<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

/**
 * ClaimCalculator v1 — unified computation for refund (Art.18), compensation (Art.19) and expenses (Art.20).
 * Service fee is configurable (see config/app.php: App.claim_service_fee_pct / App.claim_service_fee_mode).
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
        $liablePartyTrip = (string)($trip['liable_party'] ?? ''); // 'operator' | 'retailer' | ''
        $disruption = (array)($input['disruption'] ?? []);
        $choices = (array)($input['choices'] ?? []);
        $expensesIn = (array)($input['expenses'] ?? []);
        $alreadyRefunded = (float)($input['already_refunded'] ?? 0.0);
        $applyMinThreshold = (bool)($input['apply_min_threshold'] ?? false);

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
        $arts = (array)($profile['articles'] ?? []);
        $assistAllowed = (($arts['art20_2'] ?? true) === true)
            || ($arts['art20_2a'] ?? false)
            || ($arts['art20_2b'] ?? false)
            || ($arts['art20_2c'] ?? false)
            || ($arts['art20_3'] ?? false);

        // Gatekeepers
        $selfInflicted = (bool)($disruption['self_inflicted'] ?? false);
        $extraordinary = (bool)($disruption['extraordinary'] ?? false);
        $extraordinaryType = (string)($disruption['extraordinary_type'] ?? '');
        $notifiedBefore = (bool)($disruption['notified_before_purchase'] ?? false);
        if ($selfInflicted) { $compAllowed = false; }
        if ($extraordinary) {
            // Art. 19(10) carve-out: operator's own staff strikes do NOT remove compensation rights
            if (!$this->isInternalStrike($extraordinaryType)) {
                $compAllowed = false;
            }
        }
        if ($notifiedBefore) { $compAllowed = false; }

        // Delay calculation — prefer provided, else compute; if non-EU legs present and eu_only=true, filter
        $delay = $this->resolveDelayMinutes($disruption, $legs);
        if (!empty($disruption['eu_only'])) {
            $delay = $this->resolveDelayMinutesEUOnly($disruption, $legs);
        }

        // 3) Compensation
        $compPct = 0;
        $overridePct = null;
        if (isset($input['override_comp_pct']) && is_numeric($input['override_comp_pct'])) {
            $ov = (int)$input['override_comp_pct'];
            if (in_array($ov, [25,50], true)) { $overridePct = $ov; }
        }
        $compEligible = false;
        $missedConnection = (bool)($disruption['missed_connection'] ?? false);
        $multiLeg = count($legs) > 1;
        $art12Retailer75 = false;
        if ($compAllowed) {
            // Art.12(4) special rule: retailer liable for missed connection → 75% + full refund (handled below)
            if ($missedConnection && $multiLeg && $liablePartyTrip === 'retailer') {
                $art12Retailer75 = true;
                $compPct = 75; // applied to whole transaction amount (or basis derived below)
                $compEligible = true;
            } else {
                if ($delay >= 120) { $compPct = 50; }
                elseif ($delay >= 60) { $compPct = 25; }
                if ($overridePct !== null) { $compPct = max($compPct, $overridePct); }
                $compEligible = $compPct > 0;
            }
        }
        // Basis — Art. 19(3): leg price if available; for return with no split price -> 1/2; else whole fare
        [$compBaseAmount, $compBaseLabel] = $this->computeCompensationBasis($ticketTotal, $legs, $throughTicket, $trip, $disruption);
        $compAmount = $compEligible ? round($compBaseAmount * ($compPct / 100), 2) : 0.0;
        // Minimum threshold (Art. 19(8)) — suppress very small amounts ≤ 4 EUR when enabled
        if ($compEligible && $applyMinThreshold && $compAmount > 0 && $compAmount < 4.0) {
            $compEligible = false;
            $compPct = 0;
            $compAmount = 0.0;
            $compBaseLabel = 'Art.19(8) minimum threshold (≤ 4 EUR)';
        }
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
            // Art.18(1)(a): refund eligibility can be triggered by upstream gating (e.g., cancellation/missed/PMR/bike),
            // not necessarily by final arrival delay minutes available at computation time.
            $art18Active = !empty($disruption['art18_active']);
            $meetsArt18 = $art18Active
                || ($delay >= 60)
                || !empty($disruption['trip_cancelled'])
                || !empty($choices['wants_reroute_same_soonest'])
                || !empty($choices['wants_reroute_later_choice']);
            if ($meetsArt18) {
                $refundBasis = 'Art.18(1)(a) whole fare';
                $refundAmount = round($ticketTotal, 2);
            }
        }

        // Downgrade-based partial refund (heuristic) — applies when class/amenity downgraded.
        // If user explicitly provided CIV/Annex II downgrade inputs (downgrade_occurred=yes),
        // we avoid double counting by not also applying heuristic downgrade here.
        $dgOcc = strtolower(trim((string)($input['downgrade_occurred'] ?? '')));
        if ($dgOcc === 'ja') { $dgOcc = 'yes'; }
        if ($dgOcc === 'nej') { $dgOcc = 'no'; }
        $downgradeAmount = 0.0; $downgradeLabel = null;
        if ($dgOcc !== 'yes') {
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
        }

        // Avoid double coverage: if refund covers whole fare, suppress compensation for same portion
        if ($refundAmount >= $ticketTotal - 0.01 && !$art12Retailer75) {
            $compEligible = false; $compPct = 0; $compAmount = 0.0; $compBasis = '-';
        }
        // Art.12(4) override: refund whole fare + 75% compensation — ensure refund covers total
        if ($art12Retailer75) {
            // Force refund to whole fare if not already
            if ($refundAmount < $ticketTotal) {
                $refundAmount = round($ticketTotal, 2);
                $refundBasis = 'Art.12(4) full fare refund';
            } else {
                $refundBasis .= ' + Art.12(4) full fare refund';
            }
            $compBaseLabel = 'Art.12(4) 75% missed connection compensation';
            $compRule = 'Art12(4)';
        }

        // 4) Expenses (Art.20)
        $altTransportLabel = ($delay >= 100) ? 'Reroute costs (Art.18(3))' : 'Alternative transport';
        // Build numeric-only amounts separately to avoid summing non-numeric labels
        $expenseAmounts = [
            'meals' => (float)($expensesIn['meals'] ?? 0),
            'hotel' => (float)($expensesIn['hotel'] ?? 0),
            'alt_transport' => (float)($expensesIn['alt_transport'] ?? 0),
            'other' => (float)($expensesIn['other'] ?? 0),
        ];
        $expensesTotal = $assistAllowed ? array_sum($expenseAmounts) : 0.0;
        $expenses = $expenseAmounts + [
            'alt_transport_label' => ($expenseAmounts['alt_transport'] ?? 0) > 0 ? $altTransportLabel : null,
        ];
        if (!$assistAllowed && $expensesTotal > 0) {
            $exemptionsApplied[] = 'art20_2_blocked';
            $exemptionsApplied = array_values(array_unique($exemptionsApplied));
        }

        // 4b) Art.18 extras: reroute extra costs, return transport, and CIV/Annex II downgrade (stk. 3).
        $normYesNo = static function ($v): string {
            $s = strtolower(trim((string)$v));
            if ($s === 'ja') { return 'yes'; }
            if ($s === 'nej') { return 'no'; }
            return $s;
        };

        $rerouteExtraFlag = $normYesNo($input['reroute_extra_costs'] ?? '');
        $rerouteExtraAmount = is_numeric($input['reroute_extra_costs_amount'] ?? null)
            ? (float)$input['reroute_extra_costs_amount']
            : (float)preg_replace('/[^0-9.]/', '', (string)($input['reroute_extra_costs_amount'] ?? '0'));
        if ($rerouteExtraFlag === 'no') { $rerouteExtraAmount = 0.0; }

        $returnFlag = $normYesNo($input['return_to_origin_expense'] ?? '');
        $returnAmount = is_numeric($input['return_to_origin_amount'] ?? null)
            ? (float)$input['return_to_origin_amount']
            : (float)preg_replace('/[^0-9.]/', '', (string)($input['return_to_origin_amount'] ?? '0'));
        if ($returnFlag === 'no') { $returnAmount = 0.0; }

        // Downgrade Annex II (CIV) rate map (same as TRIN 9 UI).
        $rateMap = ['seat' => 0.25, 'couchette' => 0.50, 'sleeper' => 0.75];
        $dwOcc = $normYesNo($input['downgrade_occurred'] ?? '');
        $dwBasis = strtolower(trim((string)($input['downgrade_comp_basis'] ?? '')));
        $dwShare = is_numeric($input['downgrade_segment_share'] ?? null) ? (float)$input['downgrade_segment_share'] : 1.0;
        if (!is_finite($dwShare)) { $dwShare = 1.0; }
        $dwShare = max(0.0, min(1.0, $dwShare));
        $dwRate = $rateMap[$dwBasis] ?? 0.0;

        // If no explicit basis, attempt to infer the highest applicable downgrade rate from per-leg inputs.
        if ($dwRate <= 0.0) {
            $legBuy = (array)($input['leg_class_purchased'] ?? []);
            $legDel = (array)($input['leg_class_delivered'] ?? []);
            $legResBuy = (array)($input['leg_reservation_purchased'] ?? []);
            $legResDel = (array)($input['leg_reservation_delivered'] ?? []);
            $legDg = (array)($input['leg_downgraded'] ?? []);
            $rank = ['sleeper' => 4, 'couchette' => 3, '1st' => 2, '2nd' => 1];
            $normClass = static function (string $v): string {
                $v = strtolower(trim($v));
                if (in_array($v, ['1st_class','1st','first','1'], true)) { return '1st'; }
                if (in_array($v, ['2nd_class','2nd','second','2'], true)) { return '2nd'; }
                if (in_array($v, ['seat_reserved','free_seat'], true)) { return '2nd'; }
                return $v;
            };
            $normRes = static function (string $v): string {
                $v = strtolower(trim($v));
                if (in_array($v, ['seat_reserved','reserved','seat'], true)) { return 'reserved'; }
                if (in_array($v, ['free','free_seat'], true)) { return 'free_seat'; }
                if ($v === 'missing') { return 'missing'; }
                return $v;
            };
            $autoRateFor = static function (string $buy, string $del, string $buyRes, string $delRes) use ($rank, $normClass, $normRes): float {
                $bc = $normClass($buy);
                $dc = $normClass($del);
                $rb = $rank[$bc] ?? 0;
                $rd = $rank[$dc] ?? 0;
                if ($rb > 0 && $rd > 0 && $rb > $rd) {
                    if ($bc === 'sleeper') { return 0.75; }
                    if ($bc === 'couchette') { return 0.50; }
                    return 0.25;
                }
                $br = $normRes($buyRes);
                $dr = $normRes($delRes);
                if ($br === 'reserved' && $dr !== '' && $dr !== 'reserved') { return 0.25; }
                return 0.0;
            };

            $countLegs = max(count($legBuy), count($legDel), count($legResBuy), count($legResDel), count($legDg));
            $maxRate = 0.0;
            for ($i = 0; $i < $countLegs; $i++) {
                $buy = (string)($legBuy[$i] ?? '');
                $del = (string)($legDel[$i] ?? '');
                $buyRes = (string)($legResBuy[$i] ?? '');
                $delRes = (string)($legResDel[$i] ?? '');
                $dg = ((string)($legDg[$i] ?? '') === '1');
                $autoRate = $autoRateFor($buy, $del, $buyRes, $delRes);
                if ($dg || $autoRate > 0) {
                    if ($autoRate > $maxRate) { $maxRate = $autoRate; }
                }
            }
            $dwRate = $maxRate;
            if ($dwBasis === '' && $dwRate > 0) {
                $dwBasis = ($dwRate >= 0.74) ? 'sleeper' : (($dwRate >= 0.49) ? 'couchette' : 'seat');
            }
        }

        $downgradeAnnexiiAmount = 0.0;
        if ($dwOcc === 'yes' && $dwRate > 0.0 && $ticketTotal > 0.0) {
            $downgradeAnnexiiAmount = round($ticketTotal * $dwRate * $dwShare, 2);
        }

        // 5) Gross, fee, net
        $gross = max(
            $refundAmount
            + $compAmount
            + $expensesTotal
            + max(0.0, $rerouteExtraAmount)
            + max(0.0, $returnAmount)
            + max(0.0, $downgradeAnnexiiAmount)
            - $alreadyRefunded,
            0.0
        );
        $cfgPct = Configure::read('App.claim_service_fee_pct');
        $serviceFeePct = is_numeric($input['service_fee_pct'] ?? null)
            ? (int)$input['service_fee_pct']
            : (is_numeric($cfgPct) ? (int)$cfgPct : 12);
        if ($serviceFeePct < 0) { $serviceFeePct = 0; }
        if ($serviceFeePct > 100) { $serviceFeePct = 100; }
        // Service fee mode: default on gross; if 'expenses_only', only apply on expenses
        $feeModeCfg = (string)(Configure::read('App.claim_service_fee_mode') ?? '');
        $feeMode = (string)($input['service_fee_mode'] ?? ($feeModeCfg !== '' ? $feeModeCfg : 'gross'));
        $feeBase = ($feeMode === 'expenses_only') ? max($expensesTotal, 0.0) : $gross;
        // Floor to 2 decimals to avoid overpay by rounding up
        $serviceFee = floor(($feeBase * ($serviceFeePct / 100)) * 100) / 100;
        $net = floor(($gross - $serviceFee) * 100) / 100;

        // 6) Output
        return [
            'breakdown' => [
                'refund' => ['basis' => $refundBasis, 'amount' => $refundAmount] + ($downgradeLabel ? ['downgrade_component' => $downgradeAmount] : []),
                'art18' => [
                    'reroute_extra_costs' => $rerouteExtraAmount > 0 ? round($rerouteExtraAmount, 2) : 0.0,
                    'return_to_origin' => $returnAmount > 0 ? round($returnAmount, 2) : 0.0,
                    'downgrade_annexii' => $downgradeAnnexiiAmount > 0 ? round($downgradeAnnexiiAmount, 2) : 0.0,
                    'downgrade_annexii_rate' => $dwRate,
                    'downgrade_annexii_share' => $dwShare,
                    'downgrade_annexii_basis' => $dwBasis,
                ],
                'compensation' => [
                    'eligible' => $compEligible,
                    'delay_minutes' => $delay,
                    'pct' => $compPct,
                    'basis' => $compBaseLabel,
                    'amount' => $compAmount,
                    'rule' => $compRule,
                    'art12_4' => $art12Retailer75 ? true : false,
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
                'extraordinary_type' => $extraordinaryType ?: null,
                'self_inflicted' => $selfInflicted,
                'exemptions_applied' => !empty($exemptionsApplied) ? array_values(array_unique($exemptionsApplied)) : null,
                'manual_review' => false,
                'retailer_75' => $art12Retailer75,
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

        // 4) Separate contracts (Art. 12 OFF) with multiple legs but no per-leg price:
        //    Allocate proportional share instead of whole fare to avoid overcompensation for a single delayed leg.
        //    Strategy: equal share unless future enrichment adds distance/time weighting.
        if (!$throughTicket && $legsCount > 1) {
            // Use delayed leg index to pick the affected portion; if missing fallback to first.
            $delayedIdx = null;
            if (isset($disruption['delayed_leg_index'])) {
                $delayedIdx = max(0, (int)$disruption['delayed_leg_index']);
                if ($delayedIdx >= $legsCount) { $delayedIdx = $legsCount - 1; }
            } else {
                $delayedIdx = 0;
            }
            // Equal division (later we can swap in distance/time weighting if legs enriched):
            $share = $ticketTotal / $legsCount;
            return [max(0.0, $share), 'Art.19(3) proportional share (separate contracts; leg ' . ($delayedIdx + 1) . ' of ' . $legsCount . ')'];
        }

        // 5) Single-leg/simple default → whole fare
        return [max(0.0, $ticketTotal), 'Art.19(3) whole fare'];
    }

    /** Identify internal/operator staff strikes from a free-text or enum type token. */
    private function isInternalStrike(string $t): bool
    {
        $s = strtolower(trim($t));
        if ($s === '') { return false; }
    $ok = ['own_staff_strike','staff_strike','internal_strike','personalestrike'];
        if (in_array($s, $ok, true)) { return true; }
        // Loose matching on common words
        return str_contains($s, 'own staff') || str_contains($s, 'staff strike') || str_contains($s, 'internal') || str_contains($s, 'personale');
    }
}
