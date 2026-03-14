<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TransportNodeDerivationService;
use Cake\TestSuite\TestCase;

final class TransportNodeDerivationServiceTest extends TestCase
{
    public function testDeriveFerryFieldsFromLookupMetadata(): void
    {
        $service = new TransportNodeDerivationService();

        $out = $service->derive([
            'transport_mode' => 'ferry',
            'operator_country' => 'DK',
            'dep_station_lookup_in_eu' => 'yes',
            'dep_station_lookup_node_type' => 'ferry_terminal',
            'dep_station_lookup_lat' => '55.708',
            'dep_station_lookup_lon' => '12.592',
            'arr_station_lookup_in_eu' => 'yes',
            'arr_station_lookup_lat' => '56.039',
            'arr_station_lookup_lon' => '12.706',
        ]);

        self::assertSame('yes', $out['departure_port_in_eu']);
        self::assertSame('yes', $out['arrival_port_in_eu']);
        self::assertSame('yes', $out['departure_from_terminal']);
        self::assertSame('yes', $out['carrier_is_eu']);
        self::assertNotEmpty($out['route_distance_meters']);
        self::assertSame('ferry', $out['incident_segment_mode']);
    }

    public function testDeriveBusFieldsFromLookupMetadata(): void
    {
        $service = new TransportNodeDerivationService();

        $out = $service->derive([
            'transport_mode' => 'bus',
            'dep_station_lookup_in_eu' => 'yes',
            'dep_station_lookup_node_type' => 'terminal',
            'dep_station_lookup_lat' => '55.676',
            'dep_station_lookup_lon' => '12.568',
            'arr_station_lookup_in_eu' => 'yes',
            'arr_station_lookup_lat' => '56.162',
            'arr_station_lookup_lon' => '10.204',
        ]);

        self::assertSame('yes', $out['boarding_in_eu']);
        self::assertSame('yes', $out['alighting_in_eu']);
        self::assertSame('yes', $out['departure_from_terminal']);
        self::assertNotEmpty($out['scheduled_distance_km']);
        self::assertSame('bus', $out['incident_segment_mode']);
    }

    public function testDeriveBusFieldsCanSetNoForNonTerminal(): void
    {
        $service = new TransportNodeDerivationService();

        $out = $service->derive([
            'transport_mode' => 'bus',
            'dep_station_lookup_in_eu' => 'yes',
            'dep_station_lookup_node_type' => 'stop',
            'arr_station_lookup_in_eu' => 'no',
        ]);

        self::assertSame('yes', $out['boarding_in_eu']);
        self::assertSame('no', $out['alighting_in_eu']);
        self::assertSame('no', $out['departure_from_terminal']);
    }

    public function testDeriveAirFieldsFromLookupMetadata(): void
    {
        $service = new TransportNodeDerivationService();

        $out = $service->derive([
            'transport_mode' => 'air',
            'dep_station_lookup_in_eu' => 'yes',
            'arr_station_lookup_in_eu' => 'no',
        ]);

        self::assertSame('yes', $out['departure_airport_in_eu']);
        self::assertSame('no', $out['arrival_airport_in_eu']);
        self::assertSame('air', $out['incident_segment_mode']);
    }

    public function testDerivesOperatorMetadataFromRegistry(): void
    {
        $service = new TransportNodeDerivationService();

        $ferry = $service->derive([
            'transport_mode' => 'ferry',
            'operator' => 'Scandlines',
        ]);
        self::assertSame('DK', $ferry['operator_country']);
        self::assertSame('yes', $ferry['carrier_is_eu']);

        $air = $service->derive([
            'transport_mode' => 'air',
            'operating_carrier' => 'SK',
            'marketing_carrier' => 'British Airways',
        ]);
        self::assertSame('DK', $air['operator_country']);
        self::assertSame('yes', $air['operating_carrier_is_eu']);
        self::assertSame('no', $air['marketing_carrier_is_eu']);

        $bus = $service->derive([
            'transport_mode' => 'bus',
            'operator' => 'FlixBus',
        ]);
        self::assertSame('DE', $bus['operator_country']);
    }
}
