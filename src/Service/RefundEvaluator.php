<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates eligibility for refund ("refusion") aligned with Article 18 rights.
 * Simplified rules:
 *  - If final arrival delay >= 60 minutes, passenger should be offered refund option.
 *  - "refundAlready" denies new refund (already processed elsewhere).
 *  - Extraordinary circumstances DO NOT affect refund eligibility (unlike compensation).
 *  - Unknown times => eligible = null; UI should prompt.
 */
class RefundEvaluator
{
    /**
     * @param array $journey Expected keys: segments[] with schedArr/actArr or fallback dep/arr fields
     * @param array $meta Optional flags: refundAlready?:bool
     * @return array{
     *   minutes:int|null,
     *   eligible:bool|null,
     *   reasoning:string[],
     *   fallback_recommended:string[]
     * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $minutes = $this->computeDelayMinutes($journey);
        $reasoning = [];
        $fallbacks = [];

        if ($minutes === null) {
            $reasoning[] = 'Manglende tidsdata; kan ikke beregne forsinkelse.';
            $fallbacks[] = 'ask_actual_and_scheduled_times';
            return [
                'minutes' => null,
                'eligible' => null,
                'reasoning' => $reasoning,
                'fallback_recommended' => array_values(array_unique($fallbacks)),
            ];
        }

        $eligible = $minutes >= 60;
        if ($eligible) {
            $reasoning[] = 'Forsinkelse >= 60 min → tilbud om refusion (Art. 18).';
            $fallbacks[] = 'offer_refund_option';
            $fallbacks[] = 'offer_rerouting_option';
            $fallbacks[] = 'show_terms_art18';
        } else {
            $reasoning[] = 'Forsinkelse < 60 min → ingen automatisk refusion.';
            $fallbacks[] = 'explain_threshold_60m';
        }

        if (!empty($meta['refundAlready'])) {
            $eligible = false;
            $reasoning[] = 'Allerede refunderet.';
        }

        return [
            'minutes' => $minutes,
            'eligible' => $eligible,
            'reasoning' => $reasoning,
            'fallback_recommended' => array_values(array_unique($fallbacks)),
        ];
    }

    private function computeDelayMinutes(array $journey): ?int
    {
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? '');
        $actArr = (string)($last['actArr'] ?? '');
        if ($schedArr !== '' && $actArr !== '') {
            $t1 = strtotime($schedArr);
            $t2 = strtotime($actArr);
            if ($t1 && $t2) {
                return max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        // Fallback fields
        $depDate = (string)($journey['depDate']['value'] ?? '');
        $sched = (string)($journey['schedArrTime']['value'] ?? '');
        $act = (string)($journey['actualArrTime']['value'] ?? '');
        if ($depDate && $sched && $act) {
            $t1 = strtotime($depDate . 'T' . $sched . ':00');
            $t2 = strtotime(($journey['actualArrDate']['value'] ?? $depDate) . 'T' . $act . ':00');
            if ($t1 && $t2) {
                return max(0, (int)round(($t2 - $t1) / 60));
            }
        }
        return null;
    }
}
