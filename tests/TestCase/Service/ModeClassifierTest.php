<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ModeClassifier;
use PHPUnit\Framework\TestCase;

final class ModeClassifierTest extends TestCase
{
    public function testClassifiesBornholmStyleTicketAsFerry(): void
    {
        $ocr = <<<TEXT
Udrejse
Fredag, den 3. december 2021, kl. 14:30
Ronne-Ystad (1 time 20 min)
1 Lavpris Bil < 1,95 m 349,00
3 Person (er)
Check-in skal vaere foretaget senest 10 min. inden planmaessig afgang.
TEXT;

        $result = (new ModeClassifier())->classify([
            'form' => ['ticket_upload_mode' => 'ticket'],
            'meta' => [
                '_ocr_text' => $ocr,
                '_auto' => [
                    'dep_station' => ['value' => 'Ronne'],
                    'arr_station' => ['value' => 'Ystad'],
                ],
            ],
        ]);

        $this->assertSame('ferry', $result['primary_mode']);
        $this->assertSame('high', $result['confidence']);
        $this->assertGreaterThan(($result['scores']['rail'] ?? 0), $result['scores']['ferry'] ?? 0);
    }

    public function testKeepsRailWhenTrainSignalsAreDominant(): void
    {
        $ocr = <<<TEXT
DSB
CIV
Odense - Kobenhavn H
Train IC 801
Platform 3
Seat 12A
TEXT;

        $result = (new ModeClassifier())->classify([
            'form' => ['ticket_upload_mode' => 'ticket'],
            'meta' => [
                '_ocr_text' => $ocr,
                '_auto' => [
                    'train_no' => ['value' => 'IC 801'],
                    'operator' => ['value' => 'DSB'],
                ],
            ],
        ]);

        $this->assertSame('rail', $result['primary_mode']);
        $this->assertContains('operator:DSB', $result['reasons']);
    }

    public function testClassifiesBoardingPassAsAir(): void
    {
        $ocr = <<<TEXT
BOARDING PASS
Passenger Name: Jane Doe
Flight SK1423
Gate A12
Boarding 08:30
PNR ABC123
CPH ARN
TEXT;

        $result = (new ModeClassifier())->classify([
            'form' => ['ticket_upload_mode' => 'ticket'],
            'meta' => [
                '_ocr_text' => $ocr,
                '_identifiers' => ['pnr' => 'ABC123'],
                '_auto' => [
                    'dep_station' => ['value' => 'CPH'],
                    'arr_station' => ['value' => 'ARN'],
                ],
            ],
        ]);

        $this->assertSame('air', $result['primary_mode']);
        $this->assertContains('ocr:boarding-pass', $result['reasons']);
        $this->assertContains('ocr:flight-number', $result['reasons']);
    }

    public function testClassifiesCoachTicketAsBus(): void
    {
        $ocr = <<<TEXT
Coach ticket
Long distance bus
Departure stop: Odense Banegard Center
Arrival stop: Aarhus Bus Station
Bus no 601
Seat 12
TEXT;

        $result = (new ModeClassifier())->classify([
            'form' => ['ticket_upload_mode' => 'ticket'],
            'meta' => [
                '_ocr_text' => $ocr,
            ],
        ]);

        $this->assertSame('bus', $result['primary_mode']);
        $this->assertContains('ocr:bus-ticket', $result['reasons']);
        $this->assertContains('ocr:bus-stop', $result['reasons']);
    }
}
