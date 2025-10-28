# TRIN 6 — Code snapshot and visibility logic (Art. 12)

This document shows the concrete code implemented for TRIN 6 (Art. 12), including:
- The template markup (PHP + HTML) for TRIN 6
- The derived `pnrCount` and hidden field used by client gating
- The client-side gating logic controlling which questions appear

It’s extracted from `templates/Flow/one.php` on 2025‑10‑24.

This snapshot also includes TRIN 5 (CIV), TRIN 7 (Art. 18 remedies) and TRIN 8 (Art. 20 assistance) since they interact with TRIN 6’s gating and live panel.

---

## Template: TRIN 6 fieldset (PHP/HTML)

Source: `templates/Flow/one.php`

```php
<fieldset id="s6" class="fieldset mt12 <?= (!$isCompleted && empty($form['delayLikely60'])) ? 'hidden' : '' ?>">
  <legend>TRIN 6 · Art. 12 – gennemgående billet (missed connection)</legend>
  <?php $showArt12 = !empty($reason_missed_conn); $hooks = (array)($art12['hooks'] ?? []); $missing = (array)($art12['missing'] ?? []); $applies = $art12['art12_applies'] ?? null; $autoOK = (!empty($reason_missed_conn) && $applies === true); ?>
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

    <div class="grid-2 mt8">
      <div>
        <strong>Sælger og køb</strong><br/>
        <?php $sc = (string)($form['seller_channel'] ?? ''); ?>
        <div class="small mt4">Hvor købte du rejsen?</div>
        <label><input type="radio" name="seller_channel" value="operator" <?= $sc==='operator'?'checked':'' ?> /> Direkte hos togselskabet (operatøren)</label>
        <label class="ml8"><input type="radio" name="seller_channel" value="retailer" <?= $sc==='retailer'?'checked':'' ?> /> Hos rejsebureau/billetudsteder (tredjepart)</label>
        <?php
          // Gating-variabler baseret på flowet (TRIN 1–5)
          $st = (string)($form['same_transaction'] ?? '');
          $sharedPNR = ($pnrCount === 1);
          $showSameTxn = ($pnrCount > 1);
          $showT4T5 = false; // Skal TRIN 4/5 stilles?

          if ($sc === 'operator') {
            // Operatør: Hvis én PNR eller confirmed same txn, kan vi auto-konkludere og skjule TRIN 4/5
            if ($sharedPNR) {
              $showT4T5 = false;
            } elseif ($showSameTxn && $st === 'yes') {
              $showT4T5 = false;
            } elseif ($showSameTxn && $st === 'no') {
              // Allerede afgjort separate – TRIN 4/5 irrelevant
              $showT4T5 = false;
            } else {
              // Afvent svar på same_transaction først
              $showT4T5 = false;
            }
          } elseif ($sc === 'retailer') {
            // Rejsebureau/billetudsteder: Ved én PNR gå direkte til TRIN 4; ved flere PNR spørg same_transaction først
            if ($sharedPNR) {
              $showT4T5 = true;
            } elseif ($showSameTxn && $st === 'yes') {
              $showT4T5 = true;
            } else {
              $showT4T5 = false; // enten venter vi på svar, eller 'no' ⇒ separate (TRIN 2 stop)
            }
          }
        ?>

        <?php $st = (string)($form['same_transaction'] ?? ''); ?>
        <div id="sameTxnWrap" class="small mt8 <?= ($pnrCount>1)?'':'hidden' ?>">Var billetterne købt samlet i én transaktion?
          <label class="ml8"><input type="radio" name="same_transaction" value="yes" <?= $st==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="same_transaction" value="no" <?= $st==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <?php if ($sc==='operator' && $sharedPNR): ?>
          <div class="small badge mt8">AUTO: Gennemgående (stk. 3) – én PNR hos operatør</div>
        <?php elseif ($pnrCount>1 && $st==='no'): ?>
          <div class="small badge mt8">AUTO: Særskilt kontrakt – flere PNR og ikke samme transaktion</div>
        <?php endif; ?>

        <div id="t4t5Wrap" class="<?= $showT4T5 ? '' : 'hidden' ?>">
          <div class="small mt8">Blev du tydeligt informeret før køb, om dine billetter var gennemgående eller ej?</div>
          <?php $td = (string)($form['through_ticket_disclosure'] ?? ''); ?>
          <label><input type="radio" name="through_ticket_disclosure" value="yes" <?= $td==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="through_ticket_disclosure" value="no" <?= $td==='no'?'checked':'' ?> /> Nej</label>

          <?php $vSimple = (string)($form['separate_contract_notice'] ?? ''); ?>
          <div class="small mt8">Står der på billetter/kvittering, at billetterne er særskilte befordringskontrakter?</div>
          <label><input type="radio" name="separate_contract_notice" value="yes" <?= $vSimple==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="separate_contract_notice" value="no" <?= $vSimple==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <!-- PNR/booking scope afledes automatisk fra 3.2.7/uploadede billetter -->
      </div>
      <div>
        <!-- Right column intentionally left empty (debug removed) -->
      </div>
    </div>
  </div>
  <script>
  (function(){
    // No auto-fill needed in the simplified TRIN 6 – we rely on two answers + AUTO.
  })();
  </script>
  <div id="s6Skip" class="small muted <?= $showArt12? ($autoOK? '' : 'hidden') : '' ?>"><?php if ($autoOK): ?>TRIN 6 springes over (AUTO).<?php else: ?>TRIN 6 springes over (ingen missed connection) – gå videre til næste trin.<?php endif; ?></div>
</fieldset>
```

