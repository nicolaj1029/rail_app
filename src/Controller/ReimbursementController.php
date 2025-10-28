<?php
declare(strict_types=1);

namespace App\Controller;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use FPDF; // autoloaded by setasign/fpdf

class ReimbursementController extends AppController
{
    public function start(): void
    {
        // Render the form
    }

    public function generate(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            // If user navigated directly without any params, send them to the form
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Build a comprehensive PDF summary with all available fields grouped by TRIN sections.
        $this->disableAutoRender();

        // Compute a claim snapshot similar to flow
        $priceStr = (string)($data['price'] ?? '0 EUR');
        $currency = 'EUR';
        if (preg_match('/([A-Z]{3})/i', $priceStr, $mm)) { $currency = strtoupper($mm[1]); }
        $priceNum = (float)preg_replace('/[^0-9.]/', '', $priceStr);
        $delayFinal = (int)($data['delayAtFinalMinutes'] ?? $data['delay_min_eu'] ?? 0);
        $selfInflicted = false;
        $hasValidTicket = (string)($data['hasValidTicket'] ?? 'yes');
        $safetyMisconduct = (string)($data['safetyMisconduct'] ?? 'no');
        $forbiddenItemsOrAnimals = (string)($data['forbiddenItemsOrAnimals'] ?? 'no');
        $customsRulesBreached = (string)($data['customsRulesBreached'] ?? 'yes');
        $selfInflicted = ($hasValidTicket !== 'yes') || ($safetyMisconduct === 'yes') || ($forbiddenItemsOrAnimals === 'yes') || ($customsRulesBreached !== 'yes');
        $expensesIn = [
            'meals' => (float)($data['expense_breakdown_meals'] ?? ($data['expense_meals'] ?? 0)),
            'hotel' => ((int)($data['expense_breakdown_hotel_nights'] ?? 0) > 0) ? 0.0 : (float)($data['expense_hotel'] ?? 0),
            'alt_transport' => (float)($data['expense_breakdown_local_transport'] ?? ($data['expense_alt_transport'] ?? 0)),
            'other' => (float)($data['expense_breakdown_other_amounts'] ?? ($data['expense_other'] ?? 0)),
        ];
        try {
            $claim = (new \App\Service\ClaimCalculator())->calculate([
                'country_code' => (string)($data['operator_country'] ?? 'EU'),
                'currency' => $currency,
                'ticket_price_total' => $priceNum,
                'trip' => [ 'through_ticket' => true, 'legs' => [] ],
                'disruption' => [
                    'delay_minutes_final' => $delayFinal,
                    'eu_only' => true,
                    'notified_before_purchase' => !empty($data['known_delay']),
                    'extraordinary' => !empty($data['extraordinary']),
                    'self_inflicted' => $selfInflicted,
                ],
                'choices' => [
                    'wants_refund' => ((string)($data['remedyChoice'] ?? '') === 'refund_return'),
                    'wants_reroute_same_soonest' => ((string)($data['remedyChoice'] ?? '') === 'reroute_soonest'),
                    'wants_reroute_later_choice' => ((string)($data['remedyChoice'] ?? '') === 'reroute_later'),
                ],
                'expenses' => $expensesIn,
                'already_refunded' => 0,
                'service_fee_mode' => 'expenses_only',
            ]);
        } catch (\Throwable $e) {
            $claim = ['gross' => 0.0, 'fee' => 0.0, 'net' => 0.0, 'currency' => $currency, 'error' => $e->getMessage()];
        }

        // Helper to stringify mixed values (UploadedFile, arrays, booleans, scalars)
        $stringify = function($val) use (&$stringify): string {
            if ($val === null) { return ''; }
            if ($val instanceof \Psr\Http\Message\UploadedFileInterface) {
                try {
                    if ($val->getError() === \UPLOAD_ERR_OK) {
                        return (string)$val->getClientFilename();
                    }
                } catch (\Throwable $e) { /* ignore */ }
                return '';
            }
            if (is_array($val)) {
                $parts = [];
                foreach ($val as $vv) { $s = $stringify($vv); if ($s !== '') { $parts[] = $s; } }
                return implode(', ', $parts);
            }
            if (is_bool($val)) { return $val ? 'ja' : 'nej'; }
            if (is_scalar($val)) { return (string)$val; }
            $j = @json_encode($val);
            return is_string($j) ? $j : '';
        };

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'Reimbursement Claim Summary', 0, 1);

        $pdf->SetFont('Arial', '', 10);

