<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\BikeDetectionService;
use Cake\TestSuite\TestCase;

class BikeDetectionServiceTest extends TestCase
{
    public function testDetectsGermanBikeReservation(): void
    {
        $svc = new BikeDetectionService();
        $text = "Reservierung Fahrradplatz 1\nFahrradreservierung ICE 123\nWagen 25 Sitz 56";
        $res = $svc->analyze(['rawText' => $text, 'seller' => 'DB']);
        $this->assertTrue($res['bike_booked']);
        $this->assertNotEmpty($res['evidence']);
        $this->assertGreaterThan(0.55, $res['confidence']);
    }

    public function testDetectsDanishBikeCount(): void
    {
        $svc = new BikeDetectionService();
        $text = "Cykelpladsbillet x2\nAfgang 08:12 København";
        $res = $svc->analyze(['rawText' => $text, 'seller' => 'DSB']);
        $this->assertTrue($res['bike_booked']);
        $this->assertEquals(2, $res['bike_count']);
    }

    public function testNoBikeSignals(): void
    {
        $svc = new BikeDetectionService();
        $text = "Standard billet\nVoksen 1\nSæde 12A";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertFalse($res['bike_booked']);
        $this->assertNull($res['bike_count']);
    }
}
