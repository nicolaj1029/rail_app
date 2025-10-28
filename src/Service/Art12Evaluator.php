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
        $reason = [];
        $notes  = [];

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
            // New logic: treat 'through_ticket_disclosure' as a Yes/No about whether disclosure was clear before purchase
            'through_ticket_disclosure' => $this->normYesNo($meta['through_ticket_disclosure'] ?? 'unknown'),
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
            // Convenience: single_operator_scope (inverse of multi_operator_trip)
            'single_operator_scope'     => $this->normYesNo($meta['single_operator_scope'] ?? ($multiOpsAuto ? 'Nej' : (count($carriers) ? 'Ja' : 'unknown'))),

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
            // Build missing lists (UI vs AUTO) for compatibility in panels
            [$missingUi, $missingAuto] = $this->splitMissing($hooks);
            $missing = array_values(array_unique(array_merge($missingUi, $missingAuto)));
            return [
                'hooks' => $hooks,
                'missing' => $missing,
                'missing_ui' => $missingUi,
                'missing_auto' => $missingAuto,
                'art12_applies' => false,
                'liable_party' => 'unknown',
                'reasoning' => $reason,
                'notes' => $notes,
            ];
        }

        // --- Quick classification (using only already-gathered minimal signals) ---
        $quick = $this->quickClassify(
            $segments,
            is_string($bookingRef) ? $bookingRef : null,
            is_string($sellerType) ? $sellerType : null,
            // pass current preliminary hooks for notice/one schedule
            [
                'separate_contract_notice' => $this->normYesNo($meta['separate_contract_notice'] ?? 'unknown'),
                'one_contract_schedule'    => $this->normYesNo($meta['one_contract_schedule'] ?? 'unknown'),
            ]
        );

        // --- Afgørelseslogik ---
        $applies = null;              // bool|null
        $liable = 'unknown';          // 'operator'|'agency'|'unknown'

        // Derivér single_txn (foreløbig) fra PNR/booking/single_txn flags
        $singleTxn = null; // bool|null
        if (count($uniquePnrs) > 1 && $hooks['single_txn_operator'] === 'no' && $hooks['single_txn_retailer'] === 'no') {
            $singleTxn = false;
        } elseif (
            $hooks['shared_pnr_scope'] === 'yes' ||
            $hooks['single_booking_reference'] === 'yes' ||
            $hooks['single_txn_operator'] === 'yes' ||
            $hooks['single_txn_retailer'] === 'yes'
        ) {
            $singleTxn = true;
        }

        // 1) TRIN 1–2: Hvis vi ved det er multi-transaction (single_txn === false) → separate og stop her
        if ($singleTxn === false) {
            $applies = false;
            $reason[] = 'Ikke samme transaktion → særskilte kontrakter.';
        }

        // 2) Default presumption (stk. 3/4) – foreløbig antagelse, først endelig efter stk. 5
        if ($applies === null && $singleTxn === true) {
            $applies = true;
            if ($hooks['seller_type_operator'] === 'yes') { $liable = 'operator'; $reason[] = 'Foreløbig: 12(3) ved operatør.'; }
            elseif ($hooks['seller_type_agency'] === 'yes') { $liable = 'agency'; $reason[] = 'Foreløbig: 12(4) ved billetudsteder/rejsebureau.'; }
            else { $reason[] = 'Foreløbig: 12(3)/(4) – sælger ukendt.'; }
        }

        // 1) Disclosure before purchase (new semantics): if disclosure was NOT clear (no), weigh towards "through" when other signals support it
        if ($hooks['through_ticket_disclosure'] === 'no') {
            $notes[] = 'Manglende tydelig oplysning før køb (Art. 12(2)).';
        } elseif ($hooks['through_ticket_disclosure'] === 'yes') {
            // If later marked as separate AND clearly informed, treat as separate in TRIN 5 block below.
            $notes[] = 'Tydelig oplysning før køb registreret (Art. 12(2)).';
        }

        // 5 & 13) Fælles PNR/bookingRef uden særskilt-notits → styrker presumption (men den endelige afgørelse sker efter stk. 5)
        $sharedScope = ($hooks['shared_pnr_scope'] === 'yes' || $hooks['single_booking_reference'] === 'yes');
        if ($applies === null && $sharedScope && $hooks['separate_contract_notice'] !== 'yes') {
            $applies = true;
            $reason[] = 'Fælles booking/PNR uden særskilt-notits → foreløbig gennemgående (Art. 12(5)).';
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
                // Når der kun er én operatør og art.12 allerede gælder, tilskriv ansvar
                if ($liable === 'unknown') { $liable = 'operator'; }
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

        // TRIN 2 (nye regler): allerede håndteret via $singleTxn === false ovenfor

        // TRIN 5 (art.12(5) undtagelsen) – endelig afgørelse:
        // Kun hvis særskilt-notits VAR givet OG disclosure VAR givet før køb, fraviges presumptionen
        if ($hooks['separate_contract_notice'] === 'yes' && $hooks['through_ticket_disclosure'] === 'yes') {
            $applies = false;
            $reason[] = 'Undtagelse i Art. 12(5): Særskilt-notits + tydelig oplysning før køb → ikke gennemgående.';
        } elseif ($applies === null) {
            // Ingen automatisk "Gælder"-default, medmindre vi allerede har en stærk indikation om samme transaktion
            // (dækket ovenfor ved $singleTxn === true). Hvis vi stadig er null her, lader vi det forblive ukendt,
            // så UI kan spørge den minimale undtagelses-duo (TRIN 4/5) i stedet for at vise "Gælder".
        }

        // Hvis beslutningen stadig er uklar, brug quick classification til at fastsætte default
        if ($applies === null && is_array($quick)) {
            switch ($quick['classification']) {
                case 'THROUGH_12_3':
                case 'THROUGH_12_4':
                case 'THROUGH_DEFAULT':
                    $applies = true;
                    if ($liable === 'unknown' && !empty($quick['liable_party'])) {
                        $liable = $quick['liable_party'];
                    }
                    $reason[] = 'Quick Art.12: ' . $quick['classification'];
                    break;
                case 'SEPARATE_12_5':
                    $applies = false;
                    $reason[] = 'Quick Art.12: SEPARATE_12_5';
                    break;
                case 'OBLIGATION_12_1':
                    // Marker pligtspor i noter; selve anvendelsen kan afhænge af øvrige forhold
                    $notes[] = 'Quick Art.12: OBLIGATION_12_1 (én operatør med skift; vurder disclosure/PNR).';
                    if ($liable === 'unknown' && !empty($quick['liable_party'])) {
                        $liable = $quick['liable_party'];
                    }
                    break;
                case 'NA':
                default:
                    // ingen skift / ikke relevant
                    break;
            }
        }

    // Hvis vi stadig ikke ved det, sæt null og bed UI om at indsamle KUN de afgørende svar
    // Minimal missing: kun det, der er nødvendigt for at afgøre "gennemgående" vs. "særskilte kontrakter"
    $missingUi = $this->computeMinimalMissing($hooks, $applies);
    $missingAuto = []; // skjul AUTO-mangler i normalt flow; vis kun i debug-paneler
    $missing = $missingUi;

        return [
            'hooks'          => $hooks,
            'missing'        => $missing,
            'missing_ui'     => $missingUi,
            'missing_auto'   => $missingAuto,
            'art12_applies'  => $applies,
            'liable_party'   => $liable,
            'reasoning'      => $reason,
            'classification' => $quick['classification'] ?? null,
            'basis'          => $quick['basis'] ?? [],
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

    /**
     * Minimal mangler til TRIN 6 (kun logisk afgørelse: gennemgående vs særskilte).
     * Vi spørger kun om:
     * - separate_contract_notice (altid, hvis ukendt)
     * - through_ticket_disclosure (kun hvis separate_contract_notice === 'yes' og disclosure er ukendt)
     * - single_txn_operator / single_txn_retailer (kun hvis afgørelsen stadig er uklar og PNR-scope ikke hjælper)
     * @param array<string,string> $hooks
     * @param bool|null $applies
     * @return string[]
     */
    private function computeMinimalMissing(array $hooks, ?bool $applies): array
    {
        $out = [];
        $scn = $hooks['separate_contract_notice'] ?? 'unknown';
        $ttd = $hooks['through_ticket_disclosure'] ?? 'unknown';
        $sbo = $hooks['single_booking_reference'] ?? 'unknown';
        $spr = $hooks['shared_pnr_scope'] ?? 'unknown';
        $sto = $hooks['single_txn_operator'] ?? 'unknown';
        $str = $hooks['single_txn_retailer'] ?? 'unknown';
        $selOp = $hooks['seller_type_operator'] ?? 'unknown';
        $selAg = $hooks['seller_type_agency'] ?? 'unknown';

        $pnrHelpful = ($sbo === 'yes' || $spr === 'yes');

        // Operatør-spor: Hvis PNR/booking hjælper (fælles scope), stiller vi ikke undtagelses-parret.
        // Hvis PNR/booking er ukendt/ikke hjælpsomt, skal begge spørgsmål stilles (TRIN 4 → TRIN 5).
        if ($selOp === 'yes') {
            if ($pnrHelpful) { return []; }
            if ($ttd === 'unknown') { $out[] = 'through_ticket_disclosure'; }
            if ($scn === 'unknown') { $out[] = 'separate_contract_notice'; }
            if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $out[] = 'through_ticket_disclosure'; }
            // Stable order later
        }

        // New rule (per spec): Never STOP as "through" before checking the exception (Art. 12(5)).
        // If single-transaction is presumed/confirmed (either via PNR/shared scope or explicit single_txn flags),
        // we MUST collect the exception pair: separate_contract_notice and, if 'yes', through_ticket_disclosure.
        $singleTxnLikely = $pnrHelpful || $sto === 'yes' || $str === 'yes';

        if ($singleTxnLikely) {
            if ($scn === 'unknown') { $out[] = 'separate_contract_notice'; }
            if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $out[] = 'through_ticket_disclosure'; }
            // Note: even if $applies === true from default presumption, we still surface the exception questions.
        }

        // Branch by seller role to avoid asking irrelevant questions
        if ($selOp === 'yes') {
            // Operator seller path:
            // If single-transaction not yet established, keep it simple: ask only TRIN 4/5 pair to allow exception path.
            if (!$singleTxnLikely) {
                if ($scn === 'unknown') { $out[] = 'separate_contract_notice'; }
                if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $out[] = 'through_ticket_disclosure'; }
            }
        } elseif ($selAg === 'yes') {
            // Retailer seller path: if PNR helps or single transaction confirmed, ask TRIN 4/5 to decide 12(5)
            if (!$pnrHelpful && $applies === null && $str === 'unknown' && $scn !== 'yes') { $out[] = 'single_txn_retailer'; }
            if ($scn === 'unknown') { $out[] = 'separate_contract_notice'; }
            if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $out[] = 'through_ticket_disclosure'; }
        } else {
            // Unknown seller: hold it truly minimal – ask the two simple TRIN 4/5 questions first
            if ($scn === 'unknown') { $out[] = 'separate_contract_notice'; }
            if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $out[] = 'through_ticket_disclosure'; }
            // Only if decision remains unclear AND PNR isn't helpful, then ask about same transaction
            if ($applies === null && !$pnrHelpful && empty($out)) {
                if ($sto === 'unknown') { $out[] = 'single_txn_operator'; }
                elseif ($str === 'unknown') { $out[] = 'single_txn_retailer'; }
            }
        }

        // Stable ordering
        $order = ['separate_contract_notice','through_ticket_disclosure','single_txn_operator','single_txn_retailer'];
        $out = array_values(array_unique($out));
        usort($out, function($a,$b) use ($order){
            $ia = array_search($a, $order, true); $ib = array_search($b, $order, true);
            $ia = $ia === false ? 999 : $ia; $ib = $ib === false ? 999 : $ib;
            return $ia <=> $ib;
        });
        return $out;
    }

    /**
     * Split missing into UI (1–6) and AUTO (7–13) buckets for TRIN 6 UX.
     * @param array<string,string> $hooks
     * @return array{0: string[], 1: string[]}
     */
    private function splitMissing(array $hooks): array
    {
        // Behold for bagudkompatibilitet i debug, men brug computeMinimalMissing for faktisk 'missing'
        $ui = ['separate_contract_notice','through_ticket_disclosure','single_txn_operator','single_txn_retailer'];
        $auto = ['shared_pnr_scope','single_booking_reference'];
        $missingUi = [];
        foreach ($ui as $k) { if (!isset($hooks[$k]) || $hooks[$k] === 'unknown') { $missingUi[] = $k; } }
        $missingAuto = [];
        foreach ($auto as $k) { if (!isset($hooks[$k]) || $hooks[$k] === 'unknown') { $missingAuto[] = $k; } }
        return [$missingUi, $missingAuto];
    }

    /**
     * Quick classifier per user’s minimal-data rules.
     * @param array<int,array<string,mixed>> $segments
     * @param string|null $bookingRef
     * @param string|null $sellerType 'operator'|'agency'|null
     * @param array{separate_contract_notice:string, one_contract_schedule:string} $preHooks
     * @return array{classification:string, liable_party?:string, basis:string[]}
     */
    private function quickClassify(array $segments, ?string $bookingRef, ?string $sellerType, array $preHooks): array
    {
        $hasTransfer = count($segments) > 1;
        if (!$hasTransfer) {
            return ['classification' => 'NA', 'basis' => ['no_transfer']];
        }

        // Operators on segments
        $ops = [];
        $pnrs = [];
        foreach ($segments as $s) {
            $op = $s['operator_name'] ?? ($s['operator'] ?? ($s['carrier'] ?? null));
            if (is_string($op) && trim($op) !== '') $ops[] = trim($op);
            $pnr = $s['booking_reference'] ?? ($s['pnr'] ?? null);
            if (is_string($pnr) && trim($pnr) !== '') $pnrs[] = trim($pnr);
        }
        $ops = array_values(array_unique($ops));
        $pnrs = array_values(array_unique($pnrs));
        $oneOperatorAll = (count($ops) === 1 && $ops[0] !== '');
        $sharedPNR = false;
        if (is_string($bookingRef) && trim($bookingRef) !== '') {
            $sharedPNR = true;
        } elseif (count($pnrs) === 1 && $pnrs[0] !== '') {
            $sharedPNR = true;
        }

        $notice = ($preHooks['separate_contract_notice'] === 'yes');
        $oneSched = ($preHooks['one_contract_schedule'] === 'yes');
        $seller = null;
        if ($sellerType === 'operator') $seller = 'operator';
        if ($sellerType === 'agency')   $seller = 'retailer';

        // 1) THROUGH_12_3 – operator liable
        if ($sharedPNR && !$notice && ($seller === 'operator' || ($seller === null && $oneOperatorAll))) {
            return [
                'classification' => 'THROUGH_12_3',
                'liable_party'   => 'operator',
                'basis'          => ['shared_pnr','no_separate_notice', $seller === 'operator' ? 'seller_operator' : 'one_operator_all_segments']
            ];
        }
        // 2) THROUGH_12_4 – retailer liable
        if ($sharedPNR && !$notice && $seller === 'retailer') {
            return [
                'classification' => 'THROUGH_12_4',
                'liable_party'   => 'retailer',
                'basis'          => ['shared_pnr','no_separate_notice','seller_retailer']
            ];
        }
        // 3) THROUGH_DEFAULT
        if (!$notice && ($sharedPNR || $oneSched)) {
            return [
                'classification' => 'THROUGH_DEFAULT',
                'liable_party'   => $seller ?? ($oneOperatorAll ? 'operator' : null),
                'basis'          => [ $sharedPNR ? 'shared_pnr' : 'one_contract_schedule', 'no_separate_notice', $seller ? ('seller_'.$seller) : ($oneOperatorAll ? 'one_operator_all_segments' : 'seller_unknown') ]
            ];
        }
        // 4) SEPARATE_12_5
        if ($notice && (!$sharedPNR || !$oneSched)) {
            return [
                'classification' => 'SEPARATE_12_5',
                'basis'          => ['separate_notice', !$sharedPNR ? 'multiple_pnr' : 'no_single_itinerary']
            ];
        }
        // 5) OBLIGATION_12_1
        if ($hasTransfer && $oneOperatorAll) {
            return [
                'classification' => 'OBLIGATION_12_1',
                'liable_party'   => 'operator',
                'basis'          => ['one_operator_all_segments','has_transfer', $notice ? 'separate_notice_present' : 'no_separate_notice']
            ];
        }
        // Fallback
        return [ 'classification' => 'SEPARATE_12_5', 'basis' => ['default_fallback'] ];
    }
}
