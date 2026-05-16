<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Core\Configure;
use Cake\Http\Client;

final class HafasRailDepartureProvider implements RailDepartureProviderInterface
{
    private Client $http;
    private string $baseUrl;
    private int $timeoutSeconds = 10;
    private HafasRailLocationProvider $locationProvider;

    public function __construct(?Client $http = null, ?RailDepartureNormalizer $normalizer = null)
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
        $this->locationProvider = new HafasRailLocationProvider($this->http, $this->normalizer);
        $this->baseUrl = rtrim((string)(
            Configure::read('Rail.hafasBaseUrl')
            ?: Configure::read('External.dbTransportRestBase')
            ?: 'https://v6.db.transport.rest'
        ), '/');
    }

    private RailDepartureNormalizer $normalizer;

    public function search(array $criteria): array
    {
        try {
            $fromId = $this->resolveLocationId((string)($criteria['from_station'] ?? ''));
            $toId = $this->resolveLocationId((string)($criteria['to_station'] ?? ''));
            $date = trim((string)($criteria['date'] ?? ''));
            $time = trim((string)($criteria['time'] ?? ''));
            if ($fromId === '' || $toId === '' || $date === '') {
                return [];
            }

            $departureAt = $date . 'T' . ($time !== '' ? $time : '00:00') . ':00';
            $payload = $this->requestJson('/journeys', [
                'from' => $fromId,
                'to' => $toId,
                'departure' => $departureAt,
                'results' => 6,
                'stopovers' => true,
                'remarks' => true,
                'routingMode' => 'HYBRID',
                'language' => 'da',
            ]);
            if (!is_array($payload)) {
                return [];
            }

            $journeys = is_array($payload['journeys'] ?? null) ? $payload['journeys'] : [];
            $out = [];
            foreach ($journeys as $journey) {
                if (!is_array($journey)) {
                    continue;
                }
                $candidate = $this->mapJourneyToCandidate($journey, $criteria);
                if ($candidate === null) {
                    continue;
                }
                $out[] = $this->normalizer->normalize($candidate);
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveLocationId(string $query): string
    {
        $resolved = $this->locationProvider->resolveLocation($query);

        return is_array($resolved) ? trim((string)($resolved['id'] ?? '')) : '';
    }

    /**
     * transport.rest appears to reject Cake\Http\Client intermittently with 503,
     * while plain cURL succeeds. Prefer direct cURL when available and keep
     * Cake's HTTP client as fallback so the provider degrades gracefully.
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
                    CURLOPT_CONNECTTIMEOUT => 5,
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
     * @param array<string,mixed> $journey
     * @param array<string,mixed> $criteria
     * @return array<string,mixed>|null
     */
    private function mapJourneyToCandidate(array $journey, array $criteria): ?array
    {
        $legs = array_values(array_filter((array)($journey['legs'] ?? []), fn($leg): bool => is_array($leg)));
        if ($legs === []) {
            return null;
        }

        $railLegs = array_values(array_filter($legs, fn(array $leg): bool => $this->isRailLeg($leg)));
        if ($railLegs === []) {
            return null;
        }

        $first = $railLegs[0];
        $last = $railLegs[array_key_last($railLegs)];
        $allCancelled = count(array_filter($railLegs, fn(array $leg): bool => !empty($leg['cancelled']))) === count($railLegs);
        $someCancelled = count(array_filter($railLegs, fn(array $leg): bool => !empty($leg['cancelled']))) > 0;
        $hasReplacement = count(array_filter($legs, fn(array $leg): bool => !$this->isRailLeg($leg) && empty($leg['walking']))) > 0;

        $plannedDep = $this->pickLegTime($first, ['plannedDeparture', 'departure', 'plannedWhen']);
        $estimatedDep = $this->pickLegTime($first, ['departure', 'when']);
        $actualDep = $this->pickLegTime($first, ['departure', 'when']);
        $plannedArr = $this->pickLegTime($last, ['plannedArrival', 'arrival', 'plannedWhen']);
        $estimatedArr = $this->pickLegTime($last, ['arrival', 'when']);
        $actualArr = $this->pickLegTime($last, ['arrival', 'when']);

        $departureDelay = $this->calculateDelayMinutes($plannedDep, $estimatedDep);
        $arrivalDelay = $this->calculateDelayMinutes($plannedArr, $estimatedArr);
        $remarks = [];
        foreach ((array)($journey['remarks'] ?? []) as $remark) {
            $text = trim((string)(is_array($remark) ? ($remark['text'] ?? ($remark['summary'] ?? '')) : $remark));
            if ($text !== '') {
                $remarks[] = $text;
            }
        }

        $status = 'planned';
        if ($allCancelled) {
            $status = 'cancelled';
        } elseif ($someCancelled) {
            $status = 'partially_cancelled';
        } elseif ($hasReplacement) {
            $status = 'replacement_transport';
        } elseif (($arrivalDelay !== null && $arrivalDelay > 0) || ($departureDelay !== null && $departureDelay > 0)) {
            $status = 'delayed';
        } elseif ($actualArr !== null) {
            $status = 'arrived';
        } elseif ($actualDep !== null) {
            $status = 'departed';
        }

        $line = (array)($first['line'] ?? []);
        $operator = (array)($line['operator'] ?? []);
        $operatorNames = [];
        $operatorCodes = [];
        foreach ($railLegs as $railLeg) {
            $railLine = (array)($railLeg['line'] ?? []);
            $railOperator = (array)($railLine['operator'] ?? []);
            $operatorName = trim((string)($railOperator['name'] ?? ''));
            $operatorCode = trim((string)($railOperator['id'] ?? ''));
            if ($operatorName !== '') {
                $operatorNames[strtolower($operatorName)] = $operatorName;
            }
            if ($operatorCode !== '') {
                $operatorCodes[strtolower($operatorCode)] = $operatorCode;
            }
        }

        return [
            'id' => trim((string)($journey['id'] ?? ($journey['tripId'] ?? ''))),
            'source' => 'hafas',
            'confidence' => 0.76,
            'train_number' => trim((string)($line['name'] ?? ($line['fahrtNr'] ?? ''))),
            'service_name' => trim((string)($line['productName'] ?? ($line['mode'] ?? 'Train'))),
            'line_name' => trim((string)($line['name'] ?? ($line['id'] ?? ''))),
            'product' => trim((string)($line['product'] ?? ($line['mode'] ?? ''))),
            'operator_code' => trim((string)($operator['id'] ?? '')),
            'operator_name' => trim((string)($operator['name'] ?? ($criteria['operator_hint'] ?? ''))),
            'infrastructure_manager' => null,
            'origin_station_name' => trim((string)($first['origin']['name'] ?? ($criteria['from_station'] ?? ''))),
            'origin_station_code' => trim((string)($first['origin']['id'] ?? '')),
            'destination_station_name' => trim((string)($last['destination']['name'] ?? ($criteria['to_station'] ?? ''))),
            'destination_station_code' => trim((string)($last['destination']['id'] ?? '')),
            'planned_departure_at' => $plannedDep,
            'estimated_departure_at' => $estimatedDep,
            'actual_departure_at' => $actualDep,
            'planned_arrival_at' => $plannedArr,
            'estimated_arrival_at' => $estimatedArr,
            'actual_arrival_at' => $actualArr,
            'departure_delay_minutes' => $departureDelay,
            'arrival_delay_minutes' => $arrivalDelay,
            'status' => $status,
            'platform_planned' => trim((string)($first['plannedPlatform'] ?? '')),
            'platform_actual' => trim((string)($first['platform'] ?? '')),
            'cancelled_section_from' => $someCancelled ? trim((string)($first['origin']['name'] ?? '')) : null,
            'cancelled_section_to' => $someCancelled ? trim((string)($last['destination']['name'] ?? '')) : null,
            'calling_points' => $this->mapCallingPoints((array)($first['stopovers'] ?? [])),
            'disruption_reason_public' => $remarks[0] ?? null,
            'disruption_reason_code' => null,
            'remarks' => $remarks,
            'raw' => [
                'journey_id' => (string)($journey['id'] ?? ''),
                'trip_id' => (string)($first['tripId'] ?? ''),
                'line_id' => (string)($line['id'] ?? ''),
                'from_id' => (string)($first['origin']['id'] ?? ''),
                'to_id' => (string)($last['destination']['id'] ?? ''),
                'leg_count' => count($legs),
                'rail_leg_count' => count($railLegs),
                'transfer_count' => max(0, count($railLegs) - 1),
                'operator_names' => array_values($operatorNames),
                'operator_codes' => array_values($operatorCodes),
                'has_connections' => count($railLegs) > 1,
                'has_replacement' => $hasReplacement,
            ],
        ];
    }

    /**
     * @param array<int,mixed> $stopovers
     * @return array<int,array<string,mixed>>
     */
    private function mapCallingPoints(array $stopovers): array
    {
        $out = [];
        foreach ($stopovers as $stopover) {
            if (!is_array($stopover)) {
                continue;
            }
            $out[] = [
                'station_name' => (string)($stopover['stop']['name'] ?? ''),
                'station_code' => (string)($stopover['stop']['id'] ?? ''),
                'planned_arrival_at' => $this->normalizeTimeValue($stopover['plannedArrival'] ?? null),
                'estimated_arrival_at' => $this->normalizeTimeValue($stopover['arrival'] ?? null),
                'actual_arrival_at' => $this->normalizeTimeValue($stopover['arrival'] ?? null),
                'planned_departure_at' => $this->normalizeTimeValue($stopover['plannedDeparture'] ?? null),
                'estimated_departure_at' => $this->normalizeTimeValue($stopover['departure'] ?? null),
                'actual_departure_at' => $this->normalizeTimeValue($stopover['departure'] ?? null),
                'cancelled' => (bool)($stopover['cancelled'] ?? false),
                'platform' => trim((string)($stopover['platform'] ?? ($stopover['plannedPlatform'] ?? ''))),
            ];
        }

        return $out;
    }

    private function isRailLeg(array $leg): bool
    {
        $line = (array)($leg['line'] ?? []);
        $product = strtolower(trim((string)($line['product'] ?? ($line['mode'] ?? ''))));
        if ($product === '') {
            $product = strtolower(trim((string)($leg['mode'] ?? '')));
        }

        return in_array($product, ['train', 'national', 'nationalexp', 'nationalexpress', 'regional', 'regionalexpress', 'suburban', 'express', 'ice', 'ic', 'ec', 're', 'rb', 's'], true)
            || preg_match('/^(ice|ic|ec|re|rb|s|ir|tgv|ter|nightjet)/i', (string)($line['name'] ?? '')) === 1;
    }

    /**
     * @param array<string,mixed> $leg
     * @param array<int,string> $keys
     */
    private function pickLegTime(array $leg, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $leg[$key] ?? null;
            $normalized = $this->normalizeTimeValue($value);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }

        return date(DATE_ATOM, $ts);
    }

    private function calculateDelayMinutes(?string $planned, ?string $observed): ?int
    {
        if ($planned === null || $observed === null) {
            return null;
        }
        $plannedTs = strtotime($planned);
        $observedTs = strtotime($observed);
        if ($plannedTs === false || $observedTs === false) {
            return null;
        }

        return (int)round(($observedTs - $plannedTs) / 60);
    }
}
