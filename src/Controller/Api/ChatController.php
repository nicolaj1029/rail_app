<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\AdminChatService;
use Cake\Http\Response;

final class ChatController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get', 'post', 'options']);
    }

    public function bootstrap(): Response
    {
        $payload = (new AdminChatService())->bootstrap($this->request->getSession());

        return $this->json($this->normalizePayload($payload));
    }

    public function message(): Response
    {
        $this->request->allowMethod(['post']);

        $input = trim((string)($this->request->getData('message') ?? ''));
        $payload = (new AdminChatService())->handleMessage($this->request->getSession(), $input);

        return $this->json($this->normalizePayload($payload));
    }

    public function reset(): Response
    {
        $this->request->allowMethod(['post']);

        $payload = (new AdminChatService())->reset($this->request->getSession());

        return $this->json($this->normalizePayload($payload));
    }

    public function context(): Response
    {
        $this->request->allowMethod(['post']);

        $context = $this->request->getData('context');
        if (!is_array($context)) {
            return $this->json([
                'ok' => false,
                'notice' => 'Ingen kontekst modtaget.',
            ]);
        }

        $payload = (new AdminChatService())->applyMobileContext($this->request->getSession(), $context);

        return $this->json($this->normalizePayload($payload));
    }

    public function upload(): Response
    {
        $this->request->allowMethod(['post']);

        $file = $this->request->getUploadedFile('ticket_upload');
        if ($file === null) {
            return $this->json([
                'ok' => false,
                'notice' => 'Ingen fil modtaget.',
            ]);
        }

        $payload = (new AdminChatService())->handleUpload($this->request->getSession(), $file);

        return $this->json($this->normalizePayload($payload));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePayload(array $payload): array
    {
        return $this->rewriteStrings($payload);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function rewriteStrings(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->rewriteStrings($item);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $replacements = [
            'Admin chat er aktiv.' => 'Chat er aktiv.',
            'admin-chatten' => 'chatten',
            'admin-chat' => 'chat',
            'Admin chat' => 'Chat',
            'Upload behandlet i admin-chatten.' => 'Upload behandlet i chatten.',
            'Brug preview-actions eller nulstil chatten.' => 'Brug forslagene eller nulstil chatten.',
            'Brug preview-actions eller aabn wizard-trinnene direkte.' => 'Brug forslagene eller aabn de relevante trin direkte.',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $value);
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
