<?php
/**
 * TC6 wrapper for legacy ferry departure selection.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$selectedDeparture = (array)($selectedDeparture ?? []);
$steps = $steps ?? [];
$currentStep = (int)($currentStep ?? 3);
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
        'Faerge' => 'Ferry',
        'Operatør' => 'Operator',
        'Status' => 'Status',
        'Afventer' => 'Pending',
        'Trin' => 'Step',
        'Vaelg den rigtige afgang' => 'Choose the correct departure',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Faerge' => 'Ferry',
        'Operatør' => 'Operateur',
        'Status' => 'Statut',
        'Afventer' => 'En attente',
        'Trin' => 'Etape',
        'Vaelg den rigtige afgang' => 'Choisissez le bon depart',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$vesselLabel = trim((string)($selectedDeparture['vessel_name'] ?? ($form['ferry_vessel_name'] ?? '')));
$operatorLabel = trim((string)($selectedDeparture['operator_name'] ?? ($form['operator'] ?? '')));
$statusLabel = trim((string)($selectedDeparture['status'] ?? ''));

$summaryRows = [
    ['Faerge', $vesselLabel !== '' ? $vesselLabel : 'Afventer', $vesselLabel !== '' ? 'blue' : 'gray'],
    ['Operatør', $operatorLabel !== '' ? $operatorLabel : 'Afventer', null],
    ['Status', $statusLabel !== '' ? $statusLabel : 'Afventer', $statusLabel !== '' ? 'blue' : 'gray'],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'ferry_departure_select.php';
$legacyContent = (string)ob_get_clean();

ob_start();
?>
<div class="tc6-legacy-wrap tc6-legacy-wrap--selection tc6-ferry-departure-wrap">
  <div class="tc6-early-intro">
    <div class="tc6-chip">Trin <?= (int)$currentStep ?></div>
    <h1 class="tc6-h1">Vaelg den rigtige afgang</h1>
  </div>
  <?= $legacyContent ?>
</div>
<?php
$content = (string)ob_get_clean();

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => '',
]);

echo $this->element('tc6/shell', [
    'steps' => $steps,
    'currentStep' => $currentStep,
    'doneSteps' => $doneSteps,
    'context' => $context,
    'brandName' => 'FerryClaim',
    'brandMark' => 'FC',
    'content' => $content,
    'rightPanel' => $rightPanel,
]);
