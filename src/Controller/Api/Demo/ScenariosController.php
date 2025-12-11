<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\FixtureRepository;
use App\Service\ScenarioRunner;

class ScenariosController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * GET /api/demo/scenarios
     * ?withEval=1 to run pipeline
     * ?id=<fixtureId> to filter one
     */
    public function index(): void
    {
        // Support both withEval=1 and eval=1 (alias)
        $withEval = (string)$this->request->getQuery('withEval') === '1'
            || (string)$this->request->getQuery('eval') === '1';
        $id = (string)$this->request->getQuery('id') ?: null;
        $compact = (string)$this->request->getQuery('compact') === '1';

        $repo = new FixtureRepository();
        $fixtures = $repo->getAll($id);
        $runner = new ScenarioRunner();

        $out = [];
        foreach ($fixtures as $fx) {
            $row = [
                'id' => $fx['id'],
                'version' => $fx['version'] ?? 1,
                'label' => $fx['label'] ?? $fx['id'],
                'tags' => $fx['tags'] ?? [],
                'expected' => $fx['expected'] ?? null,
            ];
            if ($withEval) {
                $eval = $runner->evaluateFixture($fx);
                $actual = $eval['actual'];
                if ($compact) {
                    // Keep only high-signal keys relevant for steps 1-6
                    $keep = [
                        'profile.scope','profile.articles','compensation','refund','claim.breakdown','claim.totals',
                        'art9.hooks','art12.hooks','art9.missing','art12.missing','wizard.step3_entitlements','wizard.step4_choices','wizard.step5_assistance',
                    ];
                    $filtered = [];
                    foreach ($keep as $k) {
                        $val = \Cake\Utility\Hash::get($actual, $k);
                        if ($val !== null) { \Cake\Utility\Hash::insert($filtered, $k, $val); }
                    }
                    $actual = $filtered + ['_compact' => true];
                }
                $row['actual'] = $actual;
                $row['match'] = $eval['match'];
                $row['diff'] = $eval['diff'];
            }
            $out[] = $row;
        }

        $this->set(['scenarios' => $out]);
        $this->viewBuilder()->setOption('serialize', ['scenarios']);
    }
}
