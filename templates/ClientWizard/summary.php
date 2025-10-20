<?php
/** @var \App\View\AppView $this */
/** @var array $state */
/** @var array $calc */
?>
<div class="content">
  <h1>Opsummering</h1>
  <p>Gennemse beregningen og bekræft overdragelsen så vi kan udbetale.</p>
  <h3>Totals</h3>
  <ul>
    <li>Billetpris (basis): <?= h($state['journey']['ticketPrice']['value'] ?? '–') ?></li>
    <li>Forsinkelse anvendt: <?= h((string)($state['answers']['delay_minutes_final'] ?? 0)) ?> min</li>
  <li>Art. 19 aktiv? <?= isset($profile['articles']['art19']) ? ($profile['articles']['art19'] ? 'Ja' : 'Nej (exempt)') : (isset($state['profile']['articles']['art19']) ? ($state['profile']['articles']['art19'] ? 'Ja' : 'Nej (exempt)') : 'ukendt') ?></li>
    <li>Refund (Art. 18): <?= h(number_format((float)($calc['breakdown']['refund']['amount'] ?? 0), 2)) ?> <?= h($calc['totals']['currency'] ?? 'EUR') ?></li>
    <li>Kompensation (Art. 19): <?= h(number_format((float)($calc['breakdown']['compensation']['amount'] ?? 0), 2)) ?> (<?= h(($calc['breakdown']['compensation']['pct'] ?? 0) . '%') ?>)</li>
    <li>Kompensation regel: <?= h($calc['breakdown']['compensation']['source'] ?? 'eu') ?><?= isset($calc['breakdown']['compensation']['notes']) && $calc['breakdown']['compensation']['notes'] !== '' ? ' — ' . h($calc['breakdown']['compensation']['notes']) : '' ?></li>
    <li>Udgifter (Art. 20): <?= h(number_format((float)($calc['breakdown']['expenses']['total'] ?? 0), 2)) ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($calc['totals']['service_fee_amount'] ?? 0), 2)) ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($calc['totals']['net_to_client'] ?? 0), 2)) ?></strong></li>
  </ul>
  <?php if (isset($profile['articles']['art19']) && !$profile['articles']['art19']): ?>
    <div class="message warning" style="background:#fff3cd;border:1px solid #ffeeba;padding:10px;margin:10px 0;">
      EU-kompensation (Art. 19) er undtaget for den valgte tjeneste/land. Vi forsøger automatisk national/operatør-ordning hvor relevant.
    </div>
  <?php endif; ?>
  <details>
    <summary>Detaljer</summary>
    <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($calc, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </details>

  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Dine oplysninger</legend>
      <?= $this->Form->control('name', ['label' => 'Navn']) ?>
      <?= $this->Form->control('email', ['label' => 'Email']) ?>
      <label style="display:block;margin-top:10px;">
        <input type="checkbox" name="assignment_accepted" value="1"> Jeg accepterer overdragelse af kravet og udbetaling netto af 25% fee.
      </label>
    </fieldset>
    <?= $this->Form->button('Bekræft og send') ?>
  <?= $this->Form->end() ?>
</div>
