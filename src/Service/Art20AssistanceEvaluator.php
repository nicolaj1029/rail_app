<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluator for Art. 20(2), 20(3) og 20(5) (assistance).
 *
 * Inputs (meta):
 *  - activation: art20_expected_delay_60, incident.cancellation/missed_connection, delayExpectedMinutes
 *  - meals: meal_offered, assistance_meals_unavailable_reason
 *  - hotel: hotel_offered, assistance_hotel_transport_included, assistance_hotel_accessible,
 *           hotel_self_paid_amount/currency/nights
 *  - blocked: blocked_train_alt_transport, blocked_no_transport_action, assistance_blocked_transport_to/time/possible
 *  - alternative transport: alt_transport_provided, assistance_alt_transport_offered_by/type/to_destination/departure_time/arrival_time
 *  - station (Art.20(3)): a20_3_solution_offered, a20_3_solution_type, a20_3_solution_offered_by, a20_3_no_solution_action, a20_3_outcome, a20_3_self_arranged_type, a20_3_self_paid_*
 *  - outcome: journey_outcome
 *  - PMR: pmr_user, assistance_pmr_priority_applied/companion_supported/dog_supported
 *  - self paid: meal_self_paid_*, hotel_self_paid_*, blocked_self_paid_*, alt_self_paid_*
 */
