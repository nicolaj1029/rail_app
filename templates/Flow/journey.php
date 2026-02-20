<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$bikeHint = $isOngoing ? 'Svar ud fra det, der er sket indtil nu.' : ($isCompleted ? 'Svar ud fra hvad der faktisk skete.' : '');
$pmrHint = $isOngoing ? 'Har du faaet den assistance, du har brug for indtil nu?' : ($isCompleted ? 'Fik du den assistance, du havde ret til?' : '');
$disruptionHint = $isOngoing ? 'Hvad er status lige nu?' : ($isCompleted ? 'Hvad skete der under hele rejsen?' : '');

$bikeAutoDetected = !empty($meta['_auto']['bike_booked']) || !empty($meta['_bike_detection']);
$pmrAutoDetected  = !empty($meta['_auto']['pmr_user']) || !empty($meta['_pmr_detection']) || !empty($meta['_pmr_detected']);
$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);
$art9On  = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;
$art93On = ($articles['art9_3'] ?? ($articles['art9'] ?? true)) !== false;

$v = fn(string $k): string => (string)($form[$k] ?? '');
?>

<style>
  .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
  .hidden { display:none; }
  .hide-bike-delay { display:none !important; }
  [data-show-if] { display:none; }
</style>

<?php if ($isOngoing): ?>
  <h1>TRIN 3 - Bekraeft rejse og forsinkelse (igangvaerende)</h1>
<?php elseif ($isCompleted): ?>
  <h1>TRIN 3 - Bekraeft hvad der skete paa rejsen</h1>
<?php else: ?>
  <h1>TRIN 3 - Bekraeft rejse og forsinkelse</h1>
<?php endif; ?>
<?php if (!empty($contractWarning ?? '')): ?>
  <div class="card hl" style="border:1px solid #f5c2c7; background:#fff5f5; margin-bottom:8px;">
    <div class="small" style="color:#a71d2a;"><?= h($contractWarning) ?></div>
  </div>
<?php endif; ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>

