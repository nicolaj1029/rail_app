<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
// Optional form mirror (for checkboxes like delayLikely60)
$form = $form ?? [];
?>
<h1>Bekræft rejse og forsinkelse</h1>
<?= $this->Form->create(null) ?>
  <!-- Kendt før køb? flyttet til TRIN 3 (entitlements). -->
  <!-- Ekstraordinære omstændigheder? flyttet til TRIN 6 (compensation). -->
  <fieldset style="background:#ffa50022; padding:10px;">
    <legend>TRIN 2 – Incident (vælg én)</legend>
    <?php $main = $incident['main'] ?? ''; ?>
    <label><input type="radio" name="incident_main" value="delay" <?= $main==='delay'?'checked':'' ?> /> Delay (kun muligt at vælge en)</label><br/>
    <label><input type="radio" name="incident_main" value="cancellation" <?= $main==='cancellation'?'checked':'' ?> /> Cancellation (kun muligt at vælge en)</label><br/>
    <!-- Delay ≥60 bekræftelse fjernet: vi bruger live-data i TRIN 3/6 -->
  </fieldset>
  <button type="submit">Fortsæt</button>
<?= $this->Form->end() ?>

<div id="hooksPanel">
  <?= $this->element('hooks_panel', array_merge(compact('profile','art12','art9','form','meta'), ['showFormDecision' => true, 'showArt12Section' => false])) ?>
</div>
