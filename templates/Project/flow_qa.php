<?php
/** @var \App\View\AppView $this */
?>
<style>
  .qa-page { max-width: 960px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  h1, h2 { color: #111; margin-bottom: 6px; }
  p { color: #333; line-height: 1.45; }
  ul { margin: 6px 0 12px 18px; }
  li { margin: 4px 0; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 12px; }
  .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .card h3 { margin: 0 0 8px; font-size: 16px; color: #111; }
  .card a { color: #0a6fd8; text-decoration: none; }
  .card a:hover { text-decoration: underline; }
  .note { color: #555; font-size: 13px; }
  code { background: #f4f4f5; padding: 1px 4px; border-radius: 4px; }
</style>

<div class="qa-page">
  <h1>Flow QA (trin-test for split steps)</h1>
  <p>Hurtige links og en simpel "how-to" til at teste split-steps flowet. Fokus er trin 1-6. Passer til begyndere.</p>

  <h2>Sådan tester du trin for trin</h2>
  <ol>
    <li>Åbn hovedflowet (split steps) og kør igennem trin 1-6: <a href="<?= h($this->Url->build('/flow/start', ['fullBase' => true])) ?>" target="_blank">/flow/start</a>.</li>
    <li>Når du er færdig, dump sessionen som fixture-skelet: <a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1</a> (kopiér JSON).<br>
      <span class="note">Enriched: <a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1&enriched=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1&enriched=1</a> (journey+segments+step 3 ekstra).</span>
    </li>
    <li>Gem JSON'en i <code>tests/fixtures/demo/</code> som fx <code>my_case.json</code> (version=2). Nu dukker den op i v2-listen.</li>
    <li>Kør alle v2-fixtures med eval: <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?withEval=1', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/scenarios?withEval=1</a> eller testrunneren: <a href="<?= h($this->Url->build('/api/demo/v2/run-scenarios', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/run-scenarios</a>.</li>
    <li>Se evt. diff/match i JSON-responsen. Gentag med nye fixtures efter ændringer.</li>
  </ol>

  <h2>Direkte trin-links (split steps)</h2>
  <div class="cards">
    <div class="card"><h3>Trin 1</h3><a href="<?= h($this->Url->build('/flow/start', ['fullBase' => true])) ?>" target="_blank">/flow/start</a><div class="note">Sæt travel_state, euOnly (admin).</div></div>
    <div class="card"><h3>Trin 2</h3><a href="<?= h($this->Url->build('/flow/journey', ['fullBase' => true])) ?>" target="_blank">/flow/journey</a><div class="note">Incident valg, scope/auto (profil, art12/9 previews).</div></div>
    <div class="card"><h3>Trin 3</h3><a href="<?= h($this->Url->build('/flow/entitlements', ['fullBase' => true])) ?>" target="_blank">/flow/entitlements</a><div class="note">Klasse/PMR/cykel, preinformed, nedgradering.</div></div>
    <div class="card"><h3>Trin 4</h3><a href="<?= h($this->Url->build('/flow/choices', ['fullBase' => true])) ?>" target="_blank">/flow/choices</a><div class="note">Remedy + kompensationsbasis, art19 gate.</div></div>
    <div class="card"><h3>Trin 5</h3><a href="<?= h($this->Url->build('/flow/assistance', ['fullBase' => true])) ?>" target="_blank">/flow/assistance</a><div class="note">Assistance/udgifter.</div></div>
    <div class="card"><h3>Trin 6</h3><a href="<?= h($this->Url->build('/flow/compensation', ['fullBase' => true])) ?>" target="_blank">/flow/compensation</a><div class="note">Final delay/band, art19 flag.</div></div>
  </div>

    <h2>Trin 4+5 / Multi-ticket regressions</h2>
  <div class=\"cards\">
    <div class=\"card\">
      <h3>Art.20 assistance (trin 4+5)</h3>
      <a href=\"<?= h(->Url->build('/api/demo/v2/scenarios?id=art20-assistance-demo&withEval=1', ['fullBase' => true])) ?>\" target=\"_blank\">art20-assistance-demo</a>
      <div class=\"note\">Nye art18/art20 felter i trin 4+5 (fallback, bekræftelse, assistance).</div>
    </div>
    <div class=\"card\">
      <h3>Multi-ticket / guardian</h3>
      <a href=\"<?= h(->Url->build('/api/demo/v2/scenarios?id=multiticket-demo&withEval=1', ['fullBase' => true])) ?>\" target=\"_blank\">multiticket-demo</a>
      <div class=\"note\">Viser ticket_upload_count, ticket_multi_passenger og værge-flag (trin 3).</div>
    </div>
  </div>
<h2>Testværktøjer</h2>
  <div class="cards">
    <div class="card">
      <h3>Dump Session → Fixture</h3>
      <a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1</a>
      <div class="note">Eksporter nuværende flow-session som v2-fixture-skelet.</div>
      <div><a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1&enriched=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1&enriched=1</a></div>
      <div class="note">Enriched-variant: journey basics, op til 3 segmenter, og ekstra trin-3 felter.</div>
    </div>
    <div class="card">
      <h3>Fixtures (v2)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/fixtures', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/fixtures</a>
      <div class="note">Lister alle v2-fixtures (JSON-filer i <code>tests/fixtures/demo/</code>). Brug til at se hvilke cases der kan køres.</div>
    </div>
    <div class="card">
      <h3>Scenarios (v2) med eval</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?withEval=1', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/scenarios?withEval=1</a>
      <div class="note">Kører alle fixtures gennem pipelinen og viser resultatet. Tilføj <code>&id=&lt;fixture-id&gt;</code> for én case. Tilføj <code>&compact=1</code> for kort output (trin 1–6 signaler).</div>
    </div>
    <div class="card">
      <h3>Run Scenarios (v2)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/run-scenarios', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/run-scenarios</a>
      <div class="note">Testrunner som returnerer samme struktur som eval, men egnet til automatiske batch-kørsler. Brug <code>?id=&lt;fixture-id&gt;</code> eller <code>?limit=</code> for subset.</div>
    </div>
  </div>

  <h2>Specifik evaluering (Art. 12)</h2>
  <p>Direkte links til udvalgte Art.12 regressions‑fixtures. Brug <code>&compact=1</code> for kort output. Alle kører fuld pipeline.</p>
  <div class="cards">
    <div class="card">
      <h3>Operatør ansvar (single)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-operator-single&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-operator-single</a>
      <div class="note">Single operatør + fælles PNR → forventet operator ansvar.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-operator-single&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
    <div class="card">
      <h3>Forhandler ansvar</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-retailer-agency&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-retailer-agency</a>
      <div class="note">Agency sælger multi‑operator → forventet retailer ansvar.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-retailer-agency&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
    <div class="card">
      <h3>Multi‑operator (operatør sælger)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-multi-operator&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-multi-operator</a>
      <div class="note">Operatør sælger flere operatører → fælles ansvar spor.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-multi-operator&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
    <div class="card">
      <h3>Ikke anvendelig</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-non-applicable&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-non-applicable</a>
      <div class="note">Særskilt‑notits + ingen samlet ref → forventet art12_applies = false.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-non-applicable&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
    <div class="card">
      <h3>Ukendt (mangler signaler)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-unknowns&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-unknowns</a>
      <div class="note">Alle centrale hooks 'unknown' → art12_applies null.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-unknowns&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
    <div class="card">
      <h3>Undtagelse test (stk. 5)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-disclosure-pnr-shared&withEval=1', ['fullBase' => true])) ?>" target="_blank">art12-disclosure-pnr-shared</a>
      <div class="note">Særskilt‑notits + tydelig disclosure + delt PNR → ikke gennemgående.</div>
      <div><a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art12-disclosure-pnr-shared&withEval=1&compact=1', ['fullBase' => true])) ?>" target="_blank">Compact</a></div>
    </div>
  </div>

  <h2>Exemptions / Profil (Artikler on/off)</h2>
  <p>Representative profil-tests (ikke alle kombinationer – kun nøglecases). Kør med <code>&withEval=1</code> og evt. <code>&compact=1</code>.</p>
  <div class="cards">
    <div class="card">
      <h3>SE &lt;150 km (regional)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ex-profile-se-under150&withEval=1', ['fullBase' => true])) ?>" target="_blank">ex-profile-se-under150</a>
      <div class="note">Forventet: Art.8/9(1)/17–20 off.</div>
    </div>
    <div class="card">
      <h3>SE &gt;150 km (regional)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ex-profile-se-over150&withEval=1', ['fullBase' => true])) ?>" target="_blank">ex-profile-se-over150</a>
      <div class="note">Forventet: artikler re‑enabled.</div>
    </div>
    <div class="card">
      <h3>FI regional</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ex-profile-fi-regional-under150&withEval=1', ['fullBase' => true])) ?>" target="_blank">ex-profile-fi-regional-under150</a>
      <div class="note">Forventet: Art.19 & Art.20(2) off.</div>
    </div>
    <div class="card">
      <h3>HU regional (blocked)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ex-profile-hu-regional-blocked&withEval=1', ['fullBase' => true])) ?>" target="_blank">ex-profile-hu-regional-blocked</a>
      <div class="note">Forventet: profile.blocked=true; Art.19 off.</div>
    </div>
    <div class="card">
      <h3>PL intl beyond EU</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=ex-profile-pl-intl-beyond-eu&withEval=1', ['fullBase' => true])) ?>" target="_blank">ex-profile-pl-intl-beyond-eu</a>
      <div class="note">Forventet: Art.12 & Art.18(3) off (+ Art.10 off).</div>
    </div>
  </div>
  <p class="note">Dækningstrategi: Én fixture pr. mønster (distance‑betinget, blocked, multi‑land, scope‑switch). Tilføj flere kun hvis ny regel tilføjes.</p>


  <h2>Formål med endpoints</h2>
  <ul>
    <li><code>/api/demo/v2/dump-session</code>: Ekstraherer din aktuelle flow-session til en fixture, som kan gemmes og bruges til regressionstests.</li>
    <li><code>/api/demo/v2/fixtures</code>: Oversigt over tilgængelige fixtures (testcases) i projektet.</li>
    <li><code>/api/demo/v2/scenarios?withEval=1</code>: Evaluerer fixtures mod den fulde pipeline og viser output samt evt. diff mod <code>expected</code>.</li>
    <li><code>/api/demo/v2/run-scenarios</code>: Samme evaluering som ovenfor, men tilpasset automatiske kørsler (CI/batch); accepterer filtrering via query-parametre.</li>
  </ul>

  <h2>Korte noter</h2>
  <ul>
    <li>Flowet gemmer i session under <code>flow.form/meta/compute/flags</code>. Dump-session viser det hele.</li>
    <li>V2-fixtures: læg JSON i <code>tests/fixtures/demo/</code> (version=2). Så virker v2-listen og runneren.</li>
    <li>Brug compute-API’erne (<code>/api/compute/*</code>) hvis du vil teste beregninger isoleret (POST JSON).</li>
    <li>Legacy “one/wizard” er ikke primære; brug split steps som main.</li>
  </ul>
</div>
