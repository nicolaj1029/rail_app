<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AppController;
use App\Service\AdminChatService;
use Cake\Http\Response;

final class ChatController extends AppController
{
    public function index(): void
    {
        $payload = (new AdminChatService())->bootstrap($this->request->getSession());
        $this->set('chatPayload', $payload);
    }

    public function message(): Response
    {
        $this->request->allowMethod(['post']);

        $input = trim((string)($this->request->getData('message') ?? ''));
        $payload = (new AdminChatService())->handleMessage($this->request->getSession(), $input);

        return $this->json($payload);
    }

    public function reset(): Response
    {
        $this->request->allowMethod(['post']);

        $payload = (new AdminChatService())->reset($this->request->getSession());

        return $this->json($payload);
    }

    public function focus(): Response
    {
        $this->request->allowMethod(['post']);

        $key = trim((string)($this->request->getData('key') ?? ''));
        $payload = (new AdminChatService())->focusQuestion($this->request->getSession(), $key);

        return $this->json($payload);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(array $payload): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ));
    }
}
