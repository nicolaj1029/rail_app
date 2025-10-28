<?php
declare(strict_types=1);

namespace App\Service;

final class ClaimFormSelector
{
    public const FORM_EU   = 'eu_standard_claim';
    public const FORM_NONE = 'none';

    // Example national form identifiers (can be mapped to routes/templates)
    private const FORM_SNFC_G30         = 'fr_sncf_g30';
    private const FORM_RENFE_PUNT       = 'es_renfe_punctuality';
    private const FORM_TRENI_FRECCE     = 'it_trenitalia_frecce_bonus';
    private const FORM_NS_DOMESTIC      = 'nl_ns_delay';
    private const FORM_DSB_RT_GUARANTEE = 'dk_dsb_rejsetidsgaranti';
    private const FORM_SJ_NATIONAL_LAW  = 'se_sj_law';
    private const FORM_VR_COMMUTER      = 'fi_vr_commuter';

    public function __construct(
        private ?ExemptionMatrixRepository $matrix = null,
        private ?ExemptionsRepository $exemptions = null,
        private ?NationalOverridesRepository $overrides = null,
        private ?OperatorCatalog $operatorCatalog = null,
    ) {
        $this->matrix = $this->matrix ?: new ExemptionMatrixRepository();
        $this->exemptions = $this->exemptions ?: new ExemptionsRepository();
        $this->overrides = $this->overrides ?: new NationalOverridesRepository();
        $this->operatorCatalog = $this->operatorCatalog ?: new OperatorCatalog();
    }

    /**
     * Select recommended claim form type based on country/scope exemptions and national overrides.
     * @param array{
     *   country:?string, scope:?string, operator:?string, product:?string,
     *   delayMin:int, distanceKm?:?float, isCommuter?:bool, intlBeyondEU?:bool,
     *   profile?:array{scope:string,articles:array<string,bool>,blocked?:bool}
     * } $ctx
     * @return array{form:string, reason:string, notes:array<int,string>, matchedOverride?:array<string,mixed>}
     */
    public function select(array $ctx): array
    {
        $notes = [];
        $country = (string)($ctx['country'] ?? '');
        $scope   = (string)($ctx['profile']['scope'] ?? ($ctx['scope'] ?? ''));
        $delay   = (int)($ctx['delayMin'] ?? 0);
        $op      = $ctx['operator'] ?? null;
        $prod    = $ctx['product'] ?? null;

        // 0) If profile provided, honor blocked + Art.19 flags directly
        $blocked = (bool)($ctx['profile']['blocked'] ?? false);
        $art19On = $ctx['profile']['articles']['art19'] ?? null;

        // 1) EU-flow blocked for country+scope?
        if ($blocked || $this->isScopeBlocked($country, $scope)) {
            $notes[] = "EU-flow disabled for {$country}/{$scope} (blocked).";
            $ov = $this->findApplicableOverride($country, $op, $prod, $delay, $ctx);
            if ($ov) {
                return [
                    'form' => $this->mapOverrideToForm($ov),
                    'reason' => 'Scope blocked → use national override',
                    'notes' => $notes,
                    'matchedOverride' => $ov,
                ];
            }
            return ['form' => self::FORM_NONE, 'reason' => 'Scope blocked (no national form found)', 'notes' => $notes];
        }

        // 2) Art. 19 exempt in context?
        $isArt19Exempt = ($art19On === false) || $this->isArt19ExemptInContext($country, $scope, $ctx);
        if ($isArt19Exempt) {
            $notes[] = 'Art. 19 exempt in this context → EU compensation not available.';
            $ov = $this->findApplicableOverride($country, $op, $prod, $delay, $ctx);
            if ($ov) {
                return [
                    'form' => $this->mapOverrideToForm($ov),
                    'reason' => 'Art. 19 exempt → use national override',
                    'notes' => $notes,
                    'matchedOverride' => $ov,
                ];
            }
            return ['form' => self::FORM_NONE, 'reason' => 'Art. 19 exempt (no national form found)', 'notes' => $notes];
        }

        // 3) Prefer national override if present and delay meets a tier threshold
        $ov = $this->findApplicableOverride($country, $op, $prod, $delay, $ctx);
        if ($ov) {
            $notes[] = "National/operator override is applicable at {$delay} min.";
            return [
                'form' => $this->mapOverrideToForm($ov),
                'reason' => 'National override more specific/more generous',
                'notes' => $notes,
                'matchedOverride' => $ov,
            ];
        }

        // 4) Fall back to EU standard
        $notes[] = 'EU baseline applies (25% ≥60m, 50% ≥120m).';
        return ['form' => self::FORM_EU, 'reason' => 'EU baseline', 'notes' => $notes];
    }

