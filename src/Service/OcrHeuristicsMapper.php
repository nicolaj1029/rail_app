<?php
declare(strict_types=1);

namespace App\Service;

class OcrHeuristicsMapper
{
    /**
     * Map a free-form OCR text blob into Art.9(1) auto hooks and logs.
     * @return array{auto: array<string, array{value:mixed,source:string}>, logs: string[]}
     */
    public function mapText(string $text): array
    {
        $auto = [];
        $logs = [];
        if ($text === '') { return ['auto' => $auto, 'logs' => $logs]; }

        // Normalize common OCR quirks: NBSP, fancy dashes, multiple spaces
        $repls = [
            "\xC2\xA0" => ' ', // NBSP
        ];
        $text = strtr($text, $repls);
        $text = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $text) ?? $text; // hyphen variations and minus to '-'
        $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

    $yes = fn(string $k) => ['value' => 'Ja', 'source' => 'ocr'];
    // Optional multilingual labels provider (JSON-driven); safe to ignore failures
    $lbl = null;
    try { $lbl = new LabelsProvider(); } catch (\Throwable $e) { $lbl = null; }
    // Helpers
        $isCodeLike = function(string $s): bool {
            $s = trim($s);
            if ($s === '') { return true; }
            // Remove common wrappers
            $sNoParen = preg_replace('/\s*[()\[\]]\s*/u', '', $s) ?? $s;
            $sFlat = preg_replace('/\s+/u', '', $sNoParen) ?? $sNoParen;
            // Reject if contains any digit (station names rarely contain digits on tickets)
            if (preg_match('/\d/u', $sFlat)) { return true; }
            // Pure short all-caps tokens (e.g., CDG, LY, BCN) look like codes
            if (preg_match('/^[A-Z]{2,5}$/u', $sFlat)) { return true; }
            // Pure numbers or letter+digits code patterns
            if (preg_match('/^(?:[A-Z]{1,3}\d{2,}[A-Z]?|\d{2,})$/u', $sFlat)) { return true; }
            return false;
        };
        $letterLen = function(string $s): int {
            $onlyLetters = preg_replace('/[^\p{L}]/u', '', $s) ?? '';
            return mb_strlen($onlyLetters);
        };
        $normDate = function(string $d): string {
            $d = trim($d);
            // YYYY-MM-DD
            if (preg_match('/^(\d{4})[-](\d{2})[-](\d{2})$/', $d, $m)) { return "$m[1]-$m[2]-$m[3]"; }
            // DD/MM/YYYY or DD.MM.YYYY
            if (preg_match('/^(\d{2})[\/.](\d{2})[\/.](\d{4})$/', $d, $m)) { return "$m[3]-$m[2]-$m[1]"; }
            // D/M or DD/M or D/MM without year -> assume current year
            if (preg_match('/^(\d{1,2})[\/.](\d{1,2})$/', $d, $m)) {
                $yy = (int)date('Y');
                return sprintf('%04d-%02d-%02d', $yy, (int)$m[2], (int)$m[1]);
            }
            // DD/MM/YY or DD.MM.YY → assume 20YY
            if (preg_match('/^(\d{2})[\/.](\d{2})[\/.](\d{2})$/', $d, $m)) { $yy = (int)$m[3]; $yy += ($yy < 70 ? 2000 : 1900); return sprintf('%04d-%02d-%02d', $yy, (int)$m[2], (int)$m[1]); }
            return $d;
        };
        $normTime = function(string $t): string {
            $t = trim($t);
            // Normalize comma to colon for locales like Italian (e.g., 11,17)
            $t = str_replace(',', ':', $t);
            // h, :, . with optional minutes; also allow hours-only
            if (preg_match('/^(\d{1,2})(?:\s*[:.h]\s*(\d{1,2}))?$/iu', $t, $m)) {
                $hh = (int)$m[1];
                $mm = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
                if ($hh > 23) { return $t; }
                if ($mm > 59) { $mm = 0; }
                return sprintf('%02d:%02d', $hh, $mm);
            }
            // Compact HHMM or HMM (e.g., 0920, 920)
            if (preg_match('/^(\d{3,4})$/u', $t, $m)) {
                $digits = $m[1];
                $hh = (int)substr($digits, 0, -2);
                $mm = (int)substr($digits, -2);
                if ($hh <= 23 && $mm <= 59) { return sprintf('%02d:%02d', $hh, $mm); }
            }
            // 12:34 or 12.34 (fallback precise)
            if (preg_match('/^(\d{1,2})[:.](\d{2})/u', $t, $m)) { return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]); }
            return $t;
        };
    // Prefer 4-digit year before 2-digit year to avoid partial matches (e.g., 2025 -> 20)
    $dateToken = '(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}[-\/.][0-9]{1,2}(?:[-\/.](?:[0-9]{4}|[0-9]{2}))?)';

        // TRIN 3.2 & 3.3: Scheduled and actual journey fields (multi-language, multi-format)
        // Departure date
    $dateLabels = '(?:Dep\.?\s*date|Afgangsdato|Departure\s*date|Avgångsdatum|Abfahrtsdatum|Date\s*de\s*départ|Datum|Data)';
        if ($lbl) {
            $dateLabels = '(?:' . $lbl->group('date') . '|Afgangsdato|Departure\s*date|Avgångsdatum|Abfahrtsdatum|Date\s*de\s*départ|Datum|Data)';
        }
        if (preg_match('/' . $dateLabels . '[:\s]*([0-9]{4}-[0-9]{2}-[0-9]{2}|\d{2}[\/.]\d{2}[\/.](?:\d{4}|\d{2}))/iu', $text, $m)) {
            $auto['dep_date'] = ['value' => $normDate($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: dep_date=' . $auto['dep_date']['value'];
        } elseif (preg_match('/\bDate\b[:\s]*([0-9]{2}[\/.]\d{2}[\/.](?:\d{4}|\d{2}))(?:\s+((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)|\b\d{3,4}\b))?/iu', $text, $m)) {
            $auto['dep_date'] = ['value' => $normDate($m[1]), 'source' => 'ocr'];
            if (!empty($m[2]) && !isset($auto['dep_time'])) {
                $auto['dep_time'] = ['value' => $normTime($m[2]), 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_time (from Date)=' . $auto['dep_time']['value'];
            }
        } elseif (preg_match('/\bData\b[:\s]*([0-9]{2}[\/.]\d{2}[\/.](?:\d{4}|\d{2}))(?:\s+((?:[01]?\d|2[0-3])(?:[:.h,]\s*[0-5]\d)|\b\d{3,4}\b))?/iu', $text, $m)) {
            // Italian label "Data" with optional time after it
            $auto['dep_date'] = ['value' => $normDate($m[1]), 'source' => 'ocr'];
            if (!empty($m[2]) && !isset($auto['dep_time'])) {
                $auto['dep_time'] = ['value' => $normTime($m[2]), 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_time (from Data)=' . $auto['dep_time']['value'];
            }
        }

        // Line-based fallback: capture time from a Date: line if present
        if (!isset($auto['dep_time'])) {
            $lines = preg_split('/\R/u', $text) ?: [];
            foreach ($lines as $ln) {
                if (preg_match('/^\s*Date\b[:\s]*([0-9]{1,2}[-\/.][0-9]{1,2}[-\/.](?:[0-9]{4}|[0-9]{2}))(?:\s+((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)|\b\d{3,4}\b))?/iu', $ln, $mm)) {
                    if (!empty($mm[2])) {
                        $auto['dep_time'] = ['value' => $normTime($mm[2]), 'source' => 'ocr'];
                        $logs[] = 'AUTO: dep_time (Date line)=' . $auto['dep_time']['value'];
                        break;
                    }
                }
                if (!isset($auto['dep_time']) && preg_match('/^\s*Data\b[:\s]*([0-9]{1,2}[-\/.][0-9]{1,2}[-\/.](?:[0-9]{4}|[0-9]{2}))(?:\s+((?:[01]?\d|2[0-3])(?:[:.h,]\s*[0-5]\d)|\b\d{3,4}\b))?/iu', $ln, $mmIta)) {
                    if (!empty($mmIta[2])) {
                        $auto['dep_time'] = ['value' => $normTime($mmIta[2]), 'source' => 'ocr'];
                        $logs[] = 'AUTO: dep_time (Data line)=' . $auto['dep_time']['value'];
                        break;
                    }
                }
            }
        }

        // Departure time
    $timeLabelsDep = '(?:Dep\.?\s*time|Afgangstid|Afgang|Departure\s*time|Avgångstid|Abfahrtszeit|Heure\s*de\s*d[ée]part|Abfahrt|Kl\.?|Kl\s*|Départ|Depart|Partenza|Part\.|Scheduled\s*Dep(?:arture)?)';
        if ($lbl) {
            $timeLabelsDep = '(?:' . $lbl->group('dep_time') . '|Afgangstid|Departure\s*time|Abfahrtszeit|Kl\.?|Kl\s*|Afgang|Avgång|Départ|Depart|Partenza|Part\.)';
        }
        if (preg_match('/' . $timeLabelsDep . '[:\s]*(?:(' . $dateToken . ')\s*(?:à|at|um|kl\.?|,)?\s*)?(' . '(?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b' . ')(?:\s*(?:Uhr|h))?/iu', $text, $m)) {
            // m[1] may be full date match if present
            $maybeDate = $m[1] ?? '';
            $timeVal = $m[2] ?? '';
            if ($maybeDate && !isset($auto['dep_date'])) {
                $auto['dep_date'] = ['value' => $normDate($maybeDate), 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_date (from label)=' . $auto['dep_date']['value'];
            }
            $auto['dep_time'] = ['value' => $normTime($timeVal), 'source' => 'ocr'];
            $logs[] = 'AUTO: dep_time=' . $auto['dep_time']['value'];
        }

        // Departure station
        $stationDepLabels = '(?:Dep\.?\s*station|Afgangsstation|Departure\s*station|Avgångsstation|Abfahrtsbahnhof|Gare\s*de\s*d[ée]part|Partenza|\bDe\b|\bDa\b|\bVon\b|\bFra\b|\bFrån\b|\bFrom\b)';
        if ($lbl) {
            // Avoid substring matches by requiring word boundaries around the dynamic group
            $stationDepLabels = '(?:\b(?:' . $lbl->group('dep_station') . ')\b|\bDe\b|\bDa\b|\bVon\b|\bFra\b|\bFrån\b|\bFrom\b)';
        }
        if (preg_match('/' . $stationDepLabels . '[:\s]*([\p{L}0-9\- .()]+)(?=$|\r|\n|\s+(?:À|A|Til|Till|Nach)\b)/iu', $text, $m)) {
            $candidate = trim($m[1]);
            // strip trailing parenthetical fragments like (08:04)
            $candidate = preg_replace('/\s*\([^)]*\)\s*$/u', '', $candidate);
            // must contain at least one letter and not be a code-like token
            $isValidStation = (bool)preg_match('/\p{L}/u', $candidate) && !$isCodeLike($candidate) && $letterLen($candidate) >= 3;
            if ($isValidStation) {
                $auto['dep_station'] = ['value' => $candidate, 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_station=' . $candidate;
            } else {
                $logs[] = 'SKIP: dep_station candidate too short/code-like => ' . $candidate;
            }
        }
        // Cross-line fallback for departure station label on previous line, value on next line
        if (empty($auto['dep_station']['value'])) {
            $depCrossLbl = $lbl ? '(?:' . $lbl->group('dep_station') . '|De|Da|Von|Fra|Från|From)' : '(?:Dep\.?\s*station|Departure\s*station|Afgangsstation|Avgångsstation|Abfahrtsbahnhof|Gare\s*de\s*d[ée]part|Partenza|De|Da|Von|Fra|Från|From)';
            if (preg_match('/\b' . $depCrossLbl . '\b\s*:?\s*(?:\r?\n)+\s*([^\r\n]+)/ium', $text, $mc)) {
                $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mc[1]));
                if (preg_match('/\p{L}/u', $cand) && !$isCodeLike($cand) && $letterLen($cand) >= 3) {
                    $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr'];
                    $logs[] = 'AUTO: dep_station (cross-line)=' . $cand;
                }
            }
        }

        // Arrival station
        $stationArrLabels = '(?:Arr\.?\s*station|Ankomststation|Destination\s*station|Ankunftsbahnhof|Gare\s*d\'arriv[ée]e|Destination|Arrivo|\bÀ\b|\bA\b|\bTil\b|\bTill\b|\bNach\b|\bVers\b|\bTo\b)';
        if ($lbl) {
            // Avoid substring matches by requiring word boundaries around the dynamic group
            $stationArrLabels = '(?:\b(?:' . $lbl->group('arr_station') . ')\b|\bÀ\b|\bA\b|\bTil\b|\bTill\b|\bNach\b|\bVers\b|\bTo\b)';
        }
        if (preg_match('/' . $stationArrLabels . '[:\s]*([\p{L}0-9\- .()]+?)(?=$|\r|\n|\s+(?:Partenza|Part\.|Départ|Depart|Afgang|Departure|Arrivo|Arr\.|Arrival|Ankunft|Ankomst|Til|Till|Nach|Vers|To)\b)/iu', $text, $m)) {
            $candidate = trim($m[1]);
            $candidate = preg_replace('/\s*\([^)]*\)\s*$/u', '', $candidate);
            $isValidStation = (bool)preg_match('/\p{L}/u', $candidate) && !$isCodeLike($candidate) && $letterLen($candidate) >= 3;
            if ($isValidStation) {
                $auto['arr_station'] = ['value' => $candidate, 'source' => 'ocr'];
                $logs[] = 'AUTO: arr_station=' . $candidate;
            } else {
                $logs[] = 'SKIP: arr_station candidate too short/code-like => ' . $candidate;
            }
        }
        // Cross-line fallback for arrival/destination label on previous line, value on next line
        if (empty($auto['arr_station']['value'])) {
            $arrCrossLbl = $lbl ? '(?:' . $lbl->group('arr_station') . '|À|A|Til|Till|Nach|Vers|To)' : '(?:Arr\.?\s*station|Destination\s*station|Ankomststation|Ankunftsbahnhof|Gare\s*d\'arriv[ée]e|Destination|Arrivo|À|A|Til|Till|Nach|Vers|To)';
            if (preg_match('/\b' . $arrCrossLbl . '\b\s*:?\s*(?:\r?\n)+\s*([^\r\n]+)/ium', $text, $mc)) {
                $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mc[1]));
                if (preg_match('/\p{L}/u', $cand) && !$isCodeLike($cand) && $letterLen($cand) >= 3) {
                    $auto['arr_station'] = ['value' => $cand, 'source' => 'ocr'];
                    $logs[] = 'AUTO: arr_station (cross-line)=' . $cand;
                }
            }
        }

        // If either station was skipped as code-like, try scanning labelled lines or arrow lines for readable names
        if ((empty($auto['dep_station']['value']) || empty($auto['arr_station']['value']))) {
            $lines = preg_split('/\R/u', $text) ?: [];
            $timeReLbl = '/((?:[01]?\d|2[0-3])(?:[:.h,]\s*\d{1,2})|\b\d{3,4}\b)/u';
            foreach ($lines as $ln) {
                // From: X (multilingual)
                $fromLbl = $lbl ? $lbl->group('from') : '(?:From|Fra|Da|De)';
                if (preg_match('/\b' . $fromLbl . '\b:\s*([\p{L}0-9 .,\'\-()]+)/iu', $ln, $mm)) {
                    $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mm[1]));
                    if (empty($auto['dep_station']['value']) && preg_match('/\p{L}/u', $cand) && !$isCodeLike($cand)) { $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (from labelled line)=' . $cand; }
                    if (!isset($auto['dep_time']) && preg_match($timeReLbl, $ln, $mtL)) { $auto['dep_time'] = ['value' => $normTime($mtL[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (labelled line)=' . $auto['dep_time']['value']; }
                    continue;
                }
                // To: Y (multilingual)
                $toLbl = $lbl ? $lbl->group('to') : '(?:To|A|Til|Till|À|A)';
                if (preg_match('/\b' . $toLbl . '\b:\s*([\p{L}0-9 .,\'\-()]+)/iu', $ln, $mm2)) {
                    $cand2 = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mm2[1]));
                    if (empty($auto['arr_station']['value']) && preg_match('/\p{L}/u', $cand2) && !$isCodeLike($cand2)) { $auto['arr_station'] = ['value' => $cand2, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (from labelled line)=' . $cand2; }
                    if (!isset($auto['arr_time']) && preg_match($timeReLbl, $ln, $mtL2)) { $auto['arr_time'] = ['value' => $normTime($mtL2[1]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (labelled line)=' . $auto['arr_time']['value']; }
                    continue;
                }
                // Arrow lines like "Paris (07:15) → Göteborg (10:05)"
                if (preg_match('/^\s*([\p{L}0-9 .,\'\-]+?)\s*(?:\([^)]*\))?\s*(?:→|->|—|–|\s-\s)\s*([\p{L}0-9 .,\'\-]+?)\s*(?:\([^)]*\))?/iu', $ln, $mm3)) {
                    $f = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $mm3[1]));
                    $t = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $mm3[2]));
                    $validF = preg_match('/\p{L}/u', $f) && mb_strlen($f) > 2 && !$isCodeLike($f);
                    $validT = preg_match('/\p{L}/u', $t) && mb_strlen($t) > 2 && !$isCodeLike($t);
                    if (empty($auto['dep_station']['value']) && $validF) { $auto['dep_station'] = ['value' => $f, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (arrow fallback)=' . $f; }
                    if (empty($auto['arr_station']['value']) && $validT) { $auto['arr_station'] = ['value' => $t, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (arrow fallback)=' . $t; }
                }
                if (!empty($auto['dep_station']['value']) && !empty($auto['arr_station']['value'])) { break; }
            }
        }

        // Italian headings present on Trenitalia tickets: extract stations and dates from lines with Partenza/Arrivo
        if (!isset($auto['dep_station']['value']) || !isset($auto['arr_station']['value']) || !isset($auto['dep_date']) || !isset($auto['arr_date'])) {
            $lines = preg_split('/\R/u', $text) ?: [];
            $dateRe = '/(\d{1,2}[\/.]\d{1,2}(?:[\/.](?:\d{2}|\d{4}))?)/u';
            foreach ($lines as $ln) {
                if (!isset($auto['dep_station']['value']) && preg_match('/\bPartenza\b\s*[:\-]?\s*([\p{L}0-9 .,\'\-()]+)/iu', $ln, $mmP)) {
                    $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mmP[1]));
                    if (preg_match('/\p{L}/u', $cand)) { $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (Partenza)=' . $cand; }
                    if (!isset($auto['dep_date']) && preg_match($dateRe, $ln, $md)) { $auto['dep_date'] = ['value' => $normDate($md[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_date (Partenza line)=' . $auto['dep_date']['value']; }
                }
                if (!isset($auto['arr_station']['value']) && preg_match('/\bArrivo\b\s*[:\-]?\s*([\p{L}0-9 .,\'\-()]+)/iu', $ln, $mmA)) {
                    $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mmA[1]));
                    if (preg_match('/\p{L}/u', $cand)) { $auto['arr_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (Arrivo)=' . $cand; }
                    if (!isset($auto['arr_date']) && preg_match($dateRe, $ln, $ma)) { $auto['arr_date'] = ['value' => $normDate($ma[1]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_date (Arrivo line)=' . $auto['arr_date']['value']; }
                }
                if (isset($auto['dep_station']['value']) && isset($auto['arr_station']['value']) && isset($auto['dep_date']) && isset($auto['arr_date'])) { break; }
            }
        }

        // Arrival time
    $timeLabelsArr = '(?:Arr\.?\s*time|Ankomsttid|Arrival\s*time|Ankunftszeit|Heure\s*d\'arriv[ée]e|Ankomst|Arriv[ée]e|Arrivo|Arr\.|Scheduled\s*Arr(?:ival)?)';
        if ($lbl) { $timeLabelsArr = '(?:' . $lbl->group('arr_time') . '|Arrival\s*time|Ankunftszeit|Ankomst|Arriv[ée]e|Arrivo)'; }
        if (preg_match('/' . $timeLabelsArr . '[:\s]*(?:(' . $dateToken . ')\s*(?:à|at|um|kl\.?|,)?\s*)?(' . '(?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b' . ')(?:\s*(?:Uhr|h))?/iu', $text, $m)) {
            $maybeDate = $m[1] ?? '';
            $timeVal = $m[2] ?? '';
            if ($maybeDate && !isset($auto['arr_date'])) {
                $auto['arr_date'] = ['value' => $normDate($maybeDate), 'source' => 'ocr'];
                $logs[] = 'AUTO: arr_date (from label)=' . $auto['arr_date']['value'];
            }
            $auto['arr_time'] = ['value' => $normTime($timeVal), 'source' => 'ocr'];
            $logs[] = 'AUTO: arr_time=' . $auto['arr_time']['value'];
        }
        // Simple fallback for lines like "Scheduled Arr: 10:10" (no date on the line)
        if (!isset($auto['arr_time'])) {
            if (preg_match('/\b(?:Scheduled\s*Arr(?:ival)?|Arr\.|Arrival|Arriv[ée]e)\b[:\s]*((?:[01]?\d|2[0-3])(?:[:.h,]\s*[0-5]\d)|\b\d{3,4}\b)/iu', $text, $mS)) {
                $auto['arr_time'] = ['value' => $normTime($mS[1]), 'source' => 'ocr'];
                $logs[] = 'AUTO: arr_time (scheduled shorthand)=' . $auto['arr_time']['value'];
            }
        }

        // Fallback: label + date + time on the same line (e.g., "Départ 12/11/2020 à 09h20")
        if (!isset($auto['dep_time'])) {
            if (preg_match('/\b(?:Départ|Depart|Departure|Abfahrt|Avgång|Afgang|Dep\.?)\b[\s\S]{0,120}' . $dateToken . '\s*(?:à|at|um|kl\.?|,)?\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b)/iu', $text, $m)) {
                $auto['dep_time'] = ['value' => $normTime($m[1]), 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_time (label+date)=' . $auto['dep_time']['value'];
            }
        }
        if (!isset($auto['arr_time'])) {
            if (preg_match('/\b(?:Arrivée|Arrivee|Arrival|Ankunft|Ankomst|Arr\.?)\b[\s\S]{0,120}' . $dateToken . '\s*(?:à|at|um|kl\.?|,)?\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b)/iu', $text, $m)) {
                $auto['arr_time'] = ['value' => $normTime($m[1]), 'source' => 'ocr'];
                $logs[] = 'AUTO: arr_time (label+date)=' . $auto['arr_time']['value'];
            }
        }

        // Fallback: simple time range e.g., "09:20 - 11:22" if not yet set
        if (!isset($auto['dep_time']) || !isset($auto['arr_time'])) {
            if (preg_match('/\b((?:[01]?\d|2[0-3])(?:[:.h,]\s*\d{1,2})?|\d{3,4})\b\s*[–\-]\s*\b((?:[01]?\d|2[0-3])(?:[:.h,]\s*\d{1,2})?|\d{3,4})\b/iu', $text, $mm)) {
                if (!isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($mm[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (range)=' . $auto['dep_time']['value']; }
                if (!isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($mm[2]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (range)=' . $auto['arr_time']['value']; }
            }
        }

        // Fallback: extract times from lines containing station names
        if ((!isset($auto['dep_time']) || !isset($auto['arr_time'])) && (!empty($auto['dep_station']['value']) || !empty($auto['arr_station']['value']))) {
            $lines = preg_split('/\R/u', $text) ?: [];
            $timeRe = '/((?:[01]?\d|2[0-3])(?:[:.h,]\s*\d{1,2})?|\b\d{3,4}\b)/u';
            $depName = isset($auto['dep_station']['value']) ? (string)$auto['dep_station']['value'] : '';
            $arrName = isset($auto['arr_station']['value']) ? (string)$auto['arr_station']['value'] : '';
            foreach ($lines as $ln) {
                if ($depName && !isset($auto['dep_time']) && preg_match('/' . preg_quote($depName, '/') . '/iu', $ln) && preg_match($timeRe, $ln, $mt)) {
                    $auto['dep_time'] = ['value' => $normTime($mt[1]), 'source' => 'ocr'];
                    $logs[] = 'AUTO: dep_time (near station)=' . $auto['dep_time']['value'];
                }
                if ($arrName && !isset($auto['arr_time']) && preg_match('/' . preg_quote($arrName, '/') . '/iu', $ln) && preg_match($timeRe, $ln, $mt2)) {
                    $auto['arr_time'] = ['value' => $normTime($mt2[1]), 'source' => 'ocr'];
                    $logs[] = 'AUTO: arr_time (near station)=' . $auto['arr_time']['value'];
                }
                if (isset($auto['dep_time']) && isset($auto['arr_time'])) { break; }
            }
        }

        // Arrow line pattern: From → To [HH:MM] – [HH:MM]
        if (!isset($auto['dep_station']) || !isset($auto['arr_station']) || !isset($auto['dep_time']) || !isset($auto['arr_time'])) {
            // Variant with times in parentheses next to station names
            if (preg_match('/^\s*([\p{L}0-9 .,\'\-]+?)\s*\(\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\d{3,4})\s*\)\s*(?:→|->|—|–|\s-\s)\s*([\p{L}0-9 .,\'\-]+?)\s*\(\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\d{3,4})\s*\)/ium', $text, $m)) {
                $from = trim($m[1]); $to = trim($m[3]);
                $t1 = $m[2] ?? ''; $t2 = $m[4] ?? '';
                if ($from && !isset($auto['dep_station']) && !$isCodeLike($from)) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (arrow paren)=' . $from; }
                if ($to && !isset($auto['arr_station']) && !$isCodeLike($to)) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (arrow paren)=' . $to; }
                if ($t1 && !isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($t1), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (arrow paren)=' . $auto['dep_time']['value']; }
                if ($t2 && !isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($t2), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (arrow paren)=' . $auto['arr_time']['value']; }
            } elseif (preg_match('/^\s*([\p{L}0-9 .,\'\-]+?)\s*(?:→|->|—|–|\s-\s)\s*([\p{L}0-9 .,\'\-]+?)(?:\s+((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)?))?(?:\s*[–\-]\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)?))?/ium', $text, $m)) {
                $from = trim($m[1]); $to = trim($m[2]);
                if ($from && !isset($auto['dep_station']) && !$isCodeLike($from)) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (arrow)=' . $from; }
                if ($to && !isset($auto['arr_station']) && !$isCodeLike($to)) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (arrow)=' . $to; }
                if (!empty($m[3]) && !isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($m[3]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (arrow)=' . $auto['dep_time']['value']; }
                if (!empty($m[4]) && !isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($m[4]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (arrow)=' . $auto['arr_time']['value']; }
            }
            // Fallback: "De X à Y" style without arrow
            if ((!isset($auto['dep_station']) || !isset($auto['arr_station'])) && preg_match('/\b(?:De|Da|Von|Fra|Från)\b[:\s]*([^\n\r]+?)\s+\b(?:À|A|Til|Till|Nach|Vers)\b[:\s]*([^\n\r]+)/iu', $text, $m2)) {
                $from = trim($m2[1]); $to = trim($m2[2]);
                if ($from && !isset($auto['dep_station']) && !$isCodeLike($from)) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (de/à)=' . $from; }
                if ($to && !isset($auto['arr_station']) && !$isCodeLike($to)) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (de/à)=' . $to; }
            }
        }

        // Global scan (low priority): multiple Date entries on a single line; pick the first time after a Date
        if (!isset($auto['dep_time'])) {
            $labelGroup = '(?:Date|Data|Datum|Dato|Afgangsdato|Departure\s*date|Date\s*de\s*départ)';
            if ($lbl) { $labelGroup = '(?:' . $lbl->group('date') . '|Dato|Afgangsdato|Departure\s*date|Date\s*de\s*départ)'; }
            $timeGroup = '((?:[01]?\d|2[0-3])(?:[:.h,]\s*[0-5]\d)|\b\d{3,4}\b)';
            // Do not cross line breaks between date and time; only spaces/tabs allowed
            if (preg_match_all('/' . $labelGroup . '\b[:\s]*' . $dateToken . '[ \t]*[,;:]?[ \t]*' . $timeGroup . '/ium', $text, $all, PREG_SET_ORDER)) {
                foreach ($all as $one) {
                    $t = $one[count($one)-1] ?? '';
                    if ($t !== '') { $auto['dep_time'] = ['value' => $normTime($t), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (Date global)=' . $auto['dep_time']['value']; break; }
                }
            }
        }

        // Train no/category: prefer labeled capture first
        if (preg_match('/(?:Train\s*(?:no\.|number|category)?|Tognummer|Treno)[:\s]*([A-ZÄÖÜÅÆØ]{1,8}\s*\d{1,6}[A-Z]?)/iu', $text, $m)) {
            $auto['train_no'] = ['value' => trim($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: train_no=' . $auto['train_no']['value'];
        } elseif (preg_match('/\b(?:TGV|ICE|IC|EC|EN|TER|AVE|REG|RE|RB|IR|RJX?|SJ|DSB|SBB|ÖBB|OEBB|NS|SNCF|CFL|CP|PKP|ZSSK)\s*\d{1,6}[A-Z]?\b/u', $text, $m)) {
            $auto['train_no'] = ['value' => trim($m[0]), 'source' => 'ocr'];
            $logs[] = 'AUTO: train_no=' . $auto['train_no']['value'];
        }

        // Ticket/Booking ref (PNR)
        if (preg_match('/\b(?:PNR|Booking\s*Reference|Billetnummer|Ticket\s*(?:no\.|number|reference))\b[:\s]*([A-Z0-9\-]+)/iu', $text, $m)) {
            $auto['ticket_no'] = ['value' => $m[1], 'source' => 'ocr'];
            $logs[] = 'AUTO: ticket_no=' . $m[1];
        }

        // Price with decimals, symbols and currencies
        if (preg_match('/(?:Price|Pris|Ticket\s*price|Preis|Prix)[:\s]*([0-9]+(?:[\.,][0-9]{2})?)\s*(€|EUR|DKK|SEK|NOK|USD|CHF|GBP|kr)?/iu', $text, $m)) {
            $val = $m[1]; if (str_contains($val, ',')) { $val = str_replace(',', '.', $val); }
            $auto['price'] = ['value' => $val . (isset($m[2]) && $m[2] ? (' ' . strtoupper($m[2])) : ''), 'source' => 'ocr'];
            $logs[] = 'AUTO: price=' . $auto['price']['value'];
        }
        // Actual journey fields (TRIN 3.3)
        if (preg_match('/(?:Actual\s*arrival\s*date|Faktisk\s*ankomstdato)[:\s]*([0-9]{4}-[0-9]{2}-[0-9]{2}|\d{2}[\/.]\d{2}[\/.](?:\d{2}|\d{4}))/i', $text, $m)) {
            $auto['actual_arrival_date'] = ['value' => $normDate($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: actual_arrival_date=' . $m[1];
        }
        if (preg_match('/(?:Actual\s*departure\s*time|Faktisk\s*afgangstid)[:\s]*((?:[01]?\d|2[0-3])[:.][0-5]\d)/i', $text, $m)) {
            $auto['actual_dep_time'] = ['value' => $normTime($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: actual_dep_time=' . $m[1];
        }
        if (preg_match('/(?:Actual\s*arrival\s*time|Faktisk\s*ankomsttid)[:\s]*((?:[01]?\d|2[0-3])[:.][0-5]\d)/i', $text, $m)) {
            $auto['actual_arr_time'] = ['value' => $normTime($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: actual_arr_time=' . $m[1];
        }
        if (preg_match('/(?:Missed\s*connection\s*station|Mistet\s*forbindelse\s*i|Missed\s*station|Umstiegsbahnhof\s*verpasst)[:\s]*([\w\- .]+)/i', $text, $m)) {
            $auto['missed_connection_station'] = ['value' => trim($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: missed_connection_station=' . $m[1];
        }

        // Cheapest highlighted / multiple fares shown
        if (preg_match('/cheapest\s+fare(\s+shown)?|billigste/i', $text)) {
            $auto['cheapest_highlighted'] = $yes('cheapest_highlighted');
            $auto['multiple_fares_shown'] = $auto['multiple_fares_shown'] ?? $yes('multiple_fares_shown');
            $logs[] = 'AUTO: cheapest_highlighted from OCR';
        }
        if (preg_match('/fares\s+from|pris(er)?\s+fra|multiple\s+fares/i', $text)) { $auto['multiple_fares_shown'] = $yes('multiple_fares_shown'); $logs[] = 'AUTO: multiple_fares_shown from OCR'; }

        // MCT realistic / alternatives shown
        if (preg_match('/minimum\s+connection\s+time|\bmct\b|skifte\s*tid/i', $text)) { $auto['mct_realistic'] = $yes('mct_realistic'); $logs[] = 'AUTO: mct_realistic from OCR'; }
        if (preg_match('/alternativ(e|er)|alternatives\s+shown/i', $text)) { $auto['alts_shown_precontract'] = $yes('alts_shown_precontract'); $logs[] = 'AUTO: alts_shown_precontract from OCR'; }

        // Fare flex type
        if (preg_match('/non-?ref(undable)|non-?exchangeable|no\s+changes/i', $text)) { $auto['fare_flex_type'] = ['value' => 'Restriktiv', 'source' => 'ocr']; $logs[] = 'AUTO: fare_flex_type=Restriktiv'; }
        elseif (preg_match('/flex(i|ible)|changeable|ref(undable)/i', $text)) { $auto['fare_flex_type'] = ['value' => 'Fleksibel', 'source' => 'ocr']; $logs[] = 'AUTO: fare_flex_type=Fleksibel'; }

        // Promised facilities
            if ($lbl) {
                // Keep provider-driven group but also include broad static synonyms
                $timeLabelsDep = '(?:' . $lbl->group('dep_time') . '|Dep\.?\s*time|Afgangstid|Afgang|Departure\s*time|Avgångstid|Abfahrtszeit|Heure\s*de\s*d[ée]part|Abfahrt|Kl\.?|Kl\s*|Départ|Depart|Partenza|Part\.|Scheduled\s*Dep(?:arture)?)';
            }
        $fac = [];
        if (preg_match('/wifi/i', $text)) { $fac[] = 'Wifi'; }
        if (preg_match('/quiet\s+zone|stille\s+zone/i', $text)) { $fac[] = 'Quiet zone'; }
        if (preg_match('/power\s*outlets|stik\s*kontakt/i', $text)) { $fac[] = 'Stikkontakt'; }
        if (!empty($fac)) { $auto['promised_facilities'] = ['value' => $fac, 'source' => 'ocr']; $logs[] = 'AUTO: promised_facilities=' . implode(',', $fac); }

        // Train specificity
        if (preg_match('/\btrain\b\s*[:#]\s*[A-Z]*\s*\d+|\btog\b\s*[:#]\s*\d+/i', $text)) { $auto['train_specificity'] = ['value' => 'Kun specifikt tog', 'source' => 'ocr']; $logs[] = 'AUTO: train_specificity'; }

        // CIV marking on ticket
        if (preg_match('/\bCIV\b/i', $text)) { $auto['civ_marking_present'] = $yes('civ_marking_present'); $logs[] = 'AUTO: civ_marking_present'; }

        // Through ticket disclosure / separate contracts (very simple language cues)
        if (preg_match('/gennemgående\s+billet|through\s+ticket/i', $text)) { $auto['through_ticket_disclosure'] = ['value' => 'Gennemgående', 'source' => 'ocr']; $logs[] = 'AUTO: through_ticket_disclosure=Gennemgående'; }
        if (preg_match('/særskilte\s+kontrakter|separate\s+contracts/i', $text)) { $auto['through_ticket_disclosure'] = ['value' => 'Særskilte', 'source' => 'ocr']; $logs[] = 'AUTO: through_ticket_disclosure=Særskilte'; }

        // Explicit separate-contract notice (distinct from disclosure phrasing)
            if ($lbl) {
                // Keep provider-driven group but also include broad static synonyms
                $timeLabelsArr = '(?:' . $lbl->group('arr_time') . '|Arr\.?\s*time|Ankomsttid|Arrival\s*time|Ankunftszeit|Heure\s*d\'arriv[ée]e|Ankomst|Arriv[ée]e|Arrivo|Arr\.|Scheduled\s*Arr(?:ival)?)';
            }
        if (preg_match('/separate\s+contract(s)?\s+(notice|stated|indicated)|særskilte\s+kontrakter\s+(angivet|oplyst)/i', $text)) {
            $auto['separate_contract_notice'] = ['value' => 'Ja', 'source' => 'ocr'];
            $logs[] = 'AUTO: separate_contract_notice=Ja';
        }

        // Contact info and responsibility statements
        if (preg_match('/contact\s+(us|info|customer\s*service)|kontakt\s*(os|info|kundeservice)/i', $text)) {
            $auto['contact_info_provided'] = ['value' => 'yes', 'source' => 'ocr'];
            $logs[] = 'AUTO: contact_info_provided=yes';
        }
        if (preg_match('/responsib(le|ility)\s+(for|in\s+case\s+of)\s+(miss(ed)?\s+connection|delay)|ansvar\s+(ved|for)\s+(misset|missed|forsinkelse)/i', $text)) {
            $auto['responsibility_explained'] = ['value' => 'yes', 'source' => 'ocr'];
            $logs[] = 'AUTO: responsibility_explained=yes';
        }

        // Agency/retailer keywords vs operator sale (very rough)
        if (preg_match('/rail\.eu|trainline|omio|rejse(bureau|agent)|ticket\s*(retailer|agency)|billet\s*udsteder/i', $text)) {
            $auto['seller_type'] = ['value' => 'agency', 'source' => 'ocr'];
            $logs[] = 'AUTO: seller_type=agency';
        }
        if (preg_match('/book(ed)?\s*(with|via)\s*(dsb|db|sncf|sj|trenitalia)|købt\s*(hos|via)\s*(dsb|db|sncf|sj)/i', $text)) {
            $auto['seller_type'] = ['value' => 'operator', 'source' => 'ocr'];
            $logs[] = 'AUTO: seller_type=operator';
        }

        // Fastest/recommended flag
        if (preg_match('/fastest|anbefalet|recommended/i', $text)) { $auto['fastest_flag_at_purchase'] = $yes('fastest_flag_at_purchase'); $logs[] = 'AUTO: fastest_flag_at_purchase'; }

        // Complaint channel mention
        if (preg_match('/complaint|klage|reklamation/i', $text)) { $auto['complaint_channel_seen'] = $yes('complaint_channel_seen'); $logs[] = 'AUTO: complaint_channel_seen'; }

        // PMR hints
        if (preg_match('/wheelchair|pmr|nedsat\s+mobilitet|assistance/i', $text)) { $auto['pmr_user'] = $auto['pmr_user'] ?? $yes('pmr_user'); $logs[] = 'AUTO: pmr_user'; }
        if (preg_match('/assistance\s+(booked|reserved|bestilt)|pmr\s+booking/i', $text)) { $auto['pmr_booked'] = $yes('pmr_booked'); $logs[] = 'AUTO: pmr_booked'; }

        // Bikes
        if (preg_match('/bike|cykel/i', $text)) {
            if (preg_match('/reservation\s+required|reservation\s+needed|reservation\s+påkrævet|kræver\s+reservation/i', $text)) {
                $auto['bike_res_required'] = $yes('bike_res_required');
                $logs[] = 'AUTO: bike_res_required=Ja';
            }
            if (preg_match('/bike\s+reservation|cykel\s*reservation/i', $text)) {
                $auto['bike_reservation_type'] = ['value' => 'Ja, separat cykelreservation', 'source' => 'ocr'];
                $logs[] = 'AUTO: bike_reservation_type=separat';
            }
            if (preg_match('/no\s+bike\s+reservation|ingen\s+cykel\s*reservation/i', $text)) {
                $auto['bike_reservation_type'] = ['value' => 'Nej, ingen reservation krævet', 'source' => 'ocr'];
                $logs[] = 'AUTO: bike_reservation_type=ingen';
            }
        }

        // Operator / country / product via catalog
        try {
            $catalog = new \App\Service\OperatorCatalog();
            $found = $catalog->findOperator($text);
            if ($found) {
                $auto['operator'] = ['value' => $found['name'], 'source' => 'ocr'];
                $auto['operator_country'] = ['value' => $found['country'], 'source' => 'ocr'];
                $logs[] = 'AUTO: operator from catalog=' . $found['name'] . ' (' . $found['country'] . ')';
            }
            $prod = $catalog->findProduct($text);
            if ($prod) {
                $auto['operator_product'] = ['value' => $prod, 'source' => 'ocr'];
                $logs[] = 'AUTO: operator_product from catalog=' . $prod;
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        return ['auto' => $auto, 'logs' => $logs];
    }
}
