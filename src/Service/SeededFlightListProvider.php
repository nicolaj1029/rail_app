<?php
declare(strict_types=1);

namespace App\Service;

final class SeededFlightListProvider implements FlightListProviderInterface
{
    public function __construct(
        private ?TransportOperatorRegistry $operatorRegistry = null
    ) {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
    }

    public function searchByRouteAndDate(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        if ($fromIata === '' || $toIata === '' || $date === '') {
            return [];
        }

        $departureTime = $this->normalizeTime((string)($context['depTime'] ?? ''));
        $arrivalTime = $this->normalizeTime((string)($context['arrTime'] ?? ''));
        $flightNumber = strtoupper(trim((string)($context['flightNumber'] ?? ($context['selectedFlightNumber'] ?? ''))));
        $marketingCarrier = trim((string)($context['marketingCarrier'] ?? ''));
        $operatingCarrier = trim((string)($context['operatingCarrier'] ?? ''));

        $resolved = $this->resolveCarrierNames($flightNumber, $marketingCarrier, $operatingCarrier);
        $marketingCarrier = $resolved['marketing_carrier_name'];
        $operatingCarrier = $resolved['operating_carrier_name'];
        $displayCarrier = $resolved['carrier_name'];

        if ($displayCarrier === '' && $flightNumber === '' && $departureTime === '' && $arrivalTime === '') {
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
            'carrier_name' => $displayCarrier,
            'operating_carrier_name' => $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'departure_airport_iata' => $fromIata,
            'arrival_airport_iata' => $toIata,
            'scheduled_departure_local' => $this->buildDateTime($date, $departureTime),
            'scheduled_arrival_local' => $this->buildDateTime($date, $arrivalTime),
            'status' => null,
            'source' => 'ticketless_seed',
            'codeshare_numbers' => [],
        ];

        return [$item];
    }

    /**
     * @return array{carrier_name:string,marketing_carrier_name:string,operating_carrier_name:string}
     */
    private function resolveCarrierNames(string $flightNumber, string $marketingCarrier, string $operatingCarrier): array
    {
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
            $operatingCarrier = trim((string)($derived['operating_carrier_name'] ?? ($derived['legal_entity_name'] ?? ($derived['name'] ?? ''))));
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
}
