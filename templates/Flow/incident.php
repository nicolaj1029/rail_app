<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];
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
    $altSegs = $meta['_segments_llm_suggest'] ?? ($meta['_segments_auto'] ?? []);
    if (is_array($altSegs)) { $segCount = max($segCount, count($altSegs)); }
}
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($multimodal['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail'))));
if (!in_array($transportMode, ['rail','ferry','bus','air'], true)) { $transportMode = 'rail'; }
$isFerry = $transportMode === 'ferry';
$isBus = $transportMode === 'bus';
$isAir = $transportMode === 'air';
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$busRights = (array)($multimodal['bus_rights'] ?? []);
$busContract = (array)($multimodal['bus_contract'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$airRights = (array)($multimodal['air_rights'] ?? []);
$airContract = (array)($multimodal['air_contract'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);

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
  <?= $this->Form->create(null, ['novalidate' => true]) ?>
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
  <div class="card mt12 <?= $art91On ? '' : 'hidden' ?>" data-art="9(1)">
    <strong><span aria-hidden="true">&#x23F1;</span> Afbrydelser/forsinkelser</strong>
    <p class="small muted">Default er "Nej". Udfyld kun hvis relevant.</p>

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
  </div>


  <!-- Standard gating -->
  <div class="card mt12">
    <?php if ($isFerry): ?>
      <strong><span aria-hidden="true">&#x26A1;</span> Haendelse (Art. 16-19 ferry)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore information, assistance, rerouting/refund og kompensation for faergebenet.</p>

      <div class="mt8">
        <div>Haendelsestype</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
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

      <div class="mt8">
        <label>Forsinkelse ved ankomst (minutter)
          <input type="number" name="arrival_delay_minutes" min="0" step="1" value="<?= h($v('arrival_delay_minutes')) ?>" placeholder="130" />
        </label>
      </div>

      <div class="mt8">
        <label>Planlagt rejsevarighed (minutter)
          <input type="number" name="scheduled_journey_duration_minutes" min="0" step="1" value="<?= h($v('scheduled_journey_duration_minutes')) ?>" placeholder="300" />
        </label>
      </div>

      <div class="mt8">
        <div>Var overnatning noedvendig pga. haendelsen?</div>
        <label><input type="radio" name="overnight_required" value="yes" <?= $v('overnight_required')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="overnight_required" value="no" <?= $v('overnight_required')==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <div class="mt8">
        <div>Var passageren informeret om aflysning/forsinkelse foer koeb?</div>
        <label><input type="radio" name="informed_before_purchase" value="yes" <?= $v('informed_before_purchase')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="informed_before_purchase" value="no" <?= $v('informed_before_purchase')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8">
        <div>Skyldtes problemet passagerens egne forhold?</div>
        <label><input type="radio" name="passenger_fault" value="yes" <?= $v('passenger_fault')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="passenger_fault" value="no" <?= $v('passenger_fault')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

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

      <div class="mt8">
        <div>Er det en aaben billet uden afgangstid?</div>
        <label><input type="radio" name="open_ticket_without_departure_time" value="yes" <?= $v('open_ticket_without_departure_time')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="open_ticket_without_departure_time" value="no" <?= $v('open_ticket_without_departure_time')==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <input type="hidden" name="season_ticket" value="<?= h(((string)($form['ticket_upload_mode'] ?? '') === 'seasonpass') ? 'yes' : 'no') ?>" />

      <?php if (!empty($ferryScope) || !empty($ferryContract) || !empty($ferryRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($ferryScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Art. 17: <?= !empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']) ? 'Ja' : 'Nej' ?></div>
          <div>Art. 18: <?= !empty($ferryRights['gate_art18']) ? 'Ja' : 'Nej' ?></div>
          <div>Art. 19: <?= !empty($ferryRights['gate_art19']) ? ('Ja (' . h((string)($ferryRights['art19_comp_band'] ?? '')) . '%)') : 'Nej' ?></div>
        </div>
      <?php endif; ?>
    <?php elseif ($isBus): ?>
      <strong><span aria-hidden="true">&#x1F68C;</span> Haendelse (bus / EU 181/2011)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore information, assistance, rerouting/refund og evt. 50% kompensation ved manglende valg mellem refund og ombooking.</p>

      <div class="mt8">
        <div>Haendelsestype</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
      </div>

      <div class="mt8">
        <label>Afgangsforsinkelse (minutter)
          <input type="number" name="delay_minutes_departure" min="0" step="1" value="<?= h($v('delay_minutes_departure')) ?>" placeholder="130" />
        </label>
      </div>

      <div class="mt8">
        <label>Planlagt rejsevarighed (minutter)
          <input type="number" name="scheduled_journey_duration_minutes" min="0" step="1" value="<?= h($v('scheduled_journey_duration_minutes')) ?>" placeholder="240" />
        </label>
      </div>

      <div class="mt8">
        <div>Var der overbooking / manglende plads?</div>
        <label><input type="radio" name="overbooking" value="yes" <?= $v('overbooking')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="overbooking" value="no" <?= $v('overbooking')==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <div class="mt8">
        <div>Tilboed operatoeren valg mellem refund og ombooking?</div>
        <label><input type="radio" name="carrier_offered_choice" value="yes" <?= $v('carrier_offered_choice')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="carrier_offered_choice" value="no" <?= $v('carrier_offered_choice')==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <div class="mt8">
        <div>Er det en aaben billet uden afgangstid?</div>
        <label><input type="radio" name="open_ticket_without_departure_time" value="yes" <?= $v('open_ticket_without_departure_time')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="open_ticket_without_departure_time" value="no" <?= $v('open_ticket_without_departure_time')==='no'?'checked':'' ?> /> Nej</label>
      </div>

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
      <p class="small muted">TRIN 5 bruges til at afgore care, rerouting/refund og kompensation for flighten eller den protected connection der blev ramt.</p>

      <div class="mt8">
        <div>Haendelsestype</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Lang forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
        <label class="ml8"><input type="radio" name="incident_main" value="denied_boarding" <?= $v('incident_main')==='denied_boarding'?'checked':'' ?> /> Nægtet boarding</label>
        <label class="ml8"><input type="radio" name="incident_main" value="missed_connection" <?= $v('incident_main')==='missed_connection'?'checked':'' ?> /> Misset protected connection</label>
      </div>

      <div class="mt8">
        <label>Forsinkelse ved ankomst (minutter)
          <input type="number" name="arrival_delay_minutes" min="0" step="1" value="<?= h($v('arrival_delay_minutes')) ?>" placeholder="185" />
        </label>
      </div>

      <div class="mt8">
        <label>Forsinkelse ved afgang (minutter, valgfri)
          <input type="number" name="delay_minutes_departure" min="0" step="1" value="<?= h($v('delay_minutes_departure')) ?>" placeholder="90" />
        </label>
      </div>

      <div class="mt8" data-show-if="incident_main:denied_boarding">
        <div>Var boarding nægtet mod din vilje?</div>
        <label><input type="radio" name="voluntary_denied_boarding" value="no" <?= $v('voluntary_denied_boarding')==='no'?'checked':'' ?> /> Ja, ufrivilligt</label>
        <label class="ml8"><input type="radio" name="voluntary_denied_boarding" value="yes" <?= $v('voluntary_denied_boarding')==='yes'?'checked':'' ?> /> Nej, frivilligt</label>
      </div>
      <input type="hidden" name="boarding_denied" value="<?= h(in_array($v('incident_main'), ['denied_boarding'], true) ? 'yes' : $v('boarding_denied')) ?>" />

      <div class="mt8" data-show-if="incident_main:missed_connection">
        <div>Var forbindelsen beskyttet paa samme booking/PNR?</div>
        <label><input type="radio" name="protected_connection_missed" value="yes" <?= $v('protected_connection_missed')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="protected_connection_missed" value="no" <?= $v('protected_connection_missed')==='no'?'checked':'' ?> /> Nej / uklart</label>
      </div>

      <div class="mt8">
        <div>Tilboed operatoeren ombooking?</div>
        <label><input type="radio" name="reroute_offered" value="yes" <?= $v('reroute_offered')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="reroute_offered" value="no" <?= $v('reroute_offered')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8" data-show-if="reroute_offered:yes">
        <label>Forsinkelse ved ankomst efter ombooking (minutter, valgfri)
          <input type="number" name="reroute_arrival_delay_minutes" min="0" step="1" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" placeholder="95" />
        </label>
      </div>

      <div class="mt8">
        <div>Tilboed operatoeren maaltider/forfriskninger?</div>
        <label><input type="radio" name="meal_offered" value="yes" <?= $v('meal_offered')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $v('meal_offered')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8">
        <div>Var hotel/overnatning noedvendig?</div>
        <label><input type="radio" name="hotel_required" value="yes" <?= $v('hotel_required')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="hotel_required" value="no" <?= $v('hotel_required')==='no'?'checked':'' ?> /> Nej</label>
      </div>

      <div class="mt8" data-show-if="hotel_required:yes">
        <div>Tilboed operatoeren hotel?</div>
        <label><input type="radio" name="hotel_offered" value="yes" <?= $v('hotel_offered')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $v('hotel_offered')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8">
        <div>Paaberaaber flyselskabet extraordinary circumstances?</div>
        <label><input type="radio" name="extraordinary_circumstances" value="yes" <?= $v('extraordinary_circumstances')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="extraordinary_circumstances" value="no" <?= $v('extraordinary_circumstances')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <?php if (!empty($airScope) || !empty($airContract) || !empty($airRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($airScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($airContract['primary_claim_party_name'] ?? ($airContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Care: <?= !empty($airRights['gate_air_care']) ? 'Ja' : 'Nej' ?></div>
          <div>Reroute/refund: <?= !empty($airRights['gate_air_reroute_refund']) ? 'Ja' : 'Nej' ?></div>
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

  <?php if (!$isFerry): ?>
  <!-- Mistet forbindelse -->
  <div class="card mt12">
    <strong><span aria-hidden="true">&#128206;</span> Mistet forbindelse</strong>
    <p class="small muted">Marker kun hvis du faktisk missede et skift.</p>

    <div class="mt4">
      <div>Mistede du en forbindelse pga. haendelsen?</div>
      <label><input type="radio" name="incident_missed" value="yes" <?= $v('incident_missed')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="incident_missed" value="no" <?= $v('incident_missed')==='no'?'checked':'' ?> /> Nej</label>
      <?php if ($segCount < 2): ?>
        <div class="small muted mt4">Vi fandt ingen skift paa billetterne &ndash; marker kun hvis du faktisk missede et skift.</div>
      <?php endif; ?>
    </div>

    <div class="mt4" data-show-if="incident_missed:yes">
      <div class="card" style="margin-top:8px;">
        <?= $this->element('missed_connection_block', compact('meta','form')) ?>
      </div>
      <div id="missed60Wrap" class="mt8">
        <div>Betyder det missede skift, at du forventer at ankomme mindst 60 minutter senere til din endelige destination?</div>
        <label><input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        <div class="small muted mt4">Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isFerry): ?>
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

  <?php if (!$isFerry): ?>
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
    <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
  </div>

  </fieldset>
  <?= $this->Form->end() ?>

  <?= $this->element('hooks_panel') ?>
</div>

<script>
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
  updateStep4State();
});
document.addEventListener('DOMContentLoaded', function(){
  updateReveal();
  updateStep4State();
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
  var missed = getRadioValue('incident_missed');
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
