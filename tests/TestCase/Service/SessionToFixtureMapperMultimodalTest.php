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

    public function testMapSessionToFixtureCarriesFerryAliasFields(): void
    {
        $flow = [
            'form' => [
                'transport_mode' => 'ferry',
                'remedyChoice' => 'refund_return',
                'ferry_remedy_choice' => 'refund_return',
                'ferry_refund_requested' => 'yes',
                'ferry_reroute_choice' => '',
                'return_to_origin_expense' => 'yes',
                'return_to_origin_amount' => '28.50',
                'return_to_origin_currency' => 'EUR',
                'ferry_return_to_departure_port_expense' => 'yes',
                'ferry_return_to_departure_port_amount' => '28.50',
                'ferry_return_to_departure_port_currency' => 'EUR',
                'meal_offered' => 'no',
                'ferry_refreshments_offered' => 'no',
                'meal_self_paid_amount' => '12.00',
                'meal_self_paid_currency' => 'EUR',
                'ferry_refreshments_self_paid_amount' => '12.00',
                'ferry_refreshments_self_paid_currency' => 'EUR',
                'hotel_offered' => 'no',
                'ferry_hotel_offered' => 'no',
                'overnight_needed' => 'yes',
                'ferry_overnight_required' => 'yes',
                'assistance_hotel_transport_included' => 'no',
                'ferry_hotel_transport_included' => 'no',
                'hotel_self_paid_amount' => '95.00',
                'hotel_self_paid_currency' => 'EUR',
                'hotel_self_paid_nights' => '1',
                'ferry_hotel_self_paid_amount' => '95.00',
                'ferry_hotel_self_paid_currency' => 'EUR',
                'ferry_hotel_self_paid_nights' => '1',
            ],
            'incident' => ['main' => 'delay'],
            'flags' => [],
            'compute' => [],
        ];

        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture($flow);

        $this->assertSame('ferry', $fixture['transport_mode']);
        $this->assertSame('refund_return', $fixture['wizard']['step7_remedies']['ferry_remedy_choice'] ?? null);
        $this->assertSame('yes', $fixture['wizard']['step7_remedies']['ferry_return_to_departure_port_expense'] ?? null);
        $this->assertSame('28.50', $fixture['wizard']['step7_remedies']['ferry_return_to_departure_port_amount'] ?? null);
        $this->assertSame('no', $fixture['wizard']['step8_assistance']['ferry_refreshments_offered'] ?? null);
        $this->assertSame('12.00', $fixture['wizard']['step8_assistance']['ferry_refreshments_self_paid_amount'] ?? null);
        $this->assertSame('no', $fixture['wizard']['step8_assistance']['ferry_hotel_offered'] ?? null);
        $this->assertSame('yes', $fixture['wizard']['step8_assistance']['ferry_overnight_required'] ?? null);
        $this->assertSame('95.00', $fixture['wizard']['step8_assistance']['ferry_hotel_self_paid_amount'] ?? null);
        $this->assertSame('1', $fixture['wizard']['step8_assistance']['ferry_hotel_self_paid_nights'] ?? null);
    }
}
