<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$railCaseCreatedAt = (string)($meta['rail_case_created_at'] ?? '');
$isPreview = !empty($flowPreview);
$bookingReference = trim((string)($form['booking_reference'] ?? ($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))));
$operatorName = trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? '')));
$trainNumber = trim((string)($selectedDeparture['train_number'] ?? ($form['train_no'] ?? '')));
$routeLabel = trim((string)($form['dep_station'] ?? '')) . ' -> ' . trim((string)($form['arr_station'] ?? ''));
$routeLabel = trim($routeLabel, ' ->');
$problemAnchor = (array)($meta['rail_problem_anchor'] ?? []);
$problemLabel = trim((string)($problemAnchor['label'] ?? ''));
$currentLocation = (array)($meta['rail_current_location_anchor'] ?? []);
$currentStation = trim((string)($currentLocation['station_name'] ?? ''));
$stepHeading = 'TRIN 10';
$stepTitle = 'Kontakt & opret rail-sag';
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
  .rail-contact-shell,
  .flow-wrapper.rail-contact-shell { max-width: 1080px; margin: 0 auto; }
  .rail-contact-grid { display:grid; gap:20px; grid-template-columns:minmax(0, 1fr); align-items:start; }
  .rail-contact-panel { border:1px solid #dbe4ee; border-radius:14px; background:#fff; padding:24px; }
  .rail-contact-title { margin:0 0 8px; font-size:40px; line-height:1.05; letter-spacing:-0.03em; color:#0f172a; }
  .rail-contact-lead { margin:0; font-size:16px; line-height:1.55; color:#475569; }
  .rail-contact-form { margin-top:22px; display:grid; gap:16px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
  .rail-contact-field { display:grid; gap:8px; }
  .rail-contact-field label { font-weight:700; color:#0f172a; }
  .rail-contact-help { font-size:12px; color:#64748b; margin-top:-2px; }
  .rail-contact-checks { display:grid; gap:14px; margin-top:10px; }
  .rail-contact-check { display:flex; gap:10px; align-items:flex-start; color:#1e293b; }
  .rail-contact-check input { width:auto; margin-top:3px; }
  .rail-contact-actions { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:22px; }
  .rail-contact-meta { margin-top:16px; display:grid; gap:10px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); }
  .rail-contact-meta-card { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:12px 14px; }
  .rail-contact-meta-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .rail-contact-meta-value { margin-top:4px; font-weight:700; color:#0f172a; }
  .rail-contact-side { display:grid; gap:12px; align-content:start; }
  .rail-contact-side-card { border:1px solid #c7d2fe; border-radius:14px; background:#f8faff; padding:18px; }
  .rail-contact-side-card h2 { margin:0 0 8px; font-size:18px; color:#0f172a; }
  .rail-contact-side-card p { margin:0; color:#475569; line-height:1.55; }
  @media (max-width: 920px) {
    .rail-contact-form { grid-template-columns:1fr; }
  }
</style>

<div class="flow-wrapper rail-contact-shell">
  <h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
  <?= $this->element('flow_locked_notice') ?>
  <?= $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey')) ?>

  <div class="rail-contact-grid">
    <section class="rail-contact-panel">
      <h2 class="rail-contact-title">Angiv dine detaljer</h2>
      <p class="rail-contact-lead">
        Rail-resultatet er allerede vist loebende i flowet. Dette sidste frontend-trin opretter sagen,
        saa dokumenter, Art. 12-review, udgifter og manuel vurdering kan faerdiggoeres i backend.
      </p>

      <div class="rail-contact-meta">
        <?php if ($routeLabel !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Rute</div>
            <div class="rail-contact-meta-value"><?= h($routeLabel) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($operatorName !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Operator</div>
            <div class="rail-contact-meta-value"><?= h($operatorName) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($trainNumber !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Foerste tog</div>
            <div class="rail-contact-meta-value"><?= h($trainNumber) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($bookingReference !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Bookingreference</div>
            <div class="rail-contact-meta-value"><?= h($bookingReference) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($problemLabel !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Problemsted</div>
            <div class="rail-contact-meta-value"><?= h($problemLabel) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($currentStation !== ''): ?>
          <div class="rail-contact-meta-card">
            <div class="rail-contact-meta-label">Aktuel station</div>
            <div class="rail-contact-meta-value"><?= h($currentStation) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <?= $this->Form->create(null) ?>
      <fieldset <?= $isPreview ? 'disabled' : '' ?>>
        <div class="rail-contact-form">
          <div class="rail-contact-field">
            <?= $this->Form->control('firstName', [
              'label' => 'Fornavn',
              'value' => (string)($form['firstName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="rail-contact-help">Indtast dit fornavn, som det star pa dit ID.</div>
          </div>
          <div class="rail-contact-field">
            <?= $this->Form->control('lastName', [
              'label' => 'Efternavn',
              'value' => (string)($form['lastName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="rail-contact-help">Indtast alle efternavne, som de star pa dit ID.</div>
          </div>
          <div class="rail-contact-field">
            <?= $this->Form->control('address_country', [
              'label' => 'Land',
              'value' => (string)($form['address_country'] ?? 'Danmark'),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="rail-contact-field">
            <?= $this->Form->control('contact_phone', [
              'label' => 'Telefonnummer',
              'value' => (string)($form['contact_phone'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="rail-contact-field">
            <?= $this->Form->control('contact_email', [
              'label' => 'Din e-mail',
              'type' => 'email',
              'value' => (string)($form['contact_email'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="rail-contact-field">
            <?= $this->Form->control('booking_reference', [
              'label' => 'Bookingnummer (valgfrit)',
              'value' => $bookingReference,
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
        </div>

        <div class="rail-contact-checks">
          <label class="rail-contact-check">
            <?= $this->Form->checkbox('marketing_opt_in', [
              'value' => '1',
              'checked' => (string)($form['marketing_opt_in'] ?? '') === '1',
            ]) ?>
            <span>Jeg godkender at blive kontaktet i forbindelse med markedsfoering.</span>
          </label>
          <label class="rail-contact-check">
            <?= $this->Form->checkbox('gdprConsent', [
              'value' => '1',
              'checked' => (string)($form['gdprConsent'] ?? '') === '1',
            ]) ?>
            <span>Jeg bekraefter at have laest og accepteret vilkar og betingelser, og jeg accepterer, at sagen behandles.</span>
          </label>
        </div>

        <div class="rail-contact-actions">
          <?= $this->Html->link('Tilbage', ['action' => (is_string($flowPrevAction ?? null) && $flowPrevAction !== '' ? $flowPrevAction : 'assistance')], ['class' => 'button', 'style' => 'background:#eef2f7; color:#0f172a;']) ?>
          <?= $this->Form->button($railCaseCreatedAt === '' ? 'Opret rail-sag i backend' : 'Aabn rail-sag', ['class' => 'button button-primary']) ?>
        </div>
      </fieldset>
      <?= $this->Form->end() ?>
      <?= $this->element('flow_autosave', ['step' => 'rail_contact_case']) ?>
    </section>

    <aside class="rail-contact-side">
      <div class="rail-contact-side-card">
        <h2>Naeste skridt</h2>
        <p>
          Backend-sagen bruges nu til dokumenter, Art. 12-kontraktreview, rail assistance, remedies,
          kvitteringer og den endelige manuelle vurdering af ansvar og ekstraudgifter.
        </p>
      </div>
    </aside>
  </div>
</div>
