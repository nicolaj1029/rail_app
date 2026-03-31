<?php
declare(strict_types=1);

namespace App\Service;

class SessionToFixtureMapper
{
    private const SUPPORTED_CURRENCIES = ['EUR','DKK','SEK','NOK','GBP','CHF','BGN','CZK','HUF','PLN','RON'];
    private const COUNTRY_TO_CURRENCY = [
        'BG' => 'BGN',
        'CZ' => 'CZK',
        'DK' => 'DKK',
        'HU' => 'HUF',
        'PL' => 'PLN',
        'RO' => 'RON',
        'SE' => 'SEK',
        'NO' => 'NOK',
        'GB' => 'GBP',
        'CH' => 'CHF',
    ];

    /**
     * Map flow session to a v2 fixture skeleton.
     *
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function mapSessionToFixture(array $flow): array
    {
        [$journeyBasic, $segments] = $this->buildJourneyParts($flow);
        $step4Journey = $this->mergeStep3($flow);
        $step3Station = $this->extractStep4Station($flow);
        $step5Incident = $this->extractStep5Incident($flow);
        $step6Choices = $this->extractStep6Choices($flow);
        $step7Remedies = $this->extractStep7Remedies($flow);
        $step8Assistance = $this->extractStep8Assistance($flow);
        $step9Downgrade = $this->extractStep9Downgrade($flow);
        $step10Comp = $this->extractStep10Compensation($flow);
        $transportMode = $this->detectTransportMode($flow);
        $contractMeta = $this->buildContractMeta($flow, $journeyBasic, $segments, $transportMode);
        $scopeMeta = $this->buildScopeMeta($flow, $transportMode);
        $incidentMeta = $this->buildIncidentMeta($flow, $transportMode, $step5Incident);
        $ticketExtracts = [];
        $groupedContracts = [];
        $canonical = [];
        try {
            $multimodal = (new MultimodalFlowResolver())->evaluate($flow, false);
            $transportMode = (string)($multimodal['transport_mode'] ?? $transportMode);
            $contractMeta = array_replace($contractMeta, (array)($multimodal['contract_meta'] ?? []));
            $scopeMeta = array_replace($scopeMeta, (array)($multimodal['scope_meta'] ?? []));
            $incidentMeta = array_replace($incidentMeta, (array)($multimodal['incident_meta'] ?? []));
            $ticketExtracts = (array)($multimodal['ticket_extracts'] ?? []);
            $groupedContracts = (array)($multimodal['grouped_contracts'] ?? []);
            $canonical = (array)($multimodal['canonical'] ?? []);
        } catch (\Throwable $e) {
            // Keep local fallback mapping if multimodal evaluation fails.
        }
        $canonical = $this->enrichCanonicalModeFields(
            $canonical,
            $transportMode,
            $step4Journey,
            $step5Incident,
            $step6Choices,
            $step7Remedies,
            $step8Assistance,
            $step10Comp,
            $scopeMeta,
            $contractMeta,
            $incidentMeta
        );

        return [
            'id' => 'session_' . date('Ymd_His'),
            'version' => 2,
            'label' => 'Captured wizard session ' . date('c'),
            'transport_mode' => $transportMode,
            'journey' => $flow['journey'] ?? ($flow['meta']['journey'] ?? []),
            'journeyBasic' => $journeyBasic,
            'segments' => $segments,
            'ticket_extracts' => $ticketExtracts,
            'grouped_contracts' => $groupedContracts,
            'canonical' => $canonical,
            'contract_meta' => $contractMeta,
            'scope_meta' => $scopeMeta,
            'incident_meta' => $incidentMeta,
            // Persist selected meta hooks (multi-ticket / guardian etc.) for QA fixtures
            'meta' => $this->extractMeta($flow),
            'wizard' => [
                // Split flow order: start -> entitlements -> station -> journey -> incident -> choices -> remedies -> assistance -> downgrade -> compensation
                'step1_start' => $this->extractStep1Start($flow),
                'step2_entitlements' => $this->extractStep2Entitlements($flow),
                'step3_station' => $step3Station,
                'step4_journey' => $step4Journey,
                'step5_incident' => $step5Incident,
                'step6_choices' => $step6Choices,
                'step7_remedies' => $step7Remedies,
                'step8_assistance' => $step8Assistance,
                'step9_downgrade' => $step9Downgrade,
                'step10_compensation' => $step10Comp,
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
            $base['wizard']['step4_journey'] = (array)($base['wizard']['step4_journey'] ?? []) + $extraStep3;
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
        $base['contract_meta'] = $this->buildContractMeta($flow, (array)$base['journeyBasic'], (array)$base['segments'], (string)($base['transport_mode'] ?? 'rail'));
        $base['scope_meta'] = $this->buildScopeMeta($flow, (string)($base['transport_mode'] ?? 'rail'));
        $base['incident_meta'] = $this->buildIncidentMeta($flow, (string)($base['transport_mode'] ?? 'rail'), (array)(($base['wizard']['step5_incident'] ?? [])));

        return $base;
    }

    private function detectTransportMode(array $flow): string
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? ($meta['journey'] ?? []));
        $ticketMode = strtolower(trim((string)($form['ticket_upload_mode'] ?? '')));
        $modeSource = strtolower(trim((string)(
            $form['transport_mode_source']
            ?? $meta['transport_mode_source']
            ?? ''
        )));

        $candidate = strtolower(trim((string)(
            $form['transport_mode']
            ?? $meta['transport_mode']
            ?? $journey['transport_mode']
            ?? ''
        )));

        if ($this->isManualTransportModeAuthoritative($ticketMode, $modeSource)
            && in_array($candidate, ['ferry', 'bus', 'air', 'rail'], true)
        ) {
            return $candidate;
        }

        $classifiedMode = (new ModeClassifier())->resolvePrimaryMode($flow);
        if ($classifiedMode !== null) {
            return $classifiedMode;
        }

        return match ($candidate) {
            'ferry', 'bus', 'air', 'rail' => $candidate,
            default => 'rail',
        };
    }

    private function isManualTransportModeAuthoritative(string $ticketMode, string $modeSource): bool
    {
        if (in_array($ticketMode, ['ticketless', 'seasonpass'], true)) {
            return true;
        }

        return $modeSource === 'manual';
    }

    /**
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @return array<string,mixed>
     */
    private function buildContractMeta(array $flow, array $journeyBasic, array $segments, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);

        $sellerChannel = strtolower(trim((string)($form['seller_channel'] ?? '')));
        $singleTxnOperator = $this->normalizeYesNoMachine($form['single_txn_operator'] ?? ($meta['single_txn_operator'] ?? null));
        $singleTxnRetailer = $this->normalizeYesNoMachine($form['single_txn_retailer'] ?? ($meta['single_txn_retailer'] ?? null));
        $throughDisclosure = $this->normalizeDisclosure($form['through_ticket_disclosure'] ?? ($meta['through_ticket_disclosure'] ?? null));
        $separateNotice = $this->normalizeSeparateNotice($form['separate_contract_notice'] ?? ($meta['separate_contract_notice'] ?? null));

        $ticketNo = (string)($journeyBasic['ticket_no'] ?? '');
        $sharedBookingReference = $ticketNo !== '';
        $singleTransaction = match (true) {
            $singleTxnOperator === 'yes', $singleTxnRetailer === 'yes' => true,
            $singleTxnOperator === 'no' && $singleTxnRetailer === 'no' => false,
            default => null,
        };

        $sellerType = match ($sellerChannel) {
            'operator' => 'operator',
            'retailer' => 'ticket_vendor',
            'agency' => 'travel_agent',
            'tour_operator' => 'tour_operator',
            default => ($singleTxnRetailer === 'yes' ? 'ticket_vendor' : ($journeyBasic['operator'] ?? null ? 'operator' : 'unknown')),
        };
        $sellerName = $journeyBasic['operator'] ?? ($auto['operator']['value'] ?? null);

        $manualJourneyStructure = strtolower(trim((string)($form['journey_structure'] ?? '')));
        if (!in_array($manualJourneyStructure, ['single_segment', 'single_mode_connections', 'multimodal_connections'], true)) {
            $manualJourneyStructure = '';
        }
        $journeyStructure = $manualJourneyStructure !== '' ? $manualJourneyStructure : match (true) {
            count($segments) > 1 && $transportMode === 'rail' => 'single_mode_connections',
            count($segments) > 1 => 'multimodal_connections',
            !empty($journeyBasic['dep_station']) || !empty($journeyBasic['arr_station']) => 'single_segment',
            default => 'unknown',
        };
        $originalContractMode = $this->resolveOriginalContractMode($form, $meta, $segments, $transportMode);

        $contractTopology = 'unknown_manual_review';
        if ($journeyStructure === 'single_segment') {
            $contractTopology = 'single_mode_single_contract';
        } elseif ($separateNotice === 'yes' && $throughDisclosure === 'separate') {
            $contractTopology = 'separate_contracts';
        } elseif (($sharedBookingReference || $singleTransaction === true) && $separateNotice === 'no') {
            $contractTopology = $journeyStructure === 'single_mode_connections'
                ? 'protected_single_contract'
                : 'single_multimodal_contract';
        } elseif ($singleTransaction === false) {
            $contractTopology = 'separate_contracts';
        }

