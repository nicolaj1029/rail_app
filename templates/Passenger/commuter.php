<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,mixed>|null $coverage */
$coverageRows = is_array($coverage) ? array_slice($coverage, 0, 8, true) : [];
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .cta { display: inline-block; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; }
  .cta.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
  th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
  th { background: #f8fafc; }
  .muted { color: #64748b; }
</style>

<div class="passenger-page">
  <h1>Pendler / season pass</h1>
  <p class="muted">Denne side er lavet til pendlere og abonnementer. Den bruger den samme season-pass logik som resten af flowet, men samler det vigtigste ét sted: fast operatør, produkt, små forsinkelser og hurtig adgang til data-pack/resultat.</p>

  <div class="grid">
    <div class="card">
      <h2>Hvorfor et særskilt pendler-spor</h2>
      <ul>
        <li>Små forsinkelser kan stadig være relevante.</li>
        <li>Resultat og data-pack bør kunne åbnes tidligt.</li>
        <li>Data-pack/claim-assist er ofte vigtigere end instant payout.</li>
        <li>Fast rute og produkt kan genbruges på tværs af sager.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Din pendler-status</h2>
      <p><strong>Mode:</strong> <?= h($snapshot['mode'] === 'commuter' ? 'Pendler aktiv' : 'Ikke sat til pendler endnu') ?></p>
      <p><strong>Operatør:</strong> <?= h((string)($snapshot['operator'] ?: 'Mangler')) ?></p>
      <p><strong>Produkt:</strong> <?= h((string)($snapshot['product'] ?: 'Mangler')) ?></p>
      <a class="cta" href="<?= h($this->Url->build('/flow/entitlements')) ?>">Udfyld season-pass setup</a>
      <br>
      <a class="cta secondary" href="<?= h($this->Url->build('/flow/compensation')) ?>">Åbn resultat / data-pack</a>
    </div>
  </div>

  <?php if ($coverageRows !== []): ?>
    <div class="card" style="margin-top:16px;">
      <h2>Dækning i policy-matrix</h2>
      <p class="muted">Dette er bagvedliggende dækning for season-pass regler. Det er primært nyttigt til QA og operatørdækning, men det viser også hvor verified rules findes.</p>
      <table>
        <thead>
          <tr>
            <th>Land</th>
            <th>Policies</th>
            <th>Verified</th>
            <th>Links</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($coverageRows as $cc => $row): $row = (array)$row; ?>
            <tr>
              <td><?= h((string)$cc) ?></td>
              <td><?= (int)($row['season_policies'] ?? 0) ?></td>
              <td><?= (int)($row['verified'] ?? 0) ?></td>
              <td><?= (int)($row['with_links'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
