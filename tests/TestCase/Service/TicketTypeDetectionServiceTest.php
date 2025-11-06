<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TicketTypeDetectionService;
use Cake\TestSuite\TestCase;

class TicketTypeDetectionServiceTest extends TestCase
{
    public function testDbSuperSparpreisIsNonflexAndSpecific(): void
    {
        $svc = new TicketTypeDetectionService();
        $text = "DB Super Sparpreis, Gilt nur im gebuchten Zug. Train ICE 618, Wagen 12 Sitz 34, 08:12.";
        $res = $svc->analyze([
            'rawText' => $text,
            'productName' => 'ICE',
        ]);
        $this->assertSame('nonflex', $res['fare_flex_type']);
        $this->assertSame('specific', $res['train_specificity']);
        $this->assertNotEmpty($res['evidence']);
    }

    public function testSncfSemiFlexSpecific(): void
    {
        $svc = new TicketTypeDetectionService();
        $text = "SNCF Billet échangeable avec frais. Réservation obligatoire. Train TGV 8423 10:58. Siège 22.";
        $res = $svc->analyze(['rawText' => $text, 'productName' => 'TGV']);
        $this->assertTrue(in_array($res['fare_flex_type'], ['semiflex','flex'], true));
        $this->assertSame('specific', $res['train_specificity']);
    }

    public function testOpenTicketAnyTrainSameDay(): void
    {
        $svc = new TicketTypeDetectionService();
        $text = "Open ticket – valid any train same day. No seat assigned.";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertSame('any_day', $res['train_specificity']);
    }

    public function testSeasonPass(): void
    {
        $svc = new TicketTypeDetectionService();
        $text = "Periodekort / Abonnement – gyldig i zone 01-04 hele perioden.";
        $res = $svc->analyze(['rawText' => $text]);
        $this->assertSame('pass', $res['fare_flex_type']);
    }
}
