<?php
/**
 * TC6 rail incident step.
 *
 * This is a visual wrapper around the existing CakePHP rail incident form.
 * The content, field names and conditional structure stay aligned with legacy.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$journey = $journey ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$nationalPolicy = $nationalPolicy ?? null;
$incidentPrevAction = (string)($incidentPrevAction ?? 'entitlements');
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$pageTranslations = (array)($pageTranslations ?? []);

if ($uiLanguage === 'en') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Ongoing journey',
        'Afsluttet rejse' => 'Completed journey',
        'Tilbagebetaling' => 'Refund',
        'Ombooking hurtigst muligt' => 'Rerouting as soon as possible',
        'Ombooking senere' => 'Rerouting later',
        'Intet reelt valg' => 'No real choice',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Ikke valgt endnu' => 'Not selected yet',
        'Start & Rejsestatus' => 'Start & journey status',
        'Billet / Ticketless + pris' => 'Ticket / ticketless + price',
        'Rejseoplysninger' => 'Journey details',
        'Vaelg afgang + rail-vurdering' => 'Choose departure + rail assessment',
        'Incident' => 'Incident',
        'Spor efter incident' => 'Track after incident',
        'Mad og Hotel' => 'Meals and hotel',
        'Nedgradering' => 'Downgrade',
        'Kompensation' => 'Compensation',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Afsluttet rejse' => 'Trajet termine',
        'Tilbagebetaling' => 'Remboursement',
        'Ombooking hurtigst muligt' => 'Reacheminement au plus vite',
        'Ombooking senere' => 'Reacheminement plus tard',
        'Intet reelt valg' => 'Aucun vrai choix',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Start & Rejsestatus' => 'Depart et statut du voyage',
        'Billet / Ticketless + pris' => 'Billet / sans billet + prix',
        'Rejseoplysninger' => 'Informations de voyage',
        'Vaelg afgang + rail-vurdering' => 'Choix du depart + evaluation rail',
        'Incident' => 'Incident',
        'Spor efter incident' => 'Suivi apres incident',
        'Mad og Hotel' => 'Repas et hotel',
        'Nedgradering' => 'Declassement',
        'Kompensation' => 'Indemnisation',
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Ved ikke' => 'Je ne sais pas',
        'Trin ' => 'Etape ',
        ' trin' => ' etapes',
        'Rail haendelse + foreloebig vurdering' => 'Incident rail + evaluation preliminaire',
        'Rail hændelse + foreløbig vurdering' => 'Incident rail + evaluation preliminaire',
        'Cykel og bagage (Art. 6)' => 'Velo et bagages (art. 6)',
        'Svarene her aktiverer Art. 18/20 ved cykel-problemer.' => 'Ces reponses activent les art. 18/20 en cas de probleme lie au velo.',
        'Svar ud fra det, der er sket indtil nu.' => 'Repondez selon ce qui s est passe jusqu a present.',
        'Svar ud fra hvad der faktisk skete.' => 'Repondez selon ce qui s est reellement passe.',
        '1. Har du en cykel med paa rejsen?' => '1. Avez-vous un velo avec vous pendant le voyage ?',
        '1. Havde du en cykel med paa rejsen?' => '1. Aviez-vous un velo avec vous pendant le voyage ?',
        'Cyklen er en del af sagen og kan aabne Art. 18/20-sporet.' => 'Le velo fait partie du dossier et peut ouvrir le parcours des art. 18/20.',
        'Spring cykelspoeret over og fortsaet med den oevrige haendelse.' => 'Ignorez le parcours velo et poursuivez avec l incident principal.',
        '2. Forsinkede cyklen eller dens haandtering dig?' => '2. Le velo ou sa prise en charge vous a-t-il retarde ?',
        'Cyklen eller dens haandtering blev en del af forsinkelsen eller problemerne paa rejsen.' => 'Le velo ou sa prise en charge a contribue au retard ou aux problemes du voyage.',
        'Cyklen var med, men skabte ikke i sig selv ekstra forsinkelse eller driftshindring.' => 'Le velo etait present, mais n a pas lui-meme cause de retard supplementaire ou de probleme d exploitation.',
        '3. Har du reserveret plads til cyklen?' => '3. Avez-vous reserve une place pour le velo ?',
        '3. Havde du reserveret plads til cyklen?' => '3. Aviez-vous reserve une place pour le velo ?',
        'Du havde en konkret reservation eller plads til cyklen, som sagen kan vurderes ud fra.' => 'Vous aviez une reservation ou une place concrete pour le velo, utilisable pour evaluer le dossier.',
        'Vi afklarer derfor, om toget overhovedet kraevede cykelreservation.' => 'Nous clarifions donc si ce train exigeait une reservation velo.',
        '4. Er det et tog, hvor der kraeves cykelreservation?' => '4. S agit-il d un train ou la reservation velo est obligatoire ?',
        '4. Var det et tog, hvor der kraevedes cykelreservation?' => '4. S agissait-il d un train ou la reservation velo etait obligatoire ?',
        'Toget kraevede reservation, saa manglende plads kan vaere forventelig efter reglerne.' => 'Le train exigeait une reservation ; l absence de place peut donc etre attendue au regard des regles.',
        'Cyklen burde kunne medtages uden saerskilt reservation paa denne afgang.' => 'Le velo aurait du pouvoir etre transporte sans reservation specifique sur ce depart.',
        'Du eller cyklen blev afvist, selv om du forsoegte at rejse med den paagaaeldende afgang.' => 'Vous ou votre velo avez ete refuse, meme si vous avez tente de voyager sur ce depart.',
        '6. Informerede operatoeren dig om aarsagen?' => '6. L operateur vous a-t-il informe de la raison ?',
        'Operatoeren oplyste en konkret begrundelse, som vi kan registrere nedenfor.' => 'L operateur a donne une raison concrete que nous pouvons enregistrer ci-dessous.',
        'Afvisningen skete uden en tydelig forklaring fra operatoeren.' => 'Le refus est intervenu sans explication claire de l operateur.',
        '- vaelg -' => '- choisir -',
        '- Vaelg -' => '- Choisir -',
        'PMR / saerlig assistance' => 'PMR / assistance particuliere',
        'Hvis bestilt hjaelp ikke blev leveret, kan Art. 18/20 aktiveres automatisk.' => 'Si l assistance reservee n a pas ete fournie, les art. 18/20 peuvent etre actives automatiquement.',
        'Hvis bestilt hjaelp ikke blev leveret, kan Art. 18/20 aktiveres automatisk. Fik du den assistance, du havde ret til?' => 'Si l assistance reservee n a pas ete fournie, les art. 18/20 peuvent etre actives automatiquement. Avez-vous recu l assistance a laquelle vous aviez droit ?',
        '4. Manglede der PMR-faciliteter, som var lovet foer koebet?' => '4. Des facilites PMR promises avant l achat manquaient-elles ?',
        'En lovet PMR-facilitet eller ydelse manglede, selv om den indgik foer koebet.' => 'Une facilite ou prestation PMR promise manquait, meme si elle faisait partie de l achat.',
        'Afbrydelser/forsinkelser foer koeb' => 'Perturbations / retards avant l achat',
        'Var der meddelt afbrydelse eller forsinkelse foer dit koeb?' => 'Une interruption ou un retard avait-il ete annonce avant votre achat ?',
        'Hvis ja: hvor blev det vist?' => 'Si oui : ou etait-ce indique ?',
        'Ja, i app' => 'Oui, dans l application',
        'Ja, i toget' => 'Oui, dans le train',
        'Ja, paa station' => 'Oui, en gare',
        'Haendelsestype' => 'Type d incident',
        'Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.' => 'Choisissez l incident qui a touche votre train. Il sert a activer l evaluation standard des art. 18/20.',
        'Haendelsestype (vaelg en)' => 'Type d incident (choisir une option)',
        'Forsinkelse' => 'Retard',
        'Toget koerte, men du blev forsinket ved slutdestinationen.' => 'Le train a circule, mais vous etes arrive en retard a destination finale.',
        'Aflysning' => 'Annulation',
        'Toget eller den relevante del af rejsen blev aflyst.' => 'Le train, ou la partie pertinente du voyage, a ete annule.',
        'Har du faaet besked om mindst 60 minutters forsinkelse ved endelig destination?' => 'Avez-vous ete informe d un retard d au moins 60 minutes a destination finale ?',
        'Fik du besked om mindst 60 minutters forsinkelse ved endelig destination?' => 'Avez-vous ete informe d un retard d au moins 60 minutes a destination finale ?',
        'Rejseplanen eller operatoerens besked peger paa mindst 60 minutters forsinkelse.' => 'L horaire ou le message de l operateur indique un retard d au moins 60 minutes.',
        'Nej / ved ikke' => 'Non / je ne sais pas',
        'Den faktiske forsinkelse ved endelig destination er allerede mindst 60 minutter.' => 'Le retard reel a destination finale est deja d au moins 60 minutes.',
        'Der er endnu ikke en sikker faktisk 60+ forsinkelse at bygge EU-sporet paa.' => 'Il n y a pas encore de retard reel certain de 60 minutes ou plus pour fonder le parcours UE.',
        'Mistet forbindelse' => 'Correspondance manquee',
        'Systemet har fundet en mulig mistet forbindelse pga. valgt afgang og dens forsinkelse. Bekraeft nedenfor, om forbindelsen faktisk blev misset.' => 'Le systeme a detecte une possible correspondance manquee a cause du depart choisi et de son retard. Confirmez ci-dessous si la correspondance a reellement ete manquee.',
        'Ved forsinkelse bruger vi 60+-spoergsmaalene ovenfor som hovedgate. Kun hvis begge delay-svar er nej, afklarer vi her, om det missede skift alligevel gav 60+ til slutdestination.' => 'En cas de retard, les questions 60+ ci-dessus servent de filtre principal. Seulement si les deux reponses sont non, nous verifions ici si la correspondance manquee a tout de meme entraine 60+ minutes a destination finale.',
        'Ved aflysning bruges dette kun til forbindelses- og stationskontekst. Art. 18/20 aabnes allerede af aflysningen, saa du skal ikke svare paa et ekstra 60+-spoergsmaal her.' => 'En cas d annulation, ceci sert seulement au contexte de correspondance et de gare. Les art. 18/20 sont deja ouverts par l annulation, donc aucune question 60+ supplementaire n est necessaire ici.',
        'Den registrerede forbindelse gik tabt paa grund af haendelsen.' => 'La correspondance enregistree a ete manquee a cause de l incident.',
        'Forsinkelsen fra det missede skift er endnu ikke over 60 minutter, eller du mangler svar.' => 'Le retard lie a la correspondance manquee ne depasse pas encore 60 minutes, ou il manque une reponse.',
        'Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.' => 'Si non, des regimes nationaux peuvent encore etre pertinents selon le pays.',
        'Henviser operatoeren til ekstraordinaere forhold (Art. 19(10))?' => 'L operateur invoque-t-il des circonstances extraordinaires (art. 19(10)) ?',
        'Henviste operatoeren til ekstraordinaere forhold (Art. 19(10))?' => 'L operateur a-t-il invoque des circonstances extraordinaires (art. 19(10)) ?',
        'Du kender endnu ikke operatoerens begrundelse.' => 'Vous ne connaissez pas encore la justification de l operateur.',
        'Hvis ja: vaelg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)' => 'Si oui : choisissez le type (utilise pour appliquer correctement l exception ; par exemple une greve du personnel propre n exclut pas l indemnisation)',
        '- Vaelg type -' => '- Choisir le type -',
        'forsinkelse (eller aflysning).' => 'retard (ou annulation).',
        'ukendt (kraever land + scope).' => 'inconnu (necessite le pays et le champ d application).',
        'Ja' => 'Oui',
        'Nej' => 'Non',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$translateIncident = static function ($text) use ($pageTranslations) {
    if (!is_string($text)) {
        return $text;
    }
    if (isset($pageTranslations[$text])) {
        return $pageTranslations[$text];
    }
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $pageTranslations[$decoded] ?? $text;
};

$v = static fn(string $key): string => (string)($form[$key] ?? '');

$articles = (array)($profile['articles'] ?? []);
$euArt18Supported = ($articles['art18'] ?? true) !== false;
$euArt20Supported = ($articles['art20_2'] ?? true) !== false;
$euFlowSupported = $euArt18Supported || $euArt20Supported;
$art9On = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;

$bikeWasPresentValue = strtolower(trim((string)($form['bike_was_present'] ?? '')));
$bikeReservationMadeValue = strtolower(trim((string)($form['bike_reservation_made'] ?? '')));
$bikeReservationRequiredValue = strtolower(trim((string)($form['bike_reservation_required'] ?? '')));
$bikeQ3Visible = $art91On && $bikeWasPresentValue === 'yes';
$bikeQ4Visible = $bikeQ3Visible && $bikeReservationMadeValue === 'no';
$bikeFollowupVisible = $art92On
    && $bikeWasPresentValue === 'yes'
    && (
        $bikeReservationMadeValue === 'yes'
        || ($bikeReservationMadeValue === 'no' && $bikeReservationRequiredValue === 'no')
    );

$pmrBookedValue = strtolower(trim((string)($form['pmr_booked'] ?? '')));
$pmrDeliveredValue = strtolower(trim((string)($form['pmr_delivered_status'] ?? '')));
$pmrQ4Visible = $art92On && (
    $pmrBookedValue === 'no'
    || ($pmrBookedValue === 'yes' && in_array($pmrDeliveredValue, ['yes', 'no'], true))
);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isOngoing = $travelState === 'ongoing';
$isCompleted = $travelState === 'completed';
$isPreview = !empty($flowPreview);

$transportMode = strtolower((string)($form['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail')));
$entryVariant = strtolower((string)($flags['entry_variant'] ?? ($meta['entry_variant'] ?? '')));

$bikeHint = $isOngoing ? 'Svar ud fra det, der er sket indtil nu.' : ($isCompleted ? 'Svar ud fra hvad der faktisk skete.' : '');
$pmrHint = $isOngoing ? 'Har du faaet den assistance, du har brug for indtil nu?' : ($isCompleted ? 'Fik du den assistance, du havde ret til?' : '');
$bikeAutoDetected = !empty($meta['_auto']['bike_booked']) || !empty($meta['_bike_detection']);
$pmrAutoDetected = !empty($meta['_auto']['pmr_user']) || !empty($meta['_pmr_detection']) || !empty($meta['_pmr_detected']);

$railIncidentSeed = (array)($meta['rail_incident_seed'] ?? []);
$railProblemAnchor = (array)($meta['rail_problem_anchor'] ?? []);
$selectedRailDeparture = (array)($meta['rail_selected_departure'] ?? []);

$railSeedIncidentType = strtolower(trim((string)($railIncidentSeed['incident_type'] ?? 'unknown')));
$railSeedTransferCount = is_numeric($railIncidentSeed['transfer_count'] ?? null) ? (int)$railIncidentSeed['transfer_count'] : 0;
$railSeedMissedConnectionSuspected = !empty($railIncidentSeed['missed_connection_suspected']);

$railProblemAnchorType = strtolower(trim((string)($railProblemAnchor['type'] ?? '')));
$railProblemAnchorStation = trim((string)($railProblemAnchor['station_name'] ?? ''));
$railProblemAnchorLabel = trim((string)($railProblemAnchor['label'] ?? ''));

$railProblemAnchorSummary = match ($railProblemAnchorType) {
    'before_departure' => ($railProblemAnchorStation !== '' ? ('problem foer afgang fra ' . $railProblemAnchorStation) : 'problem foer afgang'),
    'transfer' => ($railProblemAnchorLabel !== '' ? strtolower($railProblemAnchorLabel) : ($railProblemAnchorStation !== '' ? ('problem ved skift i ' . $railProblemAnchorStation) : 'problem ved skift')),
    'en_route' => 'problem senere paa den valgte kontrakt',
    default => '',
};

$railSeedMain = match ($railSeedIncidentType) {
    'cancellation', 'partial_cancellation', 'replacement_transport' => 'cancellation',
    'delay' => 'delay',
    default => '',
};

$railIncidentMainValue = $v('incident_main');
if ($railIncidentMainValue === '' && $railSeedMain !== '') {
    $railIncidentMainValue = $railSeedMain;
}

$railSeedArrivalDelay = is_numeric($railIncidentSeed['arrival_delay_minutes_seed'] ?? null) ? (int)$railIncidentSeed['arrival_delay_minutes_seed'] : null;
$railSeedStatus = strtolower(trim((string)($selectedRailDeparture['status'] ?? 'unknown')));

$railExpectedDelay60Value = $v('expected_delay_60');
if ($railExpectedDelay60Value === '' && $railSeedMain === 'delay' && $railSeedArrivalDelay !== null) {
    $railExpectedDelay60Value = $railSeedArrivalDelay >= 60 ? 'yes' : 'no';
}

$railDelayAlready60Value = $v('delay_already_60');
if ($railDelayAlready60Value === '' && $railSeedMain === 'delay' && $railSeedArrivalDelay !== null && in_array($railSeedStatus, ['arrived'], true)) {
    $railDelayAlready60Value = $railSeedArrivalDelay >= 60 ? 'yes' : 'no';
}

$railExpectedDelayPrompt = $isCompleted
    ? 'Fik du besked om mindst 60 minutters forsinkelse ved endelig destination?'
    : 'Har du faaet besked om mindst 60 minutters forsinkelse ved endelig destination?';
$railActualDelayPrompt = $isCompleted
    ? 'Ankom du mindst 60 minutter senere til din endelige destination?'
    : 'Er du allerede mindst 60 minutter forsinket i forhold til din endelige destination?';
$railActualDelayNoLabel = $isCompleted ? 'Nej / ved ikke' : 'Nej';
$railActualDelayNote = $isCompleted
    ? 'Hvis du ikke kender det praecise minutantal endnu, kan du fortsaette og justere senere i sagen.'
    : 'Tip: Hvis du ikke ved det endnu, kan du fortsaette og opdatere senere.';
$railMissedConnectionPrompt = match (true) {
    $railProblemAnchorType === 'transfer' && $isCompleted => 'Blev den valgte forbindelse faktisk misset?',
    $railProblemAnchorType === 'transfer' => 'Er forbindelsen ved problemstedet faktisk misset?',
    $isCompleted => 'Mistede du senere en planlagt videre forbindelse pga. haendelsen?',
    default => 'Betoed haendelsen, at du senere mistede en planlagt videre forbindelse?',
};
$railMissedConnectionDelayPrompt = $isCompleted
    ? 'Medfoerte den mistede forbindelse, at du ankom mindst 60 minutter senere til din endelige destination?'
    : 'Betyder den mistede forbindelse, at du forventer at ankomme mindst 60 minutter senere til din endelige destination?';

$railTransferStations = [];
foreach ((array)(($selectedRailDeparture['raw'] ?? [])['transfer_station_names'] ?? []) as $transferStationName) {
    $transferStationName = trim((string)$transferStationName);
    if ($transferStationName === '') {
        continue;
    }
    $railTransferStations[] = $transferStationName;
}
$normalizedSelectedJourney = (array)($journey['selected_journey'] ?? []);
foreach ((array)($normalizedSelectedJourney['connections'] ?? []) as $normalizedConnection) {
    if (!is_array($normalizedConnection)) {
        continue;
    }
    $transferStationName = trim((string)(
        ($normalizedConnection['boundary_from'] ?? [])['name']
        ?? ($normalizedConnection['boundary_to'] ?? [])['name']
        ?? ''
    ));
    if ($transferStationName !== '') {
        $railTransferStations[] = $transferStationName;
    }
}
$railTransferStations = array_values(array_unique($railTransferStations));
$railTransferStationsSummary = implode(' -> ', $railTransferStations);

$missedConnectionStation = trim((string)($form['missed_connection_station'] ?? ''));
$missedConnectionPick = trim((string)($form['missed_connection_pick'] ?? ''));
$missedConnectionChosen = $missedConnectionStation !== '' || $missedConnectionPick !== '';
$railIncidentMissedValue = strtolower(trim((string)($form['incident_missed'] ?? ($incident['missed'] ?? ($missedConnectionChosen ? 'yes' : 'no')))));
if (!in_array($railIncidentMissedValue, ['yes', 'no'], true)) {
    $railIncidentMissedValue = $missedConnectionChosen ? 'yes' : 'no';
}
$railMissedConnectionHasTransferAnchor = $railProblemAnchorType === 'transfer' && $missedConnectionChosen;
$railCanChooseFollowOnConnection = !$railMissedConnectionHasTransferAnchor && !empty($railTransferStations);
$railMissedConnectionIntro = $railMissedConnectionHasTransferAnchor
    ? 'Hvis problemet opstod ved et skift, hentes fokuspunktet fra TRIN 3. Her bekraefter du kun, om den valgte forbindelse faktisk blev misset.'
    : 'Hvis haendelsen foer afgang eller senere paa kontrakten betoed, at du mistede en planlagt videre forbindelse, registrerer du det her.';
$railMissedConnectionReferenceNote = $railMissedConnectionHasTransferAnchor
    ? 'Hvis problemet opstod ved et skift, kommer fokuspunktet nu fra TRIN 3 og bruges kun som reference her.'
    : 'TRIN 3 har her kun valgt et problemanker. Hvis du senere mistede en videre forbindelse, vaelger du den konkrete skifteforbindelse her.';
$railMissedConnectionNoChoiceNote = $railProblemAnchorType === 'transfer'
    ? 'Ingen forbindelse valgt endnu. Gaa tilbage til TRIN 3, hvis problemet opstod ved et skift, og vaelg fokuspunktet der.'
    : 'Der er endnu ikke valgt en konkret videre forbindelse. Hvis den tidlige haendelse senere medfoerte et misset skift, vaelger du det her nedenfor.';
$railMissedConnectionDelayNote = 'Ved forsinkelse bruger vi 60+-spoergsmaalene ovenfor som hovedgate. Kun hvis begge delay-svar er nej, afklarer vi her, om det missede skift alligevel gav 60+ til slutdestination.';
$railMissedConnectionCancellationNote = 'Ved aflysning bruges dette kun til forbindelses- og stationskontekst. Art. 18/20 aabnes allerede af aflysningen, saa du skal ikke svare paa et ekstra 60+-spoergsmaal her.';
$railShowMissedConnectionBlock = !empty($railTransferStations) || $railSeedMissedConnectionSuspected || $railProblemAnchorType === 'transfer' || $missedConnectionChosen;

$nationalCutoff = null;
$nationalThr50 = null;
try {
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['25'])) {
        $nationalCutoff = (int)$nationalPolicy['thresholds']['25'];
    }
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['50'])) {
        $nationalThr50 = (int)$nationalPolicy['thresholds']['50'];
    }
} catch (\Throwable $e) {
    $nationalCutoff = null;
}

$exc0 = strtolower(trim((string)($form['operatorExceptionalCircumstances'] ?? '')));
$excType0 = trim((string)($form['operatorExceptionalType'] ?? ''));
$compBlockedByFM = ($exc0 === 'yes') && ($excType0 === '' || $excType0 !== 'own_staff_strike');

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
$currentStep = (int)($currentStep ?? 5);
$doneSteps = $doneSteps ?? [];
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = $backUrl ?? $this->Url->build(['action' => $incidentPrevAction, '?' => $flowQuery]);
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ' trin'));

$travelStateLabel = $isCompleted ? 'Afsluttet rejse' : 'Igangvaerende rejse';
$context = $travelStateLabel;
$brandName = 'TrainClaim';
$brandMark = 'TC';
$stats = $stats ?? [
    ['Flow', $travelStateLabel, null],
    ['Transport', strtoupper($transportMode ?: 'rail'), null],
];

$railOperatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $selectedRailDeparture['operator'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $railOperatorLabel = $candidateOperator;
        break;
    }
}
if ($railOperatorLabel === '') {
    $railOperatorLabel = 'Ikke valgt endnu';
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
    ['Operatør', $railOperatorLabel, null],
];
$summaryRows[2][0] = 'Operat' . "\u{00F8}" . 'r';
if ($uiLanguage !== 'da' && $pageTranslations !== []) {
    $steps = array_map($translateIncident, $steps);
    $context = $translateIncident($context);
    $progressLabel = $currentStep . ' / ' . count($steps) . ' etapes';
    $stats = array_map(static function (array $row) use ($translateIncident): array {
        $row[0] = $translateIncident($row[0] ?? '');
        $row[1] = $translateIncident($row[1] ?? '');

        return $row;
    }, $stats);
    $summaryRows = array_map(static function (array $row) use ($translateIncident): array {
        $row[0] = $translateIncident($row[0] ?? '');
        $row[1] = $translateIncident($row[1] ?? '');

        return $row;
    }, $summaryRows);
}

ob_start();
?>
<?= $this->element('flow_locked_notice') ?>

<?= $this->Form->create(null, [
    'url' => ['action' => 'incident', '?' => $flowQuery],
    'id' => 'incidentStepForm',
    'class' => 'tc6-form tc6-incident',
    'novalidate' => true,
]) ?>

<fieldset<?= $isPreview ? ' style="pointer-events:none"' : '' ?>>
  <style>
    .tc6-incident > .tc6-subtitle,
    .tc6-incident .tc6-step-copy,
    .tc6-incident .tc6-choice-card__sub,
    .tc6-incident .tc6-small.tc6-muted {
      display: none !important;
    }
  </style>

  <div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
  <h1 class="tc6-h1">Rail haendelse + foreloebig vurdering</h1>
  <?php $bikeAutoDetected = false; $pmrAutoDetected = false; ?>

  <div class="tc6-card">
    <div class="tc6-section-label">Cykel og bagage (Art. 6)</div>
    <p class="tc6-step-copy">Svarene her aktiverer Art. 18/20 ved cykel-problemer.<?= $bikeHint !== '' ? ' ' . h($bikeHint) : '' ?></p>
    <?php if ($bikeAutoDetected): ?>
      <div class="tc6-note tc6-note--blue">
        <span class="tc6-small">Auto-note: Billet/OCR ser ud til at naevne cykel. Valget er stadig sat til "Nej" som udgangspunkt. Ret det hvis det er forkert.</span>
      </div>
    <?php endif; ?>

    <div class="tc6-field">
      <div class="tc6-choice-line"><?= h($isOngoing ? '1. Har du en cykel med paa rejsen?' : '1. Havde du en cykel med paa rejsen?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="bike_was_present" value="yes" <?= $v('bike_was_present') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Cyklen er en del af sagen og kan aabne Art. 18/20-sporet.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="bike_was_present" value="no" <?= $v('bike_was_present') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Spring cykelspoeret over og fortsaet med den oevrige haendelse.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?> hide-bike-delay" data-show-if="bike_was_present:yes">
      <div class="tc6-choice-line">2. Forsinkede cyklen eller dens haandtering dig?</div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="bike_delay" value="yes" <?= $v('bike_delay') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Cyklen eller dens haandtering blev en del af forsinkelsen eller problemerne paa rejsen.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="bike_delay" value="no" <?= $v('bike_delay') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Cyklen var med, men skabte ikke i sig selv ekstra forsinkelse eller driftshindring.</span>
          </span>
        </label>
      </div>
    </div>

    <div id="bikeQ3Wrap" class="tc6-field <?= $bikeQ3Visible ? '' : 'tc6-hidden' ?>" data-show-if="bike_was_present:yes">
      <div class="tc6-choice-line"><?= h($isOngoing ? '3. Har du reserveret plads til cyklen?' : '3. Havde du reserveret plads til cyklen?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="bike_reservation_made" value="yes" <?= $v('bike_reservation_made') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Du havde en konkret reservation eller plads til cyklen, som sagen kan vurderes ud fra.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="bike_reservation_made" value="no" <?= $v('bike_reservation_made') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Vi afklarer derfor, om toget overhovedet kraevede cykelreservation.</span>
          </span>
        </label>
      </div>
    </div>

    <div id="bikeQ4Wrap" class="tc6-field <?= $bikeQ4Visible ? '' : 'tc6-hidden' ?>" data-show-if="bike_reservation_made:no">
      <div class="tc6-choice-line"><?= h($isOngoing ? '4. Er det et tog, hvor der kraeves cykelreservation?' : '4. Var det et tog, hvor der kraevedes cykelreservation?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="bike_reservation_required" value="yes" <?= $v('bike_reservation_required') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Toget kraevede reservation, saa manglende plads kan vaere forventelig efter reglerne.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="bike_reservation_required" value="no" <?= $v('bike_reservation_required') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Cyklen burde kunne medtages uden saerskilt reservation paa denne afgang.</span>
          </span>
        </label>
      </div>
    </div>

    <div id="bikeAfter2B" class="<?= $bikeFollowupVisible ? '' : 'tc6-hidden' ?>">
      <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?>">
        <div class="tc6-choice-line"><?= h($isOngoing ? '5. Er du blevet naegtet at tage cyklen med?' : '5. Blev du naegtet at tage cyklen med?') ?></div>
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="bike_denied_boarding" value="yes" <?= $v('bike_denied_boarding') === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Ja</span>
              <span class="tc6-choice-card__sub">Du eller cyklen blev afvist, selv om du forsoegte at rejse med den paagaaeldende afgang.</span>
            </span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="bike_denied_boarding" value="no" <?= $v('bike_denied_boarding') === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Nej</span>
              <span class="tc6-choice-card__sub">Problemet handler ikke om direkte afvisning af at tage cyklen med.</span>
            </span>
          </label>
        </div>
      </div>

      <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?>" data-show-if="bike_denied_boarding:yes">
        <div class="tc6-choice-line"><?= h($isOngoing ? '6. Har operatoeren informeret dig om aarsagen?' : '6. Informerede operatoeren dig om aarsagen?') ?></div>
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="bike_refusal_reason_provided" value="yes" <?= $v('bike_refusal_reason_provided') === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Ja</span>
              <span class="tc6-choice-card__sub">Operatoeren oplyste en konkret begrundelse, som vi kan registrere nedenfor.</span>
            </span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="bike_refusal_reason_provided" value="no" <?= $v('bike_refusal_reason_provided') === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Nej</span>
              <span class="tc6-choice-card__sub">Afvisningen skete uden en tydelig forklaring fra operatoeren.</span>
            </span>
          </label>
        </div>
      </div>

      <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?>" data-show-if="bike_refusal_reason_provided:yes">
        <label class="tc6-label" for="bike-refusal-type">7. Hvad var begrundelsen for afvisningen?</label>
        <select id="bike-refusal-type" name="bike_refusal_reason_type" class="tc6-select">
          <option value="">- vaelg -</option>
          <option value="capacity" <?= $v('bike_refusal_reason_type') === 'capacity' ? 'selected' : '' ?>>Kapacitet</option>
          <option value="equipment" <?= $v('bike_refusal_reason_type') === 'equipment' ? 'selected' : '' ?>>Materiel tillader det ikke</option>
          <option value="weight_dim" <?= $v('bike_refusal_reason_type') === 'weight_dim' ? 'selected' : '' ?>>Vaegt/dimensioner</option>
          <option value="other" <?= $v('bike_refusal_reason_type') === 'other' ? 'selected' : '' ?>>Andet</option>
        </select>
      </div>

      <div class="tc6-field" data-show-if="bike_refusal_reason_type:other">
        <label class="tc6-label" for="bike-refusal-other">Beskriv kort</label>
        <textarea id="bike-refusal-other" name="bike_refusal_reason_other_text" rows="2" class="tc6-textarea"><?= h($v('bike_refusal_reason_other_text')) ?></textarea>
      </div>
    </div>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-label">PMR / handicap</div>
    <p class="tc6-step-copy">Hvis bestilt hjaelp ikke blev leveret, kan Art. 18/20 aktiveres automatisk.<?= $pmrHint !== '' ? ' ' . h($pmrHint) : '' ?></p>
    <?php if ($pmrAutoDetected): ?>
      <div class="tc6-note tc6-note--blue">
        <span class="tc6-small">Auto-note: Billet/OCR ser ud til at naevne handicap/PMR. Valget er stadig sat til "Nej" som udgangspunkt. Ret det hvis det er forkert.</span>
      </div>
    <?php endif; ?>

    <div class="tc6-field">
      <div class="tc6-choice-line"><?= h($isOngoing ? '1. Har du et handicap eller nedsat mobilitet, som kraever assistance?' : '1. Har du et handicap eller nedsat mobilitet, som kraevede assistance?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Vi afklarer derefter bestilt assistance og eventuelle mangler.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_user" value="no" <?= $v('pmr_user') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">PMR-sporet springes over, og sagen vurderes uden handicapassistance.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field <?= $art91On ? '' : 'tc6-hidden' ?>" data-show-if="pmr_user:yes">
      <div class="tc6-choice-line"><?= h($isOngoing ? '2. Har du bestilt assistance foer rejsen?' : '2. Bestilte du assistance foer rejsen?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_booked" value="yes" <?= $v('pmr_booked') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Der var bestilt assistance paa forhaand, saa vi kan vurdere om den blev leveret korrekt.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_booked" value="no" <?= $v('pmr_booked') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Der var ikke bestilt assistance, men lovede faciliteter kan stadig vaere relevante.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?>" data-show-if="pmr_booked:yes">
      <div class="tc6-choice-line"><?= h($isOngoing ? '3. Er assistancen blevet leveret?' : '3. Blev assistancen leveret?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_delivered_status" value="yes" <?= $v('pmr_delivered_status') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Den aftalte assistance blev leveret, som den skulle ved rejsen.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_delivered_status" value="no" <?= $v('pmr_delivered_status') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Den bestilte assistance udeblev helt eller delvist.</span>
          </span>
        </label>
      </div>
    </div>

    <div id="pmrQ4Wrap" class="tc6-field <?= $pmrQ4Visible ? '' : 'tc6-hidden' ?>">
      <div class="tc6-choice-line"><?= h($isOngoing ? '4. Mangler der PMR-faciliteter, som var lovet foer koebet?' : '4. Manglede der PMR-faciliteter, som var lovet foer koebet?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_promised_missing" value="yes" <?= $v('pmr_promised_missing') === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">En lovet PMR-facilitet eller ydelse manglede, selv om den indgik foer koebet.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_promised_missing" value="no" <?= $v('pmr_promised_missing') === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Der mangler ikke nogen kendt lovet PMR-facilitet i den konkrete rejse.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field" data-show-if="pmr_promised_missing:yes">
      <label class="tc6-label" for="pmr-facility-details">Beskriv kort</label>
      <textarea id="pmr-facility-details" name="pmr_facility_details" rows="2" class="tc6-textarea"><?= h($v('pmr_facility_details')) ?></textarea>
    </div>
  </div>

  <?php
    $preinformedDisruptionValue = strtolower(trim((string)($form['preinformed_disruption'] ?? 'no')));
    if ($preinformedDisruptionValue === '' || $preinformedDisruptionValue === 'unknown') {
        $preinformedDisruptionValue = 'no';
    }
    $preinfoChannelValue = trim((string)($form['preinfo_channel'] ?? ''));
    $realtimeInfoSeenValue = trim((string)($form['realtime_info_seen'] ?? ''));
    $realtimeInfoOptions = [
        'app' => 'Ja, i app',
        'train' => 'Ja, i toget',
        'station' => 'Ja, paa station',
        'no' => 'Nej',
        'unknown' => 'Ved ikke',
    ];
  ?>
  <div class="tc6-card <?= $art91On ? '' : 'tc6-hidden' ?>">
    <div class="tc6-section-label">Afbrydelser/forsinkelser foer koeb</div>

    <div class="tc6-field">
      <div class="tc6-choice-line">Var der meddelt afbrydelse eller forsinkelse foer dit koeb?</div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="preinformed_disruption" value="yes" <?= $preinformedDisruptionValue === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="preinformed_disruption" value="no" <?= $preinformedDisruptionValue === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field" data-show-if="preinformed_disruption:yes">
      <label class="tc6-label" for="rail-preinfo-channel">Hvis ja: hvor blev det vist?</label>
      <select id="rail-preinfo-channel" name="preinfo_channel" class="tc6-select">
        <option value="">- Vaelg -</option>
        <option value="website" <?= $preinfoChannelValue === 'website' ? 'selected' : '' ?>>Hjemmeside</option>
        <option value="journey_planner" <?= $preinfoChannelValue === 'journey_planner' ? 'selected' : '' ?>>Rejseplan</option>
        <option value="app" <?= $preinfoChannelValue === 'app' ? 'selected' : '' ?>>App</option>
        <option value="station" <?= $preinfoChannelValue === 'station' ? 'selected' : '' ?>>Station</option>
        <option value="other" <?= $preinfoChannelValue === 'other' ? 'selected' : '' ?>>Andet</option>
      </select>
    </div>

    <div class="tc6-field <?= $art92On ? '' : 'tc6-hidden' ?>" data-show-if="preinformed_disruption:yes">
      <div class="tc6-choice-line">Saa du realtime-opdateringer under rejsen?</div>
      <div class="tc6-choice-cards tc6-choice-cards--3">
        <?php foreach ($realtimeInfoOptions as $realtimeKey => $realtimeLabel): ?>
          <label class="tc6-choice-card">
            <input type="radio" name="realtime_info_seen" value="<?= h($realtimeKey) ?>" <?= $realtimeInfoSeenValue === $realtimeKey ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title"><?= h($realtimeLabel) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="tc6-card">
    <div class="tc6-section-label">Haendelsestype</div>
    <p class="tc6-step-copy"><?= h($isOngoing ? 'Vaelg den haendelse, der rammer dit tog lige nu. Bruges til at aktivere standard vurdering af Art. 18/20.' : 'Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.') ?></p>

    <div class="tc6-field">
      <div class="tc6-choice-line">Haendelsestype (vaelg en)</div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="incident_main" value="delay" <?= $railIncidentMainValue === 'delay' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Forsinkelse</span>
            <span class="tc6-choice-card__sub">Toget koerte, men du blev forsinket ved slutdestinationen.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="incident_main" value="cancellation" <?= $railIncidentMainValue === 'cancellation' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Aflysning</span>
            <span class="tc6-choice-card__sub">Toget eller den relevante del af rejsen blev aflyst.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field" data-show-if="incident_main:delay">
      <div class="tc6-choice-line"><?= h($railExpectedDelayPrompt) ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="expected_delay_60" value="yes" <?= $railExpectedDelay60Value === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Rejseplanen eller operatoerens besked peger paa mindst 60 minutters forsinkelse.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="expected_delay_60" value="no" <?= $railExpectedDelay60Value === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej / ved ikke</span>
            <span class="tc6-choice-card__sub">Der er endnu ikke en klar besked om 60+ minutter ved slutdestinationen.</span>
          </span>
        </label>
      </div>
    </div>

    <div class="tc6-field" data-show-if="incident_main:delay">
      <div class="tc6-choice-line"><?= h($railActualDelayPrompt) ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--2">
        <label class="tc6-choice-card">
          <input type="radio" name="delay_already_60" value="yes" <?= $railDelayAlready60Value === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Den faktiske forsinkelse ved endelig destination er allerede mindst 60 minutter.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="delay_already_60" value="no" <?= $railDelayAlready60Value === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title"><?= h($railActualDelayNoLabel) ?></span>
            <span class="tc6-choice-card__sub">Der er endnu ikke en sikker faktisk 60+ forsinkelse at bygge EU-sporet paa.</span>
          </span>
        </label>
      </div>
      <div class="tc6-small tc6-muted tc6-mt4"><?= h($railActualDelayNote) ?></div>
    </div>
  </div>

  <?php if ($railShowMissedConnectionBlock): ?>
    <div class="tc6-card" data-show-if="incident_main:delay,cancellation">
      <div class="tc6-section-label">Mistet forbindelse</div>
      <p class="tc6-step-copy"><?= h($railMissedConnectionIntro) ?></p>

      <?php if ($railSeedMissedConnectionSuspected): ?>
        <div class="tc6-note tc6-note--blue">
          <span class="tc6-small">Systemet har fundet en mulig mistet forbindelse pga. valgt afgang og dens forsinkelse. Bekraeft nedenfor, om forbindelsen faktisk blev misset.</span>
        </div>
      <?php endif; ?>

      <div class="tc6-small tc6-muted tc6-hidden rail-missed60-delay-note"><?= h($railMissedConnectionDelayNote) ?></div>
      <div class="tc6-small tc6-muted tc6-hidden rail-missed60-cancellation-note"><?= h($railMissedConnectionCancellationNote) ?></div>

      <?php if ($railTransferStationsSummary !== ''): ?>
        <div class="tc6-small tc6-muted tc6-mt8">Skift i den valgte rejse: <strong><?= h($railTransferStationsSummary) ?></strong></div>
      <?php endif; ?>
      <?php if ($railProblemAnchorSummary !== ''): ?>
        <div class="tc6-small tc6-muted tc6-mt4">Problemsted fra TRIN 3: <strong><?= h($railProblemAnchorSummary) ?></strong></div>
      <?php endif; ?>

      <div class="tc6-small tc6-muted tc6-mt8"><?= h($railMissedConnectionReferenceNote) ?></div>

      <input type="hidden" name="incident_missed" value="<?= $missedConnectionChosen ? 'yes' : 'no' ?>" />

      <?php if ($railMissedConnectionHasTransferAnchor): ?>
        <div class="tc6-small tc6-mt8">
          Registreret forbindelse:
          <strong><?= h($missedConnectionPick !== '' ? $missedConnectionPick : ('Ved skift i ' . $missedConnectionStation)) ?></strong>
        </div>
        <div class="tc6-field">
          <div class="tc6-choice-line"><?= h($railMissedConnectionPrompt) ?></div>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="incident_missed" value="yes" <?= $railIncidentMissedValue === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Ja</span>
                <span class="tc6-choice-card__sub">Den registrerede forbindelse gik tabt paa grund af haendelsen.</span>
              </span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="incident_missed" value="no" <?= $railIncidentMissedValue === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Nej / ved ikke</span>
                <span class="tc6-choice-card__sub">Forbindelsen blev ikke misset, eller du kender endnu ikke udfaldet sikkert.</span>
              </span>
            </label>
          </div>
        </div>
        <div class="tc6-field rail-missed60-wrap" data-show-if="incident_missed:yes">
          <div class="tc6-choice-line"><?= h($railMissedConnectionDelayPrompt) ?></div>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60') === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Ja</span>
                <span class="tc6-choice-card__sub">Det missede skift giver alene 60+ minutter ved din endelige destination.</span>
              </span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60') === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Nej / ved ikke</span>
                <span class="tc6-choice-card__sub">Forsinkelsen fra det missede skift er endnu ikke over 60 minutter, eller du mangler svar.</span>
              </span>
            </label>
          </div>
          <div class="tc6-small tc6-muted tc6-mt4">Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.</div>
        </div>
      <?php else: ?>
        <div class="tc6-small tc6-muted tc6-mt8"><?= h($railMissedConnectionNoChoiceNote) ?></div>
        <div class="tc6-field">
          <div class="tc6-choice-line"><strong>Medfoerte haendelsen ogsaa en mistet videre forbindelse?</strong></div>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="incident_missed" value="yes" <?= $railIncidentMissedValue === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Ja</span>
                <span class="tc6-choice-card__sub">Haendelsen gjorde, at du senere mistede en planlagt videre forbindelse.</span>
              </span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="incident_missed" value="no" <?= $railIncidentMissedValue === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Nej / ved ikke</span>
                <span class="tc6-choice-card__sub">Der er ingen bekræftet mistet forbindelse at bygge videre paa endnu.</span>
              </span>
            </label>
          </div>
        </div>
        <?php if ($railCanChooseFollowOnConnection): ?>
          <div class="tc6-field" data-show-if="incident_missed:yes">
            <label class="tc6-label" for="railMissedConnectionStation"><strong>Hvilken videre forbindelse blev misset?</strong></label>
            <select id="railMissedConnectionStation" name="missed_connection_station" class="tc6-select">
              <option value="">Vaelg skiftestation</option>
              <?php foreach ($railTransferStations as $transferStationName): ?>
                <option value="<?= h($transferStationName) ?>" <?= $missedConnectionStation === $transferStationName ? 'selected' : '' ?>><?= h('Ved skift i ' . $transferStationName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="tc6-field rail-missed60-wrap" data-show-if="incident_missed:yes">
          <div class="tc6-choice-line"><?= h($railMissedConnectionDelayPrompt) ?></div>
          <div class="tc6-choice-cards tc6-choice-cards--2">
            <label class="tc6-choice-card">
              <input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60') === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Ja</span>
                <span class="tc6-choice-card__sub">Det missede skift forklarer 60+ minutters samlet forsinkelse til slutdestinationen.</span>
              </span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60') === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body">
                <span class="tc6-choice-card__title">Nej / ved ikke</span>
                <span class="tc6-choice-card__sub">Forsinkelsen fra det missede skift er endnu ikke afklaret til 60+ minutter.</span>
              </span>
            </label>
          </div>
          <div class="tc6-small tc6-muted tc6-mt4">Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.</div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tc6-card tc6-warn-card">
    <div class="tc6-section-label">Force majeure</div>
    <p class="tc6-step-copy">Udbetaling sker som udgangspunkt kontant. Vouchers accepteres ikke i denne loesning.</p>
    <input type="hidden" name="voucherAccepted" value="no" />

    <?php $exc = (string)($form['operatorExceptionalCircumstances'] ?? ''); ?>
    <div class="tc6-field">
      <div class="tc6-choice-line"><?= h($isCompleted ? 'Henviste operatoeren til ekstraordinaere forhold (Art. 19(10))?' : 'Henviser operatoeren til ekstraordinaere forhold (Art. 19(10))?') ?></div>
      <div class="tc6-choice-cards tc6-choice-cards--3">
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Operatøren henviser til vejrlig, infrastruktur, strejke eller lignende.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Ingen force majeure-begrundelse er givet i sagen.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc === '' || $exc === 'unknown') ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ved ikke</span>
            <span class="tc6-choice-card__sub">Du kender endnu ikke operatoerens begrundelse.</span>
          </span>
        </label>
      </div>
    </div>

    <?php $excType = (string)($form['operatorExceptionalType'] ?? ''); ?>
    <div class="tc6-field" data-show-if="operatorExceptionalCircumstances:yes">
      <label class="tc6-label" for="operator-exceptional-type">Hvis ja: vaelg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)</label>
      <select id="operator-exceptional-type" name="operatorExceptionalType" class="tc6-select">
        <option value="">- Vaelg type -</option>
        <option value="weather" <?= $excType === 'weather' ? 'selected' : '' ?>>Vejr</option>
        <option value="sabotage" <?= $excType === 'sabotage' ? 'selected' : '' ?>>Sabotage</option>
        <option value="infrastructure_failure" <?= $excType === 'infrastructure_failure' ? 'selected' : '' ?>>Infrastrukturfejl</option>
        <option value="third_party" <?= $excType === 'third_party' ? 'selected' : '' ?>>Tredjepart</option>
        <option value="own_staff_strike" <?= $excType === 'own_staff_strike' ? 'selected' : '' ?>>Egen personalestrejke</option>
        <option value="external_strike" <?= $excType === 'external_strike' ? 'selected' : '' ?>>Ekstern strejke</option>
        <option value="other" <?= $excType === 'other' ? 'selected' : '' ?>>Andet</option>
      </select>
    </div>

    <div class="tc6-inline-check">
      <label><input type="checkbox" name="minThresholdApplies" value="1" <?= !empty($form['minThresholdApplies']) ? 'checked' : '' ?> /> Anvend min. taerskel &lt;= 4 EUR (Art. 19(8))</label>
    </div>
  </div>

  <div id="nationalFallbackWrap" class="tc6-card tc6-note--amber tc6-hidden">
    <strong>National ordning (fallback)<?= (!empty($nationalPolicy['name']) ? (': ' . h((string)$nationalPolicy['name'])) : '') ?></strong>
    <div class="tc6-small tc6-mt4">
      EU: Art. 18/20 udloeses typisk ved <strong>&ge;60 min</strong> forsinkelse (eller aflysning).
    </div>
    <div class="tc6-small tc6-mt4">
      National ordning:
      <?php if ($nationalCutoff !== null && $nationalCutoff > 0 && $nationalCutoff < 60): ?>
        kompensation fra <strong><?= (int)$nationalCutoff ?> min</strong><?= ($nationalThr50 !== null && $nationalThr50 > 0) ? (' (naeste band: ' . (int)$nationalThr50 . ' min)') : '' ?>.
      <?php else: ?>
        <span class="tc6-muted">ukendt (kraever land + scope).</span>
      <?php endif; ?>
    </div>

    <div id="nationalFallbackBlockedHint" class="tc6-note tc6-note--blue tc6-hidden tc6-mt8">
      <span class="tc6-small"><strong>Foer vi gaar til national ordning:</strong> Svar paa spoergsmaalet ovenfor om det missede skift giver <strong>&ge;60 min</strong> til din endelige destination.</span>
    </div>

    <?php if ($compBlockedByFM): ?>
      <div class="tc6-note tc6-note--red tc6-mt8">
        <span class="tc6-small"><strong>Bemaerk:</strong> Du har angivet ekstraordinaere forhold (Art. 19(10)). Kompensation kan vaere udelukket (EU + national), men Art. 18/20 kan stadig blive relevant ved <strong>&ge;60 min</strong> eller aflysning.</span>
      </div>
    <?php endif; ?>

    <div id="nationalFallbackInputs" class="tc6-field tc6-mt8">
      <label class="tc6-label" for="nationalDelayMinutes"><?= h($isCompleted ? 'Hvor mange minutter var du forsinket?' : 'Hvor mange minutter var/er du forsinket?') ?></label>
      <input id="nationalDelayMinutes" type="number" name="national_delay_minutes" min="0" step="1" value="<?= h($v('national_delay_minutes')) ?>" placeholder="minutter" class="tc6-input" />
      <input type="hidden" id="nationalDelayReportedAt" name="national_delay_reported_at" value="<?= h($v('national_delay_reported_at')) ?>" />
      <div class="tc6-small tc6-muted tc6-mt4">Denne oplysning bruges kun til national fallback - den aktiverer ikke Art. 18/20.</div>
    </div>

    <?php if ($isOngoing): ?>
      <div id="euReminderWrap" class="tc6-mt12 tc6-hidden">
        <div class="tc6-small"><strong>Reminder (igangvaerende rejse)</strong></div>
        <div class="tc6-small tc6-mt4">
          Hvis forsinkelsen stiger til <strong>60 min</strong>, kan EU-rettigheder (Art. 18/20) blive relevante.
          <span id="euReminderInfo"></span>
        </div>
        <div class="tc6-mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <button type="button" class="tc6-btn tc6-btn--ghost" id="startEuReminder">Start reminder</button>
          <span class="tc6-small tc6-muted" id="euReminderStatus"></span>
        </div>
        <div id="euReminderPrompt" class="tc6-note tc6-note--blue tc6-hidden tc6-mt8">
          <div class="tc6-small"><strong>Reminder</strong></div>
          <div class="tc6-small tc6-mt4">Du kan nu vaere &gt;=60 min forsinket. Er du det?</div>
          <div class="tc6-mt8" style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="tc6-btn tc6-btn--navy" id="euReminderYes">Ja</button>
            <button type="button" class="tc6-btn tc6-btn--ghost" id="euReminderNo">Nej</button>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?= $this->element('tc6/action_bar', [
      'backUrl' => $backUrl,
      'backLabel' => $translateIncident('Tilbage'),
      'nextLabel' => $translateIncident('Naeste trin'),
      'nextVariant' => 'navy',
      'submitName' => '_save',
  ]) ?>
</fieldset>
<?= $this->Form->end() ?>
<?= $this->element('flow_autosave', ['step' => 'incident', 'formSelector' => '#incidentStepForm']) ?>

<script>
let __euReminderTimer = null;
const tc6Forms = window.tc6Forms || {};
const setBlockVisible = tc6Forms.setBlockVisible || function(el, show) {
  if (!el) return;
  el.style.display = show ? 'block' : 'none';
  el.hidden = !show;
};
const showById = tc6Forms.showById || function(id, show) {
  const el = document.getElementById(id);
  if (!el) return;
  setBlockVisible(el, show);
};
const getRadioValue = tc6Forms.getRadioValue || function(name) {
  const checked = document.querySelector('input[name="' + name + '"]:checked');
  return checked ? (checked.value || '') : '';
};
const getVal = tc6Forms.getFieldValue || function(name) {
  const checked = document.querySelector('input[name="' + name + '"]:checked');
  if (checked) return checked.value || '';
  const select = document.querySelector('select[name="' + name + '"]');
  if (select) return select.value || '';
  const input = document.querySelector('input[name="' + name + '"]');
  if (input && input.type !== 'radio' && input.type !== 'checkbox') return input.value || '';
  return '';
};
const clearRadioGroup = function(name) {
  document.querySelectorAll('input[name="' + name + '"]').forEach(function(node) {
    node.checked = false;
  });
};
const clearSelectField = function(name) {
  const select = document.querySelector('select[name="' + name + '"]');
  if (select) {
    select.value = '';
  }
};
const clearTextField = function(name) {
  const field = document.querySelector('textarea[name="' + name + '"], input[name="' + name + '"]');
  if (field && field.type !== 'radio' && field.type !== 'checkbox') {
    field.value = '';
  }
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

function clearEuReminder() {
  if (__euReminderTimer) {
    window.clearTimeout(__euReminderTimer);
    __euReminderTimer = null;
  }
  showById('euReminderPrompt', false);
  const status = document.getElementById('euReminderStatus');
  if (status) status.textContent = '';
}

function updateReveal() {
  updateShowIf(document);

  const after2b = document.getElementById('bikeAfter2B');
  const bikeQ3Wrap = document.getElementById('bikeQ3Wrap');
  const bikeQ4Wrap = document.getElementById('bikeQ4Wrap');
  const presentVal = getRadioValue('bike_was_present');
  const reservationMadeVal = getRadioValue('bike_reservation_made');
  const reservationRequiredVal = getRadioValue('bike_reservation_required');
  if (bikeQ3Wrap) {
    setBlockVisible(bikeQ3Wrap, presentVal === 'yes');
  }
  if (bikeQ4Wrap) {
    const showBikeQ4 = presentVal === 'yes' && reservationMadeVal === 'no';
    setBlockVisible(bikeQ4Wrap, showBikeQ4);
  }
  const showBikeFollowup = (presentVal === 'yes')
    && (reservationMadeVal === 'yes' || (reservationMadeVal === 'no' && reservationRequiredVal === 'no'));
  if (after2b) {
    setBlockVisible(after2b, showBikeFollowup);
  }
  if (presentVal !== 'yes') {
    ['bike_delay', 'bike_reservation_made', 'bike_reservation_required', 'bike_denied_boarding', 'bike_refusal_reason_provided'].forEach(clearRadioGroup);
    clearSelectField('bike_refusal_reason_type');
    clearTextField('bike_refusal_reason_other_text');
  }
  if (reservationMadeVal !== 'no') {
    clearRadioGroup('bike_reservation_required');
  }
  if (!showBikeFollowup) {
    ['bike_denied_boarding', 'bike_refusal_reason_provided'].forEach(clearRadioGroup);
    clearSelectField('bike_refusal_reason_type');
    clearTextField('bike_refusal_reason_other_text');
  }
  if (getRadioValue('bike_refusal_reason_provided') !== 'yes') {
    clearSelectField('bike_refusal_reason_type');
    clearTextField('bike_refusal_reason_other_text');
  }
  if (getVal('bike_refusal_reason_type') !== 'other') {
    clearTextField('bike_refusal_reason_other_text');
  }

  const pmrQ4 = document.getElementById('pmrQ4Wrap');
  if (pmrQ4) {
    const bookedVal = getRadioValue('pmr_booked');
    const deliveredVal = getRadioValue('pmr_delivered_status');
    const showPmrQ4 = (bookedVal === 'no') || (bookedVal === 'yes' && (deliveredVal === 'yes' || deliveredVal === 'no'));
    setBlockVisible(pmrQ4, showPmrQ4);
  }
}

function updateStep4State() {
  const isOngoing = <?= json_encode((bool)$isOngoing) ?>;
  const euFlowSupported = <?= json_encode((bool)$euFlowSupported) ?>;
  const compBlockedByFM = <?= json_encode((bool)$compBlockedByFM) ?>;

  const main = getRadioValue('incident_main');
  const exp60 = getRadioValue('expected_delay_60');
  const already60 = getRadioValue('delay_already_60');
  const missed = getVal('incident_missed');
  const missed60 = getRadioValue('missed_expected_delay_60');
  const fm = getRadioValue('operatorExceptionalCircumstances');
  const fmTypeSel = document.querySelector('select[name="operatorExceptionalType"]');
  const fmType = fmTypeSel ? (fmTypeSel.value || '') : '';
  const fmBlocksComp = (fm === 'yes') && (fmType === '' || fmType !== 'own_staff_strike');

  let euGateFromMain = false;
  if (main === 'cancellation') euGateFromMain = true;
  if (main === 'delay' && (exp60 === 'yes' || already60 === 'yes')) euGateFromMain = true;

  const delayGateResolvedToNo = (main === 'delay') && exp60 === 'no' && already60 === 'no';
  const showMissed60 = (missed === 'yes') && delayGateResolvedToNo;
  document.querySelectorAll('.rail-missed60-wrap').forEach(function(el) {
    setBlockVisible(el, showMissed60);
  });
  document.querySelectorAll('.rail-missed60-delay-note').forEach(function(el) {
    setBlockVisible(el, (main === 'delay') && (missed === 'yes') && !showMissed60);
  });
  document.querySelectorAll('.rail-missed60-cancellation-note').forEach(function(el) {
    setBlockVisible(el, (main === 'cancellation') && (missed === 'yes'));
  });

  let euGate = euGateFromMain;
  if (!euGateFromMain && missed === 'yes' && missed60 === 'yes') euGate = true;

  const delayLastChanceAnswered = (main === 'delay') && (exp60 !== '') && (already60 !== '');
  const delayReadyForFallback = (!euGate) && (main === 'delay') && delayLastChanceAnswered;
  const missedReadyForFallback = (!euGate) && showMissed60 && (missed60 !== '');
  const missedLastChanceUnanswered = showMissed60 && (missed60 === '');
  const showNat = (!euGate) && !missedLastChanceUnanswered && (delayReadyForFallback || missedReadyForFallback) && !fmBlocksComp;

  showById('nationalFallbackWrap', showNat);
  showById('nationalFallbackBlockedHint', false);

  const minsField = document.getElementById('nationalDelayMinutes');
  if (minsField) minsField.disabled = false;
  const mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;

  let canRemind = euFlowSupported && isOngoing && showNat && !Number.isNaN(mins) && mins > 0 && mins < 60;
  if (!canRemind && euFlowSupported && isOngoing && compBlockedByFM && !euGate && !Number.isNaN(mins) && mins > 0 && mins < 60) {
    canRemind = true;
  }

  showById('euReminderWrap', canRemind);
  const info = document.getElementById('euReminderInfo');
  if (info) {
    info.textContent = canRemind ? (' (ca. ' + (60 - mins) + ' min til 60, hvis forsinkelsen ikke aendrer sig)') : '';
  }
  if (!canRemind) {
    clearEuReminder();
  }

  if (isOngoing && showNat && !Number.isNaN(mins) && mins >= 60) {
    showById('euReminderWrap', true);
    showById('euReminderPrompt', true);
    const status = document.getElementById('euReminderStatus');
    if (status) status.textContent = 'Du har angivet ' + mins + ' min. Bekraeft om du nu er >=60 min forsinket.';
  }
}

function setLiveTextAll(selector, value) {
  document.querySelectorAll(selector).forEach(function(node) {
    node.textContent = value;
  });
}

function getRailIncidentPreviewState() {
  const main = getRadioValue('incident_main');
  const expected60 = getRadioValue('expected_delay_60');
  const already60 = getRadioValue('delay_already_60');
  const missedValue = getRadioValue('incident_missed') || getVal('incident_missed');
  const missed = missedValue === 'yes';
  const missed60 = getRadioValue('missed_expected_delay_60');
  const extraordinary = getRadioValue('operatorExceptionalCircumstances') === 'yes';
  const stranding = getRadioValue('rail_stranding_context') || getVal('rail_stranding_context') || 'no';
  const pmrUser = getRadioValue('pmr_user');
  const pmrBooked = getRadioValue('pmr_booked');
  const pmrDelivered = getRadioValue('pmr_delivered_status');
  const pmrMissing = getRadioValue('pmr_promised_missing');
  const bikeDenied = getRadioValue('bike_denied_boarding');
  const bikeReasonProvided = getRadioValue('bike_refusal_reason_provided');
  const bikeReasonType = String(getVal('bike_refusal_reason_type') || '').trim().toLowerCase();
  const hasExplicitAnswers = [
    main, expected60, already60, missedValue, missed60,
    pmrUser, pmrBooked, pmrDelivered, pmrMissing,
    bikeDenied, bikeReasonProvided, bikeReasonType
  ].some(function(value) { return value !== ''; });

  let euGateFromMain = false;
  if (main === 'cancellation') {
    euGateFromMain = true;
  } else if (main === 'delay') {
    euGateFromMain = expected60 === 'yes' || already60 === 'yes';
  }

  let euGate = euGateFromMain;
  if (!euGateFromMain && missed) {
    euGate = missed60 === 'yes';
  }

  const pmrFullGate = pmrUser === 'yes' && pmrBooked === 'yes' && pmrDelivered === 'no';
  const pmrPartialGate = pmrUser === 'yes' && pmrMissing === 'yes' && !pmrFullGate;
  const pmrGate = pmrFullGate || pmrPartialGate;
  const bikeReasonAllowed = ['capacity', 'equipment', 'weight_dim'].indexOf(bikeReasonType) !== -1;
  const bikeGate = bikeDenied === 'yes' && (bikeReasonProvided !== 'yes' || !bikeReasonAllowed);

  return {
    main: main,
    expected60: expected60,
    already60: already60,
    missed: missed,
    missed60: missed60,
    extraordinary: extraordinary,
    stranding: stranding,
    hasExplicitAnswers: hasExplicitAnswers,
    euGateFromMain: euGateFromMain,
    art18Active: (main === 'cancellation') || missed || euGate || pmrGate || bikeGate,
    art20Active: (main === 'cancellation') || euGate || pmrFullGate || bikeGate,
    art19Active: main === 'delay' && euGate
  };
}

function updateRailLiveEstimatePreview() {
  const panel = document.getElementById('railLiveEstimate');
  if (!panel) return;

  const state = getRailIncidentPreviewState();
  const seedArt18 = panel.dataset.seedArt18 === '1';
  const seedArt19 = panel.dataset.seedArt19 === '1';
  const seedArt20 = panel.dataset.seedArt20 === '1';
  const seedArrivalDelay = panel.dataset.seedArrivalDelay === '' ? null : parseInt(panel.dataset.seedArrivalDelay, 10);
  const seedDepartureDelay = panel.dataset.seedDepartureDelay === '' ? null : parseInt(panel.dataset.seedDepartureDelay, 10);
  const seedIncidentType = String(panel.dataset.seedIncidentType || 'unknown');
  const panelTicketPrice = panel.dataset.ticketPrice === '' ? 0 : parseFloat(panel.dataset.ticketPrice);
  const panelCurrency = String(panel.dataset.currency || 'EUR');
  const panelPriceKnown = panel.dataset.priceKnown === '1';
  const panelPriceEstimate = panel.dataset.priceEstimate === '1';
  const panelArt19Allowed = panel.dataset.art19Allowed !== '0';

  const main = state.main;
  const expected60 = state.expected60;
  const already60 = state.already60;
  const missed = state.missed;
  const missed60 = state.missed60;
  const extraordinary = state.extraordinary;
  const stranding = state.stranding;
  const hasExplicitAnswers = state.hasExplicitAnswers;
  const euGateFromMain = state.euGateFromMain;

  let incidentLabel = 'Afventer';
  if (main === 'delay') incidentLabel = 'Forsinkelse';
  if (main === 'cancellation') incidentLabel = 'Aflysning';
  if (missed && missed60 === 'yes' && !euGateFromMain) incidentLabel = 'Mistet forbindelse';
  if (incidentLabel === 'Afventer') {
    if (seedIncidentType === 'delay') incidentLabel = 'Forsinkelse';
    else if (seedIncidentType === 'cancellation') incidentLabel = 'Aflysning';
    else if (seedIncidentType === 'missed_connection') incidentLabel = 'Mistet forbindelse';
    else if (seedIncidentType === 'partial_cancellation') incidentLabel = 'Delvis aflysning';
    else if (seedIncidentType === 'replacement_transport') incidentLabel = 'Erstatningstransport';
  }

  let art18Active = seedArt18;
  let art20Active = seedArt20;
  let art19Active = seedArt19;
  if (hasExplicitAnswers) {
    art18Active = state.art18Active;
    art20Active = state.art20Active;
    art19Active = state.art19Active;
  }

  let arrivalDelay = seedArrivalDelay;
  if (already60 === 'yes' && (arrivalDelay === null || arrivalDelay < 60)) {
    arrivalDelay = 60;
  }
  if (missed && missed60 === 'yes' && !euGateFromMain && (arrivalDelay === null || arrivalDelay < 60)) {
    arrivalDelay = 60;
  }

  let art19Label = 'Afventer svar';
  const showProvisionalAmount = panelPriceKnown || art19Active || (extraordinary && art19Active);
  const provisionalBandPct = art19Active ? ((arrivalDelay !== null && arrivalDelay >= 120) ? 50 : 25) : 25;
  const bandPct = (arrivalDelay !== null && arrivalDelay >= 120) ? 50 : 25;

  let amountText = 'Afventer';
  let thresholdText = 'Afventer';
  let statusText = 'Afventer flere svar';

  if (!panelArt19Allowed) {
    art19Label = 'Undtaget nationalt';
    statusText = 'Kompensation blokeret';
    amountText = 'Blokeret';
  } else if (art19Active && extraordinary) {
    art19Label = 'Blokeret af force majeure';
    statusText = 'Kompensation blokeret';
    amountText = 'Blokeret';
  } else if (art19Active) {
    art19Label = bandPct + '% af billetpris';
    statusText = 'Foreloebigt kompensationsniveau';
    amountText = (panelPriceKnown && panelTicketPrice > 0)
      ? ((panelTicketPrice * bandPct / 100).toFixed(2) + ' ' + panelCurrency)
      : (bandPct + '% af billetpris');
  } else if (showProvisionalAmount) {
    amountText = panelTicketPrice > 0
      ? ((panelTicketPrice * provisionalBandPct / 100).toFixed(2) + ' ' + panelCurrency)
      : (provisionalBandPct + '% af billetpris');
  }

  if (!art19Active && (art18Active || art20Active)) {
    statusText = 'Omlaegning / assistance aktiv';
  }

  if (!panelArt19Allowed) {
    thresholdText = 'Undtaget nationalt';
  } else if (main === 'cancellation') {
    thresholdText = 'Ikke relevant ved aflysning';
  } else if (art19Active || expected60 === 'yes' || already60 === 'yes' || (missed && missed60 === 'yes' && !euGateFromMain)) {
    thresholdText = 'Aktiveret';
  } else if (hasExplicitAnswers && main === 'delay' && expected60 === 'no' && already60 === 'no' && !(missed && missed60 === 'yes' && !euGateFromMain)) {
    thresholdText = 'Under 60 min';
  }

  setLiveTextAll('[data-rail-live-status]', statusText);
  setLiveTextAll('[data-rail-live-amount]', amountText);
  setLiveTextAll('[data-rail-live-incident]', incidentLabel);
  setLiveTextAll('[data-rail-live-art18]', art18Active ? 'Fuld daekning mulig' : 'Afventer flere svar');
  setLiveTextAll('[data-rail-live-art19]', art19Label);
  setLiveTextAll('[data-rail-live-threshold]', thresholdText);
  setLiveTextAll('[data-rail-live-art20]', art20Active ? 'Rimelige noedvendige udgifter kan daekkes' : 'Afventer flere svar');
  setLiveTextAll('[data-rail-live-arrival-delay]', arrivalDelay !== null ? ((arrivalDelay > 0 ? '+' : '') + arrivalDelay + ' min') : 'Afventer');
  setLiveTextAll('[data-rail-live-departure-delay]', seedDepartureDelay !== null ? ((seedDepartureDelay > 0 ? '+' : '') + seedDepartureDelay + ' min') : 'Afventer');

  let strandingLabel = 'Ikke strandet';
  if (stranding === 'station') strandingLabel = 'Strandet paa station';
  if (stranding === 'track') strandingLabel = 'Strandet i tog / paa spor';
  setLiveTextAll('[data-rail-live-stranding]', strandingLabel);

  let summary = '';
  if (!panelArt19Allowed) {
    summary = 'EU-kompensation (Art. 19) er undtaget for denne rejse efter den nationale matrix.';
  } else if (art19Active && extraordinary) {
    summary = 'Kompensationssporet er foreloebigt blokeret af force majeure.';
  } else if (art19Active) {
    summary = (panelPriceKnown && panelTicketPrice > 0)
      ? ('Foreloebigt Art. 19-estimat ud fra billetpris og ankomstforsinkelse.' + (panelPriceEstimate ? ' Beloebet bygger paa et ca. estimat fra TRIN 2.' : ''))
      : 'Kompensationen ser mulig ud, men billetpris mangler endnu. Registrer prisen i TRIN 2 eller bekraeft den senere i backend.';
  } else if (showProvisionalAmount) {
    summary = 'Foreloebigt rail-estimat ud fra billetpris med 60+ minutter som standardantagelse.'
      + (panelPriceEstimate ? ' Beloebet bygger paa et ca. estimat fra TRIN 2.' : '');
  } else if (art18Active || art20Active) {
    summary = 'Kompensationen afventer stadig 60+ minutter, men assistance eller omlaegning / refund kan allerede vaere relevante.';
  } else {
    summary = 'Rail-panelet afventer stadig rail-spoergsmaalene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.';
  }
  setLiveTextAll('[data-rail-live-note]', summary);
}

document.addEventListener('change', function() {
  updateReveal();
  updateStep4State();
  updateRailLiveEstimatePreview();
});

document.addEventListener('DOMContentLoaded', function() {
  updateReveal();
  updateStep4State();
  updateRailLiveEstimatePreview();

  const minsField = document.getElementById('nationalDelayMinutes');
  const reportedAt = document.getElementById('nationalDelayReportedAt');
  if (minsField && reportedAt) {
    minsField.addEventListener('input', function() {
      reportedAt.value = String(Date.now());
      updateStep4State();
    }, { passive: true });
  }

  const startBtn = document.getElementById('startEuReminder');
  if (startBtn) {
    startBtn.addEventListener('click', function() {
      clearEuReminder();
      const mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;
      if (Number.isNaN(mins) || mins <= 0 || mins >= 60) return;
      const status = document.getElementById('euReminderStatus');
      if (status) status.textContent = 'Reminder sat til ca. ' + (60 - mins) + ' min.';
      __euReminderTimer = window.setTimeout(function() {
        showById('euReminderPrompt', true);
      }, (60 - mins) * 60 * 1000);
    });
  }

  const yesBtn = document.getElementById('euReminderYes');
  if (yesBtn) {
    yesBtn.addEventListener('click', function() {
      const radio = document.querySelector('input[name="delay_already_60"][value="yes"]');
      if (radio) radio.checked = true;
      showById('euReminderPrompt', false);
      updateReveal();
      updateStep4State();
      updateRailLiveEstimatePreview();
    });
  }

  const noBtn = document.getElementById('euReminderNo');
  if (noBtn) {
    noBtn.addEventListener('click', function() {
      showById('euReminderPrompt', false);
      clearEuReminder();
      updateRailLiveEstimatePreview();
    });
  }
});
</script>
<?php
$content = ob_get_clean();
if ($uiLanguage !== 'da' && $pageTranslations !== []) {
    $content = strtr($content, $pageTranslations);
}
$content = str_replace(
    ['OperatÃ¸ren', 'bekrÃ¦ftet'],
    ['Operatoeren', 'bekraeftet'],
    $content
);

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
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
