
<?php
/** @var \App\View\AppView $this */
?>
<style>
  .flow-grid { display:grid; grid-template-columns: 220px 1fr 1fr; gap:16px; align-items:start; }
  .toc { position:sticky; top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .toc h3 { margin:0 0 8px; font-size:14px; }
  .toc a { display:block; color:#0366d6; text-decoration:none; padding:4px 0; font-size:13px; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .sticky-actions { position:sticky; top:12px; z-index:5; background:#fff; padding:8px; border:1px solid #eee; border-radius:6px; margin-bottom:12px; }
  .fieldset { margin-top:12px; border:1px solid #ccc; border-radius:6px; padding:12px; }
  .fieldset legend { font-weight:bold; }
  @media (max-width: 1000px) { .flow-grid { grid-template-columns: 1fr; } .toc { position:static; } }
  .muted { color:#666; font-size:12px; }
  .warn { color:#b00; }
  .preview { position:sticky; top:12px; }
  .hooks-panel { position:sticky; top:12px; padding:12px; border:1px solid #ddd; background:#f9fbff; border-radius:6px; max-height:80vh; overflow-y:auto; }
  .hooks-panel h3 { margin:0 0 8px; font-size:14px; }
  .hooks-panel .kv { font-size:12px; display:flex; justify-content:space-between; gap:8px; }
  .hooks-panel code { background:#eef; padding:0 4px; border-radius:4px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .grid-2 label { display:block; }
  .section-title { margin:0 0 8px; }
  .badge { background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px; }
  .hl { background:#ffa50022; }
  .hl-blue { background:#f3f6ff; }
  .actions-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .button[disabled] { opacity:0.5; cursor:not-allowed; }
  .small { font-size:12px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .mt16 { margin-top:16px; }
  .hidden { display:none; }
  /* Hide-left mode: collapse TOC column and expand center content */
  /* In no-left mode, give the form ~70% + 200px and take from right column with a safe min width */
  .flow-grid.no-left { grid-template-columns: minmax(0, calc(70% + 200px)) minmax(320px, calc(30% - 200px)); }
  .flow-grid.no-left .toc { display: none; }
  .flow-grid.no-left .preview { grid-column: 2; }
  .flow-grid.no-left .sticky-actions { position:sticky; top:12px; }
  .toggle-left-btn { margin-left: 8px; font-size: 12px; }
  @media (max-width: 1000px) { .flow-grid.no-left { grid-template-columns: 1fr; } }
</style>

<h1 class="section-title">Én side – segmented flow</h1>

<?= $this->Form->create(null, ['id' => 'flowOneForm', 'type' => 'file']) ?>
  <div class="flow-grid no-left" id="flowGrid">
    <aside class="toc">
      <h3>Indholdsfortegnelse</h3>
    <a href="#s1">TRIN 1 · Status</a>
    <a href="#s2">TRIN 2 · Hændelse</a>
  <a href="#sR" class="hidden">Rejsedata & parametre</a>
  <a href="#s3" id="toc-s3" class="hidden">TRIN 3 · Billettype</a>
  <a href="#s4">TRIN 4 · Rejsedetaljer (Rubrik 3)</a>
    <a href="#s5">TRIN 5 · Valg</a>
  <a href="#s6">TRIN 6 · Art. 12</a>
  <a href="#s7">TRIN 7 · Dine valg (Art. 18)</a>
    <a href="#s8">TRIN 8 · Assistance</a>
  <a href="#s9">TRIN 9 · Billetinformation (Art. 9)</a>
    <a href="#s10">TRIN 10 · Kompensation</a>
    <a href="#sD">PDF felter</a>
    <a href="#s11">TRIN 11 · GDPR/Info</a>
      <div class="mt12">
        <span class="muted">Tip: Brug indholdsfortegnelsen for at hoppe mellem sektioner.</span>
      </div>
    </aside>

  <section>
  <div class="sticky-actions actions-row">
    <button type="submit">Gem/Opdater</button>
  <?php $forceOfficial = (bool)($this->getRequest()->getQuery('allow_official') ?? $this->getRequest()->getQuery('debug') ?? false); ?>
  <?php $disableOfficial = $forceOfficial ? false : ((isset($liability_ok)&&!$liability_ok) || (isset($gdpr_ok)&&!$gdpr_ok) || (!empty($reason_missed_conn) && isset($art12['art12_applies']) && $art12['art12_applies']!==true)); ?>
  <button type="submit" formaction="<?= $this->Url->build('/reimbursement/generate') ?>" formtarget="_blank" class="button">Generér PDF opsummering</button>
  <button id="officialBtn" type="submit" formaction="<?= $this->Url->build('/reimbursement/official') ?>" formtarget="_blank" class="button official-btn" data-base-disabled="<?= $disableOfficial?'1':'0' ?>" <?= $disableOfficial?'disabled':'' ?>>Officiel EU-formular</button>
  <button type="button" id="autofillToStep6" class="button">Autofyld TRIN 1–5 (missed connection)</button>
  <button type="button" id="resetSteps1to5" class="button">Nulstil TRIN 1–5</button>
  <?php if (!empty($formDecision)) :
    $fd = $formDecision;
    $rec = (string)($fd['form'] ?? 'eu_standard_claim');
    $isEu = ($rec === 'eu_standard_claim');
    $label = $isEu ? 'Anbefalet: EU-formular' : (($rec === 'none') ? 'Anbefalet: ingen EU-formular' : 'Anbefalet: national formular');
    $reason = (string)($fd['reason'] ?? '');
  ?>
    <span style="margin-left:10px; padding:4px 8px; border-radius:4px; font-size:12px; <?= $isEu ? 'background:#e6f7ff;color:#006bb3;' : 'background:#fff7e6;color:#b36b00;' ?>" title="<?= h($reason) ?>">
      <?= h($label) ?>
    </span>
  <?php endif; ?>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      var btn = document.getElementById('autofillToStep6');
      var resetBtn = document.getElementById('resetSteps1to5');
      function setRadio(name, value) {
        var el = document.querySelector('input[name="'+name+'\"][value="'+value+'"]');
        if (el) el.checked = true;
      }
      function setCheckbox(name, checked) {
        var el = document.querySelector('input[name="'+name+'\"][type="checkbox"]');
        if (el) el.checked = !!checked;
      }
      function clearRadios(name) {
        var els = document.querySelectorAll('input[name=\"'+name+'\\\"][type=\"radio\"]');
        els.forEach(function(e){ e.checked = false; });
      }
      btn.addEventListener('click', function(){
        // TRIN 1 – Status: ongoing
        setRadio('travel_state', 'ongoing');
        // TRIN 2 – Hændelse: delay + missed connection yes
        setRadio('incident_main', 'delay');
        setRadio('missed_connection', 'yes');
        // TRIN 4 – Bekræft forsinkelse ≥ 60 min.
        setCheckbox('delayLikely60', true);
        // TRIN 5 – Fornuftige default svar (ikke-selvforskyldt, ingen dokumentation påkrævet)
        setRadio('hasValidTicket', 'yes');
        setRadio('safetyMisconduct', 'no');
        setRadio('forbiddenItemsOrAnimals', 'no');
        // "Administrative forskrifter (overholdt?)" – vælg 'no' som standard for ingen overtrædelse
  // "Administrative forskrifter (overholdt?)" – 'yes' betyder overholdt (ingen overtrædelse)
  setRadio('customsRulesBreached', 'yes');
        setRadio('operatorStampedDisruptionProof', 'no');
        // Submit to persist and recalc visibility up to TRIN 6
        var f = document.getElementById('flowOneForm');
        if (f) f.submit();
      });

      if (resetBtn) {
        resetBtn.addEventListener('click', function(){
          // Clear TRIN 1
          clearRadios('travel_state');
          // Clear TRIN 2
          clearRadios('incident_main');
          clearRadios('missed_connection');
          // Clear TRIN 4 confirmation
          setCheckbox('delayLikely60', false);
          // Clear TRIN 5 CIV screening answers
          clearRadios('hasValidTicket');
          clearRadios('safetyMisconduct');
          clearRadios('forbiddenItemsOrAnimals');
          clearRadios('customsRulesBreached');
          clearRadios('operatorStampedDisruptionProof');
          // Submit to persist and recalc (back to earlier steps)
          var f = document.getElementById('flowOneForm');
          if (f) f.submit();
        });
      }
    });
  </script>
    <?php if (!$forceOfficial && $disableOfficial): ?><span class="warn small">Deaktiveret: tjek CIV og GDPR.</span><?php endif; ?>
    <?php if ($forceOfficial): ?><span class="badge small">DEBUG: EU-formular låst op</span><?php endif; ?>
    <button type="button" id="toggleLeftBtn" class="toggle-left-btn">Skjul venstre panel</button>
  </div>

      <fieldset id="s1" class="fieldset hl">
        <legend>TRIN 1 · Status</legend>
        <p class="muted">Sæt ét kryds</p>
        <label><input type="radio" name="travel_state" value="completed" <?= !empty($flags['travel_state']) && $flags['travel_state']==='completed'?'checked':'' ?> /> Rejsen er afsluttet</label><br/>
        <label><input type="radio" name="travel_state" value="ongoing" <?= !empty($flags['travel_state']) && $flags['travel_state']==='ongoing'?'checked':'' ?> /> Rejsen er påbegyndt (i tog / skift)</label><br/>
        <label><input type="radio" name="travel_state" value="before_start" <?= !empty($flags['travel_state']) && $flags['travel_state']==='before_start'?'checked':'' ?> /> Skal til at påbegynde rejsen</label>
      </fieldset>
      <script>
      // Lightweight fallback just for TRIN 2 → ensures UI reacts even if other JS is blocked
      (function(){
        try {
          var missedRow = document.getElementById('missedRow');
          var s6 = document.getElementById('s6');
          var s6Art12 = document.getElementById('s6Art12');
          var s6Skip = document.getElementById('s6Skip');
          function updateLocal(){
            var sel = document.querySelector('input[name="incident_main"]:checked');
            if (missedRow) {
              var showMissed = !!(sel && (sel.value === 'delay' || sel.value === 'cancellation'));
              missedRow.classList.toggle('hidden', !showMissed);
            }
            // Also gate TRIN 6 quickly based on missed-connection answer
            var missedYes = !!document.querySelector('input[name="missed_connection"][value="yes"]:checked');
            var autoOkEl = document.getElementById('art12AutoOK');
            var autoOk = !!(autoOkEl && autoOkEl.value === '1');
            if (s6 && s6Art12 && s6Skip) {
              if (missedYes) {
                s6.classList.remove('hidden');
                if (autoOk) { s6Art12.classList.add('hidden'); s6Skip.classList.remove('hidden'); }
                else { s6Art12.classList.remove('hidden'); s6Skip.classList.add('hidden'); }
              } else {
                // Keep fieldset visible or hidden according to higher-level gating; just hide the card and show skip
                s6Art12.classList.add('hidden'); s6Skip.classList.remove('hidden');
              }
            }
          }
          var binds = document.querySelectorAll('input[name="incident_main"], input[name="missed_connection"]');
          binds.forEach(function(r){ r.addEventListener('change', updateLocal); r.addEventListener('click', updateLocal); });
          updateLocal();
        } catch (e) { /* no-op fallback */ }
      })();
      </script>

      <?php $isCompleted = (!empty($flags['travel_state']) && $flags['travel_state']==='completed'); ?>

      <fieldset id="s2" class="fieldset hl mt12">
        <legend>TRIN 2 · Hændelse</legend>
        <p class="muted"><em>REIMBURSEMENT AND COMPENSATION REQUEST FORM – Rubrik 1 udfyldes</em></p>
        <?php $main = $incident['main'] ?? ''; ?>
  <label><input type="radio" name="incident_main" value="delay" <?= $main==='delay'?'checked':'' ?> onchange="var r=document.getElementById('missedRow'); if(r){ r.classList.remove('hidden'); }" /> Delay (vælg én)</label><br/>
  <label><input type="radio" name="incident_main" value="cancellation" <?= $main==='cancellation'?'checked':'' ?> onchange="var r=document.getElementById('missedRow'); if(r){ r.classList.remove('hidden'); }" /> Cancellation (vælg én)</label><br/>
        <div id="missedRow" class="mt8 <?= ($main==='delay'||$main==='cancellation') ? '' : 'hidden' ?>">
          <div>Har det medført en missed connection?</div>
          <label><input type="radio" name="missed_connection" value="yes" <?= !empty($incident['missed'])?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="missed_connection" value="no" <?= empty($incident['missed'])?'checked':'' ?> /> Nej</label>
        </div>
      </fieldset>

      <fieldset id="sR" class="fieldset mt12 hidden">
        <legend>Rejsedata & parametre</legend>
        <label for="journey_json">Rejsedata (JSON)</label>
        <textarea name="journey_json" id="journey_json" rows="6" style="width:100%"><?= h(json_encode($journey ?: ["country"=>["value"=>"EU"],"ticketPrice"=>["value"=>"100 EUR"]], JSON_UNESCAPED_UNICODE)) ?></textarea>
        <label for="ocr_text" class="mt8">Billet-tekst (OCR)</label>
        <textarea name="ocr_text" id="ocr_text" rows="4" style="width:100%"></textarea>
        <div class="grid-2 mt8">
          <label><input type="checkbox" name="eu_only" <?= !empty($compute['euOnly'])?'checked':'' ?> /> EU-only forsinkelse</label>
          <label>Forsinkelse (min)
            <input type="number" name="delay_min_eu" value="<?= (int)($compute['delayMinEU'] ?? 0) ?>" />
          </label>
          <label><input type="checkbox" name="known_delay" <?= !empty($compute['knownDelayBeforePurchase'])?'checked':'' ?> /> Kendt før køb?</label>
          <label><input type="checkbox" name="extraordinary" <?= !empty($compute['extraordinary'])?'checked':'' ?> /> Ekstraordinære omstændigheder?</label>
          <label><input type="checkbox" name="art9_opt_in" <?= !empty($compute['art9OptIn'])?'checked':'' ?> /> Inkludér Art. 9 (kun på anmodning)</label>
        </div>
      </fieldset>

      <fieldset id="s3" class="fieldset hl-blue mt12 hidden">
        <legend>TRIN 3</legend>
        <div><strong>Art. 11 - billettype</strong></div>
        <p class="muted">Sæt et X ved en af mulighederne</p>
        <div class="mt8">Billetten er købt på :</div>
        <label><input type="radio" name="purchaseChannel" value="station" <?= (isset($form['purchaseChannel'])&&$form['purchaseChannel']==='station')?'checked':'' ?> /> På station - jernbanevirksomhed</label><br/>
        <label><input type="radio" name="purchaseChannel" value="web_app" <?= (isset($form['purchaseChannel'])&&$form['purchaseChannel']==='web_app')?'checked':'' ?> /> Internet / app -  jernbanevirksomhed eller billetsælger</label><br/>
        <label><input type="radio" name="purchaseChannel" value="onboard" <?= (isset($form['purchaseChannel'])&&$form['purchaseChannel']==='onboard')?'checked':'' ?> /> I toget - jernbanevirksomhed</label>
      </fieldset>

    <fieldset id="s4" class="fieldset mt12">
  <legend>TRIN 4</legend>
  <p class="small muted">Upload din billet for automatisk at udfylde rejsedetaljer.</p>
        <div class="card mt8">
          <label>Billet
            <input type="file" name="ticket_upload" accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" onchange="document.getElementById('flowOneForm').submit();" />
          </label>
          <?php if (!empty($form['_ticketUploaded'])): ?>
            <div class="small muted mt8">Billet uploadet<?= !empty($form['_ticketOriginalName']) ? (': ' . h($form['_ticketOriginalName'])) : '' ?>. Felter nedenfor er forhåndsudfyldt, hvor muligt.</div>
          <?php else: ?>
            <div class="small muted mt8">Der er ikke valgt nogen fil.</div>
          <?php endif; ?>
        </div>

        <?php if (!empty($meta['_passengers_auto'])): ?>
        <div class="card mt8">
          <strong>📋 Fundne passagerer på billetten</strong>
          <div class="small mt4">Redigér navne og markér hvem der klager:</div>
          <div class="small" style="margin:6px 0 0 0;">
            <?php $paxList = (array)$meta['_passengers_auto']; ?>
            <?php foreach ($paxList as $i => $p): $nameVal = (string)($p['name'] ?? ''); $age = (string)($p['age_category'] ?? 'unknown'); $isC = !empty($p['is_claimant']); ?>
              <div class="mt4">
                <label>Navn
                  <input type="text" name="passenger[<?= (int)$i ?>][name]" value="<?= h($nameVal) ?>" placeholder="Passager #<?= (int)($i+1) ?>" />
                </label>
                <span class="badge" style="margin-left:6px;"><?= h(ucfirst($age)) ?></span>
                <label class="ml8"><input type="checkbox" name="passenger[<?= (int)$i ?>][is_claimant]" value="1" <?= $isC?'checked':'' ?> /> Klager</label>
              </div>
            <?php endforeach; ?>
          </div>
          <label class="small mt8"><input type="checkbox" name="claimant_is_legal_representative" value="1" <?= !empty($form['claimant_is_legal_representative'])?'checked':'' ?> /> Jeg er juridisk ansvarlig for disse passagerer</label>
        </div>
        <?php endif; ?>

        <div class="card mt8">
          <label class="small">⬆️ Upload flere billetter hvis rejsen er fordelt over flere transaktioner</label>
          <input type="file" name="multi_ticket_upload[]" multiple accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" onchange="document.getElementById('flowOneForm').submit();" />
        </div>

        <?php if (!empty($groupedTickets)): ?>
        <div class="card mt8">
          <strong>Billetter samlet i sagen</strong>
          <?php foreach ((array)$groupedTickets as $gi => $g): $shared = !empty($g['shared']); ?>
            <div class="small mt4">
              <strong>Gruppe <?= (int)($gi+1) ?></strong>
              <?php if (!empty($g['pnr']) || !empty($g['dep_date'])): ?>
                (<?= h(trim((string)($g['pnr'] ?? '') . ' ' . (string)($g['dep_date'] ?? ''))) ?>)
              <?php endif; ?>
              <span class="badge" style="margin-left:6px;"><?= $shared ? 'samlet' : 'enkelt' ?></span>
            </div>
            <ul class="small" style="margin:4px 0 0 16px;">
              <?php foreach ((array)($g['tickets'] ?? []) as $t): ?>
                <li>
                  <?= h((string)($t['file'] ?? '')) ?><?= (!empty($t['pnr'])||!empty($t['dep_date'])) ? (': ' . h(trim((string)($t['pnr'] ?? '') . ' ' . (string)($t['dep_date'] ?? '')))) : '' ?>
                  <?php $pc = isset($t['passengers']) ? count((array)$t['passengers']) : 0; if ($pc>0): ?>
                    <span class="badge" style="margin-left:6px;">pax <?= (int)$pc ?></span>
                  <?php endif; ?>
                  <?php if (!empty($t['file'])): ?>
                    <button type="submit" name="remove_ticket" value="<?= h((string)$t['file']) ?>" class="small" style="margin-left:6px;">Fjern</button>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endforeach; ?>
          <div class="small muted mt4">Grupperet efter PNR + dato. Du kan uploade flere billetter her.</div>
        </div>
        <?php endif; ?>
        
        <div class="card mt8">
          <strong>3.1. Name of railway undertaking: <span class="badge">AUTO</span></strong><br/>
          <div class="grid-2 mt8">
            <label>Operatør (kan overskrives)
              <input type="text" name="operator" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" />
            </label>
            <label>Land
              <input type="text" name="operator_country" value="<?= h($meta['_auto']['operator_country']['value'] ?? ($form['operator_country'] ?? '')) ?>" />
            </label>
            <label>Produkt
              <input type="text" name="operator_product" value="<?= h($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? '')) ?>" />
            </label>
          </div>
        </div>

        

        <div class="card mt8">
          <strong>3.2. Scheduled journey — <span class="badge">AUTO</span></strong>
          <?php if (!empty($meta['logs'])): ?>
            <div class="small muted mt8">
              <strong>OCR debug:</strong>
              <ul>
                <?php foreach ((array)$meta['logs'] as $log): ?>
                  <li><?= h($log) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <div class="grid-2 mt8">
            <label>3.2.1. Departure date (YYYY-MM-DD) — <span class="badge">OK available</span>
              <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
            <label>3.2.4. Scheduled time of departure — <span class="badge">OK available</span>
              <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" />
            </label>
            <label>3.2.2. Departure station — <span class="badge">OK available</span>
              <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" />
            </label>
            <label>3.2.3. Destination station — <span class="badge">OK available</span>
              <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" />
            </label>
            <label>3.2.5. Scheduled time of arrival — <span class="badge">OK available</span>
              <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" />
            </label>
            <label>3.2.6. Train no./category — <span class="badge">OK available</span>
              <input type="text" name="train_no" value="<?= h($meta['_auto']['train_no']['value'] ?? ($form['train_no'] ?? '')) ?>" />
            </label>
            <label>3.2.7. Ticket Number(s)/Booking Reference — <span class="badge">OK available</span>
              <input type="text" name="ticket_no" value="<?= h($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? '')) ?>" />
            </label>
            <?php if (!empty($journey['passengerCount'])): ?>
              <div class="small badge">AUTO: Passagerer = <?= (int)$journey['passengerCount'] ?><?= !empty($journey['isGroupTicket']) ? ' (gruppebillet)' : '' ?></div>
            <?php endif; ?>
            <label>3.2.8. Ticket price(s) — <span class="badge">OK available</span>
              <input type="text" name="price" value="<?= h($meta['_auto']['price']['value'] ?? ($form['price'] ?? '')) ?>" placeholder="100 EUR" />
            </label>
          </div>
        </div>

  <?php $isCompleted = (!empty($flags['travel_state']) && $flags['travel_state']==='completed'); $missedInc = !empty($incident['missed']); ?>
        <div id="delayLikelyBox" class="card mt8 <?= $isCompleted ? 'hidden' : '' ?>">
          <strong>Bekræftelse</strong>
          <div class="mt8 small">Rejsen er ikke afsluttet. Bekræft venligst at en forsinkelse på ≥ 60 minutter er sandsynlig.</div>
          <label class="mt8"><input type="checkbox" name="delayLikely60" value="1" <?= !empty($form['delayLikely60'])?'checked':'' ?> /> Forsinkelse ≥ 60 minutter er sandsynlig</label>
          <div id="delayLikelyWarn" class="small warn mt8 <?= (!empty($form['delayLikely60'])) ? 'hidden' : '' ?>">Afkryds venligst boksen for at fortsætte til TRIN 5–7.</div>
        </div>

        <div id="actualJourneyCard" class="card mt8 <?= $isCompleted ? '' : 'hidden' ?>">
          <strong>3.3. Actual journey — <span class="badge">AUTO</span></strong>
          <div class="small muted">Se TRIN 1 — hvis “Rejsen er afsluttet”, udfyld nedenstående</div>
          <div class="grid-2 mt8">
            <label>3.3.1. Date of actual arrival — <span class="badge">OK available</span>
              <input type="text" name="actual_arrival_date" value="<?= h($meta['_auto']['actual_arrival_date']['value'] ?? ($form['actual_arrival_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
            <label>3.3.2. Actual time of departure — <span class="badge">OK available</span>
              <input type="text" name="actual_dep_time" value="<?= h($meta['_auto']['actual_dep_time']['value'] ?? ($form['actual_dep_time'] ?? '')) ?>" placeholder="HH:MM" />
            </label>
            <label>3.3.3. Actual time of arrival — <span class="badge">OK available</span>
              <input type="text" name="actual_arr_time" value="<?= h($meta['_auto']['actual_arr_time']['value'] ?? ($form['actual_arr_time'] ?? '')) ?>" placeholder="HH:MM" />
            </label>
            <label>3.3.4. Train no./category — <span class="badge">OK available</span>
              <input type="text" name="train_no" value="<?= h($meta['_auto']['train_no']['value'] ?? ($form['train_no'] ?? '')) ?>" />
            </label>
            <label id="missedIn33Wrap" class="<?= $missedInc ? '' : 'hidden' ?>">3.3.5. Missed connection in (station)
              <input id="missedStationIn33" type="text" name="missed_connection_station" value="<?= h($meta['_auto']['missed_connection_station']['value'] ?? ($form['missed_connection_station'] ?? '')) ?>" <?= $isCompleted ? '' : 'disabled' ?> />
            </label>
          </div>
        </div>

  <?php $segAuto = (array)($meta['_segments_auto'] ?? []); if (!empty($segAuto) && !empty($incident['missed'])): ?>
        <div id="connectionsCard" class="card mt8">
          <strong>Forbindelser fundet på billetten</strong>
          <div class="small muted mt4">Vælg den forbindelse der blev misset (skiftestation). Vi udfylder feltet nedenfor.</div>
          <ul class="small" style="margin:6px 0 0 16px;">
            <?php foreach ($segAuto as $i => $s): $from = (string)($s['from'] ?? ''); $to = (string)($s['to'] ?? ''); $dep = (string)($s['schedDep'] ?? ''); $arr = (string)($s['schedArr'] ?? ''); ?>
              <li><?= h($from) ?> (<?= h($dep) ?>) → <?= h($to) ?> (<?= h($arr) ?>)</li>
            <?php endforeach; ?>
          </ul>
          <?php
            $choices = (array)($form['_miss_conn_choices'] ?? []);
            if (empty($choices) && count($segAuto) >= 2) {
              // Build default choices client-side
              $choices = [];
              for ($i=0; $i < count($segAuto)-1; $i++) { $choices[$segAuto[$i]['to']] = $segAuto[$i]['to']; }
            }
          ?>
          <?php if (!empty($choices)): ?>
            <div class="mt8">Misset forbindelse i (station):</div>
            <?php $currentMiss = (string)($form['missed_connection_station'] ?? ''); ?>
            <?php foreach ($choices as $key => $label): ?>
              <label class="mr8"><input type="radio" name="missed_connection_station" value="<?= h($key) ?>" <?= $currentMiss===$key?'checked':'' ?> /> <?= h($label) ?></label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div id="missedOnlyCard" class="card mt8 <?= (!$isCompleted && $missedInc) ? '' : 'hidden' ?>">
          <strong>3.5. Missed connection (kun station)</strong>
          <div class="grid-2 mt8">
            <label>Station for missed connection
              <input id="missedStationStandalone" type="text" name="missed_connection_station" value="<?= h($meta['_auto']['missed_connection_station']['value'] ?? ($form['missed_connection_station'] ?? '')) ?>" <?= (!$isCompleted && $missedInc) ? '' : 'disabled' ?> />
            </label>
          </div>
        </div>
      </fieldset>

  <fieldset id="s5" class="fieldset mt12 <?= ($isCompleted || !empty($form['delayLikely60']) || !empty($reason_missed_conn) || !empty($reason_cancellation) || !empty($reason_delay)) ? '' : 'hidden' ?>">
  <legend>TRIN 5 · CIV vurdering</legend>
  <p class="muted">Opdelt i to trin: A) Selvforskyldt hændelse? B) Dokumentation for operatørens ansvar.</p>
        <?php
          $travelState = $flags['travel_state'] ?? '';
          $showScreen = in_array($travelState, ['ongoing','completed'], true);
          $hasValid = isset($form['hasValidTicket']) ? (string)$form['hasValidTicket'] : 'yes';
          $sm = isset($form['safetyMisconduct']) ? (string)$form['safetyMisconduct'] : 'no';
          $fia = isset($form['forbiddenItemsOrAnimals']) ? (string)$form['forbiddenItemsOrAnimals'] : 'no';
          $crb = isset($form['customsRulesBreached']) ? (string)$form['customsRulesBreached'] : 'yes';
          // Default til ukendt, så advarsler ikke vises før brugerens valg
          $stamp = isset($form['operatorStampedDisruptionProof']) ? (string)$form['operatorStampedDisruptionProof'] : '';
        ?>
        <div id="s5Screening" class="card <?= $showScreen? '' : 'hidden' ?>">
          <h4>Trin A – Var forsinkelsen selvforskyldt?</h4>
          <div class="small muted">Besvar for hver situation:</div>
          <div class="grid-2 mt8">
            <div>
              <strong>Gyldig billet</strong><br/>
              <label><input type="radio" name="hasValidTicket" value="yes" <?= ($hasValid==='yes')?'checked':'' ?> /> Jeg havde gyldig rejsehjemmel under hele rejsen</label><br/>
              <label><input type="radio" name="hasValidTicket" value="no" <?= ($hasValid==='no')?'checked':'' ?> /> Nej</label>
            </div>
            <div>
              <h4>Trin B – Dokumentation for operatørens ansvar</h4>
              <div class="small">Er billetten stemplet af operatøren, eller findes anden dokumentation?</div>
              <label><input type="radio" name="operatorStampedDisruptionProof" value="yes" <?= ($stamp==='yes')?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="operatorStampedDisruptionProof" value="no" <?= ($stamp!=='yes')?'checked':'' ?> /> Nej</label>
            </div>
            <div>
              <strong>Adfærd i toget</strong><br/>
              <label><input type="radio" name="safetyMisconduct" value="yes" <?= ($sm==='yes')?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="safetyMisconduct" value="no" <?= ($sm!=='yes')?'checked':'' ?> /> Nej</label>
            </div>
            <div>
              <strong>Håndbagage / dyr / genstande</strong><br/>
              <label><input type="radio" name="forbiddenItemsOrAnimals" value="yes" <?= ($fia==='yes')?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="forbiddenItemsOrAnimals" value="no" <?= ($fia!=='yes')?'checked':'' ?> /> Nej</label>
            </div>
            <div>
              <strong>Administrative forskrifter (overholdt?)</strong><br/>
              <label><input type="radio" name="customsRulesBreached" value="yes" <?= ($crb==='yes')?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="customsRulesBreached" value="no" <?= ($crb!=='yes')?'checked':'' ?> /> Nej</label>
            </div>
          </div>
          <?php
            // Beregn initiale A/B-statusser direkte ud fra de nuværende radioværdier,
            // så visningen matcher det brugeren ser – uafhængigt af controllerens flags.
            $selfInfLocal = ($hasValid !== 'yes') || ($sm === 'yes') || ($fia === 'yes') || ($crb !== 'yes');
            $hasProofAnswer = ($stamp === 'yes' || $stamp === 'no');
            $proofLocal = ($stamp === 'yes');
            $liabilityOkLocal = (!$selfInfLocal) && $proofLocal;
          ?>
          <div class="mt8">
            <span id="civSelfInflictedWarn" class="warn <?= $selfInfLocal ? '' : 'hidden' ?>"><strong>Kan ikke tage sagen:</strong> selvforskyldt udfald iht. CIV (trin A).</span>
            <span id="civOperatorProofWarn" class="warn <?= (!$selfInfLocal && $hasProofAnswer && !$proofLocal) ? '' : 'hidden' ?>"><strong>Mangler bevis:</strong> Operatørens dokumentation mangler (trin B).</span>
            <span id="civOkBadge" class="small badge <?= ($liabilityOkLocal && $hasProofAnswer) ? '' : 'hidden' ?>">Ok – CIV vurdering bestået (A+B)</span>
          </div>
        </div>
        <div id="s5SkipNote" class="small muted <?= $showScreen? 'hidden' : '' ?>">TRIN 5 springes over – gå videre til TRIN 6.</div>
      </fieldset>

      <script>
      // Standalone, defensive CIV-status updater for TRIN 5
      // Ensures the A/B warnings toggle correctly even without a full rerender
      (function(){
        function updateCIVStatus(){
          try {
            var hv = document.querySelector('input[name="hasValidTicket"]:checked');
            var smv = document.querySelector('input[name="safetyMisconduct"]:checked');
            var fvv = document.querySelector('input[name="forbiddenItemsOrAnimals"]:checked');
            var crv = document.querySelector('input[name="customsRulesBreached"]:checked');
            var op  = document.querySelector('input[name="operatorStampedDisruptionProof"]:checked');
            var hasValid = hv ? (hv.value === 'yes') : true;
            var safetyMis = smv ? (smv.value === 'yes') : false;
            var forb = fvv ? (fvv.value === 'yes') : false;
            var customsOk = crv ? (crv.value === 'yes') : true; // 'yes' = overholdt
            var proof = op ? (op.value === 'yes') : false;
            var selfInf = (!hasValid) || safetyMis || forb || (!customsOk);
            var elA = document.getElementById('civSelfInflictedWarn');
            var elB = document.getElementById('civOperatorProofWarn');
            var elOK = document.getElementById('civOkBadge');
            if (elA) elA.classList.toggle('hidden', !selfInf);
            if (elB) elB.classList.toggle('hidden', !((!selfInf) && (!proof)));
            if (elOK) elOK.classList.toggle('hidden', !((!selfInf) && proof));
          } catch (e) { /* no-op */ }
        }
        // Run once on load and bind listeners to TRIN 5 inputs
        try { updateCIVStatus(); } catch(e) {}
        try {
          var trin5Inputs = document.querySelectorAll('input[name="hasValidTicket"], input[name="safetyMisconduct"], input[name="forbiddenItemsOrAnimals"], input[name="customsRulesBreached"], input[name="operatorStampedDisruptionProof"]');
          trin5Inputs.forEach(function(inp){
            ['change','click','input'].forEach(function(ev){ inp.addEventListener(ev, updateCIVStatus); });
          });
        } catch(e) { /* no-op */ }
      })();
      </script>

  <!-- TRIN 6 skal kun være aktivt for missed connection -->
  <fieldset id="s6" class="fieldset mt12 <?= (!empty($reason_missed_conn)) ? '' : 'hidden' ?>">
        <legend>TRIN 6 · Art. 12 – gennemgående billet (missed connection)</legend>
  <?php $showArt12 = !empty($reason_missed_conn); $hooks = (array)($art12['hooks'] ?? []); $missing = (array)($art12['missing'] ?? []); $applies = $art12['art12_applies'] ?? null; $autoOK = (!empty($reason_missed_conn) && $applies === true); ?>
  <?php
    // Inline evaluation summary so result is visible even without the right panel
    $liableParty = (string)($art12['liable_party'] ?? 'unknown');
    $liableNorm = ($liableParty === 'retailer') ? 'agency' : $liableParty;
    $liableLbl = ($liableNorm === 'operator') ? 'Jernbanevirksomhed' : (($liableNorm === 'agency') ? 'Forhandler/rejsebureau' : 'Ukendt');
    $resultLbl = ($applies === true) ? 'Gælder' : (($applies === false) ? 'Gælder ikke' : 'Ukendt');
  ?>
  <div class="small mt4" id="s6EvalSummary">
    <strong>Evaluering:</strong>
    <span class="badge" style="background:<?= ($applies===true?'#e6ffed':'#f6f8fa') ?>;border-color:<?= ($applies===true?'#b2f2bb':'#d0d7de') ?>;color:#24292f;">
      <?= h($resultLbl) ?>
    </span>
    <?php if ($applies === true): ?>
      <span class="badge" style="margin-left:6px;">Ansvarlig: <?= h($liableLbl) ?></span>
    <?php endif; ?>
  </div>
  <?php
    // Inline TRIN 6 decision details (defensive, same rules as hooks panel)
    $hks = (array)($art12['hooks'] ?? []);
    $ttdTri = (string)($hks['through_ticket_disclosure'] ?? 'unknown');
    $scnTri = (string)($hks['separate_contract_notice'] ?? 'unknown');
    $sellerOpTri = (string)($hks['seller_type_operator'] ?? 'unknown');
    $sellerAgTri = (string)($hks['seller_type_agency'] ?? 'unknown');
    $billettype = null; $kompBasis = null; $needs = (array)($art12['missing'] ?? []);
    if ($scnTri === 'no') {
      $billettype = 'gennemgående';
      if ($sellerOpTri === 'yes') { $kompBasis = 'stk3'; }
      elseif ($sellerAgTri === 'yes') { $kompBasis = 'stk4'; }
      else { $kompBasis = 'stk3_eller_4'; }
    } elseif ($scnTri === 'yes') {
      if ($ttdTri === 'yes') {
        $billettype = 'ikke gennemgående';
        $kompBasis = 'per_led';
      } else {
        $billettype = 'gennemgående';
        if ($sellerOpTri === 'yes') { $kompBasis = 'stk3'; }
        elseif ($sellerAgTri === 'yes') { $kompBasis = 'stk4'; }
        else { $kompBasis = 'stk3_eller_4'; }
      }
    }
  ?>
  <div class="small mt4" id="s6DecisionInline">
    <span>billettype: <code><?= h($billettype ?? '-') ?></code></span>
    <span class="ml8">komp: <code><?= h($kompBasis ?? '-') ?></code></span>
    <?php if (!empty($needs)): ?>
      <span class="ml8">mangler: <code><?= h(implode(', ', $needs)) ?></code></span>
    <?php endif; ?>
  </div>
  <div class="small muted <?= $autoOK? '' : 'hidden' ?>">Art. 12 er automatisk bekræftet ud fra billetdata – ingen spørgsmål nødvendige.</div>
  <input type="hidden" id="art12AutoOK" value="<?= $autoOK? '1':'0' ?>" />
  <div class="mt8 <?= ($showArt12 && !$autoOK)? '' : 'hidden' ?>">
    <span class="small muted">Besvar de to spørgsmål nedenfor. PNR/booking afledes automatisk fra 3.2.7/uploadede billetter.</span>
  </div>
  <div id="s6Art12" class="card <?= ($showArt12 && !$autoOK)? '' : 'hidden' ?>">
          <?php if ($applies === false): ?>
            <div class="warn"><strong>Kan ikke tage sagen:</strong> Art. 12-evaluering negativ ved missed connection.</div>
          <?php endif; ?>
          <?php
            // Compute pnr_count from available data (bookingRef + multi tickets)
            $pnrSet = [];
            $br = (string)($journey['bookingRef'] ?? '');
            if ($br !== '') { $pnrSet[$br] = true; }
            $multi = (array)($meta['_multi_tickets'] ?? []);
            foreach ($multi as $mt) { $p = (string)($mt['pnr'] ?? ''); if ($p !== '') { $pnrSet[$p] = true; } }
            $pnrCount = count($pnrSet);
          ?>
          <input type="hidden" id="pnrCount" value="<?= (int)$pnrCount ?>" />
          <?php $sharedScopeTri = (string)($hks['shared_pnr_scope'] ?? 'unknown'); $singleBookingTri = (string)($hks['single_booking_reference'] ?? 'unknown'); ?>
          <input type="hidden" id="sharedScopeTri" value="<?= h($sharedScopeTri) ?>" />
          <input type="hidden" id="singleBookingTri" value="<?= h($singleBookingTri) ?>" />
          <div class="grid-2 mt8">
            <div>
              <?php
                // Gating-variabler baseret på flowet (TRIN 1–5)
                $st = (string)($form['same_transaction'] ?? '');
                $noPNR = ($pnrCount === 0);
                $singlePNR = ($pnrCount === 1);
                $sharedPNRForRetailer = ($pnrCount <= 1); // 0 eller 1: lad forhandler-scenariet stille TRIN 4/5
                $showSameTxn = ($pnrCount > 1);
                $showT4T5 = false; // Start med sælger-spørgsmålet først
              ?>

              <!-- Spørgsmål 1: Er du operatør eller rejsebureau? -->
              <?php $sc = (string)($form['seller_channel'] ?? ''); ?>
              <div id="sellerWrap" class="mt0">
                <strong>Hvem har du købt hos?</strong><br/>
                <div class="small mt4">Er du operatør (togselskab) eller rejsebureau/billetudsteder?</div>
                <label><input type="radio" name="seller_channel" value="operator" <?= $sc==='operator'?'checked':'' ?> /> Operatør (togselskab)</label>
                <label class="ml8"><input type="radio" name="seller_channel" value="retailer" <?= $sc==='retailer'?'checked':'' ?> /> Rejsebureau/billetudsteder</label>
                <!-- Hidden bindings so evaluator sees single_txn_* directly from seller choice (minimal TRIN 6 flow) -->
                <input type="hidden" name="single_txn_operator" id="s6_h_single_txn_operator" value="<?= h($form['single_txn_operator'] ?? '') ?>" />
                <input type="hidden" name="single_txn_retailer" id="s6_h_single_txn_retailer" value="<?= h($form['single_txn_retailer'] ?? '') ?>" />
                <?php $st = (string)($form['same_transaction'] ?? ''); ?>
                <div id="sameTxnWrap" class="small mt8 <?= ($pnrCount>1)?'':'hidden' ?>">Var billetterne købt samlet i én transaktion?
                  <label class="ml8"><input type="radio" name="same_transaction" value="yes" <?= $st==='yes'?'checked':'' ?> /> Ja</label>
                  <label class="ml8"><input type="radio" name="same_transaction" value="no" <?= $st==='no'?'checked':'' ?> /> Nej</label>
                </div>
                <?php if ($sc==='operator' && $pnrCount===1): ?>
                  <div id="autoBadgeOp" class="small badge mt8">AUTO: Gennemgående (stk. 3) – én PNR hos operatør</div>
                <?php elseif ($pnrCount>1 && $st==='no'): ?>
                  <div id="autoBadgeSep" class="small badge mt8">AUTO: Særskilt kontrakt – flere PNR og ikke samme transaktion</div>
                <?php endif; ?>
              </div>

              <?php
                // If evaluator says we are missing exception answers, force them visible regardless of operator+single-booking heuristics
                $needExceptions = in_array('separate_contract_notice', $missing ?? [], true) || in_array('through_ticket_disclosure', $missing ?? [], true);
                $hideExceptionsInit = !$needExceptions && ($sc==='operator' && ($pnrCount===1 || ($sharedScopeTri==='yes') || ($singleBookingTri==='yes')));
              ?>
              <div id="t4t5Wrap" class="mt12<?= $hideExceptionsInit ? ' hidden' : '' ?>">
                <?php
                  // Begge underspørgsmål renderes, men initialt skjules det/de, som ikke er nødvendige.
                  // Server-initialisering: vis separate_contract_notice først; hvis evaluator kun mangler disclosure, vis den i stedet.
                  $askSeparate = in_array('separate_contract_notice', $missing ?? [], true);
                  $askDisclosure = (!$askSeparate) && in_array('through_ticket_disclosure', $missing ?? [], true);
                  $initShowSep = $askSeparate || (!$askDisclosure && !$askSeparate); // default til sep
                  $initShowDis = $askDisclosure && !$askSeparate;
                  $vSimple = (string)($form['separate_contract_notice'] ?? '');
                  $td = (string)($form['through_ticket_disclosure'] ?? '');
                ?>
                <div id="s6_q_sep" class="<?= $initShowSep ? '' : 'hidden' ?>">
                  <div class="small mt8">Står der på billetter/kvittering, at billetterne er særskilte befordringskontrakter?</div>
                  <label><input type="radio" name="separate_contract_notice" value="yes" <?= $vSimple==='yes'?'checked':'' ?> /> Ja</label>
                  <label class="ml8"><input type="radio" name="separate_contract_notice" value="no" <?= $vSimple==='no'?'checked':'' ?> /> Nej</label>
                </div>
                <div id="s6_q_dis" class="<?= $initShowDis ? '' : 'hidden' ?>">
                  <div class="small mt8">Blev du tydeligt informeret før køb, om dine billetter var gennemgående eller ej?</div>
                  <label><input type="radio" name="through_ticket_disclosure" value="yes" <?= $td==='yes'?'checked':'' ?> /> Ja</label>
                  <label class="ml8"><input type="radio" name="through_ticket_disclosure" value="no" <?= $td==='no'?'checked':'' ?> /> Nej</label>
                </div>
              </div>

              <script>
                // TRIN 6: klientlogik for visning og autosubmit
                (function(){
                  function toggleT4T5BySep() {
                    var sepSel = document.querySelector('input[name="separate_contract_notice"]:checked');
                    var showSep = true, showDis = false;
                    if (sepSel && sepSel.value === 'yes') { showSep = false; showDis = true; }
                    var sepDiv = document.getElementById('s6_q_sep');
                    var disDiv = document.getElementById('s6_q_dis');
                    if (sepDiv) sepDiv.classList.toggle('hidden', !showSep);
                    if (disDiv) disDiv.classList.toggle('hidden', !showDis);
                  }

                  function s6Update() {
                    var pnrCount = parseInt(document.getElementById('pnrCount')?.value || '0', 10);
                    var sc = document.querySelector('input[name="seller_channel"]:checked');
                    var st = document.querySelector('input[name="same_transaction"]:checked');
                    var sameTxnWrap = document.getElementById('sameTxnWrap');
                    if (sameTxnWrap) sameTxnWrap.classList.toggle('hidden', !(pnrCount > 1));

                    var t4t5Wrap = document.getElementById('t4t5Wrap');
                    var sellerWrap = document.getElementById('sellerWrap');
                    var autoBadgeOp = document.getElementById('autoBadgeOp');
                    var autoBadgeSep = document.getElementById('autoBadgeSep');
                    // Show exception questions only when relevant:
                    // "operator + 1 PNR" => only ask Q1 (seller). If seller is retailer, show TRIN 4/5.
                    var triShared = (document.getElementById('sharedScopeTri')?.value || 'unknown') === 'yes';
                    var triSingleBook = (document.getElementById('singleBookingTri')?.value || 'unknown') === 'yes';
                    var operatorOne = (pnrCount === 1) || triShared || triSingleBook;
                    var showExceptions = true;
                    if (sc && sc.value === 'operator' && operatorOne) { showExceptions = false; }
                    // But if any required exception answer is still missing, force-show them to avoid evaluator staying 'Ukendt'
                    try {
                      var sepChecked = document.querySelector('input[name="separate_contract_notice"]:checked');
                      var disChecked = document.querySelector('input[name="through_ticket_disclosure"]:checked');
                      var needSep = !sepChecked; // must answer sep first
                      var needDis = !!(sepChecked && sepChecked.value === 'yes' && !disChecked);
                      if (needSep || needDis) { showExceptions = true; }
                    } catch(e) { /* no-op */ }
                    if (t4t5Wrap) t4t5Wrap.classList.toggle('hidden', !showExceptions);

                    // AUTO badges and hiding seller when fully determined
                    var autoOpOnePNR = (!!sc && sc.value === 'operator' && pnrCount === 1);
                    var autoSeparate = (pnrCount > 1 && !!st && st.value === 'no');
                    if (autoBadgeOp) autoBadgeOp.classList.toggle('hidden', !autoOpOnePNR);
                    if (autoBadgeSep) autoBadgeSep.classList.toggle('hidden', !autoSeparate);
                    // Keep seller question visible to prevent it from disappearing when other answers change
                    if (sellerWrap) sellerWrap.classList.remove('hidden');

                    toggleT4T5BySep();
                  }

                  // Keep single_txn_* hidden fields in sync with seller choice for minimal evaluator input
                  function syncSellerTxn(){
                    try {
                      var sc = document.querySelector('input[name="seller_channel"]:checked');
                      var opH = document.getElementById('s6_h_single_txn_operator');
                      var rtH = document.getElementById('s6_h_single_txn_retailer');
                      if (!opH || !rtH) return;
                      var v = sc ? sc.value : '';
                      if (v === 'operator') { opH.value = 'yes'; rtH.value = 'no'; }
                      else if (v === 'retailer') { opH.value = 'no'; rtH.value = 'yes'; }
                      else { opH.value = ''; rtH.value = ''; }
                    } catch(e) { /* no-op */ }
                  }

                  function bindAutosubmit(sel) {
                    if (!sel) return;
                    ['change','click','input'].forEach(function(ev){
                      sel.addEventListener(ev, function(){
                        s6Update();
                        syncSellerTxn();
                        if (typeof queueRecalc === 'function') { try { queueRecalc(); } catch(e){} }
                      });
                    });
                  }

                  document.addEventListener('DOMContentLoaded', function(){
                    // initial
                    s6Update();
                    syncSellerTxn();
                    // bind
                    document.querySelectorAll('input[name="seller_channel"]').forEach(bindAutosubmit);
                    document.querySelectorAll('input[name="same_transaction"]').forEach(bindAutosubmit);
                    document.querySelectorAll('input[name="separate_contract_notice"], input[name="through_ticket_disclosure"]').forEach(function(el){
                      ['change','click','input'].forEach(function(ev){
                        el.addEventListener(ev, function(){
                          toggleT4T5BySep();
                          if (typeof queueRecalc === 'function') { try { queueRecalc(); } catch(e){} }
                        });
                      });
                    });
                  });
                })();
              </script>

              <!-- PNR/booking scope afledes automatisk fra 3.2.7/uploadede billetter -->
            </div>
            <div></div>
          </div>
        </div>
        <script>
        (function(){
          // No auto-fill needed in the simplified TRIN 6 – we rely on two answers + AUTO.
        })();
        </script>
  <div id="s6Skip" class="small muted <?= $showArt12? ($autoOK? '' : 'hidden') : '' ?>"><?php if ($autoOK): ?>TRIN 6 springes over (AUTO).<?php else: ?>TRIN 6 springes over (ingen missed connection) – gå videre til næste trin.<?php endif; ?></div>
      </fieldset>

  <fieldset id="s7" class="fieldset mt12 <?= ($isCompleted || !empty($form['delayLikely60']) || !empty($reason_cancellation) || !empty($reason_missed_conn) || !empty($reason_delay)) ? '' : 'hidden' ?>">
        <legend>TRIN 7 · Dine valg (Art. 18)</legend>

        <?php if ($isCompleted): ?>
          <div class="card">
            <strong>Rejsen er afsluttet — hvad skete der?</strong>
            <div class="small muted mt8">Ved afgang, missed connection eller aflysning — ved ≥60 min. forsinkelse tilbydes nedenstående muligheder.</div>
            <?php $remedy = (string)($form['remedyChoice'] ?? ''); if ($remedy==='') { if (($form['trip_cancelled_return_to_origin'] ?? '')==='yes') { $remedy='refund_return'; } elseif (($form['reroute_same_conditions_soonest'] ?? '')==='yes') { $remedy='reroute_soonest'; } elseif (($form['reroute_later_at_choice'] ?? '')==='yes') { $remedy='reroute_later'; } } ?>
            <div class="mt8"><strong>Vælg præcis én mulighed</strong></div>
            <label><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Blev hele rejsen aflyst, og vendte du tilbage til udgangspunktet?</label><br/>
            <label><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Fik du tilbudt omlægning på tilsvarende vilkår ved først givne lejlighed?</label><br/>
            <label><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Ønskede du i stedet omlægning på et senere tidspunkt efter eget valg?</label>

            <!-- Hidden sync to legacy hooks -->
            <input type="hidden" id="tcr_sync_past" name="trip_cancelled_return_to_origin" value="<?= ($form['trip_cancelled_return_to_origin'] ?? '') ?>" />
            <input type="hidden" id="rsc_sync_past" name="reroute_same_conditions_soonest" value="<?= ($form['reroute_same_conditions_soonest'] ?? '') ?>" />
            <input type="hidden" id="rlc_sync_past" name="reroute_later_at_choice" value="<?= ($form['reroute_later_at_choice'] ?? '') ?>" />

            <div id="refundSectionPast" class="mt8 <?= $remedy==='refund_return' ? '' : 'hidden' ?>">
              <div class="mt8"><strong>Refusion</strong></div>
              <?php $rr = (string)($form['refund_requested'] ?? ''); ?>
              <div class="mt4">Anmodede du om refusion fra operatøren?</div>
              <label><input type="radio" name="refund_requested" value="yes" <?= $rr==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="refund_requested" value="no" <?= $rr==='no'?'checked':'' ?> /> Nej</label>
              <label class="ml8"><input type="radio" name="refund_requested" value="unknown" <?= ($rr===''||$rr==='unknown')?'checked':'' ?> /> Ved ikke</label>

              <?php $rf = (string)($form['refund_form_selected'] ?? ''); ?>
              <div class="mt8">Hvis ja, hvilken form for refusion?</div>
              <label><input type="radio" name="refund_form_selected" value="money" <?= $rf==='money'?'checked':'' ?> /> Kontant</label>
              <label class="ml8"><input type="radio" name="refund_form_selected" value="voucher" <?= $rf==='voucher'?'checked':'' ?> /> Voucher</label>
              <label class="ml8"><input type="radio" name="refund_form_selected" value="other" <?= $rf==='other'?'checked':'' ?> /> Andet</label>
            </div>

            <div id="rerouteSectionPast" class="mt12 <?= in_array($remedy, ['reroute_soonest','reroute_later'], true) ? '' : 'hidden' ?>">
              <div class="mt0"><strong>Omlægning</strong></div>
              <?php $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); ?>
              <div id="ri100PastWrap">
                <div class="mt8">Fik du besked om mulighederne for omlægning inden for 100 minutter? (Art. 18(3))
                  <span class="small muted">Vi bruger planlagt afgang + første omlægnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="unknown" <?= ($ri100===''||$ri100==='unknown')?'checked':'' ?> /> Ved ikke</label>
              </div>

              <?php $rec = (string)($form['reroute_extra_costs'] ?? ''); ?>
              <div class="mt8">Medførte omlægningen ekstra udgifter for dig? (højere klasse/andet transportmiddel)</div>
              <label><input type="radio" name="reroute_extra_costs" value="yes" <?= $rec==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="reroute_extra_costs" value="no" <?= $rec==='no'?'checked':'' ?> /> Nej</label>
              <label class="ml8"><input type="radio" name="reroute_extra_costs" value="unknown" <?= ($rec===''||$rec==='unknown')?'checked':'' ?> /> Ved ikke</label>
              <div class="grid-2 mt8 <?= $rec==='yes' ? '' : 'hidden' ?>" id="recWrapPast">
                <label>Beløb
                  <input type="number" step="0.01" name="reroute_extra_costs_amount" value="<?= h($form['reroute_extra_costs_amount'] ?? '') ?>" />
                </label>
                <label>Valuta
                  <input type="text" name="reroute_extra_costs_currency" value="<?= h($form['reroute_extra_costs_currency'] ?? '') ?>" placeholder="EUR" />
                </label>
              </div>

              <?php $dgc = (string)($form['downgrade_occurred'] ?? ''); ?>
              <div class="mt8">Blev du nedklassificeret pga. omlægning (lavere kategori end købt)?</div>
              <label><input type="radio" name="downgrade_occurred" value="yes" <?= $dgc==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="downgrade_occurred" value="no" <?= $dgc==='no'?'checked':'' ?> /> Nej</label>
              <div class="mt8 <?= $dgc==='yes' ? '' : 'hidden' ?>" id="dgcWrapPast">
                <label>Grundlag for delvis tilbagebetaling
                  <select name="downgrade_comp_basis">
                    <option value="" <?= empty($form['downgrade_comp_basis'])?'selected':'' ?>>-</option>
                    <option value="seat" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='seat')?'selected':'' ?>>Sæde</option>
                    <option value="couchette" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='couchette')?'selected':'' ?>>Ligge</option>
                    <option value="sleeper" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='sleeper')?'selected':'' ?>>Sove</option>
                  </select>
                </label>
              </div>

              <?php if (isset($profile['articles']['art18_3']) && $profile['articles']['art18_3'] === false): ?>
                <div class="small mt8 hl">⚠️ 100-min-reglen kan være undtaget her. Vi logger stadig udgifter og afprøver krav efter lokal praksis.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="card">
            <strong>Rejsen er ikke afsluttet — hvad ønsker du nu?</strong>
            <div class="small muted mt8">I en presset situation giver vi dig overblik over dine muligheder.</div>
            <?php $remedy = (string)($form['remedyChoice'] ?? ''); if ($remedy==='') { if (($form['trip_cancelled_return_to_origin'] ?? '')==='yes') { $remedy='refund_return'; } elseif (($form['reroute_same_conditions_soonest'] ?? '')==='yes') { $remedy='reroute_soonest'; } elseif (($form['reroute_later_at_choice'] ?? '')==='yes') { $remedy='reroute_later'; } } ?>
            <div class="mt8"><strong>Vælg præcis én mulighed</strong></div>
            <label><input type="radio" name="remedyChoice" value="refund_return" <?= $remedy==='refund_return'?'checked':'' ?> /> Ønsker du at aflyse hele rejsen og vende tilbage til udgangspunktet?</label><br/>
            <label><input type="radio" name="remedyChoice" value="reroute_soonest" <?= $remedy==='reroute_soonest'?'checked':'' ?> /> Ønsker du omlægning på tilsvarende vilkår ved først givne lejlighed?</label><br/>
            <label><input type="radio" name="remedyChoice" value="reroute_later" <?= $remedy==='reroute_later'?'checked':'' ?> /> Ønsker du omlægning til et senere tidspunkt efter eget valg?</label>

            <!-- Hidden sync to legacy hooks -->
            <input type="hidden" id="tcr_sync_now" name="trip_cancelled_return_to_origin" value="<?= ($form['trip_cancelled_return_to_origin'] ?? '') ?>" />
            <input type="hidden" id="rsc_sync_now" name="reroute_same_conditions_soonest" value="<?= ($form['reroute_same_conditions_soonest'] ?? '') ?>" />
            <input type="hidden" id="rlc_sync_now" name="reroute_later_at_choice" value="<?= ($form['reroute_later_at_choice'] ?? '') ?>" />

            <div id="refundSectionNow" class="mt8 <?= $remedy==='refund_return' ? '' : 'hidden' ?>">
              <div class="mt8"><strong>Refusion</strong></div>
              <?php $rr = (string)($form['refund_requested'] ?? ''); ?>
              <div class="mt4">Har du allerede anmodet om refusion?</div>
              <label><input type="radio" name="refund_requested" value="yes" <?= $rr==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="refund_requested" value="no" <?= $rr==='no'?'checked':'' ?> /> Nej</label>
              <label class="ml8"><input type="radio" name="refund_requested" value="unknown" <?= ($rr===''||$rr==='unknown')?'checked':'' ?> /> Ved ikke</label>

              <?php $rf = (string)($form['refund_form_selected'] ?? ''); ?>
              <div class="mt8">Hvis ja, hvilken form for refusion?</div>
              <label><input type="radio" name="refund_form_selected" value="money" <?= $rf==='money'?'checked':'' ?> /> Kontant</label>
              <label class="ml8"><input type="radio" name="refund_form_selected" value="voucher" <?= $rf==='voucher'?'checked':'' ?> /> Voucher</label>
              <label class="ml8"><input type="radio" name="refund_form_selected" value="other" <?= $rf==='other'?'checked':'' ?> /> Andet</label>
            </div>

            <div id="rerouteSectionNow" class="mt12 <?= in_array($remedy, ['reroute_soonest','reroute_later'], true) ? '' : 'hidden' ?>">
              <div class="mt0"><strong>Omlægning</strong></div>
              <?php $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); ?>
              <div id="ri100NowWrap">
                <div class="mt8">Er du blevet informeret om mulighederne for omlægning inden for 100 minutter efter planlagt afgang? (Art. 18(3))
                  <span class="small muted">Vi bruger planlagt afgang + første omlægnings-besked til at vurdere 100-min-reglen.</span>
                </div>
                <label><input type="radio" name="reroute_info_within_100min" value="yes" <?= $ri100==='yes'?'checked':'' ?> /> Ja</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="no" <?= $ri100==='no'?'checked':'' ?> /> Nej</label>
                <label class="ml8"><input type="radio" name="reroute_info_within_100min" value="unknown" <?= ($ri100===''||$ri100==='unknown')?'checked':'' ?> /> Ved ikke</label>
              </div>

              <?php $rec = (string)($form['reroute_extra_costs'] ?? ''); ?>
              <div class="mt8">Kommer omlægningen til at medføre ekstra udgifter for dig? (højere klasse/andet transportmiddel)</div>
              <label><input type="radio" name="reroute_extra_costs" value="yes" <?= $rec==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="reroute_extra_costs" value="no" <?= $rec==='no'?'checked':'' ?> /> Nej</label>
              <label class="ml8"><input type="radio" name="reroute_extra_costs" value="unknown" <?= ($rec===''||$rec==='unknown')?'checked':'' ?> /> Ved ikke</label>
              <div class="grid-2 mt8 <?= $rec==='yes' ? '' : 'hidden' ?>" id="recWrapNow">
                <label>Beløb
                  <input type="number" step="0.01" name="reroute_extra_costs_amount" value="<?= h($form['reroute_extra_costs_amount'] ?? '') ?>" />
                </label>
                <label>Valuta
                  <input type="text" name="reroute_extra_costs_currency" value="<?= h($form['reroute_extra_costs_currency'] ?? '') ?>" placeholder="EUR" />
                </label>
              </div>

              <?php $dgc = (string)($form['downgrade_occurred'] ?? ''); ?>
              <div class="mt8">Er du blevet nedklassificeret eller regner med at blive det pga. omlægningen?</div>
              <label><input type="radio" name="downgrade_occurred" value="yes" <?= $dgc==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="downgrade_occurred" value="no" <?= $dgc==='no'?'checked':'' ?> /> Nej</label>
              <div class="mt8 <?= $dgc==='yes' ? '' : 'hidden' ?>" id="dgcWrapNow">
                <label>Forventet grundlag for delvis tilbagebetaling
                  <select name="downgrade_comp_basis">
                    <option value="" <?= empty($form['downgrade_comp_basis'])?'selected':'' ?>>-</option>
                    <option value="seat" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='seat')?'selected':'' ?>>Sæde</option>
                    <option value="couchette" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='couchette')?'selected':'' ?>>Ligge</option>
                    <option value="sleeper" <?= (isset($form['downgrade_comp_basis'])&&$form['downgrade_comp_basis']==='sleeper')?'selected':'' ?>>Sove</option>
                  </select>
                </label>
              </div>

              <?php if (isset($profile['articles']['art18_3']) && $profile['articles']['art18_3'] === false): ?>
                <div class="small mt8 hl">⚠️ 100-min-reglen kan være undtaget her. Vi logger stadig udgifter og afprøver krav efter lokal praksis.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Kompensation fjernet fra TRIN 7; flyttet til TRIN 10 -->
        <script>
          // TRIN 7 (Art. 18): klientlogik for valg og sektioner
          (function(){
            function s7Update() {
              var remEl = document.querySelector('input[name="remedyChoice"]:checked');
              var val = remEl ? remEl.value : '';
              ['Past','Now'].forEach(function(suf){
                var refund = document.getElementById('refundSection' + suf);
                var reroute = document.getElementById('rerouteSection' + suf);
                if (refund) refund.classList.toggle('hidden', val !== 'refund_return');
                if (reroute) reroute.classList.toggle('hidden', !(val === 'reroute_soonest' || val === 'reroute_later'));

                var noteR = document.getElementById('assistNoteRefund' + suf);
                var noteS = document.getElementById('assistNoteSoonest' + suf);
                var noteL = document.getElementById('assistNoteLater' + suf);
                if (noteR) noteR.classList.toggle('hidden', val !== 'refund_return');
                if (noteS) noteS.classList.toggle('hidden', val !== 'reroute_soonest');
                if (noteL) noteL.classList.toggle('hidden', val !== 'reroute_later');

                var tcr = document.getElementById('tcr_sync_' + suf.toLowerCase());
                var rsc = document.getElementById('rsc_sync_' + suf.toLowerCase());
                var rlc = document.getElementById('rlc_sync_' + suf.toLowerCase());
                if (tcr) tcr.value = (val === 'refund_return') ? 'yes' : '';
                if (rsc) rsc.value = (val === 'reroute_soonest') ? 'yes' : '';
                if (rlc) rlc.value = (val === 'reroute_later') ? 'yes' : '';
              });

              if (typeof queueRecalc === 'function') { try { queueRecalc(); } catch(e){} }
            }

            document.addEventListener('DOMContentLoaded', function(){
              s7Update();
              document.querySelectorAll('input[name="remedyChoice"]').forEach(function(el){
                ['change','click','input'].forEach(function(ev){ el.addEventListener(ev, s7Update); });
              });
            });
          })();
        </script>
      </fieldset>

      

      <?php $isCompleted = (!empty($flags['travel_state']) && $flags['travel_state']==='completed'); ?>
  <fieldset id="s8" class="fieldset mt12 <?= ($isCompleted || !empty($form['delayLikely60']) || !empty($reason_cancellation) || !empty($reason_missed_conn) || !empty($reason_delay)) ? '' : 'hidden' ?>">
        <legend>TRIN 8 · Assistance og udgifter (Art. 20)</legend>
        <div class="small muted">Aktiveres ved forsinkelse ≥60 min, aflysning eller afbrudt forbindelse. Ekstraordinære forhold påvirker kun hotel-loft (max 3 nætter).</div>

        <?php if ($isCompleted): ?>
          <div class="card">
            <strong>Rejsen er afsluttet — hvad blev tilbudt/leveret mens du ventede?</strong>
            <?php $rem = (string)($form['remedyChoice'] ?? ''); ?>
            <div class="mt8" id="assistNotesPast">
              <div id="assistNoteRefundPast" class="<?= $rem==='refund_return' ? '' : 'hidden' ?> small hl">Rettigheder (ved refusion): Mad og drikke mens du venter; hotel hvis retur ikke var mulig samme dag; ret til returtransport.</div>
              <div id="assistNoteSoonestPast" class="<?= $rem==='reroute_soonest' ? '' : 'hidden' ?> small hl">Rettigheder (ved omlægning hurtigst muligt): Mad og drikke mens du venter; hotel hvis næste tog først næste dag.</div>
              <div id="assistNoteLaterPast" class="<?= $rem==='reroute_later' ? '' : 'hidden' ?> small hl">Rettigheder (omlægning senere efter ønske): Kun i den oprindelige forsinkelsesperiode indtil du traf dit valg.</div>
            </div>

            <div id="assistA_past">
            <div class="mt8"><strong>A) Tilbudt assistance</strong></div>
            <?php $mo = (string)($form['meal_offered'] ?? ''); ?>
            <div class="mt4">1. Fik du måltider/forfriskninger under ventetiden? (Art. 20(2)(a))</div>
            <label><input type="radio" name="meal_offered" value="yes" <?= $mo==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $mo==='no'?'checked':'' ?> /> Nej</label>

            <?php $ho = (string)($form['hotel_offered'] ?? ''); ?>
            <div class="mt8">2. Fik du hotel/indkvartering + transport dertil? (Art. 20(2)(b))</div>
            <label><input type="radio" name="hotel_offered" value="yes" <?= $ho==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $ho==='no'?'checked':'' ?> /> Nej</label>
            <?php $on = (string)($form['overnight_needed'] ?? ''); ?>
            <div class="mt4 <?= $ho==='no' ? '' : 'hidden' ?>" id="overnightWrapPast">
              <span>Hvis nej: Blev overnatning nødvendig?</span>
              <label class="ml8"><input type="radio" name="overnight_needed" value="yes" <?= $on==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $on==='no'?'checked':'' ?> /> Nej</label>
              <div class="small muted mt4">Ved ekstraordinære forhold kan hotel begrænses til 3 nætter.</div>
            </div>

            <?php $bt = (string)($form['blocked_train_alt_transport'] ?? ''); ?>
            <div class="mt8">3. Var toget blokeret på sporet — fik du transport væk? (Art. 20(2)(c))</div>
            <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
            </div>

            <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
            <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
            <div>4. Fik du alternative transporttjenester, hvis forbindelsen blev afbrudt? (Art. 20(3))</div>
            <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

            <div class="mt12"><strong>C) Dokumentation & udgifter</strong></div>
            <?php $exu = (string)($form['extra_expense_upload'] ?? ''); ?>
            <div>5. Har du haft udgifter (taxi, bus, hotel, mad osv.)? (Upload kvitteringer)</div>
            <input type="file" name="extra_expense_upload" />
            <?php if ($exu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($exu)) ?></div><?php endif; ?>
            <div class="grid-3 mt8">
              <label>Måltider (beløb)
                <input type="number" step="0.01" name="expense_breakdown_meals" value="<?= h($form['expense_breakdown_meals'] ?? '') ?>" />
              </label>
              <label>Hotel (nætter)
                <input type="number" step="1" name="expense_breakdown_hotel_nights" value="<?= h($form['expense_breakdown_hotel_nights'] ?? '') ?>" />
              </label>
              <label>Lokal transport (beløb)
                <input type="number" step="0.01" name="expense_breakdown_local_transport" value="<?= h($form['expense_breakdown_local_transport'] ?? '') ?>" />
              </label>
              <label>Andre beløb
                <input type="number" step="0.01" name="expense_breakdown_other_amounts" value="<?= h($form['expense_breakdown_other_amounts'] ?? '') ?>" />
              </label>
              <label>Valuta
                <input type="text" name="expense_breakdown_currency" value="<?= h($form['expense_breakdown_currency'] ?? '') ?>" placeholder="EUR" />
              </label>
            </div>

            <?php $dcr = (string)($form['delay_confirmation_received'] ?? ''); ?>
            <div class="mt8">6. Fik du skriftlig bekræftelse på forsinkelse/aflysning/mistet forbindelse? (Art. 20(4))</div>
            <label><input type="radio" name="delay_confirmation_received" value="yes" <?= $dcr==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="delay_confirmation_received" value="no" <?= $dcr==='no'?'checked':'' ?> /> Nej</label>
            <?php $dcu = (string)($form['delay_confirmation_upload'] ?? ''); ?>
            <div class="mt4">
              <input type="file" name="delay_confirmation_upload" />
              <?php if ($dcu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($dcu)) ?></div><?php endif; ?>
            </div>

            <div class="mt12"><strong>D) Ekstraordinære forhold</strong></div>
            <?php $ec = (string)($form['extraordinary_claimed'] ?? ''); ?>
            <div>7. Henviste operatøren til ekstraordinære forhold?</div>
            <label><input type="radio" name="extraordinary_claimed" value="yes" <?= $ec==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="extraordinary_claimed" value="no" <?= $ec==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="extraordinary_claimed" value="unknown" <?= ($ec===''||$ec==='unknown')?'checked':'' ?> /> Ved ikke</label>
            <?php $et = (string)($form['extraordinary_type'] ?? ''); ?>
            <div class="mt4 <?= $ec==='yes' ? '' : 'hidden' ?>" id="extraTypePast">
              <label>Type
                <select name="extraordinary_type">
                  <option value="" <?= $et===''?'selected':'' ?>>-</option>
                  <option value="weather" <?= $et==='weather'?'selected':'' ?>>Vejr</option>
                  <option value="natural_disaster" <?= $et==='natural_disaster'?'selected':'' ?>>Naturkatastrofe</option>
                  <option value="public_health" <?= $et==='public_health'?'selected':'' ?>>Folkesundhed</option>
                  <option value="other" <?= $et==='other'?'selected':'' ?>>Andet</option>
                </select>
              </label>
              <div class="small muted mt4">Dette påvirker kun loftet for hotel (op til 3 nætter), ikke dine øvrige assistance-krav.</div>
            </div>

            <?php if (isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false): ?>
              <div class="small mt8 hl">⚠️ Assistance (måltider/hotel/transport) kan være undtaget her. Vi logger dine udgifter og rejser krav efter lokale regler/kontraktvilkår.</div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="card">
            <strong>Rejsen er ikke afsluttet — hvad får du tilbudt mens du venter?</strong>
            <?php $rem = (string)($form['remedyChoice'] ?? ''); ?>
            <div class="mt8" id="assistNotesNow">
              <div id="assistNoteRefundNow" class="<?= $rem==='refund_return' ? '' : 'hidden' ?> small hl">Rettigheder (ved refusion): Mad og drikke mens du venter; hotel hvis retur ikke er mulig samme dag; ret til returtransport.</div>
              <div id="assistNoteSoonestNow" class="<?= $rem==='reroute_soonest' ? '' : 'hidden' ?> small hl">Rettigheder (ved omlægning hurtigst muligt): Mad og drikke mens du venter; hotel hvis næste tog først næste dag.</div>
              <div id="assistNoteLaterNow" class="<?= $rem==='reroute_later' ? '' : 'hidden' ?> small hl">Rettigheder (omlægning senere efter ønske): Kun i den oprindelige forsinkelsesperiode, indtil du træffer dit valg.</div>
            </div>

            <div id="assistA_now">
            <div class="mt8"><strong>A) Tilbudt assistance</strong></div>
            <?php $mo = (string)($form['meal_offered'] ?? ''); ?>
            <div class="mt4">1. Får du måltider/forfriskninger under ventetiden? (Art. 20(2)(a))</div>
            <label><input type="radio" name="meal_offered" value="yes" <?= $mo==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $mo==='no'?'checked':'' ?> /> Nej</label>

            <?php $ho = (string)($form['hotel_offered'] ?? ''); ?>
            <div class="mt8">2. Får du hotel/indkvartering + transport dertil? (Art. 20(2)(b))</div>
            <label><input type="radio" name="hotel_offered" value="yes" <?= $ho==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $ho==='no'?'checked':'' ?> /> Nej</label>
            <?php $on = (string)($form['overnight_needed'] ?? ''); ?>
            <div class="mt4 <?= $ho==='no' ? '' : 'hidden' ?>" id="overnightWrapNow">
              <span>Hvis nej: Bliver overnatning nødvendig?</span>
              <label class="ml8"><input type="radio" name="overnight_needed" value="yes" <?= $on==='yes'?'checked':'' ?> /> Ja</label>
              <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $on==='no'?'checked':'' ?> /> Nej</label>
              <div class="small muted mt4">Ved ekstraordinære forhold kan hotel begrænses til 3 nætter.</div>
            </div>

            <?php $bt = (string)($form['blocked_train_alt_transport'] ?? ''); ?>
            <div class="mt8">3. Er toget blokeret på sporet — får du transport væk? (Art. 20(2)(c))</div>
            <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
            </div>

            <div class="mt12"><strong>B) Alternative transporttjenester</strong></div>
            <?php $ap = (string)($form['alt_transport_provided'] ?? ''); ?>
            <div>4. Får du alternative transporttjenester, hvis forbindelsen er afbrudt? (Art. 20(3))</div>
            <label><input type="radio" name="alt_transport_provided" value="yes" <?= $ap==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="alt_transport_provided" value="no" <?= $ap==='no'?'checked':'' ?> /> Nej</label>

            <div class="mt12"><strong>C) Dokumentation & udgifter</strong></div>
            <?php $exu = (string)($form['extra_expense_upload'] ?? ''); ?>
            <div>5. Har du udgifter (taxi, bus, hotel, mad osv.)? (Upload kvitteringer)</div>
            <input type="file" name="extra_expense_upload" />
            <?php if ($exu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($exu)) ?></div><?php endif; ?>
            <div class="grid-3 mt8">
              <label>Måltider (beløb)
                <input type="number" step="0.01" name="expense_breakdown_meals" value="<?= h($form['expense_breakdown_meals'] ?? '') ?>" />
              </label>
              <label>Hotel (nætter)
                <input type="number" step="1" name="expense_breakdown_hotel_nights" value="<?= h($form['expense_breakdown_hotel_nights'] ?? '') ?>" />
              </label>
              <label>Lokal transport (beløb)
                <input type="number" step="0.01" name="expense_breakdown_local_transport" value="<?= h($form['expense_breakdown_local_transport'] ?? '') ?>" />
              </label>
              <label>Andre beløb
                <input type="number" step="0.01" name="expense_breakdown_other_amounts" value="<?= h($form['expense_breakdown_other_amounts'] ?? '') ?>" />
              </label>
              <label>Valuta
                <input type="text" name="expense_breakdown_currency" value="<?= h($form['expense_breakdown_currency'] ?? '') ?>" placeholder="EUR" />
              </label>
            </div>

            <?php $dcr = (string)($form['delay_confirmation_received'] ?? ''); ?>
            <div class="mt8">6. Får du skriftlig bekræftelse på forsinkelse/aflysning/mistet forbindelse? (Art. 20(4))</div>
            <label><input type="radio" name="delay_confirmation_received" value="yes" <?= $dcr==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="delay_confirmation_received" value="no" <?= $dcr==='no'?'checked':'' ?> /> Nej</label>
            <?php $dcu = (string)($form['delay_confirmation_upload'] ?? ''); ?>
            <div class="mt4">
              <input type="file" name="delay_confirmation_upload" />
              <?php if ($dcu !== ''): ?><div class="small muted mt4">Uploadet: <?= h(basename($dcu)) ?></div><?php endif; ?>
            </div>

            <div class="mt12"><strong>D) Ekstraordinære forhold</strong></div>
            <?php $ec = (string)($form['extraordinary_claimed'] ?? ''); ?>
            <div>7. Henviser operatøren til ekstraordinære forhold?</div>
            <label><input type="radio" name="extraordinary_claimed" value="yes" <?= $ec==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="extraordinary_claimed" value="no" <?= $ec==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="extraordinary_claimed" value="unknown" <?= ($ec===''||$ec==='unknown')?'checked':'' ?> /> Ved ikke</label>
            <?php $et = (string)($form['extraordinary_type'] ?? ''); ?>
            <div class="mt4 <?= $ec==='yes' ? '' : 'hidden' ?>" id="extraTypeNow">
              <label>Type
                <select name="extraordinary_type">
                  <option value="" <?= $et===''?'selected':'' ?>>-</option>
                  <option value="weather" <?= $et==='weather'?'selected':'' ?>>Vejr</option>
                  <option value="natural_disaster" <?= $et==='natural_disaster'?'selected':'' ?>>Naturkatastrofe</option>
                  <option value="public_health" <?= $et==='public_health'?'selected':'' ?>>Folkesundhed</option>
                  <option value="other" <?= $et==='other'?'selected':'' ?>>Andet</option>
                </select>
              </label>
              <div class="small muted mt4">Dette påvirker kun loftet for hotel (op til 3 nætter), ikke dine øvrige assistance-krav.</div>
            </div>

            <?php if (isset($profile['articles']['art20_2']) && $profile['articles']['art20_2'] === false): ?>
              <div class="small mt8 hl">⚠️ Assistance (måltider/hotel/transport) kan være undtaget her. Vi logger dine udgifter og rejser krav efter lokale regler/kontraktvilkår.</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </fieldset>

      <!-- TRIN 9 · Billetinformation (flyttet op fra bunden) -->
      <?php
        $art10Applies = $profile['articles']['art10'] ?? true;
        $art9Hooks = (array)($art9['hooks'] ?? []);
        $art9Ask = (array)($art9['ask_hooks'] ?? []);
        $art9Banners = (array)($art9['ui_banners'] ?? []);
        // Map evaluator hook keys to our 10 interest buckets
        $interestMap = [
          'coc' => ['coc_acknowledged','coc_evidence_upload','civ_marking_present'],
          'fastest' => ['fastest_flag_at_purchase','mct_realistic','alts_shown_precontract'],
          'fares' => ['multiple_fares_shown','cheapest_highlighted','fare_flex_type','train_specificity'],
          'pmr' => ['pmr_user','pmr_booked','pmr_delivered_status','pmr_promised_missing'],
          'bike' => ['bike_reservation_type','bike_res_required','bike_denied_reason','bike_followup_offer','bike_delay_bucket'],
          'class' => ['fare_class_purchased','berth_seat_type','reserved_amenity_delivered','class_delivered_status'],
          'disruption' => ['preinformed_disruption','preinfo_channel','realtime_info_seen'],
          'facilities' => ['promised_facilities','facilities_delivered_status','facility_impact_note'],
          'through' => ['through_ticket_disclosure','single_txn_operator','single_txn_retailer','separate_contract_notice'],
          'complaint' => ['complaint_channel_seen','complaint_already_filed','complaint_receipt_upload','submit_via_official_channel'],
        ];
        $autoInterest = [];
        foreach ($interestMap as $key=>$hookList) {
          $autoInterest[$key] = false;
          foreach ($hookList as $hk) {
            if (in_array($hk, $art9Ask, true)) { $autoInterest[$key] = true; break; }
          }
        }
      ?>
      <fieldset id="s9" class="fieldset mt12">
        <legend>TRIN 9 · Billetinformation før køb (Art. 9)</legend>
        <div class="small muted">Art. 9(1) oplysninger gives på anmodning. Vælg de områder du vil uddybe; underspørgsmål vises efter behov.</div>
        <?php if (!empty($art9Banners)): ?>
          <div class="mt8 small hl-blue">
            <?php foreach ($art9Banners as $b): ?>
              <div>• <?= h($b) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="card mt8">
          <strong>Spørgsmål 1</strong>
          <div class="mt4">Har du anmodet om yderligere oplysninger i forbindelse med køb af billet?</div>
          <?php $ir = (string)($form['info_requested_pre_purchase'] ?? ''); ?>
          <label><input type="radio" name="info_requested_pre_purchase" value="yes" <?= $ir==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="info_requested_pre_purchase" value="no" <?= $ir==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <div class="card mt12">
          <strong>Spørgsmål 2</strong>
          <div class="mt4">Sæt kryds ved de emner, du vil uddybe (Annex II, del 1–10):</div>
          <?php
            $interests = [
              'coc'=>'1) Betingelser (CoC/CIV)',
              'fastest'=>'2) Hurtigste rejse og MCT',
              'fares'=>'3) Billetpriser og billigste pris',
              'pmr'=>'4) Tilgængelighed/PMR',
              'bike'=>'5) Cykelkapacitet/betingelser',
              'class'=>'6) Klasse/plads/ligge/sove',
              'disruption'=>'7) Afbrydelser/forsinkelser',
              'facilities'=>'8) Faciliteter/servicetilbud',
              'through'=>'9) Gennemgående billet-oplysning',
              'complaint'=>'10) Klageprocedure'
            ];
          ?>
          <div class="grid-2 mt8" id="art9_interests">
            <?php foreach ($interests as $k=>$label): $cb = array_key_exists('interest_'.$k, $form) ? !empty($form['interest_'.$k]) : (!empty($autoInterest[$k])); ?>
              <label><input type="checkbox" name="interest_<?= $k ?>" <?= $cb?'checked':'' ?> data-target="art9_<?= $k ?>" /> <?= h($label) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- 1) Betingelser (CoC/CIV) -->
        <?php $show = !empty($form['interest_coc']) || (!empty($autoInterest['coc'])); ?>
        <div id="art9_coc" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>1) Almindelige betingelser (CoC/CIV)</strong>
          <div class="small muted mt4">AUTO: Link/henvisning til operatørens betingelser. Vises hvis mangler/mismatch.</div>
          <?php $coc = (string)($form['coc_acknowledged'] ?? ($art9Hooks['coc_acknowledged'] ?? '')); ?>
          <div class="mt8">1. Så/Accepterede du betingelserne ved købet?</div>
          <label><input type="radio" name="coc_acknowledged" value="yes" <?= $coc==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="coc_acknowledged" value="no" <?= $coc==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="coc_acknowledged" value="unknown" <?= ($coc===''||$coc==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <div class="mt8">2. Link/pdf til betingelserne (upload valgfrit)</div>
          <input type="file" name="coc_evidence_upload" />
          <?php if (!empty($form['coc_evidence_upload'])): ?><div class="small muted mt4">Uploadet: <?= h(basename((string)$form['coc_evidence_upload'])) ?></div><?php endif; ?>
          <?php $civ = (string)($form['civ_marking_present'] ?? ($art9Hooks['civ_marking_present'] ?? '')); ?>
          <div class="mt8">3. Stod 'CIV' eller fælles regler på billetten? (AUTO)</div>
          <label><input type="radio" name="civ_marking_present" value="yes" <?= $civ==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="civ_marking_present" value="no" <?= $civ==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="civ_marking_present" value="unknown" <?= ($civ===''||$civ==='unknown')?'checked':'' ?> /> Ved ikke</label>
        </div>

        <!-- 2) Hurtigste rejse & MCT -->
        <?php $show = !empty($form['interest_fastest']) || (!empty($autoInterest['fastest'])); ?>
        <div id="art9_fastest" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>2) Køreplaner og hurtigste rejse</strong>
          <?php $ff = (string)($form['fastest_flag_at_purchase'] ?? ($art9Hooks['fastest_flag_at_purchase'] ?? '')); ?>
          <div class="mt8">1. Var rejsen markeret som 'hurtigste' eller 'anbefalet' ved købet?</div>
          <label><input type="radio" name="fastest_flag_at_purchase" value="yes" <?= $ff==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="fastest_flag_at_purchase" value="no" <?= $ff==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="fastest_flag_at_purchase" value="unknown" <?= ($ff===''||$ff==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $mct = (string)($form['mct_realistic'] ?? ($art9Hooks['mct_realistic'] ?? '')); ?>
          <div class="mt8">2. Var minimumsskiftetiden realistisk (missed station)?</div>
          <label><input type="radio" name="mct_realistic" value="yes" <?= $mct==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="mct_realistic" value="no" <?= $mct==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="mct_realistic" value="unknown" <?= ($mct===''||$mct==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $alts = (string)($form['alts_shown_precontract'] ?? ($art9Hooks['alts_shown_precontract'] ?? '')); ?>
          <div class="mt8">3. Så du alternative forbindelser ved købet?</div>
          <label><input type="radio" name="alts_shown_precontract" value="many" <?= $alts==='many'?'checked':'' ?> /> Ja, flere</label>
          <label class="ml8"><input type="radio" name="alts_shown_precontract" value="few" <?= $alts==='few'?'checked':'' ?> /> Kun få</label>
          <label class="ml8"><input type="radio" name="alts_shown_precontract" value="no" <?= $alts==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="alts_shown_precontract" value="unknown" <?= ($alts===''||$alts==='unknown')?'checked':'' ?> /> Ved ikke</label>
        </div>

        <!-- 3) Billetpriser -->
        <?php $show = !empty($form['interest_fares']) || (!empty($autoInterest['fares'])); ?>
        <div id="art9_fares" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>3) Billetpriser og billigste pris</strong>
          <?php $mf = (string)($form['multiple_fares_shown'] ?? ($art9Hooks['multiple_fares_shown'] ?? '')); ?>
          <div class="mt8">1. Fik du vist flere prisvalg for samme afgang?</div>
          <label><input type="radio" name="multiple_fares_shown" value="yes" <?= $mf==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="multiple_fares_shown" value="no" <?= $mf==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="multiple_fares_shown" value="unknown" <?= ($mf===''||$mf==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $ch = (string)($form['cheapest_highlighted'] ?? ($art9Hooks['cheapest_highlighted'] ?? '')); ?>
          <div class="mt8">2. Var 'billigste pris' markeret/anbefalet?</div>
          <label><input type="radio" name="cheapest_highlighted" value="yes" <?= $ch==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="cheapest_highlighted" value="no" <?= $ch==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="cheapest_highlighted" value="unknown" <?= ($ch===''||$ch==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $fft = (string)($form['fare_flex_type'] ?? ($art9Hooks['fare_flex_type'] ?? '')); ?>
          <div class="mt8">3. Vælg din købstype (AUTO):</div>
          <select name="fare_flex_type">
            <option value="" <?= $fft===''?'selected':'' ?>>-</option>
            <option value="nonflex" <?= $fft==='nonflex'?'selected':'' ?>>Standard/Non-flex</option>
            <option value="semiflex" <?= $fft==='semiflex'?'selected':'' ?>>Semi-flex</option>
            <option value="flex" <?= $fft==='flex'?'selected':'' ?>>Flex</option>
            <option value="pass" <?= $fft==='pass'?'selected':'' ?>>Abonnement/Periodekort</option>
            <option value="other" <?= $fft==='other'?'selected':'' ?>>Andet</option>
          </select>
          <?php $ts = (string)($form['train_specificity'] ?? ($art9Hooks['train_specificity'] ?? '')); ?>
          <div class="mt8">4. Gælder billetten kun for tognummer X (AUTO) eller 'any train that day'?</div>
          <label><input type="radio" name="train_specificity" value="specific" <?= $ts==='specific'?'checked':'' ?> /> Kun specifikt tog</label>
          <label class="ml8"><input type="radio" name="train_specificity" value="any_day" <?= $ts==='any_day'?'checked':'' ?> /> Vilkårlig afgang samme dag</label>
          <label class="ml8"><input type="radio" name="train_specificity" value="unknown" <?= ($ts===''||$ts==='unknown')?'checked':'' ?> /> Ved ikke</label>
        </div>

        <!-- 4) PMR -->
        <?php $show = !empty($form['interest_pmr']) || (!empty($autoInterest['pmr'])); ?>
        <div id="art9_pmr" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>4) Tilgængelighed/PMR</strong>
          <?php $pmru = (string)($form['pmr_user'] ?? ($art9Hooks['pmr_user'] ?? '')); ?>
          <div class="mt8">1. Har du et handicap eller nedsat mobilitet, som krævede assistance?</div>
          <label><input type="radio" name="pmr_user" value="yes" <?= $pmru==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $pmru==='no'?'checked':'' ?> /> Nej</label>
          <?php $pmrb = (string)($form['pmr_booked'] ?? ($art9Hooks['pmr_booked'] ?? '')); ?>
          <div class="mt8">2. Bestilte du assistance før rejsen?</div>
          <label><input type="radio" name="pmr_booked" value="yes" <?= $pmrb==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="pmr_booked" value="no" <?= $pmrb==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="pmr_booked" value="refused" <?= $pmrb==='refused'?'checked':'' ?> /> Forsøgte men fik afslag</label>
          <?php $pmrd = (string)($form['pmr_delivered_status'] ?? ($art9Hooks['pmr_delivered_status'] ?? '')); ?>
          <div class="mt8">3. Blev den bestilte assistance leveret?</div>
          <select name="pmr_delivered_status">
            <option value="" <?= $pmrd===''?'selected':'' ?>>-</option>
            <option value="full" <?= $pmrd==='full'?'selected':'' ?>>Ja, fuldt</option>
            <option value="partial" <?= $pmrd==='partial'?'selected':'' ?>>Delvist</option>
            <option value="none" <?= $pmrd==='none'?'selected':'' ?>>Nej</option>
          </select>
          <?php $pmrp = (string)($form['pmr_promised_missing'] ?? ($art9Hooks['pmr_promised_missing'] ?? '')); ?>
          <div class="mt8">4. Manglede lovede PMR-faciliteter?</div>
          <label><input type="radio" name="pmr_promised_missing" value="yes" <?= $pmrp==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="pmr_promised_missing" value="no" <?= $pmrp==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <!-- 5) Cykel -->
        <?php $show = !empty($form['interest_bike']) || (!empty($autoInterest['bike'])); ?>
        <div id="art9_bike" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>5) Cykelkapacitet/betingelser</strong>
          <?php $brt = (string)($form['bike_reservation_type'] ?? ($art9Hooks['bike_reservation_type'] ?? '')); ?>
          <div class="mt8">1. Var cyklen omfattet af billet/reservation? (AUTO)</div>
          <select name="bike_reservation_type">
            <option value="" <?= $brt===''?'selected':'' ?>>-</option>
            <option value="separate" <?= $brt==='separate'?'selected':'' ?>>Separat cykelreservation</option>
            <option value="included" <?= $brt==='included'?'selected':'' ?>>Inkluderet i billetten</option>
            <option value="not_required" <?= $brt==='not_required'?'selected':'' ?>>Ingen reservation krævet</option>
          </select>
          <?php $br = (string)($form['bike_res_required'] ?? ($art9Hooks['bike_res_required'] ?? '')); ?>
          <div class="mt8">2. Krævede denne afgang cykelreservation? (AUTO)</div>
          <label><input type="radio" name="bike_res_required" value="yes" <?= $br==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="bike_res_required" value="no" <?= $br==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="bike_res_required" value="unknown" <?= ($br===''||$br==='unknown')?'checked':'' ?> /> Ukendt</label>
          <?php $bd = (string)($form['bike_denied_reason'] ?? ($art9Hooks['bike_denied_reason'] ?? '')); ?>
          <div class="mt8">3. Blev cyklen afvist ved ombordstigning?</div>
          <select name="bike_denied_reason">
            <option value="" <?= $bd===''?'selected':'' ?>>-</option>
            <option value="none" <?= $bd==='none'?'selected':'' ?>>Nej</option>
            <option value="unreasoned" <?= $bd==='unreasoned'?'selected':'' ?>>Ja, uden begrundelse</option>
            <option value="capacity_safety_tech" <?= $bd==='capacity_safety_tech'?'selected':'' ?>>Ja, begrundet (plads/sikkerhed/teknisk)</option>
          </select>
          <?php $bf = (string)($form['bike_followup_offer'] ?? ($art9Hooks['bike_followup_offer'] ?? '')); ?>
          <div class="mt8">4. Hvis afvist: Fik du omlægning/refusion tilbudt?</div>
          <label><input type="radio" name="bike_followup_offer" value="reroute" <?= $bf==='reroute'?'checked':'' ?> /> Omlægning</label>
          <label class="ml8"><input type="radio" name="bike_followup_offer" value="refund" <?= $bf==='refund'?'checked':'' ?> /> Refusion</label>
          <label class="ml8"><input type="radio" name="bike_followup_offer" value="none" <?= $bf==='none'?'checked':'' ?> /> Intet</label>
          <?php $bb = (string)($form['bike_delay_bucket'] ?? ($art9Hooks['bike_delay_bucket'] ?? '')); ?>
          <div class="mt8">5. Forsinkelse ved ankomst pga. cykelhåndtering?</div>
          <select name="bike_delay_bucket">
            <option value="" <?= $bb===''?'selected':'' ?>>-</option>
            <option value="lt60" <?= $bb==='lt60'?'selected':'' ?>><60</option>
            <option value="60_119" <?= $bb==='60_119'?'selected':'' ?>>60–119</option>
            <option value="ge120" <?= $bb==='ge120'?'selected':'' ?>>≥120</option>
          </select>
        </div>

        <!-- 6) Klasse/plads/ligge/sove -->
        <?php $show = !empty($form['interest_class']) || (!empty($autoInterest['class'])); ?>
        <div id="art9_class" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>6) Klasse og reserverede faciliteter</strong>
          <?php $fcp = (string)($form['fare_class_purchased'] ?? ($art9Hooks['fare_class_purchased'] ?? '')); ?>
          <div class="mt8">1. Hvilken klasse var købt? (AUTO)</div>
          <select name="fare_class_purchased">
            <option value="" <?= $fcp===''?'selected':'' ?>>-</option>
            <option value="1" <?= $fcp==='1'?'selected':'' ?>>1. klasse</option>
            <option value="2" <?= $fcp==='2'?'selected':'' ?>>2. klasse</option>
            <option value="other" <?= $fcp==='other'?'selected':'' ?>>Andet</option>
            <option value="unknown" <?= $fcp==='unknown'?'selected':'' ?>>Ved ikke</option>
          </select>
          <?php $cds = (string)($form['class_delivered_status'] ?? ($art9Hooks['class_delivered_status'] ?? '')); ?>
          <div class="mt8">2. Fik du den klasse, du betalte for?</div>
          <label><input type="radio" name="class_delivered_status" value="ok" <?= $cds==='ok'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="class_delivered_status" value="downgrade" <?= $cds==='downgrade'?'checked':'' ?> /> Nej, nedklassificeret</label>
          <label class="ml8"><input type="radio" name="class_delivered_status" value="upgrade" <?= $cds==='upgrade'?'checked':'' ?> /> Nej, opgraderet</label>
          <?php $bst = (string)($form['berth_seat_type'] ?? ($art9Hooks['berth_seat_type'] ?? '')); ?>
          <div class="mt8">3. Var der reserveret plads/kupe/ligge/sove? (AUTO)</div>
          <select name="berth_seat_type">
            <option value="" <?= $bst===''?'selected':'' ?>>-</option>
            <option value="seat" <?= $bst==='seat'?'selected':'' ?>>Fast sæde</option>
            <option value="free" <?= $bst==='free'?'selected':'' ?>>Fri plads</option>
            <option value="couchette" <?= $bst==='couchette'?'selected':'' ?>>Liggevogn</option>
            <option value="sleeper" <?= $bst==='sleeper'?'selected':'' ?>>Sovevogn</option>
            <option value="none" <?= $bst==='none'?'selected':'' ?>>Ingen</option>
          </select>
          <?php $rad = (string)($form['reserved_amenity_delivered'] ?? ($art9Hooks['reserved_amenity_delivered'] ?? '')); ?>
          <div class="mt8">4. Blev reserveret plads/ligge/sove leveret?</div>
          <label><input type="radio" name="reserved_amenity_delivered" value="yes" <?= $rad==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reserved_amenity_delivered" value="no" <?= $rad==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reserved_amenity_delivered" value="partial" <?= $rad==='partial'?'checked':'' ?> /> Delvist</label>
        </div>

        <!-- 7) Afbrydelser/forsinkelser -->
        <?php $show = !empty($form['interest_disruption']) || (!empty($autoInterest['disruption'])); ?>
        <div id="art9_disruption" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>7) Afbrydelser og forsinkelser</strong>
          <?php if (!$art10Applies): ?>
            <div class="small hl mt4">Note: Art. 10 undtaget for denne rejse – krav om live-opdateringer (Art. 9(2)) gælder ikke.</div>
          <?php endif; ?>
          <?php $pid = (string)($form['preinformed_disruption'] ?? ($art9Hooks['preinformed_disruption'] ?? '')); ?>
          <div class="mt8">1. Var der meddelt afbrydelse/forsinkelse før dit køb?</div>
          <label><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="preinformed_disruption" value="unknown" <?= ($pid===''||$pid==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $pic = (string)($form['preinfo_channel'] ?? ($art9Hooks['preinfo_channel'] ?? '')); ?>
          <div class="mt8">2. Hvis ja: Hvor blev det vist?</div>
          <select name="preinfo_channel">
            <option value="" <?= $pic===''?'selected':'' ?>>-</option>
            <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
            <option value="operator_site_app" <?= $pic==='operator_site_app'?'selected':'' ?>>Operatør-site/app</option>
            <option value="ticket_overview" <?= $pic==='ticket_overview'?'selected':'' ?>>Billetoverblik</option>
            <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
          </select>
          <?php if ($art10Applies): $ris = (string)($form['realtime_info_seen'] ?? ($art9Hooks['realtime_info_seen'] ?? '')); ?>
          <div class="mt8">3. Så du realtime-opdateringer under rejsen?</div>
          <label><input type="radio" name="realtime_info_seen" value="app" <?= $ris==='app'?'checked':'' ?> /> Ja, i app</label>
          <label class="ml8"><input type="radio" name="realtime_info_seen" value="on_train" <?= $ris==='on_train'?'checked':'' ?> /> Ja, i toget</label>
          <label class="ml8"><input type="radio" name="realtime_info_seen" value="station" <?= $ris==='station'?'checked':'' ?> /> Ja, på station</label>
          <label class="ml8"><input type="radio" name="realtime_info_seen" value="no" <?= $ris==='no'?'checked':'' ?> /> Nej</label>
          <?php endif; ?>
        </div>

        <!-- 8) Faciliteter ombord -->
        <?php $show = !empty($form['interest_facilities']) || (!empty($autoInterest['facilities'])); ?>
        <div id="art9_facilities" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>8) Faciliteter i toget / servicetilbud</strong>
          <?php $pf = (array)($form['promised_facilities'] ?? ($art9Hooks['promised_facilities'] ?? [])); ?>
          <div class="mt8">1. Hvilke faciliteter var lovet? (vælg)</div>
          <label><input type="checkbox" name="promised_facilities[]" value="wifi" <?= in_array('wifi', $pf, true)?'checked':'' ?> /> Wi‑Fi</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="toilet" <?= in_array('toilet', $pf, true)?'checked':'' ?> /> Toilet</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="power" <?= in_array('power', $pf, true)?'checked':'' ?> /> Strøm</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="catering" <?= in_array('catering', $pf, true)?'checked':'' ?> /> Servering</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="family" <?= in_array('family', $pf, true)?'checked':'' ?> /> Familiezone</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="pmr_help" <?= in_array('pmr_help', $pf, true)?'checked':'' ?> /> PMR-hjælp</label>
          <label class="ml8"><input type="checkbox" name="promised_facilities[]" value="other" <?= in_array('other', $pf, true)?'checked':'' ?> /> Andet</label>
          <?php $fds = (string)($form['facilities_delivered_status'] ?? ($art9Hooks['facilities_delivered_status'] ?? '')); ?>
          <div class="mt8">2. Hvad fik du faktisk?</div>
          <label><input type="radio" name="facilities_delivered_status" value="same" <?= $fds==='same'?'checked':'' ?> /> Samme</label>
          <label class="ml8"><input type="radio" name="facilities_delivered_status" value="partial" <?= $fds==='partial'?'checked':'' ?> /> Kun delvist</label>
          <label class="ml8"><input type="radio" name="facilities_delivered_status" value="none" <?= $fds==='none'?'checked':'' ?> /> Slet ikke</label>
          <div class="mt8">3. Manglede en lovet facilitet og påvirkede det rejsen? (kort)</div>
          <textarea name="facility_impact_note" rows="3" style="width:100%"><?= h($form['facility_impact_note'] ?? '') ?></textarea>
        </div>

        <!-- 9) Gennemgående billet-oplysning -->
  <?php $show = (!empty($form['interest_through']) || (!empty($autoInterest['through']))) && empty($reason_missed_conn); ?>
        <div id="art9_through" class="card mt12 <?= $show?'':'hidden' ?>">
          <?php $art9ThroughDisabled = $show ? '' : 'disabled'; ?>
          <strong>9) Gennemgående vs. særskilte kontrakter</strong>
          <?php $ttd = (string)($form['through_ticket_disclosure'] ?? ($art9Hooks['through_ticket_disclosure'] ?? '')); ?>
          <div class="mt8">1. Blev du tydeligt informeret om kontrakt-typen?</div>
          <label><input type="radio" name="through_ticket_disclosure" value="Gennemgående" <?= $ttd==='Gennemgående'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Gennemgående billet</label>
          <label class="ml8"><input type="radio" name="through_ticket_disclosure" value="Særskilte" <?= $ttd==='Særskilte'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Særskilte kontrakter</label>
          <label class="ml8"><input type="radio" name="through_ticket_disclosure" value="Ved ikke" <?= ($ttd===''||$ttd==='Ved ikke')?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ved ikke</label>
          <?php $sto = (string)($form['single_txn_operator'] ?? ($art9Hooks['single_txn_operator'] ?? 'unknown')); ?>
          <div class="mt8">2. Køb i én transaktion hos operatøren? (AUTO)</div>
          <label><input type="radio" name="single_txn_operator" value="yes" <?= $sto==='yes'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="single_txn_operator" value="no" <?= $sto==='no'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="single_txn_operator" value="unknown" <?= ($sto===''||$sto==='unknown')?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ved ikke</label>
          <?php $str = (string)($form['single_txn_retailer'] ?? ($art9Hooks['single_txn_retailer'] ?? 'unknown')); ?>
          <div class="mt8">3. Én transaktion hos billetudsteder/rejsebureau? (AUTO)</div>
          <label><input type="radio" name="single_txn_retailer" value="yes" <?= $str==='yes'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="single_txn_retailer" value="no" <?= $str==='no'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="single_txn_retailer" value="unknown" <?= ($str===''||$str==='unknown')?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ved ikke</label>
          <?php $scn = (string)($form['separate_contract_notice'] ?? ($art9Hooks['separate_contract_notice'] ?? '')); ?>
          <div class="mt8">4. Var 'særskilte kontrakter' udtrykkeligt angivet?</div>
          <label><input type="radio" name="separate_contract_notice" value="Ja" <?= $scn==='Ja'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="separate_contract_notice" value="Nej" <?= $scn==='Nej'?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="separate_contract_notice" value="Ved ikke" <?= ($scn===''||$scn==='Ved ikke')?'checked':'' ?> <?= $art9ThroughDisabled ?> /> Ved ikke</label>
        </div>

        <!-- 10) Klageprocedure -->
        <?php $show = !empty($form['interest_complaint']) || (!empty($autoInterest['complaint'])); ?>
        <div id="art9_complaint" class="card mt12 <?= $show?'':'hidden' ?>">
          <strong>10) Procedurer for indgivelse af klager</strong>
          <?php $ccs = (string)($form['complaint_channel_seen'] ?? ($art9Hooks['complaint_channel_seen'] ?? '')); ?>
          <div class="mt8">1. Så du info om, hvor klagen skulle indsendes?</div>
          <label><input type="radio" name="complaint_channel_seen" value="yes" <?= $ccs==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="complaint_channel_seen" value="no" <?= $ccs==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="complaint_channel_seen" value="unknown" <?= ($ccs===''||$ccs==='unknown')?'checked':'' ?> /> Ved ikke</label>
          <?php $caf = (string)($form['complaint_already_filed'] ?? ($art9Hooks['complaint_already_filed'] ?? '')); ?>
          <div class="mt8">2. Har du allerede indsendt en klage?</div>
          <label><input type="radio" name="complaint_already_filed" value="yes" <?= $caf==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="complaint_already_filed" value="no" <?= $caf==='no'?'checked':'' ?> /> Nej</label>
          <div class="mt4"><input type="file" name="complaint_receipt_upload" />
          <?php if (!empty($form['complaint_receipt_upload'])): ?><div class="small muted mt4">Uploadet: <?= h(basename((string)$form['complaint_receipt_upload'])) ?></div><?php endif; ?></div>
          <?php $svc = (string)($form['submit_via_official_channel'] ?? ($art9Hooks['submit_via_official_channel'] ?? '')); ?>
          <div class="mt8">3. Ønsker du, at vi indsender via operatørens officielle kanal på dine vegne?</div>
          <label><input type="radio" name="submit_via_official_channel" value="yes" <?= $svc==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="submit_via_official_channel" value="no" <?= $svc==='no'?'checked':'' ?> /> Nej</label>
        </div>

      </fieldset>

      <!-- Hidden EU request mirror (no UI) -->
      <?php
        $remedy = (string)($form['remedyChoice'] ?? '');
        $band = (string)($form['compensationBand'] ?? '');
        $bMeals = (float)($form['expense_breakdown_meals'] ?? ($form['expense_meals'] ?? 0));
        $bHotelN = (int)($form['expense_breakdown_hotel_nights'] ?? 0);
        $bLocal = (float)($form['expense_breakdown_local_transport'] ?? 0);
        $bOther = (float)($form['expense_breakdown_other_amounts'] ?? ($form['expense_other'] ?? 0));
        $bUpload = (string)($form['extra_expense_upload'] ?? '');
        $recYes = ((string)($form['reroute_extra_costs'] ?? '') === 'yes');
        $recAmt = (float)($form['reroute_extra_costs_amount'] ?? 0);
        $hasExpenses = ($bMeals>0) || ($bHotelN>0) || ($bLocal>0) || ($bOther>0) || ($bUpload!=='') || $recYes || ($recAmt>0);
        $reqRefund = ($remedy==='refund_return');
        $reqComp60 = ($band==='60_119');
        $reqComp120 = ($band==='120_plus');
        $reqExpenses = $hasExpenses;
      ?>
      <div class="hidden">
        <input type="hidden" name="request_refund" id="req_refund" value="<?= $reqRefund ? '1' : '' ?>" />
        <input type="hidden" name="request_comp_60" id="req_comp60" value="<?= $reqComp60 ? '1' : '' ?>" />
        <input type="hidden" name="request_comp_120" id="req_comp120" value="<?= $reqComp120 ? '1' : '' ?>" />
        <input type="hidden" name="request_expenses" id="req_expenses" value="<?= $reqExpenses ? '1' : '' ?>" />
      </div>

  <!-- TRIN 10 · Kompensation (flyttet fra TRIN 13) -->
      <?php $art19On = !isset($profile['articles']['art19']) || $profile['articles']['art19'] !== false; ?>
      <fieldset id="s10" class="fieldset mt12" data-comp-wrap="1">
        <legend>TRIN 10 · Kompensation (Art. 19)</legend>
        <?php if (!$art19On): ?>
          <div class="card hl small">Kompensation (Art. 19) ser ud til at være undtaget for denne rejse. Vi kan stadig udarbejde krav efter lokale regler, hvor muligt.</div>
        <?php endif; ?>
        <div class="card mt8" id="compFinalWrap">
          <div class="small muted">Tærskel: endelig ankomstforsinkelse ≥ 60 min. Prisgrundlag afhænger af kontrakt-type (Art. 12) og billetdata.</div>
          <div class="grid-2 mt8">
            <label>Forsinkelse ved slutdestination (min)
              <input type="number" name="delayAtFinalMinutes" value="<?= h($delayAtFinal ?? '') ?>" oninput="queueRecalc()" />
            </label>
            <label>Niveau (auto eller manuelt)
              <select name="compensationBand" onchange="queueRecalc()">
                <option value="" <?= empty($form['compensationBand'])?'selected':'' ?>>-</option>
                <option value="60_119" <?= (isset($form['compensationBand'])&&$form['compensationBand']==='60_119')?'selected':'' ?>>60–119</option>
                <option value="120_plus" <?= (isset($form['compensationBand'])&&$form['compensationBand']==='120_plus')?'selected':'' ?>>≥120</option>
              </select>
            </label>
          </div>
          <div class="mt8">
            <label><input type="checkbox" name="voucherAccepted" <?= !empty($form['voucherAccepted'])?'checked':'' ?> onchange="queueRecalc()" /> Accepterer voucher (frivilligt)</label>
          </div>
          <div class="mt8 actions-row">
            <button type="submit" formaction="<?= $this->Url->build('/claims') ?>" class="button">Gå til krav</button>
            <button type="button" class="button" onclick="document.getElementById('flowOneForm').requestSubmit();">Opdater beregning</button>
          </div>
        </div>
      </fieldset>

      <fieldset id="sD" class="fieldset mt12">
        <legend>Kontakt (PDF felter)</legend>
        <div class="grid-2">
          <label>Navn
            <input type="text" name="name" value="<?= h($form['name'] ?? '') ?>" />
          </label>
          <label>Email
            <input type="email" name="email" value="<?= h($form['email'] ?? '') ?>" />
          </label>
        </div>
      </fieldset>

      <!-- TRIN 11 fjernet (vi udbetaler til vores konto). -->

      <fieldset id="s11" class="fieldset mt12">
        <legend>TRIN 11 · GDPR & yderligere oplysninger</legend>
        <label><input type="checkbox" name="gdprConsent" <?= !empty($form['gdprConsent'])?'checked':'' ?> /> Jeg accepterer behandling/deling af mine oplysninger iht. EU-formularens erklæring.</label>
        <br/>
        <label>Yderligere oplysninger (sektion 6)
          <textarea name="additionalInfo" rows="4" style="width:100%"><?= h($form['additionalInfo'] ?? '') ?></textarea>
        </label>
      </fieldset>


      

      <div class="actions-row mt16">
        <!-- Hidden fields for reimbursement endpoints -->
        <input type="hidden" name="reason_delay" value="<?= $reason_delay ? '1' : '' ?>" />
        <input type="hidden" name="reason_cancellation" value="<?= $reason_cancellation ? '1' : '' ?>" />
        <input type="hidden" name="reason_missed_conn" value="<?= $reason_missed_conn ? '1' : '' ?>" />
        <input type="hidden" name="additional_info" value="<?= h($additional_info) ?>" />
        <button type="submit">Gem/Opdater</button>
        <button type="submit" formaction="<?= $this->Url->build('/reimbursement/generate') ?>" formtarget="_blank" class="button">Generér PDF opsummering</button>
  <button id="officialBtnBottom" type="submit" formaction="<?= $this->Url->build('/reimbursement/official') ?>" formtarget="_blank" class="button official-btn" data-base-disabled="<?= $disableOfficial?'1':'0' ?>" <?= $disableOfficial?'disabled':'' ?>>Officiel EU-formular</button>
  <?php if (!empty($formDecision)) :
    $fd = $formDecision;
    $rec = (string)($fd['form'] ?? 'eu_standard_claim');
    $isEu = ($rec === 'eu_standard_claim');
    $label = $isEu ? 'Anbefalet: EU-formular' : (($rec === 'none') ? 'Anbefalet: ingen EU-formular' : 'Anbefalet: national formular');
    $reason = (string)($fd['reason'] ?? '');
  ?>
    <span style="margin-left:10px; padding:4px 8px; border-radius:4px; font-size:12px; <?= $isEu ? 'background:#e6f7ff;color:#006bb3;' : 'background:#fff7e6;color:#b36b00;' ?>" title="<?= h($reason) ?>">
      <?= h($label) ?>
    </span>
  <?php endif; ?>
      </div>
    </section>

    <aside class="preview">
      <div class="hooks-panel" id="hooksPanel">
        <?= $this->element('hooks_panel', compact('profile','art12','art9','claim','form','meta')) ?>
      </div>
    </aside>

    <aside class="card preview">
      <h3>Preview</h3>
      <?php if (!empty($additional_info)): ?>
        <p><strong>TRIN valg:</strong> <?= h($additional_info) ?></p>
      <?php endif; ?>
      <?php if (isset($section4_choice) && $section4_choice): ?>
        <p><strong>Sektion 4 valg:</strong> <?= h($section4_choice) ?><?= !empty($form['compensationBand'])? ' ('.$form['compensationBand'].')':'' ?></p>
      <?php endif; ?>
      <h4>Art. 12</h4>
      <pre><?= h(json_encode($art12, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      <h4>Art. 9 (on request)</h4>
      <?php if (!empty($compute['art9OptIn'])): ?>
        <pre><?= h(json_encode($art9, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      <?php else: ?>
        <p>Art. 9 vises kun hvis du markerer boksen.</p>
      <?php endif; ?>
      <h4>Refusion og assistance</h4>
      <ul>
        <li>Refusion: <?= !empty($refund['eligible']) ? 'Mulig' : 'Ikke mulig' ?></li>
        <li>Assistance (Art. 18): <?= h(implode(', ', (array)($refusion['options'] ?? []))) ?></li>
      </ul>
      <h4>Kompensation</h4>
      <ul>
        <li>Brutto: <?= number_format((float)($claim['gross'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
        <li>Gebyr: <?= number_format((float)($claim['fee'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
        <li>Netto: <?= number_format((float)($claim['net'] ?? 0), 2) ?> <?= h($claim['currency'] ?? 'EUR') ?></li>
      </ul>
      <?php if (isset($allow_refund) || isset($allow_compensation)): ?>
        <p><strong>Entitlements:</strong>
          Refusion <?= !empty($allow_refund)?'tilladt':'nej' ?>,
          Kompensation <?= !empty($allow_compensation)?'tilladt':'nej' ?>
        </p>
      <?php endif; ?>
      <p class="mt12 small">
        Hurtig test: <a href="<?= $this->Url->build('/reimbursement/official?debug=1&dx=0&dy=0') ?>" target="_blank">Officiel PDF (debug)</a>
      </p>
    </aside>
  </div>
<?= $this->Form->end() ?>
<script>
  // Left panel toggle (persisted in localStorage)
  (function(){
    var grid = document.getElementById('flowGrid');
    var btn = document.getElementById('toggleLeftBtn');
    if (!grid || !btn) return;
    var KEY = 'flow_hide_left';
    var apply = function(v){
      var on = (v === '1');
      grid.classList.toggle('no-left', on);
      btn.textContent = on ? 'Vis venstre panel' : 'Skjul venstre panel';
    };
    try {
      var stored = localStorage.getItem(KEY);
      if (stored === null) {
        // If not stored, default to whatever class is present on first render
        stored = grid.classList.contains('no-left') ? '1' : '0';
        localStorage.setItem(KEY, stored);
      }
      apply(stored);
    } catch(e) { apply(grid.classList.contains('no-left') ? '1' : '0'); }
    btn.addEventListener('click', function(){
      try {
        var cur = localStorage.getItem(KEY) || '0';
        var next = (cur === '1') ? '0' : '1';
        localStorage.setItem(KEY, next);
        apply(next);
      } catch(e) { apply(grid.classList.contains('no-left') ? '0' : '1'); }
    });
  })();
  // Expose exemption flags for client gating
  window.__artFlags = {
    scope: <?= json_encode($profile['scope'] ?? '') ?>,
    art: <?= json_encode($profile['articles'] ?? []) ?>,
    artSub: <?= json_encode($profile['articles_sub'] ?? []) ?>
  };
  (function(){
    var radios = document.querySelectorAll('input[name="incident_main"]');
    var missed = document.getElementById('missedRow');
    var stateRadios = document.querySelectorAll('input[name="travel_state"]');
    var actualCard = document.getElementById('actualJourneyCard');
    var delayLikely = document.getElementById('delayLikelyBox');
    var missedOnly = document.getElementById('missedOnlyCard');
    var missedIn33 = document.getElementById('missedStationIn33');
    var missedIn33Wrap = document.getElementById('missedIn33Wrap');
  var missedStandalone = document.getElementById('missedStationStandalone');
  var s5Screen = document.getElementById('s5Screening');
  var s5Skip = document.getElementById('s5SkipNote');
  var s6Art12 = document.getElementById('s6Art12');
  var s6Skip = document.getElementById('s6Skip');
  var art12Auto = document.getElementById('art12AutoOK');
  var officialBtns = document.querySelectorAll('.official-btn');
  var debugForceOfficial = (new URLSearchParams(window.location.search)).has('allow_official') || (new URLSearchParams(window.location.search)).has('debug');
  var delayLikelyCheckbox = document.querySelector('input[name="delayLikely60"]');
  var s5Fieldset = document.getElementById('s5');
  var s6Fieldset = document.getElementById('s6');
  var s7Fieldset = document.getElementById('s7');
  var s8Fieldset = document.getElementById('s8');
  var s9Fieldset = document.getElementById('s9');
  var delayLikelyWarn = document.getElementById('delayLikelyWarn');
    function update() {
      function setOfficialDisabled(flag) {
        try {
          officialBtns.forEach(function(btn){ btn.disabled = debugForceOfficial ? false : !!flag; });
        } catch(e) { /* no-op */ }
      }
  var selected = document.querySelector('input[name="incident_main"]:checked');
      if (missed) {
        if (selected && (selected.value === 'delay' || selected.value === 'cancellation')) {
          missed.classList.remove('hidden');
        } else {
          missed.classList.add('hidden');
        }
      }
  // Toggle TRIN 4 sub-sections based on TRIN 1 state and missed connection
  var isCompleted = !!(document.querySelector('input[name="travel_state"][value="completed"]') && document.querySelector('input[name="travel_state"][value="completed"]').checked);
  var isOngoing = !!(document.querySelector('input[name="travel_state"][value="ongoing"]') && document.querySelector('input[name="travel_state"][value="ongoing"]').checked);
  var started = isCompleted || isOngoing;
  var missedYes = !!(document.querySelector('input[name="missed_connection"][value="yes"]') && document.querySelector('input[name="missed_connection"][value="yes"]').checked);
  var needsDelayLikely = !isCompleted;
  var hasDelayLikely = !!(delayLikelyCheckbox && delayLikelyCheckbox.checked);
  var hasIncident = !!(selected && (selected.value === 'delay' || selected.value === 'cancellation'));
        if (isCompleted) {
        if (actualCard) actualCard.classList.remove('hidden');
        if (delayLikely) delayLikely.classList.add('hidden');
        if (missedOnly) missedOnly.classList.add('hidden');
        if (missedIn33) missedIn33.disabled = false;
        if (missedIn33Wrap) { if (missedYes) missedIn33Wrap.classList.remove('hidden'); else missedIn33Wrap.classList.add('hidden'); }
        if (missedStandalone) missedStandalone.disabled = true;
        if (s5Screen) s5Screen.classList.remove('hidden');
        if (s5Skip) s5Skip.classList.add('hidden');
        if (s5Fieldset) s5Fieldset.classList.remove('hidden');
        if (s6Fieldset) s6Fieldset.classList.remove('hidden');
        if (s7Fieldset) s7Fieldset.classList.remove('hidden');
          if (s8Fieldset) s8Fieldset.classList.remove('hidden');
    if (delayLikelyWarn) delayLikelyWarn.classList.add('hidden');
    var baseDisabled = false;
    if (officialBtns && officialBtns.length > 0) { baseDisabled = (officialBtns[0].getAttribute('data-base-disabled') === '1'); }
    setOfficialDisabled(baseDisabled);
      } else {
        if (actualCard) actualCard.classList.add('hidden');
        if (delayLikely) delayLikely.classList.remove('hidden');
        if (missedOnly) {
          if (missedYes) { missedOnly.classList.remove('hidden'); }
          else { missedOnly.classList.add('hidden'); }
        }
        if (missedIn33) missedIn33.disabled = true;
        if (missedIn33Wrap) missedIn33Wrap.classList.add('hidden');
        if (missedStandalone) missedStandalone.disabled = !missedYes;
        // TRIN 5 visibility depends on 'started' (ongoing or completed)
        if (started) {
          if (s5Screen) s5Screen.classList.remove('hidden');
          if (s5Skip) s5Skip.classList.add('hidden');
        } else {
          if (s5Screen) s5Screen.classList.add('hidden');
          if (s5Skip) s5Skip.classList.remove('hidden');
        }
        // Relaxed gating: show TRIN 5–7 when any of the following is true:
        // - rejsen er afsluttet
        // - ≥60 min. sandsynlig
        // - der er valgt delay/cancellation
        // - missed connection er Ja
        var show567 = isCompleted || hasDelayLikely || hasIncident || missedYes;
        if (s5Fieldset) s5Fieldset.classList.toggle('hidden', !show567);
        if (s6Fieldset) s6Fieldset.classList.toggle('hidden', !show567);
        if (s7Fieldset) s7Fieldset.classList.toggle('hidden', !show567);
        // TRIN 8: vis når samme betingelser, eller særskilt hvis cancellation/missed
        var showS8 = isCompleted || hasDelayLikely || hasIncident || missedYes;
        if (s8Fieldset) s8Fieldset.classList.toggle('hidden', !showS8);
  // TRIN 9: følg serverens gating (vises kun på anmodning); ingen tvangsåbning her
        // Delay-likely advarsel kun når ikke afsluttet, ikke ≥60, og heller ingen incident/missed
        var showDelayWarn = needsDelayLikely && !hasDelayLikely && !(hasIncident || missedYes);
        if (delayLikelyWarn) delayLikelyWarn.classList.toggle('hidden', !showDelayWarn);
        // Official-knapper: behold base disabled-state, ingen ekstra blokering her
        var baseDisabled2 = false;
        if (officialBtns && officialBtns.length > 0) { baseDisabled2 = (officialBtns[0].getAttribute('data-base-disabled') === '1'); }
        setOfficialDisabled(baseDisabled2);
      }
      // If user switched to 'No', clear both station inputs immediately to avoid residual values
      if (!missedYes) {
        if (missedIn33) missedIn33.value = '';
        if (missedStandalone) missedStandalone.value = '';
      }
      // TRIN 6 (Art.12) visibility: show only when missed connection = yes
      if (s6Art12 && s6Skip) {
        var autoOk = !!(art12Auto && art12Auto.value==='1');
        if (missedYes) {
          if (!autoOk) { s6Art12.classList.remove('hidden'); s6Skip.classList.add('hidden'); }
          else { s6Art12.classList.add('hidden'); s6Skip.classList.remove('hidden'); }
        } else {
          s6Art12.classList.add('hidden'); s6Skip.classList.remove('hidden');
        }
      }
      // Gate by exemptions
      try {
        var art = (window.__artFlags && window.__artFlags.art) || {};
        // Art. 18(3): hide the 100-min question blocks if exempt
        var a183 = !!(art['art18_3']);
        var riPast = document.getElementById('ri100PastWrap');
        var riNow = document.getElementById('ri100NowWrap');
        if (riPast) riPast.classList.toggle('hidden', !a183);
        if (riNow) riNow.classList.toggle('hidden', !a183);
        // Art. 20(2): hide assistance A blocks if exempt
        var a202 = !!(art['art20_2']);
        var aPast = document.getElementById('assistA_past');
        var aNow = document.getElementById('assistA_now');
        if (aPast) aPast.classList.toggle('hidden', !a202);
        if (aNow) aNow.classList.toggle('hidden', !a202);
  // Art. 19: gate compensation sections (now at TRIN 10)
  var compWraps = document.querySelectorAll('[data-comp-wrap="1"]');
  compWraps.forEach(function(el){ el.classList.toggle('hidden', art['art19']===false); });
  // Always hide TRIN 3 (kept for later revival)
  var s3 = document.getElementById('s3'); if (s3) s3.classList.add('hidden');
      } catch(e) { /* no-op */ }

      // TRIN 7 detail toggles (extra costs / downgrade)
      var recPast = document.getElementById('recWrapPast');
      var recNow = document.getElementById('recWrapNow');
      var dgcPast = document.getElementById('dgcWrapPast');
      var dgcNow = document.getElementById('dgcWrapNow');
      var recChecked = document.querySelector('input[name="reroute_extra_costs"]:checked');
      var dgcChecked = document.querySelector('input[name="downgrade_occurred"]:checked');
      var recVal = recChecked ? recChecked.value : null;
      var dgcVal = dgcChecked ? dgcChecked.value : null;
      if (recPast) { if (recVal === 'yes') recPast.classList.remove('hidden'); else recPast.classList.add('hidden'); }
      if (recNow) { if (recVal === 'yes') recNow.classList.remove('hidden'); else recNow.classList.add('hidden'); }
      if (dgcPast) { if (dgcVal === 'yes') dgcPast.classList.remove('hidden'); else dgcPast.classList.add('hidden'); }
      if (dgcNow) { if (dgcVal === 'yes') dgcNow.classList.remove('hidden'); else dgcNow.classList.add('hidden'); }

      // TRIN 8 dynamic toggles: overnight-needed shown when hotel_offered=no; extraordinary type when claimed=yes; assistance notes per remedy
      var ho = document.querySelector('input[name="hotel_offered"]:checked');
      var ec = document.querySelector('input[name="extraordinary_claimed"]:checked');
      var overnightPast = document.getElementById('overnightWrapPast');
      var overnightNow = document.getElementById('overnightWrapNow');
      var extraTypePast = document.getElementById('extraTypePast');
      var extraTypeNow = document.getElementById('extraTypeNow');
      if (overnightPast && ho) overnightPast.classList.toggle('hidden', ho.value !== 'no');
      if (overnightNow && ho) overnightNow.classList.toggle('hidden', ho.value !== 'no');
      if (extraTypePast && ec) extraTypePast.classList.toggle('hidden', ec.value !== 'yes');
      if (extraTypeNow && ec) extraTypeNow.classList.toggle('hidden', ec.value !== 'yes');

      // TRIN 5 – Live CIV status messaging (A+B)
      try {
        var hv = document.querySelector('input[name="hasValidTicket"]:checked');
        var smv = document.querySelector('input[name="safetyMisconduct"]:checked');
        var fvv = document.querySelector('input[name="forbiddenItemsOrAnimals"]:checked');
        var crv = document.querySelector('input[name="customsRulesBreached"]:checked');
        var op = document.querySelector('input[name="operatorStampedDisruptionProof"]:checked');
        var hasValid = hv ? (hv.value === 'yes') : true;
        var safetyMis = smv ? (smv.value === 'yes') : false;
        var forb = fvv ? (fvv.value === 'yes') : false;
        var customsOk = crv ? (crv.value === 'yes') : true; // yes = overholdt
        var proof = op ? (op.value === 'yes') : false;
        var selfInf = (!hasValid) || safetyMis || forb || (!customsOk);
        var elA = document.getElementById('civSelfInflictedWarn');
        var elB = document.getElementById('civOperatorProofWarn');
        var elOK = document.getElementById('civOkBadge');
        if (elA && elB && elOK) {
          elA.classList.toggle('hidden', !selfInf);
          var showB = (!selfInf) && (!proof);
          elB.classList.toggle('hidden', !showB);
          var showOK = (!selfInf) && proof;
          elOK.classList.toggle('hidden', !showOK);
        }
      } catch (e) { /* no-op */ }

      // TRIN 6 (Art. 12) – client-side gating for same transaction and TRIN 4/5 visibility
      try {
        var sameTxnWrap = document.getElementById('sameTxnWrap');
        var t4t5Wrap = document.getElementById('t4t5Wrap');
        var pnrCountEl = document.getElementById('pnrCount');
        var pnrCount = parseInt((pnrCountEl && pnrCountEl.value) ? pnrCountEl.value : '0', 10) || 0;
  if (sameTxnWrap) sameTxnWrap.classList.toggle('hidden', !(pnrCount > 1));
  var scChecked = document.querySelector('input[name="seller_channel"]:checked');
  var scVal = scChecked ? scChecked.value : '';
        var stChecked = document.querySelector('input[name="same_transaction"]:checked');
        var stVal = stChecked ? stChecked.value : '';
        var showT4T5 = false;
        if (scVal === 'retailer' || scVal === '') { // ukendt sælger behandles som retailer for TRIN 4/5-visning
          // When PNR is unknown (0) or single (1), allow answering TRIN 4/5 directly
          if (pnrCount <= 1) { showT4T5 = true; }
          else if (pnrCount > 1 && stVal === 'yes') { showT4T5 = true; }
        }
        // Operatør: tillad TRIN 4/5 hvis der slet ikke er PNR (pnrCount==0)
        if (scVal === 'operator' && pnrCount === 0) {
          showT4T5 = true;
        }
        if (t4t5Wrap) t4t5Wrap.classList.toggle('hidden', !showT4T5);

        // Sub-question toggle: show only ONE at a time
        var qSep = document.getElementById('s6_q_sep');
        var qDis = document.getElementById('s6_q_dis');
        var sepSel = document.querySelector('input[name="separate_contract_notice"]:checked');
        var showSep = showT4T5 && !(sepSel && sepSel.value === 'yes');
        var showDis = showT4T5 && (sepSel && sepSel.value === 'yes');
        if (qSep) qSep.classList.toggle('hidden', !showSep);
        if (qDis) qDis.classList.toggle('hidden', !showDis);
      } catch (e) { /* no-op */ }

      var remedy = document.querySelector('input[name="remedyChoice"]:checked');
      var rv = remedy ? remedy.value : '';
      var aidNotes = [
        'assistNoteRefundPast','assistNoteSoonestPast','assistNoteLaterPast',
        'assistNoteRefundNow','assistNoteSoonestNow','assistNoteLaterNow'
      ];
      aidNotes.forEach(function(id){ var el=document.getElementById(id); if (!el) return; el.classList.add('hidden'); });
      if (rv==='refund_return') { if (document.getElementById('assistNoteRefundPast')) document.getElementById('assistNoteRefundPast').classList.remove('hidden'); if (document.getElementById('assistNoteRefundNow')) document.getElementById('assistNoteRefundNow').classList.remove('hidden'); }
      else if (rv==='reroute_soonest') { if (document.getElementById('assistNoteSoonestPast')) document.getElementById('assistNoteSoonestPast').classList.remove('hidden'); if (document.getElementById('assistNoteSoonestNow')) document.getElementById('assistNoteSoonestNow').classList.remove('hidden'); }
  else if (rv==='reroute_later') { if (document.getElementById('assistNoteLaterPast')) document.getElementById('assistNoteLaterPast').classList.remove('hidden'); if (document.getElementById('assistNoteLaterNow')) document.getElementById('assistNoteLaterNow').classList.remove('hidden'); }
      // Debounced recalculation after compensation input changes
      var recalcTimer = null;
      window.queueRecalc = function(){
        if (recalcTimer) clearTimeout(recalcTimer);
        recalcTimer = setTimeout(function(){
          var f = document.getElementById('flowOneForm');
          if (f) {
            // Use AJAX to submit only the form and update hooks panel, preventing scroll-to-top
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname + '?ajax_hooks=1', true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function(){
              if (xhr.status === 200) {
                var hooksPanel = document.getElementById('hooksPanel');
                if (!hooksPanel) return;
                var txt = xhr.responseText || '';
                // Fallback: if server accidentally returns a full page, extract #hooksPanel content
                if (txt.indexOf('<html') !== -1 || txt.indexOf('<body') !== -1) {
                  try {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = txt;
                    var inner = tmp.querySelector('#hooksPanel');
                    hooksPanel.innerHTML = inner ? inner.innerHTML : txt;
                  } catch (e) {
                    hooksPanel.innerHTML = txt;
                  }
                } else {
                  hooksPanel.innerHTML = txt;
                }
                // After replacing hooks panel, sync tri-states and pnrCount into main hidden fields for TRIN 6 gating
                try {
                  var ajP = hooksPanel.querySelector('#aj_pnrCount');
                  var ajS = hooksPanel.querySelector('#aj_sharedTri');
                  var ajB = hooksPanel.querySelector('#aj_singleBookTri');
                  var pnrEl = document.getElementById('pnrCount');
                  var sharedEl = document.getElementById('sharedScopeTri');
                  var singleEl = document.getElementById('singleBookingTri');
                  if (pnrEl && ajP && ajP.value !== undefined) { pnrEl.value = ajP.value; }
                  if (sharedEl && ajS && ajS.value !== undefined) { sharedEl.value = ajS.value; }
                  if (singleEl && ajB && ajB.value !== undefined) { singleEl.value = ajB.value; }
                } catch (e) { /* ignore sync errors */ }
                // Re-run client-side visibility logic after sync
                try { if (typeof update === 'function') { update(); } } catch (e) { /* no-op */ }
              }
            };
      // TRIN 7 remedyChoice toggles and legacy hook sync
      var remedy = document.querySelector('input[name="remedyChoice"]:checked');
      var remedyVal = remedy ? remedy.value : '';
      var refundPast = document.getElementById('refundSectionPast');
      var reroutePast = document.getElementById('rerouteSectionPast');
      var refundNow = document.getElementById('refundSectionNow');
      var rerouteNow = document.getElementById('rerouteSectionNow');
      if (refundPast) refundPast.classList.toggle('hidden', remedyVal !== 'refund_return');
      if (reroutePast) reroutePast.classList.toggle('hidden', !(remedyVal === 'reroute_soonest' || remedyVal === 'reroute_later'));
      if (refundNow) refundNow.classList.toggle('hidden', remedyVal !== 'refund_return');
      if (rerouteNow) rerouteNow.classList.toggle('hidden', !(remedyVal === 'reroute_soonest' || remedyVal === 'reroute_later'));
      // hidden sync inputs if present
      var tcrSyncPast = document.getElementById('tcr_sync_past');
      var rscSyncPast = document.getElementById('rsc_sync_past');
      var rlcSyncPast = document.getElementById('rlc_sync_past');
      var tcrSyncNow = document.getElementById('tcr_sync_now');
      var rscSyncNow = document.getElementById('rsc_sync_now');
      var rlcSyncNow = document.getElementById('rlc_sync_now');
      var setVals = function(prefix) {
        if (prefix==='past') {
          if (tcrSyncPast) tcrSyncPast.value = (remedyVal==='refund_return') ? 'yes' : (tcrSyncPast.value || 'no');
          if (rscSyncPast) rscSyncPast.value = (remedyVal==='reroute_soonest') ? 'yes' : (rscSyncPast.value || 'no');
          if (rlcSyncPast) rlcSyncPast.value = (remedyVal==='reroute_later') ? 'yes' : (rlcSyncPast.value || 'no');
        } else if (prefix==='now') {
          if (tcrSyncNow) tcrSyncNow.value = (remedyVal==='refund_return') ? 'yes' : (tcrSyncNow.value || 'no');
          if (rscSyncNow) rscSyncNow.value = (remedyVal==='reroute_soonest') ? 'yes' : (rscSyncNow.value || 'no');
          if (rlcSyncNow) rlcSyncNow.value = (remedyVal==='reroute_later') ? 'yes' : (rlcSyncNow.value || 'no');
        }
      };
      setVals('past'); setVals('now');
      // Build FormData AFTER syncing any hidden legacy inputs so values are included
      var formData = new FormData(f);
      xhr.send(formData);
    }
  // TRIN 2 (incident) – react + autosubmit hooks
  radios.forEach(function(r){
    r.addEventListener('change', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('click', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('input', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
  });
  // TRIN 1 (status) – now autosubmits hooks on change as well
  stateRadios.forEach(function(r){
    r.addEventListener('change', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('click', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('input', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
  });
  var missedRadios = document.querySelectorAll('input[name="missed_connection"]');
  missedRadios.forEach(function(r){
    r.addEventListener('change', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('click', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    r.addEventListener('input', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
  });
  if (delayLikelyCheckbox) {
    delayLikelyCheckbox.addEventListener('change', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    delayLikelyCheckbox.addEventListener('click', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
  }
  // TRIN 1 numeric delay field autosubmits too
  var delayMinEuInput = document.querySelector('input[name="delay_min_eu"]');
  if (delayMinEuInput) {
    ['change','input','blur'].forEach(function(ev){ delayMinEuInput.addEventListener(ev, function(){ update(); if (window.queueRecalc) window.queueRecalc(); }); });
  }
  var radiosExtra = document.querySelectorAll('input[name="reroute_extra_costs"], input[name="downgrade_occurred"]');
  radiosExtra.forEach(function(r){ r.addEventListener('change', update); r.addEventListener('click', update); });
  // TRIN 5 (CIV screening) – trigger recalculation when answers change
  var trin5Inputs = document.querySelectorAll('input[name="hasValidTicket"], input[name="safetyMisconduct"], input[name="forbiddenItemsOrAnimals"], input[name="customsRulesBreached"], input[name="operatorStampedDisruptionProof"]');
  trin5Inputs.forEach(function(inp){
    inp.addEventListener('change', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
    inp.addEventListener('click', function(){ update(); if (window.queueRecalc) window.queueRecalc(); });
  });
  // TRIN 6 (Art. 12) – autosubmit on changes to update hooks panel live
  var s6Wrap = document.getElementById('s6');
  if (s6Wrap) {
    // Debounced autosubmit for all TRIN 6 inputs (Art. 12)
    var trin6Inputs = s6Wrap.querySelectorAll('input, select, textarea');
    trin6Inputs.forEach(function(inp){
      if (inp.type === 'file') return; // No file inputs in TRIN 6
      inp.addEventListener('change', function(){ update(); queueRecalc(); });
      inp.addEventListener('input', function(){ update(); queueRecalc(); });
      inp.addEventListener('blur', function(){ update(); queueRecalc(); });
    });
    // Failsafe: delegate at fieldset level for any dynamic fields
    s6Wrap.addEventListener('change', function(e){
      var t = e.target; if (!t) return; if (t.type === 'file') return;
      update(); queueRecalc();
    });
    s6Wrap.addEventListener('input', function(e){
      var t = e.target; if (!t) return;
      if (t.tagName === 'INPUT' || t.tagName === 'SELECT' || t.tagName === 'TEXTAREA') {
        update(); queueRecalc();
      }
    });
  }
  var radiosRemedy = document.querySelectorAll('input[name="remedyChoice"]');
  radiosRemedy.forEach(function(r){ r.addEventListener('change', function(){ update(); queueRecalc(); }); r.addEventListener('click', function(){ update(); queueRecalc(); }); });
  var radiosHotel = document.querySelectorAll('input[name="hotel_offered"]');
  radiosHotel.forEach(function(r){ r.addEventListener('change', function(){ update(); queueRecalc(); }); r.addEventListener('click', function(){ update(); queueRecalc(); }); });
  var radiosExtraClaim = document.querySelectorAll('input[name="extraordinary_claimed"]');
  radiosExtraClaim.forEach(function(r){ r.addEventListener('change', function(){ update(); queueRecalc(); }); r.addEventListener('click', function(){ update(); queueRecalc(); }); });
  // TRIN 7/8 related toggles affecting snapshots should also trigger autosubmit
  var radiosReroute = document.querySelectorAll('input[name="reroute_info_within_100min"], input[name="reroute_extra_costs"], input[name="downgrade_occurred"]');
  radiosReroute.forEach(function(r){ r.addEventListener('change', function(){ update(); queueRecalc(); }); r.addEventListener('click', function(){ update(); queueRecalc(); }); });
  var inputsReroute = document.querySelectorAll('input[name="reroute_extra_costs_amount"], input[name="reroute_extra_costs_currency"]');
  inputsReroute.forEach(function(inp){ inp.addEventListener('input', function(){ update(); queueRecalc(); }); inp.addEventListener('change', function(){ update(); queueRecalc(); }); });
  // Keep TRIN 9 in sync with compensation band and expense breakdown changes
  var compBand = document.querySelector('select[name="compensationBand"]');
  if (compBand) { compBand.addEventListener('change', update); }
  var expInputs = document.querySelectorAll('input[name="expense_breakdown_meals"], input[name="expense_breakdown_hotel_nights"], input[name="expense_breakdown_local_transport"], input[name="expense_breakdown_other_amounts"], input[name="expense_meals"], input[name="expense_other"]');
  expInputs.forEach(function(inp){ inp.addEventListener('input', function(){ update(); queueRecalc(); }); inp.addEventListener('change', function(){ update(); queueRecalc(); }); });
    // Failsafe: delegate changes at form level as well
    var formEl = document.getElementById('flowOneForm');
    if (formEl) {
      formEl.addEventListener('change', function(e){
        var t = e.target;
        if (!t) return;
  if (t.name === 'missed_connection' || t.name === 'travel_state' || t.name === 'incident_main' || t.name === 'reroute_extra_costs' || t.name === 'downgrade_occurred' || t.name === 'remedyChoice' || t.name === 'hotel_offered' || t.name === 'extraordinary_claimed' || t.name === 'compensationBand' || t.name === 'expense_breakdown_meals' || t.name === 'expense_breakdown_hotel_nights' || t.name === 'expense_breakdown_local_transport' || t.name === 'expense_breakdown_other_amounts' || t.name === 'expense_meals' || t.name === 'expense_other') { update(); if (window.queueRecalc) window.queueRecalc(); }
      });
    }
    update();
    // If TRIN 6 radios are already selected on load (e.g., after PRG), trigger one AJAX refresh
    try {
      var s6AnyChecked = document.querySelector('#s6 input[type="radio"]:checked');
      if (s6AnyChecked && typeof window.queueRecalc === 'function') { window.queueRecalc(); }
    } catch(e) { /* no-op */ }
  })();

  // TRIN 4 – autosubmit when operator/station/time/price fields change (affects profile/exemptions/claim)
  (function(){
    var fields = ['operator','operator_country','operator_product','dep_station','arr_station','dep_date','dep_time','arr_time','train_no','ticket_no','price','missed_connection_station'];
    fields.forEach(function(name){
      var el = document.querySelector('[name="'+name+'"]');
      if (!el) return;
      ['input','change','blur'].forEach(function(ev){ el.addEventListener(ev, function(){ if (window.queueRecalc) { queueRecalc(); } }); });
    });
  })();

  // TRIN 11 – autosubmit GDPR to update button gating
  (function(){
    var gdpr = document.querySelector('input[name="gdprConsent"]');
    if (!gdpr) return;
    ['change','click'].forEach(function(ev){ gdpr.addEventListener(ev, function(){ if (window.queueRecalc) queueRecalc(); }); });
  })();
  
  // Toggle Art. 9 subcards live when interests are checked/unchecked
  (function(){
    var boxWrap = document.getElementById('art9_interests');
    if (!boxWrap) return;
    boxWrap.addEventListener('change', function(e){
      var t = e.target; if (!t || t.type !== 'checkbox') return;
      var targetId = t.getAttribute('data-target'); if (!targetId) return;
      var el = document.getElementById(targetId); if (!el) return;
      el.classList.toggle('hidden', !t.checked);
      queueRecalc();
    });
  })();
  // Autosubmit on Art. 9 field changes to update hooks panel
  (function(){
    var s9 = document.getElementById('s9');
    if (!s9) return;
    s9.addEventListener('change', function(e){
      var t = e.target; if (!t) return;
      // For file inputs, trigger immediate recalculation on change
      queueRecalc();
    });
    s9.addEventListener('input', function(e){
      var t = e.target; if (!t) return;
      if (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT' ) {
        queueRecalc();
      }
    });
  })();
  // Trigger hooks refresh when any file input in the form changes (TRIN 4, 8, 9 uploads)
  (function(){
    var form = document.getElementById('flowOneForm');
    if (!form) return;
    var fileInputs = form.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(fi){
      fi.addEventListener('change', function(){ if (window.queueRecalc) window.queueRecalc(); });
    });
  })();
  // Autoscroll removed; PRG redirect adds #s4 only immediately after upload
  </script>
