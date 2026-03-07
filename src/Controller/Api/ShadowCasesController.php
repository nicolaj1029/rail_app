<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class ShadowCasesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get']);
    }

    /**
     * GET /api/shadow/cases
     * Lists stored case submissions from tmp/shadow_cases (debug/QA).
     */
    public function index()
    {
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        $cases = [];
        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
            foreach ($files as $f) {
                $summary = $this->summarizeCaseFile($f);
                $cases[] = [
                    'file' => basename($f),
                    'modified' => date('c', filemtime($f)),
                    'size' => filesize($f),
                    ...$summary,
                ];
            }
        }
        usort($cases, fn(array $a, array $b): int => strcmp((string)($b['modified'] ?? ''), (string)($a['modified'] ?? '')));
        $this->set([
            'success' => true,
            'data' => [
                'cases' => $cases,
            ],
        ]);
    }

    private function summarizeCaseFile(string $file): array
    {
        $raw = @file_get_contents($file);
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [
                'journey_id' => '',
                'status' => 'submitted',
                'route_label' => '',
                'dep_station' => '',
                'arr_station' => '',
                'delay_minutes' => null,
                'ticket_type' => '',
            ];
        }

        $payload = isset($decoded['payload']) && is_array($decoded['payload']) ? $decoded['payload'] : $decoded;
        $tickets = isset($payload['tickets']) && is_array($payload['tickets']) ? $payload['tickets'] : [];
        $primaryTicket = isset($tickets[0]) && is_array($tickets[0]) ? $tickets[0] : [];
        $incident = isset($payload['incident']) && is_array($payload['incident']) ? $payload['incident'] : [];
        $legacyJourney = isset($payload['journey']) && is_array($payload['journey']) ? $payload['journey'] : [];
        $legacyEvent = isset($payload['event']) && is_array($payload['event']) ? $payload['event'] : [];

        $depStation = $this->normalizeLocationField((string)($primaryTicket['from'] ?? $legacyJourney['dep'] ?? ''));
        $arrStation = $this->normalizeLocationField((string)($primaryTicket['to'] ?? $legacyJourney['arr'] ?? ''));
        $routeLabel = trim($depStation . ' -> ' . $arrStation, ' ->');
        $delayMinutes = $incident['delay_confirmed_minutes'] ?? $incident['delay_expected_minutes'] ?? $legacyEvent['delay_minutes'] ?? null;

        return [
            'journey_id' => (string)($decoded['journey_id'] ?? ''),
            'status' => 'submitted',
            'route_label' => $routeLabel,
            'dep_station' => $depStation,
            'arr_station' => $arrStation,
            'delay_minutes' => is_numeric((string)$delayMinutes) ? (int)$delayMinutes : null,
            'ticket_type' => (string)($primaryTicket['ticketType'] ?? $legacyJourney['ticket_type'] ?? ''),
            'submitted_at' => (string)($decoded['submitted_at'] ?? ''),
        ];
    }

    private function normalizeLocationField(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $trimmed)) {
            return '';
        }

        return $trimmed;
    }
}
