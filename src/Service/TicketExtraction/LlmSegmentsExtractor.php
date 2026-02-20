<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

use Cake\Http\Client;

/**
 * Optional LLM-backed segment structuring fallback.
 * Guarded by env USE_LLM_STRUCTURING and LLM provider creds; returns empty when disabled.
 */
final class LlmSegmentsExtractor
{
    /**
     * Try to extract journey segments from OCR text using an LLM.
     * Returns ['segments'=>array<int,array{from:string,to:string,schedDep?:string,schedArr?:string,trainNo?:string,depDate?:string,arrDate?:string}>, 'logs'=>string[]]
     * Never throws; on failure returns ['segments'=>[], 'logs'=>['reason...']].
     *
     * Note: Requires the same OPENAI_* envs as LlmExtractor, or Azure-compatible creds.
     */
    public function extractSegments(string $text, bool $forceRun = false): array
    {
        $logs = [];
        $enabled = strtolower((string)($this->env('USE_LLM_STRUCTURING') ?? ''));
        if (!$forceRun && !in_array($enabled, ['1','true','yes','on'], true)) {
            return ['segments' => [], 'logs' => ['LLM structuring disabled']];
        }

        $provider = $this->env('LLM_PROVIDER') ?: 'disabled';
        $apiKey   = $this->env('OPENAI_API_KEY');
        $baseUrlRaw = (string)($this->env('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1');
        $model    = $this->env('OPENAI_MODEL') ?: 'gpt-4o-mini';
        $timeout  = (int)($this->env('LLM_TIMEOUT_SECONDS') ?: 15);
        $forceJson = strtolower((string)($this->env('LLM_FORCE_JSON') ?? '1')) !== '0';

        if ($provider === 'disabled' || !$apiKey) {
            return ['segments' => [], 'logs' => ['LLM segments extractor disabled (no API key/provider)']];
        }

        $isAzure = strtolower((string)$provider) === 'azure';
        $baseUrl = $this->sanitizeBaseUrl($baseUrlRaw);
        $baseUrl = $this->normalizeBaseUrl($baseUrl, strtolower((string)$provider));
        $logs[] = 'LLM seg base=' . $baseUrl . ', model=' . $model;
        $apiVersion = $this->env('OPENAI_API_VERSION') ?: '2024-08-01-preview';

        // Limit input size
    $text = mb_substr($text, 0, 8000);

        $prompt = $this->buildPrompt($text);

        try {
            $client = new Client(['timeout' => $timeout]);

            if ($isAzure) {
                $url = rtrim($baseUrl, '/') . "/openai/deployments/{$model}/chat/completions?api-version={$apiVersion}";
                $headers = [ 'Content-Type' => 'application/json', 'api-key' => $apiKey ];
            } else {
                $url = rtrim($baseUrl, '/') . '/chat/completions';
                $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $apiKey ];
            }

            $messages = [
                ['role' => 'system', 'content' => 'You extract journey segments from EU train tickets. Output strict JSON only.'],
                ['role' => 'user', 'content' => $prompt],
            ];

            $body = [
                'model' => $model,
                'temperature' => 0,
                'top_p' => 0,
                'messages' => $messages,
            ];
            $body = $this->applyReasoningParams($body, $model, 500);
            if ($forceJson && !$this->isReasoningModel($model)) { $body['response_format'] = ['type' => 'json_object']; }

            $resp = $client->post($url, json_encode($body), ['headers' => $headers]);
            if ($resp->isOk()) {
                $data = (array)$resp->getJson();
                $content = (string)($data['choices'][0]['message']['content'] ?? '');
                $parsed = $this->tryParseJson($content);
                if (is_array($parsed)) {
                    $segments = $this->normalizeSegments($parsed['segments'] ?? []);
                    $logs[] = 'LLM segments ok: ' . count($segments);
                    return ['segments' => $segments, 'logs' => $logs];
                }
                $logs[] = 'LLM segments non-JSON';
            } else {
                $logs[] = 'LLM segments HTTP ' . $resp->getStatusCode();
            }
        } catch (\Throwable $e) {
            $logs[] = 'LLM segments exception: ' . $e->getMessage();
        }

        return ['segments' => [], 'logs' => $logs];
    }

