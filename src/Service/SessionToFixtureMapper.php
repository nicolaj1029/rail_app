<?php
declare(strict_types=1);

namespace App\Service;

class SessionToFixtureMapper
{
    /**
     * Map flow session to a v2 fixture skeleton.
     *
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function mapSessionToFixture(array $flow): array
    {
        [$journeyBasic, $segments] = $this->buildJourneyParts($flow);
        $step3 = $this->mergeStep3($flow);
        $step5Transport = $this->extractStep5Transport($flow);
        $step6Remedies = $this->extractStep6Remedies($flow);
        $step7Assistance = $this->extractStep7Assistance($flow);
        $step8Downgrade = $this->extractStep8Downgrade($flow);
        $step9Comp = $this->extractStep9Compensation($flow);

        return [
            'id' => 'session_' . date('Ymd_His'),
            'version' => 2,
            'label' => 'Captured wizard session ' . date('c'),
            'journey' => $flow['journey'] ?? ($flow['meta']['journey'] ?? []),
            'journeyBasic' => $journeyBasic,
            'segments' => $segments,
            // Persist selected meta hooks (multi-ticket / guardian etc.) for QA fixtures
            'meta' => $this->extractMeta($flow),
            'wizard' => [
                // Split flow order: start -> entitlements -> journey -> incident -> choices(transport) -> remedies -> assistance -> downgrade -> compensation
                'step1_start' => $this->extractStep1Start($flow),
                'step2_entitlements' => $this->extractStep2Entitlements($flow),
                'step3_journey' => $step3,
                'step4_incident' => $this->extractStep4Incident($flow),
                // Keep legacy key name step5_choices, but it now only contains Art.20 transport.
                'step5_choices' => $step5Transport,
                'step6_remedies' => $step6Remedies,
                'step7_assistance' => $step7Assistance,
                'step8_downgrade' => $step8Downgrade,
                'step9_compensation' => $step9Comp,
            ],
            'art9_meta' => $this->buildArt9Meta($flow),
            'art12_meta' => $this->buildArt12Meta($flow, $journeyBasic, $segments),
            'computeOverrides' => [
                // Keep these explicit so scenarios can reproduce claim/compensation gating.
                'euOnly' => $flow['compute']['euOnly'] ?? null,
                'delayMinEU' => $flow['compute']['delayMinEU'] ?? null,
                'knownDelayBeforePurchase' => $flow['compute']['knownDelayBeforePurchase'] ?? null,
                'delayAtFinalMinutes' => $flow['compute']['delayAtFinalMinutes'] ?? null,
                'refundAlready' => $flow['compute']['refundAlready'] ?? null,
                'throughTicket' => $flow['compute']['throughTicket'] ?? null,
                'returnTicket' => $flow['compute']['returnTicket'] ?? null,
                'legPrice' => $flow['compute']['legPrice'] ?? null,
                'extraordinary' => $flow['compute']['extraordinary'] ?? null,
                'selfInflicted' => $flow['compute']['selfInflicted'] ?? null,
                'art18Option' => $flow['compute']['art18Option'] ?? null,
            ],
            'expected' => [],
        ];
    }

    /**
     * Enriched variant: includes journey basics, up to 3 segments, and extra step3 fields
     * without altering the default minimal schema.
     *
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function mapSessionToFixtureEnriched(array $flow): array
    {
        $base = $this->mapSessionToFixture($flow);
        $base['meta'] = ($base['meta'] ?? []) + ['enriched' => true];
        $form = (array)($flow['form'] ?? []);
        $auto = (array)($flow['meta']['_auto'] ?? []);
        $segAuto = (array)($flow['meta']['_segments_auto'] ?? []);

        $base['journeyBasic'] = [
            'dep_date' => $form['dep_date'] ?? ($auto['dep_date']['value'] ?? null),
            'dep_time' => $form['dep_time'] ?? ($auto['dep_time']['value'] ?? null),
            'dep_station' => $form['dep_station'] ?? ($auto['dep_station']['value'] ?? null),
            'arr_station' => $form['arr_station'] ?? ($auto['arr_station']['value'] ?? null),
            'arr_time' => $form['arr_time'] ?? ($auto['arr_time']['value'] ?? null),
            'train_no' => $form['train_no'] ?? ($auto['train_no']['value'] ?? null),
            'ticket_no' => $form['ticket_no'] ?? ($auto['ticket_no']['value'] ?? null),
            'operator' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            'operator_country' => $form['operator_country'] ?? ($auto['operator_country']['value'] ?? null),
            'operator_product' => $form['operator_product'] ?? ($auto['operator_product']['value'] ?? null),
            'price' => $form['price'] ?? ($auto['price']['value'] ?? null),
        ];

        $segments = [];
        for ($i = 0; $i < min(3, count($segAuto)); $i++) {
            $s = (array)$segAuto[$i];
            $segments[] = [
                'from' => $s['from'] ?? null,
                'to' => $s['to'] ?? null,
                'schedDep' => $s['schedDep'] ?? null,
                'schedArr' => $s['schedArr'] ?? null,
                'trainNo' => $s['trainNo'] ?? ($s['train_no'] ?? null),
                'depDate' => $s['depDate'] ?? null,
                'arrDate' => $s['arrDate'] ?? null,
                'pnr' => $form['ticket_no'] ?? ($auto['ticket_no']['value'] ?? null),
                'carrier' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            ];
        }
        $base['segments'] = $segments;

        // Merge in additional step3 fields if present
        $extraStep3 = $this->extractStep3Extras($flow);
        if (!empty($extraStep3)) {
            $base['wizard']['step3_journey'] = (array)($base['wizard']['step3_journey'] ?? []) + $extraStep3;
        }

        // Art.12 minimal meta injection (only hints; exception pair left unknown)
        $ticketNo = $base['journeyBasic']['ticket_no'] ?? null;
        $operator = $base['journeyBasic']['operator'] ?? null;
        $pnrPresent = !empty($ticketNo);
        $opsSet = [];
        foreach ($segments as $sg) { $opCur = $sg['carrier'] ?? null; if ($opCur) { $opsSet[$opCur] = true; } }
        $multiOps = count($opsSet) > 1;
        $base['art12_meta'] = [
            'seller_type_operator' => $operator ? 'Ja' : 'Ved ikke',
            'seller_type_agency' => 'Nej',
            'single_booking_reference' => $pnrPresent ? 'Ja' : 'Ved ikke',
            'shared_pnr_scope' => $pnrPresent ? 'Ja' : 'Ved ikke',
            'multi_operator_trip' => $multiOps ? 'Ja' : ($operator ? 'Nej' : 'Ved ikke'),
            'separate_contract_notice' => 'Ved ikke',
            'through_ticket_disclosure' => 'Ved ikke',
        ];

        return $base;
    }

    /**
     * Keep only relevant meta hooks (avoid dumping heavy OCR blobs).
     */
    private function extractMeta(array $flow): array
    {
        $meta = (array)($flow['meta'] ?? []);
        $keep = [
            '_multi_tickets',
            '_passengers_auto',
            'ticket_upload_count',
            'ticket_multi_passenger',
            'claimant_is_legal_representative',
        ];
        $out = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $meta)) {
                $out[$k] = $meta[$k];
            }
        }
        return $out;
    }

    private function extractStep3(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'pmr_user' => $f['pmr_user'] ?? 'unknown',
            'pmr_booked' => $f['pmr_booked'] ?? 'unknown',
            'pmrQBooked' => $f['pmrQBooked'] ?? null,
            'pmrQDelivered' => $f['pmrQDelivered'] ?? null,
            'pmrQPromised' => $f['pmrQPromised'] ?? null,
            'pmr_facility_details' => $f['pmr_facility_details'] ?? null,
            'fare_flex_type' => $f['fare_flex_type'] ?? null,
            'train_specificity' => $f['train_specificity'] ?? null,
            'fare_class_purchased' => $f['fare_class_purchased'] ?? null,
            'berth_seat_type' => $f['berth_seat_type'] ?? null,
        ];
    }

    /**
     * Extract optional extra Step 3 fields commonly used in UI/tests.
     *
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function extractStep3Extras(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        $out = [];
        foreach ([
            'interest_class','eligible_under_18_3','class_delivered','class_purchased',
            'bike_booked','bike_res_required','bike_reservation_type',
            'bike_was_present','bike_caused_issue','bike_reservation_made','bike_reservation_required',
            'bike_denied_boarding','bike_refusal_reason_provided','bike_refusal_reason_type','bike_refusal_reason_other_text',
            'pmr_promised_missing','pmr_delivered_status','pmrQAny','pmr_any',
        ] as $k) {
            if (array_key_exists($k, $f)) { $out[$k] = $f[$k]; }
        }
        return $out;
    }

    /**
     * Merge primary step3 entitlements with optional extras (bike/pmr/class flags).
     */
    private function mergeStep3(array $flow): array
    {
        $base = $this->extractStep3($flow);
        $extra = $this->extractStep3Extras($flow);
        if (!empty($extra)) {
            $base = $base + $extra;
        }
        return $this->normalizeStep3($base);
    }

    /**
     * Normalize yes/no values in step3 to a consistent Ja/Nej/unknown format.
     *
     * @param array<string,mixed> $step3
     * @return array<string,mixed>
     */
    private function normalizeStep3(array $step3): array
    {
        $ynKeys = [
            'pmr_user','pmr_booked','pmr_promised_missing',
            'bike_was_present','bike_caused_issue','bike_reservation_made','bike_reservation_required',
            'bike_denied_boarding','bike_refusal_reason_provided',
        ];
        foreach ($ynKeys as $k) {
            if (!array_key_exists($k, $step3)) { continue; }
            $step3[$k] = $this->normalizeYesNoDa($step3[$k]);
        }
        return $step3;
    }

    private function normalizeYesNoDa(mixed $v): string
    {
        if (is_bool($v)) { return $v ? 'Ja' : 'Nej'; }
        $s = strtolower(trim((string)$v));
        if (in_array($s, ['refused','attempted_refused'], true)) { return 'Nej'; }
        if (in_array($s, ['ja','yes','y','1','true'], true)) { return 'Ja'; }
        if (in_array($s, ['nej','no','n','0','false'], true)) { return 'Nej'; }
        if ($s === '' || $s === '-' || $s === 'unknown' || $s === 'ved ikke') { return 'unknown'; }
        return (string)$v;
    }

    private function extractStep4(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'art18_expected_delay_60' => $f['art18_expected_delay_60'] ?? null,
            'remedyChoice' => $f['remedyChoice'] ?? null,
            'self_purchased_new_ticket' => $f['self_purchased_new_ticket'] ?? null,
            'self_purchase_approved_by_operator' => $f['self_purchase_approved_by_operator'] ?? null,
            'offer_provided' => $f['offer_provided'] ?? null,
            'reroute_same_conditions_soonest' => $f['reroute_same_conditions_soonest'] ?? null,
            'reroute_later_at_choice' => $f['reroute_later_at_choice'] ?? null,
            'reroute_info_within_100min' => $f['reroute_info_within_100min'] ?? null,
            'reroute_extra_costs' => $f['reroute_extra_costs'] ?? null,
            'reroute_extra_costs_amount' => $f['reroute_extra_costs_amount'] ?? null,
            'reroute_extra_costs_currency' => $f['reroute_extra_costs_currency'] ?? null,
            'delayAtFinalMinutes' => $f['delayAtFinalMinutes'] ?? null,
            'compensationBand' => $f['compensationBand'] ?? null,
            'voucherAccepted' => $f['voucherAccepted'] ?? null,
            'trip_cancelled_return_to_origin' => $f['trip_cancelled_return_to_origin'] ?? null,
            'return_to_origin_expense' => $f['return_to_origin_expense'] ?? null,
            'return_to_origin_amount' => $f['return_to_origin_amount'] ?? null,
            'return_to_origin_currency' => $f['return_to_origin_currency'] ?? null,
            'delay_confirmation_info' => $f['delay_confirmation_info'] ?? null,
            'delay_confirmation_received' => $f['delay_confirmation_received'] ?? null,
            'delay_confirmation_upload' => $f['delay_confirmation_upload'] ?? null,
            'delay_confirmation_type' => $f['delay_confirmation_type'] ?? null,
            'reroute_later_ticket_upload' => $f['reroute_later_ticket_upload'] ?? null,
            // Art.20 transport (moved to choices)
            'stranded_location' => $f['stranded_location'] ?? null,
            'journey_outcome' => $f['journey_outcome'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
            'assistance_alt_transport_offered_by' => $f['assistance_alt_transport_offered_by'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
            'assistance_alt_to_destination' => $f['assistance_alt_to_destination'] ?? null,
            'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,
            // Art.20(3) station-specific
            'a20_3_solution_offered' => $f['a20_3_solution_offered'] ?? null,
            'a20_3_solution_type' => $f['a20_3_solution_type'] ?? null,
            'a20_3_solution_offered_by' => $f['a20_3_solution_offered_by'] ?? null,
            'a20_3_no_solution_action' => $f['a20_3_no_solution_action'] ?? null,
            'a20_3_outcome' => $f['a20_3_outcome'] ?? null,
            'a20_3_self_arranged_type' => $f['a20_3_self_arranged_type'] ?? null,
            'a20_3_self_paid_amount' => $f['a20_3_self_paid_amount'] ?? null,
            'a20_3_self_paid_currency' => $f['a20_3_self_paid_currency'] ?? null,
            'a20_3_self_paid_receipt' => $f['a20_3_self_paid_receipt'] ?? null,
        ];
    }

    private function extractStep5(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'art20_expected_delay_60' => $f['art20_expected_delay_60'] ?? null,
            'meal_offered' => $f['meal_offered'] ?? null,
            'assistance_meals_unavailable_reason' => $f['assistance_meals_unavailable_reason'] ?? null,
            'hotel_offered' => $f['hotel_offered'] ?? null,
            'overnight_needed' => $f['overnight_needed'] ?? null,
            'assistance_hotel_transport_included' => $f['assistance_hotel_transport_included'] ?? null,
            'assistance_hotel_accessible' => $f['assistance_hotel_accessible'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_on_track' => $f['blocked_on_track'] ?? null,
            'stranded_at_station' => $f['stranded_at_station'] ?? null,
            'stranded_unknown' => $f['stranded_unknown'] ?? null,
            'assistance_blocked_transport_to' => $f['assistance_blocked_transport_to'] ?? null,
            'assistance_blocked_transport_time' => $f['assistance_blocked_transport_time'] ?? null,
            'assistance_blocked_transport_possible' => $f['assistance_blocked_transport_possible'] ?? null,
            'alt_transport_provided' => $f['alt_transport_provided'] ?? null,
            'assistance_alt_transport_offered_by' => $f['assistance_alt_transport_offered_by'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
            'assistance_alt_to_destination' => $f['assistance_alt_to_destination'] ?? null,
            'assistance_alt_departure_time' => $f['assistance_alt_departure_time'] ?? null,
            'assistance_alt_arrival_time' => $f['assistance_alt_arrival_time'] ?? null,
            'assistance_pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
            'assistance_pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
            'assistance_pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,
            // Self-paid assistance costs
            'meal_self_paid_amount' => $f['meal_self_paid_amount'] ?? null,
            'meal_self_paid_currency' => $f['meal_self_paid_currency'] ?? null,
            'meal_self_paid_receipt' => $f['meal_self_paid_receipt'] ?? null,
            'hotel_self_paid_nights' => $f['hotel_self_paid_nights'] ?? null,
            'hotel_self_paid_amount' => $f['hotel_self_paid_amount'] ?? null,
            'hotel_self_paid_currency' => $f['hotel_self_paid_currency'] ?? null,
            'hotel_self_paid_receipt' => $f['hotel_self_paid_receipt'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,
            'alt_self_paid_amount' => $f['alt_self_paid_amount'] ?? null,
            'alt_self_paid_currency' => $f['alt_self_paid_currency'] ?? null,
            'alt_self_paid_receipt' => $f['alt_self_paid_receipt'] ?? null,
            'price_hints' => $flow['meta']['price_hints'] ?? null,
        ];
    }

    /**
     * TRIN 5 (choices.php): Art.20 transport / stranded resolution.
     * Keep this minimal so it does not drift into TRIN 6 remedies.
     */
    private function extractStep5Transport(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            // Art.20(2)(c) / (3) – stranded context
            'stranded_location' => $f['stranded_location'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
            'assistance_alt_transport_offered_by' => $f['assistance_alt_transport_offered_by'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
            'assistance_alt_to_destination' => $f['assistance_alt_to_destination'] ?? null,
            'assistance_alt_departure_time' => $f['assistance_alt_departure_time'] ?? null,
            'assistance_alt_arrival_time' => $f['assistance_alt_arrival_time'] ?? null,

            // Self-paid transport while blocked on track
            'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,

            // Art.20(3) station-specific
            'a20_3_solution_offered' => $f['a20_3_solution_offered'] ?? null,
            'a20_3_solution_type' => $f['a20_3_solution_type'] ?? null,
            'a20_3_solution_offered_by' => $f['a20_3_solution_offered_by'] ?? null,
            'a20_3_no_solution_action' => $f['a20_3_no_solution_action'] ?? null,
            'a20_3_outcome' => $f['a20_3_outcome'] ?? null,
            'a20_3_self_arranged_type' => $f['a20_3_self_arranged_type'] ?? null,
            'a20_3_self_paid_amount' => $f['a20_3_self_paid_amount'] ?? null,
            'a20_3_self_paid_currency' => $f['a20_3_self_paid_currency'] ?? null,
            'a20_3_self_paid_receipt' => $f['a20_3_self_paid_receipt'] ?? null,

            // Convergence endpoints
            'journey_outcome' => $f['journey_outcome'] ?? null,
            'assistance_alt_to_destination_assumed' => $f['assistance_alt_to_destination_assumed'] ?? null,
            'assistance_alt_to_destination_station' => $f['assistance_alt_to_destination_station'] ?? null,
            'assistance_alt_to_destination_station_other' => $f['assistance_alt_to_destination_station_other'] ?? null,
        ];
    }

    /**
     * TRIN 6 (remedies.php): Art.18 refusion/omlaegning.
     */
    private function extractStep6Remedies(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'art18_expected_delay_60' => $f['art18_expected_delay_60'] ?? null,
            'remedyChoice' => $f['remedyChoice'] ?? null,

            // Reroute now/later
            'reroute_same_conditions_soonest' => $f['reroute_same_conditions_soonest'] ?? null,
            'reroute_later_at_choice' => $f['reroute_later_at_choice'] ?? null,
            'reroute_info_within_100min' => $f['reroute_info_within_100min'] ?? null,
            'self_purchased_new_ticket' => $f['self_purchased_new_ticket'] ?? null,
            'self_purchase_approved_by_operator' => $f['self_purchase_approved_by_operator'] ?? null,
            'reroute_extra_costs' => $f['reroute_extra_costs'] ?? null,
            'reroute_extra_costs_amount' => $f['reroute_extra_costs_amount'] ?? null,
            'reroute_extra_costs_currency' => $f['reroute_extra_costs_currency'] ?? null,
            'reroute_later_ticket_upload' => $f['reroute_later_ticket_upload'] ?? null,

            // Refund + return-to-origin
            'trip_cancelled_return_to_origin' => $f['trip_cancelled_return_to_origin'] ?? null,
            'return_to_origin_expense' => $f['return_to_origin_expense'] ?? null,
            'return_to_origin_amount' => $f['return_to_origin_amount'] ?? null,
            'return_to_origin_currency' => $f['return_to_origin_currency'] ?? null,
            'return_to_origin_receipt' => $f['return_to_origin_receipt'] ?? null,

            // Delay confirmation evidence (if user has it)
            'delay_confirmation_info' => $f['delay_confirmation_info'] ?? null,
            'delay_confirmation_received' => $f['delay_confirmation_received'] ?? null,
            'delay_confirmation_upload' => $f['delay_confirmation_upload'] ?? null,
            'delay_confirmation_type' => $f['delay_confirmation_type'] ?? null,
        ];
    }

    /**
     * TRIN 7 (assistance.php): Art.20 meals/hotel costs.
     */
    private function extractStep7Assistance(array $flow): array
    {
        return $this->extractStep5($flow);
    }

    /**
     * TRIN 8 (downgrade.php): per-leg bought vs delivered class/reservation.
     */
    private function extractStep8Downgrade(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'downgrade_occurred' => $f['downgrade_occurred'] ?? null,
            'downgrade_comp_basis' => $f['downgrade_comp_basis'] ?? null,
            'downgrade_segment_share' => $f['downgrade_segment_share'] ?? null,
            'leg_class_purchased' => $f['leg_class_purchased'] ?? [],
            'leg_class_delivered' => $f['leg_class_delivered'] ?? [],
            'leg_reservation_purchased' => $f['leg_reservation_purchased'] ?? [],
            'leg_reservation_delivered' => $f['leg_reservation_delivered'] ?? [],
            'leg_downgraded' => $f['leg_downgraded'] ?? [],
        ];
    }

    /**
     * TRIN 9 (compensation.php): Art.19 band selection + Art.19(10) answers.
     * Note: Some inputs are collected earlier (TRIN 4), but used here.
     */
    private function extractStep9Compensation(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $compute = (array)($flow['compute'] ?? []);

        return [
            'delayAtFinalMinutes' => $form['delayAtFinalMinutes'] ?? null,
            'compensationBand' => $form['compensationBand'] ?? null,
            'voucherAccepted' => $form['voucherAccepted'] ?? null,
            'operatorExceptionalCircumstances' => $form['operatorExceptionalCircumstances'] ?? null,
            'operatorExceptionalType' => $form['operatorExceptionalType'] ?? null,
            'minThresholdApplies' => $form['minThresholdApplies'] ?? null,

            // Useful for scenario overrides
            'delayMinEU' => $compute['delayMinEU'] ?? null,
            'knownDelayBeforePurchase' => $compute['knownDelayBeforePurchase'] ?? null,
        ];
    }

    /**
     * Build journey basics + first up-to-3 segments from AUTO/form data.
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    private function buildJourneyParts(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $auto = (array)($flow['meta']['_auto'] ?? []);
        $segAuto = (array)($flow['meta']['_segments_auto'] ?? []);

        $journeyBasic = [
            'dep_date' => $form['dep_date'] ?? ($auto['dep_date']['value'] ?? null),
            'dep_time' => $form['dep_time'] ?? ($auto['dep_time']['value'] ?? null),
            'dep_station' => $form['dep_station'] ?? ($auto['dep_station']['value'] ?? null),
            'arr_station' => $form['arr_station'] ?? ($auto['arr_station']['value'] ?? null),
            'arr_time' => $form['arr_time'] ?? ($auto['arr_time']['value'] ?? null),
            'train_no' => $form['train_no'] ?? ($auto['train_no']['value'] ?? null),
            'ticket_no' => $form['ticket_no'] ?? ($auto['ticket_no']['value'] ?? null),
            'operator' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            'operator_country' => $form['operator_country'] ?? ($auto['operator_country']['value'] ?? null),
            'operator_product' => $form['operator_product'] ?? ($auto['operator_product']['value'] ?? null),
            'price' => $form['price'] ?? ($auto['price']['value'] ?? null),
        ];

        $segments = [];
        for ($i = 0; $i < min(3, count($segAuto)); $i++) {
            $s = (array)$segAuto[$i];
            $segments[] = [
                'from' => $s['from'] ?? null,
                'to' => $s['to'] ?? null,
                'schedDep' => $s['schedDep'] ?? null,
                'schedArr' => $s['schedArr'] ?? null,
                'trainNo' => $s['trainNo'] ?? ($s['train_no'] ?? null),
                'depDate' => $s['depDate'] ?? null,
                'arrDate' => $s['arrDate'] ?? null,
                'pnr' => $form['ticket_no'] ?? ($auto['ticket_no']['value'] ?? null),
                'carrier' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            ];
        }
        return [$journeyBasic, $segments];
    }

    private function extractStep1Start(array $flow): array
    {
        return [
            'travel_state' => $flow['flags']['travel_state'] ?? null,
            'eu_only' => $flow['compute']['euOnly'] ?? null,
        ];
    }

    private function extractStep2Entitlements(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $auto = (array)($flow['meta']['_auto'] ?? []);

        return [
            'operator' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            'operator_country' => $form['operator_country'] ?? ($auto['operator_country']['value'] ?? null),
            'operator_product' => $form['operator_product'] ?? ($auto['operator_product']['value'] ?? null),
            'ticket_no' => $form['ticket_no'] ?? ($auto['ticket_no']['value'] ?? null),
            'dep_date' => $form['dep_date'] ?? ($auto['dep_date']['value'] ?? null),
            'dep_time' => $form['dep_time'] ?? ($auto['dep_time']['value'] ?? null),
            'dep_station' => $form['dep_station'] ?? ($auto['dep_station']['value'] ?? null),
            'arr_station' => $form['arr_station'] ?? ($auto['arr_station']['value'] ?? null),
            'arr_time' => $form['arr_time'] ?? ($auto['arr_time']['value'] ?? null),
            'train_no' => $form['train_no'] ?? ($auto['train_no']['value'] ?? null),
            'price' => $form['price'] ?? ($auto['price']['value'] ?? null),
            'seller_channel' => $form['seller_channel'] ?? null,
            'through_ticket_disclosure' => $form['through_ticket_disclosure'] ?? null,
            'separate_contract_notice' => $form['separate_contract_notice'] ?? null,
            'single_txn_operator' => $form['single_txn_operator'] ?? null,
            'single_txn_retailer' => $form['single_txn_retailer'] ?? null,
        ];
    }

    private function extractStep4Incident(array $flow): array
    {
        $incident = (array)($flow['incident'] ?? []);
        $form = (array)($flow['form'] ?? []);

        return [
            'incident_main' => $incident['main'] ?? null,
            'incident_missed' => $incident['missed'] ?? null,
            'expected_delay_60' => $form['expected_delay_60'] ?? null,

            // Moved from TRIN 3d -> TRIN 4 top
            'preinformed_disruption' => $form['preinformed_disruption'] ?? null,
            'preinfo_channel' => $form['preinfo_channel'] ?? null,
            'realtime_info_seen' => $form['realtime_info_seen'] ?? null,

            // Missed connection details (when incident_missed === yes)
            'missed_connection_station' => $form['missed_connection_station'] ?? null,
            'missed_connection_pick' => $form['missed_connection_pick'] ?? null,

            // Form & exemptions (Art.19(10) etc.) are collected in TRIN 4 bottom
            'voucherAccepted' => $form['voucherAccepted'] ?? null,
            'operatorExceptionalCircumstances' => $form['operatorExceptionalCircumstances'] ?? null,
            'operatorExceptionalType' => $form['operatorExceptionalType'] ?? null,
            'minThresholdApplies' => $form['minThresholdApplies'] ?? null,
        ];
    }

    private function extractStep7Compensation(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $compute = (array)($flow['compute'] ?? []);

        return [
            'delayAtFinalMinutes' => $form['delayAtFinalMinutes'] ?? null,
            'compensationBand' => $form['compensationBand'] ?? null,
            'voucherAccepted' => $form['voucherAccepted'] ?? null,
            'operatorExceptionalCircumstances' => $form['operatorExceptionalCircumstances'] ?? null,
            'operatorExceptionalType' => $form['operatorExceptionalType'] ?? null,
            'extraordinary_claimed' => $form['extraordinary_claimed'] ?? null,
            'extraordinary_type' => $form['extraordinary_type'] ?? null,
            'minThresholdApplies' => $form['minThresholdApplies'] ?? null,
            'delayMinEU' => $compute['delayMinEU'] ?? null,
        ];
    }

    /**
     * Minimal Art.12 meta injection (hints only).
     *
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     */
    private function buildArt12Meta(array $flow, array $journeyBasic, array $segments): array
    {
        $ticketNo = $journeyBasic['ticket_no'] ?? null;
        $operator = $journeyBasic['operator'] ?? null;
        $pnrPresent = !empty($ticketNo);
        $opsSet = [];
        foreach ($segments as $sg) { $opCur = $sg['carrier'] ?? null; if ($opCur) { $opsSet[$opCur] = true; } }
        $multiOps = count($opsSet) > 1;
        return [
            'seller_type_operator' => $operator ? 'Ja' : 'Ved ikke',
            'seller_type_agency' => 'Nej',
            'single_booking_reference' => $pnrPresent ? 'Ja' : 'Ved ikke',
            'shared_pnr_scope' => $pnrPresent ? 'Ja' : 'Ved ikke',
            'multi_operator_trip' => $multiOps ? 'Ja' : ($operator ? 'Nej' : 'Ved ikke'),
            'separate_contract_notice' => 'Ved ikke',
            'through_ticket_disclosure' => 'Ved ikke',
        ];
    }

    /**
     * Build Art.9 meta from session/form/auto signals.
     */
    private function buildArt9Meta(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $flowMeta = (array)($flow['meta'] ?? []);
        $autoFlags = (array)($flowMeta['_auto'] ?? []);

        $toYesNoUnknown = function($v): string {
            if (is_bool($v)) { return $v ? 'Ja' : 'Nej'; }
            $s = strtolower(trim((string)$v));
            return match ($s) {
                'ja','yes','y','true','1' => 'Ja',
                'nej','no','n','false','0' => 'Nej',
                'ved ikke','unknown','' => 'unknown',
                default => 'unknown',
            };
        };
        $arrToYesNoUnknown = function($arr) use ($toYesNoUnknown): string {
            if (is_array($arr)) { return !empty($arr) ? 'Ja' : 'unknown'; }
            return $toYesNoUnknown($arr);
        };

        $deniedBoarding = $flowMeta['bike_denied_boarding'] ?? ($form['bike_denied_boarding'] ?? null);
        $refusalProvided = $flowMeta['bike_refusal_reason_provided'] ?? ($form['bike_refusal_reason_provided'] ?? null);
        $refusalType = $flowMeta['bike_refusal_reason_type'] ?? ($form['bike_refusal_reason_type'] ?? null);
        $refusalOtherText = $flowMeta['bike_refusal_reason_other_text'] ?? ($form['bike_refusal_reason_other_text'] ?? null);

        $bikeDeniedReason = 'unknown';
        if ($toYesNoUnknown($deniedBoarding) === 'Ja') {
            $bikeDeniedReason = is_string($refusalType) && trim($refusalType) !== '' ? (string)$refusalType : 'denied_boarding';
        } elseif ($toYesNoUnknown($refusalProvided) === 'Ja' && (empty($refusalType))) {
            $bikeDeniedReason = 'unspecified';
        }

        $rtSeen = $flowMeta['realtime_info_seen'] ?? ($form['realtime_info_seen'] ?? []);
        $pmrDeliveredMeta = $flowMeta['pmr_delivered_status'] ?? null;

        $bikeResMade = $toYesNoUnknown($flowMeta['bike_reservation_made'] ?? ($form['bike_reservation_made'] ?? null));
        $bikeResReq = $toYesNoUnknown($flowMeta['bike_reservation_required'] ?? ($form['bike_reservation_required'] ?? null));
        $bikeDenied = $toYesNoUnknown($deniedBoarding);
        $bikeReasonProvided = $toYesNoUnknown($refusalProvided);
        $bikeReasonType = is_string($refusalType) ? trim($refusalType) : '';

        $pmrUser = $toYesNoUnknown($flowMeta['pmr_user'] ?? ($form['pmr_user'] ?? null));
        $pmrBooked = $toYesNoUnknown($flowMeta['pmr_booked'] ?? ($form['pmr_booked'] ?? null));
        $pmrPromisedMissing = $toYesNoUnknown($flowMeta['pmr_promised_missing'] ?? ($form['pmr_promised_missing'] ?? null));
        $pmrDelivered = (string)($pmrDeliveredMeta ?? ($form['pmrQDelivered'] ?? 'unknown'));

        $preinformed = $toYesNoUnknown($flowMeta['preinformed_disruption'] ?? ($form['preinformed_disruption'] ?? 'unknown'));
        $preinfoChannel = (string)($flowMeta['preinfo_channel'] ?? ($form['preinfo_channel'] ?? ''));
        $realtimeSeen = $arrToYesNoUnknown($rtSeen);

        return [
            // Grouped map for clarity in fixtures (evidence only)
            '_groups' => [
                'bike' => [
                    'art9_1' => [
                        'bike_reservation_made' => $bikeResMade,
                        'bike_reservation_required' => $bikeResReq,
                    ],
                    'art9_2' => [
                        'bike_denied_boarding' => $bikeDenied,
                        'bike_refusal_reason_provided' => $bikeReasonProvided,
                        'bike_refusal_reason_type' => $bikeReasonType,
                        'bike_refusal_reason_other_text' => $refusalOtherText ?? '',
                    ],
                    'art9_3' => [],
                ],
                'pmr' => [
                    'art9_1' => [
                        'pmr_booked' => $pmrBooked,
                    ],
                    'art9_2' => [
                        'pmr_delivered_status' => $pmrDelivered,
                        'pmr_promised_missing' => $pmrPromisedMissing,
                    ],
                    'art9_3' => [],
                ],
                'preinfo' => [
                    'art9_1' => [
                        'preinformed_disruption' => $preinformed,
                        'preinfo_channel' => $preinfoChannel,
                    ],
                    'art9_2' => [
                        'realtime_info_seen' => $realtimeSeen,
                    ],
                    'art9_3' => [
                        'preinfo_channel' => $preinfoChannel,
                        'realtime_info_seen' => $realtimeSeen,
                    ],
                ],
            ],
            // Flat keys used by Art.9 evaluator (evidence only; no pricing/fastest/complaints here)
            'preinformed_disruption' => $preinformed,
            'preinfo_channel' => $preinfoChannel,
            'realtime_info_seen' => $realtimeSeen,
            'pmr_user' => $pmrUser,
            'pmr_booked' => $pmrBooked,
            'pmr_promised_missing' => $pmrPromisedMissing,
            'pmr_delivered_status' => $pmrDelivered,
            'bike_reservation_made' => $bikeResMade,
            'bike_reservation_required' => $bikeResReq,
            'bike_denied_boarding' => $bikeDenied,
            'bike_refusal_reason_provided' => $bikeReasonProvided,
            'bike_refusal_reason_type' => $bikeReasonType,
            'bike_refusal_reason_other_text' => $refusalOtherText ?? '',
        ];
    }
}