---

## Template: TRIN 5 fieldset (CIV screening)

Source: `templates/Flow/one.php`

```php
<fieldset id="s5" class="fieldset mt12 <?= (!$isCompleted && empty($form['delayLikely60'])) ? 'hidden' : '' ?>">
  <legend>TRIN 5 · CIV vurdering</legend>
  <p class="muted">Opdelt i to trin: A) Selvforskyldt hændelse? B) Dokumentation for operatørens ansvar.</p>
  <?php
    $travelState = $flags['travel_state'] ?? '';
    $showScreen = in_array($travelState, ['ongoing','completed'], true);
    $hasValid = isset($form['hasValidTicket']) ? (string)$form['hasValidTicket'] : 'yes';
    $sm = isset($form['safetyMisconduct']) ? (string)$form['safetyMisconduct'] : 'no';
    $fia = isset($form['forbiddenItemsOrAnimals']) ? (string)$form['forbiddenItemsOrAnimals'] : 'no';
    $crb = isset($form['customsRulesBreached']) ? (string)$form['customsRulesBreached'] : 'yes';
    $stamp = isset($form['operatorStampedDisruptionProof']) ? (string)$form['operatorStampedDisruptionProof'] : 'no';
  ?>
  <div id="s5Screening" class="card <?= $showScreen? '' : 'hidden' ?>">
    <h4>Trin A – Var forsinkelsen selvforskyldt?</h4>
    <!-- hasValidTicket / safetyMisconduct / forbiddenItemsOrAnimals / customsRulesBreached -->
    <h4>Trin B – Dokumentation for operatørens ansvar</h4>
    <!-- operatorStampedDisruptionProof -->
    <div class="mt8">
      <span id="civSelfInflictedWarn" class="warn <?= (isset($selfInflicted) && $selfInflicted) ? '' : 'hidden' ?>">…</span>
      <span id="civOperatorProofWarn" class="warn <?= (isset($operatorProof) && !$operatorProof && empty($selfInflicted)) ? '' : 'hidden' ?>">…</span>
      <span id="civOkBadge" class="small badge <?= (isset($liability_ok) && $liability_ok) ? '' : 'hidden' ?>">Ok – CIV vurdering bestået (A+B)</span>
    </div>
  </div>
  <div id="s5SkipNote" class="small muted <?= $showScreen? 'hidden' : '' ?>">TRIN 5 springes over – gå videre til TRIN 6.</div>
</fieldset>
```

Client-side updates inside `update()`:

```js
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
```

