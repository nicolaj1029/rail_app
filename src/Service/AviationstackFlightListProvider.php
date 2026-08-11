<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use DateTimeImmutable;
use Throwable;
use UnexpectedValueException;

final class AviationstackFlightListProvider implements
    FlightListProviderInterface,
    FlightListProviderDiagnosticsInterface
{
    private string $apiKey;
    private Client $http;
    private string $endpoint;
    private int $timeoutSeconds;
    /**
     * @var callable|null
     */
    private $requestExecutor;
    /**
     * @var array<string,mixed>
     */
    private array $lastOutcome = ['status' => 'not_started', 'provider' => 'aviationstack'];

    /** Construct a bounded Aviationstack client. */
    public function __construct(
        ?string $apiKey = null,
        ?Client $http = null,
        ?string $endpoint = null,
        ?callable $requestExecutor = null,
    ) {
        $config = (array)Configure::read('External.aviationstack');
        $this->apiKey = trim((string)($apiKey ?? $config['apiKey'] ?? $this->env('AVIATIONSTACK_API_KEY') ?? ''));
        $timeout = max(1, (int)($config['timeoutSeconds'] ?? 5));
        $connectTimeout = max(1, min($timeout, (int)($config['connectTimeoutSeconds'] ?? 2)));
        $this->http = $http ?? new Client(['timeout' => $timeout, 'connect_timeout' => $connectTimeout]);
        $this->timeoutSeconds = $timeout;
        $this->endpoint = rtrim((string)(
            $endpoint
            ?? $config['baseUrl']
            ?? $this->env('AVIATIONSTACK_BASE_URL')
            ?? 'https://api.aviationstack.com/v1/flights'
        ), '/');
        $this->requestExecutor = $requestExecutor;
    }

    /** Search the provider and classify all response categories. */
    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $started = microtime(true);
        if (!$this->isConfigured()) {
            $this->lastOutcome = $this->outcome('configuration_missing', $started);

            return [];
        }

        try {
            $deadline = is_numeric($context['_deadline_monotonic'] ?? null)
                ? (float)$context['_deadline_monotonic']
                : null;
            $remainingTimeout = $deadline !== null
                ? max(1, min($this->timeoutSeconds, (int)ceil($deadline - microtime(true))))
                : $this->timeoutSeconds;
            $request = fn(): Response => $this->http->get($this->endpoint, [
                    'access_key' => $this->apiKey,
                    'dep_iata' => strtoupper(trim($fromIata)),
                    'arr_iata' => strtoupper(trim($toIata)),
                    'flight_date' => trim($date),
                ], ['timeout' => $remainingTimeout]);
            $response = $this->requestExecutor !== null
                ? ($this->requestExecutor)($this->endpoint, $request)
                : $request();
            if (!$response instanceof Response) {
                throw new UnexpectedValueException('AIR provider executor returned an invalid response');
            }
        } catch (Throwable $e) {
            $status = preg_match('/timed?\s*out|timeout/i', $e->getMessage())
                ? 'provider_timeout'
                : 'provider_unavailable';
            $this->lastOutcome = $this->outcome($status, $started);

            return [];
        }

        if (!$response->isOk()) {
            $httpStatus = $response->getStatusCode();
            $status = match (true) {
                $httpStatus === 429 => 'provider_rate_limited',
                in_array($httpStatus, [401, 403], true) => 'provider_authentication_failed',
                $httpStatus === 404 => 'flight_not_found',
                $httpStatus >= 400 && $httpStatus < 500 => 'provider_rejected_request',
                default => 'provider_unavailable',
            };
            $this->lastOutcome = $this->outcome($status, $started, ['http_status' => $httpStatus]);

            return [];
        }

        try {
            $payload = $response->getJson();
        } catch (Throwable $e) {
            $this->lastOutcome = $this->outcome(
                'provider_invalid_response',
                $started,
                ['http_status' => $response->getStatusCode()],
            );

            return [];
        }

        if (!is_array($payload) || !array_key_exists('data', $payload) || !is_array($payload['data'])) {
            $this->lastOutcome = $this->outcome(
                'provider_invalid_response',
                $started,
                ['http_status' => $response->getStatusCode()],
            );

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

        $this->lastOutcome = $this->outcome($out === [] ? 'success_no_data' : 'success', $started, [
            'http_status' => $response->getStatusCode(),
            'item_count' => count($out),
        ]);

        return $out;
    }

    /** Return whether the required provider configuration is present. */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Return the stable provider identifier. */
    public function getProviderName(): string
    {
        return 'aviationstack';
    }

    /** Return redacted diagnostics for the most recent search. */
    public function getLastOutcome(): array
    {
        return $this->lastOutcome;
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
        $operatingFlightNumber = strtoupper(trim((string)($codeshared['flight_iata'] ?? '')));
        $status = isset($row['flight_status']) ? (string)$row['flight_status'] : null;

        return [
            'flight_key' => md5(implode('|', [
                $depIata, $arrIata, $depScheduled, $arrScheduled,
                $flightNumber, 'aviationstack',
            ])),
            'flight_number' => $flightNumber,
            'marketing_flight_number' => $flightNumber,
            'operating_flight_number' => $operatingFlightNumber,
            'carrier_name' => $marketingCarrier !== '' ? $marketingCarrier : $operatingCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'operating_carrier_iata' => strtoupper(trim((string)($codeshared['airline_iata'] ?? ''))),
            'operating_carrier_icao' => strtoupper(trim((string)($codeshared['airline_icao'] ?? ''))),
            'marketing_carrier_name' => $marketingCarrier,
            'marketing_carrier_iata' => strtoupper(trim((string)($airline['iata'] ?? ''))),
            'marketing_carrier_icao' => strtoupper(trim((string)($airline['icao'] ?? ''))),
            'departure_airport_iata' => $depIata,
            'departure_airport_icao' => strtoupper(trim((string)($departure['icao'] ?? ''))),
            'arrival_airport_iata' => $arrIata,
            'arrival_airport_icao' => strtoupper(trim((string)($arrival['icao'] ?? ''))),
            'scheduled_departure_local' => $depScheduled,
            'scheduled_departure_utc' => $depScheduled,
            'departure_timezone' => trim((string)($departure['timezone'] ?? '')),
            'scheduled_arrival_local' => $arrScheduled,
            'scheduled_arrival_utc' => $arrScheduled,
            'arrival_timezone' => trim((string)($arrival['timezone'] ?? '')),
            'estimated_departure_local' => $this->normalizeDateTime((string)($departure['estimated'] ?? '')),
            'estimated_departure_utc' => $this->normalizeDateTime((string)($departure['estimated'] ?? '')),
            'actual_departure_local' => $this->normalizeDateTime((string)($departure['actual'] ?? '')),
            'actual_departure_utc' => $this->normalizeDateTime((string)($departure['actual'] ?? '')),
            'estimated_arrival_local' => $this->normalizeDateTime((string)($arrival['estimated'] ?? '')),
            'estimated_arrival_utc' => $this->normalizeDateTime((string)($arrival['estimated'] ?? '')),
            'actual_arrival_local' => $this->normalizeDateTime((string)($arrival['actual'] ?? '')),
            'actual_arrival_utc' => $this->normalizeDateTime((string)($arrival['actual'] ?? '')),
            'status' => $status,
            'cancelled' => $status !== null && stripos($status, 'cancel') !== false
                ? true
                : ($status !== null ? false : null),
            'diverted' => $status !== null && stripos($status, 'divert') !== false ? true : null,
            'source' => 'aviationstack',
            'provider_flight_id' => (string)($flight['icao'] ?? $flight['iata'] ?? ''),
            'retrieved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'codeshare_numbers' => $this->extractCodeshares($codeshared),
            'codeshare_role' => $codeshared !== [] ? 'marketing' : 'operator',
            'time_provenance' => [
                'scheduled_departure' => 'departure.scheduled',
                'scheduled_arrival' => 'arrival.scheduled',
                'estimated_departure' => !empty($departure['estimated']) ? 'departure.estimated' : null,
                'estimated_arrival' => !empty($arrival['estimated']) ? 'arrival.estimated' : null,
                'actual_departure' => !empty($departure['actual']) ? 'departure.actual' : null,
                'actual_arrival' => !empty($arrival['actual']) ? 'arrival.actual' : null,
            ],
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

    /** Preserve the provider offset while normalizing timestamp syntax. */
    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $time = new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }

        return $time->format('Y-m-d\TH:i:sP');
    }

    /** @param array<string,mixed> $extra */
    private function outcome(string $status, float $started, array $extra = []): array
    {
        return $extra + [
            'provider' => 'aviationstack',
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
