<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Tiny offline station geocoder for distance gating.
 * - Loads config/data/stations_coords.json
 * - Normalizes common suffixes/aliases (e.g., "C", "Central", "Hbf", "station")
 */
class StationGeocoder
{
    /** @var array<string,array{lat:float,lon:float}> */
    private array $db = [];

    /** @var array<int,array{name:string,lat:float,lon:float}> */
    private array $rows = [];

    /** @var array<string,array{lat:float,lon:float}>|null */
    private static ?array $cacheDb = null;
    /** @var array<int,array{name:string,lat:float,lon:float}>|null */
    private static ?array $cacheRows = null;
    private static ?int $cacheMtime = null;

    public function __construct(?string $path = null)
    {
        $path = $path ?: (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'stations_coords.json');
        $mtime = is_file($path) ? (int)@filemtime($path) : null;
        if ($mtime !== null && self::$cacheDb !== null && self::$cacheRows !== null && self::$cacheMtime === $mtime) {
            $this->db = self::$cacheDb;
            $this->rows = self::$cacheRows;
            return;
        }

        $db = [];
        $rows = [];
        if (is_file($path)) {
            $json = (string)file_get_contents($path);
            $data = json_decode($json, true);
            if (is_array($data)) {
                // Support both shapes:
                // 1) Map: { "København H": {"lat":..,"lon":..}, ... }
                // 2) List: [ {"name":"København H","lat":..,"lon":..}, ... ] (current stations_coords.json)
                $isList = function_exists('array_is_list') ? array_is_list($data) : (array_keys($data) === range(0, count($data) - 1));
                if ($isList) {
                    foreach ($data as $row) {
                        if (!is_array($row)) { continue; }
                        $name = (string)($row['name'] ?? ($row['station'] ?? ''));
                        if ($name === '' || !isset($row['lat'], $row['lon'])) { continue; }
                        $lat = (float)$row['lat'];
                        $lon = (float)$row['lon'];
                        $db[$this->norm($name)] = ['lat' => $lat, 'lon' => $lon];
                        $rows[] = ['name' => $name, 'lat' => $lat, 'lon' => $lon];
                    }
                } else {
                    foreach ($data as $k => $v) {
                        if (is_array($v) && isset($v['lat'], $v['lon'])) {
                            $name = (string)$k;
                            $lat = (float)$v['lat'];
                            $lon = (float)$v['lon'];
                            $db[$this->norm($name)] = ['lat' => $lat, 'lon' => $lon];
                            $rows[] = ['name' => $name, 'lat' => $lat, 'lon' => $lon];
                        }
                    }
                }
            }
        }

        $this->db = $db;
        $this->rows = $rows;
        self::$cacheDb = $db;
        self::$cacheRows = $rows;
        self::$cacheMtime = $mtime;
    }

    /** @return array{lat:float,lon:float}|null */
    public function lookup(string $name): ?array
    {
        $n = $this->norm($name);
        if (isset($this->db[$n])) { return $this->db[$n]; }
        // Try stripping common suffix tokens
        $base = preg_replace('/\b(hbf|central(?:en)?|centralstation|station|st\.?|bahnhof|gare|c)\b/u', '', $n) ?? $n;
        $base = trim(preg_replace('/\s{2,}/u', ' ', $base) ?? $base);
        if (isset($this->db[$base])) { return $this->db[$base]; }
        return null;
    }

    /** @return array{name:string,lat:float,lon:float,distance_m:float}|null */
    public function nearest(float $lat, float $lon, float $maxDistanceMeters = 1500.0): ?array
    {
        $best = null;
        $bestDistance = INF;

        foreach ($this->rows as $row) {
            $distance = $this->distanceMeters($lat, $lon, $row['lat'], $row['lon']);
            if ($distance > $maxDistanceMeters || $distance >= $bestDistance) {
                continue;
            }
            $bestDistance = $distance;
            $best = $row;
        }

        if ($best === null) {
            return null;
        }

        return [
            'name' => $best['name'],
            'lat' => $best['lat'],
            'lon' => $best['lon'],
            'distance_m' => round($bestDistance, 1),
        ];
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    private function norm(string $s): string
    {
        $t = mb_strtolower(trim($s), 'UTF-8');
        $t = str_replace([' '], ' ', $t); // nbsp
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;
        return $t;
    }
}
