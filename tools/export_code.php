<?php
declare(strict_types=1);

// Simple exporter: concatenates all Controllers and templates into a single file.
// Usage:
//   php tools/export_code.php [outputPath]
// Default output path:
//   code_exports/controllers_templates_all.php

function rrmdir_mkdir(string $filePath): void {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function findPhpFiles(string $dir): array {
    $files = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            if (strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function main(array $argv): int {
    $root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if ($root === false) {
        fwrite(STDERR, "Could not resolve project root.\n");
        return 1;
    }

    $defaultOut = $root . DIRECTORY_SEPARATOR . 'code_exports' . DIRECTORY_SEPARATOR . 'controllers_templates_all.php';
    $outFile = $argv[1] ?? $defaultOut;

    $candidateDirs = [
        $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller',
        $root . DIRECTORY_SEPARATOR . 'templates',
        $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'View',
        // Some repos keep sources under bin/src; include if present
        $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller',
        $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'templates',
    ];

    $existingDirs = array_values(array_filter($candidateDirs, fn($d) => is_dir($d)));
    if (!$existingDirs) {
        fwrite(STDERR, "No controller/template directories found. Checked:\n  - " . implode("\n  - ", $candidateDirs) . "\n");
        return 2;
    }

    $allFiles = [];
    foreach ($existingDirs as $dir) {
        $allFiles = array_merge($allFiles, findPhpFiles($dir));
    }
    $allFiles = array_values(array_unique($allFiles));
    sort($allFiles, SORT_NATURAL | SORT_FLAG_CASE);

    rrmdir_mkdir($outFile);
    $fh = fopen($outFile, 'w');
    if ($fh === false) {
        fwrite(STDERR, "Unable to open output file: {$outFile}\n");
        return 3;
    }

    $header = "/**\n * Controllers and Templates export\n * Generated: " . date('c') . "\n * Files: " . count($allFiles) . "\n */\n\n";
    fwrite($fh, $header);

    foreach ($allFiles as $file) {
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
        fwrite($fh, "===== FILE: {$rel} =====\n\n");
        $content = file_get_contents($file);
        if ($content === false) {
            fwrite($fh, "<!-- Failed to read {$rel} -->\n\n");
            continue;
        }
        fwrite($fh, $content);
        fwrite($fh, "\n\n");
    }

    fclose($fh);
    echo "Wrote export to: {$outFile}\n";
    return 0;
}

exit(main($argv));
