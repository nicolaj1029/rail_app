<?php
declare(strict_types=1);

namespace App\Service;

final class TransportNodeDerivationService
{
    public function __construct(
        private ?TransportOperatorRegistry $operatorRegistry = null,
        private ?TransportNodeSearchService $nodeSearch = null
    ) {
        $this->operatorRegistry ??= new TransportOperatorRegistry();
        $this->nodeSearch ??= new TransportNodeSearchService();
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    public function derive(array $form): array
    {
        $mode = strtolower(trim((string)($form['transport_mode'] ?? 'rail')));
        if (!in_array($mode, ['ferry', 'bus', 'air'], true)) {
            return $form;
        }

        $form = $this->resolveLookupMeta($form, 'dep_station', $mode);
        $form = $this->resolveLookupMeta($form, 'arr_station', $mode);
        if ($mode === 'ferry') {
            $form = $this->autoFillFerryTerminalFromPort($form, 'dep_station', 'dep_terminal');
            $form = $this->autoFillFerryTerminalFromPort($form, 'arr_station', 'arr_terminal');
            $form = $this->resolveLookupMeta($form, 'dep_terminal', $mode);
            $form = $this->resolveLookupMeta($form, 'arr_terminal', $mode);
            $form = $this->deriveFerryPortFromTerminal($form, 'dep_station', 'dep_terminal');
            $form = $this->deriveFerryPortFromTerminal($form, 'arr_station', 'arr_terminal');
        }
        $dep = $this->lookupMeta($form, 'dep_station');
        $arr = $this->lookupMeta($form, 'arr_station');

        if (($form['incident_segment_mode'] ?? '') === '') {
            $form['incident_segment_mode'] = $mode;
        }

        if ($mode === 'ferry') {
            $depTerminal = $this->lookupMeta($form, 'dep_terminal');
            $arrTerminal = $this->lookupMeta($form, 'arr_terminal');
            $form = $this->deriveOperatorDefaults($form, 'ferry', ['operator', 'incident_segment_operator']);
            if ($dep['in_eu'] !== '') {
                $form['departure_port_in_eu'] = $dep['in_eu'];
            }
            if ($arr['in_eu'] !== '') {
                $form['arrival_port_in_eu'] = $arr['in_eu'];
            }
            if ($depTerminal['node_type'] !== '') {
                $form['departure_from_terminal'] = in_array($depTerminal['node_type'], ['ferry_terminal', 'terminal'], true) ? 'yes' : 'no';
            } elseif ($dep['node_type'] !== '') {
                $form['departure_from_terminal'] = in_array($dep['node_type'], ['ferry_terminal', 'terminal'], true) ? 'yes' : 'no';
            }
            $meters = $this->distanceMeters(
                $dep['lat'] !== null && $dep['lon'] !== null ? $dep : $depTerminal,
                $arr['lat'] !== null && $arr['lon'] !== null ? $arr : $arrTerminal
            );
            if ($meters !== null) {
                $form['route_distance_meters'] = (string)$meters;
            }
            if (($form['carrier_is_eu'] ?? '') === '') {
                $carrierIsEu = $this->operatorRegistry->deriveEuFlag('ferry', (string)($form['operator'] ?? ''));
                if ($carrierIsEu === null) {
                    $carrierIsEu = $this->operatorRegistry->deriveEuFlag('ferry', (string)($form['incident_segment_operator'] ?? ''));
                }
                if ($carrierIsEu === null) {
                    $carrierIsEu = $this->deriveEuFromCountry((string)($form['operator_country'] ?? ''));
                }
                if ($carrierIsEu !== null) {
                    $form['carrier_is_eu'] = $carrierIsEu ? 'yes' : 'no';
                }
            }

            return $form;
        }

        if ($mode === 'bus') {
            $form = $this->deriveOperatorDefaults($form, 'bus', ['operator', 'incident_segment_operator']);
            $operatorCountry = strtoupper(trim((string)($form['operator_country'] ?? '')));
            if ($dep['in_eu'] !== '') {
                $form['boarding_in_eu'] = $dep['in_eu'];
            } elseif (($form['boarding_in_eu'] ?? '') === '') {
                $depEu = $this->deriveEuFromCountry((string)($dep['country'] ?? ''));
                if ($depEu === null && $operatorCountry !== '') {
                    $depEu = $this->deriveEuFromCountry($operatorCountry);
                }
                if ($depEu !== null) {
                    $form['boarding_in_eu'] = $depEu ? 'yes' : 'no';
                }
            }
            if ($arr['in_eu'] !== '') {
                $form['alighting_in_eu'] = $arr['in_eu'];
            } elseif (($form['alighting_in_eu'] ?? '') === '') {
                $arrEu = $this->deriveEuFromCountry((string)($arr['country'] ?? ''));
                if ($arrEu !== null) {
                    $form['alighting_in_eu'] = $arrEu ? 'yes' : 'no';
                }
            }
            if ($dep['node_type'] !== '') {
                $form['departure_from_terminal'] = in_array($dep['node_type'], ['terminal', 'bus_terminal'], true) ? 'yes' : 'no';
            }
            $meters = $this->distanceMeters($dep, $arr);
            if ($meters !== null) {
                $form['scheduled_distance_km'] = (string)max(1, (int)round($meters / 1000));
            }

            return $form;
        }

        $form = $this->deriveOperatorDefaults($form, 'air', ['operating_carrier', 'marketing_carrier', 'operator', 'incident_segment_operator']);
        if ($dep['in_eu'] !== '') {
            $form['departure_airport_in_eu'] = $dep['in_eu'];
        }
        if ($arr['in_eu'] !== '') {
            $form['arrival_airport_in_eu'] = $arr['in_eu'];
        }
        $opIsEu = $this->operatorRegistry->deriveEuFlag('air', (string)($form['operating_carrier'] ?? ''));
        if ($opIsEu !== null) {
            $form['operating_carrier_is_eu'] = $opIsEu ? 'yes' : 'no';
        }
        $marketingIsEu = $this->operatorRegistry->deriveEuFlag('air', (string)($form['marketing_carrier'] ?? ''));
        if ($marketingIsEu !== null) {
            $form['marketing_carrier_is_eu'] = $marketingIsEu ? 'yes' : 'no';
        }

        $meters = $this->distanceMeters($dep, $arr);
        if ($meters !== null && trim((string)($form['flight_distance_km'] ?? '')) === '') {
            $form['flight_distance_km'] = (string)max(1, (int)round($meters / 1000));
        }

        return $this->applyAirDistanceMeta($form);
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function applyAirDistanceMeta(array $form): array
    {
        $distanceKm = $this->toNullableInt($form['flight_distance_km'] ?? null);
        $departureInEu = $this->normalizeNullableBoolString($form['departure_airport_in_eu'] ?? null);
        $arrivalInEu = $this->normalizeNullableBoolString($form['arrival_airport_in_eu'] ?? null);

        $selectedBand = $this->normalizeAirDistanceBand($form['air_distance_band'] ?? null);
        $derivedBand = $this->deriveAirDistanceBand($distanceKm, $departureInEu === 'yes', $arrivalInEu === 'yes');
        $band = $selectedBand ?? $derivedBand;

        if ($distanceKm !== null) {
            $form['flight_distance_km'] = (string)$distanceKm;
        }

        if ($band !== null) {
            $form['air_distance_band'] = $band;
            $form['intra_eu_over_1500'] = $band === 'intra_eu_over_1500' ? 'yes' : 'no';
            $thresholdHours = $this->delayThresholdHoursFromAirBand($band);
            if ($thresholdHours !== null) {
                $form['air_delay_threshold_hours'] = (string)$thresholdHours;
            }
        } else {
            $form['air_distance_band'] = '';
            $form['intra_eu_over_1500'] = '';
            $form['air_delay_threshold_hours'] = '';
        }

        return $form;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function deriveFerryPortFromTerminal(array $form, string $stationPrefix, string $terminalPrefix): array
    {
        $hasStationLookup = trim((string)($form[$stationPrefix . '_lookup_id'] ?? '')) !== '';
        $terminalLookupId = trim((string)($form[$terminalPrefix . '_lookup_id'] ?? ''));
        if ($hasStationLookup || $terminalLookupId === '') {
            return $form;
        }

        $parentName = trim((string)($form[$terminalPrefix . '_lookup_parent'] ?? ''));
        if ($parentName === '') {
            return $form;
        }

        if (trim((string)($form[$stationPrefix] ?? '')) === '') {
            $form[$stationPrefix] = $parentName;
        }

        return $this->resolveLookupMeta($form, $stationPrefix, 'ferry');
    }

    /**
     * Upload and ticketless ferry flows usually extract port names ("Odden", "Aarhus"),
     * not the underlying passenger terminal. Assistance gating depends on terminal scope,
     * so we try to promote a clear terminal candidate when the match is strong enough.
     *
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function autoFillFerryTerminalFromPort(array $form, string $stationPrefix, string $terminalPrefix): array
    {
        if (trim((string)($form[$terminalPrefix] ?? '')) !== '') {
            return $form;
        }

        $portName = trim((string)($form[$stationPrefix] ?? ''));
        if ($portName === '') {
            return $form;
        }

        $operator = trim((string)($form['operator'] ?? ($form['incident_segment_operator'] ?? '')));
        $candidates = $this->nodeSearch->search('ferry', $portName, null, 6, 'terminal');
        $best = $this->pickBestFerryTerminalCandidate($portName, $operator, $candidates);
        if ($best === null || trim((string)($best['name'] ?? '')) === '') {
            return $form;
        }

        $form[$terminalPrefix] = (string)$best['name'];

        return $form;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function resolveLookupMeta(array $form, string $prefix, string $mode): array
    {
        if (!$this->shouldRefreshLookupMeta($form, $prefix, $mode) && trim((string)($form[$prefix . '_lookup_id'] ?? '')) !== '') {
            return $form;
        }

        $query = trim((string)($form[$prefix] ?? ''));
        if ($query === '') {
            return $form;
        }

        $match = $this->nodeSearch->search($mode, $query, null, 1, $this->preferredKindForPrefix($mode, $prefix), true)[0] ?? null;
        if (!is_array($match) || empty($match['id'])) {
            return $form;
        }

        $lookupCountry = strtoupper(trim((string)($match['country'] ?? '')));
        $lookupInEu = '';
        if (array_key_exists('in_eu', $match) && $match['in_eu'] !== null && $match['in_eu'] !== '') {
            $lookupInEu = ((bool)$match['in_eu']) ? 'yes' : 'no';
        } elseif ($lookupCountry !== '') {
            $derivedEu = $this->deriveEuFromCountry($lookupCountry);
            if ($derivedEu !== null) {
                $lookupInEu = $derivedEu ? 'yes' : 'no';
            }
        }

        $form[$prefix . '_lookup_id'] = (string)($match['id'] ?? '');
        $form[$prefix . '_lookup_code'] = (string)($match['code'] ?? '');
        $form[$prefix . '_lookup_mode'] = (string)($match['mode'] ?? $mode);
        $form[$prefix . '_lookup_country'] = $lookupCountry;
        $form[$prefix . '_lookup_in_eu'] = $lookupInEu;
        $form[$prefix . '_lookup_node_type'] = (string)($match['node_type'] ?? '');
        $form[$prefix . '_lookup_parent'] = (string)($match['parent_name'] ?? '');
        $form[$prefix . '_lookup_source'] = (string)($match['source'] ?? '');
        $form[$prefix . '_lookup_lat'] = isset($match['lat']) && $match['lat'] !== null ? (string)$match['lat'] : '';
        $form[$prefix . '_lookup_lon'] = isset($match['lon']) && $match['lon'] !== null ? (string)$match['lon'] : '';

        return $form;
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<string,mixed>|null
     */
    private function pickBestFerryTerminalCandidate(string $portName, string $operator, array $candidates): ?array
    {
        $portNeedle = $this->normalizeSearchNeedle($portName);
        if ($portNeedle === '') {
            return null;
        }

        $operatorNeedles = $this->operatorSearchNeedles($operator);
        $best = null;
        $bestScore = PHP_INT_MIN;

        foreach ($candidates as $candidate) {
            $nodeType = strtolower(trim((string)($candidate['node_type'] ?? '')));
            if (!in_array($nodeType, ['ferry_terminal', 'terminal', 'cruise_terminal'], true)) {
                continue;
            }

            $nameNeedle = $this->normalizeSearchNeedle((string)($candidate['name'] ?? ''));
            $parentNeedle = $this->normalizeSearchNeedle((string)($candidate['parent_name'] ?? ''));
            $score = 0;

            if ($nodeType === 'ferry_terminal') {
                $score += 26;
            } elseif ($nodeType === 'terminal') {
                $score += 18;
            } else {
                $score += 2;
            }

            if ($parentNeedle !== '' && $parentNeedle === $portNeedle) {
                $score += 40;
            } elseif (
                $portNeedle !== '' &&
                (
                    ($parentNeedle !== '' && str_contains($parentNeedle, $portNeedle)) ||
                    ($nameNeedle !== '' && str_contains($nameNeedle, $portNeedle))
                )
            ) {
                $score += 18;
            }

            foreach ($operatorNeedles as $needle) {
                if ($needle !== '' && (($nameNeedle !== '' && str_contains($nameNeedle, $needle)) || ($parentNeedle !== '' && str_contains($parentNeedle, $needle)))) {
                    $score += 20;
                    break;
                }
            }

            if ($nodeType === 'cruise_terminal') {
                $score -= 16;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= 34 ? $best : null;
    }

    private function normalizeSearchNeedle(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    /**
     * @return array<int,string>
     */
    private function operatorSearchNeedles(string $operator): array
    {
        $normalized = $this->normalizeSearchNeedle($operator);
        if ($normalized === '') {
            return [];
        }

        $stopWords = ['a/s', 'as', 'aps', 'ab', 'oy', 'ag', 'ltd', 'limited', 'ferries', 'ferry', 'lines', 'line'];
        $tokens = preg_split('/[\s-]+/u', $normalized) ?: [];
        $needles = [];
        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, $stopWords, true) || mb_strlen($token, 'UTF-8') < 4) {
                continue;
            }
            $needles[] = $token;
        }
        if ($needles === []) {
            $needles[] = $normalized;
        }

        return array_values(array_unique($needles));
    }

    /**
     * @param array<string,mixed> $form
     */
    private function shouldRefreshLookupMeta(array $form, string $prefix, string $mode): bool
    {
        $lookupId = trim((string)($form[$prefix . '_lookup_id'] ?? ''));
        if ($lookupId === '') {
            return true;
        }

        if ($mode !== 'bus') {
            return false;
        }

        $source = strtolower(trim((string)($form[$prefix . '_lookup_source'] ?? '')));
        $country = strtoupper(trim((string)($form[$prefix . '_lookup_country'] ?? '')));
        $inEu = strtolower(trim((string)($form[$prefix . '_lookup_in_eu'] ?? '')));

        if ($country === '' || $inEu === '') {
            return true;
        }

        // Refresh older generic bus lookups so curated seed metadata can win.
        return in_array($source, ['', 'osm', 'rail_station_fallback'], true);
    }

    private function preferredKindForPrefix(string $mode, string $prefix): ?string
    {
        if ($mode !== 'ferry') {
            return null;
        }

        return in_array($prefix, ['dep_terminal', 'arr_terminal'], true) ? 'terminal' : 'port';
    }

    /**
     * @param array<string,mixed> $form
     * @param array<int,string> $candidateKeys
     * @return array<string,mixed>
     */
    private function deriveOperatorDefaults(array $form, string $mode, array $candidateKeys): array
    {
        $candidate = '';
        foreach ($candidateKeys as $key) {
            $value = trim((string)($form[$key] ?? ''));
            if ($value !== '') {
                $candidate = $value;
                break;
            }
        }
        if ($candidate === '') {
            return $form;
        }

        if (($form['operator_country'] ?? '') === '') {
            $country = $this->operatorRegistry->deriveCountryCode($mode, $candidate);
            if ($country !== null) {
                $form['operator_country'] = $country;
            }
        }

        return $form;
    }

    /**
     * @param array<string,mixed> $form
     * @return array{in_eu:string,node_type:string,lat:?float,lon:?float,country:string}
     */
    private function lookupMeta(array $form, string $prefix): array
    {
        $country = strtoupper(trim((string)($form[$prefix . '_lookup_country'] ?? ($form[$prefix . '_country'] ?? ''))));
        return [
            'in_eu' => $this->normalizeYesNo($form[$prefix . '_lookup_in_eu'] ?? ''),
            'node_type' => strtolower(trim((string)($form[$prefix . '_lookup_node_type'] ?? ''))),
            'lat' => $this->toFloat($form[$prefix . '_lookup_lat'] ?? null),
            'lon' => $this->toFloat($form[$prefix . '_lookup_lon'] ?? null),
            'country' => $country,
        ];
    }

    private function normalizeYesNo(mixed $value): string
    {
        $raw = strtolower(trim((string)$value));
        if ($raw === 'yes' || $raw === 'no') {
            return $raw;
        }

        return '';
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    /**
     * @param array{lat:?float,lon:?float} $dep
     * @param array{lat:?float,lon:?float} $arr
     */
    private function distanceMeters(array $dep, array $arr): ?int
    {
        if ($dep['lat'] === null || $dep['lon'] === null || $arr['lat'] === null || $arr['lon'] === null) {
            return null;
        }

        $earthRadius = 6371000.0;
        $lat1 = deg2rad($dep['lat']);
        $lat2 = deg2rad($arr['lat']);
        $deltaLat = deg2rad($arr['lat'] - $dep['lat']);
        $deltaLon = deg2rad($arr['lon'] - $dep['lon']);
        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int)round($earthRadius * $c);
    }

    private function deriveEuFromCountry(string $countryCode): ?bool
    {
        $code = strtoupper(trim($countryCode));
        if ($code === '') {
            return null;
        }

        static $eu = [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT',
            'LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
        ];

        return in_array($code, $eu, true);
    }

    private function normalizeAirDistanceBand(mixed $value): ?string
    {
        $band = strtolower(trim((string)$value));
        return in_array($band, ['up_to_1500', 'intra_eu_over_1500', 'other_1500_to_3500', 'other_over_3500'], true)
            ? $band
            : null;
    }

    private function deriveAirDistanceBand(?int $distanceKm, bool $departureInEu, bool $arrivalInEu): ?string
    {
        if ($distanceKm === null || $distanceKm <= 0) {
            return null;
        }

        if ($distanceKm <= 1500) {
            return 'up_to_1500';
        }

        if ($departureInEu && $arrivalInEu) {
            return 'intra_eu_over_1500';
        }

        if ($distanceKm <= 3500) {
            return 'other_1500_to_3500';
        }

        return 'other_over_3500';
    }

    private function delayThresholdHoursFromAirBand(?string $band): ?int
    {
        return match ($band) {
            'up_to_1500' => 2,
            'intra_eu_over_1500', 'other_1500_to_3500' => 3,
            'other_over_3500' => 4,
            default => null,
        };
    }

    private function normalizeNullableBoolString(mixed $value): ?string
    {
        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            '1', 'true', 'yes', 'ja', 'y' => 'yes',
            '0', 'false', 'no', 'nej', 'n' => 'no',
            default => null,
        };
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int)round((float)$value);
        }

        $digits = preg_replace('/[^0-9]/', '', (string)$value);
        if ($digits === null || $digits === '') {
            return null;
        }

        return (int)$digits;
    }
}
