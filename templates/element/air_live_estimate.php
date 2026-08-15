<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$airRights = (array)($airRights ?? []);
$airScope = (array)($airScope ?? []);
$airContract = (array)($airContract ?? []);
$opsEvidence = (array)($meta['air_operational_evidence'] ?? []);
$airLiveEstimateScope = trim((string)($airLiveEstimateScope ?? ''));
$showPrimaryCards = isset($showPrimaryCards) ? (bool)$showPrimaryCards : true;
$transportCaps = (new \App\Service\TransportCapsResolver())->resolveAir($airScope, [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
    'contract' => $airContract,
]);
$airExpenseReview = [];
$airCostZonesPath = CONFIG . 'air' . DS . 'air_airport_cost_zones.php';
$airReviewBandsPath = CONFIG . 'air' . DS . 'air_expense_review_bands.php';
if (is_file($airCostZonesPath) && is_file($airReviewBandsPath)) {
    $airExpenseReview = (new \App\Service\Air\AirExpenseReviewBandService())->availableBandsForScope([
        'scope' => in_array($airLiveEstimateScope, ['air_assistance_scope', 'air_reroute_scope', 'air_refund_scope'], true)
            ? $airLiveEstimateScope
            : (!empty($airRights['gate_air_care']) ? 'air_assistance_scope' : 'air_reroute_scope'),
        'form' => $form,
        'flags' => $flags,
        'meta' => $meta,
        'air_scope' => $airScope,
    ]);
}

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';
$showTechnicalDetails = trim((string)$this->request->getSession()->read('admin.auth_user')) !== '';

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
$gateRerouteRefund = !empty($airRights['gate_air_reroute_refund']);
$gateDelayRefund5h = !empty($airRights['gate_air_delay_refund_5h']);
$gateRemedy = $gateRerouteRefund || $gateDelayRefund5h;
$incidentMain = strtolower(trim((string)($form['incident_main'] ?? '')));
$delayRefundOnly = $incidentMain === 'delay' && !$gateRerouteRefund && $gateDelayRefund5h;
$gateComp = !empty($airRights['gate_air_compensation']);
$article7EligibilityStatus = strtolower(trim((string)($airRights['article7_eligibility_status'] ?? ($gateComp ? 'eligible' : 'not_eligible'))));
$article7ReductionStatus = strtolower(trim((string)($airRights['article7_reduction_status'] ?? 'not_applicable')));
$article7ReductionApplies = !empty($airRights['article7_reduction_applies']);
$article7ReductionProvisional = $article7ReductionStatus === 'provisional';
$selectedFlight = (array)($meta['air_selected_flight'] ?? []);
$selectedLeg = (array)($meta['air_selected_leg'] ?? []);
$routeLegs = array_values(array_filter((array)($meta['air_route_legs'] ?? []), 'is_array'));
$claimParty = trim((string)($airContract['primary_claim_party_name'] ?? ($airContract['primary_claim_party'] ?? '')));
if ($claimParty === '' || $claimParty === 'manual_review') {
    $claimParty = trim((string)($selectedFlight['operating_carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ($form['operating_carrier'] ?? ($form['marketing_carrier'] ?? 'manual_review')))));
}
$responsibleCarrier = trim((string)($selectedFlight['operating_carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ($form['operating_carrier'] ?? ($form['marketing_carrier'] ?? '')))));
if ($responsibleCarrier === '') {
    $responsibleCarrier = trim((string)($airContract['liable_carrier_candidate'] ?? $claimParty));
}
$responsibleCarrierLabel = $responsibleCarrier !== '' && strtolower($responsibleCarrier) !== 'manual_review'
    ? $responsibleCarrier
    : 'Afventer svar';
$claimChannelLabel = match (strtolower($claimParty)) {
    '', 'manual_review' => 'Manuel vurdering',
    default => $claimParty,
};
$routeLabel = '';
if ($routeLegs !== []) {
    $routePoints = [];
    foreach ($routeLegs as $index => $leg) {
        $depLabel = trim((string)($leg['dep_label'] ?? ''));
        $arrLabel = trim((string)($leg['arr_label'] ?? ''));
        if ($index === 0 && $depLabel !== '') {
            $routePoints[] = $depLabel;
        }
        if ($arrLabel !== '') {
            $routePoints[] = $arrLabel;
        }
    }
    $routeLabel = implode(' -> ', $routePoints);
}
if ($routeLabel === '') {
    $routeLabel = trim((string)($form['dep_station'] ?? '')) . ' -> ' . trim((string)($form['arr_station'] ?? ''));
    $routeLabel = trim($routeLabel, ' ->');
}
$selectedLegLabel = trim((string)($selectedLeg['title'] ?? ''));
if ($selectedLegLabel === '' && !empty($selectedLeg['dep_label']) && !empty($selectedLeg['arr_label'])) {
    $selectedLegLabel = trim((string)$selectedLeg['dep_label']) . ' -> ' . trim((string)$selectedLeg['arr_label']);
}
$selectedFlightSummary = trim(implode(' | ', array_filter([
    trim((string)($selectedFlight['flight_number'] ?? '')),
    trim((string)($selectedFlight['carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ''))),
])));

$perPassengerBase = match ($distanceBand) {
    'up_to_1500' => 250.0,
    'intra_eu_over_1500', 'other_1500_to_3500' => 400.0,
    'other_over_3500' => 600.0,
    default => 250.0,
};
$hasKnownDistanceBand = in_array($distanceBand, [
    'up_to_1500',
    'intra_eu_over_1500',
    'other_1500_to_3500',
    'other_over_3500',
], true);
$reductionThreshold = match ($distanceBand) {
    'up_to_1500' => 120,
    'intra_eu_over_1500', 'other_1500_to_3500' => 180,
    'other_over_3500' => 240,
    default => 120,
};
$rerouteArrivalDelayMinutes = is_numeric($form['reroute_arrival_delay_minutes'] ?? null)
    ? (int)$form['reroute_arrival_delay_minutes']
    : 0;
$reductionPct = $article7ReductionApplies ? 50 : 0;
$perPassengerAmount = $article7EligibilityStatus === 'eligible'
    ? (float)($airRights['article7_final_amount_eur'] ?? round($perPassengerBase * ($reductionPct > 0 ? 0.5 : 1.0), 2))
    : 0.0;

$passengerCount = 1;
if (is_numeric($form['passenger_count'] ?? null) && (int)$form['passenger_count'] > 0) {
    $passengerCount = (int)$form['passenger_count'];
} elseif (!empty($meta['_passengers_auto']) && is_array($meta['_passengers_auto'])) {
    $passengerCount = max(1, count((array)$meta['_passengers_auto']));
}
$totalAmount = round($perPassengerAmount * $passengerCount, 2);
$potentialTotalAmount = round($perPassengerBase * $passengerCount, 2);

$airExpectedDelayBucket = strtolower(trim((string)($form['air_expected_delay_bucket'] ?? '')));
$airActualArrivalBucket = strtolower(trim((string)($form['air_actual_arrival_delay_bucket'] ?? '')));
$cancellationNoticeBand = strtolower(trim((string)($form['cancellation_notice_band'] ?? '')));
$voluntaryDeniedBoarding = strtolower(trim((string)($form['voluntary_denied_boarding'] ?? '')));
$extraordinaryCircumstances = strtolower(trim((string)($form['operatorExceptionalCircumstances'] ?? ($form['extraordinary_circumstances'] ?? ''))));
$pmrUserValue = strtolower(trim((string)($form['pmr_user'] ?? '')));
$unaccompaniedMinorValue = strtolower(trim((string)($form['unaccompanied_minor'] ?? '')));
$previewCareActive = $gateCare;
$previewRemedyActive = $gateRemedy;
if ($incidentMain === 'denied_boarding' && $voluntaryDeniedBoarding === 'yes') {
    $previewCareActive = $pmrUserValue === 'yes' || $unaccompaniedMinorValue === 'yes';
    $previewRemedyActive = true;
}
$ongoingDelayBelowThreshold = $incidentMain === 'delay'
    && !$isCompleted
    && $airExpectedDelayBucket === 'under_threshold';
$delayCompQuestionAnswered = $incidentMain === 'delay'
    && in_array($airActualArrivalBucket, ['under_3h', 'three_to_four', 'four_plus', 'never_arrived', 'unknown'], true);
$cancellationCompQuestionAnswered = $incidentMain === 'cancellation'
    && $cancellationNoticeBand !== '';
$deniedBoardingCompQuestionAnswered = $incidentMain === 'denied_boarding'
    && in_array($voluntaryDeniedBoarding, ['yes', 'no'], true);
$eligibilityResolved = $article7EligibilityStatus === 'eligible'
    || $delayCompQuestionAnswered
    || $cancellationCompQuestionAnswered
    || $deniedBoardingCompQuestionAnswered;
$resolvedNotEligible = $article7EligibilityStatus === 'not_eligible' && $eligibilityResolved;
$statusTone = $article7EligibilityStatus === 'eligible'
    ? 'green'
    : (($resolvedNotEligible || $ongoingDelayBelowThreshold) ? 'red' : 'gray');
$careTone = $previewCareActive ? 'green' : 'gray';
$remedyTone = $previewRemedyActive ? 'green' : 'gray';
$carrierTone = $responsibleCarrierLabel !== 'Afventer svar' ? 'green' : 'gray';
$panelTone = $statusTone;

$displayAmount = $article7EligibilityStatus === 'eligible'
    ? $totalAmount
    : ($ongoingDelayBelowThreshold
        ? 0.0
        : ($resolvedNotEligible
        ? 0.0
        : ($hasKnownDistanceBand ? $potentialTotalAmount : null)));

$statusText = $article7EligibilityStatus === 'eligible'
    ? 'Kompensation mulig'
    : ($article7EligibilityStatus === 'uncertain'
        ? 'Kompensation usikker'
    : ($ongoingDelayBelowThreshold
        ? 'Ikke aktiveret endnu'
    : ($resolvedNotEligible
        ? 'Ingen kompensation'
        : ($hasKnownDistanceBand
            ? 'Foreloebigt kompensationsniveau'
            : ($gateRemedy ? 'Article 8 / refund vurderes' : 'Afventer flere svar')))));
$nextText = $isCompleted
    ? 'Efter haendelsen kan du gaa direkte til kontaktoplysninger og sagsoprettelse.'
    : ($isOngoing
        ? 'Udfyld kun det, der er relevant nu. Resultatet opdateres loebende.'
        : 'Du kan fortsaette med ombooking/care og stadig se estimatet loebende.');
$careTooltip = (string)($transportCaps['tooltips']['care'] ?? 'Care vurderes konkret efter lov og noedvendighed.');
$remedyLabel = $delayRefundOnly ? 'Refund ved 5+ timer' : 'Refund / ombooking';
$rerouteTooltip = $delayRefundOnly
    ? 'Ved flyforsinkelse paa 5+ timer kan passageren kraeve billetrefusion efter Article 8(1)(a). Dette aabner ikke i sig selv et almindeligt ombookingsspor.'
    : (string)($transportCaps['tooltips']['reroute'] ?? 'Refund og ombooking vurderes konkret efter lov og noedvendighed.');
$opsStatus = trim((string)($opsEvidence['status'] ?? ''));
$opsSource = strtoupper(trim((string)($opsEvidence['source'] ?? '')));
$opsConfidence = trim((string)($opsEvidence['confidence'] ?? ''));
$opsScore = isset($opsEvidence['evidence_score']) ? (int)$opsEvidence['evidence_score'] : null;
$opsLabel = $opsStatus !== '' ? $opsStatus : ($opsSource !== '' ? 'Ops data klar' : 'Ingen ops data');
$opsDetailParts = array_filter([
    $opsSource !== '' ? $opsSource : null,
    $opsScore !== null && $opsScore > 0 ? ('score ' . $opsScore) : null,
    $opsConfidence !== '' ? $opsConfidence : null,
]);
$expenseLocationLabel = trim((string)($airExpenseReview['expense_airport_iata'] ?? ''));
if ($expenseLocationLabel === '') {
    $expenseLocationLabel = trim((string)($airExpenseReview['expense_airport_label'] ?? ''));
}
$expenseCountryCode = strtoupper(trim((string)($airExpenseReview['expense_country_code'] ?? '')));
$expenseLocationDisplay = $expenseLocationLabel;
if ($expenseLocationDisplay !== '' && $expenseCountryCode !== '') {
    $expenseLocationDisplay .= ', ' . $expenseCountryCode;
}
$expenseLocationZone = strtolower(trim((string)($airExpenseReview['airport_cost_zone'] ?? '')));
$expenseLocationConfidence = strtolower(trim((string)($airExpenseReview['location_confidence'] ?? '')));
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
  .air-live-estimate { margin-top:10px; padding:12px; border:1px solid #dbeafe; border-radius:8px; background:#f8fbff; }
  .air-live-estimate-head { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap; }
  .air-live-estimate-status { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; }
  .air-live-estimate-amount-block { margin-top:10px; }
  .air-live-estimate-amount { font-size:28px; font-weight:800; line-height:1; color:#0f172a; }
  .air-live-estimate-sub { color:#475569; font-size:12px; }
  .air-live-estimate-route { margin-top:10px; color:#334155; font-size:13px; }
  .air-live-estimate-route strong { color:#0f172a; }
  .air-live-estimate-route-meta { margin-top:4px; color:#64748b; font-size:12px; }
  .air-live-estimate-primary { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .air-live-estimate-grid { margin-top:10px; display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:8px; }
  .air-live-estimate-cell { padding:8px; border-radius:6px; background:#fff; border:1px solid #e2e8f0; }
  .air-live-estimate-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .air-live-estimate-label-row { display:flex; align-items:center; gap:6px; }
  .air-live-estimate-value { margin-top:4px; font-weight:700; color:#1e293b; }
  .air-live-estimate-tooltip { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; font-size:11px; font-weight:700; cursor:help; }
  .air-live-estimate-details { margin-top:10px; border-top:1px solid #dbeafe; padding-top:10px; }
  .air-live-estimate-details summary { display:flex; align-items:center; gap:8px; color:#0f172a; font-weight:700; cursor:pointer; list-style:none; }
  .air-live-estimate-details summary::-webkit-details-marker { display:none; }
  .air-live-estimate-details summary::after { content:'Vis'; color:#64748b; font-size:12px; font-weight:500; }
  .air-live-estimate-details[open] summary::after { content:'Skjul'; }
</style>

<div
  id="airLiveEstimate"
  class="air-live-estimate tc6-live-estimate tc6-live-estimate--<?= h($panelTone) ?>"
  data-passenger-count="<?= h((string)$passengerCount) ?>"
  data-potential-total="<?= h(number_format($potentialTotalAmount, 2, '.', '')) ?>"
  data-reduction-threshold="<?= h((string)$reductionThreshold) ?>"
  data-has-known-distance="<?= $hasKnownDistanceBand ? '1' : '0' ?>"
  data-travel-state="<?= h($travelState) ?>"
  data-live-tone="<?= h($panelTone) ?>"
>
  <div class="air-live-estimate-head">
    <div>
      <div><strong>Live air-estimat</strong></div>
    </div>
    <div id="airLiveEstimateStatus" class="air-live-estimate-status tc6-live-chip tc6-live-chip--<?= h($statusTone) ?>" style="<?= h($chipToneStyle($statusTone)) ?>"><?= h($statusText) ?></div>
  </div>

  <div class="air-live-estimate-amount-block">
    <div id="airLiveEstimateAmount" class="air-live-estimate-amount">
      <?= $displayAmount !== null ? h(number_format($displayAmount, 2, '.', ',')) . ' EUR' : 'Afventer' ?>
    </div>
  </div>

  <?php if ($routeLabel !== ''): ?>
    <div class="air-live-estimate-route">
      <div><strong><?= h($routeLabel) ?></strong></div>
    </div>
  <?php endif; ?>
  <?php if ($expenseLocationDisplay !== ''): ?>
    <div class="air-live-estimate-route-meta">
      Expense location: <?= h($expenseLocationDisplay) ?><?= $expenseLocationZone !== '' ? ' (' . h($expenseLocationZone) . ')' : '' ?>
    </div>
  <?php endif; ?>
  <?php if ($expenseLocationConfidence === 'low'): ?>
    <div class="air-live-estimate-route-meta">Vi har estimeret ud fra den mest sandsynlige lufthavn. Du kan rette placeringen senere, hvis den ikke passer.</div>
  <?php endif; ?>

  <?php if ($showPrimaryCards): ?>
  <div class="air-live-estimate-primary">
    <div class="air-live-estimate-cell tc6-live-card tc6-live-card--<?= h($careTone) ?>" data-air-live-card="care" style="<?= h($cardToneStyle($careTone)) ?>">
      <div class="air-live-estimate-label-row">
        <div class="air-live-estimate-label">Assistance</div>
        <span class="air-live-estimate-tooltip" title="<?= h($careTooltip) ?>" aria-label="<?= h($careTooltip) ?>">i</span>
      </div>
      <div id="airLiveEstimateCareValue" class="air-live-estimate-value"><?= $previewCareActive ? 'Rimelige noedvendige udgifter kan daekkes' : 'Afventer flere svar' ?></div>
    </div>
    <div class="air-live-estimate-cell tc6-live-card tc6-live-card--<?= h($remedyTone) ?>" data-air-live-card="remedy" style="<?= h($cardToneStyle($remedyTone)) ?>">
      <div class="air-live-estimate-label-row">
        <div class="air-live-estimate-label"><?= h($remedyLabel) ?></div>
        <span class="air-live-estimate-tooltip" title="<?= h($rerouteTooltip) ?>" aria-label="<?= h($rerouteTooltip) ?>">i</span>
      </div>
      <div id="airLiveEstimateRemedyValue" class="air-live-estimate-value"><?= $previewRemedyActive ? 'Fuld daekning mulig' : 'Afventer flere svar' ?></div>
    </div>
    <div class="air-live-estimate-cell tc6-live-card tc6-live-card--<?= h($carrierTone) ?>" data-air-live-card="carrier" style="<?= h($cardToneStyle($carrierTone)) ?>">
      <div class="air-live-estimate-label">Ansvarligt flyselskab</div>
      <div class="air-live-estimate-value"><?= h($responsibleCarrierLabel) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showTechnicalDetails): ?>
  <details class="air-live-estimate-details">
    <summary>Tekniske detaljer</summary>
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
        <div class="air-live-estimate-label">Claim-kanal</div>
        <div class="air-live-estimate-value"><?= h($claimChannelLabel) ?></div>
      </div>
      <div class="air-live-estimate-cell">
        <div class="air-live-estimate-label">Flydistance</div>
        <div class="air-live-estimate-value"><?= h($flightDistanceKm !== '' ? ($flightDistanceKm . ' km') : 'Ikke afledt endnu') ?></div>
      </div>
      <div class="air-live-estimate-cell">
        <div class="air-live-estimate-label">Expense location</div>
        <div class="air-live-estimate-value"><?= h($expenseLocationDisplay !== '' ? $expenseLocationDisplay : 'Afventer') ?></div>
        <?php if ($expenseLocationZone !== ''): ?>
          <div class="air-live-estimate-sub" style="margin-top:4px;"><?= h('Zone: ' . $expenseLocationZone . ' · confidence: ' . ($expenseLocationConfidence !== '' ? $expenseLocationConfidence : 'low')) ?></div>
        <?php endif; ?>
      </div>
      <div class="air-live-estimate-cell">
        <div class="air-live-estimate-label">Ops status</div>
        <div class="air-live-estimate-value"><?= h($opsLabel) ?></div>
        <?php if ($opsDetailParts !== []): ?>
          <div class="air-live-estimate-sub" style="margin-top:4px;"><?= h(implode(' · ', $opsDetailParts)) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </details>
  <?php endif; ?>
</div>
