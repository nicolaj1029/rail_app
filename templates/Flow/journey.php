<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];

$bikeAutoDetected = !empty($meta['_auto']['bike_booked']) || !empty($meta['_bike_detection']);
$pmrAutoDetected  = !empty($meta['_auto']['pmr_user']) || !empty($meta['_pmr_detection']) || !empty($meta['_pmr_detected']);

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
  [data-show-if] { display:none; }
</style>

<h1>TRIN 3 – Bekræft rejse og forsinkelse</h1>
<?php if (!empty($contractWarning ?? '')): ?>
  <div class="card hl" style="border:1px solid #f5c2c7; background:#fff5f5; margin-bottom:8px;">
    <div class="small" style="color:#a71d2a;"><?= h($contractWarning) ?></div>
  </div>
<?php endif; ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>

<!-- TRIN 3a – Cykel -->
<div class="card mt12">
  <strong>🚲 TRIN 3a – Cykel og bagage (Art.6)</strong>
  <p class="small muted">Svarene her aktiverer Art.18/20 ved cykel-problemer.</p>
  <?php if ($bikeAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at nævne cykel. Valget er stadig sat til “Nej” som udgangspunkt – ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Havde du en cykel med på rejsen?</div>
    <label><input type="radio" name="bike_was_present" value="yes" <?= $v('bike_was_present')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_was_present" value="no" <?= $v('bike_was_present')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bike_was_present:yes">
    <div>2. Forsinkede cyklen eller dens håndtering dig?</div>
    <label><input type="radio" name="bike_delay" value="yes" <?= $v('bike_delay')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_delay" value="no" <?= $v('bike_delay')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bike_was_present:yes">
    <div>3. Havde du reserveret plads til cyklen?</div>
    <label><input type="radio" name="bike_reservation_made" value="yes" <?= $v('bike_reservation_made')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_made" value="no" <?= $v('bike_reservation_made')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bike_reservation_made:no">
    <div>3B. Var toget cykelvenligt uden reservation?</div>
    <label><input type="radio" name="bike_reservation_required" value="yes" <?= $v('bike_reservation_required')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_required" value="no" <?= $v('bike_reservation_required')==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="bike_reservation_required" value="unknown" <?= $v('bike_reservation_required')==='unknown'?'checked':'' ?> /> Ved ikke</label>
  </div>

  <div class="mt4" data-show-if="bike_was_present:yes">
    <div>4. Blev du nægtet at tage cyklen med?</div>
    <label><input type="radio" name="bike_denied_boarding" value="yes" <?= $v('bike_denied_boarding')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_denied_boarding" value="no" <?= $v('bike_denied_boarding')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bike_denied_boarding:yes">
    <div>5. Informerede operatøren dig om årsagen?</div>
    <label><input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $v('bike_refusal_reason_provided')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_refusal_reason_provided" value="no" <?= $v('bike_refusal_reason_provided')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bike_refusal_reason_provided:yes">
    <div>6. Hvad var begrundelsen for afvisningen?</div>
    <select name="bike_refusal_reason_type">
      <option value="">- vælg -</option>
      <option value="capacity" <?= $v('bike_refusal_reason_type')==='capacity'?'selected':'' ?>>Kapacitet</option>
      <option value="equipment" <?= $v('bike_refusal_reason_type')==='equipment'?'selected':'' ?>>Materiel tillader det ikke</option>
      <option value="weight_dim" <?= $v('bike_refusal_reason_type')==='weight_dim'?'selected':'' ?>>Vægt/dimensioner</option>
    </select>
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

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Bestilte du assistance før rejsen?</div>
    <label><input type="radio" name="pmr_booked" value="yes" <?= $v('pmr_booked')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_booked" value="no" <?= $v('pmr_booked')==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="pmr_booked" value="attempted_refused" <?= $v('pmr_booked')==='attempted_refused'?'checked':'' ?> /> Forsøgte men fik afslag</label>
  </div>

  <div class="mt4" data-show-if="pmr_booked:yes">
    <div>3. Blev assistancen leveret?</div>
    <label><input type="radio" name="pmr_delivered_status" value="yes_full" <?= $v('pmr_delivered_status')==='yes_full'?'checked':'' ?> /> Ja, fuldt ud</label>
    <label class="ml8"><input type="radio" name="pmr_delivered_status" value="partial" <?= $v('pmr_delivered_status')==='partial'?'checked':'' ?> /> Delvist</label>
    <label class="ml8"><input type="radio" name="pmr_delivered_status" value="no" <?= $v('pmr_delivered_status')==='no'?'checked':'' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>4. Manglede der PMR-faciliteter, som var lovet før købet?</div>
    <label><input type="radio" name="pmr_promised_missing" value="yes" <?= $v('pmr_promised_missing')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_promised_missing" value="no" <?= $v('pmr_promised_missing')==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="pmr_promised_missing" value="unknown" <?= $v('pmr_promised_missing')==='unknown'?'checked':'' ?> /> Ved ikke</label>
  </div>
  <div class="mt4" data-show-if="pmr_promised_missing:yes">
    <label class="small">Beskriv kort</label>
    <textarea name="pmr_promised_missing_text" rows="2"><?= h($v('pmr_promised_missing_text')) ?></textarea>
  </div>
</div>

<!-- TRIN 3c – Nedgradering (Art.18 stk.3) -->
<div class="card mt12">
  <strong>⬇️ TRIN 3c – Nedgradering (Art.18 stk.3)</strong>
  <p class="small muted">Default er ingen nedgradering. Ret kun hvis du blev placeret i lavere klasse.</p>
  <div class="mt8">
    <div>1. Blev du nedgraderet under rejsen?</div>
    <label><input type="radio" name="downgrade_occurred" value="yes" <?= $v('downgrade_occurred')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="downgrade_occurred" value="no" <?= $v('downgrade_occurred')==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="mt4" data-show-if="downgrade_occurred:yes">
    <div class="small muted">Udfyld klasse/reservations-niveau pr. strækning (pkt. 6 fra Trin 2) så vi kan beregne nedgradering.</div>
    <?php
      $classOptions = [
        '1st_class' => '1. klasse',
        '2nd_class' => '2. klasse',
        'seat_reserved' => 'Reserveret sæde',
        'couchette' => 'Liggevogn',
        'sleeper' => 'Sovevogn',
        'free_seat' => 'Fri plads / ingen reservation',
        'other' => 'Andet',
      ];
      $journeyRowsDowng = $journeyRows ?? [];
      if (empty($journeyRowsDowng)) {
        try {
          $segSrc = (array)($meta['_segments_auto'] ?? []);
          $jr = [];
          foreach ($segSrc as $s) {
            $from = trim((string)($s['from'] ?? ''));
            $to = trim((string)($s['to'] ?? ''));
            $jr[] = [
              'leg' => $from . ' -> ' . $to,
              'dep' => (string)($s['schedDep'] ?? ''),
              'arr' => (string)($s['schedArr'] ?? ''),
              'train' => (string)($s['train'] ?? ($s['trainNo'] ?? '')),
              'change' => (string)($s['change'] ?? ''),
            ];
          }
          if (!empty($jr)) { $journeyRowsDowng = $jr; }
        } catch (\Throwable $e) { /* ignore */ }
      }
      echo $this->element('downgrade_table', compact('journeyRowsDowng','classOptions','form','meta'));
    ?>
  </div>
</div>

<!-- TRIN 3d – Afbrydelser/forsinkelser -->
<div class="card mt12">
  <strong>⏱️ TRIN 3d – Afbrydelser/forsinkelser</strong>
  <p class="small muted">Default er “Nej”/“Ved ikke”. Udfyld kun hvis relevant.</p>
  <?php
    $pid = strtolower((string)$v('preinformed_disruption'));
    $pic = (string)$v('preinfo_channel');
    $ris = (string)$v('realtime_info_seen');
    if ($pid === '' || $pid === 'unknown') { $pid = 'no'; }
    $rtOptions = [
      'app' => 'Ja, i app',
      'train' => 'Ja, i toget',
      'station' => 'Ja, på station',
      'no' => 'Nej',
      'unknown' => 'Ved ikke',
    ];
  ?>
  <div class="mt8">
    <div class="small">Var der meddelt afbrydelse/forsinkelse før dit køb?</div>
    <label class="mr8"><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
    <label class="mr8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="mt4" data-show-if="preinformed_disruption:yes">
    <div class="small">Hvis ja: Hvor blev det vist?</div>
    <select name="preinfo_channel">
      <option value=""><?= __('- Vælg -') ?></option>
      <option value="website" <?= $pic==='website'?'selected':'' ?>>Hjemmeside</option>
      <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
      <option value="app" <?= $pic==='app'?'selected':'' ?>>App</option>
      <option value="station" <?= $pic==='station'?'selected':'' ?>>Station</option>
      <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
    </select>
  </div>
  <div class="mt4" data-show-if="preinformed_disruption:yes">
    <div class="small">Så du realtime-opdateringer under rejsen?</div>
    <?php foreach ($rtOptions as $key => $label): ?>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="<?= h($key) ?>" <?= $ris===$key?'checked':'' ?> /> <?= h($label) ?></label>
    <?php endforeach; ?>
  </div>
</div>

<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('← Tilbage', ['action' => 'entitlements'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
  <?= $this->Html->link('Næste trin →', ['action' => 'incident'], ['class' => 'button']) ?>
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
    var show = checked && valid.includes(checked.value);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
}
document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  updateReveal();
});
document.addEventListener('DOMContentLoaded', updateReveal);
</script>
