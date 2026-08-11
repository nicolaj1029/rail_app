<?php
declare(strict_types=1);

namespace App\Service\Air;

use App\Service\TransportNodeSearchService;

final class AirPassengerExpenseLocationResolver
{
    private TransportNodeSearchService $search;

    public function __construct(?TransportNodeSearchService $search = null)
    {
        $this->search = $search ?? new TransportNodeSearchService();
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function resolve(array $context): array
    {
        $form = (array)($context['form'] ?? []);
        $meta = (array)($context['meta'] ?? []);
        $incident = (array)($context['incident'] ?? []);
        $scope = strtolower(trim((string)($context['scope'] ?? ($context['action'] ?? ''))));
        $routeLegs = $this->routeLegsFromContext($form, $meta);
        $selectedLeg = $this->selectedLegFromContext($form, $meta, $routeLegs);
        $incidentMain = strtolower(trim((string)($form['incident_main'] ?? ($incident['main'] ?? ''))));
        $protectedMissed = $this->toBool($form['protected_connection_missed'] ?? null) === true
            || $incidentMain === 'missed_connection';
        $missedConnectionStation = trim((string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? '')));
        $currentAnchor = (array)($meta['air_current_location_anchor'] ?? []);

        if (in_array($scope, ['air_reroute_scope', 'air_refund_scope', 'remedies_reroute', 'remedies_refund', 'reroute', 'refund'], true)) {
            $remedyLocation = $this->resolveFromRemedyCurrentAirport($form);
            if ($remedyLocation !== []) {
                return $remedyLocation;
            }
            $anchorLocation = $this->resolveFromCurrentAnchor($currentAnchor);
            if ($anchorLocation !== []) {
                return $anchorLocation;
            }
        } elseif ($scope === 'air_assistance_scope') {
            $anchorLocation = $this->resolveFromCurrentAnchor($currentAnchor, ['remedy_current_airport']);
            if ($anchorLocation !== []) {
                return $anchorLocation;
            }
        }

        if ($protectedMissed && $missedConnectionStation !== '') {
            $match = $this->lookupAirport('', $missedConnectionStation);
            return $this->buildResolvedLocation(
                $match['code'] ?? '',
                $match['country'] ?? '',
                $match['name'] ?? $missedConnectionStation,
                'incident_missed_connection_station',
                $match !== [] ? 'high' : 'medium',
                $match === []
            );
        }

        if ($selectedLeg !== []) {
            if ($protectedMissed) {
                return $this->resolveFromLegEdge(
                    $selectedLeg,
                    'arr',
                    'inferred_from_selected_leg_connection_arrival',
                    false
                );
            }

            if (in_array($incidentMain, ['delay', 'cancellation', 'denied_boarding'], true)) {
                return $this->resolveFromLegEdge(
                    $selectedLeg,
                    'dep',
                    'inferred_from_problem_segment_and_incident',
                    false
                );
            }
        }

        $selectedFlight = (array)($meta['air_selected_flight'] ?? []);
        if ($selectedFlight !== []) {
            if ($protectedMissed) {
                return $this->resolveFromAirportCandidate(
                    (string)($selectedFlight['arrival_airport_iata'] ?? ''),
                    '',
                    'fallback_selected_flight_arrival_airport',
                    'medium',
                    true
                );
            }

            return $this->resolveFromAirportCandidate(
                (string)($selectedFlight['departure_airport_iata'] ?? ''),
                '',
                'fallback_selected_flight_departure_airport',
                'medium',
                true
            );
        }

        if (!$protectedMissed) {
            $depCode = (string)($form['dep_station_lookup_code'] ?? '');
            $depCountry = (string)($form['dep_station_lookup_country'] ?? '');
            $depLabel = (string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''));

            return $this->resolveFromAirportCandidate(
                $depCode,
                $depLabel,
                'fallback_departure_airport',
                $depCode !== '' ? 'medium' : 'low',
                true,
                $depCountry
            );
        }

        if ($routeLegs !== []) {
            $firstNonLastLeg = [];
            foreach ($routeLegs as $leg) {
                if (!is_array($leg) || !empty($leg['is_last_leg'])) {
                    continue;
                }
                $firstNonLastLeg = $leg;
                break;
            }
            if ($firstNonLastLeg !== []) {
                return $this->resolveFromLegEdge(
                    $firstNonLastLeg,
                    'arr',
                    'fallback_first_connection_airport',
                    true
                );
            }
        }

