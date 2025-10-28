<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art12Evaluator;
use Cake\TestSuite\TestCase;

class Art12EvaluatorQuickTest extends TestCase
{
    public function testSharedPnrOperatorSellerTriggersThrough12_3(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['operator' => 'DB', 'pnr' => 'PNR123'],
                ['operator' => 'DB', 'pnr' => 'PNR123'],
            ],
            'bookingRef' => 'PNR123',
            'seller_type' => 'operator',
            'missed_connection' => true,
        ];
        $meta = [
            'separate_contract_notice' => 'Nej',
            'one_contract_schedule' => 'Ja',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertSame('THROUGH_12_3', $out['classification'] ?? null);
        $this->assertTrue($out['art12_applies'] === true);
        $this->assertSame('operator', $out['liable_party']);
    }

    public function testSeparateNoticeWithNoSharedItineraryIsSeparate12_5(): void
    {
        $svc = new Art12Evaluator();
        $journey = [
            'segments' => [
                ['operator' => 'DB', 'pnr' => 'A1'],
                ['operator' => 'DB', 'pnr' => 'B2'],
            ],
            'bookingRef' => null,
            'seller_type' => null,
        ];
        $meta = [
            'separate_contract_notice' => 'Ja',
            'one_contract_schedule' => 'Nej',
        ];
        $out = $svc->evaluate($journey, $meta);
        $this->assertSame('SEPARATE_12_5', $out['classification'] ?? null);
        $this->assertFalse($out['art12_applies'] === true);
    }
}
