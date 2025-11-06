<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PmrDetectionService;
use Cake\TestSuite\TestCase;

class PmrDetectionServiceTest extends TestCase
{
    public function testDetectsBookedAssistance(): void
    {
        $svc = new PmrDetectionService();
        $text = "ICE 71 München – Berlin\nServiceauftrag 12345\nEinstiegshilfe gebucht\nErmäßigung: Schwerbehinderte 50%";
        $res = $svc->analyze(['rawText' => $text, 'seller' => 'Deutsche Bahn AG']);
        $this->assertTrue($res['pmr_user']);
        $this->assertTrue($res['pmr_booked']);
        $this->assertNotEmpty($res['evidence']);
        $this->assertGreaterThanOrEqual(0.85, $res['confidence']);
    }

    public function testDetectsDiscountOnly(): void
    {
        $svc = new PmrDetectionService();
        $text = "Billet\nErmäßigung: Schwerbehinderte 50%\nDSB";
        $res = $svc->analyze(['rawText' => $text, 'seller' => 'DSB']);
        $this->assertTrue($res['pmr_user']);
        $this->assertFalse($res['pmr_booked']);
    }

    public function testNoSignals(): void
    {
        $svc = new PmrDetectionService();
        $text = "Standard ticket\nSeat 12A\nWi-Fi";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertFalse($res['pmr_user']);
        $this->assertFalse($res['pmr_booked']);
    }
}
