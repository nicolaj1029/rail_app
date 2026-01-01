<?php
// Reusable hooks panel element used both on full page render and AJAX updates.
// Expected vars: $profile, $art12, $art9, $claim, $form, $meta
?>
<h3>Live hooks & AUTO</h3>
<div class="small">Undtagelser (test)</div>
<div class="small">scope: <code><?= h($profile['scope'] ?? '-') ?></code></div>
<?php $ocrPages = isset($meta['_ocr_pages']) ? (int)$meta['_ocr_pages'] : null; ?>
<?php if ($ocrPages && $ocrPages > 1): ?>
  <div class="small">PDF sider (OCR): <code><?= h($ocrPages) ?></code></div>
<?php endif; ?>
<?php if (isset($euOnlySuggested)): ?>
  <div class="small">EU only (anbefalet): <code><?= h($euOnlySuggested) ?></code><?php if (!empty($this->getRequest()->getQuery('debug')) && !empty($euOnlyReason)): ?> · <span class="muted"><?= h($euOnlyReason) ?></span><?php endif; ?></div>
<?php endif; ?>
<?php if (!empty($profile['blocked'])): ?>
  <div class="small warn">Denne rute/scope er blokeret i EU-flowet (nationalt regime anvendes).</div>
<?php endif; ?>
<?php if (!empty($profile['ui_banners'])): ?>
  <div class="small mt4">Bemærkninger:</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ((array)$profile['ui_banners'] as $b): ?>
      <li><?= h($b) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php $arts = (array)($profile['articles'] ?? []); $artsSub = (array)($profile['articles_sub'] ?? []); ?>
<?php if (!empty($arts)): ?>
  <div class="small mt4">Artikler (ON= gælder, OFF = undtaget):</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($arts as $k=>$v): ?>
      <li><?= h($k) ?>: <strong><?= $v ? 'ON' : 'OFF' ?></strong></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if (!empty($artsSub)): ?>
  <div class="small mt4">Del-artikler (Art. 9):</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($artsSub as $k=>$v): ?>
      <li><?= h($k) ?>: <strong><?= $v ? 'ON' : 'OFF' ?></strong></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php
  // Explicit exemptions section: list only the articles that are OFF (exempt)
  $labels = [
    'art12' => 'Art. 12 – Gennemgående billet',
    'art17' => 'Art. 17 – Forsinkelsesinformation',
    'art18_3' => 'Art. 18(3) – 100-minutters-reglen',
    'art19' => 'Art. 19 – Kompensation',
    'art20_2' => 'Art. 20(2) – Assistance',
    'art30_2' => 'Art. 30(2) – Tvistbilæggelse',
    'art10' => 'Art. 10 – Realtidsdata',
    'art9' => 'Art. 9 – Informationspligter',
  ];
  $exList = [];
  foreach ($arts as $k=>$v) {
    if ($v === false) { $exList[] = $labels[$k] ?? $k; }
  }
  // Add partial Art. 9 exemptions if any sub-parts are OFF
  if (!empty($artsSub)) {
    foreach ($artsSub as $k=>$v) {
      if ($v === false) {
        $part = substr((string)$k, -1);
        $exList[] = 'Art. 9(' . h($part) . ') – undtaget';
      }
    }
  }
?>
<?php if (!empty($exList)): ?>
  <div class="small mt4">Fritagelser (gældende undtagelser):</div>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($exList as $ex): ?>
      <li><?= is_string($ex) ? $ex : h((string)$ex) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if (empty($hidePmrBike)): ?>
<hr/>
<div class="small"><strong>TRIN 3</strong> · Cykel på billetten</div>
<?php 
  $bikeAuto = (array)($meta['_bike_detection'] ?? []);
  $bikeB = (string)($meta['bike_booked'] ?? ($meta['_auto']['bike_booked']['value'] ?? 'unknown'));
  $bikeC = (string)($meta['bike_count'] ?? ($meta['_auto']['bike_count']['value'] ?? ''));
?>
<div class="small">bike_booked (auto): <code><?= h($bikeB ?: 'unknown') ?></code><?php if ($bikeC!==''): ?> · antal: <code><?= h($bikeC) ?></code><?php endif; ?></div>
<?php if (!empty($meta['_auto']['bike_was_present']['value'])): ?>
  <div class="small">bike_was_present (auto): <code><?= h((string)$meta['_auto']['bike_was_present']['value']) ?></code></div>
<?php endif; ?>
<?php if (!empty($bikeAuto)): ?>
  <div class="small muted">evidence: <code><?= h(implode(', ', array_slice((array)($bikeAuto['evidence'] ?? []), 0, 3))) ?></code> · conf: <code><?= h((string)($bikeAuto['confidence'] ?? '')) ?></code></div>
<?php endif; ?>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <div class="small">Er der cykel på billetten?
    <?php $vb = strtolower($bikeB); ?>
    <label class="ml8"><input type="radio" name="bike_booked" value="Ja" <?= $vb==='ja'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="bike_booked" value="Nej" <?= $vb==='nej'?'checked':'' ?> /> Nej</label>
    <?php if ($vb==='nej' && empty($bikeAuto['evidence'])): ?>
      <span class="badge" style="margin-left:6px; background:#eef; border:1px solid #ccd; border-radius:999px; padding:2px 8px; font-size:11px;">Auto: Ingen signaler</span>
    <?php endif; ?>
  </div>
  <div class="small" style="margin-top:4px;">Antal cykler (valgfrit):
    <input type="number" min="1" max="6" step="1" name="bike_count" value="<?= h($bikeC) ?>" style="width:70px;" />
  </div>
  <button type="submit" class="small" style="margin-top:4px;">Gem</button>
</form>
<hr/>
<div class="small"><strong>TRIN 3</strong> · Billetype (pris/fleks + togspecifik)</div>
<?php 
  $ttd = (array)($meta['_ticket_type_detection'] ?? []);
  $fftAuto = (string)($meta['fare_flex_type'] ?? ($meta['_auto']['fare_flex_type']['value'] ?? ''));
  $tsAuto = (string)($meta['train_specificity'] ?? ($meta['_auto']['train_specificity']['value'] ?? 'unknown'));
