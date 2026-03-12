<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FerryScopeResolver;
use Cake\TestSuite\TestCase;

final class FerryScopeResolverTest extends TestCase
{
    public function testPassengerServiceFromEuDepartureIsInScope(): void
    {
        $result = (new FerryScopeResolver())->evaluate([
            'service_type' => 'passenger_service',
            'departure_port_in_eu' => true,
            'arrival_port_in_eu' => false,
            'carrier_is_eu' => false,
            'departure_from_terminal' => true,
            'vessel_passenger_capacity' => 150,
            'vessel_operational_crew' => 6,
            'route_distance_meters' => 12000,
        ]);

        $this->assertTrue($result['regulation_applies']);
        $this->assertSame('departure_eu', $result['scope_basis']);
        $this->assertFalse($result['cruise_carveout']);
        $this->assertNull($result['scope_exclusion_reason']);
    }

    public function testCruiseFromEuDepartureAppliesWithCarveOut(): void
    {
        $result = (new FerryScopeResolver())->evaluate([
            'service_type' => 'cruise',
            'departure_port_in_eu' => true,
            'departure_from_terminal' => true,
            'vessel_passenger_capacity' => 500,
            'vessel_operational_crew' => 30,
            'route_distance_meters' => 10000,
        ]);

        $this->assertTrue($result['regulation_applies']);
        $this->assertTrue($result['cruise_carveout']);
        $this->assertFalse($result['articles']['art18']);
        $this->assertFalse($result['articles']['art19']);
    }

    public function testDistanceUnderFiveHundredMetersIsOutOfScope(): void
    {
        $result = (new FerryScopeResolver())->evaluate([
            'service_type' => 'passenger_service',
            'departure_port_in_eu' => true,
            'departure_from_terminal' => true,
            'vessel_passenger_capacity' => 150,
            'vessel_operational_crew' => 6,
            'route_distance_meters' => 450,
        ]);

        $this->assertFalse($result['regulation_applies']);
        $this->assertSame('route_distance_under_500m', $result['scope_exclusion_reason']);
    }
}
