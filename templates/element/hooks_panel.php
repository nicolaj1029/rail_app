<?php
// Reusable hooks panel element used both on full page render and AJAX updates.
// Expected vars: $profile, $art12, $art9, $claim, $form, $meta
?>
<h3>Live hooks & AUTO</h3>
<div class="small">Undtagelser (test)</div>
<div class="small">scope: <code><?= h($profile['scope'] ?? '-') ?></code></div>
<?php $arts = (array)($profile['articles'] ?? []); $artsSub = (array)($profile['articles_sub'] ?? []); ?>
<?php if (!empty($arts)): ?>
  <div class="small mt4">Artikler (ON= gælder, OFF = undtaget):</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($arts as $k=>$v): ?>
      <li><?= h($k) ?>: <strong><?= $v ? 'ON' : 'OFF' ?></strong></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if (!empty($artsSub)): ?>
  <div class="small mt4">Del-artikler (Art. 9):</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($artsSub as $k=>$v): ?>
      <li><?= h($k) ?>: <strong><?= $v ? 'ON' : 'OFF' ?></strong></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<hr/>
<div class="small"><strong>TRIN 6</strong> · Art. 12</div>
<?php $h = (array)($art12['hooks'] ?? []); $miss = (array)($art12['missing'] ?? []); ?>
<div class="kv small">applies: <code><?= isset($art12['art12_applies']) ? var_export((bool)$art12['art12_applies'], true) : '-' ?></code></div>
<div class="kv small">missing: <code><?= h(implode(', ', $miss) ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 7</strong> · Art. 18 (remedies)</div>
<?php $remedy = (string)($form['remedyChoice'] ?? ''); $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); ?>
<div class="small">remedy: <code><?= h($remedy ?: '-') ?></code></div>
<div class="small">100-min info: <code><?= h($ri100 ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 8</strong> · Art. 20 (assistance)</div>
<?php $mo=(string)($form['meal_offered']??''); $ho=(string)($form['hotel_offered']??''); $on=(string)($form['overnight_needed']??''); ?>
<div class="small">meal_offered: <code><?= h($mo ?: '-') ?></code></div>
<div class="small">hotel_offered: <code><?= h($ho ?: '-') ?></code></div>
<div class="small">overnight_needed: <code><?= h($on ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 9</strong> · Art. 9 ask_hooks</div>
<?php $ask = (array)($art9['ask_hooks'] ?? []); ?>
<div class="kv small">count: <code><?= count($ask) ?></code></div>
<div class="small">hooks: <code><?= h(implode(', ', $ask) ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 10</strong> · Art. 19 (comp)</div>
<?php $band=(string)($form['compensationBand']??''); $df=(string)($form['delayAtFinalMinutes']??''); ?>
<div class="small">delay_final: <code><?= h($df ?: '-') ?></code></div>
<div class="small">band: <code><?= h($band ?: '-') ?></code></div>
<?php if (isset($claim) && is_array($claim)): ?>
  <div class="small">calc.amount: <code><?= h(number_format((float)($claim['compensation_amount'] ?? 0),2,'.','')) ?></code> <?= h($claim['currency'] ?? '') ?></div>
<?php endif; ?>
<hr/>
<div class="small">AUTO felter</div>
<?php $auto = (array)($meta['_auto'] ?? []); ?>
<?php if (!empty($meta['extraction_provider'])): ?>
  <div class="small">provider: <code><?= h((string)($meta['extraction_provider'] ?? '-')) ?></code></div>
  <div class="small">confidence: <code><?= h((string)number_format((float)($meta['extraction_confidence'] ?? 0), 2)) ?></code></div>
<?php endif; ?>
<div class="small">operator: <code><?= h($auto['operator']['value'] ?? ($form['operator'] ?? '-')) ?></code></div>
<div class="small">country: <code><?= h($auto['operator_country']['value'] ?? ($form['operator_country'] ?? '-')) ?></code></div>
<div class="small">product: <code><?= h($auto['operator_product']['value'] ?? ($form['operator_product'] ?? '-')) ?></code></div>
<div class="small">train: <code><?= h($form['train_no'] ?? '-') ?></code></div>
