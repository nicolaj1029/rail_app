<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$routeType = $routeType ?? 'connecting';
$routeLegs = $routeLegs ?? [];
$selectedLeg = $selectedLeg ?? [];
$isPreview = !empty($flowPreview);
$stopoversRaw = (string)($form['air_stopover_airports'] ?? '');
$stepHeading = 'TRIN 4';
$stepTitle = 'Vaelg berort leg';
foreach (($flowSteps ?? []) as $step) {
  if ((string)($step['action'] ?? '') !== 'airLegSelect') {
    continue;
  }
  $stepNum = $step['ui_num'] ?? $step['num'] ?? 4;
  $stepHeading = 'TRIN ' . (string)$stepNum;
  $stepTitle = (string)($step['title'] ?? $stepTitle);
  break;
}
$routePreviewPoints = [];
if (!empty($routeLegs)) {
  foreach ($routeLegs as $index => $leg) {
    if (!is_array($leg)) { continue; }
    $depLabel = trim((string)($leg['dep_label'] ?? ''));
    $arrLabel = trim((string)($leg['arr_label'] ?? ''));
    if ($index === 0 && $depLabel !== '') {
      $routePreviewPoints[] = $depLabel;
    }
    if ($arrLabel !== '') {
      $routePreviewPoints[] = $arrLabel;
    }
  }
}
$routePreview = implode(' -> ', $routePreviewPoints);
?>

