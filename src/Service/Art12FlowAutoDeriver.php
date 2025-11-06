<?php
declare(strict_types=1);

namespace App\Service;

final class Art12FlowAutoDeriver
{
    /**
     * Auto-derive seller type, shared PNR scope, and operator multiplicity
     * from parsed journey and known meta when possible.
     *
     * @param array $flow
     * @return array{journey_seller_type:null|string,shared_pnr_scope:null|string,multi_operator:null|bool}
     */
    public function derive(array $flow): array
    {
        $meta = (array)($flow['meta'] ?? []);
        $journey = (array)($flow['journey'] ?? []);
        $segments = (array)($journey['segments'] ?? []);

        $journeySeller = $meta['journey.seller_type'] ?? $meta['seller_type'] ?? null;

        $pnrs = [];
        foreach ((array)($meta['_multi_tickets'] ?? []) as $t) {
            $pnr = (string)($t['identifiers']['pnr'] ?? '');
            if ($pnr !== '') { $pnrs[$pnr] = true; }
        }
        $sharedPnr = null;
        if (count($pnrs) > 0) {
            $sharedPnr = count($pnrs) === 1 ? 'yes' : 'no';
        }

        $ops = [];
        foreach ($segments as $s) {
            $op = (string)($s['operator'] ?? '');
            if ($op !== '') { $ops[$op] = true; }
        }
        $multiOps = null;
        if (count($ops) > 0) { $multiOps = count($ops) > 1; }

        return [
            'journey_seller_type' => is_string($journeySeller) ? $journeySeller : null,
            'shared_pnr_scope' => $sharedPnr,
            'multi_operator' => $multiOps,
        ];
    }
}
