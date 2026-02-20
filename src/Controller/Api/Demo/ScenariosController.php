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
                if ($compact) {
                    $row['wizard_compact'] = $this->buildWizardCompact($fx);
                    $row['computeOverrides_compact'] = $this->buildComputeOverridesCompact($fx);
                    $actual = $this->buildActualCompact($actual) + ['_compact' => true];
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
        $step4 = (array)($w['step4_incident'] ?? []);
        $step5 = (array)($w['step5_choices'] ?? []); // transport/stranded (split flow)
        $step6 = (array)($w['step6_remedies'] ?? []);
        $step7 = (array)($w['step7_assistance'] ?? ($w['step6_assistance'] ?? []));
        $step8 = (array)($w['step8_downgrade'] ?? []);
        $step9 = (array)($w['step9_compensation'] ?? ($w['step7_compensation'] ?? []));

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
            'step4_incident' => $this->pick($step4, [
                'incident_main',
                'incident_missed',
                'expected_delay_60',
                'preinformed_disruption',
                'realtime_info_seen',
                'missed_connection_station',
                'voucherAccepted',
                'operatorExceptionalCircumstances',
                'operatorExceptionalType',
                'minThresholdApplies',
            ]),
            'step5_choices' => $this->pick($step5, [
                'stranded_location',
                'a20_3_solution_offered',
                'a20_3_solution_type',
                'a20_3_no_solution_action',
                'a20_3_outcome',
                'a20_3_self_arranged_type',
                'a20_3_self_paid_amount',
                'a20_3_self_paid_currency',
                'assistance_alt_to_destination',
                'assistance_alt_to_destination_station',
                'assistance_alt_to_destination_station_other',
                'journey_outcome',
            ]),
            'step6_remedies' => $this->pick($step6, [
                'art18_expected_delay_60',
                'remedyChoice',
                'reroute_info_within_100min',
                'reroute_same_conditions_soonest',
                'reroute_later_at_choice',
                'self_purchased_new_ticket',
                'self_purchase_approved_by_operator',
                'reroute_extra_costs',
                'reroute_extra_costs_amount',
                'reroute_extra_costs_currency',
                'trip_cancelled_return_to_origin',
                'return_to_origin_expense',
                'return_to_origin_amount',
                'return_to_origin_currency',
            ]),
            'step7_assistance' => $this->pick($step7, [
                'art20_expected_delay_60',
                'meal_offered',
                'meal_self_paid_amount',
                'meal_self_paid_currency',
                'hotel_offered',
                'overnight_needed',
                'hotel_self_paid_nights',
                'hotel_self_paid_amount',
                'hotel_self_paid_currency',
                'alt_transport_provided',
            ]),
            'step8_downgrade' => $this->pick($step8, [
                'downgrade_occurred',
                'downgrade_comp_basis',
                'downgrade_segment_share',
                'leg_class_purchased',
                'leg_class_delivered',
                'leg_reservation_purchased',
                'leg_reservation_delivered',
            ]),
            'step9_compensation' => $this->pick($step9, [
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
                    'blocked_train_alt_transport',
                    'alt_transport_provided',
                    'a20_3_solution_offered',
                    'a20_3_solution_type',
                    'a20_3_no_solution_action',
                    'a20_3_outcome',
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
}