        return [
            'expense_airport_iata' => '',
            'expense_country_code' => '',
            'expense_airport_label' => '',
            'location_source' => 'unresolved',
            'location_confidence' => 'low',
            'fallback_used' => true,
        ];
    }

    /**
     * @param array<string,mixed> $leg
     * @return array<string,mixed>
     */
    private function resolveFromLegEdge(array $leg, string $edge, string $source, bool $fallback): array
    {
        $code = (string)($leg[$edge . '_iata'] ?? '');
        $label = (string)($leg[$edge . '_label'] ?? '');

        return $this->resolveFromAirportCandidate(
            $code,
            $label,
            $source,
            $code !== '' ? 'high' : 'medium',
            $fallback
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function resolveFromAirportCandidate(
        string $iata,
        string $label,
        string $source,
        string $confidence,
        bool $fallback,
        string $country = ''
    ): array {
        $match = $this->lookupAirport($iata, $label);

        return $this->buildResolvedLocation(
            $match['code'] ?? $iata,
            $match['country'] ?? $country,
            $match['name'] ?? $label,
            $source,
            $match !== [] ? $confidence : ($iata !== '' || $label !== '' ? 'medium' : 'low'),
            $fallback || $match === []
        );
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $meta
     * @return array<int,array<string,mixed>>
     */
    private function routeLegsFromContext(array $form, array $meta): array
    {
        $routeLegs = array_values(array_filter((array)($meta['air_route_legs'] ?? []), 'is_array'));
        if ($routeLegs !== []) {
            return $routeLegs;
        }

        $depLabel = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
        $arrLabel = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
        if ($depLabel === '' || $arrLabel === '') {
            return [];
        }

        $stops = preg_split('/[\r\n,]+/', trim((string)($form['air_stopover_airports'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $points = array_merge([$depLabel], array_values(array_map('trim', $stops)), [$arrLabel]);
        $legs = [];
        for ($i = 0; $i < count($points) - 1; $i++) {
            $from = trim((string)$points[$i]);
            $to = trim((string)$points[$i + 1]);
            if ($from === '' || $to === '') {
                continue;
            }
            $depMatch = $this->lookupAirport($i === 0 ? (string)($form['dep_station_lookup_code'] ?? '') : '', $from);
            $arrMatch = $this->lookupAirport(($i + 1) === count($points) - 1 ? (string)($form['arr_station_lookup_code'] ?? '') : '', $to);
            $legs[] = [
                'key' => 'leg_' . ($i + 1),
                'dep_label' => $from,
                'arr_label' => $to,
                'dep_iata' => (string)($depMatch['code'] ?? ''),
                'arr_iata' => (string)($arrMatch['code'] ?? ''),
                'is_last_leg' => ($i + 2) === count($points),
            ];
        }

        return $legs;
    }

    /**
     * @param array<int,array<string,mixed>> $routeLegs
     * @return array<string,mixed>
     */
    private function selectedLegFromContext(array $form, array $meta, array $routeLegs): array
    {
        $selectedLeg = (array)($meta['air_selected_leg'] ?? []);
        if ($selectedLeg !== []) {
            return $selectedLeg;
        }

        $legKey = trim((string)($form['air_disruption_leg_id'] ?? ($form['air_affected_leg_key'] ?? ($meta['air_disruption_leg_id'] ?? ''))));
        if ($legKey === '') {
            return count($routeLegs) === 1 ? (array)($routeLegs[0] ?? []) : [];
        }

        foreach ($routeLegs as $leg) {
            if (is_array($leg) && (string)($leg['key'] ?? '') === $legKey) {
                return $leg;
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function resolveFromRemedyCurrentAirport(array $form): array
    {
        $currentAirport = trim((string)($form['a18_from_station'] ?? ''));
        if ($currentAirport === 'other') {
            $currentAirport = trim((string)($form['a18_from_station_other'] ?? ''));
        }
        if ($currentAirport === '' || $currentAirport === 'unknown') {
            return [];
        }

        $country = trim((string)($form['a18_from_station_lookup_country'] ?? ''));
        if ($country === '') {
            $country = trim((string)($form['a18_from_station_other_country'] ?? ''));
        }

        return $this->resolveFromAirportCandidate(
            '',
            $currentAirport,
            'remedy_current_airport',
            'high',
            false,
            $country
        );
    }

    /**
     * @param array<string,mixed> $anchor
     * @param array<int,string>|null $allowedSources
     * @return array<string,mixed>
     */
    private function resolveFromCurrentAnchor(array $anchor, ?array $allowedSources = null): array
    {
        $label = trim((string)($anchor['airport_label'] ?? ''));
        if ($label === '') {
            return [];
        }

        $source = trim((string)($anchor['source'] ?? 'air_current_location_anchor'));
        if ($allowedSources !== null && !in_array($source, $allowedSources, true)) {
            return [];
        }

        return $this->resolveFromAirportCandidate(
            (string)($anchor['airport_iata'] ?? ''),
            $label,
            $source,
            'high',
            false,
            (string)($anchor['country_code'] ?? '')
        );
    }

    /**
     * @return array<string,string>
     */
    private function lookupAirport(string $iata, string $label): array
    {
        $iata = strtoupper(trim($iata));
        $label = trim($label);
        $query = $iata !== '' ? $iata : $label;
        if ($query === '') {
            return [];
        }

        try {
            $matches = $this->search->search('air', $query, null, 5, 'airport', true);
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }
            if ($iata !== '' && strtoupper(trim((string)($match['code'] ?? ''))) === $iata) {
                return [
                    'code' => strtoupper(trim((string)($match['code'] ?? ''))),
                    'country' => strtoupper(trim((string)($match['country'] ?? ''))),
                    'name' => trim((string)($match['name'] ?? '')),
                ];
            }
        }

        $first = (array)($matches[0] ?? []);
        if ($first === []) {
            return [];
        }

        return [
            'code' => strtoupper(trim((string)($first['code'] ?? $iata))),
            'country' => strtoupper(trim((string)($first['country'] ?? ''))),
            'name' => trim((string)($first['name'] ?? $label)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildResolvedLocation(
        string $airportIata,
        string $countryCode,
        string $airportLabel,
        string $source,
        string $confidence,
        bool $fallback
    ): array {
        return [
            'expense_airport_iata' => strtoupper(trim($airportIata)),
            'expense_country_code' => strtoupper(trim($countryCode)),
            'expense_airport_label' => trim($airportLabel),
            'location_source' => $source,
            'location_confidence' => in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low',
            'fallback_used' => $fallback,
        ];
    }

    private function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower(trim((string)$value))) {
            '1', 'true', 'yes', 'ja', 'y' => true,
            '0', 'false', 'no', 'nej', 'n' => false,
            default => null,
        };
    }
}
