<?php
declare(strict_types=1);

namespace App\Service;

/**
 * NationalPolicy - provides country/scope-specific compensation policy hints for TRIN 6 UI.
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
     * @return array<string,mixed>|null
     */
    public function decide(array $ctx): ?array
    {
        $country = strtoupper((string)($ctx['country'] ?? ''));
        $scope = (string)($ctx['scope'] ?? '');
        if ($country === '' || $scope === '') { return null; }

        // Only apply for indenrigsrejser (inkl. regional); internationalt behold EU-baseline
        $isDomestic = in_array($scope, ['long_domestic','domestic','regional'], true);
        if (!$isDomestic) { return null; }

        // Curated map baseret på national_overrides (lempeligere bands for udvalgte lande)
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
                'thresholds' => ['25' => 30, '50' => 120],
                'notes' => 'National ordning; thresholds tilpasset national_overrides (25% ≥30 min).',
            ],
            'NL' => [
                'name' => 'NS Geld tilbage ved forsinkelse',
                'id' => 'nl_gtbv',
                'thresholds' => ['25' => 30, '50' => 60], // proxy: 50% ≥30 min, 100% ≥60 min
                'notes' => 'NS har faste beløb (50% ≥30 min, 100% ≥60); proxier til 25/50 bands.',
            ],
            'ES' => [
                'name' => 'Renfe Compensación',
                'id' => 'es_compensacion',
                'thresholds' => ['25' => 60, '50' => 90], // proxy til togtype-afhængige bands
                'notes' => 'Renfe har togtype-afhængige tærskler; proxier til 25/50 = 60/90 min.',
            ],
            'DK' => [
                'name' => 'DSB national ordning',
                'id' => 'dk_dsb',
                'thresholds' => ['25' => 30, '50' => 60],
                'notes' => 'DSB rejsetidsgaranti: 25% ≥30 min, 50% ≥60 min.',
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
