<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\PassengerDataService;

final class MobileHomeController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get']);
    }

    public function index(): void
    {
        $deviceId = trim((string)($this->request->getQuery('device_id') ?? ''));
        $summary = (new PassengerDataService())->buildHomeSummary($deviceId);

        $this->set([
            'success' => true,
            'data' => $summary,
        ]);
    }
}
