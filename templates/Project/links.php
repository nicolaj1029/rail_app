<?php
/** @var \App\View\AppView $this */
/**
 * @var array<int,array{
 *   name:string,
 *   links:array<int,array{title:string,href?:string,method:string,note?:string,desc?:string}>
 * }>
 */
?>
<style>
  .links-page { max-width: 1100px; margin: 0 auto; padding: 10px 16px 20px; }
  .lead { color:#444; margin: 6px 0 18px; }

  .group { margin: 28px 0; }
  .group h2 { margin: 0 0 14px; font-size: 20px; font-weight: 600; color:#1a1a1a; }

  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 14px; }
  .card { border: 1px solid #e6e6e6; border-radius: 10px; background: #fff; padding: 12px 14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .card .top { display:flex; align-items:center; gap:8px; }
  .method { display:inline-block; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 999px; color: #fff; letter-spacing:.2px; }
  .GET { background: #1aa06d; }
  .POST { background: #2a78d1; }
  .card .title { font-size: 16px; font-weight: 600; color:#222; }
  .card .title a { color: inherit; text-decoration: none; border-bottom: 1px dotted #bbb; }
  .card .title a:hover { border-bottom-color: #888; }
  .meta { margin-top: 8px; display:flex; flex-wrap:wrap; gap: 8px; align-items:center; }
  .chip { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; background: #f6f7f8; color:#333; border:1px solid #ececec; border-radius: 6px; padding: 3px 6px; font-size: 12px; }
  .note { color:#6a6a6a; font-size: 12px; }
  .desc { margin-top: 8px; color:#303030; font-size: 13px; line-height: 1.45; }

  /* Details area */
  .details { max-width: 1100px; margin: 0 auto; padding: 0 16px 40px; }
  .details h1, .details h2 { color:#1a1a1a; }
  .details ul { margin: 8px 0 14px 18px; }
  .details li { margin: 4px 0; }
  details { border:1px solid #eee; border-radius: 10px; padding: 10px 12px; background:#fff; margin: 10px 0; }
  details summary { cursor: pointer; font-weight:600; }
  pre { background:#0f172a; color:#e5e7eb; border-radius: 8px; padding: 10px 12px; overflow:auto; }
  pre code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
</style>

<div class="links-page">
  <h1>Test Links</h1>
  <p class="lead">Quick access to UI pages, demo analyzers, and JSON APIs. Base path assumed is this app. POST endpoints require a JSON body.</p>

  <?php /* absolute base not needed per-link; we'll build each href with UrlHelper */ ?>

  <?php foreach ($groups as $g): ?>
    <div class="group">
      <h2><?= h($g['name']) ?></h2>
      <div class="cards">
        <?php foreach ($g['links'] as $l): ?>
          <?php $hrefAbs = !empty($l['href']) ? $this->Url->build($l['href'], ['fullBase' => true]) : ''; ?>
          <div class="card">
            <div class="top">
              <span class="method <?= h($l['method']) ?>"><?= h($l['method']) ?></span>
              <div class="title">
                <?php if (!empty($l['href'])): ?>
                  <a href="<?= h($hrefAbs) ?>" target="_blank" rel="noopener"><?= h($l['title']) ?></a>
                <?php else: ?>
                  <?= h($l['title']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="meta">
              <?php if (!empty($l['href'])): ?><span class="chip"><?= h($hrefAbs) ?></span><?php endif; ?>
              <?php if (!empty($l['note'])): ?><span class="note">— <?= h($l['note']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($l['desc'])): ?>
              <div class="desc">
                <?= h($l['desc']) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<hr style="margin:28px 0;"/>

<div class="details">
  <h1>Detailed Guide & Examples</h1>
  <p>Use <strong>http://localhost/rail_app</strong> as the base (WAMP default). If your vhost differs, replace the base accordingly.</p>

  <h2>Client UI (upload/OCR flow and wizard)</h2>
  <ul>
    <li>
      <strong>Project documents</strong>
      <ul>
        <li>Forklaring (PDF): <a href="<?= h($this->Url->build('/project/forklaring', ['fullBase' => true])) ?>" target="_blank" rel="noopener">view</a> ·
          <a href="<?= h($this->Url->build('/project/text/forklaring', ['fullBase' => true])) ?>" target="_blank" rel="noopener">text</a> ·
          <a href="<?= h($this->Url->build('/project/annotate/forklaring', ['fullBase' => true])) ?>" target="_blank" rel="noopener">annotate</a></li>
        <li>Flow chart (PDF/SVG): <a href="<?= h($this->Url->build('/project/flowchart', ['fullBase' => true])) ?>" target="_blank" rel="noopener">view</a> ·
          <a href="<?= h($this->Url->build('/project/text/flowchart', ['fullBase' => true])) ?>" target="_blank" rel="noopener">text</a></li>
      </ul>
    </li>
    <li>
      <strong>Upload (OCR/fixtures entry)</strong>
      <ul>
        <li>GET: <a href="<?= h($this->Url->build('/upload', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/upload', ['fullBase' => true])) ?></a><br/>
          <em>Lets you upload a ticket file or paste a Journey JSON.</em></li>
        <li>POST: <?= h($this->Url->build('/upload/analyze', ['fullBase' => true])) ?><br/>
          <em>Triggered by the form above; computes Art. 12/9/18/19 and unified claim.</em><br/>
          <em>With USE_LIVE_APIS=true it will also fetch live delay (DB/SNCF) and show “Live forsinkelse…” at the top.</em></li>
      </ul>
    </li>
    <li><strong>Client Wizard (step-by-step claim flow)</strong>
      <ul>
  <li>Start: <a href="<?= h($this->Url->build('/wizard', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/wizard', ['fullBase' => true])) ?></a></li>
  <li>Questions: <a href="<?= h($this->Url->build('/wizard/questions', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/wizard/questions', ['fullBase' => true])) ?></a></li>
  <li>Expenses: <a href="<?= h($this->Url->build('/wizard/expenses', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/wizard/expenses', ['fullBase' => true])) ?></a></li>
  <li>Summary: <a href="<?= h($this->Url->build('/wizard/summary', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/wizard/summary', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>Claims (simple start/compute view)</strong>
      <ul>
  <li>Start: <a href="<?= h($this->Url->build('/claims', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/claims', ['fullBase' => true])) ?></a></li>
  <li>Compute (POST-only): <?= h($this->Url->build('/claims/compute', ['fullBase' => true])) ?></li>
      </ul>
    </li>
    <li><strong>Reimbursement demo</strong>
      <ul>
  <li>Start: <a href="<?= h($this->Url->build('/reimbursement', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/reimbursement', ['fullBase' => true])) ?></a></li>
  <li>Generate: <a href="<?= h($this->Url->build('/reimbursement/generate', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/reimbursement/generate', ['fullBase' => true])) ?></a></li>
  <li>Official: <a href="<?= h($this->Url->build('/reimbursement/official', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/reimbursement/official', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>Admin (no auth in this demo)</strong>
      <ul>
  <li>Claims list: <a href="<?= h($this->Url->build('/admin/claims', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/admin/claims', ['fullBase' => true])) ?></a></li>
        <li>View one: http://localhost/rail_app/admin/claims/view/1</li>
        <li>Update status (POST-only): http://localhost/rail_app/admin/claims/update-status/1</li>
        <li>Mark paid (POST-only): http://localhost/rail_app/admin/claims/mark-paid/1</li>
      </ul>
    </li>
  </ul>

  <details>
    <summary><strong>Fra upload til udbetaling</strong> — billetgenkendelse og flow</summary>
    <p>Denne app understøtter to veje: <em>Scenarier</em> (syntetiske, genereret i kode) og <em>Mock tickets</em> (rigtige filer fra en mappe) samt en <em>Upload</em>-side (OCR på din fil).</p>
    <ul>
      <li><strong>Scenarier</strong> (ingen filer): fast/shufflet datasæt til hurtig test. Se “Scenarier” længere nede.</li>
      <li><strong>Mock tickets</strong> (PDF/PNG/TXT): læs fra mappe, parse TXT og kør hele motoren:
        <ul>
          <li>Analyse: <a href="<?= h($this->Url->build('/api/demo/mock-tickets', ['fullBase' => true])) ?>" target="_blank" rel="noopener">/api/demo/mock-tickets</a></li>
          <li>Med RNE enrichment: <a href="<?= h($this->Url->build('/api/demo/mock-tickets?withRne=1', ['fullBase' => true])) ?>" target="_blank" rel="noopener">?withRne=1</a></li>
          <li>Vælg mappe: <code>/api/demo/mock-tickets?baseDir=C:%5Cwamp64%5Cwww%5Crail_app%5Cmocks%5Ctests%5Cfixtures</code></li>
        </ul>
      </li>
      <li><strong>Upload</strong> (OCR):
        <ul>
          <li>UI: <a href="<?= h($this->Url->build('/upload', ['fullBase' => true])) ?>" target="_blank" rel="noopener">/upload</a> — accepterer PDF/TXT; viser <em>OCR logs</em> og bruger AUTO-hooks i Art. 9(1).</li>
          <li>Pipeline (alt-i-en): <a href="<?= h($this->Url->build('/api/pipeline/run', ['fullBase' => true])) ?>" target="_blank" rel="noopener">/api/pipeline/run</a> (POST JSON)</li>
        </ul>
      </li>
    </ul>
    <p><strong>Status</strong> i forhold til “hurtig plan”:</p>
    <ul>
      <li>OCR af PDF/TXT: <span style="color:#1aa06d;">på plads</span> (Upload + OcrHeuristicsMapper).</li>
      <li>Art. 12/9/18/19 + claim: <span style="color:#1aa06d;">på plads</span>.</li>
      <li>RNE/operator-stubs: <span style="color:#1aa06d;">tilgængelig</span> (for demo/berigelse).</li>
      <li>2D-kode (Aztec/QR/PDF417, UIC/FCB): <span style="color:#c97a00;">backlog</span> (parse barcode → journey/meta).</li>
      <li>RICS slå-op (operatør-kode): <span style="color:#c97a00;">backlog</span> (kan tilføjes i OperatorCatalog).</li>
      <li>Anti-svindel (hash/signatur/EXIF): <span style="color:#c97a00;">backlog</span>.</li>
    </ul>
    <p class="note">Mock tickets er <em>ikke</em> OCR af billeder; de bruger en .txt pr. billet. Vil du OCR’e en grafisk billet (PDF/PNG/JPG), brug <strong>/upload</strong>.</p>
  </details>

  <h2>Demo/mocks and scenarios (ready to click)</h2>
  <ul>
    <li><strong>Fixtures listing (demo)</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/demo/fixtures', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/fixtures', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/demo/exemption-fixtures', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/exemption-fixtures', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/demo/art12-fixtures', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/art12-fixtures', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>Scenarier</strong>
      <ul>
        <li>
          <strong>Fast scenario (kendt korrekt)</strong>
          <ul>
            <li>Vis (eval): <a href="<?= h($this->Url->build('/api/demo/scenarios?withEval=1&seed=fixed&count=1', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/scenarios?withEval=1&seed=fixed&count=1', ['fullBase' => true])) ?></a></li>
            <li>Kør (hele motoren): <a href="<?= h($this->Url->build('/api/demo/run-scenarios?seed=fixed&id=sncf_png_through_ticket_ok', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/run-scenarios?seed=fixed&id=sncf_png_through_ticket_ok', ['fullBase' => true])) ?></a></li>
          </ul>
        </li>
        <li>
          <strong>Shufflet scenarier</strong>
          <ul>
            <li>Vis (eval, shufflet): <a href="<?= h($this->Url->build('/api/demo/scenarios?withEval=1', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/scenarios?withEval=1', ['fullBase' => true])) ?></a></li>
            <li>Kør (shufflet): <a href="<?= h($this->Url->build('/api/demo/run-scenarios', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/run-scenarios', ['fullBase' => true])) ?></a></li>
            <li class="note">Kør én bestemt fra den shufflet liste: <span class="chip">/api/demo/run-scenarios?id=&lt;id-fra-listen&gt;</span></li>
          </ul>
        </li>
      </ul>
      <div class="note">“Vis (eval)” viser kun listen med vurderinger. “Kør” udfører Art. 12/9/18/19 og claim.</div>
    </li>
    <li><strong>Analyze generated mock tickets</strong> (PDF/PNG/TXT under mocks/tests/fixtures)
      <ul>
  <li><a href="<?= h($this->Url->build('/api/demo/mock-tickets', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/mock-tickets', ['fullBase' => true])) ?></a></li>
  <li>With RNE enrichment: <a href="<?= h($this->Url->build('/api/demo/mock-tickets?withRne=1', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/mock-tickets?withRne=1', ['fullBase' => true])) ?></a></li>
  <li>Custom directory: <a href="<?= h($this->Url->build('/api/demo/mock-tickets?baseDir=C:%5Cwamp64%5Cwww%5Crail_app%5Cmocks%5Ctests%5Cfixtures', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/demo/mock-tickets?baseDir=C:%5Cwamp64%5Cwww%5Crail_app%5Cmocks%5Ctests%5Cfixtures', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
  </ul>

  <h2>Dokumentation</h2>
  <ul>
    <li>PDF-samling: <a href="<?= h($this->Url->build('/files/', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/files/', ['fullBase' => true])) ?></a></li>
  </ul>

  <h2>OCR/ingest API stub (for pipeline tests)</h2>
  <ul>
    <li><strong>Ticket ingest</strong> (stub; returns a structure you can expand later)
      <ul>
        <li>POST: http://localhost/rail_app/api/ingest/ticket</li>
        <li>Body (JSON or form) optional; returns { journey: {...}, logs: [...] }</li>
      </ul>
      <em>Tip: Use this until your OCR call is wired. After you plug in OCR, map the extracted fields into the Journey format used by Upload/Compute.</em>
    </li>
  </ul>

  <details>
    <summary><strong>Unified Pipeline</strong> — /api/pipeline/run</summary>
    <pre><code>{
  "text": "Cheapest fare shown. Alternatives shown. MCT respected. Wifi and Quiet zone. Train: ICE 123",
  "journey": {
    "segments": [
      { "operator": "DB", "trainCategory": "ICE", "country": "DE", "schedArr": "2025-10-11T19:00:00", "actArr": "2025-10-11T20:15:00" }
    ],
    "ticketPrice": { "value": "49.90 EUR" },
    "country": { "value": "DE" }
  },
  "art12_meta": { "through_ticket_disclosure": "Gennemgående" },
  "art9_meta": { "info_on_rights": "Delvist", "preinformed_disruption": "Nej" },
  "refusion_meta": { "downgrade_occurred": "Nej" },
  "compute": { "euOnly": true, "minPayout": 4.0 }
}</code></pre>
    <p class="note">Returnerer journey/meta/logs samt profile, art12, art9, compensation, refund, refusion, claim i ét svar.</p>
  </details>

  <h2>Compute APIs (Art 12/9/18/refund/claim/compensation)</h2>
  <p>All are POST endpoints. They accept JSON (application/json) or form data.</p>

  <details>
    <summary><strong>Compensation (EU Art. 19 rules)</strong> — /api/compute/compensation</summary>
    <pre><code>{
  "journey": {
    "segments": [
      { "schedArr": "2025-10-11T19:00:00", "actArr": "2025-10-11T20:15:00" }
    ],
    "ticketPrice": { "value": "49.90 EUR" },
    "country": { "value": "DE" },
    "operatorName": { "value": "DB" },
    "trainCategory": { "value": "ICE" }
  },
  "euOnly": true,
  "refundAlready": false,
  "knownDelayBeforePurchase": false,
  "extraordinary": false,
  "selfInflicted": false,
  "throughTicket": true,
  "minPayout": 4.0
}</code></pre>
  </details>

  <details>
    <summary><strong>Exemptions profile</strong> — /api/compute/exemptions</summary>
    <pre><code>{
  "journey": {
    "segments": [{ "country": "FR" }],
    "is_long_domestic": false,
    "is_international_inside_eu": true,
    "is_international_beyond_eu": false
  }
}</code></pre>
  </details>

  <details>
    <summary><strong>Art. 12 evaluator</strong> — /api/compute/art12</summary>
    <pre><code>{
  "journey": {
    "segments": [{ "operator": "DB", "country": "DE" }],
    "is_international_inside_eu": true
  },
  "meta": { "through_ticket_disclosure": "yes", "contract_type": "single" }
}</code></pre>
  </details>

  <details>
    <summary><strong>Art. 9 evaluator (information)</strong> — /api/compute/art9</summary>
    <pre><code>{
  "journey": { "segments": [{ "country": "DE", "operator": "DB", "trainCategory": "ICE" }] },
  "meta": {
    "info_before_purchase": "Delvist",
    "info_on_rights": "Delvist",
    "info_during_disruption": "Ved ikke",
    "language_accessible": "Ja",
    "accessible_formats_offered": "unknown",
    "preinformed_disruption": "Nej",
    "through_ticket_disclosure": "Gennemgående",
    "bike_reservation_type": "Ikke muligt",
    "bike_res_required": "Nej",
    "art9_precontract_ext": {
      "hooks": [
        { "hook": "multiple_fares_shown", "value": "Delvist" },
        { "hook": "cheapest_highlighted", "value": "Nej" },
        { "hook": "mct_realistic", "value": "Ja" },
        { "hook": "alts_shown_precontract", "value": "Ved ikke" }
      ]
    }
  }
}</code></pre>
    <p class="note">Tip: RNE enrichment toggles <code>station_board_updates</code> and bike hooks automatically via the Demo analyzer.</p>
  </details>

  <details>
  <summary><strong>Refund (Art. 18-like)</strong> — /api/compute/refund</summary>
    <pre><code>{
  "journey": { "segments": [{ "from": "München", "to": "Berlin" }] },
  "meta": { "refundAlready": false }
}</code></pre>
  </details>

  <details>
    <summary><strong>Refusion (rerouting, Art. 18)</strong> — /api/compute/refusion</summary>
    <pre><code>{
  "journey": { "segments": [{ "from": "Paris", "to": "Lyon", "operator": "SNCF", "trainCategory": "TGV" }] },
  "meta": {
    "claim_rerouting": true,
    "reroute_info_within_100min": "Nej",
    "meal_offered": "Nej",
    "alt_transport_provided": "Nej",
    "downgrade_occurred": "Ja",
    "downgrade_comp_basis": "class",
    "fare_class_purchased": "1",
    "class_delivered_status": "Lower",
    "reserved_amenity_delivered": "Nej",
    "promised_facilities": ["Wifi", "Quiet zone"]
  }
}</code></pre>
  </details>

  <details>
    <summary><strong>Unified claim calculation</strong> — /api/compute/claim</summary>
    <pre><code>{
  "country_code": "DE",
  "currency": "EUR",
  "ticket_price_total": 49.9,
  "trip": {
    "through_ticket": true,
    "legs": [
      {
        "from": "München",
        "to": "Berlin",
        "scheduled_dep": "2025-10-11T15:00:00",
        "scheduled_arr": "2025-10-11T19:00:00",
        "actual_arr": "2025-10-11T20:15:00"
      }
    ]
  },
  "disruption": {
    "delay_minutes_final": 75,
    "notified_before_purchase": false,
    "extraordinary": false,
    "self_inflicted": false
  },
  "choices": {
    "wants_refund": false,
    "wants_reroute_same_soonest": false,
    "wants_reroute_later_choice": false
  },
  "expenses": { "meals": 0, "hotel": 0, "alt_transport": 0, "other": 0 },
  "already_refunded": 0
}</code></pre>
  </details>

  <h2>Provider stubs (DB/SNCF/DSB/RNE/open)</h2>
  <ul>
    <li><strong>SNCF:</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/providers/sncf/booking/validate', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/sncf/booking/validate', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/providers/sncf/trains', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/sncf/trains', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/providers/sncf/realtime', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/sncf/realtime', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>Deutsche Bahn:</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/providers/db/lookup', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/db/lookup', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/providers/db/trip', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/db/trip', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/providers/db/realtime', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/db/realtime', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>DSB:</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/providers/dsb/trip', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/dsb/trip', ['fullBase' => true])) ?></a></li>
  <li><a href="<?= h($this->Url->build('/api/providers/dsb/realtime', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/dsb/realtime', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>RNE:</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/providers/rne/realtime', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/rne/realtime', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
    <li><strong>Open (generic):</strong>
      <ul>
  <li><a href="<?= h($this->Url->build('/api/providers/open/rt', ['fullBase' => true])) ?>" target="_blank" rel="noopener"><?= h($this->Url->build('/api/providers/open/rt', ['fullBase' => true])) ?></a></li>
      </ul>
    </li>
  </ul>

  <h2>Live train API toggle and testing (DB/SNCF)</h2>
  <ul>
    <li><strong>Toggle live data</strong>
      <ul>
        <li>Set environment variable <code>USE_LIVE_APIS=true</code> for your web server/PHP process.</li>
        <li>Optional vars:
          <ul>
            <li><code>DB_TRANSPORT_REST_BASE=https://v6.db.transport.rest</code></li>
            <li><code>SNCF_NAVITIA_BASE=https://api.sncf.com</code></li>
            <li><code>SNCF_API_KEY=your_key</code></li>
            <li><code>RNE_BASE_URL=http://localhost:5555/api/providers/rne</code></li>
          </ul>
        </li>
        <li>A sample file exists: <code>rail_app/.env.example</code> (environment variables—not auto-loaded; set them in your environment/Apache service).</li>
      </ul>
    </li>
    <li><strong>Where live delay is used now</strong>
      <ul>
        <li>The Upload flow (<code>/upload → analyze</code>) calls live APIs (when <code>USE_LIVE_APIS=true</code>).</li>
        <li>You’ll see a green line “Live forsinkelse fra API: N min” on the result page when live delay was applied.</li>
      </ul>
    </li>
    <li><strong>Minimal Journey JSON for DB live test</strong>
      <pre><code>{
  "segments": [
    {
      "operator": "DB",
      "trainNo": "ICE 123",
      "from": "München Hbf",
      "to": "Berlin Hbf",
      "schedDep": "2025-10-11T15:00:00",
      "schedArr": "2025-10-11T19:00:00"
    }
  ],
  "depDate": { "value": "2025-10-11" }
}</code></pre>
      <em>Paste that into the “Journey JSON” box on /upload with USE_LIVE_APIS=true to see the live delay override.</em>
    </li>
  </ul>

  <h2>Optional PowerShell one-liners (copyable)</h2>
  <details>
    <summary>POST JSON to a compute endpoint</summary>
    <pre><code class="language-powershell">$body = @{
  journey = @{
    segments = @(@{ schedArr = "2025-10-11T19:00:00"; actArr = "2025-10-11T20:15:00" })
    ticketPrice = @{ value = "49.90 EUR" }
    country = @{ value = "DE" }
    operatorName = @{ value = "DB" }
    trainCategory = @{ value = "ICE" }
  }
  euOnly = $true
} | ConvertTo-Json -Depth 6
Invoke-RestMethod -Method POST -Uri "http://localhost/rail_app/api/compute/compensation" -ContentType "application/json" -Body $body</code></pre>
  </details>

  <details>
    <summary>Upload a file to /upload/analyze</summary>
    <pre><code class="language-powershell">Invoke-WebRequest -Method POST -Uri "http://localhost/rail_app/upload/analyze" -InFile "C:\path\to\ticket.pdf" -ContentType "application/pdf"</code></pre>
  </details>

  <p style="margin-top:20px;">JSON vs form: All compute endpoints accept JSON bodies. The upload/analyze action is typically form/multipart from the page. If a link 404s, confirm your base URL (on WAMP it’s usually http://localhost/rail_app).</p>
</div>
