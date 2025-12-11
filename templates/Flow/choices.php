<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$profile = $profile ?? ['articles' => []];
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
?>

<style>
    .hidden { display:none; }
    .small { font-size:12px; }
    .muted { color:#666; }
    .mt8 { margin-top:8px; }
    .mt12 { margin-top:12px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .disabled-block { opacity: 0.6; }
</style>
<h1>TRIN 4 · Dine valg (Art. 18)</h1>
<?php
    if ($travelState === 'completed') {
        echo '<p class="small muted">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';
    } elseif ($travelState === 'ongoing') {
        echo '<p class="small muted">Status: Rejsen er i gang. Vi samler dine valg for resten af forløbet.</p>';
    } elseif ($travelState === 'not_started') {
        echo '<p class="small muted">Status: Rejsen er endnu ikke påbegyndt. Besvar ud fra, hvad du forventer at gøre ved forsinkelse/aflysning.</p>';
    }
?>
<?php
    $articles = (array)($profile['articles'] ?? []);
    $showArt183 = !isset($articles['art18_3']) || $articles['art18_3'] !== false;
?>
<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'choices'], 'type' => 'file', 'novalidate' => true]) ?>

<?php if (!empty($art18Blocked)): ?>
    <div class="card" style="padding:12px; border:1px solid #f5c6cb; background:#fff5f5; border-radius:6px; margin-bottom:12px;">
        <strong>Ikke berettiget til omlægning/refusion (Art. 18)</strong>
        <p class="small muted">Du har svaret, at du ikke forventede ≥ 60 minutters forsinkelse, og der er heller ikke registreret aflysning eller mistet forbindelse. Derfor kan vi ikke behandle denne sag under Art. 18 lige nu.</p>
        <p class="small muted">Du kan gå tilbage og rette oplysningerne, eller fortsætte senere hvis situationen ændrer sig.</p>
    </div>
<?php elseif (!empty($showArt18Fallback)): ?>
    <?php
        $expectedDelay = (string)($form['art18_expected_delay_60'] ?? '');
        $fbStyle = ($expectedDelay === 'yes') ? 'display:none;' : '';
    ?>
    <div id="art18FallbackCard" class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px; <?= $fbStyle ?>">
        <strong>Forventet forsinkelse ≥ 60 minutter?</strong>
        <p class="small muted">Da vi ikke på forhånd vidste, om forsinkelsen ville blive mindst 60 minutter, må du svare her.</p>
        <label><input type="radio" name="art18_expected_delay_60" value="yes" <?= $expectedDelay==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="art18_expected_delay_60" value="no" <?= $expectedDelay==='no'?'checked':'' ?> /> Nej</label>
        
        <div id="art18NoWarn" class="small" style="display:none; margin-top:6px; padding:6px; background:#fff3cd; border-radius:6px;">
            Du har valgt "Nej" – så kan vi ikke tilbyde omlægning/refusion under Art. 18. Ret dine oplysninger eller gå tilbage.
        </div>
        <div id="art18YesHint" class="small muted" style="display:none; margin-top:6px;">Svar "Ja" aktiverer valgmulighederne nedenfor.</div>
    </div>
<?php endif; ?>

<?php if (empty($art18Blocked)): ?>
<div id="coreAfterArt18" style="<?= (!empty($showArt18Fallback) && (($form['art18_expected_delay_60'] ?? '')!=='yes')) ? 'display:none;' : '' ?>">
<?php
// Downgrade preview context (server-side): ticket price from controller or fallback
$tp = isset($ticketPrice) ? (float)$ticketPrice : (float)preg_replace('/[^0-9.]/','', (string)($form['price'] ?? '0'));
$basis = (string)($form['downgrade_comp_basis'] ?? '');
$share = (float)($form['downgrade_segment_share'] ?? 1.0);
if (!is_finite($share)) { $share = 1.0; }
if ($share < 0.0) { $share = 0.0; }
if ($share > 1.0) { $share = 1.0; }
$rateMap = ['seat'=>0.25,'couchette'=>0.50,'sleeper'=>0.75];
$rate    = $rateMap[$basis] ?? 0.0;
$preview = round($tp * $rate * $share, 2);
?>

