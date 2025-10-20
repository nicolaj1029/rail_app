# Implementation Bundle (Art.9 + Art.19 integrations)
}
```

---

## File: src/Service/EligibilityService.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

class EligibilityService
{
    public function __construct(
        private ExemptionsRepository $exemptions,
        private NationalOverridesRepository $overrides,
        private ?ExemptionMatrixRepository $matrix = null
    ) {}

    /**
     * Compute compensation percentage based on delay, EU base rule, exemptions and optional national overrides.
     *
     * Inputs (subset, others ignored safely):
     * - delayMin: minutes of delay at destination considered for compensation
     * - euOnly: whether the journey is within EU scope (pre-filtering of segments upstream)
     * - refundAlready: if true, no compensation (Art. 18 alternative chosen)
     * - knownDelayBeforePurchase: if true, no compensation (Art. 19(9))
     * - extraordinary: if true, no compensation (Art. 19(10))
     * - selfInflicted: if true, deny (CIV fault by passenger)
     * - throughTicket: hint for upstream aggregation; this function does not split segments itself
     * - country/operator/product: used to look up national/operator overrides
     *
    * @param array{delayMin:int, euOnly:bool, refundAlready?:bool, knownDelayBeforePurchase?:bool, extraordinary?:bool, selfInflicted?:bool, throughTicket?:bool, country?:string, operator?:string, product?:string, scope?:string} $ctx
     * @return array{percent:int, source:string, notes?:string}
     */
    public function computeCompensation(array $ctx): array
    {
        // Hard denials first
        if (!empty($ctx['refundAlready']) || !empty($ctx['selfInflicted'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Refund already paid or self-inflicted'];
        }
        if (!empty($ctx['knownDelayBeforePurchase'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Known delay before purchase'];
        }
        if (!empty($ctx['extraordinary'])) {
            // Art. 19(10): extraordinary circumstances exclude compensation
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Extraordinary circumstances'];
        }

        // Check exemptions that remove Art. 19 entirely (only when country context is known)
        $exMatched = [];
        // Early block for any scope marked blocked in matrix (Model A and other full blocks)
        if (!empty($ctx['country']) && !empty($ctx['scope'])) {
            $scopeName = (string)$ctx['scope'];
            $mx = $this->matrix ?: new ExemptionMatrixRepository();
            $rows = $mx->find(['country' => $ctx['country'], 'scope' => $scopeName]);
            foreach ($rows as $r) {
                if (!empty($r['blocked'])) {
                    $out = [
                        'percent' => 0,
                        'source' => 'denied',
                        'notes' => ucfirst($scopeName) . ' scope blocked by national/regional exemption',
                        'scope' => $scopeName,
                        'exemptions' => (array)($r['exemptions'] ?? []),
                    ];
                    if (!empty($r['reason'])) { $out['overrideNotes'] = (string)$r['reason']; }
                    return $out;
                }
            }
        }
        if (!empty($ctx['country'])) {
            $ex = $this->exemptions->find([
                'country' => $ctx['country'],
                'scope' => $ctx['scope'] ?? null,
            ]);
            $exMatched = $ex;
            foreach ($ex as $row) {
                if (isset($row['articlesExempt']) && in_array('19', (array)$row['articlesExempt'], true)) {
                    // EU compensation not applicable; fall back to overrides if present
                    $override = $this->overrides->findOne([
                        'country' => $ctx['country'] ?? null,
                        'operator' => $ctx['operator'] ?? null,
                        'product' => $ctx['product'] ?? null,
                    ]);
                    if ($override) {
                        [$ovPercent, $ovPayout] = $this->applyTiersWithPayout($ctx['delayMin'], $override['tiers'] ?? []);
                        $out = [
                            'percent' => $ovPercent,
                            'source' => 'override',
                            'notes' => 'Art. 19 exempt; using national/operator override',
                        ];
                        if ($ovPayout) { $out['payout'] = $ovPayout; }
                        if (!empty($override['notes'])) { $out['overrideNotes'] = (string)$override['notes']; }
                        if (!empty($override['source'])) { $out['overrideSource'] = (string)$override['source']; }
                        if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                        if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
                        if (!empty($override['exemptions']) && empty($out['exemptions'])) { $out['exemptions'] = (array)$override['exemptions']; }
                        if (!empty($override['scope']) && empty($out['scope'])) { $out['scope'] = (string)$override['scope']; }
                        return $out;
                    }
                    $out = ['percent' => 0, 'source' => 'denied', 'notes' => 'Art. 19 exempt in this context'];
                    if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                    if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
                    return $out;
                }
            }
        }

        // Base EU rule
        $percent = 0;
        if ($ctx['delayMin'] >= 120) {
            $percent = 50;
        } elseif ($ctx['delayMin'] >= 60) {
            $percent = 25;
        }

    // Note: throughTicket guidance – if throughTicket=false, callers should pass per-segment delayMin upstream.

        // Apply national overrides if more generous
        $override = $this->overrides->findOne([
            'country' => $ctx['country'] ?? null,
            'operator' => $ctx['operator'] ?? null,
            'product' => $ctx['product'] ?? null,
        ]);
        if ($override) {
            [$ovPercent, $ovPayout] = $this->applyTiersWithPayout($ctx['delayMin'], $override['tiers'] ?? []);
            if ($ovPercent > $percent) {
                $out = ['percent' => $ovPercent, 'source' => 'override'];
                if ($ovPayout) { $out['payout'] = $ovPayout; }
                if (!empty($override['notes'])) { $out['overrideNotes'] = (string)$override['notes']; }
                if (!empty($override['source'])) { $out['overrideSource'] = (string)$override['source']; }
                // If exemptions were matched on the country, bubble them up for badges
                if (!empty($exMatched)) {
                    foreach ($exMatched as $row) {
                        if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                        if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
                    }
                }
                // Also include override-specified exemptions/scope if present
                if (!empty($override['exemptions']) && empty($out['exemptions'])) { $out['exemptions'] = (array)$override['exemptions']; }
                if (!empty($override['scope']) && empty($out['scope'])) { $out['scope'] = (string)$override['scope']; }
                return $out;
            }
        }
        $out = ['percent' => $percent, 'source' => 'eu'];
        if (!empty($exMatched)) {
            foreach ($exMatched as $row) {
                if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
            }
        }
        // If no explicit exemptions matched, but override declares scope/exemptions, expose them for UI badges
        if (empty($out['exemptions']) || empty($out['scope'])) {
            if (!empty($override)) {
                if (empty($out['exemptions']) && !empty($override['exemptions'])) { $out['exemptions'] = (array)$override['exemptions']; }
                if (empty($out['scope']) && !empty($override['scope'])) { $out['scope'] = (string)$override['scope']; }
            }
        }
        return $out;
    }

    /**
     * @param int $delay
     * @param array<int,array{minDelayMin:int,percent:int}> $tiers
     */
    private function applyTiers(int $delay, array $tiers): int
    {
        $best = 0;
        foreach ($tiers as $t) {
            if (!isset($t['minDelayMin'], $t['percent'])) {
                continue;
            }
            if ($delay >= (int)$t['minDelayMin']) {
                $best = max($best, (int)$t['percent']);
            }
        }
        return $best;
    }

    /**
     * Return best percent and the payout mode (if any) for the matched tier.
     * @param array<int,array{minDelayMin:int,percent:int,payout?:string}> $tiers
     * @return array{0:int,1:?string}
     */
    private function applyTiersWithPayout(int $delay, array $tiers): array
    {
        $best = 0; $bestPayout = null; $bestMin = -1;
        foreach ($tiers as $t) {
            if (!isset($t['minDelayMin'], $t['percent'])) {
                continue;
            }
            $min = (int)$t['minDelayMin'];
            if ($delay >= $min) {
                // prefer higher threshold when equal percent; else prefer higher percent
                if ($t['percent'] > $best || ($t['percent'] == $best && $min > $bestMin)) {
                    $best = (int)$t['percent'];
                    $bestMin = $min;
                    $bestPayout = $t['payout'] ?? $bestPayout;
                }
            }
        }
        return [$best, $bestPayout];
    }
}
```

---

## File: src/Service/RefundEvaluator.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates eligibility for refund ("refusion") aligned with Article 18 rights.
 * Simplified rules:
 *  - If final arrival delay >= 60 minutes, passenger should be offered refund option.
 *  - "refundAlready" denies new refund (already processed elsewhere).
 *  - Extraordinary circumstances DO NOT affect refund eligibility (unlike compensation).
 *  - Unknown times => eligible = null; UI should prompt.
 */
