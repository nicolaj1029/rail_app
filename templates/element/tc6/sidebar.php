<?php
/**
 * Sidebar for TC6 views.
 *
 * Accepts either:
 * - [stepId => 'Label']
 * - [stepId => ['label' => 'Label', 'url' => '/optional-link']]
 *
 * It does not invent routing. Links are rendered only when the controller
 * explicitly provides a URL.
 */

$context = $context ?? 'Afsluttet rejse';
$doneSteps = $doneSteps ?? [];
$steps = $steps ?? [];
$brandName     = $brandName     ?? 'TrainClaim';
$brandMark     = $brandMark     ?? 'TC';
$showLockIcons = $showLockIcons ?? false;
?>
<aside class="tc6-sidebar">
  <div class="tc6-sidebar__brand">
    <div class="tc6-sidebar__logo"><?= h($brandMark) ?></div>
    <span class="tc6-sidebar__name"><?= h($brandName) ?></span>
  </div>

  <div class="tc6-sidebar__context"><?= h($context) ?></div>

  <?php foreach ($steps as $id => $step):
      $label = is_array($step) ? (string)($step['label'] ?? $id) : (string)$step;
      $url = is_array($step) ? (string)($step['url'] ?? '') : '';
      $isDone = in_array($id, $doneSteps, true);
      $isActive = (int)$id === (int)$currentStep;
      $classes = 'tc6-step';
      if ($isActive) {
          $classes .= ' tc6-step--active';
      }
      if ($isDone) {
          $classes .= ' tc6-step--done';
      }
  ?>
    <?php if ((int)$id > array_key_first($steps)): ?>
      <div class="tc6-step-conn<?= $isDone ? ' tc6-step-conn--done' : '' ?>"></div>
    <?php endif; ?>

    <?php if ($url !== '' && ($isDone || $isActive)): ?>
      <a href="<?= h($url) ?>" class="<?= $classes ?>">
    <?php else: ?>
      <span class="<?= $classes ?>" aria-disabled="true">
    <?php endif; ?>

      <div class="tc6-step__num">
        <?php if ($isDone): ?>&#10003;<?php elseif ($showLockIcons && !$isActive): ?><svg width="10" height="12" viewBox="0 0 10 12" fill="none" aria-hidden="true"><rect x="2" y="5" width="6" height="6" rx="1.2" fill="currentColor" opacity=".55"/><path d="M3 5V3.5a2 2 0 0 1 4 0V5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7"/></svg><?php else: ?><?= h((string)$id) ?><?php endif; ?>
      </div>
      <span class="tc6-step__label"><?= h($label) ?></span>

    <?php if ($url !== '' && ($isDone || $isActive)): ?>
      </a>
    <?php else: ?>
      </span>
    <?php endif; ?>
  <?php endforeach; ?>
</aside>
