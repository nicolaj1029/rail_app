<?php
declare(strict_types=1);

namespace App\Service\TicketExtraction;

use App\Service\OcrHeuristicsMapper;

final class HeuristicsExtractor implements ExtractorInterface
{
    public function name(): string { return 'heuristics'; }

    public function extract(string $text): TicketExtractionResult
    {
        $mapper = new OcrHeuristicsMapper();
        $res = $mapper->mapText($text);
        $auto = $res['auto'] ?? [];
        $logs = $res['logs'] ?? [];

        // Confidence heuristic: count core fields present
        $core = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'];
        $score = 0; $max = count($core);
        foreach ($core as $k) {
            if (!empty($auto[$k]['value'])) { $score++; }
        }
        $conf = $max > 0 ? $score / $max : 0.0;

        // Flatten fields to simple key => value
        $fields = [];
        foreach ($auto as $k => $v) { $fields[$k] = $v['value'] ?? null; }

        return new TicketExtractionResult($fields, $conf, $this->name(), $logs);
    }
}
