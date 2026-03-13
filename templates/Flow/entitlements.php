<?php
/** @var \App\View\AppView $this */
$compute = $compute ?? [];
$form = $form ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$groupedTickets = $groupedTickets ?? [];
$hasTickets = !empty($groupedTickets) || !empty($form['_ticketFilename']) || !empty($meta['_multi_tickets']);
$seasonMeta0 = (array)($meta['season_pass'] ?? []);
$fftSeason0 = strtolower((string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? '')));
$hasSeason0 = array_key_exists('has', $seasonMeta0) ? (bool)$seasonMeta0['has'] : ($fftSeason0 === 'pass');
$ticketMode = (string)($form['ticket_upload_mode'] ?? '');
if (!in_array($ticketMode, ['ticket','ticketless','seasonpass'], true)) { $ticketMode = ''; }
if ($ticketMode === '') { $ticketMode = $hasSeason0 ? 'seasonpass' : 'ticket'; }
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($multimodal['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail'))));
if (!in_array($transportMode, ['rail','ferry','bus','air'], true)) { $transportMode = 'rail'; }
$isFerry = $transportMode === 'ferry';
$isBus = $transportMode === 'bus';
$isAir = $transportMode === 'air';
$isRail = $transportMode === 'rail';
$seasonSupported = in_array($transportMode, ['rail', 'ferry'], true);
if (!$seasonSupported && $ticketMode === 'seasonpass') { $ticketMode = 'ticket'; }
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$modeContract = $isBus ? (array)($multimodal['bus_contract'] ?? []) : (($isAir ? (array)($multimodal['air_contract'] ?? []) : []));
$claimDirection = (array)($multimodal['claim_direction'] ?? []);
$isPreview = !empty($flowPreview);
?>
<?php echo $this->Html->css('flow-entitlements', ['block' => true]); ?>
<style>
  /* Skjul PMR/cykel i Trin 2 – håndteres i Trin 3 */
  #pmrFlowCard, #bikeFlowCard { display:none !important; }
  .fe-wrapper { max-width: 1200px; margin: 0 auto; }
  .fe-wide { width: 100%; }
  /* Ticketless station autocomplete */
  #ticketlessCard label { position: relative; }
  #ticketlessCard .station-suggest {
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
  #ticketlessCard .station-suggest button {
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    padding: 8px 10px;
    cursor: pointer;
    font-size: 14px;
    color: #111 !important;
  }
  #ticketlessCard .station-suggest button:hover { background: #f6f6f6; color: #111 !important; }
  #ticketlessCard .station-suggest button:active { color: #111 !important; }
  #ticketlessCard .station-suggest button:focus { outline: none; background: #f1f3f5; color: #111 !important; }
  #ticketlessCard .station-suggest .muted { color: #666; font-size: 12px; }
</style>
<div class="fe-header">
  <div class="fe-step">Trin 2</div>
  <h1 class="fe-title">Billet (upload eller ticketless)</h1>
  <p class="fe-sub">Upload billetter eller udfyld minimum uden billet. Sidepanelet viser løbende dine rettigheder.</p>
</div>
<?php
  // UI banners derived from exemption profile (global notices)
  $uiBanners = (array)($profile['ui_banners'] ?? []);
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
  // Offline operator/product catalog for ticketless suggestions (no tokens / no external calls).
  $opCatalog = new \App\Service\OperatorCatalog();
  $opsByCountry = (array)$opCatalog->getOperators();
  $productsByOperator = (array)$opCatalog->getProducts();
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
	?>
