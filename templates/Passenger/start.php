<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $quickLinks */
$nextStep = $snapshot['nextStep'] ?? null;
$steps = (array)($snapshot['steps'] ?? []);
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .hero { border: 1px solid #dbeafe; background: #eff6ff; border-radius: 16px; padding: 20px; margin-bottom: 16px; }
  .hero h1 { margin: 0 0 8px; }
  .hero p { margin: 0; color: #334155; line-height: 1.5; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .card h2, .card h3 { margin-top: 0; }
  .muted { color: #64748b; }
  .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: #0f172a; font-size: 12px; font-weight: 600; }
  .cta { display: inline-block; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; }
  .cta.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
  .list { margin: 0; padding-left: 18px; }
  .step-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
  .step-chip { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 6px 10px; font-size: 13px; }
</style>

<div class="passenger-page">
  <div class="hero">
    <span class="pill">Alternativ web-oplevelse</span>
    <h1>Passenger V2</h1>
    <p>Dette er et alternativt passagerlag oven på den eksisterende flow-motor. Det gamle flow er bevaret urørt. Her reorganiseres oplevelsen efter det, der fungerede bedst i mobil-appen: review først, pendler-flow som egen genvej og hjælp i kontekst.</p>
  </div>

  <div class="grid" style="margin-bottom:16px;">
    <div class="card">
      <h2>Aktuel sag</h2>
      <p><strong>Status:</strong> <?= h((string)$snapshot['status']) ?></p>
      <p><strong>Mode:</strong> <?= h($snapshot['mode'] === 'commuter' ? 'Pendler / season pass' : 'Standard') ?></p>
      <p><strong>Operatør:</strong> <?= h((string)($snapshot['operator'] ?: 'Ikke valgt endnu')) ?></p>
      <p><strong>Rute:</strong> <?= h((string)($snapshot['route'] ?: 'Ikke udfyldt endnu')) ?></p>
      <p><strong>Progression:</strong> <?= (int)$snapshot['completedVisible'] ?>/<?= (int)$snapshot['visibleTotal'] ?> synlige trin færdige</p>
      <?php if (is_array($nextStep)): ?>
        <a class="cta" href="<?= h($this->Url->build('/flow/' . $nextStep['action'])) ?>">Fortsæt ved trin <?= h((string)($nextStep['num'] ?? '')) ?></a>
      <?php else: ?>
        <a class="cta" href="<?= h($quickLinks['flowCompensation']) ?>">Se resultat</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Hurtig start</h2>
      <p class="muted">Samme backend som i dag. Kun indgangen er enklere.</p>
      <a class="cta" href="<?= h($quickLinks['flowStart']) ?>">Start ny sag</a>
      <br>
      <a class="cta secondary" href="<?= h($quickLinks['review']) ?>">Åbn review-side</a>
      <br>
      <a class="cta secondary" href="<?= h($quickLinks['claims']) ?>">Se claims-status</a>
    </div>

    <div class="card">
      <h2>Pendler</h2>
      <p class="muted">Små forsinkelser og data-pack/claim-assist først. Samme season-pass motor som i flowet.</p>
      <a class="cta" href="<?= h($quickLinks['commuter']) ?>">Åbn pendler-mode</a>
      <br>
      <a class="cta secondary" href="<?= h($this->Url->build('/flow/entitlements')) ?>">Gå til billet / season setup</a>
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Hvad løftes ind fra mobil-appen</h3>
      <ul class="list">
        <li>Review først i stedet for ren stepper først.</li>
        <li>Pendler og standard i samme produkt, men med forskellig entry.</li>
        <li>Statusfaser: live, review, submitted, completed.</li>
        <li>Claims og hjælp i kontekst, ikke generisk navigation.</li>
      </ul>
    </div>

    <div class="card">
      <h3>Originalt flow bevares</h3>
      <ul class="list">
        <li><a href="<?= h($quickLinks['flowStart']) ?>">/flow/start</a> er stadig baseline.</li>
        <li>Alle eksisterende evaluators og session keys genbruges.</li>
        <li>Passenger V2 er kun et nyt UI-lag.</li>
      </ul>
    </div>

    <div class="card">
      <h3>Synlige trin nu</h3>
      <div class="step-list">
        <?php foreach ($steps as $step): ?>
          <span class="step-chip">
            Trin <?= h((string)($step['ui_num'] ?? $step['num'] ?? '')) ?> · <?= h((string)($step['title'] ?? '')) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
