<?php
declare(strict_types=1);

namespace App\Service\Rail;

final class RailDepartureNormalizer
{
    /** @var array<string,array<int,string>> */
    private array $stationAliases;

    /** @var array<string,array<string,mixed>> */
    private array $operators;

    public function __construct(?array $stationAliases = null, ?array $operators = null)
    {
        $this->stationAliases = $stationAliases ?? (array)include CONFIG . 'rail' . DS . 'station_aliases.php';
        $this->operators = $operators ?? (array)include CONFIG . 'rail' . DS . 'operators.php';
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    public function normalize(array $item): array
    {
        $normalized = [
            'id' => trim((string)($item['id'] ?? '')),
            'source' => strtolower(trim((string)($item['source'] ?? 'mock'))),
            'confidence' => $this->normalizeFloat($item['confidence'] ?? 0.5, 0.0, 1.0),
            'train_number' => $this->normalizeString($item['train_number'] ?? null),
            'service_name' => $this->normalizeString($item['service_name'] ?? null),
            'line_name' => $this->normalizeString($item['line_name'] ?? null),
            'product' => $this->normalizeString($item['product'] ?? null),
            'operator_code' => $this->normalizeString($item['operator_code'] ?? null),
            'operator_name' => $this->normalizeString($item['operator_name'] ?? null),
            'infrastructure_manager' => $this->normalizeString($item['infrastructure_manager'] ?? null),
            'origin_station_name' => $this->canonicalizeStationName($this->normalizeString($item['origin_station_name'] ?? null)),
            'origin_station_code' => $this->normalizeString($item['origin_station_code'] ?? null),
            'destination_station_name' => $this->canonicalizeStationName($this->normalizeString($item['destination_station_name'] ?? null)),
            'destination_station_code' => $this->normalizeString($item['destination_station_code'] ?? null),
            'planned_departure_at' => $this->normalizeDateTime($item['planned_departure_at'] ?? null),
            'estimated_departure_at' => $this->normalizeDateTime($item['estimated_departure_at'] ?? null),
            'actual_departure_at' => $this->normalizeDateTime($item['actual_departure_at'] ?? null),
            'planned_arrival_at' => $this->normalizeDateTime($item['planned_arrival_at'] ?? null),
            'estimated_arrival_at' => $this->normalizeDateTime($item['estimated_arrival_at'] ?? null),
            'actual_arrival_at' => $this->normalizeDateTime($item['actual_arrival_at'] ?? null),
            'departure_delay_minutes' => $this->normalizeInt($item['departure_delay_minutes'] ?? null),
            'arrival_delay_minutes' => $this->normalizeInt($item['arrival_delay_minutes'] ?? null),
            'status' => $this->normalizeStatus($item['status'] ?? null),
            'platform_planned' => $this->normalizeString($item['platform_planned'] ?? null),
            'platform_actual' => $this->normalizeString($item['platform_actual'] ?? null),
            'cancelled_section_from' => $this->normalizeString($item['cancelled_section_from'] ?? null),
            'cancelled_section_to' => $this->normalizeString($item['cancelled_section_to'] ?? null),
            'calling_points' => $this->normalizeCallingPoints((array)($item['calling_points'] ?? [])),
            'disruption_reason_public' => $this->normalizeString($item['disruption_reason_public'] ?? null),
            'disruption_reason_code' => $this->normalizeString($item['disruption_reason_code'] ?? null),
            'remarks' => $this->normalizeRemarks((array)($item['remarks'] ?? [])),
            'raw' => $this->normalizeRaw((array)($item['raw'] ?? [])),
        ];

        $this->applyOperatorDefaults($normalized);

        if ($normalized['id'] === '') {
            $normalized['id'] = md5(json_encode([
                $normalized['source'],
                $normalized['train_number'],
                $normalized['origin_station_name'],
                $normalized['destination_station_name'],
                $normalized['planned_departure_at'],
                $normalized['planned_arrival_at'],
            ], JSON_UNESCAPED_UNICODE));
        }

        return $normalized;
    }

    public function stationsMatch(?string $candidateName, ?string $candidateCode, ?string $query): bool
    {
        $query = $this->normalizeString($query);
        if ($query === null) {
            return false;
        }

        $queryCanonical = $this->canonicalizeStationName($query) ?? $query;
        $queryKey = $this->stationKey($queryCanonical);
        foreach ([$candidateName, $candidateCode] as $candidate) {
            $candidate = $this->normalizeString($candidate);
            if ($candidate === null) {
                continue;
            }
            $candidateCanonical = $this->canonicalizeStationName($candidate) ?? $candidate;
            if ($this->stationKey($candidateCanonical) === $queryKey) {
                return true;
            }
        }

        return false;
    }

    public function operatorMatches(?string $candidateCode, ?string $candidateName, ?string $query): bool
    {
        $query = $this->normalizeString($query);
        if ($query === null) {
            return false;
        }
        $queryKey = $this->compareKey($query);
        foreach ([$candidateCode, $candidateName] as $candidate) {
            $candidate = $this->normalizeString($candidate);
            if ($candidate === null) {
                continue;
            }
            if ($this->compareKey($candidate) === $queryKey) {
                return true;
            }
        }

        foreach ($this->operators as $code => $definition) {
            $aliases = array_map([$this, 'compareKey'], (array)($definition['aliases'] ?? []));
            $aliases[] = $this->compareKey((string)$code);
            $aliases[] = $this->compareKey((string)($definition['name'] ?? ''));
            $aliases = array_values(array_filter(array_unique($aliases)));
            if (!in_array($queryKey, $aliases, true)) {
                continue;
            }

            return in_array($this->compareKey((string)($candidateCode ?? '')), $aliases, true)
                || in_array($this->compareKey((string)($candidateName ?? '')), $aliases, true);
        }

        return false;
    }

    public function canonicalizeStationName(?string $value): ?string
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return null;
        }

