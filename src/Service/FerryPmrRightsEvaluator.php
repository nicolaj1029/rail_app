<?php
declare(strict_types=1);

namespace App\Service;

final class FerryPmrRightsEvaluator
{
    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $contractResult
     * @return array<string,mixed>
     */
    public function evaluate(array $incidentMeta, array $scopeResult = [], array $contractResult = []): array
    {
        $regulationApplies = (bool)($scopeResult['regulation_applies'] ?? false);
        $pmrUser = $this->toBool($incidentMeta['pmr_user'] ?? null);
        $companion = $this->toBool($incidentMeta['ferry_pmr_companion'] ?? null);
        $serviceDog = $this->toBool($incidentMeta['ferry_pmr_service_dog'] ?? null);
        $notice48h = $this->toBool($incidentMeta['ferry_pmr_notice_48h'] ?? null);
        $metCheckinTime = $this->toBool($incidentMeta['ferry_pmr_met_checkin_time'] ?? null);
        $assistanceDelivered = $this->normalizeAssistanceStatus($incidentMeta['ferry_pmr_assistance_delivered'] ?? null);
        $boardingRefused = $this->toBool($incidentMeta['ferry_pmr_boarding_refused'] ?? null);
        $refusalBasis = $this->normalizeRefusalBasis($incidentMeta['ferry_pmr_refusal_basis'] ?? null);
        $reasonGiven = $this->toBool($incidentMeta['ferry_pmr_reason_given'] ?? null);
        $alternativeTransportOffered = $this->toBool($incidentMeta['ferry_pmr_alternative_transport_offered'] ?? null);

        if (!$regulationApplies || !$pmrUser) {
            return [
                'gate_ferry_pmr_assistance' => false,
                'gate_ferry_pmr_assistance_partial' => false,
                'gate_ferry_pmr_boarding_remedy' => false,
                'gate_ferry_pmr_reason_notice' => false,
                'notice_requirement_met' => false,
                'assistance_delivered' => $assistanceDelivered,
                'refusal_basis' => $refusalBasis,
                'ferry_pmr_companion' => $companion,
                'ferry_pmr_service_dog' => $serviceDog,
                'ferry_pmr_alternative_transport_offered' => $alternativeTransportOffered,
                'reason' => $regulationApplies ? 'no_pmr_signal' : 'scope_excluded',
            ];
        }

        $noticeRequirementMet = $notice48h && $metCheckinTime;
        $assistanceIssue = in_array($assistanceDelivered, ['partial', 'none'], true);
        $gateAssistance = $assistanceIssue;
        $gateAssistancePartial = $assistanceDelivered === 'partial';
        $gateBoardingRemedy = $boardingRefused;
        $gateReasonNotice = $gateBoardingRemedy && !$reasonGiven;

        return [
            'gate_ferry_pmr_assistance' => $gateAssistance,
            'gate_ferry_pmr_assistance_partial' => $gateAssistancePartial,
            'gate_ferry_pmr_boarding_remedy' => $gateBoardingRemedy,
            'gate_ferry_pmr_reason_notice' => $gateReasonNotice,
            'notice_requirement_met' => $noticeRequirementMet,
            'assistance_delivered' => $assistanceDelivered,
            'refusal_basis' => $refusalBasis,
            'ferry_pmr_companion' => $companion,
            'ferry_pmr_service_dog' => $serviceDog,
            'ferry_pmr_alternative_transport_offered' => $alternativeTransportOffered,
            'reason' => null,
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['yes', 'ja', 'y', '1', 'true'], true);
    }

    private function normalizeAssistanceStatus(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['full', 'partial', 'none', 'unknown'], true) ? $normalized : 'unknown';
    }

    private function normalizeRefusalBasis(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['safety_requirements', 'port_or_ship_infrastructure', 'other_or_unknown'], true)
            ? $normalized
            : 'other_or_unknown';
    }
}
