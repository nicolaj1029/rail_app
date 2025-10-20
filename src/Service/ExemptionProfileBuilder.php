<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Builds an exemption profile (which articles apply) for a journey using a JSON-based country matrix.
 */
class ExemptionProfileBuilder
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $matrix;

    public function __construct(?array $matrix = null)
    {
        $this->matrix = $matrix ?? $this->loadMatrix();
    }

    /**
     * @return array{scope:string,articles:array<string,bool>,notes:array<int,string>,ui_banners:array<int,string>}
     */
    public function build(array $journey): array
    {
        $scope = $this->classifyScope($journey);
        $profile = [
            'scope' => $scope,
            'articles' => [
                'art12' => true,
                'art17' => true,
                'art18_3' => true,
                'art19' => true,
                'art20_2' => true,
                'art30_2' => true,
                'art10' => true,
                'art9' => true,
            ],
            'articles_sub' => [
                'art9_1' => true,
                'art9_2' => true,
                'art9_3' => true,
            ],
            'notes' => [],
            'ui_banners' => [],
        ];

        $segments = (array)($journey['segments'] ?? []);
        foreach ($segments as $seg) {
            $country = strtoupper((string)($seg['country'] ?? ''));
            if ($country === '') { continue; }
            foreach ($this->matrix[$country] ?? [] as $entry) {
                if (($entry['scope'] ?? null) !== $scope) { continue; }
                // If the entry marks this scope as blocked (EU-flow disabled), model compensation/assistance as exempt accordingly
                if (!empty($entry['blocked'])) {
                    // Default: disable Art.19 (compensation) for blocked regional flows
                    $profile['articles']['art19'] = false;
                    // Country-specific: for PL regional, also disable Art.20(2)
                    if ($country === 'PL' && $scope === 'regional') {
                        $profile['articles']['art20_2'] = false;
                    }
                    // Carry reason and add a standardized country+scope note for tests/UX
                    if (!empty($entry['reason'])) { $profile['notes'][] = (string)$entry['reason']; }
                    $profile['notes'][] = $country . ' regional: EU-flow disabled (blocked).';
                }
                // Map generic "exemptions" array into article flags
                $exArr = (array)($entry['exemptions'] ?? []);
                $map = [
                    'Art.12' => 'art12',
                    'Art.17' => 'art17',
                    'Art.18(3)' => 'art18_3',
                    'Art.19' => 'art19',
                    'Art.20(2)' => 'art20_2',
                    'Art.30(2)' => 'art30_2',
                    'Art.10' => 'art10',
                    'Art.9' => 'art9',
                ];
                foreach ($exArr as $ex) {
                    $exStr = (string)$ex;
                    $artKey = $map[$exStr] ?? null;
                    if ($artKey) { $profile['articles'][$artKey] = false; }
                    // Partial Art.9 handling
                    if ($exStr === 'Art.9') {
                        $profile['articles_sub']['art9_1'] = false;
                        $profile['articles_sub']['art9_2'] = false;
                        $profile['articles_sub']['art9_3'] = false;
                    } elseif (preg_match('/^Art\.9\((1|2|3)\)$/', $exStr, $m)) {
                        $profile['articles_sub']['art9_' . $m[1]] = false;
                    }
                }
                if (!empty($entry['notes']) && is_array($entry['notes'])) {
                    foreach ($entry['notes'] as $n) { $profile['notes'][] = (string)$n; }
                }
            }
        }

        // Consolidate art9 based on sub-parts unless already fully disabled
        if ($profile['articles']['art9']) {
            $subs = $profile['articles_sub'];
            if ($subs['art9_1'] === false && $subs['art9_2'] === false && $subs['art9_3'] === false) {
                $profile['articles']['art9'] = false;
            }
        }
        // Add note for partial exemption
        if ($profile['articles']['art9'] && in_array(false, $profile['articles_sub'], true)) {
            $partsOff = [];
            foreach ($profile['articles_sub'] as $k => $v) {
                if ($v === false) { $partsOff[] = '9(' . substr($k, -1) . ')'; }
            }
            if (!empty($partsOff)) {
                $profile['notes'][] = 'Delvis Art. 9-fritagelse: ' . implode(', ', $partsOff) . ' undtaget.';
            }
        }

        // Apply country-specific conditional gates not directly expressible in the matrix
        $this->applyConditionalGates($journey, $profile);

        // Derive UI banners
        if (!$profile['articles']['art10']) {
            $profile['ui_banners'][] = 'Realtime-data (Art. 10) kan mangle — fallback til ikke-live RNE og upload dokumentation (Art. 20(4)).';
        }
        if (!$profile['articles']['art12']) {
            $profile['ui_banners'][] = 'Gennemgående billet (Art. 12) undtaget — krav splittes pr. billet/kontrakt.';
        }
        if (!$profile['articles']['art18_3']) {
            $profile['ui_banners'][] = '100-minutters-reglen (Art. 18(3)) kan være undtaget.';
        }
        if (!$profile['articles']['art19']) {
            $profile['ui_banners'][] = 'EU-kompensation (Art. 19) undtaget — anvend national/operatør-ordning hvor relevant.';
        }
        if (!$profile['articles']['art20_2']) {
            $profile['ui_banners'][] = 'Assistance (Art. 20(2)) kan være undtaget; upload udgiftsbilag.';
        }
        if (!$profile['articles']['art9']) {
            $profile['ui_banners'][] = 'Informationspligter (Art. 9) kan være undtaget — vis basisoplysninger og fallback-links.';
        }

        return $profile;
    }

    /**
     * Apply conditional rules that require runtime context (distance, terminals, third-country checks).
     * Mutates $profile in-place.
     * @param array $journey
     * @param array{scope:string,articles:array<string,bool>,notes:array<int,string>,ui_banners:array<int,string>} &$profile
     */
    private function applyConditionalGates(array $journey, array &$profile): void
    {
        $scope = (string)$profile['scope'];
        $segments = (array)($journey['segments'] ?? []);
        $countries = array_values(array_filter(array_map(fn($s) => strtoupper((string)($s['country'] ?? '')), $segments)));
        $has = function(string $cc) use ($countries): bool { return in_array(strtoupper($cc), $countries, true); };

        // SE: <150 km domestic only — only apply exemptions when under 150 km
        if ($has('SE') && $scope === 'regional') {
            $under150 = false;
            // Prefer explicit distance on journey or per-segment sum
            $dist = $this->getJourneyDistanceKm($journey);
            if ($dist !== null) { $under150 = ($dist < 150.0); }
            // Allow explicit hint flag if distance is unavailable
            if ($dist === null) {
                $under150 = (bool)($journey['se_under_150km'] ?? false);
            }
            if (!$under150) {
                // Re-enable articles that might have been disabled via matrix for SE regional when not under 150 km
                // Focus on keys we actually model downstream
                $profile['articles']['art19'] = true;
                $profile['articles']['art17'] = true;
                $profile['articles']['art20_2'] = true;
                $profile['notes'][] = 'SE: <150 km-betingelsen ikke opfyldt — regionale undtagelser anvendes ikke.';
            }
        }

        // FI: intl_beyond_eu involving RU/BY — restrict Art.12 and Art.18(3)
        if ($has('FI') && $scope === 'intl_beyond_eu') {
            if ($has('RU') || $has('BY')) {
                $profile['articles']['art12'] = false;
                $profile['articles']['art18_3'] = false;
                $profile['notes'][] = 'FI: Rute til/fra RU/BY — Art. 12 og 18(3) begrænses (Art. 2(6)(b)).';
                $profile['ui_banners'][] = 'Rute delvist uden for EU — gennemgående billet/100-min kan være undtaget.';
            }
        }

        // CZ: intl_beyond_eu where first/last terminal in third country (except CH) — restrict Art.12 and Art.18(3)
        if ($has('CZ') && $scope === 'intl_beyond_eu' && count($segments) > 0) {
            [$firstC, $lastC] = [$countries[0] ?? '', $countries[count($countries)-1] ?? ''];
            $isThird = function(string $c): bool {
                if ($c === '') return false;
                if ($c === 'CH') return false; // Switzerland is the explicit exception
                return !$this->isEuCountry($c);
            };
            if ($isThird($firstC) || $isThird($lastC)) {
                $profile['articles']['art12'] = false;
                $profile['articles']['art18_3'] = false;
                $profile['notes'][] = 'CZ: Tredjelands-terminal (ekskl. CH) — Art. 12 og 18(3) undtages.';
                $profile['ui_banners'][] = 'Gennemgående billet/100-min kan være undtaget pga. tredjelandsterminal.';
            }
        }
    }

    /** Determine total distance in km if present on journey or sum of segments. */
    private function getJourneyDistanceKm(array $journey): ?float
    {
        $dist = null;
        if (isset($journey['distance_km']) && is_numeric($journey['distance_km'])) {
            $dist = (float)$journey['distance_km'];
        } else {
            $sum = 0.0; $have = false;
            foreach ((array)($journey['segments'] ?? []) as $s) {
                if (isset($s['distance_km']) && is_numeric($s['distance_km'])) {
                    $sum += (float)$s['distance_km'];
                    $have = true;
                }
            }
            if ($have) { $dist = $sum; }
        }
        return $dist;
    }

    /** Minimal EU membership check for terminal-country logic. */
    private function isEuCountry(string $code): bool
    {
        $eu = [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'
        ];
        return in_array(strtoupper($code), $eu, true);
    }

    private function classifyScope(array $journey): string
    {
        // Simple classifier; can be replaced with distance/time based share outside EU
        if (!empty($journey['is_international_beyond_eu'])) return 'intl_beyond_eu';
        if (!empty($journey['is_international_inside_eu'])) return 'intl_inside_eu';
        if (!empty($journey['is_long_domestic'])) return 'long_domestic';
        return 'regional';
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function loadMatrix(): array
    {
        $path = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'exemption_matrix.json';
        if (!is_file($path)) { return []; }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
