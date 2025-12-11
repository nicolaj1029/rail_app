<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Minimal Article 6 evaluator (carriage of bicycles).
 * Inputs are read from meta (already normalized by FlowController/mapper):
 *  - bike_was_present, bike_caused_issue, bike_reservation_made, bike_reservation_required,
 *    bike_denied_boarding, bike_refusal_reason_provided, bike_refusal_reason_type,
 *    bike_res_required (alias), bike_denied_reason (consolidated if present).
 *
 * Output focuses on compliance and routing hints rather than entitlements (Art.18/19 handle those):
 *  - reservation_required/reservation_made/denied_boarding/refusal_reason_type
 *  - refusal_justified (true/false/null)
 *  - compliance_status: 'compliant'|'non_compliant'|'unknown'
 *  - route_to_art18: bool (if unjustified denial likely triggers rerouting/refund paths)
 *  - reasoning: string[] (short human messages for UI)
 */
class Art6BikeEvaluator
{
    /** @param array<string,mixed> $meta */
    public function evaluate(array $journey, array $meta = []): array
    {
        $yn = function($v): string {
            $s = strtolower(trim((string)$v));
            return match ($s) {
                'ja','yes','y','1','true' => 'yes',
                'nej','no','n','0','false' => 'no',
                default => 'unknown'
            };
        };

        $reservationRequired = $yn($meta['bike_res_required'] ?? ($meta['bike_reservation_required'] ?? 'unknown'));
        $reservationMade = $yn($meta['bike_reservation_made'] ?? 'unknown');
        $denied = $yn($meta['bike_denied_boarding'] ?? 'unknown');

        // Prefer already consolidated reason if present
        $reason = (string)($meta['bike_denied_reason'] ?? '');
        if ($reason === '' || $reason === 'unknown') {
            $rt = strtolower((string)($meta['bike_refusal_reason_type'] ?? 'unknown'));
            $rp = $yn($meta['bike_refusal_reason_provided'] ?? 'unknown');
            if (in_array($rt, ['capacity','equipment','weight_dim','other'], true)) {
                $reason = $rt;
            } elseif ($denied === 'yes' && $rp === 'yes') {
                $reason = 'unspecified';
            } elseif ($denied === 'no') {
                $reason = 'none';
            } else {
                $reason = 'unknown';
            }
        }

        $reasonAllowed = in_array($reason, ['capacity','equipment','weight_dim'], true);

        // Determine if refusal appears justified under Art. 6 (safety/capacity/technical constraints)
        $refusalJustified = null;
        if ($denied === 'yes') {
            if ($reasonAllowed) {
                $refusalJustified = true;
            } elseif ($reason === 'other' || $reason === 'unspecified') {
                $refusalJustified = false; // no clear allowed ground given
            } elseif ($reason === 'none') { // denied but no reason
                $refusalJustified = false;
            }
        }

        // Compliance heuristic
        $compliance = 'unknown';
        $notes = [];
        if ($denied === 'yes') {
            if ($refusalJustified === true) {
                // If reservation was explicitly required and not made, this also supports compliance
                if ($reservationRequired === 'yes' && $reservationMade === 'no') {
                    $notes[] = 'Reservation required and not made; denial is consistent with policy.';
                }
                $compliance = 'compliant';
            } elseif ($refusalJustified === false) {
                $compliance = 'non_compliant';
                $notes[] = 'Denial reason not among allowed grounds (capacity/equipment/weight_dim).';
            }
        } else {
            // No denial: check for contradictory reservation policy signals
            if ($reservationRequired === 'yes' && $reservationMade === 'no') {
                $notes[] = 'Reservation required but was not made; boarding may be refused next time.';
            }
        }

        $routeToArt18 = ($denied === 'yes' && $refusalJustified !== true);

        // Human-facing reasoning strings
        $reasoning = [];
        $reasoning[] = 'Reservation required: ' . $reservationRequired . ' — reservation made: ' . $reservationMade;
        if ($denied === 'yes') {
            $reasoning[] = 'Denied boarding with reason: ' . $reason;
        }
        foreach ($notes as $n) { $reasoning[] = $n; }

        return [
            'reservation_required' => $reservationRequired,
            'reservation_made' => $reservationMade,
            'denied_boarding' => $denied,
            'refusal_reason_type' => $reason,
            'refusal_justified' => $refusalJustified,
            'compliance_status' => $compliance,
            'route_to_art18' => $routeToArt18,
            'reasoning' => $reasoning,
        ];
    }
}
