<?php
/**
 * Shared downgrade table (Art. 9(1) / Art. 18 stk. 3).
 * Expects: $journeyRowsDowng (array of legs), $classOptions (array), $reservationOptions (array), $form (array), $meta (array).
 */
$journeyRowsDowng = $journeyRowsDowng ?? [];
$classOptions = $classOptions ?? [];
$reservationOptions = $reservationOptions ?? [];
$form = $form ?? [];
$meta = $meta ?? [];
$isAir = !empty($isAir);
$isFerry = !empty($isFerry);
$missedStation = $missedStation ?? '';
$affectedLegsAuto = $affectedLegsAuto ?? [];
$affectedSet = [];
if (is_array($affectedLegsAuto)) {
    foreach ($affectedLegsAuto as $ai) {
        if (is_numeric($ai)) { $affectedSet[(int)$ai] = true; }
    }
}

if (empty($classOptions)) {
    if ($isAir) {
        $classOptions = [
            'economy' => 'Economy',
            'premium_economy' => 'Premium Economy',
            'business' => 'Business',
            'first' => 'First',
        ];
    } elseif ($isFerry) {
        $classOptions = [
            'sleeper' => 'Kahyt / privat overnatning',
            'couchette' => 'Liggeplads / hvileplads',
            '1st' => 'Prioriteret / bedre plads',
            '2nd' => 'Standardplads / basisservice',
        ];
    } else {
        $classOptions = [
            'sleeper' => 'Sovevogn',
            'couchette' => 'Liggevogn',
            '1st' => '1. klasse',
            '2nd' => '2. klasse',
        ];
    }
}
if (empty($reservationOptions)) {
    $reservationOptions = [
        'reserved' => 'Reserveret plads',
        'free_seat' => 'Ingen reservation',
        'missing' => 'Reservation mangler',
    ];
}
if ($isAir) {
    $reservationOptions = [];
}
if ($isFerry) {
    $reservationOptions = [];
}

$mapClass = function($v): string {
    $v = strtolower(trim((string)$v));
    if (in_array($v, ['economy','standard','coach','main','economy_class'], true)) { return 'economy'; }
    if (in_array($v, ['premium_economy','premium economy','premium','economy_plus'], true)) { return 'premium_economy'; }
    if (in_array($v, ['business','business class','biz'], true)) { return 'business'; }
    if (in_array($v, ['first','first class','1st','1st_class','1'], true)) { return 'first'; }
    if ($v === '1st_class' || $v === '1st' || $v === 'first' || $v === '1') { return '1st'; }
    if ($v === '2nd_class' || $v === '2nd' || $v === 'second' || $v === '2') { return '2nd'; }
    if ($v === 'business' || $v === 'premium') { return '1st'; }
    if ($v === 'economy' || $v === 'standard') { return '2nd'; }
    if ($v === 'sleeper') { return 'sleeper'; }
    if ($v === 'couchette') { return 'couchette'; }
    if ($v === 'seat_reserved' || $v === 'free_seat') { return '2nd'; }
    return $v;
};
$mapRes = function($v): string {
    $v = strtolower(trim((string)$v));
    if ($v === 'seat_reserved' || $v === 'reserved' || $v === 'seat') { return 'reserved'; }
    if ($v === 'free' || $v === 'free_seat') { return 'free_seat'; }
    if ($v === 'none' || $v === 'no' || $v === 'unreserved' || $v === 'no_reservation') { return 'free_seat'; }
    if ($v === 'n/a' || $v === 'na') { return ''; }
    if ($v === 'missing') { return 'missing'; }
    return $v;
};

