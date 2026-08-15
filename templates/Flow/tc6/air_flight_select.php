<?php
/**
 * TC6 wrapper for legacy air flight selection step.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$selectedFlight = (array)($selectedFlight ?? []);
$selectedLeg = (array)($selectedLeg ?? []);
$routeLegs = $routeLegs ?? [];
$scopeLegs = $scopeLegs ?? [];
$lookupStrategy = (string)($lookupStrategy ?? '');
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
        'Segment-match' => 'Segment match',
        'Reservation-match' => 'Reservation match',
        'Direkte route' => 'Direct route',
        'Foreloebig' => 'Provisional',
        'Afventer' => 'Pending',
        'Fly' => 'Flight',
        'Ansvarligt flyselskab' => 'Responsible airline',
        'Match' => 'Match',
        'Trin' => 'Step',
        'Vaelg den rigtige flyvning' => 'Choose the correct flight',
        'Match reservationen med den konkrete flyvning, saa haendelsesgating, ansvar og den videre vurdering bliver bygget paa det rigtige segment.' => 'Match the booking to the specific flight so event gating, liability and the continued assessment are built on the correct segment.',
        'Det valgte fly laaser de foelgende air-spoergsmaal.' => 'The selected flight locks the following air questions.',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Segment-match' => 'Correspondance segment',
        'Reservation-match' => 'Correspondance reservation',
        'Direkte route' => 'Itineraire direct',
        'Foreloebig' => 'Provisoire',
        'Afventer' => 'En attente',
        'Fly' => 'Vol',
        'Ansvarligt flyselskab' => 'Compagnie responsable',
        'Match' => 'Correspondance',
        'Trin' => 'Etape',
        'Vaelg den rigtige flyvning' => 'Choisissez le bon vol',
        'Match reservationen med den konkrete flyvning, saa haendelsesgating, ansvar og den videre vurdering bliver bygget paa det rigtige segment.' => 'Associez la reservation au vol precis afin que le filtrage des evenements, la responsabilite et l evaluation suivante reposent sur le bon segment.',
        'Det valgte fly laaser de foelgende air-spoergsmaal.' => 'Le vol choisi verrouille les questions air suivantes.',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$flightNumber = strtoupper(trim((string)($selectedFlight['flight_number'] ?? ($form['selected_flight_number'] ?? ($form['ticket_no'] ?? '')))));
$carrierLabel = trim((string)($selectedFlight['carrier_name'] ?? ($selectedFlight['marketing_carrier_name'] ?? ($form['marketing_carrier'] ?? ''))));
$legLabel = trim((string)($selectedLeg['dep_label'] ?? '')) . ((trim((string)($selectedLeg['dep_label'] ?? '')) !== '' && trim((string)($selectedLeg['arr_label'] ?? '')) !== '') ? ' -> ' : '') . trim((string)($selectedLeg['arr_label'] ?? ''));
$lookupLabel = match ($lookupStrategy) {
    'connecting_segment_first' => 'Segment-match',
    'separate_segment_first' => 'Reservation-match',
    'direct_full_route' => 'Direkte route',
    'unknown_provisional' => 'Foreloebig',
    default => 'Afventer',
};

$summaryRows = [
    ['Fly', $flightNumber !== '' ? $flightNumber : 'Afventer', $flightNumber !== '' ? 'blue' : 'gray'],
    ['Ansvarligt flyselskab', $carrierLabel !== '' ? $carrierLabel : 'Afventer', null],
    ['Match', $lookupLabel, $lookupStrategy !== '' ? 'blue' : 'gray'],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'air_flight_select.php';
$legacyContent = (string)ob_get_clean();

ob_start();
?>
<div class="tc6-legacy-wrap tc6-legacy-wrap--selection tc6-air-flight-wrap">
  <div class="tc6-early-intro">
    <div class="tc6-chip">Trin <?= (int)$currentStep ?></div>
    <h1 class="tc6-h1">Vaelg den rigtige flyvning</h1>
    <p class="tc6-subtitle">
      Match reservationen med den konkrete flyvning, saa haendelsesgating, ansvar og den
      videre vurdering bliver bygget paa det rigtige segment.
    </p>
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
    'nextHint' => $legLabel !== ''
        ? ('Det valgte fly laaser haendelsesgatingen for ' . $legLabel . '.')
        : 'Det valgte fly laaser de foelgende air-spoergsmaal.',
]);

echo $this->element('tc6/shell', [
    'steps' => $steps,
    'currentStep' => $currentStep,
    'doneSteps' => $doneSteps,
    'context' => $context,
    'brandName' => 'AirClaim',
    'brandMark' => 'AC',
    'content' => $content,
    'rightPanel' => $rightPanel,
]);
