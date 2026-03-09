<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $claimLinks */
$nextStep = $snapshot['nextStep'] ?? null;
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .cta { display: inline-block; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; }
  .cta.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
  .muted { color: #64748b; }
</style>

<div class="passenger-page">
  <h1>Claims & status</h1>
  <p class="muted">Alternativ claims-side, stadig bundet til det eksisterende web-flow. Formålet er at give et mere produkt-orienteret overblik, ikke at erstatte den nuværende motor.</p>

  <div class="grid">
    <div class="card">
      <h2>Aktuel claim-status</h2>
      <p><strong>Status:</strong> <?= h((string)$snapshot['status']) ?></p>
      <p><strong>Mode:</strong> <?= h($snapshot['mode'] === 'commuter' ? 'Pendler / season pass' : 'Standard') ?></p>
      <?php if (is_array($nextStep)): ?>
        <p><strong>Næste trin:</strong> <?= h((string)($nextStep['title'] ?? '')) ?></p>
        <a class="cta" href="<?= h($this->Url->build('/flow/' . $nextStep['action'])) ?>">Fortsæt sag</a>
      <?php else: ?>
        <p><strong>Claim-motoren har ikke flere åbne trin.</strong></p>
        <a class="cta" href="<?= h($claimLinks['consent']) ?>">Se afslutning</a>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Claims handlinger</h2>
      <a class="cta" href="<?= h($claimLinks['compensation']) ?>">Åbn resultat</a>
      <br>
      <a class="cta secondary" href="<?= h($claimLinks['applicant']) ?>">Ansøger & udbetaling</a>
      <br>
      <a class="cta secondary" href="<?= h($claimLinks['consent']) ?>">Samtykke & indsendelse</a>
    </div>

    <div class="card">
      <h2>Eksterne / støtteflader</h2>
      <a class="cta secondary" href="<?= h($claimLinks['reimbursement']) ?>">Reimbursement side</a>
      <br>
      <a class="cta secondary" href="<?= h($claimLinks['shadowCases']) ?>" target="_blank">Shadow cases API</a>
    </div>
  </div>
</div>
