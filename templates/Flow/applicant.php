<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$isPreview = !empty($flowPreview);
?>

<h1>TRIN 11 - Ansoeger & udbetaling</h1>
<?= $this->element('flow_locked_notice') ?>

<?= $this->Form->create(null) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
    <legend>Person</legend>
    <?= $this->Form->control('firstName', ['label' => 'Fornavn']) ?>
    <?= $this->Form->control('lastName', ['label' => 'Efternavn']) ?>
    <?= $this->Form->control('contact_email', ['label' => 'E-mail']) ?>
    <?= $this->Form->control('contact_phone', ['label' => 'Telefon']) ?>

    <legend>Adresse</legend>
    <?= $this->Form->control('address_street', ['label' => 'Vej']) ?>
    <?= $this->Form->control('address_no', ['label' => 'Nr.']) ?>
    <?= $this->Form->control('address_postalCode', ['label' => 'Postnr.']) ?>
    <?= $this->Form->control('address_city', ['label' => 'By']) ?>
    <?= $this->Form->control('address_country', ['label' => 'Land']) ?>

    <legend>Udbetaling</legend>
    <?= $this->Form->control('payoutPreference', [
        'label' => 'Udbetalingsmetode',
        'options' => [
            'bank' => 'Bankoverfoersel',
            'voucher' => 'Voucher',
            'other' => 'Andet',
        ],
        'empty' => true,
    ]) ?>
    <?= $this->Form->control('accountHolderName', ['label' => 'Kontohaver']) ?>
    <?= $this->Form->control('iban', ['label' => 'IBAN']) ?>
    <?= $this->Form->control('bic', ['label' => 'BIC']) ?>

    <div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
        <?= $this->Html->link('Tilbage', ['action' => 'compensation'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
        <?= $this->Form->button('Fortsaet', ['class' => 'button']) ?>
    </div>
</fieldset>
<?= $this->Form->end() ?>
