<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];
$groupedTickets = $groupedTickets ?? [];
$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);
$euArt18Supported = ($articles['art18'] ?? true) !== false;
$euArt20Supported = ($articles['art20_2'] ?? true) !== false;
$euFlowSupported = $euArt18Supported || $euArt20Supported;
$art9On  = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$incidentHint = $isOngoing ? 'Hvad er situationen nu?' : ($isCompleted ? 'Hvad var den afgoerende haendelse?' : '');

$v = fn(string $k): string => (string)($form[$k] ?? '');
$isPreview = !empty($flowPreview);
$segCount = is_array($journey['segments'] ?? null) ? count($journey['segments']) : 0;
if ($segCount < 2) {
    $altSegs = $meta['_miss_conn_segments'] ?? ($meta['_segments_all'] ?? ($meta['_segments_llm_suggest'] ?? ($meta['_segments_auto'] ?? [])));
    if (is_array($altSegs)) { $segCount = max($segCount, count($altSegs)); }
}
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($multimodal['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail'))));
$gatingMode = strtolower((string)($form['gating_mode'] ?? ($meta['gating_mode'] ?? ($flags['gating_mode'] ?? $transportMode))));
if (!in_array($transportMode, ['rail','ferry','bus','air'], true)) { $transportMode = 'rail'; }
if (!in_array($gatingMode, ['rail','ferry','bus','air'], true)) { $gatingMode = $transportMode; }
$isFerry = $gatingMode === 'ferry';
$isBus = $gatingMode === 'bus';
$isAir = $gatingMode === 'air';
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$busRights = (array)($multimodal['bus_rights'] ?? []);
$busContract = (array)($multimodal['bus_contract'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$airRights = (array)($multimodal['air_rights'] ?? []);
$airContract = (array)($multimodal['air_contract'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ''))));
$airDistanceBandLabel = match ($airDistanceBand) {
    'up_to_1500' => '1500 km eller mindre',
    'intra_eu_over_1500' => 'Inden for EU over 1500 km',
    'other_1500_to_3500' => 'Øvrige flyvninger mellem 1500 og 3500 km',
    'other_over_3500' => 'Øvrige flyvninger over 3500 km',
    default => 'Ikke afledt endnu',
};
$airDelayThresholdHours = (int)($form['air_delay_threshold_hours'] ?? ($airScope['air_delay_threshold_hours'] ?? 0));
$airDelayThresholdLabel = $airDelayThresholdHours > 0 ? ($airDelayThresholdHours . '+ timer') : 'Ikke afledt endnu';
$airFlightDistanceKm = trim((string)($form['flight_distance_km'] ?? ($airScope['flight_distance_km'] ?? '')));
$airDelayBandValue = strtolower(trim((string)($form['delay_departure_band'] ?? '')));
$airCancellationNoticeBand = strtolower(trim((string)($form['cancellation_notice_band'] ?? '')));
$airRerouteDepartureBand = strtolower(trim((string)($form['reroute_departure_band'] ?? '')));
$airRerouteArrivalBand = strtolower(trim((string)($form['reroute_arrival_band'] ?? '')));
$airConnectionType = strtolower(trim((string)($airContract['air_connection_type'] ?? $form['air_connection_type'] ?? '')));
$airConnectionKnown = in_array($airConnectionType, ['single_flight', 'protected_connection', 'self_transfer'], true);
$airConnectionNeedsFallback = !$airConnectionKnown || !empty($airContract['manual_review_required']);
$showAirMissedConnection = $isAir && (
    $segCount > 1
    || in_array($airConnectionType, ['protected_connection', 'self_transfer'], true)
);
$missedConnectionStation = trim((string)($form['missed_connection_station'] ?? ''));
$missedConnectionPick = trim((string)($form['missed_connection_pick'] ?? ''));
$missedConnectionChosen = $missedConnectionStation !== '' || $missedConnectionPick !== '';
$airDelayBandOptions = match ($airDelayThresholdHours) {
    2 => [
        'under_threshold' => 'Under 2 timer',
        'threshold_to_under_5h' => '2-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    3 => [
        'under_threshold' => 'Under 3 timer',
        'threshold_to_under_5h' => '3-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    4 => [
        'under_threshold' => 'Under 4 timer',
        'threshold_to_under_5h' => '4-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    default => [
        'under_threshold' => 'Under den afledte threshold',
        'threshold_to_under_5h' => 'Threshold nået, men under 5 timer',
        'five_plus' => '5+ timer',
    ],
};

// National policy hint (optional) used for TRIN 5 "national fallback" UX (e.g., DK 30 min).
$nationalPolicy = $nationalPolicy ?? null;
$nationalCutoff = null;
$nationalThr50 = null;
try {
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['25'])) {
        $nationalCutoff = (int)$nationalPolicy['thresholds']['25'];
    }
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['50'])) {
        $nationalThr50 = (int)$nationalPolicy['thresholds']['50'];
    }
} catch (\Throwable $e) { $nationalCutoff = null; }

