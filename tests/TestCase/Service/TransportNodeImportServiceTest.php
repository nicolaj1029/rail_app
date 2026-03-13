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
}
