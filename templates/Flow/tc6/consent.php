<?php
/**
 * TC6 rail consent & extra info step.
 *
 * Visual layer only. Uses existing consent action + fields.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$gdpr_ok = $gdpr_ok ?? false;
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$translations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Start & Rejsestatus' => 'Depart et statut du voyage',
        'Start &amp; Rejsestatus' => 'Depart et statut du voyage',
        'Billet / Ticketless + Grunddata' => 'Billet / sans billet + donnees de base',
        'Haendelseskede + gating' => 'Chaine d incident + filtrage',
        'Refusion / Omlaegning (Art. 18)' => 'Remboursement / reacheminement (art. 18)',
        'Beregning & Resultat (Art. 19)' => 'Calcul et resultat (art. 19)',
        'Ansoeger & Udbetaling' => 'Demandeur et paiement',
        'Ansoeger &amp; Udbetaling' => 'Demandeur et paiement',
        'Samtykke & Ekstra info' => 'Consentement et informations complementaires',
        'Samtykke &amp; Ekstra info' => 'Consentement et informations complementaires',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Voyage termine',
        'Samtykke' => 'Consentement',
        'Ekstra info' => 'Informations complementaires',
        'Givet' => 'Donne',
        'Afventer' => 'En attente',
        'Registreret' => 'Enregistre',
        'Ingen endnu' => 'Aucune pour le moment',
        'Status' => 'Statut',
        'Klar til afslutning' => 'Pret pour la finalisation',
        'Flow' => 'Parcours',
        'Samtykke & ekstra info' => 'Consentement et informations complementaires',
        'Yderligere oplysninger (valgfrit)' => 'Informations complementaires (optionnel)',
        'Jeg giver samtykke til behandling af mine oplysninger til brug for denne sag.' => 'Je consens au traitement de mes donnees pour ce dossier.',
        'Skriv kun det, der hjaelper backend med at forstaa rail-sagen hurtigere.' => 'N ecrivez que ce qui aide le back-office a comprendre plus vite le dossier rail.',
    ],
];
$t = static function ($text) use ($uiLanguage, $translations) {
    if (!is_string($text)) {
        return $text;
    }

    if (isset($translations[$uiLanguage][$text])) {
        return $translations[$uiLanguage][$text];
    }

    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $translations[$uiLanguage][$decoded] ?? $text;
};

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$travelStateLabel = match ($travelState) {
    'ongoing' => $t('Igangvaerende rejse'),
    'before_start' => $t('Foer afgang'),
    default => $t('Afsluttet rejse'),
};

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Ticketless + Grunddata',
    3 => 'Haendelseskede + gating',
    4 => 'Refusion / Omlaegning (Art. 18)',
    5 => 'Beregning & Resultat (Art. 19)',
    6 => 'Ansoeger & Udbetaling',
    7 => 'Samtykke & Ekstra info',
];
$currentStep = (int)($currentStep ?? 7);
$doneSteps = $doneSteps ?? [];
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ' trin'));
if ($uiLanguage === 'fr') {
    $progressLabel = $currentStep . ' / ' . count($steps) . ' etapes';
}

$additionalInfo = trim((string)($form['additionalInfo'] ?? ''));
if ($uiLanguage === 'fr' && $additionalInfo === 'Passageren oensker at sagen oprettes med fokus paa Art. 19 samt dokumenterede assistanceudgifter.') {
    $additionalInfo = 'Le passager souhaite que le dossier soit cree avec un focus sur l art. 19 et les frais d assistance documentes.';
}
$steps = array_map($t, $steps);
$summaryRows = [
    [$t('Samtykke'), $gdpr_ok ? $t('Givet') : $t('Afventer'), $gdpr_ok ? 'green' : 'gray'],
    [$t('Ekstra info'), $additionalInfo !== '' ? $t('Registreret') : $t('Ingen endnu'), $additionalInfo !== '' ? 'blue' : 'gray'],
    [$t('Status'), $t('Klar til afslutning'), 'blue'],
];

$stats = $stats ?? [
    [$t('Flow'), $travelStateLabel, null],
    ['Transport', 'RAIL', null],
];
$context = $travelStateLabel;
$brandName = 'TrainClaim';
$brandMark = 'TC';

$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $this->Url->build(['action' => 'applicant', '?' => $flowQuery]);

ob_start();
?>
<?= $this->Form->create(null, [
    'url' => ['action' => 'consent', '?' => $flowQuery],
    'id' => 'tc6-consent-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>

<div class="tc6-consent">
  <div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
  <h1 class="tc6-h1"><?= h($t('Samtykke & ekstra info')) ?></h1>
  <?php /* helper copy removed */ ?>

  <?= $this->element('flow_locked_notice') ?>

  <div class="tc6-card">
    <div class="tc6-section-head">
      <div class="tc6-section-label"><?= h($t('Samtykke')) ?></div>
      <?php /* helper copy removed */ ?>
    </div>
    <label class="tc6-inline-check">
      <?= $this->Form->checkbox('gdprConsent', ['value' => '1', 'checked' => (bool)$gdpr_ok]) ?>
      <span><?= h($t('Jeg giver samtykke til behandling af mine oplysninger til brug for denne sag.')) ?></span>
    </label>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-head">
      <div class="tc6-section-label"><?= h($t('Yderligere oplysninger (valgfrit)')) ?></div>
      <?php /* helper copy removed */ ?>
    </div>
    <?= $this->Form->textarea('additionalInfo', [
        'rows' => 6,
        'value' => $additionalInfo,
        'placeholder' => $t('Skriv kun det, der hjaelper backend med at forstaa rail-sagen hurtigere.'),
        'templates' => ['inputContainer' => '{{content}}'],
    ]) ?>
  </div>

<?= $this->element('tc6/action_bar', [
    'backUrl' => $backUrl,
    'backLabel' => $t('Tilbage'),
    'nextLabel' => $t('Naeste trin'),
    'nextVariant' => 'navy',
    'submitName' => '_save',
]) ?>
</div>

<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'consent', 'formSelector' => '#tc6-consent-form']) ?>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($translations[$uiLanguage])) {
    $content = strtr($content, $translations[$uiLanguage]);
}

ob_start();
echo $this->element('tc6/live_estimate_router', [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
]);
$liveEstimateHtml = trim((string)ob_get_clean());

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
