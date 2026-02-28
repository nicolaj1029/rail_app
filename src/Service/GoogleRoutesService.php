<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

/**
 * Thin wrapper around Google Routes API (computeRoutes).
 *
 * We keep this intentionally small:
 * - Only transit routes (TRIN 5/6 helper).
 * - Returns a normalized payload safe to store in session.
 */
class GoogleRoutesService
{
    private string $apiKey;
    private Client $http;
    private string $endpoint;

    public function __construct(?string $apiKey = null, ?Client $http = null, ?string $endpoint = null)
    {
        $this->apiKey = (string)($apiKey
            ?? (getenv('GOOGLE_MAPS_SERVER_KEY') ?: '')
            ?: (getenv('GOOGLE_MAPS_API_KEY') ?: ''));
        $this->http = $http ?? new Client(['timeout' => 8]);
        $this->endpoint = rtrim($endpoint ?? 'https://routes.googleapis.com/directions/v2:computeRoutes', '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array<string,mixed> Normalized response:
     *   - ok: bool
     *   - error: string|null
     *   - routes: list<array{duration_s:int,distance_m:int,transfers:int,summary:string,segments:list<array{vehicle:string,line:string,from:string,to:string,dep_time:string,arr_time:string}>,transit_lines:list<string>}>
     *   - raw: optional (not included to keep session small)
     */
    public function computeTransitRoutes(string $origin, string $destination, array $opts = []): array
    {
        $origin = trim($origin);
        $destination = trim($destination);
        if ($origin === '' || $destination === '') {
            return ['ok' => false, 'error' => 'Missing origin/destination.', 'routes' => []];
        }
        if ($this->apiKey === '') {
            return ['ok' => false, 'error' => 'Google Routes API key is not configured.', 'routes' => []];
        }

        $body = [
            'origin' => ['address' => $origin],
            'destination' => ['address' => $destination],
            'travelMode' => 'TRANSIT',
            'computeAlternativeRoutes' => true,
            'languageCode' => (string)($opts['languageCode'] ?? 'da-DK'),
            'units' => (string)($opts['units'] ?? 'METRIC'),
            'transitPreferences' => [
                // Prefer rail-ish transit, but allow fallback modes when needed.
                'allowedTravelModes' => ['TRAIN', 'RAIL', 'LIGHT_RAIL', 'SUBWAY', 'BUS'],
                'routingPreference' => (string)($opts['routingPreference'] ?? 'FEWER_TRANSFERS'),
            ],
        ];

        $departureTime = (string)($opts['departureTime'] ?? '');
        if ($departureTime !== '') {
            // RFC3339 UTC (Zulu) recommended by Google docs.
            $body['departureTime'] = $departureTime;
        }

        // Keep field mask small but useful for UI summarization.
        $fieldMask = implode(',', [
            'routes.duration',
            'routes.distanceMeters',
            'routes.localizedValues',
            'routes.legs.steps.travelMode',
            'routes.legs.steps.transitDetails',
            'routes.legs.steps.transitDetails.stopDetails',
            'routes.legs.steps.transitDetails.transitLine',
        ]);

        try {
            $res = $this->http->post(
                $this->endpoint,
                json_encode($body, JSON_UNESCAPED_SLASHES),
                [
                    'type' => 'json',
                    'headers' => [
                        'X-Goog-Api-Key' => $this->apiKey,
                        'X-Goog-FieldMask' => $fieldMask,
                        'Content-Type' => 'application/json',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Network/HTTP error: ' . $e->getMessage(), 'routes' => []];
        }

        if (!$res->isOk()) {
            $msg = 'Google Routes error HTTP ' . (string)$res->getStatusCode();
            try {
                $j = $res->getJson();
                if (is_array($j)) {
                    $m = $j['error']['message'] ?? ($j['message'] ?? null);
                    if (is_string($m) && $m !== '') { $msg .= ' - ' . $m; }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            return ['ok' => false, 'error' => $msg, 'routes' => []];
        }

        $json = $res->getJson();
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'Invalid JSON from Google.', 'routes' => []];
        }

        $routes = [];
        foreach ((array)($json['routes'] ?? []) as $r) {
            if (!is_array($r)) { continue; }
            $durS = $this->parseDurationSeconds((string)($r['duration'] ?? ''));
            $distM = (int)($r['distanceMeters'] ?? 0);

            $lines = [];
            $segments = [];
            $steps = (array)($r['legs'][0]['steps'] ?? []);
            foreach ($steps as $st) {
                if (!is_array($st)) { continue; }
                $td = $st['transitDetails'] ?? null;
                if (!is_array($td)) { continue; }
                $line = $td['transitLine']['nameShort'] ?? $td['transitLine']['name'] ?? '';
                $veh = $td['transitLine']['vehicle']['type'] ?? '';
                $txt = trim((string)$line);
                if ($txt === '') { $txt = trim((string)$veh); }
                if ($txt !== '') { $lines[$txt] = true; }

                $segments[] = [
                    'vehicle' => (string)$veh,
                    'line' => (string)$line,
                    'from' => (string)($td['stopDetails']['departureStop']['name'] ?? ''),
                    'to' => (string)($td['stopDetails']['arrivalStop']['name'] ?? ''),
                    'dep_time' => (string)($td['stopDetails']['departureTime'] ?? ''),
                    'arr_time' => (string)($td['stopDetails']['arrivalTime'] ?? ''),
                ];
            }

            $transfers = count($segments) > 0 ? max(0, count($segments) - 1) : 0;
            $summary = $this->buildSummary($r, $durS, $distM, $transfers, array_keys($lines));
            $routes[] = [
                'duration_s' => $durS,
                'distance_m' => $distM,
                'transfers' => $transfers,
                'summary' => $summary,
                'segments' => array_slice($segments, 0, 10),
                'transit_lines' => array_slice(array_keys($lines), 0, 6),
            ];
            if (count($routes) >= 4) { break; } // primary + up to 3 alternatives
        }

        return [
            'ok' => true,
            'error' => null,
            'routes' => $routes,
        ];
    }

    private function parseDurationSeconds(string $dur): int
    {
        // Google returns duration like "1234s"
        if (preg_match('/^([0-9]+)s$/', trim($dur), $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    /**
     * @param array<string,mixed> $route
     * @param list<string> $lines
     */
    private function buildSummary(array $route, int $durS, int $distM, int $transfers, array $lines): string
    {
        $mins = $durS > 0 ? (int)round($durS / 60) : 0;
        $km = $distM > 0 ? round($distM / 1000, 1) : 0.0;
        $parts = [];
        if ($mins > 0) { $parts[] = $mins . ' min'; }
        if ($km > 0) { $parts[] = $km . ' km'; }
        if ($transfers > 0) { $parts[] = $transfers . ' skift'; }
        if ($lines) { $parts[] = implode(', ', array_slice($lines, 0, 3)); }

        // Localized times (when available) can be shown later; keep summary short for now.
        return implode(' - ', $parts);
    }
}
