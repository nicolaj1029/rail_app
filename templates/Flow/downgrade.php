<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];

$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isCompleted = ($travelState === 'completed');
$isOngoing = ($travelState === 'ongoing');

$title = $isOngoing
    ? 'TRIN 8 - Nedgradering (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 8 - Nedgradering (afsluttet rejse)' : 'TRIN 8 - Nedgradering (klasse/reservation)');
$hint = $isOngoing
    ? 'Udfyld kun hvis du allerede er blevet placeret i lavere klasse eller mistede reservation.'
    : ($isCompleted ? 'Udfyld kun hvis du blev placeret i lavere klasse eller mistede reservation.' : '');

$v = fn(string $k): string => (string)($form[$k] ?? '');
$missedStation = (string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? ''));

$articles = (array)($profile['articles'] ?? []);
$showArt18 = !isset($articles['art18']) || $articles['art18'] !== false;
$showArt182 = !isset($articles['art18_2']) || $articles['art18_2'] !== false;
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

  <?php if (!$showArt18 || !$showArt182): ?>
    <div class="card mt12" style="background:#fff3cd;border-color:#eed27c;">
      <strong>Bemaerk</strong>
      <div class="small muted mt4">Nedgradering kan vaere undtaget for denne sag (profil/exemptions).</div>
    </div>
  <?php endif; ?>

  <?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'downgrade'], 'novalidate' => true]) ?>

  <div class="card mt12">
    <div class="widget-title">
      <span class="icon-badge" aria-hidden="true">
        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
          <path fill="#1e3a8a" d="M12 3a1 1 0 0 1 1 1v10.6l2.3-2.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L11 14.6V4a1 1 0 0 1 1-1z"/>
        </svg>
      </span>
      <span>Nedgradering (klasse/reservation)</span>
    </div>
    <div class="small muted mt4">Udfyld kun hvis du reelt blev placeret lavere end koebt, eller mistede reservation.</div>

    <div class="mt8">
      <div>1. Blev du nedgraderet under rejsen?</div>
      <label><input type="radio" name="downgrade_occurred" value="yes" <?= $v('downgrade_occurred')==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="downgrade_occurred" value="no" <?= $v('downgrade_occurred')!=='yes'?'checked':'' ?> /> Nej</label>
    </div>

    <div id="downgradeDetails" class="mt12 <?= $v('downgrade_occurred')==='yes' ? '' : 'hidden' ?>">
      <details class="quick" <?= ($v('downgrade_comp_basis')!=='' || $v('downgrade_segment_share')!=='') ? 'open' : '' ?>>
        <summary><span class="chev">&gt;</span>Hurtig beregning (valgfri)</summary>
        <div class="small muted mt4">Du kan springe dette over og i stedet udfylde per-leg tabellen nedenfor.</div>
        <div class="grid-2 mt8">
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
        </div>
      </details>
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
      <span>Per-leg (koebt vs leveret)</span>
    </div>
    <div class="small muted mt4">LLM/OCR forsoeger at udfylde koebt klasse/reservation. Du kan rette og angive leveret niveau.</div>
    <?= $this->element('downgrade_table', [
        'form' => $form,
        'meta' => $meta,
        'missedStation' => $missedStation,
    ]) ?>
  </div>

  <div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
    <?= $this->Html->link('Tilbage', ['action' => 'assistance'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Fortsaet', ['class' => 'button', 'type' => 'submit']) ?>
  </div>

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
        var transport = <?= json_encode((string)($form['a20_3_solution_type'] ?? ($form['assistance_alt_transport_type'] ?? ''))) ?>;
        var isBusTaxi = (transport === 'bus' || transport === 'taxi');
        var isReroute = (remedy === 'reroute_soonest' || remedy === 'reroute_later');
        var dgcYes = q('input[name="downgrade_occurred"][value="yes"]');
        if (!isBusTaxi || !isReroute || !(dgcYes && dgcYes.checked)) return;

        qa('select[name^="leg_class_delivered"]').forEach(function(sel){
          if (!sel.value) { sel.value = '2nd'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
        });
        qa('select[name^="leg_reservation_delivered"]').forEach(function(sel){
          if (!sel.value) { sel.value = 'missing'; sel.dispatchEvent(new Event('change', {bubbles:true})); }
        });
      } catch(e) { /* ignore */ }
    }

    document.addEventListener('DOMContentLoaded', function(){
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
