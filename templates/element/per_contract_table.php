<?php
/** @var array $contractsView */
if (empty($contractsView)) { return; }
function _hnum($v){ return $v===null ? '—' : number_format((float)$v, 2, ',', '.'); }
?>
<div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
  <strong>Per-kontrakt beregning (Art. 12 undtagelse – særskilte befordringskontrakter)</strong>
  <div class="small" style="margin-top:8px; overflow:auto;">
    <table style="width:100%; border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Kontrakt</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">PNR / Ticket</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Operatør(er)</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Planlagt slutank.</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Faktisk slutank.</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Forsinkelse (min)</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Billetværdi</th>
          <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Kompensation</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contractsView as $c): ?>
          <tr>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><code><?= h((string)$c['contractKey']) ?></code></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
              <?php if (!empty($c['pnr'])): ?>PNR: <code><?= h((string)$c['pnr']) ?></code><br><?php endif; ?>
              <?php if (!empty($c['ticketId'])): ?>Ticket: <code><?= h((string)$c['ticketId']) ?></code><?php endif; ?>
            </td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h((string)($c['operators'] ?: '—')) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h((string)($c['plannedArrival'] ?: '—')) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h((string)($c['actualArrival'] ?: '—')) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
              <?= h(($c['delayMinutes'] === null) ? '—' : (string)$c['delayMinutes']) ?>
              <?php if (!empty($c['delayStatus']) && (string)$c['delayStatus'] !== 'OK'): ?>
                <span class="muted"> (<?= h((string)$c['delayStatus']) ?>)</span>
              <?php endif; ?>
            </td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= _hnum($c['ticketValue']) ?> <?= h((string)($c['currency'] ?: '')) ?></td>
            <td style="padding:4px; border-bottom:1px solid #f3f3f3;">
              <?php if ((int)$c['compPercent'] > 0): ?>
                <span class="badge" style="background:#e6ffed; border:1px solid #b2f2bb; border-radius:999px; padding:0 6px;"><?= h((string)$c['compPercent']) ?>%</span>
                <?= _hnum($c['compAmount']) ?> <?= h((string)($c['currency'] ?: '')) ?>
              <?php else: ?>
                <span class="badge" style="background:#f6f8fa; border:1px solid #d0d7de; border-radius:999px; padding:0 6px;">0%</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="small" style="margin-top:8px;">
    <strong>Note:</strong> Kompensation pr. kontrakt efter art. 19 (25% ≥ 60 min, 50% ≥ 120 min). Refusion/omlægning (art. 18) og assistance (art. 20) håndteres af den enkelte operatør for sin kontrakt.
  </div>
</div>
