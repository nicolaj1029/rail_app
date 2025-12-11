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

    private function gone(): void
    {
        $this->response = $this->response->withStatus(404);
        $this->set(['status' => 404, 'error' => 'Endpoint disabled']);
        $this->viewBuilder()->setOption('serialize', ['status','error']);
    }

    public function compensation(): void
    {
        $this->gone();
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
        // E4: allow caller to provide EU-only delay; if euOnly=true and provided, prefer it
        $euOnlyFlag = (bool)($payload['euOnly'] ?? true);
        if ($euOnlyFlag && isset($payload['delayMinEU'])) {
            $euMin = (int)$payload['delayMinEU'];
            if ($euMin >= 0) { $minutes = $euMin; }
        }

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
            'euOnly' => $euOnlyFlag,
            'refundAlready' => (bool)($payload['refundAlready'] ?? false),
            'art18Option' => (string)($payload['art18Option'] ?? ''),
            'knownDelayBeforePurchase' => $knownDelayBeforePurchase,
            'extraordinary' => (bool)($payload['extraordinary'] ?? false),
            'selfInflicted' => (bool)($payload['selfInflicted'] ?? false),
            'throughTicket' => (bool)($payload['throughTicket'] ?? true),
            'operator' => $operator ?: null,
            'product' => $product ?: null,
            'country' => $country ?: null,
        ]);
        // E3: apportionment for return tickets or per-leg price if provided
        $pct = ((int)($res['percent'] ?? 0)) / 100;
        $amountBase = $price;
        $returnFlag = (bool)($payload['returnTicket'] ?? false);
        $legPrice = isset($payload['legPrice']) ? (float)$payload['legPrice'] : null;
        if ($legPrice !== null && $legPrice > 0) {
            $amountBase = $legPrice;
        } elseif ($returnFlag) {
            $amountBase = $price / 2;
        }
        $amount = round($amountBase * $pct, 2);
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
        $this->gone();
    }

    public function exemptions(): void
    {
        $this->gone();
    }

    public function art9(): void
    {
        $this->gone();
    }

    public function refund(): void
    {
        $this->gone();
    }

    public function refusion(): void
    {
        $this->gone();
    }

    public function claim(): void
    {
        $this->gone();
    }
}
