<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Cache\Cache;
use Cake\Core\Configure;

final class RailDepartureSearchService
{
    /** @var array<int,RailDepartureProviderInterface> */
    private array $providers;

    public function __construct(?array $providers = null, ?RailDepartureNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new RailDepartureNormalizer();
        $this->providers = $providers ?? $this->buildProviderChain();
    }

    private RailDepartureNormalizer $normalizer;

    /**
     * @param array<string,mixed> $criteria
     * @return array<int,array<string,mixed>>
     */
    public function search(array $criteria): array
    {
        $criteria = $this->normalizeCriteria($criteria);
        if ((string)($criteria['from_station'] ?? '') === '' || (string)($criteria['to_station'] ?? '') === '' || (string)($criteria['date'] ?? '') === '') {
            return [];
        }

        $cacheKey = 'rail_departures_v2_' . md5(json_encode($criteria, JSON_UNESCAPED_UNICODE));
        $cached = Cache::read($cacheKey, 'default');
        $liveApisEnabled = (bool)Configure::read('External.useLiveApis', false);
        if (is_array($cached) && ($cached !== [] || !$liveApisEnabled)) {
            return $cached;
        }

        $items = [];
        foreach ($this->providers as $provider) {
            try {
                $providerItems = $provider->search($criteria);
            } catch (\Throwable $e) {
                $providerItems = [];
            }
            if ($providerItems === []) {
                continue;
            }
            foreach ($providerItems as $item) {
                $items[] = $this->normalizer->normalize((array)$item);
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
        return $this->search([
            'from_station' => (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
            'to_station' => (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
            'date' => (string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')),
            'time' => (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')),
            'operator_hint' => (string)($form['operator'] ?? ($form['incident_segment_operator'] ?? ($meta['_auto']['operator']['value'] ?? ''))),
            'train_number_hint' => (string)($form['train_no'] ?? ($form['ticket_no'] ?? '')),
            'locale' => 'da-DK',
        ]);
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<string,mixed>
     */
    private function normalizeCriteria(array $criteria): array
    {
        return [
            'from_station' => trim((string)($criteria['from_station'] ?? '')),
            'to_station' => trim((string)($criteria['to_station'] ?? '')),
            'date' => trim((string)($criteria['date'] ?? '')),
            'time' => trim((string)($criteria['time'] ?? '')),
            'operator_hint' => trim((string)($criteria['operator_hint'] ?? '')),
            'train_number_hint' => trim((string)($criteria['train_number_hint'] ?? '')),
            'locale' => trim((string)($criteria['locale'] ?? 'da-DK')),
        ];
    }

    /**
     * @return array<int,RailDepartureProviderInterface>
     */
    private function buildProviderChain(): array
    {
        $providers = [];
        $transportServiceEnabled = (bool)Configure::read('Rail.transportServiceEnabled', false);
        if ($transportServiceEnabled) {
            $providers[] = new RailTransportServiceProvider();
        }
        if (!$transportServiceEnabled && (bool)Configure::read('External.useLiveApis', false) && Configure::read('Rail.hafas.enabled', true) !== false) {
            $providers[] = new HafasRailDepartureProvider(null, $this->normalizer);
        }
        $providers[] = new MockRailDepartureProvider(null, $this->normalizer);
        if ((bool)Configure::read('Rail.rneTis.enabled', false)) {
            $providers[] = new RneTisRailDepartureProvider();
        }

        return $providers;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function dedupeAndSort(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $key = (string)($item['id'] ?? md5(json_encode($item, JSON_UNESCAPED_UNICODE)));
            if (!isset($grouped[$key])) {
                $grouped[$key] = $item;
                continue;
            }
            if ((float)($item['confidence'] ?? 0.0) > (float)($grouped[$key]['confidence'] ?? 0.0)) {
                $grouped[$key] = $item;
            }
        }

        $out = array_values($grouped);
        usort($out, static fn(array $a, array $b): int => strcmp((string)($a['planned_departure_at'] ?? ''), (string)($b['planned_departure_at'] ?? '')));

        return $out;
    }
}
