<?php
declare(strict_types=1);

namespace App\Service;

final class AirOperationalEvidenceService
{
    /**
     * @param array<string,mixed> $selectedFlight
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $selectedLeg
     * @return array<string,mixed>
     */
    public function evaluate(array $selectedFlight, array $form = [], array $meta = [], array $selectedLeg = []): array
    {
        if ($selectedFlight === []) {
            return [
                'available' => false,
                'source' => '',
                'summary' => '',
                'needs_manual_review' => 'yes',
                'match_checks' => [],
                'evidence_score' => 0,
                'confidence' => 'low',
            ];
        }

        $source = strtolower(trim((string)($selectedFlight['source'] ?? 'unknown')));
        $status = trim((string)($selectedFlight['status'] ?? ''));
        $scheduledDeparture = trim((string)($selectedFlight['scheduled_departure_local'] ?? ''));
        $scheduledArrival = trim((string)($selectedFlight['scheduled_arrival_local'] ?? ''));
        $estimatedDeparture = trim((string)($selectedFlight['estimated_departure_local'] ?? ($selectedFlight['revised_departure_local'] ?? '')));
        $estimatedArrival = trim((string)($selectedFlight['estimated_arrival_local'] ?? ($selectedFlight['revised_arrival_local'] ?? '')));
        $actualDeparture = trim((string)($selectedFlight['actual_departure_local'] ?? ''));
        $actualArrival = trim((string)($selectedFlight['actual_arrival_local'] ?? ''));
        $cancelled = $this->toBool($selectedFlight['cancelled'] ?? null) ?? $this->statusLooksCancelled($status);

        $checks = [];
        $score = $this->baseScoreForSource($source);
        $hasMismatch = false;

        $depCodeCurrent = strtoupper(trim((string)($form['dep_station_lookup_code'] ?? '')));
        $arrCodeCurrent = strtoupper(trim((string)($form['arr_station_lookup_code'] ?? '')));
        $depCodeDetected = strtoupper(trim((string)($selectedFlight['departure_airport_iata'] ?? ($selectedLeg['dep_iata'] ?? ''))));
        $arrCodeDetected = strtoupper(trim((string)($selectedFlight['arrival_airport_iata'] ?? ($selectedLeg['arr_iata'] ?? ''))));
        $routeStatus = 'unknown';
        if ($depCodeCurrent !== '' && $arrCodeCurrent !== '' && $depCodeDetected !== '' && $arrCodeDetected !== '') {
            $routeStatus = ($depCodeCurrent === $depCodeDetected && $arrCodeCurrent === $arrCodeDetected) ? 'match' : 'mismatch';
        } elseif ($depCodeDetected !== '' || $arrCodeDetected !== '') {
            $routeStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Rute',
            'status' => $routeStatus,
            'current' => trim(implode(' -> ', array_filter([$depCodeCurrent, $arrCodeCurrent]))),
            'detected' => trim(implode(' -> ', array_filter([$depCodeDetected, $arrCodeDetected]))),
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $routeStatus, 18, 18);

        $currentDate = $this->normalizeDate((string)($form['dep_date'] ?? ''));
        $detectedDate = $this->normalizeDate($scheduledDeparture);
        $dateStatus = 'unknown';
        if ($currentDate !== '' && $detectedDate !== '') {
            $dateStatus = $currentDate === $detectedDate ? 'match' : 'mismatch';
        } elseif ($detectedDate !== '') {
            $dateStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Dato',
            'status' => $dateStatus,
            'current' => $currentDate,
            'detected' => $detectedDate,
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $dateStatus, 14, 14);

        $currentFlight = strtoupper(trim((string)($form['ticket_no'] ?? ($form['flight_number'] ?? ''))));
        $detectedFlight = strtoupper(trim((string)($selectedFlight['flight_number'] ?? '')));
        $flightStatus = 'unknown';
        if ($currentFlight !== '' && $detectedFlight !== '') {
            $flightStatus = $currentFlight === $detectedFlight ? 'match' : 'mismatch';
        } elseif ($detectedFlight !== '') {
            $flightStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Flynummer',
            'status' => $flightStatus,
            'current' => $currentFlight,
            'detected' => $detectedFlight,
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $flightStatus, 18, 16);

        $currentCarrier = $this->normalizeCompareText((string)($form['marketing_carrier'] ?? ($form['operator'] ?? '')));
        $detectedCarrier = $this->normalizeCompareText((string)($selectedFlight['carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? '')));
        $carrierStatus = 'unknown';
        if ($currentCarrier !== '' && $detectedCarrier !== '') {
            $carrierStatus = $currentCarrier === $detectedCarrier ? 'match' : 'mismatch';
        } elseif ($detectedCarrier !== '') {
            $carrierStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Carrier',
            'status' => $carrierStatus,
            'current' => trim((string)($form['marketing_carrier'] ?? ($form['operator'] ?? ''))),
            'detected' => trim((string)($selectedFlight['carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ''))),
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $carrierStatus, 10, 10);

        $currentDepTime = $this->normalizeTime((string)($form['dep_time'] ?? ''));
        $detectedDepTime = $this->normalizeTime($scheduledDeparture);
        $timeStatus = 'unknown';
        if ($currentDepTime !== '' && $detectedDepTime !== '') {
            $timeStatus = $currentDepTime === $detectedDepTime ? 'match' : 'partial';
        } elseif ($detectedDepTime !== '') {
            $timeStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Afgangstid',
            'status' => $timeStatus,
            'current' => $currentDepTime,
            'detected' => $detectedDepTime,
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $timeStatus, 8, 0);

        $delayMinutes = $this->minutesDifference($scheduledArrival, $actualArrival !== '' ? $actualArrival : $estimatedArrival);
        $confidence = $hasMismatch ? 'low' : ($score >= 80 ? 'high' : ($score >= 55 ? 'medium' : 'low'));
        $needsManualReview = $hasMismatch || $score < 55 ? 'yes' : 'no';

        $summaryParts = [];
        if ($source !== '') {
            $summaryParts[] = 'Kilde: ' . strtoupper($source);
        }
        if ($status !== '') {
            $summaryParts[] = 'Status: ' . $status;
        }
        if ($delayMinutes !== null && $delayMinutes > 0) {
            $summaryParts[] = 'Ankomstafvigelse ca. ' . $delayMinutes . ' min';
        }
        if ($cancelled) {
            $summaryParts[] = 'Status peger paa cancellation';
        }
        $summaryParts[] = $needsManualReview === 'yes'
            ? 'Operationelle data kraever manuel kontrol.'
            : 'Operationelle data matcher frontflowet godt.';

        return [
            'available' => true,
            'source' => $source,
            'status' => $status,
            'scheduled_departure_local' => $scheduledDeparture,
            'scheduled_arrival_local' => $scheduledArrival,
            'estimated_departure_local' => $estimatedDeparture,
            'estimated_arrival_local' => $estimatedArrival,
            'actual_departure_local' => $actualDeparture,
            'actual_arrival_local' => $actualArrival,
            'cancelled' => $cancelled ? 'yes' : 'no',
            'delay_minutes_estimated' => $delayMinutes,
            'match_checks' => $checks,
            'evidence_score' => max(0, min(100, $score)),
            'confidence' => $confidence,
            'needs_manual_review' => $needsManualReview,
            'summary' => implode(' · ', array_filter($summaryParts)),
            'allowed_uses' => [
                'flight_lookup',
                'incident_prefill_support',
                'live_estimate_support',
                'admin_plausibility_check',
            ],
            'disallowed_uses' => [
                'final_legal_decision_only',
                'extraordinary_circumstances_final',
                'sole_dispute_proof',
            ],
        ];
    }

    private function baseScoreForSource(string $source): int
    {
        return match ($source) {
            'aerodatabox' => 35,
            'aviationstack' => 28,
            'manual' => 8,
            'manual_fallback_seed', 'ticketless_seed' => 12,
            default => 15,
        };
    }

    private function applyScore(int $score, bool $hasMismatch, string $status, int $matchPoints, int $mismatchPenalty): array
    {
        if ($status === 'match') {
            $score += $matchPoints;
        } elseif ($status === 'partial') {
            $score += (int)max(4, floor($matchPoints / 2));
        } elseif ($status === 'mismatch') {
            $score -= $mismatchPenalty;
            $hasMismatch = true;
        }

        return [$score, $hasMismatch];
    }

    private function normalizeCompareText(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return preg_replace('/[^a-z0-9 ]/iu', '', $value) ?? $value;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, 'T')) {
            return substr($value, 0, 10);
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return $value;
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, 'T')) {
            $value = substr($value, strpos($value, 'T') + 1);
        }
        return preg_match('/^\d{2}:\d{2}/', $value, $m) ? $m[0] : $value;
    }

    private function minutesDifference(string $scheduled, string $observed): ?int
    {
        if ($scheduled === '' || $observed === '') {
            return null;
        }
        $scheduledTs = strtotime($scheduled);
        $observedTs = strtotime($observed);
        if ($scheduledTs === false || $observedTs === false) {
            return null;
        }

        return (int)round(($observedTs - $scheduledTs) / 60);
    }

    private function statusLooksCancelled(string $status): bool
    {
        $status = strtolower(trim($status));
        return $status !== '' && (str_contains($status, 'cancel') || str_contains($status, 'annul'));
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
