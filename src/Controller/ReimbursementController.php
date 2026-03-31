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
        // Collect request input for the summary view
        $data = $this->request->is('post') ? (array)$this->request->getData() : (array)$this->request->getQueryParams();
        $transportMode = $this->normalizeTransportMode((string)($data['transport_mode'] ?? ($data['gating_mode'] ?? 'rail')));
        $isFerry = $transportMode === 'ferry';
        $departureLabel = match ($transportMode) {
            'ferry' => 'Afgangshavn/terminal',
            'air' => 'Afgangslufthavn',
            'bus' => 'Afgangsstoppested/terminal',
            default => 'Afgangsstation',
        };
        $arrivalLabel = match ($transportMode) {
            'ferry' => 'Ankomsthavn/terminal',
            'air' => 'Ankomstlufthavn',
            'bus' => 'Ankomststoppested/terminal',
            default => 'Ankomststation',
        };
        $connectionLabel = match ($transportMode) {
            'ferry' => 'Skiftehavn/-terminal',
            'air' => 'Skiftelufthavn',
            'bus' => 'Skiftested',
            default => 'Skiftestation',
        };
        $returnToLabel = match ($transportMode) {
            'ferry' => 'Retur til afgangshavn/terminal',
            'air' => 'Retur til afgangslufthavn',
            'bus' => 'Retur til afgangsstoppested/terminal',
            default => 'Retur til afgangsstation',
        };
        $step7OriginLabel = $isFerry ? 'Udgangspunkt for ombooking' : 'Udgangspunkt for omlaegning';
        $step7ModeLabel = $isFerry ? 'Transportform for ombooking' : 'Transportform for omlaegning';
        $step7EndpointLabel = $isFerry ? 'Hvor endte ombookingen' : 'Hvor endte omlaegningen';
        $step7ArrivalLabel = match ($transportMode) {
            'ferry' => 'Omlagt til havn/terminal',
            'air' => 'Omlagt til lufthavn',
            'bus' => 'Omlagt til stoppested/terminal',
            default => 'Omlagt til station',
        };
        $art20ArrivalLabel = match ($transportMode) {
            'ferry' => 'Art.20 ankomsthavn/terminal',
            'air' => 'Art.20 ankomstlufthavn',
            'bus' => 'Art.20 ankomststed',
            default => 'Art.20 ankomststation',
        };

        // Init simple summary PDF
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Reimbursement Claim Summary', 0, 1);

        $pdf->SetFont('Arial', '', 10);

        // Helper to stringify values
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

        // TRIN 1 ÔÇô Status
        $renderSection('TRIN 1 -À Status', [
            'Rejsestatus' => $data['travel_state'] ?? null,
            'EU-forsinkelse (min)' => $data['delay_min_eu'] ?? null,
            'Kendt forsinkelse f+©r k+©b' => !empty($data['known_delay']) ? 'ja' : 'nej',
            'Ekstraordin+ªr h+ªndelse' => !empty($data['extraordinary']) ? 'ja' : 'nej',
        ]);

        // TRIN 2 ÔÇô H+ªndelse
        $renderSection('TRIN 2 -À H+ªndelse', [
            'Type' => $data['incident_main'] ?? null,
            'Missed connection' => !empty($data['missed_connection']) ? 'ja' : 'nej',
            $connectionLabel => $data['missed_connection_station'] ?? null,
            '+àrsager (afledt)' => implode(', ', array_keys(array_filter([
                'delay' => !empty($data['reason_delay']),
                'cancellation' => !empty($data['reason_cancellation']),
                'missed connection' => !empty($data['reason_missed_conn']),
            ]))),
        ]);

        // TRIN 3/5 ÔÇô CIV screening
        $renderSection('TRIN 5 -À CIV screening', [
            'Gyldig billet' => $data['hasValidTicket'] ?? null,
            'Adf+ªrd i toget' => $data['safetyMisconduct'] ?? null,
            'H+Ñndbagage/dyr/genstande' => $data['forbiddenItemsOrAnimals'] ?? null,
            'Administrative forskrifter' => $data['customsRulesBreached'] ?? null,
            'Operator-dokumentation' => $data['operatorStampedDisruptionProof'] ?? null,
        ]);

        // TRIN 4 ÔÇô Rejsedata og operat+©r
        $renderSection('TRIN 4 -À Rejsedata', [
            'Operat+©r' => $data['operator'] ?? null,
            'Operat+©r land' => $data['operator_country'] ?? null,
            'Produkt' => $data['operator_product'] ?? null,
            $departureLabel => $data['dep_station'] ?? null,
            $arrivalLabel => $data['arr_station'] ?? null,
            'Afgangsdato' => $data['dep_date'] ?? null,
            'Afgangstid' => $data['dep_time'] ?? null,
            'Ankomsttid' => $data['arr_time'] ?? null,
            'Tog nr./kategori' => $data['train_no'] ?? null,
            'Billetnr./PNR' => $data['ticket_no'] ?? null,
            'Pris' => $data['price'] ?? null,
            'Faktisk ankomstdato' => $data['actual_arrival_date'] ?? null,
            'Faktisk afgang' => $data['actual_dep_time'] ?? null,
            'Faktisk ankomst' => $data['actual_arr_time'] ?? null,
            'Gennemg+Ñende billet' => $data['isThroughTicket'] ?? null,
            'Separate kontrakter oplyst' => $data['separateContractsDisclosed'] ?? null,
            'Enkelt transaktion' => $data['singleTxn'] ?? null,
            'S+ªlger-type' => $data['sellerType'] ?? null,
        ]);

        // TRIN 6 ÔÇô Art. 12 input (kun sp+©rgsm+Ñl 1ÔÇô6)
        $renderSection('TRIN 6 -À Art. 12', [
            'Oplyst gennemg+Ñende billet' => $data['through_ticket_disclosure'] ?? null,
            'K+©bt i +®n transaktion hos operat+©r (AUTO)' => $data['single_txn_operator'] ?? null,
            'K+©bt i +®n transaktion hos forhandler (AUTO)' => $data['single_txn_retailer'] ?? null,
            'Separate kontrakter oplyst' => $data['separate_contract_notice'] ?? null,
            'F+ªlles booking/PNR (AUTO)' => $data['shared_pnr_scope'] ?? null,
            'Solgt af operat+©r (AUTO)' => $data['seller_type_operator'] ?? null,
        ]);

        // TRIN 7 ÔÇô Remedies (Art. 18)
        $renderSection('TRIN 7 -À Afhj+ªlpning', [
            'Valg' => $this->formatRemedyChoice($data['remedyChoice'] ?? null, $transportMode),
            'Refusion anmodet' => $data['refund_requested'] ?? null,
            'Refusionsformular valgt' => $data['refund_form_selected'] ?? null,
            'Reroute samme vilk+Ñr (snarest)' => $data['reroute_same_conditions_soonest'] ?? null,
            'Reroute senere (valg)' => $data['reroute_later_at_choice'] ?? null,
            ($transportMode === 'air' ? 'Article 8-valg tilbudt' : 'Reroute info inden 100 min') => $transportMode === 'air' ? ($data['air_article8_choice_offered'] ?? null) : ($data['reroute_info_within_100min'] ?? null),
            $step7OriginLabel => $data['a18_from_station'] ?? null,
            $returnToLabel => $data['a18_return_to_station'] ?? null,
            $step7ModeLabel => $this->formatTransportChoice($data['a18_reroute_mode'] ?? null),
            $step7EndpointLabel => $this->formatResolutionEndpoint($data['a18_reroute_endpoint'] ?? null, $transportMode),
            $step7ArrivalLabel => $data['a18_reroute_arrival_station'] ?? null,
            'Air refund scope' => $transportMode === 'air' ? ($data['air_refund_scope'] ?? null) : null,
            'Retur til foerste afgangssted' => $transportMode === 'air' ? ($data['air_return_to_first_departure_point'] ?? null) : null,
            'Selv arrangeret ombooking' => $transportMode === 'air' ? ($data['air_self_arranged_reroute'] ?? ($data['self_purchased_new_ticket'] ?? null)) : null,
            'Hvorfor selv fundet loesning' => $transportMode === 'air' ? ($data['air_self_arranged_reroute_reason'] ?? ($data['self_purchase_reason'] ?? null)) : null,
            'Flyselskabet bekraeftede loesningen' => $transportMode === 'air' ? ($data['air_airline_confirmed_self_arranged_solution'] ?? ($data['self_purchase_approved_by_operator'] ?? null)) : null,
            'Alternativ lufthavn-transfer' => $transportMode === 'air' ? ($data['air_alternative_airport_transfer_needed'] ?? null) : null,
            'Ekstra omkostninger' => $data['reroute_extra_costs'] ?? null,
            'Ekstra omkostninger bel+©b' => $data['reroute_extra_costs_amount'] ?? null,
            'Ekstra omkostninger valuta' => $data['reroute_extra_costs_currency'] ?? null,
            'Nedgradering skete' => $data['downgrade_occurred'] ?? null,
            'Nedgradering komp.basis' => $data['downgrade_comp_basis'] ?? null,
            'Koebt kabineklasse' => $transportMode === 'air' ? ($data['air_downgrade_booked_class'] ?? null) : null,
            'Floejet kabineklasse' => $transportMode === 'air' ? ($data['air_downgrade_flown_class'] ?? null) : null,
            'Artikel 10-refusionsprocent' => $transportMode === 'air' ? ($data['air_downgrade_refund_percent'] ?? null) : null,
        ]);

        // TRIN 8 ÔÇô Assistance og udgifter
        $renderSection('TRIN 8 -À Assistance og udgifter', [
            'M+Ñltid tilbudt' => $data['meal_offered'] ?? null,
            'Hotel tilbudt' => $data['hotel_offered'] ?? null,
            'Overnatning n+©dvendig' => $data['overnight_needed'] ?? null,
            'Alternativ transport (blokeret tog)' => $data['blocked_train_alt_transport'] ?? null,
            'Alt. transport leveret' => $data['alt_transport_provided'] ?? null,
            'Art.20 slutpunkt' => $this->formatResolutionEndpoint($data['a20_where_ended'] ?? null, $transportMode),
            $art20ArrivalLabel => $data['a20_arrival_station'] ?? null,
            'Kvittering upload (udgifter)' => $data['extra_expense_upload'] ?? null,
            'Udgifter: m+Ñltider' => $data['expense_breakdown_meals'] ?? $data['expense_meals'] ?? null,
            'Udgifter: hotel-n+ªtter' => $data['expense_breakdown_hotel_nights'] ?? null,
            'Udgifter: lokal transport' => $data['expense_breakdown_local_transport'] ?? null,
            'Udgifter: andet' => $data['expense_breakdown_other_amounts'] ?? $data['expense_other'] ?? null,
            'Bekr+ªftet forsinkelse modtaget' => $data['delay_confirmation_received'] ?? null,
            'Upload (forsinkelsesbekr+ªftelse)' => $data['delay_confirmation_upload'] ?? null,
            'Ekstraordin+ªrt krav' => $data['extraordinary_claimed'] ?? null,
            'Ekstraordin+ªr type' => $data['extraordinary_type'] ?? null,
        ]);

        // TRIN 9 ÔÇô Interesser og hooks
        $renderSection('TRIN 9 -À Interesser og hooks', [
            'Request: Refusion' => !empty($data['request_refund']) ? 'ja' : 'nej',
            'Request: Kompensation 60+' => !empty($data['request_comp_60']) ? 'ja' : 'nej',
            'Request: Kompensation 120+' => !empty($data['request_comp_120']) ? 'ja' : 'nej',
            'Request: Udgifter' => !empty($data['request_expenses']) ? 'ja' : 'nej',
            'Info +©nsket f+©r k+©b' => !empty($data['info_requested_pre_purchase']) ? 'ja' : 'nej',
            'CoC anerkendt' => $data['coc_acknowledged'] ?? null,
            'CIV-markering' => $data['civ_marking_present'] ?? null,
            'Hurtigste flag ved k+©b' => $data['fastest_flag_at_purchase'] ?? null,
            'MCT realistisk' => $data['mct_realistic'] ?? null,
            'Alternative vist f+©r kontrakt' => $data['alts_shown_precontract'] ?? null,
            'Fik du vist flere prisvalg for samme afgang?' => $data['multiple_fares_shown'] ?? null,
            "Var 'billigste pris' markeret/anbefalet?" => $data['cheapest_highlighted'] ?? null,
            'Fleks-type' => $data['fare_flex_type'] ?? null,
            'Togspecificitet' => $data['train_specificity'] ?? null,
            'PMR bruger' => $data['pmr_user'] ?? null,
            'PMR booket' => $data['pmr_booked'] ?? null,
            'PMR leveret status' => $data['pmr_delivered_status'] ?? null,
            'PMR lovet mangler' => $data['pmr_promised_missing'] ?? null,
            'Cykelreservation type' => $data['bike_reservation_type'] ?? null,
            'Cykelreservation kr+ªvet' => $data['bike_res_required'] ?? null,
            'Cykel afvist +Ñrsag' => $data['bike_denied_reason'] ?? null,
            'Cykel opf+©lgende tilbud' => $data['bike_followup_offer'] ?? null,
            'Cykel forsinkelse bucket' => $data['bike_delay_bucket'] ?? null,
            'K+©bt klasse' => $data['fare_class_purchased'] ?? null,
            'K+©je/s+ªde-type' => $data['berth_seat_type'] ?? null,
            'Reserveret faciliteter leveret' => $data['reserved_amenity_delivered'] ?? null,
            'Klasse leveret status' => $data['class_delivered_status'] ?? null,
            'Preinformeret forstyrrelse' => $data['preinformed_disruption'] ?? null,
            'Preinfo kanal' => $data['preinfo_channel'] ?? null,
            'Realtime info set' => $data['realtime_info_seen'] ?? null,
            'Lovede faciliteter' => $data['promised_facilities'] ?? null,
            'Faktiske faciliteter status' => $data['facilities_delivered_status'] ?? null,
            'Facilitetsnote' => $data['facility_impact_note'] ?? null,
            'Disclosure: gennemg+Ñende billet' => $data['through_ticket_disclosure'] ?? null,
            'Disclosure: enkelt transaktion operat+©r' => $data['single_txn_operator'] ?? null,
            'Disclosure: enkelt transaktion forhandler' => $data['single_txn_retailer'] ?? null,
            'Disclosure: separate kontrakter' => $data['separate_contract_notice'] ?? null,
            'Klagekanal set' => $data['complaint_channel_seen'] ?? null,
            'Klage tidligere indsendt' => $data['complaint_already_filed'] ?? null,
            'Klagekvittering upload' => $data['complaint_receipt_upload'] ?? null,
            'Indsend via officiel kanal' => $data['submit_via_official_channel'] ?? null,
        ]);

        // TRIN 10 ÔÇô Kompensation
        $renderSection('TRIN 10 -À Kompensation', [
            'Endelig forsinkelse (min)' => $data['delayAtFinalMinutes'] ?? null,
            'B+Ñnd' => $data['compensationBand'] ?? null,
            'Voucher accepteret' => !empty($data['voucherAccepted']) ? 'ja' : 'nej',
        ]);

        // Kontakt
        $renderSection('Kontakt', [
            'Navn' => $data['name'] ?? null,
            'Email' => $data['email'] ?? null,
        ]);

        // TRIN 11 ÔÇô GDPR
        $renderSection('TRIN 11 -À GDPR', [
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
            $incidentSess = (array)$session->read('flow.incident') ?: [];
            $computeSess = (array)$session->read('flow.compute') ?: [];
            $flagsSess = (array)$session->read('flow.flags') ?: [];
            [$formSess, $metaSess] = $this->sanitizeFlowSession($formSess, $metaSess);
            // Persist the cleaned split so legacy debug keys stop leaking into form state
            $session->write('flow.form', $formSess);
            $session->write('flow.meta', $metaSess);
        } catch (\Throwable $e) {
            $formSess = []; $metaSess = []; $incidentSess = []; $computeSess = []; $flagsSess = [];
        }
        // Hydrate missing simple flags from compute/flags into $data so official payload reflects flow state
        if (!isset($data['travel_state'])) {
            $data['travel_state'] = (string)($computeSess['travel_state'] ?? ($flagsSess['travel_state'] ?? ''));
        }
        if (!isset($data['delay_min_eu']) || $data['delay_min_eu'] === '') {
            if (isset($computeSess['delayMinEU'])) { $data['delay_min_eu'] = (string)$computeSess['delayMinEU']; }
        }
        if (!isset($data['known_delay']) && array_key_exists('knownDelayBeforePurchase', $computeSess)) {
            $data['known_delay'] = $computeSess['knownDelayBeforePurchase'] ? '1' : '';
        }
        if (!isset($data['extraordinary']) && array_key_exists('extraordinary', $computeSess)) {
            $data['extraordinary'] = $computeSess['extraordinary'] ? '1' : '';
        }

        $transportMode = $this->normalizeTransportMode((string)(
            $data['transport_mode']
            ?? $formSess['transport_mode']
            ?? $metaSess['transport_mode']
            ?? $computeSess['transport_mode']
            ?? $flagsSess['transport_mode']
            ?? 'rail'
        ));
        $countryQuery = strtoupper((string)($this->request->getQuery('country') ?? ($data['operator_country'] ?? '')));
        $preferNat = (string)($this->request->getQuery('prefer') ?? '') === 'national'
            || (string)($this->request->getQuery('national') ?? '') === '1';
        $templateQuery = (string)($this->request->getQuery('template') ?? '');
        $isFerryClaimLetter = $transportMode === 'ferry' && (
            stripos($templateQuery, 'ferry_claim_letter') !== false
            || stripos($templateQuery, 'Form_ferry_claim_letter') !== false
            || stripos($templateQuery, 'claim_ferry_letter') !== false
        );
        $isBusClaimLetter = $transportMode === 'bus' && (
            stripos($templateQuery, 'bus_claim_letter') !== false
            || stripos($templateQuery, 'Form_bus_claim_letter') !== false
            || stripos($templateQuery, 'claim_bus_letter') !== false
        );

        // Developer aid: expose a JSON dump of relevant session values for debugging via DevTools
        // Usage: /reimbursement/official?eu=1&session=1
        $peekSess = (string)($this->request->getQuery('session') ?? $this->request->getQuery('peek') ?? '') === '1';
        $debugPayload = null;
        if ($peekSess) {
            // Ensure core incident fields are surfaced even if only stored nested under metaSess['incident']
            if (empty($data['incident_main'])) {
                $im = '';
                if (isset($formSess['incident']) && is_array($formSess['incident'])) {
                    $im = (string)($formSess['incident']['main'] ?? '');
                }
                if ($im === '' && isset($metaSess['incident']) && is_array($metaSess['incident'])) {
                    $im = (string)($metaSess['incident']['main'] ?? '');
                }
                if ($im === '' && isset($incidentSess['main'])) {
                    $im = (string)$incidentSess['main'];
                }
                if ($im !== '') { $data['incident_main'] = $im; }
            }
            if (!isset($data['missed_connection'])) {
                $mc = null;
                if (isset($formSess['incident']) && is_array($formSess['incident'])) {
                    $mc = $formSess['incident']['missed'] ?? null;
                }
                if ($mc === null && isset($metaSess['incident']) && is_array($metaSess['incident'])) {
                    $mc = $metaSess['incident']['missed'] ?? null;
                }
                if ($mc === null && array_key_exists('missed', $incidentSess)) {
                    $mc = $incidentSess['missed'];
                }
                if (!empty($mc)) { $data['missed_connection'] = true; }
            }
            $keys = [
                // Incident / page 1
                'incident_main','missed_connection','missed_connection_station','delay_min_eu','travel_state','known_delay','extraordinary',
                'reason_delay','reason_cancellation','reason_missed_conn',
                // PMR core
                'pmr_user','pmr_booked','pmrQBooked','pmrQDelivered','pmrQPromised','pmr_facility_details',
                // TRIN 4 (Remedy + reroute follow-ups)
                'remedyChoice','chosenPath','reroute_info_within_100min','self_purchased_new_ticket','reroute_extra_costs','downgrade_occurred',
                // TRIN 5 (Assistance toggles)
                'meal_offered','hotel_offered','blocked_train_alt_transport','alt_transport_provided',
                // TRIN 5 (Self-paid breakdowns)
                'meal_self_paid_amount','meal_self_paid_currency',
                'hotel_self_paid_amount','hotel_self_paid_currency','hotel_self_paid_nights',
                'blocked_self_paid_amount','blocked_self_paid_currency',
                'alt_self_paid_amount','alt_self_paid_currency',
                // TRIN 3 (Art. 9(1) Information)
                'preinformed_disruption','preinfo_channel','realtime_info_seen',
            ];
            $selected = [];
            foreach ($keys as $k) {
                $selected[$k] = $data[$k] ?? $formSess[$k] ?? $metaSess[$k] ?? null;
            }
            // Provide a full dump similar to the previous behavior, plus flattened views for quick searching
            $flatten = function($arr, $prefix = '') use (&$flatten) {
                $out = [];
                if (!is_array($arr)) { return $out; }
                foreach ($arr as $k => $v) {
                    $key = $prefix === '' ? (string)$k : ($prefix . '.' . (string)$k);
                    if (is_array($v)) {
                        $out += $flatten($v, $key);
                    } else {
                        $out[$key] = $v;
                    }
                }
                return $out;
            };
            $debugPayload = [
                'phpSessionId' => method_exists($session, 'id') ? $session->id() : null,
                'now' => date('c'),
                'notes' => 'selected shows common keys. data shows GET/POST. flow shows full stored session. flat.* are recursively flattened dot-keys for quick search.',
                'selected' => $selected,
                'data' => $data, // raw GET/POST that invoked this endpoint
                'flow' => [
                    'form' => $formSess,
                    'meta' => $metaSess,
                    'incident' => $incidentSess,
                ],
                'flat' => [
                    'form' => $flatten($formSess),
                    'meta' => $flatten($metaSess),
                    'incident' => $flatten($incidentSess),
                ],
                // Provide a derived snapshot focused on remedyChoice (no extrapolation for reasons)
                'final_preview' => (function() use ($data, $formSess, $metaSess) {
                    // Merge a lightweight view: explicit data first, then form, then meta
                    $snap = $data;
                    foreach (['remedyChoice','refund_requested','request_expenses','meal_self_paid_amount','hotel_self_paid_amount','blocked_self_paid_amount','alt_self_paid_amount'] as $k) {
                        if (!isset($snap[$k]) || $snap[$k] === '' || $snap[$k] === null) {
                            if (isset($formSess[$k]) && $formSess[$k] !== '' && $formSess[$k] !== null) { $snap[$k] = $formSess[$k]; continue; }
                            if (isset($metaSess[$k]) && $metaSess[$k] !== '' && $metaSess[$k] !== null) { $snap[$k] = $metaSess[$k]; }
                        }
                    }
                    $isYes = function($v){
                        $s = strtolower(trim((string)$v));
                        return in_array($s, ['yes','ja','true','1'], true);
                    };
                    $choice = (string)($snap['remedyChoice'] ?? '');
                    $snap['remedy_cancel_return'] = ($choice === 'refund_return');
                    $snap['remedy_reroute_soonest'] = ($choice === 'reroute_soonest');
                    $snap['remedy_reroute_later'] = ($choice === 'reroute_later');
                    if ($choice === 'refund_return' || $isYes($snap['refund_requested'] ?? null)) { $snap['main_refund'] = true; }
                    if ($choice === 'reroute_soonest' || $choice === 'reroute_later') { $snap['main_compensation'] = true; }
                    $hasExp = $isYes($snap['request_expenses'] ?? null) || ($snap['meal_self_paid_amount'] ?? '') !== '' || ($snap['hotel_self_paid_amount'] ?? '') !== '' || ($snap['blocked_self_paid_amount'] ?? '') !== '' || ($snap['alt_self_paid_amount'] ?? '') !== '';
                    if ($hasExp) { $snap['main_expenses'] = true; }
                    $transportMode = $this->normalizeTransportMode((string)($snap['transport_mode'] ?? ($snap['gating_mode'] ?? 'rail')));
                    $snap['display_remedyChoice'] = $this->formatRemedyChoice($snap['remedyChoice'] ?? null, $transportMode);
                    $snap['display_a18_reroute_mode'] = $this->formatTransportChoice($snap['a18_reroute_mode'] ?? null);
                    $snap['display_a18_reroute_endpoint'] = $this->formatResolutionEndpoint($snap['a18_reroute_endpoint'] ?? null, $transportMode);
                    $snap['display_a20_where_ended'] = $this->formatResolutionEndpoint($snap['a20_where_ended'] ?? null, $transportMode);
                    return $snap;
                })(),
            ];
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
            // Respect force=eu to always choose EU official template
            $forceEu = (string)($this->request->getQuery('eu') ?? $this->request->getQuery('force')) === '1' || (string)$this->request->getQuery('force') === 'eu';
        // Defaults to avoid undefined notices when forcing EU template
        $opName = (string)($data['operator'] ?? '');
        $opProd = (string)($data['operator_product'] ?? '');
        $preferNat = (string)($this->request->getQuery('prefer') ?? '') === 'national' || (string)$this->request->getQuery('national') === '1';

        if ($forceEu) {
            $source = $this->findOfficialTemplatePath();
        } else {
            // Hard override: if prefer=national and country=DK, force DK template if found in alt dir
            if ($preferNat && strtoupper($countryQuery) === 'DK') {
                $alt = $this->findNationalInAltDir('DK');
                if ($alt) { $source = $alt; }
            }
                try {
                    // Special-case: explicit DK national request -> force DK template if found
                    if ($preferNat && $countryQuery !== '') {
                        $alt = $this->findNationalInAltDir($countryQuery);
                        if ($alt) { $source = $alt; }
                    }
                    if ($source === null) {
                        $resolver = new \App\Service\FormResolver();
                        $decision = $resolver->decide([
                            'country' => $countryQuery,
                            'operator' => $opName,
                            'product' => $opProd,
                        ]);
                        if (($decision['form'] ?? '') === 'national_claim') {
                            $src = (string)($decision['national']['path'] ?? '');
                            if ($src !== '' && is_file($src)) { $source = $src; }
                        }
                    }
                } catch (\Throwable $e) { /* ignore and fallback below */ }
                if ($source === null) {
                    $source = $this->findOfficialTemplatePath();
                }
            }
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

    // Prefer national mapping if a national template is selected; else fallback to EU mapping
    $natMap = null;
    // Fast-path: if the selected template name looks like the DK form, load the DK map directly
    $dkMapPathOriginal = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'DK' . DIRECTORY_SEPARATOR . 'map_dk_dsb_basic_rejsetidsgaranti.json';
    $dkMapPathClean = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . 'DK' . DIRECTORY_SEPARATOR . 'map_dk_dsb_basic_rejsetidsgaranti_clean.json';
    $dkMapPath = is_file($dkMapPathClean) ? $dkMapPathClean : $dkMapPathOriginal;
    $isDkTemplate = stripos(basename((string)$source), 'dk_rejsetidsgaranti') !== false;
    if ($isDkTemplate && is_file($dkMapPath)) {
        $json = (string)file_get_contents($dkMapPath);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $natMap = $decoded;
            $mapUsed = 'national';
            $mapMeta = (array)($decoded['_meta'] ?? ($decoded['meta'] ?? []));
        }
    }
    if ($natMap === null) {
        $natMap = $this->loadNationalFieldMap($source);
    }
    // If not found, try again using the explicit country parameter (helps when the filename
    // itself does not contain a strong country token).
    if ($natMap === null) {
        $countryQuery = strtoupper((string)($this->request->getQuery('country') ?? ($data['operator_country'] ?? '')));
        if ($countryQuery !== '') {
            $natMap = $this->loadNationalFieldMap($countryQuery);
        }
    }
    // Hard fallback: if DK national form is selected or requested, force-load DK map directly
    // Only force DK map when country is DK or the template name itself is DK
    $preferDk = strtoupper((string)($countryQuery ?? '')) === 'DK';
    // If user explicitly prefers national and DK country, force-load DK map even if detection failed
    if ($preferNat && $preferDk && is_file($dkMapPath)) {
        $json = (string)file_get_contents($dkMapPath);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $natMap = $decoded;
            $mapUsed = 'national';
            $mapMeta = (array)($decoded['_meta'] ?? []);
        }
    }
    if ($isDkTemplate || $preferDk) {
        // Try explicit DK loader if the generic matcher failed
        if ($natMap === null) {
            $natMap = $this->loadNationalFieldMap($dkMapPath);
        }
        if ($natMap === null && is_file($dkMapPath)) {
            $json = (string)file_get_contents($dkMapPath);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $natMap = $decoded;
                $mapMeta = (array)($decoded['_meta'] ?? []);
            }
        }
        // If we have any DK map, force national origin; if not, still fall back to EU below.
        if ($natMap !== null) {
            $mapUsed = 'national';
        }
    }
    // Final DK force: if still no map and DK requested, decode DK map directly
    if ($preferNat && $preferDk && $natMap === null && is_file($dkMapPath)) {
        $json = (string)file_get_contents($dkMapPath);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            // Ensure national map is properly registered so the branch below
            // does not fall back to EU default due to $natMap still being null.
            $natMap = $decoded;
            $mapUsed = 'national';
            $mapMeta = (array)($decoded['_meta'] ?? ($decoded['meta'] ?? []));
        }
    }
    if ($natMap === null) {
        $mapUsed = 'eu_default';
        $map = $this->loadFieldMap() ?: $this->officialFieldMap();
        $mapMeta = is_array($map) && array_key_exists('_meta', $map) && is_array($map['_meta']) ? (array)$map['_meta'] : [];
    } else {
        // Generic national mapping (FR/DE/IT/…)
        $mapUsed = $mapUsed ?? 'national';
        $map = $natMap;
        $mapMeta = (array)($natMap['_meta'] ?? []);
        // Some mapping files only expose "meta" (lowercase) instead of "_meta"
        if (empty($mapMeta) && isset($natMap['meta']) && is_array($natMap['meta'])) {
            $mapMeta = (array)$natMap['meta'];
        }
        // If meta is missing, synthesize minimal info so downstream coordinate conversion works.
        if (empty($mapMeta)) {
            $mapMeta = [
                'file' => basename((string)$source),
                'units' => 'mm',
                'origin' => 'top-left',
            ];
        }
    }
    // FR G30 augmentation: enrich travellers/segments/loyalty and contact fields when using the FR G30 template
    $isFR = (stripos($source, 'fr') !== false) || (stripos((string)($mapMeta['file'] ?? ''), 'FR_') !== false) || (stripos((string)($mapMeta['note'] ?? ''), 'G30') !== false) || (stripos($source, 'sncf') !== false) || (stripos($source, 'g30') !== false);
    if ($isFR) {
        $data = $this->augmentG30Data($data, $formSess, $metaSess);
    }

    // Helper: split ticket/booking reference into digit boxes when the map defines ticket_no_1, ticket_no_2, …
    $applyTicketSplits = function(array &$payload, array $map) {
        $targets = [];
        foreach ($map as $pg => $flds) {
            if (!is_array($flds)) { continue; }
            foreach ($flds as $fname => $cfg) {
                if (is_string($fname) && preg_match('/^ticket_no_(\\d+)/', $fname, $m)) {
                    $targets[(int)$m[1]] = $fname;
                }
            }
        }
        if (empty($targets)) { return; }
        $raw = (string)($payload['ticket_no'] ?? '');
        if ($raw === '') { return; }
        // Remove spaces/tabs/newlines; keep other chars so letters are preserved
        $chars = preg_split('//u', preg_replace('/\\s+/', '', $raw), -1, PREG_SPLIT_NO_EMPTY);
        ksort($targets);
        $i = 0;
        foreach ($targets as $fname) {
            $payload[$fname] = $chars[$i] ?? '';
            $i++;
            if ($i >= count($chars)) { break; }
        }
    };
    // Ensure we have a ticket_no before splitting into boxes: pull from form/meta if missing
    if (!isset($data['ticket_no']) || trim((string)$data['ticket_no']) === '') {
        $fallbackTno = $formSess['ticket_no'] ?? null;
        if ($fallbackTno === null || $fallbackTno === '') {
            $fallbackTno = $metaSess['_auto']['ticket_no']['value'] ?? ($metaSess['_identifiers']['pnr'] ?? null);
        }
        if ($fallbackTno !== null && $fallbackTno !== '') {
            $data['ticket_no'] = (string)$fallbackTno;
        }
    }
    $mapArray = is_array($map) ? $map : [];
    // Split dep_date into dd/mm/yyyy boxes if mapping defines them
    $applyDateSplits = function(array &$payload, array $map, string $field = 'dep_date') use ($formSess, $metaSess) {
        $targets = [];
        foreach ($map as $pg => $flds) {
            if (!is_array($flds)) { continue; }
            foreach ($flds as $fname => $_cfg) {
                if (is_string($fname) && preg_match('/^' . preg_quote($field, '/') . '_(d1|d2|m1|m2|y1|y2|y3|y4)$/', $fname, $m)) {
                    $order = ['d1'=>1,'d2'=>2,'m1'=>3,'m2'=>4,'y1'=>5,'y2'=>6,'y3'=>7,'y4'=>8];
                    $targets[$order[$m[1]]] = $fname;
                }
            }
        }
        if (empty($targets)) { return; }
        $raw = (string)($payload[$field] ?? '');
        if ($raw === '') {
            $raw = (string)($formSess[$field] ?? ($metaSess['_auto'][$field]['value'] ?? ''));
        }
        if ($raw === '') { return; }
        // Normalize to digits only
        if (preg_match('/(\\d{4})[-\\/](\\d{1,2})[-\\/](\\d{1,2})/', $raw, $m)) {
            $digits = sprintf('%02d%02d%04d', (int)$m[3], (int)$m[2], (int)$m[1]);
        } elseif (preg_match('/(\\d{1,2})[-\\/](\\d{1,2})[-\\/](\\d{2,4})/', $raw, $m)) {
            $y = (int)$m[3]; if ($y < 100) { $y += 2000; }
            $digits = sprintf('%02d%02d%04d', (int)$m[1], (int)$m[2], $y);
        } else {
            $digits = preg_replace('/\\D+/', '', $raw);
            if (strlen($digits) === 8) {
                $digits = substr($digits, 6, 2) . substr($digits, 4, 2) . substr($digits, 0, 4); // assume yyyymmdd
            }
        }
        if (strlen($digits) < 8) { return; }
        ksort($targets);
        $chars = str_split($digits);
        $i = 0;
        foreach ($targets as $fname) {
            $payload[$fname] = $chars[$i] ?? '';
            $i++;
            if ($i >= count($chars)) { break; }
        }
    };
    $applyDateSplits($data, $mapArray, 'dep_date');
    $applyTicketSplits($data, $mapArray);

    $isAirTemplate = stripos((string)basename((string)$source), 'air_travel_form') !== false
        || stripos((string)$source, 'form_air_travel') !== false
        || stripos((string)$source, 'air_travel') !== false;
    if ($isAirTemplate) {
        $postcode = trim((string)($data['address_postalCode'] ?? ($data['postcode'] ?? '')));
        $city = trim((string)($data['address_city'] ?? ($data['city'] ?? '')));
        if ((string)($data['postcode_city'] ?? '') === '' && ($postcode !== '' || $city !== '')) {
            $data['postcode_city'] = trim($postcode . ($postcode !== '' && $city !== '' ? ' ' : '') . $city);
        }
        if ((string)($data['signature_name'] ?? '') === '') {
            $fn = trim((string)($data['firstName'] ?? ($data['firstname'] ?? '')));
            $ln = trim((string)($data['lastName'] ?? ($data['surname'] ?? '')));
            $full = trim($fn . ' ' . $ln);
            if ($full !== '') {
                $data['signature_name'] = $full;
            }
        }
        if ((string)($data['signature_date'] ?? '') === '') {
            $data['signature_date'] = date('Y-m-d');
        }
        foreach ([
            'meal_offered' => 'air_meals_offered',
            'hotel_offered' => 'air_hotel_offered',
        ] as $legacyKey => $airKey) {
            if ((string)($data[$airKey] ?? '') === '' && (string)($data[$legacyKey] ?? '') !== '') {
                $data[$airKey] = (string)$data[$legacyKey];
            }
            if ((string)($data[$legacyKey] ?? '') === '' && (string)($data[$airKey] ?? '') !== '') {
                $data[$legacyKey] = (string)$data[$airKey];
            }
        }
        if ((string)($data['flight_number'] ?? '') === '') {
            $flight = trim((string)($data['train_no'] ?? ($data['flight_no'] ?? '')));
            if ($flight !== '') {
                $data['flight_number'] = $flight;
            }
        }
        if ((string)($data['airline'] ?? '') === '' && (string)($data['operator'] ?? '') !== '') {
            $data['airline'] = (string)$data['operator'];
        }
        if ((string)($data['ticket_number'] ?? '') === '' && (string)($data['ticket_no'] ?? '') !== '') {
            $data['ticket_number'] = (string)$data['ticket_no'];
        }
        if ((string)($data['booking_reference'] ?? '') === '' && (string)($data['ticket_no'] ?? '') !== '') {
            $data['booking_reference'] = (string)$data['ticket_no'];
        }
        if ((string)($data['incident_airports'] ?? '') === '') {
            $incidentAirports = trim((string)($data['missed_connection_station'] ?? ''));
            if ($incidentAirports === '') {
                $incidentAirports = trim((string)($data['dep_station'] ?? '') . ' ' . (string)($data['arr_station'] ?? ''));
            }
            if ($incidentAirports !== '') {
                $data['incident_airports'] = $incidentAirports;
            }
        }
    }

    // If developer asked for session debug, include template and map details now that they are resolved
    if ($peekSess && is_array($debugPayload)) {
        $debugPayload['template'] = [
            'source' => $source,
            'source_basename' => basename((string)$source),
            'map_origin' => $mapUsed,
            'map_meta' => $mapMeta,
        ];
        $this->disableAutoRender();
        $this->response = $this->response->withType('json')->withStringBody(
            (string)json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        return;
    }
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
        // Add supporting keys that influence derived fields (needed for EU page 1 checkboxes and page 3 claims)
        foreach (['incident_main','missed_connection','reason_delay','reason_cancellation','reason_missed_conn','remedyChoice'] as $support) {
            $mapFields[$support] = true;
        }
        // Also scan for PMR and related nested fields that may be stored under cards in flow.form/meta
    foreach (['pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing','pmrQBooked','pmrQDelivered','pmrQPromised','pmr_facility_details','missed_connection_station','delay_min_eu','remedyChoice',
                  // Reroute follow-up questions (TRIN 4)
                  'reroute_info_within_100min','self_purchased_new_ticket','reroute_extra_costs','downgrade_occurred',
                  // Assistance self-paid breakdowns (TRIN 5)
                  'meal_self_paid_amount','meal_self_paid_currency',
                  'hotel_self_paid_amount','hotel_self_paid_currency','hotel_self_paid_nights',
                  'blocked_self_paid_amount','blocked_self_paid_currency',
                  'alt_self_paid_amount','alt_self_paid_currency'] as $support) {
            $mapFields[$support] = true;
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
        // Recursive scan for nested occurrences of target keys inside form/meta session structures (e.g., pmrFlowCard -> Handicap)
        $targetKeys = array_keys($mapFields);
        $scanNested = function($arr) use (&$scanNested, $targetKeys) {
            $found = [];
            if (!is_array($arr)) { return $found; }
            foreach ($arr as $kk => $vv) {
                if (in_array((string)$kk, $targetKeys, true)) {
                    $found[(string)$kk] = $vv;
                }
                if (is_array($vv)) {
                    $child = $scanNested($vv);
                    if (!empty($child)) { $found = $found + $child; }
                }
            }
            return $found;
        };
        $nestedForm = $scanNested($formSess);
        $nestedMeta = $scanNested($metaSess);
        foreach ([$nestedForm, $nestedMeta] as $bucket) {
            foreach ($bucket as $kk => $vv) {
                if (!array_key_exists($kk, $data) || $data[$kk] === null || $data[$kk] === '') {
                    $data[$kk] = $vv;
                }
            }
        }
        // Also derive incident flags from nested meta structure if present (and from flow.incident bucket)
        if (empty($data['incident_main'])) {
            $im = '';
            if (isset($metaSess['incident']) && is_array($metaSess['incident'])) {
                $im = (string)($metaSess['incident']['main'] ?? '');
            }
            if ($im === '' && !empty($incidentSess['main'])) {
                $im = (string)$incidentSess['main'];
            }
            if ($im !== '') { $data['incident_main'] = $im; }
        }
        if (!isset($data['missed_connection'])) {
            $mc = null;
            if (isset($metaSess['incident']) && is_array($metaSess['incident'])) {
                $mc = $metaSess['incident']['missed'] ?? null;
            }
            if ($mc === null && array_key_exists('missed', $incidentSess)) {
                $mc = $incidentSess['missed'];
            }
            if (!empty($mc)) { $data['missed_connection'] = true; }
        }
        if (!isset($data['reason_missed_conn'])) {
            $hasMc = !empty($data['missed_connection']) || !empty($formSess['missed_connection_station']) || !empty($data['missed_connection_station']);
            if ($hasMc) { $data['reason_missed_conn'] = true; }
        }
        // Alias / synonym mapping for fields present only in nested session but not in PDF field map
        $normalizeYesNo = function($v) {
            $s = mb_strtolower(trim((string)$v));
            if ($s === 'ja' || $s === 'yes' || $s === 'true' || $s === '1') { return true; }
            if ($s === 'nej' || $s === 'no' || $s === 'false' || $s === '0') { return false; }
            return $v; // leave original (for text fields)
        };
        // single_booking_reference => shared_pnr_scope (if explicit yes and shared_pnr_scope missing)
        if (!isset($data['shared_pnr_scope']) && isset($data['single_booking_reference'])) {
            $val = $normalizeYesNo($data['single_booking_reference']);
            if ($val === true) { $data['shared_pnr_scope'] = true; }
        }
        // seller_type_agency => seller_type_operator (negative means not operator; positive may confirm operator involvement)
        if (!isset($data['seller_type_operator']) && isset($data['seller_type_agency'])) {
            $val = $normalizeYesNo($data['seller_type_agency']);
            // Only set true if agency value indicates yes; ignore 'Nej'
            if ($val === true) { $data['seller_type_operator'] = true; }
        }
        // pm_bike_involved => bike_caused_issue if not already set
        if (!isset($data['bike_caused_issue']) && isset($data['pm_bike_involved'])) {
            $val = $normalizeYesNo($data['pm_bike_involved']);
            if ($val === true) { $data['bike_caused_issue'] = true; }
        }
        // pmr_promised_missing => pmrQPromised (boolean)
        if (!isset($data['pmrQPromised']) && isset($data['pmr_promised_missing'])) {
            $val = $normalizeYesNo($data['pmr_promised_missing']);
            $data['pmrQPromised'] = $val === true ? true : ($val === false ? false : $val);
        }
        // pmr_delivered_status => pmrQDelivered (translate partial, full, none)
        if (!isset($data['pmrQDelivered']) && isset($data['pmr_delivered_status'])) {
            $st = trim((string)$data['pmr_delivered_status']);
            if ($st !== '') { $data['pmrQDelivered'] = $st; }
        }
        // If facility details provided but pmrQPromised not set, infer TRUE
        if (!isset($data['pmrQPromised']) && !empty($data['pmr_facility_details'])) {
            $text = trim((string)$data['pmr_facility_details']);
            if ($text !== '') { $data['pmrQPromised'] = true; }
        }
        // pmr_booked => pmrQBooked (boolean booking of assistance before trip)
        if (!isset($data['pmrQBooked']) && isset($data['pmr_booked'])) {
            $val = $normalizeYesNo($data['pmr_booked']);
            // Preserve original textual detail if not a simple yes/no
            if ($val === true) { $data['pmrQBooked'] = true; }
            elseif ($val === false) { $data['pmrQBooked'] = false; }
            else { $data['pmrQBooked'] = $data['pmr_booked']; }
        }
        // Assistance aliasing: map self-paid fields into legacy aggregate keys for summary fallback
        if (!isset($data['expense_breakdown_meals']) && isset($data['meal_self_paid_amount'])) {
            $data['expense_breakdown_meals'] = $data['meal_self_paid_amount'];
        }
        if (!isset($data['expense_hotel']) && isset($data['hotel_self_paid_amount'])) {
            $data['expense_hotel'] = $data['hotel_self_paid_amount'];
        }
        if (!isset($data['expense_breakdown_hotel_nights']) && isset($data['hotel_self_paid_nights'])) {
            $data['expense_breakdown_hotel_nights'] = $data['hotel_self_paid_nights'];
        }
        if (!isset($data['expense_breakdown_local_transport']) && isset($data['blocked_self_paid_amount'])) {
            $data['expense_breakdown_local_transport'] = $data['blocked_self_paid_amount'];
        }
        if (!isset($data['expense_alt_transport']) && isset($data['alt_self_paid_amount'])) {
            $data['expense_alt_transport'] = $data['alt_self_paid_amount'];
        }
        // pmr_booked_detail: append to pmr_facility_details if available
        if (isset($data['pmr_booked_detail'])) {
            $detail = trim((string)$data['pmr_booked_detail']);
            if ($detail !== '') {
                $base = trim((string)($data['pmr_facility_details'] ?? ''));
                $data['pmr_facility_details'] = $base === '' ? $detail : ($base . ' | ' . $detail);
            }
        }
        // Special-case: TRIN 7 exclusives derived from remedyChoice if only present in session
        if (!isset($data['remedy_cancel_return']) && isset($formSess['remedyChoice']) && !isset($data['remedyChoice'])) {
            $data['remedyChoice'] = $formSess['remedyChoice'];
        }
        // If remedyChoice is still missing, infer it from chosenPath (flow rules) if present
        if (empty($data['remedyChoice'])) {
            $chosenPath = (string)($data['chosenPath'] ?? ($formSess['chosenPath'] ?? ($metaSess['chosenPath'] ?? '')));
            $chosenPath = strtolower($chosenPath);
            $mapChoice = [
                'refund' => 'refund_return',
                'refund_return' => 'refund_return',
                'reroute_soonest' => 'reroute_soonest',
                'reroute_later' => 'reroute_later',
            ];
            if (isset($mapChoice[$chosenPath])) {
                $data['remedyChoice'] = $mapChoice[$chosenPath];
            }
        }
        // Normalize reroute info timing flag if coming from rules fixtures
        if (!isset($data['reroute_info_within_100min']) && isset($formSess['rerouteInfoOfferedWithin100Min'])) {
            $data['reroute_info_within_100min'] = (bool)$formSess['rerouteInfoOfferedWithin100Min'];
        }
        if (!isset($data['reroute_info_within_100min']) && isset($metaSess['rerouteInfoOfferedWithin100Min'])) {
            $data['reroute_info_within_100min'] = (bool)$metaSess['rerouteInfoOfferedWithin100Min'];
        }
        if (!isset($data['reroute_info_within_100min']) && isset($data['rerouteInfoOfferedWithin100Min'])) {
            $data['reroute_info_within_100min'] = (bool)$data['rerouteInfoOfferedWithin100Min'];
        }
        // Compose full_name for national templates if missing
        if (empty($data['full_name'])) {
            $fn = (string)($formSess['firstName'] ?? $data['firstName'] ?? '');
            $ln = (string)($formSess['lastName'] ?? $data['lastName'] ?? '');
            $name = trim($fn . ' ' . $ln);
            if ($name !== '') { $data['full_name'] = $name; }
        }
        }
        if ($isFerryClaimLetter) {
            $this->disableAutoRender();
            $pdfBody = $this->buildFerryClaimLetterPdf($data, $formSess, $metaSess, $incidentSess, $computeSess, $flagsSess);
            $this->response = $this->response->withType('pdf')->withStringBody($pdfBody);
            return;
        }
        if ($isBusClaimLetter) {
            $this->disableAutoRender();
            $pdfBody = $this->buildBusClaimLetterPdf($data, $formSess, $metaSess, $incidentSess, $computeSess, $flagsSess);
            $this->response = $this->response->withType('pdf')->withStringBody($pdfBody);
            return;
        }
        $debug = (bool)$this->request->getQuery('debug');
    $dx = (float)($this->request->getQuery('dx') ?? 0);
    $dy = (float)($this->request->getQuery('dy') ?? 0);
    // Optional per-box vertical nudge for quick testing: ?boxdy=-50
    $boxDy = (float)($this->request->getQuery('boxdy') ?? 0);

    // Developer: return final PDF-ready snapshot after backfill/aliasing when requested
    $finalReq = (string)($this->request->getQuery('final') ?? '') === '1';
    if ($finalReq) {
        $dataForPdf = $data;
        $incident = strtolower((string)($dataForPdf['incident_main'] ?? ''));
        // Derive reason_* with same rules as rendering loop
        $incFlat = preg_replace('/[^a-z]/', '', strtolower((string)($dataForPdf['incident_main'] ?? '')));
        $derive = function($current, $want) {
            if ($current === null) { return (bool)$want; }
            if (is_bool($current)) { return $current || (bool)$want; }
            $s = mb_strtolower(trim((string)$current));
            $isEmpty = ($s === '' || $s === '0' || $s === 'nej' || $s === 'no' || $s === 'false');
            return $isEmpty ? (bool)$want : ($current ? true : (bool)$want);
        };
        $wantDelay = (strpos($incFlat, 'delay') !== false) || !empty($data['reason_delay']);
        $wantCancel = (strpos($incFlat, 'cancel') !== false) || !empty($data['reason_cancellation']);
        $wantMiss = (strpos($incFlat, 'missed') !== false) || !empty($data['missed_connection']) || !empty($data['reason_missed_conn']);
        if (!$wantDelay && ($wantMiss) && isset($data['delay_min_eu']) && is_numeric($data['delay_min_eu']) && (float)$data['delay_min_eu'] > 0) {
            $wantDelay = true;
        }
        $dataForPdf['reason_delay'] = $derive($dataForPdf['reason_delay'] ?? null, $wantDelay);
        $dataForPdf['reason_cancellation'] = $derive($dataForPdf['reason_cancellation'] ?? null, $wantCancel);
        $dataForPdf['reason_missed_conn'] = $derive($dataForPdf['reason_missed_conn'] ?? null, $wantMiss);

        // Remedies exclusivity
        $choice = (string)($data['remedyChoice'] ?? '');
        if ($choice === '') {
            if (!empty($data['reroute_later_at_choice'])) { $choice = 'reroute_later'; }
            elseif (!empty($data['reroute_same_conditions_soonest'])) { $choice = 'reroute_soonest'; }
            elseif (!empty($data['refund_requested'])) { $choice = 'refund_return'; }
        }
        $dataForPdf['remedy_cancel_return'] = ($choice === 'refund_return');
        $dataForPdf['remedy_reroute_soonest'] = ($choice === 'reroute_soonest');
        $dataForPdf['remedy_reroute_later'] = ($choice === 'reroute_later');

        // Top-level flags: refund vs compensation per current policy
        $choiceForTop = (string)($data['remedyChoice'] ?? '');
        if ($choiceForTop === 'refund_return' || !empty($data['refund_requested'])) {
            $dataForPdf['main_refund'] = true;
        } elseif ($choiceForTop === 'reroute_soonest' || $choiceForTop === 'reroute_later') {
            $dataForPdf['main_compensation'] = true;
        }

        // TRIN 5 group marker
        if (!isset($dataForPdf['assistA_now'])) {
            $anyAssist = !empty($data['meal_offered']) || !empty($data['hotel_offered']) || !empty($data['blocked_train_alt_transport']);
            if ($anyAssist) { $dataForPdf['assistA_now'] = true; }
        }

        // Build payload similar to ?session=1 but with final snapshot
        $flatten = function($arr, $prefix = '') use (&$flatten) {
            $out = [];
            if (!is_array($arr)) { return $out; }
            foreach ($arr as $k => $v) {
                $key = $prefix === '' ? (string)$k : ($prefix . '.' . (string)$k);
                if (is_array($v)) { $out += $flatten($v, $key); } else { $out[$key] = $v; }
            }
            return $out;
        };
        // Rebuild selected keys list for consistency with session=1
        $keys = [
            'incident_main','missed_connection','missed_connection_station','delay_min_eu',
            'reason_delay','reason_cancellation','reason_missed_conn','pmr_user','pmr_booked','pmrQBooked','pmrQDelivered','pmrQPromised','pmr_facility_details',
            'remedyChoice','chosenPath','reroute_info_within_100min','self_purchased_new_ticket','reroute_extra_costs','downgrade_occurred',
            'meal_offered','hotel_offered','blocked_train_alt_transport','alt_transport_provided',
            'meal_self_paid_amount','meal_self_paid_currency','hotel_self_paid_amount','hotel_self_paid_currency','hotel_self_paid_nights',
            'blocked_self_paid_amount','blocked_self_paid_currency','alt_self_paid_amount','alt_self_paid_currency',
            'preinformed_disruption','preinfo_channel','realtime_info_seen'
        ];
        $sel = [];
        foreach ($keys as $k) { $sel[$k] = $data[$k] ?? $formSess[$k] ?? $metaSess[$k] ?? null; }
        $payload = [
            'phpSessionId' => method_exists($session, 'id') ? $session->id() : null,
            'now' => date('c'),
            'final' => $dataForPdf,
            'selected' => $sel,
            'data' => $data,
            'flow' => ['form' => $formSess, 'meta' => $metaSess],
            'flat' => ['form' => $flatten($formSess), 'meta' => $flatten($metaSess)],
        ];
        $this->disableAutoRender();
        $this->response = $this->response->withType('json')->withStringBody((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }

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

    // If incident_main is missing (user opened official PDF direkte / refresh uden session merge), fors+©g at inferere den
    if (empty($data['incident_main'])) {
        $guess = '';
        if (!empty($data['reason_delay'])) { $guess = 'delay'; }
        elseif (!empty($data['reason_cancellation'])) { $guess = 'cancellation'; }
        elseif (!empty($data['missed_connection']) || !empty($data['reason_missed_conn']) || !empty($data['missed_connection_station'])) { $guess = 'missed_connection'; }
        if ($guess !== '') { $data['incident_main'] = $guess; }
    }
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
    // Propagate ticket_no digit splits into snapshot as well (uses the same helper as main data)
    $applyTicketSplits($dataSnap, $mapArray);

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
            // Derive reason_* with override from incident_main even if existing values are explicitly 'nej'/'0'/false
            $incFlat = preg_replace('/[^a-z]/', '', strtolower((string)($dataForPdf['incident_main'] ?? '')));
            $derive = function($current, $want) {
                // Treat '', null, false, '0', 'nej', 'no', 'false' as empty
                if ($current === null) { return (bool)$want; }
                if (is_bool($current)) { return $current || (bool)$want; }
                $s = mb_strtolower(trim((string)$current));
                $isEmpty = ($s === '' || $s === '0' || $s === 'nej' || $s === 'no' || $s === 'false');
                return $isEmpty ? (bool)$want : ($current ? true : (bool)$want);
            };
            $wantDelay = (strpos($incFlat, 'delay') !== false) || !empty($data['reason_delay']);
            $wantCancel = (strpos($incFlat, 'cancel') !== false) || !empty($data['reason_cancellation']);
            $wantMiss = (strpos($incFlat, 'missed') !== false) || !empty($data['missed_connection']) || !empty($data['reason_missed_conn']);
            // Cross-cause: if missed connection and there is a positive delay minutes value, also tick delay
            if (!$wantDelay && ($wantMiss) && isset($data['delay_min_eu']) && is_numeric($data['delay_min_eu']) && (float)$data['delay_min_eu'] > 0) {
                $wantDelay = true;
            }
            $dataForPdf['reason_delay'] = $derive($dataForPdf['reason_delay'] ?? null, $wantDelay);
            $dataForPdf['reason_cancellation'] = $derive($dataForPdf['reason_cancellation'] ?? null, $wantCancel);
            $dataForPdf['reason_missed_conn'] = $derive($dataForPdf['reason_missed_conn'] ?? null, $wantMiss);

            // No extrapolation: reasons are driven by explicit incident_main / reason_* only

            // TRIN 7: Exclusive remedies (Option A) ÔÇô only one checkbox can be marked based on remedyChoice
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
                'safetyMisconduct' => 'Adf+ªrd i toget',
                'forbiddenItemsOrAnimals' => 'H+Ñndbagage/dyr/genstande',
                'customsRulesBreached' => 'Administrative forskrifter',
                'operatorStampedDisruptionProof' => 'Operator-dokumentation',
                'through_ticket_disclosure' => 'Disclosure: gennemg+Ñende billet',
                'single_txn_operator' => 'Disclosure: enkelt transaktion (operat+©r)',
                'single_txn_retailer' => 'Disclosure: enkelt transaktion (forhandler)',
                'separate_contract_notice' => 'Disclosure: separate kontrakter',
                'mct_realistic' => 'MCT realistisk',
                'remedy_cancel_return' => 'Refusion (valgt)',
                'remedy_reroute_soonest' => 'Reroute (snarest)',
                'remedy_reroute_later' => 'Reroute (senere)',
                'meal_offered' => 'M+Ñltid tilbudt',
                'hotel_offered' => 'Hotel tilbudt',
                'request_refund' => 'Request: Refusion',
                'request_comp_60' => 'Request: Kompensation 60+',
                'request_comp_120' => 'Request: Kompensation 120+',
                'request_expenses' => 'Request: Udgifter',
                'pmr_user' => 'PMR bruger',
                'complaint_already_filed' => 'Klage tidligere indsendt',
                'submit_via_official_channel' => 'Indsend via officiel kanal',
                'bike_res_required' => 'Cykelreservation kr+ªvet',
            ];

            // Derive top-level EU Section 4 selections
            // Policy (2025-11):
            //   a) If remedyChoice = refund_return -> only main_refund ticked; NEVER main_compensation (Art. 19 excludes compensation when refund chosen).
            //   b) If remedyChoice = reroute_soonest or reroute_later -> always tick main_compensation (passenger retains compensation entitlement for qualifying delay bands).
            //   c) Ignore request_comp_* and compensationBand for now; entitlement is implied by reroute choice.
            //   d) main_expenses unchanged (explicit request_expenses OR any expense amount > 0).
            $amounts = [
                (float)($data['expense_breakdown_meals'] ?? $data['expense_meals'] ?? 0),
                (float)($data['expense_breakdown_hotel_nights'] ?? 0) > 0 ? 0.01 : (float)($data['expense_hotel'] ?? 0),
                (float)($data['expense_breakdown_local_transport'] ?? $data['expense_alt_transport'] ?? 0),
                (float)($data['expense_breakdown_other_amounts'] ?? $data['expense_other'] ?? 0),
            ];
            // Allow explicit URL overrides: ?main_refund=0 prevents auto-setting even if remedyChoice matches
            $normBool = function($v) {
                $s = strtolower(trim((string)$v));
                if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'ja') { return true; }
                if ($s === '0' || $s === 'false' || $s === 'no' || $s === 'nej') { return false; }
                return null; // ambiguous / not provided
            };

            $expRefund = array_key_exists('main_refund', $data);
            $expComp = array_key_exists('main_compensation', $data);
            $expExpenses = array_key_exists('main_expenses', $data);
            $valRefund = $expRefund ? $normBool($data['main_refund']) : null;
            $valComp = $expComp ? $normBool($data['main_compensation']) : null;
            $valExpenses = $expExpenses ? $normBool($data['main_expenses']) : null;

            $hasExpenses = array_sum($amounts) > 0.0 || !empty($data['request_expenses']);
            if ($valExpenses === true) {
                $dataForPdf['main_expenses'] = true; // forced on
            } elseif ($valExpenses === false) {
                // forced off
            } elseif ($hasExpenses) {
                $dataForPdf['main_expenses'] = true;
            }

            $choiceForTop = (string)($data['remedyChoice'] ?? '');
            if ($valRefund === true) {
                $dataForPdf['main_refund'] = true; // forced on
            } elseif ($valRefund === false) {
                // forced off
            } else {
                if ($choiceForTop === 'refund_return' || !empty($data['refund_requested'])) {
                    $dataForPdf['main_refund'] = true;
                }
            }
            if ($valComp === true) {
                $dataForPdf['main_compensation'] = true; // forced on
            } elseif ($valComp === false) {
                // forced off
            } else {
                if ($choiceForTop === 'reroute_soonest' || $choiceForTop === 'reroute_later') {
                    $dataForPdf['main_compensation'] = true;
                }
            }

            // Group A assistance marker for TRIN 5: set when any of the immediate assistance items are affirmative
            if (!isset($dataForPdf['assistA_now'])) {
                $anyAssist = !empty($data['meal_offered']) || !empty($data['hotel_offered']) || !empty($data['blocked_train_alt_transport']);
                if ($anyAssist) { $dataForPdf['assistA_now'] = true; }
            }

            // Question text map and TRIN grouping for page 5 (show question + answer grouped by TRIN)
            $questionText = [
                // TRIN 1/2 (Status/H+ªndelse) ÔÇö not shown on consolidated page (filtered), but kept for completeness
                'hasValidTicket' => 'Havde du en gyldig billet?',
                'safetyMisconduct' => 'Var der adf+ªrd i toget, som p+Ñvirkede situationen?',
                'forbiddenItemsOrAnimals' => 'Var der forbudte genstande eller dyr med?',
                'customsRulesBreached' => 'Var told-/administrative regler overtr+Ñdt?',
                'operatorStampedDisruptionProof' => 'Har operat+©ren stemplet dokumentation for forstyrrelsen?',
                // TRIN 3 (Art. 12 + Art. 9)
                'through_ticket_disclosure' => 'Blev det oplyst at billetten var gennemg+Ñende?',
                'single_txn_operator' => 'Var k+©bet en enkelt transaktion med operat+©ren?',
                'single_txn_retailer' => 'Var k+©bet en enkelt transaktion med forhandleren?',
                'separate_contract_notice' => 'Blev separate kontrakter oplyst?',
                'shared_pnr_scope' => 'Var alle billetter udstedt under samme bookingnummer/PNR?',
                'seller_type_operator' => 'Var det en jernbanevirksomhed der solgte dig hele rejsen?',
                // MCT wording consolidated to a single phrasing below
                'one_contract_schedule' => 'Var hele rejseplanen under +®n kontrakt?',
                'contact_info_provided' => 'Blev kontaktinfo oplyst f+©r k+©b?',
                'responsibility_explained' => 'Blev ansvar/ansvarsfordeling forklaret?',
                'continue_national_rules' => 'Forts+ªtter sagen under nationale regler?',
                'info_requested_pre_purchase' => '+ÿnskede du info f+©r k+©b?',
                'coc_acknowledged' => 'Er CoC (conditions of carriage) anerkendt?',
                'civ_marking_present' => 'Er CIV-markering til stede?',
                'fastest_flag_at_purchase' => 'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                'mct_realistic' => 'Var minimumsskiftetiden realistisk (missed station)?',
                'alts_shown_precontract' => 'S+Ñ du alternative forbindelser ved k+©bet?',
                'multiple_fares_shown' => 'Fik du vist flere prisvalg for samme afgang?',
                'cheapest_highlighted' => "Var 'billigste pris' markeret/anbefalet?",
                // TRIN 5 (Assistance og udgifter)
                'meal_offered' => 'Blev der tilbudt m+Ñltid?',
                'hotel_offered' => 'Blev der tilbudt hotelovernatning?',
                'overnight_needed' => 'Var overnatning n+©dvendig?',
                'blocked_train_alt_transport' => 'Blev der tilbudt alternativ transport pga. blokeret tog?',
                'alt_transport_provided' => 'Blev alternativ transport leveret?',
                'extra_expense_upload' => 'Har du uploadet kvitteringer for udgifter?',
                'delay_confirmation_received' => 'Modtog du bekr+ªftelse p+Ñ forsinkelsen?',
                'delay_confirmation_upload' => 'Har du uploadet bekr+ªftelse p+Ñ forsinkelsen?',
                'extraordinary_claimed' => 'Har du angivet ekstraordin+ªrt krav?',
                // TRIN 3 (Afhj+ªlpning og krav)
                'request_refund' => '+ÿnsker du refusion?',
                'request_comp_60' => '+ÿnsker du kompensation for 60+ min?',
                'request_comp_120' => '+ÿnsker du kompensation for 120+ min?',
                'request_expenses' => '+ÿnsker du d+ªkning af udgifter?',
                // TRIN 4 (Afhj+ªlpning) ÔÇô Refusion / Oml+ªgning
                'trip_cancelled_return_to_origin' => '+ÿnsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?',
                'refund_requested' => 'Har du allerede anmodet om refusion?',
                'refund_form_selected' => 'Hvis ja, hvilken form for refusion?',
                'reroute_same_conditions_soonest' => '+ÿnsker du oml+ªgning p+Ñ tilsvarende vilk+Ñr ved f+©rst givne lejlighed?',
                'reroute_later_at_choice' => '+ÿnsker du oml+ªgning til et senere tidspunkt efter eget valg?',
                'reroute_info_within_100min' => $transportMode === 'air' ? 'Tilboed flyselskabet refusion eller ombooking efter article 8?' : 'Er du blevet informeret om mulighederne for oml+ªgning inden for 100 minutter efter planlagt afgang? (Art. 18(3))',
                'self_purchased_new_ticket' => $transportMode === 'air' ? 'Maatte du selv arrangere ombooking eller videre rejse?' : 'K+©ber du selv en ny billet for at komme videre?',
                'reroute_extra_costs' => 'Kommer oml+ªgningen til at medf+©re ekstra udgifter for dig? (h+©jere klasse/andet transportmiddel)?',
                'downgrade_occurred' => 'Er du blevet nedklassificeret eller regner med at blive det pga. oml+ªgningen?',
                'pmr_user' => 'Har du et handicap eller nedsat mobilitet, som kr+ªvede assistance?',
                'pmr_booked' => 'Var PMR-booking foretaget?',
                'pmr_delivered_status' => 'Er PMR-levering bekr+ªftet?',
                'pmr_promised_missing' => 'Manglede lovede PMR-faciliteter?',
                'complaint_channel_seen' => 'S+Ñ du klagekanalen?',
                'complaint_already_filed' => 'Er der tidligere indsendt klage?',
                'submit_via_official_channel' => 'Sendes ind via officiel kanal?',
                'bike_res_required' => 'Var cykelreservation p+Ñkr+ªvet?',
                'bike_followup_offer' => 'Fik du opf+©lgende tilbud for cykel?',
                // TRIN 3 (PMR/handicap)
                'pmrQBooked' => 'Bestilte du assistance f+©r rejsen?',
                'pmrQDelivered' => 'Blev den bestilte assistance leveret?',
                'pmrQPromised' => 'Manglede der PMR-faciliteter, som var lovet f+©r k+©bet?',
                'pmr_facility_details' => 'Hvilke faciliteter manglede? (rampe, skiltning, lift ÔÇª)',
                // TRIN 3 (Cykel)
                'bike_was_present' => 'Havde du en cykel med p+Ñ rejsen?',
                'bike_caused_issue' => 'Var det cyklen eller h+Ñndteringen af cyklen, der har forsinket dig?',
                'bike_reservation_made' => 'Havde du reserveret plads til en cykel?',
                'bike_reservation_required' => 'Var det et tog, hvor der ikke kr+ªvedes cykelreservation?',
                'bike_denied_boarding' => 'Blev du n+ªgtet at tage cyklen med?',
                'bike_refusal_reason_provided' => 'Blev du informeret om, hvorfor du ikke m+Ñtte tage cyklen med?',
                'bike_refusal_reason_type' => 'Hvad var begrundelsen for afvisningen?',
                // TRIN 3 (Billetpriser og fleksibilitet)
                'fare_flex_type' => 'K+©bstype (fleksibilitet)',
                'train_specificity' => 'G+ªlder billetten kun for specifikt tog?',
                // TRIN 3 (Klasse og reserverede billetter)
                'fare_class_purchased' => 'Hvilken klasse var k+©bt?',
                'class_delivered_status' => 'Fik du den klasse, du betalte for?',
                'berth_seat_type' => 'Var der reserveret plads/kupe/ligge/sove?',
                'reserved_amenity_delivered' => 'Blev reserveret plads/ligge/sove leveret?',
                // TRIN 3 (Afbrydelser oplyst f+©r k+©b)
                'preinformed_disruption' => 'Var der meddelt afbrydelse/forsinkelse f+©r dit k+©b?',
                'preinfo_channel' => 'Hvis ja: Hvor blev det vist?',
                'realtime_info_seen' => 'S+Ñ du realtime-opdateringer under rejsen?',
            ];

            // Optional: field-to-article/subsection mapping for subheaders inside each TRIN on the consolidated page
            // Keep labels concise; prefer article numbers where applicable, else use a short domain label
            $fieldArticle = [
                // TRIN 3 -À Art. 12 ÔÇö through tickets, responsibility, contract scope
                'through_ticket_disclosure' => 'Art. 12',
                'single_txn_operator' => 'Art. 12',
                'single_txn_retailer' => 'Art. 12',
                'separate_contract_notice' => 'Art. 12',
                'shared_pnr_scope' => 'Art. 12',
                'seller_type_operator' => 'Art. 12',
                'one_contract_schedule' => 'Art. 12',
                'responsibility_explained' => 'Art. 12',
                'contact_info_provided' => 'Art. 12',
                // TRIN 3 -À Art. 9(1) ÔÇö precontract info, options, fares
                'info_requested_pre_purchase' => 'Art. 9(1)',
                'coc_acknowledged' => 'Art. 9(1)',
                'civ_marking_present' => 'Art. 9(1)',
                'fastest_flag_at_purchase' => 'Art. 9 (1) Hurtigste rejse',
                'mct_realistic' => 'Art. 9 (1) Hurtigste rejse',
                'alts_shown_precontract' => 'Art. 9 (1) Hurtigste rejse',
                'multiple_fares_shown' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                'cheapest_highlighted' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                // TRIN 3 -À PMR (Art. 21-24)
                'pmr_user' => 'PMR (Art. 21-24)',
                'pmr_booked' => 'PMR (Art. 21-24)',
                'pmrQBooked' => 'PMR (Art. 21-24)',
                'pmrQDelivered' => 'PMR (Art. 21-24)',
                'pmrQPromised' => 'PMR (Art. 21-24)',
                'pmr_facility_details' => 'PMR (Art. 21-24)',
                // TRIN 3 -À Cykel (Art. 6)
                'bike_res_required' => 'Cykel (Art. 6)',
                'bike_followup_offer' => 'Cykel (Art. 6)',
                'bike_was_present' => 'Cykel (Art. 6)',
                'bike_caused_issue' => 'Cykel (Art. 6)',
                'bike_reservation_made' => 'Cykel (Art. 6)',
                'bike_reservation_required' => 'Cykel (Art. 6)',
                'bike_denied_boarding' => 'Cykel (Art. 6)',
                'bike_refusal_reason_provided' => 'Cykel (Art. 6)',
                'bike_refusal_reason_type' => 'Cykel (Art. 6)',
                // TRIN 3 -À Billetpriser og fleksibilitet (under Art. 9 (1))
                'fare_flex_type' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                'train_specificity' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                // TRIN 3 -À Klasse og reserverede billetter (under Art. 9 (1))
                'fare_class_purchased' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'class_delivered_status' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'berth_seat_type' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'reserved_amenity_delivered' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                // TRIN 3 -À Information (under Art. 9 (1))
                'preinformed_disruption' => 'Art. 9 (1) -À Information',
                'preinfo_channel' => 'Art. 9 (1) -À Information',
                'realtime_info_seen' => 'Art. 9 (1) -À Information',
                // TRIN 4 -À Afhj+ªlpning (Refusion / Oml+ªgning)
                // Refusion
                'trip_cancelled_return_to_origin' => 'Refusion',
                'refund_requested' => 'Refusion',
                'refund_form_selected' => 'Refusion',
                // Oml+ªgning
                'reroute_same_conditions_soonest' => 'Oml+ªgning',
                'reroute_later_at_choice' => 'Oml+ªgning',
                'reroute_info_within_100min' => $transportMode === 'air' ? 'Article 8' : 'Oml+ªgning',
                'self_purchased_new_ticket' => $transportMode === 'air' ? 'Article 8' : 'Oml+ªgning',
                'reroute_extra_costs' => 'Oml+ªgning',
                'downgrade_occurred' => 'Oml+ªgning',
                // TRIN 5 -À Assistance og udgifter (Art. 20)
                'meal_offered' => 'Assistance (Art. 20)',
                'hotel_offered' => 'Assistance (Art. 20)',
                'overnight_needed' => 'Assistance (Art. 20)',
                'blocked_train_alt_transport' => 'Assistance (Art. 20)',
                'alt_transport_provided' => 'Assistance (Art. 20)',
                'delay_confirmation_received' => 'Dokumentation',
                'delay_confirmation_upload' => 'Dokumentation',
                'extra_expense_upload' => 'Udgifter (Art. 20)',
                'extraordinary_claimed' => 'Udgifter (Art. 20)',
                // Requests hooks ÔÇö keep under a generic entitlement label
                'request_refund' => 'Entitlement',
                'request_comp_60' => 'Entitlement',
                'request_comp_120' => 'Entitlement',
                'request_expenses' => 'Entitlement',
            ];

            $trinForField = [
                // TRIN 1 (Status) ÔÇö excluded by [4,5,6] filter
                'hasValidTicket' => 1,'safetyMisconduct' => 1,'forbiddenItemsOrAnimals' => 1,'customsRulesBreached' => 1,'operatorStampedDisruptionProof' => 1,
                // TRIN 4 (Afhj+ªlpning og krav)
                'trip_cancelled_return_to_origin' => 4,
                'refund_requested' => 4,
                'refund_form_selected' => 4,
                'self_purchased_new_ticket' => 4,
                'reroute_same_conditions_soonest' => 4,
                'reroute_later_at_choice' => 4,
                'reroute_info_within_100min' => 4,
                'reroute_extra_costs' => 4,
                'downgrade_occurred' => 4,
                // TRIN 3 (Art.12 + Art.9)
                'through_ticket_disclosure' => 3,'single_txn_operator' => 3,'single_txn_retailer' => 3,'separate_contract_notice' => 3,'shared_pnr_scope' => 3,'seller_type_operator' => 3,
                'mct_realistic' => 3,'one_contract_schedule' => 3,'contact_info_provided' => 3,'responsibility_explained' => 3,'continue_national_rules' => 3,
                'info_requested_pre_purchase' => 3,'coc_acknowledged' => 3,'civ_marking_present' => 3,'fastest_flag_at_purchase' => 3,'alts_shown_precontract' => 3,'multiple_fares_shown' => 3,'cheapest_highlighted' => 3,
                // TRIN 5 (Assistance og udgifter)
                'meal_offered' => 5,'hotel_offered' => 5,'overnight_needed' => 5,'blocked_train_alt_transport' => 5,'alt_transport_provided' => 5,'extra_expense_upload' => 5,'delay_confirmation_received' => 5,'delay_confirmation_upload' => 5,'extraordinary_claimed' => 5,
                // TRIN 3 (Krav & interesser fortsat)
                'request_refund' => 3,'request_comp_60' => 3,'request_comp_120' => 3,'request_expenses' => 3,
                'pmr_user' => 3,
                'complaint_channel_seen' => 3,'complaint_already_filed' => 3,'submit_via_official_channel' => 3,
                'bike_res_required' => 3,'bike_followup_offer' => 3,
                // TRIN 3 additions (PMR)
                'pmrQBooked' => 3,'pmrQDelivered' => 3,'pmrQPromised' => 3,'pmr_facility_details' => 3,
                // TRIN 3 additions (Bike)
                'bike_was_present' => 3,'bike_caused_issue' => 3,'bike_reservation_made' => 3,'bike_reservation_required' => 3,'bike_denied_boarding' => 3,'bike_refusal_reason_provided' => 3,'bike_refusal_reason_type' => 3,
                // TRIN 3 additions (Pricing/Class)
                'fare_flex_type' => 3,'train_specificity' => 3,'fare_class_purchased' => 3,'class_delivered_status' => 3,'berth_seat_type' => 3,'reserved_amenity_delivered' => 3,
                // TRIN 3 additions (Disruption info)
                'preinformed_disruption' => 3,'preinfo_channel' => 3,'realtime_info_seen' => 3,
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
            // Helper for boolean-type inclusions (true/yes/1)
            $isAffirmative = function($v) use ($stringify) {
                if ($v === null) { return false; }
                if (is_bool($v)) { return $v === true; }
                $s = mb_strtolower(trim((string)$stringify($v)));
                if ($s === '') { return false; }
                // numeric values are affirmative if > 0
                if (is_numeric($s)) { return ((float)$s) > 0; }
                $pos = ['1','true','yes','ja','on'];
                return in_array($s, $pos, true);
            };
            // Add only if boolean-affirmative
            $addBool = function($label, $key) use ($dataForPdf, &$trinBlock, $stringify, $isAffirmative) {
                $v = $dataForPdf[$key] ?? null;
                if (!$isAffirmative($v)) { return; }
                $s = is_array($v) ? $stringify($v) : trim((string)$stringify($v));
                if ($s === '') { return; }
                $trinBlock[] = sprintf('%s: %s', $label, $s);
            };
            // Add for any non-empty value (text, file name, numeric > 0)
            $addAny = function($label, $key) use ($dataForPdf, &$trinBlock, $stringify) {
                $v = $dataForPdf[$key] ?? null;
                if ($v === null) { return; }
                $s = is_array($v) ? $stringify($v) : trim((string)$stringify($v));
                if ($s === '') { return; }
                // suppress explicit negatives if text is exactly 'nej'/'no'
                $low = mb_strtolower($s);
                if (in_array($low, ['nej','no','false','0'], true)) { return; }
                $trinBlock[] = sprintf('%s: %s', $label, $s);
            };

            // TRIN 5 (CIV screening) ÔÇö omit from Section 6 per request

            // TRIN 7 (Remedies) ÔÇö handled as its own TRIN group on the summary page now
            // (We don't inject TRIN 7 items into additional_info to avoid duplicates)

            // TRIN 8 (Assistance & expenses)
            $addBool('M+Ñltid tilbudt', 'meal_offered');
            $addBool('Hotel tilbudt', 'hotel_offered');
            $addAny('Udgifter: m+Ñltider', 'expense_breakdown_meals');
            $addAny('Udgifter: hotel-n+ªtter', 'expense_breakdown_hotel_nights');
            $addAny('Kvittering upload (udgifter)', 'extra_expense_upload');
            $addAny('Udgifter: lokal transport', 'expense_breakdown_local_transport');
            $addAny('Udgifter: andet', 'expense_breakdown_other_amounts');

            // TRIN 9 (Interests & hooks) ÔÇö user request: remove entitlement flags from additional_info summary
            // (request_refund, request_comp_60, request_comp_120, request_expenses excluded)

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
                        // Include selected steps on the consolidated last page
                        if (!in_array($trin, [3, 4, 5, 6], true)) { continue; }
                        $q = $questionText[$src] ?? $field;
                        $ans = $answerToText($dataForPdf[$src] ?? null);
                        $art = $fieldArticle[$src] ?? '';
                        $groups[$trin][] = ['q' => $q, 'a' => $ans, 'art' => $art, 'field' => $src];
                    }
                    // Add derived expense breakdown lines to TRIN 5, if present
                    $expenseLines = [
                        ['label' => 'Udgifter: m+Ñltider', 'key' => 'expense_breakdown_meals'],
                        ['label' => 'Udgifter: hotel-n+ªtter', 'key' => 'expense_breakdown_hotel_nights'],
                        ['label' => 'Udgifter: lokal transport', 'key' => 'expense_breakdown_local_transport'],
                        ['label' => 'Udgifter: andet', 'key' => 'expense_breakdown_other_amounts'],
                    ];
                    foreach ($expenseLines as $el) {
                        $val = $answerToText($dataForPdf[$el['key']] ?? null);
                        if ($val !== '') { $groups[5][] = ['q' => $el['label'], 'a' => $val, 'art' => 'Udgifter (Art. 20)', 'field' => $el['key']]; }
                    }
                    // Ensure TRIN 4 (afhj+ªlpning/krav) items are present even if not mapped on page 5
                    $trin6Fields = [
                        'trip_cancelled_return_to_origin',
                        'refund_requested',
                        'refund_form_selected',
                        'reroute_same_conditions_soonest',
                        'reroute_later_at_choice',
                        'reroute_info_within_100min',
                        'self_purchased_new_ticket',
                        'reroute_extra_costs',
                        'downgrade_occurred',
                    ];
                    foreach ($trin6Fields as $f) {
                        $val = $dataForPdf[$f] ?? null;
                        // Always include TRIN 4 questions, even if unanswered (empty answer string)
                        $q = $questionText[$f] ?? $f;
                        $art = $fieldArticle[$f] ?? '';
                        $groups[4][] = ['q' => $q, 'a' => $answerToText($val), 'art' => $art, 'field' => $f];
                    }
                    // De-duplicate any TRIN items by field key, preferring non-empty answers
                    foreach ($groups as $t => $items) {
                        $byKey = [];
                        foreach ($items as $it) {
                            $k = (string)($it['field'] ?? ($it['q'] ?? ''));
                            if ($k === '') { $byKey[] = $it; continue; }
                            if (!isset($byKey[$k])) {
                                $byKey[$k] = $it;
                                continue;
                            }
                            $existing = $byKey[$k];
                            $oldAns = trim((string)($existing['a'] ?? ''));
                            $newAns = trim((string)($it['a'] ?? ''));
                            if ($oldAns === '' && $newAns !== '') {
                                $byKey[$k] = $it; // replace empty with richer answer
                            }
                        }
                        // Preserve insertion order: iterate original list and emit chosen variant
                        $ordered = [];
                        $emitted = [];
                        foreach ($items as $it) {
                            $k = (string)($it['field'] ?? ($it['q'] ?? ''));
                            if ($k === '') { $ordered[] = $it; continue; }
                            if (isset($emitted[$k])) { continue; }
                            $ordered[] = $byKey[$k];
                            $emitted[$k] = true;
                        }
                        $groups[$t] = $ordered;
                    }
                    // Override TRIN 5 with custom assistance/alternative transport layout per specification
                    $currency = '';
                    if (!empty($dataForPdf['price']) && preg_match('/([A-Z]{3})/', (string)$dataForPdf['price'], $mm)) { $currency = strtoupper($mm[1]); }
                    // Helper: pick first non-empty value from a list of candidates
                    $firstNonEmpty = function(...$vals) {
                        foreach ($vals as $v) {
                            if ($v === null) { continue; }
                            // Treat strings of only whitespace as empty
                            if (is_string($v) && trim($v) === '') { continue; }
                            // Allow numeric 0, but skip string '0' for amount context later via caller logic if needed
                            return $v;
                        }
                        return null;
                    };

                    // Prefer self-paid breakdowns if present; else fall back to legacy fields (choose first non-empty)
                    $mealAmtRaw = $firstNonEmpty($dataForPdf['meal_self_paid_amount'] ?? null, $dataForPdf['expense_breakdown_meals'] ?? null, $dataForPdf['expense_meals'] ?? null);
                    $mealAmt = $answerToText($mealAmtRaw);
                    $currencyMeals = trim((string)$firstNonEmpty($dataForPdf['meal_self_paid_currency'] ?? null, $currency));

                    $hotelAmtRaw = $firstNonEmpty($dataForPdf['hotel_self_paid_amount'] ?? null, $dataForPdf['expense_hotel'] ?? null);
                    $hotelAmt = $answerToText($hotelAmtRaw);
                    $hotelNightsRaw = $firstNonEmpty($dataForPdf['hotel_self_paid_nights'] ?? null, $dataForPdf['expense_breakdown_hotel_nights'] ?? null);
                    $hotelNights = $answerToText($hotelNightsRaw);
                    $currencyHotel = trim((string)$firstNonEmpty($dataForPdf['hotel_self_paid_currency'] ?? null, $currency));

                    $blockedAmtRaw = $firstNonEmpty($dataForPdf['blocked_self_paid_amount'] ?? null, $dataForPdf['expense_breakdown_local_transport'] ?? null, $dataForPdf['expense_alt_transport'] ?? null);
                    $blockedAmt = $answerToText($blockedAmtRaw);
                    $currencyBlocked = trim((string)$firstNonEmpty($dataForPdf['blocked_self_paid_currency'] ?? null, $currency));

                    $altTransAmtRaw = $firstNonEmpty($dataForPdf['alt_self_paid_amount'] ?? null, $dataForPdf['expense_alt_transport'] ?? null, $dataForPdf['expense_breakdown_local_transport'] ?? null);
                    $altTransAmt = $answerToText($altTransAmtRaw);
                    $currencyAlt = trim((string)$firstNonEmpty($dataForPdf['alt_self_paid_currency'] ?? null, $currency));
                    $uploadVal = $answerToText($dataForPdf['extra_expense_upload'] ?? null);
                    $groups[5] = [];
                    // A) Tilbudt assistance
                    $groups[5][] = ['q' => 'F+Ñr du m+Ñltider/forfriskninger under ventetiden? (Art. 20(2)(a))', 'a' => $answerToText($dataForPdf['meal_offered'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'meal_offered'];
                    $groups[5][] = ['q' => 'M+Ñltider ÔÇô bel+©b', 'a' => $mealAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_meals'];
                    $groups[5][] = ['q' => 'Valuta', 'a' => $currencyMeals, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_meals'];
                    $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
                    $groups[5][] = ['q' => 'F+Ñr du hotel/indkvartering + transport dertil? (Art. 20(2)(b))', 'a' => $answerToText($dataForPdf['hotel_offered'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'hotel_offered'];
                    $groups[5][] = ['q' => 'Hotel ÔÇô bel+©b (samlet)', 'a' => $hotelAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_hotel'];
                    $groups[5][] = ['q' => 'Valuta', 'a' => $currencyHotel, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_hotel'];
                    $groups[5][] = ['q' => 'Antal n+ªtter', 'a' => $hotelNights, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_hotel_nights'];
                    $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
                    // Extra spacer BEFORE blocked train question to push this cluster downward
                    $groups[5][] = ['q' => '', 'a' => '', 'art' => 'A) Tilbudt assistance', 'field' => '_spacer_blocked_transport_before'];
                    $groups[5][] = ['q' => 'Er toget blokeret p+Ñ sporet ÔÇö f+Ñr du transport v+ªk? (Art. 20(2)(c))', 'a' => $answerToText($dataForPdf['blocked_train_alt_transport'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'blocked_train_alt_transport'];
                    // Spacer AFTER question before amount/currency lines
                    $groups[5][] = ['q' => '', 'a' => '', 'art' => 'A) Tilbudt assistance', 'field' => '_spacer_blocked_transport_after'];
                    $groups[5][] = ['q' => 'Transport v+ªk ÔÇô bel+©b', 'a' => $blockedAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_local_transport'];
                    $groups[5][] = ['q' => 'Valuta', 'a' => $currencyBlocked, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_blocked'];
                    $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
                    // B) Alternative transporttjenester
                    $groups[5][] = ['q' => 'F+Ñr du alternative transporttjenester, hvis forbindelsen er afbrudt? (Art. 20(3))', 'a' => $answerToText($dataForPdf['alt_transport_provided'] ?? null), 'art' => 'B) Alternative transporttjenester', 'field' => 'alt_transport_provided'];
                    $groups[5][] = ['q' => 'Alternativ transport til destination ÔÇô bel+©b', 'a' => $altTransAmt, 'art' => 'B) Alternative transporttjenester', 'field' => 'expense_alt_transport'];
                    $groups[5][] = ['q' => 'Valuta', 'a' => $currencyAlt, 'art' => 'B) Alternative transporttjenester', 'field' => '_currency_alt'];
                    $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'B) Alternative transporttjenester', 'field' => 'extra_expense_upload'];
                    // Ensure TRIN 3 (Art.12 + Art.9) items are present even if not mapped on page 5
                    // Note: Do NOT include raw PMR synonyms here to avoid duplicates; we include normalized pmrQ* keys only.
                    $trin3Fields = [
                        'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice','shared_pnr_scope','seller_type_operator',
                        'one_contract_schedule','contact_info_provided','responsibility_explained','continue_national_rules',
                        // Hurtigste rejse ordering: fastest flag first then MCT realism
                        'fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                        // Pris/fleksibilitet
                        'info_requested_pre_purchase','coc_acknowledged','civ_marking_present','multiple_fares_shown','cheapest_highlighted',
                        // Information (Art. 9(1))
                        'preinformed_disruption','preinfo_channel','realtime_info_seen',
                        // PMR (Art. 21-24)
                        'pmr_user','pmrQBooked','pmrQDelivered','pmrQPromised','pmr_facility_details',
                        // (pmr_delivered_status, pmr_promised_missing, pmr_booked_detail handled via alias/inference and not listed directly)
                        // Additional booking & bike synonyms
                        'single_booking_reference','seller_type_agency','pm_bike_involved'
                    ];
                    foreach ($trin3Fields as $f) {
                        $val = $dataForPdf[$f] ?? null;
                        $ans = $answerToText($val);
                        $includeAlways = in_array($f, ['pmr_user','pmrQBooked','pmrQDelivered','pmrQPromised'], true);
                        if ($val === null && !$includeAlways) { continue; }
                        if ($ans === '' && !$includeAlways) { continue; }
                        // Suppress empty facility details entirely
                        if ($f === 'pmr_facility_details' && trim((string)$ans) === '') { continue; }
                        $q = $questionText[$f] ?? $f;
                        $art = $fieldArticle[$f] ?? '';
                        $groups[3][] = ['q' => $q, 'a' => $ans, 'art' => $art, 'field' => $f];
                    }
                    // De-duplicate again after augmentation, still preferring non-empty answers
                    foreach ($groups as $t => $items) {
                        $byKey = [];
                        foreach ($items as $it) {
                            $k = (string)($it['field'] ?? ($it['q'] ?? ''));
                            if ($k === '') { $byKey[] = $it; continue; }
                            if (!isset($byKey[$k])) { $byKey[$k] = $it; continue; }
                            $existing = $byKey[$k];
                            $oldAns = trim((string)($existing['a'] ?? ''));
                            $newAns = trim((string)($it['a'] ?? ''));
                            if ($oldAns === '' && $newAns !== '') { $byKey[$k] = $it; }
                        }
                        $ordered = [];
                        $emitted = [];
                        foreach ($items as $it) {
                            $k = (string)($it['field'] ?? ($it['q'] ?? ''));
                            if ($k === '') { $ordered[] = $it; continue; }
                            if (isset($emitted[$k])) { continue; }
                            $ordered[] = $byKey[$k];
                            $emitted[$k] = true;
                        }
                        $groups[$t] = $ordered;
                    }

                    // Build a list of lines with TRIN headers and article subheaders
                    $groupTitles = [
                        3 => 'TRIN 3 -À Art. 6, Art. 9, Art. 12 og Art. 21-24',
                        4 => 'TRIN 4 -À Afhj+ªlpning og krav',
                        5 => 'TRIN 5 -À Assistance og udgifter',
                        6 => 'TRIN 6 -À Kompensation (Art. 19)'
                    ];
                    $allLines = [];
                    ksort($groups);
                    // Exclude selected complaint/national/entitlement flags from TRIN 3 only
                    $excludeTrin3Fields = ['continue_national_rules','complaint_channel_seen','complaint_already_filed','submit_via_official_channel','request_refund','request_comp_60','request_comp_120','request_expenses','bike_res_required','bike_followup_offer','fare_class_purchased','class_delivered_status','berth_seat_type','reserved_amenity_delivered'];
                    foreach ($groups as $trin => $items) {
                        $title = $groupTitles[$trin] ?? sprintf('TRIN %d', $trin);
                        $allLines[] = $toPdf($title);
                        // add spacing after TRIN title
                        $allLines[] = '';
                        // Group by article label in insertion order
                        $byArt = [];
                        $order = [];
                        foreach ($items as $it) {
                            $art = (string)($it['art'] ?? '');
                            $key = $art !== '' ? $art : '_';
                            if (!isset($byArt[$key])) { $byArt[$key] = []; $order[] = $key; }
                            $byArt[$key][] = $it;
                        }
                        // Group Art. 9 (1) subheaders together (no superheader)
                        $a9Keys = [];
                        $otherKeys = [];
                        foreach ($order as $artKey) {
                            if ($artKey === '_') { continue; }
                            $isA9 = stripos($artKey, 'art. 9') !== false; // matches 'Art. 9(1)' and 'Art. 9 (1)'
                            if ($isA9) { $a9Keys[] = $artKey; } else { $otherKeys[] = $artKey; }
                        }
                        // First, print items without any subheader ('_')
                        if (isset($byArt['_'])) {
                            foreach ($byArt['_'] as $it) {
                                if ($trin === 3 && in_array($it['field'] ?? '', $excludeTrin3Fields, true)) { continue; }
                                $text = sprintf('%s: %s', $it['q'], $it['a']);
                                $allLines[] = $toPdf($text);
                            }
                        }
                        if ($trin === 3) {
                            // Custom TRIN 3 subheader order: Art. 6 ÔåÆ Art. 9 ÔåÆ Art. 12 ÔåÆ Art. 21-24 ÔåÆ rest
                            $art6 = [];$art9 = [];$art12 = [];$art2124 = [];$rest = [];
                            foreach ($order as $artKey) {
                                if ($artKey === '_') { continue; }
                                $low = mb_strtolower($artKey);
                                if (strpos($low, 'art. 6') !== false || strpos($low, 'cykel') !== false) { $art6[] = $artKey; }
                                elseif (strpos($low, 'art. 9') !== false) { $art9[] = $artKey; }
                                elseif (strpos($low, 'art. 12') !== false) { $art12[] = $artKey; }
                                elseif (strpos($low, '21-24') !== false || strpos($low, 'pmr') !== false) { $art2124[] = $artKey; }
                                else { $rest[] = $artKey; }
                            }
                            $sortedKeys = array_merge($art6, $art9, $art12, $art2124, $rest);
                            foreach ($sortedKeys as $artKey) {
                                $itemsForArt = $byArt[$artKey];
                                if (stripos($artKey, 'Hurtigste rejse') !== false) {
                                    $pref = [
                                        'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                                        'Var minimumsskiftetiden realistisk (missed station)?',
                                        'S+Ñ du alternative forbindelser ved k+©bet?'
                                    ];
                                    $rank = function($q) use ($pref) { $i = array_search($q, $pref, true); return $i === false ? 999 : $i; };
                                    usort($itemsForArt, function($a, $b) use ($rank) { return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? ''); });
                                }
                                $visibleItems = [];
                                foreach ($itemsForArt as $it) { if ($trin === 3 && in_array(($it['field'] ?? ''), $excludeTrin3Fields, true)) { continue; } $visibleItems[] = $it; }
                                if (!empty($visibleItems)) {
                                    $allLines[] = '';
                                    $allLines[] = $toPdf($artKey);
                                    foreach ($visibleItems as $it) {
                                        if (trim((string)($it['q'] ?? '')) === '' && trim((string)($it['a'] ?? '')) === '') { $allLines[] = ''; continue; }
                                        $allLines[] = $toPdf(sprintf('%s: %s', $it['q'], $it['a']));
                                        if ($trin === 5 && isset($it['field']) && is_string($it['field']) && $it['field'] === 'extra_expense_upload') { $allLines[] = ''; }
                                    }
                                }
                            }
                        } else {
                            // Then non-Art.9(1) groups in original order
                            foreach ($otherKeys as $artKey) {
                                $itemsForArt = $byArt[$artKey];
                                if (stripos($artKey, 'Hurtigste rejse') !== false) {
                                    $pref = [
                                        'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                                        'Var minimumsskiftetiden realistisk (missed station)?',
                                        'S+Ñ du alternative forbindelser ved k+©bet?'
                                    ];
                                    $rank = function($q) use ($pref) {
                                        $i = array_search($q, $pref, true);
                                        return $i === false ? 999 : $i;
                                    };
                                    usort($itemsForArt, function($a, $b) use ($rank) {
                                        return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? '');
                                    });
                                } elseif (trim($artKey) === 'Refusion') {
                                    $pref = [
                                        '+ÿnsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?',
                                        'Har du allerede anmodet om refusion?',
                                        'Hvis ja, hvilken form for refusion?'
                                    ];
                                    $rank = function($q) use ($pref) {
                                        $i = array_search($q, $pref, true);
                                        return $i === false ? 999 : $i;
                                    };
                                    usort($itemsForArt, function($a, $b) use ($rank) {
                                        return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? '');
                                    });
                                } elseif (trim($artKey) === 'Oml+ªgning') {
                                    $pref = [
                                        '+ÿnsker du oml+ªgning p+Ñ tilsvarende vilk+Ñr ved f+©rst givne lejlighed?',
                                        '+ÿnsker du oml+ªgning til et senere tidspunkt efter eget valg?',
                                        $transportMode === 'air' ? 'Tilboed flyselskabet refusion eller ombooking efter article 8?' : 'Er du blevet informeret om mulighederne for oml+ªgning inden for 100 minutter efter planlagt afgang? (Art. 18(3))',
                                        'K+©ber du selv en ny billet for at komme videre?',
                                        'Kommer oml+ªgningen til at medf+©re ekstra udgifter for dig? (h+©jere klasse/andet transportmiddel)?',
                                        'Er du blevet nedklassificeret eller regner med at blive det pga. oml+ªgningen?'
                                    ];
                                    $rank = function($q) use ($pref) {
                                        $i = array_search($q, $pref, true);
                                        return $i === false ? 999 : $i;
                                    };
                                    usort($itemsForArt, function($a, $b) use ($rank) {
                                        return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? '');
                                    });
                                }
                                $visibleItems = [];
                                foreach ($itemsForArt as $it) {
                                    if ($trin === 3 && in_array($it['field'] ?? '', $excludeTrin3Fields, true)) { continue; }
                                    $visibleItems[] = $it;
                                }
                                if (!empty($visibleItems)) {
                                    $allLines[] = '';
                                    $allLines[] = $toPdf($artKey);
                                    foreach ($visibleItems as $it) {
                                        // Print a spacer as a blank line
                                        if (trim((string)($it['q'] ?? '')) === '' && trim((string)($it['a'] ?? '')) === '') {
                                            $allLines[] = '';
                                            continue;
                                        }
                                        $text = sprintf('%s: %s', $it['q'], $it['a']);
                                        $allLines[] = $toPdf($text);
                                        // Add a small blank line after TRIN 5 upload lines
                                        if ($trin === 5 && isset($it['field']) && is_string($it['field']) && $it['field'] === 'extra_expense_upload') {
                                            $allLines[] = '';
                                        }
                                    }
                                }
                            }
                            // Finally, all Art. 9(1) subheaders together
                            foreach ($a9Keys as $artKey) {
                                $itemsForArt = $byArt[$artKey];
                                if (stripos($artKey, 'Hurtigste rejse') !== false) {
                                    $pref = [
                                        'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                                        'Var minimumsskiftetiden realistisk (missed station)?',
                                        'S+Ñ du alternative forbindelser ved k+©bet?'
                                    ];
                                    $rank = function($q) use ($pref) {
                                        $i = array_search($q, $pref, true);
                                        return $i === false ? 999 : $i;
                                    };
                                    usort($itemsForArt, function($a, $b) use ($rank) {
                                        return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? '');
                                    });
                                }
                                $visibleItems = [];
                                foreach ($itemsForArt as $it) {
                                    if ($trin === 3 && in_array($it['field'] ?? '', $excludeTrin3Fields, true)) { continue; }
                                    $visibleItems[] = $it;
                                }
                                if (!empty($visibleItems)) {
                                    $allLines[] = '';
                                    $allLines[] = $toPdf($artKey);
                                    foreach ($visibleItems as $it) {
                                        $text = sprintf('%s: %s', $it['q'], $it['a']);
                                        $allLines[] = $toPdf($text);
                                    }
                                }
                            }
                        }
                        $allLines[] = '';
                    }

                    $extra = trim($stringify($dataForPdf['additional_info'] ?? ''));
                    if ($extra !== '') {
                        $allLines[] = $toPdf('');
                        // keep paragraphs as-is, split by newlines
                        $paras = preg_split('/`r`n|\r|\n/', $extra);
                        foreach ($paras as $p) { $p = trim($p); if ($p !== '') { $allLines[] = $toPdf($p); } }
                    }

                    // Filter out any accidental TRIN 1 headers and store for rendering
                    $filtered = array_filter($allLines, function($l) {
                        return !preg_match('/^\s*TRIN\s*1\b/i', (string)$l);
                    });
                    $pendingSection6 = array_values($filtered);
                    // Render a minimal subset of page 5 checkboxes (PMR) so user-selected handicap assistance answers appear on the official template page as well.
                    $pmrShow = ['pmr_user','pmr_booked','pmrQBooked','pmrQDelivered','pmrQPromised'];
                    $fpdi->SetFont('Helvetica', '', 9);
                    foreach ($pmrShow as $f) {
                        if (empty($map[$pageNo][$f])) { continue; }
                        $cfg = $map[$pageNo][$f];
                        $type = $cfg['type'] ?? 'checkbox';
                        [$x,$y,$w] = $this->adaptCoordinates(is_array($cfg)?$cfg:[], (float)$size['height'], $mapMeta, (float)$dx, (float)$dy);
                        $val = $dataForPdf[$f] ?? null;
                        $checked = !empty($val) && $val !== '0' && $val !== 'nej' && $val !== false;
                        if ($type === 'checkbox') {
                            if ($checked) {
                                $fpdi->SetDrawColor(0,0,0);
                                $fpdi->SetLineWidth(0.25);
                                $fpdi->Line($x, $y, $x+4, $y+4);
                                $fpdi->Line($x, $y+4, $x+4, $y);
                            }
                        } else {
                            $s = $stringify($val);
                            if ($s === '') { continue; }
                            $fpdi->SetXY($x, $y);
                            $fpdi->Cell($w > 0 ? $w : 0, 4, $toPdf($s), 0, 0);
                        }
                    }
                    // Skip all other per-field rendering for page 5
                    continue;
                }

                // Default per-field rendering for non-page-5
                $fpdi->SetFont('Helvetica', '', 9);
                $pageH = (float)$size['height'];
                foreach ($map[$pageNo] as $field => $cfg) {
                    $type = $cfg['type'] ?? 'text';
                    [$x, $y, $w] = $this->adaptCoordinates(is_array($cfg) ? $cfg : [], $pageH, $mapMeta, (float)$dx, (float)$dy);
                    if ($type === 'checkbox') {
                        $srcFieldCk = $cfg['source'] ?? $field;
                        // Suppress entitlement checkboxes only on template page 5 (still shown in summary)
                        if ($pageNo === 5 && in_array($srcFieldCk, ['request_refund','request_comp_60','request_comp_120','request_expenses'], true)) {
                            continue;
                        }

                        // Normalise incident-based reason fields so exotic values like 'delayLikely60' still tick
                        if (in_array($srcFieldCk, ['reason_delay','reason_cancellation','reason_missed_conn'], true)) {
                            $cur = $dataForPdf[$srcFieldCk] ?? null;
                            $curS = is_bool($cur) ? ($cur ? 'ja' : 'nej') : mb_strtolower(trim((string)$cur));
                            $isFalsy = ($cur === null) || ($cur === false) || ($curS === '' || $curS === '0' || $curS === 'nej' || $curS === 'no' || $curS === 'false');
                            if ($isFalsy) {
                                $incNorm = strtolower((string)($dataForPdf['incident_main'] ?? ''));
                                // remove non letters for substring checks (e.g. delay-likely-60)
                                $incFlat2 = preg_replace('/[^a-z]/','', $incNorm);
                                if ($srcFieldCk === 'reason_delay' && (strpos($incNorm,'delay') !== false || strpos($incFlat2,'delay') !== false)) {
                                    $dataForPdf[$srcFieldCk] = true;
                                } elseif ($srcFieldCk === 'reason_cancellation' && (strpos($incNorm,'cancel') !== false || strpos($incFlat2,'cancel') !== false)) {
                                    $dataForPdf[$srcFieldCk] = true;
                                } elseif ($srcFieldCk === 'reason_missed_conn' && (strpos($incNorm,'missed') !== false || strpos($incFlat2,'missed') !== false)) {
                                    $dataForPdf[$srcFieldCk] = true;
                                }
                            }
                        }

                        $checked = !empty($dataForPdf[$srcFieldCk]);
                        if ($checked) {
                            // Draw a more visible checkbox mark: light square + thicker cross
                            $fpdi->SetDrawColor(0,0,0);
                            $fpdi->SetLineWidth(0.25);
                            // Optional bounding box (slightly larger than mapping coordinate) for reason_* on page 1
                            if ($pageNo === 1 && in_array($srcFieldCk, ['reason_delay','reason_cancellation','reason_missed_conn'], true)) {
                                $fpdi->Rect($x-0.5, $y-0.5, 5, 5);
                            }
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
                // TRIN 4 (Afhj+ªlpning og krav)
                'trip_cancelled_return_to_origin' => '+ÿnsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?',
                'refund_requested' => 'Har du allerede anmodet om refusion?',
                'refund_form_selected' => 'Hvis ja, hvilken form for refusion?',
                'reroute_same_conditions_soonest' => '+ÿnsker du oml+ªgning p+Ñ tilsvarende vilk+Ñr ved f+©rst givne lejlighed?',
                'reroute_later_at_choice' => '+ÿnsker du oml+ªgning til et senere tidspunkt efter eget valg?',
                'reroute_info_within_100min' => $transportMode === 'air' ? 'Tilboed flyselskabet refusion eller ombooking efter article 8?' : 'Er du blevet informeret om mulighederne for oml+ªgning inden for 100 minutter efter planlagt afgang? (Art. 18(3))',
                'self_purchased_new_ticket' => $transportMode === 'air' ? 'Maatte du selv arrangere ombooking eller videre rejse?' : 'K+©ber du selv en ny billet for at komme videre?',
                'reroute_extra_costs' => 'Kommer oml+ªgningen til at medf+©re ekstra udgifter for dig? (h+©jere klasse/andet transportmiddel)?',
                'downgrade_occurred' => 'Er du blevet nedklassificeret eller regner med at blive det pga. oml+ªgningen?',
                'through_ticket_disclosure' => 'Blev det oplyst at billetten var gennemg+Ñende?',
                'single_txn_operator' => 'Var k+©bet en enkelt transaktion med operat+©ren?',
                'single_txn_retailer' => 'Var k+©bet en enkelt transaktion med forhandleren?',
                'separate_contract_notice' => 'Blev separate kontrakter oplyst?',
                'shared_pnr_scope' => 'Var alle billetter udstedt under samme bookingnummer/PNR?',
                'seller_type_operator' => 'Var det en jernbanevirksomhed der solgte dig hele rejsen?',
                // MCT wording consolidated to a single phrasing below
                'one_contract_schedule' => 'Var hele rejseplanen under +®n kontrakt?',
                'contact_info_provided' => 'Blev kontaktinfo oplyst f+©r k+©b?',
                'responsibility_explained' => 'Blev ansvar/ansvarsfordeling forklaret?',
                'continue_national_rules' => 'Forts+ªtter sagen under nationale regler?',
                'meal_offered' => 'Blev der tilbudt m+Ñltid?',
                'hotel_offered' => 'Blev der tilbudt hotelovernatning?',
                'overnight_needed' => 'Var overnatning n+©dvendig?',
                'blocked_train_alt_transport' => 'Blev der tilbudt alternativ transport pga. blokeret tog?',
                'alt_transport_provided' => 'Blev alternativ transport leveret?',
                'extra_expense_upload' => 'Har du uploadet kvitteringer for udgifter?',
                'delay_confirmation_received' => 'Modtog du bekr+ªftelse p+Ñ forsinkelsen?',
                'delay_confirmation_upload' => 'Har du uploadet bekr+ªftelse p+Ñ forsinkelsen?',
                'extraordinary_claimed' => 'Har du angivet ekstraordin+ªrt krav?',
                'request_refund' => '+ÿnsker du refusion?',
                'request_comp_60' => '+ÿnsker du kompensation for 60+ min?',
                'request_comp_120' => '+ÿnsker du kompensation for 120+ min?',
                'request_expenses' => '+ÿnsker du d+ªkning af udgifter?',
                'info_requested_pre_purchase' => '+ÿnskede du info f+©r k+©b?',
                'coc_acknowledged' => 'Er CoC (conditions of carriage) anerkendt?',
                'civ_marking_present' => 'Er CIV-markering til stede?',
                'fastest_flag_at_purchase' => 'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                'mct_realistic' => 'Var minimumsskiftetiden realistisk (missed station)?',
                'alts_shown_precontract' => 'S+Ñ du alternative forbindelser ved k+©bet?',
                'multiple_fares_shown' => 'Fik du vist flere prisvalg for samme afgang?',
                'cheapest_highlighted' => "Var 'billigste pris' markeret/anbefalet?",
                'pmr_user' => 'Har du et handicap eller nedsat mobilitet, som kr+ªvede assistance?',
                'pmr_booked' => 'Var PMR-booking foretaget?',
                'submit_via_official_channel' => 'Sendes ind via officiel kanal?',
                'bike_res_required' => 'Var cykelreservation p+Ñkr+ªvet?',
                'bike_followup_offer' => 'Fik du opf+©lgende tilbud for cykel?',
                // TRIN 3 (PMR/handicap)
                'pmrQBooked' => 'Bestilte du assistance f+©r rejsen?',
                'pmrQDelivered' => 'Blev den bestilte assistance leveret?',
                'pmrQPromised' => 'Manglede der PMR-faciliteter, som var lovet f+©r k+©bet?',
                'pmr_facility_details' => 'Hvilke faciliteter manglede? (rampe, skiltning, lift ÔÇª)',
                // TRIN 3 (Cykel)
                'bike_was_present' => 'Havde du en cykel med p+Ñ rejsen?',
                'bike_caused_issue' => 'Var det cyklen eller h+Ñndteringen af cyklen, der har forsinket dig?',
                'bike_reservation_made' => 'Havde du reserveret plads til en cykel?',
                'bike_reservation_required' => 'Var det et tog, hvor der ikke kr+ªvedes cykelreservation?',
                'bike_denied_boarding' => 'Blev du n+ªgtet at tage cyklen med?',
                'bike_refusal_reason_provided' => 'Blev du informeret om, hvorfor du ikke m+Ñtte tage cyklen med?',
                'bike_refusal_reason_type' => 'Hvad var begrundelsen for afvisningen?',
                // TRIN 3 (Billetpriser og fleksibilitet)
                'fare_flex_type' => 'K+©bstype (fleksibilitet)',
                'train_specificity' => 'G+ªlder billetten kun for specifikt tog?',
                // TRIN 3 (Klasse og reserverede billetter)
                'fare_class_purchased' => 'Hvilken klasse var k+©bt?',
                'class_delivered_status' => 'Fik du den klasse, du betalte for?',
                'berth_seat_type' => 'Var der reserveret plads/kupe/ligge/sove?',
                'reserved_amenity_delivered' => 'Blev reserveret plads/ligge/sove leveret?',
                // TRIN 3 (Afbrydelser oplyst f+©r k+©b)
                'preinformed_disruption' => 'Var der meddelt afbrydelse/forsinkelse f+©r dit k+©b?',
                'preinfo_channel' => 'Hvis ja: Hvor blev det vist?',
                'realtime_info_seen' => 'S+Ñ du realtime-opdateringer under rejsen?'
            ];
            // Augment question text with alias fields so they appear with readable Danish labels
            $questionText += [
                'pmr_delivered_status' => 'Leveringsstatus for assistance (fuld/delvis/ingen)',
                'pmr_promised_missing' => 'Manglede lovede PMR-faciliteter? (synonym)',
                'pmr_booked_detail' => 'Detalje om bestilt assistance (afvist/andet)',
                'single_booking_reference' => 'Var hele rejsen p+Ñ +®t bookingnummer?',
                'seller_type_agency' => 'Var det et rejsebureau der solgte rejsen (synonym)?',
                'pm_bike_involved' => 'Var cykel involveret i h+ªndelsen? (synonym)'
            ];
            // Fallback: field-to-article/subsection mapping (duplicate minimal set for local scope)
            $fieldArticle = [
                // TRIN 3 -À Art. 12
                'through_ticket_disclosure' => 'Art. 12',
                'single_txn_operator' => 'Art. 12',
                'single_txn_retailer' => 'Art. 12',
                'separate_contract_notice' => 'Art. 12',
                'shared_pnr_scope' => 'Art. 12',
                'seller_type_operator' => 'Art. 12',
                'one_contract_schedule' => 'Art. 12',
                'responsibility_explained' => 'Art. 12',
                'contact_info_provided' => 'Art. 12',
                // TRIN 3 -À Art. 9(1)
                'info_requested_pre_purchase' => 'Art. 9(1)',
                'coc_acknowledged' => 'Art. 9(1)',
                'civ_marking_present' => 'Art. 9(1)',
                'fastest_flag_at_purchase' => 'Art. 9 (1) Hurtigste rejse',
                'mct_realistic' => 'Art. 9 (1) Hurtigste rejse',
                'alts_shown_precontract' => 'Art. 9 (1) Hurtigste rejse',
                'multiple_fares_shown' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                'cheapest_highlighted' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                // TRIN 3 -À PMR (Art. 21-24)
                'pmr_user' => 'PMR (Art. 21-24)',
                'pmr_booked' => 'PMR (Art. 21-24)',
                'pmrQBooked' => 'PMR (Art. 21-24)',
                'pmrQDelivered' => 'PMR (Art. 21-24)',
                'pmrQPromised' => 'PMR (Art. 21-24)',
                'pmr_facility_details' => 'PMR (Art. 21-24)',
                // Aliases map to existing articles
                'pmr_delivered_status' => 'PMR (Art. 21-24)',
                'pmr_promised_missing' => 'PMR (Art. 21-24)',
                'pmr_booked_detail' => 'PMR (Art. 21-24)',
                'single_booking_reference' => 'Art. 12',
                'seller_type_agency' => 'Art. 12',
                'pm_bike_involved' => 'Cykel (Art. 6)',
                // TRIN 3 -À Cykel (Art. 6)
                'bike_res_required' => 'Cykel (Art. 6)',
                'bike_followup_offer' => 'Cykel (Art. 6)',
                'bike_was_present' => 'Cykel (Art. 6)',
                'bike_caused_issue' => 'Cykel (Art. 6)',
                'bike_reservation_made' => 'Cykel (Art. 6)',
                'bike_reservation_required' => 'Cykel (Art. 6)',
                'bike_denied_boarding' => 'Cykel (Art. 6)',
                'bike_refusal_reason_provided' => 'Cykel (Art. 6)',
                'bike_refusal_reason_type' => 'Cykel (Art. 6)',
                // TRIN 3 -À Billetpriser og fleksibilitet (under Art. 9 (1))
                'fare_flex_type' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                'train_specificity' => 'Art. 9 (1) -À Billetpriser og fleksibilitet',
                // TRIN 3 -À Klasse og reserverede billetter (under Art. 9 (1))
                'fare_class_purchased' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'class_delivered_status' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'berth_seat_type' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                'reserved_amenity_delivered' => 'Art. 9 (1) -À Klasse og reserverede billetter',
                // TRIN 3 -À Information (under Art. 9 (1))
                'preinformed_disruption' => 'Art. 9 (1) -À Information',
                'preinfo_channel' => 'Art. 9 (1) -À Information',
                'realtime_info_seen' => 'Art. 9 (1) -À Information',
                // TRIN 4 -À Afhj+ªlpning (Refusion / Oml+ªgning)
                'trip_cancelled_return_to_origin' => 'Refusion',
                'refund_requested' => 'Refusion',
                'refund_form_selected' => 'Refusion',
                'reroute_same_conditions_soonest' => 'Oml+ªgning',
                'reroute_later_at_choice' => 'Oml+ªgning',
                'reroute_info_within_100min' => $transportMode === 'air' ? 'Article 8' : 'Oml+ªgning',
                'self_purchased_new_ticket' => $transportMode === 'air' ? 'Article 8' : 'Oml+ªgning',
                'reroute_extra_costs' => 'Oml+ªgning',
                'downgrade_occurred' => 'Oml+ªgning',
                // TRIN 5 -À Art. 20
                'meal_offered' => 'Assistance (Art. 20)',
                'hotel_offered' => 'Assistance (Art. 20)',
                'overnight_needed' => 'Assistance (Art. 20)',
                'blocked_train_alt_transport' => 'Assistance (Art. 20)',
                'alt_transport_provided' => 'Assistance (Art. 20)',
                'delay_confirmation_received' => 'Dokumentation',
                'delay_confirmation_upload' => 'Dokumentation',
                'extra_expense_upload' => 'Udgifter (Art. 20)',
                'extraordinary_claimed' => 'Udgifter (Art. 20)',
                // Requests hooks
                'request_refund' => 'Entitlement',
                'request_comp_60' => 'Entitlement',
                'request_comp_120' => 'Entitlement',
                'request_expenses' => 'Entitlement',
            ];
            $trinForField = [
                // TRIN 4 (Afhj+ªlpning/krav)
                'trip_cancelled_return_to_origin' => 4,
                'refund_requested' => 4,
                'refund_form_selected' => 4,
                'self_purchased_new_ticket' => 4,
                'reroute_same_conditions_soonest' => 4,
                'reroute_later_at_choice' => 4,
                'reroute_info_within_100min' => 4,
                'reroute_extra_costs' => 4,
                'downgrade_occurred' => 4,
                // TRIN 3 (Art. 12)
                'through_ticket_disclosure' => 3,'single_txn_operator' => 3,'single_txn_retailer' => 3,'separate_contract_notice' => 3,'shared_pnr_scope' => 3,'seller_type_operator' => 3,
                'mct_realistic' => 3,'one_contract_schedule' => 3,'contact_info_provided' => 3,'responsibility_explained' => 3,'continue_national_rules' => 3,
                // TRIN 5 (Assistance & udgifter)
                'meal_offered' => 5,'hotel_offered' => 5,'overnight_needed' => 5,'blocked_train_alt_transport' => 5,'alt_transport_provided' => 5,'extra_expense_upload' => 5,'delay_confirmation_received' => 5,'delay_confirmation_upload' => 5,'extraordinary_claimed' => 5,
                // TRIN 3 (Krav fortsat)
                'request_refund' => 3,'request_comp_60' => 3,'request_comp_120' => 3,'request_expenses' => 3,
                'info_requested_pre_purchase' => 3,'coc_acknowledged' => 3,'civ_marking_present' => 3,'fastest_flag_at_purchase' => 3,'alts_shown_precontract' => 3,'multiple_fares_shown' => 3,'cheapest_highlighted' => 3,'pmr_user' => 3,'submit_via_official_channel' => 3,'bike_res_required' => 3,'bike_followup_offer' => 3,
                // TRIN 3 additions (PMR)
                'pmrQBooked' => 3,'pmrQDelivered' => 3,'pmrQPromised' => 3,'pmr_facility_details' => 3,
                // TRIN 3 additions (Bike)
                'bike_was_present' => 3,'bike_caused_issue' => 3,'bike_reservation_made' => 3,'bike_reservation_required' => 3,'bike_denied_boarding' => 3,'bike_refusal_reason_provided' => 3,'bike_refusal_reason_type' => 3,
                // TRIN 3 additions (Pricing/Class)
                'fare_flex_type' => 3,'train_specificity' => 3,'fare_class_purchased' => 3,'class_delivered_status' => 3,'berth_seat_type' => 3,'reserved_amenity_delivered' => 3,
                // TRIN 3 additions (Disruption info)
                'preinformed_disruption' => 3,'preinfo_channel' => 3,'realtime_info_seen' => 3,
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
                $art = $fieldArticle[$src] ?? '';
                $groups[$trin][] = ['q' => $q, 'a' => $ans, 'art' => $art, 'field' => $src];
            }
            // Ensure TRIN 4 appears even if not in map[5]
            $trin6Fields = ['trip_cancelled_return_to_origin','refund_requested','refund_form_selected','reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min','self_purchased_new_ticket','reroute_extra_costs','downgrade_occurred'];
            foreach ($trin6Fields as $f) {
                $val = $dataSnap[$f] ?? null;
                // Always include TRIN 4 questions, even if unanswered
                $q = $questionText[$f] ?? $f;
                $art = $fieldArticle[$f] ?? '';
                $groups[4][] = ['q' => $q, 'a' => $answerToText($val), 'art' => $art, 'field' => $f];
            }
            // De-duplicate any TRIN items by field key to avoid double entries
            foreach ($groups as $t => $items) {
                $seen = [];
                $uniq = [];
                foreach ($items as $it) {
                    $k = (string)($it['field'] ?? ($it['q'] ?? ''));
                    if ($k === '') { $uniq[] = $it; continue; }
                    if (isset($seen[$k])) { continue; }
                    $seen[$k] = true;
                    $uniq[] = $it;
                }
                $groups[$t] = $uniq;
            }
            // Override TRIN 5 with custom assistance/alternative transport layout per specification (fallback builder)
            $currency = '';
            if (!empty($dataSnap['price']) && preg_match('/([A-Z]{3})/', (string)$dataSnap['price'], $mm)) { $currency = strtoupper($mm[1]); }
            $mealAmt = $answerToText($dataSnap['expense_breakdown_meals'] ?? ($dataSnap['expense_meals'] ?? null));
            $hotelAmt = $answerToText($dataSnap['expense_hotel'] ?? null);
            $hotelNights = $answerToText($dataSnap['expense_breakdown_hotel_nights'] ?? null);
            $blockedAmt = $answerToText($dataSnap['expense_breakdown_local_transport'] ?? ($dataSnap['expense_alt_transport'] ?? null));
            $altTransAmt = $answerToText($dataSnap['expense_alt_transport'] ?? ($dataSnap['expense_breakdown_local_transport'] ?? null));
            $uploadVal = $answerToText($dataSnap['extra_expense_upload'] ?? null);
            $groups[5] = [];
            $groups[5][] = ['q' => 'F+Ñr du m+Ñltider/forfriskninger under ventetiden? (Art. 20(2)(a))', 'a' => $answerToText($dataSnap['meal_offered'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'meal_offered'];
            $groups[5][] = ['q' => 'M+Ñltider ÔÇô bel+©b', 'a' => $mealAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_meals'];
            $groups[5][] = ['q' => 'Valuta', 'a' => $currency, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_meals'];
            $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
            $groups[5][] = ['q' => 'F+Ñr du hotel/indkvartering + transport dertil? (Art. 20(2)(b))', 'a' => $answerToText($dataSnap['hotel_offered'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'hotel_offered'];
            $groups[5][] = ['q' => 'Hotel ÔÇô bel+©b (samlet)', 'a' => $hotelAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_hotel'];
            $groups[5][] = ['q' => 'Valuta', 'a' => $currency, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_hotel'];
            $groups[5][] = ['q' => 'Antal n+ªtter', 'a' => $hotelNights, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_hotel_nights'];
            $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
            $groups[5][] = ['q' => 'Er toget blokeret p+Ñ sporet ÔÇö f+Ñr du transport v+ªk? (Art. 20(2)(c))', 'a' => $answerToText($dataSnap['blocked_train_alt_transport'] ?? null), 'art' => 'A) Tilbudt assistance', 'field' => 'blocked_train_alt_transport'];
            $groups[5][] = ['q' => 'Transport v+ªk ÔÇô bel+©b', 'a' => $blockedAmt, 'art' => 'A) Tilbudt assistance', 'field' => 'expense_breakdown_local_transport'];
            $groups[5][] = ['q' => 'Valuta', 'a' => $currency, 'art' => 'A) Tilbudt assistance', 'field' => '_currency_blocked'];
            $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'A) Tilbudt assistance', 'field' => 'extra_expense_upload'];
            $groups[5][] = ['q' => 'F+Ñr du alternative transporttjenester, hvis forbindelsen er afbrudt? (Art. 20(3))', 'a' => $answerToText($dataSnap['alt_transport_provided'] ?? null), 'art' => 'B) Alternative transporttjenester', 'field' => 'alt_transport_provided'];
            $groups[5][] = ['q' => 'Alternativ transport til destination ÔÇô bel+©b', 'a' => $altTransAmt, 'art' => 'B) Alternative transporttjenester', 'field' => 'expense_alt_transport'];
            $groups[5][] = ['q' => 'Valuta', 'a' => $currency, 'art' => 'B) Alternative transporttjenester', 'field' => '_currency_alt'];
            $groups[5][] = ['q' => 'Upload kvittering (PDF/JPG/PNG)', 'a' => $uploadVal, 'art' => 'B) Alternative transporttjenester', 'field' => 'extra_expense_upload'];
            $groupTitles = [
                3 => 'TRIN 3 -À Art. 6, Art. 9, Art. 12 og Art. 21-24',
                4 => 'TRIN 4 -À Afhj+ªlpning og krav',
                5 => 'TRIN 5 -À Assistance og udgifter',
                6 => 'TRIN 6 -À Kompensation (Art. 19)'
            ];
            $allLines = [];
            ksort($groups);
            // Exclude selected complaint/national/entitlement flags from TRIN 3 only
            $excludeTrin3Fields = ['continue_national_rules','complaint_channel_seen','complaint_already_filed','submit_via_official_channel','request_refund','request_comp_60','request_comp_120','request_expenses','bike_res_required','bike_followup_offer','fare_class_purchased','class_delivered_status','berth_seat_type','reserved_amenity_delivered'];
            foreach ($groups as $trin => $items) {
                $title = $groupTitles[$trin] ?? sprintf('TRIN %d', $trin);
                $allLines[] = $toPdf($title);
                // spacing after TRIN title
                $allLines[] = '';
                // Group by article label in insertion order
                $byArt = [];
                $order = [];
                foreach ($items as $it) {
                    $art = (string)($it['art'] ?? '');
                    $key = $art !== '' ? $art : '_';
                    if (!isset($byArt[$key])) { $byArt[$key] = []; $order[] = $key; }
                    $byArt[$key][] = $it;
                }
                // Group Art. 9 (1) subheaders together (no superheader)
                $a9Keys = [];
                $otherKeys = [];
                foreach ($order as $artKey) {
                    if ($artKey === '_') { continue; }
                    $isA9 = stripos($artKey, 'art. 9') !== false;
                    if ($isA9) { $a9Keys[] = $artKey; } else { $otherKeys[] = $artKey; }
                }
                // First, print items without any subheader ('_')
                if (isset($byArt['_'])) {
                    foreach ($byArt['_'] as $it) { if ($trin === 3 && in_array(($it['field'] ?? ''), $excludeTrin3Fields, true)) { continue; } $allLines[] = $toPdf(sprintf('%s: %s', $it['q'], $it['a'])); }
                }
                // Then non-Art.9(1) groups
                foreach ($otherKeys as $artKey) {
                    $itemsForArt = $byArt[$artKey];
                    if (stripos($artKey, 'Hurtigste rejse') !== false) {
                        $pref = [
                            'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                            'Var minimumsskiftetiden realistisk (missed station)?',
                            'S+Ñ du alternative forbindelser ved k+©bet?'
                        ];
                        $rank = function($q) use ($pref) { $i = array_search($q, $pref, true); return $i === false ? 999 : $i; };
                        usort($itemsForArt, function($a, $b) use ($rank) { return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? ''); });
                    } elseif (trim($artKey) === 'Refusion') {
                        $pref = [
                            '+ÿnsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?',
                            'Har du allerede anmodet om refusion?',
                            'Hvis ja, hvilken form for refusion?'
                        ];
                        $rank = function($q) use ($pref) { $i = array_search($q, $pref, true); return $i === false ? 999 : $i; };
                        usort($itemsForArt, function($a, $b) use ($rank) { return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? ''); });
                    } elseif (trim($artKey) === 'Oml+ªgning') {
                        $pref = [
                            '+ÿnsker du oml+ªgning p+Ñ tilsvarende vilk+Ñr ved f+©rst givne lejlighed?',
                            '+ÿnsker du oml+ªgning til et senere tidspunkt efter eget valg?',
                            $transportMode === 'air' ? 'Tilboed flyselskabet refusion eller ombooking efter article 8?' : 'Er du blevet informeret om mulighederne for oml+ªgning inden for 100 minutter efter planlagt afgang? (Art. 18(3))',
                            'K+©ber du selv en ny billet for at komme videre?',
                            'Kommer oml+ªgningen til at medf+©re ekstra udgifter for dig? (h+©jere klasse/andet transportmiddel)?',
                            'Er du blevet nedklassificeret eller regner med at blive det pga. oml+ªgningen?'
                        ];
                        $rank = function($q) use ($pref) { $i = array_search($q, $pref, true); return $i === false ? 999 : $i; };
                        usort($itemsForArt, function($a, $b) use ($rank) { return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? ''); });
                    }
                    $visibleItems = [];
                    foreach ($itemsForArt as $it) { if ($trin === 3 && in_array(($it['field'] ?? ''), $excludeTrin3Fields, true)) { continue; } $visibleItems[] = $it; }
                    if (!empty($visibleItems)) {
                        $allLines[] = '';
                        $allLines[] = $toPdf($artKey);
                        foreach ($visibleItems as $it) {
                            if (trim((string)($it['q'] ?? '')) === '' && trim((string)($it['a'] ?? '')) === '') { $allLines[] = ''; continue; }
                            $allLines[] = $toPdf(sprintf('%s: %s', $it['q'], $it['a']));
                            if ($trin === 5 && isset($it['field']) && is_string($it['field']) && $it['field'] === 'extra_expense_upload') { $allLines[] = ''; }
                        }
                    }
                }
                // Finally, all Art. 9(1) subheaders together
                foreach ($a9Keys as $artKey) {
                    $itemsForArt = $byArt[$artKey];
                    if (stripos($artKey, 'Hurtigste rejse') !== false) {
                        $pref = [
                            'Var rejsen markeret som "hurtigste" eller "anbefalet" ved k+©bet?',
                            'Var minimumsskiftetiden realistisk (missed station)?',
                            'S+Ñ du alternative forbindelser ved k+©bet?'
                        ];
                        $rank = function($q) use ($pref) { $i = array_search($q, $pref, true); return $i === false ? 999 : $i; };
                        usort($itemsForArt, function($a, $b) use ($rank) { return $rank($a['q'] ?? '') <=> $rank($b['q'] ?? ''); });
                    }
                    $visibleItems = [];
                    foreach ($itemsForArt as $it) { if ($trin === 3 && in_array(($it['field'] ?? ''), $excludeTrin3Fields, true)) { continue; } $visibleItems[] = $it; }
                    if (!empty($visibleItems)) {
                        $allLines[] = '';
                        $allLines[] = $toPdf($artKey);
                        foreach ($visibleItems as $it) {
                            if (trim((string)($it['q'] ?? '')) === '' && trim((string)($it['a'] ?? '')) === '') { $allLines[] = ''; continue; }
                            $allLines[] = $toPdf(sprintf('%s: %s', $it['q'], $it['a']));
                            if ($trin === 5 && isset($it['field']) && is_string($it['field']) && $it['field'] === 'extra_expense_upload') { $allLines[] = ''; }
                        }
                    }
                }
                $allLines[] = '';
            }
            $extra = trim($stringify($dataSnap['additional_info'] ?? $data['additional_info'] ?? ''));
            if ($extra !== '') {
                $allLines[] = $toPdf('');
                $paras = preg_split('/`r`n|\r|\n/', $extra);
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
                // Headings (lines starting with 'TRIN <n>') in bold and slightly larger
                $text = (string)$line;
                if (preg_match('/^\s*TRIN\s*\d/iu', $text)) {
                    $fpdi->SetFont('Helvetica', 'B', 10);
                } elseif (trim($text) !== '' && mb_strpos($text, ':') === false) {
                    // Article/subheaders (no colon) in bold
                    $fpdi->SetFont('Helvetica', 'B', 9);
                } else {
                    $fpdi->SetFont('Helvetica', '', 9);
                }
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
    private function normalizeTransportMode(?string $mode): string
    {
        $normalized = strtolower(trim((string)$mode));

        return in_array($normalized, ['rail', 'bus', 'ferry', 'air'], true) ? $normalized : 'rail';
    }

    private function formatRemedyChoice(?string $value, ?string $transportMode = null): ?string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        $mode = $this->normalizeTransportMode($transportMode);

        return match ($normalized) {
            'refund_return' => 'Tilbagebetaling',
            'reroute_soonest' => $mode === 'ferry' ? 'Ombooking hurtigst muligt' : 'Omlaegning hurtigst muligt',
            'reroute_later' => $mode === 'ferry' ? 'Ombooking senere (efter eget valg)' : 'Omlaegning senere (efter eget valg)',
            default => $normalized,
        };
    }

    private function formatTransportChoice(?string $value): ?string
    {
        $normalized = strtolower(trim((string)$value));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'rail' => 'Tog',
            'bus' => 'Bus',
            'ferry' => 'Faerge',
            'air' => 'Fly',
            'taxi' => 'Taxi/minibus',
            'rideshare' => 'Samkoersel/rideshare',
            'other' => 'Andet',
            default => trim((string)$value),
        };
    }

    private function formatResolutionEndpoint(?string $value, ?string $transportMode = null): ?string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }

        $mode = $this->normalizeTransportMode($transportMode);

        return match ($normalized) {
            'nearest_station' => match ($mode) {
                'ferry' => 'Naermeste havn/terminal',
                'air' => 'Naermeste lufthavn',
                'bus' => 'Naermeste stoppested/terminal',
                default => 'Naermeste station',
            },
            'other_departure_point' => 'Et andet egnet afgangssted',
            'final_destination' => 'Mit endelige bestemmelsessted',
            default => $normalized,
        };
    }

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
                // Section 6: Additional information (arguments/evidence) ÔÇô wide multiline area
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

    /**
     * Build a ferry claim letter PDF from the stored flow state.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $formSess
     * @param array<string,mixed> $metaSess
     * @param array<string,mixed> $incidentSess
     * @param array<string,mixed> $computeSess
     * @param array<string,mixed> $flagsSess
     */
    private function buildFerryClaimLetterPdf(array $data, array $formSess, array $metaSess, array $incidentSess, array $computeSess, array $flagsSess): string
    {
        $multimodal = (array)($metaSess['_multimodal'] ?? []);
        $ferryScope = (array)($multimodal['ferry_scope'] ?? []);
        $ferryContract = (array)($multimodal['ferry_contract'] ?? []);
        $ferryRights = (array)($multimodal['ferry_rights'] ?? []);
        $claimDirection = (array)($multimodal['claim_direction'] ?? []);

        $pick = function (array $sources, array $keys, string $default = ''): string {
            foreach ($keys as $key) {
                foreach ($sources as $src) {
                    if (is_array($src) && array_key_exists($key, $src) && trim((string)$src[$key]) !== '') {
                        return trim((string)$src[$key]);
                    }
                }
            }
            return $default;
        };
        $pickNested = function (array $src, array $path, string $default = '') use (&$pickNested): string {
            $cur = $src;
            foreach ($path as $key) {
                if (!is_array($cur) || !array_key_exists($key, $cur)) {
                    return $default;
                }
                $cur = $cur[$key];
            }
            $val = trim((string)$cur);
            return $val !== '' ? $val : $default;
        };
        $normalizeMoney = function (mixed $raw, string $defaultCurrency = 'EUR'): array {
            $rawStr = trim((string)$raw);
            $currency = strtoupper(trim($defaultCurrency));
            if ($currency === '') {
                $currency = 'EUR';
            }
            if (preg_match('/\b(EUR|DKK|SEK|NOK|PLN|CZK|HUF|RON|BGN|GBP|USD)\b/i', $rawStr, $m)) {
                $currency = strtoupper($m[1]);
            } elseif (str_contains($rawStr, '€')) {
                $currency = 'EUR';
            } elseif (str_contains($rawStr, 'kr')) {
                $currency = 'DKK';
            }
            $num = preg_replace('/[^0-9,.\-]/', '', $rawStr);
            $amount = 0.0;
            if ($num !== '') {
                if (preg_match('/-?\d[\d.,]*/', $num, $m)) {
                    $guess = str_replace([' ', ','], ['', '.'], $m[0]);
                    $amount = (float)$guess;
                }
            }
            return [$amount, $currency];
        };
        $sumMoney = function (array $values, string $defaultCurrency = 'EUR') use ($normalizeMoney): array {
            $amount = 0.0;
            $currency = strtoupper(trim($defaultCurrency)) ?: 'EUR';
            foreach ($values as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                [$a, $c] = $normalizeMoney($v, $currency);
                if ($a > 0) {
                    $amount += $a;
                }
                if ($currency === 'EUR' && $c !== '') {
                    $currency = $c;
                }
            }
            return [$amount, $currency];
        };
        $yes = function (mixed $v): bool {
            $s = strtolower(trim((string)$v));
            return in_array($s, ['1', 'true', 'yes', 'ja', 'y'], true);
        };
        $firstNonEmpty = function (array $values, string $default = ''): string {
            foreach ($values as $v) {
                $s = trim((string)$v);
                if ($s !== '') {
                    return $s;
                }
            }
            return $default;
        };
        $formatDate = function (string $date, string $time = ''): string {
            $date = trim($date);
            $time = trim($time);
            if ($date === '' && $time === '') {
                return '';
            }
            if ($time === '') {
                return $date;
            }
            return trim($date . ' ' . $time);
        };
        $bulletList = function (array $items): string {
            $lines = [];
            foreach ($items as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $lines[] = '- ' . $item;
                }
            }
            return implode("\n", $lines);
        };
        $toPdf = function (string $s): string {
            if (!preg_match('//u', $s)) {
                return $s;
            }
            $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
            return $conv !== false ? $conv : $s;
        };

        $fullName = $firstNonEmpty([
            trim((string)($data['full_name'] ?? '')),
            trim((string)($formSess['firstName'] ?? '') . ' ' . (string)($formSess['lastName'] ?? '')),
            trim((string)($data['firstName'] ?? '') . ' ' . (string)($data['lastName'] ?? '')),
        ]);
        $email = $firstNonEmpty([
            (string)($data['contact_email'] ?? ''),
            (string)($data['email'] ?? ''),
            (string)($formSess['contact_email'] ?? ''),
        ]);
        $phone = $firstNonEmpty([
            (string)($data['contact_phone'] ?? ''),
            (string)($data['phone'] ?? ''),
            (string)($formSess['contact_phone'] ?? ''),
        ]);
        $address = trim((string)($data['address_line1'] ?? ($formSess['address_line1'] ?? '')));
        $city = trim((string)($data['address_city'] ?? ($formSess['address_city'] ?? '')));
        $postcode = trim((string)($data['address_postalCode'] ?? ($formSess['address_postalCode'] ?? '')));
        $country = trim((string)($data['address_country'] ?? ($formSess['address_country'] ?? '')));
        $fullAddress = trim(implode(', ', array_values(array_filter([
            $address,
            trim($postcode . ' ' . $city),
            $country,
        ], static fn($v) => trim((string)$v) !== ''))));

        $carrier = $firstNonEmpty([
            (string)($ferryContract['primary_claim_party_name'] ?? ''),
            (string)($data['operator'] ?? ''),
            (string)($formSess['operator'] ?? ''),
            (string)($computeSess['primary_claim_party_name'] ?? ''),
        ]);
        $carrierKnown = $carrier !== '';
        if (!$carrierKnown) {
            $carrier = 'Carrier / operator';
        }
        $carrierType = $firstNonEmpty([
            (string)($ferryContract['primary_claim_party'] ?? ''),
            (string)($computeSess['primary_claim_party'] ?? ''),
        ]);
        $bookingRef = $firstNonEmpty([
            (string)($data['booking_reference'] ?? ''),
            (string)($data['ticket_no'] ?? ''),
            (string)($formSess['ticket_no'] ?? ''),
            (string)($metaSess['_auto']['ticket_no']['value'] ?? ''),
        ]);
        $fromPort = $firstNonEmpty([
            (string)($data['dep_station'] ?? ''),
            (string)($formSess['dep_station'] ?? ''),
            (string)($metaSess['_auto']['dep_station']['value'] ?? ''),
        ]);
        $toPort = $firstNonEmpty([
            (string)($data['arr_station'] ?? ''),
            (string)($formSess['arr_station'] ?? ''),
            (string)($metaSess['_auto']['arr_station']['value'] ?? ''),
        ]);
        $depDate = $firstNonEmpty([
            (string)($data['dep_date'] ?? ''),
            (string)($formSess['dep_date'] ?? ''),
            (string)($metaSess['_auto']['dep_date']['value'] ?? ''),
        ]);
        $depTime = $firstNonEmpty([
            (string)($data['dep_time'] ?? ''),
            (string)($formSess['dep_time'] ?? ''),
        ]);
        $arrTime = $firstNonEmpty([
            (string)($data['arr_time'] ?? ''),
            (string)($formSess['arr_time'] ?? ''),
        ]);
        $arrivalDelayMinutes = (int)preg_replace('/[^0-9\-]/', '', (string)($data['arrival_delay_minutes'] ?? ($formSess['arrival_delay_minutes'] ?? '0')));
        if ($arrivalDelayMinutes < 0) {
            $arrivalDelayMinutes = 0;
        }
        $plannedDurationMinutes = (int)preg_replace('/[^0-9\-]/', '', (string)($data['scheduled_journey_duration_minutes'] ?? ($formSess['scheduled_journey_duration_minutes'] ?? '0')));
        if ($plannedDurationMinutes < 0) {
            $plannedDurationMinutes = 0;
        }
        [$ticketAmount, $ticketCurrency] = $normalizeMoney((string)($data['price'] ?? ($formSess['price'] ?? '0')), (string)($data['price_currency'] ?? ($formSess['price_currency'] ?? 'EUR')));
        $ticketCurrency = strtoupper($ticketCurrency ?: 'EUR');
        if ($ticketCurrency === 'AUTO') {
            $ticketCurrency = 'EUR';
        }

        $incidentMain = $firstNonEmpty([
            (string)($data['incident_main'] ?? ''),
            (string)($incidentSess['main'] ?? ''),
            (string)($metaSess['incident']['main'] ?? ''),
        ]);
        if ($incidentMain === '') {
            $incidentMain = !empty($data['missed_connection']) ? 'missed_connection' : 'delay';
        }
        $incidentReasons = [];
        if ($incidentMain !== '') {
            $incidentReasons[] = ucfirst(str_replace('_', ' ', $incidentMain));
        }
        if (!empty($data['reason_delay'])) {
            $incidentReasons[] = 'Forsinkelse';
        }
        if (!empty($data['reason_cancellation'])) {
            $incidentReasons[] = 'Aflysning';
        }
        if (!empty($data['reason_missed_conn']) || !empty($data['missed_connection'])) {
            $incidentReasons[] = 'Missed connection';
        }
        $incidentConsequence = $firstNonEmpty([
            (string)($data['ferry_incident_consequence'] ?? ''),
            (string)($incidentSess['consequence'] ?? ''),
        ]);
        if ($incidentConsequence === '') {
            if ($incidentMain === 'cancellation') {
                $incidentConsequence = 'The service could not be performed as planned.';
            } elseif ($arrivalDelayMinutes > 0) {
                $incidentConsequence = 'The journey arrived with a significant delay.';
            } else {
                $incidentConsequence = 'My rights under Regulation (EU) No 1177/2010 were triggered.';
            }
        }

        $remedyChoice = (string)($data['remedyChoice'] ?? ($formSess['remedyChoice'] ?? ''));
        if ($remedyChoice === '') {
            if (!empty($data['ferry_refund_requested'])) {
                $remedyChoice = 'refund_return';
            } elseif (!empty($data['ferry_reroute_choice'])) {
                $remedyChoice = (string)$data['ferry_reroute_choice'];
            }
        }
        $remedyLabel = (string)($this->formatRemedyChoice($remedyChoice, 'ferry') ?? $remedyChoice);

        $ferryBand = (string)($ferryRights['art19_comp_band'] ?? ($flagsSess['ferry_art19_comp_band'] ?? ''));
        $bandPct = is_numeric($ferryBand) ? (float)$ferryBand : 0.0;
        $art19Active = !empty($ferryRights['gate_art19']) || $bandPct > 0;
        $compAmount = ($art19Active && $ticketAmount > 0 && $bandPct > 0) ? round($ticketAmount * ($bandPct / 100), 2) : 0.0;

        $refundRequested = $yes($data['ferry_refund_requested'] ?? ($data['refund_requested'] ?? false)) || $remedyChoice === 'refund_return';
        $returnToOriginAmount = 0.0;
        $rerouteExtraAmount = 0.0;
        $selfPaidReturn = (string)($data['ferry_return_to_departure_port_expense'] ?? ($data['return_to_origin_expense'] ?? ''));
        if ($selfPaidReturn !== '') {
            [$returnToOriginAmount] = $normalizeMoney($data['ferry_return_to_departure_port_amount'] ?? ($data['return_to_origin_amount'] ?? 0), $ticketCurrency);
        }
        if (!empty($data['reroute_extra_costs']) || !empty($data['reroute_extra_costs_amount'])) {
            [$rerouteExtraAmount] = $normalizeMoney($data['reroute_extra_costs_amount'] ?? 0, $ticketCurrency);
        }

        [$mealsAmount] = $sumMoney([
            $data['meal_self_paid_amount'] ?? '',
            $data['ferry_refreshments_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$hotelAmount] = $sumMoney([
            $data['hotel_self_paid_amount'] ?? '',
            $data['ferry_hotel_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$hotelTransportAmount] = $sumMoney([
            $data['hotel_transport_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$altAmount] = $sumMoney([
            $data['alt_self_paid_amount'] ?? '',
            $data['blocked_self_paid_amount'] ?? '',
            $data['blocked_self_paid_transport_amount'] ?? '',
        ], $ticketCurrency);
        [$otherAmount] = $sumMoney([
            $data['expense_other'] ?? '',
            $data['expense_breakdown_other_amounts'] ?? '',
        ], $ticketCurrency);
        $assistanceAmount = round($mealsAmount + $hotelAmount + $hotelTransportAmount + $altAmount + $otherAmount, 2);
        $refundAmount = $refundRequested ? $ticketAmount : 0.0;
        $art18ExtraAmount = round($returnToOriginAmount + $rerouteExtraAmount, 2);
        $totalClaim = round($refundAmount + $art18ExtraAmount + $assistanceAmount + $compAmount, 2);

        $recommendations = [];
        foreach ((array)($claimDirection['recommended_documents'] ?? []) as $doc) {
            $doc = trim((string)$doc);
            if ($doc !== '') {
                $recommendations[] = $doc;
            }
        }

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);

        $write = function (string $text, string $font = 'Arial', string $style = '', int $size = 11) use ($pdf, $toPdf): void {
            $pdf->SetFont($font, $style, $size);
            $pdf->MultiCell(0, 6, $toPdf($text));
        };
        $section = function (string $title) use ($pdf, $toPdf): void {
            $pdf->Ln(1);
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->Cell(0, 8, $toPdf($title), 0, 1);
        };
        $kv = function (string $label, string $value) use ($pdf, $toPdf): void {
            if (trim($value) === '') {
                return;
            }
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(58, 6, $toPdf($label) . ':', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6, $toPdf($value));
        };

        $write('Subject: Claim under Regulation (EU) No 1177/2010 - Ferry Passenger Rights', 'Arial', 'B', 14);
        $pdf->Ln(1);
        $write('To: ' . ($carrierKnown ? $carrier : 'Carrier / operator'), 'Arial', '', 11);
        $write('Please treat this as a formal ferry passenger rights claim based on the journey and disruption information supplied in my case.', 'Arial', '', 10);

        $section('Passenger details');
        $kv('Name', $fullName);
        $kv('Email', $email);
        $kv('Phone', $phone);
        $kv('Address', $fullAddress);
        $kv('Booking reference', $bookingRef);

        $section('Journey details');
        if ($carrierKnown) {
            $kv('Carrier / operator', $carrier);
        }
        if ($carrierType !== '' && !in_array(strtolower($carrierType), ['carrier', 'manual_review'], true)) {
            $kv('Claim channel', $carrierType);
        }
        $kv('Route', trim($fromPort . ' -> ' . $toPort));
        $kv('Scheduled departure', $formatDate($depDate, $depTime));
        $kv('Scheduled arrival', $formatDate($depDate, $arrTime));
        if ($arrivalDelayMinutes > 0 && $incidentMain !== 'cancellation') {
            $kv('Arrival delay (minutes)', (string)$arrivalDelayMinutes);
        }
        $kv('Planned journey duration (minutes)', (string)$plannedDurationMinutes);
        $kv('Ticket price', number_format($ticketAmount, 2) . ' ' . $ticketCurrency);

        $section('Incident');
        $kv('Incident type', trim(implode(', ', $incidentReasons)) !== '' ? implode(', ', $incidentReasons) : 'Delay');
        if ($incidentMain !== 'cancellation') {
            $kv('Delay basis', $arrivalDelayMinutes > 0 ? (string)$arrivalDelayMinutes . ' minutes' : 'Not stated');
        }
        $kv('Consequence', $incidentConsequence);
        $kv('Scope', !empty($ferryScope['regulation_applies']) ? 'Regulation applies on the stated facts' : 'Regulation unclear / not applicable');
        if (!empty($ferryScope['scope_exclusion_reason'])) {
            $kv('Scope reason', (string)$ferryScope['scope_exclusion_reason']);
        }

        $section('Rights invoked');
        $kv('Article 18', $remedyLabel !== '' ? $remedyLabel : 'Not selected');
        if (!empty($ferryRights['gate_art16_notice'])) {
            $kv('Article 16', 'Relevant');
        }
        if (!empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel'])) {
            $kv('Article 17', 'Relevant');
        }
        if (!empty($ferryRights['gate_art18'])) {
            $kv('Article 18 gate', 'Relevant');
        }
        $kv('Article 19', $art19Active ? ($bandPct > 0 ? 'Relevant - ' . rtrim(rtrim(number_format($bandPct, 2), '0'), '.') . '%' : 'Relevant') : 'Not activated');

        $section('Claim amounts');
        $kv('Refund / reimbursement', number_format($refundAmount, 2) . ' ' . $ticketCurrency);
        $kv('Rerouting / return costs', number_format($art18ExtraAmount, 2) . ' ' . $ticketCurrency);
        $kv('Assistance expenses', number_format($assistanceAmount, 2) . ' ' . $ticketCurrency);
        if ($mealsAmount > 0) {
            $kv('Meals / refreshments', number_format($mealsAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($hotelAmount > 0) {
            $kv('Hotel / accommodation', number_format($hotelAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($hotelTransportAmount > 0) {
            $kv('Transport to / from hotel', number_format($hotelTransportAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($altAmount > 0) {
            $kv('Alternative transport', number_format($altAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($otherAmount > 0) {
            $kv('Other expenses', number_format($otherAmount, 2) . ' ' . $ticketCurrency);
        }
        $kv('Article 19 compensation', number_format($compAmount, 2) . ' ' . $ticketCurrency);
        $kv('Total claim amount', number_format($totalClaim, 2) . ' ' . $ticketCurrency);
        $write('Legal conclusion: I therefore claim the amounts above under Regulation (EU) No 1177/2010 and request reimbursement in money, not vouchers, unless I explicitly agree otherwise.', 'Arial', 'B', 10);
        $write('Please respond within the deadlines set out in Article 24 of Regulation (EU) No 1177/2010.', 'Arial', '', 10);

        $section('Documentation');
        if (!empty($recommendations)) {
            $write('Supporting documentation: ' . implode(', ', $recommendations), 'Arial', '', 10);
        }
        $write('I request payment in money, not vouchers, unless I explicitly agree otherwise.', 'Arial', '', 10);
        $write('Kind regards,', 'Arial', '', 10);
        $write($fullName !== '' ? $fullName : 'Passenger', 'Arial', 'B', 11);

        return $pdf->Output('S');
    }

    /**
     * Build a bus claim letter PDF from the stored flow state.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $formSess
     * @param array<string,mixed> $metaSess
     * @param array<string,mixed> $incidentSess
     * @param array<string,mixed> $computeSess
     * @param array<string,mixed> $flagsSess
     */
    private function buildBusClaimLetterPdf(array $data, array $formSess, array $metaSess, array $incidentSess, array $computeSess, array $flagsSess): string
    {
        $multimodal = (array)($metaSess['_multimodal'] ?? []);
        $busScope = (array)($multimodal['bus_scope'] ?? []);
        $busContract = (array)($multimodal['bus_contract'] ?? []);
        $busRights = (array)($multimodal['bus_rights'] ?? []);
        $busPmrRights = (array)($multimodal['bus_pmr_rights'] ?? []);
        $claimDirection = (array)($multimodal['claim_direction'] ?? []);

        $firstNonEmpty = function (array $values, string $default = ''): string {
            foreach ($values as $v) {
                $s = trim((string)$v);
                if ($s !== '') {
                    return $s;
                }
            }
            return $default;
        };
        $yes = function (mixed $v): bool {
            $s = strtolower(trim((string)$v));
            return in_array($s, ['1', 'true', 'yes', 'ja', 'y'], true);
        };
        $normalizeMoney = function (mixed $raw, string $defaultCurrency = 'EUR'): array {
            $rawStr = trim((string)$raw);
            $currency = strtoupper(trim($defaultCurrency)) ?: 'EUR';
            if (preg_match('/\b(EUR|DKK|SEK|NOK|PLN|CZK|HUF|RON|BGN|GBP|USD)\b/i', $rawStr, $m)) {
                $currency = strtoupper($m[1]);
            } elseif (str_contains($rawStr, '€')) {
                $currency = 'EUR';
            } elseif (str_contains($rawStr, 'kr')) {
                $currency = 'DKK';
            }
            $amount = 0.0;
            $num = preg_replace('/[^0-9,.\-]/', '', $rawStr);
            if ($num !== '' && preg_match('/-?\d[\d.,]*/', $num, $m)) {
                $guess = str_replace([' ', ','], ['', '.'], $m[0]);
                $amount = (float)$guess;
            }
            return [$amount, $currency];
        };
        $sumMoney = function (array $values, string $defaultCurrency = 'EUR') use ($normalizeMoney): array {
            $amount = 0.0;
            $currency = strtoupper(trim($defaultCurrency)) ?: 'EUR';
            foreach ($values as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                [$a, $c] = $normalizeMoney($v, $currency);
                $amount += max(0.0, $a);
                if ($currency === 'EUR' && $c !== '') {
                    $currency = $c;
                }
            }
            return [$amount, $currency];
        };
        $toPdf = function (string $s): string {
            if (!preg_match('//u', $s)) {
                return $s;
            }
            $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
            return $conv !== false ? $conv : $s;
        };
        $firstNonEmptyArray = function (array $values, string $default = ''): string {
            foreach ($values as $v) {
                $s = trim((string)$v);
                if ($s !== '') {
                    return $s;
                }
            }
            return $default;
        };
        $formatDate = function (string $date, string $time = ''): string {
            $date = trim($date);
            $time = trim($time);
            if ($date === '' && $time === '') {
                return '';
            }
            return trim($date . ($time !== '' ? ' ' . $time : ''));
        };

        $fullName = $firstNonEmpty([
            trim((string)($data['full_name'] ?? '')),
            trim((string)($formSess['firstName'] ?? '') . ' ' . (string)($formSess['lastName'] ?? '')),
            trim((string)($data['firstName'] ?? '') . ' ' . (string)($data['lastName'] ?? '')),
        ]);
        $email = $firstNonEmpty([
            (string)($data['contact_email'] ?? ''),
            (string)($data['email'] ?? ''),
            (string)($formSess['contact_email'] ?? ''),
        ]);
        $phone = $firstNonEmpty([
            (string)($data['contact_phone'] ?? ''),
            (string)($data['phone'] ?? ''),
            (string)($formSess['contact_phone'] ?? ''),
        ]);
        $address = trim((string)($data['address_line1'] ?? ($formSess['address_line1'] ?? '')));
        $city = trim((string)($data['address_city'] ?? ($formSess['address_city'] ?? '')));
        $postcode = trim((string)($data['address_postalCode'] ?? ($formSess['address_postalCode'] ?? '')));
        $country = trim((string)($data['address_country'] ?? ($formSess['address_country'] ?? '')));
        $fullAddress = trim(implode(', ', array_values(array_filter([
            $address,
            trim($postcode . ' ' . $city),
            $country,
        ], static fn($v) => trim((string)$v) !== ''))));

        $carrier = $firstNonEmpty([
            (string)($busContract['primary_claim_party_name'] ?? ''),
            (string)($data['operator'] ?? ''),
            (string)($formSess['operator'] ?? ''),
            (string)($computeSess['primary_claim_party_name'] ?? ''),
        ], 'Unknown carrier (to be confirmed)');
        if (preg_match('/^flix$/i', $carrier)) {
            $carrier = $firstNonEmpty([
                (string)($data['operator_product'] ?? ''),
                (string)($formSess['operator_product'] ?? ''),
                $carrier,
            ], $carrier);
        }
        $carrierType = $firstNonEmpty([
            (string)($busContract['primary_claim_party'] ?? ''),
            (string)($computeSess['primary_claim_party'] ?? ''),
        ]);
        $bookingRef = $firstNonEmpty([
            (string)($data['booking_reference'] ?? ''),
            (string)($data['ticket_no'] ?? ''),
            (string)($formSess['ticket_no'] ?? ''),
            (string)($metaSess['_auto']['ticket_no']['value'] ?? ''),
        ]);
        $fromStop = $firstNonEmpty([
            (string)($data['dep_station'] ?? ''),
            (string)($formSess['dep_station'] ?? ''),
            (string)($metaSess['_auto']['dep_station']['value'] ?? ''),
        ]);
        $toStop = $firstNonEmpty([
            (string)($data['arr_station'] ?? ''),
            (string)($formSess['arr_station'] ?? ''),
            (string)($metaSess['_auto']['arr_station']['value'] ?? ''),
        ]);
        $depDate = $firstNonEmpty([
            (string)($data['dep_date'] ?? ''),
            (string)($formSess['dep_date'] ?? ''),
            (string)($metaSess['_auto']['dep_date']['value'] ?? ''),
        ]);
        $depTime = $firstNonEmpty([
            (string)($data['dep_time'] ?? ''),
            (string)($formSess['dep_time'] ?? ''),
        ]);
        $arrTime = $firstNonEmpty([
            (string)($data['arr_time'] ?? ''),
            (string)($formSess['arr_time'] ?? ''),
        ]);
        $distanceKm = (int)preg_replace('/[^0-9\-]/', '', (string)($data['scheduled_distance_km'] ?? ($formSess['scheduled_distance_km'] ?? '0')));
        if ($distanceKm < 0) {
            $distanceKm = 0;
        }
        $arrivalDelayMinutes = (int)preg_replace('/[^0-9\-]/', '', (string)($data['arrival_delay_minutes'] ?? ($formSess['arrival_delay_minutes'] ?? '0')));
        if ($arrivalDelayMinutes < 0) {
            $arrivalDelayMinutes = 0;
        }
        [$ticketAmount, $ticketCurrency] = $normalizeMoney((string)($data['price'] ?? ($formSess['price'] ?? '0')), (string)($data['price_currency'] ?? ($formSess['price_currency'] ?? 'EUR')));
        if ($ticketCurrency === 'AUTO' || $ticketCurrency === '') {
            $ticketCurrency = 'EUR';
        }

        $incidentMain = $firstNonEmpty([
            (string)($data['incident_main'] ?? ''),
            (string)($incidentSess['main'] ?? ''),
            (string)($metaSess['incident']['main'] ?? ''),
        ]);
        if ($incidentMain === '') {
            $incidentMain = !empty($data['bus_pmr_boarding_refused']) ? 'denied_boarding' : (!empty($data['reason_cancellation']) ? 'cancellation' : 'departure_delay');
        }
        $incidentNotes = [];
        if ($incidentMain !== '') {
            $incidentNotes[] = ucfirst(str_replace('_', ' ', $incidentMain));
        }
        if (!empty($data['reason_delay'])) {
            $incidentNotes[] = 'Departure delay';
        }
        if (!empty($data['reason_cancellation'])) {
            $incidentNotes[] = 'Cancellation';
        }
        if (!empty($data['reason_missed_conn']) || !empty($data['missed_connection'])) {
            $incidentNotes[] = 'Missed connection';
        }
        $incidentConsequence = $firstNonEmpty([
            (string)($data['bus_incident_consequence'] ?? ''),
            (string)($incidentSess['consequence'] ?? ''),
        ]);
        if ($incidentConsequence === '') {
            if ($incidentMain === 'cancellation') {
                $incidentConsequence = 'The service could not be performed as planned.';
            } elseif ($arrivalDelayMinutes > 0) {
                $incidentConsequence = 'The journey arrived with a significant delay.';
            } else {
                $incidentConsequence = 'My rights under Regulation (EU) No 181/2011 were triggered.';
            }
        }

        $remedyChoice = (string)($data['remedyChoice'] ?? ($formSess['remedyChoice'] ?? ''));
        if ($remedyChoice === '') {
            if (!empty($data['bus_refund_requested']) || !empty($formSess['bus_refund_requested'])) {
                $remedyChoice = 'refund_return';
            } elseif (!empty($data['bus_reroute_choice']) || !empty($formSess['bus_reroute_choice'])) {
                $remedyChoice = (string)($data['bus_reroute_choice'] ?? $formSess['bus_reroute_choice'] ?? '');
            } elseif (!empty($data['refund_requested']) || !empty($formSess['refund_requested'])) {
                $remedyChoice = 'refund_return';
            }
        }
        $remedyLabel = (string)($this->formatRemedyChoice($remedyChoice, 'bus') ?? $remedyChoice);

        $refundRequested = $yes(
            $data['bus_refund_requested']
                ?? $formSess['bus_refund_requested']
                ?? $data['refund_requested']
                ?? $formSess['refund_requested']
                ?? false
        ) || $remedyChoice === 'refund_return';
        $art19Active = !empty($busRights['gate_bus_reroute_refund']);
        $compPct = !empty($busRights['gate_bus_compensation_50']) ? 50.0 : 0.0;
        $compAmount = ($compPct > 0 && $ticketAmount > 0) ? round($ticketAmount * 0.50, 2) : 0.0;
        $refundAmount = $refundRequested ? $ticketAmount : 0.0;
        $showArrivalDelay = $arrivalDelayMinutes > 0 && $incidentMain !== 'cancellation';
        $routeLabel = trim(implode(' -> ', array_values(array_filter([$fromStop, $toStop], static fn($v) => trim((string)$v) !== ''))));
        $scheduledArrival = $arrTime !== '' ? $formatDate($depDate, $arrTime) : '';
        $showPmrLine = !empty($busPmrRights['gate_bus_pmr_assistance'])
            || !empty($busPmrRights['gate_bus_pmr_assistance_partial'])
            || !empty($busPmrRights['gate_bus_pmr_boarding_remedy']);

        [$mealsAmount] = $sumMoney([
            $data['meal_self_paid_amount'] ?? '',
            $data['bus_refreshments_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$hotelAmount] = $sumMoney([
            $data['hotel_self_paid_amount'] ?? '',
            $data['bus_hotel_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$hotelTransportAmount] = $sumMoney([
            $data['hotel_transport_self_paid_amount'] ?? '',
        ], $ticketCurrency);
        [$altAmount] = $sumMoney([
            $data['alt_self_paid_amount'] ?? '',
            $data['blocked_self_paid_amount'] ?? '',
            $data['blocked_self_paid_transport_amount'] ?? '',
        ], $ticketCurrency);
        [$otherAmount] = $sumMoney([
            $data['expense_other'] ?? '',
            $data['expense_breakdown_other_amounts'] ?? '',
        ], $ticketCurrency);
        [$rerouteAmount] = $sumMoney([
            $data['reroute_extra_costs_amount'] ?? '',
        ], $ticketCurrency);
        [$returnToOriginAmount] = $sumMoney([
            $data['bus_return_to_departure_stop_amount'] ?? '',
            $formSess['bus_return_to_departure_stop_amount'] ?? '',
            $data['return_to_origin_amount'] ?? '',
            $formSess['return_to_origin_amount'] ?? '',
        ], $ticketCurrency);
        $assistanceAmount = round($mealsAmount + $hotelAmount + $hotelTransportAmount + $altAmount + $otherAmount, 2);
        $art18ExtraAmount = round($rerouteAmount + $returnToOriginAmount, 2);
        $totalClaim = round($refundAmount + $art18ExtraAmount + $assistanceAmount + $compAmount, 2);

        $recommendations = [];
        foreach ((array)($claimDirection['recommended_documents'] ?? []) as $doc) {
            $doc = trim((string)$doc);
            if ($doc !== '') {
                $recommendations[] = $doc;
            }
        }

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);

        $write = function (string $text, string $font = 'Arial', string $style = '', int $size = 11) use ($pdf, $toPdf): void {
            $pdf->SetFont($font, $style, $size);
            $pdf->MultiCell(0, 6, $toPdf($text));
        };
        $section = function (string $title) use ($pdf, $toPdf): void {
            $pdf->Ln(1);
            $pdf->SetFont('Arial', 'B', 13);
            $pdf->Cell(0, 8, $toPdf($title), 0, 1);
        };
        $kv = function (string $label, string $value) use ($pdf, $toPdf): void {
            if (trim($value) === '') {
                return;
            }
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(46, 6, $toPdf($label) . ':', 0, 0);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell(0, 6, $toPdf($value));
        };

        $write('Subject: Claim under Regulation (EU) No 181/2011 - Bus and coach passenger rights', 'Arial', 'B', 14);
        $pdf->Ln(1);
        $write('To: ' . $carrier, 'Arial', '', 11);
        $write('This is a formal bus passenger rights claim based on the journey and disruption information supplied in my case.', 'Arial', '', 10);

        $section('Passenger details');
        $kv('Name', $fullName);
        $kv('Email', $email);
        $kv('Phone', $phone);
        $kv('Address', $fullAddress);
        $kv('Booking reference', $bookingRef);

        $section('Journey details');
        $kv('Carrier / operator', $carrier);
        if ($carrierType !== '' && !in_array(strtolower($carrierType), ['carrier', 'manual_review'], true)) {
            $kv('Claim channel', $carrierType);
        }
        $kv('Route', $routeLabel);
        $kv('Scheduled departure', $formatDate($depDate, $depTime));
        $kv('Scheduled arrival', $scheduledArrival);
        $kv('Planned distance', $distanceKm > 0 ? $distanceKm . ' km' : '');
        if ($showArrivalDelay) {
            $kv('Arrival delay (minutes)', (string)$arrivalDelayMinutes);
        }
        $kv('Ticket price', number_format($ticketAmount, 2) . ' ' . $ticketCurrency);

        $section('Incident');
        $kv('Incident type', trim(implode(', ', $incidentNotes)) !== '' ? implode(', ', $incidentNotes) : 'Departure delay');
        $kv('Consequence', $incidentConsequence);
        $kv('Scope', !empty($busScope['regulation_applies']) ? 'Regulation applies on the stated facts' : 'Regulation unclear / not applicable');
        if (!empty($busScope['scope_exclusion_reason'])) {
            $kv('Scope reason', (string)$busScope['scope_exclusion_reason']);
        }

        $section('Rights invoked');
        $kv('Article 19', $art19Active ? 'Relevant' : 'Not activated');
        $kv('Article 20', !empty($busRights['gate_bus_info']) ? 'Relevant' : 'Not activated');
        $kv('Article 21', (!empty($busRights['gate_bus_assistance_refreshments']) || !empty($busRights['gate_bus_assistance_hotel'])) ? 'Relevant' : 'Not activated');
        if ($showPmrLine) {
            $kv('PMR / disability rights', 'Relevant');
        }
        if (!empty($busRights['gate_bus_compensation_50'])) {
            $kv('50% compensation gate', 'Relevant');
        }
        if (!empty($busRights['gate_bus_pmr_boarding_remedy'])) {
            $kv('PMR remedy gate', 'Relevant');
        }
        if ($remedyLabel !== '') {
            $kv('Chosen remedy', $remedyLabel);
        }

        $section('Claim amounts');
        $kv('Refund / reimbursement', number_format($refundAmount, 2) . ' ' . $ticketCurrency);
        $kv('Art. 19 / 50% compensation', number_format($compAmount, 2) . ' ' . $ticketCurrency);
        $kv('Rerouting / return costs', number_format($art18ExtraAmount, 2) . ' ' . $ticketCurrency);
        $kv('Assistance expenses', number_format($assistanceAmount, 2) . ' ' . $ticketCurrency);
        if ($mealsAmount > 0) {
            $kv('Meals / refreshments', number_format($mealsAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($hotelAmount > 0) {
            $kv('Hotel / accommodation', number_format($hotelAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($hotelTransportAmount > 0) {
            $kv('Transport to / from hotel', number_format($hotelTransportAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($altAmount > 0) {
            $kv('Alternative transport', number_format($altAmount, 2) . ' ' . $ticketCurrency);
        }
        if ($otherAmount > 0) {
            $kv('Other expenses', number_format($otherAmount, 2) . ' ' . $ticketCurrency);
        }
        $kv('Total claim amount', number_format($totalClaim, 2) . ' ' . $ticketCurrency);
        $write('Legal conclusion: I therefore claim the amounts above under Regulation (EU) No 181/2011 and request reimbursement in money, not vouchers, unless I explicitly agree otherwise.', 'Arial', 'B', 10);
        $write('Please respond within the deadlines set out in Article 27 of Regulation (EU) No 181/2011.', 'Arial', '', 10);

        $section('Documentation');
        if (!empty($recommendations)) {
            $write('Supporting documentation: ' . implode(', ', $recommendations), 'Arial', '', 10);
        }
        $write('I request payment in money, not vouchers, unless I explicitly agree otherwise.', 'Arial', '', 10);
        $write('Kind regards,', 'Arial', '', 10);
        $write($fullName !== '' ? $fullName : 'Passenger', 'Arial', 'B', 11);

        return $pdf->Output('S');
    }

    /**
     * @return array{0:float,1:string}
     */
    private function normalizeMoneyParts(mixed $raw, string $defaultCurrency = 'EUR'): array
    {
        $rawStr = trim((string)$raw);
        $currency = strtoupper(trim($defaultCurrency));
        if ($currency === '') {
            $currency = 'EUR';
        }
        if (preg_match('/\b(EUR|DKK|SEK|NOK|PLN|CZK|HUF|RON|BGN|GBP|USD)\b/i', $rawStr, $m)) {
            $currency = strtoupper($m[1]);
        } elseif (str_contains($rawStr, '€')) {
            $currency = 'EUR';
        } elseif (str_contains($rawStr, '£')) {
            $currency = 'GBP';
        } elseif (preg_match('/\bkr\b/i', $rawStr)) {
            $currency = 'DKK';
        }
        $amount = 0.0;
        if ($rawStr !== '') {
            $num = preg_replace('/[^0-9,.\-]/', '', $rawStr);
            if ($num !== '' && preg_match('/-?\d[\d.,]*/', $num, $m)) {
                $guess = str_replace([' ', ','], ['', '.'], $m[0]);
                $amount = (float)$guess;
            }
        }
        return [$amount, $currency];
    }

    /**
     * Load a national field map matched to the selected template source.
     * Looks under config/pdf/forms/<CC>/*.json and normalizes keys.
     * Returns array with numeric page keys and optional _meta.
     * @return array<int,array<string,mixed>>|null
     */
    private function loadNationalFieldMap(string $source): ?array
    {
        $cc = null;
        // Recognize FR not only by explicit tokens but also by G30 variants like g_30 / g-30
        if (preg_match('/(fr|france|sncf)/i', $source) || preg_match('/g[\s_\-]?30/i', $source)) { $cc = 'FR'; }
        elseif (preg_match('/(de|germany|deutsch|db|fahrgastrechte)/i', $source)) { $cc = 'DE'; }
        elseif (preg_match('/(it|italy|italia|trenitalia)/i', $source)) { $cc = 'IT'; }
        elseif (preg_match('/(dk|denmark|danmark|dsb)/i', $source)) { $cc = 'DK'; }
        elseif (preg_match('/(nl|netherlands|nederland|ns)/i', $source)) { $cc = 'NL'; }
        elseif (preg_match('/(es|spain|espa|renfe)/i', $source)) { $cc = 'ES'; }
        elseif (preg_match('/(air|air_travel_form|boarding\\s*pass|ec261|eu261)/i', $source)) { $cc = 'AIR'; }
        if ($cc === null) { return null; }
        // Support both uppercase and lowercase country folders (we have "fr" on disk)
        $dirUpper = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . $cc . DIRECTORY_SEPARATOR;
        $dirLower = CONFIG . 'pdf' . DIRECTORY_SEPARATOR . 'forms' . DIRECTORY_SEPARATOR . strtolower($cc) . DIRECTORY_SEPARATOR;
        $dir = is_dir($dirUpper) ? $dirUpper : $dirLower;
        // Prefer specific known mappings first, then generic names
        $candidates = [
            // Clean DK mapping (preferred if present)
            $dir . 'map_dk_dsb_basic_rejsetidsgaranti_clean.json',
            // Country-specific known files
            $dir . 'map_fr_sncf_g30.json',
            $dir . 'map_de_db_fahrgastrechte.json',
            $dir . 'map_it_trenitalia_compensation.json',
            $dir . 'map_dk_dsb_basic_rejsetidsgaranti.json',
            $dir . 'map_air_travel_form.json',
            // Generic fallbacks
            $dir . 'mapping_g30.json',
            $dir . 'mapping_' . strtolower($cc) . '.json',
            $dir . 'map_' . strtolower($cc) . '.json',
            $dir . 'mapping.json',
        ];
        $candidates = array_values(array_unique($candidates));
        foreach ($candidates as $p) {
            if (!is_file($p)) { continue; }
            $json = (string)file_get_contents($p);
            $arr = json_decode($json, true);
            if (!is_array($arr)) { continue; }
            // Accept multiple formats:
            // 1) { meta/_meta, 1:{...}, 2:{...} }
            // 2) { units, origin, pages:{ '1':{...}, '2':{...} } }
            $meta = [];
            if (isset($arr['meta']) && is_array($arr['meta'])) { $meta = array_merge($meta, $arr['meta']); }
            if (isset($arr['_meta']) && is_array($arr['_meta'])) { $meta = array_merge($meta, $arr['_meta']); }
            // Also accept top-level units/origin/file/note
            foreach (['units','origin','file','note'] as $mk) {
                if (isset($arr[$mk]) && !isset($meta[$mk])) { $meta[$mk] = $arr[$mk]; }
            }
            $pages = [];
            // Variant 1: numeric keys at top-level
            foreach ($arr as $k => $v) {
                if ($k === 'meta' || $k === '_meta' || $k === 'pages') { continue; }
                if (is_array($v) && (is_int($k) || ctype_digit((string)$k))) {
                    $pages[(int)$k] = $v;
                }
            }
            // Variant 2: nested under 'pages'
            if (empty($pages) && isset($arr['pages']) && is_array($arr['pages'])) {
                foreach ($arr['pages'] as $k => $v) {
                    if (is_array($v) && (is_int($k) || ctype_digit((string)$k))) {
                        $pages[(int)$k] = $v;
                    }
                }
            }
            if (!empty($pages)) {
                ksort($pages);
                if (!empty($meta)) { $pages['_meta'] = $meta; }
                return $pages;
            }
        }
        return null;
    }

    /**
     * Convert mapping coordinates to mm/top-left origin if needed.
     * Supports units: 'pt', 'pdf_points', 'mm'. origin: 'bottom-left'|'top-left'.
     * Returns [x_mm, y_mm, w_mm]
     */
    private function adaptCoordinates(array $cfg, float $pageHeightMm, array $meta, float $dxMm, float $dyMm): array
    {
        $x = (float)($cfg['x'] ?? 0);
        $y = (float)($cfg['y'] ?? 0);
        $w = isset($cfg['w']) ? (float)$cfg['w'] : 0.0;
        $units = strtolower((string)($meta['units'] ?? 'mm'));
        $origin = strtolower((string)($meta['origin'] ?? 'top-left'));
        if ($units === 'pt' || $units === 'pdf_points' || $units === 'points') {
            $mmPerPt = 25.4 / 72.0;
            $x *= $mmPerPt; $y *= $mmPerPt; $w *= $mmPerPt;
        }
        if ($origin === 'bottom-left') {
            $y = $pageHeightMm - $y;
        }
        $x += $dxMm; $y += $dyMm;
        return [$x, $y, $w];
    }

    /**
     * Augment data for the French G30 form based on session-derived fields.
     * @param array<string,mixed> $data
     * @param array<string,mixed> $formSess
     * @param array<string,mixed> $metaSess
     * @return array<string,mixed>
     */
    private function augmentG30Data(array $data, array $formSess, array $metaSess): array
    {
        $get = function(array $src, string $k, string $altK = '') {
            if (array_key_exists($k, $src) && $src[$k] !== null && $src[$k] !== '') { return $src[$k]; }
            if ($altK !== '' && array_key_exists($altK, $src) && $src[$altK] !== null && $src[$altK] !== '') { return $src[$altK]; }
            return null;
        };
        // Contact
        $data['surname'] = (string)($get($formSess, 'lastName') ?? '');
        $data['firstname'] = (string)($get($formSess, 'firstName') ?? '');
        $street = (string)($get($formSess, 'address_street') ?? '');
        $no = (string)($get($formSess, 'address_no') ?? '');
        $data['address_street'] = $street;
        $data['address_number'] = $no;
        $data['address_complement'] = (string)($get($formSess, 'address_complement') ?? '');
        $data['postcode'] = (string)($get($formSess, 'address_postalCode') ?? '');
        $data['city'] = (string)($get($formSess, 'address_city') ?? '');
        $data['country'] = (string)($get($formSess, 'address_country') ?? '');
        $data['email'] = (string)($get($formSess, 'contact_email') ?? ($get($formSess, 'email') ?? ''));
        $data['phone'] = (string)($get($formSess, 'contact_phone') ?? '');
        // Booking code / PNR
        $data['booking_reference'] = (string)($get($formSess, 'ticket_no') ?? ($metaSess['_auto']['ticket_no']['value'] ?? ''));
        // Travellers
        $trav = [];
        if (!empty($metaSess['_passengers_auto']) && is_array($metaSess['_passengers_auto'])) {
            foreach ((array)$metaSess['_passengers_auto'] as $p) {
                $name = trim((string)($p['name'] ?? ''));
                if ($name !== '') { $trav[] = $name; }
            }
        }
        if (empty($trav) && !empty($formSess['passenger']) && is_array($formSess['passenger'])) {
            foreach ((array)$formSess['passenger'] as $p) {
                $name = trim((string)($p['name'] ?? ''));
                if ($name !== '') { $trav[] = $name; }
            }
        }
        if (empty($trav)) {
            $fn = trim((string)($data['firstname'] ?? ''));
            $ln = trim((string)($data['surname'] ?? ''));
            $full = trim($fn . ' ' . $ln);
            if ($full !== '') { $trav[] = $full; }
        }
        $data['traveller_count'] = (string)count($trav);
        for ($i = 0; $i < min(9, count($trav)); $i++) {
            $data['traveller_' . ($i + 1)] = $trav[$i];
        }
        // Segments
        $segs = (array)($metaSess['_segments_auto'] ?? []);
        // Helper: classify French product into mapping slugs
        $classify = function(string $raw): string {
            $s = trim(strtolower($raw));
            if ($s === '') { return 'other'; }
            // remove diacritics (basic)
            $s = strtr($s, [
                'é' => 'e','ê' => 'e','è' => 'e','É' => 'e','ï' => 'i','î' => 'i','à' => 'a','ô' => 'o','ö' => 'o','â' => 'a'
            ]);
            // collapse whitespace
            $s = preg_replace('/\s+/', ' ', $s) ?? $s;
            // Normalization synonyms
            if (strpos($s, 'lyria') !== false) { return 'tgv_lyria'; }
            if (strpos($s, 'ouigo') !== false) { return 'ouigo'; }
            if (preg_match('/\bter\b/', $s)) { return 'ter'; }
            if (preg_match('/intercites?|\bic\b/', $s)) { return 'intercites'; }
            // Treat generic TGV as INOUI unless clearly LYRIA (handled above)
            if (strpos($s, 'inoui') !== false || strpos($s, 'tgv') !== false) { return 'tgv_inoui'; }
            return 'other';
        };
        $fillSeg = function(int $idx, array $seg) use (&$data, $classify) {
            $k = 'seg' . $idx . '_';
            $data[$k . 'train_no'] = (string)($seg['trainNo'] ?? ($seg['train'] ?? ($seg['train_no'] ?? '')));
            $data[$k . 'dep_station'] = (string)($seg['from'] ?? ($seg['depStation'] ?? ''));
            $data[$k . 'arr_station'] = (string)($seg['to'] ?? ($seg['arrStation'] ?? ''));
            $data[$k . 'dep_time'] = (string)($seg['schedDep'] ?? '');
            $data[$k . 'arr_time'] = (string)($seg['schedArr'] ?? '');
            $rawProd = (string)($seg['product'] ?? ($seg['trainCategory'] ?? ($seg['category'] ?? '')));
            $slug = $classify($rawProd);
            $all = ['tgv_inoui','intercites','ouigo','ter','tgv_lyria','other'];
            foreach ($all as $pSlug) {
                $data[$k . 'prod_' . $pSlug] = ($pSlug === $slug) ? '1' : '';
            }
        };
        if (!empty($segs)) {
            for ($i = 0; $i < min(3, count($segs)); $i++) {
                $fillSeg($i + 1, (array)$segs[$i]);
            }
        }
        // If no segments auto-detected, attempt to populate first segment from high-level operator_product & journey fields
        if (empty($segs)) {
            $fallbackSeg = [
                'trainNo' => (string)($formSess['train_no'] ?? ($metaSess['_auto']['train_no']['value'] ?? '')),
                'from' => (string)($formSess['dep_station'] ?? ($metaSess['_auto']['dep_station']['value'] ?? '')),
                'to' => (string)($formSess['arr_station'] ?? ($metaSess['_auto']['arr_station']['value'] ?? '')),
                'schedDep' => (string)($formSess['dep_time'] ?? ($metaSess['_auto']['dep_time']['value'] ?? '')),
                'schedArr' => (string)($formSess['arr_time'] ?? ($metaSess['_auto']['arr_time']['value'] ?? '')),
                'product' => (string)($formSess['operator_product'] ?? ($metaSess['_auto']['operator_product']['value'] ?? '')),
            ];
            $fillSeg(1, $fallbackSeg);
        }
        if (!isset($data['loyalty_gv'])) {
            $data['loyalty_gv'] = (string)($formSess['loyalty_sncf_gv'] ?? '');
        }
        return $data;
    }

    /**
     * Remove noisy debug/auto-detected keys from flow.form and relocate them to flow.meta.
     * Keeps form lean for TRIN 6 while preserving diagnostics in meta.
     */
    private function sanitizeFlowSession(array $form, array $meta): array
    {
        $prefixes = [
            '_auto','_segments','_ocr','_pmr_detection','_bike_detection','_ticket_type_detection','_class_detection',
            '_identifiers','_multi_tickets','_passengers_auto','_barcode','_mct_eval',
        ];
        $debugKeys = [
            'logs','extraction_provider','extraction_confidence',
            'saved_leg_class_purchased','saved_leg_class_delivered','saved_leg_reservation_purchased','saved_leg_reservation_delivered','saved_leg_downgraded','saved_leg_by_file',
            'single_txn_operator','single_booking_reference','shared_pnr_scope',
            'pmr_promised_missing','pmr_delivered_status','mct_realistic','pm_bike_involved',
        ];

        foreach ($form as $k => $v) {
            $move = in_array($k, $debugKeys, true);
            if (!$move) {
                foreach ($prefixes as $p) {
                    if (strpos($k, $p) === 0) { $move = true; break; }
                }
            }
            if (!$move) { continue; }
            if ($k === 'logs') {
                $meta['logs'] = array_merge($meta['logs'] ?? [], (array)$v);
            } elseif (isset($meta[$k]) && is_array($meta[$k]) && is_array($v)) {
                // prefer the more recent form value but keep older meta keys
                $meta[$k] = $v + $meta[$k];
            } elseif (!array_key_exists($k, $meta)) {
                $meta[$k] = $v;
            }
            unset($form[$k]);
        }

        return [$form, $meta];
    }
        

    /**
     * Try to find a national template in webroot/files/Nationale PDF former by country heuristics.
     */
    private function findNationalInAltDir(string $country): ?string
    {
        $country = strtoupper($country);
        $altDirs = [
            WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'Nationale PDF former',
            WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'Nationale pdf former',
        ];
        $syn = [
            'FR' => ['fr', 'france', 'sncf'],
            'DE' => ['de', 'germany', 'deutsch', 'fahrgastrechte', 'db'],
            'IT' => ['it', 'italy', 'italia', 'trenitalia'],
            'DK' => ['dk', 'denmark', 'danmark', 'dsb'],
            'NL' => ['nl', 'netherlands', 'nederland', 'ns'],
            'ES' => ['es', 'spain', 'espa', 'renfe'],
        ];
        $keys = $syn[$country] ?? [strtolower($country)];
        foreach ($altDirs as $altDir) {
            if (!is_dir($altDir)) { continue; }
            foreach ($keys as $k) {
                $hits = glob($altDir . DIRECTORY_SEPARATOR . '*' . $k . '*.pdf') ?: [];
                if (!empty($hits)) { return $hits[0]; }
                $hits = glob($altDir . DIRECTORY_SEPARATOR . '*' . strtoupper($k) . '*.pdf') ?: [];
                if (!empty($hits)) { return $hits[0]; }
            }
        }
        return null;
    }
}








