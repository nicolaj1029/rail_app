<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Throwable;
use UnexpectedValueException;

final class AeroDataBoxFlightListProvider implements FlightListProviderInterface, FlightListProviderDiagnosticsInterface
{
    private string $apiKey;
    private string $apiHost;
    private string $baseUrl;
    private Client $http;
    private int $timeoutSeconds;
    private int $minRequestIntervalMs;
    /**
     * @var callable|null
     */
    private $requestExecutor;
    /**
     * @var array<string,mixed>
     */
    private array $lastOutcome = ['status' => 'not_started', 'provider' => 'aerodatabox'];

    /** Construct a bounded AeroDataBox client. */
    public function __construct(
        ?string $apiKey = null,
        ?string $apiHost = null,
        ?string $baseUrl = null,
        ?Client $http = null,
        ?callable $requestExecutor = null,
        ?int $minRequestIntervalMs = null,
    ) {
        $config = (array)Configure::read('External.aeroDataBox');
        $this->apiKey = trim((string)($apiKey ?? $config['apiKey'] ?? $this->env('AERODATABOX_API_KEY') ?? ''));
        $this->apiHost = trim((string)(
            $apiHost
            ?? $config['apiHost']
            ?? $this->env('AERODATABOX_API_HOST')
            ?? 'aerodatabox.p.rapidapi.com'
        ));
        $this->baseUrl = rtrim((string)(
            $baseUrl
            ?? $config['baseUrl']
            ?? $this->env('AERODATABOX_BASE_URL')
            ?? 'https://aerodatabox.p.rapidapi.com'
        ), '/');
        $timeout = max(1, (int)($config['timeoutSeconds'] ?? 5));
        $connectTimeout = max(1, min($timeout, (int)($config['connectTimeoutSeconds'] ?? 2)));
        $this->http = $http ?? new Client(['timeout' => $timeout, 'connect_timeout' => $connectTimeout]);
        $this->timeoutSeconds = $timeout;
        $this->requestExecutor = $requestExecutor;
        $configuredInterval = (int)($config['minRequestIntervalMs'] ?? 1100);
        $this->minRequestIntervalMs = max(0, min(5000, $minRequestIntervalMs
            ?? ($requestExecutor !== null ? 0 : $configuredInterval)));
    }

