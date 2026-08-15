<?php
/**
 * TC6 AIR incident step.
 *
 * Handles the air-specific incident + preliminary assessment (trin 4).
 * Wraps the same form fields as legacy incident.php but in the tc6 3-column shell.
 */

$form = $form ?? [];
$flags = $flags ?? [];
$incident = $incident ?? [];
$meta = $meta ?? [];
$profile = $profile ?? ['articles' => []];
$multimodal = $multimodal ?? (array)($meta['_multimodal'] ?? []);

$airRights = (array)($multimodal['air_rights'] ?? ($airRights ?? []));
$airScope = (array)($multimodal['air_scope'] ?? ($airScope ?? []));
$airContract = (array)($multimodal['air_contract'] ?? ($airContract ?? []));
$uiLanguage = strtolower((string)($uiLanguage ?? 'da'));
$pageTranslations = (array)($pageTranslations ?? []);

if ($uiLanguage === 'en') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Ongoing journey',
        'Afsluttet rejse' => 'Completed journey',
        'Kommende rejse' => 'Upcoming journey',
        'Refund / ombooking' => 'Refund / rerouting',
        'Ansvarligt flyselskab' => 'Responsible airline',
        'Air haendelse + foreloebig vurdering' => 'Air incident + preliminary assessment',
        'Billet / Booking' => 'Ticket / booking',
        'Flyvalg' => 'Flight selection',
        'Haendelse + vurdering' => 'Incident + assessment',
        'Nedgradering' => 'Downgrade',
        'Kontakt & opret sag' => 'Contact & create case',
    ];
} elseif ($uiLanguage === 'fr') {
    $pageTranslations += [
        'Igangvaerende rejse' => 'Trajet en cours',
        'Afsluttet rejse' => 'Trajet termine',
        'Kommende rejse' => 'Voyage a venir',
        'Refund / ombooking' => 'Remboursement / reacheminement',
        'Ansvarligt flyselskab' => 'Compagnie responsable',
        'Air haendelse + foreloebig vurdering' => 'Incident air + evaluation preliminaire',
        'Billet / Booking' => 'Billet / reservation',
        'Flyvalg' => 'Choix du vol',
        'Haendelse + vurdering' => 'Incident + evaluation',
        'Nedgradering' => 'Declassement',
        'Kontakt & opret sag' => 'Contact et creation du dossier',
    ];
}
$this->set('pageTranslations', $pageTranslations);

$v = static fn(string $key, string $fallback = ''): string => (string)($form[$key] ?? $fallback);

$travelState = strtolower((string)($flags['travel_state'] ?? ($form['travel_state'] ?? '')));
$isOngoing = $travelState === 'ongoing';
$isCompleted = $travelState === 'completed';
$isPreview = !empty($flowPreview);

$articles = (array)($profile['articles'] ?? []);
$art9On = ($articles['art9'] ?? true) !== false;
$airRouteLegs = is_array($meta['air_route_legs'] ?? null) ? (array)$meta['air_route_legs'] : [];
$airRouteType = strtolower(trim((string)($form['air_route_type'] ?? '')));
$airMissedConnectionOptions = [];
foreach ($airRouteLegs as $airRouteLeg) {
    if (!is_array($airRouteLeg) || !empty($airRouteLeg['is_last_leg'])) {
        continue;
    }
    $airMissedConnectionLabel = trim((string)($airRouteLeg['arr_label'] ?? ''));
    if ($airMissedConnectionLabel === '' || in_array($airMissedConnectionLabel, $airMissedConnectionOptions, true)) {
        continue;
    }
    $airMissedConnectionOptions[] = $airMissedConnectionLabel;
}
$airHasExplicitStopovers = (
    $airRouteType === 'connecting'
    || ((string)($flags['air_has_stopovers'] ?? '') === '1')
    || count($airRouteLegs) > 1
);

$incidentMain = $v('incident_main');
$excValue = $v('operatorExceptionalCircumstances');
$pmrValue = $v('pmr_user');
$airDelayThresholdHours = (int)($form['air_delay_threshold_hours'] ?? ($airScope['air_delay_threshold_hours'] ?? 0));
$expectedDelayBucket = $v('air_expected_delay_bucket');
$actualArrivalBucket = $v('air_actual_arrival_delay_bucket');
$cancellationNoticeBand = $v('cancellation_notice_band');
$cancellationReasonInformed = $v('air_cancellation_reason_informed');
$cancellationReasonGiven = trim((string)($form['air_cancellation_reason_given'] ?? ''));
$rerouteOffered = $v('reroute_offered');
$voluntaryDenied = $v('voluntary_denied_boarding');
$deniedCalledForVolunteers = $v('air_denied_boarding_called_for_volunteers');
$deniedRefusalGround = $v('air_denied_boarding_refused_for_safety_security_health_documents');
$deniedAtGateOnTime = $v('air_denied_boarding_at_gate_on_time');
$protectedConnectionMissed = $v('protected_connection_missed');
$connectionProtectionBasis = $v('connection_protection_basis');
$airConnectionType = strtolower(trim((string)($airContract['air_connection_type'] ?? ($form['air_connection_type'] ?? ''))));
$operatorExceptionalType = trim((string)($form['operatorExceptionalType'] ?? ''));
$airMissedConnectionOptions = array_values(array_filter($airMissedConnectionOptions, static fn($value): bool => trim((string)$value) !== ''));
$airMissedConnectionSelected = trim((string)($form['missed_connection_station'] ?? ''));
if ($airMissedConnectionSelected === '') {
    foreach ($airRouteLegs as $airRouteLeg) {
        if (!is_array($airRouteLeg) || empty($airRouteLeg['is_problem_leg'])) {
            continue;
        }
        $airMissedConnectionSelected = trim((string)($airRouteLeg['arr_label'] ?? ''));
        if ($airMissedConnectionSelected !== '') {
            break;
        }
    }
}
if ($airMissedConnectionSelected === '' && count($airMissedConnectionOptions) === 1) {
    $airMissedConnectionSelected = $airMissedConnectionOptions[0];
}
if ($airMissedConnectionSelected === '' && $airMissedConnectionOptions !== []) {
    $airMissedConnectionSelected = $airMissedConnectionOptions[0];
}
$showAirMissedConnection = $airHasExplicitStopovers && $airMissedConnectionOptions !== [];
$showAirCancellationDetailQuestions = false;
$airConnectionKnown = in_array($airConnectionType, ['single_flight', 'protected_connection', 'self_transfer'], true);
$airConnectionNeedsFallback = !$airConnectionKnown || !empty($airContract['manual_review_required']);

