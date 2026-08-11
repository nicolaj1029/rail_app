<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service\Air;

use App\Service\Air\AirExpenseReviewBandKeyMapper;
use App\Service\Air\AirExpenseReviewBandService;
use App\Service\Air\AirPassengerExpenseLocationResolver;
use App\Service\TransportNodeSearchService;
use PHPUnit\Framework\TestCase;

final class AirExpenseReviewBandServiceTest extends TestCase
{
    private string $nodesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nodesPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'air-review-nodes-' . uniqid('', true) . '.json';
        file_put_contents($this->nodesPath, json_encode([
            [
                'id' => '1',
                'mode' => 'air',
                'name' => 'Los Angeles International Airport',
                'aliases' => ['LAX'],
                'code' => 'LAX',
                'country' => 'US',
                'in_eu' => false,
                'lat' => 33.9416,
                'lon' => -118.4085,
                'node_type' => 'airport',
                'city' => 'Los Angeles',
                'source' => 'test',
            ],
            [
                'id' => '2',
                'mode' => 'air',
                'name' => 'Amsterdam Airport Schiphol',
                'aliases' => ['Schiphol'],
                'code' => 'AMS',
                'country' => 'NL',
                'in_eu' => true,
                'lat' => 52.3086,
                'lon' => 4.7639,
                'node_type' => 'airport',
                'city' => 'Amsterdam',
                'source' => 'test',
            ],
            [
                'id' => '3',
                'mode' => 'air',
                'name' => 'Copenhagen Airport',
                'aliases' => ['Kastrup', 'CPH'],
                'code' => 'CPH',
                'country' => 'DK',
                'in_eu' => true,
                'lat' => 55.6181,
                'lon' => 12.656,
                'node_type' => 'airport',
                'city' => 'Copenhagen',
                'source' => 'test',
            ],
            [
                'id' => '4',
                'mode' => 'air',
                'name' => 'Hamburg Airport',
                'aliases' => ['HAM'],
                'code' => 'HAM',
                'country' => 'DE',
                'in_eu' => true,
                'lat' => 53.6304,
                'lon' => 9.9882,
                'node_type' => 'airport',
                'city' => 'Hamburg',
                'source' => 'test',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        if (is_file($this->nodesPath)) {
            @unlink($this->nodesPath);
        }

        parent::tearDown();
    }

    public function testAssistanceUsesVeryHighBandAtLax(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'cancellation',
                'meal_offered' => 'no',
                'hotel_offered' => 'no',
            ],
            'meta' => [
                'air_selected_leg' => [
                    'key' => 'leg_1',
                    'dep_label' => 'Los Angeles International Airport',
                    'arr_label' => 'Amsterdam Airport Schiphol',
                    'dep_iata' => 'LAX',
                    'arr_iata' => 'AMS',
                ],
                'air_route_legs' => [[
                    'key' => 'leg_1',
                    'dep_label' => 'Los Angeles International Airport',
                    'arr_label' => 'Amsterdam Airport Schiphol',
                    'dep_iata' => 'LAX',
                    'arr_iata' => 'AMS',
                ]],
            ],
        ], ['meal_offered', 'hotel_offered']);

        $this->assertSame('LAX', $result['expense_airport_iata']);
        $this->assertSame('very_high', $result['airport_cost_zone']);
        $this->assertSame(25, $result['items'][0]['min']);
        $this->assertSame(70, $result['items'][0]['max']);
        $this->assertSame(250, $result['items'][1]['min']);
        $this->assertSame(550, $result['items'][1]['max']);
    }

