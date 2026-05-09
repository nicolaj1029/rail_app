<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];

$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$opsEvidence = (array)($meta['rail_operational_evidence'] ?? []);
$incidentSeed = (array)($meta['rail_incident_seed'] ?? []);
if (($incidentSeed['mode'] ?? '') !== 'rail') {
    $fallbackSeed = (array)($meta['incident_seed'] ?? []);
    if (($fallbackSeed['mode'] ?? '') === 'rail') {
        $incidentSeed = $fallbackSeed;
    }
}

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';

$fmtDateTime = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return 'Ukendt';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d.m.Y H:i', $ts);
};

$fmtTimeRange = static function (?string $dep, ?string $arr) use ($fmtDateTime): string {
    $depLabel = $fmtDateTime($dep);
    $arrLabel = $fmtDateTime($arr);

    return $depLabel . ' -> ' . $arrLabel;
};

$statusLabel = static function (?string $status): string {
    return match (strtolower(trim((string)$status))) {
        'planned' => 'Planlagt',
        'departed' => 'Afgaaet',
        'arrived' => 'Ankommet',
        'delayed' => 'Forsinket',
        'cancelled' => 'Aflyst',
        'partially_cancelled' => 'Delvist aflyst',
        'diverted' => 'Omlagt',
        'replacement_transport' => 'Erstatningstransport',
        default => 'Ukendt',
    };
};

$incidentTypeLabel = static function (?string $type): string {
    return match (strtolower(trim((string)$type))) {
        'delay' => 'Forsinkelse',
        'cancellation' => 'Aflysning',
        'missed_connection' => 'Mistet forbindelse',
        'partial_cancellation' => 'Delvis aflysning',
        'replacement_transport' => 'Erstatningstransport',
        default => 'Afventer',
    };
};

$product = trim((string)($selectedDeparture['product'] ?? ''));
$trainNumber = trim((string)($selectedDeparture['train_number'] ?? ($selectedDeparture['line_name'] ?? '')));
$serviceName = trim((string)($selectedDeparture['service_name'] ?? ''));
$operatorName = trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? '')));
$origin = trim((string)($selectedDeparture['origin_station_name'] ?? ($form['dep_station'] ?? '')));
$destination = trim((string)($selectedDeparture['destination_station_name'] ?? ($form['arr_station'] ?? '')));
$routeLabel = trim($origin . ($origin !== '' || $destination !== '' ? ' -> ' : '') . $destination, ' ->');
if ($routeLabel === '') {
    $routeLabel = 'Ikke valgt endnu';
}

$serviceLabel = trim(implode(' | ', array_filter([
    $trainNumber !== '' ? $trainNumber : null,
    $serviceName !== '' && $serviceName !== $trainNumber ? $serviceName : null,
    $product !== '' ? $product : null,
])));
if ($serviceLabel === '') {
    $serviceLabel = 'Ukendt afgang';
}

$plannedDepartureAt = (string)($selectedDeparture['planned_departure_at'] ?? '');
$plannedArrivalAt = (string)($selectedDeparture['planned_arrival_at'] ?? '');
$estimatedDepartureAt = (string)($selectedDeparture['estimated_departure_at'] ?? '');
$estimatedArrivalAt = (string)($selectedDeparture['estimated_arrival_at'] ?? '');
$departureDelay = is_numeric($selectedDeparture['departure_delay_minutes'] ?? null)
    ? (int)$selectedDeparture['departure_delay_minutes']
    : (is_numeric($incidentSeed['departure_delay_minutes'] ?? null) ? (int)$incidentSeed['departure_delay_minutes'] : null);
$arrivalDelay = is_numeric($selectedDeparture['arrival_delay_minutes'] ?? null)
    ? (int)$selectedDeparture['arrival_delay_minutes']
    : (is_numeric($incidentSeed['arrival_delay_minutes'] ?? null) ? (int)$incidentSeed['arrival_delay_minutes'] : null);
$seedIncidentType = strtolower(trim((string)($incidentSeed['incident_type'] ?? 'unknown')));
$seedArt18 = !empty($incidentSeed['gate_art18']) || ((string)($flags['gate_art18'] ?? '') === '1');
$seedArt19 = !empty($incidentSeed['gate_art19']) || ((string)($flags['gate_art19'] ?? '') === '1');
$seedArt20 = !empty($incidentSeed['gate_art20']) || ((string)($flags['gate_art20'] ?? '') === '1');
$transferCount = is_numeric($incidentSeed['transfer_count'] ?? null) ? (int)$incidentSeed['transfer_count'] : 0;
$hasConnections = !empty($incidentSeed['has_connections']) || $transferCount > 0;
$missedConnectionSuspected = !empty($incidentSeed['missed_connection_suspected']);
$strandingContext = strtolower(trim((string)($form['rail_stranding_context'] ?? 'no')));
$strandingLabel = match ($strandingContext) {
    'station' => 'Strandet paa station',
    'track' => 'Strandet i tog / paa spor',
    default => 'Ikke strandet',
};
$source = strtoupper(trim((string)($selectedDeparture['source'] ?? ($opsEvidence['source'] ?? 'mock'))));
$confidence = (float)($selectedDeparture['confidence'] ?? ($opsEvidence['confidence'] ?? 0.0));
$art19BandLabel = $arrivalDelay !== null && $arrivalDelay >= 120 ? '50%' : '25%';

$statusText = $seedArt19
    ? 'Art. 19 mulig | ' . $art19BandLabel
    : (($seedArt18 || $seedArt20) ? 'Art. 18/20 seedet' : 'Afventer rail-bekraeftelse');
