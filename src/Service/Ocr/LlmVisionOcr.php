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
        $logs = [];
        if (!is_file($filePath)) { return ['', ['LLM Vision: file missing']]; }

        $enabled = strtolower((string)($this->env('LLM_VISION_ENABLED') ?? '0')) === '1';
        if (!$enabled) { return ['', ['LLM Vision: disabled']]; }

    $apiKey = $this->env('OPENAI_API_KEY');
    $baseUrl = rtrim((string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $model = $this->env('VISION_MODEL') ?: ($this->env('OPENAI_MODEL') ?: 'gpt-4o-mini');
        $provider = strtolower((string)($this->env('LLM_PROVIDER') ?: 'openai'));
        $timeout = (int)($this->env('LLM_TIMEOUT_SECONDS') ?: 20);

        if (!$apiKey) { return ['', ['LLM Vision: no API key']]; }

        $isAzure = $provider === 'azure';
        // Normalize Groq base to include /openai/v1 if missing
        if ($provider === 'groq' || str_contains(strtolower($baseUrl), 'api.groq.com')) {
            if (!str_ends_with($baseUrl, '/openai/v1')) {
                if (str_ends_with($baseUrl, '/v1')) { $baseUrl = substr($baseUrl, 0, -3) . 'openai/v1'; }
                elseif (!str_contains($baseUrl, '/openai/')) { $baseUrl .= '/openai/v1'; }
            }
        }
        $logs[] = 'LLM Vision base=' . $baseUrl . ', model=' . $model;
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';

        try {
            $bin = @file_get_contents($filePath);
            if (!is_string($bin) || $bin === '') { return ['', ['LLM Vision: empty file']]; }
            $mime = $this->guessMime($filePath);
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

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if (!$resp->isOk()) {
                return ['', ['LLM Vision: HTTP ' . $resp->getStatusCode()]];
            }
            $data = (array)$resp->getJson();
            $content = (string)($data['choices'][0]['message']['content'] ?? '');
            $text = trim($content);
            if ($text !== '') {
                return [$text, ['LLM Vision: extracted ' . strlen($text) . ' chars']];
            }
            return ['', ['LLM Vision: empty content']];
        } catch (\Throwable $e) {
            return ['', ['LLM Vision: exception ' . $e->getMessage()]];
        }
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
