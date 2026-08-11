<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AirFlightsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    private mixed $previousLiveApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousLiveApis = Configure::read('External.useLiveApis');
        Configure::write('External.useLiveApis', false);
        Cache::clear(Cache::getConfig('air_flights') !== null ? 'air_flights' : 'default');
    }

    protected function tearDown(): void
    {
        Configure::write('External.useLiveApis', $this->previousLiveApis);
        parent::tearDown();
    }

    public function testHealthSeparatesApplicationFromProviderConfiguration(): void
    {
        $this->get('/api/air/health');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('ok', $body['air']['application']);
        $this->assertArrayHasKey('providers', $body['air']);
        $this->assertArrayHasKey('live_provider_configured', $body['air']);
        $this->assertArrayHasKey('last_provider_outcome', $body['air']);
        $this->assertStringNotContainsString('apiKey', (string)$this->_response->getBody());
        $this->assertArrayNotHasKey('uiText', $body);
    }

    public function testInvalidLookupReturns422JsonWithoutCakeFatal(): void
    {
        $this->get('/api/air/flights/search?departure=CPH<script>&arrival=LHR&date=2026-02-31');
        $this->assertResponseCode(422);
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('invalid_request', $body['lookup_status']);
        $this->assertSame([], $body['items']);
    }

    public function testManualLookupResponseIsExplicitlyUnverified(): void
    {
        $this->get('/api/air/flights/search?departure=CPH&arrival=LHR&date=2026-09-01&flightNumber=SK%201501&depTime=08%3A00&arrTime=09%3A00');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('manual_fallback', $body['lookup_status']);
        $this->assertTrue($body['manual_fallback']);
        $this->assertFalse($body['items'][0]['operational_data_verified']);
        $this->assertNotSame('', $this->_response->getHeaderLine('X-Request-ID'));
    }
}
