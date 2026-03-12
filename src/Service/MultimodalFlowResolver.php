<?php
declare(strict_types=1);

namespace App\Service;

final class MultimodalFlowResolver
{
    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function evaluate(array $flow, bool $includeIncident = true): array
    {
        $transportMode = $this->detectTransportMode($flow);
        $journeyBasic = $this->extractJourneyBasic($flow);
        $segments = $this->extractSegments($flow);
        $contractMeta = $this->buildContractMeta($flow, $journeyBasic, $segments, $transportMode);
        $scopeMeta = $this->buildScopeMeta($flow, $transportMode);
        $incidentMeta = $this->buildIncidentMeta($flow, $transportMode);

        $result = [
            'transport_mode' => $transportMode,
            'contract_meta' => $contractMeta,
            'scope_meta' => $scopeMeta,
            'incident_meta' => $incidentMeta,
        ];

        if ($transportMode === 'ferry') {
            $scope = (new FerryScopeResolver())->evaluate($scopeMeta);
            $contract = (new FerryContractResolver())->evaluate($contractMeta, $scope);
            $result['ferry_scope'] = $scope;
            $result['ferry_contract'] = $contract;
            if ($includeIncident) {
                $result['ferry_rights'] = (new FerryRightsEvaluator())->evaluate($incidentMeta, $scope, $contract);
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function detectTransportMode(array $flow): string
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);

        $candidate = strtolower(trim((string)(
            $form['transport_mode']
            ?? $meta['transport_mode']
            ?? $journey['transport_mode']
            ?? ''
        )));

        return match ($candidate) {
            'ferry', 'bus', 'air', 'rail' => $candidate,
            default => 'rail',
        };
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function extractJourneyBasic(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);

        return [
            'operator' => $form['operator'] ?? ($auto['operator']['value'] ?? null),
            'operator_country' => $form['operator_country'] ?? ($auto['operator_country']['value'] ?? null),
            'operator_product' => $form['operator_product'] ?? ($auto['operator_product']['value'] ?? null),
            'ticket_no' => $form['ticket_no'] ?? ($journey['bookingRef'] ?? null) ?? ($meta['_identifiers']['pnr'] ?? null),
            'dep_station' => $form['dep_station'] ?? ($auto['dep_station']['value'] ?? null),
            'arr_station' => $form['arr_station'] ?? ($auto['arr_station']['value'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<int,array<string,mixed>>
     */
    private function extractSegments(array $flow): array
    {
        $journey = (array)($flow['journey'] ?? []);
        $meta = (array)($flow['meta'] ?? []);

        $segments = (array)($journey['segments'] ?? []);
        if ($segments !== []) {
            return $segments;
        }

        return (array)($meta['_segments_auto'] ?? []);
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<string,mixed> $journeyBasic
     * @param array<int,array<string,mixed>> $segments
     * @return array<string,mixed>
     */
    private function buildContractMeta(array $flow, array $journeyBasic, array $segments, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);

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

        $journeyStructure = match (true) {
            count($segments) > 1 && $transportMode === 'rail' => 'single_mode_connections',
            count($segments) > 1 => 'multimodal_connections',
            !empty($journeyBasic['dep_station']) || !empty($journeyBasic['arr_station']) => 'single_segment',
            default => 'unknown',
        };

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

        return [
            'seller_type' => $sellerType,
            'seller_name' => $journeyBasic['operator'] ?? null,
            'shared_booking_reference' => $sharedBookingReference,
            'single_transaction' => $singleTransaction,
            'contract_structure_disclosure' => $throughDisclosure,
            'separate_contract_notice' => $separateNotice,
            'journey_structure' => $journeyStructure,
            'contract_topology' => $contractTopology,
            'incident_segment_mode' => $incidentSegmentMode,
            'incident_segment_operator' => $form['incident_segment_operator'] ?? $journeyBasic['operator'] ?? null,
            'primary_claim_party' => match ($contractTopology) {
                'separate_contracts' => 'segment_operator',
                'single_mode_single_contract', 'protected_single_contract', 'single_multimodal_contract' => 'seller',
                default => 'manual_review',
            },
            'rights_module' => $incidentSegmentMode,
            'manual_review_required' => $contractTopology === 'unknown_manual_review',
        ];
    }

    /**
     * @param array<string,mixed> $flow
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
            'vessel_passenger_capacity' => $this->normalizeNullableInt($form['vessel_passenger_capacity'] ?? null),
            'vessel_operational_crew' => $this->normalizeNullableInt($form['vessel_operational_crew'] ?? null),
            'route_distance_meters' => $this->normalizeNullableInt($form['route_distance_meters'] ?? null),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function buildIncidentMeta(array $flow, string $transportMode): array
    {
        $form = (array)($flow['form'] ?? []);
        $incident = (array)($flow['incident'] ?? []);

        $incidentType = strtolower(trim((string)($incident['main'] ?? ($form['incident_main'] ?? ''))));
        if (!in_array($incidentType, ['delay', 'cancellation'], true)) {
            $incidentType = $incidentType !== '' ? $incidentType : null;
        }

        return [
            'transport_mode' => $transportMode,
            'incident_type' => $incidentType,
            'incident_missed' => $this->normalizeYesNoMachine($incident['missed'] ?? ($form['incident_missed'] ?? null)),
            'expected_delay_60' => $this->normalizeNullableBool($form['expected_delay_60'] ?? null),
            'delay_already_60' => $this->normalizeNullableBool($form['delay_already_60'] ?? null),
            'missed_expected_delay_60' => $this->normalizeNullableBool($form['missed_expected_delay_60'] ?? null),
            'national_delay_minutes' => $this->normalizeNullableInt($form['national_delay_minutes'] ?? null),
            'preinformed_disruption' => $this->normalizeNullableBool($form['preinformed_disruption'] ?? null),
            'preinfo_channel' => $form['preinfo_channel'] ?? null,
            'realtime_info_seen' => $form['realtime_info_seen'] ?? null,
            'operator_exceptional_circumstances' => $this->normalizeNullableBool($form['operatorExceptionalCircumstances'] ?? null),
            'operator_exceptional_type' => $form['operatorExceptionalType'] ?? null,
            'minimum_threshold_applies' => $this->normalizeNullableBool($form['minThresholdApplies'] ?? null),
            'arrival_delay_minutes' => $this->normalizeNullableInt($form['arrival_delay_minutes'] ?? null),
            'scheduled_journey_duration_minutes' => $this->normalizeNullableInt($form['scheduled_journey_duration_minutes'] ?? null),
            'expected_departure_delay_90' => $this->normalizeNullableBool($form['expected_departure_delay_90'] ?? null),
            'actual_departure_delay_90' => $this->normalizeNullableBool($form['actual_departure_delay_90'] ?? null),
            'overnight_required' => $this->normalizeNullableBool($form['overnight_required'] ?? null),
            'informed_before_purchase' => $this->normalizeNullableBool($form['informed_before_purchase'] ?? null),
            'passenger_fault' => $this->normalizeNullableBool($form['passenger_fault'] ?? null),
            'weather_safety' => $this->normalizeNullableBool($form['weather_safety'] ?? null),
            'extraordinary_circumstances' => $this->normalizeNullableBool($form['extraordinary_circumstances'] ?? null),
            'open_ticket_without_departure_time' => $this->normalizeNullableBool($form['open_ticket_without_departure_time'] ?? null),
            'season_ticket' => array_key_exists('season_ticket', $form)
                ? $this->normalizeNullableBool($form['season_ticket'])
                : (($form['ticket_upload_mode'] ?? null) === 'seasonpass'),
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
        if (is_bool($value)) {
            return $value;
        }

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
}
