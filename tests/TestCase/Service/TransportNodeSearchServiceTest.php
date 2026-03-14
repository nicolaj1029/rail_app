<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\StationSearchService;
use App\Service\TransportNodeSearchService;
use Cake\TestSuite\TestCase;

final class TransportNodeSearchServiceTest extends TestCase
{
    private TransportNodeSearchService $service;

    /** @var array<int,string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransportNodeSearchService(CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_nodes.json');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testSearchFindsFerryTerminal(): void
    {
        $rows = $this->service->search('ferry', 'Helsingør', null, 5);
        $this->assertNotEmpty($rows);
        $this->assertSame('ferry', $rows[0]['mode']);
        $this->assertStringContainsString('Helsingør', $rows[0]['name']);
    }

    public function testSearchFindsAirportByCode(): void
    {
        $rows = $this->service->search('air', 'CPH', null, 5);
        $this->assertNotEmpty($rows);
        $this->assertSame('CPH', $rows[0]['code']);
        $this->assertSame('air', $rows[0]['mode']);
    }

    public function testSearchRespectsCountryFilter(): void
    {
        $rows = $this->service->search('air', 'Aachen', 'DE', 10);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('DE', $row['country']);
        }
    }

    public function testSearchPrefersFerryPortBeforeRouteNamedTerminal(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'port-helsingor',
                'mode' => 'ferry',
                'name' => 'Helsingør',
                'aliases' => ['Helsingor'],
                'country' => 'DK',
                'node_type' => 'port',
                'source' => 'test',
            ],
            [
                'id' => 'terminal-route',
                'mode' => 'ferry',
                'name' => 'Helsingør-Helsingborg',
                'country' => null,
                'node_type' => 'ferry_terminal',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($path);
        $rows = $service->search('ferry', 'Helsingor', null, 5);

        $this->assertNotEmpty($rows);
        $this->assertSame('Helsingør', $rows[0]['name']);
    }

    public function testSearchFallsBackToStationsForBus(): void
    {
        $transportPath = $this->writeTempJson([
            [
                'id' => 'irrelevant-air',
                'mode' => 'air',
                'name' => 'Copenhagen Kastrup Airport',
                'country' => 'DK',
                'node_type' => 'airport',
                'source' => 'test',
            ],
        ]);
        $stationsPath = $this->writeTempJson([
            [
                'name' => 'Odense',
                'country' => 'DK',
                'lat' => 55.4038,
                'lon' => 10.4024,
                'type' => 'station',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($transportPath, new StationSearchService($stationsPath));
        $rows = $service->search('bus', 'Odense', null, 5);

        $this->assertNotEmpty($rows);
        $this->assertSame('bus', $rows[0]['mode']);
        $this->assertSame('Odense', $rows[0]['name']);
        $this->assertSame('rail_station_fallback', $rows[0]['source']);
        $this->assertTrue((bool)$rows[0]['in_eu']);
    }

    public function testSearchFindsAirportByLocalAlias(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'air-cph',
                'mode' => 'air',
                'name' => 'Copenhagen Kastrup Airport',
                'city' => 'Copenhagen',
                'code' => 'CPH',
                'country' => 'DK',
                'node_type' => 'airport',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($path);
        $rows = $service->search('air', 'Kobenhavn', null, 5);

        $this->assertNotEmpty($rows);
        $this->assertSame('CPH', $rows[0]['code']);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function writeTempJson(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'railapp_nodes_');
        $this->assertIsString($path);
        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->tempFiles[] = $path;

        return $path;
    }
}