<?php if ($isCompleted): ?>
    <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
        <strong>Rejsen er afsluttet — hvad skete der?</strong>
        <div class="small muted" style="margin-top:6px;">Ved afgang, missed connection eller aflysning — ved ≥60 min. forsinkelse tilbydes nedenstående muligheder.</div>
        <?php $remedy = (string)($form['remedyChoice'] ?? ''); if ($remedy==='') { if (($form['trip_cancelled_return_to_origin'] ?? '')==='yes') { $remedy='refund_return'; } elseif (($form['reroute_same_conditions_soonest'] ?? '')==='yes') { $remedy='reroute_soonest'; } elseif (($form['reroute_later_at_choice'] ?? '')==='yes') { $remedy='reroute_later'; } } ?>
        <div class="mt8"><strong>Vælg præcis én mulighed</strong></div>
        <label><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Blev hele rejsen aflyst, og vendte du tilbage til udgangspunktet?</label><br/>
        <label><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Fik du tilbudt omlægning på tilsvarende vilkår ved først givne lejlighed?</label><br/>
        <label><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Ønskede du i stedet omlægning på et senere tidspunkt efter eget valg?</label>

        <!-- Hidden sync to legacy hooks -->
        <input type="hidden" id="tcr_sync_past" name="trip_cancelled_return_to_origin" value="<?= ($form['trip_cancelled_return_to_origin'] ?? '') ?>" />
        <input type="hidden" id="rsc_sync_past" name="reroute_same_conditions_soonest" value="<?= ($form['reroute_same_conditions_soonest'] ?? '') ?>" />
        <input type="hidden" id="rlc_sync_past" name="reroute_later_at_choice" value="<?= ($form['reroute_later_at_choice'] ?? '') ?>" />

        <div id="returnExpensePast" class="mt8 <?= $remedy==='refund_return' ? '' : 'hidden' ?>">
            <div class="mt8"><strong>Returtransport (Art. 18 stk. 1)</strong></div>
            <?php $rtFlag = (string)($form['return_to_origin_expense'] ?? ''); ?>
            <div class="mt4">Havde du udgifter til at komme tilbage til udgangspunktet?</div>
            <label><input type="radio" name="return_to_origin_expense" value="no" <?= $rtFlag==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="return_to_origin_expense" value="yes" <?= $rtFlag==='yes'?'checked':'' ?> /> Ja</label>
            <div class="grid-2 mt8" id="returnExpenseFieldsPast" style="<?= $rtFlag==='yes' ? '' : 'display:none;' ?>">
                <label>Beløb
                    <input type="number" step="0.01" name="return_to_origin_amount" value="<?= h($form['return_to_origin_amount'] ?? '') ?>" />
                </label>
                <label>Valuta
                    <input type="text" name="return_to_origin_currency" value="<?= h($form['return_to_origin_currency'] ?? '') ?>" placeholder="<?= h($currency ?? 'EUR') ?>" />
                </label>
            </div>
        </div>

        <div id="rerouteSectionPast" class="mt12 <?= in_array($remedy, ['reroute_soonest','reroute_later'], true) ? '' : 'hidden' ?>">
            <div class="mt0"><strong>Omlægning</strong></div>
            <?php $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); $show100Past = $showArt183 && $remedy==='reroute_soonest'; ?>
            <div id="ri100PastWrap" style="<?= $show100Past ? '' : 'display:none;' ?>" data-art="18(3)">
                <div class="mt8">Fik du besked om mulighederne for omlægning inden for 100 minutter? (Art. 18(3))
                    <span class="small muted">Vi bruger planlagt afgang + første omlægnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="unknown" <?= ($ri100===''||$ri100==='unknown')?'checked':'' ?> /> Ved ikke</label>
            </div>

            <!-- OFF-variant additions: always simple branch without 100-min dependency -->
            <fieldset id="offerProvidedWrapPast" class="mt8" <?= $showArt183 ? 'hidden' : '' ?> >
              <legend>Fik du et konkret omlægningstilbud fra operatøren?</legend>
              <?php $offProv = (string)($form['offer_provided'] ?? ''); ?>
              <label><input type="radio" name="offer_provided" value="yes" <?= $offProv==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="offer_provided" value="no" <?= $offProv==='no'?'checked':'' ?> /> Nej</label>
              <label class="ml8"><input type="radio" name="offer_provided" value="unknown" <?= ($offProv===''||$offProv==='unknown')?'checked':'' ?> /> Ved ikke</label>
            </fieldset>

            <?php $spt = (string)($form['self_purchased_new_ticket'] ?? ''); ?>
                        <div id="step2Past" style="display:none">
                <div class="mt8">Købte du selv en ny billet for at komme videre?</div>
                <label><input type="radio" name="self_purchased_new_ticket" value="yes" <?= $spt==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="self_purchased_new_ticket" value="no" <?= $spt==='no'?'checked':'' ?> /> Nej</label>
                <div id="selfBuyNotePast" class="small" style="margin-top:6px; padding:6px; background:#fff3cd; border-radius:6px; display:none;">Du købte selv ny billet, selvom omlægning blev tilbudt inden for 100 min — udgiften refunderes normalt ikke (Art. 18(3)). Kompensation kan stadig være mulig.</div>
            </div>

                        <fieldset id="opApprovalWrapPast" class="mt8" hidden>
                            <legend>Var selvkøbet godkendt af operatøren?</legend>
                            <?php $opOK = (string)($form['self_purchase_approved_by_operator'] ?? ''); ?>
                            <label><input type="radio" name="self_purchase_approved_by_operator" value="yes" <?= $opOK==='yes'?'checked':'' ?> /> Ja</label>
                            <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="no" <?= $opOK==='no'?'checked':'' ?> /> Nej</label>
                            <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="unknown" <?= ($opOK===''||$opOK==='unknown')?'checked':'' ?> /> Ved ikke</label>
                        </fieldset>
                        <div id="notesAreaPast" class="notes small">
                            <p id="noteApprovedPast" class="note success" style="display:none;">✓ Selvkøb er oplyst som godkendt af operatøren.</p>
                            <p id="noteNotRefundablePast" class="note warn" style="display:none;">⚠️ Selvkøb uden operatørens godkendelse refunderes normalt ikke.</p>
                        </div>


                        <div id="recBlockPast">
                                <?php $rec = (string)($form['reroute_extra_costs'] ?? ''); ?>
                                <div class="mt8">Medførte omlægningen ekstra udgifter for dig? (højere klasse/andet transportmiddel)</div>
                                <label><input type="radio" name="reroute_extra_costs" value="yes" <?= $rec==='yes'?'checked':'' ?> /> Ja</label>
                                <label class="ml8"><input type="radio" name="reroute_extra_costs" value="no" <?= $rec==='no'?'checked':'' ?> /> Nej</label>
                                <label class="ml8"><input type="radio" name="reroute_extra_costs" value="unknown" <?= ($rec===''||$rec==='unknown')?'checked':'' ?> /> Ved ikke</label>
                                <div class="grid-2 mt8 <?= $rec==='yes' ? '' : 'hidden' ?>" id="recWrapPast">
                                        <label>Beløb
                                            <input type="number" step="0.01" name="reroute_extra_costs_amount" value="<?= h($form['reroute_extra_costs_amount'] ?? '') ?>" />
                                        </label>
                                        <label>Valuta
                                            <?php $curCur = strtoupper(trim((string)($form['reroute_extra_costs_currency'] ?? ''))); ?>
                                            <select name="reroute_extra_costs_currency">
                                                <option value="">Auto</option>
                                                <?php foreach (['EUR','DKK','SEK','BGN','CZK','HUF','PLN','RON'] as $cc): ?>
                                                    <option value="<?= $cc ?>" <?= $curCur===$cc?'selected':'' ?>><?= $cc ?></option>
                                                <?php endforeach; ?>
                                                <?php if ($curCur !== '' && !in_array($curCur, ['EUR','DKK','SEK','BGN','CZK','HUF','PLN','RON'], true)): ?>
                                                    <option value="<?= h($curCur) ?>" selected><?= h($curCur) ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </label>
                                </div>
                        </div>
                        <div id="dgcBlockPast" class="mt8">
                            <?php
                              $dgc = (string)($form['downgrade_occurred'] ?? '');
                              $basisCur = (string)($form['downgrade_comp_basis'] ?? '');
                              $shareCur = (string)($form['downgrade_segment_share'] ?? (string)$share);
                            ?>
                            <input type="hidden" name="downgrade_occurred" value="<?= h($dgc) ?>" />
                            <input type="hidden" name="downgrade_comp_basis" value="<?= h($basisCur) ?>" />
                            <input type="hidden" name="downgrade_segment_share" value="<?= h($shareCur) ?>" />
                            <div class="small"><strong>Nedgradering</strong> (opsummering fra Trin 3; rediger i Trin 3)</div>
                            <div class="small">Status: <?= $dgc!=='' ? h($dgc) : '—' ?> · Basis: <?= $basisCur!=='' ? h($basisCur) : '—' ?> · Andel: <?= number_format((float)$shareCur, 3) ?> (basis <?= h($form['downgrade_segment_share_basis'] ?? 'time') ?>)</div>
                            <div class="mt4 small">Billetpris (fra TRIN 3): <strong><?= number_format($tp, 2) ?></strong></div>
                            <div class="mt4 small">Forventet delvis tilbagebetaling (Bilag II): <strong id="downgrade-preview-past"><?= number_format($preview, 2) ?></strong></div>
                        </div>
                    </div>
            </div>

            <?php if (!$showArt183): ?>
                <div class="small mt8" style="background:#fff3cd; padding:6px; border-radius:6px;">⚠️ 100-minutters-reglen (Art. 18(3)) er undtaget for denne rejse. Vi skjuler spørgsmålet og anvender alternative vurderinger.</div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
        <strong>Rejsen er ikke afsluttet — hvad ønsker du nu?</strong>
        <div class="small muted" style="margin-top:6px;">I en presset situation giver vi dig overblik over dine muligheder.</div>
        <?php $remedy = (string)($form['remedyChoice'] ?? ''); if ($remedy==='') { if (($form['trip_cancelled_return_to_origin'] ?? '')==='yes') { $remedy='refund_return'; } elseif (($form['reroute_same_conditions_soonest'] ?? '')==='yes') { $remedy='reroute_soonest'; } elseif (($form['reroute_later_at_choice'] ?? '')==='yes') { $remedy='reroute_later'; } } ?>
        <div class="mt8"><strong>Vælg præcis én mulighed</strong></div>
        <label><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Ønsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?</label><br/>
        <label><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Ønsker du omlægning på tilsvarende vilkår ved først givne lejlighed?</label><br/>
        <label><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Ønsker du omlægning til et senere tidspunkt efter eget valg?</label>

        <!-- Hidden sync to legacy hooks -->
        <input type="hidden" id="tcr_sync_now" name="trip_cancelled_return_to_origin" value="<?= ($form['trip_cancelled_return_to_origin'] ?? '') ?>" />
        <input type="hidden" id="rsc_sync_now" name="reroute_same_conditions_soonest" value="<?= ($form['reroute_same_conditions_soonest'] ?? '') ?>" />
        <input type="hidden" id="rlc_sync_now" name="reroute_later_at_choice" value="<?= ($form['reroute_later_at_choice'] ?? '') ?>" />

        <div id="returnExpenseNow" class="mt8 <?= $remedy==='refund_return' ? '' : 'hidden' ?>">
            <div class="mt8"><strong>Returtransport (Art. 18 stk. 1)</strong></div>
            <?php $rtFlagNow = (string)($form['return_to_origin_expense'] ?? ''); ?>
            <div class="mt4">Havde du udgifter til at komme tilbage til udgangspunktet?</div>
            <label><input type="radio" name="return_to_origin_expense" value="no" <?= $rtFlagNow==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="return_to_origin_expense" value="yes" <?= $rtFlagNow==='yes'?'checked':'' ?> /> Ja</label>
            <div class="grid-2 mt8" id="returnExpenseFieldsNow" style="<?= $rtFlagNow==='yes' ? '' : 'display:none;' ?>">
                <label>Beløb
                    <input type="number" step="0.01" name="return_to_origin_amount" value="<?= h($form['return_to_origin_amount'] ?? '') ?>" />
                </label>
                <label>Valuta
                    <input type="text" name="return_to_origin_currency" value="<?= h($form['return_to_origin_currency'] ?? '') ?>" placeholder="<?= h($currency ?? 'EUR') ?>" />
                </label>
            </div>
        </div>

        <div id="rerouteSectionNow" class="mt12 <?= in_array($remedy, ['reroute_soonest','reroute_later'], true) ? '' : 'hidden' ?>">
            <div class="mt0"><strong>Omlægning</strong></div>
            <?php $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); $show100Now = $showArt183 && $remedy==='reroute_soonest'; ?>
            <div id="ri100NowWrap" style="<?= $show100Now ? '' : 'display:none;' ?>" data-art="18(3)">
                <div class="mt8">Er du blevet informeret om mulighederne for omlægning inden for 100 minutter efter planlagt afgang? (Art. 18(3))
                    <span class="small muted">Vi bruger planlagt afgang + første omlægnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="unknown" <?= ($ri100===''||$ri100==='unknown')?'checked':'' ?> /> Ved ikke</label>
            </div>

                        <!-- OFF-variant additions: always simple branch without 100-min dependency -->
                        <fieldset id="offerProvidedWrapNow" class="mt8" <?= $showArt183 ? 'hidden' : '' ?> >
                            <legend>Fik du et konkret omlægningstilbud fra operatøren?</legend>
                            <?php $offProv = (string)($form['offer_provided'] ?? ''); ?>
                            <label><input type="radio" name="offer_provided" value="yes" <?= $offProv==='yes'?'checked':'' ?> /> Ja</label>
                            <label class="ml8"><input type="radio" name="offer_provided" value="no" <?= $offProv==='no'?'checked':'' ?> /> Nej</label>
                            <label class="ml8"><input type="radio" name="offer_provided" value="unknown" <?= ($offProv===''||$offProv==='unknown')?'checked':'' ?> /> Ved ikke</label>
                        </fieldset>

            <?php $spt = (string)($form['self_purchased_new_ticket'] ?? ''); ?>
                        <div id="step2Now" style="display:none">
                <div class="mt8">Køber du selv en ny billet for at komme videre?</div>
                <label><input type="radio" name="self_purchased_new_ticket" value="yes" <?= $spt==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="self_purchased_new_ticket" value="no" <?= $spt==='no'?'checked':'' ?> /> Nej</label>
                <div id="selfBuyNoteNow" class="small" style="margin-top:6px; padding:6px; background:#fff3cd; border-radius:6px; display:none;">Hvis du selv køber billet, selvom operatøren tilbød omlægning inden for 100 min, refunderes den normalt ikke (Art. 18(3)). Kompensation kan stadig være mulig.</div>
            </div>

                        <fieldset id="opApprovalWrapNow" class="mt8" hidden>
                            <legend>Var selvkøbet godkendt af operatøren?</legend>
                            <?php $opOK = (string)($form['self_purchase_approved_by_operator'] ?? ''); ?>
                            <label><input type="radio" name="self_purchase_approved_by_operator" value="yes" <?= $opOK==='yes'?'checked':'' ?> /> Ja</label>
                            <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="no" <?= $opOK==='no'?'checked':'' ?> /> Nej</label>
                            <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="unknown" <?= ($opOK===''||$opOK==='unknown')?'checked':'' ?> /> Ved ikke</label>
                        </fieldset>
                        <div id="notesAreaNow" class="notes small">
                            <p id="noteApprovedNow" class="note success" style="display:none;">✓ Selvkøb er oplyst som godkendt af operatøren.</p>
                            <p id="noteNotRefundableNow" class="note warn" style="display:none;">⚠️ Selvkøb uden operatørens godkendelse refunderes normalt ikke.</p>
                        </div>

            <div id="recBlockNow">
                <?php $rec = (string)($form['reroute_extra_costs'] ?? ''); ?>
                <div class="mt8">Kommer omlægningen til at medføre ekstra udgifter for dig? (højere klasse/andet transportmiddel)</div>
                <label><input type="radio" name="reroute_extra_costs" value="yes" <?= $rec==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_extra_costs" value="no" <?= $rec==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_extra_costs" value="unknown" <?= ($rec===''||$rec==='unknown')?'checked':'' ?> /> Ved ikke</label>
                <div class="grid-2 mt8 <?= $rec==='yes' ? '' : 'hidden' ?>" id="recWrapNow">
                    <label>Beløb
                        <input type="number" step="0.01" name="reroute_extra_costs_amount" value="<?= h($form['reroute_extra_costs_amount'] ?? '') ?>" />
                    </label>
                    <label>Valuta
                        <?php $curCur = strtoupper(trim((string)($form['reroute_extra_costs_currency'] ?? ''))); ?>
                        <select name="reroute_extra_costs_currency">
                            <option value="">Auto</option>
                            <?php foreach (['EUR','DKK','SEK','BGN','CZK','HUF','PLN','RON'] as $cc): ?>
                                <option value="<?= $cc ?>" <?= $curCur===$cc?'selected':'' ?>><?= $cc ?></option>
                            <?php endforeach; ?>
                            <?php if ($curCur !== '' && !in_array($curCur, ['EUR','DKK','SEK','BGN','CZK','HUF','PLN','RON'], true)): ?>
                                <option value="<?= h($curCur) ?>" selected><?= h($curCur) ?></option>
                            <?php endif; ?>
                        </select>
                    </label>
                </div>
                        </div>


                        <div id="dgcBlockNow" style="display:none;">
                            <!-- Nedgradering håndteres i Trin 3; behold hidden felter for dataflow -->
                            <input type="hidden" name="downgrade_occurred" value="<?= h((string)($form['downgrade_occurred'] ?? '')) ?>" />
                            <input type="hidden" name="downgrade_comp_basis" value="<?= h((string)($form['downgrade_comp_basis'] ?? '')) ?>" />
                            <input type="hidden" name="downgrade_segment_share" value="<?= h((string)($form['downgrade_segment_share'] ?? $share)) ?>" />
                        </div>

            <?php if (!$showArt183): ?>
                <div class="small mt8" style="background:#fff3cd; padding:6px; border-radius:6px;">⚠️ 100-minutters-reglen (Art. 18(3)) er undtaget for denne rejse. Vi skjuler spørgsmålet og anvender alternative vurderinger.</div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
