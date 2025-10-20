<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\TicketExtraction;

use App\Service\TicketExtraction\ExtractorBroker;
use App\Service\TicketExtraction\HeuristicsExtractor;
use App\Service\TicketExtraction\LlmExtractor;
use Cake\TestSuite\TestCase;

final class ExtractorBrokerTest extends TestCase
{
    public function testHeuristicsWinsWhenConfident(): void
    {
        $text = "Paris Gare de Lyon (08:04) → Lyon Part-Dieu (10:41)\nDate: 20/03/2025\nTrain: TGV 8412\n";
        $broker = new ExtractorBroker([
            new HeuristicsExtractor(),
            new LlmExtractor(),
        ], 0.5);
        $res = $broker->run($text);
        $this->assertSame('heuristics', $res->provider);
        $this->assertArrayHasKey('dep_station', $res->fields);
        $this->assertSame('Paris Gare de Lyon', $res->fields['dep_station']);
        $this->assertGreaterThanOrEqual(0.5, $res->confidence);
    }
}
