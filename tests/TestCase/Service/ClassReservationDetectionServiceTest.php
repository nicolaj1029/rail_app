<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ClassReservationDetectionService;
use Cake\TestSuite\TestCase;

class ClassReservationDetectionServiceTest extends TestCase
{
    public function testDetectsFirstClassWithSeat(): void
    {
        $svc = new ClassReservationDetectionService();
        $text = "Billet 1. klasse\nDB ICE 618\nCoach 12 Seat 34\nPlatzreservierung";
        $res = $svc->analyze(['rawText' => $text, 'fields' => ['seatLine' => 'Coach 12 Seat 34']]);
        $this->assertSame('1', $res['fare_class_purchased']);
        $this->assertSame('seat', $res['berth_seat_type']);
        $this->assertNotEmpty($res['evidence']);
    }

    public function testDetectsFreeSeating(): void
    {
        $svc = new ClassReservationDetectionService();
        $text = "Standard billet\nFri plads\nIngen reservation";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertSame('free', $res['berth_seat_type']);
    }

    public function testDetectsCouchette(): void
    {
        $svc = new ClassReservationDetectionService();
        $text = "Nightjet Liegewagen\nVogn 4 Plads 12B";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertSame('couchette', $res['berth_seat_type']);
    }
}
