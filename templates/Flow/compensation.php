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
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$compTitle = $isOngoing
    ? ($isFerry ? 'TRIN 10 - Faergeresultat (foreloebigt)' : 'TRIN 10 - Kompensation (foreloebig)')
    : ($isCompleted ? ($isFerry ? 'TRIN 10 - Faergeresultat (afsluttet rejse)' : 'TRIN 10 - Kompensation (afsluttet rejse)') : ($isFerry ? 'TRIN 10 - Faergeresultat' : 'TRIN 10 - Kompensation (Art. 19)'));
$compHint = $isOngoing
    ? ($isFerry ? 'Resultatet kan aendre sig, naar den faktiske ankomstforsinkelse er kendt.' : 'Beregningen kan aendre sig, naar rejsen er afsluttet.')
    : ($isCompleted ? ($isFerry ? 'Resultatet er baseret paa den afsluttede faergerejse.' : 'Beregningen er baseret paa den afsluttede rejse.') : '');
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
$downgradeBasis = (string)($form['downgrade_comp_basis'] ?? '');
$downgradeShare = is_numeric($form['downgrade_segment_share'] ?? null)
    ? (float)$form['downgrade_segment_share']
    : (float)($form['downgrade_segment_share'] ?? 1);
$downgradeShare = max(0.0, min(1.0, $downgradeShare));
$rateMap = ['seat' => 0.25, 'couchette' => 0.50, 'sleeper' => 0.75];
$downgradeRate = $rateMap[$downgradeBasis] ?? 0.0;

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
if ($downgradeRate <= 0 && $countLegs > 0) {
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
  $ferryScopeReason = (string)($ferryScope['scope_exclusion_reason'] ?? '');
  $ferryApplies = array_key_exists('regulation_applies', $ferryScope) ? (bool)$ferryScope['regulation_applies'] : true;
?>
<div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
  <strong>Faerge-resultat</strong>
  <div class="small muted mt4">TRIN 10 viser her claim-assist og den juridiske retning for ferry. Den endelige pengeudregning er ikke koblet paa samme maade som rail endnu.</div>
  <?php if ($ferryClaimName !== ''): ?>
    <div class="small mt8">Primær claim-kanal: <strong><?= h($ferryClaimName) ?></strong><?= $ferryClaimType !== '' ? ' (' . h($ferryClaimType) . ')' : '' ?></div>
  <?php endif; ?>
  <ul class="small mt8">
    <li>Scope: <strong><?= $ferryApplies ? 'omfattet' : 'ikke omfattet' ?></strong><?= $ferryScopeReason !== '' ? ' - ' . h($ferryScopeReason) : '' ?></li>
    <li>Art. 16 information: <strong><?= !empty($ferryRights['gate_art16_notice']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 17 assistance: <strong><?= !empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 18 tilbagebetaling/ombooking: <strong><?= !empty($ferryRights['gate_art18']) ? 'relevant' : 'ikke aktiveret' ?></strong></li>
    <li>Art. 19 kompensation: <strong><?= !empty($ferryRights['gate_art19']) ? 'relevant' : 'ikke aktiveret' ?></strong><?= $ferryBand !== '' && $ferryBand !== 'none' ? ' - band ' . h($ferryBand) . '%' : '' ?></li>
  </ul>
  <?php if (!empty($ferryRights['gate_art19']) && $ferryBand !== 'none'): ?>
    <div class="ok mt8 small">Ankomstforsinkelsen peger paa ferry Art. 19 med et foreloebigt bånd paa <strong><?= h($ferryBand) ?>%</strong>.</div>
  <?php elseif (!$ferryApplies): ?>
    <div class="hl mt8 small">Faergeforordningen ser ikke ud til at finde anvendelse paa denne sejlads ud fra de nuvaerende scope-oplysninger.</div>
  <?php else: ?>
    <div class="hl mt8 small">Brug data-pack og de aktive gates ovenfor som grundlag for claim-assist eller manuel vurdering.</div>
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

