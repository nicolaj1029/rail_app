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
                foreach ($data as $k => $v) {
                    if (isset($v['lat'], $v['lon'])) {
                        $this->db[$this->norm($k)] = ['lat' => (float)$v['lat'], 'lon' => (float)$v['lon']];
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
