<?php
/**
 * TC6 ferry incident wrapper.
 *
 * Keeps the existing ferry incident logic intact, but renders it inside the
 * TC6 shell with ferry branding and stronger TC6-style card treatment.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$meta = $meta ?? [];
$journey = $journey ?? [];
$incident = $incident ?? [];
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$pageTranslations = (array)($pageTranslations ?? []);

if ($uiLanguage === 'en') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Ongoing journey',
        'Foer afgang' => 'Before departure',
        'Afsluttet rejse' => 'Completed journey',
        'Refusion / ombooking' => 'Refund / rerouting',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operator',
        'Ferry haendelse + foreloebig vurdering' => 'Ferry incident + preliminary assessment',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Foer afgang' => 'Avant le depart',
        'Afsluttet rejse' => 'Trajet termine',
        'Refusion / ombooking' => 'Remboursement / reacheminement',
        'Assistance' => 'Assistance',
        'Operatør' => 'Operateur',
        'Ferry haendelse + foreloebig vurdering' => 'Incident ferry + evaluation preliminaire',
    ];
}
if ($uiLanguage === 'en') {
    $pageTranslations += [
        'Afbrydelser/forsinkelser' => 'Interruptions/delays',
        'Var passageren informeret om aflysning/forsinkelse foer koeb?' => 'Was the passenger informed about cancellation/delay before purchase?',
        'Nej / ved ikke' => 'No / do not know',
        'Aaben billet / afgangstid' => 'Open ticket / departure time',
        'Er det en aaben billet uden afgangstid?' => 'Is it an open ticket without a departure time?',
        'Artikel 11 / Art. 8(3): PMR' => 'Article 11 / Art. 8(3): PRM',
        'Har passageren behov for saerlig assistance / PMR?' => 'Does the passenger need special assistance / PRM?',
        'Blev du naegtet at komme om bord paa faergen paa grund af handicap / nedsat mobilitet?' => 'Were you refused boarding on the ferry because of disability / reduced mobility?',
        'Reservation eller billetafvisning alene aabner ikke dette PMR-remedy-spor. Her ser vi kun paa naegtet indskibning.' => 'Reservation or ticket refusal alone does not open this PRM remedy track. Here we only assess denied boarding.',
        'Oplyste du ved booking eller forhaandskoeb om saerlige behov, hvis behovet var kendt?' => 'Did you notify special needs at booking or pre-purchase, if the need was known?',
        'Ved ikke' => 'Do not know',
        'Ikke relevant / behovet var ikke kendt' => 'Not relevant / the need was not known',
        'Bruges som soft evidence for Art. 11(2). Det styrer ikke remedies alene.' => 'Used as soft evidence for Art. 11(2). It does not determine remedies on its own.',
        'Hvad sagde transportoeren var begrundelsen?' => 'What reason did the operator give?',
        'Sikkerhedskrav' => 'Safety requirements',
        'Havnens eller skibets indretning' => 'Port or vessel design',
        'Andet / ved ikke' => 'Other / do not know',
        'Fik du en klar begrundelse skriftligt eller mundtligt?' => 'Did you receive a clear reason in writing or verbally?',
        'PMR-assistance, ledsager, servicehund, 48-timers varsel og leveret assistance registreres bagefter i backend-supporttrinnet.' => 'PRM assistance, companion, service dog, 48-hour notice and delivered assistance are registered later in the backend support step.',
        'Haendelse (Art. 16-19 ferry)' => 'Incident (Art. 16-19 ferry)',
        'Bruges til ferry-gating for Art. 17, 18 og 19.' => 'Used for ferry gating for Articles 17, 18 and 19.',
        'Haendelse' => 'Incident',
        'Forsinkelse' => 'Delay',
        'Aflysning' => 'Cancellation',
        'Var faergens afgang forventet eller faktisk forsinket mere end 90 minutter?' => 'Was the ferry departure expected to be, or actually, delayed by more than 90 minutes?',
        'Er afgangen aflyst af transportoeren?' => 'Has the departure been cancelled by the operator?',
        'Aflysning aabner Art. 17/18-sporet uafhaengigt af 90-minuttersforsinkelse. Art. 19 afhaenger stadig af faktisk ankomstforsinkelse og undtagelser.' => 'Cancellation opens the Art. 17/18 track regardless of the 90-minute delay. Art. 19 still depends on actual arrival delay and exceptions.',
        'Betyder haendelsen, at passageren skal overnatte?' => 'Does the incident mean that the passenger needs to stay overnight?',
        'Bruges kun til Art. 17(2) hotel/overnatning og aabner ikke hotel alene uden disruption-gate fra samme trin.' => 'Used only for Art. 17(2) hotel/overnight stay and does not open hotel on its own without a disruption gate from this step.',
        'Force majeure' => 'Force majeure',
        'Var der vejrsikkerhed / sikkerhedsforhold?' => 'Were there weather safety / security conditions?',
        'Paaberaaber carrier ekstraordinaere omstaendigheder?' => 'Is the carrier invoking extraordinary circumstances?',
        '<- Tilbage' => '<- Back',
        'Naeste' => 'Next',
        'Live ferry-estimat' => 'Live ferry estimate',
        'Afventer forsinkelse/sejltid' => 'Awaiting delay / sailing time',
        'Afventer flere svar' => 'Awaiting more answers',
        'Ikke valgt endnu' => 'Not selected yet',
        'Operator' => 'Operator',
        'Aktiv' => 'Active',
        'Afventer' => 'Pending',
        'Ikke aktiv endnu' => 'Not active yet',
        '% af billetpris' => '% of ticket price',
        'Systemet har fundet' => 'The system found',
        'Ferry bruger API/OCR/afgangsvalg som standard. Ret kun hvis data mangler eller er forkert.' => 'Ferry uses API/OCR/departure selection by default. Only edit if data is missing or incorrect.',
        'Kilde:' => 'Source:',
        'Planlagt afgang' => 'Scheduled departure',
        'Planlagt sejltid' => 'Scheduled sailing time',
        'Aflysning valgt' => 'Cancellation selected',
        'Afgangsforsinkelse' => 'Departure delay',
        'Ankomstforsinkelse' => 'Arrival delay',
        'Bekraeft' => 'Confirm',
        'Bekraeftet' => 'Confirmed',
        'Ret oplysninger' => 'Edit details',
        'Ret systemdata / backend confirmation' => 'Edit system data / backend confirmation',
        'Disse felter er normalt udfyldt af API/OCR. De bruges til rettighedsflags, men skal ikke tastes af passageren som standard.' => 'These fields are normally filled by API/OCR. They are used for rights flags, but should not be entered by the passenger by default.',
        'Fik du information om aflysningen eller forsinkelsen senest 30 min efter planlagt afgangstid?' => 'Did you receive information about the cancellation or delay no later than 30 minutes after the scheduled departure time?',
        'Art. 16 er et informations-/claim-strength spor. Det aabner ikke Art. 17/18/19 alene.' => 'Article 16 is an information / claim-strength track. It does not open Articles 17/18/19 on its own.',
        'Forventet afgangsforsinkelse mindst 90 minutter?' => 'Expected departure delay of at least 90 minutes?',
        'Var afgangen faktisk mindst 90 minutter forsinket?' => 'Was the departure actually delayed by at least 90 minutes?',
        'Planlagt sejltid i minutter' => 'Scheduled sailing time in minutes',
        'Bruges til Art. 19-threshold: 60/120/180/360 minutter afhaengigt af planlagt sejltid.' => 'Used for the Article 19 threshold: 60/120/180/360 minutes depending on scheduled sailing time.',
        'Afgangsforsinkelse i minutter' => 'Departure delay in minutes',
        'Ankomstforsinkelse i minutter' => 'Arrival delay in minutes',
        'Dette er det centrale felt for Art. 19-kompensation.' => 'This is the key field for Article 19 compensation.',
        ' - dette er kun testdata og ikke endelig driftsverifikation.' => ' - this is test data only and not final operational verification.',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Afbrydelser/forsinkelser' => 'Interruptions / retards',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Var passageren informeret om aflysning/forsinkelse foer koeb?' => 'Le passager a-t-il ete informe d une annulation / d un retard avant l achat ?',
        'Nej / ved ikke' => 'Non / je ne sais pas',
        'Aaben billet / afgangstid' => 'Billet ouvert / heure de depart',
        'Er det en aaben billet uden afgangstid?' => 'S agit-il d un billet ouvert sans heure de depart ?',
        'Artikel 11 / Art. 8(3): PMR' => 'Article 11 / art. 8(3) : PMR',
        'Har passageren behov for saerlig assistance / PMR?' => 'Le passager a-t-il besoin d une assistance speciale / PMR ?',
        'Blev du naegtet at komme om bord paa faergen paa grund af handicap / nedsat mobilitet?' => 'L embarquement sur le ferry vous a-t-il ete refuse en raison d un handicap / d une mobilite reduite ?',
        'Reservation eller billetafvisning alene aabner ikke dette PMR-remedy-spor. Her ser vi kun paa naegtet indskibning.' => 'Le refus d une reservation ou d un billet n ouvre pas a lui seul ce parcours PMR. Ici, nous examinons uniquement le refus d embarquement.',
        'Oplyste du ved booking eller forhaandskoeb om saerlige behov, hvis behovet var kendt?' => 'Avez-vous signale des besoins particuliers lors de la reservation ou de l achat anticipe, si ce besoin etait connu ?',
        'Ved ikke' => 'Je ne sais pas',
        'Ikke relevant / behovet var ikke kendt' => 'Non pertinent / le besoin n etait pas connu',
        'Bruges som soft evidence for Art. 11(2). Det styrer ikke remedies alene.' => 'Utilise comme indice souple pour l art. 11(2). Cela ne determine pas a lui seul les recours.',
        'Hvad sagde transportoeren var begrundelsen?' => 'Quelle raison l operateur a-t-il invoquee ?',
        'Sikkerhedskrav' => 'Exigences de securite',
        'Havnens eller skibets indretning' => 'Configuration du port ou du navire',
        'Andet / ved ikke' => 'Autre / je ne sais pas',
        'Fik du en klar begrundelse skriftligt eller mundtligt?' => 'Avez-vous recu une justification claire, ecrite ou orale ?',
        'PMR-assistance, ledsager, servicehund, 48-timers varsel og leveret assistance registreres bagefter i backend-supporttrinnet.' => 'L assistance PMR, l accompagnateur, le chien d assistance, le preavis de 48 heures et l assistance effectivement fournie sont enregistres plus tard dans l etape de support back-office.',
        'Haendelse (Art. 16-19 ferry)' => 'Incident (art. 16-19 ferry)',
        'Bruges til ferry-gating for Art. 17, 18 og 19.' => 'Utilise pour le filtrage ferry des art. 17, 18 et 19.',
        'Haendelse' => 'Incident',
        'Forsinkelse' => 'Retard',
        'Aflysning' => 'Annulation',
        'Var faergens afgang forventet eller faktisk forsinket mere end 90 minutter?' => 'Le depart du ferry etait-il prevu ou effectivement retarde de plus de 90 minutes ?',
        'Er afgangen aflyst af transportoeren?' => 'Le depart a-t-il ete annule par l operateur ?',
        'Er afgangen aflyst af transportøren?' => 'Le depart a-t-il ete annule par l operateur ?',
        'Aflysning aabner Art. 17/18-sporet uafhaengigt af 90-minuttersforsinkelse. Art. 19 afhaenger stadig af faktisk ankomstforsinkelse og undtagelser.' => 'L annulation ouvre le parcours des art. 17/18 independamment du retard de 90 minutes. L art. 19 depend toujours du retard reel a l arrivee et des exceptions.',
        'Annulation åbner Art. 17/18-sporet uafhængigt af 90-minuttersforsinkelse. Art. 19 afhænger stadig af faktisk ankomstforsinkelse og undtagelser.' => 'L annulation ouvre le parcours des art. 17/18 independamment du retard de 90 minutes. L art. 19 depend toujours du retard reel a l arrivee et des exceptions.',
        'Betyder haendelsen, at passageren skal overnatte?' => 'L incident signifie-t-il que le passager doit passer la nuit sur place ?',
        'Bruges kun til Art. 17(2) hotel/overnatning og aabner ikke hotel alene uden disruption-gate fra samme trin.' => 'Utilise uniquement pour l art. 17(2) hotel / hebergement et n ouvre pas a lui seul l hotel sans filtre de perturbation depuis cette etape.',
        'Force majeure' => 'Force majeure',
        'Var der vejrsikkerhed / sikkerhedsforhold?' => 'Y avait-il des conditions de securite meteorologique / de surete ?',
        'Paaberaaber carrier ekstraordinaere omstaendigheder?' => 'Le transporteur invoque-t-il des circonstances extraordinaires ?',
        '<- Tilbage' => '<- Retour',
        'Naeste' => 'Suivant',
        'Live ferry-estimat' => 'Estimation ferry en direct',
        'Afventer forsinkelse/sejltid' => 'En attente du retard / temps de traversee',
        'Afventer flere svar' => 'En attente de reponses supplementaires',
        'Ikke valgt endnu' => 'Pas encore selectionne',
        'Operator' => 'Operateur',
        'Aktiv' => 'Actif',
        'Afventer' => 'En attente',
        'Ikke aktiv endnu' => 'Pas encore actif',
        '% af billetpris' => '% du prix du billet',
        '50% af billetpris' => '50% du prix du billet',
        'En attente forsinkelse/sejltid' => 'En attente du retard / temps de traversee',
        'Systemet har fundet' => 'Le systeme a trouve',
        'Ferry bruger API/OCR/afgangsvalg som standard. Ret kun hvis data mangler eller er forkert.' => 'Le ferry utilise l API, l OCR et la selection du depart par defaut. Ne modifiez que si des donnees manquent ou sont incorrectes.',
        'Kilde:' => 'Source :',
        'Planlagt afgang' => 'Depart prevu',
        'Planlagt sejltid' => 'Duree de traversee prevue',
        'Aflysning valgt' => 'Annulation selectionnee',
        'Afgangsforsinkelse' => 'Retard au depart',
        'Ankomstforsinkelse' => 'Retard a l arrivee',
        'Bekraeft' => 'Confirmer',
        'Bekraeftet' => 'Confirme',
        'Ret oplysninger' => 'Modifier les informations',
        'Ret systemdata / backend confirmation' => 'Modifier les donnees systeme / confirmation back-office',
        'Disse felter er normalt udfyldt af API/OCR. De bruges til rettighedsflags, men skal ikke tastes af passageren som standard.' => 'Ces champs sont normalement remplis par l API ou l OCR. Ils servent aux indicateurs de droits, mais ne doivent pas etre saisis par le passager par defaut.',
        'Fik du information om aflysningen eller forsinkelsen senest 30 min efter planlagt afgangstid?' => 'Avez-vous recu l information sur l annulation ou le retard au plus tard 30 minutes apres l heure de depart prevue ?',
        'Art. 16 er et informations-/claim-strength spor. Det aabner ikke Art. 17/18/19 alene.' => 'L art. 16 est un parcours d information / de force du dossier. Il n ouvre pas a lui seul les art. 17/18/19.',
        'Forventet afgangsforsinkelse mindst 90 minutter?' => 'Retard de depart prevu d au moins 90 minutes ?',
        'Var afgangen faktisk mindst 90 minutter forsinket?' => 'Le depart a-t-il effectivement eu au moins 90 minutes de retard ?',
        'Planlagt sejltid i minutter' => 'Duree de traversee prevue en minutes',
        'Bruges til Art. 19-threshold: 60/120/180/360 minutter afhaengigt af planlagt sejltid.' => 'Utilise pour le seuil de l art. 19 : 60/120/180/360 minutes selon la duree de traversee prevue.',
        'Afgangsforsinkelse i minutter' => 'Retard au depart en minutes',
        'Ankomstforsinkelse i minutter' => 'Retard a l arrivee en minutes',
        'Dette er det centrale felt for Art. 19-kompensation.' => 'C est le champ central pour l indemnisation au titre de l art. 19.',
        ' - dette er kun testdata og ikke endelig driftsverifikation.' => ' - il s agit uniquement de donnees de test et non d une verification operationnelle definitive.',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? 'completed')));
$isCompleted = $travelState === 'completed';
$isOngoing = $travelState === 'ongoing';
$context = $isOngoing ? 'Igangvaerende rejse' : ($isCompleted ? 'Afsluttet rejse' : 'Foer afgang');
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$incidentPrevAction = (string)($incidentPrevAction ?? ($flowPrevAction ?? 'ferryDepartureSelect'));
$backUrl = html_entity_decode($this->Url->build(['action' => $incidentPrevAction, '?' => $flowQuery]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

$steps = $steps ?? [
    1 => 'Billet, grunddata',
    2 => 'Vaelg afgang',
    3 => 'Haendelse',
    4 => 'Refusion, ombooking',
    5 => 'Assistance',
    6 => 'Kontakt, opret sag',
];
if ($uiLanguage === 'fr') {
    $steps = [
        1 => 'Billet, donnees de base',
        2 => 'Choix du depart',
        3 => 'Incident',
        4 => 'Remboursement, reacheminement',
        5 => 'Assistance',
        6 => 'Contact, creation du dossier',
    ];
} elseif ($uiLanguage === 'en') {
    $steps = [
        1 => 'Ticket, basic data',
        2 => 'Choose departure',
        3 => 'Incident',
        4 => 'Refund, rerouting',
        5 => 'Assistance',
        6 => 'Contact, create case',
    ];
}
$currentStep = (int)($currentStep ?? 3);
$doneSteps = $doneSteps ?? [];
$progressPct = (int)($progressPct ?? (count($steps) > 0 ? round(($currentStep / count($steps)) * 100) : 0));
$progressLabel = (string)($progressLabel ?? ($currentStep . ' / ' . count($steps) . ' trin'));
if ($uiLanguage === 'fr') {
    $progressLabel = $currentStep . ' / ' . count($steps) . ' etapes';
}

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

$stats = [
    ['Flow', $context, null],
    ['Transport', 'FERRY', null],
];
$summaryRows = [
    ['Refusion / ombooking', $art18Active ? 'Aktiv' : 'Afventer', $art18Active ? 'green' : 'gray'],
    ['Assistance', $assistanceActive ? 'Aktiv' : 'Afventer', $assistanceActive ? 'green' : 'gray'],
    ['Operatør', $operatorLabel, null],
];
if ($uiLanguage !== 'da' && $pageTranslations !== []) {
    $translateFerryIncident = static function ($text) use ($pageTranslations) {
        if (!is_string($text)) {
            return $text;
        }
        if (isset($pageTranslations[$text])) {
            return $pageTranslations[$text];
        }
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $pageTranslations[$decoded] ?? $text;
    };
    $context = $translateFerryIncident($context);
    $stats = array_map(static function (array $row) use ($translateFerryIncident): array {
        $row[0] = $translateFerryIncident($row[0] ?? '');
        $row[1] = $translateFerryIncident($row[1] ?? '');

        return $row;
    }, $stats);
    $summaryRows = array_map(static function (array $row) use ($translateFerryIncident): array {
        $row[0] = $translateFerryIncident($row[0] ?? '');
        $row[1] = $translateFerryIncident($row[1] ?? '');

        return $row;
    }, $summaryRows);
}

ob_start();
?>
<div class="tc6-chip">Trin <?= (int)$currentStep ?> / <?= count($steps) ?></div>
<h1 class="tc6-h1">Ferry haendelse + foreloebig vurdering</h1>

<style>
.tc6-ferry-incident .flow-wrapper.fg-step {
    padding: 0;
    background: transparent;
}
.tc6-ferry-incident .flow-wrapper.fg-step > h1,
.tc6-ferry-incident .flow-wrapper.fg-step > .fg-step-kicker,
.tc6-ferry-incident .flow-wrapper.fg-step > .fg-step-sub,
.tc6-ferry-incident .flow-wrapper.fg-step > .fg-status {
    display: none;
}
.tc6-ferry-incident .flow-wrapper .small.muted,
.tc6-ferry-incident .flow-wrapper .fg-card-lead {
    display: none !important;
}
.tc6-ferry-incident .flow-wrapper [hidden],
.tc6-ferry-incident .flow-wrapper .hidden {
    display: none !important;
}
.tc6-ferry-incident .flow-wrapper .card,
.tc6-ferry-incident .flow-wrapper .fg-question-card {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.08) !important;
    background: linear-gradient(180deg, rgba(252, 253, 255, 0.98) 0%, rgba(246, 249, 253, 0.98) 100%) !important;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
}
.tc6-ferry-incident .flow-wrapper .fg-choice-row {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) !important;
    gap: 12px !important;
    margin-top: 12px !important;
}
.tc6-ferry-incident .flow-wrapper .mt8:not([hidden]):not(.hidden):has(> label > input[type="radio"]),
.tc6-ferry-incident .flow-wrapper .mt12:not([hidden]):not(.hidden):has(> label > input[type="radio"]) {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    align-items: stretch !important;
}
.tc6-ferry-incident .flow-wrapper .mt8:not([hidden]):not(.hidden):has(> label > input[type="radio"]) > :first-child,
.tc6-ferry-incident .flow-wrapper .mt12:not([hidden]):not(.hidden):has(> label > input[type="radio"]) > :first-child {
    width: 100% !important;
    margin: 0 !important;
}
.tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]) {
    display: inline-flex !important;
    align-items: center !important;
    gap: 12px !important;
    min-height: 64px !important;
    padding: 14px 16px !important;
    margin: 0 !important;
    border-radius: var(--tc-r-lg) !important;
    border: 1.5px solid var(--tc-border-md) !important;
    background: var(--tc-surface) !important;
    box-shadow: var(--tc-shadow-xs) !important;
    box-sizing: border-box !important;
    cursor: pointer !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    color: var(--tc-text-1) !important;
    transition: border-color .12s, background .12s, box-shadow .12s, transform .12s !important;
    vertical-align: top !important;
    width: calc(50% - 6px) !important;
    min-width: 180px !important;
    flex: 1 1 180px !important;
    justify-content: flex-start !important;
    text-transform: none !important;
}
.tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]).ml8 {
    margin-left: 0 !important;
}
.tc6-ferry-incident .flow-wrapper .mt8 > label:has(input[type="radio"][value="yes"]),
.tc6-ferry-incident .flow-wrapper .mt12 > label:has(input[type="radio"][value="yes"]) {
    order: 1;
}
.tc6-ferry-incident .flow-wrapper .mt8 > label:has(input[type="radio"][value="no"]),
.tc6-ferry-incident .flow-wrapper .mt12 > label:has(input[type="radio"][value="no"]) {
    order: 2;
}
.tc6-ferry-incident .flow-wrapper .mt8 > label:has(input[type="radio"][value="unknown"]),
.tc6-ferry-incident .flow-wrapper .mt12 > label:has(input[type="radio"][value="unknown"]) {
    order: 3;
}
.tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]):hover {
    transform: translateY(-1px);
    box-shadow: var(--tc-shadow-sm) !important;
}
.tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]):has(input:checked) {
    border-color: var(--tc-blue) !important;
    background: #f0f7ff !important;
    color: var(--tc-blue) !important;
    box-shadow: 0 0 0 3px rgba(29,111,216,0.08), var(--tc-shadow-sm) !important;
}
.tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]) input[type="radio"] {
    margin: 0 !important;
    flex-shrink: 0 !important;
    width: 16px !important;
    height: 16px !important;
    accent-color: #2563eb !important;
}
@media (max-width: 760px) {
    .tc6-ferry-incident .flow-wrapper label:has(input[type="radio"]) {
        width: 100% !important;
    }
}
</style>

<?php
require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'incident.php';
$content = ob_get_clean();
$legacyMarkupTools = require ROOT . DS . 'templates' . DS . 'element' . DS . 'tc6' . DS . 'legacy_markup_tools.php';
$content = $legacyMarkupTools['stripEstimate']($content, 'ferryLiveEstimate', '.ferry-live-estimate');
$content = preg_replace(
    '/action="[^"]*\/flow\/incident[^"]*"/',
    'action="' . h(html_entity_decode($this->Url->build(['action' => 'incident', '?' => $flowQuery]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '"',
    $content,
    1
) ?? $content;
if ($uiLanguage !== 'da' && $pageTranslations !== []) {
    $content = strtr($content, array_merge($pageTranslations, [
        'Afventer svar' => 'En attente de reponse',
        'Afventer flere svar' => 'En attente de reponses supplementaires',
        'Afventer' => 'En attente',
        'Fuld daekning mulig' => 'Couverture complete possible',
        'Rimelige noedvendige udgifter kan daekkes' => 'Les frais necessaires raisonnables peuvent etre couverts',
        'Foreloebigt kompensationsniveau' => 'Niveau d indemnisation provisoire',
        'Tilbagebetaling' => 'Remboursement',
        'Suivant threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.' => 'Le prochain seuil est de 90 min, ou l assistance peut devenir pertinente pour les trajets de plus de 3 heures.',
        'Suivant threshold er 120 min, hvor refund eller omlaegning kan blive relevant.' => 'Le prochain seuil est de 120 min, ou le remboursement ou le reacheminement peuvent devenir pertinents.',
        'Naeste threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.' => 'Le prochain seuil est de 90 min, ou l assistance peut devenir pertinente pour les trajets de plus de 3 heures.',
        'Naeste threshold er 120 min, hvor refund eller omlaegning kan blive relevant.' => 'Le prochain seuil est de 120 min, ou le remboursement ou le reacheminement peuvent devenir pertinents.',
    ]));
}
if ($uiLanguage === 'fr') {
    $content = preg_replace(
        '/<a href="[^"]*" class="button fg-button-secondary">(?:&lt;- )?Tilbage<\/a>/',
        '<a href="' . htmlspecialchars($backUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" class="button fg-button-secondary">&lt;- Retour</a>',
        $content,
        1
    ) ?? $content;
}

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
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', [
    'steps' => $steps,
    'currentStep' => $currentStep,
    'doneSteps' => $doneSteps,
    'content' => $content,
    'rightPanel' => $rightPanel,
    'context' => $context,
    'brandName' => 'FerryClaim',
    'brandMark' => 'FC',
    'shellClass' => 'tc6-ferry-incident',
]);
