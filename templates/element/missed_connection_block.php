<?php
/**
 * Missed connection selector (station) + journey table.
 * Expects: $meta, $form. Optional: $journeyRowsInline, $mcChoicesInline, $changeBullets.
 */
$meta = $meta ?? [];
$form = $form ?? [];
$journeyRowsInline = $journeyRowsInline ?? [];
$mcChoicesInline = $mcChoicesInline ?? [];
$changeBullets = $changeBullets ?? [];
// Use a literal path to avoid the URL builder selecting the /api/demo/v2 scope fallback route.
$stationsSearchUrl = $stationsSearchUrl ?? $this->Url->build('/api/stations/search');

$mctEvalRaw = (array)($meta['_mct_eval'] ?? []);
$normStation = function($s){ return trim(mb_strtolower((string)$s, 'UTF-8')); };
$mctByStation = [];
foreach ($mctEvalRaw as $ev) {
    $mctByStation[$normStation($ev['station'] ?? '')] = $ev;
}
$toMin = function(string $t){ if(!preg_match('/^(\\d{1,2}):(\\d{2})$/', trim($t), $m)) return null; return (int)$m[1]*60 + (int)$m[2]; };
$segLlm = (array)($meta['_segments_llm_suggest'] ?? []);
$segAuto = (array)($meta['_segments_auto'] ?? []);
$normChain = function(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    return trim($s);
};
$isChainable = function($segs) use ($normChain): bool {
    if (!is_array($segs) || count($segs) < 2) { return false; }
    $prevTo = '';
    foreach ($segs as $idx => $seg) {
        $from = trim((string)($seg['from'] ?? ''));
        $to = trim((string)($seg['to'] ?? ''));
        if ($from === '' || $to === '' || $from === $to) { return false; }
        if ($idx > 0 && $normChain($from) !== $normChain($prevTo)) { return false; }
        $prevTo = $to;
    }
    return true;
};
$segSrc = $segLlm;
if (empty($segLlm) || !$isChainable($segLlm)) {
    $segSrc = $segAuto;
    if (empty($segAuto) && !empty($segLlm)) { $segSrc = $segLlm; }
}

// Fallback build of journey rows from detected segments
if (empty($journeyRowsInline)) {
    foreach ($segSrc as $s) {
        $from = trim((string)($s['from'] ?? ''));
        $to = trim((string)($s['to'] ?? ''));
        $journeyRowsInline[] = [
            'leg' => $from . ' -> ' . $to,
            'dep' => (string)($s['schedDep'] ?? ''),
            'arr' => (string)($s['schedArr'] ?? ''),
            'train' => (string)($s['train'] ?? ($s['trainNo'] ?? '')),
            'change' => (string)($s['change'] ?? ''),
        ];
    }
}

// Fallback MC choices: prefer LLM segments, then auto segments, then simple choices from form
if (empty($mcChoicesInline)) {
    if (!empty($segSrc)) {
        $last = count($segSrc) - 1;
        for ($i = 0; $i < $last; $i++) {
            $seg = (array)$segSrc[$i];
            $next = (array)($segSrc[$i+1] ?? []);
            $toName = trim((string)($seg['to'] ?? ''));
            if ($toName === '') { continue; }
            $arr = trim((string)($seg['schedArr'] ?? ''));
            $nextDep = trim((string)($next['schedDep'] ?? ''));
            $lay = null; $m1 = $toMin($arr); $m2 = $toMin($nextDep); if ($m1 !== null && $m2 !== null) { $lay = $m2 - $m1; if ($lay < 0) { $lay += 24*60; } }
            $label = $toName;
            if ($arr || $nextDep) {
                $label .= ' (ank. ' . ($arr ?: '-') . ' • afg. ' . ($nextDep ?: '-') . (($lay !== null && $lay >= 0 && $lay <= 360) ? (', ophold ' . $lay . ' min') : '') . ')';
            }
            // Append MCT judgement if available
            $ev = $mctByStation[$normStation($toName)] ?? null;
            if (is_array($ev)) {
                $ok = !empty($ev['realistic']); $thr = (int)($ev['threshold'] ?? 0);
                $label .= $ok ? ' [MCT ok = ' . $thr . 'm]' : ' [MCT kort < ' . $thr . 'm]';
            }
            $mcChoicesInline[] = ['station' => $toName, 'label' => $label];
        }
    } elseif (!empty($form['_miss_conn_choices'])) {
        foreach ((array)$form['_miss_conn_choices'] as $st => $lbl) {
            $mcChoicesInline[] = ['station' => (string)$st, 'label' => (string)$lbl];
        }
    }
}

