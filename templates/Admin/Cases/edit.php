<?php /** @var \App\Model\Entity\RailCase $case */ ?>
<?php
$remedyOptions = [
    'refund_return' => 'Tilbagebetaling',
    'reroute_soonest' => 'Videre rejse hurtigst muligt',
    'reroute_later' => 'Videre rejse senere (efter eget valg)',
];
$snapshot = json_decode((string)($case->flow_snapshot ?? ''), true);
$form = is_array($snapshot) ? (array)($snapshot['form'] ?? []) : [];
$transportMode = strtolower(trim((string)($form['transport_mode'] ?? ($form['gating_mode'] ?? ''))));
$articleModeLabel = match ($transportMode) {
    'air' => 'Air: Art. 7 / 8 / 9',
    'rail' => 'Rail: Art. 18 / 19 / 20',
    'bus' => 'Bus: transportspecifikt regelsaet',
    'ferry' => 'Ferry: Art. 17 / 18 / 19 + PMR',
    default => 'Transportuafhaengigt admin-resume',
};
$fieldLabels = match ($transportMode) {
    'ferry' => [
        'remedy' => 'Art. 18 refund/ombooking / PMR Art. 8(3)',
        'expenses' => 'Art. 17 assistanceudgifter',
        'compBand' => 'Art. 19 band (25/50)',
        'compAmount' => 'Art. 19 kompensationsbeloeb',
    ],
    default => [
        'remedy' => 'Afhjaelpning',
        'expenses' => 'Assistanceudgifter',
        'compBand' => 'Band (25/50)',
        'compAmount' => 'Kompensationsbeloeb',
    ],
};
?>
<h2>Rediger sag <?= h((string)$case->ref) ?></h2>
<?= $this->Form->create($case) ?>
<fieldset>
  <legend>Grunddata</legend>
  <?= $this->Form->control('status', ['options' => ['open'=>'Åben','closed'=>'Lukket','pending'=>'Afventer'], 'label'=>'Status']) ?>
  <?= $this->Form->control('assigned_to', ['label'=>'Ansvarlig behandler']) ?>
  <?= $this->Form->control('due_at', ['type'=>'datetime', 'label'=>'Tidsfrist']) ?>
</fieldset>
<fieldset>
  <legend>Rettigheder og beloeb</legend>
  <p><small><?= h($articleModeLabel) ?></small></p>
  <?= $this->Form->control('delay_min_eu', ['label'=>'Forsinkelse (min)']) ?>
  <?= $this->Form->control('remedy_choice', ['label'=>$fieldLabels['remedy'], 'options' => $remedyOptions, 'empty' => '-']) ?>
  <?= $this->Form->control('art20_expenses_total', ['label'=>$fieldLabels['expenses']]) ?>
  <?= $this->Form->control('comp_band', ['label'=>$fieldLabels['compBand']]) ?>
  <?= $this->Form->control('comp_amount', ['label'=>$fieldLabels['compAmount']]) ?>
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
