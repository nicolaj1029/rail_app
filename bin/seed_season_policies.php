#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Seed/merge season-policy stubs from transport_operators_catalog.json into season_policy_matrix.json.
 *
 * Usage:
 *   php bin/seed_season_policies.php
 */

$root = dirname(__DIR__);
$operatorsPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json';
$matrixPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'season_policy_matrix.json';

if (!is_file($operatorsPath)) {
    fwrite(STDERR, "Missing operators catalog: $operatorsPath\n");
    exit(2);
}

$opsRaw = file_get_contents($operatorsPath);
if ($opsRaw === false) {
    fwrite(STDERR, "Cannot read: $operatorsPath\n");
    exit(3);
}
$opsJson = json_decode($opsRaw, true);
if (!is_array($opsJson) || !is_array($opsJson['operators'] ?? null)) {
    fwrite(STDERR, "Invalid operators catalog JSON structure.\n");
    exit(4);
}

$matrix = [
    'schema_version' => 1,
    'updated_at' => date('Y-m-d'),
    'notes' => [
        'Art. 19(2) season/period passes follow each operator\'s compensation scheme; there is no EU-wide harmonized formula.',
        'This file is a policy-matrix scaffold. Most entries should start as link-only stubs and be upgraded to verified over time.',
        'Always store source_url + last_verified when you upgrade an entry to verified.',
    ],
    'policies' => [],
];

if (is_file($matrixPath)) {
    $raw = file_get_contents($matrixPath);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $matrix = $decoded + $matrix;
            if (!isset($matrix['policies']) || !is_array($matrix['policies'])) {
                $matrix['policies'] = [];
            }
        }
    }
}

/** @return string */
$norm = function (string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9 ]/u', '', $s) ?? $s;
    return trim((string)$s);
};

$existing = [];
foreach ((array)$matrix['policies'] as $p) {
    if (!is_array($p)) {
        continue;
    }
    $op = (string)($p['operator'] ?? '');
    $cc = strtoupper((string)($p['country'] ?? ''));
    if ($op === '' || $cc === '') {
        continue;
    }
    $existing[$cc . '|' . $norm($op)] = true;
}

$added = 0;
foreach ((array)$opsJson['operators'] as $op) {
    if (!is_array($op)) {
        continue;
    }
    $name = trim((string)($op['name'] ?? ''));
    $cc = strtoupper(trim((string)($op['country_code'] ?? $op['country'] ?? '')));
    if ($name === '' || $cc === '') {
        continue;
    }
    $key = $cc . '|' . $norm($name);
    if (isset($existing[$key])) {
        continue;
    }

    $aliases = $op['aliases'] ?? [];
    if (!is_array($aliases)) {
        $aliases = [];
    }
    $aliases = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $aliases), static fn($v) => $v !== ''));

    $matrix['policies'][] = [
        'operator' => $name,
        'country' => $cc,
        'aliases' => $aliases,
        'coverage_status' => 'stub',
        'verified' => false,
        'last_verified' => null,
        'source_url' => '',
        'claim_channel' => ['type' => 'url', 'value' => ''],
        'products' => [
            [
                'product_type' => 'season_pass',
                'name' => 'Pendler-/periodekort (generelt)',
                'policy_type' => 'operator_scheme',
                'aggregation_window' => 'month',
                'qualifying_rules' => (object)[],
                'compensation_method' => 'operator_defined',
                'min_payout' => null,
                'notes' => 'TO_VERIFY: udfyld operatørens konkrete ordning + claim-link.',
            ],
        ],
    ];
    $existing[$key] = true;
    $added++;
}

// Sort policies by country then operator
usort($matrix['policies'], function ($a, $b) use ($norm) {
    $aC = strtoupper((string)($a['country'] ?? ''));
    $bC = strtoupper((string)($b['country'] ?? ''));
    if ($aC !== $bC) {
        return $aC <=> $bC;
    }
    $aO = $norm((string)($a['operator'] ?? ''));
    $bO = $norm((string)($b['operator'] ?? ''));
    return $aO <=> $bO;
});

$matrix['updated_at'] = date('Y-m-d');

$out = json_encode($matrix, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($out)) {
    fwrite(STDERR, "Failed to encode JSON.\n");
    exit(5);
}
if (file_put_contents($matrixPath, $out . "\n") === false) {
    fwrite(STDERR, "Failed to write: $matrixPath\n");
    exit(6);
}

fwrite(STDOUT, "Seed complete. Added $added operator stub(s).\nWrote: $matrixPath\n");
