<?php
/** @var \App\View\AppView $this */
$form     = $form ?? [];
$flags    = $flags ?? [];
$incident = $incident ?? [];
$meta     = $meta ?? [];
$journey  = $journey ?? [];
$groupedTickets = $groupedTickets ?? [];
$profile  = $profile ?? ['articles' => []];
$articles = (array)($profile['articles'] ?? []);
$euArt18Supported = ($articles['art18'] ?? true) !== false;
$euArt20Supported = ($articles['art20_2'] ?? true) !== false;
$euFlowSupported = $euArt18Supported || $euArt20Supported;
$art9On  = ($articles['art9'] ?? true) !== false;
$art91On = ($articles['art9_1'] ?? ($articles['art9'] ?? true)) !== false;
$art92On = ($articles['art9_2'] ?? ($articles['art9'] ?? true)) !== false;
$travelState = strtolower((string)($flags['travel_state'] ?? $form['travel_state'] ?? ''));
$isOngoing = ($travelState === 'ongoing');
$isCompleted = ($travelState === 'completed');
$incidentHint = $isOngoing ? 'Hvad er situationen nu?' : ($isCompleted ? 'Hvad var den afgoerende haendelse?' : '');

$v = fn(string $k): string => (string)($form[$k] ?? '');
$isPreview = !empty($flowPreview);
$segCount = is_array($journey['segments'] ?? null) ? count($journey['segments']) : 0;
$airRouteLegs = is_array($meta['air_route_legs'] ?? null) ? (array)$meta['air_route_legs'] : [];
if (count($airRouteLegs) > $segCount) {
    $segCount = count($airRouteLegs);
}
if ($segCount < 2) {
    $altSegs = $meta['_miss_conn_segments'] ?? ($meta['_segments_all'] ?? ($meta['_segments_llm_suggest'] ?? ($meta['_segments_auto'] ?? [])));
    if (is_array($altSegs)) { $segCount = max($segCount, count($altSegs)); }
}
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);
$transportMode = strtolower((string)($form['transport_mode'] ?? ($multimodal['transport_mode'] ?? ($meta['transport_mode'] ?? 'rail'))));
$gatingMode = strtolower((string)($form['gating_mode'] ?? ($meta['gating_mode'] ?? ($flags['gating_mode'] ?? $transportMode))));
 $incidentPrevAction = trim((string)($incidentPrevAction ?? 'journey'));
if (!in_array($transportMode, ['rail','ferry','bus','air'], true)) { $transportMode = 'rail'; }
if (!in_array($gatingMode, ['rail','ferry','bus','air'], true)) { $gatingMode = $transportMode; }
$isFerry = $gatingMode === 'ferry';
$isBus = $gatingMode === 'bus';
$isAir = $gatingMode === 'air';
$ferryRights = (array)($multimodal['ferry_rights'] ?? []);
$ferryContract = (array)($multimodal['ferry_contract'] ?? []);
$ferryScope = (array)($multimodal['ferry_scope'] ?? []);
$ferryOpsEvidence = (array)($meta['ferry_operational_evidence'] ?? []);
$ferryIncidentSuggestion = (array)($meta['ferry_incident_suggestion'] ?? []);
$ferrySystemIncident = (string)($ferryIncidentSuggestion['suggested_incident_main'] ?? '');
$ferrySystemDuration = trim((string)($form['scheduled_journey_duration_minutes'] ?? ($ferryIncidentSuggestion['suggested_scheduled_journey_duration_minutes'] ?? '')));
$ferrySystemDepartureDelay = trim((string)($form['delay_minutes_departure'] ?? ($ferryIncidentSuggestion['suggested_departure_delay_minutes'] ?? '')));
$ferrySystemArrivalDelay = trim((string)($form['arrival_delay_minutes'] ?? ($ferryIncidentSuggestion['suggested_arrival_delay_minutes'] ?? '')));
$ferrySystemExpected90 = (string)($form['expected_departure_delay_90'] ?? ($ferryIncidentSuggestion['suggested_expected_departure_delay_90'] ?? ''));
$ferrySystemActual90 = (string)($form['actual_departure_delay_90'] ?? ($ferryIncidentSuggestion['suggested_actual_departure_delay_90'] ?? ''));
$ferryDisruption90Value = (string)($form['ferry_departure_disruption_90'] ?? ($meta['ferry_departure_disruption_90'] ?? ''));
$ferryCancellationConfirmedValue = (string)($form['ferry_cancellation_confirmed'] ?? ($meta['ferry_cancellation_confirmed'] ?? ''));
$ferrySuggestionConfidence = (string)($ferryIncidentSuggestion['suggestion_confidence'] ?? ($ferryOpsEvidence['confidence'] ?? ''));
$busRights = (array)($multimodal['bus_rights'] ?? []);
$busContract = (array)($multimodal['bus_contract'] ?? []);
$busScope = (array)($multimodal['bus_scope'] ?? []);
$airRights = (array)($multimodal['air_rights'] ?? []);
$airContract = (array)($multimodal['air_contract'] ?? []);
$airScope = (array)($multimodal['air_scope'] ?? []);
$entryVariant = strtolower((string)($flags['entry_variant'] ?? ($meta['entry_variant'] ?? '')));
$isAirShortView = $isAir && $entryVariant === 'air_short';
$isAirShortOngoingView = $isAirShortView && $isOngoing;
$isAirShortCompletedView = $isAirShortView && $isCompleted;
$isModeSplitView = in_array($entryVariant, ['rail_split', 'bus_split', 'ferry_split'], true);
$isRailSplitView = !$isFerry && !$isBus && !$isAir && $entryVariant === 'rail_split';
$isSplitCompletedView = $isModeSplitView && $isCompleted;
$airRouteType = strtolower(trim((string)($form['air_route_type'] ?? '')));
$airHasExplicitStopovers = (
    $airRouteType === 'connecting'
    || ((string)($flags['air_has_stopovers'] ?? '') === '1')
    || count($airRouteLegs) > 1
);
$airDistanceBand = strtolower(trim((string)($form['air_distance_band'] ?? ($airScope['air_distance_band'] ?? ''))));
$airDistanceBandLabel = match ($airDistanceBand) {
    'up_to_1500' => '1500 km eller mindre',
    'intra_eu_over_1500' => 'Inden for EU over 1500 km',
    'other_1500_to_3500' => 'Øvrige flyvninger mellem 1500 og 3500 km',
    'other_over_3500' => 'Øvrige flyvninger over 3500 km',
    default => 'Ikke afledt endnu',
};
$airDelayThresholdHours = (int)($form['air_delay_threshold_hours'] ?? ($airScope['air_delay_threshold_hours'] ?? 0));
$airDelayThresholdLabel = $airDelayThresholdHours > 0 ? ($airDelayThresholdHours . '+ timer') : 'Ikke afledt endnu';
$airFlightDistanceKm = trim((string)($form['flight_distance_km'] ?? ($airScope['flight_distance_km'] ?? '')));
$airDelayBandValue = strtolower(trim((string)($form['delay_departure_band'] ?? '')));
$airExpectedDelayBucket = strtolower(trim((string)($form['air_expected_delay_bucket'] ?? '')));
$airActualArrivalBucket = strtolower(trim((string)($form['air_actual_arrival_delay_bucket'] ?? '')));
$airCancellationNoticeBand = strtolower(trim((string)($form['cancellation_notice_band'] ?? '')));
$airRerouteDepartureBand = strtolower(trim((string)($form['reroute_departure_band'] ?? '')));
$airRerouteArrivalBand = strtolower(trim((string)($form['reroute_arrival_band'] ?? '')));
$airRerouteUsedOrAccepted = strtolower(trim((string)($form['reroute_used_or_accepted'] ?? '')));
$airConnectionType = strtolower(trim((string)($airContract['air_connection_type'] ?? $form['air_connection_type'] ?? '')));
$showAirCancellationWindows = $isAir;
$showAirCancellationDetailQuestions = $showAirCancellationWindows && !$isAirShortView;
$airConnectionKnown = in_array($airConnectionType, ['single_flight', 'protected_connection', 'self_transfer'], true);
$airConnectionNeedsFallback = !$airConnectionKnown || !empty($airContract['manual_review_required']);
$showAirMissedConnection = $isAirShortView
    ? $airHasExplicitStopovers
    : ($isAir && (
        $segCount > 1
        || in_array($airConnectionType, ['protected_connection', 'self_transfer'], true)
    ));
$selectedAirFlight = (array)($meta['air_selected_flight'] ?? []);
$selectedAirLeg = (array)($meta['air_selected_leg'] ?? []);
$railIncidentSeed = [];
if (!$isFerry && !$isBus && !$isAir) {
    $railIncidentSeed = (array)($meta['rail_incident_seed'] ?? []);
    if (($railIncidentSeed['mode'] ?? '') !== 'rail') {
        $fallbackRailSeed = (array)($meta['incident_seed'] ?? []);
        if (($fallbackRailSeed['mode'] ?? '') === 'rail') {
            $railIncidentSeed = $fallbackRailSeed;
        }
    }
}
$railSeedIncidentType = strtolower(trim((string)($railIncidentSeed['incident_type'] ?? 'unknown')));
$railSeedTransferCount = is_numeric($railIncidentSeed['transfer_count'] ?? null) ? (int)$railIncidentSeed['transfer_count'] : 0;
$railSeedMissedConnectionSuspected = !empty($railIncidentSeed['missed_connection_suspected']);
$railProblemAnchor = (!$isFerry && !$isBus && !$isAir) ? (array)($meta['rail_problem_anchor'] ?? []) : [];
$selectedRailDeparture = (!$isFerry && !$isBus && !$isAir) ? (array)($meta['rail_selected_departure'] ?? []) : [];
$railContractSeed = (!$isFerry && !$isBus && !$isAir) ? (array)($meta['rail_contract_structure_seed'] ?? []) : [];
$railCurrentLocationAnchor = (!$isFerry && !$isBus && !$isAir) ? (array)($meta['rail_current_location_anchor'] ?? []) : [];
$railContractOptions = (!$isFerry && !$isBus && !$isAir) ? (array)($meta['contract_options'] ?? []) : [];
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
$railSeedStatus = strtolower(trim((string)($meta['rail_selected_departure']['status'] ?? 'unknown')));
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
$railTransferStations = array_values(array_unique($railTransferStations));
$railTransferStationsSummary = implode(' -> ', $railTransferStations);
$airOpsEvidence = (array)($meta['air_operational_evidence'] ?? []);
$airOpsAvailable = !empty($airOpsEvidence['available']);
$airOpsStatus = trim((string)($airOpsEvidence['status'] ?? ''));
$airOpsSource = strtoupper(trim((string)($airOpsEvidence['source'] ?? '')));
$airOpsConfidence = trim((string)($airOpsEvidence['confidence'] ?? ''));
$airOpsScore = isset($airOpsEvidence['evidence_score']) ? (int)$airOpsEvidence['evidence_score'] : 0;
$airOpsDelayMinutes = is_numeric($airOpsEvidence['delay_minutes_estimated'] ?? null) ? (int)$airOpsEvidence['delay_minutes_estimated'] : null;
$airOpsCancelled = (string)($airOpsEvidence['cancelled'] ?? 'no') === 'yes';
$airOpsDelaySuggestionFloor = 15;
$airOpsCanSuggestDelay = $airOpsDelayMinutes !== null && $airOpsDelayMinutes >= $airOpsDelaySuggestionFloor;
$airOpsDelayDisplay = null;
if ($airOpsDelayMinutes !== null) {
    $airOpsDelayDisplay = $airOpsDelayMinutes > 0
        ? ('+' . $airOpsDelayMinutes . ' min')
        : ($airOpsDelayMinutes < 0 ? ($airOpsDelayMinutes . ' min (foran schedule)') : '0 min');
}
$airOpsSuggestedDelayBand = '';
if ($airOpsCanSuggestDelay) {
    if ($airOpsDelayMinutes >= 300) {
        $airOpsSuggestedDelayBand = 'five_plus';
    } elseif ($airDelayThresholdHours > 0 && $airOpsDelayMinutes >= ($airDelayThresholdHours * 60)) {
        $airOpsSuggestedDelayBand = 'threshold_to_under_5h';
    } else {
        $airOpsSuggestedDelayBand = 'under_threshold';
    }
}
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
$railJourneyRoute = trim((string)($selectedRailDeparture['origin_station_name'] ?? '')) !== '' || trim((string)($selectedRailDeparture['destination_station_name'] ?? '')) !== ''
    ? trim((string)($selectedRailDeparture['origin_station_name'] ?? '')) . ' -> ' . trim((string)($selectedRailDeparture['destination_station_name'] ?? ''))
    : trim((string)($form['dep_station'] ?? '')) . ' -> ' . trim((string)($form['arr_station'] ?? ''));
$railJourneyRoute = trim($railJourneyRoute, ' ->');
$railJourneyService = trim((string)($selectedRailDeparture['train_number'] ?? ($selectedRailDeparture['service_name'] ?? ($selectedRailDeparture['line_name'] ?? ''))));
$railJourneyOperatorNames = array_values(array_filter(array_map(
    static fn($value): string => trim((string)$value),
    (array)($railContractSeed['operator_names'] ?? (($selectedRailDeparture['raw'] ?? [])['operator_names'] ?? []))
)));
if ($railJourneyOperatorNames === []) {
    $singleOperator = trim((string)($selectedRailDeparture['operator_name'] ?? ($form['operator'] ?? '')));
    if ($singleOperator !== '') {
        $railJourneyOperatorNames = [$singleOperator];
    }
}
$railContractModel = strtolower(trim((string)($form['contract_model'] ?? ($railContractSeed['effective_contract_model'] ?? ''))));
$railContractModelLabel = match ($railContractModel) {
    'through' => 'Gennemgaaende billet / en kontrakt',
    'separate' => 'Saerskilte kontrakter',
    default => 'Afventer Art. 12-afklaring',
};
$railSellerChannel = strtolower(trim((string)($form['seller_channel'] ?? ($railContractSeed['seller_channel'] ?? ''))));
$railSellerChannelLabel = match ($railSellerChannel) {
    'operator' => 'Jernbaneoperatoer',
    'retailer' => 'Rejsebureau / billetudsteder',
    default => 'Ikke afklaret endnu',
};
$railSameTransaction = strtolower(trim((string)($form['same_transaction'] ?? ($railContractSeed['same_transaction'] ?? ''))));
$railSameTransactionLabel = match ($railSameTransaction) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke afklaret endnu',
};
$railDisclosure = strtolower(trim((string)($form['through_ticket_disclosure'] ?? ($railContractSeed['through_ticket_disclosure'] ?? ''))));
$railDisclosureLabel = match ($railDisclosure) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke afklaret endnu',
};
$railSeparateNotice = strtolower(trim((string)($form['separate_contract_notice'] ?? ($railContractSeed['separate_contract_notice'] ?? ''))));
$railSeparateNoticeLabel = match ($railSeparateNotice) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => 'Ikke afklaret endnu',
};
$railProblemContractId = trim((string)($form['problem_contract_id'] ?? ($railContractSeed['problem_contract_id'] ?? '')));
$railSelectedProblemContract = ($railProblemContractId !== '' && isset($railContractOptions[$railProblemContractId]) && is_array($railContractOptions[$railProblemContractId]))
    ? (array)$railContractOptions[$railProblemContractId]
    : [];
$railProblemContractLabel = '';
if ($railSelectedProblemContract !== []) {
    $contractStops = array_values(array_filter((array)($railSelectedProblemContract['stops'] ?? []), static fn($stop): bool => is_array($stop)));
    $firstStop = $contractStops[0] ?? [];
    $lastStop = $contractStops !== [] ? $contractStops[count($contractStops) - 1] : [];
    $contractFrom = trim((string)($firstStop['name'] ?? ''));
    $contractTo = trim((string)($lastStop['name'] ?? ''));
    $contractOperator = trim((string)($railSelectedProblemContract['operator_name'] ?? ''));
    $railProblemContractLabel = trim(($contractFrom !== '' || $contractTo !== '' ? ($contractFrom . ' -> ' . $contractTo) : '') . ($contractOperator !== '' ? (' · ' . $contractOperator) : ''), ' ·');
}
$railStrandedStation = trim((string)($form['stranded_current_station'] ?? ''));
if ($railStrandedStation === 'other') {
    $railStrandedStation = trim((string)($form['stranded_current_station_other'] ?? ''));
}
$railWhereEnded = strtolower(trim((string)($form['rail_station_where_ended'] ?? '')));
$railWhereEndedLabel = match ($railWhereEnded) {
    'same_station' => 'Endte paa strandingsstationen',
    'other_station' => 'Kom videre til en anden station',
    'return_to_departure' => 'Vendte tilbage til afgangsstationen',
    'final_destination' => 'Naaede endelig destination',
    'unknown' => 'Ved ikke endnu',
    default => '',
};
$railOutcomeStation = trim((string)($form['rail_station_end_station'] ?? ''));
if ($railOutcomeStation === 'other') {
    $railOutcomeStation = trim((string)($form['rail_station_end_station_other'] ?? ''));
}
if ($railOutcomeStation === '') {
    $railOutcomeStation = trim((string)($railCurrentLocationAnchor['station_name'] ?? ''));
}
$railStillThere = strtolower(trim((string)($form['rail_station_still_there'] ?? '')));
$railStillThereLabel = match ($railStillThere) {
    'yes' => 'Ja',
    'no' => 'Nej',
    default => '',
};
$airDelayBandOptions = match ($airDelayThresholdHours) {
    2 => [
        'under_threshold' => 'Under 2 timer',
        'threshold_to_under_5h' => '2-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    3 => [
        'under_threshold' => 'Under 3 timer',
        'threshold_to_under_5h' => '3-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    4 => [
        'under_threshold' => 'Under 4 timer',
        'threshold_to_under_5h' => '4-4 timer 59 min',
        'five_plus' => '5+ timer',
    ],
    default => [
        'under_threshold' => 'Under den afledte threshold',
        'threshold_to_under_5h' => 'Threshold nået, men under 5 timer',
        'five_plus' => '5+ timer',
    ],
};
$airExpectedDelayOptions = [
    'under_threshold' => ($airDelayThresholdHours > 0 ? ('Under ' . $airDelayThresholdHours . ' timer') : 'Under threshold'),
    'threshold_to_under_5h' => ($airDelayThresholdHours > 0 ? ('Mindst ' . $airDelayThresholdHours . ' timer') : 'Threshold naaet'),
    'five_plus' => '5+ timer',
    'next_day' => 'Ny afgang foerst naeste dag',
    'unknown' => 'Ved ikke',
];
$airActualArrivalOptions = [
    'under_3h' => 'Under 3 timer',
    'three_to_four' => '3-3 timer 59 min',
    'four_plus' => '4+ timer',
    'never_arrived' => 'Ankom aldrig',
    'unknown' => 'Ved ikke',
];

