<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\EligibilityService;
use App\Service\ExemptionsRepository;
use App\Service\NationalOverridesRepository;
use App\Service\OperatorCatalog;

class ClaimsController extends AppController
{
    public function start(): void
    {
        $catalog = new OperatorCatalog();
        $countries = $catalog->getCountries();
        $operators = $catalog->getOperators();
        $products = $catalog->getProducts();
        $productScopes = $catalog->getProductScopes();
        // Filter: drop products in any blocked scope under its operators
        $matrix = new \App\Service\ExemptionMatrixRepository();
        foreach ($operators as $cc => $ops) {
            $blockedScopes = [];
            foreach (['regional','long_domestic','intl_inside_eu','intl_beyond_eu'] as $scName) {
                $rows = $matrix->find(['country' => $cc, 'scope' => $scName]);
                foreach ($rows as $r) { if (!empty($r['blocked'])) { $blockedScopes[$scName] = true; break; } }
            }
            if ($blockedScopes) {
                foreach ($ops as $opId => $opName) {
                    $list = $products[$opId] ?? [];
                    $scopes = $productScopes[$opId] ?? [];
                    $filtered = array_values(array_filter($list, function($p) use ($scopes, $blockedScopes) {
                        $sc = $scopes[$p] ?? '';
                        return empty($blockedScopes[$sc]);
                    }));
                    $products[$opId] = $filtered;
                }
            }
        }
        // Build override meta map for tooltips (notes/source/exemptions) keyed by operator->product
        $overrideRepo = new NationalOverridesRepository();
        $meta = [];
        foreach ($overrideRepo->all() as $row) {
            $op = (string)($row['operator'] ?? '');
            $prod = (string)($row['product'] ?? '');
            if ($op === '' || $prod === '') { continue; }
            $meta[$op][$prod] = [
                'notes' => (string)($row['notes'] ?? ''),
                'source' => (string)($row['source'] ?? ''),
                'exemptions' => $row['exemptions'] ?? [],
                'scope' => $row['scope'] ?? null,
            ];
        }
        $overrideMeta = $meta;
        $this->set(compact('countries', 'operators', 'products', 'overrideMeta'));
    }

    public function compute(): void
    {
        $this->request->allowMethod(['post']);

        $delay = (int)($this->request->getData('delay_min') ?? 0);
        $ctx = [
            'delayMin' => $delay,
            'euOnly' => true,
            'refundAlready' => (bool)$this->request->getData('refund_already'),
            'knownDelayBeforePurchase' => (bool)$this->request->getData('known_delay_before_purchase'),
            'extraordinary' => (bool)$this->request->getData('extraordinary'),
            'selfInflicted' => (bool)$this->request->getData('self_inflicted'),
            'country' => (string)($this->request->getData('country') ?? ''),
            'operator' => (string)($this->request->getData('operator') ?? ''),
            'product' => (string)($this->request->getData('product') ?? ''),
            'scope' => (string)($this->request->getData('scope') ?? ''),
        ];
        $service = new EligibilityService(new ExemptionsRepository(), new NationalOverridesRepository());
        $result = $service->computeCompensation($ctx);
        $this->set(compact('result', 'ctx'));
        $this->viewBuilder()->setTemplate('result');
    }
}
