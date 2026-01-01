<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;

class EvaluateAppController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Accepts the mobile Case Close payload directly and evaluates using the same
     * services as the unified pipeline. Returns a concise result but keeps keys
     * aligned with `/api/pipeline/run` where practical.
     *
     * POST JSON body: { journey: {...}, event: {...}, receipts: [...], assistance: {...}, compensation: {...} }
     */
    public function index(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();

        $journeyIn = (array)($payload['journey'] ?? []);
        $event = (array)($payload['event'] ?? []);
        $assist = (array)($payload['assistance'] ?? []);
        $compIn = (array)($payload['compensation'] ?? []);

        // Map ticket price
        $priceStr = (string)($journeyIn['ticket_price'] ?? '0');
        $currency = strtoupper((string)($journeyIn['ticket_currency'] ?? 'EUR'));
        $ticketPrice = [
            'value' => trim($priceStr) !== '' ? ($priceStr . ' ' . $currency) : ('0 ' . $currency),
            'currency' => $currency,
        ];

        // Single segment from dep/arr ISO
        $depIso = (string)($journeyIn['dep_time'] ?? '');
        $arrIso = (string)($journeyIn['arr_time'] ?? '');
        $segments = [[
            'schedDep' => $depIso,
            'schedArr' => $arrIso,
            'from' => (string)($journeyIn['dep'] ?? ''),
            'to' => (string)($journeyIn['arr'] ?? ''),
            'operator' => (string)($journeyIn['operator'] ?? ''),
            'product' => (string)($journeyIn['ticket_type'] ?? ''),
        ]];

        $journey = [
            'operator' => (string)($journeyIn['operator'] ?? ''),
            'ticketPrice' => $ticketPrice,
            'throughTicket' => (bool)($journeyIn['through_ticket'] ?? true),
            'segments' => $segments,
        ];

        // Wizard/meta mapping (assistance)
        $wizard = [
            'step5_assistance' => [
                'got_meals' => (bool)($assist['got_meals'] ?? false),
                'got_hotel' => (bool)($assist['got_hotel'] ?? false),
                'got_transport' => (bool)($assist['got_transport'] ?? false),
                'self_paid_meals' => (bool)($assist['self_paid_meals'] ?? false),
                'self_paid_hotel' => (bool)($assist['self_paid_hotel'] ?? false),
                'self_paid_transport' => (bool)($assist['self_paid_transport'] ?? false),
                'needs_wheelchair' => (bool)($assist['needs_wheelchair'] ?? false),
                'needs_escort' => (bool)($assist['needs_escort'] ?? false),
                'other_needs' => (string)($assist['other_needs'] ?? ''),
            ],
        ];

        // Compute overrides (delay + art18 option)
        $delayText = (string)($event['delay_minutes'] ?? '0');
        $delayMinEU = (int)preg_replace('/[^0-9]/', '', $delayText);
        $art18Option = $this->mapArt18Choice((string)($compIn['choice'] ?? ''));
        $compute = [
            'euOnly' => true,
            'delayMinEU' => $delayMinEU,
            'throughTicket' => (bool)($journeyIn['through_ticket'] ?? true),
            'refundAlready' => false,
        ];
        if ($art18Option !== null) { $compute['art18Option'] = $art18Option; }

        // Compensation
        $segmentsArr = (array)($journey['segments'] ?? []);
        $last = !empty($segmentsArr) ? $segmentsArr[array_key_last($segmentsArr)] : [];
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? ''); // mobile payload does not include actArr; minutes comes from compute
        $minutes = (int)($compute['delayMinEU'] ?? 0);

        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $price = 0.0; $currencyOut = 'EUR';
        if (preg_match('/([0-9]+(?:\.[0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)$m[1]; }
        if (preg_match('/([A-Z]{3})/i', $priceRaw, $m)) { $currencyOut = strtoupper($m[1]); }

        $elig = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $compRes = $elig->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => (bool)($compute['euOnly'] ?? true),
            'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            'art18Option' => (string)($compute['art18Option'] ?? ''),
            'knownDelayBeforePurchase' => false,
            'extraordinary' => false,
            'selfInflicted' => false,
            'throughTicket' => (bool)($compute['throughTicket'] ?? true),
        ]);

        $pct = ((int)($compRes['percent'] ?? 0)) / 100.0;
        $amount = round($price * $pct, 2);
        $compensation = [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currencyOut,
            'source' => $compRes['source'] ?? 'eu',
            'notes' => $compRes['notes'] ?? null,
        ];

        // Refund + Refusion mirroring pipeline defaults
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, []);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, []);

        $out = compact('journey','wizard','compute','compensation','refund','refusion');
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }

    private function mapArt18Choice(string $c): ?string
    {
        $c = strtolower(trim($c));
        return match ($c) {
            'refund' => 'refund',
            'reroute_now' => 'reroute_now',
            'reroute_later' => 'reroute_later',
            default => null,
        };
    }
}
