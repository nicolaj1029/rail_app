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

    public function __construct(?string $path = null)
    {
        $path = $path ?: (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'stations_coords.json');
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
                        $this->db[$this->norm($name)] = ['lat' => (float)$row['lat'], 'lon' => (float)$row['lon']];
                    }
                } else {
                    foreach ($data as $k => $v) {
                        if (is_array($v) && isset($v['lat'], $v['lon'])) {
                            $this->db[$this->norm((string)$k)] = ['lat' => (float)$v['lat'], 'lon' => (float)$v['lon']];
                        }
                    }
                }
            }
        }
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

    private function norm(string $s): string
    {
        $t = mb_strtolower(trim($s), 'UTF-8');
        $t = str_replace([' '], ' ', $t); // nbsp
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;
        return $t;
    }
}
