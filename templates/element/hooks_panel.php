<?php
// Reusable hooks panel element used both on full page render and AJAX updates.
// Expected vars: $profile, $art12, $art9, $claim, $form, $meta
?>
<h3>Live hooks & AUTO</h3>
<div class="small">Undtagelser (test)</div>
<div class="small">scope: <code><?= h($profile['scope'] ?? '-') ?></code></div>
<?php if (!empty($profile['blocked'])): ?>
  <div class="small warn">Denne rute/scope er blokeret i EU-flowet (nationalt regime anvendes).</div>
<?php endif; ?>
<?php if (!empty($profile['ui_banners'])): ?>
  <div class="small mt4">Bemærkninger:</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ((array)$profile['ui_banners'] as $b): ?>
      <li><?= h($b) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
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
<?php $reasons = (array)($art12['reasoning'] ?? []); ?>
<?php if (!empty($reasons)): ?>
  <div class="small mt4">Begrundelse:</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($reasons as $r): ?>
      <li><?= h($r) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<details class="small" style="margin:6px 0;">
  <summary style="cursor:pointer;">Vurderingsgrundlag (nøgleværdier)</summary>
  <div class="small" style="margin-top:4px;">
    <div>through_ticket_disclosure: <code><?= h((string)($h['through_ticket_disclosure'] ?? 'unknown')) ?></code></div>
    <div>separate_contract_notice: <code><?= h((string)($h['separate_contract_notice'] ?? 'unknown')) ?></code></div>
    <div>single_txn_operator: <code><?= h((string)($h['single_txn_operator'] ?? 'unknown')) ?></code></div>
    <div>single_txn_retailer: <code><?= h((string)($h['single_txn_retailer'] ?? 'unknown')) ?></code></div>
    <div>shared_pnr_scope: <code><?= h((string)($h['shared_pnr_scope'] ?? 'unknown')) ?></code></div>
    <div>seller_type_operator: <code><?= h((string)($h['seller_type_operator'] ?? 'unknown')) ?></code></div>
    <div>seller_type_agency: <code><?= h((string)($h['seller_type_agency'] ?? 'unknown')) ?></code></div>
    <div>multi_operator_trip: <code><?= h((string)($h['multi_operator_trip'] ?? 'unknown')) ?></code></div>
    <div>single_booking_reference: <code><?= h((string)($h['single_booking_reference'] ?? 'unknown')) ?></code></div>
    <div>mct_realistic: <code><?= h((string)($h['mct_realistic'] ?? 'unknown')) ?></code></div>
    <div>one_contract_schedule: <code><?= h((string)($h['one_contract_schedule'] ?? 'unknown')) ?></code></div>
    <div>contact_info_provided: <code><?= h((string)($h['contact_info_provided'] ?? 'unknown')) ?></code></div>
    <div>responsibility_explained: <code><?= h((string)($h['responsibility_explained'] ?? 'unknown')) ?></code></div>
  </div>
  <?php if (!empty($meta['_identifiers'])): $ids=(array)$meta['_identifiers']; ?>
    <div class="small mt4">Identifikatorer (AUTO):
      <?php if (!empty($ids['pnr'])): ?> PNR: <code><?= h((string)$ids['pnr']) ?></code><?php endif; ?>
      <?php if (!empty($ids['order_no'])): ?> <?= !empty($ids['pnr'])?' · ':'' ?>Order: <code><?= h((string)$ids['order_no']) ?></code><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($meta['_barcode'])): $bc=(array)$meta['_barcode']; ?>
    <div class="small">Barcode: <code><?= h((string)($bc['format'] ?? '')) ?></code> (<?= h((string)($bc['chars'] ?? '')) ?> chars)</div>
  <?php endif; ?>
</details>
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

<?php if (!empty($groupedTickets)): ?>
  <hr/>
  <div class="small"><strong>Billetter i sagen</strong></div>
  <?php foreach ($groupedTickets as $gi => $g): $shared = !empty($g['shared']); ?>
    <div class="small mt4">
      <strong>Gruppe <?= (int)($gi+1) ?></strong>
      <?php if (!empty($g['pnr']) || !empty($g['dep_date'])): ?>
        (<?= h(trim((string)($g['pnr'] ?? '') . ' ' . (string)($g['dep_date'] ?? ''))) ?>)
      <?php endif; ?>
      <span class="badge" style="margin-left:6px;"><?= $shared ? 'samlet' : 'enkelt' ?></span>
    </div>
    <ul class="small" style="margin:4px 0 0 16px;">
      <?php foreach ((array)($g['tickets'] ?? []) as $t): ?>
        <li>
          <?= h((string)($t['file'] ?? '')) ?><?= (!empty($t['pnr'])||!empty($t['dep_date'])) ? (': ' . h(trim((string)($t['pnr'] ?? '') . ' ' . (string)($t['dep_date'] ?? '')))) : '' ?>
          <?php $pc = isset($t['passengers']) ? count((array)$t['passengers']) : 0; if ($pc>0): ?>
            <span class="badge" style="margin-left:6px;">pax <?= (int)$pc ?></span>
          <?php endif; ?>
          <?php if (!empty($t['file'])): ?>
            <form method="post" style="display:inline; margin-left:6px;">
              <input type="hidden" name="remove_ticket" value="<?= h((string)$t['file']) ?>" />
              <button type="submit" class="small" title="Fjern denne billet">Fjern</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
  <div class="small muted mt4">Grupperet efter PNR + dato. Upload flere billetter i TRIN 4 for at samle en sag.</div>
<?php endif; ?>
