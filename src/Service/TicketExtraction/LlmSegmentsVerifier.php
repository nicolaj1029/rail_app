<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

use Cake\Http\Client;

/**
 * LlmSegmentsVerifier
 *
 * Calls an OpenAI-compatible chat API (incl. Groq) to verify/augment parsed segments.
 * Enforces JSON-only output, validates a strict schema, and normalizes to app shape.
 */
final class LlmSegmentsVerifier
{
    /**
     * Verify/augment segments using an LLM with strict JSON output.
     *
     * @param string $text Blob of relevant ticket text (trimmed)
     * @param array<int,array<string,mixed>> $parserSegments Existing parser segments
     * @param string $contextDate ISO YYYY-MM-DD if known, else ''
     * @param array<string,mixed>|null $barcode Optional decoded barcode payload
     * @param float $timeoutSeconds HTTP timeout
     * @return array{segments: array<int,array<string,mixed>>, logs: string[], error?: string}
     */
    public function verify(string $text, array $parserSegments = [], string $contextDate = '', ?array $barcode = null, float $timeoutSeconds = 12.0): array
    {
        $logs = [];
        $provider = $this->env('LLM_PROVIDER') ?: 'disabled';
        $apiKey   = $this->env('OPENAI_API_KEY');
        $baseUrlRaw = (string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        $model    = $this->env('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $temperature = (float)($this->env('LLM_TEMPERATURE') ?: 0.1);
        $forceJson = strtolower((string)($this->env('LLM_FORCE_JSON') ?? '1')) !== '0';
        $maxTokens = (int)($this->env('LLM_MAX_TOKENS') ?: 700);

        if ($provider === 'disabled' || !$apiKey) {
            return ['segments' => [], 'logs' => ['LLM verifier disabled (no API key/provider)']];
        }

        $baseUrl = $this->normalizeBaseUrl($this->sanitizeBaseUrl($baseUrlRaw), strtolower((string)$provider));
        $isAzure = strtolower((string)$provider) === 'azure';
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';
        $logs[] = 'LLM verify base=' . $baseUrl . ', model=' . $model;

        // Trim long inputs to reduce hallucinations/noise
        $textTrim = mb_substr($text, 0, 6000, 'UTF-8');
        $parserPayload = $this->safeJson($parserSegments);
        $barcodePayload = $barcode ? $this->safeJson($barcode) : '{}';

        $schema = $this->jsonSchema();
        $prompt = $this->buildPrompt($schema, $parserPayload, $textTrim, $barcodePayload, $contextDate);

        try {
            $client = new Client(['timeout' => $timeoutSeconds]);
            if ($isAzure) {
                $url = rtrim($baseUrl, '/') . "/openai/deployments/{$model}/chat/completions?api-version={$apiVersion}";
                $headers = [ 'Content-Type' => 'application/json', 'api-key' => $apiKey ];
            } else {
                $url = rtrim($baseUrl, '/') . '/chat/completions';
                $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey ];
            }

            $messages = [
                ['role' => 'system', 'content' => 'You only return valid JSON matching the provided JSON Schema. No prose.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $body = [
                'model' => $model,
                'temperature' => $temperature,
                'top_p' => 1.0,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ];
            if ($forceJson) { $body['response_format'] = ['type' => 'json_object']; }

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if (!$resp->isOk()) {
                $logs[] = 'LLM verify HTTP ' . $resp->getStatusCode();
                return ['segments' => [], 'logs' => $logs, 'error' => 'http'];
            }
            $data = (array)$resp->getJson();
            $content = (string)($data['choices'][0]['message']['content'] ?? '');
            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                $logs[] = 'LLM verify non-JSON or empty';
                return ['segments' => [], 'logs' => $logs, 'error' => 'nonjson'];
            }
            // Validate and normalize
            $validated = $this->validateAndNormalize($decoded, $contextDate);
            if (!empty($validated['error'])) {
                $logs[] = 'LLM verify rejected (schema): ' . $validated['error'];
                return ['segments' => [], 'logs' => $logs, 'error' => 'schema'];
            }
            $segs = (array)($validated['segments'] ?? []);
            $logs[] = 'LLM verify ok: ' . count($segs);
            return ['segments' => $segs, 'logs' => $logs];
        } catch (\Throwable $e) {
            $logs[] = 'LLM verify exception: ' . $e->getMessage();
            return ['segments' => [], 'logs' => $logs, 'error' => 'exception'];
        }
    }

    private function buildPrompt(string $schemaJson, string $parserJson, string $text, string $barcodeJson, string $date): string
    {
        $dateLine = $date !== '' ? ("context_date: " . $date) : '';
        return <<<EOT
Validate and, if clearly supported by input, augment journey segments. Return ONLY JSON that matches the JSON Schema below. No prose.

JSON Schema:
{$schemaJson}

INPUT:
parser_segments: {$parserJson}
pdf_text: {$this->clip($text, 2000)}
barcode_data: {$barcodeJson}
{$dateLine}

Rules:
- Use ISO8601 for times: YYYY-MM-DDTHH:MM. If date is missing, use context_date; if still missing, omit the segment.
- No invented stations. Use real station names; normalize common aliases (e.g., 'Stockholm c' -> 'Stockholm C').
- If you guess, set confidence <= 0.5 and source='llm_infer' with a brief note.
- Do not include free text outside JSON. Only return the JSON object.
EOT;
    }

    private function jsonSchema(): string
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'segments' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['from','to','schedDep','schedArr'],
                        'properties' => [
                            'from' => ['type'=>'string','minLength'=>2],
                            'to' => ['type'=>'string','minLength'=>2],
                            'schedDep' => ['type'=>'string'],
                            'schedArr' => ['type'=>'string'],
                            'trainNo' => ['type'=>'string'],
                            'operator' => ['type'=>'string'],
                            'platform' => ['type'=>'string'],
                            'pnr' => ['type'=>'string'],
                            'confidence' => ['type'=>'number'],
                            'source' => ['type'=>'string'],
                            'notes' => ['type'=>'string'],
                        ],
                    ],
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'llmModel' => ['type'=>'string'],
                        'policyWarnings' => ['type'=>'array','items'=>['type'=>'string']],
                    ],
                ],
            ],
            'required' => ['segments'],
        ];
        return json_encode($schema, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate basic shape and normalize to app format: HH:MM + depDate/arrDate.
     * @param array<string,mixed> $obj
     * @return array{segments: array<int,array<string,mixed>>, error?: string}
     */
    private function validateAndNormalize(array $obj, string $contextDate = ''): array
    {
        if (!is_array($obj) || !isset($obj['segments']) || !is_array($obj['segments'])) {
            return ['segments' => [], 'error' => 'missing segments'];
        }
        $out = [];
        foreach ($obj['segments'] as $s) {
            if (!is_array($s)) { continue; }
            $from = $this->normStation((string)($s['from'] ?? ''));
            $to   = $this->normStation((string)($s['to'] ?? ''));
            $dIso = (string)($s['schedDep'] ?? '');
            $aIso = (string)($s['schedArr'] ?? '');
            if ($from === '' || $to === '' || $from === $to) { continue; }
            // Accept either ISO or HH:MM; prefer ISO when available
            [$depDate,$depTime] = $this->splitDateTime($dIso, $contextDate);
            [$arrDate,$arrTime] = $this->splitDateTime($aIso, $contextDate);
            if ($depTime === '' || $arrTime === '') { continue; }
            $seg = [
                'from' => $from,
                'to' => $to,
                'schedDep' => $depTime,
                'schedArr' => $arrTime,
                'depDate' => $depDate,
                'arrDate' => $arrDate !== '' ? $arrDate : $depDate,
                'trainNo' => (string)($s['trainNo'] ?? ''),
            ];
            // carry through confidence/source/notes if present
            $c = (float)($s['confidence'] ?? 0.0);
            if ($c > 0) { $seg['confidence'] = $c; }
            $src = (string)($s['source'] ?? 'llm_infer');
            if ($src !== '') { $seg['source'] = $src; }
            $notes = trim((string)($s['notes'] ?? ''));
            if ($notes !== '') { $seg['notes'] = mb_substr($notes, 0, 200, 'UTF-8'); }
            $out[] = $seg;
        }
        return ['segments' => $out];
    }

    private function splitDateTime(string $val, string $fallbackDate = ''): array
    {
        $val = trim($val);
        if ($val === '') { return [$fallbackDate, '']; }
        // ISO like YYYY-MM-DDTHH:MM
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})$/', $val, $m)) {
            return [sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]), sprintf('%02d:%02d', (int)$m[4], (int)$m[5])];
        }
        // HH:MM only
        if (preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $val)) { return [$fallbackDate, $val]; }
        // 0901 or 9.01 or 9 h 01
        $val2 = str_replace(['.', 'h', ' '], [':',':',''], $val);
        if (preg_match('/^([01]?\d|2[0-3]):?(\d{2})$/', $val2, $m)) { return [$fallbackDate, sprintf('%02d:%02d', (int)$m[1], (int)$m[2])]; }
        return [$fallbackDate, ''];
    }

    private function normStation(string $name): string
    {
        $n = trim($name);
        if ($n === '') return '';
        $low = mb_strtolower($n, 'UTF-8');
        $map = [
            'københavn h' => 'København H',
            'copenhagen central' => 'København H',
            'stockholm c' => 'Stockholm C',
            'paris nord' => 'Paris Nord',
        ];
        if (isset($map[$low])) { return $map[$low]; }
        // Capitalize simple trailing letter like ' c' -> ' C'
        if (preg_match('/^(.*)\s+c$/iu', $n, $m)) { return trim($m[1]) . ' C'; }
        return $n;
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

    private function env(string $key): ?string
    {
        if (function_exists('env')) { $v = env($key); return $v !== null ? (string)$v : null; }
        $v = getenv($key);
        return $v !== false ? (string)$v : null;
    }

    private function clip(string $s, int $max): string
    {
        return mb_substr($s, 0, $max, 'UTF-8');
    }

    private function safeJson($v): string
    {
        try { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); } catch (\Throwable $e) { return '[]'; }
    }
}

?>
