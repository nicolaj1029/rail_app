<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\FerryDepartureSearchService;
use App\Service\FerryIncidentEvidenceResolver;
use App\Service\FerryOperationalEvidenceService;
use Cake\Http\Exception\BadRequestException;

class FerryDeparturesController extends AppController
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

        if ($date === '') {
            throw new BadRequestException('date is required');
        }

        $service = new FerryDepartureSearchService();
        $items = $service->search($departure, $arrival, $date, [
            'operator' => (string)($this->request->getQuery('operator') ?? ''),
            'departureLabel' => (string)($this->request->getQuery('departureLabel') ?? ''),
            'arrivalLabel' => (string)($this->request->getQuery('arrivalLabel') ?? ''),
            'depTime' => (string)($this->request->getQuery('depTime') ?? ''),
            'arrTime' => (string)($this->request->getQuery('arrTime') ?? ''),
        ]);

        $this->set([
            'success' => true,
            'items' => $items,
            'manual_fallback' => $items === [],
        ]);
    }

    public function suggestIncident()
    {
        $this->request->allowMethod(['post']);

        $data = (array)$this->request->getData();
        $selectedDeparture = (array)($data['selected_departure'] ?? []);
        $form = (array)($data['form'] ?? []);
        $meta = (array)($data['meta'] ?? []);
        $operationalEvidence = (array)($data['operational_evidence'] ?? []);

        if ($selectedDeparture === [] && $operationalEvidence === []) {
            throw new BadRequestException('selected_departure or operational_evidence is required');
        }

        if ($operationalEvidence === []) {
            $operationalEvidence = (new FerryOperationalEvidenceService())->evaluate($selectedDeparture, $form, $meta);
        }

        $suggestion = (new FerryIncidentEvidenceResolver())->suggest($operationalEvidence, $form, $meta);

        $this->set([
            'success' => true,
            'operational_evidence' => $operationalEvidence,
            'incident_suggestion' => $suggestion,
        ]);
    }
}
