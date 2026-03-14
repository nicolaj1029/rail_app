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

    /** @var array<int,array<string,mixed>>|null */
    private static array $cacheRowsByKey = [];
    private static array $cacheMtimeByKey = [];

    public function __construct(?string $path = null)
    {
        $this->path = $path;
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

        $rows = $this->loadRows($mode);
        if ($rows === []) {
            return [];
        }

        $qn = $this->norm($query);
        $qa = $this->ascii($qn);
        if ($qa === '') {
            return [];
        }
        $qWords = array_values(array_filter(explode(' ', $qa), static fn(string $word): bool => $word !== ''));
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

            $score = 0;
            if ($searchBlob === $qa) {
                $score = 100;
            } elseif (str_starts_with($searchBlob, $qa)) {
                $score = 92;
            } elseif (strpos($searchBlob, $qa) !== false) {
                $score = 80;
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
                continue;
            }

            $nodeType = strtolower((string)($row['node_type'] ?? ''));
            if ($nodeType === 'airport' || $nodeType === 'ferry_terminal' || $nodeType === 'terminal') {
                $score += 6;
            }

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
            if (!empty($row['aliases']) && is_array($row['aliases'])) {
                foreach ($row['aliases'] as $alias) {
                    if (is_string($alias) && trim($alias) !== '') {
                        $tokens[] = trim($alias);
                    }
                }
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