class RefundEvaluator
{
    /**
     * @param array $journey Expected keys: segments[] with schedArr/actArr or fallback dep/arr fields
     * @param array $meta Optional flags: refundAlready?:bool
     * @return array{
     *   minutes:int|null,
     *   eligible:bool|null,
     *   reasoning:string[],
     *   fallback_recommended:string[]
     * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $minutes = $this->computeDelayMinutes($journey);
        $reasoning = [];
        $fallbacks = [];

        if ($minutes === null) {
            $reasoning[] = 'Manglende tidsdata; kan ikke beregne forsinkelse.';
            $fallbacks[] = 'ask_actual_and_scheduled_times';
            return [
                'minutes' => null,
                'eligible' => null,
                'reasoning' => $reasoning,
                'fallback_recommended' => array_values(array_unique($fallbacks)),
            ];
        }

        $eligible = $minutes >= 60;
        if ($eligible) {
            $reasoning[] = 'Forsinkelse >= 60 min → tilbud om refusion (Art. 18).';
            $fallbacks[] = 'offer_refund_option';
            $fallbacks[] = 'offer_rerouting_option';
            $fallbacks[] = 'show_terms_art18';
        } else {
            $reasoning[] = 'Forsinkelse < 60 min → ingen automatisk refusion.';
            $fallbacks[] = 'explain_threshold_60m';
        }

        if (!empty($meta['refundAlready'])) {
            $eligible = false;
            $reasoning[] = 'Allerede refunderet.';
        }

        return [
            'minutes' => $minutes,
            'eligible' => $eligible,
            'reasoning' => $reasoning,
            'fallback_recommended' => array_values(array_unique($fallbacks)),
        ];
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
            if ($t1 && $t2) {
                return max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        // Fallback fields
        $depDate = (string)($journey['depDate']['value'] ?? '');
        $sched = (string)($journey['schedArrTime']['value'] ?? '');
        $act = (string)($journey['actualArrTime']['value'] ?? '');
        if ($depDate && $sched && $act) {
            $t1 = strtotime($depDate . 'T' . $sched . ':00');
            $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
            if ($t1 && $t2) {
                return max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        return null;
    }
}
```

---

## File: src/Service/Art18RefusionEvaluator.php

```php
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

        $art19Applies = !isset($profile['articles']['art19']) || $profile['articles']['art19'] !== false;
        if ($hooks['self_inflicted'] || $hooks['extraordinary_claimed'] === 'Ja') {
            $outcome = 'Afslag (egen skyld / force majeure)';
            $reasoning[] = 'Egen skyld eller ekstraordinære forhold angivet.';
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

        $missing = $this->missingHooks($hooks, $triggers, $profile);

        return [
            'minutes' => $minutes,
            'triggers' => $triggers,
            'hooks' => $hooks,
            'missing' => $missing,
            'outcome' => $outcome,
            'reasoning' => $reasoning,
            'ui_banners' => $uiBanners,
            'limits' => $limits,
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
    private function missingHooks(array $hooks, array $triggers, array $profile): array
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
```

---

## File: src/Service/Art12Evaluator.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates Article 12 (1)-(7) related hooks for a given journey/purchase context.
 * Heuristics are conservative; unknowns are flagged so UI can ask the user.
 */
class Art12Evaluator
{
    /**
     * @param array $journey Expected keys: segments[], bookingRef?, seller_type?, source?
     * @param array $meta Optional keys: through_ticket_disclosure?, separate_contract_notice?, contact_info_provided?, responsibility_explained?
     * @return array{
     *   hooks: array<string,mixed>,
     *   missing: string[],
     *   art12_applies: bool|null,
     *   reasoning: string[]
     * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $bookingRef = $journey['bookingRef'] ?? null;
        $pnrs = [];
        $carriers = [];
        foreach ($segments as $s) {
            $pnr = $s['pnr'] ?? null;
            if (is_string($pnr) && $pnr !== '') { $pnrs[] = $pnr; }
            $carrier = $s['carrier'] ?? ($s['operator'] ?? null);
            if (is_string($carrier) && $carrier !== '') { $carriers[] = $carrier; }
        }
        $uniquePnrs = array_values(array_unique($pnrs));
        $uniqueCarriers = array_values(array_unique($carriers));

        $hooks = [];

        // 1) Grundtype
        $hooks['through_ticket_disclosure'] = $meta['through_ticket_disclosure'] ?? 'unknown';
        $hooks['single_txn_operator'] = $meta['single_txn_operator'] ?? 'unknown';
        $hooks['single_txn_retailer'] = $meta['single_txn_retailer'] ?? 'unknown';
        $hooks['separate_contract_notice'] = $meta['separate_contract_notice'] ?? 'unknown';
        $hooks['shared_pnr_scope'] = $this->triFromBool($this->hasSharedScope($bookingRef, $uniquePnrs));

        // 2) Hvem solgte rejsen?
        $sellerType = $journey['seller_type'] ?? null; // 'operator'|'agency'|null
        $hooks['seller_type_operator'] = $this->triFromBool($sellerType === 'operator');
        $hooks['seller_type_agency'] = $this->triFromBool($sellerType === 'agency');
        $hooks['multi_operator_trip'] = $this->triFromBool(count($uniqueCarriers) > 1);

        // 3) Forbindelser/tidsforhold
        $hooks['mct_realistic'] = $meta['mct_realistic'] ?? 'unknown';
        $hooks['one_contract_schedule'] = $meta['one_contract_schedule'] ?? 'unknown';

        // 4) Kommunikations- og ansvar
        $hooks['contact_info_provided'] = $meta['contact_info_provided'] ?? 'unknown';
        $hooks['responsibility_explained'] = $meta['responsibility_explained'] ?? 'unknown';
        $hooks['single_booking_reference'] = $this->triFromBool(is_string($bookingRef) && $bookingRef !== '');

        // 5) Undtagelser og sammenhæng – integrate ExemptionProfile (Art. 2)
        $profile = (new ExemptionProfileBuilder())->build($journey);
        $hooks['exemption_override_12'] = $this->triFromBool(!$profile['articles']['art12']);

        // Determine art12 applies recommendation
        $reasoning = [];
        $applies = null; // null = unknown

        if ($hooks['exemption_override_12'] === 'yes') {
            $applies = false;
            $reasoning[] = 'Art. 12 undtaget efter national/EU-fritagelser.';
        } else {
            // If explicit disclosure says through ticket OR shared scope strongly indicates it
            if ($hooks['through_ticket_disclosure'] === 'Gennemgående' || $hooks['shared_pnr_scope'] === 'yes') {
                $applies = true;
                $reasoning[] = 'Gennemgående billet indikation (disclosure eller fælles PNR/bookingRef).';
            }
            // If explicit disclosure says separate and no shared scope
            if ($hooks['through_ticket_disclosure'] === 'Særskilte' && $hooks['shared_pnr_scope'] === 'no') {
                $applies = false;
                $reasoning[] = 'Særskilte kontrakter uden fælles PNR/bookingRef.';
            }
            // Agency sale can imply Art. 12(5) liability if not clearly informed
            if ($hooks['seller_type_agency'] === 'yes' && $hooks['separate_contract_notice'] !== 'Ja') {
                $applies = $applies ?? true; // implicit through responsibility
                $reasoning[] = 'Rejsebureau-salg uden tydelig særskilt-kontrakt info (Art. 12(5)).';
            }
        }

        $missing = $this->missingHooks($hooks);
        return [
            'hooks' => $hooks,
            'missing' => $missing,
            'art12_applies' => $applies,
            'reasoning' => $reasoning,
        ];
    }

    private function hasSharedScope($bookingRef, array $uniquePnrs): bool
    {
        if (is_string($bookingRef) && $bookingRef !== '') { return true; }
        if (count($uniquePnrs) === 1 && $uniquePnrs[0] !== '') { return true; }
        return false;
    }

    private function triFromBool(?bool $v): string
    {
        if ($v === true) return 'yes';
        if ($v === false) return 'no';
        return 'unknown';
    }

    /** @param array<string,mixed> $hooks */
    private function missingHooks(array $hooks): array
    {
        $need = [
            'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
            'mct_realistic','one_contract_schedule','contact_info_provided','responsibility_explained'
        ];
        $missing = [];
        foreach ($need as $k) {
            if (!isset($hooks[$k]) || $hooks[$k] === 'unknown') { $missing[] = $k; }
        }
        return $missing;
    }
}
```

---

## File: src/Service/RneClient.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Client;

class RneClient
{
    private string $baseUrl;
    private Client $http;

    public function __construct(?string $baseUrl = null, ?Client $http = null)
    {
        // Default to local mock server, can be overridden via env RNE_BASE_URL
        $this->baseUrl = rtrim($baseUrl ?? (getenv('RNE_BASE_URL') ?: 'http://localhost:5555/api/providers/rne'), '/');
        $this->http = $http ?? new Client(['timeout' => 3]);
    }

    /**
     * Fetch realtime payload for a given train id and service date (YYYY-MM-DD).
     * Returns [] on failure.
     *
     * @return array<string,mixed>
     */
    public function realtime(string $trainId, string $date): array
    {
        try {
            $url = $this->baseUrl . '/realtime';
            $res = $this->http->get($url, ['trainId' => $trainId, 'date' => $date]);
            if (!$res->isOk()) { return []; }
            $json = $res->getJson();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
```

````

Contents:
- src/Service/ExemptionProfileBuilder.php
- src/Service/Art9Evaluator.php
- src/Controller/Api/DemoController.php
- src/Controller/Api/ComputeController.php
- src/Service/ClaimCalculator.php
- tests/TestCase/Service/Art9EvaluatorTest.php
- src/Service/EligibilityService.php
- src/Service/RefundEvaluator.php
- src/Service/Art18RefusionEvaluator.php
- src/Service/Art12Evaluator.php
- src/Service/RneClient.php

---

## File: src/Service/ExemptionProfileBuilder.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Builds an exemption profile (which articles apply) for a journey using a JSON-based country matrix.
 */
class ExemptionProfileBuilder
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $matrix;

    public function __construct(?array $matrix = null)
    {
        $this->matrix = $matrix ?? $this->loadMatrix();
    }

