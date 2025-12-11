<?php
namespace App\Service;

class Art9PreknownEvaluator
{
    /**
     * Evaluate preknown disruption/delay duties under Art.9(1) + Annex II pt.7
     * and their linkage to Art.19(9) exclusion. Non-invasive, tolerant of
     * missing meta fields.
     */
    public function evaluate(array $journey, array $art9Meta): array
    {
        $preinformed = $this->boolOrNull($art9Meta['preinformed_disruption'] ?? null);
        $channels = is_array($art9Meta['preinfo_channels'] ?? null) ? $art9Meta['preinfo_channels'] : null;
        $knownMinutes = $this->intOrNull($art9Meta['known_delay_before_purchase_minutes'] ?? null);
        $purchaseTime = $art9Meta['ticket_purchase_time'] ?? null;

        $missing = [];
        if ($preinformed === null) {
            $missing[] = 'preinformed_disruption';
        }
        if ($preinformed === true && empty($channels)) {
            $missing[] = 'preinfo_channels';
        }

        $reasoning = [];
        $labels = [
            'Art. 9(1) + Bilag II, pkt. 7',
            'Art. 19(9) (kompensationsundtagelse)',
        ];

        $compliance = null; // ?bool to align with other evaluators
        $compExclusion = false;

        if ($preinformed === true) {
            $compliance = true;
            $reasoning[] = 'Forsinkelsen/afbrydelsen var oplyst før køb.';
            if ($knownMinutes !== null && $knownMinutes >= 60) {
                $compExclusion = true;
                $reasoning[] = 'Kendt forsinkelse ≥ 60 min ved købstidspunkt (Art. 19(9)).';
            }
        } elseif ($preinformed === false) {
            $compliance = false;
            $reasoning[] = 'Ingen forudgående oplysning om relevant afbrydelse/forsinkelse ved køb.';
        } else {
            $reasoning[] = 'Uklarhed om forudgående oplysning — mangler oplysninger.';
        }

        return [
            'compliance_status' => $compliance,
            'missing' => $missing,
            'ask' => $missing,
            'reasoning' => $reasoning,
            'labels' => $labels,
            'effects' => [
                'compensation_excluded_by_art_19_9' => $compExclusion,
                'ticket_purchase_time' => $purchaseTime,
            ],
            'hooks' => [
                'preinformed_disruption' => $preinformed,
                'preinfo_channels' => $channels,
                'known_delay_before_purchase_minutes' => $knownMinutes,
            ],
        ];
    }

    private function boolOrNull($v): ?bool
    {
        if (is_bool($v)) return $v;
        if ($v === 1 || $v === '1' || $v === 'true' || $v === 'yes' || $v === 'Ja') return true;
        if ($v === 0 || $v === '0' || $v === 'false' || $v === 'no' || $v === 'Nej') return false;
        return null;
    }

    private function intOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int)$v;
        return null;
    }
}