        $needle = $this->stationKey($value);
        foreach ($this->stationAliases as $canonical => $aliases) {
            $variants = array_merge([$canonical], (array)$aliases);
            foreach ($variants as $variant) {
                if ($this->stationKey((string)$variant) === $needle) {
                    return $canonical;
                }
            }
        }

        return $value;
    }

    public function normalizeStationQuery(?string $value): string
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return '';
        }

        return (string)($this->canonicalizeStationName($value) ?? $value);
    }

    /**
     * @return array<int,string>
     */
    public function aliasesForStation(?string $value): array
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return [];
        }

        $needle = $this->stationKey($value);
        foreach ($this->stationAliases as $canonical => $aliases) {
            $variants = array_merge([$canonical], (array)$aliases);
            foreach ($variants as $variant) {
                if ($this->stationKey((string)$variant) !== $needle) {
                    continue;
                }

                return $this->uniqueStationQueries($variants);
            }
        }

        return [$value];
    }

    /**
     * @return array<int,string>
     */
    public function expandStationQueries(?string $query): array
    {
        $query = $this->normalizeString($query);
        if ($query === null) {
            return [];
        }

        $stripped = trim((string)(preg_replace('/\s*\/.*$/u', '', $query) ?? $query));
        $variants = array_merge(
            [$query, $this->canonicalizeStationName($query) ?? $query, $stripped],
            $this->aliasesForStation($query)
        );

        return $this->uniqueStationQueries($variants);
    }

    public function hasCentralStationMarker(?string $value): bool
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return false;
        }

        $key = $this->compareKey($value);

        return preg_match('/\b(hbf|hauptbahnhof|central|central station|centralstation|centrale|centraal|hovedbanegard|hovedbanegaard|gare centrale)\b/', $key) === 1;
    }

    public function stationLookupKey(?string $value): string
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return '';
        }

        return $this->stationKey($value);
    }

    public function compareKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        try {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        } catch (\Throwable $e) {
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function stationKey(string $value): string
    {
        $key = $this->compareKey($value);
        $key = str_replace([' hovedbanegard', ' hovedbanegaard'], ' h', $key);
        $key = str_replace([' centralstation', ' centrale', ' centraal'], ' c', $key);

        return $key;
    }

    /**
     * @param array<int,string> $variants
     * @return array<int,string>
     */
    private function uniqueStationQueries(array $variants): array
    {
        $out = [];
        $seen = [];
        foreach ($variants as $variant) {
            $variant = trim((string)$variant);
            if ($variant === '') {
                continue;
            }
            $key = $this->stationKey($variant);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $variant;
        }

        return $out;
    }

    private function applyOperatorDefaults(array &$normalized): void
    {
        $currentCode = $this->normalizeString($normalized['operator_code'] ?? null);
        $currentName = $this->normalizeString($normalized['operator_name'] ?? null);
        $match = null;
        foreach ($this->operators as $code => $definition) {
            $aliases = array_map([$this, 'compareKey'], (array)($definition['aliases'] ?? []));
            $aliases[] = $this->compareKey((string)$code);
            $aliases[] = $this->compareKey((string)($definition['name'] ?? ''));
            $aliases = array_values(array_filter(array_unique($aliases)));
            if (($currentCode !== null && in_array($this->compareKey($currentCode), $aliases, true))
                || ($currentName !== null && in_array($this->compareKey($currentName), $aliases, true))) {
                $match = ['code' => (string)$code] + $definition;
                break;
            }
        }

        if ($match === null) {
            return;
        }

        if ($currentCode === null) {
            $normalized['operator_code'] = (string)$match['code'];
        }
        if ($currentName === null && !empty($match['name'])) {
            $normalized['operator_name'] = (string)$match['name'];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function normalizeCallingPoints(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = [
                'station_name' => $this->canonicalizeStationName($this->normalizeString($item['station_name'] ?? null)),
                'station_code' => $this->normalizeString($item['station_code'] ?? null),
                'planned_arrival_at' => $this->normalizeDateTime($item['planned_arrival_at'] ?? null),
                'estimated_arrival_at' => $this->normalizeDateTime($item['estimated_arrival_at'] ?? null),
                'actual_arrival_at' => $this->normalizeDateTime($item['actual_arrival_at'] ?? null),
                'planned_departure_at' => $this->normalizeDateTime($item['planned_departure_at'] ?? null),
                'estimated_departure_at' => $this->normalizeDateTime($item['estimated_departure_at'] ?? null),
                'actual_departure_at' => $this->normalizeDateTime($item['actual_departure_at'] ?? null),
                'cancelled' => (bool)($item['cancelled'] ?? false),
                'platform' => $this->normalizeString($item['platform'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param array<int,mixed> $remarks
     * @return array<int,string>
     */
    private function normalizeRemarks(array $remarks): array
    {
        $out = [];
        foreach ($remarks as $remark) {
            $text = is_array($remark) ? ($remark['text'] ?? ($remark['summary'] ?? '')) : $remark;
            $text = trim((string)$text);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeRaw(array $raw): array
    {
        if ($raw === []) {
            return [];
        }

        $allowed = [
            'journey_id',
            'trip_id',
            'line_id',
            'from_id',
            'to_id',
            'provider_hint',
            'leg_count',
            'rail_leg_count',
            'transfer_count',
            'transfer_station_names',
            'operator_names',
            'operator_codes',
            'has_connections',
            'has_replacement',
            'generated_path',
            'generated_from_country',
            'generated_to_country',
        ];
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            if (in_array($key, ['transfer_station_names', 'generated_path', 'operator_names', 'operator_codes'], true)) {
                $out[$key] = array_values(array_filter(
                    array_map(static fn($value): string => trim((string)$value), (array)$raw[$key]),
                    static fn(string $value): bool => $value !== ''
                ));
                continue;
            }

            if (in_array($key, ['leg_count', 'rail_leg_count', 'transfer_count'], true)) {
                $out[$key] = is_numeric($raw[$key]) ? (int)$raw[$key] : null;
                continue;
            }

            if (in_array($key, ['has_connections', 'has_replacement'], true)) {
                $out[$key] = (bool)$raw[$key];
                continue;
            }

            $out[$key] = is_scalar($raw[$key]) || $raw[$key] === null ? $raw[$key] : json_encode($raw[$key], JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return null;
        }

        $map = [
            'planned' => 'planned',
            'departed' => 'departed',
            'arrived' => 'arrived',
            'delayed' => 'delayed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'partially_cancelled' => 'partially_cancelled',
            'diverted' => 'diverted',
            'replacement_transport' => 'replacement_transport',
            'unknown' => 'unknown',
        ];
        $key = str_replace([' ', '-'], '_', strtolower($value));

        return $map[$key] ?? 'unknown';
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = $this->normalizeString($value);
        if ($value === null) {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date(DATE_ATOM, $timestamp);
    }

    private function normalizeString(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)round((float)$value) : null;
    }

    private function normalizeFloat(mixed $value, float $min, float $max): float
    {
        $value = is_numeric($value) ? (float)$value : $min;
        if ($value < $min) {
            $value = $min;
        }
        if ($value > $max) {
            $value = $max;
        }

        return $value;
    }
}
