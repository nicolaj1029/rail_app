<?php
/**
 * TRIN 10 – Kompensation (Art. 19)
 * Vars injected from controller: form, compute, incident, profile, claim, meta, seasonMode, seasonSummary, etc.
 */
$form = $form ?? [];
$flags = $flags ?? [];
$compute = $compute ?? [];
$incident = $incident ?? [];
$profile = $profile ?? ['articles' => []];
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$multimodal = (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? 'rail'))));
$isFerry = ($transportMode === 'ferry');
$isBus = ($transportMode === 'bus');
$isAir = ($transportMode === 'air');
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$airContract = (array)($multimodal['air_contract'] ?? []);
$airRights = (array)($multimodal['air_rights'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$busContract = (array)($multimodal['bus_contract'] ?? []);
$busRights = (array)($multimodal['bus_rights'] ?? []);
$modeContract = $isBus ? $busContract : (($isAir ? (array)($multimodal['air_contract'] ?? []) : []));
$claimDirection = (array)($multimodal['claim_direction'] ?? []);
$compTitle = $isOngoing
    ? (($isFerry || $isBus || $isAir) ? 'TRIN 10 - Resultat (foreloebigt)' : 'TRIN 10 - Kompensation (foreloebig)')
    : ($isCompleted ? (($isFerry || $isBus || $isAir) ? 'TRIN 10 - Resultat (afsluttet rejse)' : 'TRIN 10 - Kompensation (afsluttet rejse)') : (($isFerry || $isBus || $isAir) ? 'TRIN 10 - Resultat' : 'TRIN 10 - Kompensation (Art. 19)'));
$compHint = $isOngoing
    ? (($isFerry || $isBus || $isAir) ? 'Resultatet kan aendre sig, naar kontrakt- og haendelsesoplysningerne er fuldt afklaret.' : 'Beregningen kan aendre sig, naar rejsen er afsluttet.')
    : ($isCompleted ? (($isFerry || $isBus || $isAir) ? 'Resultatet er baseret paa den afsluttede rejse og den nuvaerende kontraktvurdering.' : 'Beregningen er baseret paa den afsluttede rejse.') : '');
$delayAtFinal = (int)($delayAtFinal ?? 0);
$bandAuto = (string)($bandAuto ?? '0');
$refundChosen = (bool)($refundChosen ?? false);
$preinformed = (bool)($preinformed ?? false);
$rerouteUnder60 = (bool)($rerouteUnder60 ?? false);
$art19Allowed = (bool)($art19Allowed ?? true);
$articles = (array)($profile['articles'] ?? []);
$art19Enabled = !isset($articles['art19']) || $articles['art19'] !== false;
$isPreview = !empty($flowPreview);
$step10Ready = array_key_exists('step10Ready', get_defined_vars()) ? (bool)$step10Ready : true;
$seasonMode = (bool)($seasonMode ?? false);
$seasonPolicy = $seasonPolicy ?? null;

if (!$step10Ready) {
    $opName = (string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? ''));
    $opCountry = (string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''));
    $policyUrl = is_array($seasonPolicy) ? (string)($seasonPolicy['source_url'] ?? '') : '';
    $claimUrl = '';
    if (is_array($seasonPolicy) && !empty($seasonPolicy['claim_channel']) && is_array($seasonPolicy['claim_channel'])) {
        if ((string)($seasonPolicy['claim_channel']['type'] ?? '') === 'url') {
            $claimUrl = (string)($seasonPolicy['claim_channel']['value'] ?? '');
        }
    }
    $incidentUrl = $this->Url->build(['controller' => 'Flow', 'action' => 'incident']);
    ?>
    <section class="panel">
        <h2><?= h($compTitle) ?></h2>
        <p>
            Season/pendler-mode er aktiv. Udfyld TRIN 5 (hændelse + forsinkelse) for at få beregning/resultat.
        </p>
        <?php if ($opName !== '' || $opCountry !== ''): ?>
            <p><strong>Operatør:</strong> <?= h(trim($opName . ($opCountry !== '' ? ' (' . $opCountry . ')' : ''))) ?></p>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="<?= h($incidentUrl) ?>">Gå til TRIN 5</a></p>
        <?php if ($policyUrl !== '' || $claimUrl !== ''): ?>
            <div class="mt12">
                <div><strong>Operatørens policy/claim</strong></div>
                <ul>
                    <?php if ($policyUrl !== ''): ?><li><a target="_blank" rel="noopener" href="<?= h($policyUrl) ?>">Policy-side</a></li><?php endif; ?>
                    <?php if ($claimUrl !== ''): ?><li><a target="_blank" rel="noopener" href="<?= h($claimUrl) ?>">Claim-kanal</a></li><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <p class="muted mt12">
            TRIN 10 er åbent tidligt for pendlere, men vi viser først et egentligt resultat når hændelsen er registreret.
        </p>
    </section>
    <?php
    return;
}

$priceFromTicket = (float)($ticketPriceAmount ?? 0);
$priceCurrency = (string)($currency ?? 'EUR');
$remedyChoice = (string)($form['remedyChoice'] ?? '');
$rerouteExtraAmount = is_numeric($form['reroute_extra_costs_amount'] ?? null)
    ? (float)$form['reroute_extra_costs_amount']
    : (float)preg_replace('/[^0-9.]/', '', (string)($form['reroute_extra_costs_amount'] ?? '0'));
$rerouteExtraCur = (string)($form['reroute_extra_costs_currency'] ?? $priceCurrency);
if ($rerouteExtraCur === '') {
    $rerouteExtraCur = $priceCurrency;
}
$returnFlag = (string)($form['return_to_origin_expense'] ?? '');
$returnAmt = is_numeric($form['return_to_origin_amount'] ?? null)
    ? (float)$form['return_to_origin_amount']
    : (float)preg_replace('/[^0-9.]/', '', (string)($form['return_to_origin_amount'] ?? '0'));
$returnCur = (string)($form['return_to_origin_currency'] ?? $priceCurrency);
if ($returnCur === '') { $returnCur = $priceCurrency; }
$airAltAirportTransferAmt = is_numeric($form['air_alternative_airport_transfer_amount'] ?? null)
    ? (float)$form['air_alternative_airport_transfer_amount']
    : (float)preg_replace('/[^0-9.]/', '', (string)($form['air_alternative_airport_transfer_amount'] ?? '0'));
$airAltAirportTransferCur = (string)($form['air_alternative_airport_transfer_currency'] ?? $priceCurrency);
if ($airAltAirportTransferCur === '') { $airAltAirportTransferCur = $priceCurrency; }
$airRerouteExpenseFlag = (string)($form['air_reroute_expenses_incurred'] ?? ($form['reroute_extra_costs'] ?? ''));
$airRerouteExpenseType = (string)($form['air_reroute_expense_type'] ?? '');
$airRerouteExpenseAmount = is_numeric($form['air_reroute_expense_amount'] ?? null)
    ? (float)$form['air_reroute_expense_amount']
    : (float)preg_replace('/[^0-9.]/', '', (string)($form['air_reroute_expense_amount'] ?? '0'));
$airRerouteExpenseCur = (string)($form['air_reroute_expense_currency'] ?? $priceCurrency);
if ($airRerouteExpenseCur === '') { $airRerouteExpenseCur = $priceCurrency; }
$airRerouteExpenseDescription = (string)($form['air_reroute_expense_description'] ?? '');
$airAlternativeAirportUsed = (string)($form['air_alternative_airport_used'] ?? '');
$airRerouteExpenseTypeLabel = match ($airRerouteExpenseType) {
    'new_ticket' => 'Ny billet',
    'airport_transfer' => 'Transfer til/fra alternativ lufthavn',
    'other_transport' => 'Anden noedvendig transport',
    'expensive_solution' => 'Dyrere alternativ loesning',
    'accommodation' => 'Indkvartering (legacy)',
    'other' => 'Andet',
    default => $airRerouteExpenseType,
};
$airRerouteExpenseItems = [];
foreach ((array)($form['air_reroute_expense_items'] ?? []) as $row) {
    if (!is_array($row)) {
        continue;
    }
    $type = (string)($row['type'] ?? '');
    if ($type === 'alt_transport') { $type = 'other_transport'; }
    if ($type === 'higher_class') { $type = 'expensive_solution'; }
    $amount = is_numeric($row['amount'] ?? null)
        ? (float)$row['amount']
        : (float)preg_replace('/[^0-9.]/', '', (string)($row['amount'] ?? '0'));
    $currencyItem = strtoupper(trim((string)($row['currency'] ?? '')));
    if ($type === '' && $amount <= 0 && $currencyItem === '' && trim((string)($row['description'] ?? '')) === '') {
        continue;
    }
    if ($currencyItem === '') {
        $currencyItem = $priceCurrency;
    }
    $airRerouteExpenseItems[] = [
        'type' => $type,
        'amount' => $amount,
        'currency' => $currencyItem,
        'description' => trim((string)($row['description'] ?? '')),
        'label' => match ($type) {
            'new_ticket' => 'Ny billet',
            'airport_transfer' => 'Transfer til/fra alternativ lufthavn',
            'other_transport' => 'Anden noedvendig transport',
            'expensive_solution' => 'Dyrere alternativ loesning',
            'accommodation' => 'Indkvartering (legacy)',
            'other' => 'Andet',
            default => $type,
        },
    ];
}
if ($airRerouteExpenseItems === [] && ($airRerouteExpenseType !== '' || $airRerouteExpenseAmount > 0 || $airRerouteExpenseDescription !== '')) {
    $airRerouteExpenseItems[] = [
        'type' => $airRerouteExpenseType,
        'amount' => $airRerouteExpenseAmount,
        'currency' => $airRerouteExpenseCur,
        'description' => $airRerouteExpenseDescription,
        'label' => $airRerouteExpenseTypeLabel,
    ];
}
$downgradeBasis = (string)($form['downgrade_comp_basis'] ?? '');
$downgradeShare = is_numeric($form['downgrade_segment_share'] ?? null)
    ? (float)$form['downgrade_segment_share']
    : (float)($form['downgrade_segment_share'] ?? 1);
$downgradeShare = max(0.0, min(1.0, $downgradeShare));
$rateMap = ['seat' => 0.25, 'couchette' => 0.50, 'sleeper' => 0.75];
$downgradeRate = $rateMap[$downgradeBasis] ?? 0.0;
if ($isFerry) {
    $downgradeBasis = '';
    $downgradeShare = 0.0;
    $downgradeRate = 0.0;
}

