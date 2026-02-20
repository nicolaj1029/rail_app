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
        $booked = in_array($bookedRaw, ['ja','yes','y','1','true'], true);
        $attemptedRefused = in_array($bookedRaw, ['attempted_refused','refused'], true);
        $deliveredRaw = strtolower((string)($meta['pmr_delivered_status'] ?? 'unknown'));
        if (in_array($deliveredRaw, ['yes_full','yes'], true)) { $delivered = 'yes'; }
        elseif (in_array($deliveredRaw, ['partial','no'], true)) { $delivered = 'no'; }
        else { $delivered = 'unknown'; }
        $promMiss = strtolower((string)($meta['pmr_promised_missing'] ?? 'unknown'));

        $assistanceExpected = $pmrUser && $booked;
        $assistanceBreach = $assistanceExpected && ($delivered === 'no');
        $precontractMissing = in_array($promMiss, ['ja','yes'], true);

        $compliance = 'unknown';
        if ($assistanceExpected) {
            if ($delivered === 'no') { $compliance = 'non_compliant'; }
            elseif ($delivered === 'yes') { $compliance = 'compliant'; }
        } elseif ($attemptedRefused) {
            $compliance = 'non_compliant';
        }

        $routeClaim = ($assistanceBreach || $attemptedRefused || $precontractMissing);

        $reasoning = [];
        if ($pmrUser) { $reasoning[] = 'PMR bruger: ja'; } else { $reasoning[] = 'PMR bruger: nej'; }
        $reasoning[] = 'Assistance bestilt: ' . ($booked ? 'ja' : ($attemptedRefused ? 'forsogt - afvist' : 'nej'));
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