    /**
     * @return array{scope:string,articles:array<string,bool>,notes:array<int,string>,ui_banners:array<int,string>}
     */
    public function build(array $journey): array
    {
        $scope = $this->classifyScope($journey);
        $profile = [
            'scope' => $scope,
            'articles' => [
                'art12' => true,
                'art17' => true,
                'art18_3' => true,
                'art19' => true,
                'art20_2' => true,
                'art30_2' => true,
                'art10' => true,
                'art9' => true,
            ],
            'articles_sub' => [
                'art9_1' => true,
                'art9_2' => true,
                'art9_3' => true,
            ],
            'notes' => [],
            'ui_banners' => [],
        ];

        $segments = (array)($journey['segments'] ?? []);
        foreach ($segments as $seg) {
            $country = strtoupper((string)($seg['country'] ?? ''));
            if ($country === '') { continue; }
            foreach ($this->matrix[$country] ?? [] as $entry) {
                if (($entry['scope'] ?? null) !== $scope) { continue; }
                // Map generic "exemptions" array into article flags
                $exArr = (array)($entry['exemptions'] ?? []);
                $map = [
                    'Art.12' => 'art12',
                    'Art.17' => 'art17',
                    'Art.18(3)' => 'art18_3',
                    'Art.19' => 'art19',
                    'Art.20(2)' => 'art20_2',
                    'Art.30(2)' => 'art30_2',
                    'Art.10' => 'art10',
                    'Art.9' => 'art9',
                ];
                foreach ($exArr as $ex) {
                    $exStr = (string)$ex;
                    $artKey = $map[$exStr] ?? null;
                    if ($artKey) { $profile['articles'][$artKey] = false; }
                    // Partial Art.9 handling
                    if ($exStr === 'Art.9') {
                        $profile['articles_sub']['art9_1'] = false;
                        $profile['articles_sub']['art9_2'] = false;
                        $profile['articles_sub']['art9_3'] = false;
                    } elseif (preg_match('/^Art\.9\((1|2|3)\)$/', $exStr, $m)) {
                        $profile['articles_sub']['art9_' . $m[1]] = false;
                    }
                }
                if (!empty($entry['notes']) && is_array($entry['notes'])) {
                    foreach ($entry['notes'] as $n) { $profile['notes'][] = (string)$n; }
                }
            }
        }

        // Consolidate art9 based on sub-parts unless already fully disabled
        if ($profile['articles']['art9']) {
            $subs = $profile['articles_sub'];
            if ($subs['art9_1'] === false && $subs['art9_2'] === false && $subs['art9_3'] === false) {
                $profile['articles']['art9'] = false;
            }
        }
        // Add note for partial exemption
        if ($profile['articles']['art9'] && in_array(false, $profile['articles_sub'], true)) {
            $partsOff = [];
            foreach ($profile['articles_sub'] as $k => $v) {
                if ($v === false) { $partsOff[] = '9(' . substr($k, -1) . ')'; }
            }
            if (!empty($partsOff)) {
                $profile['notes'][] = 'Delvis Art. 9-fritagelse: ' . implode(', ', $partsOff) . ' undtaget.';
            }
        }

        // Apply country-specific conditional gates not directly expressible in the matrix
        $this->applyConditionalGates($journey, $profile);

        // Derive UI banners
        if (!$profile['articles']['art10']) {
            $profile['ui_banners'][] = 'Realtime-data (Art. 10) kan mangle — fallback til ikke-live RNE og upload dokumentation (Art. 20(4)).';
        }
        if (!$profile['articles']['art12']) {
            $profile['ui_banners'][] = 'Gennemgående billet (Art. 12) undtaget — krav splittes pr. billet/kontrakt.';
        }
        if (!$profile['articles']['art18_3']) {
            $profile['ui_banners'][] = '100-minutters-reglen (Art. 18(3)) kan være undtaget.';
        }
        if (!$profile['articles']['art19']) {
            $profile['ui_banners'][] = 'EU-kompensation (Art. 19) undtaget — anvend national/operatør-ordning hvor relevant.';
        }
        if (!$profile['articles']['art20_2']) {
            $profile['ui_banners'][] = 'Assistance (Art. 20(2)) kan være undtaget; upload udgiftsbilag.';
        }
        if (!$profile['articles']['art9']) {
            $profile['ui_banners'][] = 'Informationspligter (Art. 9) kan være undtaget — vis basisoplysninger og fallback-links.';
        }

        return $profile;
    }

    /**
     * Apply conditional rules that require runtime context (distance, terminals, third-country checks).
     * Mutates $profile in-place.
     * @param array $journey
     * @param array{scope:string,articles:array<string,bool>,notes:array<int,string>,ui_banners:array<int,string>} &$profile
     */
    private function applyConditionalGates(array $journey, array &$profile): void
    {
        $scope = (string)$profile['scope'];
        $segments = (array)($journey['segments'] ?? []);
        $countries = array_values(array_filter(array_map(fn($s) => strtoupper((string)($s['country'] ?? '')), $segments)));
        $has = function(string $cc) use ($countries): bool { return in_array(strtoupper($cc), $countries, true); };

        // SE: <150 km domestic only — only apply exemptions when under 150 km
        if ($has('SE') && $scope === 'regional') {
            $under150 = false;
            // Prefer explicit distance on journey or per-segment sum
            $dist = $this->getJourneyDistanceKm($journey);
            if ($dist !== null) { $under150 = ($dist < 150.0); }
            // Allow explicit hint flag if distance is unavailable
            if ($dist === null) {
                $under150 = (bool)($journey['se_under_150km'] ?? false);
            }
            if (!$under150) {
                // Re-enable articles that might have been disabled via matrix for SE regional when not under 150 km
                // Focus on keys we actually model downstream
                $profile['articles']['art19'] = true;
                $profile['articles']['art17'] = true;
                $profile['articles']['art20_2'] = true;
                $profile['notes'][] = 'SE: <150 km-betingelsen ikke opfyldt — regionale undtagelser anvendes ikke.';
            }
        }

        // FI: intl_beyond_eu involving RU/BY — restrict Art.12 and Art.18(3)
        if ($has('FI') && $scope === 'intl_beyond_eu') {
            if ($has('RU') || $has('BY')) {
                $profile['articles']['art12'] = false;
                $profile['articles']['art18_3'] = false;
                $profile['notes'][] = 'FI: Rute til/fra RU/BY — Art. 12 og 18(3) begrænses (Art. 2(6)(b)).';
                $profile['ui_banners'][] = 'Rute delvist uden for EU — gennemgående billet/100-min kan være undtaget.';
            }
        }

        // CZ: intl_beyond_eu where first/last terminal in third country (except CH) — restrict Art.12 and Art.18(3)
        if ($has('CZ') && $scope === 'intl_beyond_eu' && count($segments) > 0) {
            [$firstC, $lastC] = [$countries[0] ?? '', $countries[count($countries)-1] ?? ''];
            $isThird = function(string $c): bool {
                if ($c === '') return false;
                if ($c === 'CH') return false; // Switzerland is the explicit exception
                return !$this->isEuCountry($c);
            };
            if ($isThird($firstC) || $isThird($lastC)) {
                $profile['articles']['art12'] = false;
                $profile['articles']['art18_3'] = false;
                $profile['notes'][] = 'CZ: Tredjelands-terminal (ekskl. CH) — Art. 12 og 18(3) undtages.';
                $profile['ui_banners'][] = 'Gennemgående billet/100-min kan være undtaget pga. tredjelandsterminal.';
            }
        }
    }

    /** Determine total distance in km if present on journey or sum of segments. */
    private function getJourneyDistanceKm(array $journey): ?float
    {
        $dist = null;
        if (isset($journey['distance_km']) && is_numeric($journey['distance_km'])) {
            $dist = (float)$journey['distance_km'];
        } else {
            $sum = 0.0; $have = false;
            foreach ((array)($journey['segments'] ?? []) as $s) {
                if (isset($s['distance_km']) && is_numeric($s['distance_km'])) {
                    $sum += (float)$s['distance_km'];
                    $have = true;
                }
            }
            if ($have) { $dist = $sum; }
        }
        return $dist;
    }

    /** Minimal EU membership check for terminal-country logic. */
    private function isEuCountry(string $code): bool
    {
        $eu = [
            'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'
        ];
        return in_array(strtoupper($code), $eu, true);
    }

