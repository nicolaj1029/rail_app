<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\FixtureRepository;
use App\Service\ScenarioRunner;

class RunScenariosController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
        $this->viewBuilder()->setOption('jsonOptions', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/demo/run-scenarios
     * ?id=<fixtureId> to run one
     * ?limit=10 to cap number
     */
    public function index(): void
    {
        $id = (string)$this->request->getQuery('id') ?: null;
        $limit = (int)($this->request->getQuery('limit') ?? 0);

        $repo = new FixtureRepository();
        $fixtures = $repo->getAll($id);
        if ($limit > 0) {
            $fixtures = array_slice($fixtures, 0, $limit);
        }

        $runner = new ScenarioRunner();
        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($fixtures as $fx) {
            $eval = $runner->evaluateFixture($fx);
            $status = $eval['match'] ? 'passed' : 'failed';
            $status === 'passed' ? $passed++ : $failed++;
            $results[] = [
                'id' => $fx['id'],
                'label' => $fx['label'] ?? $fx['id'],
                'status' => $status,
                'diff' => $eval['diff'],
            ];
        }

        $summary = [
            'total' => count($fixtures),
            'passed' => $passed,
            'failed' => $failed,
        ];

        $this->set(compact('summary', 'results'));
        $this->viewBuilder()->setOption('serialize', ['summary', 'results']);
    }
}
