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
        return [
            'id' => 'session_' . date('Ymd_His'),
            'version' => 2,
            'label' => 'Captured wizard session ' . date('c'),
            'journey' => $flow['meta']['journey'] ?? [],
            // Persist selected meta hooks (multi-ticket / guardian etc.) for QA fixtures
            'meta' => $this->extractMeta($flow),
            'wizard' => [
                'step1' => [
                    'travel_state' => $flow['flags']['travel_state'] ?? null,
                ],
                'step2' => [
                    'incident_main' => $flow['incident']['main'] ?? null,
                ],
                'step3_entitlements' => $this->extractStep3($flow),
                'step4_choices' => $this->extractStep4($flow),
                'step5_assistance' => $this->extractStep5($flow),
            ],
            'computeOverrides' => [
                'knownDelayBeforePurchase' => $flow['compute']['knownDelayBeforePurchase'] ?? null,
                'delayAtFinalMinutes' => $flow['compute']['delayAtFinalMinutes'] ?? null,
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
            $base['wizard']['step3_entitlements'] = $base['wizard']['step3_entitlements'] + $extraStep3;
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

        // Art.9 minimal meta mapping from existing session data (Step 3 + AUTO hints)
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
        $refusalType = $flowMeta['bike_refusal_reason_type'] ?? ($form['bike_refusal_reason_type'] ?? null); // e.g., capacity, equipment, weight_dim, other

        $bikeDeniedReason = 'unknown';
        if ($toYesNoUnknown($deniedBoarding) === 'Ja') {
            $bikeDeniedReason = is_string($refusalType) && trim($refusalType) !== '' ? (string)$refusalType : 'denied_boarding';
        } elseif ($toYesNoUnknown($refusalProvided) === 'Ja' && (empty($refusalType))) {
            $bikeDeniedReason = 'unspecified';
        }

        $rtSeen = $flowMeta['realtime_info_seen'] ?? ($form['realtime_info_seen'] ?? []);
        $pmrDeliveredMeta = $flowMeta['pmr_delivered_status'] ?? null;
        $bikeDeniedReasonMeta = $flowMeta['bike_denied_reason'] ?? null;
        $bikeFollowup = $flowMeta['bike_followup_offer'] ?? ($form['bike_followup_offer'] ?? 'unknown');
        $bikeDelayBucket = $flowMeta['bike_delay_bucket'] ?? ($form['bike_delay_bucket'] ?? 'unknown');

        $base['art9_meta'] = [
            // 9(1) – pre-contract
            'preinformed_disruption' => $toYesNoUnknown($flowMeta['preinformed_disruption'] ?? ($form['preinformed_disruption'] ?? 'unknown')),
            // Fasteste rejse – visning og alternativer (pkt. 2)
            'fastest_flag_at_purchase' => $toYesNoUnknown($flowMeta['fastest_flag_at_purchase'] ?? ($form['fastest_flag_at_purchase'] ?? 'unknown')),
            'alts_shown_precontract' => $toYesNoUnknown($flowMeta['alts_shown_precontract'] ?? ($form['alts_shown_precontract'] ?? 'unknown')),
            'language_accessible' => 'unknown',
            'accessibility_format' => 'unknown',
            'multi_channel_information' => 'unknown',
            'accessible_formats_offered' => 'unknown',
            'pmr_user' => $toYesNoUnknown($flowMeta['pmr_user'] ?? ($form['pmr_user'] ?? null)),
            'pmr_booked' => $toYesNoUnknown($flowMeta['pmr_booked'] ?? ($form['pmr_booked'] ?? null)),
            'pmr_promised_missing' => $toYesNoUnknown(($flowMeta['pmr_promised_missing'] ?? ($form['pmr_promised_missing'] ?? null))),
            'bike_reservation_type' => (string)($flowMeta['bike_reservation_type'] ?? ($autoFlags['bike_reservation_type']['value'] ?? 'unknown')),
            'bike_res_required' => $toYesNoUnknown($flowMeta['bike_res_required'] ?? ($autoFlags['bike_res_required']['value'] ?? null)),
            'fare_flex_type' => (string)($flowMeta['fare_flex_type'] ?? ($form['fare_flex_type'] ?? ($autoFlags['fare_flex_type']['value'] ?? 'unknown'))),
            'train_specificity' => (string)($flowMeta['train_specificity'] ?? ($form['train_specificity'] ?? ($autoFlags['train_specificity']['value'] ?? 'unknown'))),
            // Billetpriser – visning af tiers og billigste (pkt. 3)
            'multiple_fares_shown' => $toYesNoUnknown($flowMeta['multiple_fares_shown'] ?? ($form['multiple_fares_shown'] ?? 'unknown')),
            'cheapest_highlighted' => $toYesNoUnknown($flowMeta['cheapest_highlighted'] ?? ($form['cheapest_highlighted'] ?? 'unknown')),
            'fare_class_purchased' => (string)($flowMeta['fare_class_purchased'] ?? ($form['fare_class_purchased'] ?? 'unknown')),
            'berth_seat_type' => (string)($flowMeta['berth_seat_type'] ?? ($form['berth_seat_type'] ?? 'unknown')),
            'promised_facilities' => 'unknown',
            // Art.12 overlap
            'through_ticket_disclosure' => (string)($flowMeta['through_ticket_disclosure'] ?? 'unknown'),
            'single_txn_operator' => (string)($flowMeta['single_txn_operator'] ?? 'unknown'),
            'single_txn_retailer' => (string)($flowMeta['single_txn_retailer'] ?? 'unknown'),
            'separate_contract_notice' => (string)($flowMeta['separate_contract_notice'] ?? 'unknown'),

            // 9(2) – rights & complaints (unknown by default unless set elsewhere)
            'info_on_rights' => 'unknown',
            'rights_notice_displayed' => 'unknown',
            'rights_contact_provided' => 'unknown',
            'complaint_channel_seen' => 'unknown',
            'complaint_already_filed' => 'unknown',
            'complaint_receipt_upload' => 'unknown',
            'submit_via_official_channel' => 'unknown',

            // 9(3) – during disruption / delivery
            'realtime_info_seen' => $arrToYesNoUnknown($rtSeen),
            'info_during_disruption' => 'unknown',
            'station_board_updates' => 'unknown',
            'onboard_announcements' => 'unknown',
            'disruption_updates_frequency' => 'unknown',
            'assistance_contact_visible' => 'unknown',
            'pmr_delivered_status' => (string)($pmrDeliveredMeta ?? ($form['pmrQDelivered'] ?? 'unknown')),
            'bike_denied_reason' => (string)($bikeDeniedReasonMeta ?? $bikeDeniedReason),
            'bike_followup_offer' => $toYesNoUnknown($bikeFollowup),
            'bike_delay_bucket' => (string)$bikeDelayBucket,
            'facilities_delivered_status' => 'unknown',
            'facility_impact_note' => 'unknown',
            'class_delivered_status' => (string)($flowMeta['class_delivered_status'] ?? ($form['class_delivered_status'] ?? 'unknown')),
            'reserved_amenity_delivered' => $toYesNoUnknown($flowMeta['reserved_amenity_delivered'] ?? ($form['reserved_amenity_delivered'] ?? 'unknown')),
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
            'leg_class_purchased' => $f['leg_class_purchased'] ?? [],
            'leg_class_delivered' => $f['leg_class_delivered'] ?? [],
            'leg_downgraded' => $f['leg_downgraded'] ?? [],
            'pmr_user' => $f['pmr_user'] ?? 'Nej',
            'pmr_booked' => $f['pmr_booked'] ?? 'Nej',
            'pmrQBooked' => $f['pmrQBooked'] ?? null,
            'pmrQDelivered' => $f['pmrQDelivered'] ?? null,
            'pmrQPromised' => $f['pmrQPromised'] ?? null,
            'pmr_facility_details' => $f['pmr_facility_details'] ?? null,
            'fare_flex_type' => $f['fare_flex_type'] ?? null,
            'train_specificity' => $f['train_specificity'] ?? null,
            'fare_class_purchased' => $f['fare_class_purchased'] ?? null,
            'berth_seat_type' => $f['berth_seat_type'] ?? null,
            'reserved_amenity_delivered' => $f['reserved_amenity_delivered'] ?? null,
            'class_delivered_status' => $f['class_delivered_status'] ?? null,
            'preinformed_disruption' => $f['preinformed_disruption'] ?? 'Ved ikke',
            'preinfo_channel' => $f['preinfo_channel'] ?? 'Ved ikke',
            'realtime_info_seen' => $f['realtime_info_seen'] ?? [],
            'downgrade_occurred' => $f['downgrade_occurred'] ?? null,
            'downgrade_comp_basis' => $f['downgrade_comp_basis'] ?? null,
            'downgrade_segment_share' => $f['downgrade_segment_share'] ?? null,
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
            'pmr_promised_missing','pmrQAny','pmr_any',
        ] as $k) {
            if (array_key_exists($k, $f)) { $out[$k] = $f[$k]; }
        }
        return $out;
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
}
