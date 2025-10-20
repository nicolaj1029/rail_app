<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class DemoController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Scan generated mock tickets under mocks/tests/fixtures and run full analysis on each.
     * Optional query: baseDir to override directory.
     */
    public function mockTickets(): void
    {
        $baseDir = (string)($this->request->getQuery('baseDir') ?? (ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'));
        $withRne = (string)($this->request->getQuery('withRne') ?? '0') === '1';
        if (!is_dir($baseDir)) {
            $this->set(['error' => 'not_found', 'baseDir' => $baseDir]);
            $this->viewBuilder()->setOption('serialize', ['error','baseDir']);
            return;
        }

        // Group files by basename (without extension)
        $entries = scandir($baseDir) ?: [];
        $groups = [];
        foreach ($entries as $fn) {
            if ($fn === '.' || $fn === '..') { continue; }
            $ext = strtolower((string)pathinfo($fn, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','png','txt'], true)) { continue; }
            $base = (string)pathinfo($fn, PATHINFO_FILENAME);
            if (!isset($groups[$base])) { $groups[$base] = ['pdf' => null, 'png' => null, 'txt' => null]; }
            $groups[$base][$ext] = $baseDir . DIRECTORY_SEPARATOR . $fn;
        }

        $results = [];
        foreach ($groups as $base => $media) {
            // Parse TXT if present; otherwise attempt heuristics from filename
            $txt = '';
            if (!empty($media['txt']) && is_file($media['txt'])) {
                $txt = (string)file_get_contents((string)$media['txt']);
            }

            $parsed = $this->parseMockText($base, $txt);
            $journey = (array)($parsed['journey'] ?? []);
            // Accept both snake_case (preferred) and camelCase from parser
            $art12Meta = (array)($parsed['art12_meta'] ?? ($parsed['art12Meta'] ?? []));
            $art9Meta = (array)($parsed['art9_meta'] ?? ($parsed['art9Meta'] ?? []));
            $refusionMeta = (array)($parsed['refusion_meta'] ?? ($parsed['refusionMeta'] ?? []));
            $compute = (array)($parsed['compute'] ?? []);

            // Profile and evaluations
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);

            // Prepare auto values for Art.9 from OCR text and overlaps; RNE added below if available
            $auto = [];
            foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
            }
            if ($txt !== '') {
                if (preg_match('/\bCIV\b/i', $txt) || stripos($txt, 'conditions of carriage') !== false) {
                    $auto['civ_marking_present'] = ['value' => 'Ja', 'source' => 'ticket_ocr'];
                }
                $trainLine = $this->matchOne($txt, '/^Train:\s*([^\r\n]+)/mi');
                if ($trainLine && preg_match('/\d+/', $trainLine)) {
                    $auto['train_specificity'] = ['value' => 'Kun specifikt tog', 'source' => 'ticket_ocr'];
                }
            }
            $comp = $this->computeCompensationPreview($journey, $compute);
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => (bool)($compute['refundAlready'] ?? false)]);
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            $scenario = [
                'journey' => $journey,
                'refusion_meta' => $refusionMeta,
                'compute' => $compute,
            ];
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($this->mapScenarioToClaimInput($scenario));

            $rne = null;
            if ($withRne) {
                // naive extraction for demo: use product+number or PNR as trainId and schedDep date
                $trainId = $this->matchOne($txt, '/^Train:\s*([^\r\n]+)/mi') ?: ($pnr ?? '');
                $dateIso = $this->dateToIso($this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/mi'));
                if ($trainId && $dateIso) {
                    $rne = (new \App\Service\RneClient())->realtime($trainId, substr($dateIso, 0, 10));
                } else {
                    $rne = [];
                }
            }
            // Add conservative RNE auto hints (if data fetched)
            if (!empty($rne)) {
                $auto['station_board_updates'] = ['value' => 'Ja', 'source' => 'rne'];
                // Bike AUTO mapping (Bilag II I.5) — tolerant to common key variants
                $valOf = function($v): string {
                    $s = strtolower(trim((string)$v));
                    if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'ja') return 'Ja';
                    if ($s === '0' || $s === 'false' || $s === 'no' || $s === 'nej') return 'Nej';
                    return 'unknown';
                };
                // bikesAllowed → if false, set reservation type to 'Ikke muligt'
                $bikesAllowed = $rne['bikesAllowed'] ?? ($rne['bike_allowed'] ?? null);
                if ($bikesAllowed !== null) {
                    $ba = $valOf($bikesAllowed);
                    if ($ba === 'Nej') {
                        $auto['bike_reservation_type'] = ['value' => 'Ikke muligt', 'source' => 'rne'];
                    }
                }
                // bikeReservationRequired → bike_res_required Ja/Nej
                $bikeReq = $rne['bikeReservationRequired'] ?? ($rne['bike_res_required'] ?? null);
                if ($bikeReq !== null) {
                    $auto['bike_res_required'] = ['value' => $valOf($bikeReq), 'source' => 'rne'];
                }
            }

            if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

            $results[] = [
                'id' => $base,
                'media' => [
                    'pdf' => $media['pdf'],
                    'png' => $media['png'],
                    'txt' => $media['txt'],
                ],
                'rne' => $rne,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results, 'count' => count($results), 'baseDir' => $baseDir]);
        $this->viewBuilder()->setOption('serialize', ['results','count','baseDir']);
    }

    /** Generate a set of mock tickets (TXT + PNG + PDF) with QR/barcode-like graphics */
    public function generateMocks(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $baseDir = (string)($this->request->getQuery('baseDir') ?? (ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'));
        if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }

        $tickets = [
            ['base' => 'sncf_tgv_paris_lyon', 'pnr' => 'ABC123', 'operator' => 'SNCF', 'product' => 'TGV', 'train' => 'TGV 1234', 'from' => 'Paris Gare de Lyon', 'to' => 'Lyon Part-Dieu', 'date' => '12/11/2025 08:10', 'schedArr' => '10:10', 'notes' => ['CIV','Fastest','Non-refundable']],
            ['base' => 'db_ice_berlin_munich', 'pnr' => 'ZX9-88Q', 'operator' => 'DB', 'product' => 'ICE', 'train' => 'ICE 789', 'from' => 'Berlin Hbf', 'to' => 'München Hbf', 'date' => '18/11/2025 09:05', 'schedArr' => '13:05', 'notes' => ['Flexible','Complaints']],
            ['base' => 'dsb_re_copenhagen_odense', 'pnr' => 'DSB-55K7', 'operator' => 'DSB', 'product' => 'RE', 'train' => 'RE 123', 'from' => 'København H', 'to' => 'Odense', 'date' => '20/11/2025 14:22', 'schedArr' => '16:12', 'notes' => ['Bike reservation required','Complaints']],
            ['base' => 'sj_reg_stockholm_goteborg', 'pnr' => 'SJ-7788', 'operator' => 'SJ', 'product' => 'REG', 'train' => 'REG 456', 'from' => 'Stockholm Central', 'to' => 'Göteborg C', 'date' => '22/11/2025 07:30', 'schedArr' => '10:45', 'notes' => ['Alternatives shown','MCT ok']],
            ['base' => 'zssk_r_bratislava_kosice', 'pnr' => 'SK-3321', 'operator' => 'ZSSK', 'product' => 'R', 'train' => 'R 610', 'from' => 'Bratislava hl.st.', 'to' => 'Košice', 'date' => '25/11/2025 15:20', 'schedArr' => '21:50', 'notes' => ['CIV']],
            ['base' => 'pkp_ic_warszawa_gdansk', 'pnr' => 'PL-IC-9011', 'operator' => 'PKP Intercity', 'product' => 'IC', 'train' => 'IC 3502', 'from' => 'Warszawa Centralna', 'to' => 'Gdańsk Główny', 'date' => '27/11/2025 11:55', 'schedArr' => '14:55', 'notes' => ['Bike reservation required','Complaints']],
            ['base' => 'sncf_tgv_lille_paris', 'pnr' => 'LIL-PAR-447', 'operator' => 'SNCF', 'product' => 'TGV', 'train' => 'TGV 8123', 'from' => 'Lille Europe', 'to' => 'Paris Nord', 'date' => '29/11/2025 08:05', 'schedArr' => '09:04', 'notes' => ['CIV','Fastest','Non-refundable']],
        ];

        $created = [];
        foreach ($tickets as $t) {
            $txt = $this->renderTxt($t);
            $txtPath = $baseDir . DIRECTORY_SEPARATOR . $t['base'] . '.txt';
            file_put_contents($txtPath, $txt);

            $pngPath = $baseDir . DIRECTORY_SEPARATOR . $t['base'] . '.png';
            $this->renderPngTicket($t, $pngPath);

            $pdfPath = $baseDir . DIRECTORY_SEPARATOR . $t['base'] . '.pdf';
            $this->renderPdfTicket($t, $pdfPath, $pngPath);

            $created[] = ['base' => $t['base'], 'txt' => $txtPath, 'png' => $pngPath, 'pdf' => $pdfPath];
        }

        $this->set(['created' => $created, 'baseDir' => $baseDir]);
        $this->viewBuilder()->setOption('serialize', ['created','baseDir']);
    }

    private function renderTxt(array $t): string
    {
        $lines = [
            'PNR: ' . $t['pnr'],
            'Operator: ' . $t['operator'],
            'Train: ' . $t['train'],
            'From: ' . $t['from'],
            'To: ' . $t['to'],
            'Date: ' . $t['date'],
            'Scheduled Arr: ' . $t['schedArr'],
        ];
        foreach ((array)($t['notes'] ?? []) as $n) { $lines[] = $n; }
        return implode("\n", $lines) . "\n";
    }

    private function renderPngTicket(array $t, string $path): void
    {
        if (!extension_loaded('gd')) { $this->renderPngFallback($path); return; }
        $w = 1200; $h = 600;
        $im = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        $gray = imagecolorallocate($im, 220, 220, 220);
        imagefilledrectangle($im, 0, 0, $w, $h, $white);

        // Prefer TTF rendering (UTF-8 capable) if FreeType is available and font file exists
        $fontPath = ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'DejaVuSans.ttf';
        $canTtf = function_exists('imagettftext') && is_file($fontPath);

        $drawText = function($text, int $x, int $y, int $size = 16) use ($im, $black, $canTtf, $fontPath) {
            if ($canTtf) {
                // Render as UTF-8 with TrueType
                imagettftext($im, $size, 0, $x, $y + (int)($size*0.8), $black, $fontPath, $text);
            } else {
                // Fallback: transliterate to ASCII and draw with bitmap font
                $ascii = $this->utf8ToAscii($text);
                imagestring($im, max(1, min(5, (int)round($size/6))), $x, $y, $ascii, $black);
            }
        };

        // Header and details
        $drawText($t['operator'] . ' ' . $t['product'] . ' – ' . $t['train'], 20, 20, 24);
        $drawText('PNR: ' . $t['pnr'], 20, 60, 18);
        $drawText('From: ' . $t['from'], 20, 90, 18);
        $drawText('To:   ' . $t['to'], 20, 120, 18);
        $drawText('Date: ' . $t['date'] . '  |  Scheduled Arr: ' . $t['schedArr'], 20, 150, 18);

        // QR-like block (not scannable, but structured) – right side
        $qrX = 900; $qrY = 60; $qrSize = 250; $cells = 29;
        imagefilledrectangle($im, $qrX-10, $qrY-10, $qrX+$qrSize+10, $qrY+$qrSize+10, $gray);
        $this->drawPseudoQr($im, $qrX, $qrY, $qrSize, $cells, $t['pnr']);

        // Simple barcode-like bars under header
        $this->drawPseudoBarcode($im, 20, 200, 800, 70, $t['pnr']);

        imagepng($im, $path);
        imagedestroy($im);
    }

    private function drawPseudoQr($im, int $x, int $y, int $size, int $cells, string $seed): void
    {
        $black = imagecolorallocate($im, 0, 0, 0);
        $white = imagecolorallocate($im, 255, 255, 255);
        $cell = (int)floor($size / $cells);
        // Seeded pattern from hash
        $bits = md5($seed);
        $idx = 0;
        for ($i=0; $i<$cells; $i++) {
            for ($j=0; $j<$cells; $j++) {
                $bit = hexdec($bits[$idx % strlen($bits)]) % 2;
                $color = $bit ? $black : $white;
                imagefilledrectangle($im, $x + $j*$cell, $y + $i*$cell, $x + ($j+1)*$cell - 1, $y + ($i+1)*$cell - 1, $color);
                $idx++;
            }
        }
        // Draw quiet zone border
        $gray = imagecolorallocate($im, 180, 180, 180);
        imagerectangle($im, $x, $y, $x + $cells*$cell, $y + $cells*$cell, $gray);
    }

    private function drawPseudoBarcode($im, int $x, int $y, int $w, int $h, string $seed): void
    {
        $black = imagecolorallocate($im, 0, 0, 0);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, $x, $y, $x+$w, $y+$h, $white);
        $hash = sha1($seed);
        $pos = $x + 10;
        $end = $x + $w - 10;
        $i = 0;
        while ($pos < $end) {
            $val = hexdec(substr($hash, ($i*2) % strlen($hash), 2));
            $barW = 1 + ($val % 4);
            imagefilledrectangle($im, $pos, $y+5, $pos+$barW, $y+$h-5, $black);
            $pos += $barW + 1 + ($val % 3);
            $i++;
        }
    }

    private function renderPdfTicket(array $t, string $pdfPath, string $pngPath): void
    {
        $pdf = new \FPDF('P','mm','A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial','B',16);
        $hdr = $this->utf8ToWin1252(sprintf('%s %s – %s', $t['operator'], $t['product'], $t['train']));
        $pdf->Cell(0,10, $hdr, 0, 1);
        $pdf->SetFont('Arial','',12);
        foreach ([
            $this->utf8ToWin1252('PNR: ' . $t['pnr']),
            $this->utf8ToWin1252('From: ' . $t['from']),
            $this->utf8ToWin1252('To: ' . $t['to']),
            $this->utf8ToWin1252('Date: ' . $t['date'] . '    Scheduled Arr: ' . $t['schedArr']),
        ] as $line) {
            $pdf->Cell(0,8,$line,0,1);
        }
        // Embed the PNG we generated (only if valid PNG)
        if (is_file($pngPath) && $this->isValidPng($pngPath)) {
            $pdf->Image($pngPath, 130, 40, 70, 0, 'PNG');
        }
        $pdf->Output('F', $pdfPath);
    }

    private function renderPngFallback(string $path): void
    {
        // 200x100 simple black/white PNG (valid header) – base64 encoded
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAMgAAABkCAYAAABXoT2ZAAAACXBIWXMAAAsSAAALEgHS3X78AAABYElEQVR4nO3UsQ3CMBAF0Zs7Gv//o+QeQk5x6b0Ck5pWe7kJxgq0r3IY5b8gJmZgAAAAAAAAAAAAAAAPzv7p2bKQ+1v3+0F5iP1m3fZQm0g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g7yqQm8g76m8Kk4H8+fPn7Z0wz9f7fM2v2r+0bKXK8u/6j1+fPn7/v3+8wqAAAAAAAAAAAAAAAAAPh8B9Gq8bO1f7AAAAAElFTkSuQmCC';
        $data = base64_decode($pngBase64, true);
        if ($data !== false) { file_put_contents($path, $data); }
    }

    private function isValidPng(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (!$fh) { return false; }
        $sig = fread($fh, 8);
        fclose($fh);
        return $sig === "\x89PNG\x0D\x0A\x1A\x0A";
    }

    private function utf8ToWin1252(string $s): string
    {
        $s = str_replace(["\u{2013}", "\u{2014}", "\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], ['–','—','‘','’','“','”'], $s);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    private function utf8ToAscii(string $s): string
    {
        $s = strtr($s, ['–' => '-', '—' => '-', '’' => "'", '“' => '"', '”' => '"']);
        $out = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    /** Convert mock TXT content + filename into journey + meta */
    private function parseMockText(string $base, string $txt): array
    {
        $lines = preg_split('/\r?\n/', $txt) ?: [];
        $blob = strtoupper($txt);
    $op = '';
    $product = '';
    $country = '';
    if (str_contains($blob, 'SNCF') || str_contains(strtoupper($base), 'SNCF')) { $op = 'SNCF'; $product = 'TGV'; $country = 'FR'; }
    if (preg_match('/\bDB\b/', $blob) || str_contains(strtoupper($base), 'DB')) { $op = 'DB'; $product = 'ICE'; $country = 'DE'; }
    if (str_contains($blob, 'DSB') || str_contains(strtoupper($base), 'DSB')) { $op = 'DSB'; $product = 'RE'; $country = 'DK'; }
    if (str_contains($blob, 'SJ') || str_contains(strtoupper($base), 'SE_')) { $op = 'SJ'; $product = 'REG'; $country = 'SE'; }
    if (str_contains($blob, 'ZSSK') || str_contains(strtoupper($base), 'SK_')) { $op = 'ZSSK'; $product = 'R'; $country = 'SK'; }
    if (str_contains($blob, 'PKP') || str_contains($blob, 'PKP INTERCITY') || str_contains(strtoupper($base), 'PL_')) { $op = 'PKP'; $product = 'IC'; $country = 'PL'; }

        $pnr = $this->matchOne($txt, '/PNR:\s*([A-Z0-9\-]+)/i');
        $trainRaw = $this->matchOne($txt, '/Train:\s*([^\r\n]+)/i');
        if ($trainRaw) {
            // Split product and number if possible
            if (preg_match('/^([A-ZÅÆØÄÖÜ]+)\s*([0-9]+)/i', $trainRaw, $m)) {
                $product = $product ?: strtoupper($m[1]);
            }
        }

        $from = $this->matchOne($txt, '/^From:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Fra:\s*(.+)$/mi');
        $to = $this->matchOne($txt, '/^To:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Til:\s*(.+)$/mi');

    $dateStr = $this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?/mi');
    $schedArrTxt = $this->matchOne($txt, '/^Scheduled Arr:\s*([0-9]{2}:[0-9]{2})/mi');
        $depTime = '';
        $depDate = '';
        if ($dateStr) {
            if (preg_match('/^([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?$/', $dateStr, $m)) {
                $depDate = $m[1];
                $depTime = $m[2] ?? '';
            }
        }

        // Special SNCF line with both stations and times
        $sncfLine = null;
        $arrTime = '';
        foreach ($lines as $l) {
            if (str_contains($l, '→') && preg_match('/\((\d{2}:\d{2})\).*→.*\((\d{2}:\d{2})\)/', $l, $mm)) {
                $sncfLine = $l; $depTime = $mm[1]; $arrTime = $mm[2];
                if (!$from && preg_match('/^(.*)\s*\(/', $l, $m1)) { $from = trim($m1[1]); }
                if (!$to && preg_match('/→\s*(.*)\s*\(/', $l, $m2)) { $to = trim($m2[1]); }
                break;
            }
        }

        $schedDep = '';
        $schedArr = '';
        if ($depDate && ($depTime || $arrTime || $schedArrTxt)) {
            $isoDate = $this->dateToIso($depDate);
            if ($depTime) { $schedDep = $isoDate . 'T' . $depTime . ':00'; }
            if ($arrTime) { $schedArr = $isoDate . 'T' . $arrTime . ':00'; }
            if (!$schedArr && $schedArrTxt) { $schedArr = $isoDate . 'T' . $schedArrTxt . ':00'; }
        }

        // If arrival missing, assume +75 minutes to exercise logic
        if (!$schedArr && $schedDep) {
            $schedArr = $this->addMinutes($schedDep, 120); // assume 2h journey
        }

        // Tailor actual arrival to match characteristic delays per known mock
        $actArr = '';
        if ($schedArr) {
            $delta = 75; // default
            $b = strtolower($base);
            if (str_starts_with($b, 'se_regional_lt150')) { $delta = 37; }
            if (str_starts_with($b, 'sk_long_domestic_exempt')) { $delta = 110; }
            if (str_starts_with($b, 'pl_intl_beyond_eu_partial')) { $delta = 0; /* cancellation: no actuals */ }
            $actArr = $delta > 0 ? $this->addMinutes($schedArr, $delta) : '';
        }

        $priceRaw = $this->matchOne($txt, '/^Price:\s*([0-9]+(?:\.[0-9]{1,2})?)\s*([A-Z]{3})/mi');
        $ticketPrice = $priceRaw ? ($priceRaw) : '0 EUR';

        // Scope flags
        $scope = strtolower($this->matchOne($txt, '/^Scope:\s*(.+)$/mi'));
        if ($scope === '') {
            $b = strtolower($base);
            if (str_contains($b, 'intl_beyond_eu') || str_contains($b, 'beyond_eu')) { $scope = 'intl_beyond_eu'; }
            elseif (str_contains($b, 'intl_inside_eu')) { $scope = 'intl_inside_eu'; }
            elseif (str_contains($b, 'long_domestic')) { $scope = 'long_domestic'; }
            elseif (str_contains($b, 'regional')) { $scope = 'regional'; }
        }
        $isLongDomestic = $scope === 'long_domestic';
        $isIntlBeyond = $scope === 'intl_beyond_eu';
        $isIntlInside = $scope === 'intl_inside_eu';

        // Art. 12 meta from disclosure/contract lines if present
        $throughDisclosure = $this->matchOne($txt, '/^Through ticket disclosure:\s*(.+)$/mi') ?: 'unknown';
        $contractType = $this->matchOne($txt, '/^Contract type:\s*(.+)$/mi');

        $journey = [
            'segments' => [[
                'operator' => $op,
                'trainCategory' => $product,
                'country' => $country,
                'pnr' => $pnr,
                'from' => $from,
                'to' => $to,
                'schedDep' => $schedDep,
                'schedArr' => $schedArr,
                'actArr' => $actArr,
            ]],
            'ticketPrice' => ['value' => $ticketPrice],
            'operatorName' => ['value' => $op],
            'trainCategory' => ['value' => $product],
            'country' => ['value' => $country],
            'is_long_domestic' => $isLongDomestic,
            'is_international_beyond_eu' => $isIntlBeyond,
            'is_international_inside_eu' => $isIntlInside,
        ];

        $art12Meta = [
            'through_ticket_disclosure' => $throughDisclosure,
            'contract_type' => $contractType ?: null,
        ];
        $art9Meta = [
            'info_on_rights' => 'Delvist',
        ];
        $refusionMeta = [
            'reason_delay' => true,
            'claim_rerouting' => true,
            'reroute_info_within_100min' => 'Ved ikke',
        ];
        if (str_starts_with(strtolower($base), 'pl_intl_beyond_eu_partial')) {
            $refusionMeta = [
                'reason_cancellation' => true,
                'claim_refund_ticket' => true,
                'claim_rerouting' => false,
                'reroute_info_within_100min' => 'Nej',
            ];
        }
        $compute = [
            'euOnly' => !$isIntlBeyond, // outside-EU parts not in EU scope
            'minPayout' => 4.0,
        ];

        return compact('journey','art12Meta','art9Meta','refusionMeta','compute');
    }

    private function matchOne(string $txt, string $pattern): string
    {
        if (preg_match($pattern, $txt, $m)) { return trim((string)($m[1] ?? '')); }
        return '';
    }

    private function dateToIso(string $dmy): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dmy, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $dmy;
    }

    private function addMinutes(string $iso, int $min): string
    {
        $t = strtotime($iso);
        if ($t) { return date('Y-m-d\TH:i:s', $t + ($min * 60)); }
        return $iso;
    }

    public function fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'ice_125m');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function exemptionFixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_exemptions_fr_regional');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function art12Fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_art12_through_ticket');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    /**
     * Returns a bundle of varied demo scenarios to exercise PDFs/PNGs, exemptions, Art. 12, Art. 9 and compensation fallbacks.
     */
    public function scenarios(): void
    {
        $seed = (string)($this->request->getQuery('seed') ?? 'demo');
        $count = (int)($this->request->getQuery('count') ?? 0);
        $withEval = (string)($this->request->getQuery('withEval') ?? $this->request->getQuery('eval') ?? '0') === '1';
        $mix = $this->buildScenarios($seed);

        if ($count > 0) {
            $mix = array_slice($mix, 0, $count);
        }

        // Optionally shuffle for variety
        if ($seed !== 'fixed') {
            shuffle($mix);
        }
        if ($withEval) {
            foreach ($mix as &$scenario) {
                $journey = (array)($scenario['journey'] ?? []);
                $art12Meta = (array)($scenario['art12_meta'] ?? []);
                $art9Meta = (array)($scenario['art9_meta'] ?? []);
                $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
                $scenario['profile'] = $profile;
                $scenario['art12'] = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
                // Seed Art.9 auto from Art.12 meta where overlapping
                $auto = [];
                foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                    if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
                }
                // Additional safe AUTO heuristics for Art. 9(1):
                // - CIV marking assumed present for common EU operators
                // - Train specificity: if ticket has a scheduled arrival, treat as specific train
                // - Basic fare_flex_type guess by product keyword (very rough; demo only)
                $op = (string)($journey['operatorName']['value'] ?? (($journey['segments'][0]['operator'] ?? '') ?: ''));
                $segments = (array)($journey['segments'] ?? []);
                $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
                $schedArr = (string)($last['schedArr'] ?? '');
                $euOps = ['DB','SNCF','DSB','SJ','ZSSK','PKP','CD','SBB','NS','SNCB','ÖBB','OEBB'];
                if ($op !== '' && in_array(strtoupper($op), $euOps, true)) {
                    if (empty($auto['civ_marking_present'])) {
                        $auto['civ_marking_present'] = ['value' => 'Ja', 'source' => 'demo_heuristic'];
                    }
                }
                if ($schedArr !== '') {
                    if (empty($auto['train_specificity'])) {
                        $auto['train_specificity'] = ['value' => 'Kun specifikt tog', 'source' => 'ticket_times'];
                    }
                }
                $prod = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
                $p = strtoupper($prod);
                if ($p !== '' && empty($auto['fare_flex_type'])) {
                    $auto['fare_flex_type'] = ['value' => (str_contains($p,'IC')||str_contains($p,'ICE')||str_contains($p,'TGV') ? 'Standard/Non-flex' : 'Semi-flex'), 'source' => 'demo_heuristic'];
                }
                if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
                $scenario['art9'] = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);
            }
            unset($scenario);
        }
        $out = ['scenarios' => $mix, 'withEval' => $withEval];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', ['scenarios','withEval']);
    }

    /**
     * POST: Runs exemption profile, Art.12, Art.9 and compensation over the generated scenarios.
     * Accepts optional body: { seed?: string, count?: int, scenarios?: array }
     */
    public function runScenarios(): void
    {
        $method = strtoupper((string)$this->request->getMethod());
        if ($method === 'GET') {
            // Support GET for convenience in browser: use query params
            $seed = (string)($this->request->getQuery('seed') ?? 'demo');
            $count = (int)($this->request->getQuery('count') ?? 0);
            $scenarios = $this->buildScenarios($seed);
            // Optional: run a specific scenario by id
            $id = (string)($this->request->getQuery('id') ?? '');
            if ($id !== '') {
                $scenarios = array_values(array_filter($scenarios, function ($s) use ($id) {
                    return isset($s['id']) && (string)$s['id'] === $id;
                }));
                if (empty($scenarios)) {
                    $available = array_values(array_map(function ($s) { return (string)($s['id'] ?? ''); }, $this->buildScenarios($seed)));
                    $this->set(['error' => 'unknown_id', 'id' => $id, 'available' => $available]);
                    $this->viewBuilder()->setOption('serialize', ['error','id','available']);
                    return;
                }
            }
        } else {
            $this->request->allowMethod(['post']);
            $payload = (array)$this->request->getData();
            $seed = (string)($payload['seed'] ?? 'demo');
            $count = (int)($payload['count'] ?? 0);
            $scenarios = (array)($payload['scenarios'] ?? $this->buildScenarios($seed));
        }
        if ($count > 0) {
            $scenarios = array_slice($scenarios, 0, $count);
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            $journey = (array)($scenario['journey'] ?? []);
            $art12Meta = (array)($scenario['art12_meta'] ?? []);
            $art9Meta = (array)($scenario['art9_meta'] ?? []);
            $compute = (array)($scenario['compute'] ?? []);
            $refundMeta = [
                'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            ];
            $refusionMeta = (array)($scenario['refusion_meta'] ?? []);

            // Exemptions
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

            // Art. 12 and Art. 9
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
            // Seed Art.9 auto from Art.12 meta for overlapping fields
            $auto = [];
            foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
            }
            if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

            // Compensation
            $comp = $this->computeCompensationPreview($journey, $compute);

            // Refund (Art. 18-like)
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, $refundMeta);

            // Step Refusion (Art. 18 + CIV + 10 + dele af 20)
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            // Unified claim sample
            $claimInput = $this->mapScenarioToClaimInput($scenario);
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($claimInput);

            $results[] = [
                'id' => $scenario['id'] ?? null,
                'media' => $scenario['media'] ?? null,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results]);
        $this->viewBuilder()->setOption('serialize', ['results']);
    }

    /** Map scenario into ClaimInput (simplified) */
    private function mapScenarioToClaimInput(array $scenario): array
    {
        $journey = (array)($scenario['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? ''));
        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        $price = 0.0;
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }

        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true, // assume EU for demo; real pipeline should mark per segment
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }

        $delayMin = 0;
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr && $actArr) {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { $delayMin = max(0, (int)round(($t2 - $t1)/60)); }
        }

        $extraordinary = (bool)($scenario['compute']['extraordinary'] ?? false);
        $selfInflicted = (bool)($scenario['compute']['selfInflicted'] ?? false);
        $notified = (bool)($scenario['compute']['knownDelayBeforePurchase'] ?? false);

        return [
            'country_code' => $country ?: 'EU',
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [
                'through_ticket' => true,
                'legs' => $legs,
            ],
            'service_scope' => (!empty($journey['is_international_beyond_eu']) ? 'intl_beyond_eu' : (!empty($journey['is_international_inside_eu']) ? 'intl_inside_eu' : (!empty($journey['is_long_domestic']) ? 'long_domestic' : 'regional'))),
            'disruption' => [
                'delay_minutes_final' => $delayMin,
                'eu_only' => (bool)($scenario['compute']['euOnly'] ?? true),
                'notified_before_purchase' => $notified,
                'extraordinary' => $extraordinary,
                'self_inflicted' => $selfInflicted,
            ],
            'choices' => [
                'wants_refund' => (bool)($scenario['refusion_meta']['claim_refund_ticket'] ?? false),
                'wants_reroute_same_soonest' => ($scenario['refusion_meta']['reroute_same_conditions_soonest'] ?? 'Ved ikke') === 'Ja',
                'wants_reroute_later_choice' => ($scenario['refusion_meta']['reroute_later_at_choice'] ?? 'Ved ikke') === 'Ja',
            ],
            'expenses' => [
                // A minimal mapping; real form would pass numeric amounts
                'meals' => 0,
                'hotel' => 0,
                'alt_transport' => 0,
                'other' => 0,
            ],
            'already_refunded' => 0,
        ];
    }

    /**
     * Build the base scenarios list. Caller may shuffle/slice.
     * @param string $seed
     * @return array<int,array<string,mixed>>
     */
    private function buildScenarios(string $seed): array
    {
        $mix = [
            [
                'id' => 'sncf_png_through_ticket_ok',
                'media' => ['png' => 'tests/fixtures/sncf_ticket_through.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'SNCF', 'trainCategory' => 'TGV', 'country' => 'FR', 'pnr' => 'ABC123', 'schedArr' => '2025-01-02T10:00:00', 'actArr' => '2025-01-02T11:15:00'],
                    ],
                    'ticketPrice' => ['value' => '120.00 EUR'],
                    'operatorName' => ['value' => 'SNCF'],
                    'trainCategory' => ['value' => 'TGV'],
                    'country' => ['value' => 'FR'],
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Gennemgående',
                    'single_txn_operator' => 'Ja',
                    'separate_contract_notice' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Ja',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'Ja',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ja',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'denial_paths_extraordinary_refund',
                'media' => ['png' => 'tests/fixtures/denial_sample.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DSB', 'trainCategory' => 'REG', 'country' => 'DK', 'pnr' => 'DK9', 'schedArr' => '2025-05-01T08:00:00', 'actArr' => '2025-05-01T08:40:00'],
                    ],
                    'ticketPrice' => ['value' => '8.00 EUR'],
                    'operatorName' => ['value' => 'DSB'],
                    'trainCategory' => ['value' => 'REG'],
                    'country' => ['value' => 'DK'],
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_on_rights' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => true,
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'db_pdf_separate_contracts_agency',
                'media' => ['pdf' => 'tests/fixtures/db_ticket_separate.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DB', 'trainCategory' => 'ICE', 'country' => 'DE', 'pnr' => 'X1', 'schedArr' => '2025-03-05T12:00:00', 'actArr' => '2025-03-05T14:30:00'],
                        ['operator' => 'CD', 'trainCategory' => 'EC', 'country' => 'CZ', 'pnr' => 'Y2', 'schedArr' => '2025-03-05T16:00:00', 'actArr' => '2025-03-05T17:45:00'],
                    ],
                    'ticketPrice' => ['value' => '89.00 EUR'],
                    'operatorName' => ['value' => 'DB'],
                    'trainCategory' => ['value' => 'ICE'],
                    'country' => ['value' => 'DE'],
                    'seller_type' => 'agency',
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Særskilte',
                    'separate_contract_notice' => 'Nej',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Delvist',
                    'info_on_rights' => 'Nej',
                    'info_during_disruption' => 'Nej',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_missed_conn' => true,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Nej',
                    'meal_offered' => 'Nej',
                    'alt_transport_provided' => 'Nej',
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => false,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'long_domestic_sk_exemptions',
                'media' => ['png' => 'tests/fixtures/sk_ticket.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'ZSSK', 'trainCategory' => 'R', 'country' => 'SK', 'pnr' => 'ZZ9', 'schedArr' => '2025-02-10T09:00:00', 'actArr' => '2025-02-10T10:05:00'],
                    ],
                    'ticketPrice' => ['value' => '12.00 EUR'],
                    'operatorName' => ['value' => 'ZSSK'],
                    'trainCategory' => ['value' => 'R'],
                    'country' => ['value' => 'SK'],
                    'is_long_domestic' => true,
                ],
                'art12_meta' => [],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'unknown',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ved ikke',
                    'meal_offered' => 'Ved ikke',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'intl_beyond_eu_partial',
                'media' => ['pdf' => 'tests/fixtures/int_beyond_eu.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'PKP', 'trainCategory' => 'IC', 'country' => 'PL', 'pnr' => 'PL1', 'schedArr' => '2025-04-01T18:00:00', 'actArr' => '2025-04-01T19:20:00'],
                        ['operator' => 'BY', 'trainCategory' => 'INT', 'country' => 'BY', 'pnr' => 'BY2', 'schedArr' => '2025-04-01T22:00:00', 'actArr' => '2025-04-01T22:00:00'],
                    ],
                    'ticketPrice' => ['value' => '60.00 EUR'],
                    'operatorName' => ['value' => 'PKP'],
                    'trainCategory' => ['value' => 'IC'],
                    'country' => ['value' => 'PL'],
                    'is_international_beyond_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'Ja',
                    'language_accessible' => 'Delvist',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_cancellation' => true,
                    'claim_refund_ticket' => true,
                    'refund_requested' => 'Nej',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => false,
                    'minPayout' => 0.0,
                ],
            ],
        ];
        return $mix;
    }

    /**
     * Compute a compensation preview mirroring ComputeController::compensation logic.
     * @param array $journey
     * @param array $payload
     * @return array{minutes:int,pct:float,amount:float,currency:string,source:string,notes:?string}
     */
    private function computeCompensationPreview(array $journey, array $payload): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];

        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        $minutes = null;
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                $minutes = max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        if ($minutes === null) {
            $depDate = (string)($journey['depDate']['value'] ?? '');
            $sched = (string)($journey['schedArrTime']['value'] ?? '');
            $act = (string)($journey['actualArrTime']['value'] ?? '');
            if ($depDate && $sched && $act) {
                $t1 = strtotime($depDate . 'T' . $sched . ':00');
                $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
                if ($t1 && $t2) {
                    $minutes = max(0, (int)round(($t2 - $t1) / 60));
                }
            }
        }
        $minutes = $minutes ?? 0;
        // E4: EU-only delay: if euOnly=true and explicit delayMinEU provided, prefer it
        $euOnlyFlag = (bool)($payload['euOnly'] ?? true);
        if ($euOnlyFlag && isset($payload['delayMinEU'])) {
            $euMin = (int)$payload['delayMinEU'];
            if ($euMin >= 0) { $minutes = $euMin; }
        }

        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0');
        $price = 0.0;
        $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) {
            $price = (float)$m[1];
        }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

        $operator = (string)($journey['operatorName']['value'] ?? ($last['operator'] ?? ''));
        $product = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
        $country = (string)($journey['country']['value'] ?? ($payload['country'] ?? ''));

        // Determine scope similarly to ExemptionProfileBuilder
        $scope = 'regional';
        if (!empty($journey['is_international_beyond_eu'])) { $scope = 'intl_beyond_eu'; }
        elseif (!empty($journey['is_international_inside_eu'])) { $scope = 'intl_inside_eu'; }
        elseif (!empty($journey['is_long_domestic'])) { $scope = 'long_domestic'; }

        $svc = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $res = $svc->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => $euOnlyFlag,
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'art18Option' => (string)($payload['art18Option'] ?? ''),
            'knownDelayBeforePurchase' => (bool)($payload['knownDelayBeforePurchase'] ?? false),
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
            'scope' => $scope,
        ]);

        // E3: apportionment via legPrice or returnTicket half-fare
        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amountBase = $price;
        $legPrice = isset($payload['legPrice']) ? (float)$payload['legPrice'] : null;
        if ($legPrice !== null && $legPrice > 0) {
            $amountBase = $legPrice;
        } elseif (!empty($payload['returnTicket'])) {
            $amountBase = $price / 2;
        }
        $amount = round($amountBase * $pct, 2);
        $minPayout = isset($payload['minPayout']) ? (float)$payload['minPayout'] : 0.0;
        $source = $res['source'] ?? 'eu';
        $notes = $res['notes'] ?? null;
        if ($minPayout > 0 && $amount > 0 && $amount < $minPayout) {
            $amount = 0.0;
            $source = 'denied';
            $notes = trim(((string)$notes) . ' Min payout threshold');
        }

        return [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'notes' => $notes,
        ];
}
}
