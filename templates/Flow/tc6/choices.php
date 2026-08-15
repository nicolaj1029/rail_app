<?php
/**
 * TC6 rail choices step.
 *
 * Thin visual wrapper around FlowController::choices().
 * Keeps the legacy field names, wording and conditional flow.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$maps = $maps ?? [];
$priceHints = $priceHints ?? ($meta['price_hints'] ?? ($form['price_hints'] ?? []));
$railPostIncident = is_array($railPostIncident ?? null) ? (array)$railPostIncident : [];
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$choicesTranslations = [
    'fr' => [
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Flow' => 'Parcours',
        'Afsluttet rejse' => 'Voyage termine',
        'Igangvaerende rejse' => 'Voyage en cours',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatoer' => 'Operateur',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Trin ' => 'Etape ',
        'Hvad blev relevant efter incident?' => 'Qu est-ce qui est devenu pertinent apres l incident ?',
        'Valg efter incident' => 'Choix apres incident',
        'Transport og spor efter incident' => 'Transport et suites apres incident',
        'Transport til/fra strandet sted' => 'Transport depuis/vers le lieu de blocage',
        'Live valg efter incident' => 'Choix en direct apres l incident',
        'Spor efter incident' => 'Parcours apres incident',
        'Refusion / Omlaegning' => 'Remboursement / reacheminement',
        'Mad og Hotel' => 'Repas et hotel',
        'Kompensation' => 'Indemnisation',
        'Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.' => 'Statut : le voyage est termine. Repondez selon ce qui s est reellement passe.',
        'Status: Rejsen er i gang. Det her trin styrer kun hvilke efterfoelgende trin, der aabner. Live-overblikket ligger i hoejrepanelet.' => 'Statut : le voyage est en cours. Cette etape sert seulement a ouvrir les etapes suivantes pertinentes. La vue en direct se trouve dans le panneau de droite.',
        'Status: Rejsen er endnu ikke paabegyndt. Besvar ud fra, hvad du forventer at goere ved forsinkelse/aflysning.' => 'Statut : le voyage n a pas encore commence. Repondez selon ce que vous pensez faire en cas de retard ou d annulation.',
        'Valgte spor efter incident' => 'Parcours selectionnes apres l incident',
        'Vaelg de spor der faktisk blev relevante' => 'Choisissez les parcours qui ont reellement ete pertinents',
        'Det her er kun en router. Vaelg det der faktisk skete efter incident, saa aabner vi kun de relevante efterfoelgende trin.' => 'Ce bloc sert seulement de routeur. Choisissez ce qui s est reellement passe apres l incident afin de n ouvrir que les etapes suivantes pertinentes.',
        'Refusion / videre rejse' => 'Remboursement / poursuite du voyage',
        'Jeg opgav rejsen / skulle tilbage' => 'J ai abandonne le voyage / je devais retourner',
        'Jeg ville videre hurtigst muligt' => 'Je voulais poursuivre au plus vite',
        'Jeg ville rejse videre senere' => 'Je voulais poursuivre plus tard',
        'Strandet' => 'Bloque',
        'Jeg var strandet paa en station' => 'J etais bloque dans une gare',
        'Jeg sad fast i toget / paa sporet' => 'J etais bloque dans le train / sur la voie',
        'Udgifter' => 'Frais',
        'Jeg havde udgifter til mad og drikke' => 'J ai eu des frais de repas et de boissons',
        'Jeg havde udgifter til hotel / overnatning' => 'J ai eu des frais d hotel / d hebergement',
        'Hvad er planen lige nu?' => 'Quel est le plan en ce moment ?',
        'Det her er kun dit live-overblik. Selve Art. 18-valget bekraefter du stadig i naeste trin.' => 'Ce bloc n est qu une vue en direct. Le choix au titre de l art. 18 est toujours confirme a l etape suivante.',
        'Jeg vil videre hurtigst muligt' => 'Je veux poursuivre au plus vite',
        'Jeg vil rejse videre senere' => 'Je veux poursuivre plus tard',
        'Jeg opgiver rejsen / skal tilbage' => 'J abandonne le voyage / je dois retourner',
        'Jeg har ikke besluttet mig endnu' => 'Je n ai pas encore decide',
        'Hvilket live-spor passer lige nu?' => 'Quel parcours en direct correspond a la situation actuelle ?',
        'Incident er skaeringspunktet. Vaelg kun hvor passageren er lige nu. Selve station/spor-detaljerne udfyldes i det naeste trin.' => 'L incident est le point de bascule. Choisissez seulement ou se trouve actuellement le passager. Les details gare / voie sont renseignes a l etape suivante.',
        'Jeg er strandet paa en station' => 'Je suis bloque dans une gare',
        'Ingen af de to er aktive nu' => 'Aucun des deux n est actif actuellement',
        'Hvilken assistance er relevant nu?' => 'Quelle assistance est pertinente maintenant ?',
        'Vaelg kun det, der skal aabne i assistance-trinnet. Detaljespoergsmaalene ligger i de efterfoelgende trin.' => 'Choisissez uniquement ce qui doit ouvrir dans l etape d assistance. Les questions detaillees se trouvent dans les etapes suivantes.',
        'Mad og drikke' => 'Repas et boissons',
        'Hotel / overnatning' => 'Hotel / hebergement',
        'Vejledende rail-niveauer (ikke faste juridiske caps)' => 'Niveaux rail indicatifs (pas des plafonds juridiques fixes)',
        'Hvis du selv maa finde akut videre transport fra banen, saa hold dig til noedvendige og rimelige loesninger. Dokumentation og endelig vurdering sker senere i backend-sagen.' => 'Si vous devez trouver vous-meme un transport d urgence pour poursuivre votre trajet, restez sur des solutions necessaires et raisonnables. La documentation et l evaluation finale seront traitees plus tard dans le dossier back-office.',
        'TRIN 5 - Er du strandet? (igangvaerende rejse)' => 'ETAPE 5 - Etes-vous bloque ? (voyage en cours)',
        'TRIN 5 - Er du strandet? (afsluttet rejse)' => 'ETAPE 5 - Etiez-vous bloque ? (voyage termine)',
        'TRIN 5 - Er du strandet? (rejsen starter senere)' => 'ETAPE 5 - Serez-vous bloque ? (voyage plus tard)',
        'TRIN 5 - Er du strandet? (Art. 20)' => 'ETAPE 5 - Etes-vous bloque ? (art. 20)',
        'Alternativ transport skulle tilbydes, hvis du blev strandet pga. aflysning/forsinkelse.' => 'Un transport alternatif devait etre propose si vous avez ete bloque en raison d une annulation ou d un retard.',
        'Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.' => 'Un transport alternatif doit etre propose si vous etes bloque en raison d une annulation ou d un retard.',
        ' (Udfyld det, der er sket indtil nu).' => ' (indiquez ce qui s est passe jusqu a present).',
        'Sad du fast i et tog paa sporet (Art.20(2)(c))?' => 'Etiez-vous bloque dans un train sur la voie (art. 20(2)(c)) ?',
        'Sidder du fast i et tog paa sporet (Art.20(2)(c))?' => 'Etes-vous bloque dans un train sur la voie (art. 20(2)(c)) ?',
        'Du har markeret, at du sad fast i toget eller paa sporet. Udfyld kun det der faktisk skete.' => 'Vous avez indique que vous etiez bloque dans le train ou sur la voie. Renseignez uniquement ce qui s est reellement passe.',
        'Du har valgt sporet som aktiv live-haendelse. Udfyld hvad der sker lige nu og hvordan du kom videre.' => 'Vous avez choisi la voie comme incident actif en direct. Renseignez ce qui se passe maintenant et comment vous avez pu poursuivre.',
        'Ruter (Google Maps, valgfrit)' => 'Itineraires (Google Maps, optionnel)',
        'Transport til/fra (Art.20)' => 'Transport vers / depuis (art. 20)',
        'Brug Google Maps i denne sag' => 'Utiliser Google Maps dans ce dossier',
        'Hent forslag' => 'Recuperer des suggestions',
        'Aabn i Google Maps' => 'Ouvrir dans Google Maps',
        'Klik for at hente forslag til omlaegning. Vi sender start/destination til Google for at finde ruter.' => 'Cliquez pour recuperer des suggestions de reacheminement. Nous envoyons le depart et la destination a Google pour trouver des itineraires.',
        'Fra (station)' => 'Depuis (gare)',
        'Tip: Brug missed connection / din nuvaerende station som start.' => 'Conseil : utilisez la correspondance manquee ou votre gare actuelle comme point de depart.',
        'Til (destination)' => 'Vers (destination)',
        'Hentes fra billetten (destination).' => 'Recupere depuis le billet (destination).',
        'Bemaerk: Google Routes er ikke konfigureret (mangler server API key).' => 'Remarque : Google Routes n est pas configure (cle API serveur manquante).',
        'Seneste forslag (gemt i session):' => 'Dernieres suggestions (enregistrees en session) :',
        'Tog' => 'Train',
        'Letbane' => 'Tramway',
        'Faerge' => 'Ferry',
        'Blev der stillet transport til raadighed for at komme vaek/videre?' => 'Un transport a-t-il ete mis a disposition pour partir ou poursuivre ?',
        'Ikke relevant / andet' => 'Non pertinent / autre',
        'Hvad gjorde du saa?' => 'Qu avez-vous fait ensuite ?',
        'Blev evakueret senere' => 'J ai ete evacue plus tard',
        'Fandt selv transport' => 'J ai trouve moi-meme un transport',
        'Transporttype' => 'Type de transport',
        'Samkoersel/rideshare' => 'Covoiturage / rideshare',
        'Ventede til toget kunne koere videre' => 'J ai attendu que le train puisse repartir',
        'Fandt selv vej til station/spor' => 'J ai trouve moi-meme le chemin vers la gare / la voie',
        'Registrer kun transporttypen her. Beloeb, valuta og dokumentation indtastes senere i backend-sagen.' => 'Indiquez ici uniquement le type de transport. Le montant, la devise et la documentation seront saisis plus tard dans le dossier back-office.',
        'Foreloebigt beloeb flyttes til live-udgifter' => 'Le montant provisoire est reporte vers les frais live',
        'Hvis du allerede kender et foreloebigt beloeb for videre transport, saa afkryds "Selvbetalt videre transport" i kortet ovenfor og registrer beloebet der.' => 'Si vous connaissez deja un montant provisoire pour le transport ulterieur, cochez "Transport ulterieur paye par vous-meme" dans la carte ci-dessus et enregistrez le montant la.',
        'Hvor endte du?' => 'Ou etes-vous arrive ?',
        'Slutpunkt' => 'Point d arrivee',
        'Naermeste station' => 'Gare la plus proche',
        'Et andet afgangssted' => 'Un autre point de depart',
        'Mit endelige bestemmelsessted' => 'Ma destination finale',
        'Angiv station, saa vi kan bruge stedet videre i ombooking og vurdering.' => 'Indiquez la gare afin que nous puissions reutiliser ce lieu pour le reacheminement et l evaluation.',
        'Hvilken station endte du ved?' => 'Dans quelle gare etes-vous arrive ?',
        'Anden station' => 'Autre gare',
        '(ukendt sted)' => '(lieu inconnu)',
        'Station' => 'Gare',
        'Terminal' => 'Terminal',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Ved ikke' => 'Je ne sais pas',
        'Vaelg' => 'Choisir',
        'Andet' => 'Autre',
        'Vaelg start og destination foerst.' => 'Choisissez d abord le depart et la destination.',
        'Henter...' => 'Chargement...',
        'Fejl' => 'Erreur',
        'Typisk interval: ' => 'Fourchette indicative : ',
        'Alternativ videre transport' => 'Transport alternatif pour poursuivre',
        'Kunne ikke hente ruter.' => 'Impossible de recuperer les itineraires.',
        'Ingen forslag fundet.' => 'Aucune suggestion trouvee.',
        'Faergeterminal' => 'Terminal ferry',
        'Havn' => 'Port',
        'Stopested' => 'Arret',
    ],
];
$translateChoice = static function ($text) use ($uiLanguage, $choicesTranslations) {
    if (!is_string($text)) {
        return $text;
    }

    if (isset($choicesTranslations[$uiLanguage][$text])) {
        return $choicesTranslations[$uiLanguage][$text];
    }

    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $choicesTranslations[$uiLanguage][$decoded] ?? $text;
};

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';
$isBeforeStart = $travelState === 'before_start';
$isFutureLike = $isOngoing || $isBeforeStart;

$multimodal = (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? ($multimodal['transport_mode'] ?? 'rail'))));
$isRail = ($transportMode === 'rail' || $transportMode === '');
$isRailCompletedRouter = $isRail && $isCompleted;
$completedRouter = (array)($railPostIncident['completed_router'] ?? []);
$railAvailableAssistance = (array)($railPostIncident['available_assistance'] ?? []);
$showRailMealChoice = !array_key_exists('meal_expenses', $railAvailableAssistance) || !empty($railAvailableAssistance['meal_expenses']);
$showRailHotelChoice = !array_key_exists('hotel_expenses', $railAvailableAssistance) || !empty($railAvailableAssistance['hotel_expenses']);
$routerChecked = static function (string $key) use ($completedRouter): string {
    return !empty($completedRouter[$key]) ? 'checked' : '';
};
$railStationExpenseTypes = array_values(array_filter((array)($form['rail_station_expense_types'] ?? []), 'is_string'));
$expenseTypeChecked = static function (string $key) use ($railStationExpenseTypes): string {
    return in_array($key, $railStationExpenseTypes, true) ? 'checked' : '';
};
$currencies = ['EUR', 'DKK', 'SEK', 'NOK', 'GBP', 'CHF'];

$hintText = function (string $key) use ($priceHints, $uiLanguage): string {
    if (!is_array($priceHints)) {
        return '';
    }
    $h = $priceHints[$key] ?? null;
    if (!is_array($h) || !isset($h['min'], $h['max'], $h['currency'])) {
        return '';
    }
    $min = number_format((float)$h['min'], 0, ',', '.');
    $max = number_format((float)$h['max'], 0, ',', '.');
    $prefix = $uiLanguage === 'fr' ? 'Fourchette indicative : ' : 'Typisk interval: ';

    return $prefix . "{$min}-{$max} {$h['currency']}";
};

$railTaxiHint = $hintText('taxi');
$railAltTransportHint = $hintText('altTransport');
$railChoiceHintParts = array_values(array_filter([
    $railTaxiHint !== '' ? 'Taxi / minibus: ' . $railTaxiHint : '',
    $railAltTransportHint !== '' ? 'Alternativ videre transport: ' . $railAltTransportHint : '',
]));
$railCapsFallbackText = $railChoiceHintParts !== []
    ? implode(' | ', $railChoiceHintParts)
    : 'Hold dig til noedvendige og rimelige loesninger. Backend vurderer senere beloebet op mod rail-niveauer og dokumentation.';

$blockedSelfPaidTypeValue = strtolower(trim((string)($form['blocked_self_paid_transport_type'] ?? '')));
$railSpecificChoiceHint = match ($blockedSelfPaidTypeValue) {
    'taxi', 'rideshare' => $railTaxiHint,
    'rail', 'bus' => $railAltTransportHint,
    default => '',
};

$mapsOptIn = !empty($form['maps_opt_in_trin5']);
$mapsTrin5 = (is_array($maps) && isset($maps['trin5']) && is_array($maps['trin5'])) ? $maps['trin5'] : null;

$transportTitle = $isOngoing
    ? 'TRIN 5 - Er du strandet? (igangvaerende rejse)'
    : ($isCompleted ? 'TRIN 5 - Er du strandet? (afsluttet rejse)' : ($isBeforeStart ? 'TRIN 5 - Er du strandet? (rejsen starter senere)' : 'TRIN 5 - Er du strandet? (Art. 20)'));
$transportHint = $isCompleted
    ? 'Alternativ transport skulle tilbydes, hvis du blev strandet pga. aflysning/forsinkelse.'
    : 'Alternativ transport skal tilbydes, hvis du er strandet pga. aflysning/forsinkelse.' . ($isFutureLike ? ' (Udfyld det, der er sket indtil nu).' : '');
$strandedQuestion = $isCompleted
    ? 'Sad du fast i et tog paa sporet (Art.20(2)(c))?'
    : 'Sidder du fast i et tog paa sporet (Art.20(2)(c))?';
$transportCardTitle = 'Transport til/fra (Art.20)';
$mapsCardTitle = 'Ruter (Google Maps, valgfrit)';
$mapsHelp = $isCompleted
    ? 'Klik for at hente ruter som reference til den videre transport eller omlaegning, der faktisk blev brugt.'
    : 'Klik for at hente forslag til omlaegning. Vi sender start/destination til Google for at finde ruter.';
$mapsOriginLabel = 'Fra (station)';
$mapsOriginHint = $isCompleted
    ? 'Tip: Brug den station, hvor du sad fast eller maatte finde en anden loesning.'
    : 'Tip: Brug missed connection / din nuvaerende station som start.';
$mapsDestinationLabel = 'Til (destination)';
$mapsDestinationHint = 'Hentes fra billetten (destination).';
$altTransportTypeLabel = 'Transporttype';
$waitedLabel = 'Ventede til toget kunne koere videre';
$selfActionWalkLabel = 'Fandt selv vej til station/spor';
$resolutionEndpointNearestLabel = $translateChoice('Naermeste station');
$resolutionEndpointHelp = $translateChoice('Angiv station, saa vi kan bruge stedet videre i ombooking og vurdering.');
$resolutionArrivalLabel = $translateChoice('Hvilken station endte du ved?');
$otherPlaceLabel = $translateChoice('Anden station');
$otherPlacePlaceholder = $translateChoice('Anden station');
$unknownPlaceLabel = $translateChoice('(ukendt sted)');

$segments = [];
if (!empty($meta['_segments_auto']) && is_array($meta['_segments_auto'])) {
    $segments = (array)$meta['_segments_auto'];
} elseif (!empty($meta['_segments_all']) && is_array($meta['_segments_all'])) {
    $segments = (array)$meta['_segments_all'];
}
$stations = [];
$addStation = function ($val) use (&$stations) {
    $s = trim((string)$val);
    if ($s === '') return;
    $stations[$s] = true;
};
foreach ($segments as $seg) {
    if (!is_array($seg)) continue;
    foreach (['from','to','origin','destination','dep_station','arr_station','departureStation','arrivalStation'] as $k) {
        if (isset($seg[$k])) $addStation($seg[$k]);
    }
}
$addStation($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? ''));
$addStation($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? ''));
$stationOptions = array_keys($stations);
sort($stationOptions, SORT_NATURAL | SORT_FLAG_CASE);
$stationsSearchUrl = $this->Url->build('/api/stations/search');
$stationCountryDefault = strtoupper(trim((string)($form['operator_country'] ?? ($meta['_auto']['operator_country']['value'] ?? ''))));

$articles = (array)($profile['articles'] ?? []);
$art20TrackOff = ($articles['art20_2c'] ?? ($articles['art20_2'] ?? true)) === false;
$art20Disabled = $art20TrackOff;
$showTrack = !$art20TrackOff;

$v = fn(string $k): string => (string)($form[$k] ?? '');
$isPreview = !empty($flowPreview);
$isStrandedTrin5 = strtolower(trim((string)($form['is_stranded_trin5'] ?? 'no')));
if ($isStrandedTrin5 !== 'yes' && $isStrandedTrin5 !== 'no') {
    $isStrandedTrin5 = 'no';
}

$depDefault = trim((string)($form['dep_station'] ?? ($meta['_auto']['dep_station']['value'] ?? '')));
$destDefault = trim((string)($form['arr_station'] ?? ($meta['_auto']['arr_station']['value'] ?? '')));
$missedDefault = trim((string)($form['missed_connection_station'] ?? ($incident['missed_station'] ?? '')));
$railCurrentStationPrefill = trim((string)($railCurrentStationPrefill ?? ''));
$scs = trim((string)($form['stranded_current_station'] ?? ''));
if ($scs === 'other') { $scs = trim((string)($form['stranded_current_station_other'] ?? '')); }
if ($scs === 'unknown') { $scs = ''; }
$originDefault = $scs !== '' ? $scs : ($railCurrentStationPrefill !== '' ? $railCurrentStationPrefill : ($missedDefault !== '' ? $missedDefault : $depDefault));
$mapsConfigured = ((string)(getenv('GOOGLE_MAPS_SERVER_KEY') ?: (getenv('GOOGLE_MAPS_API_KEY') ?: ''))) !== '';
$showTrackFlow = ($showTrack && $isStrandedTrin5 === 'yes');

$toEnd = $v('a20_where_ended');
$arrEnd = $v('a20_arrival_station');
$arrEndOther = $v('a20_arrival_station_other');
$railStationOutcome = strtolower(trim((string)($form['rail_station_where_ended'] ?? '')));
$railStationEndChoice = trim((string)($form['rail_station_end_station'] ?? ''));
$railStationEndOther = trim((string)($form['rail_station_end_station_other'] ?? ''));
$currentStationChoice = trim((string)($form['stranded_current_station'] ?? ''));
$currentStationOther = trim((string)($form['stranded_current_station_other'] ?? ''));
if ($currentStationChoice === '' && $currentStationOther === '' && $railCurrentStationPrefill !== '') {
    if (in_array($railCurrentStationPrefill, $stationOptions, true)) {
        $currentStationChoice = $railCurrentStationPrefill;
    } else {
        $currentStationChoice = 'other';
        $currentStationOther = $railCurrentStationPrefill;
    }
}
$currentStationResolved = $currentStationChoice === 'other' ? $currentStationOther : $currentStationChoice;
if ($currentStationResolved === 'unknown') {
    $currentStationResolved = '';
}
$livePath = 'none';
if (strtolower(trim((string)($form['rail_stranding_context'] ?? 'no'))) === 'station' || strtolower(trim((string)($form['a20_station_stranded'] ?? 'no'))) === 'yes') {
    $livePath = 'station';
} elseif ($isStrandedTrin5 === 'yes' || strtolower(trim((string)($form['stranded_location'] ?? ''))) === 'track') {
    $livePath = 'track';
}
$railStationStillThere = strtolower(trim((string)($form['rail_station_still_there'] ?? ($livePath === 'station' && $isOngoing ? 'yes' : 'no'))));
if (!in_array($railStationStillThere, ['yes', 'no'], true)) {
    $railStationStillThere = $livePath === 'station' && $isOngoing ? 'yes' : 'no';
}

if ($toEnd === '') {
    $toEnd = match ($railStationOutcome) {
        'same_station', 'other_station' => 'nearest_station',
        'return_to_departure' => 'other_departure_point',
        'final_destination' => 'final_destination',
        default => '',
    };
}

if ($arrEnd === '') {
    if ($railStationOutcome === 'same_station' && $currentStationResolved !== '') {
        if ($currentStationChoice === 'other') {
            $arrEnd = 'other';
            $arrEndOther = $currentStationOther !== '' ? $currentStationOther : $arrEndOther;
        } else {
            $arrEnd = $currentStationResolved;
        }
    } elseif ($railStationOutcome === 'other_station') {
        if ($railStationEndChoice === 'other' || ($railStationEndChoice === '' && $railStationEndOther !== '')) {
            $arrEnd = 'other';
            $arrEndOther = $railStationEndOther !== '' ? $railStationEndOther : $arrEndOther;
        } elseif ($railStationEndChoice !== '') {
            $arrEnd = $railStationEndChoice;
        }
    } elseif ($railStationOutcome === 'return_to_departure' && $depDefault !== '') {
        $arrEnd = $depDefault;
    }
}

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Ticketless + pris',
    3 => 'Rejseoplysninger',
    4 => 'Vaelg afgang + rail-vurdering',
    5 => 'Incident',
    6 => $isOngoing ? 'Live valg efter incident' : 'Spor efter incident',
    7 => 'Refusion / Omlaegning',
    8 => 'Mad og Hotel',
    9 => 'Nedgradering',
    10 => 'Kompensation',
];
$steps = array_map($translateChoice, $steps);
$currentStep = (int)($currentStep ?? 6);
$doneSteps = $doneSteps ?? [];
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $backUrl ?? $this->Url->build(['action' => 'incident', '?' => $flowQuery]);
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ($uiLanguage === 'fr' ? ' etapes' : ' trin')));
$travelStateLabel = $translateChoice($isCompleted ? 'Afsluttet rejse' : 'Igangvaerende rejse');
$stats = $stats ?? [
    [$translateChoice('Flow'), $travelStateLabel, null],
    ['Transport', 'RAIL', null],
];

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
$completedRemedyChoice = trim((string)($form['rail_completed_remedy_choice'] ?? ''));
if ($completedRemedyChoice === '') {
    foreach (['refund_return', 'reroute_soonest', 'reroute_later'] as $completedRemedyKey) {
        if (!empty($completedRouter[$completedRemedyKey])) {
            $completedRemedyChoice = $completedRemedyKey;
            break;
        }
    }
}
$remedyGateActive = ((string)($flags['gate_art18'] ?? '')) === '1';
$remedySummary = match ($remedyChoice) {
    'refund_return' => $translateChoice('Tilbagebetaling'),
    'reroute_soonest' => $translateChoice('Ombooking hurtigst muligt'),
    'reroute_later' => $translateChoice('Ombooking senere'),
    'no_real_choice' => $translateChoice('Intet reelt valg'),
    default => $translateChoice($remedyGateActive ? 'Aktiv' : 'Afventer'),
};
$remedySummaryBadge = $remedyChoice !== ''
    ? 'blue'
    : ($remedyGateActive ? 'green' : 'gray');

$livePlanChoiceRaw = array_key_exists('rail_live_plan_choice', $form)
    ? (string)($form['rail_live_plan_choice'] ?? '')
    : ($isOngoing && $remedyChoice !== '' ? $remedyChoice : '');
$livePlanChoice = strtolower(trim($livePlanChoiceRaw));
if (!in_array($livePlanChoice, ['refund_return', 'reroute_soonest', 'reroute_later', 'undecided'], true)) {
    $livePlanChoice = '';
}
if ($isOngoing && $livePlanChoice === '') {
    $livePlanChoice = 'undecided';
}
$liveExpenseTypes = array_values(array_unique(array_values(array_filter(array_map(
    static fn($value): string => strtolower(trim((string)$value)),
    (array)($form['rail_live_expense_types'] ?? [])
), static fn(string $value): bool => in_array($value, ['meals', 'hotel'], true)))));
$liveExpenseChecked = static function (string $key) use ($liveExpenseTypes): string {
    return in_array($key, $liveExpenseTypes, true) ? 'checked' : '';
};

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
$assistanceSummary = $translateChoice($assistanceActive ? 'Aktiv' : 'Afventer');
$assistanceSummaryBadge = $assistanceActive ? 'green' : 'gray';

$summaryRows = [
    [$translateChoice('Refusion / ombooking'), $remedySummary, $remedySummaryBadge],
    [$translateChoice('Assistance'), $assistanceSummary, $assistanceSummaryBadge],
    [$translateChoice('Operatoer'), $operatorLabel !== '' ? $operatorLabel : $translateChoice('Ikke valgt endnu'), null],
];

ob_start();
?>
<style>
  .tc6-choices--ongoing > .tc6-chip { margin-bottom: 4px; }
  .tc6-choices--ongoing > .tc6-h1 { margin-bottom: -6px; }
  .tc6-choices--ongoing > .tc6-step-copy + .tc6-card { margin-top: -14px; }
  .tc6-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; min-width: 0; }
  .tc6-card-plain { background:#f8f9fb; border:1px solid var(--tc-border); border-radius: var(--tc-r-md); padding: 12px; }
  .tc6-station-autocomplete { position: relative; min-width:0; }
  .tc6-station-suggest {
    position:absolute; left:0; right:0; top:calc(100% + 2px); z-index:50;
    background:#fff; border:1px solid var(--tc-border); border-radius:var(--tc-r-md);
    box-shadow: var(--tc-shadow-md); max-height:220px; overflow:auto;
  }
  .tc6-station-suggest button {
    width:100%; text-align:left; border:0; background:transparent; padding:8px 10px; cursor:pointer;
    font-size:14px; color:var(--tc-text-1);
  }
  .tc6-station-suggest button:hover,
  .tc6-station-suggest button:focus { background:#f6f6f6; outline:none; }
  @media (max-width: 760px) {
    .tc6-grid-2 { grid-template-columns: 1fr; }
  }
  .tc6-form > fieldset > .tc6-subtitle,
  .tc6-form > fieldset > .tc6-step-copy,
  .tc6-form .tc6-step-copy,
  .tc6-form .tc6-small.tc6-muted {
    display: none !important;
  }
</style>

<?= $this->Form->create(null, ['url' => ['controller' => 'Flow', 'action' => 'choices', '?' => $flowQuery], 'type' => 'file', 'id' => 'choicesStepForm', 'class' => 'tc6-form', 'novalidate' => true]) ?>
<fieldset class="<?= $isOngoing ? 'tc6-choices--ongoing' : 'tc6-choices--default' ?>" <?= $isPreview ? 'disabled' : '' ?>>
  <div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
  <h1 class="tc6-h1"><?= h($isRailCompletedRouter ? 'Hvad blev relevant efter incident?' : ($isOngoing ? 'Valg efter incident' : 'Transport og spor efter incident')) ?></h1>
  <p class="tc6-subtitle"><?= h($transportTitle) ?></p>
  <?php if ($travelState === 'completed'): ?>
    <p class="tc6-step-copy">Status: Rejsen er afsluttet. Besvar ud fra hvad der faktisk skete.</p>
  <?php elseif ($travelState === 'ongoing'): ?>
    <p class="tc6-step-copy">Status: Rejsen er i gang. Det her trin styrer kun hvilke efterfoelgende trin, der aabner. Live-overblikket ligger i hoejrepanelet.</p>
  <?php elseif ($travelState === 'before_start'): ?>
    <p class="tc6-step-copy">Status: Rejsen er endnu ikke paabegyndt. Besvar ud fra, hvad du forventer at goere ved forsinkelse/aflysning.</p>
  <?php endif; ?>

  <?= $this->element('flow_locked_notice') ?>
  <?php if ($railPostIncident !== [] && !$isOngoing): ?>
    <?= $this->element('tc6/rail_post_incident_strip', ['railPostIncident' => $railPostIncident, 'headline' => $isOngoing ? 'Live cards efter incident' : 'Valgte spor efter incident']) ?>
  <?php endif; ?>

  <?php if ($isRailCompletedRouter): ?>
    <div class="tc6-card tc6-mb12">
      <div class="tc6-section-label">Vaelg de spor der faktisk blev relevante</div>
      <div class="tc6-small tc6-muted tc6-mt4">Det her er kun en router. Vaelg det der faktisk skete efter incident, saa aabner vi kun de relevante efterfoelgende trin.</div>
      <div class="tc6-section-label tc6-mt12">Refusion / videre rejse</div>
      <div class="tc6-choice-cards tc6-choice-cards--2 tc6-mt8">
        <?php foreach ([
          'refund_return' => 'Jeg opgav rejsen / skulle tilbage',
          'reroute_soonest' => 'Jeg ville videre hurtigst muligt',
          'reroute_later' => 'Jeg ville rejse videre senere',
        ] as $routerKey => $routerLabel): ?>
          <label class="tc6-choice-card">
            <input type="radio" name="rail_completed_remedy_choice" value="<?= h($routerKey) ?>" <?= $completedRemedyChoice === $routerKey ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($routerLabel) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="tc6-section-label tc6-mt12">Strandet</div>
      <div class="tc6-choice-cards tc6-choice-cards--2 tc6-mt8">
        <?php foreach ([
          'station_stranding' => 'Jeg var strandet paa en station',
          'track_stranding' => 'Jeg sad fast i toget / paa sporet',
        ] as $routerKey => $routerLabel): ?>
          <label class="tc6-choice-card">
            <input type="checkbox" name="rail_completed_consequences[]" value="<?= h($routerKey) ?>" <?= $routerChecked($routerKey) ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($routerLabel) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php
      $railCompletedExpenseChoices = [];
      if ($showRailMealChoice) {
          $railCompletedExpenseChoices['meal_expenses'] = 'Jeg havde udgifter til mad og drikke';
      }
      if ($showRailHotelChoice) {
          $railCompletedExpenseChoices['hotel_expenses'] = 'Jeg havde udgifter til hotel / overnatning';
      }
      ?>
      <?php if ($railCompletedExpenseChoices !== []): ?>
        <div class="tc6-section-label tc6-mt12">Udgifter</div>
        <div class="tc6-choice-cards tc6-choice-cards--2 tc6-mt8">
          <?php foreach ($railCompletedExpenseChoices as $routerKey => $routerLabel): ?>
            <label class="tc6-choice-card">
              <input type="checkbox" name="rail_completed_consequences[]" value="<?= h($routerKey) ?>" <?= $routerChecked($routerKey) ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($routerLabel) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <input type="hidden" id="railCompletedTrackFlag" name="is_stranded_trin5" value="<?= !empty($completedRouter['track_stranding']) ? 'yes' : 'no' ?>" />
  <?php elseif ($isOngoing): ?>
    <div class="tc6-card tc6-mb12">
      <div class="tc6-section-label">Hvad er planen lige nu?</div>
      <div class="tc6-small tc6-muted tc6-mt4">Det her er kun dit live-overblik. Selve Art. 18-valget bekraefter du stadig i naeste trin.</div>
      <div class="tc6-choice-cards tc6-choice-cards--2 tc6-mt8">
        <?php foreach ([
          'reroute_soonest' => 'Jeg vil videre hurtigst muligt',
          'reroute_later' => 'Jeg vil rejse videre senere',
          'refund_return' => 'Jeg opgiver rejsen / skal tilbage',
          'undecided' => 'Jeg har ikke besluttet mig endnu',
        ] as $routerKey => $routerLabel): ?>
          <label class="tc6-choice-card">
            <input type="radio" name="rail_live_plan_choice" value="<?= h($routerKey) ?>" <?= $livePlanChoice === $routerKey ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($routerLabel) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tc6-card tc6-mb12">
      <div class="tc6-section-label">Hvilket live-spor passer lige nu?</div>
      <div class="tc6-small tc6-muted tc6-mt4">Incident er skaeringspunktet. Vaelg kun hvor passageren er lige nu. Selve station/spor-detaljerne udfyldes i det naeste trin.</div>
      <div class="tc6-choice-cards tc6-choice-cards--3 tc6-mt8">
        <?php foreach ([
          'track' => 'Jeg sidder fast i toget / paa sporet',
          'station' => 'Jeg er strandet paa en station',
          'none' => 'Ingen af de to er aktive nu',
        ] as $routerKey => $routerLabel): ?>
          <label class="tc6-choice-card">
            <input type="radio" name="rail_live_path" value="<?= h($routerKey) ?>" <?= $livePath === $routerKey ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($routerLabel) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <input type="hidden" id="railLiveStationContext" name="rail_stranding_context" value="<?= $livePath === 'station' ? 'station' : 'no' ?>" />
      <input type="hidden" id="railLiveTrackFlag" name="is_stranded_trin5" value="<?= $livePath === 'track' ? 'yes' : 'no' ?>" />
    </div>

    <div class="tc6-card tc6-mb12">
      <div class="tc6-section-label">Hvilken assistance er relevant nu?</div>
      <div class="tc6-small tc6-muted tc6-mt4">Vaelg kun det, der skal aabne i assistance-trinnet. Detaljespoergsmaalene ligger i de efterfoelgende trin.</div>
      <?php
      $railLiveExpenseChoices = [];
      if ($showRailMealChoice) {
          $railLiveExpenseChoices['meals'] = 'Mad og drikke';
      }
      if ($showRailHotelChoice) {
          $railLiveExpenseChoices['hotel'] = 'Hotel / overnatning';
      }
      ?>
      <?php if ($railLiveExpenseChoices !== []): ?>
        <div class="tc6-choice-cards tc6-choice-cards--2 tc6-mt8">
          <?php foreach ($railLiveExpenseChoices as $expenseKey => $expenseLabel): ?>
            <label class="tc6-choice-card">
              <input type="checkbox" name="rail_live_expense_types[]" value="<?= h($expenseKey) ?>" <?= $liveExpenseChecked($expenseKey) ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($expenseLabel) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

    <div id="coreAfterArt20">
    <?php if (!$isOngoing && !$isRailCompletedRouter): ?>
    <div id="art20Wrapper" class="tc6-card <?= (($art20Disabled && !$isPreview) || ($isOngoing && $livePath !== 'track')) ? 'tc6-hidden' : '' ?>" data-art="20" data-art20-disabled="<?= $art20Disabled ? '1' : '0' ?>" <?= $isOngoing ? 'data-show-if="is_stranded_trin5:yes"' : '' ?>>
      <div class="tc6-section-label"><?= h($transportCardTitle) ?></div>
      <p class="tc6-step-copy"><?= h($transportHint) ?></p>

      <?php if (!$isOngoing): ?>
      <div class="tc6-card-plain tc6-mt8">
        <strong>Vejledende rail-niveauer (ikke faste juridiske caps)</strong>
        <div class="tc6-small tc6-muted tc6-mt4">Hvis du selv maa finde akut videre transport fra banen, saa hold dig til noedvendige og rimelige loesninger. Dokumentation og endelig vurdering sker senere i backend-sagen.</div>
        <div class="tc6-small tc6-muted tc6-mt4"><?= h($railCapsFallbackText) ?></div>
      </div>
      <?php endif; ?>

      <?php if (!$isRailCompletedRouter && !$isOngoing): ?>
        <div class="tc6-field tc6-mt8">
          <div class="tc6-choice-line"><?= h($strandedQuestion) ?></div>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="is_stranded_trin5" value="yes" <?= $isStrandedTrin5 === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="is_stranded_trin5" value="no" <?= $isStrandedTrin5 === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
            </label>
          </div>
        </div>
      <?php elseif ($isRailCompletedRouter): ?>
        <div class="tc6-card-plain tc6-mt8">Du har markeret, at du sad fast i toget eller paa sporet. Udfyld kun det der faktisk skete.</div>
      <?php else: ?>
        <div class="tc6-card-plain tc6-mt8">Du har valgt sporet som aktiv live-haendelse. Udfyld hvad der sker lige nu og hvordan du kom videre.</div>
      <?php endif; ?>

      <div class="tc6-card tc6-mt12" id="mapsCardTrin5" data-show-if="is_stranded_trin5:yes" style="background:#f8f9fb;">
        <div class="tc6-section-label"><?= h($mapsCardTitle) ?></div>
        <div class="tc6-small tc6-muted tc6-mt4"><?= h($mapsHelp) ?></div>
        <input type="hidden" name="maps_opt_in_trin5" value="0" />
        <label class="tc6-inline-check tc6-mt8"><input type="checkbox" name="maps_opt_in_trin5" value="1" <?= $mapsOptIn ? 'checked' : '' ?> /> Brug Google Maps i denne sag</label>

        <div id="mapsPanelTrin5" class="tc6-mt8 <?= $mapsOptIn ? '' : 'tc6-hidden' ?>" data-endpoint="<?= h($this->Url->build(['controller' => 'Flow', 'action' => 'mapsRoutes'])) ?>">
          <div class="tc6-grid-2">
            <label class="tc6-label"><?= h($mapsOriginLabel) ?>
              <input type="text" id="mapsOriginTrin5" value="<?= h($originDefault) ?>" class="tc6-input" />
              <div class="tc6-small tc6-muted tc6-mt4"><?= h($mapsOriginHint) ?></div>
            </label>
            <label class="tc6-label"><?= h($mapsDestinationLabel) ?>
              <input type="text" id="mapsDestTrin5" value="<?= h($destDefault) ?>" readonly class="tc6-input" />
              <div class="tc6-small tc6-muted tc6-mt4"><?= h($mapsDestinationHint) ?></div>
            </label>
          </div>

          <div class="tc6-mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="tc6-btn tc6-btn--ghost" id="mapsFetchTrin5" <?= $mapsConfigured ? '' : 'disabled' ?>>Hent forslag</button>
            <a class="tc6-btn tc6-btn--ghost" id="mapsOpenTrin5" target="_blank" rel="noopener">Aabn i Google Maps</a>
            <span class="tc6-small tc6-muted" id="mapsStatusTrin5"></span>
          </div>
          <?php if (!$mapsConfigured): ?>
            <div class="tc6-small tc6-muted tc6-mt8">Bemaerk: Google Routes er ikke konfigureret (mangler server API key).</div>
          <?php endif; ?>

          <div id="mapsRoutesTrin5" class="tc6-mt8"></div>

          <?php if (is_array($mapsTrin5) && !empty($mapsTrin5['routes'])): ?>
            <div class="tc6-mt8 tc6-small tc6-muted">Seneste forslag (gemt i session):</div>
            <ul class="tc6-small">
              <?php foreach ((array)$mapsTrin5['routes'] as $r): ?>
                <?php if (!is_array($r)) { continue; } ?>
                <li>
                  <div><strong><?= h((string)($r['summary'] ?? '')) ?></strong></div>
                  <?php if (!empty($r['segments']) && is_array($r['segments'])): ?>
                    <ul class="tc6-small tc6-muted tc6-mt4">
                      <?php foreach ((array)$r['segments'] as $s): ?>
                        <?php if (!is_array($s)) { continue; } ?>
                        <?php
                          $veh = strtoupper((string)($s['vehicle'] ?? ''));
                          $vehLabel = $veh;
                          if (strpos($veh, 'TRAIN') !== false || $veh === 'RAIL') { $vehLabel = 'Tog'; }
                          elseif (strpos($veh, 'BUS') !== false) { $vehLabel = 'Bus'; }
                          elseif (strpos($veh, 'SUBWAY') !== false) { $vehLabel = 'Metro'; }
                          elseif (strpos($veh, 'LIGHT_RAIL') !== false || strpos($veh, 'TRAM') !== false) { $vehLabel = 'Letbane'; }
                          elseif (strpos($veh, 'FERRY') !== false) { $vehLabel = 'Faerge'; }
                          $line = trim((string)($s['line'] ?? ''));
                          $from = trim((string)($s['from'] ?? ''));
                          $to = trim((string)($s['to'] ?? ''));
                          $txt = trim($vehLabel . ($line !== '' ? (' ' . $line) : ''));
                          if ($from !== '' || $to !== '') { $txt .= ': ' . ($from !== '' ? $from : '?') . ' -> ' . ($to !== '' ? $to : '?'); }
                        ?>
                        <li><?= h($txt) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div class="<?= $showTrackFlow ? '' : 'tc6-hidden' ?>" data-show-if="is_stranded_trin5:yes" data-art="20(2c)">
        <div class="tc6-field tc6-mt8">
          <div class="tc6-choice-line">Blev der stillet transport til raadighed for at komme vaek/videre?</div>
          <?php $bt = $v('blocked_train_alt_transport'); ?>
          <div class="tc6-choice-cards tc6-choice-cards--3">
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_train_alt_transport" value="yes" <?= $bt === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_train_alt_transport" value="no" <?= $bt === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_train_alt_transport" value="irrelevant" <?= $bt === 'irrelevant' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ikke relevant / andet</span></span>
            </label>
          </div>
        </div>

        <div class="tc6-field" data-show-if="blocked_train_alt_transport:yes">
          <label class="tc6-label"><?= h($altTransportTypeLabel) ?>
            <?php $tt = $v('assistance_alt_transport_type'); ?>
            <select name="assistance_alt_transport_type" class="tc6-select">
              <option value="">Vaelg</option>
              <option value="rail" <?= $tt === 'rail' ? 'selected' : '' ?>>Tog</option>
              <option value="bus" <?= $tt === 'bus' ? 'selected' : '' ?>>Bus</option>
              <option value="taxi" <?= $tt === 'taxi' ? 'selected' : '' ?>>Taxi</option>
              <option value="other" <?= $tt === 'other' ? 'selected' : '' ?>>Andet</option>
            </select>
            <div class="tc6-small tc6-muted tc6-mt4"><?= h($railCapsFallbackText) ?></div>
          </label>
        </div>

        <div class="tc6-field" data-show-if="blocked_train_alt_transport:no">
          <div class="tc6-choice-line">Hvad gjorde du saa?</div>
          <?php $bn = $v('blocked_no_transport_action'); ?>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_no_transport_action" value="waited" <?= $bn === 'waited' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($waitedLabel) ?></span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_no_transport_action" value="walked_station" <?= $bn === 'walked_station' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title"><?= h($selfActionWalkLabel) ?></span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_no_transport_action" value="evacuated_later" <?= $bn === 'evacuated_later' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Blev evakueret senere</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_no_transport_action" value="self_arranged" <?= $bn === 'self_arranged' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Fandt selv transport</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="blocked_no_transport_action" value="other" <?= $bn === 'other' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Andet</span></span>
            </label>
          </div>
        </div>

        <div class="tc6-field" data-show-if="blocked_no_transport_action:self_arranged">
          <div class="tc6-small tc6-muted">Registrer kun transporttypen her. Beloeb, valuta og dokumentation indtastes senere i backend-sagen.</div>
          <?php if ($railSpecificChoiceHint !== ''): ?>
            <div class="tc6-small tc6-muted tc6-mt4"><?= h((in_array($blockedSelfPaidTypeValue, ['taxi', 'rideshare'], true) ? 'Typisk niveau for taxi/minibus: ' : 'Typisk niveau for alternativ videre transport: ') . $railSpecificChoiceHint) ?></div>
          <?php else: ?>
            <div class="tc6-small tc6-muted tc6-mt4"><?= h($railCapsFallbackText) ?></div>
          <?php endif; ?>
          <div class="tc6-grid-2 tc6-mt8">
            <label class="tc6-label"><?= h($altTransportTypeLabel) ?>
              <?php $bst = $v('blocked_self_paid_transport_type'); ?>
              <select name="blocked_self_paid_transport_type" class="tc6-select">
                <option value="">Vaelg</option>
                <option value="rail" <?= $bst === 'rail' ? 'selected' : '' ?>>Tog</option>
                <option value="bus" <?= $bst === 'bus' ? 'selected' : '' ?>>Bus</option>
                <option value="taxi" <?= $bst === 'taxi' ? 'selected' : '' ?>>Taxi</option>
                <option value="rideshare" <?= $bst === 'rideshare' ? 'selected' : '' ?>>Samkoersel/rideshare</option>
                <option value="other" <?= $bst === 'other' ? 'selected' : '' ?>>Andet</option>
              </select>
            </label>
            <?php if ($isOngoing): ?>
              <div class="tc6-card-plain">
                <strong>Foreloebigt beloeb flyttes til live-udgifter</strong>
                <div class="tc6-small tc6-muted tc6-mt4">Hvis du allerede kender et foreloebigt beloeb for videre transport, saa afkryds "Selvbetalt videre transport" i kortet ovenfor og registrer beloebet der.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div id="resolutionWrapTrin5" class="tc6-field tc6-mt12 tc6-hidden">
          <div class="tc6-choice-line"><strong>Hvor endte du?</strong></div>
          <div class="tc6-grid-2 tc6-mt8">
            <label class="tc6-label">Slutpunkt
              <select name="a20_where_ended" class="tc6-select">
                <option value="">Vaelg</option>
                <option value="nearest_station" <?= $toEnd === 'nearest_station' ? 'selected' : '' ?>><?= h($resolutionEndpointNearestLabel) ?></option>
                <option value="other_departure_point" <?= $toEnd === 'other_departure_point' ? 'selected' : '' ?>>Et andet afgangssted</option>
                <option value="final_destination" <?= $toEnd === 'final_destination' ? 'selected' : '' ?>>Mit endelige bestemmelsessted</option>
              </select>
              <div class="tc6-small tc6-muted tc6-mt4" data-show-if="a20_where_ended:nearest_station,other_departure_point"><?= h($resolutionEndpointHelp) ?></div>
            </label>
            <label class="tc6-label tc6-station-autocomplete" data-station-select="a20_arrival_station" data-station-other="a20_arrival_station_other" data-show-if="a20_where_ended:nearest_station,other_departure_point"><?= h($resolutionArrivalLabel) ?>
              <select name="a20_arrival_station" class="tc6-select">
                <option value="">Vaelg</option>
                <?php foreach ($stationOptions as $st): ?>
                  <option value="<?= h($st) ?>" <?= $arrEnd === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                <?php endforeach; ?>
                <option value="unknown" <?= $arrEnd === 'unknown' ? 'selected' : '' ?>>Ved ikke</option>
                <option value="other" <?= $arrEnd === 'other' ? 'selected' : '' ?>><?= h($otherPlaceLabel) ?></option>
              </select>
              <input type="text" name="a20_arrival_station_other" value="<?= h($arrEndOther) ?>" placeholder="<?= h($otherPlacePlaceholder) ?>" class="tc6-input tc6-mt8" data-show-if="a20_arrival_station:other" />
              <input type="hidden" name="a20_arrival_station_other_osm_id" value="<?= h($v('a20_arrival_station_other_osm_id')) ?>" />
              <input type="hidden" name="a20_arrival_station_other_lat" value="<?= h($v('a20_arrival_station_other_lat')) ?>" />
              <input type="hidden" name="a20_arrival_station_other_lon" value="<?= h($v('a20_arrival_station_other_lon')) ?>" />
              <input type="hidden" name="a20_arrival_station_other_country" value="<?= h($v('a20_arrival_station_other_country')) ?>" />
              <input type="hidden" name="a20_arrival_station_other_type" value="<?= h($v('a20_arrival_station_other_type')) ?>" />
              <input type="hidden" name="a20_arrival_station_other_source" value="<?= h($v('a20_arrival_station_other_source')) ?>" />
              <div class="tc6-station-suggest" data-for="a20_arrival_station_other" style="display:none;"></div>
            </label>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?= $this->element('tc6/action_bar', [
      'backUrl' => $backUrl,
      'backLabel' => $translateChoice('Tilbage'),
      'nextLabel' => $translateChoice('Naeste trin'),
      'nextVariant' => 'navy',
      'submitName' => '_save',
  ]) ?>
  <input type="hidden" name="_choices_submitted" value="1" />
</fieldset>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'choices', 'formSelector' => '#choicesStepForm']) ?>

<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && isset($choicesTranslations[$uiLanguage])) {
    $content = strtr($content, $choicesTranslations[$uiLanguage]);
}
ob_start();
echo $content;
?>

<script>
(function(){
  const stationsSearchUrl = <?= json_encode((string)$stationsSearchUrl, JSON_UNESCAPED_SLASHES) ?>;
  const stationCountryDefault = <?= json_encode((string)$stationCountryDefault, JSON_UNESCAPED_SLASHES) ?>;
  const mapsPickText = <?= json_encode($translateChoice('Vaelg start og destination foerst.'), JSON_UNESCAPED_UNICODE) ?>;
  const mapsLoadingText = <?= json_encode($translateChoice('Henter...'), JSON_UNESCAPED_UNICODE) ?>;
  const mapsErrorText = <?= json_encode($translateChoice('Fejl'), JSON_UNESCAPED_UNICODE) ?>;
  const mapsFetchErrorText = <?= json_encode($translateChoice('Kunne ikke hente ruter.'), JSON_UNESCAPED_UNICODE) ?>;
  const mapsNoRoutesText = <?= json_encode($translateChoice('Ingen forslag fundet.'), JSON_UNESCAPED_UNICODE) ?>;
  const stationLabel = <?= json_encode($translateChoice('Station'), JSON_UNESCAPED_UNICODE) ?>;
  const terminalLabel = <?= json_encode($translateChoice('Terminal'), JSON_UNESCAPED_UNICODE) ?>;
  const trainLabel = <?= json_encode($translateChoice('Tog'), JSON_UNESCAPED_UNICODE) ?>;
  const tramLabel = <?= json_encode($translateChoice('Letbane'), JSON_UNESCAPED_UNICODE) ?>;
  const ferryLabel = <?= json_encode($translateChoice('Faerge'), JSON_UNESCAPED_UNICODE) ?>;
  const ferryTerminalLabel = <?= json_encode($translateChoice('Faergeterminal'), JSON_UNESCAPED_UNICODE) ?>;
  const portLabel = <?= json_encode($translateChoice('Havn'), JSON_UNESCAPED_UNICODE) ?>;
  const haltLabel = <?= json_encode($translateChoice('Stopested'), JSON_UNESCAPED_UNICODE) ?>;
  const tc6Forms = window.tc6Forms || {};
  const getVal = tc6Forms.getFieldValue || function(name) {
    const checked = document.querySelector('input[name="' + name + '"]:checked');
    if (checked) return checked.value || '';
    const sel = document.querySelector('select[name="' + name + '"]');
    if (sel) return sel.value || '';
    const inp = document.querySelector('input[name="' + name + '"]');
    if (inp && inp.type !== 'radio' && inp.type !== 'checkbox') return inp.value || '';
    return '';
  };
  const setBlockVisible = tc6Forms.setBlockVisible || function(el, show) {
    if (!el) return;
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  };
  const updateShowIf = tc6Forms.updateShowIf || function(root = document) {
    root.querySelectorAll('[data-show-if]').forEach(function(el) {
      const spec = el.getAttribute('data-show-if');
      if (!spec) return;
      const parts = spec.split(':');
      if (parts.length !== 2) return;
      const name = parts[0];
      const valid = parts[1].split(',');
      const value = getVal(name);
      const show = value !== '' && valid.includes(value);
      setBlockVisible(el, show);
    });
  };
  const clearField = tc6Forms.clearField || function(name) {
    document.querySelectorAll('[name="' + name + '"]').forEach(function(el) {
      if (el.type === 'radio' || el.type === 'checkbox') { el.checked = false; return; }
      if (el.tagName === 'SELECT') {
        el.value = '';
        if (el.value !== '') el.selectedIndex = 0;
        return;
      }
      el.value = '';
    });
  };
  const clearFields = tc6Forms.clearFields || function(names) { names.forEach(clearField); };
  function consequenceChecked(key) {
    const input = document.querySelector('input[name="rail_completed_consequences[]"][value="' + key + '"]');
    return !!(input && input.checked);
  }
  function liveExpenseChecked(key) {
    const input = document.querySelector('input[name="rail_live_expense_types[]"][value="' + key + '"]');
    return !!(input && input.checked);
  }
  function syncCompletedConsequences() {
    document.querySelectorAll('[data-show-if-consequence]').forEach(function(el) {
      const key = el.getAttribute('data-show-if-consequence');
      if (!key) return;
      setBlockVisible(el, consequenceChecked(key));
    });
    const trackFlag = document.getElementById('railCompletedTrackFlag');
    if (trackFlag) {
      trackFlag.value = consequenceChecked('track_stranding') ? 'yes' : 'no';
    }
  }
  function syncLiveExpensePanels() {
    document.querySelectorAll('[data-live-expense]').forEach(function(el) {
      const key = el.getAttribute('data-live-expense');
      if (!key) return;
      setBlockVisible(el, liveExpenseChecked(key));
    });
  }

  function syncLivePathInputs() {
    const stationContext = document.getElementById('railLiveStationContext');
    const trackFlag = document.getElementById('railLiveTrackFlag');
    const livePath = getVal('rail_live_path');
    if (stationContext) {
      stationContext.value = livePath === 'station' ? 'station' : 'no';
    }
    if (trackFlag) {
      trackFlag.value = livePath === 'track' ? 'yes' : 'no';
    }
  }

  function updateReveal() {
    syncLivePathInputs();
    syncCompletedConsequences();
    syncLiveExpensePanels();
    updateShowIf(document);
  }

  function updateResolutionVisibility() {
    const wrap = document.getElementById('resolutionWrapTrin5');
    if (!wrap) return;
    const stranded = getVal('is_stranded_trin5');
    let shouldShow = false;
    if (stranded === 'yes') {
      const bt = getVal('blocked_train_alt_transport');
      if (bt === 'yes') shouldShow = true;
      else if (bt === 'no') shouldShow = !!getVal('blocked_no_transport_action');
    }
    setBlockVisible(wrap, shouldShow);
    if (!shouldShow && stranded === 'yes') {
      clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
    }
  }

  function syncMapsOriginFromResolution(){
    const cb = document.querySelector('input[type="checkbox"][name="maps_opt_in_trin5"]');
    const originEl = document.getElementById('mapsOriginTrin5');
    const destEl = document.getElementById('mapsDestTrin5');
    const open = document.getElementById('mapsOpenTrin5');
    if (!cb || !cb.checked || !originEl || !destEl || !open) return;
    originEl.dataset.manual = originEl.dataset.manual || '0';
    if (originEl.dataset.manual === '1') return;

    const stranded = getVal('is_stranded_trin5');
    if (stranded !== 'yes') return;
    const ended = getVal('a20_where_ended');
    if (!(ended === 'nearest_station' || ended === 'other_departure_point')) return;

    const sel = document.querySelector('select[name="a20_arrival_station"]');
    const other = document.querySelector('input[name="a20_arrival_station_other"]');
    if (!sel) return;
    let st = (sel.value || '').trim();
    if (st === 'other') st = (other && other.value ? String(other.value).trim() : '');
    if (!st || st === 'unknown') return;

    originEl.value = st;
    const o = encodeURIComponent((originEl.value || '').trim());
    const d = encodeURIComponent((destEl.value || '').trim());
    open.href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
  }

  function handleResets(target) {
    const name = target.name || '';
    if (name === 'rail_live_path') {
      const v = target.value || '';
      if (v !== 'track') {
        clearFields([
          'blocked_train_alt_transport','assistance_alt_transport_type',
          'blocked_no_transport_action','blocked_self_paid_transport_type',
          'rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency',
          'a20_where_ended','a20_arrival_station','a20_arrival_station_other'
        ]);
      }
      if (v !== 'station') {
        clearFields([
          'stranded_current_station','stranded_current_station_other',
          'rail_station_still_there','rail_station_where_ended',
          'rail_station_end_station','rail_station_end_station_other'
        ]);
      }
      return;
    }
    if (name === 'rail_live_expense_types[]') {
      const v = target.value || '';
      if (target.checked) {
        return;
      }
      if (v === 'meals') {
        clearFields(['rail_live_meal_amount','rail_live_meal_currency']);
      } else if (v === 'hotel') {
        clearFields(['rail_live_hotel_amount','rail_live_hotel_currency']);
      } else if (v === 'local_transport') {
        clearFields(['rail_live_local_transport_amount','rail_live_local_transport_currency']);
      } else if (v === 'self_paid_transport') {
        clearFields(['rail_live_self_paid_transport_type','rail_live_self_paid_transport_amount','rail_live_self_paid_transport_currency']);
      }
      return;
    }
    if (name === 'rail_completed_consequences[]') {
      const v = target.value || '';
      if (!target.checked && v === 'track_stranding') {
        clearFields([
          'blocked_train_alt_transport','assistance_alt_transport_type',
          'blocked_no_transport_action','blocked_self_paid_transport_type',
          'rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency',
          'a20_where_ended','a20_arrival_station','a20_arrival_station_other'
        ]);
      }
      if (!target.checked && v === 'station_stranding') {
        clearFields([
          'stranded_current_station','stranded_current_station_other',
          'rail_station_where_ended','rail_station_end_station','rail_station_end_station_other'
        ]);
        document.querySelectorAll('input[name="rail_station_expense_types[]"]').forEach(function(el){ el.checked = false; });
      }
      return;
    }
    if (name === 'is_stranded_trin5') {
      const v0 = target.value || '';
      if (v0 !== 'yes') {
        const hadTrackState = !!getVal('blocked_train_alt_transport') || !!getVal('blocked_no_transport_action') || !!getVal('assistance_alt_transport_type');
        clearFields([
          'blocked_train_alt_transport','assistance_alt_transport_type',
          'blocked_no_transport_action',
          'blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt',
          'rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency',
        ]);
        if (hadTrackState) {
          clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
        }
      }
      return;
    }
    if (name === 'blocked_train_alt_transport') {
      const v2 = target.value || '';
      clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
      if (v2 === 'yes') {
        clearFields(['blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt','rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency']);
      } else if (v2 === 'no') {
        clearFields(['assistance_alt_transport_type','a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
      } else {
        clearFields([
          'blocked_no_transport_action','blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt','rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency',
          'assistance_alt_transport_type','a20_where_ended','a20_arrival_station','a20_arrival_station_other'
        ]);
      }
      return;
    }
    if (name === 'blocked_no_transport_action') {
      clearFields(['a20_where_ended','a20_arrival_station','a20_arrival_station_other']);
      if ((target.value || '') !== 'self_arranged') {
        clearFields(['blocked_self_paid_transport_type','blocked_self_paid_amount','blocked_self_paid_currency','blocked_self_paid_receipt','rail_live_blocked_self_paid_amount','rail_live_blocked_self_paid_currency']);
      }
      return;
    }
    if (name === 'rail_station_where_ended') {
      if ((target.value || '') !== 'other_station') {
        clearFields(['rail_station_end_station','rail_station_end_station_other']);
      }
      return;
    }
    if (name === 'rail_station_still_there') {
      if ((target.value || '') !== 'no') {
        clearFields(['rail_station_where_ended','rail_station_end_station','rail_station_end_station_other']);
      }
      return;
    }
    if (name === 'rail_station_end_station' || name === 'stranded_current_station') {
      if ((target.value || '') !== 'other') {
        clearFields([name === 'rail_station_end_station' ? 'rail_station_end_station_other' : 'stranded_current_station_other']);
      }
      return;
    }
    if (name === 'a20_where_ended') {
      const v4 = target.value || '';
      if (!(v4 === 'nearest_station' || v4 === 'other_departure_point')) {
        clearFields(['a20_arrival_station','a20_arrival_station_other']);
      }
      return;
    }
    if (name === 'a20_arrival_station') {
      if ((target.value || '') !== 'other') {
        clearFields(['a20_arrival_station_other']);
      }
    }
  }

  function mapsInit(){
    const panel = document.getElementById('mapsPanelTrin5');
    const cb = document.querySelector('input[type="checkbox"][name="maps_opt_in_trin5"]');
    const originEl = document.getElementById('mapsOriginTrin5');
    const destEl = document.getElementById('mapsDestTrin5');
    const btn = document.getElementById('mapsFetchTrin5');
    const open = document.getElementById('mapsOpenTrin5');
    const out = document.getElementById('mapsRoutesTrin5');
    const status = document.getElementById('mapsStatusTrin5');
    if (!panel || !cb || !originEl || !destEl || !btn || !open || !out) return;

    function getOrigin(){ return (originEl.value || '').trim(); }
    function getDest(){ return (destEl.value || '').trim(); }
    function setOpenLink(){
      const o = encodeURIComponent(getOrigin());
      const d = encodeURIComponent(getDest());
      open.href = 'https://www.google.com/maps/dir/?api=1&origin=' + o + '&destination=' + d + '&travelmode=transit';
    }
    function updatePanel(){
      panel.classList.toggle('tc6-hidden', !cb.checked);
      panel.hidden = !cb.checked;
      setOpenLink();
    }
    function renderRoutes(payload){
      out.innerHTML = '';
      if (!payload || !payload.ok) {
        const msg = (payload && payload.error) ? payload.error : mapsFetchErrorText;
        out.innerHTML = '<div class="tc6-small tc6-muted">' + ('' + msg).replace(/</g,'&lt;') + '</div>';
        return;
      }
      const routes = payload.routes || [];
      if (!routes.length) {
        out.innerHTML = '<div class="tc6-small tc6-muted">' + mapsNoRoutesText + '</div>';
        return;
      }
      function vehicleLabel(v){
        v = (v || '').toString().toUpperCase();
        if (v.indexOf('TRAIN') >= 0 || v === 'RAIL') return trainLabel;
        if (v.indexOf('BUS') >= 0) return 'Bus';
        if (v.indexOf('SUBWAY') >= 0) return 'Metro';
        if (v.indexOf('LIGHT_RAIL') >= 0 || v.indexOf('TRAM') >= 0) return tramLabel;
        if (v.indexOf('FERRY') >= 0) return ferryLabel;
        return v || 'Transit';
      }
      function hhmm(ts){
        if (!ts) return '';
        const m = /T(\d{2}:\d{2})/.exec(ts.toString());
        return m ? m[1] : '';
      }
      function segText(s){
        s = s || {};
        const mode = vehicleLabel(s.vehicle);
        const line = (s.line || '').toString().trim();
        const from = (s.from || '').toString().trim();
        const to = (s.to || '').toString().trim();
        const dep = hhmm(s.dep_time);
        const arr = hhmm(s.arr_time);
        const head = (mode + (line ? (' ' + line) : '')).trim();
        const parts = [];
        if (from || to) parts.push((from || '?') + ' -> ' + (to || '?'));
        if (dep || arr) parts.push((dep || '') + (arr ? ('-' + arr) : ''));
        return head + (parts.length ? (': ' + parts.join(' / ')) : '');
      }
      routes.forEach(function(r){
        const box = document.createElement('div');
        box.style.cssText = 'padding:8px;border:1px solid #ddd;border-radius:6px;background:#fff;margin-top:8px;';
        const title = document.createElement('div');
        title.style.fontWeight = '600';
        title.textContent = r.summary || '';
        box.appendChild(title);
        const segs = r.segments || [];
        if (segs.length) {
          const ul = document.createElement('ul');
          ul.className = 'tc6-small tc6-muted tc6-mt4';
          segs.forEach(function(s){
            const li = document.createElement('li');
            li.textContent = segText(s);
            ul.appendChild(li);
          });
          box.appendChild(ul);
        }
        out.appendChild(box);
      });
    }

    cb.addEventListener('change', function(){
      if (cb.checked) { try { syncMapsOriginFromResolution(); } catch (e) {} }
      updatePanel();
    });
    originEl.dataset.manual = originEl.dataset.manual || '0';
    originEl.addEventListener('input', function(){
      originEl.dataset.manual = '1';
      setOpenLink();
    });
    btn.addEventListener('click', async function(){
      const origin = getOrigin();
      const dest = getDest();
      setOpenLink();
      if (!origin || !dest) {
        if (status) status.textContent = mapsPickText;
        return;
      }
      if (status) status.textContent = mapsLoadingText;
      try {
        const csrf = (document.querySelector('input[name="_csrfToken"]') || {}).value || '';
        const res = await fetch(panel.getAttribute('data-endpoint'), {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded', ...(csrf ? {'X-CSRF-Token': csrf} : {})},
          body: new URLSearchParams({
            context: 'trin5',
            maps_opt_in_trin5: '1',
            origin: origin,
            destination: dest
          })
        });
        const j = await res.json();
        renderRoutes(j);
        if (status) status.textContent = j && j.ok ? 'OK' : mapsErrorText;
      } catch (e) {
        if (status) status.textContent = mapsErrorText;
        renderRoutes({ok:false, error: (e && e.message) ? e.message : mapsErrorText});
      }
    });

    updatePanel();
    setOpenLink();
    try { syncMapsOriginFromResolution(); } catch (e) {}
  }

  function initStationAutocomplete(){
    if (!stationsSearchUrl) return;
    document.querySelectorAll('.tc6-station-autocomplete').forEach(function(lbl){
      const selectName = lbl.getAttribute('data-station-select') || '';
      const otherName = lbl.getAttribute('data-station-other') || '';
      if (!selectName || !otherName) return;
      const sel = lbl.querySelector('select[name="' + selectName + '"]');
      const input = lbl.querySelector('input[name="' + otherName + '"]');
      const box = lbl.querySelector('.tc6-station-suggest[data-for="' + otherName + '"]');
      if (!sel || !input || !box) return;

      let timer = null;
      let ctrl = null;
      function niceType(t){
        const s = (t || '').toString().toLowerCase();
        if (s === 'station') return stationLabel;
        if (s === 'terminal') return terminalLabel;
        if (s === 'ferry_terminal') return ferryTerminalLabel;
        if (s === 'port' || s === 'harbor' || s === 'harbour') return portLabel;
        if (s === 'halt') return haltLabel;
        return s;
      }
      function metaInput(suffix){
        return lbl.querySelector('input[name="' + otherName + '_' + suffix + '"]');
      }
      function clearMeta(){
        ['osm_id','lat','lon','country','type','source'].forEach(function(s){
          const el = metaInput(s);
          if (el) el.value = '';
        });
      }
      function setMeta(st){
        if (!st) { clearMeta(); return; }
        const v = function(k){ return (st && st[k] !== undefined && st[k] !== null) ? String(st[k]) : ''; };
        const m = { osm_id: v('osm_id'), lat: v('lat'), lon: v('lon'), country: v('country'), type: v('type'), source: v('source') };
        Object.keys(m).forEach(function(k){
          const el = metaInput(k);
          if (el) el.value = m[k];
        });
      }
      function hide() {
        box.style.display = 'none';
        box.innerHTML = '';
      }
      function render(stations){
        box.innerHTML = '';
        if (!stations || !stations.length) { hide(); return; }
        const stStations = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() === 'station'; });
        const stOthers = stations.filter(function(st){ return String((st && st.type) || '').toLowerCase() !== 'station'; });
        const shown = (stStations.length >= 5) ? stStations : stStations.concat(stOthers);
        shown.slice(0, 10).forEach(function(st){
          const btn = document.createElement('button');
          btn.type = 'button';
          const nm = (st && st.name) ? String(st.name) : '';
          const cc = (st && st.country) ? String(st.country) : '';
          const tp = (st && st.type) ? String(st.type) : '';
          btn.appendChild(document.createTextNode(nm || <?= json_encode($unknownPlaceLabel, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>));
          if (cc || tp) {
            const meta = document.createElement('div');
            meta.className = 'tc6-small tc6-muted';
            meta.textContent = [cc, niceType(tp)].filter(Boolean).join(' · ');
            btn.appendChild(document.createElement('br'));
            btn.appendChild(meta);
          }
          btn.addEventListener('click', function(){
            if (nm) input.value = nm;
            setMeta(st);
            hide();
          });
          box.appendChild(btn);
        });
        box.style.display = 'block';
      }
      async function fetchStations(){
        if ((sel.value || '') !== 'other') { hide(); return; }
        const q = (input.value || '').trim();
        if (q.length < 2) { hide(); return; }
        const cc = (stationCountryDefault || '').trim().toUpperCase();
        if (!cc && q.length < 4) { hide(); return; }
        function buildUrl(country){
          const u = new URL(stationsSearchUrl, window.location.origin);
          u.searchParams.set('q', q);
          if (country) u.searchParams.set('country', country);
          u.searchParams.set('limit', '10');
          return u;
        }
        if (ctrl) { try { ctrl.abort(); } catch (e) {} }
        ctrl = new AbortController();
        try {
          let res = await fetch(buildUrl(cc).toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
          if (!res.ok) { hide(); return; }
          let js = await res.json();
          let stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
          if ((!stations || !stations.length) && cc) {
            res = await fetch(buildUrl('').toString(), { signal: ctrl.signal, headers: { 'Accept': 'application/json' } });
            if (res.ok) {
              js = await res.json();
              stations = js && js.data && Array.isArray(js.data.stations) ? js.data.stations : [];
            }
          }
          render(stations);
        } catch (e) {}
      }
      input.addEventListener('input', function(){
        clearMeta();
        if (timer) clearTimeout(timer);
        timer = setTimeout(fetchStations, 200);
      });
      input.addEventListener('focus', function(){
        if (box.innerHTML.trim() !== '' && (sel.value || '') === 'other') box.style.display = 'block';
      });
      input.addEventListener('blur', function(){ setTimeout(hide, 180); });
      box.addEventListener('mousedown', function(e){ e.preventDefault(); });
      sel.addEventListener('change', function(){
        if ((sel.value || '') !== 'other') hide();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    updateReveal();
    initStationAutocomplete();
    mapsInit();
    updateResolutionVisibility();
    try { syncMapsOriginFromResolution(); } catch (e) {}
  });

  document.addEventListener('change', function(e){
    if (!e.target || !e.target.name) return;
    handleResets(e.target);
    updateReveal();
    updateResolutionVisibility();
    syncMapsOriginFromResolution();
  });
})();
</script>
<?php
$content = ob_get_clean();

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
    'nextHint' => '',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel') + [
    'context' => $travelStateLabel,
    'brandName' => 'TrainClaim',
    'brandMark' => 'TC',
]);
