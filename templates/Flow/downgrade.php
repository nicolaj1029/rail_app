<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$metaView = $metaView ?? null;
$meta = is_array($metaView) ? $metaView : ($meta ?? []);
$profile = $profile ?? ['articles' => []];
$affectedLegsAuto = $affectedLegsAuto ?? [];
$downgradeScopeAuto = $downgradeScopeAuto ?? ['from' => null, 'to' => null, 'basis' => '', 'confidence' => 0.0];
$journeyRowsDowng = $journeyRowsDowng ?? [];
$downgradeTicketOptions = $downgradeTicketOptions ?? [];
$downgradeTicketFile = (string)($downgradeTicketFile ?? ($form['downgrade_ticket_file'] ?? ''));
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$isAir = ($transportMode === 'air');
$isFerry = ($transportMode === 'ferry');

$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');

$title = $isAir
    ? ($isOngoing ? 'TRIN 9 - Nedgradering (fly, igangvaerende rejse)' : ($isCompleted ? 'TRIN 9 - Nedgradering (fly, afsluttet rejse)' : 'TRIN 9 - Nedgradering (fly)'))
    : ($isFerry
        ? ($isOngoing ? 'TRIN 9 - Serviceafvigelse (faerge, igangvaerende rejse)' : ($isCompleted ? 'TRIN 9 - Serviceafvigelse (faerge, afsluttet rejse)' : 'TRIN 9 - Serviceafvigelse (faerge)'))
        : ($isOngoing
            ? 'TRIN 9 - Nedgradering (igangvaerende rejse)'
            : ($isCompleted ? 'TRIN 9 - Nedgradering (afsluttet rejse)' : 'TRIN 9 - Nedgradering (klasse/reservation)')));
$hint = $isAir
    ? ($isOngoing ? 'Udfyld kun hvis du allerede er blevet placeret i lavere kabineklasse end koebt.' : ($isCompleted ? 'Udfyld kun hvis du blev placeret i lavere kabineklasse end koebt.' : ''))
    : ($isFerry
        ? 'Valgfrit trin. Brug det kun hvis den konkrete faergeservice afveg fra det koebte. Hotel, hoteltransport, refund, ombooking og ankomstkompensation hoerer fortsat hjemme i TRIN 7, 8 og 10.'
        : ($isOngoing
            ? 'Udfyld kun hvis du allerede er blevet placeret i lavere klasse eller mistede reservation.'
            : ($isCompleted ? 'Udfyld kun hvis du blev placeret i lavere klasse eller mistede reservation.' : '')));

$v = fn(string $k): string => (string)($form[$k] ?? '');
$missedStation = (string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? ''));
$isPreview = !empty($flowPreview);