// Fallback: build rows from auto segments if not provided
if (empty($journeyRowsDowng)) {
    try {
        $segSrc = (array)($meta['_segments_auto'] ?? []);
        $jr = [];
        foreach ($segSrc as $s) {
            $from = trim((string)($s['from'] ?? ''));
            $to = trim((string)($s['to'] ?? ''));
            $jr[] = [
                'leg' => $from . ' -> ' . $to,
                'dep' => (string)($s['schedDep'] ?? ''),
                'arr' => (string)($s['schedArr'] ?? ''),
                'train' => (string)($s['train'] ?? ($s['trainNo'] ?? '')),
                'change' => (string)($s['change'] ?? ''),
            ];
        }
        if (!empty($jr)) { $journeyRowsDowng = $jr; }
    } catch (\Throwable $e) { /* ignore */ }
}
// Last-resort fallback: build a single leg from extracted core fields
if (empty($journeyRowsDowng)) {
    $clean = function($s): string {
        $s = trim((string)$s);
        if (stripos($s, 'Til rejsen ') === 0) {
            $s = trim(substr($s, strlen('Til rejsen ')));
        }
        return $s;
    };
    $dep = $clean($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''));
    $arr = $clean($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''));
    $depTime = (string)($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? ''));
    $arrTime = (string)($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? ''));
    $train = (string)($form['train_no'] ?? ($meta['_auto']['train_no']['value'] ?? ''));
    if ($dep !== '' && $arr !== '' && $dep !== $arr) {
        $journeyRowsDowng = [[
            'leg' => $dep . ' -> ' . $arr,
            'dep' => $depTime,
            'arr' => $arrTime,
            'train' => $train,
            'change' => '',
        ]];
    }
}
?>
<?php
  $missedIdx = null;
  $missedNorm = strtolower(trim((string)$missedStation));
  $normStation = function(string $s): string {
      $s = strtolower(trim($s));
      $s = str_replace([' station',' st.',' st',' st ','  '], ['','','',' ',' '], $s);
      $s = preg_replace('/\s+/', ' ', $s);
      return $s;
  };
  if ($missedNorm !== '') {
      $missedNorm = $normStation($missedNorm);
      foreach ($journeyRowsDowng as $i => $r) {
          $leg = (string)($r['leg'] ?? '');
          $from = '';
          $to = '';
          if (strpos($leg, '->') !== false) {
              [$from, $to] = array_map('trim', explode('->', $leg, 2));
          } else {
              $from = trim($leg);
          }
          $fromN = $normStation($from);
          $toN = $normStation($to);
          if ($fromN !== '' && $fromN === $missedNorm) { $missedIdx = (int)$i; break; }
          if ($toN !== '' && $toN === $missedNorm) { $missedIdx = (int)$i + 1; break; }
      }
  }
