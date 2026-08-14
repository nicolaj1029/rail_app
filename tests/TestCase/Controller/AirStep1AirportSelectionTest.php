<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

final class AirStep1AirportSelectionTest extends TestCase
{
    use IntegrationTestTrait;

    public function testAirStep1RendersBothAirportPreselectorsAndSharedFlowDoesNotLeakThem(): void
    {
        $this->seedModeEntry('air');
        $this->get('/flow/entitlements?tc6=1');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-airport-preselector="departure"', $body);
        $this->assertStringContainsString('data-airport-preselector="arrival"', $body);
        $this->assertStringContainsString('/api/transport-nodes/search', $body);
        $this->assertStringContainsString('name="dep_station_lookup_id"', $body);
        $this->assertStringContainsString('name="arr_station_lookup_id"', $body);

        foreach (['rail', 'ferry'] as $mode) {
            $this->seedModeEntry($mode);
            $this->get('/flow/entitlements?tc6=1');
            $this->assertResponseOk();
            $this->assertStringNotContainsString(
                'data-airport-preselector=',
                (string)$this->_response->getBody(),
                sprintf('%s Step 1 must not render AIR airport bindings.', $mode),
            );
        }
    }

    public function testCanonicalAirportsSurviveSubmissionIntoFlightFlow(): void
    {
        $this->seedModeEntry('air');
        $this->enableCsrfToken();
        $this->post('/flow/entitlements?tc6=1', array_merge(
            $this->baseAirPost(),
            $this->airportFields('dep_station', 'air-bru', 'BRU', 'Brussels Airport', 'BE', true),
            $this->airportFields('arr_station', 'air-lhr', 'LHR', 'London Heathrow Airport', 'GB', false),
        ));

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/air-reservation-contract', $this->_response->getHeaderLine('Location'));
        $this->assertSession('air', 'flow.form.transport_mode');
        $this->assertSession('Brussels Airport', 'flow.form.dep_station');
        $this->assertSession('air-bru', 'flow.form.dep_station_lookup_id');
        $this->assertSession('BRU', 'flow.form.dep_station_lookup_code');
        $this->assertSession('air', 'flow.form.dep_station_lookup_mode');
        $this->assertSession('airport', 'flow.form.dep_station_lookup_node_type');
        $this->assertSession('London Heathrow Airport', 'flow.form.arr_station');
        $this->assertSession('air-lhr', 'flow.form.arr_station_lookup_id');
        $this->assertSession('LHR', 'flow.form.arr_station_lookup_code');
        $this->assertSession('air', 'flow.form.arr_station_lookup_mode');
        $this->assertSession('airport', 'flow.form.arr_station_lookup_node_type');
    }

    private function seedModeEntry(string $mode): void
    {
        $entryVariant = match ($mode) {
            'air' => 'air_short',
            'ferry' => 'ferry_split',
            default => 'rail_split',
        };
        $this->session([
            'flow.tc6_mode' => '1',
            'flow.flags' => [
                'step1_done' => '1',
                'travel_state' => 'completed',
                'transport_mode' => $mode,
                'gating_mode' => $mode,
                'entry_mode' => $mode,
                'entry_variant' => $entryVariant,
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
                'entry_mode' => $mode,
                'entry_variant' => $entryVariant,
                'entry_travel_state' => 'completed',
            ],
        ]);
    }

    /** @return array<string,string> */
    private function baseAirPost(): array
    {
        return [
            'ticket_upload_mode' => 'ticketless',
            'transport_mode' => 'air',
            'air_route_type' => 'direct',
            'dep_date' => '2026-08-12',
            'passenger_count' => '1',
            'continue' => '1',
        ];
    }

    /** @return array<string,string> */
    private function airportFields(
        string $field,
        string $id,
        string $code,
        string $name,
        string $country,
        bool $inEu,
    ): array {
        return [
            $field => $name,
            $field . '_lookup_id' => $id,
            $field . '_lookup_code' => $code,
            $field . '_lookup_mode' => 'air',
            $field . '_lookup_country' => $country,
            $field . '_lookup_in_eu' => $inEu ? 'yes' : 'no',
            $field . '_lookup_node_type' => 'airport',
            $field . '_lookup_source' => 'local',
            $field . '_lookup_canonical_name' => $name,
            $field . '_lookup_display_name' => $name,
        ];
    }
}
