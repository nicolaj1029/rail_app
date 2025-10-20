<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Udgifter (Art. 20)</h1>
  <p>Indtast dokumenterede udgifter. Du kan uploade bilag senere.</p>
  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Valuta og beløb</legend>
      <?= $this->Form->control('currency', ['label' => 'Valuta', 'value' => 'EUR']) ?>
      <?= $this->Form->control('meals', ['label' => 'Mad og drikke', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('hotel', ['label' => 'Hotel', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('alt_transport', ['label' => 'Alternativ transport (taxa/bus)', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
      <?= $this->Form->control('other', ['label' => 'Andet', 'type' => 'number', 'step' => '0.01', 'value' => 0]) ?>
    </fieldset>
    <?= $this->Form->button('Se opsummering') ?>
  <?= $this->Form->end() ?>
</div>
