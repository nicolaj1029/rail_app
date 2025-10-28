<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Infers journey scope (intl/domestic) and involved countries from station names.
 * Conservative heuristic: only sets flags when confidence is reasonable.
 */
class JourneyScopeInferer
{
    /** Map common station/city tokens to ISO country codes */
    private const CITY_COUNTRY = [
        // IT
        'verona' => 'IT', 'brennero' => 'IT', 'bressanone' => 'IT', 'bolzano' => 'IT', 'trento' => 'IT', 'brenner' => 'IT',
        // DE
        'münchen' => 'DE', 'munchen' => 'DE', 'muenchen' => 'DE', 'düsseldorf' => 'DE', 'duesseldorf' => 'DE', 'dusseldorf' => 'DE', 'hamburg' => 'DE', 'berlin' => 'DE', 'frankfurt' => 'DE',
        // AT
        'innsbruck' => 'AT', 'jenbach' => 'AT', 'kufstein' => 'AT', 'salzburg' => 'AT',
        // CH
        'zürich' => 'CH', 'zurich' => 'CH', 'basel' => 'CH',
        // FR
        'paris' => 'FR', 'lyon' => 'FR', 'lille' => 'FR', 'bordeaux' => 'FR', 'montpellier' => 'FR',
        // DK (Denmark)
        'københavn' => 'DK', 'kobenhavn' => 'DK', 'copenhagen' => 'DK', 'odense' => 'DK', 'aarhus' => 'DK', 'århus' => 'DK', 'aalborg' => 'DK', 'esbjerg' => 'DK', 'roskilde' => 'DK', 'nyborg' => 'DK', 'slagelse' => 'DK',
        // PL (Poland)
        'warszawa' => 'PL', 'warsaw' => 'PL', 'kraków' => 'PL', 'krakow' => 'PL', 'wrocław' => 'PL', 'wroclaw' => 'PL', 'poznan' => 'PL', 'poznań' => 'PL', 'gdansk' => 'PL', 'gdańsk' => 'PL', 'katowice' => 'PL', 'łódź' => 'PL', 'lodz' => 'PL', 'przemyśl' => 'PL', 'przemysl' => 'PL',
        // UA (Ukraine)
        'lviv' => 'UA', 'kyiv' => 'UA', 'kiev' => 'UA', 'odesa' => 'UA', 'odessa' => 'UA', 'kharkiv' => 'UA',
    ];

    /** Minimal EU list for classification */
    private const EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];

    /** Strip platform abbreviations and brackets */
    private function cleanStation(string $s): string
    {
        $t = ' ' . trim($s) . ' ';
        // Remove German platform abbreviation Gl. or Gl
        $t = preg_replace('/\bGl\.?\s*\d+(?:[-–]\d+)?\b/iu', ' ', $t) ?? $t;
        // Remove generic platform terms we already handle elsewhere
        $t = preg_replace('/\b(?:gleis|platform|bin(?:ario)?)\s*\d+(?:[-–]\d+)?\b/iu', ' ', $t) ?? $t;
        $t = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $t) ?? $t;
        return trim(preg_replace('/\s{2,}/u', ' ', $t) ?? $t);
    }

    /** Guess country from station string using token map */
    private function guessCountry(string $station): ?string
    {
        $s = mb_strtolower($this->cleanStation($station));
        $parts = preg_split('/[\s,\-]+/u', $s) ?: [];
        foreach ($parts as $p) {
            if (isset(self::CITY_COUNTRY[$p])) { return self::CITY_COUNTRY[$p]; }
        }
        // Also allow multi-word tokens (e.g., 'porta' 'nuova' together implies IT for Verona Porta Nuova)
        if (str_contains($s, 'verona')) { return 'IT'; }
        if (str_contains($s, 'münchen') || str_contains($s, 'munchen') || str_contains($s, 'muenchen')) { return 'DE'; }
        if (str_contains($s, 'københavn') || str_contains($s, 'kobenhavn') || str_contains($s, 'copenhagen')) { return 'DK'; }
        return null;
    }

    /** Apply inference to the given journey using meta._auto dep/arr stations. */
    public function infer(array $journey, array $meta, array &$logs = []): array
    {
        $dep = (string)($meta['_auto']['dep_station']['value'] ?? '');
        $arr = (string)($meta['_auto']['arr_station']['value'] ?? '');
        $depC = $dep !== '' ? $this->guessCountry($dep) : null;
        $arrC = $arr !== '' ? $this->guessCountry($arr) : null;
        if ($depC) { $logs[] = 'AUTO: inferred dep country from station=' . $depC; }
        if ($arrC) { $logs[] = 'AUTO: inferred arr country from station=' . $arrC; }

        // Update segments countries if we have guesses
        if ($depC || $arrC) {
            if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = [[]]; }
            // Ensure at least one segment
            if (empty($journey['segments'])) { $journey['segments'][] = []; }
            // Set first/last segment countries if available
            if ($depC) { $journey['segments'][0]['country'] = $depC; }
            if ($arrC) {
                $lastIdx = count($journey['segments']) - 1;
                $journey['segments'][$lastIdx]['country'] = $journey['segments'][$lastIdx]['country'] ?? $arrC;
            }
            // Set journey.country to departure as a baseline
            if ($depC) { $journey['country']['value'] = $depC; }
        }

        // Classify scope if dep/arr countries differ
        if ($depC && $arrC && $depC !== $arrC) {
            $bothEU = in_array($depC, self::EU, true) && in_array($arrC, self::EU, true);
            if ($bothEU) {
                $journey['is_international_inside_eu'] = true;
                $journey['is_international_beyond_eu'] = false;
                $journey['is_long_domestic'] = false;
                $logs[] = 'AUTO: scope inferred as intl_inside_eu from stations';
            } else {
                $journey['is_international_beyond_eu'] = true;
                $journey['is_international_inside_eu'] = false;
                $journey['is_long_domestic'] = false;
                $logs[] = 'AUTO: scope inferred as intl_beyond_eu from stations';
            }
        } elseif ($depC && $arrC && $depC === $arrC) {
            // Same-country: consider long_domestic when product hints long-distance
            $prod = (string)($meta['_auto']['operator_product']['value'] ?? '');
            $longProducts = ['TGV','ICE','IC','EC','AVE','Frecciarossa','Frecciargento','Frecciabianca','Intercity','Intercités','Pendolino'];
            foreach ($longProducts as $lp) {
                if ($prod !== '' && stripos($prod, $lp) !== false) {
                    $journey['is_long_domestic'] = true;
                    $journey['is_international_inside_eu'] = false;
                    $journey['is_international_beyond_eu'] = false;
                    $logs[] = 'AUTO: scope inferred as long_domestic from product=' . $prod;
                    break;
                }
            }
        }
        return $journey;
    }

    /**
     * Back-compat wrapper used by controllers: returns ['journey' => array, 'logs' => string[]]
     */
    public function apply(array $journey, array $meta): array
    {
        $logs = [];
        $journey2 = $this->infer($journey, $meta, $logs);
        return ['journey' => $journey2, 'logs' => $logs];
    }
}
