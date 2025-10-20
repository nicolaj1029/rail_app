<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Filesystem\File;
use Cake\Utility\Text;
use App\Service\ExemptionProfileBuilder;
use App\Service\TrainDataService;
use Cake\Core\Configure;

class UploadController extends AppController
{
    public function index(): void
    {
        // Renders templates/Upload/index.php
    }

    public function analyze(): void
    {
        $this->request->allowMethod(['post']);
        $file = $this->request->getData('ticket');
        $extJourneyRaw = $this->request->getData('journey'); // optional JSON pasted (string)
        $manualDelayInput = $this->request->getData('manual_delay_minutes');
        $manualDelay = null;
        if ($manualDelayInput !== null && $manualDelayInput !== '') {
            $manualDelay = (int)$manualDelayInput;
            if ($manualDelay < 0) { $manualDelay = 0; }
        }

        $journey = [];
        $errors = [];
        $meta = [];
        $ocrLogs = [];
    $ocrUsed = false;
        $ocrAutoCount = 0;
    $filenameHints = [];
    $textHints = [];

        // If user pasted JSON journey, use that (developer shortcut)
        if (is_string($extJourneyRaw) && trim($extJourneyRaw) !== '') {
            $decoded = json_decode($extJourneyRaw, true);
            if (is_array($decoded)) {
                $journey = $decoded;
            } else {
                $errors[] = 'Ugyldigt JSON i Journey-feltet.';
            }
        }

        // Handle file upload (image/pdf/pkpass) – store only; real parsing can be added later
        $savedPath = null;
        if ($file && is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = $file['tmp_name'];
            $name = $file['name'] ?? ('ticket_' . Text::uuid());
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$name) ?: ('ticket_' . Text::uuid());
            $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($destDir)) { mkdir($destDir, 0775, true); }
            $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
            if (@move_uploaded_file($tmp, $dest)) {
                $savedPath = $dest;
                // Try simple OCR/text extraction for PDFs/TXT to feed Art. 9 auto hooks
                $ext = strtolower((string)pathinfo($savedPath, PATHINFO_EXTENSION));
                $textBlob = '';
                try {
                    if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($savedPath);
                        $textBlob = $pdf->getText() ?? '';
                    } elseif (in_array($ext, ['txt','text'], true)) {
                        $textBlob = (string)file_get_contents($savedPath);
                    } elseif (in_array($ext, ['png','jpg','jpeg'], true)) {
                        // Try to find a mock TXT with same basename under mocks/tests/fixtures for testing
                        $base = (string)pathinfo($safe, PATHINFO_FILENAME);
                        $mockDir = ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
                        $candidates = [
                            $mockDir . $base . '.txt',
                            $mockDir . strtolower($base) . '.txt',
                            $mockDir . str_replace('-', '_', strtolower($base)) . '.txt',
                        ];
                        foreach ($candidates as $cand) {
                            if (is_file($cand)) { $textBlob = (string)file_get_contents($cand); $ocrLogs[] = 'AUTO: indlæst mock TXT: ' . basename($cand); break; }
                        }
                    }
                } catch (\Throwable $e) {
                    $textBlob = '';
                }
                if ($textBlob !== '') {
                    $ocrUsed = true;
                    $map = (new \App\Service\OcrHeuristicsMapper())->mapText($textBlob);
                    foreach (($map['auto'] ?? []) as $k => $v) { $meta['_auto'][$k] = $v; }
                    $ocrLogs = array_merge($ocrLogs, $map['logs'] ?? []);
                    $ocrAutoCount = count((array)($map['auto'] ?? []));
                    // Derive coarse country/operator/product hints from OCR text
                    $textHints = $this->deriveJourneyFromText($textBlob);
                }
                // Heuristics from filename for images (png/jpg) or when OCR is not available
                if (empty($journey)) {
                    $fname = strtolower((string)pathinfo($safe, PATHINFO_FILENAME));
                    $fh = $this->deriveJourneyFromFilename($fname);
                    if (!empty($fh['journey'])) { $journey = $fh['journey']; }
                    if (!empty($fh['auto'])) {
                        foreach ($fh['auto'] as $k => $v) { $meta['_auto'][$k] = $v; }
                        $ocrAutoCount += count($fh['auto']);
                    }
                    if (!empty($fh['logs'])) { $ocrLogs = array_merge($ocrLogs, $fh['logs']); }
                    if (!empty($fh['hints'])) { $filenameHints = $fh['hints']; }
                }
                // If we already have a journey but OCR-derived hints indicate a different country, prefer OCR
                if (!empty($journey) && !empty($textHints['country'])) {
                    $seg0 = $journey['segments'][0] ?? [];
                    $jc = strtoupper((string)($seg0['country'] ?? ''));
                    $tc = strtoupper((string)$textHints['country']);
                    if ($tc !== '' && $tc !== $jc) {
                        $journey['segments'][0]['country'] = $textHints['country'];
                        $journey['country']['value'] = $textHints['country'];
                        if (!empty($textHints['is_long_domestic'])) { $journey['is_long_domestic'] = true; }
                        $ocrLogs[] = 'AUTO: land korrigeret fra OCR-tekst: ' . $tc;
                    }
                }
            } else {
                $errors[] = 'Kunne ikke gemme den uploadede fil';
            }
        }

        // Minimal placeholder: if no journey provided, create a single-segment journey using a heuristic country hint
        if (empty($journey)) {
            // Prefer OCR-derived hints over filename, then user-specified, else EU
            $countryHint = (string)($textHints['country'] ?? $filenameHints['country'] ?? $this->request->getData('country') ?? 'EU');
            $isLongDomestic = (bool)($textHints['is_long_domestic'] ?? $filenameHints['is_long_domestic'] ?? false);
            $journey = [
                'segments' => [[ 'country' => $countryHint ]],
                'is_international_inside_eu' => false,
                'is_international_beyond_eu' => false,
                'is_long_domestic' => $isLongDomestic,
                'country' => ['value' => $countryHint],
            ];
            if (!empty($textHints) || !empty($filenameHints)) { $ocrLogs[] = 'AUTO: fallback med land fra hints: ' . $countryHint; }
        }

        // Compute exemptions profile (focus på Art. 12 til at starte med)
        $builder = new ExemptionProfileBuilder();
        $profile = $builder->build($journey);
        $art12_applies = (bool)($profile['articles']['art12'] ?? true);

        // Run evaluators for a quick end-to-end summary
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, []);
        $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta);
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => false]);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, []);

        // Build a minimal ClaimInput from the journey (best-effort until OCR/mocks er koblet på)
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? 'EU'));
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $currency = 'EUR';
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        $price = 0.0;
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }
        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true,
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }
        // Optionally fetch live delay minutes if enabled
        $liveDelay = null;
        try {
            $trainSvc = new TrainDataService();
            $liveDelay = $trainSvc->getDelayMinutes($journey);
        } catch (\Throwable $e) {
            $liveDelay = null;
        }
        
        // Determine delay minutes: manual > live > computed
        $delayMins = $this->computeDelayFromJourney($journey);
        $delaySource = 'computed_from_journey';
        if (is_int($liveDelay)) { $delayMins = $liveDelay; $delaySource = 'live_api'; }
        if (is_int($manualDelay)) { $delayMins = $manualDelay; $delaySource = 'manual_override'; }

        $claimInput = [
            'country_code' => $country,
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [ 'through_ticket' => true, 'legs' => $legs ],
            'disruption' => [
                'delay_minutes_final' => $delayMins,
                'notified_before_purchase' => false,
                'extraordinary' => false,
                'self_inflicted' => false,
            ],
            'choices' => [ 'wants_refund' => false, 'wants_reroute_same_soonest' => false, 'wants_reroute_later_choice' => false ],
            'expenses' => [ 'meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0 ],
            'already_refunded' => 0,
        ];
        $claim = (new \App\Service\ClaimCalculator())->calculate($claimInput);

        // For result view context
        $this->set(compact(
            'profile', 'art12_applies', 'art12', 'art9', 'refund', 'refusion', 'claim', 'savedPath', 'errors',
            'liveDelay', 'ocrUsed', 'ocrLogs', 'ocrAutoCount', 'journey', 'legs', 'price', 'currency', 'manualDelay', 'delayMins', 'delaySource'
        ));
        $this->viewBuilder()->setTemplate('result');
    }

    /** Compute delay minutes from a Journey-like array */
    private function computeDelayFromJourney(array $journey): int
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1)/60)); }
        }
        // Fallback fields
        $depDate = (string)($journey['depDate']['value'] ?? '');
        $sched = (string)($journey['schedArrTime']['value'] ?? '');
        $act = (string)($journey['actualArrTime']['value'] ?? '');
        if ($depDate && $sched && $act) {
            $t1 = strtotime($depDate . 'T' . $sched . ':00');
            $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1)/60)); }
        }
        return 0;
    }

    /**
     * Derive basic journey and Art.9 AUTO hints from filename patterns like 'db_ice_berlin_munich'.
    * @return array{journey?:array, auto?:array, logs?:array, hints?:array}
     */
    private function deriveJourneyFromFilename(string $fname): array
    {
        $logs = [];
        $auto = [];
        $segments = [];
        $country = null; $operator = null; $product = null;
        $orig = $fname;
        $fname = $this->normalizeMojibake($fname);
        $fname = str_replace(['__','-'], '_', strtolower($fname));
    if (str_starts_with($fname, 'sncf_')) { $country = 'FR'; $operator = 'SNCF'; }
    if (str_starts_with($fname, 'db_')) { $country = 'DE'; $operator = 'DB'; }
    if (str_starts_with($fname, 'dsb_')) { $country = 'DK'; $operator = 'DSB'; }
    if (str_starts_with($fname, 'sj_')) { $country = 'SE'; $operator = 'SJ'; }
    if (str_starts_with($fname, 'zssk_')) { $country = 'SK'; $operator = 'ZSSK'; }
    if (str_starts_with($fname, 'pkp_')) { $country = 'PL'; $operator = 'PKP Intercity'; }

        if (str_contains($fname, '_tgv_') || str_ends_with($fname, '_tgv')) { $product = 'TGV'; }
        elseif (str_contains($fname, '_ice_')) { $product = 'ICE'; }
        elseif (str_contains($fname, '_ic_') || str_ends_with($fname, '_ic')) { $product = 'IC'; }
        elseif (str_contains($fname, '_reg_')) { $product = 'REG'; }
        elseif (preg_match('/(^|_)r(_|$)/', $fname)) { $product = 'R'; }
        elseif (str_contains($fname, '_re_') || str_ends_with($fname, '_re')) { $product = 'RE'; }

        // City → country hints for when operator/product not present in filename
        $cityMap = [
            // FR
            'paris' => 'FR', 'lyon' => 'FR', 'lille' => 'FR', 'bordeaux' => 'FR',
            // DE
            'berlin' => 'DE', 'munich' => 'DE', 'muenchen' => 'DE', 'münchen' => 'DE', 'hamburg' => 'DE',
            // DK
            'kobenhavn' => 'DK', 'københavn' => 'DK', 'odense' => 'DK', 'aarhus' => 'DK', 'århus' => 'DK',
            // SE
            'stockholm' => 'SE', 'gothenburg' => 'SE', 'goteborg' => 'SE', 'göteborg' => 'SE',
            // SK
            'bratislava' => 'SK', 'kosice' => 'SK', 'košice' => 'SK',
            // PL
            'warszawa' => 'PL', 'gdansk' => 'PL', 'gdańsk' => 'PL',
        ];
        $parts = preg_split('/[_\s]+/', $fname) ?: [];
        $cities = array_values(array_filter($parts, fn($p) => isset($cityMap[$p])));
        if ($country === null && !empty($cities)) {
            $country = $cityMap[$cities[0]];
        }
        // Infer operator from country + long-distance product
        if ($operator === null && $country) {
            if ($country === 'FR' && $product === 'TGV') { $operator = 'SNCF'; }
            if ($country === 'DE' && in_array($product, ['ICE','IC'], true)) { $operator = 'DB'; }
            if ($country === 'DK' && in_array($product, ['IC','RE'], true)) { $operator = 'DSB'; }
        }

        // From/To: last two tokens often city names
        if (count($parts) >= 2) {
            $from = ucfirst($parts[count($parts)-2] ?? '');
            $to = ucfirst($parts[count($parts)-1] ?? '');
            $segments[] = [
                'from' => str_replace('-', ' ', $from),
                'to' => str_replace('-', ' ', $to),
                'country' => $country ?? 'EU',
                'operator' => $operator,
                'trainCategory' => $product,
            ];
        }

        // Long-distance if product suggests it
        $longDistance = in_array($product, ['TGV','ICE','IC'], true);
        // If two known cities from the same country and product absent, prefer long-domestic for typical intercity routes
        if (!$longDistance && count($cities) >= 2) {
            $c0 = $cityMap[$cities[0]]; $c1 = $cityMap[$cities[1]];
            if ($c0 === $c1 && in_array($c0, ['FR','DE','DK','SE','PL','SK'], true)) {
                $longDistance = true;
            }
        }
        $journey = [];
        if (!empty($segments)) {
            $journey = [
                'segments' => $segments,
                'is_international_inside_eu' => false,
                'is_international_beyond_eu' => false,
                'is_long_domestic' => $longDistance,
                'country' => ['value' => $country ?? 'EU'],
            ];
            $logs[] = 'AUTO: journey fra filnavn (' . $orig . ')';
        }

        // Auto hooks: train specificity and fare flex from product hints (weak)
        if ($product) { $auto['train_specificity'] = ['value' => 'Kun specifikt tog', 'source' => 'filename']; }
        return [
            'journey' => $journey,
            'auto' => $auto,
            'logs' => $logs,
            'hints' => [
                'country' => $country,
                'operator' => $operator,
                'product' => $product,
                'is_long_domestic' => $longDistance,
            ],
        ];
    }

    /**
     * Derive coarse hints from OCR text: country/operator/product and long-distance.
     * @return array{country?:string, operator?:string, product?:string, is_long_domestic?:bool}
     */
    private function deriveJourneyFromText(string $text): array
    {
        $t = $this->normalizeMojibake($text);
        $t = mb_strtolower($t, 'UTF-8');
        $country = null; $operator = null; $product = null; $long = false;
        // Operators and products
        if (preg_match('/\bdeutsche\s*bahn\b|\bdb\b/u', $t)) { $operator = 'DB'; $country = $country ?? 'DE'; }
        if (preg_match('/\bsncf\b|\bter\b|\btgv\b/u', $t)) { $operator = $operator ?? 'SNCF'; $country = $country ?? 'FR'; }
        if (preg_match('/\bdsb\b/u', $t)) { $operator = 'DSB'; $country = $country ?? 'DK'; }
        if (preg_match('/\bsj\b/u', $t)) { $operator = $operator ?? 'SJ'; $country = $country ?? 'SE'; }

        if (preg_match('/\bice\b/u', $t)) { $product = 'ICE'; $country = $country ?? 'DE'; }
        elseif (preg_match('/\b(ic|intercity)\b/u', $t)) { $product = 'IC'; }
        elseif (preg_match('/\btgv\b/u', $t)) { $product = 'TGV'; $country = $country ?? 'FR'; }
        elseif (preg_match('/\bre(g|gio)\b|\bregional\b/u', $t)) { $product = $product ?? 'REG'; }

        // City hints
        $cityMap = [
            'paris' => 'FR', 'lyon' => 'FR', 'lille' => 'FR', 'bordeaux' => 'FR',
            'berlin' => 'DE', 'munich' => 'DE', 'muenchen' => 'DE', 'münchen' => 'DE', 'munchen' => 'DE', 'hamburg' => 'DE', 'frankfurt' => 'DE', 'köln' => 'DE', 'koln' => 'DE', 'cologne' => 'DE', 'düsseldorf' => 'DE', 'duesseldorf' => 'DE', 'dusseldorf' => 'DE', 'stuttgart' => 'DE',
            'kobenhavn' => 'DK', 'københavn' => 'DK', 'odense' => 'DK', 'aarhus' => 'DK', 'århus' => 'DK',
            'stockholm' => 'SE', 'gothenburg' => 'SE', 'goteborg' => 'SE', 'göteborg' => 'SE',
            'bratislava' => 'SK', 'kosice' => 'SK', 'košice' => 'SK',
            'warszawa' => 'PL', 'gdansk' => 'PL', 'gdańsk' => 'PL',
        ];
        $foundCities = [];
        foreach ($cityMap as $city => $cc) {
            if (preg_match('/\b' . preg_quote($city, '/') . '\b/u', $t)) { $foundCities[] = [$city, $cc]; }
        }
        if ($country === null && !empty($foundCities)) { $country = $foundCities[0][1]; }

        // Long-distance heuristics
        if (in_array($product, ['ICE','IC','TGV'], true)) { $long = true; }
        if (!$long && count($foundCities) >= 2) {
            if ($foundCities[0][1] === $foundCities[1][1]) { $long = true; }
        }

        return array_filter([
            'country' => $country,
            'operator' => $operator,
            'product' => $product,
            'is_long_domestic' => $long ?: null,
        ], fn($v) => $v !== null && $v !== '');
    }

    /** Normalize common UTF-8 mojibake to real characters (e.g., MÃ¼nchen -> München). */
    private function normalizeMojibake(string $s): string
    {
        // Quick fixes for frequent sequences
        $map = [
            'Ã¼' => 'ü', 'Ã¶' => 'ö', 'Ã¤' => 'ä', 'ÃŸ' => 'ß',
            'Ãœ' => 'Ü', 'Ã–' => 'Ö', 'Ã„' => 'Ä',
            'Ã¥' => 'å', 'Ã¸' => 'ø', 'Ã¦' => 'æ',
            'Ã…' => 'Å', 'Ã˜' => 'Ø', 'Ã†' => 'Æ',
            'Ã©' => 'é', 'Ãè' => 'è', 'Ã¡' => 'á', 'Ã ' => 'à', 'Ã³' => 'ó', 'Ãº' => 'ú', 'Ã±' => 'ñ',
            'â€“' => '–', 'â€”' => '—', 'â€˜' => '‘', 'â€™' => '’', 'â€œ' => '“', 'â€ 9d' => '”', 'â€¢' => '•', 'â€¦' => '…',
            'Â ' => ' ',
            // Specific full words often seen
            'MÃ¼nchen' => 'München', 'KÃ¶ln' => 'Köln', 'DÃ¼sseldorf' => 'Düsseldorf',
            'GÃ¶teborg' => 'Göteborg', 'KÃ¸benhavn' => 'København', 'Aarhus' => 'Aarhus',
        ];
        // Replace case-sensitively first
        $s = strtr($s, $map);
        // Then handle any remaining generic uppercase variants
        $s = strtr($s, [ 'Ã' => 'Å', 'Â' => '' ]);
        return $s;
    }
}
