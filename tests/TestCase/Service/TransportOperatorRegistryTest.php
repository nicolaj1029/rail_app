<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TransportOperatorRegistry;
use Cake\TestSuite\TestCase;

final class TransportOperatorRegistryTest extends TestCase
{
    public function testDerivesFerryCarrierCountryAndEuFlag(): void
    {
        $registry = new TransportOperatorRegistry();

        self::assertSame('DK', $registry->deriveCountryCode('ferry', 'Scandlines'));
        self::assertTrue($registry->deriveEuFlag('ferry', 'Scandlines'));
    }

    public function testDerivesAirCarrierByAlias(): void
    {
        $registry = new TransportOperatorRegistry();

        self::assertSame('DK', $registry->deriveCountryCode('air', 'SK'));
        self::assertTrue($registry->deriveEuFlag('air', 'SAS'));
        self::assertFalse($registry->deriveEuFlag('air', 'British Airways'));
    }

    public function testReturnsModeSpecificNames(): void
    {
        $registry = new TransportOperatorRegistry();

        $ferry = $registry->namesByMode('ferry');
        $air = $registry->namesByMode('air');

        self::assertContains('Scandlines', $ferry);
        self::assertNotContains('FlixBus', $ferry);
        self::assertContains('SAS', $air);
        self::assertNotContains('Scandlines', $air);
    }

    public function testReturnsModeSpecificEntries(): void
    {
        $registry = new TransportOperatorRegistry();

        $entries = $registry->entriesByMode('ferry');
        $scandlines = array_values(array_filter($entries, static fn(array $entry): bool => $entry['name'] === 'Scandlines'));

        self::assertCount(1, $scandlines);
        self::assertSame('DK', $scandlines[0]['country_code']);
        self::assertTrue($scandlines[0]['is_eu_operator']);
    }
}
