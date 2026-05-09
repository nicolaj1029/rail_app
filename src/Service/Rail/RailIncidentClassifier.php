<?php
declare(strict_types=1);

namespace App\Service\Rail;

final class RailIncidentClassifier
{
    /**
     * @param array<string,mixed> $departure
     * @param array<string,mixed> $userAnswers
     * @return array<string,mixed>
     */
    public function classify(array $departure, array $userAnswers = []): array
    {
        $arrivalDelay = $this->normalizeInt($departure['arrival_delay_minutes'] ?? null);
        $departureDelay = $this->normalizeInt($departure['departure_delay_minutes'] ?? null);
        $status = strtolower(trim((string)($departure['status'] ?? 'unknown')));
        $raw = (array)($departure['raw'] ?? []);
        $legCount = $this->normalizeInt($raw['leg_count'] ?? null) ?? 1;
        $railLegCount = $this->normalizeInt($raw['rail_leg_count'] ?? null) ?? $legCount;
        $transferCount = $this->normalizeInt($raw['transfer_count'] ?? null) ?? max(0, $railLegCount - 1);
        $hasConnections = $transferCount > 0;
        $replacementTransportSuspected = !empty($raw['has_replacement']) || $status === 'replacement_transport';
        $incidentType = 'unknown';
        $gateArt18 = false;
        $gateArt19 = false;
        $gateArt20 = false;
        $warnings = ['Eksterne rail-data er kun UX-seed og skal altid bekraeftes af brugeren.'];

        if ($status === 'cancelled') {
            $incidentType = 'cancellation';
            $gateArt18 = true;
            $gateArt20 = true;
        } elseif ($status === 'partially_cancelled') {
            $incidentType = 'partial_cancellation';
            $gateArt18 = true;
            $gateArt20 = true;
        } elseif ($status === 'replacement_transport') {
            $incidentType = 'replacement_transport';
            $gateArt18 = true;
            $gateArt20 = true;
        }

        if ($arrivalDelay !== null && $arrivalDelay >= 60) {
            if ($incidentType === 'unknown') {
                $incidentType = 'delay';
            }
            $gateArt18 = true;
            $gateArt19 = true;
            $gateArt20 = true;
        }

        if (!empty($userAnswers['missed_connection'])) {
            $incidentType = 'missed_connection';
            $gateArt18 = true;
            $gateArt20 = true;
        }

        $missedConnectionSuspected = $hasConnections
            && (
                $incidentType === 'delay'
                || $incidentType === 'partial_cancellation'
                || $incidentType === 'replacement_transport'
                || ($arrivalDelay !== null && $arrivalDelay >= 20)
            );

        if ($arrivalDelay === null) {
            $warnings[] = 'Ankomstforsinkelse mangler og skal bekraeftes manuelt i incident.';
        }
        if ($status === 'unknown') {
            $warnings[] = 'Status fra rail-provider er ukendt eller ufuldstaendig.';
        }

        return [
            'incident_type' => $incidentType,
            'arrival_delay_minutes' => $arrivalDelay,
            'departure_delay_minutes' => $departureDelay,
            'gate_art18' => $gateArt18,
            'gate_art19' => $gateArt19,
            'gate_art20' => $gateArt20,
            'needs_user_confirmation' => true,
            'delay_seed' => $arrivalDelay !== null && $arrivalDelay >= 60,
            'cancellation_seed' => in_array($incidentType, ['cancellation', 'partial_cancellation'], true),
            'replacement_transport_suspected' => $replacementTransportSuspected,
            'missed_connection_suspected' => $missedConnectionSuspected,
            'leg_count' => $legCount,
            'rail_leg_count' => $railLegCount,
            'transfer_count' => $transferCount,
            'has_connections' => $hasConnections,
            'warnings' => $warnings,
        ];
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)round((float)$value) : null;
    }
}
