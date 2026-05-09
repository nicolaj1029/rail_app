<?php
declare(strict_types=1);

namespace App\Service\Rail;

final class MockRailDepartureProvider implements RailDepartureProviderInterface
{
    /** @var array<int,array<string,mixed>> */
    private array $items;

    public function __construct(
        ?array $items = null,
        private ?RailDepartureNormalizer $normalizer = null
    ) {
        $this->items = $items ?? (array)include CONFIG . 'rail' . DS . 'mock_departures.php';
        $this->normalizer ??= new RailDepartureNormalizer();
    }

    public function search(array $criteria): array
    {
        $date = trim((string)($criteria['date'] ?? ''));
        if ($date === '') {
            return [];
        }

        $out = [];
        foreach ($this->items as $item) {
            $candidate = $this->normalizer->normalize((array)$item);
            if (!$this->matchesCriteria($candidate, $criteria, $date)) {
                continue;
            }
            $out[] = $candidate;
        }

        usort($out, static fn(array $a, array $b): int => strcmp((string)($a['planned_departure_at'] ?? ''), (string)($b['planned_departure_at'] ?? '')));

        return $out;
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $criteria
     */
    private function matchesCriteria(array $candidate, array $criteria, string $date): bool
    {
        $plannedDate = substr((string)($candidate['planned_departure_at'] ?? ''), 0, 10);
        if ($plannedDate !== $date) {
            return false;
        }

        $fromQuery = (string)($criteria['from_station'] ?? '');
        $toQuery = (string)($criteria['to_station'] ?? '');
        if ($fromQuery !== '' && !$this->normalizer->stationsMatch(
            (string)($candidate['origin_station_name'] ?? ''),
            (string)($candidate['origin_station_code'] ?? ''),
            $fromQuery
        )) {
            return false;
        }
        if ($toQuery !== '' && !$this->normalizer->stationsMatch(
            (string)($candidate['destination_station_name'] ?? ''),
            (string)($candidate['destination_station_code'] ?? ''),
            $toQuery
        )) {
            return false;
        }

        $trainHint = trim((string)($criteria['train_number_hint'] ?? ''));
        if ($trainHint !== '' && stripos((string)($candidate['train_number'] ?? ''), $trainHint) === false) {
            return false;
        }

        $operatorHint = trim((string)($criteria['operator_hint'] ?? ''));
        if ($operatorHint !== '' && !$this->normalizer->operatorMatches(
            (string)($candidate['operator_code'] ?? ''),
            (string)($candidate['operator_name'] ?? ''),
            $operatorHint
        )) {
            return false;
        }

        return true;
    }
}
