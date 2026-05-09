<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

final class AviationstackFlightListProvider implements FlightListProviderInterface
{
    private string $apiKey;
    private Client $http;
    private string $endpoint;

    public function __construct(?string $apiKey = null, ?Client $http = null, ?string $endpoint = null)
    {
        $this->apiKey = trim((string)($apiKey ?? $this->env('AVIATIONSTACK_API_KEY') ?? ''));
        $this->http = $http ?? new Client(['timeout' => 8]);
        $this->endpoint = rtrim((string)($endpoint ?? $this->env('AVIATIONSTACK_BASE_URL') ?? 'https://api.aviationstack.com/v1/flights'), '/');
    }

    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->http->get($this->endpoint, [
                'access_key' => $this->apiKey,
                'dep_iata' => strtoupper(trim($fromIata)),
                'arr_iata' => strtoupper(trim($toIata)),
                'flight_date' => trim($date),
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

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->mapRow($row, strtoupper(trim($fromIata)), strtoupper(trim($toIata)));
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return $out;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function mapRow(array $row, string $fromIata, string $toIata): ?array
    {
        $departure = is_array($row['departure'] ?? null) ? $row['departure'] : [];
        $arrival = is_array($row['arrival'] ?? null) ? $row['arrival'] : [];
        $airline = is_array($row['airline'] ?? null) ? $row['airline'] : [];
        $flight = is_array($row['flight'] ?? null) ? $row['flight'] : [];
        $codeshared = is_array($flight['codeshared'] ?? null) ? $flight['codeshared'] : [];

        $depIata = strtoupper(trim((string)($departure['iata'] ?? '')));
        $arrIata = strtoupper(trim((string)($arrival['iata'] ?? '')));
        if ($depIata === '' || $arrIata === '' || $depIata !== $fromIata || $arrIata !== $toIata) {
            return null;
        }

        $marketingCarrier = trim((string)($airline['name'] ?? ''));
        $operatingCarrier = trim((string)($codeshared['airline_name'] ?? ''));
        if ($operatingCarrier === '') {
            $operatingCarrier = $marketingCarrier;
        }

        $flightNumber = strtoupper(trim((string)($flight['iata'] ?? '')));
        if ($flightNumber === '') {
            $prefix = strtoupper(trim((string)($airline['iata'] ?? '')));
            $number = trim((string)($flight['number'] ?? ''));
            $flightNumber = $prefix !== '' && $number !== '' ? $prefix . $number : $number;
        }

        $depScheduled = $this->normalizeDateTime((string)($departure['scheduled'] ?? ''));
        $arrScheduled = $this->normalizeDateTime((string)($arrival['scheduled'] ?? ''));

        return [
            'flight_key' => md5(implode('|', [$depIata, $arrIata, $depScheduled, $arrScheduled, $flightNumber, 'aviationstack'])),
            'flight_number' => $flightNumber,
            'carrier_name' => $marketingCarrier !== '' ? $marketingCarrier : $operatingCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'departure_airport_iata' => $depIata,
            'arrival_airport_iata' => $arrIata,
            'scheduled_departure_local' => $depScheduled,
            'scheduled_arrival_local' => $arrScheduled,
            'status' => isset($row['flight_status']) ? (string)$row['flight_status'] : null,
            'source' => 'aviationstack',
            'codeshare_numbers' => $this->extractCodeshares($codeshared),
        ];
    }

    /**
     * @param array<string,mixed> $codeshared
     * @return array<int,string>
     */
    private function extractCodeshares(array $codeshared): array
    {
        if ($codeshared === []) {
            return [];
        }

        $values = [];
        $flightNumber = strtoupper(trim((string)($codeshared['flight_iata'] ?? '')));
        if ($flightNumber !== '') {
            $values[] = $flightNumber;
        } elseif (!empty($codeshared['flight_number'])) {
            $values[] = strtoupper(trim((string)$codeshared['flight_number']));
        }

        return array_values(array_filter(array_unique($values), static fn(string $value): bool => $value !== ''));
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
