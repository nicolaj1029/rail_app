<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$flightCandidates = $flightCandidates ?? [];
$selectedFlight = $selectedFlight ?? [];
$selectedLeg = $selectedLeg ?? [];
$depAirport = trim((string)($depAirport ?? ''));
$arrAirport = trim((string)($arrAirport ?? ''));
$depDate = trim((string)($depDate ?? ''));
$isPreview = !empty($flowPreview);
$flightSearchFallback = empty($flightCandidates);
$selectedFlightKey = trim((string)($selectedFlight['flight_key'] ?? ($meta['air_selected_flight_key'] ?? ($form['selected_flight_key'] ?? ''))));
$selectedFlightNumber = strtoupper(trim((string)($selectedFlight['flight_number'] ?? ($form['selected_flight_number'] ?? ($form['ticket_no'] ?? '')))));
$selectedFlightCarrier = mb_strtolower(trim((string)($selectedFlight['carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ''))), 'UTF-8');
$selectedFlightDep = (string)($selectedFlight['scheduled_departure_local'] ?? '');
$selectedFlightArr = (string)($selectedFlight['scheduled_arrival_local'] ?? '');
$prevAction = trim((string)($prevAction ?? 'entitlements'));
$lookupStrategy = trim((string)($lookupStrategy ?? ''));
$lookupDebug = $lookupDebug ?? [];
$routeLegs = $routeLegs ?? [];
$stepHeading = 'TRIN 3';
$stepTitle = 'Vaelg flyvning';
foreach (($flowSteps ?? []) as $step) {
    if ((string)($step['action'] ?? '') !== 'airFlightSelect') {
        continue;
    }
    $stepNum = $step['ui_num'] ?? $step['num'] ?? 3;
    $stepHeading = 'TRIN ' . (string)$stepNum;
    $stepTitle = (string)($step['title'] ?? $stepTitle);
    break;
}
$routeLine = '';
if (!empty($routeLegs)) {
    $points = [];
    foreach ($routeLegs as $index => $leg) {
        if (!is_array($leg)) {
            continue;
        }
        $depLabel = trim((string)($leg['dep_label'] ?? ''));
        $arrLabel = trim((string)($leg['arr_label'] ?? ''));
        if ($index === 0 && $depLabel !== '') {
            $points[] = $depLabel;
        }
        if ($arrLabel !== '') {
            $points[] = $arrLabel;
        }
    }
    $routeLine = implode(' -> ', $points);
}

$formatTimeRange = static function (array $item): string {
    $dep = (string)($item['scheduled_departure_local'] ?? '');
    $arr = (string)($item['scheduled_arrival_local'] ?? '');
    $depPart = strpos($dep, 'T') !== false ? substr($dep, strpos($dep, 'T') + 1, 5) : $dep;
    $arrPart = strpos($arr, 'T') !== false ? substr($arr, strpos($arr, 'T') + 1, 5) : $arr;
    if ($depPart !== '' && $arrPart !== '') {
        return $depPart . ' - ' . $arrPart;
    }
    if ($depPart !== '') {
        return $depPart;
    }
    return 'Ukendt tidspunkt';
};
?>

<style>
  .air-step { max-width: 1040px; }
  .flight-card { padding:16px; border:1px solid #dbe3ea; background:#fff; border-radius:16px; box-shadow:0 8px 20px rgba(15, 23, 42, .04); }
  .flight-grid { display:grid; grid-template-columns: 1.2fr 1.8fr; gap:14px; align-items:start; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .mt16 { margin-top:16px; }
  .small { font-size:12px; }
  .muted { color:#64748b; }
  .pill { display:inline-block; padding:4px 10px; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; }
  .route-line { font-size:18px; font-weight:700; color:#0f172a; margin-top:8px; }
  .route-meta { display:grid; gap:10px; margin-top:12px; }
  .route-meta-item { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:10px 12px; }
  .route-meta-label { display:block; font-size:12px; color:#64748b; margin-bottom:2px; }
  .flight-candidate-list { display:grid; gap:10px; margin-top:12px; }
  .flight-option { position:relative; border:1px solid #dbe3ea; border-radius:14px; background:#fff; transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
  .flight-option:hover { border-color:#94a3b8; box-shadow:0 10px 18px rgba(15, 23, 42, .06); transform:translateY(-1px); }
  .flight-option.selected { border-color:#0f766e; box-shadow:0 0 0 2px rgba(15, 118, 110, .12); background:#f0fdfa; }
  .flight-option input[type=radio] { position:absolute; top:16px; left:16px; }
  .flight-option-label { display:block; cursor:pointer; padding:14px 16px 14px 46px; }
  .flight-option-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
  .flight-time { font-size:22px; font-weight:800; color:#0f172a; letter-spacing:.01em; }
  .flight-number { font-size:14px; font-weight:700; color:#0f172a; }
  .flight-carrier { margin-top:2px; color:#334155; }
  .flight-source { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; background:#eef2ff; color:#4338ca; }
  .flight-option.selected .flight-source { background:#ccfbf1; color:#115e59; }
  .flight-meta { display:flex; gap:14px; flex-wrap:wrap; margin-top:10px; font-size:13px; color:#475569; }
  .flight-meta strong { color:#0f172a; }
  .manual-box { border:1px dashed #cbd5e1; border-radius:14px; background:#f8fafc; padding:14px; }
  .manual-grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .manual-grid label { display:block; font-weight:600; color:#0f172a; }
  .manual-grid input { display:block; width:100%; margin-top:6px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; }
  .step-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .button-secondary { display:inline-block; padding:12px 16px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#0f172a; font-weight:700; text-decoration:none; }
  .button-secondary:hover { background:#f8fafc; border-color:#94a3b8; }
  .button-primary { display:inline-block; padding:12px 16px; border:none; border-radius:12px; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
  .button-primary:hover { background:#1e293b; }
  .helper-note { border-left:4px solid #38bdf8; background:#f0f9ff; border-radius:10px; padding:10px 12px; color:#0f172a; }
  @media (max-width: 820px) {
    .flight-grid, .manual-grid { grid-template-columns: 1fr; }
    .flight-time { font-size:18px; }
  }
</style>

<div class="air-step">
<h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
<div class="small muted">Vaelg den konkrete flight tidligt i flowet. Vi bruger den til at stabilisere carrier og route-legs, foer du vaelger hvilket segment der gik galt.</div>
<?php if ($lookupStrategy === 'separate_segment_first'): ?>
  <div class="helper-note mt12">Lookup koeres segment-for-segment, fordi forbindelsen er markeret som separat billet / self-transfer.</div>
<?php elseif ($lookupStrategy === 'connecting_segment_first'): ?>
  <div class="helper-note mt12">Lookup koeres primært pr. segment, fordi rejsen har mellemlandinger. Mellemlandingerne bruges derfor aktivt i matchningen.</div>
<?php elseif ($lookupStrategy === 'unknown_provisional'): ?>
  <div class="helper-note mt12">Flight-match er foreloebigt. Rutestruktur og leg-valg bekraeftes i naeste trin.</div>
<?php endif; ?>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<div class="flight-card mt16">
  <?php if ($routeLine !== ''): ?>
    <span class="pill">Rute</span>
    <div class="route-line"><?= h($routeLine) ?></div>
    <?php if (count($routeLegs) > 1): ?>
      <div class="small muted mt8">Der er registreret <?= count($routeLegs) ?> flight-legs ud fra dine mellemlandinger. Flight-listen prioriterer derfor segment-match.</div>
    <?php endif; ?>
  <?php endif; ?>
  <div class="<?= $routeLine !== '' ? 'mt12' : '' ?>">
  <strong>Vaelg dit fly fra listen</strong>
  <div class="small muted mt8">Listen kan komme fra live schedule/FIDS-data eller fra det, vi allerede kender fra TRIN 2. Ved separate contracts bruger vi seedede legs og lookup pr. segment som fallback.</div>
  </div>

  <?php if (!empty($flightCandidates)): ?>
    <div class="flight-candidate-list">
      <?php foreach ($flightCandidates as $idx => $item): ?>
        <?php
          $key = (string)($item['flight_key'] ?? ('candidate_' . $idx));
          $candidateFlightNumber = strtoupper(trim((string)($item['flight_number'] ?? '')));
          $candidateCarrier = mb_strtolower(trim((string)($item['carrier_name'] ?? ($item['marketing_carrier_name'] ?? ''))), 'UTF-8');
          $candidateDep = (string)($item['scheduled_departure_local'] ?? '');
          $candidateArr = (string)($item['scheduled_arrival_local'] ?? '');
          $isChecked = $key !== '' && $key === $selectedFlightKey;
          if (!$isChecked && $selectedFlightNumber !== '' && $candidateFlightNumber !== '' && $candidateFlightNumber === $selectedFlightNumber) {
            $sameCarrier = $selectedFlightCarrier === '' || $candidateCarrier === '' || $candidateCarrier === $selectedFlightCarrier;
            $sameDep = $selectedFlightDep === '' || $candidateDep === '' || $candidateDep === $selectedFlightDep;
            $sameArr = $selectedFlightArr === '' || $candidateArr === '' || $candidateArr === $selectedFlightArr;
            $isChecked = $sameCarrier && $sameDep && $sameArr;
          }
          $rowId = 'flight_candidate_' . $idx;
          $carrierName = (string)($item['carrier_name'] ?? 'Ukendt carrier');
          $flightNumber = (string)($item['flight_number'] ?? '-');
          $source = (string)($item['source'] ?? 'unknown');
          $matchType = (string)($item['match_type'] ?? '');
          $matchedLegTitle = trim((string)($item['matched_leg_title'] ?? ''));
          $operating = trim((string)($item['operating_carrier_name'] ?? ''));
          $codeshares = array_values(array_filter((array)($item['codeshare_numbers'] ?? []), static fn($value): bool => trim((string)$value) !== ''));
        ?>
        <div class="flight-option<?= $isChecked ? ' selected' : '' ?>">
          <input id="<?= h($rowId) ?>" type="radio" name="selected_flight_key" value="<?= h($key) ?>" <?= $isChecked ? 'checked' : '' ?> />
          <label class="flight-option-label" for="<?= h($rowId) ?>">
            <div class="flight-option-top">
              <div>
                <div class="flight-time"><?= h($formatTimeRange($item)) ?></div>
                <div class="flight-carrier"><?= h($carrierName) ?></div>
              </div>
              <div style="text-align:right;">
                <div class="flight-number"><?= h($flightNumber) ?></div>
                <span class="flight-source"><?= h($source) ?></span>
              </div>
            </div>
            <div class="flight-meta">
              <?php if ($matchedLegTitle !== ''): ?>
                <span><strong>Leg:</strong> <?= h($matchedLegTitle) ?></span>
              <?php endif; ?>
              <?php if ($operating !== ''): ?>
                <span><strong>Operating:</strong> <?= h($operating) ?></span>
              <?php endif; ?>
              <?php if (!empty($codeshares)): ?>
                <span><strong>Codeshare:</strong> <?= h(implode(', ', $codeshares)) ?></span>
              <?php endif; ?>
              <?php if ($matchType === 'full_route_provisional'): ?>
                <span><strong>Match:</strong> Foreloebigt route-match</span>
              <?php elseif ($matchType === 'exact_segment_match'): ?>
                <span><strong>Match:</strong> Segment-match</span>
              <?php endif; ?>
            </div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="helper-note mt12">
      Vi kunne ikke hente en brugbar flight-liste for denne rute og dato endnu. Brug manuel fallback nedenfor, sa sagen stadig kan oprettes.
    </div>
  <?php endif; ?>
</div>

<div class="manual-box mt16">
  <strong>Manuel fallback</strong>
  <div class="small muted mt8">Brug dette, hvis listen mangler din flight eller hvis du kender flynummeret bedre end listen. Du vaelger berort leg i naeste trin, hvis rejsen havde flere segmenter.</div>
  <div class="manual-grid mt12">
    <label>Flynummer
      <input type="text" name="manual_flight_number" value="<?= h((string)($selectedFlight['flight_number'] ?? '')) ?>" placeholder="fx SK568" />
    </label>
    <label>Carrier (valgfri)
      <input type="text" name="manual_carrier_name" value="<?= h((string)($selectedFlight['carrier_name'] ?? '')) ?>" placeholder="fx SAS" />
    </label>
  </div>
  <div class="small muted mt8">Det er ikke et brugerkrav at skelne mellem marketing og operating carrier her.</div>
  <?php if ($flightSearchFallback): ?>
    <div class="small muted mt8">Manuel fallback er aktiv, fordi search-laget ikke fandt et brugbart match denne gang.</div>
  <?php endif; ?>
</div>

<div class="step-actions mt16">
  <?= $this->Html->link('Tilbage', ['action' => $prevAction], ['class' => 'button-secondary']) ?>
  <button type="submit" class="button-primary">Videre til haendelse</button>
  <span class="small muted">Du kan stadig justere flynummer senere pa sagen, hvis ticket eller booking viser noget andet.</span>
</div>

</fieldset>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'air_flight_select']) ?>
</div>

<?php if (!empty($lookupDebug) && !empty($flowPreview)): ?>
  <div class="small muted mt12">
    debug: strategy=<?= h((string)($lookupDebug['query_type'] ?? '')) ?>,
    vias_used=<?= !empty($lookupDebug['vias_used']) ? 'true' : 'false' ?>,
    match_type=<?= h((string)($lookupDebug['match_type'] ?? '')) ?>,
    source=<?= h((string)($lookupDebug['source'] ?? '')) ?>
  </div>
<?php endif; ?>

<script>
(() => {
  const options = Array.from(document.querySelectorAll('.flight-option'));
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
