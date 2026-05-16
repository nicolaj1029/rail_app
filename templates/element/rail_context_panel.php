<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];

$multimodal = (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? 'rail'))));
if ($transportMode !== 'rail') {
    return;
}

$selectedRailDeparture = (array)($meta['rail_selected_departure'] ?? []);
$railContractSeed = (array)($meta['rail_contract_structure_seed'] ?? []);
$railProblemAnchor = (array)($meta['rail_problem_anchor'] ?? []);
$railCurrentLocationAnchor = (array)($meta['rail_current_location_anchor'] ?? []);
$railIncidentSeed = (array)($meta['rail_incident_seed'] ?? []);
$railContractOptions = (array)($meta['contract_options'] ?? []);

$railJourneyRoute = trim((string)(
    ($selectedRailDeparture['origin_station_name'] ?? '')
    && ($selectedRailDeparture['destination_station_name'] ?? '')
        ? (($selectedRailDeparture['origin_station_name'] ?? '') . ' -> ' . ($selectedRailDeparture['destination_station_name'] ?? ''))
        : (($form['dep_station'] ?? '') && ($form['arr_station'] ?? '')
            ? (($form['dep_station'] ?? '') . ' -> ' . ($form['arr_station'] ?? ''))
            : '')
));
$railJourneyService = trim((string)(($selectedRailDeparture['line'] ?? '') ?: ($selectedRailDeparture['train_number'] ?? '')));
$railJourneyOperatorNames = array_values(array_filter(array_map(
    static fn($value): string => trim((string)$value),
    (array)($selectedRailDeparture['operator_names'] ?? ($selectedRailDeparture['raw']['operator_names'] ?? []))
)));
if ($railJourneyOperatorNames === []) {
    $singleOperator = trim((string)(($selectedRailDeparture['operator_name'] ?? '') ?: ($form['operator'] ?? '')));
    if ($singleOperator !== '') {
        $railJourneyOperatorNames = [$singleOperator];
    }
}

$railContractModel = strtolower(trim((string)($form['contract_model'] ?? ($railContractSeed['effective_contract_model'] ?? ''))));
$railContractModelLabel = match ($railContractModel) {
    'through' => 'Gennemgaaende billet / en kontrakt',
    'separate' => 'Saerskilte kontrakter',
    default => (($railContractSeed['auto_label'] ?? '') !== '' ? (string)$railContractSeed['auto_label'] : 'Ikke afklaret endnu'),
};
$railSellerChannelLabel = match ((string)($form['seller_channel'] ?? ($railContractSeed['seller_channel'] ?? ''))) {
    'operator' => 'Jernbaneoperatoer',
    'retailer' => 'Rejsebureau / billetudsteder',
    default => 'Ikke besvaret endnu',
};
$railSameTransactionLabel = match ((string)($form['same_transaction'] ?? ($railContractSeed['same_transaction'] ?? ''))) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke besvaret endnu',
};
$railDisclosureLabel = match ((string)($form['through_ticket_disclosure'] ?? ($railContractSeed['through_ticket_disclosure'] ?? ''))) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke besvaret endnu',
};
$railSeparateNoticeLabel = match ((string)($form['separate_contract_notice'] ?? ($railContractSeed['separate_contract_notice'] ?? ''))) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke besvaret endnu',
};

$problemContractId = trim((string)($form['problem_contract_id'] ?? ($railContractSeed['problem_contract_id'] ?? '')));
$railProblemContractLabel = '';
if ($problemContractId !== '' && isset($railContractOptions[$problemContractId]) && is_array($railContractOptions[$problemContractId])) {
    $option = (array)$railContractOptions[$problemContractId];
    $railProblemContractLabel = trim((string)($option['label'] ?? ''));
}

$railProblemAnchorType = strtolower(trim((string)($railProblemAnchor['type'] ?? '')));
$railProblemAnchorStation = trim((string)($railProblemAnchor['station_name'] ?? ''));
$railProblemAnchorLabel = trim((string)($railProblemAnchor['label'] ?? ''));
$railProblemAnchorSummary = match ($railProblemAnchorType) {
    'before_departure' => ($railProblemAnchorStation !== '' ? ('Problem foer afgang fra ' . $railProblemAnchorStation) : 'Problem foer afgang'),
    'transfer' => ($railProblemAnchorLabel !== '' ? $railProblemAnchorLabel : ($railProblemAnchorStation !== '' ? ('Problem ved skift i ' . $railProblemAnchorStation) : 'Problem ved skift')),
    'en_route' => 'Problem senere paa den valgte kontrakt',
    default => '',
};

