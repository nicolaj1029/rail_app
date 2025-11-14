<?php
/** @var \Cake\View\View $this */
/** @var \Cake\Datasource\ResultSetInterface $cases */
?>
<h2>Sager</h2>
<form method="get" class="mb8">
  <input type="text" name="q" value="<?= h((string)($q ?? '')) ?>" placeholder="Søg i ref, navn, operatør" style="width:280px;" />
  <button type="submit">Søg</button>
  <a href="<?= $this->Url->build(['action' => 'index']) ?>" class="ml6">Nulstil</a>
</form>
<table class="table" style="width:100%; font-size:13px;">
  <thead>
    <tr>
      <th>ID</th>
      <th>Ref</th>
      <th>Status</th>
      <th>Dato</th>
      <th>Passager</th>
      <th>Operatør</th>
      <th>Delay (EU)</th>
      <th>Art.18</th>
      <th>Art.20</th>
      <th>Art.19</th>
      <th>EU-only</th>
      <th>FM</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($cases as $c): ?>
      <tr>
        <td><?= (int)$c->id ?></td>
        <td><code><?= h((string)$c->ref) ?></code></td>
        <td><?= h((string)$c->status) ?></td>
        <td><?= h($c->travel_date ? $c->travel_date->format('Y-m-d') : '') ?></td>
        <td><?= h((string)$c->passenger_name) ?></td>
        <td><?= h((string)$c->operator) ?></td>
        <td><?= h((string)$c->delay_min_eu) ?> min</td>
        <td><?= h((string)$c->remedy_choice ?: '-') ?></td>
        <td><?= $c->art20_expenses_total !== null ? (h(number_format((float)$c->art20_expenses_total, 2, ',', '.')) . ' ' . h((string)$c->currency)) : '-' ?></td>
        <td><?= $c->comp_amount !== null ? (h(number_format((float)$c->comp_amount, 2, ',', '.')) . ' ' . h((string)$c->currency) . ($c->comp_band?(' ('.h((string)$c->comp_band).'%)'):'') ) : '-' ?></td>
        <td><?= $c->eu_only ? '✔' : '✖' ?></td>
        <td><?= $c->extraordinary ? '⚠' : '' ?></td>
        <td><a href="<?= $this->Url->build(['action' => 'view', $c->id]) ?>">Vis</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p class="mt8 small">Tip: Opret sag fra aktuel session via knap i hooks-panel (admin).</p>
