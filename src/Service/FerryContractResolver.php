<?php
declare(strict_types=1);

namespace App\Service;

final class FerryContractResolver
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
        $sellerName = (string)($contractMeta['seller_name'] ?? '');
        $incidentSegmentMode = (string)($contractMeta['incident_segment_mode'] ?? 'unknown');
        $incidentSegmentOperator = (string)($contractMeta['incident_segment_operator'] ?? '');
        $scopeApplies = (bool)($scopeResult['regulation_applies'] ?? false);

        $rightsModule = $incidentSegmentMode !== '' && $incidentSegmentMode !== 'unknown'
            ? $incidentSegmentMode
            : 'ferry';

        $resolved = [
            'contract_resolver' => 'ferry',
            'contract_topology' => $topology,
            'seller_type' => $sellerType,
            'seller_name' => $sellerName,
            'incident_segment_mode' => $incidentSegmentMode,
            'incident_segment_operator' => $incidentSegmentOperator,
            'primary_claim_party' => 'manual_review',
            'primary_claim_party_name' => null,
            'rights_module' => $rightsModule,
            'manual_review_required' => false,
            'decision_basis' => null,
            'scope_blocks_rights' => !$scopeApplies,
        ];

        if ($topology === 'unknown_manual_review' || $incidentSegmentMode === 'unknown' || $incidentSegmentMode === '') {
            $resolved['manual_review_required'] = true;
            $resolved['decision_basis'] = 'unknown_contract_or_segment';

            return $resolved;
        }

        if ($topology === 'separate_contracts') {
            $resolved['primary_claim_party'] = 'segment_operator';
            $resolved['primary_claim_party_name'] = $incidentSegmentOperator !== '' ? $incidentSegmentOperator : null;
            $resolved['decision_basis'] = 'separate_contracts_follow_affected_segment';
            $resolved['manual_review_required'] = $incidentSegmentOperator === '';

            return $resolved;
        }

        if (in_array($topology, ['single_mode_single_contract', 'protected_single_contract', 'single_multimodal_contract'], true)) {
            if ($sellerType === 'operator') {
                $resolved['primary_claim_party'] = 'carrier';
                $resolved['primary_claim_party_name'] = $sellerName !== '' ? $sellerName : null;
                $resolved['decision_basis'] = 'single_contract_direct_carrier';
            } elseif (in_array($sellerType, ['ticket_vendor', 'travel_agent', 'tour_operator'], true)) {
                $resolved['primary_claim_party'] = 'seller';
                $resolved['primary_claim_party_name'] = $sellerName !== '' ? $sellerName : null;
                $resolved['decision_basis'] = 'single_contract_intermediary_or_packager';
            } else {
                $resolved['primary_claim_party'] = 'manual_review';
                $resolved['decision_basis'] = 'single_contract_unknown_seller_type';
                $resolved['manual_review_required'] = true;
            }

            return $resolved;
        }

        $resolved['manual_review_required'] = true;
        $resolved['decision_basis'] = 'unmapped_contract_topology';

        return $resolved;
    }
}
