<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Multimodal node search for ferry ports/terminals, bus stops/terminals and airports.
 *
 * Rail keeps using the dedicated StationSearchService and large station dataset.
 * Non-rail modes use a smaller curated transport_nodes.json seed that can later
 * be replaced by imported datasets.
 */
final class TransportNodeSearchService
{
    private ?string $path;
    private StationSearchService $stationSearch;

    /** @var array<string,array<int,string>> */
    private const LOCATION_ALIASES = [
        'copenhagen' => ['København', 'Kobenhavn'],
    ];

    /** @var array<int,array<string,mixed>>|null */
    private static array $cacheRowsByKey = [];
    private static array $cacheMtimeByKey = [];

    public function __construct(?string $path = null, ?StationSearchService $stationSearch = null)
    {
        $this->path = $path;
        $this->stationSearch = $stationSearch ?? new StationSearchService();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(string $mode, string $query, ?string $country = null, int $limit = 10): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            return [];
        }

        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }

        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === '') {
            $country = null;
        }
        $limit = max(1, min(50, $limit));

        $qn = $this->norm($query);
        $qa = $this->ascii($qn);
        if ($qa === '') {
            return [];
        }
        $qWords = array_values(array_filter(explode(' ', $qa), static fn(string $word): bool => $word !== ''));
        $rows = $this->loadRows($mode);
        if ($rows === []) {
            return $mode === 'bus' ? $this->fallbackBusNodes($query, $country, $limit) : [];
        }

        $scored = $this->searchRows($rows, $mode, $country, $qa, $qWords);
        if ($scored === [] && $country !== null) {
            $scored = $this->searchRows($rows, $mode, null, $qa, $qWords);
        }

        if ($scored === []) {
            return $mode === 'bus' ? $this->fallbackBusNodes($query, $country, $limit) : [];
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreCmp = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $out = [];
        $seen = [];
        foreach ($scored as $row) {
            $key = strtolower((string)($row['mode'] ?? '')) . '|' . strtolower((string)($row['country'] ?? '')) . '|' . $this->ascii($this->norm((string)($row['name'] ?? '')));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            unset($row['score']);
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $qWords
     * @return array<int,array<string,mixed>>
     */
    private function searchRows(array $rows, string $mode, ?string $country, string $qa, array $qWords): array
    {
        $scored = [];

        foreach ($rows as $row) {
            if (($row['mode'] ?? '') !== $mode) {
                continue;
            }
            $rowCountry = strtoupper((string)($row['country'] ?? ''));
            if ($country !== null && $rowCountry !== $country) {
                continue;
            }

            $searchBlob = (string)($row['__search'] ?? '');
            if ($searchBlob === '') {
                continue;
            }

            $nameSearch = (string)($row['__name_search'] ?? '');
            /** @var array<int,string> $aliasSearch */
            $aliasSearch = is_array($row['__alias_search'] ?? null) ? $row['__alias_search'] : [];
            $codeSearch = (string)($row['__code_search'] ?? '');
            $score = $this->scoreRow($mode, $qa, $qWords, $row, $nameSearch, $aliasSearch, $codeSearch, $searchBlob);

            if ($score <= 0) {
                continue;
            }

            $nodeType = strtolower((string)($row['node_type'] ?? ''));

            $scored[] = [
                'score' => $score,
                'id' => (string)($row['id'] ?? ''),
                'mode' => $mode,
                'name' => (string)($row['name'] ?? ''),
                'country' => $rowCountry !== '' ? $rowCountry : null,
                'in_eu' => isset($row['in_eu']) ? (bool)$row['in_eu'] : null,
                'code' => isset($row['code']) ? (string)$row['code'] : null,
                'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
                'lon' => isset($row['lon']) ? (float)$row['lon'] : null,
                'node_type' => $nodeType !== '' ? $nodeType : null,
                'parent_name' => isset($row['parent_name']) ? (string)$row['parent_name'] : null,
                'source' => isset($row['source']) ? (string)$row['source'] : 'seed',
            ];
        }

        return $scored;
    }

    /**
     * @param array<int,string> $qWords
     * @param array<int,string> $aliasSearch
     * @param array<string,mixed> $row
     */
    private function scoreRow(string $mode, string $qa, array $qWords, array $row, string $nameSearch, array $aliasSearch, string $codeSearch, string $searchBlob): int
    {
        $score = 0;

        if ($nameSearch === $qa) {
            $score = 124;
        } elseif ($codeSearch !== '' && $codeSearch === $qa) {
            $score = 120;
        } elseif ($codeSearch !== '' && str_starts_with($codeSearch, $qa)) {
            $score = 110;
        } elseif (in_array($qa, $aliasSearch, true)) {
            $score = 118;
        } elseif ($nameSearch !== '' && str_starts_with($nameSearch, $qa)) {
            $score = 110;
        } elseif ($this->startsWithAny($aliasSearch, $qa)) {
            $score = 102;
        } elseif ($nameSearch !== '' && strpos($nameSearch, $qa) !== false) {
            $score = 94;
        } elseif ($this->containsAny($aliasSearch, $qa)) {
            $score = 88;
        } elseif (strpos($searchBlob, $qa) !== false) {
            $score = 76;
        } else {
            $hits = 0;
            foreach ($qWords as $word) {
                if (strpos($searchBlob, $word) !== false) {
                    $hits++;
                }
            }
            if ($hits === count($qWords) && $hits > 0) {
                $score = 65 + min(10, $hits * 2);
            }
        }

        if ($score <= 0) {
            return 0;
        }

        $nodeType = strtolower((string)($row['node_type'] ?? ''));
        $queryLooksLikeSinglePlace = !str_contains($qa, '-') && count($qWords) <= 2;
        if ($queryLooksLikeSinglePlace && $nameSearch !== '' && str_contains($nameSearch, '-')) {
            $score -= 12;
        }

        if ($mode === 'ferry') {
            if ($queryLooksLikeSinglePlace && $nodeType === 'port') {
                $score += 8;
            } elseif ($nodeType === 'ferry_terminal') {
                $score += 4;
            }
        } elseif ($mode === 'air' && $nodeType === 'airport') {
            $score += 6;
        } elseif ($mode === 'bus' && ($nodeType === 'terminal' || $nodeType === 'stop' || $nodeType === 'bus_terminal')) {
            $score += 5;
        }

        if (((string)($row['country'] ?? '')) !== '') {
            $score += 2;
        }

        return $score;
    }

    /**
     * @param array<int,string> $values
     */
    private function startsWithAny(array $values, string $query): bool
    {
        foreach ($values as $value) {
            if ($value !== '' && str_starts_with($value, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $values
     */
    private function containsAny(array $values, string $query): bool
    {
        foreach ($values as $value) {
            if ($value !== '' && strpos($value, $query) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fallbackBusNodes(string $query, ?string $country, int $limit): array
    {
        $stations = $this->stationSearch->search($query, $country, $limit);
        if ($stations === []) {
            return [];
        }

        $rows = [];
        foreach ($stations as $station) {
            $stationCountry = isset($station['country']) ? strtoupper((string)$station['country']) : null;
            $rows[] = [
                'id' => 'bus-fallback-' . md5((string)($station['name'] ?? '') . '|' . (string)$stationCountry),
                'mode' => 'bus',
                'name' => (string)($station['name'] ?? ''),
                'country' => $stationCountry,
                'in_eu' => $stationCountry !== null ? $this->isEuCountry($stationCountry) : null,
                'code' => null,
                'lat' => isset($station['lat']) ? (float)$station['lat'] : null,
                'lon' => isset($station['lon']) ? (float)$station['lon'] : null,
                'node_type' => 'terminal',
                'parent_name' => null,
                'source' => 'rail_station_fallback',
            ];
        }

        return $rows;
    }

    private function isEuCountry(string $country): bool
    {
        static $eu = [
            'AT' => true, 'BE' => true, 'BG' => true, 'HR' => true, 'CY' => true, 'CZ' => true,
            'DE' => true, 'DK' => true, 'EE' => true, 'ES' => true, 'FI' => true, 'FR' => true,
            'GR' => true, 'HU' => true, 'IE' => true, 'IT' => true, 'LT' => true, 'LU' => true,
            'LV' => true, 'MT' => true, 'NL' => true, 'PL' => true, 'PT' => true, 'RO' => true,
            'SE' => true, 'SI' => true, 'SK' => true,
        ];

        return isset($eu[strtoupper($country)]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRows(string $mode): array
    {
        $path = $this->resolvePath($mode);
        if ($path === null) {
            return [];
        }
        $mtime = is_file($path) ? (int)@filemtime($path) : null;
        $cacheKey = $mode . '|' . $path;
        if ($mtime !== null && isset(self::$cacheRowsByKey[$cacheKey]) && (self::$cacheMtimeByKey[$cacheKey] ?? null) === $mtime) {
            return self::$cacheRowsByKey[$cacheKey];
        }
        if (!is_file($path)) {
            return [];
        }

        $json = (string)@file_get_contents($path);
        if ($json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            $mode = strtolower(trim((string)($row['mode'] ?? '')));
            if ($name === '' || !in_array($mode, ['ferry', 'bus', 'air'], true)) {
                continue;
            }

            $tokens = [$name];
            $aliases = $this->expandAliases($mode, $row);
            foreach ($aliases as $alias) {
                $tokens[] = $alias;
            }
            if (!empty($row['code'])) {
                $tokens[] = (string)$row['code'];
            }
            if (!empty($row['parent_name'])) {
                $tokens[] = (string)$row['parent_name'];
            }
            if (!empty($row['city'])) {
                $tokens[] = (string)$row['city'];
            }

            $row['mode'] = $mode;
            $row['country'] = strtoupper((string)($row['country'] ?? ''));
            $row['__name_search'] = $this->ascii($this->norm($name));
            $row['__alias_search'] = array_values(array_filter(array_map(fn(string $value): string => $this->ascii($this->norm($value)), $aliases), static fn(string $value): bool => $value !== ''));
            $row['__code_search'] = !empty($row['code']) ? $this->ascii($this->norm((string)$row['code'])) : '';
            $row['__search'] = $this->ascii($this->norm(implode(' ', $tokens)));
            $rows[] = $row;
        }

        self::$cacheRowsByKey[$cacheKey] = $rows;
        self::$cacheMtimeByKey[$cacheKey] = $mtime;

        return $rows;
    }

    private function resolvePath(string $mode): ?string
    {
        if ($this->path !== null && $this->path !== '') {
            return $this->path;
        }

        $searchPath = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_nodes_search_' . $mode . '.json';
        if (is_file($searchPath)) {
            return $searchPath;
        }

        $fallbackPath = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_nodes.json';
        if (is_file($fallbackPath)) {
            return $fallbackPath;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private function expandAliases(string $mode, array $row): array
    {
        $aliases = [];
        if (!empty($row['aliases']) && is_array($row['aliases'])) {
            foreach ($row['aliases'] as $alias) {
                if (is_string($alias) && trim($alias) !== '') {
                    $aliases[] = trim($alias);
                }
            }
        }

        foreach ([$row['city'] ?? null, $row['name'] ?? null] as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $key = $this->ascii($this->norm($candidate));
            if ($key === '') {
                continue;
            }
            foreach (self::LOCATION_ALIASES[$key] ?? [] as $alias) {
                $aliases[] = $alias;
            }
        }

        $aliases = array_values(array_unique(array_filter(array_map('trim', $aliases), static fn(string $value): bool => $value !== '')));

        return $aliases;
    }

    private function norm(string $value): string
    {
        $text = mb_strtolower(trim($value), 'UTF-8');
        $text = str_replace(["\u{00A0}"], ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim((string)$text);
    }

    private function ascii(string $value): string
    {
        $text = $value;
        try {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        } catch (\Throwable $e) {
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim((string)$text);
    }
}
