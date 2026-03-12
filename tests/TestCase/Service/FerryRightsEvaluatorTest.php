<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FerryRightsEvaluator;
use Cake\TestSuite\TestCase;

final class FerryRightsEvaluatorTest extends TestCase
{
    public function testDelayFromTerminalTriggersArt17Art18AndArt19Band25(): void
    {
        $result = (new FerryRightsEvaluator())->evaluate(
            [
                'incident_type' => 'delay',
                'actual_departure_delay_90' => true,
                'arrival_delay_minutes' => 130,
                'scheduled_journey_duration_minutes' => 300,
                'overnight_required' => false,
                'informed_before_purchase' => false,
                'passenger_fault' => false,
                'weather_safety' => false,
                'extraordinary_circumstances' => false,
            ],
            [
                'regulation_applies' => true,
                'service_type' => 'passenger_service',
                'departure_from_terminal' => true,
                'articles' => [
                    'art18' => true,
                    'art19' => true,
                ],
            ],
            [
                'manual_review_required' => false,
            ]
        );

        $this->assertTrue($result['gate_art16_notice']);
        $this->assertTrue($result['gate_art17_refreshments']);
        $this->assertFalse($result['gate_art17_hotel']);
        $this->assertTrue($result['gate_art18']);
        $this->assertTrue($result['gate_art19']);
        $this->assertSame('25', $result['art19_comp_band']);
    }

    public function testWeatherSafetyDisablesHotelAndArt19(): void
    {
        $result = (new FerryRightsEvaluator())->evaluate(
            [
                'incident_type' => 'cancellation',
                'expected_departure_delay_90' => true,
                'arrival_delay_minutes' => 240,
                'scheduled_journey_duration_minutes' => 300,
                'overnight_required' => true,
                'weather_safety' => true,
            ],
            [
                'regulation_applies' => true,
                'service_type' => 'passenger_service',
                'departure_from_terminal' => true,
                'articles' => [
                    'art18' => true,
                    'art19' => true,
                ],
            ],
            []
        );

        $this->assertTrue($result['gate_art16_notice']);
        $this->assertTrue($result['gate_art17_refreshments']);
        $this->assertFalse($result['gate_art17_hotel']);
        $this->assertTrue($result['gate_art18']);
        $this->assertFalse($result['gate_art19']);
    }

    public function testOpenTicketWithoutDepartureTimeBlocksRightsUnlessSeason(): void
    {
        $result = (new FerryRightsEvaluator())->evaluate(
            [
                'incident_type' => 'delay',
                'actual_departure_delay_90' => true,
                'arrival_delay_minutes' => 180,
                'scheduled_journey_duration_minutes' => 300,
                'open_ticket_without_departure_time' => true,
                'season_ticket' => false,
            ],
            [
                'regulation_applies' => true,
                'service_type' => 'passenger_service',
                'departure_from_terminal' => true,
                'articles' => [
                    'art18' => true,
                    'art19' => true,
                ],
            ],
            []
        );

        $this->assertTrue($result['gate_art16_notice']);
        $this->assertFalse($result['gate_art17_refreshments']);
        $this->assertFalse($result['gate_art18']);
        $this->assertFalse($result['gate_art19']);
        $this->assertSame('none', $result['art19_comp_band']);
    }
}