    private function isScopeBlocked(?string $country, ?string $scope): bool
    {
        if (!$country || !$scope) { return false; }
        $rows = $this->matrix->find(['country' => $country, 'scope' => $scope]);
        foreach ($rows as $r) {
            if (!empty($r['blocked'])) { return true; }
        }
        return false;
    }

    private function isArt19ExemptInContext(?string $country, ?string $scope, array $ctx): bool
    {
        if (!$country) { return false; }
        // Matrix-level exemptions
        $rows = $this->matrix->find(['country' => $country, 'scope' => $scope ?? null]);
        foreach ($rows as $r) {
            $ex = (array)($r['exemptions'] ?? []);
            if (in_array('Art.19', $ex, true)) { return true; }
        }
        // Repository-level exemptions
        $repoRows = $this->exemptions->find(['country' => $country, 'scope' => $scope ?? null]);
        foreach ($repoRows as $r) {
            $ex = (array)($r['articlesExempt'] ?? []);
            if (in_array('19', $ex, true) || in_array('Art.19', $ex, true)) { return true; }
        }
        // Country-specific contextual gates from snapshot
        if (strtoupper((string)$country) === 'SE' && ($scope === 'regional')) {
            $dist = $ctx['distanceKm'] ?? null;
            if ($dist !== null) { return ((float)$dist < 150.0); }
            // Allow explicit flag to force commuter/distance gating when distance is unknown
            if (!empty($ctx['se_under_150km'])) { return true; }
        }
        if (!empty($ctx['isCommuter']) && strtoupper((string)$country) === 'FI') {
            return true;
        }
        return false;
    }

    /** @return array<string,mixed>|null */
    private function findApplicableOverride(?string $country, ?string $operator, ?string $product, int $delayMin, array $ctx): ?array
    {
        $query = [
            'country' => $country, 'operator' => $operator, 'product' => $product,
        ];
        $ov = $this->overrides->findOne($query);
        if (!$ov) { return null; }
        // Scope gating on override
        if (!empty($ov['scope']) && !$this->scopeMatches((string)$ov['scope'], $ctx)) {
            return null;
        }
        // If tiers exist, require delay ≥ first matching tier
        $tiers = (array)($ov['tiers'] ?? []);
        if (!empty($tiers)) {
            $meets = false;
            foreach ($tiers as $t) {
                $min = (int)($t['minDelayMin'] ?? 0);
                if ($delayMin >= $min && $min > 0) { $meets = true; break; }
            }
            if (!$meets) { return null; }
        }
        return $ov;
    }

    private function scopeMatches(string $overrideScope, array $ctx): bool
    {
        return match ($overrideScope) {
            'commuter_exempt' => !empty($ctx['isCommuter']),
            'intl_beyond_eu'  => !empty($ctx['intlBeyondEU']) || (($ctx['profile']['scope'] ?? null) === 'intl_beyond_eu') || (($ctx['scope'] ?? null) === 'intl_beyond_eu'),
            'intl_inside_eu'  => (($ctx['profile']['scope'] ?? null) === 'intl_inside_eu') || (($ctx['scope'] ?? null) === 'intl_inside_eu'),
            'long_domestic'   => (($ctx['profile']['scope'] ?? null) === 'long_domestic') || (($ctx['scope'] ?? null) === 'long_domestic'),
            'regional'        => (($ctx['profile']['scope'] ?? null) === 'regional') || (($ctx['scope'] ?? null) === 'regional'),
            default           => true,
        };
    }

    private function mapOverrideToForm(array $override): string
    {
        $country = strtolower((string)($override['country'] ?? ''));
        $operator = strtolower((string)($override['operator'] ?? ''));
        $product = strtolower((string)($override['product'] ?? ''));
        $scope = strtolower((string)($override['scope'] ?? ''));

        return match (true) {
            str_contains($country, 'france') && str_contains($product, 'g30')                              => self::FORM_SNFC_G30,
            str_contains($country, 'spain')  && (str_contains($product, 'ave') || str_contains($product, 'avlo')) => self::FORM_RENFE_PUNT,
            str_contains($country, 'italy')  && str_contains($product, 'frecce')                           => self::FORM_TRENI_FRECCE,
            str_contains($country, 'netherlands')                                                          => self::FORM_NS_DOMESTIC,
            str_contains($country, 'denmark')                                                              => self::FORM_DSB_RT_GUARANTEE,
            str_contains($country, 'sweden')                                                               => self::FORM_SJ_NATIONAL_LAW,
            str_contains($country, 'finland') && str_contains($scope, 'commuter')                          => self::FORM_VR_COMMUTER,
            default                                                                                        => self::FORM_EU,
        };
    }
}
