<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\FlightSearchService;
use Cake\Http\Exception\BadRequestException;

class AirFlightsController extends AppController
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

        $departure = strtoupper(trim((string)($this->request->getQuery('departure') ?? '')));
        $arrival = strtoupper(trim((string)($this->request->getQuery('arrival') ?? '')));
        $date = trim((string)($this->request->getQuery('date') ?? ''));

        if ($departure === '' || $arrival === '' || $date === '') {
            throw new BadRequestException('departure, arrival and date are required');
        }

        $service = new FlightSearchService();
        $items = $service->search($departure, $arrival, $date, [
            'depTime' => (string)($this->request->getQuery('depTime') ?? ''),
            'arrTime' => (string)($this->request->getQuery('arrTime') ?? ''),
            'flightNumber' => (string)($this->request->getQuery('flightNumber') ?? ''),
            'marketingCarrier' => (string)($this->request->getQuery('carrier') ?? ''),
            'operatingCarrier' => (string)($this->request->getQuery('operatingCarrier') ?? ''),
            'departureLabel' => (string)($this->request->getQuery('departureLabel') ?? ''),
            'arrivalLabel' => (string)($this->request->getQuery('arrivalLabel') ?? ''),
        ]);

        $this->set([
            'success' => true,
            'items' => $items,
            'manual_fallback' => $items === [],
        ]);
    }
}
