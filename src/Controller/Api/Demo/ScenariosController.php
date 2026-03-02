<?php
declare(strict_types=1);

namespace App\Controller\Api\Demo;

use App\Controller\AppController;
use App\Service\FixtureRepository;
use App\Service\ScenarioRunner;

class ScenariosController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
        // Make demo outputs readable (preserve Danish characters and punctuation)
        $this->viewBuilder()->setOption('jsonOptions', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/demo/scenarios
     * ?withEval=1 to run pipeline
     * ?id=<fixtureId> to filter one
     */
    public function index(): void
    {
        // Support both withEval=1 and eval=1 (alias)
        $withEval = (string)$this->request->getQuery('withEval') === '1'
            || (string)$this->request->getQuery('eval') === '1';
        $id = (string)$this->request->getQuery('id') ?: null;
        $compact = (string)$this->request->getQuery('compact') === '1';
        // Optional: include where wizard fields originated (which TRIN/step set them).
        // Useful for debugging "station" fields across Art.20(3) vs Art.18 flows.
        $withProvenance = (string)$this->request->getQuery('provenance') === '1'
            || (string)$this->request->getQuery('withProvenance') === '1'
            || (string)$this->request->getQuery('debug') === '1';

        $repo = new FixtureRepository();
        $fixtures = $repo->getAll($id);
        $runner = new ScenarioRunner();

        $out = [];
        foreach ($fixtures as $fx) {
            $row = [
                'id' => $fx['id'],
                'version' => $fx['version'] ?? 1,
                'label' => $fx['label'] ?? $fx['id'],
                'tags' => $fx['tags'] ?? [],
                'expected' => $fx['expected'] ?? null,
            ];
            if ($withEval) {
                $eval = $runner->evaluateFixture($fx);
                $actual = $eval['actual'];
                $origin = $withProvenance ? $this->buildWizardFieldOrigin($fx) : null;
                if ($compact) {
                    $row['wizard_compact'] = $this->buildWizardCompact($fx);
                    $row['computeOverrides_compact'] = $this->buildComputeOverridesCompact($fx);
                    $actual = $this->buildActualCompact($actual) + ['_compact' => true];
                }
                if ($withProvenance && is_array($origin)) {
                    $row['wizard_field_origin'] = $origin;
                    // Attach compact per-module hints when available (keep it lightweight).
                    if (isset($actual['art20_assistance']['hooks']) && is_array($actual['art20_assistance']['hooks'])) {
                        $actual['art20_assistance']['hooks']['_origin_step'] = $this->pickOriginForFields($origin, (array)$actual['art20_assistance']['hooks']);
                    }
                    if (isset($actual['art12']['hooks']) && is_array($actual['art12']['hooks'])) {
                        $actual['art12']['hooks']['_origin_step'] = $this->pickOriginForFields($origin, (array)$actual['art12']['hooks']);
                    }
                    $actual['_origin_note'] = 'wizard_field_origin maps form keys to the TRIN/step where they are set. Actual modules may reuse the same key.';
                }
                $row['actual'] = $actual;
                $row['match'] = $eval['match'];
                $row['diff'] = $eval['diff'];
            }
            $out[] = $row;
        }

        $this->set(['scenarios' => $out]);
        $this->viewBuilder()->setOption('serialize', ['scenarios']);
    }

    /**
     * Compact wizard fields for TRIN 1-9 (high-signal only).
     *
     * @return array<string,array<string,mixed>>
     */
    private function buildWizardCompact(array $fx): array
    {
        $w = (array)($fx['wizard'] ?? []);

        $step1 = (array)($w['step1_start'] ?? []);
        $step2 = (array)($w['step2_entitlements'] ?? []);
        $step3 = (array)($w['step3_journey'] ?? []);
        $step4 = (array)($w['step4_station'] ?? []);
        // Back-compat: older fixtures stored station-flow inside step4_incident before TRIN 4 existed.
        if (empty($step4) && !empty($w['step4_incident']) && is_array($w['step4_incident'])) {
            $legacy4 = (array)$w['step4_incident'];
            if (array_key_exists('a20_station_stranded', $legacy4) || array_key_exists('stranded_current_station', $legacy4)) {
                $step4 = $legacy4;
            }
        }
        $step5 = (array)($w['step5_incident'] ?? ($w['step4_incident'] ?? []));
        $step6 = (array)($w['step6_choices'] ?? ($w['step5_choices'] ?? [])); // transport/stranded (split flow)
        $step7 = (array)($w['step7_remedies'] ?? ($w['step6_remedies'] ?? []));
        $step8 = (array)($w['step8_assistance'] ?? ($w['step7_assistance'] ?? ($w['step6_assistance'] ?? [])));
        $step9 = (array)($w['step9_downgrade'] ?? ($w['step8_downgrade'] ?? []));
        $step10 = (array)($w['step10_compensation'] ?? ($w['step9_compensation'] ?? ($w['step7_compensation'] ?? [])));

        return [
            'step1_start' => $this->pick($step1, [
                'travel_state',
                'eu_only',
            ]),
            'step2_entitlements' => $this->pick($step2, [
                'operator',
                'operator_country',
                'operator_product',
                'dep_date',
                'dep_time',
                'dep_station',
                'arr_station',
                'arr_time',
                'train_no',
                'price',
            ]),
            'step3_journey' => $this->pick($step3, [
                'pmr_user',
                'pmr_booked',
                'pmr_delivered_status',
                'pmr_promised_missing',
                'bike_was_present',
                'bike_denied_boarding',
                'bike_refusal_reason_provided',
                'bike_refusal_reason_type',
                'fare_class_purchased',
                'berth_seat_type',
            ]),
            'step4_station' => $this->pick($step4, [
                'a20_station_stranded',
                'is_stranded',
                'stranded_location',
                'stranded_current_station',
                'stranded_current_station_other',
                'a20_3_solution_offered',
                'a20_3_solution_type',
                'a20_3_self_paid',
                'a20_3_self_arranged_type',
                'a20_3_self_paid_direction',
                'a20_3_self_paid_amount',
                'a20_3_self_paid_currency',
                'a20_3_self_paid_receipt',
                'a20_where_ended',
                'a20_arrival_station',
                'a20_arrival_station_other',
                'a20_where_ended_assumed',
                'handoff_station',
            ]),
            'step5_incident' => $this->pick($step5, [
                'incident_main',
                'incident_missed',
                'expected_delay_60',
                'delay_already_60',
                'missed_expected_delay_60',
                'national_delay_minutes',
                'national_delay_reported_at',
                'preinformed_disruption',
                'preinfo_channel',
                'realtime_info_seen',
                'missed_connection_station',
                'missed_connection_pick',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
            ]),
            'step6_choices' => $this->pick($step6, [
                'is_stranded_trin5',
                'maps_opt_in_trin5',
                'stranded_location',
                // Track (Art.20(2c))
                'blocked_train_alt_transport',
                'assistance_alt_transport_type',
                'blocked_no_transport_action',
                'blocked_self_paid_transport_type',
                'blocked_self_paid_amount',
                'blocked_self_paid_currency',
                'blocked_self_paid_receipt',
                // TRIN 5 resolution endpoint ("Hvor endte du?")
                'a20_where_ended',
                'a20_arrival_station',
                'a20_arrival_station_other',
                'a20_where_ended_assumed',
                'handoff_station',
            ]),
            'step7_remedies' => $this->pick($step7, [
                'art18_expected_delay_60',
                'remedyChoice',
                // TRIN 6 station context (separate from TRIN 5)
                'a18_from_station',
                'a18_from_station_other',
                // TRIN 6 refund context
                'a18_return_to_station',
                'a18_return_to_station_other',
                'a18_reroute_mode',
                'a18_reroute_endpoint',
                'a18_reroute_arrival_station',
                'a18_reroute_arrival_station_other',
                'reroute_info_within_100min',
                'reroute_same_conditions_soonest',
                'reroute_later_at_choice',
                'self_purchased_new_ticket',
                'self_purchase_approved_by_operator',
                'reroute_extra_costs',
                'reroute_extra_costs_amount',
                'reroute_extra_costs_currency',
                'reroute_later_ticket_upload',
                'reroute_later_ticket_file',
                'trip_cancelled_return_to_origin',
                'return_to_origin_expense',
                'return_to_origin_amount',
                'return_to_origin_currency',
            ]),
            'step8_assistance' => $this->pick($step8, [
                'art20_expected_delay_60',
                'meal_offered',
                'assistance_meals_unavailable_reason',
                'meal_self_paid_amount',
                'meal_self_paid_currency',
                'meal_self_paid_receipt',
                'hotel_offered',
                'assistance_hotel_transport_included',
                'hotel_transport_self_paid_amount',
                'hotel_transport_self_paid_currency',
                'hotel_transport_self_paid_receipt',
                'overnight_needed',
                'hotel_self_paid_nights',
                'hotel_self_paid_amount',
                'hotel_self_paid_currency',
                'hotel_self_paid_receipt',
                'assistance_pmr_priority_applied',
                'assistance_pmr_companion_supported',
                'price_hints',
            ]),
            'step9_downgrade' => $this->pick($step9, [
                'downgrade_ticket_file',
                'downgrade_occurred',
                'downgrade_comp_basis',
                'downgrade_segment_share',
                'leg_class_purchased',
                'leg_class_delivered',
                'leg_reservation_purchased',
                'leg_reservation_delivered',
                // Useful for QA/scenarios; otherwise it will be recomputed client-side only.
                'leg_downgraded',
            ]),
            'step10_compensation' => $this->pick($step10, [
                'delayAtFinalMinutes',
                'delayMinEU',
                'knownDelayBeforePurchase',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
            ]),
        ];
    }

    /**
     * Compact computeOverrides (high-signal only).
     *
     * @return array<string,mixed>
     */
    private function buildComputeOverridesCompact(array $fx): array
    {
        $c = (array)($fx['computeOverrides'] ?? []);
        return $this->pick($c, [
            'euOnly',
            'delayMinEU',
            'delayAtFinalMinutes',
            'knownDelayBeforePurchase',
            'refundAlready',
            'throughTicket',
            'returnTicket',
            'legPrice',
            'extraordinary',
            'selfInflicted',
            'art18Option',
        ]);
    }

    /**
     * Compact actual pipeline output (high-signal only).
     *
     * @return array<string,mixed>
     */
    private function buildActualCompact(array $actual): array
    {
        $out = [];

        // Profile + exemptions (hooks panel mirrors these)
        $out['profile'] = $this->pick((array)($actual['profile'] ?? []), [
            'scope',
            'eu_only_recommended',
            'articles',
            'articles_sub',
            'ui_banners',
        ]);

        // Art 12 / Art 9 headlines (keep hooks + missing only)
        $out['art12'] = $this->pick((array)($actual['art12'] ?? []), [
            'art12_applies',
            'hooks',
            'missing',
            'issues',
            'recommendations',
        ]);
        $out['art9'] = $this->pick((array)($actual['art9'] ?? []), [
            'hooks',
            'missing',
            'issues',
            'recommendations',
        ]);

        // TRIN 6-9: Claim output is the canonical summary used in TRIN 9.
        $out['claim'] = $this->pick((array)($actual['claim'] ?? []), [
            'breakdown',
            'totals',
            'flags',
        ]);

        // Keep raw modules that feed TRIN screens (small, but useful for debugging)
        $out['refund'] = $actual['refund'] ?? null;
        $out['refusion'] = $actual['refusion'] ?? null;

        // Assistance + downgrade evaluators (diagnostic)
        $a20 = (array)($actual['art20_assistance'] ?? []);
        if (!empty($a20)) {
            $a20Out = $this->pick($a20, ['compliance_status','missing','issues','recommendations']);
            $hooks = (array)($a20['hooks'] ?? []);
            if (!empty($hooks)) {
                $a20Out['hooks'] = $this->pick($hooks, [
                    '_active',
                    'art20_expected_delay_60',
                    'stranded_location',
                    // Station context (Art.20(3) + resolution endpoint)
                    'stranded_current_station',
                    'a20_where_ended',
                    'a20_arrival_station',
                    'handoff_station',
                    'blocked_train_alt_transport',
                     'alt_transport_provided',
                     'a20_3_solution_offered',
                     'a20_3_solution_type',
                     'a20_3_self_paid',
                     'a20_3_self_arranged_type',
                     'a20_3_self_paid_amount',
                     'a20_3_self_paid_currency',
                    'meal_offered',
                    'meal_self_paid_amount',
                    'meal_self_paid_currency',
                    'hotel_offered',
                    'overnight_needed',
                    'hotel_self_paid_amount',
                    'hotel_self_paid_currency',
                    'hotel_self_paid_nights',
                ]);
            }
            $out['art20_assistance'] = $a20Out;
        }

        $dw = (array)($actual['downgrade'] ?? []);
        if (!empty($dw)) {
            $out['downgrade'] = $this->pick($dw, [
                'compliance_status',
                'missing',
                'operator',
                'results',
            ]);
            if (isset($out['downgrade']['results']) && is_array($out['downgrade']['results'])) {
                $out['downgrade']['results'] = array_map(static function($r) {
                    $r = (array)$r;
                    $keep = [];
                    foreach (['legIndex','segment','downgraded','refund','missing'] as $k) {
                        if (array_key_exists($k, $r)) { $keep[$k] = $r[$k]; }
                    }
                    return $keep;
                }, (array)$out['downgrade']['results']);
            }
        }

        // Remove null-only top-level keys for readability.
        foreach ($out as $k => $v) {
            if ($v === null) { unset($out[$k]); }
        }

        return $out;
    }

    /**
     * Pick keys that exist and are not null (keep false/0).
     *
     * @param array<string,mixed> $src
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    private function pick(array $src, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $src)) { continue; }
            $v = $src[$k];
            if ($v === null) { continue; }
            $out[$k] = $v;
        }
        return $out;
    }

    /**
     * Build a compact "field -> step" provenance map from the fixture wizard sections.
     *
     * @return array<string,string|array<int,string>>
     */
    private function buildWizardFieldOrigin(array $fx): array
    {
        $w = (array)($fx['wizard'] ?? []);
        $steps = [
            'step1_start',
            'step2_entitlements',
            'step3_journey',
            'step4_station',
            'step5_incident',
            'step6_choices',
            'step7_remedies',
            'step8_assistance',
            'step9_downgrade',
            'step10_compensation',
        ];

        $origin = [];
        foreach ($steps as $step) {
            $payload = (array)($w[$step] ?? []);
            foreach (array_keys($payload) as $k) {
                if ($k === '') { continue; }
                if (!array_key_exists($k, $origin)) {
                    $origin[$k] = $step;
                    continue;
                }
                // Same form key can appear in multiple wizard steps across versions; keep both.
                $prev = $origin[$k];
                if (is_string($prev)) { $prev = [$prev]; }
                if (is_array($prev) && !in_array($step, $prev, true)) { $prev[] = $step; }
                $origin[$k] = $prev;
            }
        }

        ksort($origin, SORT_NATURAL | SORT_FLAG_CASE);
        return $origin;
    }

    /**
     * Pick provenance entries for a set of fields (same key names).
     *
     * @param array<string,string|array<int,string>> $origin
     * @param array<string,mixed> $fields
     * @return array<string,string|array<int,string>>
     */
    private function pickOriginForFields(array $origin, array $fields): array
    {
        $out = [];
        foreach (array_keys($fields) as $k) {
            if ($k === '_origin_step') { continue; }
            if (array_key_exists($k, $origin)) { $out[$k] = $origin[$k]; }
        }
        ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
        return $out;
    }
}
