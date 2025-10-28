<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Art. 12 evaluator – dækker spørgsmål 1–13 (stk. 1–7; Bilag II, del I, pkt. 9)
 *
 * INPUT:
 *  $journey: [
 *    'segments' => [
 *      ['pnr' => 'ABCD12', 'carrier' => 'DSB' (eller 'operator')],
 *      ...
 *    ],
 *    'bookingRef' => 'XYZ123'|null,
 *    'seller_type' => 'operator'|'agency'|null,   // AUTO for spm. 6/7
 *    // Valgfrit: brugt hvis du har MCT-data osv.
 *    'missed_connection' => true|false|null,
 *  ]
 *
 *  $meta (kan komme fra formular/OCR/auto): [
 *    // 1–5
 *    'through_ticket_disclosure' => 'Gennemgående'|'Særskilte'|'Ved ikke'|'unknown',
 *    'single_txn_operator'       => 'Ja'|'Nej'|'Ved ikke'|'unknown',
 *    'single_txn_retailer'       => 'Ja'|'Nej'|'Ved ikke'|'unknown',
 *    'separate_contract_notice'  => 'Ja'|'Nej'|'Ved ikke'|'unknown',
 *    'shared_pnr_scope'          => 'Ja'|'Nej'|'Ved ikke'|'unknown', // hvis du sætter manuelt
 *
 *    // 6–13
 *    'seller_type_operator'      => 'Ja'|'Nej'|'Ved ikke'|'unknown', // kan afledes af seller_type
 *    'seller_type_agency'        => 'Ja'|'Nej'|'Ved ikke'|'unknown', // kan afledes af seller_type
 *    'multi_operator_trip'       => 'Ja'|'Nej'|'Ved ikke'|'unknown', // AUTO hvis du vil
 *    'connection_time_realistic' => 'Ja'|'Nej'|'Ved ikke'|'unknown', // spm. 9
 *    'one_contract_schedule'     => 'Ja'|'Nej'|'Ved ikke'|'unknown', // spm.10
 *    'contact_info_provided'     => 'Ja'|'Nej'|'Ved ikke'|'unknown', // spm.11
 *    'responsibility_explained'  => 'Ja'|'Nej'|'Ved ikke'|'unknown', // spm.12
 *    'single_booking_reference'  => 'Ja'|'Nej'|'Ved ikke'|'unknown', // spm.13
 *  ]
 *
 * OUTPUT:
 *  [
 *    'hooks' => [ key => value ... ],        // alle 13 hooks i tri-state (yes/no/unknown)
 *    'missing' => string[],                   // keys blandt 1–13 der er 'unknown'/'Ved ikke'
 *    'art12_applies' => bool|null,            // anbefaling: gennemgående-billet-beskyttelse gælder?
 *    'liable_party' => 'operator'|'agency'|'unknown',  // hvem bærer ansvaret hvis art12_applies=true
 *    'reasoning' => string[],                 // juridisk/heuristisk forklaring
 *    'notes' => string[],                     // supplerende notater (fx oplysningsmangler, art. 30)
 *  ]
 */
