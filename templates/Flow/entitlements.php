<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
$form = $form ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$groupedTickets = $groupedTickets ?? [];
$hasTickets = !empty($groupedTickets) || !empty($form['_ticketFilename']) || !empty($meta['_multi_tickets']);
$hasUploadedTickets = !empty($groupedTickets);
$seasonMeta0 = (array)($meta['season_pass'] ?? []);
$fftSeason0 = strtolower((string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? '')));
$hasSeason0 = array_key_exists('has', $seasonMeta0) ? (bool)$seasonMeta0['has'] : ($fftSeason0 === 'pass');
$ticketMode = (string)($form['ticket_upload_mode'] ?? '');
if (!in_array($ticketMode, ['ticket','ticketless','seasonpass'], true)) { $ticketMode = ''; }
if ($ticketMode === '') { $ticketMode = $hasSeason0 ? 'seasonpass' : 'ticket'; }
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$transportModeSelectionRequired = in_array($ticketMode, ['ticketless', 'seasonpass'], true);
$transportModeAutoReady = !$transportModeSelectionRequired && ($hasTickets || !empty($meta['_auto']) || !empty($meta['_segments_auto']));
$transportModeSource = (string)($form['transport_mode_source'] ?? ($meta['transport_mode_source'] ?? ''));
$transportModeSeed = (string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? ''));
if ($transportModeSelectionRequired && $transportModeSource !== 'manual') {
  $transportModeSeed = '';
}
if ($transportModeSeed === '' && $transportModeAutoReady) {
  $transportModeSeed = (string)($multimodal['transport_mode'] ?? '');
}
$transportMode = strtolower($transportModeSeed);
if (!in_array($transportMode, ['rail','ferry','bus','air'], true)) { $transportMode = ''; }
$transportModeChosen = $transportMode !== '';
$transportModeReady = $transportModeChosen || (!$transportModeSelectionRequired && $transportModeAutoReady);
$transportModeRender = $transportModeChosen ? $transportMode : '';
$isFerry = $transportModeRender === 'ferry';
$isBus = $transportModeRender === 'bus';
$isAir = $transportModeRender === 'air';
$isRail = $transportModeRender === 'rail';
$isFerryTicketless = $isFerry && $ticketMode === 'ticketless';
$seasonSupported = $transportModeChosen && in_array($transportMode, ['rail', 'ferry'], true);
if (!$seasonSupported && $ticketMode === 'seasonpass') { $ticketMode = 'ticket'; }
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$modeContract = $isBus ? (array)($multimodal['bus_contract'] ?? []) : (($isAir ? (array)($multimodal['air_contract'] ?? []) : []));
$claimDirection = (array)($multimodal['claim_direction'] ?? []);
$isPreview = !empty($flowPreview);
$firstNonEmpty = static function (...$values): string {
  foreach ($values as $value) {
    $value = is_string($value) ? trim($value) : trim((string)$value);
    if ($value !== '') {
      return $value;
    }
  }
  return '';
};
$autoReturnSegment = null;
if ($transportMode === 'ferry') {
  $candidateSegments = [];
  if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
    $candidateSegments = array_values((array)$meta['_segments_auto']);
  } elseif (!empty($groupedTickets) && is_array($groupedTickets)) {
    foreach ((array)$groupedTickets as $group) {
      $segments = (array)($group['segments'] ?? []);
      if (!empty($segments)) {
        $candidateSegments = array_values($segments);
        break;
      }
    }
  }
  if (count($candidateSegments) >= 2) {
    $autoReturnSegment = (array)$candidateSegments[1];
  }
}
$transportModeLabel = match ($transportModeRender) {
  'ferry' => 'Færge',
  'bus' => 'Bus',
  'air' => 'Fly',
  'rail' => 'Tog',
  default => 'Ikke valgt endnu',
};
$primaryTicketExt = strtolower((string)pathinfo((string)($form['_ticketFilename'] ?? ''), PATHINFO_EXTENSION));
$primaryTicketIsImage = in_array($primaryTicketExt, ['png','jpg','jpeg','webp','bmp','tif','tiff','heic'], true);
$showGenericJourneyFields = !$transportModeChosen && $ticketMode !== 'ticketless';
$hasJourneyFieldPrefill = !empty($meta['_auto'])
  || !empty($form['operator'])
  || !empty($form['operator_country'])
  || !empty($form['operator_product'])
  || !empty($form['dep_date'])
  || !empty($form['dep_time'])
  || !empty($form['dep_station'])
  || !empty($form['arr_station'])
  || !empty($form['arr_time'])
  || !empty($form['train_no'])
  || !empty($form['ticket_no'])
  || !empty($form['price']);
$pinJourneyFieldsForUploadedMode = $ticketMode === 'ticket'
  && ($hasTickets || $hasJourneyFieldPrefill);
$forceJourneyFieldsOpen = $ticketMode === 'ticket'
  && $hasTickets
  && ($primaryTicketIsImage || !$transportModeChosen);
$journeyFieldsCollapsible = true;
$journeyFieldsOpenDefault = $ticketMode !== 'ticketless'
  && ($hasTickets || $hasJourneyFieldPrefill || ($transportModeChosen && !$isRail));
$uploadIntroTitle = (!$transportModeSelectionRequired && $ticketMode === 'ticket')
  ? 'Har du en booking eller billet du kan uploade?'
  : ($isAir ? 'Har du en booking eller billet du kan uploade?' : ($isFerry ? 'Har du en booking eller færgebillet du kan uploade?' : ($isBus ? 'Har du en busbillet eller booking du kan uploade?' : 'Har du en billet du kan uploade?')));
$uploadIntroText = ($ticketMode === 'ticket' && !$transportModeSelectionRequired)
  ? 'Ved upload bruger vi LLM/OCR og kontraktmotoren til at finde den relevante transportgren. Ticketless kræver stadig et manuelt valg.'
  : ($isRail ? 'Vælg ticketless hvis du vil lave et hurtigt estimat uden upload. Du kan altid uploade senere.' : 'Vælg ticketless hvis du vil fortsætte uden upload. Du kan altid tilføje booking eller dokumentation senere.');
?>
<?php echo $this->Html->css('flow-entitlements', ['block' => true]); ?>
<style>
  /* Skjul PMR/cykel i Trin 2 – håndteres i Trin 3 */
  #pmrFlowCard, #bikeFlowCard { display:none !important; }
  .fe-wrapper { max-width: 1200px; margin: 0 auto; }
  .fe-wide { width: 100%; }
  #ticketlessCard,
  #modeJourneyFields,
  #ticketlessCard .grid-2,
  #modeJourneyFields .grid-2,
  #ticketlessCard details,
  #modeJourneyFields details { overflow: visible; }
  /* TRIN 2 node autocomplete */
  #ticketlessCard label { position: relative; }
  #ticketlessCard .station-suggest,
  #ticketlessCard .node-suggest,
  #modeJourneyFields .node-suggest,
  body > .node-suggest.portal {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 2px);
    z-index: 50;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 8px 18px rgba(0,0,0,0.08);
    max-height: 220px;
    overflow: auto;
  }
  body > .node-suggest.portal {
    position: fixed;
    top: 0;
    right: auto;
    z-index: 2000;
  }
  #ticketlessCard .station-suggest button,
  #ticketlessCard .node-suggest button,
  #modeJourneyFields .node-suggest button {
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    padding: 8px 10px;
    cursor: pointer;
    font-size: 14px;
    color: #111 !important;
  }
  #ticketlessCard .station-suggest button:hover,
  #ticketlessCard .node-suggest button:hover,
  #modeJourneyFields .node-suggest button:hover { background: #f6f6f6; color: #111 !important; }
  #ticketlessCard .station-suggest button:active,
  #ticketlessCard .node-suggest button:active,
  #modeJourneyFields .node-suggest button:active { color: #111 !important; }
  #ticketlessCard .station-suggest button:focus,
  #ticketlessCard .node-suggest button:focus,
  #modeJourneyFields .node-suggest button:focus { outline: none; background: #f1f3f5; color: #111 !important; }
  #ticketlessCard .station-suggest .muted,
  #ticketlessCard .node-suggest .muted,
  #modeJourneyFields .node-suggest .muted { color: #666; font-size: 12px; }
  #ticketlessCard .ticketless-optional { display: none !important; }
  #journeyFields[data-upload-pinned="1"] { display: block !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode="rail"] #railJourneyFields { display: block !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode="rail"] #modeJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="rail"] #genericJourneyFields { display: none !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode="ferry"] #modeJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="bus"] #modeJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="air"] #modeJourneyFields { display: block !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode="ferry"] #railJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="ferry"] #genericJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="bus"] #railJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="bus"] #genericJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="air"] #railJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode="air"] #genericJourneyFields { display: none !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode=""] #genericJourneyFields { display: block !important; }
  #journeyFields[data-upload-pinned="1"][data-render-mode=""] #railJourneyFields,
  #journeyFields[data-upload-pinned="1"][data-render-mode=""] #modeJourneyFields { display: none !important; }
</style>
<div class="fe-header">
  <div class="fe-step">Trin 2</div>
  <h1 class="fe-title">Billet (upload eller ticketless)</h1>
  <p class="fe-sub">Upload billetter eller udfyld minimum uden billet. Sidepanelet viser løbende dine rettigheder.</p>
</div>
<?php
  // UI banners derived from exemption profile (global notices)
  $uiBanners = (array)($profile['ui_banners'] ?? []);
  if ($transportModeChosen && !$isRail) {
      $uiBanners = [];
  }
  $serviceWarnings = (array)($serviceWarnings ?? []);
  if (!empty($serviceWarnings)) {
      echo '<div class="small" style="margin-top:6px;">';
      foreach ($serviceWarnings as $ban) {
          echo '<div style="background:#ffecec; border:1px solid #e0aaaa; padding:6px; border-radius:6px; margin-top:6px;">' . h($ban) . '</div>';
      }
      echo '</div>';
  }
  if (!empty($uiBanners)) {
      echo '<div class="small" style="margin-top:6px;">';
      foreach ($uiBanners as $ban) {
          echo '<div style="background:#fff3cd; border:1px solid #eed27c; padding:6px; border-radius:6px; margin-top:6px;">' . h($ban) . '</div>';
      }
      echo '</div>';
  }
  $articles = (array)($profile['articles'] ?? []);
  $articlesSub = (array)($profile['articles_sub'] ?? []);
  // Per clarification: pricing, class, and pre-purchase disclosure all fall under Art. 9 stk. 1
  $showArt9_1 = !isset($articlesSub['art9_1']) || $articlesSub['art9_1'] !== false;
  $contractOptions = $contractOptions ?? [];
  // Use a literal path to avoid the URL builder selecting the /api/demo/v2 scope fallback route.
  $stationsSearchUrl = $this->Url->build('/api/stations/search');
  $transportNodesSearchUrl = $this->Url->build('/api/transport-nodes/search');
  // Offline operator/product catalog for ticketless suggestions (no tokens / no external calls).
  $opCatalog = new \App\Service\OperatorCatalog();
  $transportOperatorRegistry = new \App\Service\TransportOperatorRegistry();
  $opsByCountry = (array)$opCatalog->getOperators('rail');
  $productsByOperator = (array)$opCatalog->getProducts($transportModeChosen ? $transportModeRender : null);
  $countryToCurrency = ['BG'=>'BGN','CZ'=>'CZK','DK'=>'DKK','HU'=>'HUF','PL'=>'PLN','RO'=>'RON','SE'=>'SEK','NO'=>'NOK','GB'=>'GBP','CH'=>'CHF'];
  // Ticketless: country prefill. Default DK only when nothing else is known.
  $journey = $journey ?? [];
  $tlCountry = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ($journey['country']['value'] ?? '')))));
  $tlCountryAssumed = (string)($form['operator_country_assumed'] ?? '0');
  if ($tlCountry === '') {
      $tlCountry = 'DK';
      $tlCountryAssumed = '1';
  }
  $operatorToCountry = [];
  foreach ($opsByCountry as $cc => $ops) {
      foreach ((array)$ops as $name => $label) { $operatorToCountry[(string)$name] = (string)$cc; }
  }
  $allOperators = array_keys($operatorToCountry);
  sort($allOperators);
  $ferryOperators = $transportOperatorRegistry->namesByMode('ferry');
  $busOperators = $transportOperatorRegistry->namesByMode('bus');
  $airOperators = $transportOperatorRegistry->namesByMode('air');
  $transportOperatorEntries = [
      'ferry' => $transportOperatorRegistry->entriesByMode('ferry'),
      'bus' => $transportOperatorRegistry->entriesByMode('bus'),
      'air' => $transportOperatorRegistry->entriesByMode('air'),
  ];
  $currentOperatorListId = $isRail ? 'railOperatorSuggestions' : ($isFerry ? 'ferryOperatorSuggestions' : ($isBus ? 'busOperatorSuggestions' : 'airOperatorSuggestions'));
  $depLookupId = trim((string)($form['dep_station_lookup_id'] ?? ''));
  $arrLookupId = trim((string)($form['arr_station_lookup_id'] ?? ''));
  $depTerminalLookupId = trim((string)($form['dep_terminal_lookup_id'] ?? ''));
  $arrTerminalLookupId = trim((string)($form['arr_terminal_lookup_id'] ?? ''));
  $depLookupInEu = strtolower(trim((string)($form['dep_station_lookup_in_eu'] ?? '')));
  $arrLookupInEu = strtolower(trim((string)($form['arr_station_lookup_in_eu'] ?? '')));
  $depLookupNodeType = strtolower(trim((string)($form['dep_station_lookup_node_type'] ?? '')));
  $currentOperatorText = trim((string)($form['operator'] ?? ''));
  $currentIncidentOperatorText = trim((string)($form['incident_segment_operator'] ?? ''));
  $currentMarketingCarrier = trim((string)($form['marketing_carrier'] ?? ''));
  $currentOperatingCarrier = trim((string)($form['operating_carrier'] ?? ''));
  $ferryCarrierEuDerived = $transportOperatorRegistry->deriveEuFlag('ferry', $currentOperatorText) ?? $transportOperatorRegistry->deriveEuFlag('ferry', $currentIncidentOperatorText);
  $airOperatingEuDerived = $transportOperatorRegistry->deriveEuFlag('air', $currentOperatingCarrier);
  $airMarketingEuDerived = $transportOperatorRegistry->deriveEuFlag('air', $currentMarketingCarrier);
  $ferryNodeLookupResolved = $depLookupId !== '' || $arrLookupId !== '' || $depTerminalLookupId !== '' || $arrTerminalLookupId !== '';
  $busNodeLookupResolved = $depLookupId !== '' || $arrLookupId !== '';
  $airNodeLookupResolved = $depLookupId !== '' || $arrLookupId !== '';
  $hasFerryScopeValues = static function (array $form): bool {
      foreach (['departure_port_in_eu', 'arrival_port_in_eu', 'carrier_is_eu', 'departure_from_terminal'] as $field) {
          if (trim((string)($form[$field] ?? '')) !== '') {
              return true;
          }
      }

      return trim((string)($form['route_distance_meters'] ?? '')) !== '';
  };
  $ferryScopeComplete = static function (array $form): bool {
      foreach (['departure_port_in_eu', 'arrival_port_in_eu', 'carrier_is_eu', 'departure_from_terminal'] as $field) {
          if (trim((string)($form[$field] ?? '')) === '') {
              return false;
          }
      }

      return true;
  };
  $busScopeComplete = static function (array $form): bool {
      foreach (['boarding_in_eu', 'alighting_in_eu', 'departure_from_terminal', 'scheduled_distance_km'] as $field) {
          if (trim((string)($form[$field] ?? '')) === '') {
              return false;
          }
      }

      return true;
  };
  $airScopeComplete = static function (array $form): bool {
      foreach (['departure_airport_in_eu', 'arrival_airport_in_eu', 'operating_carrier_is_eu', 'marketing_carrier_is_eu', 'flight_distance_km', 'air_distance_band'] as $field) {
          if (trim((string)($form[$field] ?? '')) === '') {
              return false;
          }
      }

      return true;
  };
  $scopeValueLabel = static function (?string $value): string {
      $v = strtolower(trim((string)$value));
      return match ($v) {
          'yes' => 'Ja',
          'no' => 'Nej',
          default => 'Ikke afledt endnu',
      };
  };
  $airDistanceBandLabel = static function (?string $value): string {
      $v = strtolower(trim((string)$value));
      return match ($v) {
          'up_to_1500' => '1500 km eller mindre',
          'intra_eu_over_1500' => 'Inden for EU over 1500 km',
          'other_1500_to_3500' => 'Øvrige flyvninger mellem 1500 og 3500 km',
          'other_over_3500' => 'Øvrige flyvninger over 3500 km',
          default => 'Ikke afledt endnu',
      };
  };
  $airDelayThresholdLabel = static function (?string $value): string {
      $v = trim((string)$value);
      return match ($v) {
          '2' => '2+ timer',
          '3' => '3+ timer',
          '4' => '4+ timer',
          default => 'Ikke afledt endnu',
      };
  };
	?>
