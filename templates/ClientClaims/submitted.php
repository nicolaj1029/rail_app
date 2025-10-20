<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Claim $claim */
?>
<div class="content">
  <h1>Tak! Din sag er modtaget</h1>
  <p><strong>Sagsnummer:</strong> <?= h($claim->case_number) ?></p>
  <p><strong>Forventet kompensation (beregnet):</strong> <?= number_format((float)$claim->compensation_amount, 2) . ' ' . h($claim->currency) ?> (<?= (int)$claim->computed_percent ?>% via <?= h($claim->computed_source) ?>)</p>
  <p><strong>Vores honorar:</strong> <?= number_format((float)$claim->fee_amount, 2) . ' ' . h($claim->currency) ?> (<?= (int)$claim->fee_percent ?>%)</p>
  <p><strong>Udbetaling nu:</strong> <?= number_format((float)$claim->payout_amount, 2) . ' ' . h($claim->currency) ?></p>
  <p>Vi kontakter dig på <?= h($claim->client_email) ?>. Du kan svarere denne mail, hvis du vil tilføje bilag eller oplysninger.</p>
  <p><?= $this->Html->link('Til forsiden', ['controller' => 'Pages', 'action' => 'display', 'home']) ?></p>
</div>
