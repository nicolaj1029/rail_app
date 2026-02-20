<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\RegulationIndex;

/**
 * Local regulation search/quote endpoints (RAG helper).
 *
 * - GET /api/regulation/search?q=...&limit=8
 * - GET /api/regulation/quote?id=art18_c1
 *
 * The index file is created offline from the PDF:
 *   scripts/regulations/index_32021r0782_da.py
 */
final class RegulationController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function search(): void
    {
        $q = (string)($this->request->getQuery('q') ?? '');
        $limit = (int)($this->request->getQuery('limit') ?? 8);
        if ($limit < 1) { $limit = 1; }
        if ($limit > 20) { $limit = 20; }

        $idx = new RegulationIndex();
        $hits = $idx->search($q, $limit);
        $this->set(['query' => $q, 'hits' => $hits]);
        $this->viewBuilder()->setOption('serialize', ['query','hits']);
    }

    public function quote(): void
    {
        $id = (string)($this->request->getQuery('id') ?? '');
        $idx = new RegulationIndex();
        $q = $idx->quote($id);
        $this->set(['id' => $id, 'quote' => $q]);
        $this->viewBuilder()->setOption('serialize', ['id','quote']);
    }
}

