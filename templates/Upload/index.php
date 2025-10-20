<?php
/** @var \App\View\AppView $this */
?>
<div class="content">
  <h1>Upload og analyse</h1>
  <p>Tag et foto, upload et screenshot eller PKPass. Vi kører hele evalueringen (Art. 9/12/18/19/20) og viser et samlet resultat samt klargør PDF-udfyldning.</p>
  <?= $this->Form->create(null, ['url' => ['action' => 'analyze'], 'type' => 'file']) ?>
    <fieldset>
      <legend>Billet</legend>
      <?= $this->Form->control('ticket', ['type' => 'file', 'label' => 'Billede/PDF/PKPass']) ?>
      <?= $this->Form->control('country', ['label' => 'Land (hint, hvis parsing ikke er klar endnu)', 'value' => 'FR']) ?>
      <?= $this->Form->control('manual_delay_minutes', ['type' => 'number', 'label' => 'Manuel forsinkelse (minutter) – overstyring', 'min' => 0, 'step' => 1, 'placeholder' => 'fx 75']) ?>
    </fieldset>
    <details style="margin:10px 0;">
      <summary>Udvikler-genvej: Indsæt Journey JSON (overrider parsing)</summary>
      <textarea name="journey" style="width:100%;height:140px;" placeholder='{"segments":[{"country":"FR"}],"is_international_inside_eu":false,"is_international_beyond_eu":false,"is_long_domestic":false}'></textarea>
      <p style="font-size:12px;color:#666;">Hvis angivet, bruges denne direkte til Art. 12/Exemptions.</p>
    </details>
  <?= $this->Form->button('Analyser og beregn (final)') ?>
  <?= $this->Form->end() ?>

  <p style="margin-top:14px;">Se også: <?= $this->Html->link('Flow chart (v4)', ['/project/flowchart']) ?> og <?= $this->Html->link('Forklaring', ['/project/forklaring']) ?>. 
  Du kan senere generere Kommissionens officielle PDF fra resultat-siden.</p>

  <details style="margin-top:16px;">
    <summary><strong>Tests og værktøjer</strong></summary>
    <ul>
      <li><?= $this->Html->link('Scenarier – vis & kør (med evaluering)', '/project/links') ?></li>
      <li><?= $this->Html->link('Mock tickets (mappeanalyse)', '/api/demo/mock-tickets') ?></li>
  <li><?= $this->Html->link('Generér realistiske mock-billetter (PDF/PNG/TXT)', '/api/demo/generate-mocks') ?></li>
      <li><?= $this->Html->link('Pipeline (end-to-end JSON)', '/api/pipeline/run') ?></li>
      <li><?= $this->Html->link('PDF-generering (demo)', '/reimbursement') ?> · <?= $this->Html->link('Officiel PDF (formular)', '/reimbursement/official') ?></li>
      <li><?= $this->Html->link('Art. 12 – separat test', '/project/flowchart') ?> (for isoleret verifikation)</li>
    </ul>
  </details>
</div>
