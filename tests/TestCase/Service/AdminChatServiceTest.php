<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AdminChatService;
use Cake\Http\Session;
use Cake\TestSuite\TestCase;

final class AdminChatServiceTest extends TestCase
{
    private AdminChatService $service;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminChatService();
        $this->session = Session::create([
            'defaults' => 'cake',
            'cookie' => 'admin_chat_test',
        ]);
        $this->service->reset($this->session);
    }

    protected function tearDown(): void
    {
        $this->service->reset($this->session);
        parent::tearDown();
    }

    public function testBaseSequenceStaysInOrder(): void
    {
        $payload = $this->service->bootstrap($this->session);
        $this->assertSame('travel_state', $payload['question']['key'] ?? null);
        $this->assertArrayHasKey('explanation', $payload);

        $payload = $this->service->handleMessage($this->session, 'ongoing');
        $this->assertSame('ticket_upload_mode', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'ticket');
        $this->assertSame('operator', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'DSB');
        $this->assertSame('operator_country', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'DK');
        $this->assertSame('confirm_step2', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'ja');
        $this->assertSame('dep_station', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'København');
        $this->assertSame('arr_station', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'Roskilde');
        $this->assertSame('incident_main', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'delay');
        $this->assertSame('delay_minutes', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, '65');
        $this->assertNotSame('ticket_upload_mode', $payload['question']['key'] ?? null);
        $this->assertNotSame('confirm_step2', $payload['question']['key'] ?? null);
    }

    public function testSeasonPassAsksProductBeforeStep2Confirmation(): void
    {
        $payload = $this->service->bootstrap($this->session);
        $this->assertSame('travel_state', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'ongoing');
        $this->assertSame('ticket_upload_mode', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'seasonpass');
        $this->assertSame('operator', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'DSB');
        $this->assertSame('operator_country', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'DK');
        $this->assertSame('operator_product', $payload['question']['key'] ?? null);

        $payload = $this->service->handleMessage($this->session, 'Pendlerkort');
        $this->assertSame('confirm_step2', $payload['question']['key'] ?? null);
    }

    public function testPayloadAlwaysExposesExplanationBlock(): void
    {
        $payload = $this->service->bootstrap($this->session);

        $this->assertArrayHasKey('explanation', $payload);
        $this->assertSame('groq', $payload['explanation']['provider'] ?? null);
        $this->assertContains($payload['explanation']['status'] ?? null, ['disabled', 'idle', 'ok', 'cached', 'error']);
    }
}