<!-- TRIN 3a – Cykel -->
<div class="card mt12">
  <strong>🚲 TRIN 3a – Cykel og bagage (Art.6)</strong>
  <p class="small muted">Svarene her aktiverer Art.18/20 ved cykel-problemer.<?= ($bikeHint !== '') ? (' ' . h($bikeHint)) : '' ?></p>
  <?php if ($bikeAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at nævne cykel. Valget er stadig sat til “Nej” som udgangspunkt – ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Havde du en cykel med på rejsen?</div>
    <label><input type="radio" name="bike_was_present" value="yes" <?= $v('bike_was_present')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_was_present" value="no" <?= $v('bike_was_present')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4 hide-bike-delay <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_was_present:yes" data-art="9(2)">
    <div>2. Forsinkede cyklen eller dens håndtering dig?</div>
    <label><input type="radio" name="bike_delay" value="yes" <?= $v('bike_delay')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_delay" value="no" <?= $v('bike_delay')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="bike_was_present:yes" data-art="9(1)">
    <div>2. Havde du reserveret plads til cyklen?</div>
    <label><input type="radio" name="bike_reservation_made" value="yes" <?= $v('bike_reservation_made')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_made" value="no" <?= $v('bike_reservation_made')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="bike_reservation_made:no" data-art="9(1)">
    <div>2B. Var det et tog, hvor der krævedes cykelreservation?</div>
    <label><input type="radio" name="bike_reservation_required" value="yes" <?= $v('bike_reservation_required')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_required" value="no" <?= $v('bike_reservation_required')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div id="bikeAfter2B" class="mt4" data-show-if="bike_was_present:yes">
    <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-art="9(2)">
      <div>3. Blev du nægtet at tage cyklen med?</div>
      <label><input type="radio" name="bike_denied_boarding" value="yes" <?= $v('bike_denied_boarding')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="bike_denied_boarding" value="no" <?= $v('bike_denied_boarding')==='no'?'checked':'' ?> /> Nej</label>
    </div>

    <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_denied_boarding:yes" data-art="9(2)">
      <div>4. Informerede operatøren dig om årsagen?</div>
      <label><input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $v('bike_refusal_reason_provided')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="bike_refusal_reason_provided" value="no" <?= $v('bike_refusal_reason_provided')==='no'?'checked':'' ?> /> Nej</label>
    </div>

  <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_refusal_reason_provided:yes" data-art="9(2)">
    <div>5. Hvad var begrundelsen for afvisningen?</div>
    <select name="bike_refusal_reason_type">
      <option value="">- vælg -</option>
      <option value="capacity" <?= $v('bike_refusal_reason_type')==='capacity'?'selected':'' ?>>Kapacitet</option>
      <option value="equipment" <?= $v('bike_refusal_reason_type')==='equipment'?'selected':'' ?>>Materiel tillader det ikke</option>
      <option value="weight_dim" <?= $v('bike_refusal_reason_type')==='weight_dim'?'selected':'' ?>>Vægt/dimensioner</option>
      <option value="other" <?= $v('bike_refusal_reason_type')==='other'?'selected':'' ?>>Andet</option>
    </select>
  </div>
  <div class="mt4" data-show-if="bike_refusal_reason_type:other">
    <label class="small">Beskriv kort</label>
    <textarea name="bike_refusal_reason_other_text" rows="2"><?= h($v('bike_refusal_reason_other_text')) ?></textarea>
  </div>
  </div>
</div>

<!-- TRIN 3b – PMR -->
<div class="card mt12">
  <strong>♿ TRIN 3b – PMR / handicap</strong>
  <p class="small muted">Hvis bestilt hjælp ikke blev leveret, kan Art.18/20 aktiveres automatisk.</p>
  <?php if ($pmrAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at nævne handicap/PMR. Valget er stadig sat til “Nej” som udgangspunkt – ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Har du et handicap eller nedsat mobilitet, som krævede assistance?</div>
    <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="pmr_user:yes" data-art="9(1)">
    <div>2. Bestilte du assistance før rejsen?</div>
    <label><input type="radio" name="pmr_booked" value="yes" <?= $v('pmr_booked')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_booked" value="no" <?= $v('pmr_booked')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="pmr_booked:yes" data-art="9(2)">
    <div>3. Blev assistancen leveret?</div>
    <label><input type="radio" name="pmr_delivered_status" value="yes" <?= $v('pmr_delivered_status')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_delivered_status" value="no" <?= $v('pmr_delivered_status')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div id="pmrQ4Wrap" class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-art="9(2)">
    <div>4. Manglede der PMR-faciliteter, som var lovet før købet?</div>
    <label><input type="radio" name="pmr_promised_missing" value="yes" <?= $v('pmr_promised_missing')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_promised_missing" value="no" <?= $v('pmr_promised_missing')==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="mt4" data-show-if="pmr_promised_missing:yes">
    <label class="small">Beskriv kort</label>
    <textarea name="pmr_facility_details" rows="2"><?= h($v('pmr_facility_details')) ?></textarea>
  </div>
</div>


<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('← Tilbage', ['action' => 'entitlements'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Form->button('Næste trin →', ['class' => 'button']) ?>
</div>

<?= $this->Form->end() ?>

<?= $this->element('hooks_panel') ?>

<script>
function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if'); if (!spec) return;
    var parts = spec.split(':'); if (parts.length !== 2) return;
    var name = parts[0]; var valid = parts[1].split(',');
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    var value = checked ? checked.value : '';
    if (!value) {
      var select = document.querySelector('select[name="' + name + '"]');
      if (select) { value = select.value; }
    }
    var show = value && valid.includes(value);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
  var after2b = document.getElementById('bikeAfter2B');
  if (after2b) {
    var present = document.querySelector('input[name="bike_was_present"]:checked');
    var presVal = present ? present.value : '';
    var resMade = document.querySelector('input[name="bike_reservation_made"]:checked');
    var resVal = resMade ? resMade.value : '';
    var req = document.querySelector('input[name="bike_reservation_required"]:checked');
    var reqVal = req ? req.value : '';
    var show = (presVal === 'yes') && (resVal === 'yes' || (resVal === 'no' && reqVal === 'no'));
    after2b.style.display = show ? 'block' : 'none';
    after2b.hidden = !show;
  }
  var pmrQ4 = document.getElementById('pmrQ4Wrap');
  if (pmrQ4) {
    var booked = document.querySelector('input[name="pmr_booked"]:checked');
    var bookedVal = booked ? booked.value : '';
    var delivered = document.querySelector('input[name="pmr_delivered_status"]:checked');
    var delVal = delivered ? delivered.value : '';
    var showQ4 = (bookedVal === 'no') || (bookedVal === 'yes' && (delVal === 'yes' || delVal === 'no'));
    if (pmrQ4.classList.contains('hidden')) { showQ4 = false; }
    pmrQ4.style.display = showQ4 ? 'block' : 'none';
    pmrQ4.hidden = !showQ4;
  }
}
document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  updateReveal();
});
document.addEventListener('DOMContentLoaded', updateReveal);
</script>
