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
        // SE (Sweden)
        'stockholm' => 'SE', 'stockholms' => 'SE', 'göteborg' => 'SE', 'goteborg' => 'SE', 'gothenburg' => 'SE', 'malmö' => 'SE', 'malmo' => 'SE', 'uppsala' => 'SE', 'gävle' => 'SE', 'gavle' => 'SE',
        'sundsvall' => 'SE', 'örebro' => 'SE', 'orebro' => 'SE', 'västerås' => 'SE', 'vasteras' => 'SE', 'jönköping' => 'SE', 'jonkoping' => 'SE', 'linköping' => 'SE', 'linkoping' => 'SE',
        'norrköping' => 'SE', 'norrkoping' => 'SE', 'lund' => 'SE', 'helsingborg' => 'SE', 'halmstad' => 'SE', 'karlstad' => 'SE', 'luleå' => 'SE', 'lulea' => 'SE', 'umeå' => 'SE', 'umea' => 'SE',
        'borås' => 'SE', 'boras' => 'SE', 'skövde' => 'SE', 'skovde' => 'SE', 'trollhättan' => 'SE', 'trollhattan' => 'SE', 'hässleholm' => 'SE', 'hassleholm' => 'SE', 'kalmar' => 'SE', 'växjö' => 'SE', 'vaxjo' => 'SE',
        // PL (Poland)
        'warszawa' => 'PL', 'warsaw' => 'PL', 'kraków' => 'PL', 'krakow' => 'PL', 'wrocław' => 'PL', 'wroclaw' => 'PL', 'poznan' => 'PL', 'poznań' => 'PL', 'gdansk' => 'PL', 'gdańsk' => 'PL', 'katowice' => 'PL', 'łódź' => 'PL', 'lodz' => 'PL', 'przemyśl' => 'PL', 'przemysl' => 'PL',
        // UA (Ukraine)
        'lviv' => 'UA', 'kyiv' => 'UA', 'kiev' => 'UA', 'odesa' => 'UA', 'odessa' => 'UA', 'kharkiv' => 'UA',
    ];

    /** Minimal EU list for classification */
    private const EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];

    /**
     * Suburban/local products per country (ASCII tokens, uppercase) â€” these are never handled by the app.
     * This mirrors the policy shared with the user: suburban services are excluded in all EU countries.
     */
    private const SUBURBAN_SERVICES = [
        'AT' => ['S-BAHN', 'REX', 'REX+', 'REGIONALZUG', ' R '],
        'BE' => ['S-TRAIN', 'S-TREIN', 'L-TREIN', ' L '],
        'BG' => ['BDZ REGIONAL', 'PÄTNICHESKI', 'PATNICHESKI', 'REGIONALNI'],
        'HR' => ['HŽPP SUBURBAN', 'HZPP SUBURBAN', 'LOKALNI VLAK'],
        'CY' => [],
        'CZ' => [' OS ', 'OSOBNI', 'OSOBNÍ', ' SP ', 'S-LINE'],
        'DK' => ['S-TOG', 'STOG', 'LOKALTOG', 'LETBANE', 'ODENSE LETBANE', 'AARHUS LETBANE'],
        'EE' => ['ELRON LOCAL', 'ELRON COMMUTER'],
        'FI' => ['HSL ', ' VR COMMUTER', ' VR LAHI', ' VR LÃ„HI', ' VR LÄHI', ' HSL A', ' HSL I', ' HSL P', ' HSL K', ' HSL L', ' HSL E', ' HSL R', ' HSL T'],
        'FR' => ['RER', 'TRANSILIEN', 'TER', 'NAVETTE', 'ILE-DE-FRANCE'],
        'DE' => ['S-BAHN', ' RB ', ' RE ', ' IRE '],
        'GR' => ['PROASTIAKOS'],
        'HU' => ['HÉV', 'HEV', 'SZEMELY', 'SZEMÉLY', 'INTERRÉGIÓ', 'INTERREGIO', 'IR ', ' GYORSVONAT'],
        'IE' => ['DART', 'COMMUTER RAIL', 'IARNROD EIREANN COMMUTER', 'IARNRÃ“D Ã‰IREANN COMMUTER'],
        'IT' => ['TRENO SUBURBANO', 'TRENORD S', ' FL1', ' FL2', ' FL3', ' FL4', ' FL5', ' FL6', ' FL7', ' FL8', 'CIRCUMVESUVIANA'],
        'LV' => ['PASAZIERU VILCIENS SUBURBAN', 'PASAZIERU VILCIENS'],
        'LT' => ['LTG LINK COMMUTER', 'LTG COMMUTER'],
        'LU' => ['CFL SUBURBAN', 'CFL LIGNE SUBURBAINE'],
        'MT' => [],
        'NL' => ['SPRINTER', 'R-NET', 'ARRIVA LOCAL', 'KEOLIS REGIONAL'],
        'PL' => ['SKM', 'POLREGIO', ' PR ', 'KOLEJE MAZOWIECKIE', ' KM ', 'KOLEJE SLASKIE', 'KS ', 'KŚ', 'KD ', 'Koleje Dolnoslaskie', 'ŁKA', 'LKA'],
        'PT' => ['URBANOS', 'LINHA DE SINTRA', 'LINHA DE CASCAIS', 'LINHA DO PORTO', 'LINHA DE COIMBRA'],
        'RO' => ['REGIO', 'REGIO EXPRESS', 'SUBURBAN CFR'],
        'SK' => ['OSOBNY VLAK', 'OS ', ' RYCHLIK', ' R '],
        'SI' => ['SŽ LOCAL', 'SZ LOCAL', 'LJUBLJANA SUBURBAN'],
        'ES' => ['CERCANIAS', 'CERCANÃAS', 'RODALIES', 'RODALIAS'],
        'SE' => ['PENDELTAG', 'PENDELTÃ…G', 'VASTTAGEN', 'VÄSTTÅGEN', 'PAGATAGEN', 'PÅGATÅGEN', 'ORESUNDSTAG', 'ORESUNDSTÃ…G', 'Ã–RESUNDSTÅG'],
    ];

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

    /** Uppercase + transliterate for robust token matching (ASCII-friendly) */
    private function normalize(string $s): string
    {
        $norm = $s;
        try {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($conv !== false) { $norm = $conv; }
        } catch (\Throwable $e) { /* ignore */ }
        return strtoupper($norm);
    }

    /** Find a usable country context from journey/meta/operator hints */
    private function resolveCountryContext(array $journey, array $meta): ?string
    {
        // Prefer segment countries
        foreach ((array)($journey['segments'] ?? []) as $seg) {
            $c = strtoupper((string)($seg['country'] ?? ''));
            if ($c !== '') { return $c; }
        }
        // Fall back to journey-level country
        $journeyC = strtoupper((string)($journey['country']['value'] ?? ''));
        if ($journeyC !== '') { return $journeyC; }
        // Finally, operator country from OCR/manual input
        $opC = strtoupper((string)($meta['_auto']['operator_country']['value'] ?? ($meta['operator_country'] ?? '')));
        return $opC !== '' ? $opC : null;
    }

    /** Match product against the per-country suburban blocklist */
    private function isSuburbanProduct(?string $country, string $product): bool
    {
        if ($country === null || $country === '') { return false; }
        $tokens = self::SUBURBAN_SERVICES[$country] ?? [];
        if (empty($tokens)) { return false; }
        $p = $this->normalize($product);
        foreach ($tokens as $t) {
            if ($t === '') { continue; }
            if (strpos($p, $t) !== false) { return true; }
        }
        return false;
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
        if (str_contains($s, 'stockholm') || str_contains($s, 'göteborg') || str_contains($s, 'goteborg') || str_contains($s, 'malmö') || str_contains($s, 'malmo')) { return 'SE'; }
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

        // Suburban/commuter detection (S-Bahn, S-tog, Pendeltåg, etc.)
        try {
            $prod = (string)($meta['_auto']['operator_product']['value'] ?? '');
            $countryCtx = $this->resolveCountryContext($journey, $meta);
            // Suburban detection using extended blocklist per country
            if ($prod !== '' && $this->isSuburbanProduct($countryCtx, $prod)) {
                $journey['is_suburban'] = true;
                $logs[] = 'AUTO: flagged as suburban/commuter based on product=' . $prod;
            }
            // Finland commuter restriction hint
            if ($depC === 'FI' || $arrC === 'FI' || (string)($journey['country']['value'] ?? '') === 'FI') {
                $fiTokens = [ 'hsl', 'lähijuna', 'lahijuna', 'vr commuter', 'vr lähiliikenne', 'vr lahiliikenne', 'vr lahijuna' ];
                foreach ($fiTokens as $t) {
                    if ($prod !== '' && stripos($prod, $t) !== false) { $journey['fi_commuter_route'] = true; $logs[] = 'AUTO: FI commuter route hint from product=' . $prod; break; }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }
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
