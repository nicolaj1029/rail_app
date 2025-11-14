<?php
/** @var \App\View\AppView $this */
?>
<h1>Start</h1>
<?= $this->Form->create(null) ?>
  <fieldset style="background:#ffa50022; padding:10px;">
    <legend>TRIN 1 – Rejsens status</legend>
    <div class="small muted" style="margin-bottom:6px;">Vælg den situation der passer nu:</div>
    <?= $this->Form->radio('travel_state', [
      ['value' => 'completed', 'text' => 'Rejsen er afsluttet'],
      ['value' => 'ongoing', 'text' => 'Rejsen er påbegyndt – jeg er i toget eller er ved/skal til at skifte forbindelse'],
      ['value' => 'before_start', 'text' => 'Jeg står på banegården og skal til at påbegynde rejsen'],
    ], ['legend' => false, 'separator' => '<br/>']) ?>
  </fieldset>
  <?php $isAdmin = (bool)($isAdmin ?? false); ?>
  <?php if ($isAdmin): ?>
  <div style="margin-top:10px;">
    <label><?= $this->Form->checkbox('eu_only', ['checked' => !empty($compute['euOnly'])]) ?> Beregn kun for EU-delen (EU only)</label>
  </div>
  <?php endif; ?>
  <?= $this->Form->button('Fortsæt') ?>
<?= $this->Form->end() ?>
