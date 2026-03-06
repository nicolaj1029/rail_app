<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Session;
use Cake\Utility\Hash;

/**
 * Deterministic admin chat orchestration on top of the existing flow session.
 *
 * This is intentionally narrow:
 * - stores answers in the same flow.* session buckets as the wizard
 * - asks one whitelisted question at a time
 * - uses RegulationIndex only for citations/explanations
 * - does not let an LLM mutate state directly
 */
final class AdminChatService
{
    /**
     * @return array<string,mixed>
     */
    public function bootstrap(Session $session): array
    {
        $session->write('admin.mode', true);

        $history = (array)$session->read('admin.chat_history') ?: [];
        if ($history === []) {
            $history[] = $this->assistantMessage(
                'Admin chat er aktiv. Jeg bruger den eksisterende flow-session som sandhedskilde og guider kun gennem noeglefelter.'
            );
            $session->write('admin.chat_history', $history);
        }

        return $this->buildPayload($session);
    }

    /**
     * @return array<string,mixed>
     */
    public function reset(Session $session): array
    {
        foreach ([
            'flow.form',
            'flow.meta',
            'flow.compute',
            'flow.flags',
            'flow.incident',
            'flow.journey',
            'admin.chat_history',
        ] as $key) {
            $session->delete($key);
        }

        return $this->bootstrap($session);
    }

