<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class OperatorController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function trip(string $operatorCode): void
    {
        $this->set(['operator' => $operatorCode, 'segments' => []]);
        $this->viewBuilder()->setOption('serialize', ['operator','segments']);
    }
}
