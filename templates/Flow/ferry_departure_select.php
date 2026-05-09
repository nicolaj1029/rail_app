<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$departureCandidates = $departureCandidates ?? [];
$selectedDeparture = $selectedDeparture ?? [];
$depPort = trim((string)($depPort ?? ''));
$arrPort = trim((string)($arrPort ?? ''));
$depDate = trim((string)($depDate ?? ''));
$mockScenarioOptions = $mockScenarioOptions ?? [];
$selectedMockScenario = trim((string)($selectedMockScenario ?? ''));
$isPreview = !empty($flowPreview);
$departureSearchFallback = empty($departureCandidates);
$selectedDepartureKey = trim((string)($selectedDeparture['departure_key'] ?? ($meta['ferry_selected_departure_key'] ?? ($form['selected_ferry_departure_key'] ?? ''))));
$selectedVesselName = mb_strtolower(trim((string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? ''))), 'UTF-8');
$selectedOperatorName = mb_strtolower(trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ''))), 'UTF-8');
$prevAction = 'entitlements';
$stepHeading = 'TRIN 3';
$stepTitle = 'Vaelg afgang';
foreach (($flowSteps ?? []) as $step) {
    if ((string)($step['action'] ?? '') !== 'ferryDepartureSelect') {
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
    $dep = $formatDateTime((string)($item['scheduled_departure_local'] ?? ''));
    $arr = $formatDateTime((string)($item['scheduled_arrival_local'] ?? ''));

    return $dep . ' -> ' . $arr;
};
?>

<style>
  .ferry-step { max-width: 1040px; }
  .ferry-card { padding:16px; border:1px solid #dbe3ea; background:#fff; border-radius:16px; box-shadow:0 8px 20px rgba(15, 23, 42, .04); }
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
  .departure-time { font-size:20px; font-weight:800; color:#0f172a; }
  .departure-vessel { font-size:14px; font-weight:700; color:#0f172a; }
  .departure-operator { margin-top:2px; color:#334155; }
  .departure-source { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; background:#eef2ff; color:#4338ca; }
  .departure-option.selected .departure-source { background:#ccfbf1; color:#115e59; }
  .departure-meta { display:flex; gap:14px; flex-wrap:wrap; margin-top:10px; font-size:13px; color:#475569; }
  .departure-meta strong { color:#0f172a; }
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
  @media (max-width: 820px) {
    .manual-grid { grid-template-columns: 1fr; }
    .departure-time { font-size:18px; }
  }
</style>

<div class="ferry-step">
<h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
<div class="small muted">Vaelg den konkrete afgang tidligt i ferry-flowet. Vi bruger den til at stabilisere operator, planlagt afgang og live ETA foer den egentlige ferry-gating.</div>
<?php if (!empty($selectedDeparture) || !empty($meta['ferry_operational_evidence'])): ?>
  <?= $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey')) ?>
<?php endif; ?>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="ferry-card mt16">
  <?php if ($depPort !== '' || $arrPort !== ''): ?>
    <span class="pill">Rute</span>
    <div class="route-line"><?= h(trim($depPort . ' -> ' . $arrPort, ' ->')) ?></div>
  <?php endif; ?>
  <div class="mt12">
    <strong>Vaelg din afgang fra listen</strong>
    <div class="small muted mt8">Listen kan komme fra AIS-baseret live feed eller fra det, vi allerede ved fra TRIN 2. Vi krydstjekker rute, dato, tidspunkt og operator for at vise de mest sandsynlige afgange.</div>
    <?php if ($depDate !== ''): ?>
      <div class="small muted mt8">Dato: <?= h($depDate) ?></div>
    <?php endif; ?>
  </div>

  <?php if ($mockScenarioOptions !== []): ?>
    <div class="helper-note mt12">
      <strong>Proforma testdata</strong>
      <div class="small muted mt8">Brug dette midlertidigt, indtil Spire/livefeed er koblet paa. Scenarioet skriver testdata ind som operationel ferry-evidence og taender panelet, saa Art. 19 og force-majeure kan testes i de efterfoelgende trin.</div>
      <div class="mt8">
        <label for="ferryMockScenario"><strong>Testscenario</strong></label>
        <select id="ferryMockScenario" name="ferry_mock_scenario" style="display:block; margin-top:6px; max-width:360px;">
          <?php foreach ($mockScenarioOptions as $scenarioValue => $scenarioLabel): ?>
            <option value="<?= h($scenarioValue) ?>" <?= $selectedMockScenario === (string)$scenarioValue ? 'selected' : '' ?>><?= h($scenarioLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($departureCandidates)): ?>
    <div class="departure-list">
      <?php foreach ($departureCandidates as $idx => $item): ?>
        <?php
          $key = (string)($item['departure_key'] ?? ('candidate_' . $idx));
          $candidateVessel = mb_strtolower(trim((string)($item['vessel_name'] ?? '')), 'UTF-8');
          $candidateOperator = mb_strtolower(trim((string)($item['operator_name'] ?? '')), 'UTF-8');
          $isChecked = $key !== '' && $key === $selectedDepartureKey;
          if (!$isChecked && $selectedVesselName !== '' && $candidateVessel !== '') {
            $sameVessel = $candidateVessel === $selectedVesselName;
            $sameOperator = $selectedOperatorName === '' || $candidateOperator === '' || $candidateOperator === $selectedOperatorName;
            $isChecked = $sameVessel && $sameOperator;
          }
          $rowId = 'ferry_departure_candidate_' . $idx;
          $vesselName = (string)($item['vessel_name'] ?? 'Ukendt faerge');
          $operatorName = (string)($item['operator_name'] ?? 'Ukendt operator');
          $source = (string)($item['source'] ?? 'unknown');
          $status = trim((string)($item['status'] ?? ''));
          $eta = trim((string)($item['estimated_arrival_local'] ?? ''));
          $reported = trim((string)($item['live_position_reported_local'] ?? ''));
          $destination = trim((string)($item['live_destination'] ?? ''));
          $speed = $item['live_speed_knots'] ?? null;
        ?>
        <div class="departure-option<?= $isChecked ? ' selected' : '' ?>">
          <input id="<?= h($rowId) ?>" type="radio" name="selected_ferry_departure_key" value="<?= h($key) ?>" <?= $isChecked ? 'checked' : '' ?> />
          <label class="departure-option-label" for="<?= h($rowId) ?>">
            <div class="departure-top">
              <div>
                <div class="departure-time"><?= h($formatTimeRange($item)) ?></div>
                <div class="departure-vessel"><?= h($vesselName) ?></div>
                <div class="departure-operator"><?= h($operatorName) ?></div>
              </div>
              <div style="text-align:right;">
                <span class="departure-source"><?= h($source) ?></span>
              </div>
            </div>
            <div class="departure-meta">
              <?php if ($status !== ''): ?>
                <span><strong>Status:</strong> <?= h($status) ?></span>
              <?php endif; ?>
              <?php if ($eta !== ''): ?>
                <span><strong>Live ETA:</strong> <?= h($formatDateTime($eta)) ?></span>
              <?php endif; ?>
              <?php if ($reported !== ''): ?>
                <span><strong>Sidst set:</strong> <?= h($formatDateTime($reported)) ?></span>
              <?php endif; ?>
              <?php if ($destination !== ''): ?>
                <span><strong>Destination:</strong> <?= h($destination) ?></span>
              <?php endif; ?>
              <?php if ($speed !== null && $speed !== ''): ?>
                <span><strong>Fart:</strong> <?= h(number_format((float)$speed, 1, '.', '')) ?> kn</span>
              <?php endif; ?>
            </div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="helper-note mt12">
      Vi kunne ikke hente en brugbar afgangsliste for denne rute og dato endnu. Brug manuel fallback nedenfor, saa sagen stadig kan oprettes.
    </div>
  <?php endif; ?>
</div>

<div class="manual-box mt16">
  <strong>Manuel fallback</strong>
  <div class="small muted mt8">Brug dette, hvis listen mangler din afgang eller hvis rute/tid endnu ikke matcher korrekt. Du kan stadig fortsætte ferry-flowet, selv om livedata ikke gav et sikkert match.</div>
  <div class="manual-grid mt12">
    <label>Faergenavn (valgfri)
      <input type="text" name="manual_ferry_vessel_name" value="<?= h((string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? ''))) ?>" placeholder="fx Aurora" />
    </label>
    <label>Operator (valgfri)
      <input type="text" name="manual_ferry_operator_name" value="<?= h((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ''))) ?>" placeholder="fx DFDS" />
    </label>
  </div>
  <?php if ($departureSearchFallback): ?>
    <div class="small muted mt8">Manuel fallback er aktiv, fordi search-laget ikke fandt et brugbart match denne gang.</div>
  <?php endif; ?>
</div>

<div class="step-actions mt16">
  <?= $this->Html->link('Tilbage', ['action' => $prevAction], ['class' => 'button-secondary']) ?>
  <button type="submit" class="button-primary">Videre til PMR og gating</button>
  <span class="small muted">Valgt afgang bruges som referencepunkt for live ETA, operator og sagens videre ferry-vurdering.</span>
</div>

</fieldset>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'ferry_departure_select']) ?>
</div>

<script>
(() => {
  const options = Array.from(document.querySelectorAll('.departure-option'));
  if (!options.length) return;

  const syncSelectedState = () => {
    options.forEach((option) => {
      const input = option.querySelector('input[type="radio"]');
      option.classList.toggle('selected', !!(input && input.checked));
    });
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

  syncSelectedState();
})();
</script>
