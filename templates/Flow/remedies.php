<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$art18Active = $art18Active ?? true;
$art18Blocked = $art18Blocked ?? false;
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');
$remediesTitle = $isOngoing
    ? 'TRIN 6 - Dine valg (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 6 - Dine valg (afsluttet rejse)' : 'TRIN 6 - Dine valg (Art. 18)');
$art18Title = $isOngoing ? 'Rejsen er i gang - hvad er dit valg nu?' : 'Rejsen er afsluttet - hvad skete der?';
$art18Help = $isOngoing ? 'Ud fra din nuvaerende situation kan foelgende muligheder vaere relevante.' : 'Ved afgang, missed connection eller aflysning - ved forsinkelse paa 60+ min. tilbydes nedenstaaende muligheder.';
$decisionHint = $isOngoing ? 'Foreloebige valg baseret paa nuvaerende situation.' : ($isCompleted ? 'Endelige valg baseret paa hvad der skete.' : '');
$downgradeHint = $isOngoing ? 'Udfyld kun hvis du allerede er blevet placeret i lavere klasse eller mistede reservation.' : 'Udfyld kun hvis du blev placeret i lavere klasse eller mistede reservation.';
$returnQuestion = $isOngoing ? 'Har du haft - eller forventer du at faa - udgifter til at komme tilbage til udgangspunktet?' : 'Havde du udgifter til at komme tilbage til udgangspunktet?';

$segments = [];
if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) { $segments = (array)$meta['_segments_auto']; }
elseif (!empty($meta['_segments_all']) && is_array($meta['_segments_all'])) { $segments = (array)$meta['_segments_all']; }
$stations = [];
$addStation = function ($val) use (&$stations) {
    $s = trim((string)$val);
    if ($s === '') { return; }
    $stations[$s] = true;
};
foreach ($segments as $seg) {
    if (!is_array($seg)) { continue; }
    foreach (['from','to','origin','destination','dep_station','arr_station','departureStation','arrivalStation'] as $k) {
        if (isset($seg[$k])) { $addStation($seg[$k]); }
    }
}
$addStation($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''));
$addStation($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''));
$stationOptions = array_keys($stations);
sort($stationOptions, SORT_NATURAL | SORT_FLAG_CASE);
?>

<style>
    .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
    .hidden { display:none; }
    .small { font-size:12px; }
    .muted { color:#666; }
    .mt4 { margin-top:4px; }
    .mt8 { margin-top:8px; }
    .mt12 { margin-top:12px; }
    .ml8 { margin-left:8px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .disabled-block { opacity: 0.6; }
    .flow-wrapper { max-width: 1100px; margin: 0 auto; }
    .flow-wide { width: 100%; }
    .card-title { display:flex; align-items:center; gap:8px; font-weight:600; }
    .card-title .icon { font-size:16px; line-height:1; }
</style>
<div class="flow-wrapper">
<h1><?= h($remediesTitle) ?></h1>
<?php
    if ($travelState === 'completed') {
        echo '<p class="small muted">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';
    } elseif ($travelState === 'ongoing') {
        echo '<p class="small muted">Status: Rejsen er i gang. Vi samler dine valg for resten af forloebet.</p>';
    } elseif ($travelState === 'not_started') {
        echo '<p class="small muted">Status: Rejsen er endnu ikke paabegyndt. Besvar ud fra, hvad du forventer at goere ved forsinkelse/aflysning.</p>';
    }
?>
<?php
    $articles = (array)($profile['articles'] ?? []);
    $showArt18  = !isset($articles['art18'])   || $articles['art18']   !== false;
    $showArt181 = !isset($articles['art18_1']) || $articles['art18_1'] !== false;
    $showArt182 = !isset($articles['art18_2']) || $articles['art18_2'] !== false;
    $showArt183 = !isset($articles['art18_3']) || $articles['art18_3'] !== false;
    $v = fn(string $k): string => (string)($form[$k] ?? '');
?>
<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'remedies'], 'type' => 'file', 'novalidate' => true]) ?>
<input type="hidden" name="journey_outcome" value="<?= h((string)($form['journey_outcome'] ?? '')) ?>" />

<?php if (!$art18Active): ?>
    <div class="card mt12" style="padding:12px; border:1px solid #ddd; background:#fff3cd; border-radius:6px;">
        <?php if ($art18Blocked): ?>
            <strong>Art. 18 er ikke aktiveret.</strong>
            <div class="small muted">Betingelserne er ikke opfyldt ud fra dine svar i Trin 4.</div>
        <?php else: ?>
            <strong>Art. 18 afventer gating.</strong>
            <div class="small muted">Ga tilbage til Trin 4 og udfyld haendelsen (inkl. 60-min. varsel), eller til Trin 3 hvis PMR/cykel skal aktivere Art. 18.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div id="coreAfterArt18">
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

