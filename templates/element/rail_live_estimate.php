<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$profile = is_array($profile ?? null) ? (array)$profile : [];

if (!is_array($profile['articles'] ?? null) && is_array($journey) && $journey !== []) {
    try {
        $profile = (new \App\Service\ExemptionProfileBuilder())->build($journey);
    } catch (\Throwable $e) {
        $profile = [];
    }
}

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
$showTechnicalDetails = trim((string)$this->request->getSession()->read('admin.auth_user')) !== '';

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
$flagArt18 = ((string)($flags['gate_art18'] ?? '') === '1');
$art19AllowedByProfile = ($profile['articles']['art19'] ?? true) !== false;
$art19BlockedByProfile = !$art19AllowedByProfile;
$flagArt19 = ((string)($flags['gate_art19'] ?? '') === '1') && !$art19BlockedByProfile;
$flagArt20 = ((string)($flags['gate_art20'] ?? '') === '1');
$missedConnectionSuspected = !empty($incidentSeed['missed_connection_suspected']);
$resolveStation = static function (array $values, string $key, string $otherKey = ''): string {
    $value = trim((string)($values[$key] ?? ''));
    if ($value === 'other' && $otherKey !== '') {
        $value = trim((string)($values[$otherKey] ?? ''));
    }
    if (in_array(strtolower($value), ['', 'other', 'unknown'], true)) {
        return '';
    }

    return $value;
};
$currentAnchor = is_array($meta['rail_current_location_anchor'] ?? null) ? (array)$meta['rail_current_location_anchor'] : [];
$problemAnchor = is_array($meta['rail_problem_anchor'] ?? null) ? (array)$meta['rail_problem_anchor'] : [];
$rawLivePath = strtolower(trim((string)($form['rail_live_path'] ?? '')));
$strandingContext = strtolower(trim((string)($form['rail_stranding_context'] ?? 'no')));
if (!in_array($strandingContext, ['station', 'track'], true)) {
    if (in_array($rawLivePath, ['station', 'track'], true)) {
        $strandingContext = $rawLivePath;
    } elseif (strtolower(trim((string)($form['is_stranded_trin5'] ?? 'no'))) === 'yes' || strtolower(trim((string)($form['stranded_location'] ?? ''))) === 'track') {
        $strandingContext = 'track';
    } elseif (strtolower(trim((string)($form['a20_station_stranded'] ?? 'no'))) === 'yes' || strtolower(trim((string)($form['stranded_location'] ?? ''))) === 'station') {
        $strandingContext = 'station';
    } else {
        $strandingContext = 'no';
    }
}
$strandingLabel = match ($strandingContext) {
    'station' => 'Strandet paa station',
    'track' => 'Strandet i tog / paa spor',
    default => 'Ikke strandet',
};
$resolvedStrandedCurrentStation = $resolveStation($form, 'stranded_current_station', 'stranded_current_station_other');
$resolvedTrackArrivalStation = $resolveStation($form, 'a20_arrival_station', 'a20_arrival_station_other');
$resolvedStationEndStation = '';
$railStationWhereEnded = strtolower(trim((string)($form['rail_station_where_ended'] ?? '')));
if ($railStationWhereEnded === 'other_station') {
    $resolvedStationEndStation = $resolveStation($form, 'rail_station_end_station', 'rail_station_end_station_other');
} elseif ($railStationWhereEnded === 'return_to_departure') {
    $resolvedStationEndStation = trim((string)($form['dep_station'] ?? ''));
} elseif ($railStationWhereEnded === 'final_destination') {
    $resolvedStationEndStation = trim((string)($form['arr_station'] ?? ''));
} elseif ($railStationWhereEnded === 'same_station') {
    $resolvedStationEndStation = $resolvedStrandedCurrentStation;
}
$stationStillThere = strtolower(trim((string)($form['rail_station_still_there'] ?? '')));
$liveCurrentStationCandidate = '';
if ($strandingContext === 'station') {
    $liveCurrentStationCandidate = $stationStillThere === 'no'
        ? ($resolvedStationEndStation !== '' ? $resolvedStationEndStation : $resolvedStrandedCurrentStation)
        : $resolvedStrandedCurrentStation;
} elseif ($strandingContext === 'track') {
    $liveCurrentStationCandidate = $resolvedTrackArrivalStation;
}
$currentStationLabel = '';
foreach ([
    $liveCurrentStationCandidate,
    $resolveStation($form, 'a18_from_station', 'a18_from_station_other'),
    $resolvedTrackArrivalStation,
    $resolvedStrandedCurrentStation,
    trim((string)($currentAnchor['station_name'] ?? '')),
    trim((string)($form['handoff_station'] ?? '')),
    trim((string)($form['missed_connection_station'] ?? '')),
    trim((string)($problemAnchor['station_name'] ?? '')),
] as $stationCandidate) {
    if ($stationCandidate !== '' && strtolower($stationCandidate) !== 'unknown') {
        $currentStationLabel = $stationCandidate;
        break;
    }
}
$problemStationLabel = trim((string)($problemAnchor['station_name'] ?? ''));
$liveChoiceRaw = trim((string)($form['remedyChoice'] ?? ($form['rail_live_plan_choice'] ?? '')));
$liveChoiceLabel = match ($liveChoiceRaw) {
    'refund_return' => 'Refund / tilbage',
    'reroute_soonest' => 'Videre hurtigst muligt',
    'reroute_later' => 'Videre senere',
    'no_real_choice' => 'Intet reelt valg',
    default => '',
};
$contextLinePrimary = array_values(array_filter([
    $currentStationLabel !== '' ? ('Aktuel station: ' . $currentStationLabel) : '',
    $strandingContext !== 'no' ? ('Situation: ' . $strandingLabel) : '',
]));
$contextLineSecondary = array_values(array_filter([
    ($problemStationLabel !== '' && $problemStationLabel !== $currentStationLabel) ? ('Problemsted: ' . $problemStationLabel) : '',
    $liveChoiceLabel !== '' ? ('Valg: ' . $liveChoiceLabel) : '',
]));
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
$showProvisionalAmount = $ticketPriceKnown || $flagArt19 || ($extraordinary && $flagArt19) || $art19BlockedByProfile;
$provisionalBandPct = $flagArt19 ? $art19BandPct : 25;
$estimatedCompensationAmount = (!$extraordinary && $ticketPriceKnown)
    ? round($ticketPriceAmount * ($provisionalBandPct / 100), 2)
    : null;
