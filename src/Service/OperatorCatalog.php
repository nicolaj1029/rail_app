<?php
declare(strict_types=1);

namespace App\Service;

final class OperatorCatalog
{
    /** @var array{name:string,aliases:string[],country:string,products:string[]}[] */
    private array $operators = [];
    /** @var array<string,string> */
    private array $productAliases = [];

    public function __construct(?string $path = null)
    {
        $path = $path ?? (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'operators_catalog.json');
        if (is_file($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data)) {
                $this->operators = array_values((array)($data['operators'] ?? []));
                $this->productAliases = (array)($data['product_aliases'] ?? []);
            }
        }
    }

    /** Country code => Country name (basic map) */
    public function getCountries(): array
    {
        return [
            'AT'=>'Austria','BE'=>'Belgium','BG'=>'Bulgaria','CZ'=>'Czechia','DE'=>'Germany','DK'=>'Denmark','EE'=>'Estonia','ES'=>'Spain','FI'=>'Finland','FR'=>'France','GR'=>'Greece','HR'=>'Croatia','HU'=>'Hungary','IE'=>'Ireland','IT'=>'Italy','LT'=>'Lithuania','LU'=>'Luxembourg','LV'=>'Latvia','NL'=>'Netherlands','PL'=>'Poland','PT'=>'Portugal','RO'=>'Romania','SE'=>'Sweden','SI'=>'Slovenia','SK'=>'Slovakia','CH'=>'Switzerland','GB'=>'United Kingdom'
        ];
    }

    /** Country => operators (name=>name) built from JSON */
    public function getOperators(): array
    {
        $out = [];
        foreach ($this->operators as $op) {
            $cc = strtoupper((string)($op['country'] ?? ''));
            $name = (string)($op['name'] ?? '');
            if ($cc && $name) {
                $out[$cc][$name] = $name;
            }
        }
        ksort($out);
        foreach ($out as &$arr) { ksort($arr); }
        return $out;
    }

    /** Operator => products list built from JSON */
    public function getProducts(): array
    {
        $out = [];
        foreach ($this->operators as $op) {
            $name = (string)($op['name'] ?? '');
            if ($name) {
                $out[$name] = array_values((array)($op['products'] ?? []));
            }
        }
        return $out;
    }

    /** @return array{name:string,country:string}|null */
    public function findOperator(string $text): ?array
    {
        $hay = (string)$text;
        foreach ($this->operators as $op) {
            $names = array_unique(array_merge([(string)$op['name']], (array)$op['aliases']));
            foreach ($names as $nRaw) {
                $n = trim((string)$nRaw);
                // Skip overly short aliases (e.g., 'es') to avoid massive false positives
                if (mb_strlen($n) < 3) { continue; }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($n, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $hay)) {
                    return ['name' => (string)$op['name'], 'country' => strtoupper((string)$op['country'])];
                }
            }
        }
        return null;
    }

    /** @return string|null */
    public function findProduct(string $text): ?string
    {
        $hay = (string)$text;
        // First pass: strict word-boundary matches for full product names
        foreach ($this->operators as $op) {
            foreach ((array)$op['products'] as $pRaw) {
                $p = trim((string)$pRaw);
                if ($p === '') { continue; }
                // Avoid extremely short products (1-letter like "R") which cause massive false positives
                if (mb_strlen($p) < 2) { continue; }
                // Avoid matching inside other words (e.g., TER in "international")
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($p, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $hay)) { return (string)$p; }
            }
        }
        // Second pass: alias map with boundaries; skip extremely short aliases to avoid noise
        foreach ($this->productAliases as $aliasRaw => $norm) {
            $alias = trim((string)$aliasRaw);
            if (mb_strlen($alias) < 3) { continue; }
            $pattern = '/(?<![A-Za-z0-9])' . preg_quote($alias, '/') . '(?![A-Za-z0-9])/iu';
            if (preg_match($pattern, $hay)) { return (string)$norm; }
        }
        return null;
    }

    /**
     * Find the operator that owns a given product name.
     * @return array{name:string,country:string}|null
     */
    public function findOperatorByProduct(string $product): ?array
    {
        $pNeedle = trim($product);
        if ($pNeedle === '') { return null; }
        foreach ($this->operators as $op) {
            $prods = array_map(fn($p) => (string)$p, (array)($op['products'] ?? []));
            foreach ($prods as $p) {
                if (mb_strtolower($p) === mb_strtolower($pNeedle)) {
                    return ['name' => (string)$op['name'], 'country' => strtoupper((string)$op['country'] ?? '')];
                }
            }
        }
        return null;
    }

    /**
     * Returns a static map used by some templates/tools to infer product scope buckets.
     * opName => [ productName => scopeKey ]
     */
    public function getProductScopes(): array
    {
        return [
            'ÖBB' => [
                'R/REX/S-Bahn' => 'regional',
                'Railjet' => 'long_domestic',
                'InterCity' => 'long_domestic',
                'Nightjet (domestic legs)' => 'long_domestic',
                'Railjet/EC/NJ (EU)' => 'intl_inside_eu',
            ],
            'SNCB/NMBS' => [
                'IC (domestic)' => 'regional',
                'IC (long domestic)' => 'long_domestic',
                'IC/EC/ICE/Thalys/Eurostar EU' => 'intl_inside_eu',
            ],
            'BDZ' => [
                'Local/Regional' => 'regional',
                'IC/IR' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
            ],
            'ČD' => [
                'Os/Sp/RE' => 'regional',
                'IC/EC/Ex/RJ' => 'long_domestic',
                'EC/RJ (EU)' => 'intl_inside_eu',
            ],
            'DB' => [
                'RB/RE' => 'regional',
                'IC (domestic)' => 'long_domestic',
                'ICE (domestic)' => 'long_domestic',
                'EC (domestic)' => 'long_domestic',
                'ICE/EC (EU)' => 'intl_inside_eu',
            ],
            'DSB' => [
                'Regionaltog' => 'regional',
                'IC' => 'long_domestic',
                'Lyntog' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
            ],
            'Elron' => [
                'Rongid (Local/Regional)' => 'regional',
            ],
            'Renfe' => [
                'MD/Cercanías' => 'regional',
                'AVE' => 'long_domestic',
                'Avlo' => 'long_domestic',
                'ALVIA' => 'long_domestic',
                'Intercity' => 'long_domestic',
                'EU services (limited)' => 'intl_inside_eu',
            ],
            'VR' => [
                'IC' => 'long_domestic',
                'Pendolino' => 'long_domestic',
                'Intl (SE) – TO_VERIFY' => 'intl_inside_eu',
            ],
            'SNCF' => [
                'TER' => 'regional',
                'TGV INOUI' => 'long_domestic',
                'Intercités' => 'long_domestic',
                'ICE (EU)' => 'intl_inside_eu',
                'Lyria (EU)' => 'intl_inside_eu',
                'Thalys/Eurostar (EU)' => 'intl_inside_eu',
                'TGV INOUI / Intercités (G30)' => 'long_domestic',
            ],
            'Hellenic Train' => [
                'Proastiakos/Regional' => 'regional',
                'InterCity' => 'long_domestic',
            ],
            'HŽPP' => [
                'Local/Regional' => 'regional',
                'IC/IR' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
            ],
            'MÁV-START' => [
                'Személy/RE' => 'regional',
                'IC' => 'long_domestic',
                'EC/RJ (EU)' => 'intl_inside_eu',
            ],
            'Iarnród Éireann' => [
                'InterCity' => 'long_domestic',
            ],
            'Trenitalia' => [
                'Regionale' => 'regional',
                'Frecciarossa' => 'long_domestic',
                'Frecciargento' => 'long_domestic',
                'Frecciabianca' => 'long_domestic',
                'InterCity' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
                'Frecce (Frecciarossa/Frecciargento/Frecciabianca)' => 'long_domestic',
            ],
            'Italo NTV' => [
                'Italo (long-distance)' => 'long_domestic',
            ],
            'LTG Link' => [
                'Local' => 'regional',
                'Long-distance – TO_VERIFY' => 'long_domestic',
            ],
            'CFL' => [
                'CFL Local' => 'regional',
                'CFL cross-border (EU)' => 'intl_inside_eu',
            ],
            'Pasažieru vilciens' => [
                'PV Local/Regional' => 'regional',
            ],
            'NS' => [
                'Sprinter' => 'regional',
                'Intercity' => 'long_domestic',
                'IC/ICE/Eurostar (EU)' => 'intl_inside_eu',
                'Domestic (Reisvertraging regeling)' => 'long_domestic',
            ],
            'PKP Intercity' => [
                'IC' => 'long_domestic',
                'TLK' => 'long_domestic',
                'EIC/EC (EU)' => 'intl_inside_eu',
                'International beyond EU exceptions' => 'intl_beyond_eu',
            ],
            'Regional Operators (PL)' => [
                'PR/KM/ŚKM/… (regional operators)' => 'regional',
            ],
            'CP Comboios de Portugal' => [
                'Regional/Urbano' => 'regional',
                'Intercidades' => 'long_domestic',
                'Alfa Pendular' => 'long_domestic',
                'Intl (EU) – TO_VERIFY' => 'intl_inside_eu',
            ],
            'CFR Călători' => [
                'R/RE' => 'regional',
                'IR (domestic)' => 'long_domestic',
                'IR (EU)' => 'intl_inside_eu',
            ],
            'SJ' => [
                'Länstrafik (regional)' => 'regional',
                'SJ long-distance' => 'long_domestic',
                'SJ/Öresundståg (EU)' => 'intl_inside_eu',
                'Förseningsersättning (national law)' => 'long_domestic',
            ],
            'Öresundståg' => [
                'Öresundståg (EU)' => 'intl_inside_eu',
            ],
            'SŽ' => [
                'Regional' => 'regional',
                'IC/EC (domestic legs)' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
            ],
            'ZSSK' => [
                'Os/RE' => 'regional',
                'R/IC/Ex' => 'long_domestic',
                'EC (EU)' => 'intl_inside_eu',
            ],
        ];
    }
}
