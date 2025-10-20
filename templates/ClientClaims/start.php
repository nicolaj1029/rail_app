<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Start din sag</h1>
  <p>Udfyld felterne – vi beregner kompensation med det samme og udbetaler straks, hvorefter vi overtager sagen.</p>
  <?= $this->Form->create(null, ['url' => ['action' => 'submit'], 'type' => 'file']) ?>
    <fieldset>
      <legend>Dine oplysninger</legend>
      <?= $this->Form->control('name', ['label' => 'Navn', 'required' => true]) ?>
      <?= $this->Form->control('email', ['label' => 'Email', 'required' => true]) ?>
    </fieldset>
    <fieldset>
      <legend>Bilag</legend>
      <?= $this->Form->control('ticket_file', ['type' => 'file', 'label' => 'Billet (PDF/PNG/JPG)']) ?>
      <?= $this->Form->control('receipts_file', ['type' => 'file', 'label' => 'Kvitteringer (PDF/PNG/JPG)']) ?>
      <?= $this->Form->control('delay_confirmation_file', ['type' => 'file', 'label' => 'Bekræftelse på forsinkelse (Art. 20(4))']) ?>
    </fieldset>
    <fieldset>
      <legend>Rejsen</legend>
      <?= $this->Form->control('country', ['label' => 'Land']) ?>
      <?= $this->Form->control('operator', ['label' => 'Operatør']) ?>
      <?= $this->Form->control('product', ['label' => 'Produkt']) ?>
      <?= $this->Form->control('delay_min', ['label' => 'Forsinkelse (minutter)', 'type' => 'number', 'min' => 0]) ?>
      <?= $this->Form->control('ticket_price', ['label' => 'Billetpris', 'type' => 'number', 'step' => '0.01']) ?>
      <?= $this->Form->control('currency', ['label' => 'Valuta', 'value' => 'EUR']) ?>
    </fieldset>
    <fieldset>
      <legend>Checks</legend>
      <?= $this->Form->control('refund_already', ['label' => 'Refusion allerede udbetalt', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('known_delay_before_purchase', ['label' => 'Forsinkelsen var kendt før køb', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære forhold', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt', 'type' => 'checkbox']) ?>
    </fieldset>
    <fieldset>
      <legend>Overdragelse</legend>
      <p>Jeg accepterer, at I overtager sagen, og at jeg modtager min udbetaling nu mod et honorar (se beregning).</p>
      <?= $this->Form->control('assignment_accepted', ['label' => 'Jeg accepterer overdragelsen', 'type' => 'checkbox', 'required' => true]) ?>
    </fieldset>
    <?= $this->Form->button('Indsend & få tilbud nu') ?>
  <?= $this->Form->end() ?>
</div>