// Fallback change bullets
if (empty($changeBullets)) {
    foreach ($segSrc as $s) {
        $chg = trim((string)($s['change'] ?? ''));
        if ($chg === '') continue;
        $arr = (string)($s['schedArr'] ?? '');
        $dep = (string)($s['schedDepNext'] ?? ($s['nextDep'] ?? ''));
        $lay = $s['layoverMin'] ?? null;
        $bullet = 'Skift i ' . $chg;
        if ($arr || $dep) { $bullet .= ' (ankomst ' . ($arr ?: '-') . ', afgang ' . ($dep ?: '-') . ')'; }
        if ($lay !== null && $lay !== '') { $bullet .= ', opholdstid: ' . $lay . ' minutter'; }
        $ev = $mctByStation[$normStation($chg)] ?? null;
        if (is_array($ev)) { $bullet .= !empty($ev['realistic']) ? ' - MCT: OK' : ' - MCT: for kort'; }
        $changeBullets[] = $bullet;
    }
}

$currentMissInline = (string)($form['missed_connection_station'] ?? '');
$mcCountryHint = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
?>
<div style="grid-column: 1 / span 2;">
  <label>3.5. Missed connection (kun station)
    <input id="mcField" type="text" name="missed_connection_station" value="<?= h($meta['_auto']['missed_connection_station']['value'] ?? ($form['missed_connection_station'] ?? '')) ?>" placeholder="Skriv skiftestation (hvis relevant)" />
  </label>
  <input type="hidden" name="missed_connection_station_osm_id" id="mcField_osm_id" value="<?= h((string)($form['missed_connection_station_osm_id'] ?? '')) ?>" />
  <input type="hidden" name="missed_connection_station_lat" id="mcField_lat" value="<?= h((string)($form['missed_connection_station_lat'] ?? '')) ?>" />
  <input type="hidden" name="missed_connection_station_lon" id="mcField_lon" value="<?= h((string)($form['missed_connection_station_lon'] ?? '')) ?>" />
  <input type="hidden" name="missed_connection_station_country" id="mcField_country" value="<?= h((string)($form['missed_connection_station_country'] ?? '')) ?>" />
  <input type="hidden" name="missed_connection_station_type" id="mcField_type" value="<?= h((string)($form['missed_connection_station_type'] ?? '')) ?>" />
  <input type="hidden" name="missed_connection_station_source" id="mcField_source" value="<?= h((string)($form['missed_connection_station_source'] ?? '')) ?>" />

  <style>
    /* Offline station autocomplete for ticketless fallback */
    #mcSuggest {
      display:none;
      margin-top:4px;
      border:1px solid #ddd;
      border-radius:6px;
      background:#fff;
      overflow:auto;
      max-height:220px;
      box-shadow:0 6px 18px rgba(0,0,0,.08);
    }
    #mcSuggest button {
      display:block;
      width:100%;
      text-align:left;
      padding:8px 10px;
      border:0;
      background:transparent;
      cursor:pointer;
      color:#111 !important;
      font-size:13px;
      line-height:1.25;
    }
    #mcSuggest button:hover { background:#f6f8fb; }
    #mcSuggest .muted { color:#666 !important; font-size:11px; }
  </style>
  <div id="mcSuggest" class="station-suggest" aria-label="Stationsforslag"></div>
  <?php if (!empty($mcChoicesInline)): ?>
    <div class="small muted" style="margin-top:6px;">Vælg hvor skiftet blev misset (enkeltvalg):</div>
    <div class="small" style="margin-top:4px; display:flex; flex-direction:column; gap:6px;">
      <?php foreach ($mcChoicesInline as $opt): $stationOpt = (string)($opt['station'] ?? ''); $labelOpt = (string)($opt['label'] ?? $stationOpt); $checked = (string)$currentMissInline === (string)$stationOpt; ?>
        <label class="mr8"><input type="radio" name="missed_connection_pick" value="<?= h($stationOpt) ?>" <?= $checked?'checked':'' ?> data-mc-single data-station="<?= h($stationOpt) ?>" /> <?= h($labelOpt) ?></label>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="small muted" style="margin-top:6px;">Ingen skift fundet automatisk. Hvis du missede en forbindelse, skriv stationen manuelt ovenfor.</div>
  <?php endif; ?>
