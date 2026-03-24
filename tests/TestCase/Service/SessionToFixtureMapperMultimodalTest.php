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
                'original_contract_mode' => 'rail',
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
        $this->assertSame('rail', $fixture['contract_meta']['original_contract_mode'] ?? null);
        $this->assertSame('seller', $fixture['contract_meta']['primary_claim_party'] ?? null);
        $this->assertSame('rail', $fixture['contract_meta']['claim_transport_mode'] ?? null);
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

    public function testMapSessionToFixtureUsesFerryOvernightAliasForIncidentMeta(): void
    {
        $flow = [
            'form' => [
                'transport_mode' => 'ferry',
                'incident_main' => 'delay',
                'ferry_overnight_required' => 'yes',
                'overnight_needed' => 'yes',
                'weather_safety' => 'yes',
                'extraordinary_circumstances' => 'no',
            ],
            'incident' => ['main' => 'delay'],
            'flags' => [],
            'compute' => [],
        ];

        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture($flow);

        $this->assertTrue($fixture['incident_meta']['overnight_required'] ?? false);
        $this->assertTrue($fixture['incident_meta']['weather_safety'] ?? false);
        $this->assertFalse($fixture['incident_meta']['extraordinary_circumstances'] ?? true);
    }

    public function testMapSessionToFixtureUsesModeClassificationForFerryUpload(): void
    {
        $ocr = <<<TEXT
Udrejse
Ronne-Ystad (1 time 20 min)
1 Lavpris Bil < 1,95 m 349,00
3 Person (er)
Check-in skal vaere foretaget senest 10 min. inden planmaessig afgang.
TEXT;

        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture([
            'form' => [
                'ticket_upload_mode' => 'ticket',
            ],
            'meta' => [
                '_ocr_text' => $ocr,
                '_auto' => [
                    'dep_station' => ['value' => 'Ronne'],
                    'arr_station' => ['value' => 'Ystad'],
                ],
            ],
            'flags' => [],
            'compute' => [],
        ]);

        $this->assertSame('ferry', $fixture['transport_mode']);
        $this->assertSame('ferry', $fixture['contract_meta']['rights_module'] ?? null);
    }

    public function testMapSessionToFixtureCarriesGroupedTicketEvidence(): void
    {
        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture([
            'form' => [
                'ticket_upload_mode' => 'ticket',
                'transport_mode' => 'ferry',
                'journey_structure' => 'multimodal_connections',
                'same_transaction' => 'yes',
                'separate_contract_notice' => 'no',
            ],
            'meta' => [
                '_multi_tickets' => [
                    [
                        'file' => 'ticket_a.pdf',
                        'pnr' => 'ABC123',
                        'dep_date' => '2026-03-17',
                        'segments' => [
                            ['from' => 'Odense', 'to' => 'Helsingor', 'mode' => 'rail'],
                        ],
                        'auto' => [
                            'operator' => ['value' => 'DSB'],
                            'price' => ['value' => '199.00 DKK'],
                        ],
                    ],
                    [
                        'file' => 'ticket_b.pdf',
                        'pnr' => 'ABC123',
                        'dep_date' => '2026-03-17',
                        'segments' => [
                            ['from' => 'Helsingor', 'to' => 'Helsingborg', 'mode' => 'ferry'],
                        ],
                        'auto' => [
                            'operator' => ['value' => 'DSB'],
                            'price' => ['value' => '199.00 DKK'],
                        ],
                    ],
                ],
            ],
            'flags' => [],
            'compute' => [],
        ]);

        $this->assertCount(2, $fixture['ticket_extracts'] ?? []);
        $this->assertCount(1, $fixture['grouped_contracts'] ?? []);
        $this->assertSame('strong', $fixture['contract_meta']['booking_cohesion'] ?? null);
        $this->assertSame('single_multimodal_contract', $fixture['contract_meta']['contract_topology'] ?? null);
    }

    public function testMapSessionToFixturePrefersFerryAssistanceOvernightAndDefersPassengerFault(): void
    {
        $flow = [
            'form' => [
                'transport_mode' => 'ferry',
                'incident_main' => 'cancellation',
                'overnight_required' => 'no',
                'ferry_overnight_required' => 'yes',
                'overnight_needed' => 'yes',
                'passenger_fault' => 'yes',
            ],
            'incident' => ['main' => 'cancellation'],
            'flags' => [],
            'compute' => [],
        ];

        $fixture = (new SessionToFixtureMapper())->mapSessionToFixture($flow);

        $this->assertTrue($fixture['incident_meta']['overnight_required'] ?? false);
        $this->assertNull($fixture['incident_meta']['passenger_fault'] ?? null);
    }
}
