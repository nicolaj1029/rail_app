<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\FixtureRepository;

class FixturesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function index(): void
    {
        $repo = new FixtureRepository();
        $this->set(['fixtures' => $repo->listMeta()]);
        $this->viewBuilder()->setOption('serialize', ['fixtures']);
    }
}