$expectedDelayBuckets = [
    'under_threshold' => ($airDelayThresholdHours > 0 ? ('Under ' . $airDelayThresholdHours . ' timer') : 'Under threshold'),
    'threshold_to_under_5h' => ($airDelayThresholdHours > 0 ? ('Mindst ' . $airDelayThresholdHours . ' timer') : 'Threshold naaet'),
    'five_plus' => '5+ timer',
    'next_day' => 'Ny afgang foerst naeste dag',
    'unknown' => 'Ved ikke',
];

$delayBuckets = [
    'under_3h' => 'Under 3 timer',
    'three_to_four' => '3-3 timer 59 min',
    'four_plus' => 'Mere end 4 timer',
    'never_arrived' => 'Flyet ankom aldrig',
    'unknown' => 'Ved ikke',
];

$noticeBands = [
    '14_plus_days' => 'Mindst 14 dage foer',
    '7_to_13_days' => 'Mellem 14 og 7 dage foer',
    'under_7_days' => 'Under 7 dage foer',
    'airport_on_day_of_departure' => 'I lufthavnen / ved afgang',
    'unknown' => 'Ved ikke',
];

$cancellationReasonOptions = [
    '' => 'Vaelg aarsag...',
    'Tekniske problemer' => 'Tekniske problemer',
    'Strejke' => 'Strejke',
    'Daarlige vejrforhold' => 'Daarlige vejrforhold',
    'Andet' => 'Andet',
    'Krig eller terror' => 'Krig eller terror',
    'Problemer i lufthavnen' => 'Problemer i lufthavnen',
    'Et tidligere fly var forsinket' => 'Et tidligere fly var forsinket',
    'Ved ikke' => 'Ved ikke',
    'Covid-19' => 'Covid-19',
];
if ($cancellationReasonGiven !== '' && !array_key_exists($cancellationReasonGiven, $cancellationReasonOptions)) {
    $cancellationReasonOptions[$cancellationReasonGiven] = $cancellationReasonGiven;
}

$_tcStep = null;
foreach (($flowSteps ?? []) as $_s) {
    if ((string)($_s['action'] ?? '') === ($flowCurrentAction ?? '')) {
        $_tcStep = $_s;
        break;
    }
}
$_tcUiNum = (int)($_tcStep['ui_num'] ?? $_tcStep['num'] ?? 4);
$_tcUiTotal = (int)($_tcStep['ui_total'] ?? 0);
$_tcTitle = 'Air haendelse + foreloebig vurdering';