    private function buildPrompt(string $text): string
    {
        $schema = [
            'segments' => [
                [
                    'from' => 'string',
                    'to' => 'string',
                    'schedDep' => 'HH:MM or ""',
                    'schedArr' => 'HH:MM or ""',
                    'trainNo' => 'string or ""',
                    'depDate' => 'YYYY-MM-DD or ""',
                    'arrDate' => 'YYYY-MM-DD or ""',
                    'classPurchased' => '1st|2nd|couchette|sleeper|""',
                    'reservationPurchased' => 'reserved|free_seat|missing|""',
                ],
            ],
        ];
        $schemaText = json_encode($schema, JSON_UNESCAPED_SLASHES);
        return <<<EOT
Extract the train journey segments from the OCR text.
Return STRICT JSON with key "segments" as an array of objects with keys:
{$schemaText}

Rules:
- A segment is from one station to the next change/destination.
- Use 24h HH:MM for times when present, else empty string.
- Use ISO YYYY-MM-DD for dates when present, else empty string.
- Only fill classPurchased/reservationPurchased if clearly stated on the ticket for that leg; otherwise return empty string.
- Do not add extra keys.

OCR TEXT:
--------
{$text}
--------
EOT;
    }

    private function tryParseJson(string $content): ?array
    {
        $content = trim($content);
        $content = $this->normalizeJsonWhitespace($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?/i', '', $content);
            $content = preg_replace('/```$/', '', (string)$content);
            $content = trim((string)$content);
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param mixed $arr */
    private function normalizeSegments($arr): array
    {
        if (!is_array($arr)) { return []; }
        $out = [];
        $mapClass = function($v): string {
            $v = strtolower(trim((string)$v));
            if ($v === '') { return ''; }
            if (in_array($v, ['1st','1st_class','first','1','business','comfort','premiere','premium'], true)) { return '1st'; }
            if (in_array($v, ['2nd','2nd_class','second','2','standard','economy'], true)) { return '2nd'; }
            if (in_array($v, ['couchette','berth','liegewagen','liggevogn'], true)) { return 'couchette'; }
            if (in_array($v, ['sleeper','sleeping','sovevogn'], true)) { return 'sleeper'; }
            return '';
        };
        $mapRes = function($v): string {
            $v = strtolower(trim((string)$v));
            if ($v === '') { return ''; }
            if (in_array($v, ['reserved','seat','seat_reserved','reservation','reserveret','platzreservierung'], true)) { return 'reserved'; }
            if (in_array($v, ['free','free_seat','unreserved','open_seat','no_reservation','ingen reservation'], true)) { return 'free_seat'; }
            if (in_array($v, ['missing','none','not_included'], true)) { return 'missing'; }
            return '';
        };
        foreach ($arr as $s) {
            if (!is_array($s)) { continue; }
            $from = trim((string)($s['from'] ?? ''));
            $to = trim((string)($s['to'] ?? ''));
            if ($from === '' || $to === '' || $from === $to) { continue; }
            if (!$this->isValidStationName($from) || !$this->isValidStationName($to)) { continue; }
            $classPurchased = $mapClass($s['classPurchased'] ?? $s['class_purchased'] ?? '');
            $resPurchased = $mapRes($s['reservationPurchased'] ?? $s['reservation_purchased'] ?? '');
            $out[] = [
                'from' => $from,
                'to' => $to,
                'schedDep' => trim((string)($s['schedDep'] ?? '')),
                'schedArr' => trim((string)($s['schedArr'] ?? '')),
                'trainNo' => trim((string)($s['trainNo'] ?? '')),
                'depDate' => trim((string)($s['depDate'] ?? '')),
                'arrDate' => trim((string)($s['arrDate'] ?? '')),
                'classPurchased' => $classPurchased,
                'reservationPurchased' => $resPurchased,
            ];
        }
        // Prefer anchor legs (with a time or a train number) to avoid listing every via stop
        $anchors = [];
        foreach ($out as $seg) {
            $hasTime = ((string)$seg['schedDep'] !== '') || ((string)$seg['schedArr'] !== '');
            $hasTrain = ((string)$seg['trainNo'] !== '');
            if ($hasTime || $hasTrain) { $anchors[] = $seg; }
        }
        if (count($anchors) >= 1) {
            // Also collapse adjacent anchors with identical trainNo
            $collapsed = [];
            foreach ($anchors as $seg) {
                $last = end($collapsed);
                if ($last && (string)$last['trainNo'] !== '' && (string)$last['trainNo'] === (string)$seg['trainNo']) {
                    // Extend previous leg to this segment's destination/times
                    $collapsed[key($collapsed)] = [
                        'from' => $last['from'],
                        'to' => $seg['to'],
                        'schedDep' => $last['schedDep'] ?: $seg['schedDep'],
                        'schedArr' => $seg['schedArr'] ?: $last['schedArr'],
                        'trainNo' => $last['trainNo'],
                        'depDate' => $last['depDate'] ?: $seg['depDate'],
                        'arrDate' => $seg['arrDate'] ?: $last['arrDate'],
                        'classPurchased' => $last['classPurchased'] ?: $seg['classPurchased'],
                        'reservationPurchased' => $last['reservationPurchased'] ?: $seg['reservationPurchased'],
                    ];
                } else {
                    $collapsed[] = $seg;
                }
            }
            return $collapsed;
        }
        return $out;
    }

    private function isValidStationName(string $name): bool
    {
        $n = trim(mb_strtolower($name, 'UTF-8'));
        if ($n === '') return false;
        // Filter common non-station phrases picked by OCR/LLM
        $badPhrases = [
            'se rejseplan', 'se rejsplan', 'rejseplan', 'rejsplan',
            'den dag og den afgang', 'den dag og afgang', 'den afgang',
            'se mere', 'se rejsen', 'se plan',
            // common German boilerplate words found on DB tickets
            'klasse', 'classe', 'class', 'hinweise', 'bedingungen', 'tarifgemeinschaft', 'verkehrsverbund', 'agb',
            // French reservation boilerplate occasionally misread as stations
            'réservation du billet', 'reservation du billet', 'réservation', 'reservation',
            // Swedish/SJ labels
            'byte', 'assisterad', 'rullstol', 'reserv', 'välsw', 'eu-gemensam', 'köp', 'køb',
            // Multilingual ticket/reservation/purchase/customer-service words (broad EU set)
            'billet', 'billete', 'bilhete', 'biglietto', 'bilet', 'jízdenka', 'lístok', 'vozovnica', 'bilete',
            'reservation', 'réservation', 'reserva', 'prenotazione', 'rezervace', 'rezerwacja', 'rezervácia', 'rezervare', 'foglalás',
            'compra', 'achat', 'acquisto', 'osto', 'zakup', 'nákup', 'cumpărare',
            'cliente', 'klant', 'klient', 'zákazník', 'ügyfél', 'kundeservice', 'service client',
            // Scheduling/label phrases that are not stations
            'scheduled arr', 'scheduled arrival', 'scheduled dep', 'scheduled departure',
            'date', 'dato', 'time', 'tid', 'ankomst', 'afgang', 'avgång', 'ank.', 'afg.', 'ankom',
            // product/category words (Italian and generic)
            'frecciarossa', 'frecciargento', 'freccia', 'intercity', 'eurocity', 'railjet', 'regio', 'regionale', 'italo',
        ];
        foreach ($badPhrases as $bp) {
            if (str_contains($n, $bp)) return false;
        }
        // Too many words unlikely for a station
        if (substr_count($n, ' ') >= 5) return false;
        // Must contain at least one letter (avoid numbers/symbols only)
        if (!preg_match('/\p{L}/u', $n)) return false;
        return true;
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

    private function sanitizeBaseUrl(string $baseUrl): string
    {
        $b = trim($baseUrl);
        $b = preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $b) ?? $b;
        $b = preg_replace('/[\s\x{2010}-\x{2015}\x{2026}]+$/u', '', $b) ?? $b;
        $b = str_replace('\\', '/', $b);
        if (!preg_match('#^https?://#i', $b)) { $b = 'https://' . ltrim($b, '/'); }
        return rtrim($b, '/');
    }

    private function env(string $key): ?string
    {
        if (function_exists('env')) { $v = env($key); return $v !== null ? (string)$v : null; }
        $v = getenv($key);
        return $v !== false ? (string)$v : null;
    }

    private function normalizeJsonWhitespace(string $s): string
    {
        return preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $s) ?? $s;
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
}

?>
