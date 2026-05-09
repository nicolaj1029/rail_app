<?php
declare(strict_types=1);

namespace App\Service;

final class FerryMockScenarioService
{
    /**
     * @return array<string,string>
     */
    public function options(): array
    {
        return [
            '' => 'Ingen proforma-data',
            'art19_25' => 'Art. 19 test - 25%',
            'art19_50' => 'Art. 19 test - 50%',
            'cancellation_art18' => 'Aflysning test - Art. 17/18',
        ];
    }

    /**
     * @param array<string,mixed> $selectedDeparture
     * @param array<string,mixed> $form
     * @return array{selected_departure:array<string,mixed>,operational_evidence:array<string,mixed>,presets:array<string,string>}
     */
    public function apply(array $selectedDeparture, string $scenario, array $form = []): array
    {
        $scenario = strtolower(trim($scenario));
        if (!isset($this->options()[$scenario])) {
            return [
                'selected_departure' => $selectedDeparture,
                'operational_evidence' => [],
                'presets' => [],
            ];
        }

        $depTs = $this->resolveBaseDepartureTs($selectedDeparture, $form);
        $operatorName = trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? 'Mock Ferry Operator')));
        $vesselName = trim((string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? 'Mock Vessel')));
        $depPortName = trim((string)($selectedDeparture['departure_port_name'] ?? ($form['dep_station'] ?? 'Departure Terminal')));
        $arrPortName = trim((string)($selectedDeparture['arrival_port_name'] ?? ($form['arr_station'] ?? 'Arrival Terminal')));

        $durationMinutes = 180;
        $departureDelayMinutes = 0;
        $arrivalDelayMinutes = 0;
        $status = 'scheduled';
        $cancelled = false;

        switch ($scenario) {
            case 'art19_25':
                $durationMinutes = 180;
                $departureDelayMinutes = 95;
                $arrivalDelayMinutes = 70;
                $status = 'delayed';
                break;
            case 'art19_50':
                $durationMinutes = 180;
                $departureDelayMinutes = 125;
                $arrivalDelayMinutes = 140;
                $status = 'delayed';
                break;
            case 'cancellation_art18':
                $durationMinutes = 180;
                $departureDelayMinutes = 0;
                $arrivalDelayMinutes = 0;
                $status = 'cancelled';
                $cancelled = true;
                break;
        }

        $scheduledDepartureTs = $depTs;
        $scheduledArrivalTs = $scheduledDepartureTs + ($durationMinutes * 60);
        $estimatedDepartureTs = $scheduledDepartureTs + ($departureDelayMinutes * 60);
        $estimatedArrivalTs = $scheduledArrivalTs + ($arrivalDelayMinutes * 60);

        $selectedDeparture['source'] = 'mock_proforma';
        $selectedDeparture['operator_name'] = $operatorName;
        $selectedDeparture['vessel_name'] = $vesselName;
        $selectedDeparture['departure_port_name'] = $depPortName;
        $selectedDeparture['arrival_port_name'] = $arrPortName;
        $selectedDeparture['scheduled_departure_local'] = date('Y-m-d\TH:i:s', $scheduledDepartureTs);
        $selectedDeparture['scheduled_arrival_local'] = date('Y-m-d\TH:i:s', $scheduledArrivalTs);
        $selectedDeparture['status'] = $status;
        $selectedDeparture['estimated_departure_local'] = $cancelled ? '' : date('Y-m-d\TH:i:s', $estimatedDepartureTs);
        $selectedDeparture['estimated_arrival_local'] = $cancelled ? '' : date('Y-m-d\TH:i:s', $estimatedArrivalTs);
        $selectedDeparture['actual_departure_local'] = $cancelled ? '' : date('Y-m-d\TH:i:s', $estimatedDepartureTs);
        $selectedDeparture['actual_arrival_local'] = $cancelled ? '' : date('Y-m-d\TH:i:s', $estimatedArrivalTs);
        $selectedDeparture['live_position_reported_local'] = date('Y-m-d\TH:i:s', max($scheduledDepartureTs - 300, 0));
        $selectedDeparture['live_destination'] = $arrPortName;
        $selectedDeparture['live_speed_knots'] = $cancelled ? null : 14.2;

        $summaryParts = [];
        if ($scenario === 'art19_25') {
            $summaryParts[] = 'Proforma Art. 19 testdata (25%)';
        } elseif ($scenario === 'art19_50') {
            $summaryParts[] = 'Proforma Art. 19 testdata (50%)';
        } else {
            $summaryParts[] = 'Proforma aflysningstest';
        }
        if (!$cancelled) {
            $summaryParts[] = 'ETA/ATA peger paa ankomstafvigelse ca. ' . $arrivalDelayMinutes . ' min';
        }

        $operationalEvidence = [
            'available' => true,
            'source' => 'mock_proforma',
            'status' => $status,
            'operator_name' => $operatorName,
            'vessel_name' => $vesselName,
            'vessel_imo' => (string)($selectedDeparture['vessel_imo'] ?? ''),
            'vessel_mmsi' => (string)($selectedDeparture['vessel_mmsi'] ?? ''),
            'scheduled_departure_local' => (string)$selectedDeparture['scheduled_departure_local'],
            'scheduled_arrival_local' => (string)$selectedDeparture['scheduled_arrival_local'],
            'estimated_departure_local' => (string)$selectedDeparture['estimated_departure_local'],
            'estimated_arrival_local' => (string)$selectedDeparture['estimated_arrival_local'],
            'actual_departure_local' => (string)$selectedDeparture['actual_departure_local'],
            'actual_arrival_local' => (string)$selectedDeparture['actual_arrival_local'],
            'live_position_reported_local' => (string)$selectedDeparture['live_position_reported_local'],
            'live_destination' => (string)$selectedDeparture['live_destination'],
            'live_speed_knots' => $selectedDeparture['live_speed_knots'],
            'cancelled' => $cancelled ? 'yes' : 'no',
            'departure_delay_minutes_estimated' => $departureDelayMinutes,
            'arrival_delay_minutes_estimated' => $arrivalDelayMinutes,
            'match_checks' => [],
            'evidence_score' => 88,
            'confidence' => 'high',
            'needs_manual_review' => 'no',
            'summary' => implode(' · ', $summaryParts),
            'allowed_uses' => ['dev_mock', 'incident_prefill_support', 'live_estimate_support'],
            'disallowed_uses' => ['final_legal_decision_only'],
        ];

        return [
            'selected_departure' => $selectedDeparture,
            'operational_evidence' => $operationalEvidence,
            'presets' => [
                'scheduled_journey_duration_minutes' => (string)$durationMinutes,
                'delay_minutes_departure' => (string)$departureDelayMinutes,
                'arrival_delay_minutes' => (string)$arrivalDelayMinutes,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $selectedDeparture
     * @param array<string,mixed> $form
     */
    private function resolveBaseDepartureTs(array $selectedDeparture, array $form): int
    {
        $selected = trim((string)($selectedDeparture['scheduled_departure_local'] ?? ''));
        if ($selected !== '') {
            $ts = strtotime($selected);
            if ($ts !== false) {
                return $ts;
            }
        }

        $depDate = trim((string)($form['dep_date'] ?? date('Y-m-d')));
        $depTime = trim((string)($form['dep_time'] ?? '10:00'));
        $ts = strtotime($depDate . ' ' . $depTime);
        if ($ts !== false) {
            return $ts;
        }

        return strtotime(date('Y-m-d') . ' 10:00') ?: time();
    }
}