// TRIN 4 fallback: give immediate UI feedback (disable/enable Continue)
(function(){
    var hasFallback = <?= !empty($showArt18Fallback) ? 'true' : 'false' ?>;
    function onA18Change(){
        var v = (document.querySelector('input[name="art18_expected_delay_60"]:checked')||{}).value || '';
        var btn = document.getElementById('choicesSubmitBtn');
        var warn = document.getElementById('art18NoWarn');
        var hint = document.getElementById('art18YesHint');
        var core = document.getElementById('coreAfterArt18');
        var fb = document.getElementById('art18FallbackCard');
        if (v === 'no') {
            if (btn) { btn.disabled = true; btn.setAttribute('aria-disabled','true'); }
            if (warn) warn.style.display = '';
            if (hint) hint.style.display = 'none';
            if (core) core.style.display = 'none';
            if (fb) fb.style.display = '';
        } else {
            if (btn) { btn.disabled = false; btn.removeAttribute('aria-disabled'); }
            if (warn) warn.style.display = 'none';
            if (hint) hint.style.display = (v === 'yes') ? '' : 'none';
            if (core) {
                if (!hasFallback) {
                    core.style.display = '';
                } else {
                    core.style.display = (v === 'yes' || v === '') ? '' : 'none';
                }
            }
            if (fb && hasFallback) {
                // Skjul kortet når der svares Ja for et renere layout
                fb.style.display = (v === 'yes') ? 'none' : '';
            }
        }
    }
    document.addEventListener('DOMContentLoaded', function(){
        ['change','click'].forEach(function(ev){
            document.querySelectorAll('input[name="art18_expected_delay_60"]').forEach(function(r){ r.addEventListener(ev, onA18Change); });
        });
        onA18Change();
    });
})();
</script>

