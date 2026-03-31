<?php
declare(strict_types=1);

namespace App\Service;

final class TransportOperatorRegistry
{
    private const CATALOG_PATH = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json';

    /** @var array<int,array<string,mixed>> */
    private array $operators = [];

    public function __construct(?string $path = null)
    {
        $path = $path ?? self::CATALOG_PATH;
        $data = TransportOperatorsCatalogLoader::load($path);
        if ($data === []) {
            return;
        }

        $this->operators = $this->normalizeOperators((array)($data['operators'] ?? []));
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

            $countryCode = strtoupper(trim((string)($row['country_code'] ?? ($row['country'] ?? ''))));
            $metadata = is_array($row['metadata'] ?? null) ? (array)$row['metadata'] : [];
            $products = array_values(array_filter(
                array_map('strval', (array)($row['products'] ?? ($metadata['products'] ?? []))),
                static fn(string $value): bool => trim($value) !== ''
            ));
            if ($products !== [] && !isset($metadata['products'])) {
                $metadata['products'] = $products;
            }

            $normalized[] = [
                'mode' => $mode,
                'name' => $name,
                'aliases' => array_values(array_filter(
                    array_map('strval', (array)($row['aliases'] ?? [])),
                    static fn(string $value): bool => trim($value) !== ''
                )),
                'country_code' => $countryCode,
                'is_eu_operator' => array_key_exists('is_eu_operator', $row)
                    ? (bool)$row['is_eu_operator']
                    : $this->isEuCountryCode($countryCode),
                'operator_type' => trim((string)($row['operator_type'] ?? $this->defaultOperatorType($mode))),
                'operator_key' => trim((string)($row['operator_key'] ?? $metadata['operator_key'] ?? '')),
                'brand_group' => trim((string)($row['brand_group'] ?? $metadata['brand_group'] ?? '')),
                'legal_entity_name' => trim((string)($row['legal_entity_name'] ?? $metadata['legal_entity_name'] ?? '')),
                'operating_carrier_name' => trim((string)($row['operating_carrier_name'] ?? $metadata['operating_carrier_name'] ?? '')),
                'codes' => is_array($row['codes'] ?? null) ? (array)$row['codes'] : (is_array($metadata['codes'] ?? null) ? (array)$metadata['codes'] : []),
                'metadata' => $metadata,
                'products' => $products,
            ];
        }

