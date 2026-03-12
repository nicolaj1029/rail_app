<?php
declare(strict_types=1);

namespace App\Service;

final class AirRightsEvaluator
{
    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $contractResult
     * @return array<string,mixed>
     */
    public function evaluate(array $incidentMeta, array $scopeResult, array $contractResult): array
    {
        $scopeApplies = (bool)($scopeResult['regulation_applies'] ?? false);
        $incidentType = strtolower(trim((string)($incidentMeta['incident_type'] ?? '')));
        $arrivalDelay = (int)($incidentMeta['arrival_delay_minutes'] ?? 0);
        $departureDelay = (int)($incidentMeta['delay_minutes_departure'] ?? 0);
        $boardingDenied = $this->toBool($incidentMeta['boarding_denied'] ?? null);
        $voluntaryDenied = $this->toBool($incidentMeta['voluntary_denied_boarding'] ?? null);
        $protectedMissed = $this->toBool($incidentMeta['protected_connection_missed'] ?? null);
        $rerouteArrivalDelay = (int)($incidentMeta['reroute_arrival_delay_minutes'] ?? 0);
        $extraordinary = $this->toBool($incidentMeta['extraordinary_circumstances'] ?? null) === true;
        $connectionType = (string)($contractResult['air_connection_type'] ?? 'unknown');
        $isProtected = in_array($connectionType, ['protected_connection', 'single_flight'], true);

        $deniedBoarding = $incidentType === 'denied_boarding' || $boardingDenied === true;
        $nonVoluntaryDenied = $deniedBoarding && $voluntaryDenied !== true;
        $cancellation = $incidentType === 'cancellation';
        $delay = $incidentType === 'delay';
        $missedProtectedConnection = $incidentType === 'missed_connection' && $isProtected && ($protectedMissed !== false);
        $longArrivalDelay = $arrivalDelay >= 180;
        $extendedDepartureDelay = $departureDelay >= 120;

        $gateCare = $scopeApplies && ($nonVoluntaryDenied || $cancellation || $delay || $missedProtectedConnection || $extendedDepartureDelay || $longArrivalDelay);
        $gateRefundReroute = $scopeApplies && ($nonVoluntaryDenied || $cancellation || $missedProtectedConnection || $longArrivalDelay);

        $compensationCandidate = $scopeApplies && ($nonVoluntaryDenied || $cancellation || $longArrivalDelay || $missedProtectedConnection);
        $compensationBlockedReason = 'none';
        if (!$scopeApplies) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'scope_excluded';
        } elseif ($deniedBoarding && $voluntaryDenied === true) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'voluntary_denied_boarding';
        } elseif ($incidentType === 'missed_connection' && !$isProtected) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'self_transfer_or_unprotected_connection';
        } elseif ($extraordinary) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'extraordinary_circumstances';
        }

        if ($cancellation && $rerouteArrivalDelay > 0 && $rerouteArrivalDelay < 180) {
            $compensationBlockedReason = 'reroute_under_threshold';
            $compensationCandidate = false;
        }

        $band = $compensationCandidate ? 'candidate' : $compensationBlockedReason;

        return [
            'gate_air_care' => $gateCare,
            'gate_air_reroute_refund' => $gateRefundReroute,
            'gate_air_compensation' => $compensationCandidate,
            'gate_air_denied_boarding' => $nonVoluntaryDenied,
            'air_comp_band' => $band,
            'manual_review_required' => !$scopeApplies ? false : ($connectionType === 'unknown'),
            'connection_type' => $connectionType,
            'compensation_block_reason' => $compensationBlockedReason,
            'arrival_delay_minutes' => $arrivalDelay,
            'reroute_arrival_delay_minutes' => $rerouteArrivalDelay,
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
}
