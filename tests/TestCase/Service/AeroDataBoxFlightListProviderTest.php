<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AeroDataBoxFlightListProvider;
use App\Service\AirFlightNormalizer;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

final class AeroDataBoxFlightListProviderTest extends TestCase
{
    public function testLinksContractShapedCodeshareRowsToOperatingFlight(): void
    {
        $calls = 0;
        $payload = ['departures' => [
            $this->flightRow('SK1501', 'SAS', 'SK', 'SAS', 'IsOperator'),
            $this->flightRow('LH6257', 'Lufthansa', 'LH', 'DLH', 'IsCodeshared'),
        ]];
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'aerodatabox.p.rapidapi.com',
            'https://example.test',
            null,
            static function () use (&$calls, $payload): Response {
                $body = $calls++ === 0 ? $payload : ['departures' => []];

                return self::jsonResponse($body);
            },
        );

        $items = $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01');
        $marketing = array_values(array_filter(
            $items,
            static fn(array $item): bool => ($item['flight_number'] ?? '') === 'LH6257',
        ))[0];

        $this->assertSame('LH6257', $marketing['marketing_flight_number']);
        $this->assertSame('SK1501', $marketing['operating_flight_number']);
        $this->assertSame('SAS', $marketing['operating_carrier_name']);
        $this->assertSame('SK', $marketing['operating_carrier_iata']);
        $this->assertContains('LH6257', $marketing['codeshare_numbers']);
        $this->assertContains('SK1501', $marketing['codeshare_numbers']);
        $this->assertSame('2026-09-01T09:25+01:00', $marketing['estimated_arrival_local']);
        $this->assertNull($marketing['actual_arrival_local']);
        $this->assertSame(2, $provider->getLastOutcome()['attempts'] !== []
            ? count($provider->getLastOutcome()['attempts'])
            : 0);
    }

    public function testArrivedRevisedTimeBecomesActualAndProducesUtcDelay(): void
    {
        $calls = 0;
        $row = $this->flightRow('SK1501', 'SAS', 'SK', 'SAS', 'IsOperator');
        $row['status'] = 'Arrived';
        $row['arrival']['scheduledTime'] = [
            'local' => '2026-09-01T09:00+01:00',
            'utc' => '2026-09-01T08:00Z',
        ];
        $row['arrival']['revisedTime'] = [
            'local' => '2026-09-01T10:12+01:00',
            'utc' => '2026-09-01T09:12Z',
        ];
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'aerodatabox.p.rapidapi.com',
            'https://example.test',
            null,
            static function () use (&$calls, $row): Response {
                $body = $calls++ === 0 ? ['departures' => [$row]] : ['departures' => []];

                return self::jsonResponse($body);
            },
        );

        $mapped = $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01')[0];
        $normalized = (new AirFlightNormalizer())->normalize($mapped, 'CPH', 'LHR');

        $this->assertSame('2026-09-01T09:12Z', $mapped['actual_arrival_utc']);
        $this->assertNull($mapped['estimated_arrival_utc']);
        $this->assertSame(72, $normalized['arrival_delay_minutes']);
        $this->assertSame('actual', $normalized['arrival_delay_basis']);
        $this->assertTrue($normalized['operational_data_verified']);
    }

    public function testNoContentIsAValidNoDataResponse(): void
    {
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'aerodatabox.p.rapidapi.com',
            'https://example.test',
            null,
            static fn(): Response => new Response(['HTTP/1.1 204 No Content'], ''),
        );

        $this->assertSame([], $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01'));
        $this->assertSame('success_no_data', $provider->getLastOutcome()['status']);
    }

    public function testFlightNumberUsesSingleFlightEndpointAndMapsCompletedArrival(): void
    {
        $calls = 0;
        $requestedUrl = '';
        $row = $this->flightRow('BA 823', 'British Airways', 'BA', 'BAW', 'IsOperator');
        $row['status'] = 'Arrived';
        $row['arrival']['scheduledTime'] = [
            'local' => '2026-08-10 08:25+01:00',
            'utc' => '2026-08-10 07:25Z',
        ];
        $row['arrival']['revisedTime'] = [
            'local' => '2026-08-10 08:13+01:00',
            'utc' => '2026-08-10 07:13Z',
        ];
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'aerodatabox.p.rapidapi.com',
            'https://example.test',
            null,
            static function (string $url) use (&$calls, &$requestedUrl, $row): Response {
                $calls++;
                $requestedUrl = $url;

                return self::jsonResponse([$row]);
            },
        );

        $items = $provider->searchByRouteAndDate('CPH', 'LHR', '2026-08-10', ['flightNumber' => 'BA 823']);
        $normalized = (new AirFlightNormalizer())->normalize($items[0], 'CPH', 'LHR');

        $this->assertSame(1, $calls);
        $this->assertStringEndsWith('/flights/number/BA823/2026-08-10', $requestedUrl);
        $this->assertSame('2026-08-10 07:13Z', $items[0]['actual_arrival_utc']);
        $this->assertSame(-12, $normalized['arrival_delay_minutes']);
        $this->assertSame('actual', $normalized['arrival_delay_basis']);
        $this->assertSame(1, count($provider->getLastOutcome()['attempts']));
    }

    public function testFutureCodeshareWithFlightNumberKeepsFullFidsLinking(): void
    {
        $calls = 0;
        $payload = ['departures' => [
            $this->flightRow('SK1501', 'SAS', 'SK', 'SAS', 'IsOperator'),
            $this->flightRow('LH6257', 'Lufthansa', 'LH', 'DLH', 'IsCodeshared'),
        ]];
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'aerodatabox.p.rapidapi.com',
            'https://example.test',
            null,
            static function (string $url) use (&$calls, $payload): Response {
                $calls++;
                self::assertStringContainsString('/flights/airports/iata/', $url);

                return self::jsonResponse($calls === 1 ? $payload : ['departures' => []]);
            },
        );

        $items = $provider->searchByRouteAndDate(
            'CPH',
            'LHR',
            '2099-09-01',
            ['flightNumber' => 'LH6257'],
        );
        $marketing = array_values(array_filter(
            $items,
            static fn(array $item): bool => ($item['flight_number'] ?? '') === 'LH6257',
        ))[0];

        $this->assertSame(2, $calls);
        $this->assertSame('SK1501', $marketing['operating_flight_number']);
        $this->assertSame('2026-09-01T09:00+01:00', $marketing['scheduled_arrival_local']);
    }

    /** @return array<string,mixed> */
    private function flightRow(
        string $number,
        string $airline,
        string $iata,
        string $icao,
        string $codeshareStatus,
    ): array {
        return [
            'number' => $number,
            'status' => 'Expected',
            'codeshareStatus' => $codeshareStatus,
            'isCargo' => false,
            'airline' => [
                'name' => $airline,
                'iata' => $iata,
                'icao' => $icao,
            ],
            'departure' => [
                'airport' => ['iata' => 'CPH', 'icao' => 'EKCH', 'timeZone' => 'Europe/Copenhagen'],
                'scheduledTime' => [
                    'local' => '2026-09-01T08:00+02:00',
                    'utc' => '2026-09-01T06:00Z',
                ],
                'quality' => ['Basic'],
            ],
            'arrival' => [
                'airport' => ['iata' => 'LHR', 'icao' => 'EGLL', 'timeZone' => 'Europe/London'],
                'scheduledTime' => [
                    'local' => '2026-09-01T09:00+01:00',
                    'utc' => '2026-09-01T08:00Z',
                ],
                'revisedTime' => [
                    'local' => '2026-09-01T09:25+01:00',
                    'utc' => '2026-09-01T08:25Z',
                ],
                'quality' => ['Basic', 'Live'],
            ],
        ];
    }

    /** @param array<string,mixed> $body */
    private static function jsonResponse(array $body): Response
    {
        return new Response(
            ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
            (string)json_encode($body, JSON_UNESCAPED_SLASHES),
        );
    }
}
