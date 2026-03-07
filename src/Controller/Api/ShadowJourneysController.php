<?php
namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\StationGeocoder;

class ShadowJourneysController extends AppController
{
    private const JOURNEY_GAP_SECONDS = 900;
    private ?StationGeocoder $stationGeocoder = null;

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
                $gapSeconds = self::JOURNEY_GAP_SECONDS;
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
                        $journeys[] = $this->summarizeJourney($current, $deviceId, false);
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
                    $journeys[] = $this->summarizeJourney($current, $deviceId, (time() - strtotime($current['end'])) <= $gapSeconds);
                }
            }
        }

        $this->set([
            'success' => true,
            'data' => ['journeys' => $journeys],
        ]);
    }

    private function summarizeJourney(array $j, string $deviceId, bool $active = false): array
    {
        $points = $j['points'] ?? [];
        $from = $points[0] ?? null;
        $to = $points[count($points)-1] ?? null;
        $depLabel = $this->formatPointLabel($from);
        $arrLabel = $this->formatPointLabel($to);

        return [
            'id' => $j['id'],
            'device_id' => $deviceId,
            'start' => $j['start'],
            'end' => $j['end'],
            'dep_time' => $j['start'],
            'arr_time' => $j['end'],
            'dep_station' => $depLabel,
            'arr_station' => $arrLabel,
            'route_label' => trim($depLabel . ' -> ' . $arrLabel, ' ->'),
            'status' => $active ? 'active' : 'review',
            'delay_minutes' => null,
            'ticket_type' => '',
            'from' => $from,
            'to' => $to,
            'count' => count($points),
        ];
    }

    private function formatPointLabel(?array $point): string
    {
        if (!$point) {
            return '';
        }

        $nearest = $this->stationGeocoder()->nearest(
            (float)($point['lat'] ?? 0.0),
            (float)($point['lon'] ?? 0.0),
        );
        if ($nearest !== null) {
            return (string)$nearest['name'];
        }

        $lat = isset($point['lat']) ? number_format((float)$point['lat'], 4, '.', '') : null;
        $lon = isset($point['lon']) ? number_format((float)$point['lon'], 4, '.', '') : null;

        if ($lat === null || $lon === null) {
            return '';
        }

        return $lat . ', ' . $lon;
    }

    private function stationGeocoder(): StationGeocoder
    {
        return $this->stationGeocoder ??= new StationGeocoder();
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
