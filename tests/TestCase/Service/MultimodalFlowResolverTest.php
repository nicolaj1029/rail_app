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
    }
}
