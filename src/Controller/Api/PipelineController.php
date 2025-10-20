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

    // Inline ingest mapping (avoid controller coupling)
        $text = (string)($payload['text'] ?? '');
        $journey = (array)($payload['journey'] ?? []);
        $meta = (array)($payload['meta'] ?? []);
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
        $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);

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
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, (array)($payload['refund_meta'] ?? []));
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, (array)($payload['refusion_meta'] ?? []));
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

        $out = compact('journey','meta','logs','profile','art12','art9','compensation','refund','refusion','claim');
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}
