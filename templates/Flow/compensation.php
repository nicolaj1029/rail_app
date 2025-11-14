<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$compute = $compute ?? [];
$incident = $incident ?? [];
$profile = $profile ?? ['articles'=>[]];
$delayAtFinal = (int)($delayAtFinal ?? 0);
$bandAuto = (string)($bandAuto ?? '0');
$refundChosen = (bool)($refundChosen ?? false);
$preinformed = (bool)($preinformed ?? false);
$rerouteUnder60 = (bool)($rerouteUnder60 ?? false);
$art19Allowed = (bool)($art19Allowed ?? true);
?>

<style>
  .small{font-size:12px}
  .muted{color:#666}
  .hl{background:#fff3cd;padding:6px;border-radius:6px}
  .ok{background:#e9f7ef;border:1px solid #d4edda;padding:6px;border-radius:6px}
  .bad{background:#fdecea;border:1px solid #f5c6cb;padding:6px;border-radius:6px}
  .card{padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px}
  .mt4{margin-top:4px}.mt8{margin-top:8px}.mt12{margin-top:12px}.ml8{margin-left:8px}
</style>

<h1>TRIN 6 · Kompensation (Art. 19)</h1>
<?php $art19Allowed = (bool)($art19Allowed ?? true); $articles = (array)($profile['articles'] ?? []); $art19Enabled = !isset($articles['art19']) || $articles['art19'] !== false; ?>
<?= $this->Form->create(null) ?>

<?php if (!empty($claim)): ?>
  <?php $br = (array)($claim['breakdown'] ?? []); $tot = (array)($claim['totals'] ?? []); ?>
  <div class="card" style="border-color:#cce5ff;background:#f3f6ff;">
    <strong>🔎 Hurtigt overblik</strong>
    <div class="small mt4">Kernefelter fra denne sag – opdateres live.</div>
    <ul class="small mt8" style="margin:0 0 0 16px;">
      <li>Endelig forsinkelse: <strong><?= (int)($delayAtFinal ?? 0) ?> min</strong> (band: <?= $bandAuto==='50'?'50%':($bandAuto==='25'?'25%':'<60') ?>)</li>
      <li>Kompensation pct: <strong><?= isset($br['compensation']['pct']) ? (int)$br['compensation']['pct'] : 0 ?>%</strong><?= !empty($br['compensation']['basis']) ? ' · basis: '.h($br['compensation']['basis']) : '' ?></li>
      <li>Beløb kompensation: <strong><?= isset($br['compensation']['amount']) ? number_format((float)$br['compensation']['amount'],2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?></strong></li>
      <li>Refusion (Art. 18): <?= isset($br['refund']['amount']) ? number_format((float)$br['refund']['amount'],2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?></li>
      <li>Udgifter (Art. 20): <?= isset($br['expenses']['total']) ? number_format((float)$br['expenses']['total'],2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?></li>
      <?php if (!empty($br['expenses']['alt_transport']) && (float)$br['expenses']['alt_transport']>0): ?>
        <li>Alt. transportandel: <?= number_format((float)$br['expenses']['alt_transport'],2) ?> <?= h($tot['currency'] ?? 'EUR') ?></li>
      <?php endif; ?>
      <?php if (!empty($claim['flags']['retailer_75'])): ?>
        <li>Retlig basis: <span class="badge" style="background:#ffe8cc;border:1px solid #ffc078;">Art. 12(4) 75%</span></li>
      <?php endif; ?>
      <?php if (!empty($seasonMode) && !empty($seasonSummary['cum_minutes_lt60'])): ?>
        <li>Periodekort – akkumulerede sub-60 min: <strong><?= (int)$seasonSummary['cum_minutes_lt60'] ?></strong></li>
      <?php endif; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if (!empty($seasonMode)): ?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>🟦 Abonnement/Periodekort (Art. 19, stk. 2)</strong>
  <div class="small mt4">For indehavere af abonnement/pendler-/periodekort kan gentagne forsinkelser eller aflysninger i gyldighedsperioden udløse kompensation efter operatørens ordning. Forsinkelser under 60 min kan kumuleres.</div>
  <?php $seasonMeta = (array)($meta['season_pass'] ?? []); ?>
  <?php if (!empty($seasonMeta)): ?>
    <div class="small mt8" style="display:flex;gap:12px;flex-wrap:wrap;">
      <?php if (!empty($seasonMeta['operator'])): ?><span>Operatør: <strong><?= h((string)$seasonMeta['operator']) ?></strong></span><?php endif; ?>
      <?php if (!empty($seasonMeta['type'])): ?><span>Type: <strong><?= h((string)$seasonMeta['type']) ?></strong></span><?php endif; ?>
      <?php if (!empty($seasonMeta['valid_from']) || !empty($seasonMeta['valid_to'])): ?>
        <span>Gyldighed: <strong><?= h((string)($seasonMeta['valid_from'] ?? '')) ?></strong> – <strong><?= h((string)($seasonMeta['valid_to'] ?? '')) ?></strong></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($seasonSummary) && is_array($seasonSummary)): ?>
    <?php $ss=(array)$seasonSummary; ?>
    <ul class="small mt8">
      <li>Antal registrerede hændelser (i denne sag/session): <strong><?= (int)($ss['count_total'] ?? 0) ?></strong></li>
      <li>Aflysninger: <strong><?= (int)($ss['count_cancel'] ?? 0) ?></strong></li>
      <li>Forsinkelser ≥ 60 min: <strong><?= (int)($ss['count_ge60'] ?? 0) ?></strong></li>
      <li>Forsinkelser 20–59 min: <strong><?= (int)($ss['count_20_59'] ?? 0) ?></strong></li>
      <li>Forsinkelser < 20 min: <strong><?= (int)($ss['count_lt20'] ?? 0) ?></strong></li>
      <li>Samlet min. under 60: <strong><?= (int)($ss['cum_minutes_lt60'] ?? 0) ?></strong> min</li>
    </ul>
    <div class="small muted">Bemærk: Den konkrete kompensation fastsættes af jernbanevirksomhedens offentlige kompensationsordning.</div>
  <?php else: ?>
    <div class="small mt8">Hændelsen er registreret for periodekort. Gentagende mindre forsinkelser kan tælles sammen.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($nationalPolicy) && is_array($nationalPolicy)): ?>
<?php $np=(array)$nationalPolicy; $thr=(array)($np['thresholds'] ?? []); ?>
<div class="card mt12" style="border-color:#ffe8cc;background:#fff8e6">
  <strong>🇪🇺→🇫🇷 Nationale ordninger (domestic)</strong>
  <div class="small mt4">Denne rejse ser ud til at være indenrigs i <strong><?= h((string)($np['country'] ?? '')) ?></strong>. National ordning: <strong><?= h((string)($np['name'] ?? '')) ?></strong><?php if(!empty($np['notes'])):?> — <?= h((string)$np['notes']) ?><?php endif; ?>.</div>
  <?php if (!empty($thr['25'])): ?>
    <div class="small mt8">Lempet bånd: <strong>25% fra <?= (int)$thr['25'] ?> min</strong><?php if (!empty($thr['50'])): ?>; <strong>50% fra <?= (int)$thr['50'] ?> min</strong><?php endif; ?>.</div>
  <?php endif; ?>
  <!-- Pass policy metadata forward (used by PDF/form generation and downstream logic) -->
  <input type="hidden" name="nationalPolicyCountry" id="nationalPolicyCountry" value="<?= h((string)($np['country'] ?? '')) ?>" />
  <input type="hidden" name="nationalPolicyName" id="nationalPolicyName" value="<?= h((string)($np['name'] ?? '')) ?>" />
  <input type="hidden" name="nationalPolicyId" id="nationalPolicyId" value="<?= h((string)($np['id'] ?? '')) ?>" />
<?php if (!empty($thr['25'])): ?>
  <input type="hidden" name="nationalPolicyThreshold25" id="nationalPolicyThreshold25" value="<?= (int)$thr['25'] ?>" />
<?php endif; ?>
<?php if (!empty($thr['50'])): ?>
  <input type="hidden" name="nationalPolicyThreshold50" id="nationalPolicyThreshold50" value="<?= (int)$thr['50'] ?>" />
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ((string)($this->getRequest()->getQuery('debug') ?? '') !== ''): ?>
<div class="card mt12" style="border-color:#cce5ff;background:#f1f8ff">
  <strong>Debug</strong>
  <div class="small mt4">URL: <code><?= h($this->getRequest()->getRequestTarget()) ?></code></div>
  <ul class="small mt4">
    <li>DelayAtFinal: <strong><?= (int)$delayAtFinal ?></strong> min · AutoBand: <strong><?= h($bandAuto) ?></strong> · SelectedBand: <strong><?= h($selectedBand ?? '-') ?></strong></li>
    <li>Ticket price captured: <strong><?= number_format((float)($ticketPriceAmount ?? 0),2) ?> <?= h($currency ?? 'EUR') ?></strong></li>
    <li>Gates — refundChosen: <strong><?= $refundChosen?'true':'false' ?></strong>, preinformed: <strong><?= $preinformed?'true':'false' ?></strong>, rerouteUnder60: <strong><?= $rerouteUnder60?'true':'false' ?></strong>, art19Allowed: <strong><?= $art19Allowed?'true':'false' ?></strong></li>
    <?php if (!empty($claim)): $br=(array)$claim['breakdown']; ?>
      <li>Comp: eligible=<strong><?= !empty($br['compensation']['eligible'])?'true':'false' ?></strong>, pct=<strong><?= (int)($br['compensation']['pct'] ?? 0) ?></strong>, basis=<strong><?= h($br['compensation']['basis'] ?? '') ?></strong>, amount=<strong><?= number_format((float)($br['compensation']['amount'] ?? 0),2) ?></strong></li>
    <?php endif; ?>
  </ul>
  <div class="small mt4 muted">Tip: Skift bånd hurtigt via ?band=25 eller ?band=50 i URL’en.</div>
  <div class="small muted">Hash (#fragment) ses i browserens adresselinje, men sendes ikke til serveren — derfor påvirker det ikke debug.</div>
  <div class="small muted">I Chrome DevTools: Åbn Network → klik på den seneste request → se “Request URL”. Den indeholder query (?debug=1), men aldrig hash (#...).</div>
</div>
<?php endif; ?>

<div class="card" data-art="19" <?= $art19Enabled ? '' : 'style="opacity:0.6;"' ?> >
  <strong>💶 Kompensations-beregning (Art. 19)</strong>
  <div class="small mt8">Grundregler</div>
  <ul class="small mt4">
    <li>Tærskel: endelig ankomstforsinkelse ≥ 60 min → 25 % (60–119) / 50 % (≥120) af prisgrundlaget.</li>
    <li>Ingen kompensation hvis refusion vælges for samme tjeneste, hvis forsinkelsen var oplyst før køb, eller omlægning gav endelig forsinkelse &lt; 60 min.</li>
    <li>Prisgrundlag afhænger af kontrakt/retur/gennemgående billet (Art. 19(3)); dit Art. 12-modul afgør ved tvivl.</li>
    <li>EU/ikke-EU: kun forsinkelsesminutter opstået i EU medregnes (prisgrundlag ændres ikke).</li>
  </ul>
  <?php if (!empty($liableParty)): ?>
    <div class="small mt8 muted">Ansvarlig part: <strong><?= h($liableParty === 'retailer' ? 'Billetudsteder' : 'Operatør') ?></strong><?= !empty($liableBasis) ? ' — ' . h($liableBasis) : '' ?></div>
  <?php endif; ?>
  <?php if (!empty($claim) && !empty($claim['flags']['retailer_75'])): ?>
    <div class="ok mt8">✅ Art. 12(4) gælder: Billetudsteder/refusionskrav for hele beløbet + kompensation på 75 % af transaktionsbeløbet (missed connection).</div>
  <?php endif; ?>
  <?php if (!$art19Enabled): ?>
    <div class="bad mt8">⚠️ Kompensation (Art. 19) er undtaget for denne rejse. Sektionen låses og beløb sættes til 0.</div>
  <?php elseif (!$art19Allowed): ?>
    <div class="bad mt8">⚠️ Art. 19 midlertidigt slået fra af andre gates (fx refusion valgt eller national undtagelse).</div>
  <?php endif; ?>
  <?php if ($refundChosen): ?>
    <div class="bad mt8">⚠️ Du har valgt refusion efter Art. 18 — der kan ikke ydes kompensation for samme tjeneste.</div>
  <?php endif; ?>
  <?php if ($preinformed): ?>
    <div class="hl mt8">ℹ️ Forsinkelsen var oplyst før køb (Art. 19(9)) — kompensation kan afvises.</div>
  <?php endif; ?>
  <?php if ($rerouteUnder60): ?>
    <div class="hl mt8">ℹ️ Omlægning gav endelig forsinkelse &lt; 60 min (Art. 19(9)) — kompensation kan afvises.</div>
  <?php endif; ?>
</div>

<div class="card mt12">
  <strong>1) Endelig ankomstforsinkelse</strong>
  <?php $knownDelay = (int)($form['delayAtFinalMinutes'] ?? $delayAtFinal); ?>
  <?php if ($knownDelay > 0): ?>
    <div class="small mt4">Vi har beregnet den endelige forsinkelse ved bestemmelsesstedet:</div>
    <div class="ok mt8"><strong><?= h($knownDelay) ?> min</strong> (EU-filter anvendes kun på forsinkelsesminutter, ikke prisgrundlaget).</div>
    <input type="hidden" name="delayAtFinalMinutes" value="<?= h($knownDelay) ?>" />
  <?php else: ?>
    <div class="small mt4">Udfyld kun hvis vi ikke allerede kender den. Brug faktiske ankomsttider ved endeligt bestemmelsessted.</div>
    <label class="mt8">Forsinkelse (minutter)
      <input type="number" name="delayAtFinalMinutes" min="0" step="1" value="<?= h($form['delayAtFinalMinutes'] ?? '') ?>" />
    </label>
  <?php endif; ?>
  <div class="small mt4">Auto-beregnet bånd: <strong><?= $bandAuto === '50' ? '50%' : ($bandAuto === '25' ? '25%' : '0% (under 60 min)') ?></strong></div>
</div>

<div class="card mt12">
  <strong>2) Vælg bånd (hvis relevant)</strong>
  <div class="small mt4">
    Hvis forsinkelsen er ≥ 60 min: vælg bånd for beregning. (Vi forslår automatisk ud fra minutter.)
    <?php if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds']['25'])): ?>
      <br /><span class="muted">Nationalt hint: <?= h((string)$nationalPolicy['name']) ?> anvender 25% allerede fra <?= (int)$nationalPolicy['thresholds']['25'] ?> min.</span>
    <?php endif; ?>
  </div>
  <?php
    $sel = (string)($selectedBand ?? ($form['compensationBand'] ?? ($bandAuto === '0' ? '' : $bandAuto)));
    $thr25 = null; $thr50 = null;
    if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds'])) {
      $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : null;
      $thr50 = isset($nationalPolicy['thresholds']['50']) ? (int)$nationalPolicy['thresholds']['50'] : null;
    }
    $label25 = ($thr25 !== null && $thr25 !== 60) ? ('25 % (≥' . (int)$thr25 . ' min)') : '25 % (60–119 min)';
    $label50 = ($thr50 !== null && $thr50 !== 120) ? ('50 % (≥' . (int)$thr50 . ' min)') : '50 % (≥120 min)';
    $labelNone = ($thr25 !== null && $thr25 !== 60) ? ('Ikke relevant / under ' . (int)$thr25 . ' min') : 'Ikke relevant / under 60 min';
  ?>
  <label class="mt8"><input type="radio" name="compensationBand" value="25" <?= $sel==='25'?'checked':'' ?> /> <?= h($label25) ?></label>
  <label class="ml8"><input type="radio" name="compensationBand" value="50" <?= $sel==='50'?'checked':'' ?> /> <?= h($label50) ?></label>
  <label class="ml8"><input type="radio" name="compensationBand" value="" <?= $sel===''?'checked':'' ?> /> <?= h($labelNone) ?></label>
  <script>
    // Lightweight instant preview: update URL with ?band=.. to re-render server-side without POSTing
    (function(){
      var radios = document.querySelectorAll('input[name="compensationBand"]');
      radios.forEach(function(r){ r.addEventListener('change', function(){
        var v = this.value; var url = new URL(window.location.href);
        url.searchParams.set('band', v);
        window.location.href = url.toString();
      }); });
    })();
  </script>
</div>

<div class="card mt12">
  <strong>3) Form og undtagelser</strong>
  <div class="small mt4">Udbetaling sker som udgangspunkt kontant. Vouchers accepteres ikke i denne løsning.</div>
  <input type="hidden" name="voucherAccepted" value="no" />

  <?php $exc = (string)($form['operatorExceptionalCircumstances'] ?? ''); ?>
  <div class="mt8">Henviser operatøren til ekstraordinære forhold (Art. 19(10))?</div>
  <label><input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc==='yes'?'checked':'' ?> /> Ja</label>
  <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc==='no'?'checked':'' ?> /> Nej</label>
  <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc===''||$exc==='unknown')?'checked':'' ?> /> Ved ikke</label>

  <?php $excType = (string)($form['operatorExceptionalType'] ?? ''); ?>
  <div class="small mt8">Hvis ja: vælg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)</div>
  <select name="operatorExceptionalType">
    <option value="">- Vælg type -</option>
    <option value="weather" <?= $excType==='weather'?'selected':'' ?>>Vejr</option>
    <option value="sabotage" <?= $excType==='sabotage'?'selected':'' ?>>Sabotage</option>
    <option value="infrastructure_failure" <?= $excType==='infrastructure_failure'?'selected':'' ?>>Infrastrukturfejl</option>
    <option value="third_party" <?= $excType==='third_party'?'selected':'' ?>>Tredjepart</option>
    <option value="own_staff_strike" <?= $excType==='own_staff_strike'?'selected':'' ?>>Egen personalestrejke</option>
    <option value="external_strike" <?= $excType==='external_strike'?'selected':'' ?>>Ekstern strejke</option>
    <option value="other" <?= $excType==='other'?'selected':'' ?>>Andet</option>
  </select>

  <div class="mt8">
    <label><input type="checkbox" name="minThresholdApplies" value="1" <?= !empty($form['minThresholdApplies']) ? 'checked' : '' ?> /> Anvend min. tærskel ≤ 4 EUR (Art. 19(8))</label>
  </div>
</div>

<?php if (!empty($claim)): ?>
<div class="card mt12">
  <strong>💶 Beløb pr. artikel (live)</strong>
  <?php $br = (array)($claim['breakdown'] ?? []); $tot = (array)($claim['totals'] ?? []); ?>
  <div class="small mt4">Pris fanget i trin 3: <strong><?= number_format((float)($ticketPriceAmount ?? 0), 2) ?> <?= h($currency ?? 'EUR') ?></strong> (basis: <?= h($br['compensation']['basis'] ?? '—') ?>)</div>
  <ul class="small mt8">
    <li><strong>Refusion (Art. 18)</strong>: <?= isset($br['refund']['amount']) ? number_format((float)$br['refund']['amount'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?><?= isset($br['refund']['basis']) && $br['refund']['basis'] ? ' — ' . h($br['refund']['basis']) : '' ?><?php if (isset($br['refund']['downgrade_component']) && (float)$br['refund']['downgrade_component']>0): ?> (inkl. nedgradering: <?= number_format((float)$br['refund']['downgrade_component'], 2) ?>)<?php endif; ?></li>
    <li><strong>Kompensation (Art. 19)</strong>: <?= isset($br['compensation']['amount']) ? number_format((float)$br['compensation']['amount'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?> — <?= h($br['compensation']['pct'] ?? 0) ?>% · <?= h($br['compensation']['basis'] ?? '') ?></li>
    <li><strong>Assistance/udgifter (Art. 20)</strong>: <?= isset($br['expenses']['total']) ? number_format((float)$br['expenses']['total'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?>
      <?php if (!empty($br['expenses']['alt_transport']) && (float)$br['expenses']['alt_transport']>0 && !empty($br['expenses']['alt_transport_label'])): ?>
        — heraf <?= number_format((float)$br['expenses']['alt_transport'], 2) ?> for <?= h($br['expenses']['alt_transport_label']) ?>
      <?php endif; ?>
    </li>
  </ul>
  <div class="small mt8">Samlet krav (brutto): <strong><?= isset($tot['gross_claim']) ? number_format((float)$tot['gross_claim'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?></strong></div>
</div>
<?php endif; ?>

<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>📄 Formularer</strong>
  <div class="small mt4">Generér enten EU standard-formular eller en national operatørform (hvis tilgængelig) for vedhæftning.</div>
  <?php $cc = strtolower((string)($nationalCountryCode ?? '')); $decision = (array)($formDecision ?? []); $recForm = (string)($decision['form'] ?? 'eu_standard_claim'); $reason = (string)($decision['reason'] ?? ''); ?>
  <div class="small mt8">
    Anbefaling: <strong><?= $recForm==='national_claim' ? 'National formular' : 'EU standard' ?></strong>
    <?php if($reason!==''): ?><span class="muted">(<?= h($reason) ?>)</span><?php endif; ?>
  </div>
  <?php $ccOk = ($recForm==='national_claim'); ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
    <!-- Official EU form (always from official action) -->
    <a class="button" style="background:#004085;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;" href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'official','?'=>['eu'=>'1']]) ?>" target="_blank">EU officiel formular</a>
    <!-- National official form via resolver and alt dir -->
    <?php if ($ccOk): ?>
      <a class="button" style="background:#005f5f;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;" href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'official','?'=>['prefer'=>'national','country'=>$cc]]) ?>" target="_blank">National officiel formular (<?= $cc!==''?h(strtoupper($cc)):'N/A' ?>)</a>
    <?php endif; ?>
    <!-- Summary PDF (previous generate action) -->
    <a class="button" style="background:#6c757d;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;" href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'generate','?'=>['flow'=>'1']]) ?>" target="_blank">Reimbursement Claim Summary</a>
    <?php if(!$ccOk): ?><div class="small muted" style="align-self:center;">National skabelon ikke fundet – EU anvendes.</div><?php endif; ?>
  </div>
  <div class="small mt8 muted">Efter generering kan du fortsætte til næste trin; formularerne åbner i ny fane.</div>
</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
  <?= $this->Html->link('← Tilbage', ['action' => 'assistance'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Form->button('Næste →', ['class' => 'button']) ?>
</div>

<?= $this->Form->end() ?>
