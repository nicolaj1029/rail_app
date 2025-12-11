<?php
declare(strict_types=1);

namespace App\Service;

class Art9FastestEvaluator
{
    /**
     * Minimal evaluator for Art. 9 fastest-route duties (Bilag II pkt. 2).
     * Inputs expected in $meta: fastest_flag_at_purchase, alts_shown_precontract, mct_realistic
     * Values: 'Ja'|'Nej'|'Delvist'|'unknown'
     * @return array{hooks:array<string,string>, compliance_status:?bool, missing:string[], ask:string[], reasoning:string[]}
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $hooks = [
            'fastest_flag_at_purchase' => (string)($meta['fastest_flag_at_purchase'] ?? 'unknown'),
            'alts_shown_precontract' => (string)($meta['alts_shown_precontract'] ?? 'unknown'),
            'mct_realistic' => (string)($meta['mct_realistic'] ?? 'unknown'),
        ];

        $missing = [];
        $ask = [];
        foreach ($hooks as $k => $v) { if ($v === 'unknown' || $v === '') { $missing[] = $k; $ask[] = $k; } }

        $negative = in_array('Nej', $hooks, true);
        $allKnown = !in_array('unknown', $hooks, true) && !in_array('', $hooks, true);
        $status = null;
        if ($negative) { $status = false; }
        elseif ($allKnown) { $status = true; }

        $reasoning = [];
        if ($hooks['fastest_flag_at_purchase'] === 'Nej') {
            $reasoning[] = 'Hurtigste rute ikke tydeligt markeret ved køb.';
        }
        if ($hooks['alts_shown_precontract'] === 'Nej') {
            $reasoning[] = 'Alternative hurtigere ruter ikke vist før køb.';
        }
        if ($hooks['mct_realistic'] === 'Nej') {
            $reasoning[] = 'Skiftetider under anbefalet MCT; realistisk forbindelse tvivlsom.';
        }

        return [
            'hooks' => $hooks,
            'compliance_status' => $status,
            'missing' => $missing,
            'ask' => $ask,
            'reasoning' => $reasoning,
            'labels' => ['jf. Art. 9(1) + Bilag II, pkt. 2'],
        ];
    }
}