</div>

<script>
(function(){
  var field = document.getElementById('mcField');
  var box = document.getElementById('mcSuggest');
  var stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  var countryHint = <?= json_encode((string)$mcCountryHint, JSON_UNESCAPED_UNICODE) ?>;

  var hid = {
    osm_id: document.getElementById('mcField_osm_id'),
    lat: document.getElementById('mcField_lat'),
    lon: document.getElementById('mcField_lon'),
    country: document.getElementById('mcField_country'),
    type: document.getElementById('mcField_type'),
    source: document.getElementById('mcField_source')
  };

  function clearMeta(){
    if (hid.osm_id) hid.osm_id.value = '';
    if (hid.lat) hid.lat.value = '';
    if (hid.lon) hid.lon.value = '';
    if (hid.country) hid.country.value = '';
    if (hid.type) hid.type.value = '';
    if (hid.source) hid.source.value = '';
  }

  function setMeta(st){
    if (!st) { clearMeta(); return; }
    if (hid.osm_id) hid.osm_id.value = (st.osm_id !== undefined && st.osm_id !== null) ? String(st.osm_id) : '';
    if (hid.lat) hid.lat.value = (st.lat !== undefined && st.lat !== null) ? String(st.lat) : '';
    if (hid.lon) hid.lon.value = (st.lon !== undefined && st.lon !== null) ? String(st.lon) : '';
    if (hid.country) hid.country.value = (st.country !== undefined && st.country !== null) ? String(st.country) : '';
    if (hid.type) hid.type.value = (st.type !== undefined && st.type !== null) ? String(st.type) : '';
    if (hid.source) hid.source.value = (st.source !== undefined && st.source !== null) ? String(st.source) : '';
  }

  function hide(){
    if (!box) return;
    box.style.display = 'none';
    box.innerHTML = '';
  }

  function niceType(t){
    var s = (t || '').toString().toLowerCase();
    if (s === 'station') return 'Station';
    if (s === 'halt') return 'Stopested';
    return s;
  }

  function clearMcPickRadios(){
    var radios = document.querySelectorAll('input[type=\"radio\"][data-mc-single]');
    radios.forEach(function(r){
      r.checked = false;
      r.dataset.selected = '0';
    });
  }

  function render(stations){
    if (!box) return;
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
        if (field && nm) field.value = nm;
        setMeta(st);
        clearMcPickRadios(); // avoid mismatch between radio-choice and autocomplete
        hide();
      });
      box.appendChild(btn);
    });
    box.style.display = 'block';
  }

  var timer = null;
  var ctrl = null;

  async function fetchStations(){
    if (!field || !box || !stationsSearchUrl) return;
    var q = String(field.value || '').trim();
    if (q.length < 2) { hide(); return; }
    var cc = String(countryHint || '').trim().toUpperCase();
    // If no country is available, avoid a full EU scan until the user has typed more.
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
      var stations = (js && js.data && Array.isArray(js.data.stations)) ? js.data.stations : [];
      if ((!stations || stations.length === 0) && cc) {
        res = await fetch(buildUrl(''), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
        if (res.ok) {
          js = await res.json();
          stations = (js && js.data && Array.isArray(js.data.stations)) ? js.data.stations : [];
        }
      }
      render(stations);
    } catch(e) {
      // ignore abort/network
    }
  }

  if (field && box && stationsSearchUrl) {
    field.addEventListener('input', function(){
      clearMeta(); // user is typing; metadata no longer trusted
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(fetchStations, 200);
    }, { passive:true });
    field.addEventListener('focus', function(){
      if (box.innerHTML.trim() !== '') box.style.display = 'block';
    }, { passive:true });
    field.addEventListener('blur', function(){ window.setTimeout(hide, 180); }, { passive:true });
    // Prevent blur while clicking suggestions
    box.addEventListener('mousedown', function(e){ e.preventDefault(); });
  }

  // Existing "single choice" radios should drive the field value, but should not leave stale station metadata.
  var radios = document.querySelectorAll('input[type=\"radio\"][data-mc-single]');
  radios.forEach(function(r){ r.dataset.selected = r.checked ? '1' : '0'; });
  radios.forEach(function(r){
    r.addEventListener('click', function(ev){
      if (r.dataset.selected === '1') {
        r.checked = false;
        r.dataset.selected = '0';
        if (field) { field.value = ''; }
        clearMeta();
        hide();
        ev.preventDefault();
        return false;
      }
      radios.forEach(function(o){ o.dataset.selected = '0'; });
      r.dataset.selected = '1';
      if (field) { field.value = (r.getAttribute('data-station') || r.value); }
      clearMeta(); // value came from derived label; not a DB selection
      hide();
    });
    r.addEventListener('change', function(){
      if (r.checked && field) { field.value = (r.getAttribute('data-station') || r.value); }
      if (r.checked) { clearMeta(); hide(); }
    });
  });
})();
</script>

