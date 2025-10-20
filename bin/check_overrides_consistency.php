#!/usr/bin/env php
<?php
declare(strict_types=1);

// Basic consistency checker between national_overrides.json, OperatorCatalog, and exemption_matrix.json

define('ROOT', realpath(__DIR__ . '/..'));
require_once ROOT . '/vendor/autoload.php';

use App\Service\OperatorCatalog;
use App\Service\NationalOverridesRepository;
use App\Service\ExemptionMatrixRepository;

$catalog = new OperatorCatalog();
$overrides = new NationalOverridesRepository();
$matrix = new ExemptionMatrixRepository();

$countries = $catalog->getCountries(); // code => name
$countryByName = array_flip(array_map('strtolower', $countries)); // name(lower) => code
$opsByCountry = $catalog->getOperators(); // code => [opId=>label]
$productsByOp = $catalog->getProducts(); // opId => [product]
$productScopes = $catalog->getProductScopes(); // opId => [product => scope]

$rows = $overrides->all();
$errors = [];
$info = [];

foreach ($rows as $i => $row) {
    $countryName = (string)($row['country'] ?? '');
    $op = (string)($row['operator'] ?? '');
    $prod = (string)($row['product'] ?? '');
    if ($countryName === '' || $op === '' || $prod === '') {
        $errors[] = "[$i] Missing required fields country/operator/product";
        continue;
    }
    $code = $countryByName[strtolower($countryName)] ?? null;
    if (!$code) {
        $errors[] = "[$i] Country not in catalog: {$countryName}";
        continue;
    }
    // operator present under this country?
    $ops = $opsByCountry[$code] ?? [];
    if (!array_key_exists($op, $ops)) {
        $errors[] = "[$i] Operator '{$op}' not listed for country {$code}";
    }
    // product listed under operator?
    $prods = $productsByOp[$op] ?? [];
    if (!in_array($prod, $prods, true)) {
        $errors[] = "[$i] Product '{$prod}' not listed under operator '{$op}' in catalog";
    }
    // scope inference
    $scope = $productScopes[$op][$prod] ?? '';
    // if inferred regional and matrix says blocked for this country, it's inconsistent to expose such override
    if ($scope === 'regional') {
        $mxRows = $matrix->find(['country' => $code, 'scope' => 'regional']);
        $blocked = false; foreach ($mxRows as $r) { if (!empty($r['blocked'])) { $blocked = true; break; } }
        if ($blocked) {
            $errors[] = "[$i] Override product is regional but country {$code} blocks regional scope";
        }
    }
    // optional note if override declares exemptions/scope meta
    if (!empty($row['exemptions']) || !empty($row['scope'])) {
        $info[] = "[$i] Override meta present for {$code}/{$op}/{$prod}: scope=" . ($row['scope'] ?? '') . ", exemptions=" . json_encode($row['exemptions'] ?? []);
    }
}

if ($errors) {
    fwrite(STDERR, "Found " . count($errors) . " issue(s):\n");
    foreach ($errors as $e) fwrite(STDERR, "- $e\n");
    exit(1);
}

fwrite(STDOUT, "Overrides consistency OK (" . count($rows) . " entries).\n");
if ($info) {
    fwrite(STDOUT, "Notes:\n");
    foreach ($info as $n) fwrite(STDOUT, "* $n\n");
}