// Force majeure / extraordinary circumstances (Art. 19(10)) – affects compensation only (Art. 19).
// In ClaimCalculator: operator's own staff strikes do NOT remove compensation rights.
$exc0 = strtolower(trim((string)($form['operatorExceptionalCircumstances'] ?? '')));
$excType0 = trim((string)($form['operatorExceptionalType'] ?? ''));
$compBlockedByFM = ($exc0 === 'yes') && ($excType0 === '' || $excType0 !== 'own_staff_strike');
$showHooksPanel = (bool)$this->getRequest()->getQuery('debug');
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .hidden { display:none; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .flow-wrapper { max-width: 1080px; margin: 0 auto; }
  select, input[type="text"], input[type="number"] { max-width: 520px; width: 100%; }
  .widget-title { display:flex; align-items:center; gap:10px; font-weight:700; }
  .step-badge { width:28px; height:28px; border-radius:999px; background:#e9f2ff; border:1px solid #cfe0ff; color:#1e3a8a; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; line-height:1; flex:0 0 auto; }
  .fm-badge { width:26px; height:26px; border-radius:999px; background:#fff3cd; border:1px solid #eed27c; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; }
  .fm-badge svg { width:16px; height:16px; display:block; }
  .bus-live-shell { margin-top:10px; padding:12px; border:1px dashed #cbd5e1; border-radius:8px; background:#ffffff; }
  .bus-live-display { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
  .bus-live-minutes { font-size:28px; font-weight:800; color:#1f2937; line-height:1; }
  .bus-live-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; }
  .bus-live-badge.is-90 { background:#fff7ed; border-color:#fdba74; color:#9a3412; }
  .bus-live-badge.is-120 { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
  .bus-live-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .bus-live-actions button { background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe; border-radius:6px; padding:6px 10px; font-size:12px; font-weight:700; cursor:pointer; }
  .bus-live-actions button.bus-live-secondary { background:#f8fafc; color:#334155; border-color:#cbd5e1; }
</style>

<div class="flow-wrapper">
  <?php if ($isFerry): ?>
    <h1>TRIN 5 - Haendelse (ferry)</h1>
  <?php elseif ($isBus): ?>
    <h1>TRIN 5 - Haendelse (bus)</h1>
  <?php elseif ($isAir): ?>
    <h1>TRIN 5 - Haendelse (fly)</h1>
  <?php elseif ($isOngoing): ?>
    <h1>TRIN 5 - Forsinkelse, aflysning eller mistet forbindelse (igangvaerende rejse)</h1>
  <?php elseif ($isCompleted): ?>
    <h1>TRIN 5 - Haendelse (afsluttet rejse)</h1>
  <?php else: ?>
    <h1>TRIN 5 - Haendelse (Art. 18/20 standard gating)</h1>
  <?php endif; ?>

  <?= $this->element('flow_locked_notice') ?>
  <?= $this->Form->create(null, ['novalidate' => true, 'id' => 'incidentStepForm']) ?>
  <fieldset <?= $isPreview ? 'disabled' : '' ?>>

  <!-- Preinformed disruption (moved from TRIN 3d) -->
  <?php
    $pid = strtolower((string)$v('preinformed_disruption'));
    $pic = (string)($v('preinfo_channel'));
    $ris = (string)($v('realtime_info_seen'));
    if ($pid === '' || $pid === 'unknown') { $pid = 'no'; }
    $rtOptions = [
      'app' => 'Ja, i app',
      'train' => 'Ja, i toget',
      'station' => 'Ja, paa station',
      'no' => 'Nej',
      'unknown' => 'Ved ikke',
    ];
  ?>
  <div class="card mt12 <?= (($art91On && !$isBus && !$isAir) || $isFerry) ? '' : 'hidden' ?>" data-art="9(1)">
    <?php if ($isFerry): ?>
    <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
      <div class="widget-title">
        <span class="step-badge" aria-hidden="true">&#x23F1;</span>
        <span>Afbrydelser/forsinkelser</span>
      </div>
      <p class="small muted mt8">Default er "Nej". Udfyld kun hvis relevant.</p>

      <div class="mt8">
        <div>Var passageren informeret om aflysning/forsinkelse foer koeb?</div>
        <label><input type="radio" name="informed_before_purchase" value="yes" <?= $v('informed_before_purchase')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="informed_before_purchase" value="no" <?= $v('informed_before_purchase')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>
    </div>

    <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
      <div class="widget-title">
        <span class="step-badge" aria-hidden="true">O</span>
        <span>Aaben billet / afgangstid</span>
      </div>

      <div class="mt8">
        <div>Er det en aaben billet uden afgangstid?</div>
        <label><input type="radio" name="open_ticket_without_departure_time" value="yes" <?= $v('open_ticket_without_departure_time')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="open_ticket_without_departure_time" value="no" <?= $v('open_ticket_without_departure_time')==='no'?'checked':'' ?> /> Nej</label>
      </div>
    </div>

    <?php elseif (!$isBus && !$isAir): ?>
    <div class="mt8">
      <div class="small">Var der meddelt afbrydelse/forsinkelse foer dit koeb?</div>
      <label><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
    </div>

    <div class="mt8" data-show-if="preinformed_disruption:yes" data-art="9(1)">
      <div class="small">Hvis ja: Hvor blev det vist?</div>
      <select name="preinfo_channel">
        <option value="">- Vaelg -</option>
        <option value="website" <?= $pic==='website'?'selected':'' ?>>Hjemmeside</option>
        <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
        <option value="app" <?= $pic==='app'?'selected':'' ?>>App</option>
        <option value="station" <?= $pic==='station'?'selected':'' ?>>Station</option>
        <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8 <?= $art92On ? '' : 'hidden' ?>" data-show-if="preinformed_disruption:yes" data-art="9(2)">
      <div class="small">Saa du realtime-opdateringer under rejsen?</div>
      <?php $i = 0; foreach ($rtOptions as $key => $label): ?>
        <label class="<?= $i>0 ? 'ml8' : '' ?>"><input type="radio" name="realtime_info_seen" value="<?= h($key) ?>" <?= $ris===$key?'checked':'' ?> /> <?= h($label) ?></label>
      <?php $i++; endforeach; ?>
    </div>
    <?php endif; ?>
  </div>


  <!-- Standard gating -->
  <div class="card mt12">
    <?php if ($isFerry): ?>
      <input type="hidden" name="overnight_required" value="" />
      <input type="hidden" name="passenger_fault" value="" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">&#x26A1;</span>
          <span>Haendelse (Art. 16-19 ferry)</span>
        </div>
        <p class="small muted mt8">Bruges til ferry-gating for Art. 17, 18 og 19.</p>

        <div class="mt8">
          <div>Haendelse</div>
          <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
          <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
        </div>

        <div class="mt8" data-show-if="incident_main:delay,cancellation">
          <div>Fik du information om aflysningen eller forsinkelsen senest 30 min efter planlagt afgangstid?</div>
          <label><input type="radio" name="ferry_art16_notice_within_30min" value="yes" <?= $v('ferry_art16_notice_within_30min')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="ferry_art16_notice_within_30min" value="no" <?= $v('ferry_art16_notice_within_30min')==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="ferry_art16_notice_within_30min" value="unknown" <?= $v('ferry_art16_notice_within_30min')==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>

        <div class="mt8" data-show-if="incident_main:delay">
          <div>Forventet afgangsforsinkelse mindst 90 minutter?</div>
          <label><input type="radio" name="expected_departure_delay_90" value="yes" <?= $v('expected_departure_delay_90')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="expected_departure_delay_90" value="no" <?= $v('expected_departure_delay_90')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>

        <div class="mt8" data-show-if="incident_main:delay">
          <div>Var afgangen faktisk mindst 90 minutter forsinket?</div>
          <label><input type="radio" name="actual_departure_delay_90" value="yes" <?= $v('actual_departure_delay_90')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="actual_departure_delay_90" value="no" <?= $v('actual_departure_delay_90')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>
      </div>

      <div class="card mt12">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">Disse svar kan afskaere hotel og/eller kompensation i ferry-flowet.</p>

        <div class="mt8">
          <div>Var der vejrsikkerhed / sikkerhedsforhold?</div>
          <label><input type="radio" name="weather_safety" value="yes" <?= $v('weather_safety')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="weather_safety" value="no" <?= $v('weather_safety')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>

        <div class="mt8">
          <div>Paaberaaber carrier ekstraordinaere omstaendigheder?</div>
          <label><input type="radio" name="extraordinary_circumstances" value="yes" <?= $v('extraordinary_circumstances')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="extraordinary_circumstances" value="no" <?= $v('extraordinary_circumstances')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>
      </div>

      <input type="hidden" name="season_ticket" value="<?= h(((string)($form['ticket_upload_mode'] ?? '') === 'seasonpass') ? 'yes' : 'no') ?>" />

      <?php if (!empty($ferryScope) || !empty($ferryContract) || !empty($ferryRights)): ?>
        <div
          id="ferryResolverStatus"
          class="small"
          style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;"
          data-regulation-applies="<?= !empty($ferryScope['regulation_applies']) ? '1' : '0' ?>"
          data-art18-supported="<?= (($ferryScope['articles']['art18'] ?? true) !== false) ? '1' : '0' ?>"
          data-departure-from-terminal="<?=
            ($ferryScope['departure_from_terminal'] ?? null) === true
              ? '1'
              : (($ferryScope['departure_from_terminal'] ?? null) === false ? '0' : '')
          ?>"
        >
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($ferryScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div id="ferryResolverArt17">Art. 17: <?= !empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']) ? 'Ja' : 'Nej' ?></div>
          <div id="ferryResolverArt18">Art. 18: <?= !empty($ferryRights['gate_art18']) ? 'Ja' : 'Nej' ?></div>
          <div>Art. 19: <?= !empty($ferryRights['gate_art19']) ? ('Ja (' . h((string)($ferryRights['art19_comp_band'] ?? '')) . '%)') : 'Nej' ?></div>
        </div>
      <?php endif; ?>
    <?php elseif ($isBus): ?>
      <strong><span aria-hidden="true">&#x1F68C;</span> Haendelse (bus / EU 181/2011)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore information, assistance og refund/omlaegning for bus. Layoutet er holdt enkelt, saa kun de juridisk relevante bus-spoergsmaal staar tilbage.</p>

      <div class="small mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:8px; padding:10px 12px;">
        <strong>Bus-flow i TRIN 5:</strong> vaelg foerst haendelsen, derefter eventuel forsinkelses-kategori, og afslut med aaben billet, tilslutning og force majeure.
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">H</span>
          <span>Haendelsestype</span>
        </div>
        <p class="small muted mt8">Vaelg den bushaendelse der aabner det videre rettighedsspor.</p>

        <div class="mt8">
          <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
          <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
          <label class="ml8"><input type="radio" name="incident_main" value="overbooking" <?= $v('incident_main')==='overbooking'?'checked':'' ?> /> Overbooking / manglende plads</label>
        </div>
        <div class="mt12" data-show-if="incident_main:delay">
          <div class="small"><strong>Forsinkelsesniveau</strong></div>
          <p class="small muted mt4">Vaelg den juridiske forsinkelses-kategori i stedet for at indtaste minutter.</p>

          <div class="mt8">
            <label><input type="radio" name="delay_departure_band" value="under_90" <?= $v('delay_departure_band')==='under_90'?'checked':'' ?> /> Under 90 min</label>
            <label class="ml8"><input type="radio" name="delay_departure_band" value="90_119" <?= $v('delay_departure_band')==='90_119'?'checked':'' ?> /> 90-119 min</label>
            <label class="ml8"><input type="radio" name="delay_departure_band" value="120_plus" <?= $v('delay_departure_band')==='120_plus'?'checked':'' ?> /> 120+ min</label>
          </div>

          <div id="busDelayTimerCard" class="bus-live-shell">
            <div class="small"><strong>Live forsinkelses-hjaelper</strong></div>
            <div class="small muted mt4">Timeren kan koere ved igangvaerende busforsinkelse og synkroniserer automatisk til 90- og 120-minutters-taersklerne.</div>

            <div class="bus-live-display mt8">
              <span id="busDelayLiveMinutes" class="bus-live-minutes"><?= h((string)($form['delay_minutes_departure'] ?? '0')) ?> min</span>
              <span id="busDelayThresholdBadge" class="bus-live-badge">Under 90 min</span>
            </div>

            <div id="busDelayThresholdMessage" class="small mt8">Naeste threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.</div>
            <div id="busDelayNextHint" class="small muted mt4">Refund eller omlaegning bliver forst relevant ved 120+ min.</div>

            <label class="small mt8" style="display:block;">Saet aktuel forsinkelse nu (minutter)
              <input id="busDelayLiveMinutesInput" type="number" min="0" step="1" value="<?= h((string)($form['delay_minutes_departure'] ?? '0')) ?>" placeholder="75" />
            </label>

            <div class="bus-live-actions">
              <button type="button" id="busDelayTimerStart">Start timer</button>
              <button type="button" id="busDelayTimerPause" class="bus-live-secondary">Pause</button>
              <button type="button" id="busDelayTimerReset" class="bus-live-secondary">Nulstil</button>
            </div>

            <div id="busDelayTimerStatus" class="small muted mt8"></div>
          </div>
        </div>

        <div class="mt12" id="busPlannedDurationCard">
          <div class="small"><strong>Planlagt rejsevarighed</strong></div>
          <p class="small muted mt4">Vises kun naar Art. 21 kan komme i spil, saa brugeren ikke moedes af irrelevante spoergsmaal.</p>

          <div class="mt8">
            <label><input type="radio" name="planned_duration_band" value="up_to_3h" <?= $v('planned_duration_band')==='up_to_3h'?'checked':'' ?> /> 3 timer eller mindre</label>
            <label class="ml8"><input type="radio" name="planned_duration_band" value="over_3h" <?= $v('planned_duration_band')==='over_3h'?'checked':'' ?> /> Over 3 timer</label>
          </div>
        </div>
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">D</span>
          <span>Driftsproblem</span>
        </div>
        <p class="small muted mt8">Registrer separat, om bussen blev uanvendelig undervejs. Det supplerer haendelsestypen og erstatter den ikke.</p>

        <div class="mt8">
          <div>Gik bussen i stykker eller blev den uanvendelig under rejsen?</div>
          <label><input type="radio" name="vehicle_breakdown" value="yes" <?= $v('vehicle_breakdown')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="vehicle_breakdown" value="no" <?= $v('vehicle_breakdown')==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="vehicle_breakdown" value="unknown" <?= $v('vehicle_breakdown')==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
      </div>

      <input type="hidden" name="overbooking" value="<?= h($v('incident_main') === 'overbooking' ? 'yes' : 'no') ?>" />
      <input type="hidden" name="carrier_offered_choice" value="" />
      <input type="hidden" name="delay_minutes_departure" value="<?= h((string)($form['delay_minutes_departure'] ?? '')) ?>" />
      <input type="hidden" name="scheduled_journey_duration_minutes" value="" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">M</span>
          <span>Tilslutning</span>
        </div>
        <p class="small muted mt8">Busflowet bruger en enkel ja/nej-model her. Det er ikke den almindelige rail-blok for mistet forbindelse.</p>

        <div class="mt8">
          <div>Missede du en videre forbindelse pga. forsinkelsen?</div>
          <label><input type="radio" name="missed_connection_due_to_delay" value="yes" <?= $v('missed_connection_due_to_delay')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="missed_connection_due_to_delay" value="no" <?= $v('missed_connection_due_to_delay')==='no'?'checked':'' ?> /> Nej</label>
        </div>
      </div>

      <div class="card mt12" style="border-color:#e8d7aa;background:#fffaf0">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">Disse svar bruges til at afgoere, om hotel efter busreglerne bortfalder ved ekstraordinaere forhold.</p>

        <div class="mt8">
          <div>Var der kraftigt vejr?</div>
          <label><input type="radio" name="severe_weather" value="yes" <?= $v('severe_weather')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="severe_weather" value="no" <?= $v('severe_weather')==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <div class="mt8">
          <div>Var der stor naturkatastrofe?</div>
          <label><input type="radio" name="major_natural_disaster" value="yes" <?= $v('major_natural_disaster')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="major_natural_disaster" value="no" <?= $v('major_natural_disaster')==='no'?'checked':'' ?> /> Nej</label>
        </div>
      </div>

      <input type="hidden" name="season_ticket" value="<?= h(((string)($form['ticket_upload_mode'] ?? '') === 'seasonpass') ? 'yes' : 'no') ?>" />

      <?php if (!empty($busScope) || !empty($busContract) || !empty($busRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($busScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($busContract['primary_claim_party_name'] ?? ($busContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Info: <?= !empty($busRights['gate_bus_info']) ? 'Ja' : 'Nej' ?></div>
          <div>Assistance: <?= !empty($busRights['gate_bus_assistance_refreshments']) || !empty($busRights['gate_bus_assistance_hotel']) ? 'Ja' : 'Nej' ?></div>
          <div>Refund/ombooking: <?= !empty($busRights['gate_bus_reroute_refund']) ? 'Ja' : 'Nej' ?></div>
          <div>50% kompensation: <?= !empty($busRights['gate_bus_compensation_50']) ? 'Ja' : 'Nej' ?></div>
        </div>
      <?php endif; ?>
    <?php elseif ($isAir): ?>
      <strong><span aria-hidden="true">&#x2708;</span> Haendelse (air / EC261)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore care og kompensation for flighten eller den protected connection der blev ramt. Delay-care foelger Art. 6-thresholds efter afstandskategori.</p>

      <div class="small mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:8px; padding:10px 12px;">
        <strong>Air-flow i TRIN 5:</strong> afstandskategori <strong><?= h($airDistanceBandLabel) ?></strong><?= $airFlightDistanceKm !== '' ? (' (' . h($airFlightDistanceKm) . ' km)') : '' ?>. Art. 6-delay-threshold er <strong><?= h($airDelayThresholdLabel) ?></strong>.
      </div>

      <div class="mt8">
        <div>Haendelsestype</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Lang forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
        <label class="ml8"><input type="radio" name="incident_main" value="denied_boarding" <?= $v('incident_main')==='denied_boarding'?'checked':'' ?> /> Boardingafvisning</label>
      </div>

      <div id="airCancellationNoticeCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">C</span>
          <span>Varsel om aflysning</span>
        </div>
        <p class="small muted mt8">Bruges kun til Art. 5-kompensationsundtagelserne ved aflysning. Remedy og care aabnes stadig af selve aflysningen.</p>

        <div class="mt8">
          <div>Hvor lang tid foer planlagt afgang fik du besked om aflysningen?</div>
          <label><input type="radio" name="cancellation_notice_band" value="14_plus_days" <?= $airCancellationNoticeBand==='14_plus_days'?'checked':'' ?> /> Mindst 14 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="7_to_13_days" <?= $airCancellationNoticeBand==='7_to_13_days'?'checked':'' ?> /> Mellem 14 og 7 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="under_7_days" <?= $airCancellationNoticeBand==='under_7_days'?'checked':'' ?> /> Under 7 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="unknown" <?= $airCancellationNoticeBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="incident_main:delay">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">D</span>
          <span>Forventet afgangsforsinkelse</span>
        </div>
        <p class="small muted mt8">Vaelg den juridiske delay-kategori efter afstandsbucket i Art. 6. Ved 5+ timer kan refund blive relevant i et senere trin.</p>

        <div class="mt8">
          <?php foreach ($airDelayBandOptions as $bandValue => $bandLabel): ?>
            <label class="<?= $bandValue !== 'under_threshold' ? 'ml8' : '' ?>"><input type="radio" name="delay_departure_band" value="<?= h($bandValue) ?>" <?= $airDelayBandValue===$bandValue?'checked':'' ?> /> <?= h($bandLabel) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mt8" data-show-if="incident_main:delay">
        <label>Forsinkelse ved endelig ankomst (minutter, bruges til kompensation)
          <input type="number" name="arrival_delay_minutes" min="0" step="1" value="<?= h($v('arrival_delay_minutes')) ?>" placeholder="185" />
        </label>
      </div>
      <input type="hidden" name="delay_minutes_departure" value="" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="incident_main:denied_boarding">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">B</span>
          <span>Boardingafvisning</span>
        </div>
        <p class="small muted mt8">Artikel 4 skelner mellem frivilligt afkald mod en aftalt modydelse og boardingafvisning mod din vilje. Frivillige kan stadig have ret til Art. 8 og 9, men ikke automatisk Art. 7-kompensation.</p>
        <div class="mt8">Gav du frivilligt afkald på din reservation mod en aftalt modydelse?</div>
        <label><input type="radio" name="voluntary_denied_boarding" value="yes" <?= $v('voluntary_denied_boarding')==='yes'?'checked':'' ?> /> Ja, frivilligt</label>
        <label class="ml8"><input type="radio" name="voluntary_denied_boarding" value="no" <?= $v('voluntary_denied_boarding')==='no'?'checked':'' ?> /> Nej, jeg blev afvist mod min vilje</label>
      </div>
      <input type="hidden" name="boarding_denied" value="<?= h(in_array($v('incident_main'), ['denied_boarding'], true) ? 'yes' : $v('boarding_denied')) ?>" />

      <?php if ($showAirMissedConnection): ?>
      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">P</span>
          <span>Missed connection</span>
        </div>
        <p class="small muted mt8">TRIN 2 afgør allerede, om forbindelsen er samlet eller separat. Her registrerer vi kun, om du faktisk mistede en videre forbindelse.</p>
        <div class="mt8">Mistede du en videre forbindelse pga. hændelsen?</div>
        <label><input type="radio" name="protected_connection_missed" value="yes" <?= $v('protected_connection_missed')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="protected_connection_missed" value="no" <?= $v('protected_connection_missed')==='no'?'checked':'' ?> /> Nej / uklart</label>
        <div class="mt8" data-show-if="protected_connection_missed:yes">
          <?php if ($airConnectionNeedsFallback): ?>
          <div>Hvad bygger forbindelsen på?</div>
          <select name="connection_protection_basis">
            <option value="">- Vaelg grundlag -</option>
            <option value="same_booking_reference" <?= $v('connection_protection_basis')==='same_booking_reference'?'selected':'' ?>>Samme bookingreference / PNR</option>
            <option value="same_ticket" <?= $v('connection_protection_basis')==='same_ticket'?'selected':'' ?>>Samme billet / ticket chain</option>
            <option value="same_airline_interline" <?= $v('connection_protection_basis')==='same_airline_interline'?'selected':'' ?>>Samme airline / interline-forloeb</option>
            <option value="separate_tickets" <?= $v('connection_protection_basis')==='separate_tickets'?'selected':'' ?>>Saerskilte billetter</option>
            <option value="unclear" <?= $v('connection_protection_basis')==='unclear'?'selected':'' ?>>Uklart</option>
          </select>
          <div class="small muted mt4">Vises kun naar TRIN 2 ikke allerede har afgjort forbindelsestypen sikkert.</div>
          <?php else: ?>
          <input type="hidden" name="connection_protection_basis" value="" />
          <div class="small muted mt4">TRIN 2 har allerede afgjort forbindelsestypen som <?= h($airConnectionType ?: 'afgjort') ?>, så der er ikke brug for et ekstra grundlag her.</div>
          <?php endif; ?>
        </div>
      </div>

      <div id="airCancellationNotice14Card" class="small muted mt8 hidden">Hvis aflysningen blev meddelt mindst 14 dage foer, bortfalder kompensation normalt efter Art. 5(1)(c)(i), men remedy og care kan stadig vaere relevante.</div>
      <?php else: ?>
      <input type="hidden" name="protected_connection_missed" value="no" />
      <input type="hidden" name="connection_protection_basis" value="" />
      <input type="hidden" name="reroute_arrival_delay_minutes" value="" />
      <?php endif; ?>

      <div id="airCancellationRerouteCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">R</span>
          <span>Ombooking og undtagelse fra kompensation</span>
        </div>
        <p id="airCancellationRerouteHelp" class="small muted mt8">Kun relevant hvis du fik besked om aflysningen mindre end 14 dage foer og faktisk blev tilbudt ombooking.</p>

        <div class="mt8">
          <div>Tilboed operatoeren ombooking?</div>
          <label><input type="radio" name="reroute_offered" value="yes" <?= $v('reroute_offered')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_offered" value="no" <?= $v('reroute_offered')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>

        <div class="mt8">
          <div id="airCancellationDepartureQuestion">Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?</div>
          <label><input type="radio" name="reroute_departure_band" value="within_window" <?= $airRerouteDepartureBand==='within_window'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_departure_band" value="outside_window" <?= $airRerouteDepartureBand==='outside_window'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_departure_band" value="unknown" <?= $airRerouteDepartureBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>

        <div class="mt8">
          <div id="airCancellationArrivalQuestion">Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?</div>
          <label><input type="radio" name="reroute_arrival_band" value="within_window" <?= $airRerouteArrivalBand==='within_window'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_arrival_band" value="outside_window" <?= $airRerouteArrivalBand==='outside_window'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_arrival_band" value="unknown" <?= $airRerouteArrivalBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
      </div>

      <div class="mt8" data-show-if="protected_connection_missed:yes">
        <label>Forsinkelse ved ankomst efter ombooking (minutter, valgfri)
          <input type="number" name="reroute_arrival_delay_minutes" min="0" step="1" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" placeholder="95" />
        </label>
      </div>

      <div class="card mt12" style="border-color:#e8d7aa;background:#fffaf0">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / extraordinary circumstances">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">Bruges i air-flowet til at vurdere om kompensation kan bortfalde pga. extraordinary circumstances. Care og hotel behandles i et senere trin.</p>

        <div class="mt8">
          <div>Paaberaaber flyselskabet extraordinary circumstances?</div>
          <label><input type="radio" name="extraordinary_circumstances" value="yes" <?= $v('extraordinary_circumstances')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="extraordinary_circumstances" value="no" <?= $v('extraordinary_circumstances')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>
      </div>

      <input type="hidden" name="meal_offered" value="" />
      <input type="hidden" name="hotel_required" value="" />
      <input type="hidden" name="hotel_offered" value="" />

      <?php if (!empty($airScope) || !empty($airContract) || !empty($airRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($airScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Distancekategori: <?= h($airDistanceBandLabel) ?></div>
          <div>Art. 6 delay-threshold: <?= h($airDelayThresholdLabel) ?></div>
          <div>Aflysningsvarsel: <?= h(match ($airCancellationNoticeBand) {
              '14_plus_days' => '14+ dage',
              '7_to_13_days' => '7-13 dage',
              'under_7_days' => 'Under 7 dage',
              'unknown' => 'Ved ikke',
              default => 'Ikke angivet',
          }) ?></div>
          <div>Claim-kanal: <?= h((string)($airContract['primary_claim_party_name'] ?? ($airContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Care: <?= !empty($airRights['gate_air_care']) ? 'Ja' : 'Nej' ?></div>
          <div>Reroute/refund: <?= !empty($airRights['gate_air_reroute_refund']) ? 'Ja' : 'Nej' ?></div>
          <div>Delay 5h refund: <?= !empty($airRights['gate_air_delay_refund_5h']) ? 'Ja' : 'Nej' ?></div>
          <div>Kompensation: <?= !empty($airRights['gate_air_compensation']) ? 'Candidate' : 'Ikke aktiveret' ?><?= !empty($airRights['air_comp_band']) ? ' - ' . h((string)$airRights['air_comp_band']) : '' ?></div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <strong><span aria-hidden="true">&#x26A1;</span> Haendelse (Art.18/20)</strong>
      <p class="small muted">Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.<?= $incidentHint !== '' ? (' ' . h($incidentHint)) : '' ?></p>

      <div class="mt8">
        <div>Haendelsestype (vaelg en)</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
      </div>

      <div class="mt4" data-show-if="incident_main:delay">
        <div>Har du modtaget besked om &ge;60 minutters forsinkelse?</div>
        <label><input type="radio" name="expected_delay_60" value="yes" <?= $v('expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="expected_delay_60" value="no" <?= $v('expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8" data-show-if="incident_main:delay">
        <div>Er du allerede 60 minutter forsinket?</div>
        <label><input type="radio" name="delay_already_60" value="yes" <?= $v('delay_already_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="delay_already_60" value="no" <?= $v('delay_already_60')==='no'?'checked':'' ?> /> Nej</label>
        <div class="small muted mt4">Tip: Hvis du ikke ved det endnu, kan du fortsaette og opdatere senere.</div>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- Mistet forbindelse -->
  <div class="card mt12">
    <strong><span aria-hidden="true">&#128206;</span> Mistet forbindelse</strong>
    <p class="small muted">Vælg den konkrete leg i TRIN 3.5. Her bruger vi kun den valgte forbindelse som reference.</p>

    <input type="hidden" name="incident_missed" value="<?= $missedConnectionChosen ? 'yes' : 'no' ?>" />

    <?php if ($missedConnectionChosen): ?>
      <div class="mt8 small">
        Registreret forbindelse:
        <strong>
          <?= h($missedConnectionPick !== '' ? $missedConnectionPick : $missedConnectionStation) ?>
        </strong>
      </div>
      <div id="missed60Wrap" class="mt8">
        <div>Betyder den valgte forbindelse, at du forventer at ankomme mindst 60 minutter senere til din endelige destination?</div>
        <label><input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        <div class="small muted mt4">Hvis nej, kan nationale ordninger stadig være relevante afhængigt af land.</div>
      </div>
    <?php else: ?>
      <div class="small muted mt8">Ingen forbindelse valgt endnu. Gå tilbage til TRIN 3.5 for at vælge den ramte leg.</div>
    <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- Form & exemptions (moved from TRIN 9) -->
  <div class="card mt12">
    <div class="widget-title">
      <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
          <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
        </svg>
      </span>
      <span>Force majeure</span>
    </div>
    <div class="small mt4">Udbetaling sker som udgangspunkt kontant. Vouchers accepteres ikke i denne loesning.</div>
    <input type="hidden" name="voucherAccepted" value="no" />

    <?php $exc = (string)($form['operatorExceptionalCircumstances'] ?? ''); ?>
    <div class="mt8">
      Henviser operatoeren til ekstraordinaere forhold (Art. 19(10))?
    </div>
    <label><input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc===''||$exc==='unknown')?'checked':'' ?> /> Ved ikke</label>

    <?php $excType = (string)($form['operatorExceptionalType'] ?? ''); ?>
    <div class="mt8" data-show-if="operatorExceptionalCircumstances:yes">
      <div class="small">Hvis ja: vaelg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)</div>
      <select name="operatorExceptionalType">
        <option value="">- Vaelg type -</option>
        <option value="weather" <?= $excType==='weather'?'selected':'' ?>>Vejr</option>
        <option value="sabotage" <?= $excType==='sabotage'?'selected':'' ?>>Sabotage</option>
        <option value="infrastructure_failure" <?= $excType==='infrastructure_failure'?'selected':'' ?>>Infrastrukturfejl</option>
        <option value="third_party" <?= $excType==='third_party'?'selected':'' ?>>Tredjepart</option>
        <option value="own_staff_strike" <?= $excType==='own_staff_strike'?'selected':'' ?>>Egen personalestrejke</option>
        <option value="external_strike" <?= $excType==='external_strike'?'selected':'' ?>>Ekstern strejke</option>
        <option value="other" <?= $excType==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8">
      <label><input type="checkbox" name="minThresholdApplies" value="1" <?= !empty($form['minThresholdApplies']) ? 'checked' : '' ?> /> Anvend min. taerskel <= 4 EUR (Art. 19(8))</label>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- National fallback (shown only when EU 60-min gate is NOT met; displayed after "last chance" + force majeure) -->
  <div id="nationalFallbackWrap" class="card mt12 hidden" style="border-color:#ffe8cc;background:#fff8e6">
    <strong>National ordning (fallback)<?= (!empty($nationalPolicy['name']) ? (': ' . h((string)$nationalPolicy['name'])) : '') ?></strong>
    <div class="small mt4">
      EU: Art. 18/20 udloeses typisk ved <strong>&ge;60 min</strong> forsinkelse (eller aflysning).
    </div>
    <div class="small mt4">
      National ordning:
      <?php if ($nationalCutoff !== null && $nationalCutoff > 0 && $nationalCutoff < 60): ?>
        kompensation fra <strong><?= (int)$nationalCutoff ?> min</strong><?= ($nationalThr50 !== null && $nationalThr50 > 0) ? (' (naeste band: ' . (int)$nationalThr50 . ' min)') : '' ?>.
      <?php else: ?>
        <span class="muted">ukendt (kraever land + scope).</span>
      <?php endif; ?>
    </div>

    <div id="nationalFallbackBlockedHint" class="small mt8 hidden" style="background:#fff;border:1px solid #cfe0ff;border-radius:6px;padding:8px;">
      <strong>Foer vi gaar til national ordning:</strong> Svar paa spoergsmaalet ovenfor om det missede skift giver <strong>&ge;60 min</strong> til din endelige destination.
    </div>

    <?php if ($compBlockedByFM): ?>
      <div class="small mt8" style="background:#fff;border:1px solid #f5c2c7;border-radius:6px;padding:8px;">
        <strong>Bemaerk:</strong> Du har angivet ekstraordinaere forhold (Art. 19(10)). Kompensation kan vaere udelukket (EU + national),
        men Art. 18/20 kan stadig blive relevant ved <strong>&ge;60 min</strong> eller aflysning.
      </div>
    <?php endif; ?>

    <div id="nationalFallbackInputs" class="mt8">
      <label class="small">Hvor mange minutter var/er du forsinket?
        <input id="nationalDelayMinutes" type="number" name="national_delay_minutes" min="0" step="1" value="<?= h($v('national_delay_minutes')) ?>" placeholder="minutter" />
      </label>
      <input type="hidden" id="nationalDelayReportedAt" name="national_delay_reported_at" value="<?= h($v('national_delay_reported_at')) ?>" />
      <div class="small muted mt4">Denne oplysning bruges kun til national fallback - den aktiverer ikke Art. 18/20.</div>
    </div>

    <div id="euReminderWrap" class="mt12 hidden">
      <div class="small"><strong>Reminder (igangvaerende rejse)</strong></div>
      <div class="small mt4">
        Hvis forsinkelsen stiger til <strong>60 min</strong>, kan EU-rettigheder (Art. 18/20) blive relevante.
        <span id="euReminderInfo"></span>
      </div>
      <div class="mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="button" id="startEuReminder" style="background:#eee;color:#333;">Start reminder</button>
        <span class="small muted" id="euReminderStatus"></span>
      </div>
      <div id="euReminderPrompt" class="card mt8 hidden" style="border-color:#cfe0ff;background:#f1f8ff">
        <div class="small"><strong>Reminder</strong></div>
        <div class="small mt4">Du kan nu vaere >=60 min forsinket. Er du det?</div>
        <div class="mt8" style="display:flex;gap:8px;align-items:center;">
          <button type="button" class="button" id="euReminderYes">Ja</button>
          <button type="button" class="button" id="euReminderNo" style="background:#eee;color:#333;">Nej</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="mt12" style="display:flex; gap:8px; align-items:center;">
    <?= $this->Html->link('<- Tilbage', ['action' => 'journey'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Naeste trin ->', ['class' => 'button', 'id' => 'incidentSubmitBtn', 'form' => 'incidentStepForm']) ?>
  </div>

  </fieldset>
  <?= $this->Form->end() ?>

  <div class="mt12">
    <button type="button" class="button" id="loadHooksBtn" style="background:#eee;color:#333;">
      Indlaes sidepanel
    </button>
    <div id="hooksPanel" class="card mt12 hidden" hidden>
      <div class="small muted">Sidepanel ikke indlaest endnu.</div>
    </div>
  </div>
</div>

<script>
var __incidentMode = <?= json_encode($gatingMode) ?>;
var __incidentIsRail = __incidentMode === 'rail';
var __incidentIsFerry = __incidentMode === 'ferry';
var __incidentIsBus = __incidentMode === 'bus';
var __incidentIsAir = __incidentMode === 'air';
var __incidentShowHooksPanel = <?= json_encode($showHooksPanel) ?>;

function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if'); if (!spec) return;
    var parts = spec.split(':'); if (parts.length !== 2) return;
    var name = parts[0]; var valid = parts[1].split(',');
    var val = '';
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    if (checked) {
      val = checked.value || '';
    } else {
      var sel = document.querySelector('select[name="' + name + '"]');
      if (sel) { val = sel.value || ''; }
    }
    var show = val && valid.includes(val);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
}
function getVal(name) {
  var checked = document.querySelector('input[name="' + name + '"]:checked');
  if (checked) { return checked.value || ''; }
  var sel = document.querySelector('select[name="' + name + '"]');
  if (sel) { return sel.value || ''; }
  var inp = document.querySelector('input[name="' + name + '"]');
  if (inp && inp.type !== 'radio' && inp.type !== 'checkbox') { return inp.value || ''; }
  return '';
}
document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  updateReveal();
  if (__incidentIsFerry) {
    updateFerryResolverStatus();
  }
  if (__incidentIsAir) {
    updateAirCancellationState();
  }
  if (__incidentIsBus) {
    updateBusIncidentState();
    updateBusDelayTimerUi();
  }
  if (__incidentIsRail) {
    updateStep4State();
  }
});
document.addEventListener('DOMContentLoaded', function(){
  updateReveal();
  if (__incidentIsFerry) {
    updateFerryResolverStatus();
  }
  if (__incidentIsAir) {
    updateAirCancellationState();
  }
  if (__incidentIsBus) {
    updateBusIncidentState();
    setupBusDelayTimer();
    updateBusDelayTimerUi();
  }
  if (__incidentIsRail) {
    updateStep4State();
  }

  var panel = document.getElementById('hooksPanel');
  var loadHooksBtn = document.getElementById('loadHooksBtn');
  if (panel && loadHooksBtn) {
    var loadHooksPanel = function() {
      if (panel.dataset.loading === '1' || panel.dataset.loaded === '1') return;
      panel.dataset.loading = '1';
      panel.hidden = false;
      panel.classList.remove('hidden');
      loadHooksBtn.disabled = true;
      loadHooksBtn.textContent = 'Indlaeser sidepanel...';
      var url = new URL(window.location.href);
      url.searchParams.set('ajax_hooks', '1');
      fetch(url.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function(resp) {
        return resp.text();
      }).then(function(txt) {
        panel.innerHTML = txt;
        panel.dataset.loaded = '1';
        loadHooksBtn.textContent = 'Sidepanel indlaest';
      }).catch(function() {
        panel.innerHTML = '<div class="small muted">Sidepanel kunne ikke indlaeses.</div>';
        loadHooksBtn.disabled = false;
        loadHooksBtn.textContent = 'Indlaes sidepanel';
      }).finally(function() {
        panel.dataset.loading = '0';
      });
    };

    loadHooksBtn.addEventListener('click', loadHooksPanel);
    if (__incidentShowHooksPanel) {
      loadHooksPanel();
    }
  }
});

function getRadioValue(name){
  var r = document.querySelector('input[name="' + name + '"]:checked');
  return r ? r.value : '';
}
function showById(id, show){
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden', !show);
  el.hidden = !show;
}

function setBlockVisible(el, show) {
  if (!el) return;
  el.style.display = show ? 'block' : 'none';
  el.hidden = !show;
}

function updateFerryResolverStatus() {
  var status = document.getElementById('ferryResolverStatus');
  if (!status) return;

  var art17El = document.getElementById('ferryResolverArt17');
  var art18El = document.getElementById('ferryResolverArt18');
  var regulationApplies = status.dataset.regulationApplies === '1';
  var art18Supported = status.dataset.art18Supported !== '0';
  var departureFromTerminal = status.dataset.departureFromTerminal === '1';
  var seasonTicketInput = document.querySelector('input[name="season_ticket"]');
  var seasonTicket = seasonTicketInput ? seasonTicketInput.value === 'yes' : false;
  var incidentMain = getRadioValue('incident_main');
  var expectedDelay90 = getRadioValue('expected_departure_delay_90') === 'yes';
  var actualDelay90 = getRadioValue('actual_departure_delay_90') === 'yes';
  var informedBeforePurchase = getRadioValue('informed_before_purchase') === 'yes';
  var openTicketWithoutDepartureTime = getRadioValue('open_ticket_without_departure_time') === 'yes';
  var hasDepartureDisruption = incidentMain === 'cancellation' || (incidentMain === 'delay' && (expectedDelay90 || actualDelay90));
  var art17Active = regulationApplies && hasDepartureDisruption && departureFromTerminal && !openTicketWithoutDepartureTime && !informedBeforePurchase;
  var art18Active = regulationApplies && art18Supported && hasDepartureDisruption && (!openTicketWithoutDepartureTime || seasonTicket);

  if (art17El) {
    art17El.textContent = 'Art. 17: ' + (art17Active ? 'Ja' : 'Nej');
  }
  if (art18El) {
    art18El.textContent = 'Art. 18: ' + (art18Active ? 'Ja' : 'Nej');
  }
}

function updateAirCancellationState() {
  var main = getRadioValue('incident_main');
  var reroute = getRadioValue('reroute_offered');
  var notice = getRadioValue('cancellation_notice_band');
  var isCancellation = main === 'cancellation';

  setBlockVisible(document.getElementById('airCancellationNoticeCard'), isCancellation);
  setBlockVisible(document.getElementById('airCancellationNotice14Card'), isCancellation && notice === '14_plus_days');
  setBlockVisible(document.getElementById('airCancellationRerouteCard'), isCancellation && reroute === 'yes' && (notice === '7_to_13_days' || notice === 'under_7_days'));

  var depQuestion = document.getElementById('airCancellationDepartureQuestion');
  var arrQuestion = document.getElementById('airCancellationArrivalQuestion');
  var help = document.getElementById('airCancellationRerouteHelp');

  if (notice === 'under_7_days') {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 1 time foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 2 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = 'Ved under 7 dages varsel bortfalder kompensation kun, hvis ombookningen holdt sig inden for 1 time foer afgang og 2 timer efter planlagt ankomst.';
  } else if (notice === '7_to_13_days') {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = 'Ved 7-13 dages varsel bortfalder kompensation kun, hvis ombookningen holdt sig inden for 2 timer foer afgang og 4 timer efter planlagt ankomst.';
  } else {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = 'Kun relevant hvis du fik besked om aflysningen mindre end 14 dage foer og faktisk blev tilbudt ombooking.';
  }
}

function updateBusIncidentState() {
  var busDurationCard = document.getElementById('busPlannedDurationCard');
  var busDelayTimerCard = document.getElementById('busDelayTimerCard');
  if (!busDurationCard && !busDelayTimerCard) return;

  var main = getRadioValue('incident_main');
  var delayBand = getRadioValue('delay_departure_band');
  var showBusDuration = (main === 'cancellation') || (main === 'delay' && (delayBand === '90_119' || delayBand === '120_plus'));
  setBlockVisible(busDurationCard, showBusDuration);
  setBlockVisible(busDelayTimerCard, main === 'delay');
  updateBusDelayTimerUi();
}

var __busDelayTimerInterval = null;
var __busDelayTimerBaseMinutes = 0;
var __busDelayTimerStartedAt = null;

function busDelayBandFromMinutes(mins) {
  if (mins >= 120) return '120_plus';
  if (mins >= 90) return '90_119';
  return 'under_90';
}

function busDelayMinutesFromBand(band) {
  if (band === '120_plus') return 120;
  if (band === '90_119') return 90;
  return 0;
}

function setRadioValue(name, value) {
  var target = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
  if (!target) return;
  if (!target.checked) {
    target.checked = true;
  }
}

function getBusDelayLiveMinutes() {
  if (__busDelayTimerStartedAt === null) {
    return __busDelayTimerBaseMinutes;
  }
  var elapsedMs = Date.now() - __busDelayTimerStartedAt;
  return __busDelayTimerBaseMinutes + Math.max(0, Math.floor(elapsedMs / 60000));
}

function syncBusDelayMinutes(mins, autoBand) {
  mins = parseInt(mins, 10);
  if (isNaN(mins) || mins < 0) mins = 0;

  __busDelayTimerBaseMinutes = mins;
  if (__busDelayTimerStartedAt !== null) {
    __busDelayTimerStartedAt = Date.now();
  }

  var hidden = document.querySelector('input[name="delay_minutes_departure"]');
  if (hidden) hidden.value = String(mins);

  var input = document.getElementById('busDelayLiveMinutesInput');
  if (input && String(input.value || '') !== String(mins)) {
    input.value = String(mins);
  }

  if (autoBand && getRadioValue('incident_main') === 'delay') {
    setRadioValue('delay_departure_band', busDelayBandFromMinutes(mins));
  }

  updateBusDelayTimerUi();
  updateBusIncidentState();
}

function stopBusDelayTicker() {
  if (__busDelayTimerInterval !== null) {
    window.clearInterval(__busDelayTimerInterval);
    __busDelayTimerInterval = null;
  }
}

function ensureBusDelayTicker() {
  if (__busDelayTimerInterval !== null) return;
  __busDelayTimerInterval = window.setInterval(function(){
    updateBusDelayTimerUi();
  }, 1000);
}

function updateBusDelayTimerUi() {
  var card = document.getElementById('busDelayTimerCard');
  if (!card) return;

  var main = getRadioValue('incident_main');
  var visible = main === 'delay';
  setBlockVisible(card, visible);
  if (!visible) return;

  var mins = getBusDelayLiveMinutes();
  var derivedBand = busDelayBandFromMinutes(mins);
  var durationBand = getRadioValue('planned_duration_band');
  var currentDelayBand = getRadioValue('delay_departure_band');
  if (derivedBand !== currentDelayBand) {
    setRadioValue('delay_departure_band', derivedBand);
    currentDelayBand = derivedBand;
  }

  var durationCard = document.getElementById('busPlannedDurationCard');
  if (durationCard) {
    var showBusDuration = (main === 'cancellation') || (main === 'delay' && (currentDelayBand === '90_119' || currentDelayBand === '120_plus'));
    setBlockVisible(durationCard, showBusDuration);
  }

  var display = document.getElementById('busDelayLiveMinutes');
  var badge = document.getElementById('busDelayThresholdBadge');
  var message = document.getElementById('busDelayThresholdMessage');
  var nextHint = document.getElementById('busDelayNextHint');
  var status = document.getElementById('busDelayTimerStatus');

  if (display) display.textContent = String(mins) + ' min';
  if (status) status.textContent = (__busDelayTimerStartedAt !== null) ? 'Timeren koerer.' : 'Timeren er pauset.';

  if (badge) {
    badge.className = 'bus-live-badge';
    if (mins >= 120) {
      badge.classList.add('is-120');
      badge.textContent = '120+ min';
    } else if (mins >= 90) {
      badge.classList.add('is-90');
      badge.textContent = '90+ min';
    } else {
      badge.textContent = 'Under 90 min';
    }
  }

  if (message) {
    if (mins >= 120) {
      message.textContent = 'Art. 19-threshold naet: refund eller omlaegning kan vaere relevant nu.';
    } else if (mins >= 90) {
      if (durationBand === 'over_3h') {
        message.textContent = 'Art. 21-threshold naet: assistance kan vaere relevant, fordi rejsen er over 3 timer.';
      } else if (durationBand === 'up_to_3h') {
        message.textContent = '90 min er naet, men Art. 21 assistance kraever ogsaa en planlagt rejse over 3 timer.';
      } else {
        message.textContent = '90 min er naet. Vurder nu om den planlagte rejse var over 3 timer for Art. 21.';
      }
    } else {
      message.textContent = 'Naeste threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.';
    }
  }

  if (nextHint) {
    if (mins >= 120) {
      nextHint.textContent = (durationBand === 'over_3h')
        ? 'Baade assistance og refund/omlaegning er nu inden for bus-thresholds.'
        : 'Refund eller omlaegning er inden for threshold; assistance afhaenger fortsat af rejse over 3 timer.';
    } else if (mins >= 90) {
      nextHint.textContent = 'Naeste threshold er 120 min, hvor refund eller omlaegning kan blive relevant.';
    } else {
      nextHint.textContent = 'Refund eller omlaegning bliver forst relevant ved 120+ min.';
    }
  }
}

function setupBusDelayTimer() {
  var card = document.getElementById('busDelayTimerCard');
  if (!card) return;

  var hidden = document.querySelector('input[name="delay_minutes_departure"]');
  var input = document.getElementById('busDelayLiveMinutesInput');
  var initial = 0;

  if (input && input.value !== '') {
    initial = parseInt(input.value, 10);
  } else if (hidden && hidden.value !== '') {
    initial = parseInt(hidden.value, 10);
  } else {
    var presetBand = getRadioValue('delay_departure_band');
    if (presetBand === '90_119') initial = 90;
    if (presetBand === '120_plus') initial = 120;
  }
  if (isNaN(initial) || initial < 0) initial = 0;
  __busDelayTimerBaseMinutes = initial;

  if (input) {
    input.addEventListener('input', function() {
      syncBusDelayMinutes(input.value, true);
    });
    input.addEventListener('change', function() {
      syncBusDelayMinutes(input.value, true);
    });
  }

  var delayBandInputs = document.querySelectorAll('input[name="delay_departure_band"]');
  delayBandInputs.forEach(function(radio) {
    radio.addEventListener('change', function() {
      if (!radio.checked || getRadioValue('incident_main') !== 'delay') return;
      var nextMinutes = busDelayMinutesFromBand(radio.value);
      if (__busDelayTimerStartedAt !== null) {
        __busDelayTimerStartedAt = Date.now();
      }
      syncBusDelayMinutes(nextMinutes, false);
    });
  });

  var start = document.getElementById('busDelayTimerStart');
  if (start) {
    start.addEventListener('click', function() {
      if (__busDelayTimerStartedAt === null) {
        __busDelayTimerStartedAt = Date.now();
      }
      ensureBusDelayTicker();
      updateBusDelayTimerUi();
    });
  }

  var pause = document.getElementById('busDelayTimerPause');
  if (pause) {
    pause.addEventListener('click', function() {
      if (__busDelayTimerStartedAt !== null) {
        __busDelayTimerBaseMinutes = getBusDelayLiveMinutes();
        __busDelayTimerStartedAt = null;
        stopBusDelayTicker();
        syncBusDelayMinutes(__busDelayTimerBaseMinutes, true);
      }
    });
  }

  var reset = document.getElementById('busDelayTimerReset');
  if (reset) {
    reset.addEventListener('click', function() {
      __busDelayTimerStartedAt = null;
      stopBusDelayTicker();
      syncBusDelayMinutes(0, true);
    });
  }
}

var __euReminderTimer = null;
function clearEuReminder(){
  if (__euReminderTimer) { try { window.clearTimeout(__euReminderTimer); } catch(e) {} }
  __euReminderTimer = null;
  showById('euReminderPrompt', false);
  var st = document.getElementById('euReminderStatus');
  if (st) st.textContent = '';
}

function updateStep4State(){
  var isOngoing = <?= json_encode((bool)$isOngoing) ?>;
  var euFlowSupported = <?= json_encode((bool)$euFlowSupported) ?>;
  var cutoff = <?= json_encode($nationalCutoff) ?>;
  var compBlockedByFM = <?= json_encode((bool)$compBlockedByFM) ?>;

  var main = getRadioValue('incident_main');
  var exp60 = getRadioValue('expected_delay_60');
  var already60 = getRadioValue('delay_already_60');
  var missed = getVal('incident_missed');
  var missed60 = getRadioValue('missed_expected_delay_60');
  var missedAnswered = (missed === 'yes' || missed === 'no');

  // Live (client-side) force-majeure evaluation: if user says "yes", we hide national fallback entirely.
  // This avoids confusing the user with national bands when compensation is likely excluded anyway.
  var fm = getRadioValue('operatorExceptionalCircumstances');
  var fmTypeSel = document.querySelector('select[name="operatorExceptionalType"]');
  var fmType = fmTypeSel ? (fmTypeSel.value || '') : '';
  var fmBlocksComp = (fm === 'yes') && (fmType === '' || fmType !== 'own_staff_strike');

  // EU gate can be satisfied either by the main incident (delay>=60 or cancellation),
  // or (only when EU gate is not already satisfied) by missed-connection implying >=60 to final destination.
  var euGateFromMain = false;
  if (main === 'cancellation') euGateFromMain = true;
  if (main === 'delay' && (exp60 === 'yes' || already60 === 'yes')) euGateFromMain = true;

  // UX: avoid asking the missed >=60 question when EU gate is already satisfied from delay/cancellation.
  // NOTE: we keep the value in session (server-side) for audit, but we ignore it for gating in that case.
  var showMissed60 = (missed === 'yes') && (!euGateFromMain);
  showById('missed60Wrap', showMissed60);

  var euGate = euGateFromMain;
  if (!euGateFromMain && missed === 'yes' && missed60 === 'yes') euGate = true;

  // National fallback is shown only when EU gate is NOT met, and only after the user has answered the
  // relevant "last chance" questions. Important: selecting missed-connection (incident_missed=yes)
  // must NOT be enough to trigger national fallback; the sub-question (missed_expected_delay_60) must be answered.
  var delayLastChanceAnswered = (main === 'delay') && (exp60 !== '') && (already60 !== '');
  var delayReadyForFallback = (!euGate) && (main === 'delay') && delayLastChanceAnswered;
  var missedReadyForFallback = (!euGate) && showMissed60 && (missed60 !== '');
  // If the user has enabled missed-connection, require the sub-question to be answered
  // before we show national fallback (even if delay-branch is already answered).
  var missedLastChanceUnanswered = showMissed60 && (missed60 === '');
  var showNat = (!euGate) && !missedLastChanceUnanswered && (delayReadyForFallback || missedReadyForFallback) && !fmBlocksComp;
  showById('nationalFallbackWrap', showNat);
  // We no longer keep the fallback visible-but-disabled. If the needed sub-question isn't answered yet,
  // we hide the fallback entirely (cleaner UX and avoids the "red box shows too early" issue).
  showById('nationalFallbackBlockedHint', false);
  var minsField = document.getElementById('nationalDelayMinutes');
  if (minsField) { minsField.disabled = false; }

  // Reminder UI: only useful in ongoing journeys when national fallback is visible.
  var mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;
  // Also show reminder when compensation is blocked by force majeure (Art. 19(10)) and EU gate isn't met yet.
  var canRemind = euFlowSupported && isOngoing && showNat && !isNaN(mins) && mins > 0 && mins < 60;
  if (!canRemind && euFlowSupported && isOngoing && compBlockedByFM && !euGate && !isNaN(mins) && mins > 0 && mins < 60) {
    canRemind = true;
  }
  showById('euReminderWrap', canRemind);
  var info = document.getElementById('euReminderInfo');
  if (info && canRemind) {
    var m2 = 60 - mins;
    info.textContent = ' (ca. ' + m2 + ' min til 60, hvis forsinkelsen ikke aendrer sig)';
  } else if (info) {
    info.textContent = '';
  }

  // If gating context changes, clear any running reminder.
  if (!canRemind) { clearEuReminder(); }

  // If the user already typed >=60 minutes in national fallback (ongoing journey),
  // prompt them to confirm EU gating (we still require an explicit click).
  if (isOngoing && showNat && !isNaN(mins) && mins >= 60) {
    showById('euReminderWrap', true);
    showById('euReminderPrompt', true);
    var status2 = document.getElementById('euReminderStatus');
    if (status2) status2.textContent = 'Du har angivet ' + mins + ' min. Bekraeft om du nu er >=60 min forsinket.';
  }

}

(function(){
  var minsField = document.getElementById('nationalDelayMinutes');
  var reportedAt = document.getElementById('nationalDelayReportedAt');
  if (minsField && reportedAt) {
    minsField.addEventListener('input', function(){
      // Stamp when user edits national minutes (client-only; server stores it as-is).
      reportedAt.value = String(Date.now());
      updateStep4State();
    }, { passive:true });
  }

  var btn = document.getElementById('startEuReminder');
  if (btn) {
    btn.addEventListener('click', function(){
      clearEuReminder();
      var mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;
      if (isNaN(mins) || mins <= 0 || mins >= 60) { return; }
      var m2 = 60 - mins;
      var status = document.getElementById('euReminderStatus');
      if (status) status.textContent = 'Reminder sat til ca. ' + m2 + ' min.';
      __euReminderTimer = window.setTimeout(function(){
        showById('euReminderPrompt', true);
      }, m2 * 60 * 1000);
    });
  }

  var yes = document.getElementById('euReminderYes');
  var no = document.getElementById('euReminderNo');
  if (yes) {
    yes.addEventListener('click', function(){
      // User confirmation required: set the EU gate question, do not auto-submit.
      var r = document.querySelector('input[name=\"delay_already_60\"][value=\"yes\"]');
      if (r) { r.checked = true; }
      showById('euReminderPrompt', false);
      updateReveal();
      updateStep4State();
    });
  }
  if (no) {
    no.addEventListener('click', function(){
      showById('euReminderPrompt', false);
      clearEuReminder();
    });
  }
})();
</script>
