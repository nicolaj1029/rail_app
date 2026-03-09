<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,string> $apiLinks */
?>
<style>
  .passenger-page { max-width: 1080px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin: 12px 0 16px; }
  .journey-list { display: grid; gap: 12px; }
  .journey-card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; background: #fff; }
  .muted { color: #64748b; }
</style>

<div class="passenger-page" id="passenger-trips-root"
     data-journeys-api="<?= h($apiLinks['journeys']) ?>"
     data-chat-url="<?= h($apiLinks['chat']) ?>"
     data-review-url="<?= h($apiLinks['review']) ?>">
  <h1>Journeys</h1>
  <p class="muted">Her ser du rejser fra den samme journey-kilde som mobil-appen. Brug listen til hurtigt at åbne review eller chat for en konkret rejse.</p>

  <div class="card">
    <div class="toolbar">
      <label for="device-id">Device ID</label>
      <input id="device-id" type="text" placeholder="fx stationtest">
      <button type="button" id="load-journeys">Hent journeys</button>
      <a href="<?= h($this->Url->build('/passenger/start')) ?>">Tilbage til passager-start</a>
    </div>
    <div id="journey-results" class="journey-list">
      <div class="muted">Ingen rejser hentet endnu.</div>
    </div>
  </div>
</div>

<script>
(() => {
  const root = document.getElementById('passenger-trips-root');
  if (!root) return;
  const api = root.dataset.journeysApi || '';
  const chatUrl = root.dataset.chatUrl || '';
  const reviewUrl = root.dataset.reviewUrl || '';
  const input = document.getElementById('device-id');
  const results = document.getElementById('journey-results');

  const esc = (value) => {
    const span = document.createElement('span');
    span.textContent = value == null ? '' : String(value);
    return span.innerHTML;
  };

  if (input) {
    input.value = localStorage.getItem('passenger.device_id') || '';
  }

  const render = (journeys) => {
    if (!Array.isArray(journeys) || journeys.length === 0) {
      results.innerHTML = '<div class="muted">Ingen journeys fundet.</div>';
      return;
    }
    results.innerHTML = journeys.map((journey) => {
      const params = new URLSearchParams({
        journey_id: journey.id || '',
        route_label: journey.route_label || '',
        status: journey.status || '',
        dep_station: journey.dep_station || '',
        arr_station: journey.arr_station || '',
        delay_minutes: journey.delay_minutes == null ? '' : String(journey.delay_minutes),
      });
      return `
        <div class="journey-card">
          <strong>${esc(journey.route_label || 'Journey')}</strong><br>
          <span class="muted">Status: ${esc(journey.status_label || journey.status || 'ukendt')}</span><br>
          <span class="muted">Fra ${esc(journey.dep_station || '')} til ${esc(journey.arr_station || '')}</span><br>
          <span class="muted">Tid: ${esc(journey.dep_time || '')} -> ${esc(journey.arr_time || '')}</span><br>
          <div style="margin-top:8px;">
            <a href="${reviewUrl}?${params.toString()}">Review</a> ·
            <a href="${chatUrl}?${params.toString()}">Chat</a> ·
            <a href="<?= h($this->Url->build('/flow/start')) ?>">Flow</a>
          </div>
        </div>`;
    }).join('');
  };

  document.getElementById('load-journeys')?.addEventListener('click', async () => {
    const deviceId = (input?.value || '').trim();
    if (!deviceId) {
      results.innerHTML = '<div class="muted">Indtast et device-id først.</div>';
      return;
    }
    localStorage.setItem('passenger.device_id', deviceId);
    results.innerHTML = '<div class="muted">Henter journeys...</div>';
    const response = await fetch(`${api}?device_id=${encodeURIComponent(deviceId)}`);
    const payload = await response.json();
    render(payload?.data?.journeys || []);
  });
})();
</script>