    /**
     * @return array<string,mixed>
     */
    public function handleMessage(Session $session, string $rawInput): array
    {
        $input = trim($rawInput);
        if ($input === '') {
            return $this->buildPayload($session, 'Tom besked. Svar paa spoergsmaalet eller brug en quick reply.');
        }
        if (in_array(strtolower($input), ['/reset', 'reset', 'nulstil'], true)) {
            return $this->reset($session);
        }

        $history = (array)$session->read('admin.chat_history') ?: [];
        $history[] = $this->userMessage($input);
        $session->write('admin.chat_history', $history);

        $flow = $this->readFlow($session);
        $preview = $this->buildPipelinePreview($flow);
        $question = $this->currentQuestion($flow, $preview);
        if ($question === null) {
            $history[] = $this->assistantMessage('Der er ikke flere aktive spoergsmaal lige nu. Brug preview-actions eller nulstil chatten.');
            $session->write('admin.chat_history', $history);
            return $this->buildPayload($session);
        }

        $result = $this->applyAnswer($flow, $question, $input);
        if (!$result['ok']) {
            $history[] = $this->assistantMessage((string)$result['message']);
            $session->write('admin.chat_history', $history);
            return $this->buildPayload($session);
        }

        $this->writeFlow($session, $flow);
        $history[] = $this->assistantMessage((string)$result['message']);
        $session->write('admin.chat_history', $history);

        $payload = $this->buildPayload($session);
        $history = (array)$session->read('admin.chat_history') ?: [];
        $next = (array)($payload['question'] ?? []);
        if ($next !== []) {
            $history[] = $this->assistantMessage((string)($next['prompt'] ?? ''));
        } else {
            $history[] = $this->assistantMessage('Der er ikke flere aktive opfoelgningsspoergsmaal. Brug preview-actions eller aabn wizard-trinnene direkte.');
        }
        $session->write('admin.chat_history', $history);
        $payload['history'] = $history;

        return $payload;
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>|null
     */
    private function currentQuestion(array $flow, ?array $preview = null): ?array
    {
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $incident = (array)($flow['incident'] ?? []);

        if (((string)($flags['step1_done'] ?? '')) !== '1') {
            return [
                'key' => 'travel_state',
                'prompt' => 'Hvad er rejse-status? Vae lg completed, ongoing eller before_start.',
                'choices' => [
                    ['value' => 'completed', 'label' => 'completed'],
                    ['value' => 'ongoing', 'label' => 'ongoing'],
                    ['value' => 'before_start', 'label' => 'before_start'],
                ],
                'citation_query' => 'Artikel 9 information',
                'flow_path' => '/flow/start',
            ];
        }

        if (($form['ticket_upload_mode'] ?? '') === '') {
            return [
                'key' => 'ticket_upload_mode',
                'prompt' => 'Hvilken billettype? Vae lg ticket, ticketless eller seasonpass.',
                'choices' => [
                    ['value' => 'ticket', 'label' => 'ticket'],
                    ['value' => 'ticketless', 'label' => 'ticketless'],
                    ['value' => 'seasonpass', 'label' => 'seasonpass'],
                ],
                'citation_query' => 'Artikel 19 2 periodekort abonnement',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (trim((string)($form['operator'] ?? '')) === '') {
            return [
                'key' => 'operator',
                'prompt' => 'Hvilken operatoer rejste passageren med?',
                'choices' => [],
                'citation_query' => 'Artikel 12 operatoer',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (trim((string)($form['operator_country'] ?? '')) === '') {
            return [
                'key' => 'operator_country',
                'prompt' => 'Hvilket land hoerer operatoeren til? Brug helst ISO2, fx DK, DE, FR.',
                'choices' => [],
                'citation_query' => 'Artikel 12 ansvar',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (((string)($flags['step2_done'] ?? '')) !== '1') {
            return [
                'key' => 'confirm_step2',
                'prompt' => 'Vil du markere TRIN 2 som udfyldt? Svar ja eller nej.',
                'choices' => [
                    ['value' => 'yes', 'label' => 'ja'],
                    ['value' => 'no', 'label' => 'nej'],
                ],
                'citation_query' => 'Artikel 12 19',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (trim((string)($form['dep_station'] ?? '')) === '') {
            return [
                'key' => 'dep_station',
                'prompt' => 'Hvad var afgangsstationen?',
                'choices' => [],
                'citation_query' => 'Artikel 20 3 station',
                'flow_path' => '/flow/station',
            ];
        }

        if (trim((string)($form['arr_station'] ?? '')) === '') {
            return [
                'key' => 'arr_station',
                'prompt' => 'Hvad var destinationsstationen?',
                'choices' => [],
                'citation_query' => 'Artikel 20 3 station',
                'flow_path' => '/flow/station',
            ];
        }

        if ((string)($incident['main'] ?? '') === '') {
            return [
                'key' => 'incident_main',
                'prompt' => 'Hvad skete der? Vae lg delay, cancellation, missed_connection eller stranded.',
                'choices' => [
                    ['value' => 'delay', 'label' => 'delay'],
                    ['value' => 'cancellation', 'label' => 'cancellation'],
                    ['value' => 'missed_connection', 'label' => 'missed_connection'],
                    ['value' => 'stranded', 'label' => 'stranded'],
                ],
                'citation_query' => 'Artikel 18 19 20',
                'flow_path' => '/flow/incident',
            ];
        }

        $delayKnown = trim((string)($form['delayAtFinalMinutes'] ?? '')) !== '' || trim((string)($form['national_delay_minutes'] ?? '')) !== '';
        if (!$delayKnown) {
            return [
                'key' => 'delay_minutes',
                'prompt' => 'Hvor mange minutters forsinkelse ved destinationen eller forventet samlet forsinkelse?',
                'choices' => [],
                'citation_query' => 'Artikel 19 forsinkelse minutter',
                'flow_path' => '/flow/incident',
            ];
        }

        return $this->followupQuestion($flow, $preview);
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $question
     * @return array{ok:bool,message:string}
     */
    private function applyAnswer(array &$flow, array $question, string $input): array
    {
        $key = (string)($question['key'] ?? '');
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $incident = (array)($flow['incident'] ?? []);
        $compute = (array)($flow['compute'] ?? []);

        switch ($key) {
            case 'travel_state':
                $value = $this->parseChoice($input, ['completed', 'ongoing', 'before_start']);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Ugyldigt svar. Brug completed, ongoing eller before_start.'];
                }
                $flags['travel_state'] = $value;
                $flags['step1_done'] = '1';
                $compute['euOnly'] = $compute['euOnly'] ?? true;
                break;

            case 'ticket_upload_mode':
                $value = $this->parseTicketMode($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Ugyldigt svar. Brug ticket, ticketless eller seasonpass.'];
                }
                $form['ticket_upload_mode'] = $value;
                $flags['gate_season_pass'] = ($value === 'seasonpass') ? '1' : '';
                break;

            case 'operator':
                $form['operator'] = trim($input);
                break;

            case 'operator_country':
                $country = $this->normalizeCountry($input);
                if ($country === '') {
                    return ['ok' => false, 'message' => 'Kunne ikke normalisere landekoden. Brug fx DK, DE eller FR.'];
                }
                $form['operator_country'] = $country;
                break;

            case 'confirm_step2':
                $value = $this->parseYesNo($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Svar ja eller nej.'];
                }
                if ($value) {
                    $flags['step2_done'] = '1';
                }
                break;

            case 'dep_station':
                $form['dep_station'] = trim($input);
                break;

            case 'arr_station':
                $form['arr_station'] = trim($input);
                $flags['step3_done'] = '1';
                $flags['step4_done'] = '1';
                break;

            case 'incident_main':
                $value = $this->parseIncident($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Ugyldigt svar. Brug delay, cancellation, missed_connection eller stranded.'];
                }
                if ($value === 'missed_connection') {
                    $incident['main'] = 'delay';
                    $incident['missed'] = true;
                } elseif ($value === 'stranded') {
                    $incident['main'] = 'cancellation';
                    $incident['missed'] = false;
                    $flags['gate_art20_2c'] = '1';
                } else {
                    $incident['main'] = $value;
                    $incident['missed'] = false;
                }
                break;

            case 'delay_minutes':
                $minutes = $this->parseMinutes($input);
                if ($minutes === null) {
                    return ['ok' => false, 'message' => 'Jeg kunne ikke laese et antal minutter. Skriv fx 37.'];
                }
                $form['delayAtFinalMinutes'] = (string)$minutes;
                $form['national_delay_minutes'] = (string)$minutes;
                $compute['delayAtFinalMinutes'] = $minutes;
                $compute['delayMinEU'] = $minutes;
                $flags['step5_done'] = '1';
                break;

            case 'separate_contract_notice':
            case 'through_ticket_disclosure':
            case 'meal_offered':
            case 'hotel_offered':
            case 'alt_transport_provided':
            case 'a20_3_solution_offered':
            case 'a20_3_self_paid':
                $value = $this->parseTriChoice($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Brug ja, nej eller ved ikke.'];
                }
                $form[$key] = $value;
                break;
        }

        $flow['form'] = $form;
        $flow['flags'] = $flags;
        $flow['incident'] = $incident;
        $flow['compute'] = $compute;
        $this->recomputeDerivedFlags($flow);

        return ['ok' => true, 'message' => $this->confirmationMessage($key, $flow)];
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function recomputeDerivedFlags(array &$flow): void
    {
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $incident = (array)($flow['incident'] ?? []);
        $delay = (int)($form['delayAtFinalMinutes'] ?? $form['national_delay_minutes'] ?? 0);
        $isSeason = ((string)($form['ticket_upload_mode'] ?? '')) === 'seasonpass';
        $isCancel = ((string)($incident['main'] ?? '')) === 'cancellation';
        $isMissed = (bool)($incident['missed'] ?? false);
        $isStranded = $isCancel && ((string)($flags['gate_art20_2c'] ?? '')) === '1';

        if (((string)($flags['step2_done'] ?? '')) === '1') {
            $flags['gate_season_pass'] = $isSeason ? '1' : '';
        }

        $gateArt18 = $isCancel || $isMissed || $delay >= 60;
        $gateArt20 = $isCancel || $delay >= 60;
        $gateArt20_2c = $isStranded;

        $flags['gate_art18'] = $gateArt18 ? '1' : '';
        $flags['gate_art20'] = $gateArt20 ? '1' : '';
        $flags['gate_art20_2c'] = $gateArt20_2c ? '1' : '';

        $flow['flags'] = $flags;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(Session $session, ?string $notice = null): array
    {
        $flow = $this->readFlow($session);
        $preview = $this->buildPipelinePreview($flow);
        $question = $this->currentQuestion($flow, $preview);
        $history = (array)$session->read('admin.chat_history') ?: [];
        $summary = $this->buildSummary($flow);
        $citations = $this->buildCitations($question);
        $stepper = (new FlowStepsService())->buildSteps((array)($flow['flags'] ?? []), 'start');
        $visibleSteps = [];
        foreach ($stepper as $step) {
            $visible = !array_key_exists('visible', $step) || (bool)$step['visible'];
            if (!$visible) {
                continue;
            }
            $visibleSteps[] = [
                'title' => (string)($step['title'] ?? ''),
                'ui_num' => $step['ui_num'] ?? null,
                'action' => (string)($step['action'] ?? ''),
                'state' => (string)($step['state'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'notice' => $notice,
            'history' => $history,
            'question' => $question,
            'summary' => $summary,
            'preview' => $preview,
            'citations' => $citations,
            'visible_steps' => $visibleSteps,
            'flow' => $flow,
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed>|null $preview
     * @return array<string,mixed>|null
     */
    private function followupQuestion(array $flow, ?array $preview = null): ?array
    {
        $form = (array)($flow['form'] ?? []);
        $raw = (array)(($preview['raw'] ?? null) ?: []);

        $askTri = function (string $key, string $prompt, string $query, string $path): array {
            return [
                'key' => $key,
                'prompt' => $prompt,
                'choices' => [
                    ['value' => 'yes', 'label' => 'ja'],
                    ['value' => 'no', 'label' => 'nej'],
                    ['value' => 'unknown', 'label' => 'ved ikke'],
                ],
                'citation_query' => $query,
                'flow_path' => $path,
            ];
        };

        $art12Missing = (array)(Hash::get($raw, 'art12.missing') ?? []);
        if (in_array('separate_contract_notice', $art12Missing, true) && ($form['separate_contract_notice'] ?? '') === '') {
            return $askTri(
                'separate_contract_notice',
                'Var der en tydelig notits om saerskilte kontrakter foer koeb?',
                'Artikel 12 separate contracts notice',
                '/flow/entitlements'
            );
        }
        if (in_array('through_ticket_disclosure', $art12Missing, true) && ($form['through_ticket_disclosure'] ?? '') === '') {
            return $askTri(
                'through_ticket_disclosure',
                'Blev kontraktstrukturen tydeligt oplyst foer koeb?',
                'Artikel 12 through ticket disclosure',
                '/flow/entitlements'
            );
        }

        $art20Missing = (array)(Hash::get($raw, 'art20_assistance.missing') ?? []);
        $orderedArt20 = [
            'meal_offered' => ['Blev maaltider eller forfriskninger tilbudt?', 'Artikel 20 2 a maaltider', '/flow/assistance'],
            'hotel_offered' => ['Blev hotel eller overnatning tilbudt?', 'Artikel 20 2 b hotel', '/flow/assistance'],
            'a20_3_solution_offered' => ['Blev der tilbudt en loesning eller alternativ transport fra stationen?', 'Artikel 20 3 alternativ transport', '/flow/station'],
            'a20_3_self_paid' => ['Maatte passageren selv betale den alternative transport?', 'Artikel 20 3 selvbetalt transport', '/flow/station'],
            'alt_transport_provided' => ['Blev alternativ transport tilbudt?', 'Artikel 20 3 alternativ transport', '/flow/assistance'],
        ];
        foreach ($orderedArt20 as $key => [$prompt, $query, $path]) {
            if (in_array($key, $art20Missing, true) && ($form[$key] ?? '') === '') {
                return $askTri($key, $prompt, $query, $path);
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildPipelinePreview(array $flow): array
    {
        $flags = (array)($flow['flags'] ?? []);
        $form = (array)($flow['form'] ?? []);

        if (((string)($flags['step1_done'] ?? '')) !== '1') {
            return [
                'status' => 'idle',
                'message' => 'Preview starter, naar TRIN 1 er udfyldt.',
                'summary' => [],
                'raw' => null,
            ];
        }

        try {
            $fixture = (new SessionToFixtureMapper())->mapSessionToFixtureEnriched($flow);
            $fixture['art12_meta'] = array_merge(
                (array)($fixture['art12_meta'] ?? []),
                $this->buildArt12Overrides($flow)
            );
            $result = (new ScenarioRunner())->evaluateFixture($fixture);
            $actual = (array)($result['actual'] ?? []);

            if (!empty($actual['error'])) {
                return [
                    'status' => 'error',
                    'message' => 'Pipeline-preview fejlede: ' . (string)($actual['error'] ?? 'unknown'),
                    'summary' => [],
                    'raw' => $actual,
                ];
            }

            $compPct = Hash::get($actual, 'compensation.pct');
            if (is_numeric($compPct)) {
                $compPct = (float)$compPct * 100;
            } else {
                $compPct = null;
            }

            $grossClaim = Hash::get($actual, 'claim.totals.gross_claim');
            if (!is_numeric($grossClaim)) {
                $grossClaim = Hash::get($actual, 'claim.gross');
            }

            $currency = (string)(Hash::get($actual, 'compensation.currency')
                ?? Hash::get($actual, 'claim.currency')
                ?? ($form['price_currency'] ?? 'EUR'));

            $previewSummary = [
                'scope' => (string)(Hash::get($actual, 'profile.scope') ?? ''),
                'profile_blocked' => (bool)(Hash::get($actual, 'profile.blocked') ?? false),
                'art12_applies' => Hash::get($actual, 'art12.art12_applies'),
                'liable_party' => (string)(Hash::get($actual, 'art12.liable_party') ?? ''),
                'compensation_minutes' => Hash::get($actual, 'compensation.minutes'),
                'compensation_pct' => $compPct,
                'compensation_amount' => Hash::get($actual, 'compensation.amount'),
                'currency' => $currency,
                'refund_eligible' => Hash::get($actual, 'refund.eligible'),
                'refund_minutes' => Hash::get($actual, 'refund.minutes'),
                'refusion_outcome' => (string)(Hash::get($actual, 'refusion.outcome') ?? ''),
                'art20_compliance' => Hash::get($actual, 'art20_assistance.compliance_status'),
                'gross_claim' => $grossClaim,
                'claim_basis' => (string)(Hash::get($actual, 'claim.breakdown.compensation.basis') ?? ''),
                'partial' => ((string)($flags['step5_done'] ?? '')) !== '1',
            ];

            $actions = $this->derivePreviewActions($flow, $actual, $previewSummary);

            return [
                'status' => 'ok',
                'message' => ((string)($flags['step5_done'] ?? '')) === '1'
                    ? 'Pipeline-preview er opdateret fra aktuel flow-session.'
                    : 'Pipeline-preview koerer paa delvist input. Udfyld TRIN 5 for et mere stabilt resultat.',
                'summary' => $previewSummary,
                'actions' => $actions,
                'raw' => $actual,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Pipeline-preview kunne ikke bygges: ' . $e->getMessage(),
                'summary' => [],
                'actions' => [],
                'raw' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,string>
     */
    private function buildArt12Overrides(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $out = [];
        foreach (['separate_contract_notice', 'through_ticket_disclosure', 'single_txn_operator', 'single_txn_retailer'] as $key) {
            $value = trim((string)($form[$key] ?? ''));
            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $actual
     * @param array<string,mixed> $summary
     * @return array<int,array<string,string>>
     */
    private function derivePreviewActions(array $flow, array $actual, array $summary): array
    {
        $flags = (array)($flow['flags'] ?? []);
        $actions = [];

        if (((string)($flags['step5_done'] ?? '')) !== '1') {
            $actions[] = [
                'label' => 'Udfyld TRIN 5',
                'detail' => 'Haendelse og forsinkelse mangler stadig for et stabilt preview.',
                'href' => '/flow/incident',
            ];
        }

        if (!empty($summary['profile_blocked'])) {
            $actions[] = [
                'label' => 'Brug national procedure',
                'detail' => 'Profilen er blokeret i EU-flowet; følg operatørens/national proces.',
                'href' => '/flow/compensation',
            ];
        }

        $art12Missing = (array)(Hash::get($actual, 'art12.missing') ?? []);
        if ($art12Missing !== []) {
            $actions[] = [
                'label' => 'Afklar Art. 12',
                'detail' => 'Manglende hooks: ' . implode(', ', array_slice($art12Missing, 0, 4)),
                'href' => '/flow/journey',
            ];
        }

        $refundEligible = $summary['refund_eligible'] ?? null;
        if ($refundEligible === true) {
            $actions[] = [
                'label' => 'Gennemgaa refusion',
                'detail' => 'Preview peger paa refusion efter Art. 18.',
                'href' => '/flow/remedies',
            ];
        }

        $refusionOutcome = trim((string)($summary['refusion_outcome'] ?? ''));
        if ($refusionOutcome !== '' && stripos($refusionOutcome, 'Oml') !== false) {
            $actions[] = [
                'label' => 'Tjek omlaegning',
                'detail' => 'Refusion-preview peger paa omlaegning/rerouting.',
                'href' => '/flow/remedies',
            ];
        }

        $art20Compliance = $summary['art20_compliance'] ?? null;
        $art20Missing = (array)(Hash::get($actual, 'art20_assistance.missing') ?? []);
        $art20Issues = (array)(Hash::get($actual, 'art20_assistance.issues') ?? []);
        if ($art20Compliance === false || $art20Missing !== [] || $art20Issues !== []) {
            $detailParts = [];
            if ($art20Issues !== []) {
                $detailParts[] = (string)$art20Issues[0];
            }
            if ($art20Missing !== []) {
                $detailParts[] = 'Manglende hooks: ' . implode(', ', array_slice($art20Missing, 0, 3));
            }
            $actions[] = [
                'label' => 'Tjek assistance',
                'detail' => implode(' ', array_filter($detailParts)) ?: 'Art. 20-data skal afklares.',
                'href' => '/flow/assistance',
            ];
        }

        $compAmount = $summary['compensation_amount'] ?? null;
        if (is_numeric($compAmount) && (float)$compAmount > 0) {
            $actions[] = [
                'label' => 'Se kompensation',
                'detail' => 'Der er et positivt kompensationspreview i TRIN 10.',
                'href' => '/flow/compensation',
            ];
        } elseif (((string)($flags['gate_season_pass'] ?? '')) === '1') {
            $actions[] = [
                'label' => 'Brug data-pack for season pass',
                'detail' => 'Season/pendler-sager skal som udgangspunkt gennem claim-assist/data-pack.',
                'href' => '/flow/compensation',
            ];
        }

        $claimGross = $summary['gross_claim'] ?? null;
        if (is_numeric($claimGross) && (float)$claimGross > 0) {
            $actions[] = [
                'label' => 'Aabn claim-resultat',
                'detail' => 'Samlet claim-preview er positivt.',
                'href' => '/flow/compensation',
            ];
        }

        $profileBanners = (array)(Hash::get($actual, 'profile.ui_banners') ?? []);
        if ($profileBanners !== []) {
            $actions[] = [
                'label' => 'Laes profiladvarsel',
                'detail' => (string)$profileBanners[0],
                'href' => '/flow/entitlements',
            ];
        }

        $deduped = [];
        foreach ($actions as $action) {
            $key = ($action['label'] ?? '') . '|' . ($action['href'] ?? '');
            if ($key === '|' || isset($deduped[$key])) {
                continue;
            }
            $deduped[$key] = $action;
        }

        return array_values($deduped);
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildSummary(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $incident = (array)($flow['incident'] ?? []);
        $compute = (array)($flow['compute'] ?? []);
        $ticketMode = (string)($form['ticket_upload_mode'] ?? '');
        $delay = (string)($form['delayAtFinalMinutes'] ?? $form['national_delay_minutes'] ?? '');

        return [
            'travel_state' => (string)($flags['travel_state'] ?? ''),
            'ticket_mode' => $ticketMode,
            'season_mode' => $ticketMode === 'seasonpass',
            'operator' => (string)($form['operator'] ?? ''),
            'operator_country' => (string)($form['operator_country'] ?? ''),
            'route' => trim((string)($form['dep_station'] ?? '') . ' -> ' . (string)($form['arr_station'] ?? '')),
            'incident_main' => (string)($incident['main'] ?? ''),
            'missed_connection' => (bool)($incident['missed'] ?? false),
            'delay_minutes' => $delay,
            'eu_only' => (bool)($compute['euOnly'] ?? true),
            'step2_done' => ((string)($flags['step2_done'] ?? '')) === '1',
            'step5_done' => ((string)($flags['step5_done'] ?? '')) === '1',
            'gate_art18' => ((string)($flags['gate_art18'] ?? '')) === '1',
            'gate_art20' => ((string)($flags['gate_art20'] ?? '')) === '1',
            'gate_art20_2c' => ((string)($flags['gate_art20_2c'] ?? '')) === '1',
            'datapack_url' => (((string)($flags['step5_done'] ?? '')) === '1' || (((string)($flags['step2_done'] ?? '')) === '1' && $ticketMode === 'seasonpass'))
                ? '/flow/compensation?datapack=1&pretty=1'
                : null,
        ];
    }

    /**
     * @param array<string,mixed>|null $question
     * @return array<int,array<string,mixed>>
     */
    private function buildCitations(?array $question): array
    {
        if ($question === null) {
            return [];
        }
        $query = trim((string)($question['citation_query'] ?? ''));
        if ($query === '') {
            return [];
        }
        $hits = (new RegulationIndex())->search($query, 2);
        $out = [];
        foreach ($hits as $hit) {
            $text = trim((string)($hit['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'id' => (string)($hit['id'] ?? ''),
                'article' => (int)($hit['article'] ?? 0),
                'page_from' => (int)($hit['page_from'] ?? 0),
                'text' => $this->truncate($text, 220),
            ];
        }
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function readFlow(Session $session): array
    {
        return [
            'form' => (array)$session->read('flow.form') ?: [],
            'meta' => (array)$session->read('flow.meta') ?: [],
            'compute' => (array)$session->read('flow.compute') ?: [],
            'flags' => (array)$session->read('flow.flags') ?: [],
            'incident' => (array)$session->read('flow.incident') ?: [],
            'journey' => (array)$session->read('flow.journey') ?: [],
        ];
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function writeFlow(Session $session, array $flow): void
    {
        foreach (['form', 'meta', 'compute', 'flags', 'incident', 'journey'] as $key) {
            $session->write('flow.' . $key, (array)($flow[$key] ?? []));
        }
    }

    /**
     * @return array{role:string,content:string}
     */
    private function assistantMessage(string $content): array
    {
        return ['role' => 'assistant', 'content' => $content];
    }

    /**
     * @return array{role:string,content:string}
     */
    private function userMessage(string $content): array
    {
        return ['role' => 'user', 'content' => $content];
    }

    private function parseChoice(string $input, array $allowed): ?string
    {
        $value = strtolower(trim($input));
        foreach ($allowed as $candidate) {
            if ($value === strtolower($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function parseTicketMode(string $input): ?string
    {
        $value = strtolower(trim($input));
        if (preg_match('/\b(season|pendler|abonnement|periode|seasonpass)\b/', $value)) {
            return 'seasonpass';
        }
        if (preg_match('/\b(ticketless|uden billet|no ticket)\b/', $value)) {
            return 'ticketless';
        }
        if (preg_match('/\b(ticket|billet)\b/', $value)) {
            return 'ticket';
        }
        return in_array($value, ['seasonpass', 'ticketless', 'ticket'], true) ? $value : null;
    }

    private function parseIncident(string $input): ?string
    {
        $value = strtolower(trim($input));
        if (preg_match('/\b(delay|forsink)/', $value)) {
            return 'delay';
        }
        if (preg_match('/\b(cancel|aflys)/', $value)) {
            return 'cancellation';
        }
        if (preg_match('/\b(missed|connection|forbindelse)/', $value)) {
            return 'missed_connection';
        }
        if (preg_match('/\b(stranded|strandet|stuck|blokeret)/', $value)) {
            return 'stranded';
        }
        return in_array($value, ['delay', 'cancellation', 'missed_connection', 'stranded'], true) ? $value : null;
    }

    private function parseYesNo(string $input): ?bool
    {
        $value = strtolower(trim($input));
        if (in_array($value, ['ja', 'yes', 'y'], true)) {
            return true;
        }
        if (in_array($value, ['nej', 'no', 'n'], true)) {
            return false;
        }
        return null;
    }

    private function parseTriChoice(string $input): ?string
    {
        $value = strtolower(trim($input));
        if (in_array($value, ['ja', 'yes', 'y'], true)) {
            return 'yes';
        }
        if (in_array($value, ['nej', 'no', 'n'], true)) {
            return 'no';
        }
        if (in_array($value, ['ved ikke', 'unknown', 'dont know', "don't know", 'idk'], true)) {
            return 'unknown';
        }

        return null;
    }

    private function parseMinutes(string $input): ?int
    {
        if (!preg_match('/(\d{1,4})/', $input, $m)) {
            return null;
        }
        return max(0, (int)$m[1]);
    }

    private function normalizeCountry(string $input): string
    {
        try {
            return (string)(new CountryNormalizer())->toIso2($input);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function confirmationMessage(string $key, array $flow): string
    {
        $summary = $this->buildSummary($flow);
        return match ($key) {
            'travel_state' => 'TRIN 1 opdateret: travel_state=' . (string)$summary['travel_state'],
            'ticket_upload_mode' => 'Billet-type sat til ' . (string)$summary['ticket_mode'],
            'operator' => 'Operatoer gemt: ' . (string)$summary['operator'],
            'operator_country' => 'Operatoer-land gemt: ' . (string)$summary['operator_country'],
            'confirm_step2' => ((bool)$summary['step2_done']) ? 'TRIN 2 er nu markeret som udfyldt.' : 'TRIN 2 blev ikke markeret endnu.',
            'dep_station', 'arr_station' => 'Rute opdateret: ' . (string)$summary['route'],
            'incident_main' => 'Haendelse sat til ' . (string)$summary['incident_main'],
            'delay_minutes' => 'Forsinkelse gemt: ' . (string)$summary['delay_minutes'] . ' min.',
            'separate_contract_notice' => 'Art. 12-notits gemt.',
            'through_ticket_disclosure' => 'Art. 12-disclosure gemt.',
            'meal_offered' => 'Maaltids-oplysning gemt.',
            'hotel_offered' => 'Hotel-oplysning gemt.',
            'alt_transport_provided' => 'Alternativ transport-oplysning gemt.',
            'a20_3_solution_offered' => 'Art. 20(3)-oplysning gemt.',
            'a20_3_self_paid' => 'Selvbetalt transport-oplysning gemt.',
            default => 'Svar gemt.',
        };
    }

    private function truncate(string $value, int $maxLen): string
    {
        if (mb_strlen($value) <= $maxLen) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $maxLen - 3)) . '...';
    }
}
