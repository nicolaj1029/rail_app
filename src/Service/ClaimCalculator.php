<?php
declare(strict_types=1);

namespace App\Service;

/**
 * ClaimCalculator v1 — unified computation for refund (Art.18), compensation (Art.19) and expenses (Art.20), incl. 25% service fee.
 */
class ClaimCalculator
{
    /**
     * @param array $input See user blueprint ClaimInput. Keys are loosely typed; missing keys handled defensively.
     * @return array ClaimOutput as per blueprint.
     */
    public function calculate(array $input): array
    {
        $country = (string)($input['country_code'] ?? '');
        $currency = (string)($input['currency'] ?? 'EUR');
        $ticketTotal = (float)($input['ticket_price_total'] ?? 0.0);
        $trip = (array)($input['trip'] ?? []);
        $legs = (array)($trip['legs'] ?? []);
        $throughTicket = (bool)($trip['through_ticket'] ?? true);
        $disruption = (array)($input['disruption'] ?? []);
        $choices = (array)($input['choices'] ?? []);
        $expensesIn = (array)($input['expenses'] ?? []);
        $alreadyRefunded = (float)($input['already_refunded'] ?? 0.0);

        // Exemption profile
        $journeyForProfile = [
            'segments' => $this->mapLegsToSegments($legs, $country),
            'country' => ['value' => $country],
        ];
        $serviceScope = (string)($input['service_scope'] ?? '');
        if ($serviceScope === 'intl_beyond_eu') { $journeyForProfile['is_international_beyond_eu'] = true; }
        elseif ($serviceScope === 'intl_inside_eu') { $journeyForProfile['is_international_inside_eu'] = true; }
        elseif ($serviceScope === 'long_domestic') { $journeyForProfile['is_long_domestic'] = true; }
        $profile = (new ExemptionProfileBuilder())->build($journeyForProfile);
        // Compose exemptions applied: collect all articles set to false
        $exemptionsApplied = [];
        foreach (($profile['articles'] ?? []) as $k => $v) {
            if ($v === false) { $exemptionsApplied[] = $k; }
        }
        $exemptionsApplied = array_values(array_unique($exemptionsApplied));
        $art19Allowed = ($profile['articles']['art19'] ?? true) === true;
        $compAllowed = $art19Allowed;
        $assistAllowed = ($profile['articles']['art20_2'] ?? true) === true;

        // Gatekeepers
        $selfInflicted = (bool)($disruption['self_inflicted'] ?? false);
        $extraordinary = (bool)($disruption['extraordinary'] ?? false);
        $notifiedBefore = (bool)($disruption['notified_before_purchase'] ?? false);
        if ($selfInflicted) { $compAllowed = false; }
        if ($extraordinary) { $compAllowed = false; }
        if ($notifiedBefore) { $compAllowed = false; }

        // Delay calculation — prefer provided, else compute; if non-EU legs present and eu_only=true, filter
        $delay = $this->resolveDelayMinutes($disruption, $legs);
        if (!empty($disruption['eu_only'])) {
            $delay = $this->resolveDelayMinutesEUOnly($disruption, $legs);
        }

        // 3) Compensation
        $compPct = 0;
        $compEligible = false;
        if ($compAllowed) {
            if ($delay >= 120) { $compPct = 50; }
            elseif ($delay >= 60) { $compPct = 25; }
            $compEligible = $compPct > 0;
        }
        // Basis — Art. 19(3): leg price if available; for return with no split price -> 1/2; else whole fare
        [$compBaseAmount, $compBaseLabel] = $this->computeCompensationBasis($ticketTotal, $legs, $throughTicket, $trip, $disruption);
    $compAmount = $compEligible ? round($compBaseAmount * ($compPct / 100), 2) : 0.0;
        $compRule = 'EU'; // placeholder for national overrides
        if (!$compAllowed) {
            // If Art. 19 is nationally exempted (profile), mark as such; else keep EU basis
            $compEligible = false; $compPct = 0; $compAmount = 0.0;
            if (!$art19Allowed) {
                $compBaseLabel = 'Art.19 undtaget (national exemption)';
                $compRule = 'N/A';
            } else {
                $compBaseLabel = 'No compensation (<60 min / extraordinary)';
                $compRule = 'EU';
            }
        }

        // 2) Refund (Art.18) — simplified decision using choices + downgrade comparator
        $refundBasis = 'none';
        $refundAmount = 0.0;
        if (!empty($choices['wants_refund'])) {
            $meetsArt18 = ($delay >= 60) || !empty($disruption['trip_cancelled']) || !empty($choices['wants_reroute_same_soonest']) || !empty($choices['wants_reroute_later_choice']);
            if ($meetsArt18) {
                $refundBasis = 'Art.18(1)(a) whole fare';
                $refundAmount = round($ticketTotal, 2);
            }
        }

        // Downgrade-based partial refund (heuristic) — applies when class/amenity downgraded
        $refusion = (array)($input['refusion'] ?? []);
        $downgrade = (array)($refusion['downgrade'] ?? []);
        if (empty($downgrade)) {
            // Try to assemble context from available inputs if not provided
            $dwCtx = [
                'fare_class_purchased' => $input['fare_class_purchased'] ?? null,
                'class_delivered_status' => $input['class_delivered_status'] ?? null,
                'reserved_amenity_delivered' => $input['reserved_amenity_delivered'] ?? null,
                'promised_facilities' => $input['promised_facilities'] ?? null,
                'facilities_delivered_status' => $input['facilities_delivered_status'] ?? null,
                'downgrade_occurred' => $input['downgrade_occurred'] ?? null,
                'downgrade_comp_basis' => $input['downgrade_comp_basis'] ?? null,
            ];
            $downgrade = (new \App\Service\DowngradeComparator())->assess($dwCtx);
        }
        $downgradeAmount = 0.0; $downgradeLabel = null;
        if (!empty($downgrade) && ($downgrade['severity'] ?? 'none') !== 'none') {
            $pct = (float)($downgrade['suggested_pct'] ?? 0.0);
            if ($pct > 0) {
                $downgradeAmount = round($ticketTotal * $pct, 2);
                $refBasis = 'Downgrade refund (' . (int)round($pct * 100) . '%; ' . ($downgrade['basis'] ?? 'heuristic') . ')';
                if ($refundAmount <= 0.0) { $refundBasis = $refBasis; $refundAmount = $downgradeAmount; }
                else { $refundBasis .= ' + ' . $refBasis; $refundAmount = round($refundAmount + $downgradeAmount, 2); }
                $downgradeLabel = $refBasis;
            }
        }

        // Avoid double coverage: if refund covers whole fare, suppress compensation for same portion
        if ($refundAmount >= $ticketTotal - 0.01) {
            $compEligible = false; $compPct = 0; $compAmount = 0.0; $compBasis = '-';
        }

        // 4) Expenses (Art.20)
        $expenses = [
            'meals' => (float)($expensesIn['meals'] ?? 0),
            'hotel' => (float)($expensesIn['hotel'] ?? 0),
            'alt_transport' => (float)($expensesIn['alt_transport'] ?? 0),
            'other' => (float)($expensesIn['other'] ?? 0),
        ];
        $expensesTotal = $assistAllowed ? array_sum($expenses) : 0.0;
        if (!$assistAllowed && $expensesTotal > 0) {
            $exemptionsApplied[] = 'art20_2_blocked';
            $exemptionsApplied = array_values(array_unique($exemptionsApplied));
        }

        // 5) Gross, fee, net
        $gross = max($refundAmount + $compAmount + $expensesTotal - $alreadyRefunded, 0.0);
        $serviceFeePct = 25;
        // Service fee mode: default on gross; if 'expenses_only', only apply on expenses
        $feeMode = (string)($input['service_fee_mode'] ?? 'gross');
        $feeBase = ($feeMode === 'expenses_only') ? max($expensesTotal, 0.0) : $gross;
        // Floor to 2 decimals to avoid overpay by rounding up
        $serviceFee = floor(($feeBase * ($serviceFeePct / 100)) * 100) / 100;
        $net = floor(($gross - $serviceFee) * 100) / 100;

        // 6) Output
        return [
            'breakdown' => [
                'refund' => ['basis' => $refundBasis, 'amount' => $refundAmount] + ($downgradeLabel ? ['downgrade_component' => $downgradeAmount] : []),
                'compensation' => [
                    'eligible' => $compEligible,
                    'delay_minutes' => $delay,
                    'pct' => $compPct,
                    'basis' => $compBaseLabel,
                    'amount' => $compAmount,
                    'rule' => $compRule,
                ],
                'expenses' => $expenses + ['total' => $expensesTotal],
                'deductions' => ['already_refunded' => $alreadyRefunded],
            ],
            'totals' => [
                'gross_claim' => $gross,
                'service_fee_pct' => $serviceFeePct,
                'service_fee_amount' => $serviceFee,
                'net_to_client' => $net,
                'currency' => $currency,
            ],
            'flags' => [
                'extraordinary' => $extraordinary,
                'self_inflicted' => $selfInflicted,
                'exemptions_applied' => !empty($exemptionsApplied) ? array_values(array_unique($exemptionsApplied)) : null,
                'manual_review' => false,
            ],
        ];
    }

