<?php
/**
 * TC6 wrapper for legacy rail stranding step.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$steps = $steps ?? [];
$currentStep = (int)($currentStep ?? 4);
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
        'Tilbagebetaling' => 'Refund',
        'Ombooking hurtigst muligt' => 'Rerouting as soon as possible',
        'Ombooking senere' => 'Rerouting later',
        'Intet reelt valg' => 'No real choice',
        'Aktiv' => 'Active',
        'Afventer' => 'Pending',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Ikke valgt endnu' => 'Not selected yet',
        'Trin' => 'Step',
        'Strandet paa station' => 'Stranded at the station',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Trin' => 'Etape',
        'Strandet paa station' => 'Bloque en gare',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $selectedDeparture['operator_name'] ?? null,
    $selectedDeparture['operator'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $operatorLabel = $candidateOperator;
        break;
    }
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
    ['Operatør', $operatorLabel !== '' ? $operatorLabel : 'Ikke valgt endnu', null],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'railstranding.php';
$legacyContent = (string)ob_get_clean();
$legacyMarkupTools = require ROOT . DS . 'templates' . DS . 'element' . DS . 'tc6' . DS . 'legacy_markup_tools.php';
$legacyContent = $legacyMarkupTools['stripEstimate']($legacyContent, 'railLiveEstimate', '.rail-live-estimate');
$legacyContent = $legacyMarkupTools['stripStepIntro']($legacyContent);

ob_start();
?>
<style>
.tc6-railstranding-wrap [data-show-if] {
  display: none;
}
.tc6-railstranding-wrap.flow-preview [data-show-if] {
  display: none !important;
}
.tc6-railstranding-wrap .flow-wrapper {
  max-width: none;
  margin: 0;
  padding: 0;
}
.tc6-railstranding-wrap .fps-callout {
  display: block !important;
}
.tc6-railstranding-wrap .fps-callout .fps-subsection-title,
.tc6-railstranding-wrap .fps-callout .small.muted {
  display: block !important;
}
.tc6-railstranding-wrap .fps-panel-grid {
  align-items: start;
}
.tc6-railstranding-wrap .fps-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.tc6-railstranding-wrap .fps-actions a.button,
.tc6-railstranding-wrap .fps-actions button.button {
  min-width: 160px;
  min-height: 56px;
  border-radius: 16px;
  font-size: 15px;
  font-weight: 700;
  box-shadow: none;
}
.tc6-railstranding-wrap .fps-actions a.button {
  background: #fff !important;
  color: #0f172a !important;
  border: 1px solid #d7deea !important;
}
.tc6-railstranding-wrap .fps-actions button.button {
  background: #0f172a !important;
  color: #fff !important;
  border: 1px solid #0f172a !important;
}
.tc6-railstranding-wrap .fps-inline-choices:has(> label:nth-of-type(3)) {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.tc6-railstranding-wrap .tc6-station-autocomplete {
  position: relative;
  min-width: 0;
}
.tc6-railstranding-wrap .tc6-station-suggest {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 2px);
  z-index: 50;
  background: #fff;
  border: 1px solid var(--tc-border);
  border-radius: var(--tc-r-md);
  box-shadow: var(--tc-shadow-md);
  max-height: 220px;
  overflow: auto;
}
.tc6-railstranding-wrap .tc6-station-suggest button {
  width: 100%;
  text-align: left;
  border: 0;
  background: transparent;
  padding: 8px 10px;
  cursor: pointer;
  font-size: 14px;
  color: var(--tc-text-1);
}
.tc6-railstranding-wrap .tc6-station-suggest button:hover,
.tc6-railstranding-wrap .tc6-station-suggest button:focus {
  background: #f6f6f6;
  outline: none;
}
@media (max-width: 760px) {
  .tc6-railstranding-wrap .fps-inline-choices:has(> label:nth-of-type(3)) {
    grid-template-columns: 1fr;
  }
  .tc6-railstranding-wrap .fps-actions {
    justify-content: stretch;
  }
  .tc6-railstranding-wrap .fps-actions a.button,
  .tc6-railstranding-wrap .fps-actions button.button {
    width: 100%;
    min-width: 0;
  }
}
</style>
<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1">Strandet paa station</h1>
<div class="tc6-legacy-wrap tc6-railstranding-wrap<?= !empty($flowPreview) ? ' flow-preview' : '' ?>">
  <?= $legacyContent ?>
</div>
<?php
$content = (string)ob_get_clean();

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation'     => $compensation ?? null,
    'compRate'         => $compRate ?? null,
    'compBase'         => $compBase ?? null,
    'progressPct'      => $progressPct,
    'progressLabel'    => $progressLabel,
    'stats'            => $stats,
    'summaryRows'      => $summaryRows,
    'nextHint'         => '',
]);

// Fix E: showLockIcons for fremtidige trin i sidebar
echo $this->element('tc6/shell', [
    'steps'         => $steps,
    'currentStep'   => $currentStep,
    'doneSteps'     => $doneSteps,
    'context'       => $context,
    'brandName'     => 'TrainClaim',
    'brandMark'     => 'TC',
    'content'       => $content,
    'rightPanel'    => $rightPanel,
    'showLockIcons' => true,
]);
