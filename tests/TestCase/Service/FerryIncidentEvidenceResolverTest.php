<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FerryIncidentEvidenceResolver;
use PHPUnit\Framework\TestCase;

final class FerryIncidentEvidenceResolverTest extends TestCase
{
    public function testSuggestsDelayAndArt19BandFromOperationalEvidence(): void
    {
        $result = (new FerryIncidentEvidenceResolver())->suggest([
            'available' => true,
            'confidence' => 'high',
            'needs_manual_review' => 'no',
            'status' => 'Arrived',
            'scheduled_departure_local' => '2026-05-01T10:00:00',
            'scheduled_arrival_local' => '2026-05-01T12:00:00',
            'actual_departure_local' => '2026-05-01T11:35:00',
            'actual_arrival_local' => '2026-05-01T13:45:00',
            'departure_delay_minutes_estimated' => 95,
            'arrival_delay_minutes_estimated' => 105,
            'cancelled' => 'no',
        ]);

        $this->assertSame('delay', $result['suggested_incident_main']);
        $this->assertSame('yes', $result['suggested_expected_departure_delay_90']);
        $this->assertSame('yes', $result['suggested_actual_departure_delay_90']);
        $this->assertSame(105, $result['suggested_arrival_delay_minutes']);
        $this->assertSame(120, $result['suggested_scheduled_journey_duration_minutes']);
        $this->assertSame(60, $result['suggested_art19_threshold_minutes']);
        $this->assertSame('25', $result['suggested_art19_band_preview']);
        $this->assertSame('high', $result['suggestion_confidence']);
        $this->assertFalse($result['manual_review_required']);
    }

    public function testEtaOnlyArrivalDelayRequiresManualReview(): void
    {
        $result = (new FerryIncidentEvidenceResolver())->suggest([
            'available' => true,
            'confidence' => 'medium',
            'needs_manual_review' => 'no',
            'scheduled_departure_local' => '2026-05-01T10:00:00',
            'scheduled_arrival_local' => '2026-05-01T15:00:00',
            'estimated_arrival_local' => '2026-05-01T18:10:00',
            'arrival_delay_minutes_estimated' => 190,
            'cancelled' => 'no',
        ]);

        $this->assertSame('delay', $result['suggested_incident_main']);
        $this->assertSame(300, $result['suggested_scheduled_journey_duration_minutes']);
        $this->assertSame(120, $result['suggested_art19_threshold_minutes']);
        $this->assertSame('25', $result['suggested_art19_band_preview']);
        $this->assertTrue($result['manual_review_required']);
        $this->assertContains('arrival_delay_from_eta_only', $result['manual_review_reasons']);
        $this->assertSame('medium', $result['suggestion_confidence']);
    }

    public function testSuggestsCancellationFromCancelledSignal(): void
    {
        $result = (new FerryIncidentEvidenceResolver())->suggest([
            'available' => true,
            'confidence' => 'medium',
            'needs_manual_review' => 'no',
            'status' => 'Cancelled',
            'cancelled' => 'yes',
        ]);

        $this->assertSame('cancellation', $result['suggested_incident_main']);
        $this->assertSame('unknown', $result['suggested_expected_departure_delay_90']);
        $this->assertSame('unknown', $result['suggested_actual_departure_delay_90']);
    }
}