$strandedCurrentStation = trim((string)($form['stranded_current_station'] ?? ''));
if ($strandedCurrentStation === 'other') {
    $strandedCurrentStation = trim((string)($form['stranded_current_station_other'] ?? ''));
}
$railStrandedStation = $strandedCurrentStation !== '' ? $strandedCurrentStation : trim((string)($railCurrentLocationAnchor['station_name'] ?? ''));
$railStillThereLabel = match ((string)($form['rail_station_still_there'] ?? '')) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => '',
};
$railWhereEndedLabel = match ((string)($form['rail_station_where_ended'] ?? '')) {
    'same_station' => 'Jeg er stadig paa strandingsstationen',
    'next_station' => 'Jeg kom videre til naeste station / skiftestation',
    'destination' => 'Jeg naaede den endelige destination',
    'returned_origin' => 'Jeg vendte tilbage til afgangsstationen',
    'other_station' => 'Jeg afsluttede midlertidigt rejsen paa en anden station',
    default => '',
};
$railOutcomeStation = trim((string)($form['rail_station_end_station'] ?? ''));
if ($railOutcomeStation === 'other') {
    $railOutcomeStation = trim((string)($form['rail_station_end_station_other'] ?? ''));
}

$railSeedIncidentType = strtolower(trim((string)($railIncidentSeed['incident_type'] ?? 'unknown')));
$railSeedArrivalDelay = is_numeric($railIncidentSeed['arrival_delay_minutes_seed'] ?? null) ? (int)$railIncidentSeed['arrival_delay_minutes_seed'] : null;
$railSeedDepartureDelay = is_numeric($railIncidentSeed['departure_delay_minutes_seed'] ?? null) ? (int)$railIncidentSeed['departure_delay_minutes_seed'] : null;
$railSeedTransferCount = is_numeric($railIncidentSeed['transfer_count'] ?? null) ? (int)$railIncidentSeed['transfer_count'] : 0;
$railSeedMissedConnectionSuspected = !empty($railIncidentSeed['missed_connection_suspected']);
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
    return $fmtDateTime($dep) . ' -> ' . $fmtDateTime($arr);
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

$product = trim((string)($selectedRailDeparture['product'] ?? ''));
$trainNumber = trim((string)($selectedRailDeparture['train_number'] ?? ($selectedRailDeparture['line_name'] ?? '')));
$serviceName = trim((string)($selectedRailDeparture['service_name'] ?? ''));
$serviceLabel = trim(implode(' | ', array_filter([
    $trainNumber !== '' ? $trainNumber : null,
    $serviceName !== '' && $serviceName !== $trainNumber ? $serviceName : null,
    $product !== '' ? $product : null,
])));
if ($serviceLabel === '') {
    $serviceLabel = $railJourneyService !== '' ? $railJourneyService : 'Ukendt afgang';
}
$operatorName = $railJourneyOperatorNames !== [] ? implode(' | ', $railJourneyOperatorNames) : 'Ikke valgt endnu';
$plannedDepartureAt = (string)($selectedRailDeparture['planned_departure_at'] ?? '');
$plannedArrivalAt = (string)($selectedRailDeparture['planned_arrival_at'] ?? '');
$estimatedDepartureAt = (string)($selectedRailDeparture['estimated_departure_at'] ?? '');
$estimatedArrivalAt = (string)($selectedRailDeparture['estimated_arrival_at'] ?? '');
$arrivalDelay = is_numeric($selectedRailDeparture['arrival_delay_minutes'] ?? null)
    ? (int)$selectedRailDeparture['arrival_delay_minutes']
    : $railSeedArrivalDelay;
$departureDelay = is_numeric($selectedRailDeparture['departure_delay_minutes'] ?? null)
    ? (int)$selectedRailDeparture['departure_delay_minutes']
    : $railSeedDepartureDelay;
