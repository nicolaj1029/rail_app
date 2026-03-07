<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AdminChatService;
use Cake\Http\Session;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

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

    public function testUploadUpdatesFlowWithExtractedFields(): void
    {
        $this->service->handleMessage($this->session, 'ongoing');
        $this->service->handleMessage($this->session, 'ticket');

        $tmp = tempnam(sys_get_temp_dir(), 'chat_ticket_');
        $path = $tmp . '.txt';
        @rename($tmp, $path);
        file_put_contents($path, "From København\nTo Roskilde\nOperator DSB\nDate 2026-03-07\nDeparture 08:15\nArrival 08:45\nPrice 129 DKK");

        $upload = new UploadedFile($path, filesize($path) ?: 0, UPLOAD_ERR_OK, 'ticket.txt', 'text/plain');
        $payload = $this->service->handleUpload($this->session, $upload);

        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame('ticket.txt', $payload['summary']['uploaded_file'] ?? null);
        $this->assertSame('DSB', $payload['summary']['operator'] ?? null);
        $this->assertNotSame('', $payload['summary']['route'] ?? '');
        $this->assertNotSame('', $payload['summary']['extraction_provider'] ?? '');

        $stored = (string)$this->session->read('flow.form._ticketFilename');
        if ($stored !== '') {
            $fullPath = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $stored;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }
}