// Forsøg automatisk at udlede sats ud fra per-leg købt/leveret niveau, hvis ikke sat
$legBuy = (array)($form['leg_class_purchased'] ?? []);
$legDel = (array)($form['leg_class_delivered'] ?? []);
$legResBuy = (array)($form['leg_reservation_purchased'] ?? []);
$legResDel = (array)($form['leg_reservation_delivered'] ?? []);
$legDg  = (array)($form['leg_downgraded'] ?? []);
$rank = [
    'sleeper' => 4,
    'couchette' => 3,
    '1st' => 2,
    '2nd' => 1,
];
$normClass = function(string $v): string {
    $v = strtolower(trim($v));
    if (in_array($v, ['1st_class','1st','first','1'], true)) return '1st';
    if (in_array($v, ['2nd_class','2nd','second','2'], true)) return '2nd';
    if ($v === 'seat_reserved' || $v === 'free_seat') return '2nd';
    return $v;
};
$normRes = function(string $v): string {
    $v = strtolower(trim($v));
    if (in_array($v, ['seat_reserved','reserved','seat'], true)) return 'reserved';
    if (in_array($v, ['free','free_seat'], true)) return 'free_seat';
    if ($v === 'missing') return 'missing';
    return $v;
};
$autoRateFor = function(string $buy, string $del, string $buyRes, string $delRes) use ($rank, $normClass, $normRes): float {
    $bc = $normClass($buy);
    $dc = $normClass($del);
    $rb = $rank[$bc] ?? 0;
    $rd = $rank[$dc] ?? 0;
    if ($rb > 0 && $rd > 0 && $rb > $rd) {
        if ($bc === 'sleeper') { return 0.75; }
        if ($bc === 'couchette') { return 0.50; }
        return 0.25;
    }
    $br = $normRes($buyRes);
    $dr = $normRes($delRes);
    if ($br === 'reserved' && $dr !== '' && $dr !== 'reserved') { return 0.25; }
    return 0.0;
};
$countLegs = max(count($legBuy), count($legDel), count($legResBuy), count($legResDel), count($legDg));
if (!$isFerry && $downgradeRate <= 0 && $countLegs > 0) {
    $dgFound = false;
    for ($i = 0; $i < $countLegs; $i++) {
        $buy = (string)($legBuy[$i] ?? '');
        $del = (string)($legDel[$i] ?? '');
        $buyRes = (string)($legResBuy[$i] ?? '');
        $delRes = (string)($legResDel[$i] ?? '');
        $dg  = ((string)($legDg[$i] ?? '') === '1');
        $autoRate = $autoRateFor($buy, $del, $buyRes, $delRes);
        if ($dg || $autoRate > 0) {
            $dgFound = $dgFound || $dg || $autoRate > 0;
            if ($autoRate > $downgradeRate) {
                $downgradeRate = $autoRate;
            }
        }
    }
    if ($dgFound && empty($form['downgrade_occurred'])) {
        $form['downgrade_occurred'] = 'yes';
    }
}
// Totals are computed server-side (ClaimCalculator) and shared with demo/scenario output.
$serviceFeePct = isset($claim['totals']['service_fee_pct']) ? (int)$claim['totals']['service_fee_pct'] : 0;
$grossAdjusted = isset($claim['totals']['gross_claim']) ? (float)$claim['totals']['gross_claim'] : 0.0;
$serviceFee = isset($claim['totals']['service_fee_amount']) ? (float)$claim['totals']['service_fee_amount'] : 0.0;
$netPayout = isset($claim['totals']['net_to_client']) ? (float)$claim['totals']['net_to_client'] : 0.0;
$downgradeAmount = isset($claim['breakdown']['art18']['downgrade_annexii']) ? (float)$claim['breakdown']['art18']['downgrade_annexii'] : 0.0;
$ferryServiceDeviation = $isFerry && (string)($form['downgrade_occurred'] ?? '') === 'yes';
// Static FX table (EUR base). Approximate mid-rates.
// FX map loader: try cached daily rates from tmp/rates.json; fallback to ECB via frankfurter; else fallback static
$fxStatic = [
    'EUR' => 1.0,
    'DKK' => 7.45,
    'SEK' => 11.0,
    'BGN' => 1.96,
    'CZK' => 25.0,
    'HUF' => 385.0,
    'PLN' => 4.35,
    'RON' => 4.95,
];
$fx = $fxStatic;
$fxCachePath = TMP . 'rates.json';
$fxForceRefresh = false;
try {
    $useCache = false;
    if (is_file($fxCachePath)) {
        $age = time() - filemtime($fxCachePath);
        if ($age < 22 * 3600) { $useCache = true; }
    }
    if ($useCache) {
        $data = json_decode((string)file_get_contents($fxCachePath), true);
        if (is_array($data) && !empty($data['rates'])) { $fx = $data['rates']; }
    } else {
        $api = 'https://api.frankfurter.app/latest?base=EUR&symbols=DKK,SEK,BGN,CZK,HUF,PLN,RON';
        $resp = @file_get_contents($api);
        $json = json_decode((string)$resp, true);
        if (is_array($json) && !empty($json['rates'])) {
            $fx = array_merge(['EUR' => 1.0], $json['rates']);
            @file_put_contents($fxCachePath, json_encode(['ts' => time(), 'rates' => $fx]));
        }
    }
} catch (\Throwable $e) {
    $fx = $fxStatic;
}
$fxConv = function(float $amount, string $from, string $to) use ($fx): ?float {
    $from = strtoupper(trim($from)); $to = strtoupper(trim($to));
    if (!isset($fx[$from]) || !isset($fx[$to]) || $fx[$from] <= 0 || $fx[$to] <= 0) { return null; }
    $eur = $amount / $fx[$from];
    return $eur * $fx[$to];
};
$totCurrency = (string)($totals['currency'] ?? $tot['currency'] ?? $priceCurrency ?? 'EUR');
?>

