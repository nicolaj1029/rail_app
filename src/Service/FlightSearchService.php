<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Log\Log;
use DateTimeImmutable;
use ReflectionClass;
use Throwable;

final class FlightSearchService
{
    private const PROVIDER_DIAGNOSTIC_CACHE_KEY = 'air_provider_last_outcome_v1';

    /**
     * @var array<int,\App\Service\FlightListProviderInterface>
     */
    private array $providers;
    private AirFlightNormalizer $normalizer;
    /**
     * @var array<string,mixed>
     */
    private array $lastOutcome = [];

    /** Construct the AIR provider chain and normalizer. */
    public function __construct(
        ?array $providers = null,
        private ?TransportOperatorRegistry $operatorRegistry = null,
        ?AirFlightNormalizer $normalizer = null,
    ) {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
        $this->normalizer = $normalizer ?? new AirFlightNormalizer($this->operatorRegistry);
        $this->providers = $providers ?? $this->buildProviderChain();
    }

    /**
     * Backwards-compatible item-only API used by the existing flow.
     *
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function search(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        return $this->searchWithMeta($fromIata, $toIata, $date, $context)['items'];
    }

    /**
     * Stable AIR lookup result with provider/freshness/performance diagnostics.
     *
     * @param array<string,mixed> $context
     * @return array{status:string,user_message:string,items:array<int,array<string,mixed>>,manual_fallback:bool,cache:array<string,mixed>,timing:array<string,mixed>,provider_attempts:array<int,array<string,mixed>>,request_id:string}
     */
    public function searchWithMeta(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $started = microtime(true);
        $requestId = $this->requestId((string)($context['requestId'] ?? ''));
        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        $context = $this->normalizeContext($context);

        $validationError = $this->validateRequest($fromIata, $toIata, $date, $context);
        if ($validationError !== null) {
            return $this->finish([
                'status' => 'invalid_request',
                'user_message' => $validationError,
                'items' => [],
                'manual_fallback' => true,
                'cache' => ['status' => 'bypass', 'age_seconds' => null, 'expires_at' => null],
                'provider_attempts' => [],
                'request_id' => $requestId,
            ], $started, 0.0);
        }

        $cacheKey = $this->cacheKey($fromIata, $toIata, $date, $context);
        $cacheConfig = $this->cacheConfig();
        $cached = Cache::read($cacheKey, $cacheConfig);
        if (is_array($cached) && is_array($cached['result'] ?? null) && (int)($cached['expires_epoch'] ?? 0) > time()) {
            $result = (array)$cached['result'];
            $result['cache'] = [
                'status' => 'hit',
                'age_seconds' => max(0, time() - (int)($cached['written_epoch'] ?? time())),
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', (int)$cached['expires_epoch']),
            ];
            $result['request_id'] = $requestId;

            return $this->finish($result, $started, 0.0);
        }

        $items = [];
        $attempts = [];
        $providerLatency = 0.0;
        $budgetSeconds = max(1, (int)Configure::read('External.airLookup.budgetSeconds', 8));
        $deadline = microtime(true) + $budgetSeconds;
        $providerContext = $context + ['_deadline_monotonic' => $deadline];
        foreach ($this->providers as $provider) {
            if (microtime(true) >= $deadline) {
                $attempts[] = [
                    'provider' => 'air_lookup',
                    'status' => 'lookup_budget_exhausted',
                    'latency_ms' => 0.0,
                    'http_status' => null,
                    'item_count' => 0,
                ];
                break;
            }
            $providerStarted = microtime(true);
            try {
                $providerItems = $provider->searchByRouteAndDate($fromIata, $toIata, $date, $providerContext);
            } catch (Throwable $e) {
                $providerItems = [];
                $failureStatus = preg_match('/timed?\s*out|timeout/i', $e->getMessage())
                    ? 'provider_timeout'
                    : 'provider_unavailable';
                $diagnostic = [
                    'provider' => $this->providerName($provider),
                    'status' => $failureStatus,
                ];
            }
            $elapsed = round((microtime(true) - $providerStarted) * 1000, 3);
            $providerLatency += $elapsed;
            if (!isset($diagnostic)) {
                $diagnostic = $provider instanceof FlightListProviderDiagnosticsInterface
                    ? $provider->getLastOutcome()
                    : [
                        'provider' => $this->providerName($provider),
                        'status' => $providerItems === [] ? 'success_no_data' : 'success',
                    ];
            }
            $attempts[] = $this->sanitizeAttempt($diagnostic, $elapsed);
            unset($diagnostic);

            foreach ($providerItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $normalized = $this->normalizer->normalize($item, $fromIata, $toIata, $context);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
            if ($items !== []) {
                break;
            }
        }

        $items = $this->dedupeAndSort($items, (string)($context['flightNumber'] ?? ''));
        $this->rememberProviderOutcome($attempts);
        $status = $this->resultStatus($attempts, $items);
        $result = [
            'status' => $status,
            'user_message' => $this->userMessage($status),
            'items' => $items,
            'manual_fallback' => $items === [] || !$this->hasVerifiedItem($items),
            'cache' => ['status' => 'miss', 'age_seconds' => null, 'expires_at' => null],
            'provider_attempts' => $attempts,
            'request_id' => $requestId,
            'normalized_identity' => [
                'departure_iata' => $fromIata,
                'arrival_iata' => $toIata,
                'service_date' => $date,
                'flight_number' => $this->normalizer->normalizeFlightNumber(
                    (string)($context['flightNumber'] ?? ''),
                ),
            ],
        ];

        if ($this->isCacheable($status, $attempts)) {
            $ttl = $this->ttlFor($date, $items, $status);
            $expires = time() + $ttl;
            $result['cache']['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', $expires);
            Cache::write($cacheKey, [
                'written_epoch' => time(),
                'expires_epoch' => $expires,
                'result' => $result,
            ], $cacheConfig);
        }

        return $this->finish($result, $started, $providerLatency);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<int,array<string,mixed>>
     */
    public function searchFromForm(array $form, array $meta = []): array
    {
        $fromIata = $this->resolveAirportCode(
            (string)($form['dep_station_lookup_code'] ?? ''),
            (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
        );
        $toIata = $this->resolveAirportCode(
            (string)($form['arr_station_lookup_code'] ?? ''),
            (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
        );
        $date = trim((string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')));

        return $this->search($fromIata, $toIata, $date, [
            'depTime' => (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')),
            'arrTime' => (string)($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')),
            'flightNumber' => (string)($form['ticket_no'] ?? ($form['flight_number'] ?? '')),
            'marketingCarrier' => (string)(
                $form['marketing_carrier']
                ?? $form['operator']
                ?? $meta['_auto']['marketing_carrier']['value']
                ?? ''
            ),
            'operatingCarrier' => (string)(
                $form['operating_carrier']
                ?? $meta['_auto']['operating_carrier']['value']
                ?? ''
            ),
            'departureLabel' => (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
            'arrivalLabel' => (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
        ]);
    }

    /** @return array<string,mixed> */
    public function getLastOutcome(): array
    {
        return $this->lastOutcome;
    }

    /** @return array<string,mixed> */
    public function health(): array
    {
        $aeroDataBox = new AeroDataBoxFlightListProvider();
        $aviationstack = new AviationstackFlightListProvider();
        $liveApisEnabled = (bool)Configure::read('External.useLiveApis');
        $liveProviderConfigured = $aeroDataBox->isConfigured() || $aviationstack->isConfigured();
        $lastOutcome = Cache::read(self::PROVIDER_DIAGNOSTIC_CACHE_KEY, $this->cacheConfig());
        $lastOutcome = is_array($lastOutcome) ? $lastOutcome : null;
        $lastProviderVerified = $lastOutcome !== null
            && in_array(
                (string)($lastOutcome['status'] ?? ''),
                ['success', 'success_no_data'],
                true,
            );
        $providers = [
            ['provider' => 'aerodatabox', 'configured' => $aeroDataBox->isConfigured()],
            ['provider' => 'aviationstack', 'configured' => $aviationstack->isConfigured()],
            ['provider' => 'ticketless_seed', 'configured' => true],
        ];

        return [
            'application' => 'ok',
            'live_apis_enabled' => $liveApisEnabled,
            'live_provider_configured' => $liveProviderConfigured,
            'live_provider_ready' => $liveApisEnabled && $liveProviderConfigured && $lastProviderVerified,
            'last_provider_outcome' => $lastOutcome,
            'providers' => $providers,
            'cache_configured' => Cache::getConfig($this->cacheConfig()) !== null,
        ];
    }

    /** @param array<int,array<string,mixed>> $attempts */
    private function rememberProviderOutcome(array $attempts): void
    {
        foreach ($attempts as $attempt) {
            if (!in_array((string)($attempt['provider'] ?? ''), ['aerodatabox', 'aviationstack'], true)) {
                continue;
            }
            Cache::write(self::PROVIDER_DIAGNOSTIC_CACHE_KEY, [
                'provider' => (string)$attempt['provider'],
                'status' => (string)($attempt['status'] ?? 'provider_unavailable'),
                'latency_ms' => round((float)($attempt['latency_ms'] ?? 0.0), 3),
                'http_status' => isset($attempt['http_status']) ? (int)$attempt['http_status'] : null,
                'item_count' => max(0, (int)($attempt['item_count'] ?? 0)),
                'request_count' => max(0, (int)($attempt['request_count'] ?? 0)),
                'request_statuses' => array_values((array)($attempt['request_statuses'] ?? [])),
                'http_statuses' => array_values((array)($attempt['http_statuses'] ?? [])),
                'checked_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ], $this->cacheConfig());

            return;
        }
    }

    /** Resolve a strict IATA code from flow metadata. */
    private function resolveAirportCode(string $lookupCode, string $label): string
    {
        $lookupCode = strtoupper(trim($lookupCode));
        if (preg_match('/^[A-Z]{3}$/', $lookupCode)) {
            return $lookupCode;
        }
        $label = strtoupper(trim($label));

        return preg_match('/\b([A-Z]{3})\b/', $label, $m) ? $m[1] : '';
    }

    /** @return array<int,\App\Service\FlightListProviderInterface> */
    private function buildProviderChain(): array
    {
        $providers = [];
        if ((bool)Configure::read('External.useLiveApis')) {
            $aeroDataBox = new AeroDataBoxFlightListProvider();
            if ($aeroDataBox->isConfigured()) {
                $providers[] = $aeroDataBox;
            }
            $aviationstack = new AviationstackFlightListProvider();
            if ($aviationstack->isConfigured()) {
                $providers[] = $aviationstack;
            }
        }
        $providers[] = new SeededFlightListProvider($this->operatorRegistry);

        return $providers;
    }

    /** @param array<string,mixed> $context */
    private function validateRequest(string $from, string $to, string $date, array $context): ?string
    {
        if (!preg_match('/^[A-Z]{3}$/', $from) || !preg_match('/^[A-Z]{3}$/', $to)) {
            return 'departure and arrival must be valid three-letter IATA airport codes';
        }
        if ($from === $to) {
            return 'departure and arrival must be different airports';
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = $errors !== false
            && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if ($parsed === false || $hasDateErrors || $parsed->format('Y-m-d') !== $date) {
            return 'date must be a real calendar date in YYYY-MM-DD format';
        }
        $flightNumber = (string)($context['flightNumber'] ?? '');
        if ($flightNumber !== '' && $this->normalizer->normalizeFlightNumber($flightNumber) === '') {
            return 'flightNumber has an invalid format';
        }

        return null;
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function normalizeContext(array $context): array
    {
        foreach (['marketingCarrier', 'operatingCarrier', 'departureLabel', 'arrivalLabel'] as $key) {
            $context[$key] = mb_substr(trim((string)($context[$key] ?? '')), 0, 160);
        }
        foreach (['depTime', 'arrTime'] as $key) {
            $value = trim((string)($context[$key] ?? ''));
            $context[$key] = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
        }
        $context['flightNumber'] = mb_substr(trim((string)($context['flightNumber'] ?? '')), 0, 16);

        return $context;
    }

    /** @param array<string,mixed> $context */
    private function cacheKey(string $from, string $to, string $date, array $context): string
    {
        return 'air_flights_' . hash('sha256', json_encode([
            'v' => 3,
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'flight' => $this->normalizer->normalizeFlightNumber((string)($context['flightNumber'] ?? '')),
            'carrier' => strtolower((string)($context['marketingCarrier'] ?? '')),
            'op' => strtolower((string)($context['operatingCarrier'] ?? '')),
            'depTime' => (string)($context['depTime'] ?? ''),
            'arrTime' => (string)($context['arrTime'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Select the dedicated cache, retaining compatibility with older config. */
    private function cacheConfig(): string
    {
        return Cache::getConfig('air_flights') !== null ? 'air_flights' : 'default';
    }

    /** @param array<int,array<string,mixed>> $attempts @param array<int,array<string,mixed>> $items */
    private function resultStatus(array $attempts, array $items): string
    {
        $verified = $this->hasVerifiedItem($items);
        $statuses = array_map(static fn(array $attempt): string => (string)($attempt['status'] ?? ''), $attempts);
        $technical = array_intersect($statuses, $this->technicalStatuses()) !== [];
        if ($verified) {
            return $technical || count($attempts) > 1 ? 'success_fallback' : 'success';
        }
        if ($items !== []) {
            if ($technical) {
                return 'degraded_manual_fallback';
            }
            if (array_intersect($statuses, ['success_no_data', 'flight_not_found']) !== []) {
                return 'flight_not_found_manual_fallback';
            }
            $providerNames = array_map(
                static fn(array $attempt): string => (string)($attempt['provider'] ?? ''),
                $attempts,
            );
            $liveApisEnabled = (bool)Configure::read('External.useLiveApis');
            if ($liveApisEnabled && array_diff($providerNames, ['ticketless_seed']) === []) {
                return 'no_provider_configured_manual_fallback';
            }

            return 'manual_fallback';
        }
        if ($technical) {
            return 'providers_unavailable';
        }
        if (array_intersect($statuses, ['success_no_data', 'flight_not_found']) !== []) {
            return 'flight_not_found';
        }

        return $attempts === [] ? 'no_provider_configured' : 'provider_no_data';
    }

    /** Map internal lookup status to a safe user-facing explanation. */
    private function userMessage(string $status): string
    {
        return match ($status) {
            'success', 'success_fallback' => '',
            'manual_fallback' => 'Live flight data is disabled; verify the ticket-derived flight manually.',
            'no_provider_configured_manual_fallback' => 'No live flight provider is configured; '
                . 'verify the ticket-derived flight manually.',
            'flight_not_found', 'flight_not_found_manual_fallback' => 'No provider flight matched this route and date. '
                . 'This does not mean the flight was cancelled.',
            'degraded_manual_fallback', 'providers_unavailable' => 'Live flight data is temporarily unavailable. '
                . 'Use the manual option and verify the flight later.',
            'no_provider_configured' => 'No live flight provider is configured. Use the manual option.',
            default => 'No usable flight data is available for this lookup.',
        };
    }

    /** @param array<int,array<string,mixed>> $attempts */
    private function isCacheable(string $status, array $attempts): bool
    {
        $statuses = array_map(
            static fn(array $attempt): string => (string)($attempt['status'] ?? ''),
            $attempts,
        );
        if (array_intersect($statuses, $this->technicalStatuses()) !== []) {
            return false;
        }

        return in_array($status, [
            'success', 'success_fallback', 'manual_fallback', 'no_provider_configured_manual_fallback',
            'flight_not_found', 'flight_not_found_manual_fallback',
        ], true);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function ttlFor(string $date, array $items, string $status): int
    {
        $config = (array)Configure::read('External.airLookup');
        if (str_contains($status, 'not_found')) {
            return max(15, (int)($config['noDataCacheSeconds'] ?? 60));
        }
        $statuses = array_column($items, 'status');
        if (array_intersect($statuses, ['active', 'departed', 'delayed']) !== []) {
            return max(15, (int)($config['activeCacheSeconds'] ?? 60));
        }
        if ($date < gmdate('Y-m-d') || array_intersect($statuses, ['arrived', 'cancelled', 'diverted']) !== []) {
            return max(60, (int)($config['historicalCacheSeconds'] ?? 86400));
        }

        return max(60, (int)($config['futureCacheSeconds'] ?? 900));
    }

    /** @param array<int,array<string,mixed>> $items */
    private function hasVerifiedItem(array $items): bool
    {
        foreach ($items as $item) {
            if (!empty($item['operational_data_verified'])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function technicalStatuses(): array
    {
        return [
            'provider_timeout', 'provider_rate_limited', 'provider_unavailable',
            'provider_invalid_response', 'provider_rejected_request', 'provider_authentication_failed',
            'lookup_budget_exhausted', 'success_degraded',
        ];
    }

    /** @param array<string,mixed> $diagnostic @return array<string,mixed> */
    private function sanitizeAttempt(array $diagnostic, float $fallbackLatency): array
    {
        $attempt = [
            'provider' => mb_substr(trim((string)($diagnostic['provider'] ?? 'provider')), 0, 50),
            'status' => mb_substr(trim((string)($diagnostic['status'] ?? 'provider_unavailable')), 0, 50),
            'latency_ms' => isset($diagnostic['latency_ms'])
                ? round((float)$diagnostic['latency_ms'], 3)
                : $fallbackLatency,
            'http_status' => isset($diagnostic['http_status']) ? (int)$diagnostic['http_status'] : null,
            'item_count' => max(0, (int)($diagnostic['item_count'] ?? 0)),
        ];
        if (is_array($diagnostic['attempts'] ?? null)) {
            $attempt['request_count'] = count($diagnostic['attempts']);
            $attempt['request_statuses'] = array_values(array_unique(array_filter(array_map(
                static fn(array $part): string => mb_substr(trim((string)($part['status'] ?? '')), 0, 50),
                $diagnostic['attempts'],
            ))));
            $attempt['http_statuses'] = array_values(array_unique(array_filter(array_map(
                static fn(array $part): ?int => isset($part['http_status'])
                    && (int)$part['http_status'] >= 100
                    && (int)$part['http_status'] <= 599
                    ? (int)$part['http_status']
                    : null,
                $diagnostic['attempts'],
            ), static fn(?int $status): bool => $status !== null)));
        }

        return $attempt;
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private function dedupeAndSort(array $items, string $requestedFlight): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = (string)($item['flight_identity_key'] ?? $item['flight_key'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = $item;
                continue;
            }
            $groups[$key] = $this->mergeCodeshareRows($groups[$key], $item, $requestedFlight);
        }
        $out = array_values($groups);
        $requested = $this->normalizer->normalizeFlightNumber($requestedFlight);
        usort($out, static function (array $a, array $b) use ($requested): int {
            $aMatch = $requested !== '' && in_array($requested, (array)($a['codeshare_numbers'] ?? []), true) ? 0 : 1;
            $bMatch = $requested !== '' && in_array($requested, (array)($b['codeshare_numbers'] ?? []), true) ? 0 : 1;

            return [$aMatch, (string)($a['scheduled_departure_utc'] ?? $a['scheduled_departure_local'] ?? '')]
                <=> [$bMatch, (string)($b['scheduled_departure_utc'] ?? $b['scheduled_departure_local'] ?? '')];
        });

        return $out;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b @return array<string,mixed> */
    private function mergeCodeshareRows(array $a, array $b, string $requestedFlight): array
    {
        $numbers = array_values(array_unique(array_filter(array_merge(
            (array)($a['codeshare_numbers'] ?? []),
            (array)($b['codeshare_numbers'] ?? []),
            [(string)($a['flight_number'] ?? ''), (string)($b['flight_number'] ?? '')],
        ))));
        $operator = ($b['codeshare_role'] ?? '') === 'operator' ? $b : $a;
        $marketing = ($a['codeshare_role'] ?? '') === 'marketing' ? $a : $b;
        $merged = $operator;
        $marketingFields = [
            'marketing_flight_number', 'marketing_carrier_name',
            'marketing_carrier_iata', 'marketing_carrier_icao',
        ];
        foreach ($marketingFields as $field) {
            if (!empty($marketing[$field])) {
                $merged[$field] = $marketing[$field];
            }
        }
        $requested = $this->normalizer->normalizeFlightNumber($requestedFlight);
        $merged['flight_number'] = $requested !== '' && in_array($requested, $numbers, true)
            ? $requested
            : (string)($merged['marketing_flight_number'] ?? $merged['flight_number'] ?? '');
        $merged['codeshare_numbers'] = $numbers;

        return $merged;
    }

    /** Return a non-sensitive provider identifier for logs and diagnostics. */
    private function providerName(FlightListProviderInterface $provider): string
    {
        if ($provider instanceof FlightListProviderDiagnosticsInterface) {
            return $provider->getProviderName();
        }
        $short = strtolower((new ReflectionClass($provider))->getShortName());

        return mb_substr($short, 0, 50);
    }

    /** Validate or generate the request correlation identifier. */
    private function requestId(string $candidate): string
    {
        return preg_match('/^[a-zA-Z0-9._-]{8,64}$/', $candidate) ? $candidate : bin2hex(random_bytes(8));
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function finish(array $result, float $started, float $providerLatency): array
    {
        $total = round((microtime(true) - $started) * 1000, 3);
        $result['timing'] = [
            'total_ms' => $total,
            'provider_ms' => round($providerLatency, 3),
            'internal_ms' => round(max(0.0, $total - $providerLatency), 3),
            'database_ms' => 0.0,
        ];
        $this->lastOutcome = $result;
        $logContext = [
            'request_id' => $result['request_id'] ?? '',
            'status' => $result['status'] ?? '',
            'cache' => $result['cache']['status'] ?? '',
            'item_count' => count((array)($result['items'] ?? [])),
            'provider_attempts' => $result['provider_attempts'] ?? [],
            'normalized_identity' => $result['normalized_identity'] ?? [],
            'normalized_statuses' => array_values(array_unique(array_filter(array_map(
                static fn(array $item): string => (string)($item['status'] ?? ''),
                (array)($result['items'] ?? []),
            )))),
            'timing' => $result['timing'],
        ];
        $level = in_array(
            (string)($result['status'] ?? ''),
            ['providers_unavailable', 'degraded_manual_fallback'],
            true,
        ) ? 'warning' : 'info';
        Log::write($level, 'AIR lookup ' . json_encode($logContext, JSON_UNESCAPED_SLASHES));

        return $result;
    }
}
