<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

final class CaseRiskService
{
    private const DUPLICATE_CODES = [
        'duplicate_booking_reference',
        'duplicate_ticket_fingerprint',
    ];

    private Table $cases;

    private TransportOperatorRegistry $operatorRegistry;

    public function __construct(?Table $cases = null, ?TransportOperatorRegistry $operatorRegistry = null)
    {
        $this->cases = $cases ?? TableRegistry::getTableLocator()->get('Cases');
        $this->operatorRegistry = $operatorRegistry ?? new TransportOperatorRegistry();
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<int,array<string,mixed>> $existingCases
     * @return array<string,mixed>
     */
    public function evaluate(array $snapshot, array $existingCases = [], ?string $excludeCaseId = null): array
    {
        $context = $this->buildContext($snapshot);
        $comparables = $existingCases !== [] ? $existingCases : $this->loadComparableCases($excludeCaseId);
        $flags = [];

        $bookingReference = (string)($context['booking_reference'] ?? '');
        $ticketFingerprint = (string)($context['ticket_fingerprint'] ?? '');
        $passengerName = (string)($context['passenger_name'] ?? '');

        if ($bookingReference !== '') {
            $matches = array_values(array_filter($comparables, static function (array $candidate) use ($bookingReference): bool {
                return (string)($candidate['booking_reference'] ?? '') === $bookingReference;
            }));
            if ($matches !== []) {
                $detail = 'Booking reference ' . $bookingReference . ' ses i ' . count($matches) . ' andre sag(er): '
                    . implode(', ', $this->matchLabels($matches));
                $flags[] = $this->makeFlag('duplicate_booking_reference', 'Dubleret bookingreference', 35, 'high', $detail);

                if ($passengerName !== '') {
                    $differentPassenger = false;
                    foreach ($matches as $match) {
                        $otherPassenger = (string)($match['passenger_name'] ?? '');
                        if ($otherPassenger !== '' && $otherPassenger !== $passengerName) {
                            $differentPassenger = true;
                            break;
                        }
                    }
                    if ($differentPassenger) {
                        $flags[] = $this->makeFlag(
                            'duplicate_booking_reference_different_passenger',
                            'Bookingreference bruges af flere passagernavne',
                            20,
                            'high',
                            'Mindst en anden sag med samme bookingreference har et andet passagernavn.'
                        );
                    }
                }
            }
        }

        if ($ticketFingerprint !== '') {
            $matches = array_values(array_filter($comparables, static function (array $candidate) use ($ticketFingerprint): bool {
                return (string)($candidate['ticket_fingerprint'] ?? '') === $ticketFingerprint;
            }));
            if ($matches !== []) {
                $flags[] = $this->makeFlag(
                    'duplicate_ticket_fingerprint',
                    'Samme billetfingeraftryk er set tidligere',
                    45,
                    'high',
                    'OCR/fingerprint matcher ' . count($matches) . ' andre sag(er): ' . implode(', ', $this->matchLabels($matches))
                );
            }
        }

        $legs = (array)($context['legs'] ?? []);
        $incidentLegId = (string)($context['incident_leg_id'] ?? '');
        if ($incidentLegId !== '' && !$this->legExists($legs, $incidentLegId)) {
            $flags[] = $this->makeFlag(
                'incident_leg_missing',
                'Haendelses-leg findes ikke i canonical journey',
                20,
                'medium',
                'Incident peger paa ' . $incidentLegId . ', men legget findes ikke i canonical legs.'
            );
        }

        if ($this->hasImpossibleLegTiming($legs)) {
            $flags[] = $this->makeFlag(
                'impossible_leg_timing',
                'Planlagte tider er indbyrdes umulige',
                18,
                'medium',
                'Mindst et leg har ankomst tidligere end afgang uden et sikkert dato-skift.'
            );
        }

        if ($this->hasDisjointLegChain($legs)) {
            $flags[] = $this->makeFlag(
                'disjoint_leg_chain',
                'Leg-keden haenger ikke sammen',
                12,
                'medium',
                'Destinationen paa et leg matcher ikke origin paa det naeste leg.'
            );
        }

        if ($this->hasOperatorModeMismatch($context)) {
            $flags[] = $this->makeFlag(
                'operator_mode_mismatch',
                'Operatoer og transportform matcher ikke',
                10,
                'low',
                'Canonical mode stemmer ikke med operator-brandingen i kataloget.'
            );
        }

        $score = min(100, array_sum(array_map(static fn(array $flag): int => (int)($flag['points'] ?? 0), $flags)));
        $level = $score >= 60 ? 'high' : ($score >= 25 ? 'medium' : 'low');
        $fraudReviewRequired = $score >= 60;
        $summary = $this->buildSummary($flags, $score);

        return [
            'score' => $score,
            'level' => $level,
            'level_label' => $this->riskLevelLabel($level),
            'fraud_review_required' => $fraudReviewRequired,
            'summary' => $summary,
            'flags' => array_values($flags),
            'duplicate_flag' => $this->hasDuplicateFlag($flags),
            'computed_at' => date('c'),
        ];
    }

    /**
     * @param array<string,mixed> $risk
     * @return array<string,mixed>
     */
    public function toCaseFields(array $risk): array
    {
        $flags = $this->decodeFlags($risk['flags'] ?? []);

        return [
            'risk_score' => (int)($risk['score'] ?? 0),
            'risk_level' => (string)($risk['level'] ?? 'low'),
            'risk_flags' => $flags !== [] ? json_encode($flags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'fraud_review_required' => !empty($risk['fraud_review_required']),
            'risk_last_evaluated_at' => !empty($risk['computed_at']) ? date('Y-m-d H:i:s', strtotime((string)$risk['computed_at'])) : date('Y-m-d H:i:s'),
            'risk_summary' => (string)($risk['summary'] ?? ''),
            'duplicate_flag' => !empty($risk['duplicate_flag']),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function decodeFlags(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_array'));
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private function buildContext(array $snapshot, ?string $caseId = null, ?string $caseRef = null, ?string $passengerName = null): array
    {
        $form = (array)($snapshot['form'] ?? []);
        $meta = (array)($snapshot['meta'] ?? []);
        $journey = (array)($snapshot['journey'] ?? []);
        $multimodal = (array)($meta['_multimodal'] ?? []);
        $canonical = (array)($multimodal['canonical'] ?? []);
        $canonicalJourney = (array)($canonical['journey'] ?? []);
        $canonicalIncident = (array)($canonical['incident'] ?? []);
        $legs = $this->normalizeLegs((array)($canonical['legs'] ?? []), $snapshot);
        $operator = trim((string)($canonicalJourney['operator'] ?? ($form['operator'] ?? '')));
        $transportMode = strtolower(trim((string)($canonical['transport_mode'] ?? ($journey['transport_mode'] ?? ($form['transport_mode'] ?? ($snapshot['flags']['transport_mode'] ?? ''))))));
        $bookingReference = $this->normalizeToken(
            (string)($canonicalJourney['ticket_no'] ?? ($journey['bookingRef'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ''))))
        );
        $effectivePassenger = trim((string)($passengerName ?? ($form['passenger_name'] ?? '')));
        $travelDate = trim((string)(
            $canonicalJourney['service_date']
            ?? ($legs[0]['service_date'] ?? null)
            ?? ($form['dep_date'] ?? '')
        ));
        $ocrText = trim((string)($meta['_ocr_text'] ?? ''));
        $ticketFingerprint = $this->buildTicketFingerprint($ocrText);

        return [
            'case_id' => $caseId,
            'case_ref' => $caseRef,
            'passenger_name' => $effectivePassenger,
            'booking_reference' => $bookingReference,
            'ticket_fingerprint' => $ticketFingerprint,
            'operator' => $operator,
            'transport_mode' => $transportMode,
            'origin' => trim((string)($canonicalJourney['origin'] ?? ($form['dep_station'] ?? ''))),
            'destination' => trim((string)($canonicalJourney['destination'] ?? ($form['arr_station'] ?? ''))),
            'travel_date' => $travelDate,
            'incident_leg_id' => trim((string)($canonicalIncident['incident_leg_id'] ?? '')),
            'legs' => $legs,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadComparableCases(?string $excludeCaseId): array
    {
        $rows = [];
        $query = $this->cases
            ->find()
            ->select(['id', 'ref', 'passenger_name', 'flow_snapshot'])
            ->where(['flow_snapshot IS NOT' => null])
            ->orderDesc('modified');
        if ($excludeCaseId !== null && $excludeCaseId !== '') {
            $query->where(['id !=' => $excludeCaseId]);
        }

        foreach ($query->all() as $case) {
            $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
            if (!is_array($snapshot)) {
                continue;
            }
            $rows[] = $this->buildContext(
                $snapshot,
                (string)$case->get('id'),
                (string)$case->get('ref'),
                (string)$case->get('passenger_name')
            );
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     */
    private function legExists(array $legs, string $incidentLegId): bool
    {
        foreach ($legs as $leg) {
            if ((string)($leg['leg_id'] ?? '') === $incidentLegId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     */
    private function hasImpossibleLegTiming(array $legs): bool
    {
        foreach ($legs as $leg) {
            $departure = $this->parseComparableDateTime((string)($leg['planned_departure'] ?? ''));
            $arrival = $this->parseComparableDateTime((string)($leg['planned_arrival'] ?? ''));
            if ($departure === null || $arrival === null) {
                continue;
            }
            if ($arrival < $departure) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     */
    private function hasDisjointLegChain(array $legs): bool
    {
        if (count($legs) < 2) {
            return false;
        }

        for ($i = 0; $i < count($legs) - 1; $i++) {
            $left = $this->normalizeStation((string)($legs[$i]['destination'] ?? ''));
            $right = $this->normalizeStation((string)($legs[$i + 1]['origin'] ?? ''));
            if ($left === '' || $right === '') {
                continue;
            }
            if (!$this->stationsSimilar($left, $right)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function hasOperatorModeMismatch(array $context): bool
    {
        $operator = trim((string)($context['operator'] ?? ''));
        $transportMode = strtolower(trim((string)($context['transport_mode'] ?? '')));
        if ($operator === '' || !in_array($transportMode, ['rail', 'ferry', 'bus', 'air'], true)) {
            return false;
        }

        $detectedMode = $this->operatorRegistry->detectModeByName($operator);
        return $detectedMode !== null && $detectedMode !== $transportMode;
    }

    /**
     * @param array<int,array<string,mixed>> $matches
     * @return list<string>
     */
    private function matchLabels(array $matches): array
    {
        $labels = [];
        foreach ($matches as $match) {
            $labels[] = trim((string)($match['case_ref'] ?? '')) !== ''
                ? (string)$match['case_ref']
                : ('case#' . (string)($match['case_id'] ?? '?'));
        }

        return array_slice(array_values(array_unique($labels)), 0, 4);
    }

    /**
     * @param array<string,mixed> $flag
     * @return array<string,mixed>
     */
    private function makeFlag(string $code, string $label, int $points, string $severity, string $detail): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'points' => $points,
            'severity' => $severity,
            'detail' => $detail,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $flags
     */
    private function buildSummary(array $flags, int $score): string
    {
        if ($flags === []) {
            return 'Ingen staerke risikosignaler fundet i fase 1.';
        }

        $parts = array_map(static fn(array $flag): string => (string)($flag['label'] ?? ''), array_slice($flags, 0, 2));
        $summary = implode('; ', array_filter($parts));

        return 'Risk score ' . $score . ': ' . $summary . '.';
    }

    /**
     * @param array<int,array<string,mixed>> $flags
     */
    private function hasDuplicateFlag(array $flags): bool
    {
        foreach ($flags as $flag) {
            if (in_array((string)($flag['code'] ?? ''), self::DUPLICATE_CODES, true)) {
                return true;
            }
        }

        return false;
    }

    private function riskLevelLabel(string $level): string
    {
        return match ($level) {
            'high' => 'High risk',
            'medium' => 'Medium risk',
            default => 'Low risk',
        };
    }

    /**
     * @param array<int,array<string,mixed>> $legs
     * @param array<string,mixed> $snapshot
     * @return array<int,array<string,mixed>>
     */
    private function normalizeLegs(array $legs, array $snapshot): array
    {
        if ($legs !== []) {
            $normalizedLegs = array_map(function (array $leg): array {
                return [
                    'leg_id' => (string)($leg['leg_id'] ?? ''),
                    'mode' => strtolower(trim((string)($leg['mode'] ?? ''))),
                    'operator' => (string)($leg['operator'] ?? ''),
                    'origin' => (string)($leg['origin'] ?? ''),
                    'destination' => (string)($leg['destination'] ?? ''),
                    'planned_departure' => (string)($leg['planned_departure'] ?? ''),
                    'planned_arrival' => (string)($leg['planned_arrival'] ?? ''),
                    'service_date' => (string)($leg['service_date'] ?? ''),
                ];
            }, $legs);

            return array_values(array_filter(
                $normalizedLegs,
                static fn(array $leg): bool => $leg['origin'] !== '' || $leg['destination'] !== ''
            ));
        }

        $segments = (array)($snapshot['journey']['segments'] ?? ($snapshot['meta']['_segments_auto'] ?? []));
        $normalized = [];
        foreach (array_values($segments) as $index => $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $normalized[] = [
                'leg_id' => 'leg_' . ($index + 1),
                'mode' => strtolower(trim((string)($segment['mode'] ?? ($snapshot['flags']['transport_mode'] ?? '')))),
                'operator' => (string)($segment['operator'] ?? ($segment['carrier'] ?? ($snapshot['form']['operator'] ?? ''))),
                'origin' => (string)($segment['from'] ?? ''),
                'destination' => (string)($segment['to'] ?? ''),
                'planned_departure' => $this->combineDateAndTime((string)($segment['depDate'] ?? ''), (string)($segment['schedDep'] ?? '')),
                'planned_arrival' => $this->combineDateAndTime((string)($segment['arrDate'] ?? ($segment['depDate'] ?? '')), (string)($segment['schedArr'] ?? '')),
                'service_date' => (string)($segment['depDate'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function combineDateAndTime(string $date, string $time): string
    {
        $date = trim($date);
        $time = trim($time);
        if ($date !== '' && $time !== '') {
            return $date . 'T' . $time;
        }

        return $date !== '' ? $date : $time;
    }

    private function buildTicketFingerprint(string $ocrText): string
    {
        $ocrText = $this->normalizeLongText($ocrText);
        if (mb_strlen($ocrText) < 80) {
            return '';
        }

        return sha1($ocrText);
    }

    private function normalizeLongText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9]/', '', $value) ?? $value;

        return trim($value);
    }

    private function normalizeStation(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function stationsSimilar(string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }
        if (str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        $leftAscii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $left) ?: $left;
        $rightAscii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $right) ?: $right;
        $distance = levenshtein($leftAscii, $rightAscii);
        $maxLength = max(strlen($leftAscii), strlen($rightAscii));

        return $maxLength > 0 && $distance <= max(2, (int)floor($maxLength * 0.2));
    }

    private function parseComparableDateTime(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || !str_contains($value, 'T')) {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }
}