final class Art12Evaluator
{
    public function evaluate(array $journey, ?array $meta = null): array
    {
        $meta = (array)($meta ?? []);
        $segments   = (array)($journey['segments'] ?? []);
        $bookingRef = $journey['bookingRef'] ?? null;
        $sellerType = $journey['seller_type'] ?? null; // 'operator'|'agency'|null
        $missedConn = $journey['missed_connection'] ?? null;

        // --- AUTO-afledninger ---
        // PNR/booking: fælles scope?
        $pnrs = [];
        $carriers = [];
        foreach ($segments as $s) {
            $p = $s['pnr'] ?? null;
            if (is_string($p) && $p !== '') $pnrs[] = $p;
            $c = $s['carrier'] ?? ($s['operator'] ?? null);
            if (is_string($c) && $c !== '') $carriers[] = $c;
        }
        $uniquePnrs = array_values(array_unique($pnrs));
        $sharedByAuto = (
            (is_string($bookingRef) && $bookingRef !== '') ||
            (count($uniquePnrs) === 1 && $uniquePnrs[0] !== '')
        );

        // multi-operator?
        $multiOpsAuto = (count(array_unique($carriers)) > 1);

        // Sælgerrolle AUTO
        $sellerOpAuto = ($sellerType === 'operator');
        $sellerAgAuto = ($sellerType === 'agency');

        // --- Normaliser tri-state helper ---
        $tri = fn($v) => $this->toTri($v);
        $yn  = fn($bool) => $bool === true ? 'yes' : ($bool === false ? 'no' : 'unknown');

        // --- Saml alle 13 hooks (tri-state 'yes'/'no'/'unknown') ---
        $hooks = [
            // 1–5
            'through_ticket_disclosure' => $this->normChoice($meta['through_ticket_disclosure'] ?? 'unknown'), // 'Gennemgående'|'Særskilte'|'Ved ikke'|'unknown'
            'single_txn_operator'       => $this->normYesNo($meta['single_txn_operator'] ?? 'unknown'),
            'single_txn_retailer'       => $this->normYesNo($meta['single_txn_retailer'] ?? 'unknown'),
            'separate_contract_notice'  => $this->normYesNo($meta['separate_contract_notice'] ?? 'unknown'),
            'shared_pnr_scope'          => $this->normYesNo(
                                              $meta['shared_pnr_scope'] ?? ($sharedByAuto ? 'Ja' : (count($pnrs) ? 'Nej' : 'unknown'))
                                          ),

            // 6–8
            'seller_type_operator'      => $this->normYesNo($meta['seller_type_operator'] ?? ($sellerOpAuto ? 'Ja' : ($sellerType !== null ? 'Nej' : 'unknown'))),
            'seller_type_agency'        => $this->normYesNo($meta['seller_type_agency']   ?? ($sellerAgAuto ? 'Ja' : ($sellerType !== null ? 'Nej' : 'unknown'))),
            'multi_operator_trip'       => $this->normYesNo($meta['multi_operator_trip']  ?? ($multiOpsAuto ? 'Ja' : (count($carriers) ? 'Nej' : 'unknown'))),

            // 9–13
            'connection_time_realistic' => $this->normYesNo($meta['connection_time_realistic'] ?? 'unknown'),
            'one_contract_schedule'     => $this->normYesNo($meta['one_contract_schedule']     ?? 'unknown'),
            'contact_info_provided'     => $this->normYesNo($meta['contact_info_provided']     ?? 'unknown'),
            'responsibility_explained'  => $this->normYesNo($meta['responsibility_explained']  ?? 'unknown'),
            'single_booking_reference'  => $this->normYesNo($meta['single_booking_reference']  ?? ($sharedByAuto ? 'Ja' : (count($pnrs) ? 'Nej' : 'unknown'))),
        ];

        // --- Exemption profile check ---
        // If the global exemption profile disables Art.12 for this journey, honour that override.
        // This mirrors how other services consult ExemptionProfileBuilder and matches test expectations.
        $exProfile = (new ExemptionProfileBuilder())->build($journey);
        if (isset($exProfile['articles']['art12']) && $exProfile['articles']['art12'] === false) {
            $notes = array_merge($notes, $exProfile['notes'] ?? []);
            $reason[] = 'Art.12 disabled by exemption profile (ExemptionProfileBuilder).';
            $missing = $this->missing1to13($hooks);
            return [
                'hooks' => $hooks,
                'missing' => $missing,
                'art12_applies' => false,
                'liable_party' => 'unknown',
                'reasoning' => $reason,
                'notes' => $notes,
            ];
        }

        // --- Afgørelseslogik ---
        $reason = [];
        $notes  = [];
        $applies = null;              // bool|null
        $liable = 'unknown';          // 'operator'|'agency'|'unknown'

        // 4) Klart angivet "særskilte kontrakter" (før køb) → udvidet ansvar i 12(3)-(4) fraviges.
        if ($hooks['separate_contract_notice'] === 'yes') {
            $applies = false;
            $reason[] = 'Særskilte kontrakter var udtrykkeligt angivet før køb (Art. 12(5)).';
        }

        // 2–3) Én transaktion → gennemgående (operator: 12(3); agency: 12(4))
        if ($hooks['single_txn_operator'] === 'yes') {
            $applies = true;
            $liable  = 'operator';
            $reason[] = 'Billetter købt i én transaktion hos operatør (Art. 12(3)).';
        }
        if ($hooks['single_txn_retailer'] === 'yes') {
            if ($hooks['separate_contract_notice'] !== 'yes') {
                $applies = true;
                $liable  = 'agency';
                $reason[] = 'Billetter købt samlet hos billetudsteder/rejsebureau uden klar særskilt-notits (Art. 12(4) + 12(5)).';
            } else {
                $notes[] = 'Samlet køb hos forhandler, men særskilte kontrakter oplyst → 12(4) fraviges via 12(5).';
            }
        }

        // 1) Disclosure “Gennemgående” → vægt for anvendelse
        if ($this->isDisclosureThrough($hooks['through_ticket_disclosure'])) {
            $applies = true;
            $reason[] = 'Oplyst som gennemgående billet (Art. 12(2), Bilag II pkt. 9).';
        }

        // 5 & 13) Fælles PNR/bookingRef uden særskilt-notits → implicit gennemgående (12(5))
        $sharedScope = ($hooks['shared_pnr_scope'] === 'yes' || $hooks['single_booking_reference'] === 'yes');
        if ($sharedScope && $hooks['separate_contract_notice'] !== 'yes') {
            $applies = true;
            $reason[] = 'Fælles booking/PNR uden tydelig særskilt-notits → implicit gennemgående (Art. 12(5)).';
        }

        // 6–7) Sælgerrolle – hvem er ansvarlig part når art.12 gælder
        if ($applies === true) {
            if ($hooks['seller_type_operator'] === 'yes') {
                $liable = 'operator';
            } elseif ($hooks['seller_type_agency'] === 'yes') {
                $liable = 'agency';
            }
        }

        // 8) Enkelt operatør for hele rejsen → pligt til at tilbyde gennemgående (12(1))
        if ($hooks['multi_operator_trip'] === 'no') {
            // Hvis det samtidig fremstår som separate (ingen disclosure som gennemgående, evt. separate_contract_notice != 'yes'),
            // kan det indikere, at 12(1)-pligten ikke blev opfyldt. Fortolk forbrugerbeskyttende:
            if ($applies !== true) {
                // Hvis købet tydligt var fragmenteret OG ingen klar særskilt-notits → favorér anvendelse
                if ($hooks['separate_contract_notice'] !== 'yes') {
                    $applies = true;
                    $reason[] = 'Én operatør for hele rejsen → burde have tilbudt gennemgående billet (Art. 12(1)); manglende disclosure → behandles som gennemgående.';
                    if ($liable === 'unknown') $liable = 'operator';
                } else {
                    $notes[] = 'Én operatør men særskilt-notits givet → 12(1)-pligt problematisk, men 12(5) kan redde fraskrivelsen; vurder konkret.';
                }
            } else {
                $notes[] = 'Én operatør – gennemgående anvendes (Art. 12(1) understøtter).';
            }
        }

        // 9) Urealistiske skiftetider → indikerer planlægningssvigt (betragtning 26)
        if ($hooks['connection_time_realistic'] === 'no') {
            $notes[] = 'Skiftetider vurderes urealistiske (betragtning 26) – taler for samlet ansvar ved missed connection.';
            // Hvis der var samlet køb / fælles PNR / disclosure som gennemgående, styrker det anvendelse:
            if ($applies !== true && ($sharedScope || $this->isDisclosureThrough($hooks['through_ticket_disclosure']))) {
                $applies = true;
                $reason[] = 'Urealistiske skift i en fremstillet samlet rejseplan → behandles som gennemgående.';
            }
        }

        // 10) Fremstod det som én samlet rejseplan? → indikator for gennemgående
        if ($hooks['one_contract_schedule'] === 'yes' && $hooks['separate_contract_notice'] !== 'yes') {
            if ($applies !== true) {
                $applies = true;
                $reason[] = 'Købet fremstod som én sammenhængende rejseplan uden særskilt-notits (Art. 12(5)).';
            } else {
                $notes[] = 'Sammenhængende rejseplan bekræfter gennemgående karakter.';
            }
        }

        // 11–12) Oplysningspligt om kontaktpunkt/ansvar (Art. 12(6)–(7), Art. 30)
        if ($hooks['contact_info_provided'] === 'no') {
            $notes[] = 'Manglende kontaktoplysning ved forstyrrelser (Art. 30, Art. 20(4)).';
        }
        if ($hooks['responsibility_explained'] === 'no') {
            $notes[] = 'Ansvarsfordeling ikke forklaret (Art. 12(6)–(7)) – tvivl fortolkes til passagerens fordel.';
            // Hvis sælger påstår "separate" men intet ansvar forklaret → hælder mod gennemgående beskyttelse
            if ($applies !== true && $hooks['through_ticket_disclosure'] !== 'Gennemgående') {
                $applies = true;
                $reason[] = 'Manglende ansvarsforklaring ved “separate” → behandles som gennemgående (Art. 12(5)–(7)).';
            }
        }

        // 13) ’single_booking_reference’ er i praksis samme indikator som spm. 5 – tjek uoverensstemmelser
        if ($hooks['shared_pnr_scope'] !== $hooks['single_booking_reference']) {
            $notes[] = 'Uoverensstemmelse mellem PNR-scope (spm. 5) og bookingreference (spm. 13) – datavalidering anbefales.';
        }

        // Konflikt-tilfælde: disclosure “Gennemgående” MEN separate_contract_notice “Ja”
        if ($this->isDisclosureThrough($hooks['through_ticket_disclosure']) && $hooks['separate_contract_notice'] === 'yes') {
            // Forbrugerbeskyttende tilgang: behandle som gennemgående, og flag konflikt.
            $applies = true;
            $reason[] = 'Konflikt: oplyst gennemgående, men særskilt-notits givet. Fortolkes til passagerens fordel.';
            if ($liable === 'unknown' && $hooks['seller_type_operator'] === 'yes') $liable = 'operator';
            if ($liable === 'unknown' && $hooks['seller_type_agency']   === 'yes') $liable = 'agency';
        }

        // Hvis vi stadig ikke ved det, sæt null og bed UI om at indsamle de afgørende svar
        $missing = $this->missing1to13($hooks);

        return [
            'hooks'          => $hooks,
            'missing'        => $missing,
            'art12_applies'  => $applies,
            'liable_party'   => $liable,
            'reasoning'      => $reason,
            'notes'          => $notes,
        ];
    }

