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

        $cacheKey = 'rail_departures_v3_' . $this->buildSearchCacheKey($criteria);
        $cached = Cache::read($cacheKey, 'default');
        $onlineProvidersEnabled = $this->hasOnlineProvidersEnabled();
        if (is_array($cached) && ($cached !== [] || !$onlineProvidersEnabled)) {
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
        if ($items !== [] || !$onlineProvidersEnabled) {
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
        return $this->search($this->buildCriteriaFromForm($form, $meta));
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function buildCriteriaFromForm(array $form, array $meta = []): array
    {
        return [
            'from_station' => (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')),
            'from_station_id' => (string)($form['dep_station_lookup_id'] ?? ''),
            'to_station' => (string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')),
            'to_station_id' => (string)($form['arr_station_lookup_id'] ?? ''),
            'date' => (string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')),
            'time' => (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')),
            'operator_hint' => (string)($form['operator'] ?? ($form['incident_segment_operator'] ?? ($meta['_auto']['operator']['value'] ?? ''))),
            'train_number_hint' => (string)($form['train_no'] ?? ($form['ticket_no'] ?? '')),
            'locale' => 'da-DK',
        ];
    }

    /**
     * Search-signature for candidate lookup lifecycle.
     *
     * Intentionally excludes operator/train hints because they should not
     * invalidate an already fetched HAFAS candidate set for the same route/date/time.
     *
     * @param array<string,mixed> $criteria
     */
    public function buildLookupSignature(array $criteria): string
    {
        $criteria = $this->normalizeCriteria($criteria);

        return md5(json_encode([
            'from_station' => (string)($criteria['from_station'] ?? ''),
            'from_station_id' => (string)($criteria['from_station_id'] ?? ''),
            'to_station' => (string)($criteria['to_station'] ?? ''),
            'to_station_id' => (string)($criteria['to_station_id'] ?? ''),
            'date' => (string)($criteria['date'] ?? ''),
            'time' => (string)($criteria['time'] ?? ''),
            'locale' => (string)($criteria['locale'] ?? 'da-DK'),
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     */
    public function buildLookupSignatureFromForm(array $form, array $meta = []): string
    {
        return $this->buildLookupSignature($this->buildCriteriaFromForm($form, $meta));
    }

    /**
     * @param array<string,mixed> $criteria
     * @return array<string,mixed>
     */
    private function normalizeCriteria(array $criteria): array
    {
        $fromStation = trim((string)($criteria['from_station'] ?? ''));
        $toStation = trim((string)($criteria['to_station'] ?? ''));

        return [
            'from_station' => $this->normalizer->canonicalizeStationName($fromStation) ?? $fromStation,
            'from_station_id' => trim((string)($criteria['from_station_id'] ?? '')),
            'to_station' => $this->normalizer->canonicalizeStationName($toStation) ?? $toStation,
            'to_station_id' => trim((string)($criteria['to_station_id'] ?? '')),
            'date' => $this->normalizeDate((string)($criteria['date'] ?? '')),
            'time' => $this->normalizeTime((string)($criteria['time'] ?? '')),
            'operator_hint' => trim((string)($criteria['operator_hint'] ?? '')),
            'train_number_hint' => trim((string)($criteria['train_number_hint'] ?? '')),
            'locale' => trim((string)($criteria['locale'] ?? 'da-DK')),
        ];
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})\.(\d{2})$/', $value, $m) === 1) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('H:i', $timestamp);
    }

    /**
     * @return array<int,RailDepartureProviderInterface>
     */
    private function buildProviderChain(): array
    {
        $providers = [];
        $transportClient = new RailTransportServiceClient();
        if ($transportClient->isConfigured()) {
            $providers[] = new RailTransportServiceProvider();
        }
        if ((bool)Configure::read('External.useLiveApis', false) && Configure::read('Rail.hafas.enabled', true) !== false) {
            $providers[] = new HafasRailDepartureProvider(null, $this->normalizer);
        }
        $providers[] = new MockRailDepartureProvider(null, $this->normalizer);
        if ((bool)Configure::read('Rail.rneTis.enabled', false)) {
            $providers[] = new RneTisRailDepartureProvider();
        }

        return $providers;
    }

    private function hasOnlineProvidersEnabled(): bool
    {
        if ((new RailTransportServiceClient())->isConfigured()) {
            return true;
        }

        return (bool)Configure::read('External.useLiveApis', false) && Configure::read('Rail.hafas.enabled', true) !== false;
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

    /**
     * @param array<string,mixed> $criteria
     */
    private function buildSearchCacheKey(array $criteria): string
    {
        return md5(json_encode($criteria, JSON_UNESCAPED_UNICODE));
    }
}
