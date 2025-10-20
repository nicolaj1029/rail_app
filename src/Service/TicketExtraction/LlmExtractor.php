<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

use Cake\Http\Client;

final class LlmExtractor implements ExtractorInterface
{
    public function name(): string { return 'llm'; }

    public function extract(string $text): TicketExtractionResult
    {
        $logs = [];

        // Env/config guard: if not configured, return stub result with low confidence.
    $provider = $this->env('LLM_PROVIDER') ?: 'disabled';
    $apiKey   = $this->env('OPENAI_API_KEY');
    $baseUrlRaw  = (string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        $model    = $this->env('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $timeout  = (int)($this->env('LLM_TIMEOUT_SECONDS') ?: 15);
        $forceJson = strtolower((string)($this->env('LLM_FORCE_JSON') ?? '1')) !== '0';

        if ($provider === 'disabled' || !$apiKey) {
            return new TicketExtractionResult([], 0.1, $this->name(), ['LLM extractor disabled (no API key/provider)']);
        }

        // Azure OpenAI special handling for API version and path
    $isAzure = strtolower((string)$provider) === 'azure';
    $baseUrl = $this->sanitizeBaseUrl($baseUrlRaw);
    $baseUrl = $this->normalizeBaseUrl($baseUrl, strtolower((string)$provider));
    $logs[] = 'LLM base=' . $baseUrl . ', model=' . $model;
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';

        // Safety: limit input size
        $text = mb_substr($text, 0, 8000);

        $prompt = $this->buildPrompt($text);
        // Try to enrich prompt with multilingual labels if available
        try {
            $lbl = new \App\Service\LabelsProvider();
            $hints = [
                'date' => $lbl->list('date'),
                'dep_time' => $lbl->list('dep_time'),
                'arr_time' => $lbl->list('arr_time'),
                'dep_station' => $lbl->list('dep_station'),
                'arr_station' => $lbl->list('arr_station'),
                'from' => $lbl->list('from'),
                'to' => $lbl->list('to'),
                'train_no' => $lbl->list('train_no'),
                'price' => $lbl->list('price'),
            ];
            $hintText = json_encode($hints, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if ($hintText && $hintText !== 'null') {
                $prompt .= "\n\nLanguage label hints (do not output):\n" . $hintText;
                // Log small summary for debugging
                $counts = array_map(fn($v) => is_array($v) ? count($v) : 0, $hints);
                $parts = [];
                foreach ($counts as $k => $c) { $parts[] = $k . '=' . $c; }
                $logs[] = 'LLM hints loaded: ' . implode(', ', $parts);
            }
        } catch (\Throwable $e) {
            // optional, ignore
        }

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

            // Compose messages with an extra system instruction that emphasizes label hint preference
            $messages = [
                ['role' => 'system', 'content' => 'You extract structured fields from EU train tickets. Output strict JSON only.'],
            ];
            // If hints were appended to the prompt, reinforce usage as a system directive
            $messages[] = ['role' => 'system', 'content' => 'When multilingual label hints are provided in the conversation, prefer extracting fields from text segments that are immediately preceded by or on the same line as those labels in the relevant language (e.g., From/To, Departure/Arrival date/time, Train number). Avoid substring matches and ignore code-like tokens for station names (e.g., all-caps codes or tokens containing digits). If uncertain, return null.'];
            $messages[] = ['role' => 'user', 'content' => $prompt];

            $body = [
                'model' => $model,
                'temperature' => 0,
                'top_p' => 0,
                'messages' => $messages,
                // Some providers (OpenAI-compatible incl. Groq) support JSON mode
                // Use only when enabled to avoid 400s on unsupported backends
                // Azure recent API versions support it too.
                // We'll add the key conditionally below.
                'max_tokens' => 400,
            ];

            if ($forceJson) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if ($resp->isOk()) {
                $data = (array)$resp->getJson();
                $content = (string)($data['choices'][0]['message']['content'] ?? '');
                $parsed = $this->tryParseJson($content);
                if (is_array($parsed)) {
                    $fields = $this->normalizeFields($parsed);
                    $conf = $this->confidenceFromFields($fields);
                    if (!empty($fields['__notes'])) { $logs[] = 'LLM notes: ' . (string)$fields['__notes']; unset($fields['__notes']); }
                    $logs[] = 'LLM extractor success: fields=' . implode(',', array_keys(array_filter($fields, fn($v) => $v !== null && $v !== '')));
                    return new TicketExtractionResult($fields, $conf, $this->name(), $logs);
                }
                // Robust fallback: try to salvage JSON substring from the content
                $salvaged = $this->salvageJson($content);
                if (is_array($salvaged)) {
                    $fields = $this->normalizeFields($salvaged);
                    $conf = $this->confidenceFromFields($fields);
                    $logs[] = 'LLM non-JSON cleaned via salvage';
                    return new TicketExtractionResult($fields, $conf, $this->name(), $logs);
                }
                $logs[] = 'LLM returned non-JSON content';
            } else {
                $logs[] = 'LLM HTTP error: ' . $resp->getStatusCode();
            }
        } catch (\Throwable $e) {
            $logs[] = 'LLM exception: ' . $e->getMessage();
        }

        // Fallback: low-confidence empty
        return new TicketExtractionResult([], 0.2, $this->name(), $logs);
    }

    /** Ensure Groq base URL includes /openai/v1; leave others as-is. */
    private function normalizeBaseUrl(string $baseUrl, string $provider): string
    {
        $b = rtrim($baseUrl, '/');
        if ($provider === 'groq' || str_contains(strtolower($b), 'api.groq.com')) {
            // Accept forms like https://api.groq.com or https://api.groq.com/v1 and coerce to /openai/v1
            if (!str_ends_with($b, '/openai/v1')) {
                // If it ends with /v1, replace with /openai/v1
                if (str_ends_with($b, '/v1')) { $b = substr($b, 0, -3) . 'openai/v1'; }
                elseif (!str_contains($b, '/openai/')) { $b .= '/openai/v1'; }
            }
        }
        return $b;
    }

    /** Sanitize odd punctuation and ensure https scheme for base URL. */
    private function sanitizeBaseUrl(string $baseUrl): string
    {
        $b = trim($baseUrl);
        // Replace common unicode dashes with ASCII hyphen
        $b = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $b) ?? $b;
        // Remove accidental trailing punctuation like en-dash/em-dash/ellipsis
        $b = preg_replace('/[\s\x{2010}-\x{2015}\x{2026}]+$/u', '', $b) ?? $b;
        // Replace backslashes
        $b = str_replace('\\', '/', $b);
        // If scheme missing, default to https
        if (!preg_match('#^https?://#i', $b)) { $b = 'https://' . ltrim($b, '/'); }
        return rtrim($b, '/');
    }

    /**
     * Build a strict instruction asking for JSON only.
     */
    private function buildPrompt(string $text): string
    {
        $schema = [
            'dep_station' => 'string|null',
            'arr_station' => 'string|null',
            'dep_date' => 'YYYY-MM-DD or null',
            'dep_time' => 'HH:MM 24h or null',
            'arr_time' => 'HH:MM 24h or null',
            'train_no' => 'string|null',
            'ticket_no' => 'string|null',
            'price' => 'string|null',
            'operator' => 'string|null',
            'operator_country' => 'string|null',
            'operator_product' => 'string|null',
            'notes' => 'array of strings with uncertainties or OCR ambiguities (optional)',
        ];

        $schemaText = json_encode($schema, JSON_UNESCAPED_SLASHES);
        return <<<EOT
Extract the following fields from the OCR text of a European train ticket.
Return STRICT JSON with exactly these keys and values matching the types:
{$schemaText}

Rules:
- Use ISO date YYYY-MM-DD when a date is present.
- Use 24-hour HH:MM for times.
- If unknown, put null.
- Do not add comments or extra keys. Output JSON only.

OCR TEXT:
--------
{$text}
--------
EOT;
    }

    /**
     * Try to parse JSON content, trimming code fences and whitespace.
     * @return array<string,mixed>|null
     */
    private function tryParseJson(string $content): ?array
    {
        $content = trim($content);
        // Remove markdown fences if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?/i', '', $content);
            $content = preg_replace('/```$/', '', (string)$content);
            $content = trim((string)$content);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Try to salvage a JSON object embedded in text by locating the first '{' and matching closing '}'.
     * @return array<string,mixed>|null
     */
    private function salvageJson(string $content): ?array
    {
        $s = trim($content);
        // Quick fence strip again
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```(?:json)?/i', '', $s);
            $s = preg_replace('/```$/', '', (string)$s);
            $s = trim((string)$s);
        }
        $start = strpos($s, '{');
        $end = strrpos($s, '}');
        if ($start === false || $end === false || $end <= $start) { return null; }
        $candidate = substr($s, (int)$start, (int)($end - $start + 1));
        if ($candidate === false) { return null; }
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) { return $decoded; }
        // Try to unescape common issues (e.g., trailing commas)
        $candidate = preg_replace('/,\s*([}\]])/', '$1', $candidate ?? '');
        $decoded = json_decode((string)$candidate, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Normalize field values to strings (or null) and only keep known keys.
     * @param array<string,mixed> $parsed
     * @return array<string,?string>
     */
    private function normalizeFields(array $parsed): array
    {
        $keys = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no','ticket_no','price','operator','operator_country','operator_product'];
        $out = [];
        foreach ($keys as $k) {
            $v = $parsed[$k] ?? null;
            if (is_string($v)) { $out[$k] = trim($v); }
            elseif ($v === null) { $out[$k] = null; }
            else { $out[$k] = (string)$v; }
        }
        // Capture optional notes for logs if present
        if (isset($parsed['notes']) && is_array($parsed['notes'])) {
            $notes = array_values(array_filter(array_map('strval', $parsed['notes'])));
            if (!empty($notes)) { $out['__notes'] = implode(' | ', $notes); }
        }
        return $out;
    }

    /**
     * Confidence based on number of core fields present.
     * @param array<string,?string> $fields
     */
    private function confidenceFromFields(array $fields): float
    {
        $core = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'];
        $score = 0; $max = count($core);
        foreach ($core as $k) {
            $v = $fields[$k] ?? null;
            if (is_string($v) && $v !== '') { $score++; }
        }
        return $max > 0 ? $score / $max : 0.0;
    }

    /**
     * env() helper compatible with CakePHP and PHP fallback.
     * @return string|null
     */
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
