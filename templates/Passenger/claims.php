<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $claimLinks */
$nextStep = $snapshot['nextStep'] ?? null;
$casesApi = $this->Url->build('/api/shadow/cases', ['fullBase' => true]);
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .cta { display: inline-block; margin-top: 10px; padding: 9px 12px; border-radius: 10px; background: #0f172a; color: #fff; text-decoration: none; }
  .cta.secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
  .muted { color: #64748b; }
  .backend-list { display: grid; gap: 10px; margin-top: 12px; }
  .backend-item { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
</style>

<div class="passenger-page" id="passenger-claims-root"
     data-cases-api="<?= h($casesApi) ?>"
     data-chat-url="<?= h($this->Url->build('/passenger/chat', ['fullBase' => true])) ?>"
     data-review-url="<?= h($this->Url->build('/passenger/review', ['fullBase' => true])) ?>">
  <h1>Claims & status</h1>
  <p class="muted">Her samles dine kladder, reviews og indsendte claims i et enklere overblik. Den samme motor bruges stadig bagved.</p>

  <div class="grid">
    <div class="card">
      <h2>Aktuel claim-status</h2>
      <p><strong>Status:</strong> <?= h((string)$snapshot['status']) ?></p>
      <p><strong>Type:</strong> <?= h($snapshot['mode'] === 'commuter' ? 'Pendler / season pass' : 'Standard') ?></p>
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
      <h2>Andre værktøjer</h2>
      <a class="cta secondary" href="<?= h($claimLinks['reimbursement']) ?>">Reimbursement side</a>
      <br>
      <a class="cta secondary" href="<?= h($claimLinks['shadowCases']) ?>" target="_blank">Shadow cases API</a>
    </div>
  </div>

  <div class="card" style="margin-top:16px;">
    <h2>Indsendte claims i backend</h2>
    <p class="muted">Denne liste kommer direkte fra backend-filerne i <code>tmp/shadow_cases</code>.</p>
    <div style="margin:8px 0 12px;">
      <button type="button" id="load-real-claims">Hent claims</button>
    </div>
    <div id="real-claims-list" class="backend-list"><div class="muted">Ingen claims hentet endnu.</div></div>
  </div>
</div>

<script>
(() => {
  const root = document.getElementById('passenger-claims-root');
  if (!root) return;
  const api = root.dataset.casesApi || '';
  const chatUrl = root.dataset.chatUrl || '';
  const reviewUrl = root.dataset.reviewUrl || '';
  const list = document.getElementById('real-claims-list');

  const esc = (value) => {
    const span = document.createElement('span');
    span.textContent = value == null ? '' : String(value);
    return span.innerHTML;
  };

  const render = (cases) => {
    if (!Array.isArray(cases) || cases.length === 0) {
      list.innerHTML = '<div class="muted">Ingen claims fundet.</div>';
      return;
    }
    list.innerHTML = cases.map((item) => {
      const params = new URLSearchParams({
        case_file: item.file || '',
        route_label: item.route_label || '',
        status: item.status || '',
        dep_station: item.dep_station || '',
        arr_station: item.arr_station || '',
        delay_minutes: item.delay_minutes == null ? '' : String(item.delay_minutes),
        ticket_mode: item.ticket_type || '',
      });
      return `
        <div class="backend-item">
          <strong>${esc(item.route_label || item.file || 'Claim')}</strong><br>
          <span class="muted">Status: ${esc(item.status || 'submitted')}</span><br>
          <span class="muted">Indsendt: ${esc(item.submitted_at || item.modified || '')}</span><br>
          <div style="margin-top:8px;">
            <a href="${reviewUrl}?${params.toString()}">Review</a> ·
            <a href="${chatUrl}?${params.toString()}">Chat</a>
          </div>
        </div>`;
    }).join('');
  };

  document.getElementById('load-real-claims')?.addEventListener('click', async () => {
    list.innerHTML = '<div class="muted">Henter claims...</div>';
    const response = await fetch(api);
    const payload = await response.json();
    render(payload?.data?.cases || []);
  });
})();
</script>
