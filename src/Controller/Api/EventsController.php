<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class EventsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get', 'post', 'options']);
    }

    /**
     * POST /api/events
     * Body: { device_id, type, payload }
     */
    public function add()
    {
        $data = (array)$this->request->getData();
        $deviceId = (string)($data['device_id'] ?? '');
        $type = (string)($data['type'] ?? '');
        $payload = $data['payload'] ?? null;

        // Minimal persistence: append to tmp events file
        if ($deviceId !== '' && $type !== '') {
            $dir = ROOT . DS . 'tmp' . DS . 'shadow_events';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $file = $dir . DS . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.jsonl';
            $row = [
                'device_id' => $deviceId,
                'type' => $type,
                'payload' => $payload,
                'received_at' => gmdate('c'),
            ];
            @file_put_contents($file, json_encode($row) . "\n", FILE_APPEND);
        }

        $this->set([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'type' => $type,
            ],
        ]);
    }

    /**
     * GET /api/events?device_id=...&limit=...
     * Returns events from tmp/shadow_events device log (if any).
     */
    public function index()
    {
        $deviceId = (string)($this->request->getQuery('device_id') ?? '');
        $limit = (int)($this->request->getQuery('limit') ?? 50);
        $events = [];
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_events';
        if ($deviceId !== '') {
            $file = $dir . DS . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.jsonl';
            if (is_file($file)) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $lines = array_slice(array_reverse($lines), 0, $limit);
                foreach ($lines as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        $events[] = $decoded;
                    }
                }
            }
        }
        $this->set([
            'success' => true,
            'data' => [
                'events' => $events,
            ],
        ]);
    }
}