<style>
  .small{font-size:12px}
  .muted{color:#666}
  .hl{background:#fff3cd;padding:6px;border-radius:6px}
  .ok{background:#e9f7ef;border:1px solid #d4edda;padding:6px;border-radius:6px}
  .bad{background:#fdecea;border:1px solid #f5c6cb;padding:6px;border-radius:6px}
  .card{padding:12px;border:1px solid #ddd;background:#fff;border-radius:6px}
  .mt4{margin-top:4px}.mt8{margin-top:8px}.mt12{margin-top:12px}.ml8{margin-left:8px}
  .widget-title{display:flex;align-items:center;gap:10px;font-weight:700}
  .step-badge{width:28px;height:28px;border-radius:999px;background:#e9f2ff;border:1px solid #cfe0ff;color:#1e3a8a;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;line-height:1;flex:0 0 auto}
</style>

<h1><?= h($compTitle) ?></h1>
<?php if ($compHint !== ''): ?>
  <p class="small muted"><?= h($compHint) ?></p>
<?php endif; ?>
<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>

<?php
  $instantPayoutEligible = (bool)($instantPayoutEligible ?? false);
  $instantPayoutReason = (string)($instantPayoutReason ?? 'Not enabled');
  $datapackUrl = $this->Url->build(['controller' => 'Flow', 'action' => 'compensation', '?' => ['datapack' => '1', 'pretty' => '1']]);
?>

<div class="card mt12" style="border-color:#cfe8ff;background:#f3f8ff">
  <strong>B. Claim-assist / data-pack</strong>
  <div class="small mt4 muted">
    Data-pack er et maskinlÃ¦sbart resume af dine facts (ikke et juridisk dokument). Brug det til at sende en claim,
    eller gem sagen og vend tilbage senere.
  </div>
  <div class="mt8">
    <a class="btn btn-primary" href="<?= h($datapackUrl) ?>">Download data-pack (JSON)</a>
  </div>
</div>

<div class="card mt12" style="border-color:#e5e7eb;background:#fafafa">
  <strong>C. Instant payout</strong>
  <?php if ($instantPayoutEligible): ?>
    <div class="ok small mt8">
      Instant payout er mulig for denne sag (feature-flag/risikomodel skal stadig kobles pÃ¥).
    </div>
  <?php else: ?>
    <div class="hl small mt8">
      Ikke aktiveret endnu. Status: <?= h($instantPayoutReason) ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($isFerry): ?>
<?php
  $ferryBand = (string)($ferryRights['art19_comp_band'] ?? ($flags['ferry_art19_comp_band'] ?? 'none'));
  $ferryClaimName = (string)($ferryContract['primary_claim_party_name'] ?? '');
  $ferryClaimType = (string)($ferryContract['primary_claim_party'] ?? '');
  $ferryClaimPdfUrl = $this->Url->build(['controller' => 'Reimbursement', 'action' => 'official', '?' => ['template' => 'Form_ferry_claim_letter.pdf']]);
  $ferryScopeReason = (string)($ferryScope['scope_exclusion_reason'] ?? '');
  $ferryApplies = array_key_exists('regulation_applies', $ferryScope) ? (bool)$ferryScope['regulation_applies'] : true;
  $ferryArt19Reasons = [];
  if (!$ferryApplies) {
      $ferryArt19Reasons[] = 'Forordningen finder ikke anvendelse paa denne sejlads.';
  } else {
      if ((string)($form['informed_before_purchase'] ?? '') === 'yes') {
          $ferryArt19Reasons[] = 'Forsinkelsen blev oplyst foer koeb.';
      }
      if ((string)($form['weather_safety'] ?? '') === 'yes') {
          $ferryArt19Reasons[] = 'Vejr- eller sikkerhedsforhold blokerer kompensation.';
      }
      if ((string)($form['extraordinary_circumstances'] ?? '') === 'yes') {
          $ferryArt19Reasons[] = 'Carrier paaberaaber ekstraordinaere omstaendigheder.';
      }
      if ((string)($form['open_ticket_without_departure_time'] ?? '') === 'yes' && (string)($form['season_ticket'] ?? '') !== 'yes') {
          $ferryArt19Reasons[] = 'Aaben billet uden afgangstid er undtaget.';
      }
      if (trim((string)($form['arrival_delay_minutes'] ?? '')) === '' || trim((string)($form['scheduled_journey_duration_minutes'] ?? '')) === '') {
          $ferryArt19Reasons[] = 'Ankomstforsinkelse og planlagt rejsevarighed skal vaere udfyldt.';
      }
  }
?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>Faerge-resultat</strong>
  <div class="small muted mt4">TRIN 10 samler scope, claim-kanal og aktive ferry-rettigheder. Brug resultatet til claim-assist, dokumentpakke eller manuel vurdering.</div>
  <div class="card mt12" style="border-color:#e5e7eb;background:#fff">
    <div class="widget-title">
      <span class="step-badge" aria-hidden="true">K</span>
      <span>Kompensationsudregning</span>
    </div>
    <div class="small muted mt4">Faerge bruger ankomstforsinkelse og planlagt rejsevarighed til Art. 19-bandet, ligesom rail bruger sit kompensationstrin til den endelige beregning.</div>
    <div class="mt8">
      <label>Forsinkelse ved ankomst (minutter)
        <input type="number" name="arrival_delay_minutes" min="0" step="1" value="<?= h((string)($form['arrival_delay_minutes'] ?? '')) ?>" placeholder="130" data-ferry-auto-submit="1" />
      </label>
    </div>
    <div class="mt8">
      <label>Planlagt rejsevarighed (minutter)
        <input type="number" name="scheduled_journey_duration_minutes" min="0" step="1" value="<?= h((string)($form['scheduled_journey_duration_minutes'] ?? '')) ?>" placeholder="300" data-ferry-auto-submit="1" />
      </label>
    </div>
  </div>
  <?php if ($ferryClaimName !== ''): ?>
    <div class="small mt8">Primær claim-kanal: <strong><?= h($ferryClaimName) ?></strong><?= $ferryClaimType !== '' ? ' (' . h($ferryClaimType) . ')' : '' ?></div>
  <?php endif; ?>
  <div class="mt8">
    <button type="submit" class="button button-primary" formaction="<?= h($ferryClaimPdfUrl) ?>" formtarget="_blank">
      Download ferry claim letter (PDF)
    </button>
  </div>
  <ul class="small mt8">
    <li>Scope: <strong><?= $ferryApplies ? 'omfattet' : 'ikke omfattet' ?></strong><?= $ferryScopeReason !== '' ? ' - ' . h($ferryScopeReason) : '' ?></li>
    <li>Art. 16 information: <strong><?= !empty($ferryRights['gate_art16_notice']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 17 assistance: <strong><?= !empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 18 tilbagebetaling/ombooking: <strong><?= !empty($ferryRights['gate_art18']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 19 kompensation: <strong><?= !empty($ferryRights['gate_art19']) ? 'relevant' : 'ikke aktiveret' ?></strong><?= $ferryBand !== '' && $ferryBand !== 'none' ? ' - band ' . h($ferryBand) . '%' : '' ?></li>
    <?php if (!empty($claimDirection['recommended_documents'])): ?>
      <li>Anbefalet dokumentation: <strong><?= h(implode(', ', (array)$claimDirection['recommended_documents'])) ?></strong></li>
    <?php endif; ?>
  </ul>
  <div class="small muted mt8">
    Naeste handling:
    <strong><?=
      !$ferryApplies
        ? 'afklar scope eller afslut som uden for forordningen'
        : (!empty($ferryRights['gate_art18'])
            ? 'gaa til tilbagebetaling eller ombooking'
            : ((!empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']))
                ? 'registrer assistance og egne udgifter'
                : 'byg data-pack og vurder claim-kanalen manuelt'))
    ?></strong>
  </div>
  <?php if (!empty($ferryRights['gate_art19']) && $ferryBand !== 'none'): ?>
    <div class="ok mt8 small">Ankomstforsinkelsen peger paa ferry Art. 19 med et foreloebigt bånd paa <strong><?= h($ferryBand) ?>%</strong>.</div>
  <?php elseif (!$ferryApplies): ?>
    <div class="hl mt8 small">Faergeforordningen ser ikke ud til at finde anvendelse paa denne sejlads ud fra de nuvaerende scope-oplysninger.</div>
  <?php elseif ($ferryArt19Reasons !== []): ?>
    <div class="hl mt8 small">Art. 19 er ikke aktiveret endnu, fordi: <strong><?= h(implode(' ', $ferryArt19Reasons)) ?></strong></div>
  <?php else: ?>
    <div class="hl mt8 small">Brug data-pack og de aktive gates ovenfor som grundlag for claim-assist eller manuel vurdering.</div>
  <?php endif; ?>
  <?php if (!empty($claim)): ?>
    <?php
      $br = (array)($claim['breakdown'] ?? []);
      $tot = (array)($claim['totals'] ?? []);
      $summaryCurrency = (string)($tot['currency'] ?? ($priceCurrency !== '' ? $priceCurrency : 'EUR'));
      $refundAmount = (float)($br['refund']['amount'] ?? 0);
      $refundBasis = (string)($br['refund']['basis'] ?? '');
      $rerouteAmount = (float)($br['art18']['reroute_extra_costs'] ?? 0);
      $returnAmount = (float)($br['art18']['return_to_origin'] ?? 0);
      $compAmount = (float)($br['compensation']['amount'] ?? 0);
      $compPct = (int)($br['compensation']['pct'] ?? 0);
      $compBasis = (string)($br['compensation']['basis'] ?? '');
      $expenseTotal = (float)($br['expenses']['total'] ?? 0);
      $mealAmount = (float)($br['expenses']['meals'] ?? 0);
      $hotelAmount = (float)($br['expenses']['hotel'] ?? 0);
      $altTransportAmount = (float)($br['expenses']['alt_transport'] ?? 0);
      $otherAmount = (float)($br['expenses']['other'] ?? 0);
      $ferryCaps = (array)($br['ferry_caps'] ?? []);
      $ferryCapsLegal = (array)($ferryCaps['legal'] ?? []);
      $ferryCapsEngine = (array)($ferryCaps['engine'] ?? []);
      $ferryHotelApproved = (float)($ferryCaps['hotel_legal_approved_amount'] ?? 0);
      $ferryHotelExcess = (float)($ferryCaps['hotel_excess_amount'] ?? 0);
      $ferryHotelManual = !empty($ferryCaps['hotel_manual_review_required']);
      $ferryHotelWeatherBlocked = !empty($ferryCaps['hotel_weather_blocked']);
      $ferryMealsManual = !empty($ferryCaps['meals_manual_review_required']);
      $ferryHotelTransportManual = !empty($ferryCaps['hotel_transport_manual_review_required']);
      $ferryAltTransportManual = !empty($ferryCaps['reroute_alt_transport_manual_review_required']);
    ?>
    <div class="card mt12" style="display:grid;gap:8px;">
      <strong>Ferry okonomi</strong>
      <div class="small muted">TRIN 10 viser ferry-kravet i adskilte spande: Art. 18 refund/ombooking, Art. 17 assistance og Art. 19 ankomstkompensation. Eventuel serviceafvigelse fra TRIN 9 holdes uden for de lovbestemte ferry-artikler.</div>
      <div class="small">Billetpris fra TRIN 2: <strong><?= number_format($priceFromTicket, 2) ?> <?= h($priceCurrency) ?></strong></div>
      <div style="display:grid;gap:6px;">
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Art. 18 - Tilbagebetaling / ombooking</strong></div>
          <div class="small">Tilbagebetaling: <?= number_format($refundAmount, 2) ?> <?= h($summaryCurrency) ?><?= $refundBasis !== '' ? ' - ' . h($refundBasis) : '' ?></div>
          <div class="small">Retur til afgangshavn/udgangspunkt: <?= number_format($returnAmount, 2) ?> <?= h($summaryCurrency) ?></div>
          <div class="small">Ekstra omkostninger ved ombooking: <?= number_format($rerouteAmount, 2) ?> <?= h($summaryCurrency) ?></div>
          <?php if ($ferryAltTransportManual): ?><div class="small muted">Kan kraeve manuel vurdering: selvbetalt alternativ transport ligger over internt standardniveau.</div><?php endif; ?>
          <?php if (!empty($ferryCaps['accommodation_migrated_from_art18'])): ?><div class="small muted">Hotel/overnatning registreret under ombooking er flyttet til Art. 17 assistance for at undgaa dobbeltoptælling.</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Art. 17 - Assistance</strong></div>
          <div class="small">Samlede assistanceudgifter: <?= number_format($expenseTotal, 2) ?> <?= h($summaryCurrency) ?></div>
          <?php if ($mealAmount > 0): ?><div class="small">Maaltider/forfriskninger: <?= number_format($mealAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($hotelAmount > 0): ?><div class="small">Hotel/overnatning: <?= number_format($hotelAmount, 2) ?> <?= h($summaryCurrency) ?><?= $ferryHotelApproved > 0 && abs($hotelAmount - $ferryHotelApproved) > 0.009 ? ' (efter legal cap: ' . number_format($ferryHotelApproved, 2) . ' ' . h($summaryCurrency) . ')' : '' ?></div><?php endif; ?>
          <?php if ($altTransportAmount > 0): ?><div class="small">Lokal transport / hoteltransport: <?= number_format($altTransportAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($otherAmount > 0): ?><div class="small">Oevrige udgifter: <?= number_format($otherAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($ferryHotelWeatherBlocked): ?><div class="small muted">Hotel/overnatning er juridisk blokeret ved vejrsikkerhedsrisiko.</div><?php endif; ?>
          <?php if ($ferryMealsManual): ?><div class="small muted">Maaltider overstiger internt standardniveau og boer vurderes manuelt.</div><?php endif; ?>
          <?php if ($ferryHotelTransportManual): ?><div class="small muted">Transport til/fra hotel overstiger internt standardniveau og boer vurderes manuelt.</div><?php endif; ?>
          <?php if ($expenseTotal <= 0): ?><div class="small muted">Ingen assistanceudgifter registreret endnu.</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Ferry caps og daekning</strong></div>
          <div class="small">Lovbestemt maksimum: hotel paa land <?= h((string)($ferryCapsLegal['hotel_land_per_night_eur'] ?? 80)) ?> EUR pr. nat i maks. <?= h((string)($ferryCapsLegal['hotel_land_max_nights'] ?? 3)) ?> naetter.</div>
          <div class="small">Lovregel: maaltider/snacks vurderes som rimelige i forhold til ventetiden.</div>
          <div class="small">Transport mellem havneterminal og hotel daekkes separat.</div>
          <div class="small">Lovregel: omlaegning uden ekstraomkostninger og refusion af billetpris med <?= h((string)($ferryCapsLegal['refund_ticket_price_percent'] ?? 100)) ?>% inden for <?= h((string)($ferryCapsLegal['refund_deadline_days'] ?? 7)) ?> dage.</div>
          <div class="small">Internt standardniveau: maaltider <?= h((string)($ferryCapsEngine['meals_per_day_eur'] ?? 40)) ?> EUR pr. dag, lokal transport <?= h((string)($ferryCapsEngine['local_transport_per_trip_eur'] ?? 50)) ?> EUR pr. tur (samlet <?= h((string)($ferryCapsEngine['total_local_transport_eur'] ?? 150)) ?> EUR).</div>
          <div class="small">Internt standardniveau: taxi <?= h((string)($ferryCapsEngine['taxi_soft_cap_eur'] ?? 150)) ?> EUR og samlet alternativ transport <?= h((string)($ferryCapsEngine['self_arranged_alt_transport_total_eur'] ?? 400)) ?> EUR.</div>
          <?php if ($ferryHotelExcess > 0): ?><div class="small muted">Kan kraeve manuel vurdering: hotelkrav over legal cap = <?= number_format($ferryHotelExcess, 2) ?> <?= h($summaryCurrency) ?>.</div><?php endif; ?>
          <?php if ($ferryHotelManual || $ferryMealsManual || $ferryHotelTransportManual || $ferryAltTransportManual): ?><div class="small muted">Kan kraeve manuel vurdering: mindst et ferry-krav ligger over lovbestemt maksimum eller internt standardniveau.</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Art. 19 - Kompensation</strong></div>
          <div class="small">Kompensation: <?= number_format($compAmount, 2) ?> <?= h($summaryCurrency) ?> - <?= h((string)$compPct) ?>%<?= $compBasis !== '' ? ' - ' . h($compBasis) : '' ?></div>
          <div class="small muted">Dette er ferry-beloebet ud fra billetprisen fra TRIN 2 og det aktuelle Art. 19-band.</div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Serviceafvigelse / prisdifference (TRIN 9)</strong></div>
          <?php if ($ferryServiceDeviation): ?>
            <div class="small">Der er registreret en serviceafvigelse i TRIN 9.</div>
            <div class="small muted">Denne post holdes adskilt fra Art. 17, 18 og 19 og maa vurderes manuelt eller kontraktretligt. Den er ikke medregnet i de lovbestemte ferry-totaler ovenfor.</div>
          <?php else: ?>
            <div class="small muted">Ingen separat serviceafvigelse registreret.</div>
          <?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Total og udbetaling</strong></div>
          <div class="small">Samlet krav (brutto): <strong><?= number_format($grossAdjusted, 2) ?> <?= h($summaryCurrency) ?></strong></div>
          <div class="small">Servicefee <?= h((string)$serviceFeePct) ?>%: <strong><?= number_format($serviceFee, 2) ?> <?= h($summaryCurrency) ?></strong></div>
          <div class="small">Netto: <strong><?= number_format($netPayout, 2) ?> <?= h($summaryCurrency) ?></strong></div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<script>
  (function() {
    var fields = document.querySelectorAll('input[data-ferry-auto-submit="1"]');
    if (!fields.length) { return; }
    var form = fields[0].closest('form');
    if (!form) { return; }

    var timer = null;
    var submitForm = function() {
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.submit();
      }
    };

    fields.forEach(function(field) {
      var queueSubmit = function() {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(submitForm, 250);
      };
      field.addEventListener('input', queueSubmit);
      field.addEventListener('change', queueSubmit);
    });
  })();
</script>
</fieldset>
<?= $this->Form->end() ?>
<?php return; ?>
<?php endif; ?>

<?php if ($isAir): ?>
<?php
  $airClaimPartyName = (string)($airContract['primary_claim_party_name'] ?? '');
  $airClaimPartyType = (string)($airContract['primary_claim_party'] ?? '');
  $airScopeReason = (string)($airScope['scope_exclusion_reason'] ?? '');
  $airScopeApplies = array_key_exists('regulation_applies', $airScope) ? (bool)$airScope['regulation_applies'] : null;
  $airCompBand = (string)($airRights['air_comp_band'] ?? ($flags['air_comp_band'] ?? 'none'));
  $airBookedClass = strtolower(trim((string)($form['air_downgrade_booked_class'] ?? '')));
  $airFlownClass = strtolower(trim((string)($form['air_downgrade_flown_class'] ?? '')));
  $airDowngradeGate = (string)($form['downgrade_occurred'] ?? '') === 'yes'
    || (string)($form['air_downgrade_refund_percent'] ?? '') !== ''
    || ($airBookedClass !== '' && $airFlownClass !== '' && $airBookedClass !== $airFlownClass);
  $airArticle8Offered = (string)($form['air_article8_choice_offered'] ?? '');
  $airRefundScope = (string)($form['air_refund_scope'] ?? '');
  $airAltAirportTransfer = (string)($form['air_alternative_airport_transfer_needed'] ?? '');
  $airDowngradePct = (string)($form['air_downgrade_refund_percent'] ?? '');
  $airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ($airRights['air_distance_band'] ?? '')))));
  $airTicketCurrency = strtoupper(trim((string)($form['price_currency'] ?? ($meta['_auto']['price_currency']['value'] ?? ''))));
  if ($airTicketCurrency === '' || $airTicketCurrency === 'AUTO') {
    $airTicketCurrency = 'EUR';
  }
  $airPdfUrl = $this->Url->build(['controller' => 'Reimbursement', 'action' => 'official', '?' => ['template' => 'Form_air_travel/air_travel_form.pdf']]);
  $airTicketAmount = (float)($ticketPriceAmount ?? 0);
  $airRefundActive = $airRefundScope !== ''
    || !empty($airRights['gate_air_reroute_refund'])
    || !empty($airRights['gate_air_delay_refund_5h']);
  $airRefundComputedAmount = 0.0;
  $airRefundComputedBasis = '';
  if ($airRefundActive && $airTicketAmount > 0) {
    $airRefundComputedAmount = $airTicketAmount;
    $airRefundComputedBasis = match ($airRefundScope) {
      'full_ticket' => 'Art. 8(1)(a) full ticket',
      'unused_part' => 'Art. 8(1)(a) unused part',
      'unused_plus_used_if_no_longer_serves_purpose' => 'Art. 8(1)(a) unused plus used if no longer serves purpose',
      default => !empty($airRights['gate_air_delay_refund_5h'])
        ? 'Art. 8(1)(a) refund after 5h+ delay'
        : 'Art. 8(1)(a) refund',
    };
  }
  $airCompFlatAmount = match ($airDistanceBand) {
    'up_to_1500' => 250.0,
    'intra_eu_over_1500', 'other_1500_to_3500' => 400.0,
    'other_over_3500' => 600.0,
    default => 250.0,
  };
  $airCompReductionThresholdMinutes = match ($airDistanceBand) {
    'up_to_1500' => 120,
    'intra_eu_over_1500', 'other_1500_to_3500' => 180,
    'other_over_3500' => 240,
    default => 120,
  };
  $airRerouteArrivalDelayMinutes = is_numeric($form['reroute_arrival_delay_minutes'] ?? null)
    ? (int)$form['reroute_arrival_delay_minutes']
    : 0;
  $airCompReductionPct = (!empty($airRights['gate_air_compensation'])
      && $airRerouteArrivalDelayMinutes > 0
      && $airRerouteArrivalDelayMinutes <= $airCompReductionThresholdMinutes)
    ? 50
    : 0;
  $airCompComputedAmount = !empty($airRights['gate_air_compensation'])
    ? round($airCompFlatAmount * (($airCompReductionPct > 0 ? 50 : 100) / 100), 2)
    : 0.0;
  $airCompComputedBasis = !empty($airRights['gate_air_compensation'])
    ? ('Art. 7 EC261 flat amount' . ($airCompReductionPct > 0 ? ' - reduced 50% under Art. 7(2)' : ''))
    : '';
  $airCompComputedLabel = match ($airDistanceBand) {
    'up_to_1500' => '250 EUR',
    'intra_eu_over_1500', 'other_1500_to_3500' => '400 EUR',
    'other_over_3500' => '600 EUR',
    default => '250 EUR',
  };
  $airDowngradePctNum = in_array((int)$airDowngradePct, [30, 50, 75], true)
    ? (int)$airDowngradePct
    : match ($airDistanceBand) {
      'up_to_1500' => 30,
      'intra_eu_over_1500', 'other_1500_to_3500' => 50,
      'other_over_3500' => 75,
      default => 30,
    };
  $airDowngradeComputedAmount = ($airDowngradeGate && $airTicketAmount > 0)
    ? round($airTicketAmount * ($airDowngradePctNum / 100), 2)
    : 0.0;
  $airDowngradeComputedBasis = $airDowngradeGate ? 'Art. 10 EC261' : '';
?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>Fly-resultat</strong>
  <div class="small muted mt4">TRIN 10 samler scope, bookingtype og aktive flight-rettigheder. Brug resultatet til claim-assist og til at skelne mellem protected connection og self-transfer.</div>
  <ul class="small mt8">
    <li>Scope: <strong><?= $airScopeApplies === true ? 'omfattet' : ($airScopeApplies === false ? 'ikke omfattet' : 'uklart') ?></strong><?= $airScopeReason !== '' ? ' - ' . h($airScopeReason) : '' ?></li>
    <li>Connection type: <strong><?= h((string)($airContract['air_connection_type'] ?? 'unknown')) ?></strong></li>
    <li>Claim-kanal: <strong><?= h($airClaimPartyName !== '' ? $airClaimPartyName : ($airClaimPartyType !== '' ? $airClaimPartyType : 'manual_review')) ?></strong></li>
    <li>Care: <strong><?= !empty($airRights['gate_air_care']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Reroute / refund: <strong><?= $airRefundActive ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Kompensation: <strong><?= !empty($airRights['gate_air_compensation']) ? 'mulig' : 'ikke aktiveret' ?></strong><?= $airCompBand !== '' && $airCompBand !== 'none' ? ' - ' . h($airCompBand) : '' ?></li>
    <?php if (!empty($claimDirection['recommended_documents'])): ?>
      <li>Anbefalet dokumentation: <strong><?= h(implode(', ', (array)$claimDirection['recommended_documents'])) ?></strong></li>
    <?php endif; ?>
  </ul>
  <div class="small muted mt8">
    Naeste handling:
    <strong><?=
      $airRefundActive
        ? 'gaa til refund eller ombooking'
        : (!empty($airRights['gate_air_care'])
            ? 'registrer maaltider, hotel og anden care'
            : (!empty($airRights['gate_air_compensation'])
                ? 'byg data-pack og forbered kompensationsclaim'
                : 'afklar bookingtype, scope eller manuel vurdering'))
    ?></strong>
  </div>
  <?php if (!empty($airRights['gate_air_compensation'])): ?>
    <div class="ok mt8 small">Sagen peger paa flight-kompensation som kandidat. Brug data-pack og flightdokumentation til claim-assist eller videre juridisk vurdering.</div>
  <?php elseif (!empty($airRights['compensation_block_reason']) && $airRights['compensation_block_reason'] !== 'none'): ?>
    <div class="hl mt8 small">Kompensation er foreloebigt blokeret af: <strong><?= h((string)$airRights['compensation_block_reason']) ?></strong>.</div>
  <?php else: ?>
    <div class="hl mt8 small">Brug data-pack og claim-kanalen ovenfor som grundlag for claim-assist, mens flight-sagen afklares yderligere.</div>
  <?php endif; ?>
  <?php if (!empty($claim)): ?>
    <?php
      $br = (array)($claim['breakdown'] ?? []);
      $tot = (array)($claim['totals'] ?? []);
      $summaryCurrency = (string)($tot['currency'] ?? ($priceCurrency !== '' ? $priceCurrency : 'EUR'));
      $airExpenseTotal = (float)($br['expenses']['total'] ?? 0);
      $airMealAmount = (float)($br['expenses']['meals'] ?? 0);
      $airHotelAmount = (float)($br['expenses']['hotel'] ?? 0);
      $airAltTransportAmount = (float)($br['expenses']['alt_transport'] ?? 0);
      $airOtherAmount = (float)($br['expenses']['other'] ?? 0);
      $airReturnToOriginAmount = (float)($br['art18']['return_to_origin'] ?? 0);
      $airRerouteExtraAmount = (float)($br['art18']['reroute_extra_costs'] ?? 0);
      $airReturnToOriginDisplayAmount = $airReturnToOriginAmount > 0 ? $airReturnToOriginAmount : (((string)($form['return_to_origin_expense'] ?? '') === 'yes' && $returnAmt > 0) ? $returnAmt : 0.0);
      $airReturnToOriginDisplayCurrency = $airReturnToOriginAmount > 0 ? $summaryCurrency : ($returnCur !== '' ? $returnCur : $summaryCurrency);
      $airUseNewExpense = ($airRerouteExpenseFlag === 'yes' && $airRerouteExpenseItems !== []);
      $airRerouteExtraDisplayAmount = 0.0;
      $airRerouteExtraDisplayCurrency = $summaryCurrency;
      $airAltAirportTransferDisplayAmount = 0.0;
      $airAltAirportTransferDisplayCurrency = $summaryCurrency;
      $airRerouteExpenseDisplayRows = [];
      if ($airUseNewExpense) {
          foreach ($airRerouteExpenseItems as $expenseRow) {
              $rowAmount = (float)($expenseRow['amount'] ?? 0.0);
              $rowCurrency = (string)($expenseRow['currency'] ?? $summaryCurrency);
              $rowLabel = (string)($expenseRow['label'] ?? ((string)($expenseRow['type'] ?? '') ?: 'Udgift'));
              $rowDescription = trim((string)($expenseRow['description'] ?? ''));
              $rowAmountSummary = $rowAmount > 0
                ? ($rowCurrency === $summaryCurrency ? $rowAmount : ($fxConv ? ($fxConv($rowAmount, $rowCurrency, $summaryCurrency) ?? $rowAmount) : $rowAmount))
                : 0.0;
              if ((string)($expenseRow['type'] ?? '') === 'airport_transfer') {
                  $airAltAirportTransferDisplayAmount += $rowAmountSummary;
              } else {
                  $airRerouteExtraDisplayAmount += $rowAmountSummary;
              }
              if ($rowAmount > 0 || $rowDescription !== '') {
                  $airRerouteExpenseDisplayRows[] = [
                      'label' => $rowLabel,
                      'amount' => $rowAmount,
                      'currency' => $rowCurrency,
                      'description' => $rowDescription,
                  ];
              }
          }
      } else {
          $airRerouteExtraDisplayAmount = $airRerouteExtraAmount > 0 ? $airRerouteExtraAmount : (((string)($form['reroute_extra_costs'] ?? '') === 'yes' && $rerouteExtraAmount > 0) ? $rerouteExtraAmount : 0.0);
          $airRerouteExtraDisplayCurrency = $airRerouteExtraAmount > 0 ? $summaryCurrency : ($rerouteExtraCur !== '' ? $rerouteExtraCur : $summaryCurrency);
          $airAltAirportTransferDisplayAmount = (((string)($form['air_alternative_airport_transfer_needed'] ?? '') === 'yes') && $airAltAirportTransferAmt > 0)
            ? $airAltAirportTransferAmt
            : 0.0;
          $airAltAirportTransferDisplayCurrency = $airAltAirportTransferCur !== '' ? $airAltAirportTransferCur : $summaryCurrency;
      }
      $airRefundDisplayAmount = $airRefundComputedAmount > 0 ? ($fxConv ? ($fxConv($airRefundComputedAmount, $airTicketCurrency, $summaryCurrency) ?? $airRefundComputedAmount) : $airRefundComputedAmount) : 0.0;
      $airCompDisplayAmount = $airCompComputedAmount > 0 ? ($fxConv ? ($fxConv($airCompComputedAmount, 'EUR', $summaryCurrency) ?? $airCompComputedAmount) : $airCompComputedAmount) : 0.0;
      $airDowngradeDisplayAmount = $airDowngradeComputedAmount > 0 ? ($fxConv ? ($fxConv($airDowngradeComputedAmount, $airTicketCurrency, $summaryCurrency) ?? $airDowngradeComputedAmount) : $airDowngradeComputedAmount) : 0.0;
      $airReturnToOriginDisplayAmountSummary = $airReturnToOriginDisplayAmount > 0 ? ($airReturnToOriginDisplayCurrency === $summaryCurrency ? $airReturnToOriginDisplayAmount : ($fxConv ? ($fxConv($airReturnToOriginDisplayAmount, $airReturnToOriginDisplayCurrency, $summaryCurrency) ?? $airReturnToOriginDisplayAmount) : $airReturnToOriginDisplayAmount)) : 0.0;
      $airRerouteExtraDisplayAmountSummary = $airRerouteExtraDisplayAmount > 0 ? ($airRerouteExtraDisplayCurrency === $summaryCurrency ? $airRerouteExtraDisplayAmount : ($fxConv ? ($fxConv($airRerouteExtraDisplayAmount, $airRerouteExtraDisplayCurrency, $summaryCurrency) ?? $airRerouteExtraDisplayAmount) : $airRerouteExtraDisplayAmount)) : 0.0;
      $airAltAirportTransferDisplayAmountSummary = $airAltAirportTransferDisplayAmount > 0 ? ($airAltAirportTransferDisplayCurrency === $summaryCurrency ? $airAltAirportTransferDisplayAmount : ($fxConv ? ($fxConv($airAltAirportTransferDisplayAmount, $airAltAirportTransferDisplayCurrency, $summaryCurrency) ?? $airAltAirportTransferDisplayAmount) : $airAltAirportTransferDisplayAmount)) : 0.0;
      $airPreviewGross = round(
        max(0.0, $airExpenseTotal)
        + max(0.0, $airRefundDisplayAmount)
        + max(0.0, $airReturnToOriginDisplayAmountSummary)
        + max(0.0, $airRerouteExtraDisplayAmountSummary)
        + max(0.0, $airAltAirportTransferDisplayAmountSummary)
        + max(0.0, $airCompDisplayAmount)
        + max(0.0, $airDowngradeDisplayAmount),
        2
      );
      $airPreviewServiceFee = round($airPreviewGross * ($serviceFeePct / 100), 2);
      $airPreviewNet = round(max(0.0, $airPreviewGross - $airPreviewServiceFee), 2);
    ?>
    <div class="card mt12" style="display:grid;gap:8px;">
      <strong>Air kravspor</strong>
      <div style="display:grid;gap:6px;">
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Care claim</strong></div>
          <div class="small">Status: <?= !empty($airRights['gate_air_care']) ? 'relevant' : 'ikke aktiveret' ?></div>
          <div class="small">Samlede care-udgifter: <?= number_format($airExpenseTotal, 2) ?> <?= h($summaryCurrency) ?></div>
          <?php if ($airMealAmount > 0): ?><div class="small">Maaltider/forfriskninger: <?= number_format($airMealAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($airHotelAmount > 0): ?><div class="small">Hotel/overnatning: <?= number_format($airHotelAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($airAltTransportAmount > 0): ?><div class="small">Transport: <?= number_format($airAltTransportAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
          <?php if ($airOtherAmount > 0): ?><div class="small">Oevrige udgifter: <?= number_format($airOtherAmount, 2) ?> <?= h($summaryCurrency) ?></div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Refund / reroute claim</strong></div>
          <div class="small">Status: <?= $airRefundActive ? 'relevant' : 'ikke aktiveret' ?></div>
          <div class="small">Article 8-valg tilbudt: <?= h($airArticle8Offered !== '' ? $airArticle8Offered : '-') ?></div>
          <?php if ((string)($form['air_self_arranged_reroute'] ?? '') !== ''): ?><div class="small">Selvarrangeret loesning: <?= h((string)$form['air_self_arranged_reroute']) ?></div><?php endif; ?>
          <?php if ((string)($form['air_airline_confirmed_self_arranged_solution'] ?? '') !== ''): ?><div class="small">Bekraeftet af flyselskabet: <?= h((string)$form['air_airline_confirmed_self_arranged_solution']) ?></div><?php endif; ?>
          <div class="small">Refusion / refund: <?= number_format($airRefundComputedAmount, 2) ?> <?= h($airTicketCurrency) ?><?= $airRefundComputedBasis !== '' ? ' - ' . h($airRefundComputedBasis) : '' ?></div>
          <?php if ($airReturnToOriginDisplayAmount > 0): ?><div class="small">Retur til udgangspunkt: <?= number_format($airReturnToOriginDisplayAmount, 2) ?> <?= h($airReturnToOriginDisplayCurrency) ?></div><?php endif; ?>
          <?php if ($airUseNewExpense && count($airRerouteExpenseDisplayRows) > 1): ?><div class="small">Reroute-udgifter: <?= count($airRerouteExpenseDisplayRows) ?> poster</div><?php endif; ?>
          <?php foreach ($airRerouteExpenseDisplayRows as $row): ?>
            <div class="small"><?= h((string)$row['label']) ?>: <?= number_format((float)$row['amount'], 2) ?> <?= h((string)$row['currency']) ?><?= (string)$row['description'] !== '' ? ' - ' . h((string)$row['description']) : '' ?></div>
          <?php endforeach; ?>
          <?php if ($airRerouteExtraDisplayAmount > 0): ?><div class="small">Ekstra ombookingsomkostninger: <?= number_format($airRerouteExtraDisplayAmount, 2) ?> <?= h($airRerouteExtraDisplayCurrency) ?></div><?php endif; ?>
          <?php if ($airAltAirportTransferDisplayAmount > 0): ?><div class="small">Alternativ lufthavn-transfer: <?= number_format($airAltAirportTransferDisplayAmount, 2) ?> <?= h($airAltAirportTransferDisplayCurrency) ?></div><?php elseif ($airAltAirportTransfer !== ''): ?><div class="small">Alternativ lufthavn-transfer: <?= h($airAltAirportTransfer) ?></div><?php endif; ?>
          <?php if ($airUseNewExpense && !$airRerouteExpenseDisplayRows && $airRerouteExpenseDescription !== ''): ?><div class="small muted">Beskrivelse: <?= h($airRerouteExpenseDescription) ?></div><?php endif; ?>
          <?php if ($airRefundScope !== ''): ?><div class="small">Refund scope: <?= h($airRefundScope) ?></div><?php endif; ?>
          <?php if ($airTicketAmount > 0): ?><div class="small muted">Billetpris fra TRIN 2: <?= number_format($airTicketAmount, 2) ?> <?= h($airTicketCurrency) ?></div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Compensation claim</strong></div>
          <div class="small">Status: <?= !empty($airRights['gate_air_compensation']) ? 'mulig' : 'ikke aktiveret' ?></div>
          <div class="small">Kompensation: <?= number_format($airCompComputedAmount, 2) ?> EUR<?= $airCompComputedBasis !== '' ? ' - ' . h($airCompComputedBasis) : '' ?></div>
          <?php if (!empty($airRights['gate_air_compensation'])): ?><div class="small muted">Distancekategori giver normalt <strong><?= h($airCompComputedLabel) ?></strong><?= $airCompReductionPct > 0 ? ' efter reroute-reduktion' : '' ?>.</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
          <div class="small"><strong>Downgrade claim</strong></div>
          <div class="small">Status: <?= $airDowngradeGate ? 'relevant' : 'ikke aktiveret' ?></div>
          <div class="small">Artikel 10-refusion: <?= number_format($airDowngradeComputedAmount, 2) ?> <?= h($airTicketCurrency) ?></div>
          <?php if ($airDowngradeGate): ?><div class="small">Refusionsprocent: <?= h((string)$airDowngradePctNum) ?>%</div><?php endif; ?>
          <?php if ($airBookedClass !== '' || $airFlownClass !== ''): ?><div class="small">Kabineklasse: <?= h($airBookedClass !== '' ? $airBookedClass : '-') ?> -> <?= h($airFlownClass !== '' ? $airFlownClass : '-') ?></div><?php endif; ?>
          <?php if ($airDowngradeComputedBasis !== ''): ?><div class="small">Basis: <?= h($airDowngradeComputedBasis) ?></div><?php endif; ?>
        </div>
        <?php if ($airPreviewGross > 0 || $airPreviewServiceFee > 0 || $airPreviewNet > 0): ?>
          <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
            <div class="small"><strong>Total og avance</strong></div>
            <div class="small mt4">Samlet krav (brutto): <strong><?= number_format($airPreviewGross, 2) ?> <?= h($summaryCurrency) ?></strong></div>
            <div class="small">Servicefee <?= h((string)$serviceFeePct) ?>%: <strong><?= number_format($airPreviewServiceFee, 2) ?> <?= h($summaryCurrency) ?></strong></div>
            <div class="small">Netto til kunde: <strong><?= number_format($airPreviewNet, 2) ?> <?= h($summaryCurrency) ?></strong></div>
          </div>
        <?php endif; ?>
      </div>
      <div class="mt12" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <?= $this->Form->hidden('air_postcode_city', ['value' => trim((string)($form['address_postalCode'] ?? '') . ' ' . (string)($form['address_city'] ?? ''))]) ?>
        <?= $this->Form->hidden('air_signature_name', ['value' => trim((string)($form['firstName'] ?? '') . ' ' . (string)($form['lastName'] ?? ''))]) ?>
        <?= $this->Form->hidden('signature_date', ['value' => date('Y-m-d')]) ?>
        <?= $this->Form->hidden('air_refund_amount', ['value' => number_format($airRefundComputedAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_compensation_amount', ['value' => number_format($airCompComputedAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_downgrade_amount', ['value' => number_format($airDowngradeComputedAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_care_total', ['value' => number_format($airExpenseTotal, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_meals_amount', ['value' => number_format($airMealAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_hotel_amount', ['value' => number_format($airHotelAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_alt_transport_amount', ['value' => number_format($airAltTransportAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_alternative_airport_transfer_amount', ['value' => number_format($airAltAirportTransferDisplayAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_alternative_airport_transfer_currency', ['value' => (string)$airAltAirportTransferDisplayCurrency]) ?>
        <?= $this->Form->hidden('air_other_amount', ['value' => number_format($airOtherAmount, 2, '.', '')]) ?>
        <?= $this->Form->hidden('air_downgrade_booked_first', ['value' => $airBookedClass === 'first' ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_downgrade_booked_business', ['value' => $airBookedClass === 'business' ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_downgrade_flown_business', ['value' => $airFlownClass === 'business' ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_downgrade_flown_economy', ['value' => $airFlownClass === 'economy' ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_refund_active', ['value' => $airRefundActive ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_compensation_active', ['value' => !empty($airRights['gate_air_compensation']) ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_downgrade_active', ['value' => $airDowngradeGate ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_delay_incident', ['value' => (($form['incident_main'] ?? '') === 'delay') ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_cancellation_incident', ['value' => (($form['incident_main'] ?? '') === 'cancellation') ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_denied_boarding_incident', ['value' => (($form['incident_main'] ?? '') === 'denied_boarding') ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_missed_connection_incident', ['value' => (!empty($form['missed_connection']) || !empty($form['reason_missed_conn'])) ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_meals_offered', ['value' => (string)($form['meal_offered'] ?? '')]) ?>
        <?= $this->Form->hidden('air_refreshments_offered', ['value' => (string)($form['meal_offered'] ?? '')]) ?>
        <?= $this->Form->hidden('air_hotel_offered', ['value' => (string)($form['hotel_offered'] ?? '')]) ?>
        <?= $this->Form->hidden('air_hotel_transport_included', ['value' => (string)($form['assistance_hotel_transport_included'] ?? '')]) ?>
        <?= $this->Form->hidden('air_next_day_departure', ['value' => (string)($form['air_next_day_departure'] ?? '')]) ?>
        <?= $this->Form->hidden('air_transfer_offered', ['value' => (!empty($form['blocked_train_alt_transport']) || !empty($form['alt_transport_provided'])) ? 'yes' : '']) ?>
        <?= $this->Form->hidden('air_communication_offered', ['value' => '']) ?>
        <?= $this->Form->hidden('air_other_services_offered', ['value' => '']) ?>
        <?= $this->Form->hidden('air_article8_choice_offered', ['value' => (string)($form['air_article8_choice_offered'] ?? '')]) ?>
        <?= $this->Form->hidden('air_refund_scope', ['value' => (string)($form['air_refund_scope'] ?? '')]) ?>
        <?= $this->Form->hidden('arrival_delay_minutes', ['value' => (string)($form['arrival_delay_minutes'] ?? '')]) ?>
        <?= $this->Form->hidden('scheduled_journey_duration_minutes', ['value' => (string)($form['scheduled_journey_duration_minutes'] ?? '')]) ?>
        <?= $this->Form->hidden('air_downgrade_booked_class', ['value' => (string)($form['air_downgrade_booked_class'] ?? '')]) ?>
        <?= $this->Form->hidden('air_downgrade_flown_class', ['value' => (string)($form['air_downgrade_flown_class'] ?? '')]) ?>
        <?= $this->Form->hidden('air_downgrade_refund_percent', ['value' => (string)($form['air_downgrade_refund_percent'] ?? '')]) ?>
        <?= $this->Form->hidden('air_distance_band', ['value' => (string)($form['air_distance_band'] ?? '')]) ?>
        <button type="submit" class="button button-primary" formaction="<?= h($airPdfUrl) ?>" formtarget="_blank">
          Udfyld air-formular (PDF)
        </button>
      </div>
    </div>
  <?php endif; ?>
</div>
</fieldset>
<?= $this->Form->end() ?>
<?php return; ?>
<?php endif; ?>

<?php if ($isBus): ?>
<?php
  $claimPartyName = (string)($busContract['primary_claim_party_name'] ?? '');
  $claimPartyType = (string)($busContract['primary_claim_party'] ?? '');
  $busClaimPdfUrl = $this->Url->build(['controller' => 'Reimbursement', 'action' => 'official', '?' => ['template' => 'Form_bus_claim_letter.pdf']]);
  $scopeReason = (string)($busScope['scope_exclusion_reason'] ?? '');
  $scopeApplies = array_key_exists('regulation_applies', $busScope) ? (bool)$busScope['regulation_applies'] : null;
  $busCompBand = (string)($busRights['bus_comp_band'] ?? ($flags['bus_comp_band'] ?? 'none'));
  $busClaimBreakdown = (array)($claim['breakdown'] ?? []);
  $busClaimTotals = (array)($claim['totals'] ?? []);
  $busCurrency = (string)($busClaimTotals['currency'] ?? ($priceCurrency !== '' ? $priceCurrency : 'EUR'));
  $busCaps = (array)($busClaimBreakdown['bus_caps'] ?? []);
  $busRefundAmount = (float)($busClaimBreakdown['refund']['amount'] ?? 0);
  $busRefundBasis = (string)($busClaimBreakdown['refund']['basis'] ?? '');
  $busCompAmount = (float)($busClaimBreakdown['compensation']['amount'] ?? 0);
  $busCompPct = (float)($busClaimBreakdown['compensation']['pct'] ?? 0);
  $busCompBasis = (string)($busClaimBreakdown['compensation']['basis'] ?? '');
  $busExpenseTotal = (float)($busClaimBreakdown['expenses']['total'] ?? 0);
  $busMealsAmount = (float)($busClaimBreakdown['expenses']['meals'] ?? 0);
  $busHotelAmount = (float)($busClaimBreakdown['expenses']['hotel'] ?? 0);
  $busHotelTransportAmount = (float)($busClaimBreakdown['expenses']['hotel_transport'] ?? 0);
  $busAltTransportAmount = (float)($busClaimBreakdown['expenses']['alt_transport'] ?? 0);
  $busOtherExpenseAmount = (float)($busClaimBreakdown['expenses']['other'] ?? 0);
  $busArt18 = (array)($busClaimBreakdown['art18'] ?? []);
  $busRerouteExtraAmount = (float)($busArt18['reroute_extra_costs'] ?? 0);
  $busReturnToOriginAmount = (float)($busArt18['return_to_origin'] ?? 0);
  $busDowngradeAnnexIIAmount = (float)($busArt18['downgrade_annexii'] ?? 0);
  $busTicketAmount = (float)($ticketPriceAmount ?? 0);
  $busHotelLegalCap = (float)($busCaps['hotel_legal_cap_amount'] ?? 0);
  $busHotelLegalPerNightEur = (float)($busCaps['hotel_legal_per_night_eur'] ?? 80);
  $busHotelRequested = (float)($busCaps['hotel_requested_amount'] ?? 0);
  $busHotelCapApplied = !empty($busCaps['hotel_cap_applied']);
  $busMealsSoftCap = (float)($busCaps['meals_soft_cap_amount'] ?? 0);
  $busHotelTransportSoftCap = (float)($busCaps['hotel_transport_soft_cap_amount'] ?? 0);
  $busAltTransportSoftCap = (float)($busCaps['alt_transport_soft_cap_amount'] ?? 0);
  $busBreakdownFullCoverage = !empty($busCaps['breakdown_full_coverage']);
  $busDistanceForCap = isset($busCaps['soft_cap_basis_distance_km']) && is_numeric($busCaps['soft_cap_basis_distance_km']) ? (int)$busCaps['soft_cap_basis_distance_km'] : null;
  $busDelayHoursForCap = isset($busCaps['soft_cap_delay_hours']) && is_numeric($busCaps['soft_cap_delay_hours']) ? (int)$busCaps['soft_cap_delay_hours'] : null;
?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>Bus-resultat</strong>
  <div class="small muted mt4">TRIN 10 samler scope, claim-kanal og aktive busrettigheder. Resultatet bruges til claim-assist og til at afgore om sagen skal videre til refund, assistance eller manuel vurdering.</div>
  <ul class="small mt8">
    <li>Scope: <strong><?= $scopeApplies === true ? 'omfattet' : ($scopeApplies === false ? 'ikke omfattet' : 'uklart') ?></strong><?= $scopeReason !== '' ? ' - ' . h($scopeReason) : '' ?></li>
    <li>Claim-kanal: <strong><?= h($claimPartyName !== '' ? $claimPartyName : ($claimPartyType !== '' ? $claimPartyType : 'manual_review')) ?></strong></li>
    <li>Refund / ombooking: <strong><?= !empty($busRights['gate_bus_reroute_refund']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Assistance: <strong><?= !empty($busRights['gate_bus_assistance_refreshments']) || !empty($busRights['gate_bus_assistance_hotel']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>50% kompensation: <strong><?= !empty($busRights['gate_bus_compensation_50']) ? 'relevant' : 'ikke aktiveret' ?></strong><?= $busCompBand !== '' && $busCompBand !== 'none' ? ' - ' . h($busCompBand) . '%' : '' ?></li>
    <li>Manual review: <strong><?= !empty($busContract['manual_review_required']) || !empty($busRights['manual_review_required']) ? 'ja' : 'nej' ?></strong></li>
    <?php if (!empty($claimDirection['recommended_documents'])): ?>
      <li>Anbefalet dokumentation: <strong><?= h(implode(', ', (array)$claimDirection['recommended_documents'])) ?></strong></li>
    <?php endif; ?>
  </ul>
  <div class="mt8">
    <button type="submit" class="button button-primary" formaction="<?= h($busClaimPdfUrl) ?>" formtarget="_blank">
      Download bus claim letter (PDF)
    </button>
  </div>
  <?php if ($busCompAmount > 0 || $busTicketAmount > 0): ?>
    <div class="card mt8" style="border-color:#e5e7eb;background:#fff;">
      <strong>Bus-kompensation</strong>
      <div class="small mt4">Billetpris fra TRIN 2: <strong><?= number_format($busTicketAmount, 2) ?> <?= h($busCurrency) ?></strong></div>
      <div class="small">Kompensation: <strong><?= number_format($busCompAmount, 2) ?> <?= h($busCurrency) ?></strong><?= $busCompPct > 0 ? ' - ' . h((string)$busCompPct) . '%' : '' ?><?= $busCompBasis !== '' ? ' - ' . h($busCompBasis) : '' ?></div>
      <?php if (!empty($busRights['gate_bus_compensation_50'])): ?>
        <div class="small muted mt4">Beløbet er den beregnede 50%-kompensation på baggrund af billettens pris og de aktive busgates.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($busCaps !== []): ?>
    <div class="card mt8" style="border-color:#e5e7eb;background:#fff;">
      <strong>Bus caps og dækning</strong>
      <div class="small mt4">Hotel følger lovens loft paa <strong>80 EUR pr. nat, maks. 2 naetter</strong>. Maaltider, transport til/fra hotel og alternativ transport vises med interne standardsatser som vejledende cap.</div>
      <?php if ($busHotelLegalCap > 0): ?>
        <div class="small mt4">Hotel-loft i denne sag: <strong><?= number_format($busHotelLegalPerNightEur, 2) ?> EUR pr. nat</strong><?= !empty($busCaps['hotel_capped_nights']) ? ' (' . h((string)$busCaps['hotel_capped_nights']) . ' nat' . ((int)$busCaps['hotel_capped_nights'] === 1 ? '' : 'ter') . ')' : '' ?></div>
      <?php endif; ?>
      <?php if ($busHotelCapApplied): ?>
        <div class="small">Registreret hoteludgift: <?= number_format($busHotelRequested, 2) ?> <?= h($busCurrency) ?>. Refunderbar hoteldel er capped til <?= number_format($busHotelAmount, 2) ?> <?= h($busCurrency) ?>.</div>
      <?php endif; ?>
      <?php if ($busMealsSoftCap > 0): ?>
        <div class="small mt4">Maaltider soft cap: <?= number_format($busMealsSoftCap, 2) ?> <?= h($busCurrency) ?><?= $busDelayHoursForCap !== null ? ' (' . h((string)$busDelayHoursForCap) . ' forsinkelsestime' . ($busDelayHoursForCap === 1 ? '' : 'r') . ')' : '' ?>.</div>
      <?php endif; ?>
      <?php if ($busHotelTransportSoftCap > 0): ?>
        <div class="small">Transport til/fra hotel soft cap: <?= number_format($busHotelTransportSoftCap, 2) ?> <?= h($busCurrency) ?>.</div>
      <?php endif; ?>
      <?php if ($busAltTransportSoftCap > 0): ?>
        <div class="small">Alternativ transport soft cap: <?= number_format($busAltTransportSoftCap, 2) ?> <?= h($busCurrency) ?><?= $busDistanceForCap !== null ? ' (distancegrundlag: ' . h((string)$busDistanceForCap) . ' km)' : '' ?>.</div>
      <?php endif; ?>
      <?php if ($busBreakdownFullCoverage): ?>
        <div class="ok mt8 small">Busnedbrud er markeret. Videre transport til nyt koeretoej, terminal eller afventningssted behandles derfor som full coverage-spor.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($busExpenseTotal > 0 || $busHotelTransportAmount > 0 || $busRefundAmount > 0 || $busRerouteExtraAmount > 0 || $busReturnToOriginAmount > 0 || $busDowngradeAnnexIIAmount > 0): ?>
    <div class="card mt8" style="border-color:#e5e7eb;background:#fff;">
      <strong>Udgifter og refusion</strong>
      <?php if ($busRefundAmount > 0): ?>
        <div class="small mt4">Refund: <strong><?= number_format($busRefundAmount, 2) ?> <?= h($busCurrency) ?></strong><?= $busRefundBasis !== '' ? ' - ' . h($busRefundBasis) : '' ?></div>
      <?php endif; ?>
      <?php if ($busExpenseTotal > 0): ?>
        <div class="small mt4">Samlede assistanceudgifter: <strong><?= number_format($busExpenseTotal, 2) ?> <?= h($busCurrency) ?></strong></div>
        <?php if ($busMealsAmount > 0): ?><div class="small">Måltider/forfriskninger: <?= number_format($busMealsAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
        <?php if ($busHotelAmount > 0): ?><div class="small">Hotel/overnatning: <?= number_format($busHotelAmount, 2) ?> <?= h($busCurrency) ?><?php if ($busHotelCapApplied): ?> <span class="muted">(efter legal cap)</span><?php endif; ?></div><?php endif; ?>
        <?php if ($busHotelTransportAmount > 0): ?><div class="small">Transport til/fra hotel: <?= number_format($busHotelTransportAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
        <?php if ($busAltTransportAmount > 0): ?><div class="small">Alternativ transport: <?= number_format($busAltTransportAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
        <?php if ($busOtherExpenseAmount > 0): ?><div class="small">Øvrige udgifter: <?= number_format($busOtherExpenseAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
      <?php endif; ?>
      <?php if ($busRerouteExtraAmount > 0 || $busReturnToOriginAmount > 0 || $busDowngradeAnnexIIAmount > 0): ?>
        <div class="small mt4"><strong>Art. 18 ekstraudgifter</strong></div>
        <?php if ($busRerouteExtraAmount > 0): ?><div class="small">Ekstra ombookingsomkostninger: <?= number_format($busRerouteExtraAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
        <?php if ($busReturnToOriginAmount > 0): ?><div class="small">Retur til afgangssted: <?= number_format($busReturnToOriginAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
        <?php if ($busDowngradeAnnexIIAmount > 0): ?><div class="small">Nedgradering: <?= number_format($busDowngradeAnnexIIAmount, 2) ?> <?= h($busCurrency) ?></div><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($grossAdjusted > 0 || $serviceFee > 0 || $netPayout > 0): ?>
    <div class="card mt8" style="border-color:#e5e7eb;background:#fff;">
      <strong>Total og avance</strong>
      <div class="small mt4">Samlet krav (brutto): <strong><?= number_format($grossAdjusted, 2) ?> <?= h($busCurrency) ?></strong></div>
      <div class="small">Servicefee <?= h((string)$serviceFeePct) ?>%: <strong><?= number_format($serviceFee, 2) ?> <?= h($busCurrency) ?></strong></div>
      <div class="small">Netto til kunde: <strong><?= number_format($netPayout, 2) ?> <?= h($busCurrency) ?></strong></div>
    </div>
  <?php endif; ?>
  <div class="small muted mt8">
    Naeste handling:
    <strong><?=
      !empty($busRights['gate_bus_reroute_refund'])
        ? 'gaa til refund eller ombooking'
        : ((!empty($busRights['gate_bus_assistance_refreshments']) || !empty($busRights['gate_bus_assistance_hotel']))
            ? 'registrer assistance og egne udgifter'
            : ((!empty($busContract['manual_review_required']) || !empty($busRights['manual_review_required']))
                ? 'send sagen til manuel vurdering'
                : 'byg data-pack og afklar buskravet videre'))
    ?></strong>
  </div>
  <?php if (!empty($busRights['gate_bus_compensation_50'])): ?>
    <div class="ok mt8 small">Sagen peger paa 50% buskompensation, hvis operatoeren ikke tilbod et valg mellem ombooking og tilbagebetaling.</div>
  <?php elseif (!empty($busRights['compensation_block_reason']) && $busRights['compensation_block_reason'] !== 'none'): ?>
    <div class="hl mt8 small">Kompensation er foreloebigt blokeret af: <strong><?= h((string)$busRights['compensation_block_reason']) ?></strong>.</div>
  <?php else: ?>
    <div class="hl mt8 small">Brug data-pack og claim-kanalen ovenfor som grundlag for claim-assist, mens bus-sagen afklares yderligere.</div>
  <?php endif; ?>
</div>
</fieldset>
<?= $this->Form->end() ?>
<?php return; ?>
<?php endif; ?>

<?php if (!empty($seasonMode)): ?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong><?= __('Abonnement/Periodekort (Art. 19, stk. 2)') ?></strong>
  <div class="small mt4"><?= __('For indehavere af abonnement/pendler-/periodekort kan gentagne forsinkelser eller aflysninger i gyldighedsperioden udløse kompensation efter operatørens ordning. Forsinkelser under 60 min kan kumuleres.') ?></div>
  <?php $seasonMeta = (array)($meta['season_pass'] ?? []); ?>
  <?php if (!empty($seasonMeta)): ?>
    <div class="small mt8" style="display:flex;gap:12px;flex-wrap:wrap;">
      <?php if (!empty($seasonMeta['operator'])): ?><span><?= __('Operatør:') ?> <strong><?= h((string)$seasonMeta['operator']) ?></strong></span><?php endif; ?>
      <?php if (!empty($seasonMeta['type'])): ?><span><?= __('Type:') ?> <strong><?= h((string)$seasonMeta['type']) ?></strong></span><?php endif; ?>
      <?php if (!empty($seasonMeta['valid_from']) || !empty($seasonMeta['valid_to'])): ?>
        <span><?= __('Gyldighed:') ?> <strong><?= h((string)($seasonMeta['valid_from'] ?? '')) ?></strong> - <strong><?= h((string)($seasonMeta['valid_to'] ?? '')) ?></strong></span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($seasonPolicy) && is_array($seasonPolicy)): ?>
    <?php $spol = (array)$seasonPolicy; ?>
    <?php
      $cov = (string)($spol['coverage_status'] ?? '');
      $verified = !empty($spol['verified']);
      $lv = (string)($spol['last_verified'] ?? '');
      $src = (string)($spol['source_url'] ?? '');
      $ch = (array)($spol['claim_channel'] ?? []);
      $chUrl = (string)($ch['value'] ?? '');
    ?>
    <div class="small mt8">
      <div><?= __('OperatÃ¸r-ordning (matrix):') ?> <strong><?= h($verified ? 'verified' : ($cov !== '' ? $cov : 'stub')) ?></strong><?= $lv !== '' ? (' · ' . __('sidst tjekket:') . ' ' . h($lv)) : '' ?>.</div>
      <?php if ($chUrl !== ''): ?>
        <div><?= __('Claim-kanal:') ?> <a href="<?= h($chUrl) ?>" target="_blank" rel="noopener"><?= h($chUrl) ?></a></div>
      <?php endif; ?>
      <?php if ($src !== ''): ?>
        <div><?= __('Kilde:') ?> <a href="<?= h($src) ?>" target="_blank" rel="noopener"><?= h($src) ?></a></div>
      <?php endif; ?>
      <?php if ($chUrl === '' && $src === ''): ?>
        <div class="muted"><?= __('Ikke onboardet endnu â€“ brug upload/link i Trin 2 og opgradÃ©r senere i policy-matrix.') ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($seasonSummary) && is_array($seasonSummary)): ?>
    <?php $ss=(array)$seasonSummary; ?>
    <ul class="small mt8">
      <li><?= __('Antal registrerede hændelser (i denne sag/session):') ?> <strong><?= (int)($ss['count_total'] ?? 0) ?></strong></li>
      <li><?= __('Aflysninger:') ?> <strong><?= (int)($ss['count_cancel'] ?? 0) ?></strong></li>
      <li><?= __('Forsinkelser ≥ 60 min:') ?> <strong><?= (int)($ss['count_ge60'] ?? 0) ?></strong></li>
      <li><?= __('Forsinkelser 20-59 min:') ?> <strong><?= (int)($ss['count_20_59'] ?? 0) ?></strong></li>
      <li><?= __('Forsinkelser < 20 min:') ?> <strong><?= (int)($ss['count_lt20'] ?? 0) ?></strong></li>
      <li><?= __('Samlet min. under 60:') ?> <strong><?= (int)($ss['cum_minutes_lt60'] ?? 0) ?></strong> <?= __('min') ?></li>
    </ul>
    <div class="small muted"><?= __('Bemærk: Den konkrete kompensation fastsættes af jernbanevirksomhedens offentlige kompensationsordning.') ?></div>
  <?php else: ?>
    <div class="small mt8"><?= __('Hændelsen er registreret for periodekort. Gentagende mindre forsinkelser kan tælles sammen.') ?></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($nationalPolicy) && is_array($nationalPolicy)): ?>
<?php $np=(array)$nationalPolicy; $thr=(array)($np['thresholds'] ?? []); ?>
<div class="card mt12" style="border-color:#ffe8cc;background:#fff8e6">
  <strong><?= __('Nationale ordninger (domestic)') ?></strong>
  <div class="small mt4"><?= __('Denne rejse ser ud til at være indenrigs i') ?> <strong><?= h((string)($np['country'] ?? '')) ?></strong>. <?= __('National ordning:') ?> <strong><?= h((string)($np['name'] ?? '')) ?></strong><?php if(!empty($np['notes'])):?> - <?= h((string)$np['notes']) ?><?php endif; ?>.</div>
  <?php if (!empty($thr['25'])): ?>
    <div class="small mt8"><?= __('Lempet bånd:') ?> <strong><?= __('25% fra {0} min', (int)$thr['25']) ?></strong><?php if (!empty($thr['50'])): ?>; <strong><?= __('50% fra {0} min', (int)$thr['50']) ?></strong><?php endif; ?>.</div>
  <?php endif; ?>
  <!-- Pass policy metadata forward -->
  <input type="hidden" name="nationalPolicyCountry" value="<?= h((string)($np['country'] ?? '')) ?>" />
  <input type="hidden" name="nationalPolicyName" value="<?= h((string)($np['name'] ?? '')) ?>" />
  <input type="hidden" name="nationalPolicyId" value="<?= h((string)($np['id'] ?? '')) ?>" />
  <?php if (!empty($thr['25'])): ?><input type="hidden" name="nationalPolicyThreshold25" value="<?= (int)$thr['25'] ?>" /><?php endif; ?>
  <?php if (!empty($thr['50'])): ?><input type="hidden" name="nationalPolicyThreshold50" value="<?= (int)$thr['50'] ?>" /><?php endif; ?>
</div>
<?php endif; ?>

<?php if ((string)($this->getRequest()->getQuery('debug') ?? '') !== ''): ?>
<div class="card mt12" style="border-color:#cce5ff;background:#f1f8ff">
  <strong><?= __('Debug') ?></strong>
  <div class="small mt4">URL: <code><?= h($this->getRequest()->getRequestTarget()) ?></code></div>
  <ul class="small mt4">
    <li>DelayAtFinal: <strong><?= (int)$delayAtFinal ?></strong> min · AutoBand: <strong><?= h($bandAuto) ?></strong> · SelectedBand: <strong><?= h($selectedBand ?? '-') ?></strong></li>
    <li>Ticket price captured: <strong><?= number_format((float)($ticketPriceAmount ?? 0),2) ?> <?= h($currency ?? 'EUR') ?></strong></li>
    <li>Gates: refundChosen=<strong><?= $refundChosen?'true':'false' ?></strong>, preinformed=<strong><?= $preinformed?'true':'false' ?></strong>, rerouteUnder60=<strong><?= $rerouteUnder60?'true':'false' ?></strong>, art19Allowed=<strong><?= $art19Allowed?'true':'false' ?></strong></li>
    <?php if (!empty($claim)): $br=(array)$claim['breakdown']; ?>
      <li>Comp: eligible=<strong><?= !empty($br['compensation']['eligible'])?'true':'false' ?></strong>, pct=<strong><?= (int)($br['compensation']['pct'] ?? 0) ?></strong>, basis=<strong><?= h($br['compensation']['basis'] ?? '') ?></strong>, amount=<strong><?= number_format((float)($br['compensation']['amount'] ?? 0),2) ?></strong></li>
    <?php endif; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card" data-art="19" <?= $art19Enabled ? '' : 'style="opacity:0.6;"' ?> >
  <strong><?= __('Kompensations-beregning (Art. 19)') ?></strong>
  <div class="small mt8"><?= __('Grundregler') ?></div>
  <ul class="small mt4">
    <?php
      $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : 60;
      $thr50 = isset($nationalPolicy['thresholds']['50']) ? (int)$nationalPolicy['thresholds']['50'] : 120;
    ?>
    <li><?= __('Tærskel: endelig ankomstforsinkelse ≥ {0} min → 25 % (≥{0}) / 50 % (≥{1}) af prisgrundlaget.', (int)$thr25, (int)$thr50) ?></li>
    <li><?= __('Ingen kompensation hvis refusion vælges for samme tjeneste, hvis forsinkelsen var oplyst før køb, eller omlægning gav endelig forsinkelse < 60 min.') ?></li>
    <li><?= __('Prisgrundlag afhænger af kontrakt/retur/gennemgående billet (Art. 19(3)); dit Art. 12-modul afgør ved tvivl.') ?></li>
    <li><?= __('EU/ikke-EU: kun forsinkelsesminutter opstået i EU medregnes (prisgrundlag ændres ikke).') ?></li>
  </ul>
  <?php if (!empty($liableParty)): ?>
    <div class="small mt8 muted"><?= __('Ansvarlig part:') ?> <strong><?= h($liableParty === 'retailer' ? 'Billetudsteder' : 'Operatør') ?></strong><?= !empty($liableBasis) ? ' - ' . h($liableBasis) : '' ?></div>
  <?php endif; ?>
  <?php if (!empty($claim) && !empty($claim['flags']['retailer_75'])): ?>
    <div class="ok mt8"><?= __('Art. 12(4) gælder: Billetudsteder/refusionskrav for hele beløbet + kompensation på 75 % af transaktionsbeløbet (missed connection).') ?></div>
  <?php endif; ?>
  <?php if (!$art19Enabled): ?>
    <div class="bad mt8"><?= __('Kompensation (Art. 19) er undtaget for denne rejse. Sektionen låses og beløb sættes til 0.') ?></div>
  <?php elseif (!$art19Allowed): ?>
    <div class="bad mt8"><?= __('Art. 19 midlertidigt slået fra af andre gates (fx refusion valgt eller national undtagelse).') ?></div>
  <?php endif; ?>
  <?php if ($refundChosen): ?>
    <div class="bad mt8"><?= __('Du har valgt refusion efter Art. 18 — der kan ikke ydes kompensation for samme tjeneste.') ?></div>
  <?php endif; ?>
  <?php if ($preinformed): ?>
    <div class="hl mt8"><?= __('Forsinkelsen var oplyst før køb (Art. 19(9)) — kompensation kan afvises.') ?></div>
  <?php endif; ?>
  <?php if ($rerouteUnder60): ?>
    <div class="hl mt8"><?= __('Omlægning gav endelig forsinkelse < 60 min (Art. 19(9)) — kompensation kan afvises.') ?></div>
  <?php endif; ?>
</div>

<?php
  $gateArt18 = ((string)($flags['gate_art18'] ?? '')) === '1';
  $natDelayRaw = trim((string)($form['national_delay_minutes'] ?? ''));
  $hasNatDelay = ($natDelayRaw !== '');
  $knownDelay = (int)($form['delayAtFinalMinutes'] ?? $delayAtFinal);
  // EU UI is relevant when EU-gate is active OR the user has already entered >=60 minutes.
  $showEuUi = $gateArt18 || $knownDelay >= 60;
?>

<?php if ($showEuUi): ?>
  <div class="card mt12">
    <strong><?= __('1) Var forsinkelsen ved endelig destination mindst {0} min?', (int)$thr50) ?></strong>
    <div class="small mt4"><?= __('Hvis ja: 50%. Hvis nej (men mindst {0} min): 25%.', (int)$thr25) ?></div>

    <?php
      // Prefer explicit override / saved value; otherwise use minutes; otherwise default to 25% when EU is active.
      $sel = (string)($selectedBand ?? ($form['compensationBand'] ?? ''));
      if ($sel === '') {
        if ($knownDelay >= (int)$thr50) { $sel = '50'; }
        elseif ($knownDelay >= (int)$thr25) { $sel = '25'; }
      }

      $label50 = __('Ja (>= {0} min -> 50%)', (int)$thr50);
      if ((int)$thr50 > (int)$thr25) {
        $label25 = __('Nej ({0}-{1} min -> 25%)', (int)$thr25, (int)$thr50 - 1);
      } else {
        $label25 = __('Nej (>= {0} min -> 25%)', (int)$thr25);
      }
    ?>

    <label class="mt8"><input type="radio" name="compensationBand" value="50" <?= $sel==='50'?'checked':'' ?> /> <?= $label50 ?></label>
    <label class="ml8"><input type="radio" name="compensationBand" value="25" <?= $sel==='25'?'checked':'' ?> /> <?= $label25 ?></label>
    <label class="ml8"><input type="radio" name="compensationBand" value="" <?= $sel===''?'checked':'' ?> /> <?= __('Ved ikke (foreloebig)') ?></label>

    <?php if ($knownDelay > 0): ?>
      <div class="small muted mt8"><?= __('Beregnet endelig forsinkelse:') ?> <strong><?= h((string)$knownDelay) ?> <?= __('min') ?></strong></div>
      <input type="hidden" name="delayAtFinalMinutes" value="<?= h((string)$knownDelay) ?>" />
    <?php elseif ($sel === '50'): ?>
      <input type="hidden" name="delayAtFinalMinutes" value="<?= h((string)(int)$thr50) ?>" />
    <?php elseif ($sel === '25'): ?>
      <input type="hidden" name="delayAtFinalMinutes" value="<?= h((string)(int)$thr25) ?>" />
    <?php endif; ?>

    <details class="mt8">
      <summary class="small"><?= __('Avanceret: indtast minutter manuelt') ?></summary>
      <div class="small muted mt4"><?= __('Udfyld kun hvis vi ikke allerede kender den. Brug faktiske ankomsttider ved endeligt bestemmelsessted.') ?></div>
      <label class="mt8 small"><?= __('Forsinkelse (minutter)') ?>
        <input type="number" name="delayAtFinalMinutes" min="0" step="1" value="<?= h($form['delayAtFinalMinutes'] ?? '') ?>" />
      </label>
    </details>

    <script>
      // Live preview: skift baand og reload med ?band=.. for at re-beregne kompensation uden at poste videre
      (function(){
        var radios = document.querySelectorAll('input[name="compensationBand"]');
        radios.forEach(function(r){
          r.addEventListener('change', function(){
            var url = new URL(window.location.href);
            url.searchParams.set('band', this.value);
            window.location.href = url.toString();
          });
        });
      })();

      // Ferry Art. 19: submit form again when arrival delay or planned duration changes,
      // so the compensation preview reflects the latest inputs immediately.
      (function(){
        var fields = document.querySelectorAll('input[data-ferry-auto-submit="1"]');
        if (!fields.length) { return; }
        var form = fields[0].closest('form');
        if (!form) { return; }
        var timer = null;
        var submitForm = function() {
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        };
        fields.forEach(function(field){
          field.addEventListener('change', function(){
            if (timer) { clearTimeout(timer); }
            timer = setTimeout(submitForm, 150);
          });
        });
      })();
    </script>
  </div>
<?php else: ?>
  <div class="card mt12" style="border-color:#ffe8cc;background:#fff8e6">
    <strong><?= __('National beregning (preview)') ?></strong>
    <div class="small mt4"><?= __('EU-kompensation (Art. 19) udloeses typisk ved mindst 60 min (eller aflysning).') ?></div>

    <?php if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds'])): ?>
      <?php
        $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : null;
        $thr50 = isset($nationalPolicy['thresholds']['50']) ? (int)$nationalPolicy['thresholds']['50'] : null;
      ?>
      <div class="small mt8">
        <?= __('National ordning:') ?> <strong><?= h((string)($nationalPolicy['name'] ?? '')) ?></strong>
        <?php if ($thr25 !== null): ?> - <?= __('25% fra {0} min', (int)$thr25) ?><?php endif; ?>
        <?php if ($thr50 !== null): ?> (<?= __('50% fra {0} min', (int)$thr50) ?>)<?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($hasNatDelay): ?>
      <div class="small mt8"><?= __('Forsinkelse (oplyst i Trin 5):') ?> <strong><?= h($natDelayRaw) ?> <?= __('min') ?></strong></div>
    <?php else: ?>
      <div class="small mt8 muted"><?= __('Angiv forsinkelse i Trin 5 (national fallback) for at faa et bedre preview.') ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if (!empty($claim)): ?>
<div class="card mt12" style="display:grid;gap:8px;">
  <strong><?= __('Økonomi (live)') ?></strong>
  <?php $br = (array)($claim['breakdown'] ?? []); $tot = (array)($claim['totals'] ?? []); ?>
  <?php
    // Expenses: show totals in the canonical (single) summary below.
    $expVal = isset($br['expenses']['total']) ? (float)$br['expenses']['total'] : 0.0;
    $expCur = (string)($tot['currency'] ?? $priceCurrency);
    $expToTicket = ($fxConv && $expCur !== '' && $expCur !== $priceCurrency) ? $fxConv($expVal, $expCur, $priceCurrency) : null;
    $expToEur = ($fxConv && $expCur !== '' && $expCur !== 'EUR') ? $fxConv($expVal, $expCur, 'EUR') : null;

    $refundHeuristicDowngrade = (float)($br['refund']['downgrade_component'] ?? 0);
    $refundDisplay = (float)($br['refund']['amount'] ?? 0);
    $refundBasis = (string)($br['refund']['basis'] ?? '');
    $usedWholeFareFallback = false;
    if ($remedyChoice === 'refund_return' && $refundDisplay <= 0 && $priceFromTicket > 0) {
        $refundDisplay = $priceFromTicket;
        $refundBasis = $refundBasis !== '' ? $refundBasis : 'Art. 18(1)(1)';
        $usedWholeFareFallback = true;
        $refundHeuristicDowngrade = 0.0;
    }
    $refundBaseOnly = max(0.0, $refundDisplay - $refundHeuristicDowngrade);
    $returnFlag = (string)($form['return_to_origin_expense'] ?? '');
    $returnAmt = is_numeric($form['return_to_origin_amount'] ?? null)
      ? (float)$form['return_to_origin_amount']
      : (float)preg_replace('/[^0-9.]/','', (string)($form['return_to_origin_amount'] ?? '0'));
    $returnCur = (string)($form['return_to_origin_currency'] ?? $priceCurrency);
    if ($returnCur === '') { $returnCur = $priceCurrency; }
  ?>
  <div class="small mt4">
    <?= __('Billetpris:') ?> <strong><?= number_format($priceFromTicket, 2) ?> <?= h($priceCurrency) ?></strong>
  </div>
  <div style="display:grid;gap:6px;">
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 18(1) – Refusion') ?></strong></div>
      <div class="small"><?= __('Refusion:') ?> <?= number_format($refundBaseOnly, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?><?= $refundBasis ? ' - ' . h($refundBasis) : '' ?></div>
      <div class="small"><?= __('Returtransport til udgangspunktet:') ?> <?= $returnAmt > 0 ? number_format($returnAmt, 2) : '0.00' ?> <?= h($returnCur) ?>
        <?php if ($returnAmt > 0 && $fxConv && $returnCur !== $priceCurrency): ?>
          (<?= __('= {0} {1}', number_format($fxConv($returnAmt, $returnCur, $priceCurrency),2), h($priceCurrency)) ?>)
        <?php endif; ?>
        <?php if ($returnAmt > 0 && $fxConv && $returnCur !== 'EUR'): ?>
          (<?= __('= {0} EUR', number_format($fxConv($returnAmt, $returnCur, 'EUR'),2)) ?>)
        <?php endif; ?>
      </div>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 18(2)-(3) – Omlægning') ?></strong></div>
      <div class="small"><?= __('Ekstra omkostninger:') ?> <?= $rerouteExtraAmount > 0 ? number_format($rerouteExtraAmount, 2) : '0.00' ?> <?= h($rerouteExtraCur) ?>
        <?php if ($rerouteExtraAmount > 0 && $fxConv && $rerouteExtraCur !== $priceCurrency): ?>
          (<?= __('= {0} {1}', number_format($fxConv($rerouteExtraAmount, $rerouteExtraCur, $priceCurrency),2), h($priceCurrency)) ?>)
        <?php endif; ?>
        <?php if ($rerouteExtraAmount > 0 && $fxConv && $rerouteExtraCur !== 'EUR'): ?>
          (<?= __('= {0} EUR', number_format($fxConv($rerouteExtraAmount, $rerouteExtraCur, 'EUR'),2)) ?>)
        <?php endif; ?>
      </div>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Bilag II (CIV) – Nedgradering') ?></strong></div>
      <div class="small"><?= __('Nedgradering (Bilag II):') ?> <?= $downgradeAmount > 0 ? number_format($downgradeAmount,2) : '0.00' ?> <?= h($priceCurrency) ?><?php if ($downgradeAmount > 0 && $priceCurrency !== 'EUR' && $fxConv): ?> (<?= __('= {0} EUR', number_format($fxConv($downgradeAmount, $priceCurrency, 'EUR'),2)) ?>)<?php endif; ?></div>
      <?php if ($refundHeuristicDowngrade > 0): ?>
        <div class="small muted"><?= __('Auto (heuristik, hvis Bilag II ikke er udfyldt):') ?> <?= number_format($refundHeuristicDowngrade, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></div>
      <?php endif; ?>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 19 – Kompensation') ?></strong></div>
      <div class="small"><?= __('Kompensation:') ?> <?= isset($br['compensation']['amount']) ? number_format((float)$br['compensation']['amount'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?> - <?= h($br['compensation']['pct'] ?? 0) ?>%<?= !empty($br['compensation']['basis']) ? ' - ' . h($br['compensation']['basis']) : '' ?></div>
      <?php
        $compEligible = (bool)($br['compensation']['eligible'] ?? false);
        $compReasons = [];
        if (!$compEligible || !$art19Enabled || !$art19Allowed) {
            if (!$art19Enabled) { $compReasons[] = __('Art. 19 er undtaget for denne rejse.'); }
            if (!$art19Allowed) { $compReasons[] = __('Art. 19 er slået fra af gates i denne sag (fx refusion valgt eller national undtagelse).'); }
            if ($refundChosen) { $compReasons[] = __('Refusion er valgt — der kan ikke ydes kompensation for samme tjeneste.'); }
            if ($preinformed) { $compReasons[] = __('Forsinkelsen var oplyst før køb (Art. 19(9)).'); }
            if ($rerouteUnder60) { $compReasons[] = __('Omlægning gav endelig forsinkelse under 60 min (Art. 19(9)).'); }
            if (!empty($claim['flags']['extraordinary'])) { $compReasons[] = __('Ekstraordinære forhold (force majeure) er angivet.'); }
        }
      ?>
      <?php if (!empty($compReasons)): ?>
        <div class="small muted mt4"><?= __('Art. 19 er 0, fordi:') ?></div>
        <ul class="small muted" style="margin:6px 0 0 18px;">
          <?php foreach ($compReasons as $r): ?><li><?= h((string)$r) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 20 – Udgifter') ?></strong></div>
      <?php
        $pick = function(array $keys) use ($form) {
            foreach ($keys as $k) {
                if ($form[$k] ?? null) { return (float)$form[$k]; }
            }
            return 0.0;
        };
        $mealsAmt = $pick(['meal_self_paid_amount','expense_breakdown_meals','expense_meals']);
        $hotelAmt = $pick(['hotel_self_paid_amount','expense_breakdown_hotel_amount','expense_hotel']);
        $altAmt = $pick(['alt_self_paid_amount','expense_breakdown_local_transport','expense_alt_transport']);
        $blockedAltAmt = $pick(['blocked_self_paid_amount']);
        $otherAmt = $pick(['expense_breakdown_other_amounts','expense_other']);
        $mealsCur = strtoupper((string)($form['meal_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $priceCurrency)));
        $hotelCur = strtoupper((string)($form['hotel_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $priceCurrency)));
        $altCur = strtoupper((string)($form['alt_self_paid_currency'] ?? $form['blocked_self_paid_currency'] ?? ($form['expense_breakdown_currency'] ?? $priceCurrency)));
        $blockedCur = strtoupper((string)($form['blocked_self_paid_currency'] ?? $altCur));
        $otherCur = strtoupper((string)($form['expense_breakdown_currency'] ?? $priceCurrency));
        $sumAlt = max(0.0, $altAmt + $blockedAltAmt);
      ?>
      <div class="small"><?= __('Assistance/udgifter indsendt:') ?> <?= number_format($expVal, 2) ?> <?= h($expCur) ?></div>
      <?php if ($expToTicket !== null): ?>
        <div class="small"><?= __('Udgifter i billetvaluta:') ?> <?= number_format($expToTicket,2) ?> <?= h($priceCurrency) ?></div>
      <?php endif; ?>
      <?php if ($expToEur !== null): ?>
        <div class="small"><?= __('Udgifter i EUR:') ?> <?= number_format($expToEur,2) ?> EUR</div>
      <?php endif; ?>
      <div class="small mt4"><strong><?= __('Detaljer (Art. 20)') ?></strong></div>
      <?php if ($mealsAmt > 0): ?>
        <div class="small"><?= __('Måltider: {0} {1}', number_format($mealsAmt,2), h($mealsCur)) ?><?php if ($fxConv && $mealsCur !== $priceCurrency): ?> (<?= __('= {0} {1}', number_format($fxConv($mealsAmt, $mealsCur, $priceCurrency) ?? $mealsAmt,2), h($priceCurrency)) ?>)<?php endif; ?></div>
      <?php endif; ?>
      <?php if ($hotelAmt > 0): ?>
        <div class="small"><?= __('Hotel: {0} {1}', number_format($hotelAmt,2), h($hotelCur)) ?><?php if ($fxConv && $hotelCur !== $priceCurrency): ?> (<?= __('= {0} {1}', number_format($fxConv($hotelAmt, $hotelCur, $priceCurrency) ?? $hotelAmt,2), h($priceCurrency)) ?>)<?php endif; ?></div>
      <?php endif; ?>
      <?php if ($sumAlt > 0): ?>
        <div class="small"><?= __('Alt. transport: {0} {1}', number_format($sumAlt,2), h($altCur)) ?><?php if ($fxConv && $altCur !== $priceCurrency): ?> (<?= __('= {0} {1}', number_format($fxConv($sumAlt, $altCur, $priceCurrency) ?? $sumAlt,2), h($priceCurrency)) ?>)<?php endif; ?></div>
        <?php if ($blockedAltAmt > 0): ?>
          <div class="small muted"><?= __('Heraf transport væk (blokeret spor): {0} {1}', number_format($blockedAltAmt,2), h($blockedCur)) ?></div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($otherAmt > 0): ?>
        <div class="small"><?= __('Øvrige: {0} {1}', number_format($otherAmt,2), h($otherCur)) ?><?php if ($fxConv && $otherCur !== $priceCurrency): ?> (<?= __('= {0} {1}', number_format($fxConv($otherAmt, $otherCur, $priceCurrency) ?? $otherAmt,2), h($priceCurrency)) ?>)<?php endif; ?></div>
      <?php endif; ?>
      <?php if ($mealsAmt<=0 && $hotelAmt<=0 && $sumAlt<=0 && $otherAmt<=0): ?>
        <div class="small muted"><?= __('Ingen underposter angivet.') ?></div>
      <?php endif; ?>
      <?php if ($sumAlt <= 0 && !empty($br['expenses']['alt_transport']) && (float)$br['expenses']['alt_transport']>0 && !empty($br['expenses']['alt_transport_label'])): ?>
        <div class="small"><?= __('Heraf alt. transport:') ?> <?= number_format((float)$br['expenses']['alt_transport'], 2) ?> <?= h($expCur) ?> (<?= h($br['expenses']['alt_transport_label']) ?>)</div>
      <?php endif; ?>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Total & udbetaling') ?></strong></div>
      <div class="small"><?= __('Samlet krav (brutto, inkl. Art.18 stk.3):') ?> <strong><?= number_format($grossAdjusted, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
      <div class="small"><?= __('Servicefee {0}%:', $serviceFeePct) ?> <strong><?= number_format($serviceFee, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
      <div class="small"><?= __('Udbetaling inden 24t (netto):') ?> <strong><?= number_format($netPayout, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong><?= __('Formularer') ?></strong>
  <div class="small mt4"><?= __('Generér enten EU standard-formular eller en national operatørform (hvis tilgængelig) for vedhæftning.') ?></div>
  <?php
    $cc = strtolower((string)($nationalCountryCode ?? ''));
    $decision = (array)($formDecision ?? []);
    $recForm = (string)($decision['form'] ?? 'eu_standard_claim');
    $reason = (string)($decision['reason'] ?? '');
    $natList = ['fr','it','nl','es','dk','de'];
  ?>
  <div class="small mt8">
    <?= __('Anbefaling:') ?> <strong><?= $recForm==='national_claim' ? __('National formular') : __('EU standard') ?></strong>
    <?php if($reason!==''): ?><span class="muted">(<?= h($reason) ?>)</span><?php endif; ?>
  </div>
  <?php $ccOk = ($recForm==='national_claim'); ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
    <a class="button" style="background:#004085;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;" href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'official','?'=>['eu'=>'1']]) ?>" target="_blank"><?= __('EU officiel formular') ?></a>
    <?php foreach ($natList as $nat): ?>
      <a class="button" style="background:#005f5f;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;"
         href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'official','?'=>['prefer'=>'national','country'=>$nat]]) ?>" target="_blank">
         <?= __('National formular') ?> (<?= h(strtoupper($nat)) ?>)
      </a>
    <?php endforeach; ?>
    <a class="button" style="background:#6c757d;color:#fff;padding:8px 12px;border-radius:4px;text-decoration:none;" href="<?= $this->Url->build(['controller'=>'Reimbursement','action'=>'generate','?'=>['flow'=>'1']]) ?>" target="_blank"><?= __('Reimbursement Claim Summary') ?></a>
    <?php if(!$ccOk): ?><div class="small muted" style="align-self:center;"><?= __('National skabelon ikke fundet - EU anvendes.') ?></div><?php endif; ?>
  </div>
  <div class="small mt8 muted"><?= __('Efter generering kan du afslutte her; formularerne åbner i ny fane.') ?></div>
</div>

<div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
  <?= $this->Html->link('<- Tilbage', ['action' => 'downgrade'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
</div>

</fieldset>
<?= $this->Form->end() ?>

