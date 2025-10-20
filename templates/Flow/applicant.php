<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Applicant (TRIN 11)</h2>
<?= $this->Form->create(null) ?>
<fieldset>
    <legend>Person</legend>
    <?= $this->Form->control('firstName') ?>
    <?= $this->Form->control('lastName') ?>
    <?= $this->Form->control('contact_email') ?>
    <?= $this->Form->control('contact_phone') ?>
</fieldset>
<fieldset>
    <legend>Address</legend>
    <?= $this->Form->control('address_street') ?>
    <?= $this->Form->control('address_no') ?>
    <?= $this->Form->control('address_postalCode') ?>
    <?= $this->Form->control('address_city') ?>
    <?= $this->Form->control('address_country') ?>
</fieldset>
<fieldset>
    <legend>Payout</legend>
    <?= $this->Form->control('payoutPreference', ['options' => [
        'bank' => 'Bank transfer',
        'voucher' => 'Voucher',
        'other' => 'Other',
    ], 'empty' => true]) ?>
    <?= $this->Form->control('accountHolderName') ?>
    <?= $this->Form->control('iban') ?>
    <?= $this->Form->control('bic') ?>
</fieldset>
<div style="display:flex;gap:8px;align-items:center">
    <?= $this->Html->link('← Back', ['action' => 'extras'], ['class' => 'button']) ?>
    <?= $this->Form->button('Next →') ?>
</div>
<?= $this->Form->end() ?>
