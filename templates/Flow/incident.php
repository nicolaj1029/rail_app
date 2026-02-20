<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];
$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);
$art9On  = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$incidentHint = $isOngoing ? 'Hvad er situationen nu?' : ($isCompleted ? 'Hvad var den afgoerende haendelse?' : '');

$v = fn(string $k): string => (string)($form[$k] ?? '');
$segCount = is_array($journey['segments'] ?? null) ? count($journey['segments']) : 0;
if ($segCount < 2) {
    $altSegs = $meta['_segments_llm_suggest'] ?? ($meta['_segments_auto'] ?? []);
    if (is_array($altSegs)) { $segCount = max($segCount, count($altSegs)); }
}
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .hidden { display:none; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .flow-wrapper { max-width: 1080px; margin: 0 auto; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
  select, input[type="text"], input[type="number"] { max-width: 520px; width: 100%; }
  .widget-title { display:flex; align-items:center; gap:10px; font-weight:700; }
  .step-badge { width:28px; height:28px; border-radius:999px; background:#e9f2ff; border:1px solid #cfe0ff; color:#1e3a8a; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; line-height:1; flex:0 0 auto; }
  .fm-badge { width:26px; height:26px; border-radius:999px; background:#fff3cd; border:1px solid #eed27c; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; }
  .fm-badge svg { width:16px; height:16px; display:block; }
</style>

<div class="flow-wrapper">
  <?php if ($isOngoing): ?>
    <h1>TRIN 4 - Forsinkelse, aflysning eller mistet forbindelse (igangvaerende rejse)</h1>
  <?php elseif ($isCompleted): ?>
    <h1>TRIN 4 - Haendelse (afsluttet rejse)</h1>
  <?php else: ?>
    <h1>TRIN 4 - Haendelse (Art. 18/20 standard gating)</h1>
  <?php endif; ?>

  <?= $this->Form->create(null, ['novalidate' => true]) ?>


  <!-- Preinformed disruption (moved from TRIN 3d) -->
  <?php
    $pid = strtolower((string)$v('preinformed_disruption'));
    $pic = (string)($v('preinfo_channel'));
    $ris = (string)($v('realtime_info_seen'));
    if ($pid === '' || $pid === 'unknown') { $pid = 'no'; }
    $rtOptions = [
      'app' => 'Ja, i app',
      'train' => 'Ja, i toget',
      'station' => 'Ja, paa station',
      'no' => 'Nej',
      'unknown' => 'Ved ikke',
    ];
  ?>
  <div class="card mt12 <?= $art91On ? '' : 'hidden' ?>" data-art="9(1)">
    <strong>⏱️ Afbrydelser/forsinkelser</strong>
    <p class="small muted">Default er "Nej". Udfyld kun hvis relevant.</p>

    <div class="mt8">
      <div class="small">Var der meddelt afbrydelse/forsinkelse foer dit koeb?</div>
      <label><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
    </div>

    <div class="mt8" data-show-if="preinformed_disruption:yes" data-art="9(1)">
      <div class="small">Hvis ja: Hvor blev det vist?</div>
      <select name="preinfo_channel">
        <option value="">- Vaelg -</option>
        <option value="website" <?= $pic==='website'?'selected':'' ?>>Hjemmeside</option>
        <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
        <option value="app" <?= $pic==='app'?'selected':'' ?>>App</option>
        <option value="station" <?= $pic==='station'?'selected':'' ?>>Station</option>
        <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8 <?= $art92On ? '' : 'hidden' ?>" data-show-if="preinformed_disruption:yes" data-art="9(2)">
      <div class="small">Saa du realtime-opdateringer under rejsen?</div>
      <?php $i = 0; foreach ($rtOptions as $key => $label): ?>
        <label class="<?= $i>0 ? 'ml8' : '' ?>"><input type="radio" name="realtime_info_seen" value="<?= h($key) ?>" <?= $ris===$key?'checked':'' ?> /> <?= h($label) ?></label>
      <?php $i++; endforeach; ?>
    </div>
  </div>

  <!-- Standard gating -->
  <div class="card mt12">
    <strong>⚡ Haendelse (Art.18/20)</strong>
    <p class="small muted">Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.<?= $incidentHint !== '' ? (' ' . h($incidentHint)) : '' ?></p>

    <div class="mt8">
      <div>Haendelsestype (vaelg en)</div>
      <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
      <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
    </div>

    <div class="mt4" data-show-if="incident_main:delay">
      <div>Har du modtaget besked om &ge;60 minutters forsinkelse?</div>
      <label><input type="radio" name="expected_delay_60" value="yes" <?= $v('expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="expected_delay_60" value="no" <?= $v('expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
    </div>
  </div>

  <!-- Mistet forbindelse -->
  <div class="card mt12">
    <strong>🔗 Mistet forbindelse</strong>
    <p class="small muted">Marker kun hvis du faktisk missede et skift.</p>

    <div class="mt4">
      <div>Mistede du en forbindelse pga. haendelsen?</div>
      <label><input type="radio" name="incident_missed" value="yes" <?= $v('incident_missed')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="incident_missed" value="no" <?= $v('incident_missed')==='no'?'checked':'' ?> /> Nej</label>
      <?php if ($segCount < 2): ?>
        <div class="small muted mt4">Vi fandt ingen skift paa billetterne &ndash; marker kun hvis du faktisk missede et skift.</div>
      <?php endif; ?>
    </div>

    <div class="mt4" data-show-if="incident_missed:yes">
      <div class="card" style="margin-top:8px;">
        <?= $this->element('missed_connection_block', compact('meta','form')) ?>
      </div>
    </div>
  </div>



  <!-- Form & exemptions (moved from TRIN 9) -->
  <div class="card mt12">
    <div class="widget-title"><span class="step-badge">3</span><span>Form og undtagelser</span></div>
    <div class="small mt4">Udbetaling sker som udgangspunkt kontant. Vouchers accepteres ikke i denne loesning.</div>
    <input type="hidden" name="voucherAccepted" value="no" />

    <?php $exc = (string)($form['operatorExceptionalCircumstances'] ?? ''); ?>
    <div class="mt8">
      <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
          <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
        </svg>
      </span>
      Henviser operatoeren til ekstraordinaere forhold (Art. 19(10))?
    </div>
    <label><input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc===''||$exc==='unknown')?'checked':'' ?> /> Ved ikke</label>

    <?php $excType = (string)($form['operatorExceptionalType'] ?? ''); ?>
    <div class="mt8" data-show-if="operatorExceptionalCircumstances:yes">
      <div class="small">Hvis ja: vaelg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)</div>
      <select name="operatorExceptionalType">
        <option value="">- Vaelg type -</option>
        <option value="weather" <?= $excType==='weather'?'selected':'' ?>>Vejr</option>
        <option value="sabotage" <?= $excType==='sabotage'?'selected':'' ?>>Sabotage</option>
        <option value="infrastructure_failure" <?= $excType==='infrastructure_failure'?'selected':'' ?>>Infrastrukturfejl</option>
        <option value="third_party" <?= $excType==='third_party'?'selected':'' ?>>Tredjepart</option>
        <option value="own_staff_strike" <?= $excType==='own_staff_strike'?'selected':'' ?>>Egen personalestrejke</option>
        <option value="external_strike" <?= $excType==='external_strike'?'selected':'' ?>>Ekstern strejke</option>
        <option value="other" <?= $excType==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8">
      <label><input type="checkbox" name="minThresholdApplies" value="1" <?= !empty($form['minThresholdApplies']) ? 'checked' : '' ?> /> Anvend min. taerskel <= 4 EUR (Art. 19(8))</label>
    </div>
  </div>
  <div class="mt12" style="display:flex; gap:8px; align-items:center;">
    <?= $this->Html->link('<- Tilbage', ['action' => 'journey'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
  </div>

  <?= $this->Form->end() ?>

  <?= $this->element('hooks_panel') ?>
</div>

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
