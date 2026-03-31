<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$gatingMode = strtolower((string)($form['gating_mode'] ?? ($meta['gating_mode'] ?? ($flags['gating_mode'] ?? $transportMode))));
 $needsRouter = ((string)($flags['needs_initial_incident_router'] ?? '')) === '1';
if (!in_array($gatingMode, ['rail', 'ferry', 'bus', 'air'], true)) {
  $gatingMode = $transportMode;
}
$isFerry = ($gatingMode === 'ferry');
$isBus = ($gatingMode === 'bus');
$isAir = ($gatingMode === 'air');
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$bikeHint = $isOngoing ? 'Svar ud fra det, der er sket indtil nu.' : ($isCompleted ? 'Svar ud fra hvad der faktisk skete.' : '');
$pmrHint = $isOngoing ? 'Har du faaet den assistance, du har brug for indtil nu?' : ($isCompleted ? 'Fik du den assistance, du havde ret til?' : '');

$bikeAutoDetected = !empty($meta['_auto']['bike_booked']) || !empty($meta['_bike_detection']);
$pmrAutoDetected = !empty($meta['_auto']['pmr_user']) || !empty($meta['_pmr_detection']) || !empty($meta['_pmr_detected']);
$profile = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);
$art9On = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;
$art93On = ($articles['art9_3'] ?? ($articles['art9'] ?? true)) !== false;
$routerType = (string)($routerType ?? ($form['initial_incident_router_type'] ?? ($meta['initial_incident_router_type'] ?? 'none')));
$routerCandidates = (array)($routerCandidates ?? ($meta['initial_incident_candidates'] ?? []));
$pmrCompanion = strtolower((string)($form['pmr_companion'] ?? ($meta['pmr_companion'] ?? 'no')));
$pmrServiceDog = strtolower((string)($form['pmr_service_dog'] ?? ($meta['pmr_service_dog'] ?? 'no')));
$unaccompaniedMinor = strtolower((string)($form['unaccompanied_minor'] ?? ($meta['unaccompanied_minor'] ?? 'no')));
$ferryPmrCompanion = strtolower((string)($form['ferry_pmr_companion'] ?? ($meta['ferry_pmr_companion'] ?? 'no')));
$ferryPmrServiceDog = strtolower((string)($form['ferry_pmr_service_dog'] ?? ($meta['ferry_pmr_service_dog'] ?? 'no')));
$ferryPmrNotice48h = strtolower((string)($form['ferry_pmr_notice_48h'] ?? ($meta['ferry_pmr_notice_48h'] ?? 'no')));
$ferryPmrMetCheckinTime = strtolower((string)($form['ferry_pmr_met_checkin_time'] ?? ($meta['ferry_pmr_met_checkin_time'] ?? 'no')));
$ferryPmrAssistanceDelivered = strtolower((string)($form['ferry_pmr_assistance_delivered'] ?? ($meta['ferry_pmr_assistance_delivered'] ?? 'unknown')));
if (!in_array($ferryPmrAssistanceDelivered, ['full', 'partial', 'none', 'unknown'], true)) {
  $ferryPmrAssistanceDelivered = 'unknown';
}
$ferryPmrBoardingRefused = strtolower((string)($form['ferry_pmr_boarding_refused'] ?? ($meta['ferry_pmr_boarding_refused'] ?? 'no')));
$ferryPmrRefusalBasis = strtolower((string)($form['ferry_pmr_refusal_basis'] ?? ($meta['ferry_pmr_refusal_basis'] ?? 'other_or_unknown')));
if (!in_array($ferryPmrRefusalBasis, ['safety_requirements', 'port_or_ship_infrastructure', 'other_or_unknown'], true)) {
  $ferryPmrRefusalBasis = 'other_or_unknown';
}
$ferryPmrReasonGiven = strtolower((string)($form['ferry_pmr_reason_given'] ?? ($meta['ferry_pmr_reason_given'] ?? 'no')));
$ferryPmrAlternativeTransportOffered = strtolower((string)($form['ferry_pmr_alternative_transport_offered'] ?? ($meta['ferry_pmr_alternative_transport_offered'] ?? 'no')));
$busPmrCompanion = strtolower((string)($form['bus_pmr_companion'] ?? ($meta['bus_pmr_companion'] ?? 'no')));
$busPmrNotice36h = strtolower((string)($form['bus_pmr_notice_36h'] ?? ($meta['bus_pmr_notice_36h'] ?? 'no')));
$busPmrMetTerminalTime = strtolower((string)($form['bus_pmr_met_terminal_time'] ?? ($meta['bus_pmr_met_terminal_time'] ?? 'no')));
$busPmrSpecialSeatingNotified = strtolower((string)($form['bus_pmr_special_seating_notified'] ?? ($meta['bus_pmr_special_seating_notified'] ?? 'no')));
$busPmrAssistanceDelivered = strtolower((string)($form['bus_pmr_assistance_delivered'] ?? ($meta['bus_pmr_assistance_delivered'] ?? 'unknown')));
if (!in_array($busPmrAssistanceDelivered, ['full', 'partial', 'none', 'unknown'], true)) {
  $busPmrAssistanceDelivered = 'unknown';
}
$busPmrBoardingRefused = strtolower((string)($form['bus_pmr_boarding_refused'] ?? ($meta['bus_pmr_boarding_refused'] ?? 'no')));
$busPmrRefusalBasis = strtolower((string)($form['bus_pmr_refusal_basis'] ?? ($meta['bus_pmr_refusal_basis'] ?? 'other_or_unknown')));
if (!in_array($busPmrRefusalBasis, ['safety_requirements', 'impossible_infrastructure', 'other_or_unknown'], true)) {
  $busPmrRefusalBasis = 'other_or_unknown';
}
$busPmrReasonGiven = strtolower((string)($form['bus_pmr_reason_given'] ?? ($meta['bus_pmr_reason_given'] ?? 'no')));
$busPmrAlternativeTransportOffered = strtolower((string)($form['bus_pmr_alternative_transport_offered'] ?? ($meta['bus_pmr_alternative_transport_offered'] ?? 'no')));
$initialIncidentMode = strtolower((string)($form['initial_incident_mode'] ?? ($meta['initial_incident_mode'] ?? $transportMode)));
if (!in_array($initialIncidentMode, ['rail', 'ferry', 'bus', 'air', 'unknown'], true)) {
  $initialIncidentMode = $transportMode;
}
$initialIncidentContractKey = (string)($form['initial_incident_contract_key'] ?? ($meta['initial_incident_contract_key'] ?? ''));
$initialIncidentSegmentKey = (string)($form['initial_incident_segment_key'] ?? ($meta['initial_incident_segment_key'] ?? ''));