<script>
// TRIN 4 (Art. 18): klientlogik for valg og sektioner
(function(){
    function s7Update() {
        var remEl = document.querySelector('input[name="remedyChoice"]:checked');
        var val = remEl ? remEl.value : '';
        ['Past','Now'].forEach(function(suf){
            var returnExp = document.getElementById('returnExpense' + suf);
        var reroute = document.getElementById('rerouteSection' + suf);
        if (returnExp) returnExp.classList.toggle('hidden', val !== 'refund_return');
        if (reroute) reroute.classList.toggle('hidden', !(val === 'reroute_soonest' || val === 'reroute_later'));
        var tcr = document.getElementById('tcr_sync_' + suf.toLowerCase());
        var rsc = document.getElementById('rsc_sync_' + suf.toLowerCase());
            var rlc = document.getElementById('rlc_sync_' + suf.toLowerCase());
            if (tcr) tcr.value = (val === 'refund_return') ? 'yes' : '';
            if (rsc) rsc.value = (val === 'reroute_soonest') ? 'yes' : '';
            if (rlc) rlc.value = (val === 'reroute_later') ? 'yes' : '';
            // 100-min kun for reroute_soonest
            var riWrap = document.getElementById('ri100' + suf + 'Wrap');
            if (riWrap) { riWrap.style.display = (val === 'reroute_soonest') ? '' : 'none'; }
        });
        // Vis Art.20(4) når en gren er valgt
        var art20 = document.getElementById('art20Wrapper');
        if (art20) { art20.style.display = (val !== '') ? '' : 'none'; }
        // Detail toggles
        var recChecked = document.querySelector('input[name="reroute_extra_costs"]:checked');
        var dgcChecked = document.querySelector('input[name="downgrade_occurred"]:checked');
        var recVal = recChecked ? recChecked.value : null;
        var dgcVal = dgcChecked ? dgcChecked.value : null;
    var recPast = document.getElementById('recWrapPast');
    var recNow = document.getElementById('recWrapNow');
    var dgcPast = document.getElementById('dgcWrapPast');
    var dgcNow = document.getElementById('dgcWrapNow');
    var recBlockPast = document.getElementById('recBlockPast');
    var recBlockNow = document.getElementById('recBlockNow');
    var dgcBlockPast = document.getElementById('dgcBlockPast');
    var dgcBlockNow = document.getElementById('dgcBlockNow');
    // (deferred toggles moved after scenario adjustments)

        // Art. 18(3) conditional: distinguish who rerouted vs self-purchase
        var ri100El = document.querySelector('input[name="reroute_info_within_100min"]:checked');
        var ri100 = ri100El ? ri100El.value : '';
        var selfBuyEl = document.querySelector('input[name="self_purchased_new_ticket"]:checked');
        var selfBuy = selfBuyEl ? selfBuyEl.value : '';
        var opApprEl = document.querySelector('input[name="self_purchase_approved_by_operator"]:checked');
    var opAppr = opApprEl ? opApprEl.value : '';
    var notePast = document.getElementById('selfBuyNotePast');
    var noteNow = document.getElementById('selfBuyNoteNow');
    var apprPast = document.getElementById('opApprovalWrapPast');
    var apprNow = document.getElementById('opApprovalWrapNow');
var noteApprovedPast = document.getElementById('noteApprovedPast');
var noteApprovedNow = document.getElementById('noteApprovedNow');
var returnPast = document.getElementById('returnExpensePast');
var returnNow = document.getElementById('returnExpenseNow');
var returnFieldsPast = document.getElementById('returnExpenseFieldsPast');
var returnFieldsNow = document.getElementById('returnExpenseFieldsNow');
var advPast = document.getElementById('advPast');
var advNow = document.getElementById('advNow');
var live = document.getElementById('rerouteLive');

        function setRadio(name, value) {
            var els = document.querySelectorAll('input[name="'+name+'"]');
            els.forEach(function(r){ r.checked = (r.value === value); });
        }
        function disableGroup(name, disabled) {
            document.querySelectorAll('input[name="'+name+'"]').forEach(function(r){
                r.disabled = !!disabled;
                var lbl = r.closest('label');
                if (lbl) { lbl.style.opacity = disabled ? 0.6 : 1; }
            });
        }
        function setBlockDisabled(id, disabled){
            var el = document.getElementById(id);
            if (!el) return;
            if (disabled) { el.classList.add('disabled-block'); el.setAttribute('aria-disabled','true'); }
            else { el.classList.remove('disabled-block'); el.removeAttribute('aria-disabled'); }
        }

        // Feature flag for Art. 18(3) (true = ON). When OFF we collapse logic to simple self-purchase approval gating.
        var artFlags = (window.__artFlags && window.__artFlags.art) || {};
        var a183On = !(artFlags.hasOwnProperty('art18_3') && artFlags['art18_3'] === false);

        // Progressive visibility
        // Step 1 (only when 18(3) is ON) → Step 2: show Step 2 only when answered OR 18(3) OFF
        var step1Answered = a183On ? !!ri100 : true;
        var step2Past = document.getElementById('step2Past');
        var step2Now = document.getElementById('step2Now');
    var advBox = document.getElementById('advToggle');
    var advOn = !!(advBox && advBox.checked);
    if (step2Past) step2Past.style.display = (step1Answered || (advPast && advPast.open) || advOn) ? '' : 'none';
    if (step2Now) step2Now.style.display = (step1Answered || (advNow && advNow.open) || advOn) ? '' : 'none';

        // Step 2 → Branch: hide detail blocks until Step 2 is answered (only explicit yes/no)
        var step2Answered = (selfBuy === 'yes' || selfBuy === 'no');
        if ((advPast && advPast.open) || advOn) {
            if (recBlockPast) recBlockPast.style.display = '';
            if (dgcBlockPast) dgcBlockPast.style.display = '';
        } else {
            if (recBlockPast) recBlockPast.style.display = step2Answered ? '' : 'none';
            if (dgcBlockPast) dgcBlockPast.style.display = step2Answered ? '' : 'none';
        }
        if ((advNow && advNow.open) || advOn) {
            if (recBlockNow) recBlockNow.style.display = '';
            if (dgcBlockNow) dgcBlockNow.style.display = 'none';
        } else {
            if (recBlockNow) recBlockNow.style.display = step2Answered ? '' : 'none';
            if (dgcBlockNow) dgcBlockNow.style.display = 'none';
        }
        if (live) { live.textContent = step2Answered ? 'Trin 2 besvaret – gren valgt' : (step1Answered ? 'Trin 1 besvaret – vis trin 2' : 'Trin 1 endnu ikke besvaret'); }

        // Reset any prior locks (ensures toggling between scenarios clears stale states)
        disableGroup('reroute_extra_costs', false);
        disableGroup('downgrade_occurred', false);
        setBlockDisabled('recBlockPast', false);
        setBlockDisabled('recBlockNow', false);
        setBlockDisabled('dgcBlockPast', false);
        setBlockDisabled('dgcBlockNow', false);

        // Reset approval UI first
        if (apprPast) apprPast.hidden = (selfBuy !== 'yes');
        if (apprNow) apprNow.hidden = (selfBuy !== 'yes');
        if (noteApprovedPast) noteApprovedPast.style.display='none';
        if (noteApprovedNow) noteApprovedNow.style.display='none';
        // Return-to-origin expense fields toggle (shared radio group)
        var rtVal = (document.querySelector('input[name="return_to_origin_expense"]:checked') || {}).value || '';
        var rtPastFields = document.getElementById('returnExpenseFieldsPast');
        var rtNowFields = document.getElementById('returnExpenseFieldsNow');
        var showRt = (rtVal === 'yes');
        if (rtPastFields) { rtPastFields.style.display = showRt ? '' : 'none'; }
        if (rtNowFields) { rtNowFields.style.display = showRt ? '' : 'none'; }
        // Attach live listener so toggle happens immediately on click
        var rtRadios = document.querySelectorAll('input[name="return_to_origin_expense"]');
        rtRadios.forEach(function(r){
          r.addEventListener('change', function(){
            var val = this.value || '';
            var show = (val === 'yes');
            if (rtPastFields) rtPastFields.style.display = show ? '' : 'none';
            if (rtNowFields) rtNowFields.style.display = show ? '' : 'none';
          });
        });

        // Scenario logic differs when Art. 18(3) OFF:
        // OFF: extra costs only if selfBuy==yes AND operator approved (opAppr==yes). Downgrade always shown for reroute.
        // ON: retain legacy 100-min branching (C/A/B cases).
        if (a183On) {
            // Scenario C: offered within 100 AND self purchased
            if (ri100 === 'yes' && selfBuy === 'yes') {
                if (opAppr === 'yes') {
                    // Operator approved self-purchase: allow extra costs to be claimed; keep downgrade off
                    if (!recVal || recVal === 'unknown') { setRadio('reroute_extra_costs', 'yes'); }
                    disableGroup('reroute_extra_costs', false);
                    setBlockDisabled('recBlockPast', false);
                    setBlockDisabled('recBlockNow', false);
                    setRadio('downgrade_occurred', 'no');
                    disableGroup('downgrade_occurred', true);
                    setBlockDisabled('dgcBlockPast', true);
                    setBlockDisabled('dgcBlockNow', true);
                    if (dgcPast) dgcPast.style.display = 'none';
                    if (dgcNow) dgcNow.style.display = 'none';
                    if (notePast) notePast.style.display = 'none';
                    if (noteNow) noteNow.style.display = 'none';
                } else {
                    // Self-purchase without approval when reroute was offered -> lock out extra costs
                    setRadio('reroute_extra_costs', 'no');
                    disableGroup('reroute_extra_costs', true); // locked
                    setBlockDisabled('recBlockPast', true);
                    setBlockDisabled('recBlockNow', true);
                    setRadio('downgrade_occurred', 'no');
                    disableGroup('downgrade_occurred', true);
                    setBlockDisabled('dgcBlockPast', true);
                    setBlockDisabled('dgcBlockNow', true);
                    if (dgcPast) dgcPast.style.display = 'none';
                    if (dgcNow) dgcNow.style.display = 'none';
                    if (notePast) notePast.style.display = '';
                    if (noteNow) noteNow.style.display = '';
                }
            }
            // Scenario A: NOT offered within 100 AND self purchased -> extra costs allowed; hide downgrade
            else if ((ri100 === 'no' || ri100 === 'unknown') && selfBuy === 'yes') {
            setRadio('reroute_extra_costs', 'yes');
            disableGroup('reroute_extra_costs', false); // user can edit amount
            setBlockDisabled('recBlockPast', false);
            setBlockDisabled('recBlockNow', false);
            setRadio('downgrade_occurred', 'no');
            disableGroup('downgrade_occurred', true);
            setBlockDisabled('dgcBlockPast', true);
            setBlockDisabled('dgcBlockNow', true);
            if (dgcPast) dgcPast.style.display = 'none';
            if (dgcNow) dgcNow.style.display = 'none';
            if (notePast) notePast.style.display = 'none';
            if (noteNow) noteNow.style.display = 'none';
            }
            // Scenario B: offered within 100 AND did not self purchase -> default no extra costs; show downgrade
            else if (ri100 === 'yes' && (selfBuy === 'no')) {
            // Default only if user hasn't chosen yet (or chose 'unknown'); don't override an explicit Yes/No selection
            if (!recVal || recVal === 'unknown') {
                setRadio('reroute_extra_costs', 'no');
            }
            disableGroup('reroute_extra_costs', false);
            setBlockDisabled('recBlockPast', false);
            setBlockDisabled('recBlockNow', false);
            disableGroup('downgrade_occurred', false);
            setBlockDisabled('dgcBlockPast', false);
            setBlockDisabled('dgcBlockNow', false);
            if (notePast) notePast.style.display = 'none';
            if (noteNow) noteNow.style.display = 'none';
                // downgrade question remains visible based on user's choice
            } else {
                // fallback: keep groups enabled and notes hidden
                disableGroup('reroute_extra_costs', false);
                disableGroup('downgrade_occurred', false);
                setBlockDisabled('recBlockPast', false);
                setBlockDisabled('recBlockNow', false);
                setBlockDisabled('dgcBlockPast', false);
                setBlockDisabled('dgcBlockNow', false);
                if (notePast) notePast.style.display = 'none';
                if (noteNow) noteNow.style.display = 'none';
            }
        } else {
            // OFF variant
            if (selfBuy === 'yes') {
                if (opAppr === 'yes') {
                    // Allow extra costs
                    disableGroup('reroute_extra_costs', false);
                    setBlockDisabled('recBlockPast', false);
                    setBlockDisabled('recBlockNow', false);
                    if (noteApprovedPast) noteApprovedPast.style.display='';
                    if (noteApprovedNow) noteApprovedNow.style.display='';
                } else {
                    // Force no extra costs
                    setRadio('reroute_extra_costs','no');
                    disableGroup('reroute_extra_costs', true);
                    setBlockDisabled('recBlockPast', true);
                    setBlockDisabled('recBlockNow', true);
                }
            } else {
                // Not self purchase: keep groups available (user may be downgraded)
                disableGroup('reroute_extra_costs', false);
                setBlockDisabled('recBlockPast', false);
                setBlockDisabled('recBlockNow', false);
            }
        }

        // If advanced view is open, show everything but keep disabled state as set above
        if ((advPast && advPast.open) || advOn) {
            if (recBlockPast) recBlockPast.style.display = '';
            if (dgcBlockPast) dgcBlockPast.style.display = '';
        }
        if ((advNow && advNow.open) || advOn) {
            if (recBlockNow) recBlockNow.style.display = '';
            if (dgcBlockNow) dgcBlockNow.style.display = '';
        }

    // Now apply inner wrap visibility according to the current radio states (style.display to override any inline)
        if (recPast) recPast.classList.remove('hidden');
        if (recNow) recNow.classList.remove('hidden');
        if (dgcPast) dgcPast.classList.remove('hidden');
        if (dgcNow) { dgcNow.classList.add('hidden'); dgcNow.style.display='none'; }
    recChecked = document.querySelector('input[name="reroute_extra_costs"]:checked');
    dgcChecked = document.querySelector('input[name="downgrade_occurred"]:checked');
    recVal = recChecked ? recChecked.value : null;
    dgcVal = dgcChecked ? dgcChecked.value : null;
    if (recPast) recPast.style.display = (recVal === 'yes') ? '' : 'none';
    if (recNow) recNow.style.display = (recVal === 'yes') ? '' : 'none';
    if (dgcPast) dgcPast.style.display = (dgcVal === 'yes') ? '' : 'none';
    if (dgcNow) dgcNow.style.display = 'none';

        // Class/reservation dynamic toggles (moved fields)
        // (Class/reservation UI now only in TRIN 3 — no toggles needed here)

        // Exemptions gating: hide 100-min question blocks when Art. 18(3) doesn't apply
        try {
            var art = (window.__artFlags && window.__artFlags.art) || {};
            var a183 = !(art.hasOwnProperty('art18_3') && art['art18_3'] === false) ;
            var riPast = document.getElementById('ri100PastWrap');
            var riNow = document.getElementById('ri100NowWrap');
            var offProvPast = document.getElementById('offerProvidedWrapPast');
            var offProvNow = document.getElementById('offerProvidedWrapNow');
            if (riPast) riPast.classList.toggle('hidden', !a183);
            if (riNow) riNow.classList.toggle('hidden', !a183);
            if (offProvPast) offProvPast.hidden = a183; // only show in OFF variant
            if (offProvNow) offProvNow.hidden = a183;
        } catch(e) { /* no-op */ }

        // Live downgrade preview (Annex II) for both Past/Now blocks if present
        try {
            var rate = { seat: 0.25, couchette: 0.50, sleeper: 0.75 };
            var tp = <?= json_encode($tp) ?>;
            function clamp01(x){ var n = parseFloat(x); if (isNaN(n)) n = 0; return Math.max(0, Math.min(1, n)); }
            function updOne(suf){
                var wrap = document.getElementById('dgcWrap' + suf);
                if (!wrap || wrap.classList.contains('hidden')) return;
                var sel = document.getElementById('downgrade_comp_basis_' + suf.toLowerCase());
                var basis = sel ? sel.value : '';
                var shareEl = wrap.querySelector('input[name="downgrade_segment_share"]');
                var share = clamp01(shareEl ? shareEl.value : 1);
                var r = rate[basis] || 0;
                var val = Math.round(tp * r * share * 100)/100;
                var out = document.getElementById('downgrade-preview-' + suf.toLowerCase());
                if (out) out.textContent = val.toFixed(2);
            }
            updOne('Past');
            updOne('Now');
        } catch(e) { /* no-op */ }
    }
    document.addEventListener('DOMContentLoaded', function(){
        // Expose exemption flags like in one.php
        try {
            window.__artFlags = window.__artFlags || {};
            window.__artFlags.art = <?= json_encode($profile['articles'] ?? []) ?>;
            window.__artFlags.scope = <?= json_encode($profile['scope'] ?? '') ?>;
        } catch(e) { /* no-op */ }
        s7Update();
        document.querySelectorAll('input[name="remedyChoice"], input[name="reroute_extra_costs"], input[name="downgrade_occurred"], input[name="reroute_info_within_100min"], input[name="self_purchased_new_ticket"], input[name="self_purchase_approved_by_operator"], input[name="offer_provided"]').forEach(function(el){
            ['change','click','input'].forEach(function(ev){ el.addEventListener(ev, s7Update); });
        });
    // (No class/reservation fields in TRIN 4 now)
        var advPast = document.getElementById('advPast');
        var advNow = document.getElementById('advNow');
        if (advPast) advPast.addEventListener('toggle', s7Update);
        if (advNow) advNow.addEventListener('toggle', s7Update);
        ['downgrade_comp_basis_past','downgrade_comp_basis_now'].forEach(function(id){ var el = document.getElementById(id); if (el) el.addEventListener('change', s7Update); });
        document.querySelectorAll('input[name="downgrade_segment_share"]').forEach(function(el){ el.addEventListener('input', s7Update); });
        var delayRadios = document.querySelectorAll('input[name="delay_confirmation_received"]');
        var delayUploadBlock = document.getElementById('delayConfirmationUploadBlock');
        var toggleDelayUpload = function(){
            if (!delayUploadBlock) { return; }
            var checked = document.querySelector('input[name="delay_confirmation_received"]:checked');
            delayUploadBlock.style.display = (checked && checked.value === 'yes') ? '' : 'none';
        };
        delayRadios.forEach(function(r){ r.addEventListener('change', toggleDelayUpload); });
        toggleDelayUpload();
    });
})();
</script>

