#!/usr/bin/env php
<?php
declare(strict_types=1);

// Minimal JSON validation script using just PHP.
$root = dirname(__DIR__);
$schemaPath = $root . '/config/schema/national_overrides.schema.json';
$dataPath = $root . '/config/data/national_overrides.json';

function fail(string $msg): void {
    fwrite(STDERR, "[overrides:invalid] $msg\n");
    exit(1);
}

if (!is_file($dataPath)) fail('Data file missing: ' . $dataPath);
$json = file_get_contents($dataPath);
$data = json_decode((string)$json, true);
if (!is_array($data)) fail('Invalid JSON or not an array at root');

$errors = [];
foreach ($data as $i => $row) {
    $rowPath = "[$i]";
    if (!is_array($row)) { $errors[] = "$rowPath not an object"; continue; }
    foreach (['country','operator','product','tiers'] as $req) {
        if (!array_key_exists($req, $row) || ($req === 'tiers' ? !is_array($row[$req]) : trim((string)$row[$req]) === '')) {
            $errors[] = "$rowPath missing or invalid '$req'";
        }
    }
    if (!empty($row['tiers']) && is_array($row['tiers'])) {
        foreach ($row['tiers'] as $j => $t) {
            $tp = "$rowPath.tiers[$j]";
            if (!is_array($t)) { $errors[] = "$tp not an object"; continue; }
            if (!isset($t['minDelayMin']) || !is_int($t['minDelayMin']) || $t['minDelayMin'] < 0) {
                $errors[] = "$tp.minDelayMin must be integer >=0";
            }
            if (!isset($t['percent']) || !is_int($t['percent']) || $t['percent'] < 0 || $t['percent'] > 100) {
                $errors[] = "$tp.percent must be integer 0..100";
            }
            if (isset($t['payout']) && !in_array($t['payout'], ['voucher','cash_or_voucher','cash','other'], true)) {
                $errors[] = "$tp.payout must be one of voucher|cash_or_voucher|cash|other";
            }
        }
    }
}

if ($errors) {
    foreach ($errors as $e) fwrite(STDERR, "- $e\n");
    fail('Validation failed');
}

fwrite(STDOUT, "[overrides:ok] Validation passed (" . count($data) . " items)\n");
