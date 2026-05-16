<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Rail\RailDepartureNormalizer;
use App\Service\Rail\RailTransportServiceClient;
use App\Service\Rail\RailStationLookupService;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

final class RailStationLookupServiceTest extends TestCase
{
    private mixed $originalLiveApis;
    private mixed $originalHafasEnabled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLiveApis = Configure::read('External.useLiveApis');
        $this->originalHafasEnabled = Configure::read('Rail.hafas.enabled');
        Configure::write('External.useLiveApis', false);
        Configure::write('Rail.hafas.enabled', false);
    }

    protected function tearDown(): void
    {
        Configure::write('External.useLiveApis', $this->originalLiveApis);
        Configure::write('Rail.hafas.enabled', $this->originalHafasEnabled);
        parent::tearDown();
    }

    public function testSearchFallsBackToMajorStationCatalogForAliasQuery(): void
    {
        $service = new RailStationLookupService();

        $rows = $service->search('Brussels Midi', null, 8);

        $this->assertNotEmpty($rows);
        $this->assertSame('Bruxelles Midi', $rows[0]['name']);
        $this->assertSame('major_station_catalog', $rows[0]['source']);
        $this->assertSame('station', $rows[0]['type']);
    }

    public function testSearchUsesAliasExpansionForCityQuery(): void
    {
        $service = new RailStationLookupService();

        $rows = $service->search('Copenhagen', null, 8);

        $this->assertNotEmpty($rows);
        $this->assertSame('Kobenhavn H', $rows[0]['name']);
        $this->assertSame('major_station_catalog', $rows[0]['source']);
    }

    public function testNormalizerExpandsStationAliases(): void
    {
        $normalizer = new RailDepartureNormalizer();

        $queries = $normalizer->expandStationQueries('Kbh H');

        $this->assertContains('Kbh H', $queries);
        $this->assertContains('Kobenhavn H', $queries);
        $this->assertContains('Copenhagen H', $queries);
    }

    public function testSearchPrefersTransportServiceHafasIdOverCatalogDuplicate(): void
    {
        $hafasProvider = new class extends \App\Service\Rail\HafasRailLocationProvider {
            public function __construct()
            {
            }

            public function searchLocations(string $query, ?string $country = null, int $limit = 8): array
            {
                return [];
            }
        };

        $transportClient = new class extends RailTransportServiceClient {
            public function __construct()
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function searchStations(string $query, int $limit = 8, string $locale = 'da-DK'): array
            {
                return [
                    [
                        'id' => 'Barcelona Sants',
                        'name' => 'Barcelona Sants',
                        'type' => 'station',
                        'source' => 'catalog',
                        'country' => 'ES',
                    ],
                    [
                        'id' => '7100064',
                        'name' => 'Barcelona Sants',
                        'type' => 'station',
                        'source' => 'hafas',
                        'country' => 'ES',
                    ],
                ];
            }
        };

        $service = new RailStationLookupService($hafasProvider, null, null, $transportClient);

        $rows = $service->search('Barcelona Sants', null, 5);

        $this->assertNotEmpty($rows);
        $this->assertSame('7100064', $rows[0]['id']);
        $this->assertSame('hafas', $rows[0]['source']);
    }
}
