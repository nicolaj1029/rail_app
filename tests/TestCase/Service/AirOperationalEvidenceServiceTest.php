<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirOperationalEvidenceService;
use Cake\TestSuite\TestCase;

final class AirOperationalEvidenceServiceTest extends TestCase
{
    public function testUsesActualArrivalDelayWithoutRelabelingEstimate(): void
    {
        $result = (new AirOperationalEvidenceService())->evaluate([
            'source' => 'aerodatabox',
            'status' => 'arrived',
            'arrival_delay_minutes' => 45,
            'estimated_arrival_delay_minutes' => 30,
            'scheduled_departure_local' => '2026-09-01T08:00:00',
            'scheduled_arrival_local' => '2026-09-01T09:00:00',
            'actual_arrival_local' => '2026-09-01T09:45:00',
            'departure_airport_iata' => 'CPH',
            'arrival_airport_iata' => 'LHR',
            'flight_number' => 'SK1501',
            'carrier_name' => 'SAS',
            'cancelled' => false,
        ], [
            'dep_station_lookup_code' => 'CPH',
            'arr_station_lookup_code' => 'LHR',
            'dep_date' => '2026-09-01',
            'dep_time' => '08:00',
            'flight_number' => 'SK1501',
            'marketing_carrier' => 'SAS',
        ]);

        $this->assertSame(45, $result['arrival_delay_minutes']);
        $this->assertSame(30, $result['estimated_arrival_delay_minutes']);
        $this->assertSame('actual', $result['arrival_delay_basis']);
        $this->assertSame(45, $result['delay_minutes_estimated']);
    }

    public function testUnknownProviderCancellationRemainsUnknown(): void
    {
        $result = (new AirOperationalEvidenceService())->evaluate([
            'source' => 'ticketless_seed',
            'status' => 'unknown',
            'scheduled_departure_local' => '2026-09-01T08:00:00',
            'departure_airport_iata' => 'CPH',
            'arrival_airport_iata' => 'LHR',
            'flight_number' => 'SK1501',
            'cancelled' => null,
        ]);

        $this->assertSame('unknown', $result['cancelled']);
        $this->assertNull($result['arrival_delay_minutes']);
    }
}
