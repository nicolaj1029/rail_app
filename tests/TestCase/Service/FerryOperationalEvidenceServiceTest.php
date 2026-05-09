<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FerryOperationalEvidenceService;
use PHPUnit\Framework\TestCase;

final class FerryOperationalEvidenceServiceTest extends TestCase
{
    public function testEvaluatesHighConfidenceMatchedFerryDeparture(): void
    {
        $result = (new FerryOperationalEvidenceService())->evaluate([
            'source' => 'marinetraffic',
            'status' => 'Arrived',
            'operator_name' => 'Scandlines',
            'vessel_name' => 'Aurora',
            'vessel_imo' => '9123456',
            'vessel_mmsi' => '219000123',
            'departure_port_code' => 'DKRDB',
            'arrival_port_code' => 'DEPUT',
            'scheduled_departure_local' => '2026-05-01T10:00:00',
            'scheduled_arrival_local' => '2026-05-01T12:00:00',
            'actual_departure_local' => '2026-05-01T11:35:00',
            'actual_arrival_local' => '2026-05-01T13:45:00',
            'live_position_reported_local' => '2026-05-01T13:40:00',
        ], [
            'dep_station_lookup_code' => 'DKRDB',
            'arr_station_lookup_code' => 'DEPUT',
            'dep_date' => '2026-05-01',
            'dep_time' => '10:00',
            'operator' => 'Scandlines',
            'ferry_vessel_name' => 'Aurora',
        ]);

        $this->assertTrue($result['available']);
        $this->assertSame('high', $result['confidence']);
        $this->assertSame('no', $result['needs_manual_review']);
        $this->assertSame(105, $result['arrival_delay_minutes_estimated']);
        $this->assertSame(95, $result['departure_delay_minutes_estimated']);
        $this->assertGreaterThanOrEqual(80, $result['evidence_score']);
    }

    public function testMarksRouteMismatchAsManualReview(): void
    {
        $result = (new FerryOperationalEvidenceService())->evaluate([
            'source' => 'marinetraffic',
            'operator_name' => 'Scandlines',
            'departure_port_code' => 'DKRDB',
            'arrival_port_code' => 'DEPUT',
            'scheduled_departure_local' => '2026-05-01T10:00:00',
        ], [
            'dep_station_lookup_code' => 'DKGED',
            'arr_station_lookup_code' => 'DEROS',
            'dep_date' => '2026-05-01',
        ]);

        $this->assertSame('low', $result['confidence']);
        $this->assertSame('yes', $result['needs_manual_review']);
        $this->assertSame('mismatch', $result['match_checks'][0]['status']);
    }
}
