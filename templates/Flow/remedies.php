<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$art18Active = $art18Active ?? true;
$art18Blocked = $art18Blocked ?? false;
$maps = $maps ?? [];
$mapsOptIn = !empty($form['maps_opt_in_trin6']);
$mapsTrin6 = (is_array($maps) && isset($maps['trin6']) && is_array($maps['trin6'])) ? $maps['trin6'] : null;
$ticketMode = (string)($form['ticket_upload_mode'] ?? '');
$hasTickets = !empty($form['_ticketFilename']) || !empty($meta['_multi_tickets']) || !empty($meta['_ticket_files']);
$isTicketless = ($ticketMode === 'ticketless' && !$hasTickets);
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');
$isBeforeStart = ($travelState === 'before_start');
$isFutureLike = ($isOngoing || $isBeforeStart);
$remediesTitle = $isOngoing
    ? 'TRIN 7 - Dine valg (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 7 - Dine valg (afsluttet rejse)' : ($isBeforeStart ? 'TRIN 7 - Dine valg (rejsen starter senere)' : 'TRIN 7 - Dine valg (Art. 18)'));
$art18Title = $isOngoing ? 'Rejsen er i gang - hvad er dit valg nu?' : ($isBeforeStart ? 'Rejsen starter senere - hvad vil du vaelge, hvis det sker?' : 'Rejsen er afsluttet - hvad skete der?');
$art18Help = $isFutureLike ? 'Ud fra din nuvaerende situation kan foelgende muligheder vaere relevante.' : 'Ved afgang, missed connection eller aflysning - ved forsinkelse paa 60+ min. tilbydes nedenstaaende muligheder.';
$decisionHint = $isFutureLike ? 'Foreloebige valg baseret paa nuvaerende situation.' : ($isCompleted ? 'Endelige valg baseret paa hvad der skete.' : '');
$downgradeHint = $isOngoing ? 'Udfyld kun hvis du allerede er blevet placeret i lavere klasse eller mistede reservation.' : 'Udfyld kun hvis du blev placeret i lavere klasse eller mistede reservation.';
$returnQuestion = $isOngoing ? 'Har du haft - eller forventer du at faa - udgifter til at komme tilbage til udgangspunktet?' : 'Havde du udgifter til at komme tilbage til udgangspunktet?';

