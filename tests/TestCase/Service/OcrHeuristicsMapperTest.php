<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OcrHeuristicsMapper;
use Cake\TestSuite\TestCase;

final class OcrHeuristicsMapperTest extends TestCase
{
    public function testDoesNotAcceptSingleLetterArrivalStation(): void
    {
        $mapper = new OcrHeuristicsMapper();
        // Simulate an OCR text where arrow parsing might capture a single-letter 'T' as destination
        $text = "POITIERS (07h42) -> TGV 8501 (train)\nPARIS (Arrivée 09:10)\n";
        $res = $mapper->mapText($text);
        $auto = $res['auto'] ?? [];

        // dep_station might be found or not depending on heuristics, but arr_station must NOT be 'T'
        $this->assertArrayNotHasKey('arr_station', $auto, 'arr_station should be dropped when only a single letter candidate is present');
    }
}
