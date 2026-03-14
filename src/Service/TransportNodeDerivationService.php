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
        $dep = $this->lookupMeta($form, 'dep_station');
        $arr = $this->lookupMeta($form, 'arr_station');

        if (($form['incident_segment_mode'] ?? '') === '') {
            $form['incident_segment_mode'] = $mode;
        }

        if ($mode === 'ferry') {
            $form = $this->deriveOperatorDefaults($form, 'ferry', ['operator', 'incident_segment_operator']);
            if ($dep['in_eu'] !== '') {
                $form['departure_port_in_eu'] = $dep['in_eu'];
            }
            if ($arr['in_eu'] !== '') {
                $form['arrival_port_in_eu'] = $arr['in_eu'];
            }
            if ($dep['node_type'] !== '') {
                $form['departure_from_terminal'] = in_array($dep['node_type'], ['ferry_terminal', 'terminal'], true) ? 'yes' : 'no';
            }
            $meters = $this->distanceMeters($dep, $arr);
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
            if ($dep['in_eu'] !== '') {
                $form['boarding_in_eu'] = $dep['in_eu'];
            }
            if ($arr['in_eu'] !== '') {
                $form['alighting_in_eu'] = $arr['in_eu'];
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

        return $form;
    }

    /**
     * @param array<string,mixed> $form
     * @return array<string,mixed>
     */
    private function resolveLookupMeta(array $form, string $prefix, string $mode): array
    {
        if (trim((string)($form[$prefix . '_lookup_id'] ?? '')) !== '') {
            return $form;
        }

        $query = trim((string)($form[$prefix] ?? ''));
        if ($query === '') {
            return $form;
        }

        $match = $this->nodeSearch->search($mode, $query, null, 1)[0] ?? null;
        if (!is_array($match) || empty($match['id'])) {
            return $form;
        }

        $form[$prefix . '_lookup_id'] = (string)($match['id'] ?? '');
        $form[$prefix . '_lookup_code'] = (string)($match['code'] ?? '');
        $form[$prefix . '_lookup_mode'] = (string)($match['mode'] ?? $mode);
        $form[$prefix . '_lookup_country'] = (string)($match['country'] ?? '');
        $form[$prefix . '_lookup_in_eu'] = array_key_exists('in_eu', $match)
            ? ((bool)$match['in_eu'] ? 'yes' : 'no')
            : '';
        $form[$prefix . '_lookup_node_type'] = (string)($match['node_type'] ?? '');
        $form[$prefix . '_lookup_parent'] = (string)($match['parent_name'] ?? '');
        $form[$prefix . '_lookup_source'] = (string)($match['source'] ?? '');
        $form[$prefix . '_lookup_lat'] = isset($match['lat']) && $match['lat'] !== null ? (string)$match['lat'] : '';
        $form[$prefix . '_lookup_lon'] = isset($match['lon']) && $match['lon'] !== null ? (string)$match['lon'] : '';

        return $form;
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
     * @return array{in_eu:string,node_type:string,lat:?float,lon:?float}
     */
    private function lookupMeta(array $form, string $prefix): array
    {
        return [
            'in_eu' => $this->normalizeYesNo($form[$prefix . '_lookup_in_eu'] ?? ''),
            'node_type' => strtolower(trim((string)($form[$prefix . '_lookup_node_type'] ?? ''))),
            'lat' => $this->toFloat($form[$prefix . '_lookup_lat'] ?? null),
            'lon' => $this->toFloat($form[$prefix . '_lookup_lon'] ?? null),
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
}
