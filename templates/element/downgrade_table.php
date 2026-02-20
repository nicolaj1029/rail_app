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
$missedStation = $missedStation ?? '';

if (empty($classOptions)) {
    $classOptions = [
        'sleeper' => 'Sovevogn',
        'couchette' => 'Liggevogn',
        '1st' => '1. klasse',
        '2nd' => '2. klasse',
    ];
}
if (empty($reservationOptions)) {
    $reservationOptions = [
        'reserved' => 'Reserveret plads',
        'free_seat' => 'Ingen reservation',
        'missing' => 'Reservation mangler',
    ];
}

$mapClass = function($v): string {
    $v = strtolower(trim((string)$v));
    if ($v === '1st_class' || $v === '1st' || $v === 'first' || $v === '1') { return '1st'; }
    if ($v === '2nd_class' || $v === '2nd' || $v === 'second' || $v === '2') { return '2nd'; }
    if ($v === 'sleeper') { return 'sleeper'; }
    if ($v === 'couchette') { return 'couchette'; }
    if ($v === 'seat_reserved' || $v === 'free_seat') { return '2nd'; }
    return $v;
};
$mapRes = function($v): string {
    $v = strtolower(trim((string)$v));
    if ($v === 'seat_reserved' || $v === 'reserved' || $v === 'seat') { return 'reserved'; }
    if ($v === 'free' || $v === 'free_seat') { return 'free_seat'; }
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
    <div class="small"><strong>Per-leg niveau (nedgradering)</strong></div>
    <?php if ($missedNorm !== '' && $missedIdx !== null): ?>
      <div class="small muted" style="margin-top:4px;">Skift ved: <strong><?= h($missedStation) ?></strong>. R&aelig;kker f&oslash;r/efter markeres.</div>
    <?php endif; ?>
    <div class="small muted" style="margin-top:4px;">LLM/OCR har udfyldt kÃ¸bt/leveret niveau; marker nedgraderet hvis leveret var lavere.</div>
    <div class="small" style="overflow:auto; margin-top:6px;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">StrÃ¦kning</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Skift</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Tog</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">KÃ¸bt klasse</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Leveret klasse</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">KÃ¸bt reservation</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Leveret reservation</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($journeyRowsDowng as $idx => $r): ?>
            <?php
              $autoClass = (string)($meta['_auto']['fare_class_purchased']['value'] ?? '');
              $autoBerth = (string)($meta['_auto']['berth_seat_type']['value'] ?? '');
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
              $resPurchasedVal = (string)($form["leg_reservation_purchased"][$idx] ?? '');
              if ($resPurchasedVal === '') {
                if ($autoLegRes !== '') {
                  $resPurchasedVal = $autoLegRes;
                } elseif (in_array(strtolower($autoBerth), ['seat_reserved','seat','free','free_seat'], true)) {
                  $resPurchasedVal = $autoBerth;
                }
              }
              $resPurchasedVal = $mapRes($resPurchasedVal);
              $resDeliveredVal = $mapRes($form["leg_reservation_delivered"][$idx] ?? "");
              $phase = '';
              if ($missedIdx !== null) {
                $phase = ($idx < $missedIdx) ? 'F&oslash;r skift' : 'Efter skift';
              }
            ?>
            <tr>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["leg"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= $phase ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["dep"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["arr"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["train"]) ?></td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_class_purchased[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= __("Vaelg koebt niveau") ?></option>
                  <?php foreach ($classOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $purchasedVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
                <select name="leg_class_delivered[<?= (int)$idx ?>]" style="width:100%; min-width:140px;">
                  <option value=""><?= __("Vaelg leveret niveau") ?></option>
                  <?php foreach ($classOptions as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $deliveredVal===$key?"selected":"" ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
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
          '1st': 2,
          '2nd': 1
        };
        function normClass(v){
          v = (v || '').toLowerCase().trim();
          if (v === '1st_class' || v === '1st' || v === 'first' || v === '1') return '1st';
          if (v === '2nd_class' || v === '2nd' || v === 'second' || v === '2') return '2nd';
          if (v === 'seat_reserved' || v === 'free_seat') return '2nd';
          return v;
        }
        function normRes(v){
          v = (v || '').toLowerCase().trim();
          if (v === 'seat_reserved' || v === 'reserved' || v === 'seat') return 'reserved';
          if (v === 'free' || v === 'free_seat') return 'free_seat';
          if (v === 'missing') return 'missing';
          return v;
        }
        function bindRow(row, idx){
          const selBuy = row.querySelector('select[name="leg_class_purchased['+idx+']"]');
          const selDel = row.querySelector('select[name="leg_class_delivered['+idx+']"]');
          const selResBuy = row.querySelector('select[name="leg_reservation_purchased['+idx+']"]');
          const selResDel = row.querySelector('select[name="leg_reservation_delivered['+idx+']"]');
          if (!selBuy || !selDel || !selResBuy || !selResDel) return;
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
            const resBuy = normRes(selResBuy.value);
            const resDel = normRes(selResDel.value);
            const resDown = (resBuy === 'reserved') && (resDel !== '' && resDel !== 'reserved');
            const downg = classDown || resDown;
            hid.value = downg ? '1' : '';
          };
          selBuy.addEventListener('change', auto);
          selDel.addEventListener('change', auto);
          selResBuy.addEventListener('change', auto);
          selResDel.addEventListener('change', auto);
          auto();
        }
        document.querySelectorAll('#perLegDowngrade table tbody tr').forEach((tr,i)=>bindRow(tr,i));
      })();
    </script>
  </div>
<?php endif; ?>