?>
<div class="small">fare_flex_type (auto): <code><?= h($fftAuto ?: 'other') ?></code> · train_specificity: <code><?= h($tsAuto ?: 'unknown') ?></code></div>
<?php if (!empty($ttd)): ?>
  <div class="small muted">evidence: <code><?= h(implode(', ', array_slice((array)($ttd['evidence'] ?? []), 0, 3))) ?></code> · conf: <code><?= h((string)($ttd['confidence'] ?? '')) ?></code></div>
<?php endif; ?>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <div class="small">Købstype (AUTO):
    <select name="fare_flex_type">
      <?php $curFft = strtolower((string)$fftAuto); ?>
      <option value="">-</option>
      <option value="nonflex" <?= $curFft==='nonflex'?'selected':'' ?>>Standard/Non-flex</option>
      <option value="semiflex" <?= $curFft==='semiflex'?'selected':'' ?>>Semi-flex</option>
      <option value="flex" <?= $curFft==='flex'?'selected':'' ?>>Flex</option>
      <option value="pass" <?= $curFft==='pass'?'selected':'' ?>>Abonnement/Periodekort</option>
      <option value="other" <?= $curFft==='other'?'selected':'' ?>>Andet</option>
    </select>
  </div>
  <div class="small" style="margin-top:4px;">Gælder billetten kun for specifikt tog?
    <?php $curTs = strtolower((string)$tsAuto); ?>
    <label class="ml8"><input type="radio" name="train_specificity" value="specific" <?= $curTs==='specific'?'checked':'' ?> /> Kun specifikt tog</label>
    <label class="ml8"><input type="radio" name="train_specificity" value="any_day" <?= $curTs==='any_day'?'checked':'' ?> /> Vilkårlig afgang samme dag</label>
    <label class="ml8"><input type="radio" name="train_specificity" value="unknown" <?= ($curTs===''||$curTs==='unknown')?'checked':'' ?> /> Ved ikke</label>
  </div>
  <button type="submit" class="small" style="margin-top:4px;">Gem</button>
  <div class="small muted" style="margin-top:4px;">Match: Art. 9 – Bilag II del I</div>
  </form>
<?php
// Pricing transparency – read dedicated evaluator block when available
$art9Pricing = is_array($art9_pricing ?? null) ? (array)$art9_pricing : [];
if (!empty($art9Pricing)) {
  $prStatus = $art9Pricing['compliance_status'] ?? null;
  $prReasons = (array)($art9Pricing['reasoning'] ?? []);
  echo '<div class="card info"><h4>Art. 9(1) · Billetpriser</h4>';
  if ($prStatus !== null) {
    $lbl = ($prStatus === true) ? 'Opfyldt' : 'Ikke opfyldt';
    echo '<div class="small">status: <span class="badge" style="border:1px solid #d0d7de;">' . h($lbl) . '</span></div>';
  }
  if (!empty($prReasons)) {
    echo '<ul>';
    foreach ($prReasons as $m) { echo '<li>' . h((string)$m) . '</li>'; }
    echo '</ul>';
  }
  echo '<div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 3</div></div>';
} else {
  // Fallback to hooks-based minimal messages
  $h9 = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
  $multiShown = strtolower((string)($h9['multiple_fares_shown'] ?? ($meta['multiple_fares_shown'] ?? 'unknown')));
  $cheapHi = strtolower((string)($h9['cheapest_highlighted'] ?? ($meta['cheapest_highlighted'] ?? 'unknown')));
  $pricingMsgs = [];
  if ($multiShown === 'nej' || $multiShown === 'no') {
    $pricingMsgs[] = 'Flere prisniveauer ser ikke ud til at være vist før købet.';
  }
  if ($cheapHi === 'nej' || $cheapHi === 'no') {
    $pricingMsgs[] = 'Billigste pris ser ikke ud til at være fremhævet.';
  }
  if (!empty($pricingMsgs)) {
    echo '<div class="card info"><h4>Art. 9(1) · Billetpriser</h4><ul>'; 
    foreach ($pricingMsgs as $m) { echo '<li>' . h($m) . '</li>'; }
    echo '</ul><div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 3</div></div>';
  }
}
?>
<hr/>
<div class="small"><strong>TRIN 3</strong> · Klasse og reserverede faciliteter</div>
<?php 
  $crd = (array)($meta['_class_detection'] ?? []);
  $classAuto = (string)($meta['fare_class_purchased'] ?? ($meta['_auto']['fare_class_purchased']['value'] ?? 'unknown'));
  $seatType = (string)($meta['berth_seat_type'] ?? ($meta['_auto']['berth_seat_type']['value'] ?? 'unknown'));
?>
<div class="small">fare_class_purchased (auto): <code><?= h($classAuto ?: 'unknown') ?></code> · berth_seat_type: <code><?= h($seatType ?: 'unknown') ?></code></div>
<?php if (!empty($crd)): ?>
  <div class="small muted">evidence: <code><?= h(implode(', ', array_slice((array)($crd['evidence'] ?? []), 0, 3))) ?></code> · conf: <code><?= h((string)($crd['confidence'] ?? '')) ?></code></div>
<?php endif; ?>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <div class="small">Klasse (AUTO):
    <?php $curCls = strtolower((string)$classAuto); ?>
    <select name="fare_class_purchased">
      <option value="">-</option>
      <option value="1" <?= $curCls==='1'?'selected':'' ?>>1. klasse</option>
      <option value="2" <?= $curCls==='2'?'selected':'' ?>>2. klasse</option>
      <option value="other" <?= $curCls==='other'?'selected':'' ?>>Andet</option>
      <option value="unknown" <?= ($curCls===''||$curCls==='unknown')?'selected':'' ?>>Ukendt</option>
    </select>
  </div>
  <div class="small" style="margin-top:4px;">Reservering/liggeplads:
    <?php $curSeat = strtolower((string)$seatType); ?>
    <select name="berth_seat_type">
      <option value="">-</option>
      <option value="seat" <?= $curSeat==='seat'?'selected':'' ?>>Sæde (pladsreservation)</option>
      <option value="free" <?= $curSeat==='free'?'selected':'' ?>>Fri siddeplads (ingen reservation)</option>
      <option value="couchette" <?= $curSeat==='couchette'?'selected':'' ?>>Liggeplads (couchette)</option>
      <option value="sleeper" <?= $curSeat==='sleeper'?'selected':'' ?>>Sovevogn</option>
      <option value="none" <?= $curSeat==='none'?'selected':'' ?>>Ingen relevant</option>
      <option value="unknown" <?= ($curSeat===''||$curSeat==='unknown')?'selected':'' ?>>Ukendt</option>
    </select>
  </div>
  <button type="submit" class="small" style="margin-top:4px;">Gem</button>
