<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\PassengerDataService;
use Cake\TestSuite\TestCase;

final class PassengerDataServiceTest extends TestCase
{
    private const DEVICE_ID = 'passenger_test_device';

    private PassengerDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PassengerDataService();
        $this->cleanupFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupFiles();
        parent::tearDown();
    }

    public function testListJourneysReturnsNormalizedPayload(): void
    {
        $path = ROOT . DS . 'tmp' . DS . 'shadow_pings' . DS . self::DEVICE_ID . '.jsonl';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $rows = [
            ['ping' => ['t' => '2026-03-09T08:00:00Z', 'lat' => 55.6727, 'lon' => 12.5646]],
            ['ping' => ['t' => '2026-03-09T08:05:00Z', 'lat' => 55.6731, 'lon' => 12.5651]],
            ['ping' => ['t' => '2026-03-09T08:30:00Z', 'lat' => 55.6605, 'lon' => 12.5169]],
        ];
        file_put_contents($path, implode(PHP_EOL, array_map(static fn(array $row): string => (string)json_encode($row), $rows)));

        $journeys = $this->service->listJourneys(self::DEVICE_ID);

        $this->assertCount(2, $journeys);
        $this->assertSame('shadow_journey', $journeys[0]['source'] ?? null);
        $this->assertArrayHasKey('status_label', $journeys[0]);
        $this->assertArrayHasKey('route_label', $journeys[0]);
        $this->assertArrayHasKey('dep_station', $journeys[0]);
        $this->assertArrayHasKey('arr_station', $journeys[0]);
    }

    public function testListCasesReturnsNormalizedPayload(): void
    {
        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $payload = [
            'journey_id' => 'j-123',
            'submitted_at' => '2026-03-09T10:00:00Z',
            'payload' => [
                'tickets' => [[
                    'from' => 'KÃ¸benhavn H',
                    'to' => 'Roskilde',
                    'ticketType' => 'ticket',
                ]],
                'incident' => [
                    'delay_confirmed_minutes' => 47,
                ],
            ],
        ];
        file_put_contents($dir . DS . 'case_test.json', (string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $cases = $this->service->listCases();
        $target = $this->findCaseByFile($cases, 'case_test.json');

        $this->assertNotNull($target);
        $this->assertSame('shadow_case', $target['source'] ?? null);
        $this->assertSame('submitted', $target['status'] ?? null);
        $this->assertSame('Indsendt', $target['status_label'] ?? null);
        $this->assertSame('KÃ¸benhavn H -> Roskilde', $target['route_label'] ?? null);
        $this->assertSame(47, $target['delay_minutes'] ?? null);
        $this->assertSame('ticket', $target['ticket_mode'] ?? null);
    }

    public function testBuildHomeSummaryUsesNormalizedStatuses(): void
    {
        $baselineSubmittedCount = count($this->service->listCases());

        $journeyPath = ROOT . DS . 'tmp' . DS . 'shadow_pings' . DS . self::DEVICE_ID . '.jsonl';
        if (!is_dir(dirname($journeyPath))) {
            mkdir(dirname($journeyPath), 0777, true);
        }

        $rows = [
            ['ping' => ['t' => gmdate('c', time() - 60), 'lat' => 55.6727, 'lon' => 12.5646]],
            ['ping' => ['t' => gmdate('c', time() - 30), 'lat' => 55.6731, 'lon' => 12.5651]],
        ];
        file_put_contents($journeyPath, implode(PHP_EOL, array_map(static fn(array $row): string => (string)json_encode($row), $rows)));

        $dir = ROOT . DS . 'tmp' . DS . 'shadow_cases';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . DS . 'case_home.json', (string)json_encode(['journey_id' => 'j-1', 'submitted_at' => '2026-03-09T10:00:00Z']));

        $summary = $this->service->buildHomeSummary(self::DEVICE_ID);

        $this->assertSame(self::DEVICE_ID, $summary['summary']['device_id'] ?? null);
        $this->assertSame(1, $summary['summary']['active_count'] ?? null);
        $this->assertSame($baselineSubmittedCount + 1, $summary['summary']['submitted_count'] ?? null);
        $this->assertNotEmpty($summary['next_actions'] ?? []);
    }

    private function cleanupFiles(): void
    {
        $pingFile = ROOT . DS . 'tmp' . DS . 'shadow_pings' . DS . self::DEVICE_ID . '.jsonl';
        if (is_file($pingFile)) {
            @unlink($pingFile);
        }

        foreach ([
            ROOT . DS . 'tmp' . DS . 'shadow_cases' . DS . 'case_test.json',
            ROOT . DS . 'tmp' . DS . 'shadow_cases' . DS . 'case_home.json',
        ] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $cases
     * @return array<string,mixed>|null
     */
    private function findCaseByFile(array $cases, string $file): ?array
    {
        foreach ($cases as $case) {
            if (($case['file'] ?? null) === $file) {
                return $case;
            }
        }

        return null;
    }
}
