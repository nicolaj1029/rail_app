<?php
declare(strict_types=1);

namespace App\Service\Ocr;

use Cake\Http\Client;

/**
 * Optional OCR via LLM Vision: sends the image to an OpenAI-compatible
 * chat/completions endpoint with image input and returns plain text.
 */
final class LlmVisionOcr
{
    /**
     * Try to extract plain text from an image file using a vision model.
     * Returns [text, logs]. If not configured or call fails, returns ["", logs].
     *
     * Env keys:
     * - LLM_VISION_ENABLED=1
     * - OPENAI_API_KEY
     * - OPENAI_BASE_URL (e.g., https://api.groq.com/openai/v1 or https://api.openai.com/v1)
     * - OPENAI_MODEL (or VISION_MODEL overrides for vision-capable model)
     * - LLM_PROVIDER=openai|groq|azure (affects headers/path)
     * - OPENAI_API_VERSION (for Azure)
     * - LLM_TIMEOUT_SECONDS
     */
    public function extractTextFromImage(string $filePath): array
    {
        return $this->callVision($filePath, 'text');
    }

    /**
     * Extract air-relevant structured signals from a ticket image/PDF.
     *
     * @return array{0:array<string,mixed>,1:array<int,string>}
     */
    public function extractAirSignalsFromImage(string $filePath): array
    {
        return $this->callVision($filePath, 'air');
    }

