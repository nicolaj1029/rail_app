<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Core\Configure;
final class RailTransportServiceClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->timeout = max(1, (int)Configure::read('Rail.transportServiceTimeout', 5));
        $this->baseUrl = rtrim((string)Configure::read('Rail.transportServiceBaseUrl', ''), '/');
    }

    public function isConfigured(): bool
    {
        return (bool)Configure::read('Rail.transportServiceEnabled', false) && $this->baseUrl !== '';
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function searchJourneys(array $criteria): array
    {
        $payload = $this->getJson('/journeys', [
            'from_station' => (string)($criteria['from_station'] ?? ''),
            'to_station' => (string)($criteria['to_station'] ?? ''),
            'date' => (string)($criteria['date'] ?? ''),
            'time' => (string)($criteria['time'] ?? ''),
            'operator_hint' => (string)($criteria['operator_hint'] ?? ''),
            'train_number_hint' => (string)($criteria['train_number_hint'] ?? ''),
            'locale' => (string)($criteria['locale'] ?? 'da-DK'),
        ]);

        return is_array($payload['items'] ?? null) ? array_values(array_filter($payload['items'], 'is_array')) : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function searchStations(string $query, int $limit = 8, string $locale = 'da-DK'): array
    {
        $payload = $this->getJson('/stations/search', [
            'q' => $query,
            'limit' => $limit,
            'locale' => $locale,
        ]);

        return is_array($payload['items'] ?? null) ? array_values(array_filter($payload['items'], 'is_array')) : [];
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function getDepartures(array $criteria): array
    {
        $payload = $this->getJson('/departures', [
            'station' => (string)($criteria['station'] ?? ''),
            'date' => (string)($criteria['date'] ?? ''),
            'time' => (string)($criteria['time'] ?? ''),
            'limit' => (string)($criteria['limit'] ?? '6'),
            'locale' => (string)($criteria['locale'] ?? 'da-DK'),
        ]);

        return is_array($payload['items'] ?? null) ? array_values(array_filter($payload['items'], 'is_array')) : [];
    }

    /**
     * @return array<string,mixed>
     */
    public function health(): array
    {
        return $this->getJson('/health');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function getJson(string $path, array $query = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return [];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'User-Agent: rail_app/1.0',
                ],
                CURLOPT_NOPROXY => '127.0.0.1,localhost',
            ]);

            $body = curl_exec($ch);
            $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $statusCode < 200 || $statusCode >= 300) {
                return [];
            }

            $payload = json_decode($body, true);
            return is_array($payload) ? $payload : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
