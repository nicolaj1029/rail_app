<?php
/**
 * Shared action bar for TC6 views.
 *
 * Button behavior must still be governed by the normal Cake form submit.
 */

$backUrl = $backUrl ?? null;
$backLabel = $backLabel ?? 'Tilbage';
$nextLabel = $nextLabel ?? 'Naeste trin';
$nextVariant = $nextVariant ?? 'navy';
$saveUrl = $saveUrl ?? null;
$saveLabel = $saveLabel ?? 'Gem kladde';
$submitName = $submitName ?? '_save';
$submitValue = $submitValue ?? '1';
$disabled = $disabled ?? false;

if (is_string($backUrl) && $backUrl !== '') {
    $backUrl = html_entity_decode($backUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
if (is_string($saveUrl) && $saveUrl !== '') {
    $saveUrl = html_entity_decode($saveUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<div class="tc6-action-bar">
  <?php if ($backUrl): ?>
    <a href="<?= h($backUrl) ?>" class="tc6-btn tc6-btn--ghost">&larr; <?= h($backLabel) ?></a>
  <?php else: ?>
    <span></span>
  <?php endif; ?>

  <div class="tc6-action-bar__right">
    <?php if ($saveUrl): ?>
      <a href="<?= h($saveUrl) ?>" class="tc6-btn tc6-btn--ghost"><?= h($saveLabel) ?></a>
    <?php endif; ?>

    <button
      type="submit"
      name="<?= h($submitName) ?>"
      value="<?= h($submitValue) ?>"
      class="tc6-btn tc6-btn--<?= h($nextVariant) ?>"
      <?= $disabled ? 'disabled' : '' ?>
    >
      <?= h($nextLabel) ?>
    </button>
  </div>
</div>
