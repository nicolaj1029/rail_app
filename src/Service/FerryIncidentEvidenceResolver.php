<?php
declare(strict_types=1);

namespace App\Service;

final class FerryIncidentEvidenceResolver
{
    /**
     * @param array<string,mixed> $operationalEvidence
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function suggest(array $operationalEvidence, array $form = [], array $meta = []): array
    {
        $available = !empty($operationalEvidence['available']);
        $confidence = strtolower(trim((string)($operationalEvidence['confidence'] ?? 'low')));
        if (!in_array($confidence, ['high', 'medium', 'low'], true)) {
            $confidence = 'low';
        }

        $manualReviewReasons = [];
        if (!$available) {
            $manualReviewReasons[] = 'ops_unavailable';
        }
        if ($confidence === 'low') {
            $manualReviewReasons[] = 'low_operational_confidence';
        }
        if ((string)($operationalEvidence['needs_manual_review'] ?? '') === 'yes') {
            $manualReviewReasons[] = 'operational_manual_review';
        }

        $scheduledDuration = $this->firstInt([
            $form['scheduled_journey_duration_minutes'] ?? null,
            $meta['_auto']['scheduled_journey_duration_minutes']['value'] ?? null,
        ]);
        if ($scheduledDuration === null) {
            $scheduledDuration = $this->minutesDifference(
                (string)($operationalEvidence['scheduled_departure_local'] ?? ''),
                (string)($operationalEvidence['scheduled_arrival_local'] ?? '')
            );
        }

        $arrivalDelay = $this->firstInt([
            $form['arrival_delay_minutes'] ?? null,
            $operationalEvidence['arrival_delay_minutes_estimated'] ?? null,
        ]);
        $departureDelay = $this->firstInt([
            $form['delay_minutes_departure'] ?? null,
            $operationalEvidence['departure_delay_minutes_estimated'] ?? null,
        ]);

        $cancelled = (string)($operationalEvidence['cancelled'] ?? 'no') === 'yes';
        $status = strtolower(trim((string)($operationalEvidence['status'] ?? '')));
        if (!$cancelled && $status !== '' && (str_contains($status, 'cancel') || str_contains($status, 'aflys'))) {
            $cancelled = true;
            $manualReviewReasons[] = 'cancelled_signal_from_status';
        }

        $incidentMain = 'unknown';
        if ($cancelled) {
            $incidentMain = 'cancellation';
        } elseif (($arrivalDelay !== null && $arrivalDelay > 0) || ($departureDelay !== null && $departureDelay > 0)) {
            $incidentMain = 'delay';
        }

        $expectedDepartureDelay90 = $this->delay90Signal($departureDelay);
        $actualDepartureDelay90 = $this->actualDepartureDelay90Signal($departureDelay, $operationalEvidence);

        if ($arrivalDelay === null) {
            $manualReviewReasons[] = 'missing_arrival_delay';
        } elseif (
            trim((string)($operationalEvidence['actual_arrival_local'] ?? '')) === ''
            && trim((string)($operationalEvidence['estimated_arrival_local'] ?? '')) !== ''
        ) {
            $manualReviewReasons[] = 'arrival_delay_from_eta_only';
        }

        if ($scheduledDuration === null) {
            $manualReviewReasons[] = 'missing_scheduled_duration';
        }

        $art19Threshold = $scheduledDuration !== null ? $this->determineArt19Threshold($scheduledDuration) : null;
        $art19BandPreview = 'none';
        if ($arrivalDelay !== null && $art19Threshold !== null && $arrivalDelay >= $art19Threshold) {
            $art19BandPreview = $arrivalDelay >= ($art19Threshold * 2) ? '50' : '25';
        }

        $suggestionConfidence = $this->deriveSuggestionConfidence($confidence, $manualReviewReasons, $arrivalDelay, $scheduledDuration);

        return [
            'available' => $available,
            'suggested_incident_main' => $incidentMain,
            'suggested_expected_departure_delay_90' => $expectedDepartureDelay90,
            'suggested_actual_departure_delay_90' => $actualDepartureDelay90,
            'suggested_arrival_delay_minutes' => $arrivalDelay,
            'suggested_departure_delay_minutes' => $departureDelay,
            'suggested_scheduled_journey_duration_minutes' => $scheduledDuration,
            'suggested_art19_threshold_minutes' => $art19Threshold,
            'suggested_art19_band_preview' => $art19BandPreview,
            'suggestion_confidence' => $suggestionConfidence,
            'manual_review_required' => $manualReviewReasons !== [],
            'manual_review_reasons' => array_values(array_unique($manualReviewReasons)),
            'allowed_uses' => [
                'incident_prefill_support',
                'live_estimate_support',
                'admin_plausibility_check',
            ],
            'disallowed_uses' => [
                'final_legal_decision_only',
                'force_majeure_final',
                'sole_dispute_proof',
            ],
        ];
    }

    /**
     * @param array<int,mixed> $values
     */
    private function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                return (int)$value;
            }
        }

        return null;
    }

    private function delay90Signal(?int $delayMinutes): string
    {
        if ($delayMinutes === null) {
            return 'unknown';
        }

        return $delayMinutes >= 90 ? 'yes' : 'no';
    }

    /**
     * @param array<string,mixed> $operationalEvidence
     */
    private function actualDepartureDelay90Signal(?int $delayMinutes, array $operationalEvidence): string
    {
        if ($delayMinutes === null) {
            return 'unknown';
        }
        if (trim((string)($operationalEvidence['actual_departure_local'] ?? '')) === '') {
            return $delayMinutes >= 90 ? 'unknown' : 'no';
        }

        return $delayMinutes >= 90 ? 'yes' : 'no';
    }

    private function determineArt19Threshold(int $scheduledDurationMinutes): ?int
    {
        if ($scheduledDurationMinutes <= 0) {
            return null;
        }
        if ($scheduledDurationMinutes <= 240) {
            return 60;
        }
        if ($scheduledDurationMinutes < 480) {
            return 120;
        }
        if ($scheduledDurationMinutes < 1440) {
            return 180;
        }

        return 360;
    }

    private function deriveSuggestionConfidence(string $opsConfidence, array $manualReviewReasons, ?int $arrivalDelay, ?int $scheduledDuration): string
    {
        if ($opsConfidence === 'low') {
            return 'low';
        }
        if ($arrivalDelay === null || $scheduledDuration === null) {
            return 'low';
        }
        $hardReasons = array_intersect($manualReviewReasons, [
            'operational_manual_review',
            'missing_arrival_delay',
            'missing_scheduled_duration',
            'low_operational_confidence',
            'ops_unavailable',
        ]);
        if ($hardReasons !== []) {
            return 'low';
        }
        if ($manualReviewReasons !== [] || $opsConfidence === 'medium') {
            return 'medium';
        }

        return 'high';
    }

    private function minutesDifference(string $scheduled, string $observed): ?int
    {
        $scheduled = trim($scheduled);
        $observed = trim($observed);
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
}
