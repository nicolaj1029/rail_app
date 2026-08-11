<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AirFlightNormalizer;
use Cake\TestSuite\TestCase;

final class AirFlightNormalizerTest extends TestCase
{
    private AirFlightNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new AirFlightNormalizer();
    }

    public function testNormalizesEquivalentIataAndIcaoFlightNumbersToSameIdentity(): void
    {
        $sk = $this->normalizer->normalize($this->base([
            'flight_number' => 'SK 1501',
            'marketing_carrier_iata' => 'SK',
        ]), 'CPH', 'LHR');
        $sas = $this->normalizer->normalize($this->base([
            'flight_number' => 'SAS1501',
            'marketing_carrier_icao' => 'SAS',
        ]), 'CPH', 'LHR');

        $this->assertSame('SK1501', $sk['flight_number']);
        $this->assertSame($sk['flight_identity_key'], $sas['flight_identity_key']);
        $this->assertSame('SK', $sas['marketing_carrier_iata']);
        $this->assertSame('SAS', $sas['marketing_carrier_icao']);
    }

    public function testCrossMidnightAndCrossTimezoneDelayUsesUtc(): void
    {
        $flight = $this->normalizer->normalize($this->base([
            'scheduled_departure_local' => '2026-10-10T23:30:00+02:00',
            'scheduled_departure_utc' => '2026-10-10T21:30:00Z',
            'scheduled_arrival_local' => '2026-10-11T01:00:00-04:00',
            'scheduled_arrival_utc' => '2026-10-11T05:00:00Z',
            'actual_arrival_local' => '2026-10-11T01:45:00-04:00',
            'actual_arrival_utc' => '2026-10-11T05:45:00Z',
            'status' => 'Arrived',
        ]), 'CPH', 'LHR');

        $this->assertSame(45, $flight['arrival_delay_minutes']);
        $this->assertSame('actual', $flight['arrival_delay_basis']);
        $this->assertSame('arrived', $flight['status']);
        $this->assertSame('2026-10-11T05:45:00Z', $flight['actual_arrival_utc']);
    }

    public function testDstTransitionUsesTimezoneInsteadOfNaiveLocalArithmetic(): void
    {
        $flight = $this->normalizer->normalize($this->base([
            'departure_timezone' => 'Europe/Copenhagen',
            'scheduled_departure_local' => '2026-03-29T01:30:00',
            'scheduled_departure_utc' => null,
            'actual_departure_local' => '2026-03-29T03:30:00',
            'status' => 'Departed',
        ]), 'CPH', 'LHR');

        $this->assertSame(60, $flight['departure_delay_minutes']);
        $this->assertSame('2026-03-29T00:30:00Z', $flight['scheduled_departure_utc']);
        $this->assertSame('2026-03-29T01:30:00Z', $flight['actual_departure_utc']);
    }

    public function testEstimateIsNotExposedAsActualDelay(): void
    {
        $flight = $this->normalizer->normalize($this->base([
            'scheduled_arrival_utc' => '2026-09-01T10:00:00Z',
            'estimated_arrival_utc' => '2026-09-01T10:35:00Z',
            'estimated_arrival_local' => '2026-09-01T11:35:00+01:00',
            'status' => 'Active',
        ]), 'CPH', 'LHR');

        $this->assertNull($flight['arrival_delay_minutes']);
        $this->assertSame(35, $flight['estimated_arrival_delay_minutes']);
        $this->assertSame('estimated', $flight['arrival_delay_basis']);
        $this->assertNull($flight['actual_arrival_utc']);
        $this->assertSame('active', $flight['status']);
    }

    public function testCodesharePreservesMarketingAndOperatingIdentity(): void
    {
        $flight = $this->normalizer->normalize($this->base([
            'flight_number' => 'LH 6207',
            'marketing_flight_number' => 'LH6207',
            'operating_flight_number' => 'SK1501',
            'marketing_carrier_name' => 'Lufthansa',
            'marketing_carrier_iata' => 'LH',
            'operating_carrier_name' => 'SAS',
            'operating_carrier_iata' => 'SK',
            'codeshare_numbers' => ['LH6207', 'SK1501'],
        ]), 'CPH', 'LHR', ['flightNumber' => 'LH 6207']);

        $this->assertSame('LH6207', $flight['flight_number']);
        $this->assertSame('LH6207', $flight['marketing_flight_number']);
        $this->assertSame('SK1501', $flight['operating_flight_number']);
        $this->assertSame('SAS', $flight['operating_carrier_name']);
    }

    public function testRequestedCodeshareDoesNotRelabelOperatorRowCarrier(): void
    {
        $flight = $this->normalizer->normalize($this->base([
            'flight_number' => 'SK1501',
            'marketing_flight_number' => 'SK1501',
            'operating_flight_number' => 'SK1501',
            'marketing_carrier_name' => 'SAS',
            'marketing_carrier_iata' => 'SK',
            'operating_carrier_name' => 'SAS',
            'operating_carrier_iata' => 'SK',
            'codeshare_numbers' => ['U28724', 'SK1501'],
            'codeshare_role' => 'operator',
        ]), 'CPH', 'LHR', ['flightNumber' => 'U28724']);

        $this->assertSame('SK1501', $flight['marketing_flight_number']);
        $this->assertSame('SK', $flight['marketing_carrier_iata']);
        $this->assertContains('U28724', $flight['codeshare_numbers']);
    }

    public function testCancellationAndDiversionAreNormalizedWithoutConflatingNoData(): void
    {
        $cancelled = $this->normalizer->normalize($this->base(['status' => 'Cancelled', 'cancelled' => true]), 'CPH', 'LHR');
        $diverted = $this->normalizer->normalize($this->base([
            'status' => 'Diverted',
            'diverted' => true,
            'actual_arrival_airport_iata' => 'STN',
        ]), 'CPH', 'LHR');
        $unknown = $this->normalizer->normalize($this->base(['status' => null, 'cancelled' => null]), 'CPH', 'LHR');

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertTrue($cancelled['cancelled']);
        $this->assertSame('diverted', $diverted['status']);
        $this->assertSame('STN', $diverted['actual_arrival_airport_iata']);
        $this->assertSame('unknown', $unknown['status']);
        $this->assertNull($unknown['cancelled']);
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function base(array $overrides = []): array
    {
        return $overrides + [
            'flight_number' => 'SK1501',
            'marketing_carrier_name' => 'SAS',
            'departure_airport_iata' => 'CPH',
            'departure_airport_icao' => 'EKCH',
            'arrival_airport_iata' => 'LHR',
            'arrival_airport_icao' => 'EGLL',
            'scheduled_departure_local' => '2026-09-01T08:00:00+02:00',
            'scheduled_departure_utc' => '2026-09-01T06:00:00Z',
            'scheduled_arrival_local' => '2026-09-01T09:00:00+01:00',
            'scheduled_arrival_utc' => '2026-09-01T08:00:00Z',
            'source' => 'test_provider',
        ];
    }
}
