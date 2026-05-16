<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$departureCandidates = $departureCandidates ?? [];
$selectedDeparture = $selectedDeparture ?? [];
$depStation = trim((string)($depStation ?? ''));
$arrStation = trim((string)($arrStation ?? ''));
$depDate = trim((string)($depDate ?? ''));
$isPreview = !empty($flowPreview);
$selectedDepartureId = trim((string)($selectedDeparture['id'] ?? ($meta['rail_selected_departure_id'] ?? ($form['selected_rail_departure_id'] ?? ''))));
$selectedTrainNumber = mb_strtolower(trim((string)($selectedDeparture['train_number'] ?? ($form['train_no'] ?? ''))), 'UTF-8');
$selectedOperatorName = mb_strtolower(trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ''))), 'UTF-8');
$sellerChannel = trim((string)($form['seller_channel'] ?? ''));
$sameTransaction = trim((string)($form['same_transaction'] ?? ''));
$throughTicketDisclosure = trim((string)($form['through_ticket_disclosure'] ?? ''));
$separateContractNotice = trim((string)($form['separate_contract_notice'] ?? ''));
$contractOptions = (array)($contractOptions ?? ($meta['contract_options'] ?? []));
$railContractWarning = trim((string)($railContractWarning ?? ''));
$railExemptionWarning = trim((string)($railExemptionWarning ?? ''));
$railContractSeed = (array)($meta['rail_contract_structure_seed'] ?? []);
$prevAction = 'entitlements';
$stepHeading = 'TRIN 3';
$stepTitle = 'Vaelg afgang';
foreach (($flowSteps ?? []) as $step) {
    if ((string)($step['action'] ?? '') !== 'railDepartureSelect') {
        continue;
    }
    $stepNum = $step['ui_num'] ?? $step['num'] ?? 3;
    $stepHeading = 'TRIN ' . (string)$stepNum;
    $stepTitle = (string)($step['title'] ?? $stepTitle);
    break;
}

$formatDateTime = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return 'Ukendt';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y H:i', $timestamp);
};

$formatTimeRange = static function (array $item) use ($formatDateTime): string {
    $dep = $formatDateTime((string)($item['planned_departure_at'] ?? ''));
    $arr = $formatDateTime((string)($item['planned_arrival_at'] ?? ''));

    return $dep . ' -> ' . $arr;
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

$extractTransferCount = static function (array $item): int {
    $raw = (array)($item['raw'] ?? []);
    $transferCount = $raw['transfer_count'] ?? null;
    if (is_numeric($transferCount)) {
        return max(0, (int)$transferCount);
    }
    $railLegCount = $raw['rail_leg_count'] ?? null;
    if (is_numeric($railLegCount)) {
        return max(0, (int)$railLegCount - 1);
    }

    return 0;
};

$extractViaStations = static function (array $item, int $limit = 3): array {
    $origin = mb_strtolower(trim((string)($item['origin_station_name'] ?? '')), 'UTF-8');
    $destination = mb_strtolower(trim((string)($item['destination_station_name'] ?? '')), 'UTF-8');
    $callingPoints = (array)($item['calling_points'] ?? []);
    $via = [];
    $seen = [];

    foreach ($callingPoints as $point) {
        if (!is_array($point)) {
            continue;
        }
        $name = trim((string)($point['station_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $nameKey = mb_strtolower($name, 'UTF-8');
        if ($nameKey === $origin || $nameKey === $destination || isset($seen[$nameKey])) {
            continue;
        }
        $seen[$nameKey] = true;
        $via[] = $name;
        if (count($via) >= $limit) {
            break;
        }
    }

    return $via;
};

$extractTransferStations = static function (array $item, int $limit = 3): array {
    $raw = (array)($item['raw'] ?? []);
    $stations = [];
    $seen = [];

    foreach ((array)($raw['transfer_station_names'] ?? []) as $name) {
        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }
        $nameKey = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$nameKey])) {
            continue;
        }
        $seen[$nameKey] = true;
        $stations[] = $name;
        if (count($stations) >= $limit) {
            break;
        }
    }

    return $stations;
};

