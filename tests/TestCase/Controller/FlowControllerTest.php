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
}
