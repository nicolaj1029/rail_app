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
    <?php $travelState = (string)($flags['travel_state'] ?? ''); ?>
    <?php if ($travelState !== 'completed'): ?>
    <div id="delayLikelyBox" style="margin:10px 0; padding:10px; border:1px solid #ddd; background:#fff; border-radius:6px;">
      <strong>Bekræftelse</strong>
      <div class="small" style="margin-top:6px; color:#555;">Rejsen er ikke afsluttet. Bekræft venligst at en forsinkelse på ≥ 60 minutter er sandsynlig.</div>
      <label style="display:block; margin-top:8px;"><input type="checkbox" name="delayLikely60" value="1" <?= !empty($form['delayLikely60']) ? 'checked' : '' ?> /> Forsinkelse ≥ 60 minutter er sandsynlig</label>
      <div class="small" style="margin-top:6px; color:#b00; <?= (!empty($form['delayLikely60'])) ? 'display:none;' : '' ?>">Afkryds venligst boksen for at fortsætte.</div>
    </div>
    <?php endif; ?>
  </fieldset>
  <button type="submit">Fortsæt</button>
<?= $this->Form->end() ?>

<div id="hooksPanel">
  <?= $this->element('hooks_panel', array_merge(compact('profile','art12','art9','form','meta'), ['showFormDecision' => true, 'showArt12Section' => false])) ?>
</div>
