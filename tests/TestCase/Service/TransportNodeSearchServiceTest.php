<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TransportNodeSearchService;
use Cake\TestSuite\TestCase;

final class TransportNodeSearchServiceTest extends TestCase
{
    private TransportNodeSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransportNodeSearchService(CONFIG . 'data' . DIRECTORY_SEPARATOR . 'transport_nodes.json');
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
        $rows = $this->service->search('bus', 'ZOB', 'DE', 10);
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('DE', $row['country']);
        }
    }
}
