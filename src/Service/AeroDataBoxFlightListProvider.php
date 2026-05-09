<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

final class AeroDataBoxFlightListProvider implements FlightListProviderInterface
{
    private string $apiKey;
    private string $apiHost;
    private string $baseUrl;
    private Client $http;

    public function __construct(?string $apiKey = null, ?string $apiHost = null, ?string $baseUrl = null, ?Client $http = null)
    {
        $this->apiKey = trim((string)($apiKey ?? $this->env('AERODATABOX_API_KEY') ?? ''));
        $this->apiHost = trim((string)($apiHost ?? $this->env('AERODATABOX_API_HOST') ?? 'aerodatabox.p.rapidapi.com'));
        $this->baseUrl = rtrim((string)($baseUrl ?? $this->env('AERODATABOX_BASE_URL') ?? 'https://aerodatabox.p.rapidapi.com'), '/');
        $this->http = $http ?? new Client(['timeout' => 10]);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiHost !== '';
    }

    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        if ($fromIata === '' || $toIata === '' || $date === '') {
            return [];
        }

        $items = [];
        foreach ($this->buildDailyWindows($date) as $window) {
            $payload = $this->fetchDepartures($fromIata, $window['from'], $window['to']);
            if ($payload === null) {
                continue;
            }

            $departures = is_array($payload['departures'] ?? null) ? $payload['departures'] : [];
            foreach ($departures as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $item = $this->mapDeparture($row, $fromIata, $toIata);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @return array<int,array{from:string,to:string}>
     */
    private function buildDailyWindows(string $date): array
    {
        return [
            ['from' => $date . 'T00:00', 'to' => $date . 'T11:59'],
            ['from' => $date . 'T12:00', 'to' => $date . 'T23:59'],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchDepartures(string $fromIata, string $fromLocal, string $toLocal): ?array
    {
        $url = $this->baseUrl . '/flights/airports/iata/' . rawurlencode($fromIata) . '/' . rawurlencode($fromLocal) . '/' . rawurlencode($toLocal);

        try {
            $response = $this->http->get($url, [
                'direction' => 'Departure',
                'withLeg' => 'true',
                'withCancelled' => 'false',
                'withCodeshared' => 'true',
                'withCargo' => 'false',
                'withPrivate' => 'false',
            ], [
                'headers' => [
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => $this->apiHost,
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$response->isOk()) {
            return null;
        }

        try {
            $payload = $response->getJson();
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function mapDeparture(array $row, string $fromIata, string $toIata): ?array
    {
        $departure = is_array($row['departure'] ?? null) ? $row['departure'] : [];
        $arrival = is_array($row['arrival'] ?? null) ? $row['arrival'] : [];
        $movement = is_array($row['movement'] ?? null) ? $row['movement'] : [];
        $airline = is_array($row['airline'] ?? null) ? $row['airline'] : [];

        $depAirport = $this->extractAirportIata($departure, $movement, $fromIata);
        $arrAirport = $this->extractAirportIata($arrival, $movement, $toIata);
        if ($depAirport !== $fromIata || $arrAirport !== $toIata) {
            return null;
        }

        $flightNumber = strtoupper(trim((string)($row['number'] ?? '')));
        $marketingCarrier = trim((string)($airline['name'] ?? ($airline['shortName'] ?? '')));
        $operatingCarrier = $marketingCarrier;

        $scheduledDeparture = $this->extractLocalDateTime($departure);
        $scheduledArrival = $this->extractLocalDateTime($arrival);
        if ($scheduledDeparture === null) {
            return null;
        }
        $estimatedDeparture = $this->extractOperationalDateTime($departure, ['revisedTime', 'predictedTime', 'estimatedTime']);
        $estimatedArrival = $this->extractOperationalDateTime($arrival, ['revisedTime', 'predictedTime', 'estimatedTime']);
        $actualDeparture = $this->extractOperationalDateTime($departure, ['actualTime', 'runwayTime']);
        $actualArrival = $this->extractOperationalDateTime($arrival, ['actualTime', 'runwayTime']);
        $status = isset($row['status']) ? (string)$row['status'] : null;
        $cancelled = $this->deriveCancelled($row, $status);

        $codeshares = [];
        $codeshareStatus = trim((string)($row['codeshareStatus'] ?? ''));
        if ($codeshareStatus !== '' && strcasecmp($codeshareStatus, 'IsCodeshared') === 0 && $flightNumber !== '') {
            $codeshares[] = $flightNumber;
        }

        return [
            'flight_key' => md5(implode('|', [$depAirport, $arrAirport, $scheduledDeparture, $scheduledArrival, $flightNumber, 'aerodatabox'])),
            'flight_number' => $flightNumber,
            'carrier_name' => $marketingCarrier !== '' ? $marketingCarrier : $operatingCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'departure_airport_iata' => $depAirport,
            'arrival_airport_iata' => $arrAirport,
            'scheduled_departure_local' => $scheduledDeparture,
            'scheduled_arrival_local' => $scheduledArrival,
            'estimated_departure_local' => $estimatedDeparture,
            'estimated_arrival_local' => $estimatedArrival,
            'actual_departure_local' => $actualDeparture,
            'actual_arrival_local' => $actualArrival,
            'status' => $status,
            'cancelled' => $cancelled,
            'source' => 'aerodatabox',
            'codeshare_numbers' => $codeshares,
        ];
    }

    /**
     * @param array<string,mixed> $movement
     * @param array<string,mixed> $fallbackMovement
     */
    private function extractAirportIata(array $movement, array $fallbackMovement, string $default = ''): string
    {
        $candidates = [
            $movement['airport']['iata'] ?? null,
            $movement['airport']['code'] ?? null,
            $movement['airport']['shortName'] ?? null,
            $fallbackMovement['airport']['iata'] ?? null,
            $fallbackMovement['airport']['code'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string)$candidate));
            if (preg_match('/^[A-Z]{3}$/', $value)) {
                return $value;
            }
        }

        return strtoupper(trim($default));
    }

    /**
     * @param array<string,mixed> $movement
     */
    private function extractLocalDateTime(array $movement): ?string
    {
        $candidates = [
            $movement['scheduledTime']['local'] ?? null,
            $movement['revisedTime']['local'] ?? null,
            $movement['actualTime']['local'] ?? null,
            $movement['time']['local'] ?? null,
            $movement['scheduledTimeLocal'] ?? null,
            $movement['actualTimeLocal'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d\TH:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $movement
     * @param array<int,string> $keys
     */
    private function extractOperationalDateTime(array $movement, array $keys): ?string
    {
        $candidates = [];
        foreach ($keys as $key) {
            $candidates[] = $movement[$key]['local'] ?? null;
            $candidates[] = $movement[$key . 'Local'] ?? null;
        }
        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value === '') {
                continue;
            }
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return date('Y-m-d\TH:i:s', $timestamp);
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function deriveCancelled(array $row, ?string $status): bool
    {
        if (isset($row['isCancelled'])) {
            return (bool)$row['isCancelled'];
        }
        $status = strtolower(trim((string)$status));
        return $status !== '' && (str_contains($status, 'cancel') || str_contains($status, 'annul'));
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