<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['type' => 'file', 'id' => 'entitlementsForm']) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
<div class="fe-wrapper">
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;">
    <strong><?= $isAir ? 'Har du en booking eller billet du kan uploade?' : ($isFerry ? 'Har du en booking eller færgebillet du kan uploade?' : ($isBus ? 'Har du en busbillet eller booking du kan uploade?' : 'Har du en billet du kan uploade?')) ?></strong>
    <div class="small muted" style="margin-top:6px;"><?= $isRail ? 'Vælg ticketless hvis du vil lave et hurtigt estimat uden upload. Du kan altid uploade senere.' : 'Vælg ticketless hvis du vil fortsætte uden upload. Du kan altid tilføje booking eller dokumentation senere.' ?></div>
    <div class="small" style="margin-top:8px;">
      <label class="mr8"><input type="radio" name="ticket_upload_mode" value="ticket" <?= $ticketMode==='ticket'?'checked':'' ?> /> <?= $isAir ? 'Ja, jeg kan uploade booking/billet' : ($isFerry ? 'Ja, jeg kan uploade booking/færgebillet' : ($isBus ? 'Ja, jeg kan uploade booking/busbillet' : 'Ja, jeg kan uploade billet')) ?></label>
      <label class="mr8"><input type="radio" name="ticket_upload_mode" value="ticketless" <?= $ticketMode==='ticketless'?'checked':'' ?> /> Nej, ticketless</label>
      <label class="mr8" id="seasonPassOptionWrap" style="<?= $seasonSupported ? '' : 'display:none;' ?>"><input type="radio" name="ticket_upload_mode" value="seasonpass" <?= $ticketMode==='seasonpass'?'checked':'' ?> /> Jeg rejser på pendler-/periodekort</label>
    </div>
  </div>

  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;">
    <strong>Transportform</strong>
    <div class="small muted" style="margin-top:6px;">Vælg den transporttype hvor problemet opstod eller forventes at opstå. Det styrer scope, ansvar og hvilket regelsæt vi bruger senere i flowet.</div>
    <div class="small" style="margin-top:8px;">
      <label class="mr8"><input type="radio" name="transport_mode" value="rail" <?= $transportMode==='rail'?'checked':'' ?> /> Tog</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="ferry" <?= $transportMode==='ferry'?'checked':'' ?> /> Færge</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="bus" <?= $transportMode==='bus'?'checked':'' ?> /> Bus</label>
      <label class="mr8"><input type="radio" name="transport_mode" value="air" <?= $transportMode==='air'?'checked':'' ?> /> Fly</label>
    </div>
  </div>

  <?php if (!$isRail): ?>
  <?php
    $sellerChannelMode = (string)($form['seller_channel'] ?? 'operator');
    $sameBookingMode = (string)($form['shared_pnr_scope'] ?? 'yes');
    $sameTransactionMode = (string)($form['same_transaction'] ?? 'yes');
    $separateNoticeMode = (string)($form['separate_contract_notice'] ?? 'no');
    $modeIncidentSegmentTop = (string)($form['incident_segment_mode'] ?? ($isFerry ? ($ferryContract['rights_module'] ?? 'ferry') : ($modeContract['rights_module'] ?? $transportMode)));
    $modeProblemOperatorTop = (string)($form['incident_segment_operator'] ?? ($isFerry ? ($ferryContract['primary_claim_party_name'] ?? '') : ($modeContract['primary_claim_party_name'] ?? '')));
  ?>
  <div class="card" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px;">
    <strong>Kontrakt og ansvar</strong>
    <div class="small muted" style="margin-top:6px;">Fælles multimodal kontraktblok. Bruges til at afgøre claim-kanal, samlet booking vs. separate kontrakter og hvilket segment der er ramt.</div>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
      <label>Hvem solgte hele rejsen?
        <select name="seller_channel">
          <option value="operator" <?= $sellerChannelMode==='operator'?'selected':'' ?>>Operatør / carrier</option>
          <option value="retailer" <?= $sellerChannelMode==='retailer'?'selected':'' ?>>Billetudsteder / platform</option>
          <option value="agency" <?= $sellerChannelMode==='agency'?'selected':'' ?>>Rejsebureau</option>
          <option value="tour_operator" <?= $sellerChannelMode==='tour_operator'?'selected':'' ?>>Tour operator</option>
        </select>
      </label>
      <label>Samme booking / reference?
        <select name="shared_pnr_scope">
          <option value="yes" <?= $sameBookingMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameBookingMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Købt i én handelstransaktion?
        <select name="same_transaction">
          <option value="yes" <?= $sameTransactionMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameTransactionMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <?php if ($isAir): ?>
      <?php $samePnrMode = (string)($form['same_pnr'] ?? 'yes'); ?>
      <label>Same PNR?
        <select name="same_pnr">
          <option value="yes" <?= $samePnrMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $samePnrMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <?php $sameBookingReferenceMode = (string)($form['same_booking_reference'] ?? 'yes'); ?>
      <label>Same bookingreference?
        <select name="same_booking_reference">
          <option value="yes" <?= $sameBookingReferenceMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameBookingReferenceMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <?php $sameEticketMode = (string)($form['same_eticket'] ?? 'yes'); ?>
      <label>Same e-ticket?
        <select name="same_eticket">
          <option value="yes" <?= $sameEticketMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameEticketMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <?php $selfTransferNoticeMode = (string)($form['self_transfer_notice'] ?? 'no'); ?>
      <label>Self-transfer oplyst før køb?
        <select name="self_transfer_notice">
          <option value="yes" <?= $selfTransferNoticeMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $selfTransferNoticeMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <?php endif; ?>
      <label>Separate kontrakter oplyst?
        <select name="separate_contract_notice">
          <option value="yes" <?= $separateNoticeMode==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $separateNoticeMode==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Ramt segment
        <select name="incident_segment_mode">
          <option value="<?= h($transportMode) ?>" <?= $modeIncidentSegmentTop===$transportMode?'selected':'' ?>><?= $isFerry ? 'Færge' : ($isBus ? 'Bus' : 'Fly') ?></option>
          <option value="rail" <?= $modeIncidentSegmentTop==='rail'?'selected':'' ?>>Tog</option>
          <option value="ferry" <?= $modeIncidentSegmentTop==='ferry'?'selected':'' ?>>Færge</option>
          <option value="bus" <?= $modeIncidentSegmentTop==='bus'?'selected':'' ?>>Bus</option>
          <option value="air" <?= $modeIncidentSegmentTop==='air'?'selected':'' ?>>Fly</option>
        </select>
      </label>
      <label>Problem-operatør (valgfri)
        <input type="text" name="incident_segment_operator" value="<?= h($modeProblemOperatorTop) ?>" placeholder="<?= $isFerry ? 'Fx Scandlines' : ($isBus ? 'Fx FlixBus' : 'Fx SAS') ?>" />
      </label>
    </div>
    <?php if (!empty($ferryContract) || !empty($modeContract) || !empty($claimDirection)): ?>
      <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
        <div><strong>Foreløbig vurdering</strong></div>
        <div>Kontraktstruktur: <?= h((string)($multimodal['contract_meta']['contract_topology'] ?? 'Auto')) ?></div>
        <div>Claim-kanal: <?= h((string)(($isFerry ? ($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? null)) : ($modeContract['primary_claim_party_name'] ?? ($modeContract['primary_claim_party'] ?? null))) ?? 'manual_review')) ?></div>
        <div>Rettighedsmodul: <?= h((string)(($isFerry ? ($ferryContract['rights_module'] ?? 'ferry') : ($modeContract['rights_module'] ?? ($claimDirection['rights_module'] ?? $transportMode))))) ?></div>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card" id="modeContractCard" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px; display:none;">
    <strong><?= $isAir ? 'Fly: booking og ansvar' : 'Bus: booking og ansvar' ?></strong>
    <div class="small muted" style="margin-top:6px;"><?= $isAir ? 'Her afklarer vi protected connection vs. self-transfer, claim-kanal og EU-scope for flysagen.' : 'Her afklarer vi regular service-scope, claim-kanal og hvilket busmodul der skal bruges senere.' ?></div>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
      <?php if ($isAir): ?>
      <label>Afgangslufthavn i EU?
        <?php $airDepEu = (string)($form['departure_airport_in_eu'] ?? 'yes'); ?>
        <select name="departure_airport_in_eu">
          <option value="yes" <?= $airDepEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $airDepEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Ankomstlufthavn i EU?
        <?php $airArrEu = (string)($form['arrival_airport_in_eu'] ?? 'yes'); ?>
        <select name="arrival_airport_in_eu">
          <option value="yes" <?= $airArrEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $airArrEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Operating carrier er EU-operatoer?
        <?php $opCarrierEu = (string)($form['operating_carrier_is_eu'] ?? 'yes'); ?>
        <select name="operating_carrier_is_eu">
          <option value="yes" <?= $opCarrierEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $opCarrierEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Marketing carrier er EU-operatoer?
        <?php $mkCarrierEu = (string)($form['marketing_carrier_is_eu'] ?? 'yes'); ?>
        <select name="marketing_carrier_is_eu">
          <option value="yes" <?= $mkCarrierEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $mkCarrierEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Forbindelsestype
        <?php $airConnectionType = (string)($form['air_connection_type'] ?? ($modeContract['air_connection_type'] ?? '')); ?>
        <select name="air_connection_type">
          <option value="">Auto / resolver</option>
          <option value="single_flight" <?= $airConnectionType==='single_flight'?'selected':'' ?>>Enkelt flight</option>
          <option value="protected_connection" <?= $airConnectionType==='protected_connection'?'selected':'' ?>>Protected connection</option>
          <option value="self_transfer" <?= $airConnectionType==='self_transfer'?'selected':'' ?>>Self-transfer</option>
        </select>
      </label>
      <label>Same PNR?
        <?php $samePnr = (string)($form['same_pnr'] ?? 'yes'); ?>
        <select name="same_pnr">
          <option value="yes" <?= $samePnr==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $samePnr==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Same bookingreference?
        <?php $sameBooking = (string)($form['same_booking_reference'] ?? 'yes'); ?>
        <select name="same_booking_reference">
          <option value="yes" <?= $sameBooking==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameBooking==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Same e-ticket?
        <?php $sameEticket = (string)($form['same_eticket'] ?? 'yes'); ?>
        <select name="same_eticket">
          <option value="yes" <?= $sameEticket==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $sameEticket==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Self-transfer oplyst foer koeb?
        <?php $selfTransferNotice = (string)($form['self_transfer_notice'] ?? 'no'); ?>
        <select name="self_transfer_notice">
          <option value="yes" <?= $selfTransferNotice==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $selfTransferNotice==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Marketing carrier
        <input type="text" name="marketing_carrier" value="<?= h((string)($form['marketing_carrier'] ?? ($modeContract['marketing_carrier'] ?? ''))) ?>" placeholder="Fx SAS" />
      </label>
      <label>Operating carrier
        <input type="text" name="operating_carrier" value="<?= h((string)($form['operating_carrier'] ?? ($modeContract['operating_carrier'] ?? ''))) ?>" placeholder="Fx CityJet" />
      </label>
      <?php elseif ($isBus): ?>
      <label>Regular service?
        <?php $busRegular = (string)($form['bus_regular_service'] ?? 'yes'); ?>
        <select name="bus_regular_service">
          <option value="yes" <?= $busRegular==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $busRegular==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Boarding i EU?
        <?php $boardingInEu = (string)($form['boarding_in_eu'] ?? 'yes'); ?>
        <select name="boarding_in_eu">
          <option value="yes" <?= $boardingInEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $boardingInEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Alighting i EU?
        <?php $alightingInEu = (string)($form['alighting_in_eu'] ?? 'yes'); ?>
        <select name="alighting_in_eu">
          <option value="yes" <?= $alightingInEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $alightingInEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Fra terminal?
        <?php $busTerminal = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
        <select name="departure_from_terminal">
          <option value="yes" <?= $busTerminal==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $busTerminal==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Planlagt distance (km)
        <input type="number" name="scheduled_distance_km" min="0" step="1" value="<?= h((string)($form['scheduled_distance_km'] ?? '')) ?>" placeholder="320" />
      </label>
      <?php endif; ?>
      <label>Ramt segment
        <?php $modeIncidentSegment = (string)($form['incident_segment_mode'] ?? ($modeContract['rights_module'] ?? $transportMode)); ?>
        <select name="incident_segment_mode">
          <option value="<?= h($transportMode) ?>" <?= $modeIncidentSegment===$transportMode?'selected':'' ?>><?= $isAir ? 'Fly' : 'Bus' ?></option>
          <option value="rail" <?= $modeIncidentSegment==='rail'?'selected':'' ?>>Tog</option>
          <option value="ferry" <?= $modeIncidentSegment==='ferry'?'selected':'' ?>>Faerge</option>
          <option value="bus" <?= $modeIncidentSegment==='bus'?'selected':'' ?>>Bus</option>
          <option value="air" <?= $modeIncidentSegment==='air'?'selected':'' ?>>Fly</option>
        </select>
      </label>
      <label>Problem-operatoer (valgfri)
        <input type="text" name="incident_segment_operator" value="<?= h((string)($form['incident_segment_operator'] ?? ($modeContract['primary_claim_party_name'] ?? ''))) ?>" placeholder="<?= $isAir ? 'Fx SAS' : 'Fx FlixBus' ?>" />
      </label>
    </div>
    <?php if (!empty($modeContract) || !empty($claimDirection)): ?>
      <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
        <div><strong>Foreloebig vurdering</strong></div>
        <?php if ($isAir): ?>
          <div>Scope: <?= array_key_exists('regulation_applies', $airScope) ? (!empty($airScope['regulation_applies']) ? 'In scope' : 'Out of scope') : 'Auto' ?><?= !empty($airScope['scope_basis']) ? ' - ' . h((string)$airScope['scope_basis']) : '' ?></div>
          <div>Forbindelse: <?= h((string)($modeContract['air_connection_type'] ?? 'Auto')) ?></div>
        <?php elseif ($isBus): ?>
          <div>Scope: <?= array_key_exists('regulation_applies', $busScope) ? (!empty($busScope['regulation_applies']) ? 'In scope' : 'Out of scope') : 'Auto' ?><?= !empty($busScope['scope_basis']) ? ' - ' . h((string)$busScope['scope_basis']) : '' ?></div>
        <?php endif; ?>
        <div>Kontraktstruktur: <?= h((string)($multimodal['contract_meta']['contract_topology'] ?? 'Auto')) ?></div>
        <div>Claim-kanal: <?= h((string)($modeContract['primary_claim_party_name'] ?? ($modeContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
        <div>Rettighedsmodul: <?= h((string)($modeContract['rights_module'] ?? ($claimDirection['rights_module'] ?? $transportMode))) ?></div>
        <?php if (!empty($claimDirection['recommended_documents'])): ?>
          <div>Anbefalet dokumentation: <?= h(implode(', ', (array)$claimDirection['recommended_documents'])) ?></div>
        <?php endif; ?>
        <div class="muted" style="margin-top:6px;">Claim-kanal og rettighedsmodul bruges senere til at afgore hvem sagen rettes mod, og hvilke regler der vurderes efter.</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" id="ferryScopeCard" style="padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; margin-bottom:12px; display:none;">
    <strong>Faerge: scope og ansvar</strong>
    <div class="small muted" style="margin-top:6px;">Her afklarer vi om faergeforordningen finder anvendelse, om problemet ligger paa faergedelen, og hvem claimet som udgangspunkt skal rettes mod. Handicap/PMR kommer senere.</div>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
      <label>Servicetype
        <?php $serviceType = (string)($form['service_type'] ?? 'passenger_service'); ?>
        <select name="service_type">
          <option value="passenger_service" <?= $serviceType==='passenger_service'?'selected':'' ?>>Passenger service</option>
          <option value="cruise" <?= $serviceType==='cruise'?'selected':'' ?>>Cruise</option>
        </select>
      </label>
      <label>Ramt segment
        <?php $incidentSegmentMode = (string)($form['incident_segment_mode'] ?? ($ferryContract['rights_module'] ?? 'ferry')); ?>
        <select name="incident_segment_mode">
          <option value="ferry" <?= $incidentSegmentMode==='ferry'?'selected':'' ?>>Faerge</option>
          <option value="rail" <?= $incidentSegmentMode==='rail'?'selected':'' ?>>Tog</option>
          <option value="bus" <?= $incidentSegmentMode==='bus'?'selected':'' ?>>Bus</option>
          <option value="air" <?= $incidentSegmentMode==='air'?'selected':'' ?>>Fly</option>
        </select>
      </label>
      <label>Problem-operatoer (valgfri)
        <input type="text" name="incident_segment_operator" value="<?= h((string)($form['incident_segment_operator'] ?? ($ferryContract['primary_claim_party_name'] ?? ''))) ?>" placeholder="Fx Scandlines" />
      </label>
      <label>Fra havneterminal?
        <?php $depTerminal = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
        <select name="departure_from_terminal">
          <option value="yes" <?= $depTerminal==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $depTerminal==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Afgang i EU?
        <?php $depEu = (string)($form['departure_port_in_eu'] ?? 'yes'); ?>
        <select name="departure_port_in_eu">
          <option value="yes" <?= $depEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $depEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Ankomst i EU?
        <?php $arrEu = (string)($form['arrival_port_in_eu'] ?? 'yes'); ?>
        <select name="arrival_port_in_eu">
          <option value="yes" <?= $arrEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $arrEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Carrier er EU-operatoer?
        <?php $carrierEu = (string)($form['carrier_is_eu'] ?? 'yes'); ?>
        <select name="carrier_is_eu">
          <option value="yes" <?= $carrierEu==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $carrierEu==='no'?'selected':'' ?>>Nej</option>
        </select>
      </label>
      <label>Ruteafstand i meter (valgfri)
        <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
      </label>
      <label>Passagerkapacitet (valgfri)
        <input type="number" name="vessel_passenger_capacity" min="0" step="1" value="<?= h((string)($form['vessel_passenger_capacity'] ?? '')) ?>" placeholder="200" />
      </label>
      <label>Operationel besaetning (valgfri)
        <input type="number" name="vessel_operational_crew" min="0" step="1" value="<?= h((string)($form['vessel_operational_crew'] ?? '')) ?>" placeholder="12" />
      </label>
    </div>
    <?php if (!empty($ferryScope) || !empty($ferryContract)): ?>
      <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
        <div><strong>Foreloebig vurdering</strong></div>
        <div>Scope: <?= !empty($ferryScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?><?= !empty($ferryScope['scope_basis']) ? (' â€“ ' . h((string)$ferryScope['scope_basis'])) : '' ?></div>
        <div>Kontraktstruktur: <?= h((string)($multimodal['contract_meta']['contract_topology'] ?? 'Auto')) ?></div>
        <div>Claim-kanal: <?= h((string)($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
        <div>Rettighedsmodul: <?= h((string)($ferryContract['rights_module'] ?? 'ferry')) ?></div>
        <div class="muted" style="margin-top:6px;">Hvis kontrakten er samlet, kan claim-kanalen vaere rederiet eller saelgeren, selv om selve rettighedsmodulet ligger paa den ramte transportdel.</div>
      </div>
    <?php endif; ?>
  </div>

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
          <input type="text" name="season_pass_operator" list="operatorSuggestions" value="<?= h($seasonOp) ?>" placeholder="<?= $isFerry ? 'Fx Scandlines / ForSea' : 'DSB / DB / SNCF ?' ?>" />
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
    <div class="section-title"><?= $isAir ? 'Booking / billetter' : ($isFerry ? 'Booking / fÃ¦rgebilletter' : ($isBus ? 'Booking / billetter' : 'Billetter')) ?></div>
  <div id="uploadDropzone" class="upload-dropzone" tabindex="0">
      <div class="upload-title">Slip filer her eller klik for at tilfÃ¸je</div>
      <div class="small muted" style="margin-top:6px;">UnderstÃ¸tter PDF, JPG, PNG, PKPASS, TXT</div>
      <div class="upload-actions">
        <button type="button" id="addFilesBtn" class="button">TilfÃ¸j filer</button>
        <button type="button" id="clearFilesBtn" class="button button-outline">Fjern alle</button>
      </div>
    </div>
    <!-- Hidden real inputs wired by JS -->
    <input type="file" id="ticketSingle" name="ticket_upload" accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <input type="file" id="ticketMulti" name="multi_ticket_upload[]" multiple accept=".pdf,.png,.jpg,.jpeg,.pkpass,.txt,image/*,application/pdf" style="display:none;" />
    <ul id="selectedFilesList" class="small file-list" style="list-style:none; padding-left:0; margin:12px 0 0 0;"></ul>
  </div>

  <div class="card" id="ticketlessCard" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;<?= $ticketMode==='ticketless'?'':' display:none;' ?>">
    <div class="section-title"><?= $isFerry ? 'Ticketless færge' : ($isBus ? 'Ticketless bus' : ($isAir ? 'Ticketless fly' : 'Ticketless (minimum)')) ?></div>
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
      <label>OperatÃ¸r (valgfri)
        <input type="text" name="operator" list="operatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx DSB, DB, SJ" />
      </label>
      <label>Produkt (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx IC, ICE" />
      </label>
      <label>Koebt klasse (valgfri)
        <?php $fc = strtolower((string)($form['fare_class_purchased'] ?? ($meta['fare_class_purchased'] ?? ($meta['_auto']['fare_class_purchased']['value'] ?? '')))); ?>
        <select name="fare_class_purchased">
          <option value="">Vaelg...</option>
          <option value="1" <?= $fc==='1'?'selected':'' ?>>1. klasse</option>
          <option value="2" <?= $fc==='2'?'selected':'' ?>>2. klasse</option>
          <option value="other" <?= $fc==='other'?'selected':'' ?>>Andet</option>
        </select>
      </label>
      <label>Plads/komfort (valgfri)
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
      <label>Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label>Planlagt ankomsttid (valgfri)
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
        <input type="text" name="operator" list="operatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx Scandlines" />
      </label>
      <label>Produkt / overfart (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx Helsingør-Helsingborg" />
      </label>
      <label>Afgangshavn
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingør" />
      </label>
      <label>Ankomsthavn
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Helsingborg" />
      </label>
      <label>Bookingreference (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx ABC123" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label>Planlagt ankomsttid (valgfri)
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
        <input type="text" name="operator" list="operatorSuggestions" value="<?= h($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')) ?>" placeholder="Fx FlixBus" />
      </label>
      <label>Produkt (valgfri)
        <input type="text" name="operator_product" list="productSuggestions" value="<?= h($form['operator_product'] ?? ($meta['_auto']['operator_product']['value'] ?? '')) ?>" placeholder="Fx Ekspres" />
      </label>
      <label>Afgangssted / terminal
        <input type="text" name="dep_station" value="<?= h($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Odense station" />
      </label>
      <label>Ankomststed / terminal
        <input type="text" name="arr_station" value="<?= h($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')) ?>" autocomplete="off" placeholder="Fx Aarhus busterminal" />
      </label>
      <label>Bookingreference (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx BUS123" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label>Planlagt ankomsttid (valgfri)
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
        <input type="text" name="marketing_carrier" value="<?= h((string)($form['marketing_carrier'] ?? ($modeContract['marketing_carrier'] ?? ''))) ?>" placeholder="Fx SAS" />
      </label>
      <label>Operating carrier
        <input type="text" name="operating_carrier" value="<?= h((string)($form['operating_carrier'] ?? ($modeContract['operating_carrier'] ?? ''))) ?>" placeholder="Fx CityJet" />
      </label>
      <label>Bookingreference / PNR (valgfri)
        <input type="text" name="ticket_no" value="<?= h((string)($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))) ?>" placeholder="Fx X7YZ12" />
      </label>
      <label>Planlagt afgangsdato
        <input type="date" name="dep_date" value="<?= h($form['dep_date'] ?? ($meta['_auto']['dep_date']['value'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
      </label>
      <label>Planlagt afgangstid (valgfri)
        <input type="time" name="dep_time" value="<?= h($form['dep_time'] ?? ($meta['_auto']['dep_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <label>Planlagt ankomsttid (valgfri)
        <input type="time" name="arr_time" value="<?= h($form['arr_time'] ?? ($meta['_auto']['arr_time']['value'] ?? '')) ?>" placeholder="HH:MM" step="60" />
      </label>
      <?php endif; ?>
    </div>

    <?php if (!$isRail): ?>
      <div class="small" style="margin-top:14px; font-weight:600;">2. Scopefelter</div>
      <div class="small muted" style="margin-top:4px;">Bruges til at afgøre om forordningen gælder og hvilke undtagelser der kan være relevante.</div>
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
        <?php if ($isFerry): ?>
          <label>Afgangshavn i EU?
            <?php $depPortEu = (string)($form['departure_port_in_eu'] ?? 'yes'); ?>
            <select name="departure_port_in_eu">
              <option value="yes" <?= $depPortEu==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $depPortEu==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Ankomsthavn i EU?
            <?php $arrPortEu = (string)($form['arrival_port_in_eu'] ?? 'yes'); ?>
            <select name="arrival_port_in_eu">
              <option value="yes" <?= $arrPortEu==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $arrPortEu==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Carrier er EU-operatør?
            <?php $carrierEuTl = (string)($form['carrier_is_eu'] ?? 'yes'); ?>
            <select name="carrier_is_eu">
              <option value="yes" <?= $carrierEuTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $carrierEuTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Fra havneterminal?
            <?php $depTerminalTl = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
            <select name="departure_from_terminal">
              <option value="yes" <?= $depTerminalTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $depTerminalTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Ruteafstand i meter (valgfri)
            <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
          </label>
          <label>Passagerkapacitet (valgfri)
            <input type="number" name="vessel_passenger_capacity" min="0" step="1" value="<?= h((string)($form['vessel_passenger_capacity'] ?? '')) ?>" placeholder="200" />
          </label>
          <label>Operationel besætning (valgfri)
            <input type="number" name="vessel_operational_crew" min="0" step="1" value="<?= h((string)($form['vessel_operational_crew'] ?? '')) ?>" placeholder="12" />
          </label>
        <?php elseif ($isBus): ?>
          <?php $busRegularTl = (string)($form['bus_regular_service'] ?? 'yes'); ?>
          <label>Regular service?
            <select name="bus_regular_service">
              <option value="yes" <?= $busRegularTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busRegularTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $busTerminalTl = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
          <label>Fra terminal?
            <select name="departure_from_terminal">
              <option value="yes" <?= $busTerminalTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busTerminalTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $boardingTl = (string)($form['boarding_in_eu'] ?? 'yes'); ?>
          <label>Boarding i EU?
            <select name="boarding_in_eu">
              <option value="yes" <?= $boardingTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $boardingTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $alightingTl = (string)($form['alighting_in_eu'] ?? 'yes'); ?>
          <label>Alighting i EU?
            <select name="alighting_in_eu">
              <option value="yes" <?= $alightingTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $alightingTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Planlagt distance (km)
            <input type="number" name="scheduled_distance_km" min="0" step="1" value="<?= h((string)($form['scheduled_distance_km'] ?? '')) ?>" placeholder="320" />
          </label>
        <?php elseif ($isAir): ?>
          <?php $airDepEuTl = (string)($form['departure_airport_in_eu'] ?? 'yes'); ?>
          <label>Afgangslufthavn i EU?
            <select name="departure_airport_in_eu">
              <option value="yes" <?= $airDepEuTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $airDepEuTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $airArrEuTl = (string)($form['arrival_airport_in_eu'] ?? 'yes'); ?>
          <label>Ankomstlufthavn i EU?
            <select name="arrival_airport_in_eu">
              <option value="yes" <?= $airArrEuTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $airArrEuTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $opCarrierEuTl = (string)($form['operating_carrier_is_eu'] ?? 'yes'); ?>
          <label>Operating carrier er EU-operatør?
            <select name="operating_carrier_is_eu">
              <option value="yes" <?= $opCarrierEuTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $opCarrierEuTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $mkCarrierEuTl = (string)($form['marketing_carrier_is_eu'] ?? 'yes'); ?>
          <label>Marketing carrier er EU-operatør?
            <select name="marketing_carrier_is_eu">
              <option value="yes" <?= $mkCarrierEuTl==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $mkCarrierEuTl==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <datalist id="operatorSuggestions">
      <?php foreach ($allOperators as $opName): ?>
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
    </div>
    </fieldset>
  </div>
  <?php
    // Lightweight status so users see that parsing happened even when no choices are shown
    $segCountTop = isset($meta['_segments_auto']) && is_array($meta['_segments_auto']) ? count((array)$meta['_segments_auto']) : 0;
    $llmFlagRawTop = function_exists('env') ? env('USE_LLM_STRUCTURING') : getenv('USE_LLM_STRUCTURING');
    $llmOnTop = in_array(strtolower((string)$llmFlagRawTop), ['1','true','yes','on'], true);
  ?>
  <div class="small muted" style="margin-top:6px;">
    Auto: <?= (int)$segCountTop ?> segmenter fundet<?= $segCountTop===0 ? (' - LLM-strukturering: ' . ($llmOnTop ? 'til' : 'fra')) : '' ?>.
  </div>

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
            <?php if (!empty($t['passengers'])): ?>
              <details style="margin-top:6px;">
                <summary>RedigÃ©r passagerer</summary>
                <?php foreach ((array)$t['passengers'] as $i => $p): $nameVal = (string)($p['name'] ?? ''); $age = (string)($p['age_category'] ?? 'unknown'); $isC = !empty($p['is_claimant']); ?>
                  <div style="margin-top:6px;">
                    <label>Navn
                      <input type="text" name="passenger_multi[<?= h((string)$t['file']) ?>][<?= (int)$i ?>][name]" value="<?= h($nameVal) ?>" placeholder="Passager #<?= (int)($i+1) ?>" />
                    </label>
                    <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;"><?= h(ucfirst($age)) ?></span>
                    <label class="ml8"><input type="checkbox" name="passenger_multi[<?= h((string)$t['file']) ?>][<?= (int)$i ?>][is_claimant]" value="1" <?= $isC?'checked':'' ?> /> Klager</label>
                  </div>
                <?php endforeach; ?>
              </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
    <div class="small muted" style="margin-top:6px;">Grupperet efter PNR + dato. Du kan uploade flere billetter her.</div>
  </div>
  <?php endif; ?>

  <!-- Rail-kontraktblokken vises længere nede -->

  <button type="button" id="toggleJourneyFields" class="button button-outline" data-has-tickets="<?= $hasTickets ? '1' : '0' ?>" data-transport-mode="<?= h($transportMode) ?>" style="margin-top:12px; margin-bottom:8px;<?= $ticketMode==='ticketless'?' display:none;':'' ?>">Vis/skjul rejsefelter (3.1–3.5)</button>
  <div id="journeyFields" style="display:none;">
  <fieldset id="journeyFieldsFieldset" <?= $ticketMode==='ticketless' ? 'disabled' : '' ?> style="border:0; padding:0; margin:0;">
  <div id="railJourneyFields" style="<?= $isRail ? '' : 'display:none;' ?>">
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>3.1. Name of railway undertaking:</strong>
    <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
      <label>OperatÃ¸r
        <input type="text" name="operator" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" />
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
      <?php
        // Build journey summary + missed-connection station choices with both arrival and next departure times
  $mcChoicesInline = [];
        $journeyRowsInline = [];
        $changeBullets = [];
        // Prefer segments detected for the current upload; if empty, use first non-empty grouped ticket
        $segAutoInline = (array)($meta['_segments_auto'] ?? []);
        if (empty($segAutoInline)) {
          foreach ((array)($groupedTickets ?? []) as $grp) {
            $gs = (array)($grp['segments'] ?? []);
            if (!empty($gs)) { $segAutoInline = $gs; break; }
          }
        }
  $mctEvalRaw = (array)($meta['_mct_eval'] ?? []);
  $norm = function($s){ return trim(mb_strtolower((string)$s, 'UTF-8')); };
  $mctByStation = [];
  foreach ($mctEvalRaw as $ev) { $mctByStation[$norm($ev['station'] ?? '')] = $ev; }
        $fmtDk = function($dateStr){ if(!$dateStr) return ''; $t = @strtotime($dateStr); if(!$t) return ''; return date('d.m.', $t); };
        $toMin = function($t){ if(!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$t, $m)) return null; return (int)$m[1]*60 + (int)$m[2]; };
        try {
          if (!empty($segAutoInline)) {
            $last = count($segAutoInline) - 1;
            foreach ($segAutoInline as $i => $s) {
              $from = trim((string)($s['from'] ?? ''));
              $to = trim((string)($s['to'] ?? ''));
              $dep = trim((string)($s['schedDep'] ?? ''));
              $arr = trim((string)($s['schedArr'] ?? ''));
              $prod = trim((string)($s['trainNo'] ?? ''));
              $depD = (string)($s['depDate'] ?? ($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')));
              $arrD = (string)($s['arrDate'] ?? $depD);
              $journeyRowsInline[] = [
                'leg' => $from . ' ? ' . $to,
                'dep' => ($dep ? ($fmtDk($depD) . ' kl. ' . $dep) : ''),
                'arr' => ($arr ? ($fmtDk($arrD) . ' kl. ' . $arr) : ''),
                'train' => $prod,
                'change' => ($i < $last ? ('Skift i ' . $to) : '(slutstation)'),
              ];
              if ($i < $last) {
                $next = $segAutoInline[$i+1] ?? [];
                $nextDep = trim((string)($next['schedDep'] ?? ''));
                $toName = $to;
                // Build radio labels with both arr and next dep and layover
                $lay = null; $m1 = $toMin($arr); $m2 = $toMin($nextDep);
                if ($m1 !== null && $m2 !== null) { $lay = $m2 - $m1; if ($lay < 0) { $lay += 24*60; } }
                $label = $toName;
                if ($arr || $nextDep) {
                  $label .= ' (ank. ' . ($arr ?: '-') . ' â€¢ afg. ' . ($nextDep ?: '-') . (($lay !== null && $lay >= 0 && $lay <= 360) ? (', ophold ' . $lay . ' min') : '') . ')';
                }
                // Append MCT judgement if available
                $ev = $mctByStation[$norm($toName)] ?? null;
                if (is_array($ev)) {
                  $ok = !empty($ev['realistic']); $thr = (int)($ev['threshold'] ?? 0);
                  $label .= $ok ? ' [MCT ok = ' . $thr . 'm]' : ' [MCT kort < ' . $thr . 'm]';
                }
                // Collect ALL changes without deduping by station to ensure multiple skift are shown
                if (!isset($mcChoicesInline) || !is_array($mcChoicesInline)) { $mcChoicesInline = []; }
                $mcChoicesInline[] = ['station' => $toName, 'label' => $label];
                if ($lay !== null && $lay >= 0 && $lay <= 360) {
                  $bullet = 'Skift i ' . $toName . ' (ankomst ' . ($arr ?: '-') . ', afgang ' . ($nextDep ?: '-') . '), opholdstid: ' . $lay . ' minutter';
                  if (is_array($ev)) { $bullet .= !empty($ev['realistic']) ? ' - MCT: OK' : ' - MCT: for kort'; }
                  $changeBullets[] = $bullet;
                }
              }
            }
          }
        } catch (\Throwable $e) { /* ignore */ }
        $currentMissInline = (string)($form['missed_connection_station'] ?? '');
      ?>
      <?php
        // Fallback: if no inline candidates but controller built a simple list, use it
        if (empty($mcChoicesInline)) {
          $simple = (array)($form['_miss_conn_choices'] ?? []);
          if (!empty($simple)) {
            // Normalize controller-provided map (station => station) into list of {station,label}
            $mcChoicesInline = [];
            foreach ($simple as $st => $lbl) { $mcChoicesInline[] = ['station' => (string)$st, 'label' => (string)$lbl]; }
          }
        }
      ?>
      <div class="missed-connection-section" style="display:none;">
        <?= $this->element('missed_connection_block', compact('meta','form','journeyRowsInline','mcChoicesInline','changeBullets')) ?>
      </div>
      </div>
      <?php if (!empty($journeyRowsInline)): ?>
      <div class="missed-connection-section" style="display:none;">
      <?php
        $normClassInline = function($v): string {
          $v = strtolower(trim((string)$v));
          if (in_array($v, ['1st_class','1st','first','1'], true)) return '1st';
          if (in_array($v, ['2nd_class','2nd','second','2'], true)) return '2nd';
          if ($v === 'seat_reserved' || $v === 'free_seat') return '2nd';
          return $v;
        };
      ?>
      <div class="small" style="margin-top:10px;"><strong>Rejseplan (aflÃ¦st fra billetten)</strong></div>
      <div class="small" style="overflow:auto;">
        <table id="mcJourneyTable" class="fe-table">
          <thead>
            <tr>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">StrÃ¦kning</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Afgang</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Ankomst</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Tog</th>
              <th style="text-align:left; border-bottom:1px solid #eee; padding:4px;">Skift</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($journeyRowsInline as $idx => $r): ?>
              <?php
                // Brug gemt vÃ¦rdi, ellers evt. auto-detektion; fald ikke tilbage til "kÃ¸bt"-valg
                $deliveredValRaw = (string)($form['leg_class_delivered'][$idx] ?? ($meta['_auto']['class_delivered'][$idx]['value'] ?? ''));
                $deliveredVal = $normClassInline($deliveredValRaw);
                $downgVal = isset($form['leg_downgraded'][$idx]) && $form['leg_downgraded'][$idx] === '1';
              ?>
              <tr>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['leg']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['dep']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['arr']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['train']) ?></td>
                <td style="padding:4px; border-bottom:1px solid #f3f3f3;"><?= h($r['change']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($changeBullets)): ?>
        <div class="small" style="margin-top:8px;">
          <div><strong>Der er <?= count($changeBullets) ?> skift<?= count($changeBullets)===1?'':'e' ?>:</strong></div>
          <ul style="margin:6px 0 0 16px;">
            <?php foreach ($changeBullets as $b): ?>
              <li><?= h($b) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (empty($mcChoicesInline)): ?>
        <div class="small muted" style="margin-top:8px;">Ingen skift fundet â€“ punkt 3.5 vises kun, nÃ¥r der er et skift i rejsen.</div>
      <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  </div><!-- /railJourneyFields -->

  <?php if ($isFerry || $isBus || $isAir): ?>
  <div id="modeJourneyFields" style="<?= ($isFerry || $isBus || $isAir) ? '' : 'display:none;' ?>">
    <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
      <strong><?= $isFerry ? 'Færge — booking, ansvar og scope' : ($isBus ? 'Bus — booking, ansvar og scope' : 'Fly — booking, ansvar og scope') ?></strong>
      <div class="small muted" style="margin-top:6px;">
        <?= $isFerry ? 'Ved upload bruger vi LLM først. Udfyld kun det der mangler: først basisrejse, derefter kontrakt og ansvar, og til sidst scopefelter.' : ($isBus ? 'Ved upload bruger vi LLM først. Udfyld kun det der mangler: først basisrejse, derefter kontrakt og ansvar, og til sidst scopefelter.' : 'Ved upload bruger vi LLM først. Udfyld kun det der mangler: først basisrejse, derefter kontrakt og ansvar, og til sidst scopefelter.') ?>
      </div>
      <div class="small" style="margin-top:12px; font-weight:600;">1. Basisrejse</div>
      <div class="small muted" style="margin-top:4px;">Carrier/operator, fra/til, tider, bookingreference og pris.</div>
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:6px;">
        <label><?= $isAir ? 'Carrier / operating carrier' : ($isFerry ? 'Carrier' : 'Operatør') ?>
          <input type="text" name="operator" value="<?= h($meta['_auto']['operator']['value'] ?? ($form['operator'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Scandlines' : ($isBus ? 'Fx FlixBus' : 'Fx SAS') ?>" />
        </label>
        <label>Produkt / bookingtype
          <input type="text" name="operator_product" value="<?= h($meta['_auto']['operator_product']['value'] ?? ($form['operator_product'] ?? '')) ?>" placeholder="<?= $isAir ? 'Fx Economy / Flex' : 'Fx Standard ticket' ?>" />
        </label>
        <label><?= $isFerry ? 'Afgangshavn' : ($isBus ? 'Afgangssted / terminal' : 'Afgangslufthavn') ?>
          <input type="text" name="dep_station" value="<?= h($meta['_auto']['dep_station']['value'] ?? ($form['dep_station'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Helsingør' : ($isBus ? 'Fx København Busterminal' : 'Fx CPH') ?>" />
        </label>
        <label><?= $isFerry ? 'Ankomsthavn' : ($isBus ? 'Ankomststed / terminal' : 'Ankomstlufthavn') ?>
          <input type="text" name="arr_station" value="<?= h($meta['_auto']['arr_station']['value'] ?? ($form['arr_station'] ?? '')) ?>" placeholder="<?= $isFerry ? 'Fx Helsingborg' : ($isBus ? 'Fx Aarhus Rutebilstation' : 'Fx ARN') ?>" />
        </label>
        <label>Afgangsdato
          <input type="text" name="dep_date" value="<?= h($meta['_auto']['dep_date']['value'] ?? ($form['dep_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
        </label>
        <label>Planlagt afgangstid
          <input type="text" name="dep_time" value="<?= h($meta['_auto']['dep_time']['value'] ?? ($form['dep_time'] ?? '')) ?>" placeholder="HH:MM" />
        </label>
        <label>Planlagt ankomsttid
          <input type="text" name="arr_time" value="<?= h($meta['_auto']['arr_time']['value'] ?? ($form['arr_time'] ?? '')) ?>" placeholder="HH:MM" />
        </label>
        <label><?= $isAir ? 'Bookingreference / e-ticket' : 'Bookingreference / billetnummer' ?>
          <input type="text" name="ticket_no" value="<?= h((string)($meta['_auto']['ticket_no']['value'] ?? ($form['ticket_no'] ?? ($meta['_identifiers']['pnr'] ?? ($journey['bookingRef'] ?? ''))))) ?>" />
        </label>
        <?php if ($isAir): ?>
        <label>Marketing carrier
          <input type="text" name="marketing_carrier" value="<?= h((string)($form['marketing_carrier'] ?? ($modeContract['marketing_carrier'] ?? ''))) ?>" placeholder="Fx SAS" />
        </label>
        <label>Operating carrier
          <input type="text" name="operating_carrier" value="<?= h((string)($form['operating_carrier'] ?? ($modeContract['operating_carrier'] ?? ''))) ?>" placeholder="Fx CityJet" />
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
      <div class="small" style="margin-top:14px; font-weight:600;">2. Scopefelter</div>
      <div class="small muted" style="margin-top:4px;">Bruges til at afgøre om forordningen gælder og hvilke undtagelser der kan være relevante.</div>
      <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px;">
        <?php if ($isFerry): ?>
          <?php $serviceTypeUpload = (string)($form['service_type'] ?? 'passenger_service'); ?>
          <label>Servicetype
            <select name="service_type">
              <option value="passenger_service" <?= $serviceTypeUpload==='passenger_service'?'selected':'' ?>>Passenger service</option>
              <option value="cruise" <?= $serviceTypeUpload==='cruise'?'selected':'' ?>>Cruise</option>
              <option value="excursion" <?= $serviceTypeUpload==='excursion'?'selected':'' ?>>Excursion</option>
              <option value="sightseeing" <?= $serviceTypeUpload==='sightseeing'?'selected':'' ?>>Sightseeing</option>
            </select>
          </label>
          <?php $depPortEuUpload = (string)($form['departure_port_in_eu'] ?? 'yes'); ?>
          <label>Afgangshavn i EU?
            <select name="departure_port_in_eu">
              <option value="yes" <?= $depPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $depPortEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $arrPortEuUpload = (string)($form['arrival_port_in_eu'] ?? 'yes'); ?>
          <label>Ankomsthavn i EU?
            <select name="arrival_port_in_eu">
              <option value="yes" <?= $arrPortEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $arrPortEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $carrierEuUpload = (string)($form['carrier_is_eu'] ?? 'yes'); ?>
          <label>Carrier er EU-operatør?
            <select name="carrier_is_eu">
              <option value="yes" <?= $carrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $carrierEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $depTerminalUpload = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
          <label>Fra havneterminal?
            <select name="departure_from_terminal">
              <option value="yes" <?= $depTerminalUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $depTerminalUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Ruteafstand i meter (valgfri)
            <input type="number" name="route_distance_meters" min="0" step="1" value="<?= h((string)($form['route_distance_meters'] ?? '')) ?>" placeholder="10000" />
          </label>
          <label>Passagerkapacitet (valgfri)
            <input type="number" name="vessel_passenger_capacity" min="0" step="1" value="<?= h((string)($form['vessel_passenger_capacity'] ?? '')) ?>" placeholder="200" />
          </label>
          <label>Operationel besætning (valgfri)
            <input type="number" name="vessel_operational_crew" min="0" step="1" value="<?= h((string)($form['vessel_operational_crew'] ?? '')) ?>" placeholder="12" />
          </label>
        <?php elseif ($isBus): ?>
          <?php $busRegularUpload = (string)($form['bus_regular_service'] ?? 'yes'); ?>
          <label>Regular service?
            <select name="bus_regular_service">
              <option value="yes" <?= $busRegularUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busRegularUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $busTerminalUpload = (string)($form['departure_from_terminal'] ?? 'yes'); ?>
          <label>Fra terminal?
            <select name="departure_from_terminal">
              <option value="yes" <?= $busTerminalUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $busTerminalUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $boardingInEuUpload = (string)($form['boarding_in_eu'] ?? 'yes'); ?>
          <label>Boarding i EU?
            <select name="boarding_in_eu">
              <option value="yes" <?= $boardingInEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $boardingInEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $alightingInEuUpload = (string)($form['alighting_in_eu'] ?? 'yes'); ?>
          <label>Alighting i EU?
            <select name="alighting_in_eu">
              <option value="yes" <?= $alightingInEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $alightingInEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <label>Planlagt distance (km)
            <input type="number" name="scheduled_distance_km" min="0" step="1" value="<?= h((string)($form['scheduled_distance_km'] ?? '')) ?>" placeholder="320" />
          </label>
        <?php elseif ($isAir): ?>
          <?php $airDepEuUpload = (string)($form['departure_airport_in_eu'] ?? 'yes'); ?>
          <label>Afgangslufthavn i EU?
            <select name="departure_airport_in_eu">
              <option value="yes" <?= $airDepEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $airDepEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $airArrEuUpload = (string)($form['arrival_airport_in_eu'] ?? 'yes'); ?>
          <label>Ankomstlufthavn i EU?
            <select name="arrival_airport_in_eu">
              <option value="yes" <?= $airArrEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $airArrEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $opCarrierEuUpload = (string)($form['operating_carrier_is_eu'] ?? 'yes'); ?>
          <label>Operating carrier er EU-operatør?
            <select name="operating_carrier_is_eu">
              <option value="yes" <?= $opCarrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $opCarrierEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $mkCarrierEuUpload = (string)($form['marketing_carrier_is_eu'] ?? 'yes'); ?>
          <label>Marketing carrier er EU-operatør?
            <select name="marketing_carrier_is_eu">
              <option value="yes" <?= $mkCarrierEuUpload==='yes'?'selected':'' ?>>Ja</option>
              <option value="no" <?= $mkCarrierEuUpload==='no'?'selected':'' ?>>Nej</option>
            </select>
          </label>
          <?php $airConnectionTypeUpload = (string)($form['air_connection_type'] ?? ($modeContract['air_connection_type'] ?? '')); ?>
          <label>Forbindelsestype
            <select name="air_connection_type">
              <option value="">Auto / resolver</option>
              <option value="single_flight" <?= $airConnectionTypeUpload==='single_flight'?'selected':'' ?>>Enkelt flight</option>
              <option value="protected_connection" <?= $airConnectionTypeUpload==='protected_connection'?'selected':'' ?>>Protected connection</option>
              <option value="self_transfer" <?= $airConnectionTypeUpload==='self_transfer'?'selected':'' ?>>Self-transfer</option>
            </select>
          </label>
        <?php endif; ?>
      </div>
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
    // Also compute PNR count + shared scope hint for same-transaction prompt
    $pnrCountInline = 0; try {
      $pnrSet = [];
      $br = (string)($journey['bookingRef'] ?? ''); if ($br!=='') { $pnrSet[$br]=true; }
      foreach ((array)($groupedTickets ?? []) as $g) { $p=(string)($g['pnr'] ?? ''); if ($p!=='') { $pnrSet[$p]=true; } }
      $pnrCountInline = count($pnrSet);
    } catch (\Throwable $e) { $pnrCountInline = 0; }
  ?>
  <?php if ($isRail): ?>
  <?php
    // Vis kun Art.12-blokken for rail, hvor den fungerer som rail-specialregel i TRIN 2.
  ?>
  <?php $a12Open = (bool)$needA12; ?>
  <div class="card" style="margin-top:12px; padding:16px; border:1px solid #e5e7eb; background:#fff; border-radius:6px;" id="art12MinimalBlock" data-art="12">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <strong>ðŸ“„ Art. 12 â€“ Kontraktoplysninger</strong>
      <button type="button" id="a12EditSellerBtn" class="small" style="background:transparent; border:0; color:#0b5; text-decoration:underline; cursor:pointer;">Rediger</button>
    </div>
    <?php
      // Vis altid spÃ¸rgsmÃ¥lene, uanset auto-status, sÃ¥ brugeren kan rette dem
      $showSeller = true;
      $showThrough = true;
      $showSeparate = true;
      $sellerLabel = ($sellerInf === 'operator') ? 'OperatÃ¸r (jernbane)' : (($sellerInf === 'retailer') ? 'Forhandler/rejsebureau' : 'Ukendt');
      $throughLabel = ($ttdVal === 'yes') ? 'Ja' : (($ttdVal === 'no') ? 'Nej' : 'Ukendt');
      $separateLabel = ($scnVal === 'yes') ? 'Ja' : (($scnVal === 'no') ? 'Nej' : 'Ukendt');
    ?>
    <div class="small muted" style="margin-top:6px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
      <span class="badge" style="background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:12px;">Auto</span>
      <span>SÃ¦lger: <?= h($sellerLabel) ?></span>
      <span>â€¢ GennemgÃ¥ende billet: <?= h($throughLabel) ?></span>
      <span>â€¢ Separate kontrakter oplyst: <?= h($separateLabel) ?></span>
    </div>
    <?php if (!$a12Open): ?>
      <div class="small muted" style="margin-top:6px;">(Art. 12 ser ud til at vÃ¦re dÃ¦kket af AUTO. Klik â€œRedigerâ€ hvis du vil Ã¦ndre svarene.)</div>
    <?php endif; ?>

    <div id="a12Questions" style="display:<?= $a12Open ? 'block' : 'none' ?>;">
    <div id="a12Q1" class="small" style="margin-top:10px; display:<?= $showSeller ? 'block' : 'none' ?>;">
      Hvem solgte dig hele rejsen?
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="seller_channel" value="operator" <?= $sellerInf==='operator'?'checked':'' ?> /> OperatÃ¸r (jernbane)</label>
        <label class="mr8"><input type="radio" name="seller_channel" value="retailer" <?= $sellerInf==='retailer'?'checked':'' ?> /> Forhandler/rejsebureau</label>
      </div>
    </div>

    <div id="a12Q1b" class="small" style="margin-top:10px;">
      Var det samme transaktion/booking (samme PNR/bookingnummer)?
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="shared_pnr_scope" value="yes" <?= $pnrScopeVal==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="shared_pnr_scope" value="no" <?= $pnrScopeVal==='no'?'checked':'' ?> /> Nej</label>
      </div>
    </div>

    <div id="a12Q2" class="small" style="margin-top:10px; display:<?= $showThrough ? 'block' : 'none' ?>;">
      Blev det oplyst fÃ¸r kÃ¸b, at billetten var gennemgÃ¥ende?
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="through_ticket_disclosure" value="yes" <?= $ttdVal==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="through_ticket_disclosure" value="no" <?= $ttdVal==='no'?'checked':'' ?> /> Nej</label>
      </div>
    </div>

    <div id="a12Q3" class="small" style="margin-top:10px; display:<?= $showSeparate ? 'block' : 'none' ?>;">
      Blev separate kontrakter oplyst?
      <div class="small" style="margin-top:4px;">
        <label class="mr8"><input type="radio" name="separate_contract_notice" value="yes" <?= $scnVal==='yes'?'checked':'' ?> /> Ja</label>
        <label class="mr8"><input type="radio" name="separate_contract_notice" value="no" <?= $scnVal==='no'?'checked':'' ?> /> Nej</label>
      </div>
    </div>
    <?php $showSameTxn = ($ticketMode !== 'ticketless') && (($pnrCountInline > 1) || (strtolower((string)($meta['shared_pnr_scope'] ?? '')) === 'no')); ?>
    <?php if ($showSameTxn): ?>
    <div class="small" style="margin-top:10px;">Hvis der er flere PNR'er: Var alle billetter kÃ¸bt i Ã©n transaktion?</div>
    <div class="small" style="margin-top:4px;">
      <label class="mr8"><input type="radio" name="same_transaction" value="yes" <?= $sameTxnInf==='yes'?'checked':'' ?> /> Ja</label>
      <label class="mr8"><input type="radio" name="same_transaction" value="no" <?= $sameTxnInf==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <?php endif; ?>
    <div class="small muted" style="margin-top:6px;">(HjÃ¦lper med at afgÃ¸re om der er gennemgÃ¥ende billet og hvem der er ansvarlig efter Art. 12.)</div>
    </div><!-- /a12Questions -->
  </div>
  <?php endif; ?>

  <?php if (!empty($meta['_passengers_auto'])): ?>
  <div class="card" style="margin-top:12px; padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px;">
    <strong>ðŸ‘¥ Fundne passagerer pÃ¥ billetten</strong>
    <div class="small" style="margin-top:6px;">RedigÃ©r navne og markÃ©r hvem der klager:</div>
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
      <label><input type="checkbox" name="claimant_is_legal_representative" value="1" <?= !empty($meta['claimant_is_legal_representative']) ? 'checked' : '' ?> /> Jeg er juridisk vÃ¦rge/ansvarlig for andre pÃ¥ billetten</label>
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
      <input type="checkbox" name="se_under_150km" value="1" <?= !empty($journey['se_under_150km']) ? 'checked' : '' ?> onchange="this.form.submit()" /> StrÃ¦kningen er under 150 km
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
    <?= $this->element('hooks_panel', compact('profile','art12','art9','refund','refusion','form','meta','groupedTickets','euOnlySuggested','euOnlyReason','journey','formDecision') + ['showFormDecision' => true, 'showArt12Section' => false, 'hidePmrBike' => true]) ?>
  </div>
  <div class="small muted" style="margin-top:6px;">Sidepanelet opdateres automatisk ved Ã¦ndringer.</div>
  <div class="small" style="margin-top:6px; display:flex; gap:8px; align-items:center;">
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
  const stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  const productsByOperator = <?= json_encode($productsByOperator, JSON_UNESCAPED_UNICODE) ?>;
  const operatorToCountry = <?= json_encode($operatorToCountry, JSON_UNESCAPED_UNICODE) ?>;
  const countryToCurrency = <?= json_encode($countryToCurrency, JSON_UNESCAPED_UNICODE) ?>;

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
      const cc = (ccInput && (ccInput.value||'').trim().toUpperCase()) || '';
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
    const seasonOptionWrap = document.getElementById('seasonPassOptionWrap');
    const art12Card = document.getElementById('art12MinimalBlock');
    const ferryScopeCard = document.getElementById('ferryScopeCard');
    const modeContractCard = document.getElementById('modeContractCard');
    const priceBlock = document.getElementById('ticketlessPriceBlock');
    const ticketlessFs = document.getElementById('ticketlessFieldset');
    const seasonFs = document.getElementById('seasonPassFieldset');
    const seasonHas = document.getElementById('seasonPassHas');
    const journeyFieldsFs = document.getElementById('journeyFieldsFieldset');
    const railJourneyFields = document.getElementById('railJourneyFields');
    const modeJourneyFields = document.getElementById('modeJourneyFields');
    const a12Qs = document.getElementById('a12Questions');
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
      for (const n of nodes) { if (n && n.checked) return n.value; }
      return '';
    }
    function show(el, on){ if (!el) return; el.style.display = on ? 'block' : 'none'; }
    function updateTicketMode(){
      const mode = radioVal('ticket_upload_mode') || 'ticket';
      const isTicketless = mode === 'ticketless';
      const transportMode = radioVal('transport_mode') || 'rail';
      const seasonAllowed = transportMode === 'rail' || transportMode === 'ferry';
      const isSeason = seasonAllowed && mode === 'seasonpass';
      show(uploadCard, mode === 'ticket');
      show(ticketlessCard, isTicketless);
      show(seasonCard, isSeason);
      show(seasonOptionWrap, seasonAllowed);
      // Prevent duplicate-name inputs from overwriting each other on submit.
      // Also keep the currently visible mode editable without requiring a round-trip.
      if (ticketlessFs) { ticketlessFs.disabled = !isTicketless; }
      if (seasonFs) { seasonFs.disabled = !isSeason; }
      if (seasonHas) { seasonHas.value = isSeason ? '1' : '0'; }
      if (journeyFieldsFs) { journeyFieldsFs.disabled = isTicketless; }
      if (!seasonAllowed && mode === 'seasonpass') {
        const ticketRadio = form.querySelector('input[name="ticket_upload_mode"][value="ticket"]');
        if (ticketRadio) { ticketRadio.checked = true; }
        return updateTicketMode();
      }
      // In ticketless mode we always want Art.12 questions available, even before the first submit.
      if (a12Qs && (isTicketless || isSeason)) { a12Qs.style.display = 'block'; }
      setDisabledWithin(ferryScopeCard, true);
      setDisabledWithin(modeContractCard, true);
      setDisabledWithin(art12Card, transportMode !== 'rail');
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
    function updateTransportMode(){
      const mode = radioVal('transport_mode') || 'rail';
      const ticketMode = radioVal('ticket_upload_mode') || 'ticket';
      const isTicketless = ticketMode === 'ticketless';
      show(ferryScopeCard, false);
      show(modeContractCard, false);
      show(art12Card, mode === 'rail');
      show(railJourneyFields, mode === 'rail');
      show(modeJourneyFields, mode !== 'rail');
      setDisabledWithin(ferryScopeCard, true);
      setDisabledWithin(modeContractCard, true);
      setDisabledWithin(art12Card, mode !== 'rail');
    }

    form.addEventListener('change', (e)=>{
      const nm = (e.target && (e.target.name||'')) || '';
      if (nm === 'ticket_upload_mode') updateTicketMode();
      if (nm === 'transport_mode') {
        updateTransportMode();
        setTimeout(()=>form.submit(), 0);
      }
      if (nm === 'price_known') updatePriceKnown();
    }, { passive:true });
    updateTicketMode();
    updateTransportMode();
    updatePriceKnown();
  })();
  // Toggle journey fields 3.1â€“3.5
  const toggleBtn = document.getElementById('toggleJourneyFields');
  const jf = document.getElementById('journeyFields');
  const openJourneyFields = () => { if (jf) { jf.style.display = 'block'; } };
  const closeJourneyFields = () => { if (jf) { jf.style.display = 'none'; } };
  const journeyToggleLabel = () => {
    if (!toggleBtn) return 'Vis/skjul rejsefelter (3.1–3.5)';
    const mode = toggleBtn.dataset.transportMode || 'rail';
    if (mode === 'ferry') return 'Vis/skjul booking- og havnefelter';
    if (mode === 'bus') return 'Vis/skjul booking- og rutefelter';
    if (mode === 'air') return 'Vis/skjul booking- og flyfelter';
    return 'Vis/skjul rejsefelter (3.1–3.5)';
  };
  const updateJourneyToggleUi = () => {
    if (!toggleBtn || !jf) return;
    const isOpen = (jf.style.display !== 'none' && jf.style.display !== '');
    toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    toggleBtn.textContent = isOpen
      ? journeyToggleLabel() + ' – skjul'
      : journeyToggleLabel() + ' – vis';
  };
  const hasTickets = !!(toggleBtn && toggleBtn.dataset.hasTickets === '1');
  if (toggleBtn && jf) {
    toggleBtn.addEventListener('click', function(){
      const opening = (jf.style.display === 'none' || jf.style.display === '');
      if (opening) {
        openJourneyFields();
      } else {
        closeJourneyFields();
        try { toggleBtn.scrollIntoView({ block: 'start' }); } catch (e) { /* ignore */ }
      }
      updateJourneyToggleUi();
    });
  }
  if (hasTickets) { openJourneyFields(); }
  updateJourneyToggleUi();
  if (form && toggleBtn) {
    form.addEventListener('change', function(e){
      if (e.target && e.target.name === 'transport_mode') {
        toggleBtn.dataset.transportMode = e.target.value || 'rail';
        updateJourneyToggleUi();
      }
    }, { passive:true });
  }
  const panel = document.getElementById('hooksPanel');
  if (!form || !panel) return;
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
    sync();
  }
  function removeIndex(idx){
    const ndt = new DataTransfer();
    Array.from(dt.files).forEach((f,i)=>{ if(i!==idx) ndt.items.add(f); });
    dt = ndt; sync();
    // If nothing left client-side, force clear_all to wipe cached tickets server-side
    if (dt.files.length === 0) {
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
    inputMulti.files = dt.files;
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
    if (dt.files.length === 0) {
      const li = document.createElement('li');
      li.className = 'muted';
      li.textContent = 'Der er ikke valgt nogen fil.';
      list.appendChild(li);
      return;
    }
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
  if (drop && addBtn && inputMulti && inputSingle && list) {
  drop.addEventListener('click', ()=> inputMulti.click());
  addBtn.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); inputMulti.click(); });
    if (clearBtn) clearBtn.addEventListener('click', (e)=>{
      e.preventDefault(); e.stopPropagation();
      // Reset client-side selection
      dt = new DataTransfer();
      // Mark server-side clear-all and submit
      const hid = document.createElement('input');
      hid.type = 'hidden'; hid.name = 'clear_all'; hid.value = '1';
      form.appendChild(hid);
      sync();
    });
    drop.addEventListener('dragover', (e)=>{ e.preventDefault(); drop.classList.add('drag'); });
    drop.addEventListener('dragleave', ()=>{ drop.classList.remove('drag'); });
    drop.addEventListener('drop', (e)=>{ e.preventDefault(); drop.classList.remove('drag'); if (e.dataTransfer?.files?.length){ addFiles(e.dataTransfer.files); }});
    inputMulti.addEventListener('change', ()=>{ if (inputMulti.files?.length) addFiles(inputMulti.files); });
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

  // Art. 12: show only necessary questions, allow edit to reveal radios
  const a12Init = {
    sellerInit: '',
    showSeller: false,
    showThrough: false,
    showSeparate: false
  };
  let a12EditClicked = false;
  a12Init.sellerInit = valRadio2('seller_channel') || '';
  a12Init.showSeller = (document.getElementById('a12Q1')?.style.display === 'block');
  a12Init.showThrough = (document.getElementById('a12Q2')?.style.display === 'block');
  a12Init.showSeparate = (document.getElementById('a12Q3')?.style.display === 'block');
  function updateA12(){
    // Alle spÃ¸rgsmÃ¥l vises nu altid; bevar funktionen for fremtidig udvidelse
    const q1 = document.getElementById('a12Q1');
    const q2 = document.getElementById('a12Q2');
    const q3 = document.getElementById('a12Q3');
    if (q1) q1.style.display = 'block';
    if (q2) q2.style.display = 'block';
    if (q3) q3.style.display = 'block';
  }
  const a12EditBtn = document.getElementById('a12EditSellerBtn');
  if (a12EditBtn) {
    a12EditBtn.addEventListener('click', function(){
      a12EditClicked = true;
      const qs = document.getElementById('a12Questions');
      if (qs) { qs.style.display = 'block'; }
      const q1 = document.getElementById('a12Q1');
      if (q1) { q1.style.display = 'block'; q1.scrollIntoView({ behavior:'smooth', block:'center' }); }
      updateA12();
    });
  }
  // React when seller choice changes
  (function(){
    const els = document.querySelectorAll('input[name="seller_channel"]');
    els.forEach(function(el){ el.addEventListener('change', updateA12, { passive:true }); });
  })();

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

