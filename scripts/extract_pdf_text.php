<?php
// Usage: php scripts/extract_pdf_text.php <pdfPath> [<pdfPath> ...]
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/extract_pdf_text.php <pdfPath> [<pdfPath> ...>\n");
    exit(1);
}

$parser = new Parser();
$outDir = __DIR__ . '/../tmp/extracted';
if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

foreach (array_slice($argv, 1) as $pdfPath) {
    $pdfPath = realpath($pdfPath) ?: $pdfPath;
    if (!is_file($pdfPath)) {
        fwrite(STDERR, "Not found: {$pdfPath}\n");
        continue;
    }
    try {
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();
        // Normalize whitespace a bit
        $text = preg_replace("/\r\n?|\n/", "\n", $text);
        $text = preg_replace("/\t/", " ", $text);
        // Generate output path
        $base = pathinfo($pdfPath, PATHINFO_FILENAME);
        $outFile = $outDir . '/' . $base . '.txt';
        file_put_contents($outFile, $text ?? '');
        echo "Wrote: {$outFile}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Error parsing {$pdfPath}: " . $e->getMessage() . "\n");
    }
}