        // Helper to render a section
        $renderSection = function(string $title, array $pairs) use ($pdf, $stringify) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Ln(2);
            $pdf->Cell(0, 8, $title, 0, 1);
            $pdf->SetFont('Arial', '', 10);
            foreach ($pairs as $label => $val) {
                $s = trim($stringify($val));
                if ($s === '') { continue; }
                $pdf->MultiCell(0, 6, sprintf('%s: %s', $label, $s));
            }
        };

        // TRIN 1 – Status
        $renderSection('TRIN 1 · Status', [
            'Rejsestatus' => $data['travel_state'] ?? null,
            'EU-forsinkelse (min)' => $data['delay_min_eu'] ?? null,
            'Kendt forsinkelse før køb' => !empty($data['known_delay']) ? 'ja' : 'nej',
            'Ekstraordinær hændelse' => !empty($data['extraordinary']) ? 'ja' : 'nej',
        ]);

        // TRIN 2 – Hændelse
        $renderSection('TRIN 2 · Hændelse', [
            'Type' => $data['incident_main'] ?? null,
            'Missed connection' => !empty($data['missed_connection']) ? 'ja' : 'nej',
            'Missed connection station' => $data['missed_connection_station'] ?? null,
            'Årsager (afledt)' => implode(', ', array_keys(array_filter([
                'delay' => !empty($data['reason_delay']),
                'cancellation' => !empty($data['reason_cancellation']),
                'missed connection' => !empty($data['reason_missed_conn']),
            ]))),
        ]);

        // TRIN 3/5 – CIV screening
        $renderSection('TRIN 5 · CIV screening', [
            'Gyldig billet' => $data['hasValidTicket'] ?? null,
            'Adfærd i toget' => $data['safetyMisconduct'] ?? null,
            'Håndbagage/dyr/genstande' => $data['forbiddenItemsOrAnimals'] ?? null,
            'Administrative forskrifter' => $data['customsRulesBreached'] ?? null,
            'Operator-dokumentation' => $data['operatorStampedDisruptionProof'] ?? null,
        ]);

        // TRIN 4 – Rejsedata og operatør
        $renderSection('TRIN 4 · Rejsedata', [
            'Operatør' => $data['operator'] ?? null,
            'Operatør land' => $data['operator_country'] ?? null,
            'Produkt' => $data['operator_product'] ?? null,
            'Afgangsstation' => $data['dep_station'] ?? null,
            'Ankomststation' => $data['arr_station'] ?? null,
            'Afgangsdato' => $data['dep_date'] ?? null,
            'Afgangstid' => $data['dep_time'] ?? null,
            'Ankomsttid' => $data['arr_time'] ?? null,
            'Tog nr./kategori' => $data['train_no'] ?? null,
            'Billetnr./PNR' => $data['ticket_no'] ?? null,
            'Pris' => $data['price'] ?? null,
            'Faktisk ankomstdato' => $data['actual_arrival_date'] ?? null,
            'Faktisk afgang' => $data['actual_dep_time'] ?? null,
            'Faktisk ankomst' => $data['actual_arr_time'] ?? null,
            'Gennemgående billet' => $data['isThroughTicket'] ?? null,
            'Separate kontrakter oplyst' => $data['separateContractsDisclosed'] ?? null,
            'Enkelt transaktion' => $data['singleTxn'] ?? null,
            'Sælger-type' => $data['sellerType'] ?? null,
        ]);

        // TRIN 6 – Art. 12 input (kun spørgsmål 1–6)
        $renderSection('TRIN 6 · Art. 12', [
            'Oplyst gennemgående billet' => $data['through_ticket_disclosure'] ?? null,
            'Købt i én transaktion hos operatør (AUTO)' => $data['single_txn_operator'] ?? null,
            'Købt i én transaktion hos forhandler (AUTO)' => $data['single_txn_retailer'] ?? null,
            'Separate kontrakter oplyst' => $data['separate_contract_notice'] ?? null,
            'Fælles booking/PNR (AUTO)' => $data['shared_pnr_scope'] ?? null,
            'Solgt af operatør (AUTO)' => $data['seller_type_operator'] ?? null,
        ]);

        // TRIN 7 – Remedies (Art. 18)
        $renderSection('TRIN 7 · Afhjælpning', [
            'Valg' => $data['remedyChoice'] ?? null,
            'Refusion anmodet' => $data['refund_requested'] ?? null,
            'Refusionsformular valgt' => $data['refund_form_selected'] ?? null,
            'Reroute samme vilkår (snarest)' => $data['reroute_same_conditions_soonest'] ?? null,
            'Reroute senere (valg)' => $data['reroute_later_at_choice'] ?? null,
            'Reroute info inden 100 min' => $data['reroute_info_within_100min'] ?? null,
            'Ekstra omkostninger' => $data['reroute_extra_costs'] ?? null,
            'Ekstra omkostninger beløb' => $data['reroute_extra_costs_amount'] ?? null,
            'Ekstra omkostninger valuta' => $data['reroute_extra_costs_currency'] ?? null,
            'Nedgradering skete' => $data['downgrade_occurred'] ?? null,
            'Nedgradering komp.basis' => $data['downgrade_comp_basis'] ?? null,
        ]);

        // TRIN 8 – Assistance og udgifter
        $renderSection('TRIN 8 · Assistance og udgifter', [
            'Måltid tilbudt' => $data['meal_offered'] ?? null,
            'Hotel tilbudt' => $data['hotel_offered'] ?? null,
            'Overnatning nødvendig' => $data['overnight_needed'] ?? null,
            'Alternativ transport (blokeret tog)' => $data['blocked_train_alt_transport'] ?? null,
            'Alt. transport leveret' => $data['alt_transport_provided'] ?? null,
            'Kvittering upload (udgifter)' => $data['extra_expense_upload'] ?? null,
            'Udgifter: måltider' => $data['expense_breakdown_meals'] ?? $data['expense_meals'] ?? null,
            'Udgifter: hotel-nætter' => $data['expense_breakdown_hotel_nights'] ?? null,
            'Udgifter: lokal transport' => $data['expense_breakdown_local_transport'] ?? null,
            'Udgifter: andet' => $data['expense_breakdown_other_amounts'] ?? $data['expense_other'] ?? null,
            'Bekræftet forsinkelse modtaget' => $data['delay_confirmation_received'] ?? null,
            'Upload (forsinkelsesbekræftelse)' => $data['delay_confirmation_upload'] ?? null,
            'Ekstraordinært krav' => $data['extraordinary_claimed'] ?? null,
            'Ekstraordinær type' => $data['extraordinary_type'] ?? null,
        ]);

        // TRIN 9 – Interesser og hooks
        $renderSection('TRIN 9 · Interesser og hooks', [
            'Request: Refusion' => !empty($data['request_refund']) ? 'ja' : 'nej',
            'Request: Kompensation 60+' => !empty($data['request_comp_60']) ? 'ja' : 'nej',
            'Request: Kompensation 120+' => !empty($data['request_comp_120']) ? 'ja' : 'nej',
            'Request: Udgifter' => !empty($data['request_expenses']) ? 'ja' : 'nej',
            'Info ønsket før køb' => !empty($data['info_requested_pre_purchase']) ? 'ja' : 'nej',
            'CoC anerkendt' => $data['coc_acknowledged'] ?? null,
            'CIV-markering' => $data['civ_marking_present'] ?? null,
            'Hurtigste flag ved køb' => $data['fastest_flag_at_purchase'] ?? null,
            'MCT realistisk' => $data['mct_realistic'] ?? null,
            'Alternative vist før kontrakt' => $data['alts_shown_precontract'] ?? null,
            'Flere priser vist' => $data['multiple_fares_shown'] ?? null,
            'Billigste fremhævet' => $data['cheapest_highlighted'] ?? null,
            'Fleks-type' => $data['fare_flex_type'] ?? null,
            'Togspecificitet' => $data['train_specificity'] ?? null,
            'PMR bruger' => $data['pmr_user'] ?? null,
            'PMR booket' => $data['pmr_booked'] ?? null,
            'PMR leveret status' => $data['pmr_delivered_status'] ?? null,
            'PMR lovet mangler' => $data['pmr_promised_missing'] ?? null,
            'Cykelreservation type' => $data['bike_reservation_type'] ?? null,
            'Cykelreservation krævet' => $data['bike_res_required'] ?? null,
            'Cykel afvist årsag' => $data['bike_denied_reason'] ?? null,
            'Cykel opfølgende tilbud' => $data['bike_followup_offer'] ?? null,
            'Cykel forsinkelse bucket' => $data['bike_delay_bucket'] ?? null,
            'Købt klasse' => $data['fare_class_purchased'] ?? null,
            'Køje/sæde-type' => $data['berth_seat_type'] ?? null,
            'Reserveret faciliteter leveret' => $data['reserved_amenity_delivered'] ?? null,
            'Klasse leveret status' => $data['class_delivered_status'] ?? null,
            'Preinformeret forstyrrelse' => $data['preinformed_disruption'] ?? null,
            'Preinfo kanal' => $data['preinfo_channel'] ?? null,
            'Realtime info set' => $data['realtime_info_seen'] ?? null,
            'Lovede faciliteter' => $data['promised_facilities'] ?? null,
            'Faktiske faciliteter status' => $data['facilities_delivered_status'] ?? null,
            'Facilitetsnote' => $data['facility_impact_note'] ?? null,
            'Disclosure: gennemgående billet' => $data['through_ticket_disclosure'] ?? null,
            'Disclosure: enkelt transaktion operatør' => $data['single_txn_operator'] ?? null,
            'Disclosure: enkelt transaktion forhandler' => $data['single_txn_retailer'] ?? null,
            'Disclosure: separate kontrakter' => $data['separate_contract_notice'] ?? null,
            'Klagekanal set' => $data['complaint_channel_seen'] ?? null,
            'Klage tidligere indsendt' => $data['complaint_already_filed'] ?? null,
            'Klagekvittering upload' => $data['complaint_receipt_upload'] ?? null,
            'Indsend via officiel kanal' => $data['submit_via_official_channel'] ?? null,
        ]);

        // TRIN 10 – Kompensation
        $renderSection('TRIN 10 · Kompensation', [
            'Endelig forsinkelse (min)' => $data['delayAtFinalMinutes'] ?? null,
            'Bånd' => $data['compensationBand'] ?? null,
            'Voucher accepteret' => !empty($data['voucherAccepted']) ? 'ja' : 'nej',
        ]);

        // Kontakt
        $renderSection('Kontakt', [
            'Navn' => $data['name'] ?? null,
            'Email' => $data['email'] ?? null,
        ]);

        // TRIN 11 – GDPR
        $renderSection('TRIN 11 · GDPR', [
            'Samtykke' => !empty($data['gdprConsent']) ? 'ja' : 'nej',
            'Yderligere oplysninger' => $data['additionalInfo'] ?? $data['additional_info'] ?? null,
        ]);

        // Claim snapshot
        $renderSection('Beregning (snapshot)', [
            'Brutto' => isset($claim['gross']) ? number_format((float)$claim['gross'], 2) . ' ' . $currency : null,
            'Gebyr' => isset($claim['fee']) ? number_format((float)$claim['fee'], 2) . ' ' . $currency : null,
            'Netto' => isset($claim['net']) ? number_format((float)$claim['net'], 2) . ' ' . $currency : null,
            'Valuta' => $currency,
            'Fejl (hvis nogen)' => $claim['error'] ?? null,
        ]);

        // Include any remaining fields not yet shown to truly capture "all fields"
        $shownKeys = [];
        foreach ($data as $k => $_) { /* collect all keys we used above */ }
        // We'll recompute by rendering a catch-all of non-empty fields not in the curated list

        $curated = [
            'name','email','operator','dep_date','dep_station','arr_station','dep_time','arr_time','train_no','ticket_no','price','actual_arrival_date','actual_dep_time','actual_arr_time','missed_connection_station',
            'reason_delay','reason_cancellation','reason_missed_conn',
            'travel_state','delay_min_eu','known_delay','extraordinary','incident_main','missed_connection',
            'hasValidTicket','safetyMisconduct','forbiddenItemsOrAnimals','customsRulesBreached','operatorStampedDisruptionProof',
            'isThroughTicket','separateContractsDisclosed','singleTxn','sellerType','operator_country','operator_product',
            'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice','mct_realistic','one_contract_schedule','contact_info_provided','responsibility_explained','continue_national_rules',
            'remedyChoice','refund_requested','refund_form_selected','reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min','reroute_extra_costs','reroute_extra_costs_amount','reroute_extra_costs_currency','downgrade_occurred','downgrade_comp_basis',
            'meal_offered','hotel_offered','overnight_needed','blocked_train_alt_transport','alt_transport_provided','extra_expense_upload','expense_breakdown_meals','expense_breakdown_hotel_nights','expense_breakdown_local_transport','expense_breakdown_other_amounts','expense_meals','expense_hotel','expense_alt_transport','expense_other','delay_confirmation_received','delay_confirmation_upload','extraordinary_claimed','extraordinary_type',
            'request_refund','request_comp_60','request_comp_120','request_expenses','info_requested_pre_purchase','coc_acknowledged','civ_marking_present','fastest_flag_at_purchase','alts_shown_precontract','multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity','pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing','bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket','fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status','preinformed_disruption','preinfo_channel','realtime_info_seen','promised_facilities','facilities_delivered_status','facility_impact_note','through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice','complaint_channel_seen','complaint_already_filed','complaint_receipt_upload','submit_via_official_channel',
            'delayAtFinalMinutes','compensationBand','voucherAccepted','gdprConsent','additionalInfo','additional_info'
        ];
        $other = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $curated, true)) { continue; }
            $s = $stringify($v);
            if ($s === '') { continue; }
            $other[$k] = $s;
        }
        if (!empty($other)) {
            ksort($other);
            $renderSection('Andre felter', $other);
        }

        // Output inline
        $this->response = $this->response->withType('pdf');
        $this->response = $this->response->withStringBody($pdf->Output('S'));
        return;
    }

    public function official(): void
    {
        $this->request->allowMethod(['post', 'get']);
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        if ($this->request->is('get')) {
            $hasAny = array_filter($data, fn($v) => $v !== null && $v !== '');
            if (empty($hasAny)) {
                $this->redirect(['action' => 'start']);
                return;
            }
        }

        // Merge missing fields from the flow session state so the official PDF reflects
        // the same answers as the on-page flow and non-official summary view.
        // Preference order: explicit POST/GET data > flow.form > flow.meta
        try {
            $session = $this->request->getSession();
            $formSess = (array)$session->read('flow.form') ?: [];
            $metaSess = (array)$session->read('flow.meta') ?: [];
        } catch (\Throwable $e) {
            $formSess = []; $metaSess = [];
        }

        // Allow overriding template via query parameter for diagnostics, but only within webroot or webroot/files
        $source = null;
        $forceName = (string)($this->request->getQuery('template') ?? '');
        if ($forceName !== '') {
            $try = [WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . $forceName, WWW_ROOT . $forceName];
            foreach ($try as $p) {
                if (is_file($p)) { $source = $p; break; }
            }
        }
        if ($source === null) {
            $source = $this->findOfficialTemplatePath();
        }
        if ($source === null || !is_file($source)) {
            // Fallback to summary if template missing
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'Official Form template missing', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 7, "Looked in: webroot/files and webroot. Filenames tried include 'reimbursement_form_uncompressed.pdf' and '(reimboursement|reimbursement) form - EN - accessible.pdf' (spaces or %20).\nYou can also force a file with ?template=FILENAME.pdf");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }

    $map = $this->loadFieldMap() ?: $this->officialFieldMap();
    // If we have a field map, collect all referenced source keys and backfill from session
    if (is_array($map) && !empty($map)) {
        $mapFields = [];
        foreach ($map as $pg => $flds) {
            if (!is_array($flds)) { continue; }
            foreach ($flds as $field => $cfg) {
                $src = is_array($cfg) && array_key_exists('source', $cfg) ? (string)$cfg['source'] : (string)$field;
                if ($src === '') { continue; }
                $mapFields[$src] = true;
            }
        }
        // Backfill gaps from form/meta session so official PDF mirrors the live flow
        foreach (array_keys($mapFields) as $k) {
            $has = array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '';
            if ($has) { continue; }
            if (array_key_exists($k, $formSess) && $formSess[$k] !== null && $formSess[$k] !== '') {
                $data[$k] = $formSess[$k];
                continue;
            }
            if (array_key_exists($k, $metaSess) && $metaSess[$k] !== null && $metaSess[$k] !== '') {
                $data[$k] = $metaSess[$k];
            }
        }
        // Special-case: TRIN 7 exclusives derived from remedyChoice if only present in session
        if (!isset($data['remedy_cancel_return']) && isset($formSess['remedyChoice']) && !isset($data['remedyChoice'])) {
            $data['remedyChoice'] = $formSess['remedyChoice'];
        }
    }
    $debug = (bool)$this->request->getQuery('debug');
    $dx = (float)($this->request->getQuery('dx') ?? 0);
    $dy = (float)($this->request->getQuery('dy') ?? 0);
    // Optional per-box vertical nudge for quick testing: ?boxdy=-50
    $boxDy = (float)($this->request->getQuery('boxdy') ?? 0);

    // Safe stringify to support arrays and uploaded files when filling into the official PDF
    $stringify = function($val) use (&$stringify): string {
        if ($val === null) { return ''; }
        if ($val instanceof \Psr\Http\Message\UploadedFileInterface) {
            try {
                if ($val->getError() === \UPLOAD_ERR_OK) {
                    return (string)$val->getClientFilename();
                }
            } catch (\Throwable $e) { /* ignore */ }
            return '';
        }
        if (is_array($val)) {
            $parts = [];
            foreach ($val as $vv) { $s = $stringify($vv); if ($s !== '') { $parts[] = $s; } }
            return implode(', ', $parts);
        }
        if (is_bool($val)) { return $val ? 'ja' : 'nej'; }
        if (is_scalar($val)) { return (string)$val; }
        $j = @json_encode($val);
        return is_string($j) ? $j : '';
    };

    // Converter for FPDF: FPDF/FPDI's core fonts expect single-byte encodings.
    // Convert UTF-8 strings to ISO-8859-1 where possible (fallback to original on failure).
    $toPdf = function($s) use ($stringify) {
        $s = (string)$s;
        // If contains non-ascii, try converting
        if (!preg_match('//u', $s)) { return $s; }
        $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $conv !== false ? $conv : $s;
    };

    $this->disableAutoRender();
    $fpdi = new Fpdi('P', 'mm', 'A4');
        try {
            $pageCount = $fpdi->setSourceFile($source);
        } catch (CrossReferenceException $e) {
            // Handle compressed xref (unsupported by free parser)
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template: compressed cross-reference (XRef) stream');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Ln(2);
            $pdf->MultiCell(0, 6, "This PDF uses a compression technique that the free FPDI parser can't handle. Options:\n\n1) Provide an uncompressed PDF (save as PDF 1.4 / 'reduced size').\n2) Convert locally using qpdf to disable object streams.\n3) Use the commercial fpdi-pdf-parser add-on.\n\nTried file:\n" . $source);
            $pdf->Ln(2);
            $pdf->SetFont('Courier', '', 10);
            $pdf->MultiCell(0, 5, "qpdf --qdf --object-streams=disable \"in.pdf\" \"out.pdf\"");
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        } catch (\Throwable $e) {
            $this->disableAutoRender();
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->MultiCell(0, 8, 'Cannot import template');
            $pdf->SetFont('Arial', '', 12);
            $pdf->MultiCell(0, 6, 'Error: ' . $e->getMessage() . "\nFile: " . $source);
            $this->response = $this->response->withType('pdf')->withStringBody($pdf->Output('S'));
            return;
        }
    // Buffer for Section 6 content that we'll render on a dedicated blank page
    $pendingSection6 = [];

    // Build a data snapshot for PDF filling and page-6 fallback before the page loop,
    // mirroring the derivations done within the loop when we hit page 5.
    $dataSnap = $data;
    $incident = strtolower((string)($data['incident_main'] ?? ''));
    if (!isset($dataSnap['reason_delay'])) {
        $dataSnap['reason_delay'] = ($incident === 'delay') || !empty($data['reason_delay']);
    }
    if (!isset($dataSnap['reason_cancellation'])) {
        $dataSnap['reason_cancellation'] = ($incident === 'cancellation') || !empty($data['reason_cancellation']);
    }
    if (!isset($dataSnap['reason_missed_conn'])) {
        $dataSnap['reason_missed_conn'] = ($incident === 'missed_connection') || !empty($data['missed_connection']) || !empty($data['reason_missed_conn']);
    }
    // Enforce TRIN 7 exclusive remedy flags if only remedyChoice is present
    $choicePre = (string)($data['remedyChoice'] ?? '');
    if ($choicePre !== '') {
        $dataSnap['remedy_cancel_return'] = ($choicePre === 'refund_return');
        $dataSnap['remedy_reroute_soonest'] = ($choicePre === 'reroute_soonest');
        $dataSnap['remedy_reroute_later'] = ($choicePre === 'reroute_later');
    }

    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tpl = $fpdi->importPage($pageNo);
            $size = $fpdi->getTemplateSize($tpl);
            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

            // Optional debug grid overlay to calibrate coordinates
            if ($debug) {
                // Draw grid; offset labels to reflect dx/dy
                $this->drawDebugGrid($fpdi, (float)$size['width'], (float)$size['height']);
                if ($dx != 0 || $dy != 0) {
                    $fpdi->SetTextColor(255, 0, 0);
                    $fpdi->SetFont('Helvetica', '', 7);
                    $fpdi->SetXY(4, 4);
                    $fpdi->Cell(0, 4, sprintf('nudge dx=%.1fmm dy=%.1fmm', $dx, $dy));
                    $fpdi->SetTextColor(0, 0, 0);
                }
                // If page 5 and we have an additional_info box in the map, draw its rectangle
                if ($pageNo === 5 && !empty($map[$pageNo]['additional_info'])) {
                    $box = $map[$pageNo]['additional_info'];
                    $bx = ($box['x'] ?? 30) + $dx;
                    $by = ($box['y'] ?? 235) + $dy;
                    $bw = ($box['w'] ?? 150);
                    $bh = 60; // visual box height for debug overlay
                    $fpdi->SetDrawColor(255, 0, 0);
                    $fpdi->SetLineWidth(0.5);
                    $fpdi->Rect($bx, $by, $bw, $bh);
                    $fpdi->SetDrawColor(0, 0, 0);
                    $fpdi->SetLineWidth(0.2);
                    // Print numeric debug info for diagnostics
                    $fpdi->SetFont('Helvetica', '', 7);
                    $fpdi->SetXY(4, 10);
                    $info = sprintf('boxX=%.1f boxY=%.1f boxW=%.1f boxH=%.1f pageH=%.1f dx=%.1f dy=%.1f boxdy=%.1f', $bx, $by, $bw, $bh, $size['height'], $dx, $dy, $boxDy);
                    $fpdi->Cell(0, 4, $info, 0, 1);
                    $fpdi->SetFont('Helvetica', '', 9);
                }
            }

            // Derive TRIN 2 checkbox booleans from common form inputs before writing fields
            // - If 'incident_main' is one of delay|cancellation|missed_connection, set the corresponding reason_*
            // - Also map 'missed_connection' boolean to reason_missed_conn if present
            $dataForPdf = $data;
            $incident = strtolower((string)($data['incident_main'] ?? ''));
            if (!isset($dataForPdf['reason_delay'])) {
                $dataForPdf['reason_delay'] = ($incident === 'delay') || !empty($data['reason_delay']);
            }
            if (!isset($dataForPdf['reason_cancellation'])) {
                $dataForPdf['reason_cancellation'] = ($incident === 'cancellation') || !empty($data['reason_cancellation']);
            }
            if (!isset($dataForPdf['reason_missed_conn'])) {
                $dataForPdf['reason_missed_conn'] = ($incident === 'missed_connection') || !empty($data['missed_connection']) || !empty($data['reason_missed_conn']);
            }

            // TRIN 7: Exclusive remedies (Option A) – only one checkbox can be marked based on remedyChoice
            // If remedyChoice absent, infer a single choice from boolean hints with fixed priority
            $choice = (string)($data['remedyChoice'] ?? '');
            if ($choice === '') {
                if (!empty($data['reroute_later_at_choice'])) { $choice = 'reroute_later'; }
                elseif (!empty($data['reroute_same_conditions_soonest'])) { $choice = 'reroute_soonest'; }
                elseif (!empty($data['refund_requested'])) { $choice = 'refund_return'; }
            }
            // Enforce exclusivity regardless of incoming booleans
            $dataForPdf['remedy_cancel_return'] = ($choice === 'refund_return');
            $dataForPdf['remedy_reroute_soonest'] = ($choice === 'reroute_soonest');
            $dataForPdf['remedy_reroute_later'] = ($choice === 'reroute_later');

            // Prepare label map for checkbox fields where we prefer textual rendering
            $fieldLabels = [
                'hasValidTicket' => 'Gyldig billet',
                'safetyMisconduct' => 'Adfærd i toget',
                'forbiddenItemsOrAnimals' => 'Håndbagage/dyr/genstande',
                'customsRulesBreached' => 'Administrative forskrifter',
                'operatorStampedDisruptionProof' => 'Operator-dokumentation',
                'through_ticket_disclosure' => 'Disclosure: gennemgående billet',
                'single_txn_operator' => 'Disclosure: enkelt transaktion (operatør)',
                'single_txn_retailer' => 'Disclosure: enkelt transaktion (forhandler)',
                'separate_contract_notice' => 'Disclosure: separate kontrakter',
                'mct_realistic' => 'MCT realistisk',
                'remedy_cancel_return' => 'Refusion (valgt)',
                'remedy_reroute_soonest' => 'Reroute (snarest)',
                'remedy_reroute_later' => 'Reroute (senere)',
                'meal_offered' => 'Måltid tilbudt',
                'hotel_offered' => 'Hotel tilbudt',
                'request_refund' => 'Request: Refusion',
                'request_comp_60' => 'Request: Kompensation 60+',
                'request_comp_120' => 'Request: Kompensation 120+',
                'request_expenses' => 'Request: Udgifter',
                'pmr_user' => 'PMR bruger',
                'complaint_already_filed' => 'Klage tidligere indsendt',
                'submit_via_official_channel' => 'Indsend via officiel kanal',
                'bike_res_required' => 'Cykelreservation krævet',
            ];

            // Question text map and TRIN grouping for page 5 (show question + answer grouped by TRIN)
            $questionText = [
                // TRIN 5 questions
                'hasValidTicket' => 'Havde du en gyldig billet?',
                'safetyMisconduct' => 'Var der adfærd i toget, som påvirkede situationen?',
                'forbiddenItemsOrAnimals' => 'Var der forbudte genstande eller dyr med?',
                'customsRulesBreached' => 'Var told-/administrative regler overtrådt?',
                'operatorStampedDisruptionProof' => 'Har operatøren stemplet dokumentation for forstyrrelsen?',
                // TRIN 6 questions (only 1-6)
                'through_ticket_disclosure' => 'Blev det oplyst at billetten var gennemgående?',
                'single_txn_operator' => 'Var købet en enkelt transaktion med operatøren?',
                'single_txn_retailer' => 'Var købet en enkelt transaktion med forhandleren?',
                'separate_contract_notice' => 'Blev separate kontrakter oplyst?',
                'shared_pnr_scope' => 'Var alle billetter udstedt under samme bookingnummer/PNR?',
                'seller_type_operator' => 'Var det en jernbanevirksomhed der solgte dig hele rejsen?',
                // TRIN 8 questions
                'meal_offered' => 'Blev der tilbudt måltid?',
                'hotel_offered' => 'Blev der tilbudt hotelovernatning?',
                'overnight_needed' => 'Var overnatning nødvendig?',
                'blocked_train_alt_transport' => 'Blev der tilbudt alternativ transport pga. blokeret tog?',
                'alt_transport_provided' => 'Blev alternativ transport leveret?',
                'extra_expense_upload' => 'Har du uploadet kvitteringer for udgifter?',
                'delay_confirmation_received' => 'Modtog du bekræftelse på forsinkelsen?',
                'delay_confirmation_upload' => 'Har du uploadet bekræftelse på forsinkelsen?',
                'extraordinary_claimed' => 'Har du angivet ekstraordinært krav?',
                // TRIN 9 questions
                'request_refund' => 'Ønsker du refusion?',
                'request_comp_60' => 'Ønsker du kompensation for 60+ min?',
                'request_comp_120' => 'Ønsker du kompensation for 120+ min?',
                'request_expenses' => 'Ønsker du dækning af udgifter?',
                'info_requested_pre_purchase' => 'Ønskede du info før køb?',
                'coc_acknowledged' => 'Er CoC (conditions of carriage) anerkendt?',
                'civ_marking_present' => 'Er CIV-markering til stede?',
                'fastest_flag_at_purchase' => 'Var "hurtigste" flaget valgt ved køb?',
                'alts_shown_precontract' => 'Blev alternativer vist før kontrakt?',
                'multiple_fares_shown' => 'Blev flere priser vist?',
                'cheapest_highlighted' => 'Blev den billigste fremhævet?',
                'pmr_user' => 'Er du PMR-bruger?',
                'pmr_booked' => 'Var PMR-booking foretaget?',
                'pmr_delivered_status' => 'Er PMR-levering bekræftet?',
                'pmr_promised_missing' => 'Manglede lovede PMR-faciliteter?',
                'complaint_channel_seen' => 'Så du klagekanalen?',
                'complaint_already_filed' => 'Er der tidligere indsendt klage?',
                'submit_via_official_channel' => 'Sendes ind via officiel kanal?',
                'bike_res_required' => 'Var cykelreservation påkrævet?',
                'bike_followup_offer' => 'Fik du opfølgende tilbud for cykel?',
            ];

            $trinForField = [
                // TRIN 5
                'hasValidTicket' => 5,'safetyMisconduct' => 5,'forbiddenItemsOrAnimals' => 5,'customsRulesBreached' => 5,'operatorStampedDisruptionProof' => 5,
                // TRIN 6 (only keys 1-6)
                'through_ticket_disclosure' => 6,'single_txn_operator' => 6,'single_txn_retailer' => 6,'separate_contract_notice' => 6,'shared_pnr_scope' => 6,'seller_type_operator' => 6,
                // TRIN 8
                'meal_offered' => 8,'hotel_offered' => 8,'overnight_needed' => 8,'blocked_train_alt_transport' => 8,'alt_transport_provided' => 8,'extra_expense_upload' => 8,'delay_confirmation_received' => 8,'delay_confirmation_upload' => 8,'extraordinary_claimed' => 8,
                // TRIN 9
                'request_refund' => 9,'request_comp_60' => 9,'request_comp_120' => 9,'request_expenses' => 9,'info_requested_pre_purchase' => 9,'coc_acknowledged' => 9,'civ_marking_present' => 9,'fastest_flag_at_purchase' => 9,'alts_shown_precontract' => 9,'multiple_fares_shown' => 9,'cheapest_highlighted' => 9,'pmr_user' => 9,'pmr_booked' => 9,'complaint_channel_seen' => 9,'complaint_already_filed' => 9,'submit_via_official_channel' => 9,'bike_res_required' => 9,'bike_followup_offer' => 9,
            ];

            $answerToText = function($v) use ($stringify) {
                if ($v === null) { return ''; }
                if (is_bool($v)) { return $v ? 'ja' : 'nej'; }
                $s = trim((string)$stringify($v));
                // Common boolean-like / textual answers
                $map = [
                    '1' => 'ja', '0' => 'nej', 'true' => 'ja', 'false' => 'nej',
                    'yes' => 'ja', 'no' => 'nej', 'nej' => 'nej', 'ja' => 'ja',
                    // unknown/unsure tokens
                    'unknown' => 'ved ikke', 'ved ikke' => 'ved ikke', 'ikke sikker' => 'ved ikke', 'unsure' => 'ved ikke', 'maybe' => 'ved ikke', 'dont know' => 'ved ikke'
                ];
                $low = mb_strtolower($s);
                return $map[$low] ?? $s;
            };

            // Write fields for this page
            // Before writing fields, build an augmented Section 6 (additional_info) by including
            // selected answers from TRIN 5-9 so the official PDF's wide multiline area contains
            // any relevant short answers/evidence. Only include items that are explicitly
            // affirmative/checked (avoid including explicit negative answers like 'nej').
            $trinBlock = [];
            $isAffirmative = function($v) use ($stringify) {
                if ($v === null) { return false; }
                if (is_bool($v)) { return $v === true; }
                // normalize via stringify for UploadedFile/arrays/scalars
                $s = mb_strtolower(trim((string)$stringify($v)));
                if ($s === '') { return false; }
                // Accept explicit positive tokens only
                $pos = ['1','true','yes','ja','on'];
                return in_array($s, $pos, true);
            };

            $addIf = function($label, $key) use ($dataForPdf, &$trinBlock, $stringify, $isAffirmative) {
                $v = $dataForPdf[$key] ?? null;
                if (!$isAffirmative($v)) { return; }
                $s = is_array($v) ? $stringify($v) : trim((string)$stringify($v));
                if ($s === '') { return; }
                $trinBlock[] = sprintf('%s: %s', $label, $s);
            };

            // TRIN 5 (CIV screening) — omit from Section 6 per request

            // TRIN 7 (Remedies)
            $addIf('Valg (afhjælpning)', 'remedyChoice');
            $addIf('Refusion anmodet', 'refund_requested');
            $addIf('Reroute info', 'reroute_info_within_100min');

            // TRIN 8 (Assistance & expenses)
            $addIf('Måltid tilbudt', 'meal_offered');
            $addIf('Hotel tilbudt', 'hotel_offered');
            $addIf('Udgifter: måltider', 'expense_breakdown_meals');
            $addIf('Udgifter: hotel-nætter', 'expense_breakdown_hotel_nights');
            $addIf('Kvittering upload (udgifter)', 'extra_expense_upload');

            // TRIN 9 (Interests & hooks) — include a few high-level flags
            $addIf('Request: Refusion', 'request_refund');
            $addIf('Request: Kompensation 60+', 'request_comp_60');
            $addIf('Request: Kompensation 120+', 'request_comp_120');
            $addIf('Request: Udgifter', 'request_expenses');

            if (!empty($trinBlock)) {
                $existing = (string)($dataForPdf['additional_info'] ?? $dataForPdf['additionalInfo'] ?? '');
                $existing = trim($existing);
                $built = implode("\n", $trinBlock);
                $dataForPdf['additional_info'] = $existing === '' ? $built : ($existing . "\n\n" . $built);
            }
            if (!empty($map[$pageNo])) {
                // Special grouped handling for page 5: collect all lines, but do NOT
                // render them on page 5. We will render the entire Section 6 on a
                // separate blank page after the main template pages are emitted.
                if ($pageNo === 5) {
                    $groups = [];
                    foreach ($map[$pageNo] as $field => $cfg) {
                        $src = $cfg['source'] ?? $field;
                        // Only include fields we know question text for
                        if (!isset($trinForField[$src])) { continue; }
                        $trin = $trinForField[$src];
                        // Only include TRINs we want on page 5 -> move to page 6
                        if (!in_array($trin, [6, 8, 9], true)) { continue; }
                        $q = $questionText[$src] ?? $field;
                        $ans = $answerToText($dataForPdf[$src] ?? null);
                        $groups[$trin][] = ['q' => $q, 'a' => $ans];
                    }
                    // Build a flat list of lines (header + questions + additional_info)
                    $allLines = [];
                    ksort($groups);
                    foreach ($groups as $trin => $items) {
                        $allLines[] = $toPdf(sprintf('TRIN %d', $trin));
                        foreach ($items as $it) {
                            $text = sprintf('%s: %s', $it['q'], $it['a']);
                            // simple wrap using MultiCell later on the blank page
                            $allLines[] = $toPdf($text);
                        }
                        $allLines[] = '';
                    }

                    $extra = trim($stringify($dataForPdf['additional_info'] ?? ''));
                    if ($extra !== '') {
                        $allLines[] = $toPdf('');
                        // keep paragraphs as-is, split by newlines
                        $paras = preg_split('/\r\n|\r|\n/', $extra);
                        foreach ($paras as $p) { $p = trim($p); if ($p !== '') { $allLines[] = $toPdf($p); } }
                    }

                    // Filter out any accidental TRIN 1 headers and store for rendering
                    $filtered = array_filter($allLines, function($l) {
                        return !preg_match('/^\s*TRIN\s*1\b/i', (string)$l);
                    });
                    $pendingSection6 = array_values($filtered);
                    // Skip per-field rendering for page 5 entirely
                    continue;
                }

                // Default per-field rendering for non-page-5
                $fpdi->SetFont('Helvetica', '', 9);
                foreach ($map[$pageNo] as $field => $cfg) {
                    $type = $cfg['type'] ?? 'text';
                    $x = (float)$cfg['x'] + $dx;
                    $y = (float)$cfg['y'] + $dy;
                    $w = isset($cfg['w']) ? (float)$cfg['w'] : 0;
                    if ($type === 'checkbox') {
                        $srcFieldCk = $cfg['source'] ?? $field;
                        $checked = !empty($dataForPdf[$srcFieldCk]);
                        if ($checked) {
                            $fpdi->SetDrawColor(0,0,0);
                            $fpdi->SetLineWidth(0.3);
                            $fpdi->Line($x, $y, $x+4, $y+4);
                            $fpdi->Line($x, $y+4, $x+4, $y);
                        }
                        continue;
                    }
                    $srcField = $cfg['source'] ?? $field;
                    $val = $stringify($dataForPdf[$srcField] ?? null);
                    if ($val === '') { continue; }
                    $valPdf = $toPdf($val);
                    $fpdi->SetXY($x, $y);
                    if (!empty($cfg['multiline'])) {
                        $fpdi->MultiCell($w > 0 ? $w : 100, 4, $valPdf);
                    } else {
                        $fpdi->Cell($w > 0 ? $w : 0, 4, $valPdf, 0, 0);
                    }
                }
            }
        }
        // If the template didn't have page 5 (or we otherwise didn't collect Section 6),
        // build a fallback from the map's page-5 config and the prepared data snapshot.
        if (empty($pendingSection6) && !empty($map[5]) && is_array($map[5])) {
            $groups = [];
            $questionText = [
                'through_ticket_disclosure' => 'Blev det oplyst at billetten var gennemgående?',
                'single_txn_operator' => 'Var købet en enkelt transaktion med operatøren?',
                'single_txn_retailer' => 'Var købet en enkelt transaktion med forhandleren?',
                'separate_contract_notice' => 'Blev separate kontrakter oplyst?',
                'shared_pnr_scope' => 'Var alle billetter udstedt under samme bookingnummer/PNR?',
                'seller_type_operator' => 'Var det en jernbanevirksomhed der solgte dig hele rejsen?',
                'meal_offered' => 'Blev der tilbudt måltid?',
                'hotel_offered' => 'Blev der tilbudt hotelovernatning?',
                'overnight_needed' => 'Var overnatning nødvendig?',
                'blocked_train_alt_transport' => 'Blev der tilbudt alternativ transport pga. blokeret tog?',
                'alt_transport_provided' => 'Blev alternativ transport leveret?',
                'extra_expense_upload' => 'Har du uploadet kvitteringer for udgifter?',
                'delay_confirmation_received' => 'Modtog du bekræftelse på forsinkelsen?',
                'delay_confirmation_upload' => 'Har du uploadet bekræftelse på forsinkelsen?',
                'extraordinary_claimed' => 'Har du angivet ekstraordinært krav?',
                'request_refund' => 'Ønsker du refusion?',
                'request_comp_60' => 'Ønsker du kompensation for 60+ min?',
                'request_comp_120' => 'Ønsker du kompensation for 120+ min?',
                'request_expenses' => 'Ønsker du dækning af udgifter?'
            ];
            $trinForField = [
                'through_ticket_disclosure' => 6,'single_txn_operator' => 6,'single_txn_retailer' => 6,'separate_contract_notice' => 6,'shared_pnr_scope' => 6,'seller_type_operator' => 6,
                'meal_offered' => 8,'hotel_offered' => 8,'overnight_needed' => 8,'blocked_train_alt_transport' => 8,'alt_transport_provided' => 8,'extra_expense_upload' => 8,'delay_confirmation_received' => 8,'delay_confirmation_upload' => 8,'extraordinary_claimed' => 8,
                'request_refund' => 9,'request_comp_60' => 9,'request_comp_120' => 9,'request_expenses' => 9,
            ];
            $answerToText = function($v) use ($stringify) {
                if ($v === null) { return ''; }
                if (is_bool($v)) { return $v ? 'ja' : 'nej'; }
                $s = trim((string)$stringify($v));
                $map = ['1'=>'ja','0'=>'nej','true'=>'ja','false'=>'nej','yes'=>'ja','no'=>'nej','nej'=>'nej','ja'=>'ja','unknown'=>'ved ikke'];
                $low = mb_strtolower($s);
                return $map[$low] ?? $s;
            };
            foreach ($map[5] as $field => $cfg) {
                $src = is_array($cfg) && array_key_exists('source', $cfg) ? (string)$cfg['source'] : (string)$field;
                if (!isset($trinForField[$src])) { continue; }
                $trin = $trinForField[$src];
                $q = $questionText[$src] ?? $field;
                $ans = $answerToText($dataSnap[$src] ?? null);
                $groups[$trin][] = ['q' => $q, 'a' => $ans];
            }
            $allLines = [];
            ksort($groups);
            foreach ($groups as $trin => $items) {
                $allLines[] = $toPdf(sprintf('TRIN %d', $trin));
                foreach ($items as $it) { $allLines[] = $toPdf(sprintf('%s: %s', $it['q'], $it['a'])); }
                $allLines[] = '';
            }
            $extra = trim($stringify($dataSnap['additional_info'] ?? $data['additional_info'] ?? ''));
            if ($extra !== '') {
                $allLines[] = $toPdf('');
                $paras = preg_split('/\r\n|\r|\n/', $extra);
                foreach ($paras as $p) { $p = trim($p); if ($p !== '') { $allLines[] = $toPdf($p); } }
            }
            $pendingSection6 = array_values(array_filter($allLines, function($l){ return !preg_match('/^\s*TRIN\s*1\b/i', (string)$l); }));
        }

        // After rendering all template pages (and optional fallback build), if we collected
        // Section 6 content, render it on a dedicated blank page (page 6+).
        if (!empty($pendingSection6)) {
            // Add blank A4 page
            $fpdi->AddPage('P', [210, 297]);
            $fpdi->SetFont('Helvetica', '', 9);
            $left = 20; $top = 20; $boxWidth = 170; $lineH = 5;
            $y = $top;
            foreach ($pendingSection6 as $line) {
                if ($y + $lineH > (297 - 20)) {
                    $fpdi->AddPage('P', [210, 297]);
                    $y = $top;
                }
                $fpdi->SetXY($left, $y);
                // Use MultiCell to allow wrapping inside the width
                $fpdi->MultiCell($boxWidth, $lineH, $line);
                $y += $lineH * (max(1, ceil($fpdi->GetStringWidth($line) / ($boxWidth))));
            }
        }

        $this->response = $this->response->withType('pdf')->withStringBody($fpdi->Output('S'));
        return;
    }

    /**
     * Minimal coordinate map for key fields on the official form.
     * Coordinates are in mm relative to the top-left of each page.
     * Adjust iteratively by viewing the output.
     * @return array<int,array<string,array{x:float,y:float,w?:float,multiline?:bool}>>
     */
    private function officialFieldMap(): array
    {
        return [
            1 => [
                'name' => ['x' => 30, 'y' => 40, 'w' => 80, 'type' => 'text'],
                'email' => ['x' => 130, 'y' => 40, 'w' => 60, 'type' => 'text'],
                'operator' => ['x' => 30, 'y' => 55, 'w' => 80, 'type' => 'text'],
                'train_no' => ['x' => 130, 'y' => 55, 'w' => 60, 'type' => 'text'],
                'dep_station' => ['x' => 30, 'y' => 70, 'w' => 80, 'type' => 'text'],
                'arr_station' => ['x' => 130, 'y' => 70, 'w' => 60, 'type' => 'text'],
                'dep_date' => ['x' => 30, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'dep_time' => ['x' => 75, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'arr_time' => ['x' => 130, 'y' => 85, 'w' => 40, 'type' => 'text'],
                'ticket_no' => ['x' => 30, 'y' => 100, 'w' => 160, 'type' => 'text'],
                'price' => ['x' => 30, 'y' => 115, 'w' => 40, 'type' => 'text'],
                'actual_arrival_date' => ['x' => 30, 'y' => 130, 'w' => 40, 'type' => 'text'],
                'missed_connection_station' => ['x' => 30, 'y' => 145, 'w' => 160, 'multiline' => true, 'type' => 'text'],
                // Section 6: Additional information (arguments/evidence) – wide multiline area
                'additional_info' => ['x' => 30, 'y' => 200, 'w' => 160, 'multiline' => true, 'type' => 'text'],
            ],
        ];
    }

    /**
     * Attempt to locate the official template PDF, handling filenames with spaces or literal %20,
     * and both reimbursement/reimboursement spellings.
     */
    private function findOfficialTemplatePath(): ?string
    {
        $dirs = [
            WWW_ROOT . 'files' . DIRECTORY_SEPARATOR,
            WWW_ROOT,
        ];
        // Prefer locally converted/uncompressed files first
        $candidates = [
            'reimbursement_form_uncompressed.pdf',
            'reimbursement_form_converted.pdf',
            // then the known official names (may be compressed)
            'reimboursement form - EN - accessible.pdf',
            'reimboursement%20form%20-%20EN%20-%20accessible.pdf',
            'reimbursement form - EN - accessible.pdf',
            'reimbursement%20form%20-%20EN%20-%20accessible.pdf',
        ];
        foreach ($dirs as $dir) {
            foreach ($candidates as $file) {
                $p = $dir . $file;
                if (is_file($p)) { return $p; }
            }
        }
        // Fallback glob scan in both locations
        foreach ($dirs as $dir) {
            $patterns = [
                $dir . '*reimbours*form*EN*accessible*.pdf',
                $dir . '*reimburs*form*EN*accessible*.pdf',
            ];
            foreach ($patterns as $glob) {
                $hits = glob($glob) ?: [];
                if (!empty($hits)) { return $hits[0]; }
            }
        }
        return null;
    }

    /**
     * Load a field map from config/pdf/reimbursement_map.json if present.
     * @return array<int,array<string,array<string,mixed>>>|null
     */
    private function loadFieldMap(): ?array
    {
        $path = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'reimbursement_map.json';
        if (!is_file($path)) {
            return null;
        }
        $json = (string)file_get_contents($path);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Draw a light grid to calibrate coordinates (in mm).
     */
    private function drawDebugGrid(Fpdi $pdf, float $w, float $h): void
    {
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFont('Helvetica', '', 6);
        for ($x = 0; $x <= $w; $x += 10) {
            $pdf->Line($x, 0, $x, $h);
            if ($x % 20 === 0) { $pdf->SetXY($x + 1, 2); $pdf->Cell(8, 3, (string)$x, 0, 0); }
        }
        for ($y = 0; $y <= $h; $y += 10) {
            $pdf->Line(0, $y, $w, $y);
            if ($y % 20 === 0) { $pdf->SetXY(2, $y + 1); $pdf->Cell(8, 3, (string)$y, 0, 0); }
        }
    }
}
