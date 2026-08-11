<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class TransportNodeSearchIndexBuilder
{
    /**
     * @return array<string,int>
     */
    public function build(?string $sourcePath = null, ?string $outputDir = null, ?bool $enforceAirIntegrity = null): array
    {
        $sourcePath = $sourcePath ?? TransportDataPaths::transportNodes();
        $outputDir = $outputDir ?? TransportDataPaths::nodesSearchDir();

        if (!is_file($sourcePath)) {
            throw new RuntimeException('transport node source not found: ' . $sourcePath);
        }

        $json = file_get_contents($sourcePath);
        if (!is_string($json) || $json === '') {
            throw new RuntimeException('transport node source could not be read');
        }

        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            throw new RuntimeException('transport node source is not valid JSON');
        }

        $grouped = [
            'ferry' => [],
            'bus' => [],
            'air' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mode = strtolower(trim((string)($row['mode'] ?? '')));
            if (!isset($grouped[$mode])) {
                continue;
            }

            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $indexRow = [
                'id' => (string)($row['id'] ?? ''),
                'mode' => $mode,
                'name' => $name,
                'aliases' => array_values(array_filter(array_map('strval', (array)($row['aliases'] ?? [])))),
                'code' => isset($row['code']) ? (string)$row['code'] : null,
                'country' => isset($row['country']) ? strtoupper((string)$row['country']) : null,
                'in_eu' => array_key_exists('in_eu', $row) ? (bool)$row['in_eu'] : null,
                'lat' => isset($row['lat']) ? (float)$row['lat'] : null,
                'lon' => isset($row['lon']) ? (float)$row['lon'] : null,
                'node_type' => isset($row['node_type']) ? (string)$row['node_type'] : null,
                'parent_name' => isset($row['parent_name']) ? (string)$row['parent_name'] : null,
                'city' => isset($row['city']) ? (string)$row['city'] : null,
                'source' => isset($row['source']) ? (string)$row['source'] : null,
            ];

            foreach (['iata_code', 'icao_code', 'timezone', 'airport_type'] as $key) {
                if (isset($row[$key])) {
                    $indexRow[$key] = (string)$row[$key];
                }
            }
            foreach ([
                'is_civil',
                'is_military',
                'is_joint_use',
                'is_cargo_only',
                'has_scheduled_passenger_service',
                'is_private_use',
                'is_public_use',
                'is_active',
                'is_closed',
                'allow_in_frontend_search',
                'allow_in_claim_flow',
                'allow_as_alternative_airport',
                'needs_manual_review',
            ] as $key) {
                if (array_key_exists($key, $row) && $row[$key] !== null) {
                    $indexRow[$key] = (bool)$row[$key];
                }
            }
            if (isset($row['lookup_priority'])) {
                $indexRow['lookup_priority'] = (int)$row['lookup_priority'];
            }

            $grouped[$mode][] = $indexRow;
        }

        $enforceAirIntegrity ??= $this->samePath($outputDir, TransportDataPaths::nodesSearchDir());
        if ($enforceAirIntegrity) {
            AirportSearchDatasetIntegrity::assertValid($grouped['air'], 'generated airport search index');
        }

        $counts = [];
        foreach ($grouped as $mode => $modeRows) {
            $targetPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $mode . '.json';
            $encoded = json_encode($modeRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                throw new RuntimeException('could not encode search index for mode: ' . $mode);
            }
            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0775, true);
            }
            file_put_contents($targetPath, $encoded . PHP_EOL);
            $counts[$mode] = count($modeRows);
        }

        return $counts;
    }

    private function samePath(string $left, string $right): bool
    {
        $normalize = static fn(string $path): string => strtolower(rtrim(str_replace('\\', '/', $path), '/'));

        return $normalize($left) === $normalize($right);
    }
}