</form>
<?php // Downgrade per-leg summary placed near class/reservation to group related elements ?>
<?php if (!empty($downgrade) && is_array($downgrade)): ?>
  <?php $dgStatus = $downgrade['compliance_status'] ?? null; $dgRes = (array)($downgrade['results'] ?? []); ?>
  <div class="card info" style="margin-top:6px;">
    <h4>Nedgradering pr. stræk</h4>
    <?php if ($dgStatus !== null): ?>
      <div class="small">status: <span class="badge" style="border:1px solid #d0d7de;"><?= ($dgStatus===true?'Beregnede refusioner':'Mangler data til beregning') ?></span></div>
    <?php endif; ?>
    <?php if (!empty($dgRes)): ?>
      <ul>
        <?php foreach ($dgRes as $row): if (empty($row['downgraded'])) continue; ?>
          <li>
            <strong><?= h((string)($row['segment'] ?? '')) ?></strong>:
            <?php $rf=(array)($row['refund']??[]); $meth=(string)($rf['method']??''); ?>
            <?php if (!empty($rf['amount'])): ?>
              Refusion: <code><?= h(number_format((float)$rf['amount'],2,'.','')) ?></code> <?= h((string)($rf['currency']??'')) ?> (<?= h($meth) ?>)
            <?php elseif (!empty($rf['percent'])): ?>
              Refusion: <code><?= h((string)$rf['percent']) ?>%</code> (<?= h($meth) ?>)
            <?php else: ?>
              Refusion: <span class="muted">(beløb udregnes)</span>
            <?php endif; ?>
            <?php $rs=(array)($row['reasoning']??[]); if (!empty($rs)): ?>
              <div class="small">Årsag: <?= h(implode('; ', array_map('strval',$rs))) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="small">Ingen nedgradering af klasse eller reserveret plads registreret.</div>
    <?php endif; ?>
    <div class="small muted">jf. CIV + GCC‑CIV/PRR · jf. Art. 9(1) · (ved omlægning jf. Art. 18(2))</div>
  </div>
<?php endif; ?>
<hr/>
<div class="small"><strong>TRIN 1</strong> · Forsinkelse & hændelse</div>
<?php $delayNow = (int)($compute['delayMinEU'] ?? (int)($form['delayAtFinalMinutes'] ?? 0)); $kd = !empty($compute['knownDelayBeforePurchase']); ?>
<div class="small">delay_min_eu: <code><?= (int)$delayNow ?></code></div>
<?php
  $incMain = (string)($incident['main'] ?? '');
  $segAutoHp = (array)($meta['_segments_auto'] ?? []);
  $hasConnHp = count($segAutoHp) >= 2;
  $hasMcStationHp = trim((string)($form['missed_connection_station'] ?? '')) !== '';
  $incMiss = !empty($incident['missed']) || $hasMcStationHp || $hasConnHp;
?>
<div class="small">incident: <code><?= h($incMain ?: '-') ?></code><?= $incMiss ? ' · <span class="badge">missed-connection</span>' : '' ?></div>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <label>Minuters forsinkelse: <input type="number" min="0" name="delay_min_eu" value="<?= (int)$delayNow ?>" style="width:80px;" /></label>
  <label class="ml8"><input type="checkbox" name="known_delay" value="1" <?= $kd?'checked':'' ?> /> Kendt før køb</label>
  <label class="ml8">Hændelse:
    <select name="incident_main">
      <?php foreach (['','delay','cancellation','other'] as $opt): ?>
        <option value="<?= h($opt) ?>" <?= $incMain===$opt?'selected':'' ?>><?= h($opt===''?'-':$opt) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="ml8"><input type="checkbox" name="incident_missed" value="1" <?= $incMiss?'checked':'' ?> /> Misset forbindelse</label>
  <button type="submit" class="small ml8">Gem</button>
  <span class="muted" style="margin-left:6px;">(opdaterer efter næste handling)</span>
  </form>
