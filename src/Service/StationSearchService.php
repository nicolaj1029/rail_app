<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Offline station search for ticketless mode.
 *
 * Data source: config/data/nodes/stations_coords.json (list of rows).
 * Each row should contain at least: name, lat, lon. Optional: country, osm_id, type, source.
 */
final class StationSearchService
{
    private string $path;

    /** @var array<int,array<string,mixed>>|null */
    private static ?array $cacheRows = null;
    private static ?int $cacheMtime = null;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: TransportDataPaths::stationsCoords();
    }

    /**
     * @return array<int,array{name:string,country?:string|null,lat:float,lon:float,osm_id?:int|string|null,type?:string|null,source?:string|null}>
     */
    public function search(string $query, ?string $country = null, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2) { return []; }

        $limit = max(1, min(50, $limit));
        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === '') { $country = null; }

        $rows = $this->loadRows($country);
        if (empty($rows)) { return []; }

        $qn = $this->norm($query);
        $qa = $this->ascii($qn);
        if ($qa === '') { return []; }

        $qWords = array_values(array_filter(explode(' ', $qa), fn($w) => $w !== ''));
        $qLen = strlen($qa);
        $qFirst = $qLen > 0 ? substr($qa, 0, 1) : '';

        $scored = [];
        foreach ($rows as $row) {
            if (!is_array($row)) { continue; }
            $name = (string)($row['name'] ?? '');
            if ($name === '') { continue; }
            $cc = isset($row['country']) ? strtoupper((string)$row['country']) : null;
            $type = isset($row['type']) ? strtolower((string)$row['type']) : '';
            $na = (string)($row['__na'] ?? '');
            if ($na === '') { continue; }
            // Quick prune: for very short queries, require matching first letter when available.
            if ($qLen <= 3 && $qFirst !== '' && isset($row['__f']) && (string)$row['__f'] !== '' && (string)$row['__f'] !== $qFirst) {
                continue;
            }

            $score = 0;
            if ($na === $qa) {
                $score = 100;
            } elseif (str_starts_with($na, $qa)) {
                $score = 92;
            } elseif (strpos($na, $qa) !== false) {
                $score = 80;
            } else {
                // Loose word match: all query words appear in any order.
                if (!empty($qWords)) {
                    $hits = 0;
                    foreach ($qWords as $w) { if (strpos($na, $w) !== false) { $hits++; } }
                    if ($hits === count($qWords)) {
                        $score = 65 + min(10, $hits * 2);
                    }
                }
            }

            if ($score <= 0) { continue; }

            // Prefer full stations over halts/stops when the textual match is comparable.
            if ($type === 'station') { $score += 8; }
            elseif ($type === 'halt') { $score += 2; }

            // Boost common "main station" names so city queries typically surface the correct choice first.
            // This reduces UX noise in cities like Düsseldorf where many halts exist.
            if (preg_match('/\\b(hbf|hauptbahnhof|centralstation|central\\s*station|centrale|centraal|gare\\s*centrale)\\b/', $na)) {
                $score += 10;
            }

            $scored[] = [
                'score' => $score,
                'name' => $name,
                'country' => $cc ?: null,
                'lat' => (float)($row['lat'] ?? 0),
                'lon' => (float)($row['lon'] ?? 0),
                'osm_id' => $row['osm_id'] ?? null,
                'type' => $type !== '' ? $type : (isset($row['type']) ? (string)$row['type'] : null),
                'source' => isset($row['source']) ? (string)$row['source'] : null,
            ];
        }

        if (empty($scored)) { return []; }

        usort($scored, function($a, $b){
            $d = ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            if ($d !== 0) { return $d; }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $out = [];
        $seen = [];
        foreach ($scored as $s) {
            $key = strtolower((string)($s['country'] ?? '')) . '|' . $this->ascii($this->norm((string)($s['name'] ?? '')));
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            unset($s['score']);
            $out[] = $s;
            if (count($out) >= $limit) { break; }
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function loadRows(?string $country = null): array
    {
        $path = $this->path;
        $mtime = is_file($path) ? (int)@filemtime($path) : null;
        if ($mtime !== null && self::$cacheRows !== null && self::$cacheMtime === $mtime) {
            return $this->filterRowsByCountry(self::$cacheRows, $country);
        }
        if (!is_file($path)) { return []; }

        $firstChar = $this->firstSignificantChar($path);
        $all = $firstChar === '['
            ? $this->loadRowsFromStream($path)
            : $this->loadRowsFromDecodedJson($path);

        self::$cacheRows = $all;
        self::$cacheMtime = $mtime;
        return $this->filterRowsByCountry($all, $country);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRowsFromDecodedJson(string $path): array
    {
        $json = (string)@file_get_contents($path);
        if ($json === '') { return []; }

        $data = json_decode($json, true);
        unset($json);
        if (!is_array($data)) { return []; }

        // Expect list; tolerate map by turning into compact list in-place.
        $isList = function_exists('array_is_list') ? array_is_list($data) : (array_keys($data) === range(0, count($data) - 1));
        if (!$isList) {
            $rows = [];
            foreach ($data as $k => $v) {
                if (!is_array($v)) { continue; }
                $row = $this->compactRow([
                    'name' => (string)$k,
                    'lat' => $v['lat'] ?? null,
                    'lon' => $v['lon'] ?? null,
                    'country' => $v['country'] ?? null,
                    'osm_id' => $v['osm_id'] ?? null,
                    'type' => $v['type'] ?? null,
                    'source' => $v['source'] ?? null,
                ]);
                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            return $rows;
        }

        foreach ($data as $idx => $row) {
            if (!is_array($row)) {
                unset($data[$idx]);
                continue;
            }

            $compact = $this->compactRow($row);
            if ($compact === null) {
                unset($data[$idx]);
                continue;
            }

            $data[$idx] = $compact;
        }

        return array_values($data);
    }

    /**
     * Stream top-level list JSON to keep peak memory low.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadRowsFromStream(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) { return []; }

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
                        $compact = $this->compactRow($row);
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
        if ($handle === false) { return null; }

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
    private function compactRow(array $row): ?array
    {
        $name = (string)($row['name'] ?? ($row['station'] ?? ''));
        if ($name === '' || !isset($row['lat'], $row['lon'])) { return null; }

        $cc = isset($row['country']) ? strtoupper((string)$row['country']) : '';
        $nn = $this->norm($name);
        $na = $this->ascii($nn);
        if ($na === '') { return null; }

        return [
            'name' => $name,
            'country' => $cc !== '' ? $cc : null,
            'lat' => (float)$row['lat'],
            'lon' => (float)$row['lon'],
            'osm_id' => $row['osm_id'] ?? null,
            'type' => isset($row['type']) ? (string)$row['type'] : null,
            'source' => isset($row['source']) ? (string)$row['source'] : null,
            '__na' => $na,
            '__f' => substr($na, 0, 1),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function filterRowsByCountry(array $rows, ?string $country): array
    {
        if ($country === null || $country === '') {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            $rowCountry = strtoupper((string)($row['country'] ?? ''));
            if ($rowCountry === $country) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    private function norm(string $s): string
    {
        $t = mb_strtolower(trim($s), 'UTF-8');
        $t = str_replace(["\u{00A0}"], ' ', $t); // nbsp
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return trim((string)$t);
    }

    private function ascii(string $s): string
    {
        $t = $s;
        try {
            $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
            if (is_string($tmp) && $tmp !== '') { $t = $tmp; }
        } catch (\Throwable $e) { /* ignore */ }
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9\s]/', ' ', $t) ?? $t;
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;
        return trim((string)$t);
    }
}