$extractContractStops = static function (array $contractOption): array {
    $segments = (array)($contractOption['segments'] ?? []);
    $stops = [];
    $seen = [];
    foreach ($segments as $segment) {
        if (!is_array($segment)) {
            continue;
        }
        foreach ([
            [
                'name' => trim((string)($segment['from_name'] ?? $segment['from'] ?? '')),
                'code' => trim((string)($segment['from_code'] ?? '')),
            ],
            [
                'name' => trim((string)($segment['to_name'] ?? $segment['to'] ?? '')),
                'code' => trim((string)($segment['to_code'] ?? '')),
            ],
        ] as $stop) {
            if ($stop['name'] === '') {
                continue;
            }
            $key = mb_strtolower($stop['name'], 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $stops[] = $stop;
        }
    }

    return $stops;
};

$selectedDepartureHasConnections = $selectedDeparture !== [] && $extractTransferCount($selectedDeparture) > 0;
$uiDefaultCandidate = (!$selectedDepartureHasConnections && $selectedDepartureId === '' && !empty($departureCandidates)) ? (array)$departureCandidates[0] : [];
if ($uiDefaultCandidate !== [] && $extractTransferCount($uiDefaultCandidate) > 0) {
    $selectedDepartureHasConnections = true;
}
$hasAnyConnectionCandidates = false;
foreach ($departureCandidates as $candidate) {
    if ($extractTransferCount((array)$candidate) > 0) {
        $hasAnyConnectionCandidates = true;
        break;
    }
}
$contractConfidence = strtolower(trim((string)($railContractSeed['confidence'] ?? '')));
if (!in_array($contractConfidence, ['high', 'medium', 'low'], true)) {
    $contractConfidence = $selectedDepartureHasConnections ? 'low' : 'high';
}
$autoContractModel = strtolower(trim((string)($railContractSeed['auto_contract_model'] ?? '')));
$effectiveContractModel = strtolower(trim((string)($railContractSeed['effective_contract_model'] ?? '')));
if (!in_array($autoContractModel, ['through', 'separate'], true)) {
    $autoContractModel = $selectedDepartureHasConnections ? '' : 'through';
}
if (!in_array($effectiveContractModel, ['through', 'separate'], true)) {
    $effectiveContractModel = $autoContractModel;
}
$autoStop = !empty($railContractSeed['auto_stop']);
$needsFollowup = !empty($railContractSeed['needs_followup']);
$liableBasis = trim((string)($railContractSeed['liable_basis'] ?? 'manual_review'));
$operatorNamesSeed = array_values(array_filter(array_map(
    static fn($value): string => trim((string)$value),
    (array)($railContractSeed['operator_names'] ?? [])
)));
$operatorCountSeed = (int)($railContractSeed['operator_count'] ?? count($operatorNamesSeed));
$extractOperatorHints = static function (array $item): array {
    return array_values(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        (array)(($item['raw']['operator_hints'] ?? []))
    ), static fn(string $value): bool => $value !== ''));
};
$selectedFallbackOperatorHints = $extractOperatorHints((array)($selectedDeparture !== [] ? $selectedDeparture : $uiDefaultCandidate));
$selectedProblemContractId = trim((string)($form['problem_contract_id'] ?? ''));
$selectedProblemAnchorChoice = trim((string)($form['rail_problem_anchor_choice'] ?? 'unknown'));
$requiresManualArt12 = $selectedDepartureHasConnections && ($needsFollowup || ($contractConfidence === 'low' && !$autoStop));
$art12NeedsAttention = $railContractWarning !== '' || $requiresManualArt12 || ($effectiveContractModel === 'separate' && $selectedProblemContractId === '');
$autoContractLabel = trim((string)($railContractSeed['auto_label'] ?? ''));
if ($autoContractLabel === '') {
    $autoContractLabel = match ($effectiveContractModel !== '' ? $effectiveContractModel : $autoContractModel) {
    'through' => 'Gennemgaaende billet / en kontrakt',
    'separate' => 'Separate kontrakter',
    default => 'Uklar - kraever brugerbekraeftelse',
    };
}
$autoReason = trim((string)($railContractSeed['auto_reason'] ?? ''));
$topologyHint = trim((string)($autoReason !== '' ? $autoReason : ''));
$manualArt12Open = $requiresManualArt12 || $sellerChannel !== '' || $sameTransaction !== '' || $throughTicketDisclosure !== '' || $separateContractNotice !== '';
$selectedProblemAnchorStations = $selectedDepartureHasConnections ? $extractTransferStations((array)($selectedDeparture !== [] ? $selectedDeparture : $uiDefaultCandidate), 10) : [];
$problemAnchorStartStation = $depStation !== '' ? $depStation : trim((string)(($selectedDeparture !== [] ? $selectedDeparture : $uiDefaultCandidate)['origin_station_name'] ?? ''));
if ($effectiveContractModel === 'separate' && $selectedProblemContractId !== '' && !empty($contractOptions[$selectedProblemContractId])) {
    $selectedContractStops = $extractContractStops((array)$contractOptions[$selectedProblemContractId]);
    if (!empty($selectedContractStops)) {
        $problemAnchorStartStation = (string)($selectedContractStops[0]['name'] ?? $problemAnchorStartStation);
        $selectedProblemAnchorStations = array_map(
            static fn(array $stop): string => (string)($stop['name'] ?? ''),
            array_values(array_slice($selectedContractStops, 1, -1))
        );
        $selectedProblemAnchorStations = array_values(array_filter($selectedProblemAnchorStations, static fn(string $value): bool => trim($value) !== ''));
    }
}
?>

