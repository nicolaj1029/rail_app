<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\OperatorCatalog;
use App\Service\TransportOperatorRegistry;
use Cake\TestSuite\TestCase;

final class OperatorCatalogIdentityTest extends TestCase
{
    public function testFindsNorwegianAirSwedenByBoardingPassText(): void
    {
        $catalog = new OperatorCatalog();
        $match = $catalog->findIdentity('NORWEGIAN AIR SWEDEN AOC AB D83639', 'air');

        self::assertNotNull($match);
        self::assertSame('norwegian_air_sweden', $match['key']);
        self::assertSame('Norwegian Air Sweden', $match['name']);
        self::assertSame('code', $match['match_type']);
    }

    public function testFindsOperatorByAirIataCode(): void
    {
        $catalog = new OperatorCatalog();
        $match = $catalog->findByCode('D8', 'air');

        self::assertNotNull($match);
        self::assertSame('Norwegian Air Sweden', $match['name']);
        self::assertSame('SE', $match['country']);
    }

    public function testRegistryFindsByIdentityAndCode(): void
    {
        $registry = new TransportOperatorRegistry();

        $byIdentity = $registry->findByIdentity('air', 'Norwegian Air Sweden AOC AB');
        self::assertNotNull($byIdentity);
        self::assertSame('Norwegian Air Sweden', $byIdentity['name']);

        $byCode = $registry->findByCode('air', 'D8');
        self::assertNotNull($byCode);
        self::assertSame('Norwegian Air Sweden', $byCode['name']);
        self::assertSame('SE', $byCode['country_code']);
    }
}
