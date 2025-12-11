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
 *  - blocked: blocked_train_alt_transport, assistance_blocked_transport_to/time/possible
 *  - alternative transport: alt_transport_provided, assistance_alt_transport_offered_by/type/to_destination/departure_time/arrival_time
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

        $missing = [];
        $ask = [];
        $issues = [];
        $recs = [];

        $ynFields = [
            'meal_offered', 'hotel_offered', 'blocked_train_alt_transport', 'alt_transport_provided',
        ];
        foreach ($ynFields as $f) {
            if (!$active) { continue; }
            $v = $hooks[$f] ?? 'unknown';
            if ($v === 'unknown' || $v === '') {
                $missing[] = $f;
                $ask[] = $f;
            }
        }

        // Issues (only when active)
        if ($active && $hooks['meal_offered'] === 'no') {
            $issues[] = 'Måltider/forfriskninger blev ikke tilbudt (Art. 20(2)(a)).';
        }
        if ($active && $hooks['hotel_offered'] === 'no' && $hooks['overnight_needed'] === 'yes') {
            $issues[] = 'Hotel/indkvartering blev ikke tilbudt ved nødvendig overnatning (Art. 20(2)(b)).';
        }
        if ($active && $hooks['blocked_train_alt_transport'] === 'no') {
            $issues[] = 'Transport væk fra blokeret tog blev ikke tilbudt (Art. 20(2)(c)).';
        }
        if ($active && $hooks['alt_transport_provided'] === 'no') {
            $issues[] = 'Alternativ transport ved afbrudt forbindelse blev ikke tilbudt (Art. 20(3)).';
        }
        if ($active && $hooks['pmr_user'] === 'Ja' && $hooks['assistance_pmr_priority_applied'] === 'Nej') {
            $issues[] = 'PMR-prioritet mangler (Art. 20(5)).';
        }

        $status = null;
        if ($active) {
            if (!empty($issues)) {
                $status = false;
            } elseif (empty($missing)) {
                $status = true;
            }
        }

        if ($hooks['meal_offered'] === 'no') {
            $recs[] = 'Anmod om refusion af selvbetalte måltider (bilag uploadet).';
        }
        if ($hooks['hotel_offered'] === 'no' && $hooks['overnight_needed'] === 'yes') {
            $recs[] = 'Dokumentér behov for overnatning og selvbetalte udgifter.';
        }
        if ($hooks['alt_transport_provided'] === 'no' && $active) {
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
            'meal_offered' => $hook('meal_offered'),
            'assistance_meals_unavailable_reason' => $hook('assistance_meals_unavailable_reason', 'raw'),
            'hotel_offered' => $hook('hotel_offered'),
            'overnight_needed' => $hook('overnight_needed'),
            'assistance_hotel_transport_included' => $hook('assistance_hotel_transport_included'),
            'assistance_hotel_accessible' => $hook('assistance_hotel_accessible'),
            'blocked_train_alt_transport' => $hook('blocked_train_alt_transport'),
            'assistance_blocked_transport_to' => $hook('assistance_blocked_transport_to', 'raw'),
            'assistance_blocked_transport_time' => $hook('assistance_blocked_transport_time', 'raw'),
            'assistance_blocked_transport_possible' => $hook('assistance_blocked_transport_possible'),
            'alt_transport_provided' => $hook('alt_transport_provided'),
            'assistance_alt_transport_offered_by' => $hook('assistance_alt_transport_offered_by', 'raw'),
            'assistance_alt_transport_type' => $hook('assistance_alt_transport_type', 'raw'),
            'assistance_alt_to_destination' => $hook('assistance_alt_to_destination', 'raw'),
            'assistance_alt_departure_time' => $hook('assistance_alt_departure_time', 'raw'),
            'assistance_alt_arrival_time' => $hook('assistance_alt_arrival_time', 'raw'),
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
            'blocked_self_paid_amount' => $hook('blocked_self_paid_amount', 'raw'),
            'blocked_self_paid_currency' => $hook('blocked_self_paid_currency', 'raw'),
            'alt_self_paid_amount' => $hook('alt_self_paid_amount', 'raw'),
            'alt_self_paid_currency' => $hook('alt_self_paid_currency', 'raw'),
        ];
    }
}
