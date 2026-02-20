<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Lightweight local index for Regulation (EU) 2021/782 (DA).
 *
 * Data source is a precomputed JSON file created by scripts/regulations/index_32021r0782_da.py
 * so we avoid adding PDF parsing dependencies to the PHP runtime.
 */
final class RegulationIndex
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    private string $indexPath;

    public function __construct(?string $indexPath = null)
    {
        $this->indexPath = $indexPath ?: (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'regulations' . DIRECTORY_SEPARATOR . '32021R0782_DA_chunks.json');
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        if (!is_file($this->indexPath)) {
            self::$cache = ['error' => 'missing_index', 'path' => $this->indexPath, 'chunks' => []];
            return self::$cache;
        }
        $raw = (string)file_get_contents($this->indexPath);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::$cache = ['error' => 'invalid_index', 'path' => $this->indexPath, 'chunks' => []];
            return self::$cache;
        }
        $data['chunks'] = is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
        self::$cache = $data;
        return self::$cache;
    }

    /**
     * Simple keyword search (no embeddings) with deterministic scoring.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $wantArticle = null;
        if (preg_match('/\b(?:artikel|art\.?)\s*(\d{1,2})\b/i', $query, $m)) {
            $wantArticle = (int)$m[1];
            if ($wantArticle <= 0) { $wantArticle = null; }
        }
        $idx = $this->load();
        /** @var array<int,array<string,mixed>> $chunks */
        $chunks = (array)($idx['chunks'] ?? []);

        $terms = $this->terms($query);
        if (empty($terms)) {
            $terms = [$query];
        }

        $scored = [];
        foreach ($chunks as $c) {
            $txt = (string)($c['text'] ?? '');
            if ($txt === '') {
                continue;
            }
            $tLow = $this->lower($txt);
            $score = 0;
            if ($wantArticle !== null && (int)($c['article'] ?? 0) === $wantArticle) {
                // Strong bias to the correct article when the query explicitly references it.
                $score += 50;
            }
            foreach ($terms as $t) {
                $t = $this->lower((string)$t);
                if ($t === '') {
                    continue;
                }
                // count occurrences; cap each term to reduce spam
                $count = substr_count($tLow, $t);
                if ($count > 0) {
                    $isNum = ctype_digit($t);
                    $score += min(6, $count) * ($isNum ? 4 : (strlen($t) >= 6 ? 3 : 2));
                }
            }
            if ($score <= 0) {
                continue;
            }
            $scored[] = ['score' => $score, 'chunk' => $c];
        }

        usort($scored, static function ($a, $b) {
            return ($b['score'] <=> $a['score']);
        });

        $out = [];
        foreach (array_slice($scored, 0, max(1, $limit)) as $row) {
            $c = (array)$row['chunk'];
            $out[] = [
                'id' => (string)($c['id'] ?? ''),
                'article' => (int)($c['article'] ?? 0),
                'page_from' => (int)($c['page_from'] ?? 0),
                'page_to' => (int)($c['page_to'] ?? 0),
                'score' => (int)($row['score'] ?? 0),
                'text' => (string)($c['text'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function quote(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $idx = $this->load();
        /** @var array<int,array<string,mixed>> $chunks */
        $chunks = (array)($idx['chunks'] ?? []);
        foreach ($chunks as $c) {
            if ((string)($c['id'] ?? '') === $id) {
                return [
                    'id' => (string)($c['id'] ?? ''),
                    'article' => (int)($c['article'] ?? 0),
                    'page_from' => (int)($c['page_from'] ?? 0),
                    'page_to' => (int)($c['page_to'] ?? 0),
                    'text' => (string)($c['text'] ?? ''),
                ];
            }
        }
        return null;
    }

    /**
     * @return array<int,string>
     */
    private function terms(string $q): array
    {
        $q = $this->lower($q);
        $parts = preg_split('/[^a-z0-9æøå]+/u', $q) ?: [];
        $seen = [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string)$p);
            // keep numeric tokens too (e.g., "18", "19(10)")
            if (ctype_digit($p) && $this->len($p) >= 2) {
                $k = 'n:' . $p; // avoid PHP casting numeric-string keys to int
                if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $p; }
                continue;
            }
            if ($this->len($p) < 3) {
                continue;
            }
            $k = 't:' . $p;
            if (!isset($seen[$k])) { $seen[$k] = true; $out[] = $p; }
        }
        return $out;
    }

    private function lower(string $s): string
    {
        if (function_exists('mb_strtolower')) {
            return (string)mb_strtolower($s);
        }
        return strtolower($s);
    }

    private function len(string $s): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($s);
        }
        return strlen($s);
    }
}
