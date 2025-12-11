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
        $this->configRequest([
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        // Enable CSRF/Security tokens for POST requests in tests
        $this->enableCsrfToken();
        $this->enableSecurityToken();

        $data = [
            // Minimal fields that may influence hooks rendering
            'remedyChoice' => 'reroute_soonest',
            'reroute_info_within_100min' => 'yes',
            'delayAtFinalMinutes' => '75',
            'compensationBand' => '60_119',
        ];

        $this->post('/flow/one?ajax_hooks=1', $data);

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

    public function testChoicesPostRedirectsToAssistance(): void
    {
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $data = [
            'remedyChoice' => 'refund_return',
            'refund_requested' => 'yes',
            // minimal set of fields; controller should still redirect regardless of completeness
        ];
        $this->post('/flow/choices', $data);
        $this->assertResponseCode(302);
        $this->assertRedirect(['controller' => 'Flow', 'action' => 'assistance']);
    }

    public function testBikeWasPresentAutoDefaultsToNoOnEntitlements(): void
    {
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

    public function testAssistanceHotelSelfPaidFieldsVisibleWhenHotelNotOffered(): void
    {
        // Seed session so Art. 20 is active (cancellation) and hotel_offered = no
        $this->session([
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
        $this->assertStringContainsString('name="hotel_self_paid_amount"', $body);
        $this->assertStringContainsString('name="hotel_self_paid_currency"', $body);
        $this->assertStringContainsString('name="hotel_self_paid_nights"', $body);
    }
}