<style>
  .air-leg-step { max-width: 1040px; }
  .air-leg-card { padding:16px; border:1px solid #dbe3ea; background:#fff; border-radius:16px; box-shadow:0 8px 18px rgba(15, 23, 42, .04); }
  .air-leg-list { display:grid; gap:10px; margin-top:12px; }
  .air-leg-option { position:relative; border:1px solid #dbe3ea; border-radius:14px; background:#fff; transition:border-color .15s ease, box-shadow .15s ease; }
  .air-leg-option:hover { border-color:#94a3b8; box-shadow:0 10px 18px rgba(15, 23, 42, .06); }
  .air-leg-option.selected { border-color:#0f766e; box-shadow:0 0 0 2px rgba(15, 118, 110, .12); background:#f0fdfa; }
  .air-leg-option input[type=radio] { position:absolute; top:16px; left:16px; }
  .air-leg-label { display:block; cursor:pointer; padding:14px 16px 14px 46px; }
  .air-leg-title { font-size:18px; font-weight:800; color:#0f172a; }
  .air-leg-meta { margin-top:6px; color:#475569; font-size:13px; }
  .air-leg-helper { border-left:4px solid #38bdf8; background:#f0f9ff; border-radius:10px; padding:10px 12px; color:#0f172a; }
  .air-leg-grid { display:grid; grid-template-columns: 1.2fr 1fr; gap:14px; align-items:start; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .mt16 { margin-top:16px; }
  .small { font-size:12px; }
  .muted { color:#64748b; }
  .step-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .button-secondary { display:inline-block; padding:12px 16px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#0f172a; font-weight:700; text-decoration:none; }
  .button-secondary:hover { background:#f8fafc; border-color:#94a3b8; }
  .button-primary { display:inline-block; padding:12px 16px; border:none; border-radius:12px; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
  .button-primary:hover { background:#1e293b; }
  .button-helper { color:#64748b; font-size:12px; }
  .air-leg-textarea { width:100%; min-height:90px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:12px; resize:vertical; }
  @media (max-width: 820px) {
    .air-leg-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="air-leg-step">
  <h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
  <div class="small muted">Hvis rejsen havde mellemlandinger, vaelger du her det leg, hvor forsinkelsen eller aflysningen opstod. Leg-valget sker efter flight-opslag, saa carrier og rutestruktur er mere stabile.</div>
  <?php if ($routePreview !== ''): ?>
    <div class="air-leg-helper mt12">
      Registreret rute: <strong><?= h($routePreview) ?></strong>
    </div>
  <?php endif; ?>

  <?= $this->element('flow_locked_notice') ?>
  <?= $this->Form->create(null, ['novalidate' => true]) ?>
  <fieldset <?= $isPreview ? 'disabled' : '' ?>>

    <div class="air-leg-grid mt12">
      <section class="air-leg-card">
        <strong>Valg af berort leg</strong>
        <div class="air-leg-helper mt12">
          Vaelg det flysegment, der faktisk blev forsinket, aflyst eller paa anden maade gik galt. Hvis den valgte flight allerede matcher et bestemt leg, er det forvalgt for dig.
        </div>

        <?php if (!empty($routeLegs)): ?>
          <div class="air-leg-list">
            <?php foreach ($routeLegs as $idx => $leg): ?>
              <?php
                $legKey = (string)($leg['key'] ?? ('leg_' . $idx));
                $checked = $legKey !== '' && $legKey === (string)($selectedLeg['key'] ?? '');
                $rowId = 'air_leg_' . $idx;
              ?>
              <div class="air-leg-option<?= $checked ? ' selected' : '' ?>">
                <input id="<?= h($rowId) ?>" type="radio" name="air_affected_leg_key" value="<?= h($legKey) ?>" <?= $checked ? 'checked' : '' ?> />
                <label class="air-leg-label" for="<?= h($rowId) ?>">
                  <div class="air-leg-title"><?= h((string)($leg['title'] ?? 'Leg')) ?></div>
                  <div class="air-leg-meta">
                    <?php if (!empty($leg['dep_iata']) || !empty($leg['arr_iata'])): ?>
                      <?= h((string)($leg['dep_iata'] ?? '?')) ?> -> <?= h((string)($leg['arr_iata'] ?? '?')) ?>
                    <?php else: ?>
                      Bruges som route-grundlag for næste trin
                    <?php endif; ?>
                  </div>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="small muted mt12">Vi kunne ikke opbygge legs endnu. Juster mellemlanding(er) i boksen til hoejre og proev igen.</div>
        <?php endif; ?>
      </section>

      <section class="air-leg-card">
        <strong>Mellemlanding(er)</strong>
        <div class="small muted mt8">Hvis listen ser forkert ud, kan du rette lufthavnene her. Skriv dem i raekkefolge, adskilt med komma eller ny linje.</div>
        <div class="mt12">
          <textarea class="air-leg-textarea" name="air_stopover_airports" placeholder="Fx AMS, CDG"><?= h($stopoversRaw) ?></textarea>
        </div>
        <div class="small muted mt8">Eksempel: `CPH -> AMS -> CDG -> JFK` betyder, at du skriver `AMS, CDG` i feltet.</div>
      </section>
    </div>

    <div class="step-actions mt16">
      <?= $this->Html->link('Tilbage', ['action' => 'airFlightSelect'], ['class' => 'button-secondary']) ?>
      <button type="submit" class="button-primary">Videre til haendelse</button>
      <span class="button-helper">Du kan stadig justere mellemlandingerne ved at gaa tilbage til TRIN 2 eller skifte flight i TRIN 3.</span>
    </div>

  </fieldset>
  <?= $this->Form->end() ?>
  <?= $this->element('flow_autosave', ['step' => 'air_leg_select']) ?>
</div>

<script>
(() => {
  const options = Array.from(document.querySelectorAll('.air-leg-option'));
  if (!options.length) return;

  const sync = () => {
    options.forEach((option) => {
      const input = option.querySelector('input[type="radio"]');
      option.classList.toggle('selected', !!(input && input.checked));
    });
  };

  options.forEach((option) => {
    option.addEventListener('click', (event) => {
      const input = option.querySelector('input[type="radio"]');
      if (!input) return;
      if (event.target instanceof HTMLInputElement && event.target.type === 'radio') {
        sync();
        return;
      }
      input.checked = true;
      sync();
    });
  });

  sync();
})();
</script>
