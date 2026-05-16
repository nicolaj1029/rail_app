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

$normalizePrice = static function (string $value): float {
    $value = trim($value);
    if ($value === '') {
        return 0.0;
    }
    $value = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';
    if ($value === '') {
        return 0.0;
    }
    $lastComma = strrpos($value, ',');
    $lastDot = strrpos($value, '.');
    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif ($lastComma !== false) {
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? (float)$value : 0.0;
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
$missedConnectionSuspected = !empty($incidentSeed['missed_connection_suspected']);
$strandingContext = strtolower(trim((string)($form['rail_stranding_context'] ?? 'no')));
$strandingLabel = match ($strandingContext) {
    'station' => 'Strandet paa station',
    'track' => 'Strandet i tog / paa spor',
    default => 'Ikke strandet',
};
$art19BandLabel = $arrivalDelay !== null && $arrivalDelay >= 120 ? '50%' : '25%';
$art19BandPct = $arrivalDelay !== null && $arrivalDelay >= 120 ? 50 : 25;
$priceRaw = trim((string)($form['price'] ?? ($journey['ticketPrice']['value'] ?? ($meta['_auto']['price']['value'] ?? ''))));
$ticketPriceAmount = $normalizePrice($priceRaw);
$priceCurrency = strtoupper(trim((string)($form['price_currency'] ?? ($journey['ticketPrice']['currency'] ?? ($meta['_auto']['price_currency']['value'] ?? 'EUR')))));
if ($priceCurrency === '') {
    $priceCurrency = 'EUR';
}
$railPriceInputMode = strtolower(trim((string)($form['rail_price_input_mode'] ?? '')));
if (!in_array($railPriceInputMode, ['exact', 'estimate', 'unknown'], true)) {
    $railPriceInputMode = $ticketPriceAmount > 0 ? 'exact' : 'unknown';
}
$ticketPriceKnown = $railPriceInputMode !== 'unknown' && $ticketPriceAmount > 0;
$ticketPriceIsEstimate = $railPriceInputMode === 'estimate';
$extraordinary = strtolower(trim((string)($form['operatorExceptionalCircumstances'] ?? ($form['extraordinary_circumstances'] ?? '')))) === 'yes';
$estimatedCompensationAmount = ($seedArt19 && !$extraordinary && $ticketPriceKnown)
    ? round($ticketPriceAmount * ($art19BandPct / 100), 2)
    : null;
$statusText = $seedArt19
    ? ($extraordinary ? 'Kompensation blokeret' : 'Foreloebigt kompensationsniveau')
    : (($seedArt18 || $seedArt20) ? 'Omlaegning / assistance aktiv' : ($ticketPriceKnown ? 'Afventer haendelse' : 'Afventer flere svar'));
$leadText = $isCompleted
    ? 'Resultatet bygger paa den valgte afgang og de afsluttede rail-svar.'
    : ($isOngoing
        ? 'Udfyld kun det, der er relevant nu. Rail-estimatet opdateres loebende.'
        : 'Panelet opdateres, naar afgang, haendelse og billetpris er kendt.');
$amountText = $extraordinary && $seedArt19
    ? 'Blokeret'
    : ($estimatedCompensationAmount !== null
        ? number_format($estimatedCompensationAmount, 2, '.', ',') . ' ' . $priceCurrency
        : ($seedArt19 ? ($art19BandLabel . ' af billetpris') : 'Afventer'));
$amountSummary = $extraordinary && $seedArt19
    ? 'Kompensationssporet er foreloebigt blokeret af force majeure.'
    : ($estimatedCompensationAmount !== null
        ? ('Foreloebigt Art. 19-estimat ud fra billetpris og ankomstforsinkelse.' . ($ticketPriceIsEstimate ? ' Beloebet bygger paa et ca. estimat fra TRIN 2.' : ''))
        : ($seedArt19
            ? 'Kompensationen ser mulig ud, men billetpris mangler endnu. Registrer prisen i TRIN 2 eller bekraeft den senere i backend.'
            : (($seedArt18 || $seedArt20)
                ? 'Kompensationen afventer stadig 60+ minutter, men assistance eller omlaegning / refund kan allerede vaere relevante.'
                : ($ticketPriceKnown
                    ? 'Billetpris er registreret. Kompensationen beregnes, naar haendelsen er afklaret i TRIN 5.'
                    : 'Rail-panelet afventer stadig rail-spoergsmaalene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.'))));
$thresholdLabel = match (true) {
    $seedIncidentType === 'cancellation' => 'Ikke relevant ved aflysning',
    $seedArt19 || ($arrivalDelay !== null && $arrivalDelay >= 60) => 'Aktiveret',
    $arrivalDelay !== null => 'Under 60 min',
    default => 'Afventer',
};
$opsStatus = trim((string)($opsEvidence['status'] ?? ($selectedDeparture['status'] ?? '')));
$opsSource = strtoupper(trim((string)($opsEvidence['source'] ?? ($selectedDeparture['source'] ?? ''))));
$opsScore = isset($opsEvidence['evidence_score']) ? (int)$opsEvidence['evidence_score'] : null;
$opsConfidence = isset($selectedDeparture['confidence']) || isset($opsEvidence['confidence'])
    ? number_format((float)($selectedDeparture['confidence'] ?? $opsEvidence['confidence']), 2, '.', '')
    : '';
$opsLabel = $opsStatus !== '' ? $statusLabel($opsStatus) : ($opsSource !== '' ? 'Ops data klar' : 'Ingen ops data');
$opsDetailParts = array_filter([
    $opsSource !== '' ? $opsSource : null,
    $opsScore !== null && $opsScore > 0 ? ('score ' . $opsScore) : null,
    $opsConfidence !== '' ? ('confidence ' . $opsConfidence) : null,
]);
?>

<style>
  .rail-live-estimate { margin-top:10px; padding:12px; border:1px solid #c7d2fe; border-radius:8px; background:#f8faff; }
  .rail-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .rail-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:700; }
  .rail-live-estimate-amount { font-size:28px; font-weight:800; line-height:1; color:#0f172a; }
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
  data-ticket-price="<?= h(number_format($ticketPriceAmount, 2, '.', '')) ?>"
  data-currency="<?= h($priceCurrency) ?>"
  data-price-known="<?= $ticketPriceKnown ? '1' : '0' ?>"
  data-price-estimate="<?= $ticketPriceIsEstimate ? '1' : '0' ?>"
>
  <div class="rail-live-estimate-head">
    <div>
      <div><strong>Live rail-estimat</strong></div>
      <div class="rail-live-estimate-sub"><?= h($leadText) ?></div>
    </div>
    <div class="rail-live-estimate-status" data-rail-live-status><?= h($statusText) ?></div>
  </div>

  <div style="margin-top:10px;">
    <div class="rail-live-estimate-amount" data-rail-live-amount><?= h($amountText) ?></div>
    <div class="rail-live-estimate-sub" data-rail-live-note data-rail-live-summary>
      <?= h($amountSummary) ?>
      <?php if ($missedConnectionSuspected): ?>
        <?= h(' Systemet har samtidig fundet en mulig misset forbindelse, som brugeren skal gennemgaa i incident.') ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="rail-live-estimate-sub" style="margin-top:8px;"><?= h($routeLabel) ?></div>
  <div class="rail-live-estimate-sub" style="margin-top:4px;"><?= h($serviceLabel) ?><?= $operatorName !== '' ? (' | ' . h($operatorName)) : '' ?></div>

  <div class="rail-live-estimate-grid">
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Haendelse</div>
      <div class="rail-live-estimate-value" data-rail-live-incident><?= h($incidentTypeLabel($seedIncidentType)) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Kompensation</div>
      <div class="rail-live-estimate-value" data-rail-live-art19><?= h($extraordinary && $seedArt19 ? 'Blokeret af force majeure' : ($seedArt19 ? ($art19BandLabel . ' af billetpris') : 'Ikke aktiv endnu')) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">60 min threshold</div>
      <div class="rail-live-estimate-value" data-rail-live-threshold><?= h($thresholdLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Ankomstforsinkelse</div>
      <div class="rail-live-estimate-value" data-rail-live-arrival-delay><?= h($arrivalDelay !== null ? (($arrivalDelay > 0 ? '+' : '') . $arrivalDelay . ' min') : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Omlaegning / refund</div>
      <div class="rail-live-estimate-value" data-rail-live-art18><?= h($seedArt18 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Assistance</div>
      <div class="rail-live-estimate-value" data-rail-live-art20><?= h($seedArt20 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
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
      <div class="rail-live-estimate-label">Ops status</div>
      <div class="rail-live-estimate-value"><?= h($opsLabel) ?></div>
      <?php if ($opsDetailParts !== []): ?>
        <div class="rail-live-estimate-sub" style="margin-top:4px;"><?= h(implode(' | ', $opsDetailParts)) ?></div>
      <?php endif; ?>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Planlagt</div>
      <div class="rail-live-estimate-value"><?= h($fmtTimeRange($plannedDepartureAt, $plannedArrivalAt)) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Forventet</div>
      <div class="rail-live-estimate-value"><?= h(($estimatedDepartureAt !== '' || $estimatedArrivalAt !== '') ? $fmtTimeRange($estimatedDepartureAt, $estimatedArrivalAt) : 'Afventer') ?></div>
    </div>
  </div>
</div>
