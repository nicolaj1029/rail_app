<?php
/** @var \App\View\AppView $this */
/** @var array<string,mixed> $snapshot */
/** @var array<string,mixed> $caseData */
$ticketFiles = (array)($caseData['ticketFiles'] ?? []);
$ticketAnalysis = (array)($caseData['ticketAnalysis'] ?? []);
$refundReceiptFiles = (array)($caseData['refundReceiptFiles'] ?? []);
$careReceiptFiles = (array)($caseData['careReceiptFiles'] ?? []);
$refundExpenseItems = (array)($caseData['refundExpenseItems'] ?? []);
$careExpenseItems = (array)($caseData['careExpenseItems'] ?? []);
$incidentExpenseFlag = (string)($caseData['incidentExpenseFlag'] ?? '');
$backendExpenseFlag = (string)($caseData['backendExpenseFlag'] ?? '');
$steps = (array)($caseData['steps'] ?? []);
$activeStep = (string)($caseData['activeStep'] ?? 'details');
$progressCompleted = (int)($caseData['progressCompleted'] ?? 0);
$progressTotal = (int)($caseData['progressTotal'] ?? 0);
$progressPct = (int)($caseData['progressPct'] ?? 0);
$currencyOptions = (array)($caseData['currencyOptions'] ?? ['EUR', 'DKK', 'SEK', 'NOK', 'GBP', 'CHF', 'BGN', 'CZK', 'HUF', 'PLN', 'RON']);
$compFallbackReasons = (array)($caseData['compFallbackReasons'] ?? []);
$hasDetails = !empty($caseData['hasDetails']);
$supportSummary = (string)($caseData['supportSummary'] ?? 'Article 9-care, PMR/handicap og supplerende udgifter samles her.');
$operatorExceptionalTypeLabel = (string)($caseData['operatorExceptionalTypeLabel'] ?? '');
$incidentMain = (string)($caseData['incidentMain'] ?? '');
$isDeniedBoardingContext = !empty($caseData['isDeniedBoardingContext']);
$rerouteOffered = (string)($caseData['rerouteOffered'] ?? '');
$firstDepartureLabel = (string)($caseData['firstDepartureLabel'] ?? '');
$rerouteOriginLabel = (string)($caseData['rerouteOriginLabel'] ?? '');
$isDelayRefundContext = !empty($caseData['isDelayRefundContext']);
$isCancellationRefundContext = !empty($caseData['isCancellationRefundContext']);
$showCancellationCompensationDetails = !empty($caseData['showCancellationCompensationDetails']);
$showBackendArticle8OfferField = !empty($caseData['showBackendArticle8OfferField']);
$travelState = strtolower((string)($caseData['travelState'] ?? 'completed'));
$isOngoingCase = $travelState === 'ongoing';
$isCompletedCase = !$isOngoingCase;
$isOngoingDelayRefundContext = $isDelayRefundContext && $isOngoingCase;
$expectedDelayLabel = (string)($caseData['expectedDelayLabel'] ?? '');
$actualArrivalDelayLabel = (string)($caseData['actualArrivalDelayLabel'] ?? '');
$delayDepartureBandLabel = (string)($caseData['delayDepartureBandLabel'] ?? '');
$arrivalDelayMinutes = (string)($caseData['arrivalDelayMinutes'] ?? '');
$airDelayThresholdHours = (int)($caseData['airDelayThresholdHours'] ?? 0);
$sections = (array)($caseData['sections'] ?? []);
$article7EligibilityStatus = (string)($caseData['article7EligibilityStatus'] ?? '');
$article7ReductionStatus = (string)($caseData['article7ReductionStatus'] ?? '');
$article7BaseAmountEur = (int)($caseData['article7BaseAmountEur'] ?? 0);
$article7FinalAmountEur = (int)($caseData['article7FinalAmountEur'] ?? 0);
$extraordinaryScore = is_numeric($caseData['extraordinaryScore'] ?? null) ? (int)$caseData['extraordinaryScore'] : null;
$extraordinaryBand = (string)($caseData['extraordinaryBand'] ?? '');
$manualReviewRequired = !empty($caseData['manualReviewRequired']);
$showRefundStep = !empty($caseData['showRefundStep']);
$showSupportStep = !empty($caseData['showSupportStep']);
$isVoluntaryDeniedBoarding = !empty($caseData['isVoluntaryDeniedBoarding']);
$deniedBoardingSelfArrangedFacts = !empty($caseData['deniedBoardingSelfArrangedFacts']);
$showDeniedBoardingCompensationDetails = !empty($caseData['showDeniedBoardingCompensationDetails']);
$deniedBoardingReductionRelevant = !empty($caseData['deniedBoardingReductionRelevant']);
$cancellationArt7ReductionRelevant = $article7EligibilityStatus === 'eligible';
$formState = (array)($snapshot['form'] ?? []);
$metaState = (array)($snapshot['meta'] ?? []);
$flagsState = (array)($snapshot['flags'] ?? []);
$transportMode = strtolower(trim((string)($formState['transport_mode'] ?? '')));
$isAirCase = $transportMode === 'air';
$isFerryCase = $transportMode === 'ferry';
$isRailCase = $transportMode === 'rail';
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
$stepKeys = array_values(array_map(static fn(array $step): string => (string)($step['key'] ?? ''), $steps));
$activeIndex = array_search($activeStep, $stepKeys, true);
$prevStep = is_int($activeIndex) && $activeIndex > 0 ? (string)$stepKeys[$activeIndex - 1] : '';
$nextStep = is_int($activeIndex) && $activeIndex < count($stepKeys) - 1 ? (string)$stepKeys[$activeIndex + 1] : '';
$field = static fn(string $key, string $default = ''): string => trim((string)($formState[$key] ?? $default));
$selected = static fn(string $key, string $value): string => $field($key) === $value ? 'selected' : '';
$backendHasExpenses = $backendExpenseFlag === 'yes';
$caseRef = (string)($caseData['caseRef'] ?? ($metaState['air_case_ref'] ?? ($metaState['ferry_case_ref'] ?? ($metaState['rail_case_ref'] ?? ''))));
$caseId = (int)($caseData['caseId'] ?? ($metaState['air_case_id'] ?? ($metaState['ferry_case_id'] ?? ($metaState['rail_case_id'] ?? 0))));
$isAdminCaseView = !empty($caseData['isAdminCaseView']);
$adminDeskHref = $caseId > 0
    ? $this->Url->build('/admin/desk/view?source=case&id=' . rawurlencode((string)$caseId) . ($caseRef !== '' ? '&ref=' . rawurlencode($caseRef) : ''))
    : '';
$adminInboxHref = $this->Url->build('/admin/desk');
$buildCaseHref = function (string $step = '') use ($caseRef): string {
    $query = [];
    if ($caseRef !== '') {
        $query['ref'] = $caseRef;
    }
    if ($step !== '') {
        $query['step'] = $step;
    }

    return $this->Url->build([
        'controller' => 'Passenger',
        'action' => 'case',
        '?' => $query,
    ], ['escape' => false]);
};

$incidentOptions = ['' => 'Vaelg', 'delay' => 'Forsinkelse', 'cancellation' => 'Aflysning', 'denied_boarding' => 'Boardingafvisning', 'missed_connection' => 'Mistet forbindelse'];
$yesNoUnknown = ['' => 'Vaelg', 'yes' => 'Ja', 'no' => 'Nej', 'unknown' => 'Ved ikke'];
$yesNo = ['' => 'Vaelg', 'yes' => 'Ja', 'no' => 'Nej'];
$noticeBandOptions = ['' => 'Vaelg', '14_plus_days' => '14 dage eller mere', '7_to_13_days' => '7-13 dage', 'under_7_days' => 'Under 7 dage', 'airport_on_day_of_departure' => 'I lufthavnen / ved afgang'];
$windowOptions = ['' => 'Vaelg', 'within_window' => 'Ja', 'outside_window' => 'Nej', 'unknown' => 'Ved ikke'];
$airRouteTypeOptions = ['direct' => 'Direkte fly', 'connecting' => 'Med mellemlanding(er)'];
$airConnectionTypeOptions = [
    '' => 'Ved ikke endnu',
    'protected_connection' => 'Ja, samme booking / protected connection',
    'separate_contracts' => 'Nej, separate billetter / kontrakter',
];
$extraordinaryTypeOptions = ['' => 'Ikke oplyst', 'weather' => 'Vejr', 'air_traffic_control' => 'ATC / luftrum', 'security' => 'Sikkerhed', 'external_strike' => 'Ekstern strejke', 'own_staff_strike' => 'Egen personalestrejke', 'technical_issue' => 'Teknisk fejl', 'crew_shortage' => 'Crew / bemanding', 'other' => 'Andet'];
$delayRemedyOptions = ['' => 'Vaelg', 'refund_return' => 'Jeg oensker refusion', 'no_refund_continue' => 'Jeg fortsaetter rejsen og oensker ikke refusion nu'];
$ongoingDelayRemedyOptions = ['' => 'Vaelg', 'refund_return' => 'Jeg vil stoppe rejsen og have refund', 'no_refund_continue' => 'Jeg fortsaetter rejsen og oensker ikke refusion nu'];
$delayRemedyInputOptions = $isOngoingDelayRefundContext ? $ongoingDelayRemedyOptions : $delayRemedyOptions;
$delayRemedyLabelOptions = $delayRemedyInputOptions + ['no_refund' => 'Jeg fortsaetter rejsen og oensker ikke refusion nu'];
$remedyOptions = ['' => 'Vaelg', 'refund_return' => 'Refusion / retur til foerste afgangssted', 'reroute_soonest' => 'Omlaegning hurtigst muligt', 'reroute_later' => 'Omlaegning paa et senere tidspunkt', 'no_real_choice' => 'Transportoeren tilboed ikke et reelt valg'];
if ($isFerryCase) {
    unset($remedyOptions['reroute_later']);
}
$refundScopeOptions = ['' => 'Vaelg', 'whole_ticket' => 'Hele billetten', 'unused_only' => 'Kun ubrugt del', 'unused_plus_used_if_no_longer_serves_purpose' => 'Ubrugt del plus brugt del, hvis rejsen ikke laengere tjente sit formaal'];
$rerouteReasonOptions = ['' => 'Vaelg', 'offer_not_usable' => 'Flyselskabets loesning kunne ikke bruges', 'needed_fast' => 'Passageren havde brug for hurtig videre rejse', 'separate_ticket' => 'Videre rejse eller separat billet skulle naas', 'alt_airport_issue' => 'Alternativ lufthavn gav praktiske problemer', 'other' => 'Anden aarsag'];
$rerouteLaterOutcomeOptions = ['' => 'Vaelg', 'operator_offered' => 'Passageren afventede senere ombooking', 'self_bought' => 'Passageren koebte selv en senere billet', 'no_solution' => 'Ingen loesning endnu'];
$expenseTypeOptions = ['' => 'Vaelg', 'new_ticket' => 'Ny billet', 'airport_transfer' => 'Transfer til/fra alternativ lufthavn', 'other_transport' => 'Anden noedvendig transport', 'expensive_solution' => 'Dyrere alternativ loesning', 'other' => 'Andet'];
$refundExpenseTypeOptions = [
    '' => 'Vaelg',
    'return_to_origin' => 'Returtransport / tilbage til afgangssted',
    'new_ticket' => 'Ny billet',
    'airport_transfer' => 'Transfer / alternativ transport',
    'other_transport' => 'Anden noedvendig transport',
    'expensive_solution' => 'Dyrere alternativ loesning',
    'other' => 'Andet',
];
$rerouteExpenseTypeOptions = [
    '' => 'Vaelg',
    'new_ticket' => 'Ny billet',
    'airport_transfer' => 'Transfer til/fra alternativ lufthavn',
    'other_transport' => 'Anden noedvendig transport',
    'expensive_solution' => 'Dyrere alternativ loesning',
    'other' => 'Andet',
];
$careExpenseTypeOptions = [
    '' => 'Vaelg',
    'meal' => 'Maaltider / forfriskninger',
    'hotel' => 'Hotel / indkvartering',
    'hotel_transport' => 'Transport til/fra hotel',
    'other' => 'Andet',
];
$pmrDeliveredOptions = ['' => 'Vaelg', 'fully_delivered' => 'Ja, fuldt leveret', 'partly_delivered' => 'Delvist leveret', 'not_delivered' => 'Nej, ikke leveret'];
$article11CareOptions = ['' => 'Vaelg', 'fully_delivered' => 'Ja, saa hurtigt som muligt', 'partly_delivered' => 'Delvist / uklart', 'not_delivered' => 'Nej'];
$ferryPmrSpecialNeedsOptions = ['' => 'Vaelg', 'yes' => 'Ja', 'no' => 'Nej', 'unknown' => 'Ved ikke', 'not_relevant' => 'Ikke relevant / behovet var ikke kendt'];
$ferryPmrDeliveredOptions = ['' => 'Vaelg', 'full' => 'Fuldt leveret', 'partial' => 'Delvist leveret', 'none' => 'Ikke leveret', 'unknown' => 'Ved ikke'];
$ferryPmrRefusalBasisOptions = ['' => 'Ikke oplyst', 'safety_requirements' => 'Sikkerhedskrav', 'port_or_ship_infrastructure' => 'Havnens eller skibets indretning', 'other_or_unknown' => 'Andet / ved ikke'];
$stepDisplayLabels = ['refund' => 'Afhjaelpning', 'documents' => 'Dokumenter', 'support' => 'Assistance', 'applicant' => 'Ansoger', 'consent' => 'Samtykke'];
$multimodalState = (array)($metaState['_multimodal'] ?? []);
$ferryScopeState = (array)($caseData['ferryScope'] ?? ($multimodalState['ferry_scope'] ?? []));
$ferryRights = (array)($caseData['ferryRights'] ?? ($multimodalState['ferry_rights'] ?? []));
$ferryPmrRights = (array)($caseData['ferryPmrRights'] ?? ($multimodalState['ferry_pmr_rights'] ?? []));
$ferryOpsEvidence = (array)($caseData['ferryOperationalEvidence'] ?? ($metaState['ferry_operational_evidence'] ?? []));
$selectedDeparture = (array)($metaState['ferry_selected_departure'] ?? []);
$railSelectedDeparture = (array)($caseData['railSelectedDeparture'] ?? ($metaState['rail_selected_departure'] ?? []));
$railOpsEvidence = (array)($caseData['railOperationalEvidence'] ?? ($metaState['rail_operational_evidence'] ?? []));
$railIncidentSeed = (array)($caseData['railIncidentSeed'] ?? ($metaState['rail_incident_seed'] ?? []));
$modeEyebrow = $isFerryCase
    ? 'Ferry ' . ($isOngoingCase ? 'ongoing' : 'completed')
    : ($isRailCase ? 'Rail ' . ($isOngoingCase ? 'ongoing' : 'completed') : 'Air ' . ($isOngoingCase ? 'ongoing' : 'completed'));
$modeHeroText = $isFerryCase
    ? ($isOngoingCase
        ? 'Liveflowet har samlet ferry-afgang, ETA/ATA og de foerste gates. Backend samler kun de resterende ferry-spor: Art. 18 remedy, Art. 17 assistance, dokumenter, ansoeger og samtykke.'
        : 'Frontflowet holdes kort. Her i kontrolpanelet samles de tilbagevaerende ferry-spor: Art. 18 remedy, Art. 17 assistance, dokumenter, ansoeger og samtykke.')
    : ($isRailCase
        ? ($isOngoingCase
            ? 'Liveflowet har samlet togafgang, rail-seed og de foerste gates. Backend samler kun de resterende rail-spor: Art. 18 refund/omlaegning, Art. 20 assistance, dokumenter, ansoeger og samtykke.'
            : 'Frontflowet holdes kort. Her i kontrolpanelet samles de tilbagevaerende rail-spor: Art. 18 refund/omlaegning, Art. 19 kompensation, Art. 20 assistance, dokumenter, ansoeger og samtykke.')
        : ($isOngoingCase
            ? 'Liveflowet har samlet de hurtige valg. Her i kontrolpanelet matcher backend de samme air-spor og viser kun de trin, der stadig skal dokumenteres eller afsluttes: afhjaelpning, assistance, dokumenter, ansoeger og samtykke efter behov.'
            : 'Frontflowet holdes kort. Her i kontrolpanelet samles de tilbagevaerende air-spor: afhjaelpning, assistance, dokumenter, ansoeger og samtykke.'));
$modeNextFocusText = $isFerryCase
    ? 'Arbejd dig igennem stepbaren til hoejre. Incident-fakta og ferry live-estimat ligger som fast sagsresumae, saa backend kun viser det, der stadig skal dokumenteres eller vaelges.'
    : ($isRailCase
        ? 'Arbejd dig igennem stepbaren til hoejre. Incident-fakta og rail live-estimat ligger som fast sagsresumae, saa backend kun viser det, der stadig skal dokumenteres eller bekraeftes.'
        : ($isOngoingCase
            ? 'Arbejd dig igennem stepbaren til hoejre. Backend viser kun de air-spor, der er aktiveret af frontflowets incident-gating og de oplysninger, der stadig kan dokumenteres eller korrigeres.'
            : 'Arbejd dig igennem stepbaren til hoejre. Incident-fakta ligger nu som et fast sagsresumae over trinene, saa backend kun viser det, du stadig skal tage stilling til.'));
$segmentLabel = $isFerryCase ? 'Færgeafgang' : 'Flyvning';
$segmentFallback = $isFerryCase ? 'Vaelges i ferry flow' : 'Vaelges i air flow';
$routeFallback = $isFerryCase ? 'Ferry-sag' : 'Air-sag';
$articleCompensationLabel = $isFerryCase ? 'Art. 19 kompensation' : 'Article 7 kompensation';
$articleRemedyLabel = $isFerryCase ? 'Art. 18 refund/ombooking' : 'Article 8 remedies';
$articleAssistanceLabel = $isFerryCase ? 'Art. 17 assistance' : 'Article 9 assistance';
$knownArticlesText = $isFerryCase ? 'Ferry Art. 17, 18, 19 og PMR medregnes kun i det omfang beloeb er kendt.' : 'Article 7, 8, 9 og 10 medregnes kun i det omfang beloeb er kendt.';
$feeBasisText = $isFerryCase ? 'Fee beregnes foreloebigt kun af Art. 19-kompensation.' : 'Fee beregnes foreloebigt kun af Article 7 og Article 10.';
$segmentLabel = $isRailCase ? 'Togafgang' : $segmentLabel;
$segmentFallback = $isRailCase ? 'Vaelges i rail flow' : $segmentFallback;
$routeFallback = $isRailCase ? 'Rail-sag' : $routeFallback;
$articleCompensationLabel = $isRailCase ? 'Art. 19 kompensation' : $articleCompensationLabel;
$articleRemedyLabel = $isRailCase ? 'Art. 18 refund/omlaegning' : $articleRemedyLabel;
$articleAssistanceLabel = $isRailCase ? 'Art. 20 assistance' : $articleAssistanceLabel;
$knownArticlesText = $isRailCase ? 'Rail Art. 18, 19 og 20 medregnes kun i det omfang beloeb er kendt.' : $knownArticlesText;
$feeBasisText = $isRailCase ? 'Fee beregnes foreloebigt kun af Art. 19-kompensation.' : $feeBasisText;
$ferryGateArt19 = !empty($ferryRights['gate_art19'])
    || (string)($flagsState['gate_art19'] ?? '') === '1'
    || (string)($flagsState['gate_ferry_art19'] ?? '') === '1';
$ferryBand = (string)($ferryRights['art19_comp_band'] ?? ($flagsState['ferry_art19_comp_band'] ?? 'none'));
$ferryBandPct = in_array($ferryBand, ['25', '50'], true) ? (int)$ferryBand : 0;
$ferryGateArt18 = !empty($ferryRights['gate_art18']) || (string)($flagsState['gate_art18'] ?? '') === '1';
$ferryGateArt17 = !empty($ferryRights['gate_art17_refreshments'])
    || !empty($ferryRights['gate_art17_hotel'])
    || (string)($flagsState['gate_ferry_art17_refreshments'] ?? '') === '1'
    || (string)($flagsState['gate_ferry_art17_hotel'] ?? '') === '1';
