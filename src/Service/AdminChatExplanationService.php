<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Session;
use GuzzleHttp\Client;

final class AdminChatExplanationService
{
    private const CACHE_LIMIT = 8;

    /**
     * @param array<string,mixed>|null $question
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $preview
     * @param array<int,array<string,mixed>> $citations
     * @return array<string,mixed>
     */
    public function build(Session $session, ?array $question, array $summary, array $preview, array $citations): array
    {
        if ($question === null && $preview === []) {
            return [
                'enabled' => false,
                'status' => 'idle',
                'provider' => 'groq',
                'model' => null,
                'message' => 'Ingen aktiv forklaring endnu.',
                'text' => null,
            ];
        }

        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'provider' => 'groq',
                'model' => null,
                'message' => 'Groq-forklaring er slået fra under PHPUnit.',
                'text' => null,
            ];
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            return [
                'enabled' => false,
                'status' => 'disabled',
                'provider' => 'groq',
                'model' => null,
                'message' => 'Groq-forklaring er deaktiveret. Sæt `GROQ_API_KEY` for at aktivere read-only forklaringer.',
                'text' => null,
            ];
        }

        $payload = $this->buildPromptPayload($question, $summary, $preview, $citations);
        $cacheKey = sha1((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cache = (array)$session->read('admin.chat_explanation_cache') ?: [];
        if (isset($cache[$cacheKey]) && is_array($cache[$cacheKey])) {
            $cached = (array)$cache[$cacheKey];
            $cached['enabled'] = true;
            $cached['status'] = 'cached';
            $cached['message'] = 'Groq-forklaring hentet fra cache.';

            return $cached;
        }

        $model = $this->resolveModel();
        $explanation = $this->requestExplanation($apiKey, $model, $payload);
        $cache[$cacheKey] = $explanation;
        $cache = $this->trimCache($cache);
        $session->write('admin.chat_explanation_cache', $cache);

        return $explanation;
    }

    /**
     * @param array<string,mixed>|null $question
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $preview
     * @param array<int,array<string,mixed>> $citations
     * @return array<string,mixed>
     */
    private function buildPromptPayload(?array $question, array $summary, array $preview, array $citations): array
    {
        $previewSummary = (array)($preview['summary'] ?? []);
        $actions = [];
        foreach (array_slice((array)($preview['actions'] ?? []), 0, 3) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $actions[] = [
                'label' => (string)($item['label'] ?? ''),
                'detail' => (string)($item['detail'] ?? ''),
            ];
        }
        $blockers = [];
        foreach (array_slice((array)($preview['blocking_fields'] ?? []), 0, 3) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $blockers[] = [
                'key' => (string)($item['key'] ?? ''),
                'label' => (string)($item['label'] ?? ''),
                'detail' => (string)($item['detail'] ?? ''),
                'priority' => (string)($item['priority'] ?? ''),
            ];
        }
        $citationRows = [];
        foreach (array_slice($citations, 0, 2) as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $citationRows[] = [
                'article' => (string)($citation['article'] ?? ''),
                'page' => (string)($citation['page_from'] ?? ''),
                'text' => $this->truncate((string)($citation['text'] ?? ''), 180),
            ];
        }

        return [
            'question' => [
                'key' => (string)($question['key'] ?? ''),
                'prompt' => (string)($question['prompt'] ?? ''),
            ],
            'summary' => [
                'travel_state' => (string)($summary['travel_state'] ?? ''),
                'ticket_mode' => (string)($summary['ticket_mode'] ?? ''),
                'season_mode' => (bool)($summary['season_mode'] ?? false),
                'operator' => (string)($summary['operator'] ?? ''),
                'operator_country' => (string)($summary['operator_country'] ?? ''),
                'incident_main' => (string)($summary['incident_main'] ?? ''),
                'delay_minutes' => (string)($summary['delay_minutes'] ?? ''),
                'gate_art18' => (bool)($summary['gate_art18'] ?? false),
                'gate_art20' => (bool)($summary['gate_art20'] ?? false),
                'gate_art20_2c' => (bool)($summary['gate_art20_2c'] ?? false),
            ],
            'preview' => [
                'status' => (string)($preview['status'] ?? ''),
                'message' => (string)($preview['message'] ?? ''),
                'scope' => (string)($previewSummary['scope'] ?? ''),
                'liable_party' => (string)($previewSummary['liable_party'] ?? ''),
                'profile_blocked' => (bool)($previewSummary['profile_blocked'] ?? false),
                'art12_applies' => $previewSummary['art12_applies'] ?? null,
                'refund_eligible' => $previewSummary['refund_eligible'] ?? null,
                'art20_compliance' => $previewSummary['art20_compliance'] ?? null,
                'claim_basis' => (string)($previewSummary['claim_basis'] ?? ''),
                'partial' => (bool)($previewSummary['partial'] ?? false),
            ],
            'actions' => $actions,
            'blockers' => $blockers,
            'citations' => $citationRows,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function requestExplanation(string $apiKey, string $model, array $payload): array
    {
        $endpoint = rtrim($this->resolveBaseUrl(), '/') . '/openai/v1/chat/completions';
        $client = new Client([
            'timeout' => 12,
            'connect_timeout' => 5,
            'http_errors' => true,
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Du er et read-only forklaringslag for en admin-chat i en jernbanekrav-app. Du maa ikke opfinde rettigheder, satser eller gating. Brug kun det givne preview og de givne citations. Svar paa dansk i maks 4 korte linjer. Forklar: 1) hvad systemet fokuserer paa nu, 2) hvorfor spoergsmaalet betyder noget, 3) hvad admin boer afklare herefter. Ingen markdown-tabeller. Ingen feltaendringer.',
            ],
            [
                'role' => 'user',
                'content' => (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 220,
                    'messages' => $messages,
                ],
            ]);

            $data = (array)json_decode((string)$response->getBody(), true);
            $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
            if ($text === '') {
                return [
                    'enabled' => true,
                    'status' => 'error',
                    'provider' => 'groq',
                    'model' => $model,
                    'message' => 'Groq returnerede ingen forklaring.',
                    'text' => null,
                ];
            }

            return [
                'enabled' => true,
                'status' => 'ok',
                'provider' => 'groq',
                'model' => $model,
                'message' => 'Groq-forklaring er opdateret.',
                'text' => $this->truncate($text, 900),
            ];
        } catch (\Throwable $e) {
            return [
                'enabled' => true,
                'status' => 'error',
                'provider' => 'groq',
                'model' => $model,
                'message' => 'Groq-kald fejlede: ' . $e->getMessage(),
                'text' => null,
            ];
        }
    }

    private function resolveApiKey(): string
    {
        return $this->envAny(['GROQ_API_KEY', 'GROQ_KEY', 'GROQ_TOKEN', 'GROQ_API_TOKEN']);
    }

    private function resolveModel(): string
    {
        $model = $this->envAny(['GROQ_MODEL']);
        if ($model !== '') {
            return $model;
        }

        $model = $this->envAny(['OPENAI_MODEL']);
        if ($model !== '') {
            return $model;
        }

        return 'llama-3.1-8b-instant';
    }

    private function resolveBaseUrl(): string
    {
        $base = $this->envAny(['GROQ_BASE_URL']);
        if ($base !== '') {
            return $base;
        }

        $proxyBase = $this->envAny(['OPENAI_BASE_URL']);
        if ($proxyBase !== '' && stripos($proxyBase, 'groq.com') !== false) {
            return $proxyBase;
        }

        return 'https://api.groq.com';
    }

    /**
     * @param array<string,mixed> $cache
     * @return array<string,mixed>
     */
    private function trimCache(array $cache): array
    {
        if (count($cache) <= self::CACHE_LIMIT) {
            return $cache;
        }

        return array_slice($cache, -self::CACHE_LIMIT, null, true);
    }

    private function envAny(array $names): string
    {
        foreach ($names as $name) {
            foreach ([$name, strtolower($name)] as $key) {
                $value = getenv($key);
                if ($value === false || $value === '') {
                    $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
                }
                if ($value !== '' && $value !== false) {
                    return trim((string)$value);
                }
            }
        }

        return '';
    }

    private function truncate(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }
}
