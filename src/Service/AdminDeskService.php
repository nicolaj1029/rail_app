<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\EntityInterface;
use Cake\Http\Session;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Throwable;

final class AdminDeskService
{
    private Table $cases;

    private Table $claims;

    public function __construct()
    {
        $locator = TableRegistry::getTableLocator();
        $this->cases = $locator->get('Cases');
        $this->claims = $locator->get('Claims');
    }

    public function getRole(Session $session): string
    {
        $role = strtolower(trim((string)$session->read('admin.role')));

        return in_array($role, ['jurist', 'operator'], true) ? $role : 'jurist';
    }

    public function getAuthenticatedUser(Session $session): string
    {
        return trim((string)$session->read('admin.auth_user'));
    }

    public function getRoleLabel(Session $session): string
    {
        $label = trim((string)$session->read('admin.auth_label'));

        return $label !== '' ? $label : ucfirst($this->getRole($session));
    }

    public function roleIsLocked(Session $session): bool
    {
        return (bool)$session->read('admin.role_locked');
    }

    public function setRole(Session $session, string $role): string
    {
        if ($this->roleIsLocked($session)) {
            return $this->getRole($session);
        }

        $normalized = strtolower(trim($role));
        if (!in_array($normalized, ['jurist', 'operator'], true)) {
            $normalized = 'jurist';
        }
        $session->write('admin.role', $normalized);

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    public function buildInbox(Session $session, string $filter = 'all', string $search = ''): array
    {
        $items = [];

        $currentSession = $this->buildCurrentSessionItem($session);
        if ($currentSession !== null) {
            $items[] = $currentSession;
        }

        try {
            foreach ($this->cases->find()->orderDesc('modified')->limit(100)->all() as $case) {
                $items[] = $this->buildCaseItem($case);
            }
        } catch (Throwable) {
        }

        try {
            foreach ($this->claims->find()->orderDesc('modified')->limit(100)->all() as $claim) {
                $items[] = $this->buildClaimItem($claim);
            }
        } catch (Throwable) {
        }

        foreach ((new PassengerDataService())->listCases() as $shadowCase) {
            $items[] = $this->buildShadowCaseItem($shadowCase);
        }

        $items = array_map(function (array $item): array {
            $source = (string)($item['source'] ?? '');
            $id = (string)($item['id'] ?? '');
            $followUp = $this->followUpFor($source, $id);
            $item['follow_up'] = $followUp;
            $item['notes_count'] = count($this->notesFor($source, $id));

            return $item;
        }, $items);

        usort($items, static function (array $a, array $b): int {
            $aTime = strtotime((string)($a['updated_at'] ?? '')) ?: 0;
            $bTime = strtotime((string)($b['updated_at'] ?? '')) ?: 0;

            return $bTime <=> $aTime;
        });

        $normalizedFilter = $this->normalizeInboxFilter($filter);
        $normalizedSearch = mb_strtolower(trim($search));
        $items = array_values(array_filter($items, function (array $item) use ($normalizedFilter, $normalizedSearch): bool {
            if (!$this->matchesInboxFilter($item, $normalizedFilter)) {
                return false;
            }
            if ($normalizedSearch === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', array_filter([
                (string)($item['title'] ?? ''),
                (string)($item['subtitle'] ?? ''),
                (string)($item['source'] ?? ''),
                (string)($item['ticket_mode'] ?? ''),
                (string)($item['next_action'] ?? ''),
                (string)(($item['follow_up']['reason'] ?? '')),
            ])));

            return str_contains($haystack, $normalizedSearch);
        }));

        $stats = [
            'all' => count($items),
            'awaiting_passenger' => 0,
            'in_review' => 0,
            'legal_review' => 0,
            'ready_to_submit' => 0,
            'submitted' => 0,
            'resolved' => 0,
        ];

        foreach ($items as $item) {
            $status = (string)($item['ops_status'] ?? '');
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return [
            'items' => $items,
            'stats' => $stats,
            'filter' => $normalizedFilter,
            'search' => $search,
            'available_filters' => $this->availableInboxFilters(),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function loadDeskItem(Session $session, string $source, string $id): ?array
    {
        $cockpit = match ($source) {
            'session' => $this->buildCurrentSessionCockpit($session),
            'case' => $this->buildStoredCaseCockpit($id),
            'claim' => $this->buildClaimCockpit($id),
            'shadow_case' => $this->buildShadowCaseCockpit($id),
            default => null,
        };
        if ($cockpit === null) {
            return null;
        }

        $cockpit['notes'] = $this->notesFor($source, $id);
        $cockpit['follow_up'] = $this->followUpFor($source, $id);

        return $cockpit;
    }

    public function updateStatus(string $source, string $id, string $status): bool
    {
        return $this->updateStatusForRole('jurist', $source, $id, $status);
    }

    /**
     * @return array<string,string>
     */
    public function availableInboxFilters(): array
    {
        return [
            'all' => 'Alle',
            'awaiting_passenger' => 'Afventer passager',
            'in_review' => 'Under behandling',
            'legal_review' => 'Juridisk review',
            'ready_to_submit' => 'Klar til indsendelse',
            'submitted' => 'Indsendt',
            'resolved' => 'Løst',
            'due_today' => 'Forfalder i dag',
            'overdue' => 'Overskredet',
            'scheduled' => 'Har opfølgning',
        ];
    }

    public function addNote(string $source, string $id, string $role, string $author, string $text): bool
    {
        $source = trim($source);
        $id = trim($id);
        $text = trim($text);
        if ($source === '' || $id === '' || $text === '') {
            return false;
        }

        $state = $this->readDeskState();
        $key = $this->stateKey($source, $id);
        $notes = (array)($state['notes'][$key] ?? []);
        $notes[] = [
            'created_at' => date('c'),
            'role' => $role,
            'author' => $author !== '' ? $author : 'admin',
            'text' => $text,
        ];
        $state['notes'][$key] = $notes;

        return $this->writeDeskState($state);
    }

    public function saveFollowUp(string $source, string $id, string $role, string $author, string $dueAt, string $reason): bool
    {
        $source = trim($source);
        $id = trim($id);
        $reason = trim($reason);
        $normalizedDue = trim($dueAt);
        if ($source === '' || $id === '') {
            return false;
        }

        $state = $this->readDeskState();
        $key = $this->stateKey($source, $id);
        if ($normalizedDue === '') {
            unset($state['follow_ups'][$key]);
            return $this->writeDeskState($state);
        }

        $timestamp = strtotime($normalizedDue);
        if ($timestamp === false) {
            return false;
        }

        $state['follow_ups'][$key] = [
            'due_at' => date('c', $timestamp),
            'reason' => $reason,
            'role' => $role,
            'author' => $author !== '' ? $author : 'admin',
            'updated_at' => date('c'),
        ];

        return $this->writeDeskState($state);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function notesFor(string $source, string $id): array
    {
        $state = $this->readDeskState();
        $key = $this->stateKey($source, $id);

        return array_values((array)($state['notes'][$key] ?? []));
    }

    /**
     * @return array<string,mixed>
     */
    public function followUpFor(string $source, string $id): array
    {
        $state = $this->readDeskState();
        $key = $this->stateKey($source, $id);

        return (array)($state['follow_ups'][$key] ?? []);
    }

    public function updateStatusForRole(string $role, string $source, string $id, string $status): bool
    {
        $normalized = $this->normalizeOpsStatus($status);
        if ($normalized === '') {
            return false;
        }
        if (!array_key_exists($normalized, $this->allowedStatusesForRole($role))) {
            return false;
        }

        if ($source === 'case') {
            try {
                $entity = $this->cases->get($id);
                $entity->set('status', $normalized);

                return (bool)$this->cases->save($entity);
            } catch (Throwable) {
                return false;
            }
        }

        if ($source === 'claim') {
            try {
                $entity = $this->claims->get($id);
                $entity->set('status', $normalized);

                return (bool)$this->claims->save($entity);
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $cockpit
     * @return list<array<string,mixed>>
     */
    public function playbooksForRole(string $role, array $cockpit): array
    {
        $item = (array)($cockpit['item'] ?? []);
        $opsStatus = (string)($item['ops_status'] ?? 'in_review');
        $source = (string)($cockpit['source'] ?? '');
        $legalPanel = (array)($cockpit['legal_panel'] ?? []);
        $summaryRows = (array)($cockpit['summary_rows'] ?? []);
        $ticketMode = strtolower(trim((string)($summaryRows['Billetmode'] ?? $item['ticket_mode'] ?? '')));
        $playbooks = [];

        if ($role === 'operator') {
            $playbooks[] = [
                'title' => 'Live intake',
                'text' => 'Bekræft kun kernefakta: operatør, rute, forsinkelse, billetgrundlag og uploads. Undgå juridiske løfter.',
            ];
            $playbooks[] = [
                'title' => 'Eskalering',
                'text' => 'Hvis policy, season-pass eller artikelvurdering er uklar, skift status til Juridisk review og overlad sagen til jurist.',
            ];
            if ($ticketMode === '' || $ticketMode === 'ticketless') {
                $playbooks[] = [
                    'title' => 'Mangler dokumentation',
                    'text' => 'Bed passageren om billet, season-dokument eller kvittering før du lover næste skridt.',
                ];
            }
            if (($legalPanel['extraordinary'] ?? false) === true || ($legalPanel['profile_blocked'] ?? false) === true) {
                $playbooks[] = [
                    'title' => 'Hold hænderne fra juraen',
                    'text' => 'Denne sag har juridiske markører. Operator bør ikke afslutte den uden jurist.',
                ];
            }
        } else {
            $playbooks[] = [
                'title' => 'Jurist review',
                'text' => 'Brug cockpit til at afgøre ansvar, kompensation, refund og operator policy før endelig status.',
            ];
            if ($opsStatus === 'legal_review') {
                $playbooks[] = [
                    'title' => 'Afklar policy og outcome',
                    'text' => 'Når juraen er afklaret, flyt sagen til Klar til indsendelse eller tilbage til Afventer passager hvis der mangler materiale.',
                ];
            }
            if ($ticketMode === 'seasonpass') {
                $playbooks[] = [
                    'title' => 'Season pass',
                    'text' => 'Kontrollér operator-policy og hold claim-assist/data-pack som baseline, medmindre policy er verificeret nok til noget stærkere.',
                ];
            }
            if ($source === 'session') {
                $playbooks[] = [
                    'title' => 'Live med passager',
                    'text' => 'Brug admin-chatten til at lukke blockers i realtid og opret sag fra sessionen, når kernen er bekræftet.',
                ];
            }
        }

        return $playbooks;
    }

    /**
     * @return array<string,string>
     */
    public function allowedStatusesForRole(string $role): array
    {
        if ($role === 'operator') {
            return [
                'awaiting_passenger' => 'Afventer passager',
                'in_review' => 'Under behandling',
                'legal_review' => 'Send til jurist',
                'ready_to_submit' => 'Klar til indsendelse',
                'submitted' => 'Indsendt',
            ];
        }

        return [
            'awaiting_passenger' => 'Afventer passager',
            'in_review' => 'Under behandling',
            'legal_review' => 'Juridisk review',
            'ready_to_submit' => 'Klar til indsendelse',
            'submitted' => 'Indsendt',
            'resolved' => 'Løst',
            'closed' => 'Lukket',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildCurrentSessionItem(Session $session): ?array
    {
        $payload = (new AdminChatService())->bootstrap($session);
        $summary = (array)($payload['summary'] ?? []);
        $history = (array)($payload['history'] ?? []);

        $hasMeaningfulState =
            trim((string)($summary['operator'] ?? '')) !== '' ||
            trim((string)($summary['route'] ?? '')) !== '' ||
            trim((string)($summary['ticket_mode'] ?? '')) !== '' ||
            count($history) > 1;

        if (!$hasMeaningfulState) {
            return null;
        }

        $preview = (array)($payload['preview'] ?? []);
        $opsStatus = $this->statusFromPreview($preview);

        return [
            'source' => 'session',
            'id' => 'current',
            'title' => trim((string)($summary['route'] ?? '')) !== '' ? (string)$summary['route'] : 'Aktiv live session',
            'subtitle' => trim((string)($summary['operator'] ?? '')) !== '' ? (string)$summary['operator'] : 'Flow-session i browseren',
            'ops_status' => $opsStatus,
            'ops_status_label' => $this->opsStatusLabel($opsStatus),
            'updated_at' => date('c'),
            'delay_minutes' => $summary['delay_minutes'] ?? null,
            'ticket_mode' => (string)($summary['ticket_mode'] ?? ''),
            'next_action' => $this->nextActionFromPayload($payload),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCaseItem(EntityInterface $case): array
    {
        $route = trim((string)$case->get('operator'));
        $travelDate = $case->get('travel_date');
        $updated = $case->get('modified') ?: $case->get('created');
        $opsStatus = $this->normalizeOpsStatus((string)$case->get('status'));

        return [
            'source' => 'case',
            'id' => (string)$case->get('id'),
            'title' => $route !== '' ? $route : ('Sag ' . (string)$case->get('ref')),
            'subtitle' => trim((string)$case->get('passenger_name')) !== ''
                ? (string)$case->get('passenger_name')
                : ((string)$case->get('ref')),
            'ops_status' => $opsStatus !== '' ? $opsStatus : 'in_review',
            'ops_status_label' => $this->opsStatusLabel($opsStatus !== '' ? $opsStatus : 'in_review'),
            'updated_at' => $updated ? (string)$updated : date('c'),
            'delay_minutes' => $case->get('delay_min_eu'),
            'ticket_mode' => '',
            'next_action' => $opsStatus === 'ready_to_submit' ? 'Tjek samtykke og indsendelse' : 'Åbn cockpit',
            'meta' => [
                'ref' => (string)$case->get('ref'),
                'travel_date' => $travelDate ? (string)$travelDate : '',
                'country' => (string)$case->get('country'),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildClaimItem(EntityInterface $claim): array
    {
        $status = $this->normalizeOpsStatus((string)$claim->get('status'));
        $payoutStatus = strtolower(trim((string)$claim->get('payout_status')));
        $opsStatus = $payoutStatus === 'paid' ? 'resolved' : ($status !== '' ? $status : 'submitted');
        $updated = $claim->get('modified') ?: $claim->get('created');

        return [
            'source' => 'claim',
            'id' => (string)$claim->get('id'),
            'title' => trim((string)$claim->get('case_number')) !== ''
                ? ('Claim ' . (string)$claim->get('case_number'))
                : ('Claim #' . (string)$claim->get('id')),
            'subtitle' => trim((string)$claim->get('client_name')) !== ''
                ? (string)$claim->get('client_name')
                : (string)$claim->get('operator'),
            'ops_status' => $opsStatus,
            'ops_status_label' => $this->opsStatusLabel($opsStatus),
            'updated_at' => $updated ? (string)$updated : date('c'),
            'delay_minutes' => $claim->get('delay_min'),
            'ticket_mode' => (string)$claim->get('product'),
            'next_action' => $payoutStatus === 'paid' ? 'Arkivér eller luk sag' : 'Følg op på claim-status',
            'meta' => [
                'operator' => (string)$claim->get('operator'),
                'email' => (string)$claim->get('client_email'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $shadowCase
     * @return array<string,mixed>
     */
    private function buildShadowCaseItem(array $shadowCase): array
    {
        return [
            'source' => 'shadow_case',
            'id' => (string)($shadowCase['file'] ?? ''),
            'title' => trim((string)($shadowCase['route_label'] ?? '')) !== ''
                ? (string)$shadowCase['route_label']
                : ('Shadow case ' . (string)($shadowCase['file'] ?? '')),
            'subtitle' => (string)($shadowCase['file'] ?? ''),
            'ops_status' => 'submitted',
            'ops_status_label' => $this->opsStatusLabel('submitted'),
            'updated_at' => (string)($shadowCase['modified'] ?? date('c')),
            'delay_minutes' => $shadowCase['delay_minutes'] ?? null,
            'ticket_mode' => (string)($shadowCase['ticket_mode'] ?? ''),
            'next_action' => 'Kontrollér backend-fil og match mod reel sag',
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildCurrentSessionCockpit(Session $session): ?array
    {
        $payload = (new AdminChatService())->bootstrap($session);
        $summary = (array)($payload['summary'] ?? []);
        $preview = (array)($payload['preview'] ?? []);
        $question = (array)($payload['question'] ?? []);

        $item = $this->buildCurrentSessionItem($session);
        if ($item === null) {
            return null;
        }

        return [
            'item' => $item,
            'source' => 'session',
            'live' => true,
            'summary_rows' => [
                'Operatør' => (string)($summary['operator'] ?? '-'),
                'Land' => (string)($summary['operator_country'] ?? '-'),
                'Produkt' => (string)($summary['operator_product'] ?? '-'),
                'Billetmode' => (string)($summary['ticket_mode'] ?? '-'),
                'Rute' => (string)($summary['route'] ?? '-'),
                'Forsinkelse' => ($summary['delay_minutes'] ?? null) !== null ? ((string)$summary['delay_minutes'] . ' min') : '-',
            ],
            'action_panel' => [
                'primary' => $this->nextActionFromPayload($payload),
                'question' => (string)($question['prompt'] ?? ''),
                'upload_hint' => (array)($payload['upload_hint'] ?? []),
            ],
            'ops_panel' => [
                'blockers' => (array)($preview['blockers'] ?? []),
                'actions' => (array)($preview['actions'] ?? []),
                'steps' => (array)($payload['visible_steps'] ?? []),
            ],
            'legal_panel' => (array)($preview['summary'] ?? []),
            'citations' => (array)($payload['citations'] ?? []),
            'history' => (array)($payload['history'] ?? []),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildStoredCaseCockpit(string $id): ?array
    {
        try {
            $case = $this->cases->find()->where(['id' => $id])->first();
        } catch (Throwable) {
            return null;
        }
        if ($case === null) {
            return null;
        }

        $item = $this->buildCaseItem($case);
        $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $summary = $this->summaryFromFlowSnapshot($snapshot);

        return [
            'item' => $item,
            'source' => 'case',
            'live' => false,
            'summary_rows' => [
                'Reference' => (string)$case->get('ref'),
                'Passager' => (string)$case->get('passenger_name'),
                'Operatør' => (string)$case->get('operator'),
                'Land' => (string)$case->get('country'),
                'Rejsedato' => (string)$case->get('travel_date'),
                'Forsinkelse' => ((string)$case->get('delay_min_eu')) . ' min',
            ],
            'action_panel' => [
                'primary' => $item['next_action'],
                'question' => '',
                'upload_hint' => [],
            ],
            'ops_panel' => [
                'blockers' => $this->blockersFromSnapshot($snapshot),
                'actions' => $this->actionsFromCase($case),
                'steps' => $summary['steps'],
            ],
            'legal_panel' => [
                'liable_party' => (string)$case->get('operator'),
                'compensation_amount' => $case->get('comp_amount'),
                'currency' => (string)$case->get('currency'),
                'compensation_pct' => $case->get('comp_band'),
                'refund_eligible' => (string)$case->get('remedy_choice') !== '',
                'art20_expenses_total' => $case->get('art20_expenses_total'),
                'eu_only' => (bool)$case->get('eu_only'),
                'extraordinary' => (bool)$case->get('extraordinary'),
            ],
            'citations' => [],
            'history' => [],
            'snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildClaimCockpit(string $id): ?array
    {
        try {
            $claim = $this->claims->find()->contain(['ClaimAttachments'])->where(['id' => $id])->first();
        } catch (Throwable) {
            return null;
        }
        if ($claim === null) {
            return null;
        }

        $item = $this->buildClaimItem($claim);
        $attachments = [];
        foreach ((array)$claim->get('claim_attachments') as $attachment) {
            $attachments[] = [
                'type' => (string)$attachment->get('type'),
                'name' => (string)$attachment->get('original_name'),
                'path' => (string)$attachment->get('path'),
            ];
        }

        return [
            'item' => $item,
            'source' => 'claim',
            'live' => false,
            'summary_rows' => [
                'Sagsnr' => (string)$claim->get('case_number'),
                'Klient' => (string)$claim->get('client_name'),
                'E-mail' => (string)$claim->get('client_email'),
                'Operatør' => (string)$claim->get('operator'),
                'Produkt' => (string)$claim->get('product'),
                'Forsinkelse' => ((string)$claim->get('delay_min')) . ' min',
            ],
            'action_panel' => [
                'primary' => $item['next_action'],
                'question' => '',
                'upload_hint' => [],
            ],
            'ops_panel' => [
                'blockers' => [],
                'actions' => [
                    ['title' => 'Opdatér status', 'text' => 'Brug statuspanelet nedenfor til at styre sagens drift.'],
                    ['title' => 'Tjek bilag', 'text' => count($attachments) > 0 ? count($attachments) . ' bilag knyttet til claimen.' : 'Ingen bilag knyttet endnu.'],
                ],
                'steps' => [],
            ],
            'legal_panel' => [
                'computed_percent' => $claim->get('computed_percent'),
                'compensation_amount' => $claim->get('compensation_amount'),
                'fee_percent' => $claim->get('fee_percent'),
                'fee_amount' => $claim->get('fee_amount'),
                'payout_amount' => $claim->get('payout_amount'),
                'currency' => (string)$claim->get('currency'),
                'payout_status' => (string)$claim->get('payout_status'),
            ],
            'citations' => [],
            'history' => [],
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildShadowCaseCockpit(string $id): ?array
    {
        foreach ((new PassengerDataService())->listCases() as $shadowCase) {
            if ((string)($shadowCase['file'] ?? '') !== $id) {
                continue;
            }

            $item = $this->buildShadowCaseItem($shadowCase);

            return [
                'item' => $item,
                'source' => 'shadow_case',
                'live' => false,
                'summary_rows' => [
                    'Fil' => (string)($shadowCase['file'] ?? ''),
                    'Rute' => (string)($shadowCase['route_label'] ?? ''),
                    'Fra' => (string)($shadowCase['dep_station'] ?? ''),
                    'Til' => (string)($shadowCase['arr_station'] ?? ''),
                    'Forsinkelse' => ($shadowCase['delay_minutes'] ?? null) !== null ? ((string)$shadowCase['delay_minutes'] . ' min') : '-',
                    'Billetmode' => (string)($shadowCase['ticket_mode'] ?? ''),
                ],
                'action_panel' => [
                    'primary' => 'Match denne shadow-fil mod en reel sag eller opret en ny case',
                    'question' => '',
                    'upload_hint' => [],
                ],
                'ops_panel' => [
                    'blockers' => [],
                    'actions' => [
                        ['title' => 'Kontrollér fil', 'text' => 'Shadow-case ligger stadig som backend-fil og bør vurderes manuelt.'],
                    ],
                    'steps' => [],
                ],
                'legal_panel' => [
                    'status' => (string)($shadowCase['status'] ?? ''),
                    'submitted_at' => (string)($shadowCase['submitted_at'] ?? ''),
                ],
                'citations' => [],
                'history' => [],
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function nextActionFromPayload(array $payload): string
    {
        $question = (array)($payload['question'] ?? []);
        if (trim((string)($question['prompt'] ?? '')) !== '') {
            return (string)$question['prompt'];
        }

        $preview = (array)($payload['preview'] ?? []);
        $actions = (array)($preview['actions'] ?? []);
        if ($actions !== []) {
            return (string)($actions[0]['title'] ?? 'Åbn cockpit');
        }

        return 'Åbn cockpit';
    }

    private function normalizeInboxFilter(string $filter): string
    {
        $normalized = strtolower(trim($filter));

        return array_key_exists($normalized, $this->availableInboxFilters()) ? $normalized : 'all';
    }

    /**
     * @param array<string,mixed> $item
     */
    private function matchesInboxFilter(array $item, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }

        $followUp = (array)($item['follow_up'] ?? []);
        $dueAt = trim((string)($followUp['due_at'] ?? ''));
        $dueTs = $dueAt !== '' ? strtotime($dueAt) : false;
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $todayEnd = strtotime(date('Y-m-d 23:59:59'));
        $now = time();

        return match ($filter) {
            'due_today' => $dueTs !== false && $dueTs >= $todayStart && $dueTs <= $todayEnd,
            'overdue' => $dueTs !== false && $dueTs < $todayStart,
            'scheduled' => $dueTs !== false && $dueTs >= $now,
            default => (string)($item['ops_status'] ?? '') === $filter,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function readDeskState(): array
    {
        $file = $this->deskStateFile();
        if (!is_file($file)) {
            return ['notes' => [], 'follow_ups' => []];
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            return ['notes' => [], 'follow_ups' => []];
        }

        return [
            'notes' => (array)($decoded['notes'] ?? []),
            'follow_ups' => (array)($decoded['follow_ups'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private function writeDeskState(array $state): bool
    {
        $file = $this->deskStateFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }

    private function deskStateFile(): string
    {
        return TMP . 'admin_desk_state.json';
    }

    private function stateKey(string $source, string $id): string
    {
        return strtolower(trim($source)) . ':' . trim($id);
    }

    /**
     * @param array<string,mixed> $preview
     */
    private function statusFromPreview(array $preview): string
    {
        $blockers = (array)($preview['blockers'] ?? []);
        $summary = (array)($preview['summary'] ?? []);
        if ($blockers !== []) {
            foreach ($blockers as $blocker) {
                $key = strtolower(trim((string)($blocker['key'] ?? '')));
                if (str_contains($key, 'ticket') || str_contains($key, 'operator') || str_contains($key, 'delay') || str_contains($key, 'station')) {
                    return 'awaiting_passenger';
                }
            }

            return 'in_review';
        }

        if ((bool)($summary['profile_blocked'] ?? false)) {
            return 'legal_review';
        }

        if (($summary['compensation_amount'] ?? null) !== null || ($summary['refund_eligible'] ?? false) === true) {
            return 'ready_to_submit';
        }

        return 'in_review';
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array{steps:list<array<string,mixed>>}
     */
    private function summaryFromFlowSnapshot(array $snapshot): array
    {
        $flags = (array)($snapshot['flags'] ?? []);
        $steps = [];
        foreach ((new FlowStepsService())->buildSteps($flags, 'start') as $step) {
            $visible = !array_key_exists('visible', $step) || (bool)$step['visible'];
            if (!$visible) {
                continue;
            }
            $steps[] = [
                'title' => (string)($step['title'] ?? ''),
                'ui_num' => $step['ui_num'] ?? $step['num'] ?? null,
                'state' => (string)($step['state'] ?? ''),
            ];
        }

        return ['steps' => $steps];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return list<array<string,string>>
     */
    private function blockersFromSnapshot(array $snapshot): array
    {
        $form = (array)($snapshot['form'] ?? []);
        $blockers = [];
        if (trim((string)($form['operator'] ?? '')) === '') {
            $blockers[] = ['title' => 'Operatør mangler', 'text' => 'Sagen mangler stadig operatør.'];
        }
        if (trim((string)($form['dep_station'] ?? '')) === '' || trim((string)($form['arr_station'] ?? '')) === '') {
            $blockers[] = ['title' => 'Rute mangler', 'text' => 'Fra/til station skal være bekræftet.'];
        }
        if (trim((string)($form['delayAtFinalMinutes'] ?? '')) === '') {
            $blockers[] = ['title' => 'Forsinkelse mangler', 'text' => 'Den endelige forsinkelse er ikke gemt i snapshot.'];
        }

        return $blockers;
    }

    /**
     * @return list<array<string,string>>
     */
    private function actionsFromCase(EntityInterface $case): array
    {
        $actions = [];
        if ((bool)$case->get('eu_only')) {
            $actions[] = ['title' => 'Juridisk check', 'text' => 'Sagen er beregnet med EU-only aktivt. Kontrollér om det stadig er korrekt.'];
        }
        if ((bool)$case->get('extraordinary')) {
            $actions[] = ['title' => 'Extraordinary circumstances', 'text' => 'Force majeure/extraordinary er markeret og bør vurderes af jurist.'];
        }
        if ((float)($case->get('art20_expenses_total') ?? 0) > 0) {
            $actions[] = ['title' => 'Udgifter dokumentation', 'text' => 'Der er registreret Art. 20-udgifter. Kontrollér kvitteringer og dokumentation.'];
        }
        if ($actions === []) {
            $actions[] = ['title' => 'Snapshot klar', 'text' => 'Sagen kan behandles ud fra gemt snapshot og statuspanelet nedenfor.'];
        }

        return $actions;
    }

    private function normalizeOpsStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'new' => 'in_review',
            'open', 'draft' => 'in_review',
            'awaiting_passenger', 'missing_info' => 'awaiting_passenger',
            'legal_review', 'jurist', 'needs_legal' => 'legal_review',
            'ready', 'ready_to_submit' => 'ready_to_submit',
            'submitted', 'sent', 'waiting_operator', 'waiting' => 'submitted',
            'resolved', 'paid', 'closed' => $normalized === 'closed' ? 'closed' : 'resolved',
            'in_review' => 'in_review',
            default => '',
        };
    }

    private function opsStatusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_passenger' => 'Afventer passager',
            'in_review' => 'Under behandling',
            'legal_review' => 'Juridisk review',
            'ready_to_submit' => 'Klar til indsendelse',
            'submitted' => 'Indsendt / afventer',
            'resolved' => 'Løst',
            'closed' => 'Lukket',
            default => 'Under behandling',
        };
    }
}
