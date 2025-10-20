<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
?>
<h1>Rettigheder og muligheder</h1>
<form method="post">
  <fieldset>
    <legend>Art. 12</legend>
    <pre><?= h(json_encode($art12, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </fieldset>
  <fieldset>
    <legend>Art. 9 (kun på anmodning)</legend>
    <label><input type="checkbox" name="art9_opt_in" <?= !empty($compute['art9OptIn']) ? 'checked' : '' ?> /> Vis/brug Art. 9</label>
    <?php if (!empty($compute['art9OptIn'])): ?>
      <pre><?= h(json_encode($art9, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php else: ?>
      <p>Art. 9 vises kun hvis du markerer boksen.</p>
    <?php endif; ?>
  </fieldset>
  <fieldset>
    <legend>Refusion og assistance</legend>
    <p>Refusion: <?= !empty($refund['eligible']) ? 'Mulig' : 'Ikke mulig' ?></p>
    <p>Assistance (Art. 18): <?= h(implode(', ', (array)($refusion['options'] ?? []))) ?></p>
  </fieldset>
  <div>
    <label><input type="checkbox" name="known_delay" <?= !empty($compute['knownDelayBeforePurchase']) ? 'checked' : '' ?> /> Kendt før køb?</label>
  </div>
  <button type="submit">Fortsæt</button>
</form>
