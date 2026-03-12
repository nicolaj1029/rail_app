<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirScopeResolver;
use PHPUnit\Framework\TestCase;

final class AirScopeResolverTest extends TestCase
{
    public function testDepartureFromEuIsInScope(): void
    {
        $result = (new AirScopeResolver())->evaluate([
            'departure_airport_in_eu' => true,
            'arrival_airport_in_eu' => false,
            'operating_carrier_is_eu' => false,
        ]);

        $this->assertTrue($result['regulation_applies']);
        $this->assertSame('departure_eu', $result['scope_basis']);
    }

    public function testArrivalInEuWithEuCarrierIsInScope(): void
    {
        $result = (new AirScopeResolver())->evaluate([
            'departure_airport_in_eu' => false,
            'arrival_airport_in_eu' => true,
            'operating_carrier_is_eu' => true,
        ]);

        $this->assertTrue($result['regulation_applies']);
        $this->assertSame('arrival_eu_eu_operating_carrier', $result['scope_basis']);
    }

    public function testOutsideScopeWhenNeitherHookApplies(): void
    {
        $result = (new AirScopeResolver())->evaluate([
            'departure_airport_in_eu' => false,
            'arrival_airport_in_eu' => true,
            'operating_carrier_is_eu' => false,
        ]);

        $this->assertFalse($result['regulation_applies']);
        $this->assertSame('outside_ec261_scope', $result['scope_exclusion_reason']);
    }
}