$ferryGatePmrRemedy = !empty($ferryPmrRights['gate_ferry_pmr_remedy_art8_3']) || !empty($ferryPmrRights['gate_ferry_pmr_boarding_remedy']) || (string)($flagsState['gate_ferry_pmr_remedy'] ?? '') === '1';
$railGateArt19 = !empty($railIncidentSeed['gate_art19']) || (string)($flagsState['gate_art19'] ?? '') === '1';
$railGateArt18 = !empty($railIncidentSeed['gate_art18']) || (string)($flagsState['gate_art18'] ?? '') === '1';
$railGateArt20 = !empty($railIncidentSeed['gate_art20']) || (string)($flagsState['gate_art20'] ?? '') === '1';
$ferryVesselLabel = trim(implode(' · ', array_filter([
    (string)($ferryOpsEvidence['vessel_name'] ?? ($selectedDeparture['vessel_name'] ?? ($formState['ferry_vessel_name'] ?? ''))),
    !empty($ferryOpsEvidence['vessel_imo']) ? ('IMO ' . (string)$ferryOpsEvidence['vessel_imo']) : null,
    !empty($ferryOpsEvidence['vessel_mmsi']) ? ('MMSI ' . (string)$ferryOpsEvidence['vessel_mmsi']) : null,
])));
$ferryOpsStatusLabel = trim((string)($ferryOpsEvidence['status'] ?? ''));
$ferryOpsSourceLabel = strtoupper(trim((string)($ferryOpsEvidence['source'] ?? '')));
$railTrainLabel = trim(implode(' | ', array_filter([
    (string)($railSelectedDeparture['train_number'] ?? ($formState['train_number'] ?? '')),
    (string)($railSelectedDeparture['operator_name'] ?? ($formState['operator'] ?? '')),
    !empty($railSelectedDeparture['planned_departure_at']) ? ('Afgang ' . date('H:i', strtotime((string)$railSelectedDeparture['planned_departure_at']))) : null,
])));
$railOpsStatusLabel = trim((string)($railSelectedDeparture['status'] ?? ($railOpsEvidence['status'] ?? '')));
$railOpsSourceLabel = strtoupper(trim((string)($railSelectedDeparture['source'] ?? ($railOpsEvidence['source'] ?? ''))));
$transportNodesSearchUrl = $this->Url->build('/api/transport-nodes/search');
$labelFor = static function (array $options, string $value, string $fallback = 'Ikke udfyldt'): string {
    if (isset($options[$value]) && $options[$value] !== '') {
        return (string)$options[$value];
    }
    return $value !== '' ? $value : $fallback;
};
$cancellationWindowConfig = match ($field('cancellation_notice_band')) {
    '7_to_13_days' => [
        'showWindows' => true,
        'departure' => 'Afgik den alternative flyvning hojst 2 timer foer planlagt afgang?',
        'arrival' => 'Ankom den alternative flyvning senest 4 timer efter planlagt ankomst?',
        'note' => 'Ved varsel 7-13 dage bruges 2 timer foer / 4 timer efter som Article 5-vindue.',
    ],
    'under_7_days', 'airport_on_day_of_departure' => [
        'showWindows' => true,
        'departure' => 'Afgik den alternative flyvning hojst 1 time foer planlagt afgang?',
        'arrival' => 'Ankom den alternative flyvning senest 2 timer efter planlagt ankomst?',
        'note' => 'Ved varsel under 7 dage eller aflysning oplyst i lufthavnen bruges 1 time foer / 2 timer efter som Article 5-vindue.',
    ],
    '14_plus_days' => [
        'showWindows' => false,
        'departure' => 'Afgik den alternative flyvning inden for det relevante vindue?',
        'arrival' => 'Ankom den alternative flyvning inden for det relevante vindue?',
        'note' => 'Ved 14+ dages varsel bortfalder kompensation normalt allerede efter Article 5, saa de detaljerede vinduer er som udgangspunkt ikke noedvendige.',
    ],
    default => [
        'showWindows' => false,
        'departure' => 'Afgik den alternative flyvning inden for det relevante vindue?',
        'arrival' => 'Ankom den alternative flyvning inden for det relevante vindue?',
        'note' => 'Afklar foerst varsel om aflysning. De detaljerede Article 5-vinduer er kun relevante ved 7-13 dage eller under 7 dage.',
    ],
};
$overnightNeededValue = $field('overnight_needed', (string)($caseData['overnightNeeded'] ?? ''));
$supportOvernightValue = $isAirCase
    ? $field('air_next_day_departure', $overnightNeededValue)
    : $overnightNeededValue;
$ferryDepartureFromTerminalRaw = strtolower(trim($field('departure_from_terminal')));
$ferryDepartureFromTerminal = $ferryScopeState['departure_from_terminal'] ?? null;
if (!is_bool($ferryDepartureFromTerminal)) {
    if ($ferryDepartureFromTerminalRaw === 'yes') {
        $ferryDepartureFromTerminal = true;
    } elseif ($ferryDepartureFromTerminalRaw === 'no') {
        $ferryDepartureFromTerminal = false;
    } else {
        $ferryDepartureFromTerminal = null;
    }
}
$article7EligibilityLabels = [
    'eligible' => 'Kompensation mulig',
    'not_eligible' => 'Kompensation bortfalder',
    'uncertain' => 'Kompensation usikker',
];
$article7ReductionLabels = [
    'applied' => '50% reduktion anvendt',
    'provisional' => '50% reduktion foreloebig',
    'unknown' => '50% reduktion ikke afklaret',
    'not_applicable' => 'Ingen 50% reduktion',
];
$extraordinaryBandLabels = [
    'low' => 'Lav extraordinary-risiko',
    'medium' => 'Manual review',
    'high' => 'Staerkt extraordinary-spor',
    'none' => 'Ikke vurderet',
];
$summarizeExpenseItems = static function (array $items): array {
    $sum = 0.0;
    $count = 0;
    $currencies = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $amount = trim((string)($item['amount'] ?? ''));
        if ($amount === '' || !is_numeric($amount)) {
            continue;
        }
        $sum += (float)$amount;
        $count++;
        $currency = strtoupper(trim((string)($item['currency'] ?? '')));
        if ($currency === '') {
            $currency = 'UKENDT';
        }
        $currencies[$currency] = round(($currencies[$currency] ?? 0.0) + (float)$amount, 2);
    }
    return [
        'count' => $count,
        'total' => round($sum, 2),
        'currencies' => $currencies,
    ];
};
$returnExpenseItems = array_values(array_filter($refundExpenseItems, static fn(array $item): bool => (string)($item['type'] ?? '') === 'return_to_origin'));
$rerouteExpenseItemsOnly = array_values(array_filter($refundExpenseItems, static fn(array $item): bool => (string)($item['type'] ?? '') !== 'return_to_origin'));
$returnExpenseSummary = $summarizeExpenseItems($returnExpenseItems);
$rerouteExpenseSummary = $summarizeExpenseItems($rerouteExpenseItemsOnly);
$careExpenseSummary = $summarizeExpenseItems($careExpenseItems);
$formatExpenseSummary = static function (array $summary): string {
    if ((int)($summary['count'] ?? 0) === 0) {
        return 'Ingen poster endnu';
    }
    $currencies = (array)($summary['currencies'] ?? []);
    if (count($currencies) === 1) {
        $currency = (string)array_key_first($currencies);
        $amount = (float)reset($currencies);
        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
    $parts = [];
    foreach ($currencies as $currency => $amount) {
        $parts[] = number_format((float)$amount, 2, '.', ',') . ' ' . $currency;
    }
    return implode(' + ', $parts);
};
$remedyChoiceValue = $field('remedyChoice');
$refundScopeRawValue = $field('air_refund_scope') !== '' ? $field('air_refund_scope') : $field('ferry_refund_scope');
$refundScopeValue = match ($refundScopeRawValue) {
    'full_ticket' => 'whole_ticket',
    'unused_part' => 'unused_only',
    default => $refundScopeRawValue,
};
$refundOverviewText = !$showRefundStep
    ? 'Ikke aktiv'
    : ($remedyChoiceValue !== ''
        ? $labelFor($isDelayRefundContext ? $delayRemedyLabelOptions : $remedyOptions, $remedyChoiceValue, 'Ikke oplyst')
        : 'Afventer passagerens valg');
$compOverviewAmountText = $article7EligibilityStatus === 'eligible'
    ? ((string)$article7FinalAmountEur . ' EUR')
    : ($article7EligibilityStatus === 'uncertain' ? 'Afventer manuel vurdering' : '0 EUR');
$extraordinaryOverviewText = $extraordinaryBand !== ''
    ? $labelFor($extraordinaryBandLabels, $extraordinaryBand, $extraordinaryBand)
    : 'Ikke vurderet';
$downgradeOccurred = $field('downgrade_occurred') === 'yes';
$downgradePct = trim((string)($formState['air_downgrade_refund_percent'] ?? ''));
$downgradePriceBasis = strtolower(trim((string)($formState['air_downgrade_ticket_price_basis'] ?? 'affected_legs')));
$downgradePriceBasisLabel = match ($downgradePriceBasis) {
    'affected_legs' => 'Pris for markerede downgradede leg(s)',
    'whole_ticket' => 'Pris for hele billetten',
    'unknown' => 'Ikke afklaret',
    default => 'Ikke afklaret',
};
$downgradeTicketPrice = trim((string)($formState['air_downgrade_ticket_price'] ?? ''));
$downgradeTicketCurrency = strtoupper(trim((string)($formState['air_downgrade_ticket_price_currency'] ?? 'EUR')));
$downgradeSegmentShare = is_numeric($formState['downgrade_segment_share'] ?? null)
    ? max(0.0, min(1.0, (float)$formState['downgrade_segment_share']))
    : 1.0;
$downgradeAppliedShare = $downgradePriceBasis === 'whole_ticket' ? $downgradeSegmentShare : 1.0;
$downgradeSelectedLegs = array_values(array_filter((array)($formState['leg_downgraded'] ?? []), static fn($value): bool => (string)$value === '1'));
$downgradeSelectedLegCount = count($downgradeSelectedLegs);
$downgradeRefundOverlap = $downgradeOccurred
    && $remedyChoiceValue === 'refund_return'
    && in_array($refundScopeValue, ['whole_ticket', 'unused_plus_used_if_no_longer_serves_purpose'], true);
$downgradeAmountText = 'Ikke registreret';
if ($downgradeOccurred) {
    if ($downgradeTicketPrice !== '' && is_numeric(str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $downgradeTicketPrice))) && $downgradePct !== '' && is_numeric($downgradePct)) {
        $downgradeTicketNumeric = (float)str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $downgradeTicketPrice));
        $downgradeAmount = round($downgradeTicketNumeric * (((float)$downgradePct) / 100) * $downgradeAppliedShare, 2);
        $downgradeAmountText = number_format($downgradeAmount, 2, '.', ',') . ' ' . $downgradeTicketCurrency;
    } else {
        $downgradeAmountText = 'Afventer billetpris eller sats';
    }
}
$addMoney = static function (array &$bucket, float $amount, string $currency): void {
    $currency = strtoupper(trim($currency));
    if ($currency === '' || abs($amount) < 0.00001) {
        return;
    }
    $bucket[$currency] = round((float)($bucket[$currency] ?? 0.0) + $amount, 2);
};
$subtractMoney = static function (array $left, array $right): array {
    $out = $left;
    foreach ($right as $currency => $amount) {
        $out[$currency] = round((float)($out[$currency] ?? 0.0) - (float)$amount, 2);
        if (abs((float)$out[$currency]) < 0.00001) {
            unset($out[$currency]);
        }
    }
    ksort($out);
    return $out;
};
$formatMoneyMap = static function (array $amounts, string $empty = 'Ikke beregnet'): string {
    if ($amounts === []) {
        return $empty;
    }
    $parts = [];
    foreach ($amounts as $currency => $amount) {
        $parts[] = number_format((float)$amount, 2, '.', ',') . ' ' . strtoupper((string)$currency);
    }
    return implode(' + ', $parts);
};
$ticketPriceNumeric = null;
$ticketPriceCurrency = strtoupper(trim((string)($formState['price_currency'] ?? 'EUR')));
$ticketPriceRaw = trim((string)($formState['price'] ?? ''));
if ($ticketPriceRaw !== '') {
    $ticketPriceSanitized = str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $ticketPriceRaw));
    if ($ticketPriceSanitized !== '' && is_numeric($ticketPriceSanitized)) {
        $ticketPriceNumeric = (float)$ticketPriceSanitized;
    }
}
$ferryArt19Amount = null;
if ($isFerryCase && $ferryGateArt19 && $ferryBandPct > 0 && $ticketPriceNumeric !== null) {
    $ferryArt19Amount = round($ticketPriceNumeric * ($ferryBandPct / 100), 2);
    $compOverviewAmountText = number_format($ferryArt19Amount, 2, '.', ',') . ' ' . $ticketPriceCurrency;
} elseif ($isFerryCase && $ferryGateArt19 && $ferryBandPct > 0) {
    $compOverviewAmountText = $ferryBandPct . '% af billetpris';
} elseif ($isFerryCase) {
    $compOverviewAmountText = '0 EUR';
}
$railBandPct = $railGateArt19
    ? ((is_numeric($railIncidentSeed['arrival_delay_minutes'] ?? null) && (int)$railIncidentSeed['arrival_delay_minutes'] >= 120) ? 50 : 25)
    : 0;
$railArt19Amount = null;
if ($isRailCase && $railBandPct > 0 && $ticketPriceNumeric !== null) {
    $railArt19Amount = round($ticketPriceNumeric * ($railBandPct / 100), 2);
    $compOverviewAmountText = number_format($railArt19Amount, 2, '.', ',') . ' ' . $ticketPriceCurrency;
} elseif ($isRailCase && $railBandPct > 0) {
    $compOverviewAmountText = $railBandPct . '% af billetpris';
} elseif ($isRailCase) {
    $compOverviewAmountText = '0 EUR';
}
$article7Amounts = [];
if ($isFerryCase && $ferryArt19Amount !== null) {
    $addMoney($article7Amounts, $ferryArt19Amount, $ticketPriceCurrency);
} elseif ($isRailCase && $railArt19Amount !== null) {
    $addMoney($article7Amounts, $railArt19Amount, $ticketPriceCurrency);
} elseif (!$isFerryCase && $article7EligibilityStatus === 'eligible' && $article7FinalAmountEur > 0) {
    $addMoney($article7Amounts, (float)$article7FinalAmountEur, 'EUR');
}
$article8Amounts = [];
$article8Notes = [];
foreach ((array)($returnExpenseSummary['currencies'] ?? []) as $currency => $amount) {
    $addMoney($article8Amounts, (float)$amount, (string)$currency);
}
foreach ((array)($rerouteExpenseSummary['currencies'] ?? []) as $currency => $amount) {
    $addMoney($article8Amounts, (float)$amount, (string)$currency);
}
if ($remedyChoiceValue === 'refund_return' && in_array($refundScopeValue, ['whole_ticket', 'unused_plus_used_if_no_longer_serves_purpose'], true)) {
    if ($ticketPriceNumeric !== null) {
        $addMoney($article8Amounts, $ticketPriceNumeric, $ticketPriceCurrency);
        $article8Notes[] = 'Billetrefusion er medregnet ud fra registreret billetpris.';
    } else {
        $article8Notes[] = 'Billetrefusion er valgt, men billetpris mangler stadig i sagen.';
    }
} elseif ($remedyChoiceValue === 'refund_return' && $refundScopeValue === 'unused_only') {
    $article8Notes[] = 'Kun ubrugt del er valgt. Endeligt Article 8-beloeb afventer afgraensning af billetdelen.';
}
$article9Amounts = [];
foreach ((array)($careExpenseSummary['currencies'] ?? []) as $currency => $amount) {
    $addMoney($article9Amounts, (float)$amount, (string)$currency);
}
$article10Amounts = [];
if ($downgradeOccurred && !$downgradeRefundOverlap && isset($downgradeAmount) && $downgradeAmount > 0) {
    $addMoney($article10Amounts, (float)$downgradeAmount, $downgradeTicketCurrency);
}
$grossAmounts = [];
foreach ([$article7Amounts, $article8Amounts, $article9Amounts, $article10Amounts] as $bucket) {
    foreach ($bucket as $currency => $amount) {
        $addMoney($grossAmounts, (float)$amount, (string)$currency);
    }
}
$feeBaseAmounts = [];
foreach ([$article7Amounts, $article10Amounts] as $bucket) {
    foreach ($bucket as $currency => $amount) {
        $addMoney($feeBaseAmounts, (float)$amount, (string)$currency);
    }
}
$feePct = 20.0;
$feeAmounts = [];
foreach ($feeBaseAmounts as $currency => $amount) {
    $addMoney($feeAmounts, round((float)$amount * ($feePct / 100), 2), (string)$currency);
}
$netAmounts = $subtractMoney($grossAmounts, $feeAmounts);
$settlementNotes = [];
if ($article7EligibilityStatus === 'uncertain') {
    $settlementNotes[] = 'Article 7 er stadig usikker og kan aendre fee-grundlaget.';
}
if ($downgradeRefundOverlap) {
    $settlementNotes[] = 'Article 10 er foreloebigt holdt uden for fee og brutto, fordi refund ser ud til at daekke samme billetdel.';
}
if ($article8Notes !== []) {
    $settlementNotes = array_merge($settlementNotes, $article8Notes);
}
if ($isFerryCase && $ferryGatePmrRemedy) {
    $settlementNotes[] = 'PMR-remedy er aabnet separat efter ferry PMR-reglerne om naegtet indskibning. Det blandes ikke sammen med almindelig Art. 18-delay/cancellation.';
}
if (count($grossAmounts) > 1) {
    $settlementNotes[] = 'Oversigten vises pr. kendt valuta. En endelig samlet afregning i én valuta kan kraeve omregning senere.';
}
if ($refundExpenseItems === []) {
    $refundExpenseItems = [['type' => '', 'amount' => '', 'currency' => '', 'description' => '', 'receipt' => []]];
}
if ($careExpenseItems === []) {
    $careExpenseItems = [['type' => '', 'amount' => '', 'currency' => '', 'description' => '', 'receipt' => []]];
}
?>
<?= $this->element('passenger_sidebar', compact('passengerNav')) ?>

