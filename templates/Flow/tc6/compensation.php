<?php
/**
 * TC6 rail compensation step.
 *
 * This step is intentionally slimmed down. Detailed settlement of refund,
 * assistance and receipts now happens in backend after case creation.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$claim = is_array($claim ?? null) ? $claim : [];
$journey = $journey ?? [];
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
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Afventer' => 'En attente',
        'Registreret' => 'Enregistre',
        'Backend afklarer' => 'Verification back-office',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatoer' => 'Operateur',
        'Flow' => 'Parcours',
        'Trin ' => 'Etape ',
        'Foreloebig rail-vurdering' => 'Evaluation rail preliminaire',
        'Rute' => 'Itineraire',
        'Bookingreference' => 'Reference de reservation',
        'Det er registreret indtil nu' => 'Voici ce qui est enregistre a ce stade',
        'Live rail-estimat' => 'Estimation rail en direct',
        'Forsinkelse' => 'Retard',
        'Prisgrundlag' => 'Base tarifaire',
        'Foreloebigt Art. 19-band er registreret som ' => 'La tranche provisoire de l art. 19 est enregistree a ',
        'Refusion / ombooking er registreret med ' => 'Le remboursement / reacheminement est enregistre a ',
        'Assistanceudgifter er registreret med ' => 'Les frais d assistance sont enregistres a ',
        'Ansvarligt selskab er foreloebigt markeret som ' => 'La partie responsable est provisoirement indiquee comme ',
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
$steps = array_map($t, $steps);
$currentStep = (int)($currentStep ?? 5);
$doneSteps = $doneSteps ?? [];
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ' trin'));

$routeLabel = trim(
    (string)($form['dep_station'] ?? ($journey['origin']['value'] ?? ''))
    . ' -> '
    . (string)($form['arr_station'] ?? ($journey['destination']['value'] ?? ''))
);
$operatorLabel = trim((string)($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')));
$bookingReference = trim((string)($form['booking_reference'] ?? ($form['ticket_no'] ?? ($meta['_auto']['ticket_no']['value'] ?? ''))));
$liableParty = trim((string)($liableParty ?? ''));
$delayAtFinal = (int)($delayAtFinal ?? (int)($form['delayAtFinalMinutes'] ?? 0));
$ticketPriceAmount = (float)($ticketPriceAmount ?? 0);
$currency = (string)($currency ?? 'EUR');

$breakdown = (array)($claim['breakdown'] ?? []);
$totals = (array)($claim['totals'] ?? []);
$compBreakdown = (array)($breakdown['compensation'] ?? []);
$expensesBreakdown = (array)($breakdown['expenses'] ?? []);
$art18Breakdown = (array)($breakdown['art18'] ?? []);

$compAmount = (float)($compBreakdown['amount'] ?? 0);
$compPct = (int)($compBreakdown['pct'] ?? 0);
$totalsCurrency = (string)($totals['currency'] ?? $currency);
$expensesTotal = (float)($expensesBreakdown['total'] ?? 0);
$art18Total = (float)(
    (float)($art18Breakdown['amount'] ?? 0)
    + (float)($art18Breakdown['refund_amount'] ?? 0)
    + (float)($art18Breakdown['reroute_extra_costs'] ?? 0)
    + (float)($art18Breakdown['return_to_origin'] ?? 0)
);

$remedyChoice = trim((string)($form['remedyChoice'] ?? ''));
$remedyLabel = match ($remedyChoice) {
    'refund_return' => $t('Tilbagebetaling'),
    'reroute_soonest' => $t('Ombooking hurtigst muligt'),
    'reroute_later' => $t('Ombooking senere'),
    'no_real_choice' => $t('Intet reelt valg'),
    default => $t('Afventer'),
};
$remedyBadge = $remedyChoice !== '' ? 'blue' : 'gray';
$assistLabel = $expensesTotal > 0 ? $t('Registreret') : $t('Backend afklarer');
$assistBadge = $expensesTotal > 0 ? 'green' : 'gray';

$summaryRows = [
    [$t('Refusion / ombooking'), $remedyLabel, $remedyBadge],
    [$t('Assistance'), $assistLabel, $assistBadge],
    [$t('Operatoer'), $operatorLabel !== '' ? $operatorLabel : $t('Afventer'), null],
];

$stats = $stats ?? [
    [$t('Flow'), $travelStateLabel, null],
    ['Transport', 'RAIL', null],
];

$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $this->Url->build(['action' => 'downgrade', '?' => $flowQuery]);
$estimateLabel = $compAmount > 0 ? number_format($compAmount, 2, ',', '.') . ' ' . $totalsCurrency : $t('Afventer');

ob_start();
?>
<?= $this->Form->create(null, [
    'url' => ['action' => 'compensation', '?' => $flowQuery],
    'id' => 'tc6-compensation-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>

<div class="tc6-compensation">
  <div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
  <h1 class="tc6-h1"><?= h($t('Foreloebig rail-vurdering')) ?></h1>

  <?= $this->element('flow_locked_notice') ?>

  <div class="tc6-grid-3">
    <?php if ($routeLabel !== '->' && trim($routeLabel) !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Rute')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($routeLabel) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($operatorLabel !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Operatoer')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($operatorLabel) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($bookingReference !== ''): ?>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Bookingreference')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($bookingReference) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-label"><?= h($t('Det er registreret indtil nu')) ?></div>
    <div class="tc6-grid-3">
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Live rail-estimat')) ?></div>
        <div class="tc6-meta-pill__value"><?= h($estimateLabel) ?></div>
      </div>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Forsinkelse')) ?></div>
        <div class="tc6-meta-pill__value"><?= $delayAtFinal > 0 ? h((string)$delayAtFinal . ' min') : h($t('Afventer')) ?></div>
      </div>
      <div class="tc6-meta-pill">
        <div class="tc6-meta-pill__label"><?= h($t('Prisgrundlag')) ?></div>
        <div class="tc6-meta-pill__value"><?= $ticketPriceAmount > 0 ? h(number_format($ticketPriceAmount, 2, ',', '.') . ' ' . $currency) : h($t('Afventer')) ?></div>
      </div>
    </div>
    <?php if ($compPct > 0 || $art18Total > 0 || $expensesTotal > 0 || $liableParty !== ''): ?>
      <ul class="tc6-bullet-list" style="margin-top:16px;">
        <?php if ($compPct > 0): ?>
          <li><?= h($t('Foreloebigt Art. 19-band er registreret som ')) ?><?= h((string)$compPct) ?> %.</li>
        <?php endif; ?>
        <?php if ($art18Total > 0): ?>
          <li><?= h($t('Refusion / ombooking er registreret med ')) ?><?= h(number_format($art18Total, 2, ',', '.')) ?> <?= h($totalsCurrency) ?>.</li>
        <?php endif; ?>
        <?php if ($expensesTotal > 0): ?>
          <li><?= h($t('Assistanceudgifter er registreret med ')) ?><?= h(number_format($expensesTotal, 2, ',', '.')) ?> <?= h($totalsCurrency) ?>.</li>
        <?php endif; ?>
        <?php if ($liableParty !== ''): ?>
          <li><?= h($t('Ansvarligt selskab er foreloebigt markeret som ')) ?><?= h(strtoupper($liableParty)) ?>.</li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
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
<?= $this->element('flow_autosave', ['step' => 'compensation', 'formSelector' => '#tc6-compensation-form']) ?>
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
    'journey' => $journey,
]);
$liveEstimateHtml = trim((string)ob_get_clean());

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compAmount,
    'compRate' => $compPct > 0 ? ($compPct . ' % af billetprisen') : null,
    'compBase' => $ticketPriceAmount > 0 ? (number_format($ticketPriceAmount, 2, ',', '.') . ' ' . $currency) : null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel') + [
    'context' => $travelStateLabel,
]);
