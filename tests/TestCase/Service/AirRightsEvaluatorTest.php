<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirRightsEvaluator;
use PHPUnit\Framework\TestCase;

final class AirRightsEvaluatorTest extends TestCase
{
    public function testCancellationCreatesCareRerouteAndCompensationCandidate(): void
    {
        $result = (new AirRightsEvaluator())->evaluate([
            'incident_type' => 'cancellation',
            'arrival_delay_minutes' => 240,
            'reroute_arrival_delay_minutes' => 240,
            'extraordinary_circumstances' => false,
        ], [
            'regulation_applies' => true,
        ], [
            'air_connection_type' => 'single_flight',
        ]);

        $this->assertTrue($result['gate_air_care']);
        $this->assertTrue($result['gate_air_reroute_refund']);
        $this->assertTrue($result['gate_air_compensation']);
        $this->assertSame('candidate', $result['air_comp_band']);
    }

    public function testExtraordinaryCircumstancesBlockCompensation(): void
    {
        $result = (new AirRightsEvaluator())->evaluate([
            'incident_type' => 'delay',
            'arrival_delay_minutes' => 210,
            'extraordinary_circumstances' => true,
        ], [
            'regulation_applies' => true,
        ], [
            'air_connection_type' => 'single_flight',
        ]);

        $this->assertTrue($result['gate_air_care']);
        $this->assertFalse($result['gate_air_compensation']);
        $this->assertSame('extraordinary_circumstances', $result['compensation_block_reason']);
    }

    public function testSelfTransferMissedConnectionDoesNotTriggerCompensation(): void
    {
        $result = (new AirRightsEvaluator())->evaluate([
            'incident_type' => 'missed_connection',
            'protected_connection_missed' => false,
            'arrival_delay_minutes' => 240,
        ], [
            'regulation_applies' => true,
        ], [
            'air_connection_type' => 'self_transfer',
        ]);

        $this->assertFalse($result['gate_air_compensation']);
        $this->assertSame('self_transfer_or_unprotected_connection', $result['compensation_block_reason']);
    }
}