<?php
  // Preknown disruption/delay – read dedicated evaluator block when available
  $art9Preknown = is_array($art9_preknown ?? null) ? (array)$art9_preknown : [];
  if (!empty($art9Preknown)) {
    $pkStatus = $art9Preknown['compliance_status'] ?? null;
    $pkReasons = (array)($art9Preknown['reasoning'] ?? []);
    echo '<div class="card info"><h4>Art. 9(1) · Forhåndsoplysning om afbrydelser/forsinkelser</h4>';
    if ($pkStatus !== null) {
      $lbl = ($pkStatus === true) ? 'Opfyldt' : 'Ikke opfyldt';
      echo '<div class="small">status: <span class="badge" style="border:1px solid #d0d7de;">' . h($lbl) . '</span></div>';
    }
    if (!empty($pkReasons)) {
      echo '<ul>';
      foreach ($pkReasons as $m) { echo '<li>' . h((string)$m) . '</li>'; }
      echo '</ul>';
    }
    echo '<div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 7 · Art. 19(9)</div></div>';
  } else {
    // Fallback minimal messages using hooks/meta
    $h9f = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
    $preInf = strtolower((string)($h9f['preinformed_disruption'] ?? ($meta['preinformed_disruption'] ?? 'unknown')));
    $channels = (array)($h9f['preinfo_channels'] ?? ($meta['preinfo_channels'] ?? []));
    $kdMin = (string)($h9f['known_delay_before_purchase_minutes'] ?? ($meta['known_delay_before_purchase_minutes'] ?? ''));
    $msgs = [];
    if ($preInf === 'ja' || $preInf === 'yes' || $preInf === 'true' || $preInf === '1') {
      $msgs[] = 'Forsinkelsen/afbrydelsen var oplyst før køb. Dette kan begrænse kompensation (Art. 19(9)).';
      $kdi = is_numeric($kdMin) ? (int)$kdMin : null;
      if ($kdi !== null && $kdi >= 60) {
        $msgs[] = 'Kendt forsinkelse ≥ 60 min ved købstidspunkt (kan udelukke kompensation).';
      }
      if (!empty($channels)) {
        $msgs[] = 'Kilde: ' . implode(', ', array_map('h', array_map('strval', $channels)));
      }
    } elseif ($preInf === 'nej' || $preInf === 'no' || $preInf === 'false' || $preInf === '0') {
      $msgs[] = 'Ingen forudgående oplysning om relevant afbrydelse/forsinkelse ved køb.';
    } else {
      $msgs[] = 'Uklarhed om forudgående oplysning. Vurdering afhænger af dokumentation (før købstidspunkt).';
    }
    if (!empty($msgs)) {
      echo '<div class="card info"><h4>Art. 9(1) · Forhåndsoplysning om afbrydelser/forsinkelser</h4><ul>';
      foreach ($msgs as $m) { echo '<li>' . (is_string($m) ? h($m) : h(strval($m))) . '</li>'; }
      echo '</ul><div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 7 · Art. 19(9)</div></div>';
    }
  }
?>
<hr/>
<div class="small"><strong>TRIN 2</strong> · EU-scope</div>
<?php $euOnlyNow = isset($compute['euOnly']) ? (bool)$compute['euOnly'] : null; $isAdmin = (bool)($isAdmin ?? false); ?>
<div class="small">eu_only (aktuel): <code><?= $euOnlyNow===null?'-':($euOnlyNow?'true':'false') ?></code><?php if (isset($euOnlySuggested) && $euOnlySuggested === 'yes'): ?> · <span class="badge">anbefalet</span><?php endif; ?></div>
<?php if ($isAdmin): ?>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <label><input type="checkbox" name="eu_only" value="1" <?= $euOnlyNow?'checked':'' ?> /> Tving kun EU-regler (ignorer nationale)</label>
  <button type="submit" class="small ml8">Gem</button>
</form>
<?php else: ?>
  <div class="small muted" style="margin-top:6px;">(Kun synlig for administratorer)</div>
<?php endif; ?>
<hr/>
<div class="small"><strong>TRIN 3</strong> · PMR/handicap</div>
<?php
  // Prefer explicit AUTO provenance for display; fall back to current meta/hooks for user-answered values
  $h9 = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
  $pmrUAuto = (string)($meta['_auto']['pmr_user']['value'] ?? '');
  $pmrBAuto = (string)($meta['_auto']['pmr_booked']['value'] ?? '');
  // Normalize yes/no in both DA and EN for display
  $norm = function(string $v): string {
    $v = strtolower(trim($v));
    if ($v === 'ja') return 'Ja'; if ($v === 'nej') return 'Nej';
    if ($v === 'yes') return 'Ja'; if ($v === 'no') return 'Nej';
    return $v === '' ? 'unknown' : $v;
  };
  $pmrUDisp = $norm($pmrUAuto);
  $pmrBDisp = $norm($pmrBAuto);
  if ($pmrUDisp === 'unknown') { $pmrUDisp = $norm((string)($h9['pmr_user'] ?? ($meta['pmr_user'] ?? 'unknown'))); }
  if ($pmrBDisp === 'unknown') { $pmrBDisp = $norm((string)($h9['pmr_booked'] ?? ($meta['pmr_booked'] ?? 'unknown'))); }
  // Also keep raw values for the form preselection (prefer user/meta over auto)
  $pmrU = (string)($h9['pmr_user'] ?? ($meta['pmr_user'] ?? 'unknown'));
  $pmrB = (string)($h9['pmr_booked'] ?? ($meta['pmr_booked'] ?? 'unknown'));
?>
<div class="small">pmr_user (auto): <code><?= h($pmrUDisp ?: 'unknown') ?></code> · pmr_booked: <code><?= h($pmrBDisp ?: 'unknown') ?></code></div>
<?php if (!empty($meta['_pmr_detection'])): $pd=(array)$meta['_pmr_detection']; ?>
  <div class="small muted">evidence: <code><?= h(implode(', ', array_slice((array)($pd['evidence'] ?? []), 0, 3))) ?></code> · conf: <code><?= h((string)($pd['confidence'] ?? '')) ?></code></div>
<?php endif; ?>
<form method="post" class="small" style="margin-top:6px;">
  <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
    <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
  <?php endif; ?>
  <div class="small">Har du et handicap eller nedsat mobilitet?
  <?php $v = strtolower($pmrU); ?>
  <label class="ml8"><input type="radio" name="pmr_user" value="Ja" <?= $v==='ja'?'checked':'' ?> /> Ja</label>
  <label class="ml8"><input type="radio" name="pmr_user" value="Nej" <?= $v==='nej'?'checked':'' ?> /> Nej</label>
  </div>
  <div class="small" style="margin-top:4px;">Bestilte du assistance før rejsen?
  <?php $vb = strtolower($pmrB); ?>
  <label class="ml8"><input type="radio" name="pmr_booked" value="Ja" <?= $vb==='ja'?'checked':'' ?> /> Ja</label>
  <label class="ml8"><input type="radio" name="pmr_booked" value="Nej" <?= $vb==='nej'?'checked':'' ?> /> Nej</label>
  </div>
  <button type="submit" class="small" style="margin-top:4px;">Gem</button>
