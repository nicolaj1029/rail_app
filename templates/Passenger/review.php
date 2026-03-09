<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,mixed> $selectedContext */
$form = (array)($snapshot['form'] ?? []);
$nextStep = $snapshot['nextStep'] ?? null;
$steps = (array)($snapshot['steps'] ?? []);
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .grid { display: grid; grid-template-columns: 1.2fr .8fr; gap: 16px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .summary { display: grid; grid-template-columns: 140px 1fr; gap: 8px 12px; }
  .summary div { padding: 2px 0; }
  .muted { color: #64748b; }
  .cta { display: inline-block; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; }
  .steps { list-style: none; padding: 0; margin: 0; }
  .steps li { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
  .steps li:last-child { border-bottom: 0; }
  @media (max-width: 860px) { .grid { grid-template-columns: 1fr; } }
</style>

<div class="passenger-page">
  <h1>Trip Review</h1>
  <p class="muted">Samme flow-data som i den nuværende webapp, men læst som et review-overblik i stedet for ren stepper-navigation.</p>

  <div class="grid">
    <div class="card">
      <h2>Det vi har nu</h2>
      <?php if ($selectedContext !== []): ?>
        <p class="muted">Review-siden blev åbnet med valgt kontekst fra journeys/claims-listen.</p>
      <?php endif; ?>
      <div class="summary">
        <div>Status</div><div><strong><?= h((string)$snapshot['status']) ?></strong></div>
        <div>Sagsgrundlag</div><div><?= h((string)($form['ticket_upload_mode'] ?? 'Ikke valgt')) ?></div>
        <div>Operatør</div><div><?= h((string)($snapshot['operator'] ?: 'Mangler')) ?></div>
        <div>Produkt</div><div><?= h((string)($snapshot['product'] ?: 'Mangler / ikke relevant')) ?></div>
        <div>Fra</div><div><?= h((string)($form['dep_station_name'] ?? $form['from_station'] ?? 'Mangler')) ?></div>
        <div>Til</div><div><?= h((string)($form['arr_station_name'] ?? $form['to_station'] ?? 'Mangler')) ?></div>
        <div>Hændelse</div><div><?= h((string)($form['incident_main'] ?? 'Mangler')) ?></div>
        <div>Forsinkelse</div><div><?= h((string)($form['delay_minutes'] ?? 'Mangler')) ?></div>
        <?php if ($selectedContext !== []): ?>
          <div>Valgt kontekst</div><div><?= h((string)($selectedContext['route_label'] ?? $selectedContext['case_file'] ?? 'Ekstern liste')) ?></div>
        <?php endif; ?>
      </div>
      <?php if (is_array($nextStep)): ?>
        <a class="cta" href="<?= h($this->Url->build('/flow/' . $nextStep['action'])) ?>">Fortsæt ved næste relevante trin</a>
      <?php else: ?>
        <a class="cta" href="<?= h($this->Url->build('/flow/consent')) ?>">Fortsæt til slutningen</a>
      <?php endif; ?>
      <?php if ($selectedContext !== []): ?>
        <br>
        <a class="cta" href="<?= h($this->Url->build('/passenger/chat?' . http_build_query($selectedContext))) ?>">Åbn chat om denne sag</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Næste handling</h2>
      <?php if (is_array($nextStep)): ?>
        <p><strong>Trin <?= h((string)($nextStep['num'] ?? '')) ?>:</strong> <?= h((string)($nextStep['title'] ?? '')) ?></p>
        <p class="muted">Dette er næste synlige og ulåste trin i det eksisterende flow.</p>
      <?php else: ?>
        <p><strong>Ingen manglende trin fundet.</strong></p>
        <p class="muted">Sagen ser ud til at være fremme ved resultat eller afslutning.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <h2>Visible flow map</h2>
    <ul class="steps">
      <?php foreach ($steps as $step): ?>
        <li>
          <strong>Trin <?= h((string)($step['ui_num'] ?? $step['num'] ?? '')) ?>:</strong>
          <?= h((string)($step['title'] ?? '')) ?>
          — <?= h(($step['done'] ?? false) ? 'færdig' : (($step['unlocked'] ?? false) ? 'klar' : 'låst')) ?>
          <div class="muted"><a href="<?= h($this->Url->build('/flow/' . ($step['action'] ?? 'start'))) ?>">Åbn dette trin</a></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