    public function testSelectedTransitLegUsesAmsAsExpenseLocation(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'cancellation',
                'hotel_offered' => 'no',
            ],
            'meta' => [
                'air_selected_leg' => [
                    'key' => 'leg_2',
                    'dep_label' => 'Amsterdam Airport Schiphol',
                    'arr_label' => 'Copenhagen Airport',
                    'dep_iata' => 'AMS',
                    'arr_iata' => 'CPH',
                ],
                'air_route_legs' => [
                    [
                        'key' => 'leg_1',
                        'dep_label' => 'Los Angeles International Airport',
                        'arr_label' => 'Amsterdam Airport Schiphol',
                        'dep_iata' => 'LAX',
                        'arr_iata' => 'AMS',
                    ],
                    [
                        'key' => 'leg_2',
                        'dep_label' => 'Amsterdam Airport Schiphol',
                        'arr_label' => 'Copenhagen Airport',
                        'dep_iata' => 'AMS',
                        'arr_iata' => 'CPH',
                    ],
                ],
            ],
        ], ['hotel_offered']);

        $this->assertSame('AMS', $result['expense_airport_iata']);
        $this->assertSame('hub', $result['airport_cost_zone']);
        $this->assertSame(200, $result['items'][0]['min']);
        $this->assertSame(450, $result['items'][0]['max']);
    }

    public function testMissedConnectionUsesIncidentStation(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'delay',
                'protected_connection_missed' => 'yes',
                'missed_connection_station' => 'Amsterdam Airport Schiphol',
                'hotel_offered' => 'no',
            ],
            'meta' => [
                'air_selected_leg' => [
                    'key' => 'leg_1',
                    'dep_label' => 'Copenhagen Airport',
                    'arr_label' => 'Amsterdam Airport Schiphol',
                    'dep_iata' => 'CPH',
                    'arr_iata' => 'AMS',
                ],
            ],
        ], ['hotel_offered']);

        $this->assertSame('AMS', $result['expense_airport_iata']);
        $this->assertSame('incident_missed_connection_station', $result['location_source']);
    }

    public function testRerouteNewTicketUsesLonghaulBand(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_reroute_scope',
            'form' => [
                'air_distance_band' => 'other_over_3500',
                'air_reroute_expense_items' => [
                    ['type' => 'new_ticket', 'amount' => '2100', 'description' => 'replacement flight'],
                ],
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Amsterdam Airport Schiphol',
                    'arr_label' => 'Los Angeles International Airport',
                    'dep_iata' => 'AMS',
                    'arr_iata' => 'LAX',
                ],
            ],
        ], ['air_reroute_expense_items.type']);

        $this->assertSame('reroute_replacement_air_ticket_longhaul', $result['items'][0]['internal_category']);
        $this->assertSame(300, $result['items'][0]['min']);
        $this->assertSame(3000, $result['items'][0]['max']);
    }

    public function testRefundCityTransferDoesNotIncludeHotel(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_refund_scope',
            'form' => [
                'air_return_expense_items' => [
                    ['type' => 'other_transport', 'amount' => '70', 'description' => 'city transfer downtown'],
                ],
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Hamburg Airport',
                    'arr_label' => 'Copenhagen Airport',
                    'dep_iata' => 'HAM',
                    'arr_iata' => 'CPH',
                ],
            ],
        ], ['air_return_expense_items.type']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('refund_return_city_transfer', $result['items'][0]['internal_category']);
    }

    public function testWrongCategoryInWrongScopeIsIgnored(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_reroute_scope',
            'form' => ['meal_offered' => 'no'],
        ], ['meal_offered']);

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['total_max']);
    }

    public function testUnknownAirportFallsBackToMidZone(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'cancellation',
                'dep_station' => 'Mystery Airport',
                'dep_station_lookup_code' => 'XXX',
                'hotel_offered' => 'no',
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Mystery Airport',
                    'arr_label' => 'Hamburg Airport',
                    'dep_iata' => 'XXX',
                    'arr_iata' => 'HAM',
                ],
            ],
        ], ['hotel_offered']);

        $this->assertSame('XXX', $result['expense_airport_iata']);
        $this->assertSame('mid', $result['airport_cost_zone']);
        $this->assertTrue($result['fallback_used']);
    }

    public function testHotelReceiptAboveThresholdTriggersManualReview(): void
    {
        $service = $this->buildService();
        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'cancellation',
                'hotel_offered' => 'no',
                'hotel_self_paid_amount_items' => ['820'],
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Los Angeles International Airport',
                    'arr_label' => 'Amsterdam Airport Schiphol',
                    'dep_iata' => 'LAX',
                    'arr_iata' => 'AMS',
                ],
            ],
        ], ['hotel_offered']);

        $this->assertTrue($result['flags']['manual_review_required']);
        $this->assertNotEmpty($result['flags']['manual_review_reasons']);
    }

    public function testRemedyCurrentAirportOverridesDepartureForRefundAndReroute(): void
    {
        $service = $this->buildService();

        $refundResult = $service->estimateForScope([
            'scope' => 'air_refund_scope',
            'form' => [
                'a18_from_station' => 'Hamburg Airport',
                'air_return_expense_items' => [
                    ['type' => 'other_transport', 'amount' => '55', 'description' => 'city transfer'],
                ],
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Copenhagen Airport',
                    'arr_label' => 'Hamburg Airport',
                    'dep_iata' => 'CPH',
                    'arr_iata' => 'HAM',
                ],
            ],
        ], ['air_return_expense_items.type']);

        $rerouteResult = $service->estimateForScope([
            'scope' => 'air_reroute_scope',
            'form' => [
                'a18_from_station' => 'Hamburg Airport',
                'air_reroute_expense_items' => [
                    ['type' => 'airport_transfer', 'amount' => '40', 'description' => 'airport shuttle'],
                ],
            ],
            'meta' => [
                'air_selected_leg' => [
                    'dep_label' => 'Copenhagen Airport',
                    'arr_label' => 'Hamburg Airport',
                    'dep_iata' => 'CPH',
                    'arr_iata' => 'HAM',
                ],
            ],
        ], ['air_reroute_expense_items.type']);

        $this->assertSame('HAM', $refundResult['expense_airport_iata']);
        $this->assertSame('DE', $refundResult['expense_country_code']);
        $this->assertSame('remedy_current_airport', $refundResult['location_source']);
        $this->assertSame('HAM', $rerouteResult['expense_airport_iata']);
        $this->assertSame('DE', $rerouteResult['expense_country_code']);
        $this->assertSame('remedy_current_airport', $rerouteResult['location_source']);
    }

    public function testRemedyAnchorCanDriveRefundLocationWithoutReEnteringAirport(): void
    {
        $service = $this->buildService();

        $result = $service->estimateForScope([
            'scope' => 'air_refund_scope',
            'form' => [
                'air_return_expense_items' => [
                    ['type' => 'other_transport', 'amount' => '55', 'description' => 'city transfer'],
                ],
            ],
            'meta' => [
                'air_current_location_anchor' => [
                    'airport_label' => 'Hamburg Airport',
                    'airport_iata' => 'HAM',
                    'country_code' => 'DE',
                    'source' => 'remedy_current_airport',
                ],
                'air_selected_leg' => [
                    'dep_label' => 'Copenhagen Airport',
                    'arr_label' => 'Hamburg Airport',
                    'dep_iata' => 'CPH',
                    'arr_iata' => 'HAM',
                ],
            ],
        ], ['air_return_expense_items.type']);

        $this->assertSame('HAM', $result['expense_airport_iata']);
        $this->assertSame('DE', $result['expense_country_code']);
        $this->assertSame('remedy_current_airport', $result['location_source']);
    }

    public function testAssistanceCanReuseRemedyAnchorInRandomOrderFlow(): void
    {
        $service = $this->buildService();

        $result = $service->estimateForScope([
            'scope' => 'air_assistance_scope',
            'form' => [
                'incident_main' => 'cancellation',
                'hotel_offered' => 'no',
            ],
            'meta' => [
                'air_current_location_anchor' => [
                    'airport_label' => 'Hamburg Airport',
                    'airport_iata' => 'HAM',
                    'country_code' => 'DE',
                    'source' => 'remedy_current_airport',
                ],
                'air_selected_leg' => [
                    'dep_label' => 'Copenhagen Airport',
                    'arr_label' => 'Hamburg Airport',
                    'dep_iata' => 'CPH',
                    'arr_iata' => 'HAM',
                ],
            ],
        ], ['hotel_offered']);

        $this->assertSame('HAM', $result['expense_airport_iata']);
        $this->assertSame('DE', $result['expense_country_code']);
        $this->assertSame('remedy_current_airport', $result['location_source']);
    }

    private function buildService(): AirExpenseReviewBandService
    {
        $search = new TransportNodeSearchService($this->nodesPath);
        $resolver = new AirPassengerExpenseLocationResolver($search);

        return new AirExpenseReviewBandService(
            null,
            null,
            $resolver,
            new AirExpenseReviewBandKeyMapper()
        );
    }
}
