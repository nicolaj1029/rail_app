<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Start din sag</h1>
  <p>Upload din billet (foto/PDF) eller indsæt en JSON-udgave af rejsen. Vi spørger efter manglende detaljer på næste trin.</p>
  <?= $this->Form->create(null, ['type' => 'file']) ?>
    <fieldset>
      <legend>Billet</legend>
      <?= $this->Form->control('ticket', ['type' => 'file', 'label' => 'Billede/PDF']) ?>
      <?= $this->Form->control('country', ['label' => 'Land (hint hvis parsing mangler)', 'value' => 'FR']) ?>
      <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <div style="min-width:200px;">
          <?= $this->Form->control('ticket_amount', ['label' => 'Billetpris beløb', 'type' => 'number', 'step' => '0.01', 'placeholder' => '120.00']) ?>
        </div>
        <div style="min-width:120px;">
          <?= $this->Form->control('ticket_currency', ['label' => 'Valuta', 'type' => 'text', 'value' => 'EUR']) ?>
        </div>
      </div>
      <small style="color:#666;">Alternativt kan du udfylde et felt nedenfor i ét: "Billetpris (fx 120.00 EUR)"</small>
      <?= $this->Form->control('ticket_price', ['label' => 'Billetpris (fritekst)']) ?>
    </fieldset>
    <details style="margin:10px 0;">
      <summary>Udvikler-genvej: Indsæt Journey JSON</summary>
      <textarea name="journey" style="width:100%;height:140px;" placeholder='{"segments":[{"country":"FR"}],"ticketPrice":{"value":"120.00 EUR"}}'></textarea>
    </details>
    <?= $this->Form->button('Fortsæt') ?>
  <?= $this->Form->end() ?>

  <hr>
  <h3>Hurtig test</h3>
  <p>Autofyld en realistisk demo-sag (inkl. simuleret upload) for at teste hele flowet.</p>
  <?= $this->Form->create() ?>
    <input type="hidden" name="autofill" value="1">
    <?= $this->Form->button('Autofyld demo-sag') ?>
  <?= $this->Form->end() ?>
</div>
