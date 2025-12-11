<?php
// Quick diagnostic script to verify DK national mapping loads after fix.
// Usage: php tmp_scripts/check_dk_map.php
declare(strict_types=1);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Direct decode of DK national mapping file as used by controller fast-path.
$dkMapPath = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'DK' . DIRECTORY_SEPARATOR . 'map_dk_dsb_basic_rejsetidsgaranti_clean.json';
if (!is_file($dkMapPath)) {
    echo "DK map file missing: $dkMapPath\n";
    exit(1);
}
$json = (string)file_get_contents($dkMapPath);
$head = substr($json, 0, 80);
echo "First bytes (printable):\n";
for ($i=0;$i<strlen($head);$i++) { $c = $head[$i]; $o = ord($c); echo sprintf("%s(%02X) ", ($o>=32&&$o<=126)?$c:'.', $o); }
echo "\nTotal length: ".strlen($json)." bytes\n";
$map = json_decode($json, true);
if (!is_array($map)) {
    echo "DK map decode failed: " . json_last_error_msg() . "\n";
    exit(1);
}

$meta = $map['_meta'] ?? ($map['meta'] ?? []);
$pagesNumeric = array_filter(array_keys($map), fn($k) => is_int($k));

echo "DK map loaded: YES\n";
echo "Meta file: " . ($meta['file'] ?? 'n/a') . "\n";
echo "Units: " . ($meta['units'] ?? 'n/a') . "\n";
echo "Origin: " . ($meta['origin'] ?? 'n/a') . "\n";
echo "Page count: " . count($pagesNumeric) . "\n";
echo "Keys sample: " . implode(', ', array_slice(array_keys($map), 0, 10)) . "\n";
echo "Status: map_origin would be 'national' after controller logic (natMap non-null).\n";