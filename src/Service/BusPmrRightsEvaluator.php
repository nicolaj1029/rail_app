<?php
declare(strict_types=1);

namespace App\Service;

final class BusPmrRightsEvaluator
{
    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @return array<string,mixed>
     */
    public function evaluate(array $incidentMeta, array $scopeResult): array
    {
        $scopeApplies = (bool)($scopeResult['regulation_applies'] ?? false);
        $pmrUser = $this->toBool($incidentMeta['pmr_user'] ?? null) === true;
        $companion = $this->toBool($incidentMeta['bus_pmr_companion'] ?? null) === true;
        $notice36h = $this->toBool($incidentMeta['bus_pmr_notice_36h'] ?? null) === true;
        $metTerminalTime = $this->toBool($incidentMeta['bus_pmr_met_terminal_time'] ?? null) === true;
        $specialSeatingNotified = $this->toBool($incidentMeta['bus_pmr_special_seating_notified'] ?? null) === true;
        $assistanceDelivered = strtolower(trim((string)($incidentMeta['bus_pmr_assistance_delivered'] ?? 'unknown')));
        if (!in_array($assistanceDelivered, ['full', 'partial', 'none', 'unknown'], true)) {
            $assistanceDelivered = 'unknown';
        }
        $boardingRefused = $this->toBool($incidentMeta['bus_pmr_boarding_refused'] ?? null) === true;
        $reasonGiven = $this->toBool($incidentMeta['bus_pmr_reason_given'] ?? null) === true;
        $alternativeTransportOffered = $this->toBool($incidentMeta['bus_pmr_alternative_transport_offered'] ?? null) === true;
        $refusalBasis = strtolower(trim((string)($incidentMeta['bus_pmr_refusal_basis'] ?? 'other_or_unknown')));
        if (!in_array($refusalBasis, ['safety_requirements', 'impossible_infrastructure', 'other_or_unknown'], true)) {
            $refusalBasis = 'other_or_unknown';
        }

        $assistanceProblem = in_array($assistanceDelivered, ['partial', 'none'], true);
        $fulfilledNoticeConditions = $notice36h && $metTerminalTime;

        $gateAssistance = $scopeApplies && $pmrUser && $assistanceProblem && $fulfilledNoticeConditions;
        $gateAssistancePartial = $scopeApplies && $pmrUser && $assistanceProblem && !$gateAssistance;
        $gateBoardingRemedy = $scopeApplies && $pmrUser && $boardingRefused && $notice36h;
        $gateReasonNotice = $scopeApplies && $pmrUser && $boardingRefused && !$reasonGiven;

        return [
            'gate_bus_pmr_assistance' => $gateAssistance,
            'gate_bus_pmr_assistance_partial' => $gateAssistancePartial,
            'gate_bus_pmr_boarding_remedy' => $gateBoardingRemedy,
            'gate_bus_pmr_reason_notice' => $gateReasonNotice,
            'pmr_companion' => $companion,
            'pmr_notice_36h' => $notice36h,
            'pmr_met_terminal_time' => $metTerminalTime,
            'pmr_special_seating_notified' => $specialSeatingNotified,
            'pmr_assistance_delivered' => $assistanceDelivered,
            'pmr_boarding_refused' => $boardingRefused,
            'pmr_refusal_basis' => $refusalBasis,
            'pmr_reason_given' => $reasonGiven,
            'pmr_alternative_transport_offered' => $alternativeTransportOffered,
            'reason' => $this->buildReason(
                $scopeApplies,
                $pmrUser,
                $assistanceProblem,
                $fulfilledNoticeConditions,
                $boardingRefused,
                $notice36h,
                $reasonGiven
            ),
        ];
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

    private function buildReason(
        bool $scopeApplies,
        bool $pmrUser,
        bool $assistanceProblem,
        bool $fulfilledNoticeConditions,
        bool $boardingRefused,
        bool $notice36h,
        bool $reasonGiven
    ): string {
        if (!$scopeApplies) {
            return 'out_of_scope';
        }
        if (!$pmrUser) {
            return 'no_pmr_context';
        }
        if ($boardingRefused && $notice36h && !$reasonGiven) {
            return 'boarding_refused_no_reason_notice';
        }
        if ($boardingRefused && $notice36h) {
            return 'boarding_refused_with_notice';
        }
        if ($assistanceProblem && $fulfilledNoticeConditions) {
            return 'assistance_not_delivered_after_notice';
        }
        if ($assistanceProblem) {
            return 'assistance_not_delivered_reasonable_efforts';
        }

        return 'none';
    }
}
