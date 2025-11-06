<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Extras (TRIN 7–10)</h2>
<?= $this->Form->create(null) ?>
<fieldset>
    <legend>Purchase & PMR</legend>
    <div>
        <label>Purchase channel</label>
        <?= $this->Form->select('purchaseChannel', [
            'operator_app' => 'Operator app',
            'website' => 'Website',
            'station' => 'Station',
            'agency' => 'Agency',
            'other' => 'Other',
        ], ['empty' => true]) ?>
    </div>
    <label><?= $this->Form->checkbox('pmrUser') ?> Reduced mobility</label><br>
    <label><?= $this->Form->checkbox('assistancePromised') ?> Assistance promised</label><br>
    <label><?= $this->Form->checkbox('assistanceDelivered') ?> Assistance delivered</label>
</fieldset>
<fieldset>
    <legend>Expenses</legend>
    <label>Meals</label> <?= $this->Form->number('expense_meals', ['min' => 0, 'step' => '0.01']) ?><br>
    <label>Hotel</label> <?= $this->Form->number('expense_hotel', ['min' => 0, 'step' => '0.01']) ?><br>
    <label>Alt. transport</label> <?= $this->Form->number('expense_alt_transport', ['min' => 0, 'step' => '0.01']) ?><br>
    <label>Other</label> <?= $this->Form->number('expense_other', ['min' => 0, 'step' => '0.01']) ?>
</fieldset>
<div style="display:flex;gap:8px;align-items:center">
    <?= $this->Html->link('← Back', ['action' => 'compensation'], ['class' => 'button']) ?>
    <?= $this->Form->button('Next →') ?>
</div>
<?= $this->Form->end() ?>
