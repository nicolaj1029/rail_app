<?php
/**
 * TC6 wrapper for legacy rail departure selection.
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
        'Gennemgaaende billet' => 'Through ticket',
        'Separate kontrakter' => 'Separate contracts',
        'Afventer' => 'Pending',
        'Tilbagebetaling' => 'Refund',
        'Ombooking hurtigst muligt' => 'Rerouting as soon as possible',
        'Ombooking senere' => 'Rerouting later',
        'Intet reelt valg' => 'No real choice',
        'Aktiv' => 'Active',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Ikke valgt endnu' => 'Not selected yet',
        'Trin' => 'Step',
        'Vaelg afgang og kontrakt' => 'Choose departure and contract',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Gennemgaaende billet' => 'Billet unique',
        'Separate kontrakter' => 'Contrats separes',
        'Afventer' => 'En attente',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Trin' => 'Etape',
        'Vaelg afgang og kontrakt' => 'Choisissez le depart et le contrat',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$trainLabel = trim((string)($selectedDeparture['train_number'] ?? ($form['train_no'] ?? '')));
$operatorLabel = trim((string)($selectedDeparture['operator_name'] ?? ($selectedDeparture['operator'] ?? ($form['operator'] ?? ($meta['_auto']['operator']['value'] ?? '')))));
$contractModel = strtolower(trim((string)($form['contract_model'] ?? (($meta['rail_contract_structure_seed'] ?? [])['effective_contract_model'] ?? ''))));
$contractLabel = match ($contractModel) {
    'through' => 'Gennemgaaende billet',
    'separate' => 'Separate kontrakter',
    default => 'Afventer',
};
$originLabel = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
$destinationLabel = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
$routeLabel = trim($originLabel . ($originLabel !== '' && $destinationLabel !== '' ? ' -> ' : '') . $destinationLabel);

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
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'rail_departure_select.php';
$legacyContent = (string)ob_get_clean();

ob_start();
?>
<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1">Vaelg afgang og kontrakt</h1>
<div class="tc6-legacy-wrap tc6-legacy-wrap--selection tc6-rail-departure-wrap">
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
    'brandName' => 'TrainClaim',
    'brandMark' => 'TC',
    'content' => $content,
    'rightPanel' => $rightPanel,
]);
