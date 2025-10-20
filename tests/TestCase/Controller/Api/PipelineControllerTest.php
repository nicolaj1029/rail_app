<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class PipelineControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testRunUnifiedPipeline(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $payload = [
            'text' => 'Cheapest fare shown. Alternatives shown. MCT respected. Wifi and Quiet zone. Train: ICE 123',
            'journey' => [
                'segments' => [[ 'operator' => 'DB', 'trainCategory' => 'ICE', 'country' => 'DE', 'schedArr' => '2025-10-11T19:00:00', 'actArr' => '2025-10-11T20:15:00' ]],
                'ticketPrice' => ['value' => '49.90 EUR'],
                'country' => ['value' => 'DE']
            ],
            'art12_meta' => ['through_ticket_disclosure' => 'Gennemgående'],
            'art9_meta' => ['info_on_rights' => 'Delvist', 'preinformed_disruption' => 'Nej'],
            'compute' => ['euOnly' => true, 'minPayout' => 4.0]
        ];
        $this->post('/api/pipeline/run', $payload);
        $this->assertResponseOk();
        $json = json_decode((string)$this->_response->getBody(), true) ?: [];
        $this->assertArrayHasKey('art9', $json);
        $this->assertArrayHasKey('compensation', $json);
        $this->assertSame('EUR', $json['compensation']['currency'] ?? null);
        $this->assertArrayHasKey('claim', $json);
    }
}
