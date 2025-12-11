<?php
/** @var \App\View\AppView $this */
?>
<style>
  .qa-page { max-width: 960px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  h1, h2 { color: #111; margin-bottom: 6px; }
  p { color: #333; line-height: 1.45; }
  ul { margin: 6px 0 12px 18px; }
  li { margin: 4px 0; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
  .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
  .card h3 { margin: 0 0 8px; font-size: 16px; color: #111; }
  .card a { color: #0a6fd8; text-decoration: none; }
  .card a:hover { text-decoration: underline; }
  code { background: #f4f4f5; padding: 1px 4px; border-radius: 4px; }
  .note { color: #555; font-size: 13px; }
</style>

<div class="qa-page">
  <h1>Mobile QA (Flutter + shadow tracking)</h1>
  <p>Overblik for begyndere: links til API tests, app-run kommando og hvad du skal gøre i hvilken rækkefølge.</p>

  <h2>Start appen (Android/iOS)</h2>
  <div class="card">
    <h3>Flutter run</h3>
    <p>Kør fra <code>mobile/</code> mappen (ellers får du “No pubspec.yaml”):</p>
    <code>cd mobile<br>flutter run --dart-define=API_BASE_URL=<?= h($baseUrl) ?></code>
    <p class="note">Forklaring: <code>--dart-define</code> sætter <code>API_BASE_URL</code> ind i appen, så telefonen kalder dit backend-host (PC IP). Skift IP efter behov.</p>
  </div>

  <h2>Hvad kan du i appen?</h2>
  <ul>
    <li><strong>Live Assist</strong>: Start/stop tracking (GPS-pings til backend), log “Offers” (mad/hotel/transport), log “Self-paid” udgifter og status (stranded/aflyst). Knap “Se rejser / Case Close” åbner rejseoversigt.</li>
    <li><strong>Se rejser</strong>: Liste fra <code>/api/shadow/journeys</code> for dit device. Åbn en rejse i Case Close.</li>
    <li><strong>Case Close (6 trin)</strong>: Stepper der samler data efter rejsen:
      <ol>
        <li>Bekræft rejseoplysninger</li>
        <li>Vælg hændelse (delay/aflysning/missed connection)</li>
        <li>Upload bilag (scan/upload, rediger felter)</li>
        <li>Assistance (mad/hotel/transport, selvbetalt/operatør)</li>
        <li>Kompensation (refusion/omlægning/voucher + billetpris)</li>
        <li>Gennemse og indsend (submit er stub i denne version)</li>
      </ol>
    </li>
  </ul>

  <h2>Shadow endpoints (hurtig test)</h2>
  <div class="cards">
    <div class="card">
      <h3>Registrer device</h3>
      <a href="<?= h($this->Url->build('/api/shadow/devices/register', ['fullBase' => true])) ?>" target="_blank">/api/shadow/devices/register</a>
      <div class="note">POST/GET → får et <code>device_id</code>.</div>
    </div>
    <div class="card">
      <h3>Send pings</h3>
      <p>POST JSON til:</p>
      <code>/api/shadow/pings</code>
      <div class="note">Body: <code>{ "device_id": "...", "batch": [ { "t": "...", "lat": 55.0, "lon": 12.0, "speed_kmh": 80 } ] }</code></div>
    </div>
    <div class="card">
      <h3>Journeys liste</h3>
      <a href="<?= h($this->Url->build('/api/shadow/journeys', ['fullBase' => true])) ?>?device_id=YOUR_ID" target="_blank">/api/shadow/journeys?device_id=...</a>
      <div class="note">Viser detekterede rejser for en enhed.</div>
    </div>
    <div class="card">
      <h3>Confirm journey</h3>
      <p>POST: <code>/api/shadow/journeys/{id}/confirm</code></p>
      <div class="note">Opretter sag (stub) og markerer journey som confirmed.</div>
    </div>
  </div>

  <h2>Hurtig test-guide</h2>
  <ol>
    <li>Åbn siden <code>/api/shadow/devices/register</code> for at se, at backend svarer (200 JSON).</li>
    <li>Kør <code>flutter run --dart-define=API_BASE_URL=<?= h($baseUrl) ?></code> og åbn appen på telefonen.</li>
    <li>I appen: tryk "Start tracking" → backend bør modtage pings.</li>
    <li>Tryk "Se rejser / Case Close" → du ser journeys fra backend; åbner stepper med 6 trin (Case Close).</li>
  </ol>

  <h2>Case Close stepper (6 trin)</h2>
  <ul>
    <li>1) Bekræft rejse</li>
    <li>2) Vælg hændelse (delay/aflysning/missed connection)</li>
    <li>3) Upload bilag (scan/upload; kan rettes)</li>
    <li>4) Assistance (mad/hotel/transport, selvbetalt/operatør)</li>
    <li>5) Kompensation (refusion/omlægning/voucher) + pris</li>
    <li>6) Gennemse og indsend (stub-submit)</li>
  </ul>

  <h2>Links til flow QA (web)</h2>
  <p>Web-flow QA ligger her: <a href="<?= h($this->Url->build('/project/flow-qa', ['fullBase' => true])) ?>" target="_blank">/project/flow-qa</a></p>
</div>