Visibility rules (CIV):

- Self-inflicted (any of: no valid ticket, misconduct, forbidden items, admin rules not complied) ⇒ block (A).
- Else require operator proof (stamp/record) ⇒ if missing, warn (B). If present, OK badge shown.

---

## Template: TRIN 7 fieldset (Art. 18 remedies)

Source: `templates/Flow/one.php`

```php
<fieldset id="s7" class="fieldset mt12 <?= (!$isCompleted && empty($form['delayLikely60'])) ? 'hidden' : '' ?>">
  <legend>TRIN 7 · Dine valg (Art. 18)</legend>
  <!-- remedyChoice: refund_return | reroute_soonest | reroute_later -->
  <!-- Past vs Now blocks render based on travel_state, each toggling refund/reroute sections -->
  <!-- Hidden sync to legacy hooks: tcr_sync_*, rsc_sync_*, rlc_sync_* -->
</fieldset>
```

Client-side updates inside `queueRecalc()` prior to AJAX send:

```js
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
```

Visibility rules (remedies):

- Exactly one of refund_return / reroute_soonest / reroute_later shown via radio; respective sections toggle.
- Hidden sync fields keep legacy names in step flow for backwards compatibility.

---

## Template: TRIN 8 fieldset (Art. 20 assistance)

Source: `templates/Flow/one.php`

```php
<fieldset id="s8" class="fieldset mt12 <?= (!$isCompleted && empty($form['delayLikely60'])) ? 'hidden' : '' ?>">
  <legend>TRIN 8 · Assistance og udgifter (Art. 20)</legend>
  <!-- Offered assistance: meal_offered, hotel_offered (+overnight_needed), blocked_train_alt_transport -->
  <!-- Alternative transport provided: alt_transport_provided -->
  <!-- Documentation & expenses: extra_expense_upload + breakdown fields -->
  <!-- Delay confirmation: delay_confirmation_received + upload -->
  <!-- Extraordinary circumstances: extraordinary_claimed (+extraordinary_type) -->
</fieldset>
```

Client-side gating inside `update()`:

```js
// TRIN 8 dynamic toggles: overnight-needed shown when hotel_offered=no; extraordinary type when claimed=yes
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
```

Visibility rules (assistance):

- Show “overnight needed?” only when hotel_offered = no.
- Show “extraordinary type” only when extraordinary_claimed = yes. Hotel nights may be capped by local exemptions.

---

## Client-side gating (JavaScript)

These lines run inside the `update()` function in `templates/Flow/one.php` and control TRIN 6 visibility dynamically based on seller, PNR count and same transaction:

```js
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
  if (scVal === 'retailer') {
    if (pnrCount === 1) { showT4T5 = true; }
    else if (pnrCount > 1 && stVal === 'yes') { showT4T5 = true; }
  }
  if (t4t5Wrap) t4t5Wrap.classList.toggle('hidden', !showT4T5);
} catch (e) { /* no-op */ }
```

---

## Visibility rules (concise)

- seller = operator
  - If single PNR: TRIN 4/5 hidden; auto badge shows “Gennemgående (stk. 3)”.
  - If multiple PNR and same_transaction = yes: TRIN 4/5 hidden (still gennemgående by flow).
  - If multiple PNR and same_transaction = no: TRIN 4/5 hidden; auto badge shows “Særskilt kontrakt”.
- seller = retailer (rejsebureau/billetudsteder)
  - If single PNR: show TRIN 4/5 questions.
  - If multiple PNR: show “samme transaktion?” first; if yes → show TRIN 4/5; if no → separate (TRIN 4/5 hidden).
- PNR count is auto-derived from `journey.bookingRef` + uploaded multi-tickets (`meta._multi_tickets[].pnr`).
- Debug/AUTO fields (old TRIN 7–13) are removed from the UI.

```text
Inputs: seller_channel ∈ {operator, retailer}, same_transaction ∈ {yes, no}, pnrCount (auto)
Outputs: show/hide sameTxnWrap; show/hide TRIN 4/5 block; auto decision badges where applicable
```
