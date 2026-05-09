<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class TransportNodeImportService
{
    private string $targetPath;

    public function __construct(?string $targetPath = null)
    {
        $this->targetPath = $targetPath ?: TransportDataPaths::transportNodes();
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
        (new TransportNodeSearchIndexBuilder())->build($this->targetPath, TransportDataPaths::nodesSearchDir());

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

        if (isset($decoded['elements']) && is_array($decoded['elements'])) {
            $decoded = $decoded['elements'];
        }
        if (!array_is_list($decoded)) {
            throw new RuntimeException('JSON source must decode to an array of rows');
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
        $row = $this->transformRow($row, $options);
        if ($row === null) {
            return null;
        }

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

        foreach ([
            'iata_code',
            'icao_code',
            'timezone',
            'airport_type',
            'is_civil',
            'is_military',
            'is_joint_use',
            'is_cargo_only',
            'has_scheduled_passenger_service',
            'has_charter_passenger_service',
            'is_international',
            'is_domestic_only',
            'is_private_use',
            'is_public_use',
            'is_active',
            'is_seasonal',
            'is_closed',
            'in_eu261_departure_scope',
            'likely_third_country_origin',
            'allow_in_frontend_search',
            'allow_in_claim_flow',
            'allow_as_alternative_airport',
            'lookup_priority',
            'needs_manual_review',
        ] as $extraKey) {
            if (array_key_exists($extraKey, $row) && $row[$extraKey] !== null && $row[$extraKey] !== '') {
                $normalized[$extraKey] = $row[$extraKey];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    private function transformRow(array $row, array $options): ?array
    {
        $transform = strtolower(trim((string)($options['source_transform'] ?? '')));
        if ($transform === '') {
            return $row;
        }

        return match ($transform) {
            'air_ourairports' => $this->transformOurAirportsAirRow($row),
            'unlocode_ports' => $this->transformUnlocodePortRow($row),
            'osm_elements_terminals' => $this->transformOsmTerminalRow($row, $options),
            'osm_elements_bus_nodes' => $this->transformOsmBusNodeRow($row, $options),
            default => $row,
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function transformUnlocodePortRow(array $row): ?array
    {
        $country = strtoupper(trim((string)($row['Country'] ?? '')));
        $location = strtoupper(trim((string)($row['Location'] ?? '')));
        $name = trim((string)($row['Name'] ?? ''));
        $function = trim((string)($row['Function'] ?? ''));
        if ($country === '' || $location === '' || $name === '') {
            return null;
        }
        if (strpos($function, '1') === false) {
            return null;
        }

        [$lat, $lon] = $this->parseUnlocodeCoordinates((string)($row['Coordinates'] ?? ''));
        $nameWoDiacritics = trim((string)($row['NameWoDiacritics'] ?? ''));
        $aliases = [];
        if ($nameWoDiacritics !== '' && mb_strtolower($nameWoDiacritics) !== mb_strtolower($name)) {
            $aliases[] = $nameWoDiacritics;
        }

        $nodeType = 'port';
        if (strpos($function, '8') !== false) {
            $nodeType = 'ferry_terminal';
        }

        return [
            'name' => $name,
            'country' => $country,
            'locode' => $country . $location,
            'lat' => $lat,
            'lon' => $lon,
            'city' => $name,
            'node_type' => $nodeType,
            'aliases' => $aliases,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    private function transformOsmTerminalRow(array $row, array $options): ?array
    {
        $tags = isset($row['tags']) && is_array($row['tags']) ? $row['tags'] : [];
        $amenity = strtolower(trim((string)($tags['amenity'] ?? '')));
        $publicTransport = strtolower(trim((string)($tags['public_transport'] ?? '')));
        $ferry = strtolower(trim((string)($tags['ferry'] ?? '')));
        if ($amenity !== 'ferry_terminal' && $publicTransport !== 'station' && $ferry !== 'yes') {
            return null;
        }

        $name = trim((string)($tags['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $country = strtoupper(trim((string)($tags['addr:country'] ?? $tags['is_in:country_code'] ?? '')));
        if ($country === '' && !empty($options['default_country'])) {
            $country = strtoupper(trim((string)$options['default_country']));
        }

        $lat = $this->asFloat($row['lat'] ?? ($row['center']['lat'] ?? null));
        $lon = $this->asFloat($row['lon'] ?? ($row['center']['lon'] ?? null));
        $code = trim((string)($tags['ref'] ?? ''));
        if ($code === '') {
            $code = 'OSM-' . (string)($row['type'] ?? 'node') . '-' . (string)($row['id'] ?? '');
        }

        $aliases = [];
        foreach (['name:en', 'official_name', 'short_name'] as $aliasKey) {
            $alias = trim((string)($tags[$aliasKey] ?? ''));
            if ($alias !== '' && mb_strtolower($alias) !== mb_strtolower($name)) {
                $aliases[] = $alias;
            }
        }

        $city = trim((string)($tags['addr:city'] ?? $tags['is_in:city'] ?? ''));
        $parentName = trim((string)($tags['harbour'] ?? $tags['port'] ?? ''));

        return [
            'name' => $name,
            'country' => $country,
            'code' => $code,
            'lat' => $lat,
            'lon' => $lon,
            'city' => $city,
            'node_type' => 'ferry_terminal',
            'parent_name' => $parentName,
            'aliases' => $aliases,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $options
     * @return array<string,mixed>|null
     */
    private function transformOsmBusNodeRow(array $row, array $options): ?array
    {
        $tags = isset($row['tags']) && is_array($row['tags']) ? $row['tags'] : [];
        $amenity = strtolower(trim((string)($tags['amenity'] ?? '')));
        $publicTransport = strtolower(trim((string)($tags['public_transport'] ?? '')));
        $highway = strtolower(trim((string)($tags['highway'] ?? '')));
        if ($amenity !== 'bus_station' && $publicTransport !== 'station' && $publicTransport !== 'platform' && $highway !== 'bus_stop') {
            return null;
        }

        $name = trim((string)($tags['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $country = strtoupper(trim((string)($tags['addr:country'] ?? $tags['is_in:country_code'] ?? '')));
        if ($country === '' && !empty($options['default_country'])) {
            $country = strtoupper(trim((string)$options['default_country']));
        }

        $lat = $this->asFloat($row['lat'] ?? ($row['center']['lat'] ?? null));
        $lon = $this->asFloat($row['lon'] ?? ($row['center']['lon'] ?? null));
        $code = trim((string)($tags['ref'] ?? ''));
        if ($code === '') {
            $code = 'OSM-' . (string)($row['type'] ?? 'node') . '-' . (string)($row['id'] ?? '');
        }

        $aliases = [];
        foreach (['name:en', 'official_name', 'short_name', 'loc_name'] as $aliasKey) {
            $alias = trim((string)($tags[$aliasKey] ?? ''));
            if ($alias !== '' && mb_strtolower($alias) !== mb_strtolower($name)) {
                $aliases[] = $alias;
            }
        }

        $city = trim((string)($tags['addr:city'] ?? $tags['is_in:city'] ?? ''));
        $parentName = trim((string)($tags['operator'] ?? ''));
        $nodeType = ($amenity === 'bus_station' || $publicTransport === 'station') ? 'terminal' : 'stop';

        return [
            'name' => $name,
            'country' => $country,
            'code' => $code,
            'lat' => $lat,
            'lon' => $lon,
            'city' => $city,
            'node_type' => $nodeType,
            'parent_name' => $parentName,
            'aliases' => $aliases,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function transformOurAirportsAirRow(array $row): ?array
    {
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $country = strtoupper(trim((string)($row['iso_country'] ?? ($row['country'] ?? ''))));
        $type = strtolower(trim((string)($row['type'] ?? '')));
        $iata = strtoupper(trim((string)($row['iata_code'] ?? '')));
        $icao = strtoupper(trim((string)($row['icao_code'] ?? ($row['gps_code'] ?? ($row['local_code'] ?? '')))));
        $city = trim((string)($row['municipality'] ?? ($row['city'] ?? '')));
        $timezone = trim((string)($row['tz_database_timezone'] ?? ($row['timezone'] ?? '')));
        $scheduledService = $this->asBool($row['scheduled_service'] ?? null);
        $countryInEu = $this->countryInEu($country) === true;

        $isAirportType = in_array($type, ['small_airport', 'medium_airport', 'large_airport'], true);
        $isClosed = $type === 'closed';
        $isPrivateUse = in_array($type, ['heliport', 'seaplane_base', 'balloonport'], true);
        $isCargoOnly = $this->looksCargoOnlyAirportName($name);
        $isMilitary = $this->looksMilitaryAirportName($name);
        $isJointUse = $isMilitary && $this->looksJointUseAirportName($name);
        $isCivil = !$isMilitary || $isJointUse;
        $isPublicUse = !$isClosed && !$isPrivateUse;
        $isActive = !$isClosed;
        $isSeasonal = strtolower(trim((string)($row['seasonal'] ?? ''))) === 'yes';
        $hasScheduledPassengerService = $scheduledService ?? ($iata !== '' && $isAirportType && !$isCargoOnly);

        $allowInClaimFlow = $isCivil
            && $isActive
            && $isPublicUse
            && $hasScheduledPassengerService
            && !$isCargoOnly;

        if ($type === 'small_airport' && !$countryInEu && $scheduledService !== true) {
            $allowInClaimFlow = false;
        }

        $allowInFrontendSearch = $allowInClaimFlow;
        $allowAsAlternativeAirport = $allowInClaimFlow;
        $needsManualReview = $isJointUse || $scheduledService === null;
        $lookupPriority = 0;
        if ($allowInFrontendSearch) {
            if ($isJointUse) {
                $lookupPriority = 30;
            } elseif ($type === 'large_airport') {
                $lookupPriority = 100;
            } elseif ($countryInEu) {
                $lookupPriority = 80;
            } else {
                $lookupPriority = 60;
            }
        }

        return [
            'name' => $name,
            'country' => $country,
            'code' => $iata !== '' ? $iata : $icao,
            'iata_code' => $iata !== '' ? $iata : null,
            'icao_code' => $icao !== '' ? $icao : null,
            'lat' => $this->asFloat($row['latitude_deg'] ?? ($row['lat'] ?? null)),
            'lon' => $this->asFloat($row['longitude_deg'] ?? ($row['lon'] ?? null)),
            'city' => $city,
            'node_type' => 'airport',
            'timezone' => $timezone !== '' ? $timezone : null,
            'airport_type' => $type !== '' ? $type : null,
            'is_civil' => $isCivil,
            'is_military' => $isMilitary,
            'is_joint_use' => $isJointUse,
            'is_cargo_only' => $isCargoOnly,
            'has_scheduled_passenger_service' => $hasScheduledPassengerService,
            'has_charter_passenger_service' => null,
            'is_international' => in_array($type, ['medium_airport', 'large_airport'], true),
            'is_domestic_only' => $type === 'small_airport' ? true : null,
            'is_private_use' => $isPrivateUse,
            'is_public_use' => $isPublicUse,
            'is_active' => $isActive,
            'is_seasonal' => $isSeasonal,
            'is_closed' => $isClosed,
            'in_eu261_departure_scope' => $countryInEu,
            'likely_third_country_origin' => $country !== '' && !$countryInEu,
            'allow_in_frontend_search' => $allowInFrontendSearch,
            'allow_in_claim_flow' => $allowInClaimFlow,
            'allow_as_alternative_airport' => $allowAsAlternativeAirport,
            'lookup_priority' => $lookupPriority,
            'needs_manual_review' => $needsManualReview,
        ];
    }

    /**
     * @return array{0:?float,1:?float}
     */
    private function parseUnlocodeCoordinates(string $raw): array
    {
        $value = strtoupper(trim($raw));
        if (!preg_match('/^(\d{2})(\d{2})([NS])\s+(\d{3})(\d{2})([EW])$/', $value, $m)) {
            return [null, null];
        }

        $lat = ((int)$m[1]) + (((int)$m[2]) / 60);
        $lon = ((int)$m[4]) + (((int)$m[5]) / 60);
        if ($m[3] === 'S') {
            $lat *= -1;
        }
        if ($m[6] === 'W') {
            $lon *= -1;
        }

        return [round($lat, 6), round($lon, 6)];
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

    private function looksMilitaryAirportName(string $name): bool
    {
        $normalized = strtolower(trim((string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)));
        foreach ([
            'air base',
            'airbase',
            'air force base',
            'army air field',
            'army airfield',
            'naval air station',
            'naval air facility',
            'military',
            'militaire',
            ' raf ',
            ' afb ',
            ' nas ',
        ] as $needle) {
            if (str_contains(' ' . $normalized . ' ', ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    private function looksJointUseAirportName(string $name): bool
    {
        $normalized = strtolower(trim((string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)));
        return str_contains($normalized, 'airport')
            || str_contains($normalized, 'international')
            || str_contains($normalized, 'civil');
    }

    private function looksCargoOnlyAirportName(string $name): bool
    {
        $normalized = strtolower(trim((string)(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)));
        foreach ([
            'cargo',
            'freight',
            'logistics',
            'parcel hub',
            'distribution center',
        ] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
