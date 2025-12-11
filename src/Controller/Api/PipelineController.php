<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class PipelineController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Unified pipeline: client uploads text/JSON, we ingest and run all evaluators.
     * POST body accepts: { text?: string, journey?: object, meta?: object, compute?: object }
     * Returns: { journey, meta, profile, art12, art9, compensation, refund, refusion, claim, logs }
     */
    public function run(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $evalSel = (string)($this->request->getQuery('eval') ?? ($payload['eval'] ?? ''));
        $compact = (bool)($this->request->getQuery('compact') ?? (bool)($payload['compact'] ?? false));

        // Inline ingest mapping (avoid controller coupling)
        $text = (string)($payload['text'] ?? '');
        $journey = (array)($payload['journey'] ?? []);
        // Merge wizard step data (step3/4/5) into meta so evaluators can see user answers
        $wizard = (array)($payload['wizard'] ?? []);
        $wizardStep3 = (array)($wizard['step3_entitlements'] ?? []);
        $wizardStep4 = (array)($wizard['step4_choices'] ?? []);
        $wizardStep5 = (array)($wizard['step5_assistance'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
        // Wizard answers should take precedence over bare meta
        $meta = array_merge($meta, $wizardStep3, $wizardStep4, $wizardStep5);
        $logs = [];
        if ($text !== '') {
            $map = (new \App\Service\OcrHeuristicsMapper())->mapText($text);
            foreach (($map['auto'] ?? []) as $k => $v) { $meta['_auto'][$k] = $v; }
            $logs = array_merge($logs, $map['logs'] ?? []);
        }

        // Build profile
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

        // Art. 12 & 9
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, (array)($payload['art12_meta'] ?? []));
        $art9Meta = (array)($payload['art9_meta'] ?? []) + $meta; // merge AUTO into art9 meta
        // Load operators catalog for evaluator policies (downgrade, supplements)
        try {
            $opsPath = ROOT . DS . 'config' . DS . 'data' . DS . 'operators_catalog.json';
            if (file_exists($opsPath)) {
                $opsJson = (string)file_get_contents($opsPath);
                $opsData = json_decode($opsJson, true);
                if (is_array($opsData) && isset($opsData['downgrade_policies'])) {
                    $meta['_operators_catalog'] = (array)$opsData['downgrade_policies'];
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal; evaluators will fall back
        }
        $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);
        // Split sub-evaluations for clarity
        $art9_fastest = (new \App\Service\Art9FastestEvaluator())->evaluate($journey, $art9Meta);
        $art9_pricing = (new \App\Service\Art9PricingEvaluator())->evaluate($journey, $art9Meta);
        $art9_preknown = (new \App\Service\Art9PreknownEvaluator())->evaluate($journey, $art9Meta);
        // Downgrade per-leg (CIV/GCC-CIV/PRR with Art.9(1) evidence; Art.18(2) for reroute)
        try {
            $downgrade = (new \App\Service\DowngradeEvaluator())->evaluate($journey, ['operator' => ($journey['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')), 'currency' => ($journey['ticketPrice']['currency'] ?? 'EUR'), '_operators_catalog' => ((array)($meta['_operators_catalog'] ?? []))]);
        } catch (\Throwable $e) {
            $downgrade = ['error' => 'downgrade_failed', 'message' => $e->getMessage()];
        }
        // Art. 6 (bicycles) — minimal compliance evaluator, additive output
        try {
        $art6 = (new \App\Service\Art6BikeEvaluator())->evaluate($journey, $art9Meta);
        } catch (\Throwable $e) {
            $art6 = ['error' => 'art6_failed', 'message' => $e->getMessage()];
        }
        // Art. 21–24 (PMR assistance)
        try {
            $pmr = (new \App\Service\Art21to24PmrEvaluator())->evaluate($journey, $art9Meta);
        } catch (\Throwable $e) {
            $pmr = ['error' => 'pmr_failed', 'message' => $e->getMessage()];
        }

        // Compensation preview mirrors ComputeController::compensation
        $compute = (array)($payload['compute'] ?? []);
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? ''); $actArr = (string)($last['actArr'] ?? '');
        $minutes = 0;
        if ($schedArr && $actArr) { $t1 = strtotime($schedArr); $t2 = strtotime($actArr); if ($t1 && $t2) { $minutes = max(0, (int)round(($t2-$t1)/60)); } }
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $price = 0.0; $currency = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        // E4: EU-only delay if requested
        $euOnlyFlag = (bool)($compute['euOnly'] ?? true);
        if ($euOnlyFlag && isset($compute['delayMinEU'])) {
            $euMin = (int)$compute['delayMinEU'];
            if ($euMin >= 0) { $minutes = $euMin; }
        }
        $elig = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $knownDelay = (bool)($compute['knownDelayBeforePurchase'] ?? false);
        if (!$knownDelay && (($art9Meta['preinformed_disruption'] ?? 'unknown') === 'Ja')) { $knownDelay = true; }
        $compRes = $elig->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => $euOnlyFlag,
            'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            'art18Option' => (string)($compute['art18Option'] ?? ''),
            'knownDelayBeforePurchase' => $knownDelay,
            'extraordinary' => (bool)($compute['extraordinary'] ?? false),
            'selfInflicted' => (bool)($compute['selfInflicted'] ?? false),
            'throughTicket' => (bool)($compute['throughTicket'] ?? true),
        ]);
        $pct = ((int)($compRes['percent'] ?? 0)) / 100;
        // E3: apportion return or leg price if provided
        $amountBase = $price;
        $legPrice = isset($compute['legPrice']) ? (float)$compute['legPrice'] : null;
        if ($legPrice !== null && $legPrice > 0) { $amountBase = $legPrice; }
        elseif (!empty($compute['returnTicket'])) { $amountBase = $price / 2; }
        $amount = round($amountBase * $pct, 2);
        $compensation = [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $compRes['source'] ?? 'eu',
            'notes' => $compRes['notes'] ?? null,
        ];

        // Refund + Refusion + Claim
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, (array)($payload['refund_meta'] ?? $meta));
        // Merge refusion_meta overrides on top of merged meta (step4 answers)
        $refusionMeta = array_merge($meta, (array)($payload['refusion_meta'] ?? []));
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);
        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [ 'through_ticket' => true, 'legs' => [] ],
            'disruption' => [ 'delay_minutes_final' => $minutes, 'eu_only' => (bool)($compute['euOnly'] ?? true), 'notified_before_purchase' => $knownDelay, 'extraordinary' => (bool)($compute['extraordinary'] ?? false), 'self_inflicted' => (bool)($compute['selfInflicted'] ?? false) ],
            'choices' => [ 'wants_refund' => false, 'wants_reroute_same_soonest' => false, 'wants_reroute_later_choice' => false ],
            'expenses' => [ 'meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0 ],
            'already_refunded' => 0,
        ]);

        // Art. 20 (assistance) evaluation (step 5)
        try {
            $art20_assistance = (new \App\Service\Art20AssistanceEvaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art20_assistance = ['error' => 'art20_failed', 'message' => $e->getMessage()];
        }

        $out = compact('journey','meta','logs','profile','art12','art9','art9_fastest','art9_pricing','art9_preknown','downgrade','art6','pmr','art20_assistance','compensation','refund','refusion','claim');
        // Support selective eval output for demo/testing: eval=art9|art9.fastest|art9.pricing|mct|art30.complaints
        if ($evalSel !== '') {
            $keep = [];
            switch ($evalSel) {
                case 'art9':
                    $keep = ['journey','meta','profile','art9','art9_fastest','art9_pricing','art9_preknown','downgrade'];
                    break;
                case 'art9.fastest':
                    $keep = ['journey','meta','profile','art9_fastest'];
                    break;
                case 'art9.pricing':
                    $keep = ['journey','meta','profile','art9_pricing'];
                    break;
                case 'art9.preknown':
                    $keep = ['journey','meta','profile','art9_preknown'];
                    break;
                case 'art9.downgrade':
                    $keep = ['journey','meta','profile','downgrade'];
                    break;
                default:
                    // Unknown eval key → no special filtering
                    $keep = [];
            }
            if ($compact && !empty($keep)) {
                $out = array_intersect_key($out, array_flip($keep));
            }
        }
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}
