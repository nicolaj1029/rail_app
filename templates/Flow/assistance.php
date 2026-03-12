<?php

/** @var \App\View\AppView $this */

$form     = $form ?? [];

$flags    = $flags ?? [];

$incident = $incident ?? [];

$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);

$pmrUser      = strtolower((string)($form['pmr_user'] ?? $flags['pmr_user'] ?? '')) === 'yes';
$travelState  = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$multimodal = (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? 'rail'))));
$isFerry = ($transportMode === 'ferry');
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$assistTitle = $isOngoing
    ? ($isFerry ? 'TRIN 8 - Assistance under faergerejsen (igangvaerende rejse)' : 'TRIN 8 - Mad og drikke, hotel (igangvaerende rejse)')
    : ($isCompleted ? ($isFerry ? 'TRIN 8 - Assistance under faergerejsen (afsluttet rejse)' : 'TRIN 8 - Mad og drikke, hotel (afsluttet rejse)') : ($isFerry ? 'TRIN 8 - Assistance (faerge Art. 17)' : 'TRIN 8 - Mad og drikke, hotel (Art. 20)'));
$assistHint = $isOngoing
    ? ($isFerry ? 'Registrer maaltider, hotel og egne udgifter under faergeforloebet indtil nu.' : 'Udgifter indtil nu (du kan tilfoeje flere senere).')
    : ($isCompleted ? ($isFerry ? 'Registrer den assistance der blev tilbudt eller de udgifter du selv afholdt under faergerejsen.' : 'Udgifter under hele rejsen.') : '');
$assistMealsOff = ($articles['art20_2a'] ?? ($articles['art20_2'] ?? true)) === false;
$assistHotelOff = ($articles['art20_2b'] ?? ($articles['art20_2'] ?? true)) === false;
$assistTrackOff = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
$assistStationOff = ($articles['art20_3'] ?? true) === false;
$assistOff    = ($articles['art20_2'] ?? true) === false || ($assistMealsOff && $assistHotelOff && $assistTrackOff && $assistStationOff);
$art20Active = $art20Active ?? true;
$art20Partial = $art20Partial ?? false;
$art20Blocked = $art20Blocked ?? false;
$isPreview = !empty($flowPreview);



$currencyOptions = [

    'EUR' => 'EUR - Euro',

    'DKK' => 'DKK - Dansk krone',

    'SEK' => 'SEK - Svensk krona',

    'NOK' => 'NOK - Norsk krone',

    'GBP' => 'GBP - Britisk pund',

    'CHF' => 'CHF - Schweizisk franc',

    'PLN' => 'PLN - Polsk zloty',

    'CZK' => 'CZK - Tjekkisk koruna',

    'HUF' => 'HUF - Ungarsk forint',

    'BGN' => 'BGN - Bulgarsk lev',

    'RON' => 'RON - Rumænsk leu',

];



$v = fn(string $k): string => (string)($form[$k] ?? '');

$priceHints = $priceHints ?? ($meta['price_hints'] ?? $form['price_hints'] ?? []);

$hintText = function (string $key) use ($priceHints): string {

    if (!is_array($priceHints)) { return ''; }

    $h = $priceHints[$key] ?? null;

    if (!is_array($h) || !isset($h['min'], $h['max'], $h['currency'])) { return ''; }

    $min = number_format((float)$h['min'], 0, ',', '.');

    $max = number_format((float)$h['max'], 0, ',', '.');

    return "Typisk interval: {$min}–{$max} {$h['currency']}";

};

?>



