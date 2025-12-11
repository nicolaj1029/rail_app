<?php
declare(strict_types=1);

namespace App\Service;

class DowngradeEvaluator
{
    /**
     * Evaluate per-leg downgrade (class/reservation) and compute refund basis.
     * Legal anchors: CIV + GCC-CIV/PRR (contract), Art. 9(1) (evidence), Art. 18(2) (reroute context).
     * Inputs: journey['segments'][] with purchased/delivered/reservation fields; meta/operator catalog optional.
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $operator = (string)($journey['operator'] ?? ($meta['operator'] ?? ''));
        $currency = (string)($journey['ticketPrice']['currency'] ?? ($meta['currency'] ?? 'EUR'));
        $catalog = (array)($meta['_operators_catalog'] ?? []);
        $opRule = (array)($catalog[strtoupper($operator)]['downgrade_rules'] ?? []);
        $suppMap = (array)($catalog[strtoupper($operator)]['supplement_map'] ?? []);

        $results = [];
        $missingGlobal = [];
        $anyKnown = false; $allComputed = true;

        foreach ($segments as $i => $seg) {
            $purchased = (string)($seg['fare_class_purchased'] ?? '');
            $delStatus = (string)($seg['class_delivered_status'] ?? 'unknown');
            $amenBooked = (string)($seg['reserved_amenity_booked'] ?? 'none');
            $amenDelivered = (string)($seg['reserved_amenity_delivered'] ?? 'unknown');
            $share = $this->numOrNull($seg['downgrade_segment_share'] ?? null);
            $note = (string)($seg['downgrade_reason'] ?? '');
            $isReroute = (bool)($seg['is_reroute_leg'] ?? false);

            $from = (string)($seg['from'] ?? ''); $to = (string)($seg['to'] ?? '');
            $idLbl = trim(($from !== '' && $to !== '') ? ($from . ' → ' . $to) : ('Leg #' . ($i+1)));

            $isClassLower = (strtolower($delStatus) === 'lower');
            $amenMissing = (strtolower($amenDelivered) === 'no');
            $downgraded = $isClassLower || ($amenBooked !== 'none' && $amenMissing);
            if (!$downgraded) {
                $results[] = [
                    'legIndex' => $i,
                    'segment' => $idLbl,
                    'downgraded' => false,
                ];
                continue;
            }
            $anyKnown = true;

            // Compute basis
            $basis = [ 'method' => 'prorata' ];
            $computed = false; $miss = [];

            // 1) Supplement refund if amenity missing and known supplement
            if ($amenBooked !== 'none' && $amenMissing) {
                $suppKey = strtolower($amenBooked);
                $suppAmount = $this->numOrNull($suppMap[$suppKey]['amount'] ?? null);
                $suppCurr = (string)($suppMap[$suppKey]['currency'] ?? $currency);
                if ($suppAmount !== null && $suppAmount > 0) {
                    $basis = [ 'amount' => $suppAmount, 'currency' => $suppCurr, 'method' => 'supplement' ];
                    $computed = true;
                } else {
                    // fallthrough to tariff/prorata
                }
            }

            // 2) Operator fixed rule (e.g., DB €20)
            if (!$computed && !empty($opRule) && ($opRule['method'] ?? '') === 'fixed') {
                $amt = $this->numOrNull($opRule['fixedAmount'] ?? null);
                if ($amt !== null && $amt > 0) {
                    $basis = [ 'amount' => $amt, 'currency' => (string)($opRule['currency'] ?? $currency), 'method' => 'fixed' ];
                    $computed = true;
                }
            }

            // 3) Tariff difference (if known per-leg pricing)
            $legPrice = $this->numOrNull($seg['fare_price_leg'] ?? null);
            $uplift = $this->numOrNull($opRule['upliftRatio'] ?? null); // e.g., 0.5 means 1st ≈ 150% of 2nd
            if (!$computed && $legPrice !== null && $legPrice > 0) {
                // If uplift known, refund ≈ (uplift/(1+uplift)) of legPrice
                if ($uplift !== null && $uplift > 0) {
                    $pct = $uplift / (1.0 + $uplift);
                    $basis = [ 'amount' => round($legPrice * $pct, 2), 'currency' => $currency, 'percent' => round($pct * 100, 1), 'method' => 'tariff' ];
                    $computed = true;
                }
            }

            // 4) Prorata fallback by share
            if (!$computed) {
                if ($share === null) { $miss[] = 'downgrade_segment_share'; }
                $totalPrice = $this->numOrNull($journey['ticketPrice']['amount'] ?? null);
                if ($totalPrice === null) { $miss[] = 'ticketPrice.amount'; }
                if (empty($miss)) {
                    $segFare = $totalPrice * max(0.0, min(1.0, (float)$share));
                    $pct = ($uplift !== null && $uplift > 0) ? ($uplift / (1.0 + $uplift)) : 0.33; // conservative default
                    $basis = [ 'amount' => round($segFare * $pct, 2), 'currency' => $currency, 'percent' => round($pct * 100, 1), 'method' => 'prorata' ];
                    $computed = true;
                }
            }

            if (!$computed) { $allComputed = false; }

            $legal = [ 'CIV + GCC-CIV/PRR (kontrakt)', 'Art. 9(1) (oplysninger før køb)' ];
            if ($isReroute) { $legal[] = 'Art. 18(2) (omlægning)'; }

            $reasoning = [];
            if ($isClassLower) { $reasoning[] = 'Leveret klasse var lavere end købt.'; }
            if ($amenMissing && $amenBooked !== 'none') { $reasoning[] = 'Reserveret facilitet blev ikke leveret.'; }
            if ($note !== '') { $reasoning[] = $note; }

            $results[] = [
                'legIndex' => $i,
                'segment' => $idLbl,
                'downgraded' => true,
                'refund' => $basis,
                'missing' => $miss,
                'legal' => $legal,
                'reasoning' => $reasoning,
            ];
            $missingGlobal = array_merge($missingGlobal, $miss);
        }

        $status = null;
        if ($anyKnown) { $status = $allComputed ? true : false; }

        $policyUsed = isset($catalog[strtoupper($operator)]) ? (array)$catalog[strtoupper($operator)] : [];
        return [
            'compliance_status' => $status,
            'missing' => array_values(array_unique($missingGlobal)),
            'reasoning' => [],
            'labels' => [ 'jf. CIV + GCC-CIV/PRR', 'jf. Art. 9(1)', 'jf. Art. 18(2) (ved omlægning)' ],
            'results' => $results,
            'operator' => $operator,
            '_operators_policy_used' => $policyUsed,
        ];
    }

    private function numOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float)$v;
        return null;
    }
}
