<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class FlowControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // Default to GET base URL; no special fixtures required for this controller
    }

    public function testAjaxHooksReturnsOnlyHooksPanel(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
                'ticket_upload_mode' => 'ticketless',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
            ],
        ]);

        $this->configRequest([
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        // Split-flow: AJAX hooks fragment is served from entitlements()
        $this->get('/flow/entitlements?ajax_hooks=1');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        // Should contain hooks panel header from element
        $this->assertStringContainsString('Live hooks & AUTO', $body, 'Hooks panel header missing in AJAX fragment.');
        // Should not contain a full page wrapper
        $this->assertStringNotContainsString('<html', $body, 'Unexpected full HTML document returned for AJAX hooks request.');
        $this->assertStringNotContainsString('<body', $body, 'Unexpected full HTML document returned for AJAX hooks request.');
        // Avoid left TOC duplication
        $this->assertStringNotContainsString('class="toc"', $body, 'TOC sidebar should not be included in hooks fragment.');
    }

    public function testChoicesPostRedirectsToRemedies(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        // TRIN 6 has strict gating: requires TRIN 3-5 to be completed.
        $this->session([
            'flow.flags' => ['step3_done' => '1', 'step4_done' => '1', 'step5_done' => '1', 'gate_art20_2c' => '1', 'travel_state' => 'ongoing'],
            'flow.incident' => ['main' => 'cancellation', 'missed' => '', 'missed_source' => 'incident_form'],
        ]);

        $data = [
            // Minimal TRIN 6 payload; controller should still redirect regardless of completeness.
            'is_stranded_trin5' => 'no',
        ];
        $this->post('/flow/choices', $data);
        $this->assertResponseCode(302);
        $this->assertNotSame('', $this->_response->getHeaderLine('Location'));
    }

    public function testRailChoicesShowsGuidanceCapsCard(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art20_2c' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'is_stranded_trin5' => 'yes',
                'blocked_train_alt_transport' => 'no',
                'blocked_no_transport_action' => 'self_arranged',
                'blocked_self_paid_transport_type' => 'taxi',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'price_hints' => [
                    'taxi' => ['min' => 20, 'max' => 60, 'currency' => 'EUR'],
                    'altTransport' => ['min' => 35, 'max' => 120, 'currency' => 'EUR'],
                ],
            ],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->get('/flow/choices');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Live rail-estimat', $body);
        $this->assertStringNotContainsString('Rail-kontekstpanel', $body);
        $this->assertStringContainsString('Vejledende rail-niveauer (ikke faste juridiske caps)', $body);
        $this->assertStringContainsString('Taxi / minibus:', $body);
        $this->assertStringContainsString('Typisk niveau for taxi/minibus:', $body);
        $this->assertStringContainsString('Registrer kun transporttypen her. Beloeb, valuta og dokumentation indtastes senere i backend-sagen.', $body);
        $this->assertStringContainsString('name="blocked_self_paid_transport_type"', $body);
        $this->assertStringNotContainsString('name="blocked_self_paid_amount"', $body);
        $this->assertStringNotContainsString('name="blocked_self_paid_currency"', $body);
        $this->assertStringNotContainsString('name="blocked_self_paid_receipt"', $body);
    }

    public function testRailChoicesPostClearsFrontendExpenseInputs(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art20_2c' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'blocked_self_paid_amount' => '55.00',
                'blocked_self_paid_currency' => 'EUR',
                'blocked_self_paid_receipt' => 'C:\\fake\\receipt.pdf',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->post('/flow/choices', [
            'is_stranded_trin5' => 'yes',
            'blocked_train_alt_transport' => 'no',
            'blocked_no_transport_action' => 'self_arranged',
            'blocked_self_paid_transport_type' => 'bus',
            'a20_where_ended' => 'final_destination',
            'blocked_self_paid_amount' => '99.00',
            'blocked_self_paid_currency' => 'DKK',
            'blocked_self_paid_receipt' => 'C:\\fake\\new_receipt.pdf',
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('bus', 'flow.form.blocked_self_paid_transport_type');
        $this->assertSessionNotHasKey('flow.form.blocked_self_paid_amount');
        $this->assertSessionNotHasKey('flow.form.blocked_self_paid_currency');
        $this->assertSessionNotHasKey('flow.form.blocked_self_paid_receipt');
    }

    public function testBikeWasPresentAutoDefaultsToNoOnEntitlements(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
                'ticket_upload_mode' => 'ticketless',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
            ],
        ]);

        // Step 1: POST OCR text with no bike signals to trigger detection with low confidence and no hits
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $data = [
            'ocr_text' => 'Standard billet\nVoksen 1\nSæde 12A\nAfgang 08:12',
        ];
        $this->post('/flow/entitlements', $data);
        $this->assertResponseOk();

        // Step 2: GET same page to render template using session state set by controller
        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        // Assert the radio for bike_was_present=no is preselected (checked)
        $this->assertStringContainsString('name="bike_was_present" value="no" checked', $body);
    }

    public function testEntitlementsTicketlessDisablesJourneyFieldsToPreventOverwrite(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => ['ticket_upload_mode' => 'ticketless'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('id="journeyFieldsFieldset" disabled', $body);
        $this->assertStringNotContainsString('id="ticketlessFieldset" disabled', $body);
    }

    public function testEntitlementsTicketModeDisablesTicketlessFieldsToPreventOverwrite(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => ['ticket_upload_mode' => 'ticket'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('id="ticketlessFieldset" disabled', $body);
        $this->assertStringNotContainsString('id="journeyFieldsFieldset" disabled', $body);
    }

    public function testRailEntitlementsShowsDirectPriceEstimatePrompt(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
                'ticket_upload_mode' => 'ticketless',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
            ],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Hvad kostede billetten?', $body);
        $this->assertStringContainsString('Praecis pris', $body);
        $this->assertStringContainsString('Ca. estimat', $body);
        $this->assertStringContainsString('Kender ikke endnu', $body);
        $this->assertStringContainsString('Live rail-estimat', $body);
    }

    public function testRailEntitlementsShowsAwaitingIncidentMessageWhenPriceIsKnown(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
                'ticket_upload_mode' => 'ticketless',
                'rail_price_input_mode' => 'exact',
                'price' => '200.00',
                'price_currency' => 'DKK',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'transport_mode_source' => 'manual',
            ],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Live rail-estimat', $body);
        $this->assertStringContainsString('Afventer haendelse', $body);
        $this->assertStringContainsString('Billetpris er registreret. Kompensationen beregnes, naar haendelsen er afklaret i TRIN 5.', $body);
    }

    public function testRailIncidentPostActivatesGateArt19WhenDelay60Confirmed(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step1_done' => '1',
                'step2_done' => '1',
                'step4_done' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
                'gate_art19' => '',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'price' => '200.00',
                'price_currency' => 'DKK',
                'rail_price_input_mode' => 'exact',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'rail_incident_seed' => [
                    'mode' => 'rail',
                    'gate_art18' => false,
                    'gate_art19' => false,
                    'gate_art20' => false,
                ],
            ],
        ]);

        $this->post('/flow/incident', [
            'incident_main' => 'delay',
            'expected_delay_60' => 'yes',
            'delay_already_60' => 'no',
            'operatorExceptionalCircumstances' => 'no',
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('1', 'flow.flags.step5_done');
        $this->assertSession('1', 'flow.flags.gate_art19');
        $this->assertSession('1', 'flow.flags.gate_art18');
        $this->assertSession('1', 'flow.flags.gate_art20');
    }

    public function testRailEntitlementsRequiresTicketPriceWhenPriceMarkedKnown(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
        ]);

        $this->post('/flow/entitlements', [
            'transport_mode' => 'rail',
            'ticket_upload_mode' => 'ticketless',
            'dep_station' => 'Odense',
            'arr_station' => 'Aarhus H',
            'dep_date' => '2026-05-15',
            'rail_price_input_mode' => 'exact',
            'price' => '',
            'continue' => '1',
        ]);

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/entitlements', $this->_response->getHeaderLine('Location'));
        $this->assertSession('', 'flow.flags.step2_done');
    }

    public function testRailEntitlementsAllowsBackendFallbackWhenPriceUnknown(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
        ]);

        $this->post('/flow/entitlements', [
            'transport_mode' => 'rail',
            'ticket_upload_mode' => 'ticketless',
            'dep_station' => 'Odense',
            'arr_station' => 'Aarhus H',
            'dep_date' => '2026-05-15',
            'rail_price_input_mode' => 'unknown',
            'continue' => '1',
        ]);

        $this->assertResponseCode(302);
    }

    public function testRailEntitlementsPreservesRailDownstreamStateWhenOnlyPriceChanges(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step1_done' => '1',
                'step2_done' => '1',
                'step35_done' => '1',
                'step4_done' => '1',
                'step5_done' => '1',
                'entry_variant' => 'rail_split',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'ticket_upload_mode' => 'ticketless',
                'dep_station' => 'Odense',
                'arr_station' => 'Aarhus H',
                'dep_date' => '2026-05-15',
                'dep_time' => '08:00',
                'price' => '299.00',
                'price_currency' => 'DKK',
                'rail_price_input_mode' => 'exact',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'entry_variant' => 'rail_split',
                'gating_mode' => 'rail',
                'rail_selected_departure' => [
                    'id' => 'dep-1',
                    'origin_station_name' => 'Odense',
                    'destination_station_name' => 'Aarhus H',
                ],
            ],
        ]);

        $this->post('/flow/entitlements', [
            'transport_mode' => 'rail',
            'ticket_upload_mode' => 'ticketless',
            'dep_station' => 'Odense',
            'arr_station' => 'Aarhus H',
            'dep_date' => '2026-05-15',
            'dep_time' => '08:00',
            'rail_price_input_mode' => 'estimate',
            'price' => '349.00',
            'price_currency' => 'DKK',
            'continue' => '1',
        ]);

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/rail-departure-select', $this->_response->getHeaderLine('Location'));
        $this->assertSession('1', 'flow.flags.step35_done');
        $this->assertSession('1', 'flow.flags.step4_done');
        $this->assertSession('1', 'flow.flags.step5_done');
        $this->assertSession('estimate', 'flow.form.rail_price_input_mode');
        $this->assertSession('yes', 'flow.form.price_known');
    }

    public function testRailCompensationShowsPendingPriceNoticeWhenTicketPriceMissing(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art19' => '1',
                'travel_state' => 'completed',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'rail_price_input_mode' => 'unknown',
                'price_known' => 'no',
                'delayAtFinalMinutes' => '90',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'rail_incident_seed' => [
                    'gate_art19' => true,
                    'arrival_delay_minutes' => 90,
                ],
            ],
            'flow.incident' => [
                'main' => 'delay',
            ],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Billetpris fra TRIN 2:', $body);
        $this->assertStringContainsString('Afventer prisgrundlag', $body);
        $this->assertStringContainsString('Rail Art. 19 og samlet krav er derfor kun delvist beregnet', $body);
    }

    public function testRailDepartureSelectShowsAiFallbackOperatorHintsAsLowConfidenceOnly(): void
    {
        $candidate = [
            'id' => 'ai-case-1',
            'source' => 'ai_fallback',
            'confidence' => 0.24,
            'service_name' => 'International rail corridor',
            'line_name' => 'via Hamburg Hbf',
            'product' => 'EC',
            'operator_name' => null,
            'origin_station_name' => 'Kobenhavn H',
            'destination_station_name' => 'Barcelona Sants',
            'planned_departure_at' => '2026-05-11T06:00:00+02:00',
            'estimated_departure_at' => '2026-05-11T06:00:00+02:00',
            'planned_arrival_at' => '2026-05-12T11:39:00+02:00',
            'estimated_arrival_at' => '2026-05-12T11:39:00+02:00',
            'status' => 'unknown',
            'raw' => [
                'provider_hint' => 'ai_fallback',
                'transfer_count' => 7,
                'rail_leg_count' => 8,
                'leg_count' => 8,
                'has_connections' => true,
                'transfer_station_names' => [
                    'Hamburg Hbf',
                    'Koln Hbf',
                    'Paris Gare du Nord',
                    'Paris Gare de Lyon',
                    'Lyon Part-Dieu',
                ],
                'generated_path' => [
                    'Kobenhavn H',
                    'Hamburg Hbf',
                    'Koln Hbf',
                    'Paris Gare du Nord',
                    'Paris Gare de Lyon',
                    'Lyon Part-Dieu',
                    'Barcelona Sants',
                ],
                'generated_from_country' => 'DK',
                'generated_to_country' => 'ES',
            ],
        ];
        $signature = (new \App\Service\Rail\RailDepartureSearchService())->buildLookupSignature([
            'from_station' => 'Kobenhavn H',
            'to_station' => 'Barcelona Sants',
            'date' => '2026-05-11',
            'time' => '06:00',
            'locale' => 'da-DK',
        ]);

        $this->session([
            'flow.flags' => [
                'step2_done' => '1',
                'travel_state' => 'ongoing',
                'transport_mode' => 'rail',
                'entry_variant' => 'rail_split',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'dep_station' => 'Kobenhavn H',
                'arr_station' => 'Barcelona Sants',
                'dep_date' => '2026-05-11',
                'dep_time' => '06:00',
                'selected_rail_departure_id' => 'ai-case-1',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'entry_variant' => 'rail_split',
                'rail_selected_departure' => $candidate,
                'rail_selected_departure_id' => 'ai-case-1',
                'rail_departure_candidates' => [$candidate],
                'rail_departure_candidates_signature' => $signature,
                'rail_contract_structure_seed' => [
                    'operator_count' => 0,
                    'needs_followup' => true,
                    'confidence' => 'low',
                    'auto_label' => 'Kraver Art. 12-afklaring',
                ],
            ],
        ]);

        $this->get('/flow/rail-departure-select');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Sandsynlige operatoerer (AI hint): DSB · Deutsche Bahn · SNCF Voyageurs · Renfe', $body);
        $this->assertStringContainsString('AI fallback peger paa sandsynlige operatoerer: DSB · Deutsche Bahn · SNCF Voyageurs · Renfe.', $body);
        $this->assertStringContainsString('Hintene har lav confidence og bruges kun som UX-stoette. De taeller ikke som sikker Art. 12-dokumentation.', $body);
    }

    public function testRailCompensationShowsEstimateNoticeWhenPriceIsApproximate(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art19' => '1',
                'travel_state' => 'completed',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'rail_price_input_mode' => 'estimate',
                'price_known' => 'yes',
                'price' => '200.00',
                'price_currency' => 'DKK',
                'delayAtFinalMinutes' => '90',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'rail_incident_seed' => [
                    'gate_art19' => true,
                    'arrival_delay_minutes' => 90,
                ],
            ],
            'flow.incident' => [
                'main' => 'delay',
            ],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Billetpris fra TRIN 2 (ca. estimat):', $body);
        $this->assertStringContainsString('Billetprisen er oplyst som et ca. estimat i TRIN 2.', $body);
    }

    public function testEntitlementsUsesUnifiedContractBlock(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('id="modeContractCard"', $body);
        $this->assertStringContainsString('Kontrakt og ansvar', $body);
        $this->assertStringNotContainsString('id="art12MinimalBlock"', $body);
    }

    public function testEntitlementsShowsGenericJourneyFieldsWhenUploadModeHasNoResolvedTransportMode(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticket',
            ],
            'flow.meta' => [
                '_auto' => [
                    'operator' => ['value' => 'Bornholmslinjen'],
                    'dep_station' => ['value' => 'Ronne'],
                    'arr_station' => ['value' => 'Ystad'],
                ],
            ],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Basisrejse (transportform ikke afgjort endnu)', $body);
        $this->assertStringContainsString('Bornholmslinjen', $body);
    }

    public function testJourneyShowsHooksPlaceholderForLazyLoad(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step35_done' => '1', 'transport_mode' => 'rail', 'gating_mode' => 'rail', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.meta' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.journey' => [
                'country' => ['value' => 'DK'],
                'segments' => [
                    ['country' => 'DK'],
                ],
            ],
        ]);

        $this->get('/flow/journey');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('id="hooksPanel"', $body);
        $this->assertStringContainsString('Indlaeser hooks...', $body);
    }

    public function testJourneyAjaxHooksReturnsOnlyHooksPanel(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step35_done' => '1', 'transport_mode' => 'rail', 'gating_mode' => 'rail', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.meta' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.journey' => [
                'country' => ['value' => 'DK'],
                'segments' => [
                    ['country' => 'DK'],
                ],
            ],
        ]);

        $this->configRequest([
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/flow/journey?ajax_hooks=1');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Live hooks & AUTO', $body);
        $this->assertStringNotContainsString('<html', $body);
    }

    public function testFerryStationRedirectsToDepartureSelectInSplitFlow(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'ferry', 'entry_variant' => 'ferry_split', 'needs_initial_incident_router' => '1'],
            'flow.form' => ['transport_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry', 'entry_variant' => 'ferry_split'],
        ]);

        $this->get('/flow/station');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/ferry-departure-select', $this->_response->getHeaderLine('Location'));
    }

    public function testFerryDepartureSelectRendersRouteCandidates(): void
    {
        $this->session([
            'flow.flags' => [
                'step1_done' => '1',
                'step2_done' => '1',
                'transport_mode' => 'ferry',
                'entry_variant' => 'ferry_split',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'dep_station' => 'Ronne',
                'arr_station' => 'Ystad',
                'dep_date' => '2026-04-23',
                'dep_time' => '08:30',
                'arr_time' => '09:50',
                'operator' => 'Bornholmslinjen',
            ],
            'flow.meta' => ['transport_mode' => 'ferry', 'entry_variant' => 'ferry_split'],
        ]);

        $this->get('/flow/ferry-departure-select');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('TRIN 3 - Vaelg afgang', $body);
        $this->assertStringContainsString('Vaelg din afgang fra listen', $body);
        $this->assertStringContainsString('name="selected_ferry_departure_key"', $body);
        $this->assertStringContainsString('Bornholmslinjen', $body);
    }

    public function testRailStationRedirectsToRailStrandingWhenRouterIsNotNeeded(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'rail', 'needs_initial_incident_router' => '', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail'],
            'flow.meta' => ['transport_mode' => 'rail'],
        ]);

        $this->get('/flow/station');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/railstranding', $this->_response->getHeaderLine('Location'));
    }

    public function testRailStrandingAllowsDirectAccessAfterEntitlementsWhenRouterIsNotNeeded(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'rail', 'needs_initial_incident_router' => '', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.meta' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
        ]);

        $this->get('/flow/railstranding');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('TRIN 3.5 - Strandet paa station/sporet', $body);
        $this->assertStringContainsString('name="rail_stranding_context"', $body);
        $this->assertStringContainsString('/flow/entitlements', $body);
    }

    public function testRailStrandingShowsGuidanceCapsCard(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'rail', 'needs_initial_incident_router' => '', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'price_hints' => [
                    'meals' => ['min' => 12, 'max' => 30, 'currency' => 'EUR'],
                    'hotelPerNight' => ['min' => 80, 'max' => 140, 'currency' => 'EUR'],
                    'taxi' => ['min' => 20, 'max' => 60, 'currency' => 'EUR'],
                    'altTransport' => ['min' => 35, 'max' => 120, 'currency' => 'EUR'],
                ],
            ],
        ]);

        $this->get('/flow/railstranding');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Vejledende rail-niveauer (ikke faste juridiske caps)', $body);
        $this->assertStringContainsString('Alternativ videre transport:', $body);
    }

    public function testRailJourneyRequiresRailStrandingStepBeforeAccess(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'rail', 'needs_initial_incident_router' => '', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
            'flow.meta' => ['transport_mode' => 'rail', 'gating_mode' => 'rail'],
        ]);

        $this->get('/flow/journey');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/railstranding', $this->_response->getHeaderLine('Location'));
    }

    public function testFerryJourneySkipsStationAndShowsOnlyPmrForm(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step26_done' => '1', 'transport_mode' => 'ferry', 'entry_variant' => 'ferry_split', 'gating_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry', 'gating_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry', 'entry_variant' => 'ferry_split', 'gating_mode' => 'ferry'],
        ]);

        $this->get('/flow/journey');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('TRIN 4 - PMR / handicap (faerge, igangvaerende rejse)', $body);
        $this->assertStringContainsString('PMR-status', $body);
        $this->assertStringContainsString('/flow/ferry-departure-select', $body);
        $this->assertStringNotContainsString('TRIN 4a - Cykel og bagage (Art.6)', $body);
        $this->assertStringNotContainsString('Transport fra station? (Art. 20(3))', $body);
    }

    public function testFerryJourneyRequiresDepartureSelectionBeforeAccess(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'transport_mode' => 'ferry', 'entry_variant' => 'ferry_split', 'gating_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry', 'gating_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry', 'entry_variant' => 'ferry_split', 'gating_mode' => 'ferry'],
        ]);

        $this->get('/flow/journey');
        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/ferry-departure-select', $this->_response->getHeaderLine('Location'));
    }

    public function testBusJourneyUsesBusGatingWhenInitialIncidentModeIsBus(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step3_done' => '1', 'transport_mode' => 'rail', 'gating_mode' => 'bus', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'rail', 'gating_mode' => 'bus'],
            'flow.meta' => ['transport_mode' => 'rail', 'gating_mode' => 'bus'],
        ]);

        $this->get('/flow/journey');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('TRIN 4 - Bus gating', $body);
        $this->assertStringContainsString('Bus-specifik gating', $body);
        $this->assertStringNotContainsString('TRIN 4a - Cykel og bagage (Art.6)', $body);
        $this->assertStringNotContainsString('TRIN 4b - PMR / handicap', $body);
    }

    public function testBusTicketlessShowsAutoDerivedScopeSummary(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
                'transport_mode' => 'bus',
                'boarding_in_eu' => 'yes',
                'alighting_in_eu' => 'yes',
                'departure_from_terminal' => 'yes',
                'scheduled_distance_km' => '320',
            ],
            'flow.meta' => ['transport_mode' => 'bus'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-bus-scope-auto-summary="1"', $body);
        $this->assertStringContainsString('Auto-afledt scope', $body);
        $this->assertStringContainsString('data-bus-scope-summary="boarding_in_eu"', $body);
        $this->assertStringContainsString('data-bus-scope-summary="scheduled_distance_km"', $body);
        $this->assertStringContainsString('data-bus-scope-manual-fields="1"', $body);
    }

    public function testAirTicketlessShowsAutoDerivedScopeSummary(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
                'transport_mode' => 'air',
                'departure_airport_in_eu' => 'yes',
                'arrival_airport_in_eu' => 'yes',
                'operating_carrier_is_eu' => 'yes',
                'marketing_carrier_is_eu' => 'yes',
            ],
            'flow.meta' => ['transport_mode' => 'air'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-air-scope-auto-summary="1"', $body);
        $this->assertStringContainsString('Auto-afledt scope', $body);
        $this->assertStringContainsString('data-air-scope-summary="departure_airport_in_eu"', $body);
        $this->assertStringContainsString('data-air-scope-summary="marketing_carrier_is_eu"', $body);
        $this->assertStringContainsString('data-air-scope-manual-fields="1"', $body);
    }

    public function testFerryEntitlementsShowsReturnTicketFields(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Returbillet / hjemrejse', $body);
        $this->assertStringContainsString('name="trip_type"', $body);
        $this->assertStringContainsString('name="affected_leg"', $body);
        $this->assertStringContainsString('name="outbound_fare_amount"', $body);
        $this->assertStringContainsString('name="return_fare_amount"', $body);
        $this->assertStringContainsString('name="return_dep_station"', $body);
        $this->assertStringContainsString('name="return_arr_station"', $body);
        $this->assertStringContainsString('name="dep_terminal"', $body);
        $this->assertStringContainsString('name="arr_terminal"', $body);
        $this->assertStringContainsString('Færgeterminaler under havnene (valgfrit)', $body);
        $this->assertStringContainsString("url.searchParams.set('kind', 'terminal')", $body);
        $this->assertStringContainsString("url.searchParams.set('kind', 'port')", $body);
    }

    public function testFerryUploadShowsReturnLegBlockWhenAutoSegmentsSuggestReturnTicket(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'ticket_upload_mode' => 'ticket',
            ],
            'flow.meta' => [
                'transport_mode' => 'ferry',
                '_segments_auto' => [
                    [
                        'from' => 'Ronne',
                        'to' => 'Ystad',
                        'schedDep' => '14:30',
                        'depDate' => '2021-12-03',
                    ],
                    [
                        'from' => 'Ystad',
                        'to' => 'Ronne',
                        'schedDep' => '16:30',
                        'depDate' => '2021-12-05',
                    ],
                ],
            ],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('name="trip_type"', $body);
        $this->assertStringContainsString('id="modeJourneyReturnTripBlock"', $body);
        $this->assertStringContainsString('name="return_dep_date"', $body);
        $this->assertStringContainsString('name="return_dep_time"', $body);
        $this->assertStringContainsString('name="return_dep_station"', $body);
        $this->assertStringContainsString('name="return_arr_station"', $body);
        $this->assertStringContainsString('data-return-target="modeJourneyReturnTripBlock"', $body);
    }

    public function testUploadModeAutoDetectsTransportInsteadOfRequiringManualChoice(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => ['ticket_upload_mode' => 'ticket'],
            'flow.meta' => [],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Ved upload bruger vi LLM/OCR og kontraktmotoren til at finde den relevante transportgren.', $body);
        $this->assertStringContainsString('Transportformen vælges automatisk, når der er analyseret mindst én billet eller booking.', $body);
        $this->assertStringContainsString('id="transportModeCard"', $body);
        $this->assertStringContainsString('id="transportModeHidden" name="transport_mode" value=""', $body);
        $this->assertStringNotContainsString('name="transport_mode" value="rail" checked disabled', $body);
    }

    public function testTicketlessWithoutTransportChoiceKeepsModeUnselected(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
            ],
            'flow.meta' => [],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('id="transportModeCard"', $body);
        $this->assertStringContainsString("const transportMode = radioVal('transport_mode') || '';", $body);
        $this->assertStringContainsString('id="transportModeHidden" name="transport_mode" value="" disabled', $body);
        $this->assertStringNotContainsString('name="transport_mode" value="rail" checked', $body);
    }

    public function testTicketlessEntitlementsShowsManualJourneyStructureQuestion(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
                'transport_mode' => 'ferry',
            ],
            'flow.meta' => ['transport_mode' => 'ferry'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Bestod den købte rejse af ét direkte transportled, eller skulle du skifte undervejs?', $body);
        $this->assertStringContainsString('id="transportModeCard"', $body);
        $this->assertStringContainsString('name="transport_mode" value="ferry" checked', $body);
        $this->assertStringContainsString('name="journey_structure"', $body);
        $this->assertStringContainsString('name="original_contract_mode"', $body);
        $this->assertStringContainsString('name="shared_pnr_scope"', $body);
        $this->assertStringContainsString('Et direkte transportled uden skift', $body);
        $this->assertStringContainsString('Flere led med skift mellem forskellige transporttyper', $body);
        $this->assertStringNotContainsString('Hvilken transportform blev hovedkontrakten oprindelig solgt som?', $body);
        $this->assertStringContainsString('value="single_segment" selected', $body);
        $this->assertStringContainsString('value="yes" selected', $body);
        $this->assertStringNotContainsString('Mangler lige nu:', $body);
        $this->assertStringNotContainsString('om købet omfatter flere segmenter', $body);
        $this->assertStringContainsString('Ticketless opdaterer STOP-boksen automatisk', $body);
        $this->assertStringContainsString("const modeContractRefreshNames = new Set([", $body);
        $this->assertStringContainsString("'journey_structure'", $body);
        $this->assertStringContainsString("sessionStorage.setItem('entitlementsFocusCard', 'modeContractCard')", $body);
        $this->assertStringContainsString('Færgeterminaler under havnene (valgfrit)', $body);
        $this->assertStringContainsString('input.offsetParent === null', $body);
        $this->assertStringContainsString("input.closest('fieldset[disabled]')", $body);
        $this->assertStringContainsString("box.className = 'node-suggest portal'", $body);
        $this->assertStringContainsString("document.body.appendChild(box)", $body);
        $this->assertStringContainsString('Vaelg den transportmåde sagen starter i. Hvis rejsen er multimodal, finder vi senere det først ramte segment.', $body);
    }

    public function testRailTicketlessShowsContractBlockBeforeTicketlessFields(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticketless',
                'transport_mode' => 'rail',
            ],
            'flow.meta' => ['transport_mode' => 'rail'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Ticketless tog', $body);
        $this->assertStringContainsString('id="modeContractCard"', $body);
        $this->assertStringContainsString('id="ticketlessCard"', $body);
        $this->assertLessThan(
            strpos($body, 'id="ticketlessCard"'),
            strpos($body, 'id="modeContractCard"')
        );
    }

    public function testFerryEntitlementsUsesMultimodalArt12StopSequence(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'dep_station' => 'Ronne',
                'arr_station' => 'Ystad',
                'operator' => 'Bornholmslinjen',
            ],
            'flow.meta' => ['transport_mode' => 'ferry'],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Kontrakt og ansvar (multimodal masterflow)', $body);
        $this->assertStringContainsString('1. Art. 12 kontraktanalyse', $body);
        $this->assertStringContainsString('name="through_ticket_disclosure"', $body);
        $this->assertStringContainsString('2. STOP / fork', $body);
        $this->assertStringContainsString('Billet = Samlet kontrakt', $body);
        $this->assertStringContainsString('booking_cohesion:', $body);
        $this->assertStringContainsString('confidence:', $body);
        $this->assertStringContainsString('TRIN 2 stopper nu ved kontraktanalysen.', $body);
        $this->assertStringNotContainsString('3. Problemsegment', $body);
        $this->assertStringNotContainsString('4. Ansvarsplacering', $body);
        $this->assertStringNotContainsString('Oprindelig kontraktform:', $body);

        $art12Pos = strpos($body, '1. Art. 12 kontraktanalyse');
        $stopPos = strpos($body, '2. STOP / fork');
        $problemPos = strpos($body, 'TRIN 2 stopper nu ved kontraktanalysen.');
        $this->assertNotFalse($art12Pos);
        $this->assertNotFalse($stopPos);
        $this->assertNotFalse($problemPos);
        $this->assertTrue($art12Pos < $stopPos && $stopPos < $problemPos);
    }

    public function testUploadModeShowsAutoContractSummaryBeforeManualFallback(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'ticket_upload_mode' => 'ticket',
                '_ticketFilename' => 'ticket.pdf',
                'transport_mode' => 'ferry',
            ],
            'flow.meta' => [
                'transport_mode' => 'ferry',
                '_multimodal' => [
                    'transport_mode' => 'ferry',
                    'contract_meta' => [
                        'journey_structure' => 'multimodal_connections',
                        'shared_booking_reference' => true,
                        'single_transaction' => true,
                        'contract_structure_disclosure' => 'unknown',
                        'separate_contract_notice' => 'unclear',
                        'contract_topology' => 'unknown_manual_review',
                        'contract_topology_confidence' => 'medium',
                        'booking_cohesion' => 'strong',
                        'service_cohesion' => 'strong',
                    ],
                    'contract_decision' => [
                        'stage' => 'COLLECT',
                        'contract_label' => 'Kraever flere svar',
                        'basis' => 'manual_review',
                        'notes' => ['Kontraktstrukturen er ikke afgjort endnu.'],
                    ],
                ],
            ],
        ]);

        $this->get('/flow/entitlements');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Autoanalyse fra upload', $body);
        $this->assertStringContainsString('Kontraktanalysen er koert automatisk paa de uploadede billetter.', $body);
        $this->assertStringContainsString('Upload-sporet er auto-foerst.', $body);
        $this->assertStringContainsString('id="modeContractQuestions"', $body);
        $this->assertStringContainsString('data-auto-open="1"', $body);
        $this->assertStringContainsString('Udfyld kun de manglende svar fra autoanalysen nedenfor.', $body);
        $this->assertStringContainsString('id="modeContractShowAllBtn" class="small" style="background:transparent;', $body);
        $this->assertStringContainsString('seller: operator', $body);
        $this->assertStringContainsString('bookingreference:', $body);
        $this->assertStringContainsString('journey_structure:', $body);
    }

    public function testFerryIncidentShowsRequestedSectionsAndDefersRemovedQuestions(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step3_done' => '1', 'step4_done' => '1', 'transport_mode' => 'ferry', 'gating_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry', 'gating_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry', 'gating_mode' => 'ferry'],
        ]);

        $this->get('/flow/incident');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Primary incident + incident chain', $body);
        $this->assertStringContainsString('name="primary_incident_type"', $body);
        $this->assertStringContainsString('name="follow_on_missed_connection"', $body);
        $this->assertStringContainsString('name="next_segment_operated_normally"', $body);
        $this->assertStringContainsString('<span aria-hidden="true">&#x23F1;</span> Afbrydelser/forsinkelser', $body);
        $this->assertStringContainsString('Var passageren informeret om aflysning/forsinkelse foer koeb?', $body);
        $this->assertStringContainsString('<span>Aaben billet / afgangstid</span>', $body);
        $this->assertStringContainsString('Bruges til ferry-gating for Art. 17, 18 og 19.', $body);
        $this->assertStringContainsString('Disse svar kan afskaere hotel og/eller kompensation i ferry-flowet.', $body);
        $this->assertStringContainsString('name="informed_before_purchase"', $body);
        $this->assertStringContainsString('name="open_ticket_without_departure_time"', $body);
        $this->assertStringContainsString('name="weather_safety"', $body);
        $this->assertStringContainsString('name="extraordinary_circumstances"', $body);
        $this->assertStringNotContainsString('<strong>Afbrydelser / forsinkelser</strong>', $body);
        $this->assertStringNotContainsString('<strong>Haendelse</strong>', $body);
        $this->assertStringNotContainsString('Skyldtes problemet passagerens egne forhold?', $body);
        $this->assertStringNotContainsString('Var overnatning noedvendig pga. haendelsen?', $body);
        $this->assertStringNotContainsString('Forsinkelse ved ankomst (minutter)', $body);
        $this->assertStringNotContainsString('Planlagt rejsevarighed (minutter)', $body);
    }

    public function testRailIncidentRebuildsMissedConnectionChoicesFromOcrWhenStoredSegmentsAreShorter(): void
    {
        $ocrText = implode("\n", [
            'Din Rejseplan',
            'Detaljer',
            'Kobenhavn H',
            'Vejle St.',
            'Afg: 05:56',
            'Ank: 07:56',
            'IC-Lyntog 19 til Vejle St.',
            'Vejle St.',
            'Herning St.',
            'Afg: 08:03',
            'Ank: 08:57',
            'Arriva-tog 5917 til Herning St.',
            'Herning St.',
            'Herning Messecenter St.',
            'Afg: 09:02',
            'Ank: 09:04',
            'Arriva-tog 5317 til Herning Messecenter St.',
        ]);

        $this->session([
            'flow.flags' => [
                'step1_done' => '1',
                'step2_done' => '1',
                'step3_done' => '1',
                'step4_done' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_missed' => 'yes',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                '_ocr_text' => $ocrText,
                '_segments_auto' => [
                    ['from' => 'Vejle St.', 'to' => 'Herning St.', 'schedDep' => '08:03', 'schedArr' => '08:57'],
                    ['from' => 'Herning St.', 'to' => 'Herning Messecenter St.', 'schedDep' => '09:02', 'schedArr' => '09:04'],
                ],
            ],
            'flow.journey' => [
                'country' => ['value' => 'DK'],
                'segments' => [
                    ['from' => 'Vejle St.', 'to' => 'Herning St.', 'schedDep' => '08:03', 'schedArr' => '08:57'],
                    ['from' => 'Herning St.', 'to' => 'Herning Messecenter St.', 'schedDep' => '09:02', 'schedArr' => '09:04'],
                ],
            ],
        ]);

        $this->get('/flow/incident');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Kobenhavn H -&gt; Vejle St.', $body);
        $this->assertStringContainsString('name="missed_connection_pick" value="Vejle St."', $body);
        $this->assertStringContainsString('name="missed_connection_pick" value="Herning St."', $body);
    }

    public function testRailIncidentShowsPreinformedDisruptionWidgetInMainRailCard(): void
    {
        $this->session([
            'flow.flags' => [
                'step1_done' => '1',
                'step2_done' => '1',
                'step3_done' => '1',
                'step4_done' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
        ]);

        $this->get('/flow/incident');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Afbrydelser/forsinkelser foer koeb', $body);
        $this->assertStringContainsString('Var der meddelt afbrydelse/forsinkelse foer dit koeb?', $body);
        $this->assertStringContainsString('name="preinformed_disruption"', $body);
        $this->assertStringContainsString('name="preinfo_channel"', $body);
    }

    public function testFerryIncidentHooksPanelShowsFerrySummary(): void
    {
        $this->session([
            'flow.flags' => ['step1_done' => '1', 'step2_done' => '1', 'step3_done' => '1', 'step4_done' => '1', 'transport_mode' => 'ferry', 'gating_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'gating_mode' => 'ferry',
                'dep_station' => 'Helsingor',
                'arr_station' => 'Helsingborg',
            ],
            'flow.meta' => [
                'transport_mode' => 'ferry',
                'gating_mode' => 'ferry',
                '_multimodal' => [
                    'transport_mode' => 'ferry',
                    'contract_meta' => [
                        'contract_topology' => 'single_mode_single_contract',
                        'claim_transport_mode' => 'ferry',
                    ],
                    'incident_meta' => [
                        'incident_type' => 'cancellation',
                        'informed_before_purchase' => true,
                        'weather_safety' => false,
                        'extraordinary_circumstances' => false,
                        'open_ticket_without_departure_time' => false,
                        'arrival_delay_minutes' => 130,
                        'scheduled_journey_duration_minutes' => 180,
                    ],
                    'ferry_scope' => [
                        'regulation_applies' => true,
                        'scope_basis' => 'departure_eu',
                        'service_type' => 'passenger_service',
                        'departure_from_terminal' => true,
                    ],
                    'ferry_contract' => [
                        'primary_claim_party' => 'carrier',
                        'primary_claim_party_name' => 'Scandlines',
                        'rights_module' => 'ferry',
                    ],
                    'ferry_rights' => [
                        'gate_art16_notice' => true,
                        'gate_art17_refreshments' => true,
                        'gate_art17_hotel' => false,
                        'gate_art18' => true,
                        'gate_art19' => false,
                        'art19_comp_band' => 'none',
                    ],
                    'claim_direction' => [
                        'claim_transport_mode' => 'ferry',
                    ],
                ],
            ],
        ]);

        $this->get('/flow/incident');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Ferry hooks', $body);
        $this->assertStringContainsString('Scope: <code>ikke_omfattet</code>', $body);
        $this->assertStringContainsString('service: <code>passenger_service</code>', $body);
        $this->assertStringContainsString('Port / terminal: <code>Helsingor</code> → <code>Helsingborg</code>', $body);
        $this->assertStringContainsString('Departure from terminal: <code>ukendt</code>', $body);
        $this->assertStringContainsString('claim party: <code>manual_review</code>', $body);
        $this->assertStringContainsString('Art. 18: <code>off</code>', $body);
        $this->assertStringContainsString('Art. 19: <code>off</code>', $body);
    }

    public function testFerryAssistanceStillContainsOvernightQuestion(): void
    {
        $this->session([
            'flow.flags' => ['step5_done' => '1', 'gate_art20' => '1', 'transport_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry'],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->get('/flow/assistance');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('name="overnight_needed"', $body);
    }

    public function testAirDelayRemediesShowsOnlyRefundChoicesAtFivePlusHours(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'gate_air_delay_refund_5h' => '1',
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                'travel_state' => 'completed',
            ],
            'flow.form' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'incident_main' => 'delay',
                'remedyChoice' => 'refund_return',
            ],
            'flow.meta' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                '_multimodal' => [
                    'transport_mode' => 'air',
                    'air_rights' => [
                        'gate_air_delay_refund_5h' => true,
                    ],
                ],
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->get('/flow/remedies');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('name="remedyChoice" value="refund_return"', $body);
        $this->assertStringContainsString('name="remedyChoice" value="no_refund_continue"', $body);
        $this->assertStringContainsString('name="air_refund_scope"', $body);
        $this->assertStringNotContainsString('name="remedyChoice" value="reroute_soonest"', $body);
        $this->assertStringNotContainsString('name="remedyChoice" value="reroute_later"', $body);
    }

    public function testAirRerouteLaterDoesNotRenderLegacyLaterOutcomeQuestion(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                'travel_state' => 'completed',
            ],
            'flow.form' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'incident_main' => 'denied_boarding',
                'remedyChoice' => 'reroute_later',
            ],
            'flow.meta' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                '_multimodal' => [
                    'transport_mode' => 'air',
                    'air_rights' => [
                        'gate_air_reroute_refund' => true,
                    ],
                ],
            ],
            'flow.incident' => ['main' => 'denied_boarding'],
        ]);

        $this->get('/flow/remedies');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('name="air_self_arranged_reroute"', $body);
        $this->assertStringNotContainsString('Operat&oslash;ren tilb&oslash;d senere oml&aelig;gning', $body);
        $this->assertStringNotContainsString('Jeg k&oslash;bte selv en billet til senere', $body);
    }

    public function testRailRerouteUsesBackendHintInsteadOfFrontendExpenseAmounts(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'remedyChoice' => 'reroute_soonest',
                'offer_provided' => 'no',
                'self_purchased_new_ticket' => 'yes',
                'self_purchase_reason' => 'no_offer',
                'self_purchase_approved_by_operator' => 'yes',
                'reroute_extra_costs' => 'yes',
                'reroute_extra_costs_type' => 'new_ticket',
                'reroute_extra_costs_amount' => '350.00',
                'reroute_extra_costs_currency' => 'DKK',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.compute' => [
                'delayMinEU' => 60,
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->get('/flow/remedies');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Type af ombookingsudgift', $body);
        $this->assertStringContainsString('Vaelg den mest relevante udgiftstype for den aktuelle rail-loesning. Beloeb, valuta, forklaring og kvitteringer registreres senere paa sagen.', $body);
        $this->assertStringContainsString('Vejledende rail-niveauer (ikke faste juridiske caps):', $body);
        $this->assertStringContainsString('Hvilken type udgift havde du?', $body);
        $this->assertStringNotContainsString('name="reroute_extra_costs_amount"', $body);
        $this->assertStringNotContainsString('name="reroute_extra_costs_currency"', $body);
    }

    public function testRailRerouteKeeps100MinuteQuestionHiddenUntilApprovalAnswered(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'remedyChoice' => 'reroute_soonest',
                'offer_provided' => 'no',
                'self_purchased_new_ticket' => 'yes',
                'self_purchase_reason' => 'no_offer',
                'reroute_extra_costs' => 'no',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.compute' => [
                'delayMinEU' => 60,
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->get('/flow/remedies');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Var din egen loesning godkendt af operatoeren?', $body);
        $this->assertMatchesRegularExpression('/id="rail100MinuteWrapPast"[^>]*style="display:none;"/', $body);
    }

    public function testRailRerouteForcesNoExtraCostsWhenAlternativeWasCommunicatedWithin100Minutes(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'remedyChoice' => 'reroute_soonest',
                'offer_provided' => 'no',
                'self_purchased_new_ticket' => 'yes',
                'self_purchase_reason' => 'no_offer',
                'self_purchase_approved_by_operator' => 'no',
                'reroute_info_within_100min' => 'yes',
                'reroute_extra_costs' => 'yes',
                'reroute_extra_costs_type' => 'new_ticket',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.compute' => [
                'delayMinEU' => 60,
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->get('/flow/remedies');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertMatchesRegularExpression('/name="reroute_extra_costs" value="no" checked/', $body);
        $this->assertMatchesRegularExpression('/<div class="grid-2 mt8 hidden" id="recWrapPast"/', $body);
        $this->assertStringNotContainsString('name="reroute_extra_costs_amount"', $body);
        $this->assertStringNotContainsString('name="reroute_extra_costs_currency"', $body);
    }

    public function testRailRemediesPostClearsExpenseTypeWhenSelfPurchaseWasNotApprovedAnd100MinuteRuleBlocksFallback(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.compute' => [
                'delayMinEU' => 60,
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->post('/flow/remedies', [
            'remedyChoice' => 'reroute_soonest',
            'offer_provided' => 'no',
            'self_purchased_new_ticket' => 'yes',
            'self_purchase_reason' => 'no_offer',
            'self_purchase_approved_by_operator' => 'no',
            'reroute_info_within_100min' => 'yes',
            'reroute_extra_costs' => 'yes',
            'reroute_extra_costs_type' => 'new_ticket',
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('no', 'flow.form.reroute_extra_costs');
        $this->assertSessionNotHasKey('flow.form.reroute_extra_costs_type');
    }

    public function testRailAssistanceShowsGuidanceCapsCard(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art20' => '1',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'price_hints' => [
                    'meals' => ['min' => 12, 'max' => 30, 'currency' => 'EUR'],
                    'hotelPerNight' => ['min' => 80, 'max' => 140, 'currency' => 'EUR'],
                    'taxi' => ['min' => 20, 'max' => 60, 'currency' => 'EUR'],
                ],
            ],
            'flow.incident' => ['main' => 'delay'],
        ]);

        $this->get('/flow/assistance');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Live rail-estimat', $body);
        $this->assertStringNotContainsString('Rail-kontekstpanel', $body);
        $this->assertStringContainsString('Vejledende rail-niveauer (ikke faste juridiske caps)', $body);
        $this->assertStringContainsString('Lokal transport / taxi:', $body);
    }

    public function testRailCompensationShowsLiveEstimatePanel(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art19' => '1',
                'travel_state' => 'completed',
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
            ],
            'flow.form' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'incident_main' => 'delay',
                'rail_price_input_mode' => 'exact',
                'price_known' => 'yes',
                'price' => '200.00',
                'price_currency' => 'DKK',
                'delayAtFinalMinutes' => '90',
            ],
            'flow.meta' => [
                'transport_mode' => 'rail',
                'gating_mode' => 'rail',
                'rail_incident_seed' => [
                    'gate_art19' => true,
                    'arrival_delay_minutes' => 90,
                ],
            ],
            'flow.incident' => [
                'main' => 'delay',
            ],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Live rail-estimat', $body);
        $this->assertStringContainsString('50.00 DKK', $body);
        $this->assertStringContainsString('Foreloebigt Art. 19-estimat ud fra billetpris og ankomstforsinkelse.', $body);
    }

    public function testCompletedAirAssistanceUsesLiteFrontendWithoutExpenseFieldsOrPmrPanel(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art20' => '1',
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                'travel_state' => 'completed',
            ],
            'flow.form' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'meal_offered' => 'no',
                'hotel_offered' => 'no',
                'air_next_day_departure' => 'yes',
            ],
            'flow.meta' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                '_multimodal' => [
                    'transport_mode' => 'air',
                    'air_rights' => [
                        'gate_air_art11_priority_assistance' => true,
                    ],
                ],
            ],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->get('/flow/assistance');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('name="meal_offered"', $body);
        $this->assertStringContainsString('name="hotel_offered"', $body);
        $this->assertStringNotContainsString('name="meal_self_paid_amount_items[]"', $body);
        $this->assertStringNotContainsString('name="hotel_self_paid_amount_items[]"', $body);
        $this->assertStringNotContainsString('name="assistance_pmr_priority_applied"', $body);
    }

    public function testCompletedAirDowngradeBackLinkPointsToAssistance(): void
    {
        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'step8_done' => '1',
                'gate_art20' => '1',
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                'travel_state' => 'completed',
            ],
            'flow.form' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
            ],
            'flow.meta' => [
                'transport_mode' => 'air',
                'gating_mode' => 'air',
                'entry_variant' => 'air_short',
                '_multimodal' => [
                    'transport_mode' => 'air',
                ],
            ],
        ]);

        $this->get('/flow/downgrade');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('href="/flow/assistance"', $body);
    }

    public function testFerryCompensationContainsArrivalDelayInputs(): void
    {
        $this->session([
            'flow.flags' => ['step5_done' => '1', 'transport_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => ['transport_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry'],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Kompensationsudregning', $body);
        $this->assertStringContainsString('name="arrival_delay_minutes"', $body);
        $this->assertStringContainsString('name="scheduled_journey_duration_minutes"', $body);
    }

    public function testFerryCompensationShowsTicketBasedAmountAndSharedExpenseOverview(): void
    {
        $this->session([
            'flow.flags' => ['step5_done' => '1', 'transport_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'service_type' => 'passenger_service',
                'departure_port_in_eu' => 'yes',
                'departure_from_terminal' => 'yes',
                'arrival_delay_minutes' => '130',
                'scheduled_journey_duration_minutes' => '300',
                'meal_self_paid_amount' => '25.00',
                'meal_self_paid_currency' => 'DKK',
                'hotel_self_paid_amount' => '100.00',
                'hotel_self_paid_currency' => 'DKK',
                'reroute_extra_costs' => 'yes',
                'reroute_extra_costs_amount' => '75.00',
                'reroute_extra_costs_currency' => 'DKK',
                'return_to_origin_expense' => 'yes',
                'return_to_origin_amount' => '50.00',
                'return_to_origin_currency' => 'DKK',
                'remedyChoice' => 'reroute_soonest',
            ],
            'flow.meta' => ['transport_mode' => 'ferry'],
            'flow.journey' => [
                'ticketPrice' => ['value' => '200 DKK', 'currency' => 'DKK'],
                'country' => ['value' => 'DK'],
            ],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Art. 19 beregnes paa baggrund af den faktisk betalte billetpris fra TRIN 2.', $body);
        $this->assertStringContainsString('Art. 18 - Tilbagebetaling / ombooking', $body);
        $this->assertStringContainsString('Art. 17 - Assistance', $body);
        $this->assertStringContainsString('Art. 19 - Kompensation', $body);
        $this->assertStringContainsString('Billetpris fra TRIN 2: <strong>200.00 DKK</strong>', $body);
        $this->assertStringContainsString('Kompensation: 50.00 DKK - 25%', $body);
        $this->assertStringContainsString('Samlede assistanceudgifter: 125.00 DKK', $body);
        $this->assertStringContainsString('Ekstra omkostninger ved ombooking: 75.00 DKK', $body);
    }

    public function testFerryCompensationUsesHalfFareForReturnTicket(): void
    {
        $this->session([
            'flow.flags' => ['step5_done' => '1', 'transport_mode' => 'ferry', 'travel_state' => 'ongoing'],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'trip_type' => 'return',
                'service_type' => 'passenger_service',
                'departure_port_in_eu' => 'yes',
                'departure_from_terminal' => 'yes',
                'arrival_delay_minutes' => '130',
                'scheduled_journey_duration_minutes' => '300',
            ],
            'flow.meta' => ['transport_mode' => 'ferry'],
            'flow.journey' => [
                'ticketPrice' => ['value' => '798.00 DKK', 'currency' => 'DKK'],
                'country' => ['value' => 'DK'],
            ],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->get('/flow/compensation');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('Kompensation: 99.75 DKK - 25% - Art.19(3) 1/2 fare (return, no split prices)', $body);
    }

    public function testFerryRemediesRedirectsToCompensationWhenAssistanceNotGated(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'ferry',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => ['transport_mode' => 'ferry'],
            'flow.meta' => ['transport_mode' => 'ferry'],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->post('/flow/remedies', [
            'remedyChoice' => 'refund_return',
            'return_to_origin_expense' => 'no',
        ]);

        $this->assertResponseCode(302);
        $this->assertStringContainsString('/flow/compensation', $this->_response->getHeaderLine('Location'));
    }

    public function testFerryRemediesClearsHiddenReturnAliasFieldsWhenReturnExpenseDisabled(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $this->session([
            'flow.flags' => [
                'step5_done' => '1',
                'gate_art18' => '1',
                'transport_mode' => 'ferry',
                'travel_state' => 'ongoing',
            ],
            'flow.form' => [
                'transport_mode' => 'ferry',
                'ferry_return_to_departure_port_expense' => 'yes',
                'ferry_return_to_departure_port_amount' => '123.00',
                'ferry_return_to_departure_port_currency' => 'DKK',
            ],
            'flow.meta' => ['transport_mode' => 'ferry'],
            'flow.incident' => ['main' => 'cancellation'],
        ]);

        $this->post('/flow/remedies', [
            'remedyChoice' => 'reroute_soonest',
            'return_to_origin_expense' => 'no',
            'ferry_return_to_departure_port_expense' => 'yes',
            'ferry_return_to_departure_port_amount' => '123.00',
            'ferry_return_to_departure_port_currency' => 'DKK',
        ]);

        $this->assertResponseCode(302);
        $this->assertSession('no', 'flow.form.return_to_origin_expense');
        $this->assertSession('no', 'flow.form.ferry_return_to_departure_port_expense');
        $this->assertSession('', 'flow.form.ferry_return_to_departure_port_amount');
        $this->assertSession('', 'flow.form.ferry_return_to_departure_port_currency');
    }

    public function testAssistanceHotelSelfPaidFieldsVisibleWhenHotelNotOffered(): void
    {
        // Seed session so Art. 20 is active (cancellation) and hotel_offered = no
        $this->session([
            'flow.flags' => ['step5_done' => '1', 'gate_art20' => '1', 'travel_state' => 'ongoing'],
            'flow.incident' => ['main' => 'cancellation'],
            'flow.form' => [
                'hotel_offered' => 'no',
                'art20_expected_delay_60' => 'yes',
            ],
        ]);

        $this->get('/flow/assistance');
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        // Self-paid hotel fields should be present in the HTML (gated client-side via data-show-if)
        $this->assertStringContainsString('name="hotel_self_paid_amount_items[]"', $body);
        $this->assertStringContainsString('name="hotel_self_paid_currency"', $body);
        $this->assertStringContainsString('name="hotel_self_paid_nights_items[]"', $body);
    }
}
