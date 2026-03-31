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
                    $row['canonical'] = (array)($fx['canonical'] ?? []);
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
        $transportMode = $this->normalizeTransportMode((string)($fx['transport_mode'] ?? 'rail'));

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
                '_mode',
                '_mode_fields',
                'pmr_user',
                'pmr_companion',
                'pmr_service_dog',
                'unaccompanied_minor',
                'ferry_pmr_companion',
                'ferry_pmr_service_dog',
                'ferry_pmr_notice_48h',
                'ferry_pmr_met_checkin_time',
                'ferry_pmr_assistance_delivered',
                'ferry_pmr_boarding_refused',
                'ferry_pmr_refusal_basis',
                'ferry_pmr_reason_given',
                'ferry_pmr_alternative_transport_offered',
                'bus_pmr_companion',
                'bus_pmr_notice_36h',
                'bus_pmr_met_terminal_time',
                'bus_pmr_special_seating_notified',
                'bus_pmr_assistance_delivered',
                'bus_pmr_boarding_refused',
                'bus_pmr_refusal_basis',
                'bus_pmr_reason_given',
                'bus_pmr_alternative_transport_offered',
                'pmr_booked',
                'pmr_delivered_status',
                'pmr_promised_missing',
                'bike_was_present',
                'bike_denied_boarding',
                'bike_refusal_reason_provided',
                'bike_refusal_reason_type',
                'fare_class_purchased',
                'berth_seat_type',
            ]) + [
                '_mode' => (string)($step4Journey['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step4Journey['_mode_fields'] ?? []),
            ],
            'step5_incident' => $this->pick($step5, [
                '_mode',
                '_mode_fields',
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
                'ferry_art16_notice_within_30min',
                'vehicle_breakdown',
                'delay_departure_band',
                'delay_minutes_departure',
                'planned_duration_band',
                'missed_connection_due_to_delay',
                'overbooking',
                'severe_weather',
                'major_natural_disaster',
                'bus_incident_main',
                'bus_delay_departure_band',
                'bus_delay_minutes_departure',
                'bus_planned_duration_band',
                'bus_vehicle_breakdown',
                'bus_missed_connection_due_to_delay',
                'bus_overbooking',
                'bus_severe_weather',
                'bus_major_natural_disaster',
            ]) + [
                '_mode' => (string)($step5['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step5['_mode_fields'] ?? []),
            ],
            'step6_choices' => $this->pick($step6, [
                '_mode',
                '_mode_fields',
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
            ]) + [
                '_mode' => (string)($step6['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step6['_mode_fields'] ?? []),
            ],
            'step7_remedies' => $this->pick($step7, [
                '_mode',
                '_mode_fields',
                'art18_expected_delay_60',
                'remedyChoice',
                'rail_remedy_choice',
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
                'rail_reroute_info_within_100min',
                'reroute_same_conditions_soonest',
                'reroute_later_at_choice',
                'air_article8_choice_offered',
                'ferry_offer_provided',
                'rail_offer_provided',
                'carrier_offered_choice',
                'self_purchased_new_ticket',
                'rail_self_arranged_solution',
                'air_self_arranged_reroute',
                'ferry_self_arranged_solution',
                'ferry_self_arranged_solution_type',
                'self_purchase_approved_by_operator',
                'rail_operator_confirmed_self_arranged_solution',
                'air_airline_confirmed_self_arranged_solution',
                'self_purchase_reason',
                'rail_self_arranged_reason',
                'air_self_arranged_reroute_reason',
                'ferry_self_arranged_reason',
                'offer_provided',
                'air_refund_scope',
                'air_return_to_first_departure_point',
                'air_alternative_airport_transfer_needed',
                'air_alternative_airport_transfer_amount',
                'air_alternative_airport_transfer_currency',
                'ferry_first_usable_solution_timing',
                'reroute_extra_costs',
                'reroute_extra_costs_amount',
                'reroute_extra_costs_currency',
                'reroute_later_ticket_upload',
                'reroute_later_ticket_file',
                'trip_cancelled_return_to_origin',
                'return_to_origin_expense',
                'return_to_origin_amount',
                'return_to_origin_currency',
                'rail_return_to_origin_expense',
                'rail_return_to_origin_amount',
                'rail_return_to_origin_currency',
            ]) + [
                '_mode' => (string)($step7['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step7['_mode_fields'] ?? []),
            ],
            'step8_assistance' => $this->pick($step8, [
                '_mode',
                '_mode_fields',
                'art20_expected_delay_60',
                'meal_offered',
                'rail_refreshments_offered',
                'assistance_meals_unavailable_reason',
                'meal_self_paid_amount',
                'meal_self_paid_currency',
                'meal_self_paid_receipt',
                'rail_refreshments_self_paid_amount',
                'rail_refreshments_self_paid_currency',
                'hotel_offered',
                'rail_hotel_offered',
                'assistance_hotel_transport_included',
                'rail_hotel_transport_included',
                'hotel_transport_self_paid_amount',
                'hotel_transport_self_paid_currency',
                'hotel_transport_self_paid_receipt',
                'overnight_needed',
                'rail_overnight_required',
                'hotel_self_paid_nights',
                'hotel_self_paid_amount',
                'hotel_self_paid_currency',
                'hotel_self_paid_receipt',
                'rail_hotel_self_paid_amount',
                'rail_hotel_self_paid_currency',
                'rail_hotel_self_paid_nights',
                'assistance_pmr_priority_applied',
                'assistance_pmr_companion_supported',
                'price_hints',
            ]) + [
                '_mode' => (string)($step8['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step8['_mode_fields'] ?? []),
            ],
            'step9_downgrade' => $this->pick($step9, [
                'downgrade_ticket_file',
                'downgrade_occurred',
                'downgrade_comp_basis',
                'downgrade_segment_share',
                'air_downgrade_booked_class',
                'air_downgrade_flown_class',
                'air_downgrade_refund_percent',
                'leg_class_purchased',
                'leg_class_delivered',
                'leg_reservation_purchased',
                'leg_reservation_delivered',
                // Useful for QA/scenarios; otherwise it will be recomputed client-side only.
                'leg_downgraded',
            ]),
            'step10_compensation' => $this->pick($step10, [
                '_mode',
                '_mode_fields',
                'result_preview',
                'delayAtFinalMinutes',
                'compensationBand',
                'rail_delay_at_final_minutes',
                'rail_compensation_band',
                'delayMinEU',
                'knownDelayBeforePurchase',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
                'scheduled_distance_km',
                'vehicle_breakdown',
                'hotel_transport_self_paid_amount',
                'hotel_transport_self_paid_currency',
                'bus_hotel_legal_cap_eur_per_night',
                'bus_hotel_legal_nights_max',
                'bus_meals_soft_cap_eur_per_delay_hour',
                'bus_hotel_transport_soft_cap_eur',
                'ferry_hotel_legal_cap_eur_per_night',
                'ferry_hotel_legal_nights_max',
                'ferry_meals_legal_rule',
                'ferry_hotel_transport_included',
                'ferry_refund_ticket_price_percent',
                'ferry_refund_deadline_days',
                'ferry_meals_soft_cap_eur_per_day',
                'ferry_local_transport_soft_cap_eur_per_trip',
                'ferry_local_transport_soft_cap_total_eur',
                'ferry_taxi_soft_cap_eur',
                'ferry_self_arranged_alt_transport_soft_cap_total_eur',
            ]) + [
                '_mode' => (string)($step10['_mode'] ?? $transportMode),
                '_mode_fields' => (array)($step10['_mode_fields'] ?? []),
            ],
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
        $transportMode = $this->normalizeTransportMode((string)($fx['transport_mode'] ?? 'rail'));
        $isFerry = $transportMode === 'ferry';
        $strandedIntro = $isFerry ? 'Art.20(3) strandet ved havn/terminal' : 'Art.20(3) strandet paa station';
        $strandedPlaceLabel = $isFerry ? 'Strandingshavn/-terminal' : 'Strandings-station';
        $step7PlaceLabel = $isFerry ? 'Havn/terminal (Trin 7)' : 'Station (Trin 7)';
        $rerouteArrivalLabel = $isFerry ? 'Omlagt til havn/terminal' : 'Omlagt til station';
        // New split flow: step3_station -> step4_journey. Keep back-compat for older fixtures.
        $s3Station = (array)($w['step3_station'] ?? ($w['step4_station'] ?? []));
        $s4Journey = (array)($w['step4_journey'] ?? ($w['step3_journey'] ?? []));
        $s5 = (array)($w['step5_incident'] ?? []);
        $s6 = (array)($w['step6_choices'] ?? []);
        $s7 = (array)($w['step7_remedies'] ?? []);
        $s8 = (array)($w['step8_assistance'] ?? ($w['step6_assistance'] ?? []));
        $s9 = (array)($w['step9_downgrade'] ?? []);
        $s10 = (array)($w['step10_compensation'] ?? ($w['step7_compensation'] ?? []));
        $s10ModeFields = (array)($s10['_mode_fields'] ?? []);
        $s10ResultPreview = (array)($s10['result_preview'] ?? ($s10ModeFields[$transportMode]['result_preview'] ?? []));
        $actualClaim = (array)($actual['claim'] ?? []);
        $actualBreakdown = (array)($actualClaim['breakdown'] ?? []);
        $actualTotals = (array)($actualClaim['totals'] ?? []);

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
            $pmrContext = !empty($s4Journey['pmr_user']) || !empty($s4Journey['pmr_booked']) || !empty($s4Journey['pmr_promised_missing'])
                ? $s4Journey
                : $s3Station;
            $pmrUser = (string)($pmrContext['pmr_user'] ?? '');
            $pmrCompanion = (string)($pmrContext['pmr_companion'] ?? '');
            $pmrServiceDog = (string)($pmrContext['pmr_service_dog'] ?? '');
            $unaccompaniedMinor = (string)($pmrContext['unaccompanied_minor'] ?? '');
            $pmrBooked = (string)($pmrContext['pmr_booked'] ?? '');
            $pmrDelivered = (string)($pmrContext['pmr_delivered_status'] ?? '');
            $pmrMissing = (string)($pmrContext['pmr_promised_missing'] ?? '');
            $pmrDetails = trim((string)($pmrContext['pmr_facility_details'] ?? ''));
            $ferryPmrCompanion = (string)($pmrContext['ferry_pmr_companion'] ?? '');
            $ferryPmrServiceDog = (string)($pmrContext['ferry_pmr_service_dog'] ?? '');
            $ferryPmrNotice48h = (string)($pmrContext['ferry_pmr_notice_48h'] ?? '');
            $ferryPmrMetCheckinTime = (string)($pmrContext['ferry_pmr_met_checkin_time'] ?? '');
            $ferryPmrDelivered = (string)($pmrContext['ferry_pmr_assistance_delivered'] ?? '');
            $ferryPmrBoardingRefused = (string)($pmrContext['ferry_pmr_boarding_refused'] ?? '');
            $ferryPmrRefusalBasis = (string)($pmrContext['ferry_pmr_refusal_basis'] ?? '');
            $ferryPmrReasonGiven = (string)($pmrContext['ferry_pmr_reason_given'] ?? '');
            $ferryPmrAltTransport = (string)($pmrContext['ferry_pmr_alternative_transport_offered'] ?? '');
            $busPmrCompanion = (string)($pmrContext['bus_pmr_companion'] ?? '');
            $busPmrNotice36h = (string)($pmrContext['bus_pmr_notice_36h'] ?? '');
            $busPmrMetTerminalTime = (string)($pmrContext['bus_pmr_met_terminal_time'] ?? '');
            $busPmrSpecialSeatingNotified = (string)($pmrContext['bus_pmr_special_seating_notified'] ?? '');
            $busPmrDelivered = (string)($pmrContext['bus_pmr_assistance_delivered'] ?? '');
            $busPmrBoardingRefused = (string)($pmrContext['bus_pmr_boarding_refused'] ?? '');
            $busPmrRefusalBasis = (string)($pmrContext['bus_pmr_refusal_basis'] ?? '');
            $busPmrReasonGiven = (string)($pmrContext['bus_pmr_reason_given'] ?? '');
            $busPmrAltTransport = (string)($pmrContext['bus_pmr_alternative_transport_offered'] ?? '');

            $hasPmrSignal = in_array($pmrUser, ['Ja', 'Nej'], true)
                || in_array($pmrCompanion, ['Ja', 'Nej'], true)
                || in_array($pmrServiceDog, ['Ja', 'Nej'], true)
                || in_array($unaccompaniedMinor, ['Ja', 'Nej'], true)
                || in_array($pmrBooked, ['Ja', 'Nej'], true)
                || in_array($pmrDelivered, ['Ja', 'Nej'], true)
                || in_array($pmrMissing, ['Ja', 'Nej'], true)
                || in_array($ferryPmrCompanion, ['Ja', 'Nej'], true)
                || in_array($ferryPmrServiceDog, ['Ja', 'Nej'], true)
                || in_array($ferryPmrNotice48h, ['Ja', 'Nej'], true)
                || in_array($ferryPmrMetCheckinTime, ['Ja', 'Nej'], true)
                || in_array($ferryPmrBoardingRefused, ['Ja', 'Nej'], true)
                || in_array($ferryPmrReasonGiven, ['Ja', 'Nej'], true)
                || in_array($busPmrCompanion, ['Ja', 'Nej'], true)
                || in_array($busPmrNotice36h, ['Ja', 'Nej'], true)
                || in_array($busPmrMetTerminalTime, ['Ja', 'Nej'], true)
                || in_array($busPmrSpecialSeatingNotified, ['Ja', 'Nej'], true)
                || in_array($busPmrBoardingRefused, ['Ja', 'Nej'], true)
                || in_array($busPmrReasonGiven, ['Ja', 'Nej'], true);

            if ($hasPmrSignal) {
                $lines[] = 'PMR/handicap: bruger=' . ($pmrUser !== '' ? $pmrUser : 'unknown')
                    . ', ledsager=' . ($pmrCompanion !== '' ? $pmrCompanion : 'unknown')
                    . ', servicehund=' . ($pmrServiceDog !== '' ? $pmrServiceDog : 'unknown')
                    . ', uledsaget barn=' . ($unaccompaniedMinor !== '' ? $unaccompaniedMinor : 'unknown')
                    . ', assistance bestilt=' . ($pmrBooked !== '' ? $pmrBooked : 'unknown')
                    . ', leveret=' . ($pmrDelivered !== '' ? $pmrDelivered : 'unknown')
                    . ', lovet faciliteter manglede=' . ($pmrMissing !== '' ? $pmrMissing : 'unknown');
                if ($pmrDetails !== '') {
                    $lines[] = 'PMR detaljer: ' . $pmrDetails;
                }
                if ($ferryPmrNotice48h !== '' || $ferryPmrDelivered !== '' || $ferryPmrBoardingRefused !== '') {
                    $lines[] = 'Faerge PMR: ledsager=' . ($ferryPmrCompanion !== '' ? $ferryPmrCompanion : 'unknown')
                        . ', servicehund=' . ($ferryPmrServiceDog !== '' ? $ferryPmrServiceDog : 'unknown')
                        . ', 48t-varsel=' . ($ferryPmrNotice48h !== '' ? $ferryPmrNotice48h : 'unknown')
                        . ', check-in-tid moedt=' . ($ferryPmrMetCheckinTime !== '' ? $ferryPmrMetCheckinTime : 'unknown')
                        . ', assistance=' . ($ferryPmrDelivered !== '' ? $ferryPmrDelivered : 'unknown')
                        . ', boarding naegtet=' . ($ferryPmrBoardingRefused !== '' ? $ferryPmrBoardingRefused : 'unknown')
                        . ', begrundelse=' . ($ferryPmrRefusalBasis !== '' ? $ferryPmrRefusalBasis : 'unknown')
                        . ', begrundelse givet=' . ($ferryPmrReasonGiven !== '' ? $ferryPmrReasonGiven : 'unknown')
                        . ', alternativ transport=' . ($ferryPmrAltTransport !== '' ? $ferryPmrAltTransport : 'unknown');
                }
                if ($busPmrNotice36h !== '' || $busPmrDelivered !== '' || $busPmrBoardingRefused !== '') {
                    $lines[] = 'Bus PMR: ledsager=' . ($busPmrCompanion !== '' ? $busPmrCompanion : 'unknown')
                        . ', 36t-varsel=' . ($busPmrNotice36h !== '' ? $busPmrNotice36h : 'unknown')
                        . ', terminaltid moedt=' . ($busPmrMetTerminalTime !== '' ? $busPmrMetTerminalTime : 'unknown')
                        . ', saerlige siddebehov oplyst=' . ($busPmrSpecialSeatingNotified !== '' ? $busPmrSpecialSeatingNotified : 'unknown')
                        . ', assistance=' . ($busPmrDelivered !== '' ? $busPmrDelivered : 'unknown')
                        . ', boarding naegtet=' . ($busPmrBoardingRefused !== '' ? $busPmrBoardingRefused : 'unknown')
                        . ', begrundelse=' . ($busPmrRefusalBasis !== '' ? $busPmrRefusalBasis : 'unknown')
                        . ', begrundelse givet=' . ($busPmrReasonGiven !== '' ? $busPmrReasonGiven : 'unknown')
                        . ', alternativ transport=' . ($busPmrAltTransport !== '' ? $busPmrAltTransport : 'unknown');
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
            if ($isFerry) {
                $stranded = (string)($s3Station['a20_station_stranded'] ?? '');
                if ($stranded !== '') {
                    $lines[] = $strandedIntro . ': ' . $stranded;
                    if (!empty($s3Station['stranded_current_station'])) {
                        $lines[] = $strandedPlaceLabel . ': ' . (string)$s3Station['stranded_current_station'];
                    }
                    if (!empty($s3Station['a20_3_solution_offered'])) {
                        $lines[] = 'Tilbudt loesning: ' . (string)$s3Station['a20_3_solution_offered']
                            . (!empty($s3Station['a20_3_solution_type']) ? ' (' . (string)$s3Station['a20_3_solution_type'] . ')' : '');
                    }
                    if (!empty($s3Station['a20_where_ended'])) {
                        $end = $this->formatResolutionEndpoint((string)$s3Station['a20_where_ended'], $transportMode);
                        $lines[] = 'Hvor endte du (Art.20): ' . $end
                            . (!empty($s3Station['a20_arrival_station']) ? ' -> ' . (string)$s3Station['a20_arrival_station'] : '');
                    }
                }
            } else {
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
            if (!empty($s5['ferry_art16_notice_within_30min'])) {
                $lines[] = 'Art.16 info inden 30 min: ' . (string)$s5['ferry_art16_notice_within_30min'];
            }
            if (array_key_exists('vehicle_breakdown', $s5) && (string)($s5['vehicle_breakdown'] ?? '') !== '') {
                $lines[] = 'Bus nedbrud / uanvendelig bus: ' . (string)$s5['vehicle_breakdown'];
            }
            if ($transportMode === 'bus') {
                if (array_key_exists('delay_departure_band', $s5) && (string)($s5['delay_departure_band'] ?? '') !== '') {
                    $lines[] = 'Bus forsinkelsesniveau: ' . (string)$s5['delay_departure_band'];
                }
                if (array_key_exists('delay_minutes_departure', $s5) && (string)($s5['delay_minutes_departure'] ?? '') !== '') {
                    $lines[] = 'Bus aktuel afgangsforsinkelse (min): ' . (string)$s5['delay_minutes_departure'];
                }
                if (array_key_exists('planned_duration_band', $s5) && (string)($s5['planned_duration_band'] ?? '') !== '') {
                    $lines[] = 'Bus planlagt rejsevarighed: ' . (string)$s5['planned_duration_band'];
                }
                if (array_key_exists('missed_connection_due_to_delay', $s5) && (string)($s5['missed_connection_due_to_delay'] ?? '') !== '') {
                    $lines[] = 'Bus mistet tilslutning pga. forsinkelse: ' . (string)$s5['missed_connection_due_to_delay'];
                }
                if (array_key_exists('overbooking', $s5) && (string)($s5['overbooking'] ?? '') !== '') {
                    $lines[] = 'Bus overbooking / manglende plads: ' . (string)$s5['overbooking'];
                }
                if (array_key_exists('severe_weather', $s5) && (string)($s5['severe_weather'] ?? '') !== '') {
                    $lines[] = 'Bus kraftigt vejr: ' . (string)$s5['severe_weather'];
                }
                if (array_key_exists('major_natural_disaster', $s5) && (string)($s5['major_natural_disaster'] ?? '') !== '') {
                    $lines[] = 'Bus stor naturkatastrofe: ' . (string)$s5['major_natural_disaster'];
                }
            }
            if ($transportMode === 'air' && !empty($s5['protected_connection_missed'])) {
                $lines[] = 'Protected connection misset: ' . (string)$s5['protected_connection_missed'];
            }
            if ($transportMode === 'air' && !empty($s5['connection_protection_basis'])) {
                $lines[] = 'Protected connection-grundlag: ' . (string)$s5['connection_protection_basis'];
            }
        }

        // Art.20(2c) stuck / where ended (step 6)
        if (!empty($s6)) {
            if ($isFerry) {
                if (!empty($s6['blocked_train_alt_transport'])) {
                    $lines[] = 'Art.20(2)(c) stuck: alt transport stillet: ' . (string)$s6['blocked_train_alt_transport']
                        . (!empty($s6['assistance_alt_transport_type']) ? ' (' . $this->formatTransportChoice((string)$s6['assistance_alt_transport_type']) . ')' : '');
                }
                if (!empty($s6['a20_where_ended'])) {
                    $lines[] = 'Hvor endte du (Art.20): ' . $this->formatResolutionEndpoint((string)$s6['a20_where_ended'], $transportMode)
                        . (!empty($s6['a20_arrival_station']) ? ' -> ' . (string)$s6['a20_arrival_station'] : '');
                }
            } else {
            if (!empty($s6['blocked_train_alt_transport'])) {
                $lines[] = 'Art.20(2)(c) stuck: alt transport stillet: ' . (string)$s6['blocked_train_alt_transport']
                    . (!empty($s6['assistance_alt_transport_type']) ? ' (' . (string)$s6['assistance_alt_transport_type'] . ')' : '');
            }
            if (!empty($s6['a20_where_ended'])) {
                $lines[] = 'Hvor endte du (Art.20): ' . (string)$s6['a20_where_ended']
                    . (!empty($s6['a20_arrival_station']) ? ' → ' . (string)$s6['a20_arrival_station'] : '');
            }
            }
        }

        // Remedies (step 7)
        if (!empty($s7)) {
            if ($isFerry) {
                if (!empty($s7['remedyChoice'])) { $lines[] = 'Art.18 valg: ' . $this->formatRemedyChoice((string)$s7['remedyChoice']); }
                if (!empty($s7['a18_from_station'])) { $lines[] = $step7PlaceLabel . ': ' . (string)$s7['a18_from_station']; }
                if (!empty($s7['a18_reroute_mode'])) { $lines[] = 'Omlaegning: ' . $this->formatTransportChoice((string)$s7['a18_reroute_mode']); }
                if (!empty($s7['a18_reroute_endpoint'])) { $lines[] = 'Hvor endte omlaegningen: ' . $this->formatResolutionEndpoint((string)$s7['a18_reroute_endpoint'], $transportMode); }
                if (!empty($s7['a18_reroute_arrival_station'])) { $lines[] = $rerouteArrivalLabel . ': ' . (string)$s7['a18_reroute_arrival_station']; }
                if (!empty($s7['ferry_offer_provided'])) { $lines[] = 'Tilbud om videre rejse: ' . (string)$s7['ferry_offer_provided']; }
                if (empty($s7['ferry_offer_provided']) && !empty($s7['offer_provided'])) { $lines[] = 'Tilbud om videre rejse: ' . (string)$s7['offer_provided']; }
                if (!empty($s7['ferry_first_usable_solution_timing'])) { $lines[] = 'Foerste brugbare loesning: ' . (string)$s7['ferry_first_usable_solution_timing']; }
                if (!empty($s7['ferry_self_arranged_solution'])) { $lines[] = 'Selv arrangeret loesning: ' . (string)$s7['ferry_self_arranged_solution']; }
                if (!empty($s7['ferry_self_arranged_solution_type'])) { $lines[] = 'Selv valgt loesningstype: ' . (string)$s7['ferry_self_arranged_solution_type']; }
                if (!empty($s7['ferry_self_arranged_reason'])) { $lines[] = 'Hvorfor selv fundet loesning: ' . (string)$s7['ferry_self_arranged_reason']; }
                if (!empty($s7['reroute_info_within_100min'])) { $lines[] = 'Omlaegning meddelt inden 100 min (Art.18(3)): ' . (string)$s7['reroute_info_within_100min']; }
                if (!empty($s7['journey_no_longer_purpose'])) { $lines[] = 'Rejsen tjente ikke laengere et formaal (Art.18(1)(a)): ' . (string)$s7['journey_no_longer_purpose']; }
            } elseif ($transportMode === 'air') {
            if (!empty($s7['remedyChoice'])) { $lines[] = 'Article 8-valg: ' . (string)$s7['remedyChoice']; }
            if (!empty($s7['a18_from_station'])) { $lines[] = 'Lufthavn/sted (Trin 7): ' . (string)$s7['a18_from_station']; }
            if (!empty($s7['air_refund_scope'])) { $lines[] = 'Refund scope: ' . (string)$s7['air_refund_scope']; }
            if (!empty($s7['air_return_to_first_departure_point'])) { $lines[] = 'Retur til foerste afgangssted: ' . (string)$s7['air_return_to_first_departure_point']; }
            if (!empty($s7['a18_reroute_mode'])) { $lines[] = 'Ombooking: ' . (string)$s7['a18_reroute_mode']; }
            if (!empty($s7['a18_reroute_endpoint'])) { $lines[] = 'Hvor endte ombookingen: ' . (string)$s7['a18_reroute_endpoint']; }
            if (!empty($s7['air_article8_choice_offered'])) { $lines[] = 'Article 8-valg tilbudt: ' . (string)$s7['air_article8_choice_offered']; }
            if (!empty($s7['air_self_arranged_reroute'])) { $lines[] = 'Selv arrangeret ombooking: ' . (string)$s7['air_self_arranged_reroute']; }
            if (!empty($s7['air_self_arranged_reroute_reason'])) { $lines[] = 'Hvorfor selv fundet loesning: ' . (string)$s7['air_self_arranged_reroute_reason']; }
            if (!empty($s7['air_airline_confirmed_self_arranged_solution'])) { $lines[] = 'Flyselskabet bekraeftede loesningen: ' . (string)$s7['air_airline_confirmed_self_arranged_solution']; }
            if (!empty($s7['air_alternative_airport_transfer_needed'])) { $lines[] = 'Alternativ lufthavn-transfer: ' . (string)$s7['air_alternative_airport_transfer_needed']; }
            if (!empty($s7['air_alternative_airport_transfer_amount'])) { $lines[] = 'Alternativ lufthavn-transfer beloeb: ' . (string)$s7['air_alternative_airport_transfer_amount'] . ' ' . (string)($s7['air_alternative_airport_transfer_currency'] ?? ''); }
            } else {
            if (!empty($s7['remedyChoice'])) { $lines[] = 'Art.18 valg: ' . (string)$s7['remedyChoice']; }
            if (!empty($s7['a18_from_station'])) { $lines[] = 'Station (Trin 7): ' . (string)$s7['a18_from_station']; }
            if (!empty($s7['a18_reroute_mode'])) { $lines[] = 'Omlægning: ' . (string)$s7['a18_reroute_mode']; }
            if (!empty($s7['a18_reroute_endpoint'])) { $lines[] = 'Hvor endte omlægningen: ' . (string)$s7['a18_reroute_endpoint']; }
            if (!empty($s7['a18_reroute_arrival_station'])) { $lines[] = 'Omlagt til station: ' . (string)$s7['a18_reroute_arrival_station']; }
            if ($transportMode === 'bus' && !empty($s7['carrier_offered_choice'])) { $lines[] = 'Valg mellem tilbagebetaling og ombooking givet: ' . (string)$s7['carrier_offered_choice']; }
            if ($transportMode === 'bus' && !empty($s7['bus_self_arranged_solution'])) { $lines[] = 'Selv arrangeret loesning: ' . (string)$s7['bus_self_arranged_solution']; }
            if ($transportMode === 'bus' && !empty($s7['bus_self_arranged_solution_type'])) { $lines[] = 'Hvad gjorde du selv: ' . (string)$s7['bus_self_arranged_solution_type']; }
            if ($transportMode === 'bus' && !empty($s7['bus_self_arranged_reason'])) { $lines[] = 'Hvorfor selv fundet loesning: ' . (string)$s7['bus_self_arranged_reason']; }
            if ($transportMode === 'bus' && !empty($s7['reroute_extra_costs'])) { $lines[] = 'Merudgifter ved ombooking: ' . (string)$s7['reroute_extra_costs']; }
            if ($transportMode === 'bus' && !empty($s7['reroute_extra_costs_type'])) { $lines[] = 'Merudgiftstype: ' . (string)$s7['reroute_extra_costs_type']; }
            if ($transportMode === 'bus' && !empty($s7['reroute_extra_costs_amount'])) { $lines[] = 'Merudgift: ' . (string)$s7['reroute_extra_costs_amount'] . ' ' . (string)($s7['reroute_extra_costs_currency'] ?? ''); }
            if ($transportMode === 'bus' && !empty($s7['reroute_extra_costs_description'])) { $lines[] = 'Merudgift beskrivelse: ' . (string)$s7['reroute_extra_costs_description']; }
            if (!empty($s7['reroute_info_within_100min'])) { $lines[] = 'Omlægning meddelt inden 100 min (Art.18(3)): ' . (string)$s7['reroute_info_within_100min']; }
            if (!empty($s7['journey_no_longer_purpose'])) { $lines[] = 'Rejsen tjente ikke længere et formål (Art.18(1)(a)): ' . (string)$s7['journey_no_longer_purpose']; }
            }
        }

        // Assistance/expenses (step 8)
        if (!empty($s8)) {
            if ($transportMode === 'bus' && empty($s8['meal_offered']) && !empty($s8['bus_refreshments_offered'])) { $lines[] = 'Maltider tilbudt: ' . (string)$s8['bus_refreshments_offered']; }
            if ($transportMode === 'bus' && empty($s8['hotel_offered']) && !empty($s8['bus_hotel_offered'])) { $lines[] = 'Hotel tilbudt: ' . (string)$s8['bus_hotel_offered']; }
            if (!empty($s8['meal_offered'])) { $lines[] = 'Måltider tilbudt: ' . (string)$s8['meal_offered']; }
            if (!empty($s8['hotel_offered'])) { $lines[] = 'Hotel tilbudt: ' . (string)$s8['hotel_offered']; }
            if ($transportMode === 'bus' && !empty($s8['assistance_pmr_priority_applied'])) { $lines[] = 'Bus PMR-assistance ydet ved terminal/ombord: ' . (string)$s8['assistance_pmr_priority_applied']; }
            if ($transportMode === 'bus' && !empty($s8['assistance_pmr_companion_supported'])) { $lines[] = 'Bus PMR-ledsager/saerlige behov understoettet: ' . (string)$s8['assistance_pmr_companion_supported']; }
            if ($transportMode === 'bus' && !empty($s8['assistance_pmr_dog_supported'])) { $lines[] = 'Bus PMR-servicehund understoettet: ' . (string)$s8['assistance_pmr_dog_supported']; }
        }

        // Downgrade (step 9)
        if (!empty($s9) && array_key_exists('downgrade_occurred', $s9)) {
            $lines[] = 'Nedgradering: ' . (string)($s9['downgrade_occurred'] ?? '');
            if ($transportMode === 'air' && !empty($s9['air_downgrade_booked_class'])) {
                $lines[] = 'Koebt kabineklasse: ' . (string)$s9['air_downgrade_booked_class'];
            }
            if ($transportMode === 'air' && !empty($s9['air_downgrade_flown_class'])) {
                $lines[] = 'Floejet kabineklasse: ' . (string)$s9['air_downgrade_flown_class'];
            }
            if ($transportMode === 'air' && !empty($s9['air_downgrade_refund_percent'])) {
                $lines[] = 'Artikel 10-refusionsprocent: ' . (string)$s9['air_downgrade_refund_percent'] . '%';
            }
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

        if ($transportMode === 'bus') {
            $ticketPrice = $jb['price'] ?? null;
            $ticketCurrency = (string)($s10ResultPreview['currency'] ?? ($jb['price_currency'] ?? ($actualTotals['currency'] ?? '')));
            $compBreakdown = (array)($s10ResultPreview['compensation'] ?? ($actualBreakdown['compensation'] ?? []));
            $compPct = (int)($compBreakdown['pct'] ?? 0);
            $compAmount = $compBreakdown['amount'] ?? null;
            $busCaps = (array)($s10ResultPreview['caps']['bus_caps'] ?? ($actualBreakdown['bus_caps'] ?? []));
            $previewExpenses = (array)($s10ResultPreview['expenses'] ?? []);
            $previewArt18 = (array)($s10ResultPreview['art18'] ?? []);
            $previewTotals = (array)($s10ResultPreview['totals'] ?? $actualTotals);
            if ($compPct === 50 && $compAmount !== null && $compAmount !== '') {
                $pricePart = $ticketPrice !== null && $ticketPrice !== ''
                    ? ' (50% af billetpris ' . (string)$ticketPrice . ' ' . $ticketCurrency . ')'
                    : '';
                $lines[] = 'Bus 50%-kompensation: ' . (string)$compAmount . ' ' . $ticketCurrency . $pricePart;
            }
            if (!empty($busCaps['hotel_legal_cap_amount'])) {
                $nightsPart = !empty($busCaps['hotel_capped_nights']) ? ' (' . (string)$busCaps['hotel_capped_nights'] . ' nat' . ((int)$busCaps['hotel_capped_nights'] === 1 ? '' : 'ter') . ')' : '';
                $perNightEur = (string)($busCaps['hotel_legal_per_night_eur'] ?? '80');
                $lines[] = 'Bus hotel-loft: ' . $perNightEur . ' EUR pr. nat' . $nightsPart;
            }
            if (!empty($busCaps['hotel_cap_applied'])) {
                $lines[] = 'Bus hotel capped: ' . (string)($busCaps['hotel_requested_amount'] ?? '') . ' -> ' . (string)($actual['claim']['breakdown']['expenses']['hotel'] ?? '') . ' ' . $ticketCurrency;
            }
            if (!empty($busCaps['meals_soft_cap_amount'])) {
                $delayHoursPart = !empty($busCaps['soft_cap_delay_hours']) ? ' (' . (string)$busCaps['soft_cap_delay_hours'] . ' forsinkelsestimer)' : '';
                $lines[] = 'Bus maaltider soft cap: ' . (string)$busCaps['meals_soft_cap_amount'] . ' ' . $ticketCurrency . $delayHoursPart;
            }
            if (!empty($busCaps['hotel_transport_soft_cap_amount'])) {
                $lines[] = 'Bus hoteltransport soft cap: ' . (string)$busCaps['hotel_transport_soft_cap_amount'] . ' ' . $ticketCurrency;
            }
            if (!empty($busCaps['alt_transport_soft_cap_amount'])) {
                $distancePart = isset($busCaps['soft_cap_basis_distance_km']) && $busCaps['soft_cap_basis_distance_km'] !== null && $busCaps['soft_cap_basis_distance_km'] !== ''
                    ? ' (' . (string)$busCaps['soft_cap_basis_distance_km'] . ' km)'
                    : '';
                $lines[] = 'Bus alternativ transport soft cap: ' . (string)$busCaps['alt_transport_soft_cap_amount'] . ' ' . $ticketCurrency . $distancePart;
            }
            if (!empty($busCaps['breakdown_full_coverage'])) {
                $lines[] = 'Busnedbrud: full coverage for videre transport';
            }
            if (array_key_exists('total', $previewExpenses)) {
                $lines[] = 'Bus udgifter i alt: ' . (string)$previewExpenses['total'] . ' ' . $ticketCurrency;
            }
            if (!empty($previewArt18['reroute_extra_costs']) || !empty($previewArt18['return_to_origin'])) {
                $lines[] = 'Bus Art. 19/ombooking-retur: '
                    . (string)($previewArt18['reroute_extra_costs'] ?? 0)
                    . ' + '
                    . (string)($previewArt18['return_to_origin'] ?? 0)
                    . ' '
                    . $ticketCurrency;
            }
            if (array_key_exists('gross_claim', $previewTotals)) {
                $lines[] = 'Bus samlet krav (preview): ' . (string)$previewTotals['gross_claim'] . ' ' . $ticketCurrency;
            }
        }

        if ($transportMode === 'ferry') {
            $ticketCurrency = (string)($jb['price_currency'] ?? ($actual['claim']['totals']['currency'] ?? 'EUR'));
            $ferryCaps = (array)($actual['claim']['breakdown']['ferry_caps'] ?? []);
            $ferryLegal = (array)($ferryCaps['legal'] ?? []);
            $ferryEngine = (array)($ferryCaps['engine'] ?? []);
            if (!empty($ferryLegal)) {
                $lines[] = 'Ferry hotel-loft: '
                    . (string)($ferryLegal['hotel_land_per_night_eur'] ?? 80)
                    . ' EUR pr. nat, maks. '
                    . (string)($ferryLegal['hotel_land_max_nights'] ?? 3)
                    . ' naetter';
                $lines[] = 'Ferry maaltider: lovregel = '
                    . (string)($ferryLegal['meals_rule'] ?? 'reasonable');
                $lines[] = 'Ferry refusion: '
                    . (string)($ferryLegal['refund_ticket_price_percent'] ?? 100)
                    . '% inden for '
                    . (string)($ferryLegal['refund_deadline_days'] ?? 7)
                    . ' dage';
            }
            if (!empty($ferryEngine)) {
                $lines[] = 'Ferry soft caps: maaltider '
                    . (string)($ferryEngine['meals_per_day_eur'] ?? 40)
                    . ' EUR/dag, lokal transport '
                    . (string)($ferryEngine['local_transport_per_trip_eur'] ?? 50)
                    . ' EUR/tur, taxi '
                    . (string)($ferryEngine['taxi_soft_cap_eur'] ?? 150)
                    . ' EUR, alternativ transport '
                    . (string)($ferryEngine['self_arranged_alt_transport_total_eur'] ?? 400)
                    . ' EUR';
            }
            if (!empty($ferryCaps['hotel_excess_amount'])) {
                $lines[] = 'Ferry hotel capped: over loft med '
                    . (string)$ferryCaps['hotel_excess_amount']
                    . ' '
                    . $ticketCurrency;
            }
            if (!empty($ferryCaps['hotel_weather_blocked'])) {
                $lines[] = 'Ferry hotelret bortfaldt pga. vejrsikkerhedsrisiko';
            }
            if (!empty($ferryCaps['meals_manual_review_required'])) {
                $lines[] = 'Ferry maaltider: manuel vurdering';
            }
            if (!empty($ferryCaps['hotel_transport_manual_review_required'])) {
                $lines[] = 'Ferry hoteltransport: manuel vurdering';
            }
            if (!empty($ferryCaps['reroute_alt_transport_manual_review_required'])) {
                $lines[] = 'Ferry alternativ transport: manuel vurdering';
            }
        }

        // If actual claim exists, add one compact line.
        if (isset($s10ResultPreview['totals']['gross_claim'])) {
            $lines[] = 'Beregnet (wizard preview): brutto '
                . (string)$s10ResultPreview['totals']['gross_claim']
                . ' '
                . (string)($s10ResultPreview['currency'] ?? '');
        } elseif (isset($actualTotals['gross_claim'])) {
            $lines[] = 'Beregnet (pipeline): brutto ' . (string)$actualTotals['gross_claim'] . ' ' . (string)($actualTotals['currency'] ?? '');
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
        $transportMode = $this->normalizeTransportMode((string)($fx['transport_mode'] ?? 'rail'));
        $isFerry = $transportMode === 'ferry';
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
        $ferryPmrGate = ($yn($s4Journey['pmr_user'] ?? '') === 'yes') && (
            in_array(strtolower(trim((string)($s4Journey['ferry_pmr_assistance_delivered'] ?? 'unknown'))), ['partial', 'none'], true)
            || ($yn($s4Journey['ferry_pmr_boarding_refused'] ?? '') === 'yes')
        );
        $busPmrRemedyGate = ($yn($s4Journey['pmr_user'] ?? '') === 'yes')
            && ($yn($s4Journey['bus_pmr_boarding_refused'] ?? '') === 'yes')
            && ($yn($s4Journey['bus_pmr_notice_36h'] ?? '') === 'yes');
        $bikeGate = ($yn($s4Journey['bike_denied_boarding'] ?? '') === 'yes') && (
            $yn($s4Journey['bike_refusal_reason_provided'] ?? '') !== 'yes' || trim((string)($s4Journey['bike_refusal_reason_type'] ?? '')) === ''
        );

        $activeReasons = [];
        if ($cancellation) { $activeReasons[] = 'cancellation'; }
        if ($expected60 === 'yes' || $already60 === 'yes') { $activeReasons[] = 'delay>=60'; }
        if ($incidentMissed === 'yes') { $activeReasons[] = 'missed_connection'; }
        if ($pmrGate) { $activeReasons[] = 'pmr_gate'; }
        if ($ferryPmrGate) { $activeReasons[] = 'ferry_pmr'; }
        if ($busPmrRemedyGate) { $activeReasons[] = 'bus_pmr'; }
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
        if ($remedy !== '') { $conclusion[] = 'Valgt Art.18-vej: ' . $this->formatRemedyChoice($remedy) . '.'; }
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
            'transport_mode' => $transportMode,
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
            'display' => [
                'transport_mode' => $this->formatTransportChoice($transportMode),
                'place_label' => $isFerry ? 'havn/terminal' : 'station',
                'from_place_label' => $isFerry ? 'Fra havn/terminal' : 'Fra station',
                'return_to_place_label' => $isFerry ? 'Retur til afgangshavn/terminal' : 'Retur til afgangsstation',
                'arrival_place_label' => $isFerry ? 'Omlagt til havn/terminal' : 'Omlagt til station',
                'remedyChoice' => $this->formatRemedyChoice($remedy),
                'art20_resolution_endpoint' => $this->formatResolutionEndpoint((string)($s3Station['a20_where_ended'] ?? ''), $transportMode),
                'reroute_mode' => $this->formatTransportChoice((string)($s7['a18_reroute_mode'] ?? '')),
                'reroute_endpoint' => $this->formatResolutionEndpoint((string)($s7['a18_reroute_endpoint'] ?? ''), $transportMode),
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

    private function normalizeTransportMode(?string $mode): string
    {
        $normalized = strtolower(trim((string)$mode));

        return in_array($normalized, ['rail', 'bus', 'ferry', 'air'], true) ? $normalized : 'rail';
    }

    private function formatTransportChoice(?string $value): ?string
    {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'rail' => 'Tog',
            'bus' => 'Bus',
            'ferry' => 'Faerge',
            'air' => 'Fly',
            'taxi' => 'Taxi/minibus',
            'rideshare' => 'Samkoersel/rideshare',
            'other' => 'Andet',
            default => trim((string)$value),
        };
    }

    private function formatResolutionEndpoint(?string $value, string $transportMode): ?string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'nearest_station' => $this->normalizeTransportMode($transportMode) === 'ferry'
                ? 'Naermeste havn/terminal'
                : 'Naermeste station',
            'other_departure_point' => 'Et andet egnet afgangssted',
            'final_destination' => 'Mit endelige bestemmelsessted',
            default => $normalized,
        };
    }

    private function formatRemedyChoice(?string $value): ?string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'refund_return' => 'Tilbagebetaling',
            'reroute_soonest' => 'Ombooking hurtigst muligt',
            'reroute_later' => 'Ombooking senere (efter eget valg)',
            default => $normalized,
        };
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
