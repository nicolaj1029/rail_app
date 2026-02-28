<?php
declare(strict_types=1);

namespace App\Service\ReceiptExtraction;

use Cake\Http\Client;

/**
 * Extract structured fields from receipts/invoices using the configured OpenAI-compatible provider (incl. Groq).
 *
 * This is intentionally "suggestion-grade": we return best-effort JSON fields + a confidence score.
 * Callers should only auto-fill empty UI fields, never overwrite explicit user input.
 */
final class LlmReceiptExtractor
{
    /**
     * @return array{fields: array<string,mixed>, confidence: float, logs: string[]}
     */
    public function extract(string $text, array $context = []): array
    {
        $logs = [];
        $provider = $this->env('LLM_PROVIDER') ?: 'disabled';
        $apiKey   = $this->env('OPENAI_API_KEY');
        $baseUrlRaw = (string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        $model    = $this->env('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $timeout  = (int)($this->env('LLM_TIMEOUT_SECONDS') ?: 20);
        $forceJson = strtolower((string)($this->env('LLM_FORCE_JSON') ?? '1')) !== '0';

        if ($provider === 'disabled' || !$apiKey) {
            return ['fields' => [], 'confidence' => 0.0, 'logs' => ['LLM receipt extractor disabled (no API key/provider)']];
        }

        $isAzure = strtolower((string)$provider) === 'azure';
        $baseUrl = $this->sanitizeBaseUrl($baseUrlRaw);
        $baseUrl = $this->normalizeBaseUrl($baseUrl, strtolower((string)$provider));
        $logs[] = 'LLM receipt base=' . $baseUrl . ', model=' . $model;
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';

        // Safety: limit input size
        $text = mb_substr($text, 0, 12000);
        $prompt = $this->buildPrompt($text, $context);

        try {
            $client = new Client(['timeout' => $timeout]);

            if ($isAzure) {
                $url = rtrim($baseUrl, '/') . "/openai/deployments/{$model}/chat/completions?api-version={$apiVersion}";
                $headers = [
                    'Content-Type' => 'application/json',
                    'api-key' => $apiKey,
                ];
            } else {
                $url = rtrim($baseUrl, '/') . '/chat/completions';
                $headers = [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ];
            }

            $messages = [
                ['role' => 'system', 'content' => 'You extract structured fields from receipts/invoices. Output strict JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $maxTokens = (int)($this->env('LLM_MAX_TOKENS') ?: 600);
            $body = [
                'model' => $model,
                'temperature' => 0,
                'top_p' => 1,
                'messages' => $messages,
            ];
            if ($forceJson) {
                $body['response_format'] = ['type' => 'json_object'];
            }
            $body = $this->applyReasoningParams($body, $model, $maxTokens);

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if (!$resp->isOk()) {
                $logs[] = 'LLM HTTP error: ' . $resp->getStatusCode();
                return ['fields' => [], 'confidence' => 0.1, 'logs' => $logs];
            }
            $json = $resp->getJson();
            $content = (string)($json['choices'][0]['message']['content'] ?? '');
            if ($content === '') {
                $logs[] = 'LLM empty content';
                return ['fields' => [], 'confidence' => 0.1, 'logs' => $logs];
            }

            $parsed = $this->tryParseJson($content);
            if (!is_array($parsed)) {
                $parsed = $this->salvageJson($content);
                if (!is_array($parsed)) {
                    $logs[] = 'LLM returned non-JSON content';
                    return ['fields' => [], 'confidence' => 0.2, 'logs' => $logs];
                }
                $logs[] = 'LLM non-JSON cleaned via salvage';
            }

            $fields = $this->normalizeFields($parsed);
            $conf = $this->confidenceFromFields($fields);
            $logs[] = 'LLM receipt extractor success: keys=' . implode(',', array_keys(array_filter($fields, fn($v) => $v !== null && $v !== '')));
            return ['fields' => $fields, 'confidence' => $conf, 'logs' => $logs];
        } catch (\Throwable $e) {
            $logs[] = 'LLM exception: ' . $e->getMessage();
            return ['fields' => [], 'confidence' => 0.2, 'logs' => $logs];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeFields(array $parsed): array
    {
        $out = [
            'amount_total' => null,
            'currency' => null,
            'date' => null,
            'merchant' => null,
            'hotel_nights' => null,
            'notes' => null,
        ];

        $amt = $parsed['amount_total'] ?? null;
        if (is_string($amt)) { $amt = trim($amt); }
        if (is_numeric($amt)) { $out['amount_total'] = (string)((float)$amt); }
        elseif (is_string($amt) && $amt !== '') { $out['amount_total'] = $amt; }

        $cur = $parsed['currency'] ?? null;
        if (is_string($cur)) { $cur = strtoupper(trim($cur)); }
        $out['currency'] = (is_string($cur) && $cur !== '') ? $cur : null;

        $date = $parsed['date'] ?? null;
        if (is_string($date)) { $date = trim($date); }
        $out['date'] = (is_string($date) && $date !== '') ? $date : null;

        $mer = $parsed['merchant'] ?? null;
        if (is_string($mer)) { $mer = trim($mer); }
        $out['merchant'] = (is_string($mer) && $mer !== '') ? $mer : null;

        $n = $parsed['hotel_nights'] ?? null;
        if (is_numeric($n)) { $out['hotel_nights'] = (int)$n; }
        elseif (is_string($n) && ctype_digit(trim($n))) { $out['hotel_nights'] = (int)trim($n); }

        if (isset($parsed['notes']) && is_array($parsed['notes'])) {
            $notes = array_values(array_filter(array_map('strval', $parsed['notes'])));
            $out['notes'] = $notes ? $notes : null;
        }

        return $out;
    }

    private function confidenceFromFields(array $fields): float
    {
        $score = 0;
        $max = 4;
        if (!empty($fields['amount_total'])) { $score++; }
        if (!empty($fields['currency'])) { $score++; }
        if (!empty($fields['date'])) { $score++; }
        if (!empty($fields['merchant'])) { $score++; }
        return $max > 0 ? ($score / $max) : 0.0;
    }

    private function buildPrompt(string $text, array $context): string
    {
        $schema = [
            'amount_total' => 'number|null (decimal, dot)',
            'currency' => 'string|null (ISO 4217, e.g. EUR, DKK)',
            'date' => 'YYYY-MM-DD or null',
            'merchant' => 'string|null',
            'hotel_nights' => 'integer|null (only if explicitly present)',
            'notes' => 'array of strings (optional)',
        ];
        $schemaText = json_encode($schema, JSON_UNESCAPED_SLASHES);
        $defaultCur = strtoupper(trim((string)($context['default_currency'] ?? '')));
        $hint = $defaultCur !== '' ? ("Default currency hint: " . $defaultCur) : '';

        return <<<EOT
Extract the following fields from the OCR/text of a receipt/invoice.
Return STRICT JSON with exactly these keys and values matching the types:
{$schemaText}

Rules:
- Use ISO date YYYY-MM-DD when a date is present.
- amount_total is the final paid amount (grand total). If multiple totals exist, pick the most explicit "TOTAL"/"TOTALT"/"SUM" etc.
- currency must be ISO 4217. If currency symbol is present (€, kr), infer likely ISO if unambiguous. {$hint}
- If unknown, use null.
- Do not add extra keys. Output JSON only.

TEXT:
------
{$text}
------
EOT;
    }

    /** Ensure Groq base URL includes /openai/v1; leave others as-is. */
    private function normalizeBaseUrl(string $baseUrl, string $provider): string
    {
        $b = rtrim($baseUrl, '/');
        if ($provider === 'groq' || str_contains(strtolower($b), 'api.groq.com')) {
            if (!str_ends_with($b, '/openai/v1')) {
                if (str_ends_with($b, '/v1')) { $b = substr($b, 0, -3) . 'openai/v1'; }
                elseif (!str_contains($b, '/openai/')) { $b .= '/openai/v1'; }
            }
        }
        return $b;
    }

    private function sanitizeBaseUrl(string $baseUrl): string
    {
        $b = trim($baseUrl);
        $b = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $b) ?? $b;
        $b = preg_replace('/[\s\x{2010}-\x{2015}\x{2026}]+$/u', '', $b) ?? $b;
        $b = str_replace('\\', '/', $b);
        if (!preg_match('#^https?://#i', $b)) { $b = 'https://' . ltrim($b, '/'); }
        return rtrim($b, '/');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function tryParseJson(string $content): ?array
    {
        $content = trim($content);
        $content = $this->normalizeJsonWhitespace($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?/i', '', $content);
            $content = preg_replace('/```$/', '', (string)$content);
            $content = trim((string)$content);
        }
        $parsed = json_decode($content, true);
        return is_array($parsed) ? $parsed : null;
    }

    private function normalizeJsonWhitespace(string $s): string
    {
        return preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $s) ?? $s;
    }

    /**
     * Best-effort salvage: find the first {...} JSON object substring.
     * @return array<string,mixed>|null
     */
    private function salvageJson(string $content): ?array
    {
        $s = trim($content);
        $s = $this->normalizeJsonWhitespace($s);
        if (!preg_match('/\\{[\\s\\S]*\\}/', $s, $m)) { return null; }
        $cand = (string)($m[0] ?? '');
        $parsed = json_decode($cand, true);
        return is_array($parsed) ? $parsed : null;
    }

    private function applyReasoningParams(array $body, string $model, int $maxTokens): array
    {
        if ($this->isReasoningModel($model)) {
            $effort = strtolower((string)($this->env('LLM_REASONING_EFFORT') ?? 'low'));
            if (!in_array($effort, ['low','medium','high'], true)) { $effort = 'low'; }
            $body['max_completion_tokens'] = $maxTokens;
            $body['reasoning_effort'] = $effort;
        } else {
            $body['max_tokens'] = $maxTokens;
        }
        return $body;
    }

    private function isReasoningModel(string $model): bool
    {
        return str_contains(strtolower($model), 'gpt-oss');
    }

    private function env(string $key): ?string
    {
        if (function_exists('env')) {
            $v = env($key);
            return $v !== null ? (string)$v : null;
        }
        $v = getenv($key);
        return $v !== false ? (string)$v : null;
    }
}