class Art20AssistanceEvaluator
{
    /**
     * @param array $journey
     * @param array $meta full meta incl. incident + form values
     * @return array{hooks:array<string,mixed>,compliance_status:?bool,missing:string[],ask:string[],issues:string[],recommendations:string[],labels:string[]}
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $hooks = $this->collectHooks($journey, $meta);
        $active = $hooks['_active'];
        $pmrMissing = $this->tri($meta['pmr_promised_missing'] ?? '');
        $pmrPartial = (!$active) && ($hooks['pmr_user'] === 'Ja') && ($pmrMissing === 'Ja');
        $pmrActive = $active || $pmrPartial;

        $missing = [];
        $ask = [];
        $issues = [];
        $recs = [];

        $location = strtolower(trim((string)($hooks['stranded_location'] ?? '')));
        $isTrack = ($location === 'track');
        $isStation = ($location === 'station');
        $altProvided = $hooks['alt_transport_provided'] ?? 'unknown';
        if ($isStation && !empty($hooks['a20_3_solution_offered'])) {
            $altProvided = $hooks['a20_3_solution_offered'];
        }
        $yn = function($v): string {
            $s = strtolower(trim((string)$v));
            if ($s === 'ja') { return 'yes'; }
            if ($s === 'nej') { return 'no'; }
            return $s;
        };

        $ynFields = [
            'meal_offered', 'hotel_offered',
        ];
        foreach ($ynFields as $f) {
            if (!$active) { continue; }
            $v = $hooks[$f] ?? 'unknown';
            if ($v === 'unknown' || $v === '') {
                $missing[] = $f;
                $ask[] = $f;
            }
        }
        if ($active) {
            if ($isTrack) {
                $v = $hooks['blocked_train_alt_transport'] ?? 'unknown';
                if ($v === 'unknown' || $v === '') { $missing[] = 'blocked_train_alt_transport'; $ask[] = 'blocked_train_alt_transport'; }
            } elseif ($isStation) {
                $v = $hooks['a20_3_solution_offered'] ?? 'unknown';
                if ($v === 'unknown' || $v === '') { $missing[] = 'a20_3_solution_offered'; $ask[] = 'a20_3_solution_offered'; }
            } else {
                if ($altProvided === 'unknown' || $altProvided === '') { $missing[] = 'alt_transport_provided'; $ask[] = 'alt_transport_provided'; }
            }
        }

        // Issues (only when active)
        $mealOff = $yn($hooks['meal_offered'] ?? '');
        $hotelOff = $yn($hooks['hotel_offered'] ?? '');
        $overnight = $yn($hooks['overnight_needed'] ?? '');
        $blockedAlt = $yn($hooks['blocked_train_alt_transport'] ?? '');
        $stationOffer = $yn($hooks['a20_3_solution_offered'] ?? '');
        $altProvidedNorm = $yn($altProvided);

        if ($active && $mealOff === 'no') {
            $issues[] = 'Måltider/forfriskninger blev ikke tilbudt (Art. 20(2)(a)).';
        }
        if ($active && $hotelOff === 'no' && $overnight === 'yes') {
            $issues[] = 'Hotel/indkvartering blev ikke tilbudt ved nødvendig overnatning (Art. 20(2)(b)).';
        }
        if ($active && $isTrack && $blockedAlt === 'no') {
            $issues[] = 'Transport væk fra blokeret tog blev ikke tilbudt (Art. 20(2)(c)).';
        }
        if ($active && $isStation && $stationOffer === 'no') {
            $issues[] = 'Alternativ transport ved afbrudt forbindelse blev ikke tilbudt (Art. 20(3)).';
        } elseif ($active && !$isTrack && !$isStation && $altProvidedNorm === 'no') {
            $issues[] = 'Alternativ transport ved afbrudt forbindelse blev ikke tilbudt (Art. 20(3)).';
        }
        if ($pmrActive && $hooks['pmr_user'] === 'Ja' && $hooks['assistance_pmr_priority_applied'] === 'Nej') {
            $issues[] = 'PMR-prioritet mangler (Art. 20(5)).';
        }

        $status = null;
        if ($active) {
            if (!empty($issues)) {
                $status = false;
            } elseif (empty($missing)) {
                $status = true;
            }
        } elseif ($pmrPartial) {
            if (!empty($issues)) {
                $status = false;
            } elseif (($hooks['assistance_pmr_priority_applied'] ?? 'unknown') === 'Ja') {
                $status = true;
            }
        }

        if ($mealOff === 'no') {
            $recs[] = 'Anmod om refusion af selvbetalte måltider (bilag uploadet).';
        }
        if ($hotelOff === 'no' && $overnight === 'yes') {
            $recs[] = 'Dokumentér behov for overnatning og selvbetalte udgifter.';
        }
        if ($active && $isStation && $stationOffer === 'no') {
            $recs[] = 'Dokumentér manglende alternativ transport og selvbetalt løsning.';
        } elseif ($active && !$isTrack && !$isStation && $altProvidedNorm === 'no') {
            $recs[] = 'Dokumentér manglende alternativ transport og selvbetalt løsning.';
        }

        return [
            'hooks' => $hooks,
            'compliance_status' => $status,
            'missing' => array_values(array_unique($missing)),
            'ask' => array_values(array_unique($ask)),
            'issues' => array_values(array_unique($issues)),
            'recommendations' => array_values(array_unique($recs)),
            'labels' => ['jf. Art. 20(2), 20(3), 20(5)'],
        ];
    }

    private function tri(mixed $v): string
    {
        $s = strtolower(trim((string)$v));
        return match ($s) {
            'ja','yes','y','true','1' => 'Ja',
            'nej','no','n','false','0' => 'Nej',
            default => 'unknown',
        };
    }

    /**
     * Build normalized hooks.
     * @return array<string,mixed>
     */
    private function collectHooks(array $journey, array $meta): array
    {
        $incident = (array)($journey['incident'] ?? $meta['incident'] ?? []);
        $delayExpected = (int)($journey['delayExpectedMinutes'] ?? $meta['delayExpectedMinutes'] ?? 0);
        $art20Fallback = (string)($meta['art20_expected_delay_60'] ?? '');
        $active = $delayExpected >= 60
            || !empty($incident['cancellation'])
            || !empty($incident['missed_connection'])
            || $art20Fallback === 'yes';

        $hook = function(string $key, string $type='tri') use ($meta) {
            $v = $meta[$key] ?? null;
            if ($type === 'tri') { return $this->tri($v); }
            return $v;
        };

        return [
            '_active' => $active,
            'art20_expected_delay_60' => $art20Fallback,
            'stranded_location' => (string)($meta['stranded_location'] ?? ''),
            'journey_outcome' => $hook('journey_outcome', 'raw'),
            'meal_offered' => $hook('meal_offered'),
            'assistance_meals_unavailable_reason' => $hook('assistance_meals_unavailable_reason', 'raw'),
            'hotel_offered' => $hook('hotel_offered'),
            'overnight_needed' => $hook('overnight_needed'),
            'assistance_hotel_transport_included' => $hook('assistance_hotel_transport_included'),
            'assistance_hotel_accessible' => $hook('assistance_hotel_accessible'),
            'blocked_train_alt_transport' => $hook('blocked_train_alt_transport'),
            'blocked_no_transport_action' => $hook('blocked_no_transport_action', 'raw'),
            'assistance_blocked_transport_to' => $hook('assistance_blocked_transport_to', 'raw'),
            'assistance_blocked_transport_time' => $hook('assistance_blocked_transport_time', 'raw'),
            'assistance_blocked_transport_possible' => $hook('assistance_blocked_transport_possible'),
            'alt_transport_provided' => $hook('alt_transport_provided'),
            'assistance_alt_transport_offered_by' => $hook('assistance_alt_transport_offered_by', 'raw'),
            'assistance_alt_transport_type' => $hook('assistance_alt_transport_type', 'raw'),
            'assistance_alt_to_destination' => $hook('assistance_alt_to_destination', 'raw'),
            'assistance_alt_departure_time' => $hook('assistance_alt_departure_time', 'raw'),
            'assistance_alt_arrival_time' => $hook('assistance_alt_arrival_time', 'raw'),
            // Art.20(3) station-specific
            'a20_3_solution_offered' => $hook('a20_3_solution_offered'),
            'a20_3_solution_type' => $hook('a20_3_solution_type', 'raw'),
            'a20_3_solution_offered_by' => $hook('a20_3_solution_offered_by', 'raw'),
            'a20_3_no_solution_action' => $hook('a20_3_no_solution_action', 'raw'),
            'a20_3_outcome' => $hook('a20_3_outcome', 'raw'),
            'a20_3_self_arranged_type' => $hook('a20_3_self_arranged_type', 'raw'),
            'a20_3_self_paid_amount' => $hook('a20_3_self_paid_amount', 'raw'),
            'a20_3_self_paid_currency' => $hook('a20_3_self_paid_currency', 'raw'),
            'a20_3_self_paid_receipt' => $hook('a20_3_self_paid_receipt', 'raw'),
            'pmr_user' => $this->tri($meta['pmr_user'] ?? $meta['pmrUser'] ?? ''),
            'assistance_pmr_priority_applied' => $hook('assistance_pmr_priority_applied'),
            'assistance_pmr_companion_supported' => $hook('assistance_pmr_companion_supported'),
            'assistance_pmr_dog_supported' => $hook('assistance_pmr_dog_supported'),
            // Self-paid
            'meal_self_paid_amount' => $hook('meal_self_paid_amount', 'raw'),
            'meal_self_paid_currency' => $hook('meal_self_paid_currency', 'raw'),
            'hotel_self_paid_amount' => $hook('hotel_self_paid_amount', 'raw'),
            'hotel_self_paid_currency' => $hook('hotel_self_paid_currency', 'raw'),
            'hotel_self_paid_nights' => $hook('hotel_self_paid_nights', 'raw'),
            'blocked_self_paid_transport_type' => $hook('blocked_self_paid_transport_type', 'raw'),
            'blocked_self_paid_amount' => $hook('blocked_self_paid_amount', 'raw'),
            'blocked_self_paid_currency' => $hook('blocked_self_paid_currency', 'raw'),
            'alt_self_paid_amount' => $hook('alt_self_paid_amount', 'raw'),
            'alt_self_paid_currency' => $hook('alt_self_paid_currency', 'raw'),
        ];
    }
}
