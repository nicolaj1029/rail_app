<?php
declare(strict_types=1);

namespace App\Service\Rail;

use Cake\Cache\Cache;

final class RailStationLookupService
{
    private HafasRailLocationProvider $hafasProvider;
    private RailTransportServiceClient $transportClient;
    private RailDepartureNormalizer $normalizer;

    /** @var array<int,array<string,mixed>> */
    private array $majorStationCatalog;

    public function __construct(
        ?HafasRailLocationProvider $hafasProvider = null,
        ?RailDepartureNormalizer $normalizer = null,
        ?array $majorStationCatalog = null,
        ?RailTransportServiceClient $transportClient = null
    ) {
        $this->normalizer = $normalizer ?? new RailDepartureNormalizer();
        $this->hafasProvider = $hafasProvider ?? new HafasRailLocationProvider(null, $this->normalizer);
        $this->transportClient = $transportClient ?? new RailTransportServiceClient();
        $this->majorStationCatalog = $majorStationCatalog ?? (array)include CONFIG . 'rail' . DS . 'major_station_catalog.php';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, ?string $country = null, int $limit = 8): array
    {
        $query = $this->normalizer->normalizeStationQuery($query);
        if ($query === '' || mb_strlen($query, 'UTF-8') < 2) {
            return [];
        }

        $country = $country !== null ? strtoupper(trim($country)) : null;
        if ($country === '') {
            $country = null;
        }
        $limit = max(1, min(8, $limit));

        $cacheKey = 'rail_station_lookup_v5_' . md5(json_encode([$query, $country, $limit], JSON_UNESCAPED_UNICODE));
        $cached = Cache::read($cacheKey, 'default');
        if (is_array($cached)) {
            return $cached;
        }

        $candidates = $this->searchTransportServiceCandidates($query, $country, $limit);
        if ($candidates === []) {
            foreach ($this->hafasProvider->searchLocations($query, $country, $limit) as $candidate) {
                if (!$this->shouldKeepHafasCandidate((array)$candidate)) {
                    continue;
                }
                $candidates[] = $this->decorateHafasCandidate((array)$candidate, $query, $country);
            }
        }

        usort($candidates, fn(array $a, array $b): int => $this->compareCandidates($a, $b));
        $candidates = $this->dedupeAndLimit($candidates, $limit);
        if ($candidates === []) {
            $candidates = $this->searchMajorStationCatalog($query, $country, $limit);
        }

        if ($candidates !== []) {
            Cache::write($cacheKey, $candidates, 'default');
        }

        return $candidates;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchTransportServiceCandidates(string $query, ?string $country, int $limit): array
    {
        if (!$this->transportClient->isConfigured()) {
            return [];
        }

        $out = [];
        foreach ($this->transportClient->searchStations($query, $limit, 'da-DK') as $candidate) {
            $normalized = $this->normalizeTransportServiceCandidate((array)$candidate, $query, $country);
            if ($normalized === null) {
                continue;
            }
            $out[] = $this->decorateHafasCandidate($normalized, $query, $country);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function shouldKeepHafasCandidate(array $candidate): bool
    {
        $type = strtolower(trim((string)($candidate['hafas_type'] ?? $candidate['type'] ?? '')));
        $hasRail = !empty($candidate['rail']);
        $busOnly = !empty($candidate['bus_only']);
        $tramOnly = !empty($candidate['tram_only']);
        $metroOnly = !empty($candidate['metro_only']);

        if ($busOnly || $tramOnly || $metroOnly) {
            return false;
        }

        return $type === 'station' || $type === 'stop' || $hasRail;
    }

    /**
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>|null
     */
    private function normalizeTransportServiceCandidate(array $candidate, string $query, ?string $country): ?array
    {
        $id = trim((string)($candidate['id'] ?? ''));
        $name = trim((string)($candidate['name'] ?? ''));
        if ($id === '' || $name === '') {
            return null;
        }

        $type = strtolower(trim((string)($candidate['type'] ?? 'station')));
        if (!in_array($type, ['station', 'stop'], true)) {
            return null;
        }

        $canonicalName = (string)($this->normalizer->canonicalizeStationName($name) ?? $name);
        $source = strtolower(trim((string)($candidate['source'] ?? 'transport_rest')));
        $isCatalog = in_array($source, ['catalog', 'major_station_catalog'], true);
        $resolvedId = $id;
        if ($isCatalog) {
            $catalogEntry = $this->findCatalogEntry($canonicalName) ?? $this->findCatalogEntry($query);
            $catalogId = trim((string)($catalogEntry['transport_rest_id'] ?? ''));
            if ($catalogId !== '') {
                $resolvedId = $catalogId;
            }
        }
        $countryCode = strtoupper(trim((string)($candidate['country'] ?? '')));
        if ($countryCode === '') {
            $countryCode = null;
        }

        return [
            'id' => $resolvedId,
            'osm_id' => $resolvedId,
            'name' => $canonicalName,
            'raw_name' => $name,
            'type' => 'station',
            'hafas_type' => $type,
            'country' => $countryCode,
            'lat' => isset($candidate['lat']) && is_numeric($candidate['lat']) ? (float)$candidate['lat'] : null,
            'lon' => isset($candidate['lon']) && is_numeric($candidate['lon']) ? (float)$candidate['lon'] : null,
            'source' => $isCatalog ? 'transport_catalog' : 'hafas',
            'rail' => true,
            'bus_only' => false,
            'tram_only' => false,
            'metro_only' => false,
            'regional_or_international' => true,
            'suburban_only' => false,
            'minor_halt' => false,
            'has_central_marker' => $this->normalizer->hasCentralStationMarker($canonicalName),
            'country_match' => $country !== null && $countryCode === $country,
            'exact_alias_match' => $this->normalizer->stationsMatch($canonicalName, null, $query),
            'has_live_departures' => $isCatalog ? null : true,
        ];
    }

    /**
     * @param array<string,mixed> $candidate
     * @return array<string,mixed>
     */
    private function decorateHafasCandidate(array $candidate, string $query, ?string $country): array
    {
        $name = trim((string)($candidate['name'] ?? ''));
        $priorityScore = $this->bestStationTextScore($query, $name);
        if (!empty($candidate['exact_alias_match'])) {
            $priorityScore += 100;
        }
        if (!empty($candidate['has_central_marker'])) {
            $priorityScore += 80;
        }
        if (!empty($candidate['regional_or_international'])) {
            $priorityScore += 60;
        }
        if (($candidate['has_live_departures'] ?? null) === true) {
            $priorityScore += 40;
        }
        if (!empty($candidate['country_match']) || $this->looksLikeCityMatch($query, $name, $country, (string)($candidate['country'] ?? ''))) {
            $priorityScore += 20;
        }
        if (!empty($candidate['minor_halt'])) {
            $priorityScore -= 40;
        }
        if (ctype_digit(trim((string)($candidate['id'] ?? '')))) {
            $priorityScore += 10;
        }

        $source = strtolower(trim((string)($candidate['source'] ?? '')));
        if (in_array($source, ['hafas', 'transport_rest'], true)) {
            $priorityScore += 15;
        } elseif (in_array($source, ['transport_catalog', 'catalog', 'major_station_catalog'], true)) {
            $priorityScore -= 10;
        }

        $candidate['priority_score'] = $priorityScore;
        $candidate['confidence'] = $this->scoreToConfidence($priorityScore);
        $candidate['type'] = strtolower(trim((string)($candidate['type'] ?? ''))) === 'station'
            ? 'station'
            : (!empty($candidate['regional_or_international']) ? 'station' : 'halt');

        return $candidate;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function searchMajorStationCatalog(string $query, ?string $country, int $limit): array
    {
        $scored = [];
        foreach ($this->majorStationCatalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $canonical = trim((string)($entry['canonical_name'] ?? ''));
            if ($canonical === '') {
                continue;
            }

            $score = $this->bestStationTextScore($query, $canonical);
            if ($this->normalizer->stationsMatch($canonical, null, $query)) {
                $score += 100;
            }
            if ($this->normalizer->hasCentralStationMarker($canonical)) {
                $score += 80;
            }
            $score += 60;
            if ($country !== null && strtoupper((string)($entry['country'] ?? '')) === $country) {
                $score += 20;
            }
            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'id' => trim((string)($entry['transport_rest_id'] ?? $canonical)),
                'osm_id' => trim((string)($entry['transport_rest_id'] ?? $canonical)),
                'name' => $canonical,
                'type' => 'station',
                'country' => strtoupper(trim((string)($entry['country'] ?? ''))) ?: null,
                'lat' => isset($entry['lat']) && is_numeric($entry['lat']) ? (float)$entry['lat'] : null,
                'lon' => isset($entry['lon']) && is_numeric($entry['lon']) ? (float)$entry['lon'] : null,
                'source' => 'major_station_catalog',
                'rail' => true,
                'priority_score' => $score,
                'confidence' => $this->scoreToConfidence($score),
            ];
        }

        usort($scored, fn(array $a, array $b): int => $this->compareCandidates($a, $b));

        return $this->dedupeAndLimit($scored, $limit);
    }

    private function textScore(string $query, string $candidate): int
    {
        $needle = $this->normalizer->stationLookupKey($query);
        $candidateKey = $this->normalizer->stationLookupKey($candidate);
        if ($needle === '' || $candidateKey === '') {
            return 0;
        }

        if ($candidateKey === $needle) {
            return 130;
        }
        if (str_starts_with($candidateKey, $needle)) {
            return 90;
        }
        if (strpos($candidateKey, $needle) !== false) {
            return 60;
        }

        $queryWords = array_values(array_filter(explode(' ', $needle), static fn(string $word): bool => $word !== ''));
        if ($queryWords === []) {
            return 0;
        }

        $hits = 0;
        foreach ($queryWords as $word) {
            if (strpos($candidateKey, $word) !== false) {
                $hits++;
            }
        }

        return $hits === count($queryWords) ? 36 + min(20, $hits * 6) : 0;
    }

    private function bestStationTextScore(string $query, string $canonicalName): int
    {
        $best = $this->textScore($query, $canonicalName);
        foreach ($this->normalizer->aliasesForStation($canonicalName) as $alias) {
            $best = max($best, $this->textScore($query, $alias));
        }

        return $best;
    }

    private function looksLikeCityMatch(string $query, string $candidateName, ?string $requestedCountry, string $candidateCountry): bool
    {
        if ($requestedCountry !== null && strtoupper($candidateCountry) === $requestedCountry) {
            return true;
        }

        $queryKey = $this->normalizer->stationLookupKey($query);
        $nameKey = $this->normalizer->stationLookupKey($candidateName);
        if ($queryKey === '' || $nameKey === '') {
            return false;
        }

        return str_starts_with($nameKey, $queryKey) || strpos($nameKey, $queryKey) !== false;
    }

    private function scoreToConfidence(int $score): float
    {
        if ($score >= 260) {
            return 0.96;
        }
        if ($score >= 200) {
            return 0.9;
        }
        if ($score >= 150) {
            return 0.82;
        }
        if ($score >= 100) {
            return 0.74;
        }

        return 0.64;
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    private function compareCandidates(array $a, array $b): int
    {
        $scoreCompare = ((int)($b['priority_score'] ?? 0)) <=> ((int)($a['priority_score'] ?? 0));
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        $sourceCompare = $this->sourceRank((string)($b['source'] ?? '')) <=> $this->sourceRank((string)($a['source'] ?? ''));
        if ($sourceCompare !== 0) {
            return $sourceCompare;
        }

        $idCompare = (int)ctype_digit(trim((string)($b['id'] ?? ''))) <=> (int)ctype_digit(trim((string)($a['id'] ?? '')));
        if ($idCompare !== 0) {
            return $idCompare;
        }

        return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    }

    private function sourceRank(string $source): int
    {
        return match (strtolower(trim($source))) {
            'hafas', 'transport_rest' => 3,
            'transport_catalog', 'catalog' => 2,
            'major_station_catalog' => 1,
            default => 0,
        };
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findCatalogEntry(string $query): ?array
    {
        $needle = $this->normalizer->stationLookupKey($query);
        if ($needle === '') {
            return null;
        }

        foreach ($this->majorStationCatalog as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $canonical = trim((string)($entry['canonical_name'] ?? ''));
            if ($canonical !== '' && $this->normalizer->stationLookupKey($canonical) === $needle) {
                return $entry;
            }

            foreach ($this->normalizer->aliasesForStation($canonical) as $alias) {
                if ($this->normalizer->stationLookupKey($alias) === $needle) {
                    return $entry;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function dedupeAndLimit(array $candidates, int $limit): array
    {
        $out = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $key = strtolower(trim((string)($candidate['id'] ?? '')));
            if ($key === '') {
                $key = strtolower($this->normalizer->stationLookupKey((string)($candidate['name'] ?? '')));
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $candidate;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