</form>
<hr/>
<?php endif; ?>
<?php $incMiss = $incMiss ?? false; ?>
<?php if ($incMiss): ?>
  <div class="small"><strong>TRIN 3</strong> · Art. 9(1) – Køreplaner og hurtigste rejse</div>
  <?php 
    $mctVal = (string)($meta['mct_realistic'] ?? '');
    if ($mctVal === '' || strtolower($mctVal) === 'unknown') {
      $mctVal = (string)($meta['_auto']['mct_realistic']['value'] ?? 'unknown');
    }
  ?>
  <div class="small">Var minimumsskiftetiden realistisk (missed station)? <code><?= h($mctVal ?: 'unknown') ?></code></div>
  <?php if (strtolower((string)$mctVal) === 'unknown'): ?>
    <form method="post" class="small" style="margin-top:6px;">
      <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
        <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
      <?php endif; ?>
      <label class="ml8"><input type="radio" name="mct_realistic" value="Ja" /> Ja</label>
      <label class="ml8"><input type="radio" name="mct_realistic" value="Nej" /> Nej</label>
      <button type="submit" class="small ml8">Gem</button>
    </form>
  <?php endif; ?>
  <?php
    // Fastest-route – read dedicated evaluator block when available
    $art9Fastest = is_array($art9_fastest ?? null) ? (array)$art9_fastest : [];
    if (!empty($art9Fastest)) {
      $faStatus = $art9Fastest['compliance_status'] ?? null;
      $faReasons = (array)($art9Fastest['reasoning'] ?? []);
      echo '<div class="card info"><h4>Art. 9(1) · Hurtigste rejse</h4>';
      if ($faStatus !== null) {
        $lbl = ($faStatus === true) ? 'Opfyldt' : 'Ikke opfyldt';
        echo '<div class="small">status: <span class="badge" style="border:1px solid #d0d7de;">' . h($lbl) . '</span></div>';
      }
      if (!empty($faReasons)) {
        echo '<ul>';
        foreach ($faReasons as $m) { echo '<li>' . h((string)$m) . '</li>'; }
        echo '</ul>';
      }
      echo '<div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 2</div></div>';
    } else {
      // Fallback minimal messages
      $h9 = is_array($art9??null) ? (array)($art9['hooks'] ?? []) : [];
      $fastFlag = strtolower((string)($h9['fastest_flag_at_purchase'] ?? ($meta['fastest_flag_at_purchase'] ?? 'unknown')));
      $altsShown = strtolower((string)($h9['alts_shown_precontract'] ?? ($meta['alts_shown_precontract'] ?? 'unknown')));
      $fastMsgs = [];
      if ($fastFlag === 'nej' || $fastFlag === 'no') {
        $fastMsgs[] = 'Der er indikationer på, at “hurtigste rute” ikke blev tydeligt fremhævet. Det kan påvirke omlægning efter Art. 18.';
      }
      if ($altsShown === 'nej' || $altsShown === 'no') {
        $fastMsgs[] = 'Alternative ruter med kortere samlet rejsetid ser ikke ud til at være vist.';
      }
      if (!empty($fastMsgs)) {
        echo '<div class="card info"><h4>Art. 9(1) · Hurtigste rejse</h4><ul>';
        foreach ($fastMsgs as $m) { echo '<li>' . h($m) . '</li>'; }
        echo '</ul><div class="small muted">jf. Art. 9(1) + Bilag II, pkt. 2</div></div>';
      }
    }
  ?>
  <hr/>
<?php endif; ?>
<?php if (isset($art12flow) && is_array($art12flow)): ?>
<div class="small"><strong>TRIN 2/4/5</strong> · Art. 12 flow</div>
<?php $stage = (string)($art12flow['stage'] ?? ''); $flowNotes = (array)($art12flow['notes'] ?? []); $toAsk = (array)($art12flow['hooks_to_collect'] ?? []); ?>
<div class="small">stage: <code><?= h($stage ?: '-') ?></code></div>
<?php if (!empty($flowNotes)): ?>
  <ul class="small" style="margin:4px 0 6px 16px;">
    <?php foreach ($flowNotes as $n): ?>
      <li><?= h((string)$n) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<div class="small">mangler: <code><?= h(implode(', ', $toAsk) ?: '-') ?></code></div>
<?php if (in_array('same_transaction_all', $toAsk, true)): ?>
  <form method="post" style="margin-top:6px;">
    <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
      <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
    <?php endif; ?>
    <?php $curSta = strtolower((string)($meta['same_transaction_all'] ?? '')); ?>
    <div class="small">Købt i én transaktion (alle billetter)?
      <label class="ml8"><input type="radio" name="same_transaction_all" value="yes" <?= $curSta==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="same_transaction_all" value="no" <?= $curSta==='no'?'checked':'' ?> /> Nej</label>
    </div>
    <button type="submit" class="small" style="margin-top:4px;">Gem</button>
  </form>
<?php endif; ?>
<hr/>
<?php endif; ?>
<?php $showArt12Section = isset($showArt12Section) ? (bool)$showArt12Section : true; ?>
<?php $h = (array)($art12['hooks'] ?? []); $miss = (array)($art12['missing'] ?? []); $missUi = (array)($art12['missing_ui'] ?? []); $missAuto = (array)($art12['missing_auto'] ?? []); $debug = (bool)($this->getRequest()->getQuery('debug') ?? false); ?>
<?php if ($showArt12Section): ?>
<div class="small"><strong>TRIN 6</strong> · Art. 12</div>
<?php
  $applies = $art12['art12_applies'] ?? null;
  $liable = (string)($art12['liable_party'] ?? 'unknown');
  // Normalize liable party for display
  $liableNorm = $liable;
  if ($liableNorm === 'retailer') $liableNorm = 'agency';
  $liableLbl = ($liableNorm === 'operator') ? 'Jernbanevirksomhed' : (($liableNorm === 'agency') ? 'Forhandler/rejsebureau' : 'Ukendt');
  // Build user-facing result text
  $resultLbl = ($applies === true) ? 'Gælder' : (($applies === false) ? 'Gælder ikke' : 'Ukendt');
