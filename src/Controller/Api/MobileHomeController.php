<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\StationGeocoder;

final class MobileHomeController extends AppController
{
    private const JOURNEY_GAP_SECONDS = 900;
    private ?StationGeocoder $stationGeocoder = null;

    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setOption('serialize', true);
        $this->viewBuilder()->setClassName('Json');
        $this->request->allowMethod(['get']);
    }

    public function index(): void
    {
        $deviceId = trim((string)($this->request->getQuery('device_id') ?? ''));
        $journeys = $deviceId !== '' ? $this->loadJourneys($deviceId) : [];
        $cases = $this->loadCases();

        $readyJourneys = array_values(array_filter($journeys, fn(array $journey): bool => in_array($this->statusOf($journey), ['review', 'ready', 'ended'], true)));
        $activeJourneys = array_values(array_filter($journeys, fn(array $journey): bool => in_array($this->statusOf($journey), ['active', 'in_progress', 'detected'], true)));

        $nextActions = [];
        if ($readyJourneys !== []) {
            $nextActions[] = [
                'kind' => 'review_journey',
                'title' => 'Review næste rejse',
                'subtitle' => (string)($readyJourneys[0]['route_label'] ?? 'Rejse klar til review'),
                'journey_id' => (string)($readyJourneys[0]['id'] ?? ''),
            ];
        }
        if ($cases !== []) {
            $nextActions[] = [
                'kind' => 'open_claims',
                'title' => 'Se indsendte sager',
                'subtitle' => count($cases) . ' sager ligger i backend',
                'count' => count($cases),
            ];
        }
        if ($activeJourneys !== []) {
            $nextActions[] = [
                'kind' => 'continue_live',
                'title' => 'Fortsæt aktiv rejse',
                'subtitle' => (string)($activeJourneys[0]['route_label'] ?? 'Aktiv rejse'),
                'journey_id' => (string)($activeJourneys[0]['id'] ?? ''),
            ];
        }

        $this->set([
            'success' => true,
            'data' => [
                'summary' => [
                    'ready_count' => count($readyJourneys),
                    'active_count' => count($activeJourneys),
                    'submitted_count' => count($cases),
                    'device_id' => $deviceId,
                ],
                'next_actions' => $nextActions,
            ],
        ]);
    }

    /** @return list<array<string,mixed>> */
    private function loadJourneys(string $deviceId): array
    {
        $file = ROOT . DS . 'tmp' . DS . 'shadow_pings' . DS . preg_replace('/[^a-zA-Z0-9_-]/', '_', $deviceId) . '.jsonl';
        if (!is_file($file)) {
            return [];
        }

        $rows = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $pings = [];
        foreach ($rows as $line) {
            $obj = json_decode($line, true);
            if (!is_array($obj)) {
                continue;
            }
            $ping = $obj['ping'] ?? null;
            if (!is_array($ping)) {
                continue;
            }
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

        usort($pings, fn(array $a, array $b): int => strcmp((string)$a['t'], (string)$b['t']));
        $journeys = [];
        $current = null;
        foreach ($pings as $ping) {
            $time = strtotime((string)$ping['t']);
            if ($current === null) {
                $current = [
                    'id' => 'j-' . dechex($time),
                    'start' => gmdate('c', $time),
                    'end' => gmdate('c', $time),
                    'points' => [$ping],
                ];
                continue;
            }
            $prev = strtotime((string)$current['end']);
            if (($time - $prev) > self::JOURNEY_GAP_SECONDS) {
                $journeys[] = $this->summarizeJourney($current, false);
                $current = [
                    'id' => 'j-' . dechex($time),
                    'start' => gmdate('c', $time),
                    'end' => gmdate('c', $time),
                    'points' => [$ping],
                ];
                continue;
            }
            $current['end'] = gmdate('c', $time);
            $current['points'][] = $ping;
        }

        if ($current !== null) {
            $journeys[] = $this->summarizeJourney(
                $current,
                (time() - strtotime((string)$current['end'])) <= self::JOURNEY_GAP_SECONDS,
            );
        }

        return $journeys;
    }

    /** @return list<array<string,mixed>> */
    private function loadCases(): array
    {
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $cases = [];
        foreach ($files as $file) {
            $cases[] = [
                'file' => basename($file),
                'modified' => date('c', filemtime($file)),
            ];
        }
        usort($cases, fn(array $a, array $b): int => strcmp((string)($b['modified'] ?? ''), (string)($a['modified'] ?? '')));

        return $cases;
    }

    /** @param array{id:string,start:string,end:string,points:list<array<string,mixed>>} $journey */
    private function summarizeJourney(array $journey, bool $active): array
    {
        $from = $journey['points'][0] ?? [];
        $to = $journey['points'][count($journey['points']) - 1] ?? [];
        $routeLabel = $this->formatPoint($from) . ' -> ' . $this->formatPoint($to);

        return [
            'id' => $journey['id'],
            'route_label' => trim($routeLabel, ' ->'),
            'status' => $active ? 'active' : 'review',
        ];
    }

    private function formatPoint(array $point): string
    {
        $lat = isset($point['lat']) ? (float)$point['lat'] : null;
        $lon = isset($point['lon']) ? (float)$point['lon'] : null;
        if ($lat !== null && $lon !== null) {
            $nearest = $this->stationGeocoder()->nearest($lat, $lon);
            if ($nearest !== null) {
                return (string)$nearest['name'];
            }
        }

        $latLabel = $lat !== null ? number_format($lat, 4, '.', '') : '';
        $lonLabel = $lon !== null ? number_format($lon, 4, '.', '') : '';

        return trim($latLabel . ', ' . $lonLabel, ', ');
    }

    private function statusOf(array $item): string
    {
        return strtolower(trim((string)($item['status'] ?? '')));
    }

    private function stationGeocoder(): StationGeocoder
    {
        return $this->stationGeocoder ??= new StationGeocoder();
    }
}
