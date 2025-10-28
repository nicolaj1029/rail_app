<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art12AutoDeriver;
use App\Service\Art12Evaluator;
use Cake\TestSuite\TestCase;

final class Art12AutoDeriverTest extends TestCase
{
    public function testTgvOneOperatorImplicitThrough(): void
    {
        $journey = [
            'bookingRef' => 'KM0506',
            'seller_type'=> null,
            'segments' => [[ 'pnr'=>'KM0506', 'carrier'=>'SNCF', 'operator'=>'SNCF' ]]
        ];
        $meta = [
            'through_ticket_disclosure' => 'Ved ikke',
            'separate_contract_notice'  => 'Ved ikke',
        ];

        $auto = (new Art12AutoDeriver())->derive($journey);
        $meta = $meta + $auto;

        $out = (new Art12Evaluator())->evaluate($journey, $meta);

        $this->assertTrue($out['art12_applies'] === true, 'Art.12 should apply');
        $this->assertSame('operator', $out['liable_party'], 'Single operator should be liable when Art.12 applies');
        $reason = mb_strtolower(implode(' ', (array)$out['reasoning']));
        $this->assertStringContainsString('implicit gennemgående', $reason);
    }
}
