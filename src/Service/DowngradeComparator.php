<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Heuristic comparator for "downgrade" (lovet vs. leveret) to suggest partial refund under Art. 18(a–c).
 * Inputs tolerated:
 *  - fare_class_purchased: '1'|'2'|'Business'|'Standard'|...
 *  - class_delivered_status: 'Same'|'Lower'|'Higher'|... or explicit delivered class
 *  - reserved_amenity_delivered: 'Ja'|'Nej'|'Ved ikke'
 *  - promised_facilities: string|array
 *  - facilities_delivered_status: 'Ja'|'Nej'|'Delvist'|'Ved ikke'
 *  - downgrade_occurred / downgrade_comp_basis as user-overrides
 * Returns: [severity: none|min or minor|major, suggested_pct: float, basis: string, reasoning: string[]]
 */
class DowngradeComparator
{
    /** @param array<string,mixed> $ctx */
    public function assess(array $ctx): array
    {
        $reasons = [];
        $basisParts = [];
        $pct = 0.0;

        // Normalize helper
        $norm = function($v): string { return strtolower(trim((string)$v)); };
        $tri = function($v): string {
            $s = $norm = strtolower(trim((string)$v));
            if (in_array($s, ['ja','yes','true','1'], true)) return 'Ja';
            if (in_array($s, ['nej','no','false','0'], true)) return 'Nej';
            if ($s === '' || $s === 'unknown' || $s === 'ved ikke') return 'Ved ikke';
            return ucfirst($s);
        };

        // Class downgrade detection
        $purchased = $norm($ctx['fare_class_purchased'] ?? '');
        $deliveredStatus = $norm($ctx['class_delivered_status'] ?? '');
        $downgradeFlag = $tri($ctx['downgrade_occurred'] ?? 'Ved ikke');
        $basisOverride = $norm($ctx['downgrade_comp_basis'] ?? '');

        $classHit = false;
        if ($downgradeFlag === 'Ja' && ($basisOverride === 'class' || $basisOverride === '')) {
            $classHit = true;
            $reasons[] = 'Bruger angiver downgrade i klasse (overstyring).';
        } elseif (in_array($deliveredStatus, ['lower','nedgraderet','downgrade','downgraded'], true)) {
            $classHit = true;
            $reasons[] = 'Leveret klasse er lavere end købt.';
        }
        if ($classHit) {
            $pct += 0.30; // heuristic 30% for class downgrade
            $basisParts[] = 'class';
        }

        // Amenity/facilities detection
        $amenDelivered = $tri($ctx['reserved_amenity_delivered'] ?? 'Ved ikke');
        $promised = $ctx['promised_facilities'] ?? null; // string|array
        $facDeliveredStatus = $tri($ctx['facilities_delivered_status'] ?? 'Ved ikke');

        $amenHit = false;
        if ($downgradeFlag === 'Ja' && ($basisOverride === 'amenity' || $basisOverride === '')) {
            $amenHit = true; $reasons[] = 'Bruger angiver downgrade i facilitet (overstyring).';
        }
        if ($amenDelivered === 'Nej') { $amenHit = true; $reasons[] = 'Reserveret facilitet ikke leveret.'; }
        if ($facDeliveredStatus === 'Nej' || $facDeliveredStatus === 'Delvist') {
            $amenHit = true; $reasons[] = 'Lovede faciliteter ikke leveret/kun delvist.';
        }
        if ($promised) { $reasons[] = 'Lovede faciliteter: ' . (is_array($promised) ? implode(', ', $promised) : (string)$promised); }
        if ($amenHit) {
            $pct += 0.10; // heuristic 10% for amenity/facility non-delivery
            $basisParts[] = 'amenity';
        }

        // Cap and finalize
        if ($pct <= 0.0) {
            return [
                'severity' => 'none',
                'suggested_pct' => 0.0,
                'basis' => '',
                'reasoning' => ['Ingen tydelig downgrade identificeret.'],
            ];
        }
        $pct = min($pct, 0.50); // cap at 50%
        $severity = $pct >= 0.30 ? 'major' : 'minor';
        $basis = implode('+', array_unique($basisParts));

        return [
            'severity' => $severity,
            'suggested_pct' => $pct,
            'basis' => $basis,
            'reasoning' => $reasons,
        ];
    }
}