?>
<div class="small" style="margin:4px 0;">
  <strong>Evaluering:</strong>
  <span class="badge" style="background:<?= ($applies===true?'#e6ffed':'#f6f8fa') ?>;border-color:<?= ($applies===true?'#b2f2bb':'#d0d7de') ?>;color:#24292f;"><?= h($resultLbl) ?></span>
  <?php if ($applies === true): ?>
    <span class="badge" style="margin-left:6px;">Ansvarlig: <?= h($liableLbl) ?></span>
  <?php endif; ?>
</div>

<?php $showFormDecision = isset($showFormDecision) ? (bool)$showFormDecision : true; ?>
<?php if ($showFormDecision && isset($formDecision) && is_array($formDecision)): ?>
  <?php $rec = (string)($formDecision['form'] ?? 'eu_standard_claim'); $isEu = ($rec === 'eu_standard_claim'); $label = $isEu ? 'Anbefalet: EU-formular' : (($rec === 'none') ? 'Anbefalet: ingen EU-formular' : 'Anbefalet: national formular'); $reasonDec = (string)($formDecision['reason'] ?? ''); ?>
  <div class="small" title="<?= h($reasonDec) ?>">
    <span class="badge" style="<?= $isEu ? 'background:#e6f7ff;color:#006bb3;' : 'background:#fff7e6;color:#b36b00;' ?>;border:1px solid #d0d7de;"><?= h($label) ?></span>
  </div>
<?php endif; ?>

<?php
// Ny TRIN 6-flow (forenklet visning): brug tri-state yes/no/unknown fra evaluator hooks
$ttd = (string)($h['through_ticket_disclosure'] ?? 'unknown');   // 'yes' = tydelig oplysning før køb, 'no' = ikke tydelig
$scn = (string)($h['separate_contract_notice'] ?? 'unknown');    // 'yes' = særskilt-notits givet, 'no' = ikke givet
$ny_billettype = null; $ny_komp = null; $ny_ask = [];

// Regler (ny semantik):
// - separate_contract_notice = 'no'  ⇒ gennemgående
// - separate_contract_notice = 'yes' & through_ticket_disclosure = 'yes' ⇒ separate
// - separate_contract_notice = 'yes' & through_ticket_disclosure in ('no','unknown') ⇒ gennemgående (manglende/uklar oplysning)
if ($scn === 'no') {
  $ny_billettype = 'gennemgående';
  if (($h['seller_type_operator'] ?? 'unknown') === 'yes') { $ny_komp = 'stk3'; }
  elseif (($h['seller_type_agency'] ?? 'unknown') === 'yes') { $ny_komp = 'stk4'; }
  else { $ny_komp = 'stk3_eller_4'; }
} elseif ($scn === 'yes') {
  if ($ttd === 'yes') {
    $ny_billettype = 'ikke gennemgående';
    $ny_komp = 'per_led';
  } else { // ttd = 'no' eller 'unknown'
    $ny_billettype = 'gennemgående';
    if (($h['seller_type_operator'] ?? 'unknown') === 'yes') { $ny_komp = 'stk3'; }
    elseif (($h['seller_type_agency'] ?? 'unknown') === 'yes') { $ny_komp = 'stk4'; }
    else { $ny_komp = 'stk3_eller_4'; }
  }
} else { // scn unknown
  $ny_ask[] = 'separate_contract_notice';
}
if ($scn === 'yes' && ($ttd === 'unknown' || $ttd === '')) { $ny_ask[] = 'through_ticket_disclosure'; }
?>
<div class="small mt6">Ny TRIN 6-flow (forenklet)</div>
<div class="small">billettype: <code><?= h($ny_billettype ?? '-') ?></code></div>
<div class="small">komp: <code><?= h($ny_komp ?? '-') ?></code></div>
<?php if (!empty($ny_ask)): ?>
  <div class="small">mangler: <code><?= h(implode(', ', $ny_ask)) ?></code></div>
<?php endif; ?>
<details class="small" style="margin:6px 0;">
  <summary style="cursor:pointer;">Vurderingsgrundlag (nøgleværdier)</summary>
  <div class="small" style="margin-top:4px;">
    <div>applies: <code><?= isset($art12['art12_applies']) ? var_export((bool)$art12['art12_applies'], true) : '-' ?></code></div>
    <?php if (!empty($art12['classification'])): ?>
      <div>classification: <code><?= h((string)$art12['classification']) ?></code></div>
    <?php endif; ?>
    <?php if (!empty($art12['basis'])): ?>
      <div>basis: <code><?= h(implode(', ', (array)$art12['basis'])) ?></code></div>
    <?php endif; ?>
    <?php if (!empty($miss)): ?>
      <div>missing: <code><?= h(implode(', ', $miss)) ?></code></div>
    <?php endif; ?>
    <!-- Vis kun de relevante nøgler for afgørelsen som standard -->
    <div>separate_contract_notice: <code><?= h((string)($h['separate_contract_notice'] ?? 'unknown')) ?></code></div>
    <div>through_ticket_disclosure: <code><?= h((string)($h['through_ticket_disclosure'] ?? 'unknown')) ?></code></div>
    <div>single_txn_operator: <code><?= h((string)($h['single_txn_operator'] ?? 'unknown')) ?></code></div>
    <div>single_txn_retailer: <code><?= h((string)($h['single_txn_retailer'] ?? 'unknown')) ?></code></div>
    <div>shared_pnr_scope: <code><?= h((string)($h['shared_pnr_scope'] ?? 'unknown')) ?></code></div>
    <div>single_booking_reference: <code><?= h((string)($h['single_booking_reference'] ?? 'unknown')) ?></code></div>

    <?php if ($debug): ?>
      <!-- Debug: vis de øvrige signaler -->
      <div>seller_type_operator: <code><?= h((string)($h['seller_type_operator'] ?? 'unknown')) ?></code></div>
      <div>seller_type_agency: <code><?= h((string)($h['seller_type_agency'] ?? 'unknown')) ?></code></div>
      <div>multi_operator_trip: <code><?= h((string)($h['multi_operator_trip'] ?? 'unknown')) ?></code></div>
      <div>connection_time_realistic: <code><?= h((string)($h['connection_time_realistic'] ?? 'unknown')) ?></code></div>
      <div>one_contract_schedule: <code><?= h((string)($h['one_contract_schedule'] ?? 'unknown')) ?></code></div>
      <div>contact_info_provided: <code><?= h((string)($h['contact_info_provided'] ?? 'unknown')) ?></code></div>
      <div>responsibility_explained: <code><?= h((string)($h['responsibility_explained'] ?? 'unknown')) ?></code></div>
      <?php if (!empty($missAuto)): ?>
        <div>missing (AUTO): <code><?= h(implode(', ', $missAuto)) ?></code></div>
      <?php endif; ?>
    <?php endif; ?>
    <?php $reasons = (array)($art12['reasoning'] ?? []); if (!empty($reasons)): ?>
      <div class="small mt4">Begrundelse:</div>
      <ul class="small" style="margin:4px 0 6px 16px;">
        <?php foreach ($reasons as $r): ?>
          <li><?= h($r) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php if (!empty($meta['_identifiers'])): $ids=(array)$meta['_identifiers']; ?>
    <div class="small mt4">Identifikatorer (AUTO):
      <?php if (!empty($ids['pnr'])): ?> PNR: <code><?= h((string)$ids['pnr']) ?></code><?php endif; ?>
      <?php if (!empty($ids['order_no'])): ?> <?= !empty($ids['pnr'])?' · ':'' ?>Order: <code><?= h((string)$ids['order_no']) ?></code><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($meta['_barcode'])): $bc=(array)$meta['_barcode']; ?>
    <div class="small">Barcode: <code><?= h((string)($bc['format'] ?? '')) ?></code> (<?= h((string)($bc['chars'] ?? '')) ?> chars)</div>
  <?php endif; ?>