    private function mapLegsToSegments(array $legs, string $fallbackCountry): array
    {
        $segments = [];
        foreach ($legs as $leg) {
            $segments[] = [
                'operator' => $leg['operator'] ?? null,
                'trainCategory' => $leg['product'] ?? null,
                'country' => $leg['country'] ?? $fallbackCountry,
                'schedArr' => $leg['scheduled_arr'] ?? null,
                'actArr' => $leg['actual_arr'] ?? null,
            ];
        }
        return $segments;
    }

    private function resolveDelayMinutes(array $disruption, array $legs): int
    {
        if (isset($disruption['delay_minutes_final'])) {
            return max(0, (int)$disruption['delay_minutes_final']);
        }
        // Compute from last leg actual vs scheduled
        $last = !empty($legs) ? $legs[array_key_last($legs)] : [];
        $schedArr = (string)($last['scheduled_arr'] ?? '');
        $actArr = (string)($last['actual_arr'] ?? '');
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { return max(0, (int)round(($t2 - $t1) / 60)); }
        }
        return 0;
    }

    /**
     * Compute delay considering only portions within EU (heuristic: sum per-leg positive delays for legs flagged eu=true)
     */
    private function resolveDelayMinutesEUOnly(array $disruption, array $legs): int
    {
        // if explicit is provided, trust it
        if (isset($disruption['delay_minutes_final_eu'])) {
            return max(0, (int)$disruption['delay_minutes_final_eu']);
        }
        $sum = 0;
        foreach ($legs as $leg) {
            if (empty($leg['eu'])) { continue; }
            $sched = (string)($leg['scheduled_arr'] ?? '');
            $act = (string)($leg['actual_arr'] ?? '');
            if ($sched && $act) {
                $t1 = strtotime($sched); $t2 = strtotime($act);
                if ($t1 && $t2) { $sum += max(0, (int)round(($t2 - $t1) / 60)); }
            }
        }
        return $sum;
    }

    /**
     * Returns [numericBase, label] for compensation under Art.19(3).
     * Rules implemented (E3):
     * - If per-leg prices are provided and a delayed leg index is known, use that leg's price.
     * - If it is a return ticket and no per-leg prices are provided, use half of the total price.
     * - Otherwise use the full price paid for the service (whole fare).
     */
    private function computeCompensationBasis(float $ticketTotal, array $legs, bool $throughTicket, array $trip, array $disruption): array
    {
        $legsCount = count($legs);

        // 1) If caller provided explicit per-leg prices and a delayed leg index, use it
        $legPrices = [];
        foreach ($legs as $idx => $leg) {
            if (isset($leg['price']) && is_numeric($leg['price'])) {
                $legPrices[$idx] = (float)$leg['price'];
            }
        }
        $delayedIdx = null;
        if (isset($disruption['delayed_leg_index'])) {
            $delayedIdx = max(0, (int)$disruption['delayed_leg_index']);
        } elseif ($legsCount > 0) {
            // Fallback: assume last leg determines final delay
            $delayedIdx = $legsCount - 1;
        }
        if (!empty($legPrices) && $delayedIdx !== null && array_key_exists($delayedIdx, $legPrices)) {
            return [max(0.0, $legPrices[$delayedIdx]), 'Art.19(3) leg fare (per-leg price)'];
        }

        // 2) Return tickets → half price if per-leg prices are unknown
        $isReturn = false;
        if (!empty($trip['return_ticket']) || !empty($trip['return'])) { $isReturn = true; }
        if (($trip['trip_type'] ?? '') === 'return') { $isReturn = true; }
        if (!$isReturn && $legsCount === 2) {
            // Heuristic: two legs with dates >=1 day apart → treat as return
            $a1 = (string)($legs[0]['scheduled_arr'] ?? '');
            $a2 = (string)($legs[1]['scheduled_arr'] ?? '');
            if ($a1 && $a2) {
                $d1 = strtotime(substr($a1, 0, 10));
                $d2 = strtotime(substr($a2, 0, 10));
                if ($d1 && $d2 && abs($d2 - $d1) >= 86400) { $isReturn = true; }
            }
        }
        if ($isReturn) {
            return [max(0.0, $ticketTotal / 2), 'Art.19(3) 1/2 fare (return, no split prices)'];
        }

        // 3) Through ticket/multi-leg default → whole fare
        if ($throughTicket && $legsCount >= 2) {
            return [max(0.0, $ticketTotal), 'Art.19(3) whole fare (through ticket)'];
        }

        // 4) Single-leg/simple default → whole fare
        return [max(0.0, $ticketTotal), 'Art.19(3) whole fare'];
    }
}
