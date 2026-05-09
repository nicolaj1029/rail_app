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
        $travelState = strtolower(trim((string)($incidentMeta['travel_state'] ?? '')));
        $isOngoing = $travelState === 'ongoing';
        $arrivalDelay = (int)($incidentMeta['arrival_delay_minutes'] ?? 0);
        $departureDelay = (int)($incidentMeta['delay_minutes_departure'] ?? 0);
        $delayBand = strtolower(trim((string)($incidentMeta['delay_departure_band'] ?? '')));
        $boardingDenied = $this->toBool($incidentMeta['boarding_denied'] ?? null);
        $voluntaryDenied = $this->toBool($incidentMeta['voluntary_denied_boarding'] ?? null);
        $protectedMissed = $this->toBool($incidentMeta['protected_connection_missed'] ?? null);
        $rerouteArrivalDelay = (int)($incidentMeta['reroute_arrival_delay_minutes'] ?? 0);
        $extraordinary = $this->toBool(
            $incidentMeta['operatorExceptionalCircumstances']
                ?? $incidentMeta['operator_exceptional_circumstances']
                ?? $incidentMeta['extraordinary_circumstances']
                ?? null
        ) === true;
        $extraordinaryReason = $this->normalizeExtraordinaryReason((string)(
            $incidentMeta['operatorExceptionalType']
                ?? $incidentMeta['operator_exceptional_type']
                ?? $incidentMeta['force_majeure_reason']
                ?? ''
        ));
        $fmFlightPossible = $this->toBool($incidentMeta['fm_flight_possible'] ?? null);
        $fmAlternativesAvailable = $this->toBool($incidentMeta['fm_alternatives'] ?? null);
        $fmOtherFlightsOperating = $this->toBool($incidentMeta['fm_other_flights'] ?? null);
        $fmSuddenEvent = $this->toBool($incidentMeta['fm_sudden'] ?? null);
        $fmOutsideAirlineControl = $this->toBool($incidentMeta['fm_external_control'] ?? null);
        $fmAirlineEvidence = $this->toBool($incidentMeta['fm_documentation'] ?? null);
        $cancellationNoticeBand = $this->normalizeCancellationNoticeBand((string)($incidentMeta['cancellation_notice_band'] ?? ''));
        $rerouteDepartureBand = strtolower(trim((string)($incidentMeta['reroute_departure_band'] ?? '')));
        $rerouteArrivalBand = strtolower(trim((string)($incidentMeta['reroute_arrival_band'] ?? '')));
        $remedyChoice = strtolower(trim((string)($incidentMeta['remedy_choice'] ?? '')));
        $rerouteOfferedState = $this->toBool($incidentMeta['reroute_offered'] ?? null);
        $rerouteOffered = $rerouteOfferedState === true;
        $rerouteUsedOrAccepted = $this->toBool($incidentMeta['reroute_used_or_accepted'] ?? null);
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
        $cancellationRerouteChosen = in_array($remedyChoice, ['reroute_soonest', 'reroute_later'], true);
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
        $eligibilityStatus = $compensationCandidate ? 'eligible' : 'not_eligible';
        $manualReviewRequired = $scopeApplies && ($connectionType === 'unknown');
        if (!$scopeApplies) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'scope_excluded';
            $eligibilityStatus = 'not_eligible';
        } elseif ($deniedBoarding && $voluntaryDenied === true) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'voluntary_denied_boarding';
            $eligibilityStatus = 'not_eligible';
        } elseif ($incidentType === 'missed_connection' && !$isProtected) {
            $compensationCandidate = false;
            $compensationBlockedReason = 'self_transfer_or_unprotected_connection';
            $eligibilityStatus = 'not_eligible';
        } elseif ($cancellation) {
            $noticeNeedsRerouteTest = in_array($cancellationNoticeBand, ['7_to_13_days', 'under_7_days'], true);
            $needsRerouteWindows = $noticeNeedsRerouteTest && $rerouteOfferedState === true;
            $hasUnknownNotice = $cancellationNoticeBand === '' || $cancellationNoticeBand === 'unknown';
            $hasUnknownRerouteOffer = $noticeNeedsRerouteTest && $rerouteOfferedState === null;
            $hasUnknownRerouteWindows = $needsRerouteWindows && (
                $rerouteDepartureBand === 'unknown'
                || $rerouteArrivalBand === 'unknown'
                || $rerouteDepartureBand === ''
                || $rerouteArrivalBand === ''
            );
            if ($hasUnknownNotice || $hasUnknownRerouteOffer || $hasUnknownRerouteWindows) {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_compensation_uncertain';
                $eligibilityStatus = 'uncertain';
            } elseif ($cancellationNoticeBand === '14_plus_days') {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_notice_14_plus_days';
                $eligibilityStatus = 'not_eligible';
            } elseif (
                $cancellationNoticeBand === '7_to_13_days'
                && $rerouteOffered
                && $rerouteDepartureBand === 'within_window'
                && $rerouteArrivalBand === 'within_window'
            ) {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_reroute_within_2h_4h';
                $eligibilityStatus = 'not_eligible';
            } elseif (
                $cancellationNoticeBand === 'under_7_days'
                && $rerouteOffered
                && $rerouteDepartureBand === 'within_window'
                && $rerouteArrivalBand === 'within_window'
            ) {
                $compensationCandidate = false;
                $compensationBlockedReason = 'cancellation_reroute_within_1h_2h';
                $eligibilityStatus = 'not_eligible';
            }
        }

        $extraordinaryScore = null;
        $extraordinaryBand = 'none';
        $extraordinaryEventLikely = false;
        if ($extraordinary || $extraordinaryReason !== '') {
            $extraordinaryScore = $this->scoreExtraordinaryCircumstances(
                $extraordinaryReason,
                $fmFlightPossible,
                $fmAlternativesAvailable,
                $fmOtherFlightsOperating,
                $fmSuddenEvent,
                $fmOutsideAirlineControl,
                $fmAirlineEvidence
            );
            $extraordinaryBand = $extraordinaryScore >= 70
                ? 'high'
                : ($extraordinaryScore >= 40 ? 'medium' : 'low');
            $extraordinaryEventLikely = $extraordinaryScore >= 70;

            if ($extraordinaryScore >= 40) {
                $manualReviewRequired = true;
                if ($eligibilityStatus === 'eligible') {
                    $eligibilityStatus = 'uncertain';
                    $compensationBlockedReason = 'extraordinary_circumstances_review';
                }
            }
        }

        $band = $compensationCandidate ? 'candidate' : $compensationBlockedReason;
        $baseAmount = match ($distanceBand) {
            'up_to_1500' => 250.0,
            'intra_eu_over_1500', 'other_1500_to_3500' => 400.0,
            'other_over_3500' => 600.0,
            default => 250.0,
        };
        $reductionThresholdMinutes = match ($distanceBand) {
            'up_to_1500' => 120,
            'intra_eu_over_1500', 'other_1500_to_3500' => 180,
            'other_over_3500' => 240,
            default => 120,
        };
        $reductionStatus = 'not_applicable';
        $reductionApplies = false;
        $finalAmount = $compensationCandidate ? $baseAmount : 0.0;
        if ($eligibilityStatus === 'eligible' && $rerouteUsedOrAccepted === true) {
            if ($rerouteArrivalDelay > 0) {
                $withinReductionWindow = $rerouteArrivalDelay <= $reductionThresholdMinutes;
                if ($isOngoing) {
                    $reductionApplies = false;
                    $reductionStatus = $withinReductionWindow ? 'provisional' : 'not_applicable';
                } else {
                    $reductionApplies = $withinReductionWindow;
                    $reductionStatus = $reductionApplies ? 'applied' : 'not_applicable';
                    if ($reductionApplies) {
                        $finalAmount = round($baseAmount / 2, 2);
                    }
                }
            } else {
                $reductionStatus = 'unknown';
            }
        } elseif (
            $eligibilityStatus === 'eligible'
            && $rerouteUsedOrAccepted === null
            && $cancellation
            && $cancellationRerouteChosen
        ) {
            $reductionStatus = 'unknown';
        }

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
            'manual_review_required' => $manualReviewRequired,
            'connection_type' => $connectionType,
            'compensation_block_reason' => $compensationBlockedReason,
            'article7_eligibility_status' => $eligibilityStatus,
            'article7_base_amount_eur' => $eligibilityStatus === 'eligible' ? $baseAmount : 0.0,
            'article7_reduction_status' => $reductionStatus,
            'article7_reduction_applies' => $reductionApplies,
            'article7_final_amount_eur' => $eligibilityStatus === 'eligible' ? $finalAmount : 0.0,
            'extraordinary_score' => $extraordinaryScore,
            'extraordinary_band' => $extraordinaryBand,
            'extraordinary_event_likely' => $extraordinaryEventLikely,
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

    private function normalizeCancellationNoticeBand(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'airport', 'at_airport', 'in_airport', 'airport_on_day_of_departure', 'day_of_departure_airport' => 'under_7_days',
            default => $normalized,
        };
    }

    private function normalizeExtraordinaryReason(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'technical_issue', 'technical' => 'technical',
            'crew_shortage', 'crew' => 'crew',
            'own_staff_strike', 'own_strike' => 'own_strike',
            'weather' => 'weather',
            'air_traffic_control', 'atc' => 'atc',
            'security' => 'security',
            'external_strike' => 'external_strike',
            'other' => 'other',
            default => '',
        };
    }

    private function scoreExtraordinaryCircumstances(
        string $reason,
        ?bool $flightPossible,
        ?bool $alternativesAvailable,
        ?bool $otherFlightsOperating,
        ?bool $suddenEvent,
        ?bool $outsideAirlineControl,
        ?bool $airlineEvidence
    ): int {
        $baseScore = match ($reason) {
            'technical' => 10,
            'crew' => 15,
            'own_strike' => 20,
            'weather' => 60,
            'atc' => 65,
            'security' => 70,
            'external_strike' => 75,
            'other' => 50,
            default => 50,
        };

        if (in_array($reason, ['technical', 'crew', 'own_strike'], true)) {
            return 0;
        }

        $score = $baseScore;
        if ($flightPossible === true) {
            $score -= 25;
        }
        if ($alternativesAvailable === true) {
            $score -= 20;
        }
        if ($otherFlightsOperating === true) {
            $score -= 15;
        }
        if ($suddenEvent === true) {
            $score += 20;
        }
        if ($outsideAirlineControl === true) {
            $score += 20;
        }
        if ($airlineEvidence === false) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }
}
