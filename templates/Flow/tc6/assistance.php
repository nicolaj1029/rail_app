<?php
/**
 * TC6 view for FlowController::assistance().
 *
 * Thin CakePHP view layer over the existing assistance step.
 * No gating, redirects or calculations live here.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$priceHints = $priceHints ?? [];
$railPostIncident = is_array($railPostIncident ?? null) ? (array)$railPostIncident : [];
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$assistanceTranslations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Billet, grunddata' => 'Billet, donnees de base',
        'Vaelg afgang' => 'Choix du depart',
        'Vaelg fly' => 'Choix du vol',
        'Haendelse' => 'Incident',
        'Valg efter haendelsen' => 'Choix apres l incident',
        'Refusion, ombooking' => 'Remboursement, reacheminement',
        'Refund, ombooking' => 'Remboursement, reacheminement',
        'Assistance' => 'Assistance',
        'Kontakt, opret sag' => 'Contact, creation du dossier',
        'Kontakt & opret sag' => 'Contact et creation du dossier',
        'Resultat' => 'Resultat',
        'Mad og Hotel' => 'Repas et hotel',
        'Care under rejsen' => 'Assistance pendant le voyage',
        'Assistance under sejladsen' => 'Assistance pendant la traversee',
        'Afklar kun om flyselskabet tilbod maaltider, hotel eller anden care. Eventuelle air-udgifter registreres senere i backend-sagen.' => 'Indiquez uniquement si la compagnie aerienne a propose des repas, un hotel ou une autre assistance. Les frais air eventuels sont enregistres plus tard dans le dossier back-office.',
        'Afklar kun om operatoeren tilbod forplejning, hotel eller anden assistance. Eventuelle faergeudgifter registreres senere i backend-sagen.' => 'Indiquez uniquement si l operateur a propose des repas, un hotel ou une autre assistance. Les frais ferry eventuels sont enregistres plus tard dans le dossier back-office.',
        'Dette rail-trin bruger samme station-scope som strandet paa station: maaltider, hotel og lokal transport mellem station og hotel. Spor-transport og omlaegning hoerer ikke til her.' => 'Cette etape rail utilise le meme perimetre que le blocage en gare : repas, hotel et transport local entre la gare et l hotel. Le transport sur voie et le reacheminement ne relevent pas d ici.',
        'Ikke til raadighed' => 'Non disponible',
        'Urimelige vilkaar' => 'Conditions deraisonnables',
        'Lukket' => 'Ferme',
        'Andet' => 'Autre',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Delvis' => 'Partiel',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Operatoer' => 'Operateur',
        'Flow' => 'Parcours',
        'Afsluttet rejse' => 'Voyage termine',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Foer afgang' => 'Avant le depart',
        'Completed disruption' => 'Perturbation terminee',
        'Trin ' => 'Etape ',
        'Assistance er kun delvist aktiveret. Registrer kun de dele, der faktisk blev relevante i situationen.' => 'L assistance n est activee que partiellement. Enregistrez uniquement les elements qui ont reellement ete pertinents dans la situation.',
        'Assistance ser ikke ud til at vaere aktiv i den nuvaerende sag. Hvis du fortsaetter, er det stadig CakePHP der afgoer den endelige vurdering.' => 'L assistance ne semble pas active dans le dossier actuel. Si vous continuez, c est toujours CakePHP qui rend l evaluation finale.',
        'Assistance afventer stadig de endelige gates. Visningen her er derfor kun et tyndt tc6-lag oven paa den normale Cake-logik.' => 'L assistance attend encore les validations finales. Cet affichage n est donc qu une fine couche tc6 au-dessus de la logique Cake normale.',
        'Air-sporet afklarer kun om care blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.' => 'Le parcours air clarifie uniquement si l assistance a ete proposee ou a manque. La documentation et les frais concrets sont enregistres plus tard dans le dossier back-office.',
        'Rail-sporet afklarer kun om assistance blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.' => 'Le parcours rail clarifie uniquement si l assistance a ete proposee ou a manque. La documentation et les frais concrets sont enregistres plus tard dans le dossier back-office.',
        'Faergesporet afklarer kun om assistance blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.' => 'Le parcours ferry clarifie uniquement si l assistance a ete proposee ou a manque. La documentation et les frais concrets sont enregistres plus tard dans le dossier back-office.',
        'Vejledende niveauer:' => 'Niveaux indicatifs :',
        'Maaltider og forfriskninger' => 'Repas et rafraichissements',
        'Blev maaltider eller forfriskninger tilbudt?' => 'Des repas ou des rafraichissements ont-ils ete proposes ?',
        'Hvorfor blev maaltider ikke tilbudt?' => 'Pourquoi les repas n ont-ils pas ete proposes ?',
        'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle air-udgifter dokumenteres senere i backend.' => 'Nous enregistrons uniquement que les repas ou rafraichissements n ont pas ete proposes. Les frais air eventuels seront documentes plus tard dans le back-office.',
        'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle udgifter dokumenteres senere i backend.' => 'Nous enregistrons uniquement que les repas ou rafraichissements n ont pas ete proposes. Les frais eventuels seront documentes plus tard dans le back-office.',
        'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle faergeudgifter dokumenteres senere i backend.' => 'Nous enregistrons uniquement que les repas ou rafraichissements n ont pas ete proposes. Les frais ferry eventuels seront documentes plus tard dans le back-office.',
        'Foreloebigt beloeb (valgfrit)' => 'Montant provisoire (facultatif)',
        'Valuta' => 'Devise',
        'Hvis du selv betalte: beloeb' => 'Si vous avez paye vous-meme : montant',
        'Hotel og overnatning' => 'Hotel et hebergement',
        'Er den nye forventede afgang foerst dagen efter den planlagte afgang?' => 'Le nouveau depart prevu est-il seulement le jour suivant le depart initialement prevu ?',
        'Var den nye forventede afgang foerst dagen efter den planlagte afgang?' => 'Le nouveau depart prevu etait-il seulement le jour suivant le depart initialement prevu ?',
        'Hotel og indkvartering er kun relevant i air-sporet, hvis den nye afgang foerst blev dagen efter.' => 'L hotel et l hebergement ne sont pertinents dans le parcours air que si le nouveau depart a lieu seulement le jour suivant.',
        'Blev hotel eller indkvartering tilbudt?' => 'Un hotel ou un hebergement a-t-il ete propose ?',
        'Indgik transport til hotellet?' => 'Le transport vers l hotel etait-il inclus ?',
        'Vi registrerer kun at lokal transport ikke var inkluderet. Selve air-udgifterne samles senere i backend-sagen.' => 'Nous enregistrons uniquement que le transport local n etait pas inclus. Les frais air eux-memes seront collectes plus tard dans le dossier back-office.',
        'Vi registrerer kun at lokal transport ikke var inkluderet. Selve udgifterne samles senere i backend-sagen.' => 'Nous enregistrons uniquement que le transport local n etait pas inclus. Les frais eux-memes seront collectes plus tard dans le dossier back-office.',
        'Vi registrerer kun at lokal transport ikke var inkluderet. Selve faergeudgifterne samles senere i backend-sagen.' => 'Nous enregistrons uniquement que le transport local n etait pas inclus. Les frais ferry eux-memes seront collectes plus tard dans le dossier back-office.',
        'Transport til/fra hotel - beloeb' => 'Transport vers / depuis l hotel - montant',
        'Var overnatning noedvendig?' => 'Une nuitee etait-elle necessaire ?',
        'Vi registrerer kun at hotel eller indkvartering ikke blev tilbudt, selvom den nye afgang foerst var dagen efter. Beloeb, valuta og naetter registreres senere i backend.' => 'Nous enregistrons uniquement qu aucun hotel ou hebergement n a ete propose, meme si le nouveau depart n etait que le lendemain. Le montant, la devise et le nombre de nuits seront enregistres plus tard dans le back-office.',
        'Vi registrerer kun at overnatning blev noedvendig og ikke tilbudt. Beloeb, valuta og naetter registreres senere i backend.' => 'Nous enregistrons uniquement qu une nuitee etait necessaire et n a pas ete proposee. Le montant, la devise et le nombre de nuits seront enregistres plus tard dans le back-office.',
        'Foreloebigt hotelbeloeb (valgfrit)' => 'Montant provisoire de l hotel (facultatif)',
        'Hotel - beloeb' => 'Hotel - montant',
        'Antal naetter' => 'Nombre de nuits',
        'PMR / saerlige hensyn' => 'PMR / besoins particuliers',
        'Blev prioriteret assistance anvendt?' => 'Une assistance prioritaire a-t-elle ete appliquee ?',
        'Blev ledsager eller servicehund understoettet, naar det var relevant?' => 'L accompagnateur ou le chien d assistance a-t-il ete pris en charge quand c etait pertinent ?',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Ikke relevant' => 'Non pertinent',
        'Vaelg' => 'Choisir',
    ],
];
$translateAssistance = static function ($text) use ($uiLanguage, $assistanceTranslations) {
    if (!is_string($text)) {
        return $text;
    }

    return $assistanceTranslations[$uiLanguage][$text] ?? $text;
};
$art20Active = (bool)($art20Active ?? false);
$art20Partial = (bool)($art20Partial ?? false);
$art20Blocked = (bool)($art20Blocked ?? false);
$busPmrAssistGateActive = (bool)($busPmrAssistGateActive ?? false);
$busPmrAssistPartialActive = (bool)($busPmrAssistPartialActive ?? false);

$v = static fn(string $key, string $fallback = ''): string => (string)($form[$key] ?? $fallback);

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Ticketless + pris',
    3 => 'Rejseoplysninger',
    4 => 'Vaelg afgang + rail-vurdering',
    5 => 'Incident',
    6 => 'Spor efter incident',
    7 => 'Refusion / Omlaegning',
    8 => 'Mad og Hotel',
    9 => 'Nedgradering',
    10 => 'Kompensation',
];
$steps = array_map($translateAssistance, $steps);
$currentStep = (int)($currentStep ?? 8);
$doneSteps = $doneSteps ?? [];
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $backUrl ?? $this->Url->build(['action' => (is_string($flowPrevAction ?? null) && $flowPrevAction !== '' ? $flowPrevAction : 'remedies'), '?' => $flowQuery]);
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ($uiLanguage === 'fr' ? ' etapes' : ' trin')));
$stats = $stats ?? [];

$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$isRail = $transportMode === '' || $transportMode === 'rail';
$isAir = $transportMode === 'air';
$isFerry = $transportMode === 'ferry';
$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';
$captureAssistanceExpensesInBackend = $isRail || $isAir || $isFerry;
$railAvailableAssistance = (array)($railPostIncident['available_assistance'] ?? []);
$railEnabledAssistance = (array)($railPostIncident['enabled_assistance'] ?? []);
$shouldFilterRailAssistance = $isRail && ($railAvailableAssistance !== [] || $railEnabledAssistance !== []);
$showRailMealSection = !$shouldFilterRailAssistance
    || (
        (!array_key_exists('meal_expenses', $railAvailableAssistance) || !empty($railAvailableAssistance['meal_expenses']))
        && (!array_key_exists('meal_expenses', $railEnabledAssistance) || !empty($railEnabledAssistance['meal_expenses']))
    );
$showRailHotelSection = !$shouldFilterRailAssistance
    || (
        (!array_key_exists('hotel_expenses', $railAvailableAssistance) || !empty($railAvailableAssistance['hotel_expenses']))
        && (!array_key_exists('hotel_expenses', $railEnabledAssistance) || !empty($railEnabledAssistance['hotel_expenses']))
    );
$showAirMealSection = true;
$showAirHotelSection = true;
$showFerryMealSection = true;
$showFerryHotelSection = true;
$airPostIncidentAssistanceTypes = [];
if ($isAir) {
    $airPostIncidentAssistanceTypesRaw = $form['air_post_incident_assistance_types'] ?? null;
    if (is_array($airPostIncidentAssistanceTypesRaw)) {
        $airPostIncidentAssistanceTypes = array_values(array_unique(array_values(array_filter(array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            $airPostIncidentAssistanceTypesRaw
        ), static fn(string $value): bool => in_array($value, ['meals', 'hotel'], true)))));
        sort($airPostIncidentAssistanceTypes);
        $showAirMealSection = in_array('meals', $airPostIncidentAssistanceTypes, true);
        $showAirHotelSection = in_array('hotel', $airPostIncidentAssistanceTypes, true);
    }
}
$ferryPostIncidentAssistanceTypes = [];
if ($isFerry) {
    $ferryPostIncidentAssistanceTypesRaw = $form['ferry_post_incident_assistance_types'] ?? null;
    if (is_array($ferryPostIncidentAssistanceTypesRaw)) {
        $ferryPostIncidentAssistanceTypes = array_values(array_unique(array_values(array_filter(array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            $ferryPostIncidentAssistanceTypesRaw
        ), static fn(string $value): bool => in_array($value, ['meals', 'hotel'], true)))));
        sort($ferryPostIncidentAssistanceTypes);
        $showFerryMealSection = in_array('meals', $ferryPostIncidentAssistanceTypes, true);
        $showFerryHotelSection = in_array('hotel', $ferryPostIncidentAssistanceTypes, true);
    }
}
$showMealSection = $isAir ? $showAirMealSection : ($isFerry ? $showFerryMealSection : $showRailMealSection);
$showHotelSection = $isAir ? $showAirHotelSection : ($isFerry ? $showFerryHotelSection : $showRailHotelSection);
$pmrUser = strtolower((string)($form['pmr_user'] ?? ($flags['pmr_user'] ?? ''))) === 'yes';
$showPmrSection = $pmrUser || $busPmrAssistGateActive || $busPmrAssistPartialActive;

if (!$isRail) {
    $steps = [
        1 => 'Billet, grunddata',
        2 => $isAir ? 'Vaelg fly' : 'Vaelg afgang',
        3 => 'Haendelse',
        4 => 'Valg efter haendelsen',
        5 => $isAir ? 'Refund, ombooking' : 'Refusion, ombooking',
        6 => 'Assistance',
        7 => $isAir ? 'Nedgradering' : 'Kontakt, opret sag',
        8 => $isAir ? 'Kontakt & opret sag' : '',
    ];
    if ($isFerry) {
        unset($steps[8]);
        $currentStep = 6;
    } else {
        $currentStep = $isAir ? 6 : 5;
    }
    if (!$isAir && !$isFerry) {
        $steps = [
            1 => 'Billet, grunddata',
            2 => 'Vaelg afgang',
            3 => 'Haendelse',
            4 => 'Refusion, ombooking',
            5 => 'Assistance',
            6 => 'Resultat',
        ];
        $currentStep = 5;
    }
    if (!$isAir && !$isFerry) {
        unset($steps[7], $steps[8]);
    } elseif ($isFerry) {
        unset($steps[8]);
    } else {
        // air keeps all steps
    }
    $progressPct = (int)(count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0);
    $progressLabel = $currentStep . ' / ' . count($steps) . ' trin';
    $steps = array_map($translateAssistance, $steps);
}

$mealOffered = strtolower($v('meal_offered', ''));
$mealReason = $v('assistance_meals_unavailable_reason', '');
$hotelOffered = strtolower($v('hotel_offered', ''));
$airNextDayDeparture = strtolower($v('air_next_day_departure', ''));
$overnightNeeded = strtolower($v('overnight_needed', ''));
$hotelTransportIncluded = strtolower($v('assistance_hotel_transport_included', ''));
$pmrPriority = strtolower($v('assistance_pmr_priority_applied', ''));
$pmrCompanion = strtolower($v('assistance_pmr_companion_supported', ''));

$mealAmount = $v('meal_self_paid_amount', '');
$mealCurrency = strtoupper($v('meal_self_paid_currency', 'EUR'));
$railLiveMealAmount = $v('rail_live_meal_amount', '');
$railLiveMealCurrency = strtoupper($v('rail_live_meal_currency', 'EUR'));
$hotelAmount = $v('hotel_self_paid_amount', '');
$hotelCurrency = strtoupper($v('hotel_self_paid_currency', 'EUR'));
$railLiveHotelAmount = $v('rail_live_hotel_amount', '');
$railLiveHotelCurrency = strtoupper($v('rail_live_hotel_currency', 'EUR'));
$hotelNights = $v('hotel_self_paid_nights', '');
$hotelTransportAmount = $v('hotel_transport_self_paid_amount', '');
$hotelTransportCurrency = strtoupper($v('hotel_transport_self_paid_currency', 'EUR'));

$mealReasons = [
    'not_available' => 'Ikke til raadighed',
    'unreasonable_terms' => 'Urimelige vilkaar',
    'closed' => 'Lukket',
    'other' => 'Andet',
];
$currencies = ['EUR', 'DKK', 'SEK', 'NOK', 'GBP', 'CHF', 'BGN', 'CZK', 'HUF', 'PLN', 'RON'];

$hintFor = static function (string $key) use ($priceHints): string {
    if (!is_array($priceHints)) {
        return '';
    }
    $hint = $priceHints[$key] ?? null;
    if (!is_array($hint) || !isset($hint['min'], $hint['max'], $hint['currency'])) {
        return '';
    }

    $min = number_format((float)$hint['min'], 0, ',', '.');
    $max = number_format((float)$hint['max'], 0, ',', '.');

    return 'Typisk interval: ' . $min . '-' . $max . ' ' . $hint['currency'];
};

$mealHint = $hintFor('meals');
$hotelHint = $hintFor('hotelPerNight');
$taxiHint = $hintFor('taxi');
$airExpenseReview = [];
$airExpenseItems = [];
$airExpenseLocationDisplay = '';
$airExpenseZone = '';
$airMealsHint = '';
$airHotelHint = '';
$airTransportHint = '';
if ($isAir) {
    $airExpenseReview = (new \App\Service\Air\AirExpenseReviewBandService())->availableBandsForScope([
        'scope' => 'air_assistance_scope',
        'form' => $form,
        'flags' => $flags,
        'meta' => $meta,
    ]);
    foreach ((array)($airExpenseReview['items'] ?? []) as $airExpenseItem) {
        if (!is_array($airExpenseItem)) {
            continue;
        }
        $airExpenseItems[(string)($airExpenseItem['internal_category'] ?? '')] = $airExpenseItem;
    }
    $airExpenseLocationDisplay = trim((string)($airExpenseReview['expense_airport_iata'] ?? ($airExpenseReview['expense_airport_label'] ?? '')));
    $airExpenseCountryCode = strtoupper(trim((string)($airExpenseReview['expense_country_code'] ?? '')));
    if ($airExpenseLocationDisplay !== '' && $airExpenseCountryCode !== '') {
        $airExpenseLocationDisplay .= ', ' . $airExpenseCountryCode;
    }
    $airExpenseZone = strtolower(trim((string)($airExpenseReview['airport_cost_zone'] ?? '')));
    $airRangeText = static function (?array $item): string {
        if (!is_array($item)) {
            return '';
        }
        $min = isset($item['min']) ? (int)$item['min'] : 0;
        $max = isset($item['max']) ? (int)$item['max'] : 0;
        if ($min <= 0 && $max <= 0) {
            return '';
        }

        return $min . '-' . $max . ' EUR';
    };
    $airPrefix = $airExpenseLocationDisplay !== ''
        ? ('Vejledende cap for ' . $airExpenseLocationDisplay . ($airExpenseZone !== '' ? ' (' . $airExpenseZone . ')' : '') . ': ')
        : 'Vejledende cap: ';
    $airMealsRange = $airRangeText($airExpenseItems['assistance_meals'] ?? null);
    $airHotelRange = $airRangeText($airExpenseItems['assistance_hotel'] ?? null);
    $airTransportRange = $airRangeText($airExpenseItems['assistance_hotel_airport_transfer'] ?? null);
    $airMealsHint = $airMealsRange !== '' ? ($airPrefix . 'maaltider / forfriskninger ' . $airMealsRange . '.') : '';
    $airHotelHint = $airHotelRange !== '' ? ($airPrefix . 'hotel / indkvartering ' . $airHotelRange . '.') : '';
    $airTransportHint = $airTransportRange !== '' ? ($airPrefix . 'transport mellem lufthavn og hotel ' . $airTransportRange . '.') : '';
}
$pageTitle = $isAir ? 'Care under rejsen' : ($isFerry ? 'Assistance under sejladsen' : 'Mad og Hotel');
$pageSubtitle = $isAir
    ? 'Afklar kun om flyselskabet tilbod maaltider, hotel eller anden care. Eventuelle air-udgifter registreres senere i backend-sagen.'
    : ($isFerry
        ? 'Afklar kun om operatoeren tilbod forplejning, hotel eller anden assistance. Eventuelle faergeudgifter registreres senere i backend-sagen.'
        : 'Dette rail-trin bruger samme station-scope som strandet paa station: maaltider, hotel og lokal transport mellem station og hotel. Spor-transport og omlaegning hoerer ikke til her.');
$selectedDeparture = (array)($meta['rail_selected_departure'] ?? []);
$operatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $selectedDeparture['operator_name'] ?? null,
    $selectedDeparture['operator'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
    $form['operating_carrier'] ?? null,
    $form['marketing_carrier'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $operatorLabel = $candidateOperator;
        break;
    }
}
if ($operatorLabel === '') {
    $operatorLabel = $translateAssistance('Ikke valgt endnu');
}

$remedyChoice = trim((string)($form['remedyChoice'] ?? ''));
$remedyGateActive = ((string)($flags['gate_art18'] ?? '')) === '1';
$remedySummary = match ($remedyChoice) {
    'refund_return' => $translateAssistance('Tilbagebetaling'),
    'reroute_soonest' => $translateAssistance('Ombooking hurtigst muligt'),
    'reroute_later' => $translateAssistance('Ombooking senere'),
    'no_real_choice' => $translateAssistance('Intet reelt valg'),
    default => $translateAssistance($remedyGateActive ? 'Aktiv' : 'Afventer'),
};
$remedySummaryBadge = $remedyChoice !== ''
    ? 'blue'
    : ($remedyGateActive ? 'green' : 'gray');

$assistanceSummary = $art20Active
    ? $translateAssistance('Aktiv')
    : ($art20Partial ? $translateAssistance('Delvis') : (($mealOffered !== '' || $hotelOffered !== '' || $pmrPriority !== '' || $pmrCompanion !== '') ? $translateAssistance('Aktiv') : $translateAssistance('Afventer')));
$assistanceSummaryBadge = $art20Active
    ? 'green'
    : ($art20Partial ? 'amber' : (($mealOffered !== '' || $hotelOffered !== '' || $pmrPriority !== '' || $pmrCompanion !== '') ? 'green' : 'gray'));

$summaryRows = [
    [$translateAssistance('Refusion / ombooking'), $remedySummary, $remedySummaryBadge],
    [$translateAssistance('Assistance'), $assistanceSummary, $assistanceSummaryBadge],
    [$translateAssistance('Operatoer'), $operatorLabel, null],
];

if ($stats === []) {
    $stats = [
        [$translateAssistance('Flow'), $translateAssistance($isRail ? 'Afsluttet rejse' : 'Completed disruption'), null],
        ['Transport', strtoupper($isRail ? 'rail' : $transportMode), null],
    ];
}

if ($isRail && isset($stats[0][0], $stats[0][1]) && $stats[0][0] === 'Flow') {
    $stats[0][0] = $translateAssistance('Flow');
    $stats[0][1] = match ($travelState) {
        'ongoing' => $translateAssistance('Igangvaerende rejse'),
        'before_start' => $translateAssistance('Foer afgang'),
        default => $translateAssistance('Afsluttet rejse'),
    };
}

$nextHint = '';

ob_start();
?>
<?= $this->Form->create(null, [
    'url' => ['action' => 'assistance', '?' => $flowQuery],
    'id' => 'tc6-assistance-form',
    'class' => 'tc6-form',
    'novalidate' => true,
]) ?>
<div class="tc6-assistance">
<?php if ($captureAssistanceExpensesInBackend): ?>
<style>
  .tc6-assistance > .tc6-subtitle,
  .tc6-assistance .tc6-note:not(.tc6-note--keep),
  .tc6-assistance .tc6-small.tc6-muted,
  .tc6-assistance .tc6-step-copy {
    display: none !important;
  }
</style>
<?php endif; ?>

<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1"><?= h($pageTitle) ?></h1>
<p class="tc6-subtitle"><?= h($pageSubtitle) ?></p>
<?php if ($isRail): ?>
<?php endif; ?>

<div x-data='{
  mealOffered: <?= json_encode($mealOffered, JSON_UNESCAPED_UNICODE) ?>,
  hotelOffered: <?= json_encode($hotelOffered, JSON_UNESCAPED_UNICODE) ?>,
  airNextDayDeparture: <?= json_encode($airNextDayDeparture, JSON_UNESCAPED_UNICODE) ?>,
  overnightNeeded: <?= json_encode($overnightNeeded, JSON_UNESCAPED_UNICODE) ?>,
  hotelTransportIncluded: <?= json_encode($hotelTransportIncluded, JSON_UNESCAPED_UNICODE) ?>,
  pmrPriority: <?= json_encode($pmrPriority, JSON_UNESCAPED_UNICODE) ?>,
  pmrCompanion: <?= json_encode($pmrCompanion, JSON_UNESCAPED_UNICODE) ?>
}'>

  <?php if ($captureAssistanceExpensesInBackend): ?>
    <?= $this->Form->hidden('meal_self_paid_amount', ['value' => $mealAmount]) ?>
    <?= $this->Form->hidden('meal_self_paid_currency', ['value' => $mealCurrency]) ?>
    <?= $this->Form->hidden('hotel_self_paid_amount', ['value' => $hotelAmount]) ?>
    <?= $this->Form->hidden('hotel_self_paid_currency', ['value' => $hotelCurrency]) ?>
    <?= $this->Form->hidden('hotel_self_paid_nights', ['value' => $hotelNights]) ?>
    <?= $this->Form->hidden('hotel_transport_self_paid_amount', ['value' => $hotelTransportAmount]) ?>
    <?= $this->Form->hidden('hotel_transport_self_paid_currency', ['value' => $hotelTransportCurrency]) ?>
  <?php endif; ?>

  <?php if ($art20Partial): ?>
    <div class="tc6-note tc6-note--amber">
      Assistance er kun delvist aktiveret. Registrer kun de dele, der faktisk blev relevante i situationen.
    </div>
  <?php elseif (!$art20Active): ?>
    <div class="tc6-note tc6-note--amber">
      <?= $art20Blocked
          ? 'Assistance ser ikke ud til at vaere aktiv i den nuvaerende sag. Hvis du fortsaetter, er det stadig CakePHP der afgoer den endelige vurdering.'
          : 'Assistance afventer stadig de endelige gates. Visningen her er derfor kun et tyndt tc6-lag oven paa den normale Cake-logik.' ?>
    </div>
  <?php endif; ?>

  <?php if ($captureAssistanceExpensesInBackend): ?>
    <div class="tc6-note tc6-note--blue">
      <?= $isAir
          ? 'Air-sporet afklarer kun om care blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.'
          : ($isRail
              ? 'Rail-sporet afklarer kun om assistance blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.'
              : 'Faergesporet afklarer kun om assistance blev tilbudt eller manglede. Dokumentation og konkrete udgifter registreres senere i backend-sagen.') ?>
    </div>
  <?php elseif ($mealHint !== '' || $hotelHint !== '' || $taxiHint !== ''): ?>
    <div class="tc6-note tc6-note--blue">
      Vejledende niveauer:
      <?php if ($mealHint !== ''): ?><span><?= h($mealHint) ?></span><?php endif; ?>
      <?php if ($hotelHint !== ''): ?><span><?= $mealHint !== '' ? ' | ' : '' ?><?= h($hotelHint) ?></span><?php endif; ?>
      <?php if ($taxiHint !== ''): ?><span><?= ($mealHint !== '' || $hotelHint !== '') ? ' | ' : '' ?><?= h($taxiHint) ?></span><?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($showMealSection): ?>
  <div class="tc6-card">
    <div class="tc6-section-label">Maaltider og forfriskninger</div>
    <?php if ($isAir && $airMealsHint !== ''): ?>
    <div class="tc6-note tc6-note--blue tc6-note--keep" style="margin-bottom:14px;">
      <?= h($airMealsHint) ?>
    </div>
    <?php endif; ?>
    <div class="tc6-label" style="margin-bottom:14px">Blev maaltider eller forfriskninger tilbudt?</div>
    <div class="tc6-choice-cards tc6-choice-cards--2">
      <label class="tc6-choice-card">
        <input type="radio" name="meal_offered" value="yes" x-model="mealOffered" <?= $mealOffered === 'yes' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
      </label>
      <label class="tc6-choice-card">
        <input type="radio" name="meal_offered" value="no" x-model="mealOffered" <?= $mealOffered === 'no' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
      </label>
    </div>

    <div class="<?= $captureAssistanceExpensesInBackend ? 'tc6-field-grid--2' : 'tc6-field-grid--3' ?>" x-show="mealOffered === 'no'" x-cloak style="margin-top:14px">
      <div class="tc6-field">
        <label class="tc6-label">Hvorfor blev maaltider ikke tilbudt?</label>
        <select class="tc6-select" name="assistance_meals_unavailable_reason">
          <option value="">Vaelg</option>
          <?php foreach ($mealReasons as $value => $label): ?>
            <option value="<?= h($value) ?>" <?= $mealReason === $value ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($captureAssistanceExpensesInBackend): ?>
      <div class="tc6-note tc6-note--blue" style="margin-bottom:0;">
        <?= $isAir
            ? 'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle air-udgifter dokumenteres senere i backend.'
            : ($isRail
                ? 'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle udgifter dokumenteres senere i backend.'
                : 'Vi registrerer kun at maaltider eller forfriskninger ikke blev tilbudt. Eventuelle faergeudgifter dokumenteres senere i backend.') ?>
      </div>
      <?php if ($isRail && $isOngoing): ?>
      <div class="tc6-field-grid--2" style="margin-top:14px">
        <div class="tc6-field">
          <label class="tc6-label">Foreloebigt beloeb (valgfrit)</label>
          <input type="number" step="0.01" class="tc6-input" name="rail_live_meal_amount" value="<?= h($railLiveMealAmount) ?>" />
        </div>
        <div class="tc6-field">
          <label class="tc6-label">Valuta</label>
          <select class="tc6-select" name="rail_live_meal_currency">
            <?php foreach ($currencies as $currency): ?>
              <option value="<?= h($currency) ?>" <?= $railLiveMealCurrency === $currency ? 'selected' : '' ?>><?= h($currency) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="tc6-field">
        <label class="tc6-label">Hvis du selv betalte: beloeb</label>
        <input type="number" step="0.01" class="tc6-input" name="meal_self_paid_amount" value="<?= h($mealAmount) ?>" />
      </div>
      <div class="tc6-field">
        <label class="tc6-label">Valuta</label>
        <select class="tc6-select" name="meal_self_paid_currency">
          <?php foreach ($currencies as $currency): ?>
            <option value="<?= h($currency) ?>" <?= $mealCurrency === $currency ? 'selected' : '' ?>><?= h($currency) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showHotelSection): ?>
  <div class="tc6-card">
    <div class="tc6-section-label">Hotel og overnatning</div>
    <?php if ($isAir): ?>
    <div class="tc6-field" style="margin-bottom:14px">
      <div class="tc6-label"><?= $isOngoing ? 'Er den nye forventede afgang foerst dagen efter den planlagte afgang?' : 'Var den nye forventede afgang foerst dagen efter den planlagte afgang?' ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="air_next_day_departure" value="yes" x-model="airNextDayDeparture" <?= $airNextDayDeparture === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="air_next_day_departure" value="no" x-model="airNextDayDeparture" <?= $airNextDayDeparture === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
        </label>
      </div>
    </div>
    <div class="tc6-note tc6-note--blue" x-show="airNextDayDeparture === 'no'" x-cloak style="margin-bottom:14px;">
      Hotel og indkvartering er kun relevant i air-sporet, hvis den nye afgang foerst blev dagen efter.
    </div>
    <?php endif; ?>

    <div x-show="<?= $isAir ? 'airNextDayDeparture === \'yes\'' : 'true' ?>" x-cloak>
    <?php if ($isAir && $airHotelHint !== ''): ?>
    <div class="tc6-note tc6-note--blue tc6-note--keep" style="margin-bottom:14px;">
      <?= h($airHotelHint) ?>
    </div>
    <?php endif; ?>
    <div class="tc6-label" style="margin-bottom:14px">Blev hotel eller indkvartering tilbudt?</div>
    <div class="tc6-choice-cards tc6-choice-cards--3">
      <label class="tc6-choice-card">
        <input type="radio" name="hotel_offered" value="yes" x-model="hotelOffered" <?= $hotelOffered === 'yes' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
      </label>
      <label class="tc6-choice-card">
        <input type="radio" name="hotel_offered" value="no" x-model="hotelOffered" <?= $hotelOffered === 'no' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
      </label>
      <label class="tc6-choice-card">
        <input type="radio" name="hotel_offered" value="irrelevant" x-model="hotelOffered" <?= $hotelOffered === 'irrelevant' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ikke relevant</span></span>
      </label>
    </div>

    <div class="tc6-field" x-show="hotelOffered === 'yes'" x-cloak style="margin-top:14px">
      <div class="tc6-label">Indgik transport til hotellet?</div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="assistance_hotel_transport_included" value="yes" x-model="hotelTransportIncluded" <?= $hotelTransportIncluded === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="assistance_hotel_transport_included" value="no" x-model="hotelTransportIncluded" <?= $hotelTransportIncluded === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
        </label>
      </div>
    </div>

    <?php if ($captureAssistanceExpensesInBackend): ?>
    <?php if ($isAir && $airTransportHint !== ''): ?>
    <div class="tc6-note tc6-note--blue tc6-note--keep" x-show="hotelOffered === 'yes' || hotelOffered === 'no'" x-cloak style="margin-top:14px; margin-bottom:0;">
      <?= h($airTransportHint) ?>
    </div>
    <?php endif; ?>
    <div class="tc6-note tc6-note--blue" x-show="hotelOffered === 'yes' && hotelTransportIncluded === 'no'" x-cloak style="margin-top:14px; margin-bottom:0;">
      <?= $isAir
          ? 'Vi registrerer kun at lokal transport ikke var inkluderet. Selve air-udgifterne samles senere i backend-sagen.'
          : ($isRail
              ? 'Vi registrerer kun at lokal transport ikke var inkluderet. Selve udgifterne samles senere i backend-sagen.'
              : 'Vi registrerer kun at lokal transport ikke var inkluderet. Selve faergeudgifterne samles senere i backend-sagen.') ?>
    </div>
    <?php else: ?>
    <div class="tc6-field-grid--2" x-show="hotelOffered === 'yes' && hotelTransportIncluded === 'no'" x-cloak style="margin-top:14px">
      <div class="tc6-field">
        <label class="tc6-label">Transport til/fra hotel - beloeb</label>
        <input type="number" step="0.01" class="tc6-input" name="hotel_transport_self_paid_amount" value="<?= h($hotelTransportAmount) ?>" />
      </div>
      <div class="tc6-field">
        <label class="tc6-label">Valuta</label>
        <select class="tc6-select" name="hotel_transport_self_paid_currency">
          <?php foreach ($currencies as $currency): ?>
            <option value="<?= h($currency) ?>" <?= $hotelTransportCurrency === $currency ? 'selected' : '' ?>><?= h($currency) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$isAir): ?>
    <div class="tc6-field" x-show="hotelOffered === 'no'" x-cloak style="margin-top:14px">
      <div class="tc6-label">Var overnatning noedvendig?</div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="overnight_needed" value="yes" x-model="overnightNeeded" <?= $overnightNeeded === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="overnight_needed" value="no" x-model="overnightNeeded" <?= $overnightNeeded === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
        </label>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($captureAssistanceExpensesInBackend): ?>
    <div class="tc6-note tc6-note--blue" x-show="<?= $isAir ? 'hotelOffered === \'no\'' : 'hotelOffered === \'no\' && overnightNeeded === \'yes\'' ?>" x-cloak style="margin-top:14px; margin-bottom:0;">
      <?= $isAir
          ? 'Vi registrerer kun at hotel eller indkvartering ikke blev tilbudt, selvom den nye afgang foerst var dagen efter. Beloeb, valuta og naetter registreres senere i backend.'
          : 'Vi registrerer kun at overnatning blev noedvendig og ikke tilbudt. Beloeb, valuta og naetter registreres senere i backend.' ?>
    </div>
    <?php if ($isRail && $isOngoing): ?>
    <div class="tc6-field-grid--2" x-show="hotelOffered === 'no' && overnightNeeded === 'yes'" x-cloak style="margin-top:14px">
      <div class="tc6-field">
        <label class="tc6-label">Foreloebigt hotelbeloeb (valgfrit)</label>
        <input type="number" step="0.01" class="tc6-input" name="rail_live_hotel_amount" value="<?= h($railLiveHotelAmount) ?>" />
      </div>
      <div class="tc6-field">
        <label class="tc6-label">Valuta</label>
        <select class="tc6-select" name="rail_live_hotel_currency">
          <?php foreach ($currencies as $currency): ?>
            <option value="<?= h($currency) ?>" <?= $railLiveHotelCurrency === $currency ? 'selected' : '' ?>><?= h($currency) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="tc6-field-grid--3" x-show="<?= $isAir ? 'hotelOffered === \'no\'' : 'hotelOffered === \'no\' && overnightNeeded === \'yes\'' ?>" x-cloak style="margin-top:14px">
      <div class="tc6-field">
        <label class="tc6-label">Hotel - beloeb</label>
        <input type="number" step="0.01" class="tc6-input" name="hotel_self_paid_amount" value="<?= h($hotelAmount) ?>" />
      </div>
      <div class="tc6-field">
        <label class="tc6-label">Valuta</label>
        <select class="tc6-select" name="hotel_self_paid_currency">
          <?php foreach ($currencies as $currency): ?>
            <option value="<?= h($currency) ?>" <?= $hotelCurrency === $currency ? 'selected' : '' ?>><?= h($currency) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="tc6-field">
        <label class="tc6-label">Antal naetter</label>
        <input type="number" min="1" step="1" class="tc6-input" name="hotel_self_paid_nights" value="<?= h($hotelNights) ?>" />
      </div>
    </div>
    <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showPmrSection): ?>
    <div class="tc6-card">
      <div class="tc6-section-label">PMR / saerlige hensyn</div>
      <div class="tc6-field">
        <div class="tc6-label">Blev prioriteret assistance anvendt?</div>
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="assistance_pmr_priority_applied" value="yes" x-model="pmrPriority" <?= $pmrPriority === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="assistance_pmr_priority_applied" value="no" x-model="pmrPriority" <?= $pmrPriority === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
          </label>
        </div>
      </div>

      <div class="tc6-field" style="margin-top:14px">
        <div class="tc6-label">Blev ledsager eller servicehund understoettet, naar det var relevant?</div>
        <div class="tc6-choice-cards tc6-choice-cards--3">
          <label class="tc6-choice-card">
            <input type="radio" name="assistance_pmr_companion_supported" value="yes" x-model="pmrCompanion" <?= $pmrCompanion === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="assistance_pmr_companion_supported" value="no" x-model="pmrCompanion" <?= $pmrCompanion === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="assistance_pmr_companion_supported" value="not_applicable" x-model="pmrCompanion" <?= $pmrCompanion === 'not_applicable' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ikke relevant</span></span>
          </label>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?= $this->element('tc6/action_bar', [
    'backUrl' => $backUrl,
    'backLabel' => $translateAssistance('Tilbage'),
    'nextLabel' => $translateAssistance('Naeste trin'),
    'nextVariant' => 'navy',
    'submitName' => '_save',
]) ?>

</div>

<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'assistance', 'formSelector' => '#tc6-assistance-form']) ?>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($assistanceTranslations[$uiLanguage])) {
    $content = strtr($content, $assistanceTranslations[$uiLanguage]);
}

ob_start();
echo $this->element('tc6/live_estimate_router', [
    'form' => $form,
    'flags' => $flags,
    'meta' => $meta,
    'journey' => $journey ?? [],
]);
$liveEstimateHtml = trim((string)ob_get_clean());

$rightPanel = $this->element('tc6/right_rail_panel', [
    'compensation' => $compensation ?? null,
    'compRate' => $compRate ?? null,
    'compBase' => $compBase ?? null,
    'progressPct' => $progressPct,
    'progressLabel' => $progressLabel,
    'stats' => $stats,
    'summaryRows' => $summaryRows,
    'nextHint' => $nextHint,
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel') + [
    'context' => match ($travelState) {
        'ongoing' => $translateAssistance('Igangvaerende rejse'),
        'before_start' => $translateAssistance('Foer afgang'),
        default => $translateAssistance('Afsluttet rejse'),
    },
]);