        $incidentSegmentMode = strtolower(trim((string)(
            $form['incident_segment_mode']
            ?? $meta['incident_segment_mode']
            ?? $transportMode
        )));
        if (!in_array($incidentSegmentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            $incidentSegmentMode = $transportMode;
        }
        if ($journeyStructure === 'single_segment' && in_array($transportMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            $originalContractMode = $transportMode;
            $incidentSegmentMode = $transportMode;
        }
        $claimTransportMode = $contractTopology === 'separate_contracts'
            ? $incidentSegmentMode
            : ($originalContractMode ?? $transportMode);

        $primaryClaimParty = match ($contractTopology) {
            'separate_contracts' => 'segment_operator',
            'single_mode_single_contract', 'protected_single_contract', 'single_multimodal_contract' => 'seller',
            default => 'manual_review',
        };

        return [
            'seller_type' => $sellerType,
            'seller_name' => $sellerName,
            'shared_booking_reference' => $sharedBookingReference,
            'single_transaction' => $singleTransaction,
            'contract_structure_disclosure' => $throughDisclosure,
            'separate_contract_notice' => $separateNotice,
            'original_contract_mode' => $originalContractMode,
            'journey_structure' => $journeyStructure,
            'contract_topology' => $contractTopology,
            'incident_segment_mode' => $incidentSegmentMode,
            'incident_segment_operator' => $form['incident_segment_operator'] ?? $journeyBasic['operator'] ?? null,
            'primary_claim_party' => $primaryClaimParty,
            'claim_transport_mode' => $claimTransportMode,
            'rights_module' => $incidentSegmentMode,
            'manual_review_required' => $contractTopology === 'unknown_manual_review',
            'same_pnr' => $this->normalizeNullableBool($form['same_pnr'] ?? null),
            'same_booking_reference' => $this->normalizeNullableBool($form['same_booking_reference'] ?? null),
            'same_eticket' => $this->normalizeNullableBool($form['same_eticket'] ?? null),
            'protected_connection_disclosed' => $this->normalizeNullableBool($form['protected_connection_disclosed'] ?? null),
            'self_transfer_notice' => $this->normalizeNullableBool($form['self_transfer_notice'] ?? null),
            'marketing_carrier' => $form['marketing_carrier'] ?? null,
            'operating_carrier' => $form['operating_carrier'] ?? null,
            'air_connection_type' => $form['air_connection_type'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildScopeMeta(array $flow, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);

        return [
            'transport_mode' => $transportMode,
            'service_type' => $form['service_type'] ?? null,
            'departure_from_terminal' => $this->normalizeNullableBool($form['departure_from_terminal'] ?? null),
            'departure_port_in_eu' => $this->normalizeNullableBool($form['departure_port_in_eu'] ?? null),
            'arrival_port_in_eu' => $this->normalizeNullableBool($form['arrival_port_in_eu'] ?? null),
            'carrier_is_eu' => $this->normalizeNullableBool($form['carrier_is_eu'] ?? null),
            'departure_airport_in_eu' => $this->normalizeNullableBool($form['departure_airport_in_eu'] ?? null),
            'arrival_airport_in_eu' => $this->normalizeNullableBool($form['arrival_airport_in_eu'] ?? null),
            'operating_carrier_is_eu' => $this->normalizeNullableBool($form['operating_carrier_is_eu'] ?? null),
            'marketing_carrier_is_eu' => $this->normalizeNullableBool($form['marketing_carrier_is_eu'] ?? null),
            'flight_distance_km' => $this->normalizeNullableInt($form['flight_distance_km'] ?? null),
            'air_distance_band' => $form['air_distance_band'] ?? null,
            'air_delay_threshold_hours' => $this->normalizeNullableInt($form['air_delay_threshold_hours'] ?? null),
            'intra_eu_over_1500' => $this->normalizeNullableBool($form['intra_eu_over_1500'] ?? null),
            'bus_regular_service' => $this->normalizeNullableBool($form['bus_regular_service'] ?? null),
            'boarding_in_eu' => $this->normalizeNullableBool($form['boarding_in_eu'] ?? null),
            'alighting_in_eu' => $this->normalizeNullableBool($form['alighting_in_eu'] ?? null),
            'scheduled_distance_km' => $this->normalizeNullableInt($form['scheduled_distance_km'] ?? null),
            'vessel_passenger_capacity' => $this->normalizeNullableInt($form['vessel_passenger_capacity'] ?? null),
            'vessel_operational_crew' => $this->normalizeNullableInt($form['vessel_operational_crew'] ?? null),
            'route_distance_meters' => $this->normalizeNullableInt($form['route_distance_meters'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $step5Incident
     * @return array<string,mixed>
     */
    private function buildIncidentMeta(array $flow, string $transportMode, array $step5Incident): array
    {
        $form = (array)($flow['form'] ?? []);
        $overnightRequired = $form['overnight_required'] ?? null;
        if ($transportMode === 'ferry') {
            $overnightRequired = $form['ferry_overnight_required']
                ?? ($form['overnight_needed'] ?? ($form['overnight_required'] ?? null));
        }
        $passengerFault = $transportMode === 'ferry'
            ? null
            : $this->normalizeNullableBool($form['passenger_fault'] ?? null);
        $incidentType = strtolower(trim((string)($step5Incident['incident_main'] ?? '')));
        if (!in_array($incidentType, ['delay', 'cancellation', 'denied_boarding', 'missed_connection'], true)) {
            $incidentType = $incidentType !== '' ? $incidentType : null;
        }

        return [
            'transport_mode' => $transportMode,
            'incident_type' => $incidentType,
            'incident_missed' => $this->normalizeYesNoMachine($step5Incident['incident_missed'] ?? null),
            'expected_delay_60' => $this->normalizeNullableBool($step5Incident['expected_delay_60'] ?? null),
            'delay_already_60' => $this->normalizeNullableBool($step5Incident['delay_already_60'] ?? null),
            'missed_expected_delay_60' => $this->normalizeNullableBool($step5Incident['missed_expected_delay_60'] ?? null),
            'national_delay_minutes' => $this->normalizeNullableInt($step5Incident['national_delay_minutes'] ?? null),
            'preinformed_disruption' => $this->normalizeNullableBool($step5Incident['preinformed_disruption'] ?? null),
            'preinfo_channel' => $step5Incident['preinfo_channel'] ?? null,
            'realtime_info_seen' => $step5Incident['realtime_info_seen'] ?? null,
            'operator_exceptional_circumstances' => $this->normalizeNullableBool($step5Incident['operatorExceptionalCircumstances'] ?? null),
            'operator_exceptional_type' => $step5Incident['operatorExceptionalType'] ?? null,
            'minimum_threshold_applies' => $this->normalizeNullableBool($step5Incident['minThresholdApplies'] ?? null),
            'arrival_delay_minutes' => $this->normalizeNullableInt($form['arrival_delay_minutes'] ?? null),
            'scheduled_journey_duration_minutes' => $this->normalizeNullableInt($form['scheduled_journey_duration_minutes'] ?? null),
            'expected_departure_delay_90' => $this->normalizeNullableBool($form['expected_departure_delay_90'] ?? null),
            'actual_departure_delay_90' => $this->normalizeNullableBool($form['actual_departure_delay_90'] ?? null),
            'ferry_art16_notice_within_30min' => $this->normalizeNullableBool($form['ferry_art16_notice_within_30min'] ?? null),
            'overnight_required' => $this->normalizeNullableBool($overnightRequired),
            'informed_before_purchase' => $this->normalizeNullableBool($form['informed_before_purchase'] ?? null),
            'passenger_fault' => $passengerFault,
            'weather_safety' => $this->normalizeNullableBool($form['weather_safety'] ?? null),
            'extraordinary_circumstances' => $this->normalizeNullableBool($form['extraordinary_circumstances'] ?? null),
            'open_ticket_without_departure_time' => $this->normalizeNullableBool($form['open_ticket_without_departure_time'] ?? null),
            'season_ticket' => array_key_exists('season_ticket', $form)
                ? $this->normalizeNullableBool($form['season_ticket'])
                : (($form['ticket_upload_mode'] ?? null) === 'seasonpass'),
            'delay_departure_band' => $form['delay_departure_band'] ?? null,
            'planned_duration_band' => $form['planned_duration_band'] ?? null,
            'vehicle_breakdown' => $this->normalizeNullableBool($form['vehicle_breakdown'] ?? null),
            'delay_minutes_departure' => $this->normalizeNullableInt($form['delay_minutes_departure'] ?? null),
            'delay_minutes_arrival' => $this->normalizeNullableInt($form['delay_minutes_arrival'] ?? null),
            'boarding_denied' => $this->normalizeNullableBool($form['boarding_denied'] ?? null),
            'voluntary_denied_boarding' => $this->normalizeNullableBool($form['voluntary_denied_boarding'] ?? null),
            'reroute_offered' => $this->normalizeNullableBool($form['reroute_offered'] ?? null),
            'cancellation_notice_band' => $form['cancellation_notice_band'] ?? null,
            'reroute_departure_band' => $form['reroute_departure_band'] ?? null,
            'reroute_arrival_band' => $form['reroute_arrival_band'] ?? null,
            'refund_offered' => $this->normalizeNullableBool($form['refund_offered'] ?? null),
            'hotel_required' => $this->normalizeNullableBool($form['hotel_required'] ?? null),
            'hotel_offered' => $this->normalizeNullableBool($form['hotel_offered'] ?? null),
            'meal_offered' => $this->normalizeNullableBool($form['meal_offered'] ?? null),
            'pmr_user' => $this->normalizeNullableBool($form['pmr_user'] ?? null),
            'ferry_pmr_companion' => $this->normalizeNullableBool($form['ferry_pmr_companion'] ?? null),
            'ferry_pmr_service_dog' => $this->normalizeNullableBool($form['ferry_pmr_service_dog'] ?? null),
            'ferry_pmr_notice_48h' => $this->normalizeNullableBool($form['ferry_pmr_notice_48h'] ?? null),
            'ferry_pmr_met_checkin_time' => $this->normalizeNullableBool($form['ferry_pmr_met_checkin_time'] ?? null),
            'ferry_pmr_assistance_delivered' => is_string($form['ferry_pmr_assistance_delivered'] ?? null) ? (string)$form['ferry_pmr_assistance_delivered'] : null,
            'ferry_pmr_boarding_refused' => $this->normalizeNullableBool($form['ferry_pmr_boarding_refused'] ?? null),
            'ferry_pmr_refusal_basis' => is_string($form['ferry_pmr_refusal_basis'] ?? null) ? (string)$form['ferry_pmr_refusal_basis'] : null,
            'ferry_pmr_reason_given' => $this->normalizeNullableBool($form['ferry_pmr_reason_given'] ?? null),
            'ferry_pmr_alternative_transport_offered' => $this->normalizeNullableBool($form['ferry_pmr_alternative_transport_offered'] ?? null),
            'bus_pmr_companion' => $this->normalizeNullableBool($form['bus_pmr_companion'] ?? null),
            'bus_pmr_notice_36h' => $this->normalizeNullableBool($form['bus_pmr_notice_36h'] ?? null),
            'bus_pmr_met_terminal_time' => $this->normalizeNullableBool($form['bus_pmr_met_terminal_time'] ?? null),
            'bus_pmr_special_seating_notified' => $this->normalizeNullableBool($form['bus_pmr_special_seating_notified'] ?? null),
            'bus_pmr_assistance_delivered' => is_string($form['bus_pmr_assistance_delivered'] ?? null) ? (string)$form['bus_pmr_assistance_delivered'] : null,
            'bus_pmr_boarding_refused' => $this->normalizeNullableBool($form['bus_pmr_boarding_refused'] ?? null),
            'bus_pmr_refusal_basis' => is_string($form['bus_pmr_refusal_basis'] ?? null) ? (string)$form['bus_pmr_refusal_basis'] : null,
            'bus_pmr_reason_given' => $this->normalizeNullableBool($form['bus_pmr_reason_given'] ?? null),
            'bus_pmr_alternative_transport_offered' => $this->normalizeNullableBool($form['bus_pmr_alternative_transport_offered'] ?? null),
            'protected_connection_missed' => $this->normalizeNullableBool($form['protected_connection_missed'] ?? null),
            'connection_protection_basis' => is_string($form['connection_protection_basis'] ?? null) ? (string)$form['connection_protection_basis'] : null,
            'reroute_arrival_delay_minutes' => $this->normalizeNullableInt($form['reroute_arrival_delay_minutes'] ?? null),
            'overbooking' => $this->normalizeNullableBool($form['overbooking'] ?? null),
            'carrier_offered_choice' => $this->normalizeNullableBool($form['carrier_offered_choice'] ?? null),
            'severe_weather' => $this->normalizeNullableBool($form['severe_weather'] ?? null),
            'major_natural_disaster' => $this->normalizeNullableBool($form['major_natural_disaster'] ?? null),
            'missed_connection_due_to_delay' => $this->normalizeNullableBool($form['missed_connection_due_to_delay'] ?? null),
        ];
    }

    private function normalizeYesNoMachine(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            'ja', 'yes', 'y', '1', 'true', 'gennemgående' => 'yes',
            'nej', 'no', 'n', '0', 'false', 'særskilte' => 'no',
            '', 'unknown', 'ved ikke', 'unclear' => null,
            default => in_array($normalized, ['yes', 'no'], true) ? $normalized : null,
        };
    }

    private function normalizeDisclosure(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            'gennemgående', 'bundled' => 'bundled',
            'særskilte', 'separate' => 'separate',
            'yes', 'ja' => 'bundled',
            'no', 'nej' => 'none',
            '', 'unknown', 'ved ikke' => 'unknown',
            default => 'unknown',
        };
    }

    private function normalizeSeparateNotice(mixed $value): string
    {
        return match ($this->normalizeYesNoMachine($value)) {
            'yes' => 'yes',
            'no' => 'no',
            default => 'unclear',
        };
    }

    private function normalizeNullableBool(mixed $value): ?bool
    {
        return match ($this->normalizeYesNoMachine($value)) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return null;
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
            // Season/period pass (Art. 19(2)) + minimal ticket-type hooks for QA fixtures.
            'season_pass',
            'season_pass_files',
            'fare_flex_type',
            'train_specificity',
            // Downgrade: keep per-ticket bundles so fixtures can represent multi-ticket correctly.
            'saved_leg_by_file',
            'downgrade_by_file',
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
            'pmr_companion' => $f['pmr_companion'] ?? 'unknown',
            'pmr_service_dog' => $f['pmr_service_dog'] ?? 'unknown',
            'unaccompanied_minor' => $f['unaccompanied_minor'] ?? 'unknown',
            'ferry_pmr_companion' => $f['ferry_pmr_companion'] ?? 'unknown',
            'ferry_pmr_service_dog' => $f['ferry_pmr_service_dog'] ?? 'unknown',
            'ferry_pmr_notice_48h' => $f['ferry_pmr_notice_48h'] ?? 'unknown',
            'ferry_pmr_met_checkin_time' => $f['ferry_pmr_met_checkin_time'] ?? 'unknown',
            'ferry_pmr_assistance_delivered' => $f['ferry_pmr_assistance_delivered'] ?? 'unknown',
            'ferry_pmr_boarding_refused' => $f['ferry_pmr_boarding_refused'] ?? 'unknown',
            'ferry_pmr_refusal_basis' => $f['ferry_pmr_refusal_basis'] ?? null,
            'ferry_pmr_reason_given' => $f['ferry_pmr_reason_given'] ?? 'unknown',
            'ferry_pmr_alternative_transport_offered' => $f['ferry_pmr_alternative_transport_offered'] ?? 'unknown',
            'bus_pmr_companion' => $f['bus_pmr_companion'] ?? 'unknown',
            'bus_pmr_notice_36h' => $f['bus_pmr_notice_36h'] ?? 'unknown',
            'bus_pmr_met_terminal_time' => $f['bus_pmr_met_terminal_time'] ?? 'unknown',
            'bus_pmr_special_seating_notified' => $f['bus_pmr_special_seating_notified'] ?? 'unknown',
            'bus_pmr_assistance_delivered' => $f['bus_pmr_assistance_delivered'] ?? 'unknown',
            'bus_pmr_boarding_refused' => $f['bus_pmr_boarding_refused'] ?? 'unknown',
            'bus_pmr_refusal_basis' => $f['bus_pmr_refusal_basis'] ?? null,
            'bus_pmr_reason_given' => $f['bus_pmr_reason_given'] ?? 'unknown',
            'bus_pmr_alternative_transport_offered' => $f['bus_pmr_alternative_transport_offered'] ?? 'unknown',
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
            'pmr_companion','pmr_service_dog','unaccompanied_minor',
            'ferry_pmr_companion','ferry_pmr_service_dog','ferry_pmr_notice_48h','ferry_pmr_met_checkin_time',
            'ferry_pmr_assistance_delivered','ferry_pmr_boarding_refused','ferry_pmr_refusal_basis',
            'ferry_pmr_reason_given','ferry_pmr_alternative_transport_offered',
            'bus_pmr_companion','bus_pmr_notice_36h','bus_pmr_met_terminal_time','bus_pmr_special_seating_notified',
            'bus_pmr_assistance_delivered','bus_pmr_boarding_refused','bus_pmr_refusal_basis',
            'bus_pmr_reason_given','bus_pmr_alternative_transport_offered',
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
        $base = $this->normalizeStep3($base);
        $transportMode = $this->detectTransportMode($flow);
        $modeFields = [];
        if ($transportMode === 'bus') {
            $modeFields['bus'] = [
                'pmr_user' => $base['pmr_user'] ?? null,
                'pmr_companion' => $base['bus_pmr_companion'] ?? null,
                'notice_36h' => $base['bus_pmr_notice_36h'] ?? null,
                'met_terminal_time' => $base['bus_pmr_met_terminal_time'] ?? null,
                'special_seating_notified' => $base['bus_pmr_special_seating_notified'] ?? null,
                'assistance_delivered' => $base['bus_pmr_assistance_delivered'] ?? null,
                'boarding_refused' => $base['bus_pmr_boarding_refused'] ?? null,
                'refusal_basis' => $base['bus_pmr_refusal_basis'] ?? null,
                'reason_given' => $base['bus_pmr_reason_given'] ?? null,
                'alternative_transport_offered' => $base['bus_pmr_alternative_transport_offered'] ?? null,
            ];
        } elseif ($transportMode === 'ferry') {
            $modeFields['ferry'] = [
                'pmr_user' => $base['pmr_user'] ?? null,
                'pmr_companion' => $base['ferry_pmr_companion'] ?? null,
                'service_dog' => $base['ferry_pmr_service_dog'] ?? null,
                'notice_48h' => $base['ferry_pmr_notice_48h'] ?? null,
                'met_checkin_time' => $base['ferry_pmr_met_checkin_time'] ?? null,
                'assistance_delivered' => $base['ferry_pmr_assistance_delivered'] ?? null,
                'boarding_refused' => $base['ferry_pmr_boarding_refused'] ?? null,
                'refusal_basis' => $base['ferry_pmr_refusal_basis'] ?? null,
                'reason_given' => $base['ferry_pmr_reason_given'] ?? null,
                'alternative_transport_offered' => $base['ferry_pmr_alternative_transport_offered'] ?? null,
            ];
        } elseif ($transportMode === 'air') {
            $modeFields['air'] = [
                'pmr_user' => $base['pmr_user'] ?? null,
                'pmr_companion' => $base['pmr_companion'] ?? null,
                'service_dog' => $base['pmr_service_dog'] ?? null,
                'unaccompanied_minor' => $base['unaccompanied_minor'] ?? null,
            ];
        } elseif ($transportMode === 'rail') {
            $modeFields['rail'] = [
                'pmr_user' => $base['pmr_user'] ?? null,
                'pmr_booked' => $base['pmr_booked'] ?? null,
                'pmr_promised_missing' => $base['pmr_promised_missing'] ?? null,
                'pmr_companion' => $base['pmr_companion'] ?? null,
                'service_dog' => $base['pmr_service_dog'] ?? null,
                'bike_was_present' => $base['bike_was_present'] ?? null,
                'bike_denied_boarding' => $base['bike_denied_boarding'] ?? null,
                'bike_refusal_reason_provided' => $base['bike_refusal_reason_provided'] ?? null,
                'bike_refusal_reason_type' => $base['bike_refusal_reason_type'] ?? null,
            ];
        }
        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
        ] + $base;
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
            'pmr_user','pmr_booked','pmr_promised_missing','pmr_companion','pmr_service_dog','unaccompanied_minor',
            'ferry_pmr_companion','ferry_pmr_service_dog','ferry_pmr_notice_48h','ferry_pmr_met_checkin_time',
            'ferry_pmr_boarding_refused','ferry_pmr_reason_given','ferry_pmr_alternative_transport_offered',
            'bus_pmr_companion','bus_pmr_notice_36h','bus_pmr_met_terminal_time','bus_pmr_special_seating_notified',
            'bus_pmr_boarding_refused','bus_pmr_reason_given','bus_pmr_alternative_transport_offered',
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
            'carrier_offered_choice' => $f['carrier_offered_choice'] ?? null,
            'air_article8_choice_offered' => $f['air_article8_choice_offered'] ?? null,
            'offer_provided' => $f['offer_provided'] ?? null,
            'ferry_offer_provided' => $f['ferry_offer_provided'] ?? null,
            'self_purchase_reason' => $f['self_purchase_reason'] ?? null,
            'air_self_arranged_reroute' => $f['air_self_arranged_reroute'] ?? null,
            'air_self_arranged_reroute_reason' => $f['air_self_arranged_reroute_reason'] ?? null,
            'air_airline_confirmed_self_arranged_solution' => $f['air_airline_confirmed_self_arranged_solution'] ?? null,
            'air_refund_scope' => $f['air_refund_scope'] ?? null,
            'air_return_to_first_departure_point' => $f['air_return_to_first_departure_point'] ?? null,
            'air_alternative_airport_transfer_needed' => $f['air_alternative_airport_transfer_needed'] ?? null,
            'air_alternative_airport_transfer_amount' => $f['air_alternative_airport_transfer_amount'] ?? null,
            'air_alternative_airport_transfer_currency' => $f['air_alternative_airport_transfer_currency'] ?? null,
            'ferry_self_arranged_solution' => $f['ferry_self_arranged_solution'] ?? null,
            'ferry_self_arranged_solution_type' => $f['ferry_self_arranged_solution_type'] ?? null,
            'ferry_self_arranged_reason' => $f['ferry_self_arranged_reason'] ?? null,
            'ferry_first_usable_solution_timing' => $f['ferry_first_usable_solution_timing'] ?? null,
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
            'is_stranded' => $f['is_stranded'] ?? null,
            'stranded_location' => $f['stranded_location'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
            // TRIN 5 resolution endpoint (Art.20): where did you end up after being stranded?
            'a20_where_ended' => $f['a20_where_ended'] ?? ($f['assistance_alt_to_destination'] ?? null),
            'a20_arrival_station' => $f['a20_arrival_station'] ?? ($f['assistance_alt_arrival_station'] ?? null),
            'a20_arrival_station_other' => $f['a20_arrival_station_other'] ?? ($f['assistance_alt_arrival_station_other'] ?? null),
            'a20_where_ended_assumed' => $f['a20_where_ended_assumed'] ?? ($f['assistance_alt_to_destination_assumed'] ?? null),
            // Derived handoff station for TRIN 6 (when a20_where_ended != final_destination)
            'handoff_station' => $f['handoff_station'] ?? null,
            'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,
            // Art.20(3) station-specific
            'a20_3_solution_offered' => $f['a20_3_solution_offered'] ?? null,
            'a20_3_solution_type' => $f['a20_3_solution_type'] ?? null,
            'a20_3_self_paid' => $f['a20_3_self_paid'] ?? null,
            'a20_3_self_arranged_type' => $f['a20_3_self_arranged_type'] ?? null,
            'a20_3_self_paid_direction' => $f['a20_3_self_paid_direction'] ?? null,
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
            'meal_self_paid_amount_items' => $f['meal_self_paid_amount_items'] ?? null,
            'meal_self_paid_receipt_items' => $f['meal_self_paid_receipt_items'] ?? null,
            'hotel_self_paid_nights' => $f['hotel_self_paid_nights'] ?? null,
            'hotel_self_paid_amount' => $f['hotel_self_paid_amount'] ?? null,
            'hotel_self_paid_currency' => $f['hotel_self_paid_currency'] ?? null,
            'hotel_self_paid_receipt' => $f['hotel_self_paid_receipt'] ?? null,
            'hotel_self_paid_amount_items' => $f['hotel_self_paid_amount_items'] ?? null,
            'hotel_self_paid_nights_items' => $f['hotel_self_paid_nights_items'] ?? null,
            'hotel_self_paid_receipt_items' => $f['hotel_self_paid_receipt_items'] ?? null,
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
            'is_stranded_trin5' => $f['is_stranded_trin5'] ?? null,
            'maps_opt_in_trin5' => $f['maps_opt_in_trin5'] ?? null,
            'stranded_location' => $f['stranded_location'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
            // TRIN 5 resolution endpoint ("Hvor endte du?") – canonical names.
            // Keep legacy fallbacks so old sessions can still be dumped as fixtures.
            'a20_where_ended' => $f['a20_where_ended'] ?? ($f['assistance_alt_to_destination'] ?? null),
            'a20_arrival_station' => $f['a20_arrival_station'] ?? ($f['assistance_alt_arrival_station'] ?? null),
            'a20_arrival_station_other' => $f['a20_arrival_station_other'] ?? ($f['assistance_alt_arrival_station_other'] ?? null),
            'a20_where_ended_assumed' => $f['a20_where_ended_assumed'] ?? ($f['assistance_alt_to_destination_assumed'] ?? null),
            // Derived in FlowController::choices() when a20_where_ended indicates a station.
            'handoff_station' => $f['handoff_station'] ?? null,

            // Self-paid transport while blocked on track
            'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,

            // (legacy: assistance_alt_to_destination_assumed is replaced by a20_where_ended_assumed)
        ];
    }

    /**
     * TRIN 6 (remedies.php): Art.18 refusion/omlaegning.
     */
    private function extractStep6Remedies(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        $transportMode = $this->detectTransportMode($flow);
        $railValue = static function (array $form, string $canonical) use ($transportMode) {
            return $transportMode === 'rail' ? ($form[$canonical] ?? null) : null;
        };
        $busValue = static function (array $form, string $alias, string $canonical) use ($transportMode) {
            $aliasValue = $form[$alias] ?? null;
            if ($transportMode !== 'bus') {
                return $aliasValue;
            }
            if ($aliasValue === null || $aliasValue === '' || $aliasValue === 'irrelevant') {
                return $form[$canonical] ?? null;
            }
            return $aliasValue;
        };
        $modeFields = [];
        if ($transportMode === 'bus') {
            $modeFields['bus'] = [
                'remedy_choice' => $busValue($f, 'bus_remedy_choice', 'remedyChoice'),
                'refund_requested' => (($f['bus_refund_requested'] ?? '') !== '' ? ($f['bus_refund_requested'] ?? null) : (((string)($f['remedyChoice'] ?? '') === 'refund_return') ? 'yes' : 'no')),
                'reroute_choice' => (($f['bus_reroute_choice'] ?? '') !== '' ? ($f['bus_reroute_choice'] ?? null) : (((string)($f['remedyChoice'] ?? '') === 'reroute_soonest') ? 'reroute_soonest' : '')),
                'carrier_offered_choice' => $busValue($f, 'bus_carrier_offered_choice', 'carrier_offered_choice'),
                'self_arranged_solution' => $f['bus_self_arranged_solution'] ?? null,
                'self_arranged_solution_type' => $f['bus_self_arranged_solution_type'] ?? null,
                'self_arranged_reason' => $f['bus_self_arranged_reason'] ?? null,
                'return_to_departure_stop_expense' => $busValue($f, 'bus_return_to_departure_stop_expense', 'return_to_origin_expense'),
                'return_to_departure_stop_amount' => $busValue($f, 'bus_return_to_departure_stop_amount', 'return_to_origin_amount'),
                'return_to_departure_stop_currency' => $busValue($f, 'bus_return_to_departure_stop_currency', 'return_to_origin_currency'),
            ];
        } elseif ($transportMode === 'ferry') {
            $modeFields['ferry'] = [
                'remedy_choice' => $f['ferry_remedy_choice'] ?? null,
                'refund_requested' => $f['ferry_refund_requested'] ?? null,
                'reroute_choice' => $f['ferry_reroute_choice'] ?? null,
                'offer_provided' => $f['ferry_offer_provided'] ?? null,
                'first_usable_solution_timing' => $f['ferry_first_usable_solution_timing'] ?? null,
                'self_arranged_solution' => $f['ferry_self_arranged_solution'] ?? null,
                'self_arranged_solution_type' => $f['ferry_self_arranged_solution_type'] ?? null,
                'self_arranged_reason' => $f['ferry_self_arranged_reason'] ?? null,
                'return_to_departure_port_expense' => $f['ferry_return_to_departure_port_expense'] ?? null,
                'return_to_departure_port_amount' => $f['ferry_return_to_departure_port_amount'] ?? null,
                'return_to_departure_port_currency' => $f['ferry_return_to_departure_port_currency'] ?? null,
            ];
        } elseif ($transportMode === 'air') {
            $modeFields['air'] = [
                'article8_choice_offered' => $f['air_article8_choice_offered'] ?? null,
                'self_arranged_reroute' => $f['air_self_arranged_reroute'] ?? null,
                'self_arranged_reroute_reason' => $f['air_self_arranged_reroute_reason'] ?? null,
                'airline_confirmed_self_arranged_solution' => $f['air_airline_confirmed_self_arranged_solution'] ?? null,
                'refund_scope' => $f['air_refund_scope'] ?? null,
                'return_to_first_departure_point' => $f['air_return_to_first_departure_point'] ?? null,
                'alternative_airport_transfer_needed' => $f['air_alternative_airport_transfer_needed'] ?? null,
                'alternative_airport_transfer_amount' => $f['air_alternative_airport_transfer_amount'] ?? null,
                'alternative_airport_transfer_currency' => $f['air_alternative_airport_transfer_currency'] ?? null,
            ];
        } elseif ($transportMode === 'rail') {
            $modeFields['rail'] = [
                'remedy_choice' => $f['remedyChoice'] ?? null,
                'reroute_info_within_100min' => $f['reroute_info_within_100min'] ?? null,
                'offer_provided' => $f['offer_provided'] ?? null,
                'self_arranged_solution' => $f['self_purchased_new_ticket'] ?? null,
                'operator_confirmed_self_arranged_solution' => $f['self_purchase_approved_by_operator'] ?? null,
                'self_arranged_reason' => $f['self_purchase_reason'] ?? null,
                'return_to_origin_expense' => $f['return_to_origin_expense'] ?? null,
                'return_to_origin_amount' => $f['return_to_origin_amount'] ?? null,
                'return_to_origin_currency' => $f['return_to_origin_currency'] ?? null,
            ];
        }
        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
            'art18_expected_delay_60' => $f['art18_expected_delay_60'] ?? null,
            'remedyChoice' => $f['remedyChoice'] ?? null,
            'rail_remedy_choice' => $railValue($f, 'remedyChoice'),
            'ferry_remedy_choice' => $f['ferry_remedy_choice'] ?? null,
            'ferry_refund_requested' => $f['ferry_refund_requested'] ?? null,
            'ferry_reroute_choice' => $f['ferry_reroute_choice'] ?? null,
            'bus_remedy_choice' => $busValue($f, 'bus_remedy_choice', 'remedyChoice'),
            'bus_refund_requested' => $transportMode === 'bus'
                ? (($f['bus_refund_requested'] ?? '') !== '' ? ($f['bus_refund_requested'] ?? null) : (((string)($f['remedyChoice'] ?? '') === 'refund_return') ? 'yes' : 'no'))
                : ($f['bus_refund_requested'] ?? null),
            'bus_reroute_choice' => $transportMode === 'bus'
                ? (($f['bus_reroute_choice'] ?? '') !== '' ? ($f['bus_reroute_choice'] ?? null) : (((string)($f['remedyChoice'] ?? '') === 'reroute_soonest') ? 'reroute_soonest' : ''))
                : ($f['bus_reroute_choice'] ?? null),

            // Reroute now/later
            // TRIN 6 station context (separate from TRIN 5)
            'a18_from_station' => $f['a18_from_station'] ?? null,
            'a18_from_station_other' => $f['a18_from_station_other'] ?? null,
            // TRIN 6 refund context
            'a18_return_to_station' => $f['a18_return_to_station'] ?? null,
            'a18_return_to_station_other' => $f['a18_return_to_station_other'] ?? null,
            'a18_reroute_mode' => $f['a18_reroute_mode'] ?? null,
            'a18_reroute_endpoint' => $f['a18_reroute_endpoint'] ?? null,
            'a18_reroute_arrival_station' => $f['a18_reroute_arrival_station'] ?? null,
            'a18_reroute_arrival_station_other' => $f['a18_reroute_arrival_station_other'] ?? null,
            'carrier_offered_choice' => $f['carrier_offered_choice'] ?? null,
            'bus_carrier_offered_choice' => $busValue($f, 'bus_carrier_offered_choice', 'carrier_offered_choice'),

            'reroute_same_conditions_soonest' => $f['reroute_same_conditions_soonest'] ?? null,
            'reroute_later_at_choice' => $f['reroute_later_at_choice'] ?? null,
            'reroute_info_within_100min' => $transportMode === 'air' ? null : ($f['reroute_info_within_100min'] ?? null),
            'rail_reroute_info_within_100min' => $railValue($f, 'reroute_info_within_100min'),
            'self_purchased_new_ticket' => $f['self_purchased_new_ticket'] ?? null,
            'rail_self_arranged_solution' => $railValue($f, 'self_purchased_new_ticket'),
            'self_purchase_approved_by_operator' => $f['self_purchase_approved_by_operator'] ?? null,
            'rail_operator_confirmed_self_arranged_solution' => $railValue($f, 'self_purchase_approved_by_operator'),
            'self_purchase_reason' => $f['self_purchase_reason'] ?? null,
            'rail_self_arranged_reason' => $railValue($f, 'self_purchase_reason'),
            'bus_self_arranged_solution' => $f['bus_self_arranged_solution'] ?? null,
            'bus_self_arranged_solution_type' => $f['bus_self_arranged_solution_type'] ?? null,
            'bus_self_arranged_reason' => $f['bus_self_arranged_reason'] ?? null,
            'air_article8_choice_offered' => $f['air_article8_choice_offered'] ?? null,
            'air_self_arranged_reroute' => $f['air_self_arranged_reroute'] ?? null,
            'air_self_arranged_reroute_reason' => $f['air_self_arranged_reroute_reason'] ?? null,
            'air_airline_confirmed_self_arranged_solution' => $f['air_airline_confirmed_self_arranged_solution'] ?? null,
            'air_refund_scope' => $f['air_refund_scope'] ?? null,
            'air_return_to_first_departure_point' => $f['air_return_to_first_departure_point'] ?? null,
            'air_alternative_airport_transfer_needed' => $f['air_alternative_airport_transfer_needed'] ?? null,
            'air_alternative_airport_transfer_amount' => $f['air_alternative_airport_transfer_amount'] ?? null,
            'air_alternative_airport_transfer_currency' => $f['air_alternative_airport_transfer_currency'] ?? null,
            'offer_provided' => $f['offer_provided'] ?? null,
            'rail_offer_provided' => $railValue($f, 'offer_provided'),
            'ferry_offer_provided' => $f['ferry_offer_provided'] ?? null,
            'ferry_self_arranged_solution' => $f['ferry_self_arranged_solution'] ?? null,
            'ferry_self_arranged_solution_type' => $f['ferry_self_arranged_solution_type'] ?? null,
            'ferry_self_arranged_reason' => $f['ferry_self_arranged_reason'] ?? null,
            'ferry_first_usable_solution_timing' => $f['ferry_first_usable_solution_timing'] ?? null,
            'reroute_extra_costs' => $f['reroute_extra_costs'] ?? null,
            'reroute_extra_costs_type' => $f['reroute_extra_costs_type'] ?? null,
            'reroute_extra_costs_amount' => $f['reroute_extra_costs_amount'] ?? null,
            'reroute_extra_costs_currency' => $f['reroute_extra_costs_currency'] ?? null,
            'reroute_extra_costs_description' => $f['reroute_extra_costs_description'] ?? null,
            'reroute_extra_costs_receipt' => $f['reroute_extra_costs_receipt'] ?? null,
            'reroute_later_ticket_upload' => $f['reroute_later_ticket_upload'] ?? null,
            'reroute_later_ticket_file' => $f['reroute_later_ticket_file'] ?? null,

            // Refund + return-to-origin
            'trip_cancelled_return_to_origin' => $f['trip_cancelled_return_to_origin'] ?? null,
            'return_to_origin_expense' => $f['return_to_origin_expense'] ?? null,
            'return_to_origin_amount' => $f['return_to_origin_amount'] ?? null,
            'return_to_origin_currency' => $f['return_to_origin_currency'] ?? null,
            'rail_return_to_origin_expense' => $railValue($f, 'return_to_origin_expense'),
            'rail_return_to_origin_amount' => $railValue($f, 'return_to_origin_amount'),
            'rail_return_to_origin_currency' => $railValue($f, 'return_to_origin_currency'),
            'return_to_origin_receipt' => $f['return_to_origin_receipt'] ?? null,
            'bus_return_to_departure_stop_expense' => $busValue($f, 'bus_return_to_departure_stop_expense', 'return_to_origin_expense'),
            'bus_return_to_departure_stop_amount' => $busValue($f, 'bus_return_to_departure_stop_amount', 'return_to_origin_amount'),
            'bus_return_to_departure_stop_currency' => $busValue($f, 'bus_return_to_departure_stop_currency', 'return_to_origin_currency'),
            'ferry_return_to_departure_port_expense' => $f['ferry_return_to_departure_port_expense'] ?? null,
            'ferry_return_to_departure_port_amount' => $f['ferry_return_to_departure_port_amount'] ?? null,
            'ferry_return_to_departure_port_currency' => $f['ferry_return_to_departure_port_currency'] ?? null,

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
        $f = (array)($flow['form'] ?? []);
        $transportMode = $this->detectTransportMode($flow);
        $railValue = static function (array $form, string $canonical) use ($transportMode) {
            return $transportMode === 'rail' ? ($form[$canonical] ?? null) : null;
        };
        $busValue = static function (array $form, string $alias, string $canonical) use ($transportMode) {
            $aliasValue = $form[$alias] ?? null;
            if ($transportMode !== 'bus') {
                return $aliasValue;
            }
            if ($aliasValue === null || $aliasValue === '' || $aliasValue === 'irrelevant') {
                return $form[$canonical] ?? null;
            }
            return $aliasValue;
        };
        $priceHints = $flow['meta']['price_hints'] ?? null;
        if ($transportMode === 'rail') {
            $rawPriceHints = is_array($priceHints) ? $priceHints : [];
            $priceHints = [
                'model' => 'rail_engine_ranges',
                'meals' => $rawPriceHints['meals'] ?? null,
                'hotelPerNight' => $rawPriceHints['hotelPerNight'] ?? null,
                'taxi' => $rawPriceHints['taxi'] ?? null,
                'altTransport' => $rawPriceHints['altTransport'] ?? null,
                'upgradeFirstClass' => $rawPriceHints['upgradeFirstClass'] ?? null,
            ];
        } elseif ($transportMode === 'bus') {
            $delayMinutes = 0;
            $delayRaw = $f['delayAtFinalMinutes'] ?? ($flow['compute']['delayAtFinalMinutes'] ?? 0);
            if (is_numeric($delayRaw)) {
                $delayMinutes = max(0, (int)$delayRaw);
            }
            $delayHours = max(1, (int)ceil($delayMinutes / 60));
            $distanceKm = is_numeric($f['scheduled_distance_km'] ?? null)
                ? (float)$f['scheduled_distance_km']
                : (is_numeric($flow['form']['scheduled_distance_km'] ?? null) ? (float)$flow['form']['scheduled_distance_km'] : 0.0);
            $altTransportCap = match (true) {
                $distanceKm >= 250 => 300,
                $distanceKm >= 80 => 150,
                default => 50,
            };
            $priceHints = [
                'meals' => [
                    'min' => 0,
                    'max' => 20 * $delayHours,
                    'currency' => 'EUR',
                ],
                'hotelPerNight' => [
                    'min' => 80,
                    'max' => 80,
                    'currency' => 'EUR',
                ],
                'taxi' => [
                    'min' => 0,
                    'max' => $altTransportCap,
                    'currency' => 'EUR',
                ],
                'altTransport' => [
                    'min' => 0,
                    'max' => $altTransportCap,
                    'currency' => 'EUR',
                ],
            ];
        } elseif ($transportMode === 'ferry') {
            $priceHints = [
                'meals' => [
                    'rule' => 'reasonable',
                    'softCap' => 40,
                    'currency' => 'EUR',
                ],
                'hotelPerNight' => [
                    'min' => 80,
                    'max' => 80,
                    'currency' => 'EUR',
                    'maxNights' => 3,
                ],
                'localTransport' => [
                    'min' => 0,
                    'max' => 50,
                    'currency' => 'EUR',
                ],
                'taxi' => [
                    'min' => 0,
                    'max' => 150,
                    'currency' => 'EUR',
                ],
                'altTransport' => [
                    'min' => 0,
                    'max' => 400,
                    'currency' => 'EUR',
                ],
            ];
        }
        $modeFields = [];
        if ($transportMode === 'bus') {
            $modeFields['bus'] = [
                'refreshments_offered' => $f['bus_refreshments_offered'] ?? ($f['meal_offered'] ?? null),
                'refreshments_self_paid_amount' => $busValue($f, 'bus_refreshments_self_paid_amount', 'meal_self_paid_amount'),
                'refreshments_self_paid_currency' => $busValue($f, 'bus_refreshments_self_paid_currency', 'meal_self_paid_currency'),
                'hotel_offered' => $busValue($f, 'bus_hotel_offered', 'hotel_offered'),
                'hotel_transport_included' => $busValue($f, 'bus_hotel_transport_included', 'assistance_hotel_transport_included'),
                'hotel_transport_self_paid_amount' => $busValue($f, 'bus_hotel_transport_self_paid_amount', 'hotel_transport_self_paid_amount'),
                'hotel_transport_self_paid_currency' => $busValue($f, 'bus_hotel_transport_self_paid_currency', 'hotel_transport_self_paid_currency'),
                'hotel_transport_self_paid_receipt' => $busValue($f, 'bus_hotel_transport_self_paid_receipt', 'hotel_transport_self_paid_receipt'),
                'overnight_required' => $busValue($f, 'bus_overnight_required', 'overnight_needed'),
                'hotel_self_paid_amount' => $busValue($f, 'bus_hotel_self_paid_amount', 'hotel_self_paid_amount'),
                'hotel_self_paid_currency' => $busValue($f, 'bus_hotel_self_paid_currency', 'hotel_self_paid_currency'),
                'hotel_self_paid_nights' => $busValue($f, 'bus_hotel_self_paid_nights', 'hotel_self_paid_nights'),
                'pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
                'pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
                'pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,
            ];
        } elseif ($transportMode === 'ferry') {
            $modeFields['ferry'] = [
                'refreshments_offered' => $f['ferry_refreshments_offered'] ?? null,
                'refreshments_self_paid_amount' => $f['ferry_refreshments_self_paid_amount'] ?? null,
                'refreshments_self_paid_currency' => $f['ferry_refreshments_self_paid_currency'] ?? null,
                'hotel_offered' => $f['ferry_hotel_offered'] ?? null,
                'hotel_transport_included' => $f['ferry_hotel_transport_included'] ?? null,
                'overnight_required' => $f['ferry_overnight_required'] ?? null,
                'hotel_self_paid_amount' => $f['ferry_hotel_self_paid_amount'] ?? null,
                'hotel_self_paid_currency' => $f['ferry_hotel_self_paid_currency'] ?? null,
                'hotel_self_paid_nights' => $f['ferry_hotel_self_paid_nights'] ?? null,
                'pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
                'pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
                'pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,
            ];
        } elseif ($transportMode === 'air') {
            $modeFields['air'] = [
                'refreshments_offered' => $f['meal_offered'] ?? null,
                'refreshments_self_paid_amount' => $f['meal_self_paid_amount'] ?? null,
                'refreshments_self_paid_currency' => $f['meal_self_paid_currency'] ?? null,
                'hotel_offered' => $f['hotel_offered'] ?? null,
                'hotel_transport_included' => $f['assistance_hotel_transport_included'] ?? null,
                'hotel_transport_self_paid_amount' => $f['hotel_transport_self_paid_amount'] ?? null,
                'hotel_transport_self_paid_currency' => $f['hotel_transport_self_paid_currency'] ?? null,
                'overnight_required' => $f['overnight_needed'] ?? null,
                'hotel_self_paid_amount' => $f['hotel_self_paid_amount'] ?? null,
                'hotel_self_paid_currency' => $f['hotel_self_paid_currency'] ?? null,
                'hotel_self_paid_nights' => $f['hotel_self_paid_nights'] ?? null,
                'pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
                'pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
                'pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,
            ];
        } elseif ($transportMode === 'rail') {
            $modeFields['rail'] = [
                'refreshments_offered' => $f['meal_offered'] ?? null,
                'refreshments_self_paid_amount' => $f['meal_self_paid_amount'] ?? null,
                'refreshments_self_paid_currency' => $f['meal_self_paid_currency'] ?? null,
                'hotel_offered' => $f['hotel_offered'] ?? null,
                'hotel_transport_included' => $f['assistance_hotel_transport_included'] ?? null,
                'overnight_required' => $f['overnight_needed'] ?? null,
                'hotel_self_paid_amount' => $f['hotel_self_paid_amount'] ?? null,
                'hotel_self_paid_currency' => $f['hotel_self_paid_currency'] ?? null,
                'hotel_self_paid_nights' => $f['hotel_self_paid_nights'] ?? null,
                'pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
                'pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
                'pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,
            ];
        }

        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
            'meal_offered' => $f['meal_offered'] ?? null,
            'rail_refreshments_offered' => $railValue($f, 'meal_offered'),
            'bus_refreshments_offered' => $f['bus_refreshments_offered'] ?? null,
            'ferry_refreshments_offered' => $f['ferry_refreshments_offered'] ?? null,
            'assistance_meals_unavailable_reason' => $f['assistance_meals_unavailable_reason'] ?? null,
            'meal_self_paid_amount' => $f['meal_self_paid_amount'] ?? null,
            'meal_self_paid_currency' => $f['meal_self_paid_currency'] ?? null,
            'meal_self_paid_receipt' => $f['meal_self_paid_receipt'] ?? null,
            'meal_self_paid_amount_items' => $f['meal_self_paid_amount_items'] ?? null,
            'meal_self_paid_receipt_items' => $f['meal_self_paid_receipt_items'] ?? null,
            'rail_refreshments_self_paid_amount' => $railValue($f, 'meal_self_paid_amount'),
            'rail_refreshments_self_paid_currency' => $railValue($f, 'meal_self_paid_currency'),
            'bus_refreshments_self_paid_amount' => $busValue($f, 'bus_refreshments_self_paid_amount', 'meal_self_paid_amount'),
            'bus_refreshments_self_paid_currency' => $busValue($f, 'bus_refreshments_self_paid_currency', 'meal_self_paid_currency'),
            'ferry_refreshments_self_paid_amount' => $f['ferry_refreshments_self_paid_amount'] ?? null,
            'ferry_refreshments_self_paid_currency' => $f['ferry_refreshments_self_paid_currency'] ?? null,

            'hotel_offered' => $f['hotel_offered'] ?? null,
            'rail_hotel_offered' => $railValue($f, 'hotel_offered'),
            'bus_hotel_offered' => $busValue($f, 'bus_hotel_offered', 'hotel_offered'),
            'ferry_hotel_offered' => $f['ferry_hotel_offered'] ?? null,
            'assistance_hotel_transport_included' => $f['assistance_hotel_transport_included'] ?? null,
            'rail_hotel_transport_included' => $railValue($f, 'assistance_hotel_transport_included'),
            'bus_hotel_transport_included' => $busValue($f, 'bus_hotel_transport_included', 'assistance_hotel_transport_included'),
            'ferry_hotel_transport_included' => $f['ferry_hotel_transport_included'] ?? null,
            'hotel_transport_self_paid_amount' => $f['hotel_transport_self_paid_amount'] ?? null,
            'hotel_transport_self_paid_currency' => $f['hotel_transport_self_paid_currency'] ?? null,
            'hotel_transport_self_paid_receipt' => $f['hotel_transport_self_paid_receipt'] ?? null,
            'bus_hotel_transport_self_paid_amount' => $busValue($f, 'bus_hotel_transport_self_paid_amount', 'hotel_transport_self_paid_amount'),
            'bus_hotel_transport_self_paid_currency' => $busValue($f, 'bus_hotel_transport_self_paid_currency', 'hotel_transport_self_paid_currency'),
            'bus_hotel_transport_self_paid_receipt' => $busValue($f, 'bus_hotel_transport_self_paid_receipt', 'hotel_transport_self_paid_receipt'),
            'overnight_needed' => $f['overnight_needed'] ?? null,
            'rail_overnight_required' => $railValue($f, 'overnight_needed'),
            'bus_overnight_required' => $busValue($f, 'bus_overnight_required', 'overnight_needed'),
            'ferry_overnight_required' => $f['ferry_overnight_required'] ?? null,
            'hotel_self_paid_nights' => $f['hotel_self_paid_nights'] ?? null,
            'hotel_self_paid_amount' => $f['hotel_self_paid_amount'] ?? null,
            'hotel_self_paid_currency' => $f['hotel_self_paid_currency'] ?? null,
            'hotel_self_paid_receipt' => $f['hotel_self_paid_receipt'] ?? null,
            'hotel_self_paid_amount_items' => $f['hotel_self_paid_amount_items'] ?? null,
            'hotel_self_paid_nights_items' => $f['hotel_self_paid_nights_items'] ?? null,
            'hotel_self_paid_receipt_items' => $f['hotel_self_paid_receipt_items'] ?? null,
            'rail_hotel_self_paid_amount' => $railValue($f, 'hotel_self_paid_amount'),
            'rail_hotel_self_paid_currency' => $railValue($f, 'hotel_self_paid_currency'),
            'rail_hotel_self_paid_nights' => $railValue($f, 'hotel_self_paid_nights'),
            'bus_hotel_self_paid_amount' => $busValue($f, 'bus_hotel_self_paid_amount', 'hotel_self_paid_amount'),
            'bus_hotel_self_paid_currency' => $busValue($f, 'bus_hotel_self_paid_currency', 'hotel_self_paid_currency'),
            'bus_hotel_self_paid_nights' => $busValue($f, 'bus_hotel_self_paid_nights', 'hotel_self_paid_nights'),
            'ferry_hotel_self_paid_amount' => $f['ferry_hotel_self_paid_amount'] ?? null,
            'ferry_hotel_self_paid_currency' => $f['ferry_hotel_self_paid_currency'] ?? null,
            'ferry_hotel_self_paid_nights' => $f['ferry_hotel_self_paid_nights'] ?? null,

            // PMR only
            'assistance_pmr_priority_applied' => $f['assistance_pmr_priority_applied'] ?? null,
            'assistance_pmr_companion_supported' => $f['assistance_pmr_companion_supported'] ?? null,
            'assistance_pmr_dog_supported' => $f['assistance_pmr_dog_supported'] ?? null,

            // Useful for scenario output and UI hinting (not user-entered in step 7)
            'price_hints' => $priceHints,
        ];
    }

    /**
     * TRIN 8 (downgrade.php): per-leg bought vs delivered class/reservation.
     */
    private function extractStep8Downgrade(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        return [
            'downgrade_ticket_file' => $f['downgrade_ticket_file'] ?? null,
            'downgrade_occurred' => $f['downgrade_occurred'] ?? null,
            'downgrade_comp_basis' => $f['downgrade_comp_basis'] ?? null,
            'downgrade_segment_share' => $f['downgrade_segment_share'] ?? null,
            'air_downgrade_booked_class' => $f['air_downgrade_booked_class'] ?? null,
            'air_downgrade_flown_class' => $f['air_downgrade_flown_class'] ?? null,
            'air_downgrade_refund_percent' => $f['air_downgrade_refund_percent'] ?? null,
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
        $transportMode = $this->detectTransportMode($flow);
        $resultPreview = $this->buildCompensationPreview($flow, $transportMode);
        $modeFields = [];
        if ($transportMode === 'bus') {
            $modeFields['bus'] = [
                'delay_at_final_minutes' => $form['delayAtFinalMinutes'] ?? null,
                'compensation_band' => $form['compensationBand'] ?? null,
                'scheduled_distance_km' => $form['scheduled_distance_km'] ?? null,
                'vehicle_breakdown' => $form['vehicle_breakdown'] ?? null,
                'hotel_transport_self_paid_amount' => $form['bus_hotel_transport_self_paid_amount'] ?? ($form['hotel_transport_self_paid_amount'] ?? null),
                'hotel_transport_self_paid_currency' => $form['bus_hotel_transport_self_paid_currency'] ?? ($form['hotel_transport_self_paid_currency'] ?? null),
                'hotel_legal_cap_eur_per_night' => 80,
                'hotel_legal_nights_max' => 2,
                'meals_soft_cap_eur_per_delay_hour' => 20,
                'hotel_transport_soft_cap_eur' => 50,
                'result_preview' => $resultPreview,
            ];
        } elseif ($transportMode === 'ferry') {
            $modeFields['ferry'] = [
                'delay_at_final_minutes' => $form['delayAtFinalMinutes'] ?? null,
                'compensation_band' => $form['compensationBand'] ?? null,
                'hotel_transport_self_paid_amount' => $form['hotel_transport_self_paid_amount'] ?? null,
                'hotel_transport_self_paid_currency' => $form['hotel_transport_self_paid_currency'] ?? null,
                'hotel_legal_cap_eur_per_night' => 80,
                'hotel_legal_nights_max' => 3,
                'meals_legal_rule' => 'reasonable',
                'hotel_transport_included' => true,
                'refund_ticket_price_percent' => 100,
                'refund_deadline_days' => 7,
                'meals_soft_cap_eur_per_day' => 40,
                'local_transport_soft_cap_eur_per_trip' => 50,
                'local_transport_soft_cap_total_eur' => 150,
                'taxi_soft_cap_eur' => 150,
                'self_arranged_alt_transport_soft_cap_total_eur' => 400,
                'result_preview' => $resultPreview,
            ];
        } elseif ($transportMode === 'air') {
            $modeFields['air'] = [
                'delay_at_final_minutes' => $form['delayAtFinalMinutes'] ?? null,
                'compensation_band' => $form['compensationBand'] ?? null,
                'flight_distance_km' => $form['flight_distance_km'] ?? null,
                'air_distance_band' => $form['air_distance_band'] ?? null,
                'air_delay_threshold_hours' => $form['air_delay_threshold_hours'] ?? null,
                'hotel_transport_self_paid_amount' => $form['hotel_transport_self_paid_amount'] ?? null,
                'hotel_transport_self_paid_currency' => $form['hotel_transport_self_paid_currency'] ?? null,
                'alternative_airport_transfer_amount' => $form['air_alternative_airport_transfer_amount'] ?? null,
                'alternative_airport_transfer_currency' => $form['air_alternative_airport_transfer_currency'] ?? null,
                'result_preview' => $resultPreview,
            ];
        } elseif ($transportMode === 'rail') {
            $modeFields['rail'] = [
                'delay_at_final_minutes' => $form['delayAtFinalMinutes'] ?? null,
                'compensation_band' => $form['compensationBand'] ?? null,
                'delay_min_eu' => $compute['delayMinEU'] ?? null,
                'known_delay_before_purchase' => $compute['knownDelayBeforePurchase'] ?? null,
                'voucher_accepted' => $form['voucherAccepted'] ?? null,
                'operator_exceptional_circumstances' => $form['operatorExceptionalCircumstances'] ?? null,
                'operator_exceptional_type' => $form['operatorExceptionalType'] ?? null,
                'minimum_threshold_applies' => $form['minThresholdApplies'] ?? null,
                'result_preview' => $resultPreview,
            ];
        }

        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
            'result_preview' => $resultPreview,
            'delayAtFinalMinutes' => $form['delayAtFinalMinutes'] ?? null,
            'compensationBand' => $form['compensationBand'] ?? null,
            'rail_delay_at_final_minutes' => $transportMode === 'rail' ? ($form['delayAtFinalMinutes'] ?? null) : null,
            'rail_compensation_band' => $transportMode === 'rail' ? ($form['compensationBand'] ?? null) : null,
            'voucherAccepted' => $form['voucherAccepted'] ?? null,
            'operatorExceptionalCircumstances' => $form['operatorExceptionalCircumstances'] ?? null,
            'operatorExceptionalType' => $form['operatorExceptionalType'] ?? null,
            'minThresholdApplies' => $form['minThresholdApplies'] ?? null,

            // Useful for scenario overrides
            'delayMinEU' => $compute['delayMinEU'] ?? null,
            'knownDelayBeforePurchase' => $compute['knownDelayBeforePurchase'] ?? null,
        ] + ($transportMode === 'bus' ? [
            'scheduled_distance_km' => $form['scheduled_distance_km'] ?? null,
            'vehicle_breakdown' => $form['vehicle_breakdown'] ?? null,
            'hotel_transport_self_paid_amount' => $form['hotel_transport_self_paid_amount'] ?? null,
            'hotel_transport_self_paid_currency' => $form['hotel_transport_self_paid_currency'] ?? null,
            'bus_hotel_transport_self_paid_amount' => $form['bus_hotel_transport_self_paid_amount'] ?? ($form['hotel_transport_self_paid_amount'] ?? null),
            'bus_hotel_transport_self_paid_currency' => $form['bus_hotel_transport_self_paid_currency'] ?? ($form['hotel_transport_self_paid_currency'] ?? null),
            'bus_hotel_legal_cap_eur_per_night' => 80,
            'bus_hotel_legal_nights_max' => 2,
            'bus_meals_soft_cap_eur_per_delay_hour' => 20,
            'bus_hotel_transport_soft_cap_eur' => 50,
        ] : []) + ($transportMode === 'ferry' ? [
            'hotel_transport_self_paid_amount' => $form['hotel_transport_self_paid_amount'] ?? null,
            'hotel_transport_self_paid_currency' => $form['hotel_transport_self_paid_currency'] ?? null,
            'ferry_hotel_legal_cap_eur_per_night' => 80,
            'ferry_hotel_legal_nights_max' => 3,
            'ferry_meals_legal_rule' => 'reasonable',
            'ferry_hotel_transport_included' => true,
            'ferry_refund_ticket_price_percent' => 100,
            'ferry_refund_deadline_days' => 7,
            'ferry_meals_soft_cap_eur_per_day' => 40,
            'ferry_local_transport_soft_cap_eur_per_trip' => 50,
            'ferry_local_transport_soft_cap_total_eur' => 150,
            'ferry_taxi_soft_cap_eur' => 150,
            'ferry_self_arranged_alt_transport_soft_cap_total_eur' => 400,
        ] : []);
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
        $transportMode = $this->detectTransportMode($flow);

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

        $normalizePlace = static function ($value): string {
            return preg_replace('/\s+/u', ' ', trim((string)$value)) ?? trim((string)$value);
        };
        $looksLikeNoise = static function (string $value): bool {
            if ($value === '') {
                return true;
            }
            $letterCount = preg_match_all('/[\p{L}]/u', $value, $mLetters);
            $digitCount = preg_match_all('/[\p{N}]/u', $value, $mDigits);
            $symbolCount = preg_match_all('/[^\p{L}\p{N}\s]/u', $value, $mSymbols);
            if ($letterCount === 0 && $digitCount < 2) {
                return true;
            }
            if (mb_strlen($value, 'UTF-8') <= 4 && $letterCount < 3) {
                return true;
            }
            if ($symbolCount >= max(2, $letterCount)) {
                return true;
            }
            if ($letterCount > 0 && preg_match('/(?:^|\s)(?:fq|bust|eee|to)(?:\s|$)/iu', $value)) {
                return true;
            }
            return false;
        };

        $segments = [];
        for ($i = 0; $i < min(3, count($segAuto)); $i++) {
            $s = (array)$segAuto[$i];
            $from = $normalizePlace($s['from'] ?? null);
            $to = $normalizePlace($s['to'] ?? null);
            $fallbackFrom = $normalizePlace($journeyBasic['dep_station'] ?? '');
            $fallbackTo = $normalizePlace($journeyBasic['arr_station'] ?? '');
            if ($transportMode === 'bus') {
                if ($looksLikeNoise($from) && $fallbackFrom !== '') {
                    $from = $fallbackFrom;
                }
                if ($looksLikeNoise($to) && $fallbackTo !== '') {
                    $to = $fallbackTo;
                }
            }
            if ($from === '' && $to === '' && empty($s['schedDep']) && empty($s['schedArr']) && empty($s['depDate']) && empty($s['arrDate'])) {
                continue;
            }
            $segments[] = [
                'from' => $from !== '' ? $from : null,
                'to' => $to !== '' ? $to : null,
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
            'ticket_upload_mode' => $form['ticket_upload_mode'] ?? null,
            'scope_choice' => $form['scope_choice'] ?? null,
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
            'price_currency' => $form['price_currency'] ?? ($auto['price_currency']['value'] ?? null),
            'price_known' => $form['price_known'] ?? null,
            'seller_channel' => $form['seller_channel'] ?? null,
            'original_contract_mode' => $form['original_contract_mode'] ?? null,
            'through_ticket_disclosure' => $form['through_ticket_disclosure'] ?? null,
            'separate_contract_notice' => $form['separate_contract_notice'] ?? null,
            'single_txn_operator' => $form['single_txn_operator'] ?? null,
            'single_txn_retailer' => $form['single_txn_retailer'] ?? null,
        ];
    }

    /**
     * TRIN 4 (station.php): Art.20(3) – strandet paa station uden videre tog.
     */
    private function extractStep4Station(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        return [
            'a20_station_stranded' => $form['a20_station_stranded'] ?? null,
            // Canonical Art.20 flags (set server-side when station stranded is yes)
            'is_stranded' => $form['is_stranded'] ?? null,
            'stranded_location' => $form['stranded_location'] ?? null,

            // Where the passenger is stranded (station context)
            'stranded_current_station' => $form['stranded_current_station'] ?? null,
            'stranded_current_station_other' => $form['stranded_current_station_other'] ?? null,
            'stranded_current_station_other_osm_id' => $form['stranded_current_station_other_osm_id'] ?? null,
            'stranded_current_station_other_lat' => $form['stranded_current_station_other_lat'] ?? null,
            'stranded_current_station_other_lon' => $form['stranded_current_station_other_lon'] ?? null,
            'stranded_current_station_other_country' => $form['stranded_current_station_other_country'] ?? null,
            'stranded_current_station_other_type' => $form['stranded_current_station_other_type'] ?? null,
            'stranded_current_station_other_source' => $form['stranded_current_station_other_source'] ?? null,

            // Offered solution vs self-paid
            'a20_3_solution_offered' => $form['a20_3_solution_offered'] ?? null,
            'a20_3_solution_type' => $form['a20_3_solution_type'] ?? null,
            'a20_3_solution_offered_by' => $form['a20_3_solution_offered_by'] ?? null,
            'a20_3_self_paid' => $form['a20_3_self_paid'] ?? null,
            'a20_3_self_arranged_type' => $form['a20_3_self_arranged_type'] ?? null,
            'a20_3_self_paid_direction' => $form['a20_3_self_paid_direction'] ?? null,
            'a20_3_self_paid_amount' => $form['a20_3_self_paid_amount'] ?? null,
            'a20_3_self_paid_currency' => $form['a20_3_self_paid_currency'] ?? null,
            'a20_3_self_paid_receipt' => $form['a20_3_self_paid_receipt'] ?? null,

            // Resolution endpoint: shared canonical names (legacy fallbacks included for dumps).
            'a20_where_ended' => $form['a20_where_ended'] ?? ($form['assistance_alt_to_destination'] ?? null),
            'a20_arrival_station' => $form['a20_arrival_station'] ?? ($form['assistance_alt_arrival_station'] ?? null),
            'a20_arrival_station_other' => $form['a20_arrival_station_other'] ?? ($form['assistance_alt_arrival_station_other'] ?? null),
            'a20_arrival_station_other_osm_id' => $form['a20_arrival_station_other_osm_id'] ?? null,
            'a20_arrival_station_other_lat' => $form['a20_arrival_station_other_lat'] ?? null,
            'a20_arrival_station_other_lon' => $form['a20_arrival_station_other_lon'] ?? null,
            'a20_arrival_station_other_country' => $form['a20_arrival_station_other_country'] ?? null,
            'a20_arrival_station_other_type' => $form['a20_arrival_station_other_type'] ?? null,
            'a20_arrival_station_other_source' => $form['a20_arrival_station_other_source'] ?? null,
            'a20_where_ended_assumed' => $form['a20_where_ended_assumed'] ?? ($form['assistance_alt_to_destination_assumed'] ?? null),
            'handoff_station' => $form['handoff_station'] ?? null,
        ];
    }

    /**
     * TRIN 5 (incident.php): EU/national gating + missed connection + Art.19(10) evidence.
     */
    private function extractStep5Incident(array $flow): array
    {
        $incident = (array)($flow['incident'] ?? []);
        $form = (array)($flow['form'] ?? []);
        $transportMode = $this->detectTransportMode($flow);
        $busIncidentMain = $transportMode === 'bus' ? ($incident['main'] ?? null) : null;
        $busOverbooking = $transportMode === 'bus'
            ? (($form['overbooking'] ?? null) ?? (($incident['main'] ?? null) === 'overbooking' ? 'yes' : null))
            : null;
        $modeFields = [];
        if ($transportMode === 'bus') {
            $modeFields['bus'] = [
                'incident_main' => $busIncidentMain,
                'delay_departure_band' => $form['delay_departure_band'] ?? null,
                'delay_minutes_departure' => $form['delay_minutes_departure'] ?? null,
                'planned_duration_band' => $form['planned_duration_band'] ?? null,
                'vehicle_breakdown' => $form['vehicle_breakdown'] ?? null,
                'missed_connection_due_to_delay' => $form['missed_connection_due_to_delay'] ?? null,
                'overbooking' => $busOverbooking,
                'severe_weather' => $form['severe_weather'] ?? null,
                'major_natural_disaster' => $form['major_natural_disaster'] ?? null,
            ];
        } elseif ($transportMode === 'ferry') {
            $modeFields['ferry'] = [
                'incident_main' => $incident['main'] ?? null,
                'art16_notice_within_30min' => $form['ferry_art16_notice_within_30min'] ?? null,
                'informed_before_purchase' => $form['informed_before_purchase'] ?? null,
                'open_ticket_without_departure_time' => $form['open_ticket_without_departure_time'] ?? null,
                'weather_safety' => $form['weather_safety'] ?? null,
                'extraordinary_circumstances' => $form['extraordinary_circumstances'] ?? null,
                'overnight_required' => $form['ferry_overnight_required'] ?? ($form['overnight_needed'] ?? null),
            ];
        } elseif ($transportMode === 'air') {
            $modeFields['air'] = [
                'incident_main' => $incident['main'] ?? null,
                'boarding_denied' => $form['boarding_denied'] ?? null,
                'voluntary_denied_boarding' => $form['voluntary_denied_boarding'] ?? null,
                'cancellation_notice_band' => $form['cancellation_notice_band'] ?? null,
                'protected_connection_missed' => $form['protected_connection_missed'] ?? null,
                'reroute_offered' => $form['reroute_offered'] ?? null,
                'refund_offered' => $form['refund_offered'] ?? null,
                'hotel_required' => $form['hotel_required'] ?? null,
            ];
        }

        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
            'incident_main' => $incident['main'] ?? null,
            'incident_missed' => $incident['missed'] ?? null,
            'expected_delay_60' => $form['expected_delay_60'] ?? null,
            'delay_already_60' => $form['delay_already_60'] ?? null,
            'missed_expected_delay_60' => $form['missed_expected_delay_60'] ?? null,
            'national_delay_minutes' => $form['national_delay_minutes'] ?? null,
            'national_delay_reported_at' => $form['national_delay_reported_at'] ?? null,

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
            'ferry_art16_notice_within_30min' => $form['ferry_art16_notice_within_30min'] ?? null,
            'vehicle_breakdown' => $form['vehicle_breakdown'] ?? null,
            'delay_departure_band' => $form['delay_departure_band'] ?? null,
            'delay_minutes_departure' => $form['delay_minutes_departure'] ?? null,
            'planned_duration_band' => $form['planned_duration_band'] ?? null,
            'missed_connection_due_to_delay' => $form['missed_connection_due_to_delay'] ?? null,
            'overbooking' => $form['overbooking'] ?? null,
            'severe_weather' => $form['severe_weather'] ?? null,
            'major_natural_disaster' => $form['major_natural_disaster'] ?? null,

            // Bus-specific aliases to support gradual split from the shared rail-shaped model.
            'bus_incident_main' => $busIncidentMain,
            'bus_delay_departure_band' => $transportMode === 'bus' ? ($form['delay_departure_band'] ?? null) : null,
            'bus_delay_minutes_departure' => $transportMode === 'bus' ? ($form['delay_minutes_departure'] ?? null) : null,
            'bus_planned_duration_band' => $transportMode === 'bus' ? ($form['planned_duration_band'] ?? null) : null,
            'bus_vehicle_breakdown' => $transportMode === 'bus' ? ($form['vehicle_breakdown'] ?? null) : null,
            'bus_missed_connection_due_to_delay' => $transportMode === 'bus' ? ($form['missed_connection_due_to_delay'] ?? null) : null,
            'bus_overbooking' => $busOverbooking,
            'bus_severe_weather' => $transportMode === 'bus' ? ($form['severe_weather'] ?? null) : null,
            'bus_major_natural_disaster' => $transportMode === 'bus' ? ($form['major_natural_disaster'] ?? null) : null,
        ];
    }

    /**
     * TRIN 6 (choices.php): Art.20(2)(c) – strandet paa sporet (track-only).
     */
    private function extractStep6Choices(array $flow): array
    {
        $f = (array)($flow['form'] ?? []);
        $transportMode = $this->detectTransportMode($flow);
        $isTrack = strtolower((string)($f['is_stranded_trin5'] ?? '')) === 'yes'
            || strtolower((string)($f['stranded_location'] ?? '')) === 'track';
        $modeFields = [];
        if ($transportMode === 'rail') {
            $modeFields['rail'] = [
                'is_stranded_trin5' => $f['is_stranded_trin5'] ?? null,
                'maps_opt_in_trin5' => $f['maps_opt_in_trin5'] ?? null,
                'stranded_location' => $f['stranded_location'] ?? null,
                'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
                'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
                'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,
                'a20_where_ended' => $isTrack ? ($f['a20_where_ended'] ?? ($f['assistance_alt_to_destination'] ?? null)) : null,
                'a20_arrival_station' => $isTrack ? ($f['a20_arrival_station'] ?? ($f['assistance_alt_arrival_station'] ?? null)) : null,
                'a20_arrival_station_other' => $isTrack ? ($f['a20_arrival_station_other'] ?? ($f['assistance_alt_arrival_station_other'] ?? null)) : null,
                'a20_where_ended_assumed' => $isTrack ? ($f['a20_where_ended_assumed'] ?? ($f['assistance_alt_to_destination_assumed'] ?? null)) : null,
                'handoff_station' => $isTrack ? ($f['handoff_station'] ?? null) : null,
                'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
                'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
                'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
                'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,
            ];
        }

        return [
            '_mode' => $transportMode,
            '_mode_fields' => $modeFields,
            'is_stranded_trin5' => $f['is_stranded_trin5'] ?? null,
            'maps_opt_in_trin5' => $f['maps_opt_in_trin5'] ?? null,
            'stranded_location' => $f['stranded_location'] ?? null,
            'blocked_train_alt_transport' => $f['blocked_train_alt_transport'] ?? null,
            'blocked_no_transport_action' => $f['blocked_no_transport_action'] ?? null,
            'assistance_alt_transport_type' => $f['assistance_alt_transport_type'] ?? null,

            // Resolution endpoint ("Hvor endte du?") – only part of this step when track-flow is active.
            'a20_where_ended' => $isTrack ? ($f['a20_where_ended'] ?? ($f['assistance_alt_to_destination'] ?? null)) : null,
            'a20_arrival_station' => $isTrack ? ($f['a20_arrival_station'] ?? ($f['assistance_alt_arrival_station'] ?? null)) : null,
            'a20_arrival_station_other' => $isTrack ? ($f['a20_arrival_station_other'] ?? ($f['assistance_alt_arrival_station_other'] ?? null)) : null,
            'a20_where_ended_assumed' => $isTrack ? ($f['a20_where_ended_assumed'] ?? ($f['assistance_alt_to_destination_assumed'] ?? null)) : null,
            'handoff_station' => $isTrack ? ($f['handoff_station'] ?? null) : null,

            // Self-paid transport while blocked on track
            'blocked_self_paid_transport_type' => $f['blocked_self_paid_transport_type'] ?? null,
            'blocked_self_paid_amount' => $f['blocked_self_paid_amount'] ?? null,
            'blocked_self_paid_currency' => $f['blocked_self_paid_currency'] ?? null,
            'blocked_self_paid_receipt' => $f['blocked_self_paid_receipt'] ?? null,
        ];
    }

    private function extractStep7Remedies(array $flow): array { return $this->extractStep6Remedies($flow); }
    private function extractStep8Assistance(array $flow): array { return $this->extractStep7Assistance($flow); }
    private function extractStep9Downgrade(array $flow): array { return $this->extractStep8Downgrade($flow); }
    private function extractStep10Compensation(array $flow): array { return $this->extractStep9Compensation($flow); }

    /**
     * @param array<string,mixed> $canonical
     * @param array<string,mixed> $step4Journey
     * @param array<string,mixed> $step5Incident
     * @param array<string,mixed> $step6Choices
     * @param array<string,mixed> $step7Remedies
     * @param array<string,mixed> $step8Assistance
     * @param array<string,mixed> $step10Comp
     * @param array<string,mixed> $scopeMeta
     * @param array<string,mixed> $contractMeta
     * @param array<string,mixed> $incidentMeta
     * @return array<string,mixed>
     */
    private function enrichCanonicalModeFields(
        array $canonical,
        string $transportMode,
        array $step4Journey,
        array $step5Incident,
        array $step6Choices,
        array $step7Remedies,
        array $step8Assistance,
        array $step10Comp,
        array $scopeMeta,
        array $contractMeta,
        array $incidentMeta
    ): array {
        if ($canonical === []) {
            return $canonical;
        }

        $modeBundle = [
            'journey' => (array)(($step4Journey['_mode_fields'] ?? [])[$transportMode] ?? []),
            'incident' => (array)(($step5Incident['_mode_fields'] ?? [])[$transportMode] ?? []),
            'choices' => (array)(($step6Choices['_mode_fields'] ?? [])[$transportMode] ?? []),
            'remedies' => (array)(($step7Remedies['_mode_fields'] ?? [])[$transportMode] ?? []),
            'assistance' => (array)(($step8Assistance['_mode_fields'] ?? [])[$transportMode] ?? []),
            'compensation' => (array)(($step10Comp['_mode_fields'] ?? [])[$transportMode] ?? []),
            'scope' => $this->extractCanonicalScopeModeFields($transportMode, $scopeMeta),
            'contract' => $this->extractCanonicalContractModeFields($transportMode, $contractMeta),
            'incident_meta' => $this->extractCanonicalIncidentModeFields($transportMode, $incidentMeta),
        ];
        $modeBundle = array_filter($modeBundle, static fn($value): bool => is_array($value) && $value !== []);

        $canonical['_mode'] = $transportMode;
        $canonical['_mode_fields'] = [$transportMode => $modeBundle];

        foreach (['journey', 'incident', 'contract', 'legal_assessment', 'evidence'] as $section) {
            if (!isset($canonical[$section]) || !is_array($canonical[$section])) {
                continue;
            }
            $sectionFields = [];
            if ($section === 'journey') {
                $sectionFields = (array)($modeBundle['journey'] ?? []);
            } elseif ($section === 'incident') {
                $sectionFields = array_replace(
                    (array)($modeBundle['incident_meta'] ?? []),
                    (array)($modeBundle['incident'] ?? [])
                );
            } elseif ($section === 'contract') {
                $sectionFields = (array)($modeBundle['contract'] ?? []);
            } elseif ($section === 'legal_assessment') {
                $sectionFields = array_replace(
                    (array)($modeBundle['scope'] ?? []),
                    (array)($modeBundle['compensation'] ?? [])
                );
            } elseif ($section === 'evidence') {
                $sectionFields = array_replace(
                    (array)($modeBundle['remedies'] ?? []),
                    (array)($modeBundle['assistance'] ?? [])
                );
            }
            if ($sectionFields === []) {
                continue;
            }
            $canonical[$section]['_mode'] = $transportMode;
            $canonical[$section]['_mode_fields'] = [$transportMode => $sectionFields];
        }

        return $canonical;
    }

    /**
     * @param array<string,mixed> $scopeMeta
     * @return array<string,mixed>
     */
    private function extractCanonicalScopeModeFields(string $transportMode, array $scopeMeta): array
    {
        return match ($transportMode) {
            'bus' => array_filter([
                'scheduled_distance_km' => $scopeMeta['scheduled_distance_km'] ?? null,
                'departure_from_terminal' => $scopeMeta['departure_from_terminal'] ?? null,
                'bus_regular_service' => $scopeMeta['bus_regular_service'] ?? null,
                'boarding_in_eu' => $scopeMeta['boarding_in_eu'] ?? null,
                'alighting_in_eu' => $scopeMeta['alighting_in_eu'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'ferry' => array_filter([
                'departure_port_in_eu' => $scopeMeta['departure_port_in_eu'] ?? null,
                'arrival_port_in_eu' => $scopeMeta['arrival_port_in_eu'] ?? null,
                'carrier_is_eu' => $scopeMeta['carrier_is_eu'] ?? null,
                'vessel_passenger_capacity' => $scopeMeta['vessel_passenger_capacity'] ?? null,
                'route_distance_meters' => $scopeMeta['route_distance_meters'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'air' => array_filter([
                'departure_airport_in_eu' => $scopeMeta['departure_airport_in_eu'] ?? null,
                'arrival_airport_in_eu' => $scopeMeta['arrival_airport_in_eu'] ?? null,
                'operating_carrier_is_eu' => $scopeMeta['operating_carrier_is_eu'] ?? null,
                'flight_distance_km' => $scopeMeta['flight_distance_km'] ?? null,
                'air_distance_band' => $scopeMeta['air_distance_band'] ?? null,
                'air_delay_threshold_hours' => $scopeMeta['air_delay_threshold_hours'] ?? null,
                'intra_eu_over_1500' => $scopeMeta['intra_eu_over_1500'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'rail' => array_filter([
                'transport_mode' => $scopeMeta['transport_mode'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $contractMeta
     * @return array<string,mixed>
     */
    private function extractCanonicalContractModeFields(string $transportMode, array $contractMeta): array
    {
        return match ($transportMode) {
            'air' => array_filter([
                'marketing_carrier' => $contractMeta['marketing_carrier'] ?? null,
                'operating_carrier' => $contractMeta['operating_carrier'] ?? null,
                'air_connection_type' => $contractMeta['air_connection_type'] ?? null,
                'self_transfer_notice' => $contractMeta['self_transfer_notice'] ?? null,
                'protected_connection_disclosed' => $contractMeta['protected_connection_disclosed'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'bus', 'ferry', 'rail' => array_filter([
                'claim_transport_mode' => $contractMeta['claim_transport_mode'] ?? null,
                'original_contract_mode' => $contractMeta['original_contract_mode'] ?? null,
                'incident_segment_mode' => $contractMeta['incident_segment_mode'] ?? null,
                'journey_structure' => $contractMeta['journey_structure'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $incidentMeta
     * @return array<string,mixed>
     */
    private function extractCanonicalIncidentModeFields(string $transportMode, array $incidentMeta): array
    {
        return match ($transportMode) {
            'bus' => array_filter([
                'incident_type' => $incidentMeta['incident_type'] ?? null,
                'delay_departure_band' => $incidentMeta['delay_departure_band'] ?? null,
                'delay_minutes_departure' => $incidentMeta['delay_minutes_departure'] ?? null,
                'planned_duration_band' => $incidentMeta['planned_duration_band'] ?? null,
                'vehicle_breakdown' => $incidentMeta['vehicle_breakdown'] ?? null,
                'missed_connection_due_to_delay' => $incidentMeta['missed_connection_due_to_delay'] ?? null,
                'overbooking' => $incidentMeta['overbooking'] ?? null,
                'severe_weather' => $incidentMeta['severe_weather'] ?? null,
                'major_natural_disaster' => $incidentMeta['major_natural_disaster'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'ferry' => array_filter([
                'incident_type' => $incidentMeta['incident_type'] ?? null,
                'ferry_art16_notice_within_30min' => $incidentMeta['ferry_art16_notice_within_30min'] ?? null,
                'informed_before_purchase' => $incidentMeta['informed_before_purchase'] ?? null,
                'open_ticket_without_departure_time' => $incidentMeta['open_ticket_without_departure_time'] ?? null,
                'weather_safety' => $incidentMeta['weather_safety'] ?? null,
                'extraordinary_circumstances' => $incidentMeta['extraordinary_circumstances'] ?? null,
                'overnight_required' => $incidentMeta['overnight_required'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'air' => array_filter([
                'incident_type' => $incidentMeta['incident_type'] ?? null,
                'boarding_denied' => $incidentMeta['boarding_denied'] ?? null,
                'voluntary_denied_boarding' => $incidentMeta['voluntary_denied_boarding'] ?? null,
                'cancellation_notice_band' => $incidentMeta['cancellation_notice_band'] ?? null,
                'protected_connection_missed' => $incidentMeta['protected_connection_missed'] ?? null,
                'reroute_offered' => $incidentMeta['reroute_offered'] ?? null,
                'refund_offered' => $incidentMeta['refund_offered'] ?? null,
                'hotel_required' => $incidentMeta['hotel_required'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'rail' => array_filter([
                'incident_type' => $incidentMeta['incident_type'] ?? null,
                'incident_missed' => $incidentMeta['incident_missed'] ?? null,
                'arrival_delay_minutes' => $incidentMeta['arrival_delay_minutes'] ?? null,
                'operator_exceptional_circumstances' => $incidentMeta['operator_exceptional_circumstances'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
            default => [],
        };
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

    private function deriveDefaultCurrency(array $form, array $meta, array $journey): string
    {
        $cc = strtoupper(trim((string)(
            $form['operator_country']
            ?? ($meta['_auto']['operator_country']['value'] ?? null)
            ?? ($journey['country']['value'] ?? null)
            ?? ''
        )));
        $cur = self::COUNTRY_TO_CURRENCY[$cc] ?? 'EUR';

        return in_array($cur, self::SUPPORTED_CURRENCIES, true) ? $cur : 'EUR';
    }

    private function detectExplicitMoneyCurrency(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/€/u', $raw) || preg_match('/\bEUR(?:O)?\b/i', $raw)) {
            return 'EUR';
        }
        $symbolMap = [
            'KČ' => 'CZK',
            'Kč' => 'CZK',
            'ZŁ' => 'PLN',
            'zł' => 'PLN',
            'FT' => 'HUF',
            'Ft' => 'HUF',
            'LEI' => 'RON',
            'лв' => 'BGN',
        ];
        foreach ($symbolMap as $symbol => $iso) {
            if (stripos($raw, $symbol) !== false) {
                return $iso;
            }
        }
        if (preg_match('/\b(BGN|CZK|DKK|HUF|PLN|RON|SEK|NOK|GBP|CHF|EUR)\b/i', $raw, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    private function normalizeMoneyAmount(mixed $raw): ?float
    {
        $s = trim((string)$raw);
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/[^0-9,\.\s]/', '', $s) ?? '';
        $s = preg_replace('/\s+/', '', $s) ?? '';
        if ($s === '') {
            return null;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            if (substr_count($s, ',') > 1) {
                $parts = explode(',', $s);
                $dec = array_pop($parts);
                $s = implode('', $parts) . '.' . $dec;
            } else {
                $s = str_replace(',', '.', $s);
            }
        } elseif ($lastDot !== false && substr_count($s, '.') > 1) {
            $parts = explode('.', $s);
            $dec = array_pop($parts);
            $s = implode('', $parts) . '.' . $dec;
        }

        return is_numeric($s) ? (float)$s : null;
    }

    private function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fx = [
            'EUR' => 1.0,
            'DKK' => 7.45,
            'SEK' => 11.0,
            'BGN' => 1.96,
            'CZK' => 25.0,
            'HUF' => 385.0,
            'PLN' => 4.35,
            'RON' => 4.95,
            'NOK' => 11.6,
            'GBP' => 0.86,
            'CHF' => 0.96,
        ];
        $fromCurrency = strtoupper(trim($fromCurrency));
        $toCurrency = strtoupper(trim($toCurrency));
        if ($fromCurrency === '' || $toCurrency === '' || $fromCurrency === $toCurrency) {
            return $amount;
        }
        if (!isset($fx[$fromCurrency], $fx[$toCurrency]) || $fx[$fromCurrency] <= 0 || $fx[$toCurrency] <= 0) {
            return $amount;
        }

        return ($amount / $fx[$fromCurrency]) * $fx[$toCurrency];
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildCompensationPreview(array $flow, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? ($meta['journey'] ?? []));
        $compute = (array)($flow['compute'] ?? []);
        $incident = (array)($flow['incident'] ?? []);

        $manualPriceRaw = trim((string)($form['price'] ?? ''));
        $journeyPriceRaw = trim((string)($journey['ticketPrice']['value'] ?? ''));
        $autoPriceRaw = trim((string)($meta['_auto']['price']['value'] ?? ''));
        $priceRaw = $manualPriceRaw !== ''
            ? $manualPriceRaw
            : ($journeyPriceRaw !== '' ? $journeyPriceRaw : ($autoPriceRaw !== '' ? $autoPriceRaw : '0'));
        $ticketCurrency = $this->detectExplicitMoneyCurrency($priceRaw)
            ?? strtoupper(trim((string)(
                $form['price_currency']
                ?? ($journey['ticketPrice']['currency'] ?? '')
                ?? ($meta['_auto']['price_currency']['value'] ?? '')
            )));
        if (!in_array($ticketCurrency, self::SUPPORTED_CURRENCIES, true)) {
            $ticketCurrency = $this->deriveDefaultCurrency($form, $meta, $journey);
        }
        $ticketPriceAmount = $this->normalizeMoneyAmount($priceRaw) ?? 0.0;

        $expenseAmount = function (string $amountKey, ?string $currencyKey = null) use ($form, $ticketCurrency): float {
            $amount = $this->normalizeMoneyAmount($form[$amountKey] ?? null) ?? 0.0;
            $fromCurrency = strtoupper(trim((string)($currencyKey !== null ? ($form[$currencyKey] ?? '') : '')));
            if ($fromCurrency === '') {
                $fromCurrency = $ticketCurrency;
            }

            return $this->convertAmount($amount, $fromCurrency, $ticketCurrency);
        };

        $expenses = [
            'meals' => $expenseAmount('meal_self_paid_amount', 'meal_self_paid_currency'),
            'hotel' => $expenseAmount('hotel_self_paid_amount', 'hotel_self_paid_currency'),
            'hotel_transport' => $expenseAmount('hotel_transport_self_paid_amount', 'hotel_transport_self_paid_currency'),
            'alt_transport' => $expenseAmount('alt_self_paid_amount', 'alt_self_paid_currency')
                + $expenseAmount('blocked_self_paid_amount', 'blocked_self_paid_currency'),
            'other' => $expenseAmount('expense_breakdown_other_amounts', 'expense_breakdown_currency'),
        ];

        $claimInput = [
            'transport_mode' => $transportMode,
            'country_code' => (string)($journey['country']['value'] ?? ($form['operator_country'] ?? 'EU')),
            'currency' => $ticketCurrency,
            'ticket_price_total' => $ticketPriceAmount,
            'disruption' => [
                'delay_minutes_final' => (int)($form['delayAtFinalMinutes'] ?? ($compute['delayAtFinalMinutes'] ?? 0)),
                'eu_only' => (bool)($compute['euOnly'] ?? false),
                'delay_minutes_final_eu' => (bool)($compute['euOnly'] ?? false)
                    ? (int)($form['delayAtFinalMinutes'] ?? ($compute['delayAtFinalMinutes'] ?? 0))
                    : null,
                'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                'extraordinary' => false,
                'self_inflicted' => false,
                'missed_connection' => !empty($incident['missed']),
                'art18_active' => true,
            ],
            'choices' => [
                'wants_refund' => ((string)($form['remedyChoice'] ?? '') === 'refund_return')
                    || ((string)($form['air_refund_scope'] ?? '') !== ''),
                'wants_reroute_same_soonest' => ((string)($form['remedyChoice'] ?? '') === 'reroute_soonest'),
                'wants_reroute_later_choice' => ((string)($form['remedyChoice'] ?? '') === 'reroute_later'),
            ],
            'expenses' => $expenses,
            'carrier_offered_choice' => $form['carrier_offered_choice'] ?? null,
            'scheduled_distance_km' => $form['scheduled_distance_km'] ?? null,
            'vehicle_breakdown' => $form['vehicle_breakdown'] ?? null,
            'hotel_self_paid_nights' => $form['hotel_self_paid_nights'] ?? ($form['bus_hotel_self_paid_nights'] ?? ($form['ferry_hotel_self_paid_nights'] ?? null)),
            'hotel_transport_self_paid_amount' => $expenses['hotel_transport'],
            'return_to_origin_expense' => $form['return_to_origin_expense'] ?? null,
            'return_to_origin_amount' => $expenseAmount('return_to_origin_amount', 'return_to_origin_currency'),
            'reroute_extra_costs' => $form['reroute_extra_costs'] ?? null,
            'reroute_extra_costs_amount' => $expenseAmount('reroute_extra_costs_amount', 'reroute_extra_costs_currency'),
            'open_ticket_without_departure_time' => $form['open_ticket_without_departure_time'] ?? null,
            'weather_safety' => $form['weather_safety'] ?? null,
            'air_distance_band' => $form['air_distance_band'] ?? null,
            'air_delay_threshold_hours' => $form['air_delay_threshold_hours'] ?? null,
            'air_refund_scope' => $form['air_refund_scope'] ?? null,
            'air_alternative_airport_transfer_amount' => $expenseAmount('air_alternative_airport_transfer_amount', 'air_alternative_airport_transfer_currency'),
        ];

        $claim = (new ClaimCalculator())->calculate($claimInput);
        $breakdown = (array)($claim['breakdown'] ?? []);
        $totals = (array)($claim['totals'] ?? []);
        $art18 = (array)($breakdown['art18'] ?? []);
        $comp = (array)($breakdown['compensation'] ?? []);
        $refund = (array)($breakdown['refund'] ?? []);

        if ($transportMode === 'air') {
            $airRights = (array)($flow['meta']['_multimodal']['air_rights'] ?? []);
            $airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airRights['air_distance_band'] ?? ''))));
            $airRerouteArrivalDelayMinutes = is_numeric($form['reroute_arrival_delay_minutes'] ?? null)
                ? (int)$form['reroute_arrival_delay_minutes']
                : 0;
            $airCompFlatAmount = match ($airDistanceBand) {
                'up_to_1500' => 250.0,
                'intra_eu_over_1500', 'other_1500_to_3500' => 400.0,
                'other_over_3500' => 600.0,
                default => 250.0,
            };
            $airCompReductionThresholdMinutes = match ($airDistanceBand) {
                'up_to_1500' => 120,
                'intra_eu_over_1500', 'other_1500_to_3500' => 180,
                'other_over_3500' => 240,
                default => 120,
            };
            $airCompReductionPct = (!empty($airRights['gate_air_compensation'])
                && $airRerouteArrivalDelayMinutes > 0
                && $airRerouteArrivalDelayMinutes <= $airCompReductionThresholdMinutes)
                ? 50
                : 0;
            if (!empty($airRights['gate_air_compensation'])) {
                $comp['eligible'] = true;
                $comp['pct'] = $airCompReductionPct > 0 ? 50.0 : 100.0;
                $comp['amount'] = round($airCompFlatAmount * (($airCompReductionPct > 0 ? 50 : 100) / 100), 2);
                $comp['basis'] = 'Art. 7 EC261 flat amount' . ($airCompReductionPct > 0 ? ' - reduced 50% under Art. 7(2)' : '');
            }

            $airBookedClass = strtolower(trim((string)($form['air_downgrade_booked_class'] ?? '')));
            $airFlownClass = strtolower(trim((string)($form['air_downgrade_flown_class'] ?? '')));
            $airDowngradePctRaw = $form['air_downgrade_refund_percent'] ?? null;
            $airDowngradeGate = (string)($form['downgrade_occurred'] ?? '') === 'yes'
                || (string)$airDowngradePctRaw !== ''
                || ($airBookedClass !== '' && $airFlownClass !== '' && $airBookedClass !== $airFlownClass);
            $airDowngradePctNum = in_array((int)$airDowngradePctRaw, [30, 50, 75], true)
                ? (int)$airDowngradePctRaw
                : match ($airDistanceBand) {
                    'up_to_1500' => 30,
                    'intra_eu_over_1500', 'other_1500_to_3500' => 50,
                    'other_over_3500' => 75,
                    default => 30,
                };
            if ($airDowngradeGate && $ticketPriceAmount > 0) {
                $art18['downgrade_annexii'] = round($ticketPriceAmount * ($airDowngradePctNum / 100), 2);
            }

            $gross = round(
                max(0.0, (float)($refund['amount'] ?? 0.0))
                + max(0.0, (float)($comp['amount'] ?? 0.0))
                + max(0.0, (float)($breakdown['expenses']['total'] ?? 0.0))
                + max(0.0, (float)($art18['return_to_origin'] ?? 0.0))
                + max(0.0, (float)($art18['reroute_extra_costs'] ?? 0.0))
                + max(0.0, (float)($art18['downgrade_annexii'] ?? 0.0)),
                2
            );
            $serviceFeePct = (float)($totals['service_fee_pct'] ?? 0.0);
            $totals['gross_claim'] = $gross;
            $totals['service_fee_amount'] = round($gross * ($serviceFeePct / 100), 2);
            $totals['net_to_client'] = round(max(0.0, $gross - (float)$totals['service_fee_amount']), 2);
        }

        return [
            'ticket_price_total' => round($ticketPriceAmount, 2),
            'currency' => (string)($totals['currency'] ?? $ticketCurrency),
            'refund' => [
                'amount' => round((float)($refund['amount'] ?? 0.0), 2),
                'basis' => (string)($refund['basis'] ?? ''),
            ],
            'compensation' => [
                'eligible' => (bool)($comp['eligible'] ?? false),
                'amount' => round((float)($comp['amount'] ?? 0.0), 2),
                'pct' => (float)($comp['pct'] ?? 0.0),
                'basis' => (string)($comp['basis'] ?? ''),
            ],
            'expenses' => [
                'total' => round((float)(($breakdown['expenses']['total'] ?? 0.0)), 2),
                'meals' => round((float)(($breakdown['expenses']['meals'] ?? 0.0)), 2),
                'hotel' => round((float)(($breakdown['expenses']['hotel'] ?? 0.0)), 2),
                'hotel_transport' => round((float)(($breakdown['expenses']['hotel_transport'] ?? 0.0)), 2),
                'alt_transport' => round((float)(($breakdown['expenses']['alt_transport'] ?? 0.0)), 2),
                'other' => round((float)(($breakdown['expenses']['other'] ?? 0.0)), 2),
            ],
            'art18' => [
                'reroute_extra_costs' => round((float)($art18['reroute_extra_costs'] ?? 0.0), 2),
                'return_to_origin' => round((float)($art18['return_to_origin'] ?? 0.0), 2),
                'downgrade_annexii' => round((float)($art18['downgrade_annexii'] ?? 0.0), 2),
            ],
            'totals' => [
                'gross_claim' => round((float)($totals['gross_claim'] ?? 0.0), 2),
                'service_fee_pct' => (float)($totals['service_fee_pct'] ?? 0.0),
                'service_fee_amount' => round((float)($totals['service_fee_amount'] ?? 0.0), 2),
                'net_to_client' => round((float)($totals['net_to_client'] ?? 0.0), 2),
            ],
            'caps' => array_intersect_key(
                $breakdown,
                array_flip(['bus_caps', 'ferry_caps'])
            ),
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
            'original_contract_mode' => $flow['form']['original_contract_mode'] ?? null,
            'separate_contract_notice' => 'Ved ikke',
            'through_ticket_disclosure' => 'Ved ikke',
        ];
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<int,array<string,mixed>> $segments
     */
    private function resolveOriginalContractMode(array $form, array $meta, array $segments, string $transportMode): ?string
    {
        foreach ([
            $form['original_contract_mode'] ?? null,
            $meta['original_contract_mode'] ?? null,
        ] as $candidate) {
            $normalized = strtolower(trim((string)$candidate));
            if (in_array($normalized, ['rail', 'ferry', 'bus', 'air'], true)) {
                return $normalized;
            }
        }

        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentMode = strtolower(trim((string)($segment['mode'] ?? '')));
            if (in_array($segmentMode, ['rail', 'ferry', 'bus', 'air'], true)) {
                return $segmentMode;
            }
        }

        return in_array($transportMode, ['rail', 'ferry', 'bus', 'air'], true) ? $transportMode : null;
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

        // Prefer explicit user input (form) over meta/auto defaults when exporting fixtures.
        $deniedBoarding = $form['bike_denied_boarding'] ?? ($flowMeta['bike_denied_boarding'] ?? null);
        $refusalProvided = $form['bike_refusal_reason_provided'] ?? ($flowMeta['bike_refusal_reason_provided'] ?? null);
        $refusalType = $form['bike_refusal_reason_type'] ?? ($flowMeta['bike_refusal_reason_type'] ?? null);
        $refusalOtherText = $form['bike_refusal_reason_other_text'] ?? ($flowMeta['bike_refusal_reason_other_text'] ?? null);

        $bikeDeniedReason = 'unknown';
        if ($toYesNoUnknown($deniedBoarding) === 'Ja') {
            $bikeDeniedReason = is_string($refusalType) && trim($refusalType) !== '' ? (string)$refusalType : 'denied_boarding';
        } elseif ($toYesNoUnknown($refusalProvided) === 'Ja' && (empty($refusalType))) {
            $bikeDeniedReason = 'unspecified';
        }

        $rtSeen = $form['realtime_info_seen'] ?? ($flowMeta['realtime_info_seen'] ?? []);
        $pmrDeliveredMeta = $flowMeta['pmr_delivered_status'] ?? null;

        $bikeResMade = $toYesNoUnknown($form['bike_reservation_made'] ?? ($flowMeta['bike_reservation_made'] ?? null));
        $bikeResReq = $toYesNoUnknown($form['bike_reservation_required'] ?? ($flowMeta['bike_reservation_required'] ?? null));
        $bikeDenied = $toYesNoUnknown($deniedBoarding);
        $bikeReasonProvided = $toYesNoUnknown($refusalProvided);
        $bikeReasonType = is_string($refusalType) ? trim($refusalType) : '';

        $pmrUser = $toYesNoUnknown($form['pmr_user'] ?? ($flowMeta['pmr_user'] ?? null));
        $pmrCompanion = $toYesNoUnknown($form['pmr_companion'] ?? ($flowMeta['pmr_companion'] ?? null));
        $pmrServiceDog = $toYesNoUnknown($form['pmr_service_dog'] ?? ($flowMeta['pmr_service_dog'] ?? null));
        $unaccompaniedMinor = $toYesNoUnknown($form['unaccompanied_minor'] ?? ($flowMeta['unaccompanied_minor'] ?? null));
        $ferryPmrCompanion = $toYesNoUnknown($form['ferry_pmr_companion'] ?? ($flowMeta['ferry_pmr_companion'] ?? null));
        $ferryPmrServiceDog = $toYesNoUnknown($form['ferry_pmr_service_dog'] ?? ($flowMeta['ferry_pmr_service_dog'] ?? null));
        $ferryPmrNotice48h = $toYesNoUnknown($form['ferry_pmr_notice_48h'] ?? ($flowMeta['ferry_pmr_notice_48h'] ?? null));
        $ferryPmrMetCheckinTime = $toYesNoUnknown($form['ferry_pmr_met_checkin_time'] ?? ($flowMeta['ferry_pmr_met_checkin_time'] ?? null));
        $ferryPmrBoardingRefused = $toYesNoUnknown($form['ferry_pmr_boarding_refused'] ?? ($flowMeta['ferry_pmr_boarding_refused'] ?? null));
        $ferryPmrReasonGiven = $toYesNoUnknown($form['ferry_pmr_reason_given'] ?? ($flowMeta['ferry_pmr_reason_given'] ?? null));
        $ferryPmrAltTransport = $toYesNoUnknown($form['ferry_pmr_alternative_transport_offered'] ?? ($flowMeta['ferry_pmr_alternative_transport_offered'] ?? null));
        $ferryPmrAssistanceDelivered = is_string($form['ferry_pmr_assistance_delivered'] ?? null)
            ? trim((string)$form['ferry_pmr_assistance_delivered'])
            : (is_string($flowMeta['ferry_pmr_assistance_delivered'] ?? null) ? trim((string)$flowMeta['ferry_pmr_assistance_delivered']) : 'unknown');
        $ferryPmrRefusalBasis = is_string($form['ferry_pmr_refusal_basis'] ?? null)
            ? trim((string)$form['ferry_pmr_refusal_basis'])
            : (is_string($flowMeta['ferry_pmr_refusal_basis'] ?? null) ? trim((string)$flowMeta['ferry_pmr_refusal_basis']) : '');
        $busPmrCompanion = $toYesNoUnknown($form['bus_pmr_companion'] ?? ($flowMeta['bus_pmr_companion'] ?? null));
        $busPmrNotice36h = $toYesNoUnknown($form['bus_pmr_notice_36h'] ?? ($flowMeta['bus_pmr_notice_36h'] ?? null));
        $busPmrMetTerminalTime = $toYesNoUnknown($form['bus_pmr_met_terminal_time'] ?? ($flowMeta['bus_pmr_met_terminal_time'] ?? null));
        $busPmrSpecialSeatingNotified = $toYesNoUnknown($form['bus_pmr_special_seating_notified'] ?? ($flowMeta['bus_pmr_special_seating_notified'] ?? null));
        $busPmrBoardingRefused = $toYesNoUnknown($form['bus_pmr_boarding_refused'] ?? ($flowMeta['bus_pmr_boarding_refused'] ?? null));
        $busPmrReasonGiven = $toYesNoUnknown($form['bus_pmr_reason_given'] ?? ($flowMeta['bus_pmr_reason_given'] ?? null));
        $busPmrAltTransport = $toYesNoUnknown($form['bus_pmr_alternative_transport_offered'] ?? ($flowMeta['bus_pmr_alternative_transport_offered'] ?? null));
        $busPmrAssistanceDelivered = is_string($form['bus_pmr_assistance_delivered'] ?? null)
            ? trim((string)$form['bus_pmr_assistance_delivered'])
            : (is_string($flowMeta['bus_pmr_assistance_delivered'] ?? null) ? trim((string)$flowMeta['bus_pmr_assistance_delivered']) : 'unknown');
        $busPmrRefusalBasis = is_string($form['bus_pmr_refusal_basis'] ?? null)
            ? trim((string)$form['bus_pmr_refusal_basis'])
            : (is_string($flowMeta['bus_pmr_refusal_basis'] ?? null) ? trim((string)$flowMeta['bus_pmr_refusal_basis']) : '');
        $pmrBooked = $toYesNoUnknown($form['pmr_booked'] ?? ($flowMeta['pmr_booked'] ?? null));
        $pmrPromisedMissing = $toYesNoUnknown($form['pmr_promised_missing'] ?? ($flowMeta['pmr_promised_missing'] ?? null));
        $pmrDelivered = $toYesNoUnknown($form['pmr_delivered_status'] ?? $pmrDeliveredMeta ?? ($form['pmrQDelivered'] ?? 'unknown'));

        $preinformed = $toYesNoUnknown($form['preinformed_disruption'] ?? ($flowMeta['preinformed_disruption'] ?? 'unknown'));
        $preinfoChannel = (string)($form['preinfo_channel'] ?? ($flowMeta['preinfo_channel'] ?? ''));
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
                'air_art11' => [
                    'priority' => [
                        'pmr_user' => $pmrUser,
                        'pmr_companion' => $pmrCompanion,
                        'pmr_service_dog' => $pmrServiceDog,
                        'unaccompanied_minor' => $unaccompaniedMinor,
                    ],
                ],
                'ferry_pmr' => [
                    'status' => [
                        'pmr_user' => $pmrUser,
                        'ferry_pmr_companion' => $ferryPmrCompanion,
                        'ferry_pmr_service_dog' => $ferryPmrServiceDog,
                    ],
                    'assistance' => [
                        'ferry_pmr_notice_48h' => $ferryPmrNotice48h,
                        'ferry_pmr_met_checkin_time' => $ferryPmrMetCheckinTime,
                        'ferry_pmr_assistance_delivered' => $ferryPmrAssistanceDelivered,
                    ],
                    'boarding' => [
                        'ferry_pmr_boarding_refused' => $ferryPmrBoardingRefused,
                        'ferry_pmr_refusal_basis' => $ferryPmrRefusalBasis,
                        'ferry_pmr_reason_given' => $ferryPmrReasonGiven,
                        'ferry_pmr_alternative_transport_offered' => $ferryPmrAltTransport,
                    ],
                ],
                'bus_pmr' => [
                    'status' => [
                        'pmr_user' => $pmrUser,
                        'bus_pmr_companion' => $busPmrCompanion,
                    ],
                    'assistance' => [
                        'bus_pmr_notice_36h' => $busPmrNotice36h,
                        'bus_pmr_met_terminal_time' => $busPmrMetTerminalTime,
                        'bus_pmr_special_seating_notified' => $busPmrSpecialSeatingNotified,
                        'bus_pmr_assistance_delivered' => $busPmrAssistanceDelivered,
                    ],
                    'boarding' => [
                        'bus_pmr_boarding_refused' => $busPmrBoardingRefused,
                        'bus_pmr_refusal_basis' => $busPmrRefusalBasis,
                        'bus_pmr_reason_given' => $busPmrReasonGiven,
                        'bus_pmr_alternative_transport_offered' => $busPmrAltTransport,
                    ],
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
            'pmr_companion' => $pmrCompanion,
            'pmr_service_dog' => $pmrServiceDog,
            'unaccompanied_minor' => $unaccompaniedMinor,
            'ferry_pmr_companion' => $ferryPmrCompanion,
            'ferry_pmr_service_dog' => $ferryPmrServiceDog,
            'ferry_pmr_notice_48h' => $ferryPmrNotice48h,
            'ferry_pmr_met_checkin_time' => $ferryPmrMetCheckinTime,
            'ferry_pmr_assistance_delivered' => $ferryPmrAssistanceDelivered,
            'ferry_pmr_boarding_refused' => $ferryPmrBoardingRefused,
            'ferry_pmr_refusal_basis' => $ferryPmrRefusalBasis,
            'ferry_pmr_reason_given' => $ferryPmrReasonGiven,
            'ferry_pmr_alternative_transport_offered' => $ferryPmrAltTransport,
            'bus_pmr_companion' => $busPmrCompanion,
            'bus_pmr_notice_36h' => $busPmrNotice36h,
            'bus_pmr_met_terminal_time' => $busPmrMetTerminalTime,
            'bus_pmr_special_seating_notified' => $busPmrSpecialSeatingNotified,
            'bus_pmr_assistance_delivered' => $busPmrAssistanceDelivered,
            'bus_pmr_boarding_refused' => $busPmrBoardingRefused,
            'bus_pmr_refusal_basis' => $busPmrRefusalBasis,
            'bus_pmr_reason_given' => $busPmrReasonGiven,
            'bus_pmr_alternative_transport_offered' => $busPmrAltTransport,
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
