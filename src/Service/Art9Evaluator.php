<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Evaluates Article 9 related information duties and fallbacks.
 *
 * Backwards compatible fields retained:
 *  - hooks (now superset)
 *  - art9_ok
 *
 * New (granular) fields after Phase 2 expansion:
 *  - parts: { art9_1_ok, art9_2_ok, art9_3_ok }
 *  - ui_banners (optional, currently unused placeholder)
 *
 * Supports selective exemptions via ExemptionProfileBuilder->articles_sub.
 * Test support: meta key '_profile_override' can inject a pre-built profile to avoid
 * dependency complexity.
 */
class Art9Evaluator
{
    /**
     * Central registry of Art.9 hooks with their part mapping.
     * Values are one of: 'art9_1' | 'art9_2' | 'art9_3'.
     * Note: Values are treated nominally (Ja/Nej/unknown/Delvist). Only 'Nej' yields explicit false.
     */
    private const HOOK_DEFINITIONS = [
        // 9(1) – Pre-contract general info (Bilag II del 1)
        'info_before_purchase' => 'art9_1',
        'language_accessible' => 'art9_1',
        'accessibility_format' => 'art9_1',
        'multi_channel_information' => 'art9_1',
        'accessible_formats_offered' => 'art9_1',
        // CoC / CIV
        'coc_acknowledged' => 'art9_1',
        'coc_evidence_upload' => 'art9_1',
        'civ_marking_present' => 'art9_1',
        // Schedules / fastest / alternatives
        'fastest_flag_at_purchase' => 'art9_1',
        'mct_realistic' => 'art9_1',
        'alts_shown_precontract' => 'art9_1',
        // Fares / cheapest
        'multiple_fares_shown' => 'art9_1',
        'cheapest_highlighted' => 'art9_1',
        'fare_flex_type' => 'art9_1',
        'train_specificity' => 'art9_1',
        // PMR pre-contract
        'pmr_user' => 'art9_1',
        'pmr_booked' => 'art9_1',
        'pmr_promised_missing' => 'art9_1',
        // Bike pre-contract
        'bike_reservation_type' => 'art9_1',
        'bike_res_required' => 'art9_1',
        // Class / seat pre-contract
        'fare_class_purchased' => 'art9_1',
        'berth_seat_type' => 'art9_1',
        // Facilities promised
        'promised_facilities' => 'art9_1',
    // Pre-informed disruptions (links to Art.19(9))
    'preinformed_disruption' => 'art9_1',
        // Through ticket disclosure (also in Art12)
        'through_ticket_disclosure' => 'art9_1',
        'single_txn_operator' => 'art9_1',
        'single_txn_retailer' => 'art9_1',
        'separate_contract_notice' => 'art9_1',

        // 9(2) – Rights information & complaints
        'info_on_rights' => 'art9_2',
        'rights_notice_displayed' => 'art9_2',
        'rights_contact_provided' => 'art9_2',
        // Complaints
        'complaint_channel_seen' => 'art9_2',
        'complaint_already_filed' => 'art9_2',
        'complaint_receipt_upload' => 'art9_2',
        'submit_via_official_channel' => 'art9_2',

        // 9(3) – During disruption / delivery
        'info_during_disruption' => 'art9_3',
        'station_board_updates' => 'art9_3',
        'onboard_announcements' => 'art9_3',
        'disruption_updates_frequency' => 'art9_3',
        'assistance_contact_visible' => 'art9_3',
        // Realtime info consumption
        'realtime_info_seen' => 'art9_3',
        // PMR delivery
        'pmr_delivered_status' => 'art9_3',
        // Bike delivery / outcomes
        'bike_denied_reason' => 'art9_3',
        'bike_followup_offer' => 'art9_3',
        'bike_delay_bucket' => 'art9_3',
        // Facilities delivery
        'facilities_delivered_status' => 'art9_3',
        'facility_impact_note' => 'art9_3',
        // Class / reserved amenity delivery
        'class_delivered_status' => 'art9_3',
        'reserved_amenity_delivered' => 'art9_3',
    ];
    /**
     * @param array $journey Minimal keys used: segments[], country?; free-form fields accepted safely.
     * @param array $meta Keys considered (all optional). Values are typically one of:
     *  - "Ja" | "Nej" | "Delvist" | "unknown"
     *  Core (legacy) hooks retained:
     *    info_before_purchase, info_on_rights, info_during_disruption, language_accessible, accessibility_format
     *  Expanded hooks (Phase 2):
     *    multi_channel_information, accessible_formats_offered,
     *    rights_notice_displayed, rights_contact_provided,
     *    station_board_updates, onboard_announcements, disruption_updates_frequency, assistance_contact_visible
     *  Special meta keys:
     *    _profile_override (array) For tests: inject pre-built profile (must match ExemptionProfileBuilder schema)
     *
    * @return array{
    *   hooks: array<string,string>,
    *   auto: array<string,array{value:mixed,source?:string}>,
    *   mismatches: array<string,array{expected:mixed,actual:mixed,source?:string,part:string}>,
    *   parts: array{art9_1_ok: bool|null, art9_2_ok: bool|null, art9_3_ok: bool|null},
    *   missing: string[],
    *   ask_hooks: string[],
    *   art9_ok: bool|null,
    *   reasoning: string[],
    *   fallback_recommended: string[],
    *   ui_banners: string[]
    * }
     */
    public function evaluate(array $journey, array $meta = []): array
    {
        // Flatten any flow-chart style nested structures (e.g., art9_precontract_ext)
        $meta = $this->normalizeMeta($meta);

        $profile = isset($meta['_profile_override']) && is_array($meta['_profile_override'])
            ? $meta['_profile_override']
            : (new ExemptionProfileBuilder())->build($journey);

        // Unified hook collection from registry (legacy + extended)
        $hookKeys = array_keys(self::HOOK_DEFINITIONS);
        $hooks = [];
        foreach ($hookKeys as $k) {
            $hooks[$k] = $meta[$k] ?? 'unknown';
        }

        // Derive parent hooks from sub-hooks before any evaluation
        // This turns unknown parent values into Ja/Nej when sub-hooks give a clear signal
        $subsFlags = ($profile['articles_sub'] ?? ['art9_1' => true, 'art9_2' => true, 'art9_3' => true]);
        $hooks = $this->deriveParentHooks($hooks, $subsFlags);

        $reasoning = [];
        $fallbacks = [];
        $uiBanners = [];
        $auto = $this->collectAuto($meta);

        // If Art. 9 is exempt in this context, short-circuit to null/false accordingly
        $art9Applicable = $profile['articles']['art9'] ?? true;
        $subs = $profile['articles_sub'] ?? ['art9_1' => true, 'art9_2' => true, 'art9_3' => true];
        $partsStatus = ['art9_1_ok' => null, 'art9_2_ok' => null, 'art9_3_ok' => null];

        if ($art9Applicable === false) {
            return [
                'hooks' => $hooks,
                'auto' => $auto,
                'mismatches' => [],
                'parts' => $partsStatus, // all null when fully exempt
                'missing' => $this->missingHooks($hooks, $subs, fullyExempt: true),
                'ask_hooks' => [],
                'art9_ok' => null,
                'reasoning' => ['Art. 9 undtaget ifølge fritagelsesprofil.'],
                'fallback_recommended' => ['show_basic_rights_link'],
                'ui_banners' => ['Art. 9 undtaget (profil)'],
            ];
        }

        // Partition hooks into parts
        // - statusPartMap: baseline subset for backward-compatible part status (legacy behavior)
        // - registryPartMap: full set (for ask_hooks/missing/fallbacks)
        $statusPartMap = $this->baselinePartMap();
        $registryPartMap = $this->buildPartMap();

        $anyFalse = false; $allTrue = true; // Over non-exempt parts only

        foreach ($statusPartMap as $partKey => $partHooks) {
            $exempt = ($subs[$partKey] ?? true) === false; // part exempt
            if ($exempt) { $partsStatus[$partKey . '_ok'] = null; continue; }
            $explicitNo = false; $unknown = false;
            foreach ($partHooks as $hk) {
                $val = $hooks[$hk];
                if ($val === 'Nej') { $explicitNo = true; $reasoning[] = $hk . ' mangler.'; }
                if ($val === 'unknown') { $unknown = true; }
            }
            $status = null;
            if ($explicitNo) { $status = false; }
            elseif (!$unknown) { $status = true; }
            $partsStatus[$partKey . '_ok'] = $status;
            if ($status === false) { $anyFalse = true; }
            if ($status !== true) { $allTrue = false; }
        }

        $art9Ok = null;
        if ($anyFalse) { $art9Ok = false; }
        elseif ($allTrue) { $art9Ok = true; }

        // Fallbacks targeted (only for non-exempt parts)
        $fallbackMap = [
            'info_on_rights' => 'show_basic_rights_link',
            'rights_notice_displayed' => 'show_basic_rights_link',
            'rights_contact_provided' => 'prompt_contact_point',
            'info_during_disruption' => 'ask_upload_notifications',
            'station_board_updates' => 'ask_upload_notifications',
            'onboard_announcements' => 'ask_upload_notifications',
            'disruption_updates_frequency' => 'encourage_frequency_standard',
            'assistance_contact_visible' => 'prompt_assistance_contact',
            'language_accessible' => 'offer_language_toggle',
            'accessibility_format' => 'offer_plain_text_and_pdf',
            'accessible_formats_offered' => 'offer_plain_text_and_pdf',
            'multi_channel_information' => 'offer_multi_channel',
            // New fallbacks for extended hooks
            'coc_acknowledged' => 'show_coc_link',
            'coc_evidence_upload' => 'prompt_coc_upload',
            'civ_marking_present' => 'check_ticket_markings',
            'fastest_flag_at_purchase' => 'explain_fastest_journey_basis',
            'alts_shown_precontract' => 'explain_alternatives_basis',
            'multiple_fares_shown' => 'explain_fares_basics',
            'cheapest_highlighted' => 'explain_fares_basics',
            'fare_flex_type' => 'collect_fare_terms',
            'train_specificity' => 'collect_ticket_scope',
            'pmr_user' => 'offer_assistance_info',
            'pmr_booked' => 'prompt_pmr_evidence',
            'pmr_promised_missing' => 'collect_pmr_promises',
            'pmr_delivered_status' => 'collect_pmr_delivery',
            'bike_reservation_type' => 'show_bike_policy_link',
            'bike_res_required' => 'show_bike_policy_link',
            'bike_denied_reason' => 'offer_bike_refund_help',
            'bike_followup_offer' => 'offer_bike_refund_help',
            'bike_delay_bucket' => 'collect_bike_delay_evidence',
            'fare_class_purchased' => 'collect_downgrade_evidence',
            'class_delivered_status' => 'collect_downgrade_evidence',
            'berth_seat_type' => 'collect_reservation_evidence',
            'reserved_amenity_delivered' => 'collect_reservation_delivery',
            'promised_facilities' => 'collect_facilities_evidence',
            'facilities_delivered_status' => 'collect_facilities_evidence',
            'facility_impact_note' => 'collect_facilities_evidence',
            'realtime_info_seen' => 'ask_upload_notifications',
            'through_ticket_disclosure' => 'explain_contract_structure',
            'single_txn_operator' => 'explain_contract_structure',
            'single_txn_retailer' => 'explain_contract_structure',
            'separate_contract_notice' => 'explain_contract_structure',
            'complaint_channel_seen' => 'offer_complaint_proxy',
            'complaint_already_filed' => 'offer_complaint_proxy',
            'complaint_receipt_upload' => 'offer_complaint_proxy',
            'submit_via_official_channel' => 'offer_complaint_proxy',
        ];
        foreach ($fallbackMap as $hk => $fb) {
            // Skip if hook not in map; skip if part exempt
            $partKey = $this->partForHook($hk, $registryPartMap);
            if (($subs[$partKey] ?? true) === false) { continue; }
            $val = $hooks[$hk] ?? 'unknown';
            if ($val !== 'Ja') { $fallbacks[] = $fb; }
        }

        // Detect mismatches based on provided auto values (if any)
        $mismatches = $this->detectMismatches($hooks, $auto, $subs, $registryPartMap);

        // UI banners (simple first iteration)
        foreach (['art9_1','art9_2','art9_3'] as $pk) {
            if (($subs[$pk] ?? true) === false) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') undtaget.'; continue; }
            $st = $partsStatus[$pk . '_ok'];
            if ($st === false) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') ikke opfyldt.'; }
            elseif ($st === null) { $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') ufuldstændig (ukendt).'; }
            // Mismatch banner per part
            $mm = array_keys(array_filter($mismatches, fn($v) => ($v['part'] ?? '') === $pk));
            if (!empty($mm)) {
                $uiBanners[] = 'Art. 9(' . substr($pk,-1) . ') mismatch: ' . implode(', ', array_slice($mm,0,5)) . (count($mm) > 5 ? ' …' : '');
            }
        }

        return [
            'hooks' => $hooks,
            'auto' => $auto,
            'mismatches' => $mismatches,
            'parts' => $partsStatus,
            'missing' => $this->missingHooks($hooks, $subs),
            'ask_hooks' => $this->askHooks($hooks, $subs, $mismatches),
            'art9_ok' => $art9Ok,
            'reasoning' => $reasoning,
            'fallback_recommended' => array_values(array_unique($fallbacks)),
            'ui_banners' => $uiBanners,
        ];
    }

    /**
     * Normalize meta by flattening nested flow-chart JSON structures and normalizing values to our canonical set.
     * Supports patterns like:
     *  - art9_precontract_ext: { ... nested objects with 'hook' and 'value' or direct hook keys }
     *  - arrays of { hook: string, value|answer: mixed }
     * Values normalization: true/Yes -> 'Ja', false/No -> 'Nej', Unknown/Don't know -> 'unknown'.
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function normalizeMeta(array $meta): array
    {
        $knownHooks = array_keys(self::HOOK_DEFINITIONS);
        $flat = [];
        $walker = function($node) use (&$walker, &$flat, $knownHooks): void {
            if (is_array($node)) {
                // Form: { hook: 'id', value: 'Ja' }
                if (isset($node['hook'])) {
                    $hk = (string)$node['hook'];
                    if (in_array($hk, $knownHooks, true)) {
                        $val = $node['value'] ?? ($node['answer'] ?? null);
                        if ($val !== null) { $flat[$hk] = $this->normalizeAnswer($val); }
                    }
                }
                foreach ($node as $k => $v) {
                    // Direct key match to a known hook
                    if (is_string($k) && in_array($k, $knownHooks, true) && !is_array($v)) {
                        $flat[$k] = $this->normalizeAnswer($v);
                    } else {
                        $walker($v);
                    }
                }
            }
        };
        foreach ($meta as $k => $v) {
            if ($k === 'art9_precontract_ext' || $k === 'art9' || $k === 'art9_flow' || is_int($k)) {
                $walker($v);
            }
        }
        // Only set flattened values if top-level not already provided
        foreach ($flat as $hk => $val) {
            if (!array_key_exists($hk, $meta)) { $meta[$hk] = $val; }
        }
        return $meta;
    }

    /** Normalize various answer formats to canonical 'Ja'|'Nej'|'Delvist'|'unknown' */
    private function normalizeAnswer($val): string
    {
        if (is_bool($val)) { return $val ? 'Ja' : 'Nej'; }
        $s = strtolower(trim((string)$val));
        return match ($s) {
            'ja', 'yes', 'y', 'true', '1' => 'Ja',
            'nej', 'no', 'n', 'false', '0' => 'Nej',
            'delvist', 'partial', 'partly' => 'Delvist',
            'ved ikke', "don't know", 'dont know', 'unknown', '' => 'unknown',
            default => (in_array($s, ['ja','nej','delvist','unknown'], true) ? ucfirst($s) : (string)$val),
        };
    }

    /**
     * Derive the three parent hooks from their respective sub-hooks per part if they are unknown.
     * Rules: if any sub-hook is 'Nej' -> parent = 'Nej'.
     *        else if all considered sub-hooks are known (!= 'unknown') and none 'Nej' -> parent = 'Ja'.
     *        else keep original (unknown or provided value).
     * For 9(1), also treat preinformed_disruption==='Ja' as a weak positive and set 'Delvist' if parent was unknown.
     * Exempt parts are skipped.
     * @param array<string,string> $hooks
     * @param array<string,bool> $subs
     * @return array<string,string>
     */
    private function deriveParentHooks(array $hooks, array $subs): array
    {
        // Part 1: info_before_purchase from key pre-sale sub-hooks
        if (($subs['art9_1'] ?? true) !== false) {
            $parent = $hooks['info_before_purchase'] ?? 'unknown';
            if ($parent === 'unknown') {
                $consider = ['language_accessible','accessibility_format','multi_channel_information','accessible_formats_offered'];
                $parent = $this->deriveFromSet($hooks, $consider, $parent);
                // Weak positive from preinformed_disruption
                if ($parent === 'unknown' && (($hooks['preinformed_disruption'] ?? 'unknown') === 'Ja')) {
                    $parent = 'Delvist';
                }
                // Extended negative signals from other pre-contract hooks (flow chart points 1–10)
                // If any of these are explicitly 'Nej', force parent to 'Nej' (without requiring full knowledge for 'Ja').
                $extendedNegKeys = [
                    // Contract terms / disclosure
                    'coc_acknowledged','coc_evidence_upload','civ_marking_present',
                    // Schedules / alternatives / connection times
                    'fastest_flag_at_purchase','mct_realistic','alts_shown_precontract',
                    // Fares / pricing transparency
                    'multiple_fares_shown','cheapest_highlighted','fare_flex_type',
                    // Ticket scope (train-specific vs flexible)
                    'train_specificity',
                    // Accessibility (PMR) and bookings
                    'pmr_user','pmr_booked','pmr_promised_missing',
                    // Bikes
                    'bike_reservation_type','bike_res_required',
                    // Class/berths and reserved amenities
                    'fare_class_purchased','berth_seat_type',
                    // Promised onboard facilities
                    'promised_facilities',
                    // Through-ticket and separate contracts notices
                    'through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice',
                ];
                foreach ($extendedNegKeys as $k) {
                    $v = $hooks[$k] ?? 'unknown';
                    if ($v === 'Nej') { $parent = 'Nej'; break; }
                }
                $hooks['info_before_purchase'] = $parent;
            }
        }

        // Part 2: info_on_rights from rights_notice_displayed, rights_contact_provided, complaint_channel_seen
        if (($subs['art9_2'] ?? true) !== false) {
            $parent = $hooks['info_on_rights'] ?? 'unknown';
            if ($parent === 'unknown') {
                $consider = ['rights_notice_displayed','rights_contact_provided','complaint_channel_seen'];
                $parent = $this->deriveFromSet($hooks, $consider, $parent);
                $hooks['info_on_rights'] = $parent;
            }
        }

        // Part 3: info_during_disruption from station_board_updates, onboard_announcements, disruption_updates_frequency, assistance_contact_visible, realtime_info_seen
        if (($subs['art9_3'] ?? true) !== false) {
            $parent = $hooks['info_during_disruption'] ?? 'unknown';
            if ($parent === 'unknown') {
                $consider = ['station_board_updates','onboard_announcements','disruption_updates_frequency','assistance_contact_visible','realtime_info_seen'];
                $parent = $this->deriveFromSet($hooks, $consider, $parent);
                // If still unknown, fold in bike delivery signals as a secondary indicator (from flow-chart delivery outcomes)
                if ($parent === 'unknown') {
                    $bikeDenied = strtolower(trim((string)($hooks['bike_denied_reason'] ?? 'unknown')));
                    $bikeDelay = strtolower(trim((string)($hooks['bike_delay_bucket'] ?? 'unknown')));
                    $bikeFollow = strtolower(trim((string)($hooks['bike_followup_offer'] ?? 'unknown')));
                    // If bike denied without follow-up, that suggests disruption info/handling was insufficient → 'Nej'
                    if ($bikeDenied !== 'unknown' && $bikeDenied !== '') {
                        if ($bikeFollow === 'nej' || $bikeFollow === 'unknown' || $bikeFollow === '') { $parent = 'Nej'; }
                        else { $parent = 'Delvist'; }
                    } elseif (in_array($bikeDelay, ['>60','>30','30-60','over 60','over60','over_60'], true)) {
                        // Significant bike-related delay → mark as partial at least
                        $parent = 'Delvist';
                    }
                }
                $hooks['info_during_disruption'] = $parent;
            }
        }

        return $hooks;
    }

    /**
     * Helper to derive a parent value ('Ja'|'Nej'|'unknown'|'Delvist') from a set of hooks using the simple rule.
     * @param array<string,string> $hooks
     * @param string[] $keys
     */
    private function deriveFromSet(array $hooks, array $keys, string $fallback): string
    {
        $anyNo = false; $allKnown = true;
        foreach ($keys as $k) {
            $v = $hooks[$k] ?? 'unknown';
            if ($v === 'Nej') { $anyNo = true; }
            if ($v === 'unknown') { $allKnown = false; }
        }
        if ($anyNo) { return 'Nej'; }
        if ($allKnown) { return 'Ja'; }
        return $fallback; // keep as-is (likely 'unknown')
    }

    /**
     * Compute missing hooks (unknown) for non-exempt parts.
     * @param array<string,string> $hooks
     * @param array<string,bool> $subs
     */
    private function missingHooks(array $hooks, array $subs = [], bool $fullyExempt = false): array
    {
        if ($fullyExempt) { return []; }
        $partMap = $this->buildPartMap();
        $missing = [];
        foreach ($partMap as $pk => $hks) {
            if (($subs[$pk] ?? true) === false) { continue; }
            foreach ($hks as $hk) {
                if (!isset($hooks[$hk]) || $hooks[$hk] === 'unknown') { $missing[] = $hk; }
            }
        }
        return $missing;
    }

    /** @param array<string,string[]> $partMap */
    private function partForHook(string $hook, array $partMap): string
    {
        foreach ($partMap as $pk => $hks) { if (in_array($hook, $hks, true)) { return $pk; } }
        return 'art9_1'; // default fallback
    }

    /** Build part map from registry */
    private function buildPartMap(): array
    {
        $map = ['art9_1' => [], 'art9_2' => [], 'art9_3' => []];
        foreach (self::HOOK_DEFINITIONS as $hook => $part) { $map[$part][] = $hook; }
        return $map;
    }

    /** Baseline subset for part status (backward compatible behavior) */
    private function baselinePartMap(): array
    {
        return [
            'art9_1' => ['info_before_purchase','language_accessible','accessibility_format','multi_channel_information','accessible_formats_offered'],
            'art9_2' => ['info_on_rights','rights_notice_displayed','rights_contact_provided'],
            'art9_3' => ['info_during_disruption','station_board_updates','onboard_announcements','disruption_updates_frequency','assistance_contact_visible'],
        ];
    }

    /**
     * Compute ask_hooks: hooks that should be asked to user (unknown) in non-exempt parts.
     * Future: include mismatch detection when auto-sources exist.
     * @param array<string,string> $hooks
     * @param array<string,bool> $subs
     * @return string[]
     */
    private function askHooks(array $hooks, array $subs, array $mismatches = []): array
    {
        $partMap = $this->buildPartMap();
        $ask = [];
        foreach ($partMap as $pk => $hks) {
            if (($subs[$pk] ?? true) === false) { continue; }
            foreach ($hks as $hk) {
                if (!isset($hooks[$hk]) || $hooks[$hk] === 'unknown' || isset($mismatches[$hk])) { $ask[] = $hk; }
            }
        }
        return $ask;
    }

    /**
     * Collect auto values from meta['_auto'] placeholder.
     * Accepts either scalar or {value,source} arrays.
     * @param array<string,mixed> $meta
     * @return array<string,array{value:mixed,source?:string}>
     */
    private function collectAuto(array $meta): array
    {
        $out = [];
        $raw = isset($meta['_auto']) && is_array($meta['_auto']) ? $meta['_auto'] : [];
        foreach (self::HOOK_DEFINITIONS as $hk => $_) {
            if (!array_key_exists($hk, $raw)) { continue; }
            $val = $raw[$hk];
            if (is_array($val)) {
                $value = $val['value'] ?? null;
                if ($value !== null) { $out[$hk] = ['value' => $value] + (isset($val['source']) ? ['source' => (string)$val['source']] : []); }
            } else {
                $out[$hk] = ['value' => $val, 'source' => 'meta_auto'];
            }
        }
        return $out;
    }

    /**
     * Detect mismatches between provided hooks and auto values for non-exempt parts.
     * @param array<string,string> $hooks
     * @param array<string,array{value:mixed,source?:string}> $auto
     * @param array<string,bool> $subs
     * @param array<string,string[]> $partMap
     * @return array<string,array{expected:mixed,actual:mixed,source?:string,part:string}>
     */
    private function detectMismatches(array $hooks, array $auto, array $subs, array $partMap): array
    {
        $out = [];
        foreach ($auto as $hk => $av) {
            $pk = $this->partForHook($hk, $partMap);
            if (($subs[$pk] ?? true) === false) { continue; }
            if (!array_key_exists($hk, $hooks)) { continue; }
            $actual = $hooks[$hk];
            $expected = $av['value'] ?? null;
            if ($expected === null) { continue; }
            if ($actual === 'unknown') { continue; }
            if ($this->normalizeVal($actual) !== $this->normalizeVal($expected)) {
                $out[$hk] = [
                    'expected' => $expected,
                    'actual' => $actual,
                    'source' => $av['source'] ?? null,
                    'part' => $pk,
                ];
            }
        }
        return $out;
    }

    private function normalizeVal($v): string
    {
        return strtolower(trim((string)$v));
    }
}
