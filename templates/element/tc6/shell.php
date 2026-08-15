<?php
/**
 * Outer TC6 shell.
 *
 * Owns layout only:
 * - sidebar
 * - center content
 * - right panel
 *
 * It must not own gates, redirects or progression logic.
 */
?>
<div class="tc6-shell<?= !empty($shellClass) ? ' ' . h((string)$shellClass) : '' ?>">
  <?= $this->element('tc6/sidebar', [
      'steps'         => $steps       ?? [],
      'currentStep'   => $currentStep ?? 1,
      'doneSteps'     => $doneSteps   ?? [],
      'context'       => $context     ?? null,
      'brandName'     => $brandName   ?? null,
      'brandMark'     => $brandMark   ?? null,
      'showLockIcons' => $showLockIcons ?? false,
  ]) ?>
  <?php if (!empty($panelBeforeMain)): ?>
    <?= $rightPanel ?? $this->element('tc6/right_rail_panel', $panelData ?? []) ?>
  <?php endif; ?>
  <main class="tc6-center tc6-fade-in">
    <?= $content ?? '' ?>
  </main>
  <?php if (empty($panelBeforeMain)): ?>
    <?= $rightPanel ?? $this->element('tc6/right_rail_panel', $panelData ?? []) ?>
  <?php endif; ?>
</div>