$incidentPrevAction = (string)($incidentPrevAction ?? ($flowPrevAction ?? 'entitlements'));
$flowQuery = ['tc6' => 1];
if ($uiLanguage !== 'da') {
    $flowQuery['lang'] = $uiLanguage;
}
$backUrl = html_entity_decode($this->Url->build(['action' => $incidentPrevAction, '?' => $flowQuery]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

$steps = $steps ?? [
    1 => 'Start & Rejsestatus',
    2 => 'Billet / Booking',
    3 => 'Flyvalg',
    4 => 'Haendelse + vurdering',
    5 => 'Nedgradering',
    6 => 'Kontakt & opret sag',
];
$currentStep = (int)($currentStep ?? 4);
$doneSteps = $doneSteps ?? [];

$progressPct = count($steps) > 0 ? (int)round(($currentStep / count($steps)) * 100) : 0;
$progressLabel = $currentStep . ' / ' . count($steps) . ' trin';
if ($uiLanguage === 'fr') {
    $progressLabel = $currentStep . ' / ' . count($steps) . ' etapes';
}

$travelStateLabel = $isOngoing ? 'Igangvaerende rejse' : ($isCompleted ? 'Afsluttet rejse' : 'Kommende rejse');
$context = $travelStateLabel;
$brandName = 'AirClaim';
$brandMark = 'AC';
$stats = [
    ['Rejse', $travelStateLabel, null],
    ['Transport', 'FLY', null],
];

$airOperatorLabel = '';
foreach ([
    $form['operator'] ?? null,
    $form['operating_carrier'] ?? null,
    $form['marketing_carrier'] ?? null,
    $airContract['operator_name'] ?? null,
    $meta['_auto']['operator']['value'] ?? null,
] as $candidateOperator) {
    $candidateOperator = trim((string)$candidateOperator);
    if ($candidateOperator !== '') {
        $airOperatorLabel = $candidateOperator;
        break;
    }
}
if ($airOperatorLabel === '') {
    $airOperatorLabel = 'Ikke valgt endnu';
}

$careActive = !empty($airRights['gate_air_care']);
$remedyActive = !empty($airRights['gate_air_reroute_refund']) || !empty($airRights['gate_air_delay_refund_5h']);

$summaryRows = [
    ['Care', $careActive ? 'Aktiv' : 'Afventer', $careActive ? 'green' : 'gray'],
    ['Refund / ombooking', $remedyActive ? 'Aktiv' : 'Afventer', $remedyActive ? 'green' : 'gray'],
    ['Ansvarligt flyselskab', $airOperatorLabel, null],
];
if ($uiLanguage === 'fr') {
    $translateAirIncident = static function ($text) use ($pageTranslations) {
        if (!is_string($text)) {
            return $text;
        }
        $extra = [
            'Rejse' => 'Voyage',
            'Aktiv' => 'Actif',
            'Afventer' => 'En attente',
            'Refund / ombooking' => 'Remboursement / reacheminement',
            'Care' => 'Assistance',
            'Ansvarligt flyselskab' => 'Compagnie aerienne responsable',
        ];
        if (isset($extra[$text])) {
            return $extra[$text];
        }
        if (isset($pageTranslations[$text])) {
            return $pageTranslations[$text];
        }

        return $text;
    };
    $steps = array_map($translateAirIncident, $steps);
    $context = $translateAirIncident($context);
    $stats = array_map(static function (array $row) use ($translateAirIncident): array {
        $row[0] = $translateAirIncident($row[0] ?? '');
        $row[1] = $translateAirIncident($row[1] ?? '');

        return $row;
    }, $stats);
    $summaryRows = array_map(static function (array $row) use ($translateAirIncident): array {
        $row[0] = $translateAirIncident($row[0] ?? '');
        $row[1] = $translateAirIncident($row[1] ?? '');

        return $row;
    }, $summaryRows);
}
?>
<?php $this->assign('title', h($pageTranslations[$_tcTitle] ?? $_tcTitle) . ' - ' . ($uiLanguage === 'fr' ? 'Etape ' : 'Trin ') . $_tcUiNum) ?>
<?php $this->start('css') ?>
<style>
.t6ai-hidden { display: none !important; }
.t6ai-delay-grid { display: grid; gap: 8px; margin-top: 10px; }
.t6ai-delay-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 16px;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.t6ai-delay-card:has(input:checked) {
    border-color: #2563eb;
    background: #eff6ff;
}
.t6ai-delay-card input[type="radio"] {
    margin: 0;
    flex-shrink: 0;
}
.t6ai-delay-label {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
}
.t6ai-info-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    color: #1d4ed8;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-bottom: 14px;
}
.t6ai-info-box-icon {
    font-size: 16px;
    flex-shrink: 0;
}
.t6ai-subsection {
    margin-top: 16px;
}
</style>
<?php $this->end() ?>
<?php $this->start('script') ?>
<?php
$airJsText = [
    'awaiting' => 'Afventer',
    'awaitingMore' => 'Afventer flere svar',
    'awaitingAnswer' => 'Afventer svar',
    'provisionalLevel' => 'Foreloebigt kompensationsniveau',
    'careCovered' => 'Rimelige noedvendige udgifter kan daekkes',
    'fullCoverage' => 'Fuld daekning mulig',
];
if ($uiLanguage === 'fr') {
    $airJsText = [
        'awaiting' => 'En attente',
        'awaitingMore' => 'En attente de davantage de reponses',
        'awaitingAnswer' => 'En attente de reponse',
        'provisionalLevel' => 'Niveau d indemnisation provisoire',
        'careCovered' => 'Les frais necessaires raisonnables peuvent etre couverts',
        'fullCoverage' => 'Couverture complete possible',
    ];
}
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-tc6-air-incident]');
    if (!form) {
        return;
    }
    var airUiText = <?= json_encode($airJsText, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    function getRadioValue(name) {
        var checked = form.querySelector('input[name="' + name + '"]:checked');
        return checked ? checked.value : '';
    }

    function setVisible(id, visible) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.classList.toggle('t6ai-hidden', !visible);
    }

    function setText(selector, value) {
        var nodes = document.querySelectorAll(selector);
        Array.prototype.forEach.call(nodes, function (node) {
            node.textContent = value;
        });
    }

    function normalizeTone(tone) {
        return tone === 'green' || tone === 'red' ? tone : 'gray';
    }

    function applyChipTone(node, tone) {
        if (!node) {
            return;
        }
        var normalized = normalizeTone(tone);
        ['gray', 'green', 'red'].forEach(function (candidate) {
            node.classList.toggle('tc6-live-chip--' + candidate, candidate === normalized);
        });
        if (normalized === 'green') {
            node.style.background = 'rgba(220,252,231,0.98)';
            node.style.borderColor = 'rgba(34,197,94,0.34)';
            node.style.color = '#166534';
            return;
        }
        if (normalized === 'red') {
            node.style.background = 'rgba(254,226,226,0.98)';
            node.style.borderColor = 'rgba(239,68,68,0.34)';
            node.style.color = '#b91c1c';
            return;
        }
        node.style.background = 'rgba(248,250,252,0.96)';
        node.style.borderColor = 'rgba(148,163,184,0.30)';
        node.style.color = '#475569';
    }

    function applyCardTone(node, tone) {
        if (!node) {
            return;
        }
        var normalized = normalizeTone(tone);
        ['gray', 'green', 'red'].forEach(function (candidate) {
            node.classList.toggle('tc6-live-card--' + candidate, candidate === normalized);
        });
        if (normalized === 'green') {
            node.style.background = 'rgba(240,253,244,0.98)';
            node.style.borderColor = 'rgba(34,197,94,0.28)';
            return;
        }
        if (normalized === 'red') {
            node.style.background = 'rgba(254,242,242,0.98)';
            node.style.borderColor = 'rgba(239,68,68,0.28)';
            return;
        }
        node.style.background = 'rgba(248,250,252,0.96)';
        node.style.borderColor = 'rgba(148,163,184,0.22)';
    }

    function applyPanelTone(root, tone) {
        if (!root) {
            return;
        }
        var normalized = normalizeTone(tone);
        var wrapper = root.closest('.tc6-panel-live-estimate');
        ['gray', 'green', 'red'].forEach(function (candidate) {
            root.classList.toggle('tc6-live-estimate--' + candidate, candidate === normalized);
            if (wrapper) {
                wrapper.classList.toggle('tc6-panel-live-estimate--' + candidate, candidate === normalized);
            }
        });
        root.dataset.liveTone = normalized;
        if (wrapper) {
            wrapper.dataset.liveTone = normalized;
        }
    }

    function formatAmount(value) {
        var numeric = parseFloat(value);
        if (!isFinite(numeric)) {
            return airUiText.awaiting;
        }
        return numeric.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' EUR';
    }

    function updateAirLiveEstimatePreview() {
        var root = document.getElementById('airLiveEstimate');
        if (!root) {
            return;
        }

        var hasKnownDistance = root.dataset.hasKnownDistance === '1';
        var travelState = String(root.dataset.travelState || '').toLowerCase();
        var passengerCount = parseInt(root.dataset.passengerCount || '1', 10);
        var potentialTotal = parseFloat(root.dataset.potentialTotal || '0');
        var reductionThreshold = parseInt(root.dataset.reductionThreshold || '0', 10);
        var incidentMain = getRadioValue('incident_main');
        var expectedDelay = getRadioValue('air_expected_delay_bucket');
        var actualArrival = getRadioValue('air_actual_arrival_delay_bucket');
        var notice = getRadioValue('cancellation_notice_band');
        var rerouteOffered = getRadioValue('reroute_offered');
        var rerouteUsed = getRadioValue('reroute_used_or_accepted');
        var departureWindow = getRadioValue('reroute_departure_band');
        var arrivalWindow = getRadioValue('reroute_arrival_band');
        var rerouteArrivalDelayRaw = form.querySelector('input[name="reroute_arrival_delay_minutes"]');
        var rerouteArrivalDelay = rerouteArrivalDelayRaw && rerouteArrivalDelayRaw.value !== ''
            ? parseInt(rerouteArrivalDelayRaw.value, 10)
            : NaN;
        var voluntaryDenied = getRadioValue('voluntary_denied_boarding');
        var deniedRefusalGround = getRadioValue('air_denied_boarding_refused_for_safety_security_health_documents');
        var deniedAtGateOnTime = getRadioValue('air_denied_boarding_at_gate_on_time');
        var extraordinary = getRadioValue('operatorExceptionalCircumstances');
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
                if (rerouteOffered === 'no') {
                    state = 'eligible';
                } else if (rerouteOffered === 'yes') {
                    if (departureWindow === 'within_window' && arrivalWindow === 'within_window') {
                        state = 'not_eligible';
                    } else if (departureWindow !== '' && arrivalWindow !== '' && departureWindow !== 'unknown' && arrivalWindow !== 'unknown') {
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
                if (deniedRefusalGround === 'yes' || deniedAtGateOnTime === 'no') {
                    state = 'not_eligible';
                } else if (deniedRefusalGround === 'no' && deniedAtGateOnTime === 'yes') {
                    state = 'eligible';
                } else if (travelState === 'completed') {
                    state = 'uncertain';
                } else {
                    state = 'preview';
                }
            }
        }

        var amountText = hasKnownDistance ? formatAmount(potentialTotal) : airUiText.awaiting;
        var statusText = hasKnownDistance ? airUiText.provisionalLevel : airUiText.awaitingMore;
        var statusTone = 'gray';

        if (state === 'eligible' && hasKnownDistance) {
            if (
                incidentMain === 'cancellation'
                && rerouteOffered === 'yes'
                && rerouteUsed === 'yes'
                && travelState === 'completed'
                && isFinite(reductionThreshold)
                && reductionThreshold > 0
                && isFinite(rerouteArrivalDelay)
                && rerouteArrivalDelay >= 0
                && rerouteArrivalDelay <= reductionThreshold
            ) {
                amountText = formatAmount(potentialTotal / 2);
                statusText = '50% reduktion';
            } else {
                amountText = formatAmount(potentialTotal);
                statusText = 'Kompensation mulig';
            }
            statusTone = 'green';
        } else if (state === 'not_eligible') {
            amountText = formatAmount(0);
            statusText = 'Ingen kompensation';
            statusTone = 'red';
        } else if (state === 'inactive') {
            amountText = formatAmount(0);
            statusText = 'Ikke aktiveret endnu';
            statusTone = 'red';
        } else if (state === 'uncertain') {
            amountText = hasKnownDistance ? formatAmount(potentialTotal) : airUiText.awaiting;
            statusText = 'Kompensation usikker';
        }

        setText('#airLiveEstimateAmount', amountText);
        setText('#airLiveEstimateStatus', statusText);
        setText('#airLiveEstimateCareValue', careActive ? airUiText.careCovered : airUiText.awaitingMore);
        setText('#airLiveEstimateRemedyValue', remedyActive ? airUiText.fullCoverage : airUiText.awaitingMore);

        applyPanelTone(root, statusTone);
        applyChipTone(root.querySelector('#airLiveEstimateStatus'), statusTone);
        applyCardTone(root.querySelector('[data-air-live-card="care"]'), careActive ? 'green' : 'gray');
        applyCardTone(root.querySelector('[data-air-live-card="remedy"]'), remedyActive ? 'green' : 'gray');

        var carrierNode = root.querySelector('[data-air-live-card="carrier"]');
        var carrierValue = carrierNode ? String((carrierNode.querySelector('.air-live-estimate-value') || {}).textContent || '').trim() : '';
        var carrierKnown = carrierValue !== '' && carrierValue.toLowerCase().indexOf(String(airUiText.awaitingAnswer).toLowerCase()) === -1;
        applyCardTone(carrierNode, carrierKnown ? 'green' : 'gray');
    }

    function syncAirIncidentUi() {
        var hasConnectionFallback = form.getAttribute('data-t6ai-connection-fallback') === '1';
        var usesBackendConnectionFollowup = form.getAttribute('data-t6ai-backend-connection-followup') === '1';
        var incidentMain = getRadioValue('incident_main');
        var voluntaryDenied = getRadioValue('voluntary_denied_boarding');
        var cancellationReasonInformed = getRadioValue('air_cancellation_reason_informed');
        var rerouteOffered = getRadioValue('reroute_offered');
        var connectionMissed = getRadioValue('protected_connection_missed');
        var extraordinary = getRadioValue('operatorExceptionalCircumstances');

        setVisible('t6aiDelaySection', incidentMain === 'delay');
        setVisible('t6aiCancellationSection', incidentMain === 'cancellation');
        setVisible('t6aiDeniedSection', incidentMain === 'denied_boarding');
        setVisible(
            't6aiCancellationReasonSelectWrap',
            incidentMain === 'cancellation' && cancellationReasonInformed === 'yes'
        );
        setVisible(
            't6aiDeniedDetailWrap',
            incidentMain === 'denied_boarding' && voluntaryDenied === 'no'
        );
        setVisible(
            't6aiMissedConnectionDetailWrap',
            connectionMissed === 'yes'
        );
        setVisible(
            't6aiConnectionProtectionWrap',
            connectionMissed === 'yes' && hasConnectionFallback
        );
        setVisible(
            't6aiConnectionBackendFollowupWrap',
            connectionMissed === 'yes' && usesBackendConnectionFollowup
        );
        setVisible(
            't6aiExceptionalTypeWrap',
            extraordinary === 'yes'
        );

        updateAirLiveEstimatePreview();
    }

    form.addEventListener('change', syncAirIncidentUi);
    form.addEventListener('input', syncAirIncidentUi);
    syncAirIncidentUi();
});
</script>
<?php $this->end() ?>

