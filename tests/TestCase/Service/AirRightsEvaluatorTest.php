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
            'travel_state' => 'completed',
            'cancellation_notice_band' => 'under_7_days',
            'reroute_offered' => false,
            'remedy_choice' => 'refund_return',
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

    public function testExtraordinaryCircumstancesTriggerManualReviewInsteadOfHardReject(): void
    {
        $result = (new AirRightsEvaluator())->evaluate([
            'incident_type' => 'delay',
            'travel_state' => 'completed',
            'arrival_delay_minutes' => 210,
            'delay_departure_band' => 'threshold_to_under_5h',
            'extraordinary_circumstances' => true,
        ], [
            'regulation_applies' => true,
        ], [
            'air_connection_type' => 'single_flight',
        ]);

        $this->assertTrue($result['gate_air_care']);
        $this->assertTrue($result['gate_air_compensation']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertSame('uncertain', $result['article7_eligibility_status']);
        $this->assertSame('extraordinary_circumstances_review', $result['compensation_block_reason']);
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

    public function testOngoingDelayFivePlusActivatesCareAndRefundWithoutCompletedCompensationYet(): void
    {
        $result = (new AirRightsEvaluator())->evaluate([
            'incident_type' => 'delay',
            'travel_state' => 'ongoing',
            'delay_departure_band' => 'five_plus',
            'delay_minutes_departure' => 300,
            'arrival_delay_minutes' => 0,
        ], [
            'regulation_applies' => true,
            'air_delay_threshold_hours' => 3,
        ], [
            'air_connection_type' => 'single_flight',
        ]);

        $this->assertTrue($result['gate_air_care']);
        $this->assertTrue($result['gate_air_delay_refund_5h']);
        $this->assertFalse($result['gate_air_compensation']);
        $this->assertSame('not_eligible', $result['article7_eligibility_status']);
    }
}
