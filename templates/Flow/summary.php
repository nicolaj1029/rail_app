<?php
/** @var \App\View\AppView $this */
?>
<h1>Opsummering og PDF</h1>
<p>Estimeret kompensation (brutto/netto):</p>
<ul>
  <li>Brutto: <?= number_format((float)($claim['gross'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
  <li>Gebyr: <?= number_format((float)($claim['fee'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
  <li>Netto: <?= number_format((float)($claim['net'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
</ul>

<?php if (!empty($additional_info)): ?>
  <h3>TRIN valg</h3>
  <p><?= h($additional_info) ?></p>
<?php endif; ?>

<p>
  <a href="<?= $this->Url->build('/reimbursement/generate') ?>" target="_blank">Generér PDF opsummering</a>
  &nbsp;|&nbsp;
  <a href="<?= $this->Url->build('/reimbursement/official') ?>" target="_blank">Udfyld officiel EU formular</a>
</p>

<div style="display:flex; gap:8px; align-items:center; margin-top:12px;">
  <?= $this->Html->link('← Tilbage', ['action' => 'entitlements'], ['class' => 'button', 'style' => 'background:#eee;color:#333;']) ?>
  <?= $this->Html->link('Fortsæt →', ['action' => 'choices'], ['class' => 'button']) ?>
  <span class="small muted" style="margin-left:auto;">Du kan også downloade/udfylde formular via links ovenfor.</span>
  </div>