<?php if (!empty($journeyRowsInline)): ?>
  <div class="small" style="margin-top:10px;"><strong>Rejseplan (aflæst fra billetten)</strong></div>
  <div class="small" style="overflow:auto;">
    <style>
      /* Skjul leveret/nedgraderet kolonner i MC-tabellen */
      #mcJourneyTable th:nth-child(6),
      #mcJourneyTable td:nth-child(6),
      #mcJourneyTable th:nth-child(7),
      #mcJourneyTable td:nth-child(7) { display:none; }
    </style>
    <table id="mcJourneyTable" class="fe-table">
      <thead>
        <tr>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Strækning</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Tog</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Skift</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Misset?</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($journeyRowsInline as $idx => $r): ?>
          <?php
            $deliveredVal = (string)($form['leg_class_delivered'][$idx] ?? ($meta['_auto']['class_delivered'][$idx]['value'] ?? ''));
            $downgVal = isset($form['leg_downgraded'][$idx]) && $form['leg_downgraded'][$idx] === '1';
          ?>
          <tr>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['leg']) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['dep']) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['arr']) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['train']) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['change']) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
              <select name="leg_class_delivered[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                <option value=""><?= __('Vælg leveret niveau') ?></option>
                <option value="1st" <?= $deliveredVal==='1st'?'selected':'' ?>>1. klasse</option>
                <option value="2nd" <?= $deliveredVal==='2nd'?'selected':'' ?>>2. klasse</option>
                <option value="couchette" <?= $deliveredVal==='couchette'?'selected':'' ?>>Liggevogn</option>
                <option value="sleeper" <?= $deliveredVal==='sleeper'?'selected':'' ?>>Sovevogn</option>
              </select>
            </td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
              <label class="small">
                <input type="checkbox" name="leg_downgraded[<?= (int)$idx ?>]" value="1" <?= $downgVal?'checked':'' ?> />
                <?= __('Nedgraderet') ?>
              </label>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($changeBullets)): ?>
    <div class="small" style="margin-top:8px;">
      <div><strong>Der er <?= count($changeBullets) ?> skift<?= count($changeBullets)===1?'':'e' ?>:</strong></div>
      <ul style="margin:6px 0 0 16px;">
        <?php foreach ($changeBullets as $b): ?>
          <li><?= h($b) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if (empty($mcChoicesInline)): ?>
    <div class="small muted" style="margin-top:8px;">Ingen skift fundet – punkt 3.5 vises kun, når der er et skift i rejsen.</div>
  <?php endif; ?>
<?php endif; ?>
