<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? [];
$euOnlySuggested = $euOnlySuggested ?? null;
$euOnlyReason = $euOnlyReason ?? null;
$journey = $journey ?? [];

$v = fn(string $k): string => (string)($form[$k] ?? '');
?>
<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
</style>

<h1>TRIN 4 - Hændelse (Art. 18/20 standard gating)</h1>

<?= $this->Form->create(null, ['novalidate' => true]) ?>

<!-- Standard gating (flyttet fra tidligere TRIN 3) -->
<div class="card mt12">
  <strong>⚡ Hændelse (Art.18/20 standard gating)</strong>
  <p class="small muted">Brug hændelsen til at aktivere den normale art.18/20-vurdering.</p>

  <div class="mt8">
    <div>Hændelsestype (vælg én)</div>
    <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
    <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
  </div>

  <div class="mt4" data-show-if="incident_main:delay">
    <div>Har du modtaget besked om ≥60 minutters forsinkelse?</div>
    <label><input type="radio" name="expected_delay_60" value="yes" <?= $v('expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="expected_delay_60" value="no" <?= $v('expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
  </div>

  <div class="mt4">
    <label><input type="checkbox" name="incident_missed" value="yes" <?= $v('incident_missed')==='yes'?'checked':'' ?> /> Mistet forbindelse (kan kombineres)</label>
  </div>

  <div class="mt4" data-show-if="incident_missed:yes">
    <div class="card" style="margin-top:8px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
      <?= $this->element('missed_connection_block', compact('meta','form')) ?>
    </div>
  </div>
</div>

<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('← Tilbage', ['action' => 'journey'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
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
