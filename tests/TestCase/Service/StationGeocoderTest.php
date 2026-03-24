<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\StationGeocoder;
use Cake\TestSuite\TestCase;

final class StationGeocoderTest extends TestCase
{
    /** @var array<int,string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    public function testLookupLoadsTopLevelListFormat(): void
    {
        $service = new StationGeocoder($this->writeTempJson([
            [
                'name' => 'Aarhus',
                'lat' => 56.15,
                'lon' => 10.21,
            ],
        ]));

        $result = $service->lookup('Aarhus');

        $this->assertSame(['lat' => 56.15, 'lon' => 10.21], $result);
        $this->assertSame(['lat' => 56.15, 'lon' => 10.21], $service->lookup('Aarhus Station'));
    }

    public function testNearestReturnsClosestStation(): void
    {
        $service = new StationGeocoder($this->writeTempJson([
            [
                'name' => 'Copenhagen Central',
                'lat' => 55.6727,
                'lon' => 12.5646,
            ],
            [
                'name' => 'Odense',
                'lat' => 55.4038,
                'lon' => 10.4024,
            ],
        ]));

        $nearest = $service->nearest(55.6728, 12.5645, 500);

        $this->assertNotNull($nearest);
        $this->assertSame('Copenhagen Central', $nearest['name']);
        $this->assertLessThan(100.0, $nearest['distance_m']);
    }

    public function testLookupSupportsLegacyMapFormat(): void
    {
        $service = new StationGeocoder($this->writeTempJson([
            'Malmo' => [
                'lat' => 55.609,
                'lon' => 13.0008,
            ],
        ]));

        $this->assertSame(['lat' => 55.609, 'lon' => 13.0008], $service->lookup('Malmo'));
        $this->assertSame(['lat' => 55.609, 'lon' => 13.0008], $service->lookup('Malmo C'));
    }

    /**
     * @param array<mixed> $rows
     */
    private function writeTempJson(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'railapp_station_geo_');
        $this->assertIsString($path);
        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->tempFiles[] = $path;

        return $path;
    }
}
