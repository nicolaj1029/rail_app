<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TransportNodeImportService;
use Cake\TestSuite\TestCase;

final class TransportNodeImportServiceTest extends TestCase
{
    private string $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->target = TMP . 'transport_nodes_import_test_' . uniqid('', true) . '.json';
        file_put_contents($this->target, json_encode([], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        @unlink($this->target);
        parent::tearDown();
    }

    public function testImportJsonAddsAirports(): void
    {
        $source = TMP . 'transport_nodes_air_' . uniqid('', true) . '.json';
        file_put_contents($source, json_encode([
            [
                'name' => 'Test Airport',
                'country' => 'DK',
                'code' => 'TST',
                'lat' => 55.1,
                'lon' => 12.1,
                'city' => 'Testby',
                'node_type' => 'airport',
            ],
        ], JSON_PRETTY_PRINT));

        $service = new TransportNodeImportService($this->target);
        $result = $service->import('air', $source, ['format' => 'json', 'replace' => true, 'source_label' => 'testjson']);

        $this->assertSame(1, $result['added']);
        $saved = json_decode((string)file_get_contents($this->target), true);
        $this->assertSame('air', $saved[0]['mode']);
        $this->assertSame('TST', $saved[0]['code']);
        $this->assertTrue($saved[0]['in_eu']);
        @unlink($source);
    }

    public function testImportCsvAddsBusTerminals(): void
    {
        $source = TMP . 'transport_nodes_bus_' . uniqid('', true) . '.csv';
        file_put_contents($source, implode("\n", [
            'name,country,code,lat,lon,city,node_type',
            'Test Bus Terminal,DE,TBT,53.55,10.00,Hamburg,terminal',
        ]));

        $service = new TransportNodeImportService($this->target);
        $result = $service->import('bus', $source, [
            'format' => 'csv',
            'replace' => true,
            'source_label' => 'testcsv',
        ]);

        $this->assertSame(1, $result['added']);
        $saved = json_decode((string)file_get_contents($this->target), true);
        $this->assertSame('bus', $saved[0]['mode']);
        $this->assertSame('terminal', $saved[0]['node_type']);
        $this->assertTrue($saved[0]['in_eu']);
        @unlink($source);
    }

    public function testImportCanFilterRowsByTypeAndRequireCode(): void
    {
        $source = TMP . 'transport_nodes_air_filter_' . uniqid('', true) . '.csv';
        file_put_contents($source, implode("\n", [
            'name,iso_country,iata_code,latitude_deg,longitude_deg,municipality,type',
            'Big Airport,DK,CPH,55.6,12.6,Copenhagen,large_airport',
            'Medium Without Code,SE,,59.6,17.9,Stockholm,medium_airport',
            'Small Airport,DK,STA,55.0,12.0,Town,small_airport',
        ]));

        $service = new TransportNodeImportService($this->target);
        $result = $service->import('air', $source, [
            'format' => 'csv',
            'replace' => true,
            'source_label' => 'ourairports',
            'name_col' => 'name',
            'country_col' => 'iso_country',
            'code_col' => 'iata_code',
            'lat_col' => 'latitude_deg',
            'lon_col' => 'longitude_deg',
            'city_col' => 'municipality',
            'default_node_type' => 'airport',
            'require_code' => true,
            'filter_col' => 'type',
            'filter_allow' => ['large_airport', 'medium_airport'],
        ]);

        $this->assertSame(1, $result['added']);
        $saved = json_decode((string)file_get_contents($this->target), true);
        $this->assertCount(1, $saved);
        $this->assertSame('CPH', $saved[0]['code']);
        $this->assertSame('Big Airport', $saved[0]['name']);
        @unlink($source);
    }

    public function testImportCanTransformUnlocodePorts(): void
    {
        $source = TMP . 'transport_nodes_ferry_unlocode_' . uniqid('', true) . '.csv';
        file_put_contents($source, implode("\n", [
            'Change,Country,Location,Name,NameWoDiacritics,Subdivision,Status,Function,Date,IATA,Coordinates,Remarks',
            ',DK,CPH,Copenhagen,Copenhagen,,AI,1-3-----,--34-6--,,5538N 01234E,',
            ',DK,AAL,Aalborg,Aalborg,,AI,--3-----,--34-6--,,5703N 00955E,',
        ]));

        $service = new TransportNodeImportService($this->target);
        $result = $service->import('ferry', $source, [
            'format' => 'csv',
            'replace' => true,
            'source_label' => 'unlocode',
            'source_transform' => 'unlocode_ports',
            'name_col' => 'name',
            'country_col' => 'country',
            'code_col' => 'locode',
            'lat_col' => 'lat',
            'lon_col' => 'lon',
            'city_col' => 'city',
            'node_type_col' => 'node_type',
            'aliases_col' => 'aliases',
            'default_node_type' => 'port',
        ]);

        $this->assertSame(1, $result['added']);
        $saved = json_decode((string)file_get_contents($this->target), true);
        $this->assertCount(1, $saved);
        $this->assertSame('ferry', $saved[0]['mode']);
        $this->assertSame('DKCPH', $saved[0]['code']);
        $this->assertSame(55.633333, $saved[0]['lat']);
        $this->assertSame(12.566667, $saved[0]['lon']);
        @unlink($source);
    }
}
