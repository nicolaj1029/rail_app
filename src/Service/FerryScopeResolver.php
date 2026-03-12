<?php
declare(strict_types=1);

namespace App\Service;

final class FerryScopeResolver
{
    /**
     * @param array<string,mixed> $scopeMeta
     * @return array<string,mixed>
     */
    public function evaluate(array $scopeMeta): array
    {
        $serviceType = $this->normalizeServiceType($scopeMeta['service_type'] ?? null);
        $departurePortInEu = (bool)($scopeMeta['departure_port_in_eu'] ?? false);
        $arrivalPortInEu = (bool)($scopeMeta['arrival_port_in_eu'] ?? false);
        $carrierIsEu = (bool)($scopeMeta['carrier_is_eu'] ?? false);
        $departureFromTerminal = $scopeMeta['departure_from_terminal'] ?? null;
        $passengerCapacity = $this->normalizeNullableInt($scopeMeta['vessel_passenger_capacity'] ?? null);
        $crewCount = $this->normalizeNullableInt($scopeMeta['vessel_operational_crew'] ?? null);
        $routeDistanceMeters = $this->normalizeNullableInt($scopeMeta['route_distance_meters'] ?? null);

        $result = [
            'service_type' => $serviceType,
            'departure_from_terminal' => $departureFromTerminal,
            'regulation_applies' => false,
            'scope_basis' => null,
            'scope_exclusion_reason' => null,
            'cruise_carveout' => false,
            'articles' => [
                'art16_1' => true,
                'art16_2' => true,
                'art17' => true,
                'art18' => true,
                'art19' => true,
                'art20_1' => true,
                'art20_4' => true,
            ],
        ];

        if ($passengerCapacity !== null && $passengerCapacity <= 12) {
            return $this->exclude($result, 'small_vessel_capacity');
        }
        if ($crewCount !== null && $crewCount <= 3) {
            return $this->exclude($result, 'small_operational_crew');
        }
        if ($routeDistanceMeters !== null && $routeDistanceMeters < 500) {
            return $this->exclude($result, 'route_distance_under_500m');
        }

        if ($serviceType === 'cruise') {
            if (!$departurePortInEu) {
                return $this->exclude($result, 'cruise_outside_eu_departure');
            }
            $result['regulation_applies'] = true;
            $result['scope_basis'] = 'cruise_departure_eu';
            $result['cruise_carveout'] = true;
            $result['articles']['art16_2'] = false;
            $result['articles']['art18'] = false;
            $result['articles']['art19'] = false;
            $result['articles']['art20_1'] = false;
            $result['articles']['art20_4'] = false;

            return $result;
        }

        if ($departurePortInEu) {
            $result['regulation_applies'] = true;
            $result['scope_basis'] = 'departure_eu';

            return $result;
        }

        if ($arrivalPortInEu && $carrierIsEu) {
            $result['regulation_applies'] = true;
            $result['scope_basis'] = 'arrival_eu_carrier_eu';

            return $result;
        }

        return $this->exclude($result, 'outside_eu_scope');
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function exclude(array $result, string $reason): array
    {
        $result['regulation_applies'] = false;
        $result['scope_exclusion_reason'] = $reason;
        $result['scope_basis'] = null;

        return $result;
    }

    private function normalizeServiceType(mixed $value): string
    {
        $normalized = strtolower(trim((string)$value));

        return match ($normalized) {
            'cruise' => 'cruise',
            default => 'passenger_service',
        };
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }
}
