<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class PassengerControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function tearDown(): void
    {
        parent::tearDown();
        Configure::delete('PublicSite');
        Configure::delete('HostRouting');
    }

    public function testRailCaseShowsCompensationOverviewAndSeededRefundExpenseUpload(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art18' => '1',
                'gate_art19' => '1',
                'gate_art20' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'dep_station' => 'Koebenhavn H',
                'arr_station' => 'Hamburg Hbf',
                'dep_date' => '2026-05-01',
                'price' => '1200',
                'price_currency' => 'DKK',
                'incident_main' => 'cancellation',
                'remedyChoice' => 'reroute_soonest',
                'offer_provided' => 'no',
                'self_purchased_new_ticket' => 'yes',
                'reroute_extra_costs' => 'yes',
                'reroute_extra_costs_type' => 'new_ticket',
                'reroute_extra_costs_amount' => '350.00',
                'reroute_extra_costs_currency' => 'DKK',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_selected_departure' => [
                    'origin_station_name' => 'Koebenhavn H',
                    'destination_station_name' => 'Hamburg Hbf',
                    'train_number' => 'EC 399',
                    'operator_name' => 'DB',
                    'planned_departure_at' => '2026-05-01T08:30:00+02:00',
                ],
                'rail_incident_seed' => [
                    'gate_art18' => true,
                    'gate_art19' => true,
                    'gate_art20' => true,
                    'arrival_delay_minutes' => 90,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=refund');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Kendt rail-kravsbillede', $body);
        $this->assertStringContainsString('Billetpris fra trin 2', $body);
        $this->assertStringContainsString('1,200.00 DKK', $body);
        $this->assertStringContainsString('Operatoerens omlaegning og Art. 19-kompensation holdes adskilt', $body);
        $this->assertStringContainsString('name="air_reroute_expenses_incurred" value="yes"', $body);
        $this->assertStringContainsString('name="air_case_refund_expense_items[0][amount]" value="350.00"', $body);
        $this->assertStringContainsString('name="air_case_refund_receipts[0]"', $body);
        $this->assertStringNotContainsString('Carrierens tilbud fra incident (Article 5 / 7(2))', $body);
        $this->assertStringNotContainsString('Tilboed flyselskabet en alternativ flyvning?', $body);
        $this->assertStringNotContainsString('Carrierens alternative flyvning bruges kun som kompensationsfakta laengere nede.', $body);
    }

    public function testRailCaseShowsSeededContextExpenseUploadsFromStationContext(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art20' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'dep_station' => 'Odense',
                'arr_station' => 'Aarhus H',
                'dep_date' => '2026-05-02',
                'incident_main' => 'delay',
                'a20_station_stranded' => 'yes',
                'rail_stranding_context' => 'station',
                'stranded_current_station' => 'Odense',
                'rail_station_expenses_signal' => 'yes',
                'rail_station_expense_types' => ['meals', 'local_transport'],
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art20' => true,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=rail_context');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Frontend pegede paa maaltider / forfriskninger ved stationen.', $body);
        $this->assertStringContainsString('Stationsudgifter og kvitteringer', $body);
        $this->assertStringContainsString('name="rail_case_context_station_expense_items[0][type]"', $body);
        $this->assertStringContainsString('name="rail_case_context_station_receipts[0]"', $body);
    }

    public function testRailCaseShowsTrackExpenseUploadsInRailContext(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art20' => '1',
                'gate_art20_2c' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'cancellation',
                'is_stranded_trin5' => 'yes',
                'blocked_train_alt_transport' => 'no',
                'blocked_no_transport_action' => 'self_arranged',
                'blocked_self_paid_transport_type' => 'taxi',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art20' => true,
                    'gate_art20_2c' => true,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=rail_context');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Udgifter fra strandet paa sporet', $body);
        $this->assertStringContainsString('Frontend registrerede, at passageren fandt egen transport som taxi / minibus fra sporet.', $body);
        $this->assertStringContainsString('name="rail_case_context_track_expense_items[0][type]"', $body);
        $this->assertStringContainsString('name="rail_case_context_track_receipts[0]"', $body);
    }

    public function testRailCaseDoesNotDuplicateSeededMealExpenseInSupport(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art20' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'meal_self_paid_amount' => '75.00',
                'meal_self_paid_currency' => 'DKK',
                'rail_station_expense_types' => ['meals'],
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art20' => true,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=support');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertSame(1, substr_count($body, 'name="air_case_care_expense_items[0][amount]" value="75.00"'));
        $this->assertSame(1, substr_count($body, 'Selvbetalte maaltider fra frontend.'));
        $this->assertStringNotContainsString('name="air_case_care_expense_items[1][type]"', $body);
    }

    public function testRailContextPostStoresStationAndTrackExpenseItems(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art18' => '1',
                'gate_art20' => '1',
                'gate_art20_2c' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'a20_station_stranded' => 'yes',
                'rail_station_expenses_signal' => 'yes',
                'is_stranded_trin5' => 'yes',
                'blocked_train_alt_transport' => 'no',
                'blocked_no_transport_action' => 'self_arranged',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art18' => true,
                    'gate_art20' => true,
                    'gate_art20_2c' => true,
                ],
            ],
        ]);

        $this->post('/passenger/case?step=rail_context', [
            'active_case_step' => 'rail_context',
            'goto_step' => 'rail_context',
            'rail_case_context_station_expense_items' => [
                ['type' => 'meal', 'amount' => '45.00', 'currency' => 'EUR', 'description' => 'Sandwich og drikkevarer'],
            ],
            'rail_case_context_track_expense_items' => [
                ['type' => 'other_transport', 'amount' => '120.00', 'currency' => 'CHF', 'description' => 'Taxi fra sporet'],
            ],
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('45.00', 'flow.form.rail_case_context_station_expense_items.0.amount');
        $this->assertSession('120.00', 'flow.form.rail_case_context_track_expense_items.0.amount');
    }

    public function testRailCaseShowsRailContextStepWithPmrBikeAndStrandingSummaries(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art18' => '1',
                'gate_art20' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'pmr_user' => 'yes',
                'pmr_booked' => 'yes',
                'pmr_delivered_status' => 'no',
                'pmr_promised_missing' => 'yes',
                'pmr_facility_details' => 'Ingen rampe ved perronen.',
                'bike_was_present' => 'yes',
                'bike_delay' => 'yes',
                'bike_reservation_made' => 'no',
                'bike_reservation_required' => 'yes',
                'bike_denied_boarding' => 'yes',
                'bike_refusal_reason_provided' => 'yes',
                'bike_refusal_reason_type' => 'capacity',
                'a20_station_stranded' => 'yes',
                'rail_stranding_context' => 'station',
                'stranded_station_name' => 'Ringsted',
                'stranded_current_station' => 'Ringsted',
                'rail_station_where_ended' => 'station',
                'rail_station_expenses_signal' => 'yes',
                'rail_station_expense_types' => ['meals'],
                'is_stranded_trin5' => 'yes',
                'blocked_train_alt_transport' => 'no',
                'blocked_no_transport_action' => 'self_arranged',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art18' => true,
                    'gate_art20' => true,
                    'gate_art20_2c' => true,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=rail_context');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Rail gates og kontekst', $body);
        $this->assertStringContainsString('Rail PMR / handicap', $body);
        $this->assertStringContainsString('Rail cykel (Art. 6)', $body);
        $this->assertStringContainsString('Rail strandet paa station', $body);
        $this->assertStringContainsString('Rail strandet paa sporet', $body);
        $this->assertStringContainsString('Ingen rampe ved perronen.', $body);
        $this->assertStringContainsString('Reservation kraevet', $body);
        $this->assertStringContainsString('Kapacitet', $body);
        $this->assertStringContainsString('Ringsted', $body);
        $this->assertStringContainsString('Fandt selv transport', $body);
        $this->assertStringContainsString('Stationsudgifter og kvitteringer', $body);
        $this->assertStringContainsString('Udgifter fra strandet paa sporet', $body);
        $this->assertStringNotContainsString('Artikel 11: Uledsaget barn', $body);
        $this->assertStringNotContainsString('PMR-sporet bruges kun til artikel 11', $body);
    }

    public function testRailCaseSupportStepKeepsRailContextBlocksOutOfAssistance(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art20' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'pmr_user' => 'yes',
                'bike_was_present' => 'yes',
                'a20_station_stranded' => 'yes',
                'rail_stranding_context' => 'station',
                'stranded_current_station' => 'Odense',
                'rail_station_expense_types' => ['meals'],
                'meal_self_paid_amount' => '75.00',
                'meal_self_paid_currency' => 'DKK',
                'is_stranded_trin5' => 'yes',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art20' => true,
                    'gate_art20_2c' => true,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=support');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Care-udgifter og kvitteringer', $body);
        $this->assertStringContainsString('Selvbetalte maaltider fra frontend.', $body);
        $this->assertStringNotContainsString('Rail PMR / handicap', $body);
        $this->assertStringNotContainsString('Rail cykel (Art. 6)', $body);
        $this->assertStringNotContainsString('Rail station-kontekst fra frontend', $body);
        $this->assertStringNotContainsString('Rail strandet paa sporet', $body);
        $this->assertStringNotContainsString('Artikel 11: Uledsaget barn', $body);
    }

    public function testRailCaseDocumentsUsesSystemArt12ReviewInsteadOfPassengerDropdowns(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'seller_channel' => 'operator',
                'same_transaction' => 'yes',
                'through_ticket_disclosure' => 'yes',
                'separate_contract_notice' => 'no',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_contract_structure_seed' => [
                    'seller_channel' => 'operator',
                    'same_transaction' => 'yes',
                    'through_ticket_disclosure' => 'yes',
                    'separate_contract_notice' => 'no',
                    'effective_contract_model' => 'through',
                    'liable_basis' => 'stk3',
                    'operator_names' => ['Danish State Railways', 'DB Fernverkehr AG'],
                ],
                'air_backend_ticket_analysis' => [
                    'transport_mode' => 'rail',
                    'rail_art12_review' => [
                        'same_transaction_confirmed' => 'yes',
                        'shared_pnr_scope' => 'yes',
                        'disclosure_evidence' => 'yes',
                        'separate_notice_evidence' => 'no',
                        'final_outcome' => 'through',
                        'liable_basis' => 'stk3',
                        'confidence' => 'high',
                        'notes' => [
                            'Uploaden indeholder markoerer for gennemgaaende eller beskyttet forbindelse.',
                        ],
                    ],
                    'summary' => 'Rail-billet matcher i store traek den valgte rejse og giver en systemvurdering af Art. 12.',
                    'needs_manual_review' => 'no',
                ],
            ],
        ]);

        $this->get('/passenger/case?step=documents');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Rail Art. 12 review', $body);
        $this->assertStringContainsString('De resterende Art. 12-spoergsmaal udfyldes af systemet', $body);
        $this->assertStringContainsString('Dokumenteret i uploaden', $body);
        $this->assertStringContainsString('name="rail_art12_final_outcome" value="through"', $body);
        $this->assertStringContainsString('name="rail_art12_liable_basis" value="stk3"', $body);
        $this->assertStringContainsString('HIGH', $body);
        $this->assertStringNotContainsString('select id="rail_art12_same_transaction_confirmed"', $body);
        $this->assertStringNotContainsString('select id="rail_art12_shared_pnr_scope"', $body);
        $this->assertStringNotContainsString('select id="rail_art12_disclosure_evidence"', $body);
        $this->assertStringNotContainsString('select id="rail_art12_separate_notice_evidence"', $body);
        $this->assertStringNotContainsString('select id="rail_art12_final_outcome"', $body);
        $this->assertStringNotContainsString('select id="rail_art12_liable_basis"', $body);
    }

    public function testRailCaseBackendTicketPriceOverrideFeedsDocumentsAndOverview(): void
    {
        $this->session([
            'flow.flags' => [
                'travel_state' => 'completed',
                'gate_art19' => '1',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'price' => '1200',
                'price_currency' => 'DKK',
                'rail_backend_ticket_price' => '1500',
                'rail_backend_ticket_price_currency' => 'DKK',
                'rail_backend_ticket_price_basis' => 'affected_part',
                'rail_backend_ticket_price_note' => 'Pris korrigeret efter billetupload.',
                'incident_main' => 'delay',
            ],
            'flow.meta' => [
                'entry_travel_state' => 'completed',
                'rail_incident_seed' => [
                    'gate_art19' => true,
                    'arrival_delay_minutes' => 120,
                ],
            ],
        ]);

        $this->get('/passenger/case?step=documents');
        $this->assertResponseOk();
        $documentsBody = (string)$this->_response->getBody();

        $this->assertStringContainsString('Rail prisgrundlag (Art. 19)', $documentsBody);
        $this->assertStringContainsString('Billetpris fra trin 2', $documentsBody);
        $this->assertStringContainsString('Aktivt prisgrundlag i backend', $documentsBody);
        $this->assertStringContainsString('name="rail_backend_ticket_price"', $documentsBody);
        $this->assertStringContainsString('name="rail_backend_ticket_price_currency"', $documentsBody);
        $this->assertStringContainsString('name="rail_backend_ticket_price_basis"', $documentsBody);
        $this->assertStringContainsString('name="rail_backend_ticket_price_note"', $documentsBody);
        $this->assertStringContainsString('1,500.00 DKK', $documentsBody);
        $this->assertStringContainsString('1,200.00 DKK', $documentsBody);

        $this->get('/passenger/case?step=refund');
        $this->assertResponseOk();
        $refundBody = (string)$this->_response->getBody();

        $this->assertStringContainsString('Backend-bekraeftet billetprisgrundlag', $refundBody);
        $this->assertStringContainsString('Prisgrundlag: Kun den relevante del', $refundBody);
        $this->assertStringContainsString('750.00 DKK', $refundBody);
    }

    public function testPassengerStartShowsOnlyRailCardOnRailPublicHost(): void
    {
        Configure::write('PublicSite', ['enabled' => false, 'landingPath' => '/passenger/start']);
        Configure::write('HostRouting', [
            'defaults' => [
                'landingPath' => '/passenger/start',
                'hideTopNav' => true,
                'hidePassengerNav' => true,
                'blockAdminRoutes' => true,
            ],
            'publicHosts' => [
                'rail.example.com' => ['transportMode' => 'rail'],
            ],
        ]);

        $this->configRequest([
            'environment' => [
                'HTTP_HOST' => 'rail.example.com',
            ],
        ]);

        $this->get('/passenger/start');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Tog', $body);
        $this->assertStringNotContainsString('Fly', $body);
        $this->assertStringNotContainsString('Faerge', $body);
        $this->assertStringNotContainsString('Bus', $body);
    }
}
