<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\MultimodalFlowResolver;
use PHPUnit\Framework\TestCase;

final class MultimodalFlowResolverTest extends TestCase
{
    public function testDefaultsToRailWhenTransportModeMissing(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => ['operator' => 'DSB', 'dep_station' => 'Odense', 'arr_station' => 'Kobenhavn'],
            'meta' => [],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('rail', $result['transport_mode']);
        $this->assertSame('rail', $result['contract_meta']['rights_module']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
    }

    public function testInfersFerryTransportModeFromOperatorWhenUploadModeHasNoManualChoice(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
                'operator' => 'Scandlines',
                'dep_station' => 'Helsingor',
                'arr_station' => 'Helsingborg',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('ferry', $result['transport_mode']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
    }

    public function testClassifiesFerryUploadFromOcrSignalsBeforeRailFallback(): void
    {
        $ocr = <<<TEXT
Udrejse
Fredag, den 3. december 2021, kl. 14:30
Ronne-Ystad (1 time 20 min)
1 Lavpris Bil < 1,95 m 349,00
3 Person (er)
Check-in skal vaere foretaget senest 10 min. inden planmaessig afgang.
TEXT;

        $result = (new MultimodalFlowResolver())->evaluate([
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
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('ferry', $result['transport_mode']);
        $this->assertSame('ferry', $result['contract_meta']['rights_module']);
        $this->assertSame('ferry', $result['mode_classification']['primary_mode']);
        $this->assertSame('high', $result['mode_classification']['confidence']);
        $this->assertContains('ocr:check-in', $result['mode_classification']['reasons']);
    }

    public function testClassifiesAirUploadFromOcrSignalsBeforeRailFallback(): void
    {
        $ocr = <<<TEXT
BOARDING PASS
Passenger Name: Jane Doe
Flight SK1423
Gate A12
Boarding 08:30
PNR ABC123
CPH ARN
TEXT;

        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
            ],
            'meta' => [
                '_ocr_text' => $ocr,
                '_identifiers' => ['pnr' => 'ABC123'],
                '_auto' => [
                    'dep_station' => ['value' => 'CPH'],
                    'arr_station' => ['value' => 'ARN'],
                ],
            ],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('air', $result['transport_mode']);
        $this->assertSame('air', $result['mode_classification']['primary_mode']);
    }

    public function testClassifiesBusUploadFromOcrSignalsBeforeRailFallback(): void
    {
        $ocr = <<<TEXT
Coach ticket
Long distance bus
Departure stop: Odense Banegard Center
Arrival stop: Aarhus Bus Station
Bus no 601
Seat 12
TEXT;

        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
            ],
            'meta' => [
                '_ocr_text' => $ocr,
            ],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('bus', $result['transport_mode']);
        $this->assertSame('bus', $result['mode_classification']['primary_mode']);
    }

    public function testTicketlessManualJourneyStructureCanResolveStopDecision(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'ticket_upload_mode' => 'ticketless',
                'seller_channel' => 'operator',
                'shared_pnr_scope' => 'yes',
                'same_transaction' => 'yes',
                'original_contract_mode' => 'rail',
                'journey_structure' => 'single_segment',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertSame('rail', $result['contract_meta']['original_contract_mode']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('single', $result['contract_decision']['ticket_scope']);
    }

    public function testThroughMultimodalContractKeepsOriginalModeSeparateFromIncidentRightsModule(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'ticket_upload_mode' => 'ticketless',
                'seller_channel' => 'operator',
                'shared_pnr_scope' => 'yes',
                'same_transaction' => 'yes',
                'original_contract_mode' => 'rail',
                'journey_structure' => 'multimodal_connections',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
                'incident_segment_mode' => 'ferry',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('unknown_manual_review', $result['contract_meta']['contract_topology']);
        $this->assertSame('likely_single_contract', $result['contract_meta']['contract_topology_hint']);
        $this->assertSame('low', $result['contract_meta']['contract_topology_confidence']);
        $this->assertContains('ticketless_estimate_only', $result['contract_meta']['manual_review_reasons']);
        $this->assertSame('rail', $result['contract_meta']['original_contract_mode']);
        $this->assertSame('rail', $result['contract_meta']['claim_transport_mode']);
        $this->assertSame('ferry', $result['contract_meta']['rights_module']);
        $this->assertSame('rail', $result['claim_direction']['claim_transport_mode']);
        $this->assertSame('ferry', $result['claim_direction']['rights_module']);
    }

    public function testUploadMultipleTicketsBuildsGroupedContractEvidence(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
                'transport_mode' => 'ferry',
                'journey_structure' => 'multimodal_connections',
                'same_transaction' => 'yes',
                'separate_contract_notice' => 'no',
                'incident_segment_mode' => 'ferry',
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
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertCount(2, $result['ticket_extracts']);
        $this->assertCount(1, $result['grouped_contracts']);
        $this->assertSame('strong', $result['contract_meta']['booking_cohesion']);
        $this->assertSame(2, $result['contract_meta']['ticket_extract_count']);
        $this->assertSame(1, $result['contract_meta']['grouped_contract_count']);
        $this->assertSame('single_multimodal_contract', $result['contract_meta']['contract_topology']);
        $this->assertSame('high', $result['contract_meta']['contract_topology_confidence']);
    }

    public function testFerryRoundTripUploadUsesSingleModeConnectionsInsteadOfMultimodal(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
            ],
            'meta' => [
                '_ocr_text' => <<<TEXT
Udrejse
Ronne-Ystad
Check-in skal vaere foretaget senest 10 min. inden planmaessig afgang.
Hjemrejse
Ystad-Ronne
Check-in skal vaere foretaget senest 10 min. inden planmaessig afgang.
TEXT,
                '_segments_auto' => [
                    ['from' => 'Ronne', 'to' => 'Ystad', 'mode' => 'ferry', 'depDate' => '2021-12-03'],
                    ['from' => 'Ystad', 'to' => 'Ronne', 'mode' => 'ferry', 'depDate' => '2021-12-05'],
                ],
                '_auto' => [
                    'dep_station' => ['value' => 'Ronne'],
                    'arr_station' => ['value' => 'Ystad'],
                    'dep_date' => ['value' => '2021-12-03'],
                    'price' => ['value' => '798.00 DKK'],
                ],
                '_identifiers' => ['pnr' => 'BORNH123'],
            ],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertSame('ferry', $result['transport_mode']);
        $this->assertSame('single_mode_connections', $result['contract_meta']['journey_structure']);
        $this->assertSame('protected_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertSame('medium', $result['contract_meta']['contract_topology_confidence']);
        $this->assertNotContains('multiple_ticket_groups', $result['contract_meta']['manual_review_reasons']);
        $this->assertNotContains('contract_structure_disclosure_unknown', $result['contract_meta']['manual_review_reasons']);
    }

    public function testSameBookingReferenceAcrossOutboundAndReturnDatesStaysInOneGroup(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'ticket_upload_mode' => 'ticket',
                'transport_mode' => 'ferry',
                'same_transaction' => 'yes',
                'separate_contract_notice' => 'no',
            ],
            'meta' => [
                '_multi_tickets' => [
                    [
                        'file' => 'outbound.pdf',
                        'pnr' => 'BORNH123',
                        'dep_date' => '2021-12-03',
                        'segments' => [
                            ['from' => 'Ronne', 'to' => 'Ystad', 'mode' => 'ferry'],
                        ],
                        'auto' => [
                            'operator' => ['value' => 'Bornholmslinjen'],
                            'price' => ['value' => '349.00 DKK'],
                        ],
                    ],
                    [
                        'file' => 'return.pdf',
                        'pnr' => 'BORNH123',
                        'dep_date' => '2021-12-05',
                        'segments' => [
                            ['from' => 'Ystad', 'to' => 'Ronne', 'mode' => 'ferry'],
                        ],
                        'auto' => [
                            'operator' => ['value' => 'Bornholmslinjen'],
                            'price' => ['value' => '449.00 DKK'],
                        ],
                    ],
                ],
            ],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertCount(1, $result['grouped_contracts']);
        $this->assertSame(1, $result['contract_meta']['grouped_contract_count']);
        $this->assertSame('single_mode_connections', $result['contract_meta']['journey_structure']);
        $this->assertNotContains('multiple_ticket_groups', $result['contract_meta']['manual_review_reasons']);
    }

    public function testEvaluatesFerryScopeContractAndRights(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Example Ferry',
                'ticket_no' => 'FERRY-1',
                'dep_station' => 'Helsingor',
                'arr_station' => 'Helsingborg',
                'service_type' => 'passenger_service',
                'departure_from_terminal' => 'yes',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
                'vessel_passenger_capacity' => '200',
                'vessel_operational_crew' => '12',
                'route_distance_meters' => '10000',
                'incident_main' => 'delay',
                'actual_departure_delay_90' => 'yes',
                'arrival_delay_minutes' => '130',
                'scheduled_journey_duration_minutes' => '300',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'delay'],
        ]);

