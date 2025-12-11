<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$pmrUser = strtolower((string)($form['pmr_user'] ?? $flags['pmr_user'] ?? '')) === 'yes';
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$art20Active = $art20Active ?? false;
$art20FallbackValue = (string)($form['art20_expected_delay_60'] ?? '');
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
$hintText = function(string $key) use ($priceHints): string {
    if (!is_array($priceHints)) { return ''; }
    $h = $priceHints[$key] ?? null;
    if (!is_array($h) || !isset($h['min'],$h['max'],$h['currency'])) { return ''; }
    $min = number_format((float)$h['min'], 0, ',', '.');
    $max = number_format((float)$h['max'], 0, ',', '.');
    return "Typisk interval: {$min}–{$max} {$h['currency']}";
};
?>

<style>
  .hidden { display:none; }
  .card { padding: 16px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
  .hl { background:#fff4e5; border-color:#f4c17a; }
  [data-show-if] { display:none; }
</style>

<h1>TRIN 5 - Assistance og udgifter (Art. 20)</h1>
<?php
    if ($travelState === 'completed') {
        echo '<p class="small muted">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>';
    } elseif ($travelState === 'ongoing') {
        echo '<p class="small muted">Status: Rejsen er i gang. Vi samler dine oplevelser og udgifter under forstyrrelsen.</p>';
    } elseif ($travelState === 'not_started') {
        echo '<p class="small muted">Status: Rejsen er endnu ikke påbegyndt. Besvar ud fra, hvad du forventer at få brug for ved en forstyrrelse.</p>';
    }
?>
<?= $this->Form->create(null, ['type' => 'file', 'novalidate' => true]) ?>

  <div class="card <?= $art20Active ? 'hidden' : 'hl' ?>" id="art20Fallback">
    <strong>Aktivér Art. 20 Assistance</strong>
    <p class="small muted mt4">Assistance (måltider, hotel, alternativ transport og PMR-hensyn) gælder ved forsinkelse ≥60 min, aflysning eller afbrudt forbindelse.</p>
    <div class="mt8">
      <p>Da vi mangler oplysninger, forventede du selv, at forsinkelsen ville blive mindst 60 minutter?</p>
      <label><input type="radio" name="art20_expected_delay_60" value="yes" <?= $art20FallbackValue === 'yes' ? 'checked' : '' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="art20_expected_delay_60" value="no" <?= $art20FallbackValue === 'no' ? 'checked' : '' ?> /> Nej</label>
      <label class="ml8"><input type="radio" name="art20_expected_delay_60" value="unknown" <?= $art20FallbackValue === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
    </div>
    <p class="small muted mt8">Svar “Ja” for at aktivere assistance-spørgsmålene. Ved “Nej/Ved ikke” skjules resten.</p>
  </div>

  <div id="art20Main" style="<?= $art20Active ? '' : 'display:none;' ?>">

    <!-- A) Tilbudt assistance -->
    <div class="card mt12">
      <strong>A) Hvad selskabet tilbød (Art. 20(2))</strong>

      <!-- Måltider -->
      <div class="mt8">
        <p>1. Fik du måltider eller forfriskninger under ventetiden?</p>
        <label><input type="radio" name="meal_offered" value="yes" <?= $v('meal_offered')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $v('meal_offered')==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div class="mt4" data-show-if="meal_offered:no">
        <label>Måltider blev ikke tilbudt – hvorfor?
          <select name="assistance_meals_unavailable_reason">
            <option value="">Vælg</option>
            <?php foreach (['not_available'=>'Ikke til rådighed','unreasonable_terms'=>'Urimelige vilkår','closed'=>'Lukket','other'=>'Andet','unknown'=>'Ved ikke'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $v('assistance_meals_unavailable_reason')===$val?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="small muted mt4">Evt. udgifter/kvittering for mad angives i sektion B.</div>
      </div>

      <!-- Hotel -->
      <div class="mt12">
        <p>2. Fik du hotel/indkvartering plus transport hertil?</p>
        <label><input type="radio" name="hotel_offered" value="yes" <?= $v('hotel_offered')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $v('hotel_offered')==='no'?'checked':'' ?> /> Nej</label>
        <label class="ml8"><input type="radio" name="hotel_offered" value="irrelevant" <?= $v('hotel_offered')==='irrelevant'?'checked':'' ?> /> Ikke relevant</label>
      </div>
      <div class="mt4" data-show-if="hotel_offered:yes">
        <div class="small muted">Detaljer om nætter/beløb/kvittering angives i sektion B.</div>
        <div class="mt4">
          <span>Indgik transport til hotellet?</span>
          <label><input type="radio" name="assistance_hotel_transport_included" value="yes" <?= $v('assistance_hotel_transport_included')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="no" <?= $v('assistance_hotel_transport_included')==='no'?'checked':'' ?> /> Nej</label>
        </div>
      </div>
      <div class="mt4" data-show-if="hotel_offered:no">
        <label>Var overnatning nødvendig selvom hotel ikke blev tilbudt?
          <select name="overnight_needed">
            <option value="">Vælg</option>
            <?php foreach (['yes'=>'Ja','no'=>'Nej','unknown'=>'Ved ikke'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $v('overnight_needed')===$val?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <!-- Strandet/alternativ transport fusion 20(2)(c)+20(3) -->
      <div class="mt12">
        <p>3. Hvis din forbindelse ikke kunne fortsætte:</p>
        <div class="mt4">
          <div class="small muted">Hvor var du, da det skete? (flere kan markeres)</div>
          <label><input type="checkbox" name="blocked_on_track" value="yes" <?= $v('blocked_on_track')==='yes'?'checked':'' ?> /> Jeg sad fast i toget på sporet</label>
          <label class="ml8"><input type="checkbox" name="stranded_at_station" value="yes" <?= $v('stranded_at_station')==='yes'?'checked':'' ?> /> Jeg var på en station uden videre tog</label>
          <label class="ml8"><input type="checkbox" name="stranded_unknown" value="yes" <?= $v('stranded_unknown')==='yes'?'checked':'' ?> /> Ved ikke / andet</label>
        </div>
        <div class="mt4">
          <span>Blev der stillet transport til rådighed for at komme væk/videre?</span>
          <?php $bt = $v('blocked_train_alt_transport'); ?>
          <label><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja, af operatør/station</label>
          <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej, jeg måtte selv arrangere</label>
          <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt==='irrelevant'?'checked':'' ?> /> Ved ikke</label>
        </div>
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
        <div class="small muted">Egne udgifter/kvittering for selv arrangeret transport angives i sektion B.</div>
      </div>
    </div>

    <!-- B) Dine udgifter -->
    <div class="card mt12">
      <strong>B) Dine udgifter (selv betalt)</strong>
      <p class="small muted mt4">Angiv beløb/valuta og upload kvittering per kategori.</p>

      <div class="mt8">
        <div class="grid-3">
          <label>Mad/forfriskninger - beløb
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

      <div class="mt8">
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
        <div class="mt4">
          <span>Indgik transport til/fra hotellet i udgiften?</span>
          <label><input type="radio" name="assistance_hotel_transport_included" value="yes" <?= $v('assistance_hotel_transport_included')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="no" <?= $v('assistance_hotel_transport_included')==='no'?'checked':'' ?> /> Nej</label>
        </div>
        <?php if ($f = $v('hotel_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        <?php if ($ht = $hintText('hotelPerNight')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
      </div>

      <div class="mt8">
        <div class="grid-3">
          <label>Transport/bus/taxi/strandings-transport - beløb
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

      <div class="mt8">
        <div class="grid-3">
          <label>Alternativ billet/transport (selv arrangeret) - beløb
            <input type="number" step="0.01" name="alt_self_paid_amount" value="<?= h($v('alt_self_paid_amount')) ?>" />
          </label>
          <label>Valuta
            <select name="alt_self_paid_currency">
              <option value="">Vælg</option>
              <?php foreach ($currencyOptions as $code => $label): ?>
                <option value="<?= $code ?>" <?= strtoupper($v('alt_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="small">Kvittering
            <input type="file" name="alt_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
          </label>
        </div>
        <?php if ($f = $v('alt_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
        <?php if ($ht = $hintText('altTransport')): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
      </div>

      <div class="mt8">
        <label>Samlet udgift-upload (valgfrit)
          <input type="file" name="extra_expense_upload" accept=".pdf,.jpg,.jpeg,.png" />
        </label>
        <?php if ($f = $v('extra_expense_upload')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
      </div>
    </div>

    <!-- C) Dokumentation -->
    <div class="card mt12">
      <strong>C) Dokumentation</strong>
      <div class="mt8"><strong>Fik du besked om, hvordan du kunne bede om en skriftlig bekræftelse? (Art. 20 stk. 4)</strong></div>
      <?php $dInfo = $v('delay_confirmation_info'); ?>
      <label><input type="radio" name="delay_confirmation_info" value="yes" <?= $dInfo==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_info" value="no" <?= $dInfo==='no'?'checked':'' ?> /> Nej</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_info" value="unknown" <?= ($dInfo===''||$dInfo==='unknown')?'checked':'' ?> /> Ved ikke</label>

      <div class="mt8"><strong>Modtog du en skriftlig bekræftelse?</strong></div>
      <?php $dRecv = $v('delay_confirmation_received'); ?>
      <label><input type="radio" name="delay_confirmation_received" value="yes" <?= $dRecv==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_received" value="no" <?= $dRecv==='no'?'checked':'' ?> /> Nej</label>
      <label class="ml8"><input type="radio" name="delay_confirmation_received" value="unknown" <?= ($dRecv===''||$dRecv==='unknown')?'checked':'' ?> /> Ved ikke</label>

      <div id="delayConfUpload" class="mt8" style="display:none;">
        <div>Upload bekræftelsen (PDF/JPG/PNG)</div>
        <input type="file" name="delay_confirmation_upload" accept="application/pdf,image/jpeg,image/png" />
        <?php if ($f = $v('delay_confirmation_upload')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
      </div>
    </div>

    <?php if ($pmrUser): ?>
      <div class="card mt12">
        <strong>PMR-hensyn (Art. 20(5))</strong>
        <div class="mt8">
          <span>Blev PMR-prioritet anvendt?</span>
          <label><input type="radio" name="assistance_pmr_priority_applied" value="yes" <?= $v('assistance_pmr_priority_applied')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="assistance_pmr_priority_applied" value="no" <?= $v('assistance_pmr_priority_applied')==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="assistance_pmr_priority_applied" value="unknown" <?= $v('assistance_pmr_priority_applied')==='unknown'?'checked':'' ?> /> Ved ikke</label>
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

<?php if (!$art20Active && empty($art20Blocked)): ?>
  <div class="card mt12 hl" id="art20Pending">
    <strong>Assistance kræver vurdering</strong>
    <p class="small muted mt4">Vi mangler nødvendigt input (forsinkelse ≥60 min, aflysning eller mistet forbindelse), indtil du svarer “Ja” på spørgsmålet ovenfor.</p>
  </div>
<?php endif; ?>
<?php if (!empty($art20Blocked)): ?>
  <div class="card mt12" id="art20BlockedCard" style="border:1px solid #f5c6cb; background:#fff5f5;">
    <strong>Art. 20 springes over</strong>
    <p class="small muted mt4">Du har svaret “Nej” til ≥60 minutter, og der er ikke registreret aflysning. Derfor indsamler vi ikke assistance-oplysninger.</p>
  </div>
<?php endif; ?>

<div class="flex mt12">
  <?= $this->Html->link('← Tilbage', ['action' => 'choices'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Form->button('Fortsæt →', ['id' => 'assistanceSubmitBtn', 'class' => 'button']) ?>
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
  var dc = document.querySelector('input[name="delay_confirmation_received"]:checked');
  var up = document.getElementById('delayConfUpload');
  if (up) { up.style.display = (dc && dc.value === 'yes') ? '' : 'none'; }
}
function toggleArt20Activation(value) {
  var main = document.getElementById('art20Main');
  var fallback = document.getElementById('art20Fallback');
  var pending = document.getElementById('art20Pending');
  var blocked = document.getElementById('art20BlockedCard');
  var active = (value === 'yes') || <?= $art20Active ? 'true' : 'false'; ?>;
  var submit = document.getElementById('assistanceSubmitBtn');
  if (main) main.style.display = active ? '' : 'none';
  if (fallback) fallback.classList.toggle('hidden', active);
  if (pending) pending.classList.toggle('hidden', active);
  if (blocked) blocked.style.display = (!active && value === 'no') ? '' : 'none';
  if (submit) {
    var disable = (!active && value === 'no');
    submit.disabled = disable;
    if (disable) submit.setAttribute('aria-disabled','true'); else submit.removeAttribute('aria-disabled');
  }
  updateReveal();
}
function onArt20Change() {
  var v = (document.querySelector('input[name="art20_expected_delay_60"]:checked')||{}).value || '';
  toggleArt20Activation(v);
}
document.addEventListener('change', function(e) {
  if (e.target && e.target.name === 'art20_expected_delay_60') { onArt20Change(); }
  updateReveal();
});
document.addEventListener('input', updateReveal);
document.addEventListener('DOMContentLoaded', function() {
  updateReveal();
  var sel = document.querySelector('input[name="art20_expected_delay_60"]:checked');
  if (sel) toggleArt20Activation(sel.value); else toggleArt20Activation(<?= $art20Active ? "'yes'" : "''"; ?>);
});
</script>
