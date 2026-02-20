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
$assistTitle = $isOngoing
    ? 'TRIN 7 - Mad og drikke, hotel (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 7 - Mad og drikke, hotel (afsluttet rejse)' : 'TRIN 7 - Mad og drikke, hotel (Art. 20)');
$assistHint = $isOngoing
    ? 'Udgifter indtil nu (du kan tilfoeje flere senere).'
    : ($isCompleted ? 'Udgifter under hele rejsen.' : '');
$assistMealsOff = ($articles['art20_2a'] ?? ($articles['art20_2'] ?? true)) === false;
$assistHotelOff = ($articles['art20_2b'] ?? ($articles['art20_2'] ?? true)) === false;
$assistTrackOff = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
$assistStationOff = ($articles['art20_3'] ?? true) === false;
$assistOff    = ($articles['art20_2'] ?? true) === false || ($assistMealsOff && $assistHotelOff && $assistTrackOff && $assistStationOff);
$art20Active = $art20Active ?? true;
$art20Partial = $art20Partial ?? false;
$art20Blocked = $art20Blocked ?? false;



$currencyOptions = [

    'EUR' => 'EUR - Euro',

    'DKK' => 'DKK - Dansk krone',

    'SEK' => 'SEK - Svensk krona',

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

  /* Inline icon badges to avoid emoji encoding issues in headings */
  .icon-badge { width:26px; height:26px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; border:1px solid #d0d7de; background:#f8f9fb; }
  .icon-badge svg { width:16px; height:16px; display:block; }
  .icon-badge.hotel { background:#eef7ff; border-color:#cfe0ff; }
  .icon-badge.hotel svg path { fill:#1e3a8a; }

</style>



<h1><?= h($assistTitle) ?></h1>

<?= $this->Form->create(null, ['type' => 'file', 'novalidate' => true]) ?>



<p class="small muted">

  Aktiveres ved forsinkelse =60 min, aflysning eller afbrudt forbindelse. Ekstraordinære forhold påvirker kun hotel-loft (max 3 nætter).

</p>

<?php if ($assistHint !== ''): ?>
  <p class="small muted"><?= h($assistHint) ?></p>
<?php endif; ?>



<?php if ($art20Partial): ?>
  <div class="card hl mt8">
    <strong>Art. 20 er delvist aktiveret via PMR.</strong>
    <div class="small muted">Udfyld kun PMR-hensyn nedenfor. Måltider/hotel/transport vurderes først via standard hændelses-gating.</div>
  </div>
<?php elseif (!$art20Active): ?>
  <div class="card hl mt8">
    <?php if ($art20Blocked): ?>
      <strong>Art. 20 er ikke aktiveret.</strong>
      <div class="small muted">Betingelserne er ikke opfyldt ud fra dine svar i Trin 4.</div>
    <?php else: ?>
      <strong>Art. 20 afventer gating.</strong>
      <div class="small muted">Ga tilbage til Trin 4 og udfyld haendelsen (inkl. 60-min. varsel), eller til Trin 3 hvis PMR/cykel skal aktivere Art. 20.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($assistOff): ?>

  <div class="card hl mt8">

    ?? Assistance efter Art. 20(2) kan være undtaget for denne rejse. Udfyld alligevel udgifterne, så behandler vi dem som refusion efter de gældende regler.

  </div>

<?php endif; ?>



<div id="art20Core" class="<?= $art20Active ? '' : 'hidden' ?>">

<!-- Måltider / drikke -->
<div class="card mt12 <?= $assistMealsOff ? 'hidden' : '' ?>" data-art="20(2a),20(2)">
  <strong>🍽️ Måltider og drikke (Art.20)</strong>
  <p class="small muted">Jernbanen skal tilbyde forfriskninger ved aflysning eller ≥60 min. forsinkelse.</p>
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
      <label>Beløb
        <input type="number" step="0.01" name="meal_self_paid_amount" value="<?= h($v('meal_self_paid_amount')) ?>" />
      </label>
      <label>Valuta
        <select name="meal_self_paid_currency">
          <option value="">Vælg</option>
          <?php foreach ($currencyOptions as $code => $label): ?>
            <option value="<?= $code ?>" <?= strtoupper($v('meal_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="small">Kvittering
        <input type="file" name="meal_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
      </label>
    </div>
    <?php if ($f = $v('meal_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
    <?php if ($ht = $hintText('meals')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
  </div>
</div>
<!-- Hotel / overnatning -->

<div class="card mt12 <?= $assistHotelOff ? 'hidden' : '' ?>" data-art="20(2b),20(2)">

  <strong>
    <span class="icon-badge hotel" title="Hotel / indkvartering">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M7 10h10a3 3 0 0 1 3 3v6h-2v-2H6v2H4v-8a3 3 0 0 1 3-3zm-1 5h12v-2a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v2zm1-9a2 2 0 1 1 0 4a2 2 0 0 1 0-4z"/>
      </svg>
    </span>
    Hotel og indkvartering (Art.20)
  </strong>

  <p class="small muted">Hotel og transport hertil skal tilbydes ved aflysning eller lang forsinkelse, hvis nødvendigt.</p>

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

      <label>Hotel/overnatning - beløb

        <input type="number" step="0.01" name="hotel_self_paid_amount" value="<?= h($v('hotel_self_paid_amount')) ?>" />

      </label>

      <label>Valuta

        <select name="hotel_self_paid_currency">

          <option value="">Vælg</option>

          <?php foreach ($currencyOptions as $code => $label): ?>

            <option value="<?= $code ?>" <?= strtoupper($v('hotel_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label>Antal nætter

        <input type="number" step="1" name="hotel_self_paid_nights" value="<?= h($v('hotel_self_paid_nights')) ?>" />

      </label>

    </div>

    <?php if ($f = $v('hotel_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>

    <?php if ($ht = $hintText('hotelPerNight')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>

  </div>

</div>



<!-- Transport / taxa / bus (flyttet til trin 4 / incident) -->
<div class="card mt12" style="display:none;">

  <strong>?? Transport til/fra (Art.20)</strong>

  <p class="small muted">Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.</p>

  <?php

    $strandedLocation = $v('stranded_location');

    if (!$strandedLocation) {

        if ($v('blocked_on_track') === 'yes') { $strandedLocation = 'track'; }

        elseif ($v('stranded_at_station') === 'yes') { $strandedLocation = 'station'; }

    }

  ?>

  <div class="mt4">

    <div class="small muted">Hvor var du, da det skete? (vælg én)</div>

    <label><input type="radio" name="stranded_location" value="track" <?= $strandedLocation==='track'?'checked':'' ?> /> Jeg sad fast i toget på sporet</label>

    <label class="ml8"><input type="radio" name="stranded_location" value="station" <?= $strandedLocation==='station'?'checked':'' ?> /> Jeg var på en station uden videre tog</label>

    <label class="ml8"><input type="radio" name="stranded_location" value="irrelevant" <?= $strandedLocation==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>

  </div>



  <div class="mt4" data-show-if="stranded_location:track,station">

    <span>Blev der stillet transport til rådighed for at komme væk/videre?</span>

    <?php $bt = $v('blocked_train_alt_transport'); ?>

    <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja, af operatør/station</label>

    <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej, jeg måtte selv arrangere</label>

    <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>

  </div>



  <div class="mt4" data-show-if="blocked_train_alt_transport:yes">

    <div class="grid-3">

      <label>Tilbudt af

        <select name="assistance_alt_transport_offered_by">

          <?php foreach (['operator'=>'Operatør','station'=>'Station','retailer'=>'Retailer','other'=>'Andet'] as $val => $label): ?>

            <option value="<?= $val ?>" <?= $v('assistance_alt_transport_offered_by')===$val?'selected':'' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label>Transporttype

        <select name="assistance_alt_transport_type">

          <?php foreach (['rail'=>'Tog','bus'=>'Bus','taxi'=>'Taxi','other'=>'Andet'] as $val => $label): ?>

            <option value="<?= $val ?>" <?= $v('assistance_alt_transport_type')===$val?'selected':'' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label>Destination

        <?php $to = $v('assistance_alt_to_destination'); ?>

        <select name="assistance_alt_to_destination">

          <option value="">Vælg</option>

          <option value="station" <?= $to==='station'?'selected':'' ?>>Station</option>

          <option value="other_departure" <?= $to==='other_departure'?'selected':'' ?>>Andet afgangssted</option>

          <option value="final_destination" <?= $to==='final_destination'?'selected':'' ?>>Endelige bestemmelsessted</option>

        </select>

      </label>

    </div>

    <div class="grid-3">

      <label>Afgangstid

        <input type="time" name="assistance_alt_departure_time" value="<?= h($v('assistance_alt_departure_time')) ?>" />

      </label>

      <label>Ankomsttid

        <input type="time" name="assistance_alt_arrival_time" value="<?= h($v('assistance_alt_arrival_time')) ?>" />

      </label>

    </div>

  </div>



  <div class="mt4" data-show-if="blocked_train_alt_transport:no">

    <div class="small muted">Egne udgifter/kvittering for selv arrangeret transport kan angives herunder.</div>

  </div>



  <div class="mt8" data-show-if="blocked_train_alt_transport:no">

    <div class="grid-3">

      <label>Transport/bus/taxi eller alternativ billet - beløb

        <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($v('blocked_self_paid_amount')) ?>" />

      </label>

      <label>Valuta

        <select name="blocked_self_paid_currency">

          <option value="">Vælg</option>

          <?php foreach ($currencyOptions as $code => $label): ?>

            <option value="<?= $code ?>" <?= strtoupper($v('blocked_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label class="small">Kvittering

        <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />

      </label>

    </div>

    <?php if ($f = $v('blocked_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>

    <?php if ($ht = $hintText('taxi')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>

  </div>

</div>



<?php if ($pmrUser && ($art20Active || $art20Partial)): ?>

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

  if (['meal_offered','hotel_offered','assistance_hotel_transport_included','stranded_location','blocked_train_alt_transport'].includes(e.target.name)) {

    updateReveal();

  }

});

document.addEventListener('DOMContentLoaded', updateReveal);

</script>