        $this->assertSame('ferry', $result['transport_mode']);
        $this->assertTrue($result['ferry_scope']['regulation_applies']);
        $this->assertSame('carrier', $result['ferry_contract']['primary_claim_party']);
        $this->assertTrue($result['ferry_rights']['gate_art18']);
        $this->assertTrue($result['ferry_rights']['gate_art19']);
        $this->assertSame('25', $result['ferry_rights']['art19_comp_band']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('single', $result['contract_decision']['ticket_scope']);
        $this->assertSame('ferry', $result['claim_direction']['rights_module']);
    }

    public function testFerryIncidentMetaUsesAssistanceOvernightAlias(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Example Ferry',
                'service_type' => 'passenger_service',
                'departure_from_terminal' => 'yes',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
                'vessel_passenger_capacity' => '200',
                'vessel_operational_crew' => '12',
                'route_distance_meters' => '10000',
                'incident_main' => 'cancellation',
                'expected_departure_delay_90' => 'yes',
                'scheduled_journey_duration_minutes' => '300',
                'ferry_overnight_required' => 'yes',
                'weather_safety' => 'yes',
                'extraordinary_circumstances' => 'no',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'cancellation'],
        ]);

        $this->assertTrue($result['incident_meta']['overnight_required'] ?? false);
        $this->assertTrue($result['incident_meta']['weather_safety'] ?? false);
        $this->assertFalse($result['incident_meta']['extraordinary_circumstances'] ?? true);
        $this->assertFalse($result['ferry_rights']['gate_art17_hotel']);
    }

    public function testFerryAssistanceOvernightOverridesLegacyIncidentField(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Example Ferry',
                'service_type' => 'passenger_service',
                'departure_from_terminal' => 'yes',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
                'incident_main' => 'cancellation',
                'expected_departure_delay_90' => 'yes',
                'arrival_delay_minutes' => '130',
                'scheduled_journey_duration_minutes' => '300',
                'overnight_required' => 'no',
                'ferry_overnight_required' => 'yes',
                'overnight_needed' => 'yes',
                'passenger_fault' => 'yes',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'cancellation'],
        ]);

        $this->assertTrue($result['incident_meta']['overnight_required'] ?? false);
        $this->assertNull($result['incident_meta']['passenger_fault'] ?? null);
        $this->assertTrue($result['ferry_rights']['gate_art17_hotel']);
        $this->assertTrue($result['ferry_rights']['gate_art19']);
    }

    public function testEvaluatesBusContractDirection(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'bus',
                'operator' => 'Ticket Seller',
                'ticket_no' => 'BUS-1',
                'dep_station' => 'Odense',
                'arr_station' => 'Aarhus',
                'incident_segment_mode' => 'bus',
                'incident_segment_operator' => 'FlixBus',
                'single_txn_retailer' => 'yes',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
                'bus_regular_service' => 'yes',
                'boarding_in_eu' => 'yes',
                'alighting_in_eu' => 'yes',
                'departure_from_terminal' => 'yes',
                'scheduled_distance_km' => '320',
                'incident_main' => 'delay',
                'delay_minutes_departure' => '130',
                'scheduled_journey_duration_minutes' => '240',
                'carrier_offered_choice' => 'no',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'delay'],
        ]);

        $this->assertSame('bus', $result['transport_mode']);
        $this->assertTrue($result['bus_scope']['regulation_applies']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertSame('bus', $result['bus_contract']['rights_module']);
        $this->assertTrue($result['bus_rights']['gate_bus_reroute_refund']);
        $this->assertTrue($result['bus_rights']['gate_bus_compensation_50']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('single', $result['contract_decision']['ticket_scope']);
        $this->assertSame('bus', $result['claim_direction']['rights_module']);
        $this->assertContains('reroute_or_refund_evidence', $result['claim_direction']['recommended_documents']);
        $this->assertContains('operator_connection_or_terminal_evidence', $result['claim_direction']['recommended_documents']);
    }

    public function testEvaluatesAirContractDirection(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'air',
                'operator' => 'SAS',
                'ticket_no' => 'PNR-1',
                'dep_station' => 'CPH',
                'arr_station' => 'ARN',
                'departure_airport_in_eu' => 'yes',
                'arrival_airport_in_eu' => 'yes',
                'operating_carrier_is_eu' => 'yes',
                'incident_segment_mode' => 'air',
                'incident_segment_operator' => 'SAS',
                'single_txn_operator' => 'yes',
                'through_ticket_disclosure' => 'bundled',
                'separate_contract_notice' => 'no',
                'same_pnr' => 'yes',
                'incident_main' => 'cancellation',
                'arrival_delay_minutes' => '240',
                'reroute_arrival_delay_minutes' => '240',
            ],
            'meta' => [],
            'journey' => [],
            'incident' => ['main' => 'cancellation'],
        ]);

        $this->assertSame('air', $result['transport_mode']);
        $this->assertSame('single_mode_single_contract', $result['contract_meta']['contract_topology']);
        $this->assertTrue($result['air_scope']['regulation_applies']);
        $this->assertSame('single_flight', $result['air_contract']['air_connection_type']);
        $this->assertSame('air', $result['air_contract']['rights_module']);
        $this->assertTrue($result['air_rights']['gate_air_reroute_refund']);
        $this->assertTrue($result['air_rights']['gate_air_compensation']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('single', $result['contract_decision']['ticket_scope']);
        $this->assertSame('air', $result['claim_direction']['rights_module']);
        $this->assertContains('boarding_pass_or_pnr', $result['claim_direction']['recommended_documents']);
        $this->assertContains('arrival_delay_or_cancellation_evidence', $result['claim_direction']['recommended_documents']);
    }

    public function testMarksSeparateContractsAsStopDecision(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Scandlines',
                'dep_station' => 'Helsingor',
                'arr_station' => 'Helsingborg',
                'shared_pnr_scope' => 'no',
                'same_transaction' => 'no',
                'separate_contract_notice' => 'yes',
                'incident_segment_mode' => 'ferry',
                'incident_segment_operator' => 'Scandlines',
                'service_type' => 'passenger_service',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
            ],
            'meta' => [],
            'journey' => [
                'segments' => [
                    ['from' => 'Odense', 'to' => 'Helsingor', 'mode' => 'rail'],
                    ['from' => 'Helsingor', 'to' => 'Helsingborg', 'mode' => 'ferry'],
                ],
            ],
            'incident' => [],
        ], false);

        $this->assertSame('separate_contracts', $result['contract_meta']['contract_topology']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('separate', $result['contract_decision']['ticket_scope']);
        $this->assertTrue($result['claim_direction']['contract_stop']);
        $this->assertSame('separate', $result['claim_direction']['ticket_scope']);
    }

    public function testSeparateDisclosureCanDriveSeparateContractsDecision(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Scandlines',
                'shared_pnr_scope' => 'yes',
                'same_transaction' => 'yes',
                'through_ticket_disclosure' => 'separate',
                'separate_contract_notice' => 'yes',
            ],
            'meta' => [],
            'journey' => [
                'segments' => [
                    ['from' => 'Kobenhavn', 'to' => 'Helsingor', 'mode' => 'rail'],
                    ['from' => 'Helsingor', 'to' => 'Helsingborg', 'mode' => 'ferry'],
                ],
            ],
            'incident' => [],
        ], false);

        $this->assertSame('separate_contracts', $result['contract_meta']['contract_topology']);
        $this->assertSame('STOP', $result['contract_decision']['stage']);
        $this->assertSame('separate', $result['contract_decision']['ticket_scope']);
    }

    public function testPnrAloneDoesNotImplySameTransaction(): void
    {
        $result = (new MultimodalFlowResolver())->evaluate([
            'form' => [
                'transport_mode' => 'ferry',
                'operator' => 'Scandlines',
                'ticket_no' => 'PNR-ONLY',
                'dep_station' => 'Ronne',
                'arr_station' => 'Ystad',
                'service_type' => 'passenger_service',
                'departure_port_in_eu' => 'yes',
                'arrival_port_in_eu' => 'yes',
                'carrier_is_eu' => 'yes',
            ],
            'meta' => [
                'shared_pnr_scope' => 'yes',
            ],
            'journey' => [],
            'incident' => [],
        ], false);

        $this->assertTrue($result['contract_meta']['shared_booking_reference']);
        $this->assertNull($result['contract_meta']['single_transaction']);
    }
}
