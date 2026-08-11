<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AeroDataBoxFlightListProvider;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class AirFlightProviderFailureTest extends TestCase
{
    public static function statusCases(): array
    {
        return [
            '400' => [400, 'provider_rejected_request'],
            '401' => [401, 'provider_authentication_failed'],
            '403' => [403, 'provider_authentication_failed'],
            '404' => [404, 'flight_not_found'],
            '429' => [429, 'provider_rate_limited'],
            '500' => [500, 'provider_unavailable'],
            '502' => [502, 'provider_unavailable'],
            '503' => [503, 'provider_unavailable'],
        ];
    }

    #[DataProvider('statusCases')]
    public function testClassifiesHttpFailuresWithoutRetryStorm(int $httpStatus, string $expected): void
    {
        $calls = 0;
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'example.test',
            'https://example.test',
            null,
            static function () use (&$calls, $httpStatus): Response {
                $calls++;

                return new Response(["HTTP/1.1 {$httpStatus} Error", 'Content-Type: application/json'], '{}');
            },
        );

        $this->assertSame([], $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01'));
        $this->assertSame($expected, $provider->getLastOutcome()['status']);
        $this->assertSame(1, $calls, 'A failed half-day request must not trigger a second request');
    }

    public function testClassifiesTimeout(): void
    {
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'example.test',
            'https://example.test',
            null,
            static function (): never {
                throw new RuntimeException('Connection timed out');
            },
        );

        $this->assertSame([], $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01'));
        $this->assertSame('provider_timeout', $provider->getLastOutcome()['status']);
    }

    public function testInvalidJsonAndUnexpectedSchemaAreRejected(): void
    {
        foreach (['', 'not-json', '{}'] as $body) {
            $provider = new AeroDataBoxFlightListProvider(
                'test-key',
                'example.test',
                'https://example.test',
                null,
                static fn(): Response => new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], $body),
            );
            $this->assertSame([], $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01'));
            $this->assertSame('provider_invalid_response', $provider->getLastOutcome()['status']);
        }
    }

    public function testValidEmptyResponseIsNoDataNotCancellation(): void
    {
        $provider = new AeroDataBoxFlightListProvider(
            'test-key',
            'example.test',
            'https://example.test',
            null,
            static fn(): Response => new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], '{"departures":[]}'),
        );

        $this->assertSame([], $provider->searchByRouteAndDate('CPH', 'LHR', '2026-09-01'));
        $this->assertSame('success_no_data', $provider->getLastOutcome()['status']);
        $this->assertSame(2, $provider->getLastOutcome()['attempts'] !== [] ? count($provider->getLastOutcome()['attempts']) : 0);
    }
}
