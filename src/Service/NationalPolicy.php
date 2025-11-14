<?php
declare(strict_types=1);

namespace App\Service;

/**
 * NationalPolicy — provides country/scope-specific compensation policy hints for TRIN 6 UI.
 *
 * Scope: Read-only helper to surface national schemes with more lenient bands than EU Art. 19 baseline.
 * Note: Amount calculation still relies on ClaimCalculator; this service only informs UI defaults/notes.
 */
class NationalPolicy
{
    /**
     * Decide applicable national policy (if any) for the current journey context.
     *
     * @param array{country?:string, scope?:string, operator?:string, product?:string} $ctx
     * @return array<string,mixed>|null Example:
     *   [
     *     'country' => 'FR',
     *     'name' => 'SNCF G30',
     *     'id' => 'fr_g30',
     *     'thresholds' => ['25' => 30, '50' => 120],
     *     'notes' => 'Domestic TGV/Intercités, voucher preferences may apply.'
     *   ]
     */
    public function decide(array $ctx): ?array
    {
        $country = strtoupper((string)($ctx['country'] ?? ''));
        $scope = (string)($ctx['scope'] ?? '');
        if ($country === '' || $scope === '') { return null; }

        // Only apply for domestic journeys; international routes should keep EU baseline
        $isDomestic = ($scope === 'long_domestic' || $scope === 'domestic');
        if (!$isDomestic) { return null; }

        // Minimal curated map — safe defaults: only FR has a clearly earlier threshold (G30 ≥30 min → 25%).
        // Others point to EU baseline but still prefer national forms via FormResolver.
        $map = [
            'FR' => [
                'name' => 'SNCF G30',
                'id' => 'fr_g30',
                'thresholds' => ['25' => 30, '50' => 120],
                'notes' => 'G30: kompensation fra 30 min for udvalgte produkter; lokale vilkår kan indebære vouchers.',
            ],
            'IT' => [
                'name' => 'Trenitalia Indennizzo',
                'id' => 'it_indennizzo',
                'thresholds' => ['25' => 60, '50' => 120], // conservative (align EU) until refined
                'notes' => 'National ordning; tærskler varierer pr. togtype. EU-bands som standard.',
            ],
            'NL' => [
                'name' => 'NS Geld terug bij vertraging',
                'id' => 'nl_gtbv',
                'thresholds' => ['25' => 60, '50' => 120], // NS uses fixed amounts/30-min windows; keep EU bands for now
                'notes' => 'NS har faste beløb fra ~30 min; vises som EU-bands indtil speciallogik tilføjes.',
            ],
            'ES' => [
                'name' => 'Renfe Compensación',
                'id' => 'es_compensacion',
                'thresholds' => ['25' => 60, '50' => 120], // conservative default
                'notes' => 'Renfe har togtype-afhængige tærskler; EU-bands indtil videre.',
            ],
            'DK' => [
                'name' => 'DSB national ordning',
                'id' => 'dk_dsb',
                'thresholds' => ['25' => 60, '50' => 120],
                'notes' => 'National kanal foretrækkes; EU-bands som baseline.',
            ],
        ];

        $entry = $map[$country] ?? null;
        if (!$entry) { return null; }

        return [
            'country' => $country,
            'name' => (string)$entry['name'],
            'id' => (string)$entry['id'],
            'thresholds' => (array)$entry['thresholds'],
            'notes' => (string)$entry['notes'],
        ];
    }
}

?>