$seedArt18 = !empty($railIncidentSeed['gate_art18']) || ((string)($flags['gate_art18'] ?? '') === '1');
$seedArt19 = !empty($railIncidentSeed['gate_art19']) || ((string)($flags['gate_art19'] ?? '') === '1');
$seedArt20 = !empty($railIncidentSeed['gate_art20']) || ((string)($flags['gate_art20'] ?? '') === '1');
$source = strtoupper(trim((string)($selectedRailDeparture['source'] ?? (($meta['rail_operational_evidence']['source'] ?? 'mock')))));
$confidence = (float)($selectedRailDeparture['confidence'] ?? ($meta['rail_operational_evidence']['confidence'] ?? 0.0));
$strandingContext = strtolower(trim((string)($form['rail_stranding_context'] ?? 'no')));
$strandingLabel = match ($strandingContext) {
    'station' => 'Strandet paa station',
    'track' => 'Strandet i tog / paa spor',
    default => 'Ikke strandet',
};
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
<div class="rail-live-estimate">
  <div class="rail-live-estimate-head">
    <div>
      <div><strong>Rail-kontekstpanel</strong></div>
      <div class="rail-live-estimate-sub"><?= h($leadText) ?></div>
    </div>
    <div class="rail-live-estimate-status"><?= h($statusText) ?></div>
  </div>
  <div class="rail-live-estimate-title"><?= h($serviceLabel) ?></div>
  <div class="rail-live-estimate-sub">
    <?= h($railJourneyRoute !== '' ? $railJourneyRoute : 'Ikke valgt endnu') ?><?= $operatorName !== '' ? (' | ' . h($operatorName)) : '' ?>
  </div>
  <div class="rail-live-estimate-sub" style="margin-top:4px;">
    <?php
      $systemParts = [match ($railSeedIncidentType) {
          'delay' => 'mulig forsinkelse',
          'cancellation' => 'mulig aflysning',
          'partial_cancellation' => 'mulig delvis aflysning',
          'replacement_transport' => 'mulig erstatningstransport',
          default => 'ingen sikker haendelse endnu',
      }];
      if ($railSeedArrivalDelay !== null) { $systemParts[] = 'seedet ankomstforsinkelse: ' . $railSeedArrivalDelay . ' min'; }
      if ($railSeedTransferCount > 0) { $systemParts[] = $railSeedTransferCount . ' skift i rejsen'; }
      if ($railSeedMissedConnectionSuspected) { $systemParts[] = 'mulig mistet forbindelse'; }
    ?>
    <?= h('Systemet har fundet: ' . implode(' | ', $systemParts) . '.') ?>
  </div>
  <div class="rail-live-estimate-grid">
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Haendelse seed</div>
      <div class="rail-live-estimate-value"><?= h($incidentTypeLabel($railSeedIncidentType)) ?></div>
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
      <div class="rail-live-estimate-value"><?= h($arrivalDelay !== null ? (($arrivalDelay > 0 ? '+' : '') . $arrivalDelay . ' min') : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Afgangsforsinkelse</div>
      <div class="rail-live-estimate-value"><?= h($departureDelay !== null ? (($departureDelay > 0 ? '+' : '') . $departureDelay . ' min') : 'Afventer') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Status</div>
      <div class="rail-live-estimate-value"><?= h($statusLabel((string)($selectedRailDeparture['status'] ?? 'unknown'))) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 12</div>
      <div class="rail-live-estimate-value"><?= h($railContractModelLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Problemsted</div>
      <div class="rail-live-estimate-value"><?= h($railProblemAnchorSummary !== '' ? $railProblemAnchorSummary : 'Ikke valgt endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Problemkontrakt</div>
      <div class="rail-live-estimate-value"><?= h($railProblemContractLabel !== '' ? $railProblemContractLabel : ($railContractModel === 'separate' ? 'Ikke valgt endnu' : 'Ikke relevant')) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Stranding</div>
      <div class="rail-live-estimate-value"><?= h($strandingLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Strandingsstation</div>
      <div class="rail-live-estimate-value"><?= h($railStrandedStation !== '' ? $railStrandedStation : 'Ikke strandet') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Nuvaerende / foreloebig station</div>
      <div class="rail-live-estimate-value"><?= h($railOutcomeStation !== '' ? $railOutcomeStation : 'Ikke oplyst endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Koebskanal</div>
      <div class="rail-live-estimate-value"><?= h($railSellerChannelLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Samme transaktion</div>
      <div class="rail-live-estimate-value"><?= h($railSameTransactionLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Billet-oplysning</div>
      <div class="rail-live-estimate-value"><?= h($railDisclosureLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Separate contracts notice</div>
      <div class="rail-live-estimate-value"><?= h($railSeparateNoticeLabel) ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 18</div>
      <div class="rail-live-estimate-value"><?= h($seedArt18 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 19</div>
      <div class="rail-live-estimate-value"><?= h($seedArt19 ? $art19BandLabel : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Art. 20</div>
      <div class="rail-live-estimate-value"><?= h($seedArt20 ? 'Aktiv' : 'Ikke aktiv endnu') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Forbindelser</div>
      <div class="rail-live-estimate-value"><?= h($railSeedTransferCount > 0 ? ($railSeedTransferCount . ' skift') : 'Direkte tog') ?></div>
    </div>
    <div class="rail-live-estimate-cell">
      <div class="rail-live-estimate-label">Kilde</div>
      <div class="rail-live-estimate-value"><?= h($source !== '' ? $source : 'Mock') ?></div>
      <div class="rail-live-estimate-sub" style="margin-top:4px;"><?= h($confidence > 0 ? ('confidence ' . number_format($confidence, 2, '.', '')) : 'confidence ukendt') ?></div>
    </div>
  </div>
</div>
