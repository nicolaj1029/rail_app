<?php
declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Throwable;

final class SeededFlightListProvider implements FlightListProviderInterface, FlightListProviderDiagnosticsInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $lastOutcome = ['provider' => 'ticketless_seed', 'status' => 'not_started'];

    /** Construct the explicitly unverified manual fallback. */
    public function __construct(
        private ?TransportOperatorRegistry $operatorRegistry = null,
    ) {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
    }

    /** Build a ticket-derived fallback without claiming provider verification. */
    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        if ($fromIata === '' || $toIata === '' || $date === '') {
            $this->lastOutcome = ['provider' => 'ticketless_seed', 'status' => 'invalid_request', 'latency_ms' => 0.0];

            return [];
        }

        $departureTime = $this->normalizeTime((string)($context['depTime'] ?? ''));
        $arrivalTime = $this->normalizeTime((string)($context['arrTime'] ?? ''));
        $rawFlightNumber = (string)(
            $context['flightNumber']
            ?? $context['selectedFlightNumber']
            ?? ''
        );
        $flightNumber = strtoupper((string)(
            preg_replace('/[\s\-]+/', '', trim($rawFlightNumber)) ?? ''
        ));
        $marketingCarrier = trim((string)($context['marketingCarrier'] ?? ''));
        $operatingCarrier = trim((string)($context['operatingCarrier'] ?? ''));

        $resolved = $this->resolveCarrierNames($flightNumber, $marketingCarrier, $operatingCarrier);
        $marketingCarrier = $resolved['marketing_carrier_name'];
        $operatingCarrier = $resolved['operating_carrier_name'];
        $displayCarrier = $resolved['carrier_name'];

        if ($displayCarrier === '' && $flightNumber === '' && $departureTime === '' && $arrivalTime === '') {
            $this->lastOutcome = ['provider' => 'ticketless_seed', 'status' => 'success_no_data', 'latency_ms' => 0.0];

            return [];
        }

        $item = [
            'flight_key' => implode('|', [
                $flightNumber !== '' ? $flightNumber : 'seed',
                $date,
                $fromIata,
                $toIata,
                $departureTime !== '' ? $departureTime : '00:00',
            ]),
            'flight_number' => $flightNumber,
            'marketing_flight_number' => $flightNumber,
            'operating_flight_number' => '',
            'carrier_name' => $displayCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'departure_airport_iata' => $fromIata,
            'arrival_airport_iata' => $toIata,
            'scheduled_departure_local' => $this->buildDateTime($date, $departureTime),
            'scheduled_arrival_local' => $this->buildArrivalDateTime($date, $departureTime, $arrivalTime),
            'status' => null,
            'source' => 'ticketless_seed',
            'retrieved_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'codeshare_numbers' => [],
        ];

        $this->lastOutcome = [
            'provider' => 'ticketless_seed',
            'status' => 'manual_fallback',
            'latency_ms' => 0.0,
            'item_count' => 1,
        ];

        return [$item];
    }

    /** Return the stable fallback identifier. */
    public function getProviderName(): string
    {
        return 'ticketless_seed';
    }

    /** Return diagnostics for the most recent fallback. */
    public function getLastOutcome(): array
    {
        return $this->lastOutcome;
    }

    /**
     * @return array{carrier_name:string,marketing_carrier_name:string,operating_carrier_name:string}
     */
    private function resolveCarrierNames(
        string $flightNumber,
        string $marketingCarrier,
        string $operatingCarrier,
    ): array {
        $marketingCarrier = trim($marketingCarrier);
        $operatingCarrier = trim($operatingCarrier);

        $derived = null;
        if ($flightNumber !== '') {
            $derived = $this->operatorRegistry?->findByIdentity('air', $flightNumber);
        }

        if ($marketingCarrier === '' && is_array($derived)) {
            $marketingCarrier = trim((string)($derived['name'] ?? ($derived['brand_group'] ?? '')));
        }
        if ($operatingCarrier === '' && is_array($derived)) {
            $operatingCarrier = trim((string)(
                $derived['operating_carrier_name']
                ?? $derived['legal_entity_name']
                ?? $derived['name']
                ?? ''
            ));
        }
        if ($operatingCarrier === '') {
            $operatingCarrier = $marketingCarrier;
        }

        return [
            'carrier_name' => $marketingCarrier !== '' ? $marketingCarrier : $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'operating_carrier_name' => $operatingCarrier,
        ];
    }

    /** Validate a local wall-clock time. */
    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        return '';
    }

    /** Combine a validated service date and local time. */
    private function buildDateTime(string $date, string $time): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        if ($time === '') {
            return $date . 'T00:00:00';
        }

        return $date . 'T' . $time . ':00';
    }

    /** Move an earlier arrival wall time to the next day. */
    private function buildArrivalDateTime(string $date, string $departureTime, string $arrivalTime): ?string
    {
        $value = $this->buildDateTime($date, $arrivalTime);
        if ($value === null || $arrivalTime === '' || $departureTime === '' || $arrivalTime >= $departureTime) {
            return $value;
        }

        try {
            return (new DateTimeImmutable($value))->modify('+1 day')->format('Y-m-d\TH:i:s');
        } catch (Throwable) {
            return $value;
        }
    }
}
