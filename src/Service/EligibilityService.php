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
        // 1. Hard denials (Art.19 exclusions + CIV/self-inflicted + Art.18(a) refund choice)
        if (!empty($ctx['selfInflicted'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Self-inflicted'];
        }
        if (!empty($ctx['refundAlready'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Refund already paid'];
        }
        if (!empty($ctx['art18Option']) && strtolower((string)$ctx['art18Option']) === 'a') {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Art. 18(a) chosen (refund)'];
        }
        if (!empty($ctx['knownDelayBeforePurchase'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Known delay before purchase'];
        }
        if (!empty($ctx['extraordinary'])) {
            return ['percent' => 0, 'source' => 'denied', 'notes' => 'Extraordinary circumstances'];
        }

        // 2. Matrix scope blocks (national/regional) — early exit
        if (!empty($ctx['country']) && !empty($ctx['scope'])) {
            $mx = $this->matrix ?: new ExemptionMatrixRepository();
            $rows = $mx->find(['country' => $ctx['country'], 'scope' => (string)$ctx['scope']]);
            foreach ($rows as $r) {
                if (!empty($r['blocked'])) {
                    $out = [
                        'percent' => 0,
                        'source' => 'denied',
                        'notes' => ucfirst((string)$ctx['scope']) . ' scope blocked by national/regional exemption',
                        'scope' => (string)$ctx['scope'],
                        'exemptions' => (array)($r['exemptions'] ?? []),
                    ];
                    if (!empty($r['reason'])) { $out['overrideNotes'] = (string)$r['reason']; }
                    return $out;
                }
            }
        }

        $exMatched = [];
        $article19Exempt = false;
        if (!empty($ctx['country'])) {
            $exMatched = $this->exemptions->find([
                'country' => $ctx['country'],
                'scope' => $ctx['scope'] ?? null,
            ]);
            foreach ($exMatched as $row) {
                if (isset($row['articlesExempt']) && in_array('19', (array)$row['articlesExempt'], true)) {
                    $article19Exempt = true; break;
                }
            }
        }

        // 3. Base EU rule (percent only). If exempt, base is 0 and we will only consider override.
        $delay = (int)($ctx['delayMin'] ?? 0);
        $percent = 0;
        if (!$article19Exempt) {
            if ($delay >= 120) { $percent = 50; }
            elseif ($delay >= 60) { $percent = 25; }
        }

        // 4. National/operator/product override tiers (may supply higher % or fixed payout)
        $override = $this->overrides->findOne([
            'country' => $ctx['country'] ?? null,
            'operator' => $ctx['operator'] ?? null,
            'product' => $ctx['product'] ?? null,
        ]);

        if ($override) {
            [$ovPercent, $ovPayout, $ovMin] = $this->applyTiersWithPayout($delay, $override['tiers'] ?? []);
            // Prefer national override whenever it applies (even if % equals EU baseline) so source reflects the override.
            $preferOverride = ($ovPercent > $percent)
                || ($ovPercent === $percent && $ovPercent > 0)
                || ($article19Exempt && $ovPercent >= 0);
            if (($ctx['scope'] ?? '') === 'intl_inside_eu' && $ovPercent === $percent) {
                $preferOverride = false;
            }
            if ($preferOverride) {
                $out = [
                    'percent' => $ovPercent,
                    'source' => $article19Exempt ? 'override_exempt' : 'override',
                    'notes' => $article19Exempt ? 'Art. 19 exempt; override applied' : 'Override applied',
                ];
                if ($ovPayout) { $out['payout'] = $ovPayout; }
                if (!empty($override['notes'])) { $out['overrideNotes'] = (string)$override['notes']; }
                if (!empty($override['source'])) { $out['overrideSource'] = (string)$override['source']; }
                // bubble exemptions & scope
                foreach ($exMatched as $row) {
                    if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                    if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
                }
                if (empty($out['exemptions']) && !empty($override['exemptions'])) { $out['exemptions'] = (array)$override['exemptions']; }
                if (empty($out['scope']) && !empty($override['scope'])) { $out['scope'] = (string)$override['scope']; }
                return $out;
            }
        }

        // 5. If Art.19 was exempt and no override improved it, deny
        if ($article19Exempt) {
            $out = ['percent' => 0, 'source' => 'denied', 'notes' => 'Art. 19 exempt in this context'];
            foreach ($exMatched as $row) {
                if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
                if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
            }
            return $out;
        }

        // 6. Return EU baseline result (with any exemption meta bubbled for UI badges)
        $out = ['percent' => $percent, 'source' => 'eu'];
        foreach ($exMatched as $row) {
            if (!empty($row['articlesExempt'])) { $out['exemptions'] = (array)$row['articlesExempt']; }
            if (!empty($row['scope'])) { $out['scope'] = (string)$row['scope']; }
        }
        return $out;
    }

    /**
     * Return the EU baseline minimum delay for a given percent value.
     * @param int $percent
     * @return int  minDelayMin for EU baseline or PHP_INT_MAX when none
     */
    private function baselineMinForPercent(int $percent): int
    {
        return match ($percent) {
            50 => 120,
            25 => 60,
            default => PHP_INT_MAX,
        };
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
            return [$best, $bestPayout, $bestMin];
    }
}

