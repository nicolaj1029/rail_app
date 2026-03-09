<?php
declare(strict_types=1);

namespace App\Service;

final class PassengerDataService
{
    private const JOURNEY_GAP_SECONDS = 900;

    private ?StationGeocoder $stationGeocoder = null;

    /**
     * @return list<array<string,mixed>>
     */
    public function listJourneys(string $deviceId): array
    {
        $deviceId = trim($deviceId);
        if ($deviceId === '') {
            return [];
        }

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
                    't' => (string)$ts,
                    'lat' => (float)$lat,
                    'lon' => (float)$lon,
                ];
            }
        }

        usort($pings, static fn(array $a, array $b): int => strcmp((string)$a['t'], (string)$b['t']));

        $journeys = [];
        $current = null;
        foreach ($pings as $ping) {
            $time = strtotime((string)$ping['t']);
            if ($time === false) {
                continue;
            }
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
            if ($prev === false || ($time - $prev) > self::JOURNEY_GAP_SECONDS) {
                $journeys[] = $this->summarizeJourney($current, $deviceId, false);
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
            $end = strtotime((string)$current['end']);
            $isActive = $end !== false && (time() - $end) <= self::JOURNEY_GAP_SECONDS;
            $journeys[] = $this->summarizeJourney($current, $deviceId, $isActive);
        }

        return $journeys;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listCases(): array
    {
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            return [];
        }

        $cases = [];
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $summary = $this->summarizeCaseFile($file);
            $cases[] = [
                'id' => (string)($summary['case_id'] ?? basename($file)),
                'file' => basename($file),
                'source' => 'shadow_case',
                'modified' => date('c', filemtime($file)),
                'size' => filesize($file),
                ...$summary,
            ];
        }

        usort($cases, static fn(array $a, array $b): int => strcmp((string)($b['modified'] ?? ''), (string)($a['modified'] ?? '')));

        return $cases;
    }

    /**
     * @return array<string,mixed>
     */
    public function buildHomeSummary(string $deviceId): array
    {
        $journeys = $this->listJourneys($deviceId);
        $cases = $this->listCases();

        $readyJourneys = array_values(array_filter($journeys, fn(array $journey): bool => in_array((string)($journey['status'] ?? ''), ['review', 'ready'], true)));
        $activeJourneys = array_values(array_filter($journeys, fn(array $journey): bool => (string)($journey['status'] ?? '') === 'active'));

        $nextActions = [];
        if ($readyJourneys !== []) {
            $nextActions[] = [
                'kind' => 'review_journey',
                'title' => 'Review næste rejse',
                'subtitle' => (string)($readyJourneys[0]['route_label'] ?? 'Rejse klar til review'),
                'journey_id' => (string)($readyJourneys[0]['id'] ?? ''),
                'status' => (string)($readyJourneys[0]['status'] ?? 'review'),
            ];
        }
        if ($cases !== []) {
            $nextActions[] = [
                'kind' => 'open_claims',
                'title' => 'Se indsendte sager',
                'subtitle' => count($cases) . ' sager ligger i backend',
                'count' => count($cases),
                'status' => 'submitted',
            ];
        }
        if ($activeJourneys !== []) {
            $nextActions[] = [
                'kind' => 'continue_live',
                'title' => 'Fortsæt aktiv rejse',
                'subtitle' => (string)($activeJourneys[0]['route_label'] ?? 'Aktiv rejse'),
                'journey_id' => (string)($activeJourneys[0]['id'] ?? ''),
                'status' => 'active',
            ];
        }

        return [
            'summary' => [
                'ready_count' => count($readyJourneys),
                'active_count' => count($activeJourneys),
                'submitted_count' => count($cases),
                'device_id' => $deviceId,
            ],
            'next_actions' => $nextActions,
        ];
    }

    /**
     * @param array{id:string,start:string,end:string,points:list<array<string,mixed>>} $journey
     * @return array<string,mixed>
     */
    private function summarizeJourney(array $journey, string $deviceId, bool $active): array
    {
        $points = $journey['points'] ?? [];
        $from = $points[0] ?? [];
        $to = $points[count($points) - 1] ?? [];
        $depStation = $this->formatPointLabel($from);
        $arrStation = $this->formatPointLabel($to);
        $routeLabel = trim($depStation . ' -> ' . $arrStation, ' ->');

        return [
            'id' => (string)$journey['id'],
            'device_id' => $deviceId,
            'source' => 'shadow_journey',
            'status' => $active ? 'active' : 'review',
            'status_label' => $active ? 'Aktiv rejse' : 'Klar til review',
            'route_label' => $routeLabel,
            'dep_station' => $depStation,
            'arr_station' => $arrStation,
            'dep_time' => (string)$journey['start'],
            'arr_time' => (string)$journey['end'],
            'delay_minutes' => null,
            'ticket_mode' => '',
            'ticket_type' => '',
            'count' => count($points),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function summarizeCaseFile(string $file): array
    {
        $raw = @file_get_contents($file);
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [
                'case_id' => basename($file),
                'journey_id' => '',
                'status' => 'submitted',
                'status_label' => 'Indsendt',
                'route_label' => '',
                'dep_station' => '',
                'arr_station' => '',
                'delay_minutes' => null,
                'ticket_mode' => '',
                'ticket_type' => '',
                'submitted_at' => '',
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
        $ticketType = (string)($primaryTicket['ticketType'] ?? $legacyJourney['ticket_type'] ?? '');
        $ticketMode = $ticketType !== '' ? $ticketType : (string)($payload['ticket_mode'] ?? '');

        return [
            'case_id' => pathinfo($file, PATHINFO_FILENAME),
            'journey_id' => (string)($decoded['journey_id'] ?? ''),
            'status' => 'submitted',
            'status_label' => 'Indsendt',
            'route_label' => $routeLabel,
            'dep_station' => $depStation,
            'arr_station' => $arrStation,
            'delay_minutes' => is_numeric((string)$delayMinutes) ? (int)$delayMinutes : null,
            'ticket_mode' => $ticketMode,
            'ticket_type' => $ticketType,
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

    /**
     * @param array<string,mixed> $point
     */
    private function formatPointLabel(array $point): string
    {
        if ($point === []) {
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
}
