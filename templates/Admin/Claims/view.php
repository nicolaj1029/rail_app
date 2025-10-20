<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Claim $claim */
?>
<div class="content">
  <h1>Sag <?= h($claim->case_number) ?></h1>
  <dl>
    <dt>Klient</dt>
    <dd><?= h($claim->client_name) ?> (<?= h($claim->client_email) ?>)</dd>
    <dt>Operatør / Produkt</dt>
    <dd><?= h($claim->operator) ?> / <?= h($claim->product) ?></dd>
    <dt>Forsinkelse</dt>
    <dd><?= (int)$claim->delay_min ?> min</dd>
    <dt>Kompensation</dt>
    <dd><?= (int)$claim->computed_percent ?>% (<?= h($claim->computed_source) ?>) → <?= number_format((float)$claim->compensation_amount, 2) ?> <?= h($claim->currency) ?></dd>
    <dt>Vores honorar</dt>
    <dd><?= (int)$claim->fee_percent ?>% → <?= number_format((float)$claim->fee_amount, 2) ?> <?= h($claim->currency) ?></dd>
    <dt>Udbetaling</dt>
    <dd><?= number_format((float)$claim->payout_amount, 2) ?> <?= h($claim->currency) ?></dd>
  <?php if (!empty($claim->assignment_pdf)): ?>
  <dt>Overdragelsesdokument</dt>
  <dd><a href="/<?= h($claim->assignment_pdf) ?>" target="_blank" rel="noopener">Download PDF</a></dd>
  <?php endif; ?>
    <dt>Status</dt>
    <dd><?= h($claim->status) ?></dd>
    <dt>Oprettet</dt>
    <dd><?= h($claim->created) ?></dd>
  </dl>
  <h3>Opdater status</h3>
  <?= $this->Form->create(null, ['url' => ['action' => 'updateStatus', $claim->id]]) ?>
    <?= $this->Form->control('status', ['label' => 'Status', 'value' => $claim->status]) ?>
    <?= $this->Form->control('notes', ['label' => 'Noter', 'type' => 'textarea', 'value' => $claim->notes]) ?>
    <?= $this->Form->button('Gem status') ?>
  <?= $this->Form->end() ?>

  <h3>Markér som betalt</h3>
  <?= $this->Form->create(null, ['url' => ['action' => 'markPaid', $claim->id]]) ?>
    <?= $this->Form->control('payout_reference', ['label' => 'Udbetalingsreference']) ?>
    <?= $this->Form->button('Markér betalt') ?>
  <?= $this->Form->end() ?>

  <?php if (!empty($claim->claim_attachments)): ?>
    <h3>Bilag</h3>
    <ul>
      <?php foreach ($claim->claim_attachments as $att): ?>
        <li>[<?= h($att->type) ?>] <a href="/<?= h($att->path) ?>" target="_blank" rel="noopener"><?= h($att->original_name) ?></a> (<?= (int)$att->size ?> bytes)</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p><?= $this->Html->link('Tilbage til oversigt', ['action' => 'index']) ?></p>
</div>
