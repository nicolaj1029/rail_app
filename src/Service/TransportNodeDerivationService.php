<?php
declare(strict_types=1);

namespace App\Service;

final class TransportNodeDerivationService
{
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

        $dep = $this->lookupMeta($form, 'dep_station');
        $arr = $this->lookupMeta($form, 'arr_station');

        if (($form['incident_segment_mode'] ?? '') === '') {
            $form['incident_segment_mode'] = $mode;
        }

        if ($mode === 'ferry') {
            if (($form['departure_port_in_eu'] ?? '') === '' && $dep['in_eu'] !== '') {
                $form['departure_port_in_eu'] = $dep['in_eu'];
            }
            if (($form['arrival_port_in_eu'] ?? '') === '' && $arr['in_eu'] !== '') {
                $form['arrival_port_in_eu'] = $arr['in_eu'];
            }
            if (($form['departure_from_terminal'] ?? '') === '' && in_array($dep['node_type'], ['ferry_terminal', 'terminal'], true)) {
                $form['departure_from_terminal'] = 'yes';
            }
            if (($form['route_distance_meters'] ?? '') === '') {
                $meters = $this->distanceMeters($dep, $arr);
                if ($meters !== null) {
                    $form['route_distance_meters'] = (string)$meters;
                }
            }
            if (($form['carrier_is_eu'] ?? '') === '') {
                $carrierIsEu = $this->deriveEuFromCountry((string)($form['operator_country'] ?? ''));
                if ($carrierIsEu !== null) {
                    $form['carrier_is_eu'] = $carrierIsEu ? 'yes' : 'no';
                }
            }

            return $form;
        }

        if ($mode === 'bus') {
            if (($form['boarding_in_eu'] ?? '') === '' && $dep['in_eu'] !== '') {
                $form['boarding_in_eu'] = $dep['in_eu'];
            }
            if (($form['alighting_in_eu'] ?? '') === '' && $arr['in_eu'] !== '') {
                $form['alighting_in_eu'] = $arr['in_eu'];
            }
            if (($form['departure_from_terminal'] ?? '') === '' && in_array($dep['node_type'], ['terminal', 'bus_terminal'], true)) {
                $form['departure_from_terminal'] = 'yes';
            }
            if (($form['scheduled_distance_km'] ?? '') === '') {
                $meters = $this->distanceMeters($dep, $arr);
                if ($meters !== null) {
                    $form['scheduled_distance_km'] = (string)max(1, (int)round($meters / 1000));
                }
            }

            return $form;
        }

        if (($form['departure_airport_in_eu'] ?? '') === '' && $dep['in_eu'] !== '') {
            $form['departure_airport_in_eu'] = $dep['in_eu'];
        }
        if (($form['arrival_airport_in_eu'] ?? '') === '' && $arr['in_eu'] !== '') {
            $form['arrival_airport_in_eu'] = $arr['in_eu'];
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
