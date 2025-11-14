<?php /** @var \App\Model\Entity\RailCase $case */ ?>
<h2>Rediger sag <?= h((string)$case->ref) ?></h2>
<?= $this->Form->create($case) ?>
<fieldset>
  <legend>Grunddata</legend>
  <?= $this->Form->control('status', ['options' => ['open'=>'Åben','closed'=>'Lukket','pending'=>'Afventer'], 'label'=>'Status']) ?>
  <?= $this->Form->control('assigned_to', ['label'=>'Ansvarlig behandler']) ?>
  <?= $this->Form->control('due_at', ['type'=>'datetime', 'label'=>'Tidsfrist']) ?>
</fieldset>
<fieldset>
  <legend>Forsinkelse & kompensation</legend>
  <?= $this->Form->control('delay_min_eu', ['label'=>'Forsinkelse (min)']) ?>
  <?= $this->Form->control('remedy_choice', ['label'=>'Art.18 valg']) ?>
  <?= $this->Form->control('art20_expenses_total', ['label'=>'Art.20 udgifter']) ?>
  <?= $this->Form->control('comp_band', ['label'=>'Band (25/50)']) ?>
  <?= $this->Form->control('comp_amount', ['label'=>'Kompensationsbeløb']) ?>
  <?= $this->Form->control('currency', ['label'=>'Valuta']) ?>
</fieldset>
<fieldset>
  <legend>Flags</legend>
  <?= $this->Form->control('eu_only', ['type'=>'checkbox','label'=>'EU-only']) ?>
  <?= $this->Form->control('extraordinary', ['type'=>'checkbox','label'=>'Force Majeure / ekstraordinær']) ?>
  <?= $this->Form->control('duplicate_flag', ['type'=>'checkbox','label'=>'Mulig duplikat']) ?>
</fieldset>
<?= $this->Form->button('Gem') ?>
<?= $this->Form->end() ?>
<p><a href="<?= $this->Url->build(['action'=>'view',$case->id]) ?>">Tilbage</a></p>
