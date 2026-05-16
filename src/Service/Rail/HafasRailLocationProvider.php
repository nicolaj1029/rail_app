<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;

final class HafasRailLocationProvider
{
    private Client $http;
    private string $baseUrl;
    private int $timeoutSeconds = 8;
    private RailDepartureNormalizer $normalizer;

    /** @var array<int,array<string,mixed>> */
    private array $majorStationCatalog;

    public function __construct(?Client $http = null, ?RailDepartureNormalizer $normalizer = null, ?array $majorStationCatalog = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => $this->timeoutSeconds,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 rail_app/1.0',
                'Accept' => 'application/json',
                'Accept-Language' => 'da,en;q=0.8',
            ],
        ]);
        $this->normalizer = $normalizer ?? new RailDepartureNormalizer();
        $this->majorStationCatalog = $majorStationCatalog ?? (array)include CONFIG . 'rail' . DS . 'major_station_catalog.php';
        $this->baseUrl = rtrim((string)(
            Configure::read('Rail.hafasBaseUrl')
            ?: Configure::read('External.dbTransportRestBase')
            ?: 'https://v6.db.transport.rest'
        ), '/');
    }

    public function isEnabled(): bool
    {
        return Configure::read('Rail.hafas.enabled', true) !== false
            && (bool)Configure::read('External.useLiveApis', false);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function searchLocations(string $query, ?string $country = null, int $limit = 8): array
    {
        $query = $this->normalizer->normalizeStationQuery($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(12, $limit));
        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === '') {
            $country = null;
        }

        if (!$this->isEnabled()) {
            return [];
        }

        $cacheKey = 'rail_hafas_locations_v2_' . md5(json_encode([$query, $country, $limit], JSON_UNESCAPED_UNICODE));
        $cached = Cache::read($cacheKey, 'default');
        if (is_array($cached)) {
            return $cached;
        }

        $results = [];
        $seen = [];
        foreach ($this->preferredQueries($query) as $candidateQuery) {
            $payload = $this->requestJson('/locations', [
                'query' => $candidateQuery,
                'results' => max(12, $limit * 2),
                'addresses' => false,
                'poi' => false,
                'language' => 'da',
            ]);
            if (!is_array($payload)) {
                continue;
            }

            $items = array_is_list($payload) ? $payload : (array)($payload['items'] ?? []);
            foreach ($items as $item) {
                $normalized = is_array($item) ? $this->normalizeLocation($item, $candidateQuery, $country) : null;
                if ($normalized === null) {
                    continue;
                }
                $key = strtolower((string)$normalized['id']);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $results[] = $normalized;
            }

            if (count($results) >= $limit * 2) {
                break;
            }
        }

        if ($results !== []) {
            Cache::write($cacheKey, $results, 'default');
        }

        return $results;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function resolveLocation(string $query, ?string $country = null): ?array
    {
        $query = $this->normalizer->normalizeStationQuery($query);
        if ($query === '') {
            return null;
        }

        $catalogMatch = $this->findCatalogEntry($query);
        if (is_array($catalogMatch) && trim((string)($catalogMatch['transport_rest_id'] ?? '')) !== '') {
            return [
                'id' => trim((string)$catalogMatch['transport_rest_id']),
                'name' => (string)($catalogMatch['canonical_name'] ?? $query),
                'country' => isset($catalogMatch['country']) ? strtoupper((string)$catalogMatch['country']) : null,
                'type' => 'station',
                'source' => 'major_station_catalog',
            ];
        }

        return $this->searchLocations($query, $country, 1)[0] ?? null;
    }

    public function hasLiveDepartures(string $stationId): ?bool
    {
        $stationId = trim($stationId);
        if ($stationId === '') {
            return null;
        }

        $cacheKey = 'rail_hafas_departure_probe_v1_' . md5($stationId);
        $cached = Cache::read($cacheKey, 'default');
        if (is_bool($cached)) {
            return $cached;
        }

        $payload = $this->requestJson('/stops/' . rawurlencode($stationId) . '/departures', [
            'when' => date('c'),
            'duration' => 720,
            'results' => 3,
            'remarks' => false,
            'language' => 'da',
        ]);
        if (!is_array($payload)) {
            return null;
        }

        $departures = (array)($payload['departures'] ?? []);
        foreach ($departures as $departure) {
            if (!is_array($departure)) {
                continue;
            }
            if ($this->hasRailProducts((array)(($departure['line'] ?? [])['products'] ?? []))) {
                Cache::write($cacheKey, true, 'default');
                return true;
            }

            $product = strtolower(trim((string)(($departure['line'] ?? [])['product'] ?? (($departure['line'] ?? [])['mode'] ?? ''))));
            if (in_array($product, ['train', 'national', 'nationalexpress', 'regional', 'regionalexpress', 'suburban', 'express'], true)) {
                Cache::write($cacheKey, true, 'default');
                return true;
            }
        }

        Cache::write($cacheKey, false, 'default');

        return false;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>|null
     */
    private function normalizeLocation(array $item, string $candidateQuery, ?string $country = null): ?array
    {
        $type = strtolower(trim((string)($item['type'] ?? '')));
        if (!in_array($type, ['station', 'stop'], true)) {
            return null;
        }

        $id = trim((string)($item['id'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        if ($id === '' || $name === '') {
            return null;
        }

        $location = is_array($item['location'] ?? null) ? (array)$item['location'] : [];
        $products = $this->normalizeProducts((array)($item['products'] ?? []));
        $countryCode = strtoupper(trim((string)(
            $item['countryCode']
            ?? $item['country']
            ?? $location['countryCode']
            ?? $location['country']
            ?? ''
        )));
        if ($countryCode === '') {
            $countryCode = null;
        }

        $rail = $this->hasRailProducts($products);
        $busOnly = !empty($products['bus']) && !$rail && empty($products['tram']) && empty($products['subway']);
        $tramOnly = !empty($products['tram']) && !$rail && empty($products['bus']) && empty($products['subway']);
        $metroOnly = !empty($products['subway']) && !$rail && empty($products['bus']) && empty($products['tram']);
        $regionalOrInternational = $this->isRegionalOrInternational($products);
        $suburbanOnly = !empty($products['suburban']) && !$regionalOrInternational && empty($products['express']) && empty($products['national']);
        $minorHalt = $type === 'stop' && ($suburbanOnly || !$regionalOrInternational);

        $canonicalName = (string)($this->normalizer->canonicalizeStationName($name) ?? $name);

        return [
            'id' => $id,
            'osm_id' => $id,
            'name' => $canonicalName,
            'raw_name' => $name,
            'type' => $type === 'station' || $regionalOrInternational ? 'station' : 'halt',
            'hafas_type' => $type,
            'country' => $countryCode,
            'lat' => $this->normalizeCoordinate($location['latitude'] ?? $item['latitude'] ?? null),
            'lon' => $this->normalizeCoordinate($location['longitude'] ?? $item['longitude'] ?? null),
            'source' => 'hafas',
            'query_match' => $candidateQuery,
            'rail' => $rail,
            'bus_only' => $busOnly,
            'tram_only' => $tramOnly,
            'metro_only' => $metroOnly,
            'regional_or_international' => $regionalOrInternational,
            'suburban_only' => $suburbanOnly,
            'minor_halt' => $minorHalt,
            'has_central_marker' => $this->normalizer->hasCentralStationMarker($canonicalName),
            'country_match' => $country !== null && $countryCode === $country,
            'exact_alias_match' => $this->normalizer->stationsMatch($canonicalName, null, $candidateQuery),
            'has_live_departures' => $rail ? $this->hasLiveDepartures($id) : false,
            'products' => $products,
        ];
    }

    /**
     * transport.rest can reject Cake\Http\Client intermittently. Prefer cURL and
     * keep the Cake client as fallback.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>|array<int,mixed>|null
     */
    private function requestJson(string $path, array $query): array|null
    {
        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $ch = curl_init($url . ($queryString !== '' ? ('?' . $queryString) : ''));
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => $this->timeoutSeconds,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Accept-Language: da,en;q=0.8',
                        'User-Agent: Mozilla/5.0 rail_app/1.0',
                    ],
                ]);
                $body = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) {
                    $decoded = json_decode($body, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }
        }

        try {
            $response = $this->http->get($url, $query);
            if (!$response->isOk()) {
                return null;
            }
            $payload = $response->getJson();
            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<int,string>
     */
    private function preferredQueries(string $query): array
    {
        $catalogEntry = $this->findCatalogEntry($query);
        $preferred = [];
        if (is_array($catalogEntry)) {
            $preferred[] = trim((string)($catalogEntry['transport_rest_query'] ?? ''));
            $preferred[] = trim((string)($catalogEntry['canonical_name'] ?? ''));
        }

        return $this->dedupeQueries(array_merge($preferred, $this->normalizer->expandStationQueries($query)));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findCatalogEntry(string $query): ?array
    {
        $needle = $this->normalizer->stationLookupKey($query);
        if ($needle === '') {
            return null;
        }

        foreach ($this->majorStationCatalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $canonical = trim((string)($entry['canonical_name'] ?? ''));
            if ($canonical === '') {
                continue;
            }
            foreach ($this->normalizer->aliasesForStation($canonical) as $variant) {
                if ($this->normalizer->stationLookupKey($variant) === $needle) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $products
     */
    private function hasRailProducts(array $products): bool
    {
        foreach (['national', 'nationalexpress', 'nationalexp', 'regional', 'regionalexpress', 'express', 'suburban', 'train'] as $key) {
            if (!empty($products[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $products
     */
    private function isRegionalOrInternational(array $products): bool
    {
        foreach (['national', 'nationalexpress', 'nationalexp', 'regional', 'regionalexpress', 'express'] as $key) {
            if (!empty($products[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $products
     * @return array<string,bool>
     */
    private function normalizeProducts(array $products): array
    {
        $out = [];
        foreach ($products as $key => $value) {
            $normalizedKey = strtolower(trim((string)$key));
            $normalizedKey = str_replace([' ', '-', '_'], '', $normalizedKey);
            if ($normalizedKey === 'subway' || $normalizedKey === 'metro') {
                $normalizedKey = 'subway';
            }
            $out[$normalizedKey] = (bool)$value;
        }

        return $out;
    }

    private function normalizeCoordinate(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * @param array<int,string> $queries
     * @return array<int,string>
     */
    private function dedupeQueries(array $queries): array
    {
        $out = [];
        $seen = [];
        foreach ($queries as $query) {
            $query = trim((string)$query);
            if ($query === '') {
                continue;
            }
            $key = $this->normalizer->stationLookupKey($query);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $query;
        }

        return $out;
    }
}
