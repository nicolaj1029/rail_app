<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\PassengerDataService;

class ShadowJourneysController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * GET /api/shadow/journeys?device_id=...
     */
    public function index()
    {
        $deviceId = trim((string)($this->request->getQuery('device_id') ?? ''));
        $journeys = (new PassengerDataService())->listJourneys($deviceId);

        $this->set([
            'success' => true,
            'data' => ['journeys' => $journeys],
        ]);
    }

    /**
     * POST /api/shadow/journeys/{id}/confirm
     */
    public function confirm($id)
    {
        // TODO: Mark journey confirmed and create a case; return case_id
        $caseId = random_int(1000, 9999);

        $this->set([
            'success' => true,
            'data' => [
                'journey_id' => $id,
                'case_id' => $caseId,
            ],
        ]);
    }

    /**
     * POST /api/shadow/journeys/{id}/submit
     * Body: { form: {...}, receipts: [...] }
     * Persists to tmp/shadow_cases for inspection.
     */
    public function submit($id)
    {
        $journeyId = (string)$id;
        $payload = (array)$this->request->getData();
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $safeJourneyId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $journeyId) ?: 'journey';
        $storedPayload = [
            'journey_id' => $journeyId,
            'submitted_at' => gmdate('c'),
            'payload' => $payload,
        ];
        $file = $dir . DS . 'case_' . $safeJourneyId . '_' . time() . '.json';
        @file_put_contents($file, json_encode($storedPayload, JSON_PRETTY_PRINT));

        $this->set([
            'success' => true,
            'data' => [
                'journey_id' => $journeyId,
                'stored' => basename($file),
            ],
        ]);
    }
}
