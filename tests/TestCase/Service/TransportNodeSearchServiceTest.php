<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\StationSearchService;
use App\Service\TransportDataPaths;
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
        $this->service = new TransportNodeSearchService(TransportDataPaths::transportNodes());
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

    public function testAirSearchPrioritizesExactCodesAndSupportsIcao(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'air-arn',
                'mode' => 'air',
                'name' => 'Stockholm-Arlanda Airport',
                'code' => 'ARN',
                'iata_code' => 'ARN',
                'icao_code' => 'ESSA',
                'city' => 'Stockholm',
                'country' => 'SE',
                'in_eu' => true,
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
                'has_scheduled_passenger_service' => true,
            ],
            [
                'id' => 'air-arnborg',
                'mode' => 'air',
                'name' => 'Arnborg Gliding Center',
                'code' => 'EKAB',
                'icao_code' => 'EKAB',
                'city' => 'Arnborg',
                'country' => 'DK',
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
            ],
        ]);
        $service = new TransportNodeSearchService($path);

        $iataRows = $service->search('air', 'ARN', null, 5);
        $icaoRows = $service->search('air', 'ESSA', null, 5);

        $this->assertSame('air-arn', $iataRows[0]['id']);
        $this->assertSame('air-arn', $icaoRows[0]['id']);
        $this->assertSame('ARN', $icaoRows[0]['iata_code']);
        $this->assertSame('ESSA', $icaoRows[0]['icao_code']);
    }

    public function testAirSearchPrioritizesStockholmPassengerAirports(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'air-skavsta',
                'mode' => 'air',
                'name' => 'Stockholm Skavsta Airport',
                'code' => 'NYO',
                'city' => 'Nykoping',
                'country' => 'SE',
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
            ],
            [
                'id' => 'air-arn',
                'mode' => 'air',
                'name' => 'Stockholm-Arlanda Airport',
                'code' => 'ARN',
                'city' => 'Stockholm',
                'country' => 'SE',
                'in_eu' => true,
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
                'has_scheduled_passenger_service' => true,
            ],
            [
                'id' => 'air-bma',
                'mode' => 'air',
                'name' => 'Stockholm-Bromma Airport',
                'code' => 'BMA',
                'city' => 'Stockholm',
                'country' => 'SE',
                'in_eu' => true,
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
                'has_scheduled_passenger_service' => true,
            ],
        ]);
        $service = new TransportNodeSearchService($path);

        $rows = $service->search('air', 'Stockholm', null, 5);

        $this->assertSame(['ARN', 'BMA', 'NYO'], array_column($rows, 'code'));
    }

    public function testAirSearchUsesMetroAliasesForMajorAirports(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'air-local-paris',
                'mode' => 'air',
                'name' => 'Paris Municipal Airport',
                'code' => 'PRX',
                'city' => 'Paris',
                'country' => 'US',
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
            ],
            [
                'id' => 'air-cdg',
                'mode' => 'air',
                'name' => 'Charles de Gaulle International Airport',
                'code' => 'CDG',
                'iata_code' => 'CDG',
                'city' => 'Roissy-en-France',
                'country' => 'FR',
                'node_type' => 'airport',
                'allow_in_frontend_search' => true,
                'has_scheduled_passenger_service' => true,
            ],
        ]);
        $service = new TransportNodeSearchService($path);

        $rows = $service->search('air', 'Paris', null, 5);

        $this->assertSame('CDG', $rows[0]['code']);
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

        $service = new TransportNodeSearchService($path, null, 'disabled');
        $rows = $service->search('ferry', 'Helsingor', null, 5);

        $this->assertNotEmpty($rows);
        $this->assertSame('Helsingør', $rows[0]['name']);
    }

    public function testSearchFiltersRouteNamedFerryTerminalWhenExactPortExists(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'port-helsingor',
                'mode' => 'ferry',
                'name' => 'HelsingÃ¸r',
                'aliases' => ['Helsingor'],
                'country' => 'DK',
                'in_eu' => true,
                'node_type' => 'port',
                'source' => 'test',
            ],
            [
                'id' => 'terminal-route',
                'mode' => 'ferry',
                'name' => 'HelsingÃ¸r-Helsingborg',
                'country' => '',
                'in_eu' => false,
                'node_type' => 'ferry_terminal',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($path, null, 'disabled');
        $rows = $service->search('ferry', 'Helsingor', null, 5);

        $this->assertCount(1, $rows);
        $this->assertSame('HelsingÃ¸r', $rows[0]['name']);
    }

    public function testSearchEnrichesFerryTerminalEuMetadataFromMatchingPort(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'port-ystad',
                'mode' => 'ferry',
                'name' => 'Ystad',
                'country' => 'SE',
                'in_eu' => true,
                'node_type' => 'port',
                'source' => 'test',
            ],
            [
                'id' => 'terminal-ystad',
                'mode' => 'ferry',
                'name' => 'Ystad',
                'country' => '',
                'in_eu' => false,
                'node_type' => 'ferry_terminal',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($path, null, 'disabled');
        $rows = $service->search('ferry', 'Ystad', null, 5);

        $terminal = null;
        foreach ($rows as $row) {
            if (($row['node_type'] ?? null) === 'ferry_terminal') {
                $terminal = $row;
                break;
            }
        }

        $this->assertIsArray($terminal);
        $this->assertSame('SE', $terminal['country']);
        $this->assertTrue((bool)$terminal['in_eu']);
    }

    public function testSearchLoadsCuratedFerryTerminalFromSeedWorkbook(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'port-helsingor',
                'mode' => 'ferry',
                'name' => 'Helsingor',
                'country' => 'DK',
                'in_eu' => true,
                'node_type' => 'port',
                'source' => 'test',
            ],
        ]);
        $seedPath = $this->writeTempFerrySeedWorkbook([
            ['DK', 'Helsingor', 'Helsingor Ferry Terminal', 'ferry_terminal', 'yes', 'yes', 'manual_seed', '', 'Curated seed row'],
        ]);

        $service = new TransportNodeSearchService($path, null, $seedPath);
        $rows = $service->search('ferry', 'Helsingor', null, 5, 'terminal');

        $this->assertNotEmpty($rows);
        $this->assertSame('ferry_terminal', $rows[0]['node_type']);
        $this->assertSame('Helsingor Ferry Terminal', $rows[0]['name']);
        $this->assertSame('Helsingor', $rows[0]['parent_name']);
        $this->assertSame('manual_seed', $rows[0]['verification_status']);
    }

    public function testSearchCanPreferFerryTerminalForTerminalLookups(): void
    {
        $path = $this->writeTempJson([
            [
                'id' => 'port-helsingor',
                'mode' => 'ferry',
                'name' => 'Helsingor',
                'country' => 'DK',
                'in_eu' => true,
                'node_type' => 'port',
                'source' => 'test',
            ],
            [
                'id' => 'terminal-helsingor',
                'mode' => 'ferry',
                'name' => 'Helsingor Ferry Terminal',
                'country' => 'DK',
                'in_eu' => true,
                'node_type' => 'ferry_terminal',
                'parent_name' => 'Helsingor',
                'source' => 'test',
            ],
        ]);

        $service = new TransportNodeSearchService($path, null, 'disabled');
        $rows = $service->search('ferry', 'Helsingor', null, 5, 'terminal');

        $this->assertNotEmpty($rows);
        $this->assertSame('terminal-helsingor', $rows[0]['id']);
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

    /**
     * @param array<int,array<int,string>> $rows
     */
    private function writeTempFerrySeedWorkbook(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'railapp_seed_');
        $this->assertIsString($path);
        $xlsxPath = $path . '.xlsx';
        @unlink($path);

        $header = ['country_code', 'port_name', 'terminal_name', 'record_type', 'likely_havneterminal', 'passenger_services', 'verification_status', 'source_url', 'notes'];
        $sheetRows = [$header, ...$rows];

        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($sheetRows as $rowIndex => $row) {
            $xml .= '<row r="' . ($rowIndex + 1) . '">';
            foreach ($row as $cellIndex => $value) {
                $column = chr(ord('A') + $cellIndex);
                $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="' . $column . ($rowIndex + 1) . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $this->tempFiles[] = $xlsxPath;

        return $xlsxPath;
    }
}
