<?php
// Usage: php scripts/parse_segments_from_text.php <textPath>
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Service\TicketParseService;

if ($argc < 2) { fwrite(STDERR, "Usage: php scripts/parse_segments_from_text.php <textPath>\n"); exit(1);} 
$path = $argv[1];
if (!is_file($path)) { fwrite(STDERR, "Not found: {$path}\n"); exit(2);} 
$text = (string)file_get_contents($path);
$svc = new TicketParseService();
$segs = $svc->parseSegmentsFromText($text);
echo json_encode($segs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
// Dump a small debug header
$dbg = \App\Service\TicketParseService::getLastDebug();
fwrite(STDERR, "events=" . count((array)($dbg['events'] ?? [])) . ", blockMatched=" . (!empty($dbg['blockMatched'])?'1':'0') . "\n");
if (!empty($dbg['events'])) {
	$sample = array_slice((array)$dbg['events'], 0, 15);
	foreach ($sample as $e) {
		$line = isset($e['line']) ? $e['line'] : '';
		fwrite(STDERR, sprintf(" - [%s] %s %s %s | %s\n", (string)($e['src']??''), (string)($e['station']??''), (string)($e['type']??''), (string)($e['time']??''), $line));
	}
}
