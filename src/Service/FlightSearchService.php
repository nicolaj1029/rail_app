<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;

final class FlightSearchService
{
    /** @var array<int,FlightListProviderInterface> */
    private array $providers;

    public function __construct(
        ?array $providers = null,
        private ?TransportOperatorRegistry $operatorRegistry = null
    ) {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
        $this->providers = $providers ?? $this->buildProviderChain();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function search(string $fromIata, string $toIata, string $date, array $context = []): array
    {
        $fromIata = strtoupper(trim($fromIata));
        $toIata = strtoupper(trim($toIata));
        $date = trim($date);
        if ($fromIata === '' || $toIata === '' || $date === '') {
            return [];
        }

        $cacheKey = 'air_flights_' . md5(json_encode([
            'from' => $fromIata,
            'to' => $toIata,
            'date' => $date,
            'flight' => (string)($context['flightNumber'] ?? ''),
            'carrier' => (string)($context['marketingCarrier'] ?? ''),
            'op' => (string)($context['operatingCarrier'] ?? ''),
            'depTime' => (string)($context['depTime'] ?? ''),
            'arrTime' => (string)($context['arrTime'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
        $cached = Cache::read($cacheKey, 'default');
        $liveApisEnabled = (bool)Configure::read('External.useLiveApis');
        if (is_array($cached) && ($cached !== [] || !$liveApisEnabled)) {
            return $cached;
        }

        $items = [];
        foreach ($this->providers as $provider) {
            $providerItems = $provider->searchByRouteAndDate($fromIata, $toIata, $date, $context);
            if ($providerItems === []) {
                continue;
            }
            foreach ($providerItems as $item) {
                $normalized = $this->normalizeItem($item, $fromIata, $toIata);
                if ($normalized !== null) {
                    $items[] = $normalized;
                }
            }
            if ($items !== []) {
                break;
            }
        }

        $items = $this->dedupeAndSort($items);
        if ($items !== [] || !$liveApisEnabled) {
            Cache::write($cacheKey, $items, 'default');
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<int,array<string,mixed>>
     */
    public function searchFromForm(array $form, array $meta = []): array
    {
        $fromIata = $this->resolveAirportCode(
            (string)($form['dep_station_lookup_code'] ?? ''),
            (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''))
        );
        $toIata = $this->resolveAirportCode(
            (string)($form['arr_station_lookup_code'] ?? ''),
            (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''))
        );
        $date = trim((string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')));

        return $this->search($fromIata, $toIata, $date, [
            'depTime' => (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')),
            'arrTime' => (string)($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')),
            'flightNumber' => (string)($form['ticket_no'] ?? ($form['flight_number'] ?? '')),
            'marketingCarrier' => (string)($form['marketing_carrier'] ?? ($form['operator'] ?? ($meta['_auto']['marketing_carrier']['value'] ?? ''))),
            'operatingCarrier' => (string)($form['operating_carrier'] ?? ($meta['_auto']['operating_carrier']['value'] ?? '')),
            'departureLabel' => (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
            'arrivalLabel' => (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
        ]);
    }

    private function resolveAirportCode(string $lookupCode, string $label): string
    {
        $lookupCode = strtoupper(trim($lookupCode));
        if ($lookupCode !== '') {
            return $lookupCode;
        }

        $label = strtoupper(trim($label));
        if (preg_match('/\b([A-Z]{3})\b/', $label, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>|null
     */
    private function normalizeItem(array $item, string $fromIata, string $toIata): ?array
    {
        $departureIata = strtoupper(trim((string)($item['departure_airport_iata'] ?? '')));
        $arrivalIata = strtoupper(trim((string)($item['arrival_airport_iata'] ?? '')));
        if ($departureIata === '' || $arrivalIata === '') {
            return null;
        }
        if ($departureIata !== $fromIata || $arrivalIata !== $toIata) {
            return null;
        }

        $flightNumber = strtoupper(trim((string)($item['flight_number'] ?? '')));
        $carrierName = trim((string)($item['carrier_name'] ?? ''));
        $operatingCarrier = trim((string)($item['operating_carrier_name'] ?? ''));
        $marketingCarrier = trim((string)($item['marketing_carrier_name'] ?? ''));
        if ($carrierName === '' && $flightNumber !== '') {
            $match = $this->operatorRegistry?->findByIdentity('air', $flightNumber);
            if (is_array($match)) {
                $carrierName = trim((string)($match['name'] ?? ''));
                if ($marketingCarrier === '') {
                    $marketingCarrier = $carrierName;
                }
                if ($operatingCarrier === '') {
                    $operatingCarrier = trim((string)($match['operating_carrier_name'] ?? ($match['legal_entity_name'] ?? $carrierName)));
                }
            }
        }

        $depLocal = $this->normalizeDateTime((string)($item['scheduled_departure_local'] ?? ''));
        $arrLocal = $this->normalizeDateTime((string)($item['scheduled_arrival_local'] ?? ''));

        return [
            'flight_key' => trim((string)($item['flight_key'] ?? '')) !== ''
                ? (string)$item['flight_key']
                : md5($departureIata . '|' . $arrivalIata . '|' . $depLocal . '|' . $arrLocal . '|' . $flightNumber),
            'flight_number' => $flightNumber,
            'carrier_name' => $carrierName,
            'operating_carrier_name' => $operatingCarrier,
            'marketing_carrier_name' => $marketingCarrier,
            'departure_airport_iata' => $departureIata,
            'arrival_airport_iata' => $arrivalIata,
            'scheduled_departure_local' => $depLocal,
            'scheduled_arrival_local' => $arrLocal,
            'estimated_departure_local' => $this->normalizeDateTime((string)($item['estimated_departure_local'] ?? '')),
            'estimated_arrival_local' => $this->normalizeDateTime((string)($item['estimated_arrival_local'] ?? '')),
            'actual_departure_local' => $this->normalizeDateTime((string)($item['actual_departure_local'] ?? '')),
            'actual_arrival_local' => $this->normalizeDateTime((string)($item['actual_arrival_local'] ?? '')),
            'status' => $item['status'] ?? null,
            'cancelled' => $item['cancelled'] ?? null,
            'source' => trim((string)($item['source'] ?? 'unknown')),
            'codeshare_numbers' => is_array($item['codeshare_numbers'] ?? null) ? array_values(array_map('strval', $item['codeshare_numbers'])) : [],
        ];
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 16 ? $value . ':00' : $value;
        }

        return null;
    }

    /**
     * @return array<int,FlightListProviderInterface>
     */
    private function buildProviderChain(): array
    {
        $providers = [];
        if ((bool)Configure::read('External.useLiveApis')) {
            $aeroDataBox = new AeroDataBoxFlightListProvider();
            if ($aeroDataBox->isConfigured()) {
                $providers[] = $aeroDataBox;
            }

            $aviationstack = new AviationstackFlightListProvider();
            if ($aviationstack->isConfigured()) {
                $providers[] = $aviationstack;
            }
        }

        $providers[] = new SeededFlightListProvider($this->operatorRegistry);

        return $providers;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function dedupeAndSort(array $items): array
    {
        $out = [];
        $groups = [];
        foreach ($items as $item) {
            $key = implode('|', [
                (string)($item['departure_airport_iata'] ?? ''),
                (string)($item['arrival_airport_iata'] ?? ''),
                (string)($item['scheduled_departure_local'] ?? ''),
                (string)($item['scheduled_arrival_local'] ?? ''),
            ]);
            if (!isset($groups[$key])) {
                $groups[$key] = $item;
                continue;
            }

            $existing = $groups[$key];
            $existingCarrier = trim((string)($existing['operating_carrier_name'] ?? ''));
            $currentCarrier = trim((string)($item['operating_carrier_name'] ?? ''));
            if ($existingCarrier === '' && $currentCarrier !== '') {
                $groups[$key] = $item;
                $existing = $item;
            }
            $existingCodeshares = is_array($existing['codeshare_numbers'] ?? null) ? $existing['codeshare_numbers'] : [];
            $flightNumber = trim((string)($item['flight_number'] ?? ''));
            if ($flightNumber !== '' && !in_array($flightNumber, $existingCodeshares, true) && $flightNumber !== (string)($existing['flight_number'] ?? '')) {
                $existingCodeshares[] = $flightNumber;
                $groups[$key]['codeshare_numbers'] = array_values(array_unique($existingCodeshares));
            }
        }

        $out = array_values($groups);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['scheduled_departure_local'] ?? ''), (string)($b['scheduled_departure_local'] ?? ''));
        });

        return $out;
    }
}
