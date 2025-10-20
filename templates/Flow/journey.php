<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
?>
<h1>Bekræft rejse og forsinkelse</h1>
<form method="post">
  <div>
    <label for="delay_min_eu">Forsinkelse i minutter (EU-only)</label>
    <input type="number" id="delay_min_eu" name="delay_min_eu" value="<?= (int)($compute['delayMinEU'] ?? 0) ?>" />
  </div>
  <div>
    <label><input type="checkbox" name="known_delay" <?= !empty($compute['knownDelayBeforePurchase']) ? 'checked' : '' ?> /> Kendt før køb?</label>
  </div>
  <div>
    <label><input type="checkbox" name="extraordinary" <?= !empty($compute['extraordinary']) ? 'checked' : '' ?> /> Ekstraordinære omstændigheder?</label>
  </div>
  <fieldset style="background:#ffa50022; padding:10px;">
    <legend>TRIN 2 – Incident (vælg én) + evt. "missed connection"</legend>
    <?php $main = $incident['main'] ?? ''; ?>
    <label><input type="radio" name="incident_main" value="delay" <?= $main==='delay'?'checked':'' ?> /> Delay (kun muligt at vælge en)</label><br/>
    <label><input type="radio" name="incident_main" value="cancellation" <?= $main==='cancellation'?'checked':'' ?> /> Cancellation (kun muligt at vælge en)</label><br/>
    <label><input type="checkbox" name="missed_connection" <?= !empty($incident['missed']) ? 'checked' : '' ?> /> Missed connection (kan vælges samtidig)</label>
  </fieldset>
  <button type="submit">Fortsæt</button>
</form>
