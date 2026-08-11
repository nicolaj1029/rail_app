<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TransportDataPaths;
use App\Service\TransportNodeSearchService;
use Cake\TestSuite\TestCase;

final class AirportSearchBrusselsTest extends TestCase
{
    /** @var array<int,string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testBrusselsLocalizedAliasesAndExactCodesHaveStableRanking(): void
    {
        $service = new TransportNodeSearchService(TransportDataPaths::transportNodesSearch('air'));

        $this->assertSame(['BRU', 'CRL'], array_slice(array_column($service->search('air', 'Bruxelles', null, 8), 'code'), 0, 2));
        $this->assertSame(['BRU', 'CRL'], array_slice(array_column($service->search('air', 'Brussel', null, 8), 'code'), 0, 2));
        $this->assertSame(['BRU', 'CRL'], array_slice(array_column($service->search('air', 'Brussels', null, 8), 'code'), 0, 2));
        $this->assertSame('BRU', $service->search('air', 'BRU', null, 8)[0]['code']);
        $this->assertSame('CRL', $service->search('air', 'CRL', null, 8)[0]['code']);
    }

    public function testExistingAirportMetroAndCodeSearchesRemainAvailable(): void
    {
        $service = new TransportNodeSearchService(TransportDataPaths::transportNodesSearch('air'));

        $this->assertSame(['ARN', 'BMA', 'NYO', 'VST'], array_slice(array_column($service->search('air', 'Stockholm', null, 8), 'code'), 0, 4));
        $this->assertSame('ARN', $service->search('air', 'ARN', null, 8)[0]['code']);
        $this->assertSame('CPH', $service->search('air', 'Copenhagen', null, 8)[0]['code']);
        $this->assertSame('CPH', $service->search('air', 'CPH', null, 8)[0]['code']);
        $this->assertContains('LHR', array_column($service->search('air', 'London', null, 8), 'code'));
        $this->assertSame('LHR', $service->search('air', 'LHR', null, 8)[0]['code']);
        $this->assertContains('CDG', array_column($service->search('air', 'Paris', null, 8), 'code'));
        $this->assertSame('CDG', $service->search('air', 'CDG', null, 8)[0]['code']);
    }

    public function testDatasetSignatureInvalidatesCachedEmptyAliasResult(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airport_cache_signature_');
        $this->assertIsString($path);
        $this->tempFiles[] = $path;

        $this->writeRows($path, [[
            'id' => 'bru',
            'mode' => 'air',
            'name' => 'Brussels Airport',
            'aliases' => [],
            'code' => 'BRU',
            'country' => 'BE',
            'node_type' => 'airport',
        ]]);
        $service = new TransportNodeSearchService($path);
        $this->assertSame([], $service->search('air', 'Bruxelles', null, 8));

        $this->writeRows($path, [[
            'id' => 'bru',
            'mode' => 'air',
            'name' => 'Brussels Airport',
            'aliases' => ['Bruxelles'],
            'code' => 'BRU',
            'country' => 'BE',
            'node_type' => 'airport',
        ]]);

        $rows = $service->search('air', 'Bruxelles', null, 8);
        $this->assertSame('BRU', $rows[0]['code']);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function writeRows(string $path, array $rows): void
    {
        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        clearstatcache(true, $path);
        touch($path, time() + 2);
        clearstatcache(true, $path);
    }
}
