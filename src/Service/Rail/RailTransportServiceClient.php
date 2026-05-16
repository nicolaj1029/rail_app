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
        if ($this->baseUrl === '') {
            return false;
        }

        if ((bool)Configure::read('Rail.transportServiceEnabled', false)) {
            return true;
        }

        return (bool)Configure::read('debug', false) && $this->isLocalBaseUrl($this->baseUrl);
    }

    private function isLocalBaseUrl(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function searchJourneys(array $criteria): array
    {
        $payload = $this->getJson('/journeys', [
            'from_station' => (string)($criteria['from_station'] ?? ''),
            'from_station_id' => (string)($criteria['from_station_id'] ?? ''),
            'to_station' => (string)($criteria['to_station'] ?? ''),
            'to_station_id' => (string)($criteria['to_station_id'] ?? ''),
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

    public function shutdown(): bool
    {
        $payload = $this->postJson('/shutdown');

        return (bool)($payload['ok'] ?? false);
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
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
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

    /**
     * @return array<string,mixed>
     */
    private function postJson(string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $url = $this->baseUrl . $path;

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
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data === [] ? '' : http_build_query($data, '', '&', PHP_QUERY_RFC3986),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
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