        return $normalized;
    }

    private function defaultOperatorType(string $mode): string
    {
        return match ($mode) {
            'rail' => 'railway_undertaking',
            'ferry' => 'ferry_carrier',
            'bus' => 'bus_operator',
            'air' => 'airline',
            default => 'operator',
        };
    }

    private function isEuCountryCode(string $countryCode): bool
    {
        return in_array($countryCode, [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
            'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
            'SI', 'ES', 'SE',
        ], true);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByName(string $mode, string $text): ?array
    {
        $needle = trim($text);
        if ($needle === '') {
            return null;
        }

        $mode = strtolower(trim($mode));
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }
            $names = array_unique(array_merge(
                [(string)($operator['name'] ?? '')],
                array_map('strval', (array)($operator['aliases'] ?? []))
            ));
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                if (mb_strlen($name) < 3) {
                    if (mb_strtolower($needle) === mb_strtolower($name)) {
                        return $operator;
                    }
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($name, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $needle)) {
                    return $operator;
                }
            }
        }

        return null;
    }

    public function deriveCountryCode(string $mode, string $text): ?string
    {
        $match = $this->findByIdentity($mode, $text);
        $country = strtoupper(trim((string)($match['country_code'] ?? '')));

        return $country !== '' ? $country : null;
    }

    public function deriveEuFlag(string $mode, string $text): ?bool
    {
        $match = $this->findByIdentity($mode, $text);
        if ($match === null || !array_key_exists('is_eu_operator', $match)) {
            return null;
        }

        return (bool)$match['is_eu_operator'];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByCode(string $mode, string $code): ?array
    {
        $needle = strtoupper(trim($code));
        if ($needle === '') {
            return null;
        }

        $mode = strtolower(trim($mode));
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }

            $codes = (array)($operator['codes'] ?? []);
            foreach ($codes as $value) {
                if (strtoupper(trim((string)$value)) === $needle) {
                    return $operator;
                }
            }

            foreach (array_keys($codes) as $type) {
                if (strtoupper(trim((string)$type)) === $needle) {
                    return $operator;
                }
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

        if (preg_match('/\b([A-Z0-9]{2})\s*[-\/]?\s*(\d{2,5})\b/', $hay, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByIdentity(string $mode, string $text): ?array
    {
        $needle = trim($text);
        if ($needle === '') {
            return null;
        }

        if (strtolower(trim($mode)) === 'air' && ($flight = $this->extractAviationDesignator($needle)) !== null) {
            if (($match = $this->findByCode($mode, $flight)) !== null) {
                return $match;
            }
        }

        if (($match = $this->findByCode($mode, $needle)) !== null) {
            return $match;
        }

        $mode = strtolower(trim($mode));
        $exactFields = ['operator_key', 'brand_group', 'legal_entity_name', 'operating_carrier_name', 'display_name', 'name'];
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }

            foreach ($exactFields as $field) {
                $candidate = trim((string)($operator[$field] ?? ''));
                if ($candidate === '') {
                    continue;
                }
                if (mb_strtolower($candidate) === mb_strtolower($needle)) {
                    return $operator;
                }
            }
        }

        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }

            $candidates = array_values(array_merge(
                (array)($operator['aliases'] ?? []),
                array_keys((array)($operator['codes'] ?? [])),
                array_values((array)($operator['codes'] ?? []))
            ));

            foreach ($candidates as $candidate) {
                $candidate = trim((string)$candidate);
                if ($candidate === '') {
                    continue;
                }
                if (mb_strlen($candidate) < 3) {
                    if (mb_strtolower($needle) === mb_strtolower($candidate)) {
                        return $operator;
                    }
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($candidate, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $needle)) {
                    return $operator;
                }
            }
        }

        return null;
    }

    public function detectModeByName(string $text): ?string
    {
        $needle = trim($text);
        if ($needle === '') {
            return null;
        }

        $matches = [];
        foreach ($this->operators as $operator) {
            $mode = strtolower(trim((string)($operator['mode'] ?? '')));
            if (!in_array($mode, ['ferry', 'bus', 'air', 'rail'], true)) {
                continue;
            }
            $names = array_unique(array_merge(
                [(string)($operator['name'] ?? '')],
                array_map('strval', (array)($operator['aliases'] ?? []))
            ));
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                if (mb_strlen($name) < 3) {
                    if (mb_strtolower($needle) === mb_strtolower($name)) {
                        $matches[$mode] = true;
                        break;
                    }
                    continue;
                }
                $pattern = '/(?<![A-Za-z0-9])' . preg_quote($name, '/') . '(?![A-Za-z0-9])/iu';
                if (preg_match($pattern, $needle)) {
                    $matches[$mode] = true;
                    break;
                }
            }
        }

        if (count($matches) === 1) {
            return (string)array_key_first($matches);
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    public function namesByMode(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if ($mode === '') {
            return [];
        }

        $names = [];
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }
            $name = trim((string)($operator['name'] ?? ''));
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $out = array_keys($names);
        sort($out, SORT_NATURAL | SORT_FLAG_CASE);

        return $out;
    }

    /**
     * @return array<int,array{name:string,country_code:?string,is_eu_operator:?bool,aliases:array<int,string>}>
     */
    public function entriesByMode(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if ($mode === '') {
            return [];
        }

        $rows = [];
        foreach ($this->operators as $operator) {
            if (strtolower((string)($operator['mode'] ?? '')) !== $mode) {
                continue;
            }

            $name = trim((string)($operator['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'country_code' => ($country = strtoupper(trim((string)($operator['country_code'] ?? '')))) !== '' ? $country : null,
                'is_eu_operator' => array_key_exists('is_eu_operator', $operator) ? (bool)$operator['is_eu_operator'] : null,
                'aliases' => array_values(array_filter(array_map('strval', (array)($operator['aliases'] ?? [])), static fn(string $value): bool => trim($value) !== '')),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $rows;
    }
}
