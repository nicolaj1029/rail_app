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

$mctEvalRaw = (array)($meta['_mct_eval'] ?? []);
$normStation = function($s){ return trim(mb_strtolower((string)$s, 'UTF-8')); };
$mctByStation = [];
foreach ($mctEvalRaw as $ev) {
    $mctByStation[$normStation($ev['station'] ?? '')] = $ev;
}
$toMin = function(string $t){ if(!preg_match('/^(\\d{1,2}):(\\d{2})$/', trim($t), $m)) return null; return (int)$m[1]*60 + (int)$m[2]; };

// Fallback build of journey rows from auto segments
if (empty($journeyRowsInline)) {
    $segSrc = (array)($meta['_segments_auto'] ?? []);
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

// Fallback MC choices: use auto segments (with MCT info) or simple choices from form
if (empty($mcChoicesInline)) {
    $segAuto = (array)($meta['_segments_auto'] ?? []);
    if (!empty($segAuto)) {
        $last = count($segAuto) - 1;
        for ($i = 0; $i < $last; $i++) {
            $seg = (array)$segAuto[$i];
            $next = (array)($segAuto[$i+1] ?? []);
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
    foreach ((array)($meta['_segments_auto'] ?? []) as $s) {
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
?>
<div style="grid-column: 1 / span 2;">
  <label>3.5. Missed connection (kun station)
    <input id="mcField" type="text" name="missed_connection_station" value="<?= h($meta['_auto']['missed_connection_station']['value'] ?? ($form['missed_connection_station'] ?? '')) ?>" placeholder="Skriv skiftestation (hvis relevant)" />
  </label>
  <?php if (!empty($mcChoicesInline)): ?>
    <div class="small muted" style="margin-top:6px;">Vælg hvor skiftet blev misset (enkeltvalg):</div>
    <div class="small" style="margin-top:4px; display:flex; flex-direction:column; gap:6px;">
      <?php foreach ($mcChoicesInline as $opt): $stationOpt = (string)($opt['station'] ?? ''); $labelOpt = (string)($opt['label'] ?? $stationOpt); $checked = (string)$currentMissInline === (string)$stationOpt; ?>
        <label class="mr8"><input type="radio" name="missed_connection_pick" value="<?= h($stationOpt) ?>" <?= $checked?'checked':'' ?> data-mc-single data-station="<?= h($stationOpt) ?>" /> <?= h($labelOpt) ?></label>
      <?php endforeach; ?>
    </div>
    <script>
      (function(){
        var radios = document.querySelectorAll('input[type="radio"][data-mc-single]');
        var field = document.getElementById('mcField');
        radios.forEach(function(r){ r.dataset.selected = r.checked ? '1' : '0'; });
        radios.forEach(function(r){
          r.addEventListener('click', function(ev){
            if (r.dataset.selected === '1') {
              r.checked = false;
              r.dataset.selected = '0';
              if (field) { field.value = ''; }
              ev.preventDefault();
              return false;
            }
            radios.forEach(function(o){ o.dataset.selected = '0'; });
            r.dataset.selected = '1';
            if (field) { field.value = (r.getAttribute('data-station') || r.value); }
          });
          r.addEventListener('change', function(){ if (r.checked && field) { field.value = (r.getAttribute('data-station') || r.value); } });
        });
      })();
    </script>
  <?php else: ?>
    <div class="small muted" style="margin-top:6px;">Ingen skift fundet automatisk. Hvis du missede en forbindelse, skriv stationen manuelt ovenfor.</div>
  <?php endif; ?>
</div>

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
                <option value="1st_class" <?= $deliveredVal==='1st_class'?'selected':'' ?>>1. klasse</option>
                <option value="2nd_class" <?= $deliveredVal==='2nd_class'?'selected':'' ?>>2. klasse</option>
                <option value="seat_reserved" <?= $deliveredVal==='seat_reserved'?'selected':'' ?>>Reserveret sæde</option>
                <option value="couchette" <?= $deliveredVal==='couchette'?'selected':'' ?>>Ligge (couchette)</option>
                <option value="sleeper" <?= $deliveredVal==='sleeper'?'selected':'' ?>>Sovevogn</option>
                <option value="free_seat" <?= $deliveredVal==='free_seat'?'selected':'' ?>>Fri plads / ingen reservation</option>
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