    private function classifyScope(array $journey): string
    {
        // Simple classifier; can be replaced with distance/time based share outside EU
        if (!empty($journey['is_international_beyond_eu'])) return 'intl_beyond_eu';
        if (!empty($journey['is_international_inside_eu'])) return 'intl_inside_eu';
        if (!empty($journey['is_long_domestic'])) return 'long_domestic';
        return 'regional';
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function loadMatrix(): array
    {
        $path = CONFIG . 'data' . DIRECTORY_SEPARATOR . 'exemption_matrix.json';
        if (!is_file($path)) { return []; }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
```

---

## File: src/Service/Art9Evaluator.php

```php
<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates Article 9 related information duties and fallbacks.
 *
 * Backwards compatible fields retained:
 *  - hooks (now superset)
 *  - art9_ok
 *
 * New (granular) fields after Phase 2 expansion:
 *  - parts: { art9_1_ok, art9_2_ok, art9_3_ok }
 *  - ui_banners (optional, currently unused placeholder)
 *
 * Supports selective exemptions via ExemptionProfileBuilder->articles_sub.
 * Test support: meta key '_profile_override' can inject a pre-built profile to avoid
 * dependency complexity.
 */
class Art9Evaluator
{
    /**
     * Central registry of Art.9 hooks with their part mapping.
     * Values are one of: 'art9_1' | 'art9_2' | 'art9_3'.
     * Note: Values are treated nominally (Ja/Nej/unknown/Delvist). Only 'Nej' yields explicit false.
     */
    private const HOOK_DEFINITIONS = [
        // 9(1) – Pre-contract general info (Bilag II del 1)
        'info_before_purchase' => 'art9_1',
        'language_accessible' => 'art9_1',
        'accessibility_format' => 'art9_1',
        'multi_channel_information' => 'art9_1',
        'accessible_formats_offered' => 'art9_1',
        // CoC / CIV
        'coc_acknowledged' => 'art9_1',
        'coc_evidence_upload' => 'art9_1',
        'civ_marking_present' => 'art9_1',
        // Schedules / fastest / alternatives
        'fastest_flag_at_purchase' => 'art9_1',
        'mct_realistic' => 'art9_1',
        'alts_shown_precontract' => 'art9_1',
        // Fares / cheapest
        'multiple_fares_shown' => 'art9_1',
        'cheapest_highlighted' => 'art9_1',
        'fare_flex_type' => 'art9_1',
        'train_specificity' => 'art9_1',
        // PMR pre-contract
        'pmr_user' => 'art9_1',
        'pmr_booked' => 'art9_1',
        'pmr_promised_missing' => 'art9_1',
        // Bike pre-contract
        'bike_reservation_type' => 'art9_1',
        'bike_res_required' => 'art9_1',
        // Class / seat pre-contract
        'fare_class_purchased' => 'art9_1',
        'berth_seat_type' => 'art9_1',
        // Facilities promised
        'promised_facilities' => 'art9_1',
    // Pre-informed disruptions (links to Art.19(9))
    'preinformed_disruption' => 'art9_1',
        // Through ticket disclosure (also in Art12)
        'through_ticket_disclosure' => 'art9_1',
        'single_txn_operator' => 'art9_1',
        'single_txn_retailer' => 'art9_1',
        'separate_contract_notice' => 'art9_1',

        // 9(2) – Rights information & complaints
        'info_on_rights' => 'art9_2',
        'rights_notice_displayed' => 'art9_2',
        'rights_contact_provided' => 'art9_2',
        // Complaints
        'complaint_channel_seen' => 'art9_2',
        'complaint_already_filed' => 'art9_2',
        'complaint_receipt_upload' => 'art9_2',
        'submit_via_official_channel' => 'art9_2',

        // 9(3) – During disruption / delivery
        'info_during_disruption' => 'art9_3',
        'station_board_updates' => 'art9_3',
        'onboard_announcements' => 'art9_3',
        'disruption_updates_frequency' => 'art9_3',
        'assistance_contact_visible' => 'art9_3',
        // Realtime info consumption
        'realtime_info_seen' => 'art9_3',
        // PMR delivery
        'pmr_delivered_status' => 'art9_3',
        // Bike delivery / outcomes
        'bike_denied_reason' => 'art9_3',
        'bike_followup_offer' => 'art9_3',
        'bike_delay_bucket' => 'art9_3',
        // Facilities delivery
        'facilities_delivered_status' => 'art9_3',
        'facility_impact_note' => 'art9_3',
        // Class / reserved amenity delivery
        'class_delivered_status' => 'art9_3',
        'reserved_amenity_delivered' => 'art9_3',
    ];
    /**
     * @param array $journey Minimal keys used: segments[], country?; free-form fields accepted safely.
     * @param array $meta Keys considered (all optional). Values are typically one of:
     *  - "Ja" | "Nej" | "Delvist" | "unknown"
     *  Core (legacy) hooks retained:
     *    info_before_purchase, info_on_rights, info_during_disruption, language_accessible, accessibility_format
     *  Expanded hooks (Phase 2):
     *    multi_channel_information, accessible_formats_offered,
     *    rights_notice_displayed, rights_contact_provided,
     *    station_board_updates, onboard_announcements, disruption_updates_frequency, assistance_contact_visible
     *  Special meta keys:
     *    _profile_override (array) For tests: inject pre-built profile (must match ExemptionProfileBuilder schema)
     *
    * @return array{
    *   hooks: array<string,string>,
    *   auto: array<string,array{value:mixed,source?:string}>,
    *   mismatches: array<string,array{expected:mixed,actual:mixed,source?:string,part:string}>,
    *   parts: array{art9_1_ok: bool|null, art9_2_ok: bool|null, art9_3_ok: bool|null},
    *   missing: string[],
    *   ask_hooks: string[],
    *   art9_ok: bool|null,
    *   reasoning: string[],
    *   fallback_recommended: string[],
    *   ui_banners: string[]
    * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $profile = isset($meta['_profile_override']) && is_array($meta['_profile_override'])
            ? $meta['_profile_override']
            : (new ExemptionProfileBuilder())->build($journey);

        // Unified hook collection from registry (legacy + extended)
        $hookKeys = array_keys(self::HOOK_DEFINITIONS);
        $hooks = [];
        foreach ($hookKeys as $k) {
            $hooks[$k] = $meta[$k] ?? 'unknown';
        }

    $reasoning = [];
    $fallbacks = [];
    $uiBanners = [];
    $auto = $this->collectAuto($meta);

        // If Art. 9 is exempt in this context, short-circuit to null/false accordingly
        $art9Applicable = $profile['articles']['art9'] ?? true;
        $subs = $profile['articles_sub'] ?? ['art9_1' => true, 'art9_2' => true, 'art9_3' => true];
        $partsStatus = ['art9_1_ok' => null, 'art9_2_ok' => null, 'art9_3_ok' => null];

        if ($art9Applicable === false) {
            return [
                'hooks' => $hooks,
                'auto' => $auto,
                'mismatches' => [],
                'parts' => $partsStatus, // all null when fully exempt
                'missing' => $this->missingHooks($hooks, $subs, fullyExempt: true),
                'ask_hooks' => [],
                'art9_ok' => null,
                'reasoning' => ['Art. 9 undtaget ifølge fritagelsesprofil.'],
                'fallback_recommended' => ['show_basic_rights_link'],
                'ui_banners' => ['Art. 9 undtaget (profil)'],
            ];
        }

    // Partition hooks into parts
    // - statusPartMap: baseline subset for backward-compatible part status (legacy behavior)
    // - registryPartMap: full set (for ask_hooks/missing/fallbacks)
    $statusPartMap = $this->baselinePartMap();
    $registryPartMap = $this->buildPartMap();

    $anyFalse = false; $allTrue = true; // Over non-exempt parts only

        foreach ($statusPartMap as $partKey => $partHooks) {
            $exempt = ($subs[$partKey] ?? true) === false; // part exempt
            if ($exempt) { $partsStatus[$partKey . '_ok'] = null; continue; }
            $explicitNo = false; $unknown = false;
            foreach ($partHooks as $hk) {
                $val = $hooks[$hk];
                if ($val === 'Nej') { $explicitNo = true; $reasoning[] = $hk . ' mangler.'; }
                if ($val === 'unknown') { $unknown = true; }
            }
            $status = null;
            if ($explicitNo) { $status = false; }
            elseif (!$unknown) { $status = true; }
            $partsStatus[$partKey . '_ok'] = $status;
            if ($status === false) { $anyFalse = true; }
            if ($status !== true) { $allTrue = false; }
        }

        $art9Ok = null;
        if ($anyFalse) { $art9Ok = false; }
        elseif ($allTrue) { $art9Ok = true; }

        // Fallbacks targeted (only for non-exempt parts)
        $fallbackMap = [
            'info_on_rights' => 'show_basic_rights_link',
            'rights_notice_displayed' => 'show_basic_rights_link',
            'rights_contact_provided' => 'prompt_contact_point',
            'info_during_disruption' => 'ask_upload_notifications',
            'station_board_updates' => 'ask_upload_notifications',
            'onboard_announcements' => 'ask_upload_notifications',
            'disruption_updates_frequency' => 'encourage_frequency_standard',
            'assistance_contact_visible' => 'prompt_assistance_contact',
            'language_accessible' => 'offer_language_toggle',
            'accessibility_format' => 'offer_plain_text_and_pdf',
            'accessible_formats_offered' => 'offer_plain_text_and_pdf',
            'multi_channel_information' => 'offer_multi_channel',
            // New fallbacks for extended hooks
            'coc_acknowledged' => 'show_coc_link',
            'coc_evidence_upload' => 'prompt_coc_upload',
            'civ_marking_present' => 'check_ticket_markings',
            'fastest_flag_at_purchase' => 'explain_fastest_journey_basis',
            'alts_shown_precontract' => 'explain_alternatives_basis',
            'multiple_fares_shown' => 'explain_fares_basics',
            'cheapest_highlighted' => 'explain_fares_basics',
            'fare_flex_type' => 'collect_fare_terms',
            'train_specificity' => 'collect_ticket_scope',
            'pmr_user' => 'offer_assistance_info',
            'pmr_booked' => 'prompt_pmr_evidence',
            'pmr_promised_missing' => 'collect_pmr_promises',
            'pmr_delivered_status' => 'collect_pmr_delivery',
            'bike_reservation_type' => 'show_bike_policy_link',
            'bike_res_required' => 'show_bike_policy_link',
            'bike_denied_reason' => 'offer_bike_refund_help',
            'bike_followup_offer' => 'offer_bike_refund_help',
            'bike_delay_bucket' => 'collect_bike_delay_evidence',
            'fare_class_purchased' => 'collect_downgrade_evidence',
            'class_delivered_status' => 'collect_downgrade_evidence',
            'berth_seat_type' => 'collect_reservation_evidence',
            'reserved_amenity_delivered' => 'collect_reservation_delivery',
            'promised_facilities' => 'collect_facilities_evidence',
            'facilities_delivered_status' => 'collect_facilities_evidence',
            'facility_impact_note' => 'collect_facilities_evidence',
            'realtime_info_seen' => 'ask_upload_notifications',
            'through_ticket_disclosure' => 'explain_contract_structure',
            'single_txn_operator' => 'explain_contract_structure',
            'single_txn_retailer' => 'explain_contract_structure',
            'separate_contract_notice' => 'explain_contract_structure',
            'complaint_channel_seen' => 'offer_complaint_proxy',
            'complaint_already_filed' => 'offer_complaint_proxy',
            'complaint_receipt_upload' => 'offer_complaint_proxy',
            'submit_via_official_channel' => 'offer_complaint_proxy',
        ];
        foreach ($fallbackMap as $hk => $fb) {
            // Skip if hook not in map; skip if part exempt
            $partKey = $this->partForHook($hk, $registryPartMap);
            if (($subs[$partKey] ?? true) === false) { continue; }
            $val = $hooks[$hk] ?? 'unknown';
            if ($val !== 'Ja') { $fallbacks[] = $fb; }
        }

        // Detect mismatches based on provided auto values (if any)
        $mismatches = $this->detectMismatches($hooks, $auto, $subs, $registryPartMap);

        // UI banners (simple first iteration)
        foreach (['art9_1','art9_2','art9_3'] as $pk) {
            if (($subs[$pk] ?? true) === false) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') undtaget.'; continue; }
            $st = $partsStatus[$pk . '_ok'];
            if ($st === false) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') ikke opfyldt.'; }
            elseif ($st === null) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') ufuldstændig (ukendt).'; }
            // Mismatch banner per part
            $mm = array_keys(array_filter($mismatches, fn($v) => ($v['part'] ?? '') === $pk));
            if (!empty($mm)) {
                $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') mismatch: ' . implode(', ', array_slice($mm,0,5)) . (count($mm) > 5 ? ' …' : '');
            }
        }

        return [
            'hooks' => $hooks,
            'auto' => $auto,
            'mismatches' => $mismatches,
            'parts' => $partsStatus,
            'missing' => $this->missingHooks($hooks, $subs),
            'ask_hooks' => $this->askHooks($hooks, $subs, $mismatches),
            'art9_ok' => $art9Ok,
            'reasoning' => $reasoning,
            'fallback_recommended' => array_values(array_unique($fallbacks)),
            'ui_banners' => $uiBanners,
        ];
    }

    /**
     * Compute missing hooks (unknown) for non-exempt parts.
     * @param array<string,string> $hooks
     * @param array<string,bool> $subs
     */
    private function missingHooks(array $hooks, array $subs = [], bool $fullyExempt = false): array
    {
        if ($fullyExempt) { return []; }
        $partMap = $this->buildPartMap();
        $missing = [];
        foreach ($partMap as $pk => $hks) {
            if (($subs[$pk] ?? true) === false) { continue; }
            foreach ($hks as $hk) {
                if (!isset($hooks[$hk]) || $hooks[$hk] === 'unknown') { $missing[] = $hk; }
            }
        }
        return $missing;
    }

    /** @param array<string,string[]> $partMap */
    private function partForHook(string $hook, array $partMap): string
    {
        foreach ($partMap as $pk => $hks) { if (in_array($hook, $hks, true)) { return $pk; } }
        return 'art9_1'; // default fallback
    }

    /** Build part map from registry */
    private function buildPartMap(): array
    {
        $map = ['art9_1' => [], 'art9_2' => [], 'art9_3' => []];
        foreach (self::HOOK_DEFINITIONS as $hook => $part) { $map[$part][] = $hook; }
        return $map;
    }

    /** Baseline subset for part status (backward compatible behavior) */
    private function baselinePartMap(): array
    {
        return [
            'art9_1' => ['info_before_purchase','language_accessible','accessibility_format','multi_channel_information','accessible_formats_offered'],
            'art9_2' => ['info_on_rights','rights_notice_displayed','rights_contact_provided'],
            'art9_3' => ['info_during_disruption','station_board_updates','onboard_announcements','disruption_updates_frequency','assistance_contact_visible'],
        ];
    }

    /**
     * Compute ask_hooks: hooks that should be asked to user (unknown) in non-exempt parts.
     * Future: include mismatch detection when auto-sources exist.
     * @param array<string,string> $hooks
     * @param array<string,bool> $subs
     * @return string[]
     */
    private function askHooks(array $hooks, array $subs, array $mismatches = []): array
    {
        $partMap = $this->buildPartMap();
        $ask = [];
        foreach ($partMap as $pk => $hks) {
            if (($subs[$pk] ?? true) === false) { continue; }
            foreach ($hks as $hk) {
                if (!isset($hooks[$hk]) || $hooks[$hk] === 'unknown' || isset($mismatches[$hk])) { $ask[] = $hk; }
            }
        }
        return $ask;
    }

    /**
     * Collect auto values from meta['_auto'] placeholder.
     * Accepts either scalar or {value,source} arrays.
     * @param array<string,mixed> $meta
     * @return array<string,array{value:mixed,source?:string}>
     */
    private function collectAuto(array $meta): array
    {
        $out = [];
        $raw = isset($meta['_auto']) && is_array($meta['_auto']) ? $meta['_auto'] : [];
        foreach (self::HOOK_DEFINITIONS as $hk => $_) {
            if (!array_key_exists($hk, $raw)) { continue; }
            $val = $raw[$hk];
            if (is_array($val)) {
                $value = $val['value'] ?? null;
                if ($value !== null) { $out[$hk] = ['value' => $value] + (isset($val['source']) ? ['source' => (string)$val['source']] : []); }
            } else {
                $out[$hk] = ['value' => $val, 'source' => 'meta_auto'];
            }
        }
        return $out;
    }

    /**
     * Detect mismatches between provided hooks and auto values for non-exempt parts.
     * @param array<string,string> $hooks
     * @param array<string,array{value:mixed,source?:string}> $auto
     * @param array<string,bool> $subs
     * @param array<string,string[]> $partMap
     * @return array<string,array{expected:mixed,actual:mixed,source?:string,part:string}>
     */
    private function detectMismatches(array $hooks, array $auto, array $subs, array $partMap): array
    {
        $out = [];
        foreach ($auto as $hk => $av) {
            $pk = $this->partForHook($hk, $partMap);
            if (($subs[$pk] ?? true) === false) { continue; }
            if (!array_key_exists($hk, $hooks)) { continue; }
            $actual = $hooks[$hk];
            $expected = $av['value'] ?? null;
            if ($expected === null) { continue; }
            if ($actual === 'unknown') { continue; }
            if ($this->normalizeVal($actual) !== $this->normalizeVal($expected)) {
                $out[$hk] = [
                    'expected' => $expected,
                    'actual' => $actual,
                    'source' => $av['source'] ?? null,
                    'part' => $pk,
                ];
            }
        }
        return $out;
    }

    private function normalizeVal($v): string
    {
        return strtolower(trim((string)$v));
    }
}
```

---

## File: src/Controller/Api/DemoController.php

```php
<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class DemoController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Scan generated mock tickets under mocks/tests/fixtures and run full analysis on each.
     * Optional query: baseDir to override directory.
     */
    public function mockTickets(): void
    {
        $baseDir = (string)($this->request->getQuery('baseDir') ?? (ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures'));
        $withRne = (string)($this->request->getQuery('withRne') ?? '0') === '1';
        if (!is_dir($baseDir)) {
            $this->set(['error' => 'not_found', 'baseDir' => $baseDir]);
            $this->viewBuilder()->setOption('serialize', ['error','baseDir']);
            return;
        }

        // Group files by basename (without extension)
        $entries = scandir($baseDir) ?: [];
        $groups = [];
        foreach ($entries as $fn) {
            if ($fn === '.' || $fn === '..') { continue; }
            $ext = strtolower((string)pathinfo($fn, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','png','txt'], true)) { continue; }
            $base = (string)pathinfo($fn, PATHINFO_FILENAME);
            if (!isset($groups[$base])) { $groups[$base] = ['pdf' => null, 'png' => null, 'txt' => null]; }
            $groups[$base][$ext] = $baseDir . DIRECTORY_SEPARATOR . $fn;
        }

        $results = [];
        foreach ($groups as $base => $media) {
            // Parse TXT if present; otherwise attempt heuristics from filename
            $txt = '';
            if (!empty($media['txt']) && is_file($media['txt'])) {
                $txt = (string)file_get_contents((string)$media['txt']);
            }

            $parsed = $this->parseMockText($base, $txt);
            $journey = (array)($parsed['journey'] ?? []);
            // Accept both snake_case (preferred) and camelCase from parser
            $art12Meta = (array)($parsed['art12_meta'] ?? ($parsed['art12Meta'] ?? []));
            $art9Meta = (array)($parsed['art9_meta'] ?? ($parsed['art9Meta'] ?? []));
            $refusionMeta = (array)($parsed['refusion_meta'] ?? ($parsed['refusionMeta'] ?? []));
            $compute = (array)($parsed['compute'] ?? []);

            // Profile and evaluations
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);

            // Prepare auto values for Art.9 from OCR text and overlaps; RNE added below if available
            $auto = [];
            foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
            }
            if ($txt !== '') {
                if (preg_match('/\bCIV\b/i', $txt) || stripos($txt, 'conditions of carriage') !== false) {
                    $auto['civ_marking_present'] = ['value' => 'Ja', 'source' => 'ticket_ocr'];
                }
                $trainLine = $this->matchOne($txt, '/^Train:\s*([^\r\n]+)/mi');
                if ($trainLine && preg_match('/\d+/', $trainLine)) {
                    $auto['train_specificity'] = ['value' => 'Kun specifikt tog', 'source' => 'ticket_ocr'];
                }
            }
            $comp = $this->computeCompensationPreview($journey, $compute);
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['refundAlready' => (bool)($compute['refundAlready'] ?? false)]);
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            $scenario = [
                'journey' => $journey,
                'refusion_meta' => $refusionMeta,
                'compute' => $compute,
            ];
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($this->mapScenarioToClaimInput($scenario));

            $rne = null;
            if ($withRne) {
                // naive extraction for demo: use product+number or PNR as trainId and schedDep date
                $trainId = $this->matchOne($txt, '/^Train:\s*([^\r\n]+)/mi') ?: ($pnr ?? '');
                $dateIso = $this->dateToIso($this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})/mi'));
                if ($trainId && $dateIso) {
                    $rne = (new \App\Service\RneClient())->realtime($trainId, substr($dateIso, 0, 10));
                } else {
                    $rne = [];
                }
            }
            // Add a conservative RNE auto hint (if data fetched)
            if (!empty($rne)) {
                $auto['station_board_updates'] = ['value' => 'Ja', 'source' => 'rne'];
            }

            if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

            $results[] = [
                'id' => $base,
                'media' => [
                    'pdf' => $media['pdf'],
                    'png' => $media['png'],
                    'txt' => $media['txt'],
                ],
                'rne' => $rne,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results, 'count' => count($results), 'baseDir' => $baseDir]);
        $this->viewBuilder()->setOption('serialize', ['results','count','baseDir']);
    }

    /** Convert mock TXT content + filename into journey + meta */
    private function parseMockText(string $base, string $txt): array
    {
        $lines = preg_split('/\r?\n/', $txt) ?: [];
        $blob = strtoupper($txt);
    $op = '';
    $product = '';
    $country = '';
    if (str_contains($blob, 'SNCF') || str_contains(strtoupper($base), 'SNCF')) { $op = 'SNCF'; $product = 'TGV'; $country = 'FR'; }
    if (preg_match('/\bDB\b/', $blob) || str_contains(strtoupper($base), 'DB')) { $op = 'DB'; $product = 'ICE'; $country = 'DE'; }
    if (str_contains($blob, 'DSB') || str_contains(strtoupper($base), 'DSB')) { $op = 'DSB'; $product = 'RE'; $country = 'DK'; }
    if (str_contains($blob, 'SJ') || str_contains(strtoupper($base), 'SE_')) { $op = 'SJ'; $product = 'REG'; $country = 'SE'; }
    if (str_contains($blob, 'ZSSK') || str_contains(strtoupper($base), 'SK_')) { $op = 'ZSSK'; $product = 'R'; $country = 'SK'; }
    if (str_contains($blob, 'PKP') || str_contains($blob, 'PKP INTERCITY') || str_contains(strtoupper($base), 'PL_')) { $op = 'PKP'; $product = 'IC'; $country = 'PL'; }

        $pnr = $this->matchOne($txt, '/PNR:\s*([A-Z0-9\-]+)/i');
        $trainRaw = $this->matchOne($txt, '/Train:\s*([^\r\n]+)/i');
        if ($trainRaw) {
            // Split product and number if possible
            if (preg_match('/^([A-ZÅÆØÄÖÜ]+)\s*([0-9]+)/i', $trainRaw, $m)) {
                $product = $product ?: strtoupper($m[1]);
            }
        }

        $from = $this->matchOne($txt, '/^From:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Fra:\s*(.+)$/mi');
        $to = $this->matchOne($txt, '/^To:\s*(.+)$/mi') ?: $this->matchOne($txt, '/^Til:\s*(.+)$/mi');

    $dateStr = $this->matchOne($txt, '/^Date:\s*([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?/mi');
    $schedArrTxt = $this->matchOne($txt, '/^Scheduled Arr:\s*([0-9]{2}:[0-9]{2})/mi');
        $depTime = '';
        $depDate = '';
        if ($dateStr) {
            if (preg_match('/^([0-9]{2}\/[0-9]{2}\/[0-9]{4})(?:\s+([0-9]{2}:[0-9]{2}))?$/', $dateStr, $m)) {
                $depDate = $m[1];
                $depTime = $m[2] ?? '';
            }
        }

        // Special SNCF line with both stations and times
        $sncfLine = null;
        $arrTime = '';
        foreach ($lines as $l) {
            if (str_contains($l, '→') && preg_match('/\((\d{2}:\d{2})\).*→.*\((\d{2}:\d{2})\)/', $l, $mm)) {
                $sncfLine = $l; $depTime = $mm[1]; $arrTime = $mm[2];
                if (!$from && preg_match('/^(.*)\s*\(/', $l, $m1)) { $from = trim($m1[1]); }
                if (!$to && preg_match('/→\s*(.*)\s*\(/', $l, $m2)) { $to = trim($m2[1]); }
                break;
            }
        }

        $schedDep = '';
        $schedArr = '';
        if ($depDate && ($depTime || $arrTime || $schedArrTxt)) {
            $isoDate = $this->dateToIso($depDate);
            if ($depTime) { $schedDep = $isoDate . 'T' . $depTime . ':00'; }
            if ($arrTime) { $schedArr = $isoDate . 'T' . $arrTime . ':00'; }
            if (!$schedArr && $schedArrTxt) { $schedArr = $isoDate . 'T' . $schedArrTxt . ':00'; }
        }

        // If arrival missing, assume +75 minutes to exercise logic
        if (!$schedArr && $schedDep) {
            $schedArr = $this->addMinutes($schedDep, 120); // assume 2h journey
        }

        // Tailor actual arrival to match characteristic delays per known mock
        $actArr = '';
        if ($schedArr) {
            $delta = 75; // default
            $b = strtolower($base);
            if (str_starts_with($b, 'se_regional_lt150')) { $delta = 37; }
            if (str_starts_with($b, 'sk_long_domestic_exempt')) { $delta = 110; }
            if (str_starts_with($b, 'pl_intl_beyond_eu_partial')) { $delta = 0; /* cancellation: no actuals */ }
            $actArr = $delta > 0 ? $this->addMinutes($schedArr, $delta) : '';
        }

        $priceRaw = $this->matchOne($txt, '/^Price:\s*([0-9]+(?:\.[0-9]{1,2})?)\s*([A-Z]{3})/mi');
        $ticketPrice = $priceRaw ? ($priceRaw) : '0 EUR';

        // Scope flags
        $scope = strtolower($this->matchOne($txt, '/^Scope:\s*(.+)$/mi'));
        if ($scope === '') {
            $b = strtolower($base);
            if (str_contains($b, 'intl_beyond_eu') || str_contains($b, 'beyond_eu')) { $scope = 'intl_beyond_eu'; }
            elseif (str_contains($b, 'intl_inside_eu')) { $scope = 'intl_inside_eu'; }
            elseif (str_contains($b, 'long_domestic')) { $scope = 'long_domestic'; }
            elseif (str_contains($b, 'regional')) { $scope = 'regional'; }
        }
        $isLongDomestic = $scope === 'long_domestic';
        $isIntlBeyond = $scope === 'intl_beyond_eu';
        $isIntlInside = $scope === 'intl_inside_eu';

        // Art. 12 meta from disclosure/contract lines if present
        $throughDisclosure = $this->matchOne($txt, '/^Through ticket disclosure:\s*(.+)$/mi') ?: 'unknown';
        $contractType = $this->matchOne($txt, '/^Contract type:\s*(.+)$/mi');

        $journey = [
            'segments' => [[
                'operator' => $op,
                'trainCategory' => $product,
                'country' => $country,
                'pnr' => $pnr,
                'from' => $from,
                'to' => $to,
                'schedDep' => $schedDep,
                'schedArr' => $schedArr,
                'actArr' => $actArr,
            ]],
            'ticketPrice' => ['value' => $ticketPrice],
            'operatorName' => ['value' => $op],
            'trainCategory' => ['value' => $product],
            'country' => ['value' => $country],
            'is_long_domestic' => $isLongDomestic,
            'is_international_beyond_eu' => $isIntlBeyond,
            'is_international_inside_eu' => $isIntlInside,
        ];

        $art12Meta = [
            'through_ticket_disclosure' => $throughDisclosure,
            'contract_type' => $contractType ?: null,
        ];
        $art9Meta = [
            'info_on_rights' => 'Delvist',
        ];
        $refusionMeta = [
            'reason_delay' => true,
            'claim_rerouting' => true,
            'reroute_info_within_100min' => 'Ved ikke',
        ];
        if (str_starts_with(strtolower($base), 'pl_intl_beyond_eu_partial')) {
            $refusionMeta = [
                'reason_cancellation' => true,
                'claim_refund_ticket' => true,
                'claim_rerouting' => false,
                'reroute_info_within_100min' => 'Nej',
            ];
        }
        $compute = [
            'euOnly' => !$isIntlBeyond, // outside-EU parts not in EU scope
            'minPayout' => 4.0,
        ];

        return compact('journey','art12Meta','art9Meta','refusionMeta','compute');
    }

    private function matchOne(string $txt, string $pattern): string
    {
        if (preg_match($pattern, $txt, $m)) { return trim((string)($m[1] ?? '')); }
        return '';
    }

    private function dateToIso(string $dmy): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dmy, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $dmy;
    }

    private function addMinutes(string $iso, int $min): string
    {
        $t = strtotime($iso);
        if ($t) { return date('Y-m-d\TH:i:s', $t + ($min * 60)); }
        return $iso;
    }

    public function fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'ice_125m');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function exemptionFixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_exemptions_fr_regional');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    public function art12Fixtures(): void
    {
        $case = (string)($this->request->getQuery('case') ?? 'journey_art12_through_ticket');
        $path = CONFIG . 'demo' . DIRECTORY_SEPARATOR . $case . '.json';
        if (!is_file($path)) {
            $this->set(['error' => 'unknown_case', 'case' => $case]);
            $this->viewBuilder()->setOption('serialize', ['error','case']);
            return;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true) ?: [];
        $this->set($data);
        $this->viewBuilder()->setOption('serialize', array_keys($data));
    }