</details>
<?php // Art. 6 – Cykel: conditional, source-aware messages derived from evaluator ?>
<?php if (!empty($art6) && is_array($art6)): ?>
  <?php
    $art6Msgs = [];
    $ar = strtolower((string)($art6['reservation_required'] ?? 'unknown'));
    $am = strtolower((string)($art6['reservation_made'] ?? 'unknown'));
    $den = strtolower((string)($art6['denied_boarding'] ?? 'unknown'));
    $rt = strtolower((string)($art6['refusal_reason_type'] ?? 'unknown'));
    $allowed = in_array($rt, ['capacity','equipment','weight_dim'], true);
    if ($ar === 'yes' && $am === 'no' && $allowed) {
        $art6Msgs[] = 'Det ser ud til, at cykelreservation var påkrævet, og at reservation ikke var foretaget. Hvis det var oplyst på forhånd, kan afvisningen være berettiget efter artikel 6 (kapacitet/udstyr/regelhensyn).';
    }
    if ($ar === 'yes' && ($am === 'unknown' || !$allowed)) {
        $art6Msgs[] = 'Det er uklart, om reservation var påkrævet, eller om afvisningen var sagligt begrundet. Du kan have krav efter artikel 18 (omlægning/refusion).';
    }
    if ($den === 'yes' && in_array($rt, ['unknown','none','unspecified'], true)) {
        $art6Msgs[] = 'Du blev afvist uden begrundet årsag (kapacitet/sikkerhed/udstyr). Det kan være i strid med artikel 6. Du kan gå videre efter artikel 18.';
    }
    if ($den !== 'yes' && $ar === 'yes' && $am === 'no') {
        $art6Msgs[] = 'Bemærk: Reservation er påkrævet. Uden reservation kan boarding blive afvist ved fulde tog.';
    }
  ?>
  <?php if (!empty($art6Msgs)): ?>
    <div class="card info">
      <h4>Art. 6 – Cykel</h4>
      <ul>
        <?php foreach ($art6Msgs as $m): ?>
          <li><?= h($m) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php // PMR – Safe, conditional messages (Art.21–24 + Art.9(1)) ?>
<?php
  $pmMsgs = [];
  $pmUser = strtolower((string)($h['pmr_user'] ?? ($meta['pmr_user'] ?? 'unknown')));
  $pmBooked = strtolower((string)($h['pmr_booked'] ?? ($meta['pmr_booked'] ?? 'unknown')));
  $pmDelivered = strtolower((string)($h['pmr_delivered_status'] ?? ($meta['pmr_delivered_status'] ?? 'unknown')));
  $pmPromMiss = strtolower((string)($h['pmr_promised_missing'] ?? ($meta['pmr_promised_missing'] ?? 'unknown')));
  if ($pmUser === 'ja' || $pmUser === 'yes') {
    if ($pmBooked === 'ja' || $pmBooked === 'yes') {
      if ($pmDelivered === 'no') {
        $pmMsgs[] = 'Bestilt assistance blev ikke leveret. Det kan være i strid med artikel 21–24. Du kan klage over manglende assistance.';
      } elseif ($pmDelivered === 'partial') {
        $pmMsgs[] = 'Assistance blev delvist leveret. Du kan have et krav efter artikel 21–24 for den manglende del.';
      }
    } elseif ($pmBooked === 'attempted_refused') {
      $pmMsgs[] = 'Forsøg på at bestille assistance blev afvist. Det kan være i strid med artikel 21–24.';
    }
    if ($pmPromMiss === 'ja' || $pmPromMiss === 'yes') {
      $pmMsgs[] = 'Lovede PMR‑faciliteter før køb var ikke tilgængelige (Art.9(1)). Det kan også være i strid med artikel 21–24.';
    }
  } else {
    $pmMsgs[] = 'PMR‑rettigheder (Art.21–24) gælder normalt ikke, men generel assistance ved forsinkelse kan gælde (Art.20(2)).';
  }
?>
<?php if (!empty($pmMsgs)): ?>
  <div class="card info">
    <h4>Art. 21–24 · PMR</h4>
    <ul>
      <?php foreach ($pmMsgs as $m): ?>
        <li><?= h($m) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