$statusText = $art19BlockedByProfile
    ? 'Kompensation blokeret'
    : ($flagArt19
        ? ($extraordinary ? 'Kompensation blokeret' : 'Foreloebigt kompensationsniveau')
        : (($flagArt18 || $flagArt20) ? 'Omlaegning / assistance aktiv' : ($ticketPriceKnown ? 'Afventer haendelse' : 'Afventer flere svar')));
$leadText = $isCompleted
    ? 'Resultatet bygger paa den valgte afgang og de afsluttede rail-svar.'
    : ($isOngoing
        ? 'Udfyld kun det, der er relevant nu. Rail-estimatet opdateres loebende.'
        : 'Panelet opdateres, naar afgang, haendelse og billetpris er kendt.');
$amountText = $art19BlockedByProfile
    ? 'Blokeret'
    : ($extraordinary && $flagArt19
        ? 'Blokeret'
        : ($estimatedCompensationAmount !== null
            ? number_format($estimatedCompensationAmount, 2, '.', ',') . ' ' . $priceCurrency
            : ($showProvisionalAmount ? ($provisionalBandPct . '% af billetpris') : 'Afventer')));
$amountSummary = $art19BlockedByProfile
    ? 'EU-kompensation (Art. 19) er undtaget for denne rejse efter den nationale matrix.'
    : ($extraordinary && $flagArt19
        ? 'Kompensationssporet er foreloebigt blokeret af force majeure.'
        : ($estimatedCompensationAmount !== null
            ? (($flagArt19
                ? 'Foreloebigt Art. 19-estimat ud fra billetpris og ankomstforsinkelse.'
                : 'Foreloebigt rail-estimat ud fra billetpris med 60+ minutter som standardantagelse.')
                . ($ticketPriceIsEstimate ? ' Beloebet bygger paa et ca. estimat fra TRIN 2.' : ''))
            : ($flagArt19
                ? 'Kompensationen ser mulig ud, men billetpris mangler endnu. Registrer prisen i TRIN 2 eller bekraeft den senere i backend.'
                : (($flagArt18 || $flagArt20)
                    ? 'Kompensationen afventer stadig 60+ minutter, men assistance eller omlaegning / refund kan allerede vaere relevante.'
                    : ($ticketPriceKnown
                        ? 'Billetpris er registreret. Kompensationen beregnes, naar haendelsen er afklaret i TRIN 5.'
                        : 'Rail-panelet afventer stadig rail-spoergsmaalene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.')))));