$v = fn(string $k): string => (string)($form[$k] ?? '');
$isPreview = !empty($flowPreview);
?>

<style>
  .card { padding: 12px; border: 1px solid #ddd; border-radius: 6px; background:#fff; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
  .hidden { display:none; }
  .hide-bike-delay { display:none !important; }
  [data-show-if] { display:none; }
</style>

<?php if ($isFerry && $isOngoing): ?>
  <h1>TRIN 4 - PMR / handicap (faerge, igangvaerende rejse)</h1>
<?php elseif ($isFerry && $isCompleted): ?>
  <h1>TRIN 4 - PMR / handicap (faerge, afsluttet rejse)</h1>
<?php elseif ($isFerry): ?>
  <h1>TRIN 4 - PMR / handicap (faerge)</h1>
<?php elseif ($isBus): ?>
  <h1>TRIN 4 - PMR / handicap (bus)</h1>
<?php elseif ($isAir): ?>
  <h1>TRIN 4 - Saerlige behov og prioriteret assistance (fly)</h1>
<?php elseif ($isOngoing): ?>
  <h1>TRIN 4 - Bekraeft rejse og forsinkelse (igangvaerende rejse)</h1>
<?php elseif ($isCompleted): ?>
  <h1>TRIN 4 - Bekraeft hvad der skete paa rejsen</h1>
<?php else: ?>
  <h1>TRIN 4 - Bekraeft rejse og forsinkelse</h1>
<?php endif; ?>

<?php if (!$isFerry && !empty($contractWarning ?? '')): ?>
  <div class="card hl" style="border:1px solid #f5c2c7; background:#fff5f5; margin-bottom:8px;">
    <div class="small" style="color:#a71d2a;"><?= h($contractWarning) ?></div>
  </div>
<?php endif; ?>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<?php if (!$isFerry && !$isBus && !$isAir): ?>
<div class="card mt12">
  <strong>TRIN 4a - Cykel og bagage (Art.6)</strong>
  <p class="small muted">Svarene her aktiverer Art.18/20 ved cykel-problemer.<?= ($bikeHint !== '') ? (' ' . h($bikeHint)) : '' ?></p>
  <?php if ($bikeAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at naevne cykel. Valget er stadig sat til "Nej" som udgangspunkt - ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Havde du en cykel med paa rejsen?</div>
    <label><input type="radio" name="bike_was_present" value="yes" <?= $v('bike_was_present') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_was_present" value="no" <?= $v('bike_was_present') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4 hide-bike-delay <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_was_present:yes" data-art="9(2)">
    <div>2. Forsinkede cyklen eller dens haandtering dig?</div>
    <label><input type="radio" name="bike_delay" value="yes" <?= $v('bike_delay') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_delay" value="no" <?= $v('bike_delay') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="bike_was_present:yes" data-art="9(1)">
    <div>2. Havde du reserveret plads til cyklen?</div>
    <label><input type="radio" name="bike_reservation_made" value="yes" <?= $v('bike_reservation_made') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_made" value="no" <?= $v('bike_reservation_made') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="bike_reservation_made:no" data-art="9(1)">
    <div>2B. Var det et tog, hvor der kraevedes cykelreservation?</div>
    <label><input type="radio" name="bike_reservation_required" value="yes" <?= $v('bike_reservation_required') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_reservation_required" value="no" <?= $v('bike_reservation_required') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div id="bikeAfter2B" class="mt4" data-show-if="bike_was_present:yes">
    <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-art="9(2)">
      <div>3. Blev du naegtet at tage cyklen med?</div>
      <label><input type="radio" name="bike_denied_boarding" value="yes" <?= $v('bike_denied_boarding') === 'yes' ? 'checked' : '' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="bike_denied_boarding" value="no" <?= $v('bike_denied_boarding') === 'no' ? 'checked' : '' ?> /> Nej</label>
    </div>

    <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_denied_boarding:yes" data-art="9(2)">
      <div>4. Informerede operatoeren dig om aarsagen?</div>
      <label><input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $v('bike_refusal_reason_provided') === 'yes' ? 'checked' : '' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="bike_refusal_reason_provided" value="no" <?= $v('bike_refusal_reason_provided') === 'no' ? 'checked' : '' ?> /> Nej</label>
    </div>

    <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="bike_refusal_reason_provided:yes" data-art="9(2)">
      <div>5. Hvad var begrundelsen for afvisningen?</div>
      <select name="bike_refusal_reason_type">
        <option value="">- vaelg -</option>
        <option value="capacity" <?= $v('bike_refusal_reason_type') === 'capacity' ? 'selected' : '' ?>>Kapacitet</option>
        <option value="equipment" <?= $v('bike_refusal_reason_type') === 'equipment' ? 'selected' : '' ?>>Materiel tillader det ikke</option>
        <option value="weight_dim" <?= $v('bike_refusal_reason_type') === 'weight_dim' ? 'selected' : '' ?>>Vaegt/dimensioner</option>
        <option value="other" <?= $v('bike_refusal_reason_type') === 'other' ? 'selected' : '' ?>>Andet</option>
      </select>
    </div>

    <div class="mt4" data-show-if="bike_refusal_reason_type:other">
      <label class="small">Beskriv kort</label>
      <textarea name="bike_refusal_reason_other_text" rows="2"><?= h($v('bike_refusal_reason_other_text')) ?></textarea>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!$isBus && !$isAir && !$isFerry): ?>
<div class="card mt12">
  <strong>TRIN 4b - PMR / handicap</strong>
  <p class="small muted">
    Hvis bestilt hjaelp ikke blev leveret, kan Art.18/20 aktiveres automatisk.<?= ($pmrHint !== '') ? (' ' . h($pmrHint)) : '' ?>
  </p>
  <?php if ($pmrAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at naevne handicap/PMR. Valget er stadig sat til "Nej" som udgangspunkt - ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Har du et handicap eller nedsat mobilitet, som kraevede assistance?</div>
    <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art91On ? '' : 'hidden' ?>" data-show-if="pmr_user:yes" data-art="9(1)">
    <div>2. Bestilte du assistance foer rejsen?</div>
    <label><input type="radio" name="pmr_booked" value="yes" <?= $v('pmr_booked') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_booked" value="no" <?= $v('pmr_booked') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-show-if="pmr_booked:yes" data-art="9(2)">
    <div>3. Blev assistancen leveret?</div>
    <label><input type="radio" name="pmr_delivered_status" value="yes" <?= $v('pmr_delivered_status') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_delivered_status" value="no" <?= $v('pmr_delivered_status') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div id="pmrQ4Wrap" class="mt4 <?= $art92On ? '' : 'hidden' ?>" data-art="9(2)">
    <div>4. Manglede der PMR-faciliteter, som var lovet foer koebet?</div>
    <label><input type="radio" name="pmr_promised_missing" value="yes" <?= $v('pmr_promised_missing') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_promised_missing" value="no" <?= $v('pmr_promised_missing') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_promised_missing:yes">
    <label class="small">Beskriv kort</label>
    <textarea name="pmr_facility_details" rows="2"><?= h($v('pmr_facility_details')) ?></textarea>
  </div>
</div>
<?php endif; ?>

<?php if ($isFerry): ?>
<div class="card mt12">
  <strong>PMR-status</strong>
  <p class="small muted">
    Bruges kun til faergerejsens PMR-spor. Almindelig ferry-gating i TRIN 5 forbliver uafhaengig.
  </p>
  <?php if ($pmrAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at naevne handicap/PMR. Valget er stadig sat til "Nej" som udgangspunkt - ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Har du et handicap eller nedsat mobilitet, som kraevede assistance?</div>
    <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Rejste du med ledsager?</div>
    <label><input type="radio" name="ferry_pmr_companion" value="yes" <?= $ferryPmrCompanion === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_companion" value="no" <?= $ferryPmrCompanion === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>3. Havde du godkendt servicehund med?</div>
    <label><input type="radio" name="ferry_pmr_service_dog" value="yes" <?= $ferryPmrServiceDog === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_service_dog" value="no" <?= $ferryPmrServiceDog === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>

<div class="card mt12" data-show-if="pmr_user:yes">
  <strong>Forhaandsbesked og assistance</strong>
  <p class="small muted">
    Bruges til at vurdere assistance i havn og om bord efter ferry-PMR-reglerne.
  </p>

  <div class="mt8" data-show-if="pmr_user:yes">
    <div>1. Gav du besked mindst 48 timer foer rejsen om behovet for assistance?</div>
    <label><input type="radio" name="ferry_pmr_notice_48h" value="yes" <?= $ferryPmrNotice48h === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_notice_48h" value="no" <?= $ferryPmrNotice48h === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Moedte du paa det oplyste tidspunkt for assistance / check-in?</div>
    <label><input type="radio" name="ferry_pmr_met_checkin_time" value="yes" <?= $ferryPmrMetCheckinTime === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_met_checkin_time" value="no" <?= $ferryPmrMetCheckinTime === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>3. Hvordan blev assistancen leveret?</div>
    <label><input type="radio" name="ferry_pmr_assistance_delivered" value="full" <?= $ferryPmrAssistanceDelivered === 'full' ? 'checked' : '' ?> /> Fuldt leveret</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_assistance_delivered" value="partial" <?= $ferryPmrAssistanceDelivered === 'partial' ? 'checked' : '' ?> /> Delvist leveret</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_assistance_delivered" value="none" <?= $ferryPmrAssistanceDelivered === 'none' ? 'checked' : '' ?> /> Ikke leveret</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_assistance_delivered" value="unknown" <?= $ferryPmrAssistanceDelivered === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
  </div>
</div>

<div class="card mt12" data-show-if="pmr_user:yes">
  <strong>Naegtet reservation / boarding</strong>
  <p class="small muted">
    Bruges kun til ferry-PMR-sporet om naegtet reservation eller indskibning.
  </p>

  <div class="mt8" data-show-if="pmr_user:yes">
    <div>1. Blev du naegtet reservation eller boarding paa grund af handicap / nedsat mobilitet?</div>
    <label><input type="radio" name="ferry_pmr_boarding_refused" value="yes" <?= $ferryPmrBoardingRefused === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_boarding_refused" value="no" <?= $ferryPmrBoardingRefused === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="ferry_pmr_boarding_refused:yes">
    <div>2. Hvad sagde transportoeren var begrundelsen?</div>
    <select name="ferry_pmr_refusal_basis">
      <option value="safety_requirements" <?= $ferryPmrRefusalBasis === 'safety_requirements' ? 'selected' : '' ?>>Sikkerhedskrav</option>
      <option value="port_or_ship_infrastructure" <?= $ferryPmrRefusalBasis === 'port_or_ship_infrastructure' ? 'selected' : '' ?>>Havnens eller skibets indretning</option>
      <option value="other_or_unknown" <?= $ferryPmrRefusalBasis === 'other_or_unknown' ? 'selected' : '' ?>>Andet / ved ikke</option>
    </select>
  </div>

  <div class="mt4" data-show-if="ferry_pmr_boarding_refused:yes">
    <div>3. Fik du en klar begrundelse skriftligt eller mundtligt?</div>
    <label><input type="radio" name="ferry_pmr_reason_given" value="yes" <?= $ferryPmrReasonGiven === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_reason_given" value="no" <?= $ferryPmrReasonGiven === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="ferry_pmr_boarding_refused:yes">
    <div>4. Tilboed transportoeren alternativ befordring eller anden loesning?</div>
    <label><input type="radio" name="ferry_pmr_alternative_transport_offered" value="yes" <?= $ferryPmrAlternativeTransportOffered === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="ferry_pmr_alternative_transport_offered" value="no" <?= $ferryPmrAlternativeTransportOffered === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>
<?php endif; ?>

<?php if ($isBus): ?>
<div class="card mt12">
  <strong>PMR-status</strong>
  <p class="small muted">
    Bruges kun til busforordningens PMR-spor. Almindelig bus-gating i TRIN 5 forbliver uafhaengig.
  </p>
  <?php if ($pmrAutoDetected): ?>
    <div class="small muted mt4">Auto-note: Billet/OCR ser ud til at naevne handicap/PMR. Valget er stadig sat til "Nej" som udgangspunkt - ret hvis det er forkert.</div>
  <?php endif; ?>

  <div class="mt8">
    <div>1. Har du et handicap eller nedsat mobilitet, som kraevede assistance?</div>
    <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Rejste du med ledsager?</div>
    <label><input type="radio" name="bus_pmr_companion" value="yes" <?= $busPmrCompanion === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_companion" value="no" <?= $busPmrCompanion === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>

<div class="card mt12" data-show-if="pmr_user:yes">
  <strong>Forhaandsbesked og assistance</strong>
  <p class="small muted">
    Bruges til at vurdere assistance ved terminal og om bord efter busforordningens Art. 13-15.
  </p>

  <div class="mt8" data-show-if="pmr_user:yes">
    <div>1. Gav du besked mindst 36 timer foer rejsen om behovet for assistance?</div>
    <label><input type="radio" name="bus_pmr_notice_36h" value="yes" <?= $busPmrNotice36h === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_notice_36h" value="no" <?= $busPmrNotice36h === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Moedte du paa det oplyste assistance/check-in-tidspunkt?</div>
    <label><input type="radio" name="bus_pmr_met_terminal_time" value="yes" <?= $busPmrMetTerminalTime === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_met_terminal_time" value="no" <?= $busPmrMetTerminalTime === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>3. Meddelte du saerlige behov for siddeplads eller placering ved reservation/koeb?</div>
    <label><input type="radio" name="bus_pmr_special_seating_notified" value="yes" <?= $busPmrSpecialSeatingNotified === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_special_seating_notified" value="no" <?= $busPmrSpecialSeatingNotified === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>4. Hvordan blev assistancen leveret?</div>
    <label><input type="radio" name="bus_pmr_assistance_delivered" value="full" <?= $busPmrAssistanceDelivered === 'full' ? 'checked' : '' ?> /> Fuldt leveret</label>
    <label class="ml8"><input type="radio" name="bus_pmr_assistance_delivered" value="partial" <?= $busPmrAssistanceDelivered === 'partial' ? 'checked' : '' ?> /> Delvist leveret</label>
    <label class="ml8"><input type="radio" name="bus_pmr_assistance_delivered" value="none" <?= $busPmrAssistanceDelivered === 'none' ? 'checked' : '' ?> /> Ikke leveret</label>
    <label class="ml8"><input type="radio" name="bus_pmr_assistance_delivered" value="unknown" <?= $busPmrAssistanceDelivered === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
  </div>
</div>

<div class="card mt12" data-show-if="pmr_user:yes">
  <strong>Naegtet reservation / boarding</strong>
  <p class="small muted">
    Bruges kun til bus-PMR-sporet om naegtet reservation, billet eller boarding efter Art. 9-10.
  </p>

  <div class="mt8" data-show-if="pmr_user:yes">
    <div>1. Blev du naegtet reservation, billet eller boarding paa grund af handicap / nedsat mobilitet?</div>
    <label><input type="radio" name="bus_pmr_boarding_refused" value="yes" <?= $busPmrBoardingRefused === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_boarding_refused" value="no" <?= $busPmrBoardingRefused === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bus_pmr_boarding_refused:yes">
    <div>2. Hvad sagde transportoeren var begrundelsen?</div>
    <select name="bus_pmr_refusal_basis">
      <option value="safety_requirements" <?= $busPmrRefusalBasis === 'safety_requirements' ? 'selected' : '' ?>>Sikkerhedskrav</option>
      <option value="impossible_infrastructure" <?= $busPmrRefusalBasis === 'impossible_infrastructure' ? 'selected' : '' ?>>Bus, stoppested eller terminal gjorde det fysisk umuligt</option>
      <option value="other_or_unknown" <?= $busPmrRefusalBasis === 'other_or_unknown' ? 'selected' : '' ?>>Andet / ved ikke</option>
    </select>
  </div>

  <div class="mt4" data-show-if="bus_pmr_boarding_refused:yes">
    <div>3. Fik du en klar begrundelse skriftligt eller mundtligt?</div>
    <label><input type="radio" name="bus_pmr_reason_given" value="yes" <?= $busPmrReasonGiven === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_reason_given" value="no" <?= $busPmrReasonGiven === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="bus_pmr_boarding_refused:yes">
    <div>4. Tilboed transportoeren rimelig alternativ befordring eller anden loesning?</div>
    <label><input type="radio" name="bus_pmr_alternative_transport_offered" value="yes" <?= $busPmrAlternativeTransportOffered === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bus_pmr_alternative_transport_offered" value="no" <?= $busPmrAlternativeTransportOffered === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>
<?php endif; ?>

<?php if ($isAir): ?>
<div class="card mt12">
  <strong>Air-forloeb</strong>
  <p class="small muted">Dette trin samler saerlige behov og eventuel foerste ramte del af rejsen foer incident-gating i TRIN 5.</p>
</div>

<?php if ($routerType !== 'none'): ?>
<div class="card mt12">
  <strong>Foerste ramte segment</strong>
  <p class="small muted">
    Bruges kun hvis sagen skal routes til et andet spor end den oprindelige transportmaade.
  </p>

  <div class="mt12">
    <?php if ($routerType === 'contract'): ?>
      <div>Hvilken kontrakt eller billet blev foerst ramt af problemet?</div>
      <?php foreach ($routerCandidates as $candidate): ?>
        <?php if (!is_array($candidate)) { continue; } ?>
        <div class="mt8">
          <label>
            <input type="radio" name="initial_incident_contract_key" value="<?= h((string)($candidate['key'] ?? '')) ?>" <?= $initialIncidentContractKey === (string)($candidate['key'] ?? '') ? 'checked' : '' ?> />
            <?= h((string)($candidate['label'] ?? '')) ?>
          </label>
        </div>
      <?php endforeach; ?>
      <div class="mt8">
        <label><input type="radio" name="initial_incident_contract_key" value="" <?= $initialIncidentContractKey === '' ? 'checked' : '' ?> /> Ved ikke</label>
      </div>
    <?php elseif ($routerType === 'contract_segment'): ?>
      <div>Hvilken konkrete del af rejsen eller kontrakt blev foerst ramt af problemet?</div>
      <?php foreach ($routerCandidates as $candidate): ?>
        <?php if (!is_array($candidate)) { continue; } ?>
        <div class="mt8">
          <label>
            <input type="radio" name="initial_incident_segment_key" value="<?= h((string)($candidate['key'] ?? '')) ?>" <?= $initialIncidentSegmentKey === (string)($candidate['key'] ?? '') ? 'checked' : '' ?> />
            <?= h((string)($candidate['label'] ?? '')) ?>
          </label>
        </div>
      <?php endforeach; ?>
      <div class="mt8">
        <label><input type="radio" name="initial_incident_segment_key" value="unknown" <?= $initialIncidentSegmentKey === '' ? 'checked' : '' ?> /> Ved ikke</label>
      </div>
    <?php else: ?>
      <div>Hvilken del af rejsen blev foerst ramt af problemet?</div>
      <label><input type="radio" name="initial_incident_mode" value="rail" <?= $initialIncidentMode === 'rail' ? 'checked' : '' ?> /> Tog</label>
      <label class="ml8"><input type="radio" name="initial_incident_mode" value="ferry" <?= $initialIncidentMode === 'ferry' ? 'checked' : '' ?> /> Faerge / havn / terminal</label>
      <label class="ml8"><input type="radio" name="initial_incident_mode" value="bus" <?= $initialIncidentMode === 'bus' ? 'checked' : '' ?> /> Bus</label>
      <label class="ml8"><input type="radio" name="initial_incident_mode" value="air" <?= $initialIncidentMode === 'air' ? 'checked' : '' ?> /> Fly / lufthavn / boarding</label>
      <label class="ml8"><input type="radio" name="initial_incident_mode" value="unknown" <?= $initialIncidentMode === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card mt12">
  <strong>PMR / saerlig assistance (Art. 11)</strong>
  <p class="small muted">
    Bruges til at vurdere prioriteret transport og care saa hurtigt som muligt ved boardingafvisning, aflysning eller forsinkelse.
  </p>

  <div class="mt8">
    <div>1. Har den rejsende nedsat mobilitet eller behov for saerlig assistance?</div>
    <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user') === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user') === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>2. Rejste den paagaeldende med ledsager?</div>
    <label><input type="radio" name="pmr_companion" value="yes" <?= $pmrCompanion === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_companion" value="no" <?= $pmrCompanion === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>

  <div class="mt4" data-show-if="pmr_user:yes">
    <div>3. Var der en godkendt servicehund med?</div>
    <label><input type="radio" name="pmr_service_dog" value="yes" <?= $pmrServiceDog === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="pmr_service_dog" value="no" <?= $pmrServiceDog === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>

<div class="card mt12">
  <strong>Uledsaget barn (Art. 11)</strong>
  <p class="small muted">
    Uledsagede boern har ved boardingafvisning, aflysning eller forsinkelse ret til forplejning og indkvartering saa hurtigt som muligt.
  </p>

  <div class="mt8">
    <div>1. Var den rejsende et uledsaget barn?</div>
    <label><input type="radio" name="unaccompanied_minor" value="yes" <?= $unaccompaniedMinor === 'yes' ? 'checked' : '' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="unaccompanied_minor" value="no" <?= $unaccompaniedMinor === 'no' ? 'checked' : '' ?> /> Nej</label>
  </div>
</div>
<?php endif; ?>

<div class="mt12" style="display:flex; gap:8px; align-items:center;">
  <?= $this->Html->link('<- Tilbage', ['action' => $gatingMode === 'rail' ? 'railstranding' : (($transportMode !== 'air' && $needsRouter) ? 'station' : 'entitlements')], ['class' => 'button', 'style' => 'background:#eee; color:#333;', 'escape' => false]) ?>
  <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
</div>

</fieldset>
<?= $this->Form->end() ?>

<div id="hooksPanel" class="card mt12">
  <div class="small muted">Indlaeser hooks...</div>
</div>

<script>
function loadHooksPanel() {
  var panel = document.getElementById('hooksPanel');
  if (!panel || panel.getAttribute('data-loading') === '1') return;
  panel.setAttribute('data-loading', '1');
  var url = new URL(window.location.href);
  url.searchParams.set('ajax_hooks', '1');
  fetch(url.toString(), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  }).then(function(resp) {
    return resp.text();
  }).then(function(txt) {
    panel.innerHTML = txt;
    panel.setAttribute('data-loaded', '1');
  }).catch(function() {
    panel.innerHTML = '<div class="small muted">Hooks kunne ikke indlaeses.</div>';
  }).finally(function() {
    panel.setAttribute('data-loading', '0');
  });
}

function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if');
    if (!spec) return;
    var parts = spec.split(':');
    if (parts.length !== 2) return;
    var name = parts[0];
    var valid = parts[1].split(',');
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    var value = checked ? checked.value : '';
    if (!value) {
      var select = document.querySelector('select[name="' + name + '"]');
      if (select) value = select.value;
    }
    var show = value && valid.includes(value);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });

  var after2b = document.getElementById('bikeAfter2B');
  if (after2b) {
    var present = document.querySelector('input[name="bike_was_present"]:checked');
    var presVal = present ? present.value : '';
    var resMade = document.querySelector('input[name="bike_reservation_made"]:checked');
    var resVal = resMade ? resMade.value : '';
    var req = document.querySelector('input[name="bike_reservation_required"]:checked');
    var reqVal = req ? req.value : '';
    var show = (presVal === 'yes') && (resVal === 'yes' || (resVal === 'no' && reqVal === 'no'));
    after2b.style.display = show ? 'block' : 'none';
    after2b.hidden = !show;
  }

  var pmrQ4 = document.getElementById('pmrQ4Wrap');
  if (pmrQ4) {
    var booked = document.querySelector('input[name="pmr_booked"]:checked');
    var bookedVal = booked ? booked.value : '';
    var delivered = document.querySelector('input[name="pmr_delivered_status"]:checked');
    var delVal = delivered ? delivered.value : '';
    var showQ4 = (bookedVal === 'no') || (bookedVal === 'yes' && (delVal === 'yes' || delVal === 'no'));
    if (pmrQ4.classList.contains('hidden')) showQ4 = false;
    pmrQ4.style.display = showQ4 ? 'block' : 'none';
    pmrQ4.hidden = !showQ4;
  }
}

