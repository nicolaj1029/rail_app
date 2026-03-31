#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Report backlog/coverage for season/period pass policies (Art. 19(2)).
 *
 * Usage:
 *   php bin/report_season_policy_backlog.php
 *   php bin/report_season_policy_backlog.php --json
 */

$root = dirname(__DIR__);
$operatorsPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'transport_operators_catalog.json';
$matrixPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'season_policy_matrix.json';

$asJson = in_array('--json', $argv, true);

if (!is_file($operatorsPath)) {
    fwrite(STDERR, "Missing operators catalog: $operatorsPath\n");
    exit(2);
}
if (!is_file($matrixPath)) {
    fwrite(STDERR, "Missing policy matrix: $matrixPath\n");
    exit(2);
}

$opsRaw = file_get_contents($operatorsPath);
$matrixRaw = file_get_contents($matrixPath);
if ($opsRaw === false || $matrixRaw === false) {
    fwrite(STDERR, "Cannot read one of the input files.\n");
    exit(3);
}

$opsJson = json_decode($opsRaw, true);
$matrixJson = json_decode($matrixRaw, true);
if (!is_array($opsJson) || !is_array($opsJson['operators'] ?? null)) {
    fwrite(STDERR, "Invalid operators catalog JSON structure.\n");
    exit(4);
}
if (!is_array($matrixJson) || !is_array($matrixJson['policies'] ?? null)) {
    fwrite(STDERR, "Invalid season policy matrix JSON structure.\n");
    exit(4);
}

/** @return string */
$norm = static function (string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = preg_replace('/[^a-z0-9 ]/u', '', $s) ?? $s;
    return trim((string)$s);
};

$eu27 = [
    'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
];

// Build operators by country (canonical names from catalog)
$opsByCountry = [];
foreach ((array)$opsJson['operators'] as $op) {
    if (!is_array($op)) {
        continue;
    }
    $name = trim((string)($op['name'] ?? ''));
    $cc = strtoupper(trim((string)($op['country_code'] ?? $op['country'] ?? '')));
    if ($name === '' || $cc === '') {
        continue;
    }
    $opsByCountry[$cc][$name] = true;
}
foreach ($opsByCountry as &$map) {
    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
}
unset($map);
ksort($opsByCountry);

// Build policies by country and operator (norm)
$polByCountry = [];
foreach ((array)$matrixJson['policies'] as $p) {
    if (!is_array($p)) {
        continue;
    }
    $opName = trim((string)($p['operator'] ?? ''));
    $cc = strtoupper(trim((string)($p['country'] ?? '')));
    if ($opName === '' || $cc === '') {
        continue;
    }
    $polByCountry[$cc][$norm($opName)] = $p;
}
ksort($polByCountry);

$report = [];
foreach ($eu27 as $cc) {
    $ops = array_keys((array)($opsByCountry[$cc] ?? []));
    $pols = (array)($polByCountry[$cc] ?? []);

    $verified = 0;
    $withLinks = 0;
    $missingPolicy = [];
    $missingLinks = [];

    foreach ($pols as $p) {
        if (!is_array($p)) {
            continue;
        }
        if (!empty($p['verified'])) {
            $verified++;
        }
        $src = trim((string)($p['source_url'] ?? ''));
        $ch = (array)($p['claim_channel'] ?? []);
        $chUrl = trim((string)($ch['value'] ?? ''));
        if ($src !== '' || $chUrl !== '') {
            $withLinks++;
        }
    }

    // Missing policies + missing links per operator (based on catalog)
    foreach ($ops as $opName) {
        $p = $pols[$norm((string)$opName)] ?? null;
        if (!is_array($p)) {
            $missingPolicy[] = $opName;
            continue;
        }
        $src = trim((string)($p['source_url'] ?? ''));
        $ch = (array)($p['claim_channel'] ?? []);
        $chUrl = trim((string)($ch['value'] ?? ''));
        if ($src === '' && $chUrl === '') {
            $missingLinks[] = $opName;
        }
    }

    sort($missingPolicy, SORT_NATURAL | SORT_FLAG_CASE);
    sort($missingLinks, SORT_NATURAL | SORT_FLAG_CASE);

    $report[$cc] = [
        'operators_total' => count($ops),
        'season_policies' => count($pols),
        'verified' => $verified,
        'with_links' => $withLinks,
        'missing_policy' => $missingPolicy,
        'missing_links' => $missingLinks,
    ];
}

if ($asJson) {
    $out = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($out)) {
        fwrite(STDERR, "Failed to encode JSON.\n");
        exit(5);
    }
    fwrite(STDOUT, $out . "\n");
    exit(0);
}

fwrite(STDOUT, "EU-27 season-pass policy backlog\n");
fwrite(STDOUT, "Operators: $operatorsPath\n");
fwrite(STDOUT, "Matrix:    $matrixPath\n\n");

foreach ($report as $cc => $row) {
    $opsTotal = (int)($row['operators_total'] ?? 0);
    $polTotal = (int)($row['season_policies'] ?? 0);
    $withLinks = (int)($row['with_links'] ?? 0);
    $verified = (int)($row['verified'] ?? 0);

    $missingLinks = (array)($row['missing_links'] ?? []);
    $missingPolicy = (array)($row['missing_policy'] ?? []);

    $suffix = '';
    if (!empty($missingPolicy)) {
        $suffix .= ' missing_policy=' . count($missingPolicy);
    }
    if (!empty($missingLinks)) {
        $suffix .= ' missing_links=' . count($missingLinks);
    }

    fwrite(STDOUT, sprintf(
        "%s: ops=%d policies=%d links=%d verified=%d%s\n",
        $cc,
        $opsTotal,
        $polTotal,
        $withLinks,
        $verified,
        $suffix
    ));
    if (!empty($missingLinks)) {
        fwrite(STDOUT, "  - add links: " . implode(', ', array_slice($missingLinks, 0, 8)) . (count($missingLinks) > 8 ? ' ...' : '') . "\n");
    }
}
