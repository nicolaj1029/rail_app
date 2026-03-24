<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Multimodal node search for ferry ports/terminals, bus stops/terminals and airports.
 *
 * Rail keeps using the dedicated StationSearchService and large station dataset.
 * Non-rail modes use curated node datasets under config/data/nodes/ that can
 * later be replaced by imported datasets.
 */
final class TransportNodeSearchService
{
    private ?string $path;
    private ?string $ferrySeedPath;
    private StationSearchService $stationSearch;
    private TransportStopNormalizer $stopNormalizer;

    /** @var array<string,array<int,string>> */
    private const LOCATION_ALIASES = [
        'copenhagen' => ['København', 'Kobenhavn'],
    ];

    /** @var array<int,array<string,mixed>>|null */
    private static array $cacheRowsByKey = [];
    private static array $cacheSignatureByKey = [];

    public function __construct(?string $path = null, ?StationSearchService $stationSearch = null, ?string $ferrySeedPath = null, ?TransportStopNormalizer $stopNormalizer = null)
    {
        $this->path = $path;
        $this->stationSearch = $stationSearch ?? new StationSearchService();
        $this->ferrySeedPath = $ferrySeedPath;
        $this->stopNormalizer = $stopNormalizer ?? new TransportStopNormalizer();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(string $mode, string $query, ?string $country = null, int $limit = 10, ?string $kind = null, bool $enableAiNormalization = false): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            return [];
        }
        $kind = $this->normalizeKind($mode, $kind);

        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }

        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === '') {
            $country = null;
        }
        $limit = max(1, min(50, $limit));

        $seedRows = $this->loadSeedRows($mode);
        if ($seedRows !== []) {
            $seedResults = $this->searchCandidateRows($seedRows, $mode, $query, $country, $kind, $limit, $enableAiNormalization);
            if ($seedResults !== []) {
                return $seedResults;
            }
        }

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

        $scored = $this->searchRows($rows, $mode, $country, $qa, $qWords, $kind);
        if ($scored === [] && $country !== null) {
            $scored = $this->searchRows($rows, $mode, null, $qa, $qWords, $kind);
        }
        if ($scored === []) {
            $scored = $this->searchVariantQueries(
                $rows,
                $mode,
                $query,
                $country,
                $kind,
                $enableAiNormalization
            );
        }
        if ($mode === 'ferry' && $scored !== []) {
            $scored = $this->filterFerryCrossingRows($scored, $qa, $qWords);
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

        return $this->dedupeAndLimitResults($scored, $limit);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function searchCandidateRows(array $rows, string $mode, string $query, ?string $country, ?string $kind, int $limit, bool $enableAiNormalization): array
    {
        $qn = $this->norm($query);
        $qa = $this->ascii($qn);
        if ($qa === '') {
            return [];
        }

        $qWords = array_values(array_filter(explode(' ', $qa), static fn(string $word): bool => $word !== ''));
        $scored = $this->searchRows($rows, $mode, $country, $qa, $qWords, $kind);
        if ($scored === [] && $country !== null) {
            $scored = $this->searchRows($rows, $mode, null, $qa, $qWords, $kind);
        }
        if ($scored === []) {
            $scored = $this->searchVariantQueries(
                $rows,
                $mode,
                $query,
                $country,
                $kind,
                $enableAiNormalization
            );
        }
        if ($mode === 'ferry' && $scored !== []) {
            $scored = $this->filterFerryCrossingRows($scored, $qa, $qWords);
        }
        if ($scored === []) {
            return [];
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreCmp = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $this->dedupeAndLimitResults($scored, $limit);
    }

    /**
     * @param array<int,array<string,mixed>> $scored
     * @return array<int,array<string,mixed>>
     */
    private function dedupeAndLimitResults(array $scored, int $limit): array
    {
        $out = [];
        $seen = [];
        foreach ($scored as $row) {
            $key = strtolower((string)($row['mode'] ?? ''))
                . '|' . strtolower((string)($row['country'] ?? ''))
                . '|' . strtolower((string)($row['node_type'] ?? ''))
                . '|' . $this->ascii($this->norm((string)($row['name'] ?? '')));
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
     * @return array<int,array<string,mixed>>
     */
    private function searchVariantQueries(array $rows, string $mode, string $query, ?string $country, ?string $kind, bool $enableAiNormalization): array
    {
        $variants = $this->stopNormalizer->candidateQueries($mode, $query, $enableAiNormalization);
        if ($variants === []) {
            return [];
        }

        $originalQa = $this->ascii($this->norm($query));
        foreach ($variants as $variant) {
            $variantQa = $this->ascii($this->norm($variant));
            if ($variantQa === '' || $variantQa === $originalQa) {
                continue;
            }
            $variantWords = array_values(array_filter(explode(' ', $variantQa), static fn(string $word): bool => $word !== ''));
            $scored = $this->searchRows($rows, $mode, $country, $variantQa, $variantWords, $kind);
            if ($scored === [] && $country !== null) {
                $scored = $this->searchRows($rows, $mode, null, $variantQa, $variantWords, $kind);
            }
            if ($mode === 'ferry' && $scored !== []) {
                $scored = $this->filterFerryCrossingRows($scored, $variantQa, $variantWords);
            }
            if ($scored !== []) {
                return $scored;
            }
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $qWords
     * @return array<int,array<string,mixed>>
     */
    private function searchRows(array $rows, string $mode, ?string $country, string $qa, array $qWords, ?string $kind): array
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

            $nameSearch = (string)($row['__name_search'] ?? '');
            /** @var array<int,string> $aliasSearch */
            $aliasSearch = is_array($row['__alias_search'] ?? null) ? $row['__alias_search'] : [];
            $codeSearch = (string)($row['__code_search'] ?? '');
            $searchBlob = $this->buildRowSearchBlob($row, $nameSearch, $aliasSearch, $codeSearch);
            if ($searchBlob === '') {
                continue;
            }
            $score = $this->scoreRow($mode, $qa, $qWords, $row, $nameSearch, $aliasSearch, $codeSearch, $searchBlob, $kind);

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
                'in_eu' => $this->normalizeNullableBool($row['in_eu'] ?? null),
                'code' => isset($row['code']) ? (string)$row['code'] : null,
                'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
                'lon' => isset($row['lon']) ? (float)$row['lon'] : null,
                'node_type' => $nodeType !== '' ? $nodeType : null,
                'parent_name' => isset($row['parent_name']) ? (string)$row['parent_name'] : null,
                'source' => isset($row['source']) ? (string)$row['source'] : 'seed',
                'verification_status' => isset($row['verification_status']) ? (string)$row['verification_status'] : null,
                'seed_record_type' => isset($row['seed_record_type']) ? (string)$row['seed_record_type'] : null,
            ];
        }

        return $scored;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $aliasSearch
     */
    private function buildRowSearchBlob(array $row, string $nameSearch, array $aliasSearch, string $codeSearch): string
    {
        $parts = [];
        if ($nameSearch !== '') {
            $parts[] = $nameSearch;
        }
        if ($aliasSearch !== []) {
            $parts[] = implode(' ', $aliasSearch);
        }
        if ($codeSearch !== '') {
            $parts[] = $codeSearch;
        }

        $parentSearch = $this->ascii($this->norm((string)($row['parent_name'] ?? '')));
        if ($parentSearch !== '') {
            $parts[] = $parentSearch;
        }

        $citySearch = $this->ascii($this->norm((string)($row['city'] ?? '')));
        if ($citySearch !== '') {
            $parts[] = $citySearch;
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<int,string> $qWords
     * @param array<int,string> $aliasSearch
     * @param array<string,mixed> $row
     */
    private function scoreRow(string $mode, string $qa, array $qWords, array $row, string $nameSearch, array $aliasSearch, string $codeSearch, string $searchBlob, ?string $kind): int
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
        $rawName = (string)($row['name'] ?? '');
        if ($queryLooksLikeSinglePlace && str_contains($rawName, '-')) {
            $score -= 12;
        }

        if ($mode === 'ferry') {
            if ($kind === 'port') {
                if ($nodeType === 'port') {
                    $score += 18;
                } elseif (in_array($nodeType, ['ferry_terminal', 'terminal', 'cruise_terminal'], true)) {
                    $score -= 10;
                }
            } elseif ($kind === 'terminal') {
                if (in_array($nodeType, ['ferry_terminal', 'terminal', 'cruise_terminal'], true)) {
                    $score += 18;
                } elseif ($nodeType === 'port') {
                    $score -= 10;
                }
            } elseif ($queryLooksLikeSinglePlace && $nodeType === 'port') {
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
        $seedPath = match ($mode) {
            'ferry' => $this->resolveFerrySeedPath(),
            'bus' => $this->resolveBusSeedPath(),
            default => null,
        };
        $signature = $this->buildCacheSignature($path, $seedPath);
        $cacheKey = $mode . '|' . $path . '|' . (string)$seedPath;
        if ($signature !== '' && isset(self::$cacheRowsByKey[$cacheKey]) && (self::$cacheSignatureByKey[$cacheKey] ?? null) === $signature) {
            return self::$cacheRowsByKey[$cacheKey];
        }
        if (!is_file($path)) {
            return [];
        }

        $firstChar = $this->firstSignificantChar($path);
        $rows = $firstChar === '['
            ? $this->loadRowsFromStream($path, $mode)
            : $this->loadRowsFromDecodedJson($path, $mode);

        if ($mode === 'ferry') {
            $rows = $this->mergeSeedRows($rows, $this->loadFerrySeedRows($seedPath));
            $rows = $this->enrichFerryRows($rows);
        } elseif ($mode === 'bus') {
            $rows = $this->mergeSeedRows($rows, $this->loadBusSeedRows($seedPath));
        }

        $this->indexRowsForSearch($rows);

        self::$cacheRowsByKey[$cacheKey] = $rows;
        self::$cacheSignatureByKey[$cacheKey] = $signature;

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadSeedRows(string $mode): array
    {
        if ($mode === 'bus') {
            $seedRows = $this->loadBusSeedRows($this->resolveBusSeedPath());
            $this->indexRowsForSearch($seedRows);
            return $seedRows;
        }

        if ($mode === 'ferry') {
            $seedRows = $this->loadFerrySeedRows($this->resolveFerrySeedPath());
            $seedRows = $this->enrichFerryRows($seedRows);
            $this->indexRowsForSearch($seedRows);
            return $seedRows;
        }

        return [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRowsFromDecodedJson(string $path, string $mode): array
    {
        $json = (string)@file_get_contents($path);
        if ($json === '') {
            return [];
        }

        $data = json_decode($json, true);
        unset($json);
        if (!is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $compact = $this->compactSearchableModeRow($row, $mode);
            if ($compact !== null) {
                $rows[] = $compact;
            }
        }

        return $rows;
    }

    /**
     * Stream top-level list JSON to keep memory low on large node catalogs.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadRowsFromStream(string $path, string $mode): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $rows = [];
        $buffer = '';
        $depth = 0;
        $capturing = false;
        $inString = false;
        $escape = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $length = strlen($line);
                for ($i = 0; $i < $length; $i++) {
                    $char = $line[$i];

                    if ($capturing) {
                        $buffer .= $char;
                    }

                    if ($inString) {
                        if ($escape) {
                            $escape = false;
                            continue;
                        }
                        if ($char === '\\') {
                            $escape = true;
                            continue;
                        }
                        if ($char === '"') {
                            $inString = false;
                        }
                        continue;
                    }

                    if ($char === '"') {
                        $inString = true;
                        continue;
                    }

                    if ($char === '{') {
                        if (!$capturing) {
                            $capturing = true;
                            $buffer = '{';
                            $depth = 1;
                            continue;
                        }

                        $depth++;
                        continue;
                    }

                    if ($char !== '}' || !$capturing) {
                        continue;
                    }

                    $depth--;
                    if ($depth !== 0) {
                        continue;
                    }

                    $row = json_decode($buffer, true);
                    if (is_array($row)) {
                        $compact = $this->compactSearchableModeRow($row, $mode);
                        if ($compact !== null) {
                            $rows[] = $compact;
                        }
                    }

                    $capturing = false;
                    $buffer = '';
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function firstSignificantChar(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            while (($char = fgetc($handle)) !== false) {
                if (!ctype_space($char)) {
                    return $char;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function compactSearchableModeRow(array $row, string $mode): ?array
    {
        $name = trim((string)($row['name'] ?? ''));
        $rowMode = strtolower(trim((string)($row['mode'] ?? '')));
        if ($name === '' || $rowMode !== $mode) {
            return null;
        }

        return $this->compactRowForSearch($row, $rowMode);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function compactRowForSearch(array $row, string $mode): array
    {
        $aliases = $this->expandAliases($mode, $row);
        if (count($aliases) > 12) {
            $aliases = array_slice($aliases, 0, 12);
        }

        return [
            'id' => (string)($row['id'] ?? ''),
            'mode' => $mode,
            'name' => trim((string)($row['name'] ?? '')),
            'aliases' => $aliases,
            'code' => isset($row['code']) ? (string)$row['code'] : null,
            'country' => strtoupper((string)($row['country'] ?? '')),
            'in_eu' => $this->normalizeNullableBool($row['in_eu'] ?? null),
            'lat' => isset($row['lat']) && is_numeric($row['lat']) ? (float)$row['lat'] : null,
            'lon' => isset($row['lon']) && is_numeric($row['lon']) ? (float)$row['lon'] : null,
            'node_type' => isset($row['node_type']) ? (string)$row['node_type'] : null,
            'parent_name' => isset($row['parent_name']) ? (string)$row['parent_name'] : null,
            'city' => isset($row['city']) ? (string)$row['city'] : null,
            'source' => isset($row['source']) ? (string)$row['source'] : 'seed',
            'verification_status' => isset($row['verification_status']) ? (string)$row['verification_status'] : null,
            'seed_record_type' => isset($row['seed_record_type']) ? (string)$row['seed_record_type'] : null,
        ];
    }

    private function normalizeKind(string $mode, ?string $kind): ?string
    {
        $kind = strtolower(trim((string)$kind));
        if ($kind === '') {
            return null;
        }

        return match ($mode) {
            'ferry' => in_array($kind, ['port', 'terminal'], true) ? $kind : null,
            'bus' => $kind === 'terminal' ? $kind : null,
            'air' => $kind === 'airport' ? $kind : null,
            default => null,
        };
    }

    private function buildCacheSignature(?string $path, ?string $seedPath): string
    {
        $parts = [];
        foreach ([$path, $seedPath] as $candidate) {
            if ($candidate === null || $candidate === '' || !is_file($candidate)) {
                $parts[] = '';
                continue;
            }
            $parts[] = $candidate . ':' . (string)((int)@filemtime($candidate)) . ':' . (string)((int)@filesize($candidate));
        }

        return implode('|', $parts);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,array<string,mixed>> $seedRows
     * @return array<int,array<string,mixed>>
     */
    private function mergeSeedRows(array $rows, array $seedRows): array
    {
        if ($seedRows === []) {
            return $rows;
        }

        $index = [];
        foreach ($rows as $i => $row) {
            foreach ($this->semanticRowKeys($row) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $i;
                }
            }
        }

        foreach ($seedRows as $seedRow) {
            $matchedIndex = null;
            foreach ($this->semanticRowKeys($seedRow) as $key) {
                if (isset($index[$key])) {
                    $matchedIndex = $index[$key];
                    break;
                }
            }

            if ($matchedIndex !== null) {
                $rows[$matchedIndex] = $this->overlaySeedRow($rows[$matchedIndex], $seedRow);
                continue;
            }

            $rows[] = $seedRow;
            $newIndex = count($rows) - 1;
            foreach ($this->semanticRowKeys($seedRow) as $key) {
                if (!isset($index[$key])) {
                    $index[$key] = $newIndex;
                }
            }
        }

        return $rows;
    }

    private function resolveBusSeedPath(): ?string
    {
        $default = TransportDataPaths::busTerminalSeed();
        if (is_file($default)) {
            return $default;
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadBusSeedRows(?string $path): array
    {
        if ($path === null || $path === '' || !is_file($path)) {
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
            if ($name === '') {
                continue;
            }

            $country = strtoupper(trim((string)($row['country'] ?? '')));
            $inEu = null;
            if (array_key_exists('in_eu', $row) && $row['in_eu'] !== null && $row['in_eu'] !== '') {
                $inEu = (bool)$row['in_eu'];
            } elseif ($country !== '') {
                $inEu = $this->isEuCountry($country);
            }

            $rows[] = [
                'id' => (string)($row['id'] ?? ('bus-seed-' . md5($name . '|' . $country))),
                'mode' => 'bus',
                'name' => $name,
                'aliases' => is_array($row['aliases'] ?? null) ? array_values($row['aliases']) : [],
                'code' => isset($row['code']) ? (string)$row['code'] : null,
                'country' => $country,
                'in_eu' => $inEu,
                'lat' => isset($row['lat']) && is_numeric($row['lat']) ? (float)$row['lat'] : null,
                'lon' => isset($row['lon']) && is_numeric($row['lon']) ? (float)$row['lon'] : null,
                'node_type' => isset($row['node_type']) ? (string)$row['node_type'] : 'terminal',
                'parent_name' => isset($row['parent_name']) ? (string)$row['parent_name'] : null,
                'city' => isset($row['city']) ? (string)$row['city'] : null,
                'source' => isset($row['source']) ? (string)$row['source'] : 'bus_seed_v1',
                'verification_status' => isset($row['verification_status']) ? (string)$row['verification_status'] : null,
                'seed_record_type' => isset($row['seed_record_type']) ? (string)$row['seed_record_type'] : 'bus_seed',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private function semanticRowKeys(array $row): array
    {
        $mode = strtolower(trim((string)($row['mode'] ?? '')));
        $nodeType = strtolower(trim((string)($row['node_type'] ?? '')));
        $name = $this->ascii($this->norm((string)($row['name'] ?? '')));
        if ($mode === '' || $nodeType === '' || $name === '') {
            return [];
        }

        $country = strtoupper(trim((string)($row['country'] ?? '')));
        $parent = $this->ascii($this->norm((string)($row['parent_name'] ?? '')));
        $base = $mode . '|' . $nodeType . '|' . $name;
        $keys = [$base];
        if ($country !== '') {
            $keys[] = $base . '|' . $country;
        }
        if ($parent !== '') {
            $keys[] = $base . '|parent:' . $parent;
            if ($country !== '') {
                $keys[] = $base . '|' . $country . '|parent:' . $parent;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $seed
     * @return array<string,mixed>
     */
    private function overlaySeedRow(array $existing, array $seed): array
    {
        $merged = $existing;
        foreach (['country', 'parent_name', 'city'] as $field) {
            if (trim((string)($seed[$field] ?? '')) !== '') {
                $merged[$field] = $seed[$field];
            }
        }
        $seedInEu = $this->normalizeNullableBool($seed['in_eu'] ?? null);
        if ($seedInEu !== null) {
            $merged['in_eu'] = $seedInEu;
        }
        if (!empty($seed['aliases']) && is_array($seed['aliases'])) {
            $existingAliases = is_array($merged['aliases'] ?? null) ? $merged['aliases'] : [];
            $merged['aliases'] = array_values(array_unique(array_merge($existingAliases, $seed['aliases'])));
        }
        foreach (['source', 'verification_status', 'seed_record_type', 'source_url', 'notes', 'likely_havneterminal', 'passenger_services'] as $field) {
            if (isset($seed[$field]) && trim((string)$seed[$field]) !== '') {
                $merged[$field] = $seed[$field];
            }
        }

        return $merged;
    }

    private function resolveFerrySeedPath(): ?string
    {
        if ($this->ferrySeedPath !== null && $this->ferrySeedPath !== '') {
            return $this->ferrySeedPath;
        }

        $default = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'Doku_og_setup_ferry' . DIRECTORY_SEPARATOR . 'ferry_port_terminal_seed_v1.xlsx';
        if (is_file($default)) {
            return $default;
        }

        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadFerrySeedRows(?string $path): array
    {
        if ($path === null || $path === '' || !is_file($path) || !class_exists(\ZipArchive::class)) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        $dom = new \DOMDocument();
        if (@$dom->loadXML($sheetXml) !== true) {
            return [];
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', $ns);
        $rowNodes = $xpath->query('//x:sheetData/x:row');
        if (!$rowNodes instanceof \DOMNodeList || $rowNodes->length === 0) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $idx => $rowNode) {
            if ($idx === 0) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./x:c', $rowNode) ?: [] as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }
                $ref = strtoupper($cell->getAttribute('r'));
                $column = preg_replace('/[^A-Z]/', '', $ref) ?? '';
                if ($column === '') {
                    continue;
                }
                $cells[$column] = $this->readWorksheetCell($cell);
            }

            $country = strtoupper(trim((string)($cells['A'] ?? '')));
            $portName = trim((string)($cells['B'] ?? ''));
            $terminalName = trim((string)($cells['C'] ?? ''));
            $recordType = strtolower(trim((string)($cells['D'] ?? '')));
            $likelyTerminal = strtolower(trim((string)($cells['E'] ?? '')));
            $passengerServices = strtolower(trim((string)($cells['F'] ?? '')));
            $verificationStatus = trim((string)($cells['G'] ?? ''));
            $sourceUrl = trim((string)($cells['H'] ?? ''));
            $notes = trim((string)($cells['I'] ?? ''));

            if ($country === '' || $portName === '') {
                continue;
            }

            $sharedMeta = [
                'mode' => 'ferry',
                'country' => $country,
                'in_eu' => $this->isEuCountry($country),
                'city' => $portName,
                'source' => 'ferry_seed_v1',
                'verification_status' => $verificationStatus !== '' ? $verificationStatus : null,
                'seed_record_type' => $recordType !== '' ? $recordType : null,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'notes' => $notes !== '' ? $notes : null,
                'likely_havneterminal' => $likelyTerminal !== '' ? $likelyTerminal : null,
                'passenger_services' => $passengerServices !== '' ? $passengerServices : null,
            ];

            $rows[] = [
                'id' => $this->slug('ferry-seed-port-' . $country . '-' . $portName),
                'mode' => 'ferry',
                'name' => $portName,
                'country' => $country,
                'in_eu' => $this->isEuCountry($country),
                'code' => null,
                'lat' => null,
                'lon' => null,
                'node_type' => 'port',
                'parent_name' => null,
                'city' => $portName,
                'aliases' => $terminalName !== '' && $terminalName !== $portName ? [$terminalName] : [],
                'source' => 'ferry_seed_v1',
                'verification_status' => $sharedMeta['verification_status'],
                'seed_record_type' => $sharedMeta['seed_record_type'],
                'source_url' => $sharedMeta['source_url'],
                'notes' => $sharedMeta['notes'],
                'likely_havneterminal' => $sharedMeta['likely_havneterminal'],
                'passenger_services' => $sharedMeta['passenger_services'],
            ];

            if ($terminalName !== '' && in_array($recordType, ['ferry_terminal', 'cruise_terminal'], true)) {
                $aliases = [$portName];
                if ($recordType === 'ferry_terminal' && stripos($terminalName, $portName) === false) {
                    $aliases[] = $portName . ' Ferry Terminal';
                }
                $rows[] = [
                    'id' => $this->slug('ferry-seed-' . $recordType . '-' . $country . '-' . $terminalName),
                    'name' => $terminalName,
                    'code' => null,
                    'lat' => null,
                    'lon' => null,
                    'node_type' => $recordType,
                    'parent_name' => $portName,
                    'aliases' => array_values(array_unique(array_filter($aliases, static fn(string $value): bool => trim($value) !== ''))),
                ] + $sharedMeta;
            }
        }

        return $rows;
    }

    private function readWorksheetCell(\DOMElement $cell): string
    {
        return trim($cell->textContent);
    }

    private function slug(string $value): string
    {
        $slug = $this->ascii($this->norm($value));
        $slug = str_replace(' ', '-', $slug);

        return trim($slug, '-');
    }

    /**
     * Ferry OSM terminals often miss country/EU metadata while the matching port row has it.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function enrichFerryRows(array $rows): array
    {
        $portsByName = [];
        $portsByCity = [];
        foreach ($rows as $row) {
            if (($row['mode'] ?? '') !== 'ferry') {
                continue;
            }
            if (strtolower((string)($row['node_type'] ?? '')) !== 'port') {
                continue;
            }

            $nameKey = $this->ascii($this->norm((string)($row['name'] ?? '')));
            if ($nameKey !== '' && !isset($portsByName[$nameKey])) {
                $portsByName[$nameKey] = $row;
            }

            $cityKey = $this->ascii($this->norm((string)($row['city'] ?? '')));
            if ($cityKey !== '' && !isset($portsByCity[$cityKey])) {
                $portsByCity[$cityKey] = $row;
            }
        }

        foreach ($rows as &$row) {
            if (($row['mode'] ?? '') !== 'ferry') {
                continue;
            }
            if (!in_array(strtolower((string)($row['node_type'] ?? '')), ['ferry_terminal', 'cruise_terminal'], true)) {
                continue;
            }
            if (trim((string)($row['country'] ?? '')) !== '') {
                continue;
            }

            $portMatch = null;
            $nameKey = $this->ascii($this->norm((string)($row['name'] ?? '')));
            if ($nameKey !== '' && isset($portsByName[$nameKey])) {
                $portMatch = $portsByName[$nameKey];
            }

            if ($portMatch === null) {
                $cityKey = $this->ascii($this->norm((string)($row['city'] ?? '')));
                if ($cityKey !== '' && isset($portsByCity[$cityKey])) {
                    $portMatch = $portsByCity[$cityKey];
                }
            }

            if ($portMatch === null) {
                continue;
            }

            $row['country'] = strtoupper((string)($portMatch['country'] ?? ''));
            if (array_key_exists('in_eu', $portMatch)) {
                $row['in_eu'] = (bool)$portMatch['in_eu'];
            }
            if (empty($row['parent_name'])) {
                $row['parent_name'] = (string)($portMatch['name'] ?? '');
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $qWords
     * @return array<int,array<string,mixed>>
     */
    private function filterFerryCrossingRows(array $rows, string $qa, array $qWords): array
    {
        $queryLooksLikeSinglePlace = !str_contains($qa, '-') && count($qWords) <= 2;
        if (!$queryLooksLikeSinglePlace) {
            return $rows;
        }

        $hasSimplePlaceMatch = false;
        foreach ($rows as $row) {
            $nameSearch = $this->ascii($this->norm((string)($row['name'] ?? '')));
            $nodeType = strtolower((string)($row['node_type'] ?? ''));
            if ($nameSearch === $qa && !str_contains($nameSearch, '-') && in_array($nodeType, ['port', 'ferry_terminal', 'terminal', 'cruise_terminal'], true)) {
                $hasSimplePlaceMatch = true;
                break;
            }
        }
        if (!$hasSimplePlaceMatch) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row): bool {
            $nodeType = strtolower((string)($row['node_type'] ?? ''));
            if (!in_array($nodeType, ['ferry_terminal', 'cruise_terminal'], true)) {
                return true;
            }

            return !str_contains((string)($row['name'] ?? ''), '-');
        }));
    }

    private function resolvePath(string $mode): ?string
    {
        if ($this->path !== null && $this->path !== '') {
            return $this->path;
        }

        $searchPath = TransportDataPaths::transportNodesSearch($mode);
        if (is_file($searchPath)) {
            return $searchPath;
        }

        $fallbackPath = TransportDataPaths::transportNodes();
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

    private function normalizeNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            '1', 'true', 'yes', 'ja', 'y' => true,
            '0', 'false', 'no', 'nej', 'n' => false,
            default => null,
        };
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function indexRowsForSearch(array &$rows): void
    {
        foreach ($rows as &$row) {
            $name = trim((string)($row['name'] ?? ''));
            $mode = strtolower(trim((string)($row['mode'] ?? '')));
            if ($name === '' || !in_array($mode, ['ferry', 'bus', 'air'], true)) {
                continue;
            }

            $aliases = is_array($row['aliases'] ?? null) ? $row['aliases'] : $this->expandAliases($mode, $row);
            if (count($aliases) > 8) {
                $aliases = array_slice($aliases, 0, 8);
            }

            $row['mode'] = $mode;
            $row['country'] = strtoupper((string)($row['country'] ?? ''));
            $row['__name_search'] = $this->ascii($this->norm($name));
            $row['__alias_search'] = array_values(array_filter(array_map(fn(string $value): string => $this->ascii($this->norm($value)), $aliases), static fn(string $value): bool => $value !== ''));
            if (count($row['__alias_search']) > 8) {
                $row['__alias_search'] = array_slice($row['__alias_search'], 0, 8);
            }
            $row['__code_search'] = !empty($row['code']) ? $this->ascii($this->norm((string)$row['code'])) : '';
            unset($row['aliases']);
        }
        unset($row);
    }
}
