<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\PassengerDataService;

class ShadowCasesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get']);
    }

    /**
     * GET /api/shadow/cases
     * Lists stored case submissions from tmp/shadow_cases (debug/QA).
     */
    public function index()
    {
        $cases = (new PassengerDataService())->listCases();
        $this->set([
            'success' => true,
            'data' => [
                'cases' => $cases,
            ],
        ]);
    }
}
