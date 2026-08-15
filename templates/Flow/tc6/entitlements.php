<?php
/**
 * TC6 wrapper for legacy rail entitlements step.
 *
 * Keeps the original CakePHP step intact while placing it inside the TC6 shell.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$steps = $steps ?? [];
$currentStep = (int)($currentStep ?? 2);
$doneSteps = $doneSteps ?? [];
$progressPct = (int)($progressPct ?? 0);
$progressLabel = (string)($progressLabel ?? '');
$stats = $stats ?? [];
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$pageTranslations = (array)($pageTranslations ?? []);

if ($uiLanguage === 'en') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Ongoing journey',
        'Foer afgang' => 'Before departure',
        'Afsluttet rejse' => 'Completed journey',
        'Ikke valgt endnu' => 'Not selected yet',
        'Tilbagebetaling' => 'Refund',
        'Ombooking hurtigst muligt' => 'Rerouting as soon as possible',
        'Ombooking senere' => 'Rerouting later',
        'Intet reelt valg' => 'No real choice',
        'Aktiv' => 'Active',
        'Afventer' => 'Pending',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Naeste trin' => 'Next step',
        'Trin' => 'Step',
        'Billet og rejsegrundlag' => 'Ticket and journey basis',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Naeste trin' => 'Etape suivante',
        'Trin' => 'Etape',
        'Billet og rejsegrundlag' => 'Billet et base du trajet',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$depStation = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ($journey['origin']['value'] ?? ''))));
$arrStation = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ($journey['destination']['value'] ?? ''))));
$routeLabel = trim($depStation . ($depStation !== '' && $arrStation !== '' ? ' -> ' : '') . $arrStation);

$form['transport_mode'] = 'rail';
$form['transport_mode_source'] = 'manual';
$meta['transport_mode'] = 'rail';
$meta['transport_mode_source'] = 'manual';

$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $operatorLabel = $candidateOperator;
        break;
    }
}
if ($operatorLabel === '') {
    $operatorLabel = 'Ikke valgt endnu';
}

$remedyChoice = trim((string)($form['remedyChoice'] ?? ''));
$remedyGateActive = ((string)($flags['gate_art18'] ?? '')) === '1';
$remedySummary = match ($remedyChoice) {
    'refund_return' => 'Tilbagebetaling',
    'reroute_soonest' => 'Ombooking hurtigst muligt',
    'reroute_later' => 'Ombooking senere',
    'no_real_choice' => 'Intet reelt valg',
    default => $remedyGateActive ? 'Aktiv' : 'Afventer',
};
$remedySummaryBadge = $remedyChoice !== ''
    ? 'blue'
    : ($remedyGateActive ? 'green' : 'gray');

$assistanceActive = false;
foreach ([
    'gate_art20',
    'gate_ferry_art17_refreshments',
    'gate_ferry_art17_hotel',
    'gate_ferry_pmr_assistance',
    'gate_ferry_pmr_assistance_partial',
    'gate_bus_assistance_refreshments',
    'gate_bus_assistance_hotel',
    'gate_bus_pmr_assistance',
    'gate_bus_pmr_assistance_partial',
] as $flagKey) {
    if ((string)($flags[$flagKey] ?? '') === '1') {
        $assistanceActive = true;
        break;
    }
}
$assistanceSummary = $assistanceActive ? 'Aktiv' : 'Afventer';
$assistanceSummaryBadge = $assistanceActive ? 'green' : 'gray';

$summaryRows = [
    ['Refusion / ombooking', $remedySummary, $remedySummaryBadge],
    ['Assistance', $assistanceSummary, $assistanceSummaryBadge],
    ['Operatør', $operatorLabel, null],
];
$summaryRows[2][0] = 'Operat' . "\u{00F8}" . 'r';

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'entitlements.php';
$legacyContent = (string)ob_get_clean();
$legacyMarkupTools = require ROOT . DS . 'templates' . DS . 'element' . DS . 'tc6' . DS . 'legacy_markup_tools.php';
$legacyContent = $legacyMarkupTools['stripEstimate']($legacyContent, 'railLiveEstimate', '.rail-live-estimate');

ob_start();
echo $this->element('tc6/action_bar', [
    'backUrl' => null,
    'nextLabel' => 'Naeste trin',
    'nextVariant' => 'navy',
    'submitName' => 'continue',
]);
$actionBarHtml = (string)ob_get_clean();
$legacyContent = preg_replace('/<div class="actions-row fe-actions-row">.*?<\/div>/s', '', $legacyContent, 1) ?? $legacyContent;
$legacyContent = preg_replace('/<\/fieldset>\s*<\/form>/i', '</fieldset>' . $actionBarHtml . '</form>', $legacyContent, 1) ?? ($legacyContent . $actionBarHtml);

ob_start();
?>
<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1">Billet og rejsegrundlag</h1>
<div class="tc6-legacy-wrap tc6-legacy-wrap--entitlements tc6-entitlements-wrap">
  <style>
    .tc6-entitlements-wrap .actions-row,
    .tc6-entitlements-wrap .fe-actions-row {
      display: none !important;
    }
    .tc6-entitlements-wrap .tc6-action-bar {
      display: flex !important;
      margin-top: 24px !important;
    }
    .tc6-entitlements-wrap .air-passenger-stepper {
      margin-top: 8px;
      grid-template-columns: 56px 88px 56px;
      border-radius: 16px;
      overflow: hidden;
      vertical-align: top;
    }
    .tc6-entitlements-wrap .air-passenger-stepper-btn,
    .tc6-entitlements-wrap .air-passenger-stepper-value {
      min-height: 54px;
      height: 54px;
    }
    .tc6-entitlements-wrap .air-passenger-stepper-value {
      padding: 0;
    }
  </style>
  <?= $legacyContent ?>
</div>
<?php
$content = (string)ob_get_clean();

ob_start();
echo $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey'));
$liveEstimateHtml = ob_get_clean();

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
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context') + [
    'brandName' => 'TrainClaim',
    'brandMark' => 'TC',
    'shellClass' => 'tc6-shell--entitlements',
]);
