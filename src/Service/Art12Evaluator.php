<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates Article 12 (1)-(7) related hooks for a given journey/purchase context.
 * Heuristics are conservative; unknowns are flagged so UI can ask the user.
 */
class Art12Evaluator
{
    /**
     * @param array $journey Expected keys: segments[], bookingRef?, seller_type?, source?
     * @param array $meta Optional keys: through_ticket_disclosure?, separate_contract_notice?, contact_info_provided?, responsibility_explained?
     * @return array{
     *   hooks: array<string,mixed>,
     *   missing: string[],
     *   art12_applies: bool|null,
     *   reasoning: string[]
     * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        $segments = (array)($journey['segments'] ?? []);
        $bookingRef = $journey['bookingRef'] ?? null;
        $pnrs = [];
        $carriers = [];
        foreach ($segments as $s) {
            $pnr = $s['pnr'] ?? null;
            if (is_string($pnr) && $pnr !== '') { $pnrs[] = $pnr; }
            $carrier = $s['carrier'] ?? ($s['operator'] ?? null);
            if (is_string($carrier) && $carrier !== '') { $carriers[] = $carrier; }
        }
        $uniquePnrs = array_values(array_unique($pnrs));
        $uniqueCarriers = array_values(array_unique($carriers));

        $hooks = [];

        // 1) Grundtype
        $hooks['through_ticket_disclosure'] = $meta['through_ticket_disclosure'] ?? 'unknown';
        $hooks['single_txn_operator'] = $meta['single_txn_operator'] ?? 'unknown';
        $hooks['single_txn_retailer'] = $meta['single_txn_retailer'] ?? 'unknown';
        $hooks['separate_contract_notice'] = $meta['separate_contract_notice'] ?? 'unknown';
        $hooks['shared_pnr_scope'] = $this->triFromBool($this->hasSharedScope($bookingRef, $uniquePnrs));

        // 2) Hvem solgte rejsen?
        $sellerType = $journey['seller_type'] ?? null; // 'operator'|'agency'|null
        $hooks['seller_type_operator'] = $this->triFromBool($sellerType === 'operator');
        $hooks['seller_type_agency'] = $this->triFromBool($sellerType === 'agency');
        $hooks['multi_operator_trip'] = $this->triFromBool(count($uniqueCarriers) > 1);

        // 3) Forbindelser/tidsforhold
        $hooks['mct_realistic'] = $meta['mct_realistic'] ?? 'unknown';
        $hooks['one_contract_schedule'] = $meta['one_contract_schedule'] ?? 'unknown';

        // 4) Kommunikations- og ansvar
        $hooks['contact_info_provided'] = $meta['contact_info_provided'] ?? 'unknown';
        $hooks['responsibility_explained'] = $meta['responsibility_explained'] ?? 'unknown';
        $hooks['single_booking_reference'] = $this->triFromBool(is_string($bookingRef) && $bookingRef !== '');

        // 5) Undtagelser og sammenhæng – integrate ExemptionProfile (Art. 2)
        $profile = (new ExemptionProfileBuilder())->build($journey);
        $hooks['exemption_override_12'] = $this->triFromBool(!$profile['articles']['art12']);

        // Determine art12 applies recommendation
        $reasoning = [];
        $applies = null; // null = unknown

        if ($hooks['exemption_override_12'] === 'yes') {
            $applies = false;
            $reasoning[] = 'Art. 12 undtaget efter national/EU-fritagelser.';
        } else {
            // If explicit separate-contract notice was given on durable medium, do not apply Art. 12 extended protection
            if ($hooks['separate_contract_notice'] === 'Ja') {
                $applies = false;
                $reasoning[] = 'Særskilte kontrakter udtrykkeligt angivet før køb (Art. 12(5)).';
            } else {
                // If explicit disclosure says through ticket OR shared scope strongly indicates it
                if ($hooks['through_ticket_disclosure'] === 'Gennemgående' || $hooks['shared_pnr_scope'] === 'yes') {
                    $applies = true;
                    $reasoning[] = 'Gennemgående billet indikation (disclosure eller fælles PNR/bookingRef).';
                }
                // If explicit disclosure says separate and no shared scope
                if ($hooks['through_ticket_disclosure'] === 'Særskilte' && $hooks['shared_pnr_scope'] === 'no') {
                    $applies = false;
                    $reasoning[] = 'Særskilte kontrakter uden fælles PNR/bookingRef.';
                }
                // Agency sale can imply Art. 12(5) liability if not clearly informed
                if ($hooks['seller_type_agency'] === 'yes' && $hooks['separate_contract_notice'] !== 'Ja') {
                    $applies = $applies ?? true; // implicit through responsibility
                    $reasoning[] = 'Rejsebureau-salg uden tydelig særskilt-kontrakt info (Art. 12(5)).';
                }
            }
        }

        $missing = $this->missingHooks($hooks);
        return [
            'hooks' => $hooks,
            'missing' => $missing,
            'art12_applies' => $applies,
            'reasoning' => $reasoning,
        ];
    }

    private function hasSharedScope($bookingRef, array $uniquePnrs): bool
    {
        if (is_string($bookingRef) && $bookingRef !== '') { return true; }
        if (count($uniquePnrs) === 1 && $uniquePnrs[0] !== '') { return true; }
        return false;
    }

    private function triFromBool(?bool $v): string
    {
        if ($v === true) return 'yes';
        if ($v === false) return 'no';
        return 'unknown';
    }

    /** @param array<string,mixed> $hooks */
    private function missingHooks(array $hooks): array
    {
        $need = [
            'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
            'mct_realistic','one_contract_schedule','contact_info_provided','responsibility_explained'
        ];
        $missing = [];
        foreach ($need as $k) {
            if (!isset($hooks[$k]) || $hooks[$k] === 'unknown') { $missing[] = $k; }
        }
        return $missing;
    }
}
