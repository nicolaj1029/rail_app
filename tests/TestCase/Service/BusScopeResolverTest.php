<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\BusScopeResolver;
use PHPUnit\Framework\TestCase;

final class BusScopeResolverTest extends TestCase
{
    public function testRegularServiceOver250KmInEuIsInScope(): void
    {
        $result = (new BusScopeResolver())->evaluate([
            'bus_regular_service' => true,
            'boarding_in_eu' => true,
            'alighting_in_eu' => false,
            'departure_from_terminal' => true,
            'scheduled_distance_km' => 320,
        ]);

        $this->assertTrue($result['regulation_applies']);
        $this->assertSame('regular_service_250km_plus', $result['scope_basis']);
        $this->assertNull($result['scope_exclusion_reason']);
    }

    public function testShortDistanceServiceIsOutOfScope(): void
    {
        $result = (new BusScopeResolver())->evaluate([
            'bus_regular_service' => true,
            'boarding_in_eu' => true,
            'alighting_in_eu' => true,
            'departure_from_terminal' => true,
            'scheduled_distance_km' => 120,
        ]);

        $this->assertFalse($result['regulation_applies']);
        $this->assertSame('outside_bus_181_2011_scope', $result['scope_exclusion_reason']);
    }
}
