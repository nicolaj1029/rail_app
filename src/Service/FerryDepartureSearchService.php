<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;

final class FerryDepartureSearchService
{
    /** @var array<int,FerryDepartureListProviderInterface> */
    private array $providers;

    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? $this->buildProviderChain();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public function search(string $departureCode, string $arrivalCode, string $date, array $context = []): array
    {
        $departureCode = strtoupper(trim($departureCode));
        $arrivalCode = strtoupper(trim($arrivalCode));
        $date = trim($date);
        if ($date === '') {
            return [];
        }

        $cacheKey = 'ferry_departures_' . md5((string)json_encode([
            'departure' => $departureCode,
            'arrival' => $arrivalCode,
            'date' => $date,
            'operator' => (string)($context['operator'] ?? ''),
            'departureLabel' => (string)($context['departureLabel'] ?? ''),
            'arrivalLabel' => (string)($context['arrivalLabel'] ?? ''),
            'depTime' => (string)($context['depTime'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
        $cached = Cache::read($cacheKey, 'default');
        $liveApisEnabled = (bool)Configure::read('External.useLiveApis');
        if (is_array($cached) && ($cached !== [] || !$liveApisEnabled)) {
            return $cached;
        }

        $items = [];
        foreach ($this->providers as $provider) {
            $providerItems = $provider->searchByRouteAndDate($departureCode, $arrivalCode, $date, $context);
            if ($providerItems === []) {
                continue;
            }
            foreach ($providerItems as $item) {
                $normalized = $this->normalizeItem($item, $departureCode, $arrivalCode, $context);
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
        $departureCode = $this->resolvePortCode(
            (string)($form['dep_station_lookup_code'] ?? ''),
            (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''))
        );
        $arrivalCode = $this->resolvePortCode(
            (string)($form['arr_station_lookup_code'] ?? ''),
            (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''))
        );
        $date = trim((string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')));

        return $this->search($departureCode, $arrivalCode, $date, [
            'operator' => (string)($form['operator'] ?? ($form['incident_segment_operator'] ?? '')),
            'departureLabel' => (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
            'arrivalLabel' => (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
            'depTime' => (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')),
            'arrTime' => (string)($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')),
            'vesselName' => (string)($form['ferry_vessel_name'] ?? ''),
        ]);
    }

    private function resolvePortCode(string $lookupCode, string $label): string
    {
        $lookupCode = strtoupper(trim($lookupCode));
        if ($lookupCode !== '') {
            return $lookupCode;
        }

        $label = strtoupper(trim($label));
        if (preg_match('/\b([A-Z]{5})\b/', $label, $m)) {
            return (string)$m[1];
        }

        return '';
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    private function normalizeItem(array $item, string $departureCode, string $arrivalCode, array $context): ?array
    {
        $departureItemCode = strtoupper(trim((string)($item['departure_port_code'] ?? '')));
        $arrivalItemCode = strtoupper(trim((string)($item['arrival_port_code'] ?? '')));
        $departureName = trim((string)($item['departure_port_name'] ?? ''));
        $arrivalName = trim((string)($item['arrival_port_name'] ?? ''));
        $requestedDepartureLabel = trim((string)($context['departureLabel'] ?? ''));
        $requestedArrivalLabel = trim((string)($context['arrivalLabel'] ?? ''));

        if ($departureCode !== '' && $departureItemCode !== '' && $departureCode !== $departureItemCode) {
            return null;
        }
        if ($arrivalCode !== '' && $arrivalItemCode !== '' && $arrivalCode !== $arrivalItemCode) {
            return null;
        }
        if (
            $departureCode === ''
            && $requestedDepartureLabel !== ''
            && $departureName !== ''
            && !$this->samePortLabel($requestedDepartureLabel, $departureName)
        ) {
            return null;
        }
        if (
            $arrivalCode === ''
            && $requestedArrivalLabel !== ''
            && $arrivalName !== ''
            && !$this->samePortLabel($requestedArrivalLabel, $arrivalName)
        ) {
            return null;
        }

        return [
            'departure_key' => trim((string)($item['departure_key'] ?? '')) !== ''
                ? (string)$item['departure_key']
                : md5(implode('|', [
                    $departureItemCode !== '' ? $departureItemCode : $departureName,
                    $arrivalItemCode !== '' ? $arrivalItemCode : $arrivalName,
                    (string)($item['scheduled_departure_local'] ?? ''),
                    (string)($item['vessel_name'] ?? ''),
                    (string)($item['operator_name'] ?? ''),
                ])),
            'operator_name' => trim((string)($item['operator_name'] ?? '')),
            'vessel_name' => trim((string)($item['vessel_name'] ?? '')),
            'vessel_imo' => trim((string)($item['vessel_imo'] ?? '')),
            'vessel_mmsi' => trim((string)($item['vessel_mmsi'] ?? '')),
            'departure_port_code' => $departureItemCode !== '' ? $departureItemCode : $departureCode,
            'arrival_port_code' => $arrivalItemCode !== '' ? $arrivalItemCode : $arrivalCode,
            'departure_port_name' => $departureName !== '' ? $departureName : $requestedDepartureLabel,
            'arrival_port_name' => $arrivalName !== '' ? $arrivalName : $requestedArrivalLabel,
            'scheduled_departure_local' => $this->normalizeDateTime((string)($item['scheduled_departure_local'] ?? '')),
            'scheduled_arrival_local' => $this->normalizeDateTime((string)($item['scheduled_arrival_local'] ?? '')),
            'estimated_departure_local' => $this->normalizeDateTime((string)($item['estimated_departure_local'] ?? '')),
            'estimated_arrival_local' => $this->normalizeDateTime((string)($item['estimated_arrival_local'] ?? '')),
            'actual_departure_local' => $this->normalizeDateTime((string)($item['actual_departure_local'] ?? '')),
            'actual_arrival_local' => $this->normalizeDateTime((string)($item['actual_arrival_local'] ?? '')),
            'live_position_reported_local' => $this->normalizeDateTime((string)($item['live_position_reported_local'] ?? '')),
            'live_position_lat' => is_numeric($item['live_position_lat'] ?? null) ? (float)$item['live_position_lat'] : null,
            'live_position_lon' => is_numeric($item['live_position_lon'] ?? null) ? (float)$item['live_position_lon'] : null,
            'live_speed_knots' => is_numeric($item['live_speed_knots'] ?? null) ? (float)$item['live_speed_knots'] : null,
            'live_destination' => trim((string)($item['live_destination'] ?? '')),
            'status' => trim((string)($item['status'] ?? '')),
            'source' => trim((string)($item['source'] ?? 'unknown')),
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
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d\TH:i:s', $timestamp);
    }

    private function samePortLabel(string $left, string $right): bool
    {
        return $this->normalizePortLabel($left) === $this->normalizePortLabel($right);
    }

    private function normalizePortLabel(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\b(port|harbour|harbor|terminal|faergehavn|ferry terminal)\b/ui', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<int,FerryDepartureListProviderInterface>
     */
    private function buildProviderChain(): array
    {
        $providers = [];
        if ((bool)Configure::read('External.useLiveApis')) {
            $marineTraffic = new MarineTrafficFerryDepartureProvider();
            if ($marineTraffic->isConfigured()) {
                $providers[] = $marineTraffic;
            }
        }

        $providers[] = new SeededFerryDepartureListProvider();

        return $providers;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function dedupeAndSort(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $key = implode('|', [
                (string)($item['departure_port_code'] ?? ($item['departure_port_name'] ?? '')),
                (string)($item['arrival_port_code'] ?? ($item['arrival_port_name'] ?? '')),
                (string)($item['scheduled_departure_local'] ?? ''),
                (string)($item['vessel_name'] ?? ''),
            ]);
            if (!isset($groups[$key])) {
                $groups[$key] = $item;
                continue;
            }

            $existing = $groups[$key];
            if (trim((string)($existing['vessel_imo'] ?? '')) === '' && trim((string)($item['vessel_imo'] ?? '')) !== '') {
                $groups[$key] = $item;
            }
        }

        $out = array_values($groups);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['scheduled_departure_local'] ?? ''), (string)($b['scheduled_departure_local'] ?? ''));
        });

        return $out;
    }
}
