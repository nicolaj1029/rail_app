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
    /** @var array<string,int|array{0:float,1:float}> */
    private array $db = [];

    private string $path = '';

    /** @var array<string,int|array{0:float,1:float}>|null */
    private static ?array $cacheDb = null;

    private static ?int $cacheMtime = null;
    private static ?string $cachePath = null;

    public function __construct(?string $path = null)
    {
        $path = $path ?: (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'stations_coords.json');
        $this->path = $path;
        $mtime = is_file($path) ? (int)@filemtime($path) : null;
        if (
            $mtime !== null &&
            self::$cacheDb !== null &&
            self::$cacheMtime === $mtime &&
            self::$cachePath === $path
        ) {
            $this->db = self::$cacheDb;

            return;
        }

        $this->db = [];
        self::$cacheDb = [];
        self::$cacheMtime = $mtime;
        self::$cachePath = $path;
    }

    /** @return array{lat:float,lon:float}|null */
    public function lookup(string $name): ?array
    {
        $n = $this->norm($name);
        if (isset($this->db[$n])) {
            $hit = $this->db[$n];
            if (is_array($hit)) {
                return ['lat' => $hit[0], 'lon' => $hit[1]];
            }
        }

        // Try stripping common suffix tokens
        $base = preg_replace('/\b(hbf|central(?:en)?|centralstation|station|st\.?|bahnhof|gare|c)\b/u', '', $n) ?? $n;
        $base = trim(preg_replace('/\s{2,}/u', ' ', $base) ?? $base);
        if (isset($this->db[$base])) {
            $hit = $this->db[$base];
            if (is_array($hit)) {
                return ['lat' => $hit[0], 'lon' => $hit[1]];
            }
        }

        return $this->lookupFromFile($n, $base);
    }

    /** @return array{name:string,lat:float,lon:float,distance_m:float}|null */
    public function nearest(float $lat, float $lon, float $maxDistanceMeters = 1500.0): ?array
    {
        return $this->nearestFromFile($lat, $lon, $maxDistanceMeters);
    }

    /**
     * @return array{0:array<string,int|array{0:float,1:float}>,1:array<int,array{0:string,1:float,2:float}>}
     */
    private function loadFromDecodedJson(string $path, bool $withRows = true): array
    {
        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return [[], []];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [[], []];
        }

        $db = [];
        $rows = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['lat'], $value['lon'])) {
                $name = is_string($key) ? $key : (string)($value['name'] ?? ($value['station'] ?? ''));
                $this->addRow($db, $rows, $name, $value['lat'], $value['lon'], $withRows);
            }
        }

        return [$db, $rows];
    }

    /**
     * The shipped stations file is a large top-level JSON list. Streaming the
     * objects keeps peak memory well below the request limit.
     *
     * @return array{0:array<string,int|array{0:float,1:float}>,1:array<int,array{0:string,1:float,2:float}>}
     */
    private function loadListFromStream(string $path, bool $withRows = true): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [[], []];
        }

        $db = [];
        $rows = [];
        $buffer = '';
        $depth = 0;
        $capturing = false;
        $inString = false;
        $escape = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $length = strlen($line);
                for ($i = 0; $i < $length; $i++) {
                    $char = $line[$i];

                    if ($capturing) {
                        $buffer .= $char;
                    }

                    if ($inString) {
                        if ($escape) {
                            $escape = false;
                            continue;
                        }
                        if ($char === '\\') {
                            $escape = true;
                            continue;
                        }
                        if ($char === '"') {
                            $inString = false;
                        }
                        continue;
                    }

                    if ($char === '"') {
                        $inString = true;
                        continue;
                    }

                    if ($char === '{') {
                        if (!$capturing) {
                            $capturing = true;
                            $buffer = '{';
                            $depth = 1;
                            continue;
                        }

                        $depth++;
                        continue;
                    }

                    if ($char !== '}' || !$capturing) {
                        continue;
                    }

                    $depth--;
                    if ($depth !== 0) {
                        continue;
                    }

                    $row = json_decode($buffer, true);
                    if (is_array($row)) {
                        $name = (string)($row['name'] ?? ($row['station'] ?? ''));
                        $this->addRow($db, $rows, $name, $row['lat'] ?? null, $row['lon'] ?? null, $withRows);
                    }

                    $capturing = false;
                    $buffer = '';
                }
            }
        } finally {
            fclose($handle);
        }

        return [$db, $rows];
    }

    private function firstSignificantChar(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            while (($char = fgetc($handle)) !== false) {
                if (!ctype_space($char)) {
                    return $char;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * @param array<string,int|array{0:float,1:float}> $db
     * @param array<int,array{0:string,1:float,2:float}> $rows
     */
    private function addRow(array &$db, array &$rows, string $name, mixed $lat, mixed $lon, bool $withRows = true): void
    {
        $name = trim($name);
        if ($name === '' || !is_numeric($lat) || !is_numeric($lon)) {
            return;
        }

        $lat = (float)$lat;
        $lon = (float)$lon;
        $norm = $this->norm($name);
        if (!isset($db[$norm])) {
            $db[$norm] = [$lat, $lon];
        }
        if ($withRows) {
            $rows[] = [$name, $lat, $lon];
        }
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
        $t = str_replace(["\u{00A0}", 'Â '], ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return $t;
    }

    /** @return array{lat:float,lon:float}|null */
    private function lookupFromFile(string $normalizedName, string $normalizedBase): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $targets = array_values(array_unique(array_filter([$normalizedName, $normalizedBase])));
        $firstChar = $this->firstSignificantChar($this->path);

        if ($firstChar === '[') {
            $handle = @fopen($this->path, 'rb');
            if ($handle === false) {
                return null;
            }

            $buffer = '';
            $depth = 0;
            $capturing = false;
            $inString = false;
            $escape = false;
            try {
                while (($line = fgets($handle)) !== false) {
                    $length = strlen($line);
                    for ($i = 0; $i < $length; $i++) {
                        $char = $line[$i];

                        if ($capturing) {
                            $buffer .= $char;
                        }

                        if ($inString) {
                            if ($escape) {
                                $escape = false;
                                continue;
                            }
                            if ($char === '\\') {
                                $escape = true;
                                continue;
                            }
                            if ($char === '"') {
                                $inString = false;
                            }
                            continue;
                        }

                        if ($char === '"') {
                            $inString = true;
                            continue;
                        }

                        if ($char === '{') {
                            if (!$capturing) {
                                $capturing = true;
                                $buffer = '{';
                                $depth = 1;
                                continue;
                            }

                            $depth++;
                            continue;
                        }

                        if ($char !== '}' || !$capturing) {
                            continue;
                        }

                        $depth--;
                        if ($depth !== 0) {
                            continue;
                        }

                        $row = json_decode($buffer, true);
                        if (is_array($row)) {
                            $name = trim((string)($row['name'] ?? ($row['station'] ?? '')));
                            $rowLat = $row['lat'] ?? null;
                            $rowLon = $row['lon'] ?? null;
                            $norm = $this->norm($name);
                            if ($name !== '' && in_array($norm, $targets, true) && is_numeric($rowLat) && is_numeric($rowLon)) {
                                $hit = ['lat' => (float)$rowLat, 'lon' => (float)$rowLon];
                                $this->db[$normalizedName] = [$hit['lat'], $hit['lon']];
                                if ($normalizedBase !== '') {
                                    $this->db[$normalizedBase] = [$hit['lat'], $hit['lon']];
                                }
                                self::$cacheDb = $this->db;

                                return $hit;
                            }
                        }

                        $capturing = false;
                        $buffer = '';
                    }
                }
            } finally {
                fclose($handle);
            }

            return null;
        }

        $json = @file_get_contents($this->path);
        if (!is_string($json) || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (!is_array($value) || !isset($value['lat'], $value['lon'])) {
                continue;
            }
            $name = is_string($key) ? $key : (string)($value['name'] ?? ($value['station'] ?? ''));
            $norm = $this->norm($name);
            if (!in_array($norm, $targets, true) || !is_numeric($value['lat']) || !is_numeric($value['lon'])) {
                continue;
            }
            $hit = ['lat' => (float)$value['lat'], 'lon' => (float)$value['lon']];
            $this->db[$normalizedName] = [$hit['lat'], $hit['lon']];
            if ($normalizedBase !== '') {
                $this->db[$normalizedBase] = [$hit['lat'], $hit['lon']];
            }
            self::$cacheDb = $this->db;

            return $hit;
        }

        return null;
    }

    /** @return array{name:string,lat:float,lon:float,distance_m:float}|null */
    private function nearestFromFile(float $lat, float $lon, float $maxDistanceMeters): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $bestName = null;
        $bestLat = null;
        $bestLon = null;
        $bestDistance = INF;
        $firstChar = $this->firstSignificantChar($this->path);
        if ($firstChar === '[') {
            $handle = @fopen($this->path, 'rb');
            if ($handle === false) {
                return null;
            }

            $buffer = '';
            $depth = 0;
            $capturing = false;
            $inString = false;
            $escape = false;
            try {
                while (($line = fgets($handle)) !== false) {
                    $length = strlen($line);
                    for ($i = 0; $i < $length; $i++) {
                        $char = $line[$i];

                        if ($capturing) {
                            $buffer .= $char;
                        }

                        if ($inString) {
                            if ($escape) {
                                $escape = false;
                                continue;
                            }
                            if ($char === '\\') {
                                $escape = true;
                                continue;
                            }
                            if ($char === '"') {
                                $inString = false;
                            }
                            continue;
                        }

                        if ($char === '"') {
                            $inString = true;
                            continue;
                        }

                        if ($char === '{') {
                            if (!$capturing) {
                                $capturing = true;
                                $buffer = '{';
                                $depth = 1;
                                continue;
                            }

                            $depth++;
                            continue;
                        }

                        if ($char !== '}' || !$capturing) {
                            continue;
                        }

                        $depth--;
                        if ($depth !== 0) {
                            continue;
                        }

                        $row = json_decode($buffer, true);
                        if (is_array($row)) {
                            $name = trim((string)($row['name'] ?? ($row['station'] ?? '')));
                            $rowLat = $row['lat'] ?? null;
                            $rowLon = $row['lon'] ?? null;
                            if ($name !== '' && is_numeric($rowLat) && is_numeric($rowLon)) {
                                $rowLat = (float)$rowLat;
                                $rowLon = (float)$rowLon;
                                $distance = $this->distanceMeters($lat, $lon, $rowLat, $rowLon);
                                if ($distance <= $maxDistanceMeters && $distance < $bestDistance) {
                                    $bestDistance = $distance;
                                    $bestName = $name;
                                    $bestLat = $rowLat;
                                    $bestLon = $rowLon;
                                }
                            }
                        }

                        $capturing = false;
                        $buffer = '';
                    }
                }
            } finally {
                fclose($handle);
            }
        } else {
            $json = @file_get_contents($this->path);
            if (!is_string($json) || $json === '') {
                return null;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                return null;
            }
            foreach ($data as $key => $value) {
                if (!is_array($value) || !isset($value['lat'], $value['lon'])) {
                    continue;
                }
                $name = is_string($key) ? $key : (string)($value['name'] ?? ($value['station'] ?? ''));
                $name = trim($name);
                if ($name === '' || !is_numeric($value['lat']) || !is_numeric($value['lon'])) {
                    continue;
                }
                $rowLat = (float)$value['lat'];
                $rowLon = (float)$value['lon'];
                $distance = $this->distanceMeters($lat, $lon, $rowLat, $rowLon);
                if ($distance > $maxDistanceMeters || $distance >= $bestDistance) {
                    continue;
                }
                $bestDistance = $distance;
                $bestName = $name;
                $bestLat = $rowLat;
                $bestLon = $rowLon;
            }
        }

        if ($bestName === null || $bestLat === null || $bestLon === null) {
            return null;
        }

        return [
            'name' => $bestName,
            'lat' => $bestLat,
            'lon' => $bestLon,
            'distance_m' => round($bestDistance, 1),
        ];
    }
}
