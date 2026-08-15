<?php
/**
 * TC6 ferry post-incident choices hub.
 *
 * Mirrors the air hub pattern, but keeps ferry-specific gates for
 * Art. 18 remedies and Art. 17 assistance.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$incident = $incident ?? [];
$ferryPostIncidentChoices = is_array($ferryPostIncidentChoices ?? null) ? array_values($ferryPostIncidentChoices) : [];
$ferryPostIncidentAssistanceTypes = is_array($ferryPostIncidentAssistanceTypes ?? null) ? array_values($ferryPostIncidentAssistanceTypes) : [];
$ferryPostIncidentChoice = trim((string)($ferryPostIncidentChoice ?? ''));
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$translations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Billet, grunddata' => 'Billet, donnees de base',
        'Vaelg afgang' => 'Choix du depart',
        'Haendelse' => 'Incident',
        'Valg efter haendelsen' => 'Choix apres l incident',
        'Refusion, ombooking' => 'Remboursement, reacheminement',
        'Assistance' => 'Assistance',
        'Kontakt, opret sag' => 'Contact, creation du dossier',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Voyage termine',
        'Hvad vil du goere nu?' => 'Que voulez-vous faire maintenant ?',
        'Hvad blev relevant efter haendelsen?' => 'Qu est-ce qui est devenu pertinent apres l incident ?',
        'Vaelg kun de spor, der faktisk er relevante efter ferry-haendelsen. Kun de valgte spor bliver aabnet i de naeste trin.' => 'Choisissez uniquement les parcours qui sont reellement pertinents apres l incident ferry. Seuls les parcours choisis seront ouverts dans les etapes suivantes.',
        'Transportoerens loesning' => 'Solution du transporteur',
        'Hvilke muligheder tilboed transportoeren dig?' => 'Quelles possibilites le transporteur vous a-t-il proposees ?',
        'Jeg fik valget mellem tilbagebetaling og ombooking' => 'J ai eu le choix entre remboursement et reacheminement',
        'Begge Art. 18-spor kan blive relevante bagefter.' => 'Les deux parcours de l art. 18 peuvent devenir pertinents ensuite.',
        'Kun tilbagebetaling blev tilbudt' => 'Seul le remboursement a ete propose',
        'Transportoeren tilboed kun refund-sporet.' => 'Le transporteur n a propose que la voie du remboursement.',
        'Kun ombooking blev tilbudt' => 'Seul le reacheminement a ete propose',
        'Transportoeren tilboed kun ombookingssporet.' => 'Le transporteur n a propose que la voie du reacheminement.',
        'Passagerens loesning' => 'Solution du passager',
        'Hvilken loesning endte du med?' => 'Quelle solution avez-vous finalement retenue ?',
        'Jeg fik tilbagebetaling' => 'J ai obtenu un remboursement',
        'Jeg gik videre med ombooking' => 'J ai poursuivi avec un reacheminement',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Jeg vil stoppe rejsen og have refund' => 'Je veux arreter le voyage et obtenir un remboursement',
        'Jeg vil videre hurtigst muligt' => 'Je veux poursuivre au plus vite',
        'Jeg har ikke besluttet mig endnu' => 'Je n ai pas encore decide',
        'Aabner kun refund/retur-sporet i naeste trin.' => 'Ouvre uniquement la voie remboursement / retour a l etape suivante.',
        'Aabner kun ombooking hurtigst muligt i naeste trin.' => 'Ouvre uniquement la voie de reacheminement au plus vite a l etape suivante.',
        'Springer videre uden at laase refund/ombooking endnu.' => 'Passe a la suite sans verrouiller encore remboursement / reacheminement.',
        'Maatider / forfriskninger' => 'Repas / rafraichissements',
        'Hotel / overnatning' => 'Hotel / hebergement',
        'Aabner kun det valgte assistance-spor i naeste trin.' => 'N ouvre que le parcours d assistance choisi a l etape suivante.',
        'Refund / retur' => 'Remboursement / retour',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ikke besluttet endnu' => 'Pas encore decide',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking' => 'Reacheminement',
        'Afventer' => 'En attente',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Operatoer' => 'Operateur',
        'Valg efter haendelsen - Trin 4' => 'Choix apres l incident - etape 4',
        'Flow' => 'Parcours',
        'Transport' => 'Transport',
    ],
];
$t = static function ($text) use ($uiLanguage, $translations) {
    if (!is_string($text)) {
        return $text;
    }

    return $translations[$uiLanguage][$text] ?? $text;
};

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$isOngoing = $travelState === 'ongoing';
$isCompleted = $travelState === 'completed';
$isPreview = !empty($flowPreview);

$steps = [
    1 => 'Billet, grunddata',
    2 => 'Vaelg afgang',
    3 => 'Haendelse',
    4 => 'Valg efter haendelsen',
    5 => 'Refusion, ombooking',
    6 => 'Assistance',
    7 => 'Kontakt, opret sag',
];
$steps = array_map($t, $steps);
$currentStep = 4;
$doneSteps = $doneSteps ?? [1, 2, 3];
$progressPct = (int)round(($currentStep / count($steps)) * 100);
$progressLabel = $currentStep . ' / ' . count($steps) . ' trin';

$context = match ($travelState) {
    'ongoing' => $t('Igangvaerende rejse'),
    'before_start' => $t('Foer afgang'),
    default => $t('Afsluttet rejse'),
};
$brandName = 'FerryClaim';
$brandMark = 'FC';

$choiceOptions = [
    'refund_return' => 'Jeg vil stoppe rejsen og have refund',
    'reroute_soonest' => 'Jeg vil videre hurtigst muligt',
    'undecided' => 'Jeg har ikke besluttet mig endnu',
];
$choiceDescriptions = [
    'refund_return' => 'Aabner kun refund/retur-sporet i naeste trin.',
    'reroute_soonest' => 'Aabner kun ombooking hurtigst muligt i naeste trin.',
    'undecided' => 'Springer videre uden at laase refund/ombooking endnu.',
];
$assistanceOptions = [];
if (((string)($flags['gate_ferry_art17_refreshments'] ?? '')) === '1') {
    $assistanceOptions['meals'] = 'Maatider / forfriskninger';
}
if (((string)($flags['gate_ferry_art17_hotel'] ?? '')) === '1') {
    $assistanceOptions['hotel'] = 'Hotel / overnatning';
}

$selectedAssistanceLabels = [];
foreach ($ferryPostIncidentAssistanceTypes as $assistType) {
    if (isset($assistanceOptions[$assistType])) {
        $selectedAssistanceLabels[] = $assistanceOptions[$assistType];
    }
}
$completedOfferPattern = strtolower(trim((string)($form['ferry_offer_pattern'] ?? '')));
if (!in_array($completedOfferPattern, ['choice', 'refund_only', 'reroute_only'], true)) {
    $completedOfferPattern = '';
}
$completedOutcomeChoice = strtolower(trim((string)($form['remedyChoice'] ?? $ferryPostIncidentChoice)));
if (!in_array($completedOutcomeChoice, ['refund_return', 'reroute_soonest'], true)) {
    $completedOutcomeChoice = '';
}
$showCompletedOfferOutcome = $isCompleted && ((string)($flags['gate_art18'] ?? '')) === '1';

$remedySummary = match ($ferryPostIncidentChoice) {
    'refund_return' => 'Refund / retur',
    'reroute_soonest' => 'Ombooking hurtigst muligt',
    'undecided' => 'Ikke besluttet endnu',
    default => 'Afventer',
};
$remedySummary = $showCompletedOfferOutcome
    ? match ($completedOutcomeChoice) {
        'refund_return' => $t('Tilbagebetaling'),
        'reroute_soonest' => $t('Ombooking'),
        default => $t('Afventer'),
    }
    : $remedySummary;
$assistanceSummary = $selectedAssistanceLabels !== [] ? implode(', ', array_map($t, $selectedAssistanceLabels)) : $t('Afventer');

$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $meta['ferry_selected_departure']['operator_name'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $operatorLabel = $candidateOperator;
        break;
    }
}
if ($operatorLabel === '') {
    $operatorLabel = $t('Ikke valgt endnu');
}

$summaryRows = [
    [$t('Refusion / ombooking'), $t($remedySummary), $ferryPostIncidentChoice !== '' ? 'blue' : 'gray'],
    [$t('Assistance'), $assistanceSummary, $selectedAssistanceLabels !== [] ? 'green' : 'gray'],
    [$t('Operatoer'), $operatorLabel, null],
];

$backUrl = $this->Url->build(['action' => 'incident', '?' => ['tc6' => 1]]);

ob_start();
?>
<?php $this->assign('title', $t('Valg efter haendelsen - Trin 4')) ?>
<?php $this->start('css') ?>
<style>
.t6fc-grid { display:grid; gap:22px; }
.t6fc-card {
  border:1px solid #dbe3f0;
  border-radius:24px;
  background:#fff;
  box-shadow:0 16px 40px rgba(15,23,42,.06);
  padding:28px;
}
.t6fc-kicker {
  font-size:13px;
  font-weight:700;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:#94a3b8;
  margin-bottom:10px;
}
.t6fc-options {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:16px;
}
.t6fc-option {
  display:flex;
  align-items:flex-start;
  gap:14px;
  border:1.5px solid #d7deea;
  border-radius:18px;
  padding:20px 22px;
  background:#fff;
  cursor:pointer;
  transition:border-color .15s ease, background .15s ease, box-shadow .15s ease;
}
.t6fc-option:has(input:checked) {
  border-color:#2563eb;
  background:#eff6ff;
  box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.t6fc-option input { margin-top:4px; flex-shrink:0; }
.t6fc-option__title { font-size:18px; font-weight:700; line-height:1.3; color:#0f172a; }
.t6fc-option__meta { margin-top:6px; font-size:14px; line-height:1.5; color:#64748b; }
.t6fc-copy { font-size:18px; line-height:1.65; color:#475569; margin:0 0 8px; }
@media (max-width: 980px) {
  .t6fc-options { grid-template-columns:1fr; }
}
</style>
<?php $this->end() ?>

<?= $this->Form->create(null, [
    'url' => ['action' => 'choices', '?' => ['tc6' => 1]],
    'id' => 'tc6-ferry-choices-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>
<div class="t6fc-grid">
  <div class="tc6-chip">Trin <?= $currentStep ?> / <?= count($steps) ?></div>
  <div>
    <h1 class="tc6-h1"><?= $isOngoing ? 'Hvad vil du goere nu?' : 'Hvad blev relevant efter haendelsen?' ?></h1>
    <p class="t6fc-copy">
      Vaelg kun de spor, der faktisk er relevante efter ferry-haendelsen. Kun de valgte spor bliver aabnet i de naeste trin.
    </p>
  </div>

  <?php if ($showCompletedOfferOutcome): ?>
    <section class="t6fc-card">
      <div class="t6fc-kicker">Transportoerens loesning</div>
      <div class="t6fc-copy" style="margin-bottom:16px;">Hvilke muligheder tilboed transportoeren dig?</div>
      <input type="hidden" name="ferry_offer_pattern" value="" />
      <div class="t6fc-options">
        <?php foreach ([
          'choice' => ['Jeg fik valget mellem tilbagebetaling og ombooking', 'Begge Art. 18-spor kan blive relevante bagefter.'],
          'refund_only' => ['Kun tilbagebetaling blev tilbudt', 'Transportoeren tilboed kun refund-sporet.'],
          'reroute_only' => ['Kun ombooking blev tilbudt', 'Transportoeren tilboed kun ombookingssporet.'],
        ] as $offerKey => [$offerTitle, $offerMeta]): ?>
          <label class="t6fc-option">
            <input
              type="radio"
              name="ferry_offer_pattern"
              value="<?= h($offerKey) ?>"
              <?= $completedOfferPattern === $offerKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6fc-option__title"><?= h($offerTitle) ?></span>
              <span class="t6fc-option__meta"><?= h($offerMeta) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="t6fc-card">
      <div class="t6fc-kicker">Passagerens loesning</div>
      <div class="t6fc-copy" style="margin-bottom:16px;">Hvilken loesning endte du med?</div>
      <div class="t6fc-options">
        <?php foreach ([
          'refund_return' => ['Jeg fik tilbagebetaling', 'Aabner kun tilbagebetalingssporet i naeste trin.'],
          'reroute_soonest' => ['Jeg gik videre med ombooking', 'Aabner kun ombookingssporet i naeste trin.'],
        ] as $choiceKey => [$choiceTitle, $choiceMeta]): ?>
          <label class="t6fc-option">
            <input
              type="radio"
              name="remedyChoice"
              value="<?= h($choiceKey) ?>"
              <?= $completedOutcomeChoice === $choiceKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6fc-option__title"><?= h($choiceTitle) ?></span>
              <span class="t6fc-option__meta"><?= h($choiceMeta) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($ferryPostIncidentChoices !== []): ?>
    <section class="t6fc-card">
      <div class="t6fc-kicker">Refusion / ombooking</div>
      <div class="t6fc-options">
        <?php foreach ($ferryPostIncidentChoices as $choiceKey): ?>
          <?php if (!isset($choiceOptions[$choiceKey])) { continue; } ?>
          <label class="t6fc-option">
            <input
              type="radio"
              name="ferry_post_incident_choice"
              value="<?= h($choiceKey) ?>"
              <?= $ferryPostIncidentChoice === $choiceKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6fc-option__title"><?= h($choiceOptions[$choiceKey]) ?></span>
              <span class="t6fc-option__meta"><?= h($choiceDescriptions[$choiceKey] ?? '') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($assistanceOptions !== []): ?>
    <section class="t6fc-card">
      <div class="t6fc-kicker">Assistance</div>
      <div class="t6fc-options">
        <?php foreach ($assistanceOptions as $assistKey => $assistLabel): ?>
          <label class="t6fc-option">
            <input
              type="checkbox"
              name="ferry_post_incident_assistance_types[]"
              value="<?= h($assistKey) ?>"
              <?= in_array($assistKey, $ferryPostIncidentAssistanceTypes, true) ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6fc-option__title"><?= h($assistLabel) ?></span>
              <span class="t6fc-option__meta">Aabner kun det valgte assistance-spor i naeste trin.</span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?= $this->element('tc6/action_bar', [
      'backUrl' => $backUrl,
      'backLabel' => $t('Tilbage'),
      'nextLabel' => $t('Naeste trin'),
      'nextVariant' => 'navy',
      'disabled' => $isPreview,
  ]) ?>
</div>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'choices', 'formSelector' => '#tc6-ferry-choices-form']) ?>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($translations[$uiLanguage])) {
    $content = strtr($content, $translations[$uiLanguage]);
}

ob_start();
$multimodal = (array)($meta['_multimodal'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
echo $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope'));
$liveEstimateHtml = ob_get_clean();

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => [
        [$t('Flow'), $context, null],
        ['Transport', 'FERRY', null],
    ],
    'summaryRows' => $summaryRows,
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
