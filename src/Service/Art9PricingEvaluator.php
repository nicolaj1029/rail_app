<?php
declare(strict_types=1);

namespace App\Service;

class Art9PricingEvaluator
{
    /**
     * Minimal evaluator for Art. 9 pricing transparency (Bilag II pkt. 3).
     * Inputs in $meta: multiple_fares_shown, cheapest_highlighted, fare_flex_type, train_specificity
     * Values: 'Ja'|'Nej'|'Delvist'|'unknown' (for yes/no), strings for type/scope
     * @return array{hooks:array<string,mixed>, compliance_status:?bool, missing:string[], ask:string[], reasoning:string[]}
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $hooks = [
            'multiple_fares_shown' => (string)($meta['multiple_fares_shown'] ?? 'unknown'),
            'cheapest_highlighted' => (string)($meta['cheapest_highlighted'] ?? 'unknown'),
            'fare_flex_type' => (string)($meta['fare_flex_type'] ?? 'unknown'),
            'train_specificity' => (string)($meta['train_specificity'] ?? 'unknown'),
        ];

        $missing = [];
        $ask = [];
        foreach (['multiple_fares_shown','cheapest_highlighted'] as $k) {
            $v = (string)$hooks[$k]; if ($v === 'unknown' || $v === '') { $missing[] = $k; $ask[] = $k; }
        }

        $negative = ($hooks['multiple_fares_shown'] === 'Nej') || ($hooks['cheapest_highlighted'] === 'Nej');
        $allKnownYN = ($hooks['multiple_fares_shown'] !== 'unknown' && $hooks['cheapest_highlighted'] !== 'unknown');
        $status = null;
        if ($negative) { $status = false; }
        elseif ($allKnownYN) { $status = true; }

        $reasoning = [];
        if ($hooks['multiple_fares_shown'] === 'Nej') { $reasoning[] = 'Flere prisniveauer var ikke vist før køb.'; }
        if ($hooks['cheapest_highlighted'] === 'Nej') { $reasoning[] = 'Billigste pris var ikke tydeligt fremhævet.'; }

        return [
            'hooks' => $hooks,
            'compliance_status' => $status,
            'missing' => $missing,
            'ask' => $ask,
            'reasoning' => $reasoning,
            'labels' => ['jf. Art. 9(1) + Bilag II, pkt. 3'],
        ];
    }
}