    /**
     * @return array{0:string|array<string,mixed>,1:array<int,string>}
     */
    private function callVision(string $filePath, string $mode): array
    {
        $logs = [];
        if (!is_file($filePath)) { return [$mode === 'air' ? [] : '', ['LLM Vision: file missing']]; }

        $enabledEnv = $this->env('LLM_VISION_ENABLED');
        $enabled = $enabledEnv === null
            ? (bool)$this->env('OPENAI_API_KEY')
            : strtolower((string)$enabledEnv) === '1';
        if (!$enabled) { return [$mode === 'air' ? [] : '', ['LLM Vision: disabled']]; }

        $apiKey = $this->env('OPENAI_API_KEY');
        $baseUrl = rtrim((string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $model = $this->env('VISION_MODEL') ?: ($this->env('OPENAI_MODEL') ?: 'gpt-4o-mini');
        $provider = strtolower((string)($this->env('LLM_PROVIDER') ?: 'openai'));
        $timeout = (int)($this->env('LLM_TIMEOUT_SECONDS') ?: 20);

        if (!$apiKey) { return [$mode === 'air' ? [] : '', ['LLM Vision: no API key']]; }

        $isAzure = $provider === 'azure';
        if ($provider === 'groq' || str_contains(strtolower($baseUrl), 'api.groq.com')) {
            if (!str_ends_with($baseUrl, '/openai/v1')) {
                if (str_ends_with($baseUrl, '/v1')) { $baseUrl = substr($baseUrl, 0, -3) . 'openai/v1'; }
                elseif (!str_contains($baseUrl, '/openai/')) { $baseUrl .= '/openai/v1'; }
            }
        }
        $logs[] = 'LLM Vision base=' . $baseUrl . ', model=' . $model;
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';

        $visionPath = $filePath;
        $cleanupPath = null;
        try {
            [$visionPath, $prepLogs, $cleanupPath] = $this->prepareVisionInput($filePath);
            $logs = array_merge($logs, $prepLogs);
            $bin = @file_get_contents($visionPath);
            if (!is_string($bin) || $bin === '') { return [$mode === 'air' ? [] : '', ['LLM Vision: empty file']]; }
            $mime = $this->guessMime($visionPath);
            $b64 = base64_encode($bin);
            $dataUrl = 'data:' . $mime . ';base64,' . $b64;

            $client = new Client(['timeout' => $timeout]);

            if ($isAzure) {
                $url = rtrim($baseUrl, '/') . "/openai/deployments/{$model}/chat/completions?api-version={$apiVersion}";
                $headers = [ 'Content-Type' => 'application/json', 'api-key' => $apiKey ];
            } else {
                $url = rtrim($baseUrl, '/') . '/chat/completions';
                $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey ];
            }

            if ($mode === 'air') {
                $body = [
                    'model' => $model,
                    'temperature' => 0,
                    'top_p' => 0,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You analyze airline boarding passes and return strict JSON only. Do not guess.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => 'Inspect this boarding pass image/PDF and extract the carrier signals if visible. Return JSON with keys operator, marketing_carrier, operating_carrier, operator_country, operator_product, notes. Use exact printed names. If only one carrier is visible, use it for operator and operating_carrier. If unsure, return null values.'],
                            ['type' => 'image_url', 'image_url' => [ 'url' => $dataUrl ]],
                        ]],
                    ],
                    'max_tokens' => 800,
                    'response_format' => ['type' => 'json_object'],
                ];
            } else {
                $body = [
                    'model' => $model,
                    'temperature' => 0,
                    'top_p' => 0,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are an OCR engine. Return only the plain text you can read from the image. Keep line breaks. No summaries.'],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => 'Extract ONLY the textual content from this ticket image. Return plain text.'],
                            ['type' => 'image_url', 'image_url' => [ 'url' => $dataUrl ]],
                        ]],
                    ],
                    'max_tokens' => 1200,
                ];
            }

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if (!$resp->isOk()) {
                return [$mode === 'air' ? [] : '', ['LLM Vision: HTTP ' . $resp->getStatusCode()]];
            }
            $data = (array)$resp->getJson();
            $content = (string)($data['choices'][0]['message']['content'] ?? '');
            if ($mode === 'air') {
                $parsed = $this->tryParseJson($content);
                if (is_array($parsed)) {
                    $fields = $this->normalizeAirVisionFields($parsed);
                    $nonEmpty = array_filter($fields, static fn($v) => $v !== null && $v !== '');
                    $logs[] = 'LLM Vision: air fields=' . implode(',', array_keys($nonEmpty));
                    return [$fields, $logs];
                }
                $logs[] = 'LLM Vision: air content not JSON';
                return [[], $logs];
            }
            $text = trim($content);
            if ($text !== '') {
                return [$text, ['LLM Vision: extracted ' . strlen($text) . ' chars']];
            }
            return ['', ['LLM Vision: empty content']];
        } catch (\Throwable $e) {
            return [$mode === 'air' ? [] : '', ['LLM Vision: exception ' . $e->getMessage()]];
        } finally {
            if ($cleanupPath && is_file($cleanupPath)) {
                @unlink($cleanupPath);
            }
        }
    }

    /**
     * @param array<string,mixed> $parsed
     * @return array<string,string|null>
     */
    private function normalizeAirVisionFields(array $parsed): array
    {
        $out = [
            'operator' => null,
            'marketing_carrier' => null,
            'operating_carrier' => null,
            'operator_country' => null,
            'operator_product' => null,
            'notes' => null,
        ];
        foreach ($out as $key => $_) {
            if (!array_key_exists($key, $parsed)) {
                continue;
            }
            $val = $parsed[$key];
            if (is_array($val)) {
                $val = implode('; ', array_map('strval', $val));
            }
            $val = trim((string)$val);
            $out[$key] = $val !== '' ? $val : null;
        }
        return $out;
    }

    /**
     * Prepare an image-like file for vision. PDFs are rendered to a temp PNG first page.
     *
     * @return array{0:string,1:array<int,string>,2:?string}
     */
    private function prepareVisionInput(string $filePath): array
    {
        $logs = [];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return [$filePath, $logs, null];
        }

        $tempPng = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vision_pdf_' . bin2hex(random_bytes(6)) . '.png';
        try {
            if (class_exists('\\Imagick')) {
                $img = new \Imagick();
                $img->setResolution(220, 220);
                $img->readImage($filePath . '[0]');
                $img->setImageFormat('png');
                $img->writeImage($tempPng);
                $img->clear();
                $img->destroy();
                if (is_file($tempPng)) {
                    $logs[] = 'LLM Vision: PDF rendered via Imagick first page';
                    return [$tempPng, $logs, $tempPng];
                }
            }
        } catch (\Throwable $e) {
            $logs[] = 'LLM Vision: Imagick PDF render failed: ' . $e->getMessage();
        }

        try {
            $pdftoppm = (string)($this->env('PDFTOPPM_PATH') ?: 'pdftoppm');
            $prefix = substr($tempPng, 0, -4);
            $cmd = escapeshellarg($pdftoppm) . ' -r 220 -png -f 1 -singlefile ' . escapeshellarg($filePath) . ' ' . escapeshellarg($prefix);
            @shell_exec($cmd . ' 2>&1');
            $candidate = $prefix . '.png';
            if (is_file($candidate)) {
                $logs[] = 'LLM Vision: PDF rendered via pdftoppm first page';
                return [$candidate, $logs, $candidate];
            }
            $alt = $prefix . '-1.png';
            if (is_file($alt)) {
                $logs[] = 'LLM Vision: PDF rendered via pdftoppm first page';
                return [$alt, $logs, $alt];
            }
        } catch (\Throwable $e) {
            $logs[] = 'LLM Vision: pdftoppm PDF render failed: ' . $e->getMessage();
        }

        return [$filePath, $logs, null];
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            'heic' => 'image/heic',
            default => 'application/octet-stream',
        };
    }

    private function env(string $key): ?string
    {
        if (function_exists('env')) { $v = env($key); return $v !== null ? (string)$v : null; }
        $v = getenv($key);
        return $v !== false ? (string)$v : null;
    }
}
