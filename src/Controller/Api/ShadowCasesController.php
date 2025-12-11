<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

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
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        $cases = [];
        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
            foreach ($files as $f) {
                $cases[] = [
                    'file' => basename($f),
                    'modified' => date('c', filemtime($f)),
                    'size' => filesize($f),
                ];
            }
        }
        $this->set([
            'success' => true,
            'data' => [
                'cases' => $cases,
            ],
        ]);
    }
}
