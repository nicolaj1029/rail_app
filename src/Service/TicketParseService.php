<?php
declare(strict_types=1);

namespace App\Service;

class TicketParseService
{
    /** Cached label filters from config/ticket_labels.json */
    private static ?array $labelFilters = null;
    private static ?int $labelFiltersMtime = null;
    /**
     * Parse multiple train legs from an OCR text blob.
     * Returns an array of segments with keys: from, to, schedDep, schedArr, trainNo (optional).
     * @return array<int, array<string,string>>
     */
    public function parseSegmentsFromText(string $text): array
    {
        $segments = [];
        if (trim($text) === '') { return $segments; }
        // Normalize spaces
        $t = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $text) ?? $text;
        // Split into lines and filter noisy lines
        $lines = preg_split('/\r?\n/', $t) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', (string)$line));
            if ($line === '' || strlen($line) < 5) { continue; }
            $clean[] = $line;
        }

        // Simple arrow pattern: City HH:MM -> City HH:MM
        $arrowRe = '/([A-Za-zÀ-ÖØ-öø-ÿ .\'\-]{3,})\s+((?:[01]?\d|2[0-3]):[0-5]\d)\s*[›→\-–>]+\s*([A-Za-zÀ-ÖØ-öø-ÿ .\'\-]{3,})\s+((?:[01]?\d|2[0-3]):[0-5]\d)/u';
        // Train pattern (loose)
        $trainRe = '/\b(TGV|ICE|IC|EC|RE|RJ)\s*(\d{2,5})\b|\bTrain\s*No\.?\s*(\d{2,5})\b|\b(?:Zug|Treno|Tog)\s*(\d{2,5})\b/i';

        foreach ($clean as $line) {
            if (preg_match($arrowRe, $line, $m)) {
                $from = $this->cleanStation($m[1] ?? '');
                $dep = (string)($m[2] ?? '');
                $to = $this->cleanStation($m[3] ?? '');
                $arr = (string)($m[4] ?? '');
                $train = '';
                if (preg_match($trainRe, $line, $mt)) {
                    $cands = array_slice($mt, 1);
                    foreach ($cands as $v) { if (!empty($v)) { $train = is_numeric($v) ? $v : $v; break; } }
                }
                // Ignore obvious labels
                if ($from !== '' && $to !== '' && $dep !== '' && $arr !== '' && !$this->isNonStationLabel($from) && !$this->isNonStationLabel($to)) {
                    $segments[] = [
                        'from' => $from,
                        'to' => $to,
                        'schedDep' => $dep,
                        'schedArr' => $arr,
                        'trainNo' => $train,
                    ];
                }
            }
        }

        // Fallback: parse DB-style itinerary table
        if (count($segments) === 0) {
            $rows = [];
            $timeRe = '/(?:\b|\s)((?:[01]?\d|2[0-3]):[0-5]\d)(?:\b|\s)/u';
            $prodRe = '/\b(TGV|ICE|IC|EC|RJ|RE|RB)\s*([0-9]{1,5})\b/u';
            foreach ($clean as $line) {
                // Extract station name up to either ' Gl.' or time token
                $station = '';
                if (preg_match('/^([^\d]*?)(?:\s+Gl\.|\s+Gleis|\s+(?:\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{2,4})|\s+(?:[01]?\d|2[0-3]):[0-5]\d)/u', $line, $sm)) {
                    $station = $this->cleanStation((string)($sm[1] ?? ''));
                } else {
                    // As a fallback, take leading words until two consecutive spaces followed by time
                    if (preg_match('/^(.*?)\s{2,}.*$/u', $line, $mm)) {
                        $station = $this->cleanStation((string)$mm[1]);
                    }
                }
                if ($station === '' || mb_strlen($station, 'UTF-8') < 3) { continue; }
                if ($this->isNonStationLabel($station)) { continue; }
                $time = '';
                if (preg_match($timeRe, $line, $tm)) { $time = (string)$tm[1]; }
                $prod = '';
                if (preg_match($prodRe, $line, $pm)) { $prod = trim($pm[1] . ' ' . $pm[2]); }
                // Skip lines that are clearly boilerplate
                if ($time === '' && $prod === '') { continue; }
                $rows[] = ['station' => $station, 'time' => $time, 'prod' => $prod];
            }
            // Derive segments by product change boundaries
            $i = 0; $n = count($rows);
            while ($i < $n) {
                // Seek a start row with either a time or a product
                $start = null; $startIdx = $i;
                while ($startIdx < $n) {
                    if ($rows[$startIdx]['time'] !== '' || $rows[$startIdx]['prod'] !== '') { $start = $rows[$startIdx]; break; }
                    $startIdx++;
                }
                if ($start === null) { break; }
                $currProd = $start['prod'];
                // Find end boundary: next row where product changes, else last row
                $endIdx = $startIdx + 1;
                while ($endIdx < $n) {
                    $p = $rows[$endIdx]['prod'];
                    if ($p !== '' && $currProd !== '' && $p !== $currProd) { break; }
                    $endIdx++;
                }
                $endRow = $rows[min($endIdx, $n-1)];
                // Avoid degenerate case: if end equals start, try to extend by one
                if ($endIdx === $startIdx) { $endIdx = min($startIdx + 1, $n-1); $endRow = $rows[$endIdx]; }
                // Build segment
                $from = $start['station'];
                $to = $endRow['station'];
                $dep = $start['time'];
                $arr = $endRow['time'];
                if ($from !== '' && $to !== '' && $from !== $to && !$this->isNonStationLabel($from) && !$this->isNonStationLabel($to)) {
                    $segments[] = [
                        'from' => $from,
                        'to' => $to,
                        'schedDep' => $dep,
                        'schedArr' => $arr,
                        'trainNo' => $currProd,
                    ];
                }
                $i = max($endIdx, $startIdx + 1);
            }
        }

        // If we have overall date(s), attach them to segments
        $dates = $this->extractDates($t);
        if (!empty($dates)) {
            $date0 = $dates[0];
            foreach ($segments as &$s) { if (empty($s['depDate'])) { $s['depDate'] = $date0; } if (empty($s['arrDate'])) { $s['arrDate'] = $date0; } }
            unset($s);
        }

        // Deduplicate by key
        $uniq = [];
        $out = [];
        foreach ($segments as $s) {
            $key = strtoupper(($s['from'] ?? '') . '|' . ($s['to'] ?? '') . '|' . ($s['schedDep'] ?? '') . '|' . ($s['schedArr'] ?? ''));
            if (isset($uniq[$key])) { continue; }
            $uniq[$key] = true;
            $out[] = $s;
        }
        return $out;
    }

    /** Heuristic filter for non-station labels commonly found on tickets (e.g., 'Gültigkeit'). */
    private function isNonStationLabel(string $s): bool
    {
        $x = mb_strtolower(trim($s), 'UTF-8');
        if ($x === '') { return true; }
        $filters = $this->getLabelFilters();
        // Exact label words
        foreach (($filters['stopWords'] ?? []) as $w) {
            if ($x === $w) { return true; }
        }
        // Starts with these tokens
        foreach (($filters['prefixes'] ?? []) as $p) {
            if ($p !== '' && str_starts_with($x, $p)) { return true; }
        }
        // Contains obvious non-station keywords
        foreach (($filters['contains'] ?? []) as $c) {
            if ($c !== '' && str_contains($x, $c)) { return true; }
        }
        return false;
    }

    /** Load label filters from config/ticket_labels.json, with fallback defaults. */
    private function getLabelFilters(): array
    {
        $path = defined('CONFIG') ? (rtrim((string)CONFIG, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ticket_labels.json') : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ticket_labels.json');
        $mtime = is_file($path) ? @filemtime($path) : null;
        if ($mtime && (self::$labelFilters === null || self::$labelFiltersMtime !== $mtime)) {
            try {
                $raw = (string)@file_get_contents($path);
                $json = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
                $filters = [
                    'stopWords' => array_map(fn($v) => mb_strtolower(trim((string)$v), 'UTF-8'), (array)($json['stopWords'] ?? [])),
                    'prefixes' => array_map(fn($v) => mb_strtolower(trim((string)$v), 'UTF-8'), (array)($json['prefixes'] ?? [])),
                    'contains' => array_map(fn($v) => mb_strtolower(trim((string)$v), 'UTF-8'), (array)($json['contains'] ?? [])),
                ];
                self::$labelFilters = $filters;
                self::$labelFiltersMtime = (int)$mtime;
                return $filters;
            } catch (\Throwable $e) {
                // fall through to defaults
            }
        }
        if (self::$labelFilters !== null) { return self::$labelFilters; }
        // Defaults (mirror prior hardcoded lists)
        self::$labelFilters = [
            'stopWords' => [
                'gültigkeit','gültig','gültigkeitsbereich','gültigkeitszeitraum','gültig ab','gültig bis',
                'via','zugbindung','einfach','einfach fahrt','einzeln','reservierung','hinweise','reiseverbindung',
                'halt','datum','zeit','gleis','produkte','reservierung / hinweise','hinweis','kunde','kundenservice'
            ],
            'prefixes' => ['gültig','via ','zugbindung','reservierung','hinweis','ihre reiseverbindung','einfach fahrt'],
            'contains' => ['reservierung','auftragsnummer','kunde','kundennr','rechnung','preis','betrag','gültigkeit (00:00)'],
        ];
        self::$labelFiltersMtime = 0;
        return self::$labelFilters;
    }

    /** Extract ISO dates from text (e.g., 18.06.2024 or 18.06.). Returns array of YYYY-MM-DD strings. */
    public function extractDates(string $text): array
    {
        $out = [];
        if (preg_match_all('/\b(\d{1,2})[.\/\-](\d{1,2})(?:[.\/\-](\d{2,4}))?\b/u', $text, $all, PREG_SET_ORDER)) {
            $year = (int)date('Y');
            foreach ($all as $m) {
                $d = isset($m[1]) ? (int)$m[1] : 0;
                $mo = isset($m[2]) ? (int)$m[2] : 0;
                $y = (isset($m[3]) && $m[3] !== '') ? (int)$m[3] : $year;
                if ($y < 100) { $y += 2000; }
                if ($d>=1 && $d<=31 && $mo>=1 && $mo<=12) {
                    $out[] = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                }
            }
            // Dedup preserve order
            $out = array_values(array_unique($out));
        }
        return $out;
    }

    /** Attempt barcode read via ZXing CLI. Returns ['format'=>string|null,'payload'=>string|null]. */
    public function parseBarcode(string $imagePath): array
    {
        $res = ['format' => null, 'payload' => null];
        if (!is_file($imagePath)) { return $res; }
        $jar = getenv('ZXING_CLI_JAR') ?: (getenv('ZXING_CLI') ?: '');
        if (!$jar || !is_file($jar)) {
            // Try tools/zxing-cli.jar relative to project root
            $root = dirname(__DIR__, 2);
            $try = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'zxing-cli.jar';
            if (is_file($try)) { $jar = $try; }
        }
        if (!$jar || !is_file($jar)) { return $res; }
        $java = getenv('JAVA_BIN') ?: 'java';
        $cmd = escapeshellarg((string)$java) . ' -jar ' . escapeshellarg((string)$jar) . ' --multi --pure ' . escapeshellarg((string)$imagePath);
        $out = @shell_exec($cmd . ' 2>&1');
        if (!is_string($out) || trim($out) === '') { return $res; }
        $payload = trim($out);
        $res['payload'] = $payload;
        // Try to infer format keyword if present
        if (preg_match('/\b(AZTEC|QR_CODE|PDF_417|DATA_MATRIX)\b/i', $payload, $m)) { $res['format'] = strtoupper($m[1]); }
        return $res;
    }

    /** Extract PNR/order identifiers from text payload (OCR or barcode). */
    public function extractIdentifiers(string $text): array
    {
        $out = [];
        $t = mb_strtolower($text, 'UTF-8');
        $orig = $text;
        // Prefer explicit labels first (e.g., SNCF Dossier)
        if (preg_match('/\b(?:dossier|r[ée]f[ée]rence\s*dossier|pnr)\b\s*[:#]?\s*([a-z0-9]{6,8})\b/u', $t, $m)) {
            $out['pnr'] = strtoupper($m[1]);
        } else {
            // Fallback: 6–9 char mixed tokens that include at least one letter and one digit (avoid words like 'biljett')
            if (preg_match('/\b(?=[a-z0-9]{6,9}\b)(?=[a-z0-9]*[a-z])[a-z0-9]*\d[a-z0-9]*\b/u', $t, $m)) {
                $out['pnr'] = strtoupper($m[0]);
            } else {
                // As a last resort, scan original text for ALLCAPS 6–8 tokens (letters only) often used as PNRs
                if (preg_match('/\b([A-Z]{6,8})\b/u', $orig, $mo)) {
                    $out['pnr'] = $mo[1];
                }
            }
        }
        // DB Auftragsnummer (12+ digits)
        if (preg_match('/auftragsnummer\s*[:#]?\s*(\d{6,})/u', $t, $m)) {
            $out['order_no'] = $m[1];
        }
        return $out;
    }

    /**
     * Extract passenger data from ticket text: counts of adults/children and optional names.
     * Returns array of passenger objects: { name?, age_category: adult|child|unknown, is_claimant: false }
     *
     * @return array<int,array<string,mixed>>
     */
    public function extractPassengerData(string $text): array
    {
        $results = [];
        if (trim($text) === '') { return $results; }
        // Work on a lowercased copy for regex, but keep original for name capture when needed
        $lower = mb_strtolower($text, 'UTF-8');
        $lines = preg_split('/\r?\n/', $lower) ?: [];

        foreach ($lines as $i => $lineLower) {
            $lineLower = trim($lineLower);
            if ($lineLower === '') { continue; }
            // Adults (multiple languages): adult|erwachsener|erwachsene|voksen
            if (preg_match('/\b(\d{1,2})x?\s*(adult|erwachs(?:ener|ene)?|voksen)\b/u', $lineLower, $m)) {
                $n = max(1, (int)$m[1]);
                for ($k = 0; $k < $n; $k++) {
                    $results[] = ['age_category' => 'adult', 'is_claimant' => false];
                }
            }
            // Children: child|kinder|kind|barn
            if (preg_match('/\b(\d{1,2})x?\s*(child|kinder|kind|barn)\b/u', $lineLower, $m)) {
                $n = max(1, (int)$m[1]);
                for ($k = 0; $k < $n; $k++) {
                    $results[] = ['age_category' => 'child', 'is_claimant' => false];
                }
            }
            // Name lists: "Name: First Last" or lines starting with a plausible name label
            if (preg_match('/\bname\s*:?[\s]+([a-z \p{L}.'"\-]{3,})$/iu', $lineLower, $m)) {
                $name = trim($m[1]);
                if ($name !== '') {
                    $results[] = ['name' => $name, 'age_category' => 'unknown', 'is_claimant' => false];
                }
            }
        }

        return $results;
    }

    private function cleanStation(string $s): string
    {
        $s = trim($s);
        // Drop platform/track tokens and parentheses fragments
        $s = preg_replace('/\b(Gl\.|Gleis|Bahnsteig|Quai|Voie|Binario|Piattaforma|Platform)\b.*$/iu', '', (string)$s) ?? $s;
        $s = preg_replace('/\s*\([^\)]*\)\s*/u', ' ', (string)$s) ?? $s;
        $s = preg_replace('/\s+/', ' ', (string)$s) ?? $s;
        return trim($s, " \t\-·:");
    }
}

?>
