<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\FlightSearchService;

class AirFlightsController extends AppController
{
    /** Configure JSON serialization for AIR endpoints. */
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
    }

    /** Return a normalized, failure-aware flight lookup response. */
    public function search()
    {
        $this->request->allowMethod(['get']);

        $departure = strtoupper(trim((string)($this->request->getQuery('departure') ?? '')));
        $arrival = strtoupper(trim((string)($this->request->getQuery('arrival') ?? '')));
        $date = trim((string)($this->request->getQuery('date') ?? ''));

        $service = new FlightSearchService();
        $result = $service->searchWithMeta($departure, $arrival, $date, [
            'depTime' => (string)($this->request->getQuery('depTime') ?? ''),
            'arrTime' => (string)($this->request->getQuery('arrTime') ?? ''),
            'flightNumber' => (string)($this->request->getQuery('flightNumber') ?? ''),
            'marketingCarrier' => (string)($this->request->getQuery('carrier') ?? ''),
            'operatingCarrier' => (string)($this->request->getQuery('operatingCarrier') ?? ''),
            'departureLabel' => (string)($this->request->getQuery('departureLabel') ?? ''),
            'arrivalLabel' => (string)($this->request->getQuery('arrivalLabel') ?? ''),
            'requestId' => (string)($this->request->getHeaderLine('X-Request-ID') ?: ''),
        ]);

        if ($result['status'] === 'invalid_request') {
            $this->setResponse($this->response->withStatus(422));
        }
        $this->setResponse($this->response
            ->withHeader('X-Request-ID', $result['request_id'])
            ->withHeader('Cache-Control', 'no-store'));

        $this->set([
            'success' => in_array($result['status'], ['success', 'success_fallback'], true),
            'lookup_status' => $result['status'],
            'message' => $result['user_message'],
            'items' => $result['items'],
            'manual_fallback' => $result['manual_fallback'],
            'cache' => $result['cache'],
            'timing' => $result['timing'],
            'provider_attempts' => $result['provider_attempts'],
            'request_id' => $result['request_id'],
        ]);
        $this->viewBuilder()->setOption('serialize', [
            'success', 'lookup_status', 'message', 'items', 'manual_fallback',
            'cache', 'timing', 'provider_attempts', 'request_id',
        ]);
    }

    /** Report application/config health without making a billable provider call. */
    public function health()
    {
        $this->request->allowMethod(['get']);
        $health = (new FlightSearchService())->health();
        $this->set([
            'success' => true,
            'status' => 'ok',
            'air' => $health,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'status', 'air']);
    }
}
