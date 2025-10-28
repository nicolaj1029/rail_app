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
      <legend>TRIN – Gennemgående billet (Art. 12)</legend>

      <h3 class="mt8">Grundtype</h3>
      <?= $this->Form->control('through_ticket_disclosure', [
        'label' => 'Var du tydeligt informeret om, at din(e) billet(ter) var gennemgående eller ej?',
        'type' => 'radio',
        'options' => [
          'Gennemgående' => 'Gennemgående billet',
          'Særskilte' => 'Særskilte kontrakter',
          'Ved ikke' => 'Ved ikke'
        ],
        'default' => 'Ved ikke'
      ]) ?>

      <?= $this->Form->control('single_txn_operator', [
        'type' => 'radio',
        'label' => 'Købte du alle billetter i én transaktion hos operatøren?',
        'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
        'default' => 'Ved ikke'
      ]) ?>

      <?= $this->Form->control('single_txn_retailer', [
        'type' => 'radio',
        'label' => 'Køb samlet hos rejsebureau/billetudsteder?',
        'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
        'default' => 'Ved ikke'
      ]) ?>

      <?= $this->Form->control('separate_contract_notice', [
        'type' => 'radio',
        'label' => 'Var særskilte kontrakter udtrykkeligt angivet?',
        'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
        'default' => 'Ved ikke'
      ]) ?>

      <?= $this->Form->control('shared_pnr_scope', [
        'type' => 'radio',
        'label' => 'Samme ordrenummer/PNR for alle billetter? (AUTO)',
        'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
        'default' => 'Ved ikke'
      ]) ?>

      <div class="input">
        <label for="seller_type_choice">Hvem solgte rejsen? <span class="ml4 muted">Sælger (vælg én)</span></label>
        <?= $this->Form->control('seller_type_choice', [
          'type' => 'radio',
          'label' => false,
          'options' => [
            'operator' => 'Jernbanevirksomhed',
            'agency' => 'Rejsebureau/billetudsteder',
            'unknown' => 'Ved ikke'
          ],
          'default' => 'unknown'
        ]) ?>
      </div>

      <?php $debugUI = (bool)($this->getRequest()->getQuery('debug') ?? false); ?>
      <?php if (!$debugUI): ?>
        <div class="small muted mt8">AUTO-felter (7–13) er skjult i denne guide. Til fejlsøgning kan du bruge <code>?debug=1</code> for at vise dem.</div>
      <?php else: ?>
        <?= $this->Form->control('connection_time_realistic', [
          'type' => 'radio',
          'label' => 'Var skiftetider realistiske? (AUTO/DEBUG)',
          'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
          'default' => 'Ved ikke'
        ]) ?>

        <?= $this->Form->control('one_contract_schedule', [
          'type' => 'radio',
          'label' => 'Fremstod købet som én ansvarlig rejseplan? (AUTO/DEBUG)',
          'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
          'default' => 'Ved ikke'
        ]) ?>

        <?= $this->Form->control('contact_info_provided', [
          'type' => 'radio',
          'label' => 'Fik du oplyst kontakt ved aflysning/forsinkelse? (AUTO/DEBUG)',
          'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
          'default' => 'Ved ikke'
        ]) ?>

        <?= $this->Form->control('responsibility_explained', [
          'type' => 'radio',
          'label' => 'Var ansvar ved missed connection forklaret? (AUTO/DEBUG)',
          'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
          'default' => 'Ved ikke'
        ]) ?>

        <?= $this->Form->control('single_booking_reference', [
          'type' => 'radio',
          'label' => 'Én bookingreference for hele rejsen? (AUTO/DEBUG)',
          'options' => ['Ja'=>'Ja','Nej'=>'Nej','Ved ikke'=>'Ved ikke'],
          'default' => 'Ved ikke'
        ]) ?>
      <?php endif; ?>

  <p class="help">Kun besvarede spørgsmål medtages i PDF pkt. 6. Felter markeret (AUTO) udfyldes automatisk og er som udgangspunkt skjult.</p>
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
    <!-- Hidden AUTO/evaluator bridge fields -->
    <?= $this->Form->control('multi_operator_trip', ['type' => 'hidden', 'value' => 'unknown']) ?>
    <?= $this->Form->control('seller_type_operator', ['type' => 'hidden', 'value' => 'unknown']) ?>
    <?= $this->Form->control('seller_type_agency', ['type' => 'hidden', 'value' => 'unknown']) ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var radios = document.querySelectorAll('input[name="seller_type_choice"]');
      var op = document.querySelector('input[name="seller_type_operator"]');
      var ag = document.querySelector('input[name="seller_type_agency"]');
      function apply(val) {
        if (!op || !ag) return;
        if (val === 'operator') {
          op.value = 'Ja';
          ag.value = 'Nej';
        } else if (val === 'agency') {
          op.value = 'Nej';
          ag.value = 'Ja';
        } else {
          op.value = 'unknown';
          ag.value = 'unknown';
        }
      }
      radios.forEach(function(r){
        r.addEventListener('change', function(e){ apply(e.target.value); });
        if (r.checked) apply(r.value);
      });
    });
    </script>
    <fieldset>
      <legend>Valg (Art. 18)</legend>
      <?= $this->Form->control('wants_refund', ['label' => 'Vil du have refusion af billet?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_same_soonest', ['label' => 'Rerouting hurtigst muligt (samme forhold)?', 'type' => 'checkbox']) ?>
      <?= $this->Form->control('wants_reroute_later_choice', ['label' => 'Rerouting senere efter eget valg?', 'type' => 'checkbox']) ?>
    </fieldset>
    <?= $this->Form->button('Fortsæt til udgifter') ?>
  <?= $this->Form->end() ?>
</div>
