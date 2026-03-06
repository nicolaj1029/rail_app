<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');
$isBeforeStart = ($travelState === 'before_start');
$isFutureLike = ($isOngoing || $isBeforeStart);
$maps = $maps ?? [];
$mapsOptIn = !empty($form['maps_opt_in_trin5']);
$mapsTrin5 = (is_array($maps) && isset($maps['trin5']) && is_array($maps['trin5'])) ? $maps['trin5'] : null;
$transportHint = 'Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.';
if ($isFutureLike) { $transportHint .= ' (Udfyld det, der er sket indtil nu).'; }
$transportTitle = $isOngoing
    ? 'TRIN 6 - Er du strandet? (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 6 - Er du strandet? (afsluttet rejse)' : ($isBeforeStart ? 'TRIN 6 - Er du strandet? (rejsen starter senere)' : 'TRIN 6 - Er du strandet? (Art. 20)'));

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
// Use a literal path to avoid the URL builder selecting the /api/demo/v2 scope fallback route.
$stationsSearchUrl = $this->Url->build('/api/stations/search');
$stationCountryDefault = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
try {
    if ($stationCountryDefault !== '' && strlen($stationCountryDefault) !== 2) {
        $cc = (new \App\Service\CountryNormalizer())->toIso2($stationCountryDefault);
        if ($cc !== '') { $stationCountryDefault = $cc; }
    }
} catch (\Throwable $e) { /* ignore */ }
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

    /* Keep conditional blocks hidden unless reveal JS says otherwise (prevents "always visible" glitches). */
    [data-show-if] { display:none; }

    /* In locked preview mode we only show the top question, not the conditional branches. */
    .flow-preview [data-show-if] { display:none !important; }
    .flow-preview #resolutionWrapTrin5 { display:none !important; }
</style>
<div class="flow-wrapper">
<h1><?= h($transportTitle) ?></h1>
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
    // Art. 20 gating (TRIN 5 now focuses on Art.20(2)(c) - "blocked on track").
    // Art.20(3) (station) is collected in TRIN 4.
    $art20TrackOff = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
    $art20Disabled = $art20TrackOff;
    $showTrack = !$art20TrackOff;
    $v = fn(string $k): string => (string)($form[$k] ?? '');
    $isPreview = !empty($flowPreview);
    // IMPORTANT: TRIN 5 must default to "Nej" regardless of what happened in earlier steps.
    // We store TRIN 5's local toggle in a separate key and only derive shared Art.20 keys server-side.
    $isStrandedTrin5 = strtolower(trim((string)($form['is_stranded_trin5'] ?? 'no')));
    if ($isStrandedTrin5 !== 'yes' && $isStrandedTrin5 !== 'no') { $isStrandedTrin5 = 'no'; }
