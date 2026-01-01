<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
$isAdmin = (bool)($isAdmin ?? false);
?>
<h1>Start</h1>
<?= $this->Form->create(null) ?>
  <fieldset style="background:#ffa50022; padding:10px;">
    <legend>TRIN 1 - Rejsens status</legend>
    <div class="small muted" style="margin-bottom:6px;">V?lg den situation der passer nu:</div>
    <?= $this->Form->radio('travel_state', [
      ['value' => 'completed', 'text' => 'Rejsen er afsluttet'],
      ['value' => 'ongoing',   'text' => 'Rejsen er igang'],
    ], ['legend' => false, 'separator' => '<br/>']) ?>
  </fieldset>
  <?php if ($isAdmin): ?>
  <div style="margin-top:10px;">
    <label><?= $this->Form->checkbox('eu_only', ['checked' => !empty($compute['euOnly'])]) ?> Beregn kun for EU-delen (EU only)</label>
  </div>
  <?php endif; ?>
  <?= $this->Form->button('Forts?t') ?>
<?= $this->Form->end() ?>
