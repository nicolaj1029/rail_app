<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AdminDeskService;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

final class AdminDeskServiceTest extends TestCase
{
    public function testOperationalReviewUsesRailEvidenceLabelsAndSourceNote(): void
    {
        $review = $this->invokeOperationalReview([
            'form' => [
                'transport_mode' => 'rail',
            ],
            'meta' => [
                'rail_operational_evidence' => [
                    'source' => 'hafas',
                    'status' => 'delayed',
                    'cancelled' => 'no',
                    'delay_minutes_estimated' => 47,
                    'scheduled_departure_local' => '2026-05-01T08:00:00',
                    'actual_departure_local' => '2026-05-01T08:11:00',
                    'match_checks' => [],
                ],
            ],
        ]);

        $this->assertTrue($review['available']);
        $this->assertSame('Operationelle rail-data', $review['title']);
        $this->assertSame('operationelle rail-data', $review['action_label']);
        $this->assertSame('HAFAS', $review['source_label']);
        $this->assertSame('Aflyst', $review['cancelled_label']);
        $this->assertSame('Observeret / estimeret', $review['observed_label']);
        $this->assertStringContainsString('HAFAS bruges her', (string)$review['support_note']);
    }

    public function testOperationalReviewKeepsAirSpecificLabels(): void
    {
        $review = $this->invokeOperationalReview([
            'form' => [
                'transport_mode' => 'air',
            ],
            'meta' => [
                'air_operational_evidence' => [
                    'source' => 'aerodatabox',
                    'status' => 'cancelled',
                    'cancelled' => 'yes',
                    'match_checks' => [],
                ],
            ],
        ]);

        $this->assertTrue($review['available']);
        $this->assertSame('Operationelle flight-data', $review['title']);
        $this->assertSame('AeroDataBox', $review['source_label']);
        $this->assertSame('Cancellation', $review['cancelled_label']);
        $this->assertSame('Observeret', $review['observed_label']);
        $this->assertStringContainsString('AeroDataBox bruges her', (string)$review['support_note']);
    }

    public function testOperationalReviewUsesFerryEvidenceWhenTransportModeIsFerry(): void
    {
        $review = $this->invokeOperationalReview([
            'form' => [
                'transport_mode' => 'ferry',
            ],
            'meta' => [
                'ferry_operational_evidence' => [
                    'source' => 'marinetraffic',
                    'status' => 'delayed',
                    'cancelled' => 'no',
                    'match_checks' => [],
                ],
            ],
        ]);

        $this->assertTrue($review['available']);
        $this->assertSame('Operationelle ferry-data', $review['title']);
        $this->assertSame('MarineTraffic', $review['source_label']);
        $this->assertStringContainsString('MarineTraffic bruges her', (string)$review['support_note']);
    }

    /**
     * @param array<string,mixed> $flow
     * @return array<string,mixed>
     */
    private function invokeOperationalReview(array $flow): array
    {
        $service = new AdminDeskService();
        $method = new ReflectionMethod($service, 'operationalReviewFromFlow');
        $method->setAccessible(true);

        /** @var array<string,mixed> $review */
        $review = $method->invoke($service, $flow);

        return $review;
    }
}
