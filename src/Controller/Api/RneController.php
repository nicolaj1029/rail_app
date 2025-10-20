<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class RneController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function trip(): void
    {
        $this->set(['segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['segments']);
    }
}
