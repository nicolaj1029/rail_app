<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class TransportNodeImportService
{
    private string $targetPath;

    public function __construct(?string $targetPath = null)
    {
        $this->targetPath = $targetPath ?: (CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_nodes.json');
    }

    /**
     * @param array<string,mixed> $options
     * @return array{mode:string,source:string,added:int,updated:int,total:int}
     */
    public function import(string $mode, string $sourcePath, array $options = []): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            throw new RuntimeException('mode must be ferry, bus or air');
        }
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

        $existing = $this->loadTargetRows();
        $replace = !empty($options['replace']);
        if ($replace) {
            $existing = array_values(array_filter(
                $existing,
                static fn(array $row): bool => strtolower((string)($row['mode'] ?? '')) !== $mode
            ));
        }

        $incoming = $format === 'csv'
            ? $this->readCsv($mode, $sourcePath, $options)
            : $this->readJson($mode, $sourcePath, $options);

        $indexed = [];
        foreach ($existing as $row) {
            $indexed[(string)($row['id'] ?? '')] = $row;
        }

        $added = 0;
        $updated = 0;
        foreach ($incoming as $row) {
            $id = (string)($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (isset($indexed[$id])) {
                $indexed[$id] = $row;
                $updated++;
            } else {
                $indexed[$id] = $row;
                $added++;
            }
        }

        $rows = array_values($indexed);
        usort($rows, static function (array $a, array $b): int {
            $modeCmp = strcmp((string)($a['mode'] ?? ''), (string)($b['mode'] ?? ''));
            if ($modeCmp !== 0) {
                return $modeCmp;
            }
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('could not encode target JSON');
        }
        file_put_contents($this->targetPath, $json . PHP_EOL);

        return [
            'mode' => $mode,
            'source' => $sourcePath,
            'added' => $added,
            'updated' => $updated,
            'total' => count($rows),
        ];
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    private function readJson(string $mode, string $sourcePath, array $options): array
    {
        $raw = (string)file_get_contents($sourcePath);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON source must decode to an array');
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeRow($mode, $row, $options);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<int,array<string,mixed>>
     */
    private function readCsv(string $mode, string $sourcePath, array $options): array
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
            $normalized = $this->normalizeRow($mode, $assoc, $options);
            if ($normalized !== null) {
                $rows[] = $normalized;
            }
        }
        fclose($fh);

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    private function normalizeRow(string $mode, array $row, array $options): ?array
    {
        if (!$this->passesFilters($row, $options)) {
            return null;
        }

        $name = trim((string)$this->pick($row, ['name', (string)($options['name_col'] ?? '')]));
        if ($name === '') {
            return null;
        }

        $country = strtoupper(trim((string)$this->pick($row, ['country', (string)($options['country_col'] ?? '')])));
        $nodeType = strtolower(trim((string)$this->pick($row, ['node_type', (string)($options['node_type_col'] ?? ''), (string)($options['default_node_type'] ?? '')])));
        $city = trim((string)$this->pick($row, ['city', (string)($options['city_col'] ?? '')]));
        $parentName = trim((string)$this->pick($row, ['parent_name', (string)($options['parent_col'] ?? '')]));
        $code = trim((string)$this->pick($row, ['code', 'iata_code', 'icao_code', 'locode', (string)($options['code_col'] ?? '')]));
        $source = trim((string)($options['source_label'] ?? ($row['source'] ?? basename($this->targetPath))));
        $lat = $this->asFloat($this->pick($row, ['lat', 'latitude', (string)($options['lat_col'] ?? '')]));
        $lon = $this->asFloat($this->pick($row, ['lon', 'lng', 'longitude', (string)($options['lon_col'] ?? '')]));
        $inEu = $this->asBool($this->pick($row, ['in_eu', (string)($options['in_eu_col'] ?? '')]));
        if ($inEu === null && $country !== '') {
            $inEu = $this->countryInEu($country);
        }

        $aliasesRaw = $this->pick($row, ['aliases', (string)($options['aliases_col'] ?? '')]);
        $aliases = [];
        if (is_array($aliasesRaw)) {
            foreach ($aliasesRaw as $alias) {
                $alias = trim((string)$alias);
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        } elseif (is_string($aliasesRaw) && trim($aliasesRaw) !== '') {
            foreach (preg_split('/[|;,]/', $aliasesRaw) ?: [] as $alias) {
                $alias = trim((string)$alias);
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        }

        $id = trim((string)($row['id'] ?? ''));
        if ($id === '') {
            $id = $this->slug($mode . '-' . ($code !== '' ? $code : $name));
        }

        $normalized = [
            'id' => $id,
            'mode' => $mode,
            'node_type' => $nodeType !== '' ? $nodeType : $this->defaultNodeType($mode),
            'name' => $name,
            'country' => $country,
            'in_eu' => $inEu,
            'code' => $code !== '' ? $code : null,
            'lat' => $lat,
            'lon' => $lon,
            'source' => $source !== '' ? $source : 'import',
        ];

        if ($parentName !== '') {
            $normalized['parent_name'] = $parentName;
        }
        if ($city !== '') {
            $normalized['city'] = $city;
        }
        if ($aliases !== []) {
            $normalized['aliases'] = array_values(array_unique($aliases));
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $options
     */
    private function passesFilters(array $row, array $options): bool
    {
        if (!empty($options['require_code'])) {
            $code = trim((string)$this->pick($row, ['code', 'iata_code', 'icao_code', 'locode', (string)($options['code_col'] ?? '')]));
            if ($code === '') {
                return false;
            }
        }

        $filterCol = trim((string)($options['filter_col'] ?? ''));
        $filterAllow = $options['filter_allow'] ?? [];
        if ($filterCol !== '' && is_array($filterAllow) && $filterAllow !== []) {
            $value = strtolower(trim((string)($row[$filterCol] ?? '')));
            $allowed = array_map(
                static fn($item): string => strtolower(trim((string)$item)),
                $filterAllow
            );
            if ($value === '' || !in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadTargetRows(): array
    {
        if (!is_file($this->targetPath)) {
            return [];
        }
        $raw = (string)file_get_contents($this->targetPath);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $keys
     * @return mixed
     */
    private function pick(array $row, array $keys)
    {
        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) {
                return $row[$key];
            }
        }
        return null;
    }

    /**
     * @param mixed $value
     */
    private function asFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = str_replace(',', '.', trim((string)$value));
        if (!is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }

    /**
     * @param mixed $value
     */
    private function asBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = strtolower(trim((string)$value));
        if (in_array($value, ['1', 'true', 'yes', 'ja', 'y'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'nej', 'n'], true)) {
            return false;
        }
        return null;
    }

    private function defaultNodeType(string $mode): string
    {
        return match ($mode) {
            'air' => 'airport',
            'bus' => 'terminal',
            default => 'port',
        };
    }

    private function slug(string $value): string
    {
        $text = strtolower(trim($value));
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        return trim((string)$text, '-');
    }

    private function countryInEu(string $country): ?bool
    {
        static $eu = [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT',
            'LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
        ];
        if ($country === '') {
            return null;
        }
        return in_array($country, $eu, true);
    }
}
