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
        $flightDistanceKm = $this->toInt($scopeMeta['flight_distance_km'] ?? null);
        $airDistanceBand = $this->normalizeAirDistanceBand($scopeMeta['air_distance_band'] ?? null)
            ?? $this->deriveAirDistanceBand($flightDistanceKm, $departureInEu === true, $arrivalInEu === true);
        $thresholdHours = $this->delayThresholdHoursFromAirBand($airDistanceBand);

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
            'flight_distance_km' => $flightDistanceKm,
            'air_distance_band' => $airDistanceBand,
            'air_delay_threshold_hours' => $thresholdHours,
            'intra_eu_over_1500' => $airDistanceBand === 'intra_eu_over_1500',
        ];
    }

    private function toInt(mixed $value): ?int
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
