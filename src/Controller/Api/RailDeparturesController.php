<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\Rail\RailDepartureSearchService;
use Cake\Http\Exception\BadRequestException;

class RailDeparturesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
    }

    public function search()
    {
        $this->request->allowMethod(['get']);

        $fromStation = trim((string)($this->request->getQuery('from_station') ?? ''));
        $toStation = trim((string)($this->request->getQuery('to_station') ?? ''));
        $date = trim((string)($this->request->getQuery('date') ?? ''));
        if ($fromStation === '' || $toStation === '' || $date === '') {
            throw new BadRequestException('from_station, to_station and date are required');
        }

        $service = new RailDepartureSearchService();
        $items = $service->search([
            'from_station' => $fromStation,
            'to_station' => $toStation,
            'date' => $date,
            'time' => (string)($this->request->getQuery('time') ?? ''),
            'operator_hint' => (string)($this->request->getQuery('operator_hint') ?? ''),
            'train_number_hint' => (string)($this->request->getQuery('train_number_hint') ?? ''),
            'locale' => (string)($this->request->getQuery('locale') ?? 'da-DK'),
        ]);

        $this->set([
            'success' => true,
            'items' => $items,
            'manual_fallback' => $items === [],
        ]);
    }
}
