<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class CanonicalProductLandingTest extends TestCase
{
    use IntegrationTestTrait;

    public function testCanonicalProductRoutesRenderAndLegacyAirRedirects(): void
    {
        $this->get('/fly-ny');
        $this->assertResponseOk();

        $this->get('/fly');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/fly-ny', $this->_response->getHeaderLine('Location'));

        $this->get('/tog-ny');
        $this->assertResponseOk();
        $this->assertStringContainsString('/flow/rail/completed?tc6=1', (string)$this->_response->getBody());

        $this->get('/faerge-ny');
        $this->assertResponseOk();
        $this->assertStringContainsString('/flow/ferry/completed?tc6=1', (string)$this->_response->getBody());
    }

    public function testRailEntrySetsCanonicalTransportMode(): void
    {
        $this->get('/flow/rail/completed?tc6=1');

        $this->assertResponseCode(302);
        $this->assertSession('rail', 'flow.form.transport_mode');
        $this->assertSession('completed', 'flow.flags.travel_state');
        $this->assertSession('rail_split', 'flow.flags.entry_variant');
    }

    public function testFerryEntrySetsCanonicalTransportMode(): void
    {
        $this->get('/flow/ferry/completed?tc6=1');

        $this->assertResponseCode(302);
        $this->assertSession('ferry', 'flow.form.transport_mode');
        $this->assertSession('completed', 'flow.flags.travel_state');
        $this->assertSession('ferry_split', 'flow.flags.entry_variant');
    }

    public function testLocalizedAirEntryPreservesDisplayQuery(): void
    {
        $this->get('/flow/air/completed?tc6=1&lang=fr&ignored=drop');

        $this->assertResponseCode(302);
        $location = $this->_response->getHeaderLine('Location');
        $this->assertStringContainsString('/flow/entitlements?', $location);
        $this->assertStringContainsString('tc6=1', $location);
        $this->assertStringContainsString('lang=fr', $location);
        $this->assertStringNotContainsString('ignored=', $location);
    }
}
