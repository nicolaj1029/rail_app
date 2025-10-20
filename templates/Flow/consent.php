<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Consent (TRIN 12)</h2>
<?= $this->Form->create(null) ?>
<fieldset>
    <legend>GDPR</legend>
    <label><?= $this->Form->checkbox('gdprConsent') ?> I consent to processing my data for handling this claim</label>
</fieldset>
<fieldset>
    <legend>Additional information</legend>
    <?= $this->Form->textarea('additionalInfo', ['rows' => 5]) ?>
</fieldset>
<div style="display:flex;gap:8px;align-items:center">
    <?= $this->Html->link('← Back', ['action' => 'applicant'], ['class' => 'button']) ?>
    <?= $this->Form->button('Finish →') ?>
</div>
<?= $this->Form->end() ?>
<p>
    Gå til summary: <?= $this->Html->link('Summary', ['action' => 'summary']) ?>
</p>