<?php endif; // showArt12Section ?>
<hr/>
<div class="small"><strong>TRIN 7</strong> · Art. 18 (remedies)</div>
<?php $remedy = (string)($form['remedyChoice'] ?? ''); $ri100 = (string)($form['reroute_info_within_100min'] ?? ''); ?>
<div class="small">remedy: <code><?= h($remedy ?: '-') ?></code></div>
<div class="small">100-min info: <code><?= h($ri100 ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 8</strong> · Art. 20 (assistance)</div>
<?php $mo=(string)($form['meal_offered']??''); $ho=(string)($form['hotel_offered']??''); $on=(string)($form['overnight_needed']??''); ?>
<div class="small">meal_offered: <code><?= h($mo ?: '-') ?></code></div>
<div class="small">hotel_offered: <code><?= h($ho ?: '-') ?></code></div>
<div class="small">overnight_needed: <code><?= h($on ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 9</strong> · Art. 9 ask_hooks</div>
<?php $ask = (array)($art9['ask_hooks'] ?? []); ?>
<div class="kv small">count: <code><?= count($ask) ?></code></div>
<div class="small">hooks: <code><?= h(implode(', ', $ask) ?: '-') ?></code></div>
<hr/>
<div class="small"><strong>TRIN 10</strong> · Art. 19 (comp)</div>
<?php $band=(string)($form['compensationBand']??''); $df=(string)($form['delayAtFinalMinutes']??''); ?>
<div class="small">delay_final: <code><?= h($df ?: '-') ?></code></div>
<div class="small">band: <code><?= h($band ?: '-') ?></code></div>
<?php if (isset($claim) && is_array($claim)): ?>
  <div class="small">calc.amount: <code><?= h(number_format((float)($claim['compensation_amount'] ?? 0),2,'.','')) ?></code> <?= h($claim['currency'] ?? '') ?></div>
<?php endif; ?>
<?php if (!empty($this->getRequest()->getSession()->read('admin.mode'))): ?>
  <form method="post" action="<?= $this->Url->build('/admin/cases/create-from-session') ?>" class="small" style="margin-top:6px;">
    <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
      <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
    <?php endif; ?>
    <button type="submit" class="small">Gem som sag (admin)</button>
  </form>
<?php endif; ?>
<hr/>
<div class="small">AUTO felter</div>
<?php $auto = (array)($meta['_auto'] ?? []); ?>
<?php if (!empty($meta['extraction_provider'])): ?>
  <div class="small">provider: <code><?= h((string)($meta['extraction_provider'] ?? '-')) ?></code></div>
  <div class="small">confidence: <code><?= h((string)number_format((float)($meta['extraction_confidence'] ?? 0), 2)) ?></code></div>
<?php endif; ?>
<div class="small">operator: <code><?= h($auto['operator']['value'] ?? ($form['operator'] ?? '-')) ?></code></div>
<div class="small">country: <code><?= h($auto['operator_country']['value'] ?? ($form['operator_country'] ?? '-')) ?></code></div>
<div class="small">product: <code><?= h($auto['operator_product']['value'] ?? ($form['operator_product'] ?? '-')) ?></code></div>
<div class="small">train: <code><?= h($form['train_no'] ?? '-') ?></code></div>

<?php
// Provide hidden state for client-side gating sync (used by AJAX updater)
// Compute pnrCount from journey + multi-ticket summaries
try {
  $pnrSet = [];
  $br = (string)($journey['bookingRef'] ?? '');
  if ($br !== '') { $pnrSet[$br] = true; }
  $multi = (array)($meta['_multi_tickets'] ?? []);
  foreach ($multi as $mt) { $p = (string)($mt['pnr'] ?? ''); if ($p !== '') { $pnrSet[$p] = true; } }
  $pnrCount = count($pnrSet);
} catch (\Throwable $e) { $pnrCount = 0; }
?>
<input type="hidden" id="aj_pnrCount" value="<?= (int)$pnrCount ?>" />
<input type="hidden" id="aj_sharedTri" value="<?= h((string)($h['shared_pnr_scope'] ?? 'unknown')) ?>" />
<input type="hidden" id="aj_singleBookTri" value="<?= h((string)($h['single_booking_reference'] ?? 'unknown')) ?>" />

<?php if (!empty($groupedTickets)): ?>
  <hr/>
  <div class="small"><strong>Billetter i sagen</strong></div>
  <?php foreach ($groupedTickets as $gi => $g): $shared = !empty($g['shared']); ?>
    <div class="small mt4">
      <strong>Gruppe <?= (int)($gi+1) ?></strong>
      <?php if (!empty($g['pnr']) || !empty($g['dep_date'])): ?>
        (<?= h(trim((string)($g['pnr'] ?? '') . ' ' . (string)($g['dep_date'] ?? ''))) ?>)
      <?php endif; ?>
      <span class="badge" style="margin-left:6px;"><?= $shared ? 'samlet' : 'enkelt' ?></span>
    </div>
    <ul class="small" style="margin:4px 0 0 16px;">
      <?php foreach ((array)($g['tickets'] ?? []) as $t): ?>
        <li>
          <?= h((string)($t['file'] ?? '')) ?><?= (!empty($t['pnr'])||!empty($t['dep_date'])) ? (': ' . h(trim((string)($t['pnr'] ?? '') . ' ' . (string)($t['dep_date'] ?? '')))) : '' ?>
          <?php $pc = isset($t['passengers']) ? count((array)$t['passengers']) : 0; if ($pc>0): ?>
            <span class="badge" style="margin-left:6px;">pax <?= (int)$pc ?></span>
          <?php endif; ?>
          <?php if (!empty($t['file'])): ?>
            <form method="post" style="display:inline; margin-left:6px;">
              <?php $csrf = $this->getRequest()->getAttribute('csrfToken'); if ($csrf): ?>
                <input type="hidden" name="_csrfToken" value="<?= h($csrf) ?>" />
              <?php endif; ?>
              <input type="hidden" name="remove_ticket" value="<?= h((string)$t['file']) ?>" />
              <button type="submit" class="small" title="Fjern denne billet">Fjern</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
  <div class="small muted mt4">Grupperet efter PNR + dato. Upload flere billetter i TRIN 4 for at samle en sag.</div>
<?php endif; ?>
