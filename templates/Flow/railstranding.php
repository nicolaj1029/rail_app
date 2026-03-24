<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$v = fn(string $k): string => (string)($form[$k] ?? '');
$needsRouter = ((string)($flags['needs_initial_incident_router'] ?? '')) === '1';
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isPreview = !empty($flowPreview);
$title = match ($travelState) {
    'ongoing' => 'TRIN 3.5 - Strandet paa station/sporet (igangvaerende rejse)',
    'completed' => 'TRIN 3.5 - Strandet paa station/sporet (afsluttet rejse)',
    default => 'TRIN 3.5 - Strandet paa station/sporet',
};
$railStrandingContext = $v('rail_stranding_context') !== '' ? $v('rail_stranding_context') : 'no';
?>

<style>
  .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  [data-show-if] { display:none; }
</style>

<h1><?= h($title) ?></h1>
<p class="small muted">Dette rail-specifikke trin afklarer, om du blev strandet paa en station eller sad fast i toget paa sporet. Det holdes adskilt fra den generelle router og fra PMR/Cykel.</p>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="card mt12">
  <strong>Rail-stranding</strong>

  <div class="mt8">
    <div>Hvor blev du strandet?</div>
    <label><input type="radio" name="rail_stranding_context" value="no" <?= $railStrandingContext === 'no' ? 'checked' : '' ?> /> Ikke strandet</label>
    <label class="ml8"><input type="radio" name="rail_stranding_context" value="station" <?= $railStrandingContext === 'station' ? 'checked' : '' ?> /> Strandet paa station</label>
    <label class="ml8"><input type="radio" name="rail_stranding_context" value="track" <?= $railStrandingContext === 'track' ? 'checked' : '' ?> /> Strandet paa sporet / i toget</label>
  </div>

  <div class="mt8 small muted" data-show-if="rail_stranding_context:station">
    Brug denne, hvis du endte paa en station og ikke kunne komme videre som planlagt.
  </div>

  <div class="mt8 small muted" data-show-if="rail_stranding_context:track">
    Brug denne, hvis toget stod stille paa sporet, og evakuering/alternativ transport kan blive relevant senere.
  </div>
</div>

<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('<- Tilbage', ['action' => $needsRouter ? 'station' : 'entitlements'], ['class' => 'button', 'style' => 'background:#eee; color:#333;', 'escape' => false]) ?>
  <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
</div>

</fieldset>
<?= $this->Form->end() ?>

<script>
function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if');
    if (!spec) return;
    var parts = spec.split(':');
    if (parts.length !== 2) return;
    var name = parts[0];
    var valid = parts[1].split(',');
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    var value = checked ? checked.value : '';
    var show = value && valid.includes(value);
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
