<?php
declare(strict_types=1);

namespace App\Service;

class MctChecker
{
    /**
     * Station-specific minimum connection time rules (in minutes).
     * Extend this list as needed; keys are normalized station names.
     */
    private array $rules = [
        'frankfurt hbf' => 8,
        'köln' => 10,
        'koln' => 10,
        'hamburg' => 12,
        'münchen hbf' => 10,
        'munchen hbf' => 10,
        'muenchen hbf' => 10,
        'düsseldorf hbf' => 10,
        'duesseldorf hbf' => 10,
        'herning st' => 5,
        'vejle st' => 5,
        'bruxelles-midi' => 10,
        'brussels-midi' => 10,
        'paris gare du nord' => 15,
        'berlin hbf' => 12,
        '__default__' => 15,
    ];

    /**
     * Evaluate MCT realism for each interchange based on auto-detected segments.
     * Input segment shape: ['from','to','schedDep','schedArr'] (TicketParseService).
     * Returns an array of results: [ ['station'=>string,'margin'=>int,'threshold'=>int,'realistic'=>bool] ...]
     * @param array<int, array<string,string>> $segments
     * @return array<int, array<string,int|string|bool>>
     */
    public function evaluate(array $segments): array
    {
        $out = [];
        $n = count($segments);
        if ($n < 2) { return $out; }
        for ($i = 0; $i < $n - 1; $i++) {
            $prev = (array)$segments[$i];
            $next = (array)$segments[$i + 1];
            $station = (string)($prev['to'] ?? '');
            $arr = (string)($prev['schedArr'] ?? '');
            $dep = (string)($next['schedDep'] ?? '');
            if ($station === '' || $arr === '' || $dep === '') { continue; }
            $margin = $this->minutesBetween($arr, $dep);
            if ($margin === null) { continue; }
            $thr = $this->getThreshold($station);
            $out[] = [
                'station' => $station,
                'margin' => $margin,
                'threshold' => $thr,
                'realistic' => ($margin >= $thr),
            ];
        }
        return $out;
    }

    /** Infer overall hook value: 'Ja' if all interchanges realistic, 'Nej' if any not, else 'unknown'. */
    public function inferHook(array $results): string
    {
        if (empty($results)) { return 'unknown'; }
        $anyBad = false; $anyGood = false;
        foreach ($results as $r) {
            if (!isset($r['realistic'])) { continue; }
            if ((bool)$r['realistic'] === false) { $anyBad = true; }
            if ((bool)$r['realistic'] === true) { $anyGood = true; }
        }
        if ($anyBad) { return 'Nej'; }
        if ($anyGood) { return 'Ja'; }
        return 'unknown';
    }

    /** Infer specific station result; returns 'Ja'/'Nej'/'unknown'. */
    public function inferForStation(array $results, string $station): string
    {
        $key = $this->normalizeStation($station);
        foreach ($results as $r) {
            $st = $this->normalizeStation((string)($r['station'] ?? ''));
            if ($st === $key) {
                return !empty($r['realistic']) ? 'Ja' : 'Nej';
            }
        }
        return 'unknown';
    }

    /** Map to threshold with alias handling. */
    private function getThreshold(string $station): int
    {
        $k = $this->normalizeStation($station);
        if (isset($this->rules[$k])) { return (int)$this->rules[$k]; }
        // Common aliases
        $alias = [
            'köln hbf' => 'köln', 'koln hbf' => 'koln',
            'frankfurt (main) hbf' => 'frankfurt hbf',
            'berlin hbf (tief)' => 'berlin hbf',
            'muenchen' => 'münchen hbf', 'munchen' => 'munchen hbf',
            'münchen' => 'münchen hbf',
            'duesseldorf' => 'duesseldorf hbf', 'dusseldorf' => 'duesseldorf hbf',
            'herning' => 'herning st', 'vejle' => 'vejle st',
        ];
        if (isset($alias[$k]) && isset($this->rules[$alias[$k]])) { return (int)$this->rules[$alias[$k]]; }
        return (int)$this->rules['__default__'];
    }

    /** Normalize station string for matching. */
    private function normalizeStation(string $s): string
    {
        $s = trim(mb_strtolower($s, 'UTF-8'));
        // Remove platform hints like "gl.5-10" and parentheses content
        $s = preg_replace('/\bgl\.?\s*\d+(?:[-–]\d+)?/u', '', $s) ?? $s;
        $s = preg_replace('/\([^\)]*\)/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    /** Compute minutes difference t2 - t1 for HH:MM with overnight wrap. */
    private function minutesBetween(string $t1, string $t2): ?int
    {
        $a = $this->toMinutes($t1); $b = $this->toMinutes($t2);
        if ($a === null || $b === null) { return null; }
        $d = $b - $a; if ($d < 0) { $d += 24*60; }
        return $d;
    }

    private function toMinutes(string $t): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($t), $m)) { return null; }
        $h = (int)$m[1]; $mi = (int)$m[2];
        if ($h < 0 || $h > 29 || $mi < 0 || $mi > 59) { return null; }
        return $h*60 + $mi;
    }
}