?>
<?php if (!empty($journeyRowsDowng)): ?>
  <div id="perLegDowngrade" style="margin-top:12px; display:block;">
    <div class="small"><strong><?= $isAir ? 'Flight-segmenter (koebt vs floejet)' : ($isFerry ? 'Overfart / service (koebt vs leveret)' : 'Per-leg niveau (nedgradering)') ?></strong></div>
    <?php if ($missedNorm !== '' && $missedIdx !== null): ?>
      <div class="small muted" style="margin-top:4px;">Skift ved: <strong><?= h($missedStation) ?></strong>. R&aelig;kker f&oslash;r/efter markeres.</div>
    <?php endif; ?>
    <?php if (!empty($affectedSet)): ?>
      <div class="small muted" style="margin-top:4px;">
        Auto: viser kun de ben, vi mener er relevante. Du kan altid vise alle ben og rette.
        <button type="button" id="toggleAllDowngLegs" style="margin-left:8px; padding:2px 6px; font-size:12px;">Vis alle ben</button>
      </div>
    <?php endif; ?>
    <div class="small muted" style="margin-top:4px;"><?= $isAir ? 'LLM/OCR har udfyldt koebt/floejet kabineklasse. Marker nedgraderet hvis faktisk floejet var lavere.' : ($isFerry ? 'Brug felterne til at vise, hvad du havde koebt, og hvad der faktisk blev leveret. Reservationstabeller bruges ikke i ferry-sporet.' : 'LLM/OCR har udfyldt koebt/leveret niveau; marker nedgraderet hvis leveret var lavere.') ?></div>
    <div class="small" style="overflow:auto; margin-top:6px;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Straekning</th>
            <?php if (!$isAir && !$isFerry): ?><th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Skift</th><?php endif; ?>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;"><?= $isAir ? 'Fly' : ($isFerry ? 'Overfart' : 'Tog') ?></th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;"><?= $isAir ? 'Koebt kabineklasse' : ($isFerry ? 'Koebt service/niveau' : 'Koebt klasse') ?></th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;"><?= $isAir ? 'Faktisk floejet kabineklasse' : ($isFerry ? 'Faktisk leveret service/niveau' : 'Leveret klasse') ?></th>
            <?php if (!$isAir && !$isFerry): ?>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Koebt reservation</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Leveret reservation</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
            <?php foreach ($journeyRowsDowng as $idx => $r): ?>
              <?php
              // Prefer AUTO extraction, fall back to manual ticketless/hook inputs (meta/form).
              $autoClass = (string)($meta['_auto']['fare_class_purchased']['value'] ?? ($meta['fare_class_purchased'] ?? ($form['fare_class_purchased'] ?? '')));
              $autoBerth = (string)($meta['_auto']['berth_seat_type']['value'] ?? ($meta['berth_seat_type'] ?? ($form['berth_seat_type'] ?? '')));
              $autoLegClass = (string)($meta['_auto']['leg_class_purchased'][$idx]['value'] ?? '');
              $autoLegRes = (string)($meta['_auto']['leg_reservation_purchased'][$idx]['value'] ?? '');
              $purchasedVal = (string)($form["leg_class_purchased"][$idx] ?? '');
              if ($purchasedVal === '') {
                if ($autoLegClass !== '') {
                  $purchasedVal = $autoLegClass;
                } elseif (in_array(strtolower($autoBerth), ['sleeper','couchette'], true)) {
                  $purchasedVal = $autoBerth;
                } else {
                  $purchasedVal = $autoClass;
                }
              }
              $purchasedVal = $mapClass($purchasedVal);
              $deliveredVal = $mapClass($form["leg_class_delivered"][$idx] ?? ($meta['_auto']['class_delivered'][$idx]['value'] ?? ""));
              $resPurchasedVal = $isAir ? '' : (string)($form["leg_reservation_purchased"][$idx] ?? '');
              if (!$isAir) {
                if ($resPurchasedVal === '') {
                  if ($autoLegRes !== '') {
                    $resPurchasedVal = $autoLegRes;
                  } elseif (in_array(strtolower($autoBerth), ['seat_reserved','seat','free','free_seat'], true)) {
                    $resPurchasedVal = $autoBerth;
                  }
                }
                $resPurchasedVal = $mapRes($resPurchasedVal);
              }
              $resDeliveredVal = $isAir ? '' : $mapRes($form["leg_reservation_delivered"][$idx] ?? "");
              $phase = '';
              if ($missedIdx !== null) {
                $phase = ($idx < $missedIdx) ? 'F&oslash;r skift' : 'Efter skift';
              }
            ?>
            <?php $autoAffected = isset($affectedSet[(int)$idx]); ?>
            <tr<?= $autoAffected ? ' data-auto-affected="1" style="background:#fff7ed;"' : (!empty($affectedSet) ? ' data-auto-affected="0" style="display:none;"' : '') ?>>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["leg"]) ?></td>
              <?php if (!$isAir && !$isFerry): ?><td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= $phase ?></td><?php endif; ?>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["dep"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["arr"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["train"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_class_purchased[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= $isAir ? __("Vaelg koebt kabineklasse") : __("Vaelg koebt niveau") ?></option>
                  <?php foreach ($classOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $purchasedVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_class_delivered[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= $isAir ? __("Vaelg floejet kabineklasse") : __("Vaelg leveret niveau") ?></option>
                  <?php foreach ($classOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $deliveredVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <?php if ($isAir || $isFerry): ?>
                <input type="hidden" name="leg_reservation_purchased[<?= (int)$idx ?>]" value="" />
                <input type="hidden" name="leg_reservation_delivered[<?= (int)$idx ?>]" value="" />
              <?php else: ?>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_reservation_purchased[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= __("Vaelg koebt reservation") ?></option>
                  <?php foreach ($reservationOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $resPurchasedVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_reservation_delivered[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= __("Vaelg leveret reservation") ?></option>
                  <?php foreach ($reservationOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $resDeliveredVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
      (function(){
        const classRank = {
          'sleeper': 4,
          'couchette': 3,
          'first': 4,
          'business': 3,
          'premium_economy': 2,
          'economy': 1,
          '1st': 2,
          '2nd': 1
        };
        function normClass(v){
          v = (v || '').toLowerCase().trim();
          if (v === 'premium economy') return 'premium_economy';
          if (v === 'economy class' || v === 'main cabin' || v === 'coach') return 'economy';
          if (v === 'business class' || v === 'biz') return 'business';
          if (v === 'first class') return 'first';
          if (v === '1st_class' || v === '1st' || v === '1') return '1st';
          if (v === '2nd_class' || v === '2nd' || v === 'second' || v === '2') return '2nd';
          if (v === 'business' || v === 'premium') return '1st';
          if (v === 'economy' || v === 'standard') return '2nd';
          if (v === 'seat_reserved' || v === 'free_seat') return '2nd';
          return v;
        }
        function normRes(v){
          v = (v || '').toLowerCase().trim();
          if (v === 'seat_reserved' || v === 'reserved' || v === 'seat') return 'reserved';
          if (v === 'free' || v === 'free_seat') return 'free_seat';
          if (v === 'none' || v === 'no' || v === 'unreserved' || v === 'no_reservation') return 'free_seat';
          if (v === 'n/a' || v === 'na') return '';
          if (v === 'missing') return 'missing';
          return v;
        }
        function bindRow(row, idx){
          const selBuy = row.querySelector('select[name="leg_class_purchased['+idx+']"]');
          const selDel = row.querySelector('select[name="leg_class_delivered['+idx+']"]');
        const selResBuy = row.querySelector('select[name="leg_reservation_purchased['+idx+']"]');
        const selResDel = row.querySelector('select[name="leg_reservation_delivered['+idx+']"]');
        if (!selBuy || !selDel) return;
          let hid = row.querySelector('input[name="leg_downgraded['+idx+']"]');
          if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'leg_downgraded['+idx+']';
            row.appendChild(hid);
          }
          const auto = () => {
            const cBuy = normClass(selBuy.value);
            const cDel = normClass(selDel.value);
            const rBuy = classRank[cBuy] || 0;
            const rDel = classRank[cDel] || 0;
            const classDown = rDel > 0 && rBuy > rDel;
          const resDown = selResBuy && selResDel
            ? ((normRes(selResBuy.value) === 'reserved') && (normRes(selResDel.value) !== '' && normRes(selResDel.value) !== 'reserved'))
            : false;
          const downg = classDown || resDown;
          hid.value = downg ? '1' : '';
        };
        selBuy.addEventListener('change', auto);
        selDel.addEventListener('change', auto);
        if (selResBuy) { selResBuy.addEventListener('change', auto); }
        if (selResDel) { selResDel.addEventListener('change', auto); }
        auto();
      }
        document.querySelectorAll('#perLegDowngrade table tbody tr').forEach((tr,i)=>bindRow(tr,i));

        var btn = document.getElementById('toggleAllDowngLegs');
        if (btn) {
          var shown = false;
          btn.addEventListener('click', function(){
            shown = !shown;
            document.querySelectorAll('#perLegDowngrade table tbody tr[data-auto-affected=\"0\"]').forEach(function(tr){
              tr.style.display = shown ? '' : 'none';
            });
            btn.textContent = shown ? 'Skjul ekstra ben' : 'Vis alle ben';
          });
        }
      })();
    </script>
  </div>
<?php endif; ?>