<style>

  .hidden { display:none; }

  .small { font-size:12px; }

  .muted { color:#666; }

  .hl { background:#fff3cd; padding:6px; border-radius:6px; }

  .mt4 { margin-top:4px; }

  .mt8 { margin-top:8px; }

  .mt12 { margin-top:12px; }

  .ml8 { margin-left:8px; }

  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }

  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }

  [data-show-if] { display:none; }
  /* Locked preview should not expand conditional branches based on previous answers. */
  .flow-preview [data-show-if] { display:none !important; }

  /* Inline icon badges to avoid emoji encoding issues in headings */
  .icon-badge { width:26px; height:26px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; border:1px solid #d0d7de; background:#f8f9fb; }
  .icon-badge svg { width:16px; height:16px; display:block; }
  .icon-badge.hotel { background:#eef7ff; border-color:#cfe0ff; }
  .icon-badge.hotel svg path { fill:#1e3a8a; }

</style>



<h1><?= h($assistTitle) ?></h1>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['type' => 'file', 'novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
<?php if ($isFerry): ?>
  <input type="hidden" name="ferry_refreshments_offered" value="<?= h((string)($form['ferry_refreshments_offered'] ?? ($form['meal_offered'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_refreshments_self_paid_amount" value="<?= h((string)($form['ferry_refreshments_self_paid_amount'] ?? ($form['meal_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_refreshments_self_paid_currency" value="<?= h((string)($form['ferry_refreshments_self_paid_currency'] ?? ($form['meal_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_offered" value="<?= h((string)($form['ferry_hotel_offered'] ?? ($form['hotel_offered'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_overnight_required" value="<?= h((string)($form['ferry_overnight_required'] ?? ($form['overnight_needed'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_transport_included" value="<?= h((string)($form['ferry_hotel_transport_included'] ?? ($form['assistance_hotel_transport_included'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_amount" value="<?= h((string)($form['ferry_hotel_self_paid_amount'] ?? ($form['hotel_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_currency" value="<?= h((string)($form['ferry_hotel_self_paid_currency'] ?? ($form['hotel_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_nights" value="<?= h((string)($form['ferry_hotel_self_paid_nights'] ?? ($form['hotel_self_paid_nights'] ?? ''))) ?>" />
<?php endif; ?>



<p class="small muted">
  <?= $isFerry
      ? 'Aktiveres ved aflysning eller forventet/faktisk afgangsforsinkelse paa mindst 90 minutter. Hoteldelen kan bortfalde ved vejrsikkerhed.'
      : 'Aktiveres ved forsinkelse =60 min, aflysning eller afbrudt forbindelse. Ekstraordinære forhold påvirker kun hotel-loft (max 3 nætter).' ?>
</p>

<?php if ($assistHint !== ''): ?>
  <p class="small muted"><?= h($assistHint) ?></p>
<?php endif; ?>

<?php if ($isFerry): ?>
  <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
    <strong>Faerge-kontekst</strong>
    <div class="small muted mt4">Claim-kanal: <strong><?= h((string)($ferryContract['primary_claim_party_name'] ?? 'ukendt')) ?></strong>. Denne side samler ferry Art. 17-assistance.</div>
    <?php if (!empty($ferryScope['scope_exclusion_reason'])): ?>
      <div class="small muted mt4">Scope-note: <?= h((string)$ferryScope['scope_exclusion_reason']) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>



<?php if ($art20Partial): ?>
  <div class="card hl mt8">
    <strong><?= $isFerry ? 'Assistance er delvist aktiveret via saerhensyn.' : 'Art. 20 er delvist aktiveret via PMR.' ?></strong>
    <div class="small muted"><?= $isFerry ? 'Udfyld kun de dele der faktisk blev tilbudt eller maatte betales selv.' : 'Udfyld kun PMR-hensyn nedenfor. Måltider/hotel/transport vurderes først via standard hændelses-gating.' ?></div>
  </div>
<?php elseif (!$art20Active): ?>
  <div class="card hl mt8">
    <?php if ($art20Blocked): ?>
      <strong><?= $isFerry ? 'Faerge-assistance er ikke aktiveret.' : 'Art. 20 er ikke aktiveret.' ?></strong>
      <div class="small muted"><?= $isFerry ? 'Betingelserne for ferry Art. 17 er ikke opfyldt ud fra dine svar i Trin 5.' : 'Betingelserne er ikke opfyldt ud fra dine svar i Trin 4.' ?></div>
    <?php else: ?>
      <strong><?= $isFerry ? 'Faerge-assistance afventer gating.' : 'Art. 20 afventer gating.' ?></strong>
      <div class="small muted"><?= $isFerry ? 'Ga tilbage til Trin 5 og udfyld aflysning/90-minutters afgangsforsinkelse for at aktivere ferry Art. 17.' : 'Ga tilbage til Trin 4 og udfyld haendelsen (inkl. 60-min. varsel), eller til Trin 3 hvis PMR/cykel skal aktivere Art. 20.' ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($assistOff): ?>

  <div class="card hl mt8">

    <?= $isFerry
      ? 'Assistance efter ferry Art. 17 kan vaere undtaget for denne rejse. Udfyld alligevel udgifterne, saa de kan indgaa i claim-assist og manuel vurdering.'
      : 'Assistance efter Art. 20(2) kan være undtaget for denne rejse. Udfyld alligevel udgifterne, så behandler vi dem som refusion efter de gældende regler.' ?>

  </div>

<?php endif; ?>



<div id="art20Core" class="<?= ($art20Active || $isPreview) ? '' : 'hidden' ?>">

<!-- Måltider / drikke -->
<div class="card mt12 <?= ($assistMealsOff && !$isPreview) ? 'hidden' : '' ?>" data-art="20(2a),20(2)">
  <strong>🍽️ <?= $isFerry ? 'Måltider og forfriskninger (Art. 17)' : 'Måltider og drikke (Art.20)' ?></strong>
  <p class="small muted"><?= $isFerry ? 'Faergeoperatoeren skal tilbyde maaltider eller forfriskninger ved aflysning eller afgangsforsinkelse paa mindst 90 minutter, naar det er praktisk muligt.' : 'Jernbanen skal tilbyde forfriskninger ved aflysning eller ≥60 min. forsinkelse.' ?></p>
  <div class="mt8">
    <div>1. Fik du måltider eller forfriskninger?</div>
    <label><input type="radio" name="meal_offered" value="yes" <?= $v('meal_offered')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $v('meal_offered')==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="mt4" data-show-if="meal_offered:no">
    <label>Måltider blev ikke tilbudt – hvorfor?
      <select name="assistance_meals_unavailable_reason">
        <option value="">Vælg</option>
        <?php foreach (['not_available'=>'Ikke til rådighed','unreasonable_terms'=>'Urimelige vilkår','closed'=>'Lukket','other'=>'Andet'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $v('assistance_meals_unavailable_reason')===$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="mt8" data-show-if="meal_offered:no">
    <div class="grid-3">
      <label>Valuta
        <select name="meal_self_paid_currency">
          <option value="">Vælg</option>
          <?php foreach ($currencyOptions as $code => $label): ?>
            <option value="<?= $code ?>" <?= strtoupper($v('meal_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div></div>
      <div></div>
    </div>

    <?php
      $mealAmtItems = $form['meal_self_paid_amount_items'] ?? [];
      $mealReceiptItems = $form['meal_self_paid_receipt_items'] ?? [];
      if (!is_array($mealAmtItems)) { $mealAmtItems = []; }
      if (!is_array($mealReceiptItems)) { $mealReceiptItems = []; }
      if (!$mealAmtItems && $v('meal_self_paid_amount') !== '') { $mealAmtItems = [$v('meal_self_paid_amount')]; }
      if (!$mealReceiptItems && $v('meal_self_paid_receipt') !== '') { $mealReceiptItems = [$v('meal_self_paid_receipt')]; }
      $mealCount = max(count($mealAmtItems), count($mealReceiptItems), 1);
    ?>
    <div id="mealItemsWrap" class="mt8">
      <?php for ($i = 0; $i < $mealCount; $i++): ?>
        <?php $mAmt = (string)($mealAmtItems[$i] ?? ''); $mRc = (string)($mealReceiptItems[$i] ?? ''); ?>
        <div class="grid-3 mt8 meal-item-row">
          <label>Beløb
            <input type="number" step="0.01" name="meal_self_paid_amount_items[]" value="<?= h($mAmt) ?>" />
          </label>
          <label class="small">Kvittering
            <input type="hidden" name="meal_self_paid_receipt_items_existing[]" value="<?= h($mRc) ?>" />
            <input type="file" name="meal_self_paid_receipt_items[]" accept=".pdf,.jpg,.jpeg,.png" />
            <?php if ($mRc !== ''): ?><div class="small muted mt4">Gemmer: <?= h(basename($mRc)) ?></div><?php endif; ?>
          </label>
          <div style="display:flex; align-items:flex-end; gap:8px;">
            <button type="button" class="button button-outline meal-remove-btn" <?= $mealCount <= 1 ? 'disabled' : '' ?>>Fjern</button>
          </div>
        </div>
      <?php endfor; ?>
    </div>
    <div class="mt8">
      <button type="button" class="button button-outline" id="mealAddBtn">+ Tilføj udgift</button>
      <span class="small muted ml8">Tilføj flere beløb/kvitteringer hvis du købte flere gange.</span>
    </div>

    <?php if ($ht = $hintText('meals')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
  </div>
</div>
<!-- Hotel / overnatning -->

<div class="card mt12 <?= ($assistHotelOff && !$isPreview) ? 'hidden' : '' ?>" data-art="20(2b),20(2)">

  <strong>
    <span class="icon-badge hotel" title="Hotel / indkvartering">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M7 10h10a3 3 0 0 1 3 3v6h-2v-2H6v2H4v-8a3 3 0 0 1 3-3zm-1 5h12v-2a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v2zm1-9a2 2 0 1 1 0 4a2 2 0 0 1 0-4z"/>
      </svg>
    </span>
    <?= $isFerry ? 'Hotel og indkvartering (Art. 17)' : 'Hotel og indkvartering (Art.20)' ?>
  </strong>

  <p class="small muted"><?= $isFerry ? 'Hotel og transport hertil skal tilbydes, hvis overnatning bliver noedvendig efter aflysning eller afgangsforsinkelse paa mindst 90 minutter, med forbehold for vejrsikkerhed.' : 'Hotel og transport hertil skal tilbydes ved aflysning eller lang forsinkelse, hvis nødvendigt.' ?></p>

  <div class="mt8">

    <div>2. Fik du hotel/indkvartering plus transport hertil?</div>

    <label><input type="radio" name="hotel_offered" value="yes" <?= $v('hotel_offered')==='yes'?'checked':'' ?> /> Ja</label>

    <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $v('hotel_offered')==='no'?'checked':'' ?> /> Nej</label>

    <label class="ml8"><input type="radio" name="hotel_offered" value="irrelevant" <?= $v('hotel_offered')==='irrelevant'?'checked':'' ?> /> Ikke relevant</label>

  </div>

  <div class="mt4" data-show-if="hotel_offered:yes">

    <span>Indgik transport til hotellet?</span>

    <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="yes" <?= $v('assistance_hotel_transport_included')==='yes'?'checked':'' ?> /> Ja</label>

    <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="no" <?= $v('assistance_hotel_transport_included')==='no'?'checked':'' ?> /> Nej</label>

  </div>

  <div class="mt8" data-show-if="assistance_hotel_transport_included:no">

    <div class="small muted">Angiv evt. egne udgifter til transport mellem station og hotel.</div>

    <div class="grid-3 mt4">

      <label>Transport til/fra hotel - beløb

        <input type="number" step="0.01" name="hotel_transport_self_paid_amount" value="<?= h($v('hotel_transport_self_paid_amount')) ?>" />

      </label>

      <label>Valuta

        <select name="hotel_transport_self_paid_currency">

          <option value="">Vælg</option>

          <?php foreach ($currencyOptions as $code => $label): ?>

            <option value="<?= $code ?>" <?= strtoupper($v('hotel_transport_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label class="small">Kvittering

        <input type="file" name="hotel_transport_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />

      </label>

    </div>

    <?php if ($f = $v('hotel_transport_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>

    <?php if ($ht = $hintText('taxi')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>

  </div>

  <div class="mt4" data-show-if="hotel_offered:no">

    <label>Var overnatning nødvendig selvom hotel ikke blev tilbudt?

      <select name="overnight_needed">

        <option value="">Vælg</option>

        <?php foreach (['yes'=>'Ja','no'=>'Nej'] as $val => $label): ?>

          <option value="<?= $val ?>" <?= $v('overnight_needed')===$val?'selected':'' ?>><?= $label ?></option>

        <?php endforeach; ?>

      </select>

    </label>

  </div>

  <div class="mt8" data-show-if="hotel_offered:no">
    <div class="grid-3">
      <label>Valuta
        <select name="hotel_self_paid_currency">
          <option value="">Vælg</option>
          <?php foreach ($currencyOptions as $code => $label): ?>
            <option value="<?= $code ?>" <?= strtoupper($v('hotel_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div></div>
      <div></div>
    </div>

    <?php
      $hotelAmtItems = $form['hotel_self_paid_amount_items'] ?? [];
      $hotelNightItems = $form['hotel_self_paid_nights_items'] ?? [];
      $hotelReceiptItems = $form['hotel_self_paid_receipt_items'] ?? [];
      if (!is_array($hotelAmtItems)) { $hotelAmtItems = []; }
      if (!is_array($hotelNightItems)) { $hotelNightItems = []; }
      if (!is_array($hotelReceiptItems)) { $hotelReceiptItems = []; }
      if (!$hotelAmtItems && $v('hotel_self_paid_amount') !== '') { $hotelAmtItems = [$v('hotel_self_paid_amount')]; }
      if (!$hotelNightItems && $v('hotel_self_paid_nights') !== '') { $hotelNightItems = [$v('hotel_self_paid_nights')]; }
      if (!$hotelReceiptItems && $v('hotel_self_paid_receipt') !== '') { $hotelReceiptItems = [$v('hotel_self_paid_receipt')]; }
      $hotelCount = max(count($hotelAmtItems), count($hotelNightItems), count($hotelReceiptItems), 1);
    ?>
    <div id="hotelItemsWrap" class="mt8">
      <?php for ($i = 0; $i < $hotelCount; $i++): ?>
        <?php
          $hAmt = (string)($hotelAmtItems[$i] ?? '');
          $hN = (string)($hotelNightItems[$i] ?? '');
          $hRc = (string)($hotelReceiptItems[$i] ?? '');
        ?>
        <div class="grid-3 mt8 hotel-item-row">
          <label>Hotel/overnatning - beløb
            <input type="number" step="0.01" name="hotel_self_paid_amount_items[]" value="<?= h($hAmt) ?>" />
          </label>
          <label>Antal nætter
            <input type="number" step="1" name="hotel_self_paid_nights_items[]" value="<?= h($hN) ?>" />
          </label>
          <label class="small">Kvittering
            <input type="hidden" name="hotel_self_paid_receipt_items_existing[]" value="<?= h($hRc) ?>" />
            <input type="file" name="hotel_self_paid_receipt_items[]" accept=".pdf,.jpg,.jpeg,.png" />
            <?php if ($hRc !== ''): ?><div class="small muted mt4">Gemmer: <?= h(basename($hRc)) ?></div><?php endif; ?>
          </label>
        </div>
        <div class="mt4" style="display:flex; justify-content:flex-end;">
          <button type="button" class="button button-outline hotel-remove-btn" <?= $hotelCount <= 1 ? 'disabled' : '' ?>>Fjern</button>
        </div>
      <?php endfor; ?>
    </div>
    <div class="mt8">
      <button type="button" class="button button-outline" id="hotelAddBtn">+ Tilføj udgift</button>
      <span class="small muted ml8">Tilføj flere overnatninger/kvitteringer hvis relevant.</span>
    </div>

    <?php if ($ht = $hintText('hotelPerNight')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>

  </div>

</div>



<?php if (!$isFerry && $pmrUser && ($art20Active || $art20Partial)): ?>

  <div class="card mt12">

    <strong>PMR-hensyn (Art. 20(5))</strong>

    <div class="mt8">

      <span>Blev PMR-prioritet anvendt?</span>

      <label><input type="radio" name="assistance_pmr_priority_applied" value="yes" <?= $v('assistance_pmr_priority_applied')==='yes'?'checked':'' ?> /> Ja</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_priority_applied" value="no" <?= $v('assistance_pmr_priority_applied')==='no'?'checked':'' ?> /> Nej</label>


    </div>

    <div class="mt8">

      <span>Blev ledsager/servicehund understøttet?</span>

      <label><input type="radio" name="assistance_pmr_companion_supported" value="yes" <?= $v('assistance_pmr_companion_supported')==='yes'?'checked':'' ?> /> Ja</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_companion_supported" value="no" <?= $v('assistance_pmr_companion_supported')==='no'?'checked':'' ?> /> Nej</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_companion_supported" value="not_applicable" <?= $v('assistance_pmr_companion_supported')==='not_applicable'?'checked':'' ?> /> Ikke relevant</label>

    </div>

  </div>

<?php endif; ?>

</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">

  <?= $this->Html->link('Tilbage', ['action' => 'remedies'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>

  <?= $this->Form->button('Næste ?', ['class' => 'button']) ?>

</div>



</fieldset>
<?= $this->Form->end() ?>



<script>

function updateReveal() {

  document.querySelectorAll('[data-show-if]').forEach(function(el) {

    var spec = el.getAttribute('data-show-if'); if (!spec) return;

    var parts = spec.split(':'); if (parts.length !== 2) return;

    var name = parts[0]; var valid = parts[1].split(',');

    var checked = document.querySelector('input[name="' + name + '"]:checked');

    var show = checked && valid.includes(checked.value);

    el.style.display = show ? 'block' : 'none';

    el.hidden = !show;

  });

}

document.addEventListener('change', function(e) {

  if (['meal_offered','hotel_offered','assistance_hotel_transport_included'].includes(e.target.name)) {

    updateReveal();

  }

});

document.addEventListener('DOMContentLoaded', updateReveal);

document.addEventListener('DOMContentLoaded', function() {
  function el(tag, attrs) {
    var e = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function(k) {
      if (k === 'text') { e.textContent = attrs[k]; }
      else if (k === 'html') { e.innerHTML = attrs[k]; }
      else { e.setAttribute(k, attrs[k]); }
    });
    return e;
  }

  function updateRemovers(container, selector) {
    if (!container) return;
    var rows = container.querySelectorAll(selector);
    var canRemove = rows.length > 1;
    container.querySelectorAll(selector + ' .meal-remove-btn,' + selector + ' .hotel-remove-btn,' + '.meal-remove-btn,.hotel-remove-btn').forEach(function(btn) {
      btn.disabled = !canRemove;
    });
  }

  function bindRemoveButtons() {
    document.querySelectorAll('.meal-remove-btn').forEach(function(btn) {
      if (btn.__bound) return;
      btn.__bound = true;
      btn.addEventListener('click', function() {
        var row = btn.closest('.meal-item-row');
        if (row) row.remove();
        var wrap = document.getElementById('mealItemsWrap');
        var remaining = wrap ? wrap.querySelectorAll('.meal-item-row') : [];
        if (wrap && remaining.length === 0) {
          // Always keep at least one row.
          document.getElementById('mealAddBtn') && document.getElementById('mealAddBtn').click();
        }
        // Re-evaluate remove state
        var cnt = wrap ? wrap.querySelectorAll('.meal-item-row').length : 0;
        document.querySelectorAll('.meal-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
      });
    });
    document.querySelectorAll('.hotel-remove-btn').forEach(function(btn) {
      if (btn.__bound) return;
      btn.__bound = true;
      btn.addEventListener('click', function() {
        // Hotel remove button is outside the grid; remove the previous .hotel-item-row block + itself container if any.
        var row = btn.closest('div');
        // Find nearest hotel item row above.
        var wrap = document.getElementById('hotelItemsWrap');
        var itemRow = btn.closest('.hotel-item-row') || (row ? row.previousElementSibling : null);
        if (itemRow && itemRow.classList && itemRow.classList.contains('hotel-item-row')) {
          // Also remove the button wrapper directly after the row if present.
          var next = itemRow.nextElementSibling;
          if (next && next.querySelector && next.querySelector('.hotel-remove-btn')) { next.remove(); }
          itemRow.remove();
        }
        var remaining = wrap ? wrap.querySelectorAll('.hotel-item-row') : [];
        if (wrap && remaining.length === 0) {
          document.getElementById('hotelAddBtn') && document.getElementById('hotelAddBtn').click();
        }
        var cnt = wrap ? wrap.querySelectorAll('.hotel-item-row').length : 0;
        document.querySelectorAll('.hotel-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
      });
    });
  }

  var mealAdd = document.getElementById('mealAddBtn');
  var mealWrap = document.getElementById('mealItemsWrap');
  if (mealAdd && mealWrap) {
    mealAdd.addEventListener('click', function() {
      var row = el('div', { class: 'grid-3 mt8 meal-item-row' });
      var l1 = el('label', { });
      l1.appendChild(document.createTextNode('Beløb'));
      l1.appendChild(el('input', { type: 'number', step: '0.01', name: 'meal_self_paid_amount_items[]' }));
      var l2 = el('label', { class: 'small' });
      l2.appendChild(document.createTextNode('Kvittering'));
      l2.appendChild(el('input', { type: 'hidden', name: 'meal_self_paid_receipt_items_existing[]', value: '' }));
      l2.appendChild(el('input', { type: 'file', name: 'meal_self_paid_receipt_items[]', accept: '.pdf,.jpg,.jpeg,.png' }));
      var l3 = el('div', { style: 'display:flex; align-items:flex-end; gap:8px;' });
      l3.appendChild(el('button', { type: 'button', class: 'button button-outline meal-remove-btn', text: 'Fjern' }));
      row.appendChild(l1);
      row.appendChild(l2);
      row.appendChild(l3);
      mealWrap.appendChild(row);
      bindRemoveButtons();
      var cnt = mealWrap.querySelectorAll('.meal-item-row').length;
      document.querySelectorAll('.meal-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
    });
  }

  var hotelAdd = document.getElementById('hotelAddBtn');
  var hotelWrap = document.getElementById('hotelItemsWrap');
  if (hotelAdd && hotelWrap) {
    hotelAdd.addEventListener('click', function() {
      var row = el('div', { class: 'grid-3 mt8 hotel-item-row' });
      var l1 = el('label', { });
      l1.appendChild(document.createTextNode('Hotel/overnatning - beløb'));
      l1.appendChild(el('input', { type: 'number', step: '0.01', name: 'hotel_self_paid_amount_items[]' }));
      var l2 = el('label', { });
      l2.appendChild(document.createTextNode('Antal nætter'));
      l2.appendChild(el('input', { type: 'number', step: '1', name: 'hotel_self_paid_nights_items[]' }));
      var l3 = el('label', { class: 'small' });
      l3.appendChild(document.createTextNode('Kvittering'));
      l3.appendChild(el('input', { type: 'hidden', name: 'hotel_self_paid_receipt_items_existing[]', value: '' }));
      l3.appendChild(el('input', { type: 'file', name: 'hotel_self_paid_receipt_items[]', accept: '.pdf,.jpg,.jpeg,.png' }));
      row.appendChild(l1);
      row.appendChild(l2);
      row.appendChild(l3);
      hotelWrap.appendChild(row);

      var btnRow = el('div', { class: 'mt4', style: 'display:flex; justify-content:flex-end;' });
      btnRow.appendChild(el('button', { type: 'button', class: 'button button-outline hotel-remove-btn', text: 'Fjern' }));
      hotelWrap.appendChild(btnRow);

      bindRemoveButtons();
      var cnt = hotelWrap.querySelectorAll('.hotel-item-row').length;
      document.querySelectorAll('.hotel-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
    });
  }

  bindRemoveButtons();
  function syncFerryAssistanceAliases() {
    var ferryMeals = document.querySelector('input[name="ferry_refreshments_offered"]');
    if (!ferryMeals) { return; }
    var getRadio = function(name) {
      var checked = document.querySelector('input[name="' + name + '"]:checked');
      return checked ? (checked.value || '') : '';
    };
    var getValue = function(name) {
      var el = document.querySelector('[name="' + name + '"]');
      return el ? (el.value || '') : '';
    };
    ferryMeals.value = getRadio('meal_offered');
    var ferryMealAmt = document.querySelector('input[name="ferry_refreshments_self_paid_amount"]');
    var ferryMealCur = document.querySelector('input[name="ferry_refreshments_self_paid_currency"]');
    var ferryHotel = document.querySelector('input[name="ferry_hotel_offered"]');
    var ferryNight = document.querySelector('input[name="ferry_overnight_required"]');
    var ferryHotelTransport = document.querySelector('input[name="ferry_hotel_transport_included"]');
    var ferryHotelAmt = document.querySelector('input[name="ferry_hotel_self_paid_amount"]');
    var ferryHotelCur = document.querySelector('input[name="ferry_hotel_self_paid_currency"]');
    var ferryHotelNights = document.querySelector('input[name="ferry_hotel_self_paid_nights"]');
    if (ferryMealAmt) { ferryMealAmt.value = getValue('meal_self_paid_amount'); }
    if (ferryMealCur) { ferryMealCur.value = getValue('meal_self_paid_currency'); }
    if (ferryHotel) { ferryHotel.value = getRadio('hotel_offered'); }
    if (ferryNight) { ferryNight.value = getRadio('overnight_needed'); }
    if (ferryHotelTransport) { ferryHotelTransport.value = getRadio('assistance_hotel_transport_included'); }
    if (ferryHotelAmt) { ferryHotelAmt.value = getValue('hotel_self_paid_amount'); }
    if (ferryHotelCur) { ferryHotelCur.value = getValue('hotel_self_paid_currency'); }
    if (ferryHotelNights) { ferryHotelNights.value = getValue('hotel_self_paid_nights'); }
  }
  document.querySelectorAll('input[name="meal_offered"], input[name="hotel_offered"], input[name="overnight_needed"], input[name="assistance_hotel_transport_included"], input[name="meal_self_paid_amount"], input[name="meal_self_paid_currency"], input[name="hotel_self_paid_amount"], input[name="hotel_self_paid_currency"], input[name="hotel_self_paid_nights"]').forEach(function(el) {
    ['change','input','click'].forEach(function(ev){ el.addEventListener(ev, syncFerryAssistanceAliases); });
  });
  syncFerryAssistanceAliases();
});

</script>
