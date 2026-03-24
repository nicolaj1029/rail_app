<?php
declare(strict_types=1);

namespace App\Service;

final class FerryRightsEvaluator
{
    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $contractResult
     * @return array<string,mixed>
     */
    public function evaluate(array $incidentMeta, array $scopeResult = [], array $contractResult = []): array
    {
        $regulationApplies = (bool)($scopeResult['regulation_applies'] ?? false);
        $serviceType = (string)($scopeResult['service_type'] ?? 'passenger_service');
        $articles = (array)($scopeResult['articles'] ?? []);
        $departureFromTerminal = ($scopeResult['departure_from_terminal'] ?? null);

        $incidentType = strtolower(trim((string)($incidentMeta['incident_type'] ?? '')));
        $expectedDepartureDelay90 = (bool)($incidentMeta['expected_departure_delay_90'] ?? false);
        $actualDepartureDelay90 = (bool)($incidentMeta['actual_departure_delay_90'] ?? false);
        $arrivalDelayMinutes = $this->normalizeNullableInt($incidentMeta['arrival_delay_minutes'] ?? null);
        $scheduledDurationMinutes = $this->normalizeNullableInt($incidentMeta['scheduled_journey_duration_minutes'] ?? null);
        $informedBeforePurchase = (bool)($incidentMeta['informed_before_purchase'] ?? false);
        $passengerFault = (bool)($incidentMeta['passenger_fault'] ?? false);
        $weatherSafety = (bool)($incidentMeta['weather_safety'] ?? false);
        $extraordinaryCircumstances = (bool)($incidentMeta['extraordinary_circumstances'] ?? false);
        $openTicketWithoutDepartureTime = (bool)($incidentMeta['open_ticket_without_departure_time'] ?? false);
        $seasonTicket = (bool)($incidentMeta['season_ticket'] ?? false);

        $hasDepartureDisruption = $incidentType === 'cancellation' || $expectedDepartureDelay90 || $actualDepartureDelay90;

        $gateArt16Notice = false;
        $gateArt16AltConnections = false;
        $gateArt17Refreshments = false;
        $gateArt17Hotel = false;
        $gateArt18 = false;
        $gateArt19 = false;
        $art19CompBand = 'none';
        $gateManualReview = (bool)($contractResult['manual_review_required'] ?? false);

        if (!$regulationApplies) {
            return $this->result(
                $gateArt16Notice,
                $gateArt16AltConnections,
                $gateArt17Refreshments,
                $gateArt17Hotel,
                $gateArt18,
                $gateArt19,
                $art19CompBand,
                true,
                'scope_excluded'
            );
        }

        if ($serviceType === 'cruise') {
            $gateArt16Notice = true;
            $gateArt16AltConnections = true;

            return $this->result(
                $gateArt16Notice,
                $gateArt16AltConnections,
                false,
                false,
                false,
                false,
                'none',
                $gateManualReview,
                'cruise_carveout'
            );
        }

        if ($hasDepartureDisruption) {
            $gateArt16Notice = true;
            $gateArt16AltConnections = true;
        }

        if ($hasDepartureDisruption && $departureFromTerminal === true && !$openTicketWithoutDepartureTime) {
            $gateArt17Refreshments = true;
            if (!$weatherSafety) {
                $gateArt17Hotel = true;
            }
        }

        if ($hasDepartureDisruption && (!$openTicketWithoutDepartureTime || $seasonTicket) && ($articles['art18'] ?? true)) {
            $gateArt18 = true;
        }

        if ($informedBeforePurchase || $passengerFault) {
            $gateArt17Refreshments = false;
            $gateArt17Hotel = false;
        }

        $art19Threshold = $this->determineArt19Threshold($scheduledDurationMinutes);
        if (
            $art19Threshold !== null &&
            $arrivalDelayMinutes !== null &&
            $arrivalDelayMinutes >= $art19Threshold &&
            !$informedBeforePurchase &&
            !$passengerFault &&
            !$weatherSafety &&
            !$extraordinaryCircumstances &&
            (!$openTicketWithoutDepartureTime || $seasonTicket) &&
            ($articles['art19'] ?? true)
        ) {
            $gateArt19 = true;
            $art19CompBand = $arrivalDelayMinutes >= ($art19Threshold * 2) ? '50' : '25';
        }

        if ($weatherSafety) {
            $gateArt17Hotel = false;
        }

        return $this->result(
            $gateArt16Notice,
            $gateArt16AltConnections,
            $gateArt17Refreshments,
            $gateArt17Hotel,
            $gateArt18,
            $gateArt19,
            $art19CompBand,
            $gateManualReview,
            null
        );
    }

    private function determineArt19Threshold(?int $scheduledDurationMinutes): ?int
    {
        if ($scheduledDurationMinutes === null || $scheduledDurationMinutes <= 0) {
            return null;
        }
        if ($scheduledDurationMinutes <= 240) {
            return 60;
        }
        if ($scheduledDurationMinutes < 480) {
            return 120;
        }
        if ($scheduledDurationMinutes < 1440) {
            return 180;
        }

        return 360;
    }

    /**
     * @return array<string,mixed>
     */
    private function result(
        bool $gateArt16Notice,
        bool $gateArt16AltConnections,
        bool $gateArt17Refreshments,
        bool $gateArt17Hotel,
        bool $gateArt18,
        bool $gateArt19,
        string $art19CompBand,
        bool $gateManualReview,
        ?string $reason
    ): array {
        return [
            'gate_art16_notice' => $gateArt16Notice,
            'gate_art16_alt_connections' => $gateArt16AltConnections,
            'gate_art17_refreshments' => $gateArt17Refreshments,
            'gate_art17_hotel' => $gateArt17Hotel,
            'gate_art18' => $gateArt18,
            'gate_art19' => $gateArt19,
            'art19_comp_band' => $art19CompBand,
            'gate_manual_review' => $gateManualReview,
            'reason' => $reason,
        ];
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