<style>
  .case-page { max-width: 1280px; margin: 0 auto; padding: 8px 0 24px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; color: #0f172a; }
  .case-hero { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .case-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .06em; color: #0a6fd8; text-transform: uppercase; margin-bottom: 6px; }
  .case-hero h1 { margin: 0 0 6px; font-size: 30px; line-height: 1.08; }
  .case-hero p { margin: 0; color: #64748b; line-height: 1.55; max-width: 880px; }
  .case-progress-wrap { margin-top: 14px; display:grid; gap: 8px; }
  .case-progress-head { display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap:wrap; }
  .case-progress-title { font-size: 13px; font-weight: 700; color: #0f172a; letter-spacing: .04em; text-transform: uppercase; }
  .case-progress-meta { font-size: 13px; color: #64748b; }
  .case-progress-track { height: 10px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
  .case-progress-bar { height: 100%; border-radius: 999px; background: #0a6fd8; }
  .case-shell { display:grid; grid-template-columns: 240px minmax(0, 1fr); gap: 16px; align-items:start; }
  .case-card, .case-panel, .case-stepbar { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .case-card { padding: 16px; }
  .case-meta { display:grid; gap: 12px; position: sticky; top: 16px; }
  .case-meta-item { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #f8fafc; }
  .case-meta-item strong { display:block; color:#64748b; font-size:12px; letter-spacing:.04em; text-transform:uppercase; margin-bottom:6px; }
  .case-actions { display:flex; gap: 8px; flex-wrap:wrap; margin-top: 14px; }
  .case-cta, .case-button { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:9px 12px; border-radius:10px; text-decoration:none; font:inherit; font-weight:600; border:1px solid #cbd5e1; background:#fff; color:#0f172a; cursor:pointer; }
  .case-cta.primary, .case-button.primary { background:#0f172a; color:#fff; border-color:#0f172a; }
  .case-note { margin-top: 12px; padding: 12px; border-radius: 10px; background: #f8fafc; color: #475569; border: 1px solid #e5e7eb; font-size: 14px; line-height: 1.45; }
  .case-stepbar { display:grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; padding: 12px; }
  .case-step { position: relative; text-align:left; text-decoration:none; color:#6b7280; border:1px solid #e5e7eb; border-radius:10px; background:#fff; padding: 12px; display:grid; gap:10px; min-height: 98px; }
  .case-step::before { display:none; }
  .case-step-indicator { width:32px; height:32px; border-radius:999px; border:2px solid #cbd5e1; background:#fff; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; }
  .case-step.done .case-step-indicator { background:#0f766e; border-color:#0f766e; color:#fff; }
  .case-step.active { border-color:#93c5fd; background:#f8fbff; }
  .case-step.active .case-step-indicator { border-color:#0a6fd8; color:#0a6fd8; }
  .case-step.optional .case-step-indicator { background:#f8fafc; color:#94a3b8; }
  .case-step-label { display:block; font-size:13px; line-height:1.25; color:#0f172a; font-weight:700; }
  .case-step-status { display:block; font-size:12px; margin-top:4px; color:#64748b; }
  .case-step.active .case-step-label, .case-step.active .case-step-status { color:#0a6fd8; }
  .case-panel { padding: 18px; }
  .case-panel h2 { margin-top:0; margin-bottom: 8px; font-size: 26px; line-height: 1.1; }
  .case-panel > p { color:#475569; line-height:1.55; max-width: 920px; }
  .case-summary { margin-bottom:16px; }
  .case-summary-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:12px; }
  .case-summary-item { border:1px solid #e5e7eb; border-radius:10px; background:#f8fafc; padding:12px; }
  .case-summary-item strong { display:block; margin-bottom:4px; color:#334155; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
  .case-alert { margin: 14px 0 0; padding: 14px 16px; border-radius: 10px; border: 1px solid #fde68a; background: #fffbeb; color: #92400e; }
  .case-alert ul { margin: 8px 0 0 18px; padding: 0; }
  .case-subpanel { border:1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; padding: 14px; margin-top: 14px; }
  .case-subpanel h3 { margin: 0 0 6px; font-size: 18px; line-height: 1.2; }
  .case-badge { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid #cbd5e1; background:#fff; color:#334155; }
  .case-badge.match { border-color:#99f6e4; background:#f0fdfa; color:#0f766e; }
  .case-badge.partial { border-color:#bfdbfe; background:#eff6ff; color:#1d4ed8; }
  .case-badge.mismatch { border-color:#fecaca; background:#fef2f2; color:#b91c1c; }
  .case-badge.unknown { border-color:#e5e7eb; background:#f8fafc; color:#64748b; }
  .case-muted { color:#64748b; font-size:13px; }
  .case-form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 14px; }
  .case-field label { display:block; font-weight:700; color:#334155; font-size:14px; line-height: 1.35; }
  .case-field input, .case-field select, .case-field textarea { width:100%; margin-top:6px; padding: 10px 12px; border:1px solid #cbd5e1; border-radius: 10px; font: inherit; background:#fff; color:#0f172a; }
  .case-field select {
    min-height: 46px;
    height: auto;
    line-height: 1.35;
    padding-right: 12px;
    appearance: auto;
    -webkit-appearance: menulist;
    -moz-appearance: menulist;
    background-image: none;
  }
  .case-field textarea { min-height: 100px; resize: vertical; }
  .case-airport-autocomplete { position: relative; }
  .case-airport-suggestions { position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 30; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14); overflow: hidden; }
  .case-airport-suggestion { width: 100%; border: 0; background: #fff; padding: 10px 12px; text-align: left; cursor: pointer; display: grid; gap: 2px; }
  .case-airport-suggestion + .case-airport-suggestion { border-top: 1px solid #e5e7eb; }
  .case-airport-suggestion:hover,
  .case-airport-suggestion:focus { background: #eff6ff; outline: none; }
  .case-airport-suggestion strong { color: #0f172a; font-size: 14px; }
  .case-airport-suggestion span { color: #64748b; font-size: 12px; }
  .case-file-list { display:grid; gap:10px; margin-top:12px; }
  .case-file-item { border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fff; }
  .case-file-item a { font-weight:700; color:#0a6fd8; text-decoration:none; }
  .case-hidden { display:none; }
  .case-step-nav { display:flex; justify-content:space-between; gap: 12px; margin-top: 22px; align-items:center; }
  @media (max-width: 980px) {
    .case-shell { grid-template-columns: 1fr; }
    .case-form-grid { grid-template-columns: 1fr; }
    .case-summary-grid { grid-template-columns: 1fr; }
    .case-meta { position: static; }
    .case-stepbar { grid-template-columns: 1fr; gap: 10px; }
    .case-step { min-height: auto; grid-template-columns: 32px 1fr; align-items:center; }
  }
</style>

<div class="case-page">
  <section class="case-hero">
    <div class="case-eyebrow"><?= h($modeEyebrow) ?></div>
    <h1><?= h($isOngoingCase ? 'Suppler din live-sag' : 'Faerdiggoer dit krav') ?></h1>
    <p><?= h($modeHeroText) ?></p>
    <div class="case-progress-wrap">
      <div class="case-progress-head">
        <div class="case-progress-title">Sagens fremdrift</div>
        <div class="case-progress-meta"><?= h((string)$progressCompleted) ?>/<?= h((string)$progressTotal) ?> obligatoriske trin udfyldt - <?= h((string)$progressPct) ?>%</div>
      </div>
      <div class="case-progress-track"><div class="case-progress-bar" style="width: <?= h((string)$progressPct) ?>%;"></div></div>
    </div>
  </section>

  <div class="case-shell">
    <aside class="case-meta">
      <section class="case-card">
        <div class="case-meta-item"><strong>Status</strong><div><?= h((string)($caseData['status'] ?? 'Sag paabegyndt')) ?></div></div>
        <div class="case-meta-item"><strong>Rute</strong><div><?= h((string)($caseData['routeLabel'] ?? $routeFallback)) ?></div></div>
        <div class="case-meta-item"><strong><?= h($segmentLabel) ?></strong><div><?= h((string)($caseData['flightLabel'] ?? $segmentFallback)) ?></div></div>
        <div class="case-meta-item"><strong>Dato</strong><div><?= h((string)($caseData['date'] ?? '-')) ?></div></div>
      </section>
      <section class="case-card">
        <strong style="display:block; margin-bottom:10px;">Naeste fokus</strong>
        <div class="case-note"><?= h($modeNextFocusText) ?></div>
        <div class="case-actions">
          <a class="case-cta primary" href="<?= h($this->Url->build('/flow/compensation')) ?>">Se resultat</a>
        </div>
      </section>
    </aside>
    <div>
      <section class="case-card case-summary">
        <strong style="display:block; margin-bottom:10px;">Sagsresumae</strong>
        <div class="case-summary-grid">
          <div class="case-summary-item"><strong>Haendelse</strong><div><?= h($labelFor($incidentOptions, $field('incident_main'))) ?></div></div>
          <?php if ($isFerryCase): ?>
            <div class="case-summary-item"><strong>Fartoj / IMO-MMSI</strong><div><?= h($ferryVesselLabel !== '' ? $ferryVesselLabel : 'Ikke valgt endnu') ?></div></div>
            <div class="case-summary-item"><strong>Ops datakilde</strong><div><?= h($ferryOpsSourceLabel !== '' ? $ferryOpsSourceLabel : 'Afventer') ?></div></div>
            <div class="case-summary-item"><strong>Ops status</strong><div><?= h($ferryOpsStatusLabel !== '' ? $ferryOpsStatusLabel : 'Afventer') ?></div></div>
            <div class="case-summary-item"><strong>Art. 19 gate</strong><div><?= h($ferryGateArt19 ? ('Aktiv' . ($ferryBandPct > 0 ? ' - ' . $ferryBandPct . '%' : '')) : 'Ikke aktiv') ?></div></div>
            <div class="case-summary-item"><strong>Art. 18 gate</strong><div><?= h($ferryGateArt18 ? 'Aktiv' : 'Ikke aktiv') ?></div></div>
            <div class="case-summary-item"><strong>Art. 17 gate</strong><div><?= h($ferryGateArt17 ? 'Aktiv' : 'Ikke aktiv') ?></div></div>
            <div class="case-summary-item"><strong>PMR Art. 8(3)</strong><div><?= h($ferryGatePmrRemedy ? 'Aktiv ved naegtet indskibning' : 'Ikke aktiv') ?></div></div>
          <?php elseif ($isRailCase): ?>
            <div class="case-summary-item"><strong>Tog / operator</strong><div><?= h($railTrainLabel !== '' ? $railTrainLabel : 'Ikke valgt endnu') ?></div></div>
            <div class="case-summary-item"><strong>Ops datakilde</strong><div><?= h($railOpsSourceLabel !== '' ? $railOpsSourceLabel : 'Afventer') ?></div></div>
            <div class="case-summary-item"><strong>Ops status</strong><div><?= h($railOpsStatusLabel !== '' ? $railOpsStatusLabel : 'Afventer') ?></div></div>
            <div class="case-summary-item"><strong>Art. 19 gate</strong><div><?= h($railGateArt19 ? 'Aktiv' : 'Ikke aktiv') ?></div></div>
            <div class="case-summary-item"><strong>Art. 18 gate</strong><div><?= h($railGateArt18 ? 'Aktiv' : 'Ikke aktiv') ?></div></div>
            <div class="case-summary-item"><strong>Art. 20 gate</strong><div><?= h($railGateArt20 ? 'Aktiv' : 'Ikke aktiv') ?></div></div>
          <?php endif; ?>
          <?php if ($incidentMain === 'delay'): ?>
            <div class="case-summary-item"><strong>Meldt forsinkelse ved afgang</strong><div><?= h($expectedDelayLabel !== '' ? $expectedDelayLabel : ($delayDepartureBandLabel !== '' ? $delayDepartureBandLabel : 'Ikke oplyst')) ?></div></div>
            <?php if ($isCompletedCase): ?>
              <div class="case-summary-item"><strong>Faktisk forsinkelse ved endelig ankomst</strong><div><?= h($actualArrivalDelayLabel !== '' ? $actualArrivalDelayLabel : ($arrivalDelayMinutes !== '' ? ($arrivalDelayMinutes . ' min') : 'Ikke oplyst')) ?></div></div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!$isFerryCase): ?>
            <div class="case-summary-item"><strong>Mistet videre forbindelse</strong><div><?= h($labelFor($yesNoUnknown, $field('protected_connection_missed'))) ?></div></div>
          <?php endif; ?>
          <div class="case-summary-item"><strong><?= h($isFerryCase ? 'Saerlig grund oplyst af operatoeren' : ($isRailCase ? 'Saerlig grund oplyst af jernbanevirksomheden' : 'Saerlig grund oplyst af flyselskabet')) ?></strong><div><?= h($labelFor($yesNoUnknown, $field('operatorExceptionalCircumstances', $field('extraordinary_circumstances')))) ?></div></div>
          <div class="case-summary-item"><strong>Oplyst grund</strong><div><?= h($operatorExceptionalTypeLabel !== '' ? $operatorExceptionalTypeLabel : 'Ikke oplyst') ?></div></div>
          <div class="case-summary-item"><strong>Aflysning varslet</strong><div><?= h($labelFor($noticeBandOptions, $field('cancellation_notice_band'))) ?></div></div>
          <div class="case-summary-item"><strong>Billet uploadet</strong><div><?= $ticketFiles !== [] ? 'Ja' : 'Nej' ?></div></div>
          <?php if ($incidentMain === 'cancellation' && $isFerryCase): ?>
            <div class="case-note">Ved aflysning kommer ferry-haendelsen og Art. 18-gaten fra incident, mens passagerens valg og egen loesning kommer fra remedies. Backend bruger samme struktur for igangvaerende og afsluttede sager, men med ferry-tekster.</div>
          <?php endif; ?>
          <?php if ($incidentMain === 'cancellation' && !$isFerryCase && !$isRailCase): ?>
            <div class="case-summary-item"><strong>Ombooking tilbudt ved aflysning</strong><div><?= h($labelFor($yesNoUnknown, $rerouteOffered, 'Ikke oplyst')) ?></div></div>
          <?php endif; ?>
        </div>
        <?php if ($compFallbackReasons !== []): ?>
          <div class="case-alert">
            <strong>Kompensation kan vaere gated ud i denne sag</strong>
            <ul>
              <?php foreach ($compFallbackReasons as $reason): ?>
                <li><?= h((string)$reason) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <?php if ($incidentMain === 'delay' && $airDelayThresholdHours > 0): ?>
          <div class="case-note">Art. 6-threshold for denne flyvning er afledt til <?= h((string)$airDelayThresholdHours) ?>+ timer. Backend viser derfor kun de trin, som stadig er relevante efter delay-gatingen.</div>
        <?php endif; ?>
      </section>

      <section class="case-card case-summary">
        <strong style="display:block; margin-bottom:10px;">Foreloebig afregningsoversigt</strong>
        <div class="case-summary-grid">
          <div class="case-summary-item">
            <strong>Samlet brutto</strong>
            <div><?= h($formatMoneyMap($grossAmounts, 'Afventer flere belob')) ?></div>
            <div class="case-muted" style="margin-top:6px;"><?= h($knownArticlesText) ?></div>
          </div>
          <div class="case-summary-item">
            <strong>Fee-grundlag</strong>
            <div><?= h($formatMoneyMap($feeBaseAmounts, 'Ikke relevant endnu')) ?></div>
            <div class="case-muted" style="margin-top:6px;"><?= h($feeBasisText) ?></div>
          </div>
          <div class="case-summary-item">
            <strong>Fee (20%)</strong>
            <div><?= h($formatMoneyMap($feeAmounts, '0.00 EUR')) ?></div>
            <div class="case-muted" style="margin-top:6px;">Bankoverfoersel er den eneste udbetalingsmetode i backend.</div>
          </div>
          <div class="case-summary-item">
            <strong>Forventet netto til passager</strong>
            <div><?= h($formatMoneyMap($netAmounts, 'Afventer flere belob')) ?></div>
            <div class="case-muted" style="margin-top:6px;">Netto = brutto minus fee paa kompensationsposterne.</div>
          </div>
        </div>
        <?php if ($settlementNotes !== []): ?>
          <div class="case-note">
            <?php foreach ($settlementNotes as $note): ?>
              <div><?= h((string)$note) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <section class="case-card case-summary">
        <strong style="display:block; margin-bottom:10px;">Foreloebig sagsoversigt</strong>
        <div class="case-summary-grid">
          <div class="case-summary-item">
            <strong><?= h($articleCompensationLabel) ?></strong>
            <div><?= h($isFerryCase ? ($ferryGateArt19 ? 'Kompensation mulig' : 'Kompensation ikke aktiv') : ($isRailCase ? ($railGateArt19 ? 'Kompensation mulig' : 'Kompensation ikke aktiv') : $labelFor($article7EligibilityLabels, $article7EligibilityStatus, 'Foreloebig vurdering mangler'))) ?></div>
            <div class="case-muted" style="margin-top:6px;">Grundlag: <?= h($isFerryCase ? ($ferryBandPct > 0 ? ($ferryBandPct . '% af billetpris') : 'Ikke beregnet') : ($isRailCase ? ($railGateArt19 ? '>=60 min ved ankomst' : 'Ikke beregnet') : ($article7BaseAmountEur > 0 ? (string)$article7BaseAmountEur . ' EUR' : 'Ikke beregnet'))) ?></div>
            <div class="case-muted">Aktuelt beloeb: <?= h($compOverviewAmountText) ?></div>
          </div>
          <div class="case-summary-item">
            <strong><?= h($articleRemedyLabel) ?></strong>
            <div><?= h($refundOverviewText) ?></div>
            <?php if ($refundScopeValue !== ''): ?>
              <div class="case-muted" style="margin-top:6px;">Refusionsscope: <?= h($labelFor($refundScopeOptions, $refundScopeValue, $refundScopeValue)) ?></div>
            <?php endif; ?>
            <div class="case-muted">Retur / refund-poster: <?= h($formatExpenseSummary($returnExpenseSummary)) ?></div>
            <div class="case-muted">Ombookingsposter: <?= h($formatExpenseSummary($rerouteExpenseSummary)) ?></div>
          </div>
          <div class="case-summary-item">
            <strong><?= h($articleAssistanceLabel) ?></strong>
            <div><?= $showSupportStep ? 'Aktiv i backend' : 'Ikke aktiv i backend' ?></div>
            <div class="case-muted" style="margin-top:6px;">Registrerede care-poster: <?= h($formatExpenseSummary($careExpenseSummary)) ?></div>
            <div class="case-muted"><?= h($supportSummary) ?></div>
          </div>
          <div class="case-summary-item">
            <strong>Extraordinary review</strong>
            <div><?= h($extraordinaryOverviewText) ?></div>
            <div class="case-muted" style="margin-top:6px;">Score: <?= $extraordinaryScore !== null ? h((string)$extraordinaryScore) . '/100' : 'Ikke beregnet' ?></div>
            <div class="case-muted"><?= $manualReviewRequired ? 'Sagen kraever manuel vurdering.' : 'Ingen tvungen manuel vurdering endnu.' ?></div>
          </div>
          <?php if (!$isFerryCase && !$isRailCase): ?>
          <div class="case-summary-item">
            <strong>Article 10 downgrade</strong>
            <div><?= $downgradeOccurred ? 'Registreret' : 'Ikke registreret' ?></div>
            <div class="case-muted" style="margin-top:6px;">Foreloebigt downgrade-beloeb: <?= h($downgradeAmountText) ?></div>
            <?php if ($downgradeOccurred): ?>
              <div class="case-muted">Prisgrundlag: <?= h($downgradePriceBasisLabel) ?></div>
              <div class="case-muted">Markerede downgradede legs: <?= h($downgradeSelectedLegCount > 0 ? (string)$downgradeSelectedLegCount : 'Ingen valgt endnu') ?></div>
            <?php endif; ?>
            <?php if ($downgradeOccurred && $downgradePct !== ''): ?>
              <div class="case-muted">Sats: <?= h($downgradePct) ?>% · Andel: <?= h($downgradePriceBasis === 'whole_ticket' ? number_format($downgradeSegmentShare * 100, 0) . '%' : 'Indbygget i prisgrundlaget') ?></div>
            <?php endif; ?>
            <?php if ($downgradeRefundOverlap): ?>
              <div class="case-muted">Refusion er samtidig valgt paa et bredt prisgrundlag. Article 10 vises derfor kun foreloebigt, indtil overlap med refund er afgraenset.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="case-note"><?= h($isFerryCase
          ? 'Oversigten laeser ferry-resultaterne fra frontend-flowet og viser backend som oekonomisk og dokumentationsspor. Art. 17, 18, 19 og PMR holdes adskilt, saa PMR-remedy ikke blandes med almindelig forsinkelse/aflysning.'
          : ($isRailCase
              ? 'Oversigten laeser rail-resultaterne fra frontend-flowet og viser backend som oekonomisk og dokumentationsspor. Art. 18, 19 og 20 holdes adskilt, saa kompensation, refund/omlaegning og assistance ikke blandes sammen.'
              : 'Oversigten laeser de juridiske resultater fra frontend-flowet og viser kun backend som oekonomisk og dokumentationsspor. Belob for refund, ombooking og assistance bygger derfor paa de poster, der registreres her i sagen.')
        ) ?></div>
      </section>

      <?php if ($isAdminCaseView): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin:0 0 16px;">
          <a class="case-button" href="<?= h($adminInboxHref) ?>">Tilbage til inbox</a>
          <?php if ($adminDeskHref !== ''): ?>
            <a class="case-button" href="<?= h($adminDeskHref) ?>">Aabn admin cockpit</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <nav class="case-stepbar" aria-label="Sagstrin">
        <?php foreach ($steps as $index => $step): ?>
          <?php
            $status = (string)($step['status'] ?? 'todo');
            $classes = ['case-step'];
            if ($status === 'done') { $classes[] = 'done'; }
            if ($status === 'optional') { $classes[] = 'optional'; }
            if (!empty($step['active'])) { $classes[] = 'active'; }
            $indicator = $status === 'done' ? 'OK' : (string)($index + 1);
            $stepLabel = (string)($stepDisplayLabels[(string)($step['key'] ?? '')] ?? ($step['label'] ?? 'Trin'));
            $statusText = match ($status) {
                'done' => 'Udfyldt',
                'active_needed' => 'Afventer',
                'optional' => 'Valgfrit',
                default => 'Mangler',
            };
          ?>
          <a class="<?= h(implode(' ', $classes)) ?>" href="<?= h($buildCaseHref((string)$step['key'])) ?>">
            <span class="case-step-indicator"><?= h($indicator) ?></span>
            <span><span class="case-step-label"><?= h($stepLabel) ?></span><span class="case-step-status"><?= h($statusText) ?></span></span>
          </a>
        <?php endforeach; ?>
      </nav>

      <section class="case-panel">
        <?php $activeStepLabel = (string)($stepDisplayLabels[$activeStep] ?? ($steps[$activeIndex]['label'] ?? 'Case')); ?>
        <h2><?= h($activeStepLabel) ?></h2>

        <?php if ($activeStep === 'refund'): ?>
          <?php
            $isOngoingDeniedBoardingContext = $isOngoingCase && $isDeniedBoardingContext;
            $frontendRerouteExpenseType = $field('air_reroute_expense_type');
            foreach ((array)($formState['air_reroute_expense_items'] ?? []) as $frontendExpenseRow) {
                if (!is_array($frontendExpenseRow)) {
                    continue;
                }
                $rowType = trim((string)($frontendExpenseRow['type'] ?? ''));
                if ($rowType !== '') {
                    $frontendRerouteExpenseType = $rowType;
                    break;
                }
            }
            $hasRerouteRemedy = in_array($field('remedyChoice'), ['reroute_soonest', 'reroute_later'], true)
              || ($isFerryCase && $field('remedyChoice') === 'no_real_choice');
            $deniedBoardingDirectExpensePath = $isDeniedBoardingContext
              && $hasRerouteRemedy
              && $field('air_self_arranged_reroute') === 'yes';
            $hasCompletedFrontendRerouteFacts = $isCompletedCase
              && $hasRerouteRemedy
              && (
                $field('air_self_arranged_reroute') !== ''
                || $field('air_reroute_expenses_incurred') !== ''
                || $frontendRerouteExpenseType !== ''
              );
            $hasCancellationFrontendIncidentFacts = $isCancellationRefundContext
              && (
                $field('cancellation_notice_band') !== ''
                || (
                  !$isFerryCase
                  && (
                    $field('reroute_offered') !== ''
                    || $field('reroute_used_or_accepted') !== ''
                  )
                )
              );
            $hasCancellationFrontendRemedyFacts = $isCancellationRefundContext
              && (
                $field('remedyChoice') !== ''
                || $field('air_self_arranged_reroute') !== ''
                || $field('air_reroute_expenses_incurred') !== ''
                || $frontendRerouteExpenseType !== ''
              );
            $showCancellationFrontendSummary = $hasCancellationFrontendIncidentFacts || $hasCancellationFrontendRemedyFacts;
            $isCancellationRerouteRemedy = $isCancellationRefundContext && $hasRerouteRemedy;
            $cancellationHasFrontendSelfArrangedFacts = $field('air_self_arranged_reroute') !== '';
            $cancellationHasFrontendExpenseFacts = $field('air_reroute_expenses_incurred') !== '' || $frontendRerouteExpenseType !== '';
            $showCancellationPassengerRerouteFacts = $isFerryCase
              ? $isCancellationRerouteRemedy
              : (
                $isCancellationRerouteRemedy
                && !($field('reroute_offered') === 'yes' && $field('reroute_used_or_accepted') === 'yes')
                && (
                  ($field('reroute_offered') !== '' && $field('reroute_offered') !== 'yes')
                  || in_array($field('reroute_used_or_accepted'), ['no', 'unknown'], true)
                  || (
                    $field('reroute_offered') === ''
                    && $field('reroute_used_or_accepted') === ''
                    && $cancellationHasFrontendSelfArrangedFacts
                  )
                )
              );
            $showDeniedBoardingPassengerRerouteFacts = $isDeniedBoardingContext
              && $hasRerouteRemedy
              && (
                $isOngoingDeniedBoardingContext
                || (
                  !($field('reroute_offered') === 'yes' && $field('reroute_used_or_accepted') === 'yes')
                  && (
                    $field('reroute_offered') === 'no'
                    || in_array($field('reroute_used_or_accepted'), ['no', 'unknown'], true)
                    || (
                      $field('reroute_offered') === ''
                      && $field('reroute_used_or_accepted') === ''
                      && $field('air_self_arranged_reroute') !== ''
                    )
                  )
                )
              );
            $showRerouteSelfArrangedFacts = $isFerryCase
              ? $hasRerouteRemedy
              : ($isDeniedBoardingContext ? $showDeniedBoardingPassengerRerouteFacts : $showCancellationPassengerRerouteFacts);
            $showSelfArrangedDetailFields = $showRerouteSelfArrangedFacts && $field('air_self_arranged_reroute') === 'yes';
            $showAcceptedAlternativeExpenseFacts = $hasRerouteRemedy
              && $field('reroute_offered') === 'yes'
              && $field('reroute_used_or_accepted') === 'yes';
            $showRerouteExpenseGatePanel = $hasRerouteRemedy
              || $showSelfArrangedDetailFields
              || $showAcceptedAlternativeExpenseFacts
              || $hasCompletedFrontendRerouteFacts
              || ($isOngoingDeniedBoardingContext && $field('air_reroute_expenses_incurred') !== '')
              || ($isCompletedCase && $field('air_reroute_expenses_incurred') !== '');
            $showRerouteExpensePanel = $showRerouteExpenseGatePanel && $field('air_reroute_expenses_incurred') === 'yes';
            $rerouteUsedOrAcceptedLabel = $isFerryCase
              ? 'Brugte eller accepterede passageren den tilbudte videre rejse?'
              : ($isCancellationRefundContext
                ? 'Fortsatte passageren paa den tilbudte alternative flyvning?'
                : 'Brugte eller accepterede passageren den alternative flyvning?');
            $selfArrangedRerouteLabel = $isFerryCase
              ? 'Maatte passageren selv finde en anden videre rejse?'
              : ($isCancellationRefundContext
                ? 'Maatte passageren selv finde en anden videre rejse?'
                : 'Maatte passageren selv arrangere en loesning?');
            $rerouteExpenseGateLabel = $isFerryCase
              ? 'Havde passageren konkrete udgifter til ombooking eller videre rejse?'
              : ($isCancellationRefundContext
                ? 'Havde passageren konkrete udgifter til ombooking eller videre rejse?'
                : 'Havde passageren udgifter til ny billet, transfer eller anden ombooking?');
            $lockedRemedyChoice = !$isDelayRefundContext
              && $field('remedyChoice') !== ''
              && array_key_exists($field('remedyChoice'), $remedyOptions);
            $lockedDelayRemedyChoice = $isDelayRefundContext
              && $field('remedyChoice') !== ''
              && array_key_exists($field('remedyChoice'), $delayRemedyLabelOptions);
            $lockedBackendArticle8Choice = $showBackendArticle8OfferField && $field('air_article8_choice_offered') !== '';
            $lockedRefundScope = $refundScopeRawValue !== '';
            $lockedReturnExpense = $field('return_to_origin_expense') !== '';
            $lockedRerouteOffered = ($isCancellationRefundContext || ($isDeniedBoardingContext && !$isOngoingDeniedBoardingContext)) && $field('reroute_offered') !== '';
            $lockedRerouteUsedOrAccepted = !$isOngoingDeniedBoardingContext && $field('reroute_used_or_accepted') !== '';
            $lockedSelfArrangedReroute = $field('air_self_arranged_reroute') !== '';
            $lockedSelfArrangedReason = !$isOngoingDeniedBoardingContext && $field('air_self_arranged_reroute_reason') !== '';
            $lockedRerouteExpensesIncurred = $field('air_reroute_expenses_incurred') !== '';
            $remedyArticleName = $isFerryCase ? 'Art. 18' : ($isRailCase ? 'Art. 18' : 'Article 8');
            $carrierLabel = $isFerryCase ? 'transportoeren' : ($isRailCase ? 'jernbanevirksomheden' : 'carrieren');
            if ($isFerryCase) {
                $remedyIntroText = 'Dette trin samler ferry Art. 18: tilbagebetaling, ombooking og eventuelle udgifter til videre rejse. Art. 19-kompensation og Art. 17-assistance holdes separat.';
            } elseif ($isRailCase) {
                $remedyIntroText = 'Dette trin samler rail Art. 18: refund eller omlaegning samt eventuelle udgifter til videre rejse. Art. 19-kompensation og Art. 20-assistance holdes separat.';
            } elseif ($isDelayRefundContext) {
                $remedyIntroText = 'Ved flyforsinkelse er dette trin indsnaevret til Article 8(1)(a): billetrefusion ved 5+ timer og eventuel retur til foerste afgangssted.';
            } else {
                $remedyIntroText = 'Dette trin samler Article 8, refusion eller ombooking. Hvis du vaelger refusion, kan flat-kompensation bortfalde, mens billettilbagebetaling bliver relevant.';
            }
            if ($isFerryCase) {
                $refundAlertText = 'Ferry Art. 18-refusion er valgt. Brug dette trin til at afklare refund-scope og eventuelle transportnaere retur- eller videre-rejseudgifter. Art. 19-kompensation vurderes separat.';
            } elseif ($isRailCase) {
                $refundAlertText = 'Rail Art. 18-refusion er valgt. Brug dette trin til at afklare refund-scope og eventuelle transportnaere retur- eller videre-rejseudgifter. Art. 19-kompensation vurderes separat.';
            } else {
                $refundAlertText = 'Den nuvaerende app gates Art. 7-kompensation ud, naar passageren vaelger refusion / retur til foerste afgangssted. Brug dette trin til at afklare juraen og registrere eventuelle transportnaere udgifter.';
            }
            if ($isOngoingCase) {
                if ($isFerryCase) {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra den igangvaerende frontend. Ferry-haendelse og Art. 18-gate er afklaret i incident, mens passagerens aktuelle valg og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i completed-sporet. Nedenfor afklares kun backend-opfoelgning til Art. 18, eventuelle udgiftsbelob og kvitteringer.';
                } elseif ($isRailCase) {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra den igangvaerende frontend. Rail-haendelse og Art. 18-gate er afklaret i incident, mens passagerens aktuelle valg og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i completed-sporet. Nedenfor afklares kun backend-opfoelgning til Art. 18, eventuelle udgiftsbelob og kvitteringer.';
                } else {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra den igangvaerende frontend. Varsel om aflysning og tilbudt alternativ flyvning er afklaret i incident, mens passagerens aktuelle valg og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i completed-sporet. Nedenfor afklares kun backend-opfoelgning til Article 5/7(2), eventuelle udgiftsbelob og kvitteringer.';
                }
            } else {
                if ($isFerryCase) {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra completed-frontflowet. Ferry-haendelse og Art. 18-gate kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i ongoing-sporet. Nedenfor afklares kun backend-opfoelgning til Art. 18, eventuelle udgiftsbelob og kvitteringer.';
                } elseif ($isRailCase) {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra completed-frontflowet. Rail-haendelse og Art. 18-gate kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i ongoing-sporet. Nedenfor afklares kun backend-opfoelgning til Art. 18, eventuelle udgiftsbelob og kvitteringer.';
                } else {
                    $cancellationFrontendSummaryText = 'Disse svar kommer fra completed-frontflowet. Varsel om aflysning og tilbudt alternativ flyvning kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.';
                    $cancellationFollowupText = 'Incident og remedies leverer dermed samme kernefakta som i ongoing-sporet. Nedenfor afklares kun backend-opfoelgning til Article 5/7(2), eventuelle udgiftsbelob og kvitteringer.';
                }
            }
          ?>
          <?php if ($isFerryCase || $isRailCase): ?>
            <p><?= h($remedyIntroText) ?></p>
          <?php else: ?>
          <p><?= h($isDelayRefundContext
            ? 'Ved flyforsinkelse er dette trin indsnævret til Article 8(1)(a): billetrefusion ved 5+ timer og eventuel retur til foerste afgangssted.'
            : 'Dette trin samler Article 8, refusion eller ombooking. Hvis du vaelger refusion, kan flat-kompensation bortfalde, mens billettilbagebetaling bliver relevant.'
          ) ?></p>
          <?php endif; ?>
          <?php if ($field('remedyChoice') === 'refund_return'): ?>
            <div class="case-alert"><strong>Refusion er valgt</strong><div><?= h($refundAlertText) ?></div></div>
          <?php endif; ?>
          <?php if ($incidentMain === 'cancellation' && !$isFerryCase && !$isRailCase): ?>
            <div class="case-note">Ved aflysning kommer varsel og tilbudt alternativ flyvning fra incident, mens passagerens Article 8-valg og egen loesning kommer fra remedies. Backend bruger samme struktur for igangvaerende og afsluttede sager, men med forskellige hjælpetekster.</div>
          <?php endif; ?>
          <?php if ($showCancellationFrontendSummary): ?>
            <div class="case-subpanel">
              <h3>Frontend-resume</h3>
              <p class="case-muted"><?= h($cancellationFrontendSummaryText) ?></p>
              <div class="case-summary-grid">
                <div class="case-summary-item"><strong>Varsel om aflysning</strong><div><?= h($labelFor($noticeBandOptions, $field('cancellation_notice_band'), 'Ikke oplyst')) ?></div></div>
                <?php if (!$isFerryCase && !$isRailCase): ?>
                <div class="case-summary-item"><strong>Alternativ flyvning tilbudt</strong><div><?= h($labelFor($yesNoUnknown, $field('reroute_offered'), 'Ikke oplyst')) ?></div></div>
                <?php endif; ?>
                <div class="case-summary-item"><strong>Valg i remedies</strong><div><?= h($labelFor($remedyOptions, $field('remedyChoice'), 'Ikke valgt')) ?></div></div>
                <div class="case-summary-item"><strong>Egen videre rejse</strong><div><?= h($labelFor($yesNo, $field('air_self_arranged_reroute'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Ekstraudgifter</strong><div><?= h($labelFor($yesNo, $field('air_reroute_expenses_incurred'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Udgiftstype fra frontend</strong><div><?= h($labelFor($rerouteExpenseTypeOptions, $frontendRerouteExpenseType, 'Ikke valgt')) ?></div></div>
              </div>
              <div class="case-note"><?= h($cancellationFollowupText) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($incidentMain === 'denied_boarding'): ?>
            <div class="case-note">Ved boardingafvisning registrerede frontflowet kun, om afvisningen var frivillig eller mod passagerens vilje. Brug dette trin til det faktiske Article 8-valg, egen loesning og de detaljerede udgifter bagefter.</div>
            <?php if ($deniedBoardingDirectExpensePath): ?>
              <div class="case-note">Frontend har allerede registreret, at passageren selv maatte finde en anden videre rejse. Derfor springer backend direkte til ombookingsudgifterne og viser ikke carrierens alternative flyvning eller 50%-sporet i denne gren.</div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($isOngoingDeniedBoardingContext && in_array($field('remedyChoice'), ['reroute_soonest', 'reroute_later'], true)): ?>
            <div class="case-subpanel">
              <h3>Liveflow-resume</h3>
              <p class="case-muted">Disse svar kommer fra den igangvaerende frontend. De beskriver passagerens aktuelle valg og udgiftsspor, ikke en endelig backend-vurdering af carrierens tilbud.</p>
              <div class="case-summary-grid">
                <div class="case-summary-item"><strong>Valg nu</strong><div><?= h($labelFor($remedyOptions, $field('remedyChoice'), 'Ikke valgt')) ?></div></div>
                <div class="case-summary-item"><strong>Egen videre rejse</strong><div><?= h($labelFor($yesNo, $field('air_self_arranged_reroute'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Ekstraudgifter</strong><div><?= h($labelFor($yesNo, $field('air_reroute_expenses_incurred'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Udgiftstype fra liveflow</strong><div><?= h($labelFor($rerouteExpenseTypeOptions, $frontendRerouteExpenseType, 'Ikke valgt')) ?></div></div>
              </div>
              <div class="case-note">Carrierens faktiske tilbud, om tilbuddet blev brugt, aarsag og beloeb/kvitteringer afklares nedenfor i backend.</div>
            </div>
          <?php endif; ?>
          <?php if ($hasCompletedFrontendRerouteFacts && !$isCancellationRefundContext): ?>
            <div class="case-subpanel">
              <h3>Frontend-resume</h3>
              <p class="case-muted"><?= h($isFerryCase ? 'Disse svar kommer fra completed-frontflowet. Ferry-haendelse og Art. 18-gate kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.' : ($isRailCase ? 'Disse svar kommer fra completed-frontflowet. Rail-haendelse og Art. 18-gate kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.' : 'Disse svar kommer fra completed-frontflowet. Varsel og tilbudt alternativ flyvning kommer fra incident, mens passagerens faktiske forloeb og udgiftsspor kommer fra remedies.')) ?></p>
              <div class="case-summary-grid">
                <div class="case-summary-item"><strong>Valg</strong><div><?= h($labelFor($remedyOptions, $field('remedyChoice'), 'Ikke valgt')) ?></div></div>
                <div class="case-summary-item"><strong><?= h(($isFerryCase || $isRailCase) ? 'Egen videre rejse' : 'Egen loesning') ?></strong><div><?= h($labelFor($yesNo, $field('air_self_arranged_reroute'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Ekstraudgifter</strong><div><?= h($labelFor($yesNo, $field('air_reroute_expenses_incurred'), 'Ikke oplyst')) ?></div></div>
                <div class="case-summary-item"><strong>Udgiftstype fra frontflow</strong><div><?= h($labelFor($rerouteExpenseTypeOptions, $frontendRerouteExpenseType, 'Ikke valgt')) ?></div></div>
              </div>
              <div class="case-note">Udgiftstype, beloeb, valuta, forklaring og kvittering registreres nedenfor, mens kompensationssporet vurderes separat.</div>
            </div>
          <?php endif; ?>
          <?php if ($isDelayRefundContext): ?>
            <div class="case-note">Delay-sporet bruger ikke generel ombooking her. Hvis refund-gaten er aaben, afklarer vi kun hvad der skulle refunderes, og om der var faktiske returudgifter.</div>
          <?php endif; ?>
          <form method="post" action="<?= h($buildCaseHref($activeStep)) ?>" enctype="multipart/form-data">
            <?php if ($csrfToken !== ''): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>"><?php endif; ?>
            <input type="hidden" name="active_case_step" value="<?= h($activeStep) ?>">
            <div class="case-subpanel">
              <h3>Passagerens valg</h3>
              <p class="case-muted"><?= h($isFerryCase ? 'Registrer hvad passageren faktisk gjorde efter ferry-haendelsen. Det er Art. 18-sporet og holdes adskilt fra Art. 19-kompensationsvurderingen.' : ($isRailCase ? 'Registrer hvad passageren faktisk gjorde efter rail-haendelsen. Det er Art. 18-sporet og holdes adskilt fra Art. 19-kompensationsvurderingen.' : 'Registrer hvad passageren faktisk gjorde efter haendelsen. Det er Article 8-sporet og holdes adskilt fra kompensationsvurderingen nedenfor.')) ?></p>
              <?php if ($isDelayRefundContext): ?>
                <div class="case-form-grid">
                  <div class="case-field">
                    <label for="remedyChoice">Valg fra frontendflowet</label>
                    <?php if ($lockedDelayRemedyChoice): ?>
                      <input id="remedyChoice" type="hidden" name="remedyChoice" value="<?= h($field('remedyChoice')) ?>">
                      <div class="case-note"><?= h($labelFor($delayRemedyLabelOptions, $field('remedyChoice'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="remedyChoice" name="remedyChoice"><?php foreach ($delayRemedyInputOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('remedyChoice', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="case-note"><?= h($isFerryCase ? 'Passagerens Art. 18-valg er allerede taget i frontend. Backend bruges kun til refunddetaljer og eventuelle returudgifter, hvis passageren valgte refusion.' : ($isRailCase ? 'Passagerens Art. 18-valg er allerede taget i frontend. Backend bruges kun til refunddetaljer og eventuelle returudgifter, hvis passageren valgte refusion.' : 'Passagerens Article 8-valg er allerede taget i frontend. Backend bruges kun til refunddetaljer og eventuelle returudgifter, hvis passageren valgte refusion.')) ?></div>
              <?php else: ?>
                <div class="case-form-grid">
                  <div class="case-field">
                    <label for="remedyChoice">Hvad skete der efter haendelsen?</label>
                    <?php if ($lockedRemedyChoice): ?>
                      <input id="remedyChoice" type="hidden" name="remedyChoice" value="<?= h($field('remedyChoice')) ?>">
                      <div class="case-note"><?= h($labelFor($remedyOptions, $field('remedyChoice'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="remedyChoice" name="remedyChoice"><?php foreach ($remedyOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('remedyChoice', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
            <?php if ($showBackendArticle8OfferField): ?>
              <div class="case-subpanel">
                <h3>Carrierens Article 8-tilbud</h3>
                <p class="case-muted">Registrer separat, om flyselskabet faktisk tilbloed refusion eller ombooking. Dette er airline-fakta og holdes bevidst adskilt fra passagerens eget valg ovenfor.</p>
                <div class="case-form-grid">
                  <div class="case-field">
                    <label for="air_article8_choice_offered">Tilboed flyselskabet refusion eller ombooking efter Article 8?</label>
                    <?php if ($lockedBackendArticle8Choice): ?>
                      <input id="air_article8_choice_offered" type="hidden" name="air_article8_choice_offered" value="<?= h($field('air_article8_choice_offered')) ?>">
                      <div class="case-note"><?= h($labelFor($yesNoUnknown, $field('air_article8_choice_offered'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="air_article8_choice_offered" name="air_article8_choice_offered"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('air_article8_choice_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <div class="case-subpanel<?= ($field('remedyChoice') === 'refund_return') ? '' : ' case-hidden' ?>" data-refund-panel>
              <h3>Refusion / retur</h3>
              <input type="hidden" name="air_return_to_first_departure_point" value="">
              <div class="case-form-grid">
                <div class="case-field">
                  <label for="air_refund_scope">Hvad skulle refunderes?</label>
                  <?php if ($lockedRefundScope): ?>
                    <input id="air_refund_scope" type="hidden" name="air_refund_scope" value="<?= h($refundScopeRawValue) ?>">
                    <div class="case-note"><?= h($labelFor($refundScopeOptions, $refundScopeValue, 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="air_refund_scope" name="air_refund_scope"><?php foreach ($refundScopeOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= ($refundScopeValue === (string)$value) ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
                <div class="case-field">
                  <label for="return_to_origin_expense">Var der returudgifter?</label>
                  <?php if ($lockedReturnExpense): ?>
                    <input id="return_to_origin_expense" type="hidden" name="return_to_origin_expense" value="<?= h($field('return_to_origin_expense')) ?>">
                    <div class="case-note"><?= h($labelFor($yesNo, $field('return_to_origin_expense'), 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="return_to_origin_expense" name="return_to_origin_expense"><?php foreach ($yesNo as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('return_to_origin_expense', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($isCancellationRefundContext && !$isFerryCase && !$isRailCase): ?>
                <div class="case-note">Retur til foerste afgangssted vurderes her ud fra den afbrudte <?= h($isRailCase ? 'station' : 'lufthavn') ?> <strong><?= h($rerouteOriginLabel !== '' ? $rerouteOriginLabel : 'Ikke oplyst') ?></strong> og foerste afgangssted <strong><?= h($firstDepartureLabel !== '' ? $firstDepartureLabel : 'Ikke oplyst') ?></strong>.</div>
              <?php endif; ?>
              <div class="case-note">Brug kun returudgifter, hvis passageren faktisk betalte for at komme tilbage efter refusion eller afbrudt rejse.</div>
              <div class="case-subpanel<?= $field('return_to_origin_expense') === 'yes' ? '' : ' case-hidden' ?>" data-return-origin-fields>
                <h3>Returudgifter</h3>
                <p class="case-muted">Brug dette felt, hvis passageren selv betalte returtransport eller anden noedvendig transport tilbage efter refusion.</p>
                <div class="case-file-list" data-expense-items="refund">
                  <?php foreach ($refundExpenseItems as $index => $item): ?>
                    <?php $itemReceipt = (array)($item['receipt'] ?? []); ?>
                    <div class="case-file-item" data-expense-item>
                      <div class="case-form-grid">
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_type">Udgiftstype</label>
                          <select id="air_case_refund_expense_items_<?= h((string)$index) ?>_type" name="air_case_refund_expense_items[<?= h((string)$index) ?>][type]">
                            <?php foreach ($refundExpenseTypeOptions as $value => $label): ?>
                              <option value="<?= h($value) ?>" <?= ((string)($item['type'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_amount">Beloeb</label>
                          <input id="air_case_refund_expense_items_<?= h((string)$index) ?>_amount" name="air_case_refund_expense_items[<?= h((string)$index) ?>][amount]" value="<?= h((string)($item['amount'] ?? '')) ?>" inputmode="decimal">
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_currency">Valuta</label>
                          <select id="air_case_refund_expense_items_<?= h((string)$index) ?>_currency" name="air_case_refund_expense_items[<?= h((string)$index) ?>][currency]">
                            <option value="">Vaelg</option>
                            <?php foreach ($currencyOptions as $currency): ?>
                              <option value="<?= h($currency) ?>" <?= ((string)($item['currency'] ?? '') === $currency) ? 'selected' : '' ?>><?= h($currency) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="case-field" style="grid-column: 1 / -1;">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_description">Kort beskrivelse</label>
                          <textarea id="air_case_refund_expense_items_<?= h((string)$index) ?>_description" name="air_case_refund_expense_items[<?= h((string)$index) ?>][description]"><?= h((string)($item['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_receipts_<?= h((string)$index) ?>">Kvittering</label>
                          <input id="air_case_refund_receipts_<?= h((string)$index) ?>" name="air_case_refund_receipts[<?= h((string)$index) ?>]" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="case-field">
                          <label>Eksisterende kvittering</label>
                          <?php if ($itemReceipt !== []): ?>
                            <div class="case-file-item" style="margin-top:6px;">
                              <a href="<?= h((string)($itemReceipt['path'] ?? '#')) ?>" target="_blank" rel="noopener"><?= h((string)($itemReceipt['name'] ?? 'Upload')) ?></a>
                              <div class="case-muted">Uploadet <?= h((string)($itemReceipt['uploaded_at'] ?? '')) ?></div>
                            </div>
                          <?php else: ?>
                            <div class="case-muted" style="margin-top:10px;">Ingen kvittering endnu.</div>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="case-actions">
                        <button class="case-button" type="button" data-remove-expense-item>Fjern post</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="case-actions">
                  <button class="case-button" type="button" data-add-expense-item="refund">+ Tilfoej returudgift</button>
                </div>
              </div>
            </div>
            <div class="case-subpanel<?= (!$isDelayRefundContext && $hasRerouteRemedy) ? '' : ' case-hidden' ?>" data-reroute-panel>
              <h3><?= h($isFerryCase ? 'Omlaegning / egen videre rejse' : 'Omlaegning / egen loesning') ?></h3>
              <p class="case-muted"><?= h($isFerryCase ? 'Start med passagerens faktiske forloeb og konkrete udgifter. Transportoerens Art. 18-tilbud bruges som kontekst og holdes adskilt fra Art. 19.' : 'Start med passagerens faktiske forloeb og konkrete udgifter. Carrierens alternative flyvning bruges kun som kompensationsfakta laengere nede.') ?></p>

              <div class="case-subpanel<?= $showRerouteSelfArrangedFacts ? '' : ' case-hidden' ?>" data-reroute-self-arranged-panel>
                <h3>Passagerens egen loesning</h3>
                <p class="case-muted"><?= h($isFerryCase ? 'Denne del bruges til passagerens faktiske videre rejse og holdes adskilt fra ferry-kompensationsfakta.' : 'Denne del bruges til passagerens faktiske forloeb og holdes adskilt fra carrierens kompensationsfakta ovenfor.') ?></p>
                <div class="case-form-grid<?= $showRerouteSelfArrangedFacts ? '' : ' case-hidden' ?>" data-cancellation-passenger-reroute-fields data-denied-boarding-self-arranged-fields>
                  <div class="case-field">
                    <label for="air_self_arranged_reroute"><?= h($selfArrangedRerouteLabel) ?></label>
                    <?php if ($lockedSelfArrangedReroute): ?>
                      <input id="air_self_arranged_reroute" type="hidden" name="air_self_arranged_reroute" value="<?= h($field('air_self_arranged_reroute')) ?>">
                      <div class="case-note"><?= h($labelFor($yesNo, $field('air_self_arranged_reroute'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="air_self_arranged_reroute" name="air_self_arranged_reroute"><?php foreach ($yesNo as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('air_self_arranged_reroute', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                  <?php if ($isFerryCase): ?>
                    <input type="hidden" name="air_self_arranged_reroute_reason" value="">
                  <?php else: ?>
                  <div class="case-field case-hidden" data-self-arranged-reason-field>
                    <label for="air_self_arranged_reroute_reason"><?= h($isFerryCase ? 'Hvorfor maatte passageren selv finde en videre rejse?' : 'Hvorfor maatte passageren selv finde en loesning?') ?></label>
                    <?php if ($lockedSelfArrangedReason): ?>
                      <input id="air_self_arranged_reroute_reason" type="hidden" name="air_self_arranged_reroute_reason" value="<?= h($field('air_self_arranged_reroute_reason')) ?>">
                      <div class="case-note"><?= h($labelFor($rerouteReasonOptions, $field('air_self_arranged_reroute_reason'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="air_self_arranged_reroute_reason" name="air_self_arranged_reroute_reason"><?php foreach ($rerouteReasonOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('air_self_arranged_reroute_reason', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="case-subpanel<?= $showRerouteExpenseGatePanel ? '' : ' case-hidden' ?>" data-reroute-expense-gate-panel>
                <h3>Ombookingsudgifter</h3>
                <div class="case-form-grid">
                  <div class="case-field">
                    <label for="air_reroute_expenses_incurred"><?= h($rerouteExpenseGateLabel) ?></label>
                    <?php if ($lockedRerouteExpensesIncurred): ?>
                      <input id="air_reroute_expenses_incurred" type="hidden" name="air_reroute_expenses_incurred" value="<?= h($field('air_reroute_expenses_incurred')) ?>">
                      <div class="case-note"><?= h($labelFor($yesNo, $field('air_reroute_expenses_incurred'), 'Ikke oplyst')) ?></div>
                    <?php else: ?>
                      <select id="air_reroute_expenses_incurred" name="air_reroute_expenses_incurred"><?php foreach ($yesNo as $value => $label): ?><option value="<?= h($value) ?>" <?= ($field('air_reroute_expenses_incurred') === $value) ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="case-subpanel<?= $showRerouteExpensePanel ? '' : ' case-hidden' ?>" data-reroute-expense-panel>
                <h3>Ombookingsudgifter</h3>
                <p class="case-muted"><?= h($isFerryCase ? 'Registrer ny faergebillet, transfer eller anden noedvendig videre transport. Maaltider og hotel hoerer til under assistance.' : 'Registrer ny billet, transfer eller anden noedvendig transport. Upload kvittering direkte paa den relevante post.') ?></p>
                <div class="case-file-list" data-expense-items="reroute">
                  <?php foreach ($refundExpenseItems as $index => $item): ?>
                    <?php $itemReceipt = (array)($item['receipt'] ?? []); ?>
                    <div class="case-file-item" data-expense-item>
                      <div class="case-form-grid">
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_type">Udgiftstype</label>
                          <select id="air_case_refund_expense_items_<?= h((string)$index) ?>_type" name="air_case_refund_expense_items[<?= h((string)$index) ?>][type]">
                            <?php foreach ($rerouteExpenseTypeOptions as $value => $label): ?>
                              <option value="<?= h($value) ?>" <?= ((string)($item['type'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_amount">Beloeb</label>
                          <input id="air_case_refund_expense_items_<?= h((string)$index) ?>_amount" name="air_case_refund_expense_items[<?= h((string)$index) ?>][amount]" value="<?= h((string)($item['amount'] ?? '')) ?>" inputmode="decimal">
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_currency">Valuta</label>
                          <select id="air_case_refund_expense_items_<?= h((string)$index) ?>_currency" name="air_case_refund_expense_items[<?= h((string)$index) ?>][currency]">
                            <option value="">Vaelg</option>
                            <?php foreach ($currencyOptions as $currency): ?>
                              <option value="<?= h($currency) ?>" <?= ((string)($item['currency'] ?? '') === $currency) ? 'selected' : '' ?>><?= h($currency) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="case-field" style="grid-column: 1 / -1;">
                          <label for="air_case_refund_expense_items_<?= h((string)$index) ?>_description">Kort beskrivelse</label>
                          <textarea id="air_case_refund_expense_items_<?= h((string)$index) ?>_description" name="air_case_refund_expense_items[<?= h((string)$index) ?>][description]"><?= h((string)($item['description'] ?? '')) ?></textarea>
                        </div>
                        <div class="case-field">
                          <label for="air_case_refund_receipts_<?= h((string)$index) ?>">Kvittering</label>
                          <input id="air_case_refund_receipts_<?= h((string)$index) ?>" name="air_case_refund_receipts[<?= h((string)$index) ?>]" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="case-field">
                          <label>Eksisterende kvittering</label>
                          <?php if ($itemReceipt !== []): ?>
                            <div class="case-file-item" style="margin-top:6px;">
                              <a href="<?= h((string)($itemReceipt['path'] ?? '#')) ?>" target="_blank" rel="noopener"><?= h((string)($itemReceipt['name'] ?? 'Upload')) ?></a>
                              <div class="case-muted">Uploadet <?= h((string)($itemReceipt['uploaded_at'] ?? '')) ?></div>
                            </div>
                          <?php else: ?>
                            <div class="case-muted" style="margin-top:10px;">Ingen kvittering endnu.</div>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="case-actions">
                        <button class="case-button" type="button" data-remove-expense-item>Fjern post</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="case-actions">
                  <button class="case-button" type="button" data-add-expense-item="reroute">+ Tilfoej ombookingsudgift</button>
                </div>
              </div>

              <?php if ($isCancellationRefundContext && !$isFerryCase): ?>
                <div class="case-subpanel">
                  <h3>Carrierens tilbud fra incident (Article 5 / 7(2))</h3>
                  <p class="case-muted"><?= h($isOngoingCase
                    ? 'Incident har allerede afklaret varsel og om flyselskabet tilbloed en alternativ flyvning. Her bruger vi de samme fakta som kompensationskontekst og foelger kun op med de backend-detaljer, som liveflowet ikke tog.'
                    : 'Incident har allerede afklaret varsel og om flyselskabet tilbloed en alternativ flyvning. Her bruger vi de samme fakta som kompensationskontekst og foelger kun op med de backend-detaljer, som completed-flowet ikke tog.'
                  ) ?></p>
                  <div class="case-form-grid">
                    <div class="case-field">
                      <label for="reroute_offered">Tilboed flyselskabet en alternativ flyvning?</label>
                      <?php if ($lockedRerouteOffered): ?>
                        <input id="reroute_offered" type="hidden" name="reroute_offered" value="<?= h($field('reroute_offered')) ?>">
                        <div class="case-note"><?= h($labelFor($yesNoUnknown, $field('reroute_offered'), 'Ikke oplyst')) ?></div>
                      <?php else: ?>
                        <select id="reroute_offered" name="reroute_offered"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      <?php endif; ?>
                    </div>
                    <div class="case-field<?= $field('reroute_offered') === 'yes' ? '' : ' case-hidden' ?>" data-reroute-used-panel>
                      <label for="reroute_used_or_accepted"><?= h($rerouteUsedOrAcceptedLabel) ?></label>
                      <?php if ($lockedRerouteUsedOrAccepted): ?>
                        <input id="reroute_used_or_accepted" type="hidden" name="reroute_used_or_accepted" value="<?= h($field('reroute_used_or_accepted')) ?>">
                        <div class="case-note"><?= h($labelFor($yesNoUnknown, $field('reroute_used_or_accepted'), 'Ikke oplyst')) ?></div>
                      <?php else: ?>
                        <select id="reroute_used_or_accepted" name="reroute_used_or_accepted"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_used_or_accepted', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="case-note case-hidden" data-cancellation-self-arranged-note>Carrierens tilbud bruges kun som Article 5 / Article 7(2)-fakta. Passagerens egen loesning og udgifter registreres separat ovenfor.</div>
                </div>
              <?php endif; ?>

              <?php if ($isDeniedBoardingContext): ?>
                <div class="case-subpanel<?= $deniedBoardingDirectExpensePath ? ' case-hidden' : '' ?>" data-denied-boarding-carrier-panel>
                  <h3><?= h($isOngoingDeniedBoardingContext ? 'Carrierens tilbud (backend-opfoelgning)' : 'Carrierens tilbud (Article 7(2))') ?></h3>
                  <?php if ($isOngoingDeniedBoardingContext): ?>
                    <p class="case-muted">Frontend spurgte kun til passagerens aktuelle valg og udgifter. Afklar derfor carrierens faktiske tilbud her.</p>
                  <?php else: ?>
                    <p class="case-muted">Ved boardingafvisning findes ingen Article 5-gate. Feltet bruges kun til mulig 50% reduktion efter Article 7(2) og maa ikke blokere udgiftssporet.</p>
                  <?php endif; ?>
                  <div class="case-form-grid">
                    <div class="case-field">
                      <label for="reroute_offered">Tilboed flyselskabet en alternativ flyvning?</label>
                      <?php if ($lockedRerouteOffered): ?>
                        <input id="reroute_offered" type="hidden" name="reroute_offered" value="<?= h($field('reroute_offered')) ?>">
                        <div class="case-note"><?= h($labelFor($yesNoUnknown, $field('reroute_offered'), 'Ikke oplyst')) ?></div>
                      <?php else: ?>
                        <select id="reroute_offered" name="reroute_offered"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      <?php endif; ?>
                    </div>
                    <div class="case-field<?= $field('reroute_offered') === 'yes' ? '' : ' case-hidden' ?>" data-reroute-used-panel>
                      <label for="reroute_used_or_accepted">Brugte eller accepterede passageren den alternative flyvning?</label>
                      <?php if ($lockedRerouteUsedOrAccepted): ?>
                        <input id="reroute_used_or_accepted" type="hidden" name="reroute_used_or_accepted" value="<?= h($field('reroute_used_or_accepted')) ?>">
                        <div class="case-note"><?= h($labelFor($yesNoUnknown, $field('reroute_used_or_accepted'), 'Ikke oplyst')) ?></div>
                      <?php else: ?>
                        <select id="reroute_used_or_accepted" name="reroute_used_or_accepted"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_used_or_accepted', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="case-note<?= ($field('reroute_offered') === 'yes' && $field('reroute_used_or_accepted') === 'yes') ? '' : ' case-hidden' ?>" data-denied-boarding-used-note>Carrierens alternative flyvning bruges kun til den eventuelle Article 7(2)-reduktion. Udgiftssporet ovenfor er stadig aabent for konkrete ombookings- eller transferudgifter.</div>
                  <div class="case-note<?= (($field('reroute_offered') === 'no') || ($field('reroute_offered') === 'yes' && $field('reroute_used_or_accepted') === 'no')) ? '' : ' case-hidden' ?>" data-denied-boarding-self-arranged-note>Registrer ovenfor, om passageren selv maatte finde en loesning og havde udgifter som foelge heraf. Dette er adskilt fra Article 7(2)-vurderingen.</div>
                </div>
              <?php endif; ?>
            </div>
            <?php if ($isDeniedBoardingContext): ?>
              <div class="case-subpanel<?= $deniedBoardingDirectExpensePath ? ' case-hidden' : '' ?>" data-denied-boarding-comp-panel>
                <h3>Kompensationsvurdering ved boardingafvisning</h3>
                <p class="case-muted">Denne blok bruges kun til Article 7 og eventuel 50% reduktion efter Article 7(2). Der er ingen Article 5-gate ved boardingafvisning.</p>
                <div class="case-summary-grid">
                  <div class="case-summary-item"><strong>Boardingafvisning</strong><div><?= $isVoluntaryDeniedBoarding ? 'Frivillig' : 'Mod passagerens vilje' ?></div></div>
                  <div class="case-summary-item"><strong>Article 7-status</strong><div><?= h($labelFor($article7EligibilityLabels, $article7EligibilityStatus, 'Foreloebig vurdering mangler')) ?></div></div>
                  <div class="case-summary-item"><strong>Grundbeloeb</strong><div><?= $article7BaseAmountEur > 0 ? h((string)$article7BaseAmountEur . ' EUR') : 'Ikke beregnet' ?></div></div>
                  <div class="case-summary-item"><strong>Aktuelt beloeb</strong><div><?= $article7FinalAmountEur > 0 ? h((string)$article7FinalAmountEur . ' EUR') : 'Ikke beregnet' ?></div></div>
                </div>
                <?php if ($isVoluntaryDeniedBoarding): ?>
                  <div class="case-note">Boardingafvisningen var registreret som frivillig i frontflowet. Derfor vises Article 7(2)-reduktion ikke her.</div>
                <?php endif; ?>
                <div class="case-note<?= $showDeniedBoardingCompensationDetails ? ' case-hidden' : '' ?>" data-denied-boarding-details-note>Vaelg foerst, om flyselskabet tilbloed en alternativ flyvning, og om passageren brugte den. Foerst derefter bliver en eventuel 50% reduktion relevant.</div>
                <div class="<?= $showDeniedBoardingCompensationDetails ? '' : ' case-hidden' ?>" data-denied-boarding-details-body>
                  <div class="case-note">Boardingafvisning bruger ikke Article 5-vinduer. Her afklares kun, om flyselskabet tilbloed en alternativ flyvning, om passageren brugte den, og om ankomsten holdt sig inden for Article 7(2)-graensen.</div>
                  <div class="case-note<?= !$deniedBoardingReductionRelevant ? '' : ' case-hidden' ?>" data-denied-boarding-art7-note>50% reduktion er ikke relevant her, fordi standardkompensationen ikke staar tilbage i den nuvaerende vurdering.</div>
                  <div class="case-subpanel<?= $deniedBoardingReductionRelevant ? '' : ' case-hidden' ?>" data-denied-boarding-art7-panel>
                    <h3>Eventuel 50% reduktion</h3>
                    <p class="case-muted">Kun relevant ved ufrivillig boardingafvisning, hvis passageren faktisk brugte eller accepterede den tilbudte alternative flyvning.</p>
                    <div class="case-form-grid">
                      <div class="case-field<?= ($field('reroute_offered') === 'yes' && $field('reroute_used_or_accepted') === 'yes') ? '' : ' case-hidden' ?>" data-denied-boarding-reroute-delay-field>
                        <label for="reroute_arrival_delay_minutes">Forsinkelse ved endelig ankomst paa den alternative flyvning (minutter)</label>
                        <input id="reroute_arrival_delay_minutes" name="reroute_arrival_delay_minutes" value="<?= h($field('reroute_arrival_delay_minutes')) ?>" inputmode="numeric">
                      </div>
                    </div>
                    <?php if ($article7ReductionStatus !== ''): ?>
                      <div class="case-note">Aktuel reduktionsstatus: <strong><?= h($labelFor($article7ReductionLabels, $article7ReductionStatus, $article7ReductionStatus)) ?></strong></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($isCancellationRefundContext && !$isFerryCase && !$isRailCase): ?>
              <div class="case-subpanel" data-cancellation-comp-panel>
                <h3>Kompensationsvurdering ved aflysning</h3>
                <p class="case-muted">Denne blok bruges til Article 5 og eventuel Article 7(2). Airline-fakta og passagerens valg holdes bevidst adskilt, saa de ikke blandes sammen med selve ombookingsforloebet.</p>
                <div class="case-summary-grid">
                  <div class="case-summary-item"><strong>Varsel om aflysning</strong><div><?= h($labelFor($noticeBandOptions, $field('cancellation_notice_band'))) ?></div></div>
                  <div class="case-summary-item"><strong>Article 7-status</strong><div><?= h($labelFor($article7EligibilityLabels, $article7EligibilityStatus, 'Foreloebig vurdering mangler')) ?></div></div>
                  <div class="case-summary-item"><strong>Grundbeloeb</strong><div><?= $article7BaseAmountEur > 0 ? h((string)$article7BaseAmountEur . ' EUR') : 'Ikke beregnet' ?></div></div>
                  <div class="case-summary-item"><strong>Aktuelt beloeb</strong><div><?= $article7FinalAmountEur > 0 ? h((string)$article7FinalAmountEur . ' EUR') : 'Ikke beregnet' ?></div></div>
                </div>
                <?php if ($field('remedyChoice') === 'refund_return'): ?>
                  <div class="case-note">Passageren har valgt refusion. Derfor holdes denne blok som kompensationskontekst og read-only oversigt, mens de detaljerede ombookingsvinduer normalt ikke er noedvendige i samme omgang.</div>
                <?php endif; ?>
                <div class="case-note<?= $showCancellationCompensationDetails ? ' case-hidden' : '' ?>" data-cancellation-details-note>Vaelg foerst om passageren gik videre med omlaegning. Ved ren refusion skjules de mere detaljerede Article 5-/7(2)-spoergsmaal her, mens oversigten ovenfor stadig bevares som sagskontekst.</div>
                <div class="<?= $showCancellationCompensationDetails ? '' : ' case-hidden' ?>" data-cancellation-details-body>
                  <input id="cancellation_notice_band" type="hidden" name="cancellation_notice_band" value="<?= h($field('cancellation_notice_band')) ?>">
                  <div class="case-note">Varsel om aflysning er allerede registreret i frontend som <strong><?= h($labelFor($noticeBandOptions, $field('cancellation_notice_band'), 'Ikke oplyst')) ?></strong>. Derfor bruges feltet her kun som read-only kontekst for Article 5-vinduerne.</div>
                  <div class="case-form-grid">
                    <div class="case-field case-hidden" aria-hidden="true">
                      <label for="cancellation_notice_band">Hvornår fik passageren besked om aflysningen?</label>
                      <select id="cancellation_notice_band_display" disabled><?php foreach ($noticeBandOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('cancellation_notice_band', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                    </div>
                  </div>
                  <div class="case-subpanel<?= ($field('reroute_offered') === 'yes' && !empty($cancellationWindowConfig['showWindows'])) ? '' : ' case-hidden' ?>" data-cancellation-art5-window-panel>
                    <h3>Article 5-vinduer</h3>
                    <p class="case-muted"><?= h((string)$cancellationWindowConfig['note']) ?></p>
                    <div class="case-form-grid">
                      <div class="case-field">
                        <label for="reroute_departure_band"><?= h((string)$cancellationWindowConfig['departure']) ?></label>
                        <select id="reroute_departure_band" name="reroute_departure_band"><?php foreach ($windowOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_departure_band', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      </div>
                      <div class="case-field">
                        <label for="reroute_arrival_band"><?= h((string)$cancellationWindowConfig['arrival']) ?></label>
                        <select id="reroute_arrival_band" name="reroute_arrival_band"><?php foreach ($windowOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_arrival_band', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                      </div>
                    </div>
                  </div>
                  <div class="case-note<?= ($field('reroute_offered') === 'yes' && !$cancellationArt7ReductionRelevant) ? '' : ' case-hidden' ?>" data-cancellation-art7-note>50% reduktion er ikke relevant her, fordi kompensation ikke laengere staar tilbage efter Article 5-vurderingen.</div>
                  <div class="case-subpanel<?= ($field('reroute_offered') === 'yes' && $cancellationArt7ReductionRelevant) ? '' : ' case-hidden' ?>" data-cancellation-art7-panel>
                    <h3>Eventuel 50% reduktion</h3>
                    <p class="case-muted">Denne del bruges kun, hvis kompensation stadig bestaar efter Article 5, og passageren faktisk brugte eller accepterede den alternative flyvning.</p>
                    <div class="case-form-grid">
                      <div class="case-field<?= $field('reroute_used_or_accepted') === 'yes' ? '' : ' case-hidden' ?>" data-cancellation-reroute-delay-field>
                        <label for="reroute_arrival_delay_minutes">Forsinkelse ved endelig ankomst paa den alternative flyvning (minutter)</label>
                        <input id="reroute_arrival_delay_minutes" name="reroute_arrival_delay_minutes" value="<?= h($field('reroute_arrival_delay_minutes')) ?>" inputmode="numeric">
                      </div>
                    </div>
                    <?php if ($article7ReductionStatus !== ''): ?>
                      <div class="case-note">Aktuel reduktionsstatus: <strong><?= h($labelFor($article7ReductionLabels, $article7ReductionStatus, $article7ReductionStatus)) ?></strong></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
            <div class="case-step-nav"><?php if ($prevStep !== ''): ?><a class="case-button" href="<?= h($buildCaseHref($prevStep)) ?>">Forrige trin</a><?php else: ?><span></span><?php endif; ?><div style="display:flex; gap:10px;"><button class="case-button" type="submit">Gem</button><?php if ($nextStep !== ''): ?><button class="case-button primary" type="submit" name="goto_step" value="<?= h($nextStep) ?>">Gem og naeste trin</button><?php endif; ?></div></div>
          </form>
        <?php elseif ($activeStep === 'documents'): ?>
          <p>Upload billet her i backend. Systemet holder den op mod oplysningerne fra frontflowet og viser en teknisk kontraktvurdering, saa du slipper for at gætte senere. Kvitteringer ligger fortsat sammen med udgifter i <strong>Assistance</strong>.</p>
          <form method="post" action="<?= h($buildCaseHref($activeStep)) ?>" enctype="multipart/form-data">
            <?php if ($csrfToken !== ''): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>"><?php endif; ?>
            <input type="hidden" name="active_case_step" value="<?= h($activeStep) ?>">
            <input type="hidden" name="air_backend_needs_documents" value="<?= $ticketFiles !== [] ? 'yes' : 'no' ?>">
            <div class="case-subpanel">
              <h3>Billet</h3>
              <div class="case-field"><label for="air_backend_ticket_upload">Upload billet</label><input id="air_backend_ticket_upload" name="air_backend_ticket_upload" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
              <?php if ($ticketFiles !== []): ?><div class="case-file-list"><?php foreach ($ticketFiles as $file): ?><div class="case-file-item"><a href="<?= h((string)($file['path'] ?? '#')) ?>" target="_blank" rel="noopener"><?= h((string)($file['name'] ?? 'Upload')) ?></a><div class="case-muted">Uploadet <?= h((string)($file['uploaded_at'] ?? '')) ?></div></div><?php endforeach; ?></div><?php endif; ?>
            </div>
            <?php if ($ticketAnalysis !== []): ?>
              <?php
                $analysisNeedsReview = (string)($ticketAnalysis['needs_manual_review'] ?? 'yes') === 'yes';
                $analysisSummary = (string)($ticketAnalysis['summary'] ?? '');
                $analysisExtracted = (array)($ticketAnalysis['extracted'] ?? []);
                $analysisChecks = (array)($ticketAnalysis['match_checks'] ?? []);
                $analysisContract = (array)($ticketAnalysis['contract_summary'] ?? []);
                $analysisMode = strtolower(trim((string)($ticketAnalysis['transport_mode'] ?? ($isFerryCase ? 'ferry' : ($isRailCase ? 'rail' : 'air')))));
                $analysisIsFerry = $analysisMode === 'ferry';
                $analysisIsRail = $analysisMode === 'rail';
                $analysisBadgeClass = $analysisNeedsReview ? 'mismatch' : 'match';
                $analysisBadgeText = $analysisNeedsReview ? 'Manuel kontrol' : 'Matcher godt';
              ?>
              <div class="case-subpanel">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
                  <h3>Billetverificering</h3>
                  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <span class="case-badge <?= h($analysisBadgeClass) ?>"><?= h($analysisBadgeText) ?></span>
                    <?php if ($adminDeskHref !== ''): ?>
                      <a class="case-button" href="<?= h($adminDeskHref) ?>">Aabn admin cockpit</a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($analysisSummary !== ''): ?><p class="case-muted"><?= h($analysisSummary) ?></p><?php endif; ?>
                <div class="case-summary-grid">
                  <div class="case-summary-item"><strong>Fundet rute</strong><div><?= h((trim(implode(' -> ', array_filter([(string)($analysisExtracted['dep_station'] ?? ''), (string)($analysisExtracted['arr_station'] ?? '')]))) ?: 'Ikke udtrukket')) ?></div></div>
                  <div class="case-summary-item"><strong>Fundet dato</strong><div><?= h((string)($analysisExtracted['dep_date'] ?? '') !== '' ? (string)$analysisExtracted['dep_date'] : 'Ikke udtrukket') ?></div></div>
                  <?php if ($analysisIsFerry): ?>
                    <div class="case-summary-item"><strong>Fundet afgang</strong><div><?= h((string)($analysisExtracted['dep_time'] ?? '') !== '' ? (string)$analysisExtracted['dep_time'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Fundet fartoj</strong><div><?= h((string)($analysisExtracted['vessel_name'] ?? '') !== '' ? (string)$analysisExtracted['vessel_name'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Operator</strong><div><?= h((string)($analysisExtracted['operator'] ?? '') !== '' ? (string)$analysisExtracted['operator'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Bookingreference</strong><div><?= h((string)($analysisExtracted['booking_reference'] ?? ($analysisExtracted['ticket_no'] ?? '')) !== '' ? (string)($analysisExtracted['booking_reference'] ?? ($analysisExtracted['ticket_no'] ?? '')) : 'Ikke udtrukket') ?></div></div>
                  <?php elseif ($analysisIsRail): ?>
                    <div class="case-summary-item"><strong>Fundet afgang</strong><div><?= h((string)($analysisExtracted['dep_time'] ?? '') !== '' ? (string)$analysisExtracted['dep_time'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Fundet tog / linje</strong><div><?= h((string)($analysisExtracted['train_number'] ?? ($analysisExtracted['service_code'] ?? '')) !== '' ? (string)($analysisExtracted['train_number'] ?? ($analysisExtracted['service_code'] ?? '')) : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Operator</strong><div><?= h((string)($analysisExtracted['operator'] ?? '') !== '' ? (string)$analysisExtracted['operator'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Bookingreference / billetnr</strong><div><?= h((string)($analysisExtracted['booking_reference'] ?? ($analysisExtracted['ticket_no'] ?? '')) !== '' ? (string)($analysisExtracted['booking_reference'] ?? ($analysisExtracted['ticket_no'] ?? '')) : 'Ikke udtrukket') ?></div></div>
                  <?php else: ?>
                    <div class="case-summary-item"><strong>Fundet flynummer</strong><div><?= h((string)($analysisExtracted['flight_number'] ?? '') !== '' ? (string)$analysisExtracted['flight_number'] : 'Ikke udtrukket') ?></div></div>
                    <div class="case-summary-item"><strong>Carrier</strong><div><?= h((string)($analysisExtracted['operator'] ?? '') !== '' ? (string)$analysisExtracted['operator'] : 'Ikke udtrukket') ?></div></div>
                  <?php endif; ?>
                </div>
                <?php if ($analysisChecks !== []): ?>
                  <div class="case-file-list" style="margin-top:12px;">
                    <?php foreach ($analysisChecks as $check): ?>
                      <?php $status = (string)($check['status'] ?? 'unknown'); ?>
                      <div class="case-file-item">
                        <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                          <div>
                            <strong><?= h((string)($check['label'] ?? 'Check')) ?></strong>
                            <div class="case-muted">Frontflow: <?= h((string)($check['current'] ?? 'Ikke oplyst')) ?></div>
                            <div class="case-muted">Billet: <?= h((string)($check['detected'] ?? 'Ikke fundet')) ?></div>
                          </div>
                          <span class="case-badge <?= h($status) ?>">
                            <?= h(match ($status) {
                                'match' => 'Matcher',
                                'partial' => 'Delvist match',
                                'mismatch' => 'Afviger',
                                default => 'Uafklaret',
                            }) ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="case-summary-grid" style="margin-top:12px;">
                  <div class="case-summary-item"><strong>Kontraktstruktur</strong><div><?= h((string)($analysisContract['topology'] ?? '') !== '' ? (string)$analysisContract['topology'] : 'Ikke afklaret') ?></div></div>
                  <div class="case-summary-item"><strong><?= h($analysisIsFerry ? 'Scope' : ($analysisIsRail ? 'Ticketscope' : 'Forbindelsestype')) ?></strong><div><?= h((string)($analysisContract[$analysisIsFerry ? 'scope' : ($analysisIsRail ? 'scope' : 'connection_type')] ?? ($analysisContract['connection_type'] ?? '')) !== '' ? (string)($analysisContract[$analysisIsFerry ? 'scope' : ($analysisIsRail ? 'scope' : 'connection_type')] ?? ($analysisContract['connection_type'] ?? '')) : 'Ikke afklaret') ?></div></div>
                  <div class="case-summary-item"><strong>Beslutningsgrundlag</strong><div><?= h((string)($analysisContract['decision_basis'] ?? '') !== '' ? (string)$analysisContract['decision_basis'] : 'Ikke afklaret') ?></div></div>
                  <div class="case-summary-item"><strong>Manuel kontrol</strong><div><?= h((string)($analysisContract['manual_review_required'] ?? 'no') === 'yes' ? 'Ja' : 'Nej') ?></div></div>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($isAdminCaseView): ?>
            <div class="case-subpanel">
              <?php if ($isFerryCase): ?>
                <h3>Jurist-override: ferry-rute og haendelsesfakta</h3>
                <p class="case-muted">Brug kun denne del, hvis billetverificering, bookingdokumenter eller manuel juridisk vurdering viser, at ferry-fakta skal korrigeres.</p>
                <div class="case-form-grid">
                  <div class="case-field"><label for="dep_station">Afgangshavn / terminal</label><input id="dep_station" name="dep_station" value="<?= h($field('dep_station')) ?>"></div>
                  <div class="case-field"><label for="arr_station">Ankomsthavn / terminal</label><input id="arr_station" name="arr_station" value="<?= h($field('arr_station')) ?>"></div>
                  <div class="case-field"><label for="dep_date">Rejsedato</label><input id="dep_date" name="dep_date" value="<?= h($field('dep_date')) ?>" placeholder="YYYY-MM-DD eller DD-MM-YYYY"></div>
                  <div class="case-field"><label for="dep_time">Planlagt afgangstid</label><input id="dep_time" name="dep_time" value="<?= h($field('dep_time')) ?>" placeholder="HH:MM"></div>
                  <div class="case-field"><label for="arr_time">Planlagt ankomsttid</label><input id="arr_time" name="arr_time" value="<?= h($field('arr_time')) ?>" placeholder="HH:MM"></div>
                  <div class="case-field"><label for="operator">Operator</label><input id="operator" name="operator" value="<?= h($field('operator')) ?>"></div>
                  <div class="case-field"><label for="ferry_vessel_name">Fartoj</label><input id="ferry_vessel_name" name="ferry_vessel_name" value="<?= h($field('ferry_vessel_name')) ?>"></div>
                  <div class="case-field"><label for="ticket_no">Billetnummer</label><input id="ticket_no" name="ticket_no" value="<?= h($field('ticket_no')) ?>"></div>
                  <div class="case-field"><label for="booking_reference">Bookingreference</label><input id="booking_reference" name="booking_reference" value="<?= h($field('booking_reference')) ?>"></div>
                  <div class="case-field"><label for="incident_main">Haendelsestype</label><select id="incident_main" name="incident_main"><?php foreach ($incidentOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('incident_main', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                </div>
              <?php elseif ($isRailCase): ?>
                <h3>Jurist-override: rail-rute og haendelsesfakta</h3>
                <p class="case-muted">Brug kun denne del, hvis billetverificering, bookingdokumenter eller manuel juridisk vurdering viser, at rail-fakta skal korrigeres.</p>
                <div class="case-form-grid">
                  <div class="case-field"><label for="dep_station">Afgangsstation</label><input id="dep_station" name="dep_station" value="<?= h($field('dep_station')) ?>"></div>
                  <div class="case-field"><label for="arr_station">Ankomststation</label><input id="arr_station" name="arr_station" value="<?= h($field('arr_station')) ?>"></div>
                  <div class="case-field"><label for="dep_date">Rejsedato</label><input id="dep_date" name="dep_date" value="<?= h($field('dep_date')) ?>" placeholder="YYYY-MM-DD eller DD-MM-YYYY"></div>
                  <div class="case-field"><label for="dep_time">Planlagt afgangstid</label><input id="dep_time" name="dep_time" value="<?= h($field('dep_time')) ?>" placeholder="HH:MM"></div>
                  <div class="case-field"><label for="arr_time">Planlagt ankomsttid</label><input id="arr_time" name="arr_time" value="<?= h($field('arr_time')) ?>" placeholder="HH:MM"></div>
                  <div class="case-field"><label for="train_number">Tognummer / linje</label><input id="train_number" name="train_number" value="<?= h($field('train_number')) ?>"></div>
                  <div class="case-field"><label for="operator">Operator</label><input id="operator" name="operator" value="<?= h($field('operator')) ?>"></div>
                  <div class="case-field"><label for="ticket_no">Billetnummer</label><input id="ticket_no" name="ticket_no" value="<?= h($field('ticket_no')) ?>"></div>
                  <div class="case-field"><label for="booking_reference">Bookingreference</label><input id="booking_reference" name="booking_reference" value="<?= h($field('booking_reference')) ?>"></div>
                  <div class="case-field"><label for="incident_main">Haendelsestype</label><select id="incident_main" name="incident_main"><?php foreach ($incidentOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('incident_main', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                </div>
              <?php else: ?>
                <h3>Jurist-override: rute og haendelsesfakta</h3>
                <p class="case-muted">Brug kun denne del, hvis billetverificering, dokumentation eller manuel juridisk vurdering viser, at frontflowets fakta skal korrigeres.</p>
                <div class="case-form-grid">
                  <div class="case-field case-airport-autocomplete"><label for="dep_station">Afgangslufthavn</label><input id="dep_station" name="dep_station" value="<?= h($field('dep_station')) ?>" autocomplete="off" data-airport-autocomplete="single"></div>
                  <div class="case-field case-airport-autocomplete"><label for="arr_station">Ankomstlufthavn</label><input id="arr_station" name="arr_station" value="<?= h($field('arr_station')) ?>" autocomplete="off" data-airport-autocomplete="single"></div>
                  <div class="case-field"><label for="dep_date">Rejsedato</label><input id="dep_date" name="dep_date" value="<?= h($field('dep_date')) ?>" placeholder="YYYY-MM-DD eller DD-MM-YYYY"></div>
                  <div class="case-field"><label for="flight_number">Flynummer</label><input id="flight_number" name="flight_number" value="<?= h($field('flight_number')) ?>"></div>
                  <div class="case-field"><label for="operator">Carrier / operator</label><input id="operator" name="operator" value="<?= h($field('operator')) ?>"></div>
                  <div class="case-field"><label for="operating_carrier">Operating carrier</label><input id="operating_carrier" name="operating_carrier" value="<?= h($field('operating_carrier')) ?>"></div>
                  <div class="case-field"><label for="marketing_carrier">Marketing carrier</label><input id="marketing_carrier" name="marketing_carrier" value="<?= h($field('marketing_carrier')) ?>"></div>
                  <div class="case-field"><label for="incident_main">Haendelsestype</label><select id="incident_main" name="incident_main"><?php foreach ($incidentOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('incident_main', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="air_route_type">Rejsetype</label><select id="air_route_type" name="air_route_type"><?php foreach ($airRouteTypeOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('air_route_type', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="air_connection_type">Forbindelsestype</label><select id="air_connection_type" name="air_connection_type"><?php foreach ($airConnectionTypeOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('air_connection_type', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field case-airport-autocomplete" style="grid-column: 1 / -1;"><label for="air_stopover_airports">Mellemlanding(er) i raekkefolge</label><textarea id="air_stopover_airports" name="air_stopover_airports" autocomplete="off" data-airport-autocomplete="multi"><?= h($field('air_stopover_airports')) ?></textarea></div>
                  <div class="case-field"><label for="protected_connection_missed">Mistet protected connection?</label><select id="protected_connection_missed" name="protected_connection_missed"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('protected_connection_missed', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="voluntary_denied_boarding">Boardingafvisning frivillig?</label><select id="voluntary_denied_boarding" name="voluntary_denied_boarding"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('voluntary_denied_boarding', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="cancellation_notice_band_edit">Varsel om aflysning</label><select id="cancellation_notice_band_edit" name="cancellation_notice_band"><?php foreach ($noticeBandOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('cancellation_notice_band', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="reroute_offered">Tilboed flyselskabet alternativ flyvning?</label><select id="reroute_offered" name="reroute_offered"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('reroute_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="case-step-nav"><?php if ($prevStep !== ''): ?><a class="case-button" href="<?= h($buildCaseHref($prevStep)) ?>">Forrige trin</a><?php else: ?><span></span><?php endif; ?><div style="display:flex; gap:10px;"><button class="case-button" type="submit">Gem</button><?php if ($nextStep !== ''): ?><button class="case-button primary" type="submit" name="goto_step" value="<?= h($nextStep) ?>">Gem og naeste trin</button><?php endif; ?></div></div>
          </form>
        <?php elseif ($activeStep === 'support'): ?>
          <?php
            $lockedMealOffered = $field('meal_offered') !== '';
            $lockedSupportOvernight = $supportOvernightValue !== '';
            $lockedHotelOffered = $field('hotel_offered') !== '';
            $lockedHotelTransportIncluded = $field('assistance_hotel_transport_included') !== '';
            $hasRegisteredCareExpense = (int)($careExpenseSummary['count'] ?? 0) > 0;
            $mealExpensePotential = $field('meal_offered') !== 'yes';
            $hotelExpensePotential = true;
            $hotelTerminalEligible = !$isFerryCase || $ferryDepartureFromTerminal !== false;
            if (!$hotelTerminalEligible) {
                $hotelExpensePotential = false;
            } elseif ($supportOvernightValue === 'no') {
                $hotelExpensePotential = false;
            } elseif ($supportOvernightValue === 'yes') {
                $hotelExpensePotential = $field('hotel_offered') !== 'yes'
                    || $field('assistance_hotel_transport_included') === 'no';
            }
            $showCareExpensePrimary = (!$isAirCase && !$isFerryCase)
                || $mealExpensePotential
                || $hotelExpensePotential
                || $hasRegisteredCareExpense;
            $careExpenseOverrideOnly = ($isAirCase || $isFerryCase) && !$showCareExpensePrimary;
            $yesNoIrrelevant = ['' => 'Vaelg', 'yes' => 'Ja', 'no' => 'Nej', 'irrelevant' => 'Ikke relevant'];
            $yesNoUnknownIrrelevant = ['' => 'Vaelg', 'yes' => 'Ja', 'no' => 'Nej', 'unknown' => 'Ved ikke', 'irrelevant' => 'Ikke relevant'];
            $supportOvernightFieldId = $isAirCase ? 'air_next_day_departure' : 'overnight_needed';
            $supportOvernightLabel = $isAirCase
              ? 'Var den nye forventede afgang foerst dagen efter den planlagte afgang?'
              : 'Var overnatning noedvendig, fordi ny afgang foerst laa dagen efter?';
          ?>
          <p><?= h($supportSummary) ?></p>
          <form method="post" action="<?= h($buildCaseHref($activeStep)) ?>" enctype="multipart/form-data">
            <?php if ($csrfToken !== ''): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>"><?php endif; ?>
            <input type="hidden" name="active_case_step" value="<?= h($activeStep) ?>">
            <div class="case-subpanel">
              <h3>Maaltider / forfriskninger</h3>
              <div class="case-form-grid">
                <div class="case-field">
                  <label for="meal_offered">Blev maaltider / forfriskninger tilbudt?</label>
                  <?php if ($lockedMealOffered): ?>
                    <input id="meal_offered" type="hidden" name="meal_offered" value="<?= h($field('meal_offered')) ?>">
                    <div class="case-note"><?= h($labelFor($yesNo, $field('meal_offered'), 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="meal_offered" name="meal_offered"><?php foreach ($yesNo as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('meal_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
              </div>
              <div class="case-note<?= $field('meal_offered') === 'no' ? '' : ' case-hidden' ?>" data-meal-fields>Hvis passageren selv betalte maaltider, saa registreres de som expense-poster herunder med typen <strong>Maaltider / forfriskninger</strong>.</div>
            </div>

            <div class="case-subpanel">
              <h3>Hotel / indkvartering</h3>
              <?php if (!$hotelTerminalEligible): ?>
                <input type="hidden" name="overnight_needed" value="">
                <input type="hidden" name="hotel_offered" value="">
                <input type="hidden" name="assistance_hotel_transport_included" value="">
                <input type="hidden" name="hotel_self_paid_nights" value="">
                <div class="case-note">Trin 2 viser, at afgangen ikke var fra en havneterminal. Derfor aabnes hotel og transport efter ferry Art. 17, stk. 2 ikke i backend-sporet.</div>
              <?php else: ?>
              <div class="case-form-grid">
                <div class="case-field">
                  <label for="<?= h($supportOvernightFieldId) ?>"><?= h($supportOvernightLabel) ?></label>
                  <?php if ($lockedSupportOvernight): ?>
                    <input id="<?= h($supportOvernightFieldId) ?>" type="hidden" name="<?= h($supportOvernightFieldId) ?>" value="<?= h($supportOvernightValue) ?>">
                    <div class="case-note"><?= h($labelFor($yesNoUnknown, $supportOvernightValue, 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="<?= h($supportOvernightFieldId) ?>" name="<?= h($supportOvernightFieldId) ?>"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $supportOvernightValue === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
                <div class="case-field<?= $supportOvernightValue === 'yes' ? '' : ' case-hidden' ?>" data-hotel-requires-overnight>
                  <label for="hotel_offered">Blev hotel / indkvartering tilbudt?</label>
                  <?php if ($lockedHotelOffered): ?>
                    <input id="hotel_offered" type="hidden" name="hotel_offered" value="<?= h($field('hotel_offered')) ?>">
                    <div class="case-note"><?= h($labelFor($yesNoIrrelevant, $field('hotel_offered'), 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="hotel_offered" name="hotel_offered"><?php foreach ($yesNoIrrelevant as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('hotel_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
                <div class="case-field<?= ($supportOvernightValue === 'yes' && $field('hotel_offered') === 'no') ? '' : ' case-hidden' ?>" data-hotel-fields><label for="hotel_self_paid_nights">Antal naetter</label><input id="hotel_self_paid_nights" name="hotel_self_paid_nights" value="<?= h($field('hotel_self_paid_nights')) ?>" inputmode="numeric"></div>
                <div class="case-field<?= ($supportOvernightValue === 'yes' && $field('hotel_offered') === 'yes') ? '' : ' case-hidden' ?>" data-hotel-transport-panel>
                  <label for="assistance_hotel_transport_included">Var transport til/fra hotel inkluderet?</label>
                  <?php if ($lockedHotelTransportIncluded): ?>
                    <input id="assistance_hotel_transport_included" type="hidden" name="assistance_hotel_transport_included" value="<?= h($field('assistance_hotel_transport_included')) ?>">
                    <div class="case-note"><?= h($labelFor($yesNoUnknownIrrelevant, $field('assistance_hotel_transport_included'), 'Ikke oplyst')) ?></div>
                  <?php else: ?>
                    <select id="assistance_hotel_transport_included" name="assistance_hotel_transport_included"><?php foreach ($yesNoUnknownIrrelevant as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('assistance_hotel_transport_included', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <?php endif; ?>
                </div>
              </div>
              <div class="case-note<?= $supportOvernightValue === 'yes' ? '' : ' case-hidden' ?>" data-hotel-requires-overnight>Hotel-delen aabnes kun, hvis overnatning faktisk var noedvendig. Derefter afklares foerst, om hotel blev tilbudt, og kun hvis ja, om hoteltransport var inkluderet.</div>
              <div class="case-note<?= ($supportOvernightValue === 'yes' && $field('hotel_offered') === 'no') ? '' : ' case-hidden' ?>" data-hotel-fields>Hvis passageren selv betalte hotel, saa registreres det som en expense-post med typen <strong>Hotel / indkvartering</strong>.</div>
              <div class="case-note<?= ($supportOvernightValue === 'yes' && $field('hotel_offered') === 'yes' && $field('assistance_hotel_transport_included') === 'no') ? '' : ' case-hidden' ?>" data-hotel-transport-fields>Hvis passageren selv betalte transport til/fra hotel, saa registreres det som en expense-post med typen <strong>Transport til/fra hotel</strong>.</div>
              <?php endif; ?>
            </div>

            <?php if ($careExpenseOverrideOnly): ?>
              <div class="case-note"><?= h($isOngoingCase
                ? ($isFerryCase
                    ? 'Liveflowet siger, at der hverken er et aabent maaltids- eller overnatningsbehov. Derfor forventes der som udgangspunkt ingen assistanceudgift her.'
                    : 'Liveflowet siger, at maaltider blev tilbudt, og at overnatning ikke var noedvendig. Derfor forventes der som udgangspunkt ingen Article 9-udgift her.')
                : ($isFerryCase
                    ? 'Frontend siger, at der hverken er et aabent maaltids- eller overnatningsbehov. Derfor forventes der som udgangspunkt ingen assistanceudgift her.'
                    : 'Frontend siger, at maaltider blev tilbudt, og at overnatning ikke var noedvendig. Derfor forventes der som udgangspunkt ingen Article 9-udgift her.')
              ) ?></div>
            <?php endif; ?>
            <<?= $careExpenseOverrideOnly ? 'details' : 'div' ?> class="case-subpanel"<?= $careExpenseOverrideOnly ? '' : '' ?>>
              <?php if ($careExpenseOverrideOnly): ?>
                <summary><strong>Tilfoej care-udgift alligevel (manuel afklaring)</strong></summary>
                <p class="case-muted"><?= h($isOngoingCase
                  ? 'Brug kun denne del, hvis passageren faktisk havde en care-udgift, som ikke fremgaar af liveflowets korte svar.'
                  : 'Brug kun denne del, hvis passageren faktisk havde en care-udgift, som ikke fremgaar af frontendflowets korte svar.'
                ) ?></p>
              <?php else: ?>
                <h3>Care-udgifter og kvitteringer</h3>
                <p class="case-muted"><?= h($isFerryCase
                  ? 'Her registreres kun assistanceudgifter for ferry: maaltider, hotel og hoteltransport. Upload kvittering direkte paa den relevante post.'
                  : 'Her registreres kun Article 9-care: maaltider, hotel og hoteltransport. Upload kvittering direkte paa den relevante post.'
                ) ?></p>
              <?php endif; ?>
              <div class="case-file-list" data-expense-items="care">
                <?php foreach ($careExpenseItems as $index => $item): ?>
                  <?php $itemReceipt = (array)($item['receipt'] ?? []); ?>
                  <div class="case-file-item" data-expense-item>
                    <div class="case-form-grid">
                      <div class="case-field">
                        <label for="air_case_care_expense_items_<?= h((string)$index) ?>_type">Udgiftstype</label>
                        <select id="air_case_care_expense_items_<?= h((string)$index) ?>_type" name="air_case_care_expense_items[<?= h((string)$index) ?>][type]">
                          <?php foreach ($careExpenseTypeOptions as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ((string)($item['type'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="case-field">
                        <label for="air_case_care_expense_items_<?= h((string)$index) ?>_amount">Beloeb</label>
                        <input id="air_case_care_expense_items_<?= h((string)$index) ?>_amount" name="air_case_care_expense_items[<?= h((string)$index) ?>][amount]" value="<?= h((string)($item['amount'] ?? '')) ?>" inputmode="decimal">
                      </div>
                      <div class="case-field">
                        <label for="air_case_care_expense_items_<?= h((string)$index) ?>_currency">Valuta</label>
                        <select id="air_case_care_expense_items_<?= h((string)$index) ?>_currency" name="air_case_care_expense_items[<?= h((string)$index) ?>][currency]">
                          <option value="">Vaelg</option>
                          <?php foreach ($currencyOptions as $currency): ?>
                            <option value="<?= h($currency) ?>" <?= ((string)($item['currency'] ?? '') === $currency) ? 'selected' : '' ?>><?= h($currency) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="case-field" style="grid-column: 1 / -1;">
                        <label for="air_case_care_expense_items_<?= h((string)$index) ?>_description">Kort beskrivelse</label>
                        <textarea id="air_case_care_expense_items_<?= h((string)$index) ?>_description" name="air_case_care_expense_items[<?= h((string)$index) ?>][description]"><?= h((string)($item['description'] ?? '')) ?></textarea>
                      </div>
                      <div class="case-field">
                        <label for="air_case_care_receipts_<?= h((string)$index) ?>">Kvittering</label>
                        <input id="air_case_care_receipts_<?= h((string)$index) ?>" name="air_case_care_receipts[<?= h((string)$index) ?>]" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                      </div>
                      <div class="case-field">
                        <label>Eksisterende kvittering</label>
                        <?php if ($itemReceipt !== []): ?>
                          <div class="case-file-item" style="margin-top:6px;">
                            <a href="<?= h((string)($itemReceipt['path'] ?? '#')) ?>" target="_blank" rel="noopener"><?= h((string)($itemReceipt['name'] ?? 'Upload')) ?></a>
                            <div class="case-muted">Uploadet <?= h((string)($itemReceipt['uploaded_at'] ?? '')) ?></div>
                          </div>
                        <?php else: ?>
                          <div class="case-muted" style="margin-top:10px;">Ingen kvittering endnu.</div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="case-actions">
                      <button class="case-button" type="button" data-remove-expense-item>Fjern post</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="case-actions">
                <button class="case-button" type="button" data-add-expense-item="care">+ Tilfoej care-udgift</button>
              </div>
            </<?= $careExpenseOverrideOnly ? 'details' : 'div' ?>>

            <div class="case-subpanel">
              <h3><?= h($isFerryCase ? 'PMR: assistance og boarding support' : 'Artikel 11: PMR') ?></h3>
              <p class="case-muted"><?= h($isFerryCase ? 'Frontend har allerede afklaret PMR-status og eventuel naegtet indskibning. Her i backend samler vi den assistance-orienterede PMR-dokumentation og supplerende ferry-evidence.' : 'PMR-sporet bruges kun til artikel 11: foersteprioritet ved transport samt hurtig adgang til artikel 9-care.') ?></p>
              <input id="pmr_user" name="pmr_user" type="hidden" value="<?= h($field('pmr_user')) ?>">
              <div class="case-note">Frontend har allerede afklaret PMR: <strong><?= h($labelFor($yesNo, $field('pmr_user'), 'Ikke oplyst')) ?></strong></div>
              <div class="<?= $field('pmr_user') === 'yes' ? '' : ' case-hidden' ?>" data-pmr-panel>
                <?php if ($isFerryCase): ?>
                <div class="case-note" style="margin-bottom:12px;">
                  <div><strong>Naegtet indskibning:</strong> <?= h($labelFor($yesNoUnknown, $field('ferry_pmr_boarding_refused'), 'Ikke oplyst')) ?></div>
                  <div><strong>Begrundelse:</strong> <?= h($labelFor($ferryPmrRefusalBasisOptions, $field('ferry_pmr_refusal_basis'), 'Ikke oplyst')) ?></div>
                  <div><strong>Begrundelse givet:</strong> <?= h($labelFor($yesNoUnknown, $field('ferry_pmr_reason_given'), 'Ikke oplyst')) ?></div>
                </div>
                <?php endif; ?>
                <div class="case-form-grid">
                  <div class="case-field"><label for="<?= h($isFerryCase ? 'ferry_pmr_companion' : 'pmr_companion') ?>">Var der ledsager?</label><select id="<?= h($isFerryCase ? 'ferry_pmr_companion' : 'pmr_companion') ?>" name="<?= h($isFerryCase ? 'ferry_pmr_companion' : 'pmr_companion') ?>"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected($isFerryCase ? 'ferry_pmr_companion' : 'pmr_companion', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="<?= h($isFerryCase ? 'ferry_pmr_service_dog' : 'pmr_service_dog') ?>">Godkendt foererhund med?</label><select id="<?= h($isFerryCase ? 'ferry_pmr_service_dog' : 'pmr_service_dog') ?>" name="<?= h($isFerryCase ? 'ferry_pmr_service_dog' : 'pmr_service_dog') ?>"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected($isFerryCase ? 'ferry_pmr_service_dog' : 'pmr_service_dog', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="assistance_pmr_priority_applied">Blev foersteprioritet ved transport / boarding respekteret?</label><select id="assistance_pmr_priority_applied" name="assistance_pmr_priority_applied"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('assistance_pmr_priority_applied', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="pmr_delivered_status">Fik passageren forplejning og indkvartering efter artikel 9 saa hurtigt som muligt?</label><select id="pmr_delivered_status" name="pmr_delivered_status"><?php foreach ($article11CareOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('pmr_delivered_status', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <?php if ($isFerryCase): ?>
                  <div class="case-field"><label for="ferry_pmr_notice_48h">Blev der givet 48 timers varsel om behovet for assistance?</label><select id="ferry_pmr_notice_48h" name="ferry_pmr_notice_48h"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('ferry_pmr_notice_48h', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="ferry_pmr_met_checkin_time">Moedte passageren paa det oplyste assistance/check-in-tidspunkt?</label><select id="ferry_pmr_met_checkin_time" name="ferry_pmr_met_checkin_time"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('ferry_pmr_met_checkin_time', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="ferry_pmr_special_needs_notified_at_booking">Saerlige behov oplyst ved booking?</label><select id="ferry_pmr_special_needs_notified_at_booking" name="ferry_pmr_special_needs_notified_at_booking"><?php foreach ($ferryPmrSpecialNeedsOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('ferry_pmr_special_needs_notified_at_booking', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="ferry_pmr_assistance_delivered">Hvordan blev assistancen leveret?</label><select id="ferry_pmr_assistance_delivered" name="ferry_pmr_assistance_delivered"><?php foreach ($ferryPmrDeliveredOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('ferry_pmr_assistance_delivered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="ferry_pmr_alternative_transport_offered">Tilboed operatoeren alternativ befordring eller anden loesning?</label><select id="ferry_pmr_alternative_transport_offered" name="ferry_pmr_alternative_transport_offered"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('ferry_pmr_alternative_transport_offered', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="case-subpanel">
              <h3>Artikel 11: Uledsaget barn</h3>
              <p class="case-muted">Uledsaget barn holdes adskilt fra PMR. Ogsaa her handler artikel 11 kun om foersteprioritet ved transport og artikel 9-care saa hurtigt som muligt.</p>
              <input id="unaccompanied_minor" name="unaccompanied_minor" type="hidden" value="<?= h($field('unaccompanied_minor')) ?>">
              <div class="case-note">Frontend har allerede afklaret uledsaget barn: <strong><?= h($labelFor($yesNoUnknown, $field('unaccompanied_minor'), 'Ikke oplyst')) ?></strong></div>
              <div class="<?= $field('unaccompanied_minor') === 'yes' ? '' : ' case-hidden' ?>" data-child-panel>
                <div class="case-form-grid">
                  <div class="case-field"><label for="assistance_child_priority_applied">Blev foersteprioritet ved transport / boarding respekteret?</label><select id="assistance_child_priority_applied" name="assistance_child_priority_applied"><?php foreach ($yesNoUnknown as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('assistance_child_priority_applied', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                  <div class="case-field"><label for="child_delivered_status">Fik barnet forplejning og indkvartering efter artikel 9 saa hurtigt som muligt?</label><select id="child_delivered_status" name="child_delivered_status"><?php foreach ($article11CareOptions as $value => $label): ?><option value="<?= h($value) ?>" <?= $selected('child_delivered_status', $value) ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
                </div>
              </div>
            </div>
            <div class="case-step-nav"><?php if ($prevStep !== ''): ?><a class="case-button" href="<?= h($buildCaseHref($prevStep)) ?>">Forrige trin</a><?php else: ?><span></span><?php endif; ?><div style="display:flex; gap:10px;"><button class="case-button" type="submit">Gem</button><?php if ($nextStep !== ''): ?><button class="case-button primary" type="submit" name="goto_step" value="<?= h($nextStep) ?>">Gem og naeste trin</button><?php endif; ?></div></div>
          </form>
        <?php elseif ($activeStep === 'applicant'): ?>
          <p>Ansogeroplysninger og udbetalingsdata er nu lagt direkte ind i backend-panelet, saa passageren kan blive i samme arbejdsgang.</p>
          <form method="post" action="<?= h($buildCaseHref($activeStep)) ?>" enctype="multipart/form-data">
            <?php if ($csrfToken !== ''): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>"><?php endif; ?>
            <input type="hidden" name="active_case_step" value="<?= h($activeStep) ?>">
            <div class="case-subpanel">
              <h3>Person</h3>
              <div class="case-form-grid">
                <div class="case-field"><label for="firstName">Fornavn</label><input id="firstName" name="firstName" value="<?= h($field('firstName')) ?>"></div>
                <div class="case-field"><label for="lastName">Efternavn</label><input id="lastName" name="lastName" value="<?= h($field('lastName')) ?>"></div>
                <div class="case-field"><label for="contact_email">E-mail</label><input id="contact_email" name="contact_email" type="email" value="<?= h($field('contact_email')) ?>"></div>
                <div class="case-field"><label for="contact_phone">Telefon</label><input id="contact_phone" name="contact_phone" value="<?= h($field('contact_phone')) ?>"></div>
              </div>
            </div>
            <div class="case-subpanel">
              <h3>Adresse</h3>
              <div class="case-form-grid">
                <div class="case-field"><label for="address_street">Vej</label><input id="address_street" name="address_street" value="<?= h($field('address_street')) ?>"></div>
                <div class="case-field"><label for="address_no">Nr.</label><input id="address_no" name="address_no" value="<?= h($field('address_no')) ?>"></div>
                <div class="case-field"><label for="address_postalCode">Postnr.</label><input id="address_postalCode" name="address_postalCode" value="<?= h($field('address_postalCode')) ?>"></div>
                <div class="case-field"><label for="address_city">By</label><input id="address_city" name="address_city" value="<?= h($field('address_city')) ?>"></div>
                <div class="case-field"><label for="address_country">Land</label><input id="address_country" name="address_country" value="<?= h($field('address_country')) ?>"></div>
              </div>
            </div>
            <div class="case-subpanel">
              <h3>Udbetaling</h3>
              <div class="case-form-grid">
                <div class="case-field"><label for="payoutPreference">Udbetalingsmetode</label><select id="payoutPreference" name="payoutPreference"><option value="bank" selected>Bankoverfoersel</option></select></div>
                <div class="case-field"><label for="accountHolderName">Kontohaver</label><input id="accountHolderName" name="accountHolderName" value="<?= h($field('accountHolderName')) ?>"></div>
                <div class="case-field"><label for="iban">IBAN</label><input id="iban" name="iban" value="<?= h($field('iban')) ?>"></div>
                <div class="case-field"><label for="bic">BIC</label><input id="bic" name="bic" value="<?= h($field('bic')) ?>"></div>
              </div>
            </div>
            <div class="case-step-nav"><?php if ($prevStep !== ''): ?><a class="case-button" href="<?= h($buildCaseHref($prevStep)) ?>">Forrige trin</a><?php else: ?><span></span><?php endif; ?><div style="display:flex; gap:10px;"><button class="case-button" type="submit">Gem</button><?php if ($nextStep !== ''): ?><button class="case-button primary" type="submit" name="goto_step" value="<?= h($nextStep) ?>">Gem og naeste trin</button><?php endif; ?></div></div>
          </form>
        <?php else: ?>
          <p>Fuldmagt, vilkår og databehandling afsluttes her som sidste backend-step. Trinnet er foerst faerdigt, naar fuldmagt, fee-vilkaar og privacy er accepteret, og underskriver er udfyldt.</p>
          <form method="post" action="<?= h($buildCaseHref($activeStep)) ?>" enctype="multipart/form-data">
            <?php if ($csrfToken !== ''): ?><input type="hidden" name="_csrfToken" value="<?= h($csrfToken) ?>"><?php endif; ?>
            <input type="hidden" name="active_case_step" value="<?= h($activeStep) ?>">
            <div class="case-subpanel">
              <h3>Fuldmagt</h3>
              <p class="case-muted">Du giver tilladelse til, at sagen kan foeres paa dine vegne over for luftfartsselskabet, herunder at vi kan fremsaette krav, indhente relevante oplysninger og modtage svar i sagen.</p>
              <label class="case-field" style="display:flex; align-items:flex-start; gap:10px;">
                <input type="checkbox" name="poa_accepted" value="1" <?= $field('poa_accepted') === '1' ? 'checked' : '' ?> style="width:auto; margin-top:2px;">
                <span>Jeg giver fuldmagt til, at sagen foeres paa mine vegne.</span>
              </label>
            </div>
            <div class="case-subpanel">
              <h3>Vilkår og fee</h3>
              <p class="case-muted">Du accepterer, at sagen behandles efter de vilkaar og det fee, der er oplyst for tjenesten.</p>
              <label class="case-field" style="display:flex; align-items:flex-start; gap:10px;">
                <input type="checkbox" name="fee_terms_accepted" value="1" <?= $field('fee_terms_accepted') === '1' ? 'checked' : '' ?> style="width:auto; margin-top:2px;">
                <span>Jeg accepterer jeres vilkaar og fee for behandlingen af sagen.</span>
              </label>
            </div>
            <div class="case-subpanel">
              <h3>Databehandling</h3>
              <p class="case-muted">Du accepterer, at relevante personoplysninger og sagsoplysninger behandles og videregives, naer det er noedvendigt for at foere kravet.</p>
              <label class="case-field" style="display:flex; align-items:flex-start; gap:10px;">
                <input type="checkbox" name="privacy_accepted" value="1" <?= $field('privacy_accepted', $field('gdprConsent')) === '1' ? 'checked' : '' ?> style="width:auto; margin-top:2px;">
                <span>Jeg accepterer behandling af mine oplysninger til brug for denne sag.</span>
              </label>
            </div>
            <div class="case-subpanel">
              <h3>Underskriver</h3>
              <div class="case-form-grid">
                <div class="case-field"><label for="signer_name">Navn</label><input id="signer_name" name="signer_name" value="<?= h($field('signer_name', trim($field('firstName') . ' ' . $field('lastName')))) ?>"></div>
                <div class="case-field"><label for="signer_email">E-mail</label><input id="signer_email" name="signer_email" value="<?= h($field('signer_email', $field('contact_email'))) ?>"></div>
              </div>
            </div>
            <div class="case-subpanel">
              <h3>Afregningsoversigt</h3>
              <p class="case-muted">Read-only overblik over kendte poster i sagen. Ticket upload, Article 8-afgraensning og manuel vurdering kan stadig flytte beloebene.</p>
              <div class="case-summary-grid">
                <div class="case-summary-item">
                  <strong><?= h($articleCompensationLabel) ?></strong>
                  <div><?= h($formatMoneyMap($article7Amounts, $compOverviewAmountText)) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= h($isFerryCase ? ($ferryGateArt19 ? 'Art. 19 aktiv' : 'Art. 19 ikke aktiv') : $labelFor($article7EligibilityLabels, $article7EligibilityStatus, 'Foreloebig vurdering mangler')) ?></div>
                </div>
                <div class="case-summary-item">
                  <strong><?= h($articleRemedyLabel) ?></strong>
                  <div><?= h($formatMoneyMap($article8Amounts, 'Afventer kendte beloeb')) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= h($refundOverviewText) ?></div>
                </div>
                <div class="case-summary-item">
                  <strong><?= h($articleAssistanceLabel) ?></strong>
                  <div><?= h($formatMoneyMap($article9Amounts, 'Ingen poster endnu')) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= h($supportSummary) ?></div>
                </div>
                <?php if (!$isFerryCase): ?>
                <div class="case-summary-item">
                  <strong>Article 10 downgrade</strong>
                  <div><?= h($formatMoneyMap($article10Amounts, $downgradeAmountText)) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= $downgradeOccurred ? 'Registreret' : 'Ikke registreret' ?></div>
                </div>
                <?php endif; ?>
                <div class="case-summary-item">
                  <strong>Samlet brutto</strong>
                  <div><?= h($formatMoneyMap($grossAmounts, 'Afventer flere beloeb')) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= h($knownArticlesText) ?></div>
                </div>
                <div class="case-summary-item">
                  <strong>Fee-grundlag</strong>
                  <div><?= h($formatMoneyMap($feeBaseAmounts, 'Ikke relevant endnu')) ?></div>
                  <div class="case-muted" style="margin-top:6px;"><?= h($feeBasisText) ?></div>
                </div>
                <div class="case-summary-item">
                  <strong>Fee (20%)</strong>
                  <div><?= h($formatMoneyMap($feeAmounts, '0.00 EUR')) ?></div>
                  <div class="case-muted" style="margin-top:6px;">Afregnes kun ved kontant recovery.</div>
                </div>
                <div class="case-summary-item">
                  <strong>Forventet netto til passager</strong>
                  <div><?= h($formatMoneyMap($netAmounts, 'Afventer flere beloeb')) ?></div>
                  <div class="case-muted" style="margin-top:6px;">Netto = brutto minus fee.</div>
                </div>
              </div>
              <?php if ($settlementNotes !== []): ?>
                <div class="case-note">
                  <?php foreach ($settlementNotes as $note): ?>
                    <div><?= h((string)$note) ?></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="case-subpanel">
              <h3>Yderligere oplysninger</h3>
              <div class="case-field"><label for="additionalInfo">Supplerende information (valgfrit)</label><textarea id="additionalInfo" name="additionalInfo"><?= h($field('additionalInfo')) ?></textarea></div>
            </div>
            <div class="case-step-nav"><?php if ($prevStep !== ''): ?><a class="case-button" href="<?= h($buildCaseHref($prevStep)) ?>">Forrige trin</a><?php else: ?><span></span><?php endif; ?><div style="display:flex; gap:10px;"><button class="case-button" type="submit">Gem</button><a class="case-button primary" href="<?= h($this->Url->build('/flow/compensation')) ?>">Tilbage til resultat</a></div></div>
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<script>
  (() => {
    const byId = (id) => document.getElementById(id);
    const isDelayRefundContext = <?= json_encode($isDelayRefundContext) ?>;
    const isCancellationRefundContext = <?= json_encode($isCancellationRefundContext) ?>;
    const isDeniedBoardingContext = <?= json_encode($isDeniedBoardingContext) ?>;
    const isOngoingDeniedBoardingContext = <?= json_encode($isOngoingCase && $isDeniedBoardingContext) ?>;
    const hasCompletedFrontendRerouteFacts = <?= json_encode($activeStep === 'refund' ? ($hasCompletedFrontendRerouteFacts ?? false) : false) ?>;
    const deniedBoardingReductionRelevant = <?= json_encode($deniedBoardingReductionRelevant) ?>;
    const isAirCase = <?= json_encode($isAirCase) ?>;
    const isFerryCase = <?= json_encode($isFerryCase) ?>;
    const transportNodesSearchUrl = <?= json_encode($transportNodesSearchUrl) ?>;
    const isRerouteRemedy = (remedy) => remedy === 'reroute_soonest' || remedy === 'reroute_later' || (isFerryCase && remedy === 'no_real_choice');
    const toggleAll = (selector, show) => document.querySelectorAll(selector).forEach((node) => {
      node.classList.toggle('case-hidden', !show);
      node.querySelectorAll('input, select, textarea, button').forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement || field instanceof HTMLButtonElement)) {
          return;
        }
        if (field.hasAttribute('data-keep-enabled')) {
          return;
        }
        field.disabled = !show;
      });
    });
    const airportSearchCache = new Map();
    const airportAutocompleteControllers = new WeakMap();
    const escapeHtml = (value) => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
    const getAirportQuery = (field) => {
      const rawValue = field.value || '';
      if (field.dataset.airportAutocomplete === 'multi') {
        const parts = rawValue.split(/[\n,]/);
        return (parts[parts.length - 1] || '').trim();
      }
      return rawValue.trim();
    };
    const applyAirportSelection = (field, node) => {
      const label = String(node.name || node.code || '').trim();
      if (label === '') {
        return;
      }
      if (field.dataset.airportAutocomplete === 'multi') {
        const rawValue = field.value || '';
        const match = rawValue.match(/^(.*?)([^,\n]*)$/s);
        const prefix = match ? match[1] : '';
        field.value = `${prefix}${label}`;
      } else {
        field.value = label;
      }
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const renderAirportSuggestions = (field, nodes) => {
      const wrapper = field.closest('.case-airport-autocomplete');
      if (!wrapper) {
        return;
      }
      wrapper.querySelectorAll('.case-airport-suggestions').forEach((node) => node.remove());
      if (!nodes.length) {
        return;
      }
      const container = document.createElement('div');
      container.className = 'case-airport-suggestions';
      container.setAttribute('role', 'listbox');
      container.innerHTML = nodes.map((node, index) => {
        const name = escapeHtml(node.name || node.code || 'Ukendt lufthavn');
        const meta = [
          node.code ? String(node.code).toUpperCase() : '',
          node.city || '',
          node.country || '',
        ].filter(Boolean).join(' · ');
        return `<button type="button" class="case-airport-suggestion" data-airport-index="${index}"><strong>${name}</strong><span>${escapeHtml(meta)}</span></button>`;
      }).join('');
      container.querySelectorAll('[data-airport-index]').forEach((button) => {
        button.addEventListener('mousedown', (event) => {
          event.preventDefault();
          const index = Number(button.getAttribute('data-airport-index') || '-1');
          if (Number.isNaN(index) || !nodes[index]) {
            return;
          }
          applyAirportSelection(field, nodes[index]);
          container.remove();
        });
      });
      wrapper.appendChild(container);
    };
    const closeAirportSuggestions = () => {
      document.querySelectorAll('.case-airport-suggestions').forEach((node) => node.remove());
    };
    const fetchAirportNodes = async (query, signal) => {
      const cacheKey = query.toLowerCase();
      if (airportSearchCache.has(cacheKey)) {
        return airportSearchCache.get(cacheKey);
      }
      const url = new URL(transportNodesSearchUrl, window.location.origin);
      url.searchParams.set('mode', 'air');
      url.searchParams.set('q', query);
      url.searchParams.set('limit', '8');
      const response = await fetch(url.toString(), {
        method: 'GET',
        headers: { Accept: 'application/json' },
        signal,
      });
      if (!response.ok) {
        return [];
      }
      const payload = await response.json();
      const nodes = Array.isArray(payload?.data?.nodes) ? payload.data.nodes : [];
      airportSearchCache.set(cacheKey, nodes);
      return nodes;
    };
    const initAirportAutocomplete = (field) => {
      const runLookup = async () => {
        const query = getAirportQuery(field);
        if (query.length < 3) {
          closeAirportSuggestions();
          return;
        }
        const previousController = airportAutocompleteControllers.get(field);
        if (previousController) {
          previousController.abort();
        }
        const controller = new AbortController();
        airportAutocompleteControllers.set(field, controller);
        try {
          const nodes = await fetchAirportNodes(query, controller.signal);
          if (getAirportQuery(field) !== query) {
            return;
          }
          renderAirportSuggestions(field, nodes);
        } catch (error) {
          if (error && error.name === 'AbortError') {
            return;
          }
        }
      };
      field.addEventListener('input', runLookup);
      field.addEventListener('focus', runLookup);
      field.addEventListener('blur', () => {
        window.setTimeout(closeAirportSuggestions, 150);
      });
    };
    if (isAirCase) {
      document.querySelectorAll('[data-airport-autocomplete]').forEach((field) => {
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
          initAirportAutocomplete(field);
        }
      });
      document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.closest('.case-airport-autocomplete')) {
          closeAirportSuggestions();
        }
      });
    }
    const expenseOptionSets = {
      refund: <?= json_encode($refundExpenseTypeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      reroute: <?= json_encode($rerouteExpenseTypeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      care: <?= json_encode($careExpenseTypeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    };
    const shouldShowCancellationPassengerRerouteFields = () => {
      if (!<?= json_encode($isCancellationRefundContext) ?>) {
        return true;
      }
      const remedy = byId('remedyChoice')?.value || '';
      if (!isRerouteRemedy(remedy)) {
        return false;
      }
      const offerValue = byId('reroute_offered')?.value || '';
      const usedValue = byId('reroute_used_or_accepted')?.value || '';
      const selfArrangedValue = byId('air_self_arranged_reroute')?.value || '';
      if (offerValue === 'yes' && usedValue === 'yes') {
        return false;
      }
      return (offerValue !== '' && offerValue !== 'yes')
        || usedValue === 'no'
        || usedValue === 'unknown'
        || (offerValue === '' && usedValue === '' && selfArrangedValue !== '');
    };
    const shouldShowDeniedBoardingSelfArrangedFields = () => {
      if (!isDeniedBoardingContext) {
        return false;
      }
      const remedy = byId('remedyChoice')?.value || '';
      if (!isRerouteRemedy(remedy)) {
        return false;
      }
      if (isOngoingDeniedBoardingContext) {
        return true;
      }
      const offerValue = byId('reroute_offered')?.value || '';
      const usedValue = byId('reroute_used_or_accepted')?.value || '';
      const selfArrangedValue = byId('air_self_arranged_reroute')?.value || '';
      if (offerValue === 'yes' && usedValue === 'yes') {
        return false;
      }
      return offerValue === 'no'
        || usedValue === 'no'
        || usedValue === 'unknown'
        || (offerValue === '' && usedValue === '' && selfArrangedValue !== '');
    };
    const syncCancellationPassengerReroute = () => {
      if (!<?= json_encode($isCancellationRefundContext) ?>) {
        return;
      }
      const remedy = byId('remedyChoice')?.value || '';
      const showPassengerFields = shouldShowCancellationPassengerRerouteFields();
      const isCancellationReroute = isRerouteRemedy(remedy);
      const rerouteOffered = byId('reroute_offered')?.value === 'yes';
      const selfArranged = byId('air_self_arranged_reroute')?.value === 'yes';
      const showExpenseGate = isCancellationReroute || hasCompletedFrontendRerouteFacts;
      toggleAll('[data-cancellation-self-arranged-note]', false);
      toggleAll('[data-reroute-used-panel]', isCancellationReroute && rerouteOffered);
      toggleAll('[data-reroute-self-arranged-panel]', isCancellationReroute && showPassengerFields);
      toggleAll('[data-reroute-expense-gate-panel]', isCancellationReroute && showExpenseGate);
      toggleAll('[data-cancellation-passenger-reroute-fields]', showPassengerFields);
      toggleAll('[data-self-arranged-reason-field]', false);
      toggleAll('[data-reroute-expense-panel]', isCancellationReroute && showExpenseGate && byId('air_reroute_expenses_incurred')?.value === 'yes');
    };
    const syncRemedy = () => {
      if (isDelayRefundContext) {
        toggleAll('[data-refund-panel]', byId('remedyChoice')?.value === 'refund_return');
        toggleAll('[data-reroute-panel]', false);
        syncReturnOrigin();
        return;
      }
      const remedy = byId('remedyChoice')?.value || '';
      toggleAll('[data-refund-panel]', remedy === 'refund_return');
      toggleAll('[data-reroute-panel]', isRerouteRemedy(remedy));
      if (<?= json_encode($isCancellationRefundContext) ?>) {
        syncCancellationPassengerReroute();
      } else if (isDeniedBoardingContext) {
        syncDeniedBoardingCompensation();
      } else {
        const showReroute = isRerouteRemedy(remedy);
        toggleAll('[data-reroute-self-arranged-panel]', showReroute);
        toggleAll('[data-reroute-expense-gate-panel]', showReroute);
      }
    };
    const syncReturnOrigin = () => toggleAll('[data-return-origin-fields]', byId('return_to_origin_expense')?.value === 'yes');
    const syncRerouteExpenses = () => {
      if (isCancellationRefundContext) {
        const remedy = byId('remedyChoice')?.value || '';
        const showExpenseGate = isRerouteRemedy(remedy) || hasCompletedFrontendRerouteFacts;
        toggleAll('[data-reroute-expense-gate-panel]', showExpenseGate);
        toggleAll('[data-reroute-expense-panel]', showExpenseGate && byId('air_reroute_expenses_incurred')?.value === 'yes');
        return;
      }
      const selfArrangedOpen = isDeniedBoardingContext
        ? shouldShowDeniedBoardingSelfArrangedFields()
        : shouldShowCancellationPassengerRerouteFields();
      const hasLiveExpenseGate = isOngoingDeniedBoardingContext && (byId('air_reroute_expenses_incurred')?.value || '') !== '';
      const showExpenseGate = selfArrangedOpen || hasLiveExpenseGate || hasCompletedFrontendRerouteFacts;
      toggleAll('[data-reroute-expense-gate-panel]', showExpenseGate);
      toggleAll('[data-self-arranged-reason-field]', false);
      toggleAll('[data-reroute-expense-panel]', showExpenseGate && byId('air_reroute_expenses_incurred')?.value === 'yes');
    };
    const syncCancellationCompensation = () => {
      if (!<?= json_encode($isCancellationRefundContext) ?>) {
        return;
      }
      const remedy = byId('remedyChoice')?.value || '';
      const showDetails = isRerouteRemedy(remedy);
      const noticeBand = byId('cancellation_notice_band')?.value || '';
      const rerouteOffered = byId('reroute_offered')?.value === 'yes';
      const rerouteUsed = byId('reroute_used_or_accepted')?.value === 'yes';
      const departureBand = byId('reroute_departure_band')?.value || '';
      const arrivalBand = byId('reroute_arrival_band')?.value || '';
      const showWindows = ['7_to_13_days', 'under_7_days', 'airport_on_day_of_departure'].includes(noticeBand);
      let art7Eligibility = <?= json_encode($article7EligibilityStatus) ?>;
      if (noticeBand === '14_plus_days') {
        art7Eligibility = 'not_eligible';
      } else if (rerouteOffered && showWindows) {
        if (departureBand === 'within_window' && arrivalBand === 'within_window') {
          art7Eligibility = 'not_eligible';
        } else if (
          departureBand === ''
          || arrivalBand === ''
          || departureBand === 'unknown'
          || arrivalBand === 'unknown'
        ) {
          art7Eligibility = 'uncertain';
        } else {
          art7Eligibility = 'eligible';
        }
      }
      const showArt7Reduction = rerouteOffered && art7Eligibility === 'eligible';
      toggleAll('[data-cancellation-details-note]', !showDetails);
      toggleAll('[data-cancellation-details-body]', showDetails);
      toggleAll('[data-cancellation-art5-window-panel]', showDetails && rerouteOffered && showWindows);
      toggleAll('[data-cancellation-art7-note]', showDetails && rerouteOffered && !showArt7Reduction);
      toggleAll('[data-cancellation-art7-panel]', showDetails && showArt7Reduction);
      toggleAll('[data-cancellation-reroute-delay-field]', showDetails && showArt7Reduction && rerouteUsed);
      syncCancellationPassengerReroute();
    };
    const syncDeniedBoardingCompensation = () => {
      if (!isDeniedBoardingContext) {
        return;
      }
      const remedy = byId('remedyChoice')?.value || '';
      const rerouteOffered = byId('reroute_offered')?.value === 'yes';
      const noRerouteOffered = byId('reroute_offered')?.value === 'no';
      const rerouteUsed = byId('reroute_used_or_accepted')?.value === 'yes';
      const rerouteRejected = byId('reroute_used_or_accepted')?.value === 'no';
      const selfArranged = byId('air_self_arranged_reroute')?.value === 'yes';
      const showDetails = isRerouteRemedy(remedy);
      const skipCarrierFollowUp = showDetails && selfArranged;
      const showSelfArrangedPath = shouldShowDeniedBoardingSelfArrangedFields();
      const showExpenseGate = showDetails || hasCompletedFrontendRerouteFacts;
      toggleAll('[data-denied-boarding-carrier-panel]', showDetails && !skipCarrierFollowUp);
      toggleAll('[data-denied-boarding-comp-panel]', !skipCarrierFollowUp);
      toggleAll('[data-denied-boarding-details-note]', !showDetails && !skipCarrierFollowUp);
      toggleAll('[data-denied-boarding-details-body]', showDetails && !skipCarrierFollowUp);
      toggleAll('[data-reroute-used-panel]', showDetails && !skipCarrierFollowUp && rerouteOffered);
      toggleAll('[data-reroute-self-arranged-panel]', showDetails && showSelfArrangedPath);
      toggleAll('[data-reroute-expense-gate-panel]', showDetails && showExpenseGate);
      toggleAll('[data-denied-boarding-used-note]', showDetails && !skipCarrierFollowUp && rerouteOffered && rerouteUsed);
      toggleAll('[data-denied-boarding-self-arranged-note]', showDetails && !skipCarrierFollowUp && (noRerouteOffered || (rerouteOffered && rerouteRejected)));
      toggleAll('[data-denied-boarding-self-arranged-fields]', showDetails && showSelfArrangedPath);
      toggleAll('[data-self-arranged-reason-field]', false);
      toggleAll('[data-reroute-expense-panel]', showDetails && showExpenseGate && byId('air_reroute_expenses_incurred')?.value === 'yes');
      toggleAll('[data-denied-boarding-art7-note]', showDetails && !skipCarrierFollowUp && !deniedBoardingReductionRelevant);
      toggleAll('[data-denied-boarding-art7-panel]', showDetails && !skipCarrierFollowUp && deniedBoardingReductionRelevant);
      toggleAll('[data-denied-boarding-reroute-delay-field]', showDetails && !skipCarrierFollowUp && deniedBoardingReductionRelevant && rerouteOffered && rerouteUsed);
    };
    const syncMeals = () => toggleAll('[data-meal-fields]', byId('meal_offered')?.value === 'no');
    const syncHotel = () => {
      const overnightValue = byId('air_next_day_departure')?.value || byId('overnight_needed')?.value || '';
      const overnightNeeded = overnightValue === 'yes';
      const hotelOffered = byId('hotel_offered')?.value || '';
      const hotelTransportIncluded = byId('assistance_hotel_transport_included')?.value || '';
      toggleAll('[data-hotel-requires-overnight]', overnightNeeded);
      toggleAll('[data-hotel-fields]', overnightNeeded && hotelOffered === 'no');
      toggleAll('[data-hotel-transport-panel]', overnightNeeded && hotelOffered === 'yes');
      toggleAll('[data-hotel-transport-fields]', overnightNeeded && hotelOffered === 'yes' && hotelTransportIncluded === 'no');
    };
    const syncPmr = () => {
      const pmr = byId('pmr_user')?.value === 'yes';
      toggleAll('[data-pmr-panel]', pmr);
    };
    const syncChild = () => {
      const child = byId('unaccompanied_minor')?.value === 'yes';
      toggleAll('[data-child-panel]', child);
    };
    const buildOptionsHtml = (kind) => {
      const options = expenseOptionSets[kind] || {};
      return Object.entries(options).map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    };
    const buildExpenseItem = (kind, index) => {
      const usesRefundStorage = kind === 'refund' || kind === 'reroute';
      const prefix = usesRefundStorage ? 'air_case_refund_expense_items' : 'air_case_care_expense_items';
      const receiptPrefix = usesRefundStorage ? 'air_case_refund_receipts' : 'air_case_care_receipts';
      const wrapper = document.createElement('div');
      wrapper.className = 'case-file-item';
      wrapper.setAttribute('data-expense-item', '');
      wrapper.dataset.kind = kind;
      wrapper.innerHTML = `
        <div class="case-form-grid">
          <div class="case-field">
            <label for="${prefix}_${index}_type">Udgiftstype</label>
            <select id="${prefix}_${index}_type" name="${prefix}[${index}][type]">${buildOptionsHtml(kind)}</select>
          </div>
          <div class="case-field">
            <label for="${prefix}_${index}_amount">Beloeb</label>
            <input id="${prefix}_${index}_amount" name="${prefix}[${index}][amount]" inputmode="decimal">
          </div>
          <div class="case-field">
            <label for="${prefix}_${index}_currency">Valuta</label>
            <select id="${prefix}_${index}_currency" name="${prefix}[${index}][currency]">
              <option value="">Vaelg</option>
              <?php foreach ($currencyOptions as $currency): ?>
              <option value="<?= h($currency) ?>"><?= h($currency) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="case-field" style="grid-column: 1 / -1;">
            <label for="${prefix}_${index}_description">Kort beskrivelse</label>
            <textarea id="${prefix}_${index}_description" name="${prefix}[${index}][description]"></textarea>
          </div>
          <div class="case-field">
            <label for="${receiptPrefix}_${index}">Kvittering</label>
            <input id="${receiptPrefix}_${index}" name="${receiptPrefix}[${index}]" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp">
          </div>
          <div class="case-field">
            <label>Eksisterende kvittering</label>
            <div class="case-muted" style="margin-top:10px;">Ingen kvittering endnu.</div>
          </div>
        </div>
        <div class="case-actions">
          <button class="case-button" type="button" data-remove-expense-item>Fjern post</button>
        </div>
      `;
      return wrapper;
    };
    const renumberExpenseItems = (container) => {
      if (!container) return;
      const kind = container.getAttribute('data-expense-items') || 'care';
      const usesRefundStorage = kind === 'refund' || kind === 'reroute';
      const prefix = usesRefundStorage ? 'air_case_refund_expense_items' : 'air_case_care_expense_items';
      const receiptPrefix = usesRefundStorage ? 'air_case_refund_receipts' : 'air_case_care_receipts';
      [...container.querySelectorAll('[data-expense-item]')].forEach((item, index) => {
        item.querySelectorAll('label[for], input[id], select[id], textarea[id]').forEach((node) => {
          if (node.tagName === 'LABEL') {
            const current = node.getAttribute('for') || '';
            node.setAttribute('for', current.replace(new RegExp(`${prefix}|${receiptPrefix}`), (match) => match).replace(/\d+/, String(index)));
          } else {
            node.id = node.id.replace(/\d+/, String(index));
          }
        });
        item.querySelectorAll('input[name], select[name], textarea[name]').forEach((node) => {
          node.name = node.name.replace(/\[\d+\]/, `[${index}]`);
        });
      });
    };
    document.querySelectorAll('[data-add-expense-item]').forEach((button) => button.addEventListener('click', () => {
      const kind = button.getAttribute('data-add-expense-item') || 'care';
      const container = document.querySelector(`[data-expense-items="${kind}"]`);
      if (!container) return;
      const index = container.querySelectorAll('[data-expense-item]').length;
      container.appendChild(buildExpenseItem(kind, index));
      renumberExpenseItems(container);
    }));
    document.querySelectorAll('[data-expense-items]').forEach((container) => container.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-remove-expense-item]')) return;
      const items = container.querySelectorAll('[data-expense-item]');
      if (items.length <= 1) {
        const inputs = container.querySelectorAll('input[type="text"], input[inputmode], textarea, select');
        inputs.forEach((input) => {
          if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement || input instanceof HTMLSelectElement) {
            input.value = '';
          }
        });
        const fileInputs = container.querySelectorAll('input[type="file"]');
        fileInputs.forEach((input) => { if (input instanceof HTMLInputElement) input.value = ''; });
        return;
      }
      target.closest('[data-expense-item]')?.remove();
      renumberExpenseItems(container);
    }));
    [['remedyChoice', syncRemedy], ['remedyChoice', syncCancellationCompensation], ['remedyChoice', syncDeniedBoardingCompensation], ['return_to_origin_expense', syncReturnOrigin], ['air_self_arranged_reroute', syncRerouteExpenses], ['air_self_arranged_reroute', syncDeniedBoardingCompensation], ['air_reroute_expenses_incurred', syncRerouteExpenses], ['air_reroute_expenses_incurred', syncDeniedBoardingCompensation], ['reroute_offered', syncCancellationCompensation], ['reroute_offered', syncDeniedBoardingCompensation], ['cancellation_notice_band', syncCancellationCompensation], ['reroute_departure_band', syncCancellationCompensation], ['reroute_arrival_band', syncCancellationCompensation], ['reroute_used_or_accepted', syncCancellationCompensation], ['reroute_used_or_accepted', syncDeniedBoardingCompensation], ['meal_offered', syncMeals], ['overnight_needed', syncHotel], ['air_next_day_departure', syncHotel], ['hotel_offered', syncHotel], ['assistance_hotel_transport_included', syncHotel], ['pmr_user', syncPmr], ['unaccompanied_minor', syncChild]].forEach(([id, handler]) => {
      const node = byId(id);
      if (node) node.addEventListener('change', handler);
    });
    syncRemedy();
    syncReturnOrigin();
    syncRerouteLater();
    syncRerouteExpenses();
    syncCancellationCompensation();
    syncDeniedBoardingCompensation();
    syncMeals();
    syncHotel();
    syncPmr();
    syncChild();
  })();
</script>
</div>
</div>
