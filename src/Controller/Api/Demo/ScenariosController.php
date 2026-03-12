<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\FixtureRepository;
use App\Service\ScenarioRunner;

class ScenariosController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
        // Make demo outputs readable (preserve Danish characters and punctuation)
        $this->viewBuilder()->setOption('jsonOptions', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/demo/scenarios
     * ?withEval=1 to run pipeline
     * ?id=<fixtureId> to filter one
     */
    public function index(): void
    {
        // Support both withEval=1 and eval=1 (alias)
        $withEval = (string)$this->request->getQuery('withEval') === '1'
            || (string)$this->request->getQuery('eval') === '1';
        $id = (string)$this->request->getQuery('id') ?: null;
        $compact = (string)$this->request->getQuery('compact') === '1';
        // Include the full wizard payload (step 1..N) in the output. Useful for admin/debug/operator handoff.
        $withWizard = (string)$this->request->getQuery('withWizard') === '1'
            || (string)$this->request->getQuery('wizard') === '1';
        // Provide a readable "operator-facing" case summary string (for EU claim free-text / admin).
        $operatorText = (string)$this->request->getQuery('operatorText') === '1'
            || (string)$this->request->getQuery('operator') === '1';
        // Optional: include where wizard fields originated (which TRIN/step set them).
        // Useful for debugging "station" fields across Art.20(3) vs Art.18 flows.
        $withProvenance = (string)$this->request->getQuery('provenance') === '1'
            || (string)$this->request->getQuery('withProvenance') === '1'
            || (string)$this->request->getQuery('debug') === '1';
        // Optional: include structured human-readable summaries for key articles (for admin/operator review).
        $withExplain = (string)$this->request->getQuery('explain') === '1'
            || (string)$this->request->getQuery('withExplain') === '1';

        $repo = new FixtureRepository();
        $fixtures = $repo->getAll($id);
        $runner = new ScenarioRunner();

        $out = [];
        foreach ($fixtures as $fx) {
            $row = [
                'id' => $fx['id'],
                'version' => $fx['version'] ?? 1,
                'label' => $fx['label'] ?? $fx['id'],
                'tags' => $fx['tags'] ?? [],
                'transport_mode' => $fx['transport_mode'] ?? 'rail',
                'contract_meta' => $fx['contract_meta'] ?? null,
                'scope_meta' => $fx['scope_meta'] ?? null,
                'incident_meta' => $fx['incident_meta'] ?? null,
                'expected' => $fx['expected'] ?? null,
            ];
            if ($withEval) {
                $eval = $runner->evaluateFixture($fx);
                $actual = $eval['actual'];
                $origin = $withProvenance ? $this->buildWizardFieldOrigin($fx) : null;
                if ($compact) {
                    $row['wizard_compact'] = $this->buildWizardCompact($fx);
                    $row['computeOverrides_compact'] = $this->buildComputeOverridesCompact($fx);
                    $actual = $this->buildActualCompact($actual) + ['_compact' => true];
                }
                if ($withWizard) {
                    $row['wizard'] = (array)($fx['wizard'] ?? []);
                    $row['computeOverrides'] = (array)($fx['computeOverrides'] ?? []);
                    $row['journeyBasic'] = (array)($fx['journeyBasic'] ?? []);
                    $row['segments'] = (array)($fx['segments'] ?? []);
                }
                if ($operatorText) {
                    $row['operator_case_text'] = $this->buildOperatorCaseText($fx, $compact ? [] : $actual);
                }
                if ($withExplain) {
                    $row['explain'] = [
                        'art18' => $this->buildArt18Explain($fx),
                        'note' => 'Explain blocks are derived from wizard inputs + known timing rules. They are a summary for admin/operator use (not legal advice).',
                    ];
                }
                if ($withProvenance && is_array($origin)) {
                    $row['wizard_field_origin'] = $origin;
                    // Attach compact per-module hints when available (keep it lightweight).
                    if (isset($actual['art20_assistance']['hooks']) && is_array($actual['art20_assistance']['hooks'])) {
                        $actual['art20_assistance']['hooks']['_origin_step'] = $this->pickOriginForFields($origin, (array)$actual['art20_assistance']['hooks']);
                    }
                    if (isset($actual['art12']['hooks']) && is_array($actual['art12']['hooks'])) {
                        $actual['art12']['hooks']['_origin_step'] = $this->pickOriginForFields($origin, (array)$actual['art12']['hooks']);
                    }
                    $actual['_origin_note'] = 'wizard_field_origin maps form keys to the TRIN/step where they are set. Actual modules may reuse the same key.';
                }
                $row['actual'] = $actual;
                $row['match'] = $eval['match'];
                $row['diff'] = $eval['diff'];
            }
            $out[] = $row;
        }

        $this->set(['scenarios' => $out]);
        $this->viewBuilder()->setOption('serialize', ['scenarios']);
    }

    /**
     * Compact wizard fields for TRIN 1-9 (high-signal only).
     *
     * @return array<string,array<string,mixed>>
     */
    private function buildWizardCompact(array $fx): array
    {
        $w = (array)($fx['wizard'] ?? []);

        $step1 = (array)($w['step1_start'] ?? []);
        $step2 = (array)($w['step2_entitlements'] ?? []);
        // New split flow: step3_station -> step4_journey. Keep back-compat for older fixtures (step3_journey + step4_station).
        $step3Station = (array)($w['step3_station'] ?? ($w['step4_station'] ?? []));
        $step4Journey = (array)($w['step4_journey'] ?? ($w['step3_journey'] ?? []));
        // Back-compat: older fixtures stored station-flow inside step4_incident before a dedicated station step existed.
        if (empty($step3Station) && !empty($w['step4_incident']) && is_array($w['step4_incident'])) {
            $legacy4 = (array)$w['step4_incident'];
            if (array_key_exists('a20_station_stranded', $legacy4) || array_key_exists('stranded_current_station', $legacy4)) {
                $step3Station = $legacy4;
            }
        }
        $step5 = (array)($w['step5_incident'] ?? ($w['step4_incident'] ?? []));
        $step6 = (array)($w['step6_choices'] ?? ($w['step5_choices'] ?? [])); // transport/stranded (split flow)
        $step7 = (array)($w['step7_remedies'] ?? ($w['step6_remedies'] ?? []));
        $step8 = (array)($w['step8_assistance'] ?? ($w['step7_assistance'] ?? ($w['step6_assistance'] ?? [])));
        $step9 = (array)($w['step9_downgrade'] ?? ($w['step8_downgrade'] ?? []));
        $step10 = (array)($w['step10_compensation'] ?? ($w['step9_compensation'] ?? ($w['step7_compensation'] ?? [])));

        return [
            'step1_start' => $this->pick($step1, [
                'travel_state',
                'eu_only',
            ]),
            'step2_entitlements' => $this->pick($step2, [
                'operator',
                'operator_country',
                'operator_product',
                'dep_date',
                'dep_time',
                'dep_station',
                'arr_station',
                'arr_time',
                'train_no',
                'price',
            ]),
            'step3_station' => $this->pick($step3Station, [
                'a20_station_stranded',
                'is_stranded',
                'stranded_location',
                'stranded_current_station',
                'stranded_current_station_other',
                'a20_3_solution_offered',
                'a20_3_solution_type',
                'a20_3_self_paid',
                'a20_3_self_arranged_type',
                'a20_3_self_paid_direction',
                'a20_3_self_paid_amount',
                'a20_3_self_paid_currency',
                'a20_3_self_paid_receipt',
                'a20_where_ended',
                'a20_arrival_station',
                'a20_arrival_station_other',
                'a20_where_ended_assumed',
                'handoff_station',
            ]),
            'step4_journey' => $this->pick($step4Journey, [
                'pmr_user',
                'pmr_booked',
                'pmr_delivered_status',
                'pmr_promised_missing',
                'bike_was_present',
                'bike_denied_boarding',
                'bike_refusal_reason_provided',
                'bike_refusal_reason_type',
                'fare_class_purchased',
                'berth_seat_type',
            ]),
            'step5_incident' => $this->pick($step5, [
                'incident_main',
                'incident_missed',
                'expected_delay_60',
                'delay_already_60',
                'missed_expected_delay_60',
                'national_delay_minutes',
                'national_delay_reported_at',
                'preinformed_disruption',
                'preinfo_channel',
                'realtime_info_seen',
                'missed_connection_station',
                'missed_connection_pick',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
            ]),
            'step6_choices' => $this->pick($step6, [
                'is_stranded_trin5',
                'maps_opt_in_trin5',
                'stranded_location',
                // Track (Art.20(2c))
                'blocked_train_alt_transport',
                'assistance_alt_transport_type',
                'blocked_no_transport_action',
                'blocked_self_paid_transport_type',
                'blocked_self_paid_amount',
                'blocked_self_paid_currency',
                'blocked_self_paid_receipt',
                // TRIN 5 resolution endpoint ("Hvor endte du?")
                'a20_where_ended',
                'a20_arrival_station',
                'a20_arrival_station_other',
                'a20_where_ended_assumed',
                'handoff_station',
            ]),
            'step7_remedies' => $this->pick($step7, [
                'art18_expected_delay_60',
                'remedyChoice',
                // TRIN 6 station context (separate from TRIN 5)
                'a18_from_station',
                'a18_from_station_other',
                // TRIN 6 refund context
                'a18_return_to_station',
                'a18_return_to_station_other',
                'a18_reroute_mode',
                'a18_reroute_endpoint',
                'a18_reroute_arrival_station',
                'a18_reroute_arrival_station_other',
                'reroute_info_within_100min',
                'reroute_same_conditions_soonest',
                'reroute_later_at_choice',
                'self_purchased_new_ticket',
                'self_purchase_approved_by_operator',
                'reroute_extra_costs',
                'reroute_extra_costs_amount',
                'reroute_extra_costs_currency',
                'reroute_later_ticket_upload',
                'reroute_later_ticket_file',
                'trip_cancelled_return_to_origin',
                'return_to_origin_expense',
                'return_to_origin_amount',
                'return_to_origin_currency',
            ]),
            'step8_assistance' => $this->pick($step8, [
                'art20_expected_delay_60',
                'meal_offered',
                'assistance_meals_unavailable_reason',
                'meal_self_paid_amount',
                'meal_self_paid_currency',
                'meal_self_paid_receipt',
                'hotel_offered',
                'assistance_hotel_transport_included',
                'hotel_transport_self_paid_amount',
                'hotel_transport_self_paid_currency',
                'hotel_transport_self_paid_receipt',
                'overnight_needed',
                'hotel_self_paid_nights',
                'hotel_self_paid_amount',
                'hotel_self_paid_currency',
                'hotel_self_paid_receipt',
                'assistance_pmr_priority_applied',
                'assistance_pmr_companion_supported',
                'price_hints',
            ]),
            'step9_downgrade' => $this->pick($step9, [
                'downgrade_ticket_file',
                'downgrade_occurred',
                'downgrade_comp_basis',
                'downgrade_segment_share',
                'leg_class_purchased',
                'leg_class_delivered',
                'leg_reservation_purchased',
                'leg_reservation_delivered',
                // Useful for QA/scenarios; otherwise it will be recomputed client-side only.
                'leg_downgraded',
            ]),
            'step10_compensation' => $this->pick($step10, [
                'delayAtFinalMinutes',
                'compensationBand',
                'delayMinEU',
                'knownDelayBeforePurchase',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
            ]),
        ];
    }

    /**
     * Build a readable summary of wizard inputs intended for the operator/admin.
     * This is deliberately redundant and uses wizard values (not pipeline inference) so it matches user answers.
     */
    private function buildOperatorCaseText(array $fx, array $actual): string
    {
        $w = (array)($fx['wizard'] ?? []);
        $jb = (array)($fx['journeyBasic'] ?? []);
        // New split flow: step3_station -> step4_journey. Keep back-compat for older fixtures.
        $s3Station = (array)($w['step3_station'] ?? ($w['step4_station'] ?? []));
        $s4Journey = (array)($w['step4_journey'] ?? ($w['step3_journey'] ?? []));
        $s5 = (array)($w['step5_incident'] ?? []);
        $s6 = (array)($w['step6_choices'] ?? []);
        $s7 = (array)($w['step7_remedies'] ?? []);
        $s8 = (array)($w['step8_assistance'] ?? ($w['step6_assistance'] ?? []));
        $s9 = (array)($w['step9_downgrade'] ?? []);
        $s10 = (array)($w['step10_compensation'] ?? ($w['step7_compensation'] ?? []));

        $lines = [];
        $lines[] = 'Sagsresume (wizard input)';
        if (!empty($jb)) {
            $lines[] = 'Rejse: ' . trim(sprintf(
                '%s %s %s %s → %s',
                (string)($jb['operator'] ?? ''),
                (string)($jb['operator_product'] ?? ''),
                (string)($jb['dep_date'] ?? ''),
                (string)($jb['dep_station'] ?? ''),
                (string)($jb['arr_station'] ?? '')
            ));
            if (!empty($jb['ticket_no'])) { $lines[] = 'Bookingref: ' . (string)$jb['ticket_no']; }
            if (!empty($jb['price'])) { $lines[] = 'Pris: ' . (string)$jb['price']; }
        }

        // PMR/cykel (step 4) – important context for Art.9 rights and potential Art.18/20 activation
        if (!empty($s4Journey)) {
            $pmrUser = (string)($s4Journey['pmr_user'] ?? '');
            $pmrBooked = (string)($s4Journey['pmr_booked'] ?? '');
            $pmrDelivered = (string)($s4Journey['pmr_delivered_status'] ?? '');
            $pmrMissing = (string)($s4Journey['pmr_promised_missing'] ?? '');
            $pmrDetails = trim((string)($s4Journey['pmr_facility_details'] ?? ''));

            $hasPmrSignal = in_array($pmrUser, ['Ja', 'Nej'], true)
                || in_array($pmrBooked, ['Ja', 'Nej'], true)
                || in_array($pmrDelivered, ['Ja', 'Nej'], true)
                || in_array($pmrMissing, ['Ja', 'Nej'], true);

            if ($hasPmrSignal) {
                $lines[] = 'PMR/handicap: bruger=' . ($pmrUser !== '' ? $pmrUser : 'unknown')
                    . ', assistance bestilt=' . ($pmrBooked !== '' ? $pmrBooked : 'unknown')
                    . ', leveret=' . ($pmrDelivered !== '' ? $pmrDelivered : 'unknown')
                    . ', lovet faciliteter manglede=' . ($pmrMissing !== '' ? $pmrMissing : 'unknown');
                if ($pmrDetails !== '') {
                    $lines[] = 'PMR detaljer: ' . $pmrDetails;
                }
            }

            $bikePresent = (string)($s4Journey['bike_was_present'] ?? '');
            $bikeDenied = (string)($s4Journey['bike_denied_boarding'] ?? '');
            $bikeReasonProvided = (string)($s4Journey['bike_refusal_reason_provided'] ?? '');
            $bikeReasonType = trim((string)($s4Journey['bike_refusal_reason_type'] ?? ''));
            $bikeResReq = (string)($s4Journey['bike_reservation_required'] ?? '');
            $bikeResMade = (string)($s4Journey['bike_reservation_made'] ?? '');

            $hasBikeSignal = in_array($bikePresent, ['Ja', 'Nej'], true)
                || in_array($bikeDenied, ['Ja', 'Nej'], true)
                || in_array($bikeResReq, ['Ja', 'Nej'], true)
                || in_array($bikeResMade, ['Ja', 'Nej'], true);

            if ($hasBikeSignal) {
                $line = 'Cykel: medbragt=' . ($bikePresent !== '' ? $bikePresent : 'unknown')
                    . ', reservation krævet=' . ($bikeResReq !== '' ? $bikeResReq : 'unknown')
                    . ', reservation lavet=' . ($bikeResMade !== '' ? $bikeResMade : 'unknown')
                    . ', afvist ombord=' . ($bikeDenied !== '' ? $bikeDenied : 'unknown');
                if ($bikeReasonProvided !== '' || $bikeReasonType !== '') {
                    $line .= ', begrundelse oplyst=' . ($bikeReasonProvided !== '' ? $bikeReasonProvided : 'unknown')
                        . ($bikeReasonType !== '' ? ' (' . $bikeReasonType . ')' : '');
                }
                $lines[] = $line;
            }
        }

        // Art.20(3) station flow (step 3)
        if (!empty($s3Station)) {
            $stranded = (string)($s3Station['a20_station_stranded'] ?? '');
            if ($stranded !== '') {
                $lines[] = 'Art.20(3) strandet på station: ' . $stranded;
                if (!empty($s3Station['stranded_current_station'])) {
                    $lines[] = 'Strandings-station: ' . (string)$s3Station['stranded_current_station'];
                }
                if (!empty($s3Station['a20_3_solution_offered'])) {
                    $lines[] = 'Tilbudt løsning: ' . (string)$s3Station['a20_3_solution_offered']
                        . (!empty($s3Station['a20_3_solution_type']) ? ' (' . (string)$s3Station['a20_3_solution_type'] . ')' : '');
                }
                if (!empty($s3Station['a20_where_ended'])) {
                    $end = (string)$s3Station['a20_where_ended'];
                    $lines[] = 'Hvor endte du (Art.20): ' . $end
                        . (!empty($s3Station['a20_arrival_station']) ? ' → ' . (string)$s3Station['a20_arrival_station'] : '');
                }
            }
        }

        // Incident/gating (step 5)
        if (!empty($s5)) {
            $lines[] = 'Hændelse: ' . (string)($s5['incident_main'] ?? '');
            if (!empty($s5['incident_missed'])) { $lines[] = 'Mistet forbindelse: ' . (string)$s5['incident_missed']; }
            if (!empty($s5['expected_delay_60'])) { $lines[] = 'Varslet ≥60 min: ' . (string)$s5['expected_delay_60']; }
            if (!empty($s5['delay_already_60'])) { $lines[] = 'Allerede ≥60 min: ' . (string)$s5['delay_already_60']; }
            if (!empty($s5['missed_expected_delay_60'])) { $lines[] = 'Missed → forventet ≥60 min: ' . (string)$s5['missed_expected_delay_60']; }
            if (array_key_exists('national_delay_minutes', $s5) && (string)($s5['national_delay_minutes'] ?? '') !== '') {
                $lines[] = 'National forsinkelse (min): ' . (string)$s5['national_delay_minutes'];
            }
            if (!empty($s5['operatorExceptionalCircumstances'])) {
                $lines[] = 'Force majeure (Art.19(10)): ' . (string)$s5['operatorExceptionalCircumstances']
                    . (!empty($s5['operatorExceptionalType']) ? ' (' . (string)$s5['operatorExceptionalType'] . ')' : '');
            }
        }

        // Art.20(2c) stuck / where ended (step 6)
        if (!empty($s6)) {
            if (!empty($s6['blocked_train_alt_transport'])) {
                $lines[] = 'Art.20(2)(c) stuck: alt transport stillet: ' . (string)$s6['blocked_train_alt_transport']
                    . (!empty($s6['assistance_alt_transport_type']) ? ' (' . (string)$s6['assistance_alt_transport_type'] . ')' : '');
            }
            if (!empty($s6['a20_where_ended'])) {
                $lines[] = 'Hvor endte du (Art.20): ' . (string)$s6['a20_where_ended']
                    . (!empty($s6['a20_arrival_station']) ? ' → ' . (string)$s6['a20_arrival_station'] : '');
            }
        }

        // Remedies (step 7)
        if (!empty($s7)) {
            if (!empty($s7['remedyChoice'])) { $lines[] = 'Art.18 valg: ' . (string)$s7['remedyChoice']; }
            if (!empty($s7['a18_from_station'])) { $lines[] = 'Station (Trin 7): ' . (string)$s7['a18_from_station']; }
            if (!empty($s7['a18_reroute_mode'])) { $lines[] = 'Omlægning: ' . (string)$s7['a18_reroute_mode']; }
            if (!empty($s7['a18_reroute_endpoint'])) { $lines[] = 'Hvor endte omlægningen: ' . (string)$s7['a18_reroute_endpoint']; }
            if (!empty($s7['a18_reroute_arrival_station'])) { $lines[] = 'Omlagt til station: ' . (string)$s7['a18_reroute_arrival_station']; }
            if (!empty($s7['reroute_info_within_100min'])) { $lines[] = 'Omlægning meddelt inden 100 min (Art.18(3)): ' . (string)$s7['reroute_info_within_100min']; }
            if (!empty($s7['journey_no_longer_purpose'])) { $lines[] = 'Rejsen tjente ikke længere et formål (Art.18(1)(a)): ' . (string)$s7['journey_no_longer_purpose']; }
        }

        // Assistance/expenses (step 8)
        if (!empty($s8)) {
            if (!empty($s8['meal_offered'])) { $lines[] = 'Måltider tilbudt: ' . (string)$s8['meal_offered']; }
            if (!empty($s8['hotel_offered'])) { $lines[] = 'Hotel tilbudt: ' . (string)$s8['hotel_offered']; }
        }

        // Downgrade (step 9)
        if (!empty($s9) && array_key_exists('downgrade_occurred', $s9)) {
            $lines[] = 'Nedgradering: ' . (string)($s9['downgrade_occurred'] ?? '');
        }

        // Compensation (step 10)
        if (!empty($s10)) {
            if (array_key_exists('delayAtFinalMinutes', $s10) && $s10['delayAtFinalMinutes'] !== null && $s10['delayAtFinalMinutes'] !== '') {
                $lines[] = 'Forsinkelse ved slutdestination (min): ' . (string)$s10['delayAtFinalMinutes'];
            }
            if (array_key_exists('compensationBand', $s10) && $s10['compensationBand'] !== null && $s10['compensationBand'] !== '') {
                $lines[] = 'Kompensationsbånd (valgt): ' . (string)$s10['compensationBand'];
            }
        }

        // If actual claim exists, add one compact line.
        if (isset($actual['claim']['totals']['gross_claim'])) {
            $lines[] = 'Beregnet (pipeline): brutto ' . (string)$actual['claim']['totals']['gross_claim'] . ' ' . (string)($actual['claim']['totals']['currency'] ?? '');
        }

        return implode("\n", array_values(array_filter($lines, fn($x) => trim((string)$x) !== '')));
    }

    /**
     * Structured Art.18 summary with short notes/conclusion.
     *
     * @return array<string,mixed>
     */
    private function buildArt18Explain(array $fx): array
    {
        $w = (array)($fx['wizard'] ?? []);
        $jb = (array)($fx['journeyBasic'] ?? []);
        // New split flow: step3_station -> step4_journey. Keep back-compat for older fixtures.
        $s3Station = (array)($w['step3_station'] ?? ($w['step4_station'] ?? []));
        $s4Journey = (array)($w['step4_journey'] ?? ($w['step3_journey'] ?? []));
        $s5 = (array)($w['step5_incident'] ?? []);
        $s7 = (array)($w['step7_remedies'] ?? []);
        $segments = (array)($fx['segments'] ?? []);

        $yn = static function ($v): string {
            $s = strtolower(trim((string)$v));
            return match ($s) {
                'ja','yes','y','true','1' => 'yes',
                'nej','no','n','false','0' => 'no',
                '' => 'unknown',
                default => $s,
            };
        };
        $normStation = static function (?string $s): string {
            $s = preg_replace('/\s+/u', ' ', trim((string)$s)) ?? trim((string)$s);
            if (function_exists('mb_strtolower')) { return mb_strtolower($s, 'UTF-8'); }
            return strtolower($s);
        };

        $incidentMain = (string)($s5['incident_main'] ?? '');
        $incidentMissed = $yn($s5['incident_missed'] ?? '');
        $expected60 = $yn($s5['expected_delay_60'] ?? '');
        $already60 = $yn($s5['delay_already_60'] ?? '');
        $missedExpected60 = $yn($s5['missed_expected_delay_60'] ?? '');
        $cancellation = ($incidentMain === 'cancellation');

        $pmrGate = ($yn($s4Journey['pmr_user'] ?? '') === 'yes') && (
            ($yn($s4Journey['pmr_booked'] ?? '') === 'yes' && $yn($s4Journey['pmr_delivered_status'] ?? '') === 'no')
            || ($yn($s4Journey['pmr_promised_missing'] ?? '') === 'yes')
        );
        $bikeGate = ($yn($s4Journey['bike_denied_boarding'] ?? '') === 'yes') && (
            $yn($s4Journey['bike_refusal_reason_provided'] ?? '') !== 'yes' || trim((string)($s4Journey['bike_refusal_reason_type'] ?? '')) === ''
        );

        $activeReasons = [];
        if ($cancellation) { $activeReasons[] = 'cancellation'; }
        if ($expected60 === 'yes' || $already60 === 'yes') { $activeReasons[] = 'delay>=60'; }
        if ($incidentMissed === 'yes') { $activeReasons[] = 'missed_connection'; }
        if ($pmrGate) { $activeReasons[] = 'pmr_gate'; }
        if ($bikeGate) { $activeReasons[] = 'bike_gate'; }

        $active = !empty($activeReasons);
        $remedy = (string)($s7['remedyChoice'] ?? '');

        $purpose = (string)($s7['journey_no_longer_purpose'] ?? '');
        $toDest = (string)($s3Station['a20_where_ended'] ?? '');
        $assumed = (string)($s3Station['a20_where_ended_assumed'] ?? '0');
        $arrivedFinalExplicit = ($toDest === 'final_destination' && $assumed !== '1');

        // Art.18(3) 100-min clock: compute based on planned departure (affected service or missed connection leg).
        $plannedDeparture = null;
        $deadline100 = null;
        $clockBasis = null;
        try {
            $baseDate = '';
            $baseTime = '';
            $baseFrom = '';
            $baseTo = '';
            $basis = 'scheduled_departure';

            if ($incidentMissed === 'yes') {
                $pick = (string)($s5['missed_connection_pick'] ?? ($s5['missed_connection_station'] ?? ''));
                if ($pick !== '' && !empty($segments)) {
                    $pickN = $normStation($pick);
                    for ($i = 0; $i < count($segments) - 1; $i++) {
                        $seg = (array)$segments[$i];
                        if ($pickN !== '' && $normStation((string)($seg['to'] ?? '')) === $pickN) {
                            $next = (array)$segments[$i + 1];
                            $baseDate = (string)($next['depDate'] ?? '');
                            $baseTime = (string)($next['schedDep'] ?? '');
                            $baseFrom = (string)($next['from'] ?? '');
                            $baseTo = (string)($next['to'] ?? '');
                            $basis = 'missed_connection';
                            break;
                        }
                    }
                    if ($baseTime === '') {
                        foreach ($segments as $seg0) {
                            $seg = (array)$seg0;
                            if ($pickN !== '' && $normStation((string)($seg['from'] ?? '')) === $pickN) {
                                $baseDate = (string)($seg['depDate'] ?? '');
                                $baseTime = (string)($seg['schedDep'] ?? '');
                                $baseFrom = (string)($seg['from'] ?? '');
                                $baseTo = (string)($seg['to'] ?? '');
                                $basis = 'missed_connection';
                                break;
                            }
                        }
                    }
                }
            }

            if ($baseTime === '') {
                $baseDate = (string)($jb['dep_date'] ?? '');
                $baseTime = (string)($jb['dep_time'] ?? '');
                if ($baseTime === '' && !empty($segments)) {
                    $seg0 = (array)$segments[0];
                    $baseDate = (string)($seg0['depDate'] ?? $baseDate);
                    $baseTime = (string)($seg0['schedDep'] ?? $baseTime);
                    $baseFrom = (string)($seg0['from'] ?? '');
                    $baseTo = (string)($seg0['to'] ?? '');
                }
            }

            if ($baseDate !== '' && $baseTime !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $baseDate) && preg_match('/^\\d{1,2}:\\d{2}$/', $baseTime)) {
                $dt = new \DateTimeImmutable($baseDate . ' ' . $baseTime);
                $plannedDeparture = $dt->format('Y-m-d H:i');
                $deadline100 = $dt->modify('+100 minutes')->format('Y-m-d H:i');
                $route = trim(($baseFrom !== '' ? $baseFrom : '') . ($baseTo !== '' ? ' → ' . $baseTo : ''));
                $clockBasis = $basis === 'missed_connection'
                    ? 'Planlagt afgang for den missede forbindelse' . ($route !== '' ? ' (' . $route . ')' : '')
                    : 'Planlagt afgang for den berørte tjeneste' . ($route !== '' ? ' (' . $route . ')' : '');
            }
        } catch (\Throwable $e) { /* ignore */ }

        $notes = [];
        if (!$active) {
            $notes[] = 'Art.18 er ikke tydeligt aktiveret ud fra wizard inputs (EU gate <60 og ingen cancellation/missed/PMR/cykel-signal).';
        }
        if ($incidentMissed === 'yes' && $missedExpected60 === 'no') {
            $notes[] = 'Missed connection er markeret, men forventet endelig forsinkelse er ikke ≥60 min. National ordning kan stadig være relevant.';
        }
        if ($arrivedFinalExplicit && $purpose !== '') {
            $notes[] = 'Endelig destination er nået via Art.20; Art.18-udfald afhænger af formål-spørgsmålet.';
        }

        $conclusion = [];
        if ($remedy !== '') { $conclusion[] = 'Valgt Art.18-vej: ' . $remedy . '.'; }
        $within100 = $yn($s7['reroute_info_within_100min'] ?? '');
        $selfHelpRight = ($within100 === 'no'); // No info within 100 minutes => user may self-arrange public transport (Art.18(3)).
        if ($within100 !== 'unknown') {
            $conclusion[] = 'Art.18(3) 100-min svar: ' . ($within100 === 'yes' ? 'yes' : 'no') . '.';
        } elseif ($plannedDeparture !== null && $deadline100 !== null) {
            $conclusion[] = 'Art.18(3) 100-min frist (beregnet): ' . $deadline100 . ' (fra ' . $plannedDeparture . ').';
        }
        if ($arrivedFinalExplicit && $purpose !== '') { $conclusion[] = 'Formål efter ankomst: ' . $purpose . '.'; }

        $effects = [];
        if ($selfHelpRight) {
            $effects[] = 'Art.18(3): selvhjælpsret kan være aktiveret (ingen meddelt omlægning inden 100 min).';
        }
        if ($arrivedFinalExplicit) {
            $effects[] = 'Art.20: slutpunkt=final_destination (explicit). Trin 7/Art.18 kan ofte skippes, men refund kan stadig være relevant afhængigt af formål-spørgsmålet.';
        }
        if ($arrivedFinalExplicit && $purpose !== '') {
            $p = strtolower(trim($purpose));
            if ($p === 'ja' || $p === 'yes') {
                $effects[] = 'Formål=ja: refusion/returtransport kan stadig være relevant selvom du nåede slutdestination.';
            } elseif ($p === 'nej' || $p === 'no') {
                $effects[] = 'Formål=nej: Art.18-refusion pga. “ikke længere noget formål” ser ikke ud til at være valgt.';
            }
        }

        return [
            'active' => $active,
            'active_reasons' => $activeReasons,
            'incident_main' => $incidentMain,
            'remedyChoice' => $remedy,
            'reroute' => [
                'from_station' => $s7['a18_from_station'] ?? null,
                'mode' => $s7['a18_reroute_mode'] ?? null,
                'endpoint' => $s7['a18_reroute_endpoint'] ?? null,
                'arrival_station' => $s7['a18_reroute_arrival_station'] ?? null,
            ],
            'refund' => [
                'return_to_station' => $s7['a18_return_to_station'] ?? null,
                'journey_no_longer_purpose' => $purpose !== '' ? $purpose : null,
                'arrived_final_destination_explicit' => $arrivedFinalExplicit,
            ],
            'art18_3_100min' => [
                'planned_departure' => $plannedDeparture,
                'deadline_100min' => $deadline100,
                'basis' => $clockBasis,
                'answer' => $s7['reroute_info_within_100min'] ?? null,
                'self_help_right' => $selfHelpRight,
            ],
            'effects' => $effects,
            'notes' => $notes,
            'conclusion' => $conclusion,
        ];
    }

    /**
     * Compact computeOverrides (high-signal only).
     *
     * @return array<string,mixed>
     */
    private function buildComputeOverridesCompact(array $fx): array
    {
        $c = (array)($fx['computeOverrides'] ?? []);
        return $this->pick($c, [
            'euOnly',
            'delayMinEU',
            'delayAtFinalMinutes',
            'knownDelayBeforePurchase',
            'refundAlready',
            'throughTicket',
            'returnTicket',
            'legPrice',
            'extraordinary',
            'selfInflicted',
            'art18Option',
        ]);
    }

    /**
     * Compact actual pipeline output (high-signal only).
     *
     * @return array<string,mixed>
     */
    private function buildActualCompact(array $actual): array
    {
        $out = [];

        // Profile + exemptions (hooks panel mirrors these)
        $out['profile'] = $this->pick((array)($actual['profile'] ?? []), [
            'scope',
            'eu_only_recommended',
            'articles',
            'articles_sub',
            'ui_banners',
        ]);

        // Art 12 / Art 9 headlines (keep hooks + missing only)
        $out['art12'] = $this->pick((array)($actual['art12'] ?? []), [
            'art12_applies',
            'hooks',
            'missing',
            'issues',
            'recommendations',
        ]);
        $out['art9'] = $this->pick((array)($actual['art9'] ?? []), [
            'hooks',
            'missing',
            'issues',
            'recommendations',
        ]);

        // TRIN 6-9: Claim output is the canonical summary used in TRIN 9.
        $out['claim'] = $this->pick((array)($actual['claim'] ?? []), [
            'breakdown',
            'totals',
            'flags',
        ]);

        // Keep raw modules that feed TRIN screens (small, but useful for debugging)
        $out['refund'] = $actual['refund'] ?? null;
        $out['refusion'] = $actual['refusion'] ?? null;

        // Assistance + downgrade evaluators (diagnostic)
        $a20 = (array)($actual['art20_assistance'] ?? []);
        if (!empty($a20)) {
            $a20Out = $this->pick($a20, ['compliance_status','missing','issues','recommendations']);
            $hooks = (array)($a20['hooks'] ?? []);
            if (!empty($hooks)) {
                $a20Out['hooks'] = $this->pick($hooks, [
                    '_active',
                    'art20_expected_delay_60',
                    'stranded_location',
                    // Station context (Art.20(3) + resolution endpoint)
                    'stranded_current_station',
                    'a20_where_ended',
                    'a20_arrival_station',
                    'handoff_station',
                    'blocked_train_alt_transport',
                     'alt_transport_provided',
                     'a20_3_solution_offered',
                     'a20_3_solution_type',
                     'a20_3_self_paid',
                     'a20_3_self_arranged_type',
                     'a20_3_self_paid_amount',
                     'a20_3_self_paid_currency',
                    'meal_offered',
                    'meal_self_paid_amount',
                    'meal_self_paid_currency',
                    'hotel_offered',
                    'overnight_needed',
                    'hotel_self_paid_amount',
                    'hotel_self_paid_currency',
                    'hotel_self_paid_nights',
                ]);
            }
            $out['art20_assistance'] = $a20Out;
        }

        $dw = (array)($actual['downgrade'] ?? []);
        if (!empty($dw)) {
            $out['downgrade'] = $this->pick($dw, [
                'compliance_status',
                'missing',
                'operator',
                'results',
            ]);
            if (isset($out['downgrade']['results']) && is_array($out['downgrade']['results'])) {
                $out['downgrade']['results'] = array_map(static function($r) {
                    $r = (array)$r;
                    $keep = [];
                    foreach (['legIndex','segment','downgraded','refund','missing'] as $k) {
                        if (array_key_exists($k, $r)) { $keep[$k] = $r[$k]; }
                    }
                    return $keep;
                }, (array)$out['downgrade']['results']);
            }
        }

        // Remove null-only top-level keys for readability.
        foreach ($out as $k => $v) {
            if ($v === null) { unset($out[$k]); }
        }

        return $out;
    }

    /**
     * Pick keys that exist and are not null (keep false/0).
     *
     * @param array<string,mixed> $src
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    private function pick(array $src, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $src)) { continue; }
            $v = $src[$k];
            if ($v === null) { continue; }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Build a compact "field -> step" provenance map from the fixture wizard sections.
     *
     * @return array<string,string|array<int,string>>
     */
    private function buildWizardFieldOrigin(array $fx): array
    {
        $w = (array)($fx['wizard'] ?? []);
        // New split flow: step3_station -> step4_journey. Keep back-compat for older fixtures.
        $w['step3_station'] = (array)($w['step3_station'] ?? ($w['step4_station'] ?? []));
        $w['step4_journey'] = (array)($w['step4_journey'] ?? ($w['step3_journey'] ?? []));
        $steps = [
            'step1_start',
            'step2_entitlements',
            'step3_station',
            'step4_journey',
            'step5_incident',
            'step6_choices',
            'step7_remedies',
            'step8_assistance',
            'step9_downgrade',
            'step10_compensation',
        ];

        $origin = [];
        foreach ($steps as $step) {
            $payload = (array)($w[$step] ?? []);
            foreach (array_keys($payload) as $k) {
                if ($k === '') { continue; }
                if (!array_key_exists($k, $origin)) {
                    $origin[$k] = $step;
                    continue;
                }
                // Same form key can appear in multiple wizard steps across versions; keep both.
                $prev = $origin[$k];
                if (is_string($prev)) { $prev = [$prev]; }
                if (is_array($prev) && !in_array($step, $prev, true)) { $prev[] = $step; }
                $origin[$k] = $prev;
            }
        }

        ksort($origin, SORT_NATURAL | SORT_FLAG_CASE);
        return $origin;
    }

    /**
     * Pick provenance entries for a set of fields (same key names).
     *
     * @param array<string,string|array<int,string>> $origin
     * @param array<string,mixed> $fields
     * @return array<string,string|array<int,string>>
     */
    private function pickOriginForFields(array $origin, array $fields): array
    {
        $out = [];
        foreach (array_keys($fields) as $k) {
            if ($k === '_origin_step') { continue; }
            if (array_key_exists($k, $origin)) { $out[$k] = $origin[$k]; }
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
