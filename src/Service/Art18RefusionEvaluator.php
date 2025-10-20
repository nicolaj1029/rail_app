<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Step Refusion (Art. 18 + CIV + Art. 10 + dele af 20) evaluator.
 * Returns hooks, missing, inferred outcome and UI hints for automated flow.
 */
class Art18RefusionEvaluator
{
    /**
     * @param array $journey
     * @param array $meta Hooks from the form/auto pipeline. See README in code.
     * @return array{
     *   minutes:int|null,
     *   triggers: array{delay60_or_reason:bool},
     *   hooks: array<string,mixed>,
     *   missing: string[],
     *   outcome: string|null,
     *   reasoning: string[],
     *   ui_banners: string[],
     *   limits: array<string,mixed>
     * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $minutes = $this->computeDelayMinutes($journey);
        $profile = (new ExemptionProfileBuilder())->build($journey);

        // Collect hooks (E + F replacements and core flags)
        $hooks = [
            // Reasons (B)
            'reason_delay' => (bool)($meta['reason_delay'] ?? false),
            'reason_cancellation' => (bool)($meta['reason_cancellation'] ?? false),
            'reason_missed_conn' => (bool)($meta['reason_missed_conn'] ?? false),
            // Claim type (C)
            'claim_refund_ticket' => (bool)($meta['claim_refund_ticket'] ?? false),
            'claim_rerouting' => (bool)($meta['claim_rerouting'] ?? false),
            'claim_compensation' => (bool)($meta['claim_compensation'] ?? false),
            'claim_other_costs' => (bool)($meta['claim_other_costs'] ?? false),
            // E – Rerouting (tightened)
            'reroute_same_conditions_soonest' => $this->triStr($meta['reroute_same_conditions_soonest'] ?? 'unknown'),
            'reroute_later_at_choice' => $this->triStr($meta['reroute_later_at_choice'] ?? 'unknown'),
            'reroute_info_within_100min' => $this->triStr($meta['reroute_info_within_100min'] ?? 'unknown'),
            'reroute_extra_costs' => $this->triStr($meta['reroute_extra_costs'] ?? 'unknown'),
            'reroute_extra_costs_amount' => $meta['reroute_extra_costs_amount'] ?? null,
            'currency' => $meta['currency'] ?? 'EUR',
            'downgrade_occurred' => $this->triStr($meta['downgrade_occurred'] ?? 'unknown'),
            'downgrade_comp_basis' => $meta['downgrade_comp_basis'] ?? null,
            // D – Refund specifics
            'trip_cancelled_return_to_origin' => $this->triStr($meta['trip_cancelled_return_to_origin'] ?? 'unknown'),
            'refund_requested' => $this->triStr($meta['refund_requested'] ?? 'unknown'),
            'refund_form_selected' => $meta['refund_form_selected'] ?? null, // 'Kontant'|'Voucher'|... 
            // F – Expenses / Assistance
            'meal_offered' => $this->triStr($meta['meal_offered'] ?? 'unknown'),
            'hotel_offered' => $this->triStr($meta['hotel_offered'] ?? 'unknown'),
            'overnight_needed' => $this->triStr($meta['overnight_needed'] ?? 'unknown'),
            'blocked_train_alt_transport' => $this->triStr($meta['blocked_train_alt_transport'] ?? 'unknown'),
            'alt_transport_provided' => $this->triStr($meta['alt_transport_provided'] ?? 'unknown'),
            'extra_expense_upload' => $meta['extra_expense_upload'] ?? null,
            'delay_confirmation_received' => $this->triStr($meta['delay_confirmation_received'] ?? 'unknown'),
            'delay_confirmation_upload' => $meta['delay_confirmation_upload'] ?? null,
            // G – Force majeure / egen skyld
            'extraordinary_claimed' => $this->triStr($meta['extraordinary_claimed'] ?? 'unknown'),
            'extraordinary_type' => $meta['extraordinary_type'] ?? null,
            'self_inflicted' => (bool)($meta['self_inflicted'] ?? false),
            'third_party_fault' => (bool)($meta['third_party_fault'] ?? false),
        ];

        $delay60 = ($minutes !== null) ? ($minutes >= 60) : false;
        $reasonTrigger = (bool)$hooks['reason_delay'] || (bool)$hooks['reason_cancellation'] || (bool)$hooks['reason_missed_conn'];
        $triggers = [ 'delay60_or_reason' => ($delay60 || $reasonTrigger) ];

        // Exemption banners
        $uiBanners = [];
        if (isset($profile['articles']['art18_3']) && $profile['articles']['art18_3'] === false) {
            $uiBanners[] = '⚠️ 100-min-reglen kan være undtaget her.';
        }
        if (isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false) {
            $uiBanners[] = '⚠️ Assistance (måltider/hotel/transport) kan være undtaget lokalt.';
        }

        // Limits (hotel nights under extraordinary)
        $limits = [ 'hotel_nights_cap' => ($hooks['extraordinary_claimed'] === 'Ja' ? 3 : null) ];

        // Outcome logic
        $reasoning = [];
        $outcome = null;

        // Downgrade assessment (lovet vs. leveret)
        $downgrade = (new DowngradeComparator())->assess([
            'fare_class_purchased' => $meta['fare_class_purchased'] ?? null,
            'class_delivered_status' => $meta['class_delivered_status'] ?? null,
            'reserved_amenity_delivered' => $meta['reserved_amenity_delivered'] ?? null,
            'promised_facilities' => $meta['promised_facilities'] ?? null,
            'facilities_delivered_status' => $meta['facilities_delivered_status'] ?? null,
            'downgrade_occurred' => $meta['downgrade_occurred'] ?? null,
            'downgrade_comp_basis' => $meta['downgrade_comp_basis'] ?? null,
        ]);

        $art19Applies = !isset($profile['articles']['art19']) || $profile['articles']['art19'] !== false;
        if ($hooks['self_inflicted'] || $hooks['extraordinary_claimed'] === 'Ja') {
            $outcome = 'Afslag (egen skyld / force majeure)';
            $reasoning[] = 'Egen skyld eller ekstraordinære forhold angivet.';
        } elseif ($downgrade['severity'] !== 'none') {
            $outcome = 'Refusion pga. downgrade (Art.18 a–c)';
            $reasoning[] = 'Foreslået delvis refusion: ' . (int)round($downgrade['suggested_pct'] * 100) . '% (' . $downgrade['basis'] . ').';
            foreach ((array)$downgrade['reasoning'] as $r) { $reasoning[] = $r; }
        } elseif ((bool)$hooks['claim_refund_ticket']) {
            // Only allow refund outcome if threshold or cancellation/missed-connection applies
            if ($delay60 || (bool)$hooks['reason_cancellation'] || (bool)$hooks['reason_missed_conn']) {
                $outcome = 'Refusion jf. Art.18(1)(a)';
                $reasoning[] = 'Passageren ønsker refusion som primært krav.';
            } else {
                $outcome = 'Ingen refusion (<60 min, Art. 18)';
                $reasoning[] = 'Tærskel 60 min ikke nået.';
            }
        } elseif ((bool)$hooks['claim_rerouting'] && $hooks['reroute_info_within_100min'] === 'Ja') {
            $outcome = 'Omlægning jf. Art.18(1)(b/c)';
            $reasoning[] = 'Omlægning valgt og 100-min-regel opfyldt.';
        } elseif ((bool)$hooks['claim_other_costs'] && $hooks['meal_offered'] === 'Nej') {
            $outcome = 'Manglende assistance, Art.20(2) → udgiftsrefusion';
            $reasoning[] = 'Måltider/forfriskninger ikke tilbudt.';
        } else {
            if ($art19Applies) {
                $outcome = 'Krav sendes til kompensationsflow (Art.19)';
            } else {
                $outcome = 'EU-kompensation undtaget — fortsæt med refusion/omlægning (Art.18)';
                $reasoning[] = 'Art. 19/20(2)/30(2) undtaget nationalt; kun Art.18 anvendes.';
            }
        }

        $missing = $this->missingHooks($hooks, $triggers);
        // Ensure we ask for evidence if downgrade suggested but inputs are sparse
        if ($downgrade['severity'] !== 'none') {
            foreach (['fare_class_purchased','class_delivered_status','reserved_amenity_delivered','promised_facilities','facilities_delivered_status'] as $k) {
                if (!isset($meta[$k])) { $missing[] = $k; }
            }
        }

        return [
            'minutes' => $minutes,
            'triggers' => $triggers,
            'hooks' => $hooks,
            'missing' => array_values(array_unique($missing)),
            'outcome' => $outcome,
            'reasoning' => $reasoning,
            'ui_banners' => $uiBanners,
            'limits' => $limits + ['downgrade' => $downgrade],
        ];
    }

    private function triStr($v): string
    {
        $s = (string)$v;
        if ($s === 'Ja' || $s === 'yes' || $s === 'true' || $s === '1') { return 'Ja'; }
        if ($s === 'Nej' || $s === 'no' || $s === 'false' || $s === '0') { return 'Nej'; }
        if ($s === 'Ved ikke' || $s === 'unknown' || $s === '') { return 'Ved ikke'; }
        return $s; // already one of Ja/Nej/Ved ikke or specific value
    }

    private function computeDelayMinutes(array $journey): ?int
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1) / 60)); }
        }
        $depDate = (string)($journey['depDate']['value'] ?? '');
        $sched = (string)($journey['schedArrTime']['value'] ?? '');
        $act = (string)($journey['actualArrTime']['value'] ?? '');
        if ($depDate && $sched && $act) {
            $t1 = strtotime($depDate . 'T' . $sched . ':00');
            $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1) / 60)); }
        }
        return null;
    }

    /**
     * Determine which hooks are required and still unknown.
     * Threshold: only ask E/F blocks if triggers.delay60_or_reason is true.
     * @param array<string,mixed> $hooks
     * @param array{delay60_or_reason:bool} $triggers
     * @return string[]
     */
    private function missingHooks(array $hooks, array $triggers): array
    {
        $missing = [];
        // Always important
        foreach (['reason_delay','reason_cancellation','reason_missed_conn','claim_refund_ticket','claim_rerouting','claim_other_costs'] as $k) {
            if (!isset($hooks[$k])) { $missing[] = $k; }
        }
        if ($triggers['delay60_or_reason']) {
            $need = [
                'reroute_same_conditions_soonest','reroute_later_at_choice',
                // Only require the 100-min hook if Art.18(3) applies
                'reroute_extra_costs','downgrade_occurred','meal_offered','hotel_offered','alt_transport_provided'
            ];
            $require100 = true;
            if (isset($profile['articles']['art18_3']) && $profile['articles']['art18_3'] === false) {
                $require100 = false;
            }
            if ($require100) { array_splice($need, 2, 0, ['reroute_info_within_100min']); }
            foreach ($need as $k) {
                if (!isset($hooks[$k]) || $hooks[$k] === 'Ved ikke') { $missing[] = $k; }
            }
        }
        return array_values(array_unique($missing));
    }
}
