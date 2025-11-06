<?php
declare(strict_types=1);

namespace App\Service;

class TicketParseService
{
    /** Cached label filters from config/ticket_labels.json */
    private static ?array $labelFilters = null;
    private static ?int $labelFiltersMtime = null;
    /** Debug info from the last parse invocation */
    private static ?array $lastDebug = null;
    /**
     * Parse multiple train legs from an OCR text blob.
     * Returns an array of segments with keys: from, to, schedDep, schedArr, trainNo (optional).
     * @return array<int, array<string,string>>
     */
    public function parseSegmentsFromText(string $text): array
    {
        $segments = [];
        $debug = [
            'blockMatched' => false,
            'events' => [],
            'linesSample' => [],
        ];
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
        $debug['linesSample'] = array_slice($clean, 0, 30);

        // Dedicated DB itinerary block pass: scan the section between the itinerary header and usage notes
        // This helps when other content on the page introduces noise that breaks generic parsing.
        $events = [];
        if (count($segments) === 0) {
            if (preg_match('/Ihre\s+Reiseverbindung[\s\S]*?\n(.*?)(?:\n\s*(?:Wichtige\s+Nutzungshinweise|Wichtige\s+Nutzungs\s*hinweise)\b)/isu', $text, $blk)) {
                $blkLines = preg_split('/\r?\n/', (string)$blk[1]) ?: [];
                    $evtReBlk = '/\s*([A-Za-zÀ-ÖØ-öø-ÿ .\'\"\-]{3,}?)\s*(?:\s+(?:Gl\.?|Gleis|Bahnsteig|Voie|Quai)\s*\S+)?\s*(?:\d{1,2}[.\/\-]\d{1,2}(?:[.\/\-]\d{2,4})?)?\s*(ab|an|abf\.?|ank\.?|abfahrt|ankunft)\s*[: ,]*((?:[01]?\d|2[0-3])[:.h]?\s*\d{2}|\b\d{3,4}\b)\b.*?(?:\b(TGV|ICE|IC|EC|RJ|RE|RB|EN|NJ)\s*(\d{1,5}))?/iu';
                foreach ($blkLines as $ln) {
                    $l = trim(preg_replace('/\s+/', ' ', (string)$ln));
                    if ($l === '' || strlen($l) < 5) { continue; }
                    if (preg_match($evtReBlk, $l, $m)) {
                        $station = $this->cleanStation((string)($m[1] ?? ''));
                        $typRaw = strtolower((string)($m[2] ?? ''));
                        $typ = (str_starts_with($typRaw, 'ab') ? 'ab' : (str_starts_with($typRaw, 'an') ? 'an' : $typRaw));
                        $time = (string)($m[3] ?? '');
                        $time = preg_replace('/\s*/', '', $time);
                        $time = str_replace(['.', 'h'], ':', $time);
                        $prod = '';
                        if (!empty($m[4])) { $prod = trim((string)$m[4] . ' ' . (string)($m[5] ?? '')); }
                        if ($station !== '' && ($typ === 'ab' || $typ === 'an')) {
                            if (!$this->isNonStationLabel($station)) {
                                $events[] = ['station' => $station, 'type' => $typ, 'time' => $time, 'prod' => $prod];
                                $debug['events'][] = ['src' => 'block', 'station' => $station, 'type' => $typ, 'time' => $time, 'prod' => $prod, 'line' => $l];
                            }
                        }
                    }
                }
                // Pair up inside the block first
                if (!empty($events)) {
                    $debug['blockMatched'] = true;
                    for ($i=0; $i < count($events); $i++) {
                        if ($events[$i]['type'] !== 'ab') { continue; }
                        for ($j=$i+1; $j<count($events); $j++) {
                            if ($events[$j]['type'] === 'an') {
                                $from = $events[$i]['station'];
                                $to = $events[$j]['station'];
                                if ($from !== '' && $to !== '' && $from !== $to) {
                                    $segments[] = [
                                        'from' => $from,
                                        'to' => $to,
                                        'schedDep' => $events[$i]['time'],
                                        'schedArr' => $events[$j]['time'],
                                        'trainNo' => $events[$i]['prod'],
                                    ];
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

    // Simple arrow pattern: City HH:MM -> City HH:MM (tolerate OCR time variants like 9.01, 9 h 01, 0901)
    // Require minutes for hh:mm or h.mm variants; allow 3-4 digit compact times; do not allow single-digit hours alone
    $arrowTime = '(?:[01]?\d|2[0-3])[:.h]\s*\d{2}|\\b\d{3,4}\\b';
    // Use non-capturing groups to avoid shifting indices; capture exactly 4 groups: from, dep, to, arr
    $arrowRe = '/([A-Za-zÀ-ÖØ-öø-ÿ .\'\-]{3,})\s+(' . $arrowTime . ')\s*(?:[›→\-—–]|\-\>)\s*([A-Za-zÀ-ÖØ-öø-ÿ .\'\-]{3,})\s+(' . $arrowTime . ')/u';
        // Train pattern (loose)
    $trainRe = '/\b(TGV|ICE|IC|EC|RE|RJ)\s*(\d{2,5})\b|\bTrain\s*No\.?\s*(\d{2,5})\b|\b(?:Zug|Treno|Tog)\s*(\d{2,5})\b|\bIC[- ]?Lyntog\s*(\d{1,5})\b|\bArriva[- ]?tog\s*(\d{1,5})\b/i';

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

    // Pass 1b: DB itinerary event table ("Ihre Reiseverbindung ...") with rows like
        //   Verona Porta Nuova 18.06 ab 09:01 RJ 88 ...
        //   München Hbf Gl.5-10 18.06 an 14:27 ...
        //   München Hbf 18.06 ab 14:47 ICE 622 ...
        //   Düsseldorf Hbf 18.06 an 19:44 ...
        if (count($segments) === 0) {
            $events = [];
            // Support German ab/an (and Abf./Ank. abbreviations) and Danish afg/ank, plus generic dep/arr keywords
            // Also allow full words Abfahrt/Ankunft occasionally printed instead of abbreviations
            // Tolerate optional platform tokens (Gl./Gleis/Voie/Quai) and optional date before the ab/an token
                $evtRe = '/\s*([A-Za-zÀ-ÖØ-öø-ÿ .\'\"\-]{3,}?)\s*(?:\s+(?:Gl\.?|Gleis|Bahnsteig|Voie|Quai)\s*\S+)?\s*(?:\d{1,2}[.\/\-]\d{1,2}(?:[.\/\-]\d{2,4})?)?\s*(ab|an|abf\.?|ank\.?|abfahrt|ankunft|afg|ank|dep|arr)\s*[: ,]*((?:[01]?\d|2[0-3])[:.h]?\s*\d{2}|\b\d{3,4}\b)\b.*?(?:\b(TGV|ICE|IC|EC|RJ|RE|RB|EN|NJ)\s*(\d{1,5}))?/iu';
            foreach ($clean as $line) {
                if (preg_match($evtRe, $line, $m)) {
                    $station = $this->cleanStation((string)($m[1] ?? ''));
                    $typRaw = strtolower((string)($m[2] ?? ''));
                    // Normalize various tokens to 'ab' or 'an'
                    if ($typRaw === 'afg' || $typRaw === 'abf' || str_starts_with($typRaw, 'abfahrt')) { $typ = 'ab'; }
                    elseif ($typRaw === 'ank' || str_starts_with($typRaw, 'ank') || str_starts_with($typRaw, 'ankunft')) { $typ = 'an'; }
                    elseif ($typRaw === 'dep') { $typ = 'ab'; }
                    elseif ($typRaw === 'arr') { $typ = 'an'; }
                    else { $typ = $typRaw; }
                    $time = (string)($m[3] ?? '');
                    // Normalize time variants like 9.01 or 9 h 01
                    $time = preg_replace('/\s*/', '', $time);
                    $time = str_replace(['.', 'h'], ':', $time);
                    $prod = '';
                    if (!empty($m[4])) { $prod = trim((string)$m[4] . ' ' . (string)($m[5] ?? '')); }
                    if ($station !== '' && ($typ === 'ab' || $typ === 'an')) {
                        if (!$this->isNonStationLabel($station)) {
                            $events[] = ['station' => $station, 'type' => $typ, 'time' => $time, 'prod' => $prod];
                            $debug['events'][] = ['src' => 'line', 'station' => $station, 'type' => $typ, 'time' => $time, 'prod' => $prod, 'line' => $line];
                        }
                    }
                }
            }
            // If still nothing, try a multi-line DB capture: station line followed within next 1–3 lines by an/ab + time
            if (empty($events)) {
                for ($i = 0; $i < count($clean); $i++) {
                    $line = $clean[$i];
                    // Station candidate: mostly letters, allow Hbf and platforms, avoid lines with obvious digits first
                    if (!preg_match('/\p{L}/u', $line)) { continue; }
                    $station = $this->cleanStation($line);
                    if ($station === '' || mb_strlen($station,'UTF-8') < 3) { continue; }
                    if ($this->isNonStationLabel($station)) { continue; }
                    // Look ahead up to 3 lines for ab/an + time
                    $found = false;
                    for ($k = 1; $k <= 3 && ($i+$k) < count($clean); $k++) {
                        $ln2 = $clean[$i+$k];
                        if (preg_match('/\b(an|ank\.?|ankunft)\b\s*[: ]+((?:[01]?\d|2[0-3])[:.h]?\s*\d{2}|\b\d{3,4}\b)/iu', $ln2, $mm)) {
                            $time = str_replace(['.', 'h', ' '], [':', ':', ''], (string)$mm[2]);
                            $events[] = ['station' => $station, 'type' => 'an', 'time' => $time, 'prod' => ''];
                            $debug['events'][] = ['src' => 'multiline', 'station' => $station, 'type' => 'an', 'time' => $time, 'prod' => '', 'line' => $ln2];
                            $found = true;
                        }
                        if (preg_match('/\b(ab|abf\.?|abfahrt|afg|dep)\b\s*[: ]+((?:[01]?\d|2[0-3])[:.h]?\s*\d{2}|\b\d{3,4}\b)/iu', $ln2, $mm2)) {
                            $time = str_replace(['.', 'h', ' '], [':', ':', ''], (string)$mm2[2]);
                            $events[] = ['station' => $station, 'type' => 'ab', 'time' => $time, 'prod' => ''];
                            $debug['events'][] = ['src' => 'multiline', 'station' => $station, 'type' => 'ab', 'time' => $time, 'prod' => '', 'line' => $ln2];
                            $found = true;
                        }
                        if ($found) { break; }
                    }
                }
            }
            // DSB two-line layout: station line followed by a separate "Afg:" or "Ank:" line with the time.
            if (empty($events)) {
                $currentStation = '';
                foreach ($clean as $line) {
                    // Station candidate lines (avoid obvious labels like Start/Stop/Fra/Til words only)
                    if (preg_match('/^([A-Za-zÀ-ÖØ-öø-ÿ .\'\"\-]{3,})$/u', $line)) {
                        $cand = $this->cleanStation($line);
                        if ($cand !== '' && !$this->isNonStationLabel($cand) && mb_strlen($cand,'UTF-8') >= 3) {
                            // Ignore lines that are just column labels like "Start/Stop" or "Detaljer"
                            $low = mb_strtolower($cand, 'UTF-8');
                            if (!in_array($low, ['start/stop','detaljer','fra:','til:'], true)) {
                                $currentStation = $cand;
                            }
                        }
                    }
                    // Look for Afg/Ank tokens near a time
                    if ($currentStation !== '' && preg_match('/\b(afg|ank|ab|an|dep|arr)\b\s*:?\s*((?:[01]?\d|2[0-3])[:.h]?\s*\d{2})/iu', $line, $mm)) {
                        $typRaw = strtolower($mm[1]);
                        $typ = ($typRaw === 'afg' || $typRaw === 'ab' || $typRaw === 'dep') ? 'ab' : 'an';
                        $time = preg_replace('/\s*/', '', (string)$mm[2]);
                        $time = str_replace(['.', 'h'], ':', $time);
                        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time)) {
                            $events[] = ['station' => $currentStation, 'type' => $typ, 'time' => $time, 'prod' => ''];
                        }
                    }
                }
            }
            // Pair departures with the next arrival to form segments
            if (!empty($events)) {
                for ($i=0; $i < count($events); $i++) {
                    if ($events[$i]['type'] !== 'ab') { continue; }
                    for ($j=$i+1; $j<count($events); $j++) {
                        if ($events[$j]['type'] === 'an') {
                            $from = $events[$i]['station'];
                            $to = $events[$j]['station'];
                            if ($from !== '' && $to !== '' && $from !== $to) {
                                $segments[] = [
                                    'from' => $from,
                                    'to' => $to,
                                    'schedDep' => $events[$i]['time'],
                                    'schedArr' => $events[$j]['time'],
                                    'trainNo' => $events[$i]['prod'],
                                ];
                            }
                            break; // next departure
                        }
                    }
                }
            }
        }

        // Fallback: parse DB-style itinerary table
        if (count($segments) === 0) {
            $rows = [];
            $timeRe = '/(?:\b|\s)((?:[01]?\d|2[0-3]):[0-5]\d)(?:\b|\s)/u';
            $prodRe = '/\b(TGV|ICE|IC|EC|RJ|RE|RB|EN|NJ)\s*([0-9]{1,5})\b/u';
            foreach ($clean as $line) {
                // Extract station name up to either ' Gl.'/'Gleis' or a date/time token
                $station = '';
                if (preg_match('/^([^\d]*?)(?:\s+Gl\.?|\s+Gleis|\s+(?:\d{1,2}[.\/\-]\d{1,2}(?:[.\/\-]\d{2,4})?)|\s+(?:[01]?\d|2[0-3])[:.h]?\s*\d{2})/u', $line, $sm)) {
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
                // Normalize OCR variants
                $time = str_replace(['.', 'h', ' '], [':', ':', ''], $time);
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

        // Final fallback: derive connection points from "via" or change-at phrases (DSB/simple receipts)
        if (count($segments) === 0) {
            $viaStations = [];
            // Collect VIA lists line-by-line; tolerate arrows and noisy markers like <123>
            foreach ($clean as $ln) {
                if (!preg_match('/\bvia\b/iu', $ln)) { continue; }
                if (preg_match('/\bvia\b\s*[:\-]?\s*(.+)$/iu', $ln, $mm)) {
                    $chunk = (string)$mm[1];
                    // Remove angle-bracket numeric markers often present in DB print (e.g., <181>)
                    $chunk = preg_replace('/<[^>]*>/', ' ', $chunk) ?? $chunk;
                    // Split on arrows, commas, semicolons, bullets, slashes, or long spaces
                    $parts = preg_split('/[>»›→,;•·—–]|\s{2,}|\//u', (string)$chunk) ?: [];
                    foreach ($parts as $p) {
                        $p = trim((string)$p);
                        if ($p === '' || !preg_match('/\p{L}/u', $p)) { continue; }
                        $c = $this->cleanStation($p);
                        if ($c !== '' && mb_strlen($c,'UTF-8') >= 3 && !$this->isNonStationLabel($c)) { $viaStations[] = $c; }
                    }
                }
            }
            // Collect Danish/German/English change-at patterns (skift/omstigning/umstieg/change at)
            $changeRe = '/\b(' .
                'skift\s+(?:i|ved|på)|' .            // Danish: skift i/ved/på
                'omstigning\s+(?:i|ved|på)|' .       // Danish: omstigning i/ved/på
                'umstieg\s+in|' .                    // German: Umstieg in
                'umsteigen\s+in|' .                  // German: Umsteigen in
                'wechsel\s+in|' .                    // German: Wechsel in
                'change\s+at' .                      // English: Change at
            ')\s+([A-Za-zÀ-ÖØ-öø-ÿ .\-]{3,})\b/iu';
            if (preg_match_all($changeRe, $t, $all2, PREG_SET_ORDER)) {
                foreach ($all2 as $m) {
                    $c = $this->cleanStation((string)($m[2] ?? ''));
                    if ($c !== '' && !$this->isNonStationLabel($c)) { $viaStations[] = $c; }
                }
            }
            // Also support Danish "via A og B" lists
            if (preg_match_all('/\bvia\s+([A-Za-zÀ-ÖØ-öø-ÿ .\-]+?)\b(?:og|and)\s+([A-Za-zÀ-ÖØ-öø-ÿ .\-]{3,})/iu', $t, $all3, PREG_SET_ORDER)) {
                foreach ($all3 as $m) {
                    $c1 = $this->cleanStation((string)($m[1] ?? ''));
                    $c2 = $this->cleanStation((string)($m[2] ?? ''));
                    foreach ([$c1,$c2] as $c) { if ($c !== '' && !$this->isNonStationLabel($c)) { $viaStations[] = $c; } }
                }
            }
            $viaStations = array_values(array_unique(array_filter($viaStations)));

            // Attempt to detect explicit origin/destination labels (Fra:/Til: or From:/To: or Von:/Nach:)
            $origin = '';$dest = '';
            if (preg_match('/\b(fra|from|von)\b\s*:?\s*([A-Za-zÀ-ÖØ-öø-ÿ .\-]{3,})/iu', $t, $mF)) {
                $origin = $this->cleanStation((string)$mF[2]);
            }
            if (preg_match('/\b(til|to|nach)\b\s*:?\s*([A-Za-zÀ-ÖØ-öø-ÿ .\-]{3,})/iu', $t, $mT)) {
                $dest = $this->cleanStation((string)$mT[2]);
            }
            // DSB/DB: If we see "Einfache Fahrt" line, take next two station-like lines as origin/destination
            if ($origin === '' || $dest === '') {
                for ($i = 0; $i < count($clean); $i++) {
                    if (preg_match('/\b(einfache\s+fahrt|one\-?way|enkelt\s*rejse)\b/iu', $clean[$i])) {
                        $found = [];
                        for ($k = 1; $k <= 6 && ($i+$k) < count($clean); $k++) {
                            $cand = $this->cleanStation($clean[$i+$k]);
                            if ($cand !== '' && mb_strlen($cand,'UTF-8') >= 3 && !$this->isNonStationLabel($cand)) {
                                $found[] = $cand;
                                if (count($found) >= 2) break;
                            }
                        }
                        if (count($found) >= 2) { $origin = $origin ?: $found[0]; $dest = $dest ?: $found[1]; }
                        break;
                    }
                }
            }
            // As a loose fallback, try to infer the first and last station names from the top of the text when labels are absent
            if ($origin === '' || $dest === '') {
                $cands = [];
                foreach ($clean as $ln) {
                    // Likely station line: letters and spaces (min length), avoid lines with obvious prices or order numbers
                    if (preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ .\-]{3,}$/u', $ln)) {
                        $cand = $this->cleanStation($ln);
                        if ($cand !== '' && !$this->isNonStationLabel($cand)) { $cands[] = $cand; }
                    }
                    if (count($cands) >= 10) { break; }
                }
                if ($origin === '' && !empty($cands)) { $origin = $cands[0]; }
                if ($dest === '' && count($cands) >= 2) { $dest = $cands[count($cands)-1]; }
            }

            // Build a chain if we have at least origin and destination or any via stations
            $chain = [];
            if ($origin !== '') { $chain[] = $origin; }
            foreach ($viaStations as $v) { if ($v !== '' && ($origin === '' || $v !== $origin)) { $chain[] = $v; } }
            if ($dest !== '' && ($origin === '' || $dest !== $origin)) { $chain[] = $dest; }

            if (count($chain) >= 2) {
                for ($i = 0; $i < count($chain) - 1; $i++) {
                    $from = $chain[$i];
                    $to = $chain[$i+1];
                    if ($from !== '' && $to !== '' && $from !== $to) {
                        $segments[] = [ 'from' => $from, 'to' => $to, 'schedDep' => '', 'schedArr' => '', 'trainNo' => '' ];
                        $debug['events'][] = ['src' => 'via-fallback', 'station' => $from . '→' . $to, 'type' => 'chain', 'time' => '', 'prod' => '', 'line' => ''];
                    }
                }
            }
        }

        // If we have overall date(s), attach them to segments
        $dates = $this->extractDates($t);
        if (!empty($dates)) {
            $date0 = $dates[0];
            foreach ($segments as &$s) { if (empty($s['depDate'])) { $s['depDate'] = $date0; } if (empty($s['arrDate'])) { $s['arrDate'] = $date0; } }
            unset($s);
        }

        // Filter out obvious non-station artifacts and invalid legs, then deduplicate
        $cleanSegs = [];
        foreach ($segments as $s) {
            $from = trim((string)($s['from'] ?? ''));
            $to = trim((string)($s['to'] ?? ''));
            $dep = trim((string)($s['schedDep'] ?? ''));
            $arr = trim((string)($s['schedArr'] ?? ''));
            if ($from === '' || $to === '' || $from === $to) { continue; }
            // Skip if either side looks like a label rather than a station
            $lblRe = '/^(afg|ank|afgang|ankomst|fra:|til:|start\/stop|detaljer)\b/i';
            if (preg_match($lblRe, $from) || preg_match($lblRe, $to)) { continue; }
            if ($this->isNonStationLabel($from) || $this->isNonStationLabel($to)) { continue; }
            // If we have times, keep; else allow station-only legs (from VIA fallback)
            $cleanSegs[] = [ 'from'=>$from, 'to'=>$to, 'schedDep'=>$dep, 'schedArr'=>$arr, 'trainNo'=>(string)($s['trainNo'] ?? '') , 'depDate'=>(string)($s['depDate'] ?? ''), 'arrDate'=>(string)($s['arrDate'] ?? '') ];
        }
        $uniq = [];
        $out = [];
        foreach ($cleanSegs as $s) {
            $key = strtoupper(($s['from'] ?? '') . '|' . ($s['to'] ?? '') . '|' . ($s['schedDep'] ?? '') . '|' . ($s['schedArr'] ?? ''));
            if (isset($uniq[$key])) { continue; }
            $uniq[$key] = true;
            $out[] = $s;
        }
        self::$lastDebug = $debug;
        return $out;
    }

    /** Retrieve debug info for the last parsing run. */
    public static function getLastDebug(): array
    {
        return is_array(self::$lastDebug) ? self::$lastDebug : [];
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
                'halt','datum','zeit','gleis','produkte','reservierung / hinweise','hinweis','kunde','kundenservice',
                // Strengthen German boilerplate blockers frequently misread as stations
                'fahrgastrechte','einlösebedingungen','einloesebedingungen','agb','allgemeine','beförderungsbedingungen','beforderungsbedingungen','servicecenter','kundendialog','website','bahn.de','db.de'
            ],
            'prefixes' => ['gültig','via ','zugbindung','reservierung','hinweis','ihre reiseverbindung','einfach fahrt','bitte beachten','gilt nur'],
            'contains' => ['reservierung','auftragsnummer','kunde','kundennr','rechnung','preis','betrag','gültigkeit (00:00)','fahrgastrechte','agb','beförderungsbedingungen','beforderungsbedingungen'],
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
                    $cand = $mo[1];
                    // Avoid obvious non-PNR words commonly present on tickets
                    $black = ['DEUTSCHE','BAHN','TICKET','REISE','SERVICE','KUNDEN','KUNDE','RESERV','RESERVIER','WWW','BEHINDERTE','DEUTSCHEBAHN'];
                    if (!in_array($cand, $black, true)) {
                        $out['pnr'] = $cand;
                    }
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
            // Allow letters, spaces, dots, quotes and hyphen inside the captured name
            if (preg_match('/\bname\s*:?[\s]+([a-z \p{L}.\'"\-]{3,})$/iu', $lineLower, $m)) {
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
