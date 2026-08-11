<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\FlightSearchService;
use App\Service\SeededFlightListProvider;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FlightSearchServiceTest extends TestCase
{
    private mixed $previousLiveApis;
    /**
     * @var array<string,mixed>
     */
    private array $previousAirLookup = [];
    /**
     * @var array<string,mixed>
     */
    private array $previousAeroDataBox = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousLiveApis = Configure::read('External.useLiveApis');
        $this->previousAirLookup = (array)Configure::read('External.airLookup', []);
        $this->previousAeroDataBox = (array)Configure::read('External.aeroDataBox', []);
        Configure::write('External.useLiveApis', true);
        Cache::clear(Cache::getConfig('air_flights') !== null ? 'air_flights' : 'default');
    }

    protected function tearDown(): void
    {
        Cache::clear(Cache::getConfig('air_flights') !== null ? 'air_flights' : 'default');
        Configure::write('External.useLiveApis', $this->previousLiveApis);
        Configure::write('External.airLookup', $this->previousAirLookup);
        Configure::write('External.aeroDataBox', $this->previousAeroDataBox);
        parent::tearDown();
    }

    public function testNormalFlightLookupReturnsNormalizedCompletedFlight(): void
    {
        $provider = new AirTestProvider('test_provider', 'success', [$this->flight([
            'actual_arrival_local' => '2026-09-01T09:20:00+01:00',
            'actual_arrival_utc' => '2026-09-01T08:20:00Z',
            'status' => 'Arrived',
        ])]);
        $result = (new FlightSearchService([$provider]))->searchWithMeta('CPH', 'LHR', '2026-09-01');

        $this->assertSame('success', $result['status']);
        $this->assertFalse($result['manual_fallback']);
        $this->assertSame(20, $result['items'][0]['arrival_delay_minutes']);
        $this->assertSame('arrived', $result['items'][0]['status']);
    }

    public function testRepeatedLookupUsesCacheAndDoesNotCallProviderAgain(): void
    {
        $provider = new AirTestProvider('test_provider', 'success', [$this->flight()]);
        $service = new FlightSearchService([$provider]);

        $first = $service->searchWithMeta('CPH', 'LHR', '2026-09-02');
        $second = $service->searchWithMeta('CPH', 'LHR', '2026-09-02');

        $this->assertSame('miss', $first['cache']['status']);
        $this->assertSame('hit', $second['cache']['status']);
        $this->assertSame(1, $provider->calls);
        $this->assertSame(0.0, $second['timing']['provider_ms']);
    }

    public function testPartialProviderSuccessIsNotCachedAsACompleteDay(): void
    {
        $provider = new AirTestProvider('aerodatabox', 'success_degraded', [$this->flight()]);
        $service = new FlightSearchService([$provider]);

        $first = $service->searchWithMeta('CPH', 'LHR', '2026-09-02');
        $second = $service->searchWithMeta('CPH', 'LHR', '2026-09-02');

        $this->assertSame('success_fallback', $first['status']);
        $this->assertSame('miss', $first['cache']['status']);
        $this->assertSame('miss', $second['cache']['status']);
        $this->assertSame(2, $provider->calls);
    }

    public function testCodeshareMergeKeepsRequestedMarketingCarrier(): void
    {
        $codeshares = ['U28724', 'SK1501'];
        $operator = $this->flight([
            'flight_number' => 'SK1501',
            'marketing_flight_number' => 'SK1501',
            'operating_flight_number' => 'SK1501',
            'marketing_carrier_name' => 'SAS',
            'marketing_carrier_iata' => 'SK',
            'operating_carrier_name' => 'SAS',
            'operating_carrier_iata' => 'SK',
            'codeshare_numbers' => $codeshares,
            'codeshare_role' => 'operator',
        ]);
        $marketing = $this->flight([
            'flight_number' => 'U28724',
            'marketing_flight_number' => 'U28724',
            'operating_flight_number' => 'SK1501',
            'marketing_carrier_name' => 'easyJet',
            'marketing_carrier_iata' => 'U2',
            'operating_carrier_name' => 'SAS',
            'operating_carrier_iata' => 'SK',
            'codeshare_numbers' => $codeshares,
            'codeshare_role' => 'marketing',
        ]);
        $service = new FlightSearchService([new AirTestProvider('aerodatabox', 'success', [
            $operator,
            $marketing,
        ])]);

        $result = $service->searchWithMeta('CPH', 'LHR', '2026-09-02', ['flightNumber' => 'U28724']);

        $this->assertSame('U28724', $result['items'][0]['marketing_flight_number']);
        $this->assertSame('U2', $result['items'][0]['marketing_carrier_iata']);
        $this->assertSame('SK1501', $result['items'][0]['operating_flight_number']);
        $this->assertSame('SK', $result['items'][0]['operating_carrier_iata']);
    }

    public function testHealthSeparatesConfigurationFromLastVerifiedProviderOutcome(): void
    {
        Configure::write('External.aeroDataBox.apiKey', 'test-key-never-logged');
        Configure::write('External.aeroDataBox.apiHost', 'aerodatabox.p.rapidapi.com');
        $provider = new AirTestProvider('aerodatabox', 'success', [$this->flight()]);
        $service = new FlightSearchService([$provider]);

        $service->searchWithMeta('CPH', 'LHR', '2026-09-06');
        $health = $service->health();

        $this->assertTrue($health['live_provider_configured']);
        $this->assertTrue($health['live_provider_ready']);
        $this->assertSame('aerodatabox', $health['last_provider_outcome']['provider']);
        $this->assertSame('success', $health['last_provider_outcome']['status']);
        $this->assertArrayNotHasKey('api_key', $health['last_provider_outcome']);
    }

    public function testNoDataIsDistinctFromCancelledAndOffersManualFallback(): void
    {
        $provider = new AirTestProvider('test_provider', 'success_no_data', []);
        $service = new FlightSearchService([$provider, new SeededFlightListProvider()]);
        $result = $service->searchWithMeta('CPH', 'LHR', '2026-09-03', [
            'flightNumber' => 'SK 1501',
            'depTime' => '08:00',
            'arrTime' => '09:00',
        ]);

        $this->assertSame('flight_not_found_manual_fallback', $result['status']);
        $this->assertTrue($result['manual_fallback']);
        $this->assertSame('unknown', $result['items'][0]['status']);
        $this->assertNull($result['items'][0]['cancelled']);
    }

    public static function technicalFailures(): array
    {
        return [
            ['provider_timeout'],
            ['provider_rate_limited'],
            ['provider_unavailable'],
            ['provider_invalid_response'],
        ];
    }

    #[DataProvider('technicalFailures')]
    public function testTechnicalFailuresDegradePredictably(string $failure): void
    {
        $provider = new AirTestProvider('test_provider', $failure, []);
        $service = new FlightSearchService([$provider, new SeededFlightListProvider()]);
        $result = $service->searchWithMeta('CPH', 'LHR', '2026-09-04', [
            'flightNumber' => 'SK1501',
            'depTime' => '08:00',
        ]);

        $this->assertSame('degraded_manual_fallback', $result['status']);
        $this->assertTrue($result['manual_fallback']);
        $this->assertSame($failure, $result['provider_attempts'][0]['status']);
        $this->assertSame('miss', $result['cache']['status'], 'Technical failures must not poison the normal result cache');
    }

    public function testInvalidInputIsRejectedBeforeProviderCall(): void
    {
        $provider = new AirTestProvider('test_provider', 'success', [$this->flight()]);
        $service = new FlightSearchService([$provider]);

        $badAirport = $service->searchWithMeta('CPH<script>', 'LHR', '2026-09-01');
        $badDate = $service->searchWithMeta('CPH', 'LHR', '2026-02-31');
        $badFlight = $service->searchWithMeta('CPH', 'LHR', '2026-09-01', ['flightNumber' => str_repeat('X', 1000)]);

        $this->assertSame('invalid_request', $badAirport['status']);
        $this->assertSame('invalid_request', $badDate['status']);
        $this->assertSame('invalid_request', $badFlight['status']);
        $this->assertSame(0, $provider->calls);
    }

    public function testOverallLookupBudgetStopsFallbackChain(): void
    {
        Configure::write('External.airLookup.budgetSeconds', 1);
        $slow = new AirTestProvider('slow_provider', 'success_no_data', [], 1_050_000);
        $second = new AirTestProvider('second_provider', 'success', [$this->flight()]);
        $started = microtime(true);

        $result = (new FlightSearchService([$slow, $second]))
            ->searchWithMeta('CPH', 'LHR', '2026-09-05');

        $this->assertLessThan(1.5, microtime(true) - $started);
        $this->assertSame(0, $second->calls);
        $this->assertSame('lookup_budget_exhausted', $result['provider_attempts'][1]['status']);
        $this->assertSame('providers_unavailable', $result['status']);
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function flight(array $overrides = []): array
    {
        return $overrides + [
            'flight_number' => 'SK1501',
            'marketing_carrier_name' => 'SAS',
            'marketing_carrier_iata' => 'SK',
            'departure_airport_iata' => 'CPH',
            'arrival_airport_iata' => 'LHR',
            'scheduled_departure_local' => '2026-09-01T08:00:00+02:00',
            'scheduled_departure_utc' => '2026-09-01T06:00:00Z',
            'scheduled_arrival_local' => '2026-09-01T09:00:00+01:00',
            'scheduled_arrival_utc' => '2026-09-01T08:00:00Z',
            'status' => 'Scheduled',
            'source' => 'test_provider',
        ];
    }
}
