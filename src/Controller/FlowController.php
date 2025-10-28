<?php
declare(strict_types=1);

namespace App\Controller;

class FlowController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('App');
        $this->autoRender = true;
    }

    public function start(): \Cake\Http\Response|null
    {
        // Entry: upload or paste text, choose EU-only, and opt-in to Art. 9 (checkbox)
        if ($this->request->is('post')) {
            $journey = (array)json_decode((string)($this->request->getData('journey_json') ?? '[]'), true);
            $text = (string)($this->request->getData('ocr_text') ?? '');
            $meta = [];
            if ($text !== '') {
                $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                    new \App\Service\TicketExtraction\HeuristicsExtractor(),
                    new \App\Service\TicketExtraction\LlmExtractor(),
                ], 0.66);
                $er = $broker->run($text);
                $meta['extraction_provider'] = $er->provider;
                $meta['extraction_confidence'] = $er->confidence;
                $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                // Convert to _auto shape used by the UI
                $auto = [];
                foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                $meta['_auto'] = $auto;
                // Propagate country hint from extraction into journey for exemptions profile
                try {
                    $opCountry = isset($auto['operator_country']['value']) ? (string)$auto['operator_country']['value'] : '';
                    if ($opCountry !== '') {
                        if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = [[]]; }
                        $journey['segments'][0]['country'] = $opCountry;
                        $journey['country']['value'] = $opCountry;
                        $meta['logs'][] = 'AUTO: journey country set from operator_country=' . $opCountry;
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }

            // Art. 12: if we have a bookingRef and a known seller_type, infer single-transaction flags
            if (!empty($journey['bookingRef'])) {
                $sellerTypeNow = $journey['seller_type'] ?? null;
                if ($sellerTypeNow === 'operator') { $meta['single_txn_operator'] = 'yes'; }
                if ($sellerTypeNow === 'agency') { $meta['single_txn_retailer'] = 'yes'; }
            }
            $compute = [
                'euOnly' => (bool)$this->request->getData('eu_only'),
                'art9OptIn' => (bool)$this->request->getData('art9_opt_in'),
            ];
            // TRIN 1: Travel state (completed / ongoing / before_start)
            $flags = (array)$this->request->getSession()->read('flow.flags') ?: [];
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            $this->request->getSession()->write('flow.journey', $journey);
            $this->request->getSession()->write('flow.meta', $meta);
            $this->request->getSession()->write('flow.compute', $compute);
            $this->request->getSession()->write('flow.flags', $flags);
            return $this->redirect(['action' => 'journey']);
        }
        return null;
    }

    public function journey(): \Cake\Http\Response|null
    {
        $journey = (array)$this->request->getSession()->read('flow.journey') ?: [];
        $meta = (array)$this->request->getSession()->read('flow.meta') ?: [];
        $compute = (array)$this->request->getSession()->read('flow.compute') ?: [];
        $incident = (array)$this->request->getSession()->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            $delay = (int)$this->request->getData('delay_min_eu');
            $compute['delayMinEU'] = $delay;
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $compute['extraordinary'] = (bool)$this->request->getData('extraordinary');
            // TRIN 2: Incident selection
            $main = (string)($this->request->getData('incident_main') ?? '');
            if (!in_array($main, ['delay','cancellation',''], true)) { $main = ''; }
            $incident['main'] = $main;
            $incident['missed'] = $this->truthy($this->request->getData('missed_connection'));
            $this->request->getSession()->write('flow.compute', $compute);
            $this->request->getSession()->write('flow.incident', $incident);
            return $this->redirect(['action' => 'entitlements']);
        }
        $this->set(compact('journey','meta','compute','incident'));
        return null;
    }

    public function entitlements(): \Cake\Http\Response|null
    {
        $journey = (array)$this->request->getSession()->read('flow.journey') ?: [];
        $meta = (array)$this->request->getSession()->read('flow.meta') ?: [];
        $compute = (array)$this->request->getSession()->read('flow.compute') ?: [];

        // Services
        // Infer scope from stations before computing profile
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
            if (!empty($inferRes['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $inferRes['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: scope infer failed: ' . $e->getMessage();
        }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Derive AUTO Art.12 hooks (2,3,5,6,7,8,13) without overriding user inputs
        try {
            $auto12 = (new \App\Service\Art12AutoDeriver())->apply($journey, $meta);
            $meta = $auto12['meta'];
            if (!empty($auto12['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $auto12['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: Art12AutoDeriver failed: ' . $e->getMessage();
        }
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta);

        // Evaluate Art. 9 unconditionally so ask_hooks and banners are available even if the
        // explicit opt-in toggle isn't set. The UI can still choose what to show.
        try {
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art9 = null;
            $meta['logs'][] = 'WARN: Art9Evaluator failed: ' . $e->getMessage();
        }
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);

        if ($this->request->is('post')) {
            // Allow toggling of Art. 9 opt-in and known delay flags here as checkboxes
            $compute['art9OptIn'] = (bool)$this->request->getData('art9_opt_in');
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $this->request->getSession()->write('flow.compute', $compute);
            return $this->redirect(['action' => 'summary']);
        }

        $this->set(compact('journey','meta','compute','profile','art12','art9','refund','refusion'));
        return null;
    }

    public function summary(): void
    {
        $journey = (array)$this->request->getSession()->read('flow.journey') ?: [];
        $meta = (array)$this->request->getSession()->read('flow.meta') ?: [];
        $compute = (array)$this->request->getSession()->read('flow.compute') ?: [];
        $flags = (array)$this->request->getSession()->read('flow.flags') ?: [];
        $incident = (array)$this->request->getSession()->read('flow.incident') ?: [];

        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $mm)) { $currency = strtoupper($mm[1]); }

        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => (float)preg_replace('/[^0-9.]/', '', (string)($journey['ticketPrice']['value'] ?? 0)),
            'trip' => [ 'through_ticket' => true, 'legs' => [] ],
            'disruption' => [
                'delay_minutes_final' => (int)($compute['delayMinEU'] ?? 0),
                'eu_only' => (bool)($compute['euOnly'] ?? true),
                'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                'extraordinary' => (bool)($compute['extraordinary'] ?? false),
                'self_inflicted' => false,
            ],
            'choices' => [ 'wants_refund' => false, 'wants_reroute_same_soonest' => false, 'wants_reroute_later_choice' => false ],
            'expenses' => [ 'meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0 ],
            'already_refunded' => 0,
        ]);

        // Map incidents to reimbursement reason fields
        $reason_delay = !empty($incident['main']) && $incident['main'] === 'delay';
        $reason_cancellation = !empty($incident['main']) && $incident['main'] === 'cancellation';
        $reason_missed_conn = !empty($incident['missed']);

        // Additional info aggregation (include travel state)
        $additional_info_parts = [];
        if (!empty($flags['travel_state'])) {
            $label = [
                'completed' => 'Rejsen er afsluttet',
                'ongoing' => 'Rejsen er påbegyndt (i tog / skift)',
                'before_start' => 'Jeg skal til at påbegynde rejsen',
            ][$flags['travel_state']] ?? $flags['travel_state'];
            $additional_info_parts[] = 'TRIN 1: ' . $label;
        }
        if (!empty($incident)) {
            $sel = [];
            if ($reason_delay) { $sel[] = 'Delay'; }
            if ($reason_cancellation) { $sel[] = 'Cancellation'; }
            if ($reason_missed_conn) { $sel[] = 'Missed connection'; }
            if (!empty($sel)) { $additional_info_parts[] = 'TRIN 2: ' . implode(', ', $sel); }
        }
        $additional_info = implode(' | ', $additional_info_parts);

        $this->set(compact('journey','meta','compute','claim','flags','incident','reason_delay','reason_cancellation','reason_missed_conn','additional_info'));
    }

    /**
     * Single-page flow (all steps on one page), as per flow_chart_v_1_live_client_service.pdf
     */
    public function one(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        // When called via AJAX to refresh hooks panel, we'll short-circuit and render only the element
        $isAjaxHooks = (bool)$this->request->getQuery('ajax_hooks');
        // Previously: a plain GET cleared all session state. This caused data loss when adding
        // query params like allow_official=1. Now we only reset on explicit request (?reset=1).
        if ($this->request->is('get')) {
            $justUploaded = (bool)$session->read('flow.justUploaded');
            $doReset = $this->truthy($this->request->getQuery('reset'));
            if ($justUploaded) {
                // Preserve once after PRG, then drop the flag
                $session->write('flow.justUploaded', false);
            } elseif ($doReset) {
                // Hard reset only when user explicitly asks for it
                $session->delete('flow.form');
                $session->delete('flow.flags');
                $session->delete('flow.incident');
                // Also clear TRIN 4 fields and meta['_auto'] to prevent stale data
                $form = [];
                $meta = [];
                $session->write('flow.form', $form);
                $session->write('flow.meta', $meta);
            }
        }
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: ['euOnly' => true];
        $flags = (array)$session->read('flow.flags') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
    $form = (array)$session->read('flow.form') ?: [];

        if ($this->request->is('post')) {
            // Early handle: remove a ticket from the grouped list (TRIN 4 UI "Fjern")
            $removeFile = (string)($this->request->getData('remove_ticket') ?? '');
            if ($removeFile !== '') {
                // If removing the current primary ticket
                $currentFile = (string)($form['_ticketFilename'] ?? '');
                if ($currentFile !== '' && $removeFile === $currentFile) {
                    // Clear TRIN 4 current upload and auto fields
                    unset($form['_ticketUploaded'], $form['_ticketFilename'], $form['_ticketOriginalName']);
                    // Clear TRIN 4 form fields
                    foreach ([
                        'operator','operator_country','operator_product',
                        'dep_date','dep_time','dep_station','arr_station','arr_time',
                        'train_no','ticket_no','price',
                        'actual_arrival_date','actual_dep_time','actual_arr_time',
                        'missed_connection_station'
                    ] as $rk) { unset($form[$rk]); }
                    // Clear meta auto caches
                    unset($meta['_auto'], $meta['_segments_auto'], $meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode']);
                    unset($meta['extraction_provider'], $meta['extraction_confidence']);
                    $meta['logs'][] = 'Removed primary ticket: ' . $removeFile;
                } else {
                    // Remove from multi tickets list if present
                    if (!empty($meta['_multi_tickets']) && is_array($meta['_multi_tickets'])) {
                        $meta['_multi_tickets'] = array_values(array_filter((array)$meta['_multi_tickets'], function($t) use ($removeFile){
                            return (string)($t['file'] ?? '') !== $removeFile;
                        }));
                        $meta['logs'][] = 'Removed extra ticket: ' . $removeFile;
                    }
                }
                $session->write('flow.meta', $meta);
                $session->write('flow.form', $form);
                // PRG back to TRIN 4; if AJAX hooks refresh, render fragment
                if ($isAjaxHooks && $this->request->is('ajax')) {
                    // Fall through to recompute and render hooks element below
                } else {
                    return $this->redirect(['action' => 'one', '#' => 's4']);
                }
            }
            $didUpload = false;
            // TRIN 1
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            // OCR/JSON input (optional)
            $text = (string)($this->request->getData('ocr_text') ?? '');
            if ($text !== '') {
                // Normalize spaces to improve downstream matching
                $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $text) ?? $text;
                $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                    new \App\Service\TicketExtraction\HeuristicsExtractor(),
                    new \App\Service\TicketExtraction\LlmExtractor(),
                ], 0.66);
                $er = $broker->run($text);
                $meta['extraction_provider'] = $er->provider;
                $meta['extraction_confidence'] = $er->confidence;
                $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                // Convert to _auto shape for UI and refill TRIN 3.2 inputs
                $auto = [];
                foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                $meta['_auto'] = $auto;
                foreach ([
                    'dep_date','dep_time','dep_station','arr_station','arr_time',
                    'train_no','ticket_no','price'
                ] as $rk) {
                    $form[$rk] = isset($meta['_auto'][$rk]['value']) ? (string)$meta['_auto'][$rk]['value'] : ($form[$rk] ?? '');
                }
                if (!empty($meta['_auto']['operator']['value'])) { $form['operator'] = (string)$meta['_auto']['operator']['value']; }
                if (!empty($meta['_auto']['operator_country']['value'])) { $form['operator_country'] = (string)$meta['_auto']['operator_country']['value']; }
                if (!empty($meta['_auto']['operator_product']['value'])) { $form['operator_product'] = (string)$meta['_auto']['operator_product']['value']; }
            }
            $jjson = (string)($this->request->getData('journey_json') ?? '');
            if (trim($jjson) !== '') {
                $maybe = json_decode($jjson, true);
                if (is_array($maybe)) { $journey = $maybe; }
            }

            // TRIN 2
            $main = (string)($this->request->getData('incident_main') ?? '');
            if (in_array($main, ['delay','cancellation',''], true)) { $incident['main'] = $main; }
            $incident['missed'] = $this->truthy($this->request->getData('missed_connection'));

            // Delay and flags
            $compute['euOnly'] = (bool)$this->request->getData('eu_only');
            $compute['delayMinEU'] = (int)($this->request->getData('delay_min_eu') ?? 0);
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $compute['extraordinary'] = (bool)$this->request->getData('extraordinary');
            $compute['art9OptIn'] = (bool)$this->request->getData('art9_opt_in');

            // Seller type inference from purchaseChannel (fallback if OCR didn't catch it)
            $purchaseChannel = (string)($this->request->getData('purchaseChannel') ?? ($form['purchaseChannel'] ?? ''));
            if (!empty($purchaseChannel)) {
                if (in_array($purchaseChannel, ['station','onboard'], true)) { $journey['seller_type'] = $journey['seller_type'] ?? 'operator'; }
                elseif ($purchaseChannel === 'web_app') { $journey['seller_type'] = $journey['seller_type'] ?? 'agency'; }
            }

            // Handle ticket upload (TRIN 4) to auto-extract journey fields
            $saved = false; $attemptedUpload = false; $dest = null; $name = null; $safe = null;
            $uf = $this->request->getUploadedFile('ticket_upload');
            if ($uf instanceof \Psr\Http\Message\UploadedFileInterface) {
                $attemptedUpload = true;
            }
            if ($uf instanceof \Psr\Http\Message\UploadedFileInterface && $uf->getError() === UPLOAD_ERR_OK) {
                $name = (string)($uf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                try { $uf->moveTo($dest); $saved = true; } catch (\Throwable $e) { $saved = false; $meta['logs'][] = 'Upload move failed: ' . $e->getMessage(); }
            } elseif ($uf instanceof \Psr\Http\Message\UploadedFileInterface && $uf->getError() !== UPLOAD_ERR_OK) {
                $meta['logs'][] = 'Upload error code: ' . (string)$uf->getError();
            } else {
                $af = $this->request->getData('ticket_upload');
                if ($af && is_array($af) && ($af['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $attemptedUpload = true;
                    $tmp = $af['tmp_name'];
                    $name = (string)($af['name'] ?? ('ticket_' . bin2hex(random_bytes(4))));
                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                    $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                    $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                    if (@move_uploaded_file($tmp, $dest)) { $saved = true; }
                    else { $meta['logs'][] = 'Upload move failed (legacy array path).'; }
                } elseif ($af && is_array($af)) {
                    $attemptedUpload = true;
                    $meta['logs'][] = 'Upload error code: ' . (string)($af['error'] ?? UPLOAD_ERR_NO_FILE);
                }
            }

            // Always reset TRIN 4 fields and meta['_auto'] when a new ticket is uploaded
            if ($saved && $dest) {
                $form['_ticketUploaded'] = '1';
                $form['_ticketFilename'] = $safe;
                $form['_ticketOriginalName'] = $name;
                $didUpload = true;
                $meta['logs'][] = 'Upload saved: ' . (string)$safe;
                // Reset TRIN 4 fields so a new upload doesn't keep stale values
                $resetKeys = [
                    'operator','operator_country','operator_product',
                    'dep_date','dep_time','dep_station','arr_station','arr_time',
                    'train_no','ticket_no','price',
                    'actual_arrival_date','actual_dep_time','actual_arr_time',
                    'missed_connection_station'
                ];
                foreach ($resetKeys as $rk) { unset($form[$rk]); }
                // Also reset meta['_auto'] completely
                $meta['_auto'] = [];
                // Reset scope flags on journey to avoid leaking previous classification
                unset($journey['is_international_beyond_eu'], $journey['is_international_inside_eu'], $journey['is_long_domestic']);
                // Reset extraction diagnostics so each upload starts clean
                unset($meta['extraction_provider'], $meta['extraction_confidence']);
                if (!isset($meta['logs']) || !is_array($meta['logs'])) { $meta['logs'] = []; }
                $meta['logs'] = [];
                $meta['logs'][] = 'RESET: cleared operator/product and 3.2 fields before OCR';

                // Extract text depending on file type, including image-fixture fallback
                $textBlob = '';
                $ext = strtolower((string)pathinfo($dest, PATHINFO_EXTENSION));
                try {
                    if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($dest);
                        $textBlob = $pdf->getText() ?? '';
                        $meta['logs'][] = 'OCR: PDF parsed via smalot/pdfparser (' . strlen((string)$textBlob) . ' chars)';
                        // Fallback to pdftotext when PDF has little/no text
                        if (trim((string)$textBlob) === '') {
                            $pdftotext = function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH');
                            $pdftotext = $pdftotext ?: 'pdftotext';
                            $cmd = escapeshellarg((string)$pdftotext) . ' -layout ' . escapeshellarg((string)$dest) . ' -';
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') {
                                $textBlob = (string)$out;
                                $meta['logs'][] = 'OCR: PDF parsed via pdftotext (-layout)';
                            } else {
                                $meta['logs'][] = 'OCR: pdftotext not available or returned empty';
                            }
                        }
                    } elseif (in_array($ext, ['txt','text'], true)) {
                        $textBlob = (string)@file_get_contents($dest);
                        $meta['logs'][] = 'OCR: TXT read (' . strlen((string)$textBlob) . ' chars)';
                    } elseif (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                        $meta['logs'][] = 'OCR: image detected (' . strtoupper($ext) . ')';
                        // Try to find a mock TXT with same basename under mocks/tests/fixtures
                        $base = (string)pathinfo($safe, PATHINFO_FILENAME);
                        $mockDir = ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
                        $cands = [
                            $mockDir . $base . '.txt',
                            $mockDir . strtolower($base) . '.txt',
                            $mockDir . str_replace('-', '_', strtolower($base)) . '.txt',
                        ];
                        foreach ($cands as $cand) {
                            if (is_file($cand)) { 
                                $textBlob = (string)@file_get_contents($cand); 
                                $meta['logs'][] = 'OCR: using mock fixture ' . basename($cand) . ' (' . strlen((string)$textBlob) . ' chars)';
                                break; 
                            }
                        }
                        $visionTried = false;
                        $visionFirst = false;
                        try {
                            $vf = function_exists('env') ? env('LLM_VISION_PRIORITY') : getenv('LLM_VISION_PRIORITY');
                            $visionFirst = strtolower((string)($vf ?? '')) === 'first' || (function_exists('env') ? (env('LLM_VISION_FIRST') === '1') : (getenv('LLM_VISION_FIRST') === '1'));
                        } catch (\Throwable $e) { $visionFirst = false; }
                        $meta['logs'][] = 'Vision-first: ' . ($visionFirst ? 'enabled' : 'disabled');

                        // Optional: try Vision first when configured
                        if ($textBlob === '' && $visionFirst) {
                            try {
                                $vision = new \App\Service\Ocr\LlmVisionOcr();
                                [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                                foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                                $visionTried = true;
                                if (is_string($vText) && trim($vText) !== '') {
                                    $textBlob = (string)$vText;
                                    $meta['logs'][] = 'OCR: Vision-first provided text';
                                } else {
                                    $meta['logs'][] = 'OCR: Vision-first returned no text, falling back to Tesseract';
                                }
                            } catch (\Throwable $e) {
                                $meta['logs'][] = 'OCR Vision-first exception: ' . $e->getMessage();
                                $visionTried = true;
                            }
                        }

                        // If still empty, try local Tesseract OCR if available
                        if ($textBlob === '') {
                            // Pre-scale small images to improve OCR quality (Tesseract needs ~300 DPI)
                            $scalePath = $dest;
                            try {
                                $info = @getimagesize($dest);
                                if (is_array($info)) {
                                    $w = (int)($info[0] ?? 0); $h = (int)($info[1] ?? 0);
                                    if ($w > 0 && $h > 0 && $w < 1400) {
                                        $factor = 1.8;
                                        $nw = (int)round($w * $factor); $nh = (int)round($h * $factor);
                                        $img = null;
                                        switch ($ext) {
                                            case 'jpg': case 'jpeg': $img = @imagecreatefromjpeg($dest); break;
                                            case 'png': $img = @imagecreatefrompng($dest); break;
                                            case 'webp': if (function_exists('imagecreatefromwebp')) { $img = @imagecreatefromwebp($dest); } break;
                                            case 'bmp': if (function_exists('imagecreatefrombmp')) { $img = @imagecreatefrombmp($dest); } break;
                                            default: $img = null; break;
                                        }
                                        if ($img) {
                                            $dst = @imagecreatetruecolor($nw, $nh);
                                            if ($dst) {
                                                @imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                                                $tmpPath = (sys_get_temp_dir() ?: dirname($dest)) . DIRECTORY_SEPARATOR . ('ocr_up_' . bin2hex(random_bytes(4)) . '.png');
                                                if (@imagepng($dst, $tmpPath, 9)) {
                                                    $scalePath = $tmpPath;
                                                    $meta['logs'][] = 'OCR: upscaled image ' . $w . 'x' . $h . ' -> ' . $nw . 'x' . $nh;
                                                }
                                                @imagedestroy($dst);
                                            }
                                            @imagedestroy($img);
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                                // ignore scaling errors
                            }
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            // Determine language packs: env override wins; else infer from filename hints
                            $langs = function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS');
                            if (!$langs || trim((string)$langs) === '') {
                                $fn = strtolower((string)$safe);
                                $langs = 'eng';
                                $hint = 'eng';
                                if (preg_match('/(sncf|ouigo|ter|tgv|fr|french|eurostar)/', $fn)) { $langs = 'eng+fra'; $hint = 'eng+fra'; }
                                elseif (preg_match('/(db|bahn|ice|ic\b|re\b|de|german)/', $fn)) { $langs = 'eng+deu'; $hint = 'eng+deu'; }
                                elseif (preg_match('/(trenitalia|fs\b|it|ital)/', $fn)) { $langs = 'eng+ita'; $hint = 'eng+ita'; }
                                elseif (preg_match('/(renfe|es|span)/', $fn)) { $langs = 'eng+spa'; $hint = 'eng+spa'; }
                                elseif (preg_match('/(dsb|dk|dan)/', $fn)) { $langs = 'eng+dan'; $hint = 'eng+dan'; }
                                elseif (preg_match('/(sj\b|se|swed)/', $fn)) { $langs = 'eng+swe'; $hint = 'eng+swe'; }
                                elseif (preg_match('/(ns\b|nl|dutch)/', $fn)) { $langs = 'eng+nld'; $hint = 'eng+nld'; }
                                elseif (preg_match('/(sncb|nmbs|be|belg)/', $fn)) { $langs = 'eng+nld+fra'; $hint = 'eng+nld+fra'; }
                                elseif (preg_match('/(sbb|cff|ffs|ch|sui)/', $fn)) { $langs = 'eng+deu+fra+ita'; $hint = 'eng+deu+fra+ita'; }
                                elseif (preg_match('/(obb|at\b|aust)/', $fn)) { $langs = 'eng+deu'; $hint = 'eng+deu'; }
                                elseif (preg_match('/(pkp|pl\b|pol)/', $fn)) { $langs = 'eng+pol'; $hint = 'eng+pol'; }
                                elseif (preg_match('/(cd\b|cz\b|czech)/', $fn)) { $langs = 'eng+ces'; $hint = 'eng+ces'; }
                                elseif (preg_match('/(mav|hu\b|hung)/', $fn)) { $langs = 'eng+hun'; $hint = 'eng+hun'; }
                                $meta['logs'][] = 'OCR: inferring Tesseract langs from filename -> ' . $hint;
                            }
                            $tessOpts = (function_exists('env') ? env('TESSERACT_OPTS') : getenv('TESSERACT_OPTS')) ?: '--psm 6';
                            $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$scalePath) . ' stdout -l ' . escapeshellarg((string)$langs) . ' ' . (string)$tessOpts;
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') {
                                $textBlob = (string)$out;
                                $meta['logs'][] = 'OCR: used Tesseract (' . (string)$langs . ' ' . (string)$tessOpts . '), ' . strlen((string)$textBlob) . ' chars';
                            } else {
                                $meta['logs'][] = 'OCR: Tesseract not available or returned empty (cmd failed): ' . $cmd;
                            }
                        }
                        // If Tesseract returned very little text, try a secondary language combo commonly useful in EU tickets
                        if ($textBlob !== '' && strlen((string)trim((string)$textBlob)) < 40) {
                            $retryLangs = 'eng+fra';
                            if (preg_match('/(trenitalia|fs\b|it|ital)/', strtolower((string)$safe))) { $retryLangs = 'eng+ita'; }
                            $meta['logs'][] = 'OCR: very low text yield; retrying with ' . $retryLangs;
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            $cmd2 = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest) . ' stdout -l ' . escapeshellarg($retryLangs);
                            $out2 = @shell_exec($cmd2 . ' 2>&1');
                            if (is_string($out2) && trim($out2) !== '') {
                                $textBlob2 = (string)$out2;
                                if (strlen($textBlob2) > strlen((string)$textBlob)) {
                                    $textBlob = $textBlob2;
                                    $meta['logs'][] = 'OCR: retry improved to ' . strlen((string)$textBlob) . ' chars';
                                } else {
                                    $meta['logs'][] = 'OCR: retry did not improve yield';
                                }
                            } else {
                                $meta['logs'][] = 'OCR: retry failed';
                            }
                        }
                        // If still empty, escalate to LLM Vision OCR (Groq/OpenAI-compatible) when enabled (only if not already tried)
                        if ($textBlob === '' && !$visionTried) {
                            try {
                                $vision = new \App\Service\Ocr\LlmVisionOcr();
                                [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                                foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                                if (is_string($vText) && trim($vText) !== '') { $textBlob = (string)$vText; }
                            } catch (\Throwable $e) {
                                $meta['logs'][] = 'OCR Vision exception: ' . $e->getMessage();
                            }
                        }
                    }
                } catch (\Throwable $e) { $textBlob = ''; }
                if ($textBlob === '') { $meta['logs'][] = 'OCR: No text extracted from upload'; }

                if ($textBlob !== '') {
                    // Normalize spaces to improve downstream matching
                    $textBlob = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $textBlob) ?? $textBlob;
                    $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                        new \App\Service\TicketExtraction\HeuristicsExtractor(),
                        new \App\Service\TicketExtraction\LlmExtractor(),
                    ], 0.66);
                    $er = $broker->run($textBlob);
                    $meta['extraction_provider'] = $er->provider;
                    $meta['extraction_confidence'] = $er->confidence;
                    $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                    // Convert to _auto shape for UI
                    $auto = [];
                    foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                    $meta['_auto'] = $auto;
                    // New: parse multi-leg segments from OCR text to assist missed-connection selection
                    try {
                        $tp = new \App\Service\TicketParseService();
                        $segAuto = $tp->parseSegmentsFromText($textBlob);
                        if (!empty($segAuto)) {
                            $meta['_segments_auto'] = $segAuto;
                            // Prefill journey segments minimally if empty
                            if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = []; }
                            foreach ($segAuto as $s) {
                                $journey['segments'][] = [
                                    'from' => (string)($s['from'] ?? ''),
                                    'to' => (string)($s['to'] ?? ''),
                                    'schedDep' => (string)($s['schedDep'] ?? ''),
                                    'schedArr' => (string)($s['schedArr'] ?? ''),
                                    'depDate' => (string)($s['depDate'] ?? ''),
                                    'arrDate' => (string)($s['arrDate'] ?? ''),
                                ];
                            }
                            $meta['logs'][] = 'AUTO: detected ' . count($segAuto) . ' segment(s) from ticket text.';
                        }
                        // Parse identifiers (PNR/order) from OCR text and optional barcode when image
                        $ids = $tp->extractIdentifiers($textBlob);
                        if (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                            $bc = $tp->parseBarcode($dest);
                            if (!empty($bc['payload'])) {
                                $meta['_barcode'] = [ 'format' => $bc['format'], 'chars' => strlen((string)$bc['payload']) ];
                                $idsBC = $tp->extractIdentifiers((string)$bc['payload']);
                                $ids = array_merge($ids, $idsBC);
                            }
                        }
                        if (!empty($ids)) {
                            $meta['_identifiers'] = $ids;
                            if (!empty($ids['pnr'])) { $journey['bookingRef'] = (string)$ids['pnr']; $meta['logs'][] = 'AUTO: bookingRef from PNR'; }
                            if (empty($form['ticket_no']) && !empty($ids['order_no'])) { $form['ticket_no'] = (string)$ids['order_no']; $meta['logs'][] = 'AUTO: ticket_no from order_no'; }
                        }
                        // Extract passenger snapshot (adults/children and names) for group tickets
                        $passengerData = $tp->extractPassengerData($textBlob);
                        if (!empty($passengerData)) {
                            $meta['_passengers_auto'] = $passengerData;
                            $journey['passengerCount'] = count($passengerData);
                            $journey['passengers'] = $passengerData;
                            $journey['isGroupTicket'] = true;
                        }
                        // If dep_date missing, infer from dates found in text
                        $datesFound = $tp->extractDates($textBlob);
                        if (!empty($datesFound)) {
                            $firstDate = (string)$datesFound[0];
                            if (empty($form['dep_date'])) { $form['dep_date'] = $firstDate; $meta['logs'][] = 'AUTO: dep_date inferred from text dates'; }
                            if (empty($meta['_auto']['dep_date']['value'])) { $meta['_auto']['dep_date'] = ['value' => $firstDate, 'source' => 'parser:dates']; }
                        }
                        // Attempt soft link to existing journey by PNR + dep_date
                        try {
                            $pnr = isset($ids['pnr']) ? (string)$ids['pnr'] : ((string)($journey['bookingRef'] ?? ''));
                            $journeyDate = (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? ''));
                            if ($pnr !== '' && $journeyDate !== '') {
                                $tjoin = new \App\Service\TicketJoinService();
                                $hint = $tjoin->tryLinkToExistingJourney($pnr, $journeyDate, $passengerData ?? []);
                                if (!empty($hint['matched'])) { $meta['logs'][] = 'JOIN: candidate link by PNR+date'; }
                            }
                        } catch (\Throwable $e) { /* ignore link errors */ }
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'WARN: segment/id parsing failed: ' . $e->getMessage();
                    }
                    // Propagate country hint from extraction into journey for exemptions profile
                    try {
                        $opCountry = isset($auto['operator_country']['value']) ? (string)$auto['operator_country']['value'] : '';
                        if ($opCountry !== '') {
                            if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = [[]]; }
                            $journey['segments'][0]['country'] = $opCountry;
                            $journey['country']['value'] = $opCountry;
                            $meta['logs'][] = 'AUTO: journey country set from operator_country=' . $opCountry;
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                    // If extraction confidence is low or core fields missing, try Vision OCR re-OCR to improve text
                    $coreKeys = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'];
                    $haveCore = true; foreach ($coreKeys as $ck) { if (empty($auto[$ck]['value'] ?? '')) { $haveCore = false; break; } }
                    if (($er->confidence < 0.5 || !$haveCore) && in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                        try {
                            $vision = new \App\Service\Ocr\LlmVisionOcr();
                            [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                            foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                            if (is_string($vText) && trim($vText) !== '') {
                                $textBlobVision = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$vText) ?? (string)$vText;
                                $er2 = $broker->run($textBlobVision);
                                $meta['logs'] = array_merge($meta['logs'] ?? [], $er2->logs);
                                // Decide if vision-based extraction is better (higher confidence or more core fields)
                                $auto2 = [];
                                foreach ($er2->fields as $k => $v) { if ($v !== null && $v !== '') { $auto2[$k] = ['value' => $v, 'source' => $er2->provider]; } }
                                $haveCore2 = true; foreach ($coreKeys as $ck) { if (empty($auto2[$ck]['value'] ?? '')) { $haveCore2 = false; break; } }
                                $better = ($er2->confidence > $er->confidence) || ($haveCore2 && !$haveCore);
                                if ($better) {
                                    $er = $er2; $auto = $auto2; $meta['_auto'] = $auto;
                                    $meta['extraction_provider'] = $er2->provider;
                                    $meta['extraction_confidence'] = $er2->confidence;
                                    $meta['logs'][] = 'AUTO: using Vision OCR text for extraction';
                                }
                            }
                        } catch (\Throwable $e) {
                            $meta['logs'][] = 'OCR Vision retry failed: ' . $e->getMessage();
                        }
                    }
                    // Refill TRIN 3.2 fields from AUTO, clearing when missing
                    foreach ([
                        'dep_date','dep_time','dep_station','arr_station','arr_time',
                        'train_no','ticket_no','price'
                    ] as $rk) {
                        $form[$rk] = isset($meta['_auto'][$rk]['value']) ? (string)$meta['_auto'][$rk]['value'] : '';
                    }
                    // If multiple segments exist, prepare a simple select list for missed-connection station (arrivals of intermediate legs)
                    if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
                        $choices = [];
                        $segs = (array)$meta['_segments_auto'];
                        // Offer all arrival stations except the last segment's arrival (miss occurs at a change point)
                        $lastIdx = count($segs) - 1;
                        foreach ($segs as $idx => $s) {
                            $arr = (string)($s['to'] ?? '');
                            if ($arr === '' || $idx === $lastIdx) { continue; }
                            $label = $arr;
                            $choices[$arr] = $label;
                        }
                        if (!empty($choices)) { $form['_miss_conn_choices'] = $choices; }
                    }
                    // Light-touch: also reflect operator hints when available
                    if (!empty($meta['_auto']['operator']['value'])) { $form['operator'] = (string)$meta['_auto']['operator']['value']; }
                    if (!empty($meta['_auto']['operator_country']['value'])) { $form['operator_country'] = (string)$meta['_auto']['operator_country']['value']; }
                    if (!empty($meta['_auto']['operator_product']['value'])) { $form['operator_product'] = (string)$meta['_auto']['operator_product']['value']; }

                    // Append a normalized record to webroot/data/tickets.ndjson for offline GROQ queries
                    try {
                        $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                        if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                        $record = [
                            '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1(($safe ?? '') . microtime(true)), 0, 6),
                            '_type' => 'ticket',
                            'operator' => (string)($meta['_auto']['operator']['value'] ?? ''),
                            'operator_country' => (string)($meta['_auto']['operator_country']['value'] ?? ''),
                            'operator_product' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                            'dep_station' => (string)($meta['_auto']['dep_station']['value'] ?? ''),
                            'arr_station' => (string)($meta['_auto']['arr_station']['value'] ?? ''),
                            'dep_date' => (string)($meta['_auto']['dep_date']['value'] ?? ''),
                            'dep_time' => (string)($meta['_auto']['dep_time']['value'] ?? ''),
                            'arr_time' => (string)($meta['_auto']['arr_time']['value'] ?? ''),
                            'train_no' => (string)($meta['_auto']['train_no']['value'] ?? ''),
                            'ticket_no' => (string)($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? '')),
                            'booking_ref' => (string)($journey['bookingRef'] ?? ''),
                            'price' => (string)($meta['_auto']['price']['value'] ?? ''),
                            'segments' => (array)($meta['_segments_auto'] ?? []),
                            'passengers' => (array)($meta['_passengers_auto'] ?? []),
                            'identifiers' => (array)($meta['_identifiers'] ?? []),
                            'extraction_provider' => (string)($meta['extraction_provider'] ?? ''),
                            'extraction_confidence' => (float)($meta['extraction_confidence'] ?? 0.0),
                            'source' => [
                                'format' => $ext,
                                'original_filename' => $name,
                                'saved_filename' => $safe,
                                'ocr_chars' => (int)strlen((string)$textBlob),
                                'barcode' => isset($meta['_barcode']) ? $meta['_barcode'] : null,
                            ],
                            'createdAt' => date('c'),
                        ];
                        // For forward-compat: also wrap in tickets[] for multi-ticket grouping experiments
                        $ticketsWrap = [
                            'tickets' => [[
                                'pnr' => (string)($journey['bookingRef'] ?? ''),
                                'passenger_count' => isset($journey['passengerCount']) ? (int)$journey['passengerCount'] : count((array)($meta['_passengers_auto'] ?? [])),
                                'passengers' => (array)($meta['_passengers_auto'] ?? []),
                            ]],
                        ];
                        $line = json_encode(array_merge($record, $ticketsWrap), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        @file_put_contents($datasetDir . 'tickets.ndjson', $line, FILE_APPEND | LOCK_EX);
                        $meta['logs'][] = 'Dataset: appended to /data/tickets.ndjson';
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'Dataset append failed: ' . $e->getMessage();
                    }
                } else {
                    // Fallback: derive basics from filename conventions
                    $fname = strtolower((string)pathinfo($safe, PATHINFO_FILENAME));
                    $fname = preg_replace('/[^a-z0-9_\-]+/', '_', $fname);
                    $fname = str_replace(['__','-'], '_', $fname);
                    $country = null; $operator = null; $product = null;
                    if (str_starts_with($fname, 'sncf_')) { $country = 'FR'; $operator = 'SNCF'; }
                    if (str_starts_with($fname, 'db_')) { $country = 'DE'; $operator = 'DB'; }
                    if (str_starts_with($fname, 'dsb_')) { $country = 'DK'; $operator = 'DSB'; }
                    if (str_starts_with($fname, 'sj_')) { $country = 'SE'; $operator = 'SJ'; }
                    if (str_contains($fname, '_tgv_') || str_ends_with($fname, '_tgv')) { $product = 'TGV'; }
                    elseif (str_contains($fname, '_ice_')) { $product = 'ICE'; }
                    elseif (preg_match('/(^|_)ic(_|$)/', $fname)) { $product = 'IC'; }
                    elseif (preg_match('/(^|_)re(_|$)/', $fname)) { $product = 'RE'; }
                    $parts = preg_split('/[_]+/', $fname) ?: [];
                    if (count($parts) >= 2) {
                        $from = ucfirst($parts[count($parts)-2] ?? '');
                        $to = ucfirst($parts[count($parts)-1] ?? '');
                        if ($from && $to) { $form['dep_station'] = str_replace('_', ' ', $from); $form['arr_station'] = str_replace('_', ' ', $to); }
                    }
                    if ($operator) { $form['operator'] = $operator; }
                    if ($country) { $form['operator_country'] = $country; }
                    if ($product) { $form['operator_product'] = $product; }
                    // Dataset entry for no-text case to aid troubleshooting
                    try {
                        $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                        if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                        $record = [
                            '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1(($safe ?? '') . microtime(true)), 0, 6),
                            '_type' => 'ticket',
                            'operator' => (string)($operator ?? ''),
                            'operator_country' => (string)($country ?? ''),
                            'operator_product' => (string)($product ?? ''),
                            'dep_station' => (string)($form['dep_station'] ?? ''),
                            'arr_station' => (string)($form['arr_station'] ?? ''),
                            'extraction_provider' => 'none',
                            'extraction_confidence' => 0.0,
                            'source' => [
                                'format' => $ext,
                                'original_filename' => $name,
                                'saved_filename' => $safe,
                                'ocr_chars' => 0,
                            ],
                            'createdAt' => date('c'),
                            'status' => 'no_ocr_text'
                        ];
                        $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        @file_put_contents($datasetDir . 'tickets.ndjson', $line, FILE_APPEND | LOCK_EX);
                        $meta['logs'][] = 'Dataset: appended placeholder (no OCR text)';
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'Dataset append failed: ' . $e->getMessage();
                    }
                }
            }

            // Handle multi-ticket uploads (TRIN 4) to support multiple transactions per journey
            $multiUploaded = 0;
            try { $allUploaded = $this->request->getUploadedFiles(); } catch (\Throwable $e) { $allUploaded = []; }
            $multiFiles = [];
            if (is_array($allUploaded) && isset($allUploaded['multi_ticket_upload']) && is_array($allUploaded['multi_ticket_upload'])) {
                $multiFiles = $allUploaded['multi_ticket_upload'];
            } else {
                $raw = $this->request->getData('multi_ticket_upload');
                if (is_array($raw)) { $multiFiles = $raw; }
            }
            if (!empty($multiFiles)) {
                if (!isset($meta['_multi_tickets']) || !is_array($meta['_multi_tickets'])) { $meta['_multi_tickets'] = []; }
                foreach ($multiFiles as $mf) {
                    if (!($mf instanceof \Psr\Http\Message\UploadedFileInterface)) { continue; }
                    if ($mf->getError() !== UPLOAD_ERR_OK) { continue; }
                    $name2 = (string)($mf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                    $safe2 = preg_replace('/[^A-Za-z0-9._-]/', '_', $name2) ?: ('ticket_' . bin2hex(random_bytes(4)));
                    $destDir2 = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($destDir2)) { @mkdir($destDir2, 0775, true); }
                    $dest2 = $destDir2 . DIRECTORY_SEPARATOR . $safe2;
                    try { $mf->moveTo($dest2); } catch (\Throwable $e) { continue; }
                    $multiUploaded++;
                    $meta['logs'][] = 'Upload saved (multi): ' . (string)$safe2;
                    // Minimal OCR/extraction for each extra ticket
                    $text2 = '';
                    $ext2 = strtolower((string)pathinfo($dest2, PATHINFO_EXTENSION));
                    try {
                        if ($ext2 === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($dest2);
                            $text2 = $pdf->getText() ?? '';
                        } elseif (in_array($ext2, ['txt','text'], true)) {
                            $text2 = (string)@file_get_contents($dest2);
                        } else {
                            // Image path: try Tesseract with fallback
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                            $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest2) . ' stdout -l ' . escapeshellarg((string)$langs);
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') { $text2 = (string)$out; }
                        }
                    } catch (\Throwable $e) { $text2 = ''; }
                    $text2 = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$text2) ?? (string)$text2;
                    $summary = [ 'file' => $safe2, 'provider' => null, 'pnr' => null, 'dep_date' => null, 'passengers' => [], 'segments' => [] ];
                    if ($text2 !== '') {
                        $broker2 = new \App\Service\TicketExtraction\ExtractorBroker([
                            new \App\Service\TicketExtraction\HeuristicsExtractor(),
                            new \App\Service\TicketExtraction\LlmExtractor(),
                        ], 0.66);
                        $erx = $broker2->run($text2);
                        $summary['provider'] = $erx->provider;
                        $tp2 = new \App\Service\TicketParseService();
                        $ids2 = $tp2->extractIdentifiers($text2);
                        $summary['pnr'] = (string)($ids2['pnr'] ?? '');
                        $segs2 = $tp2->parseSegmentsFromText($text2);
                        $summary['segments'] = $segs2;
                        $pax2 = $tp2->extractPassengerData($text2);
                        $summary['passengers'] = $pax2;
                        $dates2 = $tp2->extractDates($text2);
                        $summary['dep_date'] = (string)($dates2[0] ?? '');
                        // Try to link
                        try {
                            $pnr2 = $summary['pnr'] ?: (string)($journey['bookingRef'] ?? '');
                            $date2 = $summary['dep_date'] ?: (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? ''));
                            if ($pnr2 !== '' && $date2 !== '') {
                                (new \App\Service\TicketJoinService())->tryLinkToExistingJourney($pnr2, $date2, $pax2);
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                        // Append dataset line for this ticket too
                        try {
                            $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                            if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                            $rec2 = [
                                '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1($safe2 . microtime(true)), 0, 6),
                                '_type' => 'ticket',
                                'dep_date' => $summary['dep_date'],
                                'booking_ref' => $summary['pnr'],
                                'segments' => $segs2,
                                'passengers' => $pax2,
                                'extraction_provider' => (string)$erx->provider,
                                'source' => [ 'format' => $ext2, 'original_filename' => $name2, 'saved_filename' => $safe2 ],
                                'createdAt' => date('c'),
                                'tickets' => [[ 'pnr' => $summary['pnr'], 'passenger_count' => count($pax2), 'passengers' => $pax2 ]],
                            ];
                            @file_put_contents($datasetDir . 'tickets.ndjson', json_encode($rec2, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
                        } catch (\Throwable $e) { /* ignore dataset errors */ }
                    }
                    $meta['_multi_tickets'][] = $summary;
                }
                if ($multiUploaded > 0) { $didUpload = true; $session->write('flow.justUploaded', true); }
            }

        // Removed duplicate upload handling block to prevent stale 3.2 values persisting across uploads

            // Minimal applicant/journey form fields to drive reimbursement PDF filling
            $allowedFormKeys = [
                'name','email','operator','dep_date','dep_station','arr_station','dep_time','arr_time',
                'train_no','ticket_no','price','actual_arrival_date','actual_dep_time','actual_arr_time',
                'missed_connection_station',
                // TRIN 4 – non-completed travel confirmation
                'delayLikely60',
                // operator extra
                'operator_country','operator_product',
                // simple expenses capture (optional)
                'expense_meals','expense_hotel','expense_alt_transport','expense_other',
                // TRIN 3 – CIV/liability screening
                'hasValidTicket','safetyMisconduct','forbiddenItemsOrAnimals','customsRulesBreached','operatorStampedDisruptionProof',
                // TRIN 4 – Art. 12 / contracts
                'isThroughTicket','separateContractsDisclosed','singleTxn','sellerType',
                // TRIN 6 – Optional exemption continuation
                'continue_national_rules',
                // TRIN 5 – Remedies (Art. 18)
                'remedyChoice','trip_cancelled_return_to_origin','refund_requested','refund_form_selected',
                'reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min',
                'reroute_extra_costs','reroute_extra_costs_amount','reroute_extra_costs_currency','downgrade_occurred','downgrade_comp_basis',
                // TRIN 6 – Compensation
                'delayAtFinalMinutes','compensationBand','voucherAccepted','operatorExceptionalCircumstances','minThresholdApplies',
                // TRIN 8 – Assistance/expenses
                'meal_offered','hotel_offered','overnight_needed','blocked_train_alt_transport','alt_transport_provided',
                'extra_expense_upload','expense_breakdown_meals','expense_breakdown_hotel_nights','expense_breakdown_local_transport','expense_breakdown_other_amounts','expense_breakdown_currency',
                'delay_confirmation_received','delay_confirmation_upload','extraordinary_claimed','extraordinary_type',
                // TRIN 9 – Auto EU request flags
                'request_refund','request_comp_60','request_comp_120','request_expenses',
                // TRIN 9 – Interests (persist UI state)
                'info_requested_pre_purchase',
                'interest_coc','interest_fastest','interest_fares','interest_pmr','interest_bike','interest_class','interest_disruption','interest_facilities','interest_through','interest_complaint',
                // TRIN 9 – Hook answers (persist UI echo)
                'coc_acknowledged','coc_evidence_upload','civ_marking_present',
                'fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                'multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity',
                'pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing',
                'bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket',
                'fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status',
                'preinformed_disruption','preinfo_channel','realtime_info_seen',
                'facilities_delivered_status','facility_impact_note','connection_time_realistic',
                'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
                // TRIN 6 – simplified Art. 12 UI state
                'seller_channel','same_transaction',
                'complaint_channel_seen','complaint_already_filed','complaint_receipt_upload','submit_via_official_channel',
                // Other
                'purchaseChannel',
                // TRIN 10 – PMR
                'pmrUser','assistancePromised','assistanceDelivered',
                // TRIN 11 – GDPR/attachments/info
                'gdprConsent','additionalInfo'
            ];
            foreach ($allowedFormKeys as $k) {
                $v = $this->request->getData($k);
                if ($v === null || $v === '') { continue; }
                // Avoid attempting to stringify uploaded files (handled separately below)
                if ($v instanceof \Psr\Http\Message\UploadedFileInterface) { continue; }
                if (is_string($v) || is_numeric($v) || is_bool($v)) {
                    $form[$k] = (string)$v;
                }
                // Ignore arrays/objects for these keys
            }

            // Map 3.2.7 Ticket Number(s)/Booking Reference to journey.bookingRef when sensible
            // - If a single token is provided, treat it as the booking reference (PNR)
            // - If multiple distinct tokens are provided, do NOT set bookingRef; instead hint shared_pnr_scope=Nej when unknown
            $ticketField = (string)($this->request->getData('ticket_no') ?? ($form['ticket_no'] ?? ''));
            if (is_string($ticketField)) {
                $val = trim($ticketField);
                if ($val !== '') {
                    $tokens = preg_split('/[\s,;]+/', $val) ?: [];
                    $tokens = array_values(array_filter(array_map('trim', $tokens), function($s){ return $s !== ''; }));
                    $unique = array_values(array_unique($tokens));
                    if (count($unique) === 1) {
                        if (empty($journey['bookingRef'])) {
                            $journey['bookingRef'] = (string)$unique[0];
                            $meta['logs'][] = 'AUTO: bookingRef set from 3.2.7 ticket_no field';
                        }
                    } elseif (count($unique) > 1) {
                        $cur = isset($meta['shared_pnr_scope']) ? strtolower((string)$meta['shared_pnr_scope']) : '';
                        if ($cur === '' || $cur === 'unknown' || $cur === 'ved ikke' || $cur === '-') {
                            $meta['shared_pnr_scope'] = 'Nej';
                            $meta['logs'][] = 'AUTO: shared_pnr_scope=Nej (multiple booking refs in 3.2.7)';
                        }
                    }
                }
            }

            // TRIN 1 — persist EU delay minutes if present in this POST (e.g., when uploading after entering delay)
            if ($this->request->getData('delay_min_eu') !== null && $this->request->getData('delay_min_eu') !== '') {
                $compute['delayMinEU'] = (int)$this->request->getData('delay_min_eu');
            }

            // If user clicked the TRIN 10 calculate/update button, persist delay and band explicitly
            if ($this->request->getData('delayAtFinalMinutes') !== null) {
                $form['delayAtFinalMinutes'] = (string)(int)$this->request->getData('delayAtFinalMinutes');
                $compute['delayMinEU'] = (int)$form['delayAtFinalMinutes'];
            }
            if ($this->request->getData('compensationBand') !== null) {
                $form['compensationBand'] = (string)$this->request->getData('compensationBand');
            }
            if ($this->request->getData('voucherAccepted') !== null) {
                $form['voucherAccepted'] = $this->truthy($this->request->getData('voucherAccepted')) ? '1' : '';
            }

            // Persist inline passenger edits back to meta['_passengers_auto'] if provided
            $paxEdited = $this->request->getData('passenger');
            if (is_array($paxEdited) && !empty($meta['_passengers_auto']) && is_array($meta['_passengers_auto'])) {
                $orig = (array)$meta['_passengers_auto'];
                foreach ($paxEdited as $idx => $pEdit) {
                    if (!is_array($pEdit)) { continue; }
                    $i = is_numeric($idx) ? (int)$idx : null; if ($i === null || !array_key_exists($i, $orig)) { continue; }
                    $name = isset($pEdit['name']) ? trim((string)$pEdit['name']) : null;
                    $isC = !empty($pEdit['is_claimant']);
                    if ($name !== null) { $orig[$i]['name'] = $name; }
                    $orig[$i]['is_claimant'] = $isC ? true : false;
                }
                $meta['_passengers_auto'] = $orig;
                $journey['passengers'] = $orig;
                $journey['passengerCount'] = count($orig);
            }

            // Handle TRIN 8 uploads
            try {
                $u1 = $this->request->getUploadedFile('extra_expense_upload');
            } catch (\Throwable $e) { $u1 = null; }
            try {
                $u2 = $this->request->getUploadedFile('delay_confirmation_upload');
            } catch (\Throwable $e) { $u2 = null; }
            $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            if ($u1 && $u1->getError() === \UPLOAD_ERR_OK) {
                $name = $u1->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('exp_') . '_' . $safe);
                $u1->moveTo($target);
                $form['extra_expense_upload'] = $target;
            } elseif (($raw = $this->request->getData('extra_expense_upload')) && is_string($raw)) {
                $form['extra_expense_upload'] = $raw;
            }
            if ($u2 && $u2->getError() === \UPLOAD_ERR_OK) {
                $name = $u2->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('dcr_') . '_' . $safe);
                $u2->moveTo($target);
                $form['delay_confirmation_upload'] = $target;
            } elseif (($raw2 = $this->request->getData('delay_confirmation_upload')) && is_string($raw2)) {
                $form['delay_confirmation_upload'] = $raw2;
            }

            // TRIN 9 – Art. 9: Handle uploads (CoC and complaint receipt)
            try { $u3 = $this->request->getUploadedFile('coc_evidence_upload'); } catch (\Throwable $e) { $u3 = null; }
            try { $u4 = $this->request->getUploadedFile('complaint_receipt_upload'); } catch (\Throwable $e) { $u4 = null; }
            if ($u3 && $u3->getError() === \UPLOAD_ERR_OK) {
                $name = $u3->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('coc_') . '_' . $safe);
                $u3->moveTo($target);
                $form['coc_evidence_upload'] = $target;
            } elseif (($r3 = $this->request->getData('coc_evidence_upload')) && is_string($r3)) { $form['coc_evidence_upload'] = $r3; }
            if ($u4 && $u4->getError() === \UPLOAD_ERR_OK) {
                $name = $u4->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('rcp_') . '_' . $safe);
                $u4->moveTo($target);
                $form['complaint_receipt_upload'] = $target;
            } elseif (($r4 = $this->request->getData('complaint_receipt_upload')) && is_string($r4)) { $form['complaint_receipt_upload'] = $r4; }

            // TRIN 6 – Art. 12 (NYT flow): persist and map to evaluator-compatible hooks
            // Seller channel (operator/retailer/unknown) → journey.seller_type + seller_type_* hooks
            $sellerChannel = (string)($this->request->getData('seller_channel') ?? '');
            if ($sellerChannel === 'operator') {
                $journey['seller_type'] = 'operator';
                $meta['seller_type_operator'] = 'Ja';
                $meta['seller_type_agency'] = 'Nej';
            } elseif ($sellerChannel === 'retailer') {
                $journey['seller_type'] = 'agency';
                $meta['seller_type_operator'] = 'Nej';
                $meta['seller_type_agency'] = 'Ja';
            } elseif ($sellerChannel === 'unknown') {
                $meta['seller_type_operator'] = 'Ved ikke';
                $meta['seller_type_agency'] = 'Ved ikke';
            }
            // If bookingRef already present, infer single transaction automatically by seller role
            if (!empty($journey['bookingRef'])) {
                if (($journey['seller_type'] ?? null) === 'operator') {
                    $meta['single_txn_operator'] = 'Ja';
                } elseif (($journey['seller_type'] ?? null) === 'agency') {
                    $meta['single_txn_retailer'] = 'Ja';
                }
                // Also reflect this in hook 13 for clarity
                $meta['single_booking_reference'] = 'Ja';
                $meta['shared_pnr_scope'] = 'Ja';
            }
            // Same transaction (only asked when multiple PNRs) → single_txn_* hooks depending on seller
            $sameTxn = (string)($this->request->getData('same_transaction') ?? '');
            if ($sameTxn === 'yes' || $sameTxn === 'no') {
                if ($sellerChannel === 'operator') {
                    $meta['single_txn_operator'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                    $meta['single_txn_retailer'] = 'no';
                } elseif ($sellerChannel === 'retailer') {
                    $meta['single_txn_operator'] = 'no';
                    $meta['single_txn_retailer'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                } else {
                    // Unknown seller: set both to unknown unless explicitly no
                    $meta['single_txn_operator'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                    $meta['single_txn_retailer'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                }
            }
            // TRIN 4+5 simplified: through_ticket_disclosure (yes/no) and separate_contract_notice (yes/no)
            // Accept both new names and legacy *_bool for backwards compatibility
            $sepRaw = (string)($this->request->getData('separate_contract_notice') ?? $this->request->getData('separate_contract_notice_bool') ?? '');
            if ($sepRaw === 'yes') { $meta['separate_contract_notice'] = 'Ja'; }
            elseif ($sepRaw === 'no') { $meta['separate_contract_notice'] = 'Nej'; }

            $ttdRaw = (string)($this->request->getData('through_ticket_disclosure') ?? $this->request->getData('through_ticket_disclosure_bool') ?? '');
            if ($ttdRaw === 'yes') { $meta['through_ticket_disclosure'] = 'Ja'; }
            elseif ($ttdRaw === 'no') { $meta['through_ticket_disclosure'] = 'Nej'; }
            // Propagate any debug/auto answers for 9–13 and related hooks
            $art12Keys = [
                // 'through_ticket_disclosure' intentionally excluded – mapped above from boolean
                'single_txn_operator','single_txn_retailer','separate_contract_notice',
                'connection_time_realistic','one_contract_schedule','contact_info_provided','responsibility_explained'
            ];
            foreach ($art12Keys as $k) {
                $val = $this->request->getData($k);
                if ($val === null || $val === '') { continue; }
                $vv = is_string($val) ? trim($val) : (string)$val;
                if ($vv === 'Ved ikke' || $vv === '-') { $vv = 'unknown'; }
                if ($vv === '') { $vv = 'unknown'; }
                $meta[$k] = $vv;
            }

            // TRIN 9 – Map Art. 9 hooks into $meta so evaluator can auto-complete
            $art9Keys = [
                'coc_acknowledged','civ_marking_present','fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                'multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity',
                'pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing',
                'bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket',
                'fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status',
                'preinformed_disruption','preinfo_channel','realtime_info_seen',
                'promised_facilities','facilities_delivered_status','facility_impact_note',
                'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
                'complaint_channel_seen','complaint_already_filed','submit_via_official_channel'
            ];
            foreach ($art9Keys as $k) {
                $val = $this->request->getData($k);
                if ($val === null || $val === '') { continue; }
                // promised_facilities[] stays as array in meta for echoing
                if (is_array($val)) {
                    $meta[$k] = array_values(array_map('strval', $val));
                    continue;
                }
                $vv = is_string($val) ? trim($val) : (string)$val;
                if ($vv === 'Ved ikke' || $vv === '-') { $vv = 'unknown'; }
                if ($vv === '') { $vv = 'unknown'; }

                // Important: Don't let Art. 9 UI override TRIN 6 answers that already set meta
                // - If TRIN 6 has already provided a definite yes/no for a hook, keep it
                // - Special-case through_ticket_disclosure: Art. 9 uses legacy values
                //   ("Gennemgående"/"Særskilte"/"Ved ikke"). Ignore those here so
                //   the TRIN 6 boolean (yes/no = disclosure clear before purchase) wins.
                $hasDefinite = isset($meta[$k]) && !in_array(strtolower((string)$meta[$k]), ['','unknown','ved ikke','-'], true);
                if ($k === 'through_ticket_disclosure') {
                    $low = mb_strtolower($vv);
                    $isLegacyChoice = in_array($low, ['gennemgående','gennemgaende','særskilte','saerskilte','separate','ved ikke','unknown','-'], true);
                    if ($isLegacyChoice) {
                        // Only set if not already answered by TRIN 6; map to unknown to avoid polluting
                        if (!$hasDefinite) { $meta[$k] = 'unknown'; }
                        continue;
                    }
                }
                // For all other hooks: only overwrite if not already set to a definite value
                if ($hasDefinite) { continue; }
                $meta[$k] = $vv;
            }
            // Keep promised_facilities[] in $form for checkbox echoing
            $pf = $this->request->getData('promised_facilities');
            if (is_array($pf)) {
                $form['promised_facilities'] = array_values(array_map('strval', $pf));
            }

            // If user indicated no missed connection or incident is not delay/cancellation, clear station
            if (empty($incident['missed']) || !in_array(($incident['main'] ?? ''), ['delay','cancellation'], true)) {
                unset($form['missed_connection_station']);
            }

            $session->write('flow.journey', $journey);
            $session->write('flow.meta', $meta);
            $session->write('flow.compute', $compute);
            $session->write('flow.flags', $flags);
            $session->write('flow.incident', $incident);
            $session->write('flow.form', $form);

            // PRG: After an upload attempt, redirect to avoid resubmission and ensure anchor navigation to TRIN 4
            if ($didUpload || $attemptedUpload || ($multiUploaded ?? 0) > 0) {
                $session->write('flow.justUploaded', true);
                // Hint UI to show OCR debug card even if _auto is empty
                $meta['justUploaded'] = true;
                $session->write('flow.meta', $meta);
                // For AJAX hook refresh, skip redirect and let the action render hooks fragment below
                if (!($isAjaxHooks && $this->request->is('ajax')))
                {
                    // Preserve debug unlock query flags across redirect
                    $qs = [];
                    foreach (['allow_official','debug'] as $qk) {
                        $qv = $this->request->getQuery($qk);
                        if ($qv !== null && $qv !== '') { $qs[$qk] = $qv; }
                    }
                    return $this->redirect(array_merge(['action' => 'one', '#' => 's4'], $qs));
                }
            }
        }

        // Compute dependent outputs for right-hand side preview
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
            if (!empty($inferRes['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $inferRes['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: scope infer failed: ' . $e->getMessage();
        }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Derive AUTO Art.12 hooks (2,3,5,6,7,8,13) before evaluation; user inputs remain authoritative
        try {
            $auto12 = (new \App\Service\Art12AutoDeriver())->apply($journey, $meta);
            $meta = $auto12['meta'];
            if (!empty($auto12['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $auto12['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: Art12AutoDeriver failed: ' . $e->getMessage();
        }
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta);
        // Evaluate Art. 9 unconditionally (see above rationale)
        try {
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art9 = null;
            $meta['logs'][] = 'WARN: Art9Evaluator failed: ' . $e->getMessage();
        }
    $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
    $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);

        // Compute claim (used by hooks panel) before potential AJAX short-circuit
        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $mm)) { $currency = strtoupper($mm[1]); }
        // Build claim input similar to claims page, including expenses and fee logic
        $ticketPriceStr = (string)($journey['ticketPrice']['value'] ?? ($form['price'] ?? '0 EUR'));
        $ticketPriceAmount = (float)preg_replace('/[^0-9.]/', '', $ticketPriceStr);
        $delayForClaim = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
        $expensesIn = [
            'meals' => (float)($form['expense_breakdown_meals'] ?? ($form['expense_meals'] ?? 0)),
            'hotel' => (float)($form['expense_breakdown_hotel_nights'] ?? 0) > 0 ? 0.0 : (float)($form['expense_hotel'] ?? 0),
            // When hotel_nights is provided, we defer amount capture to receipts; otherwise use simple value if any
            'alt_transport' => (float)($form['expense_breakdown_local_transport'] ?? ($form['expense_alt_transport'] ?? 0)),
            'other' => (float)($form['expense_breakdown_other_amounts'] ?? ($form['expense_other'] ?? 0)),
        ];
        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => $ticketPriceAmount,
            'trip' => [ 'through_ticket' => true, 'legs' => [] ],
            'disruption' => [
                'delay_minutes_final' => $delayForClaim,
                'eu_only' => (bool)($compute['euOnly'] ?? true),
                'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                'extraordinary' => (bool)($compute['extraordinary'] ?? false),
                'self_inflicted' => false,
            ],
            'choices' => [
                // Mirror some TRIN 7 choices if needed later
                'wants_refund' => ((string)($form['remedyChoice'] ?? '') === 'refund_return'),
                'wants_reroute_same_soonest' => ((string)($form['remedyChoice'] ?? '') === 'reroute_soonest'),
                'wants_reroute_later_choice' => ((string)($form['remedyChoice'] ?? '') === 'reroute_later'),
            ],
            'expenses' => $expensesIn,
            'already_refunded' => 0,
            // Apply 25% fee on expenses only, not on compensation base (ticket price)
            'service_fee_mode' => 'expenses_only',
        ]);

        // Build a simple multi-ticket grouped overview for hooks panel
        $multiTickets = (array)($meta['_multi_tickets'] ?? []);
        // Compose a current-ticket summary (from _auto/meta)
        $currSummary = [
            'file' => (string)($form['_ticketFilename'] ?? ''),
            'pnr' => (string)($journey['bookingRef'] ?? ''),
            'dep_date' => (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')),
            'passengers' => (array)($meta['_passengers_auto'] ?? []),
            'segments' => (array)($meta['_segments_auto'] ?? []),
        ];
        $allForGrouping = [];
        if (!empty($currSummary['pnr']) || !empty($currSummary['dep_date'])) { $allForGrouping[] = $currSummary; }
        foreach ($multiTickets as $mt) {
            $allForGrouping[] = [
                'file' => (string)($mt['file'] ?? ''),
                'pnr' => (string)($mt['pnr'] ?? ''),
                'dep_date' => (string)($mt['dep_date'] ?? ''),
                'passengers' => (array)($mt['passengers'] ?? []),
                'segments' => (array)($mt['segments'] ?? []),
            ];
        }
        $groupedTickets = [];
        if (!empty($allForGrouping)) {
            $groups = (new \App\Service\TicketJoinService())->groupTickets($allForGrouping);
            $groupedTickets = $groups;
        }

        // Recommend claim form (EU vs national) based on exemptions/overrides
        try {
            $countryCtx = (string)($journey['country']['value'] ?? ($form['operator_country'] ?? ''));
            $scopeCtx = (string)($profile['scope'] ?? '');
            $opCtx = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
            $prodCtx = (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''));
            $delayCtx = (int)($compute['delayMinEU'] ?? 0);
            $selector = new \App\Service\ClaimFormSelector();
            $formDecision = $selector->select([
                'country' => $countryCtx,
                'scope' => $scopeCtx,
                'operator' => $opCtx,
                'product' => $prodCtx,
                'delayMin' => $delayCtx,
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            $formDecision = ['form' => 'eu_standard_claim', 'reason' => 'EU baseline (fallback)', 'notes' => ['Selector error: ' . $e->getMessage()]];
        }

        if ($isAjaxHooks && $this->request->is('ajax')) {
            // Render only the hooks panel element (no layout) for live updates
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('hooks_panel');
            $this->set(compact('profile','art12','art9','refund','refusion','claim','journey','meta','compute','flags','incident','form','groupedTickets'));
            return $this->render();
        }

        // Claim already computed above (also used by AJAX partial)

    // Reasons and additional info
        $reason_delay = (!empty($incident['main']) && $incident['main'] === 'delay');
        $reason_cancellation = (!empty($incident['main']) && $incident['main'] === 'cancellation');
        $reason_missed_conn = !empty($incident['missed']);
        $add = [];
        if (!empty($flags['travel_state'])) {
            $label = [
                'completed' => 'Rejsen er afsluttet',
                'ongoing' => 'Rejsen er påbegyndt (i tog / skift)',
                'before_start' => 'Jeg skal til at påbegynde rejsen',
            ][$flags['travel_state']] ?? $flags['travel_state'];
            $add[] = 'TRIN 1: ' . $label;
        }
        $sel = [];
        if ($reason_delay) $sel[] = 'Delay';
        if ($reason_cancellation) $sel[] = 'Cancellation';
        if ($reason_missed_conn) $sel[] = 'Missed connection';
        if (!empty($sel)) $add[] = 'TRIN 2: ' . implode(', ', $sel);
        $additional_info = implode(' | ', $add);

        // TRIN 3/5 – Liability/CIV screening and gating (two-step)
        $hasValidTicket = isset($form['hasValidTicket']) ? (bool)$this->truthy($form['hasValidTicket']) : true;
        $safetyMisconduct = isset($form['safetyMisconduct']) ? (bool)$this->truthy($form['safetyMisconduct']) : false;
        $forbiddenItemsOrAnimals = isset($form['forbiddenItemsOrAnimals']) ? (bool)$this->truthy($form['forbiddenItemsOrAnimals']) : false;
        // true means complied with admin/customs rules
        $customsRulesBreached = isset($form['customsRulesBreached']) ? (bool)$this->truthy($form['customsRulesBreached']) : true;
        // Step B: operator-stamped proof or other documentary basis
        $operatorProof = isset($form['operatorStampedDisruptionProof']) ? (bool)$this->truthy($form['operatorStampedDisruptionProof']) : false;
        // Step A: self-inflicted if any of these hold
        $selfInflicted = (!$hasValidTicket) || $safetyMisconduct || $forbiddenItemsOrAnimals || (!$customsRulesBreached);
        // Combined gate: require not self-inflicted AND operator proof
        $liability_ok = (!$selfInflicted) && $operatorProof;

        // TRIN 4 – Missed connection + separate contracts disclosure (Art. 12 note)
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);

        // TRIN 5–6 – Entitlements and choices
        $delayAtFinal = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
    // Do NOT block entitlements based on Art. 12 outcome. Even if Art. 12 is negative
    // for a missed connection, the user may still seek remedies/assistance on other bases.
    $art12_block = false;
    $allow_refund = (!$missedConnBlock) && (($compute['delayMinEU'] ?? 0) >= 60 || $reason_cancellation);
    $allow_compensation = (!$missedConnBlock) && ($delayAtFinal >= 60);
        // Section 4 choice resolution (enforce not both): prefer explicit remedyChoice=refund; else compensation; else alt_costs
        $section4_choice = null;
        $remedyChoice = $form['remedyChoice'] ?? null; // refund | reroute_soonest | reroute_later
        if ($remedyChoice === 'refund' && $allow_refund) {
            $section4_choice = 'refund';
        } elseif (!empty($form['compensationBand']) && $allow_compensation) {
            $section4_choice = 'compensation';
        } elseif (!empty($form['reroute_extra_costs'])) {
            $section4_choice = 'alt_costs';
        }

        // GDPR consent
        $gdpr_ok = isset($form['gdprConsent']) ? (bool)$this->truthy($form['gdprConsent']) : false;

        $this->set(compact(
            'journey','meta','compute','flags','incident','form',
            'profile','art12','art9','refund','refusion','claim',
            'reason_delay','reason_cancellation','reason_missed_conn','additional_info',
            'liability_ok','missedConnBlock','allow_refund','allow_compensation','section4_choice','gdpr_ok','delayAtFinal','art12_block','selfInflicted','operatorProof','formDecision'
        ));
        $this->set(compact('groupedTickets'));
        $this->viewBuilder()->setTemplate('one');
        return null;
    }

    /**
     * Split-step: Details (TRIN 1–2 basic journey and incident)
     */
    public function details(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $journey = (array)$session->read('flow.journey') ?: [];
        $compute = (array)$session->read('flow.compute') ?: ['euOnly' => true];
        $flags = (array)$session->read('flow.flags') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            $compute['euOnly'] = (bool)$this->request->getData('eu_only');
            $compute['delayMinEU'] = (int)($this->request->getData('delay_min_eu') ?? 0);
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $compute['extraordinary'] = (bool)$this->request->getData('extraordinary');
            $main = (string)($this->request->getData('incident_main') ?? '');
            if (in_array($main, ['delay','cancellation',''], true)) { $incident['main'] = $main; }
            $incident['missed'] = $this->truthy($this->request->getData('missed_connection'));
            $session->write('flow.compute', $compute);
            $session->write('flow.flags', $flags);
            $session->write('flow.incident', $incident);
            return $this->redirect(['action' => 'screening']);
        }
        $this->set(compact('journey','compute','flags','incident'));
        return null;
    }

    /**
     * Split-step: Screening (TRIN 3–4 liability/CIV + Art. 12 disclosure)
     */
    public function screening(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            foreach (['hasValidTicket','safetyMisconduct','forbiddenItemsOrAnimals','customsRulesBreached','isThroughTicket','separateContractsDisclosed','singleTxn','sellerType'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'choices']);
        }
        // compute gating preview
    $hasValidTicket = isset($form['hasValidTicket']) ? (bool)$this->truthy($form['hasValidTicket']) : true;
    $safetyMisconduct = isset($form['safetyMisconduct']) ? (bool)$this->truthy($form['safetyMisconduct']) : false;
    $forbiddenItemsOrAnimals = isset($form['forbiddenItemsOrAnimals']) ? (bool)$this->truthy($form['forbiddenItemsOrAnimals']) : false;
    $customsRulesBreached = isset($form['customsRulesBreached']) ? (bool)$this->truthy($form['customsRulesBreached']) : true;
    $operatorProof = isset($form['operatorStampedDisruptionProof']) ? (bool)$this->truthy($form['operatorStampedDisruptionProof']) : false;
    $selfInflicted = (!$hasValidTicket) || $safetyMisconduct || $forbiddenItemsOrAnimals || (!$customsRulesBreached);
    $liability_ok = (!$selfInflicted) && $operatorProof;
        $reason_missed_conn = !empty($incident['missed']);
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);
        $this->set(compact('form','liability_ok','missedConnBlock'));
        return null;
    }

    /**
     * Split-step: Choices (TRIN 5–6 remedies/compensation)
     */
    public function choices(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $compute = (array)$session->read('flow.compute') ?: [];
        $form = (array)$session->read('flow.form') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            foreach (['remedyChoice','refund_requested','refund_form_selected','reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min','reroute_extra_costs','reroute_extra_costs_amount','reroute_extra_costs_currency','downgrade_occurred','downgrade_comp_basis','delayAtFinalMinutes','compensationBand','voucherAccepted'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'extras']);
        }
        $delayAtFinal = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
        $reason_cancellation = (!empty($incident['main']) && $incident['main'] === 'cancellation');
        $reason_missed_conn = !empty($incident['missed']);
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);
        $allow_refund = (!$missedConnBlock) && (($compute['delayMinEU'] ?? 0) >= 60 || $reason_cancellation);
        $allow_compensation = (!$missedConnBlock) && ($delayAtFinal >= 60);
        $this->set(compact('form','delayAtFinal','allow_refund','allow_compensation'));
        return null;
    }

    /**
     * Split-step: Extras (TRIN 7–10 purchase channel, PMR, expenses)
     */
    public function extras(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            foreach (['purchaseChannel','pmrUser','assistancePromised','assistanceDelivered','expense_meals','expense_hotel','expense_alt_transport','expense_other'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'applicant']);
        }
        $this->set(compact('form'));
        return null;
    }

    /**
     * Split-step: Applicant & payout (TRIN 11)
     */
    public function applicant(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            $keys = ['firstName','lastName','address_street','address_no','address_country','address_postalCode','address_city','contact_email','contact_phone','payoutPreference','iban','bic','accountHolderName','otherPaymentUsed'];
            foreach ($keys as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'consent']);
        }
        $this->set(compact('form'));
        return null;
    }

    /**
     * Split-step: Consent & additional info (TRIN 12)
     */
    public function consent(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            foreach (['gdprConsent','additionalInfo'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'summary']);
        }
        $gdpr_ok = isset($form['gdprConsent']) ? (bool)$this->truthy($form['gdprConsent']) : false;
        $this->set(compact('form','gdpr_ok'));
        return null;
    }

    /**
     * Normalize various truthy values coming from HTML forms.
     */
    private function truthy(mixed $v): bool
    {
        if (is_bool($v)) { return $v; }
        $s = is_string($v) ? strtolower(trim($v)) : '';
        return in_array($s, ['1','true','on','yes','ja','y'], true);
    }
}

?>