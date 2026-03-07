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
            'admin.chat_focus_key',
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
        $session->delete('admin.chat_focus_key');
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
        $preferredKey = $this->preferredQuestionKeyFromPreview($preview);
        $question = $this->currentQuestion($flow, $preview, $preferredKey !== '' ? $preferredKey : null);
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
     * @return array<string,mixed>
     */
    public function focusQuestion(Session $session, string $key): array
    {
        $focusKey = $this->normalizeQuestionKey(trim($key));
        if ($focusKey === '') {
            return $this->buildPayload($session, 'Ingen blocker-noegle modtaget.');
        }

        $session->write('admin.chat_focus_key', $focusKey);
        $payload = $this->buildPayload($session, 'Fokus sat til blocker: ' . $focusKey);
        $question = (array)($payload['question'] ?? []);
        if (($question['key'] ?? null) !== $focusKey) {
            $payload['notice'] = 'Blocker valgt, men spoergsmaalet er ikke aktivt endnu. Udfyld de tidligere afhængigheder først.';
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>|null
     */
    private function currentQuestion(array $flow, ?array $preview = null, ?string $preferredKey = null): ?array
    {
        $candidates = $this->buildBaseQuestions($flow);
        $candidates = array_merge($candidates, $this->buildFollowupQuestions($flow, $preview));
        $normalizedPreferredKey = $this->normalizeQuestionKey((string)$preferredKey);
        if ($normalizedPreferredKey !== '') {
            foreach ($candidates as $candidate) {
                if ($this->normalizeQuestionKey((string)($candidate['key'] ?? '')) === $normalizedPreferredKey) {
                    return $candidate;
                }
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<int,array<string,mixed>>
     */
    private function buildBaseQuestions(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $incident = (array)($flow['incident'] ?? []);
        $candidates = [];

        if (((string)($flags['step1_done'] ?? '')) !== '1') {
            $candidates[] = [
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
            $candidates[] = [
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
            $candidates[] = [
                'key' => 'operator',
                'prompt' => 'Hvilken operatoer rejste passageren med?',
                'choices' => [],
                'citation_query' => 'Artikel 12 operatoer',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (trim((string)($form['operator_country'] ?? '')) === '') {
            $candidates[] = [
                'key' => 'operator_country',
                'prompt' => 'Hvilket land hoerer operatoeren til? Brug helst ISO2, fx DK, DE, FR.',
                'choices' => [],
                'citation_query' => 'Artikel 12 ansvar',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (($form['ticket_upload_mode'] ?? '') === 'seasonpass' && trim((string)($form['operator_product'] ?? '')) === '') {
            $candidates[] = [
                'key' => 'operator_product',
                'prompt' => 'Hvilket pendler- eller season-produkt drejer det sig om?',
                'choices' => [],
                'citation_query' => 'Artikel 19 2 periodekort abonnement',
                'flow_path' => '/flow/entitlements',
            ];
        }

        if (((string)($flags['step2_done'] ?? '')) !== '1') {
            $candidates[] = [
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
            $candidates[] = [
                'key' => 'dep_station',
                'prompt' => 'Hvad var afgangsstationen?',
                'choices' => [],
                'citation_query' => 'Artikel 20 3 station',
                'flow_path' => '/flow/station',
            ];
        }

        if (trim((string)($form['arr_station'] ?? '')) === '') {
            $candidates[] = [
                'key' => 'arr_station',
                'prompt' => 'Hvad var destinationsstationen?',
                'choices' => [],
                'citation_query' => 'Artikel 20 3 station',
                'flow_path' => '/flow/station',
            ];
        }

        if ((string)($incident['main'] ?? '') === '') {
            $candidates[] = [
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
            $candidates[] = [
                'key' => 'delay_minutes',
                'prompt' => 'Hvor mange minutters forsinkelse ved destinationen eller forventet samlet forsinkelse?',
                'choices' => [],
                'citation_query' => 'Artikel 19 forsinkelse minutter',
                'flow_path' => '/flow/incident',
            ];
        }

        return $candidates;
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

            case 'operator_product':
                $form['operator_product'] = trim($input);
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

            case 'remedy_choice':
                $value = $this->parseRemedyChoice($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Brug refund_return, reroute_soonest eller reroute_later.'];
                }
                $form['remedyChoice'] = $value;
                break;

            case 'refund_requested':
            case 'return_to_origin_expense':
            case 'reroute_same_conditions_soonest':
            case 'reroute_later_at_choice':
            case 'reroute_info_within_100min':
            case 'reroute_extra_costs':
                $value = $this->parseTriChoice($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Brug ja, nej eller ved ikke.'];
                }
                $form[$key] = $value;
                break;

            case 'meal_self_paid_amount':
            case 'hotel_self_paid_amount':
            case 'a20_3_self_paid_amount':
            case 'return_to_origin_amount':
            case 'reroute_later_self_paid_amount':
            case 'reroute_extra_costs_amount':
                $currencyField = [
                    'meal_self_paid_amount' => 'meal_self_paid_currency',
                    'hotel_self_paid_amount' => 'hotel_self_paid_currency',
                    'a20_3_self_paid_amount' => 'a20_3_self_paid_currency',
                    'return_to_origin_amount' => 'return_to_origin_currency',
                    'reroute_later_self_paid_amount' => 'reroute_later_self_paid_currency',
                    'reroute_extra_costs_amount' => 'reroute_extra_costs_currency',
                ][$key];
                $parsed = $this->parseAmountInput($input);
                if ($parsed === null) {
                    return ['ok' => false, 'message' => 'Jeg kunne ikke laese et beloeb. Skriv fx 150 DKK eller 20.50 EUR.'];
                }
                $form[$key] = $parsed['amount'];
                $form[$currencyField] = $parsed['currency'] ?? $this->defaultCurrencyForFlow($flow);
                break;

            case 'reroute_later_outcome':
                $value = $this->parseRerouteLaterOutcome($input);
                if ($value === null) {
                    return ['ok' => false, 'message' => 'Brug operator_offered, self_bought eller no_solution.'];
                }
                $form['reroute_later_outcome'] = $value;
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
        $preferredKey = $this->normalizeQuestionKey(trim((string)$session->read('admin.chat_focus_key')));
        if ($preferredKey === '') {
            $preferredKey = $this->preferredQuestionKeyFromPreview($preview);
        }
        $question = $this->currentQuestion($flow, $preview, $preferredKey !== '' ? $preferredKey : null);
        $session->delete('admin.chat_focus_key');
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
     * @return array<int,array<string,mixed>>
     */
    private function buildFollowupQuestions(array $flow, ?array $preview = null): array
    {
        $form = (array)($flow['form'] ?? []);
        $raw = (array)(($preview['raw'] ?? null) ?: []);
        $questions = [];

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
        $askAmount = function (string $key, string $prompt, string $query, string $path) use ($flow): array {
            return [
                'key' => $key,
                'prompt' => $prompt . ' Skriv fx 150 ' . $this->defaultCurrencyForFlow($flow) . '.',
                'choices' => [],
                'citation_query' => $query,
                'flow_path' => $path,
            ];
        };

        $art12Missing = (array)(Hash::get($raw, 'art12.missing') ?? []);
        if (in_array('separate_contract_notice', $art12Missing, true) && ($form['separate_contract_notice'] ?? '') === '') {
            $questions[] = $askTri(
                'separate_contract_notice',
                'Var der en tydelig notits om saerskilte kontrakter foer koeb?',
                'Artikel 12 separate contracts notice',
                '/flow/entitlements'
            );
        }
        if (in_array('through_ticket_disclosure', $art12Missing, true) && ($form['through_ticket_disclosure'] ?? '') === '') {
            $questions[] = $askTri(
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
                $questions[] = $askTri($key, $prompt, $query, $path);
            }
        }

        if (($form['meal_offered'] ?? '') === 'no' && trim((string)($form['meal_self_paid_amount'] ?? '')) === '') {
            $questions[] = $askAmount(
                'meal_self_paid_amount',
                'Hvor meget betalte passageren selv for maaltider eller forfriskninger?',
                'Artikel 20 2 a maaltider udgifter',
                '/flow/assistance'
            );
        }
        if (($form['hotel_offered'] ?? '') === 'no' && trim((string)($form['hotel_self_paid_amount'] ?? '')) === '') {
            $questions[] = $askAmount(
                'hotel_self_paid_amount',
                'Hvor meget betalte passageren selv for hotel eller overnatning?',
                'Artikel 20 2 b hotel udgifter',
                '/flow/assistance'
            );
        }
        if (($form['a20_3_self_paid'] ?? '') === 'yes' && trim((string)($form['a20_3_self_paid_amount'] ?? '')) === '') {
            $questions[] = $askAmount(
                'a20_3_self_paid_amount',
                'Hvor meget betalte passageren selv for alternativ transport fra stationen?',
                'Artikel 20 3 selvbetalt transport belob',
                '/flow/station'
            );
        }

        $flags = (array)($flow['flags'] ?? []);
        if (((string)($flags['gate_art18'] ?? '')) === '1' && trim((string)($form['remedyChoice'] ?? '')) === '') {
            $questions[] = [
                'key' => 'remedy_choice',
                'prompt' => 'Hvilken Art. 18-retning passer bedst lige nu? Vae lg refund_return, reroute_soonest eller reroute_later.',
                'choices' => [
                    ['value' => 'refund_return', 'label' => 'refund_return'],
                    ['value' => 'reroute_soonest', 'label' => 'reroute_soonest'],
                    ['value' => 'reroute_later', 'label' => 'reroute_later'],
                ],
                'citation_query' => 'Artikel 18 refusion omlaegning',
                'flow_path' => '/flow/remedies',
            ];
        }

        $remedyChoice = (string)($form['remedyChoice'] ?? '');
        if ($remedyChoice === 'refund_return' && ($form['refund_requested'] ?? '') === '') {
            $questions[] = $askTri(
                'refund_requested',
                'Er refusion eksplicit anmodet eller valgt?',
                'Artikel 18 refund requested',
                '/flow/remedies'
            );
        }
        if ($remedyChoice === 'refund_return' && ($form['return_to_origin_expense'] ?? '') === '') {
            $questions[] = $askTri(
                'return_to_origin_expense',
                'Havde passageren udgifter til at komme tilbage til udgangspunktet?',
                'Artikel 18 return to origin expense',
                '/flow/remedies'
            );
        }
        if ($remedyChoice === 'refund_return' && ($form['return_to_origin_expense'] ?? '') === 'yes' && trim((string)($form['return_to_origin_amount'] ?? '')) === '') {
            $questions[] = $askAmount(
                'return_to_origin_amount',
                'Hvor meget betalte passageren for at komme tilbage til udgangspunktet?',
                'Artikel 18 return to origin amount',
                '/flow/remedies'
            );
        }

        if (in_array($remedyChoice, ['reroute_soonest', 'reroute_later'], true)) {
            $rerouteMap = [
                'reroute_same_conditions_soonest' => ['Blev omlaegning paa sammenlignelige vilkaar tilbudt snarest muligt?', 'Artikel 18 1 b omlaegning', '/flow/remedies'],
                'reroute_later_at_choice' => ['Blev omlaegning paa et senere tidspunkt efter passagerens valg tilbudt?', 'Artikel 18 1 c later at choice', '/flow/remedies'],
                'reroute_info_within_100min' => ['Kom der brugbar omlaegningsinformation inden 100 minutter?', 'Artikel 18 3 100 minutes', '/flow/remedies'],
                'reroute_extra_costs' => ['Havde passageren ekstra omlaegningsomkostninger?', 'Artikel 18 2 reroute extra costs', '/flow/remedies'],
            ];
            foreach ($rerouteMap as $key => [$prompt, $query, $path]) {
                if (($form[$key] ?? '') === '') {
                    $questions[] = $askTri($key, $prompt, $query, $path);
                }
            }
            if ($remedyChoice === 'reroute_later' && ($form['reroute_later_outcome'] ?? '') === '') {
                $questions[] = [
                    'key' => 'reroute_later_outcome',
                    'prompt' => 'Hvad skete der med den senere omlaegning? Vae lg operator_offered, self_bought eller no_solution.',
                    'choices' => [
                        ['value' => 'operator_offered', 'label' => 'operator_offered'],
                        ['value' => 'self_bought', 'label' => 'self_bought'],
                        ['value' => 'no_solution', 'label' => 'no_solution'],
                    ],
                    'citation_query' => 'Artikel 18 later reroute outcome',
                    'flow_path' => '/flow/remedies',
                ];
            }
            if ($remedyChoice === 'reroute_later' && ($form['reroute_later_outcome'] ?? '') === 'self_bought' && trim((string)($form['reroute_later_self_paid_amount'] ?? '')) === '') {
                $questions[] = $askAmount(
                    'reroute_later_self_paid_amount',
                    'Hvor meget betalte passageren selv for den senere omlaegning?',
                    'Artikel 18 later reroute self bought amount',
                    '/flow/remedies'
                );
            }
            if (($form['reroute_extra_costs'] ?? '') === 'yes' && trim((string)($form['reroute_extra_costs_amount'] ?? '')) === '') {
                $questions[] = $askAmount(
                    'reroute_extra_costs_amount',
                    'Hvor store var de ekstra omlaegningsomkostninger?',
                    'Artikel 18 2 reroute extra costs amount',
                    '/flow/remedies'
                );
            }
        }

        return $questions;
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
            $blockingFields = $this->deriveBlockingFields($flow, $actual, $previewSummary);

            return [
                'status' => 'ok',
                'message' => ((string)($flags['step5_done'] ?? '')) === '1'
                    ? 'Pipeline-preview er opdateret fra aktuel flow-session.'
                    : 'Pipeline-preview koerer paa delvist input. Udfyld TRIN 5 for et mere stabilt resultat.',
                'summary' => $previewSummary,
                'actions' => $actions,
                'blocking_fields' => $blockingFields,
                'blocking_count' => count($blockingFields),
                'raw' => $actual,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Pipeline-preview kunne ikke bygges: ' . $e->getMessage(),
                'summary' => [],
                'actions' => [],
                'blocking_fields' => [],
                'blocking_count' => 0,
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
     * @param array<string,mixed> $actual
     * @param array<string,mixed> $summary
     * @return array<int,array<string,mixed>>
     */
    private function deriveBlockingFields(array $flow, array $actual, array $summary): array
    {
        $form = (array)($flow['form'] ?? []);
        $flags = (array)($flow['flags'] ?? []);
        $items = [];
        $questionOrder = [
            'travel_state' => 10,
            'ticket_upload_mode' => 20,
            'operator' => 30,
            'operator_country' => 40,
            'operator_product' => 50,
            'confirm_step2' => 60,
            'dep_station' => 70,
            'arr_station' => 80,
            'incident_main' => 90,
            'delay_minutes' => 100,
            'separate_contract_notice' => 110,
            'through_ticket_disclosure' => 120,
            'meal_offered' => 130,
            'hotel_offered' => 140,
            'a20_3_solution_offered' => 150,
            'a20_3_self_paid' => 160,
            'alt_transport_provided' => 170,
            'meal_self_paid_amount' => 180,
            'hotel_self_paid_amount' => 190,
            'a20_3_self_paid_amount' => 200,
            'remedy_choice' => 210,
            'refund_requested' => 220,
            'return_to_origin_expense' => 230,
            'return_to_origin_amount' => 240,
            'reroute_same_conditions_soonest' => 250,
            'reroute_later_at_choice' => 260,
            'reroute_info_within_100min' => 270,
            'reroute_extra_costs' => 280,
            'reroute_later_outcome' => 290,
            'reroute_later_self_paid_amount' => 300,
            'reroute_extra_costs_amount' => 310,
            'claim_export' => 900,
        ];

        $add = function (string $key, string $label, string $detail, string $href, string $group, string $priority) use (&$items, $questionOrder): void {
            $id = $group . '|' . $key;
            if (isset($items[$id])) {
                return;
            }
            $focusKey = $this->normalizeQuestionKey($key);
            $items[$id] = [
                'key' => $key,
                'label' => $label,
                'detail' => $detail,
                'href' => $href,
                'group' => $group,
                'priority' => $priority,
                'focus_key' => $focusKey,
                'can_focus' => $focusKey !== '',
                'order' => $questionOrder[$focusKey !== '' ? $focusKey : $key] ?? 999,
            ];
        };

        foreach ($this->buildBaseQuestions($flow) as $question) {
            if (!is_array($question)) {
                continue;
            }
            $key = (string)($question['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $flowPath = (string)($question['flow_path'] ?? '/flow/start');
            $prompt = trim((string)($question['prompt'] ?? ''));
            $label = $this->blockingLabelForQuestion($key);
            $detail = $this->blockingDetailForQuestion($key, $prompt);
            $group = $key === 'operator_product' ? 'season' : 'foundation';
            $add($key, $label, $detail, $flowPath, $group, 'required_now');
        }

        $art12Labels = [
            'separate_contract_notice' => ['Afklar separate kontrakter', 'Notits om separate kontrakter mangler.', '/flow/entitlements'],
            'through_ticket_disclosure' => ['Afklar kontraktoplysning', 'Disclosure om kontraktstruktur mangler.', '/flow/entitlements'],
        ];
        foreach ((array)(Hash::get($actual, 'art12.missing') ?? []) as $key) {
            if (!isset($art12Labels[$key])) {
                continue;
            }
            [$label, $detail, $href] = $art12Labels[$key];
            $add((string)$key, $label, $detail, $href, 'art12', 'important_before_export');
        }

        $art20Labels = [
            'meal_offered' => ['Afklar måltider', 'Det er uklart om måltider eller forfriskninger blev tilbudt.', '/flow/assistance'],
            'hotel_offered' => ['Afklar hotel', 'Det er uklart om hotel/overnatning blev tilbudt.', '/flow/assistance'],
            'a20_3_solution_offered' => ['Afklar Art. 20(3)-løsning', 'Det er uklart om operatøren tilbød alternativ løsning fra stationen.', '/flow/station'],
            'a20_3_self_paid' => ['Afklar selvbetalt transport', 'Det er uklart om passageren selv betalte alternativ transport.', '/flow/station'],
            'alt_transport_provided' => ['Afklar alternativ transport', 'Det er uklart om alternativ transport blev stillet til rådighed.', '/flow/assistance'],
        ];
        foreach ((array)(Hash::get($actual, 'art20_assistance.missing') ?? []) as $key) {
            if (!isset($art20Labels[$key])) {
                continue;
            }
            [$label, $detail, $href] = $art20Labels[$key];
            $add((string)$key, $label, $detail, $href, 'art20', 'important_before_export');
        }

        if (($form['meal_offered'] ?? '') === 'no' && trim((string)($form['meal_self_paid_amount'] ?? '')) === '') {
            $add('meal_self_paid_amount', 'Angiv måltidsbeløb', 'Selvbetalte måltider mangler beløb.', '/flow/assistance', 'art20', 'important_before_export');
        }
        if (($form['hotel_offered'] ?? '') === 'no' && trim((string)($form['hotel_self_paid_amount'] ?? '')) === '') {
            $add('hotel_self_paid_amount', 'Angiv hotelbeløb', 'Selvbetalt hotel/overnatning mangler beløb.', '/flow/assistance', 'art20', 'important_before_export');
        }
        if (($form['a20_3_self_paid'] ?? '') === 'yes' && trim((string)($form['a20_3_self_paid_amount'] ?? '')) === '') {
            $add('a20_3_self_paid_amount', 'Angiv transportbeløb', 'Selvbetalt alternativ transport mangler beløb.', '/flow/station', 'art20', 'important_before_export');
        }

        if (((string)($flags['gate_art18'] ?? '')) === '1') {
            $remedyChoice = (string)($form['remedyChoice'] ?? '');
            if ($remedyChoice === '') {
                $add('remedyChoice', 'Vælg Art. 18-retning', 'Refusion/omlægning er ikke afklaret endnu.', '/flow/remedies', 'art18', 'required_now');
            }
            if ($remedyChoice === 'refund_return' && ($form['refund_requested'] ?? '') === '') {
                $add('refund_requested', 'Afklar refusion', 'Det er uklart om refusion er valgt eller anmodet.', '/flow/remedies', 'art18', 'important_before_export');
            }
            if ($remedyChoice === 'refund_return' && ($form['return_to_origin_expense'] ?? '') === '') {
                $add('return_to_origin_expense', 'Afklar returudgift', 'Det er uklart om der var udgifter til at vende tilbage til udgangspunktet.', '/flow/remedies', 'art18', 'important_before_export');
            }
            if ($remedyChoice === 'refund_return' && ($form['return_to_origin_expense'] ?? '') === 'yes' && trim((string)($form['return_to_origin_amount'] ?? '')) === '') {
                $add('return_to_origin_amount', 'Angiv returbeløb', 'Retur til udgangspunkt mangler beløb.', '/flow/remedies', 'art18', 'important_before_export');
            }
            if (in_array($remedyChoice, ['reroute_soonest', 'reroute_later'], true)) {
                $rerouteTri = [
                    'reroute_same_conditions_soonest' => ['Afklar omlægning snarest', 'Det er uklart om sammenlignelig omlægning blev tilbudt snarest muligt.'],
                    'reroute_later_at_choice' => ['Afklar senere omlægning', 'Det er uklart om senere omlægning efter passagerens valg blev tilbudt.'],
                    'reroute_info_within_100min' => ['Afklar 100-minuttersregel', 'Det er uklart om brugbar information kom inden 100 minutter.'],
                    'reroute_extra_costs' => ['Afklar ekstra omlægningsomkostninger', 'Det er uklart om omlægningen gav ekstra omkostninger.'],
                ];
                foreach ($rerouteTri as $key => [$label, $detail]) {
                    if (($form[$key] ?? '') === '') {
                        $add($key, $label, $detail, '/flow/remedies', 'art18', 'important_before_export');
                    }
                }
                if ($remedyChoice === 'reroute_later' && ($form['reroute_later_outcome'] ?? '') === '') {
                    $add('reroute_later_outcome', 'Afklar senere udfald', 'Det er uklart hvad der skete ved senere omlægning.', '/flow/remedies', 'art18', 'important_before_export');
                }
                if ($remedyChoice === 'reroute_later' && ($form['reroute_later_outcome'] ?? '') === 'self_bought' && trim((string)($form['reroute_later_self_paid_amount'] ?? '')) === '') {
                    $add('reroute_later_self_paid_amount', 'Angiv senere omlægningsbeløb', 'Selvkøbt senere omlægning mangler beløb.', '/flow/remedies', 'art18', 'important_before_export');
                }
                if (($form['reroute_extra_costs'] ?? '') === 'yes' && trim((string)($form['reroute_extra_costs_amount'] ?? '')) === '') {
                    $add('reroute_extra_costs_amount', 'Angiv ekstra omlægningsbeløb', 'Ekstra omlægningsomkostninger mangler beløb.', '/flow/remedies', 'art18', 'important_before_export');
                }
            }
        }

        if (($summary['gross_claim'] ?? null) === null && (($summary['partial'] ?? false) === false)) {
            $add('claim_export', 'Gennemgå claim-beregning', 'Claim-preview mangler stadig et stabilt resultat til eksport.', '/flow/compensation', 'claim', 'review_before_export');
        }

        $priorityOrder = [
            'required_now' => 0,
            'important_before_export' => 1,
            'review_before_export' => 2,
        ];
        uasort($items, static function (array $left, array $right) use ($priorityOrder): int {
            $leftPriority = $priorityOrder[$left['priority'] ?? 'review_before_export'] ?? 99;
            $rightPriority = $priorityOrder[$right['priority'] ?? 'review_before_export'] ?? 99;
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }
            $leftOrder = (int)($left['order'] ?? 999);
            $rightOrder = (int)($right['order'] ?? 999);
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }
            return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return array_values($items);
    }

    /**
     * @param array<string,mixed> $preview
     */
    private function preferredQuestionKeyFromPreview(array $preview): string
    {
        $blockingFields = (array)($preview['blocking_fields'] ?? []);
        foreach ($blockingFields as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!(bool)($item['can_focus'] ?? false)) {
                continue;
            }
            $focusKey = $this->normalizeQuestionKey((string)($item['focus_key'] ?? $item['key'] ?? ''));
            if ($focusKey !== '') {
                return $focusKey;
            }
        }

        return '';
    }

    private function blockingLabelForQuestion(string $key): string
    {
        return match ($this->normalizeQuestionKey($key)) {
            'travel_state' => 'Angiv rejse-status',
            'ticket_upload_mode' => 'Vælg billettype',
            'operator' => 'Angiv operatør',
            'operator_country' => 'Angiv operatørland',
            'operator_product' => 'Angiv season-produkt',
            'confirm_step2' => 'Fuldfør TRIN 2',
            'dep_station' => 'Angiv afgangsstation',
            'arr_station' => 'Angiv destinationsstation',
            'incident_main' => 'Angiv hændelse',
            'delay_minutes' => 'Angiv forsinkelse',
            default => $key,
        };
    }

    private function blockingDetailForQuestion(string $key, string $fallbackPrompt): string
    {
        return match ($this->normalizeQuestionKey($key)) {
            'travel_state' => 'Rejsestatus mangler stadig.',
            'ticket_upload_mode' => 'Billetgrundlag mangler stadig.',
            'operator' => 'Operatør mangler stadig.',
            'operator_country' => 'Operatørland mangler stadig.',
            'operator_product' => 'Pendler/season-produkt mangler for data-pack og policy-match.',
            'confirm_step2' => 'Operatør og billetgrundlag er ikke markeret som afsluttet endnu.',
            'dep_station' => 'Afgangsstation mangler stadig.',
            'arr_station' => 'Destinationsstation mangler stadig.',
            'incident_main' => 'Incident-type mangler stadig.',
            'delay_minutes' => 'Forsinkelsesminutter mangler stadig.',
            default => $fallbackPrompt !== '' ? $fallbackPrompt : 'Feltet mangler stadig.',
        };
    }

    private function normalizeQuestionKey(string $key): string
    {
        $trimmed = trim($key);
        if ($trimmed === '') {
            return '';
        }

        return match ($trimmed) {
            'remedyChoice' => 'remedy_choice',
            default => $trimmed,
        };
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

    private function parseRemedyChoice(string $input): ?string
    {
        $value = strtolower(trim($input));
        if (preg_match('/\b(refund|return|refusion)\b/', $value)) {
            return 'refund_return';
        }
        if (preg_match('/\b(soonest|snarest|now|nu)\b/', $value)) {
            return 'reroute_soonest';
        }
        if (preg_match('/\b(later|senere)\b/', $value)) {
            return 'reroute_later';
        }

        return in_array($value, ['refund_return', 'reroute_soonest', 'reroute_later'], true) ? $value : null;
    }

    private function parseRerouteLaterOutcome(string $input): ?string
    {
        $value = strtolower(trim($input));
        if (preg_match('/\b(operator|offered|tilboed)\b/', $value)) {
            return 'operator_offered';
        }
        if (preg_match('/\b(self|bought|koebte|købte)\b/', $value)) {
            return 'self_bought';
        }
        if (preg_match('/\b(no solution|ingen|none|ikke)\b/', $value)) {
            return 'no_solution';
        }

        return in_array($value, ['operator_offered', 'self_bought', 'no_solution'], true) ? $value : null;
    }

    private function parseMinutes(string $input): ?int
    {
        if (!preg_match('/(\d{1,4})/', $input, $m)) {
            return null;
        }
        return max(0, (int)$m[1]);
    }

    /**
     * @return array{amount:string,currency:?string}|null
     */
    private function parseAmountInput(string $input): ?array
    {
        if (!preg_match('/(\d+(?:[.,]\d{1,2})?)/', $input, $m)) {
            return null;
        }

        $amount = (float)str_replace(',', '.', $m[1]);
        $currency = null;
        if (preg_match('/\b([A-Za-z]{3})\b/', strtoupper($input), $currencyMatch)) {
            $currency = strtoupper($currencyMatch[1]);
        } elseif (str_contains($input, '€')) {
            $currency = 'EUR';
        } elseif (str_contains($input, '£')) {
            $currency = 'GBP';
        } elseif (str_contains($input, '$')) {
            $currency = 'USD';
        }

        return [
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    private function defaultCurrencyForFlow(array $flow): string
    {
        $form = (array)($flow['form'] ?? []);
        $explicit = strtoupper(trim((string)($form['price_currency'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $explicit)) {
            return $explicit;
        }

        return match (strtoupper(trim((string)($form['operator_country'] ?? '')))) {
            'DK' => 'DKK',
            'SE' => 'SEK',
            'NO' => 'NOK',
            'CH' => 'CHF',
            'CZ' => 'CZK',
            'HU' => 'HUF',
            'PL' => 'PLN',
            'RO' => 'RON',
            'BG' => 'BGN',
            'GB' => 'GBP',
            default => 'EUR',
        };
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
            'operator_product' => 'Produkt gemt: ' . (string)($flow['form']['operator_product'] ?? ''),
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
            'meal_self_paid_amount' => 'Maaltidsbeloeb gemt: ' . (string)($flow['form']['meal_self_paid_amount'] ?? '') . ' ' . (string)($flow['form']['meal_self_paid_currency'] ?? ''),
            'hotel_self_paid_amount' => 'Hotelbeloeb gemt: ' . (string)($flow['form']['hotel_self_paid_amount'] ?? '') . ' ' . (string)($flow['form']['hotel_self_paid_currency'] ?? ''),
            'a20_3_self_paid_amount' => 'Alternativ transport-beloeb gemt: ' . (string)($flow['form']['a20_3_self_paid_amount'] ?? '') . ' ' . (string)($flow['form']['a20_3_self_paid_currency'] ?? ''),
            'remedy_choice' => 'Art. 18-retning gemt.',
            'refund_requested' => 'Refusionsvalg gemt.',
            'return_to_origin_expense' => 'Tilbage-til-udgangspunkt udgift gemt.',
            'return_to_origin_amount' => 'Tilbage-til-udgangspunkt beloeb gemt: ' . (string)($flow['form']['return_to_origin_amount'] ?? '') . ' ' . (string)($flow['form']['return_to_origin_currency'] ?? ''),
            'reroute_same_conditions_soonest' => 'Omlaegning snarest-oplysning gemt.',
            'reroute_later_at_choice' => 'Omlaegning senere-oplysning gemt.',
            'reroute_later_outcome' => 'Senere omlaegningsudfald gemt.',
            'reroute_later_self_paid_amount' => 'Senere omlaegningsbeloeb gemt: ' . (string)($flow['form']['reroute_later_self_paid_amount'] ?? '') . ' ' . (string)($flow['form']['reroute_later_self_paid_currency'] ?? ''),
            'reroute_info_within_100min' => '100-minutters-oplysning gemt.',
            'reroute_extra_costs' => 'Ekstra omlaegningsomkostninger gemt.',
            'reroute_extra_costs_amount' => 'Ekstra omlaegningsbeloeb gemt: ' . (string)($flow['form']['reroute_extra_costs_amount'] ?? '') . ' ' . (string)($flow['form']['reroute_extra_costs_currency'] ?? ''),
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
