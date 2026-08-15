<?php
/**
 * TC6 wrapper for legacy ferry entitlements step.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$steps = $steps ?? [];
$currentStep = (int)($currentStep ?? 1);
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
        'Upload' => 'Upload',
        'Ticketless' => 'Ticketless',
        'Passagersejlads' => 'Passenger sailing',
        'Cruise' => 'Cruise',
        'Afventer' => 'Pending',
        'Ikke valgt endnu' => 'Not selected yet',
        'Aktiv' => 'Active',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Naeste trin' => 'Next step',
        'Trin' => 'Step',
        'Lad os saette sejladsen op' => 'Let us set up the sailing',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Upload' => 'Televersement',
        'Ticketless' => 'Sans billet',
        'Passagersejlads' => 'Service passagers',
        'Cruise' => 'Croisiere',
        'Afventer' => 'En attente',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Aktiv' => 'Actif',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Naeste trin' => 'Etape suivante',
        'Trin' => 'Etape',
        'Lad os saette sejladsen op' => 'Mettons en place la traversee',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$ticketMode = strtolower(trim((string)($form['ticket_upload_mode'] ?? 'ticketless')));
$ticketModeLabel = $ticketMode === 'ticket' ? 'Upload' : 'Ticketless';
$serviceType = trim((string)($form['service_type'] ?? ''));
$serviceLabel = match ($serviceType) {
    'passenger_service' => 'Passagersejlads',
    'cruise' => 'Cruise',
    default => 'Afventer',
};
$depStation = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ($journey['origin']['value'] ?? ''))));
$arrStation = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ($journey['destination']['value'] ?? ''))));
$routeLabel = trim($depStation . ($depStation !== '' && $arrStation !== '' ? ' -> ' : '') . $arrStation);

$form['transport_mode'] = 'ferry';
$form['transport_mode_source'] = 'manual';
$form['ticket_upload_mode'] = 'ticketless';
$meta['transport_mode'] = 'ferry';
$meta['transport_mode_source'] = 'manual';
$meta['gating_mode'] = 'ferry';

$ferryRights = (array)($meta['_multimodal']['ferry_rights'] ?? []);
$ferryScope = (array)($meta['_multimodal']['ferry_scope'] ?? []);

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
    $operatorLabel = 'Ikke valgt endnu';
}

$art18Active = !empty($ferryRights['gate_art18']) || ((string)($flags['gate_art18'] ?? '') === '1');
$assistanceActive = !empty($ferryRights['gate_art17_refreshments'])
    || !empty($ferryRights['gate_art17_hotel'])
    || ((string)($flags['gate_ferry_art17_refreshments'] ?? '') === '1')
    || ((string)($flags['gate_ferry_art17_hotel'] ?? '') === '1');

$summaryRows = [
    ['Refusion / ombooking', $art18Active ? 'Aktiv' : 'Afventer', $art18Active ? 'green' : 'gray'],
    ['Assistance', $assistanceActive ? 'Aktiv' : 'Afventer', $assistanceActive ? 'green' : 'gray'],
    ['Operatør', $operatorLabel, null],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'entitlements.php';
$legacyContent = (string)ob_get_clean();
$legacyMarkupTools = require ROOT . DS . 'templates' . DS . 'element' . DS . 'tc6' . DS . 'legacy_markup_tools.php';
$legacyContent = $legacyMarkupTools['stripEstimate']($legacyContent, 'ferryLiveEstimate', '.ferry-live-estimate');
$legacyContent = $legacyMarkupTools['stripById']($legacyContent, 'ticketUploadCard');
$legacyContent = $legacyMarkupTools['stripById']($legacyContent, 'modeJourneyFields');

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
<div class="tc6-legacy-wrap tc6-legacy-wrap--entitlements tc6-entitlements-wrap tc6-ferry-entitlements-wrap">
  <style>
    .tc6-ferry-entitlements-wrap .fe-step,
    .tc6-ferry-entitlements-wrap .fe-title,
    .tc6-ferry-entitlements-wrap .fe-sub,
    .tc6-ferry-entitlements-wrap .tc6-subtitle,
    .tc6-ferry-entitlements-wrap .small.muted,
    .tc6-ferry-entitlements-wrap .helper-note {
      display: none !important;
    }
    .tc6-ferry-entitlements-wrap .actions-row,
    .tc6-ferry-entitlements-wrap .fe-actions-row {
      display: none !important;
    }
    .tc6-ferry-entitlements-wrap .tc6-action-bar {
      display: flex !important;
      margin-top: 24px !important;
    }
  </style>
  <div class="tc6-early-intro">
    <div class="tc6-chip">Trin <?= (int)$currentStep ?></div>
    <h1 class="tc6-h1">Lad os saette sejladsen op</h1>
  </div>
  <?= $legacyContent ?>
</div>
<?php
$content = (string)ob_get_clean();

ob_start();
echo $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope'));
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
    'brandName' => 'FerryClaim',
    'brandMark' => 'FC',
    'shellClass' => 'tc6-shell--entitlements',
]);
