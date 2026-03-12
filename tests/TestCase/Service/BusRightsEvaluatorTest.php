<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\BusRightsEvaluator;
use PHPUnit\Framework\TestCase;

final class BusRightsEvaluatorTest extends TestCase
{
    public function testTerminalDelayActivatesRefundAssistanceAndCompensation(): void
    {
        $result = (new BusRightsEvaluator())->evaluate(
            [
                'incident_type' => 'delay',
                'delay_minutes_departure' => 130,
                'scheduled_journey_duration_minutes' => 240,
                'carrier_offered_choice' => false,
                'open_ticket_without_departure_time' => false,
                'season_ticket' => false,
            ],
            [
                'regulation_applies' => true,
                'departure_from_terminal' => true,
            ],
            []
        );

        $this->assertTrue($result['gate_bus_info']);
        $this->assertTrue($result['gate_bus_reroute_refund']);
        $this->assertTrue($result['gate_bus_assistance_refreshments']);
        $this->assertTrue($result['gate_bus_assistance_hotel']);
        $this->assertTrue($result['gate_bus_compensation_50']);
        $this->assertSame('50', $result['bus_comp_band']);
    }

    public function testOpenTicketBlocksCoreBusRightsUnlessSeasonTicket(): void
    {
        $result = (new BusRightsEvaluator())->evaluate(
            [
                'incident_type' => 'cancellation',
                'delay_minutes_departure' => 140,
                'scheduled_journey_duration_minutes' => 300,
                'carrier_offered_choice' => false,
                'open_ticket_without_departure_time' => true,
                'season_ticket' => false,
            ],
            [
                'regulation_applies' => true,
                'departure_from_terminal' => true,
            ],
            []
        );

        $this->assertTrue($result['gate_bus_info']);
        $this->assertFalse($result['gate_bus_reroute_refund']);
        $this->assertFalse($result['gate_bus_assistance_refreshments']);
        $this->assertFalse($result['gate_bus_compensation_50']);
        $this->assertSame('open_ticket_without_departure_time', $result['compensation_block_reason']);
    }

    public function testWeatherBlocksHotelButNotRefreshments(): void
    {
        $result = (new BusRightsEvaluator())->evaluate(
            [
                'incident_type' => 'cancellation',
                'delay_minutes_departure' => 100,
                'scheduled_journey_duration_minutes' => 240,
                'carrier_offered_choice' => true,
                'severe_weather' => true,
            ],
            [
                'regulation_applies' => true,
                'departure_from_terminal' => true,
            ],
            []
        );

        $this->assertTrue($result['gate_bus_assistance_refreshments']);
        $this->assertFalse($result['gate_bus_assistance_hotel']);
    }
}
