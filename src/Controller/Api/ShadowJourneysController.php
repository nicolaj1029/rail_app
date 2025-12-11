<?php
namespace App\Controller\Api;

use App\Controller\AppController;

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
        $deviceId = (string)($this->request->getQuery('device_id') ?? '');

        $journeys = [];
        if ($deviceId !== '') {
            $file = ROOT . DS . 'tmp' . DS . 'shadow_pings' . DS . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.jsonl';
            if (is_file($file)) {
                $rows = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $pings = [];
                foreach ($rows as $line) {
                    $obj = json_decode($line, true);
                    if (!is_array($obj)) continue;
                    $ping = $obj['ping'] ?? null;
                    if (!is_array($ping)) continue;
                    $ts = $ping['t'] ?? $ping['ts'] ?? null;
                    $lat = $ping['lat'] ?? null;
                    $lon = $ping['lon'] ?? null;
                    if ($ts && $lat !== null && $lon !== null) {
                        $pings[] = [
                            't' => $ts,
                            'lat' => (float)$lat,
                            'lon' => (float)$lon,
                        ];
                    }
                }
                usort($pings, fn($a, $b) => strcmp($a['t'], $b['t']));
                $gapSeconds = 900; // 15 min
                $current = null;
                foreach ($pings as $p) {
                    $t = strtotime($p['t']);
                    if ($current === null) {
                        $current = [
                            'id' => 'j-' . dechex($t),
                            'start' => gmdate('c', $t),
                            'end' => gmdate('c', $t),
                            'points' => [$p],
                        ];
                        continue;
                    }
                    $prevT = strtotime($current['end']);
                    if (($t - $prevT) > $gapSeconds) {
                        $journeys[] = $this->summarizeJourney($current);
                        $current = [
                            'id' => 'j-' . dechex($t),
                            'start' => gmdate('c', $t),
                            'end' => gmdate('c', $t),
                            'points' => [$p],
                        ];
                    } else {
                        $current['end'] = gmdate('c', $t);
                        $current['points'][] = $p;
                    }
                }
                if ($current !== null) {
                    $journeys[] = $this->summarizeJourney($current);
                }
            }
        }

        $this->set([
            'success' => true,
            'data' => ['journeys' => $journeys],
        ]);
    }

    private function summarizeJourney(array $j): array
    {
        $points = $j['points'] ?? [];
        $from = $points[0] ?? null;
        $to = $points[count($points)-1] ?? null;
        return [
            'id' => $j['id'],
            'start' => $j['start'],
            'end' => $j['end'],
            'from' => $from,
            'to' => $to,
            'count' => count($points),
        ];
    }

    /**
     * POST /api/shadow/journeys/{id}/confirm
     */
    public function confirm($id)
    {
        $id = (int)$id;
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
        $id = (int)$id;
        $payload = (array)$this->request->getData();
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $file = $dir . DS . 'case_' . $id . '_' . time() . '.json';
        @file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT));

        $this->set([
            'success' => true,
            'data' => [
                'journey_id' => $id,
                'stored' => basename($file),
            ],
        ]);
    }
}