<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['type' => 'file', 'id' => 'entitlementsForm']) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
<div class="fe-wrapper">
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;">
    <strong><?= h($uploadIntroTitle) ?></strong>
    <div class="small muted" style="margin-top:6px;"><?= h($uploadIntroText) ?></div>
    <?php if ($ticketMode === 'ticket' && !$transportModeSelectionRequired): ?>
      <div class="small muted" style="margin-top:6px;">Transportformen vælges automatisk, når der er analyseret mindst én billet eller booking.</div>
    <?php endif; ?>
    <?php if (!$isRail): ?>
      <div class="small muted" style="margin-top:6px;">Ved upload analyserer vi først dokumenterne med LLM og den multimodale kontraktmotor. Kontrakt og ansvar vises derefter som auto-summary med STOP, hvis sagen kan afgøres tidligt.</div>
    <?php endif; ?>
    <?php if ($ticketMode === 'ticket' && $transportModeAutoReady): ?>
      <div class="small" style="margin-top:8px;"><strong>Auto-detekteret transportform:</strong> <?= h($transportModeLabel) ?></div>
    <?php endif; ?>
    <div class="small" style="margin-top:8px;">
      <label class="mr8"><input type="radio" name="ticket_upload_mode" value="ticket" <?= $ticketMode==='ticket'?'checked':'' ?> /> <?= $isAir ? 'Ja, jeg kan uploade booking/billet' : ($isFerry ? 'Ja, jeg kan uploade booking/færgebillet' : ($isBus ? 'Ja, jeg kan uploade booking/busbillet' : 'Ja, jeg kan uploade billet')) ?></label>
      <label class="mr8"><input type="radio" name="ticket_upload_mode" value="ticketless" <?= $ticketMode==='ticketless'?'checked':'' ?> /> Nej, ticketless</label>
      <label class="mr8" id="seasonPassOptionWrap" style="<?= $seasonSupported ? '' : 'display:none;' ?>"><input type="radio" name="ticket_upload_mode" value="seasonpass" <?= $ticketMode==='seasonpass'?'checked':'' ?> /> Jeg rejser på pendler-/periodekort</label>
    </div>
  </div>

  <div class="card" id="transportModeCard" data-auto-ready="<?= $transportModeAutoReady ? '1' : '0' ?>" style="padding:10px 12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;<?= $transportModeSelectionRequired ? '' : ' display:none;' ?>">
    <div class="small" style="font-weight:700;">Vælg transportmåde</div>
    <div id="transportModeManualBlock">
      <div class="small muted" style="margin-top:4px;">Vaelg den transportmåde sagen starter i. Hvis rejsen er multimodal, finder vi senere det først ramte segment.</div>
    </div>
    <div id="transportModeChoices" class="small" style="margin-top:8px;">
      <label class="mr8"><input type="radio" name="transport_mode" value="rail" <?= $transportMode==='rail'?'checked':'' ?> <?= $transportModeSelectionRequired ? '' : 'disabled' ?> /> Tog</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="ferry" <?= $transportMode==='ferry'?'checked':'' ?> <?= $transportModeSelectionRequired ? '' : 'disabled' ?> /> Færge</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="bus" <?= $transportMode==='bus'?'checked':'' ?> <?= $transportModeSelectionRequired ? '' : 'disabled' ?> /> Bus</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="air" <?= $transportMode==='air'?'checked':'' ?> <?= $transportModeSelectionRequired ? '' : 'disabled' ?> /> Fly</label>
    </div>
  </div>
  <input type="hidden" id="transportModeHidden" name="transport_mode" value="<?= h($transportMode) ?>" <?= $transportModeSelectionRequired ? 'disabled' : '' ?> />
  <input type="hidden" name="dep_station_lookup_id" value="<?= h((string)($form['dep_station_lookup_id'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_code" value="<?= h((string)($form['dep_station_lookup_code'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_mode" value="<?= h((string)($form['dep_station_lookup_mode'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_country" value="<?= h((string)($form['dep_station_lookup_country'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_in_eu" value="<?= h((string)($form['dep_station_lookup_in_eu'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_node_type" value="<?= h((string)($form['dep_station_lookup_node_type'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_parent" value="<?= h((string)($form['dep_station_lookup_parent'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_source" value="<?= h((string)($form['dep_station_lookup_source'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_lat" value="<?= h((string)($form['dep_station_lookup_lat'] ?? '')) ?>" />
  <input type="hidden" name="dep_station_lookup_lon" value="<?= h((string)($form['dep_station_lookup_lon'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_id" value="<?= h((string)($form['dep_terminal_lookup_id'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_code" value="<?= h((string)($form['dep_terminal_lookup_code'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_mode" value="<?= h((string)($form['dep_terminal_lookup_mode'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_country" value="<?= h((string)($form['dep_terminal_lookup_country'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_in_eu" value="<?= h((string)($form['dep_terminal_lookup_in_eu'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_node_type" value="<?= h((string)($form['dep_terminal_lookup_node_type'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_parent" value="<?= h((string)($form['dep_terminal_lookup_parent'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_source" value="<?= h((string)($form['dep_terminal_lookup_source'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_lat" value="<?= h((string)($form['dep_terminal_lookup_lat'] ?? '')) ?>" />
  <input type="hidden" name="dep_terminal_lookup_lon" value="<?= h((string)($form['dep_terminal_lookup_lon'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_id" value="<?= h((string)($form['arr_station_lookup_id'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_code" value="<?= h((string)($form['arr_station_lookup_code'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_mode" value="<?= h((string)($form['arr_station_lookup_mode'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_country" value="<?= h((string)($form['arr_station_lookup_country'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_in_eu" value="<?= h((string)($form['arr_station_lookup_in_eu'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_node_type" value="<?= h((string)($form['arr_station_lookup_node_type'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_parent" value="<?= h((string)($form['arr_station_lookup_parent'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_source" value="<?= h((string)($form['arr_station_lookup_source'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_lat" value="<?= h((string)($form['arr_station_lookup_lat'] ?? '')) ?>" />
  <input type="hidden" name="arr_station_lookup_lon" value="<?= h((string)($form['arr_station_lookup_lon'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_id" value="<?= h((string)($form['arr_terminal_lookup_id'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_code" value="<?= h((string)($form['arr_terminal_lookup_code'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_mode" value="<?= h((string)($form['arr_terminal_lookup_mode'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_country" value="<?= h((string)($form['arr_terminal_lookup_country'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_in_eu" value="<?= h((string)($form['arr_terminal_lookup_in_eu'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_node_type" value="<?= h((string)($form['arr_terminal_lookup_node_type'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_parent" value="<?= h((string)($form['arr_terminal_lookup_parent'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_source" value="<?= h((string)($form['arr_terminal_lookup_source'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_lat" value="<?= h((string)($form['arr_terminal_lookup_lat'] ?? '')) ?>" />
  <input type="hidden" name="arr_terminal_lookup_lon" value="<?= h((string)($form['arr_terminal_lookup_lon'] ?? '')) ?>" />

  <?php
    $sellerChannelMode = (string)($form['seller_channel'] ?? 'operator');
    $sameBookingResolved = $multimodal['contract_meta']['shared_booking_reference'] ?? null;
    $sameTransactionResolved = $multimodal['contract_meta']['single_transaction'] ?? null;
    $throughDisclosureResolved = (string)($multimodal['contract_meta']['contract_structure_disclosure'] ?? '');
    $separateNoticeResolved = (string)($multimodal['contract_meta']['separate_contract_notice'] ?? '');
    $journeyStructureResolved = (string)($multimodal['contract_meta']['journey_structure'] ?? 'unknown');
    $originalContractResolved = (string)($multimodal['contract_meta']['original_contract_mode'] ?? '');
    $contractTopologyResolved = (string)($multimodal['contract_meta']['contract_topology'] ?? 'unknown_manual_review');
    $contractTopologyHintResolved = (string)($multimodal['contract_meta']['contract_topology_hint'] ?? '');
    $contractConfidenceResolved = (string)($multimodal['contract_meta']['contract_topology_confidence'] ?? 'low');
    $bookingCohesionResolved = (string)($multimodal['contract_meta']['booking_cohesion'] ?? 'unknown');
    $serviceCohesionResolved = (string)($multimodal['contract_meta']['service_cohesion'] ?? 'unknown');
    $manualReviewReasonsResolved = array_values(array_map('strval', (array)($multimodal['contract_meta']['manual_review_reasons'] ?? [])));
    $sameBookingMode = (string)($form['shared_pnr_scope'] ?? ($sameBookingResolved === true ? 'yes' : ($sameBookingResolved === false ? 'no' : '')));
    $sameTransactionMode = (string)($form['same_transaction'] ?? ($sameTransactionResolved === true ? 'yes' : ($sameTransactionResolved === false ? 'no' : '')));
    $throughDisclosureMode = (string)($form['through_ticket_disclosure'] ?? (in_array($throughDisclosureResolved, ['bundled', 'separate', 'none', 'unknown'], true) ? $throughDisclosureResolved : ''));
    $separateNoticeMode = (string)($form['separate_contract_notice'] ?? (in_array($separateNoticeResolved, ['yes', 'no', 'unclear'], true) ? $separateNoticeResolved : ''));
    $modeOriginalContractManual = (string)($form['original_contract_mode'] ?? (in_array($originalContractResolved, ['rail', 'ferry', 'bus', 'air'], true) ? $originalContractResolved : ''));
    $modeJourneyStructureManual = (string)($form['journey_structure'] ?? (in_array($journeyStructureResolved, ['single_segment', 'single_mode_connections', 'multimodal_connections'], true) ? $journeyStructureResolved : ''));
    if (in_array($ticketMode, ['ticketless', 'seasonpass'], true)) {
      if ($sameBookingMode === '') { $sameBookingMode = 'yes'; }
      if ($sameTransactionMode === '') { $sameTransactionMode = 'yes'; }
      if ($modeJourneyStructureManual === '') { $modeJourneyStructureManual = 'single_segment'; }
    }
    $contractDecision = (array)($multimodal['contract_decision'] ?? []);
    $modeStop = (string)($contractDecision['stage'] ?? '') === 'STOP';
    $modeContractLabel = (string)($contractDecision['contract_label'] ?? 'Kræver flere svar');
    $modeContractBasis = (string)($contractDecision['basis'] ?? 'manual_review');
    $modeDecisionNotes = array_values(array_filter(array_map('strval', (array)($contractDecision['notes'] ?? []))));
    $modeIncidentSegmentTop = (string)($form['incident_segment_mode'] ?? ($isFerry ? ($ferryContract['rights_module'] ?? 'ferry') : ($modeContract['rights_module'] ?? $transportMode)));
    $modeProblemOperatorTop = (string)($form['incident_segment_operator'] ?? ($isFerry ? ($ferryContract['primary_claim_party_name'] ?? '') : ($modeContract['primary_claim_party_name'] ?? '')));
    $modeHasAnalysis = $hasTickets;
    $uploadAutoContract = $ticketMode === 'ticket' && $modeHasAnalysis;
    $modeContractVisible = ($ticketMode === 'ticket')
      ? $modeHasAnalysis
      : $transportModeChosen;
    $modeContractModel = (string)($form['contract_model'] ?? ($contractTopologyResolved === 'separate_contracts' ? 'separate' : ($modeStop ? 'through' : '')));
    $modeProblemContractId = (string)($form['problem_contract_id'] ?? '');
    $modeShowProblemContract = $modeStop && $modeContractModel === 'separate';
    $modeClaimPartyPreview = (string)(($isFerry ? ($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? null)) : ($modeContract['primary_claim_party_name'] ?? ($modeContract['primary_claim_party'] ?? null))) ?? 'manual_review');
    $modeRightsModulePreview = (string)(($isFerry ? ($ferryContract['rights_module'] ?? 'ferry') : ($modeContract['rights_module'] ?? ($claimDirection['rights_module'] ?? $transportMode))));
    $modeClaimTransportPreview = (string)(($claimDirection['claim_transport_mode'] ?? ($multimodal['contract_meta']['claim_transport_mode'] ?? '')) ?: ($modeOriginalContractManual !== '' ? $modeOriginalContractManual : $transportMode));
    $modeLabel = static function (string $value): string {
      return match ($value) {
        'rail' => 'Tog',
        'ferry' => 'Færge',
        'bus' => 'Bus',
        'air' => 'Fly',
        default => 'Auto / ukendt',
      };
    };
    $modeJourneyStructureLabel = match ($journeyStructureResolved) {
      'single_segment' => 'Et direkte transportled uden skift',
      'single_mode_connections' => 'Flere led med skift i samme transporttype',
      'multimodal_connections' => 'Flere led med skift mellem forskellige transporttyper',
      default => 'Auto / ukendt',
    };
    $modeJourneyStructureDisplay = $modeJourneyStructureManual !== '' ? match ($modeJourneyStructureManual) {
      'single_segment' => 'Et direkte transportled uden skift',
      'single_mode_connections' => 'Flere led med skift i samme transporttype',
      'multimodal_connections' => 'Flere led med skift mellem forskellige transporttyper',
      default => 'Auto / ukendt',
    } : $modeJourneyStructureLabel;
    $modeJourneyStructureSourceLabel = $modeJourneyStructureManual !== '' ? 'Manuel' : 'AUTO';
    $modeTopologySummary = match ($contractTopologyResolved) {
      'single_mode_single_contract' => 'single_mode_single_contract',
      'protected_single_contract' => 'protected_single_contract',
      'single_multimodal_contract' => 'single_multimodal_contract',
      'separate_contracts' => 'separate_contracts',
      default => 'unknown_manual_review',
    };
    $modeStopMissing = [];
    $effectiveJourneyStructure = $modeJourneyStructureManual !== '' ? $modeJourneyStructureManual : (in_array($journeyStructureResolved, ['single_segment', 'single_mode_connections', 'multimodal_connections'], true) ? $journeyStructureResolved : '');
    $ticketlessContractLight = in_array($ticketMode, ['ticketless', 'seasonpass'], true);
    $showContractStructureQuestions = $effectiveJourneyStructure !== '' && $effectiveJourneyStructure !== 'single_segment' && !$ticketlessContractLight;
    $uploadEditShowSeller = !$uploadAutoContract;
    $uploadEditShowSameBooking = !$uploadAutoContract || $sameBookingMode === '';
    $uploadEditShowSameTransaction = !$uploadAutoContract || $sameTransactionMode === '';
    $uploadEditShowJourneyStructure = !$uploadAutoContract || $modeJourneyStructureManual === '';
    $uploadEditShowDisclosure = !$uploadAutoContract || in_array($throughDisclosureMode, ['', 'unknown'], true);
    $uploadEditShowNotice = !$uploadAutoContract || in_array($separateNoticeMode, ['', 'unclear'], true);
    $uploadAutoNeedsAnswers = $uploadAutoContract
      && !$modeStop
      && (
        $uploadEditShowSeller
        || $uploadEditShowSameBooking
        || $uploadEditShowSameTransaction
        || $uploadEditShowJourneyStructure
        || ($showContractStructureQuestions && $uploadEditShowDisclosure)
        || ($showContractStructureQuestions && $uploadEditShowNotice)
      );
    $modeContractShowAllStyle = ($uploadAutoNeedsAnswers ? '' : 'display:none; ') . 'background:transparent; border:0; color:#555; text-decoration:underline; cursor:pointer;';
    $modeContractQuestionsStyle = ($modeStop || ($uploadAutoContract && !$uploadAutoNeedsAnswers)) ? 'display:none' : 'display:block';
    $modeContractTitle = $isRail ? 'Kontrakt og ansvar' : 'Kontrakt og ansvar (multimodal masterflow)';
    $modeContractIntro = $isRail
      ? 'TRIN 2 afgoer kontraktstrukturen for rail, foer flowet gaar videre til rail-specifik routing og haendelseslogik.'
      : 'Multimodal er masterflowet i TRIN 2. Art. 12 bruges som obligatorisk kontrakt-stop for faerge, bus og fly, foer flowet gaar videre til early incident-routing og senere haendelseskaede.';
    if (!$modeStop) {
      if ($effectiveJourneyStructure === '') {
        $modeStopMissing[] = 'rejsens struktur';
      } elseif ($effectiveJourneyStructure !== 'single_segment') {
        if ($sameBookingMode === '' && $sameTransactionMode === '') {
          $modeStopMissing[] = 'bookingreference eller samlet køb';
        }
        if (!$ticketlessContractLight) {
          if ($separateNoticeMode === '') {
            $modeStopMissing[] = 'om separate kontrakter står på billet eller booking';
          }
          if ($separateNoticeMode === 'yes' && $throughDisclosureMode === '') {
            $modeStopMissing[] = 'om separate kontrakter blev oplyst før køb';
          }
        }
      }
    }
  ?>
  <?php ob_start(); ?>
  <div class="card" id="modeContractCard" data-has-analysis="<?= $modeHasAnalysis ? '1' : '0' ?>" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-top:12px; margin-bottom:12px;<?= $modeContractVisible ? '' : ' display:none;' ?>">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <strong><?= h($modeContractTitle) ?></strong>
      <div style="display:flex; gap:10px; align-items:center;">
          <?php if ($uploadAutoContract): ?>
            <button type="button" id="modeContractShowAllBtn" class="small" style="<?= h($modeContractShowAllStyle) ?>">Vis alle spørgsmål</button>
          <?php endif; ?>
        <button type="button" id="modeContractEditBtn" class="small" style="background:transparent; border:0; color:#0b5; text-decoration:underline; cursor:pointer;">Rediger</button>
      </div>
    </div>
    <div class="small muted" style="margin-top:6px;"><?= h($modeContractIntro) ?></div>
    <?php if (!$modeHasAnalysis && $ticketMode === 'ticket'): ?>
      <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
        Upload først billet/booking. Når LLM og kontraktmotoren har analyseret dokumenterne, udfyldes denne blok automatisk og stopper tidligt ved klare STOP-udfald.
      </div>
    <?php endif; ?>
    <?php if ($uploadAutoContract): ?>
      <div class="card" style="margin-top:10px; padding:10px; border:1px solid #dbeafe; background:#f8fafc; border-radius:6px;">
        <div><strong>Autoanalyse fra upload</strong></div>
        <div class="small muted" style="margin-top:4px;">Kontraktanalysen er koert automatisk paa de uploadede billetter. Ret kun manuelt, hvis autoanalysen rammer forkert eller mangler en afklaring.</div>
        <div class="small" style="margin-top:8px;">seller: <?= h($sellerChannelMode) ?></div>
        <div class="small">bookingreference: <?= h($sameBookingMode !== '' ? $sameBookingMode : 'auto/ukendt') ?></div>
        <div class="small">samlet koeb: <?= h($sameTransactionMode !== '' ? $sameTransactionMode : 'auto/ukendt') ?></div>
        <div class="small">journey_structure: <?= h($modeJourneyStructureDisplay) ?></div>
        <div class="small">disclosure: <?= h($throughDisclosureMode !== '' ? $throughDisclosureMode : 'auto/ukendt') ?></div>
        <div class="small">separate_contract_notice: <?= h($separateNoticeMode !== '' ? $separateNoticeMode : 'auto/ukendt') ?></div>
        <?php if (!$modeStop && !empty($modeStopMissing)): ?>
          <div class="small" style="margin-top:8px;"><strong>Autoanalysen mangler stadig:</strong> <?= h(implode('; ', $modeStopMissing)) ?></div>
        <?php elseif ($modeStop): ?>
          <div class="small muted" style="margin-top:8px;">Ingen yderligere Art. 12-svar er noedvendige lige nu.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div id="modeContractQuestions" data-upload-edit="<?= $uploadAutoContract ? '1' : '0' ?>" data-auto-open="<?= $uploadAutoNeedsAnswers ? '1' : '0' ?>" style="<?= h($modeContractQuestionsStyle) ?>">
      <div class="card" style="margin-top:10px; padding:10px; border:1px solid #e5e7eb; background:#fafafa; border-radius:6px;">
        <div><strong>1. Art. 12 kontraktanalyse</strong></div>
        <div class="small muted" style="margin-top:4px;">NODE 1-5 i multimodal-flowet bruges her som kontrakt-stop. TRIN 2 afgoer kun kontraktstrukturen; haendelsesrouting og mode-specifik gating kommer bagefter.</div>
        <?php if ($uploadAutoContract): ?>
            <div class="small muted" style="margin-top:8px;"><?= $uploadAutoNeedsAnswers ? 'Udfyld kun de manglende svar fra autoanalysen nedenfor. Brug “Vis alle spørgsmål” hvis du vil overstyre alt manuelt.' : 'Rediger viser kun de felter hvor autoanalysen mangler en sikker afklaring. Brug “Vis alle spørgsmål” hvis du vil overstyre alt manuelt.' ?></div>
        <?php endif; ?>
        <div class="small" style="margin-top:8px;"><?= h($modeJourneyStructureSourceLabel) ?>: Rejsestruktur = <?= h($modeJourneyStructureDisplay) ?></div>
        <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
          <label data-contract-seller-row="1" data-upload-edit-hidden="<?= $uploadEditShowSeller ? '0' : '1' ?>" style="<?= ($ticketlessContractLight || !$uploadEditShowSeller) ? 'display:none;' : '' ?>">Hvem solgte hele rejsen?
            <select name="seller_channel">
              <option value="operator" <?= $sellerChannelMode==='operator'?'selected':'' ?>>Operatør / carrier</option>
              <option value="retailer" <?= $sellerChannelMode==='retailer'?'selected':'' ?>>Billetudsteder / platform</option>
              <option value="agency" <?= $sellerChannelMode==='agency'?'selected':'' ?>>Rejsebureau</option>
              <option value="tour_operator" <?= $sellerChannelMode==='tour_operator'?'selected':'' ?>>Tour operator</option>
            </select>
          </label>
          <?php if ($ticketlessContractLight): ?>
            <input type="hidden" name="seller_channel" value="<?= h($sellerChannelMode) ?>" />
          <?php endif; ?>
          <label data-upload-edit-hidden="<?= $uploadEditShowSameBooking ? '0' : '1' ?>" style="<?= $uploadEditShowSameBooking ? '' : 'display:none;' ?>">Havde rejsen én bookingreference?
            <select name="shared_pnr_scope">
              <option value="" <?= $sameBookingMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $sameBookingMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $sameBookingMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label data-upload-edit-hidden="<?= $uploadEditShowSameTransaction ? '0' : '1' ?>" style="<?= $uploadEditShowSameTransaction ? '' : 'display:none;' ?>">Købte du hele rejsen samlet ét sted?
            <select name="same_transaction">
              <option value="" <?= $sameTransactionMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $sameTransactionMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $sameTransactionMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <input type="hidden" name="original_contract_mode" value="<?= h($modeOriginalContractManual) ?>" />
          <label data-upload-edit-hidden="<?= $uploadEditShowJourneyStructure ? '0' : '1' ?>" style="<?= $uploadEditShowJourneyStructure ? '' : 'display:none;' ?>">Bestod den købte rejse af ét direkte transportled, eller skulle du skifte undervejs?
            <select name="journey_structure">
              <option value="" <?= $modeJourneyStructureManual===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="single_segment" <?= $modeJourneyStructureManual==='single_segment'?'selected':'' ?>>Et direkte transportled uden skift</option>
              <option value="single_mode_connections" <?= $modeJourneyStructureManual==='single_mode_connections'?'selected':'' ?>>Flere led med skift i samme transporttype</option>
              <option value="multimodal_connections" <?= $modeJourneyStructureManual==='multimodal_connections'?'selected':'' ?>>Flere led med skift mellem forskellige transporttyper</option>
            </select>
          </label>
          <label data-contract-disclosure-row="1" data-upload-edit-hidden="<?= $uploadEditShowDisclosure ? '0' : '1' ?>" style="<?= ($showContractStructureQuestions && $uploadEditShowDisclosure) ? '' : 'display:none;' ?>">Blev du før købet tydeligt informeret om, at rejsen bestod af separate billetter eller separate transportkontrakter?
            <select name="through_ticket_disclosure">
              <option value="" <?= $throughDisclosureMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="separate" <?= $throughDisclosureMode==='separate'?'selected':'' ?>>Ja</option>
              <option value="none" <?= $throughDisclosureMode==='none'?'selected':'' ?>>Nej</option>
              <option value="unknown" <?= $throughDisclosureMode==='unknown'?'selected':'' ?>>Ved ikke</option>
            </select>
          </label>
          <label data-contract-notice-row="1" data-upload-edit-hidden="<?= $uploadEditShowNotice ? '0' : '1' ?>" style="<?= ($showContractStructureQuestions && $uploadEditShowNotice) ? '' : 'display:none;' ?>">Står det på billetten eller bookingbekræftelsen, at de enkelte transportled er separate kontrakter eller separate billetter?
            <select name="separate_contract_notice">
              <option value="" <?= $separateNoticeMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $separateNoticeMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $separateNoticeMode==='no'?'selected':'' ?>>Nej</option>
              <option value="unclear" <?= $separateNoticeMode==='unclear'?'selected':'' ?>>Uklart</option>
            </select>
          </label>
          <?php if ($isAir): ?>
          <?php $samePnrMode = (string)($form['same_pnr'] ?? ''); ?>
          <label>Same PNR?
            <select name="same_pnr">
              <option value="" <?= $samePnrMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $samePnrMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $samePnrMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $sameBookingReferenceMode = (string)($form['same_booking_reference'] ?? ''); ?>
          <label>Same bookingreference?
            <select name="same_booking_reference">
              <option value="" <?= $sameBookingReferenceMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $sameBookingReferenceMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $sameBookingReferenceMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $sameEticketMode = (string)($form['same_eticket'] ?? ''); ?>
          <label>Same e-ticket?
            <select name="same_eticket">
              <option value="" <?= $sameEticketMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $sameEticketMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $sameEticketMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $selfTransferNoticeMode = (string)($form['self_transfer_notice'] ?? ''); ?>
          <label>Self-transfer oplyst før køb?
            <select name="self_transfer_notice">
              <option value="" <?= $selfTransferNoticeMode===''?'selected':'' ?>>Auto / kræver svar</option>
              <option value="yes" <?= $selfTransferNoticeMode==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $selfTransferNoticeMode==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /modeContractQuestions -->
    <div class="card" style="margin-top:10px; padding:10px; border:1px solid #dbeafe; background:#f8fafc; border-radius:6px;">
      <div><strong>2. STOP / fork</strong></div>
      <?php if ($modeStop): ?>
        <div class="small" style="margin-top:6px;"><strong>Billet = <?= h($modeContractLabel) ?></strong></div>
        <div class="small">contract_topology: <?= h($modeTopologySummary) ?></div>
        <div class="small">booking_cohesion: <?= h($bookingCohesionResolved) ?></div>
        <div class="small">service_cohesion: <?= h($serviceCohesionResolved) ?></div>
        <div class="small">confidence: <?= h($contractConfidenceResolved) ?></div>
        <div class="small">Grundlag: <?= h($modeContractBasis) ?></div>
        <?php foreach ($modeDecisionNotes as $note): ?>
          <div class="small"><?= h($note) ?></div>
        <?php endforeach; ?>
        <div class="small muted" style="margin-top:6px;">Kontraktstoppet er afgjort. Flowet gaar nu videre til foerste ramte segment, mode-specifik gating og senere haendelseskaede. Klik "Rediger" hvis Art. 12-svarene skal aendres.</div>
      <?php else: ?>
        <div class="small" style="margin-top:6px;">Kontraktstoppet er ikke afgjort endnu. Udfyld Art. 12-spoergsmaalene ovenfor, foer flowet gaar videre.</div>
        <?php if ($contractTopologyHintResolved !== ''): ?>
          <div class="small" style="margin-top:6px;">Foreloebigt estimat: <?= h($contractTopologyHintResolved) ?> (confidence: <?= h($contractConfidenceResolved) ?>)</div>
        <?php endif; ?>
        <div class="small">booking_cohesion: <?= h($bookingCohesionResolved) ?></div>
        <div class="small">service_cohesion: <?= h($serviceCohesionResolved) ?></div>
        <?php if (!empty($modeStopMissing)): ?>
          <div class="small" style="margin-top:8px;"><strong>Mangler lige nu:</strong> <?= h(implode('; ', $modeStopMissing)) ?></div>
        <?php endif; ?>
        <?php if ($uploadAutoContract): ?>
          <div class="small muted" style="margin-top:6px;">Upload-sporet er auto-foerst. Brug kun "Rediger", hvis du vil overstyre eller supplere kontraktanalysen.</div>
        <?php endif; ?>
        <?php if ($ticketMode === 'ticketless' || $ticketMode === 'seasonpass'): ?>
          <div class="small muted" style="margin-top:6px;">Ticketless opdaterer STOP-boksen automatisk, sa snart du aendrer Art. 12-felterne.</div>
          <?php if ($contractConfidenceResolved === 'low'): ?>
            <div class="small muted" style="margin-top:4px;">Confidence er lav. Upload billet eller booking for en mere sikker kontraktanalyse.</div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($manualReviewReasonsResolved)): ?>
          <div class="small" style="margin-top:6px;">Manual review reasons: <?= h(implode(', ', $manualReviewReasonsResolved)) ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <input type="hidden" name="contract_model" value="" />
    <div id="modeContractPostStop" style="display:<?= $modeStop ? 'block' : 'none' ?>;">
      <div class="small muted" style="margin-top:10px;">TRIN 2 stopper nu ved kontraktanalysen. Foerste ramte segment vaelges i naeste trin, og mode-specifik gating sker derefter.</div>
    </div>
  </div>
  <?php $modeContractCardHtml = (string)ob_get_clean(); ?>

  <?php
    // Season/period pass (Art. 19(2)) is a top-level entitlement choice in TRIN 2
    $fftSeason = strtolower((string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? '')));
    $season = (array)($meta['season_pass'] ?? []);
    $seasonHas = ($ticketMode === 'seasonpass');
    $seasonType = (string)($season['type'] ?? '');
    $seasonOp = (string)($season['operator'] ?? ($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')));
    $seasonFrom = (string)($season['valid_from'] ?? '');
    $seasonTo = (string)($season['valid_to'] ?? '');
  ?>
  <div class="card" id="seasonPassCard" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;<?= $seasonHas ? '' : ' display:none;' ?>">
    <div class="section-title"><?= $isFerry ? 'Abonnement / periodekort (fÃ¦rge)' : 'Pendler-/periodekort (abonnement)' ?></div>
    <div class="small muted" style="margin-top:6px;"><?= $isFerry ? 'Hvis ja, bruger vi transport?rens egen kompensationsordning for abonnement/periodekort. Forsinkede ankomster i gyldighedsperioden kan udl?se passende kompensation efter operat?rens regler.' : 'Hvis ja, bruger vi operat??rens egen ordning (Art. 19, stk. 2). Sm?? forsinkelser (&lt; 60 min) kan typisk kumuleres i gyldighedsperioden.' ?></div>
    <input type="hidden" name="season_pass_has" id="seasonPassHas" value="<?= $seasonHas ? '1' : '0' ?>" />
    <fieldset id="seasonPassFieldset" style="border:0; padding:0; margin:0;" <?= $seasonHas ? '' : 'disabled' ?>>
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
        <label>Type (valgfri)
          <input type="text" name="season_pass_type" value="<?= h($seasonType) ?>" placeholder="<?= $isFerry ? 'Periodekort / abonnement / klippekort' : 'Pendler / Periode / ??rskort' ?>" />
        </label>
        <label><?= $isFerry ? 'Transport??r / carrier (valgfri)' : 'Operat??r (valgfri)' ?>
          <input type="text" name="season_pass_operator" list="<?= h($currentOperatorListId) ?>" value="<?= h($seasonOp) ?>" placeholder="<?= $isFerry ? 'Fx Scandlines / ForSea' : 'DSB / DB / SNCF ?' ?>" />
        </label>
        <label>Gyldig fra (valgfri)
          <input type="date" name="season_pass_valid_from" value="<?= h($seasonFrom) ?>" />
        </label>
        <label>Gyldig til (valgfri)
          <input type="date" name="season_pass_valid_to" value="<?= h($seasonTo) ?>" />
        </label>
      </div>
    </fieldset>

    <?php $spFiles = (array)($meta['season_pass_files'] ?? []); ?>
    <div class="mt8" style="margin-top:12px;">
      <div class="small"><strong><?= $isFerry ? 'Upload abonnement / periodekort (valgfrit)' : 'Upload pendlerkort/abonnement (valgfrit)' ?></strong></div>
      <div class="small muted" style="margin-top:4px;"><?= $isFerry ? 'Brug fx screenshot fra carrier-app, PDF, bookingbekr??ftelse eller billede af kort. Underst??tter PDF, JPG, PNG, PKPASS, TXT.' : 'Brug fx screenshot fra operat??r-app, PDF, eller billede af kort. Underst??tter PDF, JPG, PNG, PKPASS, TXT.' ?></div>
      <div class="upload-actions" style="margin-top:8px;">
        <button type="button" id="addSeasonPassFilesBtn" class="button">TilfÃ¸j filer</button>
        <button type="button" id="clearSeasonPassFilesBtn" class="button button-outline">Fjern alle</button>
      </div>
      <input type="file" id="seasonPassFilesInput" name="season_pass_upload[]" multiple accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
      <?php if (!empty($spFiles)): ?>
        <ul class="small file-list" style="list-style:none; padding-left:0; margin:10px 0 0 0;">
          <?php foreach ($spFiles as $f): $fn = (string)($f['file'] ?? ''); $orig = (string)($f['original'] ?? ''); ?>
            <?php if ($fn === '') { continue; } ?>
            <li style="display:flex; gap:8px; align-items:center; justify-content:space-between;">
              <span><?= h($orig !== '' ? $orig : $fn) ?></span>
              <button type="button" class="small remove-seasonpass-btn" data-file="<?= h($fn) ?>">Fjern</button>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="small muted" style="margin-top:8px;">Ingen filer uploadet endnu.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php $pcVal = (string)($form['purchaseChannel'] ?? ''); ?>
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px; display:none;">
    <strong>Hvor blev billetten kÃ¸bt?</strong>
    <div class="small" style="margin-top:6px;">
      <label class="mr8"><input type="radio" name="purchaseChannel" value="web_app" <?= $pcVal==='web_app'?'checked':'' ?> /> Online / app</label>
      <label class="mr8"><input type="radio" name="purchaseChannel" value="station" <?= $pcVal==='station'?'checked':'' ?> /> Station / automat</label>
      <label class="mr8"><input type="radio" name="purchaseChannel" value="onboard" <?= $pcVal==='onboard'?'checked':'' ?> /> I toget</label>
      <label class="mr8"><input type="radio" name="purchaseChannel" value="other" <?= $pcVal==='other'?'checked':'' ?> /> Andet</label>
    </div>
  </div>
  <div class="card" id="ticketUploadCard" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;<?= ($ticketMode==='ticketless' || $ticketMode==='seasonpass')?' display:none;':'' ?>">
    <div class="section-title"><?= $hasUploadedTickets ? 'Billetter i sagen' : ($isAir ? 'Booking / billetter' : ($isFerry ? 'Booking / færgebilletter' : ($isBus ? 'Booking / billetter' : 'Billetter'))) ?></div>
    <?php if ($hasUploadedTickets): ?>
      <div class="small muted" style="margin-top:6px;">Uploaden er allerede læst. Her kan du kun tilføje flere filer eller rydde de uploadede filer.</div>
      <div class="upload-actions" style="margin-top:10px;">
        <button type="button" id="addFilesBtn" class="button">Tilføj flere filer</button>
        <button type="button" id="clearFilesBtn" class="button button-outline">Fjern alle</button>
      </div>
    <?php else: ?>
      <div id="uploadDropzone" class="upload-dropzone" tabindex="0">
        <div class="upload-title">Slip filer her eller klik for at tilføje</div>
        <div class="small muted" style="margin-top:6px;">Understøtter PDF, JPG, PNG, PKPASS, TXT</div>
        <div class="upload-actions">
          <button type="button" id="addFilesBtn" class="button">Tilføj filer</button>
          <button type="button" id="clearFilesBtn" class="button button-outline">Fjern alle</button>
        </div>
      </div>
    <?php endif; ?>
    <!-- Hidden real inputs wired by JS -->
    <input type="file" id="ticketSingle" name="ticket_upload" accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <input type="file" id="ticketMulti" name="multi_ticket_upload[]" multiple accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <ul id="selectedFilesList" data-hide-empty="<?= $hasUploadedTickets ? '1' : '0' ?>" class="small file-list" style="list-style:none; padding-left:0; margin:12px 0 0 0;<?= $hasUploadedTickets ? ' display:none;' : '' ?>"></ul>
    <?php if (!empty($groupedTickets)): ?>
    <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
      <strong>Billetter samlet i sagen</strong>
      <?php foreach ((array)$groupedTickets as $gi => $g): $shared = !empty($g['shared']); ?>
        <div class="small" style="margin-top:6px;"><strong>Gruppe <?= (int)($gi+1) ?></strong>
          <?php if (!empty($g['pnr']) || !empty($g['dep_date'])): ?>
            (<?= h(trim((string)($g['pnr'] ?? '') . ' ' . (string)($g['dep_date'] ?? ''))) ?>)
          <?php endif; ?>
          <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= $shared ? 'samlet' : 'enkelt' ?></span>
        </div>
        <ul class="small" style="margin:6px 0 0 16px;">
          <?php foreach ((array)($g['tickets'] ?? []) as $t): ?>
            <li>
              <?= h((string)($t['file'] ?? '')) ?><?= (!empty($t['pnr'])||!empty($t['dep_date'])) ? (': ' . h(trim((string)($t['pnr'] ?? '') . ' ' . (string)($t['dep_date'] ?? '')))) : '' ?>
              <?php $pc = isset($t['passengers']) ? count((array)$t['passengers']) : 0; if ($pc>0): ?>
                <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= (int)$pc ?> pax</span>
              <?php endif; ?>
              <?php if (!empty($t['file'])): ?>
                <button type="button" class="small remove-ticket-btn" data-file="<?= h((string)$t['file']) ?>" style="margin-left:6px;">Fjern</button>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <div class="small muted" style="margin-top:6px;">Billetterne er samlet automatisk fra uploaden. Returbilletter med samme bookingreference holdes samlet.</div>
    </div>
    <?php endif; ?>
  </div>
  <div class="card" id="ticketlessCard" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;<?= ($ticketMode==='ticketless' && $transportModeChosen)?'':' display:none;' ?>">
  <div class="section-title"><?= $isFerry ? 'Ticketless færge' : ($isBus ? 'Ticketless bus' : ($isAir ? 'Ticketless fly' : 'Ticketless tog')) ?></div>
    <div class="small muted" style="margin-top:6px;"><?= $isFerry ? 'Udfyld først basisrejse, derefter kontrakt/ansvar og til sidst de færgespecifikke scopefelter. Pris er valgfri.' : ($isBus ? 'Udfyld først basisrejse, derefter kontrakt/ansvar og til sidst de busspecifikke scopefelter. Pris er valgfri.' : ($isAir ? 'Udfyld først basisrejse, derefter kontrakt/ansvar og til sidst de flyspecifikke scopefelter. Pris er valgfri.' : 'Udfyld det du ved. Pris er valgfri (procent beregnes altid).')) ?></div>
    <fieldset id="ticketlessFieldset" <?= $ticketMode==='ticketless' ? '' : 'disabled' ?> style="border:0; padding:0; margin:0;">
    <?php if (!$isRail): ?>
    <div class="small" style="margin-top:12px; font-weight:600;">1. Basisrejse</div>
    <div class="small muted" style="margin-top:4px;">Det passageren faktisk ved om rejsen: carrier, fra/til, tider, bookingreference og pris.</div>
    <?php endif; ?>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
      <?php if ($isRail): ?>
      <label>Land (ISO2)
        <input type="text" name="operator_country" list="countrySuggestions" value="<?= h($tlCountry) ?>" placeholder="DK" />
        <input type="hidden" name="operator_country_assumed" value="<?= h($tlCountryAssumed) ?>" />
      </label>
      <label>Scope
        <?php $sc = (string)($form['scope_choice'] ?? ''); ?>
        <select name="scope_choice">
          <option value="">VÃ¦lg...</option>
          <option value="regional" <?= $sc==='regional'?'selected':'' ?>>Regional/lokaltog</option>
          <option value="long_distance" <?= $sc==='long_distance'?'selected':'' ?>>Langdistance</option>
          <option value="international" <?= $sc==='international'?'selected':'' ?>>International</option>
        </select>
      </label>
      <label class="ticketless-optional">OperatÃ¸r (valgfri)
        <input type="text" name="operator" list="railOperatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx DSB, DB, SJ" />
      </label>
      <label class="ticketless-optional">Produkt (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx IC, ICE" />
      </label>
      <label class="ticketless-optional">Koebt klasse (valgfri)
        <?php $fc = strtolower((string)($form['fare_class_purchased'] ?? ($meta['fare_class_purchased'] ?? ($meta['_auto']['fare_class_purchased']['value'] ?? '')))); ?>
        <select name="fare_class_purchased">
          <option value="">Vaelg...</option>
          <option value="1" <?= $fc==='1'?'selected':'' ?>>1. klasse</option>
          <option value="2" <?= $fc==='2'?'selected':'' ?>>2. klasse</option>
          <option value="other" <?= $fc==='other'?'selected':'' ?>>Andet</option>
        </select>
      </label>
      <label class="ticketless-optional">Plads/komfort (valgfri)
        <?php $bst = strtolower((string)($form['berth_seat_type'] ?? ($meta['berth_seat_type'] ?? ($meta['_auto']['berth_seat_type']['value'] ?? '')))); ?>
        <select name="berth_seat_type">
          <option value="">Vaelg...</option>
          <option value="seat" <?= $bst==='seat'?'selected':'' ?>>Reserveret plads</option>
          <option value="free" <?= $bst==='free'?'selected':'' ?>>Ingen reservation</option>
          <option value="couchette" <?= $bst==='couchette'?'selected':'' ?>>Liggevogn</option>
          <option value="sleeper" <?= $bst==='sleeper'?'selected':'' ?>>Sovevogn</option>
          <option value="none" <?= $bst==='none'?'selected':'' ?>>Ingen (ukendt)</option>
        </select>
      </label>
      <label>Afgangsstation
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" />
        <input type="hidden" name="dep_station_osm_id" value="<?= h((string)($form['dep_station_osm_id'] ?? '')) ?>" />
        <input type="hidden" name="dep_station_lat" value="<?= h((string)($form['dep_station_lat'] ?? '')) ?>" />
        <input type="hidden" name="dep_station_lon" value="<?= h((string)($form['dep_station_lon'] ?? '')) ?>" />
        <input type="hidden" name="dep_station_country" value="<?= h((string)($form['dep_station_country'] ?? '')) ?>" />
        <input type="hidden" name="dep_station_type" value="<?= h((string)($form['dep_station_type'] ?? '')) ?>" />
        <input type="hidden" name="dep_station_source" value="<?= h((string)($form['dep_station_source'] ?? '')) ?>" />
        <div class="station-suggest" data-for="dep_station" style="display:none;"></div>
      </label>
      <label>Destination station
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" />
        <input type="hidden" name="arr_station_osm_id" value="<?= h((string)($form['arr_station_osm_id'] ?? '')) ?>" />
        <input type="hidden" name="arr_station_lat" value="<?= h((string)($form['arr_station_lat'] ?? '')) ?>" />
        <input type="hidden" name="arr_station_lon" value="<?= h((string)($form['arr_station_lon'] ?? '')) ?>" />
        <input type="hidden" name="arr_station_country" value="<?= h((string)($form['arr_station_country'] ?? '')) ?>" />
        <input type="hidden" name="arr_station_type" value="<?= h((string)($form['arr_station_type'] ?? '')) ?>" />
        <input type="hidden" name="arr_station_source" value="<?= h((string)($form['arr_station_source'] ?? '')) ?>" />
        <div class="station-suggest" data-for="arr_station" style="display:none;"></div>
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label class="ticketless-optional">Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label class="ticketless-optional">Planlagt ankomsttid (valgfri)
        <input type="time" name="arr_time" value="<?= h($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <?php elseif ($isFerry): ?>
      <label>Servicetype
        <?php $serviceTypeTl = (string)($form['service_type'] ?? 'passenger_service'); ?>
        <select name="service_type">
          <option value="passenger_service" <?= $serviceTypeTl==='passenger_service'?'selected':'' ?>>Passenger service</option>
          <option value="cruise" <?= $serviceTypeTl==='cruise'?'selected':'' ?>>Cruise</option>
          <option value="excursion" <?= $serviceTypeTl==='excursion'?'selected':'' ?>>Excursion</option>
          <option value="sightseeing" <?= $serviceTypeTl==='sightseeing'?'selected':'' ?>>Sightseeing</option>
        </select>
      </label>
      <label>Carrier / transportør
        <input type="text" name="operator" list="ferryOperatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx Scandlines" />
      </label>
      <label class="ticketless-optional">Produkt / overfart (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx Helsingør-Helsingborg" />
      </label>
      <label>Afgangshavn
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingør" />
      </label>
      <label>Ankomsthavn
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingborg" />
      </label>
      <div class="ticketless-optional" style="grid-column:1 / -1;">
        <details style="border:1px solid #e5e7eb; border-radius:6px; padding:10px 12px; background:#fafafa;">
          <summary style="cursor:pointer; font-weight:600;">Færgeterminaler under havnene (valgfrit)</summary>
          <div class="small muted" style="margin-top:6px;">Udfyld kun terminal, hvis den er kendt eller står på booking/billet. Havnene er stadig det primære.</div>
          <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
            <label>Afgangsterminal under afgangshavn
              <input type="text" name="dep_terminal" value="<?= h((string)($form['dep_terminal'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingør Ferry Terminal" />
            </label>
            <label>Ankomstterminal under ankomsthavn
              <input type="text" name="arr_terminal" value="<?= h((string)($form['arr_terminal'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingborg Ferry Terminal" />
            </label>
          </div>
        </details>
      </div>
      <label class="ticketless-optional">Bookingreference (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx ABC123" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label class="ticketless-optional">Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label class="ticketless-optional">Planlagt ankomsttid (valgfri)
        <input type="time" name="arr_time" value="<?= h($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <?php elseif ($isBus): ?>
      <label>Land (ISO2)
        <input type="text" name="operator_country" list="countrySuggestions" value="<?= h($tlCountry) ?>" placeholder="DK" />
        <input type="hidden" name="operator_country_assumed" value="<?= h($tlCountryAssumed) ?>" />
      </label>
      <label>Regular service?
        <?php $busRegularTl = (string)($form['bus_regular_service'] ?? 'yes'); ?>
        <select name="bus_regular_service">
          <option value="yes" <?= $busRegularTl==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $busRegularTl==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Operatør
        <input type="text" name="operator" list="busOperatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx FlixBus" />
      </label>
      <label class="ticketless-optional">Produkt (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx Ekspres" />
      </label>
      <label>Afgangssted / terminal
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Odense station" />
      </label>
      <label>Ankomststed / terminal
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Aarhus busterminal" />
      </label>
      <label class="ticketless-optional">Bookingreference (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx BUS123" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label class="ticketless-optional">Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label class="ticketless-optional">Planlagt ankomsttid (valgfri)
        <input type="time" name="arr_time" value="<?= h($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <?php elseif ($isAir): ?>
      <label>Afgangslufthavn
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx CPH" />
      </label>
      <label>Ankomstlufthavn
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx ARN" />
      </label>
      <label>Marketing carrier
          <input type="text" name="marketing_carrier" list="airOperatorSuggestions" value="<?= h((string)($form['marketing_carrier'] ?? ($modeContract['marketing_carrier'] ?? ''))) ?>" placeholder="Fx SAS" />
      </label>
      <label>Operating carrier
          <input type="text" name="operating_carrier" list="airOperatorSuggestions" value="<?= h((string)($form['operating_carrier'] ?? ($modeContract['operating_carrier'] ?? ''))) ?>" placeholder="Fx CityJet" />
      </label>
      <label class="ticketless-optional">Bookingreference / PNR (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx X7YZ12" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label class="ticketless-optional">Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label class="ticketless-optional">Planlagt ankomsttid (valgfri)
        <input type="time" name="arr_time" value="<?= h($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <?php endif; ?>
    </div>

    <?php if (!$isRail): ?>
      <div class="small" style="margin-top:14px; font-weight:600;">2. Scopefelter</div>
      <div class="small muted" style="margin-top:4px;">Bruges til at afgøre om forordningen gælder og hvilke undtagelser der kan være relevante. Felter der kan afledes fra stedvalg og carrier vises som auto.</div>
      <?php if ($isFerry): ?>
        <?php
          $depPortEu = (string)($form['departure_port_in_eu'] ?? '');
          $arrPortEu = (string)($form['arrival_port_in_eu'] ?? '');
          $carrierEuTl = (string)($form['carrier_is_eu'] ?? '');
          $depTerminalTl = (string)($form['departure_from_terminal'] ?? '');
          $ferryAutoReady = $ferryNodeLookupResolved || $ferryCarrierEuDerived !== null || $hasFerryScopeValues($form);
          $ferryScopeResolved = $ferryScopeComplete($form);
        ?>
          <div class="small ferry-scope-auto-summary" data-ferry-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $ferryAutoReady ? '' : ' display:none;' ?>">
            <div><strong>Auto-afledt scope</strong></div>
            <div>Afgangshavn i EU: <span data-ferry-scope-summary="departure_port_in_eu"><?= h($scopeValueLabel($depPortEu)) ?></span></div>
            <div>Ankomsthavn i EU: <span data-ferry-scope-summary="arrival_port_in_eu"><?= h($scopeValueLabel($arrPortEu)) ?></span></div>
            <div>Carrier er EU-operatør: <span data-ferry-scope-summary="carrier_is_eu"><?= h($scopeValueLabel($carrierEuTl)) ?></span></div>
            <div>Fra havneterminal: <span data-ferry-scope-summary="departure_from_terminal"><?= h($scopeValueLabel($depTerminalTl)) ?></span></div>
            <div>Ruteafstand i meter: <span data-ferry-scope-summary="route_distance_meters"><?= h((string)($form['route_distance_meters'] ?? 'Ikke afledt endnu')) ?></span></div>
          </div>
          <details class="ferry-scope-manual-editor" data-ferry-scope-manual-editor="1" style="margin-top:10px;<?= $ferryScopeResolved ? ' display:none;' : '' ?>" <?= $ferryAutoReady && !$ferryScopeResolved ? '' : 'open' ?>>
            <summary class="small">Redigér auto-afledte scopefelter</summary>
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <label>Afgangshavn i EU?
                <select name="departure_port_in_eu">
                  <option value="yes" <?= $depPortEu==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $depPortEu==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Ankomsthavn i EU?
                <select name="arrival_port_in_eu">
                  <option value="yes" <?= $arrPortEu==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $arrPortEu==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Carrier er EU-operatør?
                <select name="carrier_is_eu">
                  <option value="yes" <?= $carrierEuTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $carrierEuTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Fra havneterminal?
                <select name="departure_from_terminal">
                  <option value="yes" <?= $depTerminalTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $depTerminalTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label class="ticketless-optional">Ruteafstand i meter (valgfri)
                <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
              </label>
            </div>
          </details>
          <div class="grid-2 ferry-scope-manual-fields" data-ferry-scope-manual-fields="1" style="display:<?= $ferryAutoReady ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;<?= $ferryScopeResolved ? ' display:none;' : '' ?>">
            <label>Afgangshavn i EU?
              <select name="departure_port_in_eu">
                <option value="yes" <?= $depPortEu==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $depPortEu==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Ankomsthavn i EU?
              <select name="arrival_port_in_eu">
                <option value="yes" <?= $arrPortEu==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $arrPortEu==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Carrier er EU-operatør?
              <select name="carrier_is_eu">
                <option value="yes" <?= $carrierEuTl==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $carrierEuTl==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Fra havneterminal?
              <select name="departure_from_terminal">
                <option value="yes" <?= $depTerminalTl==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $depTerminalTl==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label class="ticketless-optional">Ruteafstand i meter (valgfri)
              <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
            </label>
          </div>
      <?php elseif ($isBus): ?>
        <?php
          $busRegularTl = (string)($form['bus_regular_service'] ?? 'yes');
          $busTerminalTl = (string)($form['departure_from_terminal'] ?? '');
          $boardingTl = (string)($form['boarding_in_eu'] ?? '');
          $alightingTl = (string)($form['alighting_in_eu'] ?? '');
          $busAutoReady = $busNodeLookupResolved || trim((string)($form['boarding_in_eu'] ?? '')) !== '' || trim((string)($form['alighting_in_eu'] ?? '')) !== '' || trim((string)($form['departure_from_terminal'] ?? '')) !== '' || trim((string)($form['scheduled_distance_km'] ?? '')) !== '';
          $busScopeResolved = $busScopeComplete($form);
        ?>
        <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
          <label>Regular service?
            <select name="bus_regular_service">
              <option value="yes" <?= $busRegularTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busRegularTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
        </div>
            <div class="small" data-bus-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $busAutoReady ? '' : ' display:none;' ?>">
              <div><strong>Auto-afledt scope</strong></div>
              <div>Boarding i EU: <span data-bus-scope-summary="boarding_in_eu"><?= h($scopeValueLabel($boardingTl)) ?></span></div>
              <div>Alighting i EU: <span data-bus-scope-summary="alighting_in_eu"><?= h($scopeValueLabel($alightingTl)) ?></span></div>
              <div>Fra terminal: <span data-bus-scope-summary="departure_from_terminal"><?= h($scopeValueLabel($busTerminalTl)) ?></span></div>
              <div>Planlagt distance (km): <span data-bus-scope-summary="scheduled_distance_km"><?= h((string)($form['scheduled_distance_km'] ?? 'Ikke afledt endnu')) ?></span></div>
            </div>
            <details data-bus-scope-manual-editor="1" style="margin-top:10px;<?= $busScopeResolved ? ' display:none;' : '' ?>" <?= $busScopeResolved ? '' : 'open' ?>>
              <summary class="small">Redigér auto-afledte scopefelter</summary>
              <div class="grid-2" data-bus-scope-manual-fields="1" style="display:<?= $busAutoReady ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
                <label>Fra terminal?
                  <select name="departure_from_terminal">
                    <option value="yes" <?= $busTerminalTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $busTerminalTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Boarding i EU?
                <select name="boarding_in_eu">
                  <option value="yes" <?= $boardingTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $boardingTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Alighting i EU?
                <select name="alighting_in_eu">
                  <option value="yes" <?= $alightingTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $alightingTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Planlagt distance (km)
                <input type="number" name="scheduled_distance_km" min="0" step="1" value="<?= h((string)($form['scheduled_distance_km'] ?? '')) ?>" placeholder="320" />
                </label>
              </div>
            </details>
        <?php elseif ($isAir): ?>
        <?php
          $airDepEuTl = (string)($form['departure_airport_in_eu'] ?? '');
          $airArrEuTl = (string)($form['arrival_airport_in_eu'] ?? '');
          $opCarrierEuTl = (string)($form['operating_carrier_is_eu'] ?? '');
          $mkCarrierEuTl = (string)($form['marketing_carrier_is_eu'] ?? '');
          $airDistKmTl = (string)($form['flight_distance_km'] ?? '');
          $airDistBandTl = (string)($form['air_distance_band'] ?? '');
          $airDelayThresholdTl = (string)($form['air_delay_threshold_hours'] ?? '');
          $airAutoReady = $airNodeLookupResolved
            || trim((string)($form['departure_airport_in_eu'] ?? '')) !== ''
            || trim((string)($form['arrival_airport_in_eu'] ?? '')) !== ''
            || trim((string)($form['operating_carrier_is_eu'] ?? '')) !== ''
            || trim((string)($form['marketing_carrier_is_eu'] ?? '')) !== ''
            || trim((string)($form['flight_distance_km'] ?? '')) !== ''
            || trim((string)($form['air_distance_band'] ?? '')) !== '';
          $airScopeResolved = $airScopeComplete($form);
        ?>
          <div class="small" data-air-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $airAutoReady ? '' : ' display:none;' ?>">
            <div><strong>Auto-afledt scope</strong></div>
            <div>Afgangslufthavn i EU: <span data-air-scope-summary="departure_airport_in_eu"><?= h($scopeValueLabel($airDepEuTl)) ?></span></div>
            <div>Ankomstlufthavn i EU: <span data-air-scope-summary="arrival_airport_in_eu"><?= h($scopeValueLabel($airArrEuTl)) ?></span></div>
            <div>Operating carrier er EU-operatør: <span data-air-scope-summary="operating_carrier_is_eu"><?= h($scopeValueLabel($opCarrierEuTl)) ?></span></div>
            <div>Marketing carrier er EU-operatør: <span data-air-scope-summary="marketing_carrier_is_eu"><?= h($scopeValueLabel($mkCarrierEuTl)) ?></span></div>
            <div>Flydistance (km): <span data-air-scope-summary="flight_distance_km"><?= h($airDistKmTl !== '' ? $airDistKmTl : 'Ikke afledt endnu') ?></span></div>
            <div>Distancekategori: <span data-air-scope-summary="air_distance_band"><?= h($airDistanceBandLabel($airDistBandTl)) ?></span></div>
            <div>Art. 6 delay-threshold: <span data-air-scope-summary="air_delay_threshold_hours"><?= h($airDelayThresholdLabel($airDelayThresholdTl)) ?></span></div>
          </div>
          <details data-air-scope-manual-editor="1" style="margin-top:10px;<?= $airScopeResolved ? ' display:none;' : '' ?>" <?= $airScopeResolved ? '' : 'open' ?>>
            <summary class="small">Redigér auto-afledte scopefelter</summary>
            <div class="grid-2" data-air-scope-manual-fields="1" style="display:<?= $airScopeResolved ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <label>Afgangslufthavn i EU?
                <select name="departure_airport_in_eu">
                  <option value="yes" <?= $airDepEuTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $airDepEuTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Ankomstlufthavn i EU?
                <select name="arrival_airport_in_eu">
                  <option value="yes" <?= $airArrEuTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $airArrEuTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Operating carrier er EU-operatør?
                <select name="operating_carrier_is_eu">
                  <option value="yes" <?= $opCarrierEuTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $opCarrierEuTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Marketing carrier er EU-operatør?
                <select name="marketing_carrier_is_eu">
                  <option value="yes" <?= $mkCarrierEuTl==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $mkCarrierEuTl==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Flydistance (km)
                <input type="number" name="flight_distance_km" min="0" step="1" value="<?= h($airDistKmTl) ?>" placeholder="1850" />
              </label>
              <label>Distancekategori
                <select name="air_distance_band">
                  <option value="">Auto / afled fra lufthavne</option>
                  <option value="up_to_1500" <?= $airDistBandTl==='up_to_1500'?'selected':'' ?>>1500 km eller mindre</option>
                  <option value="intra_eu_over_1500" <?= $airDistBandTl==='intra_eu_over_1500'?'selected':'' ?>>Inden for EU over 1500 km</option>
                  <option value="other_1500_to_3500" <?= $airDistBandTl==='other_1500_to_3500'?'selected':'' ?>>Øvrige flyvninger 1500-3500 km</option>
                  <option value="other_over_3500" <?= $airDistBandTl==='other_over_3500'?'selected':'' ?>>Øvrige flyvninger over 3500 km</option>
                </select>
              </label>
            </div>
            <input type="hidden" name="air_delay_threshold_hours" value="<?= h($airDelayThresholdTl) ?>" />
            <input type="hidden" name="intra_eu_over_1500" value="<?= h((string)($form['intra_eu_over_1500'] ?? '')) ?>" />
          </details>
      <?php endif; ?>
    <?php endif; ?>

    <datalist id="railOperatorSuggestions">
      <?php foreach ($allOperators as $opName): ?>
        <option value="<?= h($opName) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <datalist id="ferryOperatorSuggestions">
      <?php foreach ($ferryOperators as $opName): ?>
        <option value="<?= h($opName) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <datalist id="busOperatorSuggestions">
      <?php foreach ($busOperators as $opName): ?>
        <option value="<?= h($opName) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <datalist id="airOperatorSuggestions">
      <?php foreach ($airOperators as $opName): ?>
        <option value="<?= h($opName) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <datalist id="productSuggestions"></datalist>
    <datalist id="countrySuggestions">
      <?php foreach ($opCatalog->getCountries() as $cc => $nm): ?>
        <option value="<?= h($cc) ?>"><?= h($nm) ?></option>
      <?php endforeach; ?>
    </datalist>

    <?php $pk = (string)($form['price_known'] ?? ((string)($form['price'] ?? '') !== '' ? 'yes' : 'no')); ?>
    <div class="small" style="margin-top:12px;"><strong>Kender du prisen?</strong></div>
    <div class="small" style="margin-top:6px;">
      <label class="mr8"><input type="radio" name="price_known" value="yes" <?= $pk==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="price_known" value="no" <?= $pk==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="ticketlessPriceBlock" style="margin-top:8px; display:<?= $pk==='yes'?'block':'none' ?>;">
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <input type="text" name="price" value="<?= h((string)($form['price'] ?? ($meta['_auto']['price']['value'] ?? ''))) ?>" placeholder="Fx 399.00" style="flex:1 1 200px;" />
        <?php $cur = strtoupper((string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? ''))); ?>
        <select name="price_currency" style="min-width:120px;">
          <option value="">Auto</option>
          <?php foreach (['EUR','DKK','SEK','NOK','GBP','CHF','BGN','CZK','HUF','PLN','RON'] as $cc): ?>
            <option value="<?= $cc ?>" <?= $cur===$cc?'selected':'' ?>><?= $cc ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="small muted" style="margin-top:4px;">Hvis prisen er ukendt, viser vi kun kompensation i procent.</div>
      <?php if ($isFerry): ?>
        <?php
          $tripTypeTicketless = (string)($form['trip_type'] ?? 'single');
          if (!in_array($tripTypeTicketless, ['single', 'return'], true)) { $tripTypeTicketless = 'single'; }
        ?>
        <div class="small" style="margin-top:12px;"><strong>Billetype</strong></div>
        <div class="small muted" style="margin-top:4px;">Brug returbillet hvis samme booking dækker baade udrejse og hjemrejse.</div>
        <div style="margin-top:8px;">
          <select name="trip_type" data-ferry-trip-type="1" data-return-target="ticketlessReturnTripBlock" style="min-width:220px;">
            <option value="single" <?= $tripTypeTicketless==='single'?'selected':'' ?>>Enkeltrejse</option>
            <option value="return" <?= $tripTypeTicketless==='return'?'selected':'' ?>>Returbillet</option>
          </select>
        </div>
        <div id="ticketlessReturnTripBlock" style="margin-top:10px; display:<?= $tripTypeTicketless==='return'?'block':'none' ?>;">
          <div class="card" style="padding:10px; border:1px solid #e5e7eb; background:#fafafa; border-radius:6px;">
            <div class="small"><strong>Hjemrejse / returben</strong></div>
            <div class="small muted" style="margin-top:4px;">Samme princip som rail: samlet pris oeverst, og mulighed for at splitte ud- og hjemrejse hvis billetten viser det.</div>
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <?php $affectedLegTicketless = (string)($form['affected_leg'] ?? ''); ?>
              <label>Hvilken del af rejsen blev ramt?
                <select name="affected_leg">
                  <option value="">Vaelg...</option>
                  <option value="outbound" <?= $affectedLegTicketless==='outbound'?'selected':'' ?>>Udrejse</option>
                  <option value="return" <?= $affectedLegTicketless==='return'?'selected':'' ?>>Hjemrejse</option>
                  <option value="both" <?= $affectedLegTicketless==='both'?'selected':'' ?>>Begge</option>
                  <option value="unknown" <?= $affectedLegTicketless==='unknown'?'selected':'' ?>>Ved ikke</option>
                </select>
              </label>
              <label class="ticketless-optional">Udrejsepris (valgfri)
                <input type="text" name="outbound_fare_amount" value="<?= h((string)($form['outbound_fare_amount'] ?? '')) ?>" placeholder="349,00" />
              </label>
              <label class="ticketless-optional">Hjemrejsepris (valgfri)
                <input type="text" name="return_fare_amount" value="<?= h((string)($form['return_fare_amount'] ?? '')) ?>" placeholder="449,00" />
              </label>
              <label class="ticketless-optional">Hjemrejse dato (valgfri)
                <input type="text" name="return_dep_date" value="<?= h((string)($form['return_dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
              </label>
              <label class="ticketless-optional">Hjemrejse afgangstid (valgfri)
                <input type="text" name="return_dep_time" value="<?= h((string)($form['return_dep_time'] ?? '')) ?>" placeholder="HH:MM" />
              </label>
              <label class="ticketless-optional">Hjemrejse fra havn (valgfri)
                <input type="text" name="return_dep_station" value="<?= h((string)($form['return_dep_station'] ?? '')) ?>" placeholder="Fx Ystad" />
              </label>
              <label class="ticketless-optional">Hjemrejse til havn (valgfri)
                <input type="text" name="return_arr_station" value="<?= h((string)($form['return_arr_station'] ?? '')) ?>" placeholder="Fx Ronne" />
              </label>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
    </fieldset>
  </div>
  <?php if (!empty($modeContractCardHtml) && $ticketMode !== 'ticket'): ?>
    <?= $modeContractCardHtml ?>
  <?php endif; ?>
  <?php
    // Lightweight status so users see that parsing happened even when no choices are shown
    $segCountTop = isset($meta['_segments_auto']) && is_array($meta['_segments_auto']) ? count((array)$meta['_segments_auto']) : 0;
    $llmFlagRawTop = function_exists('env') ? env('USE_LLM_STRUCTURING') : getenv('USE_LLM_STRUCTURING');
    $llmOnTop = in_array(strtolower((string)$llmFlagRawTop), ['1','true','yes','on'], true);
  ?>
  <div class="small muted" style="margin-top:6px;">
    Auto: <?= (int)$segCountTop ?> segmenter fundet<?= $segCountTop===0 ? (' - LLM-strukturering: ' . ($llmOnTop ? 'til' : 'fra')) : '' ?>.
  </div>

  <!-- Rail-kontraktblokken vises længere nede -->

  <button
    type="button"
    id="toggleJourneyFields"
    class="button button-outline"
    data-has-tickets="<?= $hasTickets ? '1' : '0' ?>"
    data-has-prefill="<?= $hasJourneyFieldPrefill ? '1' : '0' ?>"
    data-default-open="<?= $journeyFieldsOpenDefault ? '1' : '0' ?>"
    data-force-open="<?= $forceJourneyFieldsOpen ? '1' : '0' ?>"
    data-pinned-open="<?= $pinJourneyFieldsForUploadedMode ? '1' : '0' ?>"
    data-collapsible="<?= $journeyFieldsCollapsible ? '1' : '0' ?>"
    data-transport-mode="<?= h($transportMode) ?>"
    data-ticket-mode="<?= h($ticketMode) ?>"
    data-label-prefix="Vis/skjul rejsefelter (3.1–3.5)"
    data-storage-key="flow.entitlements.journeyFieldsOpen.step2"
    style="margin-top:12px; margin-bottom:8px;<?= ($ticketMode==='ticketless' || $pinJourneyFieldsForUploadedMode)?' display:none;':'' ?>"
  >Vis/skjul rejsefelter (3.1–3.5)</button>
  <div
    id="journeyFields"
    data-render-mode="<?= h($transportModeRender) ?>"
    data-upload-pinned="<?= $pinJourneyFieldsForUploadedMode ? '1' : '0' ?>"
    style="<?= (($journeyFieldsOpenDefault || $pinJourneyFieldsForUploadedMode) ? 'display:block;' : 'display:none;') ?>"
  >
  <fieldset
    id="journeyFieldsFieldset"
    data-render-mode="<?= h($transportModeRender) ?>"
    data-upload-pinned="<?= $pinJourneyFieldsForUploadedMode ? '1' : '0' ?>"
    <?= $ticketMode==='ticketless' ? 'disabled' : '' ?>
    style="border:0; padding:0; margin:0;"
  >
  <?php if ($showGenericJourneyFields): ?>
  <div id="genericJourneyFields">
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Basisrejse (transportform ikke afgjort endnu)</strong>
    <div class="small muted" style="margin-top:6px;">Uploaden er læst, men transportformen er ikke afgjort endnu. Du kan stadig udfylde de fælles rejsefelter her.</div>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
      <label>Operatør / carrier
        <input type="text" name="operator" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" />
      </label>
      <label>Land
        <input type="text" name="operator_country" value="<?= h($meta['_auto']['operator_country']['value'] ?? ($form['operator_country'] ?? '')) ?>" />
      </label>
      <label>Produkt / bookingtype
        <input type="text" name="operator_product" value="<?= h($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? '')) ?>" />
      </label>
      <label>Bookingreference / billetnummer
        <input type="text" name="ticket_no" value="<?= h($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ($journey['bookingRef'] ?? '')))) ?>" />
      </label>
      <label>Afgangsdato
        <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>Planlagt afgangstid
        <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>Fra
        <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" />
      </label>
      <label>Til
        <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" />
      </label>
      <label>Planlagt ankomsttid
        <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>Pris
        <input type="text" name="price" value="<?= h($meta['_auto']['price']['value'] ?? ($form['price'] ?? '')) ?>" />
      </label>
    </div>
  </div>
  </div><!-- /genericJourneyFields -->
  <?php endif; ?>
  <?php if ($isRail): ?>
  <div id="railJourneyFields">
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>3.1. Name of railway undertaking:</strong>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
      <label>OperatÃ¸r
        <input type="text" name="operator" list="railOperatorSuggestions" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" />
      </label>
      <label>Land
        <input type="text" name="operator_country" value="<?= h($meta['_auto']['operator_country']['value'] ?? ($form['operator_country'] ?? '')) ?>" />
      </label>
      <label>Produkt
        <input type="text" name="operator_product" value="<?= h($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? '')) ?>" />
      </label>
    </div>
  </div>

  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>3.2. Scheduled journey</strong>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
      <label>3.2.1. Departure date (YYYY-MM-DD)
        <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>3.2.4. Scheduled time of departure
        <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>3.2.2. Departure station
        <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" />
      </label>
      <label>3.2.3. Destination station
        <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" />
      </label>
      <label>3.2.5. Scheduled time of arrival
        <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" />
      </label>
      <label>3.2.6. Train no./category
        <input type="text" name="train_no" value="<?= h($meta['_auto']['train_no']['value'] ?? ($form['train_no'] ?? '')) ?>" />
      </label>
      <label>3.2.7. Ticket Number(s)/Booking Reference
        <?php
          $ticketNoVal = $meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ($journey['bookingRef'] ?? '')));
          if ($ticketNoVal === '' || $ticketNoVal === null) {
            // Fallback: try first grouped ticket PNR
            if (!empty($groupedTickets)) {
              foreach ((array)$groupedTickets as $g) { if (!empty($g['pnr'])) { $ticketNoVal = (string)$g['pnr']; break; } }
            }
          }
        ?>
        <input type="text" name="ticket_no" value="<?= h((string)$ticketNoVal) ?>" />
      </label>
      <?php
        $priceVal = (string)($meta['_auto']['price']['value'] ?? ($form['price'] ?? ''));
        $currencyCandidates = [
          (string)($form['price_currency'] ?? ''),
          (string)($meta['_auto']['price_currency']['value'] ?? ''),
          (string)($meta['_auto']['price']['currency'] ?? ''),
          (string)($journey['ticketPrice']['currency'] ?? ''),
        ];
        $curCurrency = '';
        foreach ($currencyCandidates as $c) {
          $cc = strtoupper(trim($c));
          if ($cc !== '' && preg_match('/^(EUR|DKK|SEK|NOK|GBP|CHF|BGN|CZK|HUF|PLN|RON)$/', $cc)) { $curCurrency = $cc; break; }
        }
      ?>
      <label>3.2.8. Ticket price(s)
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <input type="text" name="price" value="<?= h($priceVal) ?>" placeholder="100 EUR" style="flex:1 1 200px;" />
          <select name="price_currency" style="min-width:120px;">
            <option value="">Auto</option>
            <?php foreach (['EUR','DKK','SEK','NOK','GBP','CHF','BGN','CZK','HUF','PLN','RON'] as $cc): ?>
              <option value="<?= $cc ?>" <?= $curCurrency===$cc?'selected':'' ?>><?= $cc ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="small muted" style="margin-top:4px;">VÃ¦lg valuta hvis auto-detektion ikke rammer rigtigt.</div>
      </label>
      <div class="missed-connection-section" style="display:none;">
        <?= $this->element('missed_connection_block', compact('meta','form','groupedTickets')) ?>
      </div>
  </div>
  </div><!-- /railJourneyFields -->
  <?php endif; ?>
  <?php if ($isFerry || $isBus || $isAir): ?>
  <div id="modeJourneyFields">
    <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
      <strong><?= $isFerry ? 'Færge — basisrejse og scope' : ($isBus ? 'Bus — basisrejse og scope' : 'Fly — basisrejse og scope') ?></strong>
      <div class="small muted" style="margin-top:6px;">
        <?= $isFerry ? 'LLM har allerede læst det den kan fra uploaden. Udfyld kun det der mangler i basisrejsen og scopefelterne. Kontrakt og ansvar håndteres i den fælles multimodale blok ovenfor.' : ($isBus ? 'LLM har allerede læst det den kan fra uploaden. Udfyld kun det der mangler i basisrejsen og scopefelterne. Kontrakt og ansvar håndteres i den fælles multimodale blok ovenfor.' : 'LLM har allerede læst det den kan fra uploaden. Udfyld kun det der mangler i basisrejsen og scopefelterne. Kontrakt og ansvar håndteres i den fælles multimodale blok ovenfor.') ?>
      </div>
      <?php if ($isFerryTicketless): ?>
      <div class="small" style="margin-top:10px; background:#eef6ff; border:1px solid #cfe2ff; border-radius:6px; padding:8px;">
        <strong>Ticketless ferry:</strong>
        Angiv planlagt afgangstid og planlagt ankomsttid, hvis du kender dem. Saa kan vi beregne rejsens varighed automatisk i kompensationstrinnet i stedet for at bede dig udfylde den manuelt senere.
      </div>
      <?php endif; ?>
      <div class="small" style="margin-top:12px; font-weight:600;">1. Basisrejse</div>
      <div class="small muted" style="margin-top:4px;">Carrier/operator, fra/til, tider, bookingreference og pris.</div>
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
        <label><?= $isAir ? 'Carrier / operating carrier' : ($isFerry ? 'Carrier' : 'Operatør') ?>
          <input type="text" name="operator" list="<?= h($currentOperatorListId) ?>" value="<?= h($firstNonEmpty($meta['_auto']['operator']['value'] ?? '', $form['operator'] ?? '', $modeContract['operator'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Scandlines' : ($isBus ? 'Fx FlixBus' : 'Fx SAS') ?>" />
        </label>
        <label>Produkt / bookingtype
          <input type="text" name="operator_product" value="<?= h($firstNonEmpty($meta['_auto']['operator_product']['value'] ?? '', $form['operator_product'] ?? '', $modeContract['operator_product'] ?? '')) ?>" placeholder="<?= $isAir ? 'Fx Economy / Flex' : 'Fx Standard ticket' ?>" />
        </label>
        <label><?= $isFerry ? 'Afgangshavn' : ($isBus ? 'Afgangssted / terminal' : 'Afgangslufthavn') ?>
          <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Helsingør' : ($isBus ? 'Fx København Busterminal' : 'Fx CPH') ?>" />
        </label>
        <label><?= $isFerry ? 'Ankomsthavn' : ($isBus ? 'Ankomststed / terminal' : 'Ankomstlufthavn') ?>
          <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Helsingborg' : ($isBus ? 'Fx Aarhus Rutebilstation' : 'Fx ARN') ?>" />
        </label>
        <?php if ($isFerry): ?>
        <div style="grid-column:1 / -1;">
          <details style="border:1px solid #e5e7eb; border-radius:6px; padding:10px 12px; background:#fafafa;">
            <summary style="cursor:pointer; font-weight:600;">Færgeterminaler under havnene (valgfrit)</summary>
            <div class="small muted" style="margin-top:6px;">Ved upload er terminaler kun et ekstra præciseringslag. Havnene er fortsat de vigtigste steder i TRIN 2.</div>
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <label>Afgangsterminal under afgangshavn
                <input type="text" name="dep_terminal" value="<?= h((string)($form['dep_terminal'] ?? '')) ?>" placeholder="Fx Helsingør Ferry Terminal" />
              </label>
              <label>Ankomstterminal under ankomsthavn
                <input type="text" name="arr_terminal" value="<?= h((string)($form['arr_terminal'] ?? '')) ?>" placeholder="Fx Helsingborg Ferry Terminal" />
              </label>
            </div>
          </details>
        </div>
        <?php endif; ?>
        <label>Afgangsdato
          <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
        </label>
        <label>Planlagt afgangstid
          <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" <?= $isFerryTicketless ? 'required' : '' ?> />
        </label>
        <label>Planlagt ankomsttid
          <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" <?= $isFerryTicketless ? 'required' : '' ?> />
        </label>
        <label><?= $isAir ? 'Bookingreference / e-ticket' : 'Bookingreference / billetnummer' ?>
          <input type="text" name="ticket_no" value="<?= h((string)($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ($journey['bookingRef'] ?? ''))))) ?>" />
        </label>
        <?php if ($isAir): ?>
        <label>Marketing carrier
          <input type="text" name="marketing_carrier" list="airOperatorSuggestions" value="<?= h($firstNonEmpty($form['marketing_carrier'] ?? '', $meta['_auto']['marketing_carrier']['value'] ?? '', $modeContract['marketing_carrier'] ?? '')) ?>" placeholder="Fx SAS" />
        </label>
        <label>Operating carrier
          <input type="text" name="operating_carrier" list="airOperatorSuggestions" value="<?= h($firstNonEmpty($form['operating_carrier'] ?? '', $meta['_auto']['operating_carrier']['value'] ?? '', $modeContract['operating_carrier'] ?? '')) ?>" placeholder="Fx CityJet" />
        </label>
        <?php endif; ?>
        <label>Pris (valgfri)
          <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <input type="text" name="price" value="<?= h((string)($meta['_auto']['price']['value'] ?? ($form['price'] ?? ''))) ?>" placeholder="<?= $isBus ? '250 DKK' : '100 EUR' ?>" style="flex:1 1 200px;" />
            <select name="price_currency" style="min-width:120px;">
              <option value="">Auto</option>
              <?php foreach (['EUR','DKK','SEK','NOK','GBP','CHF'] as $cc): ?>
                <option value="<?= $cc ?>" <?= strtoupper((string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? '')))===$cc?'selected':'' ?>><?= $cc ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </label>
      </div>
      <?php if ($isFerry): ?>
        <?php
          $tripTypeMode = (string)($form['trip_type'] ?? 'single');
          if ($tripTypeMode === 'single' && $autoReturnSegment !== null) { $tripTypeMode = 'return'; }
          if (!in_array($tripTypeMode, ['single', 'return'], true)) { $tripTypeMode = 'single'; }
          $returnDepDateMode = trim((string)($form['return_dep_date'] ?? '')) !== ''
            ? (string)$form['return_dep_date']
            : (string)($autoReturnSegment['depDate'] ?? '');
          $returnDepTimeMode = trim((string)($form['return_dep_time'] ?? '')) !== ''
            ? (string)$form['return_dep_time']
            : (string)($autoReturnSegment['schedDep'] ?? '');
          $returnDepStationMode = trim((string)($form['return_dep_station'] ?? '')) !== ''
            ? (string)$form['return_dep_station']
            : (string)($autoReturnSegment['from'] ?? '');
          $returnArrStationMode = trim((string)($form['return_arr_station'] ?? '')) !== ''
            ? (string)$form['return_arr_station']
            : (string)($autoReturnSegment['to'] ?? '');
        ?>
        <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
          <strong>Returbillet / hjemrejse</strong>
          <div class="small muted" style="margin-top:6px;">Hvis booking dækker baade udrejse og hjemrejse, registrer det her. Det bruges senere i kompensation og oversigt paa samme princip som rail-appens opdeling af billetgrundlag.</div>
          <div style="margin-top:10px;">
            <select name="trip_type" data-ferry-trip-type="1" data-return-target="modeJourneyReturnTripBlock" style="min-width:220px;">
              <option value="single" <?= $tripTypeMode==='single'?'selected':'' ?>>Enkeltrejse</option>
              <option value="return" <?= $tripTypeMode==='return'?'selected':'' ?>>Returbillet</option>
            </select>
          </div>
          <div id="modeJourneyReturnTripBlock" style="margin-top:10px; display:<?= $tripTypeMode==='return'?'block':'none' ?>;">
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
              <?php $affectedLegMode = (string)($form['affected_leg'] ?? ''); ?>
              <label>Hvilken del af rejsen blev ramt?
                <select name="affected_leg">
                  <option value="">Vaelg...</option>
                  <option value="outbound" <?= $affectedLegMode==='outbound'?'selected':'' ?>>Udrejse</option>
                  <option value="return" <?= $affectedLegMode==='return'?'selected':'' ?>>Hjemrejse</option>
                  <option value="both" <?= $affectedLegMode==='both'?'selected':'' ?>>Begge</option>
                  <option value="unknown" <?= $affectedLegMode==='unknown'?'selected':'' ?>>Ved ikke</option>
                </select>
              </label>
              <label>Udrejsepris (valgfri)
                <input type="text" name="outbound_fare_amount" value="<?= h((string)($form['outbound_fare_amount'] ?? '')) ?>" placeholder="349,00" />
              </label>
              <label>Hjemrejsepris (valgfri)
                <input type="text" name="return_fare_amount" value="<?= h((string)($form['return_fare_amount'] ?? '')) ?>" placeholder="449,00" />
              </label>
              <label>Hjemrejse dato (valgfri)
                <input type="text" name="return_dep_date" value="<?= h($returnDepDateMode) ?>" placeholder="YYYY-MM-DD" />
              </label>
              <label>Hjemrejse afgangstid (valgfri)
                <input type="text" name="return_dep_time" value="<?= h($returnDepTimeMode) ?>" placeholder="HH:MM" />
              </label>
              <label>Hjemrejse fra havn (valgfri)
                <input type="text" name="return_dep_station" value="<?= h($returnDepStationMode) ?>" placeholder="Fx Ystad" />
              </label>
              <label>Hjemrejse til havn (valgfri)
                <input type="text" name="return_arr_station" value="<?= h($returnArrStationMode) ?>" placeholder="Fx Ronne" />
              </label>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <div class="small" style="margin-top:14px; font-weight:600;">2. Scopefelter</div>
      <div class="small muted" style="margin-top:4px;">Bruges til at afgøre om forordningen gælder og hvilke undtagelser der kan være relevante. Felter der kan afledes fra stedvalg og carrier vises som auto.</div>
      <?php if ($isFerry): ?>
        <?php
          $serviceTypeUpload = (string)($form['service_type'] ?? 'passenger_service');
          $depPortEuUpload = (string)($form['departure_port_in_eu'] ?? '');
          $arrPortEuUpload = (string)($form['arrival_port_in_eu'] ?? '');
          $carrierEuUpload = (string)($form['carrier_is_eu'] ?? '');
          $depTerminalUpload = (string)($form['departure_from_terminal'] ?? '');
          $ferryUploadAutoReady = $ferryNodeLookupResolved || $ferryCarrierEuDerived !== null || $hasFerryScopeValues($form);
          $ferryUploadScopeResolved = $ferryScopeComplete($form);
        ?>
        <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
          <label>Servicetype
            <select name="service_type">
              <option value="passenger_service" <?= $serviceTypeUpload==='passenger_service'?'selected':'' ?>>Passenger service</option>
              <option value="cruise" <?= $serviceTypeUpload==='cruise'?'selected':'' ?>>Cruise</option>
              <option value="excursion" <?= $serviceTypeUpload==='excursion'?'selected':'' ?>>Excursion</option>
              <option value="sightseeing" <?= $serviceTypeUpload==='sightseeing'?'selected':'' ?>>Sightseeing</option>
            </select>
          </label>
        </div>
          <div class="small ferry-scope-auto-summary" data-ferry-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $ferryUploadAutoReady ? '' : ' display:none;' ?>">
            <div><strong>Auto-afledt scope</strong></div>
            <div>Afgangshavn i EU: <span data-ferry-scope-summary="departure_port_in_eu"><?= h($scopeValueLabel($depPortEuUpload)) ?></span></div>
            <div>Ankomsthavn i EU: <span data-ferry-scope-summary="arrival_port_in_eu"><?= h($scopeValueLabel($arrPortEuUpload)) ?></span></div>
            <div>Carrier er EU-operatør: <span data-ferry-scope-summary="carrier_is_eu"><?= h($scopeValueLabel($carrierEuUpload)) ?></span></div>
            <div>Fra havneterminal: <span data-ferry-scope-summary="departure_from_terminal"><?= h($scopeValueLabel($depTerminalUpload)) ?></span></div>
            <div>Ruteafstand i meter: <span data-ferry-scope-summary="route_distance_meters"><?= h((string)($form['route_distance_meters'] ?? 'Ikke afledt endnu')) ?></span></div>
          </div>
          <details class="ferry-scope-manual-editor" data-ferry-scope-manual-editor="1" style="margin-top:10px;<?= $ferryUploadScopeResolved ? ' display:none;' : '' ?>" <?= $ferryUploadAutoReady && !$ferryUploadScopeResolved ? '' : 'open' ?>>
            <summary class="small">Redigér auto-afledte scopefelter</summary>
            <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <label>Afgangshavn i EU?
                <select name="departure_port_in_eu">
                  <option value="yes" <?= $depPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $depPortEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Ankomsthavn i EU?
                <select name="arrival_port_in_eu">
                  <option value="yes" <?= $arrPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $arrPortEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Carrier er EU-operatør?
                <select name="carrier_is_eu">
                  <option value="yes" <?= $carrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $carrierEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Fra havneterminal?
                <select name="departure_from_terminal">
                  <option value="yes" <?= $depTerminalUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $depTerminalUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Ruteafstand i meter (valgfri)
                <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
              </label>
            </div>
          </details>
          <div class="grid-2 ferry-scope-manual-fields" data-ferry-scope-manual-fields="1" style="display:<?= $ferryUploadAutoReady ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;<?= $ferryUploadScopeResolved ? ' display:none;' : '' ?>">
            <label>Afgangshavn i EU?
              <select name="departure_port_in_eu">
                <option value="yes" <?= $depPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $depPortEuUpload==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Ankomsthavn i EU?
              <select name="arrival_port_in_eu">
                <option value="yes" <?= $arrPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $arrPortEuUpload==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Carrier er EU-operatør?
              <select name="carrier_is_eu">
                <option value="yes" <?= $carrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $carrierEuUpload==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Fra havneterminal?
              <select name="departure_from_terminal">
                <option value="yes" <?= $depTerminalUpload==='yes'?'selected':'' ?>>Ja</option>
                <option value="no" <?= $depTerminalUpload==='no'?'selected':'' ?>>Nej</option>
              </select>
            </label>
            <label>Ruteafstand i meter (valgfri)
              <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
            </label>
          </div>
      <?php elseif ($isBus): ?>
        <?php
          $busRegularUpload = (string)($form['bus_regular_service'] ?? 'yes');
          $busTerminalUpload = (string)($form['departure_from_terminal'] ?? '');
          $boardingInEuUpload = (string)($form['boarding_in_eu'] ?? '');
          $alightingInEuUpload = (string)($form['alighting_in_eu'] ?? '');
          $busUploadAutoReady = $busNodeLookupResolved || trim((string)($form['boarding_in_eu'] ?? '')) !== '' || trim((string)($form['alighting_in_eu'] ?? '')) !== '' || trim((string)($form['departure_from_terminal'] ?? '')) !== '' || trim((string)($form['scheduled_distance_km'] ?? '')) !== '';
          $busUploadScopeResolved = $busScopeComplete($form);
        ?>
        <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
          <label>Regular service?
            <select name="bus_regular_service">
              <option value="yes" <?= $busRegularUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busRegularUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
        </div>
            <div class="small" data-bus-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $busUploadAutoReady ? '' : ' display:none;' ?>">
              <div><strong>Auto-afledt scope</strong></div>
              <div>Boarding i EU: <span data-bus-scope-summary="boarding_in_eu"><?= h($scopeValueLabel($boardingInEuUpload)) ?></span></div>
              <div>Alighting i EU: <span data-bus-scope-summary="alighting_in_eu"><?= h($scopeValueLabel($alightingInEuUpload)) ?></span></div>
              <div>Fra terminal: <span data-bus-scope-summary="departure_from_terminal"><?= h($scopeValueLabel($busTerminalUpload)) ?></span></div>
              <div>Planlagt distance (km): <span data-bus-scope-summary="scheduled_distance_km"><?= h((string)($form['scheduled_distance_km'] ?? 'Ikke afledt endnu')) ?></span></div>
            </div>
            <details data-bus-scope-manual-editor="1" style="margin-top:10px;<?= $busUploadScopeResolved ? ' display:none;' : '' ?>" <?= $busUploadScopeResolved ? '' : 'open' ?>>
              <summary class="small">Redigér auto-afledte scopefelter</summary>
              <div class="grid-2" data-bus-scope-manual-fields="1" style="display:<?= $busUploadAutoReady ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
                <label>Fra terminal?
                  <select name="departure_from_terminal">
                    <option value="yes" <?= $busTerminalUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $busTerminalUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Boarding i EU?
                <select name="boarding_in_eu">
                  <option value="yes" <?= $boardingInEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $boardingInEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Alighting i EU?
                <select name="alighting_in_eu">
                  <option value="yes" <?= $alightingInEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $alightingInEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Planlagt distance (km)
                <input type="number" name="scheduled_distance_km" min="0" step="1" value="<?= h((string)($form['scheduled_distance_km'] ?? '')) ?>" placeholder="320" />
                </label>
              </div>
            </details>
        <?php elseif ($isAir): ?>
        <?php
          $airDepEuUpload = (string)($form['departure_airport_in_eu'] ?? '');
          $airArrEuUpload = (string)($form['arrival_airport_in_eu'] ?? '');
          $opCarrierEuUpload = (string)($form['operating_carrier_is_eu'] ?? '');
          $mkCarrierEuUpload = (string)($form['marketing_carrier_is_eu'] ?? '');
          $airDistKmUpload = (string)($form['flight_distance_km'] ?? '');
          $airDistBandUpload = (string)($form['air_distance_band'] ?? '');
          $airDelayThresholdUpload = (string)($form['air_delay_threshold_hours'] ?? '');
          $airConnectionTypeUpload = (string)($form['air_connection_type'] ?? ($modeContract['air_connection_type'] ?? ''));
          $airUploadAutoReady = $airNodeLookupResolved
            || trim((string)($form['departure_airport_in_eu'] ?? '')) !== ''
            || trim((string)($form['arrival_airport_in_eu'] ?? '')) !== ''
            || trim((string)($form['operating_carrier_is_eu'] ?? '')) !== ''
            || trim((string)($form['marketing_carrier_is_eu'] ?? '')) !== ''
            || trim((string)($form['flight_distance_km'] ?? '')) !== ''
            || trim((string)($form['air_distance_band'] ?? '')) !== '';
          $airUploadScopeResolved = $airScopeComplete($form);
        ?>
          <div class="small" data-air-scope-auto-summary="1" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;<?= $airUploadAutoReady ? '' : ' display:none;' ?>">
            <div><strong>Auto-afledt scope</strong></div>
            <div>Afgangslufthavn i EU: <span data-air-scope-summary="departure_airport_in_eu"><?= h($scopeValueLabel($airDepEuUpload)) ?></span></div>
            <div>Ankomstlufthavn i EU: <span data-air-scope-summary="arrival_airport_in_eu"><?= h($scopeValueLabel($airArrEuUpload)) ?></span></div>
            <div>Operating carrier er EU-operatør: <span data-air-scope-summary="operating_carrier_is_eu"><?= h($scopeValueLabel($opCarrierEuUpload)) ?></span></div>
            <div>Marketing carrier er EU-operatør: <span data-air-scope-summary="marketing_carrier_is_eu"><?= h($scopeValueLabel($mkCarrierEuUpload)) ?></span></div>
            <div>Flydistance (km): <span data-air-scope-summary="flight_distance_km"><?= h($airDistKmUpload !== '' ? $airDistKmUpload : 'Ikke afledt endnu') ?></span></div>
            <div>Distancekategori: <span data-air-scope-summary="air_distance_band"><?= h($airDistanceBandLabel($airDistBandUpload)) ?></span></div>
            <div>Art. 6 delay-threshold: <span data-air-scope-summary="air_delay_threshold_hours"><?= h($airDelayThresholdLabel($airDelayThresholdUpload)) ?></span></div>
          </div>
          <details data-air-scope-manual-editor="1" style="margin-top:10px;<?= $airUploadScopeResolved ? ' display:none;' : '' ?>" <?= $airUploadScopeResolved ? '' : 'open' ?>>
            <summary class="small">Redigér auto-afledte scopefelter</summary>
            <div class="grid-2" data-air-scope-manual-fields="1" style="display:<?= $airUploadScopeResolved ? 'none' : 'grid' ?>; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
              <label>Afgangslufthavn i EU?
                <select name="departure_airport_in_eu">
                  <option value="yes" <?= $airDepEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $airDepEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Ankomstlufthavn i EU?
                <select name="arrival_airport_in_eu">
                  <option value="yes" <?= $airArrEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $airArrEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Operating carrier er EU-operatør?
                <select name="operating_carrier_is_eu">
                  <option value="yes" <?= $opCarrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $opCarrierEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Marketing carrier er EU-operatør?
                <select name="marketing_carrier_is_eu">
                  <option value="yes" <?= $mkCarrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
                  <option value="no" <?= $mkCarrierEuUpload==='no'?'selected':'' ?>>Nej</option>
                </select>
              </label>
              <label>Flydistance (km)
                <input type="number" name="flight_distance_km" min="0" step="1" value="<?= h($airDistKmUpload) ?>" placeholder="1850" />
              </label>
              <label>Distancekategori
                <select name="air_distance_band">
                  <option value="">Auto / afled fra lufthavne</option>
                  <option value="up_to_1500" <?= $airDistBandUpload==='up_to_1500'?'selected':'' ?>>1500 km eller mindre</option>
                  <option value="intra_eu_over_1500" <?= $airDistBandUpload==='intra_eu_over_1500'?'selected':'' ?>>Inden for EU over 1500 km</option>
                  <option value="other_1500_to_3500" <?= $airDistBandUpload==='other_1500_to_3500'?'selected':'' ?>>Øvrige flyvninger 1500-3500 km</option>
                  <option value="other_over_3500" <?= $airDistBandUpload==='other_over_3500'?'selected':'' ?>>Øvrige flyvninger over 3500 km</option>
                </select>
              </label>
            </div>
            <input type="hidden" name="air_delay_threshold_hours" value="<?= h($airDelayThresholdUpload) ?>" />
            <input type="hidden" name="intra_eu_over_1500" value="<?= h((string)($form['intra_eu_over_1500'] ?? '')) ?>" />
          </details>
        <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
          <label>Forbindelsestype
            <select name="air_connection_type">
              <option value="">Auto / resolver</option>
              <option value="single_flight" <?= $airConnectionTypeUpload==='single_flight'?'selected':'' ?>>Enkelt flight</option>
              <option value="protected_connection" <?= $airConnectionTypeUpload==='protected_connection'?'selected':'' ?>>Protected connection</option>
              <option value="self_transfer" <?= $airConnectionTypeUpload==='self_transfer'?'selected':'' ?>>Self-transfer</option>
            </select>
          </label>
        </div>
      <?php endif; ?>
      <?php if (!empty($claimDirection) || !empty($modeContract) || !empty($ferryContract)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Foreløbig vurdering</strong></div>
          <?php if ($isFerry): ?>
            <div>Scope: <?= !empty($ferryScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?><?= !empty($ferryScope['scope_basis']) ? (' – ' . h((string)$ferryScope['scope_basis'])) : '' ?></div>
          <?php elseif ($isBus): ?>
            <div>Scope: <?= array_key_exists('regulation_applies', $busScope) ? (!empty($busScope['regulation_applies']) ? 'In scope' : 'Out of scope') : 'Auto' ?><?= !empty($busScope['scope_basis']) ? ' - ' . h((string)$busScope['scope_basis']) : '' ?></div>
          <?php elseif ($isAir): ?>
            <div>Scope: <?= array_key_exists('regulation_applies', $airScope) ? (!empty($airScope['regulation_applies']) ? 'In scope' : 'Out of scope') : 'Auto' ?><?= !empty($airScope['scope_basis']) ? ' - ' . h((string)$airScope['scope_basis']) : '' ?></div>
            <div>Forbindelse: <?= h((string)($modeContract['air_connection_type'] ?? 'Auto')) ?></div>
          <?php endif; ?>
          <div>Kontraktstruktur: <?= h((string)($multimodal['contract_meta']['contract_topology'] ?? 'Auto')) ?></div>
          <div>Claim-kanal: <?= h((string)(($isFerry ? ($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? null)) : ($modeContract['primary_claim_party_name'] ?? ($modeContract['primary_claim_party'] ?? null))) ?? 'manual_review')) ?></div>
          <div>Rettighedsmodul: <?= h((string)(($isFerry ? ($ferryContract['rights_module'] ?? 'ferry') : ($modeContract['rights_module'] ?? ($claimDirection['rights_module'] ?? $transportMode))))) ?></div>
          <?php if (!empty($claimDirection['recommended_documents'])): ?>
            <div>Anbefalet dokumentation: <?= h(implode(', ', (array)$claimDirection['recommended_documents'])) ?></div>
          <?php endif; ?>
        </div>
  <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  </fieldset>
  </div><!-- /journeyFields -->

  <?php if (!empty($modeContractCardHtml) && $ticketMode === 'ticket'): ?>
    <?= $modeContractCardHtml ?>
  <?php endif; ?>



  <?php
    // PMR block gating
    $pmrAuto = is_array($meta['_pmr_detection'] ?? null) ? (array)$meta['_pmr_detection'] : [];
    // Two separate flags: one to decide visibility (exists), one to show the Auto badge (actual signal)
    $pmrDetectedExists = !empty($meta['_pmr_detected']) || !empty($pmrAuto);
    $pmrAutoBadgeFlag = false;
    if (!empty($meta['_pmr_detected'])) { $pmrAutoBadgeFlag = true; }
    elseif (!empty($pmrAuto['evidence']) && count((array)$pmrAuto['evidence']) > 0) { $pmrAutoBadgeFlag = true; }
    elseif (!empty($pmrAuto['confidence']) && (float)$pmrAuto['confidence'] > 0.0) { $pmrAutoBadgeFlag = true; }
    // Use Art.9 hooks if present for normalized echo, falling back to meta
    $art9Hooks = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
    $pmrUserVal = strtolower((string)($art9Hooks['pmr_user'] ?? ($meta['pmr_user'] ?? 'unknown')));
    // Accept both 'Ja'/'Nej' and 'yes'/'no' variants
    if ($pmrUserVal==='ja') $pmrUserVal='yes'; if ($pmrUserVal==='nej') $pmrUserVal='no';
    // If still unknown, fall back to AUTO value from detector
    if ($pmrUserVal==='unknown' || $pmrUserVal==='') {
      $autoU = strtolower((string)($meta['_auto']['pmr_user']['value'] ?? ''));
      if ($autoU==='ja') $autoU='yes'; if ($autoU==='nej') $autoU='no';
      if (in_array($autoU, ['yes','no'], true)) { $pmrUserVal = $autoU; }
    }
    // Show card if detection ran (exists) or the user already said Yes
    $showPmr = $pmrDetectedExists || $pmrUserVal==='yes';
    $pmrBookedVal = strtolower((string)($art9Hooks['pmr_booked'] ?? ($meta['pmr_booked'] ?? 'unknown')));
    if ($pmrBookedVal==='ja') $pmrBookedVal='yes'; if ($pmrBookedVal==='nej') $pmrBookedVal='no';
    if ($pmrBookedVal==='unknown' || $pmrBookedVal==='') {
      $autoB = strtolower((string)($meta['_auto']['pmr_booked']['value'] ?? ''));
      if ($autoB==='ja') $autoB='yes'; if ($autoB==='nej') $autoB='no';
      if (in_array($autoB, ['yes','no','refused'], true)) { $pmrBookedVal = $autoB; }
    }
    $pmrDeliveredVal = strtolower((string)($art9Hooks['pmr_delivered_status'] ?? ($meta['pmr_delivered_status'] ?? 'unknown')));
    $pmrPromisedMissingVal = strtolower((string)($art9Hooks['pmr_promised_missing'] ?? ($meta['pmr_promised_missing'] ?? 'unknown')));
    $pmrFacilityDetails = (string)($meta['pmr_facility_details'] ?? '');
  ?>
  <?php if ($showPmr): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="pmrFlowCard">
    <strong>â™¿ PMR/handicap (Art. 18 og 20)</strong>
    <?php if ($pmrAutoBadgeFlag): $confVal = (string)($pmrAuto['confidence'] ?? ''); ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        <?php if ($confVal !== ''): ?>
          <span class="badge" style="margin-left:6px;border:1px solid #d0d7de;background:#f6f8fa;">conf: <?= h($confVal) ?></span>
        <?php endif; ?>
        Vi har fundet PMR/assistance i billetten â€“ du kan have ekstra rettigheder.
      </div>
    <?php endif; ?>
    <div class="small" style="margin-top:8px;">
      <div><strong>Vi har registreret fÃ¸lgende oplysninger om handicap pÃ¥ billetten â€“ ret venligst, hvis noget ikke er korrekt.</strong></div>
      <div><strong>Spm 1.</strong> Har du et handicap eller nedsat mobilitet, som krÃ¦vede assistance?</div>
      <label class="mr8"><input type="radio" name="pmr_user" value="yes" <?= $pmrUserVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_user" value="no" <?= $pmrUserVal==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="pmrQBooked" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 2.</strong> Bestilte du assistance fÃ¸r rejsen?</div>
      <label class="mr8"><input type="radio" name="pmr_booked" value="yes" <?= $pmrBookedVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_booked" value="no" <?= $pmrBookedVal==='no'?'checked':'' ?> /> Nej</label>
      <label class="mr8"><input type="radio" name="pmr_booked" value="refused" <?= $pmrBookedVal==='refused'?'checked':'' ?> /> ForsÃ¸gte men fik afslag</label>
    </div>
    <div id="pmrQDelivered" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes' && $pmrBookedVal!=='no')?'block':'none' ?>;">
      <div><strong>Spm 3.</strong> Blev den bestilte assistance leveret?</div>
      <select name="pmr_delivered_status">
        <option value="">- vÃ¦lg -</option>
        <option value="yes_full" <?= $pmrDeliveredVal==='yes_full'?'selected':'' ?>>Ja, fuldt ud</option>
        <option value="partial" <?= $pmrDeliveredVal==='partial'?'selected':'' ?>>Delvist</option>
        <option value="no" <?= $pmrDeliveredVal==='no'?'selected':'' ?>>Nej</option>
      </select>
    </div>
    <div id="pmrQPromised" class="small" style="margin-top:8px; display:<?= ($pmrUserVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 4.</strong> Manglede der PMR-faciliteter, som var lovet fÃ¸r kÃ¸bet?</div>
      <label class="mr8"><input type="radio" name="pmr_promised_missing" value="yes" <?= $pmrPromisedMissingVal==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="pmr_promised_missing" value="no" <?= $pmrPromisedMissingVal==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="pmrQDetails" class="small" style="margin-top:8px; display:<?= ($pmrPromisedMissingVal==='yes')?'block':'none' ?>;">
      <div><strong>Spm 5.</strong> Hvilke faciliteter manglede? (rampe, skiltning, lift â€¦)</div>
      <textarea name="pmr_facility_details" rows="2" style="width:100%;" placeholder="Beskriv kort"><?= h($pmrFacilityDetails) ?></textarea>
    </div>
  </div>
  <?php endif; ?>

  <?php
    // Bike flow visibility gating: show the block and preselect if OCR detected a bike on ticket
    $bikeAuto = (array)($meta['_bike_detection'] ?? []);
  // Normaliser cykel auto-detektion sÃ¥ bÃ¥de positiv og negativ auto-default kan forfylde radio-valg
  $bikeBookedAutoRaw = (string)($meta['_auto']['bike_booked']['value'] ?? ($meta['bike_booked'] ?? ''));
  $bikeBookedAutoNorm = strtolower($bikeBookedAutoRaw);
  if ($bikeBookedAutoNorm === 'ja') $bikeBookedAutoNorm = 'yes';
  if ($bikeBookedAutoNorm === 'nej') $bikeBookedAutoNorm = 'no';
  // Brugerens eget svar har altid forrang; ellers bruger vi auto (ja eller nej). Tom betyder ingen forvalg.
  $bikeWas = strtolower((string)($meta['bike_was_present'] ?? ''));
    $bikeCause = strtolower((string)($meta['bike_caused_issue'] ?? ''));
    $bikeResMade = strtolower((string)($meta['bike_reservation_made'] ?? ''));
    $bikeResReq = strtolower((string)($meta['bike_reservation_required'] ?? ''));
    $bikeDenied = strtolower((string)($meta['bike_denied_boarding'] ?? ''));
    $bikeReasonProv = strtolower((string)($meta['bike_refusal_reason_provided'] ?? ''));
    $bikeReasonType = strtolower((string)($meta['bike_refusal_reason_type'] ?? ''));
  ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;" id="bikeFlowCard">
    <strong>ðŸš² Cykel pÃ¥ rejsen (Artikel 6)</strong>
    <?php if (!empty($bikeAuto)): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har registreret fÃ¸lgende oplysninger om cyklen pÃ¥ billetten â€“ ret venligst, hvis noget ikke stemmer.
        <?php if (!empty($bikeAuto['count'])): ?><span class="ml8">(antal: <?= (int)$bikeAuto['count'] ?>)</span><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="small" style="margin-top:8px;">
      <div><strong>Spm 1.</strong> Havde du en cykel med pÃ¥ rejsen?</div>
  <?php $w = $bikeWas !== '' ? $bikeWas : ($bikeBookedAutoNorm==='yes' ? 'yes' : ($bikeBookedAutoNorm==='no' ? 'no' : 'no')); ?>
      <label class="mr8"><input type="radio" name="bike_was_present" value="yes" <?= $w==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="bike_was_present" value="no" <?= $w==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div class="small" id="bikeQ2" style="margin-top:8px; display:<?= ($w==='yes')?'block':'none' ?>;">
      <div><strong>Spm 2.</strong> Er det cyklen eller hÃ¥ndteringen af cyklen, der har forsinket dig?</div>
      <label class="mr8"><input type="radio" name="bike_caused_issue" value="yes" <?= $bikeCause==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="bike_caused_issue" value="no" <?= $bikeCause==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="bikeArticle6" style="margin-top:8px; display:<?= ($w==='yes' && $bikeCause==='yes')?'block':'none' ?>;">
      <div class="small"><strong>Spm 3.</strong> Havde du reserveret plads til en cykel?</div>
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="bike_reservation_made" value="yes" <?= $bikeResMade==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_reservation_made" value="no" <?= $bikeResMade==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ3B" class="small" style="margin-top:8px; display:<?= ($bikeResMade==='no')?'block':'none' ?>;">
        <div><strong>Spm 3B.</strong> Var det et tog, hvor der ikke krÃ¦vedes cykelreservation?</div>
        <label class="mr8"><input type="radio" name="bike_reservation_required" value="yes" <?= $bikeResReq==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_reservation_required" value="no" <?= $bikeResReq==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ4" class="small" style="margin-top:8px;">
        <div><strong>Spm 4.</strong> Blev du nÃ¦gtet at tage cyklen med?</div>
        <label class="mr8"><input type="radio" name="bike_denied_boarding" value="yes" <?= $bikeDenied==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_denied_boarding" value="no" <?= $bikeDenied==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ5" class="small" style="margin-top:8px; display:<?= ($bikeDenied==='yes')?'block':'none' ?>;">
        <div><strong>Spm 5.</strong> Blev du informeret om, hvorfor du ikke mÃ¥tte tage cyklen med?</div>
        <label class="mr8"><input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $bikeReasonProv==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="bike_refusal_reason_provided" value="no" <?= $bikeReasonProv==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div id="bikeQ6" class="small" style="margin-top:8px; display:<?= ($bikeDenied==='yes' && $bikeReasonProv==='yes')?'block':'none' ?>;">
        <div><strong>Spm 6.</strong> Hvad var begrundelsen for afvisningen?</div>
        <?php $opt = $bikeReasonType; ?>
        <select name="bike_refusal_reason_type">
          <option value="">- vÃ¦lg -</option>
          <option value="capacity" <?= $opt==='capacity'?'selected':'' ?>>Pladsmangel / Spidsbelastning</option>
          <option value="equipment" <?= $opt==='equipment'?'selected':'' ?>>Teknisk udstyr tillader det ikke</option>
          <option value="weight_dim" <?= $opt==='weight_dim'?'selected':'' ?>>VÃ¦gt eller dimensioner</option>
          <option value="other" <?= $opt==='other'?'selected':'' ?>>Andet</option>
          <option value="unknown" <?= $opt==='unknown'?'selected':'' ?>>Ved ikke</option>
        </select>
      </div>
    </div>
  </div>

  <?php
    // 3) Billetpriser og fleksibilitet (Art. 9) â€“ show simple Qs with auto-prefill
    $fftVal = (string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? ''));
    $tsVal = (string)($meta['train_specificity'] ?? ($meta['_auto']['train_specificity']['value'] ?? 'unknown'));
    $hasAutoPricing = !empty($meta['_auto']['fare_flex_type']['value'] ?? null) || !empty($meta['_auto']['train_specificity']['value'] ?? null);
  ?>
  <?php if ($showArt9_1): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; display:none;" id="pricingBlock" data-art="9(1)">
    <strong>ðŸ’¶ 3) Billetpriser og fleksibilitet (Art. 9 stk. 1)</strong>
    <?php if ($hasAutoPricing): ?>
      <div class="small" style="margin-top:6px;">
        <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
        Vi har registreret fÃ¸lgende oplysninger om kÃ¸bstype og togbinding â€“ ret venligst, hvis noget ikke stemmer.
      </div>
    <?php endif; ?>
    <div class="mt8 small">1. KÃ¸bstype (fleksibilitet)</div>
    <?php $curFft = strtolower($fftVal); ?>
    <select name="fare_flex_type">
      <option value="" <?= $curFft===''?'selected':'' ?>>- vÃ¦lg -</option>
      <option value="nonflex" <?= $curFft==='nonflex'?'selected':'' ?>>Standard/Non-flex</option>
      <option value="semiflex" <?= $curFft==='semiflex'?'selected':'' ?>>Semi-flex</option>
      <option value="flex" <?= $curFft==='flex'?'selected':'' ?>>Flex</option>
      <option value="pass" <?= $curFft==='pass'?'selected':'' ?>>Abonnement/Periodekort</option>
      <option value="other" <?= $curFft==='other'?'selected':'' ?>>Andet</option>
    </select>

    <div id="pricingQ2" class="mt8" style="display:none;">
      <div class="small">2. GÃ¦lder billetten kun for specifikt tog?</div>
      <?php $curTs = strtolower($tsVal ?: 'unknown'); ?>
      <label class="small"><input type="radio" name="train_specificity" value="specific" <?= $curTs==='specific'?'checked':'' ?> /> Kun specifikt tog</label>
      <label class="small ml8"><input type="radio" name="train_specificity" value="any_day" <?= $curTs==='any_day'?'checked':'' ?> /> VilkÃ¥rlig afgang samme dag</label>
    </div>
  </div>
  <?php else: ?>
    <div class="small" style="margin-top:12px; background:#f6f7f9; border:1px solid #e2e6ea; padding:6px; border-radius:6px;">Billetpriser og fleksibilitet (Art. 9 stk. 1) er undtaget for denne rejse og vises ikke.</div>
  <?php endif; ?>

  <?php
    // 6) Klasse og reservationer samles i per-leg tabel
    $classAuto = (array)($meta['_class_detection'] ?? []);
    $journeyRows = $journeyRows ?? [];
        $classOptions = [
      'sleeper' => 'Sovevogn',
      'couchette' => 'Liggevogn',
      '1st' => '1. klasse',
      '2nd' => '2. klasse',
    ];
    $reservationOptions = [
      'reserved' => 'Reserveret plads',
      'free_seat' => 'Ingen reservation',
      'missing' => 'Reservation mangler',
    ];
    $journeyRowsDowng = $journeyRows;
    if (empty($journeyRowsDowng)) {
      try {
        $segSrc = (array)($meta['_segments_auto'] ?? []);
        $jr = [];
        foreach ($segSrc as $s) {
          $from = trim((string)($s['from'] ?? ''));
          $to = trim((string)($s['to'] ?? ''));
          $jr[] = [
            'leg' => $from . ' -> ' . $to,
            'dep' => (string)($s['schedDep'] ?? ''),
            'arr' => (string)($s['schedArr'] ?? ''),
            'train' => (string)($s['train'] ?? ($s['trainNo'] ?? '')),
            'change' => (string)($s['change'] ?? ''),
          ];
        }
        if (!empty($jr)) { $journeyRowsDowng = $jr; }
      } catch (\Throwable $e) { /* ignore */ }
    }
  ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; display:none;" id="classReservationBlock" data-art="9(1)">
    <strong>6) Klasse og reserverede faciliteter (Art. 9 stk. 1)</strong>
    <div class="small" style="margin-top:6px;">
      Vi har aflaest klasse/reservation pr. straekning. Ret venligst hvis noget ikke stemmer.
    </div>
    <?php if (!empty($classAuto)): ?>
      <div class="small muted" style="margin-top:6px;">Auto: klasse <?= h($classAuto["fare_class_purchased"] ?? "") ?> / reservation <?= h($classAuto["berth_seat_type"] ?? "") ?> (kilde: detection)</div>
    <?php endif; ?>
    <?php if (!empty($journeyRowsDowng)): ?>
      <div id="perLegDowngrade" style="display:none;">
        <?= $this->element('downgrade_table', compact('journeyRowsDowng','classOptions','reservationOptions','form','meta')) ?>
      </div>
    <?php endif; ?>
  </div>
  <?php
    // 7) Afbrydelser/forsinkelser fÃ¸r kÃ¸b (Art. 9(1))
    // Q1 vises altid; Q2+Q3 vises nÃ¥r Q1=Ja. Q3 kun hvis Art. 10 gÃ¦lder (realtime information).
    $art10Applies = $profile['articles']['art10'] ?? true;
    $pid = (string)($form['preinformed_disruption'] ?? ($art9['hooks']['preinformed_disruption'] ?? ''));
    $pic = (string)($form['preinfo_channel'] ?? ($art9['hooks']['preinfo_channel'] ?? ''));
    $ris = (string)($form['realtime_info_seen'] ?? ($art9['hooks']['realtime_info_seen'] ?? ''));
  ?>
  <?php if ($showArt9_1): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; display:none;" id="disruptionBlock" data-art="9(1)">
    <strong>â±ï¸ 7) Afbrydelser/forsinkelser â€“ oplyst fÃ¸r kÃ¸b (Art. 9 stk. 1)</strong>
    <div class="small" style="margin-top:6px;">1. Var der meddelt afbrydelse/forsinkelse fÃ¸r dit kÃ¸b?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <div id="disQ2" class="small" style="margin-top:8px; display:<?= $pid==='yes'?'block':'none' ?>;">
      <div>2. Hvis ja: Hvor blev det vist?</div>
      <select name="preinfo_channel">
        <option value="" <?= $pic===''?'selected':'' ?>>- vÃ¦lg -</option>
        <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
        <option value="operator_site_app" <?= $pic==='operator_site_app'?'selected':'' ?>>OperatÃ¸r-site/app</option>
        <option value="ticket_overview" <?= $pic==='ticket_overview'?'selected':'' ?>>Billetoverblik</option>
        <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>
    <?php if ($art10Applies): ?>
    <div id="disQ3" class="small" style="margin-top:8px; display:<?= $pid==='yes'?'block':'none' ?>;">
      <div>3. SÃ¥ du realtime-opdateringer under rejsen?</div>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="app" <?= $ris==='app'?'checked':'' ?> /> Ja, i app</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="on_train" <?= $ris==='on_train'?'checked':'' ?> /> Ja, i toget</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="station" <?= $ris==='station'?'checked':'' ?> /> Ja, pÃ¥ station</label>
      <label class="mr8"><input type="radio" name="realtime_info_seen" value="no" <?= $ris==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <?php endif; ?>
    <div id="disruptionReqError" class="small" style="margin-top:8px; color:#b33; display:none;">Udfyld venligst punkt 7: marker om der var oplyst forsinkelse fÃ¸r kÃ¸b<?= $art10Applies ? ' (og besvar opfÃ¸lgning)' : '' ?>.</div>
  </div>
  <?php else: ?>
    <div class="small" style="margin-top:12px; background:#f6f7f9; border:1px solid #e2e6ea; padding:6px; border-radius:6px;">Oplysningspligt fÃ¸r kÃ¸b (Art. 9 stk. 1) er undtaget â€“ vi behÃ¸ver ikke disse svar.</div>
  <?php endif; ?>

  <?php
    // Minimal Art. 12 questions inline in TRIN 3 (PGR only)
    // Show only if evaluator suggests missing basics or if values are unknown
    $a12hooks = (array)($art12['hooks'] ?? []);
    $a12missing = (array)($art12['missing'] ?? []);
    $norm = function($v){ $s=strtolower((string)$v); if(in_array($s,['ja','yes','y','1','true'],true)) return 'yes'; if(in_array($s,['nej','no','n','0','false'],true)) return 'no'; if($s===''||$s==='-'||$s==='unknown'||$s==='ved ikke') return 'unknown'; return $s; };
    $scnVal = $norm($meta['separate_contract_notice'] ?? ($a12hooks['separate_contract_notice'] ?? 'unknown'));
    $ttdVal = $norm($meta['through_ticket_disclosure'] ?? ($a12hooks['through_ticket_disclosure'] ?? 'unknown'));
    $pnrScopeVal = $norm($meta['shared_pnr_scope'] ?? 'unknown');
    // Seller channel inference from meta hooks
    $sellerInf = 'unknown';
    $sto = $norm($meta['seller_type_operator'] ?? '');
    $sta = $norm($meta['seller_type_agency'] ?? '');
    if ($sto==='yes') $sellerInf = 'operator'; elseif ($sta==='yes') $sellerInf = 'retailer';
    // Same transaction inference when multiple PNRs
    $stOp = $norm($meta['single_txn_operator'] ?? '');
    $stRt = $norm($meta['single_txn_retailer'] ?? '');
    $sameTxnInf = ($stOp==='yes'||$stRt==='yes') ? 'yes' : (($stOp==='no'&&$stRt==='no') ? 'no' : 'unknown');
    // Gate visibility: show if evaluator is missing any of these, or if values are unknown
    $needA12 = in_array('separate_contract_notice', $a12missing, true) || in_array('through_ticket_disclosure', $a12missing, true)
      || $scnVal==='unknown' || $ttdVal==='unknown' || $pnrScopeVal==='unknown' || $sellerInf==='unknown' || ($ticketMode === 'ticketless');
    $a12Stop = (string)($art12flow['stage'] ?? '') === 'STOP';
    $a12TicketScope = (string)($art12flow['ticket_scope'] ?? '');
    $a12StopLabel = match ($a12TicketScope) {
      'through' => 'Gennemgående billet',
      'separate' => 'Særskilte kontrakter',
      default => 'Kontraktklassifikation afgjort',
    };
    $a12Responsibility = (string)($art12flow['responsibility'] ?? '');
    // Also compute PNR count + shared scope hint for same-transaction prompt
    $pnrCountInline = 0; try {
      $pnrSet = [];
      $br = (string)($journey['bookingRef'] ?? ''); if ($br!=='') { $pnrSet[$br]=true; }
      foreach ((array)($groupedTickets ?? []) as $g) { $p=(string)($g['pnr'] ?? ''); if ($p!=='') { $pnrSet[$p]=true; } }
      $pnrCountInline = count($pnrSet);
    } catch (\Throwable $e) { $pnrCountInline = 0; }
  ?>
  <?php if (!empty($meta['_passengers_auto'])): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>Fundne passagerer på billetten</strong>
    <div class="small" style="margin-top:6px;">Redigér navne og markér hvem der klager:</div>
    <div class="small" style="margin-top:6px;">
      <?php $paxList = (array)$meta['_passengers_auto']; ?>
      <?php foreach ($paxList as $i => $p): $nameVal = (string)($p['name'] ?? ''); $age = (string)($p['age_category'] ?? 'unknown'); $isC = !empty($p['is_claimant']); ?>
        <div style="margin-top:6px;">
          <label>Navn
            <input type="text" name="passenger[<?= (int)$i ?>][name]" value="<?= h($nameVal) ?>" placeholder="Passager #<?= (int)($i+1) ?>" />
          </label>
          <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= h(ucfirst($age)) ?></span>
          <label class="ml8"><input type="checkbox" name="passenger[<?= (int)$i ?>][is_claimant]" value="1" <?= $isC?'checked':'' ?> /> Klager</label>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="small" style="margin-top:8px;">
      <label><input type="checkbox" name="claimant_is_legal_representative" value="1" <?= !empty($meta['claimant_is_legal_representative']) ? 'checked' : '' ?> /> Jeg er juridisk værge/ansvarlig for andre på billetten</label>
    </div>
  </div>
  <?php endif; ?>

  <?php
    // Show SE regional distance toggle to drive exemptions under 150 km
    $scopeNow = (string)($profile['scope'] ?? '');
    $countryNow = strtoupper((string)($journey['country']['value'] ?? ($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));
    $productNow = strtoupper((string)($journey['trainCategory']['value'] ?? ($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? ''))));
    // Avoid showing SE regional toggle for known long-distance brands (InterCity/Snabbtag etc.)
    $isSeLongDistanceBrand = preg_match('/\\b(TGV|INTERCITY|IC|SNABBT|SJ)\\b/u', $productNow) === 1;
    $showSE150 = ($countryNow === 'SE' && $scopeNow === 'regional' && !$isSeLongDistanceBrand);
  ?>
  <?php if ($showSE150): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>SE-specifik undtagelse</strong>
    <div class="small" style="margin-top:6px;">GÃ¦lder kun for regionale rejser under 150 km.</div>
    <label class="small" style="margin-top:6px; display:inline-block;">
      <input type="checkbox" name="se_under_150km" value="1" <?= !empty($journey['se_under_150km']) ? 'checked' : '' ?> /> StrÃ¦kningen er under 150 km
    </label>
  </div>
  <?php endif; ?>

  

  <div class="actions-row" style="display:flex; gap:8px; align-items:center; margin-top:12px;">
    <a href="<?= $this->Url->build(['action' => 'start']) ?>" class="button" style="background:#eee; color:#333;">Tilbage</a>
    <button type="submit" name="continue" value="1" class="button">FortsÃ¦t</button>
    <!-- Removed duplicate 'Kendt fÃ¸r kÃ¸b?' checkbox; use Section 7 radios above (preinformed_disruption) -->
  </div>
  <!-- Afslut samlet wrapper for centrering -->
</div>
</fieldset>
<?= $this->Form->end() ?>

<?php if ($this->getRequest()->getQuery('debug')): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #eef; background:#f9fbff; border-radius:6px;">
    <strong>Debug</strong>
    <div class="small" style="margin-top:6px;">EU only (anbefalet): <code><?= h((string)($euOnlySuggested ?? 'unknown')) ?></code></div>
    <?php if (!empty($euOnlyReason)): ?><div class="small" style="margin-top:6px;">Begrundelse: <?= h($euOnlyReason) ?></div><?php endif; ?>

    <?php
      $segAutoDbg = (array)($meta['_segments_auto'] ?? []);
      $segLlmSuggestDbg = (array)($meta['_segments_llm_suggest'] ?? []);
      $segDbg = (array)($meta['_segments_debug'] ?? []);
      $logsDbg = (array)($meta['logs'] ?? []);
      $ocrText = (string)($meta['_ocr_text'] ?? '');
    ?>
    <div class="small" style="margin-top:10px;"><strong>ðŸ› ï¸ Parser/segments</strong></div>
    <div class="small" style="margin-top:4px;">Segments auto: <code><?= (int)count($segAutoDbg) ?></code></div>
    <?php if (!empty($segAutoDbg)): ?>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach (array_slice($segAutoDbg, 0, 5) as $s): $from=(string)($s['from']??''); $to=(string)($s['to']??''); $d=(string)($s['schedDep']??''); $a=(string)($s['schedArr']??''); ?>
          <li><?= h(trim($from . ' ? ' . $to)) ?> <?= h(trim($d . ($a!==''?'â†’'.$a:''))) ?></li>
        <?php endforeach; ?>
        <?php if (count($segAutoDbg) > 5): ?><li>â€¦</li><?php endif; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($segLlmSuggestDbg)): ?>
      <div class="small" style="margin-top:8px;">LLM forslag til segments: <code><?= (int)count($segLlmSuggestDbg) ?></code></div>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach (array_slice($segLlmSuggestDbg, 0, 5) as $s): $from=(string)($s['from']??''); $to=(string)($s['to']??''); $d=(string)($s['schedDep']??''); $a=(string)($s['schedArr']??''); ?>
          <li><?= h(trim($from . ' ? ' . $to)) ?> <?= h(trim($d . ($a!==''?'â†’'.$a:''))) ?></li>
        <?php endforeach; ?>
        <?php if (count($segLlmSuggestDbg) > 5): ?><li>â€¦</li><?php endif; ?>
      </ul>
    <?php endif; ?>

    <?php if (!empty($logsDbg)): ?>
      <div class="small" style="margin-top:8px;"><strong>Logs</strong></div>
      <ul class="small" style="margin-top:4px; padding-left:16px;">
        <?php foreach ($logsDbg as $ln): ?>
          <li><?= h((string)$ln) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($ocrText !== ''): ?>
      <details style="margin-top:8px;">
        <summary class="small"><strong>OCR (fÃ¸rste 600 tegn)</strong></summary>
        <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(mb_substr($ocrText, 0, 600, 'UTF-8')) ?></pre>
      </details>
    <?php endif; ?>

    <div class="small" style="margin-top:8px;"><strong>TRIN 2 render-state</strong></div>
    <div class="small" style="margin-top:4px;">
      ticket_mode=<code><?= h($ticketMode) ?></code>,
      transport_mode=<code><?= h($transportMode) ?></code>,
      transport_mode_source=<code><?= h($transportModeSource) ?></code>,
      has_tickets=<code><?= $hasTickets ? '1' : '0' ?></code>,
      has_prefill=<code><?= $hasJourneyFieldPrefill ? '1' : '0' ?></code>,
      pinned_upload=<code><?= $pinJourneyFieldsForUploadedMode ? '1' : '0' ?></code>,
      force_open=<code><?= $forceJourneyFieldsOpen ? '1' : '0' ?></code>,
      default_open=<code><?= $journeyFieldsOpenDefault ? '1' : '0' ?></code>
    </div>

    <?php if (!empty($segDbg)): ?>
      <details style="margin-top:8px;">
        <summary class="small"><strong>Segments debug (detaljer)</strong></summary>
        <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($segDbg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      </details>
    <?php endif; ?>
    <div class="small" style="margin-top:6px;">Art. 12</div>
    <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($art12, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <div class="small" style="margin-top:6px;">Art. 9 (on request)</div>
    <?php if (!empty($compute['art9OptIn'])): ?>
      <pre style="white-space:pre-wrap; background:#f3f6ff; padding:6px; border-radius:6px; max-height:240px; overflow:auto;"><?= h(json_encode($art9, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php else: ?>
      <p class="small">Art. 9 vises kun hvis du markerer boksen.</p>
    <?php endif; ?>
    <div class="small" style="margin-top:6px;">Refusion: <?= !empty($refund['eligible']) ? 'Mulig' : 'Ikke mulig' ?>, Assistance: <?= h(implode(', ', (array)($refusion['options'] ?? []))) ?></div>
  </div>
<?php endif; ?>

  <div class="card hooks-card">
  <div id="hooksPanel">
    <div class="small muted">Sidepanelet indlaeses kun manuelt i Trin 2 for at undgaa lange baggrunds-requests efter upload og fjern billet.</div>
  </div>
  <div class="small muted" style="margin-top:6px;">Rejsefelter og upload opdateres stadig normalt. Sidepanelet kan indlaeses efter behov.</div>
  <div class="small" style="margin-top:6px; display:flex; gap:8px; align-items:center;">
    <button type="button" id="loadHooksBtn" class="button button-secondary">Indlaes sidepanel</button>
    <a href="<?= $this->Url->build($this->getRequest()->getPath() . '?debug=1') ?>">Vis mere debug</a>
    <label style="margin-left:auto;"><input type="checkbox" id="toggleDebugChk" <?= $this->getRequest()->getQuery('debug') ? 'checked' : '' ?> /> Debug</label>
  </div>
  </div>

<?php
// If Art. 12 does not apply (no through-ticket), display per-contract table (TRIN 3 grouping)
$a12Applies = isset($art12['art12_applies']) ? (bool)$art12['art12_applies'] : null;
if ($a12Applies === false && !empty($contractsView)) {
  echo $this->element('per_contract_table', compact('contractsView'));
}
?>

<script>
(function(){
  const form = document.getElementById('entitlementsForm');
  let entitlementsAutoSubmitReady = false;
  window.setTimeout(() => { entitlementsAutoSubmitReady = true; }, 350);
  const journeyToggleSeed = document.getElementById('toggleJourneyFields');
  const stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  const transportNodesSearchUrl = <?= json_encode((string)$transportNodesSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  const productsByOperator = <?= json_encode($productsByOperator, JSON_UNESCAPED_UNICODE) ?>;
  const operatorToCountry = <?= json_encode($operatorToCountry, JSON_UNESCAPED_UNICODE) ?>;
  const countryToCurrency = <?= json_encode($countryToCurrency, JSON_UNESCAPED_UNICODE) ?>;
  const transportOperatorEntries = <?= json_encode($transportOperatorEntries, JSON_UNESCAPED_UNICODE) ?>;
  const initialTransportMode = <?= json_encode((string)$transportMode, JSON_UNESCAPED_SLASHES) ?>;

  function submittedTransportMode() {
    if (!form) return String(initialTransportMode || '');
    const hidden = form.querySelector('#transportModeHidden');
    if (hidden && !hidden.disabled) {
      const val = String(hidden.value || '').trim();
      if (val !== '') return val;
    }
    const radios = Array.from(form.querySelectorAll('input[type="radio"][name="transport_mode"]'));
    const active = radios.find((node) => node.checked && !node.disabled && node.offsetParent !== null);
    if (active) return String(active.value || '').trim();
    const checked = radios.find((node) => node.checked);
    if (checked) return String(checked.value || '').trim();
    return String(initialTransportMode || '').trim();
  }

  function resolvedTransportMode() {
    const submitted = submittedTransportMode();
    if (submitted) return submitted;
    const toggleMode = journeyToggleSeed ? String(journeyToggleSeed.dataset.transportMode || '').trim() : '';
    if (toggleMode) return toggleMode;
    return String(initialTransportMode || '').trim();
  }

  function currentTransportMode() {
    return resolvedTransportMode();
  }

  function setFieldValue(name, value) {
    if (!form) return;
    form.querySelectorAll('[name="' + name + '"]').forEach((node) => {
      if (!node) return;
      node.value = value;
    });
  }

  function getFieldValue(name) {
    if (!form) return '';
    const enabled = Array.from(form.querySelectorAll('[name="' + name + '"]')).find((node) => !node.disabled);
    if (enabled) return String(enabled.value || '');
    const any = form.querySelector('[name="' + name + '"]');
    return any ? String(any.value || '') : '';
  }

  function parseMaybeNumber(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }

  function normalizeLookupText(value) {
    const raw = String(value || '').trim().toLowerCase();
    if (!raw) return '';
    return raw
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function findOperatorEntry(mode, text) {
    const normalized = normalizeLookupText(text);
    if (!normalized) return null;
    const entries = transportOperatorEntries && transportOperatorEntries[mode] ? transportOperatorEntries[mode] : [];
    for (const entry of entries) {
      const names = [entry.name].concat(Array.isArray(entry.aliases) ? entry.aliases : []);
      for (const name of names) {
        if (normalizeLookupText(name) === normalized) {
          return entry;
        }
      }
    }
    return null;
  }

  function haversineMeters(lat1, lon1, lat2, lon2) {
    const toRad = (deg) => deg * Math.PI / 180;
    const earth = 6371000;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2
      + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return Math.round(earth * c);
  }

  function genericLookupMeta(name) {
    const prefixMap = {
      dep_station: 'dep_station_lookup_',
      arr_station: 'arr_station_lookup_',
      dep_terminal: 'dep_terminal_lookup_',
      arr_terminal: 'arr_terminal_lookup_'
    };
    const prefix = prefixMap[name];
    if (!prefix) {
      return {
        id: null, code: null, mode: null, country: null, inEu: null,
        nodeType: null, parent: null, source: null, lat: null, lon: null
      };
    }
    return {
      id: form.querySelector('input[name="' + prefix + 'id"]'),
      code: form.querySelector('input[name="' + prefix + 'code"]'),
      mode: form.querySelector('input[name="' + prefix + 'mode"]'),
      country: form.querySelector('input[name="' + prefix + 'country"]'),
      inEu: form.querySelector('input[name="' + prefix + 'in_eu"]'),
      nodeType: form.querySelector('input[name="' + prefix + 'node_type"]'),
      parent: form.querySelector('input[name="' + prefix + 'parent"]'),
      source: form.querySelector('input[name="' + prefix + 'source"]'),
      lat: form.querySelector('input[name="' + prefix + 'lat"]'),
      lon: form.querySelector('input[name="' + prefix + 'lon"]')
    };
  }

  function clearGenericLookupMeta(name) {
    const meta = genericLookupMeta(name);
    Object.values(meta).forEach((node) => {
      if (node) node.value = '';
    });
  }

  function setGenericLookupMeta(name, node) {
    const meta = genericLookupMeta(name);
    if (!node) {
      clearGenericLookupMeta(name);
      return;
    }
    if (meta.id) meta.id.value = node.id ? String(node.id) : '';
    if (meta.code) meta.code.value = node.code ? String(node.code) : '';
    if (meta.mode) meta.mode.value = node.mode ? String(node.mode) : '';
    if (meta.country) meta.country.value = node.country ? String(node.country) : '';
    if (meta.inEu) meta.inEu.value = node.in_eu === true ? 'yes' : (node.in_eu === false ? 'no' : '');
    if (meta.nodeType) meta.nodeType.value = node.node_type ? String(node.node_type) : '';
    if (meta.parent) meta.parent.value = node.parent_name ? String(node.parent_name) : '';
    if (meta.source) meta.source.value = node.source ? String(node.source) : '';
    if (meta.lat) meta.lat.value = node.lat !== undefined && node.lat !== null ? String(node.lat) : '';
    if (meta.lon) meta.lon.value = node.lon !== undefined && node.lon !== null ? String(node.lon) : '';
  }

  function clearDerivedScopeForNodeInput(name) {
    const mode = currentTransportMode();
    if (mode === 'ferry') {
      if (name === 'dep_station') {
        setFieldValue('departure_port_in_eu', '');
        setFieldValue('departure_from_terminal', '');
      }
      if (name === 'arr_station') {
        setFieldValue('arrival_port_in_eu', '');
      }
      if (name === 'dep_station' || name === 'arr_station' || name === 'dep_terminal' || name === 'arr_terminal') {
        setFieldValue('route_distance_meters', '');
      }
      updateFerryScopeUi();
      return;
    }

    if (mode === 'bus') {
      if (name === 'dep_station') {
        setFieldValue('boarding_in_eu', '');
        setFieldValue('departure_from_terminal', '');
      }
      if (name === 'arr_station') {
        setFieldValue('alighting_in_eu', '');
      }
      if (name === 'dep_station' || name === 'arr_station') {
        setFieldValue('scheduled_distance_km', '');
      }
      updateBusScopeUi();
      return;
    }

    if (mode === 'air') {
      if (name === 'dep_station') {
        setFieldValue('departure_airport_in_eu', '');
      }
      if (name === 'arr_station') {
        setFieldValue('arrival_airport_in_eu', '');
      }
      if (name === 'dep_station' || name === 'arr_station') {
        setFieldValue('flight_distance_km', '');
        setFieldValue('air_distance_band', '');
        setFieldValue('air_delay_threshold_hours', '');
        setFieldValue('intra_eu_over_1500', '');
      }
      updateAirScopeUi();
    }
  }

  function deriveScopeFromNodes() {
    const mode = currentTransportMode();
    const depMeta = genericLookupMeta('dep_station');
    const arrMeta = genericLookupMeta('arr_station');
    const depTerminalMeta = genericLookupMeta('dep_terminal');
    const arrTerminalMeta = genericLookupMeta('arr_terminal');
    const depInEu = depMeta.inEu && depMeta.inEu.value ? depMeta.inEu.value : '';
    const arrInEu = arrMeta.inEu && arrMeta.inEu.value ? arrMeta.inEu.value : '';
    const depType = depMeta.nodeType && depMeta.nodeType.value ? depMeta.nodeType.value.toLowerCase() : '';
    const depTerminalType = depTerminalMeta.nodeType && depTerminalMeta.nodeType.value ? depTerminalMeta.nodeType.value.toLowerCase() : '';
    const depLat = parseMaybeNumber(depMeta.lat && depMeta.lat.value);
    const depLon = parseMaybeNumber(depMeta.lon && depMeta.lon.value);
    const arrLat = parseMaybeNumber(arrMeta.lat && arrMeta.lat.value);
    const arrLon = parseMaybeNumber(arrMeta.lon && arrMeta.lon.value);
    const depTerminalLat = parseMaybeNumber(depTerminalMeta.lat && depTerminalMeta.lat.value);
    const depTerminalLon = parseMaybeNumber(depTerminalMeta.lon && depTerminalMeta.lon.value);
    const arrTerminalLat = parseMaybeNumber(arrTerminalMeta.lat && arrTerminalMeta.lat.value);
    const arrTerminalLon = parseMaybeNumber(arrTerminalMeta.lon && arrTerminalMeta.lon.value);
    const routeDepLat = depLat !== null ? depLat : depTerminalLat;
    const routeDepLon = depLon !== null ? depLon : depTerminalLon;
    const routeArrLat = arrLat !== null ? arrLat : arrTerminalLat;
    const routeArrLon = arrLon !== null ? arrLon : arrTerminalLon;

    if (mode === 'ferry') {
      setFieldValue('departure_port_in_eu', depInEu || '');
      setFieldValue('arrival_port_in_eu', arrInEu || '');
      if (depTerminalType) {
        setFieldValue('departure_from_terminal', (depTerminalType === 'ferry_terminal' || depTerminalType === 'terminal') ? 'yes' : 'no');
      } else if (depType) {
        setFieldValue('departure_from_terminal', (depType === 'ferry_terminal' || depType === 'terminal') ? 'yes' : 'no');
      } else {
        setFieldValue('departure_from_terminal', '');
      }
      if (routeDepLat !== null && routeDepLon !== null && routeArrLat !== null && routeArrLon !== null) {
        setFieldValue('route_distance_meters', String(haversineMeters(routeDepLat, routeDepLon, routeArrLat, routeArrLon)));
      } else {
        setFieldValue('route_distance_meters', '');
      }
      deriveScopeFromOperator();
      updateFerryScopeUi();
      return;
    }

    if (mode === 'bus') {
      setFieldValue('boarding_in_eu', depInEu || '');
      setFieldValue('alighting_in_eu', arrInEu || '');
      if (depType) {
        setFieldValue('departure_from_terminal', (depType === 'terminal' || depType === 'bus_terminal') ? 'yes' : 'no');
      } else {
        setFieldValue('departure_from_terminal', '');
      }
      if (depLat !== null && depLon !== null && arrLat !== null && arrLon !== null) {
        const km = Math.max(1, Math.round(haversineMeters(depLat, depLon, arrLat, arrLon) / 1000));
        setFieldValue('scheduled_distance_km', String(km));
      } else {
        setFieldValue('scheduled_distance_km', '');
      }
      deriveScopeFromOperator();
      updateBusScopeUi();
      return;
    }

    if (mode === 'air') {
      setFieldValue('departure_airport_in_eu', depInEu || '');
      setFieldValue('arrival_airport_in_eu', arrInEu || '');
      if (depLat !== null && depLon !== null && arrLat !== null && arrLon !== null) {
        const km = Math.max(1, Math.round(haversineMeters(depLat, depLon, arrLat, arrLon) / 1000));
        setFieldValue('flight_distance_km', String(km));
      } else {
        setFieldValue('flight_distance_km', '');
      }
      deriveScopeFromOperator();
      updateAirScopeUi();
    }
  }

  function updateFerryScopeUi() {
    const mode = currentTransportMode();
    const hasValue = (name) => String(getFieldValue(name) || '').trim() !== '';
    const isAutoReady = mode === 'ferry' && (
      hasValue('departure_port_in_eu') ||
      hasValue('arrival_port_in_eu') ||
      hasValue('carrier_is_eu') ||
      hasValue('departure_from_terminal') ||
      hasValue('route_distance_meters')
    );
    const isScopeComplete = mode === 'ferry' && (
      hasValue('departure_port_in_eu') &&
      hasValue('arrival_port_in_eu') &&
      hasValue('carrier_is_eu') &&
      hasValue('departure_from_terminal')
    );
    const labelFor = (value) => {
      const v = String(value || '').trim().toLowerCase();
      if (v === 'yes') return 'Ja';
      if (v === 'no') return 'Nej';
      return 'Ikke afledt endnu';
    };

    form.querySelectorAll('[data-ferry-scope-auto-summary]').forEach((node) => {
      node.style.display = isAutoReady ? '' : 'none';
    });
    form.querySelectorAll('[data-ferry-scope-manual-editor]').forEach((node) => {
      node.style.display = isScopeComplete ? 'none' : '';
      node.open = !isScopeComplete && !isAutoReady ? true : node.open;
    });
    form.querySelectorAll('[data-ferry-scope-manual-fields]').forEach((node) => {
      const details = node.closest('[data-ferry-scope-manual-editor]');
      const showFields = !isScopeComplete && (!isAutoReady || (!!details && details.open));
      node.style.display = showFields ? 'grid' : 'none';
    });

    form.querySelectorAll('[data-ferry-scope-summary="departure_port_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('departure_port_in_eu'));
    });
    form.querySelectorAll('[data-ferry-scope-summary="arrival_port_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('arrival_port_in_eu'));
    });
    form.querySelectorAll('[data-ferry-scope-summary="carrier_is_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('carrier_is_eu'));
    });
    form.querySelectorAll('[data-ferry-scope-summary="departure_from_terminal"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('departure_from_terminal'));
    });
    form.querySelectorAll('[data-ferry-scope-summary="route_distance_meters"]').forEach((node) => {
      const value = String(getFieldValue('route_distance_meters') || '').trim();
      node.textContent = value || 'Ikke afledt endnu';
    });
  }

  function updateBusScopeUi() {
    const mode = currentTransportMode();
    const hasValue = (name) => String(getFieldValue(name) || '').trim() !== '';
    const isAutoReady = mode === 'bus' && (
      hasValue('boarding_in_eu') ||
      hasValue('alighting_in_eu') ||
      hasValue('departure_from_terminal') ||
      hasValue('scheduled_distance_km')
    );
    const isScopeComplete = mode === 'bus' && (
      hasValue('boarding_in_eu') &&
      hasValue('alighting_in_eu') &&
      hasValue('departure_from_terminal') &&
      hasValue('scheduled_distance_km')
    );
    const labelFor = (value) => {
      const v = String(value || '').trim().toLowerCase();
      if (v === 'yes') return 'Ja';
      if (v === 'no') return 'Nej';
      return 'Ikke afledt endnu';
    };

    form.querySelectorAll('[data-bus-scope-auto-summary]').forEach((node) => {
      node.style.display = isAutoReady ? '' : 'none';
    });
    form.querySelectorAll('[data-bus-scope-manual-editor]').forEach((node) => {
      node.style.display = isScopeComplete ? 'none' : '';
      if (!isScopeComplete && !isAutoReady) {
        node.open = true;
      }
    });
    form.querySelectorAll('[data-bus-scope-manual-fields]').forEach((node) => {
      const details = node.closest('[data-bus-scope-manual-editor]');
      const showFields = !isScopeComplete && (!isAutoReady || (!!details && details.open));
      node.style.display = showFields ? 'grid' : 'none';
    });
    form.querySelectorAll('[data-bus-scope-summary="boarding_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('boarding_in_eu'));
    });
    form.querySelectorAll('[data-bus-scope-summary="alighting_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('alighting_in_eu'));
    });
    form.querySelectorAll('[data-bus-scope-summary="departure_from_terminal"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('departure_from_terminal'));
    });
    form.querySelectorAll('[data-bus-scope-summary="scheduled_distance_km"]').forEach((node) => {
      const value = String(getFieldValue('scheduled_distance_km') || '').trim();
      node.textContent = value || 'Ikke afledt endnu';
    });
  }

  form.querySelectorAll('[data-bus-scope-manual-editor]').forEach((node) => {
    node.addEventListener('toggle', function () {
      const isBusMode = currentTransportMode() === 'bus';
      const isAutoReady = isBusMode && (
        String(getFieldValue('boarding_in_eu') || '').trim() !== '' ||
        String(getFieldValue('alighting_in_eu') || '').trim() !== '' ||
        String(getFieldValue('departure_from_terminal') || '').trim() !== '' ||
        String(getFieldValue('scheduled_distance_km') || '').trim() !== ''
      );
      const isScopeComplete = isBusMode && (
        String(getFieldValue('boarding_in_eu') || '').trim() !== '' &&
        String(getFieldValue('alighting_in_eu') || '').trim() !== '' &&
        String(getFieldValue('departure_from_terminal') || '').trim() !== '' &&
        String(getFieldValue('scheduled_distance_km') || '').trim() !== ''
      );
      node.querySelectorAll('[data-bus-scope-manual-fields]').forEach((fields) => {
        fields.style.display = (!isScopeComplete && (!isAutoReady || node.open)) ? 'grid' : 'none';
      });
    });
  });

  function updateAirScopeUi() {
    const normalizeAirDistanceBand = (value) => {
      const v = String(value || '').trim().toLowerCase();
      return ['up_to_1500', 'intra_eu_over_1500', 'other_1500_to_3500', 'other_over_3500'].includes(v) ? v : '';
    };
    const labelForAirDistanceBand = (value) => {
      switch (normalizeAirDistanceBand(value)) {
        case 'up_to_1500':
          return '1500 km eller mindre';
        case 'intra_eu_over_1500':
          return 'Inden for EU over 1500 km';
        case 'other_1500_to_3500':
          return 'Øvrige flyvninger 1500-3500 km';
        case 'other_over_3500':
          return 'Øvrige flyvninger over 3500 km';
        default:
          return 'Ikke afledt endnu';
      }
    };
    const thresholdForAirBand = (band) => {
      switch (normalizeAirDistanceBand(band)) {
        case 'up_to_1500':
          return 2;
        case 'intra_eu_over_1500':
        case 'other_1500_to_3500':
          return 3;
        case 'other_over_3500':
          return 4;
        default:
          return null;
      }
    };
    const deriveAirBand = (distanceKm, departureInEu, arrivalInEu) => {
      if (!Number.isFinite(distanceKm) || distanceKm <= 0) return '';
      if (distanceKm <= 1500) return 'up_to_1500';
      if (departureInEu && arrivalInEu) return 'intra_eu_over_1500';
      if (distanceKm <= 3500) return 'other_1500_to_3500';
      return 'other_over_3500';
    };
    const mode = currentTransportMode();
    const hasValue = (name) => String(getFieldValue(name) || '').trim() !== '';
    const rawDistance = String(getFieldValue('flight_distance_km') || '').trim();
    const parsedDistance = rawDistance !== '' && !Number.isNaN(Number(rawDistance)) ? Math.max(0, Math.round(Number(rawDistance))) : null;
    const depInEu = String(getFieldValue('departure_airport_in_eu') || '').trim().toLowerCase() === 'yes';
    const arrInEu = String(getFieldValue('arrival_airport_in_eu') || '').trim().toLowerCase() === 'yes';
    const selectedBand = normalizeAirDistanceBand(getFieldValue('air_distance_band'));
    const derivedBand = deriveAirBand(parsedDistance, depInEu, arrInEu);
    const band = selectedBand || derivedBand;
    const thresholdHours = thresholdForAirBand(band);
    if (parsedDistance !== null) {
      setFieldValue('flight_distance_km', String(parsedDistance));
    }
    if (band && selectedBand !== band) {
      setFieldValue('air_distance_band', band);
    } else if (!band && selectedBand) {
      setFieldValue('air_distance_band', '');
    }
    setFieldValue('air_delay_threshold_hours', thresholdHours !== null ? String(thresholdHours) : '');
    setFieldValue('intra_eu_over_1500', band === 'intra_eu_over_1500' ? 'yes' : (band ? 'no' : ''));
    const isAutoReady = mode === 'air' && (
      hasValue('departure_airport_in_eu') ||
      hasValue('arrival_airport_in_eu') ||
      hasValue('operating_carrier_is_eu') ||
      hasValue('marketing_carrier_is_eu') ||
      hasValue('flight_distance_km') ||
      hasValue('air_distance_band')
    );
    const isScopeComplete = mode === 'air' && (
      hasValue('departure_airport_in_eu') &&
      hasValue('arrival_airport_in_eu') &&
      hasValue('operating_carrier_is_eu') &&
      hasValue('marketing_carrier_is_eu') &&
      hasValue('flight_distance_km') &&
      hasValue('air_distance_band')
    );
    const labelFor = (value) => {
      const v = String(value || '').trim().toLowerCase();
      if (v === 'yes') return 'Ja';
      if (v === 'no') return 'Nej';
      return 'Ikke afledt endnu';
    };

    form.querySelectorAll('[data-air-scope-auto-summary]').forEach((node) => {
      node.style.display = isAutoReady ? '' : 'none';
    });
    form.querySelectorAll('[data-air-scope-manual-editor]').forEach((node) => {
      node.style.display = isScopeComplete ? 'none' : '';
      node.open = !isScopeComplete && !isAutoReady;
    });
    form.querySelectorAll('[data-air-scope-manual-fields]').forEach((node) => {
      const details = node.closest('[data-air-scope-manual-editor]');
      const showFields = !isScopeComplete && (!isAutoReady || (!!details && details.open));
      node.style.display = showFields ? 'grid' : 'none';
    });
    form.querySelectorAll('[data-air-scope-summary="departure_airport_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('departure_airport_in_eu'));
    });
    form.querySelectorAll('[data-air-scope-summary="arrival_airport_in_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('arrival_airport_in_eu'));
    });
    form.querySelectorAll('[data-air-scope-summary="operating_carrier_is_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('operating_carrier_is_eu'));
    });
    form.querySelectorAll('[data-air-scope-summary="marketing_carrier_is_eu"]').forEach((node) => {
      node.textContent = labelFor(getFieldValue('marketing_carrier_is_eu'));
    });
    form.querySelectorAll('[data-air-scope-summary="flight_distance_km"]').forEach((node) => {
      node.textContent = parsedDistance !== null ? String(parsedDistance) : 'Ikke afledt endnu';
    });
    form.querySelectorAll('[data-air-scope-summary="air_distance_band"]').forEach((node) => {
      node.textContent = labelForAirDistanceBand(band);
    });
    form.querySelectorAll('[data-air-scope-summary="air_delay_threshold_hours"]').forEach((node) => {
      node.textContent = thresholdHours !== null ? (String(thresholdHours) + '+ timer') : 'Ikke afledt endnu';
    });
  }

  function deriveScopeFromOperator() {
    const mode = currentTransportMode();
    if (mode === 'ferry') {
      const operator = getFieldValue('operator');
      const entry = findOperatorEntry('ferry', operator) || findOperatorEntry('ferry', getFieldValue('incident_segment_operator'));
      if (entry && entry.country_code) {
        setFieldValue('operator_country', entry.country_code);
      }
      if (entry && entry.is_eu_operator !== null && entry.is_eu_operator !== undefined) {
        setFieldValue('carrier_is_eu', entry.is_eu_operator ? 'yes' : 'no');
      }
      updateFerryScopeUi();
      return;
    }

    if (mode === 'bus') {
      const operator = getFieldValue('operator');
      const entry = findOperatorEntry('bus', operator) || findOperatorEntry('bus', getFieldValue('incident_segment_operator'));
      if (entry && entry.country_code) {
        setFieldValue('operator_country', entry.country_code);
      }
      updateBusScopeUi();
      return;
    }

    if (mode === 'air') {
      const operating = findOperatorEntry('air', getFieldValue('operating_carrier'));
      const marketing = findOperatorEntry('air', getFieldValue('marketing_carrier'));
      if (operating && operating.country_code) {
        setFieldValue('operator_country', operating.country_code);
      }
      if (operating && operating.is_eu_operator !== null && operating.is_eu_operator !== undefined) {
        setFieldValue('operating_carrier_is_eu', operating.is_eu_operator ? 'yes' : 'no');
      }
      if (marketing && marketing.is_eu_operator !== null && marketing.is_eu_operator !== undefined) {
        setFieldValue('marketing_carrier_is_eu', marketing.is_eu_operator ? 'yes' : 'no');
      }
      updateAirScopeUi();
    }
  }

  // Ticketless station autocomplete (offline station DB via /api/stations/search)
  (function(){
    if (!form || !stationsSearchUrl) return;
    const card = document.getElementById('ticketlessCard');
    if (!card) return;
    const ccInput = card.querySelector('input[name="operator_country"]');

	    function setup(name){
	      const input = card.querySelector('input[name="'+name+'"]');
	      const box = card.querySelector('.station-suggest[data-for="'+name+'"]');
	      if (!input || !box) return;
	      const hid = {
	        osm_id: card.querySelector('input[name="'+name+'_osm_id"]'),
	        lat: card.querySelector('input[name="'+name+'_lat"]'),
	        lon: card.querySelector('input[name="'+name+'_lon"]'),
	        country: card.querySelector('input[name="'+name+'_country"]'),
	        type: card.querySelector('input[name="'+name+'_type"]'),
	        source: card.querySelector('input[name="'+name+'_source"]'),
	      };
	      function clearMeta(){
	        if (hid.osm_id) hid.osm_id.value = '';
	        if (hid.lat) hid.lat.value = '';
	        if (hid.lon) hid.lon.value = '';
	        if (hid.country) hid.country.value = '';
	        if (hid.type) hid.type.value = '';
	        if (hid.source) hid.source.value = '';
	      }
	      function setMeta(st){
	        if (!st) { clearMeta(); return; }
	        if (hid.osm_id) hid.osm_id.value = (st.osm_id !== undefined && st.osm_id !== null) ? String(st.osm_id) : '';
	        if (hid.lat) hid.lat.value = (st.lat !== undefined && st.lat !== null) ? String(st.lat) : '';
	        if (hid.lon) hid.lon.value = (st.lon !== undefined && st.lon !== null) ? String(st.lon) : '';
	        if (hid.country) hid.country.value = (st.country !== undefined && st.country !== null) ? String(st.country) : '';
	        if (hid.type) hid.type.value = (st.type !== undefined && st.type !== null) ? String(st.type) : '';
	        if (hid.source) hid.source.value = (st.source !== undefined && st.source !== null) ? String(st.source) : '';
	      }
	
	      let timer = null;
	      let ctrl = null;

      function hide(){
        box.style.display = 'none';
        box.innerHTML = '';
      }

      function niceType(t){
        const s = (t||'').toString().toLowerCase();
        if (s === 'station') return 'Station';
        if (s === 'halt') return 'Stopested';
        return s;
      }

      function render(stations){
        box.innerHTML = '';
        if (!stations || !stations.length) { hide(); return; }
	        // Prefer "station" results to reduce noise for city-level queries (e.g., DÃ¼sseldorf).
	        const stStations = stations.filter(st => String(st && st.type || '').toLowerCase() === 'station');
	        const stOthers = stations.filter(st => String(st && st.type || '').toLowerCase() !== 'station');
	        const shown = (stStations.length >= 5) ? stStations : stStations.concat(stOthers);

	        shown.slice(0, 10).forEach(st => {
          const btn = document.createElement('button');
          btn.type = 'button';
          const nm = (st && st.name) ? String(st.name) : '';
          const cc = (st && st.country) ? String(st.country) : '';
          const tp = (st && st.type) ? String(st.type) : '';
	          // Avoid rows with no name (e.g., "DE Â· HALT"): always render a visible label.
	          btn.appendChild(document.createTextNode(nm || '(ukendt station)'));
          if (cc || tp) {
            const meta = document.createElement('div');
            meta.className = 'muted';
	            meta.textContent = [cc, niceType(tp)].filter(Boolean).join(' Â· ');
            btn.appendChild(document.createElement('br'));
            btn.appendChild(meta);
          }
	          btn.addEventListener('click', ()=>{
	            if (nm) input.value = nm;
	            setMeta(st);
	            hide();
	          });
	          box.appendChild(btn);
	        });
	        box.style.display = 'block';
	      }

      async function fetchStations(){
        const modeRadio = document.querySelector('input[name="transport_mode"]:checked');
        const transportMode = modeRadio ? String(modeRadio.value || 'rail') : 'rail';
        if (transportMode !== 'rail') { hide(); return; }
        const q = (input.value || '').trim();
        if (q.length < 2) { hide(); return; }
        const cc = (ccInput && (ccInput.value || '')) ? String(ccInput.value).trim().toUpperCase() : '';
        // If no country is provided, avoid a full EU scan until the user has typed more.
        if (!cc && q.length < 4) { hide(); return; }

        // Two-phase lookup:
        // 1) Try with country filter (fast, reduces noise)
        // 2) If empty and we had a country, retry without country (international routes)
        const buildUrl = (country) => {
          const u = new URL(stationsSearchUrl, window.location.origin);
          u.searchParams.set('q', q);
          if (country) u.searchParams.set('country', country);
          u.searchParams.set('limit', '10');
          return u;
        };

        if (ctrl) { try { ctrl.abort(); } catch(e) {} }
        ctrl = new AbortController();
        try {
          let res = await fetch(buildUrl(cc).toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
          if (!res.ok) { hide(); return; }
          let js = await res.json();
          let stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
          if ((!stations || stations.length === 0) && cc) {
            res = await fetch(buildUrl('').toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
            if (res.ok) {
              js = await res.json();
              stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
            }
          }
          render(stations);
        } catch(e) {
          // ignore (abort or network)
        }
      }

	      input.addEventListener('input', ()=>{
	        clearMeta(); // user is typing; metadata no longer trusted
	        if (timer) clearTimeout(timer);
	        timer = setTimeout(fetchStations, 200);
	      });
      input.addEventListener('focus', ()=>{
        if (box.innerHTML.trim() !== '') { box.style.display = 'block'; }
      });
      input.addEventListener('blur', ()=> setTimeout(hide, 180));
      // Prevent blur while clicking suggestions
      box.addEventListener('mousedown', (e)=> e.preventDefault());
    }

    setup('dep_station');
    setup('arr_station');
  })();

  // Multimodal node autocomplete for ferry ports, bus stops/terminals and airports.
  (function(){
    if (!form || !transportNodesSearchUrl) return;
    const inputs = Array.from(form.querySelectorAll('input[name="dep_station"], input[name="arr_station"], input[name="dep_terminal"], input[name="arr_terminal"]'));
    if (!inputs.length) return;
    const state = new WeakMap();
    const nodeSearchCache = new Map();
    let hydratePrefilledTimer = null;
    let hydratePrefilledBusy = false;

    function shouldUseTransportNodes(input) {
      if (!input || input.disabled) return false;
      if (input.offsetParent === null) return false;
      if (input.closest('fieldset[disabled]')) return false;
      const mode = currentTransportMode();
      return mode === 'ferry' || mode === 'bus' || mode === 'air';
    }

    function ensureBox(input) {
      if (!input.dataset.nodeSuggestOwner) {
        input.dataset.nodeSuggestOwner = 'node-suggest-' + Math.random().toString(36).slice(2);
      }
      let box = document.body.querySelector('.node-suggest.portal[data-owner="' + input.dataset.nodeSuggestOwner + '"]');
      if (!box) {
        box = document.createElement('div');
        box.className = 'node-suggest portal';
        box.dataset.for = input.name;
        box.dataset.owner = input.dataset.nodeSuggestOwner;
        box.style.display = 'none';
        document.body.appendChild(box);
      }
      return box;
    }

    function positionBox(box, input) {
      if (!box || !input) return;
      const rect = input.getBoundingClientRect();
      box.style.left = Math.round(rect.left) + 'px';
      box.style.top = Math.round(rect.bottom + 2) + 'px';
      box.style.width = Math.round(rect.width) + 'px';
    }

    function hide(box) {
      if (!box) return;
      box.style.display = 'none';
      box.innerHTML = '';
    }

    function nodeTypeLabel(mode, nodeType) {
      const raw = String(nodeType || '').toLowerCase();
      if (mode === 'ferry') {
        if (raw === 'port') return 'havn';
        if (raw === 'ferry_terminal' || raw === 'terminal') return 'færgeterminal';
        if (raw === 'cruise_terminal') return 'krydstogtterminal';
      }
      if (mode === 'bus') {
        if (raw === 'bus_terminal' || raw === 'terminal') return 'busterminal';
        if (raw === 'stop') return 'stoppested';
      }
      if (mode === 'air' && raw === 'airport') return 'lufthavn';
      return raw ? raw.replace(/_/g, ' ') : '';
    }

    function render(box, input, nodes) {
      if (!box) return;
      box.innerHTML = '';
      if (!nodes || !nodes.length) { hide(box); return; }
      const mode = currentTransportMode();
      nodes.slice(0, 10).forEach((node) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.appendChild(document.createTextNode(node.name || '(ukendt sted)'));
        const metaBits = [];
        if (node.code) metaBits.push(String(node.code));
        if (node.country) metaBits.push(String(node.country));
        if (node.in_eu === true) metaBits.push('EU');
        else if (node.in_eu === false && node.country) metaBits.push('ikke-EU');
        if (node.parent_name) metaBits.push(String(node.parent_name));
        if (node.node_type) metaBits.push(nodeTypeLabel(mode, node.node_type));
        if (metaBits.length) {
          const meta = document.createElement('div');
          meta.className = 'muted';
          meta.textContent = metaBits.join(' · ');
          btn.appendChild(document.createElement('br'));
          btn.appendChild(meta);
        }
        btn.addEventListener('click', () => {
          form.querySelectorAll('input[name="' + input.name + '"]').forEach((field) => {
            field.value = node.name ? String(node.name) : '';
          });
          setGenericLookupMeta(input.name, node);
          if (currentTransportMode() === 'ferry' && (input.name === 'dep_terminal' || input.name === 'arr_terminal')) {
            const portFieldName = input.name === 'dep_terminal' ? 'dep_station' : 'arr_station';
            const portField = form.querySelector('input[name="' + portFieldName + '"]');
            const portLookup = genericLookupMeta(portFieldName);
            if (portField && String(portField.value || '').trim() === '' && node.parent_name) {
              portField.value = String(node.parent_name);
              if (portLookup.id) {
                clearGenericLookupMeta(portFieldName);
              }
            }
          }
          deriveScopeFromNodes();
          hide(box);
        });
        box.appendChild(btn);
      });
      positionBox(box, input);
      box.style.display = 'block';
    }

    function buildNodeSearchUrl(input, q) {
      const mode = currentTransportMode();
      const url = new URL(transportNodesSearchUrl, window.location.origin);
      url.searchParams.set('mode', mode);
      url.searchParams.set('q', q);
      if (mode === 'ferry') {
        if (input.name === 'dep_terminal' || input.name === 'arr_terminal') {
          url.searchParams.set('kind', 'terminal');
        } else if (input.name === 'dep_station' || input.name === 'arr_station') {
          url.searchParams.set('kind', 'port');
        }
      }
      url.searchParams.set('limit', '10');

      return url;
    }

    async function fetchNodeSearchJson(url, signal) {
      const key = url.toString();
      if (nodeSearchCache.has(key)) {
        return nodeSearchCache.get(key);
      }
      const res = await fetch(key, { signal: signal || undefined, headers: { 'Accept': 'application/json' } });
      if (!res.ok) {
        throw new Error('node_search_failed');
      }
      const js = await res.json();
      nodeSearchCache.set(key, js);
      return js;
    }

    function applyNodeSelection(input, node) {
      form.querySelectorAll('input[name="' + input.name + '"]').forEach((field) => {
        field.value = node.name ? String(node.name) : '';
      });
      setGenericLookupMeta(input.name, node);
      if (currentTransportMode() === 'ferry' && (input.name === 'dep_terminal' || input.name === 'arr_terminal')) {
        const portFieldName = input.name === 'dep_terminal' ? 'dep_station' : 'arr_station';
        const portField = form.querySelector('input[name="' + portFieldName + '"]');
        const portLookup = genericLookupMeta(portFieldName);
        if (portField && String(portField.value || '').trim() === '' && node.parent_name) {
          portField.value = String(node.parent_name);
          if (portLookup.id) {
            clearGenericLookupMeta(portFieldName);
          }
        }
      }
      deriveScopeFromNodes();
    }

    async function fetchNodes(input, box) {
      if (!shouldUseTransportNodes(input)) { hide(box); return; }
      const q = String(input.value || '').trim();
      const mode = currentTransportMode();
      const minChars = mode === 'bus' ? 3 : 2;
      if (q.length < minChars) { hide(box); return; }
      const url = buildNodeSearchUrl(input, q);

      let localState = state.get(input);
      if (!localState) {
        localState = { timer: null, ctrl: null };
        state.set(input, localState);
      }
      if (localState.ctrl) {
        try { localState.ctrl.abort(); } catch (e) {}
      }
      localState.ctrl = new AbortController();

      try {
        const js = await fetchNodeSearchJson(url, localState.ctrl.signal);
        const nodes = js && js.data && Array.isArray(js.data.nodes) ? js.data.nodes : [];
        render(box, input, nodes);
      } catch (e) {
        hide(box);
      }
    }

    async function resolveExactNode(input) {
      if (!shouldUseTransportNodes(input)) return;
      const meta = genericLookupMeta(input.name);
      if (meta.id && String(meta.id.value || '').trim() !== '') return;
      const q = String(input.value || '').trim();
      if (q.length < 2) return;
      try {
        const js = await fetchNodeSearchJson(buildNodeSearchUrl(input, q));
        const nodes = js && js.data && Array.isArray(js.data.nodes) ? js.data.nodes : [];
        const normalizedQ = normalizeLookupText(q);
        const exact = nodes.find((node) => normalizeLookupText(node && node.name ? String(node.name) : '') === normalizedQ);
        if (exact) {
          applyNodeSelection(input, exact);
          return;
        }
        // If search resolved to a single transport node, use it as the canonical stop.
        if (nodes.length === 1 && nodes[0] && nodes[0].name) {
          applyNodeSelection(input, nodes[0]);
        }
      } catch (e) {
        // ignore lookup fallback errors
      }
    }

    inputs.forEach((input) => {
      const box = ensureBox(input);
      if (!box) return;
      let localState = { timer: null, ctrl: null };
      state.set(input, localState);
      input.addEventListener('input', () => {
        if (!shouldUseTransportNodes(input)) { hide(box); return; }
        clearGenericLookupMeta(input.name);
        clearDerivedScopeForNodeInput(input.name);
        deriveScopeFromNodes();
        if (localState.timer) clearTimeout(localState.timer);
        localState.timer = setTimeout(() => fetchNodes(input, box), currentTransportMode() === 'bus' ? 320 : 180);
      });
      input.addEventListener('focus', () => {
        if (!shouldUseTransportNodes(input)) { hide(box); return; }
        if (box.innerHTML.trim() !== '') {
          positionBox(box, input);
          box.style.display = 'block';
        }
      });
      input.addEventListener('blur', () => {
        setTimeout(() => hide(box), 180);
        if (currentTransportMode() !== 'bus') {
          setTimeout(() => resolveExactNode(input), 40);
        }
      });
      input.addEventListener('change', () => resolveExactNode(input));
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hide(box);
      });
      box.addEventListener('mousedown', (e) => e.preventDefault());
      window.addEventListener('resize', () => {
        if (box.style.display !== 'none') positionBox(box, input);
      });
      window.addEventListener('scroll', () => {
        if (box.style.display !== 'none') positionBox(box, input);
      }, true);
    });

    async function hydratePrefilledTransportNodes() {
      if (currentTransportMode() === 'bus') {
        deriveScopeFromNodes();
        updateBusScopeUi();
        return;
      }
      if (hydratePrefilledBusy) return;
      hydratePrefilledBusy = true;
      try {
        const visibleInputs = inputs.filter((input) => {
          if (!shouldUseTransportNodes(input)) return false;
          if (input.offsetParent === null) return false;
          return String(input.value || '').trim().length >= 2;
        });
        for (const input of visibleInputs) {
          const meta = genericLookupMeta(input.name);
          if (meta.id && String(meta.id.value || '').trim() !== '') {
            continue;
          }
          await resolveExactNode(input);
        }
        deriveScopeFromNodes();
      } finally {
        hydratePrefilledBusy = false;
      }
    }

    function scheduleHydratePrefilledTransportNodes(delay) {
      if (hydratePrefilledTimer !== null) {
        window.clearTimeout(hydratePrefilledTimer);
      }
      hydratePrefilledTimer = window.setTimeout(() => {
        hydratePrefilledTimer = null;
        const run = () => hydratePrefilledTransportNodes();
        if (typeof window.requestIdleCallback === 'function') {
          window.requestIdleCallback(run, { timeout: 400 });
        } else {
          run();
        }
      }, typeof delay === 'number' ? delay : 120);
    }

    form.querySelectorAll('input[name="transport_mode"]').forEach((input) => {
      input.addEventListener('change', () => {
        scheduleHydratePrefilledTransportNodes(150);
      });
    });

    scheduleHydratePrefilledTransportNodes(180);

  })();

  (function(){
    if (!form) return;
    const watchedNames = ['operator', 'incident_segment_operator', 'operating_carrier', 'marketing_carrier'];
    watchedNames.forEach((name) => {
      form.querySelectorAll('input[name="' + name + '"]').forEach((input) => {
        input.addEventListener('input', () => deriveScopeFromOperator());
        input.addEventListener('change', () => deriveScopeFromOperator());
      });
    });
    ['departure_port_in_eu', 'arrival_port_in_eu', 'carrier_is_eu', 'departure_from_terminal', 'route_distance_meters'].forEach((name) => {
      form.querySelectorAll('[name="' + name + '"]').forEach((input) => {
        input.addEventListener('input', updateFerryScopeUi);
        input.addEventListener('change', updateFerryScopeUi);
      });
    });
      ['boarding_in_eu', 'alighting_in_eu', 'departure_from_terminal', 'scheduled_distance_km'].forEach((name) => {
        form.querySelectorAll('[name="' + name + '"]').forEach((input) => {
          input.addEventListener('input', updateBusScopeUi);
          input.addEventListener('change', updateBusScopeUi);
        });
      });
      ['departure_airport_in_eu', 'arrival_airport_in_eu', 'operating_carrier_is_eu', 'marketing_carrier_is_eu'].forEach((name) => {
        form.querySelectorAll('[name="' + name + '"]').forEach((input) => {
          input.addEventListener('input', updateAirScopeUi);
          input.addEventListener('change', updateAirScopeUi);
        });
      });

      form.querySelectorAll('input[name="transport_mode"]').forEach((input) => {
        input.addEventListener('change', () => {
          deriveScopeFromNodes();
          deriveScopeFromOperator();
          updateFerryScopeUi();
          updateBusScopeUi();
          updateAirScopeUi();
        });
      });

      deriveScopeFromNodes();
      deriveScopeFromOperator();
      updateFerryScopeUi();
      updateBusScopeUi();
      updateAirScopeUi();
    })();

  // Ticketless operator/product suggestions + lightweight autofill (offline catalog)
  (function(){
    if (!form) return;
    const card = document.getElementById('ticketlessCard');
    if (!card) return;
    const opInput = card.querySelector('input[name=\"operator\"]');
    const ccInput = card.querySelector('input[name=\"operator_country\"]');
    const ccAssumed = card.querySelector('input[name=\"operator_country_assumed\"]');
    const prodInput = card.querySelector('input[name=\"operator_product\"]');
    const prodList = document.getElementById('productSuggestions');

    function setProductOptions(list){
      if (!prodList) return;
      prodList.innerHTML = '';
      const seen = new Set();
      (list||[]).forEach(p=>{
        const v = (p||'').toString().trim();
        if (!v || seen.has(v)) return;
        seen.add(v);
        const opt = document.createElement('option');
        opt.value = v;
        prodList.appendChild(opt);
      });
    }
    function updateProductSuggestions(){
      const op = (opInput && opInput.value || '').trim();
      if (op && productsByOperator && productsByOperator[op]) {
        setProductOptions(productsByOperator[op]);
        return;
      }
      // Fallback: union of all products (kept short by unique filter)
      const all = [];
      if (productsByOperator) {
        Object.keys(productsByOperator).forEach(k=>{
          const arr = productsByOperator[k];
          if (Array.isArray(arr)) all.push(...arr);
        });
      }
      setProductOptions(all);
    }
    function maybeAutofillCountry(){
      if (!opInput || !ccInput) return;
      const ccNow = (ccInput.value || '').trim();
      // Only override when empty OR when it's still marked as assumed/default.
      const isAssumed = !!(ccAssumed && (ccAssumed.value || '').trim() === '1');
      if (ccNow !== '' && !isAssumed) return;
      const op = (opInput.value || '').trim();
      const cc = (operatorToCountry && operatorToCountry[op]) ? operatorToCountry[op] : '';
      if (cc) {
        ccInput.value = cc;
        if (ccAssumed) ccAssumed.value = '1';
      }
    }
    function maybeAutofillPriceCurrency(){
      const depTerminalCountry = (card.querySelector('input[name="dep_terminal_lookup_country"]') || {}).value || '';
      const depStationLookupCountry = (card.querySelector('input[name="dep_station_lookup_country"]') || {}).value || '';
      const depStationCountry = (card.querySelector('input[name="dep_station_country"]') || {}).value || '';
      const arrTerminalCountry = (card.querySelector('input[name="arr_terminal_lookup_country"]') || {}).value || '';
      const arrStationLookupCountry = (card.querySelector('input[name="arr_station_lookup_country"]') || {}).value || '';
      const arrStationCountry = (card.querySelector('input[name="arr_station_country"]') || {}).value || '';
      const cc = (
        (ccInput && (ccInput.value || '').trim().toUpperCase()) ||
        String(depTerminalCountry).trim().toUpperCase() ||
        String(depStationLookupCountry).trim().toUpperCase() ||
        String(depStationCountry).trim().toUpperCase() ||
        String(arrTerminalCountry).trim().toUpperCase() ||
        String(arrStationLookupCountry).trim().toUpperCase() ||
        String(arrStationCountry).trim().toUpperCase()
      );
      if (!cc) return;
      const cur = countryToCurrency && countryToCurrency[cc] ? countryToCurrency[cc] : 'EUR';
      const sel = card.querySelector('select[name=\"price_currency\"]');
      const price = card.querySelector('input[name=\"price\"]');
      const pkYes = !!card.querySelector('input[name=\"price_known\"][value=\"yes\"]:checked');
      if (!sel) return;
      if (!pkYes && !(price && (price.value||'').trim() !== '')) return;
      if ((sel.value || '').trim() === '') sel.value = cur;
    }

    if (opInput) {
      opInput.addEventListener('change', ()=>{ maybeAutofillCountry(); updateProductSuggestions(); maybeAutofillPriceCurrency(); }, { passive:true });
      opInput.addEventListener('blur', ()=>{ maybeAutofillCountry(); updateProductSuggestions(); maybeAutofillPriceCurrency(); }, { passive:true });
    }
    if (ccInput) {
      ccInput.addEventListener('change', ()=>{ if (ccAssumed) ccAssumed.value = '0'; maybeAutofillPriceCurrency(); }, { passive:true });
      ccInput.addEventListener('blur', ()=>{ maybeAutofillPriceCurrency(); }, { passive:true });
    }
    ['dep_station','arr_station','dep_terminal','arr_terminal','price'].forEach((name)=>{
      const el = card.querySelector('[name="' + name + '"]');
      if (!el) return;
      el.addEventListener('change', ()=>{ window.setTimeout(maybeAutofillPriceCurrency, 0); }, { passive:true });
      el.addEventListener('blur', ()=>{ window.setTimeout(maybeAutofillPriceCurrency, 0); }, { passive:true });
    });

    updateProductSuggestions();
    maybeAutofillCountry();
    maybeAutofillPriceCurrency();
  })();

  // Ticket mode toggle (ticket vs ticketless)
  (function(){
    if (!form) return;
    const uploadCard = document.getElementById('ticketUploadCard');
    const ticketlessCard = document.getElementById('ticketlessCard');
    const seasonCard = document.getElementById('seasonPassCard');
    const transportModeCard = document.getElementById('transportModeCard');
    const transportModeAutoBlock = document.getElementById('transportModeAutoBlock');
    const transportModeAutoPending = document.getElementById('transportModeAutoPending');
    const transportModeAutoReadyBlock = document.getElementById('transportModeAutoReady');
    const transportModeManualBlock = document.getElementById('transportModeManualBlock');
      const transportModeChoices = document.getElementById('transportModeChoices');
      const transportModeHidden = document.getElementById('transportModeHidden');
      const journeyToggleBtn = document.getElementById('toggleJourneyFields');
      const journeyFieldsWrap = document.getElementById('journeyFields');
      const seasonOptionWrap = document.getElementById('seasonPassOptionWrap');
    const modeContractCard = document.getElementById('modeContractCard');
    const priceBlock = document.getElementById('ticketlessPriceBlock');
    const ticketlessFs = document.getElementById('ticketlessFieldset');
    const seasonFs = document.getElementById('seasonPassFieldset');
    const seasonHas = document.getElementById('seasonPassHas');
    const journeyFieldsFs = document.getElementById('journeyFieldsFieldset');
    const genericJourneyFields = document.getElementById('genericJourneyFields');
    const railJourneyFields = document.getElementById('railJourneyFields');
    const modeJourneyFields = document.getElementById('modeJourneyFields');
    const a12Qs = document.getElementById('a12Questions');
    const uploadedAutoPrefill = <?= json_encode([
      'operator' => (string)($meta['_auto']['operator']['value'] ?? ''),
      'operator_product' => (string)($meta['_auto']['operator_product']['value'] ?? ''),
      'dep_station' => (string)($meta['_auto']['dep_station']['value'] ?? ''),
      'arr_station' => (string)($meta['_auto']['arr_station']['value'] ?? ''),
    ], JSON_UNESCAPED_UNICODE) ?>;
    const inheritedJourneyFieldNames = [
      'operator',
      'operator_product',
      'dep_station',
      'arr_station',
      'dep_terminal',
      'arr_terminal',
      'ticket_no',
      'dep_date',
      'dep_time',
      'arr_time',
      'price',
      'price_currency',
      'price_known',
      'trip_type',
      'return_dep_date',
      'return_dep_time',
      'return_dep_station',
      'return_arr_station',
      'outbound_fare_amount',
      'return_fare_amount',
      'affected_leg'
    ];
    const derivedModeFieldNames = [
      'departure_port_in_eu',
      'arrival_port_in_eu',
      'carrier_is_eu',
      'departure_from_terminal',
      'route_distance_meters',
      'boarding_in_eu',
      'alighting_in_eu',
      'scheduled_distance_km',
      'departure_airport_in_eu',
      'arrival_airport_in_eu',
      'operating_carrier_is_eu',
      'marketing_carrier_is_eu',
      'flight_distance_km',
      'air_distance_band',
      'air_delay_threshold_hours'
    ];
    function setDisabledWithin(el, disabled){
      if (!el) return;
      el.querySelectorAll('input, select, textarea, button').forEach((node) => {
        if (node.id === 'toggleJourneyFields') return;
        if (disabled) {
          node.setAttribute('data-was-enabled', node.disabled ? '0' : '1');
          node.disabled = true;
          return;
        }
        const wasEnabled = node.getAttribute('data-was-enabled');
        if (wasEnabled === '1') node.disabled = false;
        node.removeAttribute('data-was-enabled');
      });
    }

    function radioVal(name){
      const nodes = form.querySelectorAll('input[name="'+name+'"]');
      for (const n of nodes) {
        if (!n || n.disabled) continue;
        if (n.type === 'radio' && n.checked) return n.value;
      }
      for (const n of nodes) {
        if (!n || n.disabled) continue;
        if (n.type !== 'radio') return n.value || '';
      }
      return '';
    }
    function clearFieldByName(name){
      form.querySelectorAll('[name="' + name + '"]').forEach((node) => {
        if (!node) return;
        if (node.type === 'radio' || node.type === 'checkbox') {
          node.checked = false;
          return;
        }
        node.value = '';
      });
    }
    function resetInheritedJourneyFieldsForModeSwitch(){
      inheritedJourneyFieldNames.forEach((name) => {
        clearFieldByName(name);
        if (typeof clearGenericLookupMeta === 'function') {
          clearGenericLookupMeta(name);
        }
      });
      ['operator_country', 'operator_country_assumed', 'operating_carrier', 'marketing_carrier', 'service_type', 'bus_regular_service', 'scope_choice', 'fare_class_purchased', 'berth_seat_type'].forEach((name) => {
        clearFieldByName(name);
      });
      derivedModeFieldNames.forEach((name) => clearFieldByName(name));
    }
    function show(el, on){ if (!el) return; el.style.display = on ? 'block' : 'none'; }
    function pinnedUploadRenderMode(ticketMode){
      if (!journeyFieldsFs) return '';
      if (String(ticketMode || 'ticket') !== 'ticket') return '';
      if (journeyFieldsFs.dataset.uploadPinned !== '1') return '';
      return String(journeyFieldsFs.dataset.renderMode || '').trim();
    }
    function setTransportModeEnabled(enabled){
      form.querySelectorAll('input[name="transport_mode"]').forEach((input) => {
        input.disabled = !enabled;
      });
    }
    function updateTicketMode(){
      const mode = radioVal('ticket_upload_mode') || 'ticket';
      const isTicketless = mode === 'ticketless';
      const transportMode = resolvedTransportMode() || '';
      const seasonAllowed = transportMode === 'rail' || transportMode === 'ferry';
      const isSeason = seasonAllowed && mode === 'seasonpass';
      const transportAutoReady = transportModeCard && transportModeCard.dataset.autoReady === '1';
      const transportModeChosen = transportMode !== '';
      const transportSelectionRequired = isTicketless || isSeason;
      const hasModeAnalysis = modeContractCard && modeContractCard.dataset.hasAnalysis === '1';
      show(uploadCard, mode === 'ticket');
      show(ticketlessCard, isTicketless && transportModeChosen);
      show(seasonCard, isSeason);
      show(transportModeCard, transportSelectionRequired);
      show(transportModeAutoBlock, !transportSelectionRequired);
      show(transportModeManualBlock, transportSelectionRequired);
      show(transportModeAutoPending, !transportSelectionRequired && !transportAutoReady);
      show(transportModeAutoReadyBlock, !transportSelectionRequired && transportAutoReady);
      show(transportModeChoices, transportSelectionRequired || transportAutoReady);
      show(seasonOptionWrap, seasonAllowed);
      show(modeContractCard, transportModeChosen && (mode !== 'ticket' || hasModeAnalysis));
      // Prevent duplicate-name inputs from overwriting each other on submit.
      // Also keep the currently visible mode editable without requiring a round-trip.
      if (ticketlessFs) { ticketlessFs.disabled = !isTicketless; }
      if (seasonFs) { seasonFs.disabled = !isSeason; }
      if (seasonHas) { seasonHas.value = isSeason ? '1' : '0'; }
      if (journeyFieldsFs) { journeyFieldsFs.disabled = isTicketless; }
      if (journeyToggleBtn) {
        journeyToggleBtn.dataset.ticketMode = mode;
        const pinnedOpen = journeyToggleBtn.dataset.pinnedOpen === '1';
        journeyToggleBtn.style.display = (mode === 'ticket' && !pinnedOpen) ? '' : 'none';
      }
      if (journeyFieldsWrap) {
        journeyFieldsWrap.style.display = mode === 'ticket' ? 'block' : 'none';
      }
      setTransportModeEnabled(transportSelectionRequired || transportAutoReady);
      if (transportModeHidden) {
        transportModeHidden.disabled = transportSelectionRequired;
        transportModeHidden.value = transportMode;
      }
      setDisabledWithin(modeContractCard, !transportModeChosen || (mode === 'ticket' && !hasModeAnalysis));
      if (!seasonAllowed && mode === 'seasonpass') {
        const ticketRadio = form.querySelector('input[name="ticket_upload_mode"][value="ticket"]');
        if (ticketRadio) { ticketRadio.checked = true; }
        return updateTicketMode();
      }
      // In ticketless mode we always want Art.12 questions available, even before the first submit.
      if (a12Qs && (isTicketless || isSeason) && transportModeChosen) { a12Qs.style.display = 'block'; }
    }
    function updatePriceKnown(){
      if (!priceBlock) return;
      const pk = radioVal('price_known') || 'no';
      const on = pk === 'yes';
      show(priceBlock, on);
      if (!on) {
        const price = form.querySelector('input[name="price"]');
        if (price) price.value = '';
        const cur = form.querySelector('select[name="price_currency"]');
        if (cur) cur.value = '';
      }
    }
    function updateFerryTripType(){
      const handled = new Set();
      form.querySelectorAll('select[name="trip_type"][data-return-target]').forEach((select) => {
        const targetId = String(select.dataset.returnTarget || '').trim();
        if (!targetId) return;
        const block = document.getElementById(targetId);
        if (!block) return;
        const isActiveSelect = !select.disabled && select.offsetParent !== null;
        const isReturn = isActiveSelect && String(select.value || 'single') === 'return';
        handled.add(targetId);
        show(block, isReturn);
        block.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = !isReturn;
        });
      });
      ['ticketlessReturnTripBlock', 'modeJourneyReturnTripBlock'].forEach((targetId) => {
        if (handled.has(targetId)) return;
        const block = document.getElementById(targetId);
        if (!block) return;
        show(block, false);
        block.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = true;
        });
      });
    }
    function updateTransportMode(){
      const mode = resolvedTransportMode() || '';
      const ticketMode = radioVal('ticket_upload_mode') || 'ticket';
      const isTicketless = ticketMode === 'ticketless';
      const hasModeAnalysis = modeContractCard && modeContractCard.dataset.hasAnalysis === '1';
      const pinnedRenderMode = pinnedUploadRenderMode(ticketMode);
      const renderMode = pinnedRenderMode !== '' ? pinnedRenderMode : (mode !== '' ? mode : '');
      const hasMode = renderMode !== '';
      show(genericJourneyFields, !hasMode && ticketMode === 'ticket');
      show(railJourneyFields, renderMode === 'rail');
      show(modeJourneyFields, renderMode !== '' && renderMode !== 'rail');
      show(modeContractCard, hasMode && (ticketMode !== 'ticket' || hasModeAnalysis));
      setDisabledWithin(modeContractCard, !hasMode || (ticketMode === 'ticket' && !hasModeAnalysis));
    }
    function activeSelectValue(name){
      const selects = Array.from(form.querySelectorAll('select[name="' + name + '"]'));
      const active = selects.find((node) => !node.disabled && node.offsetParent !== null);
      if (active) return String(active.value || '');
      const any = selects.find((node) => !node.disabled);
      return any ? String(any.value || '') : '';
    }
    function setModeContractRowState(selector, visible){
      form.querySelectorAll(selector).forEach((row) => {
        const uploadHidden = row.dataset.uploadEditHidden === '1';
        if (uploadHidden && !window.__modeContractShowAll) {
          row.style.display = 'none';
          row.querySelectorAll('select, input, textarea').forEach((node) => {
            node.disabled = true;
          });
          return;
        }
        row.style.display = visible ? '' : 'none';
        row.querySelectorAll('select, input, textarea').forEach((node) => {
          node.disabled = !visible;
        });
      });
    }
    function updateModeContractQuestionVisibility(){
      if (!modeContractCard) return;
      const journeyStructure = activeSelectValue('journey_structure');
      const ticketMode = radioVal('ticket_upload_mode') || 'ticket';
      const isTicketlessContract = ticketMode === 'ticketless' || ticketMode === 'seasonpass';
      const showContractStructure = !isTicketlessContract && journeyStructure !== '' && journeyStructure !== 'single_segment';
      const showSeller = !isTicketlessContract;
      setModeContractRowState('[data-contract-seller-row="1"]', showSeller);
      setModeContractRowState('[data-contract-disclosure-row="1"]', showContractStructure);
      setModeContractRowState('[data-contract-notice-row="1"]', showContractStructure);
    }
    let modeContractRefreshTimer = null;
    function scheduleModeContractRefresh(){
      if (!modeContractCard) return;
      updateModeContractQuestionVisibility();
    }
    const modeContractRefreshNames = new Set([
      'seller_channel',
      'shared_pnr_scope',
      'same_transaction',
      'journey_structure',
      'through_ticket_disclosure',
      'separate_contract_notice',
      'same_pnr',
      'same_booking_reference',
      'same_eticket',
      'self_transfer_notice'
    ]);

    form.addEventListener('change', (e)=>{
      const nm = (e.target && (e.target.name||'')) || '';
      if (nm === 'ticket_upload_mode') updateTicketMode();
      if (nm === 'transport_mode') {
        updateTransportMode();
        updateModeContractQuestionVisibility();
        const activeTicketMode = radioVal('ticket_upload_mode') || 'ticket';
        if (e.isTrusted && entitlementsAutoSubmitReady && (activeTicketMode === 'ticketless' || activeTicketMode === 'seasonpass')) {
          resetInheritedJourneyFieldsForModeSwitch();
          setTimeout(() => form.submit(), 0);
        }
        return;
      }
      if (nm === 'price_known') updatePriceKnown();
      if (nm === 'trip_type') updateFerryTripType();
      if (nm === 'journey_structure') updateModeContractQuestionVisibility();
      if (modeContractRefreshNames.has(nm)) scheduleModeContractRefresh();
    }, { passive:true });
    updateTicketMode();
    updateTransportMode();
    updatePriceKnown();
    updateFerryTripType();
    updateModeContractQuestionVisibility();
    try {
      const focusCard = sessionStorage.getItem('entitlementsFocusCard');
      if (focusCard === 'modeContractCard') {
        sessionStorage.removeItem('entitlementsFocusCard');
        setTimeout(() => {
          const el = document.getElementById('modeContractCard');
          if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }, 40);
      }
    } catch (e) { /* ignore */ }
  })();
  // Toggle journey fields 3.1â€“3.5
  const toggleBtn = document.getElementById('toggleJourneyFields');
  const jf = document.getElementById('journeyFields');
  const journeyFieldsFsToggle = document.getElementById('journeyFieldsFieldset');
  const genericJourneyFieldsToggle = document.getElementById('genericJourneyFields');
  const railJourneyFieldsToggle = document.getElementById('railJourneyFields');
  const modeJourneyFieldsToggle = document.getElementById('modeJourneyFields');
  const journeyFieldsStorageKey = toggleBtn
    ? (toggleBtn.dataset.storageKey || ('flow.entitlements.journeyFieldsOpen.' + (window.location.pathname || 'entitlements')))
    : '';
  const persistJourneyFieldsState = (isOpen) => {
    if (!journeyFieldsStorageKey) return;
    try {
      sessionStorage.setItem(journeyFieldsStorageKey, isOpen ? '1' : '0');
    } catch (e) { /* ignore */ }
  };
  const openJourneyFields = () => {
    if (!jf) return;
    jf.style.display = 'block';
  };
  const closeJourneyFields = () => {
    if (!jf) return;
    jf.style.display = 'none';
  };
  const areJourneyFieldsOpen = () => !!(jf && jf.style.display !== 'none');
  const radioValue = (name) => {
    if (!form) return '';
    const nodes = form.querySelectorAll('input[name="' + name + '"]');
    for (const node of nodes) {
      if (!node || node.disabled) continue;
      if (node.type === 'radio' && node.checked) return String(node.value || '');
    }
    return '';
  };
  const showJourneyBlock = (el, visible) => {
    if (!el) return;
    el.style.display = visible ? 'block' : 'none';
  };
  const pinnedUploadJourneyMode = () => {
    const ticketMode = radioValue('ticket_upload_mode') || 'ticket';
    if (ticketMode !== 'ticket') return '';
    if (!journeyFieldsFsToggle || journeyFieldsFsToggle.dataset.uploadPinned !== '1') return '';
    return String(journeyFieldsFsToggle.dataset.renderMode || '').trim();
  };
  const resolvedJourneyMode = () => {
    const pinnedMode = pinnedUploadJourneyMode();
    if (pinnedMode) return pinnedMode;
    const submitted = submittedTransportMode();
    if (submitted) return submitted;
    if (toggleBtn) {
      const toggleMode = String(toggleBtn.dataset.transportMode || '').trim();
      if (toggleMode) return toggleMode;
    }
    return String(initialTransportMode || '').trim();
  };
  const syncJourneyModeBlocks = () => {
    const mode = resolvedJourneyMode();
    const ticketMode = radioValue('ticket_upload_mode') || 'ticket';
    const hasMode = mode !== '';
    showJourneyBlock(genericJourneyFieldsToggle, !hasMode && ticketMode === 'ticket');
    showJourneyBlock(railJourneyFieldsToggle, mode === 'rail');
    showJourneyBlock(modeJourneyFieldsToggle, hasMode && mode !== 'rail');
  };
  const journeyToggleLabel = () => {
    if (!toggleBtn) return 'Vis/skjul rejsefelter (3.1–3.5)';
    const mode = resolvedJourneyMode() || 'rail';
    if (mode === 'ferry') return 'Vis/skjul booking- og havnefelter';
    if (mode === 'bus') return 'Vis/skjul booking- og rutefelter';
    if (mode === 'air') return 'Vis/skjul booking- og flyfelter';
    return 'Vis/skjul rejsefelter (3.1–3.5)';
  };
  const updateJourneyToggleUi = () => {
    if (!toggleBtn || !jf) return;
    const isOpen = areJourneyFieldsOpen();
    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    toggleBtn.textContent = isOpen
      ? journeyToggleLabel() + ' – skjul'
      : journeyToggleLabel() + ' – vis';
  };
  const hasTickets = !!(toggleBtn && toggleBtn.dataset.hasTickets === '1');
  const hasJourneyPrefill = !!(toggleBtn && toggleBtn.dataset.hasPrefill === '1');
  const defaultJourneyFieldsOpen = !!(toggleBtn && toggleBtn.dataset.defaultOpen === '1');
  const forceJourneyFieldsOpen = !!(toggleBtn && toggleBtn.dataset.forceOpen === '1');
  const pinJourneyFieldsOpen = !!(toggleBtn && toggleBtn.dataset.pinnedOpen === '1');
  const journeyFieldsCollapsible = !!(toggleBtn && toggleBtn.dataset.collapsible === '1');
  if (toggleBtn && jf) {
    const currentTicketMode = () => String((radioValue('ticket_upload_mode') || toggleBtn.dataset.ticketMode || 'ticket')).trim();
    let journeyFieldsOpen = pinJourneyFieldsOpen || forceJourneyFieldsOpen || defaultJourneyFieldsOpen || hasTickets || hasJourneyPrefill;
    if (currentTicketMode() !== 'ticket') {
      journeyFieldsOpen = false;
    }
    if (journeyFieldsCollapsible && !forceJourneyFieldsOpen && !pinJourneyFieldsOpen) {
      try {
        const storedState = sessionStorage.getItem(journeyFieldsStorageKey);
        if (storedState === '1' || storedState === '0') {
          journeyFieldsOpen = storedState === '1';
        }
      } catch (e) { /* ignore */ }
    } else {
      journeyFieldsOpen = true;
      persistJourneyFieldsState(true);
    }
    if (journeyFieldsOpen) {
      openJourneyFields();
    } else {
      closeJourneyFields();
    }
    syncJourneyModeBlocks();
  }
  if (toggleBtn && jf) {
    toggleBtn.addEventListener('click', function(e){
      e.preventDefault();
      const opening = !areJourneyFieldsOpen();
      if (opening) {
        openJourneyFields();
      } else {
        closeJourneyFields();
      }
      syncJourneyModeBlocks();
      persistJourneyFieldsState(opening);
      updateJourneyToggleUi();
    });
  }
  if (toggleBtn && !journeyFieldsCollapsible) {
    toggleBtn.setAttribute('aria-hidden', 'true');
  }
  if (toggleBtn && pinJourneyFieldsOpen) {
    toggleBtn.setAttribute('aria-hidden', 'true');
    toggleBtn.style.display = 'none';
  }
  updateJourneyToggleUi();
  if (form && toggleBtn) {
    form.addEventListener('change', function(e){
      if (e.target && e.target.name === 'ticket_upload_mode') {
        toggleBtn.dataset.ticketMode = e.target.value || 'ticket';
      }
      if (e.target && e.target.name === 'transport_mode') {
        toggleBtn.dataset.transportMode = e.target.value || '';
        syncJourneyModeBlocks();
        updateJourneyToggleUi();
      }
    }, { passive:true });
  }
  syncJourneyModeBlocks();
  const panel = document.getElementById('hooksPanel');
  const loadHooksBtn = document.getElementById('loadHooksBtn');
  if (!form || !panel) return;
  function loadHooksPanel() {
    if (panel.dataset.loading === '1') return;
    if (panel.dataset.loaded === '1') return;
    panel.dataset.loading = '1';
    if (loadHooksBtn) {
      loadHooksBtn.disabled = true;
      loadHooksBtn.textContent = 'Indlaeser...';
    }
    const url = new URL(window.location.href);
    url.searchParams.set('ajax_hooks', '1');
    fetch(url.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then((resp) => resp.text())
      .then((txt) => {
        panel.innerHTML = txt;
        panel.dataset.loaded = '1';
      })
      .catch(() => {
        panel.innerHTML = '<div class="small muted">Hooks kunne ikke indlaeses.</div>';
      })
      .finally(() => {
        panel.dataset.loading = '0';
        if (loadHooksBtn) {
          loadHooksBtn.disabled = panel.dataset.loaded === '1';
          loadHooksBtn.textContent = panel.dataset.loaded === '1' ? 'Sidepanel indlaest' : 'Indlaes sidepanel';
        }
      });
  }
  if (loadHooksBtn) {
    loadHooksBtn.addEventListener('click', function(){
      loadHooksPanel();
    });
  }
  const seUnder150 = form.querySelector('input[name="se_under_150km"]');
  if (seUnder150) {
    let allowAutoSubmit = false;
    requestAnimationFrame(() => { allowAutoSubmit = true; });
    seUnder150.addEventListener('change', function(e) {
      if (!allowAutoSubmit || !entitlementsAutoSubmitReady || !e.isTrusted) return;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    });
  }
  // Upload UI wiring
  const drop = document.getElementById('uploadDropzone');
  const addBtn = document.getElementById('addFilesBtn');
  const inputMulti = document.getElementById('ticketMulti');
  const inputSingle = document.getElementById('ticketSingle');
  const list = document.getElementById('selectedFilesList');
  const clearBtn = document.getElementById('clearFilesBtn');
  let dt = new DataTransfer();
  // Will be set later (ajax hooks refresher)
  let triggerHooks = null;

  function fileKey(f){ return [f.name, f.size, f.lastModified].join(':'); }
  function addFiles(files){
    const seen = new Set(Array.from(dt.files).map(fileKey));
    for (const f of files) { if (!seen.has(fileKey(f))) dt.items.add(f); }
    openJourneyFields();
    persistJourneyFieldsState(true);
    sync();
  }
  function resetUploadInputs(){
    if (inputMulti) { inputMulti.value = ''; }
    if (inputSingle) { inputSingle.value = ''; }
  }
  function removeIndex(idx){
    const ndt = new DataTransfer();
    Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) ndt.items.add(f); });
    dt = ndt; resetUploadInputs(); sync();
    // If nothing left client-side, force clear_all to wipe cached tickets server-side
    if (dt.files.length === 0) {
      persistJourneyFieldsState(false);
      const hid = document.createElement('input');
      hid.type = 'hidden'; hid.name = 'clear_all'; hid.value = '1';
      form.appendChild(hid);
    }
    setTimeout(()=>form.submit(),0);
  }
  function clearActionInputs(){
    const nodes = form.querySelectorAll('input[name="remove_ticket"], input[name="clear_all"]');
    if (!nodes.length) return;
    nodes.forEach(n => n.remove());
  }

  function sync(){
    const mdt = new DataTransfer();
    Array.from(dt.files).slice(1).forEach((f) => mdt.items.add(f));
    inputMulti.files = mdt.files;
    const sdt = new DataTransfer();
    if (dt.files.length > 0) sdt.items.add(dt.files[0]);
    inputSingle.files = sdt.files;
    renderList();
    // Submit for server-side parsing (fills operator/PNR/etc.)
    clearActionInputs();
    setTimeout(()=>form.submit(), 0);
  }
  function renderList(){
    list.innerHTML = '';
    const hideEmpty = list.dataset.hideEmpty === '1';
    if (dt.files.length === 0) {
      if (hideEmpty) {
        list.style.display = 'none';
        return;
      }
      list.style.display = 'block';
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Der er ikke valgt nogen fil.';
      list.appendChild(li);
      return;
    }
    list.style.display = 'block';
    Array.from(dt.files).forEach((f, i)=>{
      const li = document.createElement('li');
      li.className = 'file-item';
      const name = document.createElement('span');
      name.textContent = f.name + ' (' + Math.round(f.size/1024) + ' KB)';
      const rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'small'; rm.textContent = 'Fjern';
      rm.addEventListener('click', ()=> removeIndex(i));
      li.appendChild(name); li.appendChild(rm);
      list.appendChild(li);
    });
  }
  if (addBtn && inputMulti && inputSingle && list) {
    if (drop) {
      drop.addEventListener('click', ()=> inputMulti.click());
      drop.addEventListener('dragover', (e)=>{ e.preventDefault(); drop.classList.add('drag'); });
      drop.addEventListener('dragleave', ()=>{ drop.classList.remove('drag'); });
      drop.addEventListener('drop', (e)=>{ e.preventDefault(); drop.classList.remove('drag'); if (e.dataTransfer?.files?.length){ addFiles(e.dataTransfer.files); }});
    }
    addBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      e.stopPropagation();
      resetUploadInputs();
      inputMulti.click();
    });
    if (clearBtn) {
      clearBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        e.stopPropagation();
        dt = new DataTransfer();
        resetUploadInputs();
        persistJourneyFieldsState(false);
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'clear_all';
        hid.value = '1';
        form.appendChild(hid);
        sync();
      });
    }
    inputMulti.addEventListener('change', ()=>{
      if (inputMulti.files?.length) {
        addFiles(inputMulti.files);
      }
    });
    renderList();
  }

  // (Midlertidigt) deaktiveret live AJAX-hook-refresh pga. upload/rydning-race.
  // Hooks opdateres via normal submit og server-side render efter upload/fjern.

  // Sync missed-connection radios to text field
  const mcField = document.getElementById('mcField');
  const mcRadios = Array.from(document.querySelectorAll('input[type="radio"][data-mc-single]'));
  const syncMcSingle = () => {
    if (!mcField || !mcRadios.length) return;
    const sel = mcRadios.find(r=>r.checked);
    mcField.value = sel ? sel.value : '';
  };
  if (mcRadios.length) {
    mcRadios.forEach(r=> r.addEventListener('change', syncMcSingle));
    syncMcSingle();
  }

  // Bike flow client-side visibility for smoother UX
  function val(name){
    const nodes = form.querySelectorAll('input[name="'+name+'"]');
    for (const n of nodes) { if ((n.type==='radio'||n.type==='checkbox') && n.checked) return n.value; }
    return '';
  }
  function show(el, on){ if (!el) return; el.style.display = on ? 'block' : 'none'; }
  function updateBike(){
    const q2 = document.getElementById('bikeQ2');
    const block = document.getElementById('bikeArticle6');
    const q3b = document.getElementById('bikeQ3B');
    const q5 = document.getElementById('bikeQ5');
    const q6 = document.getElementById('bikeQ6');
    const was = val('bike_was_present') || '';
    const cause = val('bike_caused_issue') || '';
    const resMade = val('bike_reservation_made') || '';
    const denied = val('bike_denied_boarding') || '';
    const reasonProv = val('bike_refusal_reason_provided') || '';
    show(q2, was==='yes');
    show(block, was==='yes' && cause==='yes');
    show(q3b, resMade==='no');
    show(q5, denied==='yes');
    show(q6, denied==='yes' && reasonProv==='yes');
  }
  form.addEventListener('change', (e)=>{
    if (!e.target) return;
    const nm = e.target.name||'';
    if (nm.startsWith('bike_')) { updateBike(); }
  }, { passive:true });
  // Initial
  updateBike();

  // Klasse & reservation: intelligent trinvis visning
  function updateClassUI(){
    const q4 = document.getElementById('classQ4');
    const selRes = form.querySelector('select[name="berth_seat_type"]');
    const resVal = (selRes && selRes.value) ? selRes.value : '';
    const needsDelivery = ['seat','couchette','sleeper'].includes(resVal||'');
    if (q4) q4.style.display = needsDelivery ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && (e.target.name||'')) || '';
    if (nm === 'fare_class_purchased' || nm === 'berth_seat_type') updateClassUI();
  }, { passive:true });
  updateClassUI();

  // Pricing block: show Q2 only after user changes Q1 (fare_flex_type)
  (function(){
    const sel = form.querySelector('select[name="fare_flex_type"]');
    const q2 = document.getElementById('pricingQ2');
    if (!sel || !q2) return;
    const initial = sel.value || '';
    let shown = false;
    function maybeShowQ2(){
      if (!shown && (sel.value||'') !== initial) {
        q2.style.display = 'block';
        shown = true;
      }
    }
    sel.addEventListener('change', maybeShowQ2, { passive: true });
  })();

  // Debug checkbox toggles ?debug=1 in the URL for more hooks info
  const dbg = document.getElementById('toggleDebugChk');
  if (dbg) {
    dbg.addEventListener('change', ()=>{
      const url = new URL(window.location.href);
      if (dbg.checked) { url.searchParams.set('debug','1'); }
      else { url.searchParams.delete('debug'); }
      // Preserve PRG mode flag in URL
      try {
        const prgNow = new URLSearchParams(window.location.search).get('prg') || new URLSearchParams(window.location.search).get('pgr');
        if (prgNow) { url.searchParams.set('prg','1'); }
      } catch(e) {}
      window.location.assign(url.toString());
    });
  }
  // Handle ticket removal without nested forms (keeps main form valid)
  const rmBtns = Array.from(document.querySelectorAll('.remove-ticket-btn'));
  if (rmBtns.length) {
    rmBtns.forEach(btn => {
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        const v = btn.getAttribute('data-file') || '';
        if (!v) return;
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'remove_ticket';
        hid.value = v;
        form.appendChild(hid);
        form.submit();
      });
    });
  }

  // Season pass file removal
  const spRmBtns = Array.from(document.querySelectorAll('.remove-seasonpass-btn'));
  if (spRmBtns.length) {
    spRmBtns.forEach(btn => {
      btn.addEventListener('click', (e)=>{
        e.preventDefault();
        const v = btn.getAttribute('data-file') || '';
        if (!v) return;
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'remove_season_pass_file';
        hid.value = v;
        form.appendChild(hid);
        form.submit();
      });
    });
  }

  // Season pass upload buttons
  (function(){
    const add = document.getElementById('addSeasonPassFilesBtn');
    const clear = document.getElementById('clearSeasonPassFilesBtn');
    const inp = document.getElementById('seasonPassFilesInput');
    if (!add || !inp) return;
    add.addEventListener('click', (e)=>{ e.preventDefault(); inp.click(); });
    inp.addEventListener('change', ()=>{
      if (inp.files && inp.files.length) {
        setTimeout(()=>form.submit(), 0);
      }
    });
    if (clear) {
      clear.addEventListener('click', (e)=>{
        e.preventDefault();
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'clear_season_pass_files';
        hid.value = '1';
        form.appendChild(hid);
        setTimeout(()=>form.submit(), 0);
      });
    }
  })();

  // PMR client-side visibility
  function valRadio(name){
    const els = form.querySelectorAll('input[name="'+name+'"]');
    for (const el of els) { if (el.checked) return el.value; }
    return '';
  }
  function updatePMR(){
    const qBooked = document.getElementById('pmrQBooked');
    const qDelivered = document.getElementById('pmrQDelivered');
    const qProm = document.getElementById('pmrQPromised');
    const qDet = document.getElementById('pmrQDetails');
    const u = valRadio('pmr_user');
    const b = valRadio('pmr_booked');
    const p = valRadio('pmr_promised_missing');
    if (qBooked) qBooked.style.display = (u==='yes') ? 'block' : 'none';
    if (qDelivered) qDelivered.style.display = (u==='yes' && b!=='no') ? 'block' : 'none';
    if (qProm) qProm.style.display = (u==='yes') ? 'block' : 'none';
    if (qDet) qDet.style.display = (p==='yes') ? 'block' : 'none';
  }
  form.addEventListener('change', (e)=>{
    const nm = (e.target && e.target.name) || '';
    if (nm.startsWith('pmr_')) updatePMR();
    if (nm === 'preinformed_disruption' || nm === 'preinfo_channel' || nm === 'realtime_info_seen') updateDisruption();
  }, { passive:true });
  updatePMR();

  // Disruption client-side visibility + simple required checks for Continue
  const art10Applies = true;
  function valRadio2(name){ const els = form.querySelectorAll('input[name="'+name+'"]'); for (const el of els) { if (el.checked) return el.value; } return ''; }
  function updateDisruption(){
    const q2 = document.getElementById('disQ2');
    const q3 = document.getElementById('disQ3');
    const q1 = valRadio2('preinformed_disruption');
    if (q2) q2.style.display = (q1==='yes') ? 'block' : 'none';
    if (q3) q3.style.display = (q1==='yes' && art10Applies) ? 'block' : 'none';
  }
  updateDisruption();

  window.__modeContractShowAll = false;
  const modeContractEditBtn = document.getElementById('modeContractEditBtn');
  const modeContractShowAllBtn = document.getElementById('modeContractShowAllBtn');
  if (modeContractEditBtn) {
    modeContractEditBtn.addEventListener('click', function(){
      const qs = document.getElementById('modeContractQuestions');
      if (qs) { qs.style.display = 'block'; }
      if (modeContractShowAllBtn && qs && qs.dataset.uploadEdit === '1') {
        modeContractShowAllBtn.style.display = '';
      }
      window.__modeContractShowAll = false;
      updateModeContractQuestionVisibility();
      const firstField = qs ? qs.querySelector('select, input') : null;
      if (firstField && typeof firstField.scrollIntoView === 'function') {
        firstField.scrollIntoView({ behavior:'smooth', block:'center' });
      }
    });
  }
  if (modeContractShowAllBtn) {
    modeContractShowAllBtn.addEventListener('click', function(){
      window.__modeContractShowAll = true;
      updateModeContractQuestionVisibility();
      modeContractShowAllBtn.style.display = 'none';
    });
  }
  // Only enforce disruption answers (Art. 9(1)) when that article is actually applicable/visible
  const art9_1_enforced = false;
  const disruptionBlock = document.getElementById('disruptionBlock');
  const contBtn = form.querySelector('button[name="continue"]');
  if (contBtn) {
    contBtn.addEventListener('click', (e)=>{
      // Skip gating entirely when Art. 9(1) is exempt/hidden
      if (!art9_1_enforced || !disruptionBlock) { return; }
      const err = document.getElementById('disruptionReqError');
      if (err) err.style.display = 'none';
      const q1 = valRadio2('preinformed_disruption');
      if (!q1) {
        e.preventDefault();
        if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        return;
      }
      if (q1 === 'yes') {
        const sel = form.querySelector('select[name="preinfo_channel"]');
        if (sel && !sel.value) {
          e.preventDefault();
          if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
          return;
        }
        if (art10Applies) {
          const q3 = valRadio2('realtime_info_seen');
          if (!q3) {
            e.preventDefault();
            if (err) { err.style.display = 'block'; err.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return;
          }
        }
      }
    });
  }
})();
</script>

