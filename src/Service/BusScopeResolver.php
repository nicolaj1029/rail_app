<?php
declare(strict_types=1);

namespace App\Service;

final class BusScopeResolver
{
    /**
     * @param array<string,mixed> $scopeMeta
     * @return array<string,mixed>
     */
    public function evaluate(array $scopeMeta): array
    {
        $regularService = $this->toBool($scopeMeta['bus_regular_service'] ?? null);
        $boardingInEu = $this->toBool($scopeMeta['boarding_in_eu'] ?? null);
        $alightingInEu = $this->toBool($scopeMeta['alighting_in_eu'] ?? null);
        $departureFromTerminal = $this->toBool($scopeMeta['departure_from_terminal'] ?? null);
        $distanceKm = $this->toInt($scopeMeta['scheduled_distance_km'] ?? null);

        $applies = false;
        $basis = 'out_of_scope';
        $reason = null;

        if ($regularService === true && ($boardingInEu === true || $alightingInEu === true)) {
            $applies = true;
            $basis = ($distanceKm !== null && $distanceKm >= 250)
                ? 'regular_service_250km_plus'
                : 'regular_service_under_250km';
        } else {
            $reason = 'outside_bus_181_2011_scope';
        }

        return [
            'regulation_applies' => $applies,
            'scope_basis' => $basis,
            'scope_exclusion_reason' => $reason,
            'bus_regular_service' => $regularService,
            'boarding_in_eu' => $boardingInEu,
            'alighting_in_eu' => $alightingInEu,
            'departure_from_terminal' => $departureFromTerminal,
            'scheduled_distance_km' => $distanceKm,
        ];
    }

    private function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        return match ($normalized) {
            '1', 'true', 'yes', 'ja', 'y' => true,
            '0', 'false', 'no', 'nej', 'n' => false,
            default => null,
        };
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }
}