?>
<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'choices'], 'type' => 'file', 'novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div id="coreAfterArt20">
    <div id="art20Wrapper" class="card mt12 <?= ($art20Disabled && !$isPreview) ? 'hidden' : '' ?>" data-art="20" data-art20-disabled="<?= $art20Disabled ? '1' : '0' ?>">
        <div class="card-title"><span class="icon">&#128652;</span><span>Transport til/fra (Art.20)</span></div>
        <p class="small muted"><?= h($transportHint) ?></p>

        <div class="mt8">
            <div>Sad du fast i et tog paa sporet (Art.20(2)(c))?</div>
            <label><input type="radio" name="is_stranded_trin5" value="yes" <?= $isStrandedTrin5==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="is_stranded_trin5" value="no" <?= $isStrandedTrin5==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <?php /* NOTE: We do NOT POST stranded_location from this step. Controller derives it when is_stranded_trin5=yes.
                 Posting a hidden checked input caused cross-step overwrites (TRIN 4 station endpoint got cleared). */ ?>

        <?php
            $depDefault = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
            $destDefault = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
            $missedDefault = trim((string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? '')));
            // For MAP start-point: prefer stranded station (TRIN 4) when present, else missed connection, else departure.
            $scs = trim((string)($form['stranded_current_station'] ?? ''));
            if ($scs === 'other') { $scs = trim((string)($form['stranded_current_station_other'] ?? '')); }
            if ($scs === 'unknown') { $scs = ''; }
            $originDefault = $scs !== '' ? $scs : ($missedDefault !== '' ? $missedDefault : $depDefault);
            $mapsConfigured = ((string)(getenv('GOOGLE_MAPS_SERVER_KEY') ?: (getenv('GOOGLE_MAPS_API_KEY') ?: ''))) !== '';
        ?>
        <div class="card mt12" id="mapsCardTrin5" style="background:#f8f9fb;" data-show-if="is_stranded_trin5:yes">
            <div class="card-title"><span class="icon">MAP</span><span>Ruter (Google Maps, valgfrit)</span></div>
            <div class="small muted mt4">Klik for at hente forslag til oml&aelig;gning. Vi sender start/destination til Google for at finde ruter.</div>
            <input type="hidden" name="maps_opt_in_trin5" value="0" />
            <label class="mt8"><input type="checkbox" name="maps_opt_in_trin5" value="1" <?= $mapsOptIn ? 'checked' : '' ?> /> Brug Google Maps i denne sag</label>

            <div id="mapsPanelTrin5" class="mt8 <?= $mapsOptIn ? '' : 'hidden' ?>" data-endpoint="<?= h($this->Url->build(['controller' => 'Flow', 'action' => 'mapsRoutes'])) ?>">
                <div class="grid-2">
                    <label>Fra (station)
                        <input type="text" id="mapsOriginTrin5" value="<?= h($originDefault) ?>" />
                        <div class="small muted mt4">Tip: Brug missed connection / din nuv&aelig;rende station som start.</div>
                    </label>
                    <label>Til (destination)
                        <input type="text" id="mapsDestTrin5" value="<?= h($destDefault) ?>" readonly />
                        <div class="small muted mt4">Hentes fra billetten (destination).</div>
                    </label>
                </div>

                <div class="mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <button type="button" class="button" id="mapsFetchTrin5" <?= $mapsConfigured ? '' : 'disabled' ?>>Hent forslag</button>
                    <a class="button" id="mapsOpenTrin5" target="_blank" rel="noopener">Aabn i Google Maps</a>
                    <span class="small muted" id="mapsStatusTrin5"></span>
                </div>
                <?php if (!$mapsConfigured): ?>
                    <div class="small muted mt8">Bemaerk: Google Routes er ikke konfigureret (mangler server API key).</div>
                <?php endif; ?>

                <div id="mapsRoutesTrin5" class="mt8"></div>

                <?php if (is_array($mapsTrin5) && !empty($mapsTrin5['routes'])): ?>
                    <div class="mt8 small muted">Seneste forslag (gemt i session):</div>
                    <ul class="small">
                        <?php foreach ((array)$mapsTrin5['routes'] as $r): ?>
                            <?php if (!is_array($r)) { continue; } ?>
                            <li>
                                <div><strong><?= h((string)($r['summary'] ?? '')) ?></strong></div>
                                <?php if (!empty($r['segments']) && is_array($r['segments'])): ?>
                                    <ul class="small muted mt4">
                                        <?php foreach ((array)$r['segments'] as $s): ?>
                                            <?php if (!is_array($s)) { continue; } ?>
                                            <?php
                                                $veh = strtoupper((string)($s['vehicle'] ?? ''));
                                                $vehLabel = $veh;
                                                if (strpos($veh, 'TRAIN') !== false || $veh === 'RAIL') { $vehLabel = 'Tog'; }
                                                elseif (strpos($veh, 'BUS') !== false) { $vehLabel = 'Bus'; }
                                                elseif (strpos($veh, 'SUBWAY') !== false) { $vehLabel = 'Metro'; }
                                                elseif (strpos($veh, 'LIGHT_RAIL') !== false || strpos($veh, 'TRAM') !== false) { $vehLabel = 'Letbane'; }
                                                $line = trim((string)($s['line'] ?? ''));
                                                $from = trim((string)($s['from'] ?? ''));
                                                $to = trim((string)($s['to'] ?? ''));
                                                $txt = trim($vehLabel . ($line !== '' ? (' ' . $line) : ''));
                                                if ($from !== '' || $to !== '') { $txt .= ': ' . ($from !== '' ? $from : '?') . ' -> ' . ($to !== '' ? $to : '?'); }
                                            ?>
                                            <li><?= h($txt) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php
            // IMPORTANT: Only ask follow-up questions when the user explicitly answered "Ja" to being stranded on track.
            // We do NOT rely on posting `stranded_location` from this step (controller derives it on submit).
            $showTrackFlow = ($showTrack && $isStrandedTrin5 === 'yes');
        ?>
        <div class="<?= $showTrackFlow ? '' : 'hidden' ?>" data-show-if="is_stranded_trin5:yes" data-art="20(2c)">
            <div class="mt4">
                <span>Blev der stillet transport til raadighed for at komme vaek/videre?</span>
                <?php $bt = $v('blocked_train_alt_transport'); ?>
                <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>
            </div>

            <div class="mt4" data-show-if="blocked_train_alt_transport:yes" data-art="20(2c)">
                <div class="grid-2">
                    <label>Transporttype
                        <?php $tt = $v('assistance_alt_transport_type'); ?>
                        <select name="assistance_alt_transport_type">
                            <option value="">Vaelg</option>
                            <option value="rail" <?= $tt==='rail'?'selected':'' ?>>Tog</option>
                            <option value="bus" <?= $tt==='bus'?'selected':'' ?>>Bus</option>
                            <option value="taxi" <?= $tt==='taxi'?'selected':'' ?>>Taxi</option>
                            <option value="other" <?= $tt==='other'?'selected':'' ?>>Andet</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="mt4" data-show-if="blocked_train_alt_transport:no" data-art="20(2c)">
                <div>Hvad gjorde du saa?</div>
                <?php $bn = $v('blocked_no_transport_action'); ?>
                <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="waited" <?= $bn==='waited'?'checked':'' ?> /> Ventede til toget kunne koere videre</label>
                <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="walked_station" <?= $bn==='walked_station'?'checked':'' ?> /> Fandt selv vej til station/spor</label>
                <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="evacuated_later" <?= $bn==='evacuated_later'?'checked':'' ?> /> Blev evakueret senere</label>
                <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="self_arranged" <?= $bn==='self_arranged'?'checked':'' ?> /> Fandt selv transport</label>
                <label class="ml8"><input type="radio" name="blocked_no_transport_action" value="other" <?= $bn==='other'?'checked':'' ?> /> Andet</label>
            </div>

            <div class="mt4" data-show-if="blocked_no_transport_action:self_arranged" data-art="20(2c)">
            <div class="small muted">Angiv egne udgifter hvis du selv ordnede transport.</div>
            <div class="grid-2 mt4">
                <label>Transporttype
                    <?php $bst = $v('blocked_self_paid_transport_type'); ?>
                    <select name="blocked_self_paid_transport_type">
                        <option value="">Vaelg</option>
                        <option value="rail" <?= $bst==='rail'?'selected':'' ?>>Tog</option>
                        <option value="bus" <?= $bst==='bus'?'selected':'' ?>>Bus</option>
                        <option value="taxi" <?= $bst==='taxi'?'selected':'' ?>>Taxi</option>
                        <option value="rideshare" <?= $bst==='rideshare'?'selected':'' ?>>Samkoersel/rideshare</option>
                        <option value="other" <?= $bst==='other'?'selected':'' ?>>Andet</option>
                    </select>
                </label>
                <label>Beloeb
                    <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($v('blocked_self_paid_amount')) ?>" />
                </label>
                <label>Valuta (fx DKK/EUR)
                    <input type="text" name="blocked_self_paid_currency" value="<?= h($v('blocked_self_paid_currency')) ?>" />
                </label>
                <label class="small">Kvittering
                    <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
                </label>
            </div>
            <?php if ($f = $v('blocked_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        </div>
        </div>

        <!-- Common resolution endpoint (TRIN 5 / Art.20): where did you end up after being stranded? -->
        <?php
            $toEnd = $v('a20_where_ended');
            $arrEnd = $v('a20_arrival_station');
            $arrEndOther = $v('a20_arrival_station_other');
        ?>
        <div id="resolutionWrapTrin5" class="mt12" style="display:none;">
            <div><strong>Hvor endte du?</strong></div>
            <div class="grid-2 mt8">
                <label>Slutpunkt
                    <select name="a20_where_ended">
                        <option value="">Vaelg</option>
                        <option value="nearest_station" <?= $toEnd==='nearest_station'?'selected':'' ?>>Naermeste station</option>
                        <option value="other_departure_point" <?= $toEnd==='other_departure_point'?'selected':'' ?>>Et andet afgangssted</option>
                        <option value="final_destination" <?= $toEnd==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
                    </select>
                    <div class="small muted mt4" data-show-if="a20_where_ended:nearest_station,other_departure_point">Angiv station, saa vi kan beregne downgrade korrekt.</div>
                </label>
                <label class="station-autocomplete" data-station-select="a20_arrival_station" data-station-other="a20_arrival_station_other" data-show-if="a20_where_ended:nearest_station,other_departure_point">Hvilken station endte du ved?
                    <select name="a20_arrival_station">
                        <option value="">Vaelg</option>
                        <?php foreach ($stationOptions as $st): ?>
                            <option value="<?= h($st) ?>" <?= $arrEnd===$st?'selected':'' ?>><?= h($st) ?></option>
                        <?php endforeach; ?>
                        <option value="unknown" <?= $arrEnd==='unknown'?'selected':'' ?>>Ved ikke</option>
                        <option value="other" <?= $arrEnd==='other'?'selected':'' ?>>Anden station</option>
                    </select>
	                    <input type="text" name="a20_arrival_station_other" value="<?= h($arrEndOther) ?>" placeholder="Anden station" data-show-if="a20_arrival_station:other" />
	                    <input type="hidden" name="a20_arrival_station_other_osm_id" value="<?= h($v('a20_arrival_station_other_osm_id')) ?>" />
	                    <input type="hidden" name="a20_arrival_station_other_lat" value="<?= h($v('a20_arrival_station_other_lat')) ?>" />
	                    <input type="hidden" name="a20_arrival_station_other_lon" value="<?= h($v('a20_arrival_station_other_lon')) ?>" />
	                    <input type="hidden" name="a20_arrival_station_other_country" value="<?= h($v('a20_arrival_station_other_country')) ?>" />
	                    <input type="hidden" name="a20_arrival_station_other_type" value="<?= h($v('a20_arrival_station_other_type')) ?>" />
	                    <input type="hidden" name="a20_arrival_station_other_source" value="<?= h($v('a20_arrival_station_other_source')) ?>" />
	                    <div class="station-suggest" data-for="a20_arrival_station_other" style="display:none;"></div>
	                </label>
            </div>
        </div>

    </div>
</div>

<script>
(function(){
    const stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
    const stationCountryDefault = <?= json_encode((string)$stationCountryDefault, JSON_UNESCAPED_SLASHES) ?>;
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
                if (sel) { val = sel.value || ''; }
            }
            var show = val && valid.includes(val);
            el.style.display = show ? 'block' : 'none';
            el.hidden = !show;
        });
    }
    function getVal(name) {
        var checked = document.querySelector('input[name="' + name + '"]:checked');
        if (checked) { return checked.value || ''; }
        var sel = document.querySelector('select[name="' + name + '"]');
        if (sel) { return sel.value || ''; }
        var inp = document.querySelector('input[name="' + name + '"]');
        if (inp && inp.type !== 'radio' && inp.type !== 'checkbox') { return inp.value || ''; }
        return '';
    }
    function setBlockVisible(el, show) {
        if (!el) return;
        el.style.display = show ? 'block' : 'none';
        el.hidden = !show;
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
    function clearFields(names) { names.forEach(clearField); }
    function updateResolutionVisibility() {
        var wrap = document.getElementById('resolutionWrapTrin5');
        if (!wrap) return;

        var stranded = getVal('is_stranded_trin5');

        var shouldShow = false;
        if (stranded === 'yes') {
            var bt = getVal('blocked_train_alt_transport');
            if (bt === 'yes') { shouldShow = true; }
            else if (bt === 'no') { shouldShow = !!getVal('blocked_no_transport_action'); }
        }
        setBlockVisible(wrap, shouldShow);

        // If hidden, clear stale resolution fields.
        if (!shouldShow && stranded === 'yes') {
            clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
        }
    }

    // If TRIN 6 ends at a station, the most useful MAP start-point is that station (not the original departure).
    // Only auto-sync if the user hasn't manually edited the MAP origin field.
    function syncMapsOriginFromResolution(){
        var cb = document.querySelector('input[type="checkbox"][name="maps_opt_in_trin5"]');
        var originEl = document.getElementById('mapsOriginTrin5');
        var destEl = document.getElementById('mapsDestTrin5');
        var open = document.getElementById('mapsOpenTrin5');
        if (!cb || !cb.checked || !originEl || !destEl || !open) return;
        originEl.dataset.manual = originEl.dataset.manual || '0';
        if (originEl.dataset.manual === '1') return;

        var stranded = getVal('is_stranded_trin5');
        if (stranded !== 'yes') return;
        var ended = getVal('a20_where_ended');
        if (!(ended === 'nearest_station' || ended === 'other_departure_point')) return;

        var sel = document.querySelector('select[name="a20_arrival_station"]');
        var other = document.querySelector('input[name="a20_arrival_station_other"]');
        if (!sel) return;
        var st = (sel.value || '').trim();
        if (st === 'other') st = (other && other.value ? String(other.value).trim() : '');
        if (!st || st === 'unknown') return;

        originEl.value = st;
        var o = encodeURIComponent((originEl.value || '').trim());
        var d = encodeURIComponent((destEl.value || '').trim());
        open.href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
    }
    function handleResets(target) {
        var name = target.name || '';
        if (name === 'is_stranded_trin5') {
            var v0 = target.value || '';
            if (v0 !== 'yes') {
                var hadTrackState = !!getVal('blocked_train_alt_transport') || !!getVal('blocked_no_transport_action') || !!getVal('assistance_alt_transport_type');
                clearFields([
                    'blocked_train_alt_transport','assistance_alt_transport_type',
                    'blocked_no_transport_action',
                    'blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                ]);
                if (hadTrackState) {
                    clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
                }
            }
            return;
        }
        if (name === 'blocked_train_alt_transport') {
            var v2 = target.value || '';
            clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
            if (v2 === 'yes') {
                clearFields(['blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            } else if (v2 === 'no') {
                clearFields(['assistance_alt_transport_type','a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
            } else {
                clearFields([
                    'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
                    'assistance_alt_transport_type','a20_where_ended',
                    'a20_arrival_station','a20_arrival_station_other'
                ]);
            }
            return;
        }
        if (name === 'blocked_no_transport_action') {
            clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
            if ((target.value || '') !== 'self_arranged') {
                clearFields(['blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt']);
            }
            return;
        }
        if (name === 'a20_where_ended') {
            var v4 = target.value || '';
            if (!(v4 === 'nearest_station' || v4 === 'other_departure_point')) {
                clearFields(['a20_arrival_station','a20_arrival_station_other']);
            }
            return;
        }
        if (name === 'a20_arrival_station') {
            if ((target.value || '') !== 'other') {
                clearFields(['a20_arrival_station_other']);
            }
            return;
        }
    }
    function mapsInit(){
        var panel = document.getElementById('mapsPanelTrin5');
        // There is both a hidden input (value=0) and a checkbox (value=1).
        // Bind UI behavior to the checkbox, not the hidden field.
        var cb = document.querySelector('input[type="checkbox"][name="maps_opt_in_trin5"]');
        var originEl = document.getElementById('mapsOriginTrin5');
        var destEl = document.getElementById('mapsDestTrin5');
        var btn = document.getElementById('mapsFetchTrin5');
        var open = document.getElementById('mapsOpenTrin5');
        var out = document.getElementById('mapsRoutesTrin5');
        var status = document.getElementById('mapsStatusTrin5');
        if (!panel || !cb || !originEl || !destEl || !btn || !open || !out) { return; }

        function getOrigin(){
            return (originEl.value || '').trim();
        }
        function getDest(){
            return (destEl.value || '').trim();
        }
        function setOpenLink(){
            var o = encodeURIComponent(getOrigin());
            var d = encodeURIComponent(getDest());
            var href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
            open.href = href;
        }
        function updatePanel(){
            panel.classList.toggle('hidden', !cb.checked);
            panel.hidden = !cb.checked;
            setOpenLink();
        }
        function renderRoutes(payload){
            out.innerHTML = '';
            if (!payload || !payload.ok) {
                var msg = (payload && payload.error) ? payload.error : 'Kunne ikke hente ruter.';
                out.innerHTML = '<div class="small muted">' + ('' + msg).replace(/</g,'&lt;') + '</div>';
                return;
            }
            var routes = payload.routes || [];
            if (!routes.length) {
                out.innerHTML = '<div class="small muted">Ingen forslag fundet.</div>';
                return;
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
            function hhmm(ts){
                if (!ts) return '';
                var m = /T(\\d{2}:\\d{2})/.exec(ts.toString());
                return m ? m[1] : '';
            }
            function segText(s){
                s = s || {};
                var mode = vehicleLabel(s.vehicle);
                var line = (s.line || '').toString().trim();
                var from = (s.from || '').toString().trim();
                var to = (s.to || '').toString().trim();
                var dep = hhmm(s.dep_time);
                var arr = hhmm(s.arr_time);
                var head = (mode + (line ? (' ' + line) : '')).trim();
                var parts = [];
                if (from || to) parts.push((from || '?') + ' -> ' + (to || '?'));
                if (dep || arr) parts.push((dep || '') + (arr ? ('-' + arr) : ''));
                return head + (parts.length ? (': ' + parts.join(' / ')) : '');
            }

            routes.forEach(function(r){
                var box = document.createElement('div');
                box.style.cssText = 'padding:8px;border:1px solid #ddd;border-radius:6px;background:#fff;margin-top:8px;';
                var title = document.createElement('div');
                title.style.fontWeight = '600';
                title.textContent = r.summary || '';
                box.appendChild(title);

                var segs = r.segments || [];
                if (segs && segs.length) {
                    var ul = document.createElement('ul');
                    ul.className = 'small muted mt4';
                    segs.forEach(function(s){
                        var li = document.createElement('li');
                        li.textContent = segText(s);
                        ul.appendChild(li);
                    });
                    box.appendChild(ul);
                }

                out.appendChild(box);
            });
        }

        cb.addEventListener('change', function(){
            // If Maps is enabled after the user already answered "Hvor endte du?", sync origin immediately.
            if (cb.checked) { try { syncMapsOriginFromResolution(); } catch(e) {} }
            updatePanel();
        });
        originEl.dataset.manual = originEl.dataset.manual || '0';
        originEl.addEventListener('input', function(){
            originEl.dataset.manual = '1';
            setOpenLink();
        });

        btn.addEventListener('click', async function(){
            var origin = getOrigin();
            var dest = getDest();
            setOpenLink();
            if (!origin || !dest) {
                if (status) status.textContent = 'Vaelg start og destination foerst.';
                return;
            }
            if (status) status.textContent = 'Henter...';
            try {
                var csrf = (document.querySelector('input[name="_csrfToken"]') || {}).value || '';
                var res = await fetch(panel.getAttribute('data-endpoint'), {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded', ...(csrf ? {'X-CSRF-Token': csrf} : {})},
                    body: new URLSearchParams({
                        context: 'trin5',
                        maps_opt_in_trin5: '1',
                        origin: origin,
                        destination: dest
                    })
                });
                var j = await res.json();
                renderRoutes(j);
                if (status) status.textContent = j && j.ok ? 'OK' : 'Fejl';
            } catch(e) {
                if (status) status.textContent = 'Fejl';
                renderRoutes({ok:false, error: (e && e.message) ? e.message : 'Fejl'});
            }
        });

        updatePanel();
        setOpenLink();
        try { syncMapsOriginFromResolution(); } catch(e) {}
    }
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
	                function buildUrl(country){
	                    var u = new URL(stationsSearchUrl, window.location.origin);
	                    u.searchParams.set('q', q);
	                    if (country) u.searchParams.set('country', country);
	                    u.searchParams.set('limit', '10');
	                    return u;
	                }

	                if (ctrl) { try { ctrl.abort(); } catch(e) {} }
	                ctrl = new AbortController();
	                try {
	                    var res = await fetch(buildUrl(cc).toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
	                    if (!res.ok) { hide(); return; }
	                    var js = await res.json();
	                    var stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
	                    if ((!stations || stations.length === 0) && cc) {
	                        res = await fetch(buildUrl('').toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
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
        updateReveal();
        initStationAutocomplete();
        mapsInit();
        updateResolutionVisibility();
        try { syncMapsOriginFromResolution(); } catch(e) {}
    });
    document.addEventListener('change', function(e){
        if (!e.target || !e.target.name) return;
        handleResets(e.target);
        updateReveal();
        updateResolutionVisibility();
        syncMapsOriginFromResolution();
    });
})();
</script>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('Tilbage', ['action' => 'incident'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsaet', ['id' => 'choicesSubmitBtn', 'class' => 'button', 'type' => 'submit', 'aria-label' => 'Fortsaet til naeste trin', 'formnovalidate' => true]) ?>
    <?= $this->Html->link('Spring over', ['controller' => 'Flow', 'action' => 'remedies'], ['class' => 'button', 'style' => 'background:#f5f5f5; color:#333;', 'title' => 'Gaa til naeste trin uden at gemme aendringer']) ?>
    <input type="hidden" name="_choices_submitted" value="1" />
</div>

</fieldset>
<?= $this->Form->end() ?>
</div>
