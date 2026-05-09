<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$ferryRights = (array)($ferryRights ?? []);
$ferryScope = (array)($ferryScope ?? []);
$ferryContract = (array)($ferryContract ?? []);
$selectedDeparture = (array)($meta['ferry_selected_departure'] ?? []);
$ferryCaseCreatedAt = (string)($meta['ferry_case_created_at'] ?? '');
$isPreview = !empty($flowPreview);
$bookingReference = trim((string)($form['booking_reference'] ?? ($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))));
$operatorName = trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? ($ferryContract['primary_claim_party_name'] ?? ''))));
$vesselName = trim((string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? '')));
$routeLabel = trim((string)($form['dep_station'] ?? '')) . ' -> ' . trim((string)($form['arr_station'] ?? ''));
$routeLabel = trim($routeLabel, ' ->');
$stepHeading = 'TRIN 10';
$stepTitle = 'Kontakt & opret ferry-sag';
if (!empty($flowSteps) && is_array($flowSteps)) {
  foreach ($flowSteps as $flowStep) {
    if ((string)($flowStep['action'] ?? '') !== 'compensation') {
      continue;
    }
    $stepNum = $flowStep['ui_num'] ?? $flowStep['num'] ?? 10;
    $stepHeading = 'TRIN ' . (string)$stepNum;
    $stepTitle = (string)($flowStep['title'] ?? $stepTitle);
    break;
  }
}
?>

