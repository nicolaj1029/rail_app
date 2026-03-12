<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\MultimodalFlowResolver;
use PHPUnit\Framework\TestCase;

final class MultimodalFlowResolverTest extends TestCase
{
    public function testDefaultsToRailWhenTransportModeMissing(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => ['operator' => 'DSB', 'dep_station' => 'Odense', 'arr_station' => 'Kobenhavn'],
            'meta' => [],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('rail', $result['transport_mode']);
        $this->assertSame('rail', $result['contract_meta']['rights_module']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
    }

    public function testEvaluatesFerryScopeContractAndRights(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Example Ferry',
                'ticket_no' => 'FERRY-1',
                'dep_station' => 'Helsingor',
                'arr_station' => 'Helsingborg',
                'service_type' => 'passenger_service',
                'departure_from_terminal' => 'yes',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
                'vessel_passenger_capacity' => '200',
                'vessel_operational_crew' => '12',
                'route_distance_meters' => '10000',
                'incident_main' => 'delay',
                'actual_departure_delay_90' => 'yes',
                'arrival_delay_minutes' => '130',
                'scheduled_journey_duration_minutes' => '300',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'delay'],
        ]);

        $this->assertSame('ferry', $result['transport_mode']);
        $this->assertTrue($result['ferry_scope']['regulation_applies']);
        $this->assertSame('carrier', $result['ferry_contract']['primary_claim_party']);
        $this->assertTrue($result['ferry_rights']['gate_art18']);
        $this->assertTrue($result['ferry_rights']['gate_art19']);
        $this->assertSame('25', $result['ferry_rights']['art19_comp_band']);
        $this->assertSame('ferry', $result['claim_direction']['rights_module']);
    }

    public function testEvaluatesBusContractDirection(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'bus',
                'operator' => 'Ticket Seller',
                'ticket_no' => 'BUS-1',
                'dep_station' => 'Odense',
                'arr_station' => 'Aarhus',
                'incident_segment_mode' => 'bus',
                'incident_segment_operator' => 'FlixBus',
                'single_txn_retailer' => 'yes',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
                'bus_regular_service' => 'yes',
                'boarding_in_eu' => 'yes',
                'alighting_in_eu' => 'yes',
                'departure_from_terminal' => 'yes',
                'scheduled_distance_km' => '320',
                'incident_main' => 'delay',
                'delay_minutes_departure' => '130',
                'scheduled_journey_duration_minutes' => '240',
                'carrier_offered_choice' => 'no',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'delay'],
        ]);

        $this->assertSame('bus', $result['transport_mode']);
        $this->assertTrue($result['bus_scope']['regulation_applies']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertSame('bus', $result['bus_contract']['rights_module']);
        $this->assertTrue($result['bus_rights']['gate_bus_reroute_refund']);
        $this->assertTrue($result['bus_rights']['gate_bus_compensation_50']);
        $this->assertSame('bus', $result['claim_direction']['rights_module']);
        $this->assertContains('reroute_or_refund_evidence', $result['claim_direction']['recommended_documents']);
        $this->assertContains('operator_connection_or_terminal_evidence', $result['claim_direction']['recommended_documents']);
    }

    public function testEvaluatesAirContractDirection(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'air',
                'operator' => 'SAS',
                'ticket_no' => 'PNR-1',
                'dep_station' => 'CPH',
                'arr_station' => 'ARN',
                'departure_airport_in_eu' => 'yes',
                'arrival_airport_in_eu' => 'yes',
                'operating_carrier_is_eu' => 'yes',
                'incident_segment_mode' => 'air',
                'incident_segment_operator' => 'SAS',
                'single_txn_operator' => 'yes',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
                'same_pnr' => 'yes',
                'incident_main' => 'cancellation',
                'arrival_delay_minutes' => '240',
                'reroute_arrival_delay_minutes' => '240',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'cancellation'],
        ]);

        $this->assertSame('air', $result['transport_mode']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertTrue($result['air_scope']['regulation_applies']);
        $this->assertSame('single_flight', $result['air_contract']['air_connection_type']);
        $this->assertSame('air', $result['air_contract']['rights_module']);
        $this->assertTrue($result['air_rights']['gate_air_reroute_refund']);
        $this->assertTrue($result['air_rights']['gate_air_compensation']);
        $this->assertSame('air', $result['claim_direction']['rights_module']);
        $this->assertContains('boarding_pass_or_pnr', $result['claim_direction']['recommended_documents']);
        $this->assertContains('arrival_delay_or_cancellation_evidence', $result['claim_direction']['recommended_documents']);
    }
}