<?php
ob_start();
?>

<?= $this->element('flow_locked_notice') ?>

<?= $this->Form->create(null, [
    'url' => ['action' => 'incident', '?' => $flowQuery],
    'id' => 'airIncidentForm',
    'class' => 'tc6-form',
    'data-tc6-air-incident' => '1',
    'data-air-progressive-form' => 'incident',
    'data-t6ai-owns-cancellation-details' => $showAirCancellationDetailQuestions ? '1' : '0',
    'data-t6ai-connection-fallback' => $airConnectionNeedsFallback ? '1' : '0',
    'data-t6ai-backend-connection-followup' => '0',
    'novalidate' => true,
]) ?>

<fieldset <?= $isPreview ? 'disabled' : '' ?>>

  <div class="tc6-chip">Trin <?= $_tcUiNum ?><?= $_tcUiTotal > 0 ? ' / ' . $_tcUiTotal : '' ?></div>
  <h1 class="tc6-h1">Hvad skete der med dit fly?</h1>
  <p class="tc6-subtitle">Vaelg haendelsestype og besvar spoergsmaalene nedenfor. Estimatet i hoejre kolonne opdateres loebende.</p>

  <div class="tc6-card" id="t6aiIncidentTypeCard" data-progressive-group="incident-type" data-progressive-fields="incident_main">
    <div class="tc6-section-label">Haendelsestype</div>
    <div class="tc6-choice-cards tc6-choice-cards--3">
      <label class="tc6-choice-card">
        <input type="radio" name="incident_main" value="delay" <?= $incidentMain === 'delay' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body">
          <span class="tc6-choice-card__title">Forsinkelse</span>
          <span class="tc6-choice-card__sub">Dit fly landede for sent ved din slutdestination.</span>
        </span>
      </label>
      <label class="tc6-choice-card">
        <input type="radio" name="incident_main" value="cancellation" <?= $incidentMain === 'cancellation' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body">
          <span class="tc6-choice-card__title">Aflysning</span>
          <span class="tc6-choice-card__sub">Flyet blev aflyst af flyselskabet.</span>
        </span>
      </label>
      <label class="tc6-choice-card">
        <input type="radio" name="incident_main" value="denied_boarding" <?= $incidentMain === 'denied_boarding' ? 'checked' : '' ?> />
        <span class="tc6-choice-card__body">
          <span class="tc6-choice-card__title">Afvist boarding</span>
          <span class="tc6-choice-card__sub">Du fik ikke lov at gaa om bord paa dit fly.</span>
        </span>
      </label>
    </div>
  </div>

  <div id="t6aiDelaySection">
    <div class="tc6-card" data-progressive-group="delay-expected" data-progressive-show-if="incident_main:delay" data-progressive-fields="air_expected_delay_bucket" data-progressive-clear="air_expected_delay_bucket,air_actual_arrival_delay_bucket,delay_departure_band,delay_minutes_departure,arrival_delay_minutes,air_next_day_departure">
      <div class="tc6-section-label">Forsinkelse</div>
      <div class="t6ai-info-box">
        <span class="t6ai-info-box-icon">&#x2139;</span>
        <span><?= $isCompleted ? 'Registrer baade den meldte og den faktiske forsinkelse.' : 'Svar ud fra den forsinkelse, flyselskabet har meldt lige nu.' ?></span>
      </div>
      <div class="t6ai-delay-grid">
        <?php foreach ($expectedDelayBuckets as $key => $label): ?>
          <label class="t6ai-delay-card">
            <input type="radio" name="air_expected_delay_bucket" value="<?= h($key) ?>" <?= $expectedDelayBucket === $key ? 'checked' : '' ?> />
            <span class="t6ai-delay-label"><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <?php if ($isCompleted): ?>
      <div class="t6ai-subsection" data-progressive-group="delay-actual" data-progressive-show-if="incident_main:delay" data-progressive-fields="air_actual_arrival_delay_bucket">
        <div class="tc6-section-label">Faktisk forsinkelse ved slutdestination</div>
        <div class="t6ai-info-box">
          <span class="t6ai-info-box-icon">&#x2139;</span>
          <span>Svar ud fra din faktiske ankomsttid ved slutdestination, ikke forsinkelses-board ved gate.</span>
        </div>
        <div class="t6ai-delay-grid">
        <?php foreach ($delayBuckets as $key => $label): ?>
          <label class="t6ai-delay-card">
            <input type="radio" name="air_actual_arrival_delay_bucket" value="<?= h($key) ?>" <?= $actualArrivalBucket === $key ? 'checked' : '' ?> />
            <span class="t6ai-delay-label"><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="air_actual_arrival_delay_bucket" value="" />
      <?php endif; ?>
      <input type="hidden" name="delay_departure_band" value="<?= h((string)($form['delay_departure_band'] ?? '')) ?>" />
      <input type="hidden" name="delay_minutes_departure" value="<?= h((string)($form['delay_minutes_departure'] ?? '')) ?>" />
      <input type="hidden" name="arrival_delay_minutes" value="<?= h((string)($form['arrival_delay_minutes'] ?? '')) ?>" />
      <input type="hidden" name="air_next_day_departure" value="<?= h((string)($form['air_next_day_departure'] ?? '')) ?>" />
    </div>
  </div>

  <div id="t6aiCancellationSection">
    <div class="tc6-card" data-progressive-group="cancellation-notice" data-progressive-show-if="incident_main:cancellation" data-progressive-fields="cancellation_notice_band" data-progressive-clear="cancellation_notice_band,air_cancellation_reason_informed,air_cancellation_reason_given,reroute_offered">
      <div class="tc6-section-label">Hvornaar fik du besked om aflysningen?</div>
      <div class="t6ai-info-box">
        <span class="t6ai-info-box-icon">&#x2139;</span>
        <span>Varslet afgoer, om flyselskabet pligter at tilbyde ombooking og kompensation.</span>
      </div>
      <div class="t6ai-delay-grid">
        <?php foreach ($noticeBands as $key => $label): ?>
          <label class="t6ai-delay-card">
            <input type="radio" name="cancellation_notice_band" value="<?= h($key) ?>" <?= $cancellationNoticeBand === $key ? 'checked' : '' ?> />
            <span class="t6ai-delay-label"><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <?php if ($isCompleted): ?>
      <div class="t6ai-subsection" data-progressive-group="cancellation-reason" data-progressive-show-if="incident_main:cancellation" data-progressive-fields="air_cancellation_reason_informed">
        <div class="tc6-section-label">Begrundelse for aflysning</div>
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="air_cancellation_reason_informed" value="yes" <?= $cancellationReasonInformed === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Ja</span>
              <span class="tc6-choice-card__sub">Flyselskabet gav en konkret forklaring.</span>
            </span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="air_cancellation_reason_informed" value="no" <?= $cancellationReasonInformed === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Nej</span>
              <span class="tc6-choice-card__sub">Ingen egentlig begrundelse blev oplyst.</span>
            </span>
          </label>
        </div>
        <div id="t6aiCancellationReasonSelectWrap" class="t6ai-subsection" data-progressive-group="cancellation-reason" data-progressive-show-if="incident_main:cancellation;air_cancellation_reason_informed:yes" data-progressive-fields="air_cancellation_reason_given" data-progressive-clear="air_cancellation_reason_given">
          <label class="tc6-label" for="t6aiCancellationReasonSelect">Hvilken begrundelse fik du?</label>
          <select id="t6aiCancellationReasonSelect" name="air_cancellation_reason_given" class="tc6-input">
            <?php foreach ($cancellationReasonOptions as $reasonValue => $reasonLabel): ?>
              <option value="<?= h($reasonValue) ?>" <?= $cancellationReasonGiven === $reasonValue ? 'selected' : '' ?>><?= h($reasonLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="air_cancellation_reason_informed" value="<?= h($cancellationReasonInformed) ?>" />
      <input type="hidden" name="air_cancellation_reason_given" value="<?= h($cancellationReasonGiven) ?>" />
      <?php endif; ?>

      <div class="t6ai-subsection" data-progressive-group="cancellation-reroute" data-progressive-show-if="incident_main:cancellation" data-progressive-fields="reroute_offered">
        <div class="tc6-section-label">Tilboed flyselskabet en alternativ flyvning?</div>
        <div class="tc6-choice-cards tc6-choice-cards--3">
          <label class="tc6-choice-card">
            <input type="radio" name="reroute_offered" value="yes" <?= $rerouteOffered === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="reroute_offered" value="no" <?= $rerouteOffered === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="reroute_offered" value="unknown" <?= $rerouteOffered === 'unknown' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ved ikke</span></span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div id="t6aiDeniedSection">
    <div class="tc6-card" data-progressive-group="<?= $isCompleted ? 'denied-volunteers' : 'denied-voluntary' ?>" data-progressive-show-if="incident_main:denied_boarding" data-progressive-fields="<?= $isCompleted ? 'air_denied_boarding_called_for_volunteers' : 'voluntary_denied_boarding' ?>" data-progressive-clear="air_denied_boarding_called_for_volunteers,voluntary_denied_boarding,air_denied_boarding_refused_for_safety_security_health_documents,air_denied_boarding_at_gate_on_time,boarding_denied">
      <div class="tc6-section-label">Afvisning af boarding</div>
      <?php if ($isCompleted): ?>
      <div class="t6ai-subsection"<?= $isCompleted ? ' data-progressive-group="denied-voluntary" data-progressive-show-if="incident_main:denied_boarding" data-progressive-fields="voluntary_denied_boarding"' : '' ?>>
        <div class="tc6-section-label">Kaldte flyselskabet efter frivillige?</div>
        <div class="tc6-choice-cards tc6-choice-cards--3">
          <label class="tc6-choice-card">
            <input type="radio" name="air_denied_boarding_called_for_volunteers" value="yes" <?= $deniedCalledForVolunteers === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="air_denied_boarding_called_for_volunteers" value="no" <?= $deniedCalledForVolunteers === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="air_denied_boarding_called_for_volunteers" value="unknown" <?= $deniedCalledForVolunteers === 'unknown' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ved ikke</span></span>
          </label>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="air_denied_boarding_called_for_volunteers" value="<?= h($deniedCalledForVolunteers) ?>" />
      <?php endif; ?>

      <div class="t6ai-subsection">
        <div class="tc6-choice-cards tc6-choice-cards--2">
          <label class="tc6-choice-card">
            <input type="radio" name="voluntary_denied_boarding" value="no" <?= $voluntaryDenied === 'no' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Ufrivillig afvisning</span>
              <span class="tc6-choice-card__sub">Flyselskabet afviste dig mod din vilje, fx ved overbooking.</span>
            </span>
          </label>
          <label class="tc6-choice-card">
            <input type="radio" name="voluntary_denied_boarding" value="yes" <?= $voluntaryDenied === 'yes' ? 'checked' : '' ?> />
            <span class="tc6-choice-card__body">
              <span class="tc6-choice-card__title">Frivillig afvisning</span>
              <span class="tc6-choice-card__sub">Du accepterede en godtgoerelse og gik frivilligt af flyet.</span>
            </span>
          </label>
        </div>
      </div>

      <?php if ($isCompleted): ?>
      <div id="t6aiDeniedDetailWrap" class="t6ai-subsection" data-progressive-group="denied-details" data-progressive-show-if="incident_main:denied_boarding;voluntary_denied_boarding:no" data-progressive-fields="air_denied_boarding_refused_for_safety_security_health_documents,air_denied_boarding_at_gate_on_time" data-progressive-clear="air_denied_boarding_refused_for_safety_security_health_documents,air_denied_boarding_at_gate_on_time">
        <div class="tc6-field">
          <label class="tc6-label">Blev du afvist af hensyn til sikkerhed, security, helbred eller manglende rejsedokumenter?</label>
          <div class="tc6-choice-cards tc6-choice-cards--3" style="margin-top:10px">
            <label class="tc6-choice-card">
              <input type="radio" name="air_denied_boarding_refused_for_safety_security_health_documents" value="yes" <?= $deniedRefusalGround === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="air_denied_boarding_refused_for_safety_security_health_documents" value="no" <?= $deniedRefusalGround === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="air_denied_boarding_refused_for_safety_security_health_documents" value="unknown" <?= $deniedRefusalGround === 'unknown' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ved ikke</span></span>
            </label>
          </div>
        </div>
        <div class="tc6-field" style="margin-top:16px">
          <label class="tc6-label">Meldte du dig ved gaten senest paa det tidspunkt, der stod paa boardingkortet?</label>
          <div class="tc6-choice-cards tc6-choice-cards--2" style="margin-top:10px">
            <label class="tc6-choice-card">
              <input type="radio" name="air_denied_boarding_at_gate_on_time" value="yes" <?= $deniedAtGateOnTime === 'yes' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
            </label>
            <label class="tc6-choice-card">
              <input type="radio" name="air_denied_boarding_at_gate_on_time" value="no" <?= $deniedAtGateOnTime === 'no' ? 'checked' : '' ?> />
              <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
            </label>
          </div>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="air_denied_boarding_refused_for_safety_security_health_documents" value="<?= h($deniedRefusalGround) ?>" />
      <input type="hidden" name="air_denied_boarding_at_gate_on_time" value="<?= h($deniedAtGateOnTime) ?>" />
      <?php endif; ?>
      <input type="hidden" name="boarding_denied" value="<?= h($incidentMain === 'denied_boarding' ? 'yes' : ((string)($form['boarding_denied'] ?? ''))) ?>" />
    </div>
  </div>

  <?php if ($showAirMissedConnection): ?>
  <div class="tc6-card" data-progressive-group="missed-connection" data-progressive-fields="protected_connection_missed" data-progressive-clear="missed_connection_station,connection_protection_basis">
    <div class="tc6-section-label">Missed connection</div>
    <div class="tc6-field">
      <label class="tc6-label">Mistede du en videre forbindelse pga. haendelsen?</label>
      <div class="tc6-choice-cards tc6-choice-cards--2" style="margin-top:10px">
        <label class="tc6-choice-card">
          <input type="radio" name="protected_connection_missed" value="yes" <?= $protectedConnectionMissed === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Ja</span></span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="protected_connection_missed" value="no" <?= $protectedConnectionMissed === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body"><span class="tc6-choice-card__title">Nej</span></span>
        </label>
      </div>
    </div>
    <div id="t6aiMissedConnectionDetailWrap" class="t6ai-subsection" data-progressive-group="missed-connection-details" data-progressive-show-if="protected_connection_missed:yes"<?= $airConnectionNeedsFallback ? ' data-progressive-fields="connection_protection_basis"' : ' data-progressive-complete="always"' ?> data-progressive-clear="missed_connection_station,connection_protection_basis">
      <?php if ($airMissedConnectionOptions !== []): ?>
      <div class="tc6-field">
        <label class="tc6-label" for="t6aiMissedConnectionStation">Hvor mistede du forbindelsen?</label>
        <select id="t6aiMissedConnectionStation" name="missed_connection_station" class="tc6-input">
          <?php foreach ($airMissedConnectionOptions as $airMissedConnectionOption): ?>
            <option value="<?= h($airMissedConnectionOption) ?>" <?= $airMissedConnectionSelected === $airMissedConnectionOption ? 'selected' : '' ?>><?= h('Ved skift i ' . $airMissedConnectionOption) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($airConnectionNeedsFallback): ?>
      <div id="t6aiConnectionProtectionWrap" class="tc6-field" style="margin-top:16px">
        <label class="tc6-label" for="t6aiConnectionProtectionBasis">Hvad bygger forbindelsen paa?</label>
        <select id="t6aiConnectionProtectionBasis" name="connection_protection_basis" class="tc6-input">
          <option value="">- Vaelg grundlag -</option>
          <option value="same_booking_reference" <?= $connectionProtectionBasis === 'same_booking_reference' ? 'selected' : '' ?>>Samme bookingreference / PNR</option>
          <option value="same_ticket" <?= $connectionProtectionBasis === 'same_ticket' ? 'selected' : '' ?>>Samme billet / ticket chain</option>
          <option value="same_airline_interline" <?= $connectionProtectionBasis === 'same_airline_interline' ? 'selected' : '' ?>>Samme airline / interline-forloeb</option>
          <option value="separate_tickets" <?= $connectionProtectionBasis === 'separate_tickets' ? 'selected' : '' ?>>Saerskilte billetter</option>
          <option value="unclear" <?= $connectionProtectionBasis === 'unclear' ? 'selected' : '' ?>>Uklart</option>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="connection_protection_basis" value="" />
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <input type="hidden" name="protected_connection_missed" value="no" />
  <input type="hidden" name="connection_protection_basis" value="" />
  <?php endif; ?>

  <div class="tc6-card" data-progressive-group="extraordinary" data-progressive-fields="operatorExceptionalCircumstances">
    <div class="tc6-section-label">Ekstraordinaere omstaendigheder</div>
    <div class="tc6-field">
      <label class="tc6-label">Har flyselskabet henvist til ekstraordinaere omstaendigheder, fx vejr, strejke eller sikkerhed?</label>
      <div class="tc6-choice-cards tc6-choice-cards--3" style="margin-top:10px">
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="yes" <?= $excValue === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
            <span class="tc6-choice-card__sub">Flyselskabet har givet en force majeure-begrundelse.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="no" <?= $excValue === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
            <span class="tc6-choice-card__sub">Ingen saadan begrundelse er modtaget.</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="operatorExceptionalCircumstances" value="unknown" <?= ($excValue === '' || $excValue === 'unknown') ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ved ikke</span>
          </span>
        </label>
      </div>
      <div id="t6aiExceptionalTypeWrap" class="t6ai-subsection" data-progressive-group="extraordinary" data-progressive-show-if="operatorExceptionalCircumstances:yes" data-progressive-fields="operatorExceptionalType" data-progressive-clear="operatorExceptionalType">
        <label class="tc6-label" for="t6aiExceptionalType">Hvilken grund oplyste flyselskabet?</label>
        <select id="t6aiExceptionalType" name="operatorExceptionalType" class="tc6-input">
          <option value="">- Vaelg grund -</option>
          <option value="weather" <?= $operatorExceptionalType === 'weather' ? 'selected' : '' ?>>Vejr</option>
          <option value="air_traffic_control" <?= $operatorExceptionalType === 'air_traffic_control' ? 'selected' : '' ?>>ATC / luftrum</option>
          <option value="security" <?= $operatorExceptionalType === 'security' ? 'selected' : '' ?>>Sikkerhed</option>
          <option value="external_strike" <?= $operatorExceptionalType === 'external_strike' ? 'selected' : '' ?>>Ekstern strejke</option>
          <option value="own_staff_strike" <?= $operatorExceptionalType === 'own_staff_strike' ? 'selected' : '' ?>>Egen personalestrejke</option>
          <option value="technical_issue" <?= $operatorExceptionalType === 'technical_issue' ? 'selected' : '' ?>>Teknisk fejl</option>
          <option value="crew_shortage" <?= $operatorExceptionalType === 'crew_shortage' ? 'selected' : '' ?>>Crew / bemanding</option>
          <option value="other" <?= $operatorExceptionalType === 'other' ? 'selected' : '' ?>>Andet</option>
        </select>
      </div>
    </div>
  </div>
  <input type="hidden" name="extraordinary_circumstances" value="" />

  <?php if ($art9On): ?>
  <div class="tc6-card" data-progressive-group="pmr" data-progressive-fields="pmr_user">
    <div class="tc6-section-label">PMR / saerlig assistance (Art. 11)</div>
    <div class="tc6-field">
      <label class="tc6-label">Har den rejsende nedsat mobilitet eller behov for saerlig assistance?</label>
      <div class="tc6-choice-cards tc6-choice-cards--2" style="margin-top:10px">
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_user" value="yes" <?= $pmrValue === 'yes' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Ja</span>
          </span>
        </label>
        <label class="tc6-choice-card">
          <input type="radio" name="pmr_user" value="no" <?= $pmrValue === 'no' ? 'checked' : '' ?> />
          <span class="tc6-choice-card__body">
            <span class="tc6-choice-card__title">Nej</span>
          </span>
        </label>
      </div>
    </div>
  </div>
  <?php endif; ?>

</fieldset>

<div data-progressive-group="actions" data-progressive-complete="always">
<?= $this->element('tc6/action_bar', [
    'backUrl' => $backUrl,
    'backLabel' => $uiLanguage === 'fr' ? 'Retour' : 'Tilbage',
    'nextLabel' => $uiLanguage === 'fr' ? 'Etape suivante' : 'Naeste trin',
    'nextVariant' => 'navy',
    'submitName' => '_save',
]) ?>
</div>

<?= $this->Form->end() ?>

<?php
$content = ob_get_clean();
if ($uiLanguage === 'fr') {
    $content = strtr($content, array_merge($pageTranslations, [
        'Trin ' => 'Etape ',
        'Hvad skete der med dit fly?' => 'Que s est-il passe avec votre vol ?',
        'Vaelg haendelsestype og besvar spoergsmaalene nedenfor. Estimatet i hoejre kolonne opdateres loebende.' => 'Choisissez le type d incident et repondez aux questions ci-dessous. L estimation dans la colonne de droite se met a jour en continu.',
        'Haendelsestype' => 'Type d incident',
        'Forsinkelse' => 'Retard',
        'Dit fly landede for sent ved din slutdestination.' => 'Votre vol est arrive trop tard a destination finale.',
        'Aflysning' => 'Annulation',
        'Flyet blev aflyst af flyselskabet.' => 'Le vol a ete annule par la compagnie aerienne.',
        'Afvist boarding' => 'Refus d embarquement',
        'Du fik ikke lov at gaa om bord paa dit fly.' => 'Vous n avez pas ete autorise a embarquer sur votre vol.',
        'Registrer baade den meldte og den faktiske forsinkelse.' => 'Indiquez a la fois le retard annonce et le retard reel.',
        'Svar ud fra den forsinkelse, flyselskabet har meldt lige nu.' => 'Repondez selon le retard annonce actuellement par la compagnie.',
        'Faktisk forsinkelse ved slutdestination' => 'Retard reel a destination finale',
        'Svar ud fra din faktiske ankomsttid ved slutdestination, ikke forsinkelses-board ved gate.' => 'Repondez selon votre heure d arrivee reelle a destination finale, et non selon le tableau a la porte.',
        'Under 3 timer' => 'Moins de 3 heures',
        'Under threshold' => 'Sous le seuil',
        'Threshold naaet' => 'Seuil atteint',
        'Under ' => 'Moins de ',
        'Mindst ' => 'Au moins ',
        ' timer' => ' heures',
        '3-3 timer 59 min' => '3 h a 3 h 59',
        'Mere end 4 timer' => 'Plus de 4 heures',
        'Flyet ankom aldrig' => 'Le vol n est jamais arrive',
        'Ny afgang foerst naeste dag' => 'Nouveau depart seulement le lendemain',
        'Hvornaar fik du besked om aflysningen?' => 'Quand avez-vous ete informe de l annulation ?',
        'Mindst 14 dage foer' => 'Au moins 14 jours avant',
        'Mellem 14 og 7 dage foer' => 'Entre 14 et 7 jours avant',
        'Under 7 dage foer' => 'Moins de 7 jours avant',
        'I lufthavnen / ved afgang' => 'A l aeroport / au depart',
        'Blev du tilbudt rerouting, og hvordnaar?' => 'Un reacheminement vous a-t-il ete propose, et quand ?',
        'Begrundelse for aflysning' => 'Motif de l annulation',
        'Fik du en konkret begrundelse?' => 'Avez-vous recu une justification concrete ?',
        'Hvilken begrundelse fik du?' => 'Quelle justification avez-vous recue ?',
        'Vaelg aarsag...' => 'Choisir une raison...',
        'Tekniske problemer' => 'Problemes techniques',
        'Strejke' => 'Greve',
        'Daarlige vejrforhold' => 'Mauvaises conditions meteorologiques',
        'Krig eller terror' => 'Guerre ou terrorisme',
        'Problemer i lufthavnen' => 'Problemes a l aeroport',
        'Et tidligere fly var forsinket' => 'Un vol precedent etait en retard',
        'Afvist boarding' => 'Refus d embarquement',
        'Missed connection' => 'Correspondance manquee',
        'Mistede du en videre forbindelse pga. haendelsen?' => 'Avez-vous manque une correspondance a cause de l incident ?',
        'Hvor mistede du forbindelsen?' => 'Ou avez-vous manque la correspondance ?',
        'Ved skift i ' => 'Lors de la correspondance a ',
        'Hvad bygger forbindelsen paa?' => 'Sur quoi repose la correspondance ?',
        '- Vaelg grundlag -' => '- Choisir la base -',
        'Samme bookingreference / PNR' => 'Meme reference de reservation / PNR',
        'Samme billet / ticket chain' => 'Meme billet / chaine de billets',
        'Samme airline / interline-forloeb' => 'Meme compagnie / parcours interligne',
        'Saerskilte billetter' => 'Billets separes',
        'Uklart' => 'Pas clair',
        'Ekstraordinaere omstaendigheder' => 'Circonstances extraordinaires',
        'Har flyselskabet henvist til ekstraordinaere omstaendigheder, fx vejr, strejke eller sikkerhed?' => 'La compagnie aerienne a-t-elle invoque des circonstances extraordinaires, par exemple meteo, greve ou securite ?',
        'Flyselskabet har givet en force majeure-begrundelse.' => 'La compagnie aerienne a donne une justification de force majeure.',
        'Ingen saadan begrundelse er modtaget.' => 'Aucune justification de ce type n a ete recue.',
        'Hvilken grund oplyste flyselskabet?' => 'Quelle raison la compagnie aerienne a-t-elle indiquee ?',
        '- Vaelg grund -' => '- Choisir la raison -',
        'Vejr' => 'Meteo',
        'ATC / luftrum' => 'Controle aerien / espace aerien',
        'Sikkerhed' => 'Securite',
        'Ekstern strejke' => 'Greve externe',
        'Egen personalestrejke' => 'Greve du personnel propre',
        'Teknisk fejl' => 'Panne technique',
        'Crew / bemanding' => 'Equipage / effectifs',
        'PMR / saerlig assistance (Art. 11)' => 'PMR / assistance particuliere (art. 11)',
        'Har den rejsende nedsat mobilitet eller behov for saerlig assistance?' => 'Le voyageur a-t-il une mobilite reduite ou besoin d une assistance particuliere ?',
        'Ja' => 'Oui',
        'Nej' => 'Non',
        'Ved ikke' => 'Je ne sais pas',
        'Tilbage' => 'Retour',
        'Naeste trin' => 'Etape suivante',
        'Foreloebigt kompensationsniveau' => 'Niveau d indemnisation provisoire',
        'Afventer flere svar' => 'En attente de davantage de reponses',
        'Fuld daekning mulig' => 'Couverture complete possible',
        'Rimelige noedvendige udgifter kan daekkes' => 'Les frais necessaires raisonnables peuvent etre couverts',
        'Afventer svar' => 'En attente de reponse',
        'Afventer' => 'En attente',
    ]));
}

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
    'nextHint' => 'Udfyld haendelsestype og svar paa spoergsmaalene. Hoejre kolonne samler status og foreloebigt estimat.',
    'liveEstimateHtml' => $liveEstimateHtml,
    'uiLanguage' => $uiLanguage,
]);

echo $this->element('tc6/shell', compact('steps', 'currentStep', 'doneSteps', 'content', 'rightPanel', 'context', 'brandName', 'brandMark'));