// National policy hint (optional) used for TRIN 5 "national fallback" UX (e.g., DK 30 min).
$nationalPolicy = $nationalPolicy ?? null;
$nationalCutoff = null;
$nationalThr50 = null;
try {
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['25'])) {
        $nationalCutoff = (int)$nationalPolicy['thresholds']['25'];
    }
    if (is_array($nationalPolicy) && isset($nationalPolicy['thresholds']['50'])) {
        $nationalThr50 = (int)$nationalPolicy['thresholds']['50'];
    }
} catch (\Throwable $e) { $nationalCutoff = null; }

// Force majeure / extraordinary circumstances (Art. 19(10)) – affects compensation only (Art. 19).
// In ClaimCalculator: operator's own staff strikes do NOT remove compensation rights.
$exc0 = strtolower(trim((string)($form['operatorExceptionalCircumstances'] ?? '')));
$excType0 = trim((string)($form['operatorExceptionalType'] ?? ''));
$compBlockedByFM = ($exc0 === 'yes') && ($excType0 === '' || $excType0 !== 'own_staff_strike');
$showHooksPanel = (bool)$this->getRequest()->getQuery('debug');
?>

<style>
  .small { font-size:12px; }
  .muted { color:#666; }
  .mt4 { margin-top:4px; }
  .mt8 { margin-top:8px; }
  .mt12 { margin-top:12px; }
  .ml8 { margin-left:8px; }
  .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .hidden { display:none; }
  .card { padding:12px; border:1px solid #ddd; background:#fff; border-radius:6px; }
  .flow-wrapper { max-width: 1080px; margin: 0 auto; }
  select, input[type="text"], input[type="number"] { max-width: 520px; width: 100%; }
  .widget-title { display:flex; align-items:center; gap:10px; font-weight:700; }
  .step-badge { width:28px; height:28px; border-radius:999px; background:#e9f2ff; border:1px solid #cfe0ff; color:#1e3a8a; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; line-height:1; flex:0 0 auto; }
  .fm-badge { width:26px; height:26px; border-radius:999px; background:#fff3cd; border:1px solid #eed27c; display:inline-flex; align-items:center; justify-content:center; vertical-align:middle; margin-right:8px; }
  .fm-badge svg { width:16px; height:16px; display:block; }
  .bus-live-shell { margin-top:10px; padding:12px; border:1px dashed #cbd5e1; border-radius:8px; background:#ffffff; }
  .bus-live-display { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
  .bus-live-minutes { font-size:28px; font-weight:800; color:#1f2937; line-height:1; }
  .bus-live-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; border:1px solid #cbd5e1; background:#f8fafc; color:#334155; }
  .bus-live-badge.is-90 { background:#fff7ed; border-color:#fdba74; color:#9a3412; }
  .bus-live-badge.is-120 { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
  .bus-live-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .bus-live-actions button { background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe; border-radius:6px; padding:6px 10px; font-size:12px; font-weight:700; cursor:pointer; }
  .bus-live-actions button.bus-live-secondary { background:#f8fafc; color:#334155; border-color:#cbd5e1; }
  @media (max-width: 820px) { .grid-2 { grid-template-columns:1fr; } }
</style>

<div class="flow-wrapper">
  <?php if ($isFerry): ?>
    <h1>TRIN 5 - Haendelse (ferry)</h1>
  <?php elseif ($isBus): ?>
    <h1>TRIN 5 - Haendelse (bus)</h1>
  <?php elseif ($isAir): ?>
    <h1>TRIN 5 - Haendelse (fly)</h1>
  <?php elseif ($isOngoing): ?>
    <h1>TRIN 5 - Forsinkelse, aflysning eller mistet forbindelse (igangvaerende rejse)</h1>
  <?php elseif ($isCompleted): ?>
    <h1>TRIN 5 - Haendelse (afsluttet rejse)</h1>
  <?php else: ?>
    <h1>TRIN 5 - Haendelse (Art. 18/20 standard gating)</h1>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
    <?= $this->element('rail_live_estimate', compact('form', 'flags', 'meta', 'journey')) ?>
  <?php endif; ?>

  <?php if ($isAirShortView): ?>
    <?= $this->element('air_live_estimate', compact('form', 'flags', 'meta', 'airRights', 'airScope', 'airContract')) ?>
    <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">Air-incident er holdt kort her: haendelse, missed connection og kompensationsnaere fakta. Mere teknisk kontraktanalyse kan flyttes til sagen bagefter.</div>
  <?php elseif ($isFerry): ?>
    <?= $this->element('ferry_live_estimate', compact('form', 'flags', 'meta', 'journey', 'ferryRights', 'ferryScope')) ?>
    <div class="small muted mt8" style="background:#f8fafc; border:1px solid #bae6fd; border-radius:6px; padding:8px;">Ferry-estimatet viser operator, valgt afgang, AIS/ETA-stoette og foreloebige Art. 17/18/19-gates. Det er ikke et multimodalt claim-kanal step.</div>
  <?php endif; ?>
  <?php if ($isAirShortView): ?>
    <?php if (!empty($selectedAirFlight)): ?>
      <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
        <strong>Valgt flyvning:</strong>
        <?= h((string)($selectedAirFlight['flight_number'] ?? '-')) ?>
        <?php if (!empty($selectedAirLeg['title'])): ?>
          | <?= h((string)$selectedAirLeg['title']) ?>
        <?php endif; ?>
        <?php if (!empty($selectedAirFlight['carrier_name'])): ?>
          | <?= h((string)$selectedAirFlight['carrier_name']) ?>
        <?php endif; ?>
        <?php if (!empty($selectedAirFlight['scheduled_departure_local'])): ?>
          | <?= h((string)$selectedAirFlight['scheduled_departure_local']) ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php elseif ($isModeSplitView): ?>
    <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
      <?= $isOngoing
          ? 'Dette er den igangvaerende variant. Fokus er paa den aktuelle haendelse og de naeste beslutninger, ikke hele efterbehandlingen endnu.'
          : 'Dette er den afsluttede variant. Live-/strandingsspoergsmaal er tonet ned, saa fokus er paa det endelige haendelsesforloeb.' ?>
    </div>
  <?php endif; ?>

  <?= $this->element('flow_locked_notice') ?>
  <?= $this->Form->create(null, ['novalidate' => true, 'id' => 'incidentStepForm']) ?>
  <fieldset <?= $isPreview ? 'disabled' : '' ?>>

  <!-- Preinformed disruption (moved from TRIN 3d) -->
  <?php
    $pid = strtolower((string)$v('preinformed_disruption'));
    $pic = (string)($v('preinfo_channel'));
    $ris = (string)($v('realtime_info_seen'));
    if ($pid === '' || $pid === 'unknown') { $pid = 'no'; }
    $rtOptions = [
      'app' => 'Ja, i app',
      'train' => 'Ja, i toget',
      'station' => 'Ja, paa station',
      'no' => 'Nej',
      'unknown' => 'Ved ikke',
    ];
  ?>
  <?php if ($isFerry): ?>
  <div class="card mt12" data-art="9(1)">
    <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
      <div class="widget-title">
        <span class="step-badge" aria-hidden="true">&#x23F1;</span>
        <span>Afbrydelser/forsinkelser</span>
      </div>
      <p class="small muted mt8">Default er "Nej". Udfyld kun hvis relevant.</p>

      <div class="mt8">
        <div>Var passageren informeret om aflysning/forsinkelse foer koeb?</div>
        <label><input type="radio" name="informed_before_purchase" value="yes" <?= $v('informed_before_purchase')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="informed_before_purchase" value="no" <?= $v('informed_before_purchase')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>
    </div>

    <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
      <div class="widget-title">
        <span class="step-badge" aria-hidden="true">O</span>
        <span>Aaben billet / afgangstid</span>
      </div>

      <div class="mt8">
        <div>Er det en aaben billet uden afgangstid?</div>
        <label><input type="radio" name="open_ticket_without_departure_time" value="yes" <?= $v('open_ticket_without_departure_time')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="open_ticket_without_departure_time" value="no" <?= $v('open_ticket_without_departure_time')==='no'?'checked':'' ?> /> Nej</label>
      </div>
    </div>

    <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;">
      <div class="widget-title">
        <span class="step-badge" aria-hidden="true">P</span>
        <span>Artikel 11 / Art. 8(3): PMR</span>
      </div>
      <p class="small muted mt8">For ferry afklarer vi her baade PMR-status og et eventuelt spor om naegtet indskibning. Den assistance-orienterede dokumentation samles bagefter i backend-supporttrinnet.</p>
      <div class="mt8">
        <div>Har passageren behov for saerlig assistance / PMR?</div>
        <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user')==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div class="mt8" data-show-if="pmr_user:yes">
        <div>Blev du naegtet at komme om bord paa faergen paa grund af handicap / nedsat mobilitet?</div>
        <label><input type="radio" name="ferry_pmr_boarding_refused" value="yes" <?= $v('ferry_pmr_boarding_refused')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="ferry_pmr_boarding_refused" value="no" <?= $v('ferry_pmr_boarding_refused')==='no'?'checked':'' ?> /> Nej</label>
        <div class="small muted mt4">Reservation eller billetafvisning alene aabner ikke dette PMR-remedy-spor. Her ser vi kun paa naegtet indskibning.</div>
      </div>
      <div class="mt8" data-show-if="ferry_pmr_boarding_refused:yes">
        <div>Oplyste du ved booking eller forhaandskoeb om saerlige behov, hvis behovet var kendt?</div>
        <select name="ferry_pmr_special_needs_notified_at_booking">
          <option value="unknown" <?= $v('ferry_pmr_special_needs_notified_at_booking')==='unknown'?'selected':'' ?>>Ved ikke</option>
          <option value="yes" <?= $v('ferry_pmr_special_needs_notified_at_booking')==='yes'?'selected':'' ?>>Ja</option>
          <option value="no" <?= $v('ferry_pmr_special_needs_notified_at_booking')==='no'?'selected':'' ?>>Nej</option>
          <option value="not_relevant" <?= $v('ferry_pmr_special_needs_notified_at_booking')==='not_relevant'?'selected':'' ?>>Ikke relevant / behovet var ikke kendt</option>
        </select>
        <div class="small muted mt4">Bruges som soft evidence for Art. 11(2). Det styrer ikke remedies alene.</div>
      </div>
      <div class="mt8" data-show-if="ferry_pmr_boarding_refused:yes">
        <div>Hvad sagde transportoeren var begrundelsen?</div>
        <select name="ferry_pmr_refusal_basis">
          <option value="safety_requirements" <?= $v('ferry_pmr_refusal_basis')==='safety_requirements'?'selected':'' ?>>Sikkerhedskrav</option>
          <option value="port_or_ship_infrastructure" <?= $v('ferry_pmr_refusal_basis')==='port_or_ship_infrastructure'?'selected':'' ?>>Havnens eller skibets indretning</option>
          <option value="other_or_unknown" <?= $v('ferry_pmr_refusal_basis')==='other_or_unknown'?'selected':'' ?>>Andet / ved ikke</option>
        </select>
      </div>
      <div class="mt8" data-show-if="ferry_pmr_boarding_refused:yes">
        <div>Fik du en klar begrundelse skriftligt eller mundtligt?</div>
        <label><input type="radio" name="ferry_pmr_reason_given" value="yes" <?= $v('ferry_pmr_reason_given')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="ferry_pmr_reason_given" value="no" <?= $v('ferry_pmr_reason_given')==='no'?'checked':'' ?> /> Nej</label>
      </div>
      <div class="small muted mt8" data-show-if="pmr_user:yes">PMR-assistance, ledsager, servicehund, 48-timers varsel og leveret assistance registreres bagefter i backend-supporttrinnet.</div>
    </div>

  </div>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <div class="card mt12 <?= $art91On ? '' : 'hidden' ?>" style="border-color:#d0d7de;background:#f8f9fb;" data-art="9(1)">
    <div class="widget-title">
      <span class="step-badge" aria-hidden="true">&#x23F1;</span>
      <span>Afbrydelser/forsinkelser foer koeb</span>
    </div>
    <p class="small muted mt8">Vises kun for rail og bruges til vurderingen af, om haendelsen var kendt paa koebstidspunktet.</p>

    <div class="mt8">
      <div>Var der meddelt afbrydelse/forsinkelse foer dit koeb?</div>
      <label><input type="radio" name="preinformed_disruption" value="yes" <?= $pid==='yes'?'checked':'' ?> /> Ja</label>
      <label class="ml8"><input type="radio" name="preinformed_disruption" value="no" <?= $pid==='no'?'checked':'' ?> /> Nej</label>
    </div>

    <div class="mt8" data-show-if="preinformed_disruption:yes">
      <div>Hvis ja: Hvor blev det vist?</div>
      <select name="preinfo_channel">
        <option value="">- Vaelg -</option>
        <option value="website" <?= $pic==='website'?'selected':'' ?>>Hjemmeside</option>
        <option value="journey_planner" <?= $pic==='journey_planner'?'selected':'' ?>>Rejseplan</option>
        <option value="app" <?= $pic==='app'?'selected':'' ?>>App</option>
        <option value="station" <?= $pic==='station'?'selected':'' ?>>Station</option>
        <option value="other" <?= $pic==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8 <?= $art92On ? '' : 'hidden' ?>" data-show-if="preinformed_disruption:yes" data-art="9(2)">
      <div>Saa du realtime-opdateringer under rejsen?</div>
      <?php $i = 0; foreach ($rtOptions as $key => $label): ?>
        <label class="<?= $i>0 ? 'ml8' : '' ?>"><input type="radio" name="realtime_info_seen" value="<?= h($key) ?>" <?= $ris===$key?'checked':'' ?> /> <?= h($label) ?></label>
      <?php $i++; endforeach; ?>
    </div>
  </div>
  <?php endif; ?>


  <!-- Standard gating -->
  <div class="card mt12">
    <?php if ($isFerry): ?>
      <input type="hidden" name="overnight_required" value="" />
      <input type="hidden" name="passenger_fault" value="" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">&#x26A1;</span>
          <span>Haendelse (Art. 16-19 ferry)</span>
        </div>
        <p class="small muted mt8">Bruges til ferry-gating for Art. 17, 18 og 19.</p>

        <div class="mt8">
          <div>Haendelse</div>
          <input type="hidden" id="ferryIncidentMainHidden" name="incident_main" value="<?= h($v('incident_main')) ?>" />
          <label><input type="radio" name="ferry_incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
          <label class="ml8"><input type="radio" name="ferry_incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
        </div>

        <div class="mt8" data-show-if="ferry_incident_main:delay">
          <div>Var færgens afgang fra havneterminalen forventet eller faktisk forsinket mere end 90 minutter?</div>
          <label><input type="radio" name="ferry_departure_disruption_90" value="yes" <?= $ferryDisruption90Value==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="ferry_departure_disruption_90" value="no" <?= $ferryDisruption90Value==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>

        <div class="mt8" data-show-if="ferry_incident_main:cancellation">
          <div>Er afgangen aflyst af transportøren?</div>
          <label><input type="radio" name="ferry_cancellation_confirmed" value="yes" <?= $ferryCancellationConfirmedValue==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="ferry_cancellation_confirmed" value="no" <?= $ferryCancellationConfirmedValue==='no'?'checked':'' ?> /> Nej / ved ikke</label>
          <div class="small muted mt4">Aflysning åbner Art. 17/18-sporet uafhængigt af 90-minuttersforsinkelse. Art. 19 afhænger stadig af faktisk ankomstforsinkelse og undtagelser.</div>
        </div>

        <input type="hidden" name="expected_departure_delay_90" value="<?= h($ferrySystemExpected90) ?>" />
        <input type="hidden" name="actual_departure_delay_90" value="<?= h($ferrySystemActual90) ?>" />
        <input type="hidden" name="scheduled_journey_duration_minutes" value="<?= h($ferrySystemDuration) ?>" />
        <input type="hidden" name="delay_minutes_departure" value="<?= h($ferrySystemDepartureDelay) ?>" />
        <input type="hidden" name="arrival_delay_minutes" value="<?= h($ferrySystemArrivalDelay) ?>" />

        <div class="card mt12" style="border-color:#bae6fd;background:#f0f9ff;" data-show-if="ferry_incident_main:delay,cancellation">
          <div class="widget-title">
            <span class="step-badge" aria-hidden="true">D</span>
            <span>Systemet har fundet</span>
          </div>
          <p class="small muted mt8">Ferry bruger API/OCR/afgangsvalg som standard. Ret kun hvis data mangler eller er forkert.</p>
          <div class="small mt8" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:8px;">
            <div><strong><?= h($v('incident_main') === 'cancellation' ? 'Planlagt afgang' : 'Planlagt sejltid') ?></strong><br><span data-ferry-system-duration><?= h($v('incident_main') === 'cancellation' ? 'Aflysning valgt' : ($ferrySystemDuration !== '' ? ($ferrySystemDuration . ' min') : 'Afventer')) ?></span></div>
            <div><strong>Afgangsforsinkelse</strong><br><span data-ferry-system-departure-delay><?= h($ferrySystemDepartureDelay !== '' ? ($ferrySystemDepartureDelay . ' min') : 'Afventer') ?></span></div>
            <div><strong>Ankomstforsinkelse</strong><br><span data-ferry-system-arrival-delay><?= h($ferrySystemArrivalDelay !== '' ? ($ferrySystemArrivalDelay . ' min') : 'Afventer') ?></span></div>
            <div><strong>Confidence</strong><br><?= h($ferrySuggestionConfidence !== '' ? $ferrySuggestionConfidence : 'Afventer') ?></div>
          </div>
          <div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="button" id="ferryConfirmOperationalData">Bekraeft</button>
            <button type="button" class="button" id="ferryEditOperationalData" style="background:#eef2ff;color:#1e3a8a;">Ret oplysninger</button>
          </div>
          <input type="hidden" name="ferry_operational_data_confirmed" value="<?= h($v('ferry_operational_data_confirmed')) ?>" />
        </div>

        <div id="ferryOperationalCorrectionPanel" class="card mt12 hidden" style="border-color:#cbd5e1;background:#fff;">
          <div class="widget-title">
            <span class="step-badge" aria-hidden="true">R</span>
            <span>Ret systemdata / backend confirmation</span>
          </div>
          <p class="small muted mt8">Disse felter er normalt udfyldt af API/OCR. De bruges til rettighedsflags, men skal ikke tastes af passageren som standard.</p>

          <div class="mt8">
            <div>Fik du information om aflysningen eller forsinkelsen senest 30 min efter planlagt afgangstid?</div>
            <label><input type="radio" name="ferry_art16_notice_within_30min" value="yes" <?= $v('ferry_art16_notice_within_30min')==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="ferry_art16_notice_within_30min" value="no" <?= $v('ferry_art16_notice_within_30min')==='no'?'checked':'' ?> /> Nej</label>
            <label class="ml8"><input type="radio" name="ferry_art16_notice_within_30min" value="unknown" <?= $v('ferry_art16_notice_within_30min')==='unknown'?'checked':'' ?> /> Ved ikke</label>
            <div class="small muted mt4">Art. 16 er et informations-/claim-strength spor. Det aabner ikke Art. 17/18/19 alene.</div>
          </div>

          <div class="mt8">
            <div>Forventet afgangsforsinkelse mindst 90 minutter?</div>
            <label><input type="radio" name="expected_departure_delay_90_override" value="yes" <?= $v('expected_departure_delay_90_override')==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="expected_departure_delay_90_override" value="no" <?= $v('expected_departure_delay_90_override')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
          </div>

          <div class="mt8">
            <div>Var afgangen faktisk mindst 90 minutter forsinket?</div>
            <label><input type="radio" name="actual_departure_delay_90_override" value="yes" <?= $v('actual_departure_delay_90_override')==='yes'?'checked':'' ?> /> Ja</label>
            <label class="ml8"><input type="radio" name="actual_departure_delay_90_override" value="no" <?= $v('actual_departure_delay_90_override')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
          </div>

        <div class="mt8" data-show-if="ferry_incident_main:delay,cancellation">
          <label for="ferryScheduledDurationMinutes">Planlagt sejltid i minutter</label>
          <input
            id="ferryScheduledDurationMinutes"
            type="number"
            name="scheduled_journey_duration_minutes"
            min="1"
            step="1"
            value="<?= h($ferrySystemDuration) ?>"
            placeholder="fx 115"
          />
          <div class="small muted mt4">Bruges til Art. 19-threshold: 60/120/180/360 minutter afhængigt af planlagt sejltid.</div>
        </div>

        <div class="mt8" data-show-if="ferry_incident_main:delay">
          <label for="ferryDepartureDelayMinutes">Afgangsforsinkelse i minutter</label>
          <input
            id="ferryDepartureDelayMinutes"
            type="number"
            name="delay_minutes_departure"
            min="0"
            step="1"
            value="<?= h($ferrySystemDepartureDelay) ?>"
            placeholder="fx 90"
          />
        </div>

        <div class="mt8" data-show-if="ferry_incident_main:delay">
          <label for="ferryArrivalDelayMinutes">Ankomstforsinkelse i minutter</label>
          <input
            id="ferryArrivalDelayMinutes"
            type="number"
            name="arrival_delay_minutes"
            min="0"
            step="1"
            value="<?= h($ferrySystemArrivalDelay) ?>"
            placeholder="fx 120"
          />
          <div class="small muted mt4">Dette er det centrale felt for Art. 19-kompensation.</div>
        </div>
        </div>
      </div>

      <div class="card mt12">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">Disse svar kan afskaere hotel og/eller kompensation i ferry-flowet.</p>

        <div class="mt8">
          <div>Var der vejrsikkerhed / sikkerhedsforhold?</div>
          <label><input type="radio" name="weather_safety" value="yes" <?= $v('weather_safety')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="weather_safety" value="no" <?= $v('weather_safety')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>

        <div class="mt8">
          <div>Paaberaaber carrier ekstraordinaere omstaendigheder?</div>
          <label><input type="radio" name="extraordinary_circumstances" value="yes" <?= $v('extraordinary_circumstances')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="extraordinary_circumstances" value="no" <?= $v('extraordinary_circumstances')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        </div>
      </div>

      <input type="hidden" name="season_ticket" value="<?= h(((string)($form['ticket_upload_mode'] ?? '') === 'seasonpass') ? 'yes' : 'no') ?>" />

      <?php if (!empty($ferryScope) || !empty($ferryContract) || !empty($ferryRights)): ?>
        <div
          id="ferryResolverStatus"
          class="small"
          style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;"
          data-regulation-applies="<?= !empty($ferryScope['regulation_applies']) ? '1' : '0' ?>"
          data-art18-supported="<?= (($ferryScope['articles']['art18'] ?? true) !== false) ? '1' : '0' ?>"
          data-departure-from-terminal="<?=
            ($ferryScope['departure_from_terminal'] ?? null) === true
              ? '1'
              : (($ferryScope['departure_from_terminal'] ?? null) === false ? '0' : '')
          ?>"
        >
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($ferryScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($ferryContract['primary_claim_party_name'] ?? ($ferryContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div id="ferryResolverArt17">Art. 17: <?= !empty($ferryRights['gate_art17_refreshments']) || !empty($ferryRights['gate_art17_hotel']) ? 'Ja' : 'Nej' ?></div>
          <div id="ferryResolverArt18">Art. 18: <?= !empty($ferryRights['gate_art18']) ? 'Ja' : 'Nej' ?></div>
          <div>Art. 19: <?= !empty($ferryRights['gate_art19']) ? ('Ja (' . h((string)($ferryRights['art19_comp_band'] ?? '')) . '%)') : 'Nej' ?></div>
        </div>
      <?php endif; ?>
    <?php elseif ($isBus): ?>
      <strong><span aria-hidden="true">&#x1F68C;</span> Haendelse (bus / EU 181/2011)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore information, assistance og refund/omlaegning for bus. Layoutet er holdt enkelt, saa kun de juridisk relevante bus-spoergsmaal staar tilbage.</p>

      <div class="small mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:8px; padding:10px 12px;">
        <strong>Bus-flow i TRIN 5:</strong> vaelg foerst haendelsen, derefter eventuel forsinkelses-kategori, og afslut med aaben billet, tilslutning og force majeure.
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">H</span>
          <span>Haendelsestype</span>
        </div>
        <p class="small muted mt8">Vaelg den bushaendelse der aabner det videre rettighedsspor.</p>

        <div class="mt8">
          <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Forsinkelse</label>
          <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
          <label class="ml8"><input type="radio" name="incident_main" value="overbooking" <?= $v('incident_main')==='overbooking'?'checked':'' ?> /> Overbooking / manglende plads</label>
        </div>
        <div class="mt12" data-show-if="incident_main:delay">
          <div class="small"><strong>Forsinkelsesniveau</strong></div>
          <p class="small muted mt4">Vaelg den juridiske forsinkelses-kategori i stedet for at indtaste minutter.</p>

          <div class="mt8">
            <label><input type="radio" name="delay_departure_band" value="under_90" <?= $v('delay_departure_band')==='under_90'?'checked':'' ?> /> Under 90 min</label>
            <label class="ml8"><input type="radio" name="delay_departure_band" value="90_119" <?= $v('delay_departure_band')==='90_119'?'checked':'' ?> /> 90-119 min</label>
            <label class="ml8"><input type="radio" name="delay_departure_band" value="120_plus" <?= $v('delay_departure_band')==='120_plus'?'checked':'' ?> /> 120+ min</label>
          </div>

          <div id="busDelayTimerCard" class="bus-live-shell <?= $isSplitCompletedView ? 'hidden' : '' ?>">
            <div class="small"><strong>Live forsinkelses-hjaelper</strong></div>
            <div class="small muted mt4">Timeren kan koere ved igangvaerende busforsinkelse og synkroniserer automatisk til 90- og 120-minutters-taersklerne.</div>

            <div class="bus-live-display mt8">
              <span id="busDelayLiveMinutes" class="bus-live-minutes"><?= h((string)($form['delay_minutes_departure'] ?? '0')) ?> min</span>
              <span id="busDelayThresholdBadge" class="bus-live-badge">Under 90 min</span>
            </div>

            <div id="busDelayThresholdMessage" class="small mt8">Naeste threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.</div>
            <div id="busDelayNextHint" class="small muted mt4">Refund eller omlaegning bliver forst relevant ved 120+ min.</div>

            <label class="small mt8" style="display:block;">Saet aktuel forsinkelse nu (minutter)
              <input id="busDelayLiveMinutesInput" type="number" min="0" step="1" value="<?= h((string)($form['delay_minutes_departure'] ?? '0')) ?>" placeholder="75" />
            </label>

            <div class="bus-live-actions">
              <button type="button" id="busDelayTimerStart">Start timer</button>
              <button type="button" id="busDelayTimerPause" class="bus-live-secondary">Pause</button>
              <button type="button" id="busDelayTimerReset" class="bus-live-secondary">Nulstil</button>
            </div>

            <div id="busDelayTimerStatus" class="small muted mt8"></div>
          </div>
          <?php if ($isSplitCompletedView): ?>
            <div class="small muted mt8">Live-timeren er skjult i den afsluttede bus-variant. Brug kun den juridiske forsinkelses-kategori ovenfor.</div>
          <?php endif; ?>
        </div>

        <div class="mt12" id="busPlannedDurationCard">
          <div class="small"><strong>Planlagt rejsevarighed</strong></div>
          <p class="small muted mt4">Vises kun naar Art. 21 kan komme i spil, saa brugeren ikke moedes af irrelevante spoergsmaal.</p>

          <div class="mt8">
            <label><input type="radio" name="planned_duration_band" value="up_to_3h" <?= $v('planned_duration_band')==='up_to_3h'?'checked':'' ?> /> 3 timer eller mindre</label>
            <label class="ml8"><input type="radio" name="planned_duration_band" value="over_3h" <?= $v('planned_duration_band')==='over_3h'?'checked':'' ?> /> Over 3 timer</label>
          </div>
        </div>
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">D</span>
          <span>Driftsproblem</span>
        </div>
        <p class="small muted mt8">Registrer separat, om bussen blev uanvendelig undervejs. Det supplerer haendelsestypen og erstatter den ikke.</p>

        <div class="mt8">
          <div>Gik bussen i stykker eller blev den uanvendelig under rejsen?</div>
          <label><input type="radio" name="vehicle_breakdown" value="yes" <?= $v('vehicle_breakdown')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="vehicle_breakdown" value="no" <?= $v('vehicle_breakdown')==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="vehicle_breakdown" value="unknown" <?= $v('vehicle_breakdown')==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
      </div>

      <input type="hidden" name="overbooking" value="<?= h($v('incident_main') === 'overbooking' ? 'yes' : 'no') ?>" />
      <input type="hidden" name="carrier_offered_choice" value="" />
      <input type="hidden" name="delay_minutes_departure" value="<?= h((string)($form['delay_minutes_departure'] ?? '')) ?>" />
      <input type="hidden" name="scheduled_journey_duration_minutes" value="" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">M</span>
          <span>Tilslutning</span>
        </div>
        <p class="small muted mt8">Busflowet bruger en enkel ja/nej-model her. Det er ikke den almindelige rail-blok for mistet forbindelse.</p>

        <div class="mt8">
          <div>Missede du en videre forbindelse pga. forsinkelsen?</div>
          <label><input type="radio" name="missed_connection_due_to_delay" value="yes" <?= $v('missed_connection_due_to_delay')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="missed_connection_due_to_delay" value="no" <?= $v('missed_connection_due_to_delay')==='no'?'checked':'' ?> /> Nej</label>
        </div>
      </div>

      <div class="card mt12" style="border-color:#e8d7aa;background:#fffaf0">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">Disse svar bruges til at afgoere, om hotel efter busreglerne bortfalder ved ekstraordinaere forhold.</p>

        <div class="mt8">
          <div>Var der kraftigt vejr?</div>
          <label><input type="radio" name="severe_weather" value="yes" <?= $v('severe_weather')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="severe_weather" value="no" <?= $v('severe_weather')==='no'?'checked':'' ?> /> Nej</label>
        </div>

        <div class="mt8">
          <div>Var der stor naturkatastrofe?</div>
          <label><input type="radio" name="major_natural_disaster" value="yes" <?= $v('major_natural_disaster')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="major_natural_disaster" value="no" <?= $v('major_natural_disaster')==='no'?'checked':'' ?> /> Nej</label>
        </div>
      </div>

      <input type="hidden" name="season_ticket" value="<?= h(((string)($form['ticket_upload_mode'] ?? '') === 'seasonpass') ? 'yes' : 'no') ?>" />

      <?php if (!empty($busScope) || !empty($busContract) || !empty($busRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($busScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Claim-kanal: <?= h((string)($busContract['primary_claim_party_name'] ?? ($busContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Info: <?= !empty($busRights['gate_bus_info']) ? 'Ja' : 'Nej' ?></div>
          <div>Assistance: <?= !empty($busRights['gate_bus_assistance_refreshments']) || !empty($busRights['gate_bus_assistance_hotel']) ? 'Ja' : 'Nej' ?></div>
          <div>Refund/ombooking: <?= !empty($busRights['gate_bus_reroute_refund']) ? 'Ja' : 'Nej' ?></div>
          <div>50% kompensation: <?= !empty($busRights['gate_bus_compensation_50']) ? 'Ja' : 'Nej' ?></div>
        </div>
      <?php endif; ?>
    <?php elseif ($isAir): ?>
      <strong><span aria-hidden="true">&#x2708;</span> Haendelse (air / EC261)</strong>
      <p class="small muted">TRIN 5 bruges til at afgore care og kompensation for flighten eller den protected connection der blev ramt. Delay-care foelger Art. 6-thresholds efter afstandskategori.</p>

      <div class="small mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:8px; padding:10px 12px;">
        <strong>Air-flow i TRIN 5:</strong> afstandskategori <strong><?= h($airDistanceBandLabel) ?></strong><?= $airFlightDistanceKm !== '' ? (' (' . h($airFlightDistanceKm) . ' km)') : '' ?>. Art. 6-delay-threshold er <strong><?= h($airDelayThresholdLabel) ?></strong>.
      </div>

      <?php if ($airOpsAvailable): ?>
      <div class="card mt12" style="border-color:#dbeafe;background:#f8fbff;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">O</span>
          <span>Operationelle flight-data</span>
        </div>
        <p class="small muted mt8">Brug dette som forslag fra <?= h($airOpsSource !== '' ? $airOpsSource : 'ops-kilden') ?>. Det hjaelper med status og plausibilitet, men er ikke alene juridisk facit.</p>
        <div class="small mt8" style="background:#fff; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <?php if ($airOpsStatus !== ''): ?><div><strong>Status:</strong> <?= h($airOpsStatus) ?></div><?php endif; ?>
          <?php if ($airOpsDelayDisplay !== null): ?><div><strong>Estimeret ankomstafvigelse:</strong> <?= h($airOpsDelayDisplay) ?></div><?php endif; ?>
          <?php if ($airOpsConfidence !== '' || $airOpsScore > 0): ?><div><strong>Confidence:</strong> <?= h($airOpsConfidence !== '' ? $airOpsConfidence : 'ukendt') ?><?= $airOpsScore > 0 ? (' · score ' . h((string)$airOpsScore)) : '' ?></div><?php endif; ?>
          <?php if (trim((string)($airOpsEvidence['summary'] ?? '')) !== ''): ?><div class="muted mt4"><?= h((string)$airOpsEvidence['summary']) ?></div><?php endif; ?>
        </div>
        <div class="mt8" style="display:flex; gap:8px; flex-wrap:wrap;">
          <?php if ($airOpsCancelled): ?>
            <button type="button" class="button" id="airOpsSuggestCancellation">Brug som aflysningsforslag</button>
          <?php endif; ?>
          <?php if ($airOpsCanSuggestDelay): ?>
            <button type="button" class="button" id="airOpsSuggestDelay">Brug som delay-forslag</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="mt8">
        <div>Haendelsestype</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $v('incident_main')==='delay'?'checked':'' ?> /> Lang forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $v('incident_main')==='cancellation'?'checked':'' ?> /> Aflysning</label>
        <label class="ml8"><input type="radio" name="incident_main" value="denied_boarding" <?= $v('incident_main')==='denied_boarding'?'checked':'' ?> /> Boardingafvisning</label>
      </div>

      <div id="airCancellationNoticeCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">C</span>
          <span>Varsel om aflysning</span>
        </div>
        <p class="small muted mt8">Bruges kun til Art. 5-kompensationsundtagelserne ved aflysning. Remedy og care aabnes stadig af selve aflysningen.</p>

        <div class="mt8">
          <div>Hvor lang tid foer planlagt afgang fik du besked om aflysningen?</div>
          <label><input type="radio" name="cancellation_notice_band" value="14_plus_days" <?= $airCancellationNoticeBand==='14_plus_days'?'checked':'' ?> /> Mindst 14 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="7_to_13_days" <?= $airCancellationNoticeBand==='7_to_13_days'?'checked':'' ?> /> Mellem 14 og 7 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="under_7_days" <?= $airCancellationNoticeBand==='under_7_days'?'checked':'' ?> /> Under 7 dage foer</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="airport_on_day_of_departure" <?= $airCancellationNoticeBand==='airport_on_day_of_departure'?'checked':'' ?> /> I lufthavnen / ved afgang</label>
          <label class="ml8"><input type="radio" name="cancellation_notice_band" value="unknown" <?= $airCancellationNoticeBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
        <?php if ($isAirShortCompletedView): ?>
        <div class="small muted mt8">For afsluttede flyvninger afklarer vi allerede her, om flyselskabet tilb&oslash;d en alternativ flyvning. De mere detaljerede Article 5-vinduer og eventuel Article 7(2)-reduktion registreres i sagen bagefter.</div>
        <?php endif; ?>
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="incident_main:delay">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">D</span>
          <span><?= $isCompleted ? 'Meldt forsinkelse ved afgang' : 'Meldt forsinkelse ved afgang' ?></span>
        </div>
        <p class="small muted mt8">
          <?= $isCompleted
              ? 'Brug den forsinkelse, flyselskabet meldte ud undervejs. Den bruges til Article 6-assistance og Article 8-refusion ved 5+ timer.'
              : 'Brug den forsinkelse, flyselskabet lige nu har meldt ud. Den bruges til Article 6-assistance og Article 8-refusion ved 5+ timer.' ?>
        </p>

        <div class="mt8">
          <?php foreach ($airExpectedDelayOptions as $bandValue => $bandLabel): ?>
            <label class="<?= $bandValue !== 'under_threshold' ? 'ml8' : '' ?>"><input type="radio" name="air_expected_delay_bucket" value="<?= h($bandValue) ?>" <?= $airExpectedDelayBucket===$bandValue?'checked':'' ?> /> <?= h($bandLabel) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($isCompleted): ?>
      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="incident_main:delay">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">A</span>
          <span>Faktisk forsinkelse ved endelig ankomst</span>
        </div>
        <p class="small muted mt8">Bruges til kompensationsvurderingen. Den meldte forsinkelse ovenfor bruges stadig til assistance og eventuel 5+ timers refusion.</p>
        <div class="mt8">
          <?php foreach ($airActualArrivalOptions as $bucketValue => $bucketLabel): ?>
            <label class="<?= $bucketValue !== 'under_3h' ? 'ml8' : '' ?>"><input type="radio" name="air_actual_arrival_delay_bucket" value="<?= h($bucketValue) ?>" <?= $airActualArrivalBucket===$bucketValue?'checked':'' ?> /> <?= h($bucketLabel) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="air_actual_arrival_delay_bucket" value="" />
      <?php endif; ?>
      <input type="hidden" name="delay_departure_band" value="<?= h($airDelayBandValue) ?>" />
      <input type="hidden" name="delay_minutes_departure" value="" />
      <input type="hidden" name="arrival_delay_minutes" value="<?= h($v('arrival_delay_minutes')) ?>" />

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;" data-show-if="incident_main:denied_boarding">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">B</span>
          <span>Boardingafvisning</span>
        </div>
        <p class="small muted mt8">Artikel 4 skelner mellem frivilligt afkald mod en aftalt modydelse og boardingafvisning mod din vilje. Frivillige kan stadig have ret til Art. 8 og 9, men ikke automatisk Art. 7-kompensation.</p>
        <?php if ($isAirShortView && $isOngoing): ?>
        <div class="small muted mt8">Her afklarer vi kun, om boardingafvisningen var frivillig eller ufrivillig. Refusion, ombooking, egen loesning og udgiftstyper samles i de naeste trin og kan uddybes i sagen bagefter.</div>
        <?php elseif ($isAirShortView && $isCompleted): ?>
        <div class="small muted mt8">Frontflowet holder sig her til frivillig eller ufrivillig boardingafvisning. De mere detaljerede Article 8- og udgiftsspoergsmaal registreres i sagen bagefter.</div>
        <?php endif; ?>
        <div class="mt8">Gav du frivilligt afkald på din reservation mod en aftalt modydelse?</div>
        <label><input type="radio" name="voluntary_denied_boarding" value="yes" <?= $v('voluntary_denied_boarding')==='yes'?'checked':'' ?> /> Ja, frivilligt</label>
        <label class="ml8"><input type="radio" name="voluntary_denied_boarding" value="no" <?= $v('voluntary_denied_boarding')==='no'?'checked':'' ?> /> Nej, jeg blev afvist mod min vilje</label>
      </div>
      <input type="hidden" name="boarding_denied" value="<?= h(in_array($v('incident_main'), ['denied_boarding'], true) ? 'yes' : $v('boarding_denied')) ?>" />

      <?php if ($showAirMissedConnection): ?>
      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">P</span>
          <span>Missed connection</span>
        </div>
        <p class="small muted mt8">TRIN 2 afgør allerede, om forbindelsen er samlet eller separat. Her registrerer vi kun, om du faktisk mistede en videre forbindelse.</p>
        <div class="mt8">Mistede du en videre forbindelse pga. hændelsen?</div>
        <label><input type="radio" name="protected_connection_missed" value="yes" <?= $v('protected_connection_missed')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="protected_connection_missed" value="no" <?= $v('protected_connection_missed')==='no'?'checked':'' ?> /> Nej / uklart</label>
        <div class="mt8" data-show-if="protected_connection_missed:yes">
          <?php if ($isAirShortView): ?>
          <input type="hidden" name="connection_protection_basis" value="" />
          <div class="small muted mt4">Vi spoerger ikke ind til bookingkaeden her. Den afklaring kan flyttes til sagen bagefter.</div>
          <?php elseif ($airConnectionNeedsFallback): ?>
          <div>Hvad bygger forbindelsen på?</div>
          <select name="connection_protection_basis">
            <option value="">- Vaelg grundlag -</option>
            <option value="same_booking_reference" <?= $v('connection_protection_basis')==='same_booking_reference'?'selected':'' ?>>Samme bookingreference / PNR</option>
            <option value="same_ticket" <?= $v('connection_protection_basis')==='same_ticket'?'selected':'' ?>>Samme billet / ticket chain</option>
            <option value="same_airline_interline" <?= $v('connection_protection_basis')==='same_airline_interline'?'selected':'' ?>>Samme airline / interline-forloeb</option>
            <option value="separate_tickets" <?= $v('connection_protection_basis')==='separate_tickets'?'selected':'' ?>>Saerskilte billetter</option>
            <option value="unclear" <?= $v('connection_protection_basis')==='unclear'?'selected':'' ?>>Uklart</option>
          </select>
          <div class="small muted mt4">Vises kun naar TRIN 2 ikke allerede har afgjort forbindelsestypen sikkert.</div>
          <?php else: ?>
          <input type="hidden" name="connection_protection_basis" value="" />
          <div class="small muted mt4">TRIN 2 har allerede afgjort forbindelsestypen som <?= h($airConnectionType ?: 'afgjort') ?>, så der er ikke brug for et ekstra grundlag her.</div>
          <?php endif; ?>
        </div>
      </div>

      <div id="airCancellationNotice14Card" class="small muted mt8 hidden">Hvis aflysningen blev meddelt mindst 14 dage foer, bortfalder kompensation normalt efter Art. 5(1)(c)(i), men remedy og care kan stadig vaere relevante.</div>
      <?php else: ?>
      <input type="hidden" name="protected_connection_missed" value="no" />
      <input type="hidden" name="connection_protection_basis" value="" />
      <input type="hidden" name="reroute_arrival_delay_minutes" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" />
      <input type="hidden" name="reroute_used_or_accepted" value="<?= h($airRerouteUsedOrAccepted) ?>" />
      <?php endif; ?>

      <?php if ($showAirCancellationWindows): ?>
      <div id="airCancellationRerouteCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">R</span>
          <span>Ombooking og undtagelse fra kompensation</span>
        </div>
        <p id="airCancellationRerouteHelp" class="small muted mt8"><?= $isAirShortOngoingView
            ? 'For igangvaerende flight afklarer vi her foerst, om flyselskabet tilbloed en alternativ flyvning. De mere detaljerede Article 5- og 7(2)-spoergsmaal flyttes til sagen bagefter.'
            : ($isAirShortCompletedView
                ? 'For afsluttet flight afklarer vi her, om flyselskabet tilbloed en alternativ flyvning. De mere detaljerede Article 5- og 7(2)-spoergsmaal flyttes til sagen bagefter.'
                : 'Kun relevant hvis du fik besked om aflysningen mindre end 14 dage foer. Registrer foerst om der blev tilbudt ombooking og derefter, om den holdt de juridiske tidsvinduer.') ?></p>

        <div class="mt8">
          <div>Tilboed flyselskabet en alternativ flyvning?</div>
          <label><input type="radio" name="reroute_offered" value="yes" <?= $v('reroute_offered')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_offered" value="no" <?= $v('reroute_offered')==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_offered" value="unknown" <?= $v('reroute_offered')==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
        <div id="airCancellationSelfArrangeNote" class="small muted mt8 hidden">Hvis flyselskabet ikke tilbloed en alternativ flyvning, eller hvis tilbuddet ikke bliver brugt, registreres egen loesning og eventuelle udgifter i de naeste trin eller i sagen bagefter.</div>

        <?php if ($showAirCancellationDetailQuestions): ?>
        <div id="airCancellationDetailQuestions">
        <div id="airCancellationRerouteWindows" class="mt8" data-show-if="reroute_offered:yes">
          <div id="airCancellationDepartureQuestion">Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?</div>
          <label><input type="radio" name="reroute_departure_band" value="within_window" <?= $airRerouteDepartureBand==='within_window'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_departure_band" value="outside_window" <?= $airRerouteDepartureBand==='outside_window'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_departure_band" value="unknown" <?= $airRerouteDepartureBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>

        <div class="mt8" data-show-if="reroute_offered:yes">
          <div id="airCancellationArrivalQuestion">Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?</div>
          <label><input type="radio" name="reroute_arrival_band" value="within_window" <?= $airRerouteArrivalBand==='within_window'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_arrival_band" value="outside_window" <?= $airRerouteArrivalBand==='outside_window'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_arrival_band" value="unknown" <?= $airRerouteArrivalBand==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="reroute_departure_band" value="<?= h($airRerouteDepartureBand) ?>" />
        <input type="hidden" name="reroute_arrival_band" value="<?= h($airRerouteArrivalBand) ?>" />
        <?php endif; ?>
      </div>
      <?php if ($showAirCancellationDetailQuestions): ?>
      <div id="airCancellationReductionCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">7</span>
          <span>Eventuel 50% reduktion</span>
        </div>
        <p class="small muted mt8"><?= $isCompleted ? 'Kun relevant hvis kompensation stadig bestaar efter Art. 5, og du faktisk brugte den alternative flyvning. Bruges til at vurdere en mulig 50% reduktion efter Art. 7(2).' : 'Kun relevant hvis der findes en konkret alternativ flyvning, som du forventer at bruge. Bruges kun som foreloebig vurdering af mulig 50% reduktion efter Art. 7(2).' ?></p>
        <div class="mt8">
          <div><?= $isCompleted ? 'Brugte eller accepterede du den alternative flyvning?' : 'Forventer du at bruge den alternative flyvning?' ?></div>
          <label><input type="radio" name="reroute_used_or_accepted" value="yes" <?= $airRerouteUsedOrAccepted==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="reroute_used_or_accepted" value="no" <?= $airRerouteUsedOrAccepted==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="reroute_used_or_accepted" value="unknown" <?= $airRerouteUsedOrAccepted==='unknown'?'checked':'' ?> /> Ved ikke</label>
        </div>
        <div class="mt8" data-show-if="reroute_used_or_accepted:yes">
          <div><?= $isCompleted ? 'Hvor mange minutter senere ankom du til den endelige destination?' : 'Hvor mange minutter senere forventes du at ankomme til den endelige destination?' ?></div>
          <input type="number" name="reroute_arrival_delay_minutes" min="0" step="1" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" placeholder="95" />
          <div class="small muted mt4">Dette bruges kun til Art. 7(2)-reduktion og ikke til Article 5-gaten ovenfor.</div>
        </div>
      </div>
      <div id="airCancellationReductionNotRelevantCard" class="card mt12 hidden" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="small muted">50% reduktion er ikke relevant her, fordi kompensation ikke staar tilbage efter Article 5-vurderingen.</div>
      </div>
      <?php else: ?>
      <input type="hidden" name="reroute_used_or_accepted" value="<?= h($airRerouteUsedOrAccepted) ?>" />
      <input type="hidden" name="reroute_arrival_delay_minutes" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" />
      <?php endif; ?>
      <?php else: ?>
      <input type="hidden" name="reroute_offered" value="<?= h($v('reroute_offered')) ?>" />
      <input type="hidden" name="reroute_departure_band" value="<?= h($airRerouteDepartureBand) ?>" />
      <input type="hidden" name="reroute_arrival_band" value="<?= h($airRerouteArrivalBand) ?>" />
      <input type="hidden" name="reroute_used_or_accepted" value="<?= h($airRerouteUsedOrAccepted) ?>" />
      <input type="hidden" name="reroute_arrival_delay_minutes" value="<?= h($v('reroute_arrival_delay_minutes')) ?>" />
      <?php endif; ?>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">P</span>
          <span>Artikel 11: PMR</span>
        </div>
        <p class="small muted mt8">Bevaegelseshaemmede personer og personer med saerlige behov har foersteprioritet ved transport og artikel 9-care saa hurtigt som muligt.</p>
        <div class="mt8">
          <div>Har passageren behov for saerlig assistance / PMR?</div>
          <label><input type="radio" name="pmr_user" value="yes" <?= $v('pmr_user')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="pmr_user" value="no" <?= $v('pmr_user')==='no'?'checked':'' ?> /> Nej</label>
        </div>
        <div class="small muted mt8" data-show-if="pmr_user:yes">PMR-sporet bruges her som prioritet/accelerator for assistance. Det paavirker ikke i sig selv kompensation, refusion eller downgrade.</div>
      </div>

      <div class="card mt12" style="border-color:#d0d7de;background:#f8f9fb;">
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">U</span>
          <span>Artikel 11: Uledsaget barn</span>
        </div>
        <p class="small muted mt8">Uledsagede boern har efter artikel 11 ogsaa foersteprioritet ved transport og artikel 9-care saa hurtigt som muligt.</p>
        <div class="mt8">
          <div>Var der tale om et uledsaget barn?</div>
          <label><input type="radio" name="unaccompanied_minor" value="yes" <?= $v('unaccompanied_minor')==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="unaccompanied_minor" value="no" <?= $v('unaccompanied_minor')==='no'?'checked':'' ?> /> Nej</label>
        </div>
        <div class="small muted mt8" data-show-if="unaccompanied_minor:yes">Uledsaget barn har efter artikel 11 ogsaa ret til artikel 9-care saa hurtigt som muligt. Det paavirker ikke i sig selv kompensation, refusion eller downgrade.</div>
      </div>

      <?php if (!$isAirShortOngoingView): ?>
      <div class="card mt12" style="border-color:#e8d7aa;background:#fffaf0">
        <div class="widget-title">
          <span class="fm-badge" title="Force majeure / extraordinary circumstances">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
              <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
            </svg>
          </span>
          <span>Force majeure</span>
        </div>
        <p class="small muted">
          <?= $isAirShortView && $isCompleted
              ? 'Hvis flyselskabet har oplyst en saerlig grund, registrerer vi den her tidligt, fordi den kan paavirke kompensationen. Care og hotel behandles i et senere trin.'
              : 'Bruges i air-flowet til at vurdere om kompensation kan bortfalde pga. extraordinary circumstances. Care og hotel behandles i et senere trin.' ?>
        </p>

        <div class="mt8">
          <div><?= $isAirShortView && $isCompleted ? 'Har flyselskabet oplyst en saerlig grund til aflysningen eller forsinkelsen?' : 'Paaberaaber flyselskabet extraordinary circumstances?' ?></div>
          <label><input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc0==='yes'?'checked':'' ?> /> Ja</label>
          <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc0==='no'?'checked':'' ?> /> Nej</label>
          <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc0===''||$exc0==='unknown')?'checked':'' ?> /> Ved ikke</label>
        </div>
        <div class="mt8" data-show-if="operatorExceptionalCircumstances:yes">
          <div><?= $isAirShortView && $isCompleted ? 'Hvilken grund oplyste flyselskabet?' : 'Hvis ja: vaelg type' ?></div>
          <select name="operatorExceptionalType">
            <option value="">- Vaelg grund -</option>
            <option value="weather" <?= $excType0==='weather'?'selected':'' ?>>Vejr</option>
            <option value="air_traffic_control" <?= $excType0==='air_traffic_control'?'selected':'' ?>>ATC / luftrum</option>
            <option value="security" <?= $excType0==='security'?'selected':'' ?>>Sikkerhed</option>
            <option value="external_strike" <?= $excType0==='external_strike'?'selected':'' ?>>Ekstern strejke</option>
            <option value="own_staff_strike" <?= $excType0==='own_staff_strike'?'selected':'' ?>>Egen personalestrejke</option>
            <option value="technical_issue" <?= $excType0==='technical_issue'?'selected':'' ?>>Teknisk fejl</option>
            <option value="crew_shortage" <?= $excType0==='crew_shortage'?'selected':'' ?>>Crew / bemanding</option>
            <option value="other" <?= $excType0==='other'?'selected':'' ?>>Andet</option>
          </select>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="operatorExceptionalCircumstances" value="" />
      <input type="hidden" name="operatorExceptionalType" value="" />
      <?php endif; ?>
      <input type="hidden" name="extraordinary_circumstances" value="" />

      <input type="hidden" name="meal_offered" value="" />
      <input type="hidden" name="hotel_required" value="" />
      <input type="hidden" name="hotel_offered" value="" />

      <?php if (false && $isAirShortView && $isCompleted): ?>
        <div class="card mt12" style="border-color:#dbeafe;background:#f8fbff;">
          <div class="widget-title">
            <span class="step-badge" aria-hidden="true">+</span>
            <span>Sagsbackend bagefter</span>
          </div>
          <p class="small muted mt8">Frontflowet holdes kort. Marker her, hvis vi bagefter skal åbne ekstraudgifter og dokumentupload i dit kontrolpanel.</p>

          <input type="hidden" name="air_incident_expenses_incurred" value="" />
          <input type="hidden" name="air_backend_needs_documents" value="no" />
        </div>
      <?php endif; ?>

      <?php if ($isAirShortView && $isCompleted): ?>
        <input type="hidden" name="air_incident_expenses_incurred" value="" />
        <input type="hidden" name="air_backend_needs_documents" value="no" />
      <?php endif; ?>

      <?php if ($isAirShortView): ?>
        <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">Dette air-spor er gjort lettere i frontflowet. Mere teknisk kontraktanalyse og dokumentkontrol kan flyttes til sagen bagefter.</div>
      <?php elseif (!empty($airScope) || !empty($airContract) || !empty($airRights)): ?>
        <div class="small" style="margin-top:10px; background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <div><strong>Resolver status</strong></div>
          <div>Scope: <?= !empty($airScope['regulation_applies']) ? 'In scope' : 'Out of scope' ?></div>
          <div>Distancekategori: <?= h($airDistanceBandLabel) ?></div>
          <div>Art. 6 delay-threshold: <?= h($airDelayThresholdLabel) ?></div>
          <div>Aflysningsvarsel: <?= h(match ($airCancellationNoticeBand) {
              '14_plus_days' => '14+ dage',
              '7_to_13_days' => '7-13 dage',
              'under_7_days' => 'Under 7 dage',
              'airport_on_day_of_departure' => 'I lufthavnen / ved afgang',
              'unknown' => 'Ved ikke',
              default => 'Ikke angivet',
          }) ?></div>
          <div>Claim-kanal: <?= h((string)($airContract['primary_claim_party_name'] ?? ($airContract['primary_claim_party'] ?? 'manual_review'))) ?></div>
          <div>Care: <?= !empty($airRights['gate_air_care']) ? 'Ja' : 'Nej' ?></div>
          <div>Reroute/refund: <?= !empty($airRights['gate_air_reroute_refund']) ? 'Ja' : 'Nej' ?></div>
          <div>Delay 5h refund: <?= !empty($airRights['gate_air_delay_refund_5h']) ? 'Ja' : 'Nej' ?></div>
          <div>Kompensation: <?= !empty($airRights['gate_air_compensation']) ? 'Candidate' : 'Ikke aktiveret' ?><?= !empty($airRights['air_comp_band']) ? ' - ' . h((string)$airRights['air_comp_band']) : '' ?></div>
        </div>
      <?php endif; ?>
<?php else: ?>
      <strong><span aria-hidden="true">&#x26A1;</span> Haendelse (Art.18/20)</strong>
      <p class="small muted">Vaelg den haendelse, der ramte dit tog. Bruges til at aktivere standard vurdering af Art. 18/20.<?= $incidentHint !== '' ? (' ' . h($incidentHint)) : '' ?></p>
      <?php /*
        <div class="widget-title">
          <span class="step-badge" aria-hidden="true">&#128646;</span>
          <span>Rail-kontekstpanel</span>
        </div>
        <?php if ($railJourneyRoute !== ''): ?>
          <div class="mt8"><strong>Valgt rejse:</strong> <?= h($railJourneyRoute) ?></div>
        <?php endif; ?>
        <?php if ($railJourneyService !== '' || $railJourneyOperatorNames !== []): ?>
          <div class="small muted mt4">
            <?php if ($railJourneyService !== ''): ?>Foerste tog: <strong><?= h($railJourneyService) ?></strong><?php endif; ?>
            <?php if ($railJourneyOperatorNames !== []): ?>
              <?= $railJourneyService !== '' ? ' · ' : '' ?>Operatoer<?= count($railJourneyOperatorNames) === 1 ? '' : 'er' ?>: <strong><?= h(implode(' · ', $railJourneyOperatorNames)) ?></strong>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="small muted mt8" style="background:#ffffff; border:1px solid #dbeafe; border-radius:6px; padding:8px;">
          <strong>Systemet har fundet</strong>:
          <?= h(match ($railSeedIncidentType) {
              'delay' => 'mulig forsinkelse',
              'cancellation' => 'mulig aflysning',
              'partial_cancellation' => 'mulig delvis aflysning',
              'replacement_transport' => 'mulig erstatningstransport',
              default => 'ingen sikker haendelse endnu',
          }) ?>
          <?php if ($railSeedArrivalDelay !== null): ?>
            <?= h(' · seedet ankomstforsinkelse: ' . $railSeedArrivalDelay . ' min') ?>
          <?php endif; ?>
          <?php if ($railSeedTransferCount > 0): ?>
            <?= h(' · ' . $railSeedTransferCount . ' skift i rejsen') ?>
          <?php endif; ?>
          <?php if ($railSeedMissedConnectionSuspected): ?>
            <?= h(' · mulig mistet forbindelse') ?>
          <?php endif; ?>
          .
        </div>
        <div class="grid-2 mt8">
          <div>
            <div><strong>Art. 12:</strong> <?= h($railContractModelLabel) ?></div>
            <div class="small muted mt4">Koebskanal: <strong><?= h($railSellerChannelLabel) ?></strong></div>
            <div class="small muted mt4">Samme transaktion: <strong><?= h($railSameTransactionLabel) ?></strong></div>
            <div class="small muted mt4">Oplyst om gennemgaaende/saerskilte billetter: <strong><?= h($railDisclosureLabel) ?></strong></div>
            <div class="small muted mt4">Separate kontrakter angivet paa billet/booking: <strong><?= h($railSeparateNoticeLabel) ?></strong></div>
            <?php if ($railContractModel === 'separate' && $railProblemContractLabel !== ''): ?>
              <div class="small muted mt4">Valgt problemkontrakt: <strong><?= h($railProblemContractLabel) ?></strong></div>
            <?php endif; ?>
          </div>
          <div>
            <?php if ($railProblemAnchorSummary !== ''): ?>
              <div><strong>Problemsted fra TRIN 3:</strong> <?= h($railProblemAnchorSummary) ?></div>
            <?php endif; ?>
            <?php if ($railStrandedStation !== ''): ?>
              <div class="small muted mt4">Strandingsstation: <strong><?= h($railStrandedStation) ?></strong></div>
            <?php endif; ?>
            <?php if ($railStillThereLabel !== ''): ?>
              <div class="small muted mt4">Stadig paa strandingsstationen: <strong><?= h($railStillThereLabel) ?></strong></div>
            <?php endif; ?>
            <?php if ($railWhereEndedLabel !== ''): ?>
              <div class="small muted mt4">Station-kontekst: <strong><?= h($railWhereEndedLabel) ?></strong></div>
            <?php endif; ?>
            <?php if ($railOutcomeStation !== ''): ?>
              <div class="small muted mt4">Nuvaerende / foreloebig station: <strong><?= h($railOutcomeStation) ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      */ ?>

      <div class="mt8">
        <div>Haendelsestype (vaelg en)</div>
        <label><input type="radio" name="incident_main" value="delay" <?= $railIncidentMainValue==='delay'?'checked':'' ?> /> Forsinkelse</label>
        <label class="ml8"><input type="radio" name="incident_main" value="cancellation" <?= $railIncidentMainValue==='cancellation'?'checked':'' ?> /> Aflysning</label>
      </div>

      <div class="mt4" data-show-if="incident_main:delay">
        <div><?= h($railExpectedDelayPrompt) ?></div>
        <label><input type="radio" name="expected_delay_60" value="yes" <?= $railExpectedDelay60Value==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="expected_delay_60" value="no" <?= $railExpectedDelay60Value==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>

      <div class="mt8" data-show-if="incident_main:delay">
        <div><?= h($railActualDelayPrompt) ?></div>
        <label><input type="radio" name="delay_already_60" value="yes" <?= $railDelayAlready60Value==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="delay_already_60" value="no" <?= $railDelayAlready60Value==='no'?'checked':'' ?> /> <?= h($railActualDelayNoLabel) ?></label>
        <div class="small muted mt4"><?= h($railActualDelayNote) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- Mistet forbindelse -->
  <?php if (false): ?>
  <div class="card mt12">
    <strong><span aria-hidden="true">&#128206;</span> Mistet forbindelse</strong>
    <p class="small muted">Hvis problemet opstod ved et skift, hentes fokuspunktet fra TRIN 3. Her bekræfter du kun, om den valgte forbindelse faktisk blev misset.</p>
    <?php if ($railSeedMissedConnectionSuspected): ?>
      <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">Systemet har fundet en mulig mistet forbindelse pga. valgt afgang og dens forsinkelse. Bekræft nedenfor, om forbindelsen faktisk blev misset.</div>
    <?php endif; ?>

    <?php if ($railTransferStationsSummary !== ''): ?>
      <div class="small muted mt8">Skift i den valgte rejse: <strong><?= h($railTransferStationsSummary) ?></strong></div>
    <?php endif; ?>

    <div class="small muted mt8">Hvis problemet opstod ved et skift, kommer fokuspunktet nu fra TRIN 3 og bruges kun som reference her.</div>

    <input type="hidden" name="incident_missed" value="<?= $missedConnectionChosen ? 'yes' : 'no' ?>" />

    <?php if ($missedConnectionChosen): ?>
      <div class="mt8 small">
        Registreret forbindelse:
        <strong>
          <?= h($missedConnectionPick !== '' ? $missedConnectionPick : $missedConnectionStation) ?>
        </strong>
      </div>
      <div class="mt8">
        <div><?= h($railMissedConnectionPrompt) ?></div>
        <label><input type="radio" name="incident_missed" value="yes" <?= $railIncidentMissedValue==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="incident_missed" value="no" <?= $railIncidentMissedValue==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>
      <div id="missed60Wrap" class="mt8" data-show-if="incident_missed:yes">
        <div><?= h($railMissedConnectionDelayPrompt) ?></div>
        <label><input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        <div class="small muted mt4">Hvis nej, kan nationale ordninger stadig være relevante afhængigt af land.</div>
      </div>
    <?php else: ?>
      <div class="small muted mt8">Ingen forbindelse valgt endnu. Gå tilbage til TRIN 3, hvis problemet opstod ved et skift, og vælg fokuspunktet der.</div>
    <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($railShowMissedConnectionBlock): ?>
  <div class="card mt12" data-show-if="incident_main:delay,cancellation">
    <strong><span aria-hidden="true">&#128206;</span> Mistet forbindelse</strong>
    <p class="small muted"><?= h($railMissedConnectionIntro) ?></p>
    <?php if ($railSeedMissedConnectionSuspected): ?>
      <div class="small muted mt8" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:6px; padding:8px;">Systemet har fundet en mulig mistet forbindelse pga. valgt afgang og dens forsinkelse. Bekraeft nedenfor, om forbindelsen faktisk blev misset.</div>
    <?php endif; ?>
    <div class="small muted mt8 hidden rail-missed60-delay-note"><?= h($railMissedConnectionDelayNote) ?></div>
    <div class="small muted mt8 hidden rail-missed60-cancellation-note"><?= h($railMissedConnectionCancellationNote) ?></div>

    <?php if ($railTransferStationsSummary !== ''): ?>
      <div class="small muted mt8">Skift i den valgte rejse: <strong><?= h($railTransferStationsSummary) ?></strong></div>
    <?php endif; ?>

    <div class="small muted mt8"><?= h($railMissedConnectionReferenceNote) ?></div>

    <input type="hidden" name="incident_missed" value="<?= $missedConnectionChosen ? 'yes' : 'no' ?>" />

    <?php if ($railMissedConnectionHasTransferAnchor): ?>
      <div class="mt8 small">
        Registreret forbindelse:
        <strong><?= h($missedConnectionPick !== '' ? $missedConnectionPick : ('Ved skift i ' . $missedConnectionStation)) ?></strong>
      </div>
      <div class="mt8">
        <div><?= h($railMissedConnectionPrompt) ?></div>
        <label><input type="radio" name="incident_missed" value="yes" <?= $railIncidentMissedValue==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="incident_missed" value="no" <?= $railIncidentMissedValue==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>
      <div class="mt8 rail-missed60-wrap" data-show-if="incident_missed:yes">
        <div><?= h($railMissedConnectionDelayPrompt) ?></div>
        <label><input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        <div class="small muted mt4">Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.</div>
      </div>
    <?php else: ?>
      <div class="small muted mt8"><?= h($railMissedConnectionNoChoiceNote) ?></div>
      <div class="mt8">
        <div><strong>Medfoerte haendelsen ogsaa en mistet videre forbindelse?</strong></div>
        <label><input type="radio" name="incident_missed" value="yes" <?= $railIncidentMissedValue==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="incident_missed" value="no" <?= $railIncidentMissedValue==='no'?'checked':'' ?> /> Nej / ved ikke</label>
      </div>
      <?php if ($railCanChooseFollowOnConnection): ?>
      <div class="mt8" data-show-if="incident_missed:yes">
        <label for="railMissedConnectionStation"><strong>Hvilken videre forbindelse blev misset?</strong></label>
        <select id="railMissedConnectionStation" name="missed_connection_station" class="mt4">
          <option value="">Vaelg skiftestation</option>
          <?php foreach ($railTransferStations as $transferStationName): ?>
            <option value="<?= h($transferStationName) ?>" <?= $missedConnectionStation === $transferStationName ? 'selected' : '' ?>><?= h('Ved skift i ' . $transferStationName) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="mt8 rail-missed60-wrap" data-show-if="incident_missed:yes">
        <div><?= h($railMissedConnectionDelayPrompt) ?></div>
        <label><input type="radio" name="missed_expected_delay_60" value="yes" <?= $v('missed_expected_delay_60')==='yes'?'checked':'' ?> /> Ja</label>
        <label class="ml8"><input type="radio" name="missed_expected_delay_60" value="no" <?= $v('missed_expected_delay_60')==='no'?'checked':'' ?> /> Nej / ved ikke</label>
        <div class="small muted mt4">Hvis nej, kan nationale ordninger stadig vaere relevante afhaengigt af land.</div>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- Form & exemptions (moved from TRIN 9) -->
  <div class="card mt12">
    <div class="widget-title">
      <span class="fm-badge" title="Force majeure / ekstraordinaere forhold">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path fill="#8a6d3b" d="M7 18a5 5 0 0 1 0-10a6 6 0 0 1 11.3 1.7A4.5 4.5 0 0 1 18.5 18H7z"/>
          <path fill="#8a6d3b" d="M12.2 21l2.7-5.2h-2.1l1.5-4.3l-4.6 6.6h2.2L9.6 21z"/>
        </svg>
      </span>
      <span>Force majeure</span>
    </div>
    <div class="small mt4">Udbetaling sker som udgangspunkt kontant. Vouchers accepteres ikke i denne loesning.</div>
    <input type="hidden" name="voucherAccepted" value="no" />

    <?php $exc = (string)($form['operatorExceptionalCircumstances'] ?? ''); ?>
    <div class="mt8">
      Henviser operatoeren til ekstraordinaere forhold (Art. 19(10))?
    </div>
    <label><input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $exc==='yes'?'checked':'' ?> /> Ja</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $exc==='no'?'checked':'' ?> /> Nej</label>
    <label class="ml8"><input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($exc===''||$exc==='unknown')?'checked':'' ?> /> Ved ikke</label>

    <?php $excType = (string)($form['operatorExceptionalType'] ?? ''); ?>
    <div class="mt8" data-show-if="operatorExceptionalCircumstances:yes">
      <div class="small">Hvis ja: vaelg type (bruges til korrekt undtagelse, fx egen personalestrejke udelukker ikke kompensation)</div>
      <select name="operatorExceptionalType">
        <option value="">- Vaelg type -</option>
        <option value="weather" <?= $excType==='weather'?'selected':'' ?>>Vejr</option>
        <option value="sabotage" <?= $excType==='sabotage'?'selected':'' ?>>Sabotage</option>
        <option value="infrastructure_failure" <?= $excType==='infrastructure_failure'?'selected':'' ?>>Infrastrukturfejl</option>
        <option value="third_party" <?= $excType==='third_party'?'selected':'' ?>>Tredjepart</option>
        <option value="own_staff_strike" <?= $excType==='own_staff_strike'?'selected':'' ?>>Egen personalestrejke</option>
        <option value="external_strike" <?= $excType==='external_strike'?'selected':'' ?>>Ekstern strejke</option>
        <option value="other" <?= $excType==='other'?'selected':'' ?>>Andet</option>
      </select>
    </div>

    <div class="mt8">
      <label><input type="checkbox" name="minThresholdApplies" value="1" <?= !empty($form['minThresholdApplies']) ? 'checked' : '' ?> /> Anvend min. taerskel <= 4 EUR (Art. 19(8))</label>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$isFerry && !$isBus && !$isAir): ?>
  <!-- National fallback (shown only when EU 60-min gate is NOT met; displayed after "last chance" + force majeure) -->
  <div id="nationalFallbackWrap" class="card mt12 hidden" style="border-color:#ffe8cc;background:#fff8e6">
    <strong>National ordning (fallback)<?= (!empty($nationalPolicy['name']) ? (': ' . h((string)$nationalPolicy['name'])) : '') ?></strong>
    <div class="small mt4">
      EU: Art. 18/20 udloeses typisk ved <strong>&ge;60 min</strong> forsinkelse (eller aflysning).
    </div>
    <div class="small mt4">
      National ordning:
      <?php if ($nationalCutoff !== null && $nationalCutoff > 0 && $nationalCutoff < 60): ?>
        kompensation fra <strong><?= (int)$nationalCutoff ?> min</strong><?= ($nationalThr50 !== null && $nationalThr50 > 0) ? (' (naeste band: ' . (int)$nationalThr50 . ' min)') : '' ?>.
      <?php else: ?>
        <span class="muted">ukendt (kraever land + scope).</span>
      <?php endif; ?>
    </div>

    <div id="nationalFallbackBlockedHint" class="small mt8 hidden" style="background:#fff;border:1px solid #cfe0ff;border-radius:6px;padding:8px;">
      <strong>Foer vi gaar til national ordning:</strong> Svar paa spoergsmaalet ovenfor om det missede skift giver <strong>&ge;60 min</strong> til din endelige destination.
    </div>

    <?php if ($compBlockedByFM): ?>
      <div class="small mt8" style="background:#fff;border:1px solid #f5c2c7;border-radius:6px;padding:8px;">
        <strong>Bemaerk:</strong> Du har angivet ekstraordinaere forhold (Art. 19(10)). Kompensation kan vaere udelukket (EU + national),
        men Art. 18/20 kan stadig blive relevant ved <strong>&ge;60 min</strong> eller aflysning.
      </div>
    <?php endif; ?>

    <div id="nationalFallbackInputs" class="mt8">
      <label class="small">Hvor mange minutter var/er du forsinket?
        <input id="nationalDelayMinutes" type="number" name="national_delay_minutes" min="0" step="1" value="<?= h($v('national_delay_minutes')) ?>" placeholder="minutter" />
      </label>
      <input type="hidden" id="nationalDelayReportedAt" name="national_delay_reported_at" value="<?= h($v('national_delay_reported_at')) ?>" />
      <div class="small muted mt4">Denne oplysning bruges kun til national fallback - den aktiverer ikke Art. 18/20.</div>
    </div>

    <div id="euReminderWrap" class="mt12 hidden">
      <div class="small"><strong>Reminder (igangvaerende rejse)</strong></div>
      <div class="small mt4">
        Hvis forsinkelsen stiger til <strong>60 min</strong>, kan EU-rettigheder (Art. 18/20) blive relevante.
        <span id="euReminderInfo"></span>
      </div>
      <div class="mt8" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="button" id="startEuReminder" style="background:#eee;color:#333;">Start reminder</button>
        <span class="small muted" id="euReminderStatus"></span>
      </div>
      <div id="euReminderPrompt" class="card mt8 hidden" style="border-color:#cfe0ff;background:#f1f8ff">
        <div class="small"><strong>Reminder</strong></div>
        <div class="small mt4">Du kan nu vaere >=60 min forsinket. Er du det?</div>
        <div class="mt8" style="display:flex;gap:8px;align-items:center;">
          <button type="button" class="button" id="euReminderYes">Ja</button>
          <button type="button" class="button" id="euReminderNo" style="background:#eee;color:#333;">Nej</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="mt12" style="display:flex; gap:8px; align-items:center;">
    <?= $this->Html->link('<- Tilbage', ['action' => $incidentPrevAction], ['class' => 'button', 'style' => 'background:#eee; color:#333;']) ?>
    <?= $this->Form->button('Naeste trin ->', ['class' => 'button', 'id' => 'incidentSubmitBtn', 'form' => 'incidentStepForm']) ?>
  </div>

  </fieldset>
  <?= $this->Form->end() ?>
  <?= $this->element('flow_autosave', ['step' => 'incident', 'formSelector' => '#incidentStepForm']) ?>

  <div class="mt12">
    <button type="button" class="button" id="loadHooksBtn" style="background:#eee;color:#333;">
      Indlaes sidepanel
    </button>
    <div id="hooksPanel" class="card mt12 hidden" hidden>
      <div class="small muted">Sidepanel ikke indlaest endnu.</div>
    </div>
  </div>
</div>

<script>
var __incidentMode = <?= json_encode($gatingMode) ?>;
var __incidentIsRail = __incidentMode === 'rail';
var __incidentIsFerry = __incidentMode === 'ferry';
var __incidentIsBus = __incidentMode === 'bus';
var __incidentIsAir = __incidentMode === 'air';
var __incidentShowHooksPanel = <?= json_encode($showHooksPanel) ?>;

function updateReveal() {
  document.querySelectorAll('[data-show-if]').forEach(function(el) {
    var spec = el.getAttribute('data-show-if'); if (!spec) return;
    var parts = spec.split(':'); if (parts.length !== 2) return;
    var name = parts[0]; var valid = parts[1].split(',');
    var val = '';
    var checked = document.querySelector('input[name="' + name + '"]:checked');
    if (checked) {
      val = checked.value || '';
    } else {
      var sel = document.querySelector('select[name="' + name + '"]');
      if (sel) { val = sel.value || ''; }
    }
    var show = val && valid.includes(val);
    el.style.display = show ? 'block' : 'none';
    el.hidden = !show;
  });
}
function getVal(name) {
  var checked = document.querySelector('input[name="' + name + '"]:checked');
  if (checked) { return checked.value || ''; }
  var sel = document.querySelector('select[name="' + name + '"]');
  if (sel) { return sel.value || ''; }
  var inp = document.querySelector('input[name="' + name + '"]');
  if (inp && inp.type !== 'radio' && inp.type !== 'checkbox') { return inp.value || ''; }
  return '';
}
function clearRadioGroup(name) {
  document.querySelectorAll('input[name="' + name + '"]').forEach(function(el) {
    el.checked = false;
  });
}
document.addEventListener('change', function(e) {
  if (!e.target || !e.target.name) return;
  if (__incidentIsFerry && ['ferry_departure_disruption_90', 'ferry_cancellation_confirmed', 'ferry_incident_main'].includes(e.target.name)) {
    clearRadioGroup('expected_departure_delay_90_override');
    clearRadioGroup('actual_departure_delay_90_override');
    setFirstNamedValue('ferry_operational_data_confirmed', '');
  }
  updateReveal();
  if (__incidentIsFerry) {
    syncFerryOperationalCorrections();
    updateFerryResolverStatus();
    updateFerryLiveEstimatePreview();
  }
  if (__incidentIsAir) {
    updateAirCancellationState();
    updateAirLiveEstimatePreview();
  }
  if (__incidentIsBus) {
    updateBusIncidentState();
    updateBusDelayTimerUi();
  }
  if (__incidentIsRail) {
    updateStep4State();
    updateRailLiveEstimatePreview();
  }
});
document.addEventListener('input', function(e) {
  if (!e.target || !e.target.name) return;
  if (__incidentIsFerry && ['scheduled_journey_duration_minutes', 'delay_minutes_departure', 'arrival_delay_minutes'].includes(e.target.name)) {
    syncFerryOperationalCorrections();
    updateFerryLiveEstimatePreview();
  }
  if (__incidentIsRail && ['national_delay_minutes'].includes(e.target.name)) {
    updateRailLiveEstimatePreview();
  }
});
document.addEventListener('DOMContentLoaded', function(){
  updateReveal();
  if (__incidentIsFerry) {
    updateFerryResolverStatus();
    updateFerryLiveEstimatePreview();
  }
  if (__incidentIsAir) {
    updateAirCancellationState();
    updateAirLiveEstimatePreview();
  }
  if (__incidentIsBus) {
    updateBusIncidentState();
    setupBusDelayTimer();
    updateBusDelayTimerUi();
  }
  if (__incidentIsRail) {
    updateStep4State();
    updateRailLiveEstimatePreview();
  }

  var panel = document.getElementById('hooksPanel');
  var loadHooksBtn = document.getElementById('loadHooksBtn');
  if (panel && loadHooksBtn) {
    var loadHooksPanel = function() {
      if (panel.dataset.loading === '1' || panel.dataset.loaded === '1') return;
      panel.dataset.loading = '1';
      panel.hidden = false;
      panel.classList.remove('hidden');
      loadHooksBtn.disabled = true;
      loadHooksBtn.textContent = 'Indlaeser sidepanel...';
      var url = new URL(window.location.href);
      url.searchParams.set('ajax_hooks', '1');
      fetch(url.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function(resp) {
        return resp.text();
      }).then(function(txt) {
        panel.innerHTML = txt;
        panel.dataset.loaded = '1';
        loadHooksBtn.textContent = 'Sidepanel indlaest';
      }).catch(function() {
        panel.innerHTML = '<div class="small muted">Sidepanel kunne ikke indlaeses.</div>';
        loadHooksBtn.disabled = false;
        loadHooksBtn.textContent = 'Indlaes sidepanel';
      }).finally(function() {
        panel.dataset.loading = '0';
      });
    };

    loadHooksBtn.addEventListener('click', loadHooksPanel);
    if (__incidentShowHooksPanel) {
      loadHooksPanel();
    }
  }

  var opsSuggestCancellation = document.getElementById('airOpsSuggestCancellation');
  if (opsSuggestCancellation) {
    opsSuggestCancellation.addEventListener('click', function(){
      setRadioValue('incident_main', 'cancellation');
      updateReveal();
      updateAirCancellationState();
      updateAirLiveEstimatePreview();
    });
  }

  var opsSuggestDelay = document.getElementById('airOpsSuggestDelay');
  if (opsSuggestDelay) {
    opsSuggestDelay.addEventListener('click', function(){
      setRadioValue('incident_main', 'delay');
      <?php if ($airOpsDelayMinutes !== null): ?>
      setInputValue('arrival_delay_minutes', <?= json_encode((string)$airOpsDelayMinutes) ?>);
      setRadioValue('air_actual_arrival_delay_bucket', airActualArrivalBucketFromMinutes(<?= json_encode((int)$airOpsDelayMinutes) ?>));
      <?php endif; ?>
      <?php if ($airOpsSuggestedDelayBand !== ''): ?>
      setRadioValue('air_expected_delay_bucket', airExpectedDelayBucketFromLegacyBand(<?= json_encode($airOpsSuggestedDelayBand) ?>));
      <?php endif; ?>
      updateReveal();
      updateAirCancellationState();
      updateAirLiveEstimatePreview();
    });
  }

  var ferryEditOperationalData = document.getElementById('ferryEditOperationalData');
  var ferryCorrectionPanel = document.getElementById('ferryOperationalCorrectionPanel');
  if (ferryEditOperationalData && ferryCorrectionPanel) {
    ferryEditOperationalData.addEventListener('click', function() {
      ferryCorrectionPanel.classList.remove('hidden');
      ferryCorrectionPanel.hidden = false;
      updateReveal();
      syncFerryOperationalCorrections();
      updateFerryLiveEstimatePreview();
    });
  }

  var ferryConfirmOperationalData = document.getElementById('ferryConfirmOperationalData');
  if (ferryConfirmOperationalData) {
    ferryConfirmOperationalData.addEventListener('click', function() {
      setFirstNamedValue('ferry_operational_data_confirmed', 'yes');
      syncFerryOperationalCorrections();
      updateFerryResolverStatus();
      updateFerryLiveEstimatePreview();
      ferryConfirmOperationalData.textContent = 'Bekraeftet';
    });
  }

  var incidentStepForm = document.getElementById('incidentStepForm');
  if (incidentStepForm && __incidentIsFerry) {
    incidentStepForm.addEventListener('submit', function() {
      syncFerryOperationalCorrections();
    });
  }
});

function getRadioValue(name){
  var r = document.querySelector('input[name="' + name + '"]:checked');
  return r ? r.value : '';
}
function showById(id, show){
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.toggle('hidden', !show);
  el.hidden = !show;
}

function setBlockVisible(el, show) {
  if (!el) return;
  el.style.display = show ? 'block' : 'none';
  el.hidden = !show;
}

function formatAirEstimateAmount(value) {
  var numeric = parseFloat(value);
  if (!isFinite(numeric)) return 'Afventer';
  return numeric.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EUR';
}

function updateAirLiveEstimatePreview() {
  var root = document.getElementById('airLiveEstimate');
  var amountEl = document.getElementById('airLiveEstimateAmount');
  var statusEl = document.getElementById('airLiveEstimateStatus');
  var summaryEl = document.getElementById('airLiveEstimateSummary');
  var careEl = document.getElementById('airLiveEstimateCareValue');
  var remedyEl = document.getElementById('airLiveEstimateRemedyValue');
  if (!root || !amountEl || !statusEl || !summaryEl) return;

  var hasKnownDistance = root.dataset.hasKnownDistance === '1';
  var travelState = String(root.dataset.travelState || '').toLowerCase();
  var passengerCount = parseInt(root.dataset.passengerCount || '1', 10);
  var potentialTotal = parseFloat(root.dataset.potentialTotal || '0');
  var incidentMain = getFerryIncidentMain();
  var expectedDelay = getRadioValue('air_expected_delay_bucket');
  var actualArrival = getRadioValue('air_actual_arrival_delay_bucket');
  var notice = getRadioValue('cancellation_notice_band');
  var reroute = getRadioValue('reroute_offered');
  var depWindow = getRadioValue('reroute_departure_band');
  var arrWindow = getRadioValue('reroute_arrival_band');
  var voluntaryDenied = getRadioValue('voluntary_denied_boarding');
  var extraordinary = getRadioValue('extraordinary_circumstances');
  var pmrUser = getRadioValue('pmr_user');
  var unaccompaniedMinor = getRadioValue('unaccompanied_minor');
  var peopleSuffix = passengerCount > 1 ? (' for ' + passengerCount + ' passagerer') : ' pr. sag';
  var state = 'preview';
  var careActive = false;
  var remedyActive = false;

  if (incidentMain === 'delay') {
    careActive = expectedDelay === 'threshold_to_under_5h' || expectedDelay === 'five_plus' || expectedDelay === 'next_day' || pmrUser === 'yes' || unaccompaniedMinor === 'yes';
    remedyActive = expectedDelay === 'five_plus' || expectedDelay === 'next_day';
  } else if (incidentMain === 'cancellation') {
    careActive = true;
    remedyActive = true;
  } else if (incidentMain === 'denied_boarding') {
    careActive = voluntaryDenied !== 'yes' || pmrUser === 'yes' || unaccompaniedMinor === 'yes';
    remedyActive = true;
  } else if (pmrUser === 'yes' || unaccompaniedMinor === 'yes') {
    careActive = true;
  }

  if (careEl) {
    careEl.textContent = careActive ? 'Aktiv' : 'Ikke aktiv endnu';
  }
  if (remedyEl) {
    remedyEl.textContent = remedyActive ? 'Aktiv' : 'Ikke aktiv endnu';
  }

  if (extraordinary === 'yes') {
    state = 'uncertain';
  } else if (incidentMain === 'delay') {
    if (travelState === 'completed') {
      if (actualArrival === 'under_3h') {
        state = 'not_eligible';
      } else if (actualArrival === 'three_to_four' || actualArrival === 'four_plus' || actualArrival === 'never_arrived') {
        state = 'eligible';
      } else if (actualArrival === 'unknown') {
        state = 'uncertain';
      }
    } else {
      if (expectedDelay === 'under_threshold') {
        state = 'inactive';
      } else if (expectedDelay === 'threshold_to_under_5h' || expectedDelay === 'five_plus' || expectedDelay === 'next_day') {
        state = 'preview';
      } else if (expectedDelay === 'unknown') {
        state = 'uncertain';
      }
    }
  } else if (incidentMain === 'cancellation') {
    if (notice === '14_plus_days') {
      state = 'not_eligible';
    } else if (notice === '7_to_13_days' || notice === 'under_7_days' || notice === 'airport_on_day_of_departure') {
      if (reroute === 'no') {
        state = 'eligible';
      } else if (reroute === 'yes') {
        if (depWindow === 'within_window' && arrWindow === 'within_window') {
          state = 'not_eligible';
        } else if (
          depWindow !== ''
          && arrWindow !== ''
          && depWindow !== 'unknown'
          && arrWindow !== 'unknown'
        ) {
          state = 'eligible';
        } else {
          state = 'uncertain';
        }
      } else {
        state = 'uncertain';
      }
    } else if (notice === 'unknown') {
      state = 'uncertain';
    }
  } else if (incidentMain === 'denied_boarding') {
    if (voluntaryDenied === 'yes') {
      state = 'not_eligible';
    } else if (voluntaryDenied === 'no') {
      state = 'eligible';
    }
  }

  if (state === 'eligible' && hasKnownDistance) {
    amountEl.textContent = formatAirEstimateAmount(potentialTotal);
    statusEl.textContent = 'Kompensation mulig';
    summaryEl.textContent = 'Foreloebigt kompensationsestimat' + peopleSuffix;
    return;
  }

  if (state === 'not_eligible') {
    amountEl.textContent = formatAirEstimateAmount(0);
    statusEl.textContent = 'Ingen kompensation';
    summaryEl.textContent = 'Kompensationsretten er foreloebigt afvist ud fra de nuvaerende svar.';
    return;
  }

  if (state === 'inactive') {
    amountEl.textContent = formatAirEstimateAmount(0);
    statusEl.textContent = 'Ikke aktiveret endnu';
    summaryEl.textContent = 'Den meldte forsinkelse ligger under den aktuelle threshold. Kompensationssporet er derfor ikke aktiveret endnu.';
    return;
  }

  if (state === 'uncertain') {
    amountEl.textContent = hasKnownDistance ? formatAirEstimateAmount(potentialTotal) : 'Afventer';
    statusEl.textContent = 'Kompensation usikker';
    summaryEl.textContent = 'Kompensationsretten er foreloebigt usikker og kraever flere svar om varsel, ombooking eller extraordinary circumstances.';
    return;
  }

  amountEl.textContent = hasKnownDistance ? formatAirEstimateAmount(potentialTotal) : 'Afventer';
  statusEl.textContent = hasKnownDistance ? 'Foreloebigt kompensationsniveau' : 'Afventer flere svar';
  summaryEl.textContent = hasKnownDistance
    ? ('Foreloebigt beloeb ud fra distancekategori' + (passengerCount > 1 ? ' for ' + passengerCount + ' passagerer' : '') + '. Det endelige krav afhaenger af haendelsen og de oevrige svar.')
    : 'Kompensationsbeloebet bliver vist, saa snart distancekategori eller gate er klare.';
}

function updateFerryResolverStatus() {
  var status = document.getElementById('ferryResolverStatus');
  if (!status) return;

  var art17El = document.getElementById('ferryResolverArt17');
  var art18El = document.getElementById('ferryResolverArt18');
  var regulationApplies = status.dataset.regulationApplies === '1';
  var art18Supported = status.dataset.art18Supported !== '0';
  var departureFromTerminal = status.dataset.departureFromTerminal === '1';
  var seasonTicketInput = document.querySelector('input[name="season_ticket"]');
  var seasonTicket = seasonTicketInput ? seasonTicketInput.value === 'yes' : false;
  var incidentMain = getFerryIncidentMain();
  var cancellationConfirmed = getRadioValue('ferry_cancellation_confirmed');
  var expectedDelayAnswer = getVal('expected_departure_delay_90');
  var actualDelayAnswer = getVal('actual_departure_delay_90');
  var combined90Answer = getRadioValue('ferry_departure_disruption_90');
  var expectedDelay90 = expectedDelayAnswer === 'yes';
  var actualDelay90 = actualDelayAnswer === 'yes';
  var informedBeforePurchase = getRadioValue('informed_before_purchase') === 'yes';
  var openTicketWithoutDepartureTime = getRadioValue('open_ticket_without_departure_time') === 'yes';
  var hasDepartureDisruption = false;
  if (incidentMain === 'cancellation') {
    hasDepartureDisruption = cancellationConfirmed === 'yes';
  } else if (incidentMain === 'delay') {
    if (combined90Answer === 'yes') {
      hasDepartureDisruption = true;
    } else if (combined90Answer === 'no') {
      hasDepartureDisruption = false;
    } else if (expectedDelayAnswer === 'yes' || actualDelayAnswer === 'yes') {
      hasDepartureDisruption = true;
    } else if (expectedDelayAnswer === 'no' && actualDelayAnswer === 'no') {
      hasDepartureDisruption = false;
    }
  }
  var art17Active = regulationApplies && hasDepartureDisruption && departureFromTerminal && !openTicketWithoutDepartureTime && !informedBeforePurchase;
  var art18Active = regulationApplies && art18Supported && hasDepartureDisruption && (!openTicketWithoutDepartureTime || seasonTicket);

  if (art17El) {
    art17El.textContent = 'Art. 17: ' + (art17Active ? 'Ja' : 'Nej');
  }
  if (art18El) {
    art18El.textContent = 'Art. 18: ' + (art18Active ? 'Ja' : 'Nej');
  }
}

function ferryLiveIntValue(name) {
  var raw = getVal(name);
  if (raw === '') return null;
  var num = parseInt(raw, 10);
  return isNaN(num) ? null : Math.max(0, num);
}

function ferryThresholdForDuration(minutes) {
  if (minutes === null || minutes <= 0) return null;
  if (minutes <= 240) return 60;
  if (minutes < 480) return 120;
  if (minutes < 1440) return 180;
  return 360;
}

function ferrySetLiveText(selector, value) {
  var el = document.querySelector(selector);
  if (el) el.textContent = value;
}

function setFirstNamedValue(name, value) {
  var inputs = document.querySelectorAll('input[name="' + name + '"]');
  if (!inputs.length) return;
  inputs[0].value = value == null ? '' : String(value);
}

function getFerryIncidentMain() {
  return getRadioValue('ferry_incident_main') || getVal('incident_main');
}

function ferryCorrectionInputValue(id) {
  var el = document.getElementById(id);
  return el ? (el.value || '') : '';
}

function syncFerryOperationalCorrections() {
  setFirstNamedValue('incident_main', getFerryIncidentMain());

  var combined90 = getRadioValue('ferry_departure_disruption_90');
  var incidentMain = getFerryIncidentMain();
  var cancellationConfirmed = getRadioValue('ferry_cancellation_confirmed');
  if (incidentMain === 'cancellation') {
    if (cancellationConfirmed === 'yes') {
      setFirstNamedValue('expected_departure_delay_90', 'yes');
      setFirstNamedValue('actual_departure_delay_90', '');
    } else if (cancellationConfirmed === 'no') {
      setFirstNamedValue('expected_departure_delay_90', 'no');
      setFirstNamedValue('actual_departure_delay_90', 'no');
    } else {
      setFirstNamedValue('expected_departure_delay_90', '');
      setFirstNamedValue('actual_departure_delay_90', '');
    }
  } else if (combined90 === 'yes') {
    setFirstNamedValue('expected_departure_delay_90', 'yes');
  } else if (combined90 === 'no') {
    setFirstNamedValue('expected_departure_delay_90', 'no');
    setFirstNamedValue('actual_departure_delay_90', 'no');
  }

  var expectedOverride = getRadioValue('expected_departure_delay_90_override');
  if (expectedOverride === 'yes' || expectedOverride === 'no') {
    setFirstNamedValue('expected_departure_delay_90', expectedOverride);
  }
  var actualOverride = getRadioValue('actual_departure_delay_90_override');
  if (actualOverride === 'yes' || actualOverride === 'no') {
    setFirstNamedValue('actual_departure_delay_90', actualOverride);
  }

  ['scheduled_journey_duration_minutes', 'delay_minutes_departure', 'arrival_delay_minutes'].forEach(function(name) {
    var id = name === 'scheduled_journey_duration_minutes'
      ? 'ferryScheduledDurationMinutes'
      : (name === 'delay_minutes_departure' ? 'ferryDepartureDelayMinutes' : 'ferryArrivalDelayMinutes');
    var value = ferryCorrectionInputValue(id);
    if (value !== '') {
      setFirstNamedValue(name, value);
    }
  });

  var systemDurationText = getFerryIncidentMain() === 'cancellation'
    ? 'Aflysning valgt'
    : (ferryLiveIntValue('scheduled_journey_duration_minutes') !== null ? ferryLiveIntValue('scheduled_journey_duration_minutes') + ' min' : 'Afventer');
  ferrySetLiveText('[data-ferry-system-duration]', systemDurationText);
  ferrySetLiveText('[data-ferry-system-departure-delay]', ferryLiveIntValue('delay_minutes_departure') !== null ? ferryLiveIntValue('delay_minutes_departure') + ' min' : 'Afventer');
  ferrySetLiveText('[data-ferry-system-arrival-delay]', ferryLiveIntValue('arrival_delay_minutes') !== null ? ferryLiveIntValue('arrival_delay_minutes') + ' min' : 'Afventer');
}

function updateFerryLiveEstimatePreview() {
  var panel = document.getElementById('ferryLiveEstimate');
  if (!panel) return;

  var incidentMain = getFerryIncidentMain();
  var cancellationConfirmed = getRadioValue('ferry_cancellation_confirmed');
  var expectedDelayAnswer = getVal('expected_departure_delay_90');
  var actualDelayAnswer = getVal('actual_departure_delay_90');
  var combined90Answer = getRadioValue('ferry_departure_disruption_90');
  var expectedDelay90 = getVal('expected_departure_delay_90') === 'yes';
  var actualDelay90 = getVal('actual_departure_delay_90') === 'yes';
  var informedBeforePurchase = getRadioValue('informed_before_purchase') === 'yes';
  var openTicketWithoutDepartureTime = getRadioValue('open_ticket_without_departure_time') === 'yes';
  var seasonTicketInput = document.querySelector('input[name="season_ticket"]');
  var seasonTicket = seasonTicketInput ? seasonTicketInput.value === 'yes' : false;
  var weatherSafety = getRadioValue('weather_safety') === 'yes';
  var extraordinary = getRadioValue('extraordinary_circumstances') === 'yes';
  var pmrUser = getRadioValue('pmr_user') === 'yes';
  var pmrBoardingRefused = getRadioValue('ferry_pmr_boarding_refused') === 'yes';
  var status = document.getElementById('ferryResolverStatus');
  var regulationApplies = !status || status.dataset.regulationApplies === '1';
  var art18Supported = !status || status.dataset.art18Supported !== '0';
  var departureFromTerminal = !status || status.dataset.departureFromTerminal === '1';
  var duration = ferryLiveIntValue('scheduled_journey_duration_minutes');
  var departureDelay = ferryLiveIntValue('delay_minutes_departure');
  var arrivalDelay = ferryLiveIntValue('arrival_delay_minutes');
  var threshold = ferryThresholdForDuration(duration);
  var ticketPrice = parseFloat(panel.dataset.ticketPrice || '0');
  var currency = panel.dataset.currency || 'EUR';
  var hasDepartureDisruption = false;
  if (incidentMain === 'cancellation') {
    hasDepartureDisruption = cancellationConfirmed === 'yes';
  } else if (incidentMain === 'delay') {
    if (combined90Answer === 'yes') {
      hasDepartureDisruption = true;
    } else if (combined90Answer === 'no') {
      hasDepartureDisruption = false;
    } else if (expectedDelayAnswer === 'yes' || actualDelayAnswer === 'yes') {
      hasDepartureDisruption = true;
    } else if (expectedDelayAnswer === 'no' && actualDelayAnswer === 'no') {
      hasDepartureDisruption = false;
    } else {
      hasDepartureDisruption = departureDelay !== null && departureDelay >= 90;
    }
  }
  var openTicketBlocksTimedRights = openTicketWithoutDepartureTime && !seasonTicket;
  var art17Active = regulationApplies && hasDepartureDisruption && departureFromTerminal && !openTicketWithoutDepartureTime && !informedBeforePurchase;
  var art18Active = regulationApplies && art18Supported && hasDepartureDisruption && !openTicketBlocksTimedRights;
  var art19Active = regulationApplies && threshold !== null && arrivalDelay !== null && arrivalDelay >= threshold && !informedBeforePurchase && !openTicketBlocksTimedRights && !weatherSafety && !extraordinary;
  var pmrRemedyActive = regulationApplies && pmrUser && pmrBoardingRefused;
  var bandPct = art19Active ? (arrivalDelay >= threshold * 2 ? 50 : 25) : 0;

  ferrySetLiveText('[data-ferry-live-duration]', duration !== null ? (Math.floor(duration / 60) + 't ' + (duration % 60) + 'm') : 'Afventer');
  ferrySetLiveText('[data-ferry-live-threshold]', threshold !== null ? (threshold + ' min') : 'Afventer');
  ferrySetLiveText('[data-ferry-live-arrival-delay]', arrivalDelay !== null ? ((arrivalDelay > 0 ? '+' : '') + arrivalDelay + ' min') : 'Afventer');
  ferrySetLiveText('[data-ferry-live-departure-delay]', departureDelay !== null ? ((departureDelay > 0 ? '+' : '') + departureDelay + ' min') : (actualDelay90 ? '+90 min' : 'Afventer'));
  ferrySetLiveText('[data-ferry-live-art17]', art17Active ? (weatherSafety ? 'Refreshments aktiv' : 'Refreshments + hotel') : 'Ikke aktiv endnu');
  ferrySetLiveText('[data-ferry-live-art18]', art18Active ? 'Aktiv' : 'Ikke aktiv endnu');
  ferrySetLiveText('[data-ferry-live-pmr]', pmrRemedyActive ? 'Aktiv ved nægtet indskibning' : 'Ikke aktiv');
  ferrySetLiveText('[data-ferry-live-art19]', bandPct > 0 ? (bandPct + '%') : 'Ikke aktiv endnu');
  ferrySetLiveText('[data-ferry-live-status]', art19Active ? ('Art. 19 aktiv · ' + bandPct + '%') : (threshold !== null && arrivalDelay !== null ? 'Under kompensationstærskel' : 'Afventer forsinkelse/sejltid'));

  if (bandPct > 0 && ticketPrice > 0) {
    ferrySetLiveText('[data-ferry-live-amount]', (ticketPrice * bandPct / 100).toFixed(2) + ' ' + currency);
  } else if (bandPct > 0) {
    ferrySetLiveText('[data-ferry-live-amount]', bandPct + '% af billetpris');
  } else {
    ferrySetLiveText('[data-ferry-live-amount]', 'Afventer');
  }
}

function updateRailLiveEstimatePreview() {
  var panel = document.getElementById('railLiveEstimate');
  if (!panel) return;

  var seedArt18 = panel.dataset.seedArt18 === '1';
  var seedArt19 = panel.dataset.seedArt19 === '1';
  var seedArt20 = panel.dataset.seedArt20 === '1';
  var seedArrivalDelay = panel.dataset.seedArrivalDelay === '' ? null : parseInt(panel.dataset.seedArrivalDelay, 10);
  var seedDepartureDelay = panel.dataset.seedDepartureDelay === '' ? null : parseInt(panel.dataset.seedDepartureDelay, 10);
  var seedIncidentType = String(panel.dataset.seedIncidentType || 'unknown');

  var main = getRadioValue('incident_main');
  var expected60 = getRadioValue('expected_delay_60');
  var already60 = getRadioValue('delay_already_60');
  var missed = getVal('incident_missed') === 'yes';
  var missed60 = getRadioValue('missed_expected_delay_60');
  var delayGateResolvedToNo = (main === 'delay') && expected60 === 'no' && already60 === 'no';
  var missedCanDriveGate = delayGateResolvedToNo && missed && missed60 === 'yes';
  var extraordinary = getRadioValue('operatorExceptionalCircumstances') === 'yes';
  var stranding = getRadioValue('rail_stranding_context') || getVal('rail_stranding_context') || 'no';

  var incidentLabel = 'Afventer';
  if (main === 'delay') incidentLabel = 'Forsinkelse';
  if (main === 'cancellation') incidentLabel = 'Aflysning';
  if (missedCanDriveGate) incidentLabel = 'Mistet forbindelse';
  if (incidentLabel === 'Afventer') {
    if (seedIncidentType === 'delay') incidentLabel = 'Forsinkelse';
    else if (seedIncidentType === 'cancellation') incidentLabel = 'Aflysning';
    else if (seedIncidentType === 'missed_connection') incidentLabel = 'Mistet forbindelse';
    else if (seedIncidentType === 'partial_cancellation') incidentLabel = 'Delvis aflysning';
    else if (seedIncidentType === 'replacement_transport') incidentLabel = 'Erstatningstransport';
  }

  var art18Active = false;
  var art20Active = false;
  var art19Active = false;
  var userHasMainConfirmation = false;

  if (main === 'cancellation') {
    art18Active = true;
    art20Active = true;
    userHasMainConfirmation = true;
  } else if (main === 'delay') {
    if (expected60 === 'yes' || already60 === 'yes') {
      art18Active = true;
      art20Active = true;
      userHasMainConfirmation = true;
    } else if (expected60 === 'no' && already60 === 'no') {
      art18Active = false;
      art20Active = false;
      userHasMainConfirmation = true;
    }
  }

  if (missedCanDriveGate) {
    art18Active = true;
    art20Active = true;
    art19Active = true;
  }

  if (already60 === 'yes') {
    art19Active = true;
  }

  if (!userHasMainConfirmation && !art18Active && seedArt18) {
    art18Active = true;
  }
  if (!userHasMainConfirmation && !art20Active && seedArt20) {
    art20Active = true;
  }
  if (!art19Active && seedArt19) {
    art19Active = true;
  }

  var arrivalDelay = seedArrivalDelay;
  if (already60 === 'yes' && (arrivalDelay === null || arrivalDelay < 60)) {
    arrivalDelay = 60;
  }
  if (missedCanDriveGate && (arrivalDelay === null || arrivalDelay < 60)) {
    arrivalDelay = 60;
  }

  var art19Label = 'Ikke aktiv endnu';
  var panelTicketPrice = panel.dataset.ticketPrice === '' ? 0 : parseFloat(panel.dataset.ticketPrice);
  var panelCurrency = String(panel.dataset.currency || 'EUR');
  var panelPriceKnown = panel.dataset.priceKnown === '1';
  var panelPriceEstimate = panel.dataset.priceEstimate === '1';
  var amountText = 'Afventer';
  var thresholdText = 'Afventer';
  var bandPct = (arrivalDelay !== null && arrivalDelay >= 120) ? 50 : 25;
  var statusText = 'Afventer rail-bekræftelse';
  statusText = 'Afventer flere svar';
  if (art19Active && extraordinary) {
    art19Label = 'Blokeret af force majeure';
    statusText = 'Kompensation blokeret';
    amountText = 'Blokeret';
  } else if (art19Active) {
    art19Label = bandPct + '% af billetpris';
    statusText = 'Foreloebigt kompensationsniveau';
    amountText = (panelPriceKnown && panelTicketPrice > 0)
      ? ((panelTicketPrice * bandPct / 100).toFixed(2) + ' ' + panelCurrency)
      : (bandPct + '% af billetpris');
  } else if (art18Active || art20Active) {
    statusText = 'Omlaegning / assistance aktiv';
  }

  if (main === 'cancellation') {
    thresholdText = 'Ikke relevant ved aflysning';
  } else if (art19Active || expected60 === 'yes' || already60 === 'yes' || missedCanDriveGate) {
    thresholdText = 'Aktiveret';
  } else if (expected60 === 'no' && already60 === 'no' && !missedCanDriveGate) {
    thresholdText = 'Under 60 min';
  }

  ferrySetLiveText('[data-rail-live-status]', statusText);
  ferrySetLiveText('[data-rail-live-amount]', amountText);
  ferrySetLiveText('[data-rail-live-incident]', incidentLabel);
  ferrySetLiveText('[data-rail-live-art18]', art18Active ? 'Aktiv' : 'Ikke aktiv endnu');
  ferrySetLiveText('[data-rail-live-art19]', art19Label);
  ferrySetLiveText('[data-rail-live-threshold]', thresholdText);
  ferrySetLiveText('[data-rail-live-art20]', art20Active ? 'Aktiv' : 'Ikke aktiv endnu');
  ferrySetLiveText('[data-rail-live-arrival-delay]', arrivalDelay !== null ? ((arrivalDelay > 0 ? '+' : '') + arrivalDelay + ' min') : 'Afventer');
  ferrySetLiveText('[data-rail-live-departure-delay]', seedDepartureDelay !== null ? ((seedDepartureDelay > 0 ? '+' : '') + seedDepartureDelay + ' min') : 'Afventer');

  var strandingLabel = 'Ikke strandet';
  if (stranding === 'station') strandingLabel = 'Strandet på station';
  if (stranding === 'track') strandingLabel = 'Strandet i tog / på spor';
  ferrySetLiveText('[data-rail-live-stranding]', strandingLabel);

  var summary = '';
  if (art19Active && extraordinary) {
    summary = 'Rail-panelet viser, at kompensation foreløbigt blokeres af force majeure, selv om delay-seed eller brugerbekræftelse ellers ville aktivere Art. 19.';
  } else if (art19Active) {
    summary = 'Foreløbig rail-vurdering peger på kompensation ved ankomstforsinkelse på mindst 60 minutter. Brugeren skal stadig bekræfte det endelige faktum.';
  } else if (art18Active || art20Active) {
    summary = 'Rail-panelet peger på refund/ombooking eller assistance, men endelig rettighed afhænger stadig af de konkrete brugerbekræftelser.';
  } else {
    summary = 'Rail-panelet afventer stadig rail-spørgsmålene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.';
  }
  if (art19Active && extraordinary) {
    summary = 'Kompensationssporet er foreloebigt blokeret af force majeure.';
  } else if (art19Active) {
    summary = (panelPriceKnown && panelTicketPrice > 0)
      ? ('Foreloebigt Art. 19-estimat ud fra billetpris og ankomstforsinkelse.' + (panelPriceEstimate ? ' Beloebet bygger paa et ca. estimat fra TRIN 2.' : ''))
      : 'Kompensationen ser mulig ud, men billetpris mangler endnu. Registrer prisen i TRIN 2 eller bekraeft den senere i backend.';
  } else if (art18Active || art20Active) {
    summary = 'Kompensationen afventer stadig 60+ minutter, men assistance eller omlaegning / refund kan allerede vaere relevante.';
  } else {
    summary = 'Rail-panelet afventer stadig rail-spoergsmaalene om 60+ minutters forsinkelse, aflysning eller mistet forbindelse.';
  }
  ferrySetLiveText('[data-rail-live-note]', summary);
}

function updateAirCancellationState() {
  var main = getRadioValue('incident_main');
  var reroute = getRadioValue('reroute_offered');
  var notice = getRadioValue('cancellation_notice_band');
  var isAirShortView = <?= json_encode($isAirShortView) ?>;
  var isCompletedJourney = <?= json_encode($isCompleted) ?>;
  var isCancellation = main === 'cancellation';
  var showRerouteCard = isCancellation && (notice === '7_to_13_days' || notice === 'under_7_days' || notice === 'airport_on_day_of_departure');
  var rerouteUsed = getRadioValue('reroute_used_or_accepted');
  var depWindow = getRadioValue('reroute_departure_band');
  var arrWindow = getRadioValue('reroute_arrival_band');
  var ownsArt5DetailQuestions = !!document.getElementById('airCancellationDetailQuestions');
  var ownsReductionCard = !!document.getElementById('airCancellationReductionCard');
  var frontendOwnsCancellationDetails = ownsArt5DetailQuestions || ownsReductionCard;
  var showReductionCard = showRerouteCard && reroute === 'yes';
  var reductionRelevant = true;

  if (notice === '14_plus_days') {
    reductionRelevant = false;
  } else if (reroute === 'yes' && ownsArt5DetailQuestions) {
    if (depWindow === 'within_window' && arrWindow === 'within_window') {
      reductionRelevant = false;
    } else if (
      depWindow === ''
      || arrWindow === ''
      || depWindow === 'unknown'
      || arrWindow === 'unknown'
    ) {
      reductionRelevant = false;
    }
  }

  setBlockVisible(document.getElementById('airCancellationNoticeCard'), isCancellation);
  setBlockVisible(document.getElementById('airCancellationNotice14Card'), isCancellation && notice === '14_plus_days');
  setBlockVisible(document.getElementById('airCancellationRerouteCard'), showRerouteCard);
  setBlockVisible(document.getElementById('airCancellationSelfArrangeNote'), showRerouteCard && reroute === 'no');
  setBlockVisible(document.getElementById('airCancellationRerouteWindows'), ownsArt5DetailQuestions && showRerouteCard && reroute === 'yes');
  setBlockVisible(document.getElementById('airCancellationReductionCard'), ownsReductionCard && showReductionCard && reductionRelevant);
  setBlockVisible(document.getElementById('airCancellationReductionNotRelevantCard'), ownsReductionCard && showReductionCard && !reductionRelevant);

  var depQuestion = document.getElementById('airCancellationDepartureQuestion');
  var arrQuestion = document.getElementById('airCancellationArrivalQuestion');
  var help = document.getElementById('airCancellationRerouteHelp');

  if (isAirShortView) {
    if (help) help.textContent = isCompletedJourney
      ? 'For afsluttet flight afklarer vi her, om flyselskabet tilbloed en alternativ flyvning. De mere detaljerede Article 5- og 7(2)-spoergsmaal flyttes til sagen bagefter.'
      : 'For igangvaerende flight afklarer vi her foerst, om flyselskabet tilbloed en alternativ flyvning. De mere detaljerede Article 5- og 7(2)-spoergsmaal flyttes til sagen bagefter.';
  } else if (notice === 'under_7_days' || notice === 'airport_on_day_of_departure') {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 1 time foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 2 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = notice === 'airport_on_day_of_departure'
      ? 'Hvis aflysningen foerst blev oplyst i lufthavnen eller ved afgang, behandles den som under 7 dage: 1 time foer afgang og 2 timer efter planlagt ankomst.'
      : 'Ved under 7 dages varsel bortfalder kompensation kun, hvis ombookningen holdt sig inden for 1 time foer afgang og 2 timer efter planlagt ankomst.';
  } else if (notice === '7_to_13_days') {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = 'Ved 7-13 dages varsel bortfalder kompensation kun, hvis ombookningen holdt sig inden for 2 timer foer afgang og 4 timer efter planlagt ankomst.';
  } else {
    if (depQuestion) depQuestion.textContent = 'Kunne du afrejse hoejst 2 timer foer det planlagte afgangstidspunkt?';
    if (arrQuestion) arrQuestion.textContent = 'Kunne du ankomme senest 4 timer efter det planlagte ankomsttidspunkt?';
    if (help) help.textContent = 'Kun relevant hvis du fik besked om aflysningen mindre end 14 dage foer. Registrer foerst om der blev tilbudt ombooking og derefter, om den holdt de juridiske tidsvinduer.';
  }
  if (!frontendOwnsCancellationDetails) {
    return;
  }
  if (!showReductionCard || !reductionRelevant) {
    clearRadios('reroute_used_or_accepted');
    clearFields(['reroute_arrival_delay_minutes']);
  } else if (rerouteUsed !== 'yes') {
    clearFields(['reroute_arrival_delay_minutes']);
  }
}

function updateBusIncidentState() {
  var busDurationCard = document.getElementById('busPlannedDurationCard');
  var busDelayTimerCard = document.getElementById('busDelayTimerCard');
  if (!busDurationCard && !busDelayTimerCard) return;

  var main = getRadioValue('incident_main');
  var delayBand = getRadioValue('delay_departure_band');
  var showBusDuration = (main === 'cancellation') || (main === 'delay' && (delayBand === '90_119' || delayBand === '120_plus'));
  setBlockVisible(busDurationCard, showBusDuration);
  setBlockVisible(busDelayTimerCard, main === 'delay');
  updateBusDelayTimerUi();
}

var __busDelayTimerInterval = null;
var __busDelayTimerBaseMinutes = 0;
var __busDelayTimerStartedAt = null;

function busDelayBandFromMinutes(mins) {
  if (mins >= 120) return '120_plus';
  if (mins >= 90) return '90_119';
  return 'under_90';
}

function busDelayMinutesFromBand(band) {
  if (band === '120_plus') return 120;
  if (band === '90_119') return 90;
  return 0;
}

function setRadioValue(name, value) {
  var target = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
  if (!target) return;
  if (!target.checked) {
    target.checked = true;
  }
}

function setInputValue(name, value) {
  var target = document.querySelector('input[name="' + name + '"]');
  if (!target) return;
  target.value = value;
}

function airExpectedDelayBucketFromLegacyBand(band) {
  if (band === 'five_plus') return 'five_plus';
  if (band === 'threshold_to_under_5h') return 'threshold_to_under_5h';
  if (band === 'under_threshold') return 'under_threshold';
  return '';
}

function airActualArrivalBucketFromMinutes(mins) {
  mins = parseInt(mins, 10);
  if (isNaN(mins)) return '';
  if (mins >= 240) return 'four_plus';
  if (mins >= 180) return 'three_to_four';
  return 'under_3h';
}

function getBusDelayLiveMinutes() {
  if (__busDelayTimerStartedAt === null) {
    return __busDelayTimerBaseMinutes;
  }
  var elapsedMs = Date.now() - __busDelayTimerStartedAt;
  return __busDelayTimerBaseMinutes + Math.max(0, Math.floor(elapsedMs / 60000));
}

function syncBusDelayMinutes(mins, autoBand) {
  mins = parseInt(mins, 10);
  if (isNaN(mins) || mins < 0) mins = 0;

  __busDelayTimerBaseMinutes = mins;
  if (__busDelayTimerStartedAt !== null) {
    __busDelayTimerStartedAt = Date.now();
  }

  var hidden = document.querySelector('input[name="delay_minutes_departure"]');
  if (hidden) hidden.value = String(mins);

  var input = document.getElementById('busDelayLiveMinutesInput');
  if (input && String(input.value || '') !== String(mins)) {
    input.value = String(mins);
  }

  if (autoBand && getRadioValue('incident_main') === 'delay') {
    setRadioValue('delay_departure_band', busDelayBandFromMinutes(mins));
  }

  updateBusDelayTimerUi();
  updateBusIncidentState();
}

function stopBusDelayTicker() {
  if (__busDelayTimerInterval !== null) {
    window.clearInterval(__busDelayTimerInterval);
    __busDelayTimerInterval = null;
  }
}

function ensureBusDelayTicker() {
  if (__busDelayTimerInterval !== null) return;
  __busDelayTimerInterval = window.setInterval(function(){
    updateBusDelayTimerUi();
  }, 1000);
}

function updateBusDelayTimerUi() {
  var card = document.getElementById('busDelayTimerCard');
  if (!card) return;

  var main = getRadioValue('incident_main');
  var visible = main === 'delay';
  setBlockVisible(card, visible);
  if (!visible) return;

  var mins = getBusDelayLiveMinutes();
  var derivedBand = busDelayBandFromMinutes(mins);
  var durationBand = getRadioValue('planned_duration_band');
  var currentDelayBand = getRadioValue('delay_departure_band');
  if (derivedBand !== currentDelayBand) {
    setRadioValue('delay_departure_band', derivedBand);
    currentDelayBand = derivedBand;
  }

  var durationCard = document.getElementById('busPlannedDurationCard');
  if (durationCard) {
    var showBusDuration = (main === 'cancellation') || (main === 'delay' && (currentDelayBand === '90_119' || currentDelayBand === '120_plus'));
    setBlockVisible(durationCard, showBusDuration);
  }

  var display = document.getElementById('busDelayLiveMinutes');
  var badge = document.getElementById('busDelayThresholdBadge');
  var message = document.getElementById('busDelayThresholdMessage');
  var nextHint = document.getElementById('busDelayNextHint');
  var status = document.getElementById('busDelayTimerStatus');

  if (display) display.textContent = String(mins) + ' min';
  if (status) status.textContent = (__busDelayTimerStartedAt !== null) ? 'Timeren koerer.' : 'Timeren er pauset.';

  if (badge) {
    badge.className = 'bus-live-badge';
    if (mins >= 120) {
      badge.classList.add('is-120');
      badge.textContent = '120+ min';
    } else if (mins >= 90) {
      badge.classList.add('is-90');
      badge.textContent = '90+ min';
    } else {
      badge.textContent = 'Under 90 min';
    }
  }

  if (message) {
    if (mins >= 120) {
      message.textContent = 'Art. 19-threshold naet: refund eller omlaegning kan vaere relevant nu.';
    } else if (mins >= 90) {
      if (durationBand === 'over_3h') {
        message.textContent = 'Art. 21-threshold naet: assistance kan vaere relevant, fordi rejsen er over 3 timer.';
      } else if (durationBand === 'up_to_3h') {
        message.textContent = '90 min er naet, men Art. 21 assistance kraever ogsaa en planlagt rejse over 3 timer.';
      } else {
        message.textContent = '90 min er naet. Vurder nu om den planlagte rejse var over 3 timer for Art. 21.';
      }
    } else {
      message.textContent = 'Naeste threshold er 90 min, hvor assistance kan blive relevant ved rejser over 3 timer.';
    }
  }

  if (nextHint) {
    if (mins >= 120) {
      nextHint.textContent = (durationBand === 'over_3h')
        ? 'Baade assistance og refund/omlaegning er nu inden for bus-thresholds.'
        : 'Refund eller omlaegning er inden for threshold; assistance afhaenger fortsat af rejse over 3 timer.';
    } else if (mins >= 90) {
      nextHint.textContent = 'Naeste threshold er 120 min, hvor refund eller omlaegning kan blive relevant.';
    } else {
      nextHint.textContent = 'Refund eller omlaegning bliver forst relevant ved 120+ min.';
    }
  }
}

function setupBusDelayTimer() {
  var card = document.getElementById('busDelayTimerCard');
  if (!card) return;

  var hidden = document.querySelector('input[name="delay_minutes_departure"]');
  var input = document.getElementById('busDelayLiveMinutesInput');
  var initial = 0;

  if (input && input.value !== '') {
    initial = parseInt(input.value, 10);
  } else if (hidden && hidden.value !== '') {
    initial = parseInt(hidden.value, 10);
  } else {
    var presetBand = getRadioValue('delay_departure_band');
    if (presetBand === '90_119') initial = 90;
    if (presetBand === '120_plus') initial = 120;
  }
  if (isNaN(initial) || initial < 0) initial = 0;
  __busDelayTimerBaseMinutes = initial;

  if (input) {
    input.addEventListener('input', function() {
      syncBusDelayMinutes(input.value, true);
    });
    input.addEventListener('change', function() {
      syncBusDelayMinutes(input.value, true);
    });
  }

  var delayBandInputs = document.querySelectorAll('input[name="delay_departure_band"]');
  delayBandInputs.forEach(function(radio) {
    radio.addEventListener('change', function() {
      if (!radio.checked || getRadioValue('incident_main') !== 'delay') return;
      var nextMinutes = busDelayMinutesFromBand(radio.value);
      if (__busDelayTimerStartedAt !== null) {
        __busDelayTimerStartedAt = Date.now();
      }
      syncBusDelayMinutes(nextMinutes, false);
    });
  });

  var start = document.getElementById('busDelayTimerStart');
  if (start) {
    start.addEventListener('click', function() {
      if (__busDelayTimerStartedAt === null) {
        __busDelayTimerStartedAt = Date.now();
      }
      ensureBusDelayTicker();
      updateBusDelayTimerUi();
    });
  }

  var pause = document.getElementById('busDelayTimerPause');
  if (pause) {
    pause.addEventListener('click', function() {
      if (__busDelayTimerStartedAt !== null) {
        __busDelayTimerBaseMinutes = getBusDelayLiveMinutes();
        __busDelayTimerStartedAt = null;
        stopBusDelayTicker();
        syncBusDelayMinutes(__busDelayTimerBaseMinutes, true);
      }
    });
  }

  var reset = document.getElementById('busDelayTimerReset');
  if (reset) {
    reset.addEventListener('click', function() {
      __busDelayTimerStartedAt = null;
      stopBusDelayTicker();
      syncBusDelayMinutes(0, true);
    });
  }
}

var __euReminderTimer = null;
function clearEuReminder(){
  if (__euReminderTimer) { try { window.clearTimeout(__euReminderTimer); } catch(e) {} }
  __euReminderTimer = null;
  showById('euReminderPrompt', false);
  var st = document.getElementById('euReminderStatus');
  if (st) st.textContent = '';
}

function updateStep4State(){
  var isOngoing = <?= json_encode((bool)$isOngoing) ?>;
  var euFlowSupported = <?= json_encode((bool)$euFlowSupported) ?>;
  var cutoff = <?= json_encode($nationalCutoff) ?>;
  var compBlockedByFM = <?= json_encode((bool)$compBlockedByFM) ?>;

  var main = getRadioValue('incident_main');
  var exp60 = getRadioValue('expected_delay_60');
  var already60 = getRadioValue('delay_already_60');
  var missed = getVal('incident_missed');
  var missed60 = getRadioValue('missed_expected_delay_60');

  // Live (client-side) force-majeure evaluation: if user says "yes", we hide national fallback entirely.
  // This avoids confusing the user with national bands when compensation is likely excluded anyway.
  var fm = getRadioValue('operatorExceptionalCircumstances');
  var fmTypeSel = document.querySelector('select[name="operatorExceptionalType"]');
  var fmType = fmTypeSel ? (fmTypeSel.value || '') : '';
  var fmBlocksComp = (fm === 'yes') && (fmType === '' || fmType !== 'own_staff_strike');

  // EU gate can be satisfied either by the main incident (delay>=60 or cancellation),
  // or (only when EU gate is not already satisfied) by missed-connection implying >=60 to final destination.
  var euGateFromMain = false;
  if (main === 'cancellation') euGateFromMain = true;
  if (main === 'delay' && (exp60 === 'yes' || already60 === 'yes')) euGateFromMain = true;

  // Missed-connection should only ask its own 60+ follow-up when the main delay branch
  // is explicitly below threshold (no/no). Cancellation already opens Art. 18/20 directly.
  var delayGateResolvedToNo = (main === 'delay') && exp60 === 'no' && already60 === 'no';
  var showMissed60 = (missed === 'yes') && delayGateResolvedToNo;
  document.querySelectorAll('.rail-missed60-wrap').forEach(function(el) {
    setBlockVisible(el, showMissed60);
  });
  var showDelayFollowupNote = (main === 'delay') && (missed === 'yes') && !showMissed60;
  document.querySelectorAll('.rail-missed60-delay-note').forEach(function(el) {
    setBlockVisible(el, showDelayFollowupNote);
  });
  var showCancellationContextNote = (main === 'cancellation') && (missed === 'yes');
  document.querySelectorAll('.rail-missed60-cancellation-note').forEach(function(el) {
    setBlockVisible(el, showCancellationContextNote);
  });

  var euGate = euGateFromMain;
  if (!euGateFromMain && missed === 'yes' && missed60 === 'yes') euGate = true;

  // National fallback is shown only when EU gate is NOT met, and only after the user has answered the
  // relevant "last chance" questions. Important: selecting missed-connection (incident_missed=yes)
  // must NOT be enough to trigger national fallback; the sub-question (missed_expected_delay_60) must be answered.
  var delayLastChanceAnswered = (main === 'delay') && (exp60 !== '') && (already60 !== '');
  var delayReadyForFallback = (!euGate) && (main === 'delay') && delayLastChanceAnswered;
  var missedReadyForFallback = (!euGate) && showMissed60 && (missed60 !== '');
  // If the user has enabled missed-connection, require the sub-question to be answered
  // before we show national fallback (even if delay-branch is already answered).
  var missedLastChanceUnanswered = showMissed60 && (missed60 === '');
  var showNat = (!euGate) && !missedLastChanceUnanswered && (delayReadyForFallback || missedReadyForFallback) && !fmBlocksComp;
  showById('nationalFallbackWrap', showNat);
  // We no longer keep the fallback visible-but-disabled. If the needed sub-question isn't answered yet,
  // we hide the fallback entirely (cleaner UX and avoids the "red box shows too early" issue).
  showById('nationalFallbackBlockedHint', false);
  var minsField = document.getElementById('nationalDelayMinutes');
  if (minsField) { minsField.disabled = false; }

  // Reminder UI: only useful in ongoing journeys when national fallback is visible.
  var mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;
  // Also show reminder when compensation is blocked by force majeure (Art. 19(10)) and EU gate isn't met yet.
  var canRemind = euFlowSupported && isOngoing && showNat && !isNaN(mins) && mins > 0 && mins < 60;
  if (!canRemind && euFlowSupported && isOngoing && compBlockedByFM && !euGate && !isNaN(mins) && mins > 0 && mins < 60) {
    canRemind = true;
  }
  showById('euReminderWrap', canRemind);
  var info = document.getElementById('euReminderInfo');
  if (info && canRemind) {
    var m2 = 60 - mins;
    info.textContent = ' (ca. ' + m2 + ' min til 60, hvis forsinkelsen ikke aendrer sig)';
  } else if (info) {
    info.textContent = '';
  }

  // If gating context changes, clear any running reminder.
  if (!canRemind) { clearEuReminder(); }

  // If the user already typed >=60 minutes in national fallback (ongoing journey),
  // prompt them to confirm EU gating (we still require an explicit click).
  if (isOngoing && showNat && !isNaN(mins) && mins >= 60) {
    showById('euReminderWrap', true);
    showById('euReminderPrompt', true);
    var status2 = document.getElementById('euReminderStatus');
    if (status2) status2.textContent = 'Du har angivet ' + mins + ' min. Bekraeft om du nu er >=60 min forsinket.';
  }

}

(function(){
  var minsField = document.getElementById('nationalDelayMinutes');
  var reportedAt = document.getElementById('nationalDelayReportedAt');
  if (minsField && reportedAt) {
    minsField.addEventListener('input', function(){
      // Stamp when user edits national minutes (client-only; server stores it as-is).
      reportedAt.value = String(Date.now());
      updateStep4State();
    }, { passive:true });
  }

  var btn = document.getElementById('startEuReminder');
  if (btn) {
    btn.addEventListener('click', function(){
      clearEuReminder();
      var mins = minsField ? parseInt(String(minsField.value || '').trim(), 10) : NaN;
      if (isNaN(mins) || mins <= 0 || mins >= 60) { return; }
      var m2 = 60 - mins;
      var status = document.getElementById('euReminderStatus');
      if (status) status.textContent = 'Reminder sat til ca. ' + m2 + ' min.';
      __euReminderTimer = window.setTimeout(function(){
        showById('euReminderPrompt', true);
      }, m2 * 60 * 1000);
    });
  }

  var yes = document.getElementById('euReminderYes');
  var no = document.getElementById('euReminderNo');
  if (yes) {
    yes.addEventListener('click', function(){
      // User confirmation required: set the EU gate question, do not auto-submit.
      var r = document.querySelector('input[name=\"delay_already_60\"][value=\"yes\"]');
      if (r) { r.checked = true; }
      showById('euReminderPrompt', false);
      updateReveal();
      updateStep4State();
    });
  }
  if (no) {
    no.addEventListener('click', function(){
      showById('euReminderPrompt', false);
      clearEuReminder();
    });
  }
})();
</script>
