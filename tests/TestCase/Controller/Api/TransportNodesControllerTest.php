<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class TransportNodesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testAirAutocompleteIsExplicitlyLocalOnly(): void
    {
        $this->get('/api/transport-nodes/search?mode=air&q=Stockholm&limit=10');

        $this->assertResponseOk();
        $this->assertHeader('X-Airport-Search-Source', 'local');
        $this->assertHeaderContains('Server-Timing', 'airport;dur=');

        $payload = json_decode((string)$this->_response->getBody(), true);
        $nodes = (array)($payload['data']['nodes'] ?? []);
        $this->assertNotContains('aerodatabox', array_column($nodes, 'source'));
    }

    public function testAirAutocompleteAcceptsTwoCharacters(): void
    {
        $this->get('/api/transport-nodes/search?mode=air&q=St&limit=10');

        $this->assertResponseOk();
        $this->assertHeader('X-Airport-Search-Source', 'local');
    }
}
