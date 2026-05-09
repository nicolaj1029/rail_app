<?php
declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class TransportNodeSearchIndexBuilder
{
    /**
     * @return array<string,int>
     */
    public function build(?string $sourcePath = null, ?string $outputDir = null): array
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

            $grouped[$mode][] = [
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
                'iata_code' => isset($row['iata_code']) ? (string)$row['iata_code'] : null,
                'icao_code' => isset($row['icao_code']) ? (string)$row['icao_code'] : null,
                'timezone' => isset($row['timezone']) ? (string)$row['timezone'] : null,
                'airport_type' => isset($row['airport_type']) ? (string)$row['airport_type'] : null,
                'is_civil' => array_key_exists('is_civil', $row) ? (bool)$row['is_civil'] : null,
                'is_military' => array_key_exists('is_military', $row) ? (bool)$row['is_military'] : null,
                'is_joint_use' => array_key_exists('is_joint_use', $row) ? (bool)$row['is_joint_use'] : null,
                'is_cargo_only' => array_key_exists('is_cargo_only', $row) ? (bool)$row['is_cargo_only'] : null,
                'has_scheduled_passenger_service' => array_key_exists('has_scheduled_passenger_service', $row) ? (bool)$row['has_scheduled_passenger_service'] : null,
                'is_private_use' => array_key_exists('is_private_use', $row) ? (bool)$row['is_private_use'] : null,
                'is_public_use' => array_key_exists('is_public_use', $row) ? (bool)$row['is_public_use'] : null,
                'is_active' => array_key_exists('is_active', $row) ? (bool)$row['is_active'] : null,
                'is_closed' => array_key_exists('is_closed', $row) ? (bool)$row['is_closed'] : null,
                'allow_in_frontend_search' => array_key_exists('allow_in_frontend_search', $row) ? (bool)$row['allow_in_frontend_search'] : null,
                'allow_in_claim_flow' => array_key_exists('allow_in_claim_flow', $row) ? (bool)$row['allow_in_claim_flow'] : null,
                'allow_as_alternative_airport' => array_key_exists('allow_as_alternative_airport', $row) ? (bool)$row['allow_as_alternative_airport'] : null,
                'lookup_priority' => isset($row['lookup_priority']) ? (int)$row['lookup_priority'] : null,
                'needs_manual_review' => array_key_exists('needs_manual_review', $row) ? (bool)$row['needs_manual_review'] : null,
            ];
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
}
