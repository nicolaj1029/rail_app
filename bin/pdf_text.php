<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php bin/pdf_text.php <path-to-pdf>\n");
    fwrite(STDERR, "File not found: $path\n");
    exit(2);
}

try {
    $parser = new Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($path);
    $text = $pdf->getText();
    echo $text;
    if ($text === '' || $text === null) {
        fwrite(STDERR, "No text extracted (PDF might be image-based or encrypted).\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
