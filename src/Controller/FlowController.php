<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ExemptionResolver;
use App\Service\PriceHintsService;

class FlowController extends AppController
{
    private function isAdmin(): bool
    {
        try {
            return (bool)$this->request->getSession()->read('admin.mode');
        } catch (\Throwable $e) { return false; }
    }
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('App');
        $this->autoRender = true;
    }

    /**
     * Infer a suggestion for the "EU only" scope based on journey/meta hints.
     * Returns [string $suggested, string $reason], where suggested is 'yes'|'no'|'unknown'.
     */
    private function suggestEuOnly(array $journey, array $meta): array
    {
        // EU member states (strict EU; extend to EEA if policy changes)
        $EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE'];
        $euSet = array_fill_keys($EU, true);

        // Collect country candidates from segments, journey, and OCR auto
        $countries = [];
        $segs = (array)($journey['segments'] ?? []);
        foreach ($segs as $s) {
            $c = strtoupper((string)($s['country'] ?? ''));
            if ($c !== '') { $countries[$c] = true; }
        }
        $jc = strtoupper((string)($journey['country']['value'] ?? ''));
        if ($jc !== '') { $countries[$jc] = true; }
        $oc = strtoupper((string)($meta['_auto']['operator_country']['value'] ?? ($meta['operator_country'] ?? '')));
        if ($oc !== '') { $countries[$oc] = true; }

        if (empty($countries)) {
            return ['unknown', 'Ingen lande kunne udledes (mangler stations-/landedata)'];
        }

        $hasEU = false; $hasNonEU = false; $all = array_keys($countries);
        foreach ($all as $c) {
            if (isset($euSet[$c])) { $hasEU = true; } else { $hasNonEU = true; }
        }

        if ($hasEU && !$hasNonEU) {
            return ['yes', 'Alle fundne lande er i EU: ' . implode(', ', $all)];
        }
        if ($hasEU && $hasNonEU) {
            return ['no', 'Blandet EU/ikke-EU: ' . implode(', ', $all)];
        }
        // No EU countries found
        return ['no', 'Ingen EU-lande fundet: ' . implode(', ', $all)];
    }

    public function start(): \Cake\Http\Response|null
    {
        // Entry: choose EU-only and set travel state (no OCR/JSON, no Art. 9 here)
        if ($this->request->is('post')) {
            $journey = (array)$this->request->getSession()->read('flow.journey') ?: [];
            $meta = (array)$this->request->getSession()->read('flow.meta') ?: [];
            $compute = (array)$this->request->getSession()->read('flow.compute') ?: [];
            // Only admins can set eu_only; otherwise preserve existing or default to true
            $euPost = $this->request->getData('eu_only');
            if ($this->isAdmin() && $euPost !== null) {
                $compute['euOnly'] = (bool)$this->truthy($euPost);
            } elseif (!array_key_exists('euOnly', $compute)) {
                $compute['euOnly'] = true;
            }
            // TRIN 1: Travel state (completed / ongoing / before_start)
            $flags = (array)$this->request->getSession()->read('flow.flags') ?: [];
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            $this->request->getSession()->write('flow.journey', $journey);
            $this->request->getSession()->write('flow.meta', $meta);
            $this->request->getSession()->write('flow.compute', $compute);
            $this->request->getSession()->write('flow.flags', $flags);
            return $this->redirect(['action' => 'entitlements']);
        }
        $compute = (array)$this->request->getSession()->read('flow.compute') ?: ['euOnly' => true];
        $this->set('isAdmin', $this->isAdmin());
        $this->set('compute', $compute);
        return null;
    }

    public function journey(): \Cake\Http\Response|null
    {
        $sess = $this->request->getSession();
        if ($this->request->is('get')) {
            // Only clear on explicit request (?reset=1); preserve TRIN 1 flags from Start
            $doReset = $this->truthy($this->request->getQuery('reset'));
            if ($doReset) { $sess->delete('flow'); }
        }
        $journey = (array)$sess->read('flow.journey') ?: [];
        $meta = (array)$sess->read('flow.meta') ?: [];
        $compute = (array)$sess->read('flow.compute') ?: [];
        $form = (array)$sess->read('flow.form') ?: [];
        $incident = (array)$sess->read('flow.incident') ?: [];
        $flags = (array)$sess->read('flow.flags') ?: [];
        $contractOptions = (array)($meta['contract_options'] ?? []);
        $contractWarning = '';

        // Apply contract filtering (separate model) or warn if missing selection
        $cm = (string)($form['contract_model'] ?? '');
        $pcid = (string)($form['problem_contract_id'] ?? '');
        if ($cm === 'separate') {
            if ($pcid !== '' && isset($contractOptions[$pcid]) && !empty($contractOptions[$pcid]['segments'])) {
                if (empty($meta['_segments_all'])) { $meta['_segments_all'] = $meta['_segments_auto'] ?? []; }
                $meta['_segments_auto'] = (array)$contractOptions[$pcid]['segments'];
                $journey['segments'] = (array)$contractOptions[$pcid]['segments'];
            } elseif ($pcid === '') {
                $contractWarning = 'Vælg den kontrakt, der havde problemet (Art. 12 i Trin 2).';
            }
        } else {
            // Restore original segments if previously filtered
            if (!empty($meta['_segments_all'])) {
                $meta['_segments_auto'] = (array)$meta['_segments_all'];
                $journey['segments'] = (array)$meta['_segments_all'];
            }
        }
                // Default PMR/bike based on OCR/LLM (only for initial GET)
        if ($this->request->is('get')) {
            // Default to "no" unless the user already set a value (OCR/LLM only informs later)
            if (!isset($form['bike_was_present'])) { $form['bike_was_present'] = 'no'; }
            if (!isset($form['pmr_user'])) { $form['pmr_user'] = 'no'; }
            // Nye defaults (Trin 3): downgrade/disruption/missed connection
            $form['downgrade_occurred'] = $form['downgrade_occurred'] ?? 'no';
            $form['disruption_flag'] = $form['disruption_flag'] ?? 'no';
            if (!isset($incident['missed'])) { $incident['missed'] = ''; }
            $sess->write('flow.form', $form);
            $sess->write('flow.incident', $incident);
        }
        if ($this->request->is('post')) {
            $data = (array)$this->request->getData();
            $form = array_merge($form, $data);
            // Drop early numeric delay; use final delay later.
            // NOTE: 'known_delay' moved to TRIN 3 (entitlements) and 'extraordinary' moved to TRIN 6.
            // UI checkbox delayLikely60 removed ? rely on live data (delay minutes) and TRIN 6 band selection
            // TRIN 3: Incident selection (missed-connection now in TRIN 3)
            $main = (string)($data['incident_main'] ?? '');
            if (!in_array($main, ['delay','cancellation',''], true)) { $main = ''; }
            $incident['main'] = $main;
            $incident['missed'] = $this->truthy($data['incident_missed'] ?? false) ? 'yes' : '';
            if (isset($data['expected_delay_60'])) {
                $form['expected_delay_60'] = (string)$data['expected_delay_60'];
            }
            $this->request->getSession()->write('flow.compute', $compute);
            $this->request->getSession()->write('flow.form', $form);
            $this->request->getSession()->write('flow.incident', $incident);
            return $this->redirect(['action' => 'choices']);
        }
// Recompute hooks/evaluators for live preview in TRIN 3 like one()/entitlements
        // Fallbacks to improve scope inference when extractor missed stations
        try {
            if (empty($journey['segments']) && !empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
                $journey['segments'] = (array)$meta['_segments_auto'];
            }
            // Seed dep/arr stations into _auto from 3.2 form if missing
            if (empty($meta['_auto']['dep_station']['value'] ?? '') && !empty($form['dep_station'])) {
                $meta['_auto']['dep_station'] = ['value' => (string)$form['dep_station'], 'source' => 'form'];
            }
            if (empty($meta['_auto']['arr_station']['value'] ?? '') && !empty($form['arr_station'])) {
                $meta['_auto']['arr_station'] = ['value' => (string)$form['arr_station'], 'source' => 'form'];
            }
        } catch (\Throwable $e) { /* ignore */ }
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
            if (!empty($inferRes['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $inferRes['logs']); }
        } catch (\Throwable $e) { $meta['logs'][] = 'WARN: scope infer failed: ' . $e->getMessage(); }
        // If still no country on any segment, fall back to operator_country to unlock exemption matrix
        try {
            $hasCountry = false; foreach ((array)($journey['segments'] ?? []) as $s) { if (!empty($s['country'])) { $hasCountry = true; break; } }
            $opC = (string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''));
            if (!$hasCountry && $opC !== '') {
                $journey['country']['value'] = $opC;
                if (!empty($journey['segments']) && is_array($journey['segments'])) {
                    // Assign to first segment as a minimal hint
                    $journey['segments'][0]['country'] = $opC;
                }
                $meta['logs'][] = 'Fallback: set country from operator_country=' . $opC;
            }
        } catch (\Throwable $e) { /* ignore */ }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        try {
            $auto12 = (new \App\Service\Art12AutoDeriver())->apply($journey, $meta);
            $meta = $auto12['meta'];
            if (!empty($auto12['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $auto12['logs']); }
        } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Art12AutoDeriver failed: ' . $e->getMessage(); }
        try { $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art12 = ['hooks'=>[]]; $meta['logs'][] = 'WARN: Art12Evaluator failed: ' . $e->getMessage(); }
        try { $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art9 = null; $meta['logs'][] = 'WARN: Art9Evaluator failed: ' . $e->getMessage(); }
        // Art. 6 (bicycles) evaluator for UI messages
        try { $art6 = (new \App\Service\Art6BikeEvaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art6 = null; $meta['logs'][] = 'WARN: Art6BikeEvaluator failed: ' . $e->getMessage(); }
        // Art. 21–24 (PMR assistance) for UI messages
        try { $pmr = (new \App\Service\Art21to24PmrEvaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $pmr = null; $meta['logs'][] = 'WARN: Art21to24PmrEvaluator failed: ' . $e->getMessage(); }
        try { $art20 = (new \App\Service\Art20AssistanceEvaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art20 = null; $meta['logs'][] = 'WARN: Art20AssistanceEvaluator failed: ' . $e->getMessage(); }
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
        [$euOnlySuggested, $euOnlyReason] = $this->suggestEuOnly($journey, $meta);
        // Service policy warnings (suburban + regional-blocked)
        $serviceWarnings = [];
        if (!empty($journey['is_suburban'])) {
            $serviceWarnings[] = 'Suburban/lokaltog er ikke understøttet i denne app (national håndtering påkrævet).';
        } elseif (!empty($profile['blocked']) && (string)($profile['scope'] ?? '') === 'regional') {
            $serviceWarnings[] = 'Regional trafik i dette land er undtaget fra EU-flowet her (følg operatørens nationale procedure).';
        }
        // Compute ClaimFormSelector recommendation early so TRIN 3 shows EU vs national
        try {
            $countryCtx = (string)($journey['country']['value'] ?? ($form['operator_country'] ?? ''));
            $scopeCtx = (string)($profile['scope'] ?? '');
            $opCtx = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
            $prodCtx = (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''));
            $delayCtx = (int)($compute['delayMinEU'] ?? 0);
            $selector = new \App\Service\ClaimFormSelector();
            $formDecision = $selector->select([
                'country' => $countryCtx,'scope' => $scopeCtx,'operator' => $opCtx,'product' => $prodCtx,'delayMin' => $delayCtx,'profile' => $profile,
            ]);
        } catch (\Throwable $e) { $formDecision = ['form'=>'eu_standard_claim','reason'=>'Fallback','notes'=>['err:'.$e->getMessage()]]; }

        $this->set(compact('journey','meta','compute','incident','form','flags','profile','art12','art9','art6','pmr','refund','refusion','art20','euOnlySuggested','euOnlyReason','formDecision','serviceWarnings','contractOptions','contractWarning'));
        return null;
    }

    public function entitlements(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $isAdmin = $this->isAdmin();
        $isAjaxHooks = (bool)$this->request->getQuery('ajax_hooks');
        // Previous behaviour: any normal GET wiped the entire flow (including TRIN 1 state and exemptions context).
        // This caused parity issues vs single-page flow (one()) because country/scope hints were lost and all articles showed ON.
        // New behaviour: only reset when explicitly requested via ?reset=1. Preserve journey/flags/compute across refreshes.
        $doReset = $this->truthy($this->request->getQuery('reset'));
        if ($this->request->is('get') && !$isAjaxHooks && $doReset) {
            // Targeted reset: clear form + meta (auto extraction) but keep journey/compute/flags/incident
            $session->delete('flow.form');
            $session->delete('flow.meta');
            $session->delete('flow.leg_class_delivered');
            $session->delete('flow.leg_class_delivered_locked');
            // Leave flow.journey / flow.compute / flow.flags / flow.incident intact
        }
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: [];
        $form = (array)$session->read('flow.form') ?: [];
        // Defensive restore: when navigating back (GET) keep the last saved per-leg delivered selections
        $hasPersistedDelivered = false;
        $deliveredLocked = (bool)$session->read('flow.leg_class_delivered_locked');
        // If form already contains a delivered value from an earlier POST, lock it immediately
        if (!empty($form['leg_class_delivered'])) {
            $deliveredLocked = true;
            $session->write('flow.leg_class_delivered', (array)$form['leg_class_delivered']);
            $session->write('flow.leg_class_delivered_locked', true);
        }
        if ($this->request->is('get')) {
            $persistedDelivered = (array)$session->read('flow.leg_class_delivered') ?: [];
            if (!empty($persistedDelivered)) {
                $form['leg_class_delivered'] = $persistedDelivered;
                $hasPersistedDelivered = true;
                $deliveredLocked = true;
                $session->write('flow.leg_class_delivered_locked', true);
            } elseif (!empty($meta['saved_leg_class_delivered'])) {
                $form['leg_class_delivered'] = (array)$meta['saved_leg_class_delivered'];
                $hasPersistedDelivered = true;
                $deliveredLocked = true;
                $session->write('flow.leg_class_delivered_locked', true);
            }
        }
        // Restore per-leg felter fra backup hvis de mangler (behold brugerens valg)
        $fnameCurr = (string)($form['_ticketFilename'] ?? '');
        if (!$deliveredLocked) {
            if ($fnameCurr !== '' && !empty($meta['saved_leg_by_file'][$fnameCurr])) {
                $byFile = (array)$meta['saved_leg_by_file'][$fnameCurr];
                if (!$hasPersistedDelivered && empty($form['leg_class_delivered']) && !empty($byFile['delivered'])) {
                    $form['leg_class_delivered'] = (array)$byFile['delivered'];
                }
                if (empty($form['leg_class_purchased']) && !empty($byFile['purchased'])) {
                    $form['leg_class_purchased'] = (array)$byFile['purchased'];
                }
                if (empty($form['leg_downgraded']) && !empty($byFile['downgraded'])) {
                    $form['leg_downgraded'] = (array)$byFile['downgraded'];
                }
            } else {
                if (!$hasPersistedDelivered && empty($form['leg_class_delivered']) && !empty($meta['saved_leg_class_delivered'])) {
                    $form['leg_class_delivered'] = (array)$meta['saved_leg_class_delivered'];
                }
                if (empty($form['leg_class_purchased']) && !empty($meta['saved_leg_class_purchased'])) {
                    $form['leg_class_purchased'] = (array)$meta['saved_leg_class_purchased'];
                }
                if (empty($form['leg_downgraded']) && !empty($meta['saved_leg_downgraded'])) {
                    $form['leg_downgraded'] = (array)$meta['saved_leg_downgraded'];
                }
            }
        }
        $incident = (array)$session->read('flow.incident') ?: [];
        // Hvis leveret-niveau mangler i form, seed fra auto-detektion (men overskriv ikke brugerens valg)
        if (!$deliveredLocked && !$hasPersistedDelivered && empty($form['leg_class_delivered']) && !empty($meta['_auto']['class_delivered']) && is_array($meta['_auto']['class_delivered'])) {
            $seed = [];
            foreach ((array)$meta['_auto']['class_delivered'] as $idx => $row) {
                $seed[$idx] = (string)($row['value'] ?? '');
            }
            if (!empty($seed)) {
                $form['leg_class_delivered'] = $seed;
                $session->write('flow.form', $form);
            }
        }
        // De-duplicate any lingering multi-ticket entries by filename (defensive against accidental resubmits)
        if (!empty($meta['_multi_tickets']) && is_array($meta['_multi_tickets'])) {
            $seen = [];
            $newList = [];
            foreach ((array)$meta['_multi_tickets'] as $mt) {
                $f = (string)($mt['file'] ?? '');
                if ($f === '' || isset($seen[$f])) { continue; }
                $seen[$f] = true; $newList[] = $mt;
            }
            if (count($newList) !== count((array)$meta['_multi_tickets'])) { $meta['_multi_tickets'] = $newList; $session->write('flow.meta', $meta); }
        }
        // On explicit reset (?reset=1), clear TRIN 4 local state to avoid stale uploads/checkboxes
        if ($this->request->is('get') && !$isAjaxHooks && $doReset) {
            // Clear diagnostics
            if (!empty($meta['logs'])) { $meta['logs'] = []; }
            // Clear auto-extracted data and candidates
            unset($meta['_auto'], $meta['_segments_auto'], $meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode']);
            unset($meta['extraction_provider'], $meta['extraction_confidence']);
            // Clear any grouped/multi tickets
            unset($meta['_multi_tickets']);
            // Clear journey-level derived identifiers (PNR) to avoid field fallback after refresh
            unset($journey['bookingRef']);
            // Clear TRIN 4 form fields including uploads and toggles specific to this step
            foreach ([
                '_ticketUploaded','_ticketFilename','_ticketOriginalName',
                'operator','operator_country','operator_product',
                'dep_date','dep_time','dep_station','arr_station','arr_time',
                'train_no','ticket_no','price',
                'actual_arrival_date','actual_dep_time','actual_arr_time',
                'missed_connection_station'
            ] as $rk) { unset($form[$rk]); }
            // Reset PRG-local checkbox
            $compute['knownDelayBeforePurchase'] = false;
            // Persist resets
            $session->write('flow.meta', $meta);
            $session->write('flow.journey', $journey);
            $session->write('flow.form', $form);
            if (!empty($form['leg_class_delivered'])) {
                $session->write('flow.leg_class_delivered', (array)$form['leg_class_delivered']);
                $session->write('flow.leg_class_delivered_locked', true);
            }
            $session->write('flow.compute', $compute);
        }

    // Handle uploads and inline edits first, then recompute evaluators; only redirect to summary when explicitly requested
        if ($this->request->is('post')) {
            // Clear-all action from UI: remove current and multi tickets and wipe 3.2 fields + identifiers
            if ($this->truthy($this->request->getData('clear_all'))) {
                foreach (['_ticketUploaded','_ticketFilename','_ticketOriginalName'] as $rk) { unset($form[$rk]); }
                foreach ([
                    'operator','operator_country','operator_product',
                    'dep_date','dep_time','dep_station','arr_station','arr_time',
                    'train_no','ticket_no','price',
                    'actual_arrival_date','actual_dep_time','actual_arr_time',
                    'missed_connection_station',
                    // TRIN 3 – inline hooks/fields to reset along with files
                    'fare_flex_type','train_specificity',
                    'fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status',
                    'bike_booked','bike_count','bike_was_present','bike_caused_issue','bike_reservation_made','bike_reservation_required','bike_denied_boarding','bike_refusal_reason_provided','bike_refusal_reason_type',
                    'pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing','pmr_facility_details',
                    
                ] as $rk) { unset($form[$rk]); }
                // Clear controller-built helpers
                unset($form['_miss_conn_choices']);
                // Clear meta caches and detections
                unset($meta['_auto'], $meta['_segments_auto'], $meta['_segments_debug'], $meta['_segments_llm_suggest']);
                unset($meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode']);
                unset($meta['_ocr_text'], $meta['_ocr_pages']);
                unset($meta['_pmr_detection'], $meta['_pmr_detected']);
                unset($meta['_bike_detection']);
                unset($meta['_ticket_type_detection'], $meta['_class_detection']);
                unset($meta['_mct_eval'], $meta['mct_realistic']);
                unset($meta['extraction_provider'], $meta['extraction_confidence']);
                unset($meta['_multi_tickets']);
                // Clear journey passengers snapshot and bookingRef
                unset($journey['passengers'], $journey['passengerCount']);
                unset($journey['bookingRef']);
                $meta['logs'][] = 'RESET: Clear all triggered by UI';
                $session->write('flow.journey', $journey);
                $session->write('flow.meta', $meta);
                $session->write('flow.form', $form);
                // Fall through to recompute below
            }
            // Remove ticket action
            $removeFile = (string)($this->request->getData('remove_ticket') ?? '');
            if ($removeFile !== '') {
                $removeBase = basename($removeFile);
                $currentFile = (string)($form['_ticketFilename'] ?? '');
                if ($currentFile !== '' && ($removeFile === $currentFile || basename($currentFile) === $removeBase)) {
                    unset($form['_ticketUploaded'], $form['_ticketFilename'], $form['_ticketOriginalName']);
                    foreach ([
                        'operator','operator_country','operator_product',
                        'dep_date','dep_time','dep_station','arr_station','arr_time',
                        'train_no','ticket_no','price',
                        'actual_arrival_date','actual_dep_time','actual_arr_time',
                        'missed_connection_station'
                    ] as $rk) { unset($form[$rk]); }
                    unset($meta['_auto'], $meta['_segments_auto'], $meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode']);
                    unset($meta['extraction_provider'], $meta['extraction_confidence']);
                    unset($meta['_multi_tickets']);
                    unset($journey['bookingRef']);
                    $meta['logs'][] = 'Removed primary ticket: ' . $removeFile;
                } else {
                    if (!empty($meta['_multi_tickets']) && is_array($meta['_multi_tickets'])) {
                        $meta['_multi_tickets'] = array_values(array_filter((array)$meta['_multi_tickets'], function($t) use ($removeFile, $removeBase){
                            $f = (string)($t['file'] ?? '');
                            return !($f === $removeFile || basename($f) === $removeBase);
                        }));
                        if (empty($meta['_multi_tickets'])) {
                            unset($meta['_multi_tickets']);
                            unset($journey['bookingRef']);
                        }
                        $meta['logs'][] = 'Removed extra ticket: ' . $removeFile;
                    }
                }
            }

            // Persist known_delay toggle here for convenience
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            // Persist basic incident fields for TRIN 1 hooks panel
            if ($this->request->getData('incident_main') !== null) {
                $incident['main'] = (string)$this->request->getData('incident_main');
            }
            if ($this->request->getData('incident_missed') !== null) {
                $incident['missed'] = (bool)$this->truthy($this->request->getData('incident_missed'));
            }
            // TRIN 3: Persist PMR answers (hooks panel + inline TRIN 3 block)
            $mapYN = function($v){
                $s = strtolower(trim((string)$v));
                if (in_array($s, ['ja','yes','y','1','true'], true)) return 'Ja';
                if (in_array($s, ['nej','no','n','0','false'], true)) return 'Nej';
                return 'unknown';
            };
            if ($this->request->getData('pmr_user') !== null) { $meta['pmr_user'] = $mapYN($this->request->getData('pmr_user')); }
            if ($this->request->getData('pmr_booked') !== null) {
                $raw = strtolower(trim((string)$this->request->getData('pmr_booked')));
                if (in_array($raw, ['refused','attempted_refused'], true)) {
                    $meta['pmr_booked'] = 'Nej';
                    $meta['pmr_booked_detail'] = 'refused';
                } else {
                    $meta['pmr_booked'] = $mapYN($raw);
                }
            }
            // Inline TRIN 3 PMR fields
            $pmrDelivered = (string)($this->request->getData('pmr_delivered_status') ?? '');
            if ($pmrDelivered !== '') {
                $allowed = ['yes_full','partial','no'];
                $pmrDelivered = strtolower(trim($pmrDelivered));
                if (!in_array($pmrDelivered, $allowed, true)) { $pmrDelivered = 'unknown'; }
                $meta['pmr_delivered_status'] = $pmrDelivered;
            }
            if ($this->request->getData('pmr_promised_missing') !== null) {
                $meta['pmr_promised_missing'] = strtolower(trim((string)$this->request->getData('pmr_promised_missing')));
                // normalize to yes/no/unknown for consistency; Art9Evaluator will canonicalize further
                if (!in_array($meta['pmr_promised_missing'], ['yes','no','unknown'], true)) {
                    $meta['pmr_promised_missing'] = in_array($meta['pmr_promised_missing'], ['ja','nej'], true)
                        ? ($meta['pmr_promised_missing']==='ja'?'yes':'no')
                        : 'unknown';
                }
            }
            $pmrDetails = (string)($this->request->getData('pmr_facility_details') ?? '');
            if ($pmrDetails !== '') { $meta['pmr_facility_details'] = $pmrDetails; }
            // TRIN 3: Persist Bike answers when provided in hooks panel
            if ($this->request->getData('bike_booked') !== null) { $meta['bike_booked'] = $mapYN($this->request->getData('bike_booked')); }
            if ($this->request->getData('bike_count') !== null && $this->request->getData('bike_count') !== '') {
                $cnt = (int)$this->request->getData('bike_count');
                if ($cnt > 0) { $meta['bike_count'] = (string)$cnt; }
            }
            // TRIN 3: Persist inline Bike Article 6 flow hooks (new in TRIN 3 UI)
            $normYNU = function($v){
                $s = strtolower(trim((string)$v));
                if (in_array($s, ['ja','yes','y','1','true'], true)) return 'yes';
                if (in_array($s, ['nej','no','n','0','false'], true)) return 'no';
                if ($s === '' || $s === '-' || $s === 'unknown' || $s === 'ved ikke') return 'unknown';
                return $s;
            };
            if ($this->request->getData('bike_was_present') !== null) {
                $meta['bike_was_present'] = $normYNU($this->request->getData('bike_was_present'));
            }
            if ($this->request->getData('bike_caused_issue') !== null) {
                $meta['bike_caused_issue'] = $normYNU($this->request->getData('bike_caused_issue'));
            }
            if ($this->request->getData('bike_reservation_made') !== null) {
                $meta['bike_reservation_made'] = $normYNU($this->request->getData('bike_reservation_made'));
            }
            if ($this->request->getData('bike_reservation_required') !== null) {
                $meta['bike_reservation_required'] = $normYNU($this->request->getData('bike_reservation_required'));
            }
            if ($this->request->getData('bike_denied_boarding') !== null) {
                $meta['bike_denied_boarding'] = $normYNU($this->request->getData('bike_denied_boarding'));
            }
            if ($this->request->getData('bike_refusal_reason_provided') !== null) {
                $meta['bike_refusal_reason_provided'] = $normYNU($this->request->getData('bike_refusal_reason_provided'));
            }
            $brt = (string)($this->request->getData('bike_refusal_reason_type') ?? '');
            if ($brt !== '') {
                // Allow a limited set of enum values; otherwise map to 'other'
                $allowedBrt = ['capacity','equipment','weight_dim','other','unknown'];
                $brt = strtolower(trim($brt));
                if (!in_array($brt, $allowedBrt, true)) { $brt = 'other'; }
                $meta['bike_refusal_reason_type'] = $brt;
            }
            // Derive a consolidated flag for downstream evaluators/UX
            try {
                // Normalize booked value across locales (Ja/Nej vs yes/no) and consider AUTO fallback value
                $bookedRaw = (string)($meta['bike_booked'] ?? ($meta['_auto']['bike_booked']['value'] ?? ''));
                $bookedLow = strtolower($bookedRaw);
                if ($bookedLow === 'ja') { $bookedLow = 'yes'; }
                if ($bookedLow === 'nej') { $bookedLow = 'no'; }
                $hadBike = (string)($meta['bike_was_present'] ?? '') === 'yes' || $bookedLow === 'yes' || !empty($meta['_auto']['bike_booked']);
                $bikeCause = (string)($meta['bike_caused_issue'] ?? '') === 'yes';
                if ($hadBike && $bikeCause) { $meta['pm_bike_involved'] = 'yes'; }
                elseif ($hadBike && (string)($meta['bike_caused_issue'] ?? '') === 'no') { $meta['pm_bike_involved'] = 'no'; }
            } catch (\Throwable $e) { /* ignore */ }
            // TRIN 3: Persist Ticket type (fare_flex_type + train_specificity) when edited in hooks panel
            $fft = (string)($this->request->getData('fare_flex_type') ?? '');
            if ($fft !== '') {
                $fftLow = strtolower(trim($fft));
                $allowedFft = ['nonflex','semiflex','flex','pass','other'];
                if (in_array($fftLow, $allowedFft, true)) { $meta['fare_flex_type'] = $fftLow; }
            }
            $ts = (string)($this->request->getData('train_specificity') ?? '');
            if ($ts !== '') {
                $tsLow = strtolower(trim($ts));
                $allowedTs = ['specific','any_day','unknown'];
                if (in_array($tsLow, $allowedTs, true)) { $meta['train_specificity'] = $tsLow; }
            }
            // TRIN 3: Persist Season/Period pass details when relevant (Art. 19(2))
            try {
                $spHasRaw = $this->request->getData('season_pass_has');
                $spHas = $spHasRaw !== null ? (bool)$this->truthy($spHasRaw) : null;
                $fftNow = strtolower((string)($meta['fare_flex_type'] ?? ''));
                $enableSeason = ($spHas === true) || ($fftNow === 'pass');
                if ($enableSeason) {
                    $sp = (array)($meta['season_pass'] ?? []);
                    $sp['has'] = true;
                    $t = (string)($this->request->getData('season_pass_type') ?? ''); if ($t !== '') { $sp['type'] = $t; }
                    $op = (string)($this->request->getData('season_pass_operator') ?? ''); if ($op !== '') { $sp['operator'] = $op; }
                    $vf = (string)($this->request->getData('season_pass_valid_from') ?? ''); if ($vf !== '') { $sp['valid_from'] = $vf; }
                    $vt = (string)($this->request->getData('season_pass_valid_to') ?? ''); if ($vt !== '') { $sp['valid_to'] = $vt; }
                    $meta['season_pass'] = $sp;
                } elseif ($spHas === false) {
                    // Explicitly turned off in UI
                    unset($meta['season_pass']);
                }
            } catch (\Throwable $e) { /* ignore season-pass persistence errors */ }
            // TRIN 3: Persist Class/Reservation fields when edited in hooks panel
            $cls = (string)($this->request->getData('fare_class_purchased') ?? '');
            if ($cls !== '') {
                $clsLow = strtolower(trim($cls));
                $allowedCls = ['1','2','other','unknown'];
                if (in_array($clsLow, $allowedCls, true)) { $meta['fare_class_purchased'] = $clsLow; }
            }
            $bst = (string)($this->request->getData('berth_seat_type') ?? '');
            if ($bst !== '') {
                $bstLow = strtolower(trim($bst));
                $allowedBst = ['seat','free','couchette','sleeper','none','unknown'];
                if (in_array($bstLow, $allowedBst, true)) { $meta['berth_seat_type'] = $bstLow; }
            }
            $rad = (string)($this->request->getData('reserved_amenity_delivered') ?? '');
            if ($rad !== '') {
                $radLow = strtolower(trim($rad));
                $allowedRad = ['yes','no','partial','unknown'];
                if (in_array($radLow, $allowedRad, true)) { $meta['reserved_amenity_delivered'] = $radLow; }
            }
            $cds = (string)($this->request->getData('class_delivered_status') ?? '');
            if ($cds !== '') {
                $cdsLow = strtolower(trim($cds));
                $allowedCds = ['ok','downgrade','upgrade','unknown'];
                if (in_array($cdsLow, $allowedCds, true)) { $meta['class_delivered_status'] = $cdsLow; }
            }
            // TRIN 3: Persist nedgradering (basis + status), andel auto-beregnet i controller
            if ($this->request->getData('downgrade_occurred') !== null) {
                $form['downgrade_occurred'] = (string)$this->request->getData('downgrade_occurred');
            }
            if ($this->request->getData('downgrade_comp_basis') !== null) {
                $form['downgrade_comp_basis'] = (string)$this->request->getData('downgrade_comp_basis');
            }
            if ($this->request->getData('downgrade_segment_share') !== null && $this->request->getData('downgrade_segment_share') !== '') {
                $form['downgrade_segment_share'] = (string)$this->request->getData('downgrade_segment_share');
            }
            // Persist per-leg klasse/nedgradering tabel (Trin 3) for senere trin/PDF
            foreach (['leg_class_purchased','leg_class_delivered','leg_downgraded'] as $lf) {
                $val = $this->request->getData($lf);
                if ($val === null) { continue; }
                if (is_array($val)) {
                    // Bevar tidligere ikke-tomme værdier for individuelle indeks hvor POST sender tom streng
                    if (isset($form[$lf]) && is_array($form[$lf])) {
                        foreach ($val as $i => $v) {
                            if (($v === '' || $v === null) && isset($form[$lf][$i]) && $form[$lf][$i] !== '' && $form[$lf][$i] !== null) {
                                $val[$i] = $form[$lf][$i];
                            }
                        }
                    }
                    $nonEmpty = array_filter($val, static function($v){ return $v !== null && $v !== ''; });
                    if (!empty($nonEmpty)) {
                        $form[$lf] = $val;
                    } elseif (isset($form[$lf])) {
                        // behold tidligere værdi hvis hele arrayet er tomt
                        $val = $form[$lf];
                    }
                } else {
                    $form[$lf] = (string)$val;
                }
            }
            // Gem per-leg felter også i meta som backup (for at undgå at de overskrives ved refresh)
            if (!empty($form['leg_class_purchased']) || !empty($form['leg_class_delivered']) || !empty($form['leg_downgraded'])) {
                $meta['saved_leg_class_purchased'] = $form['leg_class_purchased'] ?? [];
                $meta['saved_leg_class_delivered'] = $form['leg_class_delivered'] ?? [];
                $meta['saved_leg_downgraded']      = $form['leg_downgraded'] ?? [];
                // Keep a separate session-level copy so GET navigation cannot lose the delivered selection
                $session->write('flow.leg_class_delivered', $meta['saved_leg_class_delivered']);
                $session->write('flow.leg_class_delivered_locked', !empty($meta['saved_leg_class_delivered']));
                // Bind per filnavn hvis det findes
                $fname = (string)($form['_ticketFilename'] ?? '');
                if ($fname !== '') {
                    $meta['saved_leg_by_file'][$fname] = [
                        'purchased' => $meta['saved_leg_class_purchased'],
                        'delivered' => $meta['saved_leg_class_delivered'],
                        'downgraded'=> $meta['saved_leg_downgraded'],
                    ];
                }
                $session->write('flow.meta', $meta);
            }
            // Allow explicit EU-only override from hooks panel (TRIN 2)
            // Allow explicit EU-only override from hooks panel (TRIN 2) — admin only
            if ($isAdmin && $this->request->getData('eu_only') !== null) {
                $compute['euOnly'] = (bool)$this->truthy($this->request->getData('eu_only'));
            }
            // Country-specific hint: SE regional <150 km toggle (drives exemptions under Art. 9/17–20)
            if ($this->request->getData('se_under_150km') !== null) {
                $journey['se_under_150km'] = (bool)$this->truthy($this->request->getData('se_under_150km'));
            }
            // TRIN 3 – Art. 9(1) MCT prompt: persist answer if present
            $mapYN = function($v){
                $s = strtolower(trim((string)$v));
                if (in_array($s, ['ja','yes','y','1','true'], true)) return 'Ja';
                if (in_array($s, ['nej','no','n','0','false'], true)) return 'Nej';
                return 'unknown';
            };
            if ($this->request->getData('mct_realistic') !== null) {
                $meta['mct_realistic'] = $mapYN($this->request->getData('mct_realistic'));
            }

            // Accept edits to the core 3.2/3.3 fields
            $allowedFormKeys = [
                'operator','operator_country','operator_product',
                'dep_date','dep_time','dep_station','arr_station','arr_time',
                'train_no','ticket_no','price',
                'actual_arrival_date','actual_dep_time','actual_arr_time',
                'missed_connection_station',
                // passengers auto edit entries
                // dynamic keys passenger[i][name], passenger[i][is_claimant] handled below
            ];
            foreach ($allowedFormKeys as $k) { if ($this->request->getData($k) !== null) { $form[$k] = (string)$this->request->getData($k); } }
            // Passenger edits if provided
            $paxIn = (array)$this->request->getData('passenger');
            if (!empty($paxIn)) { $meta['_passengers_auto'] = $paxIn; }
            // Global: claimant is legal representative/guardian for others on the ticket
            if ($this->request->getData('claimant_is_legal_representative') !== null) {
                $meta['claimant_is_legal_representative'] = $this->truthy($this->request->getData('claimant_is_legal_representative')) ? 'yes' : 'no';
            }
            // Missed-connection checkbox list -> join into the free-text field
            $mcList = (array)$this->request->getData('missed_connection_choices');
            if (!empty($mcList)) {
                $form['missed_connection_station'] = implode(' / ', array_values(array_map('strval', $mcList)));
            }
            // Auto-set incident.missed based on explicit station selection in TRIN 3
            if (array_key_exists('missed_connection_station', $form)) {
                $incident['missed'] = trim((string)($form['missed_connection_station'] ?? '')) !== '';
            }
            // Minimal Art. 12 flow hook (TRIN 2): allow persisting same_transaction_all for evaluator in TRIN 3 hooks panel
            $sta = (string)($this->request->getData('same_transaction_all') ?? '');
            $staLow = strtolower(trim($sta));
            if ($staLow !== '') {
                if (in_array($staLow, ['ja','yes','y','1','true'], true)) { $meta['same_transaction_all'] = 'yes'; }
                elseif (in_array($staLow, ['nej','no','n','0','false'], true)) { $meta['same_transaction_all'] = 'no'; }
                elseif (in_array($staLow, ['unknown','ved ikke','-'], true)) { $meta['same_transaction_all'] = 'unknown'; }
            }
            // TRIN 3 (PGR): Minimal Art. 12 questions inline — persist into meta so evaluators can use them immediately
            // 1) Seller channel → journey.seller_type + seller_type_* hooks
            $sellerChannel = (string)($this->request->getData('seller_channel') ?? '');
            if ($sellerChannel === 'operator') {
                $journey['seller_type'] = 'operator';
                $meta['seller_type_operator'] = 'Ja';
                $meta['seller_type_agency'] = 'Nej';
            } elseif ($sellerChannel === 'retailer') {
                $journey['seller_type'] = 'agency';
                $meta['seller_type_operator'] = 'Nej';
                $meta['seller_type_agency'] = 'Ja';
            }
            // If bookingRef already present, infer single transaction by seller role
            if (!empty($journey['bookingRef'])) {
                if (($journey['seller_type'] ?? null) === 'operator') { $meta['single_txn_operator'] = 'Ja'; }
                elseif (($journey['seller_type'] ?? null) === 'agency') { $meta['single_txn_retailer'] = 'Ja'; }
                $meta['single_booking_reference'] = 'Ja';
                $meta['shared_pnr_scope'] = 'Ja';
            }
            // 2) Same transaction (asked when multiple PNRs) → single_txn_* hooks depending on seller
            $sameTxn = (string)($this->request->getData('same_transaction') ?? '');
            if ($sameTxn === 'yes' || $sameTxn === 'no') {
                if ($sellerChannel === 'operator') {
                    $meta['single_txn_operator'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                    $meta['single_txn_retailer'] = 'no';
                } elseif ($sellerChannel === 'retailer') {
                    $meta['single_txn_operator'] = 'no';
                    $meta['single_txn_retailer'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                } else {
                    $meta['single_txn_operator'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                    $meta['single_txn_retailer'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                }
            }
            // 3) Disclosure flags — boolean yes/no → Ja/Nej (keep 'unknown' as-is when provided)
            $sepRaw = (string)($this->request->getData('separate_contract_notice') ?? '');
            if ($sepRaw === 'yes') { $meta['separate_contract_notice'] = 'Ja'; }
            elseif ($sepRaw === 'no') { $meta['separate_contract_notice'] = 'Nej'; }
            $ttdRaw = (string)($this->request->getData('through_ticket_disclosure') ?? '');
            if ($ttdRaw === 'yes') { $meta['through_ticket_disclosure'] = 'Ja'; }
            elseif ($ttdRaw === 'no') { $meta['through_ticket_disclosure'] = 'Nej'; }
            // Passenger edits per extra ticket
            $paxMulti = $this->request->getData('passenger_multi');
            if (is_array($paxMulti) && !empty($meta['_multi_tickets']) && is_array($meta['_multi_tickets'])) {
                $list = (array)$meta['_multi_tickets'];
                foreach ($list as $idx => $mt) {
                    $file = (string)($mt['file'] ?? ''); if ($file === '' || !isset($paxMulti[$file]) || !is_array($paxMulti[$file])) { continue; }
                    $edits = (array)$paxMulti[$file];
                    $orig = (array)($mt['passengers'] ?? []);
                    foreach ($edits as $k => $ev) {
                        $i = is_numeric($k) ? (int)$k : null; if ($i === null || !array_key_exists($i, $orig)) { continue; }
                        $name = isset($ev['name']) ? trim((string)$ev['name']) : null;
                        $isC = !empty($ev['is_claimant']);
                        if ($name !== null) { $orig[$i]['name'] = $name; }
                        $orig[$i]['is_claimant'] = $isC ? true : false;
                    }
                    $list[$idx]['passengers'] = $orig;
                }
                $meta['_multi_tickets'] = array_values($list);
            }

            // Single ticket upload handling
            $didUpload = false; $saved = false; $dest = null; $name = null; $safe = null;
            try { $uf = $this->request->getUploadedFile('ticket_upload'); } catch (\Throwable $e) { $uf = null; }
            if ($uf instanceof \Psr\Http\Message\UploadedFileInterface && $uf->getError() === UPLOAD_ERR_OK) {
                $name = (string)($uf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads'; if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                try { $uf->moveTo($dest); $saved = true; } catch (\Throwable $e) { $meta['logs'][] = 'Upload move failed: ' . $e->getMessage(); }
            } elseif ($this->request->getData('ticket_upload')) {
                // Legacy/fallback array-style upload structure
                $af = $this->request->getData('ticket_upload');
                if (is_array($af) && (($af['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
                    $tmp = (string)($af['tmp_name'] ?? '');
                    $name = (string)($af['name'] ?? ('ticket_' . bin2hex(random_bytes(4))));
                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                    $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads'; if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                    $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                    if ($tmp !== '' && @move_uploaded_file($tmp, $dest)) { $saved = true; } else { $meta['logs'][] = 'Upload move failed (array-path)'; }
                }
            }
            if ($saved && $dest) {
                $didUpload = true;
                $form['_ticketUploaded'] = '1';
                $form['_ticketFilename'] = $safe;
                $form['_ticketOriginalName'] = $name;
                // Reset previous auto fields
                foreach (['operator','operator_country','operator_product','dep_date','dep_time','dep_station','arr_station','arr_time','train_no','ticket_no','price','actual_arrival_date','actual_dep_time','actual_arr_time','missed_connection_station'] as $rk) { unset($form[$rk]); }
                $meta['_auto'] = [];
                unset($journey['is_international_beyond_eu'], $journey['is_international_inside_eu'], $journey['is_long_domestic']);

                // OCR/vision extraction — mirror resilient behaviour from one()
                $textBlob = '';
                $ext = strtolower((string)pathinfo($dest, PATHINFO_EXTENSION));
                try {
                    if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($dest);
                        $textBlob = $pdf->getText() ?? '';
                        // Count pages early so we can decide on page-level OCR augmentation
                        try {
                            $pgs = method_exists($pdf, 'getPages') ? count((array)$pdf->getPages()) : null;
                            if ($pgs) {
                                $meta['logs'][] = 'PDF: embedded text extracted from ' . $pgs . ' page(s).';
                                // Persist page count for downstream UI (TRIN 3 visibility, debug panel, etc.)
                                $meta['_ocr_pages'] = (int)$pgs;
                            }
                        } catch (\Throwable $e) { $pgs = null; }
                        // Fallback to pdftotext when embedded text is missing
                        if (trim((string)$textBlob) === '') {
                            $pdftotext = function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH');
                            $pdftotext = $pdftotext ?: 'pdftotext';
                            // Ensure all pages, UTF-8 output
                            $cmd = escapeshellarg((string)$pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg((string)$dest) . ' -';
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') { $textBlob = (string)$out; $meta['logs'][] = 'OCR: pdftotext used (-layout).'; }
                        }
                        // If multi-page PDF might contain image-only pages (e.g., journey plan on page 2), do page OCR and append
                        $needImageOcr = false;
                        $probe = mb_strtolower((string)$textBlob, 'UTF-8');
                        // Strategy revision: previously only triggered when keywords missing.
                        // We now always attempt page-level OCR for multi-page PDFs to harvest text from image-only pages.
                        if ((int)($pgs ?? 0) > 1) { $needImageOcr = true; $meta['logs'][] = 'OCR: multi-page PDF -> forcing page-level image OCR pass.'; }
                        // Still ensure single-page image-only or sparse-text PDFs invoke OCR.
                        if (!$needImageOcr) {
                            if (trim((string)$textBlob) === '') { $needImageOcr = true; }
                            elseif (!preg_match('/\b(via|skift|omstigning|umstieg|umsteigen|reiseverbindung|change\s+at)\b/u', $probe)) { $needImageOcr = true; }
                        }
                        if ($needImageOcr) {
                            $added = '';
                            // Prefer Imagick if available
                            if (class_exists('Imagick')) {
                                try {
                                    // Instantiate dynamically to avoid static analyzers flagging missing extension
                                    $cls = 'Imagick';
                                    /** @var object $img */
                                    $img = new $cls();
                                    $img->setResolution(200, 200);
                                    $img->readImage($dest);
                                    $img->setImageFormat('png');
                                    $pages = $img->getNumberImages();
                                    $i = 0;
                                    foreach ($img as $frame) {
                                        $tmpP = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_ocr_' . bin2hex(random_bytes(4)) . '_' . ($i++) . '.png';
                                        $frame->writeImage($tmpP);
                                        // Try Tesseract first, then Vision OCR
                                        $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                                        if (!$tess || $tess === '') { $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'; $tess = is_file($winDefault) ? $winDefault : 'tesseract'; }
                                        $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng+dan+deu+fra+ita';
                                        $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$tmpP) . ' stdout -l ' . escapeshellarg((string)$langs) . ' --psm 6';
                                        $out = @shell_exec($cmd . ' 2>&1');
                                        if (is_string($out) && trim($out) !== '') { $added .= "\n" . (string)$out; }
                                        @unlink($tmpP);
                                    }
                                    if (trim($added) !== '') { $textBlob = trim($textBlob . "\n" . $added); $meta['logs'][] = 'OCR: PDF page OCR (Imagick+Tesseract) appended ' . (int)$pages . ' page image(s).'; }
                                } catch (\Throwable $ee) { /* ignore */ }
                            }
                            // If Imagick not available and still nothing, try pdftoppm (Poppler) as a fallback
                            if (trim((string)$textBlob) === '' || (!preg_match('/\b(via|skift|omstigning|umstieg|umsteigen|reiseverbindung|change\s+at)\b/u', mb_strtolower($textBlob,'UTF-8')))) {
                                $pdftoppm = function_exists('env') ? env('PDFTOPPM_PATH') : getenv('PDFTOPPM_PATH');
                                $pdftoppm = $pdftoppm ?: 'pdftoppm';
                                $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdfpp_' . bin2hex(random_bytes(3));
                                $cmd = escapeshellarg((string)$pdftoppm) . ' -r 200 -png ' . escapeshellarg((string)$dest) . ' ' . escapeshellarg((string)$prefix);
                                $out = @shell_exec($cmd . ' 2>&1');
                                $glob = glob($prefix . '-*.png');
                                if (is_array($glob)) {
                                    foreach ($glob as $png) {
                                        $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                                        if (!$tess || $tess === '') { $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'; $tess = is_file($winDefault) ? $winDefault : 'tesseract'; }
                                        $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng+dan+deu+fra+ita';
                                        $cmd2 = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$png) . ' stdout -l ' . escapeshellarg((string)$langs) . ' --psm 6';
                                        $txt2 = @shell_exec($cmd2 . ' 2>&1');
                                        if (is_string($txt2) && trim($txt2) !== '') { $textBlob .= "\n" . (string)$txt2; }
                                        @unlink($png);
                                    }
                                    if (!empty($glob)) { $meta['logs'][] = 'OCR: PDF page OCR (pdftoppm+Tesseract) appended ' . count($glob) . ' page image(s).'; }
                                }
                            }
                        }
                    } elseif (in_array($ext, ['txt','text'], true)) {
                        $textBlob = (string)@file_get_contents($dest);
                    }
                } catch (\Throwable $e) { $textBlob = ''; }
                if ($textBlob === '' && in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                    $meta['logs'][] = 'OCR: image detected (' . strtoupper($ext) . ')';
                    // Dev fixture: try mocks/tests/fixtures/<basename>.txt
                    $base = (string)pathinfo($safe ?? '', PATHINFO_FILENAME);
                    $mockDir = ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
                    foreach ([$mockDir . $base . '.txt', $mockDir . strtolower($base) . '.txt'] as $cand) {
                        if ($textBlob === '' && is_file($cand)) { $textBlob = (string)@file_get_contents($cand); $meta['logs'][] = 'OCR: using mock fixture ' . basename($cand); }
                    }
                    // Optional Vision-first
                    $visionFirst = false; try { $visionFirst = strtolower((string)((function_exists('env')?env('LLM_VISION_PRIORITY'):getenv('LLM_VISION_PRIORITY')) ?? '')) === 'first'; } catch (\Throwable $e) {}
                    if ($visionFirst) {
                        try { $vision = new \App\Service\Ocr\LlmVisionOcr(); [$vText,$vLogs] = $vision->extractTextFromImage($dest); foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; } if (is_string($vText) && trim($vText) !== '') { $textBlob = (string)$vText; } } catch (\Throwable $e) { $meta['logs'][] = 'Vision-first failed: ' . $e->getMessage(); }
                    }
                    // Tesseract with simple language inference
                    if ($textBlob === '') {
                        $fn = strtolower((string)($safe ?? ''));
                        $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                        if (!$langs || $langs === 'eng') {
                            if (preg_match('/(sncf|tgv|fr|french)/', $fn)) { $langs = 'eng+fra'; }
                            elseif (preg_match('/(db|bahn|ice|de|german)/', $fn)) { $langs = 'eng+deu'; }
                        }
                        $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                        if (!$tess || $tess === '') { $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'; $tess = is_file($winDefault) ? $winDefault : 'tesseract'; }
                        $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest) . ' stdout -l ' . escapeshellarg((string)$langs) . ' --psm 6';
                        $out = @shell_exec($cmd . ' 2>&1');
                        if (is_string($out) && trim($out) !== '') { $textBlob = (string)$out; $meta['logs'][] = 'OCR: Tesseract used (' . $langs . ').'; }
                    }
                    // Fallback Vision if still empty
                    if ($textBlob === '') {
                        try { $vision = new \App\Service\Ocr\LlmVisionOcr(); [$vText,$vLogs] = $vision->extractTextFromImage($dest); foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; } if (is_string($vText) && trim($vText) !== '') { $textBlob = (string)$vText; $meta['logs'][] = 'OCR: Vision fallback used.'; } } catch (\Throwable $e) { /* ignore */ }
                    }
                }
                $textBlob = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$textBlob) ?? (string)$textBlob;
                // Keep a short OCR snippet in meta for debug purposes
                if (!isset($meta['_ocr_text'])) {
                    $meta['_ocr_text'] = (string)mb_substr((string)$textBlob, 0, 4000, 'UTF-8');
                }
                if ($textBlob !== '') {
                    $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                        new \App\Service\TicketExtraction\HeuristicsExtractor(),
                        new \App\Service\TicketExtraction\LlmExtractor(),
                    ], 0.66);
                    $er = $broker->run($textBlob);
                    $meta['extraction_provider'] = $er->provider;
                    $meta['extraction_confidence'] = $er->confidence;
                    $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                    $auto = [];
                    foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                    $meta['_auto'] = $auto;
                    foreach (['dep_date','dep_time','dep_station','arr_station','arr_time','train_no','ticket_no','price'] as $rk) { $form[$rk] = isset($auto[$rk]['value']) ? (string)$auto[$rk]['value'] : ''; }
                    if (!empty($auto['operator']['value'])) { $form['operator'] = (string)$auto['operator']['value']; }
                    if (!empty($auto['operator_country']['value'])) { $form['operator_country'] = (string)$auto['operator_country']['value']; }
                    if (!empty($auto['operator_product']['value'])) { $form['operator_product'] = (string)$auto['operator_product']['value']; }

                    // PMR/handicap auto-detection from ticket text (Art. 9/20 hooks)
                    try {
                        $pmrSvc = new \App\Service\PmrDetectionService();
                        $pmr = $pmrSvc->analyze([
                            'rawText' => $textBlob,
                            'seller' => (string)($form['operator'] ?? ''),
                            'fields' => [],
                        ]);
                        // Record evidence for debugging/UX
                        $meta['_pmr_detection'] = [
                            'evidence' => (array)($pmr['evidence'] ?? []),
                            'confidence' => (float)($pmr['confidence'] ?? 0.0),
                            'discount_type' => $pmr['discount_type'] ?? null,
                        ];
                        if (!empty($pmr['pmr_user']) || !empty($pmr['pmr_booked'])) { $meta['_pmr_detected'] = true; }
                        // Populate AUTO for evaluator mismatch checks
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($pmr['pmr_user'])) { $meta['_auto']['pmr_user'] = ['value' => 'Ja', 'source' => 'pmr_detection']; }
                        if (!empty($pmr['pmr_booked'])) { $meta['_auto']['pmr_booked'] = ['value' => 'Ja', 'source' => 'pmr_detection']; }
                        // Set explicit hooks to 'Ja' only when positively detected; otherwise leave unknown and ask later
                        if (!empty($pmr['pmr_user']) && empty($meta['pmr_user'])) { $meta['pmr_user'] = 'Ja'; }
                        if (!empty($pmr['pmr_booked']) && empty($meta['pmr_booked'])) { $meta['pmr_booked'] = 'Ja'; }
                        // Server-side auto-default: if no PMR evidence and low confidence, default to 'Nej' so user can be/afkræfte
                        $pmrConf = (float)($pmr['confidence'] ?? 0.0);
                        $hasEvidence = !empty($pmr['pmr_user']) || !empty($pmr['pmr_booked']) || (!empty($meta['_pmr_detection']['evidence']));
                        if (!$hasEvidence && $pmrConf < 0.30) {
                            // pmr_user default
                            $cur = strtolower((string)($meta['pmr_user'] ?? ''));
                            if ($cur === '' || $cur === 'unknown') {
                                $meta['pmr_user'] = 'Nej';
                                $meta['_auto']['pmr_user'] = ['value' => 'Nej', 'source' => 'pmr_detection'];
                                $meta['logs'][] = 'AUTO: pmr_user=Nej (no PMR signals; conf=' . number_format($pmrConf, 2) . ')';
                            }
                            // pmr_booked default
                            $curB = strtolower((string)($meta['pmr_booked'] ?? ''));
                            if ($curB === '' || $curB === 'unknown') {
                                $meta['pmr_booked'] = 'Nej';
                                $meta['_auto']['pmr_booked'] = ['value' => 'Nej', 'source' => 'pmr_detection'];
                            }
                        }
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'WARN: PMR detection failed: ' . $e->getMessage();
                    }
                    // Bike auto-detection from ticket text (Art. 9 hooks – triggers TRIN 9 pkt.5 in one-form)
                    try {
                        $bikeSvc = new \App\Service\BikeDetectionService();
                        $bike = $bikeSvc->analyze([
                            'rawText' => $textBlob,
                            'seller' => (string)($form['operator'] ?? ''),
                            'barcodeText' => (string)($meta['_barcode_payload'] ?? ''),
                        ]);
                        $meta['_bike_detection'] = [
                            'evidence' => (array)($bike['evidence'] ?? []),
                            'confidence' => (float)($bike['confidence'] ?? 0.0),
                            'count' => $bike['bike_count'] ?? null,
                            'operator_hint' => $bike['operator_hint'] ?? null,
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($bike['bike_booked'])) { $meta['_auto']['bike_booked'] = ['value' => 'Ja', 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_count'])) { $meta['_auto']['bike_count'] = ['value' => (string)$bike['bike_count'], 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_res_required'])) { $meta['_auto']['bike_res_required'] = ['value' => (string)$bike['bike_res_required'], 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_reservation_type'])) { $meta['_auto']['bike_reservation_type'] = ['value' => (string)$bike['bike_reservation_type'], 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_booked']) && empty($meta['bike_booked'])) { $meta['bike_booked'] = 'Ja'; }
                        if (empty($bike['bike_booked']) && empty($meta['bike_booked'])) {
                            // Simplified UX: preselect Nej when no bike signals at all (user can override to Ja)
                            $meta['bike_booked'] = 'Nej';
                            $meta['logs'][] = 'AUTO: bike_booked=Nej (no bike signals detected)';
                        }
                        if (!empty($bike['bike_count']) && empty($meta['bike_count'])) { $meta['bike_count'] = (string)$bike['bike_count']; }
                        // PRG inline Article 6 flow convenience: seed bike_reservation_required when auto-known
                        if (!empty($bike['bike_res_required']) && empty($meta['bike_reservation_required'])) {
                            $val = (string)$bike['bike_res_required'];
                            if (in_array($val, ['yes','no'], true)) { $meta['bike_reservation_required'] = $val; }
                        }
                        // Todo #9: Server-side auto-default for bike_was_present (negative)
                        // Only when user hasn't answered yet, and detection shows no bike evidence.
                        // We record both a meta value and an _auto trace so evaluators/UI can see the provenance.
                        $bw = strtolower((string)($meta['bike_was_present'] ?? ''));
                        if ($bw === '' || $bw === 'unknown') {
                            $conf = (float)($bike['confidence'] ?? 0.0);
                            // No bike evidence: set explicit negative default when confidence is below ~0.5
                            // Rationale: with the adjusted formula, 0 hits => 0.10, 1 hit => ~0.48. We only default 'no' when < 0.5.
                            if (empty($bike['bike_booked']) && $conf < 0.5) {
                                $meta['bike_was_present'] = 'no';
                                if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                                $meta['_auto']['bike_was_present'] = ['value' => 'no', 'source' => 'bike_detection'];
                                $meta['logs'][] = 'AUTO: bike_was_present=no (no bike evidence; conf=' . number_format($conf, 2) . ')';
                            }
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Bike detection failed: ' . $e->getMessage(); }

                    // Ticket type detection: fare flexibility and train specificity (Art. 9 Bilag II del I)
                    try {
                        $ttd = (new \App\Service\TicketTypeDetectionService())->analyze([
                            'rawText' => $textBlob,
                            'fareName' => (string)($meta['_auto']['fareName']['value'] ?? ''),
                            'fareCode' => (string)($meta['_auto']['fareCode']['value'] ?? ''),
                            'productName' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                            'seatLine' => '',
                            'validityLine' => '',
                            'reservationLine' => '',
                        ]);
                        $meta['_ticket_type_detection'] = [
                            'evidence' => (array)($ttd['evidence'] ?? []),
                            'confidence' => (float)($ttd['confidence'] ?? 0.0),
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($ttd['fare_flex_type']) && $ttd['fare_flex_type'] !== 'other') {
                            $meta['_auto']['fare_flex_type'] = ['value' => (string)$ttd['fare_flex_type'], 'source' => 'ticket_type_detection'];
                            if (empty($meta['fare_flex_type'])) { $meta['fare_flex_type'] = (string)$ttd['fare_flex_type']; }
                        } elseif (!isset($meta['_auto']['fare_flex_type'])) {
                            $meta['_auto']['fare_flex_type'] = ['value' => 'other', 'source' => 'ticket_type_detection'];
                        }
                        if (!empty($ttd['train_specificity']) && $ttd['train_specificity'] !== 'unknown') {
                            $meta['_auto']['train_specificity'] = ['value' => (string)$ttd['train_specificity'], 'source' => 'ticket_type_detection'];
                            if (empty($meta['train_specificity'])) { $meta['train_specificity'] = (string)$ttd['train_specificity']; }
                        } elseif (!isset($meta['_auto']['train_specificity'])) {
                            $meta['_auto']['train_specificity'] = ['value' => 'unknown', 'source' => 'ticket_type_detection'];
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Ticket type detection failed: ' . $e->getMessage(); }

                    // Class/reservation detection: set AUTO for TRIN 9 pkt.6 and open interest when detected
                    try {
                        $crd = (new \App\Service\ClassReservationDetectionService())->analyze([
                            'rawText' => $textBlob,
                            'fields' => [
                                'fareName' => (string)($meta['_auto']['fareName']['value'] ?? ''),
                                'productName' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                                'reservationLine' => (string)($meta['_auto']['reservationLine']['value'] ?? ''),
                                'seatLine' => (string)($meta['_auto']['seatLine']['value'] ?? ''),
                                'coachSeatBlock' => (string)($meta['_auto']['coachSeatBlock']['value'] ?? ''),
                            ]
                        ]);
                        $meta['_class_detection'] = [
                            'evidence' => (array)($crd['evidence'] ?? []),
                            'confidence' => (float)($crd['confidence'] ?? 0.0),
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        $opened = false;
                        if (!empty($crd['fare_class_purchased']) && $crd['fare_class_purchased'] !== 'unknown') {
                            $meta['_auto']['fare_class_purchased'] = ['value' => (string)$crd['fare_class_purchased'], 'source' => 'class_det'];
                            if (empty($meta['fare_class_purchased'])) { $meta['fare_class_purchased'] = (string)$crd['fare_class_purchased']; }
                            $opened = true;
                        }
                        if (!empty($crd['berth_seat_type']) && $crd['berth_seat_type'] !== 'unknown') {
                            $meta['_auto']['berth_seat_type'] = ['value' => (string)$crd['berth_seat_type'], 'source' => 'class_det'];
                            if (empty($meta['berth_seat_type'])) { $meta['berth_seat_type'] = (string)$crd['berth_seat_type']; }
                            $opened = true;
                        }
                        if ($opened) { $form['interest_class'] = $form['interest_class'] ?? '1'; }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Class/reservation detection failed: ' . $e->getMessage(); }

                    // Fallback heuristik hvis class detection ikke gav resultat
                    try {
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        $lowTxt = mb_strtolower($textBlob, 'UTF-8');
                        $autoClass = (string)($meta['_auto']['fare_class_purchased']['value'] ?? '');
                        $autoBerth = (string)($meta['_auto']['berth_seat_type']['value'] ?? '');
                        if ($autoClass === '') {
                            if (preg_match('/\\b(1\\.?\\s*kl|1st|first|premiere|business)\\b/i', $textBlob)) {
                                $autoClass = '1st_class';
                            } elseif (preg_match('/\\b(2\\.?\\s*kl|2nd|second|seconde|standard|economy)\\b/i', $textBlob)) {
                                $autoClass = '2nd_class';
                            }
                            if ($autoClass !== '') {
                                $meta['_auto']['fare_class_purchased'] = ['value' => $autoClass, 'source' => 'regex_fallback'];
                                if (empty($meta['fare_class_purchased'])) { $meta['fare_class_purchased'] = $autoClass; }
                            }
                        }
                        if ($autoBerth === '') {
                            if (preg_match('/sleeper|sove|couchette|ligge|sleeping\\s*car/i', $textBlob)) {
                                $autoBerth = preg_match('/couchette|ligge/i', $textBlob) ? 'couchette' : 'sleeper';
                            } elseif (preg_match('/reserved\\s*seat|seat\\s*res|pladsreservation|reserv\\w* plads/i', $textBlob)) {
                                $autoBerth = 'seat_reserved';
                            }
                            if ($autoBerth !== '') {
                                $meta['_auto']['berth_seat_type'] = ['value' => $autoBerth, 'source' => 'regex_fallback'];
                                if (empty($meta['berth_seat_type'])) { $meta['berth_seat_type'] = $autoBerth; }
                            }
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Class regex fallback failed: ' . $e->getMessage(); }

                    // Resolve exemptions/blocked based on country + product using operators catalog
                    try {
                        $countryVal = strtoupper((string)($meta['_auto']['operator_country']['value'] ?? ($form['operator_country'] ?? '')));
                        $productVal = (string)($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? ''));
                        if ($countryVal !== '' && $productVal !== '') {
                            $resolver = new ExemptionResolver();
                            $ex = $resolver->resolve($countryVal, $productVal, [
                                'dep_station' => (string)($form['dep_station'] ?? ($journey['dep_station'] ?? '')),
                                'arr_station' => (string)($form['arr_station'] ?? ($journey['arr_station'] ?? '')),
                                'isInternational' => !empty($journey['isInternational']),
                                'endsOutsideEU' => !empty($journey['endsOutsideEU']),
                            ]);
                            $form['_exemption'] = $ex;
                            if (!empty($ex['blocked'])) {
                                $session->write('flow.form', $form);
                                $session->write('flow.meta', $meta);
                                try { $this->Flash->error('Tjenesten er undtaget (produkt: ' . $productVal . ' i ' . $countryVal . '). Vi kan ikke fortsætte med EU-flowet.'); } catch (\Throwable $e) { /* ignore flash */ }
                                return $this->redirect(['action' => 'start']);
                            }
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Exemption resolver failed: ' . $e->getMessage(); }

                    // Heuristic patch-ups for operator/country/product when extractor is weak
                    try {
                        $lowTxt = mb_strtolower($textBlob, 'UTF-8');
                        $opVal = (string)($meta['_auto']['operator']['value'] ?? '');
                        $opCountry = (string)($meta['_auto']['operator_country']['value'] ?? '');
                        // Currency → country hint
                        if ($opCountry === '' && preg_match('/\bczk\b/i', $textBlob)) {
                            $opCountry = 'CZ';
                            $meta['_auto']['operator_country'] = ['value' => 'CZ', 'source' => 'currency'];
                            $form['operator_country'] = 'CZ';
                        }
                        // České dráhy
                        if ($opVal === '' && preg_match('/česk[éy]?\s+dr[áa]h[yi]/u', $lowTxt)) {
                            $opVal = 'České dráhy';
                            $meta['_auto']['operator'] = ['value' => $opVal, 'source' => 'heuristic'];
                            $form['operator'] = $opVal;
                            if ($opCountry === '') { $opCountry = 'CZ'; $meta['_auto']['operator_country'] = ['value' => 'CZ', 'source' => 'heuristic']; $form['operator_country'] = 'CZ'; }
                        }
                        if ($opVal === '' && preg_match('/czech\s*railways|ceske\s*drahy/i', $textBlob)) {
                            $opVal = 'České dráhy';
                            $meta['_auto']['operator'] = ['value' => $opVal, 'source' => 'heuristic'];
                            $form['operator'] = $opVal;
                            if ($opCountry === '') { $opCountry = 'CZ'; $meta['_auto']['operator_country'] = ['value' => 'CZ', 'source' => 'heuristic']; $form['operator_country'] = 'CZ'; }
                        }
                        // Product EC/EuroCity
                        if (empty($meta['_auto']['operator_product']['value']) && preg_match('/\b(EC|Euro\s*City)\b/i', $textBlob)) {
                            $meta['_auto']['operator_product'] = ['value' => 'EC', 'source' => 'heuristic'];
                            $form['operator_product'] = 'EC';
                        }
                    } catch (\Throwable $e) { /* ignore */ }

                    // Seller/retailer vs operator heuristics (Art. 12 seeds)
                    try {
                        $sellerType = null;
                        $low = mb_strtolower($textBlob, 'UTF-8');

                        // Known operator brand hints (minimal set, expanded as needed)
                        $operatorBrands = [
                            'dsb', 'danske statsbaner', 'dsb.dk',
                            'deutsche bahn', 'bahn.de', 'db ', ' db\b',
                            'sncf', 'oui.sncf', 'sncf-connect', 'sncf connect',
                            'sj ', ' sj\b', 'statens järnvägar'
                        ];

                        // Strong agency keywords (3rd-party retailers). Note: 'rejseplanen' is NOT treated as agency by default
                        $agencyBrands = [ 'trainline', 'omio', 'goeuro', 'rail europe', 'raileurope', 'acp rail', 'raileasy' ];

                        $hasOperatorBrand = false;
                        foreach ($operatorBrands as $kw) { if (preg_match('/'. $kw .'/iu', $low)) { $hasOperatorBrand = true; break; } }

                        $hasAgencyBrand = false;
                        foreach ($agencyBrands as $kw) { if (strpos($low, $kw) !== false) { $hasAgencyBrand = true; break; } }

                        // Parse generic seller phrases and map the named entity to operator/agency if possible
                        if ($sellerType === null) {
                            if (preg_match('/\b(sold by|vendu par|verkauf(?:t)? durch|billetudsteder|issued by)\b[:\s]*([^\n\r]{0,80})/iu', $textBlob, $m)) {
                                $tail = mb_strtolower((string)($m[2] ?? ''), 'UTF-8');
                                $tail = preg_replace('/[.,;:\-–—\(\)\[\]]/u', ' ', (string)$tail) ?? (string)$tail;
                                // If the named entity looks like an operator brand, pick operator; else agency
                                $namedIsOperator = false; $namedIsAgency = false;
                                foreach ($operatorBrands as $kw) { if ($kw !== '' && preg_match('/'. $kw .'/iu', (string)$tail)) { $namedIsOperator = true; break; } }
                                if (!$namedIsOperator) {
                                    foreach ($agencyBrands as $kw) { if ($kw !== '' && str_contains((string)$tail, $kw)) { $namedIsAgency = true; break; } }
                                }
                                if ($namedIsOperator) { $sellerType = 'operator'; }
                                elseif ($namedIsAgency) { $sellerType = 'agency'; }
                                // If ambiguous, defer decision to brand presence below
                            }
                            // "on behalf of" typically indicates agency involvement, but if clearly "on behalf of DSB" we still consider operator as seller for Art.12
                            if ($sellerType === null && preg_match('/\bon behalf of\b\s*([^\n\r]{0,80})/iu', $textBlob, $m2)) {
                                $tail = mb_strtolower((string)($m2[1] ?? ''), 'UTF-8');
                                $tail = preg_replace('/[.,;:\-–—\(\)\[\]]/u', ' ', (string)$tail) ?? (string)$tail;
                                $namedIsOperator = false;
                                foreach ($operatorBrands as $kw) { if ($kw !== '' && preg_match('/'. $kw .'/iu', (string)$tail)) { $namedIsOperator = true; break; } }
                                if ($namedIsOperator) { $sellerType = 'operator'; } else { $sellerType = 'agency'; }
                            }
                        }

                        // If no explicit phrase mapping decided, fall back to brand presence
                        if ($sellerType === null) {
                            if ($hasAgencyBrand && !$hasOperatorBrand) { $sellerType = 'agency'; }
                            elseif ($hasOperatorBrand) { $sellerType = 'operator'; }
                        }

                        // If we already inferred a concrete operator name, prefer operator unless a strong agency brand was clearly detected
                        $opName = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
                        if ($opName !== '') {
                            if ($sellerType === null) { $sellerType = 'operator'; }
                            elseif ($sellerType === 'agency' && !$hasAgencyBrand) { $sellerType = 'operator'; }
                        }

                        if ($sellerType !== null) { $journey['seller_type'] = $sellerType; }
                    } catch (\Throwable $e) { /* ignore */ }

                    // Pricing (Art. 9 §3): fare flexibility type and train specificity + weak hints
                    try {
                        $pr = new \App\Service\PricingDetectionService();
                        $detPT = $pr->detectPurchaseType($textBlob);
                        if (is_array($detPT)) {
                            $mapPT = function(string $v): string {
                                $vL = mb_strtolower($v, 'UTF-8');
                                if (strpos($vL, 'abonnement') !== false || strpos($vL, 'periodekort') !== false || strpos($vL, 'pass') !== false) return 'pass';
                                if (strpos($vL, 'semi') !== false) return 'semiflex';
                                if (strpos($vL, 'non-flex') !== false || strpos($vL, 'nonflex') !== false || strpos($vL, 'standard') !== false) return 'nonflex';
                                if (strpos($vL, 'flex') !== false) return 'flex';
                                return 'other';
                            };
                            $normVal = $mapPT((string)($detPT['value'] ?? ''));
                            $meta['_auto']['fare_flex_type'] = ['value' => $normVal, 'confidence' => ($detPT['confidence'] ?? 0.6), 'source' => 'normalized'];
                            if (empty($meta['fare_flex_type'])) { $meta['fare_flex_type'] = $normVal; }
                        }
                        $detTB = $pr->detectTrainBinding($textBlob);
                        if (is_array($detTB)) {
                            $mapTB = function(string $v): string {
                                $vL = mb_strtolower($v, 'UTF-8');
                                if (strpos($vL, 'specifik') !== false || strpos($vL, 'specific') !== false || strpos($vL, 'kun') !== false) return 'specific';
                                if (strpos($vL, 'vilkårlig') !== false || strpos($vL, 'any') !== false || strpos($vL, 'alle tog') !== false || strpos($vL, 'all trains') !== false) return 'any_day';
                                return 'unknown';
                            };
                            $normVal = $mapTB((string)($detTB['value'] ?? ''));
                            $meta['_auto']['train_specificity'] = ['value' => $normVal, 'confidence' => ($detTB['confidence'] ?? 0.6), 'source' => 'normalized'];
                            if (empty($meta['train_specificity'])) { $meta['train_specificity'] = $normVal; }
                        }
                        $detMP = $pr->suggestMultiPriceShown($textBlob);
                        if (is_array($detMP)) { $meta['_auto']['multi_price_shown'] = $detMP; }
                        $detCH = $pr->suggestCheapestHighlighted($textBlob);
                        if (is_array($detCH)) { $meta['_auto']['cheapest_highlighted'] = $detCH; }
                    } catch (\Throwable $e) { /* ignore */ }

                    // Segments and passengers + identifiers (+barcode for images)
                    try {
                        $tp = new \App\Service\TicketParseService();
                        $segAuto = $tp->parseSegmentsFromText($textBlob);
                        // Feature-flagged LLM structuring fallback when no segments were found
                        try {
                            $flag = strtolower((string)((function_exists('env')?env('USE_LLM_STRUCTURING'):getenv('USE_LLM_STRUCTURING')) ?? ''));
                            if ((empty($segAuto) || count((array)$segAuto) === 0) && in_array($flag, ['1','true','yes','on'], true)) {
                                $segLlm = (new \App\Service\TicketExtraction\LlmSegmentsExtractor())->extractSegments($textBlob);
                                foreach ((array)($segLlm['logs'] ?? []) as $lg) { $meta['logs'][] = $lg; }
                                if (!empty($segLlm['segments'])) { $segAuto = (array)$segLlm['segments']; $meta['_segments_source'] = 'llm_structuring'; }
                            }
                            // Optional: verify/augment segments even when we already have some, driven by env or ?segments_verify=1
                            $verify = false;
                            try {
                                $qv = strtolower((string)($this->request->getQuery('segments_verify') ?? ''));
                                $verifyEnv = strtolower((string)((function_exists('env')?env('LLM_VERIFY_SEGMENTS'):getenv('LLM_VERIFY_SEGMENTS')) ?? ''));
                                $verify = in_array($qv, ['1','true','yes','on'], true) || in_array($verifyEnv, ['1','true','yes','on'], true);
                            } catch (\Throwable $e) { $verify = false; }
                            if ($verify) {
                                // Use strict verifier + deterministic merge
                                $cutoffRaw = strtolower((string)((function_exists('env')?env('LLM_SEGMENTS_CONFIDENCE_CUTOFF'):getenv('LLM_SEGMENTS_CONFIDENCE_CUTOFF')) ?? ''));
                                $cutoff = is_numeric($cutoffRaw) ? (float)$cutoffRaw : 0.55;
                                $hasBase = is_array($segAuto) && count($segAuto) > 0;
                                if (!$hasBase) { $cutoff = max(0.45, $cutoff - 0.10); }
                                $contextDate = (string)($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? ''));
                                $ver = (new \App\Service\TicketExtraction\LlmSegmentsVerifier())->verify($textBlob, is_array($segAuto)?$segAuto:[], $contextDate);
                                foreach ((array)($ver['logs'] ?? []) as $lg) { $meta['logs'][] = '[verify] ' . $lg; }
                                $llmSegs = (array)($ver['segments'] ?? []);
                                $meta['_segments_llm_suggest'] = $llmSegs;
                                // Normalize stations before merge for better dedup
                                $normStation = function(string $n): string {
                                    $n = trim($n); if ($n==='') return '';
                                    $low = mb_strtolower($n, 'UTF-8');
                                    $map = [ 'københavn h' => 'København H', 'copenhagen central' => 'København H', 'stockholm c' => 'Stockholm C', 'paris nord' => 'Paris Nord' ];
                                    if (isset($map[$low])) return $map[$low];
                                    if (preg_match('/^(.*)\s+c$/iu', $n, $m)) return trim($m[1]) . ' C';
                                    return $n;
                                };
                                $base = is_array($segAuto) ? $segAuto : [];
                                $keyOf = function($s) use ($normStation){
                                    $from = $normStation((string)($s['from']??''));
                                    $to = $normStation((string)($s['to']??''));
                                    return strtoupper(trim($from) . '|' . trim($to) . '|' . trim((string)($s['schedDep']??'')) . '|' . trim((string)($s['schedArr']??'')));
                                };
                                $seen = [];
                                $merged = [];
                                foreach ($base as $s) { $k = $keyOf($s); if (!isset($seen[$k])) { $seen[$k]=true; $merged[]=$s + ['source'=>($s['source']??'parser'),'confidence'=>($s['confidence']??0.95)]; } }
                                $added = 0;
                                foreach ($llmSegs as $s) {
                                    $conf = (float)($s['confidence'] ?? 0.0);
                                    if ($conf < $cutoff && $hasBase) { continue; }
                                    $k = $keyOf($s);
                                    if (!isset($seen[$k])) { $seen[$k]=true; $s['source'] = 'llm_infer'; $merged[]=$s; $added++; }
                                }
                                // Sort by departure time when available (keep stable)
                                usort($merged, function($a,$b){ return strcmp((string)($a['schedDep']??''), (string)($b['schedDep']??'')); });
                                if ($added > 0) { $meta['logs'][] = 'LLM verify merged +' . (int)$added . ' segment(s) (cutoff=' . $cutoff . ')'; }
                                $segAuto = $merged;
                            }
                        } catch (\Throwable $e) { $meta['logs'][] = 'WARN: LLM structuring failed: ' . $e->getMessage(); }
                        // Sanitize segments from both heuristics and LLM: drop non-station phrases (e.g., 'den dag og den afgang')
                        try {
                            $isValidName = function(string $name): bool {
                                $n = trim(mb_strtolower($name, 'UTF-8'));
                                if ($n === '') return false;
                                $bad = [
                                    'se rejseplan', 'rejseplan', 'se rejsen', 'se plan', 'se mere',
                                    'den dag og den afgang', 'den dag og afgang', 'den afgang',
                                    'tarifgemeinschaft', 'verkehrsverbund', 'bedingungen', 'hinweise', 'klasse', 'classe', 'class',
                                    // French ticket boilerplate often misread as stations
                                    'réservation du billet', 'reservation du billet', 'réservation', 'reservation',
                                    // Scheduling/label words that can leak into segments
                                    'scheduled arr', 'scheduled arrival', 'scheduled dep', 'scheduled departure',
                                    'date', 'dato', 'time', 'tid',
                                    // Nordic/DA/SV common labels
                                    'ankomst', 'afgang', 'avgång', 'ank.', 'afg.', 'ankom',
                                    // Swedish/SJ labels
                                    'byte', 'assisterad', 'rullstol', 'reserv', 'välsw', 'eu-gemensam', 'köp', 'køb',
                                    // Multilingual ticket/reservation/purchase/customer-service words (broad EU set)
                                    'billet', 'billete', 'bilhete', 'biglietto', 'bilet', 'jízdenka', 'lístok', 'vozovnica', 'bilete',
                                    'reservation', 'réservation', 'reserva', 'prenotazione', 'rezervace', 'rezerwacja', 'rezervácia', 'rezervare', 'foglalás',
                                    'compra', 'achat', 'acquisto', 'osto', 'zakup', 'nákup', 'cumpărare',
                                    'cliente', 'klant', 'klient', 'zákazník', 'ügyfél', 'kundeservice', 'service client',
                                    // product/category words often misread as stations
                                    'frecciargento', 'frecciarossa', 'freccia', 'intercity', 'eurocity', 'railjet', 'regio', 'regionale', 'italo'
                                ];
                                foreach ($bad as $bp) { if (str_contains($n, $bp)) return false; }
                                if (substr_count($n, ' ') >= 5) return false;
                                if (!preg_match('/\p{L}/u', $n)) return false;
                                return true;
                            };
                            $before = is_array($segAuto) ? count($segAuto) : 0;
                            $segAuto = array_values(array_filter((array)$segAuto, function($s) use ($isValidName){
                                $from = (string)($s['from'] ?? '');
                                $to = (string)($s['to'] ?? '');
                                if ($from === '' || $to === '' || $from === $to) return false;
                                if (!$isValidName($from) || !$isValidName($to)) return false;
                                return true;
                            }));
                            $after = count($segAuto);
                            if ($after < $before) { $meta['logs'][] = 'Sanitized segments: removed ' . (int)($before-$after) . ' non-station row(s).'; }
                        } catch (\Throwable $e) { /* ignore sanitize errors */ }
                        // Coalesce pseudo-skifts: when seg[i].to ≈ seg[i+1].from (e.g., 'BOLOGNA CLE' vs 'BOLOGNA CENTRALE'), merge into a single leg
                        try {
                            $norm = function(string $s): string {
                                $u = mb_strtoupper(trim($s), 'UTF-8');
                                $u = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', (string)$u) ?? (string)$u;
                                $u = preg_replace('/\s+/', ' ', (string)$u) ?? (string)$u;
                                // simple Italian short→long: ' CLE' -> ' CENTRALE'
                                $u = preg_replace('/\bCLE\b/u', 'CENTRALE', (string)$u) ?? (string)$u;
                                return trim((string)$u);
                            };
                            $similar = function(string $a, string $b) use ($norm): bool {
                                $aa = $norm($a); $bb = $norm($b);
                                if ($aa === '' || $bb === '') return false; if ($aa === $bb) return true;
                                if (str_contains($aa, $bb) || str_contains($bb, $aa)) return true;
                                // Levenshtein with guard (ASCII-only approximation)
                                $a1 = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$aa) ?: $aa;
                                $b1 = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$bb) ?: $bb;
                                if ($a1 === '' || $b1 === '') return false;
                                $dist = levenshtein($a1, $b1);
                                $max = max(strlen($a1), strlen($b1));
                                return $max > 0 ? ($dist <= max(2, (int)floor($max * 0.2))) : false;
                            };
                            $merged = [];
                            for ($i = 0; $i < count($segAuto); $i++) {
                                $cur = $segAuto[$i];
                                $next = $segAuto[$i+1] ?? null;
                                if ($next) {
                                    $same = $similar((string)($cur['to'] ?? ''), (string)($next['from'] ?? ''));
                                    if ($same) {
                                        // If next has no time/train, or trainNo equals, extend cur
                                        $sameTrain = ((string)($cur['trainNo'] ?? '') !== '') && (string)($cur['trainNo'] ?? '') === (string)($next['trainNo'] ?? '');
                                        $nextHasAnchor = ((string)($next['schedDep'] ?? '') !== '') || ((string)($next['schedArr'] ?? '') !== '') || ((string)($next['trainNo'] ?? '') !== '');
                                        if ($sameTrain || !$nextHasAnchor) {
                                            $cur['to'] = (string)($next['to'] ?? $cur['to']);
                                            if (!empty($next['schedArr']) && empty($cur['schedArr'])) { $cur['schedArr'] = (string)$next['schedArr']; }
                                            if (!empty($next['arrDate']) && empty($cur['arrDate'])) { $cur['arrDate'] = (string)$next['arrDate']; }
                                            $merged[] = $cur; $i++; continue;
                                        }
                                    }
                                }
                                $merged[] = $cur;
                            }
                            if (count($merged) < count($segAuto)) { $meta['logs'][] = 'Coalesced pseudo-skifts: -' . (int)(count($segAuto)-count($merged)) . ' segment(s).'; }
                            $segAuto = $merged;
                        } catch (\Throwable $e) { /* ignore coalesce errors */ }
                        // attach parser debug for inspection in UI/debug panels
                        try { $meta['_segments_debug'] = \App\Service\TicketParseService::getLastDebug(); } catch (\Throwable $e) { /* ignore */ }
                        if (!empty($segAuto)) { $meta['_segments_auto'] = $segAuto; }
                        // If there are no real connections (<=1 segment), clear missed-connection field so it doesn't linger
                        try {
                            $segCountNow = is_array($segAuto) ? count($segAuto) : 0;
                            if ($segCountNow <= 1) {
                                if (!empty($form['missed_connection_station'])) { $form['missed_connection_station'] = ''; }
                                if (!empty($form['_miss_conn_choices'])) { unset($form['_miss_conn_choices']); }
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                        // concise log summary
                        try {
                            $cntSeg = is_array($segAuto) ? count($segAuto) : 0;
                            if (!isset($meta['logs'])) { $meta['logs'] = []; }
                            if ($cntSeg > 0) {
                                $preview = array_slice($segAuto, 0, 3);
                                $parts = [];
                                foreach ($preview as $s) {
                                    $from = (string)($s['from'] ?? '');
                                    $to = (string)($s['to'] ?? '');
                                    $d = (string)($s['schedDep'] ?? '');
                                    $a = (string)($s['schedArr'] ?? '');
                                    $parts[] = trim($from . '→' . $to . ' ' . $d . '–' . $a);
                                }
                                $meta['logs'][] = 'Segments auto: ' . $cntSeg . ' (' . implode(' | ', $parts) . (count($segAuto) > 3 ? ' | …' : '') . ')';
                            } else {
                                $meta['logs'][] = 'Segments auto: 0';
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                        // Fallback fill for dep/arr times: use structured segments when OCR field times are missing/invalid
                        try {
                            $isValidTime = function($t){ return is_string($t) && preg_match('/^(?:[01]?\\d|2[0-3]):[0-5]\\d$/', (string)$t); };
                            if (!$isValidTime($form['dep_time'] ?? '')) {
                                if (is_array($segAuto)) {
                                    foreach ($segAuto as $s) {
                                        $t = (string)($s['schedDep'] ?? '');
                                        if ($isValidTime($t)) { $form['dep_time'] = $t; $meta['logs'][] = 'Filled dep_time from segments: ' . $t; break; }
                                    }
                                }
                            }
                            if (!$isValidTime($form['arr_time'] ?? '')) {
                                if (is_array($segAuto)) {
                                    for ($ri = count($segAuto) - 1; $ri >= 0; $ri--) {
                                        $t = (string)($segAuto[$ri]['schedArr'] ?? '');
                                        if ($isValidTime($t)) { $form['arr_time'] = $t; $meta['logs'][] = 'Filled arr_time from segments: ' . $t; break; }
                                    }
                                }
                            }
                            // Reconcile mismatches: if both times exist but differ from segment endpoints by >30 min, prefer segment times
                            $toMinutes = function(string $hhmm): ?int { if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) return null; return ((int)$m[1])*60 + (int)$m[2]; };
                            $segStart = null; $segEnd = null;
                            if (is_array($segAuto) && count($segAuto) > 0) {
                                // first dep
                                for ($i0=0; $i0<count($segAuto); $i0++) { $td = (string)($segAuto[$i0]['schedDep'] ?? ''); if ($isValidTime($td)) { $segStart = $toMinutes($td); break; } }
                                // last arr
                                for ($i1=count($segAuto)-1; $i1>=0; $i1--) { $ta = (string)($segAuto[$i1]['schedArr'] ?? ''); if ($isValidTime($ta)) { $segEnd = $toMinutes($ta); break; } }
                            }
                            if ($segStart !== null && $segEnd !== null) {
                                // arrival reconciliation
                                $arrNow = isset($form['arr_time']) && $isValidTime($form['arr_time']) ? $toMinutes((string)$form['arr_time']) : null;
                                if ($arrNow !== null && abs($arrNow - $segEnd) > 30) {
                                    $old = (string)$form['arr_time'];
                                    // format back to HH:MM
                                    $new = sprintf('%02d:%02d', intdiv($segEnd,60), $segEnd%60);
                                    $form['arr_time'] = $new; $meta['logs'][] = 'Reconciled arr_time from segments (was ' . $old . ' → ' . $new . ')';
                                }
                                // departure reconciliation (edge case)
                                $depNow = isset($form['dep_time']) && $isValidTime($form['dep_time']) ? $toMinutes((string)$form['dep_time']) : null;
                                if ($depNow !== null && abs($depNow - $segStart) > 30) {
                                    $old = (string)$form['dep_time'];
                                    $new = sprintf('%02d:%02d', intdiv($segStart,60), $segStart%60);
                                    $form['dep_time'] = $new; $meta['logs'][] = 'Reconciled dep_time from segments (was ' . $old . ' → ' . $new . ')';
                                }
                            }
                        } catch (\Throwable $e) { /* ignore fallback time errors */ }
                        $ids = $tp->extractIdentifiers($textBlob);
                        // Light operator heuristics (DSB and others) when clearly present in ticket text
                        try {
                            $lowTxt = mb_strtolower($textBlob, 'UTF-8');
                            $hasDSB = (bool)preg_match('/\bdsb\b|danske\s+statsbaner|dsb\.dk/i', $textBlob);
                            if ($hasDSB) {
                                $meta['_auto']['operator'] = ['value' => 'DSB', 'source' => 'heuristic'];
                                $form['operator'] = 'DSB';
                                if (empty($meta['_auto']['operator_country']['value'])) { $meta['_auto']['operator_country'] = ['value' => 'DK', 'source' => 'heuristic']; $form['operator_country'] = 'DK'; }
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                        if (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                            $bc = $tp->parseBarcode($dest);
                            if (!empty($bc['payload'])) { $meta['_barcode'] = [ 'format' => $bc['format'], 'chars' => strlen((string)$bc['payload']) ]; $ids = array_merge($ids, $tp->extractIdentifiers((string)$bc['payload'])); }
                        }
                        // Fallback: try deriving a PNR-like token from the filename if none found
                        if (empty($ids['pnr']) && !empty($safe)) {
                            $base = strtoupper((string)pathinfo($safe, PATHINFO_FILENAME));
                            if (preg_match('/([A-Z0-9]{6,9})/', $base, $mm)) { $ids['pnr'] = $mm[1]; }
                        }
                        if (!empty($ids)) {
                            $meta['_identifiers'] = $ids;
                            if (!empty($ids['pnr'])) {
                                $journey['bookingRef'] = (string)$ids['pnr'];
                                // Prefer showing the PNR in the 3.2.7 field if the extractor didn't set ticket_no
                                if (empty($form['ticket_no'])) { $form['ticket_no'] = (string)$ids['pnr']; }
                            }
                        }
                        $pax = $tp->extractPassengerData($textBlob); if (!empty($pax)) { $meta['_passengers_auto'] = $pax; $journey['passengerCount'] = count($pax); $journey['passengers'] = $pax; }
                        $dates = $tp->extractDates($textBlob); if (!empty($dates) && empty($form['dep_date'])) { $form['dep_date'] = (string)$dates[0]; }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: parse segments/ids failed: ' . $e->getMessage(); }

                    // Art. 12: quick auto-derivation when we have a PNR and a seller type
                    if (!empty($journey['bookingRef'])) {
                        $sellerTypeNow = (string)($journey['seller_type'] ?? '');
                        if ($sellerTypeNow === 'operator') { $meta['single_txn_operator'] = 'yes'; $meta['seller_type_operator'] = 'yes'; }
                        if ($sellerTypeNow === 'agency') { $meta['single_txn_retailer'] = 'yes'; $meta['seller_type_agency'] = 'yes'; }
                    }

                    // Build missed-connection choices from segments when available (no longer gated by TRIN 2)
                    if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
                        $choices = [];
                        $segs = (array)$meta['_segments_auto'];
                        $lastIdx = count($segs) - 1;
                        foreach ($segs as $idx => $s) {
                            $arr = (string)($s['to'] ?? '');
                            if ($arr === '' || $idx === $lastIdx) { continue; }
                            $choices[$arr] = $arr;
                        }
                        if (!empty($choices)) { $form['_miss_conn_choices'] = $choices; }
                    }
                }
            }

            // Multi-ticket upload handling (lightweight)
            try { $allUploaded = $this->request->getUploadedFiles(); } catch (\Throwable $e) { $allUploaded = []; }
            $multiFiles = [];
            if (is_array($allUploaded) && isset($allUploaded['multi_ticket_upload']) && is_array($allUploaded['multi_ticket_upload'])) { $multiFiles = $allUploaded['multi_ticket_upload']; }
            else {
                $raw = $this->request->getData('multi_ticket_upload');
                if (is_array($raw)) { $multiFiles = $raw; }
            }
            if (!empty($multiFiles)) {
                if (!isset($meta['_multi_tickets']) || !is_array($meta['_multi_tickets'])) { $meta['_multi_tickets'] = []; }
                foreach ($multiFiles as $mf) {
                    $name2 = null; $safe2 = null; $dest2 = null; $moved = false;
                    if ($mf instanceof \Psr\Http\Message\UploadedFileInterface) {
                        if ($mf->getError() !== UPLOAD_ERR_OK) { continue; }
                        $name2 = (string)($mf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                        $safe2 = preg_replace('/[^A-Za-z0-9._-]/', '_', $name2) ?: ('ticket_' . bin2hex(random_bytes(4)));
                        $destDir2 = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads'; if (!is_dir($destDir2)) { @mkdir($destDir2, 0775, true); }
                        $dest2 = $destDir2 . DIRECTORY_SEPARATOR . $safe2;
                        try { $mf->moveTo($dest2); $moved = true; } catch (\Throwable $e) { $moved = false; }
                    } elseif (is_array($mf) && (($mf['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
                        $tmp2 = (string)($mf['tmp_name'] ?? '');
                        $name2 = (string)($mf['name'] ?? ('ticket_' . bin2hex(random_bytes(4))));
                        $safe2 = preg_replace('/[^A-Za-z0-9._-]/', '_', $name2) ?: ('ticket_' . bin2hex(random_bytes(4)));
                        $destDir2 = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads'; if (!is_dir($destDir2)) { @mkdir($destDir2, 0775, true); }
                        $dest2 = $destDir2 . DIRECTORY_SEPARATOR . $safe2;
                        if ($tmp2 !== '' && @move_uploaded_file($tmp2, $dest2)) { $moved = true; }
                    }
                    if (!$moved || !$dest2) { continue; }
                    // Quick summary per ticket
                    $text2 = '';
                    $ext2 = strtolower((string)pathinfo($dest2, PATHINFO_EXTENSION));
                    try {
                        if ($ext2 === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                            $parser = new \Smalot\PdfParser\Parser(); $pdf = $parser->parseFile($dest2); $text2 = $pdf->getText() ?? '';
                            if (trim((string)$text2) === '') { $pdftotext = (function_exists('env')?env('PDFTOTEXT_PATH'):getenv('PDFTOTEXT_PATH')) ?: 'pdftotext'; $out = @shell_exec(escapeshellarg($pdftotext) . ' -layout ' . escapeshellarg($dest2) . ' - 2>&1'); if (is_string($out) && trim($out) !== '') { $text2 = (string)$out; } }
                        }
                        elseif (in_array($ext2, ['txt','text'], true)) { $text2 = (string)@file_get_contents($dest2); }
                        else {
                            // Image: quick Tesseract pass
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') { $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'; $tess = is_file($winDefault) ? $winDefault : 'tesseract'; }
                            $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                            $out = @shell_exec(escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest2) . ' stdout -l ' . escapeshellarg((string)$langs) . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') { $text2 = (string)$out; }
                        }
                    } catch (\Throwable $e) { $text2 = ''; }
                    $text2 = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$text2) ?? (string)$text2;
                    $summary = [ 'file' => $safe2, 'pnr' => null, 'dep_date' => null, 'passengers' => [], 'segments' => [] ];
                    if ($text2 !== '') {
                        $tp2 = new \App\Service\TicketParseService();
                        $summary['segments'] = $tp2->parseSegmentsFromText($text2);
                        $summary['passengers'] = $tp2->extractPassengerData($text2);
                        $ids2 = $tp2->extractIdentifiers($text2); $summary['pnr'] = (string)($ids2['pnr'] ?? '');
                        $dates2 = $tp2->extractDates($text2); $summary['dep_date'] = (string)($dates2[0] ?? '');
                    }
                    // Skip if this filename already exists in the list
                    $exists = false; foreach ((array)$meta['_multi_tickets'] as $ex) { if ((string)($ex['file'] ?? '') === (string)$safe2) { $exists = true; break; } }
                    if (!$exists) { $meta['_multi_tickets'][] = $summary; }
                }
            }

            // Write changes back to session; if explicit continue request, go next
            $session->write('flow.journey', $journey);
            $session->write('flow.meta', $meta);
            $session->write('flow.compute', $compute);
            $session->write('flow.form', $form);

            if ($this->truthy($this->request->getData('continue'))) {
                return $this->redirect(['action' => 'journey']);
            }
        }

    // Services (recompute after any upload/edits)
        // Fallbacks to improve scope inference when extractor missed stations
        try {
            if (empty($journey['segments']) && !empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
                $journey['segments'] = (array)$meta['_segments_auto'];
            }
            if (empty($meta['_auto']['dep_station']['value'] ?? '') && !empty($form['dep_station'])) {
                $meta['_auto']['dep_station'] = ['value' => (string)$form['dep_station'], 'source' => 'form'];
            }
            if (empty($meta['_auto']['arr_station']['value'] ?? '') && !empty($form['arr_station'])) {
                $meta['_auto']['arr_station'] = ['value' => (string)$form['arr_station'], 'source' => 'form'];
            }
        } catch (\Throwable $e) { /* ignore */ }
        // Infer scope from stations before computing profile
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
            if (!empty($inferRes['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $inferRes['logs']); }
            // Populate distance for SE <150 km gating before building profile
            (new \App\Service\DistanceEstimator())->populateJourneyDistance($journey, $meta, $form);
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: scope infer failed: ' . $e->getMessage();
        }
        // If still no country on any segment, fall back to operator_country to unlock exemption matrix
        try {
            $hasCountry = false; foreach ((array)($journey['segments'] ?? []) as $s) { if (!empty($s['country'])) { $hasCountry = true; break; } }
            $opC = (string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''));
            if (!$hasCountry && $opC !== '') {
                $journey['country']['value'] = $opC;
                if (!empty($journey['segments']) && is_array($journey['segments'])) {
                    $journey['segments'][0]['country'] = $opC;
                }
                $meta['logs'][] = 'Fallback: set country from operator_country=' . $opC;
            }
        } catch (\Throwable $e) { /* ignore */ }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Derive AUTO Art.12 hooks (2,3,5,6,7,8,13) without overriding user inputs
        try {
            $auto12 = (new \App\Service\Art12AutoDeriver())->apply($journey, $meta);
            $meta = $auto12['meta'];
            if (!empty($auto12['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $auto12['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: Art12AutoDeriver failed: ' . $e->getMessage();
        }
    $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta);

        // Evaluate Art. 9 unconditionally so ask_hooks and banners are available even if the
        // explicit opt-in toggle isn't set. The UI can still choose what to show.
        try {
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art9 = null;
            $meta['logs'][] = 'WARN: Art9Evaluator failed: ' . $e->getMessage();
        }
        // Compute PMR issue presence for downstream UI/rules (simple rule set)
        try {
            $pmrU = strtolower((string)($meta['pmr_user'] ?? ($meta['_auto']['pmr_user']['value'] ?? 'unknown')));
            $pmrB = strtolower((string)($meta['pmr_booked'] ?? ($meta['_auto']['pmr_booked']['value'] ?? 'unknown')));
            $pmrMissing = strtolower((string)($meta['pmr_promised_missing'] ?? 'unknown'));
            $pmrDelivered = strtolower((string)($meta['pmr_delivered_status'] ?? 'unknown'));
            $compute['pmr_issue_present'] = (
                $pmrU === 'ja' || $pmrU === 'yes'
            ) && (
                $pmrB === 'ja' || $pmrB === 'yes' || $pmrMissing === 'yes' || !in_array($pmrDelivered, ['ja','yes','yes_full'], true)
            );
        } catch (\Throwable $e) { /* ignore */ }
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);

        // If der ikke er nogen filer tilbage, ryd visningsfelter og grupper
        $hasTickets = !empty($form['_ticketFilename']) || !empty($meta['_multi_tickets']);
        if (!$hasTickets) {
            foreach ([
                'operator','operator_country','operator_product',
                'dep_date','dep_time','dep_station','arr_station','arr_time',
                'train_no','ticket_no','price',
                'actual_arrival_date','actual_dep_time','actual_arr_time',
                'missed_connection_station'
            ] as $rk) { unset($form[$rk]); }
            unset($meta['_auto'], $meta['_segments_auto'], $meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode'], $meta['_multi_tickets']);
            unset($meta['extraction_provider'], $meta['extraction_confidence']);
            unset($journey['bookingRef']);
        }

        // Build grouped tickets from current + multi for display
        $currSummary = [
            'file' => (string)($form['_ticketFilename'] ?? ''),
            'pnr' => (string)($journey['bookingRef'] ?? ''),
            'dep_date' => (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')),
            'passengers' => (array)($meta['_passengers_auto'] ?? []),
            'segments' => (array)($meta['_segments_auto'] ?? []),
        ];
        $allForGrouping = [];
        if (!empty($currSummary['pnr']) || !empty($currSummary['dep_date'])) { $allForGrouping[] = $currSummary; }
        foreach ((array)($meta['_multi_tickets'] ?? []) as $mt) {
            $allForGrouping[] = [
                'file' => (string)($mt['file'] ?? ''),
                'pnr' => (string)($mt['pnr'] ?? ''),
                'dep_date' => (string)($mt['dep_date'] ?? ''),
                'passengers' => (array)($mt['passengers'] ?? []),
                'segments' => (array)($mt['segments'] ?? []),
            ];
        }
        // Deduplicate entries with same file/pnr/dep_date to avoid dobbelte grupper
        if (!empty($allForGrouping)) {
            $seen = [];
            $uniq = [];
            foreach ($allForGrouping as $g) {
                $key = ($g['file'] ?? '') . '|' . ($g['pnr'] ?? '') . '|' . ($g['dep_date'] ?? '');
                if (isset($seen[$key])) { continue; }
                $seen[$key] = true;
                $uniq[] = $g;
            }
            $allForGrouping = $uniq;
        }
        // Dedupliker på filnavn: behold den med mest data (PNR/dep_date/segments)
        if (!empty($allForGrouping)) {
            $byFile = [];
            foreach ($allForGrouping as $g) {
                $f = (string)($g['file'] ?? '');
                if ($f === '') { $byFile[] = $g; continue; }
                $score = 0;
                if (!empty($g['pnr'])) { $score += 2; }
                if (!empty($g['dep_date'])) { $score += 2; }
                if (!empty($g['segments'])) { $score += 1; }
                if (!isset($byFile[$f]) || $score > ($byFile[$f]['_score'] ?? -1)) {
                    $g['_score'] = $score;
                    $byFile[$f] = $g;
                }
            }
            $allForGrouping = [];
            foreach ($byFile as $k => $v) {
                if (isset($v['_score'])) { unset($v['_score']); }
                $allForGrouping[] = $v;
            }
        }
        $groupedTickets = [];
        if (!empty($allForGrouping)) { $groupedTickets = (new \App\Service\TicketJoinService())->groupTickets($allForGrouping); }

        // Build simple contract options from grouped tickets (PNR + dato + operatør/produkt)
        $contractOptions = [];
        if (!empty($groupedTickets)) {
            foreach ((array)$groupedTickets as $idx => $grp) {
                $key = 'GROUP:' . (string)$idx;
                $pnr = (string)($grp['pnr'] ?? '');
                if ($pnr !== '') { $key = 'PNR:' . $pnr; }
                $depDate = (string)($grp['dep_date'] ?? '');
                $segs = (array)($grp['segments'] ?? []);
                $firstSeg = $segs[0] ?? [];
                $ops = [];
                foreach ($segs as $s) {
                    $op = trim((string)($s['operator'] ?? ''));
                    if ($op !== '' && !in_array($op, $ops, true)) { $ops[] = $op; }
                }
                $labelParts = [];
                if ($pnr !== '') { $labelParts[] = 'PNR ' . $pnr; }
                if ($depDate !== '') { $labelParts[] = $depDate; }
                if (!empty($ops)) { $labelParts[] = implode('/', $ops); }
                $prod = (string)($firstSeg['product'] ?? '');
                if ($prod !== '') { $labelParts[] = $prod; }
                $label = !empty($labelParts) ? implode(' · ', $labelParts) : $key;
                $contractOptions[$key] = [
                    'key' => $key,
                    'label' => $label,
                    'segments' => $segs,
                ];
            }
        }
        $meta['contract_options'] = $contractOptions;

        // Art. 12 hint: shared PNR scope when there is exactly one unique PNR across all uploaded tickets
        try {
            $pnrSet = [];
            $br = (string)($journey['bookingRef'] ?? ''); if ($br !== '') { $pnrSet[$br] = true; }
            foreach ((array)$groupedTickets as $grp) { $p = (string)($grp['pnr'] ?? ''); if ($p !== '') { $pnrSet[$p] = true; } }
            if (!empty($pnrSet)) { $meta['shared_pnr_scope'] = (count($pnrSet) === 1) ? 'yes' : 'no'; }
        } catch (\Throwable $e) { /* ignore */ }

        // Multi-ticket hooks for downstream automation/UX
        try {
            $countTickets = 0;
            if (!empty($form['_ticketFilename'])) { $countTickets++; }
            $countTickets += count((array)($meta['_multi_tickets'] ?? []));
            $meta['ticket_upload_count'] = (string)$countTickets;
            // Determine if any ticket has multiple passengers
            $multiPax = false;
            if (count((array)($meta['_passengers_auto'] ?? [])) > 1) { $multiPax = true; }
            foreach ((array)($meta['_multi_tickets'] ?? []) as $mt) { if (count((array)($mt['passengers'] ?? [])) > 1) { $multiPax = true; break; } }
            $meta['ticket_multi_passenger'] = $multiPax ? 'yes' : 'no';
        } catch (\Throwable $e) { /* ignore */ }

        // Art. 12 simple model: through vs separate + problem contract selection
        if ($this->request->is('post')) {
            $cm = (string)($this->request->getData('contract_model') ?? '');
            if (!in_array($cm, ['through','separate'], true)) { $cm = ''; }
            if ($cm !== '') { $form['contract_model'] = $cm; }
            $prob = (string)($this->request->getData('problem_contract_id') ?? '');
            if ($prob !== '') { $form['problem_contract_id'] = $prob; }
        }
        // Apply segment filtering if separate contract is chosen and exists
        if ((string)($form['contract_model'] ?? '') === 'separate' && !empty($form['problem_contract_id'])) {
            $pcid = (string)$form['problem_contract_id'];
            $opts = (array)($meta['contract_options'] ?? []);
            if (isset($opts[$pcid]) && !empty($opts[$pcid]['segments'])) {
                // Backup original segments once
                if (empty($meta['_segments_all'])) {
                    $meta['_segments_all'] = $meta['_segments_auto'] ?? [];
                }
                $meta['_segments_auto'] = (array)$opts[$pcid]['segments'];
                $journey['segments'] = (array)$opts[$pcid]['segments'];
            }
        } else {
            // Restore original segments if previously filtered
            if (!empty($meta['_segments_all'])) {
                $meta['_segments_auto'] = (array)$meta['_segments_all'];
                $journey['segments'] = (array)$meta['_segments_all'];
            }
        }

        // Art. 12 TRIN 2/3.1/4/5 flow (slim): compute next-hook guidance for TRIN 3 hooks panel AFTER ticket counts and PNR scope are set
        try {
            $metaFlow = $meta;
            $norm = function($v){
                $vv = strtolower((string)$v);
                if (in_array($vv, ['ja','yes','y','1','true'], true)) return 'yes';
                if (in_array($vv, ['nej','no','n','0','false'], true)) return 'no';
                if ($vv === '' || $vv === '-' || $vv === 'unknown' || $vv === 'ved ikke') return 'unknown';
                return $vv;
            };
            if (isset($metaFlow['separate_contract_notice'])) { $metaFlow['separate_contract_notice'] = $norm($metaFlow['separate_contract_notice']); }
            if (isset($metaFlow['through_ticket_disclosure'])) { $metaFlow['through_ticket_disclosure_given'] = $norm($metaFlow['through_ticket_disclosure']); }
            if (isset($metaFlow['same_transaction_all'])) { $metaFlow['same_transaction_all'] = $norm($metaFlow['same_transaction_all']); }
            if (isset($metaFlow['shared_pnr_scope'])) { $metaFlow['shared_pnr_scope'] = $norm($metaFlow['shared_pnr_scope']); }
            if (isset($metaFlow['ticket_upload_count'])) { $metaFlow['ticket_upload_count'] = (string)$metaFlow['ticket_upload_count']; }
            $art12flow = (new \App\Service\Art12FlowEvaluator())->decide(['meta' => $metaFlow, 'journey' => $journey]);
    } catch (\Throwable $e) { $art12flow = ['stage' => 'TRIN_2', 'hooks_to_collect' => [], 'notes' => ['flow-eval failed']]; }

        // Build missed-connection station choices from detected segments (not gated by 'incident.missed')
        try {
            $mcChoices = [];
            $addChoice = function(string $s) use (&$mcChoices) { $s = trim($s); if ($s !== '' && !isset($mcChoices[$s])) { $mcChoices[$s] = $s; } };
            // Primary auto segments
            foreach ((array)($meta['_segments_auto'] ?? []) as $idx => $seg) {
                $to = (string)($seg['to'] ?? ''); $last = $idx === (count((array)$meta['_segments_auto']) - 1);
                if (!$last) { $addChoice($to); }
            }
            // Also scan grouped tickets
            foreach ((array)$groupedTickets as $grp) {
                foreach ((array)($grp['segments'] ?? []) as $i => $seg) {
                    $to = (string)($seg['to'] ?? ''); $last = $i === (count((array)$grp['segments']) - 1);
                    if (!$last) { $addChoice($to); }
                }
            }
            // Fallback heuristic: if OCR text declares a higher skift count than segments imply (e.g. "Skift: 2" but only 2 segments → missing final leg), include last segment destination as an interchange candidate
            try {
                $ocrTextFull = (string)($meta['_ocr_text'] ?? '');
                if ($ocrTextFull !== '' && preg_match('/\bSkift\s*:\s*(\d{1,2})/iu', $ocrTextFull, $mmSk)) {
                    $declaredChanges = (int)$mmSk[1];
                    $segCount = count((array)($meta['_segments_auto'] ?? []));
                    // Expected legs = declaredChanges + 1; if we have fewer, we likely missed the final leg → add last 'to' as candidate
                    if ($declaredChanges >= 1 && $segCount > 0 && $segCount < ($declaredChanges + 1)) {
                        $lastSeg = (array)end($meta['_segments_auto']);
                        $lastTo = (string)($lastSeg['to'] ?? '');
                        if ($lastTo !== '' && !isset($mcChoices[$lastTo])) { $mcChoices[$lastTo] = $lastTo; }
                        $meta['logs'][] = 'Heuristic: added last destination "' . $lastTo . '" as skift candidate (declared Skift:' . $declaredChanges . ', segments:' . $segCount . ')';
                    }
                }
            } catch (\Throwable $e) { /* ignore heuristic errors */ }
            if (!empty($mcChoices)) {
                $form['_miss_conn_choices'] = $mcChoices;
                if (empty($form['missed_connection_station'])) {
                    // Preselect the first candidate to provide immediate feedback
                    $keys = array_keys($mcChoices);
                    if (!empty($keys)) { $form['missed_connection_station'] = (string)$keys[0]; }
                }
            }
            // Evaluate MCT realism for all interchanges using station-specific thresholds
            try {
                $segsForMct = (array)($meta['_segments_auto'] ?? []);
                if (!empty($segsForMct)) {
                    $mct = new \App\Service\MctChecker();
                    $eval = $mct->evaluate($segsForMct);
                    if (!empty($eval)) {
                        $meta['_mct_eval'] = $eval;
                        $hookVal = $mct->inferHook($eval);
                        if ($hookVal !== 'unknown') {
                            $meta['_auto']['mct_realistic'] = ['value' => $hookVal, 'source' => 'mct_rules'];
                            if (empty($meta['mct_realistic'])) { $meta['mct_realistic'] = $hookVal; }
                            foreach ($eval as $e) {
                                $meta['logs'][] = 'MCT: ' . (string)$e['station'] . ' margin=' . (string)($e['margin'] ?? '') . ' thr=' . (string)($e['threshold'] ?? '') . ' realistic=' . (((bool)($e['realistic'] ?? false)) ? 'Ja' : 'Nej');
                            }
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
            // Auto-derive Art. 9(1) mct_realistic if missed-connection is selected and times are available
            try {
                $missed = !empty($incident['missed']);
                $station = (string)($form['missed_connection_station'] ?? '');
                $segs = (array)($meta['_segments_auto'] ?? []);
                if ($missed && $station !== '' && !empty($segs)) {
                    $idxFound = null;
                    foreach ($segs as $i => $s) { if (isset($s['to']) && (string)$s['to'] === $station) { $idxFound = $i; break; } }
                    if ($idxFound !== null && isset($segs[$idxFound]['schedArr']) && isset($segs[$idxFound+1]['schedDep'])) {
                        $arr = (string)$segs[$idxFound]['schedArr'];
                        $dep = (string)$segs[$idxFound+1]['schedDep'];
                        $toMin = function(string $t){ if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) return null; $h=(int)$m[1]; $mi=(int)$m[2]; return $h*60+$mi; };
                        $a = $toMin($arr); $d = $toMin($dep);
                        if ($a !== null && $d !== null) {
                            $diff = $d - $a; if ($diff < 0) { $diff += 24*60; }
                            $val = null;
                            if ($diff >= 10) { $val = 'Ja'; }
                            elseif ($diff > 0 && $diff <= 4) { $val = 'Nej'; }
                            if ($val !== null) {
                                $meta['_auto']['mct_realistic'] = ['value' => $val, 'source' => 'mct_heuristic'];
                                if (empty($meta['mct_realistic'])) { $meta['mct_realistic'] = $val; }
                                $meta['logs'][] = 'AUTO: mct_realistic=' . $val . ' (layover ' . (string)$diff . ' min at ' . $station . ')';
                            }
                        }
                    }
                }
            } catch (\Throwable $e) { /* ignore mct auto errors */ }
        } catch (\Throwable $e) { /* ignore choice build errors */ }

        // EU-only suggestion (read-only, does not modify compute.euOnly)
        [$euOnlySuggested, $euOnlyReason] = $this->suggestEuOnly($journey, $meta);

        // Recommend claim form (EU vs national) comparable to one()
        try {
            $countryCtx = (string)($journey['country']['value'] ?? ($form['operator_country'] ?? ''));
            $scopeCtx = (string)($profile['scope'] ?? '');
            $opCtx = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
            $prodCtx = (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''));
            // Use explicit delay minutes if set; do not use legacy UI confirmation anymore
            $delayCtx = (int)($compute['delayMinEU'] ?? 0);
            if ($delayCtx <= 0) {
                // Treat explicit cancellation context as meeting override tier intents
                $mainIncident = (string)($incident['main'] ?? '');
                if ($delayCtx <= 0 && $mainIncident === 'cancellation') { $delayCtx = 120; }
            }
            $selector = new \App\Service\ClaimFormSelector();
            $formDecision = $selector->select([
                'country' => $countryCtx,
                'scope' => $scopeCtx,
                'operator' => $opCtx,
                'product' => $prodCtx,
                'delayMin' => $delayCtx,
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            $formDecision = ['form' => 'eu_standard_claim', 'reason' => 'EU baseline (fallback)', 'notes' => ['Selector error: ' . $e->getMessage()]];
        }

        // Persist any form updates (e.g., auto preselect)
        $session->write('flow.form', $form);

        // AJAX partial: render only hooks panel for live updates in PRG
        if ($isAjaxHooks && $this->request->is('ajax')) {
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('hooks_panel');
            $this->set(compact('journey','meta','compute','form','incident','profile','art12','art12flow','art9','refund','refusion','euOnlySuggested','euOnlyReason','groupedTickets','formDecision'));
            return $this->render();
        }

        // Per-contract computation (Art. 12(5) – separate contracts): derive view model when Art. 12 does not apply (no through-ticket)
        $contractsView = [];
        try {
            $isThrough = isset($art12['art12_applies']) ? (bool)$art12['art12_applies'] : null;
            if ($isThrough === false) {
                // Normalize segments from grouped tickets or auto segments into a common schema
                $normSegs = [];
                $op = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
                // Prefer grouped tickets (contains PNR + per-ticket segments)
                $groups = (array)($groupedTickets ?? []);
                if (!empty($groups)) {
                    foreach ($groups as $g) {
                        $pnr = (string)($g['pnr'] ?? '');
                        $ticketId = (string)($g['file'] ?? '');
                        foreach ((array)($g['segments'] ?? []) as $s) {
                            $depD = (string)($s['depDate'] ?? ($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')));
                            $arrD = (string)($s['arrDate'] ?? $depD);
                            $depT = (string)($s['schedDep'] ?? '');
                            $arrT = (string)($s['schedArr'] ?? '');
                            $depPl = ($depD && $depT) ? ($depD . 'T' . str_replace(' ', '', $depT)) : '';
                            $arrPl = ($arrD && $arrT) ? ($arrD . 'T' . str_replace(' ', '', $arrT)) : '';
                            $normSegs[] = [
                                'ticketId' => $ticketId ?: null,
                                'pnr' => $pnr ?: null,
                                'operator' => $op ?: null,
                                'depPlanned' => $depPl ?: null,
                                'arrPlanned' => $arrPl ?: null,
                                'arrActual' => null, // not captured in TRIN 3
                                'currency' => null,
                                'ticketTotal' => null,
                                'priceShare' => null,
                            ];
                        }
                    }
                } else {
                    // Fallback: use auto segments (no per-ticket grouping)
                    foreach ((array)($meta['_segments_auto'] ?? []) as $s) {
                        $depD = (string)($s['depDate'] ?? ($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')));
                        $arrD = (string)($s['arrDate'] ?? $depD);
                        $depT = (string)($s['schedDep'] ?? '');
                        $arrT = (string)($s['schedArr'] ?? '');
                        $depPl = ($depD && $depT) ? ($depD . 'T' . str_replace(' ', '', $depT)) : '';
                        $arrPl = ($arrD && $arrT) ? ($arrD . 'T' . str_replace(' ', '', $arrT)) : '';
                        $normSegs[] = [
                            'ticketId' => null,
                            'pnr' => (string)($journey['bookingRef'] ?? ''),
                            'operator' => $op ?: null,
                            'depPlanned' => $depPl ?: null,
                            'arrPlanned' => $arrPl ?: null,
                            'arrActual' => null,
                            'currency' => null,
                            'ticketTotal' => null,
                            'priceShare' => null,
                        ];
                    }
                }
                if (!empty($normSegs)) {
                    $flowNorm = ['journey' => ['segments' => $normSegs]];
                    $splitter = new \App\Service\PerContractSplitter();
                    $delayCalc = new \App\Service\PerContractDelayCalculator();
                    $compCalc = new \App\Service\PerContractCompensation();
                    $contracts = $splitter->split($flowNorm);
                    foreach ($contracts as $c) {
                        $ticketValue = $c['ticketTotal'] ?? null; // unknown in TRIN 3
                        if ($ticketValue === null) {
                            $sum = 0.0; $seen = false;
                            foreach ((array)($c['segments'] ?? []) as $s) {
                                if (isset($s['priceShare'])) { $sum += (float)$s['priceShare']; $seen = true; }
                            }
                            $ticketValue = $seen ? $sum : null;
                        }
                        $currency = $c['currency'] ?? null;
                        $d = $delayCalc->endToEndDelay($c);
                        $cmp = $compCalc->compute($ticketValue, $d['delayMinutes'], $currency);
                        $contractsView[] = [
                            'contractKey'   => (string)$c['contractKey'],
                            'pnr'           => $c['pnr'] ?? null,
                            'ticketId'      => $c['ticketId'] ?? null,
                            'operators'     => implode(', ', (array)($c['operatorSet'] ?? [])),
                            'plannedArrival'=> $d['plannedArrival'],
                            'actualArrival' => $d['actualArrival'],
                            'delayMinutes'  => $d['delayMinutes'],
                            'delayStatus'   => $d['status'],
                            'ticketValue'   => $ticketValue,
                            'currency'      => $currency,
                            'compBand'      => $cmp['band'],
                            'compPercent'   => $cmp['percent'],
                            'compAmount'    => $cmp['amount'],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) { /* silent per-contract errors in TRIN 3 */ }

        $this->set(compact('journey','meta','compute','form','incident','profile','art12','art12flow','art9','refund','refusion','euOnlySuggested','euOnlyReason','groupedTickets','formDecision','contractsView'));
        $this->set('isAdmin', $isAdmin);
        return null;
    }

    public function summary(): void
    {
        $journey = (array)$this->request->getSession()->read('flow.journey') ?: [];
        $meta = (array)$this->request->getSession()->read('flow.meta') ?: [];
        $compute = (array)$this->request->getSession()->read('flow.compute') ?: [];
        $form = (array)$this->request->getSession()->read('flow.form') ?: [];
        $flags = (array)$this->request->getSession()->read('flow.flags') ?: [];
        $incident = (array)$this->request->getSession()->read('flow.incident') ?: [];

                $currency = 'EUR';
                $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
                // Normalize local symbols to ISO codes before regex ISO extraction
                $symMap = [
                    'KČ' => 'CZK','Kč' => 'CZK','ZŁ' => 'PLN','zł' => 'PLN','FT' => 'HUF','Ft' => 'HUF','LEI' => 'RON','лв' => 'BGN'
                ];
                foreach ($symMap as $sym => $iso) { if (stripos($priceRaw, $sym) !== false && !preg_match('/\b'.$iso.'\b/i', $priceRaw)) { $priceRaw .= ' ' . $iso; break; } }
                if (preg_match('/\b(BGN|CZK|DKK|HUF|PLN|RON|SEK|EUR)\b/i', $priceRaw, $mm)) { $currency = strtoupper($mm[1]); }
                // Ambiguous 'kr' handling: map using operator country if present
                if ($currency === 'EUR' && preg_match('/\bkr\b/i', $priceRaw)) {
                        $opCountry = strtoupper((string)($journey['country']['value'] ?? ($meta['operator_country'] ?? '')));
                        if (in_array($opCountry, ['DK','SE'], true)) { $currency = $opCountry === 'DK' ? 'DKK' : 'SEK'; }
                }
                // Fallback by operator country for non-euro members if still EUR without explicit code
                if ($currency === 'EUR') {
                        $opCountry = strtoupper((string)($journey['country']['value'] ?? ($meta['operator_country'] ?? '')));
                        $nonEuro = ['BG'=>'BGN','CZ'=>'CZK','DK'=>'DKK','HU'=>'HUF','PL'=>'PLN','RO'=>'RON','SE'=>'SEK'];
                        if (isset($nonEuro[$opCountry])) { $currency = $nonEuro[$opCountry]; }
                }

        // Derive Art.19(10) extraordinary from TRIN 6 toggle if set; OR with earlier compute flag
        $extraordinary = (bool)($compute['extraordinary'] ?? false);
        try {
            $exc = strtolower((string)($form['operatorExceptionalCircumstances'] ?? ''));
            if (in_array($exc, ['yes','ja','1','true'], true)) { $extraordinary = true; }
        } catch (\Throwable $e) { /* ignore */ }

        // Build trip/legs for Art. 19(3) price basis
    try { $art12Eval = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art12Eval = null; }
    $through = (bool)($art12Eval['art12_applies'] ?? true);
        $segSrc = (array)($meta['_segments_auto'] ?? ($journey['segments'] ?? []));
        $legs = [];
        $euSet = ['AT'=>1,'BE'=>1,'BG'=>1,'HR'=>1,'CY'=>1,'CZ'=>1,'DK'=>1,'EE'=>1,'FI'=>1,'FR'=>1,'DE'=>1,'GR'=>1,'HU'=>1,'IE'=>1,'IT'=>1,'LV'=>1,'LT'=>1,'LU'=>1,'MT'=>1,'NL'=>1,'PL'=>1,'PT'=>1,'RO'=>1,'SK'=>1,'SI'=>1,'ES'=>1,'SE'=>1];
        $journeyCountry = strtoupper((string)($journey['country']['value'] ?? ''));
        foreach ($segSrc as $s) {
            $depDate = (string)($s['depDate'] ?? '');
            $arrDate = (string)($s['arrDate'] ?? $depDate);
            $arrTime = (string)($s['schedArr'] ?? '');
            $scheduledArr = '';
            if ($arrDate !== '' && $arrTime !== '') { $scheduledArr = $arrDate . 'T' . str_replace(' ', '', $arrTime); }
            $legs[] = [
                'operator' => (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')),
                'product' => (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')),
                'country' => $journeyCountry !== '' ? $journeyCountry : (string)($s['country'] ?? ''),
                'scheduled_arr' => $scheduledArr,
                'actual_arr' => null,
                'eu' => $journeyCountry !== '' ? isset($euSet[$journeyCountry]) : null,
            ];
        }
        $delayedLegIndex = max(0, count($legs) > 0 ? (count($legs) - 1) : 0);
        // Derive liable party for Art.12(3)/(4) vs defaults
        $liablePartySumm = 'operator';
        if (!empty($art12Eval) && ($art12Eval['art12_applies'] ?? false) === true) {
            $lp = (string)($art12Eval['liable_party'] ?? 'unknown');
            if ($lp === 'agency') { $liablePartySumm = 'retailer'; }
            elseif ($lp === 'operator') { $liablePartySumm = 'operator'; }
        } else {
            $liablePartySumm = 'operator';
        }
        $excType = (string)($form['operatorExceptionalType'] ?? '');
        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => (float)preg_replace('/[^0-9.]/', '', (string)($journey['ticketPrice']['value'] ?? 0)),
            'trip' => [ 'through_ticket' => $through, 'trip_type' => (count($legs)===2?'return':null), 'legs' => $legs, 'liable_party' => $liablePartySumm ],
            'disruption' => [
                'delay_minutes_final' => (int)($compute['delayMinEU'] ?? 0),
                'eu_only' => (bool)($compute['euOnly'] ?? true),
                'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                'extraordinary' => $extraordinary,
                'extraordinary_type' => $excType,
                'self_inflicted' => false,
                'delayed_leg_index' => $delayedLegIndex,
                'missed_connection' => !empty($incident['missed']),
            ],
            'choices' => [ 'wants_refund' => false, 'wants_reroute_same_soonest' => false, 'wants_reroute_later_choice' => false ],
            'expenses' => [ 'meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0 ],
            'already_refunded' => 0,
        ]);

        if ((string)($form['remedyChoice'] ?? '') === 'refund_return') {
            $compAmt = (float)($claim['breakdown']['compensation']['amount'] ?? 0);
            $claim['breakdown']['compensation']['amount'] = 0.0;
            $claim['breakdown']['compensation']['pct'] = 0;
            $claim['breakdown']['compensation']['basis'] = 'Art. 19 udelukket pga. refund';
            // Show refund of billetpris (Art. 18(1)(1)) in breakdown and totals
            $ticketPriceAmount = (float)preg_replace('/[^0-9.]/', '', (string)($journey['ticketPrice']['value'] ?? 0));
            $claim['breakdown']['refund']['amount'] = $ticketPriceAmount;
            $claim['breakdown']['refund']['basis'] = 'Art. 18(1)(1)';
            $existingGross = (float)($claim['totals']['gross_claim'] ?? 0);
            $claim['totals']['gross_claim'] = max($existingGross - $compAmt, $ticketPriceAmount);
        }


        // Map incidents to reimbursement reason fields
        $reason_delay = !empty($incident['main']) && $incident['main'] === 'delay';
        $reason_cancellation = !empty($incident['main']) && $incident['main'] === 'cancellation';
        $reason_missed_conn = !empty($incident['missed']);

        // Additional info aggregation (include travel state)
        $additional_info_parts = [];
        if (!empty($flags['travel_state'])) {
            $label = [
                'completed' => 'Rejsen er afsluttet',
                'ongoing' => 'Rejsen er påbegyndt (i tog / skift)',
                'before_start' => 'Jeg skal til at påbegynde rejsen',
            ][$flags['travel_state']] ?? $flags['travel_state'];
            $additional_info_parts[] = 'TRIN 1: ' . $label;
        }
        if (!empty($incident)) {
            $sel = [];
            if ($reason_delay) { $sel[] = 'Delay'; }
            if ($reason_cancellation) { $sel[] = 'Cancellation'; }
            if ($reason_missed_conn) { $sel[] = 'Missed connection'; }
            if (!empty($sel)) { $additional_info_parts[] = 'TRIN 2: ' . implode(', ', $sel); }
        }
        $additional_info = implode(' | ', $additional_info_parts);

        $this->set(compact('journey','meta','compute','claim','flags','incident','reason_delay','reason_cancellation','reason_missed_conn','additional_info'));
    }

    /**
     * Single-page flow (all steps on one page), as per flow_chart_v_1_live_client_service.pdf
     */
    public function one(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        // When called via AJAX to refresh hooks panel, we'll short-circuit and render only the element
        $isAjaxHooks = (bool)$this->request->getQuery('ajax_hooks');
        // Previously: a plain GET cleared all session state. This caused data loss when adding
        // query params like allow_official=1. Now we only reset on explicit request (?reset=1).
        if ($this->request->is('get')) {
            $justUploaded = (bool)$session->read('flow.justUploaded');
            $doReset = $this->truthy($this->request->getQuery('reset'));
            if ($justUploaded) {
                // Preserve once after PRG, then drop the flag
                $session->write('flow.justUploaded', false);
            } elseif ($doReset) {
                // Hard reset only when user explicitly asks for it
                $session->delete('flow.form');
                $session->delete('flow.flags');
                $session->delete('flow.incident');
                // Also clear TRIN 4 fields and meta['_auto'] to prevent stale data
                $form = [];
                $meta = [];
                $session->write('flow.form', $form);
                $session->write('flow.meta', $meta);
            }
        }
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: ['euOnly' => true];
        $flags = (array)$session->read('flow.flags') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
    $form = (array)$session->read('flow.form') ?: [];

        if ($this->request->is('post')) {
            // Early handle: remove a ticket from the grouped list (TRIN 4 UI "Fjern")
            $removeFile = (string)($this->request->getData('remove_ticket') ?? '');
            if ($removeFile !== '') {
                // If removing the current primary ticket
                $currentFile = (string)($form['_ticketFilename'] ?? '');
                if ($currentFile !== '' && $removeFile === $currentFile) {
                    // Clear TRIN 4 current upload and auto fields
                    unset($form['_ticketUploaded'], $form['_ticketFilename'], $form['_ticketOriginalName']);
                    // Clear TRIN 4 form fields
                    foreach ([
                        'operator','operator_country','operator_product',
                        'dep_date','dep_time','dep_station','arr_station','arr_time',
                        'train_no','ticket_no','price',
                        'actual_arrival_date','actual_dep_time','actual_arr_time',
                        'missed_connection_station'
                    ] as $rk) { unset($form[$rk]); }
                    // Clear meta auto caches
                    unset($meta['_auto'], $meta['_segments_auto'], $meta['_passengers_auto'], $meta['_identifiers'], $meta['_barcode']);
                    unset($meta['extraction_provider'], $meta['extraction_confidence']);
                    $meta['logs'][] = 'Removed primary ticket: ' . $removeFile;
                } else {
                    // Remove from multi tickets list if present
                    if (!empty($meta['_multi_tickets']) && is_array($meta['_multi_tickets'])) {
                        $meta['_multi_tickets'] = array_values(array_filter((array)$meta['_multi_tickets'], function($t) use ($removeFile){
                            return (string)($t['file'] ?? '') !== $removeFile;
                        }));
                        $meta['logs'][] = 'Removed extra ticket: ' . $removeFile;
                    }
                }
                $session->write('flow.meta', $meta);
                $session->write('flow.form', $form);
                // PRG back to TRIN 4; if AJAX hooks refresh, render fragment
                if ($isAjaxHooks && $this->request->is('ajax')) {
                    // Fall through to recompute and render hooks element below
                } else {
                    return $this->redirect(['action' => 'one', '#' => 's4']);
                }
            }
            $didUpload = false;
            // TRIN 1
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            // OCR/JSON input (optional)
            $text = (string)($this->request->getData('ocr_text') ?? '');
            if ($text !== '') {
                // Normalize spaces to improve downstream matching
                $text = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $text) ?? $text;
                $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                    new \App\Service\TicketExtraction\HeuristicsExtractor(),
                    new \App\Service\TicketExtraction\LlmExtractor(),
                ], 0.66);
                $er = $broker->run($text);
                $meta['extraction_provider'] = $er->provider;
                $meta['extraction_confidence'] = $er->confidence;
                $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                // Convert to _auto shape for UI and refill TRIN 3.2 inputs
                $auto = [];
                foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                $meta['_auto'] = $auto;
                foreach ([
                    'dep_date','dep_time','dep_station','arr_station','arr_time',
                    'train_no','ticket_no','price'
                ] as $rk) {
                    $form[$rk] = isset($meta['_auto'][$rk]['value']) ? (string)$meta['_auto'][$rk]['value'] : ($form[$rk] ?? '');
                }
                if (!empty($meta['_auto']['operator']['value'])) { $form['operator'] = (string)$meta['_auto']['operator']['value']; }
                if (!empty($meta['_auto']['operator_country']['value'])) { $form['operator_country'] = (string)$meta['_auto']['operator_country']['value']; }
                if (!empty($meta['_auto']['operator_product']['value'])) { $form['operator_product'] = (string)$meta['_auto']['operator_product']['value']; }
            }
            $jjson = (string)($this->request->getData('journey_json') ?? '');
            if (trim($jjson) !== '') {
                $maybe = json_decode($jjson, true);
                if (is_array($maybe)) { $journey = $maybe; }
            }

            // TRIN 2
            $main = (string)($this->request->getData('incident_main') ?? '');
            if (in_array($main, ['delay','cancellation',''], true)) { $incident['main'] = $main; }
            $incident['missed'] = $this->truthy($this->request->getData('missed_connection'));

            // Delay and flags
            $compute['euOnly'] = (bool)$this->request->getData('eu_only');
            $compute['delayMinEU'] = (int)($this->request->getData('delay_min_eu') ?? 0);
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $compute['extraordinary'] = (bool)$this->request->getData('extraordinary');
            $compute['art9OptIn'] = (bool)$this->request->getData('art9_opt_in');

            // Seller type inference from purchaseChannel (fallback if OCR didn't catch it)
            $purchaseChannel = (string)($this->request->getData('purchaseChannel') ?? ($form['purchaseChannel'] ?? ''));
            if (!empty($purchaseChannel)) {
                if (in_array($purchaseChannel, ['station','onboard'], true)) { $journey['seller_type'] = $journey['seller_type'] ?? 'operator'; }
                elseif ($purchaseChannel === 'web_app') { $journey['seller_type'] = $journey['seller_type'] ?? 'agency'; }
            }

            // Handle ticket upload (TRIN 4) to auto-extract journey fields
            $saved = false; $attemptedUpload = false; $dest = null; $name = null; $safe = null;
            $uf = $this->request->getUploadedFile('ticket_upload');
            if ($uf instanceof \Psr\Http\Message\UploadedFileInterface) {
                $attemptedUpload = true;
            }
            if ($uf instanceof \Psr\Http\Message\UploadedFileInterface && $uf->getError() === UPLOAD_ERR_OK) {
                $name = (string)($uf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                try { $uf->moveTo($dest); $saved = true; } catch (\Throwable $e) { $saved = false; $meta['logs'][] = 'Upload move failed: ' . $e->getMessage(); }
            } elseif ($uf instanceof \Psr\Http\Message\UploadedFileInterface && $uf->getError() !== UPLOAD_ERR_OK) {
                $meta['logs'][] = 'Upload error code: ' . (string)$uf->getError();
            } else {
                $af = $this->request->getData('ticket_upload');
                if ($af && is_array($af) && ($af['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $attemptedUpload = true;
                    $tmp = $af['tmp_name'];
                    $name = (string)($af['name'] ?? ('ticket_' . bin2hex(random_bytes(4))));
                    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: ('ticket_' . bin2hex(random_bytes(4)));
                    $destDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
                    $dest = $destDir . DIRECTORY_SEPARATOR . $safe;
                    if (@move_uploaded_file($tmp, $dest)) { $saved = true; }
                    else { $meta['logs'][] = 'Upload move failed (legacy array path).'; }
                } elseif ($af && is_array($af)) {
                    $attemptedUpload = true;
                    $meta['logs'][] = 'Upload error code: ' . (string)($af['error'] ?? UPLOAD_ERR_NO_FILE);
                }
            }

            // Always reset TRIN 4 fields and meta['_auto'] when a new ticket is uploaded
            if ($saved && $dest) {
                $form['_ticketUploaded'] = '1';
                $form['_ticketFilename'] = $safe;
                $form['_ticketOriginalName'] = $name;
                $didUpload = true;
                $meta['logs'][] = 'Upload saved: ' . (string)$safe;
                // Reset TRIN 4 fields so a new upload doesn't keep stale values
                $resetKeys = [
                    'operator','operator_country','operator_product',
                    'dep_date','dep_time','dep_station','arr_station','arr_time',
                    'train_no','ticket_no','price',
                    'actual_arrival_date','actual_dep_time','actual_arr_time',
                    'missed_connection_station'
                ];
                foreach ($resetKeys as $rk) { unset($form[$rk]); }
                // Also reset meta['_auto'] completely
                $meta['_auto'] = [];
                // Reset scope flags on journey to avoid leaking previous classification
                unset($journey['is_international_beyond_eu'], $journey['is_international_inside_eu'], $journey['is_long_domestic']);
                // Reset extraction diagnostics so each upload starts clean
                unset($meta['extraction_provider'], $meta['extraction_confidence']);
                if (!isset($meta['logs']) || !is_array($meta['logs'])) { $meta['logs'] = []; }
                $meta['logs'] = [];
                $meta['logs'][] = 'RESET: cleared operator/product and 3.2 fields before OCR';

                // Extract text depending on file type, including image-fixture fallback
                $textBlob = '';
                $ext = strtolower((string)pathinfo($dest, PATHINFO_EXTENSION));
                try {
                    if ($ext === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($dest);
                        $textBlob = $pdf->getText() ?? '';
                        $meta['logs'][] = 'OCR: PDF parsed via smalot/pdfparser (' . strlen((string)$textBlob) . ' chars)';
                        // Fallback to pdftotext when PDF has little/no text
                        if (trim((string)$textBlob) === '') {
                            $pdftotext = function_exists('env') ? env('PDFTOTEXT_PATH') : getenv('PDFTOTEXT_PATH');
                            $pdftotext = $pdftotext ?: 'pdftotext';
                            $cmd = escapeshellarg((string)$pdftotext) . ' -layout ' . escapeshellarg((string)$dest) . ' -';
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') {
                                $textBlob = (string)$out;
                                $meta['logs'][] = 'OCR: PDF parsed via pdftotext (-layout)';
                            } else {
                                $meta['logs'][] = 'OCR: pdftotext not available or returned empty';
                            }
                        }
                    } elseif (in_array($ext, ['txt','text'], true)) {
                        $textBlob = (string)@file_get_contents($dest);
                        $meta['logs'][] = 'OCR: TXT read (' . strlen((string)$textBlob) . ' chars)';
                    } elseif (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                        $meta['logs'][] = 'OCR: image detected (' . strtoupper($ext) . ')';
                        // Try to find a mock TXT with same basename under mocks/tests/fixtures
                        $base = (string)pathinfo($safe, PATHINFO_FILENAME);
                        $mockDir = ROOT . DIRECTORY_SEPARATOR . 'mocks' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR;
                        $cands = [
                            $mockDir . $base . '.txt',
                            $mockDir . strtolower($base) . '.txt',
                            $mockDir . str_replace('-', '_', strtolower($base)) . '.txt',
                        ];
                        foreach ($cands as $cand) {
                            if (is_file($cand)) { 
                                $textBlob = (string)@file_get_contents($cand); 
                                $meta['logs'][] = 'OCR: using mock fixture ' . basename($cand) . ' (' . strlen((string)$textBlob) . ' chars)';
                                break; 
                            }
                        }
                        $visionTried = false;
                        $visionFirst = false;
                        try {
                            $vf = function_exists('env') ? env('LLM_VISION_PRIORITY') : getenv('LLM_VISION_PRIORITY');
                            $visionFirst = strtolower((string)($vf ?? '')) === 'first' || (function_exists('env') ? (env('LLM_VISION_FIRST') === '1') : (getenv('LLM_VISION_FIRST') === '1'));
                        } catch (\Throwable $e) { $visionFirst = false; }
                        $meta['logs'][] = 'Vision-first: ' . ($visionFirst ? 'enabled' : 'disabled');

                        // Optional: try Vision first when configured
                        if ($textBlob === '' && $visionFirst) {
                            try {
                                $vision = new \App\Service\Ocr\LlmVisionOcr();
                                [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                                foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                                $visionTried = true;
                                if (is_string($vText) && trim($vText) !== '') {
                                    $textBlob = (string)$vText;
                                    $meta['logs'][] = 'OCR: Vision-first provided text';
                                } else {
                                    $meta['logs'][] = 'OCR: Vision-first returned no text, falling back to Tesseract';
                                }
                            } catch (\Throwable $e) {
                                $meta['logs'][] = 'OCR Vision-first exception: ' . $e->getMessage();
                                $visionTried = true;
                            }
                        }

                        // If still empty, try local Tesseract OCR if available
                        if ($textBlob === '') {
                            // Pre-scale small images to improve OCR quality (Tesseract needs ~300 DPI)
                            $scalePath = $dest;
                            try {
                                $info = @getimagesize($dest);
                                if (is_array($info)) {
                                    $w = (int)($info[0] ?? 0); $h = (int)($info[1] ?? 0);
                                    if ($w > 0 && $h > 0 && $w < 1400) {
                                        $factor = 1.8;
                                        $nw = (int)round($w * $factor); $nh = (int)round($h * $factor);
                                        $img = null;
                                        switch ($ext) {
                                            case 'jpg': case 'jpeg': $img = @imagecreatefromjpeg($dest); break;
                                            case 'png': $img = @imagecreatefrompng($dest); break;
                                            case 'webp': if (function_exists('imagecreatefromwebp')) { $img = @imagecreatefromwebp($dest); } break;
                                            case 'bmp': if (function_exists('imagecreatefrombmp')) { $img = @imagecreatefrombmp($dest); } break;
                                            default: $img = null; break;
                                        }
                                        if ($img) {
                                            $dst = @imagecreatetruecolor($nw, $nh);
                                            if ($dst) {
                                                @imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                                                $tmpPath = (sys_get_temp_dir() ?: dirname($dest)) . DIRECTORY_SEPARATOR . ('ocr_up_' . bin2hex(random_bytes(4)) . '.png');
                                                if (@imagepng($dst, $tmpPath, 9)) {
                                                    $scalePath = $tmpPath;
                                                    $meta['logs'][] = 'OCR: upscaled image ' . $w . 'x' . $h . ' -> ' . $nw . 'x' . $nh;
                                                }
                                                @imagedestroy($dst);
                                            }
                                            @imagedestroy($img);
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                                // ignore scaling errors
                            }
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            // Determine language packs: env override wins; else infer from filename hints
                            $langs = function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS');
                            if (!$langs || trim((string)$langs) === '') {
                                $fn = strtolower((string)$safe);
                                $langs = 'eng';
                                $hint = 'eng';
                                if (preg_match('/(sncf|ouigo|ter|tgv|fr|french|eurostar)/', $fn)) { $langs = 'eng+fra'; $hint = 'eng+fra'; }
                                elseif (preg_match('/(db|bahn|ice|ic\b|re\b|de|german)/', $fn)) { $langs = 'eng+deu'; $hint = 'eng+deu'; }
                                elseif (preg_match('/(trenitalia|fs\b|it|ital)/', $fn)) { $langs = 'eng+ita'; $hint = 'eng+ita'; }
                                elseif (preg_match('/(renfe|es|span)/', $fn)) { $langs = 'eng+spa'; $hint = 'eng+spa'; }
                                elseif (preg_match('/(dsb|dk|dan)/', $fn)) { $langs = 'eng+dan'; $hint = 'eng+dan'; }
                                elseif (preg_match('/(sj\b|se|swed)/', $fn)) { $langs = 'eng+swe'; $hint = 'eng+swe'; }
                                elseif (preg_match('/(ns\b|nl|dutch)/', $fn)) { $langs = 'eng+nld'; $hint = 'eng+nld'; }
                                elseif (preg_match('/(sncb|nmbs|be|belg)/', $fn)) { $langs = 'eng+nld+fra'; $hint = 'eng+nld+fra'; }
                                elseif (preg_match('/(sbb|cff|ffs|ch|sui)/', $fn)) { $langs = 'eng+deu+fra+ita'; $hint = 'eng+deu+fra+ita'; }
                                elseif (preg_match('/(obb|at\b|aust)/', $fn)) { $langs = 'eng+deu'; $hint = 'eng+deu'; }
                                elseif (preg_match('/(pkp|pl\b|pol)/', $fn)) { $langs = 'eng+pol'; $hint = 'eng+pol'; }
                                elseif (preg_match('/(cd\b|cz\b|czech)/', $fn)) { $langs = 'eng+ces'; $hint = 'eng+ces'; }
                                elseif (preg_match('/(mav|hu\b|hung)/', $fn)) { $langs = 'eng+hun'; $hint = 'eng+hun'; }
                                $meta['logs'][] = 'OCR: inferring Tesseract langs from filename -> ' . $hint;
                            }
                            $tessOpts = (function_exists('env') ? env('TESSERACT_OPTS') : getenv('TESSERACT_OPTS')) ?: '--psm 6';
                            $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$scalePath) . ' stdout -l ' . escapeshellarg((string)$langs) . ' ' . (string)$tessOpts;
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') {
                                $textBlob = (string)$out;
                                $meta['logs'][] = 'OCR: used Tesseract (' . (string)$langs . ' ' . (string)$tessOpts . '), ' . strlen((string)$textBlob) . ' chars';
                            } else {
                                $meta['logs'][] = 'OCR: Tesseract not available or returned empty (cmd failed): ' . $cmd;
                            }
                        }
                        // If Tesseract returned very little text, try a secondary language combo commonly useful in EU tickets
                        if ($textBlob !== '' && strlen((string)trim((string)$textBlob)) < 40) {
                            $retryLangs = 'eng+fra';
                            if (preg_match('/(trenitalia|fs\b|it|ital)/', strtolower((string)$safe))) { $retryLangs = 'eng+ita'; }
                            $meta['logs'][] = 'OCR: very low text yield; retrying with ' . $retryLangs;
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            $cmd2 = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest) . ' stdout -l ' . escapeshellarg($retryLangs);
                            $out2 = @shell_exec($cmd2 . ' 2>&1');
                            if (is_string($out2) && trim($out2) !== '') {
                                $textBlob2 = (string)$out2;
                                if (strlen($textBlob2) > strlen((string)$textBlob)) {
                                    $textBlob = $textBlob2;
                                    $meta['logs'][] = 'OCR: retry improved to ' . strlen((string)$textBlob) . ' chars';
                                } else {
                                    $meta['logs'][] = 'OCR: retry did not improve yield';
                                }
                            } else {
                                $meta['logs'][] = 'OCR: retry failed';
                            }
                        }
                        // If still empty, escalate to LLM Vision OCR (Groq/OpenAI-compatible) when enabled (only if not already tried)
                        if ($textBlob === '' && !$visionTried) {
                            try {
                                $vision = new \App\Service\Ocr\LlmVisionOcr();
                                [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                                foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                                if (is_string($vText) && trim($vText) !== '') { $textBlob = (string)$vText; }
                            } catch (\Throwable $e) {
                                $meta['logs'][] = 'OCR Vision exception: ' . $e->getMessage();
                            }
                        }
                    }
                } catch (\Throwable $e) { $textBlob = ''; }
                if ($textBlob === '') { $meta['logs'][] = 'OCR: No text extracted from upload'; }

                if ($textBlob !== '') {
                    // Normalize spaces to improve downstream matching
                    $textBlob = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', $textBlob) ?? $textBlob;
                    $broker = new \App\Service\TicketExtraction\ExtractorBroker([
                        new \App\Service\TicketExtraction\HeuristicsExtractor(),
                        new \App\Service\TicketExtraction\LlmExtractor(),
                    ], 0.66);
                    $er = $broker->run($textBlob);
                    $meta['extraction_provider'] = $er->provider;
                    $meta['extraction_confidence'] = $er->confidence;
                    $meta['logs'] = array_merge($meta['logs'] ?? [], $er->logs);
                    // Convert to _auto shape for UI
                    $auto = [];
                    foreach ($er->fields as $k => $v) { if ($v !== null && $v !== '') { $auto[$k] = ['value' => $v, 'source' => $er->provider]; } }
                    $meta['_auto'] = $auto;
                    // PMR/handicap auto-detection from ticket text (Art. 9/20 hooks)
                    try {
                        $pmrSvc = new \App\Service\PmrDetectionService();
                        $pmr = $pmrSvc->analyze([
                            'rawText' => $textBlob,
                            'seller' => (string)($form['operator'] ?? ''),
                            'fields' => [],
                        ]);
                        $meta['_pmr_detection'] = [
                            'evidence' => (array)($pmr['evidence'] ?? []),
                            'confidence' => (float)($pmr['confidence'] ?? 0.0),
                            'discount_type' => $pmr['discount_type'] ?? null,
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($pmr['pmr_user'])) { $meta['_auto']['pmr_user'] = ['value' => 'Ja', 'source' => 'pmr_detection']; }
                        if (!empty($pmr['pmr_booked'])) { $meta['_auto']['pmr_booked'] = ['value' => 'Ja', 'source' => 'pmr_detection']; }
                        if (!empty($pmr['pmr_user']) && empty($meta['pmr_user'])) { $meta['pmr_user'] = 'Ja'; }
                        if (!empty($pmr['pmr_booked']) && empty($meta['pmr_booked'])) { $meta['pmr_booked'] = 'Ja'; }
                        // Server-side auto-default (single-page): if no evidence and low confidence, default to 'Nej'
                        $pmrConf = (float)($pmr['confidence'] ?? 0.0);
                        $hasEvidence = !empty($pmr['pmr_user']) || !empty($pmr['pmr_booked']) || (!empty($meta['_pmr_detection']['evidence']));
                        if (!$hasEvidence && $pmrConf < 0.30) {
                            $cur = strtolower((string)($meta['pmr_user'] ?? ''));
                            if ($cur === '' || $cur === 'unknown') {
                                $meta['pmr_user'] = 'Nej';
                                $meta['_auto']['pmr_user'] = ['value' => 'Nej', 'source' => 'pmr_detection'];
                                $meta['logs'][] = 'AUTO: pmr_user=Nej (no PMR signals; conf=' . number_format($pmrConf, 2) . ')';
                            }
                            $curB = strtolower((string)($meta['pmr_booked'] ?? ''));
                            if ($curB === '' || $curB === 'unknown') {
                                $meta['pmr_booked'] = 'Nej';
                                $meta['_auto']['pmr_booked'] = ['value' => 'Nej', 'source' => 'pmr_detection'];
                            }
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: PMR detection failed: ' . $e->getMessage(); }
                    // Bike auto-detection (single-page flow): if bicycle detected, preselect TRIN 9 bike interest
                    try {
                        $bikeSvc = new \App\Service\BikeDetectionService();
                        $bike = $bikeSvc->analyze([
                            'rawText' => $textBlob,
                            'seller' => (string)($form['operator'] ?? ''),
                            'barcodeText' => (string)($meta['_barcode_payload'] ?? ''),
                        ]);
                        $meta['_bike_detection'] = [
                            'evidence' => (array)($bike['evidence'] ?? []),
                            'confidence' => (float)($bike['confidence'] ?? 0.0),
                            'count' => $bike['bike_count'] ?? null,
                            'operator_hint' => $bike['operator_hint'] ?? null,
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($bike['bike_booked'])) { $meta['_auto']['bike_booked'] = ['value' => 'Ja', 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_count'])) { $meta['_auto']['bike_count'] = ['value' => (string)$bike['bike_count'], 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_booked'])) { $form['interest_bike'] = $form['interest_bike'] ?? '1'; }
                        if (!empty($bike['bike_booked']) && empty($meta['bike_booked'])) { $meta['bike_booked'] = 'Ja'; }
                        if (empty($bike['bike_booked']) && empty($meta['bike_booked'])) {
                            // Simplified UX: preselect Nej when no bike signals at all (user can override to Ja)
                            $meta['bike_booked'] = 'Nej';
                            $meta['logs'][] = 'AUTO: bike_booked=Nej (no bike signals detected)';
                        }
                        if (!empty($bike['bike_count']) && empty($meta['bike_count'])) { $meta['bike_count'] = (string)$bike['bike_count']; }
                        if (!empty($bike['bike_res_required'])) { $meta['_auto']['bike_res_required'] = ['value' => (string)$bike['bike_res_required'], 'source' => 'bike_detection']; }
                        if (!empty($bike['bike_reservation_type'])) { $meta['_auto']['bike_reservation_type'] = ['value' => (string)$bike['bike_reservation_type'], 'source' => 'bike_detection']; }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Bike detection failed: ' . $e->getMessage(); }
                    // Ticket type detection (single-page): prefill AUTO and, when clear, pre-select fare/scope
                    try {
                        $ttd = (new \App\Service\TicketTypeDetectionService())->analyze([
                            'rawText' => $textBlob,
                            'fareName' => (string)($meta['_auto']['fareName']['value'] ?? ''),
                            'fareCode' => (string)($meta['_auto']['fareCode']['value'] ?? ''),
                            'productName' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                            'seatLine' => '',
                            'validityLine' => '',
                            'reservationLine' => '',
                        ]);
                        $meta['_ticket_type_detection'] = [
                            'evidence' => (array)($ttd['evidence'] ?? []),
                            'confidence' => (float)($ttd['confidence'] ?? 0.0),
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        if (!empty($ttd['fare_flex_type']) && $ttd['fare_flex_type'] !== 'other') {
                            $meta['_auto']['fare_flex_type'] = ['value' => (string)$ttd['fare_flex_type'], 'source' => 'ticket_type_detection'];
                            if (empty($meta['fare_flex_type'])) { $meta['fare_flex_type'] = (string)$ttd['fare_flex_type']; }
                        } elseif (!isset($meta['_auto']['fare_flex_type'])) {
                            $meta['_auto']['fare_flex_type'] = ['value' => 'other', 'source' => 'ticket_type_detection'];
                        }
                        if (!empty($ttd['train_specificity']) && $ttd['train_specificity'] !== 'unknown') {
                            $meta['_auto']['train_specificity'] = ['value' => (string)$ttd['train_specificity'], 'source' => 'ticket_type_detection'];
                            if (empty($meta['train_specificity'])) { $meta['train_specificity'] = (string)$ttd['train_specificity']; }
                        } elseif (!isset($meta['_auto']['train_specificity'])) {
                            $meta['_auto']['train_specificity'] = ['value' => 'unknown', 'source' => 'ticket_type_detection'];
                        }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Ticket type detection failed: ' . $e->getMessage(); }
                    // Class/reservation detection (single-page): prefill AUTO and open TRIN 9 pkt.6 when detected
                    try {
                        $crd = (new \App\Service\ClassReservationDetectionService())->analyze([
                            'rawText' => $textBlob,
                            'fields' => [
                                'fareName' => (string)($meta['_auto']['fareName']['value'] ?? ''),
                                'productName' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                                'reservationLine' => (string)($meta['_auto']['reservationLine']['value'] ?? ''),
                                'seatLine' => (string)($meta['_auto']['seatLine']['value'] ?? ''),
                                'coachSeatBlock' => (string)($meta['_auto']['coachSeatBlock']['value'] ?? ''),
                            ]
                        ]);
                        $meta['_class_detection'] = [
                            'evidence' => (array)($crd['evidence'] ?? []),
                            'confidence' => (float)($crd['confidence'] ?? 0.0),
                        ];
                        if (!isset($meta['_auto']) || !is_array($meta['_auto'])) { $meta['_auto'] = []; }
                        $opened = false;
                        if (!empty($crd['fare_class_purchased']) && $crd['fare_class_purchased'] !== 'unknown') {
                            $meta['_auto']['fare_class_purchased'] = ['value' => (string)$crd['fare_class_purchased'], 'source' => 'class_det'];
                            if (empty($meta['fare_class_purchased'])) { $meta['fare_class_purchased'] = (string)$crd['fare_class_purchased']; }
                            $opened = true;
                        }
                        if (!empty($crd['berth_seat_type']) && $crd['berth_seat_type'] !== 'unknown') {
                            $meta['_auto']['berth_seat_type'] = ['value' => (string)$crd['berth_seat_type'], 'source' => 'class_det'];
                            if (empty($meta['berth_seat_type'])) { $meta['berth_seat_type'] = (string)$crd['berth_seat_type']; }
                            $opened = true;
                        }
                        if ($opened) { $form['interest_class'] = $form['interest_class'] ?? '1'; }
                    } catch (\Throwable $e) { $meta['logs'][] = 'WARN: Class/reservation detection failed: ' . $e->getMessage(); }
                    // New: parse multi-leg segments from OCR text to assist missed-connection selection
                    try {
                        $tp = new \App\Service\TicketParseService();
                        $segAuto = $tp->parseSegmentsFromText($textBlob);
                        if (!empty($segAuto)) {
                            $meta['_segments_auto'] = $segAuto;
                            // Prefill journey segments minimally if empty
                            if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = []; }
                            foreach ($segAuto as $s) {
                                $journey['segments'][] = [
                                    'from' => (string)($s['from'] ?? ''),
                                    'to' => (string)($s['to'] ?? ''),
                                    'schedDep' => (string)($s['schedDep'] ?? ''),
                                    'schedArr' => (string)($s['schedArr'] ?? ''),
                                    'depDate' => (string)($s['depDate'] ?? ''),
                                    'arrDate' => (string)($s['arrDate'] ?? ''),
                                ];
                            }
                            $meta['logs'][] = 'AUTO: detected ' . count($segAuto) . ' segment(s) from ticket text.';
                        }
                        // Auto-derive Art. 9(1) mct_realistic if missed-connection is selected and times are available (single-page flow)
                        try {
                            $missed = !empty($incident['missed']);
                            $station = (string)($form['missed_connection_station'] ?? '');
                            $segs = (array)($meta['_segments_auto'] ?? []);
                            if ($missed && $station !== '' && !empty($segs)) {
                                $idxFound = null;
                                foreach ($segs as $i => $s) { if (isset($s['to']) && (string)$s['to'] === $station) { $idxFound = $i; break; } }
                                if ($idxFound !== null && isset($segs[$idxFound]['schedArr']) && isset($segs[$idxFound+1]['schedDep'])) {
                                    $arr = (string)$segs[$idxFound]['schedArr'];
                                    $dep = (string)$segs[$idxFound+1]['schedDep'];
                                    $toMin = function(string $t){ if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) return null; $h=(int)$m[1]; $mi=(int)$m[2]; return $h*60+$mi; };
                                    $a = $toMin($arr); $d = $toMin($dep);
                                    if ($a !== null && $d !== null) {
                                        $diff = $d - $a; if ($diff < 0) { $diff += 24*60; }
                                        $val = null;
                                        if ($diff >= 10) { $val = 'Ja'; }
                                        elseif ($diff > 0 && $diff <= 4) { $val = 'Nej'; }
                                        if ($val !== null) {
                                            $meta['_auto']['mct_realistic'] = ['value' => $val, 'source' => 'mct_heuristic'];
                                            if (empty($meta['mct_realistic'])) { $meta['mct_realistic'] = $val; }
                                            $meta['logs'][] = 'AUTO: mct_realistic=' . $val . ' (layover ' . (string)$diff . ' min at ' . $station . ')';
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) { /* ignore mct auto errors */ }
                        // Parse identifiers (PNR/order) from OCR text and optional barcode when image
                        $ids = $tp->extractIdentifiers($textBlob);
                        if (in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                            $bc = $tp->parseBarcode($dest);
                            if (!empty($bc['payload'])) {
                                $meta['_barcode'] = [ 'format' => $bc['format'], 'chars' => strlen((string)$bc['payload']) ];
                                $idsBC = $tp->extractIdentifiers((string)$bc['payload']);
                                $ids = array_merge($ids, $idsBC);
                            }
                        }
                        if (!empty($ids)) {
                            $meta['_identifiers'] = $ids;
                            if (!empty($ids['pnr'])) { $journey['bookingRef'] = (string)$ids['pnr']; $meta['logs'][] = 'AUTO: bookingRef from PNR'; }
                            if (empty($form['ticket_no']) && !empty($ids['order_no'])) { $form['ticket_no'] = (string)$ids['order_no']; $meta['logs'][] = 'AUTO: ticket_no from order_no'; }
                        }
                        // Extract passenger snapshot (adults/children and names) for group tickets
                        $passengerData = $tp->extractPassengerData($textBlob);
                        if (!empty($passengerData)) {
                            $meta['_passengers_auto'] = $passengerData;
                            $journey['passengerCount'] = count($passengerData);
                            $journey['passengers'] = $passengerData;
                            $journey['isGroupTicket'] = true;
                        }
                        // If dep_date missing, infer from dates found in text
                        $datesFound = $tp->extractDates($textBlob);
                        if (!empty($datesFound)) {
                            $firstDate = (string)$datesFound[0];
                            if (empty($form['dep_date'])) { $form['dep_date'] = $firstDate; $meta['logs'][] = 'AUTO: dep_date inferred from text dates'; }
                            if (empty($meta['_auto']['dep_date']['value'])) { $meta['_auto']['dep_date'] = ['value' => $firstDate, 'source' => 'parser:dates']; }
                        }
                        // Attempt soft link to existing journey by PNR + dep_date
                        try {
                            $pnr = isset($ids['pnr']) ? (string)$ids['pnr'] : ((string)($journey['bookingRef'] ?? ''));
                            $journeyDate = (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? ''));
                            if ($pnr !== '' && $journeyDate !== '') {
                                $tjoin = new \App\Service\TicketJoinService();
                                $hint = $tjoin->tryLinkToExistingJourney($pnr, $journeyDate, $passengerData ?? []);
                                if (!empty($hint['matched'])) { $meta['logs'][] = 'JOIN: candidate link by PNR+date'; }
                            }
                        } catch (\Throwable $e) { /* ignore link errors */ }
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'WARN: segment/id parsing failed: ' . $e->getMessage();
                    }
                    // Propagate country hint from extraction into journey for exemptions profile
                    try {
                        $opCountry = isset($auto['operator_country']['value']) ? (string)$auto['operator_country']['value'] : '';
                        if ($opCountry !== '') {
                            if (empty($journey['segments']) || !is_array($journey['segments'])) { $journey['segments'] = [[]]; }
                            $journey['segments'][0]['country'] = $opCountry;
                            $journey['country']['value'] = $opCountry;
                            $meta['logs'][] = 'AUTO: journey country set from operator_country=' . $opCountry;
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                    // If extraction confidence is low or core fields missing, try Vision OCR re-OCR to improve text
                    $coreKeys = ['dep_station','arr_station','dep_date','dep_time','arr_time','train_no'];
                    $haveCore = true; foreach ($coreKeys as $ck) { if (empty($auto[$ck]['value'] ?? '')) { $haveCore = false; break; } }
                    if (($er->confidence < 0.5 || !$haveCore) && in_array($ext, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true)) {
                        try {
                            $vision = new \App\Service\Ocr\LlmVisionOcr();
                            [$vText, $vLogs] = $vision->extractTextFromImage($dest);
                            foreach ((array)$vLogs as $lg) { $meta['logs'][] = $lg; }
                            if (is_string($vText) && trim($vText) !== '') {
                                $textBlobVision = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$vText) ?? (string)$vText;
                                $er2 = $broker->run($textBlobVision);
                                $meta['logs'] = array_merge($meta['logs'] ?? [], $er2->logs);
                                // Decide if vision-based extraction is better (higher confidence or more core fields)
                                $auto2 = [];
                                foreach ($er2->fields as $k => $v) { if ($v !== null && $v !== '') { $auto2[$k] = ['value' => $v, 'source' => $er2->provider]; } }
                                $haveCore2 = true; foreach ($coreKeys as $ck) { if (empty($auto2[$ck]['value'] ?? '')) { $haveCore2 = false; break; } }
                                $better = ($er2->confidence > $er->confidence) || ($haveCore2 && !$haveCore);
                                if ($better) {
                                    $er = $er2; $auto = $auto2; $meta['_auto'] = $auto;
                                    $meta['extraction_provider'] = $er2->provider;
                                    $meta['extraction_confidence'] = $er2->confidence;
                                    $meta['logs'][] = 'AUTO: using Vision OCR text for extraction';
                                }
                            }
                        } catch (\Throwable $e) {
                            $meta['logs'][] = 'OCR Vision retry failed: ' . $e->getMessage();
                        }
                    }
                    // Refill TRIN 3.2 fields from AUTO, clearing when missing
                    foreach ([
                        'dep_date','dep_time','dep_station','arr_station','arr_time',
                        'train_no','ticket_no','price'
                    ] as $rk) {
                        $form[$rk] = isset($meta['_auto'][$rk]['value']) ? (string)$meta['_auto'][$rk]['value'] : '';
                    }
                    // If multiple segments exist, prepare a simple select list for missed-connection station (arrivals of intermediate legs)
                    if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
                        $choices = [];
                        $segs = (array)$meta['_segments_auto'];
                        // Offer all arrival stations except the last segment's arrival (miss occurs at a change point)
                        $lastIdx = count($segs) - 1;
                        foreach ($segs as $idx => $s) {
                            $arr = (string)($s['to'] ?? '');
                            if ($arr === '' || $idx === $lastIdx) { continue; }
                            $label = $arr;
                            $choices[$arr] = $label;
                        }
                        if (!empty($choices)) { $form['_miss_conn_choices'] = $choices; }
                    }
                    // Light-touch: also reflect operator hints when available
                    if (!empty($meta['_auto']['operator']['value'])) { $form['operator'] = (string)$meta['_auto']['operator']['value']; }
                    if (!empty($meta['_auto']['operator_country']['value'])) { $form['operator_country'] = (string)$meta['_auto']['operator_country']['value']; }
                    if (!empty($meta['_auto']['operator_product']['value'])) { $form['operator_product'] = (string)$meta['_auto']['operator_product']['value']; }

                    // Append a normalized record to webroot/data/tickets.ndjson for offline GROQ queries
                    try {
                        $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                        if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                        $record = [
                            '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1(($safe ?? '') . microtime(true)), 0, 6),
                            '_type' => 'ticket',
                            'operator' => (string)($meta['_auto']['operator']['value'] ?? ''),
                            'operator_country' => (string)($meta['_auto']['operator_country']['value'] ?? ''),
                            'operator_product' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
                            'dep_station' => (string)($meta['_auto']['dep_station']['value'] ?? ''),
                            'arr_station' => (string)($meta['_auto']['arr_station']['value'] ?? ''),
                            'dep_date' => (string)($meta['_auto']['dep_date']['value'] ?? ''),
                            'dep_time' => (string)($meta['_auto']['dep_time']['value'] ?? ''),
                            'arr_time' => (string)($meta['_auto']['arr_time']['value'] ?? ''),
                            'train_no' => (string)($meta['_auto']['train_no']['value'] ?? ''),
                            'ticket_no' => (string)($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? '')),
                            'booking_ref' => (string)($journey['bookingRef'] ?? ''),
                            'price' => (string)($meta['_auto']['price']['value'] ?? ''),
                            'segments' => (array)($meta['_segments_auto'] ?? []),
                            'passengers' => (array)($meta['_passengers_auto'] ?? []),
                            'identifiers' => (array)($meta['_identifiers'] ?? []),
                            'extraction_provider' => (string)($meta['extraction_provider'] ?? ''),
                            'extraction_confidence' => (float)($meta['extraction_confidence'] ?? 0.0),
                            'source' => [
                                'format' => $ext,
                                'original_filename' => $name,
                                'saved_filename' => $safe,
                                'ocr_chars' => (int)strlen((string)$textBlob),
                                'barcode' => isset($meta['_barcode']) ? $meta['_barcode'] : null,
                            ],
                            'createdAt' => date('c'),
                        ];
                        // For forward-compat: also wrap in tickets[] for multi-ticket grouping experiments
                        $ticketsWrap = [
                            'tickets' => [[
                                'pnr' => (string)($journey['bookingRef'] ?? ''),
                                'passenger_count' => isset($journey['passengerCount']) ? (int)$journey['passengerCount'] : count((array)($meta['_passengers_auto'] ?? [])),
                                'passengers' => (array)($meta['_passengers_auto'] ?? []),
                            ]],
                        ];
                        $line = json_encode(array_merge($record, $ticketsWrap), JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        @file_put_contents($datasetDir . 'tickets.ndjson', $line, FILE_APPEND | LOCK_EX);
                        $meta['logs'][] = 'Dataset: appended to /data/tickets.ndjson';
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'Dataset append failed: ' . $e->getMessage();
                    }
                } else {
                    // Fallback: derive basics from filename conventions
                    $fname = strtolower((string)pathinfo($safe, PATHINFO_FILENAME));
                    $fname = preg_replace('/[^a-z0-9_\-]+/', '_', $fname);
                    $fname = str_replace(['__','-'], '_', $fname);
                    $country = null; $operator = null; $product = null;
                    if (str_starts_with($fname, 'sncf_')) { $country = 'FR'; $operator = 'SNCF'; }
                    if (str_starts_with($fname, 'db_')) { $country = 'DE'; $operator = 'DB'; }
                    if (str_starts_with($fname, 'dsb_')) { $country = 'DK'; $operator = 'DSB'; }
                    if (str_starts_with($fname, 'sj_')) { $country = 'SE'; $operator = 'SJ'; }
                    if (str_contains($fname, '_tgv_') || str_ends_with($fname, '_tgv')) { $product = 'TGV'; }
                    elseif (str_contains($fname, '_ice_')) { $product = 'ICE'; }
                    elseif (preg_match('/(^|_)ic(_|$)/', $fname)) { $product = 'IC'; }
                    elseif (preg_match('/(^|_)re(_|$)/', $fname)) { $product = 'RE'; }
                    $parts = preg_split('/[_]+/', $fname) ?: [];
                    if (count($parts) >= 2) {
                        $from = ucfirst($parts[count($parts)-2] ?? '');
                        $to = ucfirst($parts[count($parts)-1] ?? '');
                        if ($from && $to) { $form['dep_station'] = str_replace('_', ' ', $from); $form['arr_station'] = str_replace('_', ' ', $to); }
                    }
                    if ($operator) { $form['operator'] = $operator; }
                    if ($country) { $form['operator_country'] = $country; }
                    if ($product) { $form['operator_product'] = $product; }
                    // Dataset entry for no-text case to aid troubleshooting
                    try {
                        $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                        if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                        $record = [
                            '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1(($safe ?? '') . microtime(true)), 0, 6),
                            '_type' => 'ticket',
                            'operator' => (string)($operator ?? ''),
                            'operator_country' => (string)($country ?? ''),
                            'operator_product' => (string)($product ?? ''),
                            'dep_station' => (string)($form['dep_station'] ?? ''),
                            'arr_station' => (string)($form['arr_station'] ?? ''),
                            'extraction_provider' => 'none',
                            'extraction_confidence' => 0.0,
                            'source' => [
                                'format' => $ext,
                                'original_filename' => $name,
                                'saved_filename' => $safe,
                                'ocr_chars' => 0,
                            ],
                            'createdAt' => date('c'),
                            'status' => 'no_ocr_text'
                        ];
                        $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                        @file_put_contents($datasetDir . 'tickets.ndjson', $line, FILE_APPEND | LOCK_EX);
                        $meta['logs'][] = 'Dataset: appended placeholder (no OCR text)';
                    } catch (\Throwable $e) {
                        $meta['logs'][] = 'Dataset append failed: ' . $e->getMessage();
                    }
                }
            }

            // Handle multi-ticket uploads (TRIN 4) to support multiple transactions per journey
            $multiUploaded = 0;
            try { $allUploaded = $this->request->getUploadedFiles(); } catch (\Throwable $e) { $allUploaded = []; }
            $multiFiles = [];
            if (is_array($allUploaded) && isset($allUploaded['multi_ticket_upload']) && is_array($allUploaded['multi_ticket_upload'])) {
                $multiFiles = $allUploaded['multi_ticket_upload'];
            } else {
                $raw = $this->request->getData('multi_ticket_upload');
                if (is_array($raw)) { $multiFiles = $raw; }
            }
            if (!empty($multiFiles)) {
                if (!isset($meta['_multi_tickets']) || !is_array($meta['_multi_tickets'])) { $meta['_multi_tickets'] = []; }
                foreach ($multiFiles as $mf) {
                    if (!($mf instanceof \Psr\Http\Message\UploadedFileInterface)) { continue; }
                    if ($mf->getError() !== UPLOAD_ERR_OK) { continue; }
                    $name2 = (string)($mf->getClientFilename() ?? ('ticket_' . bin2hex(random_bytes(4))));
                    $safe2 = preg_replace('/[^A-Za-z0-9._-]/', '_', $name2) ?: ('ticket_' . bin2hex(random_bytes(4)));
                    // Skip if this is the same as the primary ticket already uploaded
                    if (!empty($form['_ticketFilename']) && (string)$form['_ticketFilename'] === $safe2) { continue; }
                    $destDir2 = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads';
                    if (!is_dir($destDir2)) { @mkdir($destDir2, 0775, true); }
                    $dest2 = $destDir2 . DIRECTORY_SEPARATOR . $safe2;
                    try { $mf->moveTo($dest2); } catch (\Throwable $e) { continue; }
                    $multiUploaded++;
                    $meta['logs'][] = 'Upload saved (multi): ' . (string)$safe2;
                    // Minimal OCR/extraction for each extra ticket
                    $text2 = '';
                    $ext2 = strtolower((string)pathinfo($dest2, PATHINFO_EXTENSION));
                    try {
                        if ($ext2 === 'pdf' && class_exists('Smalot\\PdfParser\\Parser')) {
                            $parser = new \Smalot\PdfParser\Parser();
                            $pdf = $parser->parseFile($dest2);
                            $text2 = $pdf->getText() ?? '';
                        } elseif (in_array($ext2, ['txt','text'], true)) {
                            $text2 = (string)@file_get_contents($dest2);
                        } else {
                            // Image path: try Tesseract with fallback
                            $tess = function_exists('env') ? env('TESSERACT_PATH') : getenv('TESSERACT_PATH');
                            if (!$tess || $tess === '') {
                                $winDefault = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
                                $tess = is_file($winDefault) ? $winDefault : 'tesseract';
                            }
                            $langs = (function_exists('env') ? env('TESSERACT_LANGS') : getenv('TESSERACT_LANGS')) ?: 'eng';
                            $cmd = escapeshellarg((string)$tess) . ' ' . escapeshellarg((string)$dest2) . ' stdout -l ' . escapeshellarg((string)$langs);
                            $out = @shell_exec($cmd . ' 2>&1');
                            if (is_string($out) && trim($out) !== '') { $text2 = (string)$out; }
                        }
                    } catch (\Throwable $e) { $text2 = ''; }
                    $text2 = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{2060}\x{FEFF}]/u', ' ', (string)$text2) ?? (string)$text2;
                    $summary = [ 'file' => $safe2, 'provider' => null, 'pnr' => null, 'dep_date' => null, 'passengers' => [], 'segments' => [] ];
                    if ($text2 !== '') {
                        $broker2 = new \App\Service\TicketExtraction\ExtractorBroker([
                            new \App\Service\TicketExtraction\HeuristicsExtractor(),
                            new \App\Service\TicketExtraction\LlmExtractor(),
                        ], 0.66);
                        $erx = $broker2->run($text2);
                        $summary['provider'] = $erx->provider;
                        $tp2 = new \App\Service\TicketParseService();
                        $ids2 = $tp2->extractIdentifiers($text2);
                        $summary['pnr'] = (string)($ids2['pnr'] ?? '');
                        $segs2 = $tp2->parseSegmentsFromText($text2);
                        $summary['segments'] = $segs2;
                        $pax2 = $tp2->extractPassengerData($text2);
                        $summary['passengers'] = $pax2;
                        $dates2 = $tp2->extractDates($text2);
                        $summary['dep_date'] = (string)($dates2[0] ?? '');
                        // Try to link
                        try {
                            $pnr2 = $summary['pnr'] ?: (string)($journey['bookingRef'] ?? '');
                            $date2 = $summary['dep_date'] ?: (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? ''));
                            if ($pnr2 !== '' && $date2 !== '') {
                                (new \App\Service\TicketJoinService())->tryLinkToExistingJourney($pnr2, $date2, $pax2);
                            }
                        } catch (\Throwable $e) { /* ignore */ }
                        // Append dataset line for this ticket too
                        try {
                            $datasetDir = WWW_ROOT . 'data' . DIRECTORY_SEPARATOR;
                            if (!is_dir($datasetDir)) { @mkdir($datasetDir, 0775, true); }
                            $rec2 = [
                                '_id' => 'ticket_' . date('Ymd_His') . '_' . substr(sha1($safe2 . microtime(true)), 0, 6),
                                '_type' => 'ticket',
                                'dep_date' => $summary['dep_date'],
                                'booking_ref' => $summary['pnr'],
                                'segments' => $segs2,
                                'passengers' => $pax2,
                                'extraction_provider' => (string)$erx->provider,
                                'source' => [ 'format' => $ext2, 'original_filename' => $name2, 'saved_filename' => $safe2 ],
                                'createdAt' => date('c'),
                                'tickets' => [[ 'pnr' => $summary['pnr'], 'passenger_count' => count($pax2), 'passengers' => $pax2 ]],
                            ];
                            @file_put_contents($datasetDir . 'tickets.ndjson', json_encode($rec2, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
                        } catch (\Throwable $e) { /* ignore dataset errors */ }
                    }
                    $meta['_multi_tickets'][] = $summary;
                }
                if ($multiUploaded > 0) { $didUpload = true; $session->write('flow.justUploaded', true); }
            }

        // Removed duplicate upload handling block to prevent stale 3.2 values persisting across uploads

            // Minimal applicant/journey form fields to drive reimbursement PDF filling
            $allowedFormKeys = [
                'name','email','operator','dep_date','dep_station','arr_station','dep_time','arr_time',
                'train_no','ticket_no','price','price_currency','actual_arrival_date','actual_dep_time','actual_arr_time',
                'missed_connection_station',
                // TRIN 4 – non-completed travel confirmation (legacy removed)
                // operator extra
                'operator_country','operator_product',
                // simple expenses capture (optional)
                'expense_meals','expense_hotel','expense_alt_transport','expense_other',
                // TRIN 3 – CIV/liability screening
                'hasValidTicket','safetyMisconduct','forbiddenItemsOrAnimals','customsRulesBreached','operatorStampedDisruptionProof',
                // TRIN 4 – Art. 12 / contracts
                'isThroughTicket','separateContractsDisclosed','singleTxn','sellerType',
                // TRIN 6 – Optional exemption continuation
                'continue_national_rules',
                // TRIN 5 – Remedies (Art. 18)
                'remedyChoice','trip_cancelled_return_to_origin','refund_requested','refund_form_selected',
                'reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min','self_purchased_new_ticket',
                'reroute_extra_costs','reroute_extra_costs_amount','reroute_extra_costs_currency','downgrade_occurred','downgrade_comp_basis',
                // TRIN 6 – Compensation
                'delayAtFinalMinutes','compensationBand','voucherAccepted','operatorExceptionalCircumstances','minThresholdApplies',
                // TRIN 8 - Assistance/expenses
                'meal_offered','assistance_meals_unavailable_reason',
                'hotel_offered','overnight_needed','assistance_hotel_accessible',
                'blocked_train_alt_transport','blocked_on_track','stranded_at_station','stranded_unknown','assistance_blocked_transport_possible','assistance_blocked_transport_to','assistance_blocked_transport_time',
                'alt_transport_provided','assistance_info_provided','assistance_alt_transport_offered_by','assistance_alt_transport_type','assistance_alt_departure_time','assistance_alt_arrival_time','assistance_alt_to_destination',
                'assistance_info_source','assistance_updates_channel','assistance_info_time_first','assistance_info_time_last','assistance_expected_dep','assistance_expected_arr',
                'assistance_confirmation_requested','assistance_confirmation_provided','assistance_confirmation_type','assistance_confirmation_upload',
                'assistance_pmr_priority_applied','assistance_pmr_companion_supported','assistance_pmr_dog_supported',
                'extra_expense_upload','expense_breakdown_meals','expense_breakdown_hotel_nights','expense_breakdown_local_transport','expense_breakdown_other_amounts','expense_breakdown_currency',
                'delay_confirmation_received','delay_confirmation_upload','extraordinary_claimed','extraordinary_type',
                // TRIN 9 – Auto EU request flags
                'request_refund','request_comp_60','request_comp_120','request_expenses',
                // TRIN 9 – Interests (persist UI state)
                'info_requested_pre_purchase',
                'interest_coc','interest_fastest','interest_fares','interest_pmr','interest_bike','interest_class','interest_disruption','interest_facilities','interest_through','interest_complaint',
                // TRIN 9 – Hook answers (persist UI echo)
                'coc_acknowledged','coc_evidence_upload','civ_marking_present',
                'fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                'multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity',
                'pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing',
                'bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket',
                'fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status',
                'preinformed_disruption','preinfo_channel','realtime_info_seen',
                'facilities_delivered_status','facility_impact_note','connection_time_realistic',
                'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
                // TRIN 6 – simplified Art. 12 UI state
                'seller_channel','same_transaction',
                'complaint_channel_seen','complaint_already_filed','complaint_receipt_upload','submit_via_official_channel',
                // Other
                'purchaseChannel',
                // TRIN 10 – PMR
                'pmrUser','assistancePromised','assistanceDelivered',
                // TRIN 11 – GDPR/attachments/info
                'gdprConsent','additionalInfo'
            ];
            foreach ($allowedFormKeys as $k) {
                $v = $this->request->getData($k);
                if ($v === null || $v === '') { continue; }
                // Avoid attempting to stringify uploaded files (handled separately below)
                if ($v instanceof \Psr\Http\Message\UploadedFileInterface) { continue; }
                if (is_string($v) || is_numeric($v) || is_bool($v)) {
                    $form[$k] = (string)$v;
                }
                // Ignore arrays/objects for these keys
            }

            if (!empty($form['assistance_confirmation_provided'])) {
                $form['delay_confirmation_received'] = (string)$form['assistance_confirmation_provided'];
            } elseif (!empty($form['delay_confirmation_received']) && empty($form['assistance_confirmation_provided'])) {
                $form['assistance_confirmation_provided'] = (string)$form['delay_confirmation_received'];
            }

            // Map 3.2.7 Ticket Number(s)/Booking Reference to journey.bookingRef when sensible
            // - If a single token is provided, treat it as the booking reference (PNR)
            // - If multiple distinct tokens are provided, do NOT set bookingRef; instead hint shared_pnr_scope=Nej when unknown
            $ticketField = (string)($this->request->getData('ticket_no') ?? ($form['ticket_no'] ?? ''));
            if (is_string($ticketField)) {
                $val = trim($ticketField);
                if ($val !== '') {
                    $tokens = preg_split('/[\s,;]+/', $val) ?: [];
                    $tokens = array_values(array_filter(array_map('trim', $tokens), function($s){ return $s !== ''; }));
                    $unique = array_values(array_unique($tokens));
                    if (count($unique) === 1) {
                        if (empty($journey['bookingRef'])) {
                            $journey['bookingRef'] = (string)$unique[0];
                            $meta['logs'][] = 'AUTO: bookingRef set from 3.2.7 ticket_no field';
                        }
                    } elseif (count($unique) > 1) {
                        $cur = isset($meta['shared_pnr_scope']) ? strtolower((string)$meta['shared_pnr_scope']) : '';
                        if ($cur === '' || $cur === 'unknown' || $cur === 'ved ikke' || $cur === '-') {
                            $meta['shared_pnr_scope'] = 'Nej';
                            $meta['logs'][] = 'AUTO: shared_pnr_scope=Nej (multiple booking refs in 3.2.7)';
                        }
                    }
                }
            }

            // TRIN 1 — persist EU delay minutes if present in this POST (e.g., when uploading after entering delay)
            if ($this->request->getData('delay_min_eu') !== null && $this->request->getData('delay_min_eu') !== '') {
                $compute['delayMinEU'] = (int)$this->request->getData('delay_min_eu');
            }

            // If user clicked the TRIN 10 calculate/update button, persist delay and band explicitly
            if ($this->request->getData('delayAtFinalMinutes') !== null) {
                $form['delayAtFinalMinutes'] = (string)(int)$this->request->getData('delayAtFinalMinutes');
                $compute['delayMinEU'] = (int)$form['delayAtFinalMinutes'];
            }
            if ($this->request->getData('compensationBand') !== null) {
                $form['compensationBand'] = (string)$this->request->getData('compensationBand');
            }
            if ($this->request->getData('voucherAccepted') !== null) {
                $form['voucherAccepted'] = $this->truthy($this->request->getData('voucherAccepted')) ? '1' : '';
            }

            // Persist inline passenger edits back to meta['_passengers_auto'] if provided
            $paxEdited = $this->request->getData('passenger');
            if (is_array($paxEdited) && !empty($meta['_passengers_auto']) && is_array($meta['_passengers_auto'])) {
                $orig = (array)$meta['_passengers_auto'];
                foreach ($paxEdited as $idx => $pEdit) {
                    if (!is_array($pEdit)) { continue; }
                    $i = is_numeric($idx) ? (int)$idx : null; if ($i === null || !array_key_exists($i, $orig)) { continue; }
                    $name = isset($pEdit['name']) ? trim((string)$pEdit['name']) : null;
                    $isC = !empty($pEdit['is_claimant']);
                    if ($name !== null) { $orig[$i]['name'] = $name; }
                    $orig[$i]['is_claimant'] = $isC ? true : false;
                }
                $meta['_passengers_auto'] = $orig;
                $journey['passengers'] = $orig;
                $journey['passengerCount'] = count($orig);
            }

            // Handle TRIN 8 uploads
            try {
                $u1 = $this->request->getUploadedFile('extra_expense_upload');
            } catch (\Throwable $e) { $u1 = null; }
            try {
                $u2 = $this->request->getUploadedFile('delay_confirmation_upload');
            } catch (\Throwable $e) { $u2 = null; }
            try {
                $uConf = $this->request->getUploadedFile('assistance_confirmation_upload');
            } catch (\Throwable $e) { $uConf = null; }
            $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            if ($u1 && $u1->getError() === \UPLOAD_ERR_OK) {
                $name = $u1->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('exp_') . '_' . $safe);
                $u1->moveTo($target);
                $form['extra_expense_upload'] = $target;
            } elseif (($raw = $this->request->getData('extra_expense_upload')) && is_string($raw)) {
                $form['extra_expense_upload'] = $raw;
            }
            if ($uConf && $uConf->getError() === \UPLOAD_ERR_OK) {
                $name = $uConf->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('conf_') . '_' . $safe);
                $uConf->moveTo($target);
                $form['assistance_confirmation_upload'] = $target;
                $form['delay_confirmation_upload'] = $target;
            } elseif ($u2 && $u2->getError() === \UPLOAD_ERR_OK) {
                $name = $u2->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('dcr_') . '_' . $safe);
                $u2->moveTo($target);
                $form['delay_confirmation_upload'] = $target;
                if (empty($form['assistance_confirmation_upload'])) { $form['assistance_confirmation_upload'] = $target; }
            } elseif (($rawConf = $this->request->getData('assistance_confirmation_upload')) && is_string($rawConf)) {
                $form['assistance_confirmation_upload'] = $rawConf;
                if (empty($form['delay_confirmation_upload'])) { $form['delay_confirmation_upload'] = $rawConf; }
            } elseif (($raw2 = $this->request->getData('delay_confirmation_upload')) && is_string($raw2)) {
                $form['delay_confirmation_upload'] = $raw2;
                if (empty($form['assistance_confirmation_upload'])) { $form['assistance_confirmation_upload'] = $raw2; }
            }

            // TRIN 9 – Art. 9: Handle uploads (CoC and complaint receipt)
            try { $u3 = $this->request->getUploadedFile('coc_evidence_upload'); } catch (\Throwable $e) { $u3 = null; }
            try { $u4 = $this->request->getUploadedFile('complaint_receipt_upload'); } catch (\Throwable $e) { $u4 = null; }
            if ($u3 && $u3->getError() === \UPLOAD_ERR_OK) {
                $name = $u3->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('coc_') . '_' . $safe);
                $u3->moveTo($target);
                $form['coc_evidence_upload'] = $target;
            } elseif (($r3 = $this->request->getData('coc_evidence_upload')) && is_string($r3)) { $form['coc_evidence_upload'] = $r3; }
            if ($u4 && $u4->getError() === \UPLOAD_ERR_OK) {
                $name = $u4->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('rcp_') . '_' . $safe);
                $u4->moveTo($target);
                $form['complaint_receipt_upload'] = $target;
            } elseif (($r4 = $this->request->getData('complaint_receipt_upload')) && is_string($r4)) { $form['complaint_receipt_upload'] = $r4; }

            // TRIN 6 – Art. 12 (NYT flow): persist and map to evaluator-compatible hooks
            // Seller channel (operator/retailer/unknown) → journey.seller_type + seller_type_* hooks
            $sellerChannel = (string)($this->request->getData('seller_channel') ?? '');
            if ($sellerChannel === 'operator') {
                $journey['seller_type'] = 'operator';
                $meta['seller_type_operator'] = 'Ja';
                $meta['seller_type_agency'] = 'Nej';
            } elseif ($sellerChannel === 'retailer') {
                $journey['seller_type'] = 'agency';
                $meta['seller_type_operator'] = 'Nej';
                $meta['seller_type_agency'] = 'Ja';
            } elseif ($sellerChannel === 'unknown') {
                $meta['seller_type_operator'] = 'Ved ikke';
                $meta['seller_type_agency'] = 'Ved ikke';
            }
            // If bookingRef already present, infer single transaction automatically by seller role
            if (!empty($journey['bookingRef'])) {
                if (($journey['seller_type'] ?? null) === 'operator') {
                    $meta['single_txn_operator'] = 'Ja';
                } elseif (($journey['seller_type'] ?? null) === 'agency') {
                    $meta['single_txn_retailer'] = 'Ja';
                }
                // Also reflect this in hook 13 for clarity
                $meta['single_booking_reference'] = 'Ja';
                $meta['shared_pnr_scope'] = 'Ja';
            }
            // Same transaction (only asked when multiple PNRs) → single_txn_* hooks depending on seller
            $sameTxn = (string)($this->request->getData('same_transaction') ?? '');
            if ($sameTxn === 'yes' || $sameTxn === 'no') {
                if ($sellerChannel === 'operator') {
                    $meta['single_txn_operator'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                    $meta['single_txn_retailer'] = 'no';
                } elseif ($sellerChannel === 'retailer') {
                    $meta['single_txn_operator'] = 'no';
                    $meta['single_txn_retailer'] = ($sameTxn === 'yes') ? 'yes' : 'no';
                } else {
                    // Unknown seller: set both to unknown unless explicitly no
                    $meta['single_txn_operator'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                    $meta['single_txn_retailer'] = ($sameTxn === 'no') ? 'no' : 'unknown';
                }
            }
            // TRIN 4+5 simplified: through_ticket_disclosure (yes/no) and separate_contract_notice (yes/no)
            // Accept both new names and legacy *_bool for backwards compatibility
            $sepRaw = (string)($this->request->getData('separate_contract_notice') ?? $this->request->getData('separate_contract_notice_bool') ?? '');
            if ($sepRaw === 'yes') { $meta['separate_contract_notice'] = 'Ja'; }
            elseif ($sepRaw === 'no') { $meta['separate_contract_notice'] = 'Nej'; }

            $ttdRaw = (string)($this->request->getData('through_ticket_disclosure') ?? $this->request->getData('through_ticket_disclosure_bool') ?? '');
            if ($ttdRaw === 'yes') { $meta['through_ticket_disclosure'] = 'Ja'; }
            elseif ($ttdRaw === 'no') { $meta['through_ticket_disclosure'] = 'Nej'; }
            // Propagate any debug/auto answers for 9–13 and related hooks
            $art12Keys = [
                // 'through_ticket_disclosure' intentionally excluded – mapped above from boolean
                'single_txn_operator','single_txn_retailer','separate_contract_notice',
                'connection_time_realistic','one_contract_schedule','contact_info_provided','responsibility_explained'
            ];
            foreach ($art12Keys as $k) {
                $val = $this->request->getData($k);
                if ($val === null || $val === '') { continue; }
                $vv = is_string($val) ? trim($val) : (string)$val;
                if ($vv === 'Ved ikke' || $vv === '-') { $vv = 'unknown'; }
                if ($vv === '') { $vv = 'unknown'; }
                $meta[$k] = $vv;
            }

            // TRIN 9 – Map Art. 9 hooks into $meta so evaluator can auto-complete
            $art9Keys = [
                'coc_acknowledged','civ_marking_present','fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                'multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity',
                'pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing',
                'bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket',
                'fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status',
                'preinformed_disruption','preinfo_channel','realtime_info_seen',
                'promised_facilities','facilities_delivered_status','facility_impact_note',
                'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
                'complaint_channel_seen','complaint_already_filed','submit_via_official_channel'
            ];
            foreach ($art9Keys as $k) {
                $val = $this->request->getData($k);
                if ($val === null || $val === '') { continue; }
                // promised_facilities[] stays as array in meta for echoing
                if (is_array($val)) {
                    $meta[$k] = array_values(array_map('strval', $val));
                    continue;
                }
                $vv = is_string($val) ? trim($val) : (string)$val;
                if ($vv === 'Ved ikke' || $vv === '-') { $vv = 'unknown'; }
                if ($vv === '') { $vv = 'unknown'; }

                // Important: Don't let Art. 9 UI override TRIN 6 answers that already set meta
                // - If TRIN 6 has already provided a definite yes/no for a hook, keep it
                // - Special-case through_ticket_disclosure: Art. 9 uses legacy values
                //   ("Gennemgående"/"Særskilte"/"Ved ikke"). Ignore those here so
                //   the TRIN 6 boolean (yes/no = disclosure clear before purchase) wins.
                $hasDefinite = isset($meta[$k]) && !in_array(strtolower((string)$meta[$k]), ['','unknown','ved ikke','-'], true);
                if ($k === 'through_ticket_disclosure') {
                    $low = mb_strtolower($vv);
                    $isLegacyChoice = in_array($low, ['gennemgående','gennemgaende','særskilte','saerskilte','separate','ved ikke','unknown','-'], true);
                    if ($isLegacyChoice) {
                        // Only set if not already answered by TRIN 6; map to unknown to avoid polluting
                        if (!$hasDefinite) { $meta[$k] = 'unknown'; }
                        continue;
                    }
                }
                // For all other hooks: only overwrite if not already set to a definite value
                if ($hasDefinite) { continue; }
                $meta[$k] = $vv;
            }
            // If user indicated preinformed disruption "yes", auto-open TRIN 9 · Afbrydelser in one-form
            $pid = (string)($this->request->getData('preinformed_disruption') ?? '');
            if ($pid === 'yes') { $form['interest_disruption'] = $form['interest_disruption'] ?? '1'; }
            // Keep promised_facilities[] in $form for checkbox echoing
            $pf = $this->request->getData('promised_facilities');
            if (is_array($pf)) {
                $form['promised_facilities'] = array_values(array_map('strval', $pf));
            }

            // If user indicated no missed connection or incident is not delay/cancellation, clear station
            if (empty($incident['missed']) || !in_array(($incident['main'] ?? ''), ['delay','cancellation'], true)) {
                unset($form['missed_connection_station']);
            }

            $session->write('flow.journey', $journey);
            $session->write('flow.meta', $meta);
            $session->write('flow.compute', $compute);
            $session->write('flow.flags', $flags);
            $session->write('flow.incident', $incident);
            $session->write('flow.form', $form);

            // PRG: After an upload attempt, redirect to avoid resubmission and ensure anchor navigation to TRIN 4
            if ($didUpload || $attemptedUpload || ($multiUploaded ?? 0) > 0) {
                $session->write('flow.justUploaded', true);
                // Hint UI to show OCR debug card even if _auto is empty
                $meta['justUploaded'] = true;
                $session->write('flow.meta', $meta);
                // For AJAX hook refresh, skip redirect and let the action render hooks fragment below
                if (!($isAjaxHooks && $this->request->is('ajax')))
                {
                    // Preserve debug unlock query flags across redirect
                    $qs = [];
                    foreach (['allow_official','debug'] as $qk) {
                        $qv = $this->request->getQuery($qk);
                        if ($qv !== null && $qv !== '') { $qs[$qk] = $qv; }
                    }
                    return $this->redirect(array_merge(['action' => 'one', '#' => 's4'], $qs));
                }
            }
        }

        // Compute dependent outputs for right-hand side preview
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
            if (!empty($inferRes['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $inferRes['logs']); }
            // Populate distance for SE <150 km gating before building profile
            (new \App\Service\DistanceEstimator())->populateJourneyDistance($journey, $meta, $form);
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: scope infer failed: ' . $e->getMessage();
        }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Derive AUTO Art.12 hooks (2,3,5,6,7,8,13) before evaluation; user inputs remain authoritative
        try {
            $auto12 = (new \App\Service\Art12AutoDeriver())->apply($journey, $meta);
            $meta = $auto12['meta'];
            if (!empty($auto12['logs'])) { $meta['logs'] = array_merge($meta['logs'] ?? [], $auto12['logs']); }
        } catch (\Throwable $e) {
            $meta['logs'][] = 'WARN: Art12AutoDeriver failed: ' . $e->getMessage();
        }
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta);
        // Evaluate Art. 9 unconditionally (see above rationale)
        try {
            $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art9 = null;
            $meta['logs'][] = 'WARN: Art9Evaluator failed: ' . $e->getMessage();
        }
    $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);
    $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, ['delayMin' => ($compute['delayMinEU'] ?? 0)]);

        // Compute claim (used by hooks panel) before potential AJAX short-circuit
        $currency = 'EUR';
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? '0 EUR');
        $symMap = ['KČ'=>'CZK','Kč'=>'CZK','ZŁ'=>'PLN','zł'=>'PLN','FT'=>'HUF','Ft'=>'HUF','LEI'=>'RON','лв'=>'BGN'];
        foreach ($symMap as $sym => $iso) { if (stripos($priceRaw, $sym) !== false && !preg_match('/\b'.$iso.'\b/i', $priceRaw)) { $priceRaw .= ' ' . $iso; break; } }
        if (preg_match('/\b(BGN|CZK|DKK|HUF|PLN|RON|SEK|EUR)\b/i', $priceRaw, $mm)) { $currency = strtoupper($mm[1]); }
        if ($currency === 'EUR' && preg_match('/\bkr\b/i', $priceRaw)) {
            $opCountry = strtoupper((string)($journey['country']['value'] ?? ($meta['operator_country'] ?? '')));
            if (in_array($opCountry, ['DK','SE'], true)) { $currency = $opCountry === 'DK' ? 'DKK' : 'SEK'; }
        }
        if ($currency === 'EUR') {
            $opCountry = strtoupper((string)($journey['country']['value'] ?? ($meta['operator_country'] ?? '')));
            $nonEuro = ['BG'=>'BGN','CZ'=>'CZK','DK'=>'DKK','HU'=>'HUF','PL'=>'PLN','RO'=>'RON','SE'=>'SEK'];
            if (isset($nonEuro[$opCountry])) { $currency = $nonEuro[$opCountry]; }
        }
        // Build claim input similar to claims page, including expenses and fee logic
    $ticketPriceStr = (string)($journey['ticketPrice']['value'] ?? ($form['price'] ?? '0 EUR'));
        $ticketPriceAmount = (float)preg_replace('/[^0-9.]/', '', $ticketPriceStr);
        $delayForClaim = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
        $expensesIn = [
            'meals' => (float)($form['expense_breakdown_meals'] ?? ($form['expense_meals'] ?? 0)),
            'hotel' => (float)($form['expense_breakdown_hotel_nights'] ?? 0) > 0 ? 0.0 : (float)($form['expense_hotel'] ?? 0),
            // When hotel_nights is provided, we defer amount capture to receipts; otherwise use simple value if any
            'alt_transport' => (float)($form['expense_breakdown_local_transport'] ?? ($form['expense_alt_transport'] ?? 0)),
            'other' => (float)($form['expense_breakdown_other_amounts'] ?? ($form['expense_other'] ?? 0)),
        ];
        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => $ticketPriceAmount,
            'trip' => [ 'through_ticket' => true, 'legs' => [] ],
            'disruption' => [
                'delay_minutes_final' => $delayForClaim,
                'eu_only' => (bool)($compute['euOnly'] ?? true),
                'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                'extraordinary' => (bool)($compute['extraordinary'] ?? false),
                'self_inflicted' => false,
            ],
            'choices' => [
                // Mirror some TRIN 7 choices if needed later
                'wants_refund' => ((string)($form['remedyChoice'] ?? '') === 'refund_return'),
                'wants_reroute_same_soonest' => ((string)($form['remedyChoice'] ?? '') === 'reroute_soonest'),
                'wants_reroute_later_choice' => ((string)($form['remedyChoice'] ?? '') === 'reroute_later'),
            ],
            'expenses' => $expensesIn,
            'already_refunded' => 0,
            // Apply 25% fee on expenses only, not on compensation base (ticket price)
            'service_fee_mode' => 'expenses_only',
        ]);

        // If user chose refund_return (Art. 18(1)(1)), zero Art.19 and surface refund of ticket price
        if ((string)($form['remedyChoice'] ?? '') === 'refund_return') {
            $compAmt = (float)($claim['breakdown']['compensation']['amount'] ?? 0);
            $claim['breakdown']['compensation']['amount'] = 0.0;
            $claim['breakdown']['compensation']['pct'] = 0;
            $claim['breakdown']['compensation']['basis'] = 'Art. 19 udelukket pga. refund';
            $claim['breakdown']['refund']['amount'] = (float)$ticketPriceAmount;
            $claim['breakdown']['refund']['basis'] = 'Art. 18(1)(1)';
            // Keep gross at least at refund amount, after removing comp component
            $existingGross = (float)($claim['totals']['gross_claim'] ?? 0);
            $claim['totals']['gross_claim'] = max($existingGross - $compAmt, (float)$ticketPriceAmount);
        }

        // Build a simple multi-ticket grouped overview for hooks panel
        $multiTickets = (array)($meta['_multi_tickets'] ?? []);
        // Compose a current-ticket summary (from _auto/meta)
        $currSummary = [
            'file' => (string)($form['_ticketFilename'] ?? ''),
            'pnr' => (string)($journey['bookingRef'] ?? ''),
            'dep_date' => (string)($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')),
            'passengers' => (array)($meta['_passengers_auto'] ?? []),
            'segments' => (array)($meta['_segments_auto'] ?? []),
        ];
        $allForGrouping = [];
        if (!empty($currSummary['pnr']) || !empty($currSummary['dep_date'])) { $allForGrouping[] = $currSummary; }
        foreach ($multiTickets as $mt) {
            $allForGrouping[] = [
                'file' => (string)($mt['file'] ?? ''),
                'pnr' => (string)($mt['pnr'] ?? ''),
                'dep_date' => (string)($mt['dep_date'] ?? ''),
                'passengers' => (array)($mt['passengers'] ?? []),
                'segments' => (array)($mt['segments'] ?? []),
            ];
        }
        $groupedTickets = [];
        if (!empty($allForGrouping)) {
            $groups = (new \App\Service\TicketJoinService())->groupTickets($allForGrouping);
            $groupedTickets = $groups;
        }

        // Recommend claim form (EU vs national) based on exemptions/overrides
        try {
            $countryCtx = (string)($journey['country']['value'] ?? ($form['operator_country'] ?? ''));
            $scopeCtx = (string)($profile['scope'] ?? '');
            $opCtx = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
            $prodCtx = (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''));
            // Prefer explicit minutes; else use cancellation as an intent hint
            $delayCtx = (int)($compute['delayMinEU'] ?? 0);
            if ($delayCtx <= 0) {
                $mainIncident = (string)($incident['main'] ?? '');
                if ($delayCtx <= 0 && $mainIncident === 'cancellation') { $delayCtx = 120; }
            }
            $selector = new \App\Service\ClaimFormSelector();
            $formDecision = $selector->select([
                'country' => $countryCtx,
                'scope' => $scopeCtx,
                'operator' => $opCtx,
                'product' => $prodCtx,
                'delayMin' => $delayCtx,
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            $formDecision = ['form' => 'eu_standard_claim', 'reason' => 'EU baseline (fallback)', 'notes' => ['Selector error: ' . $e->getMessage()]];
        }

        if ($isAjaxHooks && $this->request->is('ajax')) {
            // Render only the hooks panel element (no layout) for live updates
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('hooks_panel');
            // EU-only suggestion for preview
            [$euOnlySuggested, $euOnlyReason] = $this->suggestEuOnly($journey, $meta);
            $this->set(compact('profile','art12','art9','refund','refusion','claim','journey','meta','compute','flags','incident','form','groupedTickets','euOnlySuggested','euOnlyReason'));
            return $this->render();
        }

        // Claim already computed above (also used by AJAX partial)

    // Reasons and additional info
        $reason_delay = (!empty($incident['main']) && $incident['main'] === 'delay');
        $reason_cancellation = (!empty($incident['main']) && $incident['main'] === 'cancellation');
        $reason_missed_conn = !empty($incident['missed']);
        $add = [];
        if (!empty($flags['travel_state'])) {
            $label = [
                'completed' => 'Rejsen er afsluttet',
                'ongoing' => 'Rejsen er påbegyndt (i tog / skift)',
                'before_start' => 'Jeg skal til at påbegynde rejsen',
            ][$flags['travel_state']] ?? $flags['travel_state'];
            $add[] = 'TRIN 1: ' . $label;
        }
        $sel = [];
        if ($reason_delay) $sel[] = 'Delay';
        if ($reason_cancellation) $sel[] = 'Cancellation';
        if ($reason_missed_conn) $sel[] = 'Missed connection';
        if (!empty($sel)) $add[] = 'TRIN 2: ' . implode(', ', $sel);
        $additional_info = implode(' | ', $add);

        // TRIN 3/5 – Liability/CIV screening and gating (two-step)
        $hasValidTicket = isset($form['hasValidTicket']) ? (bool)$this->truthy($form['hasValidTicket']) : true;
        $safetyMisconduct = isset($form['safetyMisconduct']) ? (bool)$this->truthy($form['safetyMisconduct']) : false;
        $forbiddenItemsOrAnimals = isset($form['forbiddenItemsOrAnimals']) ? (bool)$this->truthy($form['forbiddenItemsOrAnimals']) : false;
        // true means complied with admin/customs rules
        $customsRulesBreached = isset($form['customsRulesBreached']) ? (bool)$this->truthy($form['customsRulesBreached']) : true;
        // Step B: operator-stamped proof or other documentary basis
        $operatorProof = isset($form['operatorStampedDisruptionProof']) ? (bool)$this->truthy($form['operatorStampedDisruptionProof']) : false;
        // Step A: self-inflicted if any of these hold
        $selfInflicted = (!$hasValidTicket) || $safetyMisconduct || $forbiddenItemsOrAnimals || (!$customsRulesBreached);
        // Combined gate: require not self-inflicted AND operator proof
        $liability_ok = (!$selfInflicted) && $operatorProof;

        // TRIN 4 – Missed connection + separate contracts disclosure (Art. 12 note)
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);

        // Auto-beregn nedgraderingsandel (tid/distance) i TRIN 3 og gem i form/session
        try {
            if (empty($form['downgrade_segment_share'])) {
                $segments = (array)($meta['_segments_auto'] ?? ($journey['segments'] ?? []));
                $missedStation = (string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? ''));
                $auto = $this->computeDowngradeSegmentShareAuto($segments, $missedStation);
                $shareAuto = max(0.0, min(1.0, (float)($auto['share'] ?? 1.0)));
                $form['downgrade_segment_share'] = (string)round($shareAuto, 3);
                $form['downgrade_segment_share_basis'] = (string)($auto['basis'] ?? 'unknown');
                $form['downgrade_segment_share_conf'] = (string)($auto['confidence'] ?? '0');
                $this->request->getSession()->write('flow.form', $form);
            }
        } catch (\Throwable $e) { /* ignore */ }

        // TRIN 5–6 – Entitlements and choices
        $delayAtFinal = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
    // Do NOT block entitlements based on Art. 12 outcome. Even if Art. 12 is negative
    // for a missed connection, the user may still seek remedies/assistance on other bases.
    $art12_block = false;
    $allow_refund = (!$missedConnBlock) && (($compute['delayMinEU'] ?? 0) >= 60 || $reason_cancellation);
    $allow_compensation = (!$missedConnBlock) && ($delayAtFinal >= 60);
        // Section 4 choice resolution (enforce not both): prefer explicit remedyChoice=refund; else compensation; else alt_costs
        $section4_choice = null;
        $remedyChoice = $form['remedyChoice'] ?? null; // refund | reroute_soonest | reroute_later
        if ($remedyChoice === 'refund' && $allow_refund) {
            $section4_choice = 'refund';
        } elseif (!empty($form['compensationBand']) && $allow_compensation) {
            $section4_choice = 'compensation';
        } elseif (!empty($form['reroute_extra_costs'])) {
            $section4_choice = 'alt_costs';
        }

        // GDPR consent
        $gdpr_ok = isset($form['gdprConsent']) ? (bool)$this->truthy($form['gdprConsent']) : false;

        // EU-only suggestion for full-page one() view as well
        [$euOnlySuggested, $euOnlyReason] = $this->suggestEuOnly($journey, $meta);

        $this->set(compact(
            'journey','meta','compute','flags','incident','form',
            'profile','art12','art9','refund','refusion','claim',
            'reason_delay','reason_cancellation','reason_missed_conn','additional_info',
            'liability_ok','missedConnBlock','allow_refund','allow_compensation','section4_choice','gdpr_ok','delayAtFinal','art12_block','selfInflicted','operatorProof','formDecision','euOnlySuggested','euOnlyReason'
        ));
        $this->set(compact('groupedTickets'));
        $this->viewBuilder()->setTemplate('one');
        return null;
    }

    /**
     * Split-step: Details (TRIN 1–2 basic journey and incident)
     */
    public function details(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $journey = (array)$session->read('flow.journey') ?: [];
        $compute = (array)$session->read('flow.compute') ?: ['euOnly' => true];
        $flags = (array)$session->read('flow.flags') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            $travelState = (string)($this->request->getData('travel_state') ?? '');
            if (in_array($travelState, ['completed','ongoing','before_start'], true)) {
                $flags['travel_state'] = $travelState;
            }
            $compute['euOnly'] = (bool)$this->request->getData('eu_only');
            $compute['delayMinEU'] = (int)($this->request->getData('delay_min_eu') ?? 0);
            $compute['knownDelayBeforePurchase'] = (bool)$this->request->getData('known_delay');
            $compute['extraordinary'] = (bool)$this->request->getData('extraordinary');
            $main = (string)($this->request->getData('incident_main') ?? '');
            if (in_array($main, ['delay','cancellation',''], true)) { $incident['main'] = $main; }
            $incident['missed'] = $this->truthy($this->request->getData('missed_connection'));
            $session->write('flow.compute', $compute);
            $session->write('flow.flags', $flags);
            $session->write('flow.incident', $incident);
            return $this->redirect(['action' => 'screening']);
        }
        $this->set(compact('journey','compute','flags','incident'));
        return null;
    }

    /**
     * Split-step: Screening (TRIN 3–4 liability/CIV + Art. 12 disclosure)
     */
    public function screening(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        if ($this->request->is('post')) {
            foreach (['hasValidTicket','safetyMisconduct','forbiddenItemsOrAnimals','customsRulesBreached','isThroughTicket','separateContractsDisclosed','singleTxn','sellerType'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'choices']);
        }
        // compute gating preview
    $hasValidTicket = isset($form['hasValid Ticket']) ? (bool)$this->truthy($form['hasValidTicket']) : true;
    $safetyMisconduct = isset($form['safetyMisconduct']) ? (bool)$this->truthy($form['safetyMisconduct']) : false;
    $forbiddenItemsOrAnimals = isset($form['forbiddenItemsOrAnimals']) ? (bool)$this->truthy($form['forbiddenItemsOrAnimals']) : false;
    $customsRulesBreached = isset($form['customsRulesBreached']) ? (bool)$this->truthy($form['customsRulesBreached']) : true;
    $operatorProof = isset($form['operatorStampedDisruptionProof']) ? (bool)$this->truthy($form['operatorStampedDisruptionProof']) : false;
    $selfInflicted = (!$hasValidTicket) || $safetyMisconduct || $forbiddenItemsOrAnimals || (!$customsRulesBreached);
    $liability_ok = (!$selfInflicted) && $operatorProof;
        $reason_missed_conn = !empty($incident['missed']);
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);
        $this->set(compact('form','liability_ok','missedConnBlock'));
        return null;
    }

    /**
     * Split-step: Choices (TRIN 5–6 remedies/compensation)
     */
    public function choices(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $compute = (array)$session->read('flow.compute') ?: [];
        $form = (array)$session->read('flow.form') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        if ($this->request->is('post')) {
            foreach (['remedyChoice','trip_cancelled_return_to_origin','refund_requested','refund_form_selected','return_to_origin_expense','return_to_origin_amount','return_to_origin_currency','reroute_same_conditions_soonest','reroute_later_at_choice','reroute_info_within_100min','self_purchased_new_ticket','self_purchase_approved_by_operator','offer_provided','reroute_extra_costs','reroute_extra_costs_amount','reroute_extra_costs_currency','downgrade_occurred','downgrade_comp_basis','delayAtFinalMinutes','compensationBand','voucherAccepted','art18_expected_delay_60','delay_confirmation_received','delay_confirmation_info'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            try { $delayFile = $this->request->getUploadedFile('delay_confirmation_upload'); } catch (\Throwable $e) { $delayFile = null; }
            $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            if ($delayFile && $delayFile->getError() === \UPLOAD_ERR_OK) {
                $name = (string)$delayFile->getClientFilename();
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
                $target = $uploadDir . (uniqid('delay_conf_') . '_' . $safe);
                try { $delayFile->moveTo($target); $form['delay_confirmation_upload'] = $target; } catch (\Throwable $e) { /* ignore */ }
            } elseif (($rawUpload = $this->request->getData('delay_confirmation_upload')) && is_string($rawUpload)) {
                $form['delay_confirmation_upload'] = $rawUpload;
            }
            if (($form['delay_confirmation_received'] ?? '') !== 'yes') {
                unset($form['delay_confirmation_upload']);
            }

            // Enforce Art. 18(3) OFF behavior: only allow extra costs for reroute when self-purchase is approved by operator
            try {
                $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
                $journey = $inferRes['journey'];
            } catch (\Throwable $e) { /* ignore */ }
            try {
                $profileNow = (new \App\Service\ExemptionProfileBuilder())->build($journey);
                $a183On = !isset($profileNow['articles']['art18_3']) || $profileNow['articles']['art18_3'] !== false;
            } catch (\Throwable $e) { $a183On = true; }

            if (!$a183On) {
                $selfBuy = (string)($form['self_purchased_new_ticket'] ?? '');
                $opApproved = (string)($form['self_purchase_approved_by_operator'] ?? 'unknown');
                if (!($selfBuy === 'yes' && $opApproved === 'yes')) {
                    // Force extra costs off and clear values
                    $form['reroute_extra_costs'] = 'no';
                    $form['reroute_extra_costs_amount'] = '';
                    // keep currency as-is or clear – we clear amount only
                    // Optional: flash a non-blocking notice
                    try { $this->Flash->warning('Ekstraomkostninger deaktiveret: selvkøb uden operatørens godkendelse.'); } catch (\Throwable $e) { /* ignore flash */ }
                }
            }

            // Compute and persist downgrade refund preview (Annex II) when applicable
            try {
                $ticketPriceStr = (string)($journey['ticketPrice']['value'] ?? ($form['price'] ?? '0'));
                $ticketPriceAmount = (float)preg_replace('/[^0-9.]/', '', $ticketPriceStr);
                $segShareIn = $this->request->getData('downgrade_segment_share');
                $segmentShare = is_numeric($segShareIn) ? (float)$segShareIn : (float)($form['downgrade_segment_share'] ?? 1.0);
                // If no manual share provided, derive from per-leg auto flags (TRIN 3 table)
                $legsPurchased = (array)($this->request->getData('leg_class_purchased') ?? ($form['leg_class_purchased'] ?? []));
                $legsDelivered = (array)($this->request->getData('leg_class_delivered') ?? ($form['leg_class_delivered'] ?? []));
                $legDowngraded = (array)($this->request->getData('leg_downgraded') ?? ($form['leg_downgraded'] ?? []));
                $autoShare = null;
                $autoBasis = null;
                $rank = [
                    'sleeper' => 5,
                    '1st_class' => 4,
                    'seat_reserved' => 3,
                    'couchette' => 3,
                    '2nd_class' => 2,
                    'other' => 2,
                    'free_seat' => 1,
                ];
                $rateFromDelta = function (string $buy, string $del) use ($rank): array {
                    $rb = $rank[$buy] ?? 0;
                    $rd = $rank[$del] ?? 0;
                    if ($rb <= $rd || $rb === 0 || $rd === 0) { return [0.0, null]; }
                    if ($buy === 'sleeper') { return [0.75, 'sleeper']; }
                    if ($buy === 'couchette' || ($rb >= 3 && $rd <= 2)) { return [0.50, 'couchette']; }
                    return [0.25, 'seat'];
                };
                if (!empty($legsPurchased) && !empty($legsDelivered) && count($legsPurchased) === count($legsDelivered)) {
                    $total = count($legsPurchased);
                    $downgLegs = 0;
                    $bestRate = 0.0;
                    $bestBasis = null;
                    foreach ($legsPurchased as $i => $buy) {
                        $del = (string)($legsDelivered[$i] ?? '');
                        $flag = (string)($legDowngraded[$i] ?? '');
                        if ($flag === '1') {
                            $downgLegs++;
                            [$r, $b] = $rateFromDelta((string)$buy, $del);
                            if ($r > $bestRate) { $bestRate = $r; $bestBasis = $b; }
                        } else {
                            // infer even if flag mangler
                            [$r, $b] = $rateFromDelta((string)$buy, $del);
                            if ($r > 0) {
                                $downgLegs++;
                                if ($r > $bestRate) { $bestRate = $r; $bestBasis = $b; }
                            }
                        }
                    }
                    if ($total > 0 && $downgLegs > 0) {
                        $autoShare = round($downgLegs / $total, 3);
                        $autoBasis = $bestBasis;
                        $segmentShare = $autoShare;
                        $form['downgrade_segment_share'] = (string)$segmentShare;
                        if (empty($form['downgrade_occurred'])) { $form['downgrade_occurred'] = 'yes'; }
                        if (empty($form['downgrade_comp_basis']) && $autoBasis !== null) {
                            $form['downgrade_comp_basis'] = $autoBasis;
                        }
                        $form['leg_class_purchased'] = $legsPurchased;
                        $form['leg_class_delivered'] = $legsDelivered;
                        $form['leg_downgraded'] = $legDowngraded;
                    }
                }
                if (!is_finite($segmentShare)) { $segmentShare = 1.0; }
                if ($segmentShare < 0.0) { $segmentShare = 0.0; }
                if ($segmentShare > 1.0) { $segmentShare = 1.0; }
                $downgradeRefund = 0.0;
                if (isset($form['downgrade_occurred']) && $form['downgrade_occurred'] === 'yes' && !empty($form['downgrade_comp_basis'])) {
                    $rate = 0.0;
                    switch ((string)$form['downgrade_comp_basis']) {
                        case 'seat': $rate = 0.25; break;       // Seat 1->2 class
                        case 'couchette': $rate = 0.50; break;   // Couchette/Sleeper comfort step down
                        case 'sleeper': $rate = 0.75; break;     // Sleeper -> Seat
                        default: $rate = 0.0; break;
                    }
                    $downgradeRefund = round($ticketPriceAmount * $rate * $segmentShare, 2);
                }
                $form['downgrade_segment_share'] = (string)$segmentShare;
                $form['downgrade_refund_amount'] = (string)$downgradeRefund;
                $form['downgrade_ticket_price'] = (string)$ticketPriceAmount;
            } catch (\Throwable $e) { /* ignore calc errors */ }
            $session->write('flow.form', $form);
            // Visual confirmation for users that the step advanced
            try { $this->Flash->success('Dine valg er gemt. Fortsætter til Assistance.'); } catch (\Throwable $e) { /* ignore */ }
            // After TRIN 4 · Dine valg, continue to TRIN 5 · Assistance (Art. 20)
            return $this->redirect(['action' => 'assistance']);
        }
        // Build profile for gating (Art. 18(3) etc.) to mirror one() TRIN 7 behaviour
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
        } catch (\Throwable $e) { /* ignore for choices */ }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Final defensive restore of delivered-klasse fra session, i tilfælde af at andre operationer nulstillede den
        $persistedDelivered = (array)$session->read('flow.leg_class_delivered') ?: [];
        if (!empty($persistedDelivered)) {
            $form['leg_class_delivered'] = $persistedDelivered;
            $meta['saved_leg_class_delivered'] = $persistedDelivered;
            $session->write('flow.form', $form);
            $session->write('flow.leg_class_delivered_locked', true);
        }
        // Derive ticket price amount for downgrade preview in view
        try {
            $ticketPriceStr = (string)($journey['ticketPrice']['value'] ?? ($form['price'] ?? '0'));
            $ticketPrice = (float)preg_replace('/[^0-9.]/', '', $ticketPriceStr);
        } catch (\Throwable $e) { $ticketPrice = 0.0; }
        // Auto-compute downgrade segment share (prefer distance, else time) when not provided yet
        try {
            if (empty($form['downgrade_segment_share'])) {
                $segments = (array)($meta['_segments_auto'] ?? ($journey['segments'] ?? []));
                $missedStation = (string)($form['missed_connection_station'] ?? '');
                $auto = $this->computeDowngradeSegmentShareAuto($segments, $missedStation);
                $share = max(0.0, min(1.0, (float)$auto['share']));
                $form['downgrade_segment_share'] = (string)round($share, 3);
                $form['downgrade_segment_share_basis'] = (string)($auto['basis'] ?? 'unknown');
                $form['downgrade_segment_share_conf'] = (string)($auto['confidence'] ?? '0');
                $session->write('flow.form', $form);
            }
            $autoDowngradeShare = [
                'share' => isset($form['downgrade_segment_share']) ? (float)$form['downgrade_segment_share'] : null,
                'basis' => (string)($form['downgrade_segment_share_basis'] ?? 'unknown'),
                'confidence' => (float)($form['downgrade_segment_share_conf'] ?? 0),
            ];
        } catch (\Throwable $e) { $autoDowngradeShare = null; }
        $delayAtFinal = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
        $reason_cancellation = (!empty($incident['main']) && $incident['main'] === 'cancellation');
        $reason_missed_conn = !empty($incident['missed']);
        $singleTxn = isset($form['singleTxn']) ? (bool)$this->truthy($form['singleTxn']) : false;
        $separateDisclosed = isset($form['separateContractsDisclosed']) ? (bool)$this->truthy($form['separateContractsDisclosed']) : false;
        $missedConnBlock = ($reason_missed_conn && $singleTxn && $separateDisclosed);
        $allow_refund = (!$missedConnBlock) && (($compute['delayMinEU'] ?? 0) >= 60 || $reason_cancellation);
        $allow_compensation = (!$missedConnBlock) && ($delayAtFinal >= 60);
        // Art. 18 gating is based on expected delay (EU minutes) or cancellation/missed connection
        $expectedDelay = (int)($compute['delayMinEU'] ?? 0);
        $art18Active = ($expectedDelay >= 60) || $reason_cancellation || $reason_missed_conn;
        if (!$art18Active && ((string)($form['art18_expected_delay_60'] ?? '') === 'yes')) { $art18Active = true; }
        $art18Blocked = (!$art18Active) && ((string)($form['art18_expected_delay_60'] ?? '') === 'no');
        $showArt18Fallback = (!$art18Active) && (!$art18Blocked);
        $this->set(compact('form','delayAtFinal','allow_refund','allow_compensation','flags','incident','profile','ticketPrice','autoDowngradeShare','art18Active','showArt18Fallback','art18Blocked'));
        return null;
    }

    /**
     * Split-step: Assistance & expenses (NEW TRIN 5 – Art. 20)
     * Mirrors one() TRIN 8 UI and persistence.
     */
    public function assistance(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: [];
        $flags = (array)$session->read('flow.flags') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];

        if ($this->request->is('post')) {
            // Persist Art. 20 fields (as in one() TRIN 8) + self-paid fields for Art. 18(3) eligibility
            $keys = [
                // Art. 20(2)(a) - meals offered
                'meal_offered','assistance_meals_unavailable_reason',
                // Art. 20(2)(b) - hotel offered + overnight needed
                'hotel_offered','overnight_needed','assistance_hotel_accessible',
                // Art. 20(2)(c) - evacuation/alt transport away from blocked train
                'blocked_train_alt_transport','blocked_on_track','stranded_at_station','stranded_unknown','assistance_blocked_transport_possible','assistance_blocked_transport_to','assistance_blocked_transport_time',
                // Art. 20(3) - alternative transport services to destination
                'alt_transport_provided','assistance_alt_transport_offered_by','assistance_alt_transport_type','assistance_alt_departure_time','assistance_alt_arrival_time','assistance_alt_to_destination',
                // Art. 20(1) - info updates tracked earlier (still available via info card)
                'assistance_info_source','assistance_updates_channel','assistance_info_time_first','assistance_info_time_last','assistance_expected_dep','assistance_expected_arr',
                // Art. 20(4) - confirmation info + type
                'assistance_confirmation_requested','assistance_confirmation_provided','assistance_confirmation_type','assistance_confirmation_upload',
                // Art. 20(5) - PMR priority
                'assistance_pmr_priority_applied','assistance_pmr_companion_supported','assistance_pmr_dog_supported',
                // Generic expense breakdown (legacy, kept)
                'expense_breakdown_meals','expense_breakdown_hotel_nights','expense_breakdown_local_transport','expense_breakdown_other_amounts','expense_breakdown_currency',
                // NEW: self-paid fields to capture out-of-pocket expenses when Art. 20 assistance was not delivered
                'meal_self_paid_amount','meal_self_paid_currency',
                'hotel_self_paid_amount','hotel_self_paid_currency','hotel_self_paid_nights',
                'blocked_self_paid_amount','blocked_self_paid_currency',
                'alt_self_paid_amount','alt_self_paid_currency',
                'art20_expected_delay_60',
            ];
            foreach ($keys as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }

            // Handle uploads for receipts and delay confirmation (mirror one())
            try { $u1 = $this->request->getUploadedFile('extra_expense_upload'); } catch (\Throwable $e) { $u1 = null; }
            try { $u2 = $this->request->getUploadedFile('delay_confirmation_upload'); } catch (\Throwable $e) { $u2 = null; }
            try { $uConf = $this->request->getUploadedFile('assistance_confirmation_upload'); } catch (\Throwable $e) { $uConf = null; }
            // NEW: per-field self-paid receipt uploads
            try { $uMeal = $this->request->getUploadedFile('meal_self_paid_receipt'); } catch (\Throwable $e) { $uMeal = null; }
            try { $uHotel = $this->request->getUploadedFile('hotel_self_paid_receipt'); } catch (\Throwable $e) { $uHotel = null; }
            try { $uBlocked = $this->request->getUploadedFile('blocked_self_paid_receipt'); } catch (\Throwable $e) { $uBlocked = null; }
            try { $uAlt = $this->request->getUploadedFile('alt_self_paid_receipt'); } catch (\Throwable $e) { $uAlt = null; }
            $uploadDir = WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
            if ($u1 && $u1->getError() === \UPLOAD_ERR_OK) {
                $name = (string)$u1->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('exp_') . '_' . $safe);
                try { $u1->moveTo($target); $form['extra_expense_upload'] = $target; } catch (\Throwable $e) { /* ignore */ }
            } elseif (($raw = $this->request->getData('extra_expense_upload')) && is_string($raw)) {
                $form['extra_expense_upload'] = $raw;
            }
            if ($uConf && $uConf->getError() === \UPLOAD_ERR_OK) {
                $name = (string)$uConf->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('conf_') . '_' . $safe);
                try { $uConf->moveTo($target); $form['assistance_confirmation_upload'] = $target; $form['delay_confirmation_upload'] = $target; } catch (\Throwable $e) { /* ignore */ }
            } elseif ($u2 && $u2->getError() === \UPLOAD_ERR_OK) {
                $name = (string)$u2->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $target = $uploadDir . (uniqid('dcr_') . '_' . $safe);
                try {
                    $u2->moveTo($target);
                    $form['delay_confirmation_upload'] = $target;
                    if (empty($form['assistance_confirmation_upload'])) { $form['assistance_confirmation_upload'] = $target; }
                } catch (\Throwable $e) { /* ignore */ }
            } elseif (($rawConf = $this->request->getData('assistance_confirmation_upload')) && is_string($rawConf)) {
                $form['assistance_confirmation_upload'] = $rawConf;
                if (empty($form['delay_confirmation_upload'])) { $form['delay_confirmation_upload'] = $rawConf; }
            } elseif (($raw2 = $this->request->getData('delay_confirmation_upload')) && is_string($raw2)) {
                $form['delay_confirmation_upload'] = $raw2;
                if (empty($form['assistance_confirmation_upload'])) { $form['assistance_confirmation_upload'] = $raw2; }
            }

            if (!empty($form['assistance_confirmation_provided'])) {
                $form['delay_confirmation_received'] = (string)$form['assistance_confirmation_provided'];
            } elseif (!empty($form['delay_confirmation_received']) && empty($form['assistance_confirmation_provided'])) {
                $form['assistance_confirmation_provided'] = (string)$form['delay_confirmation_received'];
            }

            // Save per-field self-paid receipts if provided
            $saveUpload = function($ufile, string $prefix) use ($uploadDir) {
                if (!$ufile || $ufile->getError() !== \UPLOAD_ERR_OK) { return null; }
                $name = (string)$ufile->getClientFilename();
                $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string)$name);
                $ext = strtolower(pathinfo($safe ?? '', PATHINFO_EXTENSION));
                $allowed = ['pdf','jpg','jpeg','png'];
                if (!in_array($ext, $allowed, true)) { return null; }
                $target = $uploadDir . (uniqid($prefix) . '_' . $safe);
                try { $ufile->moveTo($target); return $target; } catch (\Throwable $e) { return null; }
            };
            if ($path = $saveUpload($uMeal, 'mrc_')) { $form['meal_self_paid_receipt'] = $path; }
            elseif (($raw = $this->request->getData('meal_self_paid_receipt')) && is_string($raw)) { $form['meal_self_paid_receipt'] = $raw; }
            if ($path = $saveUpload($uHotel, 'hrc_')) { $form['hotel_self_paid_receipt'] = $path; }
            elseif (($raw = $this->request->getData('hotel_self_paid_receipt')) && is_string($raw)) { $form['hotel_self_paid_receipt'] = $raw; }
            if ($path = $saveUpload($uBlocked, 'brc_')) { $form['blocked_self_paid_receipt'] = $path; }
            elseif (($raw = $this->request->getData('blocked_self_paid_receipt')) && is_string($raw)) { $form['blocked_self_paid_receipt'] = $raw; }
            if ($path = $saveUpload($uAlt, 'arc_')) { $form['alt_self_paid_receipt'] = $path; }
            elseif (($raw = $this->request->getData('alt_self_paid_receipt')) && is_string($raw)) { $form['alt_self_paid_receipt'] = $raw; }

            // Normalize monetary inputs and infer Art. 18(3) eligibility when self-paid due to missing Art. 20 assistance
            try {
                $moneyFields = [
                    'meal_self_paid_amount','hotel_self_paid_amount','blocked_self_paid_amount','alt_self_paid_amount',
                    'expense_breakdown_meals','expense_breakdown_local_transport','expense_breakdown_other_amounts'
                ];
                foreach ($moneyFields as $mf) {
                    if (!empty($form[$mf])) {
                        $clean = (string)$form[$mf];
                        // Convert locale-ish numbers to standard decimal
                        $clean = preg_replace('/[^0-9,\.]/', '', $clean) ?? (string)$clean;
                        // If both comma and dot present, assume comma as thousands and remove commas
                        if (strpos((string)$clean, ',') !== false && strpos((string)$clean, '.') !== false) {
                            $clean = str_replace(',', '', (string)$clean);
                        } else {
                            // If only comma, treat as decimal separator
                            if (strpos((string)$clean, ',') !== false) { $clean = str_replace(',', '.', (string)$clean); }
                        }
                        $num = (float)$clean;
                        if ($num < 0) { $num = 0.0; }
                        $form[$mf] = number_format($num, 2, '.', '');
                    }
                }
            } catch (\Throwable $e) { /* ignore normalize errors */ }

            // Default currency fallback for self-paid if not provided
            try {
                $fallbackCur = (string)($form['expense_breakdown_currency'] ?? '');
                if ($fallbackCur !== '') {
                    foreach ([
                        ['meal_self_paid_currency'],
                        ['hotel_self_paid_currency'],
                        ['blocked_self_paid_currency'],
                        ['alt_self_paid_currency'],
                    ] as $arr) {
                        $key = $arr[0];
                        if (empty($form[$key])) { $form[$key] = $fallbackCur; }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // Art. 18(3) eligibility: user self-paid due to missing Art. 20-provided assistance
            $eligibleUnder18_3 = false;
            try {
                if ((string)($form['blocked_train_alt_transport'] ?? '') === 'no' && (string)($form['blocked_self_paid_amount'] ?? '') !== '') {
                    $eligibleUnder18_3 = true;
                }
                if ((string)($form['alt_transport_provided'] ?? '') === 'no' && (string)($form['alt_self_paid_amount'] ?? '') !== '') {
                    $eligibleUnder18_3 = true;
                }
                if ((string)($form['meal_offered'] ?? '') === 'no' && (string)($form['meal_self_paid_amount'] ?? '') !== '') {
                    $eligibleUnder18_3 = true;
                }
                if ((string)($form['hotel_offered'] ?? '') === 'no' && (string)($form['hotel_self_paid_amount'] ?? '') !== '') {
                    $eligibleUnder18_3 = true;
                }
            } catch (\Throwable $e) { /* ignore */ }
            // Guard: do not set eligibility if Art. 18(3) itself is exempt (off)
            try {
                $profileTmp = (new \App\Service\ExemptionProfileBuilder())->build($journey);
                $art183On = !isset($profileTmp['articles']['art18_3']) || $profileTmp['articles']['art18_3'] !== false;
            } catch (\Throwable $e) { $art183On = true; }
            $form['eligible_under_18_3'] = ($art183On && $eligibleUnder18_3) ? '1' : '0';

            $session->write('flow.form', $form);
            // After TRIN 5 · Assistance, continue to TRIN 6 · Compensation (Art. 19)
            return $this->redirect(['action' => 'compensation']);
        }

        // Build profile for exemption gating notes (Art. 20(2) etc.)
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
        } catch (\Throwable $e) { /* ignore for assistance */ }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

        try { $art20 = (new \App\Service\Art20AssistanceEvaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art20 = null; $meta['logs'][] = 'WARN: Art20AssistanceEvaluator failed: ' . $e->getMessage(); }
        $reason_cancellation = (!empty($incident['main']) && $incident['main'] === 'cancellation');
        // New gating per spec: ONLY cancellation auto-activates; otherwise ask user
        $art20Active = $reason_cancellation;
        if (!$art20Active && ((string)($form['art20_expected_delay_60'] ?? '') === 'yes')) { $art20Active = true; }
        $art20Blocked = (!$art20Active) && ((string)($form['art20_expected_delay_60'] ?? '') === 'no');
        $showArt20Fallback = (!$art20Active) && (!$art20Blocked);
        // Price hints (meals/hotel/taxi/alt) to pre-fill intervals in TRIN 5 UI
        $priceHints = null;
        try {
            $ctxHints = $this->buildPriceContextForHints($form, $journey, $meta);
            $fxRates = $this->loadFxRatesForHints();
            $fxFetcher = function (string $cur) use ($fxRates): ?float {
                $c = strtoupper(trim($cur));
                return isset($fxRates[$c]) && is_numeric($fxRates[$c]) ? (float)$fxRates[$c] : null;
            };
            $priceHints = (new PriceHintsService())->build($ctxHints, $fxFetcher);
            $meta['price_hints'] = $priceHints;
            $session->write('flow.meta', $meta);
        } catch (\Throwable $e) { /* ignore hints errors */ }

        $this->set(compact('form','flags','incident','profile','art20','art20Active','showArt20Fallback','art20Blocked','priceHints'));
        return null;
    }

    /**
     * Split-step: Compensation (NEW TRIN 6 – Art. 19)
     * Mirrors one() TRIN 10 summary logic in a compact PRG step.
     */
    public function compensation(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        $journey = (array)$session->read('flow.journey') ?: [];
        $meta = (array)$session->read('flow.meta') ?: [];
        $compute = (array)$session->read('flow.compute') ?: [];
        $incident = (array)$session->read('flow.incident') ?: [];

        // Note: Step 6 is final in this split flow; do not redirect away even if refund is chosen.

        if ($this->request->is('post')) {
            foreach (['delayAtFinalMinutes','compensationBand','voucherAccepted','operatorExceptionalCircumstances','operatorExceptionalType','minThresholdApplies'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'extras']);
        }

        // Build profile to apply exemptions/gating for Art. 19
        try {
            $inferRes = (new \App\Service\JourneyScopeInferer())->apply($journey, $meta);
            $journey = $inferRes['journey'];
        } catch (\Throwable $e) { /* ignore */ }
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
        // Determine national policy (for domestic cases) to inform lenient bands and UI
        $nationalPolicy = null;
        try {
            $countryCtx0 = (string)($journey['country']['value'] ?? ($form['operator_country'] ?? ''));
            $scopeCtx0 = (string)($profile['scope'] ?? '');
            $nationalPolicy = (new \App\Service\NationalPolicy())->decide([
                'country' => $countryCtx0,
                'scope' => $scopeCtx0,
                'operator' => (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')),
                'product' => (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')),
            ]);
        } catch (\Throwable $e) { $nationalPolicy = null; }

        // Detect Season/Period pass mode from TRIN 3 ticket type (fare_flex_type === 'pass')
        $seasonMode = false;
        try {
            $fft = strtolower((string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? '')));
            $seasonMode = ($fft === 'pass');
        } catch (\Throwable $e) { $seasonMode = false; }

    $delayAtFinal = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
    $bandAuto = $delayAtFinal >= 120 ? '50' : ($delayAtFinal >= 60 ? '25' : '0');
    // Apply lenient national band auto if policy provides earlier threshold (e.g., FR G30 ≥30 → 25%)
    if (is_array($nationalPolicy) && !empty($nationalPolicy['thresholds'])) {
        try {
            $thr = (array)$nationalPolicy['thresholds'];
            $thr25 = isset($thr['25']) ? (int)$thr['25'] : null;
            if ($thr25 !== null && $delayAtFinal >= $thr25 && $delayAtFinal < 60) {
                $bandAuto = '25';
            }
        } catch (\Throwable $e) { /* ignore */ }
    }
    // Allow non-persistent band override via query (?band=25) for instant preview
    $bandQ = (string)($this->request->getQuery('band') ?? '');
    if (!in_array($bandQ, ['','25','50'], true)) { $bandQ = ''; }
    $selectedBand = $bandQ !== '' || $bandQ === '' && $this->request->getQuery('band') !== null
        ? $bandQ
        : (string)($form['compensationBand'] ?? ($bandAuto === '0' ? '' : $bandAuto));
        // If still no selection and EU is <60 but national policy allows 25% earlier, default to 25 for preview
        if ($selectedBand === '' && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds'])) {
            try { $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : null; } catch (\Throwable $e) { $thr25 = null; }
            if ($thr25 !== null && $delayAtFinal >= $thr25) { $selectedBand = '25'; }
        }
        // Only gate compensation when refusion (refund) is explicitly valgt; et "nej"/"ved ikke" til refund_requested skal ikke trigge.
        $refundChosen = (string)($form['remedyChoice'] ?? '') === 'refund_return';
        $preinformed = ((string)($form['preinformed_disruption'] ?? '') === 'yes') || (bool)($compute['knownDelayBeforePurchase'] ?? false);
        $rerouteUnder60 = ($delayAtFinal > 0 && $delayAtFinal < 60) && (
            (bool)($this->truthy($form['reroute_same_conditions_soonest'] ?? '')) ||
            (bool)($this->truthy($form['reroute_later_at_choice'] ?? ''))
        );
        $art19Allowed = (bool)($profile['articles']['art19'] ?? true);
        if ((string)($form['remedyChoice'] ?? '') === 'refund_return') {
            $art19Allowed = false;
        }

        // Season pass logging and summary (Art. 19(2)): log even if normal Art.19(1) is gated out
        $seasonSummary = null;
        if ($seasonMode) {
            try {
                $opName = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
                $cancelled = (string)($incident['main'] ?? '') === 'cancellation';
                (new \App\Service\SeasonPassAccumulator($session))->addIncident([
                    'date' => date('Y-m-d'),
                    'minutes' => $delayAtFinal,
                    'cancelled' => $cancelled,
                    'operator' => $opName,
                ]);
                $seasonSummary = (new \App\Service\SeasonPassAccumulator($session))->summarize();
            } catch (\Throwable $e) { $seasonSummary = null; }
        }

        // Live breakdown preview using ClaimCalculator
        try {
            $currency = 'EUR';
            $explicitCurrency = null;
            // Prefer explicit currency from journey/form/auto if available (e.g. LLM parse in TRIN 3)
            foreach ([
                (string)($journey['ticketPrice']['currency'] ?? ''),
                (string)($form['price_currency'] ?? ''),
                (string)($meta['_auto']['price_currency']['value'] ?? ''),
                (string)($meta['_auto']['price']['currency'] ?? ''),
            ] as $c) {
                $cNorm = strtoupper(trim($c));
                if ($cNorm !== '' && preg_match('/^(EUR|DKK|SEK|BGN|CZK|HUF|PLN|RON)$/', $cNorm)) { $explicitCurrency = $cNorm; break; }
            }
            $priceRaw = (string)($journey['ticketPrice']['value'] ?? ($form['price'] ?? ($meta['_auto']['price']['value'] ?? '0 EUR')));
            // Extend symbol detection (non-euro EU currencies)
            $symMap = ['KČ'=>'CZK','Kč'=>'CZK','ZŁ'=>'PLN','zł'=>'PLN','FT'=>'HUF','Ft'=>'HUF','LEI'=>'RON','лв'=>'BGN'];
            foreach ($symMap as $sym => $iso) { if (stripos($priceRaw, $sym) !== false && !preg_match('/\b'.$iso.'\b/i', $priceRaw)) { $priceRaw .= ' ' . $iso; break; } }
            // Use explicit currency if we captured it upstream (takes precedence over symbol parsing)
            if ($explicitCurrency !== null) {
                $currency = $explicitCurrency;
            } elseif (preg_match('/\b(BGN|CZK|DKK|HUF|PLN|RON|SEK|EUR)\b/i', $priceRaw, $mm)) {
                $currency = strtoupper($mm[1]);
            } elseif ($currency === 'EUR' && preg_match('/\bkr\b/i', $priceRaw)) {
                $opCountry = strtoupper((string)(
                    $journey['country']['value'] ??
                    ($meta['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))
                ));
                if (in_array($opCountry, ['DK','SE'], true)) { $currency = $opCountry === 'DK' ? 'DKK' : 'SEK'; }
                else { $currency = 'DKK'; } // default to DKK if kr not disambiguated
            }
            if ($currency === 'EUR') {
                $opCountry = strtoupper((string)(
                    $journey['country']['value'] ??
                    ($meta['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))
                ));
                $nonEuro = ['BG'=>'BGN','CZ'=>'CZK','DK'=>'DKK','HU'=>'HUF','PL'=>'PLN','RO'=>'RON','SE'=>'SEK'];
                if (isset($nonEuro[$opCountry])) { $currency = $nonEuro[$opCountry]; }
            }
            $ticketPriceAmount = (float)preg_replace('/[^0-9.]/', '', $priceRaw);
            $delayForClaim = (int)($form['delayAtFinalMinutes'] ?? ($compute['delayMinEU'] ?? 0));
            $extraordinary = (bool)($compute['extraordinary'] ?? false);
            $exc = strtolower((string)($form['operatorExceptionalCircumstances'] ?? ''));
            if (in_array($exc, ['yes','ja','1','true'], true)) { $extraordinary = true; }
            $extraordinaryType = (string)($form['operatorExceptionalType'] ?? '');
            // Trip basis from Art. 12 and segments
            $art12 = null; try { $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, $meta); } catch (\Throwable $e) { $art12 = null; }
            $throughTicket = (bool)($art12['art12_applies'] ?? true);
            $segSrc = (array)($meta['_segments_auto'] ?? ($journey['segments'] ?? []));
            $legs = [];
            $euSet = ['AT'=>1,'BE'=>1,'BG'=>1,'HR'=>1,'CY'=>1,'CZ'=>1,'DK'=>1,'EE'=>1,'FI'=>1,'FR'=>1,'DE'=>1,'GR'=>1,'HU'=>1,'IE'=>1,'IT'=>1,'LV'=>1,'LT'=>1,'LU'=>1,'MT'=>1,'NL'=>1,'PL'=>1,'PT'=>1,'RO'=>1,'SK'=>1,'SI'=>1,'ES'=>1,'SE'=>1];
            $journeyCountry = strtoupper((string)($journey['country']['value'] ?? ''));
            foreach ($segSrc as $s) {
                $depDate = (string)($s['depDate'] ?? '');
                $arrDate = (string)($s['arrDate'] ?? $depDate);
                $arrTime = (string)($s['schedArr'] ?? '');
                $scheduledArr = '';
                if ($arrDate !== '' && $arrTime !== '') { $scheduledArr = $arrDate . 'T' . str_replace(' ', '', $arrTime); }
                $legs[] = [
                    'operator' => (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')),
                    'product' => (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')),
                    'country' => $journeyCountry !== '' ? $journeyCountry : (string)($s['country'] ?? ''),
                    'scheduled_arr' => $scheduledArr,
                    'actual_arr' => null, // not available per leg in TRIN 6 UI
                    'eu' => $journeyCountry !== '' ? isset($euSet[$journeyCountry]) : null,
                ];
            }
            $delayedLegIndex = max(0, count($legs) > 0 ? (count($legs) - 1) : 0);
            // Derive liable party note for UI (operator vs retailer) per Art. 12(3)/(4) and defaults
            $liableParty = null; $liableBasis = null;
            $art12Liable = (string)($art12['liable_party'] ?? 'unknown');
            $isArt124Retailer = !empty($art12) && ($art12['art12_applies'] ?? false) === true && $art12Liable === 'agency';
            if (!empty($art12) && ($art12['art12_applies'] ?? false) === true) {
                if ($art12Liable === 'operator') { $liableParty = 'operator'; $liableBasis = 'Art. 12(3)'; }
                elseif ($art12Liable === 'agency') { $liableParty = 'retailer'; $liableBasis = 'Art. 12(4)'; }
                else { $liableParty = 'operator'; $liableBasis = 'Art. 12 – sælger ukendt, antager operatør'; }
            } else {
                // Art. 12 OFF: default operator
                if (count($legs) <= 1) { $liableParty = 'operator'; $liableBasis = 'Single-leg default'; }
                else { $liableParty = 'operator'; $liableBasis = 'Separate contracts (multi-leg): operatør for berørt stræk'; }
            }
            // Prefer TRIN 5 self-paid fields when present; fallback to legacy expense_* keys
            $num = function($v){ return is_numeric($v) ? (float)$v : (float)preg_replace('/[^0-9.]/','', (string)$v); };
            $pick = function(array $keys) use ($form, $num){
                foreach ($keys as $k) { if (isset($form[$k]) && $form[$k] !== '' && $form[$k] !== null) { return $num($form[$k]); } }
                return 0.0;
            };
            $mealsAmt = $pick(['meal_self_paid_amount','expense_breakdown_meals','expense_meals']);
            $hotelAmt = $pick(['hotel_self_paid_amount','expense_hotel']);
            // Alt transport: include both alt_self_paid_amount and blocked_self_paid_amount as relevant buckets
            $altAmt = $pick(['alt_self_paid_amount','expense_breakdown_local_transport','expense_alt_transport']);
            $blockedAltAmt = $pick(['blocked_self_paid_amount']);
            $otherAmt = $pick(['expense_breakdown_other_amounts','expense_other']);
            // Currency-aware conversion per udgiftskategori (TRIN 5) -> billetvaluta
            $fx = ['EUR'=>1.0,'DKK'=>7.45,'SEK'=>11.0,'BGN'=>1.96,'CZK'=>25.0,'HUF'=>385.0,'PLN'=>4.35,'RON'=>4.95];
            $fxConv = function(float $amt, string $from, string $to) use (&$fx): ?float {
                $from = strtoupper(trim($from)); $to = strtoupper(trim($to));
                if (!isset($fx[$from]) || !isset($fx[$to]) || $fx[$from] <= 0 || $fx[$to] <= 0) return null;
                $eur = $amt / $fx[$from];
                return $eur * $fx[$to];
            };
            $conv = function(float $amt, string $fromCur, string $toCur) use ($fxConv){
                if ($fromCur === '' || $toCur === '' || $fromCur === $toCur) return $amt;
                $c = $fxConv($amt, $fromCur, $toCur);
                return $c !== null ? $c : $amt;
            };
            $mealsCur = strtoupper((string)($form['meal_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $currency)));
            $hotelCur = strtoupper((string)($form['hotel_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $currency)));
            $altCur = strtoupper((string)($form['alt_self_paid_currency'] ?? $form['blocked_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $currency)));
            $otherCur = strtoupper((string)($form['expense_breakdown_currency'] ?? $currency));
            $expensesIn = [
                'meals' => $conv($mealsAmt, $mealsCur, $currency),
                'hotel' => $conv($hotelAmt, $hotelCur, $currency),
                'alt_transport' => $conv(max(0.0, $altAmt + $blockedAltAmt), $altCur, $currency),
                'other' => $conv($otherAmt, $otherCur, $currency),
            ];
            // Convert expenses from TRIN 5 currency to ticket currency if needed (static FX)
            try {
                $fx = ['EUR'=>1.0,'DKK'=>7.45,'SEK'=>11.0,'BGN'=>1.96,'CZK'=>25.0,'HUF'=>385.0,'PLN'=>4.35,'RON'=>4.95];
                $fxConv = function(float $amt, string $from, string $to) use (&$fx): ?float {
                    $from = strtoupper(trim($from)); $to = strtoupper(trim($to));
                    if (!isset($fx[$from]) || !isset($fx[$to]) || $fx[$from] <= 0 || $fx[$to] <= 0) return null;
                    $eur = $amt / $fx[$from];
                    return $eur * $fx[$to];
                };
                $expenseCur = strtoupper((string)($form['expense_breakdown_currency'] ?? $form['expense_currency'] ?? $currency));
                $targetCur = $currency;
                if ($expenseCur !== '' && $targetCur !== '' && $expenseCur !== $targetCur) {
                    foreach ($expensesIn as $k => $v) {
                        $conv = $fxConv((float)$v, $expenseCur, $targetCur);
                        if ($conv !== null) { $expensesIn[$k] = $conv; }
                    }
                }
            } catch (\Throwable $e) { /* ignore FX errors */ }
            // Art. 12(4) billetudsteder hæfter: 100 % refund + 75 % komp af transaktionsbeløb
            if ($isArt124Retailer) {
                $baseAmount = max(0.0, $ticketPriceAmount);
                $refundAmt = $baseAmount;
                $compAmt = round($baseAmount * 0.75, 2);
                $expensesTotal = array_sum(array_map('floatval', $expensesIn));
                $claim = [
                    'flags' => ['retailer_75' => true],
                    'breakdown' => [
                        'compensation' => [
                            'eligible' => true,
                            'amount' => $compAmt,
                            'currency' => $currency,
                            'pct' => 75,
                            'basis' => 'Art. 12(4) billetudsteder: 75% af transaktionsbeløb',
                            'regime' => 'EU_ART_12_4',
                        ],
                        'refund' => [
                            'amount' => $refundAmt,
                            'currency' => $currency,
                            'pct' => 100,
                            'basis' => 'Art. 12(4) billetudsteder: fuld refundering af transaktionsbeløb',
                        ],
                        'expenses' => [
                            'amount' => $expensesTotal,
                            'currency' => $currency,
                            'basis' => 'Assistance/udlæg (konverteret til billetvaluta)',
                        ],
                    ],
                    'totals' => [
                        'gross_claim' => round($refundAmt + $compAmt + $expensesTotal, 2),
                        'currency' => $currency,
                    ],
                ];
                $liableParty = 'retailer';
                $liableBasis = 'Art. 12(4)';
            } else {
                $claim = (new \App\Service\ClaimCalculator())->calculate([
                    'country_code' => (string)($journey['country']['value'] ?? 'EU'),
                    'currency' => $currency,
                    'ticket_price_total' => $ticketPriceAmount,
                    'trip' => [
                        'through_ticket' => $throughTicket,
                        'trip_type' => (count($legs) === 2 ? 'return' : ($form['trip_type'] ?? null)),
                        'legs' => $legs,
                        'liable_party' => $liableParty,
                    ],
                    'disruption' => [
                        'delay_minutes_final' => $delayForClaim,
                        'eu_only' => (bool)($compute['euOnly'] ?? true),
                        'notified_before_purchase' => (bool)($compute['knownDelayBeforePurchase'] ?? false),
                        'extraordinary' => $extraordinary,
                        'extraordinary_type' => $extraordinaryType,
                        'self_inflicted' => false,
                        'delayed_leg_index' => $delayedLegIndex,
                        'missed_connection' => !empty($incident['missed']),
                    ],
                    'choices' => [
                        'wants_refund' => ((string)($form['remedyChoice'] ?? '') === 'refund_return'),
                        'wants_reroute_same_soonest' => ((string)($form['remedyChoice'] ?? '') === 'reroute_soonest'),
                        'wants_reroute_later_choice' => ((string)($form['remedyChoice'] ?? '') === 'reroute_later'),
                    ],
                    'expenses' => $expensesIn,
                    'already_refunded' => 0,
                    'service_fee_mode' => 'expenses_only',
                    // If user selected a band, honor it for preview by overriding compensation pct
                    'override_comp_pct' => ($selectedBand === '25' || $selectedBand === '50') ? (int)$selectedBand : null,
                    'apply_min_threshold' => (bool)$this->truthy($form['minThresholdApplies'] ?? ''),
                ]);
            }
        } catch (\Throwable $e) { $claim = null; $liableParty = null; $liableBasis = null; }

        // Decide recommended form (EU vs national) using resolver and uploaded matrix
        try {
            $resolver = new \App\Service\FormResolver();
            $formDecision = $resolver->decide([
                'country' => $journeyCountry ?? '',
                'operator' => (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')),
                'product' => (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')),
            ]);
        } catch (\Throwable $e) { $formDecision = ['form' => 'eu_standard_claim', 'reason' => 'Resolver error; default to EU']; }

        $nationalCountryCode = strtolower((string)($journeyCountry ?? ''));
        $this->set(compact('form','compute','incident','profile','delayAtFinal','bandAuto','refundChosen','preinformed','rerouteUnder60','art19Allowed','claim','selectedBand','ticketPriceAmount','currency','liableParty','liableBasis','seasonMode','seasonSummary','nationalCountryCode','formDecision','meta','nationalPolicy'));
        return null;
    }
    /**
     * Split-step: Extras (TRIN 7–10 purchase channel, PMR, expenses)
     */
    public function extras(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            foreach (['purchaseChannel','pmrUser','assistancePromised','assistanceDelivered','expense_meals','expense_hotel','expense_alt_transport','expense_other'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'applicant']);
        }
        $this->set(compact('form'));
        return null;
    }

    /**
     * Split-step: Applicant & payout (TRIN 11)
     */
    public function applicant(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            $keys = ['firstName','lastName','address_street','address_no','address_country','address_postalCode','address_city','contact_email','contact_phone','payoutPreference','iban','bic','accountHolderName','otherPaymentUsed'];
            foreach ($keys as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'consent']);
        }
        $this->set(compact('form'));
        return null;
    }

    /**
     * Split-step: Consent & additional info (TRIN 12)
     */
    public function consent(): \Cake\Http\Response|null
    {
        $session = $this->request->getSession();
        $form = (array)$session->read('flow.form') ?: [];
        if ($this->request->is('post')) {
            foreach (['gdprConsent','additionalInfo'] as $k) {
                $v = $this->request->getData($k);
                if ($v !== null && $v !== '') { $form[$k] = is_string($v) ? $v : (string)$v; }
            }
            $session->write('flow.form', $form);
            return $this->redirect(['action' => 'summary']);
        }
        $gdpr_ok = isset($form['gdprConsent']) ? (bool)$this->truthy($form['gdprConsent']) : false;
        $this->set(compact('form','gdpr_ok'));
        return null;
    }

    /**
     * Build price-hint context from TRIN 3 data (country/station/train/currency/time).
     *
     * @param array $form
     * @param array $journey
     * @param array $meta
     * @return array{countryCode:string,stationType:string,trainType:string,arrivalLocalHour:int,currencyOverride?:string}
     */
    private function buildPriceContextForHints(array $form, array $journey, array $meta): array
    {
        $country = strtoupper((string)(
            $form['operator_country'] ??
            ($journey['country']['value'] ?? '') ??
            ($meta['_auto']['operator_country']['value'] ?? '')
        ));
        if ($country === '') { $country = 'DE'; }

        $station = (string)($form['arr_station'] ?? ($journey['arrival']['station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
        $stationType = $this->inferStationTypeForHints($station);

        $trainNo = (string)($form['train_no'] ?? ($journey['trainNo'] ?? ($meta['_auto']['train_no']['value'] ?? '')));
        $product = (string)($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''));
        $trainType = $this->inferTrainTypeForHints($trainNo, $product);

        $arrivalTime = (string)($form['actual_arrival_time'] ?? ($form['arr_time'] ?? ($journey['arrival']['time'] ?? ($meta['_auto']['arr_time']['value'] ?? ''))));
        $arrivalHour = $this->parseHourForHints($arrivalTime);

        $currencyOverride = (string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? ''));
        if (strcasecmp($currencyOverride, 'auto') === 0) { $currencyOverride = ''; }

        return [
            'countryCode' => $country,
            'stationType' => $stationType,
            'trainType' => $trainType,
            'arrivalLocalHour' => $arrivalHour,
            'currencyOverride' => $currencyOverride !== '' ? strtoupper($currencyOverride) : null,
        ];
    }

    private function inferStationTypeForHints(string $stationName): string
    {
        $s = mb_strtolower(trim($stationName), 'UTF-8');
        if ($s === '') { return 'REGIONAL'; }
        $metroKeywords = ['hovedbanegård','hauptbahnhof','central','gare du nord','gare de lyon','central station','centrale','hb'];
        foreach ($metroKeywords as $m) { if (mb_stripos($s, $m, 0, 'UTF-8') !== false) { return 'METRO'; } }
        $ruralKeywords = ['landevej','halt','dorf','village','by'];
        foreach ($ruralKeywords as $r) { if (mb_stripos($s, $r, 0, 'UTF-8') !== false) { return 'RURAL'; } }
        return 'REGIONAL';
    }

    private function inferTrainTypeForHints(string $trainNo, string $product): string
    {
        $t = strtoupper($trainNo);
        $p = strtoupper($product);
        if (preg_match('/(ICE|TGV|RJ|RJX|LYN|AV|AVE|FR|ES)/', $t) || preg_match('/(HIGH|FRECCIAROSSA|AVE|TGV|ICE|RJ)/', $p)) {
            return 'HIGHSPEED';
        }
        if (preg_match('/(IC|EC|IR)/', $t) || preg_match('/INTERCITY|IC|EC/', $p)) {
            return 'INTERCITY';
        }
        return 'REGIONAL';
    }

    private function parseHourForHints(string $hhmm): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) {
            $h = (int)$m[1];
            if ($h >= 0 && $h <= 23) { return $h; }
        }
        return 12;
    }

    /**
     * Load FX rates (EUR base) for price hints. Uses cached TMP/rates.json if fresh, else frankfurter, else static.
     *
     * @return array<string,float>
     */
    private function loadFxRatesForHints(): array
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }
        $fx = [
            'EUR' => 1.0,
            'DKK' => 7.45,
            'SEK' => 11.0,
            'BGN' => 1.96,
            'CZK' => 25.0,
            'HUF' => 385.0,
            'PLN' => 4.35,
            'RON' => 4.95,
            'CHF' => 0.95,
            'GBP' => 0.85,
        ];
        $fxCachePath = TMP . 'rates.json';
        try {
            $useCache = false;
            if (is_file($fxCachePath)) {
                $age = time() - filemtime($fxCachePath);
                if ($age < 22 * 3600) { $useCache = true; }
            }
            if ($useCache) {
                $data = json_decode((string)file_get_contents($fxCachePath), true);
                if (is_array($data) && !empty($data['rates'])) { $fx = $data['rates']; }
            } else {
                $api = 'https://api.frankfurter.app/latest?base=EUR&symbols=DKK,SEK,BGN,CZK,HUF,PLN,RON,CHF,GBP';
                $resp = @file_get_contents($api);
                $json = json_decode((string)$resp, true);
                if (is_array($json) && !empty($json['rates'])) {
                    $fx = array_merge(['EUR' => 1.0], $json['rates']);
                    @file_put_contents($fxCachePath, json_encode(['ts' => time(), 'rates' => $fx]));
                }
            }
        } catch (\Throwable $e) { /* keep fallback */ }
        $cache = $fx;
        return $fx;
    }

    /**
     * Normalize various truthy values coming from HTML forms.
     */
    private function truthy(mixed $v): bool
    {
        if (is_bool($v)) { return $v; }
        $s = is_string($v) ? strtolower(trim($v)) : '';
        return in_array($s, ['1','true','on','yes','ja','y'], true);
    }

    /**
     * Heuristic auto-computation of downgrade segment share.
     * - Prefer distance_km when available; else use scheduled time (minutes).
     * - If a missed-connection station is provided, consider legs AFTER that station as affected.
     * - Otherwise assume whole journey potentially affected (share 1.0) with low confidence.
     * @param array<int,array<string,mixed>> $segments
     * @return array{share:float,basis:string,confidence:float}
     */
    private function computeDowngradeSegmentShareAuto(array $segments, string $missedStation = ''): array
    {
        $norm = function($s){ return trim(mb_strtolower((string)$s, 'UTF-8')); };
        $targetIdx = null;
        if ($missedStation !== '') {
            $ms = $norm($missedStation);
            foreach ($segments as $i => $seg) {
                $to = $norm($seg['to'] ?? '');
                if ($to !== '' && $to === $ms) { $targetIdx = $i; break; }
            }
        }
        $getMin = function(string $hhmm): ?int { if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) return null; $h=(int)$m[1]; $mi=(int)$m[2]; return $h*60+$mi; };
        $totalDist = 0.0; $affectedDist = 0.0; $haveDist = false;
        $totalMin = 0.0; $affectedMin = 0.0;
        foreach ($segments as $i => $seg) {
            $dist = isset($seg['distance_km']) && is_numeric($seg['distance_km']) ? (float)$seg['distance_km'] : null;
            if ($dist !== null) { $haveDist = true; $totalDist += $dist; }
            $sd = isset($seg['schedDep']) ? $getMin((string)$seg['schedDep']) : null;
            $sa = isset($seg['schedArr']) ? $getMin((string)$seg['schedArr']) : null;
            $dur = null;
            if ($sd !== null && $sa !== null) { $dur = $sa - $sd; if ($dur < 0) { $dur += 24*60; } }
            if ($dur !== null) { $totalMin += max(0, (float)$dur); }
            $isAffected = ($targetIdx !== null) ? ($i > $targetIdx) : true;
            if ($isAffected) {
                if ($dist !== null) { $affectedDist += $dist; }
                if ($dur !== null) { $affectedMin += max(0, (float)$dur); }
            }
        }
        if ($haveDist && $totalDist > 0.0) {
            return ['share' => $affectedDist / $totalDist, 'basis' => 'distance', 'confidence' => 0.85];
        }
        if ($totalMin > 0.0) {
            // Lower confidence if we didn't have a missed-connection anchor
            $conf = ($targetIdx !== null) ? 0.75 : 0.5;
            return ['share' => $affectedMin / $totalMin, 'basis' => 'time', 'confidence' => $conf];
        }
        return ['share' => 1.0, 'basis' => 'unknown', 'confidence' => 0.3];
    }
}

?>
