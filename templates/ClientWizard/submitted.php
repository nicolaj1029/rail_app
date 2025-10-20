<?php
/** @var \App\View\AppView $this */
/** @var array $calc */
?>
<div class="content">
  <h1>Tak – din sag er modtaget</h1>
  <p>Vi har modtaget din sag. Hvis du har valgt øjeblikkelig udbetaling, vil du modtage pengene kort efter.</p>
  <h3>Opsummering</h3>
  <ul>
    <li>Brutto: <?= h(number_format((float)($calc['totals']['gross_claim'] ?? 0), 2)) ?> <?= h($calc['totals']['currency'] ?? 'EUR') ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($calc['totals']['service_fee_amount'] ?? 0), 2)) ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($calc['totals']['net_to_client'] ?? 0), 2)) ?></strong></li>
  </ul>
  <p><?= $this->Html->link('Start ny sag', ['action' => 'start']) ?></p>
</div>
