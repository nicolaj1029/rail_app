<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];
$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);

$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$isBeforeStart = ($travelState === 'before_start');

$title = $isOngoing
    ? 'TRIN 4 - Transport fra station (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 4 - Transport fra station (afsluttet rejse)' : ($isBeforeStart ? 'TRIN 4 - Transport fra station (rejsen starter senere)' : 'TRIN 4 - Transport fra station (Art.20(3))'));

$v = fn(string $k): string => (string)($form[$k] ?? '');

// Station options (from extracted segments + form fallback).
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
$stationsSearchUrl = $this->Url->build('/api/stations/search');
$stationCountryDefault = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
try {
    if ($stationCountryDefault !== '' && strlen($stationCountryDefault) !== 2) {
        $cc = (new \App\Service\CountryNormalizer())->toIso2($stationCountryDefault);
        if ($cc !== '') { $stationCountryDefault = $cc; }
    }
} catch (\Throwable $e) { /* ignore */ }

$a20StationStranded = strtolower(trim((string)($form['a20_station_stranded'] ?? 'no')));
if ($a20StationStranded !== 'yes' && $a20StationStranded !== 'no') { $a20StationStranded = 'no'; }

$scs = $v('stranded_current_station');
$scsOther = $v('stranded_current_station_other');
$sol = $v('a20_3_solution_offered');
$stype = $v('a20_3_solution_type');
$sp = $v('a20_3_self_paid');
$dir = $v('a20_3_self_paid_direction');
$sat = $v('a20_3_self_arranged_type');
$toEnd = $v('a20_where_ended');
$arrEnd = $v('a20_arrival_station');
$arrEndOther = $v('a20_arrival_station_other');

