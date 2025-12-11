<?php
/** @var \App\View\AppView $this */
$art9Block = $art9['downgrade'] ?? $art9 ?? [];
$results = $art9Block['results'] ?? [];
$labels = $art9Block['labels'] ?? [];
$currency = $art9Block['currency'] ?? ($claim['currency'] ?? 'EUR');
?>
<?php if (!empty($results)): ?>
<div class="card" style="margin-top:16px;">
  <h3>Nedgradering pr. stræk</h3>
  <div class="small muted">jf. CIV + GCC‑CIV/PRR · jf. Art. 9(1) · (ved omlægning jf. Art. 18(2))</div>
  <ul style="margin-top:8px;">
    <?php foreach ($results as $row): ?>
      <li>
        <strong>Strækning #<?= (int)($row['legIndex'] ?? -1) ?>:</strong>
        Refusion
        <?php if (isset($row['refund']['amount'])): ?>
          <?= number_format((float)$row['refund']['amount'], 2) ?> <?= h($row['refund']['currency'] ?? $currency) ?>
        <?php elseif (isset($row['refund']['percent'])): ?>
          <?= number_format((float)$row['refund']['percent'], 0) ?>%
        <?php else: ?>
          (beløb udregnes)
        <?php endif; ?>
        — metode: <?= h($row['method'] ?? 'prorata') ?>.
        <?php if (!empty($row['note'])): ?>
          <span class="small muted">(<?= h($row['note']) ?>)</span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!empty($labels)): ?>
    <div class="small muted" style="margin-top:8px;">
      <?= h(implode(' · ', $labels)) ?>
    </div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="card" style="margin-top:16px;">
  <h3>Nedgradering pr. stræk</h3>
  <div class="small muted">Ingen nedgradering af klasse eller plads registreret.</div>
</div>
<?php endif; ?>