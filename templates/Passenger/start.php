<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $quickLinks */
$nextStep = $snapshot['nextStep'] ?? null;
$steps = (array)($snapshot['steps'] ?? []);
$journeysApi = $this->Url->build('/api/shadow/journeys', ['fullBase' => true]);
$casesApi = $this->Url->build('/api/shadow/cases', ['fullBase' => true]);
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
  .inline-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:10px; }
  .inline-form input { min-width: 240px; }
  .backend-list { display:grid; gap:10px; margin-top:12px; }
  .backend-item { border:1px solid #e5e7eb; border-radius:10px; padding:12px; }
</style>

<div class="passenger-page" id="passenger-v2-root"
     data-journeys-api="<?= h($journeysApi) ?>"
     data-cases-api="<?= h($casesApi) ?>"
     data-chat-url="<?= h($this->Url->build('/passenger/chat', ['fullBase' => true])) ?>"
     data-review-url="<?= h($this->Url->build('/passenger/review', ['fullBase' => true])) ?>"
     data-trips-url="<?= h($this->Url->build('/passenger/trips', ['fullBase' => true])) ?>">
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
      <br>
      <a class="cta secondary" href="<?= h($this->Url->build('/passenger/trips')) ?>">Åbn journeys-liste</a>
    </div>

    <div class="card">
      <h2>Pendler</h2>
      <p class="muted">Små forsinkelser og data-pack/claim-assist først. Samme season-pass motor som i flowet.</p>
      <a class="cta" href="<?= h($quickLinks['commuter']) ?>">Åbn pendler-mode</a>
      <br>
      <a class="cta secondary" href="<?= h($this->Url->build('/flow/entitlements')) ?>">Gå til billet / season setup</a>
      <br>
      <a class="cta secondary" href="<?= h($this->Url->build('/passenger/chat')) ?>">Åbn passager-chat</a>
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

  <div class="grid" style="margin-top:16px;">
    <div class="card">
      <h2>Backend journeys</h2>
      <p class="muted">Bruger samme shadow journeys som mobil-appen. Indtast et device-id for at hente rigtige journeys.</p>
      <div class="inline-form">
        <label for="passenger-device-id">Device ID</label>
        <input id="passenger-device-id" type="text" placeholder="fx stationtest">
        <button type="button" id="load-passenger-journeys">Hent journeys</button>
        <a class="cta secondary" href="<?= h($this->Url->build('/passenger/trips')) ?>">Fuldt journeys-view</a>
      </div>
      <div id="passenger-journeys-list" class="backend-list"><div class="muted">Ingen journeys hentet endnu.</div></div>
    </div>

    <div class="card">
      <h2>Backend claims</h2>
      <p class="muted">Liste over rigtige indsendte shadow cases i backend.</p>
      <div class="inline-form">
        <button type="button" id="load-passenger-cases">Hent claims</button>
        <a class="cta secondary" href="<?= h($this->Url->build('/passenger/claims')) ?>">Åbn claims-view</a>
      </div>
      <div id="passenger-cases-list" class="backend-list"><div class="muted">Ingen claims hentet endnu.</div></div>
    </div>
  </div>
</div>

<script>
(() => {
  const root = document.getElementById('passenger-v2-root');
  if (!root) return;

  const journeysApi = root.dataset.journeysApi || '';
  const casesApi = root.dataset.casesApi || '';
  const chatUrl = root.dataset.chatUrl || '';
  const reviewUrl = root.dataset.reviewUrl || '';
  const tripsUrl = root.dataset.tripsUrl || '';
  const deviceInput = document.getElementById('passenger-device-id');
  const journeysList = document.getElementById('passenger-journeys-list');
  const casesList = document.getElementById('passenger-cases-list');

  if (deviceInput) {
    deviceInput.value = localStorage.getItem('passenger.device_id') || '';
  }

  const esc = (value) => {
    const span = document.createElement('span');
    span.textContent = value == null ? '' : String(value);
    return span.innerHTML;
  };

  const renderJourneys = (journeys) => {
    if (!journeysList) return;
    if (!Array.isArray(journeys) || journeys.length === 0) {
      journeysList.innerHTML = '<div class="muted">Ingen journeys fundet for dette device-id.</div>';
      return;
    }
    journeysList.innerHTML = journeys.slice(0, 3).map((journey) => {
      const params = new URLSearchParams({
        journey_id: journey.id || '',
        route_label: journey.route_label || '',
        status: journey.status || '',
        dep_station: journey.dep_station || '',
        arr_station: journey.arr_station || '',
        delay_minutes: journey.delay_minutes == null ? '' : String(journey.delay_minutes),
      });
      return `
        <div class="backend-item">
          <strong>${esc(journey.route_label || 'Journey')}</strong><br>
          <span class="muted">Status: ${esc(journey.status || 'ukendt')}</span><br>
          <span class="muted">Tid: ${esc(journey.dep_time || '')} → ${esc(journey.arr_time || '')}</span><br>
          <div style="margin-top:8px;">
            <a href="${reviewUrl}?${params.toString()}">Review</a> ·
            <a href="${chatUrl}?${params.toString()}">Chat</a>
          </div>
        </div>`;
    }).join('') + `<div><a href="${tripsUrl}">Se alle journeys</a></div>`;
  };

  const renderCases = (cases) => {
    if (!casesList) return;
    if (!Array.isArray(cases) || cases.length === 0) {
      casesList.innerHTML = '<div class="muted">Ingen claims fundet endnu.</div>';
      return;
    }
    casesList.innerHTML = cases.slice(0, 3).map((item) => {
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
          <span class="muted">Fil: ${esc(item.file || '')}</span><br>
          <div style="margin-top:8px;">
            <a href="${reviewUrl}?${params.toString()}">Review</a> ·
            <a href="${chatUrl}?${params.toString()}">Chat</a>
          </div>
        </div>`;
    }).join('');
  };

  document.getElementById('load-passenger-journeys')?.addEventListener('click', async () => {
    const deviceId = (deviceInput?.value || '').trim();
    if (!deviceId) {
      journeysList.innerHTML = '<div class="muted">Indtast først et device-id.</div>';
      return;
    }
    localStorage.setItem('passenger.device_id', deviceId);
    journeysList.innerHTML = '<div class="muted">Henter journeys…</div>';
    const response = await fetch(`${journeysApi}?device_id=${encodeURIComponent(deviceId)}`);
    const payload = await response.json();
    renderJourneys(payload?.data?.journeys || []);
  });

  document.getElementById('load-passenger-cases')?.addEventListener('click', async () => {
    casesList.innerHTML = '<div class="muted">Henter claims…</div>';
    const response = await fetch(casesApi);
    const payload = await response.json();
    renderCases(payload?.data?.cases || []);
  });
})();
</script>
