<?php
declare(strict_types=1);

namespace App\Service;

final class ModeClassifier
{
    /** @var list<string> */
    private const MODES = ['rail', 'ferry', 'bus', 'air'];

    /** @var array<int,string> */
    private const COMMON_FERRY_PORTS = [
        'aarhus',
        'ronne',
        'ystad',
        'helsingor',
        'helsingborg',
        'rodby',
        'puttgarden',
        'gedser',
        'rostock',
        'hirtshals',
        'larvik',
        'oslo',
        'tallinn',
        'helsinki',
        'stockholm',
        'trelleborg',
        'ebeltoft',
        'odden',
        'ronneby',
        'karlskrona',
        'travemunde',
        'kiel',
        'calais',
        'dover',
    ];

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    public function classify(array $flow): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);
        $registry = new TransportOperatorRegistry();

        $scores = array_fill_keys(self::MODES, 0);
        $reasons = array_fill_keys(self::MODES, []);

        foreach ([
            $auto['transport_mode']['value'] ?? null,
            $meta['_mode_hint'] ?? null,
        ] as $explicitMode) {
            $mode = $this->normalizeMode($explicitMode);
            if ($mode !== null) {
                $this->addScore($scores, $reasons, $mode, 8, 'auto_extraction:' . $mode);
            }
        }

        foreach ([
            $form['dep_station_lookup_mode'] ?? null,
            $form['arr_station_lookup_mode'] ?? null,
            $form['dep_terminal_lookup_mode'] ?? null,
            $form['arr_terminal_lookup_mode'] ?? null,
        ] as $lookupMode) {
            $mode = $this->normalizeMode($lookupMode);
            if ($mode !== null) {
                $this->addScore($scores, $reasons, $mode, 6, 'lookup:' . $mode);
            }
        }

        $segments = (array)($journey['segments'] ?? ($meta['_segments_auto'] ?? []));
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $segmentMode = $this->normalizeMode($segment['mode'] ?? null);
            if ($segmentMode !== null) {
                $this->addScore($scores, $reasons, $segmentMode, 7, 'segment_mode:' . $segmentMode);
            }
        }

        foreach ([
            $form['operator'] ?? null,
            $auto['operator']['value'] ?? null,
            $form['incident_segment_operator'] ?? null,
            $form['operating_carrier'] ?? null,
            $form['marketing_carrier'] ?? null,
        ] as $operatorText) {
            $operatorText = trim((string)$operatorText);
            if ($operatorText === '') {
                continue;
            }
            $mode = $registry->detectModeByName($operatorText);
            if ($mode !== null) {
                $this->addScore($scores, $reasons, $mode, 8, 'operator:' . $operatorText);
            }
        }

        $text = $this->buildTextCorpus($flow);
        if ($text !== '') {
            $this->scoreModeSpecificTextSignals($text, $scores, $reasons);
        }

        $stationNames = $this->collectStationNames($flow, $segments);
        $this->scoreRouteSignals($stationNames, $scores, $reasons);
        $this->scoreModeSpecificStructuredSignals($form, $meta, $auto, $scores, $reasons);

        arsort($scores);
        $primaryMode = (string)array_key_first($scores);
        $primaryScore = (int)($scores[$primaryMode] ?? 0);
        $secondScore = $this->secondScore($scores);

        $resolvedPrimary = $primaryScore >= 4 && ($primaryScore - $secondScore >= 2 || $primaryScore >= 8)
            ? $primaryMode
            : null;
        $resolvedPrimary = $this->applyConflictOverrides($scores, $reasons, $resolvedPrimary);

        return [
            'primary_mode' => $resolvedPrimary,
            'mode_candidates' => array_values(array_map(
                static fn(string $mode, int $score): array => ['mode' => $mode, 'score' => $score],
                array_keys(array_filter($scores, static fn(int $score): bool => $score > 0)),
                array_values(array_filter($scores, static fn(int $score): bool => $score > 0))
            )),
            'confidence' => $this->buildConfidence($primaryScore, $secondScore),
            'scores' => $scores,
            'reasons' => $resolvedPrimary !== null ? array_values((array)$reasons[$resolvedPrimary]) : [],
            'reasons_by_mode' => array_map(static fn(array $items): array => array_values($items), $reasons),
        ];
    }

    /**
     * @param array<string,mixed> $flow
     */
    public function resolvePrimaryMode(array $flow): ?string
    {
        $classification = $this->classify($flow);
        $mode = $this->normalizeMode($classification['primary_mode'] ?? null);

        return $mode;
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function addScore(array &$scores, array &$reasons, string $mode, int $points, string $reason): void
    {
        if (!isset($scores[$mode]) || $points <= 0) {
            return;
        }
        $scores[$mode] += $points;
        if (!in_array($reason, $reasons[$mode], true)) {
            $reasons[$mode][] = $reason;
        }
    }

    /**
     * @param array<string,mixed> $flow
     */
    private function buildTextCorpus(array $flow): string
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);

        $parts = [
            (string)($meta['_ocr_text'] ?? ''),
            (string)($auto['operator']['value'] ?? ''),
            (string)($auto['operator_product']['value'] ?? ''),
            (string)($auto['dep_station']['value'] ?? ''),
            (string)($auto['arr_station']['value'] ?? ''),
            (string)($form['operator'] ?? ''),
            (string)($form['operator_product'] ?? ''),
            (string)($form['dep_station'] ?? ''),
            (string)($form['arr_station'] ?? ''),
            (string)($journey['bookingRef'] ?? ''),
        ];

        return trim(implode("\n", array_filter(array_map('trim', $parts), static fn(string $value): bool => $value !== '')));
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreModeSpecificTextSignals(string $text, array &$scores, array &$reasons): void
    {
        $ascii = $this->normalizeText($text);

        $this->scoreFerryTextSignals($ascii, $scores, $reasons);
        $this->scoreRailTextSignals($ascii, $scores, $reasons);
        $this->scoreAirTextSignals($ascii, $scores, $reasons);
        $this->scoreBusTextSignals($ascii, $scores, $reasons);
        $this->scoreBoardingPassDisambiguationSignals($ascii, $scores, $reasons);
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreFerryTextSignals(string $ascii, array &$scores, array &$reasons): void
    {
        $ferrySignals = [
            ['pattern' => '/\bmolslinjen\b|\bmols[- ]linjen\b/', 'points' => 8, 'reason' => 'ocr:molslinjen'],
            ['pattern' => '/\bcheck[- ]?in\b/', 'points' => 5, 'reason' => 'ocr:check-in'],
            ['pattern' => '/\blavpris\s+bil\b/', 'points' => 5, 'reason' => 'ocr:lavpris-bil'],
            ['pattern' => '/\bbil\s*<\s*\d+[.,]?\d*\s*m\b/', 'points' => 6, 'reason' => 'ocr:vehicle-fare'],
            ['pattern' => '/\bcar\s*<\s*\d+[.,]?\d*\s*m\b/', 'points' => 6, 'reason' => 'ocr:vehicle-fare'],
            ['pattern' => '/\bperson\s*\(?er\)?\b/', 'points' => 2, 'reason' => 'ocr:passenger-count'],
            ['pattern' => '/\bfaerge\b|\bferry\b/', 'points' => 5, 'reason' => 'ocr:ferry-word'],
            ['pattern' => '/\bolieprisfradrag\b|\bfuel\s+surcharge\b/', 'points' => 4, 'reason' => 'ocr:fuel-surcharge'],
            ['pattern' => '/\budrejse\b/', 'points' => 1, 'reason' => 'ocr:outbound-label'],
            ['pattern' => '/\bhjemrejse\b/', 'points' => 1, 'reason' => 'ocr:return-label'],
        ];
        foreach ($ferrySignals as $signal) {
            if (preg_match($signal['pattern'], $ascii)) {
                $this->addScore($scores, $reasons, 'ferry', $signal['points'], $signal['reason']);
            }
        }
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreRailTextSignals(string $ascii, array &$scores, array &$reasons): void
    {
        $railSignals = [
            ['pattern' => '/\bciv\b/', 'points' => 7, 'reason' => 'ocr:civ'],
            ['pattern' => '/\btrain\b|\btog\b/', 'points' => 4, 'reason' => 'ocr:train-word'],
            ['pattern' => '/\bplatform\b|\bgleis\b|\bspor\b/', 'points' => 3, 'reason' => 'ocr:platform'],
            ['pattern' => '/\bbahncard\b|\binterrail\b|\beurail\b/', 'points' => 4, 'reason' => 'ocr:rail-product'],
            ['pattern' => '/\bcoach\b|\bcarriage\b|\bvogn\b|\bseat\b|\bsaede\b|\bpladsreservation\b/', 'points' => 2, 'reason' => 'ocr:rail-seat'],
            ['pattern' => '/\bice\b|\beurocity\b|\bec\b|\brailjet\b/', 'points' => 3, 'reason' => 'ocr:rail-service'],
        ];
        foreach ($railSignals as $signal) {
            if (preg_match($signal['pattern'], $ascii)) {
                $this->addScore($scores, $reasons, 'rail', $signal['points'], $signal['reason']);
            }
        }
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreAirTextSignals(string $ascii, array &$scores, array &$reasons): void
    {
        $airSignals = [
            ['pattern' => '/\bboarding\s+pass\b/', 'points' => 7, 'reason' => 'ocr:boarding-pass'],
            ['pattern' => '/\bflight\b/', 'points' => 5, 'reason' => 'ocr:flight-word'],
            ['pattern' => '/\bboarding\b/', 'points' => 4, 'reason' => 'ocr:boarding'],
            ['pattern' => '/\bgate\b/', 'points' => 4, 'reason' => 'ocr:gate'],
            ['pattern' => '/\bterminal\s+\d\b/', 'points' => 3, 'reason' => 'ocr:air-terminal'],
            ['pattern' => '/\bpnr\b/', 'points' => 4, 'reason' => 'ocr:pnr'],
            ['pattern' => '/\b(passenger\s+name|name\s+of\s+passenger)\b/', 'points' => 4, 'reason' => 'ocr:passenger-name'],
            ['pattern' => '/\bsecurity\b|\bsecurity\s+control\b/', 'points' => 3, 'reason' => 'ocr:security'],
            ['pattern' => '/\bbag(?:gage| drop)\b|\bchecked\s+bag\b/', 'points' => 3, 'reason' => 'ocr:baggage'],
            ['pattern' => '/\b(?:flight|vol|fly)\s*(?:no|nr|number)?\s*[:#]?\s*[a-z]{1,3}\s?\d{2,4}\b/', 'points' => 6, 'reason' => 'ocr:flight-number'],
        ];
        foreach ($airSignals as $signal) {
            if (preg_match($signal['pattern'], $ascii)) {
                $this->addScore($scores, $reasons, 'air', $signal['points'], $signal['reason']);
            }
        }
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreBusTextSignals(string $ascii, array &$scores, array &$reasons): void
    {
        $busSignals = [
            ['pattern' => '/\bflixbus\b|\bflix\s+bus\b/', 'points' => 8, 'reason' => 'ocr:flixbus'],
            ['pattern' => '/\bglobal\.flixbus\.com\b|\bflixbus\.com\b/', 'points' => 8, 'reason' => 'ocr:flixbus-domain'],
            ['pattern' => '/\blong[- ]distance\s+bus\b/', 'points' => 4, 'reason' => 'ocr:long-distance-bus'],
            ['pattern' => '/\bcoach\b|\bbus\b/', 'points' => 3, 'reason' => 'ocr:bus-word'],
            ['pattern' => '/\bcoach\s+ticket\b|\bbus\s+ticket\b/', 'points' => 5, 'reason' => 'ocr:bus-ticket'],
            ['pattern' => '/\bdeparture\s+stop\b|\barrival\s+stop\b/', 'points' => 5, 'reason' => 'ocr:bus-stop'],
            ['pattern' => '/\bdeparture\s+station\b|\barrival\s+station\b/', 'points' => 4, 'reason' => 'ocr:station-layout'],
            ['pattern' => '/\bboarding\s+point\b|\bstand\b|\bstance\b/', 'points' => 3, 'reason' => 'ocr:boarding-point'],
            ['pattern' => '/\bbus\s*(?:no|nr|number)?\s*[:#]?\s*[a-z0-9-]{2,}\b/', 'points' => 5, 'reason' => 'ocr:bus-number'],
            ['pattern' => '/\bhold\s+luggage\b|\bhand\s+luggage\b/', 'points' => 4, 'reason' => 'ocr:bus-luggage'],
            ['pattern' => '/\bvalid\s+in\s+both\s+print\s+and\s+digital\s+form\b/', 'points' => 3, 'reason' => 'ocr:bus-digital-form'],
        ];
        foreach ($busSignals as $signal) {
            if (preg_match($signal['pattern'], $ascii)) {
                $this->addScore($scores, $reasons, 'bus', $signal['points'], $signal['reason']);
            }
        }
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreBoardingPassDisambiguationSignals(string $ascii, array &$scores, array &$reasons): void
    {
        if (!preg_match('/\bboarding\s+pass\b/', $ascii)) {
            return;
        }

        $busBoardingPassSignals = [
            '/\bflixbus\b|\bflix\s+bus\b/',
            '/\bglobal\.flixbus\.com\b|\bflixbus\.com\b/',
            '/\bbus\s*(?:no|nr|number)?\s*[:#]?\s*[a-z0-9-]{2,}\b/',
            '/\bdeparture\s+station\b|\barrival\s+station\b/',
            '/\bhold\s+luggage\b|\bhand\s+luggage\b/',
        ];
        foreach ($busBoardingPassSignals as $pattern) {
            if (preg_match($pattern, $ascii)) {
                $this->addScore($scores, $reasons, 'bus', 8, 'ocr:bus-boarding-pass-layout');
                break;
            }
        }
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $auto
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreModeSpecificStructuredSignals(array $form, array $meta, array $auto, array &$scores, array &$reasons): void
    {
        $this->scoreRailStructuredSignals($form, $auto, $scores, $reasons);
        $this->scoreAirStructuredSignals($form, $meta, $auto, $scores, $reasons);
        $this->scoreBusStructuredSignals($form, $meta, $scores, $reasons);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $auto
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreRailStructuredSignals(array $form, array $auto, array &$scores, array &$reasons): void
    {
        foreach ([
            $form['train_no'] ?? null,
            $auto['train_no']['value'] ?? null,
        ] as $trainNo) {
            if (trim((string)$trainNo) !== '') {
                $this->addScore($scores, $reasons, 'rail', 6, 'structured:train-number');
            }
        }

        foreach ([
            $form['train_specificity'] ?? null,
            $auto['train_specificity']['value'] ?? null,
        ] as $trainSpecificity) {
            if (trim((string)$trainSpecificity) !== '') {
                $this->addScore($scores, $reasons, 'rail', 3, 'structured:train-specificity');
            }
        }

        foreach ([
            $form['operator_product'] ?? null,
            $auto['operator_product']['value'] ?? null,
        ] as $product) {
            $productNorm = $this->normalizeText((string)$product);
            if ($productNorm === '') {
                continue;
            }
            if (preg_match('/\b(ice|ic|ec|railjet|regional|lyntog)\b/', $productNorm)) {
                $this->addScore($scores, $reasons, 'rail', 4, 'structured:rail-product');
            }
        }
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $auto
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreAirStructuredSignals(array $form, array $meta, array $auto, array &$scores, array &$reasons): void
    {
        foreach ([
            $form['operating_carrier'] ?? null,
            $form['marketing_carrier'] ?? null,
            $meta['_identifiers']['pnr'] ?? null,
        ] as $value) {
            if (trim((string)$value) !== '') {
                $reason = str_contains((string)$value, '-') ? 'structured:carrier-code' : 'structured:air-field';
                $this->addScore($scores, $reasons, 'air', 3, $reason);
            }
        }

        foreach ([
            $form['departure_airport_in_eu'] ?? null,
            $form['arrival_airport_in_eu'] ?? null,
        ] as $airportFlag) {
            if (trim((string)$airportFlag) !== '') {
                $this->addScore($scores, $reasons, 'air', 2, 'structured:airport-scope');
            }
        }

        foreach ([
            $auto['dep_station']['value'] ?? null,
            $auto['arr_station']['value'] ?? null,
            $form['dep_station'] ?? null,
            $form['arr_station'] ?? null,
        ] as $station) {
            $station = trim((string)$station);
            if (preg_match('/^[A-Z]{3}$/', $station)) {
                $this->addScore($scores, $reasons, 'air', 2, 'structured:iata-code');
            }
        }
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreBusStructuredSignals(array $form, array $meta, array &$scores, array &$reasons): void
    {
        foreach ([
            $form['bus_regular_service'] ?? null,
            $meta['bus_regular_service'] ?? null,
        ] as $value) {
            if (strtolower(trim((string)$value)) === 'yes') {
                $this->addScore($scores, $reasons, 'bus', 4, 'structured:regular-service');
            }
        }

        foreach ([
            $form['boarding_in_eu'] ?? null,
            $form['alighting_in_eu'] ?? null,
        ] as $value) {
            if (trim((string)$value) !== '') {
                $this->addScore($scores, $reasons, 'bus', 1, 'structured:bus-scope');
            }
        }
    }

    /**
     * @param array<string,mixed> $flow
     * @param array<int,array<string,mixed>> $segments
     * @return array<int,string>
     */
    private function collectStationNames(array $flow, array $segments): array
    {
        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $auto = (array)($meta['_auto'] ?? []);

        $names = [];
        foreach ([
            $form['dep_station'] ?? null,
            $form['arr_station'] ?? null,
            $auto['dep_station']['value'] ?? null,
            $auto['arr_station']['value'] ?? null,
        ] as $name) {
            $name = $this->normalizePlaceName((string)$name);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            foreach ([$segment['from'] ?? null, $segment['to'] ?? null] as $name) {
                $name = $this->normalizePlaceName((string)$name);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * @param array<int,string> $stationNames
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function scoreRouteSignals(array $stationNames, array &$scores, array &$reasons): void
    {
        $hits = 0;
        foreach ($stationNames as $name) {
            if (in_array($name, self::COMMON_FERRY_PORTS, true)) {
                $hits++;
            }
        }

        if ($hits >= 2) {
            $this->addScore($scores, $reasons, 'ferry', 6, 'route:known-ferry-ports');
        } elseif ($hits === 1) {
            $this->addScore($scores, $reasons, 'ferry', 2, 'route:one-ferry-port');
        }
    }

    private function secondScore(array $scores): int
    {
        $values = array_values($scores);
        return (int)($values[1] ?? 0);
    }

    /**
     * @param array<string,int> $scores
     * @param array<string,array<int,string>> $reasons
     */
    private function applyConflictOverrides(array $scores, array $reasons, ?string $resolvedPrimary): ?string
    {
        $busScore = (int)($scores['bus'] ?? 0);
        $airScore = (int)($scores['air'] ?? 0);
        if ($busScore <= 0 || $airScore <= 0) {
            return $resolvedPrimary;
        }

        $busReasons = (array)($reasons['bus'] ?? []);
        $airReasons = (array)($reasons['air'] ?? []);
        $hasStrongBusEvidence = $this->containsReasonPrefix($busReasons, 'operator:')
            || in_array('ocr:flixbus', $busReasons, true)
            || in_array('ocr:flixbus-domain', $busReasons, true)
            || in_array('ocr:bus-number', $busReasons, true)
            || in_array('ocr:bus-boarding-pass-layout', $busReasons, true);
        $airReasonsWithoutLayout = array_values(array_filter(
            $airReasons,
            static fn(string $reason): bool => !in_array($reason, ['ocr:boarding-pass', 'ocr:boarding', 'ocr:baggage', 'ocr:passenger-name'], true)
        ));

        if ($hasStrongBusEvidence && $airReasonsWithoutLayout === [] && $busScore >= ($airScore - 3)) {
            return 'bus';
        }

        return $resolvedPrimary;
    }

    /**
     * @param array<int,string> $reasons
     */
    private function containsReasonPrefix(array $reasons, string $prefix): bool
    {
        foreach ($reasons as $reason) {
            if (str_starts_with($reason, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function buildConfidence(int $primaryScore, int $secondScore): string
    {
        if ($primaryScore >= 10 && ($primaryScore - $secondScore) >= 4) {
            return 'high';
        }
        if ($primaryScore >= 6 && ($primaryScore - $secondScore) >= 2) {
            return 'medium';
        }

        return $primaryScore > 0 ? 'low' : 'none';
    }

    private function normalizeMode(mixed $value): ?string
    {
        $mode = strtolower(trim((string)$value));
        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $text = mb_strtolower($text, 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = is_string($ascii) && $ascii !== '' ? $ascii : $text;
        $text = preg_replace('/[^a-z0-9<>\-.,()\/\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function normalizePlaceName(string $name): string
    {
        $name = $this->normalizeText($name);
        if ($name === '') {
            return '';
        }

        return str_replace(' ', '', $name);
    }
}
