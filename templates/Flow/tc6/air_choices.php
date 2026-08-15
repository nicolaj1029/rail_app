<?php
/**
 * TC6 air post-incident choices hub.
 *
 * Keeps air Article 8/9 detail forms intact, but routes the passenger through
 * one canonical post-incident hub first.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$incident = $incident ?? [];
$airPostIncidentChoices = is_array($airPostIncidentChoices ?? null) ? array_values($airPostIncidentChoices) : [];
$airPostIncidentAssistanceTypes = is_array($airPostIncidentAssistanceTypes ?? null) ? array_values($airPostIncidentAssistanceTypes) : [];
$airPostIncidentChoice = trim((string)($airPostIncidentChoice ?? ''));
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$translations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Trin ' => 'Etape ',
        'Billet, grunddata' => 'Billet, donnees de base',
        'Vaelg fly' => 'Choix du vol',
        'Haendelse' => 'Incident',
        'Valg efter haendelsen' => 'Choix apres l incident',
        'Refund, ombooking' => 'Remboursement, reacheminement',
        'Assistance' => 'Assistance',
        'Nedgradering' => 'Declassement',
        'Kontakt & opret sag' => 'Contact et creation du dossier',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Voyage termine',
        'Hvad er planen lige nu?' => 'Quel est le plan en ce moment ?',
        'Hvad blev relevant efter haendelsen?' => 'Qu est-ce qui est devenu pertinent apres l incident ?',
        'Vaelg kun de spor, som faktisk er relevante nu. Remedies og assistance vises kun i de naeste trin, hvis du vaelger dem her.' => 'Choisissez uniquement les parcours qui sont reellement pertinents maintenant. Les recours et l assistance ne seront affiches dans les etapes suivantes que si vous les choisissez ici.',
        'Flyselskabets loesning' => 'Solution de la compagnie aerienne',
        'Hvilke muligheder tilboed flyselskabet dig?' => 'Quelles possibilites la compagnie aerienne vous a-t-elle proposees ?',
        'Jeg fik valget mellem refusion og ombooking' => 'J ai eu le choix entre remboursement et reacheminement',
        'Begge Article 8-spor kan blive relevante bagefter.' => 'Les deux parcours de l article 8 peuvent devenir pertinents ensuite.',
        'Kun refusion blev tilbudt' => 'Seul le remboursement a ete propose',
        'Flyselskabet tilboed kun refund-sporet.' => 'La compagnie aerienne n a propose que la voie du remboursement.',
        'Kun ombooking blev tilbudt' => 'Seul le reacheminement a ete propose',
        'Flyselskabet tilboed kun ombookingssporet.' => 'La compagnie aerienne n a propose que la voie du reacheminement.',
        'Passagerens loesning' => 'Solution du passager',
        'Hvilken loesning endte du med?' => 'Quelle solution avez-vous finalement retenue ?',
        'Jeg fik tilbagebetaling' => 'J ai obtenu un remboursement',
        'Jeg gik videre med ombooking' => 'J ai poursuivi avec un reacheminement',
        'Jeg blev ombooket paa et senere tidspunkt efter eget valg' => 'J ai ete reachemine plus tard selon mon choix',
        'Jeg fortsatte rejsen og oenskede ikke refusion' => 'J ai poursuivi le voyage et je ne souhaitais pas de remboursement',
        'Refund / ombooking' => 'Remboursement / reacheminement',
        'Jeg opgiver rejsen / skal tilbage' => 'J abandonne le voyage / je dois retourner',
        'Jeg vil videre hurtigst muligt' => 'Je veux poursuivre au plus vite',
        'Jeg vil rejse videre senere' => 'Je veux poursuivre plus tard',
        'Jeg fortsaetter rejsen og oensker ikke refund' => 'Je poursuis le voyage et je ne souhaite pas de remboursement',
        'Jeg har ikke besluttet mig endnu' => 'Je n ai pas encore decide',
        'Aabner kun refund/retur-sporet i det naeste trin.' => 'Ouvre uniquement la voie remboursement / retour a l etape suivante.',
        'Aabner kun ombooking hurtigst muligt i det naeste trin.' => 'Ouvre uniquement la voie de reacheminement au plus vite a l etape suivante.',
        'Aabner kun ombooking paa senere tidspunkt i det naeste trin.' => 'Ouvre uniquement la voie de reacheminement a une date ulterieure a l etape suivante.',
        'Bruges ved 5+ timers forsinkelse, hvis du ikke vil opgive rejsen.' => 'A utiliser en cas de retard de plus de 5 heures si vous ne voulez pas abandonner le voyage.',
        'Springer videre uden at laase refund/ombooking endnu.' => 'Passe a la suite sans verrouiller encore remboursement / reacheminement.',
        'Mad og drikke' => 'Repas et boissons',
        'Hotel / overnatning' => 'Hotel / hebergement',
        'Aabner kun det valgte assistance-spor i naeste trin.' => 'N ouvre que le parcours d assistance choisi a l etape suivante.',
        'Refund / retur' => 'Remboursement / retour',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Fortsaetter rejsen' => 'Poursuit le voyage',
        'Ikke besluttet endnu' => 'Pas encore decide',
        'Tilbagebetaling' => 'Remboursement',
        'Fortsatte rejsen' => 'A poursuivi le voyage',
        'Afventer' => 'En attente',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Ansvarligt flyselskab' => 'Compagnie aerienne responsable',
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
    2 => 'Vaelg fly',
    3 => 'Haendelse',
    4 => 'Valg efter haendelsen',
    5 => 'Refund, ombooking',
    6 => 'Assistance',
    7 => 'Nedgradering',
    8 => 'Kontakt & opret sag',
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
$brandName = 'AirClaim';
$brandMark = 'AC';

$choiceOptions = [
    'refund_return' => 'Jeg opgiver rejsen / skal tilbage',
    'reroute_soonest' => 'Jeg vil videre hurtigst muligt',
    'reroute_later' => 'Jeg vil rejse videre senere',
    'no_refund_continue' => 'Jeg fortsaetter rejsen og oensker ikke refund',
    'undecided' => 'Jeg har ikke besluttet mig endnu',
];
$choiceDescriptions = [
    'refund_return' => 'Aabner kun refund/retur-sporet i det naeste trin.',
    'reroute_soonest' => 'Aabner kun ombooking hurtigst muligt i det naeste trin.',
    'reroute_later' => 'Aabner kun ombooking paa senere tidspunkt i det naeste trin.',
    'no_refund_continue' => 'Bruges ved 5+ timers forsinkelse, hvis du ikke vil opgive rejsen.',
    'undecided' => 'Springer videre uden at laase refund/ombooking endnu.',
];
$assistanceOptions = [
    'meals' => 'Mad og drikke',
    'hotel' => 'Hotel / overnatning',
];

$selectedAssistanceLabels = [];
foreach ($airPostIncidentAssistanceTypes as $airAssistType) {
    if (isset($assistanceOptions[$airAssistType])) {
        $selectedAssistanceLabels[] = $assistanceOptions[$airAssistType];
    }
}
$completedArticle8Offer = strtolower(trim((string)($form['air_article8_offer'] ?? '')));
$completedArticle8DelayRefundOnly = $isCompleted
    && strtolower((string)($form['incident_main'] ?? '')) === 'delay'
    && ((string)($flags['gate_air_delay_refund_5h'] ?? '')) === '1'
    && ((string)($flags['gate_air_reroute_refund'] ?? '')) !== '1';
$completedArticle8OfferOptions = $completedArticle8DelayRefundOnly
    ? ['refund_only']
    : ['choice', 'refund_only', 'reroute_only'];
if (!in_array($completedArticle8Offer, $completedArticle8OfferOptions, true)) {
    $completedArticle8Offer = '';
}
$completedOutcomeChoice = strtolower(trim((string)($form['remedyChoice'] ?? $airPostIncidentChoice)));
if (!in_array($completedOutcomeChoice, $airPostIncidentChoices, true)) {
    $completedOutcomeChoice = '';
}
$showCompletedOfferOutcome = $isCompleted && ((string)($flags['gate_art18'] ?? '')) === '1' && $airPostIncidentChoices !== [];
$remedySummary = match ($airPostIncidentChoice) {
    'refund_return' => 'Refund / retur',
    'reroute_soonest' => 'Ombooking hurtigst muligt',
    'reroute_later' => 'Ombooking senere',
    'no_refund_continue' => 'Fortsaetter rejsen',
    'undecided' => 'Ikke besluttet endnu',
    default => 'Afventer',
};
$remedySummary = $showCompletedOfferOutcome
    ? match ($completedOutcomeChoice) {
        'refund_return' => $t('Tilbagebetaling'),
        'reroute_soonest' => $t('Ombooking hurtigst muligt'),
        'reroute_later' => $t('Ombooking senere'),
        'no_refund_continue' => $t('Fortsatte rejsen'),
        'undecided' => $t('Ikke besluttet endnu'),
        default => $t('Afventer'),
    }
    : $remedySummary;
$assistanceSummary = $selectedAssistanceLabels !== [] ? implode(', ', array_map($t, $selectedAssistanceLabels)) : $t('Afventer');
$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $form['operating_carrier'] ?? null,
    $form['marketing_carrier'] ?? null,
    $meta['_multimodal']['air_contract']['primary_claim_party_name'] ?? null,
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
    [$t('Refund / ombooking'), $t($remedySummary), $airPostIncidentChoice !== '' ? 'blue' : 'gray'],
    [$t('Assistance'), $assistanceSummary, $selectedAssistanceLabels !== [] ? 'green' : 'gray'],
    [$t('Ansvarligt flyselskab'), $operatorLabel, null],
];

$backUrl = $this->Url->build(['action' => 'incident', '?' => ['tc6' => 1]]);

ob_start();
?>
<?php $this->assign('title', $t('Valg efter haendelsen - Trin 4')) ?>
<?php $this->start('css') ?>
<style>
.t6ac-grid { display:grid; gap:22px; }
.t6ac-card {
  border:1px solid #dbe3f0;
  border-radius:24px;
  background:#fff;
  box-shadow:0 16px 40px rgba(15,23,42,.06);
  padding:28px;
}
.t6ac-kicker {
  font-size:13px;
  font-weight:700;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:#94a3b8;
  margin-bottom:10px;
}
.t6ac-options {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:16px;
}
.t6ac-option {
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
.t6ac-option:has(input:checked) {
  border-color:#2563eb;
  background:#eff6ff;
  box-shadow:0 0 0 3px rgba(37,99,235,.08);
}
.t6ac-option input { margin-top:4px; flex-shrink:0; }
.t6ac-option__title { font-size:18px; font-weight:700; line-height:1.3; color:#0f172a; }
.t6ac-option__meta { margin-top:6px; font-size:14px; line-height:1.5; color:#64748b; }
.t6ac-copy { font-size:18px; line-height:1.65; color:#475569; margin:0 0 8px; }
@media (max-width: 980px) {
  .t6ac-options { grid-template-columns:1fr; }
}
</style>
<?php $this->end() ?>

<?= $this->Form->create(null, [
    'url' => ['action' => 'choices', '?' => ['tc6' => 1]],
    'id' => 'tc6-air-choices-form',
    'class' => 'tc6-form',
    'data-air-progressive-form' => 'choices',
    'novalidate' => true,
]) ?>
<div class="t6ac-grid">
  <div class="tc6-chip">Trin <?= $currentStep ?> / <?= count($steps) ?></div>
  <div>
    <h1 class="tc6-h1"><?= $isOngoing ? 'Hvad er planen lige nu?' : 'Hvad blev relevant efter haendelsen?' ?></h1>
    <p class="t6ac-copy">
      Vaelg kun de spor, som faktisk er relevante nu. Remedies og assistance vises kun i de naeste trin, hvis du vaelger dem her.
    </p>
  </div>

  <?php if ($showCompletedOfferOutcome): ?>
    <section class="t6ac-card" data-progressive-group="operator-offer" data-progressive-fields="air_article8_offer">
      <div class="t6ac-kicker">Flyselskabets loesning</div>
      <div class="t6ac-copy" style="margin-bottom:16px;">Hvilke muligheder tilboed flyselskabet dig?</div>
      <input type="hidden" name="air_article8_offer" value="" />
      <div class="t6ac-options">
        <?php foreach ([
          'choice' => ['Jeg fik valget mellem refusion og ombooking', 'Begge Article 8-spor kan blive relevante bagefter.'],
          'refund_only' => ['Kun refusion blev tilbudt', 'Flyselskabet tilboed kun refund-sporet.'],
          'reroute_only' => ['Kun ombooking blev tilbudt', 'Flyselskabet tilboed kun ombookingssporet.'],
        ] as $offerKey => [$offerTitle, $offerMeta]): ?>
          <?php if (!in_array($offerKey, $completedArticle8OfferOptions, true)) { continue; } ?>
          <label class="t6ac-option">
            <input
              type="radio"
              name="air_article8_offer"
              value="<?= h($offerKey) ?>"
              <?= $completedArticle8Offer === $offerKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6ac-option__title"><?= h($offerTitle) ?></span>
              <span class="t6ac-option__meta"><?= h($offerMeta) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="t6ac-card" data-progressive-group="passenger-outcome" data-progressive-fields="remedyChoice">
      <div class="t6ac-kicker">Passagerens loesning</div>
      <div class="t6ac-copy" style="margin-bottom:16px;">Hvilken loesning endte du med?</div>
      <div class="t6ac-options">
        <?php foreach ($airPostIncidentChoices as $choiceKey): ?>
          <?php if ($choiceKey === 'undecided') { continue; } ?>
          <?php
            $completedChoiceTitle = match ($choiceKey) {
                'refund_return' => 'Jeg fik tilbagebetaling',
                'reroute_soonest' => 'Jeg gik videre med ombooking',
                'reroute_later' => 'Jeg blev ombooket paa et senere tidspunkt efter eget valg',
                'no_refund_continue' => 'Jeg fortsatte rejsen og oenskede ikke refusion',
                'undecided' => 'Jeg har ikke besluttet mig endnu',
                default => ($choiceOptions[$choiceKey] ?? ''),
            };
          ?>
          <?php if ($completedChoiceTitle === '') { continue; } ?>
          <label class="t6ac-option">
            <input
              type="radio"
              name="remedyChoice"
              value="<?= h($choiceKey) ?>"
              <?= $completedOutcomeChoice === $choiceKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6ac-option__title"><?= h($completedChoiceTitle) ?></span>
              <span class="t6ac-option__meta"><?= h($choiceDescriptions[$choiceKey] ?? '') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($airPostIncidentChoices !== []): ?>
    <section class="t6ac-card" data-progressive-group="post-incident-choice" data-progressive-fields="air_post_incident_choice">
      <div class="t6ac-kicker">Refund / ombooking</div>
      <div class="t6ac-options">
        <?php foreach ($airPostIncidentChoices as $choiceKey): ?>
          <?php if (!isset($choiceOptions[$choiceKey])) { continue; } ?>
          <label class="t6ac-option">
            <input
              type="radio"
              name="air_post_incident_choice"
              value="<?= h($choiceKey) ?>"
              <?= $airPostIncidentChoice === $choiceKey ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6ac-option__title"><?= h($choiceOptions[$choiceKey]) ?></span>
              <span class="t6ac-option__meta"><?= h($choiceDescriptions[$choiceKey] ?? '') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (((string)($flags['gate_art20'] ?? '')) === '1'): ?>
    <section class="t6ac-card" data-progressive-group="assistance-tracks" data-progressive-complete="always">
      <div class="t6ac-kicker">Assistance</div>
      <div class="t6ac-options">
        <?php foreach ($assistanceOptions as $assistKey => $assistLabel): ?>
          <label class="t6ac-option">
            <input
              type="checkbox"
              name="air_post_incident_assistance_types[]"
              value="<?= h($assistKey) ?>"
              <?= in_array($assistKey, $airPostIncidentAssistanceTypes, true) ? 'checked' : '' ?>
              <?= $isPreview ? 'disabled' : '' ?>
            >
            <span>
              <span class="t6ac-option__title"><?= h($assistLabel) ?></span>
              <span class="t6ac-option__meta">Aabner kun det valgte assistance-spor i naeste trin.</span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <div data-progressive-group="actions" data-progressive-complete="always">
  <?= $this->element('tc6/action_bar', [
      'backUrl' => $backUrl,
      'backLabel' => $t('Tilbage'),
      'nextLabel' => $t('Naeste trin'),
      'nextVariant' => 'navy',
      'disabled' => $isPreview,
  ]) ?>
  </div>
</div>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'choices', 'formSelector' => '#tc6-air-choices-form']) ?>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($translations[$uiLanguage])) {
    $content = strtr($content, $translations[$uiLanguage]);
}

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => (float)(($meta['_multimodal']['air_rights']['compensation_amount'] ?? 0)),
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => [
        [$t('Flow'), $context, null],
        ['Transport', 'FLY', null],
    ],
    'summaryRows' => $summaryRows,
    'nextHint' => '',
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
