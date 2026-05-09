<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

final class MarineTrafficFerryDepartureProvider implements FerryDepartureListProviderInterface
{
    private string $searchUrl;
    private string $apiKey;
    private Client $http;

    public function __construct(?string $searchUrl = null, ?string $apiKey = null, ?Client $http = null)
    {
        $this->searchUrl = trim((string)($searchUrl ?? $this->env('MARINETRAFFIC_FERRY_SEARCH_URL') ?? ''));
        $this->apiKey = trim((string)($apiKey ?? $this->env('MARINETRAFFIC_API_KEY') ?? ''));
        $this->http = $http ?? new Client(['timeout' => 10]);
    }

    public function isConfigured(): bool
    {
        return $this->searchUrl !== '';
    }

    public function searchByRouteAndDate(string $departureCode, string $arrivalCode, string $date, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $query = [
            'departure' => strtoupper(trim($departureCode)),
            'arrival' => strtoupper(trim($arrivalCode)),
            'date' => trim($date),
            'operator' => trim((string)($context['operator'] ?? '')),
            'departureLabel' => trim((string)($context['departureLabel'] ?? '')),
            'arrivalLabel' => trim((string)($context['arrivalLabel'] ?? '')),
            'depTime' => trim((string)($context['depTime'] ?? '')),
        ];

        try {
            $response = $this->http->get($this->searchUrl, $query, [
                'headers' => $this->buildHeaders(),
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (!$response->isOk()) {
            return [];
        }

        try {
            $payload = $response->getJson();
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $items = is_array($payload['items'] ?? null) ? $payload['items'] : $payload;
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mapped = $this->mapItem($item, $query);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,string> $query
     * @return array<string,mixed>|null
     */
    private function mapItem(array $item, array $query): ?array
    {
        $departureCode = strtoupper(trim((string)($item['departure_port_code'] ?? ($item['from_code'] ?? $query['departure'] ?? ''))));
        $arrivalCode = strtoupper(trim((string)($item['arrival_port_code'] ?? ($item['to_code'] ?? $query['arrival'] ?? ''))));
        $departureName = trim((string)($item['departure_port_name'] ?? ($item['from_name'] ?? ($query['departureLabel'] ?? ''))));
        $arrivalName = trim((string)($item['arrival_port_name'] ?? ($item['to_name'] ?? ($query['arrivalLabel'] ?? ''))));

        if ($departureCode === '' && $departureName === '') {
            return null;
        }
        if ($arrivalCode === '' && $arrivalName === '') {
            return null;
        }

        return [
            'departure_key' => trim((string)($item['departure_key'] ?? '')) !== ''
                ? (string)$item['departure_key']
                : md5(implode('|', [
                    $departureCode !== '' ? $departureCode : $departureName,
                    $arrivalCode !== '' ? $arrivalCode : $arrivalName,
                    (string)($item['scheduled_departure_local'] ?? ''),
                    (string)($item['vessel_name'] ?? ''),
                    'marinetraffic',
                ])),
            'operator_name' => trim((string)($item['operator_name'] ?? ($item['carrier_name'] ?? ''))),
            'vessel_name' => trim((string)($item['vessel_name'] ?? ($item['ship_name'] ?? ''))),
            'vessel_imo' => trim((string)($item['vessel_imo'] ?? ($item['imo'] ?? ''))),
            'vessel_mmsi' => trim((string)($item['vessel_mmsi'] ?? ($item['mmsi'] ?? ''))),
            'departure_port_code' => $departureCode,
            'arrival_port_code' => $arrivalCode,
            'departure_port_name' => $departureName,
            'arrival_port_name' => $arrivalName,
            'scheduled_departure_local' => $this->normalizeDateTime((string)($item['scheduled_departure_local'] ?? '')),
            'scheduled_arrival_local' => $this->normalizeDateTime((string)($item['scheduled_arrival_local'] ?? '')),
            'estimated_departure_local' => $this->normalizeDateTime((string)($item['estimated_departure_local'] ?? '')),
            'estimated_arrival_local' => $this->normalizeDateTime((string)($item['estimated_arrival_local'] ?? ($item['eta_local'] ?? ''))),
            'actual_departure_local' => $this->normalizeDateTime((string)($item['actual_departure_local'] ?? '')),
            'actual_arrival_local' => $this->normalizeDateTime((string)($item['actual_arrival_local'] ?? '')),
            'live_position_reported_local' => $this->normalizeDateTime((string)($item['live_position_reported_local'] ?? ($item['position_reported_at'] ?? ''))),
            'live_position_lat' => $this->normalizeNullableFloat($item['live_position_lat'] ?? ($item['lat'] ?? null)),
            'live_position_lon' => $this->normalizeNullableFloat($item['live_position_lon'] ?? ($item['lon'] ?? null)),
            'live_speed_knots' => $this->normalizeNullableFloat($item['live_speed_knots'] ?? ($item['speed_knots'] ?? null)),
            'live_destination' => trim((string)($item['live_destination'] ?? ($item['destination'] ?? ''))),
            'status' => trim((string)($item['status'] ?? '')),
            'source' => 'marinetraffic',
        ];
    }

    /**
     * @return array<string,string>
     */
    private function buildHeaders(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            $headers['X-API-Key'] = $this->apiKey;
        }

        return $headers;
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d\TH:i:s', $timestamp);
    }

    private function normalizeNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private function env(string $key): ?string
    {
        if (function_exists('env')) {
            $value = env($key);
            return $value !== null ? (string)$value : null;
        }
        $value = getenv($key);

        return $value !== false ? (string)$value : null;
    }
}
