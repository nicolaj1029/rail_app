<?php
/**
 * TRIN 9 – Kompensation (Art. 19)
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
$compTitle = $isOngoing
    ? 'TRIN 9 - Kompensation (foreloebig)'
    : ($isCompleted ? 'TRIN 9 - Kompensation (afsluttet rejse)' : 'TRIN 9 - Kompensation (Art. 19)');
$compHint = $isOngoing
    ? 'Beregningen kan aendre sig, naar rejsen er afsluttet.'
    : ($isCompleted ? 'Beregningen er baseret paa den afsluttede rejse.' : '');
$delayAtFinal = (int)($delayAtFinal ?? 0);
$bandAuto = (string)($bandAuto ?? '0');
$refundChosen = (bool)($refundChosen ?? false);
$preinformed = (bool)($preinformed ?? false);
$rerouteUnder60 = (bool)($rerouteUnder60 ?? false);
$art19Allowed = (bool)($art19Allowed ?? true);
$articles = (array)($profile['articles'] ?? []);
$art19Enabled = !isset($articles['art19']) || $articles['art19'] !== false;

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
$downgradeAmount = round($priceFromTicket * $downgradeRate * $downgradeShare, 2);
$grossBase = (float)($claim['totals']['gross_claim'] ?? 0);
$grossAdjusted = $grossBase + $rerouteExtraAmount + $returnAmt + ((($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0) ? $downgradeAmount : 0.0);
$serviceFeeRate = 0.12;
$serviceFee = round($grossAdjusted * $serviceFeeRate, 2);
$netPayout = max(0, $grossAdjusted - $serviceFee);
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
<?= $this->Form->create(null) ?>

<?php if (!empty($claim)): ?>
  <?php
    $br = (array)($claim['breakdown'] ?? []);
    $tot = (array)($claim['totals'] ?? []);
    $bandLabel = $bandAuto === '50' ? '50%' : ($bandAuto === '25' ? '25%' : '<60');
    $compPct = isset($br['compensation']['pct']) ? (int)$br['compensation']['pct'] : 0;
    $compAmount = isset($br['compensation']['amount']) ? (float)$br['compensation']['amount'] : 0.0;
    $compBasis = (string)($br['compensation']['basis'] ?? '');
    $compCur = (string)($tot['currency'] ?? 'EUR');
    $refundAmount = isset($br['refund']['amount']) ? (float)$br['refund']['amount'] : 0.0;
    $refundCur = (string)($tot['currency'] ?? 'EUR');
    $expCur = (string)($tot['currency'] ?? $priceCurrency);
    $expVal = isset($br['expenses']['total']) ? (float)$br['expenses']['total'] : 0.0;
    $expToTicket = $fxConv && $expCur !== $priceCurrency ? $fxConv($expVal, $expCur, $priceCurrency) : null;
    $expToEur = $fxConv && $expCur !== 'EUR' ? $fxConv($expVal, $expCur, 'EUR') : null;
  ?>
  <div class="card" style="border-color:#cce5ff;background:#f3f6ff;">
    <strong><?= __('Hurtigt overblik') ?></strong>
    <div class="small mt4"><?= __('Kernefelter fra denne sag - opdateres live.') ?></div>
    <div class="mt8" style="display:flex;flex-wrap:wrap;gap:8px;">
      <div class="card small" style="flex:1;min-width:220px;border-color:#e5e9f0;background:#fff;">
        <div><strong><?= __('Rejse & grundlag') ?></strong></div>
        <div class="mt4"><?= __('Endelig forsinkelse:') ?> <strong><?= (int)($delayAtFinal ?? 0) ?> <?= __('min') ?></strong> (<?= __('b?nd:') ?> <?= h($bandLabel) ?>)</div>
        <?php if ($remedyChoice === 'refund_return'): ?>
          <div class="mt4"><?= __('Billetpris (Trin 3):') ?> <strong><?= number_format($priceFromTicket,2) ?> <?= h($priceCurrency) ?></strong><br><span class="muted"><?= __('Refunderes jf. Art. 18(1)(1); kompensation udelukkes for samme tjeneste.') ?></span></div>
        <?php else: ?>
          <div class="mt4"><?= __('Prisgrundlag (Trin 3):') ?> <strong><?= number_format($priceFromTicket,2) ?> <?= h($priceCurrency) ?></strong><br><span class="muted"><?= __('Kompensation efter Art. 19.') ?></span></div>
        <?php endif; ?>
      </div>
      <div class="card small" style="flex:1;min-width:200px;border-color:#e5e9f0;background:#fff;">
        <div><strong><?= __('Art. 19 ? Kompensation') ?></strong></div>
        <div class="mt4"><?= __('Kompensation pct:') ?> <strong><?= $compPct ?>%</strong><?= $compBasis ? ' ? ' . h($compBasis) : '' ?></div>
        <div class="mt4"><?= __('Bel?b kompensation:') ?> <strong><?= number_format($compAmount,2) ?> <?= h($compCur) ?></strong></div>
        <?php if (!$art19Allowed || $remedyChoice === 'refund_return'): ?>
          <div class="small hl mt4">
            <?= h((string)($art19Reason ?? __('Art. 19 udelukkes for denne sag.'))) ?>
            <br><span class="muted"><?= __('Art. 18 (refusion) og Art. 20 (assistance/udgifter) kan stadig g�lde.') ?></span>
          </div>
        <?php endif; ?>
      </div>
      <div class="card small" style="flex:1;min-width:220px;border-color:#e5e9f0;background:#fff;">
        <div><strong><?= __('Art. 18 ? Refusion') ?></strong></div>
        <div class="mt4"><?= __('Refusion (stk. 1):') ?> <strong><?= number_format($refundAmount,2) ?> <?= h($refundCur) ?></strong></div>
        <div class="mt4"><?= __('Ekstra omkostninger:') ?> <strong><?= $rerouteExtraAmount > 0 ? number_format($rerouteExtraAmount,2) : '0.00' ?> <?= h($rerouteExtraCur) ?></strong></div>
        <div class="mt4"><?= __('Forholdsm?ssigt afslag nedgradering (stk. 3):') ?> <strong><?= ($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0 ? number_format($downgradeAmount,2) : '0.00' ?> <?= h($priceCurrency) ?></strong></div>
      </div>
      <div class="card small" style="flex:1;min-width:220px;border-color:#e5e9f0;background:#fff;">
        <div><strong><?= __('Art. 20 ? Udgifter') ?></strong></div>
        <?php
          $expMeals = (float)($br['expenses']['meals'] ?? 0);
          $expHotel = (float)($br['expenses']['hotel'] ?? 0);
          $expAlt = (float)($br['expenses']['alt_transport'] ?? 0);
          $expOther = (float)($br['expenses']['other'] ?? 0);
          $expA202 = max(0.0, $expMeals + $expHotel + $expOther);
          $expA203 = max(0.0, $expAlt);
          $altLabel = (string)($br['expenses']['alt_transport_label'] ?? '');
        ?>
        <div class="mt4"><?= __('Udgifter (indsendt):') ?> <strong><?= number_format($expVal,2) ?> <?= h($expCur) ?></strong></div>
        <div class="mt4"><?= __('Ophold/forplejning (Art. 20(2)):' ) ?> <strong><?= number_format($expA202,2) ?> <?= h($expCur) ?></strong></div>
        <div class="mt4"><?= __('Transport (Art. 20(3)):' ) ?> <strong><?= number_format($expA203,2) ?> <?= h($expCur) ?></strong><?= $altLabel !== '' && $expA203 > 0 ? ' - ' . h($altLabel) : '' ?></div>
        <?php if ($expToTicket !== null): ?>
          <div class="mt4"><?= __('Udgifter i billetvaluta:') ?> <strong><?= number_format($expToTicket,2) ?> <?= h($priceCurrency) ?></strong></div>
        <?php endif; ?>
        <?php if ($expToEur !== null): ?>
          <div class="mt4"><?= __('Udgifter i EUR:') ?> <strong><?= number_format($expToEur,2) ?> EUR</strong></div>
        <?php endif; ?>
      </div>
      <div class="card small" style="flex:1;min-width:220px;border-color:#e5e9f0;background:#fff;">
        <div><strong><?= __('Total & udbetaling') ?></strong></div>
        <div class="mt4"><?= __('Samlet krav (brutto, inkl. Art.18 stk.3):') ?> <strong><?= number_format($grossAdjusted, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
        <div class="mt4"><?= __('Servicefee 12%:') ?> <strong><?= number_format($serviceFee, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
        <div class="mt4"><?= __('Udbetaling inden 24t (netto):') ?> <strong><?= number_format($netPayout, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
      </div>
    </div>
    <?php if (!empty($claim['flags']['retailer_75'])): ?>
      <div class="small mt8 hl"><?= __('Retlig basis: Art. 12(4) 75% anvendes.') ?></div>
    <?php endif; ?>
    <?php if (!empty($seasonMode) && !empty($seasonSummary['cum_minutes_lt60'])): ?>
      <div class="small mt8 muted"><?= __('Periodekort: akkumulerede sub-60 min:') ?> <strong><?= (int)$seasonSummary['cum_minutes_lt60'] ?></strong></div>
    <?php endif; ?>
  </div>
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

<div class="card mt12">
  <strong><?= __('1) Endelig ankomstforsinkelse') ?></strong>
  <?php $knownDelay = (int)($form['delayAtFinalMinutes'] ?? $delayAtFinal); ?>
  <?php if ($knownDelay > 0): ?>
    <div class="small mt4"><?= __('Vi har beregnet den endelige forsinkelse ved bestemmelsesstedet:') ?></div>
    <div class="ok mt8"><strong><?= h($knownDelay) ?> <?= __('min') ?></strong> (<?= __('EU-filter anvendes kun på forsinkelsesminutter, ikke prisgrundlaget.') ?>)</div>
    <input type="hidden" name="delayAtFinalMinutes" value="<?= h($knownDelay) ?>" />
  <?php else: ?>
    <div class="small mt4"><?= __('Udfyld kun hvis vi ikke allerede kender den. Brug faktiske ankomsttider ved endeligt bestemmelsessted.') ?></div>
    <label class="mt8"><?= __('Forsinkelse (minutter)') ?>
      <input type="number" name="delayAtFinalMinutes" min="0" step="1" value="<?= h($form['delayAtFinalMinutes'] ?? '') ?>" />
    </label>
  <?php endif; ?>
  <?php $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : 60; ?>
  <div class="small mt4"><?= __('Auto-beregnet bånd:') ?> <strong><?= $bandAuto === '50' ? '50%' : ($bandAuto === '25' ? '25%' : __('0% (under {0} min)', (int)$thr25)) ?></strong></div>
</div>

<div class="card mt12">
  <strong><?= __('2) Vælg bånd (hvis relevant)') ?></strong>
  <div class="small mt4">
    <?php
      $thr25Txt = 60;
      if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds']['25'])) {
          $thr25Txt = (int)$nationalPolicy['thresholds']['25'];
      }
    ?>
    <?= __('Hvis forsinkelsen er ≥ {0} min: vælg bånd for beregning. (Vi foreslår automatisk ud fra minutter.)', (int)$thr25Txt) ?>
    <?php if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds']['25'])): ?>
      <br /><span class="muted"><?= __('Nationalt hint: {0} anvender 25% allerede fra {1} min.', (string)$nationalPolicy['name'], (int)$nationalPolicy['thresholds']['25']) ?></span>
    <?php endif; ?>
  </div>
  <?php
    $sel = (string)($selectedBand ?? ($form['compensationBand'] ?? ($bandAuto === '0' ? '' : $bandAuto)));
    $thr25 = null; $thr50 = null;
    if (!empty($nationalPolicy) && is_array($nationalPolicy) && !empty($nationalPolicy['thresholds'])) {
      $thr25 = isset($nationalPolicy['thresholds']['25']) ? (int)$nationalPolicy['thresholds']['25'] : null;
      $thr50 = isset($nationalPolicy['thresholds']['50']) ? (int)$nationalPolicy['thresholds']['50'] : null;
    }
    $label25 = ($thr25 !== null && $thr25 !== 60) ? __('25 % (≥ {0} min)', (int)$thr25) : __('25 % (60–119 min)');
    $label50 = ($thr50 !== null && $thr50 !== 120) ? __('50 % (≥ {0} min)', (int)$thr50) : __('50 % (≥120 min)');
    $labelNone = ($thr25 !== null && $thr25 !== 60) ? __('Ikke relevant / under {0} min', (int)$thr25) : __('Ikke relevant / under 60 min');
  ?>
  <label class="mt8"><input type="radio" name="compensationBand" value="25" <?= $sel==='25'?'checked':'' ?> /> <?= h($label25) ?></label>
  <label class="ml8"><input type="radio" name="compensationBand" value="50" <?= $sel==='50'?'checked':'' ?> /> <?= h($label50) ?></label>
  <label class="ml8"><input type="radio" name="compensationBand" value="" <?= $sel===''?'checked':'' ?> /> <?= h($labelNone) ?></label>
  <script>
    // Live preview: skift bånd og reload med ?band=.. for at re-beregne kompensation uden at poste videre
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

<?php if (!empty($claim)): ?>
<div class="card mt12" style="display:grid;gap:8px;">
  <strong><?= __('Beløb pr. artikel (live)') ?></strong>
  <?php $br = (array)($claim['breakdown'] ?? []); $tot = (array)($claim['totals'] ?? []); ?>
  <?php
    $refundDisplay = (float)($br['refund']['amount'] ?? 0);
    $refundBasis = (string)($br['refund']['basis'] ?? '');
    if ($remedyChoice === 'refund_return' && $refundDisplay <= 0 && $priceFromTicket > 0) {
        $refundDisplay = $priceFromTicket;
        $refundBasis = $refundBasis !== '' ? $refundBasis : 'Art. 18(1)(1)';
    }
    $returnFlag = (string)($form['return_to_origin_expense'] ?? '');
    $returnAmt = is_numeric($form['return_to_origin_amount'] ?? null)
      ? (float)$form['return_to_origin_amount']
      : (float)preg_replace('/[^0-9.]/','', (string)($form['return_to_origin_amount'] ?? '0'));
    $returnCur = (string)($form['return_to_origin_currency'] ?? $priceCurrency);
    if ($returnCur === '') { $returnCur = $priceCurrency; }
  ?>
  <div class="small mt4">
    <?= __('Pris fanget i trin 3:') ?> <strong><?= number_format($priceFromTicket, 2) ?> <?= h($priceCurrency) ?></strong>
    <?php if ($remedyChoice === 'refund_return'): ?>
      - <?= __('Refunderes jf. Art. 18(1)(1) (kompensation udelukket for samme tjeneste).') ?>
    <?php else: ?>
      (<?= __('basis:') ?> <?= h($br['compensation']['basis'] ?? '-') ?>)
    <?php endif; ?>
  </div>
  <div style="display:grid;gap:6px;">
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 18 stk. 1') ?></strong></div>
      <div class="small"><?= __('Refusion (Art. 18 stk. 1):') ?> <?= number_format($refundDisplay, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?><?= $refundBasis ? ' - ' . h($refundBasis) : '' ?><?php if (isset($br['refund']['downgrade_component']) && (float)$br['refund']['downgrade_component']>0): ?> (<?= __('inkl. nedgradering:') ?> <?= number_format((float)$br['refund']['downgrade_component'], 2) ?>)<?php endif; ?></div>
      <div class="small"><?= __('Returtransport til udgangspunktet:') ?> <?= $returnAmt > 0 ? number_format($returnAmt, 2) : '0.00' ?> <?= h($returnCur) ?>
        <?php if ($returnAmt > 0 && $fxConv && $returnCur !== $priceCurrency): ?>
          (<?= __('= {0} {1}', number_format($fxConv($returnAmt, $returnCur, $priceCurrency),2), h($priceCurrency)) ?>)
        <?php endif; ?>
        <?php if ($returnAmt > 0 && $fxConv && $returnCur !== 'EUR'): ?>
          (<?= __('= {0} EUR', number_format($fxConv($returnAmt, $returnCur, 'EUR'),2)) ?>)
        <?php endif; ?>
      </div>
      <div class="small"><?= __('Nedgradering (CIV/Bilag II):') ?> <?= ($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0 ? number_format($downgradeAmount,2) : '0.00' ?> <?= h($priceCurrency) ?><?php if (($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0 && $priceCurrency !== 'EUR' && $fxConv): ?> (<?= __('= {0} EUR', number_format($fxConv($downgradeAmount, $priceCurrency, 'EUR'),2)) ?>)<?php endif; ?></div>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 18 stk. 2 og 3') ?></strong></div>
      <div class="small"><?= __('Ekstra omkostninger:') ?> <?= $rerouteExtraAmount > 0 ? number_format($rerouteExtraAmount, 2) : '0.00' ?> <?= h($rerouteExtraCur) ?>
        <?php if ($rerouteExtraAmount > 0 && $fxConv && $rerouteExtraCur !== $priceCurrency): ?>
          (<?= __('= {0} {1}', number_format($fxConv($rerouteExtraAmount, $rerouteExtraCur, $priceCurrency),2), h($priceCurrency)) ?>)
        <?php endif; ?>
        <?php if ($rerouteExtraAmount > 0 && $fxConv && $rerouteExtraCur !== 'EUR'): ?>
          (<?= __('= {0} EUR', number_format($fxConv($rerouteExtraAmount, $rerouteExtraCur, 'EUR'),2)) ?>)
        <?php endif; ?>
      </div>
      <div class="small"><?= __('Nedgradering (CIV/Bilag II):') ?> <?= ($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0 ? number_format($downgradeAmount,2) : '0.00' ?> <?= h($priceCurrency) ?><?php if (($form['downgrade_occurred'] ?? '') === 'yes' && $downgradeRate > 0 && $priceCurrency !== 'EUR' && $fxConv): ?> (<?= __('= {0} EUR', number_format($fxConv($downgradeAmount, $priceCurrency, 'EUR'),2)) ?>)<?php endif; ?></div>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 19') ?></strong></div>
      <div class="small"><?= __('Kompensation:') ?> <?= isset($br['compensation']['amount']) ? number_format((float)$br['compensation']['amount'], 2) : '0.00' ?> <?= h($tot['currency'] ?? 'EUR') ?> - <?= h($br['compensation']['pct'] ?? 0) ?>% - <?= h($br['compensation']['basis'] ?? '') ?></div>
    </div>
    <div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px;background:#fff;">
      <div class="small"><strong><?= __('Art. 20') ?></strong></div>
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
      <div class="small"><?= __('Servicefee 12%:') ?> <strong><?= number_format($serviceFee, 2) ?> <?= h($tot['currency'] ?? $priceCurrency) ?></strong></div>
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

<?= $this->Form->end() ?>
