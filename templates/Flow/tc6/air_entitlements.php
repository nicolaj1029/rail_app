<?php
/**
 * TC6 wrapper for legacy air entitlements step.
 *
 * Keeps the original CakePHP entitlements logic but renders it inside the
 * shared TC6 shell for the early air-short journey flow.
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
        'Upload' => 'Upload',
        'Periodekort' => 'Season pass',
        'Ticketless' => 'Ticketless',
        'Afventer svar' => 'Awaiting answer',
        'Aktiv' => 'Active',
        'Afventer' => 'Pending',
        'Refund / ombooking' => 'Refund / rerouting',
        'Ansvarligt flyselskab' => 'Responsible airline',
        'Naeste trin' => 'Next step',
        'Trin' => 'Step',
        'Lad os saette din flyrejse op' => 'Let us set up your flight',
        'Udfyld kun den rejseinformation vi har brug for for at finde den rigtige reservation og de naeste air-spoergsmaal.' => 'Fill in only the journey information we need to find the right reservation and the next air questions.',
        'Naeste trin afklarer booking og kontrakt for flyrejsen.' => 'The next step clarifies the booking and contract for the flight.',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Upload' => 'Televersement',
        'Periodekort' => 'Carte periode',
        'Ticketless' => 'Sans billet',
        'Afventer svar' => 'En attente de reponse',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Refund / ombooking' => 'Remboursement / reacheminement',
        'Ansvarligt flyselskab' => 'Compagnie responsable',
        'Naeste trin' => 'Etape suivante',
        'Trin' => 'Etape',
        'Lad os saette din flyrejse op' => 'Mettons en place votre trajet aerien',
        'Udfyld kun den rejseinformation vi har brug for for at finde den rigtige reservation og de naeste air-spoergsmaal.' => 'Renseignez seulement les informations de trajet dont nous avons besoin pour trouver la bonne reservation et les prochaines questions air.',
        'Naeste trin afklarer booking og kontrakt for flyrejsen.' => 'L etape suivante clarifie la reservation et le contrat du vol.',
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
$ticketModeLabel = match ($ticketMode) {
    'ticket' => 'Upload',
    'seasonpass' => 'Periodekort',
    default => 'Ticketless',
};
$passengerCount = max(1, (int)($form['passenger_count'] ?? ($journey['passengerCount'] ?? 1)));
$depStation = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ($journey['origin']['value'] ?? ''))));
$arrStation = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ($journey['destination']['value'] ?? ''))));
$routeLabel = trim($depStation . ($depStation !== '' && $arrStation !== '' ? ' -> ' : '') . $arrStation);
$priceCurrency = strtoupper(trim((string)($form['price_currency'] ?? ($journey['price']['currency'] ?? 'EUR'))));

$form['transport_mode'] = 'air';
$form['transport_mode_source'] = 'manual';
$form['ticket_upload_mode'] = 'ticketless';
$meta['transport_mode'] = 'air';
$meta['transport_mode_source'] = 'manual';
$meta['gating_mode'] = 'air';

$airRights = (array)($meta['_multimodal']['air_rights'] ?? []);
$airScope = (array)($meta['_multimodal']['air_scope'] ?? []);
$airContract = (array)($meta['_multimodal']['air_contract'] ?? []);

$responsibleCarrierLabel = '';
foreach ([
    $form['operating_carrier'] ?? null,
    $form['marketing_carrier'] ?? null,
    $airContract['liable_carrier_candidate'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateCarrier) {
    $candidateCarrier = trim((string)$candidateCarrier);
    if ($candidateCarrier !== '' && strtolower($candidateCarrier) !== 'manual_review') {
        $responsibleCarrierLabel = $candidateCarrier;
        break;
    }
}
if ($responsibleCarrierLabel === '') {
    $responsibleCarrierLabel = 'Afventer svar';
}

$careActive = !empty($airRights['gate_air_care']);
$remedyActive = !empty($airRights['gate_air_reroute_refund']) || !empty($airRights['gate_air_delay_refund_5h']);

$summaryRows = [
    ['Care', $careActive ? 'Aktiv' : 'Afventer', $careActive ? 'green' : 'gray'],
    ['Refund / ombooking', $remedyActive ? 'Aktiv' : 'Afventer', $remedyActive ? 'green' : 'gray'],
    ['Ansvarligt flyselskab', $responsibleCarrierLabel, null],
];

ob_start();
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'entitlements.php';
$legacyContent = (string)ob_get_clean();
$legacyMarkupTools = require ROOT . DS . 'templates' . DS . 'element' . DS . 'tc6' . DS . 'legacy_markup_tools.php';
$legacyContent = $legacyMarkupTools['stripEstimate']($legacyContent, 'airLiveEstimate', '.air-live-estimate');
$legacyContent = $legacyMarkupTools['stripById']($legacyContent, 'ticketUploadCard');
$legacyContent = $legacyMarkupTools['stripById']($legacyContent, 'modeJourneyFields');
$legacyContent = preg_replace('/<div class="fe-header">.*?<\/div>/s', '', $legacyContent, 1) ?? $legacyContent;
$legacyContent = preg_replace(
    '/<div class="small muted" style="margin-top:6px;">\s*Auto:.*?<\/div>/s',
    '',
    $legacyContent,
    1
) ?? $legacyContent;
$legacyContent = preg_replace(
    '/(<form\b[^>]*\bid="entitlementsForm"[^>]*)(>)/i',
    '$1 data-air-progressive-form="entitlements"$2',
    $legacyContent,
    1
) ?? $legacyContent;

ob_start();
echo $this->element('tc6/action_bar', [
    'backUrl' => null,
    'nextLabel' => 'Naeste trin',
    'nextVariant' => 'navy',
    'submitName' => 'continue',
]);
$actionBarHtml = (string)ob_get_clean();
$actionBarHtml = preg_replace(
    '/<div class="tc6-action-bar">/',
    '<div class="tc6-action-bar" data-progressive-group="actions" data-progressive-complete="always">',
    $actionBarHtml,
    1
) ?? $actionBarHtml;
$legacyContent = preg_replace('/<div class="actions-row fe-actions-row">.*?<\/div>/s', '', $legacyContent, 1) ?? $legacyContent;
$legacyContent = preg_replace('/<\/fieldset>\s*<\/form>/i', '</fieldset>' . $actionBarHtml . '</form>', $legacyContent, 1) ?? ($legacyContent . $actionBarHtml);

ob_start();
?>
<div class="tc6-legacy-wrap tc6-legacy-wrap--entitlements tc6-air-entitlements-wrap">
  <style>
    #entitlementsForm .fe-header,
    #entitlementsForm .fe-wrapper > .card:first-of-type,
    #entitlementsForm .fe-wrapper > .small.muted,
    .tc6-air-entitlements-wrap .tc6-early-intro > .tc6-subtitle,
    #entitlementsForm #ticketlessCard > .section-title,
    #entitlementsForm #ticketlessCard > .small.muted,
    #entitlementsForm #ticketlessFieldset > .small {
      display: none !important;
    }
    #entitlementsForm .actions-row,
    #entitlementsForm .fe-actions-row {
      display: none !important;
    }
    #entitlementsForm .tc6-action-bar:not([hidden]) {
      display: flex !important;
      margin-top: 24px !important;
    }
  </style>
  <div class="tc6-early-intro">
    <div class="tc6-chip">Trin <?= (int)$currentStep ?></div>
    <h1 class="tc6-h1">Lad os saette din flyrejse op</h1>
    <p class="tc6-subtitle">
      Udfyld kun den rejseinformation vi har brug for for at finde den rigtige reservation
      og de naeste air-spoergsmaal.
    </p>
  </div>
  <?= $legacyContent ?>
</div>
<?php
$content = (string)ob_get_clean();

ob_start();
echo $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract'));
$liveEstimateHtml = ob_get_clean();

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => $routeLabel !== ''
        ? ('Naeste trin afklarer booking og kontrakt for ruten ' . $routeLabel . ' i ' . $priceCurrency . '.')
        : 'Naeste trin afklarer booking og kontrakt for flyrejsen.',
    'liveEstimateHtml' => $liveEstimateHtml,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context') + [
    'brandName' => 'AirClaim',
    'brandMark' => 'AC',
    'shellClass' => 'tc6-shell--entitlements',
]);
