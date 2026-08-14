<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AdminDeskService;
use Cake\Datasource\FactoryLocator;
use Cake\Http\Session;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AirPublicLandingTest extends TestCase
{
    use IntegrationTestTrait;

    public function testFlyNyIsTheCanonicalModernAirLanding(): void
    {
        $this->get('/fly-ny');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('tc-page tc-air tc-page--fullbleed', $body);
        $this->assertStringContainsString('/flow/air/ongoing?tc6=1', $body);
        $this->assertStringContainsString('/flow/air/completed?tc6=1', $body);
        $this->assertStringContainsString('/flow/air/before_start?tc6=1', $body);
        $this->assertStringNotContainsString('Preview flows', $body);
    }

    public function testLegacyFlyRedirectsToFlyNy(): void
    {
        $this->get('/fly');

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/fly-ny', $this->_response->getHeaderLine('Location'));
    }

    public function testLegacyAirclaimRedirectsToFlyNy(): void
    {
        $this->get('/airclaim');

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/fly-ny', $this->_response->getHeaderLine('Location'));
    }

    public function testFlyNyCompletedEntryPersistsAirEvidenceIntoSharedAdmin(): void
    {
        $this->get('/fly-ny');
        $this->assertResponseOk();
        $this->get('/flow/air/completed?tc6=1');
        $this->assertSession('air', 'flow.form.transport_mode');
        $this->assertSession('completed', 'flow.flags.travel_state');

        $ref = 'fly-ny-launch-' . bin2hex(random_bytes(6));
        $cases = FactoryLocator::get('Table')->get('Cases');
        $this->session([
            'passenger.authenticated' => true,
            'passenger.auth_user' => 'passenger',
            'passenger.auth_label' => 'Fly ny launch test',
            'flow.tc6_mode' => '1',
            'flow.flags' => [
                'travel_state' => 'completed',
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
            ],
            'flow.form' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'incident_main' => 'delay',
                'dep_station' => 'CPH',
                'arr_station' => 'LHR',
                'dep_date' => '2026-08-12',
                'firstName' => 'Fly ny',
                'lastName' => 'Launch Test',
                'operator' => 'British Airways',
                'flight_number' => 'BA823',
                'arrival_delay_minutes' => -8,
            ],
            'flow.meta' => [
                'air_case_ref' => $ref,
                'air_case_created_at' => '2026-08-14T12:00:00Z',
                'air_selected_flight' => [
                    'flight_number' => 'BA823',
                    'marketing_flight_number' => 'BA823',
                    'operating_flight_number' => 'BA823',
                    'departure_airport_iata' => 'CPH',
                    'arrival_airport_iata' => 'LHR',
                    'operating_carrier_name' => 'British Airways',
                    'provider' => 'aerodatabox',
                    'operational_data_verified' => true,
                ],
                'air_operational_evidence' => [
                    'source' => 'aerodatabox',
                    'status' => 'arrived',
                    'scheduled_arrival_utc' => '2026-08-12T07:30:00Z',
                    'actual_arrival_utc' => '2026-08-12T07:22:00Z',
                    'arrival_delay_minutes' => -8,
                    'arrival_delay_basis' => 'actual',
                    'operational_data_verified' => true,
                    'retrieved_at' => '2026-08-14T12:00:00Z',
                ],
            ],
            'flow.compute' => ['delayMinEU' => -8, 'euOnly' => true],
            'flow.incident' => ['main' => 'delay'],
        ]);

        try {
            $this->get('/passenger/case?ref=' . rawurlencode($ref));
            $this->assertResponseOk();
            $case = $cases->find()->where(['ref' => $ref])->firstOrFail();
            $snapshot = json_decode((string)$case->get('flow_snapshot'), true);
            $this->assertSame('air', $snapshot['form']['transport_mode']);
            $this->assertSame('aerodatabox', $snapshot['meta']['air_operational_evidence']['source']);
            $this->assertSame('actual', $snapshot['meta']['air_operational_evidence']['arrival_delay_basis']);
            $this->assertTrue($snapshot['meta']['air_operational_evidence']['operational_data_verified']);

            $deskItem = (new AdminDeskService())->loadDeskItem(new Session(), 'case', (string)$case->get('id'));
            $this->assertNotNull($deskItem);
            $this->assertSame('air', $deskItem['item']['meta']['transport_mode']);
            $this->assertTrue($deskItem['ops_review']['available']);
            $this->assertSame('AeroDataBox', $deskItem['ops_review']['source_label']);
        } finally {
            $cases->deleteAll(['ref' => $ref]);
        }
    }
}
