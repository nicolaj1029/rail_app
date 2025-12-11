<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Lightweight resolver for service exemptions based on operator catalog and national overrides.
 * Returns blocked/exemptions and any national override (minDelay, bands).
 */
class ExemptionResolver
{
    private array $operators;
    private array $matrix;
    private array $national;
    private array $stationCoords = [];

    public function __construct()
    {
        $this->operators = $this->loadJson(CONFIG . '/data/operators_catalog.json');
        $this->matrix = $this->loadJson(CONFIG . '/data/exemption_matrix.json');
        $this->national = $this->loadJson(CONFIG . '/data/national_overrides.json');
        $this->stationCoords = $this->loadStationsMap(CONFIG . '/data/stations_coords.json');
    }

    /**
     * @param string $country ISO code
     * @param string $product Operator product name
     * @param array $context Optional context (distanceKm, isInternational, endsOutsideEU, scope)
     * @return array{country:string,product:string,blocked:bool,exemptions:array,scope:string|null,national?:array}
     */
    public function resolve(string $country, string $product, array $context = []): array
    {
        $country = strtoupper(trim($country));
        $productKey = trim($product);
        // Allow caller to set scope; otherwise infer from country/product
        // Fill distance if possible
        $distanceKm = null;
        if (isset($context['distanceKm']) && is_numeric($context['distanceKm'])) {
            $distanceKm = (float)$context['distanceKm'];
        } else {
            $distanceKm = $this->computeDistanceKm($country, (string)($context['dep_station'] ?? ''), (string)($context['arr_station'] ?? ''));
            if ($distanceKm !== null) {
                $context['distanceKm'] = $distanceKm;
            }
        }
        $scope = $context['scope'] ?? $this->classifyScope($country, $productKey, $context);
        $blocked = false;
        $exemptions = [];

        // Try explicit product match
        if ($country && $productKey && isset($this->operators[$country]) && is_array($this->operators[$country])) {
            $ops = $this->operators[$country];
            if (isset($ops[$productKey]) && is_array($ops[$productKey])) {
                $cfg = $ops[$productKey];
                $blocked = !empty($cfg['blocked']);
                $exemptions = (array)($cfg['exemptions'] ?? []);
                $scope = (string)($cfg['scope'] ?? ($cfg['conditional'] ?? null));
            }
        }

        // Merge with exemption matrix (country + scope)
        if ($country && $scope && isset($this->matrix[$country]) && is_array($this->matrix[$country])) {
            foreach ((array)$this->matrix[$country] as $rule) {
                $rScope = (string)($rule['scope'] ?? '');
                if ($rScope !== $scope) { continue; }
                if (!empty($rule['blocked'])) { $blocked = true; }
                if (!empty($rule['exemptions']) && is_array($rule['exemptions'])) {
                    $exemptions = array_merge($exemptions, $rule['exemptions']);
                }
            }
        }

        // Country-specific distance rule: SE regional under 150 km → blocked
        if ($country === 'SE' && $distanceKm !== null && $distanceKm <= 150) {
            if ($scope === 'regional' || $scope === null) {
                $blocked = true;
                $exemptions[] = 'distance_under_150km_regional';
            }
        }

        $nationalOverride = $this->national[$country] ?? null;

        return [
            'country' => $country,
            'product' => $productKey,
            'blocked' => (bool)$blocked,
            'exemptions' => array_values(array_unique($exemptions)),
            'scope' => $scope,
            'distanceKm' => $distanceKm,
            'national' => $nationalOverride,
        ];
    }

    private function loadJson(string $path): array
    {
        try {
            if (!is_readable($path)) {
                return [];
            }
            $raw = (string)file_get_contents($path);
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Best-effort scope classification to avoid mislabeling long-distance as regional.
     */
    private function classifyScope(string $country, string $product, array $ctx): ?string
    {
        $brand = strtoupper(trim($product));
        // France: protect TGV/IC from falling into regional bucket
        if ($country === 'FR') {
            if (preg_match('/\b(TGV|INOU[IÍ]|OUIGO|LYRIA|INTERCIT[ÉE]S)\b/u', $brand)) {
                return 'long_domestic';
            }
            if (preg_match('/\b(TER|TRANSILIEN|RER|TRAM|METRO)\b/u', $brand)) {
                return 'regional';
            }
        }
        // Austria/DE/CH style IC/EC/ICE/RJ/NJ signals long distance
        if (preg_match('/\b(ICE|IC|INTERCITY|EC|RJ|RAILJET|NIGHTJET|EIP|EIC|AVE|FRECCIA|ITALO|LYN|LYNTOG)\b/u', $brand)) {
            return 'long_domestic';
        }
        // Generic: if caller passed isInternational
        if (!empty($ctx['isInternational'])) {
            return !empty($ctx['endsOutsideEU']) ? 'intl_beyond_eu' : 'intl_inside_eu';
        }
        // Heuristic: if distance known
        if (isset($ctx['distanceKm']) && is_numeric($ctx['distanceKm'])) {
            $d = (float)$ctx['distanceKm'];
            if ($d >= 120) { return 'long_domestic'; }
            if ($d <= 60) { return 'regional'; }
        }
        return null;
    }

    private function loadStationsMap(string $path): array
    {
        $map = [];
        try {
            if (!is_readable($path)) { return []; }
            $raw = file_get_contents($path);
            $data = json_decode((string)$raw, true);
            if (!is_array($data)) { return []; }
            foreach ($data as $row) {
                if (!is_array($row)) { continue; }
                $country = strtoupper((string)($row['country'] ?? ''));
                if ($country !== 'SE') { continue; } // we only need SE for 150km rule
                $name = strtoupper(trim((string)($row['name'] ?? '')));
                $lat = isset($row['lat']) ? (float)$row['lat'] : null;
                $lon = isset($row['lon']) ? (float)$row['lon'] : null;
                if ($name === '' || $lat === null || $lon === null) { continue; }
                $map[$name] = ['lat' => $lat, 'lon' => $lon];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $map;
    }

    private function computeDistanceKm(string $country, string $dep, string $arr): ?float
    {
        if ($country !== 'SE') { return null; }
        $d = strtoupper(trim($dep));
        $a = strtoupper(trim($arr));
        if ($d === '' || $a === '' || empty($this->stationCoords)) { return null; }
        $depCoord = $this->stationCoords[$d] ?? null;
        $arrCoord = $this->stationCoords[$a] ?? null;
        if (!$depCoord || !$arrCoord) { return null; }
        return $this->haversine($depCoord['lat'], $depCoord['lon'], $arrCoord['lat'], $arrCoord['lon']);
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
