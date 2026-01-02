<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];

$v = fn(string $k): string => (string)($form[$k] ?? '');
$segCount = is_array($journey['segments'] ?? null) ? count($journey['segments']) : 0;
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .flow-wrapper { max-width: 1080px; margin: 0 auto; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
</style>

<div class="flow-wrapper">
  <h1>TRIN 4 - Haendelse (Art. 18/20 standard gating)</h1>

  <?= $this->Form->create(null, ['novalidate' => true]) ?>

  <!-- Standard gating -->
  <div class="card mt12">
    <strong>⚡ Haendelse (Art.18/20)</strong>
    <p class="small muted">Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.</p>

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

  <!-- Transport til/fra (Art.20) -->
  <div class="card mt12">
    <strong>🚐 Transport til/fra (Art.20)</strong>
    <p class="small muted">Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.</p>

    <div class="mt8">
      <div>Hvor var du, da det skete? (vaelg en)</div>
      <label><input type="radio" name="stranded_location" value="track" <?= $v('stranded_location')==='track'?'checked':'' ?> /> Jeg sad fast i toget paa sporet</label>
      <label class="ml8"><input type="radio" name="stranded_location" value="station" <?= $v('stranded_location')==='station'?'checked':'' ?> /> Jeg var paa en station uden videre tog</label>
      <label class="ml8"><input type="radio" name="stranded_location" value="irrelevant" <?= $v('stranded_location')==='irrelevant'?'checked':'' ?> /> Ikke relevant / andet</label>
    </div>

    <div class="mt4" data-show-if="stranded_location:track,station">
      <span>Blev der stillet transport til raadighed for at komme vaek/videre?</span>
      <?php $bt = $v('blocked_train_alt_transport'); ?>
      <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt==='no'?'checked':'' ?> /> Nej</label>
      <label class="ml8"><input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt==='irrelevant'?'checked':'' ?> /> Ved ikke</label>
    </div>

    <div class="mt4" data-show-if="blocked_train_alt_transport:yes">
      <div class="grid-3">
        <label>Tilbudt af
          <select name="assistance_alt_transport_offered_by">
            <?php foreach (['operator'=>'Operator','station'=>'Station','retailer'=>'Retailer','other'=>'Andet'] as $val => $label): ?>
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
            <option value="">Vaelg</option>
            <option value="station" <?= $to==='station'?'selected':'' ?>>Station</option>
            <option value="other_departure" <?= $to==='other_departure'?'selected':'' ?>>Andet afgangssted</option>
            <option value="final_destination" <?= $to==='final_destination'?'selected':'' ?>>Endelige bestemmelsessted</option>
          </select>
        </label>
      </div>
    </div>

    <div class="mt4" data-show-if="blocked_train_alt_transport:no">
      <div class="small muted">Angiv egne udgifter hvis du selv ordnede transport.</div>
      <div class="grid-3 mt4">
        <label>Beløb
          <input type="number" step="0.01" name="blocked_self_paid_amount" value="<?= h($v('blocked_self_paid_amount')) ?>" />
        </label>
        <label>Valuta (fx DKK/EUR)
          <input type="text" name="blocked_self_paid_currency" value="<?= h($v('blocked_self_paid_currency')) ?>" />
        </label>
        <label class="small">Kvittering
          <input type="file" name="blocked_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />
        </label>
      </div>
      <?php if ($f = $v('blocked_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>
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
