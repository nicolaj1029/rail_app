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
<?= $this->Form->create(null) ?>

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

<div class="card">
  <strong>💶 Kompensations-beregning (Art. 19)</strong>
  <div class="small mt8">Grundregler</div>
  <ul class="small mt4">
    <li>Tærskel: endelig ankomstforsinkelse ≥ 60 min → 25 % (60–119) / 50 % (≥120) af prisgrundlaget.</li>
    <li>Ingen kompensation hvis refusion vælges for samme tjeneste, hvis forsinkelsen var oplyst før køb, eller omlægning gav endelig forsinkelse &lt; 60 min.</li>
    <li>Prisgrundlag afhænger af kontrakt/retur/gennemgående billet (Art. 19(3)); dit Art. 12-modul afgør ved tvivl.</li>
    <li>EU/ikke-EU: kun forsinkelsesminutter opstået i EU medregnes (prisgrundlag ændres ikke).</li>
  </ul>
  <?php if (!$art19Allowed): ?>
    <div class="bad mt8">⚠️ Art. 19 ser ud til at være undtaget for denne rejse/delstrækning. Beregning er slået fra.</div>
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
  <div class="small mt4">Hvis forsinkelsen er ≥ 60 min: vælg bånd for beregning. (Vi forslår automatisk ud fra minutter.)</div>
  <?php $sel = (string)($selectedBand ?? ($form['compensationBand'] ?? ($bandAuto === '0' ? '' : $bandAuto))); ?>
  <label class="mt8"><input type="radio" name="compensationBand" value="25" <?= $sel==='25'?'checked':'' ?> /> 25 % (60–119 min)</label>
  <label class="ml8"><input type="radio" name="compensationBand" value="50" <?= $sel==='50'?'checked':'' ?> /> 50 % (≥120 min)</label>
  <label class="ml8"><input type="radio" name="compensationBand" value="" <?= $sel===''?'checked':'' ?> /> Ikke relevant / under 60 min</label>
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

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
  <?= $this->Html->link('← Tilbage', ['action' => 'assistance'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Form->button('Næste →', ['class' => 'button']) ?>
</div>

<?= $this->Form->end() ?>