<!-- Progressive reveal: Advanced toggle (checkbox) + optional details -->
<div class="mt8">
    <label><input type="checkbox" id="advToggle" /> Vis alt (avanceret)</label>
    <span class="small muted ml8">For power users: viser alle felter; låste værdier forbliver låst.</span>
</div>
<details id="advPast" class="mt8"><summary>Avanceret (afsluttet rejse)</summary><div class="small muted">Supplerende overblik, valgfrit.</div></details>
<details id="advNow" class="mt8"><summary>Avanceret (igangværende rejse)</summary><div class="small muted">Supplerende overblik, valgfrit.</div></details>
<div id="rerouteLive" aria-live="polite" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">Init</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('← Tilbage', ['action' => 'entitlements'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsæt →', ['id' => 'choicesSubmitBtn', 'class' => 'button', 'type' => 'submit', 'aria-label' => 'Fortsæt til næste trin', 'formnovalidate' => true]) ?>
    <?= $this->Html->link('Spring over →', ['controller' => 'Flow', 'action' => 'assistance'], ['class' => 'button', 'style' => 'background:#f5f5f5; color:#333;', 'title' => 'Gå til næste trin uden at gemme ændringer']) ?>
    <input type="hidden" name="_choices_submitted" value="1" />
</div>

<?= $this->Form->end() ?>

<?php else: // art18Blocked ?>
    <div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
        <?= $this->Html->link('← Tilbage', ['action' => 'screening'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
        <?= $this->Form->button('Fortsæt →', ['class' => 'button', 'disabled' => true, 'aria-disabled' => 'true', 'title' => 'Ikke muligt at fortsætte – krav ikke opfyldt']) ?>
    </div>
    <?= $this->Form->end() ?>
<?php endif; ?>
