<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\TransportNodeSearchService;
use Cake\Http\Exception\BadRequestException;

class TransportNodesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * GET /api/transport-nodes/search?mode=ferry&q=...&country=DK&limit=10
     */
    public function search()
    {
        $this->request->allowMethod(['get']);

        $mode = strtolower(trim((string)($this->request->getQuery('mode') ?? '')));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            throw new BadRequestException('mode must be ferry, bus or air');
        }

        $q = trim((string)($this->request->getQuery('q') ?? ''));
        if ($q === '' || mb_strlen($q, 'UTF-8') < 2) {
            throw new BadRequestException('q must be at least 2 characters');
        }

        $country = trim((string)($this->request->getQuery('country') ?? ''));
        if ($country === '') {
            $country = null;
        }

        $limit = (int)($this->request->getQuery('limit') ?? 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $service = new TransportNodeSearchService();
        $nodes = $service->search($mode, $q, $country, $limit);

        $this->set([
            'success' => true,
            'data' => [
                'nodes' => $nodes,
            ],
        ]);
    }
}