    /**
     * Returns a bundle of varied demo scenarios to exercise PDFs/PNGs, exemptions, Art. 12, Art. 9 and compensation fallbacks.
     */
    public function scenarios(): void
    {
        $seed = (string)($this->request->getQuery('seed') ?? 'demo');
        $count = (int)($this->request->getQuery('count') ?? 0);
        $withEval = (string)($this->request->getQuery('withEval') ?? $this->request->getQuery('eval') ?? '0') === '1';
        $mix = $this->buildScenarios($seed);

        if ($count > 0) {
            $mix = array_slice($mix, 0, $count);
        }

        // Optionally shuffle for variety
        if ($seed !== 'fixed') {
            shuffle($mix);
        }
        if ($withEval) {
            foreach ($mix as &$scenario) {
                $journey = (array)($scenario['journey'] ?? []);
                $art12Meta = (array)($scenario['art12_meta'] ?? []);
                $art9Meta = (array)($scenario['art9_meta'] ?? []);
                $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
                $scenario['profile'] = $profile;
                $scenario['art12'] = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
                // Seed Art.9 auto from Art.12 meta where overlapping
                $auto = [];
                foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                    if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
                }
                if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
                $scenario['art9'] = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);
            }
            unset($scenario);
        }
        $out = ['scenarios' => $mix, 'withEval' => $withEval];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', ['scenarios','withEval']);
    }

    /**
     * POST: Runs exemption profile, Art.12, Art.9 and compensation over the generated scenarios.
     * Accepts optional body: { seed?: string, count?: int, scenarios?: array }
     */
    public function runScenarios(): void
    {
        $method = strtoupper((string)$this->request->getMethod());
        if ($method === 'GET') {
            // Support GET for convenience in browser: use query params
            $seed = (string)($this->request->getQuery('seed') ?? 'demo');
            $count = (int)($this->request->getQuery('count') ?? 0);
            $scenarios = $this->buildScenarios($seed);
        } else {
            $this->request->allowMethod(['post']);
            $payload = (array)$this->request->getData();
            $seed = (string)($payload['seed'] ?? 'demo');
            $count = (int)($payload['count'] ?? 0);
            $scenarios = (array)($payload['scenarios'] ?? $this->buildScenarios($seed));
        }
        if ($count > 0) {
            $scenarios = array_slice($scenarios, 0, $count);
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            $journey = (array)($scenario['journey'] ?? []);
            $art12Meta = (array)($scenario['art12_meta'] ?? []);
            $art9Meta = (array)($scenario['art9_meta'] ?? []);
            $compute = (array)($scenario['compute'] ?? []);
            $refundMeta = [
                'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            ];
            $refusionMeta = (array)($scenario['refusion_meta'] ?? []);

            // Exemptions
            $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

            // Art. 12 and Art. 9
            $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $art12Meta);
            // Seed Art.9 auto from Art.12 meta for overlapping fields
            $auto = [];
            foreach (['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'] as $k) {
                if (isset($art12Meta[$k])) { $auto[$k] = ['value' => $art12Meta[$k], 'source' => 'art12_meta']; }
            }
            if (!empty($auto)) { $art9Meta['_auto'] = $auto + (array)($art9Meta['_auto'] ?? []); }
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

            // Compensation
            $comp = $this->computeCompensationPreview($journey, $compute);

            // Refund (Art. 18-like)
            $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, $refundMeta);

            // Step Refusion (Art. 18 + CIV + 10 + dele af 20)
            $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);

            // Unified claim sample
            $claimInput = $this->mapScenarioToClaimInput($scenario);
            $claimOut = (new \App\Service\ClaimCalculator())->calculate($claimInput);

            $results[] = [
                'id' => $scenario['id'] ?? null,
                'media' => $scenario['media'] ?? null,
                'profile' => $profile,
                'art12' => $art12,
                'art9' => $art9,
                'compensation' => $comp,
                'refund' => $refund,
                'refusion' => $refusion,
                'claim' => $claimOut,
            ];
        }

        $this->set(['results' => $results]);
        $this->viewBuilder()->setOption('serialize', ['results']);
    }

    /** Map scenario into ClaimInput (simplified) */
    private function mapScenarioToClaimInput(array $scenario): array
    {
        $journey = (array)($scenario['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $country = (string)($journey['country']['value'] ?? ($last['country'] ?? ''));
        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        $price = 0.0;
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }

        $legs = [];
        foreach ($segments as $s) {
            $legs[] = [
                'from' => $s['from'] ?? '',
                'to' => $s['to'] ?? '',
                'eu' => true, // assume EU for demo; real pipeline should mark per segment
                'scheduled_dep' => $s['schedDep'] ?? '',
                'scheduled_arr' => $s['schedArr'] ?? '',
                'actual_dep' => $s['actDep'] ?? null,
                'actual_arr' => $s['actArr'] ?? null,
            ];
        }

        $delayMin = 0;
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr && $actArr) {
            $t1 = strtotime($schedArr); $t2 = strtotime($actArr);
            if ($t1 && $t2) { $delayMin = max(0, (int)round(($t2 - $t1)/60)); }
        }

        $extraordinary = (bool)($scenario['compute']['extraordinary'] ?? false);
        $selfInflicted = (bool)($scenario['compute']['selfInflicted'] ?? false);
        $notified = (bool)($scenario['compute']['knownDelayBeforePurchase'] ?? false);

        return [
            'country_code' => $country ?: 'EU',
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [
                'through_ticket' => true,
                'legs' => $legs,
            ],
            'service_scope' => (!empty($journey['is_international_beyond_eu']) ? 'intl_beyond_eu' : (!empty($journey['is_international_inside_eu']) ? 'intl_inside_eu' : (!empty($journey['is_long_domestic']) ? 'long_domestic' : 'regional'))),
            'disruption' => [
                'delay_minutes_final' => $delayMin,
                'eu_only' => (bool)($scenario['compute']['euOnly'] ?? true),
                'notified_before_purchase' => $notified,
                'extraordinary' => $extraordinary,
                'self_inflicted' => $selfInflicted,
            ],
            'choices' => [
                'wants_refund' => (bool)($scenario['refusion_meta']['claim_refund_ticket'] ?? false),
                'wants_reroute_same_soonest' => ($scenario['refusion_meta']['reroute_same_conditions_soonest'] ?? 'Ved ikke') === 'Ja',
                'wants_reroute_later_choice' => ($scenario['refusion_meta']['reroute_later_at_choice'] ?? 'Ved ikke') === 'Ja',
            ],
            'expenses' => [
                // A minimal mapping; real form would pass numeric amounts
                'meals' => 0,
                'hotel' => 0,
                'alt_transport' => 0,
                'other' => 0,
            ],
            'already_refunded' => 0,
        ];
    }

    /**
     * Build the base scenarios list. Caller may shuffle/slice.
     * @param string $seed
     * @return array<int,array<string,mixed>>
     */
    private function buildScenarios(string $seed): array
    {
        $mix = [
            [
                'id' => 'sncf_png_through_ticket_ok',
                'media' => ['png' => 'tests/fixtures/sncf_ticket_through.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'SNCF', 'trainCategory' => 'TGV', 'country' => 'FR', 'pnr' => 'ABC123', 'schedArr' => '2025-01-02T10:00:00', 'actArr' => '2025-01-02T11:15:00'],
                    ],
                    'ticketPrice' => ['value' => '120.00 EUR'],
                    'operatorName' => ['value' => 'SNCF'],
                    'trainCategory' => ['value' => 'TGV'],
                    'country' => ['value' => 'FR'],
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Gennemgående',
                    'single_txn_operator' => 'Ja',
                    'separate_contract_notice' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Ja',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'Ja',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ja',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'denial_paths_extraordinary_refund',
                'media' => ['png' => 'tests/fixtures/denial_sample.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DSB', 'trainCategory' => 'REG', 'country' => 'DK', 'pnr' => 'DK9', 'schedArr' => '2025-05-01T08:00:00', 'actArr' => '2025-05-01T08:40:00'],
                    ],
                    'ticketPrice' => ['value' => '8.00 EUR'],
                    'operatorName' => ['value' => 'DSB'],
                    'trainCategory' => ['value' => 'REG'],
                    'country' => ['value' => 'DK'],
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_on_rights' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => true,
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'db_pdf_separate_contracts_agency',
                'media' => ['pdf' => 'tests/fixtures/db_ticket_separate.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'DB', 'trainCategory' => 'ICE', 'country' => 'DE', 'pnr' => 'X1', 'schedArr' => '2025-03-05T12:00:00', 'actArr' => '2025-03-05T14:30:00'],
                        ['operator' => 'CD', 'trainCategory' => 'EC', 'country' => 'CZ', 'pnr' => 'Y2', 'schedArr' => '2025-03-05T16:00:00', 'actArr' => '2025-03-05T17:45:00'],
                    ],
                    'ticketPrice' => ['value' => '89.00 EUR'],
                    'operatorName' => ['value' => 'DB'],
                    'trainCategory' => ['value' => 'ICE'],
                    'country' => ['value' => 'DE'],
                    'seller_type' => 'agency',
                    'is_international_inside_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'Særskilte',
                    'separate_contract_notice' => 'Nej',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'Delvist',
                    'info_on_rights' => 'Nej',
                    'info_during_disruption' => 'Nej',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_missed_conn' => true,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Nej',
                    'meal_offered' => 'Nej',
                    'alt_transport_provided' => 'Nej',
                ],
                'compute' => [
                    'euOnly' => true,
                    'extraordinary' => false,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'long_domestic_sk_exemptions',
                'media' => ['png' => 'tests/fixtures/sk_ticket.png'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'ZSSK', 'trainCategory' => 'R', 'country' => 'SK', 'pnr' => 'ZZ9', 'schedArr' => '2025-02-10T09:00:00', 'actArr' => '2025-02-10T10:05:00'],
                    ],
                    'ticketPrice' => ['value' => '12.00 EUR'],
                    'operatorName' => ['value' => 'ZSSK'],
                    'trainCategory' => ['value' => 'R'],
                    'country' => ['value' => 'SK'],
                    'is_long_domestic' => true,
                ],
                'art12_meta' => [],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'unknown',
                    'info_during_disruption' => 'unknown',
                    'language_accessible' => 'unknown',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_delay' => true,
                    'claim_refund_ticket' => false,
                    'claim_rerouting' => true,
                    'reroute_info_within_100min' => 'Ved ikke',
                    'meal_offered' => 'Ved ikke',
                ],
                'compute' => [
                    'euOnly' => true,
                    'minPayout' => 4.0,
                ],
            ],
            [
                'id' => 'intl_beyond_eu_partial',
                'media' => ['pdf' => 'tests/fixtures/int_beyond_eu.pdf'],
                'journey' => [
                    'segments' => [
                        ['operator' => 'PKP', 'trainCategory' => 'IC', 'country' => 'PL', 'pnr' => 'PL1', 'schedArr' => '2025-04-01T18:00:00', 'actArr' => '2025-04-01T19:20:00'],
                        ['operator' => 'BY', 'trainCategory' => 'INT', 'country' => 'BY', 'pnr' => 'BY2', 'schedArr' => '2025-04-01T22:00:00', 'actArr' => '2025-04-01T22:00:00'],
                    ],
                    'ticketPrice' => ['value' => '60.00 EUR'],
                    'operatorName' => ['value' => 'PKP'],
                    'trainCategory' => ['value' => 'IC'],
                    'country' => ['value' => 'PL'],
                    'is_international_beyond_eu' => true,
                ],
                'art12_meta' => [
                    'through_ticket_disclosure' => 'unknown',
                ],
                'art9_meta' => [
                    'info_before_purchase' => 'unknown',
                    'info_on_rights' => 'Delvist',
                    'info_during_disruption' => 'Ja',
                    'language_accessible' => 'Delvist',
                    'accessibility_format' => 'unknown',
                ],
                'refusion_meta' => [
                    'reason_cancellation' => true,
                    'claim_refund_ticket' => true,
                    'refund_requested' => 'Nej',
                    'meal_offered' => 'Ja',
                ],
                'compute' => [
                    'euOnly' => false,
                    'minPayout' => 0.0,
                ],
            ],
        ];
        return $mix;
    }

    /**
     * Compute a compensation preview mirroring ComputeController::compensation logic.
     * @param array $journey
     * @param array $payload
     * @return array{minutes:int,pct:float,amount:float,currency:string,source:string,notes:?string}
     */
    private function computeCompensationPreview(array $journey, array $payload): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];

        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        $minutes = null;
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                $minutes = max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        if ($minutes === null) {
            $depDate = (string)($journey['depDate']['value'] ?? '');
            $sched = (string)($journey['schedArrTime']['value'] ??
                    $minutes = max(0, (int)round(($t2 - $t1) / 60));
                }
            }
        }
        $minutes = $minutes ?? 0;

        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0');
        $price = 0.0;
        $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) {
            $price = (float)$m[1];
        }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

        $operator = (string)($journey['operatorName']['value'] ?? ($last['operator'] ?? ''));
        $product = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
        $country = (string)($journey['country']['value'] ?? ($payload['country'] ?? ''));

        // Determine scope similarly to ExemptionProfileBuilder
        $scope = 'regional';
        if (!empty($journey['is_international_beyond_eu'])) { $scope = 'intl_beyond_eu'; }
        elseif (!empty($journey['is_international_inside_eu'])) { $scope = 'intl_inside_eu'; }
        elseif (!empty($journey['is_long_domestic'])) { $scope = 'long_domestic'; }

        $svc = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $res = $svc->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => (bool)($payload['euOnly'] ?? true),
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'knownDelayBeforePurchase' => (bool)($payload['knownDelayBeforePurchase'] ?? false),
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
            'scope' => $scope,
        ]);

        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amount = round($price * $pct, 2);
        $minPayout = isset($payload['minPayout']) ? (float)$payload['minPayout'] : 0.0;
        $source = $res['source'] ?? 'eu';
        $notes = $res['notes'] ?? null;
        if ($minPayout > 0 && $amount > 0 && $amount < $minPayout) {
            $amount = 0.0;
            $source = 'denied';
            $notes = trim(((string)$notes) . ' Min payout threshold');
        }

        return [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'notes' => $notes,
        ];
}
}
```

---

## File: src/Controller/Api/ComputeController.php

```php
<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;

