<?php
/** @var \App\View\AppView $this */
/** @var array $profile */
/** @var bool $art12_applies */
/** @var array $art12 */
/** @var array $art9 */
/** @var array $refund */
/** @var array $refusion */
/** @var array $claim */
/** @var string|null $savedPath */
/** @var string[] $errors */
?>
<div class="content">
  <h1>Analyse-resultat</h1>
  <?php if (!empty($errors)): ?>
    <div class="message error">
      <ul><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <p><strong>Gennemgående billet regler (Art. 12):</strong>
    <?php if ($art12_applies): ?>
      <span style="color:green;">GÆLDER</span>
    <?php else: ?>
      <span style="color:#b00;">UNDTAGET</span>
    <?php endif; ?>
  </p>

  <?php if ($savedPath): ?>
    <p>Upload gemt: <?= h(basename($savedPath)) ?></p>
  <?php endif; ?>

  <h3>Overblik</h3>
  <?php if (!empty($ocrUsed)): ?>
    <p style="color:#0a0;">OCR-tekst udtrukket og brugt (<?= h((string)$ocrAutoCount) ?> auto-felter). </p>
    <?php if (!empty($ocrLogs)): ?>
      <details style="margin:6px 0;">
        <summary>OCR logs</summary>
        <ul><?php foreach($ocrLogs as $l): ?><li><?= h($l) ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
  <?php else: ?>
    <p style="color:#666;">Ingen OCR-tekst anvendt (kunne ikke udtrække eller ikke en PDF/TXT).</p>
  <?php endif; ?>
  <?php
    $delayMsg = 'Forsinkelse: ' . (int)($delayMins ?? 0) . ' min (' . ($delaySource ?? 'computed_from_journey') . ')';
    if (($delaySource ?? '') === 'manual_override') { $delayMsg = 'Forsinkelse: ' . (int)$delayMins . ' min (manuel overstyring)'; }
    elseif (($delaySource ?? '') === 'live_api') { $delayMsg = 'Forsinkelse: ' . (int)$delayMins . ' min (live API)'; }
  ?>
  <p><?= h($delayMsg) ?></p>
  <ul>
    <li>Art. 12 (gennemgående): <?= h($art12['art12_applies'] === true ? '✓' : ($art12['art12_applies'] === false ? '✗' : '?')) ?></li>
    <li>Art. 9 (information): <?= h($art9['art9_ok'] === true ? '✓' : ($art9['art9_ok'] === false ? '✗' : '?')) ?></li>
  <li>Refund (Art. 18): <?= h(($refund['eligible'] ?? null) === true ? '✓' : (($refund['eligible'] ?? null) === false ? '✗' : '?')) ?></li>
    <li>Refusion (Art. 18): <?= h($refusion['outcome'] ?? '-') ?></li>
    <li>Kompensation (Art. 19): <?= h(($claim['breakdown']['compensation']['eligible'] ?? false) ? '✓' : '✗') ?> (<?= h(($claim['breakdown']['compensation']['pct'] ?? 0) . '%') ?>)</li>
    <li>Udgifter (Art. 20): <?= h(number_format((float)($claim['breakdown']['expenses']['total'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Samlet brutto: <?= h(number_format((float)($claim['totals']['gross_claim'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Servicefee 25%: <?= h(number_format((float)($claim['totals']['service_fee_amount'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></li>
    <li>Netto til klient: <strong><?= h(number_format((float)($claim['totals']['net_to_client'] ?? 0), 2)) ?> <?= h($claim['totals']['currency'] ?? 'EUR') ?></strong></li>
  </ul>
  <h3>Detaljeret profil</h3>
  <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($profile, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

  <details style="margin:8px 0;">
    <summary><strong>Art. 9 detaljer (vises kun på anmodning)</strong></summary>
    <p style="margin:8px 0;color:#666;">Dette afsnit viser prækontraktuelle oplysninger m.v. efter klik. Det er skjult som standard (Art. 9(1) på anmodning).</p>
    <?php
      // Ticket-type badges (prækontraktuelle signaler) – vises kun efter klik
      $badges = [];
      $td = (string)($art9['hooks']['through_ticket_disclosure'] ?? '');
      if ($td !== '') { $badges[] = 'Kontrakt: ' . $td; }
      $ft = (string)($art9['hooks']['fare_flex_type'] ?? '');
      if ($ft !== '' && $ft !== 'unknown') { $badges[] = 'Billet: ' . $ft; }
      $ts = (string)($art9['hooks']['train_specificity'] ?? '');
      if ($ts !== '' && $ts !== 'unknown') { $badges[] = 'Gyldighed: ' . $ts; }
      $civ = (string)($art9['hooks']['civ_marking_present'] ?? 'unknown');
      if ($civ === 'Ja') { $badges[] = 'CIV'; }
    ?>
    <?php if (!empty($badges)): ?>
      <p>
        <?php foreach ($badges as $b): ?>
          <span style="display:inline-block;background:#eef;border:1px solid #ccd;padding:3px 6px;border-radius:4px;margin:2px;"><?= h($b) ?></span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <h3>Art. 9 detaljer</h3>
    <ul>
      <li>Del 1 (før køb): <?= h(var_export($art9['parts']['art9_1_ok'], true)) ?></li>
      <li>Del 2 (rettigheder/klager): <?= h(var_export($art9['parts']['art9_2_ok'], true)) ?></li>
      <li>Del 3 (under afbrydelse): <?= h(var_export($art9['parts']['art9_3_ok'], true)) ?></li>
    </ul>
    <?php if (!empty($art9['ui_banners'])): ?>
      <ul>
        <?php foreach($art9['ui_banners'] as $b): ?><li><?= h($b) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <details style="margin:6px 0;">
      <summary>Hooks</summary>
      <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($art9['hooks'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>
    <?php if (!empty($art9['auto'])): ?>
      <details style="margin:6px 0;">
        <summary>AUTO fra OCR</summary>
        <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($art9['auto'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      </details>
    <?php endif; ?>
    <?php if (!empty($art9['mismatches'])): ?>
      <details style="margin:6px 0;">
        <summary>Mismatches</summary>
        <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($art9['mismatches'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      </details>
    <?php endif; ?>
    <?php if (!empty($art9['ask_hooks'])): ?>
      <details style="margin:6px 0;">
        <summary>Spørgsmål der mangler svar</summary>
        <ul><?php foreach($art9['ask_hooks'] as $hkey): ?><li><?= h($hkey) ?></li><?php endforeach; ?></ul>
      </details>
    <?php endif; ?>
  </details>

  <h3>Claim breakdown</h3>
  <pre style="background:#f8f8f8;border:1px solid #eee;padding:10px;white-space:pre-wrap;"><?= h(json_encode($claim, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>

  <?php
    // Prefill official PDF generation link + build "additional information" (Section 6)
    $first = $journey['segments'][0] ?? [];
    $last = !empty($journey['segments']) ? $journey['segments'][array_key_last($journey['segments'])] : [];
    // Build additional info (arguments/evidence summary)
    $addLines = [];
    $addLines[] = 'Forsinkelse ved ankomst: ' . (int)($delayMins ?? 0) . ' min (kilde: ' . ($delaySource ?? 'computed') . ')';
    // Art. 9
    if (!empty($art9['parts'])) {
      $addLines[] = 'Art. 9 status: før køb=' . var_export($art9['parts']['art9_1_ok'], true)
        . ', rettigheder/klager=' . var_export($art9['parts']['art9_2_ok'], true)
        . ', under afbrydelse=' . var_export($art9['parts']['art9_3_ok'], true);
    }
    if (!empty($art9['ui_banners'])) { $addLines[] = 'Art. 9 bemærkninger: ' . implode(' | ', (array)$art9['ui_banners']); }
    if (!empty($art9['mismatches'])) { $addLines[] = 'Art. 9 mismatches: ' . json_encode($art9['mismatches'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    if (!empty($art9['ask_hooks'])) { $addLines[] = 'Art. 9 uafklarede: ' . implode(', ', (array)$art9['ask_hooks']); }
    // Art. 12
    if (isset($art12['art12_applies'])) { $addLines[] = 'Art. 12 gennemgående billet: ' . (($art12['art12_applies'] === true) ? 'GÆLDER' : (($art12['art12_applies'] === false) ? 'UNDTAGET' : 'UKENDT')); }
    // Compensation / refund
    $addLines[] = 'Kompensation (Art. 19): ' . (string)($claim['breakdown']['compensation']['pct'] ?? 0) . '% ('
      . number_format((float)($claim['breakdown']['compensation']['amount'] ?? 0), 2) . ' ' . (string)($claim['totals']['currency'] ?? 'EUR') . ')';
    if (!empty($refund['eligible'])) { $addLines[] = 'Refund (Art. 18): eligible'; }
    if (!empty($refusion['outcome'])) { $addLines[] = 'Refusion (Art. 18): ' . (string)$refusion['outcome']; }
    // Ticket/CIV scope
    if (($art9['hooks']['civ_marking_present'] ?? 'unknown') === 'Ja') { $addLines[] = 'CIV-mærkning set på billet.'; }
    if (!empty($art9['hooks']['train_specificity']) && $art9['hooks']['train_specificity'] !== 'unknown') { $addLines[] = 'Billet gyldighed: ' . (string)$art9['hooks']['train_specificity']; }
    if (!empty($art9['hooks']['fare_flex_type']) && $art9['hooks']['fare_flex_type'] !== 'unknown') { $addLines[] = 'Billetvilkår: ' . (string)$art9['hooks']['fare_flex_type']; }
    // Operators/products across legs
    $opLines = [];
    foreach ((array)($journey['segments'] ?? []) as $idx => $seg) {
      $op = $seg['operator'] ?? ($seg['carrier'] ?? '');
      $prod = $seg['product'] ?? '';
      $train = $seg['train'] ?? '';
      $from = $seg['from'] ?? '';
      $to = $seg['to'] ?? '';
      $piece = trim(implode(' ', array_filter([ (string)$op, (string)$prod, (string)$train ])));
      $label = trim($from . ' → ' . $to . ($piece !== '' ? (' (' . $piece . ')') : ''));
      if ($label !== '→') { $opLines[] = $label; }
    }
    if (!empty($opLines)) { $addLines[] = 'Segmenter/operatører/produkter: ' . implode(' | ', $opLines); }
    $additional_info = implode("\n", $addLines);
    $q = [
      'dep_date' => (string)($journey['depDate']['value'] ?? ($first['date'] ?? '')),
      'dep_station' => (string)($first['from'] ?? ''),
      'arr_station' => (string)($last['to'] ?? ''),
      'dep_time' => (string)($journey['schedDepTime']['value'] ?? ($first['schedDep'] ?? '')),
      'arr_time' => (string)($journey['schedArrTime']['value'] ?? ($last['schedArr'] ?? '')),
      'actual_arrival_date' => (string)($journey['actualArrDate']['value'] ?? ($last['actArrDate'] ?? '')),
      'actual_arr_time' => (string)($journey['actualArrTime']['value'] ?? ($last['actArr'] ?? '')),
      'train_no' => (string)($first['train'] ?? ''),
      'operator' => (string)($first['operator'] ?? ($journey['operatorName']['value'] ?? '')),
      'ticket_no' => (string)($journey['ticketNo']['value'] ?? ''),
      'price' => number_format((float)($price ?? 0), 2) . ' ' . (string)($currency ?? 'EUR'),
      'additional_info' => $additional_info,
      'reason_delay' => ($delayMins ?? 0) > 0 ? 1 : 0,
    ];
    $reimbUrl = $this->Url->build('/reimbursement/official?' . http_build_query($q), ['fullBase' => true]);
  ?>
  <p>
    <?= $this->Html->link('Generér officiel PDF (EU-formular)', $reimbUrl, ['target' => '_blank', 'rel' => 'noopener']) ?>
  </p>

  <?php
    // Build prefilled GET links for compute endpoints (potpourri)
    $qBase = ['journey' => $journey];
    $qComp = $qBase + ['euOnly' => true, 'delayMinEU' => (int)($delayMins ?? 0)];
    $qArt12 = ['journey' => $journey, 'meta' => []];
    $qArt9 = ['journey' => $journey, 'meta' => $art9['hooks'] ?? []];
    $qRefund = ['journey' => $journey, 'meta' => ['refundAlready' => false]];
    $qRefusion = ['journey' => $journey, 'meta' => []];
    $qClaim = [
      'country_code' => (string)($journey['country']['value'] ?? 'EU'),
      'currency' => (string)($claim['totals']['currency'] ?? 'EUR'),
      'ticket_price_total' => (float)($claim['breakdown']['refund']['amount'] ?? $price ?? 0),
      'trip' => ['through_ticket' => true, 'legs' => $legs],
      'disruption' => ['delay_minutes_final' => (int)($delayMins ?? 0)],
      'choices' => ['wants_refund' => false],
      'expenses' => ['meals' => 0, 'hotel' => 0, 'alt_transport' => 0, 'other' => 0],
      'already_refunded' => 0,
    ];
    $u = fn(string $path, array $params) => $this->Url->build($path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986), ['fullBase' => true]);
    $euRegLink = 'https://eur-lex.europa.eu/legal-content/EN/TXT/PDF/?uri=CELEX:32021R0782';
  ?>

  <h3>Potpourri af links (final)</h3>
  <ul>
    <li>Officiel EU-forordning (PDF): <a href="<?= h($euRegLink) ?>" target="_blank" rel="noopener">Regulation (EU) 2021/782 (EUR‑Lex)</a></li>
    <li>Officiel PDF-formular (med denne sag): <a href="<?= h($reimbUrl) ?>" target="_blank" rel="noopener">Udfyldt formular</a></li>
    <li>Compute API (GET med denne sag):
      <ul>
        <li><a href="<?= h($u('/api/compute/compensation', $qComp)) ?>" target="_blank" rel="noopener">Compensation (Art. 19)</a></li>
        <li><a href="<?= h($u('/api/compute/art12', $qArt12)) ?>" target="_blank" rel="noopener">Art. 12 evaluator</a></li>
        <li><a href="<?= h($u('/api/compute/art9', $qArt9)) ?>" target="_blank" rel="noopener">Art. 9 evaluator</a></li>
        <li><a href="<?= h($u('/api/compute/refund', $qRefund)) ?>" target="_blank" rel="noopener">Refund (Art. 18)</a></li>
        <li><a href="<?= h($u('/api/compute/refusion', $qRefusion)) ?>" target="_blank" rel="noopener">Refusion (Art. 18, reroute/downgrade)</a></li>
        <li><a href="<?= h($u('/api/compute/claim', $qClaim)) ?>" target="_blank" rel="noopener">Claim (Art. 18/19/20 samlet)</a></li>
      </ul>
    </li>
    <li>Mock & scenarier:
      <ul>
        <li><?= $this->Html->link('Mock tickets (mappeanalyse)', '/api/demo/mock-tickets', ['target' => '_blank', 'rel' => 'noopener']) ?></li>
        <li><?= $this->Html->link('Generér mock-billetter (PDF/PNG/TXT)', '/api/demo/generate-mocks', ['target' => '_blank', 'rel' => 'noopener']) ?></li>
        <li><?= $this->Html->link('Scenarier (liste)', '/api/demo/scenarios', ['target' => '_blank', 'rel' => 'noopener']) ?></li>
        <li><?= $this->Html->link('Kør scenarier (med evaluering)', '/api/demo/run-scenarios', ['target' => '_blank', 'rel' => 'noopener']) ?></li>
      </ul>
    </li>
    <li>Pipeline (end‑to‑end):
      <div style="margin:6px 0;">
        <form method="post" action="<?= h($this->Url->build('/api/pipeline/run')) ?>" target="_blank">
          <?php if (!empty($journey['segments'])): ?>
            <?php foreach ((array)$journey['segments'] as $i => $seg): ?>
              <input type="hidden" name="journey[segments][<?= (int)$i ?>][from]" value="<?= h((string)($seg['from'] ?? '')) ?>" />
              <input type="hidden" name="journey[segments][<?= (int)$i ?>][to]" value="<?= h((string)($seg['to'] ?? '')) ?>" />
              <input type="hidden" name="journey[segments][<?= (int)$i ?>][country]" value="<?= h((string)($seg['country'] ?? '')) ?>" />
              <input type="hidden" name="journey[segments][<?= (int)$i ?>][schedDep]" value="<?= h((string)($seg['schedDep'] ?? '')) ?>" />
              <input type="hidden" name="journey[segments][<?= (int)$i ?>][schedArr]" value="<?= h((string)($seg['schedArr'] ?? '')) ?>" />
            <?php endforeach; ?>
          <?php endif; ?>
          <input type="hidden" name="journey[country][value]" value="<?= h((string)($journey['country']['value'] ?? '')) ?>" />
          <input type="hidden" name="art9_meta[preinformed_disruption]" value="<?= h((string)($art9['hooks']['preinformed_disruption'] ?? 'unknown')) ?>" />
          <input type="hidden" name="compute[euOnly]" value="1" />
          <input type="hidden" name="compute[delayMinEU]" value="<?= (int)($delayMins ?? 0) ?>" />
          <button type="submit">Kør Unified Pipeline (POST)</button>
        </form>
      </div>
      <small style="color:#666;">Bemærk: Pipeline kræver POST. Knappen ovenfor poster den aktuelle sag og åbner resultatet i en ny fane.</small>
    </li>
  </ul>

  <p><?= $this->Html->link('Tilbage til upload', ['action' => 'index']) ?></p>
</div>
