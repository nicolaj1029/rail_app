<?php
/**
 * TC6 wrapper for legacy air reservation contract step.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$routeType = $routeType ?? 'direct';
$routeLegs = $routeLegs ?? [];
$sellerChannel = $sellerChannel ?? 'operator';
$bookingTopology = $bookingTopology ?? '';
$problemContractId = $problemContractId ?? '';
$contractUnits = $contractUnits ?? [];
$problemLegId = $problemLegId ?? '';
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
        'Forbindelse' => 'Connection',
        'Direkte' => 'Direct',
        'Rejsebureau' => 'Travel agency',
        'Billetudbyder' => 'Ticket provider',
        'Flere steder' => 'Multiple places',
        'Flyselskab' => 'Airline',
        'Samlet booking' => 'Single booking',
        'Separate billetter' => 'Separate tickets',
        'Uafklaret' => 'Unclear',
        'Afventer' => 'Pending',
        'Rute' => 'Route',
        'Koebssted' => 'Purchase point',
        'Booking' => 'Booking',
        'Trin' => 'Step',
        'Hvordan blev flyrejsen koebt?' => 'How was the flight purchased?',
        'Vi afklarer, om rejsen ligger i samme booking eller i flere kontrakter, saa de efterfoelgende spoergsmaal og vurderinger matcher den rigtige air-logik.' => 'We clarify whether the trip is in the same booking or in multiple contracts, so the next questions and assessments match the correct air logic.',
        'Naeste trin matcher den konkrete flyvning for reservationen.' => 'The next step matches the specific flight for the booking.',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Forbindelse' => 'Correspondance',
        'Direkte' => 'Direct',
        'Rejsebureau' => 'Agence de voyages',
        'Billetudbyder' => 'Vendeur de billets',
        'Flere steder' => 'Plusieurs endroits',
        'Flyselskab' => 'Compagnie aerienne',
        'Samlet booking' => 'Reservation unique',
        'Separate billetter' => 'Billets separes',
        'Uafklaret' => 'Non clarifie',
        'Afventer' => 'En attente',
        'Rute' => 'Itineraire',
        'Koebssted' => 'Lieu d achat',
        'Booking' => 'Reservation',
        'Trin' => 'Etape',
        'Hvordan blev flyrejsen koebt?' => 'Comment le voyage aerien a-t-il ete achete ?',
        'Vi afklarer, om rejsen ligger i samme booking eller i flere kontrakter, saa de efterfoelgende spoergsmaal og vurderinger matcher den rigtige air-logik.' => 'Nous verifions si le voyage releve d une seule reservation ou de plusieurs contrats, afin que les prochaines questions et evaluations suivent la bonne logique air.',
        'Naeste trin matcher den konkrete flyvning for reservationen.' => 'L etape suivante identifie le vol precis de la reservation.',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$context = match ($travelState) {
    'ongoing' => 'Igangvaerende rejse',
    'before_start' => 'Foer afgang',
    default => 'Afsluttet rejse',
};

$routePoints = [];
foreach ((array)$routeLegs as $index => $leg) {
    if (!is_array($leg)) {
        continue;
    }
    $depLabel = trim((string)($leg['dep_label'] ?? ''));
    $arrLabel = trim((string)($leg['arr_label'] ?? ''));
    if ($index === 0 && $depLabel !== '') {
        $routePoints[] = $depLabel;
    }
    if ($arrLabel !== '') {
        $routePoints[] = $arrLabel;
    }
}
$routeLine = implode(' -> ', $routePoints);
$routeTypeLabel = $routeType === 'connecting' ? 'Forbindelse' : 'Direkte';
$sellerLabel = match ($sellerChannel) {
    'agency' => 'Rejsebureau',
    'retailer' => 'Billetudbyder',
    'tour_operator' => 'Flere steder',
    default => 'Flyselskab',
};
$topologyLabel = match ($bookingTopology) {
    'through_booking' => 'Samlet booking',
    'separate_contracts' => 'Separate billetter',
    'unknown' => 'Uafklaret',
    default => $routeType === 'connecting' ? 'Afventer' : 'Direkte',
};

$summaryRows = [
    ['Rute', $routeTypeLabel, $routeType !== '' ? 'blue' : 'gray'],
    ['Koebssted', $sellerLabel, null],
    ['Booking', $topologyLabel, $bookingTopology !== '' ? 'blue' : 'gray'],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'air_reservation_contract.php';
$legacyContent = (string)ob_get_clean();

ob_start();
?>
<div class="tc6-legacy-wrap tc6-legacy-wrap--selection tc6-air-reservation-wrap">
  <div class="tc6-early-intro">
    <div class="tc6-chip">Trin <?= (int)$currentStep ?></div>
    <h1 class="tc6-h1">Hvordan blev flyrejsen koebt?</h1>
    <p class="tc6-subtitle">
      Vi afklarer, om rejsen ligger i samme booking eller i flere kontrakter, saa de
      efterfoelgende spoergsmaal og vurderinger matcher den rigtige air-logik.
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
    'nextHint' => $routeLine !== ''
        ? ('Naeste trin matcher den konkrete flyvning paa ' . $routeLine . '.')
        : 'Naeste trin matcher den konkrete flyvning for reservationen.',
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
