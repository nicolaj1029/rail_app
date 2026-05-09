<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$ferryRights = (array)($ferryRights ?? []);
$ferryScope = (array)($ferryScope ?? []);
$selectedDeparture = (array)($meta['ferry_selected_departure'] ?? []);
$opsEvidence = (array)($meta['ferry_operational_evidence'] ?? []);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';

$operatorName = trim((string)($opsEvidence['operator_name'] ?? ($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ''))));
$vesselName = trim((string)($opsEvidence['vessel_name'] ?? ($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? ''))));
$imo = trim((string)($opsEvidence['vessel_imo'] ?? ($selectedDeparture['vessel_imo'] ?? '')));
$mmsi = trim((string)($opsEvidence['vessel_mmsi'] ?? ($selectedDeparture['vessel_mmsi'] ?? '')));
$opsSource = strtoupper(trim((string)($opsEvidence['source'] ?? '')));
$opsStatus = trim((string)($opsEvidence['status'] ?? ''));
$opsConfidence = trim((string)($opsEvidence['confidence'] ?? ''));
$opsScore = isset($opsEvidence['evidence_score']) ? (int)$opsEvidence['evidence_score'] : null;
$opsLabel = $opsStatus !== '' ? $opsStatus : ($opsSource !== '' ? 'Ops data klar' : 'Ingen ops data');
$opsDetailParts = array_filter([
    $opsSource !== '' ? $opsSource : null,
    $opsScore !== null && $opsScore > 0 ? ('score ' . $opsScore) : null,
    $opsConfidence !== '' ? $opsConfidence : null,
]);

$scheduledDuration = is_numeric($form['scheduled_journey_duration_minutes'] ?? null)
    ? (int)$form['scheduled_journey_duration_minutes']
    : null;
if ($scheduledDuration === null && !empty($opsEvidence['scheduled_departure_local']) && !empty($opsEvidence['scheduled_arrival_local'])) {
    $depTs = strtotime((string)$opsEvidence['scheduled_departure_local']);
    $arrTs = strtotime((string)$opsEvidence['scheduled_arrival_local']);
    if ($depTs !== false && $arrTs !== false && $arrTs > $depTs) {
        $scheduledDuration = (int)round(($arrTs - $depTs) / 60);
    }
}

$arrivalDelay = is_numeric($form['arrival_delay_minutes'] ?? null)
    ? (int)$form['arrival_delay_minutes']
    : (is_numeric($opsEvidence['arrival_delay_minutes_estimated'] ?? null) ? (int)$opsEvidence['arrival_delay_minutes_estimated'] : null);
$departureDelay = is_numeric($opsEvidence['departure_delay_minutes_estimated'] ?? null) ? (int)$opsEvidence['departure_delay_minutes_estimated'] : null;

$threshold = null;
if ($scheduledDuration !== null) {
    if ($scheduledDuration <= 240) {
        $threshold = 60;
    } elseif ($scheduledDuration < 480) {
        $threshold = 120;
    } elseif ($scheduledDuration < 1440) {
        $threshold = 180;
    } else {
        $threshold = 360;
    }
}

$band = (string)($ferryRights['art19_comp_band'] ?? ($flags['ferry_art19_comp_band'] ?? 'none'));
$gateArt19 = !empty($ferryRights['gate_art19']);
$gateArt18 = !empty($ferryRights['gate_art18']) || ((string)($flags['gate_art18'] ?? '') === '1');
$gateRefreshments = !empty($ferryRights['gate_art17_refreshments']) || ((string)($flags['gate_ferry_art17_refreshments'] ?? '') === '1');
$gateHotel = !empty($ferryRights['gate_art17_hotel']) || ((string)($flags['gate_ferry_art17_hotel'] ?? '') === '1');
$gatePmrRemedy = ((string)($flags['gate_ferry_pmr_remedy'] ?? '') === '1');

$priceRaw = trim((string)($form['price'] ?? ($journey['ticketPrice']['value'] ?? ($meta['_auto']['price']['value'] ?? ''))));
$ticketPrice = 0.0;
if ($priceRaw !== '') {
    $ticketPrice = (float)preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $priceRaw));
}
$currency = strtoupper(trim((string)($form['price_currency'] ?? ($journey['ticketPrice']['currency'] ?? ($meta['_auto']['price_currency']['value'] ?? 'EUR')))));
if ($currency === '') {
    $currency = 'EUR';
}

$bandPct = in_array($band, ['25', '50'], true) ? (int)$band : 0;
$estimatedAmount = $gateArt19 && $bandPct > 0 && $ticketPrice > 0 ? round($ticketPrice * $bandPct / 100, 2) : null;
$statusText = $gateArt19
    ? ('Art. 19 aktiv' . ($bandPct > 0 ? ' · ' . $bandPct . '%' : ''))
    : ($arrivalDelay !== null && $threshold !== null
        ? 'Under kompensationstærskel'
        : 'Afventer forsinkelse/sejltid');
$nextText = $isCompleted
    ? 'Resultatet bygger på afsluttet sejlads og de nuværende ferry-svar.'
    : ($isOngoing
        ? 'Udfyld kun det, der er relevant nu. ETA/ATA og gates kan opdateres løbende.'
        : 'Estimatet opdateres, når afgang, hændelse og ankomstforsinkelse er kendt.');