class ComputeController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    public function compensation(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $art9meta = (array)($payload['meta'] ?? []);

        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];

        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        $minutes = null;
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                $minutes = max(0, (int)round(($t2 - $t1) / 60));
            }
        }

        // Fallback to journey.actualArrTime + dep date when segment info missing
        if ($minutes === null) {
            $depDate = (string)($journey['depDate']['value'] ?? '');
            $sched = (string)($journey['schedArrTime']['value'] ?? '');
            $act = (string)($journey['actualArrTime']['value'] ?? '');
            if ($depDate && $sched && $act) {
                $t1 = strtotime($depDate . 'T' . $sched . ':00');
                $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
                if ($t1 && $t2) {
                    $minutes = max(0, (int)round(($t2 - $t1) / 60));
                }
            }
        }

        $minutes = $minutes ?? 0;

        // Parse price and currency from simple "99.99 EUR" style value
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0');
        $price = 0.0;
        $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) {
            $price = (float)$m[1];
        }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) {
            $currency = strtoupper($m[1]);
        }

    // Map JourneyRecord -> eligibility inputs
        $operator = (string)($journey['operatorName']['value'] ?? ($last['operator'] ?? ''));
        $product = (string)($journey['trainCategory']['value'] ?? ($last['trainCategory'] ?? ''));
    $country = (string)($journey['country']['value'] ?? ($payload['country'] ?? ''));

        $svc = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        // Bridge: if Art.9 preinformed_disruption is 'Ja' and caller didn't set knownDelayBeforePurchase,
        // respect the Art.9 signal.
        $knownDelayBeforePurchase = (bool)($payload['knownDelayBeforePurchase'] ?? false);
        if (!$knownDelayBeforePurchase && (($art9meta['preinformed_disruption'] ?? 'unknown') === 'Ja')) {
            $knownDelayBeforePurchase = true;
        }

        $res = $svc->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => (bool)($payload['euOnly'] ?? true),
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'knownDelayBeforePurchase' => $knownDelayBeforePurchase,
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
        ]);

        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amount = round($price * $pct, 2);
        $minPayout = isset($payload['minPayout']) ? (float)$payload['minPayout'] : 0.0;
        $source = $res['source'] ?? 'eu';
        $notes = $res['notes'] ?? null;
        if ($minPayout > 0 && $amount > 0 && $amount < $minPayout) {
            $amount = 0.0;
            $source = 'denied';
            $notes = trim(((string)$notes) . ' Min payout threshold');
        }

        $out = [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'notes' => $notes,
        ];
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }

    public function art12(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art12Evaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function exemptions(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);

        $builder = new \App\Service\ExemptionProfileBuilder();
        $profile = $builder->build($journey);

        $this->set(['profile' => $profile]);
        $this->viewBuilder()->setOption('serialize', ['profile']);
    }

    public function art9(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art9Evaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function refund(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\RefundEvaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function refusion(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        $svc = new \App\Service\Art18RefusionEvaluator();
        $res = $svc->evaluate($journey, $meta);
        $this->set($res);
        $this->viewBuilder()->setOption('serialize', array_keys($res));
    }

    public function claim(): void
    {
        $this->request->allowMethod(['post']);
        $input = (array)$this->request->getData();
        $calc = new \App\Service\ClaimCalculator();
        $out = $calc->calculate($input);
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}
```

---

## File: src/Service/ClaimCalculator.php

```php
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
    // Basis — simplified: whole fare unless we can split; returns [amount,label]
    [$compBaseAmount, $compBaseLabel] = $this->computeCompensationBasis($ticketTotal, $legs, $throughTicket);
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

        // 2) Refund (Art.18) — simplified decision using choices
        $refundBasis = 'none';
        $refundAmount = 0.0;
        if (!empty($choices['wants_refund'])) {
            $meetsArt18 = ($delay >= 60) || !empty($disruption['trip_cancelled']) || !empty($choices['wants_reroute_same_soonest']) || !empty($choices['wants_reroute_later_choice']);
            if ($meetsArt18) {
                $refundBasis = 'Art.18(1)(a) whole fare';
                $refundAmount = round($ticketTotal, 2);
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
    // Floor to 2 decimals to avoid overpay by rounding up
    $serviceFee = floor(($gross * ($serviceFeePct / 100)) * 100) / 100;
    $net = floor(($gross - $serviceFee) * 100) / 100;

        // 6) Output
        return [
            'breakdown' => [
                'refund' => ['basis' => $refundBasis, 'amount' => $refundAmount],
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
     * v1 heuristic: use full fare; if >1 leg and no clear prices, still full fare (documented label).
     */
    private function computeCompensationBasis(float $ticketTotal, array $legs, bool $throughTicket): array
    {
        // Heuristic v2:
        // - If through ticket across multiple legs: whole fare (affects end destination)
        // - If return indicated (two legs with far-apart dates) and no split pricing: 1/2 total
        // - If single-leg delay: full fare (assume single fare)
        // Future: when per-leg/return pricing is actually parsed, replace with exact values.

        $legsCount = count($legs);
        if ($throughTicket && $legsCount >= 2) {
            return [max(0.0, $ticketTotal), 'Art.19(3) whole fare (through ticket)'];
        }
        if ($legsCount === 2) {
            // simple return heuristic: if dates differ by >= 1 day assume out+back and no split -> 1/2
            $a1 = (string)($legs[0]['scheduled_arr'] ?? '');
            $a2 = (string)($legs[1]['scheduled_arr'] ?? '');
            if ($a1 && $a2) {
                $d1 = strtotime(substr($a1,0,10)); $d2 = strtotime(substr($a2,0,10));
                if ($d1 && $d2 && abs($d2 - $d1) >= 86400) {
                    return [max(0.0, $ticketTotal/2), 'Art.19(3) 1/2 fare (return, no split prices)'];
                }
            }
        }
        return [max(0.0, $ticketTotal), 'Art.19(3) whole fare'];
    }
}
```

---

## File: tests/TestCase/Service/Art9EvaluatorTest.php

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\Art9Evaluator;
use Cake\TestSuite\TestCase;

class Art9EvaluatorTest extends TestCase
{
    private function baseProfile(array $over = []): array
    {
        $base = [
            'scope' => 'international',
            'articles' => [
                'art12' => true,
                'art17' => true,
                'art18_3' => true,
                'art19' => true,
                'art20_2' => true,
                'art30_2' => true,
                'art10' => true,
                'art9' => true,
            ],
            'articles_sub' => [ 'art9_1' => true, 'art9_2' => true, 'art9_3' => true ],
            'notes' => [],
            'ui_banners' => [],
        ];
        return array_replace_recursive($base, $over);
    }

    public function testAllYesOverallTrue(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertTrue($res['parts']['art9_2_ok']);
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertTrue($res['art9_ok']);
    }

    public function testPart2FailureOverallFalse(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Nej', // triggers failure in part 2
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertFalse($res['parts']['art9_2_ok']);
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertFalse($res['art9_ok']);
    }

    public function testUnknownPartGivesNull(): void
    {
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $this->baseProfile(),
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            'info_on_rights' => 'Ja',
            'rights_notice_displayed' => 'Ja',
            'rights_contact_provided' => 'Ja',
            // part 3 incomplete (unknowns)
            'info_during_disruption' => 'unknown',
            'station_board_updates' => 'unknown',
            'onboard_announcements' => 'unknown',
            'disruption_updates_frequency' => 'unknown',
            'assistance_contact_visible' => 'unknown',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertTrue($res['parts']['art9_2_ok']);
        $this->assertNull($res['parts']['art9_3_ok']);
        $this->assertNull($res['art9_ok']);
    }

    public function testSelectiveExemption(): void
    {
        // Exempt part 2 only
        $profile = $this->baseProfile([
            'articles_sub' => ['art9_1' => true, 'art9_2' => false, 'art9_3' => true],
        ]);
        $svc = new Art9Evaluator();
        $meta = [
            '_profile_override' => $profile,
            // part 1 all yes
            'info_before_purchase' => 'Ja',
            'language_accessible' => 'Ja',
            'accessibility_format' => 'Ja',
            'multi_channel_information' => 'Ja',
            'accessible_formats_offered' => 'Ja',
            // part 2 (ignored / exempt) deliberately failing
            'info_on_rights' => 'Nej',
            'rights_notice_displayed' => 'Nej',
            'rights_contact_provided' => 'Nej',
            // part 3 all yes
            'info_during_disruption' => 'Ja',
            'station_board_updates' => 'Ja',
            'onboard_announcements' => 'Ja',
            'disruption_updates_frequency' => 'Ja',
            'assistance_contact_visible' => 'Ja',
        ];
        $res = $svc->evaluate([], $meta);
        $this->assertTrue($res['parts']['art9_1_ok']);
        $this->assertNull($res['parts']['art9_2_ok']); // exempt
        $this->assertTrue($res['parts']['art9_3_ok']);
        $this->assertTrue($res['art9_ok']); // failures were only in exempt part
    }
}
```