<?php
    $remedy = (string)($form['remedyChoice'] ?? '');
    if ($remedy === '') {
        if (($form['trip_cancelled_return_to_origin'] ?? '') === 'yes') { $remedy = 'refund_return'; }
        elseif (($form['reroute_same_conditions_soonest'] ?? '') === 'yes') { $remedy = 'reroute_soonest'; }
        elseif (($form['reroute_later_at_choice'] ?? '') === 'yes') { $remedy = 'reroute_later'; }
    }
    $ri100 = (string)($form['reroute_info_within_100min'] ?? '');
?>
    <?php $journeyOutcomeVal = (string)($form['journey_outcome'] ?? ''); ?>
    <?php if ($journeyOutcomeVal === ''): ?>
        <div class="card mt12" style="padding:12px; border:1px solid #ddd; background:#fff3cd; border-radius:6px;">
            <strong>Tip:</strong>
            <div class="small muted">Hvis du udfylder TRIN 5 (Transport), kan vi give en bedre anbefaling i TRIN 6. Du kan stadig fortsatte her.</div>
        </div>
    <?php endif; ?>

    <div id="art18Wrapper" class="<?= $art18Active ? '' : 'hidden' ?>">
        <div id="art18OutcomePrompt" class="card mt12 <?= $journeyOutcomeVal === '' ? '' : 'hidden' ?>" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
            <strong>Udfald i TRIN 5 mangler.</strong>
            <div class="small muted">Valgfrit: ga tilbage og udfyld TRIN 5 for at faa en anbefaling.</div>
        </div>
        <div id="art18Flow">
    <div class="card <?= ($showArt18 && $showArt181) ? '' : 'hidden' ?>" data-art="18(1)">
        <div class="card-title"><span class="icon">&#127919;</span><span><?= h($art18Title) ?></span></div>
        <div class="small muted" style="margin-top:6px;"><?= h($art18Help) ?></div>
        <div id="remedyHint" class="small muted mt8"></div>
        <div class="mt8" data-art="18(1)"><strong>V&aelig;lg pr&aelig;cis en mulighed</strong></div>
        <label data-art="18(1a)"><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Jeg &oslash;nsker refusion</label><br/>
        <label data-art="18(1b)"><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Jeg &oslash;nsker oml&aelig;gning hurtigst muligt</label><br/>
        <label data-art="18(1c)"><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Jeg &oslash;nsker oml&aelig;gning senere (efter eget valg)</label>

        <!-- Hidden sync to legacy hooks -->
        <input type="hidden" id="tcr_sync_past" name="trip_cancelled_return_to_origin" value="<?= ($form['trip_cancelled_return_to_origin'] ?? '') ?>" />
        <input type="hidden" id="rsc_sync_past" name="reroute_same_conditions_soonest" value="<?= ($form['reroute_same_conditions_soonest'] ?? '') ?>" />
        <input type="hidden" id="rlc_sync_past" name="reroute_later_at_choice" value="<?= ($form['reroute_later_at_choice'] ?? '') ?>" />
    </div>

    <div id="remedyFollowupPast" class="mt12">
        <?php $rtFlag = (string)($form['return_to_origin_expense'] ?? ''); ?>
        <div id="returnExpensePast" class="card <?= ($showArt18 && $showArt181 && $remedy==='refund_return') ? '' : 'hidden' ?>" data-art="18(1a)">
            <div class="card-title"><span class="icon">&#8634;</span><span>Returtransport (Art. 18 stk. 1)</span></div>
            <?php if ($decisionHint !== ''): ?>
                <div class="small muted"><?= h($decisionHint) ?></div>
            <?php endif; ?>
            <div class="mt4"><?= h($returnQuestion) ?></div>
            <label><input type="radio" name="return_to_origin_expense" value="no" <?= $rtFlag==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="return_to_origin_expense" value="yes" <?= $rtFlag==='yes'?'checked':'' ?> /> Ja</label>
            <div class="grid-2 mt8" id="returnExpenseFieldsPast" style="<?= $rtFlag==='yes' ? '' : 'display:none;' ?>">
                <label>Bel&oslash;b
                    <input type="number" step="0.01" name="return_to_origin_amount" value="<?= h($form['return_to_origin_amount'] ?? '') ?>" />
                </label>
                <label>Valuta
                    <input type="text" name="return_to_origin_currency" value="<?= h($form['return_to_origin_currency'] ?? '') ?>" placeholder="<?= h($currency ?? 'EUR') ?>" />
                </label>
                <label>Transporttype
                    <?php $rtt = (string)($form['return_to_origin_transport_type'] ?? ''); ?>
                    <select name="return_to_origin_transport_type">
                        <option value="">Vaelg</option>
                        <option value="rail" <?= $rtt==='rail'?'selected':'' ?>>Tog</option>
                        <option value="bus" <?= $rtt==='bus'?'selected':'' ?>>Bus</option>
                        <option value="taxi" <?= $rtt==='taxi'?'selected':'' ?>>Taxi</option>
                        <option value="rideshare" <?= $rtt==='rideshare'?'selected':'' ?>>Samkoersel/rideshare</option>
                        <option value="other" <?= $rtt==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
                <label class="small">Kvittering
                    <input type="file" name="return_to_origin_receipt" accept=".pdf,.jpg,.jpeg,.png" />
                </label>
            </div>
            <?php if ($f = $v('return_to_origin_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        </div>

        <?php $showReroutePast = $showArt18 && ($showArt182 || $showArt183) && in_array($remedy, ['reroute_soonest','reroute_later'], true); ?>
        <div id="rerouteSectionPast" class="card mt12 <?= $showReroutePast ? '' : 'hidden' ?>" data-art="18">
            <div class="card-title"><span class="icon">&#128260;</span><span>Oml&aelig;gning</span></div>
            <?php if ($decisionHint !== ''): ?>
                <div class="small muted"><?= h($decisionHint) ?></div>
            <?php endif; ?>

            <?php
                $needResolutionFallback = trim((string)($form['assistance_alt_to_destination'] ?? '')) === '';
                $assumedResolution = ((string)($form['assistance_alt_to_destination_assumed'] ?? '')) === '1';
                $showResolutionFallback = $needResolutionFallback || $assumedResolution;
            ?>
            <?php if ($showResolutionFallback): ?>
                <div class="mt8 card" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="remedyChoice:reroute_soonest,reroute_later">
                    <div class="small"><strong>Hvor endte du?</strong></div>
                    <div class="small muted mt4">Kun relevant hvis TRIN 5 ikke blev udfyldt. Bruges til at kunne beregne downgrade senere.</div>
                    <?php if ($assumedResolution): ?>
                        <div class="small mt8" style="background:#fff3cd; padding:6px; border-radius:6px;">Vi antog tidligere: <strong>Mit endelige bestemmelsessted</strong>. Ret hvis forkert.</div>
                    <?php else: ?>
                        <div class="small mt8" style="background:#fff3cd; padding:6px; border-radius:6px;">Hvis du ikke vaelger, antager vi <strong>Mit endelige bestemmelsessted</strong>.</div>
                    <?php endif; ?>
                    <div class="grid-2 mt8">
                        <label>Slutpunkt
                            <?php $rtDest = (string)($form['assistance_alt_to_destination'] ?? ''); ?>
                            <select name="assistance_alt_to_destination">
                                <option value="">Vaelg</option>
                                <option value="nearest_station" <?= $rtDest==='nearest_station'?'selected':'' ?>>Naermeste station</option>
                                <option value="other_departure_point" <?= $rtDest==='other_departure_point'?'selected':'' ?>>Et andet egnet afgangssted</option>
                                <option value="final_destination" <?= $rtDest==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
                            </select>
                        </label>
                        <label data-show-if="assistance_alt_to_destination:nearest_station,other_departure_point">Hvilken station endte du ved?
                            <?php $stVal = (string)($form['assistance_alt_arrival_station'] ?? ''); ?>
                            <select name="assistance_alt_arrival_station">
                                <option value="">Vaelg</option>
                                <?php foreach ($stationOptions as $st): ?>
                                    <option value="<?= h($st) ?>" <?= $stVal===$st?'selected':'' ?>><?= h($st) ?></option>
                                <?php endforeach; ?>
                                <option value="unknown" <?= $stVal==='unknown'?'selected':'' ?>>Ved ikke</option>
                                <option value="other" <?= $stVal==='other'?'selected':'' ?>>Anden station</option>
                            </select>
                            <input type="text" name="assistance_alt_arrival_station_other" value="<?= h((string)($form['assistance_alt_arrival_station_other'] ?? '')) ?>" placeholder="Anden station" data-show-if="assistance_alt_arrival_station:other" />
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <div id="ri100PastWrap" class="mt8 <?= $showArt183 ? '' : 'hidden' ?>" data-art="18(3)">
                <div>Fik du besked om mulighederne for oml&aelig;gning inden for 100 minutter? (Art. 18(3))
                    <span class="small muted">Vi bruger planlagt afgang + f&oslash;rste oml&aelig;gnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
            </div>

            <!-- OFF-variant additions: always simple branch without 100-min dependency -->
            <fieldset id="offerProvidedWrapPast" class="mt8" data-art="18(3)" <?= $showArt183 ? 'hidden' : '' ?> >
                <legend>Fik du et konkret oml&aelig;gningstilbud fra operat&oslash;ren?</legend>
                <?php $offProv = (string)($form['offer_provided'] ?? ''); ?>
                <label><input type="radio" name="offer_provided" value="yes" <?= $offProv==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="offer_provided" value="no" <?= $offProv==='no'?'checked':'' ?> /> Nej</label>
            </fieldset>

            <?php $spt = (string)($form['self_purchased_new_ticket'] ?? ''); ?>
            <div id="step2Past" class="mt8 <?= $showArt183 ? '' : 'hidden' ?>" data-art="18(3)">
                <div class="mt4">K&oslash;bte du selv en ny billet for at komme videre?</div>
                <label><input type="radio" name="self_purchased_new_ticket" value="yes" <?= $spt==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="self_purchased_new_ticket" value="no" <?= $spt==='no'?'checked':'' ?> /> Nej</label>
                <div class="mt4" data-show-if="self_purchased_new_ticket:yes">
                    <label>Hvorfor k&oslash;bte du selv?
                        <?php $spr = (string)($form['self_purchase_reason'] ?? ''); ?>
                        <select name="self_purchase_reason">
                            <option value="">Vaelg</option>
                            <option value="no_offer" <?= $spr==='no_offer'?'selected':'' ?>>Ingen tilbudt oml&aelig;gning</option>
                            <option value="offer_not_usable" <?= $spr==='offer_not_usable'?'selected':'' ?>>Tilbudt men kunne ikke bruges</option>
                            <option value="needed_fast" <?= $spr==='needed_fast'?'selected':'' ?>>Skulle hurtigt videre</option>
                            <option value="other" <?= $spr==='other'?'selected':'' ?>>Andet</option>
                        </select>
                    </label>
                </div>
                <div id="selfBuyNotePast" class="small" style="margin-top:6px; padding:6px; background:#fff3cd; border-radius:6px; display:none;">Du k&oslash;bte selv ny billet, selvom oml&aelig;gning blev tilbudt inden for 100 min - udgiften refunderes normalt ikke (Art. 18(3)). Kompensation kan stadig v&aelig;re mulig.</div>
            </div>

            <div class="mt8" data-show-if="remedyChoice:reroute_later">
                <div>Hvad skete der s&aring;?</div>
                <?php $rlo = (string)($form['reroute_later_outcome'] ?? ''); ?>
                <label class="ml8"><input type="radio" name="reroute_later_outcome" value="operator_offered" <?= $rlo==='operator_offered'?'checked':'' ?> /> Operat&oslash;ren tilb&oslash;d senere oml&aelig;gning</label>
                <label class="ml8"><input type="radio" name="reroute_later_outcome" value="self_bought" <?= $rlo==='self_bought'?'checked':'' ?> /> Jeg k&oslash;bte selv en billet til senere</label>
                <label class="ml8"><input type="radio" name="reroute_later_outcome" value="no_solution" <?= $rlo==='no_solution'?'checked':'' ?> /> Jeg har ikke f&aring;et nogen l&oslash;sning endnu</label>
            </div>
            <div id="rerouteLaterTicketUpload" class="mt8" data-show-if="reroute_later_outcome:self_bought">
                <div class="small muted">Hvis du k&oslash;bte ny billet til senere: angiv bel&oslash;b og upload billetten.</div>
                <div class="grid-2 mt4">
                    <label>Bel&oslash;b
                        <input type="number" step="0.01" name="reroute_later_self_paid_amount" value="<?= h($form['reroute_later_self_paid_amount'] ?? '') ?>" />
                    </label>
                    <label>Valuta
                        <input type="text" name="reroute_later_self_paid_currency" value="<?= h($form['reroute_later_self_paid_currency'] ?? '') ?>" placeholder="<?= h($currency ?? 'EUR') ?>" />
                    </label>
                    <label class="small">Billet/kvittering
                        <input type="file" name="reroute_later_ticket_upload" accept=".pdf,.jpg,.jpeg,.png" />
                    </label>
                </div>
                <?php if ($f = $v('reroute_later_ticket_upload')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
            </div>

            <fieldset id="opApprovalWrapPast" class="mt8 <?= $showArt183 && $spt==='yes' ? '' : 'hidden' ?>" data-art="18(3)" <?= $spt==='yes' ? '' : 'hidden' ?> >
                <legend>Var selvk&oslash;bet godkendt af operat&oslash;ren?</legend>
                <?php $opOK = (string)($form['self_purchase_approved_by_operator'] ?? ''); ?>
                <label><input type="radio" name="self_purchase_approved_by_operator" value="yes" <?= $opOK==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="no" <?= $opOK==='no'?'checked':'' ?> /> Nej</label>
            </fieldset>

            <div id="notesAreaPast" class="notes small">
                <p id="noteApprovedPast" class="note success" style="display:none;">&#10003; Selvk&oslash;b er oplyst som godkendt af operat&oslash;ren.</p>
                <p id="noteNotRefundablePast" class="note warn" style="display:none;">&#9888;&#65039; Selvk&oslash;b uden operat&oslash;rens godkendelse refunderes normalt ikke.</p>
            </div>

            <div id="recBlockPast" class="mt8 <?= $showArt182 ? '' : 'hidden' ?>" data-art="18(2)">
                <?php $rec = (string)($form['reroute_extra_costs'] ?? ''); ?>
                <div class="mt4">Medf&oslash;rte oml&aelig;gningen ekstra udgifter for dig? (h&oslash;jere klasse/andet transportmiddel)</div>
                <label><input type="radio" name="reroute_extra_costs" value="yes" <?= $rec==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_extra_costs" value="no" <?= $rec==='no'?'checked':'' ?> /> Nej</label>
                <div class="grid-2 mt8 <?= $rec==='yes' ? '' : 'hidden' ?>" id="recWrapPast" data-art="18(2)">
                    <label>Hvad var merudgiften knyttet til?
                        <?php $rxt = (string)($form['reroute_extra_costs_type'] ?? ''); ?>
                        <select name="reroute_extra_costs_type">
                            <option value="">Vaelg</option>
                            <option value="new_ticket" <?= $rxt==='new_ticket'?'selected':'' ?>>Ny billet</option>
                            <option value="higher_class" <?= $rxt==='higher_class'?'selected':'' ?>>H&oslash;jere klasse</option>
                            <option value="alt_transport" <?= $rxt==='alt_transport'?'selected':'' ?>>Alternativ transport (bus/taxi)</option>
                            <option value="accommodation" <?= $rxt==='accommodation'?'selected':'' ?>>Indkvartering</option>
                            <option value="other" <?= $rxt==='other'?'selected':'' ?>>Andet</option>
                        </select>
                    </label>
                    <label>Bel&oslash;b
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

        </div>
    </div>

<?php if (!$showArt183): ?>
        <div class="small mt8" style="background:#fff3cd; padding:6px; border-radius:6px;">&#9888;&#65039; 100-minutters-reglen (Art. 18(3)) er undtaget for denne rejse. Vi skjuler sp&oslash;rgsm&aring;let og anvender alternative vurderinger.</div>
    <?php endif; ?>
</div>



</div>
<script>
// TRIN 5 (Art. 18): klientlogik for valg og sektioner
(function(){
    function updateReveal() {
        document.querySelectorAll('[data-show-if]').forEach(function(el) {
            var spec = el.getAttribute('data-show-if'); if (!spec) return;
            var parts = spec.split(':'); if (parts.length !== 2) return;
            var name = parts[0]; var valid = parts[1].split(',');
            var val = '';
            var checked = document.querySelector('input[name="' + name + '"]:checked');
            if (checked) {
                val = checked.value || '';
            } else {
                var sel = document.querySelector('select[name="' + name + '"]');
                if (sel) {
                    val = sel.value || '';
                } else {
                    var inp = document.querySelector('input[name="' + name + '"]');
                    if (inp && inp.type !== 'radio') { val = inp.value || ''; }
                }
            }
            var show = val !== '' && valid.includes(val);
            el.style.display = show ? 'block' : 'none';
            el.hidden = !show;
        });
    }
    function clearField(name) {
        document.querySelectorAll('[name="' + name + '"]').forEach(function(el) {
            if (el.type === 'radio' || el.type === 'checkbox') { el.checked = false; return; }
            if (el.tagName === 'SELECT') {
                el.value = '';
                if (el.value !== '') { el.selectedIndex = 0; }
                return;
            }
            el.value = '';
        });
    }
    function clearFields(names) {
        names.forEach(function(name){ clearField(name); });
    }
    function handleResets(target) {
        var name = target.name || '';
        if (name === 'stranded_location') {
            clearFields([
                'blocked_train_alt_transport','assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by',
                'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                'a20_3_solution_offered','a20_3_solution_type','a20_3_solution_offered_by','a20_3_no_solution_action',
                'a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt',
                'journey_outcome','remedyChoice','trip_cancelled_return_to_origin','reroute_same_conditions_soonest','reroute_later_at_choice'
            ]);
            return;
        }
        if (name === 'blocked_train_alt_transport') {
            var v = target.value || '';
            if (v === 'yes') {
                clearFields(['blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            } else if (v === 'no') {
                clearFields(['assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by']);
            } else {
                clearFields([
                    'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                    'assistance_alt_transport_type','assistance_alt_to_destination','assistance_alt_transport_offered_by'
                ]);
            }
            return;
        }
        if (name === 'blocked_no_transport_action') {
            if ((target.value || '') !== 'self_arranged') {
                clearFields(['blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            }
            return;
        }
        if (name === 'a20_3_solution_offered') {
            var v2 = target.value || '';
            if (v2 === 'yes') {
                clearFields(['a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt']);
            } else if (v2 === 'no') {
                clearFields(['a20_3_solution_type','a20_3_solution_offered_by']);
            } else {
                clearFields([
                    'a20_3_solution_type','a20_3_solution_offered_by',
                    'a20_3_no_solution_action','a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt'
                ]);
            }
            return;
        }
        if (name === 'a20_3_no_solution_action') {
            if ((target.value || '') !== 'self_arranged') {
                clearFields(['a20_3_self_arranged_type','a20_3_self_paid_amount','a20_3_self_paid_currency','a20_3_self_paid_receipt']);
            }
            return;
        }
        if (name === 'journey_outcome') {
            if (!(target.value || '')) {
                clearFields(['remedyChoice','trip_cancelled_return_to_origin','reroute_same_conditions_soonest','reroute_later_at_choice']);
            }
        }
        if (name === 'remedyChoice') {
            if ((target.value || '') !== 'reroute_soonest') {
                clearFields(['reroute_info_within_100min']);
            }
            if ((target.value || '') !== 'reroute_later') {
                clearFields(['reroute_later_outcome','reroute_later_self_paid_amount','reroute_later_self_paid_currency','reroute_later_ticket_upload']);
            }
        }
        if (name === 'self_purchased_new_ticket') {
            if ((target.value || '') !== 'yes') {
                clearFields(['self_purchase_reason']);
            }
        }
        if (name === 'reroute_later_outcome') {
            if ((target.value || '') !== 'self_bought') {
                clearFields(['reroute_later_self_paid_amount','reroute_later_self_paid_currency','reroute_later_ticket_upload']);
            }
        }
        if (name === 'reroute_extra_costs') {
            if ((target.value || '') !== 'yes') {
                clearFields(['reroute_extra_costs_type','reroute_extra_costs_amount','reroute_extra_costs_currency']);
            }
        }
        if (name === 'return_to_origin_expense') {
            if ((target.value || '') !== 'yes') {
                clearFields(['return_to_origin_amount','return_to_origin_currency','return_to_origin_transport_type','return_to_origin_receipt']);
            }
        }
        if (name === 'assistance_alt_to_destination') {
            var v4 = target.value || '';
            if (!(v4 === 'nearest_station' || v4 === 'other_departure_point')) {
                clearFields(['assistance_alt_arrival_station','assistance_alt_arrival_station_other']);
            }
        }
        if (name === 'assistance_alt_arrival_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['assistance_alt_arrival_station_other']);
            }
        }
    }
    function s7Update() {
        function setRadio(name, value) {
            var els = document.querySelectorAll('input[name="'+name+'"]');
            els.forEach(function(r){ r.checked = (r.value === value); });
        }
        var outcomeEl = document.getElementById('journey_outcome');
        var outcomeHidden = document.querySelector('input[name="journey_outcome"]');
        var outcome = outcomeEl ? (outcomeEl.value || '') : ((outcomeHidden && outcomeHidden.value) ? outcomeHidden.value : '');
        var autoOutcome = '';
        if (outcomeEl) {
            var loc = (document.querySelector('input[name="stranded_location"]:checked') || {}).value || '';
            var solOff = (document.querySelector('input[name="a20_3_solution_offered"]:checked') || {}).value || '';
            var noAction = (document.querySelector('input[name="a20_3_no_solution_action"]:checked') || {}).value || '';
            var blkAlt = (document.querySelector('input[name="blocked_train_alt_transport"]:checked') || {}).value || '';
            var blkAction = (document.querySelector('input[name="blocked_no_transport_action"]:checked') || {}).value || '';
            if (loc === 'station' && solOff === 'no' && noAction) {
                if (noAction === 'self_arranged') autoOutcome = 'self_arranged';
                else if (noAction === 'went_home') autoOutcome = 'returned_origin';
                else if (noAction === 'abandoned') autoOutcome = 'abandoned';
            }
            if (loc === 'track' && blkAlt === 'no' && blkAction) {
                if (blkAction === 'self_arranged') autoOutcome = 'self_arranged';
                else if (['waited','walked_station','evacuated_later'].includes(blkAction)) autoOutcome = 'continued_trip';
            }
            var outcomeWrap = document.getElementById('journeyOutcomeWrap');
            var outcomeHint = document.getElementById('journeyOutcomeHint');
            if (outcomeWrap) {
                var allowOutcome = (loc === 'track' || loc === 'station') && ((loc === 'track' && blkAlt) || (loc === 'station' && solOff));
                if (!allowOutcome) {
                    outcomeWrap.style.display = 'none';
                    outcomeWrap.hidden = true;
                    if (outcomeEl) outcomeEl.disabled = false;
                    if (outcomeHint) outcomeHint.classList.add('hidden');
                } else {
                    outcomeWrap.style.display = '';
                    outcomeWrap.hidden = false;
                    if (autoOutcome) {
                        if (outcomeEl && outcomeEl.value !== autoOutcome) {
                            outcomeEl.value = autoOutcome;
                            clearFields(['remedyChoice','trip_cancelled_return_to_origin','reroute_same_conditions_soonest','reroute_later_at_choice']);
                        }
                        if (outcomeEl) outcomeEl.disabled = true;
                        if (outcomeHint) outcomeHint.classList.remove('hidden');
                    } else {
                        if (outcomeEl) outcomeEl.disabled = false;
                        if (outcomeHint) outcomeHint.classList.add('hidden');
                    }
                }
            }
            if (autoOutcome) { outcome = autoOutcome; }
        }
        var outcomeChosen = outcome !== '';
        var remEl = document.querySelector('input[name="remedyChoice"]:checked');
        var val = remEl ? remEl.value : '';
        var art18Flow = document.getElementById('art18Flow');
        var art18Prompt = document.getElementById('art18OutcomePrompt');
        if (art18Flow) { art18Flow.style.display = ''; }
        if (art18Prompt) { art18Prompt.style.display = outcomeChosen ? 'none' : ''; }
        var hintEl = document.getElementById('remedyHint');
        var recommendMap = {
            continued_trip: 'reroute_soonest',
            returned_origin: 'refund_return',
            abandoned: 'refund_return',
            self_arranged: 'reroute_soonest'
        };
        var hintMap = {
            continued_trip: 'Ud fra dit svar ser det ud til, at omlaegning er relevant.',
            returned_origin: 'Ud fra dit svar ser det ud til, at refusion er relevant.',
            abandoned: 'Ud fra dit svar ser det ud til, at refusion er relevant.',
            self_arranged: 'Ud fra dit svar kan du muligvis kraeve dine rimelige udgifter refunderet. Husk kvitteringer.'
        };
        if (outcomeChosen) {
            if (!val && recommendMap[outcome]) {
                setRadio('remedyChoice', recommendMap[outcome]);
                val = recommendMap[outcome];
            }
            if (hintEl) { hintEl.textContent = hintMap[outcome] || ''; }
        } else if (hintEl) {
            hintEl.textContent = '';
        }
        // Feature flags for Art. 18 sections
        var artFlags = (window.__artFlags && window.__artFlags.art) || {};
        var a18On  = !(artFlags.hasOwnProperty('art18') && artFlags['art18'] === false);
        var a181On = !(artFlags.hasOwnProperty('art18_1') && artFlags['art18_1'] === false);
        var a182On = !(artFlags.hasOwnProperty('art18_2') && artFlags['art18_2'] === false);
        var a183On = !(artFlags.hasOwnProperty('art18_3') && artFlags['art18_3'] === false);
        ['Past','Now'].forEach(function(suf){
            var returnExp = document.getElementById('returnExpense' + suf);
            var reroute = document.getElementById('rerouteSection' + suf);
            if (returnExp) returnExp.classList.toggle('hidden', !a18On || !a181On || val !== 'refund_return');
            if (reroute) reroute.classList.toggle('hidden', !a18On || (!(a182On || a183On)) || !(val === 'reroute_soonest' || val === 'reroute_later'));
            var tcr = document.getElementById('tcr_sync_' + suf.toLowerCase());
            var rsc = document.getElementById('rsc_sync_' + suf.toLowerCase());
            var rlc = document.getElementById('rlc_sync_' + suf.toLowerCase());
            if (tcr) tcr.value = (val === 'refund_return') ? 'yes' : '';
            if (rsc) rsc.value = (val === 'reroute_soonest') ? 'yes' : '';
            if (rlc) rlc.value = (val === 'reroute_later') ? 'yes' : '';
            // 100-min kun for reroute_soonest
            var riWrap = document.getElementById('ri100' + suf + 'Wrap');
            if (riWrap) { riWrap.style.display = (a183On && val === 'reroute_soonest') ? '' : 'none'; }
        });
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
        if (!a182On) {
            if (recBlockPast) recBlockPast.style.display = 'none';
            if (recBlockNow) recBlockNow.style.display = 'none';
        }

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

        // Progressive visibility
        // Step 1 (only when 18(3) is ON) -> Step 2: show Step 2 only when answered OR 18(3) OFF
        var step1Answered = a183On ? ((val === 'reroute_later') ? true : !!ri100) : true;
        var step2Past = document.getElementById('step2Past');
        var step2Now = document.getElementById('step2Now');
        var advBox = document.getElementById('advToggle');
        var advOn = !!(advBox && advBox.checked);
        if (step2Past) step2Past.style.display = (step1Answered || (advPast && advPast.open) || advOn) ? '' : 'none';
        if (step2Now) step2Now.style.display = (step1Answered || (advNow && advNow.open) || advOn) ? '' : 'none';

        // Step 2 -> Branch: hide detail blocks until Step 2 is answered (only explicit yes/no)
        var step2Answered = (selfBuy === 'yes' || selfBuy === 'no');
        if ((advPast && advPast.open) || advOn) {
            if (recBlockPast && a182On) recBlockPast.style.display = '';
            if (dgcBlockPast) dgcBlockPast.style.display = '';
        } else {
            if (recBlockPast && a182On) recBlockPast.style.display = step2Answered ? '' : 'none';
            if (dgcBlockPast) dgcBlockPast.style.display = step2Answered ? '' : 'none';
        }
        if ((advNow && advNow.open) || advOn) {
            if (recBlockNow && a182On) recBlockNow.style.display = '';
            if (dgcBlockNow) dgcBlockNow.style.display = 'none';
        } else {
            if (recBlockNow && a182On) recBlockNow.style.display = step2Answered ? '' : 'none';
            if (dgcBlockNow) dgcBlockNow.style.display = 'none';
        }
        if (live) { live.textContent = step2Answered ? 'Trin 2 besvaret - gren valgt' : (step1Answered ? 'Trin 1 besvaret - vis trin 2' : 'Trin 1 endnu ikke besvaret'); }

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

        // Auto-override delivered class/reservation for bus/taxi reroute (operator-provided)
        try {
            var perLeg = document.getElementById('perLegDowngrade');
            if (perLeg) {
                var transport = '';
                var t1 = document.querySelector('select[name="assistance_alt_transport_type"]');
                var t2 = document.querySelector('select[name="a20_3_solution_type"]');
                if (t2 && t2.value) { transport = t2.value; }
                else if (t1 && t1.value) { transport = t1.value; }
                var isBusTaxi = (transport === 'bus' || transport === 'taxi');
                var isReroute = (val === 'reroute_soonest' || val === 'reroute_later');
                var dgcSel = document.querySelector('input[name="downgrade_occurred"]:checked');
                var dgcValNow = dgcSel ? dgcSel.value : '';
                if (isReroute && isBusTaxi && dgcValNow === 'yes') {
                    perLeg.dataset.autoOverride = '1';
                    perLeg.querySelectorAll('select[name^="leg_class_delivered"]').forEach(function(sel){
                        if (sel.value !== '2nd') { sel.value = '2nd'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
                    });
                    perLeg.querySelectorAll('select[name^="leg_reservation_delivered"]').forEach(function(sel){
                        if (sel.value !== 'missing') { sel.value = 'missing'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
                    });
                } else if (perLeg.dataset.autoOverride === '1' && (!isBusTaxi || !isReroute)) {
                    perLeg.dataset.autoOverride = '';
                    perLeg.querySelectorAll('select[name^="leg_class_delivered"]').forEach(function(sel){
                        if (sel.value === '2nd') { sel.value = ''; sel.dispatchEvent(new Event('change', {bubbles:true})); }
                    });
                    perLeg.querySelectorAll('select[name^="leg_reservation_delivered"]').forEach(function(sel){
                        if (sel.value === 'missing') { sel.value = ''; sel.dispatchEvent(new Event('change', {bubbles:true})); }
                    });
                }
            }
        } catch (e) { /* no-op */ }

        // Class/reservation dynamic toggles (moved fields)
        // (Class/reservation UI now only i TRIN 2 - no toggles needed here)

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
        updateReveal();
        s7Update();
        document.querySelectorAll('input[name="remedyChoice"], input[name="reroute_later_outcome"], input[name="return_to_origin_expense"], input[name="reroute_extra_costs"], input[name="downgrade_occurred"], input[name="reroute_info_within_100min"], input[name="self_purchased_new_ticket"], input[name="self_purchase_approved_by_operator"], input[name="offer_provided"]').forEach(function(el){
            ['change','click','input'].forEach(function(ev){ el.addEventListener(ev, s7Update); });
        });
    // (No class/reservation fields in TRIN 5 now)
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
        document.addEventListener('change', function(e){
            if (!e.target || !e.target.name) return;
            handleResets(e.target);
            updateReveal();
            s7Update();
        });
    });
})();
</script>

<!-- Progressive reveal: Advanced toggle (checkbox) + optional details -->
<div class="mt8">
    <label><input type="checkbox" id="advToggle" /> Vis alt (avanceret)</label>
    <span class="small muted ml8">For power users: viser alle felter; laaste vaerdier forbliver laast.</span>
</div>
<details id="advPast" class="mt8"><summary>Avanceret (afsluttet rejse)</summary><div class="small muted">Supplerende overblik, valgfrit.</div></details>
<details id="advNow" class="mt8"><summary>Avanceret (igangvaerende rejse)</summary><div class="small muted">Supplerende overblik, valgfrit.</div></details>
<div id="rerouteLive" aria-live="polite" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">Init</div>

</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('Tilbage', ['action' => 'choices'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsaet', ['id' => 'remediesSubmitBtn', 'class' => 'button', 'type' => 'submit', 'aria-label' => 'Fortsaet til naeste trin', 'formnovalidate' => true]) ?>
    <?= $this->Html->link('Spring over', ['controller' => 'Flow', 'action' => 'assistance'], ['class' => 'button', 'style' => 'background:#f5f5f5; color:#333;', 'title' => 'Gaa til naeste trin uden at gemme aendringer']) ?>
    <input type="hidden" name="_choices_submitted" value="1" />
</div>

<?= $this->Form->end() ?>
</div>
