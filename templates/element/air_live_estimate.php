<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$airRights = (array)($airRights ?? []);
$airScope = (array)($airScope ?? []);
$airContract = (array)($airContract ?? []);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';

$distanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ($airRights['air_distance_band'] ?? '')))));
$distanceBandLabel = match ($distanceBand) {
    'up_to_1500' => '1500 km eller mindre',
    'intra_eu_over_1500' => 'Inden for EU over 1500 km',
    'other_1500_to_3500' => 'Oevrige flyvninger mellem 1500 og 3500 km',
    'other_over_3500' => 'Oevrige flyvninger over 3500 km',
    default => 'Afventer distancekategori',
};
$thresholdHours = (int)($form['air_delay_threshold_hours'] ?? ($airScope['air_delay_threshold_hours'] ?? 0));
$thresholdLabel = $thresholdHours > 0 ? ($thresholdHours . '+ timer') : 'Afventer';
$flightDistanceKm = trim((string)($form['flight_distance_km'] ?? ($airScope['flight_distance_km'] ?? '')));
$gateCare = !empty($airRights['gate_air_care']);
$gateReroute = !empty($airRights['gate_air_reroute_refund']) || !empty($airRights['gate_air_delay_refund_5h']);
$gateComp = !empty($airRights['gate_air_compensation']);
$claimParty = (string)($airContract['primary_claim_party_name'] ?? ($airContract['primary_claim_party'] ?? 'manual_review'));

$perPassengerBase = match ($distanceBand) {
    'up_to_1500' => 250.0,
    'intra_eu_over_1500', 'other_1500_to_3500' => 400.0,
    'other_over_3500' => 600.0,
    default => 250.0,
};
$reductionThreshold = match ($distanceBand) {
    'up_to_1500' => 120,
    'intra_eu_over_1500', 'other_1500_to_3500' => 180,
    'other_over_3500' => 240,
    default => 120,
};
$rerouteArrivalDelayMinutes = is_numeric($form['reroute_arrival_delay_minutes'] ?? null)
    ? (int)$form['reroute_arrival_delay_minutes']
    : 0;
$reductionPct = ($gateComp && $rerouteArrivalDelayMinutes > 0 && $rerouteArrivalDelayMinutes <= $reductionThreshold) ? 50 : 0;
$perPassengerAmount = $gateComp ? round($perPassengerBase * ($reductionPct > 0 ? 0.5 : 1.0), 2) : 0.0;

$passengerCount = 1;
if (is_numeric($form['passenger_count'] ?? null) && (int)$form['passenger_count'] > 0) {
    $passengerCount = (int)$form['passenger_count'];
} elseif (!empty($meta['_passengers_auto']) && is_array($meta['_passengers_auto'])) {
    $passengerCount = max(1, count((array)$meta['_passengers_auto']));
}
$totalAmount = round($perPassengerAmount * $passengerCount, 2);

$statusText = $gateComp
    ? 'Kompensation mulig'
    : ($gateReroute ? 'Article 8 / refund vurderes' : 'Afventer flere svar');
$nextText = $isCompleted
    ? 'Efter haendelsen kan du gaa direkte til resultat og sagsoprettelse.'
    : ($isOngoing
        ? 'Udfyld kun det, der er relevant nu. Resultatet opdateres loebende.'
        : 'Du kan fortsaette med ombooking/care og stadig se estimatet loebende.');
?>

<style>
  .air-live-estimate { margin-top:10px; padding:12px; border:1px solid #dbeafe; border-radius:8px; background:#f8fbff; }
  .air-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .air-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; }
  .air-live-estimate-amount { font-size:28px; font-weight:800; line-height:1; color:#0f172a; }
  .air-live-estimate-sub { color:#475569; font-size:12px; }
  .air-live-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .air-live-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .air-live-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .air-live-estimate-value { margin-top:4px; font-weight:700; color:#1e293b; }
</style>

<div class="air-live-estimate">
  <div class="air-live-estimate-head">
    <div>
      <div><strong>Live air-estimat</strong></div>
      <div class="air-live-estimate-sub"><?= h($nextText) ?></div>
    </div>
    <div class="air-live-estimate-status"><?= h($statusText) ?></div>
  </div>

  <div style="margin-top:10px;">
    <div class="air-live-estimate-amount">
      <?= $gateComp ? h(number_format($totalAmount, 2, '.', ',')) . ' EUR' : 'Afventer' ?>
    </div>
    <div class="air-live-estimate-sub">
      <?= $gateComp
          ? ('Foreloebigt kompensationsestimat' . ($passengerCount > 1 ? ' for ' . $passengerCount . ' passagerer' : ' pr. sag'))
          : 'Kompensationsbeloebet bliver vist, saa snart gate og distancekategori er klare.' ?>
      <?php if ($gateComp && $reductionPct > 0): ?>
        Reduktion paa 50% er medregnet ud fra nuvaerende reroute-ankomst.
      <?php endif; ?>
    </div>
  </div>

  <div class="air-live-estimate-grid">
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Distancekategori</div>
      <div class="air-live-estimate-value"><?= h($distanceBandLabel) ?></div>
    </div>
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Art. 6 threshold</div>
      <div class="air-live-estimate-value"><?= h($thresholdLabel) ?></div>
    </div>
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Care</div>
      <div class="air-live-estimate-value"><?= $gateCare ? 'Aktiv' : 'Ikke aktiv endnu' ?></div>
    </div>
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Refund / ombooking</div>
      <div class="air-live-estimate-value"><?= $gateReroute ? 'Aktiv' : 'Ikke aktiv endnu' ?></div>
    </div>
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Claim-kanal</div>
      <div class="air-live-estimate-value"><?= h($claimParty !== '' ? $claimParty : 'manual_review') ?></div>
    </div>
    <div class="air-live-estimate-cell">
      <div class="air-live-estimate-label">Flydistance</div>
      <div class="air-live-estimate-value"><?= h($flightDistanceKm !== '' ? ($flightDistanceKm . ' km') : 'Ikke afledt endnu') ?></div>
    </div>
  </div>
</div>
