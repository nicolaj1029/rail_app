<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

final class TransportStopNormalizer
{
    /** @var array<string,array<int,string>> */
    private static array $cache = [];

    /**
     * @return array<int,string>
     */
    public function candidateQueries(string $mode, string $query, bool $enableAi = false): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = $mode . '|' . ($enableAi ? 'ai' : 'det') . '|' . mb_strtolower($query, 'UTF-8');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $candidates = $this->deterministicCandidates($mode, $query);
        if ($enableAi) {
            $candidates = array_merge($candidates, $this->aiCandidates($mode, $query));
        }

        $clean = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value === '' || mb_strlen($value, 'UTF-8') < 2) {
                continue;
            }
            $key = mb_strtolower($value, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $value;
            if (count($clean) >= 8) {
                break;
            }
        }

        self::$cache[$cacheKey] = $clean;
        return $clean;
    }

    /**
     * @return array<int,string>
     */
    private function deterministicCandidates(string $mode, string $query): array
    {
        $original = trim($query);
        $candidates = [$original];

        $normalized = $this->normalizeWhitespace($original);
        $flattened = str_replace([',', ';', '/', '\\'], ' ', $normalized);
        $flattened = $this->normalizeWhitespace($flattened);
        if ($flattened !== '' && $flattened !== $original) {
            $candidates[] = $flattened;
        }

        $parts = array_values(array_filter(array_map(
            fn(string $part): string => $this->normalizeWhitespace($part),
            preg_split('/\s*,\s*/u', $normalized) ?: []
        ), static fn(string $part): bool => $part !== ''));

        if ($mode === 'bus') {
            $candidates = array_merge($candidates, $this->busCandidates($normalized, $flattened, $parts));
        } elseif ($mode === 'ferry') {
            $candidates = array_merge($candidates, $this->ferryCandidates($normalized, $flattened, $parts));
        } elseif ($mode === 'air') {
            $candidates = array_merge($candidates, $this->airCandidates($normalized, $flattened, $parts));
        }

        return $candidates;
    }

    /**
     * @param array<int,string> $parts
     * @return array<int,string>
     */
    private function busCandidates(string $normalized, string $flattened, array $parts): array
    {
        $candidates = [];
        $stripped = $normalized;
        $patterns = [
            '/\bcentral train station\b/ui',
            '/\bcentral station\b/ui',
            '/\btrain station\b/ui',
            '/\brailway station\b/ui',
            '/\bhauptbahnhof\b/ui',
            '/\bbahnhof\b/ui',
            '/\bhbf\b/ui',
        ];
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, ' ', $stripped) ?? $stripped;
        }
        $stripped = $this->normalizeWhitespace($stripped);
        if ($stripped !== '' && $stripped !== $normalized) {
            $candidates[] = $stripped;
        }

        if (count($parts) >= 2) {
            $candidates[] = $parts[0] . ' ' . $parts[1];
            $candidates[] = $parts[1];
            $candidates[] = $parts[0];
        }
        if (count($parts) >= 3) {
            $candidates[] = $parts[0] . ' ' . $parts[1] . ' ' . $parts[2];
            $candidates[] = $parts[1] . ' ' . $parts[2];
        }

        $ascii = $this->ascii($flattened);
        if (preg_match('/^([a-z0-9]+(?:\s+[a-z0-9]+)?)\s+central(?:\s+train)?\s+station$/', $ascii, $m)) {
            $city = $this->restoreCase($flattened, $m[1]);
            if ($city !== '') {
                $candidates[] = $city . ' Hbf';
                $candidates[] = $city;
            }
        }

        return $candidates;
    }

    /**
     * @param array<int,string> $parts
     * @return array<int,string>
     */
    private function ferryCandidates(string $normalized, string $flattened, array $parts): array
    {
        $candidates = [];
        $stripped = preg_replace('/\b(ferry terminal|terminal|port|harbour|harbor)\b/ui', ' ', $normalized) ?? $normalized;
        $stripped = $this->normalizeWhitespace($stripped);
        if ($stripped !== '' && $stripped !== $normalized) {
            $candidates[] = $stripped;
        }
        if (count($parts) >= 2) {
            $candidates[] = $parts[0];
            $candidates[] = $parts[0] . ' Ferry Terminal';
        }

        return $candidates;
    }

    /**
     * @param array<int,string> $parts
     * @return array<int,string>
     */
    private function airCandidates(string $normalized, string $flattened, array $parts): array
    {
        $candidates = [];
        $stripped = preg_replace('/\b(international airport|airport|airfield|terminal)\b/ui', ' ', $normalized) ?? $normalized;
        $stripped = $this->normalizeWhitespace($stripped);
        if ($stripped !== '' && $stripped !== $normalized) {
            $candidates[] = $stripped;
        }
        if (count($parts) >= 1) {
            $candidates[] = $parts[0];
        }

        return $candidates;
    }

    /**
     * @return array<int,string>
     */
    private function aiCandidates(string $mode, string $query): array
    {
        if (!$this->aiEnabledForQuery($query)) {
            return [];
        }

        $provider = strtolower((string)$this->env('LLM_PROVIDER'));
        $apiKey = (string)$this->env('OPENAI_API_KEY');
        if ($provider === '' || $provider === 'disabled' || trim($apiKey) === '') {
            return [];
        }

        $baseUrlRaw = (string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        $model = (string)($this->env('TRANSPORT_STOP_NORMALIZER_MODEL') ?: ($this->env('OPENAI_MODEL') ?: 'gpt-4o-mini'));
        $timeout = max(2, (int)($this->env('TRANSPORT_STOP_NORMALIZER_TIMEOUT_SECONDS') ?: 4));
        $forceJson = strtolower((string)($this->env('LLM_FORCE_JSON') ?? '1')) !== '0';
        $isAzure = $provider === 'azure';
        $baseUrl = $this->normalizeBaseUrl($this->sanitizeBaseUrl($baseUrlRaw), $provider);
        $apiVersion = (string)($this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview');

        $prompt = $this->buildAiPrompt($mode, mb_substr($query, 0, 160, 'UTF-8'));

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

            $body = [
                'model' => $model,
                'temperature' => 0,
                'top_p' => 0,
                'messages' => [
                    ['role' => 'system', 'content' => 'You normalize passenger stop names for transport node lookup. Output strict JSON only. Never invent coordinates or IDs.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];
            if ($forceJson) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if (!$resp->isOk()) {
                return [];
            }

            $data = (array)$resp->getJson();
            $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                return [];
            }
            $parsed = json_decode($content, true);
            if (!is_array($parsed)) {
                return [];
            }

            $confidence = isset($parsed['confidence']) && is_numeric($parsed['confidence']) ? (float)$parsed['confidence'] : 0.0;
            if ($confidence < 0.55) {
                return [];
            }

            $queries = [];
            $canonical = trim((string)($parsed['canonical_query'] ?? ''));
            if ($canonical !== '') {
                $queries[] = $canonical;
            }
            $fallbacks = $parsed['fallback_queries'] ?? [];
            if (is_array($fallbacks)) {
                foreach ($fallbacks as $fallback) {
                    $value = trim((string)$fallback);
                    if ($value !== '') {
                        $queries[] = $value;
                    }
                }
            }

            return $queries;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function aiEnabledForQuery(string $query): bool
    {
        $normalized = $this->normalizeWhitespace($query);
        if (mb_strlen($normalized, 'UTF-8') < 12) {
            return false;
        }
        $wordCount = count(array_filter(preg_split('/\s+/u', $normalized) ?: []));
        if ($wordCount < 2) {
            return false;
        }

        return true;
    }

    private function buildAiPrompt(string $mode, string $query): string
    {
        return <<<EOT
Normalize this passenger {$mode} stop/location string for transport node lookup.

Return strict JSON with exactly these keys:
{
  "canonical_query": "string or empty",
  "fallback_queries": ["string", "..."],
  "confidence": 0.0
}

Rules:
- Only output better search queries, never coordinates, codes, or explanations.
- Prefer real stop/terminal/airport names or city-level fallbacks that are likely to exist in a transport node catalog.
- If the string mentions a central train station but the mode is bus, you may normalize to a nearby bus-terminal-friendly query or city query.
- If unsure, keep the original meaning and lower confidence.

Input: {$query}
EOT;
    }

    private function normalizeWhitespace(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\p{L}\p{N}\s,\-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function restoreCase(string $source, string $asciiNeedle): string
    {
        $parts = preg_split('/\s+/u', $this->normalizeWhitespace($source)) ?: [];
        if ($parts === []) {
            return '';
        }

        $needleWords = preg_split('/\s+/', strtolower(trim($asciiNeedle))) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $ascii = strtolower($this->ascii($part));
            if (in_array($ascii, $needleWords, true)) {
                $out[] = $part;
            }
        }

        return implode(' ', $out);
    }

    private function ascii(string $value): string
    {
        $text = $value;
        try {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        } catch (\Throwable $e) {
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function env(string $key): ?string
    {
        $value = function_exists('env') ? env($key) : getenv($key);
        if ($value === false || $value === null) {
            return null;
        }

        return (string)$value;
    }

    private function normalizeBaseUrl(string $baseUrl, string $provider): string
    {
        $b = rtrim($baseUrl, '/');
        if ($provider === 'groq' || str_contains(strtolower($b), 'api.groq.com')) {
            if (!str_ends_with($b, '/openai/v1')) {
                if (str_ends_with($b, '/v1')) {
                    $b = substr($b, 0, -3) . 'openai/v1';
                } elseif (!str_contains($b, '/openai/')) {
                    $b .= '/openai/v1';
                }
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
        if (!preg_match('#^https?://#i', $b)) {
            $b = 'https://' . ltrim($b, '/');
        }

        return rtrim($b, '/');
    }
}
