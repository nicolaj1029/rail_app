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
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #e5e7eb; padding: 8px; vertical-align: top; }
  th { background: #f8fafc; text-align: left; font-size: 13px; }
  td { font-size: 13px; }
</style>

<div class="qa-page">
  <h1>Flow QA (split flow + fixtures/scenarios)</h1>
  <div class="card" style="margin-bottom:12px; border-color:#cfe8ff; background:#f3f8ff;">
    <h3>Chat / RAG / Groq</h3>
    <p>Samlet arbejdsside for chatbot-opsaetning, regulation search/quote, Groq audit og forslag til hvordan chatten kobles paa det nuvaerende flow uden at erstatte evaluators eller pipeline.</p>
    <div><a href="<?= h($this->Url->build('/project/chat-qa', ['fullBase' => true])) ?>" target="_blank">Aabn chat QA-siden</a></div>
    <div style="margin-top:6px;"><a href="<?= h($this->Url->build('/admin/chat', ['fullBase' => true])) ?>" target="_blank">Aabn admin-chat panel</a></div>
  </div>
  <p>Hurtige links og en simpel "how-to" til at teste split-flowet (trin 1-12), samt bruge fixtures/scenarios som regressionstest, admin-debug og operatør-output (EU-formularens fritekstfelt).</p>

  <h2>Sådan tester du trin for trin</h2>
  <ol>
    <li>Åbn hovedflowet (split steps) og kør igennem trin 1-7: <a href="<?= h($this->Url->build('/flow/start', ['fullBase' => true])) ?>" target="_blank">/flow/start</a>.</li>
    <li>Når du er færdig, dump sessionen som fixture-skelet: <a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1</a> (kopiér JSON).<br>
      <span class="note">Enriched: <a href="<?= $this->Url->build('/api/demo/v2/dump-session?asFixture=1&enriched=1', ['fullBase' => true]) ?>" target="_blank">/api/demo/v2/dump-session?asFixture=1&enriched=1</a> (journey+segments+step 4 ekstra).</span>
    </li>
    <li>Gem JSON'en i <code>tests/fixtures/demo/</code> som fx <code>my_case.json</code> (version=2). Nu dukker den op i v2-listen.</li>
    <li>Kør alle v2-fixtures med eval: <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?withEval=1', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/scenarios?withEval=1</a> eller testrunneren: <a href="<?= h($this->Url->build('/api/demo/v2/run-scenarios', ['fullBase' => true])) ?>" target="_blank">/api/demo/v2/run-scenarios</a>.</li>
    <li>Se evt. diff/match i JSON-responsen. Gentag med nye fixtures efter ændringer.</li>
  </ol>

  <h2>Direkte trin-links (split flow)</h2>
  <div class="cards">
    <div class="card"><h3>Trin 1</h3><a href="<?= h($this->Url->build('/flow/start', ['fullBase' => true])) ?>" target="_blank">/flow/start</a><div class="note">Sæt travel_state, euOnly (admin).</div></div>
    <div class="card"><h3>Trin 2</h3><a href="<?= h($this->Url->build('/flow/entitlements', ['fullBase' => true])) ?>" target="_blank">/flow/entitlements</a><div class="note">Billet/upload, operatør, pris, Art.12/9 input.</div></div>
    <div class="card"><h3>Trin 3</h3><a href="<?= h($this->Url->build('/flow/station', ['fullBase' => true])) ?>" target="_blank">/flow/station</a><div class="note">Art.20(3): strandet på station + hvor endte du (handoff), evt. Google Maps.</div></div>
    <div class="card"><h3>Trin 4</h3><a href="<?= h($this->Url->build('/flow/journey', ['fullBase' => true])) ?>" target="_blank">/flow/journey</a><div class="note">Rejseplan/segmenter, Art.12, PMR/cykel, klasse/reservation (købt).</div></div>
    <div class="card"><h3>Trin 5</h3><a href="<?= h($this->Url->build('/flow/incident', ['fullBase' => true])) ?>" target="_blank">/flow/incident</a><div class="note">Hændelse + EU/national gating, missed connection, force majeure.</div></div>
    <div class="card"><h3>Trin 6</h3><a href="<?= h($this->Url->build('/flow/choices', ['fullBase' => true])) ?>" target="_blank">/flow/choices</a><div class="note">Art.20(2)(c): tog stuck/blokeret + hvor endte du (slutpunkt), evt. Google Maps.</div></div>
    <div class="card"><h3>Trin 7</h3><a href="<?= h($this->Url->build('/flow/remedies', ['fullBase' => true])) ?>" target="_blank">/flow/remedies</a><div class="note">Art.18: refusion/omlægning (station → station), reroute-mode, evt. Google Maps.</div></div>
    <div class="card"><h3>Trin 8</h3><a href="<?= h($this->Url->build('/flow/assistance', ['fullBase' => true])) ?>" target="_blank">/flow/assistance</a><div class="note">Art.20: måltider/hotel + udgifter (evt. receipt autofill).</div></div>
    <div class="card"><h3>Trin 9</h3><a href="<?= h($this->Url->build('/flow/downgrade', ['fullBase' => true])) ?>" target="_blank">/flow/downgrade</a><div class="note">Annex II: nedgradering pr. ben (købt vs leveret).</div></div>
    <div class="card"><h3>Trin 10</h3><a href="<?= h($this->Url->build('/flow/compensation', ['fullBase' => true])) ?>" target="_blank">/flow/compensation</a><div class="note">Beregning & resultat (Art.19 + nationale ordninger).</div></div>
    <div class="card"><h3>Trin 11</h3><a href="<?= h($this->Url->build('/flow/applicant', ['fullBase' => true])) ?>" target="_blank">/flow/applicant</a><div class="note">Ansøger & udbetaling (kontaktoplysninger/udbetaling).</div></div>
    <div class="card"><h3>Trin 12</h3><a href="<?= h($this->Url->build('/flow/consent', ['fullBase' => true])) ?>" target="_blank">/flow/consent</a><div class="note">Samtykke & ekstra info (operator-besked/fritekst).</div></div>
  </div>

    <h2>Regressions (udvalgte)</h2>
  <div class="cards">
    <div class="card">
      <h3>Art.20 assistance (split flow)</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=art20-assistance-demo&withEval=1', ['fullBase' => true])) ?>" target="_blank">art20-assistance-demo</a>
      <div class="note">Art.20(3) station + Art.20(2)(c) blocked + Art.18/19 gating/flow (sanity check).</div>
    </div>
    <div class="card">
      <h3>Multi-ticket / guardian</h3>
      <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?id=multiticket-demo&withEval=1', ['fullBase' => true])) ?>" target="_blank">multiticket-demo</a>
      <div class="note">Viser ticket_upload_count, ticket_multi_passenger og værge-flag (trin 3).</div>
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
      <div class="note">
        Kører alle fixtures gennem pipelinen og viser resultatet.
        Tilføj <code>&id=&lt;fixture-id&gt;</code> for én case.
        Tilføj <code>&compact=1</code> for <code>wizard_compact</code> (trin 1–10/12 signaler) + kompakt output.
        Tilføj <code>&withWizard=1</code> for fuld wizard (alle step-keys).
        Tilføj <code>&operatorText=1</code> for <code>operator_case_text</code> (klar til EU-form fritekst + admin).
        Tilføj <code>&provenance=1</code> for mapping af felter → trin.
      </div>
      <div style="margin-top:6px;">
        <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?withEval=1&compact=1&provenance=1', ['fullBase' => true])) ?>" target="_blank">Eksempel: compact + provenance</a>
      </div>
      <div>
        <a href="<?= h($this->Url->build('/api/demo/v2/scenarios?withEval=1&withWizard=1&operatorText=1', ['fullBase' => true])) ?>" target="_blank">Eksempel: withWizard + operatorText</a>
      </div>
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

 
  <h2>Pendlerkort (Art. 19(2)) policy-matrix</h2>
  <p>Matrixen er et backlog-værktøj til at dække operatørers egne ordninger for periode-/abonnementskort. Start som link-only <em>stubs</em>, og opgradér til <em>verified</em> når du har primærkilder + dato + QA.</p>
  <div class="card">
    <div class="note">Datafiler: <code>config/data/season_policy_matrix.json</code> (ordninger) + <code>config/data/operators_catalog.json</code> (operatører/aliases).</div>
    <?php if (!empty($seasonCoverage) && is_array($seasonCoverage)): ?>
      <table style="margin-top:10px;">
        <thead>
          <tr>
            <th>Land</th>
            <th>Operatører i katalog</th>
            <th>Policies</th>
            <th>Verified</th>
            <th>Med links</th>
            <th>Mangler (eksempler)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($seasonCoverage as $cc => $row): $row = (array)$row; $missing = (array)($row['missing_ops'] ?? []); ?>
            <tr>
              <td><strong><?= h((string)$cc) ?></strong></td>
              <td><?= (int)($row['operators_total'] ?? 0) ?></td>
              <td><?= (int)($row['season_policies'] ?? 0) ?></td>
              <td><?= (int)($row['verified'] ?? 0) ?></td>
              <td><?= (int)($row['with_links'] ?? 0) ?></td>
              <td>
                <?php if (empty($missing)): ?>
                  <span class="note">OK</span>
                <?php else: ?>
                  <div class="note"><?= (int)count($missing) ?> mangler</div>
                  <div><?= h(implode(', ', array_slice($missing, 0, 6))) ?><?= count($missing) > 6 ? ' …' : '' ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="note" style="margin-top:10px;">Kunne ikke indlæse coverage-rapport (check at <code>src/Service/SeasonPolicyCatalog.php</code> findes og at matrix-filen er gyldig JSON).</div>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:12px;">
    <h3 style="margin:0 0 6px; font-size:16px;">Operatør policies (klik for at verificere)</h3>
    <div class="note">Tip: kør også <code>php bin/report_season_policy_backlog.php</code> og <code>php bin/check_season_policy_links.php</code> for at se hvad der mangler/bryder.</div>
    <?php if (!empty($seasonPolicies) && is_array($seasonPolicies)): ?>
      <table style="margin-top:10px;">
        <thead>
          <tr>
            <th>Land</th>
            <th>Operatør</th>
            <th>Status</th>
            <th>Verified</th>
            <th>Sidst tjekket</th>
            <th>Kilde</th>
            <th>Claim</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($seasonPolicies as $p): $p = (array)$p; $cc = (string)($p['country'] ?? ''); $op = (string)($p['operator'] ?? ''); $status = (string)($p['coverage_status'] ?? ''); $ver = !empty($p['verified']); $lv = (string)($p['last_verified'] ?? ''); $src = (string)($p['source_url'] ?? ''); $ch = (array)($p['claim_channel'] ?? []); $chUrl = (string)($ch['value'] ?? ''); ?>
            <tr>
              <td><strong><?= h($cc) ?></strong></td>
              <td><?= h($op) ?></td>
              <td><code><?= h($status) ?></code></td>
              <td><?= $ver ? 'yes' : '<span class="note">no</span>' ?></td>
              <td><?= $lv !== '' ? h($lv) : '<span class="note">—</span>' ?></td>
              <td>
                <?php if (trim($src) !== ''): ?>
                  <a href="<?= h($src) ?>" target="_blank" rel="noopener">source</a>
                <?php else: ?>
                  <span class="note">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (trim($chUrl) !== ''): ?>
                  <a href="<?= h($chUrl) ?>" target="_blank" rel="noopener">claim</a>
                <?php else: ?>
                  <span class="note">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="note" style="margin-top:10px;">Ingen policies fundet (check <code>config/data/season_policy_matrix.json</code>).</div>
    <?php endif; ?>
  </div>

  <h2>Formål med endpoints</h2>
  <ul>
    <li><code>/api/demo/v2/dump-session</code>: Ekstraherer din aktuelle flow-session til en fixture, som kan gemmes og bruges til regressionstests.</li>
    <li><code>/api/demo/v2/fixtures</code>: Oversigt over tilgængelige fixtures (testcases) i projektet.</li>
    <li><code>/api/demo/v2/scenarios?withEval=1</code>: Evaluerer fixtures mod den fulde pipeline og viser output samt evt. diff mod <code>expected</code>.</li>
    <li><code>/api/demo/v2/scenarios?withEval=1&operatorText=1</code>: Tilføjer <code>operator_case_text</code>, som kan bruges direkte som fritekst til jernbaneoperatør (EU-formularens ekstra felt) og i admin til QA.</li>
    <li><code>/api/demo/v2/scenarios?withEval=1&withWizard=1</code>: Tilføjer hele wizard-input (alle trin) i scenario-output, så admin/operator kan se "hvad brugeren svarede" sammen med beregningen.</li>
    <li><code>/api/demo/v2/scenarios?withEval=1&explain=1</code>: Tilføjer <code>explain</code>-blokke (fx Art. 18) med kort konklusion/notes, så output kan læses som en sagsopsummering.</li>
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
