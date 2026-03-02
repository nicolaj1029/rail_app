<?php
/** @var \App\View\AppView $this */
$form = $form ?? [];
$gdpr_ok = $gdpr_ok ?? false;
$isPreview = !empty($flowPreview);
?>

<h1>TRIN 12 - Samtykke & ekstra info</h1>
<?= $this->element('flow_locked_notice') ?>

<?= $this->Form->create(null) ?>
<fieldset <?= $isPreview ? 'disabled' : '' ?>>
    <legend>Samtykke</legend>
    <label>
        <?= $this->Form->checkbox('gdprConsent', ['checked' => $gdpr_ok]) ?>
        Jeg giver samtykke til behandling af mine oplysninger til brug for denne sag.
    </label>

    <legend>Yderligere oplysninger (valgfrit)</legend>
    <?= $this->Form->textarea('additionalInfo', ['rows' => 5]) ?>

    <div style="display:flex;gap:8px;align-items:center; margin-top:12px;">
        <?= $this->Html->link('Tilbage', ['action' => 'applicant'], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
        <?= $this->Form->button('Afslut', ['class' => 'button']) ?>
        <?= $this->Html->link('Til summary', ['action' => 'summary'], ['class' => 'button', 'style' => 'background:#f5f5f5; color:#333;']) ?>
    </div>
</fieldset>
<?= $this->Form->end() ?>
