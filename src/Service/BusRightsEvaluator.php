<?php
declare(strict_types=1);

namespace App\Service;

final class BusRightsEvaluator
{
    /**
     * @param array<string,mixed> $incidentMeta
     * @param array<string,mixed> $scopeResult
     * @param array<string,mixed> $contractResult
     * @return array<string,mixed>
     */
    public function evaluate(array $incidentMeta, array $scopeResult, array $contractResult): array
    {
        $scopeApplies = (bool)($scopeResult['regulation_applies'] ?? false);
        $departureFromTerminal = (bool)($scopeResult['departure_from_terminal'] ?? false);
        $distanceKm = isset($scopeResult['scheduled_distance_km']) && is_numeric($scopeResult['scheduled_distance_km'])
            ? (int)$scopeResult['scheduled_distance_km']
            : null;
        $incidentType = strtolower(trim((string)($incidentMeta['incident_type'] ?? '')));
        $delayBand = strtolower(trim((string)($incidentMeta['delay_departure_band'] ?? '')));
        $durationBand = strtolower(trim((string)($incidentMeta['planned_duration_band'] ?? '')));
        $departureDelay = (int)($incidentMeta['delay_minutes_departure'] ?? 0);
        $journeyDuration = (int)($incidentMeta['scheduled_journey_duration_minutes'] ?? 0);
        $overbooking = $incidentType === 'overbooking' || $this->toBool($incidentMeta['overbooking'] ?? null) === true;
        $carrierOfferedChoice = $this->toBool($incidentMeta['carrier_offered_choice'] ?? null);
        $openTicket = $this->toBool($incidentMeta['open_ticket_without_departure_time'] ?? null) === true;
        $seasonTicket = $this->toBool($incidentMeta['season_ticket'] ?? null) === true;
        $severeWeather = $this->toBool($incidentMeta['severe_weather'] ?? null) === true;
        $naturalDisaster = $this->toBool($incidentMeta['major_natural_disaster'] ?? null) === true;
        $missedConnection = $this->toBool($incidentMeta['missed_connection_due_to_delay'] ?? null) === true;

        $departureDelay120 = $delayBand === '120_plus' || ($delayBand === '' && $departureDelay >= 120);
        $departureDelay90 = in_array($delayBand, ['90_119', '120_plus'], true) || ($delayBand === '' && $departureDelay >= 90);
        $longJourney = $durationBand === 'over_3h' || ($durationBand === '' && $journeyDuration >= 180);
        $longDistanceService = $distanceKm !== null && $distanceKm >= 250;
        $cancellation = $incidentType === 'cancellation';
        $delay = $incidentType === 'delay';
        $terminalEvent = $scopeApplies && $longDistanceService && $departureFromTerminal && ($cancellation || $departureDelay120 || $overbooking);
        $assistanceEvent = $scopeApplies && $longDistanceService && $departureFromTerminal && $longJourney && ($cancellation || $departureDelay90);
        $openTicketExclusion = $openTicket && !$seasonTicket;

        $gateInfo = $scopeApplies && ($cancellation || $delay || $overbooking || $missedConnection);
        $gateRerouteRefund = $terminalEvent && !$openTicketExclusion;
        $gateRefreshments = $assistanceEvent && !$openTicketExclusion;
        $gateHotel = $assistanceEvent && !$openTicketExclusion && !($severeWeather || $naturalDisaster);
        $gateCompensation50 = $terminalEvent && !$openTicketExclusion && $carrierOfferedChoice === false;

        return [
            'gate_bus_info' => $gateInfo,
            'gate_bus_reroute_refund' => $gateRerouteRefund,
            'gate_bus_assistance_refreshments' => $gateRefreshments,
            'gate_bus_assistance_hotel' => $gateHotel,
            'gate_bus_compensation_50' => $gateCompensation50,
            'bus_comp_band' => $gateCompensation50 ? '50' : 'none',
            'manual_review_required' => $scopeApplies && $carrierOfferedChoice === null && $terminalEvent,
            'compensation_block_reason' => $openTicketExclusion ? 'open_ticket_without_departure_time' : (($severeWeather || $naturalDisaster) && $assistanceEvent ? 'hotel_weather_or_disaster_exclusion' : 'none'),
            'delay_departure_band' => $delayBand !== '' ? $delayBand : null,
            'planned_duration_band' => $durationBand !== '' ? $durationBand : null,
            'distance_km' => $distanceKm,
            'departure_delay_minutes' => $departureDelay,
            'scheduled_journey_duration_minutes' => $journeyDuration,
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
