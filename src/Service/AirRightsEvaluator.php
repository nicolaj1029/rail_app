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
        $delayBand = strtolower(trim((string)($incidentMeta['delay_departure_band'] ?? '')));
        $boardingDenied = $this->toBool($incidentMeta['boarding_denied'] ?? null);
        $voluntaryDenied = $this->toBool($incidentMeta['voluntary_denied_boarding'] ?? null);
        $protectedMissed = $this->toBool($incidentMeta['protected_connection_missed'] ?? null);
        $rerouteArrivalDelay = (int)($incidentMeta['reroute_arrival_delay_minutes'] ?? 0);
        $extraordinary = $this->toBool($incidentMeta['extraordinary_circumstances'] ?? null) === true;
        $cancellationNoticeBand = strtolower(trim((string)($incidentMeta['cancellation_notice_band'] ?? '')));
        $rerouteDepartureBand = strtolower(trim((string)($incidentMeta['reroute_departure_band'] ?? '')));
        $rerouteArrivalBand = strtolower(trim((string)($incidentMeta['reroute_arrival_band'] ?? '')));
        $rerouteOffered = $this->toBool($incidentMeta['reroute_offered'] ?? null) === true;
        $pmrUser = $this->toBool($incidentMeta['pmr_user'] ?? null) === true;
        $pmrCompanion = $this->toBool($incidentMeta['pmr_companion'] ?? null) === true;
        $pmrServiceDog = $this->toBool($incidentMeta['pmr_service_dog'] ?? null) === true;
        $unaccompaniedMinor = $this->toBool($incidentMeta['unaccompanied_minor'] ?? null) === true;
        $connectionType = (string)($contractResult['air_connection_type'] ?? 'unknown');
        $isProtected = in_array($connectionType, ['protected_connection', 'single_flight'], true);
        $distanceBand = strtolower(trim((string)($scopeResult['air_distance_band'] ?? '')));
        $delayThresholdHours = isset($scopeResult['air_delay_threshold_hours']) && is_numeric($scopeResult['air_delay_threshold_hours'])
            ? (int)$scopeResult['air_delay_threshold_hours']
            : null;

        $deniedBoarding = $incidentType === 'denied_boarding' || $boardingDenied === true;
        $nonVoluntaryDenied = $deniedBoarding && $voluntaryDenied !== true;
        $cancellation = $incidentType === 'cancellation';
        $delay = $incidentType === 'delay';
        $missedProtectedConnection = $incidentType === 'missed_connection' && $isProtected && ($protectedMissed !== false);
        $longArrivalDelay = $arrivalDelay >= 180;
        $delayThresholdMet = false;
        $delayFivePlus = false;

        if ($delayBand === 'five_plus') {
            $delayThresholdMet = true;
            $delayFivePlus = true;
        } elseif ($delayBand === 'threshold_to_under_5h') {
            $delayThresholdMet = true;
        } elseif ($delay && $delayThresholdHours !== null && $delayThresholdHours > 0 && $departureDelay > 0) {
            $delayThresholdMet = $departureDelay >= ($delayThresholdHours * 60);
            $delayFivePlus = $departureDelay >= 300;
        }

        $gateArt11PriorityAssistance = $scopeApplies
            && in_array($incidentType, ['delay', 'cancellation', 'denied_boarding'], true)
            && ($pmrUser || $unaccompaniedMinor);
        $priorityTransport = $pmrUser || $unaccompaniedMinor;

        $gateCare = $scopeApplies && (
            $nonVoluntaryDenied
            || $cancellation
            || $missedProtectedConnection
            || ($delay && $delayThresholdMet)
            || $gateArt11PriorityAssistance
        );
        // Art. 4(1): volunteers still receive Art. 8 assistance (refund/reroute), but not Art. 7 compensation.
        $gateRefundReroute = $scopeApplies && ($deniedBoarding || $cancellation || $missedProtectedConnection);
        $gateDelayRefund5h = $scopeApplies && $delay && $delayFivePlus;

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
        } elseif ($cancellation) {
            if ($cancellationNoticeBand === '14_plus_days') {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_notice_14_plus_days';
            } elseif (
                $cancellationNoticeBand === '7_to_13_days'
                && $rerouteOffered
                && $rerouteDepartureBand === 'within_window'
                && $rerouteArrivalBand === 'within_window'
            ) {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_reroute_within_2h_4h';
            } elseif (
                $cancellationNoticeBand === 'under_7_days'
                && $rerouteOffered
                && $rerouteDepartureBand === 'within_window'
                && $rerouteArrivalBand === 'within_window'
            ) {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_reroute_within_1h_2h';
            }
        } elseif ($extraordinary) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'extraordinary_circumstances';
        }
        if ($compensationCandidate && $extraordinary) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'extraordinary_circumstances';
        }

        $band = $compensationCandidate ? 'candidate' : $compensationBlockedReason;

        return [
            'gate_air_care' => $gateCare,
            'gate_air_reroute_refund' => $gateRefundReroute,
            'gate_air_delay_refund_5h' => $gateDelayRefund5h,
            'gate_air_compensation' => $compensationCandidate,
            'gate_air_denied_boarding' => $nonVoluntaryDenied,
            'gate_air_priority_transport' => $priorityTransport,
            'gate_air_art11_priority_assistance' => $gateArt11PriorityAssistance,
            'air_pmr_companion' => $pmrCompanion,
            'air_pmr_service_dog' => $pmrServiceDog,
            'air_unaccompanied_minor' => $unaccompaniedMinor,
            'air_comp_band' => $band,
            'manual_review_required' => !$scopeApplies ? false : ($connectionType === 'unknown'),
            'connection_type' => $connectionType,
            'compensation_block_reason' => $compensationBlockedReason,
            'arrival_delay_minutes' => $arrivalDelay,
            'reroute_arrival_delay_minutes' => $rerouteArrivalDelay,
            'cancellation_notice_band' => $cancellationNoticeBand,
            'reroute_departure_band' => $rerouteDepartureBand,
            'reroute_arrival_band' => $rerouteArrivalBand,
            'air_distance_band' => $distanceBand,
            'air_delay_threshold_hours' => $delayThresholdHours,
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
