<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\SessionToFixtureMapper;
use Cake\TestSuite\TestCase;

final class SessionToFixtureMapperMultimodalTest extends TestCase
{
    public function testMapSessionToFixtureAddsSharedMultimodalBlocks(): void
    {
        $flow = [
            'form' => [
                'seller_channel' => 'operator',
                'single_txn_operator' => 'yes',
                'through_ticket_disclosure' => 'yes',
                'separate_contract_notice' => 'no',
                'ticket_no' => 'ABC123',
                'operator' => 'DSB',
                'dep_station' => 'Odense',
                'arr_station' => 'Helsingør',
                'incident_main' => 'delay',
                'expected_delay_60' => 'yes',
                'national_delay_minutes' => '75',
            ],
            'incident' => [
                'main' => 'delay',
                'missed' => 'no',
            ],
            'flags' => [
                'travel_state' => 'completed',
            ],
            'compute' => [],
        ];

        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture($flow);

        $this->assertSame('rail', $fixture['transport_mode']);
        $this->assertSame('single_mode_single_contract', $fixture['contract_meta']['contract_topology'] ?? null);
        $this->assertSame('seller', $fixture['contract_meta']['primary_claim_party'] ?? null);
        $this->assertSame('rail', $fixture['contract_meta']['rights_module'] ?? null);
        $this->assertSame('delay', $fixture['incident_meta']['incident_type'] ?? null);
        $this->assertTrue($fixture['incident_meta']['expected_delay_60'] ?? false);
        $this->assertSame(75, $fixture['incident_meta']['national_delay_minutes'] ?? null);
    }
}