$leadText = $isCompleted
    ? 'Resultatet bygger paa den valgte afgang og de afsluttede rail-svar.'
    : ($isOngoing
        ? 'Brug kun panelet som UX-seed. Rail-data og driftssignaler skal stadig bekraeftes i incident-spoergsmaalene.'
        : 'Panelet viser rail-seed fra valgt afgang. Brugeren skal stadig bekraefte haendelsen, foer juridisk output laases.');
?>

<style>
  .rail-live-estimate { margin-top:10px; padding:12px; border:1px solid #c7d2fe; border-radius:8px; background:#f8faff; }
  .rail-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .rail-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:700; }
  .rail-live-estimate-title { font-size:28px; font-weight:800; line-height:1; color:#0f172a; margin-top:10px; }
  .rail-live-estimate-sub { color:#475569; font-size:12px; }
  .rail-live-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .rail-live-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .rail-live-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .rail-live-estimate-value { margin-top:4px; font-weight:700; color:#0f172a; }
</style>

<div
  id="railLiveEstimate"
  class="rail-live-estimate"
  data-seed-art18="<?= $seedArt18 ? '1' : '0' ?>"
  data-seed-art19="<?= $seedArt19 ? '1' : '0' ?>"
  data-seed-art20="<?= $seedArt20 ? '1' : '0' ?>"
  data-seed-arrival-delay="<?= $arrivalDelay !== null ? h((string)$arrivalDelay) : '' ?>"
  data-seed-departure-delay="<?= $departureDelay !== null ? h((string)$departureDelay) : '' ?>"
  data-seed-incident-type="<?= h($seedIncidentType) ?>"
>
  <div class="rail-live-estimate-head">
    <div>
      <div><strong>Live rail-estimat</strong></div>
      <div class="rail-live-estimate-sub"><?= h($leadText) ?></div>
    </div>
    <div class="rail-live-estimate-status" data-rail-live-status><?= h($statusText) ?></div>
  </div>

  <div class="rail-live-estimate-title" data-rail-live-headline><?= h($serviceLabel) ?></div>
  <div class="rail-live-estimate-sub">
    <?= h($routeLabel) ?><?= $operatorName !== '' ? (' | ' . h($operatorName)) : '' ?>
  </div>
  <div class="rail-live-estimate-sub" data-rail-live-note style="margin-top:4px;">
    <?= h($seedArt19
        ? 'Foreloebig rail-vurdering peger paa kompensation ved ankomstforsinkelse paa mindst 60 minutter. Brugeren skal stadig bekraefte det endelige faktum.'
        : (($seedArt18 || $seedArt20)
            ? 'Rail-panelet peger paa refund/ombooking eller assistance, men endelig rettighed afhaenger stadig af de konkrete brugerbekraeftelser.'
            : 'Rail-panelet afventer stadig rail-spoergsmaalene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.')) ?>
    <?php if ($missedConnectionSuspected): ?>
      <?= h(' Systemet har samtidig fundet en mulig misset forbindelse, som brugeren skal gennemgaa i incident.') ?>
    <?php endif; ?>
  </div>

  <div class="rail-live-estimate-grid">
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Haendelse seed</div>
      <div class="rail-live-estimate-value" data-rail-live-incident><?= h($incidentTypeLabel($seedIncidentType)) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Planlagt</div>
      <div class="rail-live-estimate-value"><?= h($fmtTimeRange($plannedDepartureAt, $plannedArrivalAt)) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Forventet</div>
      <div class="rail-live-estimate-value"><?= h(($estimatedDepartureAt !== '' || $estimatedArrivalAt !== '') ? $fmtTimeRange($estimatedDepartureAt, $estimatedArrivalAt) : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Ankomstforsinkelse</div>
      <div class="rail-live-estimate-value" data-rail-live-arrival-delay><?= h($arrivalDelay !== null ? (($arrivalDelay > 0 ? '+' : '') . $arrivalDelay . ' min') : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Afgangsforsinkelse</div>
      <div class="rail-live-estimate-value" data-rail-live-departure-delay><?= h($departureDelay !== null ? (($departureDelay > 0 ? '+' : '') . $departureDelay . ' min') : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Status</div>
      <div class="rail-live-estimate-value"><?= h($statusLabel((string)($selectedDeparture['status'] ?? ($opsEvidence['status'] ?? 'unknown')))) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 18</div>
      <div class="rail-live-estimate-value" data-rail-live-art18><?= h($seedArt18 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 19</div>
      <div class="rail-live-estimate-value" data-rail-live-art19><?= h($seedArt19 ? $art19BandLabel : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 20</div>
      <div class="rail-live-estimate-value" data-rail-live-art20><?= h($seedArt20 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Forbindelser</div>
      <div class="rail-live-estimate-value"><?= h($hasConnections ? ($transferCount . ' skift') : 'Direkte tog') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Stranding</div>
      <div class="rail-live-estimate-value" data-rail-live-stranding><?= h($strandingLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Operator</div>
      <div class="rail-live-estimate-value"><?= h($operatorName !== '' ? $operatorName : 'Ikke valgt endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Kilde</div>
      <div class="rail-live-estimate-value"><?= h($source !== '' ? $source : 'Mock') ?></div>
      <div class="rail-live-estimate-sub" style="margin-top:4px;"><?= h($confidence > 0 ? ('confidence ' . number_format($confidence, 2, '.', '')) : 'confidence ukendt') ?></div>
    </div>
  </div>
</div>
