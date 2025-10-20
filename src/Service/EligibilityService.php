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
    * - refundAlready: if true, no compensation (ticket already refunded)
    * - art18Option: when provided, only option 'a' (refund) denies compensation (E1)
     * - knownDelayBeforePurchase: if true, no compensation (Art. 19(9))
     * - extraordinary: if true, no compensation (Art. 19(10))
     * - selfInflicted: if true, deny (CIV fault by passenger)
     * - throughTicket: hint for upstream aggregation; this function does not split segments itself
     * - country/operator/product: used to look up national/operator overrides
     *
    * @param array{delayMin:int, euOnly:bool, refundAlready?:bool, art18Option?:string, knownDelayBeforePurchase?:bool, extraordinary?:bool, selfInflicted?:bool, throughTicket?:bool, country?:string, operator?:string, product?:string, scope?:string} $ctx
     * @return array{percent:int, source:string, notes?:string}
     */
    public function computeCompensation(array $ctx): array
    {
        // Hard denials first
        // E1: Only Art. 18 option (a) denies compensation; rerouting options (b)/(c) do not.
        if (!empty($ctx['selfInflicted'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Self-inflicted'];
        }
        if (!empty($ctx['refundAlready'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Refund already paid'];
        }
        if (!empty($ctx['art18Option'])) {
            $opt = strtolower((string)$ctx['art18Option']);
            if ($opt === 'a') {
                return ['percent' => 0, 'source' => 'denied', 'notes' => 'Art. 18(a) chosen (refund)'];
            }
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
