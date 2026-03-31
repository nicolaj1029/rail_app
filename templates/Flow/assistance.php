<?php

/** @var \App\View\AppView $this */

$form     = $form ?? [];

$flags    = $flags ?? [];

$incident = $incident ?? [];

$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);

$pmrUser      = strtolower((string)($form['pmr_user'] ?? $flags['pmr_user'] ?? '')) === 'yes';
$travelState  = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$multimodal = (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['gating_mode'] ?? ($meta['gating_mode'] ?? ($form['transport_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? 'rail'))))));
$isFerry = ($transportMode === 'ferry');
$isBus = ($transportMode === 'bus');
$isAir = ($transportMode === 'air');
$isAirShortView = $isAir && strtolower((string)($flags['entry_variant'] ?? '')) === 'air_short';
$isAirShortOngoingView = $isAirShortView && $isOngoing;
$entryVariant = strtolower((string)($flags['entry_variant'] ?? ''));
$isModeSplitView = in_array($entryVariant, ['rail_split', 'bus_split', 'ferry_split'], true);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$busContract = (array)($multimodal['bus_contract'] ?? []);
$busRights = (array)($multimodal['bus_rights'] ?? []);
$busPmrRights = (array)($multimodal['bus_pmr_rights'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$airContract = (array)($multimodal['air_contract'] ?? []);
$airRights = (array)($multimodal['air_rights'] ?? []);
$airPriorityAssistGate = !empty($airRights['gate_air_art11_priority_assistance']);
$airPmrCompanion = !empty($airRights['air_pmr_companion']);
$airPmrServiceDog = !empty($airRights['air_pmr_service_dog']);
$airUnaccompaniedMinor = !empty($airRights['air_unaccompanied_minor']);
$assistTitle = $isOngoing
    ? ($isFerry ? 'TRIN 8 - Assistance under faergerejsen (igangvaerende rejse)' : ($isBus ? 'TRIN 8 - Assistance under busturen (igangvaerende rejse)' : ($isAir ? 'TRIN 8 - Care under flight-forloebet (igangvaerende rejse)' : 'TRIN 8 - Mad og drikke, hotel (igangvaerende rejse)')))
    : ($isCompleted ? ($isFerry ? 'TRIN 8 - Assistance under faergerejsen (afsluttet rejse)' : ($isBus ? 'TRIN 8 - Assistance under busturen (afsluttet rejse)' : ($isAir ? 'TRIN 8 - Care under flight-forloebet (afsluttet rejse)' : 'TRIN 8 - Mad og drikke, hotel (afsluttet rejse)'))) : ($isFerry ? 'TRIN 8 - Assistance (faerge Art. 17)' : ($isBus ? 'TRIN 8 - Assistance (bus)' : ($isAir ? 'TRIN 8 - Care (flight)' : 'TRIN 8 - Mad og drikke, hotel (Art. 20)'))));
$assistHint = $isOngoing
    ? ($isFerry ? 'Registrer maaltider, hotel og egne udgifter under faergeforloebet indtil nu.' : ($isBus ? 'Registrer maaltider, hotel og egne udgifter under busforloebet indtil nu.' : ($isAir ? 'Registrer maaltider, hotel og egne udgifter under flight-forloebet indtil nu.' : 'Udgifter indtil nu (du kan tilfoeje flere senere).')))
    : ($isCompleted ? ($isFerry ? 'Registrer den assistance der blev tilbudt eller de udgifter du selv afholdt under faergerejsen.' : ($isBus ? 'Registrer den assistance der blev tilbudt eller de udgifter du selv afholdt under busturen.' : ($isAir ? 'Registrer den care der blev tilbudt eller de udgifter du selv afholdt under flight-forloebet.' : 'Udgifter under hele rejsen.'))) : '');
$assistMealsOff = ($articles['art20_2a'] ?? ($articles['art20_2'] ?? true)) === false;
$assistHotelOff = ($articles['art20_2b'] ?? ($articles['art20_2'] ?? true)) === false;
$assistTrackOff = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
$assistStationOff = ($articles['art20_3'] ?? true) === false;
$assistOff    = ($articles['art20_2'] ?? true) === false || ($assistMealsOff && $assistHotelOff && $assistTrackOff && $assistStationOff);
$art20Active = $art20Active ?? true;
$art20Partial = $art20Partial ?? false;
$art20Blocked = $art20Blocked ?? false;
$isPreview = !empty($flowPreview);
$airNextDayDeparture = strtolower((string)($form['air_next_day_departure'] ?? ''));
$overnightNeeded = strtolower((string)($form['overnight_needed'] ?? ''));
$ferryMealsGateActive = ((string)($flags['gate_ferry_art17_refreshments'] ?? '') === '1') || !empty($ferryRights['gate_art17_refreshments']);
$ferryHotelGateActive = ((string)($flags['gate_ferry_art17_hotel'] ?? '') === '1') || !empty($ferryRights['gate_art17_hotel']);
$busMealsGateActive = ((string)($flags['gate_bus_assistance_refreshments'] ?? '') === '1') || !empty($busRights['gate_bus_assistance_refreshments']);
$busHotelGateActive = ((string)($flags['gate_bus_assistance_hotel'] ?? '') === '1') || !empty($busRights['gate_bus_assistance_hotel']);
$busPmrAssistGateActive = $busPmrAssistGateActive ?? (((string)($flags['gate_bus_pmr_assistance'] ?? '') === '1') || !empty($busPmrRights['gate_bus_pmr_assistance']));
$busPmrAssistPartialActive = $busPmrAssistPartialActive ?? (((string)($flags['gate_bus_pmr_assistance_partial'] ?? '') === '1') || !empty($busPmrRights['gate_bus_pmr_assistance_partial']));
$busPmrCompanion = !empty($busPmrRights['pmr_companion']);
$busPmrNotice36h = !empty($busPmrRights['pmr_notice_36h']);
$busPmrMetTerminalTime = !empty($busPmrRights['pmr_met_terminal_time']);
$busPmrSpecialSeatingNotified = !empty($busPmrRights['pmr_special_seating_notified']);
$modeMealsSectionVisible = $isFerry ? $ferryMealsGateActive : ($isBus ? $busMealsGateActive : true);
$modeHotelSectionVisible = $isFerry ? $ferryHotelGateActive : ($isBus ? $busHotelGateActive : true);
if ($isAir || $isBus) {
  $assistMealsOff = false;
  $assistHotelOff = false;
  $assistTrackOff = false;
  $assistStationOff = false;
  $assistOff = false;
}



$currencyOptions = [

    'EUR' => 'EUR - Euro',

    'DKK' => 'DKK - Dansk krone',

    'SEK' => 'SEK - Svensk krona',

    'NOK' => 'NOK - Norsk krone',

    'GBP' => 'GBP - Britisk pund',

    'CHF' => 'CHF - Schweizisk franc',

    'PLN' => 'PLN - Polsk zloty',

    'CZK' => 'CZK - Tjekkisk koruna',

    'HUF' => 'HUF - Ungarsk forint',

    'BGN' => 'BGN - Bulgarsk lev',

    'RON' => 'RON - Rumænsk leu',

];



$v = fn(string $k): string => (string)($form[$k] ?? '');

$priceHints = $priceHints ?? ($meta['price_hints'] ?? $form['price_hints'] ?? []);

$hintText = function (string $key) use ($priceHints): string {

    if (!is_array($priceHints)) { return ''; }

    $h = $priceHints[$key] ?? null;

    if (!is_array($h) || !isset($h['min'], $h['max'], $h['currency'])) { return ''; }

    $min = number_format((float)$h['min'], 0, ',', '.');

    $max = number_format((float)$h['max'], 0, ',', '.');

    return "Typisk interval: {$min}–{$max} {$h['currency']}";

};

$capFx = [
    'EUR' => 1.0,
    'DKK' => 7.45,
    'SEK' => 11.0,
    'BGN' => 1.96,
    'CZK' => 25.0,
    'HUF' => 385.0,
    'PLN' => 4.35,
    'RON' => 4.95,
    'NOK' => 11.6,
    'GBP' => 0.86,
];
$toEur = static function ($amount, string $currency) use ($capFx): float {
    $amount = is_numeric($amount) ? (float)$amount : (float)preg_replace('/[^0-9.]/', '', (string)$amount);
    $currency = strtoupper(trim($currency));
    if ($amount <= 0 || !isset($capFx[$currency]) || $capFx[$currency] <= 0) {
        return $amount;
    }

    return $amount / $capFx[$currency];
};
$ferryOpenTicket = $isFerry && strtolower(trim((string)($form['open_ticket_without_departure_time'] ?? ''))) === 'yes';
$ferryNotifiedBefore = $isFerry && strtolower(trim((string)($form['informed_before_purchase'] ?? ''))) === 'yes';
$ferryWeatherRisk = $isFerry && strtolower(trim((string)($form['weather_safety'] ?? ''))) === 'yes';
$ferryMealAmountEur = $toEur($form['meal_self_paid_amount'] ?? '0', (string)($form['meal_self_paid_currency'] ?? 'EUR'));
$ferryHotelAmountRaw = is_numeric($form['hotel_self_paid_amount'] ?? null) ? (float)$form['hotel_self_paid_amount'] : (float)preg_replace('/[^0-9.]/', '', (string)($form['hotel_self_paid_amount'] ?? '0'));
$ferryHotelCurrency = (string)($form['hotel_self_paid_currency'] ?? 'EUR');
$ferryHotelAmountEur = $toEur($ferryHotelAmountRaw, $ferryHotelCurrency);
$ferryHotelNightsCurrent = is_numeric($form['hotel_self_paid_nights'] ?? null) ? (float)$form['hotel_self_paid_nights'] : (float)preg_replace('/[^0-9.]/', '', (string)($form['hotel_self_paid_nights'] ?? '0'));
$ferryHotelRateEur = $ferryHotelNightsCurrent > 0 ? ($ferryHotelAmountEur / max($ferryHotelNightsCurrent, 1)) : 0.0;
$ferryHotelTransportAmountEur = $toEur($form['hotel_transport_self_paid_amount'] ?? '0', (string)($form['hotel_transport_self_paid_currency'] ?? 'EUR'));

?>



<style>

  .hidden { display:none; }

  .small { font-size:12px; }

  .muted { color:#666; }

  .hl { background:#fff3cd; padding:6px; border-radius:6px; }

  .mt4 { margin-top:4px; }

  .mt8 { margin-top:8px; }

  .mt12 { margin-top:12px; }

  .ml8 { margin-left:8px; }

  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }

  .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }

  [data-show-if] { display:none; }
  /* Locked preview should not expand conditional branches based on previous answers. */
  .flow-preview [data-show-if] { display:none !important; }

  /* Inline icon badges to avoid emoji encoding issues in headings */
  .icon-badge { width:26px; height:26px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; border:1px solid #d0d7de; background:#f8f9fb; }
  .icon-badge svg { width:16px; height:16px; display:block; }
  .icon-badge.hotel { background:#eef7ff; border-color:#cfe0ff; }
  .icon-badge.hotel svg path { fill:#1e3a8a; }

</style>



<h1><?= h($assistTitle) ?></h1>
<?php if ($isAirShortView): ?>
<?= $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract')) ?>
<?php elseif ($isModeSplitView): ?>
<div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
  <?= $isOngoing
      ? 'Dette assistance-trin er den igangvaerende variant. Registrer kun de ydelser eller udgifter, der er aktuelle lige nu.'
      : 'Dette assistance-trin er den afsluttede variant. Fokus er paa de udgifter og manglende ydelser, der faktisk opstod under rejsen.' ?>
</div>
<?php endif; ?>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['type' => 'file', 'novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
<?php if ($isFerry): ?>
  <input type="hidden" name="ferry_refreshments_offered" value="<?= h((string)($form['ferry_refreshments_offered'] ?? ($form['meal_offered'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_refreshments_self_paid_amount" value="<?= h((string)($form['ferry_refreshments_self_paid_amount'] ?? ($form['meal_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_refreshments_self_paid_currency" value="<?= h((string)($form['ferry_refreshments_self_paid_currency'] ?? ($form['meal_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_offered" value="<?= h((string)($form['ferry_hotel_offered'] ?? ($form['hotel_offered'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_overnight_required" value="<?= h((string)($form['ferry_overnight_required'] ?? ($form['overnight_needed'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_transport_included" value="<?= h((string)($form['ferry_hotel_transport_included'] ?? ($form['assistance_hotel_transport_included'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_amount" value="<?= h((string)($form['ferry_hotel_self_paid_amount'] ?? ($form['hotel_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_currency" value="<?= h((string)($form['ferry_hotel_self_paid_currency'] ?? ($form['hotel_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="ferry_hotel_self_paid_nights" value="<?= h((string)($form['ferry_hotel_self_paid_nights'] ?? ($form['hotel_self_paid_nights'] ?? ''))) ?>" />
<?php elseif ($isBus): ?>
  <input type="hidden" name="bus_refreshments_offered" value="<?= h((string)($form['bus_refreshments_offered'] ?? ($form['meal_offered'] ?? ''))) ?>" />
  <input type="hidden" name="bus_refreshments_self_paid_amount" value="<?= h((string)($form['bus_refreshments_self_paid_amount'] ?? ($form['meal_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="bus_refreshments_self_paid_currency" value="<?= h((string)($form['bus_refreshments_self_paid_currency'] ?? ($form['meal_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="bus_hotel_offered" value="<?= h((string)($form['bus_hotel_offered'] ?? ($form['hotel_offered'] ?? ''))) ?>" />
  <input type="hidden" name="bus_overnight_required" value="<?= h((string)($form['bus_overnight_required'] ?? ($form['overnight_needed'] ?? ''))) ?>" />
  <input type="hidden" name="bus_hotel_transport_included" value="<?= h((string)($form['bus_hotel_transport_included'] ?? ($form['assistance_hotel_transport_included'] ?? ''))) ?>" />
  <input type="hidden" name="bus_hotel_self_paid_amount" value="<?= h((string)($form['bus_hotel_self_paid_amount'] ?? ($form['hotel_self_paid_amount'] ?? ''))) ?>" />
  <input type="hidden" name="bus_hotel_self_paid_currency" value="<?= h((string)($form['bus_hotel_self_paid_currency'] ?? ($form['hotel_self_paid_currency'] ?? ''))) ?>" />
  <input type="hidden" name="bus_hotel_self_paid_nights" value="<?= h((string)($form['bus_hotel_self_paid_nights'] ?? ($form['hotel_self_paid_nights'] ?? ''))) ?>" />
<?php elseif ($isAir): ?>
  <input type="hidden" name="air_meals_offered" value="<?= h((string)($form['air_meals_offered'] ?? ($form['meal_offered'] ?? ''))) ?>" />
  <input type="hidden" name="air_refreshments_offered" value="<?= h((string)($form['air_refreshments_offered'] ?? ($form['meal_offered'] ?? ''))) ?>" />
  <input type="hidden" name="air_hotel_offered" value="<?= h((string)($form['air_hotel_offered'] ?? ($form['hotel_offered'] ?? ''))) ?>" />
  <input type="hidden" name="air_hotel_transport_included" value="<?= h((string)($form['air_hotel_transport_included'] ?? ($form['assistance_hotel_transport_included'] ?? ''))) ?>" />
<?php endif; ?>



<p class="small muted">
  <?= $isFerry
      ? 'Aktiveres ved aflysning eller forventet/faktisk afgangsforsinkelse paa mindst 90 minutter. Hoteldelen kan bortfalde ved vejrsikkerhed.'
      : ($isBus
          ? 'Aktiveres ved aflysning, overbooking eller afgangsforsinkelse paa mindst 90/120 minutter. Hoteldelen kan bortfalde ved svaert vejr eller naturkatastrofe.'
          : ($isAir
              ? 'Aktiveres ved delay naar Art. 6-threshold er naet, ved aflysning, naegtet boarding eller beskyttet misset forbindelse. Hotel behandles kun hvis ny afgang foerst var dagen efter.'
              : 'Aktiveres ved forsinkelse =60 min, aflysning eller afbrudt forbindelse. Ekstraordinære forhold påvirker kun hotel-loft (max 3 nætter).')) ?>
</p>

<?php if ($assistHint !== ''): ?>
  <p class="small muted"><?= h($assistHint) ?></p>
<?php endif; ?>

<?php if ($isFerry): ?>
  <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
    <strong>Faerge-kontekst</strong>
    <div class="small muted mt4">Claim-kanal: <strong><?= h((string)($ferryContract['primary_claim_party_name'] ?? 'ukendt')) ?></strong>. Denne side samler ferry Art. 17-assistance.</div>
    <?php if (!empty($ferryScope['scope_exclusion_reason'])): ?>
      <div class="small muted mt4">Scope-note: <?= h((string)$ferryScope['scope_exclusion_reason']) ?></div>
    <?php endif; ?>
  </div>
  <?php if ($ferryOpenTicket): ?>
    <div class="card mt8" style="border-color:#f3d9a4;background:#fff8e8;">
      <strong>Ferry Art. 17 er ikke aktiv ved aaben billet uden afgangstid</strong>
      <div class="small muted mt4">Aabne billetter uden afgangstid er som udgangspunkt undtaget fra assistance, medmindre der er tale om abonnement eller periodekort.</div>
    </div>
  <?php elseif ($ferryNotifiedBefore): ?>
    <div class="card mt8" style="border-color:#f3d9a4;background:#fff8e8;">
      <strong>Ferry Art. 17 er foreloebigt bortfaldet</strong>
      <div class="small muted mt4">Hvis passageren blev underrettet om aflysning eller forsinkelse inden billetkoeb, falder Art. 17 assistance bort.</div>
    </div>
  <?php else: ?>
    <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
      <strong>Dine lovbestemte rettigheder</strong>
      <div class="small muted mt4">Maaltider/snacks/forfriskninger: <strong>rimeligt i forhold til ventetiden</strong>. Hotel paa land: <strong>op til 80 EUR pr. nat i maks. 3 naetter</strong>. Transport mellem havneterminal og hotel daekkes separat.<?= $ferryWeatherRisk ? ' Ved farligt vejr kan hotelretten bortfalde.' : '' ?></div>
    </div>
    <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
      <strong>Vores anbefalede dokumentationsniveau</strong>
      <div class="small muted mt4">Maaltider: vi anbefaler op til <strong>40 EUR pr. dag</strong> uden ekstra manuel kontrol. Lokal transport: vi anbefaler op til <strong>50 EUR pr. tur</strong>. Gem altid kvitteringer.</div>
    </div>
  <?php endif; ?>
<?php elseif ($isBus): ?>
  <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
    <strong>Bus-kontekst</strong>
    <div class="small muted mt4">Claim-kanal: <strong><?= h((string)($busContract['primary_claim_party_name'] ?? 'ukendt')) ?></strong>. Denne side samler bus-assistance ved terminalafgang efter busforordningen.</div>
    <?php if (!empty($busScope['scope_exclusion_reason'])): ?>
      <div class="small muted mt4">Scope-note: <?= h((string)$busScope['scope_exclusion_reason']) ?></div>
    <?php endif; ?>
  </div>
<?php elseif ($isAir && !$isAirShortView): ?>
  <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
    <strong>Air-kontekst</strong>
    <div class="small muted mt4">Claim-kanal: <strong><?= h((string)($airContract['primary_claim_party_name'] ?? 'ukendt')) ?></strong>. Denne side samler EC261 care efter forsinkelse, aflysning, naegtet boarding eller beskyttet misset forbindelse.</div>
    <div class="small muted mt4">Distancekategori: <strong><?= h((string)($airScope['air_distance_band'] ?? 'ukendt')) ?></strong><?php if (!empty($airScope['air_delay_threshold_hours'])): ?>. Art. 6 threshold: <strong><?= h((string)$airScope['air_delay_threshold_hours']) ?> timer</strong><?php endif; ?>.</div>
  </div>
<?php elseif ($isAirShortView): ?>
  <div class="card mt8" style="border-color:#d0d7de;background:#f8f9fb;">
    <strong>Air-care lige nu</strong>
    <div class="small muted mt4">Registrer kun de udgifter eller manglende ydelser, der er relevante indtil nu. Mere dokumentation kan laegges paa sagen bagefter.</div>
    <?php if ($isAirShortOngoingView): ?>
      <div class="small muted mt4">Hold dette trin kort: maaltider, hotel og transport til hotel. Mere saerlige eller tekniske forhold kan vurderes senere paa sagen.</div>
    <?php endif; ?>
  </div>
<?php endif; ?>



<?php if ($art20Partial): ?>
  <div class="card hl mt8">
      <strong><?= $isFerry ? 'Assistance er delvist aktiveret via saerhensyn.' : ($isBus ? 'Bus-assistance er delvist aktiveret.' : ($isAir ? 'Air-care er delvist aktiveret.' : 'Art. 20 er delvist aktiveret via PMR.')) ?></strong>
    <div class="small muted"><?= $isFerry ? 'Udfyld kun de dele der faktisk blev tilbudt eller maatte betales selv.' : ($isBus ? 'Udfyld kun de assistanceposter der faktisk blev tilbudt eller maatte betales selv.' : ($isAir ? 'Udfyld kun de care-poster der faktisk blev tilbudt eller maatte betales selv. Hotel kraever at ny forventet afgang foerst var dagen efter.' : 'Udfyld kun PMR-hensyn nedenfor. Måltider/hotel/transport vurderes først via standard hændelses-gating.')) ?></div>
  </div>
<?php elseif (!$art20Active): ?>
  <div class="card hl mt8">
    <?php if ($art20Blocked): ?>
      <strong><?= $isFerry ? 'Faerge-assistance er ikke aktiveret.' : ($isBus ? 'Bus-assistance er ikke aktiveret.' : ($isAir ? 'Air-care er ikke aktiveret.' : 'Art. 20 er ikke aktiveret.')) ?></strong>
      <div class="small muted"><?= $isFerry ? 'Betingelserne for ferry Art. 17 er ikke opfyldt ud fra dine svar i Trin 5.' : ($isBus ? 'Betingelserne for bus-assistance er ikke opfyldt ud fra dine svar i Trin 5.' : ($isAir ? 'Betingelserne for air-care er ikke opfyldt ud fra dine svar i Trin 5.' : 'Betingelserne er ikke opfyldt ud fra dine svar i Trin 4.')) ?></div>
    <?php else: ?>
      <strong><?= $isFerry ? 'Faerge-assistance afventer gating.' : ($isBus ? 'Bus-assistance afventer gating.' : ($isAir ? 'Air-care afventer gating.' : 'Art. 20 afventer gating.')) ?></strong>
      <div class="small muted"><?= $isFerry ? 'Ga tilbage til Trin 5 og udfyld aflysning/90-minutters afgangsforsinkelse for at aktivere ferry Art. 17.' : ($isBus ? 'Ga tilbage til Trin 5 og udfyld aflysning, overbooking eller terminalforsinkelse for at aktivere bus-assistance.' : ($isAir ? 'Ga tilbage til Trin 5 og udfyld air-haendelsen inkl. Art. 6 delay-threshold for at aktivere care.' : 'Ga tilbage til Trin 4 og udfyld haendelsen (inkl. 60-min. varsel), eller til Trin 3 hvis PMR/cykel skal aktivere Art. 20.')) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($assistOff): ?>

  <div class="card hl mt8">

    <?= $isFerry
      ? 'Assistance efter ferry Art. 17 kan vaere undtaget for denne rejse. Udfyld alligevel udgifterne, saa de kan indgaa i claim-assist og manuel vurdering.'
      : ($isBus
          ? 'Bus-assistance kan vaere undtaget for denne rejse eller denne forsinkelse. Udfyld alligevel udgifterne, saa de kan indgaa i claim-assist og manuel vurdering.'
          : ($isAir
              ? 'Air-care kan vaere begrænset i den konkrete situation. Udfyld alligevel udgifterne, saa de kan indgaa i claim-assist og manuel vurdering.'
              : 'Assistance efter Art. 20(2) kan være undtaget for denne rejse. Udfyld alligevel udgifterne, så behandler vi dem som refusion efter de gældende regler.')) ?>

  </div>

<?php endif; ?>



<div id="art20Core" class="<?= ($art20Active || $isPreview) ? '' : 'hidden' ?>">

<!-- Måltider / drikke -->
<div class="card mt12 <?= (($assistMealsOff || !$modeMealsSectionVisible) && !$isPreview) ? 'hidden' : '' ?>" data-art="20(2a),20(2)">
  <strong>🍽️ <?= $isFerry ? 'Måltider og forfriskninger (Art. 17)' : ($isBus ? 'Maaltider og forfriskninger (bus)' : ($isAir ? 'Maaltider og forfriskninger (flight Art. 9)' : 'Måltider og drikke (Art.20)')) ?></strong>
  <p class="small muted"><?= $isFerry ? 'Faergeoperatoeren skal tilbyde maaltider eller forfriskninger ved aflysning eller afgangsforsinkelse paa mindst 90 minutter, naar det er praktisk muligt.' : ($isBus ? 'Busoperatoeren skal tilbyde maaltider eller forfriskninger ved aflysning eller forsinkelse paa mindst 90 minutter, naar rejsen varer over 3 timer og assistancebetingelserne er opfyldt.' : ($isAir ? 'Flyselskabet skal tilbyde maaltider og forfriskninger, naar din delay eller haendelse har naet den relevante Art. 6-threshold.' : 'Jernbanen skal tilbyde forfriskninger ved aflysning eller ≥60 min. forsinkelse.')) ?></p>
  <?php if ($isBus): ?>
    <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">
      <strong>Bus-cap / maaltider</strong>
      <div class="muted mt4">Maaltider vurderes foreloebigt op til ca. <strong>20 EUR pr. forsinkelsestime</strong>. Det er en intern engine-cap, ikke et lovfast loft.</div>
    </div>
  <?php elseif ($isFerry): ?>
    <div class="small muted mt4">Lovbestemt maksimum: intet fast beloeb. Internt standardniveau: <strong>40 EUR pr. dag</strong>.</div>
  <?php endif; ?>
  <div class="mt8">
    <div>1. Fik du måltider eller forfriskninger?</div>
    <label><input type="radio" name="meal_offered" value="yes" <?= $v('meal_offered')==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="meal_offered" value="no" <?= $v('meal_offered')==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="mt4" data-show-if="meal_offered:no">
    <label>Måltider blev ikke tilbudt – hvorfor?
      <select name="assistance_meals_unavailable_reason">
        <option value="">Vælg</option>
        <?php foreach (['not_available'=>'Ikke til rådighed','unreasonable_terms'=>'Urimelige vilkår','closed'=>'Lukket','other'=>'Andet'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $v('assistance_meals_unavailable_reason')===$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="mt8" data-show-if="meal_offered:no">
    <div class="grid-3">
      <label>Valuta
        <select name="meal_self_paid_currency">
          <option value="">Vælg</option>
          <?php foreach ($currencyOptions as $code => $label): ?>
            <option value="<?= $code ?>" <?= strtoupper($v('meal_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div></div>
      <div></div>
    </div>

    <?php
      $mealAmtItems = $form['meal_self_paid_amount_items'] ?? [];
      $mealReceiptItems = $form['meal_self_paid_receipt_items'] ?? [];
      if (!is_array($mealAmtItems)) { $mealAmtItems = []; }
      if (!is_array($mealReceiptItems)) { $mealReceiptItems = []; }
      if (!$mealAmtItems && $v('meal_self_paid_amount') !== '') { $mealAmtItems = [$v('meal_self_paid_amount')]; }
      if (!$mealReceiptItems && $v('meal_self_paid_receipt') !== '') { $mealReceiptItems = [$v('meal_self_paid_receipt')]; }
      $mealCount = max(count($mealAmtItems), count($mealReceiptItems), 1);
    ?>
    <div id="mealItemsWrap" class="mt8">
      <?php for ($i = 0; $i < $mealCount; $i++): ?>
        <?php $mAmt = (string)($mealAmtItems[$i] ?? ''); $mRc = (string)($mealReceiptItems[$i] ?? ''); ?>
        <div class="grid-3 mt8 meal-item-row">
          <label>Beløb
            <input type="number" step="0.01" name="meal_self_paid_amount_items[]" value="<?= h($mAmt) ?>" />
          </label>
          <label class="small">Kvittering
            <input type="hidden" name="meal_self_paid_receipt_items_existing[]" value="<?= h($mRc) ?>" />
            <input type="file" name="meal_self_paid_receipt_items[]" accept=".pdf,.jpg,.jpeg,.png" />
            <?php if ($mRc !== ''): ?><div class="small muted mt4">Gemmer: <?= h(basename($mRc)) ?></div><?php endif; ?>
          </label>
          <div style="display:flex; align-items:flex-end; gap:8px;">
            <button type="button" class="button button-outline meal-remove-btn" <?= $mealCount <= 1 ? 'disabled' : '' ?>>Fjern</button>
          </div>
        </div>
      <?php endfor; ?>
    </div>
    <div class="mt8">
      <button type="button" class="button button-outline" id="mealAddBtn">+ Tilføj udgift</button>
      <span class="small muted ml8">Tilføj flere beløb/kvitteringer hvis du købte flere gange.</span>
    </div>

    <?php if (!$isBus && ($ht = $hintText('meals'))): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <?php if ($isFerry && $ferryMealAmountEur > 40): ?>
      <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">Kan kraeve manuel vurdering: forordningen har ikke et fast maaltidsbeloeb, men dette overstiger vores interne standardniveau paa 40 EUR pr. dag.</div>
    <?php endif; ?>
  </div>
</div>
<!-- Hotel / overnatning -->

<div class="card mt12 <?= (($assistHotelOff || !$modeHotelSectionVisible) && !$isPreview) ? 'hidden' : '' ?>" data-art="20(2b),20(2)">

  <strong>
    <span class="icon-badge hotel" title="Hotel / indkvartering">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M7 10h10a3 3 0 0 1 3 3v6h-2v-2H6v2H4v-8a3 3 0 0 1 3-3zm-1 5h12v-2a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v2zm1-9a2 2 0 1 1 0 4a2 2 0 0 1 0-4z"/>
      </svg>
    </span>
    <?= $isFerry ? 'Hotel og indkvartering (Art. 17)' : ($isBus ? 'Hotel og indkvartering (bus)' : ($isAir ? 'Hotel og indkvartering (flight Art. 9)' : 'Hotel og indkvartering (Art.20)')) ?>
  </strong>

  <p class="small muted"><?= $isFerry ? 'Hotel og transport hertil skal tilbydes, hvis overnatning bliver noedvendig efter aflysning eller afgangsforsinkelse paa mindst 90 minutter, med forbehold for vejrsikkerhed.' : ($isBus ? 'Hotel og transport hertil skal tilbydes ved aflysning eller lang forsinkelse, hvis nødvendigt.' : ($isAir ? 'Hotel og transport hertil bliver kun relevant, hvis den nye forventede afgang foerst er dagen efter den planlagte afgang.' : 'Hotel og transport hertil skal tilbydes ved aflysning eller lang forsinkelse, hvis nødvendigt.')) ?></p>

  <?php if ($isAir): ?>
  <div class="mt8">
    <div>1. Var den nye forventede afgang foerst dagen efter den planlagte afgang?</div>
    <label><input type="radio" name="air_next_day_departure" value="yes" <?= $airNextDayDeparture==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="air_next_day_departure" value="no" <?= $airNextDayDeparture==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="small muted mt4" data-show-if="air_next_day_departure:no">Hotel/overnatning er normalt ikke relevant i air-care, naar ny forventet afgang stadig er samme dag.</div>
  <?php elseif ($isBus): ?>
  <div class="mt8">
    <div>1. Blev overnatning noedvendig pga. aflysningen eller forsinkelsen?</div>
    <label><input type="radio" name="overnight_needed" value="yes" <?= $overnightNeeded==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $overnightNeeded==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="small muted mt4" data-show-if="overnight_needed:no">Hotel og indkvartering er kun relevant i bus-flowet, hvis overnatning faktisk blev noedvendig.</div>
  <?php elseif ($isFerry): ?>
  <div class="mt8">
    <div>1. Blev overnatning noedvendig pga. aflysningen eller forsinkelsen?</div>
    <label><input type="radio" name="overnight_needed" value="yes" <?= $overnightNeeded==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="overnight_needed" value="no" <?= $overnightNeeded==='no'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="small muted mt4" data-show-if="overnight_needed:no">Hotel og indkvartering er kun relevant i ferry-flowet, hvis overnatning faktisk blev noedvendig.</div>
  <?php endif; ?>

  <?php if ($isFerry): ?>
    <div class="small muted mt4">Lovbestemt maksimum: <strong>80 EUR pr. nat i maks. 3 naetter</strong>. Transport mellem havneterminal og hotel daekkes separat og er ikke omfattet af 80 EUR-cappen.</div>
    <?php if ($ferryWeatherRisk): ?>
      <div class="small mt4" style="background:#fff8e8; padding:8px; border-radius:6px;">Ved vejrforhold, der bringer sikker sejlads i fare, kan retten til hotel/overnatning bortfalde.</div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($isBus): ?>
    <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">
      <strong>Bus-cap / hotel</strong>
      <div class="muted mt4">Lovens hotel-loft er <strong>80 EUR pr. nat, maks. 2 naetter</strong>. Transport til/fra hotel vises med en intern standardcap paa <strong>50 EUR</strong> og omregnes senere til sagens valuta i resultatet.</div>
    </div>
  <?php endif; ?>
  <div<?= $isAir ? ' data-show-if="air_next_day_departure:yes"' : (($isFerry || $isBus) ? ' data-show-if="overnight_needed:yes"' : '') ?>>
  <div class="mt8">

    <div><?= $isAir ? '2. Fik du hotel/indkvartering plus transport hertil?' : '2. Fik du hotel/indkvartering plus transport hertil?' ?></div>

    <label><input type="radio" name="hotel_offered" value="yes" <?= $v('hotel_offered')==='yes'?'checked':'' ?> /> Ja</label>

    <label class="ml8"><input type="radio" name="hotel_offered" value="no" <?= $v('hotel_offered')==='no'?'checked':'' ?> /> Nej</label>

    <label class="ml8"><input type="radio" name="hotel_offered" value="irrelevant" <?= $v('hotel_offered')==='irrelevant'?'checked':'' ?> /> Ikke relevant</label>

  </div>

  <div class="mt4" data-show-if="hotel_offered:yes">

    <span>Indgik transport til hotellet?</span>

    <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="yes" <?= $v('assistance_hotel_transport_included')==='yes'?'checked':'' ?> /> Ja</label>

    <label class="ml8"><input type="radio" name="assistance_hotel_transport_included" value="no" <?= $v('assistance_hotel_transport_included')==='no'?'checked':'' ?> /> Nej</label>

  </div>

  <div class="mt8" data-show-if="assistance_hotel_transport_included:no">

    <div class="small muted">Angiv evt. egne udgifter til transport mellem stoppested/terminal og hotel.</div>
    <?php if ($isBus): ?><div class="small muted mt4">Bus bruger her en vejledende soft cap paa 50 EUR for transport til/fra hotel. I resultatet vises beloebet i sagens valuta.</div><?php endif; ?>
    <?php if ($isFerry): ?><div class="small muted mt4">Internt standardniveau: op til 50 EUR pr. tur og 150 EUR samlet for lokal transport uden ekstra manuel kontrol.</div><?php endif; ?>

    <div class="grid-3 mt4">

      <label>Transport til/fra hotel - beløb

        <input type="number" step="0.01" name="hotel_transport_self_paid_amount" value="<?= h($v('hotel_transport_self_paid_amount')) ?>" />

      </label>

      <label>Valuta

        <select name="hotel_transport_self_paid_currency">

          <option value="">Vælg</option>

          <?php foreach ($currencyOptions as $code => $label): ?>

            <option value="<?= $code ?>" <?= strtoupper($v('hotel_transport_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>

          <?php endforeach; ?>

        </select>

      </label>

      <label class="small">Kvittering

        <input type="file" name="hotel_transport_self_paid_receipt" accept=".pdf,.jpg,.jpeg,.png" />

      </label>

    </div>

    <?php if ($f = $v('hotel_transport_self_paid_receipt')): ?><div class="small muted mt4">Uploadet: <?= h(basename($f)) ?></div><?php endif; ?>

    <?php if (!$isBus && ($ht = $hintText('taxi'))): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <?php if ($isFerry && $ferryHotelTransportAmountEur > 50): ?>
      <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">Kan kraeve manuel vurdering: lokal transport overstiger vores interne standardniveau paa 50 EUR pr. tur.</div>
    <?php endif; ?>

  </div>

  <?php if (!$isFerry && !$isAir && !$isBus): ?>
  <div class="mt4" data-show-if="hotel_offered:no">

    <label>Var overnatning nødvendig selvom hotel ikke blev tilbudt?

      <select name="overnight_needed">

        <option value="">Vælg</option>

        <?php foreach (['yes'=>'Ja','no'=>'Nej'] as $val => $label): ?>

          <option value="<?= $val ?>" <?= $v('overnight_needed')===$val?'selected':'' ?>><?= $label ?></option>

        <?php endforeach; ?>

      </select>

    </label>

  </div>
  <?php endif; ?>

  <div class="mt8" data-show-if="hotel_offered:no">
    <div class="grid-3">
      <label>Valuta
        <select name="hotel_self_paid_currency">
          <option value="">Vælg</option>
          <?php foreach ($currencyOptions as $code => $label): ?>
            <option value="<?= $code ?>" <?= strtoupper($v('hotel_self_paid_currency')) === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div></div>
      <div></div>
    </div>

    <?php
      $hotelAmtItems = $form['hotel_self_paid_amount_items'] ?? [];
      $hotelNightItems = $form['hotel_self_paid_nights_items'] ?? [];
      $hotelReceiptItems = $form['hotel_self_paid_receipt_items'] ?? [];
      if (!is_array($hotelAmtItems)) { $hotelAmtItems = []; }
      if (!is_array($hotelNightItems)) { $hotelNightItems = []; }
      if (!is_array($hotelReceiptItems)) { $hotelReceiptItems = []; }
      if (!$hotelAmtItems && $v('hotel_self_paid_amount') !== '') { $hotelAmtItems = [$v('hotel_self_paid_amount')]; }
      if (!$hotelNightItems && $v('hotel_self_paid_nights') !== '') { $hotelNightItems = [$v('hotel_self_paid_nights')]; }
      if (!$hotelReceiptItems && $v('hotel_self_paid_receipt') !== '') { $hotelReceiptItems = [$v('hotel_self_paid_receipt')]; }
      $hotelCount = max(count($hotelAmtItems), count($hotelNightItems), count($hotelReceiptItems), 1);
    ?>
    <div id="hotelItemsWrap" class="mt8">
      <?php for ($i = 0; $i < $hotelCount; $i++): ?>
        <?php
          $hAmt = (string)($hotelAmtItems[$i] ?? '');
          $hN = (string)($hotelNightItems[$i] ?? '');
          $hRc = (string)($hotelReceiptItems[$i] ?? '');
        ?>
        <div class="grid-3 mt8 hotel-item-row">
          <label>Hotel/overnatning - beløb
            <input type="number" step="0.01" name="hotel_self_paid_amount_items[]" value="<?= h($hAmt) ?>" />
          </label>
          <label>Antal nætter
            <input type="number" step="1" name="hotel_self_paid_nights_items[]" value="<?= h($hN) ?>" />
          </label>
          <label class="small">Kvittering
            <input type="hidden" name="hotel_self_paid_receipt_items_existing[]" value="<?= h($hRc) ?>" />
            <input type="file" name="hotel_self_paid_receipt_items[]" accept=".pdf,.jpg,.jpeg,.png" />
            <?php if ($hRc !== ''): ?><div class="small muted mt4">Gemmer: <?= h(basename($hRc)) ?></div><?php endif; ?>
          </label>
        </div>
        <div class="mt4" style="display:flex; justify-content:flex-end;">
          <button type="button" class="button button-outline hotel-remove-btn" <?= $hotelCount <= 1 ? 'disabled' : '' ?>>Fjern</button>
        </div>
      <?php endfor; ?>
    </div>
    <div class="mt8">
      <button type="button" class="button button-outline" id="hotelAddBtn">+ Tilføj udgift</button>
      <span class="small muted ml8">Tilføj flere overnatninger/kvitteringer hvis relevant.</span>
    </div>

    <?php if (!$isBus && ($ht = $hintText('hotelPerNight'))): ?><div class="small muted mt4"><?= h($ht) ?></div><?php endif; ?>
    <?php if ($isFerry && $ferryHotelRateEur > 80): ?>
      <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">Lovens maksimum for hotel paa land er 80 EUR pr. nat pr. passager. Beloeb over dette kraever manuel vurdering.</div>
    <?php endif; ?>
    <?php if ($isFerry && $ferryHotelNightsCurrent > 3): ?>
      <div class="small mt4" style="background:#eef7ff; padding:8px; border-radius:6px;">Lovens maksimum er 3 naetter. Yderligere naetter kraever manuel vurdering.</div>
    <?php endif; ?>

  </div>

  </div>

</div>



<?php if (($isBus && ($busPmrAssistGateActive || $busPmrAssistPartialActive)) || ($isAir && $airPriorityAssistGate && !$isAirShortOngoingView) || (!$isFerry && !$isBus && $pmrUser && ($art20Active || $art20Partial))): ?>

  <div class="card mt12">

    <strong><?= $isAir ? 'Prioriteret assistance (Art. 11)' : ($isBus ? 'PMR-assistance (bus Art. 13-15)' : 'PMR-hensyn (Art. 20(5))') ?></strong>

    <?php if ($isAir): ?>
      <div class="small muted mt8">
        Care skal tilbydes saa hurtigt som muligt ved boardingafvisning, aflysning eller forsinkelse.
        <?php if ($airPmrCompanion || $airPmrServiceDog || $airUnaccompaniedMinor): ?>
          Relevant kontekst:
          <?= h(implode(', ', array_filter([
            $airPmrCompanion ? 'ledsager' : '',
            $airPmrServiceDog ? 'servicehund' : '',
            $airUnaccompaniedMinor ? 'uledsaget barn' : '',
          ]))) ?>.
        <?php endif; ?>
      </div>
    <?php elseif ($isBus): ?>
      <div class="small muted mt8">
        Bistand ved terminal og om bord skal ydes til PMR-passagerer, og der skal goeres rimelige anstrengelser, selv hvis 36-timers varslet ikke blev opfyldt.
        <?php if ($busPmrCompanion || $busPmrNotice36h || $busPmrMetTerminalTime || $busPmrSpecialSeatingNotified): ?>
          Relevant kontekst:
          <?= h(implode(', ', array_filter([
            $busPmrCompanion ? 'ledsager' : '',
            $busPmrNotice36h ? '36t-varsel givet' : '',
            $busPmrMetTerminalTime ? 'terminaltid moedt' : '',
            $busPmrSpecialSeatingNotified ? 'saerlige siddebehov oplyst' : '',
          ]))) ?>.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="mt8">

      <span><?= $isAir ? 'Blev prioriteret assistance anvendt saa hurtigt som muligt?' : ($isBus ? 'Blev PMR-assistance ydet ved terminalen eller om bord?' : 'Blev PMR-prioritet anvendt?') ?></span>

      <label><input type="radio" name="assistance_pmr_priority_applied" value="yes" <?= $v('assistance_pmr_priority_applied')==='yes'?'checked':'' ?> /> Ja</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_priority_applied" value="no" <?= $v('assistance_pmr_priority_applied')==='no'?'checked':'' ?> /> Nej</label>


    </div>

    <div class="mt8">

      <span><?= $isAir ? 'Blev ledsager/servicehund understoettet, naar det var relevant?' : ($isBus ? 'Blev ledsager eller saerlige behov understoettet, naar det var relevant?' : 'Blev ledsager/servicehund understøttet?') ?></span>

      <label><input type="radio" name="assistance_pmr_companion_supported" value="yes" <?= $v('assistance_pmr_companion_supported')==='yes'?'checked':'' ?> /> Ja</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_companion_supported" value="no" <?= $v('assistance_pmr_companion_supported')==='no'?'checked':'' ?> /> Nej</label>

      <label class="ml8"><input type="radio" name="assistance_pmr_companion_supported" value="not_applicable" <?= $v('assistance_pmr_companion_supported')==='not_applicable'?'checked':'' ?> /> Ikke relevant</label>

    </div>

  </div>

<?php endif; ?>

</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">

  <?= $this->Html->link('Tilbage', ['action' => 'remedies'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>

  <?= $this->Form->button('Næste ?', ['class' => 'button']) ?>

</div>



</fieldset>
<?= $this->Form->end() ?>



<script>

function updateReveal() {

  document.querySelectorAll('[data-show-if]').forEach(function(el) {

    var spec = el.getAttribute('data-show-if'); if (!spec) return;

    var parts = spec.split(':'); if (parts.length !== 2) return;

    var name = parts[0]; var valid = parts[1].split(',');

    var checked = document.querySelector('input[name="' + name + '"]:checked');

    var show = checked && valid.includes(checked.value);

    el.style.display = show ? 'block' : 'none';

    el.hidden = !show;

  });

}

document.addEventListener('change', function(e) {

  if (['meal_offered','hotel_offered','assistance_hotel_transport_included','air_next_day_departure','overnight_needed'].includes(e.target.name)) {

    updateReveal();

  }

});

document.addEventListener('DOMContentLoaded', updateReveal);

document.addEventListener('DOMContentLoaded', function() {
  function el(tag, attrs) {
    var e = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function(k) {
      if (k === 'text') { e.textContent = attrs[k]; }
      else if (k === 'html') { e.innerHTML = attrs[k]; }
      else { e.setAttribute(k, attrs[k]); }
    });
    return e;
  }

  function updateRemovers(container, selector) {
    if (!container) return;
    var rows = container.querySelectorAll(selector);
    var canRemove = rows.length > 1;
    container.querySelectorAll(selector + ' .meal-remove-btn,' + selector + ' .hotel-remove-btn,' + '.meal-remove-btn,.hotel-remove-btn').forEach(function(btn) {
      btn.disabled = !canRemove;
    });
  }

  function bindRemoveButtons() {
    document.querySelectorAll('.meal-remove-btn').forEach(function(btn) {
      if (btn.__bound) return;
      btn.__bound = true;
      btn.addEventListener('click', function() {
        var row = btn.closest('.meal-item-row');
        if (row) row.remove();
        var wrap = document.getElementById('mealItemsWrap');
        var remaining = wrap ? wrap.querySelectorAll('.meal-item-row') : [];
        if (wrap && remaining.length === 0) {
          // Always keep at least one row.
          document.getElementById('mealAddBtn') && document.getElementById('mealAddBtn').click();
        }
        // Re-evaluate remove state
        var cnt = wrap ? wrap.querySelectorAll('.meal-item-row').length : 0;
        document.querySelectorAll('.meal-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
      });
    });
    document.querySelectorAll('.hotel-remove-btn').forEach(function(btn) {
      if (btn.__bound) return;
      btn.__bound = true;
      btn.addEventListener('click', function() {
        // Hotel remove button is outside the grid; remove the previous .hotel-item-row block + itself container if any.
        var row = btn.closest('div');
        // Find nearest hotel item row above.
        var wrap = document.getElementById('hotelItemsWrap');
        var itemRow = btn.closest('.hotel-item-row') || (row ? row.previousElementSibling : null);
        if (itemRow && itemRow.classList && itemRow.classList.contains('hotel-item-row')) {
          // Also remove the button wrapper directly after the row if present.
          var next = itemRow.nextElementSibling;
          if (next && next.querySelector && next.querySelector('.hotel-remove-btn')) { next.remove(); }
          itemRow.remove();
        }
        var remaining = wrap ? wrap.querySelectorAll('.hotel-item-row') : [];
        if (wrap && remaining.length === 0) {
          document.getElementById('hotelAddBtn') && document.getElementById('hotelAddBtn').click();
        }
        var cnt = wrap ? wrap.querySelectorAll('.hotel-item-row').length : 0;
        document.querySelectorAll('.hotel-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
      });
    });
  }

  var mealAdd = document.getElementById('mealAddBtn');
  var mealWrap = document.getElementById('mealItemsWrap');
  if (mealAdd && mealWrap) {
    mealAdd.addEventListener('click', function() {
      var row = el('div', { class: 'grid-3 mt8 meal-item-row' });
      var l1 = el('label', { });
      l1.appendChild(document.createTextNode('Beløb'));
      l1.appendChild(el('input', { type: 'number', step: '0.01', name: 'meal_self_paid_amount_items[]' }));
      var l2 = el('label', { class: 'small' });
      l2.appendChild(document.createTextNode('Kvittering'));
      l2.appendChild(el('input', { type: 'hidden', name: 'meal_self_paid_receipt_items_existing[]', value: '' }));
      l2.appendChild(el('input', { type: 'file', name: 'meal_self_paid_receipt_items[]', accept: '.pdf,.jpg,.jpeg,.png' }));
      var l3 = el('div', { style: 'display:flex; align-items:flex-end; gap:8px;' });
      l3.appendChild(el('button', { type: 'button', class: 'button button-outline meal-remove-btn', text: 'Fjern' }));
      row.appendChild(l1);
      row.appendChild(l2);
      row.appendChild(l3);
      mealWrap.appendChild(row);
      bindRemoveButtons();
      var cnt = mealWrap.querySelectorAll('.meal-item-row').length;
      document.querySelectorAll('.meal-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
    });
  }

  var hotelAdd = document.getElementById('hotelAddBtn');
  var hotelWrap = document.getElementById('hotelItemsWrap');
  if (hotelAdd && hotelWrap) {
    hotelAdd.addEventListener('click', function() {
      var row = el('div', { class: 'grid-3 mt8 hotel-item-row' });
      var l1 = el('label', { });
      l1.appendChild(document.createTextNode('Hotel/overnatning - beløb'));
      l1.appendChild(el('input', { type: 'number', step: '0.01', name: 'hotel_self_paid_amount_items[]' }));
      var l2 = el('label', { });
      l2.appendChild(document.createTextNode('Antal nætter'));
      l2.appendChild(el('input', { type: 'number', step: '1', name: 'hotel_self_paid_nights_items[]' }));
      var l3 = el('label', { class: 'small' });
      l3.appendChild(document.createTextNode('Kvittering'));
      l3.appendChild(el('input', { type: 'hidden', name: 'hotel_self_paid_receipt_items_existing[]', value: '' }));
      l3.appendChild(el('input', { type: 'file', name: 'hotel_self_paid_receipt_items[]', accept: '.pdf,.jpg,.jpeg,.png' }));
      row.appendChild(l1);
      row.appendChild(l2);
      row.appendChild(l3);
      hotelWrap.appendChild(row);

      var btnRow = el('div', { class: 'mt4', style: 'display:flex; justify-content:flex-end;' });
      btnRow.appendChild(el('button', { type: 'button', class: 'button button-outline hotel-remove-btn', text: 'Fjern' }));
      hotelWrap.appendChild(btnRow);

      bindRemoveButtons();
      var cnt = hotelWrap.querySelectorAll('.hotel-item-row').length;
      document.querySelectorAll('.hotel-remove-btn').forEach(function(b) { b.disabled = cnt <= 1; });
    });
  }

  bindRemoveButtons();
  function syncModeAssistanceAliases() {
    var ferryMeals = document.querySelector('input[name="ferry_refreshments_offered"]');
    var busMeals = document.querySelector('input[name="bus_refreshments_offered"]');
    var airMeals = document.querySelector('input[name="air_meals_offered"]');
    var airRefreshments = document.querySelector('input[name="air_refreshments_offered"]');
    if (!ferryMeals && !busMeals && !airMeals && !airRefreshments) { return; }
    var getRadio = function(name) {
      var checked = document.querySelector('input[name="' + name + '"]:checked');
      return checked ? (checked.value || '') : '';
    };
    var getChoice = function(name) {
      var radio = getRadio(name);
      if (radio !== '') { return radio; }
      var el = document.querySelector('select[name="' + name + '"], input[name="' + name + '"]');
      return el ? (el.value || '') : '';
    };
    var getValue = function(name) {
      var el = document.querySelector('[name="' + name + '"]');
      return el ? (el.value || '') : '';
    };
    if (ferryMeals) { ferryMeals.value = getRadio('meal_offered'); }
    if (busMeals) { busMeals.value = getRadio('meal_offered'); }
    if (airMeals) { airMeals.value = getRadio('meal_offered'); }
    if (airRefreshments) { airRefreshments.value = getRadio('meal_offered'); }
    var ferryMealAmt = document.querySelector('input[name="ferry_refreshments_self_paid_amount"]');
    var ferryMealCur = document.querySelector('input[name="ferry_refreshments_self_paid_currency"]');
    var ferryHotel = document.querySelector('input[name="ferry_hotel_offered"]');
    var ferryNight = document.querySelector('input[name="ferry_overnight_required"]');
    var ferryHotelTransport = document.querySelector('input[name="ferry_hotel_transport_included"]');
    var ferryHotelAmt = document.querySelector('input[name="ferry_hotel_self_paid_amount"]');
    var ferryHotelCur = document.querySelector('input[name="ferry_hotel_self_paid_currency"]');
    var ferryHotelNights = document.querySelector('input[name="ferry_hotel_self_paid_nights"]');
    var busMealAmt = document.querySelector('input[name="bus_refreshments_self_paid_amount"]');
    var busMealCur = document.querySelector('input[name="bus_refreshments_self_paid_currency"]');
    var busHotel = document.querySelector('input[name="bus_hotel_offered"]');
    var busNight = document.querySelector('input[name="bus_overnight_required"]');
    var busHotelTransport = document.querySelector('input[name="bus_hotel_transport_included"]');
    var busHotelAmt = document.querySelector('input[name="bus_hotel_self_paid_amount"]');
    var busHotelCur = document.querySelector('input[name="bus_hotel_self_paid_currency"]');
    var busHotelNights = document.querySelector('input[name="bus_hotel_self_paid_nights"]');
    var airHotel = document.querySelector('input[name="air_hotel_offered"]');
    var airHotelTransport = document.querySelector('input[name="air_hotel_transport_included"]');
    if (ferryMealAmt) { ferryMealAmt.value = getValue('meal_self_paid_amount'); }
    if (ferryMealCur) { ferryMealCur.value = getValue('meal_self_paid_currency'); }
    if (ferryHotel) { ferryHotel.value = getRadio('hotel_offered'); }
    if (ferryNight) { ferryNight.value = getChoice('overnight_needed'); }
    if (ferryHotelTransport) { ferryHotelTransport.value = getRadio('assistance_hotel_transport_included'); }
    if (ferryHotelAmt) { ferryHotelAmt.value = getValue('hotel_self_paid_amount'); }
    if (ferryHotelCur) { ferryHotelCur.value = getValue('hotel_self_paid_currency'); }
    if (ferryHotelNights) { ferryHotelNights.value = getValue('hotel_self_paid_nights'); }
    if (busMealAmt) { busMealAmt.value = getValue('meal_self_paid_amount'); }
    if (busMealCur) { busMealCur.value = getValue('meal_self_paid_currency'); }
    if (busHotel) { busHotel.value = getRadio('hotel_offered'); }
    if (busNight) { busNight.value = getChoice('overnight_needed'); }
    if (busHotelTransport) { busHotelTransport.value = getRadio('assistance_hotel_transport_included'); }
    if (busHotelAmt) { busHotelAmt.value = getValue('hotel_self_paid_amount'); }
    if (busHotelCur) { busHotelCur.value = getValue('hotel_self_paid_currency'); }
    if (busHotelNights) { busHotelNights.value = getValue('hotel_self_paid_nights'); }
    if (airHotel) { airHotel.value = getRadio('hotel_offered'); }
    if (airHotelTransport) { airHotelTransport.value = getRadio('assistance_hotel_transport_included'); }
  }
  document.querySelectorAll('input[name="meal_offered"], input[name="hotel_offered"], input[name="overnight_needed"], select[name="overnight_needed"], input[name="assistance_hotel_transport_included"], input[name="meal_self_paid_amount"], input[name="meal_self_paid_currency"], input[name="hotel_self_paid_amount"], input[name="hotel_self_paid_currency"], input[name="hotel_self_paid_nights"]').forEach(function(el) {
    ['change','input','click'].forEach(function(ev){ el.addEventListener(ev, syncModeAssistanceAliases); });
  });
  syncModeAssistanceAliases();
});

</script>
