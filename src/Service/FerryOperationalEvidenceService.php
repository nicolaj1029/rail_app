<?php
declare(strict_types=1);

namespace App\Service;

final class FerryOperationalEvidenceService
{
    /**
     * @param array<string,mixed> $selectedDeparture
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function evaluate(array $selectedDeparture, array $form = [], array $meta = []): array
    {
        if ($selectedDeparture === []) {
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

        $source = strtolower(trim((string)($selectedDeparture['source'] ?? 'unknown')));
        $status = trim((string)($selectedDeparture['status'] ?? ''));
        $scheduledDeparture = trim((string)($selectedDeparture['scheduled_departure_local'] ?? ''));
        $scheduledArrival = trim((string)($selectedDeparture['scheduled_arrival_local'] ?? ''));
        $estimatedDeparture = trim((string)($selectedDeparture['estimated_departure_local'] ?? ''));
        $estimatedArrival = trim((string)($selectedDeparture['estimated_arrival_local'] ?? ''));
        $actualDeparture = trim((string)($selectedDeparture['actual_departure_local'] ?? ''));
        $actualArrival = trim((string)($selectedDeparture['actual_arrival_local'] ?? ''));
        $vesselName = trim((string)($selectedDeparture['vessel_name'] ?? ''));
        $operatorName = trim((string)($selectedDeparture['operator_name'] ?? ''));
        $imo = trim((string)($selectedDeparture['vessel_imo'] ?? ''));
        $mmsi = trim((string)($selectedDeparture['vessel_mmsi'] ?? ''));

        $checks = [];
        $score = $this->baseScoreForSource($source);
        $hasMismatch = false;

        $currentDepCode = strtoupper(trim((string)($form['dep_station_lookup_code'] ?? '')));
        $currentArrCode = strtoupper(trim((string)($form['arr_station_lookup_code'] ?? '')));
        $detectedDepCode = strtoupper(trim((string)($selectedDeparture['departure_port_code'] ?? '')));
        $detectedArrCode = strtoupper(trim((string)($selectedDeparture['arrival_port_code'] ?? '')));
        $routeStatus = 'unknown';
        if ($currentDepCode !== '' && $currentArrCode !== '' && $detectedDepCode !== '' && $detectedArrCode !== '') {
            $routeStatus = ($currentDepCode === $detectedDepCode && $currentArrCode === $detectedArrCode) ? 'match' : 'mismatch';
        } elseif ($detectedDepCode !== '' || $detectedArrCode !== '') {
            $routeStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Rute',
            'status' => $routeStatus,
            'current' => trim(implode(' -> ', array_filter([$currentDepCode, $currentArrCode]))),
            'detected' => trim(implode(' -> ', array_filter([$detectedDepCode, $detectedArrCode]))),
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

        $currentOperator = $this->normalizeCompareText((string)($form['operator'] ?? ($form['incident_segment_operator'] ?? '')));
        $detectedOperator = $this->normalizeCompareText($operatorName);
        $operatorStatus = 'unknown';
        if ($currentOperator !== '' && $detectedOperator !== '') {
            $operatorStatus = $currentOperator === $detectedOperator ? 'match' : 'mismatch';
        } elseif ($detectedOperator !== '') {
            $operatorStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Operator',
            'status' => $operatorStatus,
            'current' => trim((string)($form['operator'] ?? ($form['incident_segment_operator'] ?? ''))),
            'detected' => $operatorName,
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $operatorStatus, 10, 10);

        $currentVessel = $this->normalizeCompareText((string)($form['ferry_vessel_name'] ?? ''));
        $detectedVessel = $this->normalizeCompareText($vesselName);
        $vesselStatus = 'unknown';
        if ($currentVessel !== '' && $detectedVessel !== '') {
            $vesselStatus = $currentVessel === $detectedVessel ? 'match' : 'mismatch';
        } elseif ($detectedVessel !== '') {
            $vesselStatus = 'partial';
        }
        $checks[] = [
            'label' => 'Faerge',
            'status' => $vesselStatus,
            'current' => trim((string)($form['ferry_vessel_name'] ?? '')),
            'detected' => $vesselName,
        ];
        [$score, $hasMismatch] = $this->applyScore($score, $hasMismatch, $vesselStatus, 12, 8);

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

        if ($imo !== '' || $mmsi !== '') {
            $score += 8;
        }
        if (trim((string)($selectedDeparture['live_position_reported_local'] ?? '')) !== '') {
            $score += 8;
        }

        $arrivalDelayMinutes = $this->minutesDifference($scheduledArrival, $actualArrival !== '' ? $actualArrival : $estimatedArrival);
        $departureDelayMinutes = $this->minutesDifference($scheduledDeparture, $actualDeparture !== '' ? $actualDeparture : $estimatedDeparture);
        $cancelled = $this->statusLooksCancelled($status);
        $confidence = $hasMismatch ? 'low' : ($score >= 80 ? 'high' : ($score >= 55 ? 'medium' : 'low'));
        $needsManualReview = $hasMismatch || $score < 55 ? 'yes' : 'no';

        $summaryParts = [];
        if ($source !== '') {
            $summaryParts[] = 'Kilde: ' . strtoupper($source);
        }
        if ($status !== '') {
            $summaryParts[] = 'Status: ' . $status;
        }
        if ($arrivalDelayMinutes !== null && $arrivalDelayMinutes > 0) {
            $summaryParts[] = 'ETA/ATA peger paa ankomstafvigelse ca. ' . $arrivalDelayMinutes . ' min';
        }
        if ($cancelled) {
            $summaryParts[] = 'Status peger paa aflysning';
        }
        $summaryParts[] = $needsManualReview === 'yes'
            ? 'Operationelle data kraever manuel kontrol.'
            : 'Operationelle data matcher ferry-flowet godt.';

        return [
            'available' => true,
            'source' => $source,
            'status' => $status,
            'operator_name' => $operatorName,
            'vessel_name' => $vesselName,
            'vessel_imo' => $imo,
            'vessel_mmsi' => $mmsi,
            'scheduled_departure_local' => $scheduledDeparture,
            'scheduled_arrival_local' => $scheduledArrival,
            'estimated_departure_local' => $estimatedDeparture,
            'estimated_arrival_local' => $estimatedArrival,
            'actual_departure_local' => $actualDeparture,
            'actual_arrival_local' => $actualArrival,
            'live_position_reported_local' => (string)($selectedDeparture['live_position_reported_local'] ?? ''),
            'live_destination' => (string)($selectedDeparture['live_destination'] ?? ''),
            'live_speed_knots' => $selectedDeparture['live_speed_knots'] ?? null,
            'cancelled' => $cancelled ? 'yes' : 'no',
            'departure_delay_minutes_estimated' => $departureDelayMinutes,
            'arrival_delay_minutes_estimated' => $arrivalDelayMinutes,
            'match_checks' => $checks,
            'evidence_score' => max(0, min(100, $score)),
            'confidence' => $confidence,
            'needs_manual_review' => $needsManualReview,
            'summary' => implode(' · ', array_filter($summaryParts)),
            'allowed_uses' => [
                'ferry_lookup',
                'incident_prefill_support',
                'live_estimate_support',
                'admin_plausibility_check',
                'document_package_support',
            ],
            'disallowed_uses' => [
                'final_legal_decision_only',
                'force_majeure_final',
                'sole_dispute_proof',
            ],
        ];
    }

    private function baseScoreForSource(string $source): int
    {
        return match ($source) {
            'spire' => 38,
            'marinetraffic' => 34,
            'vesselfinder' => 30,
            'manual' => 8,
            'manual_fallback_seed', 'ticketless_seed' => 12,
            default => 15,
        };
    }

    /**
     * @return array{0:int,1:bool}
     */
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
        $value = trim(mb_strtolower($value, 'UTF-8'));
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
        return $status !== '' && (
            str_contains($status, 'cancel')
            || str_contains($status, 'aflys')
            || str_contains($status, 'annul')
        );
    }
}
