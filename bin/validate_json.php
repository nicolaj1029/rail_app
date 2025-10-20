#!/usr/bin/env php
<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/validate_json.php <path-to-json>\n");
    exit(2);
}
$path = $argv[1];
if (!is_file($path)) {
    fwrite(STDERR, "File not found: $path\n");
    exit(3);
}
$c = file_get_contents($path);
if ($c === false) {
    fwrite(STDERR, "Cannot read file: $path\n");
    exit(4);
}
$data = json_decode($c, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Invalid JSON: " . json_last_error_msg() . "\n");
    exit(1);
}
$count = is_array($data) ? count($data) : 0;
$size = strlen($c);
 fwrite(STDOUT, "JSON OK ($size bytes).\n");