    // --- helpers ---
    private function toTri($v): string
    {
        if (is_bool($v)) return $v ? 'yes' : 'no';
        $s = is_string($v) ? trim(mb_strtolower($v)) : '';
        return in_array($s, ['yes','no','unknown'], true) ? $s : 'unknown';
    }

    private function normYesNo(string $raw): string
    {
        $s = trim(mb_strtolower($raw));
        return match ($s) {
            'ja','yes' => 'yes',
            'nej','no' => 'no',
            'ved ikke','unknown','' => 'unknown',
            default => 'unknown',
        };
    }

    private function normChoice(string $raw): string
    {
        // For spm. 1: 'Gennemgående' / 'Særskilte' / 'Ved ikke' → gem som input, men til logikken bruger vi helper:
        return $raw === '' ? 'unknown' : $raw;
    }

    private function isDisclosureThrough(string $choice): bool
    {
        return mb_strtolower($choice) === 'gennemgende' || mb_strtolower($choice) === 'gennemgående' || mb_strtolower($choice) === 'gennemgaende' || mb_strtolower($choice) === 'gennemgaende';
    }

    /** @param array<string,string> $hooks */
    private function missing1to13(array $hooks): array
    {
        $need = [
            // 1–5
            'through_ticket_disclosure',
            'single_txn_operator',
            'single_txn_retailer',
            'separate_contract_notice',
            'shared_pnr_scope',
            // 6–13
            'seller_type_operator',
            'seller_type_agency',
            'multi_operator_trip',
            'connection_time_realistic',
            'one_contract_schedule',
            'contact_info_provided',
            'responsibility_explained',
            'single_booking_reference',
        ];
        $out = [];
        foreach ($need as $k) {
            if (!isset($hooks[$k]) || $hooks[$k] === 'unknown') {
                $out[] = $k;
            }
        }
        return $out;
    }
}