$segments = [];
if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) { $segments = (array)$meta['_segments_auto']; }
elseif (!empty($meta['_segments_all']) && is_array($meta['_segments_all'])) { $segments = (array)$meta['_segments_all']; }
$stations = [];
$addStation = function ($val) use (&$stations) {
    $s = trim((string)$val);
    if ($s === '' || $s === 'other' || $s === 'unknown') { return; }
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
// Ticketless / strandings-flow can add station context without segments
$addStation($form['missed_connection_station'] ?? ($meta['_auto']['missed_connection_station']['value'] ?? ''));
$addStation($form['handoff_station'] ?? '');
$scs0 = (string)($form['stranded_current_station'] ?? '');
if ($scs0 === 'other') { $addStation($form['stranded_current_station_other'] ?? ''); }
else { $addStation($scs0); }
$arr0 = (string)($form['a20_arrival_station'] ?? '');
if ($arr0 === 'other') { $addStation($form['a20_arrival_station_other'] ?? ''); }
else { $addStation($arr0); }
$stationOptions = array_keys($stations);
sort($stationOptions, SORT_NATURAL | SORT_FLAG_CASE);
$stationSet = array_fill_keys($stationOptions, true);
// Use a literal path to avoid the URL builder selecting the /api/demo/v2 scope fallback route.
$stationsSearchUrl = $this->Url->build('/api/stations/search');
$stationCountryDefault = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
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
    .grid-1 { display:grid; grid-template-columns:1fr; gap:8px; }
    .disabled-block { opacity: 0.6; }
    .flow-wrapper { max-width: 1100px; margin: 0 auto; }
    .flow-wide { width: 100%; }
    .card-title { display:flex; align-items:center; gap:8px; font-weight:600; }
    .card-title .icon { font-size:16px; line-height:1; }
    .station-autocomplete { position: relative; }
    .station-suggest {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 2px);
        z-index: 50;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        max-height: 220px;
        overflow: auto;
    }
    .station-suggest button {
        width: 100%;
        text-align: left;
        border: 0;
        background: transparent;
        padding: 8px 10px;
        cursor: pointer;
        font-size: 14px;
        color: #111 !important;
    }
    .station-suggest button:hover { background: #f6f6f6; color: #111 !important; }
    .station-suggest button:active { color: #111 !important; }
    .station-suggest button:focus { outline: none; background: #f1f3f5; color: #111 !important; }
    .station-suggest .muted { color: #666; font-size: 12px; }

    /* Google Maps routes rendering (TRIN 6 helper) */
    .maps-routes-title { font-weight: 600; margin-top: 8px; }
    .maps-route { padding: 10px; border: 1px solid #ddd; border-radius: 8px; background: #fff; margin-top: 10px; }
    .maps-route-head { display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; }
    .maps-route-time { font-weight: 700; }
    .maps-route-meta { color:#666; font-size:12px; }
    .maps-chips { display:flex; gap:6px; flex-wrap:wrap; margin-top:6px; }
    .maps-chip { font-size:12px; padding:2px 8px; border-radius:999px; background:#f1f3f5; border:1px solid #e6e8eb; }
    .maps-segs { margin-top: 8px; padding-left: 16px; }
    .maps-segs li { margin-top: 4px; }
    .maps-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top: 10px; }
    .maps-actions a { text-decoration:none; }
</style>
<div class="flow-wrapper">
<h1><?= h($remediesTitle) ?></h1>
<?php
    if ($travelState === 'completed') {
        echo '<p class="small muted">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';
    } elseif ($travelState === 'ongoing') {
        echo '<p class="small muted">Status: Rejsen er i gang. Vi samler dine valg for resten af forloebet.</p>';
    } elseif ($travelState === 'before_start') {
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
    $isPreview = !empty($flowPreview);
?>
<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'remedies'], 'type' => 'file', 'novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
<!-- TRIN 5 (Art.20) hint data: used only for UX hints in this step -->
<input type="hidden" id="trin5_a20_3_self_paid_amount" value="<?= h($v('a20_3_self_paid_amount')) ?>" />
<input type="hidden" id="trin5_a20_3_self_paid_currency" value="<?= h($v('a20_3_self_paid_currency')) ?>" />
<input type="hidden" id="trin5_a20_3_self_paid_direction" value="<?= h($v('a20_3_self_paid_direction')) ?>" />
<?php
    // TRIN 5 is the stranded/assistance step. TRIN 6 is a separate Art.18 flow.
    // If TRIN 5 ended at final destination explicitly, TRIN 6 is only needed when the journey no longer had purpose.
    $toDest0 = $v('a20_where_ended');
    $assumed0 = $v('a20_where_ended_assumed') ?: '0';
    $arrivedFinalExplicit = ($toDest0 === 'final_destination' && $assumed0 !== '1');
    $purposeVal = $v('journey_no_longer_purpose');
    $hideArt18Flow = $arrivedFinalExplicit && $purposeVal !== 'yes';
?>

<?php if ($arrivedFinalExplicit): ?>
    <div class="card mt12" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
        <strong>Rejsen havde ikke laengere noget formaal?</strong>
        <div class="small muted mt4">Hvis du naaede dit endelige bestemmelsessted, er Trin 6 normalt ikke noedvendigt. Kun hvis rejsen ikke laengere havde noget formaal kan du have krav paa refusion/returtransport.</div>
        <div class="mt8">
            <label><input type="radio" name="journey_no_longer_purpose" value="yes" <?= $purposeVal==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="journey_no_longer_purpose" value="no" <?= $purposeVal==='no'?'checked':'' ?> /> Nej</label>
        </div>
        <?php if ($purposeVal === 'no'): ?>
            <div class="small muted mt8">OK - vi springer Art. 18 over og gaar videre til assistance (Trin 7).</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<div id="coreAfterArt18" class="<?= $hideArt18Flow ? 'hidden' : '' ?>">
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
    $refundOnly = $arrivedFinalExplicit && $purposeVal === 'yes';
    if ($refundOnly) { $remedy = 'refund_return'; }
    $ri100 = (string)($form['reroute_info_within_100min'] ?? '');
?>
    <div id="art18Wrapper" class="<?= $art18Active ? '' : 'hidden' ?>">
        <div id="art18Flow">
    <div class="card <?= ($showArt18 && $showArt181) ? '' : 'hidden' ?>" data-art="18(1)">
        <div class="card-title"><span class="icon">&#127919;</span><span><?= h($art18Title) ?></span></div>
        <div class="small muted" style="margin-top:6px;"><?= h($art18Help) ?></div>
        <div id="remedyHint" class="small muted mt8"></div>
        <div class="mt8" data-art="18(1)"><strong>V&aelig;lg pr&aelig;cis en mulighed</strong></div>
        <label data-art="18(1a)"><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Jeg &oslash;nsker refusion</label><br/>
        <?php if (!$refundOnly): ?>
            <label data-art="18(1b)"><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Jeg &oslash;nsker oml&aelig;gning hurtigst muligt</label><br/>
            <label data-art="18(1c)"><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Jeg &oslash;nsker oml&aelig;gning senere (efter eget valg)</label>
        <?php endif; ?>

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

            <?php
                // TRIN 6 refund context: where are you now, and where are you returning to?
                // Prefer explicit TRIN 5 handoff_station; if missing, fall back to stranded_current_station (so refund can be chosen before handoff is known).
                $handoff = trim((string)($form['handoff_station'] ?? ''));
                if ($handoff === '') {
                    $scs = trim((string)($form['stranded_current_station'] ?? ''));
                    if ($scs === 'other') { $scs = trim((string)($form['stranded_current_station_other'] ?? '')); }
                    if ($scs !== '' && $scs !== 'unknown') { $handoff = $scs; }
                }
                $fromVal = (string)($form['a18_from_station'] ?? '');
                $fromOther = (string)($form['a18_from_station_other'] ?? '');
                $fromPref = $fromVal !== '' ? $fromVal : ($handoff !== '' ? $handoff : '');
                $fromSel = $fromVal;
                if ($fromSel === '') {
                    if ($fromPref !== '' && isset($stationSet[$fromPref])) { $fromSel = $fromPref; }
                    elseif ($fromPref === 'unknown') { $fromSel = 'unknown'; }
                    elseif ($fromPref !== '') { $fromSel = 'other'; if ($fromOther === '') { $fromOther = $fromPref; } }
                }
                if ($isTicketless && $fromSel !== 'unknown') {
                    $fromSel = 'other';
                    if ($fromOther === '' && $fromPref !== '' && $fromPref !== 'unknown') { $fromOther = $fromPref; }
                }

                $depDefault0 = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
                $retVal = (string)($form['a18_return_to_station'] ?? '');
                $retOther = (string)($form['a18_return_to_station_other'] ?? '');
                $retPref = $retVal !== '' ? $retVal : ($depDefault0 !== '' ? $depDefault0 : '');
                $retSel = $retVal;
                if ($retSel === '') {
                    if ($retPref !== '' && isset($stationSet[$retPref])) { $retSel = $retPref; }
                    elseif ($retPref === 'unknown') { $retSel = 'unknown'; }
                    elseif ($retPref !== '') { $retSel = 'other'; if ($retOther === '') { $retOther = $retPref; } }
                }
                if ($isTicketless && $retSel !== 'unknown') {
                    $retSel = 'other';
                    if ($retOther === '' && $retPref !== '' && $retPref !== 'unknown') { $retOther = $retPref; }
                }
            ?>
            <div class="grid-2 mt8" id="refundStationsPast">
                <?php if ($isTicketless): ?>
                    <label class="station-autocomplete" data-station-select="a18_from_station" data-station-other="a18_from_station_other">Hvilken station er du p&aring;?
                        <select name="a18_from_station" style="display:none;">
                            <option value="other" <?= $fromSel==='other'?'selected':'' ?>>Anden station</option>
                            <option value="unknown" <?= $fromSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                        </select>
                        <input type="text" name="a18_from_station_other" value="<?= h($fromOther) ?>" placeholder="S&oslash;g station (ticketless)" />
                        <input type="hidden" name="a18_from_station_other_osm_id" value="<?= h((string)($form['a18_from_station_other_osm_id'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_lat" value="<?= h((string)($form['a18_from_station_other_lat'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_lon" value="<?= h((string)($form['a18_from_station_other_lon'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_country" value="<?= h((string)($form['a18_from_station_other_country'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_type" value="<?= h((string)($form['a18_from_station_other_type'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_source" value="<?= h((string)($form['a18_from_station_other_source'] ?? '')) ?>" />
                        <div class="station-suggest" data-for="a18_from_station_other" style="display:none;"></div>
                        <?php if ($handoff !== '' && $fromVal === ''): ?>
                            <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($handoff) ?></strong></div>
                        <?php endif; ?>
                    </label>
                    <label class="station-autocomplete" data-station-select="a18_return_to_station" data-station-other="a18_return_to_station_other">Hvilken station skal du tilbage til?
                        <select name="a18_return_to_station" style="display:none;">
                            <option value="other" <?= $retSel==='other'?'selected':'' ?>>Anden station</option>
                            <option value="unknown" <?= $retSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                        </select>
                        <input type="text" name="a18_return_to_station_other" value="<?= h($retOther) ?>" placeholder="S&oslash;g station (ticketless)" />
                        <input type="hidden" name="a18_return_to_station_other_osm_id" value="<?= h((string)($form['a18_return_to_station_other_osm_id'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_lat" value="<?= h((string)($form['a18_return_to_station_other_lat'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_lon" value="<?= h((string)($form['a18_return_to_station_other_lon'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_country" value="<?= h((string)($form['a18_return_to_station_other_country'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_type" value="<?= h((string)($form['a18_return_to_station_other_type'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_source" value="<?= h((string)($form['a18_return_to_station_other_source'] ?? '')) ?>" />
                        <div class="station-suggest" data-for="a18_return_to_station_other" style="display:none;"></div>
                        <?php if ($depDefault0 !== '' && $retVal === ''): ?>
                            <div class="small muted mt4">Default fra billetten: <strong><?= h($depDefault0) ?></strong></div>
                        <?php endif; ?>
                    </label>
                <?php else: ?>
                    <label class="station-autocomplete" data-station-select="a18_from_station" data-station-other="a18_from_station_other">Hvilken station er du p&aring;?
                        <select name="a18_from_station">
                            <option value="">V&aelig;lg</option>
                            <?php foreach ($stationOptions as $st): ?>
                                <option value="<?= h($st) ?>" <?= $fromSel===$st?'selected':'' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                            <option value="unknown" <?= $fromSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                            <option value="other" <?= $fromSel==='other'?'selected':'' ?>>Anden station</option>
                        </select>
                        <input type="text" name="a18_from_station_other" value="<?= h($fromOther) ?>" placeholder="Anden station" data-show-if="a18_from_station:other" />
                        <input type="hidden" name="a18_from_station_other_osm_id" value="<?= h((string)($form['a18_from_station_other_osm_id'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_lat" value="<?= h((string)($form['a18_from_station_other_lat'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_lon" value="<?= h((string)($form['a18_from_station_other_lon'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_country" value="<?= h((string)($form['a18_from_station_other_country'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_type" value="<?= h((string)($form['a18_from_station_other_type'] ?? '')) ?>" />
                        <input type="hidden" name="a18_from_station_other_source" value="<?= h((string)($form['a18_from_station_other_source'] ?? '')) ?>" />
                        <div class="station-suggest" data-for="a18_from_station_other" style="display:none;"></div>
                        <?php if ($handoff !== '' && $fromVal === ''): ?>
                            <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($handoff) ?></strong></div>
                        <?php endif; ?>
                    </label>
                    <label class="station-autocomplete" data-station-select="a18_return_to_station" data-station-other="a18_return_to_station_other">Hvilken station skal du tilbage til?
                        <select name="a18_return_to_station">
                            <option value="">V&aelig;lg</option>
                            <?php foreach ($stationOptions as $st): ?>
                                <option value="<?= h($st) ?>" <?= $retSel===$st?'selected':'' ?>><?= h($st) ?></option>
                            <?php endforeach; ?>
                            <option value="unknown" <?= $retSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                            <option value="other" <?= $retSel==='other'?'selected':'' ?>>Anden station</option>
                        </select>
                        <input type="text" name="a18_return_to_station_other" value="<?= h($retOther) ?>" placeholder="Anden station" data-show-if="a18_return_to_station:other" />
                        <input type="hidden" name="a18_return_to_station_other_osm_id" value="<?= h((string)($form['a18_return_to_station_other_osm_id'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_lat" value="<?= h((string)($form['a18_return_to_station_other_lat'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_lon" value="<?= h((string)($form['a18_return_to_station_other_lon'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_country" value="<?= h((string)($form['a18_return_to_station_other_country'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_type" value="<?= h((string)($form['a18_return_to_station_other_type'] ?? '')) ?>" />
                        <input type="hidden" name="a18_return_to_station_other_source" value="<?= h((string)($form['a18_return_to_station_other_source'] ?? '')) ?>" />
                        <div class="station-suggest" data-for="a18_return_to_station_other" style="display:none;"></div>
                        <?php if ($depDefault0 !== '' && $retVal === ''): ?>
                            <div class="small muted mt4">Default fra billetten: <strong><?= h($depDefault0) ?></strong></div>
                        <?php endif; ?>
                    </label>
                <?php endif; ?>
            </div>

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
                $depDefault = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
                $destDefault = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
                $missedDefault = trim((string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? '')));

                // Default origin for reroute: prefer missed-connection station, then TRIN 5 stranded station,
                // then "ended at station" from TRIN 5, then departure station from ticket.
                $originDefault = $missedDefault !== '' ? $missedDefault : '';
                if ($originDefault === '') {
                    $scs = trim((string)($form['stranded_current_station'] ?? ''));
                    if ($scs === 'other') { $scs = trim((string)($form['stranded_current_station_other'] ?? '')); }
                    if ($scs !== '' && $scs !== 'unknown') { $originDefault = $scs; }
                }
                if ($originDefault === '') {
                    // Prefer explicit handoff station from TRIN 5 (Art.20), otherwise fall back to legacy keys.
                    $arrSt = trim((string)($form['handoff_station'] ?? ''));
                    if ($arrSt === '') {
                        $arrSt = trim((string)($form['a20_arrival_station'] ?? ''));
                        if ($arrSt === 'other') { $arrSt = trim((string)($form['a20_arrival_station_other'] ?? '')); }
                    }
                    if ($arrSt !== '' && $arrSt !== 'unknown') { $originDefault = $arrSt; }
                }
                if ($originDefault === '') { $originDefault = $depDefault; }
                $mapsConfigured = ((string)(getenv('GOOGLE_MAPS_SERVER_KEY') ?: (getenv('GOOGLE_MAPS_API_KEY') ?: ''))) !== '';
            ?>
            <div class="card mt12" style="background:#f8f9fb;" data-show-if="remedyChoice:reroute_soonest,reroute_later">
                <div class="card-title"><span class="icon">MAP</span><span>Ruter (Google Maps, valgfrit)</span></div>
                <div class="small muted mt4">Klik for at hente forslag til oml&aelig;gning (TRANSIT). Vi sender start/destination til Google.</div>
                <input type="hidden" name="maps_opt_in_trin6" value="0" />
                <label class="mt8"><input type="checkbox" name="maps_opt_in_trin6" value="1" <?= $mapsOptIn ? 'checked' : '' ?> /> Brug Google Maps i denne sag</label>

                <div id="mapsPanelTrin6" class="mt8 <?= $mapsOptIn ? '' : 'hidden' ?>" data-endpoint="<?= h($this->Url->build(['controller' => 'Flow', 'action' => 'mapsRoutes'])) ?>" data-origin="<?= h($originDefault) ?>" data-destination="<?= h($destDefault) ?>">
                    <div class="grid-2">
                        <label class="station-autocomplete">Fra (station)
                            <input type="text" id="mapsOriginTrin6" value="<?= h($originDefault) ?>" />
                            <div class="station-suggest" id="mapsOriginSuggest" style="display:none;"></div>
                            <div class="small muted mt4">Tip: Brug missed connection / din nuv&aelig;rende station som start.</div>
                        </label>
                        <label class="station-autocomplete">Til (destination)
                            <input
                                type="text"
                                id="mapsDestTrin6"
                                value="<?= h($destDefault) ?>"
                                <?= ($isTicketless || $destDefault === '') ? '' : 'readonly' ?>
                                placeholder="<?= h(($isTicketless || $destDefault === '') ? 'Soeg destination' : '') ?>"
                            />
                            <div class="station-suggest" id="mapsDestSuggest" style="display:none;"></div>
                            <?php if ($isTicketless || $destDefault === ''): ?>
                                <div class="small muted mt4">Angiv destination (ticketless/ukendt destination).</div>
                            <?php else: ?>
                                <div class="small muted mt4">Hentes fra billetten (destination).</div>
                            <?php endif; ?>
                        </label>
                    </div>
                    <div class="mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="button" id="mapsFetchTrin6" <?= $mapsConfigured ? '' : 'disabled' ?>>Hent forslag</button>
                        <a class="button" id="mapsOpenTrin6" target="_blank" rel="noopener">Aabn i Google Maps</a>
                        <span class="small muted" id="mapsStatusTrin6"></span>
                    </div>
                    <div id="mapsRoutesTrin6" class="mt8"></div>
                    <?php if (!$mapsConfigured): ?>
                        <div class="small muted mt8">Bemaerk: Google Routes er ikke konfigureret (mangler server API key).</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="trin6StationContext" class="mt8 card" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="remedyChoice:reroute_soonest,reroute_later">
                <div class="small"><strong>Stationsvalg (oml&aelig;gning)</strong></div>
                <div class="small muted mt4">TRIN 7 er uafhaengigt af TRIN 6. Brug evt. stationen fra Art.20 som udgangspunkt.</div>
                <?php
                    $handoff = trim((string)($form['handoff_station'] ?? ''));
                    if ($handoff === '') {
                        $scs = trim((string)($form['stranded_current_station'] ?? ''));
                        if ($scs === 'other') { $scs = trim((string)($form['stranded_current_station_other'] ?? '')); }
                        if ($scs !== '' && $scs !== 'unknown') { $handoff = $scs; }
                    }
                    $fromVal = (string)($form['a18_from_station'] ?? '');
                    $fromOther = (string)($form['a18_from_station_other'] ?? '');
                    $fromPref = $fromVal !== '' ? $fromVal : ($handoff !== '' ? $handoff : '');
                    $fromSel = $fromVal;
                    if ($fromSel === '') {
                        if ($fromPref !== '' && isset($stationSet[$fromPref])) { $fromSel = $fromPref; }
                        elseif ($fromPref === 'unknown') { $fromSel = 'unknown'; }
                        elseif ($fromPref !== '') { $fromSel = 'other'; if ($fromOther === '') { $fromOther = $fromPref; } }
                    }
                    if ($isTicketless && $fromSel !== 'unknown') {
                        $fromSel = 'other';
                        if ($fromOther === '' && $fromPref !== '' && $fromPref !== 'unknown') { $fromOther = $fromPref; }
                    }
                    $modeVal = (string)($form['a18_reroute_mode'] ?? '');
                    $endVal = (string)($form['a18_reroute_endpoint'] ?? '');
                    $endSt = (string)($form['a18_reroute_arrival_station'] ?? '');
                    $endStOther = (string)($form['a18_reroute_arrival_station_other'] ?? '');
                    // Suggest arrival station from TRIN 5 (handoff/arrival) when user hasn't provided anything yet.
                    $endPref = '';
                    if ($endSt === '' && $endStOther === '') {
                        $a20Arr = trim((string)($form['a20_arrival_station'] ?? ''));
                        if ($a20Arr === 'other') { $a20Arr = trim((string)($form['a20_arrival_station_other'] ?? '')); }
                        if ($a20Arr !== '' && $a20Arr !== 'unknown') { $endPref = $a20Arr; }
                        if ($endPref === '' && $handoff !== '' && $handoff !== 'unknown') { $endPref = $handoff; }
                    }
                    $endSuggested = ($endSt === '' && $endStOther === '' && $endPref !== '');
                    $endSel = $endSt;
                    if ($endSel === '' && $endPref !== '') {
                        if (isset($stationSet[$endPref])) { $endSel = $endPref; }
                        else { $endSel = 'other'; if ($endStOther === '') { $endStOther = $endPref; } }
                    }
                    if ($isTicketless && $endSel !== 'unknown') {
                        if ($endSel !== 'other' && $endStOther === '') { $endStOther = $endSel; }
                        $endSel = 'other';
                    }
                ?>
                <div class="grid-1 mt8">
                    <?php if ($isTicketless): ?>
                        <label class="station-autocomplete" data-station-select="a18_from_station" data-station-other="a18_from_station_other">Hvilken station er du p&aring;?
                            <select name="a18_from_station" style="display:none;">
                                <option value="other" <?= $fromSel==='other'?'selected':'' ?>>Anden station</option>
                                <option value="unknown" <?= $fromSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                            </select>
                            <input type="text" name="a18_from_station_other" value="<?= h($fromOther) ?>" placeholder="S&oslash;g station (ticketless)" />
                            <input type="hidden" name="a18_from_station_other_osm_id" value="<?= h((string)($form['a18_from_station_other_osm_id'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_lat" value="<?= h((string)($form['a18_from_station_other_lat'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_lon" value="<?= h((string)($form['a18_from_station_other_lon'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_country" value="<?= h((string)($form['a18_from_station_other_country'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_type" value="<?= h((string)($form['a18_from_station_other_type'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_source" value="<?= h((string)($form['a18_from_station_other_source'] ?? '')) ?>" />
                            <div class="station-suggest" data-for="a18_from_station_other" style="display:none;"></div>
                            <?php if ($handoff !== '' && $fromVal === ''): ?>
                                <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($handoff) ?></strong></div>
                            <?php endif; ?>
                        </label>
                    <?php else: ?>
                        <label class="station-autocomplete" data-station-select="a18_from_station" data-station-other="a18_from_station_other">Hvilken station er du p&aring;?
                            <select name="a18_from_station">
                                <option value="">V&aelig;lg</option>
                                <?php foreach ($stationOptions as $st): ?>
                                    <option value="<?= h($st) ?>" <?= $fromSel===$st?'selected':'' ?>><?= h($st) ?></option>
                                <?php endforeach; ?>
                                <option value="unknown" <?= $fromSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                                <option value="other" <?= $fromSel==='other'?'selected':'' ?>>Anden station</option>
                            </select>
                            <input type="text" name="a18_from_station_other" value="<?= h($fromOther) ?>" placeholder="Anden station" data-show-if="a18_from_station:other" />
                            <input type="hidden" name="a18_from_station_other_osm_id" value="<?= h((string)($form['a18_from_station_other_osm_id'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_lat" value="<?= h((string)($form['a18_from_station_other_lat'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_lon" value="<?= h((string)($form['a18_from_station_other_lon'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_country" value="<?= h((string)($form['a18_from_station_other_country'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_type" value="<?= h((string)($form['a18_from_station_other_type'] ?? '')) ?>" />
                            <input type="hidden" name="a18_from_station_other_source" value="<?= h((string)($form['a18_from_station_other_source'] ?? '')) ?>" />
                            <div class="station-suggest" data-for="a18_from_station_other" style="display:none;"></div>
                            <?php if ($handoff !== '' && $fromVal === ''): ?>
                                <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($handoff) ?></strong></div>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                </div>

                <div class="grid-2 mt8">
                    <label>Transportform (oml&aelig;gning)
                        <select name="a18_reroute_mode">
                            <option value="">V&aelig;lg</option>
                            <option value="rail" <?= $modeVal==='rail'?'selected':'' ?>>Tog</option>
                            <option value="bus" <?= $modeVal==='bus'?'selected':'' ?>>Bus</option>
                            <option value="taxi" <?= $modeVal==='taxi'?'selected':'' ?>>Taxi/minibus</option>
                            <option value="other" <?= $modeVal==='other'?'selected':'' ?>>Andet</option>
                        </select>
                    </label>
                    <label>Hvor endte oml&aelig;gningen?
                        <select name="a18_reroute_endpoint">
                            <option value="">V&aelig;lg</option>
                            <option value="nearest_station" <?= $endVal==='nearest_station'?'selected':'' ?>>N&aelig;rmeste station</option>
                            <option value="other_departure_point" <?= $endVal==='other_departure_point'?'selected':'' ?>>Et andet egnet afgangssted</option>
                            <option value="final_destination" <?= $endVal==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
                        </select>
                    </label>
                </div>

                <div class="grid-1 mt8">
                    <?php if ($isTicketless): ?>
                        <label class="station-autocomplete" data-station-select="a18_reroute_arrival_station" data-station-other="a18_reroute_arrival_station_other" data-show-if="a18_reroute_endpoint:nearest_station,other_departure_point">Hvilken station omlagde du til?
                            <select name="a18_reroute_arrival_station" style="display:none;">
                                <option value="other" <?= $endSel==='other'?'selected':'' ?>>Anden station</option>
                                <option value="unknown" <?= $endSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                            </select>
                            <input type="text" name="a18_reroute_arrival_station_other" value="<?= h($endStOther) ?>" placeholder="S&oslash;g station (ticketless)" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_osm_id" value="<?= h((string)($form['a18_reroute_arrival_station_other_osm_id'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_lat" value="<?= h((string)($form['a18_reroute_arrival_station_other_lat'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_lon" value="<?= h((string)($form['a18_reroute_arrival_station_other_lon'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_country" value="<?= h((string)($form['a18_reroute_arrival_station_other_country'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_type" value="<?= h((string)($form['a18_reroute_arrival_station_other_type'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_source" value="<?= h((string)($form['a18_reroute_arrival_station_other_source'] ?? '')) ?>" />
                            <div class="station-suggest" data-for="a18_reroute_arrival_station_other" style="display:none;"></div>
                            <?php if ($endSuggested): ?>
                                <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($endPref) ?></strong></div>
                            <?php endif; ?>
                        </label>
                    <?php else: ?>
                        <label class="station-autocomplete" data-station-select="a18_reroute_arrival_station" data-station-other="a18_reroute_arrival_station_other" data-show-if="a18_reroute_endpoint:nearest_station,other_departure_point">Hvilken station omlagde du til?
                            <select name="a18_reroute_arrival_station">
                                <option value="">V&aelig;lg</option>
                                <?php foreach ($stationOptions as $st): ?>
                                    <option value="<?= h($st) ?>" <?= $endSel===$st?'selected':'' ?>><?= h($st) ?></option>
                                <?php endforeach; ?>
                                <option value="unknown" <?= $endSel==='unknown'?'selected':'' ?>>Ved ikke</option>
                                <option value="other" <?= $endSel==='other'?'selected':'' ?>>Anden station</option>
                            </select>
                            <input type="text" name="a18_reroute_arrival_station_other" value="<?= h($endStOther) ?>" placeholder="Anden station" data-show-if="a18_reroute_arrival_station:other" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_osm_id" value="<?= h((string)($form['a18_reroute_arrival_station_other_osm_id'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_lat" value="<?= h((string)($form['a18_reroute_arrival_station_other_lat'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_lon" value="<?= h((string)($form['a18_reroute_arrival_station_other_lon'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_country" value="<?= h((string)($form['a18_reroute_arrival_station_other_country'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_type" value="<?= h((string)($form['a18_reroute_arrival_station_other_type'] ?? '')) ?>" />
                            <input type="hidden" name="a18_reroute_arrival_station_other_source" value="<?= h((string)($form['a18_reroute_arrival_station_other_source'] ?? '')) ?>" />
                            <div class="station-suggest" data-for="a18_reroute_arrival_station_other" style="display:none;"></div>
                            <?php if ($endSuggested): ?>
                                <div class="small muted mt4">Forslag fra Art.20: <strong><?= h($endPref) ?></strong></div>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                </div>
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

            <div id="rerouteLaterOutcomePast" class="mt8" data-show-if="remedyChoice:reroute_later">
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
                <label class="ml8"><input type="radio" name="self_purchase_approved_by_operator" value="unknown" <?= $opOK==='unknown'?'checked':'' ?> /> Ved ikke</label>
            </fieldset>

            <div id="ri100PastWrap" class="mt8 <?= $showArt183 ? '' : 'hidden' ?>" data-art="18(3)" style="display:none;">
                <div>Fik du besked om mulighederne for oml&aelig;gning inden for 100 minutter? (Art. 18(3))
                    <span class="small muted">Vi bruger planlagt afgang + f&oslash;rste oml&aelig;gnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="unknown" <?= $ri100==='unknown'?'checked':'' ?> /> Ved ikke</label>
                <div id="note100FallbackPast" class="small mt8" style="display:none; background:#e7f5ff; padding:8px; border-radius:6px;">
                    <strong>100-minuttersfallback:</strong>
                    <div class="muted">Hvis du ikke fik konkrete oml&aelig;gningsmuligheder inden for 100 minutter, kan du have ret til at k&oslash;be alternativ offentlig transport (tog/bus/turistbus) og f&aring; rimelige omkostninger refunderet.</div>
                </div>
                <div id="note100ManualPast" class="small mt8" style="display:none; background:#fff3cd; padding:8px; border-radius:6px;">
                    <strong>Bem&aelig;rk:</strong>
                    <div class="muted">Hvis oml&aelig;gningsmuligheder blev meddelt inden for 100 minutter, er selvk&oslash;b normalt ikke automatisk refunderbart efter Art. 18(3). Vi kan stadig vurdere sagen manuelt.</div>
                </div>
            </div>

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
                            <?php foreach (['EUR','DKK','SEK','NOK','GBP','CHF','BGN','CZK','HUF','PLN','RON'] as $cc): ?>
                              <option value="<?= $cc ?>" <?= $curCur===$cc?'selected':'' ?>><?= $cc ?></option>
                            <?php endforeach; ?>
                            <?php if ($curCur !== '' && !in_array($curCur, ['EUR','DKK','SEK','NOK','GBP','CHF','BGN','CZK','HUF','PLN','RON'], true)): ?>
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
     const stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
     const stationCountryDefault = <?= json_encode((string)$stationCountryDefault, JSON_UNESCAPED_SLASHES) ?>;
     // Embed as safe JS literal (avoid U+2028/U+2029 breaking scripts; also protect against script-tag injection)
     const savedMapsTrin6 = <?= json_encode(
         (is_array($mapsTrin6) ? $mapsTrin6 : null),
         JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
     ) ?>;
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
        // TRIN 6 is independent of TRIN 5 (Art.20); avoid clearing Art.20 keys here.
        if (name === 'remedyChoice') {
            // 100-min question is relevant for both reroute types (b and c); clear only when leaving reroute.
            var vRem = (target.value || '');
            if (!(vRem === 'reroute_soonest' || vRem === 'reroute_later')) { clearFields(['reroute_info_within_100min']); }
            if ((target.value || '') !== 'reroute_later') {
                clearFields(['reroute_later_outcome','reroute_later_self_paid_amount','reroute_later_self_paid_currency','reroute_later_ticket_upload']);
            }
            if (vRem !== 'refund_return') {
                clearFields(['a18_return_to_station','a18_return_to_station_other']);
            }
        }
        if (name === 'self_purchased_new_ticket') {
            if ((target.value || '') !== 'yes') {
                clearFields(['self_purchase_reason','self_purchase_approved_by_operator','reroute_info_within_100min']);
            }
        }
        if (name === 'self_purchase_approved_by_operator') {
            if ((target.value || '') === 'yes') {
                clearFields(['reroute_info_within_100min']);
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
        // TRIN 6 station context (separate from TRIN 5 "Hvor endte du?")
        if (name === 'a18_from_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['a18_from_station_other']);
            }
        }
        if (name === 'a18_reroute_endpoint') {
            var v4 = target.value || '';
            if (!(v4 === 'nearest_station' || v4 === 'other_departure_point')) {
                clearFields(['a18_reroute_arrival_station','a18_reroute_arrival_station_other']);
            }
        }
        if (name === 'a18_reroute_arrival_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['a18_reroute_arrival_station_other']);
            }
        }
        if (name === 'a18_return_to_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['a18_return_to_station_other']);
            }
        }
    }
    function s7Update() {
        function setRadio(name, value) {
            var els = document.querySelectorAll('input[name="'+name+'"]');
            els.forEach(function(r){ r.checked = (r.value === value); });
        }
        var remEl = document.querySelector('input[name="remedyChoice"]:checked');
        var val = remEl ? remEl.value : '';

        // If TRIN 5 explicitly ended at final destination, TRIN 6 only applies when
        // "Rejsen havde ikke laengere noget formaal?" is YES. Otherwise this step is skipped server-side.
        var purposeEl = document.querySelector('input[name="journey_no_longer_purpose"]:checked');
        var purpose = purposeEl ? (purposeEl.value || '') : '';
        var isArrivedFinal = !!document.querySelector('input[name="journey_no_longer_purpose"]');
        var core = document.getElementById('coreAfterArt18');
        if (purpose) {
            if (core) core.classList.toggle('hidden', purpose !== 'yes');
            if (purpose !== 'yes') {
                // Avoid stale answers if user flips to "no" and then back.
                clearFields(['remedyChoice','trip_cancelled_return_to_origin','reroute_same_conditions_soonest','reroute_later_at_choice']);
            }
        }
        if (isArrivedFinal && purpose === 'yes') {
            // When the passenger reached the final destination, only refund/return is relevant if
            // the journey no longer had purpose (Art.18(1)(a)).
            setRadio('remedyChoice', 'refund_return');
            val = 'refund_return';
            document.querySelectorAll('input[name="remedyChoice"][value="reroute_soonest"], input[name="remedyChoice"][value="reroute_later"]').forEach(function(r){
                r.disabled = true;
                var lbl = r.closest('label');
                if (lbl) lbl.classList.add('hidden');
            });
        }

        // Keep hint empty in TRIN 6; we no longer infer remedy from a separate "journey outcome" question.
        var hintEl = document.getElementById('remedyHint');
        if (hintEl) {
            hintEl.textContent = '';
            // UX hint: if the user self-paid for transport in TRIN 5 (station/no), that expense can be "return" vs "continue".
            // We do not auto-change remedyChoice here; we only show a hint to avoid mis-bucketing.
            var amt = (document.getElementById('trin5_a20_3_self_paid_amount') || {}).value || '';
            var dir = (document.getElementById('trin5_a20_3_self_paid_direction') || {}).value || '';
            if (amt && dir) {
                if (dir === 'return' && (val === 'reroute_soonest' || val === 'reroute_later')) {
                    hintEl.textContent = 'Note: Din selvbetalte transport i TRIN 5 ligner returtransport. Overvej om refusion/returtransport passer bedre.';
                } else if (dir === 'continue' && val === 'refund_return') {
                    hintEl.textContent = 'Note: Din selvbetalte transport i TRIN 5 ligner videre rejse mod destination. Overvej om omlaegning passer bedre.';
                } else if (dir === 'hotel') {
                    hintEl.textContent = 'Note: Din selvbetalte transport i TRIN 5 ligner hotel/overnatning. Det vurderes typisk under assistance (Trin 7).';
                }
            }
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
            var riWrap = document.getElementById('ri100' + suf + 'Wrap');
            // Progressive gating controls 100-min visibility later; default to hidden here to avoid stale overlap.
            if (riWrap) { riWrap.style.display = 'none'; }
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
    var riWrapPast = document.getElementById('ri100PastWrap');
    var note100FallbackPast = document.getElementById('note100FallbackPast');
    var note100ManualPast = document.getElementById('note100ManualPast');
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
 
        function showBlock(el){
            if (!el) return;
            // Some blocks are server-rendered with class="hidden" and/or the HTML `hidden` attribute.
            // When we reveal progressively on the client, we must remove both, otherwise the block stays invisible.
            el.classList.remove('hidden');
            el.hidden = false;
            el.style.display = '';
        }
        function hideBlock(el){
            if (!el) return;
            el.style.display = 'none';
            el.hidden = true;
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

        // Progressive ask-order: show one question at a time (do not clear previous answers).
        var step2Past = document.getElementById('step2Past');
        var step2Now = document.getElementById('step2Now');
        var laterOutcomePast = document.getElementById('rerouteLaterOutcomePast');
        var advBox = document.getElementById('advToggle');
        var advOn = !!(advBox && advBox.checked);
        var laterTicket = document.getElementById('rerouteLaterTicketUpload');
        // Hide everything by default, then progressively reveal.
        hideBlock(step2Past);
        hideBlock(step2Now);
        hideBlock(laterOutcomePast);
        hideBlock(laterTicket);
        hideBlock(apprPast);
        hideBlock(apprNow);
        hideBlock(riWrapPast);
        hideBlock(recBlockPast);
        hideBlock(recBlockNow);
        if (dgcBlockPast) dgcBlockPast.style.display = ((advPast && advPast.open) || advOn) ? '' : 'none';
        if (dgcBlockNow) dgcBlockNow.style.display = ((advNow && advNow.open) || advOn) ? '' : 'none';
        if (live) { live.textContent = 'TRIN 7: Art.18(3) flow aktiv'; }

        // If not in reroute/refund, nothing to show.
        var isReroute = (val === 'reroute_soonest' || val === 'reroute_later');
        if (!isReroute) {
            // Refund flow is handled by returnExpensePast toggles above.
            if (live) { live.textContent = 'TRIN 7: ikke-omlaegning'; }
        } else {
            // Progressive reveal for reroute paths.
            // 1) Ask whether user self-purchased a new ticket (Art.18(3) trigger).
            if (a183On) showBlock(step2Past);
            if (!a183On) {
                // When Art.18(3) is OFF, we still collect a simple offer indicator, but only after reroute is chosen.
                var offProvPast = document.getElementById('offerProvidedWrapPast');
                if (offProvPast) offProvPast.hidden = false;
                if (live) { live.textContent = 'TRIN 7: afventer omlaegningstilbud'; }
            } else if (!selfBuy) {
                if (live) { live.textContent = 'TRIN 7: afventer selvkoeb'; }
            } else {
                // For reroute_later, keep the extra outcome question, but only after self-purchase reason is selected (single-question feel).
                var sprSel = document.querySelector('select[name=\"self_purchase_reason\"]');
                var spr = sprSel ? (sprSel.value || '') : '';
                var stop = false;
                if (val === 'reroute_later') {
                    if (!spr && selfBuy === 'yes') {
                        if (live) { live.textContent = 'TRIN 7: afventer hvorfor du koebte selv'; }
                        // stop here (only show reason select)
                        // (reason select is inside step2Past and already gated by data-show-if)
                        stop = true;
                    }
                    if (!stop && selfBuy === 'yes') showBlock(laterOutcomePast);
                    var rloEl = document.querySelector('input[name=\"reroute_later_outcome\"]:checked');
                    var rlo = rloEl ? (rloEl.value || '') : '';
                    if (!stop && selfBuy === 'yes' && !rlo) {
                        if (live) { live.textContent = 'TRIN 7: afventer udfald (senere)'; }
                        stop = true;
                    }
                    if (!stop && laterTicket) {
                        if (selfBuy === 'yes' && rlo === 'self_bought') showBlock(laterTicket);
                    }
                }

                // 2) Ask operator approval when self purchase = yes (preapproval pathway).
                if (!stop && selfBuy === 'yes') {
                    showBlock(apprPast);
                    if (!opAppr) {
                        if (live) { live.textContent = 'TRIN 7: afventer godkendelse'; }
                        stop = true;
                    }
                }

                // 3) Ask 100-min only when self purchase=yes and operator not preapproved.
                var needRi = a183On && isReroute && (selfBuy === 'yes') && (opAppr !== 'yes');
                if (!stop && needRi) {
                    showBlock(riWrapPast);
                    if (!ri100) {
                        if (live) { live.textContent = 'TRIN 7: afventer 100-min'; }
                        stop = true;
                    }
                }

                // 4) Extra costs question comes last (Art.18(2)).
                if (!stop) {
                    if (a182On) showBlock(recBlockPast);
                    if (a182On) showBlock(recBlockNow);
                }
            }
        }
        if (note100FallbackPast) note100FallbackPast.style.display = 'none';
        if (note100ManualPast) note100ManualPast.style.display = 'none';
        if (a183On && (val === 'reroute_soonest' || val === 'reroute_later') && (selfBuy === 'yes') && (opAppr !== 'yes')) {
            if (ri100 === 'no' && note100FallbackPast) note100FallbackPast.style.display = '';
            if (ri100 === 'yes' && note100ManualPast) note100ManualPast.style.display = '';
        }

        // Reset any prior locks (ensures toggling between scenarios clears stale states)
        disableGroup('reroute_extra_costs', false);
        disableGroup('downgrade_occurred', false);
        setBlockDisabled('recBlockPast', false);
        setBlockDisabled('recBlockNow', false);
        setBlockDisabled('dgcBlockPast', false);
        setBlockDisabled('dgcBlockNow', false);

        // Reset approval UI first (keep class/hidden attribute in sync with progressive reveal)
        if (selfBuy === 'yes') { showBlock(apprPast); showBlock(apprNow); }
        else { hideBlock(apprPast); hideBlock(apprNow); }
        if (noteApprovedPast) noteApprovedPast.style.display='none';
        if (noteApprovedNow) noteApprovedNow.style.display='none';
        // Return-to-origin expense fields toggle (shared radio group)
        var rtVal = (document.querySelector('input[name="return_to_origin_expense"]:checked') || {}).value || '';
        var rtPastFields = document.getElementById('returnExpenseFieldsPast');
        var rtNowFields = document.getElementById('returnExpenseFieldsNow');
        var showRt = (rtVal === 'yes');
        if (rtPastFields) { rtPastFields.style.display = showRt ? '' : 'none'; }
        if (rtNowFields) { rtNowFields.style.display = showRt ? '' : 'none'; }

        // Scenario logic differs when Art. 18(3) OFF:
        // OFF: extra costs only if selfBuy==yes AND operator approved (opAppr==yes). Downgrade always shown for reroute.
        // ON: retain legacy 100-min branching (C/A/B cases).
        var isReroute = (val === 'reroute_soonest' || val === 'reroute_later');
        if (a183On) {
            // Scenario C: offered within 100 AND self purchased
            if (isReroute && ri100 === 'yes' && selfBuy === 'yes') {
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
            else if (isReroute && (ri100 === 'no') && selfBuy === 'yes') {
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
            else if (isReroute && ri100 === 'yes' && (selfBuy === 'no')) {
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

    function mapsInitTrin6(initialPayload){
        // There is both a hidden input (value=0) and a checkbox (value=1).
        // Bind UI behavior to the checkbox, not the hidden field.
        var cb = document.querySelector('input[type="checkbox"][name="maps_opt_in_trin6"]');
        var panel = document.getElementById('mapsPanelTrin6');
        var originEl = document.getElementById('mapsOriginTrin6');
        var destEl = document.getElementById('mapsDestTrin6');
        var btn = document.getElementById('mapsFetchTrin6');
        var open = document.getElementById('mapsOpenTrin6');
        var out = document.getElementById('mapsRoutesTrin6');
        var status = document.getElementById('mapsStatusTrin6');
        if (!cb || !panel || !originEl || !destEl || !btn || !open || !out) { return; }

        function getOrigin(){ return (originEl.value || '').trim(); }
        function getDest(){ return (destEl.value || '').trim(); }
        function setOpenLink(){
            var o = encodeURIComponent(getOrigin());
            var d = encodeURIComponent(getDest());
            open.href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
        }
        function updatePanel(){
            panel.classList.toggle('hidden', !cb.checked);
            panel.hidden = !cb.checked;
            setOpenLink();
        }
        function renderRoutes(payload, opts){
            opts = opts || {};
            out.innerHTML = '';
            if (!payload || !payload.ok) {
                var msg = (payload && payload.error) ? String(payload.error) : 'Kunne ikke hente ruter.';
                // Make common Google Routes errors more actionable for noobs.
                if (/HTTP\\s*403/i.test(msg) || /IP address restriction/i.test(msg) || /violates this restriction/i.test(msg)) {
                    msg = 'Google Routes fejler (HTTP 403): Din API key er IP-restricted. Whitelist serverens IP i Google Cloud Console (eller fjern IP restriction), og sikre at Routes API er enabled.';
                }
                out.innerHTML = '<div class="small muted">' + ('' + msg).replace(/</g,'&lt;') + '</div>';
                return;
            }
            var routes = Array.isArray(payload.routes) ? payload.routes.slice() : [];
            if (!routes.length) {
                out.innerHTML = '<div class="small muted">Ingen forslag fundet.</div>';
                return;
            }

            function pad2(n){ n = String(n||''); return n.length === 1 ? ('0' + n) : n; }
            function hhmm(ts){
                if (!ts) return '';
                var m = /T(\\d{2}:\\d{2})/.exec(ts.toString());
                return m ? m[1] : '';
            }
            function fmtDurationS(sec){
                sec = parseInt(sec || 0, 10);
                if (!isFinite(sec) || sec <= 0) return '';
                var min = Math.round(sec / 60);
                var h = Math.floor(min / 60);
                var m = min % 60;
                if (h <= 0) return min + ' min';
                return h + 't ' + pad2(m) + 'm';
            }
            function fmtKm(m){
                m = parseInt(m || 0, 10);
                if (!isFinite(m) || m <= 0) return '';
                var km = Math.round((m / 1000) * 10) / 10;
                return ('' + km).replace('.', ',') + ' km';
            }
            function vehicleLabel(v){
                v = (v || '').toString().toUpperCase();
                if (v.indexOf('TRAIN') >= 0 || v === 'RAIL') return 'Tog';
                if (v.indexOf('BUS') >= 0) return 'Bus';
                if (v.indexOf('SUBWAY') >= 0) return 'Metro';
                if (v.indexOf('LIGHT_RAIL') >= 0 || v.indexOf('TRAM') >= 0) return 'Letbane';
                if (v.indexOf('FERRY') >= 0) return 'Faerge';
                return v || 'Transit';
            }
            function vehicleIcon(v){
                v = (v || '').toString().toUpperCase();
                // Keep source ASCII-only; use unicode escapes for icons.
                if (v.indexOf('TRAIN') >= 0 || v === 'RAIL') return '\\uD83D\\uDE86';      // ðŸš†
                if (v.indexOf('BUS') >= 0) return '\\uD83D\\uDE8C';                       // ðŸšŒ
                if (v.indexOf('SUBWAY') >= 0) return '\\uD83D\\uDE87';                    // ðŸš‡
                if (v.indexOf('LIGHT_RAIL') >= 0 || v.indexOf('TRAM') >= 0) return '\\uD83D\\uDE8A'; // ðŸšŠ
                if (v.indexOf('FERRY') >= 0) return '\\u26F4';                            // â›´
                return '\\uD83D\\uDE8D';                                                  // ðŸš
            }
            function routeTimes(r){
                var segs = Array.isArray(r.segments) ? r.segments : [];
                if (!segs.length) return { start:'', end:'' };
                var start = hhmm(segs[0].dep_time);
                var end = hhmm(segs[segs.length - 1].arr_time);
                return { start:start, end:end };
            }
            function routeKey(r){
                var segs = Array.isArray(r.segments) ? r.segments : [];
                var segKey = segs.map(function(s){
                    return [
                        (s.vehicle||''), (s.line||''), (s.from||''), (s.to||''),
                        hhmm(s.dep_time), hhmm(s.arr_time)
                    ].join('~');
                }).join('|');
                var lines = Array.isArray(r.transit_lines) ? r.transit_lines.join(',') : '';
                return [r.duration_s||'', r.distance_m||'', r.transfers||'', lines, segKey].join('||');
            }
            function buildMapsDirLink(origin, dest){
                var o = encodeURIComponent((origin || '').trim());
                var d = encodeURIComponent((dest || '').trim());
                return 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
            }

            // Dedup + sort by duration (fastest first)
            var seen = new Set();
            var uniq = [];
            routes.forEach(function(r){
                if (!r || typeof r !== 'object') return;
                var k = routeKey(r);
                if (seen.has(k)) return;
                seen.add(k);
                uniq.push(r);
            });
            uniq.sort(function(a,b){
                return (parseInt(a.duration_s||0,10) || 0) - (parseInt(b.duration_s||0,10) || 0);
            });

            var titleText = (opts && opts.title) ? String(opts.title) : '';
            if (titleText) {
                var title = document.createElement('div');
                title.className = 'maps-routes-title small muted';
                title.textContent = titleText;
                out.appendChild(title);
            }

            uniq.forEach(function(r, idx){
                var box = document.createElement('div');
                box.className = 'maps-route';

                var t = routeTimes(r);
                var durTxt = fmtDurationS(r.duration_s);
                var kmTxt = fmtKm(r.distance_m);
                var tr = parseInt(r.transfers || 0, 10) || 0;
                var transferTxt = (tr > 0) ? (tr + ' skift') : 'Direkte';

                var head = document.createElement('div');
                head.className = 'maps-route-head';

                var time = document.createElement('div');
                time.className = 'maps-route-time';
                time.textContent = (t.start && t.end) ? (t.start + 'â€“' + t.end) : (r.summary || ('Rute ' + (idx+1)));
                head.appendChild(time);

                var meta = document.createElement('div');
                meta.className = 'maps-route-meta';
                meta.textContent = [durTxt, kmTxt, transferTxt].filter(Boolean).join(' â€¢ ');
                head.appendChild(meta);

                box.appendChild(head);

                var chips = Array.isArray(r.transit_lines) ? r.transit_lines : [];
                if (chips.length) {
                    var chipWrap = document.createElement('div');
                    chipWrap.className = 'maps-chips';
                    chips.slice(0, 8).forEach(function(c){
                        var sp = document.createElement('span');
                        sp.className = 'maps-chip';
                        sp.textContent = String(c);
                        chipWrap.appendChild(sp);
                    });
                    box.appendChild(chipWrap);
                }

                var segs = Array.isArray(r.segments) ? r.segments : [];
                if (segs.length) {
                    var ul = document.createElement('ul');
                    ul.className = 'maps-segs small muted';
                    segs.forEach(function(s){
                        s = s || {};
                        var li = document.createElement('li');
                        var icon = vehicleIcon(s.vehicle);
                        var mode = vehicleLabel(s.vehicle);
                        var line = (s.line || '').toString().trim();
                        var from = (s.from || '').toString().trim();
                        var to = (s.to || '').toString().trim();
                        var dep = hhmm(s.dep_time);
                        var arr = hhmm(s.arr_time);
                        var headTxt = (mode + (line ? (' ' + line) : '')).trim();
                        var parts = [];
                        if (from || to) parts.push((from || '?') + ' -> ' + (to || '?'));
                        if (dep || arr) parts.push((dep || '') + (arr ? ('-' + arr) : ''));
                        li.textContent = icon + ' ' + headTxt + (parts.length ? (': ' + parts.join(' / ')) : '');
                        ul.appendChild(li);
                    });
                    box.appendChild(ul);
                }

                var actions = document.createElement('div');
                actions.className = 'maps-actions';

                var segOrigin = (segs[0] && segs[0].from) ? String(segs[0].from) : getOrigin();
                var segDest = (segs[segs.length - 1] && segs[segs.length - 1].to) ? String(segs[segs.length - 1].to) : getDest();
                var a = document.createElement('a');
                a.className = 'button button-outline';
                a.target = '_blank';
                a.rel = 'noopener';
                a.href = buildMapsDirLink(segOrigin, segDest);
                a.textContent = 'Aabn i Google Maps (detaljer)';
                actions.appendChild(a);

                box.appendChild(actions);
                out.appendChild(box);
            });
        }

        cb.addEventListener('change', updatePanel);
        originEl.dataset.manual = originEl.dataset.manual || '0';
        destEl.dataset.manual = destEl.dataset.manual || '0';
        originEl.addEventListener('input', function(){ originEl.dataset.manual = '1'; setOpenLink(); });
        destEl.addEventListener('input', function(){ destEl.dataset.manual = '1'; setOpenLink(); });
        destEl.addEventListener('input', setOpenLink);

        btn.addEventListener('click', async function(){
            var origin = getOrigin();
            var dest = getDest();
            setOpenLink();
            if (!origin || !dest) {
                if (status) status.textContent = 'Angiv start og destination foerst.';
                return;
            }
            if (status) status.textContent = 'Henter...';
            try {
                var csrf = (document.querySelector('input[name="_csrfToken"]') || {}).value || '';
                var res = await fetch(panel.getAttribute('data-endpoint'), {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded', ...(csrf ? {'X-CSRF-Token': csrf} : {})},
                    body: new URLSearchParams({
                        context: 'trin6',
                        maps_opt_in_trin6: '1',
                        origin: origin,
                        destination: dest
                    })
                });
                var j = await res.json();
                renderRoutes(j, { title: 'Forslag (Google Routes)' });
                if (status) status.textContent = j && j.ok ? 'OK' : 'Fejl';
            } catch(e) {
                if (status) status.textContent = 'Fejl';
                renderRoutes({ok:false, error: (e && e.message) ? e.message : 'Fejl'});
            }
        });

        updatePanel();
        setOpenLink();
        // Render saved session routes on load (if any) using the richer UI.
        try {
            if (initialPayload && initialPayload.ok && Array.isArray(initialPayload.routes) && initialPayload.routes.length) {
                renderRoutes(initialPayload, { title: 'Seneste forslag (gemt i session)' });
            }
        } catch(e) { /* ignore */ }
    }

    // Keep MAP inputs in sync with the "Stationsvalg (omlaegning)" fields.
    // Only auto-sync when the user has not manually edited the MAP inputs.
    function syncMapsTrin6FromStations(){
        var originEl = document.getElementById('mapsOriginTrin6');
        var destEl = document.getElementById('mapsDestTrin6');
        var open = document.getElementById('mapsOpenTrin6');
        if (!originEl || !destEl || !open) return;
        originEl.dataset.manual = originEl.dataset.manual || '0';
        destEl.dataset.manual = destEl.dataset.manual || '0';

        function valSelect(name){
            var sel = document.querySelector('select[name=\"' + name + '\"]');
            return sel ? (sel.value || '') : '';
        }
        function valInput(name){
            var inp = document.querySelector('input[name=\"' + name + '\"]');
            return inp ? (inp.value || '') : '';
        }

        // Origin: a18_from_station (or other input)
        if (originEl.dataset.manual !== '1') {
            var fromSel = valSelect('a18_from_station');
            var fromOther = valInput('a18_from_station_other');
            var origin = '';
            if (fromSel === 'other') { origin = fromOther; }
            else if (fromSel && fromSel !== 'unknown') { origin = fromSel; }
            else if (fromOther) { origin = fromOther; }
            origin = (origin || '').trim();
            if (origin) { originEl.value = origin; }
        }

        // Destination: depends on reroute endpoint.
        if (destEl.dataset.manual !== '1') {
            var endpoint = (valSelect('a18_reroute_endpoint') || '').trim();
            var dest = '';
            if (endpoint === 'nearest_station' || endpoint === 'other_departure_point') {
                var endSel = valSelect('a18_reroute_arrival_station');
                var endOther = valInput('a18_reroute_arrival_station_other');
                if (endSel === 'other') { dest = endOther; }
                else if (endSel && endSel !== 'unknown') { dest = endSel; }
                else if (endOther) { dest = endOther; }
            }
            dest = (dest || '').trim();
            if (dest) { destEl.value = dest; }
        }

        // Update open link immediately.
        var o = encodeURIComponent((originEl.value || '').trim());
        var d = encodeURIComponent((destEl.value || '').trim());
        open.href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
    }

    // Optional: offline station autocomplete for the MAP inputs too (so ticketless works without Google Places).
    function initMapsStationAutocompleteTrin6(){
        if (!stationsSearchUrl) return;
        var originEl = document.getElementById('mapsOriginTrin6');
        var destEl = document.getElementById('mapsDestTrin6');
        var originBox = document.getElementById('mapsOriginSuggest');
        var destBox = document.getElementById('mapsDestSuggest');
        if (!originEl || !destEl || !originBox || !destBox) return;

        function niceType(t){
            var s = (t || '').toString().toLowerCase();
            if (s === 'station') return 'Station';
            if (s === 'halt') return 'Stopested';
            return s;
        }

        function setup(input, box){
            var timer = null;
            var ctrl = null;

            function hide(){
                box.style.display = 'none';
                box.innerHTML = '';
            }

            function render(stations){
                box.innerHTML = '';
                if (!stations || !stations.length) { hide(); return; }

                // Prefer "station" results to reduce noise for city-level queries.
                var stStations = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() === 'station'; });
                var stOthers = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() !== 'station'; });
                var shown = (stStations.length >= 5) ? stStations : stStations.concat(stOthers);

                shown.slice(0, 10).forEach(function(st){
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    var nm = (st && st.name) ? String(st.name) : '';
                    var cc = (st && st.country) ? String(st.country) : '';
                    var tp = (st && st.type) ? String(st.type) : '';
                    btn.appendChild(document.createTextNode(nm || '(ukendt station)'));
                    if (cc || tp) {
                        var meta = document.createElement('div');
                        meta.className = 'muted';
                        meta.textContent = [cc, niceType(tp)].filter(Boolean).join(' \\u00b7 ');
                        btn.appendChild(document.createElement('br'));
                        btn.appendChild(meta);
                    }
                    btn.addEventListener('click', function(){
                        if (nm) input.value = nm;
                        hide();
                        input.dispatchEvent(new Event('input', { bubbles:true }));
                    });
                    box.appendChild(btn);
                });
                box.style.display = 'block';
            }

            async function fetchStations(){
                var q = (input.value || '').trim();
                if (q.length < 2) { hide(); return; }
                var cc = (stationCountryDefault || '').trim().toUpperCase();
                if (!cc && q.length < 4) { hide(); return; }

                var buildUrl = function(country){
                    var u = new URL(stationsSearchUrl, window.location.origin);
                    u.searchParams.set('q', q);
                    if (country) u.searchParams.set('country', country);
                    u.searchParams.set('limit', '10');
                    return u.toString();
                };

                if (ctrl) { try { ctrl.abort(); } catch(e) {} }
                ctrl = new AbortController();
                try {
                    var res = await fetch(buildUrl(cc), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
                    if (!res.ok) { hide(); return; }
                    var js = await res.json();
                    var stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
                    if ((!stations || stations.length === 0) && cc) {
                        res = await fetch(buildUrl(''), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            js = await res.json();
                            stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
                        }
                    }
                    render(stations);
                } catch(e) {
                    // ignore abort/network
                }
            }

            input.addEventListener('input', function(){
                if (timer) clearTimeout(timer);
                timer = setTimeout(fetchStations, 200);
            });
            input.addEventListener('focus', function(){
                if (box.innerHTML.trim() !== '') { box.style.display = 'block'; }
            });
            input.addEventListener('blur', function(){ setTimeout(hide, 180); });
            box.addEventListener('mousedown', function(e){ e.preventDefault(); });
        }

        setup(originEl, originBox);
        setup(destEl, destBox);
    }

    // Reuse TRIN 2 station autocomplete: only active when the related <select> is set to "other".
	    function initStationAutocomplete(){
	        if (!stationsSearchUrl) return;
	        document.querySelectorAll('.station-autocomplete').forEach(function(lbl){
            var selectName = lbl.getAttribute('data-station-select') || '';
            var otherName = lbl.getAttribute('data-station-other') || '';
            if (!selectName || !otherName) return;
            var sel = lbl.querySelector('select[name="' + selectName + '"]');
            var input = lbl.querySelector('input[name="' + otherName + '"]');
            var box = lbl.querySelector('.station-suggest[data-for="' + otherName + '"]');
            if (!sel || !input || !box) return;

	            var timer = null;
	            var ctrl = null;
	            function niceType(t){
	                var s = (t || '').toString().toLowerCase();
	                if (s === 'station') return 'Station';
	                if (s === 'halt') return 'Stopested';
	                return s;
	            }
	            function metaInput(suffix){
	                return lbl.querySelector('input[name="' + otherName + '_' + suffix + '"]');
	            }
	            function clearMeta(){
	                ['osm_id','lat','lon','country','type','source'].forEach(function(s){
	                    var el = metaInput(s);
	                    if (el) el.value = '';
	                });
	            }
	            function setMeta(st){
	                if (!st) { clearMeta(); return; }
	                var v = function(k){
	                    return (st && st[k] !== undefined && st[k] !== null) ? String(st[k]) : '';
	                };
	                var m = {
	                    osm_id: v('osm_id'),
	                    lat: v('lat'),
	                    lon: v('lon'),
	                    country: v('country'),
	                    type: v('type'),
	                    source: v('source')
	                };
	                Object.keys(m).forEach(function(k){
	                    var el = metaInput(k);
	                    if (el) el.value = m[k];
	                });
	            }
	            function hide(){
	                box.style.display = 'none';
	                box.innerHTML = '';
	            }
	            function render(stations){
	                box.innerHTML = '';
	                if (!stations || !stations.length) { hide(); return; }

	                // Prefer "station" results to reduce noise for city-level queries.
	                var stStations = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() === 'station'; });
	                var stOthers = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() !== 'station'; });
	                var shown = (stStations.length >= 5) ? stStations : stStations.concat(stOthers);

	                shown.slice(0, 10).forEach(function(st){
	                    var btn = document.createElement('button');
	                    btn.type = 'button';
	                    var nm = (st && st.name) ? String(st.name) : '';
	                    var cc = (st && st.country) ? String(st.country) : '';
	                    var tp = (st && st.type) ? String(st.type) : '';
	                    btn.appendChild(document.createTextNode(nm || '(ukendt station)'));
	                    if (cc || tp) {
	                        var meta = document.createElement('div');
	                        meta.className = 'muted';
	                        meta.textContent = [cc, niceType(tp)].filter(Boolean).join(' \u00b7 ');
	                        btn.appendChild(document.createElement('br'));
	                        btn.appendChild(meta);
	                    }
	                    btn.addEventListener('click', function(){
	                        if (nm) input.value = nm;
	                        setMeta(st);
	                        hide();
	                    });
	                    box.appendChild(btn);
	                });
	                box.style.display = 'block';
	            }
            async function fetchStations(){
                if ((sel.value || '') !== 'other') { hide(); return; }
                var q = (input.value || '').trim();
                if (q.length < 2) { hide(); return; }
	                var cc = (stationCountryDefault || '').trim().toUpperCase();
	                if (!cc && q.length < 4) { hide(); return; }

	                // Two-phase lookup:
	                // 1) Try with country filter (fast, reduces noise)
	                // 2) If empty and we had a country, retry without country (international routes)
	                var buildUrl = function(country){
	                    var u = new URL(stationsSearchUrl, window.location.origin);
	                    u.searchParams.set('q', q);
	                    if (country) u.searchParams.set('country', country);
	                    u.searchParams.set('limit', '10');
	                    return u.toString();
	                };

                if (ctrl) { try { ctrl.abort(); } catch(e) {} }
                ctrl = new AbortController();
                try {
                    var res = await fetch(buildUrl(cc), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
                    if (!res.ok) { hide(); return; }
                    var js = await res.json();
                    var stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
                    if ((!stations || stations.length === 0) && cc) {
                        res = await fetch(buildUrl(''), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
                        if (res.ok) {
                            js = await res.json();
                            stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
                        }
                    }
                    render(stations);
                } catch(e) {
                    // ignore (abort or network)
                }
            }

 	            input.addEventListener('input', function(){
 	                // Ticketless UI can show the "other" input without exposing the <select>;
 	                // ensure the backing select stays on "other" when the user types.
 	                if ((sel.value || '') !== 'other') { sel.value = 'other'; }
 	                clearMeta();
 	                if (timer) clearTimeout(timer);
 	                timer = setTimeout(fetchStations, 200);
 	            });
            input.addEventListener('focus', function(){
                if (box.innerHTML.trim() !== '' && (sel.value || '') === 'other') { box.style.display = 'block'; }
            });
            input.addEventListener('blur', function(){ setTimeout(hide, 180); });
            box.addEventListener('mousedown', function(e){ e.preventDefault(); });
            sel.addEventListener('change', function(){
                if ((sel.value || '') !== 'other') { hide(); }
            });
        });
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
        initStationAutocomplete();
        initMapsStationAutocompleteTrin6();
        mapsInitTrin6(savedMapsTrin6);
        try { syncMapsTrin6FromStations(); } catch(e) { /* ignore */ }
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
            // Keep MAP helper aligned with station selection where possible.
            if (e.target.name.indexOf('a18_') === 0 || e.target.name.indexOf('handoff') === 0 || e.target.name.indexOf('stranded_current_station') === 0) {
                try { syncMapsTrin6FromStations(); } catch(e) { /* ignore */ }
            }
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

</fieldset>
<?= $this->Form->end() ?>
</div>
