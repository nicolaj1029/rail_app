<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Screening (TRIN 3–4)</h2>
<?= $this->Form->create(null) ?>
<fieldset>
    <legend>CIV / liability</legend>
    <label><?= $this->Form->checkbox('hasValidTicket', ['checked' => true]) ?> Valid ticket</label><br>
    <label><?= $this->Form->checkbox('safetyMisconduct') ?> Safety misconduct</label><br>
    <label><?= $this->Form->checkbox('forbiddenItemsOrAnimals') ?> Forbidden items/animals</label><br>
    <label><?= $this->Form->checkbox('customsRulesBreached', ['checked' => true]) ?> Complied with customs rules</label>
</fieldset>
<fieldset>
    <legend>Contracts (Art. 12)</legend>
    <label><?= $this->Form->checkbox('singleTxn') ?> All legs paid in a single transaction</label><br>
    <label><?= $this->Form->checkbox('separateContractsDisclosed') ?> Agents disclosed separate contracts</label>
</fieldset>
<?php if (isset($liability_ok)): ?>
    <p>Liability ok: <strong><?= $liability_ok ? 'Yes' : 'No' ?></strong></p>
<?php endif; ?>
<?php if (isset($missedConnBlock)): ?>
    <p>Missed-connection block (Art. 12 note): <strong><?= $missedConnBlock ? 'Yes' : 'No' ?></strong></p>
<?php endif; ?>
<div style="display:flex;gap:8px;align-items:center">
    <?= $this->Html->link('← Back', ['action' => 'details'], ['class' => 'button']) ?>
    <?= $this->Form->button('Next →') ?>
</div>
<?= $this->Form->end() ?>