$art20StationOff = ($articles['art20_3'] ?? true) === false;
$isPreview = !empty($flowPreview);
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .hidden { display:none; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .flow-wrapper { max-width: 1080px; margin: 0 auto; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
  select, input[type="text"], input[type="number"] { max-width: 520px; width: 100%; }
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
  .station-suggest button:focus { outline: none; background: #f1f3f5; color: #111 !important; }
</style>

<div class="flow-wrapper">
  <h1><?= h($title) ?></h1>
  <div class="card mt12" data-art="20(3)">
    <strong>Transport fra station (Art.20(3))</strong>
    <p class="small muted">Udfyld kun hvis du var strandet paa en station uden videre tog. EU/national gating vurderes i naeste trin.</p>

    <?php if ($art20StationOff): ?>
      <div class="mt8 small muted"><strong>Bemaerk:</strong> Art.20(3) ser ud til at vaere deaktiveret af undtagelser for denne rejse/scope i app'en. Udfyld kun hvis du vil dokumentere fakta alligevel.</div>
    <?php endif; ?>

    <?= $this->element('flow_locked_notice') ?>
    <?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'station'], 'type' => 'file', 'novalidate' => true]) ?>
    <fieldset <?= $isPreview ? 'disabled' : '' ?>>

    <div class="mt8">
      <div>Var du strandet paa en station uden videre tog?</div>
      <label><input type="radio" name="a20_station_stranded" value="yes" <?= $a20StationStranded==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="a20_station_stranded" value="no" <?= $a20StationStranded==='no'?'checked':'' ?> /> Nej</label>
    </div>

    <div id="a20StationWrap" class="mt8" data-show-if="a20_station_stranded:yes">
      <input type="hidden" name="stranded_location" value="station" />

      <div class="mt8">
        <div>Hvilken station er du strandet paa?</div>
        <div class="grid-2 mt4">
          <label class="station-autocomplete" data-station-select="stranded_current_station" data-station-other="stranded_current_station_other">Station
            <select name="stranded_current_station">
              <option value="">Vaelg</option>
              <?php foreach ($stationOptions as $st): ?>
                <option value="<?= h($st) ?>" <?= $scs===$st?'selected':'' ?>><?= h($st) ?></option>
              <?php endforeach; ?>
              <option value="unknown" <?= $scs==='unknown'?'selected':'' ?>>Ved ikke</option>
              <option value="other" <?= $scs==='other'?'selected':'' ?>>Anden station</option>
            </select>
            <input type="text" name="stranded_current_station_other" value="<?= h($scsOther) ?>" placeholder="Anden station" data-show-if="stranded_current_station:other" />
            <input type="hidden" name="stranded_current_station_other_osm_id" value="<?= h($v('stranded_current_station_other_osm_id')) ?>" />
            <input type="hidden" name="stranded_current_station_other_lat" value="<?= h($v('stranded_current_station_other_lat')) ?>" />
            <input type="hidden" name="stranded_current_station_other_lon" value="<?= h($v('stranded_current_station_other_lon')) ?>" />
            <input type="hidden" name="stranded_current_station_other_country" value="<?= h($v('stranded_current_station_other_country')) ?>" />
            <input type="hidden" name="stranded_current_station_other_type" value="<?= h($v('stranded_current_station_other_type')) ?>" />
            <input type="hidden" name="stranded_current_station_other_source" value="<?= h($v('stranded_current_station_other_source')) ?>" />
            <div class="station-suggest" data-for="stranded_current_station_other" style="display:none;"></div>
          </label>
        </div>
      </div>

      <div class="mt12">
        <div>Fik du tilbudt en loesning for at komme videre?</div>
        <label><input type="radio" name="a20_3_solution_offered" value="yes" <?= $sol==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="a20_3_solution_offered" value="no" <?= $sol==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <div class="mt8" data-show-if="a20_3_solution_offered:yes">
        <div class="grid-2">
          <label>Hvilken loesning blev tilbudt?
            <select name="a20_3_solution_type">
              <option value="">Vaelg</option>
              <option value="rebooking_rail" <?= $stype==='rebooking_rail'?'selected':'' ?>>Omlagt til andet tog/anden rute</option>
              <option value="bus" <?= $stype==='bus'?'selected':'' ?>>Erstatningsbus</option>
              <option value="taxi" <?= $stype==='taxi'?'selected':'' ?>>Taxi/minibus</option>
              <option value="wait_next" <?= $stype==='wait_next'?'selected':'' ?>>Bedt om at vente paa naeste afgang</option>
              <option value="hotel" <?= $stype==='hotel'?'selected':'' ?>>Hotel/indkvartering</option>
              <option value="refund" <?= $stype==='refund'?'selected':'' ?>>Refusion</option>
              <option value="other" <?= $stype==='other'?'selected':'' ?>>Andet</option>
            </select>
          </label>
        </div>
      </div>

      <div class="mt12" data-show-if="a20_3_solution_offered:no">
        <div class="small muted">Hvis du ikke fik en loesning, kan du angive om du selv maatte betale for transport og evt. udgifter.</div>
        <div class="mt8">
          <div>Maate du selv betale for transport?</div>
          <label><input type="radio" name="a20_3_self_paid" value="no" <?= $sp==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="a20_3_self_paid" value="yes" <?= $sp==='yes'?'checked':'' ?> /> Ja</label>
        </div>
      </div>

      <div class="mt8" data-show-if="a20_3_self_paid:yes">
        <div class="grid-3 mt8">
          <label>Transporttype
            <select name="a20_3_self_arranged_type">
              <option value="">Vaelg</option>
              <option value="rail" <?= $sat==='rail'?'selected':'' ?>>Andet tog</option>
              <option value="bus" <?= $sat==='bus'?'selected':'' ?>>Bus</option>
              <option value="taxi" <?= $sat==='taxi'?'selected':'' ?>>Taxi / minibus</option>
              <option value="rideshare" <?= $sat==='rideshare'?'selected':'' ?>>Samkoersel / rideshare</option>
              <option value="hotel" <?= $sat==='hotel'?'selected':'' ?>>Hotel / overnatning</option>
              <option value="other" <?= $sat==='other'?'selected':'' ?>>Andet</option>
            </select>
          </label>
          <label>Transporten var for at...
            <select name="a20_3_self_paid_direction">
              <option value="">Vaelg</option>
              <option value="continue" <?= $dir==='continue'?'selected':'' ?>>Komme videre mod destination</option>
              <option value="return" <?= $dir==='return'?'selected':'' ?>>Tage hjem/retur</option>
              <option value="hotel" <?= $dir==='hotel'?'selected':'' ?>>Komme til hotel/overnatning</option>
              <option value="other" <?= $dir==='other'?'selected':'' ?>>Andet</option>
            </select>
          </label>
          <label>Beloeb
            <input type="number" step="0.01" name="a20_3_self_paid_amount" value="<?= h($v('a20_3_self_paid_amount')) ?>" />
          </label>
          <label>Valuta (fx DKK/EUR)
            <input type="text" name="a20_3_self_paid_currency" value="<?= h($v('a20_3_self_paid_currency')) ?>" />
          </label>
          <label class="small">Kvittering
            <input type="file" name="a20_3_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
          </label>
        </div>
        <?php if ($f = $v('a20_3_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
      </div>

      <div id="stationResolutionWrap" class="mt12" style="display:none;">
        <div><strong>Hvor endte du?</strong></div>
        <div class="grid-2 mt8">
          <label>Slutpunkt
            <select name="a20_where_ended">
              <option value="">Vaelg</option>
              <option value="nearest_station" <?= $toEnd==='nearest_station'?'selected':'' ?>>Naermeste station</option>
              <option value="other_departure_point" <?= $toEnd==='other_departure_point'?'selected':'' ?>>Et andet afgangssted</option>
              <option value="final_destination" <?= $toEnd==='final_destination'?'selected':'' ?>>Mit endelige bestemmelsessted</option>
            </select>
            <div class="small muted mt4" data-show-if="a20_where_ended:nearest_station,other_departure_point">Angiv station, saa vi kan fortsaette rejsen i naeste trin.</div>
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

    <div class="mt12" style="display:flex; gap:8px; align-items:center;">
      <?= $this->Html->link('<- Tilbage', ['action' => 'journey'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
      <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
    </div>

    </fieldset>
    <?= $this->Form->end() ?>
  </div>
</div>

<script>
function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if'); if (!spec) return;
    var parts = spec.split(':'); if (parts.length !== 2) return;
    var name = parts[0]; var valid = parts[1].split(',');
    var val = '';
    var checked = document.querySelector('input[name=\"' + name + '\"]:checked');
    if (checked) { val = checked.value || ''; }
    else {
      var sel = document.querySelector('select[name=\"' + name + '\"]');
      if (sel) { val = sel.value || ''; }
    }
    var show = val && valid.includes(val);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
}
function getVal(name) {
  var checked = document.querySelector('input[name=\"' + name + '\"]:checked');
  if (checked) { return checked.value || ''; }
  var sel = document.querySelector('select[name=\"' + name + '\"]');
  if (sel) { return sel.value || ''; }
  var inp = document.querySelector('input[name=\"' + name + '\"]');
  if (inp && inp.type !== 'radio' && inp.type !== 'checkbox') { return inp.value || ''; }
  return '';
}
function setBlockVisible(el, show) {
  if (!el) return;
  el.style.display = show ? 'block' : 'none';
  el.hidden = !show;
}

function updateStationResolutionVisibility() {
  var wrap = document.getElementById('stationResolutionWrap');
  if (!wrap) return;

  var stranded = getVal('a20_station_stranded');
  var sol = getVal('a20_3_solution_offered');
  var selfPaid = getVal('a20_3_self_paid');

  var shouldShow = false;
  if (stranded === 'yes') {
    if (sol === 'yes') shouldShow = true;
    if (sol === 'no' && (selfPaid === 'yes' || selfPaid === 'no')) shouldShow = true;
  }
  setBlockVisible(wrap, shouldShow);
}

function initStationAutocomplete(){
  var stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  var stationCountryDefault = <?= json_encode((string)$stationCountryDefault, JSON_UNESCAPED_SLASHES) ?>;
  if (!stationsSearchUrl) return;

  document.querySelectorAll('.station-autocomplete').forEach(function(lbl){
    var selectName = lbl.getAttribute('data-station-select') || '';
    var otherName = lbl.getAttribute('data-station-other') || '';
    if (!selectName || !otherName) return;
    var sel = lbl.querySelector('select[name=\"' + selectName + '\"]');
    var input = lbl.querySelector('input[name=\"' + otherName + '\"]');
    var box = lbl.querySelector('.station-suggest[data-for=\"' + otherName + '\"]');
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
      return lbl.querySelector('input[name=\"' + otherName + '_' + suffix + '\"]');
    }
    function clearMeta(){
      ['osm_id','lat','lon','country','type','source'].forEach(function(s){
        var el = metaInput(s);
        if (el) el.value = '';
      });
    }
    function setMeta(st){
      if (!st) { clearMeta(); return; }
      var v = function(k){ return (st && st[k] !== undefined && st[k] !== null) ? String(st[k]) : ''; };
      var m = { osm_id:v('osm_id'), lat:v('lat'), lon:v('lon'), country:v('country'), type:v('type'), source:v('source') };
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
        // ignore
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
    sel.addEventListener('change', function(){ if ((sel.value || '') !== 'other') { hide(); } });
  });
}

document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  updateReveal();
  updateStationResolutionVisibility();
});
document.addEventListener('DOMContentLoaded', function(){
  updateReveal();
  initStationAutocomplete();
  updateStationResolutionVisibility();
});
</script>