$durationLabel = $scheduledDuration !== null ? (floor($scheduledDuration / 60) . 't ' . ($scheduledDuration % 60) . 'm') : 'Afventer';
$thresholdLabel = $threshold !== null ? ($threshold . ' min') : 'Afventer';
$arrivalDelayLabel = $arrivalDelay !== null ? (($arrivalDelay > 0 ? '+' : '') . $arrivalDelay . ' min') : 'Afventer';
$departureDelayLabel = $departureDelay !== null ? (($departureDelay > 0 ? '+' : '') . $departureDelay . ' min') : 'Afventer';
$art17Label = $gateRefreshments && $gateHotel
    ? 'Refreshments + hotel'
    : ($gateHotel ? 'Hotel aktiv' : ($gateRefreshments ? 'Refreshments aktiv' : 'Ikke aktiv endnu'));
$art18Label = $gateArt18 ? 'Aktiv' : 'Ikke aktiv endnu';
$pmrLabel = $gatePmrRemedy ? 'Aktiv ved nægtet indskibning' : 'Ikke aktiv';
$vesselLabel = trim(implode(' · ', array_filter([
    $vesselName !== '' ? $vesselName : null,
    $imo !== '' ? ('IMO ' . $imo) : null,
    $mmsi !== '' ? ('MMSI ' . $mmsi) : null,
])));
$vesselLabel = $vesselLabel !== '' ? $vesselLabel : 'Ikke valgt endnu';
$operatorLabel = $operatorName !== '' ? $operatorName : 'Ikke valgt endnu';
?>

<style>
  .ferry-live-estimate { margin-top:10px; padding:12px; border:1px solid #bae6fd; border-radius:8px; background:#f0f9ff; }
  .ferry-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .ferry-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #7dd3fc; background:#e0f2fe; color:#075985; font-size:12px; font-weight:700; }
  .ferry-live-estimate-amount { font-size:28px; font-weight:800; line-height:1; color:#082f49; }
  .ferry-live-estimate-sub { color:#475569; font-size:12px; }
  .ferry-live-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .ferry-live-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .ferry-live-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .ferry-live-estimate-value { margin-top:4px; font-weight:700; color:#0f172a; }
</style>

<div
  id="ferryLiveEstimate"
  class="ferry-live-estimate"
  data-ticket-price="<?= h((string)$ticketPrice) ?>"
  data-currency="<?= h($currency) ?>"
>
  <div class="ferry-live-estimate-head">
    <div>
      <div><strong>Live ferry-estimat</strong></div>
      <div class="ferry-live-estimate-sub"><?= h($nextText) ?></div>
    </div>
    <div class="ferry-live-estimate-status" data-ferry-live-status><?= h($statusText) ?></div>
  </div>

  <div style="margin-top:10px;">
    <div class="ferry-live-estimate-amount" data-ferry-live-amount>
      <?= $estimatedAmount !== null ? h(number_format($estimatedAmount, 2, '.', ',')) . ' ' . h($currency) : ($bandPct > 0 ? h((string)$bandPct) . '% af billetpris' : 'Afventer') ?>
    </div>
    <div class="ferry-live-estimate-sub">
      <?= $gateArt19
          ? 'Foreløbigt Art. 19-estimat ud fra billetpris, ankomstforsinkelse og planlagt sejltid.'
          : 'Art. 19 kræver kendt planlagt sejltid og ankomstforsinkelse. AIS/ETA er støttebevis, ikke endelig afgørelse.' ?>
    </div>
  </div>

  <div class="ferry-live-estimate-grid">
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Art. 19 niveau</div>
      <div class="ferry-live-estimate-value" data-ferry-live-art19><?= h($bandPct > 0 ? ($bandPct . '%') : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Planlagt sejltid</div>
      <div class="ferry-live-estimate-value" data-ferry-live-duration><?= h($durationLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Art. 19 threshold</div>
      <div class="ferry-live-estimate-value" data-ferry-live-threshold><?= h($thresholdLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Ankomstforsinkelse</div>
      <div class="ferry-live-estimate-value" data-ferry-live-arrival-delay><?= h($arrivalDelayLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Afgangsforsinkelse</div>
      <div class="ferry-live-estimate-value" data-ferry-live-departure-delay><?= h($departureDelayLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Art. 17 assistance</div>
      <div class="ferry-live-estimate-value" data-ferry-live-art17><?= h($art17Label) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Art. 18 refund/ombooking</div>
      <div class="ferry-live-estimate-value" data-ferry-live-art18><?= h($art18Label) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">PMR remedy</div>
      <div class="ferry-live-estimate-value" data-ferry-live-pmr><?= h($pmrLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Operator</div>
      <div class="ferry-live-estimate-value"><?= h($operatorLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Fartøj</div>
      <div class="ferry-live-estimate-value"><?= h($vesselLabel) ?></div>
    </div>
    <div class="ferry-live-estimate-cell">
      <div class="ferry-live-estimate-label">Ops status</div>
      <div class="ferry-live-estimate-value"><?= h($opsLabel) ?></div>
      <?php if ($opsDetailParts !== []): ?>
        <div class="ferry-live-estimate-sub" style="margin-top:4px;"><?= h(implode(' · ', $opsDetailParts)) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
