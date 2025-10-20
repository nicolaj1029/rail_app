<?php
/** @var \App\View\AppView $this */
/** @var array $state */
?>
<div class="content">
  <h1>Supplerende spørgsmål</h1>
  <p>Vi mangler lidt data for at vurdere Art. 12/9/18/19/20. Udfyld venligst nedenfor.</p>
  <?= $this->Form->create() ?>
    <fieldset>
      <legend>Land</legend>
      <?= $this->Form->control('country', [
        'label' => 'Primært land (for matrix)',
        'type' => 'text',
        'placeholder' => 'FR/DE/SE/SK/PL …'
      ]) ?>
    </fieldset>
    <fieldset>
      <legend>Tjenestetype (scope)</legend>
      <?= $this->Form->control('service_scope', [
        'label' => 'Vælg det der passer bedst',
        'options' => [
          'regional' => 'Regional/by/forstad',
          'long_domestic' => 'Langdistance (indenrigs)',
          'intl_inside_eu' => 'International inden for EU',
          'intl_beyond_eu' => 'International ud over EU',
        ],
        'empty' => '— vælg —'
      ]) ?>
    </fieldset>
    <fieldset>
      <legend>Art. 12 (gennemgående billet)</legend>
      <?= $this->Form->control('through_ticket_disclosure', ['label' => 'Er det en gennemgående billet?', 'options' => ['Gennemgående' => 'Gennemgående', 'Særskilte' => 'Særskilte', 'unknown' => 'Ved ikke'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('separate_contract_notice', ['label' => 'Oplyst separate kontrakter?', 'options' => ['Ja'=>'Ja','Nej'=>'Nej','unknown'=>'Ved ikke'],'default'=>'unknown']) ?>
    </fieldset>
    <fieldset>
      <legend>Art. 9 (information)</legend>
      <?= $this->Form->control('info_before_purchase', ['label' => 'Information før køb', 'options' => ['Ja'=>'Ja','Nej'=>'Nej','Delvist'=>'Delvist','unknown'=>'Ved ikke'], 'default'=>'unknown']) ?>
      <?= $this->Form->control('info_on_rights', ['label' => 'Oplysning om rettigheder', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('info_during_disruption', ['label' => 'Info under afbrydelse', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
      <?= $this->Form->control('language_accessible', ['label' => 'Tilgængeligt sprog', 'options' => ['Ja','Nej','Delvist','unknown'], 'default' => 'unknown']) ?>
    </fieldset>
    <fieldset>
      <legend>Forsinkelse / Årsager</legend>
      <?= $this->Form->control('delay_minutes_final', ['label' => 'Forsinkelse ved destination (min)', 'type' => 'number', 'min' => 0, 'value' => 0]) ?>
      <?= $this->Form->control('notified_before_purchase', ['label' => 'Vidste du om forsinkelsen før køb?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære omstændigheder?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt?', 'type' => 'checkbox']) ?>
    </fieldset>
    <fieldset>
      <legend>Valg (Art. 18)</legend>
      <?= $this->Form->control('wants_refund', ['label' => 'Vil du have refusion af billet?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_same_soonest', ['label' => 'Rerouting hurtigst muligt (samme forhold)?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_later_choice', ['label' => 'Rerouting senere efter eget valg?', 'type' => 'checkbox']) ?>
    </fieldset>
    <?= $this->Form->button('Fortsæt til udgifter') ?>
  <?= $this->Form->end() ?>
</div>