function setRadioChoice(name, value) {
  document.querySelectorAll('input[name="' + name + '"]').forEach(function(node) {
    node.checked = (node.value === value);
  });
}

function setSelectChoice(name, value) {
  var select = document.querySelector('select[name="' + name + '"]');
  if (select) select.value = value;
}

function resetModeSpecificPmrFields() {
  var pmr = document.querySelector('input[name="pmr_user"]:checked');
  var pmrValue = pmr ? pmr.value : '';
  if (pmrValue === 'yes') return;

  if (document.querySelector('input[name="ferry_pmr_companion"]')) {
    setRadioChoice('ferry_pmr_companion', 'no');
    setRadioChoice('ferry_pmr_service_dog', 'no');
    setRadioChoice('ferry_pmr_notice_48h', 'no');
    setRadioChoice('ferry_pmr_met_checkin_time', 'no');
    setRadioChoice('ferry_pmr_assistance_delivered', 'unknown');
    setRadioChoice('ferry_pmr_boarding_refused', 'no');
    setSelectChoice('ferry_pmr_refusal_basis', 'other_or_unknown');
    setRadioChoice('ferry_pmr_reason_given', 'no');
    setRadioChoice('ferry_pmr_alternative_transport_offered', 'no');
  }

  if (document.querySelector('input[name="bus_pmr_companion"]')) {
    setRadioChoice('bus_pmr_companion', 'no');
    setRadioChoice('bus_pmr_notice_36h', 'no');
    setRadioChoice('bus_pmr_met_terminal_time', 'no');
    setRadioChoice('bus_pmr_special_seating_notified', 'no');
    setRadioChoice('bus_pmr_assistance_delivered', 'unknown');
    setRadioChoice('bus_pmr_boarding_refused', 'no');
    setSelectChoice('bus_pmr_refusal_basis', 'other_or_unknown');
    setRadioChoice('bus_pmr_reason_given', 'no');
    setRadioChoice('bus_pmr_alternative_transport_offered', 'no');
  }

  if (document.querySelector('input[name="pmr_companion"]')) {
    setRadioChoice('pmr_companion', 'no');
    setRadioChoice('pmr_service_dog', 'no');
  }
}

document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  if (e.target.name === 'pmr_user') {
    resetModeSpecificPmrFields();
  }
  updateReveal();
});

document.addEventListener('DOMContentLoaded', function() {
  resetModeSpecificPmrFields();
  updateReveal();
  loadHooksPanel();
});
</script>
