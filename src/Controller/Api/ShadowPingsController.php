<?php
namespace App\Controller\Api;

use App\Controller\AppController;

class ShadowPingsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        // Allow POST for app calls; OPTIONS for preflight.
        $this->request->allowMethod(['post', 'options']);
    }

    /**
     * POST /api/shadow/pings
     */
    public function add()
    {
        $data = (array)$this->request->getData();
        $deviceId = (string)($data['device_id'] ?? '');
        $pings = (array)($data['pings'] ?? []);

        $count = count($pings);

        // Minimal persistence: append to tmp file per device
        if ($deviceId !== '' && $count > 0) {
            $dir = ROOT . DS . 'tmp' . DS . 'shadow_pings';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $file = $dir . DS . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.jsonl';
            $fh = @fopen($file, 'ab');
            if ($fh) {
                foreach ($pings as $ping) {
                    $row = [
                        'device_id' => $deviceId,
                        'ping' => $ping,
                        'received_at' => gmdate('c'),
                    ];
                    fwrite($fh, json_encode($row) . "\n");
                }
                fclose($fh);
            }
        }

        $this->set([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'received' => $count,
            ],
        ]);
    }
}
