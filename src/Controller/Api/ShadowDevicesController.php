<?php
namespace App\Controller\Api;

use App\Controller\AppController;

class ShadowDevicesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        // Allow POST for app calls; GET/OPTIONS permitted for simple connectivity tests.
        $this->request->allowMethod(['post', 'get', 'options']);
    }

    /**
     * POST /api/shadow/devices/register
     */
    public function register()
    {
        $data = (array)$this->request->getData();
        $platform = (string)($data['platform'] ?? 'unknown');
        $pushToken = (string)($data['push_token'] ?? '');

        // TODO: Persist device and generate stable device_id
        $deviceId = bin2hex(random_bytes(16));

        $this->set([
            'success' => true,
            'data' => [
                'device_id' => $deviceId,
                'platform' => $platform,
                'push_token' => $pushToken,
            ],
        ]);
    }
}
