<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\SessionToFixtureMapper;

class DumpSessionController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
        $this->viewBuilder()->setOption('jsonOptions', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/demo/dump-session
     * ?asFixture=1 to emit a fixture skeleton (v2) from current flow session.
     */
    public function index(): void
    {
        $flow = (array)$this->request->getSession()->read('flow') ?: [];
        $asFixture = (string)$this->request->getQuery('asFixture') === '1';
        $enriched = (string)$this->request->getQuery('enriched') === '1';

        if ($asFixture) {
            $mapper = new SessionToFixtureMapper();
            if ($enriched) {
                $fixture = $mapper->mapSessionToFixtureEnriched($flow);
            } else {
                $fixture = $mapper->mapSessionToFixture($flow);
            }
            $this->set(['fixture' => $fixture]);
            $this->viewBuilder()->setOption('serialize', ['fixture']);
            return;
        }

        $this->set(['flow' => $flow]);
        $this->viewBuilder()->setOption('serialize', ['flow']);
    }
}
