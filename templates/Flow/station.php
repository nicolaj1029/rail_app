<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$isPreview = !empty($flowPreview);

$v = static fn(string $k, string $fallback = ''): string => (string)($form[$k] ?? ($meta[$k] ?? $fallback));
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
if (!in_array($transportMode, ['rail', 'ferry', 'bus', 'air'], true)) {
    $transportMode = 'rail';
}
$routerType = (string)($routerType ?? ($form['initial_incident_router_type'] ?? ($meta['initial_incident_router_type'] ?? 'mode')));
$routerCandidates = (array)($routerCandidates ?? ($meta['initial_incident_candidates'] ?? []));
$initialIncidentMode = strtolower($v('initial_incident_mode', $transportMode));
if (!in_array($initialIncidentMode, ['rail', 'ferry', 'bus', 'air', 'unknown'], true)) {
    $initialIncidentMode = $transportMode;
}
$initialIncidentContractKey = (string)$v('initial_incident_contract_key');
$initialIncidentSegmentKey = (string)$v('initial_incident_segment_key');
?>

<style>
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
</style>

<h1>TRIN 3 - Foerste ramte segment</h1>

<?= $this->element('flow_locked_notice') ?>
<?= $this->Form->create(null, ['novalidate' => true]) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
  <?php if ($routerType !== 'none'): ?>
  <div class="card">
    <strong>Foerste ramte segment</strong>
    <p class="small muted mt8">
      Dette trin bruges kun til at route sagen videre til det rigtige spor.
      Valget styrer hvilket mode-specifikt gating-spor der vises i naeste trin.
    </p>

    <div class="mt12">
      <?php if ($routerType === 'contract'): ?>
        <div>Hvilken kontrakt eller billet blev foerst ramt af problemet?</div>
        <?php foreach ($routerCandidates as $candidate): ?>
          <?php if (!is_array($candidate)) { continue; } ?>
          <div class="mt8">
            <label>
              <input type="radio" name="initial_incident_contract_key" value="<?= h((string)($candidate['key'] ?? '')) ?>" <?= $initialIncidentContractKey === (string)($candidate['key'] ?? '') ? 'checked' : '' ?> />
              <?= h((string)($candidate['label'] ?? '')) ?>
            </label>
          </div>
        <?php endforeach; ?>
        <div class="mt8">
          <label><input type="radio" name="initial_incident_contract_key" value="" <?= $initialIncidentContractKey === '' ? 'checked' : '' ?> /> Ved ikke</label>
        </div>
      <?php elseif ($routerType === 'contract_segment'): ?>
        <div>Hvilken konkrete del af rejsen eller kontrakt blev foerst ramt af problemet?</div>
        <?php foreach ($routerCandidates as $candidate): ?>
          <?php if (!is_array($candidate)) { continue; } ?>
          <div class="mt8">
            <label>
              <input type="radio" name="initial_incident_segment_key" value="<?= h((string)($candidate['key'] ?? '')) ?>" <?= $initialIncidentSegmentKey === (string)($candidate['key'] ?? '') ? 'checked' : '' ?> />
              <?= h((string)($candidate['label'] ?? '')) ?>
            </label>
          </div>
        <?php endforeach; ?>
        <div class="mt8">
          <label><input type="radio" name="initial_incident_segment_key" value="unknown" <?= $initialIncidentSegmentKey === '' ? 'checked' : '' ?> /> Ved ikke</label>
        </div>
      <?php else: ?>
        <div>Hvilken del af rejsen blev foerst ramt af problemet?</div>
        <label><input type="radio" name="initial_incident_mode" value="rail" <?= $initialIncidentMode === 'rail' ? 'checked' : '' ?> /> Tog</label>
        <label class="ml8"><input type="radio" name="initial_incident_mode" value="ferry" <?= $initialIncidentMode === 'ferry' ? 'checked' : '' ?> /> Faerge / havn / terminal</label>
        <label class="ml8"><input type="radio" name="initial_incident_mode" value="bus" <?= $initialIncidentMode === 'bus' ? 'checked' : '' ?> /> Bus</label>
        <label class="ml8"><input type="radio" name="initial_incident_mode" value="air" <?= $initialIncidentMode === 'air' ? 'checked' : '' ?> /> Fly / lufthavn / boarding</label>
        <label class="ml8"><input type="radio" name="initial_incident_mode" value="unknown" <?= $initialIncidentMode === 'unknown' ? 'checked' : '' ?> /> Ved ikke</label>
      <?php endif; ?>
    </div>

    <div class="small muted mt12">
      Kontraktkanalen fra TRIN 2 aendres ikke her. Dette valg bruges kun til at aflede det videre gating-spor.
      <?php if ($transportMode === 'rail' || $initialIncidentMode === 'rail' || ($routerType !== 'mode' && !empty($routerCandidates))): ?>
        Hvis det valgte spor bliver rail, gaar sagen videre til det rail-specifikke trin om stranding foer den almindelige gating.
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="mt12" style="display:flex; gap:8px; align-items:center;">
    <?= $this->Html->link('<- Tilbage', ['action' => 'entitlements'], ['class' => 'button', 'style' => 'background:#eee; color:#333;', 'escape' => false]) ?>
    <?= $this->Form->button('Naeste trin ->', ['class' => 'button']) ?>
  </div>
</fieldset>
<?= $this->Form->end() ?>

<?= $this->element('hooks_panel') ?>