    /** Return whether the required provider configuration is present. */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiHost !== '';
    }

    /** Search the two provider-supported half-day windows. */
    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $started = microtime(true);
        if (!$this->isConfigured()) {
            $this->lastOutcome = $this->outcome('configuration_missing', $started);

            return [];
        }

        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        if ($fromIata === '' || $toIata === '' || $date === '') {
            $this->lastOutcome = $this->outcome('invalid_request', $started);

            return [];
        }

        $items = [];
        $attempts = [];
        $lastRequestCompletedAt = null;
        $deadline = is_numeric($context['_deadline_monotonic'] ?? null)
            ? (float)$context['_deadline_monotonic']
            : null;
        $requestedFlight = strtoupper(preg_replace('/\s+/', '', (string)($context['flightNumber'] ?? '')) ?? '');
        $isHistoricalDate = $date < gmdate('Y-m-d');
        if ($isHistoricalDate && preg_match('/^[A-Z0-9]{2,10}$/', $requestedFlight)) {
            $result = $this->fetchFlightByNumber($requestedFlight, $date, $deadline);
            $attempts[] = [
                'status' => $result['status'],
                'http_status' => $result['http_status'],
                'latency_ms' => $result['latency_ms'],
            ];
            foreach ((array)($result['payload'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $item = $this->mapDeparture($row, $fromIata, $toIata);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            $items = $this->linkCodeshareRows($items);
            $status = $items !== [] ? 'success' : (string)$result['status'];
            $this->lastOutcome = $this->outcome($status, $started, [
                'item_count' => count($items),
                'attempts' => $attempts,
            ]);

            return $items;
        }
        foreach ($this->buildDailyWindows($date) as $window) {
            if ($lastRequestCompletedAt !== null && $this->minRequestIntervalMs > 0) {
                $elapsedMs = (microtime(true) - $lastRequestCompletedAt) * 1000;
                $waitMs = max(0, $this->minRequestIntervalMs - (int)floor($elapsedMs));
                if ($deadline !== null && microtime(true) + ($waitMs / 1000) >= $deadline) {
                    $attempts[] = [
                        'status' => 'lookup_budget_exhausted',
                        'http_status' => null,
                        'latency_ms' => 0.0,
                    ];
                    break;
                }
                if ($waitMs > 0) {
                    usleep($waitMs * 1000);
                }
            }
            if ($deadline !== null && microtime(true) >= $deadline) {
                $attempts[] = [
                    'status' => 'lookup_budget_exhausted',
                    'http_status' => null,
                    'latency_ms' => 0.0,
                ];
                break;
            }
            $result = $this->fetchDepartures($fromIata, $window['from'], $window['to'], $deadline);
            $lastRequestCompletedAt = microtime(true);
            $attempts[] = [
                'status' => $result['status'],
                'http_status' => $result['http_status'],
                'latency_ms' => $result['latency_ms'],
            ];
            if ($result['status'] !== 'success') {
                // A second half-day call is not a retry and must not amplify upstream failures.
                break;
            }

            $payload = (array)$result['payload'];
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

        $items = $this->linkCodeshareRows($items);

        $statuses = array_column($attempts, 'status');
        $status = $items !== [] ? 'success' : 'success_no_data';
        foreach ($statuses as $attemptStatus) {
            if ($attemptStatus !== 'success') {
                $status = $items !== [] ? 'success_degraded' : (string)$attemptStatus;
                break;
            }
        }
        $this->lastOutcome = $this->outcome($status, $started, [
            'item_count' => count($items),
            'attempts' => $attempts,
        ]);

        return $items;
    }

    /** Return the stable provider identifier. */
    public function getProviderName(): string
    {
        return 'aerodatabox';
    }

    /** Return redacted diagnostics for the most recent search. */
    public function getLastOutcome(): array
    {
        return $this->lastOutcome;
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
     * @return array{status:string,payload:?array,http_status:?int,latency_ms:float}
     */
    private function fetchDepartures(
        string $fromIata,
        string $fromLocal,
        string $toLocal,
        ?float $deadline = null,
    ): array {
        $url = $this->baseUrl
            . '/flights/airports/iata/' . rawurlencode($fromIata)
            . '/' . rawurlencode($fromLocal)
            . '/' . rawurlencode($toLocal);
        $started = microtime(true);
        try {
            $remainingTimeout = $deadline !== null
                ? max(1, min($this->timeoutSeconds, (int)ceil($deadline - microtime(true))))
                : $this->timeoutSeconds;
            $request = function () use ($url, $remainingTimeout): Response {
                return $this->http->get($url, [
                'direction' => 'Departure',
                'withLeg' => 'true',
                'withCancelled' => 'true',
                'withCodeshared' => 'true',
                'withCargo' => 'false',
                'withPrivate' => 'false',
                ], [
                    'headers' => [
                    'x-rapidapi-key' => $this->apiKey,
                    'x-rapidapi-host' => $this->apiHost,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => $remainingTimeout,
                ]);
            };
            $response = $this->requestExecutor !== null
                ? ($this->requestExecutor)($url, $request)
                : $request();
            if (!$response instanceof Response) {
                throw new UnexpectedValueException('AIR provider executor returned an invalid response');
            }
        } catch (Throwable $e) {
            $status = preg_match('/timed?\s*out|timeout/i', $e->getMessage())
                ? 'provider_timeout'
                : 'provider_unavailable';

            return $this->fetchResult($status, null, null, $started);
        }

        if ($response->getStatusCode() === 204) {
            return $this->fetchResult('success', ['departures' => []], 204, $started);
        }

        if (!$response->isOk()) {
            $httpStatus = $response->getStatusCode();
            $status = match (true) {
                $httpStatus === 429 => 'provider_rate_limited',
                in_array($httpStatus, [401, 403], true) => 'provider_authentication_failed',
                $httpStatus === 404 => 'flight_not_found',
                $httpStatus >= 400 && $httpStatus < 500 => 'provider_rejected_request',
                $httpStatus >= 500 => 'provider_unavailable',
                default => 'provider_unavailable',
            };

            return $this->fetchResult($status, null, $httpStatus, $started);
        }

        try {
            $payload = $response->getJson();
        } catch (Throwable $e) {
            return $this->fetchResult('provider_invalid_response', null, $response->getStatusCode(), $started);
        }

        if (!is_array($payload) || !array_key_exists('departures', $payload) || !is_array($payload['departures'])) {
            return $this->fetchResult('provider_invalid_response', null, $response->getStatusCode(), $started);
        }

        return $this->fetchResult('success', $payload, $response->getStatusCode(), $started);
    }

    /**
     * @return array{status:string,payload:?array,http_status:?int,latency_ms:float}
     */
    private function fetchFlightByNumber(string $flightNumber, string $date, ?float $deadline = null): array
    {
        $url = $this->baseUrl
            . '/flights/number/' . rawurlencode($flightNumber)
            . '/' . rawurlencode($date);
        $started = microtime(true);
        try {
            $remainingTimeout = $deadline !== null
                ? max(1, min($this->timeoutSeconds, (int)ceil($deadline - microtime(true))))
                : $this->timeoutSeconds;
            $request = function () use ($url, $remainingTimeout): Response {
                return $this->http->get($url, [
                    'dateLocalRole' => 'Departure',
                    'withAircraftImage' => 'false',
                    'withLocation' => 'false',
                    'withFlightPlan' => 'false',
                ], [
                    'headers' => [
                        'x-rapidapi-key' => $this->apiKey,
                        'x-rapidapi-host' => $this->apiHost,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => $remainingTimeout,
                ]);
            };
            $response = $this->requestExecutor !== null
                ? ($this->requestExecutor)($url, $request)
                : $request();
            if (!$response instanceof Response) {
                throw new UnexpectedValueException('AIR provider executor returned an invalid response');
            }
        } catch (Throwable $e) {
            $status = preg_match('/timed?\s*out|timeout/i', $e->getMessage())
                ? 'provider_timeout'
                : 'provider_unavailable';

            return $this->fetchResult($status, null, null, $started);
        }

        if ($response->getStatusCode() === 204) {
            return $this->fetchResult('success_no_data', [], 204, $started);
        }
        if (!$response->isOk()) {
            $httpStatus = $response->getStatusCode();
            $status = match (true) {
                $httpStatus === 429 => 'provider_rate_limited',
                in_array($httpStatus, [401, 403], true) => 'provider_authentication_failed',
                $httpStatus === 404 => 'flight_not_found',
                $httpStatus >= 400 && $httpStatus < 500 => 'provider_rejected_request',
                $httpStatus >= 500 => 'provider_unavailable',
                default => 'provider_unavailable',
            };

            return $this->fetchResult($status, null, $httpStatus, $started);
        }

        try {
            $payload = $response->getJson();
        } catch (Throwable $e) {
            return $this->fetchResult('provider_invalid_response', null, $response->getStatusCode(), $started);
        }
        if (!is_array($payload) || !array_is_list($payload)) {
            return $this->fetchResult('provider_invalid_response', null, $response->getStatusCode(), $started);
        }

        return $this->fetchResult('success', $payload, $response->getStatusCode(), $started);
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
        // Current AeroDataBox FIDS responses expose operator and marketing services as
        // separate rows. Retain support for the older/non-FIDS shape, then link the
        // contract-shaped rows after the whole response has been mapped.
        $operatingFlight = is_array($row['operatingFlight'] ?? null) ? $row['operatingFlight'] : [];
        $operatingAirline = is_array($operatingFlight['airline'] ?? null) ? $operatingFlight['airline'] : [];

        $depAirport = $this->extractAirportIata($departure, $movement, $fromIata);
        $arrAirport = $this->extractAirportIata($arrival, $movement, $toIata);
        if ($depAirport !== $fromIata || $arrAirport !== $toIata) {
            return null;
        }

        $flightNumber = strtoupper(trim((string)($row['number'] ?? '')));
        $operatingFlightNumber = strtoupper(trim((string)($operatingFlight['number'] ?? '')));
        $marketingCarrier = trim((string)($airline['name'] ?? ($airline['shortName'] ?? '')));
        $operatingCarrier = trim((string)($operatingAirline['name'] ?? ($operatingAirline['shortName'] ?? '')));
        if ($operatingCarrier === '') {
            $operatingCarrier = $marketingCarrier;
        }

        $scheduledDeparture = $this->extractTimeSet($departure, ['scheduledTime']);
        $scheduledArrival = $this->extractTimeSet($arrival, ['scheduledTime']);
        if ($scheduledDeparture['local'] === null && $scheduledDeparture['utc'] === null) {
            return null;
        }
        $status = isset($row['status']) ? (string)$row['status'] : null;
        $statusKey = strtolower(trim((string)$status));
        $departureOccurred = in_array(
            $statusKey,
            ['departed', 'enroute', 'approaching', 'arrived', 'diverted'],
            true,
        );
        $arrivalOccurred = $statusKey === 'arrived';
        $actualDeparture = $this->extractTimeSet($departure, ['actualTime']);
        if ($actualDeparture['local'] === null && $actualDeparture['utc'] === null && $departureOccurred) {
            $actualDeparture = $this->extractTimeSet($departure, ['revisedTime', 'runwayTime']);
        }
        $actualArrival = $this->extractTimeSet($arrival, ['actualTime']);
        if ($actualArrival['local'] === null && $actualArrival['utc'] === null && $arrivalOccurred) {
            $actualArrival = $this->extractTimeSet($arrival, ['revisedTime', 'runwayTime']);
        }
        $estimatedDeparture = $departureOccurred
            ? $this->extractTimeSet($departure, ['predictedTime', 'estimatedTime'])
            : $this->extractTimeSet($departure, ['revisedTime', 'predictedTime', 'estimatedTime', 'runwayTime']);
        $estimatedArrival = $arrivalOccurred
            ? $this->extractTimeSet($arrival, ['predictedTime', 'estimatedTime'])
            : $this->extractTimeSet($arrival, ['revisedTime', 'predictedTime', 'estimatedTime', 'runwayTime']);
        $cancelled = $this->deriveCancelled($row, $status);
        $diverted = isset($row['isDiverted'])
            ? (bool)$row['isDiverted']
            : (stripos((string)$status, 'divert') !== false ? true : null);

        $codeshares = [];
        $codeshareStatus = trim((string)($row['codeshareStatus'] ?? ''));
        if ($codeshareStatus !== '' && strcasecmp($codeshareStatus, 'IsCodeshared') === 0 && $flightNumber !== '') {
            $codeshares[] = $flightNumber;
        }
        if ($operatingFlightNumber !== '') {
            $codeshares[] = $operatingFlightNumber;
        }

        return [
            'flight_key' => md5(implode('|', [
                $depAirport,
                $arrAirport,
                $scheduledDeparture['utc'] ?? $scheduledDeparture['local'],
                $flightNumber,
                'aerodatabox',
            ])),
            'flight_number' => $flightNumber,
            'marketing_flight_number' => $flightNumber,
            'operating_flight_number' => $operatingFlightNumber,
            'carrier_name' => $marketingCarrier !== '' ? $marketingCarrier : $operatingCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'operating_carrier_iata' => strtoupper(trim((string)($operatingAirline['iata'] ?? ''))),
            'operating_carrier_icao' => strtoupper(trim((string)($operatingAirline['icao'] ?? ''))),
            'marketing_carrier_name' => $marketingCarrier,
            'marketing_carrier_iata' => strtoupper(trim((string)($airline['iata'] ?? ''))),
            'marketing_carrier_icao' => strtoupper(trim((string)($airline['icao'] ?? ''))),
            'departure_airport_iata' => $depAirport,
            'departure_airport_icao' => $this->extractAirportIcao($departure),
            'arrival_airport_iata' => $arrAirport,
            'arrival_airport_icao' => $this->extractAirportIcao($arrival),
            'scheduled_departure_local' => $scheduledDeparture['local'],
            'scheduled_departure_utc' => $scheduledDeparture['utc'],
            'departure_timezone' => $scheduledDeparture['timezone'],
            'scheduled_arrival_local' => $scheduledArrival['local'],
            'scheduled_arrival_utc' => $scheduledArrival['utc'],
            'arrival_timezone' => $scheduledArrival['timezone'],
            'estimated_departure_local' => $estimatedDeparture['local'],
            'estimated_departure_utc' => $estimatedDeparture['utc'],
            'estimated_arrival_local' => $estimatedArrival['local'],
            'estimated_arrival_utc' => $estimatedArrival['utc'],
            'actual_departure_local' => $actualDeparture['local'],
            'actual_departure_utc' => $actualDeparture['utc'],
            'actual_arrival_local' => $actualArrival['local'],
            'actual_arrival_utc' => $actualArrival['utc'],
            'status' => $status,
            'cancelled' => $cancelled,
            'diverted' => $diverted,
            'source' => 'aerodatabox',
            'provider_flight_id' => (string)($row['id'] ?? $row['number'] ?? ''),
            'retrieved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'codeshare_numbers' => $codeshares,
            'codeshare_role' => match (strtolower($codeshareStatus)) {
                'isoperator' => 'operator',
                'iscodeshared' => 'marketing',
                default => 'unknown',
            },
            'time_provenance' => [
                'scheduled_departure' => $scheduledDeparture['source'],
                'scheduled_arrival' => $scheduledArrival['source'],
                'estimated_departure' => $estimatedDeparture['source'],
                'estimated_arrival' => $estimatedArrival['source'],
                'actual_departure' => $actualDeparture['source'],
                'actual_arrival' => $actualArrival['source'],
            ],
            '_codeshare_status' => strtolower($codeshareStatus),
        ];
    }

    /**
     * Link AeroDataBox FIDS operator/marketing rows for the same physical leg.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function linkCodeshareRows(array $items): array
    {
        $groups = [];
        foreach ($items as $index => $item) {
            $signature = implode('|', [
                (string)($item['departure_airport_iata'] ?? ''),
                (string)($item['arrival_airport_iata'] ?? ''),
                (string)($item['scheduled_departure_utc'] ?? $item['scheduled_departure_local'] ?? ''),
                (string)($item['scheduled_arrival_utc'] ?? $item['scheduled_arrival_local'] ?? ''),
            ]);
            $groups[$signature][] = $index;
        }

        foreach ($groups as $indexes) {
            $operatorIndexes = [];
            $numbers = [];
            foreach ($indexes as $index) {
                $number = trim((string)($items[$index]['flight_number'] ?? ''));
                if ($number !== '') {
                    $numbers[] = $number;
                }
                if (($items[$index]['_codeshare_status'] ?? '') === 'isoperator') {
                    $operatorIndexes[] = $index;
                }
            }
            $operatorIndex = count($operatorIndexes) === 1 ? $operatorIndexes[0] : null;
            $numbers = array_values(array_unique($numbers));
            foreach ($indexes as $index) {
                $items[$index]['codeshare_numbers'] = array_values(array_unique(array_merge(
                    (array)($items[$index]['codeshare_numbers'] ?? []),
                    $numbers,
                )));
                if ($operatorIndex !== null) {
                    $operator = $items[$operatorIndex];
                    $items[$index]['operating_flight_number'] = (string)($operator['flight_number'] ?? '');
                    $items[$index]['operating_carrier_name'] = (string)($operator['marketing_carrier_name'] ?? '');
                    $items[$index]['operating_carrier_iata'] = (string)($operator['marketing_carrier_iata'] ?? '');
                    $items[$index]['operating_carrier_icao'] = (string)($operator['marketing_carrier_icao'] ?? '');
                }
                unset($items[$index]['_codeshare_status']);
            }
        }

        return $items;
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
    private function extractAirportIcao(array $movement): ?string
    {
        $value = strtoupper(trim((string)($movement['airport']['icao'] ?? '')));

        return preg_match('/^[A-Z]{4}$/', $value) ? $value : null;
    }

    /**
     * @param array<string,mixed> $movement
     * @param array<int,string> $keys
     */
    private function extractTimeSet(array $movement, array $keys): array
    {
        foreach ($keys as $key) {
            $set = is_array($movement[$key] ?? null) ? $movement[$key] : [];
            $local = trim((string)($set['local'] ?? $movement[$key . 'Local'] ?? ''));
            $utc = trim((string)($set['utc'] ?? $movement[$key . 'Utc'] ?? ''));
            if ($local !== '' || $utc !== '') {
                return [
                    'local' => $local !== '' ? $local : null,
                    'utc' => $utc !== '' ? $utc : null,
                    'timezone' => trim((string)(
                        $movement['airport']['timeZone']
                        ?? $movement['airport']['timezone']
                        ?? ''
                    )) ?: null,
                    'source' => $key,
                ];
            }
        }

        return ['local' => null, 'utc' => null, 'timezone' => null, 'source' => null];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function deriveCancelled(array $row, ?string $status): ?bool
    {
        if (isset($row['isCancelled'])) {
            return (bool)$row['isCancelled'];
        }
        $status = strtolower(trim((string)$status));
        if ($status === '') {
            return null;
        }

        return str_contains($status, 'cancel') || str_contains($status, 'annul');
    }

    /** @return array{status:string,payload:?array,http_status:?int,latency_ms:float} */
    private function fetchResult(string $status, ?array $payload, ?int $httpStatus, float $started): array
    {
        return [
            'status' => $status,
            'payload' => $payload,
            'http_status' => $httpStatus,
            'latency_ms' => round((microtime(true) - $started) * 1000, 3),
        ];
    }

    /** @param array<string,mixed> $extra */
    private function outcome(string $status, float $started, array $extra = []): array
    {
        return $extra + [
            'provider' => 'aerodatabox',
            'status' => $status,
            'latency_ms' => round((microtime(true) - $started) * 1000, 3),
        ];
    }

    /** Read an environment value without logging it. */
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
