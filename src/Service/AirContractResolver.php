<?php
declare(strict_types=1);

namespace App\Service;

final class AirContractResolver
{
    /**
     * @param array<string,mixed> $contractMeta
     * @param array<string,mixed> $scopeResult
     * @return array<string,mixed>
     */
    public function evaluate(array $contractMeta, array $scopeResult = []): array
    {
        $topology = (string)($contractMeta['contract_topology'] ?? 'unknown_manual_review');
        $sellerType = (string)($contractMeta['seller_type'] ?? 'unknown');
        $sellerName = trim((string)($contractMeta['seller_name'] ?? ''));
        $incidentSegmentMode = (string)($contractMeta['incident_segment_mode'] ?? 'air');
        $incidentSegmentOperator = trim((string)($contractMeta['incident_segment_operator'] ?? ''));

        $samePnr = $this->toBool($contractMeta['same_pnr'] ?? null);
        $sameBooking = $this->toBool($contractMeta['same_booking_reference'] ?? null);
        $sameEticket = $this->toBool($contractMeta['same_eticket'] ?? null);
        $selfTransferNotice = $this->toBool($contractMeta['self_transfer_notice'] ?? null);
        $declaredConnectionType = strtolower(trim((string)($contractMeta['air_connection_type'] ?? '')));
        $marketingCarrier = trim((string)($contractMeta['marketing_carrier'] ?? $sellerName));
        $operatingCarrier = trim((string)($contractMeta['operating_carrier'] ?? $incidentSegmentOperator));

        $connectionType = $this->resolveConnectionType(
            $declaredConnectionType,
            $topology,
            $samePnr,
            $sameBooking,
            $sameEticket,
            $selfTransferNotice
        );

        $resolved = [
            'contract_resolver' => 'air',
            'contract_topology' => $topology,
            'seller_type' => $sellerType,
            'seller_name' => $sellerName,
            'incident_segment_mode' => $incidentSegmentMode,
            'incident_segment_operator' => $incidentSegmentOperator,
            'air_connection_type' => $connectionType,
            'marketing_carrier' => $marketingCarrier !== '' ? $marketingCarrier : null,
            'operating_carrier' => $operatingCarrier !== '' ? $operatingCarrier : null,
            'primary_claim_party' => 'manual_review',
            'primary_claim_party_name' => null,
            'rights_module' => 'air',
            'manual_review_required' => false,
            'decision_basis' => null,
            'scope_applies' => array_key_exists('regulation_applies', $scopeResult) ? (bool)$scopeResult['regulation_applies'] : null,
        ];

        if ($topology === 'unknown_manual_review' || $connectionType === 'unknown') {
            $resolved['manual_review_required'] = true;
            $resolved['decision_basis'] = 'unknown_contract_or_connection_type';
            return $resolved;
        }

        if ($topology === 'separate_contracts' || $connectionType === 'self_transfer') {
            $resolved['primary_claim_party'] = 'segment_operator';
            $resolved['primary_claim_party_name'] = $operatingCarrier !== '' ? $operatingCarrier : ($incidentSegmentOperator !== '' ? $incidentSegmentOperator : null);
            $resolved['decision_basis'] = 'separate_tickets_or_self_transfer_follow_affected_flight';
            $resolved['manual_review_required'] = $resolved['primary_claim_party_name'] === null;
            return $resolved;
        }

        if (in_array($topology, ['single_mode_single_contract', 'protected_single_contract', 'single_multimodal_contract'], true)) {
            if ($sellerType === 'operator') {
                $resolved['primary_claim_party'] = 'carrier';
                $resolved['primary_claim_party_name'] = $operatingCarrier !== '' ? $operatingCarrier : ($marketingCarrier !== '' ? $marketingCarrier : ($sellerName !== '' ? $sellerName : null));
                $resolved['decision_basis'] = $connectionType === 'protected_connection'
                    ? 'same_booking_protected_connection'
                    : 'single_flight_direct_carrier';
            } elseif (in_array($sellerType, ['ticket_vendor', 'travel_agent', 'tour_operator'], true)) {
                $resolved['primary_claim_party'] = 'seller';
                $resolved['primary_claim_party_name'] = $sellerName !== '' ? $sellerName : null;
                $resolved['decision_basis'] = 'single_contract_intermediary_sale';
                $resolved['manual_review_required'] = $resolved['primary_claim_party_name'] === null;
            } else {
                $resolved['manual_review_required'] = true;
                $resolved['decision_basis'] = 'single_contract_unknown_seller_type';
            }

            return $resolved;
        }

        $resolved['manual_review_required'] = true;
        $resolved['decision_basis'] = 'unmapped_air_contract_topology';

        return $resolved;
    }

    private function resolveConnectionType(
        string $declaredConnectionType,
        string $topology,
        ?bool $samePnr,
        ?bool $sameBooking,
        ?bool $sameEticket,
        ?bool $selfTransferNotice
    ): string {
        if (in_array($declaredConnectionType, ['single_flight', 'protected_connection', 'self_transfer'], true)) {
            return $declaredConnectionType;
        }

        if ($selfTransferNotice === true || $topology === 'separate_contracts') {
            return 'self_transfer';
        }

        if ($samePnr === true || $sameBooking === true || $sameEticket === true) {
            return $topology === 'single_mode_single_contract' ? 'single_flight' : 'protected_connection';
        }

        if ($topology === 'single_mode_single_contract') {
            return 'single_flight';
        }

        if (in_array($topology, ['protected_single_contract', 'single_multimodal_contract'], true)) {
            return 'protected_connection';
        }

        return 'unknown';
    }

    private function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            '1', 'true', 'yes', 'ja', 'y' => true,
            '0', 'false', 'no', 'nej', 'n' => false,
            default => null,
        };
    }
}
