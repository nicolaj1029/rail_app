<?php
declare(strict_types=1);

namespace App\Service;

final class AirScopeResolver
{
    /**
     * @param array<string,mixed> $scopeMeta
     * @return array<string,mixed>
     */
    public function evaluate(array $scopeMeta): array
    {
        $departureInEu = $this->toBool($scopeMeta['departure_airport_in_eu'] ?? null);
        $arrivalInEu = $this->toBool($scopeMeta['arrival_airport_in_eu'] ?? null);
        $operatingCarrierIsEu = $this->toBool($scopeMeta['operating_carrier_is_eu'] ?? null);

        $applies = false;
        $basis = 'out_of_scope';
        $reason = null;

        if ($departureInEu === true) {
            $applies = true;
            $basis = 'departure_eu';
        } elseif ($arrivalInEu === true && $operatingCarrierIsEu === true) {
            $applies = true;
            $basis = 'arrival_eu_eu_operating_carrier';
        } else {
            $reason = 'outside_ec261_scope';
        }

        return [
            'regulation_applies' => $applies,
            'scope_basis' => $basis,
            'scope_exclusion_reason' => $reason,
            'departure_airport_in_eu' => $departureInEu,
            'arrival_airport_in_eu' => $arrivalInEu,
            'operating_carrier_is_eu' => $operatingCarrierIsEu,
            'marketing_carrier_is_eu' => $this->toBool($scopeMeta['marketing_carrier_is_eu'] ?? null),
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
}
