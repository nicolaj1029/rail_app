<?php
/**
 * Shared downgrade table (Art. 9(1) / Art. 18 stk. 3).
 * Expects: $journeyRowsDowng (array of legs), $classOptions (array), $form (array), $meta (array).
 */
$journeyRowsDowng = $journeyRowsDowng ?? [];
$classOptions = $classOptions ?? [];
$form = $form ?? [];
$meta = $meta ?? [];

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
?>
<?php if (!empty($journeyRowsDowng)): ?>
  <div id="perLegDowngrade" style="margin-top:12px; display:block;">
    <div class="small"><strong>Per-leg niveau (nedgradering)</strong></div>
    <div class="small muted" style="margin-top:4px;">LLM/OCR har udfyldt købt/leveret niveau; marker nedgraderet hvis leveret var lavere.</div>
    <div class="small" style="overflow:auto; margin-top:6px;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Strækning</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Tog</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Købt klasse/reservation</th>
            <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Leveret klasse/reservation</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($journeyRowsDowng as $idx => $r): ?>
            <?php
              $purchasedVal = (string)($form["leg_class_purchased"][$idx] ?? ($meta['_class_detection']['fare_class_purchased'] ?? ($meta['_auto']['berth_seat_type']['value'] ?? "")));
              $deliveredVal = (string)($form["leg_class_delivered"][$idx] ?? ($meta['_auto']['class_delivered'][$idx]['value'] ?? ""));
            ?>
            <tr>
              <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r["leg"]) ?></td>
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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <script>
      (function(){
        const rank = {
          'sleeper': 5,
          '1st_class': 4,
          'seat_reserved': 3,
          'couchette': 3,
          '2nd_class': 2,
          'other': 2,
          'free_seat': 1
        };
        function bindRow(row, idx){
          const selBuy = row.querySelector('select[name="leg_class_purchased['+idx+']"]');
          const selDel = row.querySelector('select[name="leg_class_delivered['+idx+']"]');
          if (!selBuy || !selDel) return;
          let hid = row.querySelector('input[name="leg_downgraded['+idx+']"]');
          if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'leg_downgraded['+idx+']';
            row.appendChild(hid);
          }
          const auto = () => {
            const rBuy = rank[selBuy.value] || 0;
            const rDel = rank[selDel.value] || 0;
            const downg = rDel > 0 && rBuy > rDel;
            hid.value = downg ? '1' : '';
          };
          selBuy.addEventListener('change', auto);
          selDel.addEventListener('change', auto);
          auto();
        }
        document.querySelectorAll('#perLegDowngrade table tbody tr').forEach((tr,i)=>bindRow(tr,i));
      })();
    </script>
  </div>
<?php endif; ?>