$articles = (array)($profile['articles'] ?? []);
$showArt18 = !isset($articles['art18']) || $articles['art18'] !== false;
$showArt182 = !isset($articles['art18_2']) || $articles['art18_2'] !== false;
$airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($meta['_multimodal']['air_scope']['air_distance_band'] ?? ($meta['air_distance_band'] ?? '')))));
$airAutoRefundPercent = match ($airDistanceBand) {
  'up_to_1500' => '30',
  'intra_eu_over_1500', 'other_1500_to_3500' => '50',
  'other_over_3500' => '75',
  default => '',
};
$airBookedClass = strtolower((string)($form['air_downgrade_booked_class'] ?? ''));
$airFlownClass = strtolower((string)($form['air_downgrade_flown_class'] ?? ''));
$airRefundPercent = (string)($form['air_downgrade_refund_percent'] ?? ($airAutoRefundPercent !== '' ? $airAutoRefundPercent : ''));
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .hidden { display:none; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .flow-wrapper { max-width: 1100px; margin: 0 auto; }
  select, input[type="text"], input[type="number"] { max-width: 520px; width: 100%; }

  .widget-title { display:flex; align-items:center; gap:10px; font-weight:800; }
  .icon-badge { width:28px; height:28px; border-radius:999px; border:1px solid #cfe0ff; background:#e9f2ff; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }
  .icon-badge svg { width:16px; height:16px; display:block; }

  details.quick { margin-top:10px; }
  details.quick > summary { cursor:pointer; user-select:none; font-weight:700; list-style:none; }
  details.quick > summary::-webkit-details-marker { display:none; }
  details.quick > summary .chev { display:inline-block; width:10px; margin-right:6px; color:#1e3a8a; transition:transform .12s ease; }
  details.quick[open] > summary .chev { transform:rotate(90deg); }
</style>

<div class="flow-wrapper">
  <h1><?= h($title) ?></h1>
  <?php if ($hint !== ''): ?>
    <p class="small muted"><?= h($hint) ?></p>
  <?php endif; ?>

  <?php if (is_array($downgradeTicketOptions) && count($downgradeTicketOptions) > 1): ?>
    <div class="card mt12" style="background:#f8fafc;border-color:#dbeafe;">
      <div class="small muted"><strong>Billetvalg (TRIN 9)</strong></div>
      <div class="mt8 grid-2">
        <label>Hvilken billet udfylder du nedgradering for?
          <select id="downgradeTicketSelect">
            <?php foreach ($downgradeTicketOptions as $opt): ?>
              <?php $f = (string)($opt['file'] ?? ''); $lbl = (string)($opt['label'] ?? $f); ?>
              <option value="<?= h($f) ?>" <?= $f!=='' && $f===$downgradeTicketFile ? 'selected' : '' ?>><?= h($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="small muted" style="align-self:end;">Skift billet for at undgaa at blande per-leg felter paa tværs af kontrakter.</div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$showArt18 || !$showArt182): ?>
    <div class="card mt12" style="background:#fff3cd;border-color:#eed27c;">
      <strong>Bemaerk</strong>
      <div class="small muted mt4">Nedgradering kan vaere undtaget for denne sag (profil/exemptions).</div>
    </div>
  <?php endif; ?>

  <?= $this->element('flow_locked_notice') ?>
  <?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'downgrade'], 'novalidate' => true]) ?>
  <fieldset <?= $isPreview ? 'disabled' : '' ?>>
  <?= $this->Form->hidden('downgrade_ticket_file', ['value' => $downgradeTicketFile]) ?>

  <div class="card mt12">
    <div class="widget-title">
      <span class="icon-badge" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
          <path fill="#1e3a8a" d="M12 3a1 1 0 0 1 1 1v10.6l2.3-2.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L11 14.6V4a1 1 0 0 1 1-1z"/>
        </svg>
      </span>
      <span><?= $isAir ? 'Nedgradering (kabineklasse)' : ($isFerry ? 'Leveret service afveg fra det koebte' : 'Nedgradering (klasse/reservation)') ?></span>
    </div>
    <div class="small muted mt4"><?= $isAir ? 'Udfyld kun hvis du reelt blev placeret i lavere kabineklasse end koebt. Flight-sporet bruger artikel 10-procenter som vejledning.' : ($isFerry ? 'Brug kun dette trin ved kontraktmaessig serviceafvigelse, fx kahyt/plads/service ikke leveret som koebt. Det er ikke ferry Art. 17-assistance eller Art. 18-ombooking.' : 'Udfyld kun hvis du reelt blev placeret lavere end koebt, eller mistede reservation.') ?></div>

    <div class="mt8">
      <div>1. <?= $isAir ? 'Blev du placeret i lavere kabineklasse end koebt?' : ($isFerry ? 'Fik du ikke den konkrete service eller plads, du havde betalt for?' : 'Blev du nedgraderet under rejsen?') ?></div>
      <label><input type="radio" name="downgrade_occurred" value="yes" <?= $v('downgrade_occurred')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="downgrade_occurred" value="no" <?= $v('downgrade_occurred')!=='yes'?'checked':'' ?> /> Nej</label>
    </div>

    <div id="downgradeDetails" class="mt12 <?= $v('downgrade_occurred')==='yes' ? '' : 'hidden' ?>">
      <?php if (!$isFerry): ?>
      <details class="quick">
        <summary><span class="chev">&gt;</span>Avanceret (valgfrit)</summary>
        <div class="small muted mt4"><?= $isAir ? 'Hurtig artikel 10-vurdering. Du kan springe dette over og i stedet bruge flight-segmenterne nedenfor som dokumentation.' : 'Hurtig beregning. Du kan springe dette over og i stedet udfylde per-leg tabellen nedenfor.' ?></div>
        <div class="grid-2 mt8">
          <?php if ($isAir): ?>
            <label>Koebt kabineklasse
              <select name="air_downgrade_booked_class">
                <option value="">Vaelg</option>
                <option value="economy" <?= $airBookedClass==='economy'?'selected':'' ?>>Economy</option>
                <option value="premium_economy" <?= $airBookedClass==='premium_economy'?'selected':'' ?>>Premium Economy</option>
                <option value="business" <?= $airBookedClass==='business'?'selected':'' ?>>Business</option>
                <option value="first" <?= $airBookedClass==='first'?'selected':'' ?>>First</option>
              </select>
            </label>
            <label>Faktisk floejet kabineklasse
              <select name="air_downgrade_flown_class">
                <option value="">Vaelg</option>
                <option value="economy" <?= $airFlownClass==='economy'?'selected':'' ?>>Economy</option>
                <option value="premium_economy" <?= $airFlownClass==='premium_economy'?'selected':'' ?>>Premium Economy</option>
                <option value="business" <?= $airFlownClass==='business'?'selected':'' ?>>Business</option>
                <option value="first" <?= $airFlownClass==='first'?'selected':'' ?>>First</option>
              </select>
            </label>
            <label>Artikel 10-refusionsprocent
              <select name="air_downgrade_refund_percent">
                <option value="">Auto</option>
                <option value="30" <?= $airRefundPercent==='30'?'selected':'' ?>>30%</option>
                <option value="50" <?= $airRefundPercent==='50'?'selected':'' ?>>50%</option>
                <option value="75" <?= $airRefundPercent==='75'?'selected':'' ?>>75%</option>
              </select>
              <?php if ($airAutoRefundPercent !== ''): ?>
                <div class="small muted mt4">Auto fra distancekategori: <?= h($airDistanceBand) ?> -> <?= h($airAutoRefundPercent) ?>%</div>
              <?php endif; ?>
            </label>
            <label>Andel af den relevante flyvning (0-1)
              <?php $share = $v('downgrade_segment_share'); ?>
              <input type="number" name="downgrade_segment_share" min="0" max="1" step="0.01" value="<?= h($share !== '' ? $share : '1') ?>" />
            </label>
          <?php else: ?>
            <label>Basis (CIV/Bilag II)
              <?php $basis = $v('downgrade_comp_basis'); ?>
              <select name="downgrade_comp_basis">
                <option value="" <?= $basis===''?'selected':'' ?>>-</option>
                <option value="seat" <?= $basis==='seat'?'selected':'' ?>>S&aelig;de (1-&gt;2 klasse)</option>
                <option value="couchette" <?= $basis==='couchette'?'selected':'' ?>>Ligge (komfort trin ned)</option>
                <option value="sleeper" <?= $basis==='sleeper'?'selected':'' ?>>Sove (komfort trin ned)</option>
              </select>
              <div class="small muted mt4">Hvis du er i tvivl, kan du lade den st&aring; tom og udfylde per-leg felterne nedenfor.</div>
            </label>

            <label>Andel af rejsen (0-1)
              <?php $share = $v('downgrade_segment_share'); ?>
              <input type="number" name="downgrade_segment_share" min="0" max="1" step="0.01" value="<?= h($share !== '' ? $share : '1') ?>" />
              <?php if ($v('downgrade_segment_share_basis') !== ''): ?>
                <div class="small muted mt4">Auto: <?= h($v('downgrade_segment_share_basis')) ?> (conf: <?= h($v('downgrade_segment_share_conf')) ?>)</div>
              <?php endif; ?>
            </label>
          <?php endif; ?>
        </div>
      </details>
      <?php else: ?>
      <div class="small muted mt4">Eksempler: kahyt ikke leveret, reserveret siddeplads bortfaldt, eller en saerlig service/plads blev erstattet af en ringere loesning. Hvis det kun handler om hotel, maaltider eller ombooking, skal du bruge TRIN 7-8 i stedet.</div>
      <?php endif; ?>
    </div>
  </div>

    <div class="card mt12">
    <div class="widget-title">
      <span class="icon-badge" aria-hidden="true" style="background:#f3f4f6;border-color:#e5e7eb;">
        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
          <path fill="#374151" d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5zm2 0v14h12V5H6z"/>
          <path fill="#374151" d="M8 8h8v2H8V8zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/>
        </svg>
      </span>
      <span><?= $isAir ? 'Flight-segmenter (koebt vs floejet)' : ($isFerry ? 'Overfart / service (koebt vs leveret)' : 'Per-leg (koebt vs leveret)') ?></span>
    </div>
    <div class="small muted mt4"><?= $isAir ? 'OCR/metadata forsoeger at udfylde koebt klasse. Du kan bruge flight-segmenterne som dokumentation for, hvad der faktisk blev leveret.' : ($isFerry ? 'Brug denne blok som dokumentation for serviceafvigelsen. Den bruges ikke til hotel, hoteltransport eller ombookingsudgifter.' : 'LLM/OCR forsoeger at udfylde koebt klasse/reservation. Du kan rette og angive leveret niveau.') ?></div>
    <?php if (!empty($affectedLegsAuto)): ?>
      <div class="small muted mt4">
        Auto-scope: ben <?= h(implode(', ', array_map(static fn($i)=> (string)(((int)$i)+1), $affectedLegsAuto))) ?>
        <?php if (!empty($downgradeScopeAuto['from']) || !empty($downgradeScopeAuto['to'])): ?>
          (<?= h((string)($downgradeScopeAuto['from'] ?? '')) ?> &rarr; <?= h((string)($downgradeScopeAuto['to'] ?? '')) ?>)
        <?php endif; ?>
        <?php if (!empty($downgradeScopeAuto['basis'])): ?>
          — <?= h((string)$downgradeScopeAuto['basis']) ?> (conf: <?= h((string)($downgradeScopeAuto['confidence'] ?? '')) ?>)
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?= $this->element('downgrade_table', [
        'journeyRowsDowng' => $journeyRowsDowng,
        'form' => $form,
        'meta' => $meta,
        'missedStation' => $missedStation,
        'affectedLegsAuto' => $affectedLegsAuto ?? [],
        'isAir' => $isAir,
        'isFerry' => $isFerry,
    ]) ?>
  </div>

  <div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('Tilbage', ['action' => 'assistance'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsaet', ['class' => 'button', 'type' => 'submit']) ?>
  </div>

  </fieldset>
  <?= $this->Form->end() ?>
</div>

<script>
  (function(){
    function q(sel){ return document.querySelector(sel); }
    function qa(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }

    function toggleDetails(){
      var yes = q('input[name="downgrade_occurred"][value="yes"]');
      var wrap = document.getElementById('downgradeDetails');
      if (!wrap) return;
      var on = !!(yes && yes.checked);
      wrap.classList.toggle('hidden', !on);
    }

    // Safe bus/taxi prefill: only fill blank delivered fields when reroute + bus/taxi + downgrade=yes.
    function maybePrefillBusTaxi(){
      try {
        var remedy = <?= json_encode((string)($form['remedyChoice'] ?? '')) ?>;
        // Prefer TRIN 6 reroute transport mode (Art.18). Fall back to TRIN 5 (Art.20) choices for legacy sessions.
        var transport = <?= json_encode((string)($form['a18_reroute_mode'] ?? ($form['a20_3_solution_type'] ?? ($form['assistance_alt_transport_type'] ?? '')))) ?>;
        // Treat coach as bus (some hooks/models may emit "coach" instead of "bus").
        var isBusTaxi = (transport === 'bus' || transport === 'taxi' || transport === 'coach');
        var isReroute = (remedy === 'reroute_soonest' || remedy === 'reroute_later');
        var dgcYes = q('input[name="downgrade_occurred"][value="yes"]');
        if (!isBusTaxi || !isReroute || !(dgcYes && dgcYes.checked)) return;

        var affected = <?= json_encode(array_values($affectedLegsAuto ?? [])) ?>;
        if (!affected || !affected.length) {
          // If scope is unknown, fall back to previous behavior (all legs)
          qa('select[name^="leg_class_delivered"]').forEach(function(sel){
            if (!sel.value) { sel.value = '2nd'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
          });
          qa('select[name^="leg_reservation_delivered"]').forEach(function(sel){
            if (!sel.value) { sel.value = 'missing'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
          });
          return;
        }
        affected.forEach(function(i){
          var selC = q('select[name="leg_class_delivered['+i+']"]');
          if (selC && !selC.value) { selC.value = '2nd'; selC.dispatchEvent(new Event('change', {bubbles:true})); }
          var selR = q('select[name="leg_reservation_delivered['+i+']"]');
          if (selR && !selR.value) { selR.value = 'missing'; selR.dispatchEvent(new Event('change', {bubbles:true})); }
        });
      } catch(e) { /* ignore */ }
    }

    document.addEventListener('DOMContentLoaded', function(){
      var sel = document.getElementById('downgradeTicketSelect');
      if (sel) {
        sel.addEventListener('change', function(){
          var v = sel.value || '';
          var url = new URL(window.location.href);
          if (v) { url.searchParams.set('ticket', v); }
          else { url.searchParams.delete('ticket'); }
          window.location.href = url.toString();
        });
      }
      toggleDetails();
      qa('input[name="downgrade_occurred"]').forEach(function(r){
        r.addEventListener('change', function(){
          toggleDetails();
          maybePrefillBusTaxi();
        });
      });
      maybePrefillBusTaxi();
    });
  })();
</script>
