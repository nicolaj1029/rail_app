<?php
/** @var \App\View\AppView $this */
/** @var iterable $claims */
?>
<div class="content">
  <h1>Sager</h1>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Sagsnr</th>
        <th>Klient</th>
        <th>Operatør</th>
        <th>Forsinkelse</th>
        <th>Komp%</th>
        <th>Udbetaling</th>
        <th>Oprettet</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($claims as $c): ?>
      <tr>
        <td><?= (int)$c->id ?></td>
        <td><?= h($c->case_number) ?></td>
        <td><?= h($c->client_name) ?> (<?= h($c->client_email) ?>)</td>
        <td><?= h($c->operator) ?></td>
        <td><?= (int)$c->delay_min ?> min</td>
        <td><?= (int)$c->computed_percent ?>%</td>
        <td><?= number_format((float)$c->payout_amount, 2) . ' ' . h($c->currency) ?></td>
        <td><?= h($c->created) ?></td>
        <td><?= $this->Html->link('Vis', ['action' => 'view', $c->id]) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