<style>
  .ferry-contact-shell,
  .flow-wrapper.ferry-contact-shell { max-width: 1080px; margin: 0 auto; }
  .ferry-contact-grid { display:grid; gap:20px; grid-template-columns:minmax(0, 1fr); align-items:start; }
  .ferry-contact-panel { border:1px solid #dbe4ee; border-radius:14px; background:#fff; padding:24px; }
  .ferry-contact-title { margin:0 0 8px; font-size:40px; line-height:1.05; letter-spacing:-0.03em; color:#0f172a; }
  .ferry-contact-lead { margin:0; font-size:16px; line-height:1.55; color:#475569; }
  .ferry-contact-form { margin-top:22px; display:grid; gap:16px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
  .ferry-contact-field { display:grid; gap:8px; }
  .ferry-contact-field label { font-weight:700; color:#0f172a; }
  .ferry-contact-help { font-size:12px; color:#64748b; margin-top:-2px; }
  .ferry-contact-checks { display:grid; gap:14px; margin-top:10px; }
  .ferry-contact-check { display:flex; gap:10px; align-items:flex-start; color:#1e293b; }
  .ferry-contact-check input { width:auto; margin-top:3px; }
  .ferry-contact-actions { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:22px; }
  .ferry-contact-meta { margin-top:16px; display:grid; gap:10px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); }
  .ferry-contact-meta-card { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:12px 14px; }
  .ferry-contact-meta-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .ferry-contact-meta-value { margin-top:4px; font-weight:700; color:#0f172a; }
  .ferry-contact-side { display:grid; gap:12px; align-content:start; }
  .ferry-contact-side-card { border:1px solid #bae6fd; border-radius:14px; background:#f0f9ff; padding:18px; }
  .ferry-contact-side-card h2 { margin:0 0 8px; font-size:18px; color:#0f172a; }
  .ferry-contact-side-card p { margin:0; color:#475569; line-height:1.55; }
  @media (max-width: 920px) {
    .ferry-contact-form { grid-template-columns:1fr; }
  }
</style>

<div class="flow-wrapper ferry-contact-shell">
  <h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
  <?= $this->element('flow_locked_notice') ?>
  <?= $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope')) ?>

  <div class="ferry-contact-grid">
    <section class="ferry-contact-panel">
      <h2 class="ferry-contact-title">Angiv dine detaljer</h2>
      <p class="ferry-contact-lead">
        Ferry-kompensationsplanchen er allerede vist loebende. Dette sidste frontend-trin opretter sagen,
        saa resten kan faerdiggoeres i backend/kontrolpanelet.
      </p>

      <div class="ferry-contact-meta">
        <?php if ($routeLabel !== ''): ?>
          <div class="ferry-contact-meta-card">
            <div class="ferry-contact-meta-label">Rute</div>
            <div class="ferry-contact-meta-value"><?= h($routeLabel) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($operatorName !== ''): ?>
          <div class="ferry-contact-meta-card">
            <div class="ferry-contact-meta-label">Operator</div>
            <div class="ferry-contact-meta-value"><?= h($operatorName) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($vesselName !== ''): ?>
          <div class="ferry-contact-meta-card">
            <div class="ferry-contact-meta-label">Faerge</div>
            <div class="ferry-contact-meta-value"><?= h($vesselName) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($bookingReference !== ''): ?>
          <div class="ferry-contact-meta-card">
            <div class="ferry-contact-meta-label">Bookingreference</div>
            <div class="ferry-contact-meta-value"><?= h($bookingReference) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <?= $this->Form->create(null) ?>
      <fieldset <?= $isPreview ? 'disabled' : '' ?>>
        <div class="ferry-contact-form">
          <div class="ferry-contact-field">
            <?= $this->Form->control('firstName', [
              'label' => 'Fornavn',
              'value' => (string)($form['firstName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="ferry-contact-help">Indtast dit fornavn, som det star pa dit ID.</div>
          </div>
          <div class="ferry-contact-field">
            <?= $this->Form->control('lastName', [
              'label' => 'Efternavn',
              'value' => (string)($form['lastName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="ferry-contact-help">Indtast alle efternavne, som de star pa dit ID.</div>
          </div>
          <div class="ferry-contact-field">
            <?= $this->Form->control('address_country', [
              'label' => 'Land',
              'value' => (string)($form['address_country'] ?? 'Danmark'),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="ferry-contact-field">
            <?= $this->Form->control('contact_phone', [
              'label' => 'Telefonnummer',
              'value' => (string)($form['contact_phone'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="ferry-contact-field">
            <?= $this->Form->control('contact_email', [
              'label' => 'Din e-mail',
              'type' => 'email',
              'value' => (string)($form['contact_email'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="ferry-contact-field">
            <?= $this->Form->control('booking_reference', [
              'label' => 'Bookingnummer (valgfrit)',
              'value' => $bookingReference,
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
        </div>

        <div class="ferry-contact-checks">
          <label class="ferry-contact-check">
            <?= $this->Form->checkbox('marketing_opt_in', [
              'value' => '1',
              'checked' => (string)($form['marketing_opt_in'] ?? '') === '1',
            ]) ?>
            <span>Jeg godkender at blive kontaktet i forbindelse med markedsfoering.</span>
          </label>
          <label class="ferry-contact-check">
            <?= $this->Form->checkbox('gdprConsent', [
              'value' => '1',
              'checked' => (string)($form['gdprConsent'] ?? '') === '1',
            ]) ?>
            <span>Jeg bekraefter at have laest og accepteret vilkaar og betingelser, og jeg accepterer, at sagen behandles.</span>
          </label>
        </div>

        <div class="ferry-contact-actions">
          <?= $this->Html->link('Tilbage', ['action' => (is_string($flowPrevAction ?? null) && $flowPrevAction !== '' ? $flowPrevAction : 'incident')], ['class' => 'button', 'style' => 'background:#eef2f7; color:#0f172a;']) ?>
          <?= $this->Form->button($ferryCaseCreatedAt === '' ? 'Opret ferry-sag i backend' : 'Aabn ferry-sag', ['class' => 'button button-primary']) ?>
        </div>
      </fieldset>
      <?= $this->Form->end() ?>
      <?= $this->element('flow_autosave', ['step' => 'ferry_contact_case']) ?>
    </section>

    <aside class="ferry-contact-side">
      <div class="ferry-contact-side-card">
        <h2>Naeste skridt</h2>
        <p>
          Backend-sagen kan nu bruges til dokumenter, operatoransvar, Art. 17/18/19, PMR, kvitteringer og manuel kontrol
          af AIS/ETA-evidence.
        </p>
      </div>
    </aside>
  </div>
</div>
