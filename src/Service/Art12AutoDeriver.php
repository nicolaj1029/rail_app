<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Art12AutoDeriver
 *
 * Derives a subset of Art.12 hooks from journey data and identifiers without
 * overriding explicit user inputs. Targets questions 2,3,5,6,7,8,13:
 *  - single_txn_operator
 *  - single_txn_retailer
 *  - shared_pnr_scope
 *  - seller_type_operator
 *  - seller_type_agency
 *  - multi_operator_trip
 *  - single_booking_reference
 *
 * Conventions:
 *  - Uses 'Ja' / 'Nej' / 'Ved ikke' tri-language values to align with UI.
 *  - Only fills when current value is empty/unknown.
 *  - Adds short log entries to meta['logs'] explaining each AUTO inference.
 */
final class Art12AutoDeriver
{
    /**
     * Compatibility helper returning only derived key=>value pairs for merging into meta.
     * Does not include logs and does not override explicit user-provided values.
     *
     * @param array $journey
     * @return array<string,string>
     */
    public function derive(array $journey): array
    {
        $res = $this->apply($journey, []);
        $meta = (array)($res['meta'] ?? []);
        // Keep only the keys this deriver is responsible for
        $keys = [
            'single_txn_operator','single_txn_retailer','shared_pnr_scope',
            'seller_type_operator','seller_type_agency','multi_operator_trip','single_booking_reference'
        ];
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $meta)) { $out[$k] = (string)$meta[$k]; }
        }
        return $out;
    }
    /**
     * @param array $journey Expected keys: 'segments'[], 'bookingRef' (string|null), 'seller_type' ('operator'|'agency'|null)
     * @param array $meta    May contain existing user answers for Art.12 hooks
     * @return array{meta: array, logs: string[]}
     */
    public function apply(array $journey, array $meta = []): array
    {
        $logs = [];
        $meta = (array)$meta;

        $segments   = (array)($journey['segments'] ?? []);
        $bookingRef = $journey['bookingRef'] ?? null;
        $sellerType = $journey['seller_type'] ?? null; // 'operator'|'agency'|null

        // Collect PNRs and carriers from segments
        $pnrs = [];
        $carriers = [];
        foreach ($segments as $s) {
            $p = $s['pnr'] ?? null;
            if (is_string($p) && $p !== '') { $pnrs[] = $p; }
            $c = $s['carrier'] ?? ($s['operator'] ?? null);
            if (is_string($c) && $c !== '') { $carriers[] = $c; }
        }
        $uniquePnrs = array_values(array_unique($pnrs));
        $sharedByAuto = (
            (is_string($bookingRef) && $bookingRef !== '') ||
            (count($uniquePnrs) === 1 && ($uniquePnrs[0] ?? '') !== '')
        );
        $multiOpsAuto = (count(array_unique($carriers)) > 1);

        // Helper to check if an existing value is a firm answer ('Ja'/'Nej')
        $isFirm = function($v): bool {
            $s = is_string($v) ? trim(mb_strtolower($v)) : '';
            return in_array($s, ['ja','nej','yes','no'], true);
        };
        // Helper to check if existing is unknown/empty
        $isUnknown = function($v): bool {
            if ($v === null) return true;
            if (!is_string($v)) return false;
            $s = trim($v);
            if ($s === '') return true;
            $sl = mb_strtolower($s);
            return in_array($sl, ['ved ikke','unknown','-'], true);
        };

        // 2) single_txn_operator (AUTO from seller_type + bookingRef)
        if (!isset($meta['single_txn_operator']) || $isUnknown($meta['single_txn_operator'])) {
            if ($sellerType === 'operator' && $sharedByAuto) {
                $meta['single_txn_operator'] = 'Ja';
                $logs[] = 'AUTO: single_txn_operator=Ja (seller=operator & shared booking/PNR).';
            }
        }

        // 3) single_txn_retailer (AUTO from seller_type + bookingRef)
        if (!isset($meta['single_txn_retailer']) || $isUnknown($meta['single_txn_retailer'])) {
            if ($sellerType === 'agency' && $sharedByAuto) {
                $meta['single_txn_retailer'] = 'Ja';
                $logs[] = 'AUTO: single_txn_retailer=Ja (seller=agency & shared booking/PNR).';
            }
        }

        // 5) shared_pnr_scope (AUTO from bookingRef/PNR set)
        if (!isset($meta['shared_pnr_scope']) || $isUnknown($meta['shared_pnr_scope'])) {
            if ($sharedByAuto) {
                $meta['shared_pnr_scope'] = 'Ja';
                $logs[] = 'AUTO: shared_pnr_scope=Ja (bookingRef or single PNR across segments).';
            } elseif (!empty($pnrs)) {
                // If there are PNRs but not shared, we can safely say 'Nej'
                $meta['shared_pnr_scope'] = 'Nej';
                $logs[] = 'AUTO: shared_pnr_scope=Nej (multiple distinct PNRs).';
            }
        }

        // 6) seller_type_operator (AUTO from journey.seller_type)
        if (!isset($meta['seller_type_operator']) || $isUnknown($meta['seller_type_operator'])) {
            if ($sellerType === 'operator') {
                $meta['seller_type_operator'] = 'Ja';
                $logs[] = 'AUTO: seller_type_operator=Ja (journey.seller_type=operator).';
            } elseif ($sellerType === 'agency') {
                $meta['seller_type_operator'] = 'Nej';
                $logs[] = 'AUTO: seller_type_operator=Nej (journey.seller_type=agency).';
            }
        }

        // 7) seller_type_agency (AUTO from journey.seller_type)
        if (!isset($meta['seller_type_agency']) || $isUnknown($meta['seller_type_agency'])) {
            if ($sellerType === 'agency') {
                $meta['seller_type_agency'] = 'Ja';
                $logs[] = 'AUTO: seller_type_agency=Ja (journey.seller_type=agency).';
            } elseif ($sellerType === 'operator') {
                $meta['seller_type_agency'] = 'Nej';
                $logs[] = 'AUTO: seller_type_agency=Nej (journey.seller_type=operator).';
            }
        }

        // 8) multi_operator_trip (AUTO from carriers on segments)
        if (!isset($meta['multi_operator_trip']) || $isUnknown($meta['multi_operator_trip'])) {
            if (!empty($carriers)) {
                $meta['multi_operator_trip'] = $multiOpsAuto ? 'Ja' : 'Nej';
                $logs[] = 'AUTO: multi_operator_trip=' . ($multiOpsAuto ? 'Ja' : 'Nej') . ' (carriers across segments).';
            }
        }

        // 13) single_booking_reference (AUTO mirrors shared_pnr_scope)
        if (!isset($meta['single_booking_reference']) || $isUnknown($meta['single_booking_reference'])) {
            if ($sharedByAuto) {
                $meta['single_booking_reference'] = 'Ja';
                $logs[] = 'AUTO: single_booking_reference=Ja (bookingRef or single PNR across segments).';
            } elseif (!empty($pnrs)) {
                $meta['single_booking_reference'] = 'Nej';
                $logs[] = 'AUTO: single_booking_reference=Nej (multiple distinct PNRs).';
            }
        }

        return ['meta' => $meta, 'logs' => $logs];
    }
}

?>