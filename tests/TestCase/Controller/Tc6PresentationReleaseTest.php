<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class Tc6PresentationReleaseTest extends TestCase
{
    use IntegrationTestTrait;

    public function testCanonicalLandingPagesExposeTc6Entries(): void
    {
        foreach (['/fly-ny?lang=fr', '/tog-ny', '/faerge-ny'] as $url) {
            $this->get($url);
            $this->assertResponseOk($url);
            $this->assertResponseContains('tc6=1', $url);
        }
    }

    public function testAirEntryPersistsTc6ModeTransportAndFrench(): void
    {
        $this->get('/flow/air/completed?tc6=1&lang=fr');

        $this->assertRedirectContains('/flow/entitlements');
        $this->assertSession('1', 'flow.tc6_mode');
        $this->assertSession('fr', 'flow.ui_language');
        $this->assertSession('air', 'flow.form.transport_mode');
        $this->assertSession('air_short', 'flow.flags.entry_variant');
    }

    public function testFreshTransportSessionsRenderOnlyCanonicalTc6Shell(): void
    {
        foreach ([
            'air' => ['air', 'air_short', 'AirClaim'],
            'rail' => ['rail', 'rail_split', 'TrainClaim'],
            'ferry' => ['ferry', 'ferry_split', 'FerryClaim'],
        ] as [$mode, $variant, $brand]) {
            $this->session($this->flowSession($mode, $variant));
            $this->get('/flow/entitlements');

            $this->assertResponseOk();
            $body = (string)$this->_response->getBody();
            $this->assertStringContainsString('class="tc6-shell', $body);
            $this->assertStringContainsString('class="tc6-sidebar"', $body);
            $this->assertStringContainsString('class="tc6-right', $body);
            $this->assertStringContainsString($brand, $body);
            $this->assertStringNotContainsString('class="flow-layout"', $body);
        }
    }

    public function testFrenchAirTc6UsesSafeTranslatedMarkupAndSevenSteps(): void
    {
        $session = $this->flowSession('air', 'air_short');
        $session['flow.ui_language'] = 'fr';
        $this->session($session);
        $this->get('/flow/entitlements');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('<html lang="fr">', $body);
        $this->assertStringContainsString('Mettons en place votre trajet aerien', $body);
        preg_match_all('/<(?:a|span)[^>]+class="tc6-step(?:\s|\")/', $body, $matches);
        $this->assertCount(7, $matches[0]);
        $this->assertStringNotContainsString('strtr(', $body);
    }

    public function testAirReservationStepPersistsTc6AndContinuesToFlightMatch(): void
    {
        $session = $this->flowSession('air', 'air_short');
        $session['flow.flags']['step2_done'] = '1';
        $session['flow.form'] += [
            'dep_station' => 'Brussels (BRU)',
            'arr_station' => 'London Heathrow (LHR)',
            'dep_station_lookup_code' => 'BRU',
            'arr_station_lookup_code' => 'LHR',
            'air_route_type' => 'direct',
        ];
        $this->session($session);
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->post('/flow/air-reservation-contract', ['seller_channel' => 'operator']);

        $this->assertRedirectContains('/flow/air-flight-select');
        $this->assertSession('1', 'flow.flags.step24_done');
        $this->assertSession('1', 'flow.tc6_mode');
        $this->assertSession('BRU', 'flow.meta.air_selected_leg.dep_iata');
        $this->assertSession('LHR', 'flow.meta.air_selected_leg.arr_iata');
    }

    public function testAirReservationPresentationAssetsExistAndAreLinked(): void
    {
        $session = $this->flowSession('air', 'air_short');
        $session['flow.flags']['step2_done'] = '1';
        $session['flow.form'] += [
            'dep_station' => 'Brussels Airport',
            'arr_station' => 'London Heathrow Airport',
            'dep_station_lookup_code' => 'BRU',
            'arr_station_lookup_code' => 'LHR',
            'air_route_type' => 'direct',
        ];
        $this->session($session);

        $this->get('/flow/air-reservation-contract');

        $this->assertResponseOk();
        $this->assertResponseContains('/css/flow-form-steps.css');
        $this->assertResponseContains('/css/flow-select-steps.css');
        $this->assertFileExists(WWW_ROOT . 'css' . DS . 'flow-form-steps.css');
        $this->assertFileExists(WWW_ROOT . 'css' . DS . 'flow-select-steps.css');
    }

    /**
     * @return array<string,mixed>
     */
    private function flowSession(string $mode, string $variant): array
    {
        return [
            'flow.tc6_mode' => '1',
            'flow.ui_language' => 'da',
            'flow.flags' => [
                'step1_done' => '1',
                'travel_state' => 'completed',
                'transport_mode' => $mode,
                'gating_mode' => $mode,
                'entry_variant' => $variant,
            ],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
                'transport_mode' => $mode,
                'transport_mode_source' => 'manual',
                'gating_mode' => $mode,
            ],
            'flow.meta' => [
                'transport_mode' => $mode,
                'transport_mode_source' => 'manual',
                'gating_mode' => $mode,
                'entry_variant' => $variant,
            ],
            'flow.journey' => [
                'transport_mode' => $mode,
                'gating_mode' => $mode,
            ],
            'flow.compute' => ['euOnly' => true],
        ];
    }
}
