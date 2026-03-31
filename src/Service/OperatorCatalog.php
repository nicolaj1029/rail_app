<?php
declare(strict_types=1);

namespace App\Service;

final class OperatorCatalog
{
    private const CATALOG_PATH = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json';

    /** @var array<int,array<string,mixed>> */
    private array $operators = [];
    /** @var array<string,string> */
    private array $productAliases = [];
    /** @var array<string,array<string,string>> */
    private array $productAliasesByMode = [];
    /** @var array<string,mixed> */
    private array $downgradePolicies = [];
    /** @var array<string,mixed> */
    private array $modePolicies = [];
    private int $schemaVersion = 1;

    public function __construct(?string $path = null)
    {
        $path = $path ?? self::CATALOG_PATH;
        $data = TransportOperatorsCatalogLoader::load($path);
        if ($data === []) {
            return;
        }

        $this->schemaVersion = (int)($data['schema_version'] ?? 1);
        $this->operators = $this->normalizeOperators((array)($data['operators'] ?? []));
        $this->productAliases = (array)($data['product_aliases'] ?? []);
        $this->productAliasesByMode = $this->normalizeScopedAliases((array)($data['product_aliases_by_mode'] ?? []));
        $this->downgradePolicies = (array)($data['downgrade_policies'] ?? []);
        $this->modePolicies = (array)($data['mode_policies'] ?? []);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeOperators(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mode = strtolower(trim((string)($row['mode'] ?? '')));
            if (!in_array($mode, ['rail', 'ferry', 'bus', 'air'], true)) {
                continue;
            }

            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = trim((string)($row['operator_key'] ?? ''));
            if ($key === '') {
                $key = $this->buildOperatorKey($name);
            }
            $country = strtoupper(trim((string)($row['country'] ?? ($row['country_code'] ?? ''))));
            $scopeRegion = strtoupper(trim((string)($row['scope_region'] ?? '')));
            $aliases = array_values(array_filter(
                array_map('strval', (array)($row['aliases'] ?? [])),
                static fn(string $value): bool => trim($value) !== ''
            ));
            $products = array_values(array_filter(
                array_map('strval', (array)($row['products'] ?? (($row['metadata']['products'] ?? [])))),
                static fn(string $value): bool => trim($value) !== ''
            ));
            $requiredDocHints = array_values(array_filter(
                array_map('strval', (array)($row['required_doc_hints'] ?? ($row['metadata']['required_doc_hints'] ?? []))),
                static fn(string $value): bool => trim($value) !== ''
            ));
            $codes = $this->normalizeCodes((array)($row['codes'] ?? ($row['metadata']['codes'] ?? [])));
            $sourceUrls = array_values(array_filter(
                array_map('strval', (array)($row['source_urls'] ?? ($row['metadata']['source_urls'] ?? []))),
                static fn(string $value): bool => trim($value) !== ''
            ));

            $normalized[] = [
                'key' => $key,
                'mode' => $mode,
                'name' => $name,
                'display_name' => trim((string)($row['display_name'] ?? $name)),
                'operator_key' => trim((string)($row['operator_key'] ?? $key)),
                'brand_group' => trim((string)($row['brand_group'] ?? ($row['metadata']['brand_group'] ?? ''))),
                'legal_entity_name' => trim((string)($row['legal_entity_name'] ?? ($row['metadata']['legal_entity_name'] ?? ''))),
                'operating_carrier_name' => trim((string)($row['operating_carrier_name'] ?? ($row['metadata']['operating_carrier_name'] ?? ''))),
                'aliases' => $aliases,
                'country' => $country,
                'scope_region' => $scopeRegion,
                'products' => $products,
                'required_doc_hints' => $requiredDocHints,
                'service_type' => trim((string)($row['service_type'] ?? ($row['metadata']['service_type'] ?? ''))),
                'claim_url' => trim((string)($row['claim_url'] ?? ($row['metadata']['claim_url'] ?? ''))),
                'support_url' => trim((string)($row['support_url'] ?? ($row['metadata']['support_url'] ?? ''))),
                'source_confidence' => trim((string)($row['source_confidence'] ?? ($row['metadata']['source_confidence'] ?? ''))),
                'source_urls' => $sourceUrls,
                'codes' => $codes,
                'regular_service' => is_bool($row['regular_service'] ?? null) ? (bool)$row['regular_service'] : (is_bool($row['metadata']['regular_service'] ?? null) ? (bool)$row['metadata']['regular_service'] : null),
                'metadata' => is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $rows
     * @return array<string,array<string,string>>
     */
    private function normalizeScopedAliases(array $rows): array
    {
        $out = [];
        foreach ($rows as $mode => $aliases) {
            $modeKey = strtolower(trim((string)$mode));
            if (!in_array($modeKey, ['rail', 'ferry', 'bus', 'air'], true)) {
                continue;
            }
            if (!is_array($aliases)) {
                continue;
            }
            foreach ($aliases as $alias => $norm) {
                $aliasKey = trim((string)$alias);
                $normVal = trim((string)$norm);
                if ($aliasKey === '' || $normVal === '') {
                    continue;
                }
                $out[$modeKey][$aliasKey] = $normVal;
            }
        }

        return $out;
    }

    private function buildOperatorKey(string $name): string
    {
        $key = mb_strtolower(trim($name), 'UTF-8');
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key) ?? $key;
        return trim($key, '_') !== '' ? trim($key, '_') : 'operator';
    }

    /** Country code => Country name (basic map) */
    public function getCountries(): array
    {
        return [
            'AT'=>'Austria','BE'=>'Belgium','BG'=>'Bulgaria','CY'=>'Cyprus','CZ'=>'Czechia','DE'=>'Germany','DK'=>'Denmark','EE'=>'Estonia','ES'=>'Spain','FI'=>'Finland','FR'=>'France','GR'=>'Greece','HR'=>'Croatia','HU'=>'Hungary','IE'=>'Ireland','IT'=>'Italy','LT'=>'Lithuania','LU'=>'Luxembourg','LV'=>'Latvia','MT'=>'Malta','NL'=>'Netherlands','PL'=>'Poland','PT'=>'Portugal','RO'=>'Romania','SE'=>'Sweden','SI'=>'Slovenia','SK'=>'Slovakia','CH'=>'Switzerland','GB'=>'United Kingdom'
        ];
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    /**
     * Country => operators (name=>name) built from JSON.
     * Pass `null` to include all modes; default remains rail to preserve existing callers.
     */
    public function getOperators(?string $mode = 'rail'): array
    {
        $out = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
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

    /**
     * Operator => products list built from JSON.
     * Pass `null` to include all modes; default remains rail to preserve existing callers.
     */
    public function getProducts(?string $mode = 'rail'): array
    {
        $out = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
            $name = (string)($op['name'] ?? '');
            if ($name) {
                $out[$name] = array_values((array)($op['products'] ?? []));
            }
        }
        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getOperatorsDetailed(?string $mode = null): array
    {
        $out = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
            $out[] = $op;
        }
        return $out;
    }

    /** @return array{key:string,name:string,country:string}|null */
    public function findOperator(string $text, ?string $mode = null): ?array
    {
        $hay = (string)$text;
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
            $names = array_unique(array_merge([(string)$op['name']], (array)$op['aliases']));
            foreach ($names as $nRaw) {
                $n = trim((string)$nRaw);
                // Skip overly short aliases (e.g., 'es') to avoid massive false positives
                if (mb_strlen($n) < 3) { continue; }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($n, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $hay)) {
                    return [
                        'key' => (string)($op['key'] ?? $this->buildOperatorKey((string)$op['name'])),
                        'name' => (string)$op['name'],
                        'country' => strtoupper((string)$op['country']),
                    ];
                }
            }
        }
        return null;
    }

    /** @return string|null */
    public function findProduct(string $text, ?string $mode = null): ?string
    {
        $hay = (string)$text;
        $products = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
            foreach ((array)$op['products'] as $pRaw) {
                $p = trim((string)$pRaw);
                if ($p !== '') {
                    $products[$p] = $p;
                }
            }
        }
        uasort($products, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        // First pass: strict word-boundary matches for full product names, longest first.
        foreach ($products as $product) {
            if (mb_strlen($product) < 2) { continue; }
            $pattern = '/(?<![A-Za-z0-9])' . preg_quote($product, '/') . '(?![A-Za-z0-9])/iu';
            if (preg_match($pattern, $hay)) {
                return $product;
            }
        }

        $aliases = $this->productAliases;
        if ($mode !== null && $mode !== '') {
            $modeKey = strtolower($mode);
            if (!empty($this->productAliasesByMode[$modeKey])) {
                $aliases = array_merge($this->productAliasesByMode[$modeKey], $aliases);
            }
        }
        uksort($aliases, static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        // Second pass: alias map with boundaries; skip extremely short aliases to avoid noise.
        foreach ($aliases as $aliasRaw => $norm) {
            $alias = trim((string)$aliasRaw);
            if (mb_strlen($alias) < 3) { continue; }
            $pattern = '/(?<![A-Za-z0-9])' . preg_quote($alias, '/') . '(?![A-Za-z0-9])/iu';
            if (!preg_match($pattern, $hay)) {
                continue;
            }
            if ($mode !== null && $mode !== '' && !$this->productBelongsToMode((string)$norm, $mode)) {
                continue;
            }
            return (string)$norm;
        }
        return null;
    }

    /**
     * Find the operator that owns a given product name.
     * @return array{name:string,country:string}|null
     */
    public function findOperatorByProduct(string $product, ?string $mode = null): ?array
    {
        $pNeedle = trim($product);
        if ($pNeedle === '') { return null; }
        $matches = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }
            $prods = array_map(fn($p) => (string)$p, (array)($op['products'] ?? []));
            foreach ($prods as $p) {
                if (mb_strtolower($p) === mb_strtolower($pNeedle)) {
                    $matches[] = [
                        'key' => (string)($op['key'] ?? $this->buildOperatorKey((string)$op['name'])),
                        'name' => (string)$op['name'],
                        'country' => strtoupper((string)$op['country'] ?? ''),
                    ];
                    break;
                }
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function getDowngradePolicies(): array
    {
        return $this->downgradePolicies;
    }

    /**
     * @return array<string,mixed>
     */
    public function getModePolicies(): array
    {
        return $this->modePolicies;
    }

    /**
     * @param array{mode?:string} $operator
     */
    private function modeMatches(array $operator, ?string $mode): bool
    {
        if ($mode === null || $mode === '') {
            return true;
        }

        return strtolower((string)($operator['mode'] ?? '')) === strtolower($mode);
    }

    private function productBelongsToMode(string $product, string $mode): bool
    {
        foreach ($this->operators as $operator) {
            if (!$this->modeMatches($operator, $mode)) {
                continue;
            }
            foreach ((array)($operator['products'] ?? []) as $candidate) {
                if (mb_strtolower((string)$candidate) === mb_strtolower($product)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $codes
     * @return array<string,string>
     */
    private function normalizeCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $type => $value) {
            $codeType = strtolower(trim((string)$type));
            $codeValue = strtoupper(trim((string)$value));
            if ($codeType === '' || $codeValue === '') {
                continue;
            }
            $out[$codeType] = $codeValue;
        }

        return $out;
    }

    /**
     * Match operators by authoritative code values.
     *
     * @return array{key:string,name:string,country:string}|null
     */
    public function findByCode(string $code, ?string $mode = null, ?string $codeType = null): ?array
    {
        $needle = strtoupper(trim($code));
        if ($needle === '') {
            return null;
        }

        $wantedType = strtolower(trim((string)$codeType));
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }

            $codes = (array)($op['codes'] ?? []);
            foreach ($codes as $type => $value) {
                $typeKey = strtolower(trim((string)$type));
                if ($wantedType !== '' && $typeKey !== $wantedType) {
                    continue;
                }
                if (strtoupper(trim((string)$value)) !== $needle) {
                    continue;
                }

                return [
                    'key' => (string)($op['key'] ?? $this->buildOperatorKey((string)$op['name'])),
                    'name' => (string)$op['name'],
                    'country' => strtoupper((string)$op['country']),
                ];
            }
        }

        return null;
    }

    private function extractAviationDesignator(string $text): ?string
    {
        $hay = strtoupper(trim($text));
        if ($hay === '') {
            return null;
        }

        if (preg_match('/\b([A-Z0-9]{2})(\d{2,5})\b/', $hay, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Match by code, legal entity, operating carrier, brand group or aliases.
     *
     * @return array{key:string,name:string,country:string,match_type:string}|null
     */
    public function findIdentity(string $text, ?string $mode = null): ?array
    {
        $needle = trim($text);
        if ($needle === '') {
            return null;
        }

        if (strtolower((string)$mode) === 'air' && ($flight = $this->extractAviationDesignator($needle)) !== null) {
            if (($byFlight = $this->findByCode($flight, $mode, 'iata')) !== null) {
                $byFlight['match_type'] = 'code';
                return $byFlight;
            }
        }

        if (($byCode = $this->findByCode($needle, $mode)) !== null) {
            $byCode['match_type'] = 'code';
            return $byCode;
        }

        $terms = [];
        foreach ($this->operators as $op) {
            if (!$this->modeMatches($op, $mode)) {
                continue;
            }

            $terms = [
                (string)$op['name'],
                (string)($op['display_name'] ?? ''),
                (string)($op['brand_group'] ?? ''),
                (string)($op['legal_entity_name'] ?? ''),
                (string)($op['operating_carrier_name'] ?? ''),
            ];

            $terms = array_merge($terms, (array)($op['aliases'] ?? []));
            $codes = (array)($op['codes'] ?? []);
            foreach ($codes as $type => $value) {
                $terms[] = (string)$type;
                $terms[] = (string)$value;
            }

            foreach ($terms as $candidate) {
                $candidate = trim((string)$candidate);
                if ($candidate === '') {
                    continue;
                }
                if (mb_strlen($candidate) < 3) {
                    if (mb_strtolower($needle) === mb_strtolower($candidate)) {
                        return [
                            'key' => (string)($op['key'] ?? $this->buildOperatorKey((string)$op['name'])),
                            'name' => (string)$op['name'],
                            'country' => strtoupper((string)$op['country']),
                            'match_type' => 'short_code',
                        ];
                    }
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($candidate, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $needle)) {
                    return [
                        'key' => (string)($op['key'] ?? $this->buildOperatorKey((string)$op['name'])),
                        'name' => (string)$op['name'],
                        'country' => strtoupper((string)$op['country']),
                        'match_type' => 'identity',
                    ];
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