$statusTone = $art19BlockedByProfile
    ? 'red'
    : ($flagArt19
        ? ($extraordinary ? 'red' : 'green')
        : (($seedIncidentType === 'delay' && $arrivalDelay !== null && $arrivalDelay < 60) ? 'red' : 'gray'));
$remedyTone = $flagArt18 ? 'green' : 'gray';
$assistanceTone = $flagArt20 ? 'green' : 'gray';
$operatorTone = $operatorName !== '' ? 'green' : 'gray';
$panelTone = $statusTone;
$thresholdLabel = match (true) {
    $art19BlockedByProfile => 'Undtaget nationalt',
    $seedIncidentType === 'cancellation' => 'Ikke relevant ved aflysning',
    $flagArt19 || ($arrivalDelay !== null && $arrivalDelay >= 60) => 'Aktiveret',
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
$chipToneStyle = static function (string $tone): string {
    return match ($tone) {
        'green' => 'background:rgba(220,252,231,0.98);border-color:rgba(34,197,94,0.34);color:#166534;',
        'red' => 'background:rgba(254,226,226,0.98);border-color:rgba(239,68,68,0.34);color:#b91c1c;',
        default => 'background:rgba(248,250,252,0.96);border-color:rgba(148,163,184,0.30);color:#475569;',
    };
};
$cardToneStyle = static function (string $tone): string {
    return match ($tone) {
        'green' => 'background:rgba(240,253,244,0.98);border-color:rgba(34,197,94,0.28);',
        'red' => 'background:rgba(254,242,242,0.98);border-color:rgba(239,68,68,0.28);',
        default => 'background:rgba(248,250,252,0.96);border-color:rgba(148,163,184,0.22);',
    };
};
?>

<style>
  .rail-live-estimate { margin-top:10px; padding:12px; border:1px solid #c7d2fe; border-radius:8px; background:#f8faff; }
  .rail-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .rail-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; font-size:12px; font-weight:700; }
  .rail-live-estimate-amount { font-size:28px; font-weight:800; line-height:1; color:#0f172a; }
  .rail-live-estimate-sub { color:#475569; font-size:12px; }
  .rail-live-estimate-route { margin-top:8px; }
  .rail-live-estimate-context { margin-top:6px; color:#475569; font-size:12px; }
  .rail-live-estimate-amount-block { margin-top:10px; }
  .rail-live-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .rail-live-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .rail-live-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .rail-live-estimate-value { margin-top:4px; font-weight:700; color:#0f172a; }
  .rail-live-estimate-primary { margin-top:12px; }
  .rail-live-estimate-primary .rail-live-estimate-cell { background:#fff; border-color:#dbeafe; }
  .rail-live-estimate-details { margin-top:12px; border-top:1px solid #dbeafe; padding-top:10px; }
  .rail-live-estimate-details > summary { cursor:pointer; list-style:none; font-size:12px; font-weight:700; color:#334155; }
  .rail-live-estimate-details > summary::-webkit-details-marker { display:none; }
  .rail-live-estimate-details > summary::after { content:'Vis'; margin-left:8px; color:#64748b; font-weight:400; }
  .rail-live-estimate-details[open] > summary::after { content:'Skjul'; }
</style>

<div
  id="railLiveEstimate"
  class="rail-live-estimate tc6-live-estimate tc6-live-estimate--<?= h($panelTone) ?>"
  data-seed-art18="<?= $flagArt18 ? '1' : '0' ?>"
  data-seed-art19="<?= $flagArt19 ? '1' : '0' ?>"
  data-seed-art20="<?= $flagArt20 ? '1' : '0' ?>"
  data-seed-arrival-delay="<?= $arrivalDelay !== null ? h((string)$arrivalDelay) : '' ?>"
  data-seed-departure-delay="<?= $departureDelay !== null ? h((string)$departureDelay) : '' ?>"
  data-seed-incident-type="<?= h($seedIncidentType) ?>"
  data-ticket-price="<?= h(number_format($ticketPriceAmount, 2, '.', '')) ?>"
  data-currency="<?= h($priceCurrency) ?>"
  data-price-known="<?= $ticketPriceKnown ? '1' : '0' ?>"
  data-price-estimate="<?= $ticketPriceIsEstimate ? '1' : '0' ?>"
  data-art19-allowed="<?= $art19AllowedByProfile ? '1' : '0' ?>"
  data-live-tone="<?= h($panelTone) ?>"
>
  <div class="rail-live-estimate-head">
    <div>
      <div><strong>Live rail-estimat</strong></div>
    </div>
    <div class="rail-live-estimate-status tc6-live-chip tc6-live-chip--<?= h($statusTone) ?>" data-rail-live-status style="<?= h($chipToneStyle($statusTone)) ?>"><?= h($statusText) ?></div>
  </div>

  <?php if ($showProvisionalAmount): ?>
    <div class="rail-live-estimate-amount-block">
      <div class="rail-live-estimate-amount" data-rail-live-amount><?= h($amountText) ?></div>
    </div>
  <?php endif; ?>

  <div class="rail-live-estimate-sub rail-live-estimate-route"><?= h($routeLabel) ?></div>
  <?php if ($contextLinePrimary !== []): ?>
    <div class="rail-live-estimate-context"><?= h(implode(' | ', $contextLinePrimary)) ?></div>
  <?php endif; ?>
  <?php if ($contextLineSecondary !== []): ?>
    <div class="rail-live-estimate-context"><?= h(implode(' | ', $contextLineSecondary)) ?></div>
  <?php endif; ?>

  <div class="rail-live-estimate-grid rail-live-estimate-primary">
    <div class="rail-live-estimate-cell tc6-live-card tc6-live-card--<?= h($remedyTone) ?>" data-rail-live-card="remedy" style="<?= h($cardToneStyle($remedyTone)) ?>">
      <div class="rail-live-estimate-label">Refusion / ombooking</div>
      <div class="rail-live-estimate-value" data-rail-live-art18><?= h($flagArt18 ? 'Fuld daekning mulig' : 'Afventer flere svar') ?></div>
    </div>
    <div class="rail-live-estimate-cell tc6-live-card tc6-live-card--<?= h($assistanceTone) ?>" data-rail-live-card="assistance" style="<?= h($cardToneStyle($assistanceTone)) ?>">
      <div class="rail-live-estimate-label">Assistance</div>
      <div class="rail-live-estimate-value" data-rail-live-art20><?= h($flagArt20 ? 'Rimelige noedvendige udgifter kan daekkes' : 'Afventer flere svar') ?></div>
    </div>
    <div class="rail-live-estimate-cell tc6-live-card tc6-live-card--<?= h($operatorTone) ?>" data-rail-live-card="operator" style="<?= h($cardToneStyle($operatorTone)) ?>">
      <div class="rail-live-estimate-label">Operatoer</div>
      <div class="rail-live-estimate-value"><?= h($operatorName !== '' ? $operatorName : 'Afventer svar') ?></div>
    </div>
  </div>

  <?php if ($showTechnicalDetails): ?>
  <details class="rail-live-estimate-details">
    <summary>Tekniske detaljer</summary>
    <div class="rail-live-estimate-grid">
      <div class="rail-live-estimate-cell">
        <div class="rail-live-estimate-label">Haendelse</div>
        <div class="rail-live-estimate-value" data-rail-live-incident><?= h($incidentTypeLabel($seedIncidentType)) ?></div>
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
        <div class="rail-live-estimate-label">Stranding</div>
        <div class="rail-live-estimate-value" data-rail-live-stranding><?= h($strandingLabel) ?></div>
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
  </details>
  <?php endif; ?>
</div>
