<?php
declare(strict_types=1);

namespace App\Service\ReceiptExtraction;

/**
 * Receipt AI suggester: takes an uploaded receipt file (PDF or image),
 * extracts best-effort text (pdftotext or vision OCR), and asks an LLM to
 * produce structured fields (amount/currency/date/merchant/nights).
 */
final class ReceiptAiSuggester
{
    /**
     * @param array<string,mixed> $context
     * @return array{fields: array<string,mixed>, confidence: float, logs: string[]}
     */
    public function suggestFromFile(string $path, array $context = []): array
    {
        $logs = [];
        $path = (string)$path;
        if ($path === '' || !is_file($path)) {
            return ['fields' => [], 'confidence' => 0.0, 'logs' => ['Receipt file missing']];
        }

        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        $text = '';

        if ($ext === 'pdf') {
            $pdftotext = (function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH')) ?: 'pdftotext';
            $cmd = escapeshellarg((string)$pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg((string)$path) . ' - 2>&1';
            $out = @shell_exec($cmd);
            if (is_string($out) && trim($out) !== '') {
                $text = (string)$out;
                $logs[] = 'Receipt text: pdftotext (-layout)';
            } else {
                $logs[] = 'Receipt text: pdftotext returned empty';
            }
        } else {
            // Prefer vision OCR for images (user preference: AI > classic OCR).
            try {
                $visionEnabled = strtolower((string)((function_exists('env') ? env('LLM_VISION_ENABLED') : getenv('LLM_VISION_ENABLED')) ?? '0')) === '1';
                if ($visionEnabled) {
                    $vision = new \App\Service\Ocr\LlmVisionOcr();
                    [$vText, $vLogs] = $vision->extractTextFromImage($path);
                    foreach ((array)$vLogs as $lg) { $logs[] = (string)$lg; }
                    if (is_string($vText) && trim($vText) !== '') {
                        $text = (string)$vText;
                        $logs[] = 'Receipt text: vision OCR';
                    }
                }
            } catch (\Throwable $e) {
                $logs[] = 'Receipt vision OCR failed: ' . $e->getMessage();
            }

            // Optional fallback to local Tesseract if vision is disabled or returned empty.
            if (trim($text) === '') {
                try {
                    $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                    if (!$tess || $tess === '') {
                        $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                        $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                    }
                    $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                    $tessOpts = (function_exists('env') ? env('TESSERACT_OPTS') : getenv('TESSERACT_OPTS')) ?: '--psm 6';
                    $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$path) . ' stdout -l ' . escapeshellarg((string)$langs) . ' ' . (string)$tessOpts . ' 2>&1';
                    $out = @shell_exec($cmd);
                    if (is_string($out) && trim($out) !== '') {
                        $text = (string)$out;
                        $logs[] = 'Receipt text: tesseract fallback';
                    }
                } catch (\Throwable $e) {
                    $logs[] = 'Receipt tesseract failed: ' . $e->getMessage();
                }
            }
        }

        if (trim($text) === '') {
            return ['fields' => [], 'confidence' => 0.1, 'logs' => array_merge($logs, ['Receipt text extraction empty'])];
        }

        $llm = new LlmReceiptExtractor();
        $res = $llm->extract($text, $context);
        $res['logs'] = array_merge($logs, (array)($res['logs'] ?? []));
        return $res;
    }
}

