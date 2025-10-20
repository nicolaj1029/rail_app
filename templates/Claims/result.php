<?php
/** @var \App\View\AppView $this */
/** @var array $result */
/** @var array $ctx */
?>
<div class="content">
  <h1>Resultat</h1>
  <style>
    .badges { display:flex; flex-wrap: wrap; gap:6px; margin: 8px 0 12px; }
    .badge { background:#eef; color:#223; border:1px solid #99b; border-radius: 12px; padding: 2px 8px; font-size: 12px; }
    .badge.warn { background:#fee; border-color:#d99; color:#522; }
  </style>
  <?php
    $ex = $result['exemptions'] ?? [];
    $scope = $result['scope'] ?? null;
  ?>
  <?php if (!empty($ex) || !empty($scope)): ?>
    <div class="badges">
      <?php if (!empty($scope)): ?>
        <span class="badge">Scope: <?= h((string)$scope) ?></span>
      <?php endif; ?>
      <?php foreach ((array)$ex as $art): ?>
        <?php
          $artStr = (string)$art;
          $display = (stripos($artStr, 'art') === false && preg_match('/^\s*\d/', $artStr)) ? ('Art. ' . $artStr) : $artStr;
        ?>
        <span class="badge warn">Exemption: <?= h($display) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p><strong>Kompensationsprocent:</strong> <?= (int)($result['percent'] ?? 0) ?>%</p>
  <p><strong>Kilde:</strong> <?= h($result['source'] ?? 'eu') ?></p>
  <?php if (!empty($result['payout'])): ?>
    <p><strong>Udbetalingsform:</strong> <?= h($result['payout']) ?></p>
  <?php endif; ?>
  <?php if (!empty($result['notes'])): ?>
    <p><strong>Note:</strong> <?= h($result['notes']) ?></p>
  <?php endif; ?>
  <?php if (!empty($result['overrideNotes'])): ?>
    <p><strong>Override-noter:</strong> <?= h($result['overrideNotes']) ?></p>
  <?php endif; ?>
  <?php if (!empty($result['overrideSource'])): ?>
    <p><strong>Kilde (operatør):</strong> <?= h($result['overrideSource']) ?></p>
  <?php endif; ?>

  <h3>Input</h3>
  <pre><?= h(var_export($ctx, true)) ?></pre>

  <p><?= $this->Html->link('Tilbage', ['action' => 'start']) ?></p>
</div>
