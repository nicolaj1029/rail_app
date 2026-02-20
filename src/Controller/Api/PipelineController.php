<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class PipelineController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Unified pipeline: client uploads text/JSON, we ingest and run all evaluators.
     * POST body accepts: { text?: string, journey?: object, meta?: object, compute?: object }
     * Returns: { journey, meta, profile, art12, art9, compensation, refund, refusion, claim, logs }
     */
    public function run(): void
    {
        $this->request->allowMethod(['post']);
        $payload = (array)$this->request->getData();
        $evalSel = (string)($this->request->getQuery('eval') ?? ($payload['eval'] ?? ''));
        $compact = (bool)($this->request->getQuery('compact') ?? (bool)($payload['compact'] ?? false));

        // Inline ingest mapping (avoid controller coupling)
        $text = (string)($payload['text'] ?? '');
        $journey = (array)($payload['journey'] ?? []);
        // Merge wizard step data into meta so evaluators can see user answers
        $wizard = (array)($payload['wizard'] ?? []);
        // Split flow: step2_entitlements contains ticket price/operator and can be needed for claim totals.
        $wizardStep2 = (array)($wizard['step2_entitlements'] ?? []);
        $wizardStep3 = (array)($wizard['step3_journey'] ?? []);
        $wizardIncident = (array)($wizard['step4_incident'] ?? []);
        // Keep legacy key name step5_choices, but in split flow it is transport-only
        $wizardTransport = (array)($wizard['step5_choices'] ?? []);
        $wizardRemedies = (array)($wizard['step6_remedies'] ?? []);
        // Back-compat: old fixtures used step6_assistance; new split flow uses step7_assistance
        $wizardAssistance = (array)($wizard['step7_assistance'] ?? ($wizard['step6_assistance'] ?? []));
        $wizardDowngrade = (array)($wizard['step8_downgrade'] ?? []);
        // Back-compat: old fixtures used step7_compensation; new split flow uses step9_compensation
        $wizardComp = (array)($wizard['step9_compensation'] ?? ($wizard['step7_compensation'] ?? []));
        $meta = (array)($payload['meta'] ?? []);
        // Wizard answers should take precedence over bare meta
        $meta = array_merge($meta, $wizardStep2, $wizardStep3, $wizardIncident, $wizardTransport, $wizardRemedies, $wizardAssistance, $wizardDowngrade, $wizardComp);
        $logs = [];
        // Normalize per-leg class/reservation into segments + derive hooks when available
        try {
            $legClassBuy = (array)($meta['leg_class_purchased'] ?? []);
            $legClassDel = (array)($meta['leg_class_delivered'] ?? []);
            $legResBuy = (array)($meta['leg_reservation_purchased'] ?? []);
            $legResDel = (array)($meta['leg_reservation_delivered'] ?? []);
            $rank = ['sleeper' => 4, 'couchette' => 3, '1st' => 2, '2nd' => 1];
            $normClass = function($v): string {
                $v = strtolower(trim((string)$v));
                if (in_array($v, ['1st_class','1st','first','1'], true)) return '1st';
                if (in_array($v, ['2nd_class','2nd','second','2'], true)) return '2nd';
                if ($v === 'seat_reserved' || $v === 'free_seat') return '2nd';
                return $v;
            };
            $normRes = function($v): string {
                $v = strtolower(trim((string)$v));
                if (in_array($v, ['seat_reserved','reserved','seat'], true)) return 'reserved';
                if (in_array($v, ['free','free_seat'], true)) return 'free_seat';
                if ($v === 'missing') return 'missing';
                return $v;
            };
            $segments = (array)($journey['segments'] ?? []);
            if (!empty($segments)) {
                $count = min(count($segments), max(count($legClassBuy), count($legClassDel), count($legResBuy), count($legResDel)));
                for ($i = 0; $i < $count; $i++) {
                    $cb = $normClass($legClassBuy[$i] ?? '');
                    $cd = $normClass($legClassDel[$i] ?? '');
                    $rb = $normRes($legResBuy[$i] ?? '');
                    $rd = $normRes($legResDel[$i] ?? '');
                    if ($cb !== '') { $segments[$i]['fare_class_purchased'] = $cb; }
                    if ($cb !== '' && $cd !== '' && isset($rank[$cb]) && isset($rank[$cd])) {
                        if ($rank[$cd] < $rank[$cb]) { $segments[$i]['class_delivered_status'] = 'lower'; }
                        elseif ($rank[$cd] > $rank[$cb]) { $segments[$i]['class_delivered_status'] = 'higher'; }
                        else { $segments[$i]['class_delivered_status'] = 'same'; }
                    }
                    $amenBooked = 'none';
                    if (in_array($cb, ['sleeper','couchette'], true)) { $amenBooked = $cb; }
                    elseif ($rb === 'reserved') { $amenBooked = 'seat'; }
                    $segments[$i]['reserved_amenity_booked'] = $amenBooked;
                    if ($amenBooked !== 'none') {
                        if ($amenBooked === 'seat') {
                            if ($rd === 'reserved') { $segments[$i]['reserved_amenity_delivered'] = 'yes'; }
                            elseif (in_array($rd, ['free_seat','missing'], true)) { $segments[$i]['reserved_amenity_delivered'] = 'no'; }
                        } else {
                            if ($cb !== '' && $cd !== '' && isset($rank[$cb]) && isset($rank[$cd])) {
                                $segments[$i]['reserved_amenity_delivered'] = ($rank[$cd] < $rank[$cb]) ? 'no' : 'yes';
                            }
                        }
                    }
                    if (isset($meta['downgrade_segment_share'])) {
                        $segments[$i]['downgrade_segment_share'] = $meta['downgrade_segment_share'];
                    }
                }
                $journey['segments'] = $segments;
            }
            // Derive global hooks if missing
            if ((empty($meta['fare_class_purchased']) || $meta['fare_class_purchased'] === 'unknown') && !empty($legClassBuy)) {
                $firstClass = '';
                foreach ($legClassBuy as $v) { $v = $normClass($v); if ($v !== '') { $firstClass = $v; break; } }
                if ($firstClass !== '') {
                    $meta['fare_class_purchased'] = $firstClass === '1st' ? '1' : ($firstClass === '2nd' ? '2' : 'other');
                }
            }
            if ((empty($meta['berth_seat_type']) || $meta['berth_seat_type'] === 'unknown') && (!empty($legClassBuy) || !empty($legResBuy))) {
                $firstClass = '';
                foreach ($legClassBuy as $v) { $v = $normClass($v); if ($v !== '') { $firstClass = $v; break; } }
                $firstRes = '';
                foreach ($legResBuy as $v) { $v = $normRes($v); if ($v !== '') { $firstRes = $v; break; } }
                if (in_array($firstClass, ['sleeper','couchette'], true)) { $meta['berth_seat_type'] = $firstClass; }
                elseif ($firstRes === 'reserved') { $meta['berth_seat_type'] = 'seat'; }
                elseif ($firstRes === 'free_seat') { $meta['berth_seat_type'] = 'free'; }
            }
            if (empty($meta['class_delivered_status']) && (!empty($legClassBuy) || !empty($legClassDel))) {
                $anyCompared = false; $anyLower = false; $anyHigher = false;
                $cnt = max(count($legClassBuy), count($legClassDel));
                for ($i = 0; $i < $cnt; $i++) {
                    $cb = $normClass($legClassBuy[$i] ?? '');
                    $cd = $normClass($legClassDel[$i] ?? '');
                    if ($cb !== '' && $cd !== '' && isset($rank[$cb]) && isset($rank[$cd])) {
                        $anyCompared = true;
                        if ($rank[$cd] < $rank[$cb]) { $anyLower = true; }
                        elseif ($rank[$cd] > $rank[$cb]) { $anyHigher = true; }
                    }
                }
                if ($anyLower) { $meta['class_delivered_status'] = 'downgrade'; }
                elseif ($anyCompared && $anyHigher) { $meta['class_delivered_status'] = 'upgrade'; }
                elseif ($anyCompared) { $meta['class_delivered_status'] = 'ok'; }
            }
            if (empty($meta['reserved_amenity_delivered']) && (!empty($legResBuy) || !empty($legClassBuy))) {
                $amenBookedAny = false; $amenYes = false; $amenNo = false;
                $cnt = max(count($legClassBuy), count($legClassDel), count($legResBuy), count($legResDel));
                for ($i = 0; $i < $cnt; $i++) {
                    $cb = $normClass($legClassBuy[$i] ?? '');
                    $cd = $normClass($legClassDel[$i] ?? '');
                    $rb = $normRes($legResBuy[$i] ?? '');
                    $rd = $normRes($legResDel[$i] ?? '');
                    $amenBooked = 'none';
                    if (in_array($cb, ['sleeper','couchette'], true)) { $amenBooked = $cb; }
                    elseif ($rb === 'reserved') { $amenBooked = 'seat'; }
                    if ($amenBooked !== 'none') {
                        $amenBookedAny = true;
                        if ($amenBooked === 'seat') {
                            if ($rd === 'reserved') { $amenYes = true; }
                            elseif (in_array($rd, ['free_seat','missing'], true)) { $amenNo = true; }
                        } else {
                            if ($cb !== '' && $cd !== '' && isset($rank[$cb]) && isset($rank[$cd])) {
                                if ($rank[$cd] < $rank[$cb]) { $amenNo = true; }
                                else { $amenYes = true; }
                            }
                        }
                    }
                }
                if ($amenBookedAny) {
                    if ($amenNo && $amenYes) { $meta['reserved_amenity_delivered'] = 'partial'; }
                    elseif ($amenNo) { $meta['reserved_amenity_delivered'] = 'no'; }
                    elseif ($amenYes) { $meta['reserved_amenity_delivered'] = 'yes'; }
                }
            }
        } catch (\Throwable $e) {
            // ignore per-leg mapping errors
        }
        if ($text !== '') {
            $map = (new \App\Service\OcrHeuristicsMapper())->mapText($text);
            foreach (($map['auto'] ?? []) as $k => $v) { $meta['_auto'][$k] = $v; }
            $logs = array_merge($logs, $map['logs'] ?? []);
        }

        // Build profile
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);

        // Art. 12 & 9
        $art12 = (new \App\Service\Art12Evaluator())->evaluate($journey, (array)($payload['art12_meta'] ?? []));
        // Prefer wizard/meta answers, then fill gaps from art9_meta evidence
        $art9Meta = $meta + (array)($payload['art9_meta'] ?? []);
        // Load operators catalog for evaluator policies (downgrade, supplements)
        try {
            $opsPath = ROOT . DS . 'config' . DS . 'data' . DS . 'operators_catalog.json';
            if (file_exists($opsPath)) {
                $opsJson = (string)file_get_contents($opsPath);
                $opsData = json_decode($opsJson, true);
                if (is_array($opsData) && isset($opsData['downgrade_policies'])) {
                    $meta['_operators_catalog'] = (array)$opsData['downgrade_policies'];
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal; evaluators will fall back
        }
        $art9 = (new \App\Service\Art9Evaluator())->evaluate($journey, $art9Meta);
        // Split sub-evaluations for clarity
        $art9_fastest = (new \App\Service\Art9FastestEvaluator())->evaluate($journey, $art9Meta);
        $art9_pricing = (new \App\Service\Art9PricingEvaluator())->evaluate($journey, $art9Meta);
        $art9_preknown = (new \App\Service\Art9PreknownEvaluator())->evaluate($journey, $art9Meta);
        // Downgrade per-leg (CIV/GCC-CIV/PRR with Art.9(1) evidence; Art.18(2) for reroute)
        try {
            $downgrade = (new \App\Service\DowngradeEvaluator())->evaluate($journey, ['operator' => ($journey['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')), 'currency' => ($journey['ticketPrice']['currency'] ?? 'EUR'), '_operators_catalog' => ((array)($meta['_operators_catalog'] ?? []))]);
        } catch (\Throwable $e) {
            $downgrade = ['error' => 'downgrade_failed', 'message' => $e->getMessage()];
        }
        // Art. 6 (bicycles) — minimal compliance evaluator, additive output
        try {
        $art6 = (new \App\Service\Art6BikeEvaluator())->evaluate($journey, $art9Meta);
        } catch (\Throwable $e) {
            $art6 = ['error' => 'art6_failed', 'message' => $e->getMessage()];
        }
        // Art. 21–24 (PMR assistance)
        try {
            $pmr = (new \App\Service\Art21to24PmrEvaluator())->evaluate($journey, $art9Meta);
        } catch (\Throwable $e) {
            $pmr = ['error' => 'pmr_failed', 'message' => $e->getMessage()];
        }

        // Compensation preview mirrors ComputeController::compensation
        // Support scenario runner which posts computeOverrides, plus direct compute payloads
        $compute = array_merge(
            (array)($payload['compute'] ?? []),
            (array)($payload['computeOverrides'] ?? [])
        );
        $segments = (array)($journey['segments'] ?? []);
        $last = !empty($segments) ? $segments[array_key_last($segments)] : [];
        $schedArr = (string)($last['schedArr'] ?? ''); $actArr = (string)($last['actArr'] ?? '');
        $minutes = 0;
        if ($schedArr && $actArr) { $t1 = strtotime($schedArr); $t2 = strtotime($actArr); if ($t1 && $t2) { $minutes = max(0, (int)round(($t2-$t1)/60)); } }
        // Ticket price: prefer journey.ticketPrice (pipeline-native), else wizard step2 "price" (split flow).
        $priceRaw = (string)($journey['ticketPrice']['value'] ?? ($meta['price'] ?? ($meta['_auto']['price']['value'] ?? '0 EUR')));
        $price = 0.0; $currency = 'EUR';
        if (preg_match('/([0-9]+(?:[\\.,][0-9]{1,2})?)/', $priceRaw, $m)) { $price = (float)str_replace(',', '.', $m[1]); }
        if (preg_match('/\\b(BGN|CZK|DKK|HUF|PLN|RON|SEK|EUR)\\b/i', $priceRaw, $m)) { $currency = strtoupper($m[1]); }
        // Disambiguate "kr" (DKK/SEK) using operator_country when possible (demo fixtures often contain "kr.")
        if ($currency === 'EUR' && preg_match('/\\bkr\\b/i', $priceRaw)) {
            $opCountry = strtoupper((string)($meta['operator_country'] ?? ($journey['country']['value'] ?? '')));
            if (in_array($opCountry, ['DK','SE'], true)) { $currency = $opCountry === 'DK' ? 'DKK' : 'SEK'; }
            else { $currency = 'DKK'; }
        }
        // E4: EU-only delay if requested
        $euOnlyFlag = (bool)($compute['euOnly'] ?? true);
        if ($euOnlyFlag && isset($compute['delayMinEU'])) {
            $euMin = (int)$compute['delayMinEU'];
            if ($euMin >= 0) { $minutes = $euMin; }
        } elseif (isset($compute['delayAtFinalMinutes'])) {
            // Split-flow semantics: if no EU-only override exists, prefer the explicit "whole trip" delay when provided.
            $whole = (int)$compute['delayAtFinalMinutes'];
            if ($whole >= 0) { $minutes = $whole; }
        }
        $elig = new \App\Service\EligibilityService(new \App\Service\ExemptionsRepository(), new \App\Service\NationalOverridesRepository());
        $knownDelay = (bool)($compute['knownDelayBeforePurchase'] ?? false);
        if (!$knownDelay && (($art9Meta['preinformed_disruption'] ?? 'unknown') === 'Ja')) { $knownDelay = true; }
        $compRes = $elig->computeCompensation([
            'delayMin' => $minutes,
            'euOnly' => $euOnlyFlag,
            'refundAlready' => (bool)($compute['refundAlready'] ?? false),
            'art18Option' => (string)($compute['art18Option'] ?? ''),
            'knownDelayBeforePurchase' => $knownDelay,
            'extraordinary' => (bool)($compute['extraordinary'] ?? false),
            'selfInflicted' => (bool)($compute['selfInflicted'] ?? false),
            'throughTicket' => (bool)($compute['throughTicket'] ?? true),
        ]);
        $pct = ((int)($compRes['percent'] ?? 0)) / 100;
        // E3: apportion return or leg price if provided
        $amountBase = $price;
        $legPrice = isset($compute['legPrice']) ? (float)$compute['legPrice'] : null;
        if ($legPrice !== null && $legPrice > 0) { $amountBase = $legPrice; }
        elseif (!empty($compute['returnTicket'])) { $amountBase = $price / 2; }
        $amount = round($amountBase * $pct, 2);
        $compensation = [
            'minutes' => $minutes,
            'pct' => $pct,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $compRes['source'] ?? 'eu',
            'notes' => $compRes['notes'] ?? null,
        ];

        // Refund + Refusion + Claim
        $refund = (new \App\Service\RefundEvaluator())->evaluate($journey, (array)($payload['refund_meta'] ?? $meta));
        // Merge refusion_meta overrides on top of merged meta (step4 answers)
        $refusionMeta = array_merge($meta, (array)($payload['refusion_meta'] ?? []));
        $refusion = (new \App\Service\Art18RefusionEvaluator())->evaluate($journey, $refusionMeta);
        // Claim breakdown (Art.18/19/20) based on wizard answers.
        $remedy = strtolower(trim((string)($meta['remedyChoice'] ?? '')));
        $choices = [
            'wants_refund' => ($remedy === 'refund_return'),
            'wants_reroute_same_soonest' => ($remedy === 'reroute_soonest'),
            'wants_reroute_later_choice' => ($remedy === 'reroute_later'),
        ];
        $num = static function($v): float {
            if (is_numeric($v)) { return (float)$v; }
            return (float)preg_replace('/[^0-9.]/', '', (string)$v);
        };
        $pick = static function(array $keys) use ($meta, $num): float {
            foreach ($keys as $k) {
                if (isset($meta[$k]) && $meta[$k] !== '' && $meta[$k] !== null) {
                    return $num($meta[$k]);
                }
            }
            return 0.0;
        };
        $mealsAmt = $pick(['meal_self_paid_amount','expense_breakdown_meals','expense_meals']);
        $hotelAmt = $pick(['hotel_self_paid_amount','expense_hotel']);
        $altAmt = $pick(['alt_self_paid_amount','a20_3_self_paid_amount','expense_breakdown_local_transport','expense_alt_transport']);
        $blockedAltAmt = $pick(['blocked_self_paid_amount']);
        $otherAmt = $pick(['expense_breakdown_other_amounts','expense_other']);

        // Currency-aware conversion per expense bucket -> ticket currency
        $fx = ['EUR'=>1.0,'DKK'=>7.45,'SEK'=>11.0,'BGN'=>1.96,'CZK'=>25.0,'HUF'=>385.0,'PLN'=>4.35,'RON'=>4.95];
        $fxConv = static function(float $amt, string $from, string $to) use (&$fx): ?float {
            $from = strtoupper(trim($from)); $to = strtoupper(trim($to));
            if (!isset($fx[$from]) || !isset($fx[$to]) || $fx[$from] <= 0 || $fx[$to] <= 0) return null;
            $eur = $amt / $fx[$from];
            return $eur * $fx[$to];
        };
        $conv = static function(float $amt, string $fromCur, string $toCur) use ($fxConv): float {
            $fromCur = strtoupper(trim($fromCur)); $toCur = strtoupper(trim($toCur));
            if ($amt <= 0) return 0.0;
            if ($fromCur === '' || $toCur === '' || $fromCur === $toCur) return $amt;
            $c = $fxConv($amt, $fromCur, $toCur);
            return $c !== null ? $c : $amt;
        };
        $defaultCur = strtoupper((string)($meta['expense_breakdown_currency'] ?? $meta['expense_currency'] ?? $currency));
        $mealsCur = strtoupper((string)($meta['meal_self_paid_currency'] ?? $defaultCur));
        $hotelCur = strtoupper((string)($meta['hotel_self_paid_currency'] ?? $defaultCur));
        $altCur = strtoupper((string)($meta['alt_self_paid_currency'] ?? $meta['a20_3_self_paid_currency'] ?? $defaultCur));
        $blockedCur = strtoupper((string)($meta['blocked_self_paid_currency'] ?? $altCur));
        $otherCur = strtoupper((string)($defaultCur));
        $expenses = [
            'meals' => $conv($mealsAmt, $mealsCur, $currency),
            'hotel' => $conv($hotelAmt, $hotelCur, $currency),
            // Art.20(3) transport: include both station + blocked-on-track buckets
            'alt_transport' => $conv($altAmt, $altCur, $currency) + $conv($blockedAltAmt, $blockedCur, $currency),
            'other' => $conv($otherAmt, $otherCur, $currency),
        ];

        $exc = strtolower(trim((string)($meta['operatorExceptionalCircumstances'] ?? '')));
        $extraordinary = (bool)($compute['extraordinary'] ?? false);
        if (in_array($exc, ['yes','ja','1','true'], true)) { $extraordinary = true; }
        $extraordinaryType = (string)($meta['operatorExceptionalType'] ?? '');
        $minThreshold = !empty($meta['minThresholdApplies']);
        $missed = strtolower(trim((string)($meta['incident_missed'] ?? '')));
        $missedBool = in_array($missed, ['yes','ja','1','true'], true);
        $incidentMain = strtolower(trim((string)($meta['incident_main'] ?? '')));
        $reasonCancellation = ($incidentMain === 'cancellation');

        // Art.18 gating: do not rely on final delay minutes alone (TRIN 3 PMR/bike can bypass TRIN 4 details).
        $expectedDelay = (int)($compute['delayMinEU'] ?? 0);
        $art18Active = ($expectedDelay >= 60) || $reasonCancellation || $missedBool;
        if (!$art18Active && ((string)($meta['art18_expected_delay_60'] ?? '') === 'yes')) { $art18Active = true; }
        if (!$art18Active) {
            $norm = static function($v): string {
                $s = strtolower(trim((string)$v));
                if ($s === 'ja') { return 'yes'; }
                if ($s === 'nej') { return 'no'; }
                return $s;
            };
            $pmrUser = $norm($meta['pmr_user'] ?? '');
            $pmrBooked = $norm($meta['pmr_booked'] ?? '');
            $pmrDelivered = $norm($meta['pmr_delivered_status'] ?? '');
            $pmrMissing = $norm($meta['pmr_promised_missing'] ?? '');
            $pmrGate = ($pmrUser === 'yes') && (
                ($pmrBooked === 'yes' && $pmrDelivered === 'no') ||
                ($pmrMissing === 'yes')
            );

            $bikeDenied = $norm($meta['bike_denied_boarding'] ?? '');
            $bikeReasonProvided = $norm($meta['bike_refusal_reason_provided'] ?? '');
            $bikeReasonType = strtolower(trim((string)($meta['bike_refusal_reason_type'] ?? '')));
            $bikeReasonAllowed = in_array($bikeReasonType, ['capacity', 'equipment', 'weight_dim'], true);
            $bikeGate = ($bikeDenied === 'yes') && ($bikeReasonProvided !== 'yes' || !$bikeReasonAllowed);

            if ($pmrGate || $bikeGate) { $art18Active = true; }
        }

        $claim = (new \App\Service\ClaimCalculator())->calculate([
            'country_code' => (string)($journey['country']['value'] ?? 'EU'),
            'currency' => $currency,
            'ticket_price_total' => $price,
            'trip' => [
                'through_ticket' => (bool)($art12['art12_applies'] ?? ($compute['throughTicket'] ?? true)),
                'legs' => [],
            ],
            'disruption' => [
                // When eu_only=true, ClaimCalculator uses delay_minutes_final_eu (otherwise it tries to sum per-leg actual delays).
                'delay_minutes_final' => $minutes,
                'eu_only' => (bool)($compute['euOnly'] ?? true),
                'notified_before_purchase' => $knownDelay,
                'extraordinary' => $extraordinary,
                'extraordinary_type' => $extraordinaryType,
                'self_inflicted' => (bool)($compute['selfInflicted'] ?? false),
                'missed_connection' => $missedBool,
                'art18_active' => $art18Active,
            ] + (((bool)($compute['euOnly'] ?? true)) ? ['delay_minutes_final_eu' => $minutes] : []),
            'choices' => $choices,
            'expenses' => $expenses,
            'already_refunded' => 0,
            'service_fee_mode' => 'expenses_only',
            'apply_min_threshold' => $minThreshold,
            // Allow claim to incorporate downgrade/refusion output where present
            'refusion' => $refusion,
            'downgrade_occurred' => $meta['downgrade_occurred'] ?? null,
            'downgrade_comp_basis' => $meta['downgrade_comp_basis'] ?? null,
        ]);

        // Art. 20 (assistance) evaluation (step 5)
        try {
            $art20_assistance = (new \App\Service\Art20AssistanceEvaluator())->evaluate($journey, $meta);
        } catch (\Throwable $e) {
            $art20_assistance = ['error' => 'art20_failed', 'message' => $e->getMessage()];
        }

        $out = compact('journey','meta','logs','profile','art12','art9','art9_fastest','art9_pricing','art9_preknown','downgrade','art6','pmr','art20_assistance','compensation','refund','refusion','claim');
        // Support selective eval output for demo/testing: eval=art9|art9.fastest|art9.pricing|mct|art30.complaints
        if ($evalSel !== '') {
            $keep = [];
            switch ($evalSel) {
                case 'art9':
                    $keep = ['journey','meta','profile','art9','art9_fastest','art9_pricing','art9_preknown','downgrade'];
                    break;
                case 'art9.fastest':
                    $keep = ['journey','meta','profile','art9_fastest'];
                    break;
                case 'art9.pricing':
                    $keep = ['journey','meta','profile','art9_pricing'];
                    break;
                case 'art9.preknown':
                    $keep = ['journey','meta','profile','art9_preknown'];
                    break;
                case 'art9.downgrade':
                    $keep = ['journey','meta','profile','downgrade'];
                    break;
                default:
                    // Unknown eval key → no special filtering
                    $keep = [];
            }
            if ($compact && !empty($keep)) {
                $out = array_intersect_key($out, array_flip($keep));
            }
        }
        $this->set($out);
        $this->viewBuilder()->setOption('serialize', array_keys($out));
    }
}