<style>
  .rail-step { max-width: 1040px; }
  .rail-card { padding:16px; border:1px solid #dbe3ea; background:#fff; border-radius:16px; box-shadow:0 8px 20px rgba(15, 23, 42, .04); }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .mt16 { margin-top:16px; }
  .small { font-size:12px; }
  .muted { color:#64748b; }
  .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#ecfeff; color:#0f766e; font-size:12px; font-weight:700; }
  .route-line { font-size:20px; font-weight:800; color:#0f172a; margin-top:8px; }
  .departure-list { display:grid; gap:10px; margin-top:12px; }
  .departure-option { position:relative; border:1px solid #dbe3ea; border-radius:14px; background:#fff; transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
  .departure-option:hover { border-color:#94a3b8; box-shadow:0 10px 18px rgba(15, 23, 42, .06); transform:translateY(-1px); }
  .departure-option.selected { border-color:#0f766e; box-shadow:0 0 0 2px rgba(15, 118, 110, .12); background:#f0fdfa; }
  .departure-option input[type=radio] { position:absolute; top:16px; left:16px; }
  .departure-option-label { display:block; cursor:pointer; padding:14px 16px 14px 46px; }
  .departure-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
  .departure-top > div:last-child { display:none; }
  .departure-journey-label { font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#0f766e; }
  .departure-route { font-size:20px; font-weight:800; color:#0f172a; margin-top:4px; }
  .departure-time { display:none; }
  .departure-first-train { margin-top:6px; font-size:14px; font-weight:700; color:#0f172a; }
  .departure-service { font-size:14px; font-weight:700; color:#0f172a; }
  .departure-operator { margin-top:2px; color:#334155; }
  .departure-badges { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .departure-badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; background:#f1f5f9; color:#0f172a; }
  .departure-source { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; background:#eef2ff; color:#4338ca; }
  .departure-option.selected .departure-source { background:#ccfbf1; color:#115e59; }
  .departure-meta { display:flex; gap:14px; flex-wrap:wrap; margin-top:10px; font-size:13px; color:#475569; }
  .departure-meta strong { color:#0f172a; }
  .departure-via { margin-top:10px; font-size:13px; color:#334155; }
  .departure-via strong { color:#0f172a; }
  .manual-box { border:1px dashed #cbd5e1; border-radius:14px; background:#f8fafc; padding:14px; }
  .manual-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .manual-grid label { display:block; font-weight:600; color:#0f172a; }
  .manual-grid input { display:block; width:100%; margin-top:6px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; }
  .step-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .button-secondary { display:inline-block; padding:12px 16px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#0f172a; font-weight:700; text-decoration:none; }
  .button-secondary:hover { background:#f8fafc; border-color:#94a3b8; }
  .button-primary { display:inline-block; padding:12px 16px; border:none; border-radius:12px; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
  .button-primary:hover { background:#1e293b; }
  .helper-note { border-left:4px solid #14b8a6; background:#f0fdfa; border-radius:10px; padding:10px 12px; color:#0f172a; }
  .contract-auto-box { border:1px solid #dbe3ea; border-radius:14px; background:#f8fafc; padding:14px; }
  .contract-auto-box.warn { border-color:#f59e0b; background:#fff7ed; }
  .contract-warning { border-left:4px solid #dc2626; background:#fef2f2; border-radius:10px; padding:10px 12px; color:#7f1d1d; }
  .contract-edit { margin-top:12px; padding:12px; border:1px dashed #cbd5e1; border-radius:12px; background:#fff; }
  .contract-edit label { display:block; margin-top:8px; font-weight:600; color:#0f172a; }
  .contract-edit select { display:block; width:100%; margin-top:6px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; }
  .contract-choice-list { display:grid; gap:10px; margin-top:10px; }
  .contract-choice-item { border:1px solid #dbe3ea; border-radius:12px; background:#fff; padding:12px; }
  .contract-choice-item label { display:flex; gap:10px; align-items:flex-start; margin:0; font-weight:600; cursor:pointer; }
  .contract-choice-item input[type=radio] { margin-top:3px; }
  .contract-choice-meta { margin-top:6px; color:#64748b; font-size:12px; }
  .toggle-link { display:inline-block; margin-top:12px; background:transparent; border:0; color:#0f766e; font-weight:700; text-decoration:underline; cursor:pointer; padding:0; }
  .ml8 { margin-left:8px; }
  @media (max-width: 820px) {
    .manual-grid { grid-template-columns: 1fr; }
    .departure-time { font-size:18px; }
  }
</style>

<div class="rail-step">
<h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
<div class="small muted">Vaelg den konkrete togafgang tidligt i rail-flowet. Rail-data bruges kun som UX-seed og forudfyldning, ikke som juridisk sandhed.</div>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="rail-card mt16">
  <?php if ($depStation !== '' || $arrStation !== ''): ?>
    <span class="pill">Rute</span>
    <div class="route-line"><?= h(trim($depStation . ' -> ' . $arrStation, ' ->')) ?></div>
  <?php endif; ?>
  <div class="mt12">
    <strong>Vaelg din afgang fra listen</strong>
    <div class="small muted mt8">Listen kommer fra HAFAS/transport.rest, hvis live-api er aktivt, ellers fra mock eller AI fallback. Vi bruger den til at prefill operator, tider og foreloebige rail-gates.</div>
    <?php if ($depDate !== ''): ?>
      <div class="small muted mt8">Dato: <?= h($depDate) ?></div>
    <?php endif; ?>
  </div>
  <?php if ($railExemptionWarning !== ''): ?>
    <div class="contract-warning mt12">
      <?= h($railExemptionWarning) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($departureCandidates)): ?>
    <div class="departure-list">
      <?php foreach ($departureCandidates as $idx => $item): ?>
        <?php
          $candidateId = (string)($item['id'] ?? ('candidate_' . $idx));
          $candidateTrainNumber = mb_strtolower(trim((string)($item['train_number'] ?? '')), 'UTF-8');
          $candidateOperator = mb_strtolower(trim((string)($item['operator_name'] ?? '')), 'UTF-8');
          $isChecked = $candidateId !== '' && $candidateId === $selectedDepartureId;
          if (!$isChecked && $selectedTrainNumber !== '' && $candidateTrainNumber !== '') {
            $sameTrain = $candidateTrainNumber === $selectedTrainNumber;
            $sameOperator = $selectedOperatorName === '' || $candidateOperator === '' || $candidateOperator === $selectedOperatorName;
            $isChecked = $sameTrain && $sameOperator;
          }
          if (!$isChecked && $selectedDepartureId === '' && $selectedTrainNumber === '' && $selectedOperatorName === '' && $idx === 0) {
            $isChecked = true;
          }
          $rowId = 'rail_departure_candidate_' . $idx;
          $primaryService = trim((string)($item['train_number'] ?? ($item['service_name'] ?? ($item['line_name'] ?? 'Ukendt afgang'))));
          $operatorNameRaw = trim((string)($item['operator_name'] ?? ''));
          $operatorHints = $extractOperatorHints((array)$item);
          $operatorName = $operatorNameRaw !== '' ? $operatorNameRaw : ($operatorHints !== [] ? 'Sandsynlige operatoerer (AI hint): ' . implode(' · ', $operatorHints) : 'Ukendt operator');
          $source = strtoupper(trim((string)($item['source'] ?? 'mock')));
          $estimatedDeparture = trim((string)($item['estimated_departure_at'] ?? ''));
          $estimatedArrival = trim((string)($item['estimated_arrival_at'] ?? ''));
          $arrivalDelayMinutes = $item['arrival_delay_minutes'] ?? null;
          $platformPlanned = trim((string)($item['platform_planned'] ?? ''));
          $platformActual = trim((string)($item['platform_actual'] ?? ''));
          $reason = trim((string)($item['disruption_reason_public'] ?? ''));
          $transferCount = $extractTransferCount((array)$item);
          $legCount = $transferCount + 1;
          $originName = trim((string)($item['origin_station_name'] ?? ''));
          $originCode = trim((string)($item['origin_station_code'] ?? ''));
          $destinationName = trim((string)($item['destination_station_name'] ?? ''));
          $destinationCode = trim((string)($item['destination_station_code'] ?? ''));
          $rawOperatorNames = array_values(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            (array)(($item['raw']['operator_names'] ?? []))
          )));
          if ($rawOperatorNames === [] && $operatorNameRaw !== '') {
            $rawOperatorNames = array_values(array_filter(array_map(
              static fn(string $value): string => trim($value),
              preg_split('/\s*[,;\/]\s*/u', $operatorNameRaw) ?: []
            )));
          }
          $operatorCount = count($rawOperatorNames);
          $journeyLabel = $transferCount > 0 ? 'Rejseforslag' : 'Direkte afgang';
          $viaStations = $extractViaStations((array)$item, 3);
          $transferStations = $extractTransferStations((array)$item, 10);
          $locationSummaryLabel = $transferCount > 0 && !empty($transferStations) ? 'Skift i' : 'Mellemstop';
          $locationSummaryItems = $transferCount > 0 && !empty($transferStations) ? $transferStations : $viaStations;
          $locationSummaryText = implode(' -> ', $locationSummaryItems);
        ?>
        <div
          class="departure-option<?= $isChecked ? ' selected' : '' ?>"
          data-transfer-count="<?= h((string)$transferCount) ?>"
          data-origin-name="<?= h($originName) ?>"
          data-origin-code="<?= h($originCode) ?>"
          data-destination-name="<?= h($destinationName) ?>"
          data-destination-code="<?= h($destinationCode) ?>"
          data-primary-service="<?= h($primaryService) ?>"
          data-operator-name="<?= h($operatorName) ?>"
          data-operator-count="<?= h((string)$operatorCount) ?>"
          data-operator-names="<?= h((string)json_encode(array_values($rawOperatorNames), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
          data-transfer-stations="<?= h((string)json_encode(array_values($transferStations), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        >
          <input id="<?= h($rowId) ?>" type="radio" name="selected_rail_departure_id" value="<?= h($candidateId) ?>" <?= $isChecked ? 'checked' : '' ?> />
          <label class="departure-option-label" for="<?= h($rowId) ?>">
            <div class="departure-top">
              <div>
                <div class="departure-journey-label"><?= h($journeyLabel) ?></div>
                <div class="departure-route"><?= h($originName) ?> -> <?= h($destinationName) ?></div>
                <div class="departure-first-train">Foerste tog: <?= h($primaryService) ?></div>
                <div class="departure-badges mt8">
                  <span class="departure-source"><?= h($source) ?></span>
                  <?php if ($transferCount > 0): ?>
                    <span class="departure-badge"><?= h((string)$transferCount) ?> skift</span>
                    <span class="departure-badge"><?= h((string)$legCount) ?> tog</span>
                  <?php else: ?>
                    <span class="departure-badge">Direkte</span>
                  <?php endif; ?>
                </div>
                <div class="departure-time"><?= h($primaryService) ?> -> <?= h($destinationName) ?></div>
                <div class="departure-service"><?= h((string)($item['service_name'] ?? ($item['line_name'] ?? ''))) ?></div>
                <div class="departure-operator"><?= h($operatorName) ?></div>
                <?php if ($operatorNameRaw === '' && $operatorHints !== []): ?>
                  <div class="small muted mt8">Lav confidence-hint. Bruges kun som UX-stoette og ikke som sikker Art. 12-dokumentation.</div>
                <?php endif; ?>
              </div>
              <div style="text-align:right;">
                <span class="departure-source"><?= h($source) ?></span>
              </div>
            </div>
            <div class="departure-meta">
              <span><strong>Planlagt:</strong> <?= h($formatTimeRange($item)) ?></span>
              <?php if ($estimatedDeparture !== '' || $estimatedArrival !== ''): ?>
                <span><strong>Forventet:</strong> <?= h($formatDateTime($estimatedDeparture)) ?> -> <?= h($formatDateTime($estimatedArrival)) ?></span>
              <?php endif; ?>
              <?php if ($arrivalDelayMinutes !== null && $arrivalDelayMinutes !== ''): ?>
                <span><strong>Forsinkelse ved ankomst:</strong> <?= h((string)$arrivalDelayMinutes) ?> min</span>
              <?php endif; ?>
              <span><strong>Status:</strong> <?= h($statusLabel((string)($item['status'] ?? ''))) ?></span>
              <?php if ($platformPlanned !== '' || $platformActual !== ''): ?>
                <span><strong>Peron:</strong> <?= h($platformActual !== '' ? $platformActual : $platformPlanned) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($locationSummaryText !== ''): ?>
              <div class="departure-via"><strong><?= h($locationSummaryLabel) ?>:</strong> <?= h($locationSummaryText) ?></div>
            <?php endif; ?>
            <?php if ($reason !== ''): ?>
              <div class="small muted mt8"><?= h($reason) ?></div>
            <?php endif; ?>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="helper-note mt12">
      Vi kunne ikke hente en brugbar afgangsliste for denne rute og dato lige nu. Brug manuel fallback nedenfor, saa rail-flowet stadig kan fortsaette.
    </div>
  <?php endif; ?>
</div>

<?php if ($hasAnyConnectionCandidates): ?>
  <input type="hidden" name="seller_channel" value="" />
  <input type="hidden" name="same_transaction" value="" />
  <input type="hidden" name="through_ticket_disclosure" value="" />
  <input type="hidden" name="separate_contract_notice" value="" />
  <div id="railArt12Card" class="rail-card mt16"<?= $selectedDepartureHasConnections ? '' : ' style="display:none;"' ?>>
    <strong>Art. 12-struktur (kun ved skift)</strong>
    <div class="small muted mt8">Rail trin 3 bruger den valgte afgang som Art. 12-stop. Systemet ser skift og operatorer automatisk, men backend-billetupload laver stadig den endelige kontraktvurdering.</div>
    <div class="contract-auto-box mt12<?= $art12NeedsAttention ? ' warn' : '' ?>" id="railArt12AutoBox">
      <div><strong>Auto-vurdering:</strong> <?= h($autoContractLabel) ?></div>
      <div class="small muted mt8">Struktur: <?= h($selectedDepartureHasConnections ? 'Forbindelse med skift' : 'Direkte tog') ?><?php if ($selectedDepartureHasConnections): ?> -> Confidence: <?= h($contractConfidence) ?><?php endif; ?></div>
      <?php if ($operatorCountSeed > 0): ?>
        <div class="small muted mt8">Systemet har fundet <?= h((string)$operatorCountSeed) ?> operatoer<?= $operatorCountSeed === 1 ? '' : 'er' ?><?= $operatorNamesSeed !== [] ? ': ' . h(implode(' · ', $operatorNamesSeed)) : '' ?>.</div>
      <?php endif; ?>
      <?php if ($operatorCountSeed <= 0 && $selectedFallbackOperatorHints !== []): ?>
        <div class="small muted mt8">AI fallback peger paa sandsynlige operatoerer: <?= h(implode(' · ', $selectedFallbackOperatorHints)) ?>.</div>
        <div class="small muted mt8">Hintene har lav confidence og bruges kun som UX-stoette. De taeller ikke som sikker Art. 12-dokumentation.</div>
      <?php endif; ?>
      <?php if ($topologyHint !== ''): ?>
        <div class="small muted mt8">Systemet har fundet: <?= h($topologyHint) ?></div>
      <?php endif; ?>
      <?php if ($selectedDepartureHasConnections): ?>
        <div class="small muted mt8">Hvis rejsen ikke kan stoppes automatisk som gennemgaaende, fortsaetter Art. 12-spoergsmaalene nedenfor og afgraenser senere trin.</div>
      <?php endif; ?>
    </div>
    <?php if ($art12NeedsAttention): ?>
      <div class="contract-warning mt12">
        <?= h($railContractWarning !== '' ? $railContractWarning : 'Art. 12 kan ikke stoppes automatisk for denne forbindelse. Udfyld spoergsmaalene nedenfor, foer du fortsaetter.') ?>
      </div>
    <?php endif; ?>
    <button type="button" class="toggle-link" id="toggleRailArt12Edit"<?= $selectedDepartureHasConnections ? '' : ' style="display:none;"' ?>>Rediger Art. 12-oplysninger</button>
    <div id="railArt12Edit" class="contract-edit" style="display:<?= $manualArt12Open ? 'block' : 'none' ?>;">
      <label>Hvem koebte du billetten hos?</label>
      <div class="mt8">
        <label style="display:inline-block; font-weight:400;"><input type="radio" name="seller_channel" value="operator" <?= $sellerChannel === 'operator' ? 'checked' : '' ?> /> Jernbaneoperatoer</label>
        <label class="ml8" style="display:inline-block; font-weight:400;"><input type="radio" name="seller_channel" value="retailer" <?= $sellerChannel === 'retailer' ? 'checked' : '' ?> /> Rejsebureau / billetudsteder</label>
      </div>
      <label>Blev billetterne koebt i samme transaktion?</label>
      <div class="mt8">
        <label style="display:inline-block; font-weight:400;"><input type="radio" name="same_transaction" value="yes" <?= $sameTransaction === 'yes' ? 'checked' : '' ?> /> Ja</label>
        <label class="ml8" style="display:inline-block; font-weight:400;"><input type="radio" name="same_transaction" value="no" <?= $sameTransaction === 'no' ? 'checked' : '' ?> /> Nej</label>
      </div>
      <div id="railArt12Followup" class="mt12" style="display:<?= $needsFollowup ? 'block' : 'none' ?>;">
        <label>Var du tydeligt informeret om, hvorvidt din(e) billet(ter) var gennemgaaende eller saerskilte?</label>
        <div class="mt8">
          <label style="display:inline-block; font-weight:400;"><input type="radio" name="through_ticket_disclosure" value="yes" <?= $throughTicketDisclosure === 'yes' ? 'checked' : '' ?> /> Ja</label>
          <label class="ml8" style="display:inline-block; font-weight:400;"><input type="radio" name="through_ticket_disclosure" value="no" <?= $throughTicketDisclosure === 'no' ? 'checked' : '' ?> /> Nej</label>
        </div>

        <label>Staar det paa billet eller booking, at delene er saerskilte befordringskontrakter?</label>
        <div class="mt8">
          <label style="display:inline-block; font-weight:400;"><input type="radio" name="separate_contract_notice" value="yes" <?= $separateContractNotice === 'yes' ? 'checked' : '' ?> /> Ja</label>
          <label class="ml8" style="display:inline-block; font-weight:400;"><input type="radio" name="separate_contract_notice" value="no" <?= $separateContractNotice === 'no' ? 'checked' : '' ?> /> Nej</label>
        </div>
      </div>
      <div class="small muted mt8">Rail bruger ingen "ved ikke" i dette stop. Hvis du er i tvivl, svar ud fra det du faktisk saa ved koebet. Backend kan senere korrigere ud fra billetter og booking.</div>
    </div>
      <div class="contract-edit mt12" id="railProblemContractBox" data-current-value="<?= h($selectedProblemContractId) ?>" style="display:<?= ($selectedDepartureHasConnections && $effectiveContractModel === 'separate') ? 'block' : 'none' ?>;">
        <label>Hvilken kontrakt skabte problemet?</label>
        <div id="railProblemContractChoices" class="contract-choice-list">
          <?php foreach ($contractOptions as $contractKey => $contractOption): ?>
            <?php
              $contractInputId = 'rail_problem_contract_' . preg_replace('/[^a-zA-Z0-9_\-]+/', '_', (string)$contractKey);
              $contractLabel = (string)($contractOption['label'] ?? $contractKey);
              $isProvisionalContract = !empty($contractOption['provisional']);
              $contractStops = $extractContractStops((array)$contractOption);
              $contractStopNames = array_map(static fn(array $stop): string => (string)($stop['name'] ?? ''), $contractStops);
            ?>
            <div class="contract-choice-item">
              <label for="<?= h($contractInputId) ?>">
                <input
                  type="radio"
                  name="problem_contract_id"
                  id="<?= h($contractInputId) ?>"
                  value="<?= h((string)$contractKey) ?>"
                  data-contract-stops="<?= h((string)json_encode(array_values(array_filter($contractStopNames, static fn(string $value): bool => trim($value) !== '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                  <?= $selectedProblemContractId === (string)$contractKey ? 'checked' : '' ?>
                />
                <span><?= h($contractLabel) ?></span>
              </label>
              <?php if ($isProvisionalContract): ?>
                <div class="contract-choice-meta">Provisorisk kontraktopdeling udledt af valgt rail-rejse. Backend-billetreview kan senere korrigere Art. 12.</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="small muted mt8">Vaelg kun den ene billetdel/kontrakt, som skabte problemet. Den bliver fokus for rail-stranding, incident og remedies.</div>
      </div>
  </div>
<?php endif; ?>

<?php if ($hasAnyConnectionCandidates): ?>
  <div id="railProblemAnchorCard" class="rail-card mt16"<?= $selectedDepartureHasConnections ? '' : ' style="display:none;"' ?>>
    <strong>Hvor opstod problemet?</strong>
    <div class="small muted mt8">Brug dette tidligt i rail-flowet, hvis rejsen har skift. Det afgraenser, hvilken forbindelse der skal vaere fokus i station-trinnet, incident og remedies. Hvis du ikke ved det endnu, kan du lade svaret staa paa "Ved ikke endnu".</div>

    <div class="contract-edit mt12">
      <label for="railProblemAnchorSelect">Vaelg problemsted</label>
      <select id="railProblemAnchorSelect" name="rail_problem_anchor_choice" data-current-value="<?= h($selectedProblemAnchorChoice !== '' ? $selectedProblemAnchorChoice : 'unknown') ?>">
        <option value="unknown" <?= $selectedProblemAnchorChoice === 'unknown' ? 'selected' : '' ?>>Ved ikke endnu</option>
        <option value="before_departure" <?= $selectedProblemAnchorChoice === 'before_departure' ? 'selected' : '' ?>>Foer afgang fra <?= h($problemAnchorStartStation !== '' ? $problemAnchorStartStation : 'startstationen') ?></option>
        <?php foreach ($selectedProblemAnchorStations as $anchorIndex => $anchorStation): ?>
          <option value="transfer:<?= h((string)$anchorIndex) ?>" <?= $selectedProblemAnchorChoice === ('transfer:' . (string)$anchorIndex) ? 'selected' : '' ?>>Ved skift i <?= h($anchorStation) ?></option>
        <?php endforeach; ?>
        <option value="en_route" <?= $selectedProblemAnchorChoice === 'en_route' ? 'selected' : '' ?>>Senere paa den valgte kontrakt</option>
      </select>
      <div class="small muted mt8">Ved separate kontrakter virker dette sammen med kontraktvalget ovenfor. Det senere backend-review kan stadig korrigere Art. 12, hvis billetten viser noget andet.</div>
    </div>
  </div>
<?php endif; ?>

<div class="manual-box mt16">
  <strong>Manuel fallback</strong>
  <div class="small muted mt8">Brug dette, hvis listen mangler din afgang eller live-provider er nede. Du kan stadig fortsaette rail-flowet, selv om match kun er manuelt.</div>
  <div class="manual-grid mt12">
    <label>Tognummer (valgfri)
      <input type="text" name="manual_rail_train_number" value="<?= h((string)($selectedDeparture['train_number'] ?? ($form['train_no'] ?? ''))) ?>" placeholder="fx EC 395" />
    </label>
    <label>Operator (valgfri)
      <input type="text" name="manual_rail_operator_name" value="<?= h((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ''))) ?>" placeholder="fx DSB / Deutsche Bahn" />
    </label>
  </div>
</div>

<div class="step-actions mt16">
  <?= $this->Html->link('Tilbage', ['action' => $prevAction], ['class' => 'button-secondary']) ?>
  <button type="submit" class="button-primary">Videre til rail-stranding</button>
  <span class="small muted">Valgt afgang bruges kun som seed til rail incident/gating og skal stadig bekraeftes senere af brugeren.</span>
</div>

</fieldset>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'rail_departure_select']) ?>

<script>
(function(){
  const toggle = document.getElementById('toggleRailArt12Edit');
  const editBox = document.getElementById('railArt12Edit');
  if (!toggle || !editBox) return;
  toggle.addEventListener('click', function() {
    const open = editBox.style.display !== 'none';
    editBox.style.display = open ? 'none' : 'block';
  });
})();
</script>
</div>

<script>
(() => {
  const options = Array.from(document.querySelectorAll('.departure-option'));
  const art12Card = document.getElementById('railArt12Card');
  const problemAnchorCard = document.getElementById('railProblemAnchorCard');
  const problemAnchorSelect = document.getElementById('railProblemAnchorSelect');
  const problemContractBox = document.getElementById('railProblemContractBox');
  const problemContractChoices = document.getElementById('railProblemContractChoices');
  const followupBox = document.getElementById('railArt12Followup');
  const initialProblemAnchorValue = problemAnchorSelect ? (problemAnchorSelect.getAttribute('data-current-value') || 'unknown') : 'unknown';
  const getRadioValue = (name) => {
    const checked = document.querySelector('input[name="' + name + '"]:checked');
    return checked ? checked.value : '';
  };
  const getSellerChannel = () => getRadioValue('seller_channel');
  const getSameTransaction = () => getRadioValue('same_transaction');
  const getDisclosure = () => getRadioValue('through_ticket_disclosure');
  const getSeparateNotice = () => getRadioValue('separate_contract_notice');

  const getSelectedOption = () => options.find((option) => {
    const input = option.querySelector('input[type="radio"]');
    return !!(input && input.checked);
  });

  const getSelectedOperatorCount = () => {
    const selected = getSelectedOption();
    return selected ? parseInt(selected.getAttribute('data-operator-count') || '0', 10) : 0;
  };

  const resolveEffectiveContractModel = () => {
    const selected = getSelectedOption();
    const transferCount = selected ? parseInt(selected.getAttribute('data-transfer-count') || '0', 10) : 0;
    if (transferCount <= 0) {
      return 'through';
    }

    const sameTransaction = getSameTransaction();
    if (sameTransaction === 'no') {
      return 'separate';
    }
    if (sameTransaction !== 'yes') {
      return '';
    }

    const operatorCount = getSelectedOperatorCount();
    if (operatorCount === 1) {
      return 'through';
    }
    if (operatorCount <= 0) {
      return '';
    }

    const disclosure = getDisclosure();
    const separateNotice = getSeparateNotice();
    if (!disclosure || !separateNotice) {
      return '';
    }
    return disclosure === 'yes' && separateNotice === 'yes' ? 'separate' : 'through';
  };

  const buildProblemContractChoices = () => {
    if (!problemContractBox || !problemContractChoices) {
      return;
    }

    const selected = getSelectedOption();
    const transferCount = selected ? parseInt(selected.getAttribute('data-transfer-count') || '0', 10) : 0;
    const isSeparate = resolveEffectiveContractModel() === 'separate';
    if (!selected || transferCount <= 0 || !isSeparate) {
      problemContractBox.style.display = 'none';
      problemContractChoices.innerHTML = '';
      return;
    }

    const originName = selected.getAttribute('data-origin-name') || '';
    const destinationName = selected.getAttribute('data-destination-name') || '';
    const primaryService = selected.getAttribute('data-primary-service') || '';
    const operatorName = selected.getAttribute('data-operator-name') || '';
    const transferStationsJson = selected.getAttribute('data-transfer-stations') || '[]';
    let transferStations = [];

    try {
      transferStations = JSON.parse(transferStationsJson);
    } catch (error) {
      transferStations = [];
    }

    const stops = [originName]
      .concat(Array.isArray(transferStations) ? transferStations.filter(Boolean) : [], [destinationName])
      .filter(Boolean);

    if (stops.length <= 1) {
      problemContractBox.style.display = 'none';
      problemContractChoices.innerHTML = '';
      return;
    }

    const currentValue = problemContractBox.getAttribute('data-current-value') || '';
    problemContractChoices.innerHTML = '';
    for (let index = 0; index < stops.length - 1; index += 1) {
      const contractKey = 'DEP:' + index;
      const contractStops = [stops[index], stops[index + 1]];
      const item = document.createElement('div');
      item.className = 'contract-choice-item';

      const label = document.createElement('label');
      label.htmlFor = 'rail_problem_contract_' + contractKey.replace(/[^a-zA-Z0-9_\-]+/g, '_');

      const input = document.createElement('input');
      input.type = 'radio';
      input.name = 'problem_contract_id';
      input.id = label.htmlFor;
      input.value = contractKey;
      input.dataset.contractStops = JSON.stringify(contractStops);
      if (currentValue === contractKey || (!currentValue && index === 0)) {
        input.checked = true;
      }

      const text = document.createElement('span');
      const labelParts = ['Kontrakt ' + (index + 1)];
      if (primaryService) labelParts.push(primaryService);
      labelParts.push(stops[index] + ' -> ' + stops[index + 1]);
      if (operatorName) labelParts.push(operatorName);
      text.textContent = labelParts.join(' · ');

      label.appendChild(input);
      label.appendChild(text);
      item.appendChild(label);

      const meta = document.createElement('div');
      meta.className = 'contract-choice-meta';
      meta.textContent = 'Provisorisk kontraktopdeling udledt af valgt rail-rejse. Backend-billetreview kan senere korrigere Art. 12.';
      item.appendChild(meta);

      problemContractChoices.appendChild(item);
    }

    problemContractBox.style.display = '';
  };

  const buildProblemAnchorOptions = () => {
    if (!problemAnchorSelect) {
      return;
    }
    const selected = getSelectedOption();
    const transferCount = selected ? parseInt(selected.getAttribute('data-transfer-count') || '0', 10) : 0;
    const isSeparate = resolveEffectiveContractModel() === 'separate';
    let originName = selected ? (selected.getAttribute('data-origin-name') || '') : '';
    let transferStations = [];

    if (isSeparate && problemContractBox) {
      const selectedContract = problemContractBox.querySelector('input[name="problem_contract_id"]:checked');
      const contractStopsJson = selectedContract ? (selectedContract.getAttribute('data-contract-stops') || '[]') : '[]';
      let contractStops = [];
      try {
        contractStops = JSON.parse(contractStopsJson);
      } catch (error) {
        contractStops = [];
      }
      if (Array.isArray(contractStops) && contractStops.length > 0) {
        originName = contractStops[0] || originName;
        transferStations = contractStops.slice(1, -1).filter(Boolean);
      }
    } else {
      const transferStationsJson = selected ? (selected.getAttribute('data-transfer-stations') || '[]') : '[]';
      try {
        transferStations = JSON.parse(transferStationsJson);
      } catch (error) {
        transferStations = [];
      }
    }

    const previousValue = problemAnchorSelect.value || initialProblemAnchorValue || 'unknown';
    const nextOptions = [{
      value: 'unknown',
      label: 'Ved ikke endnu',
    }, {
      value: 'before_departure',
      label: 'Foer afgang fra ' + (originName || 'startstationen'),
    }];

    if (transferCount > 0) {
      transferStations.forEach((stationName, index) => {
        if (!stationName) return;
        nextOptions.push({
          value: 'transfer:' + index,
          label: 'Ved skift i ' + stationName,
        });
      });
    }

    nextOptions.push({
      value: 'en_route',
      label: 'Senere paa den valgte kontrakt',
    });

    problemAnchorSelect.innerHTML = '';
    nextOptions.forEach((item) => {
      const option = document.createElement('option');
      option.value = item.value;
      option.textContent = item.label;
      problemAnchorSelect.appendChild(option);
    });

    const hasPrevious = nextOptions.some((item) => item.value === previousValue);
    problemAnchorSelect.value = hasPrevious ? previousValue : 'unknown';
  };

  const syncArt12Visibility = () => {
    const selected = getSelectedOption();
    const transferCount = selected ? parseInt(selected.getAttribute('data-transfer-count') || '0', 10) : 0;
    const operatorCount = getSelectedOperatorCount();
    const sameTransaction = getSameTransaction();
    const needsFollowup = transferCount > 0 && sameTransaction === 'yes' && operatorCount > 1;
    if (art12Card) {
      art12Card.style.display = transferCount > 0 ? '' : 'none';
    }
    if (problemAnchorCard) {
      problemAnchorCard.style.display = transferCount > 0 ? '' : 'none';
    }
    if (followupBox) {
      followupBox.style.display = needsFollowup ? 'block' : 'none';
      if (!needsFollowup) {
        document.querySelectorAll('input[name="through_ticket_disclosure"], input[name="separate_contract_notice"]').forEach((input) => {
          if (input instanceof HTMLInputElement) {
            input.checked = false;
          }
        });
      }
    }
    buildProblemContractChoices();
    buildProblemAnchorOptions();
  };

  if (!options.length) {
    syncArt12Visibility();
    return;
  }

  const syncSelectedState = () => {
    options.forEach((option) => {
      const input = option.querySelector('input[type="radio"]');
      option.classList.toggle('selected', !!(input && input.checked));
    });
    syncArt12Visibility();
  };

  options.forEach((option) => {
    option.addEventListener('click', (event) => {
      const input = option.querySelector('input[type="radio"]');
      if (!input) return;
      if (event.target instanceof HTMLInputElement && event.target.type === 'radio') {
        syncSelectedState();
        return;
      }
      input.checked = true;
      syncSelectedState();
    });
  });

  document.querySelectorAll('input[name="seller_channel"], input[name="same_transaction"], input[name="through_ticket_disclosure"], input[name="separate_contract_notice"]').forEach((input) => {
    input.addEventListener('change', () => {
      if (problemContractBox) {
        problemContractBox.setAttribute('data-current-value', '');
      }
      syncArt12Visibility();
    });
  });

  document.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.name === 'problem_contract_id') {
      if (problemContractBox) {
        problemContractBox.setAttribute('data-current-value', target.value);
      }
      buildProblemAnchorOptions();
    }
  });

  syncSelectedState();
})();
</script>
