<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Minimal evaluator for Articles 21–24 (PMR assistance).
 * Inputs: pmr_user, pmr_booked (or attempted_refused), pmr_delivered_status, pmr_promised_missing, pmr_facility_details.
 * Output: assistance_expected, assistance_breach, precontract_facilities_missing, compliance_status, route_to_pmr_claim, reasoning[].
 */
class Art21to24PmrEvaluator
{
    /** @param array<string,mixed> $meta */
    public function evaluate(array $journey, array $meta = []): array
    {
        $isYes = function($v): bool { $s = strtolower(trim((string)$v)); return in_array($s, ['ja','yes','y','1','true'], true); };
        $pmrUser = $isYes($meta['pmr_user'] ?? null);
        $bookedRaw = strtolower(trim((string)($meta['pmr_booked'] ?? 'unknown')));
        $booked = $bookedRaw === 'ja' || $bookedRaw === 'yes';
        $attemptedRefused = ($bookedRaw === 'attempted_refused');
        $delivered = strtolower((string)($meta['pmr_delivered_status'] ?? 'unknown'));
        $promMiss = strtolower((string)($meta['pmr_promised_missing'] ?? 'unknown'));

        $assistanceExpected = $pmrUser && $booked;
        $assistanceBreach = $assistanceExpected && in_array($delivered, ['no','partial'], true);
        $precontractMissing = in_array($promMiss, ['ja','yes'], true);

        $compliance = 'unknown';
        if ($assistanceExpected) {
            if ($delivered === 'no') { $compliance = 'non_compliant'; }
            elseif ($delivered === 'partial') { $compliance = 'partial'; }
            elseif ($delivered === 'yes_full') { $compliance = 'compliant'; }
        } elseif ($attemptedRefused) {
            $compliance = 'non_compliant';
        }

        $routeClaim = ($assistanceBreach || $attemptedRefused || $precontractMissing);

        $reasoning = [];
        if ($pmrUser) { $reasoning[] = 'PMR bruger: ja'; } else { $reasoning[] = 'PMR bruger: nej'; }
        $reasoning[] = 'Assistance bestilt: ' . ($booked ? 'ja' : ($attemptedRefused ? 'forsøgt – afvist' : 'nej')); 
        $reasoning[] = 'Leveret status: ' . $delivered;
        if ($precontractMissing) { $reasoning[] = 'Lovede PMR-faciliteter manglede før køb.'; }

        return [
            'assistance_expected' => $assistanceExpected,
            'assistance_breach' => $assistanceBreach,
            'precontract_facilities_missing' => $precontractMissing,
            'compliance_status' => $compliance,
            'route_to_pmr_claim' => $routeClaim,
            'reasoning' => $reasoning,
        ];
    }
}
