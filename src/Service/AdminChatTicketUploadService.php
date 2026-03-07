<?php
declare(strict_types=1);

namespace App\Service;

use App\Service\Ocr\LlmVisionOcr;
use App\Service\TicketExtraction\ExtractorBroker;
use App\Service\TicketExtraction\HeuristicsExtractor;
use App\Service\TicketExtraction\LlmExtractor;
use Psr\Http\Message\UploadedFileInterface;

final class AdminChatTicketUploadService
{
    /**
     * @param array<string,mixed> $flow
     * @return array{ok:bool,message:string,flow:array<string,mixed>}
     */
    public function handleUpload(UploadedFileInterface $file, array $flow): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'message' => 'Upload fejlede med kode ' . $file->getError() . '.',
                'flow' => $flow,
            ];
        }

        $originalName = trim((string)($file->getClientFilename() ?? ''));
        if ($originalName === '') {
            $originalName = 'ticket_' . bin2hex(random_bytes(4));
        }
        $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'bmp', 'tif', 'tiff', 'heic', 'txt', 'text'];
        if (!in_array($ext, $allowed, true)) {
            return [
                'ok' => false,
                'message' => 'Filtypen understøttes ikke i admin-chatten endnu. Brug PDF, TXT, JPG eller PNG.',
                'flow' => $flow,
            ];
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: ('ticket_' . bin2hex(random_bytes(4)) . '.' . $ext);
        $targetDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . 'admin_chat_' . bin2hex(random_bytes(4)) . '_' . $safeName;

        try {
            $file->moveTo($targetPath);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Kunne ikke gemme uploaden: ' . $e->getMessage(),
                'flow' => $flow,
            ];
        }

        $form = (array)($flow['form'] ?? []);
        $meta = (array)($flow['meta'] ?? []);
        $logs = (array)($meta['logs'] ?? []);

        $textBlob = $this->extractTextFromFile($targetPath, $logs);
        $auto = [];
        $provider = '';
        $confidence = 0.0;
        if ($textBlob !== '') {
            $broker = $this->buildExtractorBroker();
            $result = $broker->run($textBlob);
            $provider = (string)$result->provider;
            $confidence = (float)$result->confidence;
            $logs = array_merge($logs, $result->logs);
            foreach ($result->fields as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $auto[$key] = [
                    'value' => $value,
                    'source' => $provider,
                    'provider' => $provider,
                    'confidence' => $confidence,
                ];
            }
        } else {
            $logs[] = 'Admin chat upload: ingen tekst ekstraheret fra uploaden.';
        }

        $ticketMode = (string)($form['ticket_upload_mode'] ?? 'ticket');
        if ($ticketMode === 'seasonpass') {
            $seasonFiles = (array)($meta['season_pass_files'] ?? []);
            $seasonFiles[] = [
                'file' => basename($targetPath),
                'original' => $originalName,
                'uploaded_at' => date('c'),
            ];
            $meta['season_pass_files'] = $seasonFiles;
        }

        $form['_ticketUploaded'] = '1';
        $form['_ticketFilename'] = basename($targetPath);
        $form['_ticketOriginalName'] = $originalName;

        $mapped = ['operator','operator_country','operator_product','dep_date','dep_time','dep_station','arr_station','arr_time','train_no','ticket_no','price'];
        foreach ($mapped as $key) {
            if (!isset($auto[$key]['value'])) {
                continue;
            }
            $value = (string)$auto[$key]['value'];
            if ($key === 'operator_country') {
                try {
                    $normalized = (new CountryNormalizer())->toIso2($value);
                    if ($normalized !== '') {
                        $value = $normalized;
                        $auto[$key]['value'] = $normalized;
                    }
                } catch (\Throwable $e) {
                }
            }
            $form[$key] = $value;
        }

        $meta['_auto'] = array_merge((array)($meta['_auto'] ?? []), $auto);
        $meta['extraction_provider'] = $provider !== '' ? $provider : ($meta['extraction_provider'] ?? '');
        $meta['extraction_confidence'] = $confidence > 0 ? $confidence : ($meta['extraction_confidence'] ?? 0);
        $meta['_ocr_text'] = $textBlob !== '' ? mb_substr($textBlob, 0, 4000, 'UTF-8') : (string)($meta['_ocr_text'] ?? '');
        $meta['logs'] = $logs;
        $meta['admin_chat_upload'] = [
            'path' => $targetPath,
            'file' => basename($targetPath),
            'original' => $originalName,
            'uploaded_at' => date('c'),
            'provider' => $provider,
            'confidence' => $confidence,
            'mapped_fields' => array_keys($auto),
        ];

        $flow['form'] = $form;
        $flow['meta'] = $meta;

        $fieldList = array_keys($auto);
        $fieldSummary = $fieldList === [] ? 'ingen felter' : implode(', ', array_slice($fieldList, 0, 6));

        return [
            'ok' => true,
            'message' => 'Upload behandlet: ' . $originalName . '. Ekstraherede felter: ' . $fieldSummary . '.',
            'flow' => $flow,
        ];
    }

    /**
     * @param array<int,string> $logs
     */
    private function extractTextFromFile(string $path, array &$logs): string
    {
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));

        try {
            if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($path);
                $text = (string)($pdf->getText() ?? '');
                if (trim($text) !== '') {
                    $logs[] = 'Admin chat upload: PDF embedded text extracted.';
                    return $this->normalizeText($text);
                }

                $pdftotext = function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH');
                $pdftotext = $pdftotext ?: 'pdftotext';
                $cmd = escapeshellarg((string)$pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' -';
                $out = @shell_exec($cmd . ' 2>&1');
                if (is_string($out) && trim($out) !== '') {
                    $logs[] = 'Admin chat upload: pdftotext used.';
                    return $this->normalizeText($out);
                }
            }

            if (in_array($ext, ['txt', 'text'], true)) {
                $text = @file_get_contents($path);
                if (is_string($text) && trim($text) !== '') {
                    $logs[] = 'Admin chat upload: TXT loaded directly.';
                    return $this->normalizeText($text);
                }
            }

            if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'bmp', 'tif', 'tiff', 'heic'], true)) {
                $vision = new LlmVisionOcr();
                [$vText, $vLogs] = $vision->extractTextFromImage($path);
                foreach ((array)$vLogs as $line) {
                    $logs[] = (string)$line;
                }
                if (is_string($vText) && trim($vText) !== '') {
                    $logs[] = 'Admin chat upload: vision OCR used.';
                    return $this->normalizeText($vText);
                }

                $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                if (!$tess || $tess === '') {
                    $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                    $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                }
                $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng+dan+deu+fra+ita';
                $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg($path) . ' stdout -l ' . escapeshellarg((string)$langs) . ' --psm 6';
                $out = @shell_exec($cmd . ' 2>&1');
                if (is_string($out) && trim($out) !== '') {
                    $logs[] = 'Admin chat upload: Tesseract used (' . $langs . ').';
                    return $this->normalizeText($out);
                }
            }
        } catch (\Throwable $e) {
            $logs[] = 'Admin chat upload extraction exception: ' . $e->getMessage();
        }

        return '';
    }

    private function buildExtractorBroker(): ExtractorBroker
    {
        $alwaysMerge = strtolower((string)((function_exists('env') ? env('LLM_EXTRACTOR_ALWAYS_MERGE') : getenv('LLM_EXTRACTOR_ALWAYS_MERGE')) ?: '0')) === '1';
        $providers = [
            new HeuristicsExtractor(),
            new LlmExtractor(),
        ];
        $llmFirst = strtolower((string)((function_exists('env') ? env('LLM_EXTRACTOR_ORDER') : getenv('LLM_EXTRACTOR_ORDER')) ?: '')) === 'llm_first';
        if ($llmFirst) {
            $providers = [
                new LlmExtractor(),
                new HeuristicsExtractor(),
            ];
        }

        return new ExtractorBroker($providers, 0.66, $alwaysMerge);
    }

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $text) ?? $text;
        return trim($text);
    }
}
