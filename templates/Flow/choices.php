<?php
/** @var \App\View\AppView $this */
?>
<h2>Flow · Choices (TRIN 5–6)</h2>
<?= $this->Form->create(null) ?>
<fieldset>
    <legend>Remedy choice (Art. 18)</legend>
    <?= $this->Form->radio('remedyChoice', [
        ['value' => 'refund', 'text' => 'Refund'],
        ['value' => 'reroute_soonest', 'text' => 'Reroute (same conditions, soonest)'],
        ['value' => 'reroute_later', 'text' => 'Reroute (later at your choice)'],
    ], ['empty' => true]) ?>
    <label><?= $this->Form->checkbox('refund_requested') ?> Refund requested</label>
</fieldset>
<fieldset>
    <legend>Compensation (Art. 19)</legend>
    <label>Delay at final (min)</label>
    <?= $this->Form->number('delayAtFinalMinutes', ['min' => 0]) ?>
    <div>
        <label>Compensation band</label>
        <?= $this->Form->select('compensationBand', [
            '' => '(auto/none)',
            '25' => '≥60 min (25%)',
            '50' => '≥120 min (50%)',
        ]) ?>
    </div>
</fieldset>
<?php if (isset($allow_refund)): ?>
<p>Allow refund: <strong><?= $allow_refund ? 'Yes' : 'No' ?></strong></p>
<?php endif; ?>
<?php if (isset($allow_compensation)): ?>
<p>Allow compensation: <strong><?= $allow_compensation ? 'Yes' : 'No' ?></strong></p>
<?php endif; ?>
<div style="display:flex;gap:8px;align-items:center">
    <?= $this->Html->link('← Back', ['action' => 'screening'], ['class' => 'button']) ?>
    <?= $this->Form->button('Next →') ?>
</div>
<?= $this->Form->end() ?>
