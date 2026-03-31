<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class OperatorCatalogImportService
{
    private string $catalogPath;

    public function __construct(?string $catalogPath = null)
    {
        $root = dirname(__DIR__, 2);
        $this->catalogPath = $catalogPath ?? ($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json');
    }

    /**
     * @param array<string,mixed> $options
     * @return array{source:string,format:string,added:int,updated:int,total:int}
     */
    public function import(string $sourcePath, array $options = []): array
    {
        if (!is_file($sourcePath)) {
            throw new RuntimeException('source file not found: ' . $sourcePath);
        }

        $format = strtolower(trim((string)($options['format'] ?? 'auto')));
        if ($format === 'auto') {
            $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $format = $ext === 'csv' ? 'csv' : 'json';
        }
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new RuntimeException('format must be auto, csv or json');
        }

        $catalog = $this->loadCatalog();
        $operators = (array)($catalog['operators'] ?? []);
        $indexed = [];
        foreach ($operators as $idx => $operator) {
            if (!is_array($operator)) {
                continue;
            }
            $key = $this->operatorKey((array)$operator);
            if ($key === '') {
                continue;
            }
            $indexed[$key] = $idx;
        }

        $rows = $format === 'csv'
            ? $this->readCsv($sourcePath, $options)
            : $this->readJson($sourcePath, $options);

        $added = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                continue;
            }

            $key = $this->operatorKey($normalized);
            if ($key === '') {
                continue;
            }

            if (isset($indexed[$key])) {
                $operators[$indexed[$key]] = $this->mergeOperator((array)$operators[$indexed[$key]], $normalized);
                $updated++;
            } else {
                $operators[] = $normalized;
                $indexed[$key] = array_key_last($operators);
                $added++;
            }
        }

        usort($operators, static function ($a, $b): int {
            $aMode = strtolower((string)($a['mode'] ?? ''));
            $bMode = strtolower((string)($b['mode'] ?? ''));
            if ($aMode !== $bMode) {
                return $aMode <=> $bMode;
            }
            return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $catalog['operators'] = array_values($operators);
        $catalog['updated_at'] = gmdate('c');
        $this->writeCatalog($catalog);

        return [
            'source' => $sourcePath,
            'format' => $format,
            'added' => $added,
            'updated' => $updated,
            'total' => count($operators),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function template(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['air', 'bus', 'ferry', 'rail'], true)) {
            throw new RuntimeException('mode must be air, bus, ferry or rail');
        }

        $headers = [
            'operator_key',
            'mode',
            'display_name',
            'country_code',
            'brand_group',
            'legal_entity_name',
            'operating_carrier_name',
            'aliases',
            'products',
            'service_type',
            'claim_url',
            'support_url',
            'source_urls',
            'source_confidence',
            'codes_json',
            'routes_json',
            'ports_json',
        ];

        return [
            ['_headers' => $headers],
            ['operator_key' => '', 'mode' => $mode, 'display_name' => '', 'country_code' => '', 'brand_group' => '', 'legal_entity_name' => '', 'operating_carrier_name' => '', 'aliases' => '', 'products' => '', 'service_type' => '', 'claim_url' => '', 'support_url' => '', 'source_urls' => '', 'source_confidence' => '', 'codes_json' => '', 'routes_json' => '', 'ports_json' => ''],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readJson(string $sourcePath, array $options): array
    {
        $raw = (string)file_get_contents($sourcePath);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON source must decode to an array');
        }

        if (isset($decoded['operators']) && is_array($decoded['operators'])) {
            $decoded = $decoded['operators'];
        }
        if (!array_is_list($decoded)) {
            throw new RuntimeException('JSON source must decode to a list of operator rows');
        }

        return array_values(array_filter($decoded, static fn($row): bool => is_array($row)));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readCsv(string $sourcePath, array $options): array
    {
        $fh = fopen($sourcePath, 'rb');
        if ($fh === false) {
            throw new RuntimeException('could not open CSV source');
        }

        $delimiter = (string)($options['delimiter'] ?? ',');
        $headers = fgetcsv($fh, 0, $delimiter, '"', '\\');
        if (!is_array($headers)) {
            fclose($fh);
            throw new RuntimeException('CSV source is missing a header row');
        }
        $headers = array_map(static fn($v): string => trim((string)$v), $headers);

        $rows = [];
        while (($data = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false) {
            $assoc = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = isset($data[$idx]) ? trim((string)$data[$idx]) : '';
            }
            $rows[] = $assoc;
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function normalizeRow(array $row): ?array
    {
        $mode = strtolower(trim((string)($row['mode'] ?? '')));
        $name = trim((string)($row['display_name'] ?? $row['name'] ?? ''));
        $operatorKey = trim((string)($row['operator_key'] ?? ''));
        if ($mode === '' || $name === '') {
            return null;
        }
        if (!in_array($mode, ['air', 'bus', 'ferry', 'rail'], true)) {
            return null;
        }
        if ($operatorKey === '') {
            $operatorKey = $this->slug($name);
        }

        $op = [
            'operator_key' => $operatorKey,
            'mode' => $mode,
            'name' => $name,
            'display_name' => $name,
            'country_code' => strtoupper(trim((string)($row['country_code'] ?? $row['country'] ?? ''))),
            'aliases' => $this->splitList((string)($row['aliases'] ?? '')),
            'products' => $this->splitList((string)($row['products'] ?? '')),
            'service_type' => trim((string)($row['service_type'] ?? '')),
            'claim_url' => trim((string)($row['claim_url'] ?? '')),
            'support_url' => trim((string)($row['support_url'] ?? '')),
            'source_urls' => $this->splitList((string)($row['source_urls'] ?? '')),
            'source_confidence' => trim((string)($row['source_confidence'] ?? '')),
            'brand_group' => trim((string)($row['brand_group'] ?? '')),
            'legal_entity_name' => trim((string)($row['legal_entity_name'] ?? '')),
            'operating_carrier_name' => trim((string)($row['operating_carrier_name'] ?? '')),
            'codes' => [],
            'metadata' => [],
        ];

        $codesJson = trim((string)($row['codes_json'] ?? ''));
        if ($codesJson !== '') {
            $decoded = json_decode($codesJson, true);
            if (is_array($decoded)) {
                $op['codes'] = $this->normalizeCodes($decoded);
            }
        }

        $routesJson = trim((string)($row['routes_json'] ?? ''));
        if ($routesJson !== '') {
            $decoded = json_decode($routesJson, true);
            if (is_array($decoded)) {
                $op['routes'] = $decoded;
            }
        }

        $portsJson = trim((string)($row['ports_json'] ?? ''));
        if ($portsJson !== '') {
            $decoded = json_decode($portsJson, true);
            if (is_array($decoded)) {
                $op['ports'] = $decoded;
            }
        }

        if ($op['codes'] === [] && $mode === 'air') {
            $iata = trim((string)($row['iata'] ?? ''));
            $icao = trim((string)($row['icao'] ?? ''));
            if ($iata !== '') {
                $op['codes']['iata'] = strtoupper($iata);
            }
            if ($icao !== '') {
                $op['codes']['icao'] = strtoupper($icao);
            }
        }
        if ($op['codes'] === [] && $mode === 'rail') {
            $rics = trim((string)($row['rics_code'] ?? ''));
            $era = trim((string)($row['era_org_code'] ?? ''));
            if ($rics !== '') {
                $op['codes']['rics'] = strtoupper($rics);
            }
            if ($era !== '') {
                $op['codes']['era_org_code'] = strtoupper($era);
            }
        }
        if ($op['codes'] === [] && $mode === 'bus') {
            $lic = trim((string)($row['community_licence_ref'] ?? ''));
            if ($lic !== '') {
                $op['codes']['community_licence_ref'] = strtoupper($lic);
            }
        }

        return $op;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function mergeOperator(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($key === 'aliases' || $key === 'products' || $key === 'source_urls') {
                $base[$key] = array_values(array_unique(array_merge((array)($base[$key] ?? []), (array)$value)));
                continue;
            }
            if ($key === 'codes') {
                $base[$key] = $this->mergeCodes((array)($base[$key] ?? []), (array)$value);
                continue;
            }
            if ($key === 'routes' || $key === 'ports') {
                $base[$key] = is_array($value) ? $value : [];
                continue;
            }
            if ($value !== '' && $value !== [] && $value !== null) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param array<string,mixed> $codes
     * @return array<string,string>
     */
    private function normalizeCodes(array $codes): array
    {
        $out = [];
        foreach ($codes as $type => $value) {
            $type = strtolower(trim((string)$type));
            $value = strtoupper(trim((string)$value));
            if ($type === '' || $value === '') {
                continue;
            }
            $out[$type] = $value;
        }

        return $out;
    }

    /**
     * @param array<string,string> $base
     * @param array<string,string> $incoming
     * @return array<string,string>
     */
    private function mergeCodes(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            $key = strtolower(trim((string)$key));
            $value = strtoupper(trim((string)$value));
            if ($key === '' || $value === '') {
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    /**
     * @param array<string,mixed> $catalog
     */
    private function writeCatalog(array $catalog): void
    {
        $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('could not encode catalog JSON');
        }
        file_put_contents($this->catalogPath, $json . PHP_EOL);
    }

    /**
     * @return array<string,mixed>
     */
    private function loadCatalog(): array
    {
        if (!is_file($this->catalogPath)) {
            return ['schema_version' => 2, 'operators' => [], 'product_aliases_by_mode' => []];
        }

        $raw = (string)file_get_contents($this->catalogPath);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('invalid catalog JSON');
        }

        return $decoded;
    }

    private function operatorKey(array $operator): string
    {
        $key = trim((string)($operator['operator_key'] ?? ''));
        if ($key !== '') {
            return strtolower($key);
        }

        $name = trim((string)($operator['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        return $this->slug($name);
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '_', $value) ?? $value;
        return trim($value, '_');
    }

    /**
     * @return array<int,string>
     */
    private function splitList(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[|;,]/', $value) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn(string $v): bool => $v !== ''));
        return array_values(array_unique($parts));
    }
}
