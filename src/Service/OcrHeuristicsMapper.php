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
        // Detect lines that look like boilerplate/instructions rather than station names
        $looksLikeBoilerplate = function(string $s): bool {
            $t = mb_strtolower($s);
            // Immediate reject if the string contains a URL or domain-like token (e.g., www.bahn.de)
            if (preg_match('/\b(?:https?:\/\/)?(?:www\.)?[a-z0-9.-]+\.(?:de|dk|se|no|fi|fr|it|nl|pl|cz|sk|si|hr|hu|es|pt|ro|bg|at|be|lu|ie|lt|lv|ee|gr|ch|uk|eu|com)\b/iu', $s)) {
                return true;
            }
            $stop = [
                // Italian frequent boilerplate on tickets
                'esibire','condizioni','vettore','servizio','classe','base','tot','totale','emit','vett','p.iva','p iva','trenitalia',
                'data','ora','bigl','posti','carrozza','finestrino',
                // Generic
                'conditions','service','class','price','total','pnr','booking','reference','category',
                // German boilerplate and table words
                'fahrkarte','fahrkarten','bahncard','lichtbildausweis','nutzung','nutzungshinweise','gültig','gültigkeit','bedingungen','beförderungsbedingungen','beforderungsbedingungen',
                'verkehrsunternehmen','tarif','tarifgemeinschaft','bahn.de','db.de','diebahn','zangenabdruck','auftragsnummer','auftrag','sitzplatz','reservierung','hinweise','zugbindung','fahrgastrechte','einlösebedingungen','einloesebedingungen',
                // Extra German phrases that appeared in headers/instructions
                'dokument','reiseverbindung','vorzeigepflichtig','website','kurzfristige','fahrplanänderungen','fahrplanaenderungen','bitte beachten','gilt nur',
                'klasse','personen','reisende','einfach','fahrt','produkte','produkt','gleis','platz','handy',
                // Danish complaint/legal boilerplate that occasionally bleeds into OCR near the form
                'domstol','domstole','klage','ankenævn','ankenaevn','forbruger','forbrug','vilkår','vilkar','betingelser','juridisk','ansvar','ansvarsfraskrivelse','kundeservice'
            ];
            $hits = 0;
            foreach ($stop as $w) { if (str_contains($t, $w)) { $hits++; if ($hits >= 2) return true; } }
            // Strong single-word/phrase triggers commonly found in headers/instructions
            $strong = [
                'da esibire', 'esibire', 'valgon', 'condizioni', 'risparmi', 'co2', 'tariffa', 'prenotazione', 'biglietto',
                'classe', 'carrozza', 'posti', 'finestrino', 'tot.bigl', 'p. iva', 'p iva', 'auftragsnummer', 'zangenabdruck', 'fahrgastrechte', 'einlösebedingungen', 'einloesebedingungen',
                // Danish legal boilerplate snippets
                'de almindelige domstole', 'almindelige domstole', 'klage til', 'klage over'
            ];
            foreach ($strong as $w) { if (str_contains($t, $w)) { return true; } }
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
            // Swedish/Scandinavian month names like "29 maj 2025" or with weekday prefix
            $monthMap = [
                'jan' => 1,'januar' => 1,'januari' => 1,
                'feb' => 2,'februar' => 2,'februari' => 2,
                'mar' => 3,'mars' => 3,'marts' => 3,
                'apr' => 4,'april' => 4,
                'maj' => 5,'may' => 5,
                'jun' => 6,'juni' => 6,
                'jul' => 7,'juli' => 7,
                'aug' => 8,'august' => 8,
                'sep' => 9,'sept' => 9,'september' => 9,
                'okt' => 10,'oktober' => 10,'oct' => 10,'october' => 10,
                'nov' => 11,'november' => 11,
                'dec' => 12,'december' => 12
            ];
            if (preg_match('/\b(\d{1,2})\s+([A-Za-zÅÄÖåäö]{3,9})\s+(\d{4})\b/u', $d, $msv)) {
                $moKey = mb_strtolower($msv[2], 'UTF-8');
                $moKey = preg_replace('/[^a-zåäö]/iu', '', (string)$moKey) ?? $moKey;
                if (isset($monthMap[$moKey])) {
                    return sprintf('%04d-%02d-%02d', (int)$msv[3], (int)$monthMap[$moKey], (int)$msv[1]);
                }
            }
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
        // Strict HH:MM validator to weed out compact junk like 0072 captured near codes
        $isValidTime = function(string $t): bool {
            return (bool)preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $t);
        };
        // Label/Station validators
        $isLabelToken = function(string $s): bool {
            $t = mb_strtolower(trim($s));
            if ($t === '') { return false; }
            $labels = ['partenza','arrivo','departure','depart','départ','arrival','arrivée','ankunft','abfahrt','ankomst'];
            return in_array($t, $labels, true);
        };
        $isStation = function(string $s) use ($letterLen, $isCodeLike, $looksLikeBoilerplate, $isLabelToken): bool {
            $s = trim($s);
            if ($s === '') { return false; }
            if ($isLabelToken($s)) { return false; }
            $letters = $letterLen($s);
            if ($letters < 3) { return false; }
            if ($isCodeLike($s)) { return false; }
            if ($looksLikeBoilerplate($s)) { return false; }
            // Reject lines that look like sentences or legal phrases rather than proper nouns
            // Heuristic 1: require at least one uppercase letter (most ticket station names are capitalized)
            if (!preg_match('/\p{Lu}/u', $s)) {
                // If there are no uppercase letters and the average token length is very short, reject (e.g., "den dag og den")
                $tokens = preg_split('/\s+/u', $s) ?: [];
                $lenSum = 0; $cnt = 0; foreach ($tokens as $tk) { $lenSum += mb_strlen(preg_replace('/[^\p{L}]/u','',$tk)); $cnt++; }
                $avg = $cnt > 0 ? ($lenSum / $cnt) : 0;
                if ($avg < 3.2) { return false; }
            }
            // Heuristic 2: trailing period usually indicates a sentence; allow common abbreviations like Hbf. or St.
            if (preg_match('/\.(\s*)$/u', $s) && !preg_match('/\b(Hbf\.|St\.)$/iu', $s)) { return false; }
            return (bool)preg_match('/\p{L}/u', $s);
        };
        // Sanitize station-like text: strip arrows/labels and trailing numbers/platforms
        $cleanStationText = function(string $s) use ($isLabelToken): string {
            $t = ' ' . trim($s) . ' ';
            // Remove arrow artifacts and adjacent labels
            $t = preg_replace('/(?:--?>?|→|—|–)\s*/u', ' ', $t) ?? $t;
            // Remove standalone label/preposition tokens
            $t = preg_replace('/\b(?:Partenza|Arrivo|Departure|Arrival|Abfahrt|Ankunft|Ankomst|To|Til|Till|Nach|Vers|A|À|Al)\b/iu', ' ', $t) ?? $t;
            // Danish DSB header like "Til rejsen Herning Messecenter St. - København H, Mandag d. 18 marts"
            // Strip the lead-in phrase "Til rejsen"
            $t = preg_replace('/^\s*(?:Til|For)\s+rejsen\s+/iu', ' ', $t) ?? $t;
            // Strip URLs/domains outright
            $t = preg_replace('/\b(?:https?:\/\/)?(?:www\.)?[\w.-]+\.[A-Za-z]{2,}\b/u', ' ', $t) ?? $t;
            // Drop parenthetical fragments
            $t = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $t) ?? $t;
            // Remove trailing weekday/month descriptors after a comma (", Mandag d. 18 marts")
            $t = preg_replace('/,\s*(?:mandag|tirsdag|onsdag|torsdag|fredag|l[øo]rdag|s[øo]ndag|januar|februar|marts|april|maj|juni|juli|august|september|oktober|november|december)\b.*$/iu', ' ', $t) ?? $t;
            // Remove trailing platform/coach info (incl. German "Gl.")
            $t = preg_replace('/\s+(?:bin(?:ario)?|gleis|gl\.?|platform|carrozza|coach)\s*\d+\s*$/iu', ' ', $t) ?? $t;
            // Trim trailing French layout keywords often printed to the right of station names
            $t = preg_replace('/\s+(?:classe|voiture|place|duplex|prix|page)\b.*$/iu', ' ', $t) ?? $t;
            // Remove trailing pure numbers or number-like tokens
            $t = preg_replace('/\s+(?:n[°o]\.?\s*)?\d+\s*$/iu', ' ', $t) ?? $t;
            // Collapse whitespace
            $t = preg_replace('/\s{2,}/u', ' ', $t) ?? $t;
            $t = trim($t);
            // If the entire cleaned value is just a label, blank it
            if ($isLabelToken($t)) { return ''; }
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
        // Primary departure station labels: avoid generic prepositions (De/Da/Von/Fra/From) here to reduce false positives
        $stationDepLabels = '(?:Dep\.?\s*station|Afgangsstation|Departure\s*station|Avgångsstation|Abfahrtsbahnhof|Gare\s*de\s*d[ée]part|Partenza)';
        if ($lbl) {
            // Avoid substring matches by requiring word boundaries around the dynamic group
            $stationDepLabels = '(?:\b(?:' . $lbl->group('dep_station') . ')\b)';
        }
        if (preg_match('/' . $stationDepLabels . '[:\s]*([\p{L}0-9\- .()]+)(?=$|\r|\n|\s+(?:À|A|Til|Till|Nach)\b)/iu', $text, $m)) {
            $candidate = trim($m[1]);
            // strip trailing parenthetical fragments like (08:04)
            $candidate = preg_replace('/\s*\([^)]*\)\s*$/u', '', $candidate);
            // station-like token
            $isValidStation = $isStation($candidate);
            if ($isValidStation) {
                $auto['dep_station'] = ['value' => $candidate, 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_station=' . $candidate;
            } else {
                $logs[] = 'SKIP: dep_station candidate too short/code-like => ' . $candidate;
            }
        }
        // Cross-line fallback for departure station label on previous line, value on next line
        if (empty($auto['dep_station']['value'])) {
            $depCrossLbl = $lbl ? '(?:' . $lbl->group('dep_station') . ')' : '(?:Dep\.?\s*station|Departure\s*station|Afgangsstation|Avgångsstation|Abfahrtsbahnhof|Gare\s*de\s*d[ée]part|Partenza)';
            if (preg_match('/\b' . $depCrossLbl . '\b\s*:?\s*(?:\r?\n)+\s*([^\r\n]+)/ium', $text, $mc)) {
                $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mc[1]));
                if ($isStation($cand)) {
                    $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr'];
                    $logs[] = 'AUTO: dep_station (cross-line)=' . $cand;
                }
            }
        }

        // Arrival station
        // Primary arrival/destination labels: avoid generic prepositions here too
        $stationArrLabels = '(?:Arr\.?\s*station|Ankomststation|Destination\s*station|Ankunftsbahnhof|Gare\s*d\'arriv[ée]e|Destination|Arrivo)';
        if ($lbl) {
            // Avoid substring matches by requiring word boundaries around the dynamic group
            $stationArrLabels = '(?:\b(?:' . $lbl->group('arr_station') . ')\b)';
        }
        if (preg_match('/' . $stationArrLabels . '[:\s]*([\p{L}0-9\- .()]+?)(?=$|\r|\n|\s+(?:Partenza|Part\.|Départ|Depart|Afgang|Departure|Arrivo|Arr\.|Arrival|Ankunft|Ankomst|Til|Till|Nach|Vers|To)\b)/iu', $text, $m)) {
            $candidate = trim($m[1]);
            $candidate = preg_replace('/\s*\([^)]*\)\s*$/u', '', $candidate);
            $isValidStation = $isStation($candidate);
            if ($isValidStation) {
                $auto['arr_station'] = ['value' => $candidate, 'source' => 'ocr'];
                $logs[] = 'AUTO: arr_station=' . $candidate;
            } else {
                $logs[] = 'SKIP: arr_station candidate too short/code-like => ' . $candidate;
            }
        }
        // Cross-line fallback for arrival/destination label on previous line, value on next line
        if (empty($auto['arr_station']['value'])) {
            $arrCrossLbl = $lbl ? '(?:' . $lbl->group('arr_station') . ')' : '(?:Arr\.?\s*station|Destination\s*station|Ankomststation|Ankunftsbahnhof|Gare\s*d\'arriv[ée]e|Destination|Arrivo)';
            if (preg_match('/\b' . $arrCrossLbl . '\b\s*:?\s*(?:\r?\n)+\s*([^\r\n]+)/ium', $text, $mc)) {
                $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mc[1]));
                if ($isStation($cand)) {
                    $auto['arr_station'] = ['value' => $cand, 'source' => 'ocr'];
                    $logs[] = 'AUTO: arr_station (cross-line)=' . $cand;
                }
            }
        }

        // German DB tickets often include a summary line like "Einfache Fahrt: Verona P. N. - Düsseldorf Hbf".
        // If stations are still missing, try to parse that summary.
        if (empty($auto['dep_station']['value']) || empty($auto['arr_station']['value'])) {
            if (preg_match('/\b(?:Einfach(?:e)?\s+Fahrt)\b[^:\n]*:\s*([\p{L}0-9 .,\'\-()]+?)\s*[-–—>]\s*([\p{L}0-9 .,\'\-()]+)/iu', $text, $mSum)) {
                $from = $cleanStationText($mSum[1] ?? '');
                $to = $cleanStationText($mSum[2] ?? '');
                if ($isStation($from) && empty($auto['dep_station']['value'])) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (Einfache Fahrt)=' . $from; }
                if ($isStation($to) && empty($auto['arr_station']['value'])) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (Einfache Fahrt)=' . $to; }
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
                    if (empty($auto['dep_station']['value']) && $isStation($cand)) { $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (from labelled line)=' . $cand; }
                    if (!isset($auto['dep_time']) && preg_match($timeReLbl, $ln, $mtL)) { $auto['dep_time'] = ['value' => $normTime($mtL[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (labelled line)=' . $auto['dep_time']['value']; }
                    continue;
                }
                // To: Y (multilingual)
                $toLbl = $lbl ? $lbl->group('to') : '(?:To|A|Til|Till|À)';
                if (preg_match('/\b' . $toLbl . '\b:\s*([\p{L}0-9 .,\'\-()]+)/iu', $ln, $mm2)) {
                    $cand2 = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mm2[1]));
                    if (empty($auto['arr_station']['value']) && $isStation($cand2)) { $auto['arr_station'] = ['value' => $cand2, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (from labelled line)=' . $cand2; }
                    if (!isset($auto['arr_time']) && preg_match($timeReLbl, $ln, $mtL2)) { $auto['arr_time'] = ['value' => $normTime($mtL2[1]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (labelled line)=' . $auto['arr_time']['value']; }
                    continue;
                }
                // Arrow lines like "Paris (07:15) → Göteborg (10:05)"
                if (preg_match('/^\s*([\p{L}][\p{L}0-9 .,\'\-]+?)\s*(?:\([^)]*\))?\s*(?:→|->|—|–|\s-\s)\s*([\p{L}][\p{L}0-9 .,\'\-]+?)\s*(?:\([^)]*\))?/iu', $ln, $mm3)) {
                    $f = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $mm3[1]));
                    $t = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $mm3[2]));
                    $validF = $isStation($f);
                    $validT = $isStation($t);
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
                if (!isset($auto['dep_station']['value']) && preg_match('/\bPartenza\b\s*[:\-]?\s*([\p{L}][\p{L}0-9 .,\'\-()]*?)(?=\s+(?:Arrivo|Arr\.|Arrival|Ankunft|Ankomst|To|Til|Till)\b|$)/iu', $ln, $mmP)) {
                    $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mmP[1]));
                    if ($isStation($cand)) { $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (Partenza)=' . $cand; }
                    if (!isset($auto['dep_date']) && preg_match($dateRe, $ln, $md)) { $auto['dep_date'] = ['value' => $normDate($md[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_date (Partenza line)=' . $auto['dep_date']['value']; }
                    if (!isset($auto['dep_time']) && preg_match('/\b(\d{1,2}[.:h]\s*\d{2})\b/u', $ln, $mtp)) { $auto['dep_time'] = ['value' => $normTime($mtp[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (Partenza line)=' . $auto['dep_time']['value']; }
                }
                if (!isset($auto['arr_station']['value']) && preg_match('/\bArrivo\b\s*[:\-]?\s*([\p{L}][\p{L}0-9 .,\'\-()]*?)(?=$)/iu', $ln, $mmA)) {
                    $cand = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($mmA[1]));
                    if ($isStation($cand)) { $auto['arr_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (Arrivo)=' . $cand; }
                    if (!isset($auto['arr_date']) && preg_match($dateRe, $ln, $ma)) { $auto['arr_date'] = ['value' => $normDate($ma[1]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_date (Arrivo line)=' . $auto['arr_date']['value']; }
                    if (!isset($auto['arr_time']) && preg_match('/\b(\d{1,2}[.:h]\s*\d{2})\b/u', $ln, $mta)) { $auto['arr_time'] = ['value' => $normTime($mta[1]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (Arrivo line)=' . $auto['arr_time']['value']; }
                }
                if (isset($auto['dep_station']['value']) && isset($auto['arr_station']['value']) && isset($auto['dep_date']) && isset($auto['arr_date'])) { break; }
            }
        }

    // Italian schedule row parser (e.g., "07.04 11.17 BOLOGNA CENTRALE MILANO CENTRALE 07.04 13.25 2")
        if (!isset($auto['dep_station']['value']) || !isset($auto['arr_station']['value']) || !isset($auto['dep_time']) || !isset($auto['arr_time'])) {
            $lines = preg_split('/\R/u', $text) ?: [];
            // Capture the stations block between the two date/time pairs, then split it intelligently
            $pat = '/\b(\d{1,2}[\/.]\d{1,2})\s+(\d{1,2}[.:h]\s*\d{2})\s+(.+?)\s+(\d{1,2}[\/.]\d{1,2})\s+(\d{1,2}[.:h]\s*\d{2})\b/iu';
            foreach ($lines as $ln) {
                if (preg_match($pat, $ln, $m)) {
                    $d1 = $m[1]; $t1 = $m[2];
                    $stationsBlock = trim(preg_replace('/\s{2,}/u', ' ', $m[3]));
                    // Pre-clean the stations block to remove arrows/labels
                    $stationsBlock = $cleanStationText($stationsBlock);
                    $d2 = $m[4]; $t2 = $m[5];
                    // Try to split the stationsBlock into two valid station names
                    $from = ''; $to = '';
                    $words = preg_split('/\s+/u', $stationsBlock) ?: [];
                    $best = null; // ['from' => string, 'to' => string, 'score' => int]
                    for ($i = 1; $i < count($words); $i++) {
                        $candFrom = trim(implode(' ', array_slice($words, 0, $i)));
                        $candTo = trim(implode(' ', array_slice($words, $i)));
                        if ($candFrom === '' || $candTo === '') { continue; }
                        $okFrom = $isStation($candFrom);
                        $okTo = $isStation($candTo);
                        // Score: prefer both valid, more balanced word counts, higher min letter length
                        $score = 0;
                        if ($okFrom && $okTo) { $score += 10; }
                        $score += min($letterLen($candFrom), $letterLen($candTo));
                        // Penalize extreme imbalance in word counts
                        $wcDiff = abs(count(preg_split('/\s+/u', $candFrom)) - count(preg_split('/\s+/u', $candTo)));
                        $score -= $wcDiff;
                        if ($best === null || $score > $best['score']) {
                            $best = ['from' => $candFrom, 'to' => $candTo, 'score' => $score, 'bothValid' => ($okFrom && $okTo)];
                        }
                    }
                    if ($best) { $from = $best['from']; $to = $best['to']; }
                    // Post-clean candidates (strip trailing numbers/labels once more)
                    $from = $cleanStationText($from);
                    $to = $cleanStationText($to);
                    // If not both valid and exactly 4 words, try simple 2/2 split
                    if (!($isStation($from) && $isStation($to)) && count($words) === 4) {
                        $candFrom = $cleanStationText($words[0] . ' ' . $words[1]);
                        $candTo = $cleanStationText($words[2] . ' ' . $words[3]);
                        if ($isStation($candFrom) && $isStation($candTo)) { $from = $candFrom; $to = $candTo; }
                    }
                    // Final validation
                    $validFrom = $isStation($from);
                    $validTo = $isStation($to);
                    if ($validFrom && !isset($auto['dep_station'])) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (it schedule row)=' . $from; }
                    if ($validTo && !isset($auto['arr_station'])) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (it schedule row)=' . $to; }
                    if (!isset($auto['dep_date'])) { $auto['dep_date'] = ['value' => $normDate($d1), 'source' => 'ocr']; $logs[] = 'AUTO: dep_date (it schedule row)=' . $auto['dep_date']['value']; }
                    if (!isset($auto['arr_date'])) { $auto['arr_date'] = ['value' => $normDate($d2), 'source' => 'ocr']; $logs[] = 'AUTO: arr_date (it schedule row)=' . $auto['arr_date']['value']; }
                    if (!isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($t1), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (it schedule row)=' . $auto['dep_time']['value']; }
                    if (!isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($t2), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (it schedule row)=' . $auto['arr_time']['value']; }
                    break;
                }
            }
        }

        // Swedish headings parser (e.g., lines with "Avgång  Ankomst  Tåg ..." followed by values line like "17.25  19.03  898 ...")
        if (!isset($auto['dep_time']) || !isset($auto['arr_time']) || !isset($auto['train_no'])) {
            $lines = preg_split('/\R/u', $text) ?: [];
            for ($i = 0; $i < count($lines); $i++) {
                $ln = trim($lines[$i]);
                if ($ln === '') { continue; }
                // Detect header row in Swedish
                if (preg_match('/\bAvg(?:ång|ang)\b.*\bAnkomst\b.*\bT[åa]g\b/iu', $ln)) {
                    $next = $lines[$i+1] ?? '';
                    if ($next !== '') {
                        if (preg_match('/\b((?:[01]?\d|2[0-3])[.:]\s*\d{2})\b\s+\b((?:[01]?\d|2[0-3])[.:]\s*\d{2})\b\s+(\d{2,6})/u', $next, $mm)) {
                            if (!isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($mm[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (sv header row)=' . $auto['dep_time']['value']; }
                            if (!isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($mm[2]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (sv header row)=' . $auto['arr_time']['value']; }
                            if (!isset($auto['train_no'])) { $auto['train_no'] = ['value' => $mm[3], 'source' => 'ocr']; $logs[] = 'AUTO: train_no (sv header row)=' . $auto['train_no']['value']; }
                            break;
                        }
                    }
                }
                // General fallback: any line with two time-like tokens then a 2+ digit number (train)
                if ((!isset($auto['dep_time']) || !isset($auto['arr_time'])) && preg_match('/\b((?:[01]?\d|2[0-3])[.:]\s*\d{2})\b\s+\b((?:[01]?\d|2[0-3])[.:]\s*\d{2})\b\s+(\d{2,6})\b/u', $ln, $mx)) {
                    if (!isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($mx[1]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (sv row generic)=' . $auto['dep_time']['value']; }
                    if (!isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($mx[2]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (sv row generic)=' . $auto['arr_time']['value']; }
                    if (!isset($auto['train_no'])) { $auto['train_no'] = ['value' => $mx[3], 'source' => 'ocr']; $logs[] = 'AUTO: train_no (sv row generic)=' . $auto['train_no']['value']; }
                }
            }
        }

        // Swedish compact ticket layout: lines "Avgång" on one line and time on the next; same for "Ankomst".
        if (!isset($auto['dep_time']) || !isset($auto['arr_time'])) {
            $lines = preg_split('/\R/u', $text) ?: [];
            for ($i = 0; $i < count($lines); $i++) {
                $ln = trim($lines[$i]);
                if ($ln === '') { continue; }
                // Avgång label with time either on same or next non-empty line
                if (!isset($auto['dep_time']) && preg_match('/\bAvg(?:ång|ang)\b/iu', $ln)) {
                    if (preg_match('/((?:[01]?\d|2[0-3])[.:h,]\s*\d{2}|\b\d{3,4}\b)/u', $ln, $mt)) {
                        $auto['dep_time'] = ['value' => $normTime($mt[1]), 'source' => 'ocr'];
                        $logs[] = 'AUTO: dep_time (sv compact same-line)=' . $auto['dep_time']['value'];
                    } else {
                        // Next non-empty line
                        for ($j = $i + 1; $j < min($i + 4, count($lines)); $j++) {
                            $nxt = trim($lines[$j]); if ($nxt === '') { continue; }
                            if (preg_match('/^\s*((?:[01]?\d|2[0-3])[.:h,]\s*\d{2}|\b\d{3,4}\b)\s*$/u', $nxt, $mm)) {
                                $auto['dep_time'] = ['value' => $normTime($mm[1]), 'source' => 'ocr'];
                                $logs[] = 'AUTO: dep_time (sv compact next-line)=' . $auto['dep_time']['value'];
                                break;
                            }
                        }
                    }
                }
                // Ankomst label with time on same or next line
                if (!isset($auto['arr_time']) && preg_match('/\bAnkomst\b/iu', $ln)) {
                    if (preg_match('/((?:[01]?\d|2[0-3])[.:h,]\s*\d{2}|\b\d{3,4}\b)/u', $ln, $mt2)) {
                        $auto['arr_time'] = ['value' => $normTime($mt2[1]), 'source' => 'ocr'];
                        $logs[] = 'AUTO: arr_time (sv compact same-line)=' . $auto['arr_time']['value'];
                    } else {
                        for ($j = $i + 1; $j < min($i + 4, count($lines)); $j++) {
                            $nxt = trim($lines[$j]); if ($nxt === '') { continue; }
                            if (preg_match('/^\s*((?:[01]?\d|2[0-3])[.:h,]\s*\d{2}|\b\d{3,4}\b)\s*$/u', $nxt, $mm2)) {
                                $auto['arr_time'] = ['value' => $normTime($mm2[1]), 'source' => 'ocr'];
                                $logs[] = 'AUTO: arr_time (sv compact next-line)=' . $auto['arr_time']['value'];
                                break;
                            }
                        }
                    }
                }
                if (isset($auto['dep_time']) && isset($auto['arr_time'])) { break; }
            }
        }

        // Swedish validity line date like "Giltig torsdag 29 maj 2025" → dep_date
        if (!isset($auto['dep_date'])) {
            if (preg_match('/\bGiltig\b[^\n\r]*?\b(\d{1,2}\s+[A-Za-zÅÄÖåäö]{3,9}\s+\d{4})\b/u', $text, $m)) {
                $auto['dep_date'] = ['value' => $normDate($m[1]), 'source' => 'ocr'];
                $logs[] = 'AUTO: dep_date (sv giltig)=' . $auto['dep_date']['value'];
            }
        }

        // Danish DSB header/table shorthand: "Afg: 17:51 Ank: 21:02"
        if (!isset($auto['dep_time']) || !isset($auto['arr_time']) || (isset($auto['dep_time']['value'], $auto['arr_time']['value']) && $auto['dep_time']['value'] === $auto['arr_time']['value'])) {
            if (preg_match('/\bAfg\s*:?\s*((?:[01]?\d|2[0-3])[.:h,]?\s*\d{2}|\b\d{3,4}\b)\b[\s\S]{0,40}?\bAnk\s*:?\s*((?:[01]?\d|2[0-3])[.:h,]?\s*\d{2}|\b\d{3,4}\b)/iu', $text, $mDA)) {
                $tDep = $normTime($mDA[1]);
                $tArr = $normTime($mDA[2]);
                if (!isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $tDep, 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (DSB Afg/Ank)=' . $tDep; }
                if (!isset($auto['arr_time']) || ($auto['arr_time']['value'] ?? '') === ($auto['dep_time']['value'] ?? '')) { $auto['arr_time'] = ['value' => $tArr, 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (DSB Afg/Ank)=' . $tArr; }
            }
        }

        // Arrival time
    $timeLabelsArr = '(?:Arr\.?\s*time|Ankomsttid|Arrival\s*time|Ankunftszeit|Heure\s*d\'arriv[ée]e|Ankomst|Arriv[ée]?\.?|Arrivo|Arr\.|Scheduled\s*Arr(?:ival)?)';
        if ($lbl) { $timeLabelsArr = '(?:' . $lbl->group('arr_time') . '|Arrival\s*time|Ankunftszeit|Ankomst|Arriv[ée]?\.?|Arrivo)'; }
    if (preg_match('/' . $timeLabelsArr . '(?::|\s)*(?:(' . $dateToken . ')\s*(?:à|at|um|kl\.?|,)?\s*)?(?:à|at|um|kl\.?|,)?\s*(' . '(?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b' . ')(?:\s*(?:Uhr|h))?/iu', $text, $m)) {
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
            if (preg_match('/\b(?:Scheduled\s*Arr(?:ival)?|Arr\.|Arriv[ée]?\.?|Arrival)\b(?:[:\s]|à|at|um|kl\.?|,)*((?:[01]?\d|2[0-3])(?:[:.h,]\s*[0-5]\d)|\b\d{3,4}\b)/iu', $text, $mS)) {
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
            if (preg_match('/\b(?:Arrivée|Arrivee|Arriv\.?|Arrival|Ankunft|Ankomst|Arr\.?)\b[\s\S]{0,120}' . $dateToken . '\s*(?:à|at|um|kl\.?|,)?\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})?|\b\d{3,4}\b)/iu', $text, $m)) {
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
            // Require minutes or compact HHMM; do NOT allow hours-only to avoid picking digits from nearby codes (e.g., 08 from 08218872)
            $timeRe = '/((?:[01]?\d|2[0-3])(?:[:.h,]\s*\d{2})|\b\d{3,4}\b)/u';
            $depName = isset($auto['dep_station']['value']) ? (string)$auto['dep_station']['value'] : '';
            $arrName = isset($auto['arr_station']['value']) ? (string)$auto['arr_station']['value'] : '';
            foreach ($lines as $ln) {
                if ($depName && $isStation($depName) && !isset($auto['dep_time']) && preg_match('/' . preg_quote($depName, '/') . '/iu', $ln) && preg_match($timeRe, $ln, $mt)) {
                    $auto['dep_time'] = ['value' => $normTime($mt[1]), 'source' => 'ocr'];
                    $logs[] = 'AUTO: dep_time (near station)=' . $auto['dep_time']['value'];
                }
                if ($arrName && $isStation($arrName) && !isset($auto['arr_time']) && preg_match('/' . preg_quote($arrName, '/') . '/iu', $ln) && preg_match($timeRe, $ln, $mt2)) {
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
                $from = $cleanStationText(trim($m[1])); $to = $cleanStationText(trim($m[3]));
                $t1 = $m[2] ?? ''; $t2 = $m[4] ?? '';
                $validFrom = $isStation($from);
                $validTo = $isStation($to);
                // Require both sides to be valid to avoid picking boilerplate
                if ($validFrom && $validTo) {
                    if (!isset($auto['dep_station'])) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (arrow paren)=' . $from; }
                    if (!isset($auto['arr_station'])) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (arrow paren)=' . $to; }
                    if ($t1 && !isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($t1), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (arrow paren)=' . $auto['dep_time']['value']; }
                    if ($t2 && !isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($t2), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (arrow paren)=' . $auto['arr_time']['value']; }
                } else {
                    if (!isset($auto['dep_station']) && $from !== '') { $logs[] = 'SKIP: dep_station (arrow paren) rejected => ' . $from; }
                    if (!isset($auto['arr_station']) && $to !== '') { $logs[] = 'SKIP: arr_station (arrow paren) rejected => ' . $to; }
                }
            } elseif (preg_match('/^\s*([\p{L}0-9 .,\'\-]+?)\s*(?:→|->|—|–|\s-\s)\s*([\p{L}0-9 .,\'\-]+?)(?:\s+((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)?))?(?:\s*[–\-]\s*((?:[01]?\d|2[0-3])(?:[:.h]\s*[0-5]\d)?))?/ium', $text, $m)) {
                $from = $cleanStationText(trim($m[1])); $to = $cleanStationText(trim($m[2]));
                $validFrom = $isStation($from);
                $validTo = $isStation($to);
                if ($validFrom && $validTo) {
                    if (!isset($auto['dep_station'])) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (arrow)=' . $from; }
                    if (!isset($auto['arr_station'])) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (arrow)=' . $to; }
                    if (!empty($m[3]) && !isset($auto['dep_time'])) { $auto['dep_time'] = ['value' => $normTime($m[3]), 'source' => 'ocr']; $logs[] = 'AUTO: dep_time (arrow)=' . $auto['dep_time']['value']; }
                    if (!empty($m[4]) && !isset($auto['arr_time'])) { $auto['arr_time'] = ['value' => $normTime($m[4]), 'source' => 'ocr']; $logs[] = 'AUTO: arr_time (arrow)=' . $auto['arr_time']['value']; }
                } else {
                    if (!isset($auto['dep_station']) && $from !== '') { $logs[] = 'SKIP: dep_station (arrow) rejected => ' . $from; }
                    if (!isset($auto['arr_station']) && $to !== '') { $logs[] = 'SKIP: arr_station (arrow) rejected => ' . $to; }
                }
            }
            // Fallback: "De X à Y" style without arrow
            if ((!isset($auto['dep_station']) || !isset($auto['arr_station'])) && preg_match('/\b(?:De|Da|Von|Fra|Från)\b[:\s]*([^\n\r]+?)\s+\b(?:À|A|Til|Till|Nach|Vers)\b[:\s]*([^\n\r]+)/iu', $text, $m2)) {
                $from = trim($m2[1]); $to = trim($m2[2]);
                if ($from && !isset($auto['dep_station']) && !$isCodeLike($from)) { $auto['dep_station'] = ['value' => $from, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (de/à)=' . $from; }
                if ($to && !isset($auto['arr_station']) && !$isCodeLike($to)) { $auto['arr_station'] = ['value' => $to, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (de/à)=' . $to; }
            }
            // French SNCF style lines: "Départ ... de X" and "Arriv. ... à Y"
            if (!isset($auto['dep_station']['value']) || !isset($auto['arr_station']['value'])) {
                $lines = preg_split('/\R/u', $text) ?: [];
                foreach ($lines as $ln) {
                    if (!isset($auto['dep_station']['value']) && preg_match('/\b(?:Départ|Depart|Dep\.?)\b[^\r\n]{0,120}?\bde\s+([^\r\n]+)/iu', $ln, $mds)) {
                        $cand = $cleanStationText($mds[1]);
                        if ($cand !== '' && $isStation($cand)) { $auto['dep_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: dep_station (fr de)=' . $cand; }
                        if (!isset($auto['dep_time']) && preg_match('/\b((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})|\b\d{3,4}\b)\b/iu', $ln, $mt)) {
                            $auto['dep_time'] = ['value' => $normTime($mt[1]), 'source' => 'ocr'];
                            $logs[] = 'AUTO: dep_time (fr line)=' . $auto['dep_time']['value'];
                        }
                    }
                    if (!isset($auto['arr_station']['value']) && preg_match('/\bArriv[ée]?\.?\b[^\r\n]{0,120}?\bà\s+([^\r\n]+)/iu', $ln, $mas)) {
                        $cand = $cleanStationText($mas[1]);
                        if ($cand !== '' && $isStation($cand)) { $auto['arr_station'] = ['value' => $cand, 'source' => 'ocr']; $logs[] = 'AUTO: arr_station (fr à)=' . $cand; }
                        if (!isset($auto['arr_time']) && preg_match('/\b((?:[01]?\d|2[0-3])(?:[:.h]\s*\d{1,2})|\b\d{3,4}\b)\b/iu', $ln, $mt2)) {
                            $auto['arr_time'] = ['value' => $normTime($mt2[1]), 'source' => 'ocr'];
                            $logs[] = 'AUTO: arr_time (fr line)=' . $auto['arr_time']['value'];
                        }
                    }
                    if (isset($auto['dep_station']['value']) && isset($auto['arr_station']['value'])) { break; }
                }
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

        // Final sanitization: drop low-quality station names (e.g., single-letter like "T") so downstream LLM can fill
        foreach ([[ 'key' => 'dep_station', 'label' => 'dep_station' ], [ 'key' => 'arr_station', 'label' => 'arr_station' ]] as $entry) {
            $k = $entry['key'];
            if (!empty($auto[$k]['value']) && is_string($auto[$k]['value'])) {
                $val = trim((string)$auto[$k]['value']);
                $letters = $letterLen($val);
                $hasLetter = (bool)preg_match('/\p{L}/u', $val);
                if (!$hasLetter || $letters < 3 || $isCodeLike($val) || $looksLikeBoilerplate($val)) {
                    unset($auto[$k]);
                    $logs[] = 'DROP: ' . $entry['label'] . ' low-quality => ' . $val;
                }
            }
        }

        // Train no/category: prefer labeled capture first
        // Train no/category: avoid capturing French 'Classe 2' or 'Classe' rows; require at least 2 digits to avoid 'InterCity 2'
        if (preg_match('/(?:Train\s*(?:no\.|number|category)?|Tognummer|Treno|Tåg)[:\s]*([A-ZÄÖÜÅÆØ]{1,12}\s*\d{2,6}[A-Z]?)/iu', $text, $m) && !preg_match('/\bclasse\b\s*\d+/iu', $text)) {
            $auto['train_no'] = ['value' => trim($m[1]), 'source' => 'ocr'];
            $logs[] = 'AUTO: train_no=' . $auto['train_no']['value'];
        } elseif (preg_match('/\b(?:TGV|ICE|IC|EC|EN|TER|AVE|REG|RE|RB|IR|RJX?|SJ|DSB|SBB|ÖBB|OEBB|NS|SNCF|CFL|CP|PKP|ZSSK)\s*\d{2,6}[A-Z]?\b/u', $text, $m)) {
            $auto['train_no'] = ['value' => trim($m[0]), 'source' => 'ocr'];
            $logs[] = 'AUTO: train_no=' . $auto['train_no']['value'];
        }

        // Ticket/Booking ref (PNR) — prefer lettered 6-8 char codes (e.g., SNCF Dossier QFKDQB) over long numeric receipts
        $ticketCandidate = null;
        // Common English/Danish labels
        if (preg_match('/\b(?:PNR|Booking\s*Reference|Billetnummer|Ticket\s*(?:no\.|number|reference))\b[:\s]*([A-Z0-9\-]+)/iu', $text, $m)) {
            $ticketCandidate = $m[1];
            $logs[] = 'CAND: ticket_no (generic)=' . $ticketCandidate;
        }
        // French-specific: Dossier / Référence dossier
        if (preg_match('/\b(?:Dossier|R[ée]f[ée]rence\s*dossier)\b\s*[:#]?\s*([A-Z0-9]{6,8})\b/iu', $text, $mf)) {
            $dossier = strtoupper($mf[1]);
            // Prefer Dossier if it contains at least one letter
            if ($dossier !== '' && preg_match('/[A-Z]/', $dossier)) {
                $ticketCandidate = $dossier;
                $logs[] = 'CAND: ticket_no (dossier)=' . $dossier;
            }
        }
        if ($ticketCandidate !== null) {
            $auto['ticket_no'] = ['value' => $ticketCandidate, 'source' => 'ocr'];
            $logs[] = 'AUTO: ticket_no=' . $ticketCandidate;
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
                // If operator still unknown, infer from product -> operator mapping
                if (empty($auto['operator']['value'])) {
                    $byProd = $catalog->findOperatorByProduct($prod);
                    if ($byProd) {
                        $auto['operator'] = ['value' => $byProd['name'], 'source' => 'ocr'];
                        $auto['operator_country'] = ['value' => $byProd['country'], 'source' => 'ocr'];
                        $logs[] = 'AUTO: operator inferred from product=' . $prod . ' => ' . $byProd['name'] . ' (' . $byProd['country'] . ')';
                    }
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }

        // Final sanity: drop invalid time tokens that aren't proper HH:MM (e.g., '0072')
        foreach (['dep_time','arr_time','actual_dep_time','actual_arr_time'] as $tk) {
            if (!empty($auto[$tk]['value']) && is_string($auto[$tk]['value'])) {
                if (!$isValidTime((string)$auto[$tk]['value'])) {
                    $logs[] = 'DROP: ' . $tk . ' invalid => ' . $auto[$tk]['value'];
                    unset($auto[$tk]);
                }
            }
        }

        return ['auto' => $auto, 'logs' => $logs];
    }
}
