<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$airRights = (array)($airRights ?? []);
$airScope = (array)($airScope ?? []);
$airContract = (array)($airContract ?? []);
$selectedFlight = (array)($meta['air_selected_flight'] ?? []);
$airCaseCreatedAt = (string)($meta['air_case_created_at'] ?? '');
$isPreview = !empty($flowPreview);
$bookingReference = trim((string)($form['booking_reference'] ?? ($form['ticket_no'] ?? ($selectedFlight['flight_number'] ?? ($meta['_auto']['ticket_no']['value'] ?? '')))));
$stepHeading = 'TRIN 10';
$stepTitle = 'Kontakt & opret sag';
if (!empty($flowSteps) && is_array($flowSteps)) {
  foreach ($flowSteps as $flowStep) {
    if ((string)($flowStep['action'] ?? '') !== 'compensation') {
      continue;
    }
    $stepNum = $flowStep['ui_num'] ?? $flowStep['num'] ?? 10;
    $stepHeading = 'TRIN ' . (string)$stepNum;
    $stepTitle = (string)($flowStep['title'] ?? 'Kontakt & opret sag');
    break;
  }
}
?>

<style>
  .air-contact-shell,
  .flow-wrapper.air-contact-shell { max-width: 1080px; margin: 0 auto; }
  .air-contact-grid { display:grid; gap:20px; grid-template-columns:minmax(0, 1fr); align-items:start; }
  .air-contact-panel { border:1px solid #dbe4ee; border-radius:14px; background:#fff; padding:24px; }
  .air-contact-title { margin:0 0 8px; font-size:40px; line-height:1.05; letter-spacing:-0.03em; color:#0f172a; }
  .air-contact-lead { margin:0; font-size:16px; line-height:1.55; color:#475569; }
  .air-contact-form { margin-top:22px; display:grid; gap:16px; grid-template-columns:repeat(2, minmax(0, 1fr)); }
  .air-contact-field { display:grid; gap:8px; }
  .air-contact-field.full { grid-column:1 / -1; }
  .air-contact-field label { font-weight:700; color:#0f172a; }
  .air-contact-help { font-size:12px; color:#64748b; margin-top:-2px; }
  .air-contact-checks { display:grid; gap:14px; margin-top:10px; }
  .air-contact-check { display:flex; gap:10px; align-items:flex-start; color:#1e293b; }
  .air-contact-check input { width:auto; margin-top:3px; }
  .air-contact-actions { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:22px; }
  .air-contact-meta { margin-top:16px; display:grid; gap:10px; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); }
  .air-contact-meta-card { border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; padding:12px 14px; }
  .air-contact-meta-label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#64748b; }
  .air-contact-meta-value { margin-top:4px; font-weight:700; color:#0f172a; }
  .air-contact-side { display:grid; gap:12px; align-content:start; }
  .air-contact-side-card { border:1px solid #dbe4ee; border-radius:14px; background:#f8fbff; padding:18px; }
  .air-contact-side-card h2 { margin:0 0 8px; font-size:18px; color:#0f172a; }
  .air-contact-side-card p { margin:0; color:#475569; line-height:1.55; }
  @media (max-width: 920px) {
    .air-contact-form { grid-template-columns:1fr; }
  }
</style>

<div class="flow-wrapper air-contact-shell">
  <h1><?= h($stepHeading) ?> - <?= h($stepTitle) ?></h1>
  <?= $this->element('flow_locked_notice') ?>
  <?= $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract')) ?>

  <div class="air-contact-grid">
    <section class="air-contact-panel">
      <h2 class="air-contact-title">Angiv venligst dine detaljer</h2>
      <p class="air-contact-lead">
        Kompensationsniveauet er allerede vist i haendelsestrinnet. Brug dette sidste frontflow-trin til at registrere
        kontaktoplysninger og oprette sagen, sa resten kan faerdiggoeres i kontrolpanelet bagefter.
      </p>

      <div class="air-contact-meta">
        <?php if (!empty($selectedFlight['flight_number'])): ?>
          <div class="air-contact-meta-card">
            <div class="air-contact-meta-label">Flyvning</div>
            <div class="air-contact-meta-value"><?= h((string)$selectedFlight['flight_number']) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($selectedFlight['carrier_name'])): ?>
          <div class="air-contact-meta-card">
            <div class="air-contact-meta-label">Carrier</div>
            <div class="air-contact-meta-value"><?= h((string)$selectedFlight['carrier_name']) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($bookingReference !== ''): ?>
          <div class="air-contact-meta-card">
            <div class="air-contact-meta-label">Bookingreference</div>
            <div class="air-contact-meta-value"><?= h($bookingReference) ?></div>
          </div>
        <?php endif; ?>
      </div>

      <?= $this->Form->create(null) ?>
      <fieldset <?= $isPreview ? 'disabled' : '' ?>>
        <div class="air-contact-form">
          <div class="air-contact-field">
            <?= $this->Form->control('firstName', [
              'label' => 'Fornavn',
              'value' => (string)($form['firstName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="air-contact-help">Indtast venligst dit fornavn, som det star pa dit ID.</div>
          </div>
          <div class="air-contact-field">
            <?= $this->Form->control('lastName', [
              'label' => 'Efternavn',
              'value' => (string)($form['lastName'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
            <div class="air-contact-help">Indtast venligst alle efternavne, som de star pa dit ID.</div>
          </div>
          <div class="air-contact-field">
            <?= $this->Form->control('address_country', [
              'label' => 'Land',
              'value' => (string)($form['address_country'] ?? 'Danmark'),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="air-contact-field">
            <?= $this->Form->control('contact_phone', [
              'label' => 'Telefonnummer',
              'value' => (string)($form['contact_phone'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="air-contact-field">
            <?= $this->Form->control('contact_email', [
              'label' => 'Din e-mail',
              'type' => 'email',
              'value' => (string)($form['contact_email'] ?? ''),
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
          <div class="air-contact-field">
            <?= $this->Form->control('booking_reference', [
              'label' => 'Bookingnummer (valgfrit)',
              'value' => $bookingReference,
              'templates' => ['inputContainer' => '{{content}}'],
            ]) ?>
          </div>
        </div>

        <div class="air-contact-checks">
          <label class="air-contact-check">
            <?= $this->Form->checkbox('marketing_opt_in', [
              'value' => '1',
              'checked' => (string)($form['marketing_opt_in'] ?? '') === '1',
            ]) ?>
            <span>Jeg godkender at blive kontaktet i forbindelse med markedsforing.</span>
          </label>
          <label class="air-contact-check">
            <?= $this->Form->checkbox('gdprConsent', [
              'value' => '1',
              'checked' => (string)($form['gdprConsent'] ?? '') === '1',
            ]) ?>
            <span>Jeg bekraefter at have laest og accepteret vilkar og betingelser, og jeg accepterer, at Flypenge behandler min sag.</span>
          </label>
        </div>

        <div class="air-contact-actions">
          <?= $this->Html->link('Tilbage', ['action' => (is_string($flowPrevAction ?? null) && $flowPrevAction !== '' ? $flowPrevAction : 'incident')], ['class' => 'button', 'style' => 'background:#eef2f7; color:#0f172a;']) ?>
          <?= $this->Form->button($airCaseCreatedAt === '' ? 'Opret sag' : 'Aabn sag', ['class' => 'button button-primary']) ?>
        </div>
      </fieldset>
      <?= $this->Form->end() ?>
      <?= $this->element('flow_autosave', ['step' => 'air_contact_case']) ?>
    </section>

    <aside class="air-contact-side">
      <div class="air-contact-side-card">
        <h2>Naeste skridt</h2>
        <p>
          Naar sagen er oprettet, fortsatter du i kontrolpanelet med refusion/omlaegning, dokumenter,
          assistance, PMR, kvitteringer og eventuelle ekstraudgifter.
        </p>
      </div>
    </aside>
  </div>
</div>
